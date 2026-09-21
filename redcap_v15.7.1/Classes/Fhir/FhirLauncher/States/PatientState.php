<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use User;
use Exception;
use DynamicDataPull;
use UnexpectedValueException;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Fhir\Facades\FhirClientFacade;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Patient;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirRenderer;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\FhirClientResponse;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\PatientDataDTO;
use Vanderbilt\REDCap\Classes\Utility\ToastGenerator;

class PatientState extends State
{

	public function run() {
		$state = $_GET['state'] ?? '';
		$session = $this->context->getSession();

		$accessTokenDTO = $session->accessToken;
		$patientID = $accessTokenDTO->patient ?? null;

		$username = $session->user; // REDCap username
		$user_id = User::getUIIDByUsername($username);
		if(!$patientID || !$user_id) throw new Exception("No user ID or patient ID were found.", 1);

		$patientData = $session->patientData;
		if(!$patientData || $patientData->ID !== $patientID) {
			// retrieving data for patient ID $patientID
			$patientData = $this->getPatientData($patientID, $user_id);
			$session->patientData = $patientData;
		}
		
		$patientMrn = $patientData->MRN;
		// getting registered projects with MRN '$patientMrn' for user '$user_id'
		$registeredProjects = $this->getRegisteredProjects($patientMrn, $user_id);
		// getting unregistered projects for user '$user_id'
		$unregisteredProjects = $this->getUnegisteredProjects($user_id);
		// getting MRN validation types
		$mrnValidationTypes = $this->getMrnValidationTypes();
		$fhirUser = $session->fhirUser;
		
		$renderer = FhirRenderer::engine();
		$patientApiURL = APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . '/DynamicDataPull/patient_ehr_api.php';
		$navbar = $renderer->render('partials/navbar.html.twig', compact('session', 'patientData', 'username', 'fhirUser'));
		$warnings = $renderer->render('partials/warnings.html.twig', ['warnings' => $session->getWarnings()]);
		$alerts = $renderer->render('partials/alerts.html.twig', ['toasts' => ToastGenerator::getToasts(),]);
		$footer = $warnings . $alerts;
		$ddpExplanationDialogContent = DynamicDataPull::getExplanationText($isFhir=true);
		$html = $main = $renderer->render(
			'patient.html.twig',
			[
				'navbar' => $navbar,
				'patientData' => $patientData,
				'registeredProjects' => $registeredProjects,
				'unregisteredProjects' => $unregisteredProjects,
				'mrnValidationTypes' => $mrnValidationTypes,
				'patientApiURL' => $patientApiURL,
				'footer' => $footer,
				'ddpExplanationDialogContent' => $ddpExplanationDialogContent,
			]
		);
		print($html);
	}

