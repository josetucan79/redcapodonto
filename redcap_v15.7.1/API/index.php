<?php

use ExternalModules\ExternalModules;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;

// Determine if this is a real API call or merely a passthru call for an External Module
if (isset($_GET['type']) && $_GET['type'] == "module") {
	// Set constant to denote that this is an API call
	define("API_EXTMOD", true);
	// MYCAP TEMP SOLUTION: A security fix (commit f577725a36ba63a7f5bc325c6d4ce8695d924e6e) inadventently causes 
	// the MyCap module not to work anymore. The following section is temporarily added until the fix in the MyCap module and app
	// can be propagated to all institutions and participants. 6/14/2018
	if (isset($_GET['prefix']) && $_GET['prefix'] == 'mycap') {
		define("NOAUTH", true);
	}
	// Make sure to defind ANYAUTH if exist in URL for API calls. 
	if (isset($_GET['ANYAUTH'])) {
		define("ANYAUTH", true);
	    }
	
	// Check if proxying a PHP page - if yes, authorize, if no, allow passthru
	if (isset($_GET['page'])) {
		$page = rawurldecode(urldecode($_GET['page']));
		$pageExtension = strtolower(pathinfo($page, PATHINFO_EXTENSION));
		if ($pageExtension != '' && $pageExtension != "php") {
			// This is not a php page, let's proxy it through
			define("NOAUTH", true);
		}
	}

	// The init files below remove CSRF tokens.  Store them by a different name so the module framework can check them later.
	// This line also occurs in external_modules/redcap_connect.php
	$_POST['redcap_external_module_csrf_token'] = $_POST['redcap_csrf_token'] ?? null;
	
	// Config
	require_once (dirname(dirname(__FILE__)) . '/Config/' . (isset($_GET['pid']) ? 'init_project.php' : 'init_global.php'));

	// Check if MyCap EM is migrated to REDCap Core - redirect to core API endpoint instead of MyCap EM endpoint
	$myCapEnabledFromCore = false;
	if (isset($_GET['type']) && $_GET['type'] == "module" && isset($_GET['prefix']) && $_GET['prefix'] == "mycap") {
        if (isset($_POST['stu_code']) && $_POST['stu_code'] != '') {
            $projectId = '';
            try {
                $projectId = MyCap::getProjectIdByCode($_POST['stu_code']);
            } catch (Exception $e) {

            }
            if ($projectId != '') {
                $Proj = new Project($projectId);
                if ($Proj->project['mycap_enabled'] == 1) {
                    $myCapEnabledFromCore = true;
                    unset($_GET['type'], $_GET['prefix'], $_GET['page'], $_POST['redcap_external_module_csrf_token']);
                    $_GET['content'] = 'mycap';
                    // Set constant to denote that this is an API call
                    define("API", true);
                    // Disable REDCap's authentication (will use API tokens for authentication)
                    define("NOAUTH", true);
                    // Config
                    require_once (dirname(dirname(__FILE__)) . '/Config/init_global.php');
                }
            }
        }
    }
	// Make sure that External Modules are installed
	if (defined("APP_PATH_EXTMOD") && $myCapEnabledFromCore == false) {
		// Require ExternalModules/index.php
		unset($_GET['type']);
		require_once APP_PATH_EXTMOD . "index.php";
		exit;
	}
	// Disable REDCap's authentication (will use API tokens for authentication)
	define("NOAUTH", true);
} else {
	// Set constant to denote that this is an API call
	define("API", true);
	// Disable REDCap's authentication (will use API tokens for authentication)
	define("NOAUTH", true);
	// Config
	require_once (dirname(dirname(__FILE__)) . '/Config/init_global.php');
}

// Increase memory limit in case needed for intensive processing
System::increaseMemory(2048);

/**
 * API FUNCTIONALITY
 */

// Detect playground for logging
$playground = isset($_POST['playground']) ? ' Playground' : '';


// Globals
$format = "xml";
$returnFormat = "xml";
$post = [];

