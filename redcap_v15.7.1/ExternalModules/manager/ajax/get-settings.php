<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

$pid = ExternalModules::getProjectId();
$prefix = ExternalModules::escape($_GET['moduleDirectoryPrefix']);
$module_instance = ExternalModules::getModuleInstance($prefix);
$config = ExternalModules::getConfig($prefix, null, $pid, true);

header('Content-type: application/json');
if (!empty($pid)) {
	if(!ExternalModules::hasProjectSettingSavePermission($prefix)){
		throw new \Exception(ExternalModules::tt('em_errors_20'));
	}

	$settings = array_merge(
		ExternalModules::getSystemValuesForOverridableSettings($prefix, $config),
		ExternalModules::getProjectSettingsAsArray($prefix, $pid, false)
	);
	
	$settingType = 'project-settings';
} else if (ExternalModules::isAdminWithModuleInstallPrivileges()){
	$settings = ExternalModules::getSystemSettingsAsArray($prefix);
	$settingType = 'system-settings';
}

foreach($config[$settingType] as $configKey => $configRow) {
	$config[$settingType][$configKey] = ExternalModules::getAdditionalFieldChoices($configRow, $pid);
}

if(method_exists($module_instance,'redcap_module_configuration_settings')){
    $config[$settingType] = $module_instance->redcap_module_configuration_settings($pid,$config[$settingType]);
}

echo json_encode(array(
	'status' => 'success',
	/**
	 * Do NOT HTML escape the entire config below.
	 * We've tried that multiple times.
	 * It breaks HTML in module setting names.
	 */
	'config' => ExternalModules::applyHidden($config),
	'settings' => $settings
), JSON_PARTIAL_OUTPUT_ON_ERROR);
