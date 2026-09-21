<?php
namespace Vanderbilt\REDCap\Classes\Utility;

use finfo;
use Language;
use System;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use Twig\TwigFunction;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;

/**
 * Twig renderer builder with REDCap variables
 */
class TwigRenderer
{
    private $templatePath = null;
    private $cacheEnabled = false;
    private $cachePath = null;
    private $debugEnabled = null;
    private $autoReload = true;
    private $customOptions = [];
    private $customGlobals = [];
    private $includeREDCapGlobals = true;
    private $twig = null;

    /**
     * Add custom Twig functions
     */
    private function addCustomFunctions()
    {
        // Add the printImage function
        $this->twig->addFunction(new TwigFunction('printImage', function($path) {
            if (preg_match('/svg$/', $path) === 1) {
                return file_get_contents($path);
            } else {
                $finfo = new finfo(FILEINFO_MIME);
                $type = $finfo->file($path);
                $imageData = base64_encode(file_get_contents($path));
                $src = 'data: ' . $type . ';base64,' . $imageData;
                return '<img src="' . $src . '">';
            }
        }, ['is_safe' => ['html']]));

        // Add printSvg function
        $this->twig->addFunction(new TwigFunction('printSvg', function($path) {
            return file_get_contents($path);
        }, ['is_safe' => ['html']]));

        // Add loadJS function
        $this->twig->addFunction(new TwigFunction('loadJS', 'loadJS', ['is_safe' => ['html']]));

        // Add define function
        $this->twig->addFunction(new TwigFunction('define', function($name, $value) {
            define($name, $value);
            return '';
        }));

        // Add json_encode filter
        $this->twig->addFilter(new \Twig\TwigFilter('json_encode', 'json_encode'));

        // Add addLangToJS function
        $this->twig->addFunction(new TwigFunction('addLangToJS', 'addLangToJS', ['is_safe' => ['html']]));
    }

    /**
     * Create a new builder instance
     */
    public static function create()
    {
        return new self();
    }

    /**
     * Set the template path
     */
    public function templatePath($path)
    {
        $this->templatePath = $path;
        return $this;
    }

    /**
     * Enable caching
     */
    public function enableCache($cachePath = null)
    {
        $this->cacheEnabled = true;
        $this->cachePath = $cachePath ?: APP_PATH_TEMP . 'twig_cache';
        return $this;
    }

    /**
     * Disable caching
     */
    public function disableCache()
    {
        $this->cacheEnabled = false;
        $this->cachePath = null;
        return $this;
    }

    /**
     * Enable debug mode
     */
    public function enableDebug()
    {
        $this->debugEnabled = true;
        return $this;
    }

    /**
     * Disable debug mode
     */
    public function disableDebug()
    {
        $this->debugEnabled = false;
        return $this;
    }

    /**
     * Enable/disable auto reload
     */
    public function autoReload($enabled = true)
    {
        $this->autoReload = $enabled;
        return $this;
    }

    /**
     * Add custom Twig options
     */
    public function options(array $options)
    {
        $this->customOptions = array_merge($this->customOptions, $options);
        return $this;
    }

    /**
     * Add a global variable (during build)
     */
    public function addGlobal($key, $value)
    {
        $this->customGlobals[$key] = $value;
        return $this;
    }

    /**
     * Add multiple global variables (during build)
     */
    public function addGlobals(array $globals)
    {
        $this->customGlobals = array_merge($this->customGlobals, $globals);
        return $this;
    }

    /**
     * Skip adding REDCap globals (if you want to add them manually)
     */
    public function skipREDCapGlobals()
    {
        $this->includeREDCapGlobals = false;
        return $this;
    }

    /**
     * Include REDCap globals (default behavior)
     */
    public function includeREDCapGlobals()
    {
        $this->includeREDCapGlobals = true;
        return $this;
    }