	// Obtain an array (pid=>array(attributes)) of the current user's registered projects
	private function getRegisteredProjects($mrn, $user_id)
	{
        $projects = [];
        // Loop through every data table
        $dataTables = \Records::getDataTables();
        foreach ($dataTables as $dataTable)
        {
            $sql = "SELECT p.project_id, p.data_table, IF (u.role_id IS NULL, u.record_create, (SELECT ur.record_create FROM redcap_user_roles ur 
					WHERE ur.role_id = u.role_id AND ur.project_id = p.project_id)) AS record_create, 
				p.auto_inc_set AS record_auto_numbering, p.app_title,
				IF (p.realtime_webservice_enabled = '1' AND p.realtime_webservice_type = 'FHIR', 1, 0) AS ddp_enabled, 				
				IF (p.realtime_webservice_enabled = '1' AND p.realtime_webservice_type = 'FHIR', d2.record, null) AS record,
				IF (p.realtime_webservice_enabled = '1' AND p.realtime_webservice_type = 'FHIR', r.item_count, null) AS ddp_items
				FROM redcap_user_rights u, redcap_user_information i, redcap_ehr_user_projects e, redcap_projects p
				LEFT JOIN redcap_ddp_mapping dm ON dm.project_id = p.project_id AND dm.is_record_identifier = 1
				LEFT JOIN $dataTable d2 ON d2.project_id = p.project_id AND d2.field_name = dm.field_name 
					AND d2.value = ?				
				LEFT JOIN redcap_ddp_records r ON r.project_id = p.project_id AND r.record = d2.record
				WHERE e.redcap_userid = i.ui_id AND p.project_id = e.project_id AND p.date_deleted IS NULL AND p.completed_time IS NULL 
					AND u.project_id = p.project_id AND u.username = i.username and i.ui_id = ?
					AND p.status IN (0, 1)
				ORDER BY p.project_id";
            $q = db_query($sql, [db_escape($mrn), db_escape($user_id)]);
            while ($row = db_fetch_assoc($q)) {
				if ($row['data_table'] !== $dataTable) {
					continue; // Skip this result if the table names do not match
				}
                $pid = $row['project_id'];
                unset($row['project_id']);
                $row['app_title'] = strip_tags($row['app_title']);
                // Add to array
                $projects[$pid] = $row;
            }
        }
		return $projects;
	}
	
	// Obtain an array (pid=>title) of the current user's UNregistered projects.
	// Separate viable and non-viable projects in sub-arrays
	private function getUnegisteredProjects($user_id)
	{
		global $lang;
		$sql = "SELECT p.project_id, p.app_title,
				IF ((p.realtime_webservice_enabled = '1' and p.realtime_webservice_type = 'FHIR'), 1, 0) AS viable
				FROM (redcap_user_rights u, redcap_user_information i, redcap_projects p)
				LEFT JOIN redcap_ehr_user_projects e ON e.project_id = p.project_id AND e.redcap_userid = i.ui_id
				WHERE p.date_deleted IS NULL AND u.project_id = p.project_id AND u.username = i.username 
					AND e.redcap_userid IS NULL and i.ui_id = ? AND p.status in (0, 1) 
				ORDER BY IF ((p.realtime_webservice_enabled = '1' AND p.realtime_webservice_type = 'FHIR'), 1, 0) DESC, p.project_id";
		$q = db_query($sql, [db_escape($user_id)]);
		$projects = array();
		while ($row = db_fetch_assoc($q)) {
			$row['app_title'] = strip_tags($row['app_title']);
			$viableText = $row['viable'] ? $lang['data_entry_395'] : $lang['data_entry_396'];
			$projects[$viableText][$row['project_id']] = strip_tags($row['app_title']);
		}
		return $projects;
	}

	/**
	 * Return array of field validation types with MRN data type
	 *
	 * @return array
	 */
	private function getMrnValidationTypes()
	{
		$mrnValTypes = [];
		$valTypes = getValTypes();
		foreach ($valTypes as $valType=>$attr) {
			if ($attr['data_type'] != 'mrn') continue;
			$mrnValTypes[$valType] = $attr['validation_label'];
		}
		return $mrnValTypes;
	}

	/**
	 * get patient resource
	 *
	 * @param string $patient_id
	 * @param string $username
	 * @throws Exception
	 * @return PatientDataDTO [FirstName,LastName,BirthDate,ID,MRN,identifiers]
	 */
	private function getPatientData($patient_id, $user_id)
	{
		if( !$patient_id ) return [];

		$fhirSystem = $this->context->getFhirSystem();
		$fhirClient = FhirClientFacade::getInstance($fhirSystem, $project_id=null, $user_id);
		$tokenManager = $fhirClient->getFhirTokenManager();

		$request = $fhirClient->makeRequest(FhirCategory::DEMOGRAPHICS, $patient_id, AbstractEndpoint::INTERACTION_READ);
		$response = new FhirClientResponse([
			'patient_id' => $patient_id,
			'project_id' => $project_id,
			'user_id' => $user_id,
		]);
		$response = $fhirClient->sendRequest($request, $response);
		/** @var Patient $patient */
		$patient = $response->getResource() ?? null;
		if(!$patient) throw new Exception("Error: could not retrieve patient data", 400);
		if(!$patient instanceof Patient) {
			$errorMessage = sprintf(
				'Error: expected instance of Patient, got %s',
				is_object($patient) ? get_class($patient) : gettype($patient)
			);
			throw new UnexpectedValueException($errorMessage, 400);
		}
		
		
		$patientIdentifierString = $fhirSystem->getPatientIdentifierString();
		$institutionMrn = $patient->getIdentifier($patientIdentifierString);
		$identifiers = $patient->getIdentifiers();
		if(!empty($institutionMrn)) {
			$tokenManager->removeOrphanedMrns($institutionMrn, $patient_id);
			$tokenManager->storePatientMrn($patient_id, $institutionMrn);
		}
		$patientData = new PatientDataDTO([
			'FirstName' => $patient->getNameGiven(),
			'LastName' => $patient->getNameFamily(),
			'BirthDate' => $patient->getBirthDate(),
			'ID' => $patient->getFhirID(),
			'MRN' => $institutionMrn,
			'identifiers' => $identifiers,
		]);
		return $patientData;
	}

}