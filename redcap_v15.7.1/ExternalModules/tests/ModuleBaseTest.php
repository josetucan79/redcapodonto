<?php namespace ExternalModules;
require_once ExternalModules::getTestVendorPath() . 'autoload.php';
require_once __DIR__ . '/SharedBaseTest.php';

use Exception;

// This class can be used by modules themselves to write their own tests.
abstract class ModuleBaseTest extends SharedBaseTest{
    public $module;

    public function setUp():void{
        $reflector = new \ReflectionClass(static::class);
        $moduleDirName = basename(dirname(dirname($reflector->getFileName())));
        list($prefix, $version) = ExternalModules::getParseModuleDirectoryPrefixAndVersion($moduleDirName);
        
        // Make it seem like this module is enabled, even if it's not.
        $this->setExternalModulesProperty('systemwideEnabledVersions', [
            $prefix => $version
        ]);

        # Setup/Reset test system settings
        ExternalModules::enableTestSettings($prefix);

        $this->module = ExternalModules::getModuleInstance($prefix, $version);
    }

    function __call($methodName, $args){
        $returnValue = call_user_func_array(array($this->module, $methodName), $args);

        if($returnValue === false){
            throw new Exception("Either the '$methodName' does not exist, or it's return value is 'false'.  If it's return value is false, reference it directly using '\$this->module->$methodName()' instead of implicitly using '\$this->$methodName()'.");
        }

        return $returnValue;
    }

    protected function disableTestSettings(){
        ExternalModules::disableTestSettings($this->getPrefix());
    }
}
