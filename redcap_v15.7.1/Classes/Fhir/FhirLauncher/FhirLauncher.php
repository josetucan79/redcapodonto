<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher;

use Logging;
use Session;
use Exception;
use Throwable;
use HttpClient;
use Authentication;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\State;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\NoState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\SessionDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\ErrorState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\ReadyState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\FhirCookieDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\PatientState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\EhrLaunchState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\SelectEhrState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\MapEhrUserState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\StoreTokenState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\AuthSuccessState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\TryAutoLoginState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\ConformanceStatementDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\StandaloneLaunchState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\RequestAccessTokenState;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Middlewares\AuthenticationMiddleware;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\PersistenceStrategies\SessionStrategy;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\PersistenceStrategies\PersistenceStrategyInterface;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\ExtractFhirUserState;

class FhirLauncher
{
	/**
	 * GET labels for the states
	 */
	const FLAG_EHR_LAUNCH = 'launch';
	const FLAG_STANDALONE_LAUNCH = 'standalone_launch';
	const FLAG_REQUEST_TOKEN = 'code';
	const FLAG_AUTH_SUCCESS = 'auth_success';
	const FLAG_PATIENT_ID = 'fhirPatient';
	const FLAG_ERROR = 'error';
	const FLAG_ERROR_URI = 'error_uri'; // cerner specific
	const FLAG_SESSION_ID = 'state';
	const FLAG_MAP_EHR_USER = 'map_ehr_user';
	const FLAG_TRY_AUTO_LOGIN = 'auto_login';
	const FLAG_EXTRACT_FHIR_USER = 'extract_fhir_user';
	const FLAG_STORE_TOKEN = 'store_token';
	const FLAG_SESSION_CREATE_COUNTER = 's_counter';
	const FLAG_LAUNCH_TYPE = 'launch_type';
	const FLAG_EHR_ID = 'ehr_id';

	/**
	 * launch types
	 */
	const LAUNCHTYPE_STANDALONE = 'standalone';
    const LAUNCHTYPE_EHR = 'ehr';

	/**
	 * name of the cookie used in the
	 * authentication process
	 */
	const COOKIE_NAME = 'fhir-launch-cookie';

	/**
	 * redirect URL page
	 */
	const REDIRECT_PAGE = 'ehr.php';

	/**
	 *
	 * @var State
	 */
	private $state;

	/**
	 *
	 * @var SessionDTO
	 */
	private $session;

	/**
	 * REDCap system configuration
	 *
	 * @var REDCapConfigDTO
	 */
	private $config;

	/**
	 *
	 * @var ConformanceStatementDTO
	 */
	private $conformanceStatement;

	/**
     *
     * @var Exception[]
     */
    public $errors = [];
	
	/**
	 *
	 * @var PersistenceStrategyInterface
	 */
	private $persistenceStrategy;

	private $fhirSystem;
	

	/**
	 *
	 * @param PersistenceStrategyInterface|null $strategy
	 */
	public function __construct($strategy=null)
	{
		$this->config =  REDCapConfigDTO::fromDB();
		if(is_null($strategy)) {
			$strategy = new SessionStrategy(); // default persistence strategy
			// $strategy = new FileCacheStrategy(); // default persistence strategy
			// $strategy = new CookieStrategy();
		}
		$this->persistenceStrategy = $strategy;
		// set_error_handler([$this, 'errorHandler'], $error_levels = E_ALL);
	}

	public function persistenceStrategy() {
		return $this->persistenceStrategy;
	}

	public function __destruct()
	{
		if( !($this->session instanceof SessionDTO) ) return;
		$this->session->addPreviousState($this->state); //record current state
		$this->session->save($this->persistenceStrategy());
	}

	/**
	 *
	 * @return FhirSystem|null
	 */
	public function getFhirSystem() {
		if(!isset($this->fhirSystem)) {
			$session = $this->getSession();
			$ehrID = $session->ehrID;
			if(!$ehrID) return null;
			$this->fhirSystem = new FhirSystem($ehrID);
		}
		return $this->fhirSystem;
	}

	/**
	 * make sure we are starting from scratch
	 * (the previous state must be a NoState)
	 *
	 * @return void
	 */
	public function forceBlankSession() {
		if(!($this->session instanceof SessionDTO)) return;
		$previousState = end($this->session->previousStates);
		if($previousState!=false && !($previousState === NoState::class)) HttpClient::redirect($this->getRedirectUrl(), true, 302);
	}