// Set format (default = xml)
$format = (isset($_POST['format']) ? $_POST['format'] : "xml");
if (isset($_POST['authkey']) && !isset($_POST['format'])) {
	$format = 'csv'; // Default return format for authkey is CSV
}

if (isset($_GET['content']) && $_GET['content'] == 'mycap') {
    $_POST['content'] = 'mycap';
    $format = 'json';
    $post['content'] = 'mycap';

}

switch ($format)
{
	case 'json':
		break;
	case 'csv':
		break;
	case 'odm':
		break;
	default:
		$format = "xml";
}

$_POST['format'] = $format;


// Set returnFormat for outputting error messages and other stuff (default = xml)
$tempFormat = (isset($_POST['returnFormat']) && $_POST['returnFormat'] != "") ? strtolower($_POST['returnFormat']) : strtolower($_POST['format']);
switch ($tempFormat)
{
	case 'json':
		$returnFormat = "json";
		break;
	case 'csv':
		$returnFormat = "csv";
		break;
	case 'xml':
	default:
		$returnFormat = "xml";
		break;
}

// Check if the API is enabled first
if (!$api_enabled) RestUtility::sendResponse(400, $lang['api_01']);

// Module API request
$module_api_present = method_exists("ExternalModules\\ExternalModules", "handleApiRequest");
if (isset($_POST["content"]) && $_POST["content"] == "externalModule" && $module_api_present) {
	// Reserved items (true = required)
	$reserved_items = [
		"content" => true,
		"prefix" => true,
		"action" => true,
		"token" => false,
		"format" => false,
		"returnFormat" => false,
		"csvDelim" => false, // must be one of , ; \t
	];
	$csv_delimiters = [
		"comma" => ",",
		"semicolon" => ";",
		"tab" => "\t",
		"caret" => "^",
		"space" => " ",
		"pipe" => "|",
	];
	// Sanitize/Add CSV delimter
	$csv_delim = isset($_POST["csvDelim"]) && array_key_exists($_POST["csvDelim"], $csv_delimiters) 
		? $csv_delimiters[$_POST["csvDelim"]]
		: \User::getCsvDelimiter();
	$reserved_present = [];
	// Separate custom payloads from reserved items
	$payload = [];
	foreach ($_POST as $item => $val) {
		if (array_key_exists($item, $reserved_items)) {
			$reserved_present[] = $item;
		}
		else {
			$payload[$item] = $val;
			unset($_POST[$item]);
		}
	}
	// All required present?
	$required_missing = [];
	foreach ($reserved_items as $item => $required) {
		if (!in_array($item, $reserved_present) && $required) {
			$required_missing[] = $item;
		}
	}
	if (count($required_missing)) {
		// Send a helpful response when required items are missing
		RestUtility::sendResponse(400, "Missing required items: " . join(", ", $required_missing));
	}
	// Process the request to get user data etc
	// Note: The External Module API user right is checked by the EM Framework
	$request_data = RestUtility::processRequest(isset($_POST["token"]))->getRequestVars();
	// Replace some stuff with sanitized values
	$request_data["format"] = $format;
	$request_data["returnFormat"] = $returnFormat;
	$request_data["csvDelim"] = $csv_delim;
	// Mark as EM API call and delegate further processing to the framework
	define("EM_API_ENDPOINT", true);
	ExternalModules::handleApiRequest($payload, $request_data);
	exit;
}

// Certain actions do NOT require a token
$tokenRequired = !(!isset($_POST['token']) &&
					// Advanced project bookmark
					(isset($_POST['authkey']) ));

// Process the incoming request
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['content']) && in_array($_GET['content'], array('mycap', 'tableau'))) {
    $post['content'] = $_GET['content'];
    $tokenRequired = false;
    $data = RestUtility::processGetRequest($_GET);
} else {
    if (isset($_GET['content']) && $_GET['content'] == 'mycap') {
        $tokenRequired = false;
    }
    $data = RestUtility::processRequest($tokenRequired);
}

