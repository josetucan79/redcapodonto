<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use Exception;
use HttpClient;
use Vanderbilt\REDCap\Classes\Traits\CanDecodeJWT;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\OpenIdDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\SessionDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\AccessTokenResponseDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\ConformanceStatementDTO;

/**
 * try to perform auto login using the FHIR user
 * - extract the fhirUser (if not already available)
 * - if the user is not logged in and the fhirUser is mapped in the redcap database,
 * 	then perform autologin
 * 
 * if a patient IS NOT provided, then we are in 'standalone launch context':
 * the launcher will redirect to the previous page (if available)
 * 
 * if a patient IS provided, then we are in 'EHR launch' context:
 * the launcher will transition to the PortalState
 */
class ExtractFhirUserState extends State
{
	use CanDecodeJWT;

	/**
	 * the key where the logged in username is stored in the PHP session
	 */
	const SESSION_USERNAME_KEY = 'username';

	public function __destruct()
	{
		$session = $this->context->getSession();
		$this->checkForMissingFhirUser($session);
	}

	/**
	* Checks if a user is already logged in or attempts auto-login.
	* If a user is already logged in, checks if the stored session matches the current session.
	* Throws an exception if the sessions do not match.
	*
	* @throws Exception When authentication fails or sessions do not match.
	* @return void
	*/
	public function run() {
		$session = $this->context->getSession();

		// Ensure FHIR user is assigned to the session
        $this->ensureFhirUserInSession($session);
		
		$this->redirectToNextState($session);
	}

	private function ensureFhirUserInSession(SessionDTO $session) {
		$fhirUser = $this->getFhirUser($session); // Get existing FHIR user if available
		$fhirUsers = [];

		// Always ensure we fetch the list of available FHIR users
		$extractedFhirUser = $this->extractFhirUserFromOpenId($session, $fhirUsers);

		// Assign the list of available FHIR users to the session
		$session->fhirUsers = $fhirUsers;

		// Assign fhirUser if it was missing and we found one
		if (!$fhirUser && $extractedFhirUser) {
			$fhirUser = $extractedFhirUser;
		}

		// If there's exactly one FHIR user in the list, assign it
		if (!$fhirUser && count($fhirUsers) === 1) {
			$fhirUser = reset($fhirUsers);
		}

		$session->fhirUser = $fhirUser;
	}


	/**
	 * Check if there is no FHIR user and add a warning if necessary.
	 *
	 * @param object $session
	 */
	private function checkForMissingFhirUser($session): void
	{
		if (empty($session->fhirUser)) {
			$message = <<<HERE
			No FHIR user could be extracted from the session, the access token payload,
			or the OpenID token. As a result, mapping the FHIR user to the REDCap user was not possible,
			and the auto-login feature is disabled. To enable the auto-login feature, ensure a user context
			is properly set up during the launch. Please refer to the documentation or contact your EHR system
			administrators for assistance with this configuration.
			HERE;
			$session->addWarning($message);
		}
	}

	/**
	 * get a FHIR user from the session or from
	 * the data available in the AccessTokenResponseDTO
	 *
	 * @param SessionDTO $session
	 * @return string|false 
	 */
	public function getFhirUser($session) {
		$fhirUserAvailable = $session->fhirUser==true;
		if($fhirUserAvailable) return $session->fhirUser; // fhirUser already available in the session
		$accessTokenDTO = $session->accessToken ?? null;
		if(!is_object($accessTokenDTO) ) return false;
		$username = $accessTokenDTO->username ?? null;
		if($username) {
			// username provided during launch from EHR (Cerner)
			$fhirUser = trim(rawurldecode(urldecode($username)));
			return $fhirUser;
		}

		return false;
	}

	/**
	 * redirect to the next state based on the data available in the
	 * session and the launch type
	 *
	 * @param SessionDTO $session
	 * @return void
	 */
	public function redirectToNextState($session) {
		$state = @$session->state;
		$URL = $this->context->getRedirectUrl();
		$params = [
			'state' => $state,
			FhirLauncher::FLAG_TRY_AUTO_LOGIN=>1,
		];
		$query = http_build_query($params);
		// redirecting to store token state
		HttpClient::redirect("$URL?$query", true, 302);
	}


