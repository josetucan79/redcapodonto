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
class TryAutoLoginState extends State
{
	use CanDecodeJWT;

	/**
	 * the key where the logged in username is stored in the PHP session
	 */
	const SESSION_USERNAME_KEY = 'username';

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
		
		// checking if user already logged in
		$user = $this->getCurrentUser($session);
		
		if($user) {
			$this->handleAuthenticatedUser($user, $session);
		} else {
			$this->attemptAutoLogin($session);
		}
	}

	private function handleAuthenticatedUser($user, $session)
    {
        $authenticatedUser = $_SESSION[self::SESSION_USERNAME_KEY] ?? null;

        if ($authenticatedUser && $authenticatedUser === $user) {
            $this->redirectToNextState($session);
        } else {
            throw new Exception("Error: you are not authorized", 401);
        }
    }


	/**
	 * Attempts auto-login if a FHIR user is available in the session.
	 *
	 * @param object $session The session object containing user data.
	 */
	private function attemptAutoLogin($session) {
		if($session->fhirUser) {
			// FHIR user is available; attempting auto-login
			$this->tryAutoLogin($session);
		}
		
		$this->redirectToNextState($session);
	}

	/**
	 * check on the main PHP session if a user is logged in
	 *
	 * @param SessionDTO $session
	 */
	public function getCurrentUser($session) {
		return $session->user;
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
			FhirLauncher::FLAG_STORE_TOKEN=>1,
		];
		$query = http_build_query($params);
		// redirecting to store token state
		HttpClient::redirect("$URL?$query", true, 302);
	}

	/**
	 * try to perform autologin
	 *
	 * @param SessionDTO $session
	 * @return string|false
	 */
	public function tryAutoLogin($session) {
		$user = $this->checkAutoLogin($session->fhirUser);
		if($user) $session->user = $user; // add the user to the session
		return $user;
	}

	/**
	 * attempt to autologin the user
	 *
	 * @param string $fhirUser
	 * @return void
	 */
	public function checkAutoLogin($fhirUser)
	{
		$fhirUser = trim($fhirUser);
		if(empty($fhirUser)) return false;
		// See if this user is mapped in the db table
		if($redcapUsername = $this->getMappedUsernameFromFhirUser($fhirUser))
		{
			// Perform auto-login
			require_once APP_PATH_DOCROOT . 'Libraries/PEAR/Auth.php';
			\Authentication::autoLogin($redcapUsername);
			return $redcapUsername;
		}
		return false;
	}

	/**
	 * Query table to get REDCap username from passed EHR username
	 *
	 * @param string $ehr_user
	 * @return string
	 */
	private function getMappedUsernameFromFhirUser($ehr_user)
	{
		$fhirSystem = $this->context->getFhirSystem();
		if(!$fhirSystem instanceof FhirSystem) return false;
		$ehrID = $fhirSystem->getEhrId();
		$queryString = "SELECT i.username
						FROM redcap_ehr_user_map m, redcap_user_information i
						WHERE ehr_id = ?
						AND i.ui_id = m.redcap_userid
						AND m.ehr_username = ?
						LIMIT 1";
		$result = db_query($queryString, [$ehrID, $ehr_user]);
		if(!$result) return false;
		if($row = db_fetch_assoc($result)) return $row['username'];
	}

}