# get all the variables sent in the request
$post = $data->getRequestVars();

if ($post['content'] != 'mycap') { // Do not sent these params in post for mycap API calls
    # initialize array variables if they were NOT sent or if they are empty
    if (!isset($post['records']) or $post['records'] == '') $post['records'] = array();
    if (!isset($post['events']) or $post['events'] == '') $post['events'] = array();
    if (!isset($post['fields']) or $post['fields'] == '') $post['fields'] = array();
    if (!isset($post['forms']) or $post['forms'] == '') $post['forms'] = array();
    if (!isset($post['arms']) or $post['arms'] == '') $post['arms'] = array();
    if (!isset($post['dags']) or $post['dags'] == '') $post['dags'] = array();

    if (!isset($post['mobile_app'])) $post['mobile_app'] = "0";
    if (!isset($post['uuid'])) $post['uuid'] = "";
    if (!isset($post['project_init'])) $post['project_init'] = "0";

    if (!isset($post['format'])) $post['format'] = "";
    if (!isset($post['type'])) $post['type'] = "";
    if (!isset($post['rawOrLabel'])) $post['rawOrLabel'] = "";
    if (!isset($post['rawOrLabelHeaders'])) $post['rawOrLabelHeaders'] = "";
    if (!isset($post['overwriteBehavior'])) $post['overwriteBehavior'] = "";
    if (!isset($post['action'])) $post['action'] = "";
    if (!isset($post['returnContent'])) $post['returnContent'] = "";
    if (!isset($post['event'])) $post['event'] = "";
    if (!isset($post['armNumber'])) $post['armNumber'] = "";
    if (!isset($post['armName'])) $post['armName'] = "";
    if (!isset($post['dateFormat'])) {
        $post['dateFormat'] = "YMD";
    } else {
        $post['dateFormat'] = ($post['dateFormat'] == 'DMY' ? 'DMY' : ($post['dateFormat'] == 'MDY' ? 'MDY' : 'YMD'));
    }
    $post['exportCheckboxLabel'] = (isset($post['exportCheckboxLabel']) && ($post['exportCheckboxLabel'] == '1' || strtolower($post['exportCheckboxLabel'] . "") === 'true'));
    $post['combineCheckboxOptions'] = (isset($post['combineCheckboxOptions']) && ($post['combineCheckboxOptions'] == '1' || strtolower($post['combineCheckboxOptions'] . "") === 'true'));
    $post['forceAutoNumber'] = (isset($post['forceAutoNumber']) && ($post['forceAutoNumber'] == '1' || strtolower($post['forceAutoNumber'] . "") === 'true'));

    if (isset($post['authkey'])) $post['content'] = "authkey";
    if (!isset($post['filterLogic'])) $post['filterLogic'] = false;
}

# determine if a valid content parameter was passed in
switch ($post['content'])
{
	case 'record':
		$post['exportSurveyFields'] = (isset($post['exportSurveyFields']) && ($post['exportSurveyFields'] == '1' || strtolower($post['exportSurveyFields']."") === 'true'));
		$post['exportDataAccessGroups'] = (isset($post['exportDataAccessGroups']) && ($post['exportDataAccessGroups'] == '1' || strtolower($post['exportDataAccessGroups']."") === 'true'));
        $post['backgroundProcess'] = (isset($post['backgroundProcess']) && ($post['backgroundProcess'] == '1' || strtolower($post['backgroundProcess']."") === 'true'));
		break;
	case 'metadata':
	case 'file':
	case 'fileRepository':
	case 'filesize': // currently only used for mobile app usage to determine file size for API File Export - deprecate soon to replace with 'fileinfo'
	case 'fileinfo': // currently only used for mobile app usage to determine file size for API File Export
	case 'repeatingFormsEvents':
	case 'instrument':
	case 'event':
	case 'arm':
	case 'user':
	case 'project_settings':
	case 'report':
	case 'authkey':
	case 'version':
	case 'pdf':
	case 'surveyLink':
	case 'surveyAccessCode':
	case 'surveyQueueLink':
	case 'surveyReturnCode':
	case 'participantList':
	case 'exportFieldNames':
	case 'appRightsCheck':
	case 'formEventMapping':
	case 'fieldValidation':
	case 'attachment':
	case 'project':
    case 'generateNextRecordName':
	case 'project_xml':
    case 'dag':
    case 'userDagMapping':
    case 'log':
    case 'tableau':
    case 'mycap':
    case 'userRole':
    case 'userRoleMapping':
		break;
	default:
		die(RestUtility::sendResponse(400, 'The value of the parameter "content" is not valid'));
		break;
}