	/**
	 * extract the FHIR user from the access token payload.
	 * 
	 * Cerner, in a launch from EHR, provide the
	 * FHIR user as 'username' in the payload.
	 * 
	 * if the openid scope is specified, then decode the 
	 * JWT id_token and extract the fhirUser parameter
	 *
	 * @param SessionDTO $session
	 * @param array $fhir_users
	 * @return string
	 */
	public function extractFhirUserFromOpenId(SessionDTO $session, &$fhir_users = []) {
		$fhir_user = null;
		$accessTokenDto = $session->accessToken;
		if(!($accessTokenDto instanceof AccessTokenResponseDTO)) return;
		
		$openIdDTO = $accessTokenDto->getDecodedIdToken();
		$fhirSystem = $this->context->getFhirSystem();
		if(!$fhirSystem) return;

		$fhirBaseURL = $fhirSystem->getFhirBaseUrl();
		$conformanceStatement = $this->context->getConformanceStatement($fhirBaseURL);
		$publisher = $conformanceStatement->getPublisher();
		if(in_array(strtolower($publisher), ConformanceStatementDTO::PUBLISHER_CERNER)) {
			$fhir_user = $openIdDTO->sub ?? null;
		}

		$access_token = $accessTokenDto->access_token ?? '';
		$fhir_users = $this->getPractitioner($openIdDTO, $access_token);
		
		if( !$fhir_user && (count($fhir_users)===1) ) $fhir_user = reset($fhir_users);
		return $fhir_user;
	}

	/**
	 * fetch data about the practitioner
	 * 
	 * if the fhirUser parameter is not a full URL, then compose the URL using iss and fhirUser
	 * 
	 * @param OpenIdDTO $openIdDTO
	 * @param string $accessToken
	 * @return array associative array with system=>value
	 */
	public function getPractitioner($openIdDTO, $accessToken) {
		$removeTrailingSlashes = function($string) {
			return preg_replace('/\/*$/', '',$string);
		};
		try {
			$URL = $fhirUser = @$openIdDTO->fhirUser;
			
			if(!$this->validateURL($URL)) {
				// URL not valid; try to build a valid one
				// @$openIdDTO->fhirUser could be a partial URL and must be fixed
				$fhirSystem = $this->context->getFhirSystem();
				$fhirBaseURL = $removeTrailingSlashes($fhirSystem->getFhirBaseUrl());

				$iss = $removeTrailingSlashes($openIdDTO->iss ?? '');
				if($fhirUser) $URL = $iss.'/'.$fhirUser; // order is important here, and fhirUser should have precedence (Smart Health IT)
				else if($sub = $openIdDTO->sub) $URL = "{$fhirBaseURL}/practitioner/{$sub}"; // compose the practitioner URL (Epic)
			} 
			if(!$this->validateURL($URL)) return []; // URL still not valid; exit

			$practitioner = $this->context->getFhirData($URL, $accessToken);
			$identifiersData = $practitioner['identifier'] ?? [];
			$identifiers = [];
			$index = null;
			foreach ($identifiersData as $data) {
				$type = $data['type']['text'] ?? null;
				$system = $data['system'] ?? null;
				// index could be: type, system, or an incremental number
				if(isset($type)) $index = $type;
				else if(isset($system)) $index = $system;
				else $index = is_numeric($index) ? $index+1 : 0;
				$value = $data['value'] ?? null;
				if(!$value) continue;
				$identifiers[$index] = trim(rawurldecode(urldecode($value)));
			}
			return $identifiers;
		} catch (\Throwable $th) {
			// fail silently
			return [];
		}
	}

	/**
	 * Validate a URL
	 *
	 * @param string $URL
	 * @return bool
	 */
	private function validateURL($URL) {
		return filter_var($URL, FILTER_VALIDATE_URL);
	}

}