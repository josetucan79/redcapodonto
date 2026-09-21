<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

$pid = ExternalModules::getProjectId();

?>

<table id='external-modules-disabled-table' class="table table-no-top-row-border">
    <tr id="module_no_disable_results" style="display:none;"><td><?=ExternalModules::tt('em_manage_149')?></td></tr>
	<?php

	if (ExternalModules::isSuperUser() || ExternalModules::isAdminWithModuleInstallPrivileges()) {
		$enabledModules = ExternalModules::getEnabledModules();
	} else {
		$enabledModules = ExternalModules::getDiscoverableModules();
	}

	if (!isset($pid)) {
		$disabledModuleConfigs = ExternalModules::getDisabledModuleConfigs($enabledModules);

		if (empty($disabledModuleConfigs)) {
			echo 'None';
		} else {
			foreach ($disabledModuleConfigs as $moduleDirectoryPrefix => $versions) {
				ExternalModules::psalmSuppress($moduleDirectoryPrefix);
				require __DIR__ . '/../templates/disabled-module-system-table-row.php';
			}
		}
	} else {
		$projectEnabledModules = ExternalModules::getEnabledModules($pid);

		$projectDisabledModules = [];
		$configs = array();
		$moduleTitles = array();
		foreach ($enabledModules as $prefix => $version) {
			if (isset($projectEnabledModules[$prefix])) {
				continue;
			}

			$projectDisabledModules[$prefix] = $version;
			$configs[$prefix] = ExternalModules::getConfig($prefix, $version, $pid); // Disabled modules have no say in their language.
			$moduleTitles[$prefix] = trim(strtoupper($configs[$prefix]['name'])); // Uppercase for sorting, otherwise A b C will be A C b.
		}
		array_multisort($moduleTitles, SORT_REGULAR, $projectDisabledModules);
		if (empty($projectDisabledModules)) {
			echo "None";
		} else {
			// Loop through each module to render
			foreach ($projectDisabledModules as $prefix => $version) {
				$config = $configs[$prefix];

				$name = trim($config['name']);
				if (empty($name)) {
					continue;
				}

				require __DIR__ . '/../templates/disabled-module-project-table-row.php';
			}
		}
	}
	?>
</table>

<?php 
ExternalModules::tt_initializeJSLanguageStore();
ExternalModules::tt_transferToJSLanguageStore(
	array(
		"em_manage_12",
		"em_manage_27", 	
		"em_manage_30",
		"em_manage_116",
		"em_manage_64",
		"em_manage_66",
		"em_manage_67",	
		"em_manage_68",
		"em_manage_69",
		"em_manage_70",
		"em_manage_71",
        "em_manage_89",
        "em_errors_112",
        "em_manage_27",
		"em_manage_135",
		"em_manage_136",
		"em_manage_137",
		"em_manage_138",
		"em_manage_139",
		"em_manage_140",
		"em_manage_141",
		"em_manage_142"
	)
);
ExternalModules::addResource(ExternalModules::getManagerJSDirectory() . 'get-disabled-modules.js');