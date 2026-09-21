<?php
use eftec\bladeone\BladeOne;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;

/**
 * helper class to get a blade instance
 * @method static Renderer run(string $path, array $variables)
 */
 class Renderer
{

    /**
     * get an instance of the blade template engine
     * @see https://github.com/EFTEC/BladeOne
     *
     * @return BladeOne
     */
    public static function getBlade($templatePath = null, $compiledPath = null, $mode = BladeOne::MODE_AUTO)
    {
        if(!isset($templatePath)) $templatePath =  APP_PATH_VIEWS . 'blade';
        if(!isset($compiledPath)) $compiledPath =  APP_PATH_TEMP . 'cache';
        // crete the cache directory if does not exists
        if (!file_exists($compiledPath)) {
            mkdir($compiledPath, 0777, true);
        }
        $blade = new BladeOne($templatePath, $compiledPath, $mode);
        self::applyREDCapVariables($blade);
        return $blade;
    }

    /**
     * apply REDCap variables to the rendering engine
     * @param BladeOne $blade
     * @return void
     */
    private static function applyREDCapVariables(BladeOne $blade) {
        $config = REDCapConfigDTO::fromArray(System::getConfigVals());
        if(isset($GLOBALS["lang"]) && is_array($GLOBALS["lang"]) && count($GLOBALS["lang"]) > 1000) {
            $lang = $GLOBALS["lang"];
        }else {
            $languageGlobal = $config->language_global;
            $lang = Language::getLanguage($languageGlobal);
        }


        $isBrowserSupported = function() {
            $internetExplorerVersion = vIE();
            if($internetExplorerVersion===-1) return true;
            return $internetExplorerVersion>11;
        };

        $blade->share('SUPER_USER', defined('SUPER_USER') ? SUPER_USER : false );
		$blade->share('APP_PATH_DOCROOT', APP_PATH_DOCROOT);
		$blade->share('TEMPLATES_DIR', __DIR__.'/templates');
		$blade->share('APP_PATH_WEBROOT_FULL', APP_PATH_WEBROOT_FULL);
		$blade->share('APP_PATH_IMAGES', APP_PATH_IMAGES);
		$blade->share('APP_PATH_CSS', APP_PATH_CSS);
		$blade->share('APP_PATH_JS', APP_PATH_JS);
		$blade->share('APP_PATH_WEBROOT', APP_PATH_WEBROOT);
		$blade->share('APP_PATH_WEBROOT_PARENT', APP_PATH_WEBROOT_PARENT);
		$blade->share('APP_PATH_WEBPACK', APP_PATH_WEBPACK);
		$blade->share('REDCAP_VERSION', REDCAP_VERSION);
		$blade->share('PAGE', defined('PAGE') ? PAGE : false);
		$blade->share('redcapConfig', $config);
		$blade->share('redcap_csrf_token', System::getCsrfToken());
		$blade->share('lang', $lang);
        $blade->share('browser_supported', $isBrowserSupported());
        $blade->share('internetExplorerVersion', vIE());
    }

    /**
     * use static methods with the blade instance 
     *
     * @param string $method
     * @param array $params
     * @return void
     */
    public static function __callStatic($method, $params=array())
    {
        // Note: value of $name is case sensitive.
        $blade = self::getBlade();
        if(!method_exists($blade, $method)) return;
        return call_user_func_array( array($blade, $method), $params );
    }






}