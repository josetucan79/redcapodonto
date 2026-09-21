<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

$pid = ExternalModules::getProjectId();

/**
 * This line was originally added for https://github.com/vanderbilt/redcap-external-modules/issues/311,
 * though, though changing $pid to SYSTEM_SETTING_PROJECT_ID
 * in ExternalModules::saveSettings() (like we do now) would have been a more appropriate fix.
 * Ideally the following line would be removed, but it's not currently worth
 * the time to determine what side affects that change might have.
 */
if ($pid === null) { $pid = ""; } // LS https://github.com/vanderbilt/redcap-external-modules/issues/311 

$moduleDirectoryPrefix = ExternalModules::escape($_GET['moduleDirectoryPrefix']);

$rawSettings = json_decode($_POST['settings'], true);
if($rawSettings === null){
	//= Unable to parse module settings!
	throw new \Exception(ExternalModules::tt("em_errors_90")); 
}

$module = ExternalModules::getModuleInstance($moduleDirectoryPrefix);
$validationErrorMessage = $module->validateSettings(ExternalModules::formatRawSettings($moduleDirectoryPrefix, $pid, $rawSettings));
if(!empty($validationErrorMessage)){
	exit($validationErrorMessage);
}

/**
 * The saveSettings() function calls setSetting() under the hood,
 * which verifies that the current user has appropriate permissions.
 */
$saveSqlByField = ExternalModules::saveSettings($moduleDirectoryPrefix, $pid, $rawSettings);

if(!empty($saveSqlByField)){
	// At least one setting changed.  Log this event.
	$logText = "Modify configuration for external module \"{$moduleDirectoryPrefix}_{$module->VERSION}\" for " . (!empty($pid) ? "project" : "system");
	
	// We do NOT include the values of changed settings here, since they could contain sensitive data that some users shouldn't see (API keys, etc.).
	$changeText = join(', ', array_keys($saveSqlByField));

	$queryDetails = '';
	foreach($saveSqlByField as $query){
		$queryDetails .= "SQL: " . $query->getSQL() . "\nParameters: " . json_encode($query->getParameters()) . "\n\n";
	}

	\REDCap::logEvent($logText, $changeText, $queryDetails);
}

ExternalModules::callHook('redcap_module_save_configuration', array($pid), $moduleDirectoryPrefix);

header('Content-type: application/json');
echo json_encode(array(
    'status' => 'success',
    'test' => 'success'
));
