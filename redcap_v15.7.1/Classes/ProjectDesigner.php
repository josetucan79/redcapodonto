<?php
namespace Vanderbilt\REDCap\Classes;

use Form;
use Design;
use Logging;
use Project;
use DataEntry;
use Exception;
use Randomization;
use Records;
use MultiLanguageManagement\MultiLanguage;
use Vanderbilt\REDCap\Classes\Fhir\Utility\ArrayUtils;
use Vanderbilt\REDCap\Classes\Traits\SubjectTrait;

class ProjectDesigner
{
	use SubjectTrait;

	/** tags for notifications */
	const NOTIFICATION_FIELD_INSERTED_BELOW_SECTION_HEADER = 'ProjectDesigner:NOTIFICATION_FIELD_INSERTED_BELOW_SECTION_HEADER';
	const NOTIFICATION_UNDO_PREVIOUS_FIELD_REORDER = 'ProjectDesigner:NOTIFICATION_UNDO_PREVIOUS_FIELD_REORDER';
	const NOTIFICATION_ERROR_SECTION_HEADER_NOT_ATTACHED_TO_FIELD = 'ProjectDesigner:NOTIFICATION_ERROR_SECTION_HEADER_NOT_ATTACHED_TO_FIELD';
	const NOTIFICATION_FIELD_CHANGED_INTO_SECTION_HEADER = 'ProjectDesigner:NOTIFICATION_FIELD_CHANGED_INTO_SECTION_HEADER';
	const NOTIFICATION_SECTION_HEADER_ADDED = 'ProjectDesigner:NOTIFICATION_SECTION_HEADER_ADDED';
	const NOTIFICATION_FORM_REORDER_ERROR = 'ProjectDesigner:NOTIFICATION_FORM_REORDER_ERROR';
	const NOTIFICATION_FORM_REORDER_SUCCESSFUL = 'ProjectDesigner:NOTIFICATION_FORM_REORDER_SUCCESSFUL';

	/**
	 * @var Project
	 */
	private $project;

	/**
	 * If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
	 *
	 * @var string
	 */
	private $metadata_table;
	public $form;

	const PROJECTS_TABLE 		= 'redcap_projects';
	const METADATA_TABLE 		= 'redcap_metadata';
	const METADATA_TEMP_TABLE 	= 'redcap_metadata_temp';
	const REPEAT_EVENTS_TABLE 	= 'redcap_events_repeat';

	/**
	 *
	 * @param Project $project
	 */
	public function __construct(Project $project)
	{
		$this->project = $project;
		// set the PROJECT_ID constant because is used by the Design class
		defined('PROJECT_ID') ?: define('PROJECT_ID', $this->project->project_id);
	}

	/**
	 * getter for the project ID
	 *
	 * @return integer
	 */
	public function getProjectId() { return $this->project->project_id; }

	/**
	 * get the status of the project being designed
	 *
	 * @return int
	 */
	private function getProjectStatus()
	{
		return intval(@$this->project->project['status'] ?: 0);
	}

	private function getMetadataTable()
	{
		if(!$this->metadata_table) {
			$status = $this->getProjectStatus();
			// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
			$this->metadata_table = ($status > 0) ? self::METADATA_TEMP_TABLE : self::METADATA_TABLE;
		}
		return $this->metadata_table;
	}

	/**
	 * check if a form exists
	 *
	 * @param string $form_name
	 * @return boolean
	 */
	public function formExistsALT($form_name)
	{
		$project = $this->project;
		$status = $this->getProjectStatus();
		$formExists = ($status > 0) ? isset($project->forms_temp[$form_name]) : isset($project->forms[$form_name]);
		return $formExists();
	}

	/**
	 * check if a form exists
	 *
	 * @param string $form_name
	 * @return boolean
	 */
	public function formExists($form_name)
	{
		$project_id = $this->getProjectId();
		$metadata_table = $this->getMetadataTable();
		$query_string = sprintf(
			"SELECT COUNT(1) FROM `%s` WHERE project_id = %u AND form_name = '%s' LIMIT 1",
			$metadata_table, intval($project_id), db_escape($form_name)
		);
		$result = db_query($query_string);
		return db_result($result, 0);
	}

	/**
	 * create a new form
	 *
	 * @param string $form_name
	 * @param string $afterForm
	 * @param string $label label (menu description) for the form
	 * @return void
	 */
	public function createForm($form_name, $afterForm=null, $label=null, $addLogging=true) {
		// create the label. use 'label' if provided or fallback to the form name
		$makeLabel = function($form_name, $label=null) {
			$raw_label = $label ?? $form_name;
			return trim(html_entity_decode($raw_label, ENT_QUOTES));
		};
		$project = $this->project;
		$project_id = $project->project_id;
		$status = $this->getProjectStatus();

		$label = $form_name = $makeLabel($form_name, $label);
		// Remove illegal characters first
		$form_name = preg_replace("/[^a-z_0-9]/", "", str_replace(" ", "_", strtolower($form_name)));
		// Remove any double underscores, beginning numerals, and beginning/ending underscores
		while (strpos($form_name, "__") !== false) 	$form_name = str_replace("__", "_", $form_name);
		while (substr($form_name, 0, 1) == "_") 		$form_name = substr($form_name, 1);
		while (substr($form_name, -1) == "_") 			$form_name = substr($form_name, 0, -1);
		while (is_numeric(substr($form_name, 0, 1))) 	$form_name = substr($form_name, 1);
		while (substr($form_name, 0, 1) == "_") 		$form_name = substr($form_name, 1);
		// Cannot begin with numeral and cannot be blank
		if (is_numeric(substr($form_name, 0, 1)) || $form_name == "") {
			$form_name = substr(preg_replace("/[0-9]/", "", md5($form_name)), 0, 4) . $form_name;
		}
		// Make sure it's less than 50 characters long
		$form_name = substr($form_name, 0, 50);
		// Make sure this form value doesn't already exist
		$formExists = $this->formExists($form_name);
		while ($formExists) {
			// Make sure it's less than 50 characters long
			$form_name = substr($form_name, 0, 44);
			// Append random value to form_name to prevent duplication
			$form_name .= "_" . substr(sha1(rand()), 0, 6);
			// Try again
			$formExists = $this->formExists($form_name);
		}

		// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
		$metadata_table = $this->getMetadataTable();

		// Get position of previous form's Form Status field
		$sql = "SELECT MAX(field_order) FROM $metadata_table WHERE project_id = $project_id";
		if($afterForm) $sql .= " AND form_name = '".db_escape($afterForm)."'";
		$q = db_query($sql);
		if (!db_num_rows($q)) return false;
		// Add a 0.1 to the previous form status field's field order to set it right after it (Project class will fix ordering automatically)
		$new_field_order = db_result($q, 0) + 0.1;

		// Add the Form Status field
		$sql = "INSERT INTO $metadata_table (project_id, field_name, form_name, form_menu_description, field_order, element_type,
				element_label, element_enum, element_preceding_header) VALUES ($project_id, '{$form_name}_complete',
				'{$form_name}', '".db_escape($label)."', '$new_field_order', 'select', 'Complete?',
				'0, Incomplete \\\\n 1, Unverified \\\\n 2, Complete', 'Form Status')";
		$q = db_query($sql);
		// Logging
		if (!$q) return false;
		if ($addLogging) Logging::logEvent($sql,$metadata_table,"MANAGE",$form_name,"form_name = '{$form_name}'","Create data collection instrument");

