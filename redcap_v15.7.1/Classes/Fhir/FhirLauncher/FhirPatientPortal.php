<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher;

use DynamicDataPull;
use Project;
use Exception;

class FhirPatientPortal {

	private $fhirResourceErrors;

	/**
	 * add project from Registered Project list
	 * in the Patient Portal (launch from EHR)
	 *
	 * @param int $projectID
	 * @param int $userID
	 * @return bool
	 */
	public function addEhrProject($projectID, $userID) {
		$queryString = "INSERT INTO redcap_ehr_user_projects (project_id, redcap_userid) VALUES (?,?)";
		$result = db_query($queryString, [$projectID, $userID]);
		$affectedRows = db_affected_rows();
		return $affectedRows>=0;
	}

	/**
	 * remove project from Registered Project list
	 * in the Patient Portal (launch from EHR)
	 *
	 * @param int $projectID
	 * @param int $userID
	 * @return bool
	 */
	public function removeEhrProject($projectID, $userID) {
		$queryString = "DELETE FROM redcap_ehr_user_projects where project_id = ? AND redcap_userid = ?";
		$result = db_query($queryString, [$projectID, $userID]);
		$affectedRows = db_affected_rows();
		return $affectedRows>=0;
	}

  /**
	 * Return the form_name and event_id of the DDP MRN field
	 * or the MRN data type field in the current project
	 * (if multiple, then return first)
	 *
	 * @return array
	 */
	private function getFormEventOfMrnField($project_id)
	{
		$Proj = new Project($project_id);
		// If DDP is enabled, then get DDP mapped MRN field
		$DDP = new DynamicDataPull($project_id, $Proj->project['realtime_webservice_type']);
		if (DynamicDataPull::isEnabledInSystemFhir() && DynamicDataPull::isEnabledFhir($project_id)) {
			list ($field, $event_id) = $DDP->getMappedIdRedcapFieldEvent();
		} else {
			$field = $this->getFieldWithMrnValidationType();
			$event_id = $this->getFirstEventForField($project_id, $field);
		}
		return array($field, $event_id);
	}