	/**
	 * provide the conformance statement from
	 * the local variable, the cache, or remote
	 *
	 * @param String $fhirBaseURL
	 * @return ConformanceStatementDTO
	 */
	public function getConformanceStatement($fhirBaseURL) {
		if(isset($this->conformanceStatement)) return $this->conformanceStatement; // already available

		$removeTrailingSlashes = function($string) {
			return preg_replace('/\/*$/', '',$string);
		};
		$fhirBaseUrl = $removeTrailingSlashes($fhirBaseURL);
		$conformanceStatementURL = "$fhirBaseUrl/metadata"; // this is also used as key for the cache
		$fileCache = new FileCache(__CLASS__);
		$cachedConformanceStatement = $fileCache->get($conformanceStatementURL) ?? '';
		/** @var ConformanceStatementDTO $conformanceStatement */
		$conformanceStatement = unserialize($cachedConformanceStatement, ['allowed_classes'=>[ConformanceStatementDTO::class]]);
		if(!$conformanceStatement) {
			try {
				$response = HttpClient::request('GET', $conformanceStatementURL, ['headers' => ['Accept' => 'application/json']]);
				$data = json_decode($response->getBody(), true);
				if(!$data) throw new Exception("no valid data received", 1);
				
				$conformanceStatement = ConformanceStatementDTO::fromArray($data);
				$fileCache->set($conformanceStatementURL, serialize($conformanceStatement));
			} catch (\Throwable $th) {
				// fail silently; set to empty object
				$conformanceStatement = new ConformanceStatementDTO;
			}
		}
		$this->conformanceStatement = $conformanceStatement;
		return $this->conformanceStatement;
	}

	/**
	 * detect if we are in a launch from EHR context
	 * 
	 * if EHR launch context then:
	 * - disable REDCap messanger
	 * - prevent ClickJackingControl
	 *
	 * @return boolean
	 */
	public static function inEhrLaunchContext() {
		try {
			$fhirCookie = self::getFhirContextCookie();
			if(!($fhirCookie instanceof FhirCookieDTO)) return false;
			$launchType = $fhirCookie->launchType;
			return (strcasecmp($launchType, self::LAUNCHTYPE_EHR)===0);
		} catch (\Throwable $th) {
			print_r($th);
		}
	}

	public static function getFhirContextCookie() {
		return FhirCookieDTO::fromName(self::COOKIE_NAME);
	}

	/**
	 *
	 * @param string $state
	 * @return SessionDTO
	 */
	public function getSession() {
		if(!$this->session) {
			$this->initSession();
		}
		return $this->session;
	}

	/**
	 * create a new session if there is no one or the state is invalid.
	 * if a session is available, then save a reference to it
	 * 
	 *
	 * @return void
	 */
	public function initSession() {
		$redirectToNoState = function() {
			// send back to redirect URL with no state (so it will be generated
			// also count how many tries REDCap will try to generate a new session
			$URL = $this->getRedirectUrl();
			$_GET = [self::FLAG_SESSION_CREATE_COUNTER => intval($_GET[self::FLAG_SESSION_CREATE_COUNTER] ?? 0)+1];
			$query = http_build_query($_GET);
			HttpClient::redirect("$URL?$query", true, 302);
		};
		Session::init();
		$state = $_GET[self::FLAG_SESSION_ID] ?? null;
		$strategy = $this->persistenceStrategy();
		$this->session = $session = SessionDTO::fromState($state, $strategy); // this is fine
		if(!$session) $redirectToNoState(); // the session could not be recreated; redirect to no state
	}

	/**
	 * create an empty session
	 * with the persistence strategy defined in constructor
	 * 
	 * @return SessionDTO
	 */
	public function makeSession() {
		$session = new SessionDTO();
		return $session;
	}

	/**
	 * destroy the session if available
	 *
	 * @return void
	 */
	public function destroySession() {
		$session = $this->session;
		if($session instanceof SessionDTO) $session->destroy($this->persistenceStrategy());
	}

	/**
	 *
	 * @param SessionDTO $session
	 * @return void
	 */
	public function setSession(SessionDTO $session) {
		$this->session = $session;
	}