# If content = file, determine if a valid action was passed in
if ($post['content'] == "file" || $post['content'] == "filesize" || $post['content'] == "fileinfo")
{
	switch (strtolower($post['action']))
	{
		case 'export':
		case 'import':
		case 'import_app':
		case 'delete':
			break;
		default:
			die(RestUtility::sendResponse(400, 'The value of the parameter "action" is not valid'));
			break;
	}
}
if ($post['content'] == 'version' || $post['content'] == 'event' || $post['content'] == "arm" || $post['content'] == "authkey" || $post['content'] == "repeatingFormsEvents" || $post['content'] == "dag" || $post['content'] == "user" || $post['content'] == "userRole" || $post['content'] == "userRoleMapping")
{
	if ($post['action'] == "") $post['action'] = "export";
}

// Set action as "import" if "data" parameter is provided for some methods
if (($post['content'] == "user" || $post['content'] == "userRole" || $post['content'] == "userRoleMapping") && $post['action'] != "delete" && isset($post['data']))
{
	$post['action'] = "import";
}

if ($post['content'] != 'mycap') { // Do not sent these params in post for mycap API calls
    # set the import action option
    if (strtolower($post['overwriteBehavior']) != 'normal' && strtolower($post['overwriteBehavior']) != 'overwrite') $post['overwriteBehavior'] = 'normal';

    # set the type
    if (strtolower($post['type']) != 'eav' && strtolower($post['type']) != 'flat') $post['type'] = 'flat';

    # what content to return when importing data
    switch (strtolower($post['returnContent']))
    {
        case 'ids':
        case 'auto_ids':
        case 'nothing':
        case 'count':
            break;
        default:
            $post['returnContent'] = 'count';
            break;
    }

    # set the type of content to be returned for a field that has data/value pairs
    switch (strtolower($post['rawOrLabel']))
    {
        case 'raw':
        case 'label':
            break;
        default:
            $post['rawOrLabel'] = 'raw';
            break;
    }
    switch (strtolower($post['rawOrLabelHeaders']))
    {
        case 'raw':
        case 'label':
            break;
        default:
            $post['rawOrLabelHeaders'] = 'raw';
            break;
    }

    # set the event name option (if not set, use rawOrLabel option)
    // eventName is a deprecated feature, so align it with rawOrLabel value for EAV only (since EAV is only place it still deals with it in old code)
    $post['eventName'] = ($post['rawOrLabel'] == 'raw') ? 'unique' : 'label';
}

# determine if we are exporting, importing, or deleting data
if(in_array($post['content'], array('fileRepository', 'file', 'event', 'arm', 'authkey', 'dag', 'user', 'userRole')))
{
    if ($post['content'] == "authkey") {
        $action = "export";
    } else {
        $action = $post['action'];
        if (!in_array($action, array('export', 'delete', 'import', 'import_app', 'switch', 'list', 'createFolder'))) {
			die(RestUtility::sendResponse(400, 'The value of the parameter "action" is not valid'));
		}
    }
}
elseif ($post['content'] == 'record' && in_array($post['action'], array('delete', 'rename', 'randomize'))) {
        $action = $post['action'];
}
else {
    if (in_array($post['content'], array('tableau', 'mycap'))) {
        $action = 'display';
    } else {
        $action = (!isset($post['data']) || $post['content'] == 'version') ? 'export' : 'import';
    }
}

