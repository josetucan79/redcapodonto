<?php namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

$pid = ExternalModules::getProjectId();

if(
    ($pid === null && !ACCESS_CONTROL_CENTER)
    ||
    ($pid !== null && !\UserRights::displayExternalModulesMenuLink())
){
    echo ExternalModules::tt('em_errors_128');
    return;
}

$hiddenPrefixes = [];
foreach ($_SESSION['external-module-configure-buttons-displayed'] as $prefix) {
    // This action was originally performed in-line when building module list HTML.
    // It was moved to an ajax request to allow more graceful failure if an individual module crashed during instantiation or this hook call.
    $module_instance = ExternalModules::getModuleInstance($prefix);
    if(!$module_instance->redcap_module_configure_button_display($pid)){
        $hiddenPrefixes[] = $prefix;
    }
}

echo json_encode($hiddenPrefixes, JSON_PRETTY_PRINT);
