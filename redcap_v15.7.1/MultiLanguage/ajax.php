<?php // AJAX Handler for Multi-Language Management


use MultiLanguageManagement\MultiLanguage;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;

// Initialize depending on context
if (isset($_GET["pid"])) {
    require_once dirname(dirname(__FILE__))."/Config/init_project.php";
}
else {
    require_once dirname(dirname(__FILE__))."/Config/init_global.php";
}

// Setup verification info
require_once APP_PATH_CLASSES."Crypto.php";
$crypto = Crypto::init();
$pid = defined("PROJECT_ID") ? PROJECT_ID : "SYSTEM";
$user = USERID;

// Get payloads
$raw = file_get_contents("php://input");
$data = array();
foreach (explode("&", $raw) as $item) {
    $parts = explode("=", $item, 2);
    if (count($parts) == 2) {
        $data[urldecode($parts[0])] = urldecode($parts[1]);
    }
}

// Prepare default response
$response = array(
    "success" => false,
    "error" => "Invalid request."
);

// Check verification
$verification = $crypto->decrypt($data["verification"]);
$verified = $verification && $verification["pid"] == $pid && $verification["user"] == $user;
// Handle action (using do-while as control structure to simplify conditions)
if ($verified) do { 
    $action = $data["action"] ?? "";
    // Permission check
    // Control center actions (system) require the "Modify system configuration pages" privilege.
    if ($pid == "SYSTEM") {
        if (!defined("ACCESS_SYSTEM_CONFIG") || ACCESS_SYSTEM_CONFIG != 1) {
            // All actions require "Modify system configuration pages"
            // User doesn't have sufficient rights - break out of do-while
            break;
        }
    }
    // Most project actions are only allowed for users with design rights 
    else {
        $user_rights = UserRights::getPrivileges($pid, $user);
        $user_rights = $user_rights[$pid][strtolower($user)];
        $has_mlm_rights = (isset($user_rights["design"]) && $user_rights["design"] == "1") || (defined("SUPER_USER") && SUPER_USER == 1);
        /** @var array<string,bool> Project-context actions and whether they are allowed without design rights */
        $actions = array(
            "create-snapshot" => false,
            "delete-snapshot" => false,
            "download-snapshot" => false,
            "export-lang" => false,
            "get-sys-lang" => false,
            "get-metadata-hash" => false,
            "load-snapshots" => false,
            "parse-ini" => false,
            "save" => false,
            "export-general" => false,
            "set-user-preferred-lang" => true, // This is allowed for all users
        );
        if (!array_key_exists($action, $actions)) {
            // Unknown action - break out of do-while
            break;
        }
        if (!($actions[$action] || $has_mlm_rights)) {
            // Insufficient rights - break out of do-while
            break;
        }
    }
    // Process actions and set appropriate response
    require_once APP_PATH_CLASSES."MultiLanguage.php";
    try {
        if ($pid == "SYSTEM") {
            // Control Center
            switch ($action) {
                case "get-guid":
                    $response = array(
                        "success" => true,
                        "error" => "",
                        "guid" => MultiLanguage::getGuid()
                    );
                    break;
                case "get-sys-lang":
                    $guid = json_decode($data["payload"], true)["guid"];
                    $response = MultiLanguage::getSystemLanguage($pid, $guid);
                    break;
                case "save":
                    $response = MultiLanguage::save("SYSTEM", json_decode($data["payload"], true));
                    break;
                case "parse-ini":
                    $response = MultiLanguage::parseIniFile($data["payload"]);
                    break;
                case "export-lang":
                    $response = MultiLanguage::getExportFile("SYSTEM", $user, $data["payload"]);
                    break;
                case "get-usage-stats":
                    $response = MultiLanguage::getUsageStats();
                    break;
            }
        }
        else {
            // Project
            switch ($action) {
                case "save":
                    $response = MultiLanguage::save($pid, json_decode($data["payload"], true));
                    global $mycap_enabled_global, $mycap_enabled, $Proj;
                    // Update config JSON only when project is in development mode
                    if ($response['success'] == true && $mycap_enabled_global == 1 && $mycap_enabled == 1 && $Proj->project['status'] == '0') {
                        // Update MyCap config JSON version number to version++ and update language list in config
                        $myCapProj = new MyCap($pid);
                        $return = $myCapProj->updateMLMConfigJSON();
                    }
                    break;
                case "get-sys-lang":
                    $guid = json_decode($data["payload"], true)["guid"];
                    $response = MultiLanguage::getSystemLanguage($pid, $guid);
                    break;
                case "get-metadata-hash":
                    // Prevent this request from extending the session expiration (so it doesn't interfere with auto-logout)
                    define("PREVENT_SESSION_EXTEND", true);
                    $response = array(
                        "success" => true,
                        "error" => "",
                        "hash" => MultiLanguage::getProjectMetadataHash($pid),
                    );
                    break;
                // Import/Export Support
                case "parse-ini":
                    $response = MultiLanguage::parseIniFile($data["payload"]);
                    break;
                case "export-lang":
                    $response = MultiLanguage::getExportFile($pid, $user, $data["payload"]);
                    break;
                // Language Preference
                case "set-user-preferred-lang":
                    MultiLanguage::setUserPreferredLanguage($pid, json_decode($data["payload"]));
                    $response = array(
                        "success" => true,
                        "error" => "",
                    );
                    break;
                // Snapshot Support
                case "load-snapshots":
                    $response = MultiLanguage::getSnapshots($pid);
                    break;
                case "create-snapshot":
                    $response = MultiLanguage::createSnapshot($pid, $user);
                    break;
                case "download-snapshot":
                    $response = MultiLanguage::downloadSnapshot($pid, $user, json_decode($data["payload"]));
                    break;
                case "delete-snapshot":
                    $response = MultiLanguage::deleteSnapshot($pid, $user, json_decode($data["payload"]));
                    break;
                case "export-general":
                    $response = MultiLanguage::getGeneralSettingsExportFile($pid, $user);
                    break;
            }
        }
    }
    catch (Throwable $ex) {
        $response = MultiLanguage::exceptionResponse($ex);
    }
    // Update timestamp
    $verification["timestamp"] = time();
    $response["verification"] = $crypto->encrypt($verification);
} while (false); 

// Send response
print json_encode($response);