		// Only if in Development...
		if ($status == 0) {
			// Grant all users and roles full access rights (default) to the new form
			$sql = "UPDATE redcap_user_rights SET 
                    data_entry = CONCAT(data_entry,'[{$form_name},1]'),
                    data_export_instruments = CONCAT(data_export_instruments,'[{$form_name},1]')
                    WHERE project_id = $project_id";
			db_query($sql);
			$sql = "UPDATE redcap_user_roles SET 
                    data_entry = CONCAT(data_entry,'[{$form_name},1]'),
                    data_export_instruments = CONCAT(data_export_instruments,'[{$form_name},1]')
                    WHERE project_id = $project_id";
            db_query($sql);
			// Add new forms to events_forms table ONLY if not longitudinal (if longitudinal, user will designate form for events later)
			$longitudinal = $project->longitudinal ?: false;
			if (!$longitudinal) {
				$sql = "INSERT INTO redcap_events_forms (event_id, form_name) SELECT e.event_id, '{$form_name}' FROM redcap_events_metadata e,
						redcap_events_arms a where a.arm_id = e.arm_id and a.project_id = $project_id limit 1";
				db_query($sql);
			}
		}
		$this->form = $form_name;
		return true;
	}

	/**
	 * delete a form
	 *
	 * @param string $form_name
	 * @return boolean|null true if deleted, false if error, null if cannot delete (does not exist)
	 */
	public function deleteForm($form_name)
	{
		$project = $this->project;
		$project_id = $project->project_id;
		$status =$this->getProjectStatus();
		$randomization = @$project->project['randomization'] ?: 0;

		$canDelete = (isset($form_name) && (($status == 0 && isset($project->forms[$form_name])) || ($status > 0 && isset($project->forms_temp[$form_name]))));
		if(!$canDelete) return;

		// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
		$metadata_table = $this->getMetadataTable();

		// Get current table_pk (temp if in Draft Mode)
		$current_table_pk = (empty($project->table_pk_temp)) ? $project->table_pk : $project->table_pk_temp;

		// Check if randomization has been enabled and prevent deletion of the rand field and any strata fields
		if ($randomization && Randomization::setupStatus())
		{
			// Get randomization attributes
			$randAttr = Randomization::getFormRandomizationFields($form_name);
			// If the randomization field or strata fields are on this form, then stop here
			if (count($randAttr) > 0) {
				// One or more fields are on this form, so return error code
				return false;
			}
		}

		// Get name of first form to compare in the end to see if it was moved
		// $firstFormBefore = Design::getFirstForm();

		$sql_all = array();

		// Before deleting form, get number of fields on form and field_order of first field (for reordering later)
		$sql = "select count(1) from $metadata_table where project_id = $project_id and form_name = '{$form_name}'";
		$field_count = db_result(db_query($sql), 0);
		$sql = "select field_order from $metadata_table where project_id = $project_id and form_name = '{$form_name}' limit 1";
		$first_field_order = db_result(db_query($sql), 0);

		// If edoc_id exists for any fields on this form, then set all as "deleted" in edocs_metadata table (but ONLY if the form only exists in Draft Mode and not currently in live mode)
		if (isset($project->forms_temp[$form_name]) && !isset($project->forms[$form_name]))
		{
			$sql = "update redcap_edocs_metadata set delete_date = '".NOW."' where project_id = $project_id and delete_date is null and doc_id in
					(select edoc_id from $metadata_table where project_id = $project_id and form_name = '{$form_name}' and edoc_id is not null)";
			if (db_query($sql)) $sql_all[] = $sql;
		}

		// Delete this form's fields from the metadata table (do NOT delete PK field if deleting first form - will change PK's form_name below)
		$sql = "delete from $metadata_table where project_id = $project_id
				and form_name = '{$form_name}' and field_name != '$current_table_pk'";
		if (db_query($sql)) {
			$sql_all[] = $sql;
			// Now adjust all field orders to compensate for missing form
			$sql = "update $metadata_table set field_order = field_order - $field_count where project_id = $project_id
					and field_order > $first_field_order";
			if (db_query($sql)) $sql_all[] = $sql;
		}

		// If deleted first form (with the exception of the PK field), then set PK field's form_name to new first form_name value
		$sql = "select field_name from $metadata_table where project_id = $project_id
				and form_name = '{$form_name}' and field_name = '$current_table_pk' limit 1";
		$q = db_query($sql);
		$firstFormDeleted = (db_num_rows($q) > 0);
		if ($firstFormDeleted)
		{
			$forms = ($status > 0) ? $project->forms_temp : $project->forms;
			array_shift($forms);
			$formsKeys = array_keys($forms);
			$secondForm = array_shift($formsKeys);
			$secondFormMenu = $forms[$secondForm]['menu'];
			$secondFormFirstField = array_shift(array_keys($forms[$secondForm]['fields'] ?? []));
			// Set PK's form_name with the value of the next form (which is the new first form)
			$sql = "update $metadata_table set form_name = '".db_escape($secondForm)."', form_menu_description = '".db_escape($secondFormMenu)."',
					field_order = 0 where project_id = $project_id and field_name = '$current_table_pk'";
			if (db_query($sql)) $sql_all[] = $sql;
			// Fix duplication of form_menu_description on the new first form
			$sql = "update $metadata_table set form_menu_description = null
					where project_id = $project_id and field_name = '$secondFormFirstField'";
			if (db_query($sql)) $sql_all[] = $sql;
			// Now adjust all field orders to compensate for this issue with PK field
			$sql = "update $metadata_table set field_order = field_order + 1 where project_id = $project_id";
			if (db_query($sql)) $sql_all[] = $sql;
		}

		// If in Development, delete all form-level rights associated with the form
		if ($status < 1)
		{
			// Catch all 3 possible instances of form-level rights to delete them from user rights table
			$sql = "update redcap_user_rights set 
                    data_entry = replace(data_entry,'[{$form_name},0]',''),
					data_entry = replace(data_entry,'[{$form_name},1]',''), 
					data_entry = replace(data_entry,'[{$form_name},2]',''),
					data_entry = replace(data_entry,'[{$form_name},3]',''),
                    data_export_instruments = replace(data_export_instruments,'[{$form_name},0]',''),
					data_export_instruments = replace(data_export_instruments,'[{$form_name},1]',''), 
					data_export_instruments = replace(data_export_instruments,'[{$form_name},2]',''),
					data_export_instruments = replace(data_export_instruments,'[{$form_name},3]','')
					where project_id = $project_id";
			if (db_query($sql)) $sql_all[] = $sql;
			$sql = "update redcap_user_roles set 
                    data_entry = replace(data_entry,'[{$form_name},0]',''),
					data_entry = replace(data_entry,'[{$form_name},1]',''), 
					data_entry = replace(data_entry,'[{$form_name},2]',''),
					data_entry = replace(data_entry,'[{$form_name},3]',''),
                    data_export_instruments = replace(data_export_instruments,'[{$form_name},0]',''),
					data_export_instruments = replace(data_export_instruments,'[{$form_name},1]',''), 
					data_export_instruments = replace(data_export_instruments,'[{$form_name},2]',''),
					data_export_instruments = replace(data_export_instruments,'[{$form_name},3]','')
					where project_id = $project_id";
			if (db_query($sql)) $sql_all[] = $sql;
			// Delete form from all tables EXCEPT metadata tables and user_rights table
			Form::deleteFormFromTables($form_name);
		}

		// Logging
		Logging::logEvent(implode(";\n", $sql_all), $metadata_table, "MANAGE", $form_name, "form_name = '{$form_name}'", "Delete data collection instrument");

		// Send successful response (1 = OK)
		return true;

	}

	/**
	 * list of available responses for the "delete field" process
	 */
	const DELETE_FIELD_NO_CHANGE = 0;
	const DELETE_FIELD_SUCCESS = 1;
	const DELETE_FIELD_SUCCESS_STATUS_FIELD = 2; // status field was deleted
	const DELETE_FIELD_RELOAD_TABLE = 3; // javascript should reload table
	const DELETE_FIELD_TABLE_PK = 4; // primary key was deleted
	const DELETE_FIELD_IS_LAST_FIELD_WITH_SH = 5; // is the last field with a section header
	const DELETE_FIELD_ERROR_FIELD_IS_USED = 6; // error: field is used

	public function deleteFields($fieldNames, $form_name, $sectionHeader=false)
	{
		$project = $this->project;
		$randomization = @$project->project['randomization'] ?: 0;

		// Check if randomization has been enabled and prevent deletion of the rand field and any strata fields
		if (!$sectionHeader && $randomization && Randomization::setupStatus())
		{
			foreach($fieldNames as $fieldName){
				// Get randomization attributes
				$randomizationsForField = Randomization::getFieldRandomizationIds($fieldName);
				// If this field is the randomization field or a strata fields, then stop here
				if (count($randomizationsForField) > 0) {
					// Field is used, so return error code
					return [self::DELETE_FIELD_ERROR_FIELD_IS_USED];
				}
			}
		}

		$responses = [];
		foreach($fieldNames as $fieldName){
			$response = $this->deleteField($fieldName, $form_name, $sectionHeader);
			$responses[$response] = true; 
		}

		return array_keys($responses);
	}

	private function deleteField($fieldName, $form_name, $sectionHeader=false)
	{
		$project = $this->project;
		$project_id = $project->project_id;
		$status = $this->getProjectStatus();

		$response = self::DELETE_FIELD_NO_CHANGE;

		if (!isset($fieldName) || (
				($status == 0 && !isset($project->metadata[$fieldName])) ||
				($status > 0 && !isset($project->metadata_temp[$fieldName]))
			)
		) return $response;

		//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
		$metadata_table = $this->getMetadataTable();
	
		// Check if the table_pk is being deleted. If so, give back different response so as to inform the user of change.
		$sql = "SELECT field_name FROM $metadata_table WHERE project_id = $project_id ORDER BY field_order LIMIT 1";
		$deletingTablePk = ($fieldName == db_result(db_query($sql), 0));
	
		// Remove section header only (do not delete the row in metadata table)
		if ($sectionHeader) {
	
			//Set old section header value as NULL
			$sql = "UPDATE $metadata_table SET element_preceding_header = NULL WHERE project_id = $project_id AND field_name = '{$fieldName}'";
			$q = db_query($sql);
			// Logging
			if ($q) {
				Logging::logEvent($sql,$metadata_table,"MANAGE",$fieldName,"field_name = '{$fieldName}'","Delete section header");
				$response = self::DELETE_FIELD_SUCCESS;
			}
	
		// Delete field from metadata table
		} else {	
			// Check if randomization has been enabled and prevent deletion of the rand field and any strata fields
			$randomization = @$project->project['randomization'] ?: 0;
			if ($randomization && Randomization::setupStatus())
			{
				// Get randomization attributes
				$randomizationsForField = Randomization::getFieldRandomizationIds($fieldName);
				// If this field is the randomization field or a strata fields, then stop here
				if (count($randomizationsForField) > 0) {
					// Field is used, so return error code
					return self::DELETE_FIELD_ERROR_FIELD_IS_USED;
				}
			}
			// Get current field_order of this field we're deleting
			$sql = "SELECT field_order, element_preceding_header FROM $metadata_table where project_id = $project_id AND field_name = '{$fieldName}'";
			$row = db_fetch_assoc(db_query($sql));
			$this_field_order = $row['field_order'];
			$this_field_section_header = $row['element_preceding_header'];
	
			if ($this_field_order != "") {
	
				// Check to make sure if this is the last field on form, in case it has section header, which we'd need to remove from page
				$sql = "SELECT field_name, element_preceding_header FROM redcap_metadata WHERE project_id = $project_id
						AND form_name = '{$form_name}' ORDER BY field_order DESC LIMIT 1,1";
				$q = db_query($sql);
				$lastFormField = db_result($q, 0, 'field_name');
				$lastFormFieldSH = db_result($q, 0, 'element_preceding_header');
				$isLastFieldWithSH = ($lastFormField == $fieldName && !empty($lastFormFieldSH)) ? true : false;
	
				//Get the field name of the field immediately after the one we're deleting (for purposes later)
				$sql = "SELECT field_name FROM $metadata_table WHERE project_id = $project_id AND field_order >
						" . pre_query("SELECT field_order FROM $metadata_table WHERE project_id = $project_id AND field_name = '{$fieldName}'") . "
						ORDER BY field_order LIMIT 1";
				$next_field_name = db_result(db_query($sql), 0);
	
				// Determine if field has a Section Header. If so, move it to the field immediately after it.
				if ($this_field_section_header != "")
				{
					// Set new section header value
					if ($next_field_name != $form_name."_complete") {
						// Move SH label to the following field (if following field is NOT the Form Status field)
						$sql = "update $metadata_table set element_preceding_header = '" . db_escape($this_field_section_header) . "'
								where project_id = $project_id and field_name = '$next_field_name'";
						db_query($sql);
						// Give response of 3 so javascript knows to reload table (because DOM values connect the SH to the field deleted)
						$response =self::DELETE_FIELD_RELOAD_TABLE;
					}
				}
	
				// Determine if field is first field on form, meaning that it has the Form Menu Description, which needs to be moved down to next field.
				$sql = "SELECT field_name, form_menu_description FROM $metadata_table where project_id = $project_id AND form_name =
						" . pre_query("SELECT form_name FROM $metadata_table WHERE project_id = $project_id AND field_name = '{$fieldName}' LIMIT 1"). "
						ORDER BY field_order LIMIT 1";
				$q = db_query($sql);
				$first_form_field = db_result($q, 0, "field_name");
				$form_menu_description = db_result($q, 0, "form_menu_description");
				// If we're deleting the first field on this form, then assign Form Menu to field directly below it.
				if ($first_form_field == $fieldName) {
					//Set new section header value
					$sql = "UPDATE $metadata_table SET form_menu_description = '" . db_escape($form_menu_description) . "'
							WHERE project_id = $project_id AND field_name = '$next_field_name'";
					db_query($sql);
				}
	
				## CHECK IF NEED TO DELETE EDOC: If edoc_id exists, then set as "deleted" in edocs_metadata table (development only OR if added then deleted in Draft Mode)
				Design::deleteEdoc($fieldName);
	
				// Now delete the field
				$sql = "DELETE FROM $metadata_table WHERE project_id = $project_id AND field_name = '{$fieldName}'";
				$q = db_query($sql);
	
				// Set successful response (i.e. 1, but if the last field and has a section header, give 3 so we can reload whole table)
				// Give response of 4 if the field deleted was the table_pk
				$response = ($isLastFieldWithSH ? self::DELETE_FIELD_IS_LAST_FIELD_WITH_SH : ($deletingTablePk ? self::DELETE_FIELD_TABLE_PK : ($response == self::DELETE_FIELD_RELOAD_TABLE ? $response : self::DELETE_FIELD_SUCCESS)));
	
				// Logging
				if ($q) Logging::logEvent($sql,$metadata_table,"MANAGE",$fieldName,"field_name = '{$fieldName}'","Delete project field");
	
				// Reset the field_orders of all fields
				$sql = "UPDATE $metadata_table SET field_order = field_order - 1 WHERE project_id = $project_id AND field_order > $this_field_order";
				db_query($sql);
	
				// Form Status field: Now check to make sure other fields exist. If only field left is Form Status, then remove it too.
				$sql = "SELECT field_name, field_order FROM $metadata_table WHERE project_id = $project_id AND form_name = '{$form_name}'";
				$q = db_query($sql);
				if (db_num_rows($q) == 1)
				{
					// Is only field the form status field?
					$this_field_name  = db_result($q, 0, "field_name");
					$this_field_order = db_result($q, 0, "field_order");
					if ($this_field_name == $form_name . "_complete")
					{
						// Delete the form status field
						$sql = "DELETE FROM $metadata_table WHERE project_id = $project_id AND field_name = '$this_field_name'";
						db_query($sql);
						// Reset the field_orders of all fields
						$sql = "UPDATE $metadata_table SET field_order = field_order - 1 WHERE project_id = $project_id AND field_order > $this_field_order";
						db_query($sql);
						// Set successful response and note that form status field was deleted
						$response = self::DELETE_FIELD_SUCCESS_STATUS_FIELD;
					}
				}
	
			}
	
		}
	
		//Give affirmative response back
		return $response;
	}

	/**
	 * create a new section header
	 *
	 * @param string $field_label
	 * @param string $next_field_name name of the field following the section (that will contain the data on the table)
	 * @return string name of the field it's attached to
	 */
	function createSectionHeader($field_label, $next_field_name)
	{
		if(empty($next_field_name)) {
			$this->notify(self::NOTIFICATION_ERROR_SECTION_HEADER_NOT_ATTACHED_TO_FIELD, compact('field_label'));
			throw new Exception("Section header must be attached to a field", 1);
		}
		
		$project_id = $this->getProjectId();
		$metadata_table = $this->getMetadataTable();
		// Update field
		$sql = "UPDATE $metadata_table SET element_preceding_header = " . checkNull($field_label) . " WHERE project_id = $project_id "
			. "AND field_name = '{$next_field_name}'";
		$q = db_query($sql);
		// Logging
		if ($q) Logging::logEvent($sql,$metadata_table,"MANAGE",$next_field_name,"field_name = '$next_field_name'","Edit project field");
		$this->notify(self::NOTIFICATION_SECTION_HEADER_ADDED, compact('field_label', 'next_field_name'));
		// return field name of field its attached to
		return $next_field_name;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $form_name
	 * @param array $fieldParams []
	 * @param string $next_field_name name of the field that follows the one we are inserting
	 * @param boolean $was_section_header
	 * @param string $grid_name
	 * @param string $add_form_name
	 * @param string $add_before_after
	 * @param string $add_form_place
	 * @return void
	 */
	function createField($form_name, $fieldParams=[], $next_field_name='', $was_section_header=false, $grid_name='', $add_form_name=NULL, $add_before_after=NULL, $add_form_place='', $doLogging=true)
	{
		$project = $this->project;
		$project_id = $project->project_id;
		$status = $this->getProjectStatus();

		$field_label 			= $fieldParams['field_label'] ?? null;
		$field_name 			= $fieldParams['field_name'] ?? null;
		$field_phi 				= $fieldParams['field_phi'] ?? null;
		$field_type 			= $fieldParams['field_type'] ?? null;
		$element_enum 			= $fieldParams['element_enum'] ?? null;
		$field_note 			= $fieldParams['field_note'] ?? null;
		$val_type 				= $fieldParams['val_type'] ?? null;
		$val_min 				= $fieldParams['val_min'] ?? null;
		$val_max 				= $fieldParams['val_max'] ?? null;
		$field_req 				= $fieldParams['field_req'] ?? null;
		$edoc_id 				= $fieldParams['edoc_id'] ?? null;
		$edoc_display_img 		= intval($fieldParams['edoc_display_img'] ?? null);
		$custom_alignment 		= $fieldParams['custom_alignment'] ?? null;
		$question_num 			= $fieldParams['question_num'] ?? null;
		$field_annotation 		= $fieldParams['field_annotation'] ?? null;
		$video_url 				= $fieldParams['video_url'] ?? null;
		$video_display_inline 	= intval($fieldParams['video_display_inline'] ?? null);

		$is_section_header = ($field_type == "section_header") ? 1 : 0;
		$is_last = ($next_field_name == "") ? 1 : 0;
	
		// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
		$metadata_table = $this->getMetadataTable();

		// Reformat value if adding field directly above a Section Header (i.e. ends with "-sh")
		if (substr($next_field_name, -3) == "-sh") {
			$next_field_name = substr($next_field_name, 0, -3);
			$possible_sh_attached = false;
		} else {
			// Set flag and check later if field directly below has a Section Header (i.e. are we adding a field "between" a SH and a field?)
			$possible_sh_attached = true;
		}

		## Section Headers ONLY
		if ($is_section_header) {
			return $this->createSectionHeader($field_label, $next_field_name);
		}
		
		## All field types (except section headers)
		// Check new form_name value to see if it already exists. If so, unset the value to mimic field-adding behavior for an existing form.
		if (isset($add_form_name)) {
			$formExists = $this->formExists($form_name);
			if ($formExists) {
				unset($add_form_name);
			}
		}

		// Creating new form or editing existing?
		if (isset($add_form_name) && isset($add_before_after)) {
			// NEW FORM being added
			$form_menu_description = "'" . db_escape(strip_tags(label_decode($add_form_name))) . "'";
			if ($add_before_after) {
				// Place after selected form
				$sql = "SELECT MAX(field_order)+1 from $metadata_table WHERE project_id = $project_id AND form_name = '".db_escape($add_form_place)."'";
			} elseif (!$add_before_after) {
				// Place before selected form
				$sql = "SELECT MIN(field_order) from $metadata_table WHERE project_id = $project_id AND form_name = '".db_escape($add_form_place)."'";
			}
		} else {
			// EXISTING FORM
			$form_menu_description = "NULL";
			// Determine if adding to very bottom of table or not. If so, get position of last field on form + 1
			if ($is_last) {
				$sql = "SELECT MAX(field_order) FROM $metadata_table WHERE project_id = $project_id AND form_name = '{$form_name}'";
			// Obtain the destination field's field_order value (i.e. field_order of field that will be located after this new one)
			} else {
				$sql = "SELECT FIELD_ORDER FROM $metadata_table WHERE project_id = $project_id AND field_name = '{$next_field_name}' LIMIT 1";
			}
		}

		// Get the following question's field order
		$new_field_order = db_result(db_query($sql), 0);
		// Increment added to all fields occurring after this new one. If creating a new form, also add extra increment
		// number for field_order to give extra room for the Form Status field created
		$increase_field_order = isset($add_form_name) ? 2 : 1;

		// Increase field_order of all fields after this new one
		db_query("UPDATE $metadata_table SET field_order = field_order + $increase_field_order WHERE project_id = $project_id AND field_order >= $new_field_order");
		// Set associated values for query
		$element_validation_checktype = "";
		if ($field_type == "text") {
			$element_validation_checktype = "soft_typed";
		// Parse multiple choices
		} elseif ($element_enum != "" && ($field_type == "checkbox" || $field_type == "advcheckbox" || $field_type == "radio" || $field_type == "select")) {
			$element_enum = DataEntry::autoCodeEnum($element_enum);
		// Clean calc field equation (and for "sql" field types also)
		} elseif ($element_enum != "") {
			$element_enum = html_entity_decode(trim($element_enum), ENT_QUOTES);
		}
		// Query to create new field
		$sql = "INSERT INTO $metadata_table (project_id, field_name, field_phi, form_name, form_menu_description, field_order,
				field_units, element_preceding_header, element_type, element_label, element_enum, element_note, element_validation_type,
				element_validation_min, element_validation_max, element_validation_checktype, branching_logic, field_req,
				edoc_id, edoc_display_img, custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc, video_url, video_display_inline)
				VALUES
				($project_id, '".db_escape($field_name)."', " . checkNull($field_phi) . ", "
			. "'{$form_name}', $form_menu_description, '$new_field_order', NULL, NULL, '{$field_type}', "
			. checkNull($field_label) . ", "
			. checkNull($element_enum) . ", "
			. checkNull($field_note) . ", "
			. checkNull($val_type) . ", "
			. checkNull($val_min) . ", "
			. checkNull($val_max) . ", "
			. checkNull($element_validation_checktype) . ", "
			. "NULL, "
			. "'{$field_req}', "
			. checkNull($edoc_id) . ", "
			. $edoc_display_img . ", "
			. checkNull($custom_alignment) . ", "
			. "NULL, "
			. checkNull(isset($question_num) ? $question_num : null) . ", "
			. checkNull($grid_name) . ", "
			. "0, "
			. checkNull(trim($field_annotation)) . ", "
			. checkNull($video_url) . ", "
			. checkNull($video_display_inline)
			. ")";
		$q = db_query($sql);
		// Logging
		if ($q) {
            // If this new field was added as in first position of the form, then set the form label to it and set all other form fields as null for form_menu_description
            if ($form_menu_description == "NULL") {
                // Is field in first position?
                $sql = "select field_name from $metadata_table where form_name = ? and project_id = ? order by field_order";
                $q = db_query($sql, [$form_name, $project_id]);
                $firstFieldForm = db_result($q);
                if ($field_name == $firstFieldForm) {
                    // Get form_menu_description text
                    $sql = "select form_menu_description from $metadata_table where form_name = ? and project_id = ? and form_menu_description is not null order by field_order";
                    $q = db_query($sql, [$form_name, $project_id]);
                    $form_menu_descriptionCurrent = db_result($q);
                    // Set all fields' form_menu_description as null
                    $sql = "update $metadata_table set form_menu_description = null where form_name = ? and project_id = ? and form_menu_description is not null order by field_order";
                    db_query($sql, [$form_name, $project_id]);
                    // Set form_menu_description for the first field
                    $sql = "update $metadata_table set form_menu_description = ? where form_name = ? and project_id = ? order by field_order limit 1";
                    db_query($sql, [$form_menu_descriptionCurrent, $form_name, $project_id]);
                }
            }
            // Logging
            if ($doLogging) Logging::logEvent($sql,$metadata_table,"MANAGE",$field_name,"field_name = '{$field_name}'","Create project field");
		} else {
			// UNDO previous "reorder" query: Decrease field_order of all fields after where this new one should've gone
			db_query("UPDATE $metadata_table SET field_order = field_order - $increase_field_order
						WHERE project_id = $project_id AND field_order >= ".($new_field_order + $increase_field_order));

			$this->notify(self::NOTIFICATION_UNDO_PREVIOUS_FIELD_REORDER);
			return;
		}

		// If creating a new form, also add Form Status field
		if (isset($add_form_name))
		{
			// Add the Form Status field
			$sql = "INSERT INTO $metadata_table (project_id, field_name, form_name, field_order, element_type,
					element_label, element_enum, element_preceding_header) VALUES ($project_id, '{$form_name}_complete',
					'{$form_name}', '".($new_field_order+1)."', 'select', 'Complete?',
					'0, Incomplete \\\\n 1, Unverified \\\\n 2, Complete', 'Form Status')";
			$q = db_query($sql);
			// Logging
			if ($q) Logging::logEvent($sql,$metadata_table,"MANAGE",$form_name,"form_name = '{$form_name}'","Create data collection instrument");

			// Only if in Development...
			if ($status == 0) {
                // Grant all users and roles full access rights (default) to the new form
                $sql = "UPDATE redcap_user_rights SET 
                        data_entry = CONCAT(data_entry,'[{$form_name},1]'),
                        data_export_instruments = CONCAT(data_export_instruments,'[{$form_name},1]')
                        WHERE project_id = $project_id";
                db_query($sql);
                $sql = "UPDATE redcap_user_roles SET 
                        data_entry = CONCAT(data_entry,'[{$form_name},1]'),
                        data_export_instruments = CONCAT(data_export_instruments,'[{$form_name},1]')
                        WHERE project_id = $project_id";
                db_query($sql);
				// Add new forms to events_forms table ONLY if not longitudinal (if longitudinal, user will designate form for events later)
				$longitudinal = $project->longitudinal ?: false;
				if (!$longitudinal) {
					$sql = "INSERT INTO redcap_events_forms (event_id, form_name) select e.event_id, '{$form_name}' FROM redcap_events_metadata e,
							redcap_events_arms a where a.arm_id = e.arm_id AND a.project_id = $project_id LIMIT 1";
					db_query($sql);
				}
			}
			return;
		}

		// NOT adding a new form, so deal with some logic and placement issues
		## SECTION HEADER PLACEMENT
		// Check if we are adding a field "between" a SH and a field? If so, move SH to new field from one directly after it.
		if ($possible_sh_attached && !$is_last)
		{
			$sql = "SELECT element_preceding_header FROM $metadata_table WHERE project_id = $project_id AND form_name = '{$form_name}'
					AND field_order = (SELECT field_order+1 FROM $metadata_table WHERE project_id = $project_id
					AND field_name = '{$field_name}' limit 1) AND element_preceding_header IS NOT NULL LIMIT 1";
			$q = db_query($sql);
			if (db_num_rows($q) > 0) {
				// Yes, we are adding a field "between" a SH and a field. Move the SH to the field we just created.
				$sh_value = db_result($q, 0);
				// If changed a SH to a real field, then don't reattach the SH, but instead set to null
				if ($was_section_header) {
					$sh_value = "";
				}
				$sql = "UPDATE $metadata_table SET element_preceding_header = " . checkNull($sh_value) . " WHERE project_id = $project_id
						AND field_name = '{$field_name}' LIMIT 1";
				$q = db_query($sql);
				// Get name of field directly after the new one we created.
				$sql = "SELECT field_name FROM $metadata_table WHERE project_id = $project_id AND form_name = '{$form_name}'
						AND field_order = ".($new_field_order+1)." LIMIT 1";
				$following_field = db_result(db_query($sql), 0);
				// Set SH value from other field to NULL now that we have copied it to new field
				$sql = "UPDATE $metadata_table SET element_preceding_header = NULL WHERE project_id = $project_id AND field_name = '$following_field' LIMIT 1";
				$q = db_query($sql);
				// Set value for row in table to be deleted in DOM (delete section header on following field, which is now null)
				$delete_row = $following_field . "-sh";
				// Send notification to reload page
				$this->notify(self::NOTIFICATION_FIELD_INSERTED_BELOW_SECTION_HEADER, compact('delete_row', 'form_name'));
			}
		}

		## FORM MENU: Always make sure the form_menu_description value stays only with first field on form
		// Set all field's form_menu_description as NULL
		$sql = "UPDATE $metadata_table SET form_menu_description = NULL WHERE project_id = $project_id AND form_name = '{$form_name}'";
		db_query($sql);
		// Now set form_menu_description for first field
		$projectForms = ($status > 0) ? $project->forms_temp : $project->forms;
		$form_menu = $projectForms[$form_name]['menu'] ?? false;
		if($form_menu) {
			$sql = "UPDATE $metadata_table SET form_menu_description = '".db_escape(label_decode($form_menu))."'
					WHERE project_id = $project_id AND form_name = '{$form_name}' ORDER BY field_order LIMIT 1";
			db_query($sql);
		}
	}

	/**
	 * get the position of a field and the boundaries of the forms where
	 * it is contained
	 *
	 * @param string $fieldName
	 * @return array|false [position, form_name, form_start, form_end]
	 */
	public function getFieldPosition($fieldName)
	{
		$project = $this->project;
		$project_id = $project->project_id;
		$metadata_table = $this->getMetadataTable();

		$query_string = sprintf(
			"SELECT q2.field_order, q2.form_name, MAX(q1.field_order) AS form_end, MIN(q1.field_order) AS form_start FROM %s AS q1
			JOIN (SELECT * FROM %s WHERE project_id=%u AND field_name='%s') AS q2
			ON q1.project_id=q2.project_id AND q1.form_name=q2.form_name",
			$metadata_table, $metadata_table, $project_id, $fieldName
		);

		$result = db_query($query_string);
		if($row = db_fetch_assoc($result)) {

			$metadata = [
				'position' => intval(@$row['field_order']),
				'form_name' => @$row['form_name'],
				'form_start' => intval(@$row['form_start']),
				'form_end' => intval(@$row['form_end']),
			];
			$isEmpty = function($value) { return is_null($value); };
			if(ArrayUtils::any($metadata, $isEmpty)) return false;
			return $metadata;
		}
		return false;
	}

	/**
	 * get the relative position of a field in its
	 * containing form.
	 * the numbering is zero based.
	 *
	 * @param string $fieldName
	 * @return int|false
	 */
	public function getFieldRelativePosition($fieldName) {
		$metadata = $this->getFieldPosition($fieldName);
		if($metadata===false) return false;
		return $metadata['position'] - $metadata['form_start'];
	}

	/**
	 * move a field after another one
	 * and reorder all affected fields.
	 * If no other field is specified then move the
	 * field at the top of the form
	 * 
	 * @todo : move section headers when the field_name or other_field_name end with -sh
	 *
	 * @param string $field_name
	 * @param string $other_field_name
	 * @return int affected fields
	 */
	public function moveFieldAfterField($field_name, $other_field_name=null)
	{
		$fieldPositionMetadata = $this->getFieldPosition($field_name);
		$from = @$fieldPositionMetadata['position'];
		if(is_null($other_field_name)) {
			$to = @$fieldPositionMetadata['form_start'];
		}else {
			$otherFieldPositionMetadata = $this->getFieldPosition($other_field_name);
			$to = @$otherFieldPositionMetadata['position'];
			if(@$fieldPositionMetadata['form_name'] != $otherFieldPositionMetadata['form_name']) throw new \Exception("Cannot move a field outside the form limits", 1);
		}
		return $this->moveField($from, $to);
	}

	/**
	 * change the relative position of a field
	 * in its container form
	 *
	 * @param string $fieldName
	 * @param int $relativePosition zero based position in the form
	 * @return int|null new absolute position or null if no change is needed
	 */
	public function setFieldRelativePosition($fieldName, $relativePosition) {
		$positionMetadata = $this->getFieldPosition($fieldName);
        $position = @$positionMetadata['position'];
        $form_start = @$positionMetadata['form_start'];
        $form_end = @$positionMetadata['form_end'];
        $newPosition = $form_start + $relativePosition;
        if(!is_numeric($newPosition) || $newPosition>$form_end) throw new \Exception("the target position is not valid", 1);
        $result = $this->moveField($position, $newPosition);
		return $newPosition;
	}

	/**
	 * move a section header from a field to another
	 *
	 * @param string $from name of source field
	 * @param string $to name of target field
	 * @return int
	 */
	public function moveSectionHeader($from, $to)
	{
		$project = $this->project;
		$project_id = $project->project_id;
		$metadata_table = $this->getMetadataTable();

		$query_string = sprintf(
			"UPDATE %s AS q1
			INNER JOIN %s AS q2
			ON q1.project_id=q2.project_id
			SET
				q1.element_preceding_header = NULL,
				q2.element_preceding_header = q1.element_preceding_header
			WHERE q1.project_id=%u
			AND q1.field_name='%s'
			AND q2.field_name= '%s'", $metadata_table, $metadata_table, $project_id, db_escape($from), db_escape($to)
		);
		$result = db_query($query_string);
		if(!$result) throw new Exception("There was an error moving the section header", 1);
		Logging::logEvent("",$metadata_table,"MANAGE",$project_id,"from = '{$from}', to = '{$to}'","move section header");
		return db_affected_rows();
	}

	/**
	 * move a field to a different position inside the limits of the form
	 *
	 * @param int $from
	 * @param integer $to
	 * @return int number of rows affected from the reordering
	 */
	public function moveField(int $from, int $to)
	{
		if($from==$to) return 0; // no change is $new_field_order
		$project = $this->project;
		$project_id = $project->project_id;
		$metadata_table = $this->getMetadataTable();

		$query_string = sprintf(
			'UPDATE %s AS metadata
			-- set min, max and delta for the movement
			JOIN (
			  SELECT pos_from, pos_to, 
			  CASE WHEN pos_to > pos_from THEN pos_from ELSE pos_to END AS pos_min,
			  CASE WHEN pos_to > pos_from THEN pos_to ELSE pos_from END AS pos_max,
			  CASE WHEN pos_to > pos_from THEN -1 ELSE 1 END AS pos_delta
			  FROM (
				SELECT 
				%u AS pos_from, 
				%u AS pos_to
			  ) AS q1
			) AS q2 ON metadata.field_order BETWEEN q2.pos_min AND q2.pos_max
			SET metadata.field_order = CASE
				WHEN metadata.field_order = q2.pos_from
					THEN q2.pos_to
					ELSE metadata.field_order + q2.pos_delta
				END
			WHERE project_id=%u', $metadata_table, $from, $to, $project_id
		);
		$result = db_query($query_string);
		if(!$result) throw new Exception("There was an error updating the field position", 1);
		Logging::logEvent("",$metadata_table,"MANAGE",$project_id,"from = '{$from}', to = '{$to}'","move project field");
		return db_affected_rows();
	}

	public function updateSectionHeader($form_name, $field_id, $field_name, $field_label)
	{
		$project = $this->project;
		$project_id = $project->project_id;
		$metadata_table = $this->getMetadataTable();

		// If user is changing a field into a section header, delete actual field and move section header down one field in metadata table.
		$moveSectionHeader = function() use($form_name, $field_id, $field_name, $field_label, $metadata_table, $project, $project_id) {
			// See if a section header already exists for the field. If so, append new value onto it and move down one field
			$sql = "SELECT field_order, element_preceding_header FROM $metadata_table where project_id = $project_id
					AND field_name = '{$field_name}' LIMIT 1";
			$q = db_query($sql);
			$sh_existing1 = db_result($q, 0, "element_preceding_header");
			$forder_existing1 = db_result($q, 0, "field_order");
			// See if section header exists for the succeeding field
			$sql = "SELECT field_name, element_preceding_header FROM $metadata_table WHERE project_id = $project_id
					AND field_order > $forder_existing1 ORDER BY field_order LIMIT 1";
			$q = db_query($sql);
			$sh_existing2 = db_result($q, 0, "element_preceding_header");
			$fieldname_existing2 = db_result($q, 0, "field_name");
			// Append other section header values onto submitted one
			if ($sh_existing1 != "") $field_label  = $sh_existing1 . "<br><br>" . $field_label;
			if ($sh_existing2 != "") $field_label .= "<br><br>" . $sh_existing2;
			// Move section header to succeeding field
			$sql = "UPDATE $metadata_table SET element_preceding_header = " . checkNull($field_label) . "
					WHERE project_id = $project_id AND field_name = '$fieldname_existing2'";
			db_query($sql);
			// Delete current field and reduce field_order of following fields
			db_query("DELETE FROM $metadata_table WHERE project_id = $project_id AND field_name = '{$field_name}'");
			db_query("UPDATE $metadata_table SET field_order = field_order - 1 WHERE project_id = $project_id AND field_order > $forder_existing1");
			## FORM MENU: Always make sure the form_menu_description value stays only with first field on form
			// Set all field's form_menu_description as NULL
			$sql = "UPDATE $metadata_table SET form_menu_description = NULL WHERE project_id = $project_id AND form_name = '{$form_name}'";
			db_query($sql);
			// Now set form_menu_description for first field
			$sql = "UPDATE $metadata_table SET form_menu_description = '".db_escape(label_decode($project->forms[$form_name]['menu']))."'
					WHERE project_id = $project_id AND form_name = '{$form_name}' ORDER BY field_order LIMIT 1";
			db_query($sql);
			// Run javascript to reload table
			$this->notify(self::NOTIFICATION_FIELD_CHANGED_INTO_SECTION_HEADER, compact('form_name', 'field_name'));
		};
		
		if (!isset($field_name) || empty($field_name))
		{
			// Modify section header normally (not changing a regular field into a section header)
			$sql = "UPDATE $metadata_table SET "
			. "element_preceding_header = " . checkNull($field_label) . " "
			. "WHERE project_id = $project_id AND field_name = '{$field_id}'";
			$q = db_query($sql);

			// Logging
			if ($q) Logging::logEvent($sql,$metadata_table,"MANAGE",$field_name,"field_name = '{$field_name}'","Edit project field");
			return;
		}else {
			$moveSectionHeader();
		}
	}

	/**
	 * Undocumented function
	 *
	 * @param string $form_name
	 * @param string $field_id the name of the field (in case of section header it is the field it is attached to)
	 * @param array $fieldParams
	 * @param string $grid_name
	 * @return void
	 */
	public function updateField($form_name, $field_id='', $fieldParams=[], $grid_name='', $enable_ontology_auto_suggest_field=false)
	{
		$project_id = $this->getProjectId();
		$metadata_table = $this->getMetadataTable();

		$field_label 			= $fieldParams['field_label'] ?? null;
		$field_name 			= $fieldParams['field_name'] ?? null;
		$field_phi 				= $fieldParams['field_phi'] ?? null;
		$field_type 			= $fieldParams['field_type'] ?? null;
		$element_enum 			= $fieldParams['element_enum'] ?? null;
		$field_note 			= $fieldParams['field_note'] ?? null;
		$val_type 				= $fieldParams['val_type'] ?? null;
		$val_min 				= $fieldParams['val_min'] ?? null;
		$val_max 				= $fieldParams['val_max'] ?? null;
		$field_req 				= $fieldParams['field_req'] ?? null;
		$edoc_id 				= $fieldParams['edoc_id'] ?? null;
		$edoc_display_img 		= intval($fieldParams['edoc_display_img'] ?? null);
		$custom_alignment 		= $fieldParams['custom_alignment'] ?? null;
		$question_num 			= $fieldParams['question_num'] ?? null;
		$field_annotation 		= $fieldParams['field_annotation'] ?? null;
		$video_url 				= $fieldParams['video_url'] ?? null;
		$video_display_inline 	= intval($fieldParams['video_display_inline'] ?? null);

		$is_section_header = ($field_type == 'section_header') ? 1 : 0;

		// Set associated values for query
		$element_validation_checktype = ($field_type == "text") ? "soft_typed" : "";

		$cleanupEnum = function($element_enum, $field_type, $enable_ontology_auto_suggest_field) {
			// Parse multiple choices
			if ($element_enum != "" && ($field_type == "checkbox" || $field_type == "advcheckbox" || $field_type == "radio" || $field_type == "select")) {
				$element_enum = DataEntry::autoCodeEnum($element_enum);
			// Clean calc field equation
			} elseif ($field_type == "calc") {
				$element_enum = html_entity_decode(trim($element_enum), ENT_QUOTES);
			// Ensure that most fields do not have a "select choice" value
			} elseif (!$enable_ontology_auto_suggest_field && in_array($field_type, ["text", "textarea", "notes", "file", "yesno", "truefalse"])) {
				$element_enum = "";
			}
			return $element_enum;
		};

		$element_enum = $cleanupEnum($element_enum, $field_type, $enable_ontology_auto_suggest_field);

		// Edit field's section header
		if ($is_section_header)
		{
			return $this->updateSectionHeader($form_name, $field_id, $field_name, $field_label);
		}
		// Edit field itself
		// CHECK IF NEED TO DELETE EDOC: If edoc_id is blank then set as "deleted" in edocs_metadata table (development only OR if added then deleted in Draft Mode)
		// Get current edoc_id
		$q = db_query("SELECT edoc_id FROM $metadata_table WHERE project_id = $project_id AND field_name = '{$field_id}' LIMIT 1");
		$current_edoc_id = db_result($q, 0);
		if (empty($edoc_id) || $current_edoc_id != $edoc_id)
		{
			Design::deleteEdoc($field_id);
		}

		// Update field
		$sql = "update $metadata_table set "
			. "field_name = '{$field_name}', "
			. "element_label = " . checkNull($field_label) . ", "
			. "field_req = '{$field_req}', "
			. "field_phi = " . checkNull($field_phi) . ", "
			. "element_note = " . checkNull($field_note) . ", "
			. "element_type = '{$field_type}', "
			. "element_validation_type = " . checkNull($val_type) . ", "
			. "element_validation_checktype = " . checkNull($element_validation_checktype) . ", "
			. "element_enum = " . checkNull($element_enum) . ", "
			. "element_validation_min = " . checkNull($val_min) . ", "
			. "element_validation_max = " . checkNull($val_max) . ", "
			. "edoc_id = " . checkNull($edoc_id) . ", "
			. "edoc_display_img = {$edoc_display_img}, "
			. "custom_alignment = " . checkNull($custom_alignment) . ", "
			. "question_num = " . checkNull(isset($question_num) ? $question_num : null) . ", "
			. "grid_name = " . checkNull($grid_name) . ", "
			. "video_url = " . checkNull($video_url) . ", "
			. "video_display_inline = " . checkNull($video_display_inline) . ", "
			. "misc = " . checkNull(trim($field_annotation)) . " "
			. "where project_id = $project_id and field_name = '{$field_id}'";


		$q = db_query($sql);

		// Multi-Language Management:
		// In case the field name changes, update the translation database to preserve any 
		// translations that may have been entered. This needs to be done after the
		// metadata query has executed, and only in DEVELOMENT mode
		$status = $this->getProjectStatus();
		if ($status == 0 && $field_name != $field_id) {
			MultiLanguage::updateFieldNameDuringDevelopment($project_id, $field_id, $field_name);
		}

		// Logging
		if ($q) Logging::logEvent($sql,$metadata_table,"MANAGE",$field_name,"field_name = '{$field_name}'","Edit project field");

	}

	/**
	 * make a form repeatable
	 *
	 * @param string $form_name
	 * @param int $event_id
	 * @param string $label
	 * @return bool
	 * @throws Exception if the query fails
	 */
	public function makeFormRepeatable($form_name, $event_id, $label='')
	{
		$query_string = sprintf(
			"INSERT INTO `%s` (`event_id`, `form_name`, `custom_repeat_form_label`)
            VALUES (%u, '%s', '%s')", self::REPEAT_EVENTS_TABLE, intval($event_id), db_escape($form_name), db_escape($label)
        );
		$query_string .= sprintf(" ON DUPLICATE KEY UPDATE `custom_repeat_form_label`='%s'", db_escape($label));
		$result = db_query($query_string);
		if(!$result) throw new Exception("Error making form '{$form_name}' repeatable using event ID {$event_id}", 1);
		$project_id = $this->getProjectId();
		$params = compact('form_name', 'event_id', 'label');
		Logging::logEvent($query_string, self::REPEAT_EVENTS_TABLE, "MANAGE", $project_id, json_encode($params, JSON_PRETTY_PRINT), "Set up repeating instrument");
		return true;
	}

	/**
	 * make a form NOT repeatable
	 *
	 * @param string $form_name
	 * @param int $event_id
	 * @return bool
	 * @throws Exception if the query fails
	 */
	public function makeFormNotRepeatable($form_name, $event_id)
	{
		$query_string = sprintf(
            "DELETE FROM `%s` WHERE `event_id`=%u AND `form_name`='%s'",
            self::REPEAT_EVENTS_TABLE, intval($event_id), db_escape($form_name)
        );
		$result = db_query($query_string);
		if(!$result) throw new Exception("Error making form '{$form_name}' not repeatable using event ID {$event_id}", 1);
		$project_id = $this->getProjectId();
		$params = compact('form_name', 'event_id');
		Logging::logEvent($query_string, self::REPEAT_EVENTS_TABLE, "MANAGE", $project_id, json_encode($params, JSON_PRETTY_PRINT), "Set up not repeating instrument");
		return true;
	}

	/**
	 * assign a field to an existing form in the project
	 *
	 * @param string $field_name
	 * @param string $form_name
	 * @return bool
	 * @throws Exception if the form does not exists or there is an error running the query
	 */
	public function assignFieldToForm($field_name, $form_name)
	{
		$project_id = $this->getProjectId();
		$metadata_table = $this->getMetadataTable();
		if(!$this->formExists($form_name)) throw new Exception("Error assigning the field '{$field_name}' to the form '{$form_name}': the form does not exist", 1);
		$query_string = sprintf(
			"UPDATE `%s` SET form_name = '%s' WHERE project_id = %u AND field_name = '%s'",
			$metadata_table, db_escape($form_name), intval($project_id), db_escape($field_name)
		);
		$result = db_query($query_string);
		if(!$result) throw new Exception("Error assigning the field '{$field_name}' to the form '{$form_name}'", 1);
		return true;
	}

	/**
	 * get metadata about each form:
	 * - position
	 * - total fields
	 * - boundaries (min and max field based on field_order)
	 * - form name
	 *
	 * @return array
	 */
	public function getFormsOrderAndBoundaries() {
		$project_id = $this->getProjectId();
		$metadata_table = $this->getMetadataTable();
		db_query('SET @i:=0'); // init the variable to 0
		$query_string = sprintf(
			"SELECT (@i:=@i+1) AS position , form_name, form_start, form_end, total FROM (
				SELECT form_name, MIN(field_order) AS form_start, MAX(field_order) AS form_end,
				(MAX(field_order) - MIN(field_order) + 1) AS total
				FROM %s
				WHERE project_id=%u
				GROUP BY form_name
				ORDER BY form_start
			) as q1", $metadata_table, $project_id
		);
		$result = db_query($query_string);
		$rows = [];
		while($row = db_fetch_assoc($result)) 
		{
			$rows[] = [
				'position' 		=> intval(@$row['position']),
				'form_name' 	=> @$row['form_name'],
				'form_start'	=> intval(@$row['form_start']),
				'form_end' 		=> intval(@$row['form_end']),
				'total_fields' 		=> intval(@$row['total']),
			];
		}
		return $rows;
	}

	public function getLastForm()
	{
		$project_id = $this->getProjectId();
		$metadata_table = $this->getMetadataTable();
		$query_string = sprintf("SELECT form_name FROM %s WHERE project_id=%u ORDER BY field_order DESC LIMIT 1", $metadata_table, $project_id);
	}

	/**
	 * move a form with all its fields
	 * to another position
	 */
	public function updateFormOrder($formName, $position)
	{
		$project_id = $this->getProjectId();
		$metadata_table = $this->getMetadataTable();
		$formsBoundaries = $this->getFormsOrderAndBoundaries();

		$sourceFormBoundaries = ArrayUtils::find($formsBoundaries, function($item) use($formName) {
			return @$item['form_name'] === $formName;
		});
		$targetFormBoundaries = ArrayUtils::find($formsBoundaries, function($item) use($position) {
			return intval(@$item['position']) === $position;
		});
		if(!$sourceFormBoundaries || !$targetFormBoundaries) return;

		$sourcePosition = @$sourceFormBoundaries['position'];
		$sourceStart = @$sourceFormBoundaries['form_start'];
		$sourceEnd = @$sourceFormBoundaries['form_end'];
		$sourceTotal = @$sourceFormBoundaries['total_fields'];
		
		$targetPosition = $position;
		$targetStart = @$targetFormBoundaries['form_start'];
		$targetEnd = @$targetFormBoundaries['form_end'];

		if($sourcePosition==$targetPosition) return; // nothing to move

		// moving forward:
		// - add to all fields in the source form the total number of fields in all forms between its current and final position 
		// - subtract the total number of fields in the source form to all forms between its current and final position
		// - change only the fields included among the first one in the source form and the last one in the target form
		$moveForward = function() use($project_id, $metadata_table, $sourceStart, $sourceEnd, $sourceTotal, $targetStart, $targetEnd) {
			$addToSource = $targetEnd-$sourceEnd;
			$addToTarget = -($sourceTotal);
			$query_string = sprintf(
				"UPDATE $metadata_table SET field_order = 
				CASE
					WHEN field_order>=$sourceStart AND field_order<=$sourceEnd THEN field_order+$addToSource
					WHEN field_order>$sourceEnd AND field_order<=$targetEnd THEN field_order+$addToTarget
				END
				WHERE project_id=$project_id AND field_order>=$sourceStart AND field_order<=$targetEnd"
			);
			return $query_string;
		};

		// moving backward:
		// - subtract from all fields in the source form the total number of fields in all forms between its current and final position
		// - add the total number of fields in the source form to all forms between its current and final position
		// - change only the fields included among the first one in the target form and the last one in the source form
		$moveBackward = function() use($project_id, $metadata_table, $sourceStart, $sourceEnd, $sourceTotal, $targetStart, $targetEnd) {
			$addToSource = -($sourceStart-$targetStart);
			$addToTarget = $sourceTotal;
			$query_string = sprintf(
				"UPDATE $metadata_table SET field_order = 
				CASE
					WHEN field_order>=$sourceStart AND field_order<=$sourceEnd THEN field_order+$addToSource
					WHEN field_order>=$targetStart AND field_order<$sourceStart THEN field_order+$addToTarget
				END
				WHERE project_id=$project_id AND field_order>=$targetStart AND field_order<=$sourceEnd"
			);
			return $query_string;
		};

		$delta = $sourcePosition<$targetPosition ? 1 : -1; // positive delta if moving forward, negative otherwise

		$query_string = ($delta>0) ? $moveForward() : $moveBackward();
		$result = db_query($query_string);
		if($result) {
			Records::resetRecordCountAndListCache($project_id);
			return db_affected_rows();
		}
		throw new Exception("Error moving the form", 1);
	}

	/**
	 * update the order of the forms
	 *
	 * @param array $forms list of all form names
	 * @return bool
	 */
	public function updateFormsOrder($forms=[]) {
		$project_id = $this->getProjectId();
		$metadata_table = $this->getMetadataTable();

		// Get total field count for all metadata
		$total_fields = db_result(db_query("select count(1) from $metadata_table where project_id = $project_id"), 0);

		// Check if the table_pk has changed during the recording. If so, give back different response so as to inform the user of change.
		$sql = "select field_name, form_name, form_menu_description from $metadata_table where project_id = $project_id order by field_order limit 1";
		$q = db_query($sql);
		$old_table_pk = db_result($q, 0, "field_name");
		$old_first_form = db_result($q, 0, "form_name");
		$old_first_form_menu = db_result($q, 0, "form_menu_description");

		// Get name of first form to compare in the end to see if it was moved
		$firstFormBefore = Design::getFirstForm();

		// Set up all actions as a transaction to ensure everything is done here
		db_query("SET AUTOCOMMIT=0");
		db_query("BEGIN");
		$sql_errors = 0;


		// Check field count for submitted forms against total field count for all metadata
		$sql = "select form_name, field_name from $metadata_table where project_id = $project_id
				and form_name in (" . prep_implode($forms) . ") order by field_order";
		$q = db_query($sql);
		// Quit if any forms are not valid or are missing
		if (db_num_rows($q) != $total_fields) exit("0");

		// First create array with all fields
		$fields = array();
		while ($row = db_fetch_assoc($q))
		{
			$fields[$row['form_name']][] = $row['field_name'];
		}

		// Loop through all fields and set the new field_order for each field
		$field_order = 1;
		foreach ($forms as $this_form)
		{
			foreach ($fields[$this_form] as $this_field)
			{
				$sql = "update $metadata_table set field_order = $field_order where project_id = $project_id and field_name = '$this_field'";
				if (!db_query($sql)) $sql_errors++;
				$field_order++;
			}
		}

		// Get the first form NOW (in case was moved to another position)
		$firstFormAfter = Design::getFirstForm();

		// Check if the table_pk has changed during the form move. If so, then move it back to first position.
		$sql = "select field_name, form_menu_description from $metadata_table where project_id = $project_id order by field_order limit 1";
		$q = db_query($sql);
		$new_table_pk = db_result($q, 0, "field_name");
		$new_first_form_menu = db_result($q, 0, "form_menu_description");
		// Compare old first field and new one
		if ($old_table_pk != $new_table_pk)
		{
			// First set to first position and change form name to new form
			$sql = "update $metadata_table set field_order = 1, form_name = '$firstFormAfter', form_menu_description = '".db_escape($new_first_form_menu)."'
					where project_id = $project_id and field_name = '$old_table_pk'";
			if (!db_query($sql)) $sql_errors++;
			// Now move all other fields up one position (on next page, the ProjectAttribute class will fix any messed up ordering)
			$sql = "update $metadata_table set field_order = field_order+1 where project_id = $project_id and field_name != '$old_table_pk'";
			if (!db_query($sql)) $sql_errors++;
			// Now set new table pk form menu label to null because it will be the second field AND set all of old first form as such
			$sql = "update $metadata_table set form_menu_description = null where project_id = $project_id
					and (field_name = '$new_table_pk' or form_name = '$old_first_form')";
			if (!db_query($sql)) $sql_errors++;
			// Now the new second form needs a form menu label (was attached to old pk)
			$sql = "update $metadata_table set form_menu_description = '".db_escape($old_first_form_menu)."'
					where project_id = $project_id and form_name = '$old_first_form' limit 1";
			if (!db_query($sql)) $sql_errors++;
		}

		## CHANGE THIS FOR MULTIPLE SURVEYS!!!! (How to deal with this for multiple survey projects????)
		// If first form was moved and it was a survey, make sure the
		// if ($status < 1 && $firstFormAfter != $firstFormBefore && isset($Proj->forms[$firstFormBefore]['survey_id']) && !isset($Proj->forms[$firstFormAfter]['survey_id']))
		// {
			// Change form_name of survey to the new first form name
			// $sql = "update redcap_surveys set form_name = '$firstFormAfter' where survey_id = ".$Proj->forms[$firstFormBefore]['survey_id'];
			// db_query($sql);
		// }

		// Rollback all changes if sql error occurred
		if ($sql_errors > 0)
		{
			// Errors occurred, so undo any changes made
			db_query("ROLLBACK");
			// Send error response
			$this->notify(self::NOTIFICATION_FORM_REORDER_ERROR);
			return false;
		}
		// Logging
		else
		{
			// Log it
			Logging::logEvent("",$metadata_table,"MANAGE",$project_id,"project_id = $project_id","Reorder data collection instruments");
			// No errors occurred
			db_query("COMMIT");
			// Send successful response (1 = OK, 2 = OK but first form was moved, i.e. PK was changed)
			$this->notify(self::NOTIFICATION_FORM_REORDER_SUCCESSFUL);
			return true;
			/*
			// PK can no longer be changed using this drag-n-drop method, by definition, so no need to worry about it.
			print (Design::getFirstForm() == $firstFormBefore ? "1" : "2");
			*/
		}
		
	}


}