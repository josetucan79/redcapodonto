<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use HttpClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\SessionDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\FhirCookieDTO;

class NoState extends State
{

	const MAX_RETRIES = 10;


	public function run() {
		$this->removeCookie();
		$this->makeState();
	}

	/**
	 * remove the FHIR cookie whenever a new state is issued
	 *
	 * @return void
	 */
	function removeCookie() {
		FhirCookieDTO::destroy(FhirLauncher::COOKIE_NAME);
	}

	/**
	 * create a new session if there is no one or the state is invalid.
	 * if a session is available, then save a reference to it
	 *
	 * @return void
	 */
	public function makeState() {
		$context = $this->context;
		$URL = $context->getRedirectUrl();
		$session = $this->createSession();
		$_GET[FhirLauncher::FLAG_SESSION_ID] = $session->state;
		$_GET[FhirLauncher::FLAG_SESSION_CREATE_COUNTER] = $sessionInstance = intval($_GET[FhirLauncher::FLAG_SESSION_CREATE_COUNTER] ?? 0)+1;
		$stop = $this->maxRetriesReached($sessionInstance);
		if($stop) {
			$_GET[FhirLauncher::FLAG_ERROR] = "Error: could not create a session after ".self::MAX_RETRIES." attempts";
			$query = http_build_query($_GET);
			HttpClient::redirect("$URL?$query", true, 302);
		}
		$this->context->setSession($session);
		$query = http_build_query($_GET);
		HttpClient::redirect("$URL?$query", true, 302);
	}

	function maxRetriesReached($total) {
		return $total>self::MAX_RETRIES;
	}

	/**
	 * create a custom session with a state that will be used for the whole process.
	 * will contain data during the authorization process
	 *
	 * @return SessionDTO
	 */
	public function createSession() {
		$session = $this->context->makeSession();
		$session->state = SessionDTO::makeState();
		$launchPage =  $_SERVER['HTTP_REFERER'] ?? APP_PATH_WEBROOT_FULL;
		$session->launchPage = $launchPage;
		// add user if already authenticated
		if($user = $_SESSION['username'] ?? false) $session->user = $user;
		return $session;
	}
}