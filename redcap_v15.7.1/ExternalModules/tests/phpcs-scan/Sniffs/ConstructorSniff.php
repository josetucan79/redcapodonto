<?php namespace ExternalModules\Sniffs\Misc;

use ExternalModules\SniffMessages;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class Constructor implements Sniff
{
    private $moduleClassPath;
    private $insideModuleClass = false;

    function register()
    {
        return [T_CLASS, T_FUNCTION];
    }

    function process(File $file, $position)
    {
        $moduleClassPath = $this->getModuleClassPath($file);
        if(realpath($file->path) !== $moduleClassPath){
            return;
        }

        $token = $file->getTokens()[$position];
        $content = $file->getTokens()[$position+2]['content'];

        if($token['code'] === T_CLASS){
            $this->insideModuleClass = basename($moduleClassPath) === "$content.php";
        }
        else if($this->insideModuleClass && $token['code'] === T_FUNCTION && $content === '__construct'){
            $file->addError(SniffMessages::formatMessage("
                The module class contains a constructor.  We ask that constructors be
                removed from module classes as they must be manually reviewed as they can cause
                unnecessary REDCap system load as the module class must be instantiated every time
                module links are displayed in the left menu.  They are also prone to context based
                errors (e.g. calling [30;47mgetProjectSetting()[0m from non-project pages & crons).
                Lazy loading is recommended instead
                (e.g. https://docs.php.earth/php/ref/oop/design-patterns/lazy-loading/).
                This generally means replacing any references to [30;47m\$this->myVariable[0m with
                [30;47m\$this->getMyVariable()[0m, and instead performing initialization calls
                within that method the first time it is called.
            "), $position, 'Found');
        }
    }

    private function getModuleClassPath($file){
        if(!isset($this->moduleClassPath)){
            $root = $file->config->getSettings()['files'][0];
            $config = SniffMessages::getConfig($root);
            if(empty($config)){
                $this->moduleClassPath = null;
            }
            else{
                $parts = explode('\\', $config['namespace']);
                $className = end($parts);

                $this->moduleClassPath = realpath("$root/$className.php");
            }
        }

        return $this->moduleClassPath;
    }
}
