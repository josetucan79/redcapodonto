<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Utility\TemplateEngine;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Bundle;
use Vanderbilt\REDCap\Classes\Fhir\Facades\FhirClientFacade;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Patient;
use Vanderbilt\REDCap\Classes\AccountExpirationNotifier\AccountExpirationNotifier;
use Vanderbilt\REDCap\Classes\AccountExpirationNotifier\AccountExpirationNotifierFacade;
use Vanderbilt\REDCap\Classes\Utility\UrlChecker;

class ControlCenterController extends Controller
{
	// Perform the One-Click Upgrade
	public function oneClickUpgrade()
	{
		if (!ACCESS_SYSTEM_UPGRADE) exit('ERROR'); // Admins with upgrade privileges only
		Upgrade::performOneClickUpgrade();
	}
	
	// Execute the upgrade SQL script to complete an upgrade
	public function executeUpgradeSQL()
	{
        if (!ACCESS_SYSTEM_UPGRADE) exit('ERROR'); // Admins with upgrade privileges only
		print Upgrade::executeUpgradeSQL($_POST['version']);
	}
	
	// Execute the upgrade SQL script to complete an upgrade
	public function autoFixTables()
	{
		// Super users or admins with upgrade privileges only
		print (((SUPER_USER || ACCESS_SYSTEM_UPGRADE || ACCESS_SYSTEM_CONFIG) && Upgrade::autoFixTables()) ? '1' : '0');
	}
	
	// Hide the Easy Upgrade box on the main Control Center page
	public function hideEasyUpgrade()
	{
        // Admins with upgrade privileges only
		print ((ACCESS_SYSTEM_UPGRADE && Upgrade::hideEasyUpgrade($_POST['hide'])) ? '1' : '0');
	}
	
	/**
	 * get patient string identifiers from a patient using a social security number
	 * @see https://www.hl7.org/fhir/identifier-registry.html
	 * @deprecated 13.11.1
	 *
	 * @return void
	 */
	public function getFhirStringIdentifiers()
	{
		/**
		 * @param FhirClient $fhirClient
		 * @param string $systemIdentifier
		 * @param string $id
		 * @return Patient
		 */
		$getPatient = function($fhirClient, $systemIdentifier, $id) {
			$endpointFactory = $fhirClient->getFhirVersionManager()->getEndpointFactory();
			$endpoint = $endpointFactory->makeEndpoint(FhirCategory::DEMOGRAPHICS);
			$request = $endpoint->getSearchRequest(['identifier'=> "{$systemIdentifier}|{$id}"]);
			$response = $fhirClient->sendRequest($request);
			/** @var Bundle $bundle */
			$bundle = $response->getResource();
			$entries = $bundle->getEntries();
			/** @var Patient $patient */
			$patient = current($entries);
			return $patient;
		};

		try {
			global $userid;
			$user_id = User::getUIIDByUsername($userid);
			$ssn = trim($_GET['ssn']);
			preg_match('/[^\d\s-]/',$ssn, $not_allowed_matches); // only numbers, dashes, and spaces are allowed
			// check if SSN is empty or contains characters not allowed
			if(empty($ssn) || !empty($not_allowed_matches)) throw new Exception("Error: A valid SSN must be provided", 400);

			// extract numbers from the ssn string
			preg_match_all('/\d+/', $ssn, $matches);
			$ssn_numbers = implode('', $matches[0]);

			$fhirClient = FhirClientFacade::getInstance($project_id=null, $user_id);
			$systemIdentifiers = [
				'OID' => '2.16.840.1.113883.4.1', // this works for Epic
				'URI' => 'http://hl7.org/fhir/sid/us-ssn', // this works for Smart Health IT
			];
			foreach ($systemIdentifiers as $systemIdentifier) {
				$patient = $getPatient($fhirClient, $systemIdentifier, $ssn);
				if($patient) break;
			}

			if(empty($patient)) throw new Exception("No patient found for the provided SSN ({$ssn_numbers})", 404);
			$string_identifiers = $patient->getIdentifiers();
			// $string_identifiers = $patient->getIdentifiers();

			$response = array(
				'ssn' => $ssn_numbers,
				// 'patient' => $data->entry[0]->resource,
				'string_identifiers' => $string_identifiers,
				'success' => true,
			);

			HttpClient::printJSON($response);
		} catch (\Exception $e) {
			$response = array('message' => $e->getMessage());
			HttpClient::printJSON($response, $e->getCode());
		}
	}

	public static function saveAdminPriv()
	{
		if (!ADMIN_RIGHTS) exit('0');
		print User::saveAdminPriv($_POST['userid'], $_POST['attr'], $_POST['value']) ? '1' : '0';
	}

	public static function saveNewAdminPriv()
	{
		if (!ADMIN_RIGHTS) exit('0');
		$success = false;
		foreach (explode(",", $_POST['attrs']) as $attr) {
			if (User::saveAdminPriv($_POST['userid'], $attr, '1')) {
				$success = true;
			}
		}
		print $success ? '1' : '0';
	}

	public static function getUserIdByUsername()
	{
		if (!ADMIN_RIGHTS) exit('0');
		$this_ui_id = User::getUIIDByUsername($_POST['username']);
		print is_numeric($this_ui_id) ? $this_ui_id : 0;
	}

	public function getTemplatePreview() {
		$data = file_get_contents("php://input");
        $params = json_decode($data, $assoc=true);

		$template = $params['text'] ?? '';
		$strategy = $_GET['strategy'] ?? null;
		$response  = [
			'preview' => $template,
		];
		switch ($strategy) {
			case 'preview-expiration-user':
			case 'preview-expiration-sponsor':
				$userInfo = User::getUserInfo(USERID);
				$daysFromNow = User::USER_EXPIRE_FIRST_WARNING_DAYS;
				$userInfo['user_expiration'] = $userInfo['user_expiration'] ?? date("Y-m-d H:i:s", strtotime("+$daysFromNow days"));
				$notifier = AccountExpirationNotifierFacade::make();
				$placeholders = $notifier->makePlaceholders($userInfo);
				$preview = TemplateEngine::render($template, $placeholders);
				$response['preview'] = $preview;
				break;
			
			default:
				# code...
				break;
		}
		return HttpClient::printJSON($response, 200);
	}

	public function checkURL() {
		// Example usage for a test connection button
		$data = file_get_contents("php://input");
		$params = json_decode($data, $assoc = true);
	
		$url = $params['url'] ?? false;
		$serviceName = $params['name'] ?? $url;
		$response = [
			'success' => false,
			'message' => '',
			'errors' => [],
		];
	
		if ($url) {
			$urlChecker = new UrlChecker($url);
			$valid = $urlChecker->checkComprehensive();
	
			if ($valid) {
				$response = [
					'success' => true,
					'message' => "Connection to $serviceName successful",
				];
			} else {
				$errors = $urlChecker->getErrors();
				throw new Exception("Connection to $serviceName failed: " . implode(', ', $errors));
			}
	
			return HttpClient::printJSON($response);
		} else {
			$response = [
				'success' => false,
				'message' => "No URL provided for $serviceName",
			];
			return HttpClient::printJSON($response, 400);
		}
	}
	

}