	/**
	 * create a record with the new patient
	 *
	 * @param string $patientMrn
	 * @return string
	 */
	public function createPatientRecord($project_id, $patientMrn, $record)
	{
		global $lang, $Proj;
		if (empty($patientMrn)) throw new Exception("ERROR: Did not receive the MRN!", 400);
		// Get record and MRN values
		$Proj = new Project($project_id);
		if(!$Proj) throw new Exception("ERROR: please supply a valid project ID!", 400);
		$auto_inc_set = $Proj->project['auto_inc_set'] ?? false;
		$newRecord = $auto_inc_set ? \DataEntry::getAutoId($project_id) : $record;
		if ($newRecord == '') throw new Exception("ERROR: Could not determine the record name for the project!", 400);			
		// Create new record for patient in project
		if ($this->createNewPatientRecord($project_id, $newRecord, $patientMrn)) {				
			$errors = [];
			// If DDP-FHIR is enabled in the project, then go ahead and trigger DDP to start importing data
			if ($Proj->project['realtime_webservice_enabled'] && $Proj->project['realtime_webservice_type'] == 'FHIR') 
			{
				// Fetch DDP data from EHR
				$DDP = new \DynamicDataPull($Proj->project_id, $Proj->project['realtime_webservice_type']);
				list($itemsToAdjudicate, $html) = $DDP->fetchAndOutputData($newRecord, null, array(), $Proj->project['realtime_webservice_offset_days'], 
													$Proj->project['realtime_webservice_offset_plusminus'], false);
				// Any errors?
				if (isset($this->fhirResourceErrors) && !empty($this->fhirResourceErrors)) {
					$errors[] = "{$lang['global_03']}{$lang['colon']}{$lang['ws_246']}";
					$errors[] = array_merge($errors, $this->fhirResourceErrors);
				}
			}
			// Text to display in the dialog
			$message = $lang['data_entry_384'] . implode(';', $errors);
			// Also add hidden field as the new record name value
			$html = \RCView::hidden(array('id'=>'newRecordCreated', 'value'=>$newRecord));
			// Add note about how many DDP items there are to adjudicate (if any)
			if (isset($itemsToAdjudicate) && $itemsToAdjudicate > 0) {
				$html .= 	\RCView::div(array('style'=>'color:#C00000;margin-top: 10px;', 'id'=>'data-adjudication-link'),
							\RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/record_home.php?pid={$Proj->project_id}&id={$newRecord}&openDDP=1", 'style'=>'color:#C00000;'), 
								\RCView::span(array('class'=>'badgerc'), $itemsToAdjudicate) . $lang['data_entry_378']
							)
						);
			}
			/* return [
				'id'=>'newRecordCreated',
				'value'=>$newRecord,
				'itemsToAdjudicate' => $itemsToAdjudicate,
				'href' => APP_PATH_WEBROOT."DataEntry/record_home.php?pid={$Proj->project_id}&id={$newRecord}&openDDP=1",
				'message' => $message,
				'html' => $html,
			]; */
			return $message.$html;
		} else {
			throw new Exception("ERROR: There was an error creating a new record.", 400);
		}
	}

	private function createNewPatientRecord($project_id, $newRecord, $mrn)
	{
		$Proj = new Project($project_id);
		// Find the form and event where the MRN field is located
		list ($mrnField, $mrnEventId) = $this->getFormEventOfMrnField($project_id);
		// Make sure record doesn't already exist
		if (\Records::recordExists($project_id, $newRecord)) throw new Exception("ERROR: Record \"$newRecord\" already exists in the project. Please try another record name.", 400);
		// Add record as 2 data points: 1) record ID field value, and 2) MRN field value
		$sql = "INSERT INTO ".\Records::getDataTable($project_id)." (project_id, event_id, record, field_name, value) VALUES (?,?,?,?,?),(?,?,?,?,?)";
		$values = [
			$project_id, $mrnEventId, db_escape($newRecord), db_escape($Proj->table_pk), db_escape($newRecord),
			$project_id,$mrnEventId,db_escape($newRecord),db_escape($mrnField),db_escape($mrn),
		];
		$q = db_query($sql, $values);
		$affectedRows = db_affected_rows();
		$success = $affectedRows>=0;
		if ($success) {
			// Logging
			defined("USERID") or define("USERID", strtolower($_SESSION['username']));
			\Logging::logEvent(
				$sql,
				$table = "redcap_data",
				$event = "INSERT",
				$record = $newRecord,
				$display = "{$Proj->table_pk} = '$newRecord',\n$mrnField = '$mrn'",
				$descrip = "Create record",
				$change_reason = "",
				$userid_override = "",
				$project_id_override = $project_id,
				$useNOW = true,
				$event_id_override = null,
				$instance = null,
				$bulkProcessing = false
			);
			
		}
		// Return boolean for success
		return $success;
	}

	/**
	 * Return variable name of field having MRN data type
	 *
	 * @return string|false
	 */
	private function getFieldWithMrnValidationType()
	{
		global $Proj;
		$mrnValTypes = $this->getMrnValidationTypes();
		foreach ($Proj->metadata as $field=>$attr) {
			if ($attr['element_validation_type'] == '') continue;
			if (!isset($mrnValTypes[$attr['element_validation_type']])) continue;
			return $field;
		}
		return false;
	}
	
	/**
	 * Return array of field validation types with MRN data type
	 *
	 * @return array
	 */
	private function getMrnValidationTypes()
	{
		$mrnValTypes = array();
		$valTypes = getValTypes();
		foreach ($valTypes as $valType=>$attr) {
			if ($attr['data_type'] != 'mrn') continue;
			$mrnValTypes[$valType] = $attr['validation_label'];
		}
		return $mrnValTypes;
	}

	/**
	 * Return the event_id where a field's form_name is first used in a project
	 *
	 * @param string $field
	 * @return int
	 */
	private function getFirstEventForField($project_id, $field)
	{
		$Proj = new Project($project_id);
		// Get field's form
		$form = $Proj->metadata[$field]['form_name'];
		// Loop through events to find first event to which this form is designated
		foreach ($Proj->eventsForms as $event_id=>$forms) {
			if (!in_array($form, $forms)) continue;
			return $event_id;
		}
		return $Proj->firstEventId;
	}
}