    /**
     * Build and return the Twig Environment
     */
    public function build()
    {
        if ($this->twig !== null) {
            return $this->twig;
        }

        // Set defaults
        $templatePath = $this->templatePath ?: APP_PATH_VIEWS . 'twig';
        $debugEnabled = $this->debugEnabled ?? (defined('SUPER_USER') && SUPER_USER);

        // Build options
        $options = [
            'cache' => $this->cacheEnabled ? $this->cachePath : false,
            'debug' => $debugEnabled,
            'auto_reload' => $this->autoReload,
        ];

        // Merge custom options
        $options = array_merge($options, $this->customOptions);

        // Create cache directory if caching is enabled
        if ($this->cacheEnabled && !file_exists($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }

        $loader = new FilesystemLoader($templatePath);
        $this->twig = new Environment($loader, $options);

        if ($debugEnabled) {
            $this->twig->addExtension(new DebugExtension());
        }

        // Add custom Twig functions
        $this->addCustomFunctions();

        // Add globals as part of build process
        if ($this->includeREDCapGlobals) {
            $this->addREDCapGlobals();
        }

        // Add custom globals
        foreach ($this->customGlobals as $key => $value) {
            $this->twig->addGlobal($key, $value);
        }

        return $this->twig;
    }

    /**
     * Add REDCap variables as Twig globals
     */
    private function addREDCapGlobals()
    {
        $config = REDCapConfigDTO::fromArray(System::getConfigVals());
        
        if (isset($GLOBALS["lang"]) && is_array($GLOBALS["lang"]) && count($GLOBALS["lang"]) > 1000) {
            $lang = $GLOBALS["lang"];
        } else {
            $languageGlobal = $config->language_global;
            $lang = Language::getLanguage($languageGlobal);
        }

        $isBrowserSupported = function() {
            $internetExplorerVersion = vIE();
            if ($internetExplorerVersion === -1) return true;
            return $internetExplorerVersion > 11;
        };

        // Add all REDCap variables as globals
        $globals = [
            'SUPER_USER' => defined('SUPER_USER') ? SUPER_USER : false,
            'APP_PATH_DOCROOT' => APP_PATH_DOCROOT,
            'TEMPLATES_DIR' => __DIR__ . '/templates',
            'APP_PATH_WEBROOT_FULL' => APP_PATH_WEBROOT_FULL,
            'APP_PATH_IMAGES' => APP_PATH_IMAGES,
            'APP_PATH_CSS' => APP_PATH_CSS,
            'APP_PATH_JS' => APP_PATH_JS,
            'APP_PATH_WEBROOT' => APP_PATH_WEBROOT,
            'APP_PATH_WEBROOT_PARENT' => APP_PATH_WEBROOT_PARENT,
            'APP_PATH_WEBPACK' => APP_PATH_WEBPACK,
            'REDCAP_VERSION' => REDCAP_VERSION,
            'redcapConfig' => $config,
            'redcap_csrf_token' => System::getCsrfToken(),
            'lang' => $lang,
            'browser_supported' => $isBrowserSupported(),
            'internetExplorerVersion' => vIE(),
        ];

        foreach ($globals as $key => $value) {
            $this->twig->addGlobal($key, $value);
        }
    }

    /**
     * Add global variable after build (to existing Twig instance)
     */
    public function setGlobal($key, $value)
    {
        if ($this->twig === null) {
            // If not built yet, add to custom globals for build process
            $this->customGlobals[$key] = $value;
        } else {
            // Add directly to built Twig instance
            $this->twig->addGlobal($key, $value);
        }
        return $this;
    }

    /**
     * Add multiple global variables after build
     */
    public function setGlobals(array $globals)
    {
        foreach ($globals as $key => $value) {
            $this->setGlobal($key, $value);
        }
        return $this;
    }

    /**
     * Get the current Twig instance (builds if needed)
     */
    public function getTwig()
    {
        return $this->build();
    }

    /**
     * Render a template
     */
    public function render($template, array $variables = [])
    {
        return $this->build()->render($template, $variables);
    }

    /**
     * Display a template
     */
    public function display($template, array $variables = [])
    {
        echo $this->render($template, $variables);
    }

    // Static convenience methods for quick usage
    public static function quick()
    {
        return self::create()->disableCache();
    }

    public static function cached($cachePath = null)
    {
        return self::create()->enableCache($cachePath);
    }
}