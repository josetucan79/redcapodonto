<?php


// Sleep for a short amount of time to pace several simulataneous requests
if (isset($_GET['usleep']) && is_numeric($_GET['usleep']))
{
	usleep((int)$_GET['usleep']);
}

// Check if coming from survey or authenticated form
if (isset($_GET['s']) && !empty($_GET['s']))
{
	// Call config_functions before config file in this case since we need some setup before calling config
	require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
	// Validate and clean the survey hash, while also returning if a legacy hash
	$hash = $_GET['s'] = Survey::checkSurveyHash();
	// Set all survey attributes as global variables
	Survey::setSurveyVals($hash);
	// Now set $_GET['pid'] before calling config
    if (isset($_GET['pid']) && isset($_REQUEST['pid']) && isinteger($_REQUEST['pid']) && $_REQUEST['pid'] != $project_id) {
        $project_id = $_REQUEST['pid'];
    }
	$_GET['pid'] = $project_id;
	// Set flag for no authentication for survey pages
    defined("NOAUTH") or define("NOAUTH", true);
} elseif ((isset($_GET['__dashboard']) || isset($_GET['__report'])) && isset($_GET['pid']) && isset($_GET['id']) && isset($_GET['doc_id_hash'])) {
    // Call config_functions before config file in this case since we need some setup before calling config
    require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
	// If viewing an embedded image on a public page (e.g., public report, public project dashboard), disable authentication
    $pid = null;
    if (isset($_GET['__dashboard'])) {
        $dash = new ProjectDashboards();
        list ($pid, $dash_id, $dash_title) = $dash->getDashInfoFromPublicHash($_GET['__dashboard']);
    } elseif (isset($_GET['__report'])) {
        list ($pid, $report_id, $report_title) = DataExport::getReportInfoFromPublicHash($_GET['__report']);
    }
    // Validate values
    if ($_GET['doc_id_hash'] != Files::docIdHash($_GET['id']) || !isinteger($pid) || $pid != $_GET['pid']) {
        exit("{$lang['global_01']}{$lang['exclamationpoint']}");
    }
    // Disable auth
    defined("NOAUTH") or define("NOAUTH", true);
} elseif (!isset($_GET['pid']) && isset($_GET['origin']) && $_GET['origin'] == 'messaging') {
	// If viewing an image in a User Messaging thread, which is not in a project, then bypass init project
	define("FORCE_INIT_GLOBAL", true);
} elseif (!isset($_GET['pid']) && isset($_GET['doc_id_hash']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    // If viewing a non-project embedded image in an email, which is not in a project, then bypass init project
    require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
    if ($_GET['doc_id_hash'] == Files::docIdHash($_GET['id'])) {
        define("FORCE_INIT_GLOBAL", true);
    }
}


if (defined("FORCE_INIT_GLOBAL")) {
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
	// Ensure the file attachment belongs to a thread that the current user has access to
	if (isset($_GET['origin']) && $_GET['origin'] == 'messaging' && !Messenger::fileBelongsToUserThread($_GET['id'])) {
		exit("{$lang['global_01']}{$lang['exclamationpoint']}");
	}
} else {
	require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
}

// If ID is not in query_string, then return error
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) exit("{$lang['global_01']}!");

// Confirm the hash of the doc_id
if (!isset($_GET['origin'])) {
	if (!isset($_GET['doc_id_hash']) || (isset($_GET['doc_id_hash']) && $_GET['doc_id_hash'] != Files::docIdHash($_GET['id'], Project::getProjectSalt($project_id)))) {
		exit("{$lang['global_01']}!");
	}
}

// Ensure that this file belongs to this project
$sql = "select * from redcap_edocs_metadata where doc_id = '" . db_escape($_GET['id']). "' and (delete_date is null or (delete_date is not null and delete_date > '".NOW."'))";
if (defined("PROJECT_ID")) $sql .= " and project_id = $project_id";
$q = db_query($sql);
$edoc_info = db_fetch_assoc($q);
$isValidFile = db_num_rows($q);

// Ensure this file being displayed is an image file or PDF
$fileExt = null;
if ($isValidFile) {
	$fileExt = trim(strtolower($edoc_info['file_extension']));
}
$allowedExtTypes = array("jpeg", "jpg", "gif", "png", "bmp", "pdf", "svg");
if (!$isValidFile || !in_array($fileExt, $allowedExtTypes)) {
	exit($lang['global_01']);
}

// Log when viewing a preview of a File Repository file via a public link
if (isset($_GET['__file'])) {
    // If URL contains specially encoded value to prevent logging, then do not log this
    $isExemptFromLogging = (isset($_GET['exempt']) && $_GET['exempt'] == sha1($_GET['id'].$__SALT__));
    if (!$isExemptFromLogging) {
        // Log this
        Logging::logEvent($sql, "redcap_docs", "MANAGE", $_GET['id'], Files::getEdocName($_GET['id']), "View preview of file from File Repository via Public Link", "", "[non-user]");
    }
}

// Get the file's content
list ($mimeType, $docName, $fileContent) = Files::getEdocContentsAttributes($_GET['id']);
// If missing mime-type, then try to add it manually (especially for PNGs from jSignature)
if (strtolower($edoc_info['file_extension']) == "pdf") {
    $edoc_info['mime_type'] = "application/pdf";
}
elseif ($edoc_info['mime_type'] == '') {
    $edoc_info['mime_type'] = 'image/'.strtolower(getFileExt($edoc_info['doc_name']));
}
elseif (strpos($edoc_info['mime_type'], "html") !== false || strpos($edoc_info['mime_type'], "text") !== false || strpos($edoc_info['mime_type'], "javascript") !== false) {
    // Make sure the file is not maliciously injected HTML/JS
    $edoc_info['mime_type'] = 'image/png';
}
// Set image header
header('Content-type: ' . $edoc_info['mime_type']);
// Set CSP header (very important to prevent reflected XSS)
header("Content-Security-Policy: script-src 'none'");
// Output image data
print $fileContent;
flush();