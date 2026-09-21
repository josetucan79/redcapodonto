<?php
namespace ExternalModules;

use Exception;

$exit = function($message){
	if(ExternalModules::isTesting()){
		throw new \Exception($message);
	}
	else{
		echo $message;
		exit(1);
	}
};

// Redirect any EM passthru requests to the appropriate handler
if (defined("EM_ENDPOINT") || defined("EM_SURVEY_ENDPOINT")) {
	// A prefix must be present
	$prefix = ExternalModules::getPrefix();
	if (empty($prefix)) {
		throw new Exception(ExternalModules::tt('em_errors_123'));
	}
	// Module AJAX request
	if (isset($_GET["ajax"]) && $_GET["ajax"] == "1") {
		require_once __DIR__ . "/module-ajax/jsmo-ajax.php";
		return;
	}
	// Unknown request type
	$exit(ExternalModules::tt("em_errors_185")); // Malformed AJAX request.
}

$page = $_GET['page'] ?? '';
if(!is_string($page)){
	$exit(ExternalModules::tt('em_errors_127'));
}

$page = rawurldecode(urldecode($page));

// We set NOAUTH for get-file.php requests to support old Inline Popup module settings
// that include old URLs that point to this file directly instead of going through the API.
// We shouldn't remove this without some plan to support or update those old URLs.
$isGetFilePage = $page === '/manager/rich-text/get-file.php';
$noAuth = isset($_GET['NOAUTH']) || $isGetFilePage;
if($noAuth && !defined('NOAUTH')){
	// This must be defined at the top before redcap_connect.php is required.
	define('NOAUTH', true);
}

// We call redcap_connect.php before loading any classes to make sure redirections from previous REDCap
// version URLs happen first.  We don't want to try to load old and new versions of the same class.
require_once __DIR__ . '/redcap_connect.php';

if($isGetFilePage){
	require_once __DIR__ . $page;
	return;
}

$pid = ExternalModules::getProjectId();

$prefix = ExternalModules::getPrefix();
if(empty($prefix)){
	$prefix = ExternalModules::getPrefixForID($_GET['id'] ?? null);
	if(empty($prefix)){
		$exit(ExternalModules::tt('em_errors_123'));
	}
}

if($prefix === ExternalModules::TEST_MODULE_PREFIX){
	$version = ExternalModules::TEST_MODULE_VERSION;
}
else{
	$version = ExternalModules::getEnabledVersion($prefix);
}

if(empty($version)){
	$exit(ExternalModules::tt('em_errors_124', $prefix));
}

$config = ExternalModules::getConfig($prefix, $version);
if($noAuth && !@in_array($page, $config['no-auth-pages'] ?? [])){
	$exit(ExternalModules::tt('em_errors_125'));
}

$getLink = function () use ($prefix, $version, $page) {
	$links = ExternalModules::getLinks($prefix, $version);
	foreach ($links as $link) {
		if ($link['url'] == ExternalModules::getPageUrl($prefix, $page)) {
			return $link;
		}
	}

	return null;
};

$link = $getLink();
$showHeaderAndFooter = !$noAuth && ($link['show-header-and-footer'] ?? null) === true;
if($pid != null){
	$enabledGlobal = ExternalModules::getSystemSetting($prefix,ExternalModules::KEY_ENABLED);
	$enabled = ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::KEY_ENABLED);
	if(!$enabled && !$enabledGlobal){
		$exit(ExternalModules::tt('em_errors_126', $prefix, $pid));
	}

	$headerPath = 'ProjectGeneral/header.php';
	$footerPath = 'ProjectGeneral/footer.php';
}
else{
	$headerPath = 'ControlCenter/header.php';
	$footerPath = 'ControlCenter/footer.php';
}

$pageExtension = strtolower(pathinfo($page, PATHINFO_EXTENSION));
$pagePath = $page . ($pageExtension == '' ? ".php" : "");

$modulePath = ExternalModules::getModuleDirectoryPath($prefix, $version);
if(!$modulePath){
	$exit(ExternalModules::tt('em_manage_66', $pagePath));
}

$pagePath = ExternalModules::getSafePath($pagePath, $modulePath);

if(!file_exists($pagePath)){
	$exit(ExternalModules::tt('em_errors_127'));
}

$checkLinkPermission = function ($module) use ($pid, $link, $exit): void {
	if (!$link) {
		// This url is not defined in config.json.  Allow it to work for backward compatibility.
		return;
	}

	$link = $module->redcap_module_link_check_display($pid, $link);
	if (!$link) {
		$exit(ExternalModules::tt('em_errors_128'));
	}
};

switch ($pageExtension) {
    case "php":
    case "":
		// PHP content
		$framework = ExternalModules::getFrameworkInstance($prefix, $version);
		$framework->checkCSRFToken($page);
		
        // Leave setting permissions up to module authors.
		// The redcap_module_link_check_display() hook already limits access to design rights users by default.
		// No additional security should be required.
		$framework->disableUserBasedSettingPermissions();

		$checkLinkPermission($framework->getModuleInstance());

		if($showHeaderAndFooter){
			require_once APP_PATH_DOCROOT . $headerPath;
		}

		// Define the module instance for use within the required file.
		$module = $framework->getModuleInstance();
		ExternalModules::psalmSuppress($module);

		// This should technically be a 'require_once' call,
		// but we use 'require' here to make unit testing easier.
		require $pagePath;

		if($showHeaderAndFooter){
			require_once APP_PATH_DOCROOT . $footerPath;
		}
		break;

	case "md":
		// Markdown Syntax
		$Parsedown = new \Parsedown();
		$html = $Parsedown->text(file_get_contents($pagePath));
		// Optional dark or light theme
		$theme = $_GET["theme"] ?? "light";
		$theme = in_array($theme, ["light", "dark"], true) ? $theme : "light";
		// Fix relative image paths
		// We do not want to replace any external links that start with http(s)://
		$search = '/<img\s+[^>]*src=["\'](?!https?:\/\/)([^"\']+)["\']/i';
		$replace = '<img src="' . ExternalModules::getModuleDirectoryUrl($prefix, $version) . '$1"';
		$html = preg_replace($search, $replace, $html);
		$title = strip_tags($page);
		print <<<END
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>$title</title>
		END;
		ExternalModules::addResource("css/github-markdown-$theme.css");
		print <<<END
			<style>
				.markdown-body { margin: 10px auto; max-width: 850px; }
			</style>
		</head>
		<body class="markdown-body">
			{$html}	
		</body>
		</html>
		END;
		break;

	default:
        // OTHER content (css/js/etc...):
        $contentType = ExternalModules::getContentType($pageExtension);
        if($contentType){
            // In most cases index.php is not used to access non-php files (and a content type is not needed).
            // However, Andy Martin has a use case where users are behind Shibboleth and it makes sense to serve all
            // files through index.php.  This content type was added specifically for that case.
            $mime_type = $contentType;
        } else {
            // Make a best guess
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $pagePath);
        }

        // send the headers
        // header("Content-Disposition: attachment; filename=$public_name;");
        header("Content-Type: $mime_type");
        header('Content-Length: ' . filesize($pagePath));

        // stream the file
        $fp = fopen($pagePath, 'rb');
        fpassthru($fp);
}