	/**
	 *
	 * @param string $state
	 * @param PersistenceStrategyInterface|null $strategy
	 * @return SessionDTO
	 */
	public static function getSessionFromState($state, $strategy=null) {
		$instance = new self($strategy);
		$strategy = $instance->persistenceStrategy();
		$session = SessionDTO::fromState($state, $strategy);
		return $session;
	}

	/**
	 *
	 * @param integer $errno
	 * @param string $errstr
	 * @param string|null $errfile
	 * @param integer|null $errline
	 * @param array|null $errcontext
	 * @return boolean
	 */
	private function errorHandler($errno, $errstr, $errfile = null, $errline = null, $errcontext = null): bool {
		if (!(error_reporting() & $errno)) {
			// This error code is not included in error_reporting, so let it fall
			// through to the standard PHP error handler
			return false;
		}
		return true;
	}

	/**
	 * return the REDCap system configuration
	 *
	 * @return REDCapConfigDTO
	 */
	public function getConfig() { return $this->config; }

	/**
	 * detect and transition to a specific state using global variables
	 *
	 * @return void
	 */
	private function detectState() {
		$authMiddleware = new AuthenticationMiddleware($this);
		// order is important
		// Check for mandatory state
		if (!isset($_GET[self::FLAG_SESSION_ID]) ?? false) {
			$this->transitionTo(new NoState($this));
			return;
		}
	
		// Specific state checks with higher priority
		// else if($_GET[self::FLAG_REGISTER_USER] ?? false) { $state = new RegisterUserState($this); }
		if($_GET[self::FLAG_STORE_TOKEN] ?? false) { $state =  (new StoreTokenState($this))->add($authMiddleware); }
		else if($_GET[self::FLAG_MAP_EHR_USER] ?? false) { $state = (new MapEhrUserState($this))->add($authMiddleware); }
		else if($_GET[self::FLAG_EXTRACT_FHIR_USER] ?? false) { $state = new ExtractFhirUserState($this); }
		else if($_GET[self::FLAG_TRY_AUTO_LOGIN] ?? false) { $state = new TryAutoLoginState($this); }

		// ensure a ehr ID is selected before entering a launch state
		else if(!($_GET[self::FLAG_EHR_ID] ?? false) && (
			($_GET[self::FLAG_STANDALONE_LAUNCH] ?? false) ||
			($_GET[self::FLAG_EHR_LAUNCH] ?? false)
		)) $state = new SelectEhrState($this);

		// launch types
		else if($_GET[self::FLAG_STANDALONE_LAUNCH] ?? false) $state = new StandaloneLaunchState($this);
		else if($_GET[self::FLAG_EHR_LAUNCH] ?? false) $state = new EhrLaunchState($this);
		
    	// Authorization and token-related states
		else if($_GET[self::FLAG_AUTH_SUCCESS] ?? false) $state = new AuthSuccessState($this);
		else if($_GET[self::FLAG_REQUEST_TOKEN] ?? false) $state = new RequestAccessTokenState($this);

		// Patient-related state
		else if($_GET[self::FLAG_PATIENT_ID] ?? false) $state = (new PatientState($this))->add($authMiddleware);

		// Default state
		else $state = new ReadyState($this);

		// DEBUG: comment this once done debugging
		// $currentURL = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		// Logging::writeToFile(APP_PATH_TEMP.'EHR URIs.log', $currentURL);
		// END DEBUG
		foreach ($state->middlewares() as $middleware) {
			$state = $middleware->handle($state);
		}
		$this->transitionTo($state);
	}

	/**
	 *
	 * @param State $state
	 * @param bool $exit
	 */
	public function transitionTo($state, $exit=false) {
		$this->state = $state;
		$this->state->run();
		if($exit) exit();
	}


	/**
	 * get FHIR data from the EHR system
	 *
	 * @param string $URL
	 * @param string $accessToken
	 * @return array
	 */
	public function getFhirData($URL, $accessToken) {
		$http_options = [
			'headers' => [
				'Accept' => 'application/json',
				'Authorization' => "Bearer {$accessToken}",
			],
			'form_params' => [],
		];

		$response = HttpClient::request('GET', $URL, $http_options);
		$payload = json_decode($response->getBody(), true);
		return $payload;
	}

	
	/**
	 * Returns the validated URL, or false if the filter fails
	 * 
	 * @param string $url
	 * @return string|false
	 */
	public static function validateURL($URL) {
		// remove double slashes if not preceeded by a colon symbol
		$URL = preg_replace('/(?<!:)\/\//', '/', $URL);
		// validate URL
		return filter_var($URL, FILTER_VALIDATE_URL);
	}