# determine if the user has the correct user rights
if ($tokenRequired && strlen($post['token']) != 64) {
    // Display errors if user is assigned to DAG for API import/export/delete DAG methods
    if ($post['content'] == 'dag') {
        if ($post['dataAccessGroupId'] != "" && $action != "switch") {
            if (in_array($action, array('export', 'import'))) {
                Logging::logEvent('',"redcap_user_rights","ERROR",'',json_encode($_POST),"Failed API request (user rights invalid)");
            }
            die(RestUtility::sendResponse(403, $lang['api_183']));
        }
    }
	if ($action == "export") {
		if ($post['api_export'] != 1) {
			// Logging
			Logging::logEvent('',"redcap_user_rights","ERROR",'',json_encode($_POST),"Failed API request (user rights invalid)");
			die(RestUtility::sendResponse(403, "You do not have API Export privileges"));
		}
	}
	elseif ($action == "import") {
		if ($post['api_import'] != 1) {
			// Logging
			Logging::logEvent('',"redcap_user_rights","ERROR",'',json_encode($_POST),"Failed API request (user rights invalid)");
			die(RestUtility::sendResponse(403, "You do not have API Import/Update privileges"));
		}
	}
    elseif (($action == "delete" || ($action == "list" && $post['content'] != "fileRepository") || $action == "createFolder")
        && ($post['content'] == "fileRepository" || $post['content'] == "user") && $post['api_import'] != 1)
    {
        die(RestUtility::sendResponse(403, "You do not have API Import/Update privileges"));
    }
    elseif ($action == "delete" && $post['content'] != "dag" && $post['content'] != "fileRepository") {
        if ($post['record_delete'] != 1) {
            die(RestUtility::sendResponse(403, "You do not have Delete Record privileges"));
        }
    }
    elseif ($action == "rename") {
        if ($post['record_rename'] != 1) {
            die(RestUtility::sendResponse(403, "You do not have Rename Record privileges"));
        }
    }
    elseif ($action == "randomize") {
        if ($post['random_perform'] != 1) { // lsgs Why do some of these fails cause log event and some not?
            die(RestUtility::sendResponse(403, "You do not have Randomize privileges"));
        }
    }
}

// For content=filesize, set content as file (method only used for mobile app to detect size of file)
if ($post['content'] == "filesize" || $post['content'] == "fileinfo") {
	$post['content'] = "file";
	$post['fileinfo'] = 1;
}

// If project was deleted or completed but still exists on back-end, then return error
if ($post['content'] !== "project" && $post['content'] !== "version" && !isset($post['authkey']) && $post['content'] !== "tableau" && $post['content'] !== "mycap") {
	$Proj = new Project();
	if ($Proj->project['date_deleted'] != '') {
		RestUtility::sendResponse(400, $lang['api_11']);
	} elseif ($Proj->project['completed_time'] != '') {
		RestUtility::sendResponse(400, $lang['api_150']);
	}
}

if (defined("PROJECT_ID"))
{
    $_GET['pid'] = $project_id = PROJECT_ID;
	// Check if project is offline. If so, return error.
	$Proj = new Project(PROJECT_ID);
	if ($Proj->project['online_offline'] == '0') {
		die(RestUtility::sendResponse(400, $lang['info_65']));
	}

	// Build record list cache if not yet built for this project
	Records::buildRecordListCacheCurl(PROJECT_ID);
}

$moduleError = \ExternalModules\ExternalModules::callHook('redcap_module_api_before', [
	defined('PROJECT_ID') ? PROJECT_ID : null,
	array_merge($post, ['action' => $action])
]);

if($moduleError !== null){
	RestUtility::sendResponse(403, $moduleError);
}

# include the necessary file, based off of content type and whether the "data" field was passed in
include ($post['content'] . "/$action.php");
