<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher;

use System;
use Language;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Utility\TwigRenderer;

class FhirRenderer
{

	/**
	 *
	 * @var TwigRenderer
	 */
	private $engine;

	/**
	 *
	 * @var FhirRenderer
	 */
	private static $instance;

	public function __construct() {
		// set some shared variables for the render engine
		$this->engine = TwigRenderer::create()
			->templatePath(__DIR__.'/templates')
			->addGlobals($this->getGlobals())
			->disableCache();
	}

	public function getGlobals() {
		$config = REDCapConfigDTO::fromArray(System::getConfigVals());
		$languageGlobal = $config->language_global;
        $lang = Language::getLanguage($languageGlobal);
		// set some shared variables for the render engine
		return [
			'SUPER_USER' =>  defined('SUPER_USER') ? SUPER_USER : false ,
			'APP_PATH_DOCROOT' =>  APP_PATH_DOCROOT,
			'TEMPLATES_DIR' =>  __DIR__.'/templates',
			'APP_PATH_WEBROOT_FULL' =>  APP_PATH_WEBROOT_FULL,
			'APP_PATH_IMAGES' =>  APP_PATH_IMAGES,
			'APP_PATH_CSS' =>  APP_PATH_CSS,
			'APP_PATH_JS' =>  APP_PATH_JS,
			'APP_PATH_WEBROOT' =>  APP_PATH_WEBROOT,
			'APP_PATH_WEBROOT_PARENT' =>  APP_PATH_WEBROOT_PARENT,
			'APP_PATH_WEBPACK' =>  APP_PATH_WEBPACK,
			'REDCAP_VERSION' =>  REDCAP_VERSION,
			'redirectURL' =>  APP_PATH_WEBROOT_FULL.FhirLauncher::REDIRECT_PAGE,
			'redcapConfig' =>  $config,
			
			'redcap_csrf_token' =>  $this->getCsrfToken(),
			'lang' =>  $lang,
		];
	}

	/**
	 * get an existing CSRF token
	 * or generate a new one
	 *
	 * @return string
	 */
	private function getCsrfToken() {
		$csrfToken = System::getCsrfToken();
		if(!$csrfToken) $csrfToken = System::generateCsrfToken();
		return $csrfToken ?? '';
	}

	public static function engine(): TwigRenderer {
		if(!self::$instance) self::$instance = new self();
		return self::$instance->engine;
	}

	public static function create() {
		if(!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
}