	public function getRedirectUrl() { return self::validateURL(APP_PATH_WEBROOT_FULL.self::REDIRECT_PAGE);}

	public function getLaunchURL() { return self::validateURL(APP_PATH_WEBROOT_FULL.self::REDIRECT_PAGE);}

	public static function getStandaloneLaunchURL() {
		$URL = APP_PATH_WEBROOT_FULL.self::REDIRECT_PAGE.'?standalone_launch=1';
		return self::validateURL($URL);
	}

	/**
	 * Enables authentication if a specific REDCap login form submission is detected.
	 *
	 * This method checks for the presence of a REDCap login form submission identified
	 * by the 'redcap_login_a38us_09i85' POST parameter. If this parameter is not set, the function
	 * returns immediately, taking no action. This check ensures that the function proceeds only
	 * when the specific form submission is detected.
	 *
	 * If the form submission is detected and the 'OVERRIDE-NOAUTH' constant is not already defined,
	 * the function defines 'OVERRIDE-NOAUTH' as true. This acts as an indicator or flag to potentially
	 * override any existing NOAUTH constant or logic elsewhere in the application, signaling that
	 * authentication should be enforced.
	 *
	 * After setting the 'OVERRIDE-NOAUTH' constant, the function calls `Authentication::authenticate()`
	 * to handle the actual authentication process.
	 *
	 * @return void
	 */
	function enableAuthenticationIfFormSubmission() {
		$forceAuth = function() {
			if(defined('OVERRIDE-NOAUTH')) return;
			define('OVERRIDE-NOAUTH', true);
			Authentication::authenticate();
		};
		/* $authMethod = $this->config->auth_meth_global;
		if(preg_match('/oauth2_azure_ad/', $authMethod)) {
			$forceAuth();
		}; */

		if(isset($_POST['redcap_login_a38us_09i85'])) $forceAuth();
	}

	/**
	 * Handles the transition to various states in the authentication process of the SMART on FHIR workflow.
	 *
	 * This method is responsible for managing the state transitions within the authentication process
	 * of the SMART on FHIR workflow. It primarily attempts to detect and transition to the appropriate
	 * state based on the current context. If an error occurs during this process, the method catches
	 * the throwable exception, logs the error, and then transitions to an error state to gracefully handle
	 * the failure.
	 *
	 * The method first calls `detectState()` to determine the current state and make the appropriate transition.
	 * If an exception is thrown during this process, the method captures the error message, logs it, and
	 * checks if an error already exists in the request parameters to prevent infinite reloads.
	 * If no pre-existing error is detected, it sets the error flag in the request parameters, constructs
	 * a redirect URL with these parameters, and then redirects to the error state using an HTTP 302 response.
	 *
	 * @throws Throwable If an error occurs during state detection or transition, the method catches
	 *                   the throwable and handles it by logging and redirecting to an error state.
	 * @return void The method does not return a value but performs redirections based on the application state.
	 */
	public function handleStates() {
		try {
			$this->enableAuthenticationIfFormSubmission();
			$this->detectErrors();
			$this->detectState();
		} catch (Throwable $th) {
			// add the error and redirect to the error state
			$this->addError($th);
			$this->transitionTo(new ErrorState($this), true);
		}
	}

	/**
	 * Detects and handles errors during the application flow.
	 *
	 * This method checks if any errors have occurred in the application flow. If errors are detected,
	 * it transitions the application to the error state and logs the errors.
	 *
	 * @return void
	 */
	private function detectErrors() {
		if($this->hasErrors()) {
			$this->transitionTo(new ErrorState($this), true);
		}
		if($error = $_GET[self::FLAG_ERROR] ?? false) {
			$this->addError(new Exception($error, 1));
			if($error_uri = $_GET[self::FLAG_ERROR_URI] ?? false) $this->addError(new Exception($error_uri, 1));
			$this->transitionTo(new ErrorState($this), true);
		}
	}

	/**
	 *
	 * @param Exception $error
	 * @return void
	 */
	public function addError($error) { $this->errors[] = $error; }

	/**
	 *
	 * @return Exception[]
	 */
	public function getErrors() { return $this->errors; }

	/**
	 *
	 * @return boolean
	 */
	public function hasErrors() { return count($this->errors) > 0; }

}