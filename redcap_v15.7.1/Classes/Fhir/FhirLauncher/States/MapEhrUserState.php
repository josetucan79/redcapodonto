<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use User;
use Exception;
use HttpClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirRenderer;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\SessionDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;

/**
 * make sure the user is authenticated
 * - store the token
 * - save a reference of the session in the main PHP session
 */
class MapEhrUserState extends State
{

	public function run() {
		$session = $this->context->getSession();
		$fhirUser = $session->fhirUser;
		$fhirUsers = $session->fhirUsers;
		$redcapUser = $session->user;

		// deal with the chosen FHIR user type
		if($request = $_POST) {
			$this->manageSelection($session, $fhirUsers, $redcapUser);
		}

		if($fhirUser && $redcapUser) {
			// if a FHIR user is available here, then override the mapping (if exists)
			$this->mapEhrUser($fhirUser, $redcapUser);
		}

		// redirect to next state without managing extra Fhir user types (could be confusing)
		$this->redirectToNextState($session);

		// $this->manageMultipleFhirUserTypes($session, $fhirUsers, $redcapUser);
	}

	/**
	 * manage selection made using the UI in map_ehr_user.blade.php
	 *
	 * @param SessionDTO $session
	 * @param array $fhirUsers
	 * @param string $redcapUser
	 * @return void
	 */
	private function manageSelection($session, $fhirUsers, $redcapUser) {
		$skip = boolval($request['skip'] ?? null);
		if($skip) {
			// user decided to skip the mapping
			$this->redirectToNextState($session);
		}

		$selectedFhirUser = $request['fhir-user'] ?? null;
		$session->fhirUser = $fhirUser = $fhirUsers[$selectedFhirUser] ?? null;
		if($fhirUser && $redcapUser) {
			// the user has chosen to map '{$fhirUser}' to '{$redcapUser}'
			$this->mapEhrUser($fhirUser, $redcapUser);
		}
		// whatever happens, even if nothing was mapped, go to next state
		$this->redirectToNextState($session);
	}

	/**
	 * check if more than one user is available
	 *
	 * @param SessionDTO $session
	 * @param array $fhirUsers
	 * @param string $redcapUser
	 * @return void
	 */
	private function manageMultipleFhirUserTypes($session, $fhirUsers, $redcapUser) {
		// code from here will manage multiple fhir user types retrieved using the open ID scope
		$mappedEhrUser = $this->getMappedUsernameFromFhirUser($redcapUser);
		if($mappedEhrUser && ( empty($fhirUsers) || in_array($mappedEhrUser, $fhirUsers)) ) {
			// mapping already in place; moving to next state
			$this->redirectToNextState($session);
		}


		// user mapping is usually performed after a launch from EHR, but could work in standalone launch as well
		// TODO: make sure that this one is not interfering with the ehr_user_mapping when in standalone launch context

		if(empty($fhirUsers)) $this->redirectToNextState($session);
		if($redcapUser && count($fhirUsers)===1) {
			// one mapping option is available; mapping FHIR user to REDCap user
			$fhirUser = reset($fhirUsers);
			$this->mapEhrUser($fhirUser, $redcapUser);
			$this->redirectToNextState($session);
		}
		// multiple mapping options are available; user must select one
		$renderer = FhirRenderer::engine();
		$html = $renderer->render('map_ehr_user', [
			'session' => $fhirUsers,
			'fhirUsers' => $session,
		]);
		print($html);
	}

	/**
	 * Query table to get EHR user associated to a REDCap username
	 *
	 * @param string $ehr_user
	 * @return string
	 */
	private function getMappedUsernameFromFhirUser($redcapUsername)
	{
		$fhirSystem = $this->context->getFhirSystem();
		if(!$fhirSystem instanceof FhirSystem) return false;
		$ehrID = $fhirSystem->getEhrId();
		$queryString = "SELECT ehr_username FROM redcap_ehr_user_map
			WHERE ehr_id = ?
			AND redcap_userid = (
				SELECT ui_id FROM redcap_user_information WHERE username = ?
			) LIMIT 1";
		$result = db_query($queryString, [$ehrID, $redcapUsername]);
		if(!$result) return false;
		if($row = db_fetch_assoc($result)) return $row['ehr_username'];
	}

	/**
	 * Map REDCap username to EHR username in db table
	 *
	 * @param string $ehr_user
	 * @param string $redcap_user
	 * @throws Exception if no valid user is provided
	 * @return mysqli_result|bool
	 */
	public function mapEhrUser($ehr_user, $redcap_user)
	{
		$fhirSystem = $this->context->getFhirSystem();
		if(!$fhirSystem instanceof FhirSystem) return false;
		$ehrID = $fhirSystem->getEhrId();
		// Get user ui_id
		$user_id = User::getUIIDByUsername($redcap_user);
		if(empty($user_id)) {
			return false;
			// throw new \Exception("Error mapping the EHR user: no valid REDCap user provided.", 1);
		}
		
		$sql = "REPLACE INTO redcap_ehr_user_map (ehr_username, redcap_userid, ehr_id) VALUES (?, ?, ?)";
		return db_query($sql, [$ehr_user, $user_id, $ehrID]);
	}

	/**
	 * redirect to the next state based on the data available in the
	 * session and the launch type
	 *
	 * @param SessionDTO $session
	 * @return void
	 */
	public function redirectToNextState($session) {
		$URL = $this->context->getRedirectUrl();
		$state = $session->state ?? '';
		$accessTokenDTO = $session->accessToken ?? '';
		$launchType = $session->launchType;
		$patient = $accessTokenDTO->patient ?? null;
		if($patient && $launchType===FhirLauncher::LAUNCHTYPE_EHR) {
			// set data for the patient portal (EHR launch) and redirect to the store token state
			$params = ['state' => $state, FhirLauncher::FLAG_PATIENT_ID=>$patient];
			$query = http_build_query($params);
			// redirecting to 'patient connector' page
			HttpClient::redirect("$URL?$query", true, 302);
		}else {
			// redirect to success page (standalone launch) and redirect to the store token state
			$params = ['state' => $state, FhirLauncher::FLAG_AUTH_SUCCESS=>1];
			$query = http_build_query($params);
			// redirecting to 'auth success' page
			HttpClient::redirect("$URL?$query", true, 302);
		}
	}

}