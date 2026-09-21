<?php namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

if(!ExternalModules::isAdminWithModuleInstallPrivileges()){
    echo  ExternalModules::tt("em_errors_177"); //= You do not have permission to list hooks.
    return;
}

$methods = [];
try{
    /** @var AbstractExternalModule */
    $instance = ExternalModules::getModuleInstance(ExternalModules::getPrefix(), $_GET['version']);
    $className = get_class($instance);
    $class = new \ReflectionClass($className);
    foreach ($class->getMethods() as $method) {
        if ($method->class !== $className) {
            // Ignore methods inherited from the parent class
            continue;
        }

        $methods[] = $method->name;
    }
}
catch(\Throwable $t){
    /**
     * Ignore this exception, since it will get caught again & displayed later in the enableAndCatchExceptions() call.
     */
}

$hooks = [];
foreach($methods as $method){
    if(
        starts_with($method, 'redcap_')
        ||
        starts_with($method, 'hook_')
    ){
        $hooks[] = $method;
    }
}

$info = [
    "hooks" => $hooks
];

// Get API actions if the module implements the redcap_module_api hook
if (in_array(ExternalModules::MODULE_API_HOOK_NAME, $hooks, true)) {
    $info["providesApi"] = true;
    $config = $instance->framework->getConfig();
    if (isset($config[ExternalModules::MODULE_API_ACTIONS_SETTING])) {
        $info["apiActions"] = $config[ExternalModules::MODULE_API_ACTIONS_SETTING];
    }
    else {
        $info["apiActions"] = [];
    }
}

echo json_encode($info);