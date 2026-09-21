<?php

use MultiLanguageManagement\MultiLanguage;
use Vanderbilt\REDCap\Classes\ProjectDesigner;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTask;
use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * FORM Class
 * Contains methods used with regard to forms or general data entry
 */
class Form
{
    public const FORM_STATUS_SECTION_HEADER_LABEL = "Form Status";

	/**
	 * BRANCHING LOGIC & CALC FIELDS: CROSS-EVENT FUNCTIONALITY
	 */
	public static function addHiddenFieldsOtherEvents($record, $event_id, $form, $instance)
	{
		global $Proj, $fetched, $repeatingFieldsEventInfo;
		$metadata_table = $Proj->getMetadataTable();
		$Proj_metadata = $Proj->getMetadata();
		$Proj_forms = $Proj->getForms();
		// Get list of unique event names
		$events = $Proj->getUniqueEventNames();
		// Collect the fields used for each event (so we'll know which data to retrieve)
		$eventFields = array();
		// If field is not on this form, then add it as a hidden field at bottom near Save buttons
		$sql = "SELECT * FROM (
					SELECT concat(if(branching_logic is null,'',branching_logic), ' ', if(element_enum is null,'',element_enum), ' ', if(misc is null,'',misc)) as bl_calc
					FROM $metadata_table WHERE project_id = ".PROJECT_ID." and (branching_logic is not null or element_type = 'calc' or (element_type = 'text' and misc like '%@CALC%'))
				) x where (bl_calc like '%-event-name]%' or bl_calc like '%[" . implode("]%' or bl_calc like '%[", $events) . "]%')";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Replace any Smart Variables first
			$row['bl_calc'] = Piping::pipeSpecialTags($row['bl_calc'], PROJECT_ID, $record, $event_id, $instance, null, true, null, $form, false, false, false, true, false, false, true);
			// Replace unique event name+field_name in brackets with javascript equivalent
			foreach (array_keys(getBracketedFields($row['bl_calc'], true, true)) as $this_field)
			{
				// Skip if doesn't contain a period (i.e. does not have an event name specified)
				if (strpos($this_field, ".") === false) continue;
				// Obtain event name and ensure it is legitimate
				list ($this_event, $this_field) = explode(".", $this_field, 2);
				if (in_array($this_event, $events))
				{
					// Get event_id of unique this event
					$this_event_id = array_search($this_event, $events);
					// Don't add to array if already in array
                    if (!isset($eventFields[$this_event_id]) || !in_array($this_field, $eventFields[$this_event_id])) {
						$eventFields[$this_event_id][] = $this_field;
					}
				}
			}
		}
		// Initialize HTML string
		$html = "";
		// Loop through each event where fields are used
		foreach ($eventFields as $this_event_id=>$these_fields)
		{
			// Don't create extra form if it's the same event_id (redundant)
			if ($this_event_id == $_GET['event_id']) continue;
			// Seed array with default data
			$these_fields_data = $these_fields_form_complete = array();
			foreach ($these_fields as $this_field) {
				if ($Proj_metadata[$this_field]['element_type'] != "checkbox") {
					$these_fields_data[$this_field][''] = '';
				}
				if ($Proj->isRepeatingEvent($this_event_id) || $Proj->isRepeatingForm($this_event_id, $Proj_metadata[$this_field]['form_name'])) {
					$these_fields_form_complete[] = $Proj_metadata[$this_field]['form_name']."_complete";
				}
			}
			$these_fields_form_complete = array_unique($these_fields_form_complete);
			// First, query each event for its data for this record
			$sql = "select field_name, value, if(instance is null,'',instance) as instance
					from ".\Records::getDataTable(PROJECT_ID)." where project_id = " . PROJECT_ID . " and event_id = $this_event_id and value != ''
					and record = '" . db_escape($fetched) . "' and (field_name in ('" . implode("', '", $these_fields) . "')
					or field_name in ('" . implode("', '", $these_fields_form_complete) . "'))";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// Save data in array
				if ($Proj_metadata[$row['field_name']]['element_type'] != "checkbox") {
					$these_fields_data[$row['field_name']][$row['instance']] = $row['value'];
				} else {
					$these_fields_data[$row['field_name']][$row['instance']][] = $row['value'];
				}
			}
			// If there are any repeating events/forms, then loop through the forms that have repeating instances, 
			// and make sure we have placeholder values for all existing instance for their fields
			foreach ($these_fields_form_complete as $this_form_complete) {
				$this_form = substr($this_form_complete, 0, -9);
                if (!isset($Proj_forms[$this_form]['fields']) || !is_array($Proj_forms[$this_form]['fields'])) continue;
				foreach (array_keys($Proj_forms[$this_form]['fields']) as $this_field) {
					if (!isset($these_fields_data[$this_field]) || !isset($these_fields_data[$this_form_complete]) || !is_array($these_fields_data[$this_form_complete])) continue;
					foreach (array_keys($these_fields_data[$this_form_complete]) as $this_instance) {
						if (isset($these_fields_data[$this_field][$this_instance])) continue;
						if ($Proj_metadata[$this_field]['element_type'] != "checkbox") { // Currently not sure how to do this with checkboxes
							$these_fields_data[$this_field][$this_instance] = '';
						}
					}
				}
			}
			// Get unique event name
			$this_unique_name = $events[$this_event_id];
			
			$isRepeatingEvent = $Proj->isRepeatingEvent($this_event_id);
		
			// Create HTML form
			$html .= "\n<form name=\"form__$this_unique_name\" enctype=\"multipart/form-data\">";
			// Loop through all fields in array
			foreach ($these_fields as $this_field)
			{
				// Determine if field is on a repeating form or event
				$this_form = $Proj_metadata[$this_field]['form_name'];
				$isRepeatingForm = $isRepeatingEvent ? false : $Proj->isRepeatingForm($this_event_id, $this_form);	
				$repeat_instrument = $isRepeatingForm ? $this_form : "";
				// Non-checkbox field
				if ($Proj_metadata[$this_field]['element_type'] != "checkbox")
				{
					foreach ($these_fields_data[$this_field] as $this_instance=>$value) {
						if ($this_instance == '' && ($isRepeatingForm || $isRepeatingEvent)) {
							$this_instance = '1';
						}
						// Remove from array
						unset($repeatingFieldsEventInfo[$this_event_id][$repeat_instrument][$this_instance][$this_field]);
						// Check for orphaned repeating data and skip
						if (!$isRepeatingEvent && !$isRepeatingForm && $this_instance != "") continue;
						// If this is really a date[time][_seconds] field that is hidden, then make sure we reformat the date for display on the page
                        $fv = "";
						if ($Proj_metadata[$this_field]['element_validation_type'] !== null && $Proj_metadata[$this_field]['element_type'] == 'text')
						{
                            $fv = "fv=\"".$Proj_metadata[$this_field]['element_validation_type']."\"";
							if (substr($Proj_metadata[$this_field]['element_validation_type'], -4) == '_mdy') {
								list($this_date, $this_time) = array_pad(explode(" ", $value), 2, "");
								$value = trim(DateTimeRC::date_ymd2mdy($this_date) . " " . $this_time);
							} elseif (substr($Proj_metadata[$this_field]['element_validation_type'], -4) == '_dmy') {
								list($this_date, $this_time) = array_pad(explode(" ", $value), 2, "");
								$value = trim(DateTimeRC::date_ymd2dmy($this_date) . " " . $this_time);
							}
						}
						$this_instance_name = "";
						if (is_numeric($this_instance) && ($isRepeatingForm || $isRepeatingEvent)) {
							$this_instance_name = "____I{$this_instance}";
						}
						$html .= "\n  <input type=\"hidden\" name=\"{$this_field}{$this_instance_name}\" value=\"".htmlspecialchars($value, ENT_QUOTES)."\" $fv>";
					}
				}
				// Checkbox field
				else
				{
					$field_choices = parseEnum($Proj_metadata[$this_field]['element_enum']);
					$isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($this_event_id, $Proj_metadata[$this_field]['form_name']);
					if (!isset($these_fields_data[$this_field])) {
						// No choices have been selected, so nothing in data table						
						foreach ($field_choices as $this_code=>$this_label)
						{
							// Set with no value
							$html .= "\n  <input type=\"hidden\" value=\"\" name=\"__chk__{$this_field}_RC_".DataEntry::replaceDotInCheckboxCoding($this_code)."\">";
						}
					} else {
						// Loop through data
						foreach ($these_fields_data[$this_field] as $this_instance=>$value) {
							// Remove from array
							unset($repeatingFieldsEventInfo[$this_event_id][$repeat_instrument][$this_instance][$this_field]);
							$this_instance_name = "";
							if (is_numeric($this_instance) && ($this_instance > 1 || $isRepeatingFormOrEvent)) {
								$this_instance_name = "____I{$this_instance}";
							}
							foreach ($field_choices as $this_code=>$this_label)
							{
								if (in_array($this_code, $these_fields_data[$this_field][$this_instance])) {
									$default_value = $this_code;
								} else {
									$default_value = ''; //Default value is 'null' if no present value exists
								}
								$html .= "\n  <input type=\"hidden\" value=\"".htmlspecialchars($default_value, ENT_QUOTES)."\" name=\"__chk__{$this_field}_RC_".DataEntry::replaceDotInCheckboxCoding($this_code)."{$this_instance_name}\">";
							}
						}
					}
				}
			}
			
			// Loop through fields that are being referenced but have no data
			if (isset($repeatingFieldsEventInfo[$this_event_id]) && !empty($repeatingFieldsEventInfo[$this_event_id]))
			{
				foreach ($repeatingFieldsEventInfo[$this_event_id] as $attr) {
					foreach ($attr as $this_instance=>$bttr) {
						foreach (array_keys($bttr) as $this_field) {
							$isRepeatingForm = $isRepeatingEvent ? false : $Proj->isRepeatingForm($this_event_id, $Proj_metadata[$this_field]['form_name']);			
							$this_instance_name = "";
							if (is_numeric($this_instance) && ($isRepeatingForm || $isRepeatingEvent)) {
								$this_instance_name = "____I{$this_instance}";
							}
							if (!$Proj->isCheckbox($this_field)) {
								$html .= "\n  <input type=\"hidden\" name=\"{$this_field}{$this_instance_name}\" value=\"\">";
							} else {
								$field_choices = parseEnum($Proj_metadata[$this_field]['element_enum']);
								foreach ($field_choices as $this_code=>$this_label)
								{
									// Set with no value
									$html .= "\n  <input type=\"hidden\" value=\"\" name=\"__chk__{$this_field}_RC_".DataEntry::replaceDotInCheckboxCoding($this_code)."{$this_instance_name}\">";
								}
							}
						}
					}
				}
			}
			
			// End form
			$html .= "\n</form>\n";
		}
		if ($html != "") $html = "\n\n<!-- Hidden forms containing data from other events -->$html\n";
		// Return the other events' fields in an HTML form for each event
		return $html;
	}

	// Delete a form from all database tables EXCEPT metadata tables and user_rights table and surveys table
	public static function deleteFormFromTables($form)
	{
		if (!defined("PROJECT_ID") || $form == '') return;
		$sql = "delete from redcap_events_forms where form_name = '".db_escape($form)."'
				and event_id in (" . pre_query("select m.event_id from redcap_events_arms a, redcap_events_metadata m where a.arm_id = m.arm_id and a.project_id = " . PROJECT_ID . "") . ")";
		db_query($sql);
		$sql = "delete from redcap_library_map where project_id = " . PROJECT_ID . " and form_name = '".db_escape($form)."'";
		db_query($sql);
		$sql = "delete from redcap_locking_labels where project_id = " . PROJECT_ID . " and form_name = '".db_escape($form)."'";
		db_query($sql);
		$sql = "delete from redcap_locking_data where project_id = " . PROJECT_ID . " and form_name = '".db_escape($form)."'";
		db_query($sql);
		$sql = "delete from redcap_esignatures where project_id = " . PROJECT_ID . " and form_name = '".db_escape($form)."'";
		db_query($sql);		
		$sql = "delete from redcap_events_repeat where event_id in (" . prep_implode(array_keys(Event::getEventsByProject(PROJECT_ID))) . ") and form_name = '".db_escape($form)."'";
		db_query($sql);
        $sql = "DELETE FROM redcap_mycap_tasks_schedules WHERE task_id IN (" . pre_query("SELECT task_id FROM redcap_mycap_tasks WHERE project_id = " . PROJECT_ID . " AND form_name = '".db_escape($form)."'") . ")";
        db_query($sql);
        $sql = "DELETE FROM redcap_mycap_tasks WHERE project_id = " . PROJECT_ID . " AND form_name = '".db_escape($form)."'";
        db_query($sql);
	}
	
	/**
	 * Renders the form list on the project main menu (Data Collection)
	 * @param string $record_id The record ID
	 * @param string|int $record_exists Indicates whether the record already exists (1) or not (0)
	 * @return string 
	 * @throws Exception When not in project context
	 */
	public static function renderFormMenuList($record_id, $record_exists)
	{
		// We must be in a project context
		list ($Proj, $project_id) = Project::requireProject();
		$record_exists = $record_exists == 1; // Convert to boolean
		// What page are we on?
		$isCalPopup = (PAGE == "Calendar/calendar_popup.php");
		$isDataEntry = (PAGE == "DataEntry/index.php");
		$isRecordHome = (PAGE == "DataEntry/record_home.php");
		$record_exists = $record_exists || (PAGE == "DataEntry/record_home.php" && isset($_GET["id"]) && $_GET["id"] === $record_id);
		$form_menu_classes = [ "rc-form-menu-item" ];
		// In longitudinal projects, we must be on either of the two named above, and a record must be set
		if ($Proj->longitudinal) {
			if ($isRecordHome) return RCView::span(["class" => "rc-form-menu-prompt"], RCView::tt("data_entry_675"));
			if (!($isDataEntry || $isCalPopup)) return "";
			if (!isset($record_id)) return "";
		}
		else {
			if (!($isDataEntry || $isCalPopup) && UIState::getUIStateValue($project_id, 'sidebar', 'show-instruments-toggle') != '1') {
				$form_menu_classes[] = "hidden";
			} 
		}
		if ($Proj->hasRepeatingForms()) {
			$form_menu_classes[] = "rc-form-menu-repeating";
		}

		// Determine some context info. 
		$event_id   = (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) 
			? $_GET['event_id'] 
			: getSingleEvent($project_id);
		$current_form = $_GET['page'] ?? "";
		$event_forms = $Proj->eventsForms[$event_id] ?? [];
		$instance = max(1, intval($_GET['instance'] ?? 1));
		// Repeating event?
		$isRepeatingEvent = $Proj->isRepeatingEvent($event_id);
		

		// Record ID encoded for url. Note, this does not include the double data entry suffix
		$url_record_id = RCView::escape($record_id);
		
		// ------------------------------------------------------------------------
		// Double Data Entry?
		// From here on, add the double data entry suffix to the record id argument
		$entry_num = ($Proj->project["double_data_entry"] && $GLOBALS["user_rights"]['double_data'] != '0') ? "--".$GLOBALS["user_rights"]['double_data'] : "";
		$record_id .= $entry_num;
		// ------------------------------------------------------------------------
		
		#region Locking / E-Signature indicators
		$locked_forms = array_fill_keys($event_forms, "");
		// Only query the DB if there is a record
		if ($record_id != "" && $record_exists) {
			// Locked records
			$sql = "SELECT form_name, timestamp 
					FROM redcap_locking_data 
					WHERE project_id = ? AND event_id = ? AND record = ? AND instance = ?";
			$q = db_query($sql, [$project_id, $event_id, $record_id, $instance]);
			while ($row = db_fetch_assoc($q)) {
				if (in_array($row['form_name'], $event_forms)) {
					$locked_forms[$row['form_name']] = RCView::span([
						"id" => "formlock-".$row['form_name'],
						"class" => "rc-form-menu-locked",
						"title" => js_escape2(RCView::tt_i_strip_tags("bottom_117", DateTimeRC::format_ts_from_ymd($row['timestamp']))),
					], RCIcon::Locked("fa-sm text-warning"));
				}
			}
			// E-signatures
			if ($GLOBALS['esignature_enabled_global']) {
				$sql = "SELECT form_name, timestamp
						 FROM redcap_esignatures 
						WHERE project_id = ? AND event_id = ? AND record = ? AND instance = ?";
				$q = db_query($sql, [$project_id, $event_id, $record_id, $instance]);
				while ($row = db_fetch_assoc($q)) {
					if (in_array($row['form_name'], $event_forms)) {
						$locked_forms[$row['form_name']] .= RCView::span([
							"id" => "formesign-".$row['form_name'],
							"class" => "rc-form-menu-esigned",
							"title" => js_escape2(RCView::tt_i_strip_tags("bottom_118", DateTimeRC::format_ts_from_ymd($row['timestamp']))),
						], RCIcon::ESigned("fa-sm text-success"));
					}
				}
			}
		}
		#endregion

		$fdl_hide_disabled_forms = $Proj->project["hide_disabled_forms"] == 1;
		$formsAccess = $formStatusValues = $surveyResponses = [];
		if ($record_id != "") {
			// Form Display Logic
			$formsAccess = FormDisplayLogic::getEventFormsState($project_id, $record_id, $event_id, $instance);
			$formsAccess = $formsAccess[$record_id][$event_id];

			// Get the necessary form statuses
			$formStatusValues = Records::getFormStatus($project_id, [$record_id], getArm(), null, [$event_id => $event_forms]);
			$formStatusValues = $formStatusValues[$record_id][$event_id];
			// Determine if record also exists as a survey response for some instruments
			if ($Proj->project["surveys_enabled"]) {
				$surveyResponses = Survey::getResponseStatus($project_id, $record_id, $event_id);
				$surveyResponses = $surveyResponses[$record_id][$event_id] ?? [];
			}
		}

		// Link construction
		$link_template = APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id#PAGE#&id=$url_record_id&event_id=$event_id#INSTANCE#";
		// If creating a new record via auto-numbering, make sure that the "auto" parameter gets
		// perpetuated in the query string, just in case
		$link_template .= isset($_GET['auto']) ? "&auto=1" : "";

		// Default case - no instance added
		$construct_pageurl = function($form_name) use ($link_template, $isCalPopup) {
			$key = $isCalPopup ? "onclick" : "href";
			$href = str_replace(["#PAGE#", "#INSTANCE#"], ["&page=$form_name", ""], $link_template);
			$href = $isCalPopup ? "window.opener.location.href='$href';self.close();" : $href;
			return [
				$key => $href,
			];
		};
		if ($isRepeatingEvent) {
			// In a repeating event, we maintain the current instance, unless we are rendering for the
			// calendar popup, in which case we need to show a picker
			$construct_pageurl = function($form_name) use ($link_template, $project_id, $record_id, $event_id, $instance, $isCalPopup) {
				if ($isCalPopup) {
					return [
						"href" => "javascript:;",
						"onclick" => "showFormInstanceSelector(this, $project_id, '".htmlspecialchars(($record_id), ENT_QUOTES)."', '$form_name', $event_id, '".htmlspecialchars((removeDDEending($record_id)), ENT_QUOTES)."');",
					];
				}
				return [
					"href" => str_replace(["#PAGE#", "#INSTANCE#"], ["&page=$form_name", "&instance=$instance"], $link_template),
				];
			};
		}
		else {
			// Link depends on whether the form is a repeating form
			$construct_pageurl = function($form_name) use ($link_template, $current_form, $project_id, $record_id, $event_id, $instance, $Proj, $formStatusValues, $isCalPopup) {
				if ($Proj->isRepeatingForm($event_id, $form_name) && !empty($formStatusValues[$form_name]) && count($formStatusValues[$form_name]) > 1) {
					// Always show a picker for repeating forms
					return [
						"href" => "javascript:;",
						"onclick" => "showFormInstanceSelector(this, $project_id, '".htmlspecialchars(($record_id), ENT_QUOTES)."', '$form_name', $event_id, '".htmlspecialchars((removeDDEending($record_id)), ENT_QUOTES)."');",
					];
				}
				$href = str_replace(["#PAGE#", "#INSTANCE#"], ["&page=$form_name", ""], $link_template);
				if ($isCalPopup) {
					return [
						"onclick" => "window.opener.location.href='$href';self.close();",
					];
				}
				else {
					return [
						"href" => $href,
					];
				}
			};
		}

		// Loop through each form in this event and assemble the HTML to display the forms menu
		$html = "";
		foreach ($event_forms as $form_name) {
			// Skip form when it's disabled by Form Display Logic and the option to hide disabled forms is on
			if ($fdl_hide_disabled_forms && $record_id != "" && !$formsAccess[$form_name]) {
				continue;
			}
			// Display normal form links ONLY if user has rights to the form
			$hasViewAccess = (isset($Proj->forms[$form_name]) 
				&& !UserRights::hasDataViewingRights($GLOBALS['user_rights']['forms'][$form_name], "no-access")
				&& (!$Proj->longitudinal || (PAGE == "DataEntry/index.php" || $isCalPopup)));
            if (!$hasViewAccess) continue;
            // Get attributes needed
			$menu_text = filter_tags($Proj->forms[$form_name]['menu']);
			$this_form_repeating = !$isRepeatingEvent && $Proj->isRepeatingForm($event_id, $form_name);
			$form_status = $formStatusValues[$form_name] ?? [];
			$is_survey_status = isset($surveyResponses[$form_name][$instance]);
			$is_current_form = $form_name == $current_form;
			$icon_attr = self::getFormStatusIcon($isRepeatingEvent ? [$instance => ($form_status[$instance]??"")] : $form_status, $is_current_form ? $instance : null, $is_survey_status, $this_form_repeating, $project_id, $record_id, $event_id, $form_name, $instance);
			$icon_attr["class"] = "rc-form-menu-icon";
			$link_attr = $construct_pageurl($form_name);
			if (!isset($link_attr["href"])) $link_attr["href"] = "javascript:;"; // Ensures that href is set
			$link_attr["class"] = "rc-form-menu-link";
			if ($record_id != "" && !$formsAccess[$form_name]) $link_attr["class"] .= " rc-form-menu-fdl-disabled";
			$form_name_attr = [
				"data-mlm" => "form_name",
				"data-mlm-name" => $form_name,
			];
			if ($is_current_form) $form_name_attr["class"] = "rc-form-menu-current";
			$repeat_stats = "";
			$plus_button = "";
			if ($this_form_repeating && count($form_status) && !$isCalPopup) {
				$repeat_stats = self::getFormRepeatStats($form_status, $is_current_form ? $instance : null);
				// In some cases, add + button to add a new repeat instance of a form
				// Specifically:
				// - The record must exist
				// - Draft preview is not enabled
				// - FDL allows access
				// - When this is the current form, the instance of the form exists
				if ($record_exists && !($GLOBALS["draft_preview_enabled"] ?? false) && $formsAccess[$form_name] && (!$is_current_form || array_key_exists($instance, $form_status)) && FormDisplayLogic::checkAddNewRepeatingFormInstanceAllowed($project_id, $record_id, $event_id, $form_name)) {
					$plus_button = RCView::a([
						"href" => Form::getAddNewFormInstanceUrl($project_id, $record_id, $event_id, $form_name),
						"class" => "btn btn-defaultrc rc-form-menu-plus",
					], "+");
				}
			}
			$html .= 
				RCView::div([
						"class" => join(" ", $form_menu_classes),
						"data-form" => $form_name,
					],
					($record_exists && ($isDataEntry || $isCalPopup) ? RCView::a($link_attr, 
						RCView::img($icon_attr) 
					) : "") .
					RCView::a($link_attr, 
						RCView::span($form_name_attr,
							$menu_text
						)
					) .
					$locked_forms[$form_name] .
					$repeat_stats .
					$plus_button
				);
		}
		return $html;
	}

	private static function getFormStatusIcon($statuses, $priority_instance, $is_survey, $is_repeating_form, $project_id, $record_id, $event_id, $form_name, $instance) {
		$stack = $is_repeating_form && (count($statuses) > 1 || ($priority_instance !== null && !array_key_exists($priority_instance, $statuses)))
			? "_stack" : "";
		$unique_statuses = array_unique(array_values($statuses));
		if (count($unique_statuses)) {
			if ($priority_instance == null) {
				$status = count($unique_statuses) == 1 ? $unique_statuses[0] : "blue";
			}
			else {
				$status = $statuses[$priority_instance] ?? "";
			}
		}
		else {
			$status = "";
		}
		$status = ($is_survey ? "S" : "") . $status;
		// When draft preview is enabled, the form status may be different. Thus, it needs to be updated
		if ($GLOBALS["draft_preview_enabled"] ?? false) {
			$status = Design::updateFormStatus($project_id, $record_id, $event_id, $form_name, $instance, $is_survey, $status, false);
		}
		// Depending on the status, return the appropriate icon src and title
		switch($status) {
			case "S2": 
				return [
					"src" => APP_PATH_IMAGES."circle_green_tick$stack.png",
					"title" => RCView::tt_attr("global_94")
				];
			case "S1":
			case "S0":
			case "Sblue":
				return [
					"src" => APP_PATH_IMAGES."circle_orange_tick$stack.png",
					"title" => RCView::tt_attr("global_95")
				];
			case "2":
				return [
					"src" => APP_PATH_IMAGES."circle_green$stack.png",
					"title" => RCView::tt_attr("survey_28")
				];
			case "1":
				return [
					"src" => APP_PATH_IMAGES."circle_yellow$stack.png",
					"title" => RCView::tt_attr("global_93")
				];
			case "0":
				return [
					"src" => APP_PATH_IMAGES."circle_red$stack.png",
					"title" => RCView::tt_attr("global_92")
				];
			case "blue":
				// This must be a stack
				return [
					"src" => APP_PATH_IMAGES."circle_blue_stack.png",
					"title" => RCView::tt_attr("global_92")
				];
			default:
				return [
					"src" => APP_PATH_IMAGES."circle_gray$stack.png",
					"title" => RCView::tt_attr("global_92")
				];
		}
	}


	private static function getFormRepeatStats($form_status, $instance) {
		// Get max instance number and total count
		$max = max(array_keys($form_status));
		$count = count($form_status);
		$output = function($template, $vals) {
			return RCView::tt_i($template, $vals, true, "span", [ "class" => "rc-form-menu-repeat-stats" ]);
		};
		if ($instance == null) {
			// Not on any instance of a repeating form
			return $max == $count
				? $output("data_entry_672", [ $count ])
				: $output("data_entry_673", [ $max, $count ]);
		}
		if (!isset($form_status[$instance])) {
			return $output("data_entry_670", [ "#$instance", $count ]);
		}
		return $max == $count 
			? $output("data_entry_674", [ "#$instance", $count ])
			: $output("data_entry_671", [ "#$instance", $max, $count ]);
	}

	/**
	 * Get the content for the instance selector popovers
	 * @param string $record_id 
	 * @param string $form 
	 * @param string|int $event_id 
	 * @param bool $deferred When true, the content will be deferred
	 * @param bool $isRhpTable When true, this is an AJAX request from the RHP, and the md5 of the record id is not added to the id
	 * @return array{title: string, body: string, pageLength: int, language: array{zeroRecords: string, searchPlaceholder: string}, data: array{instance: mixed, status: mixed, label: mixed}[]} 
	 * @throws Exception When not in project context
	 */
	public static function getInstanceSelectorContent($record_id, $form, $event_id, $deferred = false, $isRhpTable = false) {
		list ($Proj, $project_id) = Project::requireProject();
		$data = [];
		if ($deferred) {
			$locking_data = [];
			$esigned_data = [];
			$title = "Deferred";
		}
		else {
			$draft_preview_enabled = ($GLOBALS["draft_preview_enabled"] ?? false);
			$arm = $Proj->eventInfo[$event_id]["arm_num"];
			$event_forms = $Proj->eventsForms[$event_id];
			$formStatusValues = Records::getFormStatus($project_id, [$record_id], $arm, null, [$event_id => $event_forms]);
			$fdl_disabled = false;
			if ($Proj->isRepeatingForm($event_id, $form)) {
				$fdl_disabled = FormDisplayLogic::checkFormAccess($project_id, $record_id, $event_id, $form) !== true;
			}
			$form_status = $formStatusValues[$record_id][$event_id][$form];
			if ($Proj->isRepeatingEvent($event_id)) {
				// In case of a repeating event, we need to fill $form_status with all gray instances
				$repeating_event_instances = [];
				foreach (array_values($formStatusValues[$record_id][$event_id]) as $this_instances) {
					$repeating_event_instances = array_merge($repeating_event_instances, array_keys($this_instances));
				}
				// Ensure unique and sort numerically
				$repeating_event_instances = array_unique($repeating_event_instances);
				sort($repeating_event_instances);
				// Now fill
				foreach ($repeating_event_instances as $instance) {
					if (!isset($form_status[$instance])) {
						$form_status[$instance] = "";
					}
				}
				ksort($form_status);
			}
			// Determine if record also exists as a survey response for some instruments
			$surveyResponses = [];
			if ($Proj->project["surveys_enabled"]) {
				$surveyResponses = Survey::getResponseStatus($project_id, $record_id, $event_id);
				$surveyResponses = $surveyResponses[$record_id][$event_id][$form] ?? [];
			}
			// Custom repeat instance labels
			$custom_form_label = trim($Proj->RepeatingFormsEvents[$event_id][$form] ?? "");
			$custom_form_label_fields = array_keys(getBracketedFields($custom_form_label, true, false, true));
			$piped_data = $draft_preview_enabled 
				? Design::getRecordDataForDraftPreview($project_id, $record_id)
				: Records::getData('array', $record_id, $custom_form_label_fields, $event_id);
			// Get locking and e-signature data
			$locking_data = self::getFormLockedData($project_id, $record_id, $event_id, $form);
			$esigned_data = self::getFormESignedData($project_id, $record_id, $event_id, $form);
			// Assemble instance data
			foreach ($form_status as $instance => $status) {
				$label = $custom_form_label == ""
					? RCView::tt_i("data_entry_679", [$instance]) 
					: Piping::replaceVariablesInLabel($custom_form_label, $record_id, $event_id, $instance, $piped_data, false, null, false, $form, 1, false, false, $form);
				$locked = $locking_data[$record_id][$event_id][$form][$instance]["ts"] ?? "";
				if ($locked) $locked = DateTimeRC::format_ts_from_ymd($locked);
				$esigned = $esigned_data[$record_id][$event_id][$form][$instance]["ts"] ?? "";
				if ($esigned) $esigned = DateTimeRC::format_ts_from_ymd($esigned);
				// In case of a repeating event, we also need to check each individual instance's FDL status
				if ($Proj->isRepeatingEvent($event_id)) {
					$fdl_disabled = !(FormDisplayLogic::checkFormAccess($project_id, $record_id, $event_id, $form, $instance) === true);
				}
				$updated_status = $status;
				if ($GLOBALS["draft_preview_enabled"]) {
					$updated_status = Design::updateFormStatus($project_id, $record_id, $event_id, $form, $instance, isset($surveyResponses[$instance]), $status, false);
				}
				// Only consider survey status when draft preview has not changed the status
				$status = ($updated_status == $status && isset($surveyResponses[$instance])) ? ("S".$status) : $status;
				$data[] = [
					"instance" => $instance,
					"status" => $status,
					"disabled" => $fdl_disabled,
					"locked" => $locked,
					"esigned" => $esigned,
					"label" => $label,
				];
			}
			$title = RCView::tt_i("data_entry_676", [count($data)]) . 
				($Proj->longitudinal 
					? RCView::div(["class" => "rc-instance-selector-event-name"], $Proj->eventInfo[$event_id]["name"]) 
					: ""
				) .
				RCView::button([
					"type" => "button",
					"class" => "btn btn-xs btn-default rc-close-button",
				], RCIcon::Close());
		}
		$table_id = "rc-instance-selector-$form-$event_id-" . md5($record_id);
		if ($isRhpTable) {
			$table_id = "repeat_instrument_table-$form-$event_id";
			$title = null;
		}
		$pageLengthStateKey = $isRhpTable ? "rhp-$form-$event_id" : "grid";
		$pageLength = UIState::getUIStateValue($project_id, "rc-instance-selector-pageLength", $pageLengthStateKey) ?? "10";
		$pageLenghts = [
			"10" => "10",
			"25" => "25",
			"50" => "50",
			"all" => RCView::tt("dashboard_12"),
		];
		$pageLengthMin = 10;
		$filtersStateKey = $isRhpTable ? "rhp-$form-$event_id" : "";
		$filters = $filters_default = "0,1,2,S0,S2"; // all
		if ($isRhpTable && count($data) > $pageLengthMin) {
			$filters = UIState::getUIStateValue($project_id, "rc-instance-selector-filters", $filtersStateKey) ?? $filters_default;
		}
		return [
			"id" => $table_id,
			"title" => $title,
			"body" => RCView::table([
					"id" => $table_id,
					"class" => "compact",
					"width" => "100%",
				]) . 
				RCView::div(["class" => "hidden"], 
					// MLM support - these will be translated on the fly and used in the DataTable
					RCView::tt("data_entry_677") .
					RCView::tt("data_entry_678") .
					RCView::tt("data_entry_680") .
					RCView::tt("data_entry_687") .
					RCView::tt("data_entry_688") .
					RCView::tt_i("bottom_117", ["{0}"]) .
					RCView::tt_i("bottom_118", ["{0}"])
				),
			"pageLength" => $pageLength,
			"pageLengths" => $pageLenghts,
			"pageLengthStateKey" => $pageLengthStateKey,
			"pageLengthMin" => $pageLengthMin,
			"filtersStateKey" => $filtersStateKey,
			"filters" => explode(",", $filters),
			"language" => [
				"zeroRecords" => "data_entry_677",
				"searchPlaceholder" => "data_entry_678",
				"label" => "data_entry_680",
				"setPageSizeTitle" => $isRhpTable ? "data_entry_687" : "data_entry_688",
			],
			"locked" => RCView::span([
				"class" => "rc-rhp-locked-indicator",
			], RCIcon::Locked("text-warning fa-xs")),
			"esigned" => RCView::span([
				"class" => "rc-rhp-esigned-indicator",
			], RCIcon::ESigned("text-success fa-xs")),
			"data" => $data,
		];
	}

	/**
	 * Get locking or e-signature data for record(s) on certain events/forms
	 * @param string $table One of 'redcap_locking_data' or 'redcap_esignatures'
	 * @param string|int|null $project_id 
	 * @param string|array $record_id 
	 * @param string|int|string[]|int[]|null $event_id 
	 * @param string|string[]|null $form 
	 * @return array<string, array<string, array<string, array<int, array{ts: string, user: string}>>>> 
	 * @throws Exception When not in project context or when invalid table or invalid event id
	 */
	private static function getLockingOrESignatureData($table, $project_id, $record_id = [], $event_id = null, $form = []) {
		if (!in_array($table, ["redcap_locking_data", "redcap_esignatures"], true)) {
			throw new \Exception("Invalid table '$table' - must be one of 'redcap_locking_data' or 'redcap_esignatures");
		}
		list ($Proj, $project_id) = Project::requireProject();
		if (is_string($record_id)) $record_id = [$record_id];
		if (empty($record_id)) return [];
		$params = [ $project_id ];
		$where = "";
		// Records
		if (count($record_id) == 1) {
			$params[] = $record_id[0];
			$where .= " AND `record` = ?";
		}
		else {
			$params = array_merge($params, $record_id);
			$where .= " AND `record` IN (".implode(",", array_fill(0, count($record_id), "?")).")";
		}
		// Events
		if ($event_id === null) {
			$event_id = array_keys($Proj->eventsForms);
		}
		else {
			if (!is_array($event_id)) $event_id = [$event_id];
			// Validate
			foreach ($event_id as $key=>$this_event_id) {
				if (!array_key_exists($this_event_id, $Proj->eventsForms)) {
					unset($event_id[$key]); // Remove it if invalid
				}
			}
		}
		if (count($event_id) == 0) return []; // No need to do any more work
		if (count($event_id) == 1) {
			$params[] = $event_id[0];
			$where .= " AND `event_id` = ?";
		}
		else {
			$params = array_merge($params, $event_id);
			$where .= " AND `event_id` IN (".implode(",", array_fill(0, count($event_id), "?")).")";
		}
		// Forms
		if (is_string($form)) $form = [$form];
		if ($form === null || count($form) == 0) {} // No need to add to WHERE 
		elseif (count($form) == 1) {
			$params[] = $form[0];
			$where .= " AND `form_name` = ?";
		}
		else {
			$params = array_merge($params, $form);
			$where .= " AND `form_name` IN (".implode(",", array_fill(0, count($form), "?")).")";
		}
		// Query
		$sql = "SELECT `record`,`event_id`, `form_name`, `instance`, `timestamp`, `username` 
				FROM $table
				WHERE project_id = ? " . $where;
		$q = db_query($sql, $params);
		$data = [];
		while ($row = db_fetch_assoc($q)) {
			$data[$row["record"]][$row["event_id"]][$row["form_name"]][$row["instance"]] = [
				"ts" => $row["timestamp"],
				"user" => $row["username"],
			];
		}
		return $data;
	}

	/**
	 * Get locking data for record(s) on certain events/forms
	 * @param string|int|null $project_id 
	 * @param string|array $record_id 
	 * @param string|int|string[]|int[]|null $event_id 
	 * @param string|string[]|null $form 
	 * @return array<string, array<string, array<string, array<int, array{ts: string, user: string}>>>> 
	 * @throws Exception When not in project context or when invalid table or invalid event id
	 */
	public static function getFormLockedData($project_id, $record_id = [], $event_id = null, $form = []) {
		return self::getLockingOrESignatureData("redcap_locking_data", $project_id, $record_id, $event_id, $form);
	}

	/**
	 * Get e-signature data for record(s) on certain events/forms
	 * @param string|int|null $project_id 
	 * @param string|array $record_id 
	 * @param string|int|string[]|int[]|null $event_id 
	 * @param string|string[]|null $form 
	 * @return array<string, array<string, array<string, array<int, array{ts: string, user: string}>>>> 
	 * @throws Exception When not in project context or when invalid table or invalid event id
	 */
	public static function getFormESignedData($project_id, $record_id = [], $event_id = null, $form = []) {
		return self::getLockingOrESignatureData("redcap_esignatures", $project_id, $record_id, $event_id, $form);
	}




	// ACTION TAGS: Return array of all action tags with tag name as array key and description as array value.
	// If the $onlineDesigner param is passed, it will return only those that are utilized on the Online Designer.
	public static function getActionTags($onlineDesigner=false)
	{
		global $lang, $mobile_app_enabled, $mycap_enabled, $mycap_enabled_global;
		// Set all elements of array
		$action_tags = array();
		if (!$onlineDesigner) {
			$action_tags['@CHARLIMIT'] = $lang['data_entry_406'];
			$action_tags['@DEFAULT'] = $lang['design_659'];
            $action_tags['@DOWNLOAD-COUNT'] = $lang['design_1071'];
			$action_tags['@FORCE-MINMAX'] = $lang['data_entry_571'];
			$action_tags['@HIDDEN'] = $lang['design_609'];
			$action_tags['@HIDDEN-FORM'] = $lang['design_610'];
			$action_tags['@HIDDEN-PDF'] = $lang['design_790'];
			$action_tags['@HIDDEN-SURVEY'] = $lang['design_611'];
			$action_tags['@HIDECHOICE'] = $lang['data_entry_612'];
			$action_tags['@IF'] = $lang['data_entry_500'];
			$action_tags["@SAVE-PROMPT-EXEMPT"] = $lang['design_ic_01'];
			$action_tags["@SAVE-PROMPT-EXEMPT-WHEN-AUTOSET"] = $lang['design_ic_02'];
			$action_tags['@INLINE'] = $lang['data_entry_497'];
			$action_tags['@INLINE-PREVIEW'] = $lang['data_entry_604'];
			// Always display multilanguage action tags so that users can learn about them even before enabling MLM
            $action_tags['@LANGUAGE-CURRENT-FORM'] = $lang['multilang_03'];
            $action_tags['@LANGUAGE-CURRENT-SURVEY'] = $lang['multilang_200'];
            $action_tags['@LANGUAGE-FORCE'] = $lang['multilang_125'];
            $action_tags['@LANGUAGE-FORCE-FORM'] = $lang['multilang_126'];
            $action_tags['@LANGUAGE-FORCE-SURVEY'] = $lang['multilang_127'];
            $action_tags['@LANGUAGE-MENU-STATIC'] = $lang['multilang_805'];
            $action_tags['@LANGUAGE-SET'] = $lang['multilang_144'];
            $action_tags['@LANGUAGE-SET-FORM'] = $lang['multilang_708'];
            $action_tags['@LANGUAGE-SET-SURVEY'] = $lang['multilang_709'];
			$action_tags['@LATITUDE'] = $lang['design_629'];
			$action_tags['@LONGITUDE'] = $lang['design_630'];
			$action_tags['@MAXCHECKED'] = $lang['data_entry_420'];
			$action_tags['@MAXCHOICE'] = $lang['data_entry_419'];
			$action_tags['@MAXCHOICE-SURVEY-COMPLETE'] = $lang['data_entry_499'];
			$action_tags['@NOMISSING'] = $lang['data_entry_472'];
			$action_tags['@NONEOFTHEABOVE'] = $lang['data_entry_414'];
			$action_tags['@NOW'] = "(e.g., 2017-08-01 12:34:56) " . $lang['design_763'];
            $action_tags['@NOW-SERVER'] = "(e.g., 2017-08-01 12:34:56) " . $lang['design_785'];
            $action_tags['@NOW-UTC'] = "(e.g., 2017-08-01 12:34:56) " . $lang['design_786'];
			$action_tags['@PREFILL'] = $lang['design_948'];
			$action_tags['@RANDOMORDER'] = $lang['data_entry_407'];
			$action_tags['@READONLY'] = $lang['design_612'];
			$action_tags['@READONLY-FORM'] = $lang['design_613'];
			$action_tags['@READONLY-SURVEY'] = $lang['design_614'];
			$action_tags['@SETVALUE'] = $lang['design_948'];
			$action_tags['@SHOWCHOICE'] = $lang['data_entry_611'];
			$action_tags['@TODAY'] = "(e.g., 2017-08-01) " . $lang['design_762'];
            $action_tags['@TODAY-SERVER'] = "(e.g., 2017-08-01) " . $lang['design_787'];
            $action_tags['@TODAY-UTC'] = "(e.g., 2017-08-01) " . $lang['design_788'];
			$action_tags['@USERNAME'] = RCView::tt_i("design_1007", [System::SURVEY_RESPONDENT_USERID]);
			$action_tags['@WORDLIMIT'] = $lang['data_entry_405'];
			// The following tags are only for when using the Mobile App
			if ($mobile_app_enabled) {
				$action_tags['@APPUSERNAME-APP'] = $lang['design_661'];
				$action_tags['@BARCODE-APP'] = $lang['design_633'];
				$action_tags['@HIDDEN-APP'] = $lang['design_625'];
				$action_tags['@READONLY-APP'] = $lang['design_626'];
				$action_tags['@SYNC-APP'] = $lang['design_702'];
			}
            // The following tags are only for when using the MyCap
            $is_mycap_enabled = $mycap_enabled; // Project-level settings
            if (is_null($mycap_enabled)) {
                $is_mycap_enabled = $mycap_enabled_global; // System-level settings
            }
            if ($is_mycap_enabled) {
                $action_tags['@MC-FIELD-FILE-IMAGECAPTURE'] = $lang['data_entry_587'];
                $action_tags['@MC-FIELD-FILE-VIDEOCAPTURE'] = $lang['data_entry_588'];
                $action_tags['@MC-FIELD-HIDDEN'] = $lang['data_entry_589'];
                $action_tags['@MC-FIELD-SLIDER-BASIC'] = $lang['data_entry_586'];
                $action_tags['@MC-FIELD-SLIDER-CONTINUOUS'] = $lang['data_entry_598'];
                $action_tags['@MC-FIELD-TEXT-BARCODE'] = $lang['data_entry_590'];
                $action_tags['@MC-PARTICIPANT-CODE'] = $lang['data_entry_608'];
                $action_tags['@MC-PARTICIPANT-JOINDATE'] = $lang['data_entry_607'];
                $action_tags['@MC-PARTICIPANT-JOINDATE-UTC'] = $lang['data_entry_615'];
                $action_tags['@MC-PARTICIPANT-TIMEZONE'] = $lang['data_entry_616'];
                $action_tags['@MC-TASK-ENDDATE'] = $lang['data_entry_593'];
                $action_tags['@MC-TASK-SCHEDULEDATE'] = $lang['data_entry_594'];
                $action_tags['@MC-TASK-SERIALIZEDRESULTS'] = $lang['data_entry_597'];
                $action_tags['@MC-TASK-STARTDATE'] = $lang['data_entry_592'];
                $action_tags['@MC-TASK-STATUS'] = $lang['data_entry_595'];
                $action_tags['@MC-TASK-SUPPLEMENTALDATA'] = $lang['data_entry_596'];
                $action_tags['@MC-TASK-UUID'] = $lang['data_entry_591'];
            }
		}
		// The following tags will additionally be implemented on the Online Designer as a preview
		$action_tags['@CALCDATE'] = $lang['design_1356'].RCView::div(['class'=>'mt-2'], $lang['design_1000']." ".$lang['design_1360']);
		$action_tags['@CALCTEXT'] = $lang['design_837'].RCView::div(['class'=>'mt-2'], $lang['design_1083']).RCView::div(['class'=>'mt-2'], $lang['design_1000']);
		$action_tags['@CONSENT-VERSION'] = $lang['econsent_64'];
		$action_tags['@HIDEBUTTON'] = $lang['design_662'];
		$action_tags['@PASSWORDMASK'] = $lang['design_624'];
		$action_tags['@PLACEHOLDER'] = $lang['design_703'];
		$action_tags['@RICHTEXT'] = $lang['design_1008'];
		// Order the tags alphabetically by name
		ksort($action_tags);
		// Return array
		return $action_tags;
	}


	// ACTION TAGS: Determine if field has specific action tag
	public static function hasActionTag($action_tag, $misc)
	{
		if ($action_tag === null) return false;
        if ($misc === null) return false;
		// Explode all action tags into array
		$misc_array = explode(" ", $misc);
		return in_array($action_tag, $misc_array);
	}
	

	// ACTION TAGS: Determine if field has a @HIDDEN or @HIDDEN-SURVEY action tag
	public static function hasHiddenOrHiddenSurveyActionTag($action_tags, $project_id=null, $record=null, $event_id=null, $instrument=null, $instance=null)
	{
        if ($action_tags === null) return false;
		if (isinteger($project_id)) {
			$action_tags = Form::replaceIfActionTag($action_tags, $project_id, $record, $event_id, $instrument, $instance);
		}
		// Convert line breaks to spaces
		$action_tags = str_replace(array("\r", "\n", "\t"), array(" ", " ", " "), $action_tags);
		// Explode all action tags into array
		$action_tags_array = explode(" ", $action_tags);
		// @HIDDEN or @HIDDEN-SURVEY?
		return (in_array('@HIDDEN', $action_tags_array) || in_array('@HIDDEN-SURVEY', $action_tags_array));
	}


	// ACTION TAGS: Determine if field has a @HIDDEN-PDF action tag
	public static function hasHiddenPdfActionTag($action_tags, $project_id=null, $record=null, $event_id=null, $instrument=null, $instance=null)
	{
        if ($action_tags === null) return false;
        if (isinteger($project_id)) {
			$action_tags = Form::replaceIfActionTag($action_tags, $project_id, $record, $event_id, $instrument, $instance);
        }
		// Convert line breaks to spaces
		$action_tags = str_replace(array("\r", "\n", "\t"), array(" ", " ", " "), $action_tags);
		// Explode all action tags into array
		$action_tags_array = explode(" ", $action_tags);
		// @HIDDEN or @HIDDEN-SURVEY?
		return in_array('@HIDDEN-PDF', $action_tags_array);
	}
	

	// ACTION TAGS: Determine if field has a @HIDDEN or @HIDDEN-FORM action tag
	public static function hasHiddenOrHiddenFormActionTag($action_tags, $project_id=null, $record=null, $event_id=null, $instrument=null, $instance=null)
	{
        if ($action_tags === null) return false;
		if (isinteger($project_id)) {
			$action_tags = Form::replaceIfActionTag($action_tags, $project_id, $record, $event_id, $instrument, $instance);
		}
	    // Convert line breaks to spaces
		$action_tags = str_replace(array("\r", "\n", "\t"), array(" ", " ", " "), $action_tags);
		// Explode all action tags into array
		$action_tags_array = explode(" ", $action_tags);
		// @HIDDEN or @HIDDEN-SURVEY?
		return (in_array('@HIDDEN', $action_tags_array) || in_array('@HIDDEN-FORM', $action_tags_array));
	}


	// ACTION TAGS: Parse the Field Annotion attribute and return array of values for @MAXCHOICE action tag
	public static function parseMaxChoiceActionTag($field_annot="", $tag="@MAXCHOICE")
	{
		$maxChoices = array();
		if ($field_annot != "")
		{
			// Obtain the MAXCHOICE text for this field
			$maxChoiceText = Form::getValueInParenthesesActionTag($field_annot, $tag);
			if ($maxChoiceText != "") {
				foreach (explode(",", $maxChoiceText) as $thisVal) {
					list ($thisChoice, $thisAmount) = explode("=", $thisVal, 2);
					$thisChoice = trim($thisChoice)."";
					$thisAmount = trim($thisAmount)."";
					if ($thisChoice == "" || $thisAmount == "" || !is_numeric($thisAmount) || $thisAmount < 0) continue;
					$maxChoices[$thisChoice] = $thisAmount;
				}
			}
		}
		return $maxChoices;
	}
	

	// ACTION TAGS: For a field with a @MAXCHOICE action tag, return array of ONLY the choices that have already reached the max.
	public static function getMaxChoiceReached($field="", $currentEventId="", $tag="@MAXCHOICE")
	{
		global $Proj;
		// Parse the choice and their values
		$maxChoices = self::parseMaxChoiceActionTag($Proj->metadata[$field]['misc'], $tag);
		if (empty($maxChoices)) return array();
		// Get survey ID for this field's instrument (if exists)
        $form = $Proj->metadata[$field]['form_name'];
        $survey_id = isset($Proj->forms[$Proj->metadata[$field]['form_name']]['survey_id']) ? $Proj->forms[$Proj->metadata[$field]['form_name']]['survey_id'] : null;
		// Get the choices that we have reached the maximum
		$maxChoicesReached = array();
		if ($tag == "@MAXCHOICE") {
		    // @MAXCHOICE
			$sql = "select value, count(*) as saved from (
                        select distinct record, event_id, field_name, instance, value from ".\Records::getDataTable(PROJECT_ID)." 
                        where project_id = ".PROJECT_ID." and event_id = '".db_escape($currentEventId)."' 
                        and field_name = '".db_escape($field)."' and value in (".prep_implode(array_keys($maxChoices)).")
                    ) x
                    group by value";
        } else {
		    // If this instrument is not enabled as a survey, then this action tag doesn't even work, so return empty array
            if ($survey_id == null) return array();
			// @MAXCHOICE-SURVEY-COMPLETE    r.completion_time, p.participant_email
			$sql = "select value, count(*) as saved from (
                        select distinct d.record, d.event_id, d.field_name, d.instance, d.value
                        from ".\Records::getDataTable(PROJECT_ID)." d, redcap_surveys_participants p, redcap_surveys_response r
                        where d.project_id = ".PROJECT_ID." and d.event_id = '".db_escape($currentEventId)."' and d.field_name = '".db_escape($field)."'
                        and d.value in (".prep_implode(array_keys($maxChoices)).") and p.event_id = d.event_id and p.survey_id = $survey_id
                        and p.participant_id = r.participant_id and r.record = d.record and r.instance = ifnull(d.instance, 1)
                        and r.completion_time is not null
                    ) x
                    group by value";
        }
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if ($row['saved'] >= $maxChoices[$row['value']]) {
				$maxChoicesReached[] = $row['value'];
			}
		}
		// Add any "0" choices, which will not have data (just in case)
		foreach ($maxChoices as $thisCode=>$thisMax) {
			if ($thisMax."" === "0") {
				$maxChoicesReached[] = $thisCode;
			}
		}
		// Return array
		return $maxChoicesReached;
	}
	

	// ACTION TAGS: For a field with a @MAXCHOICE action tag, return array of ONLY the choices that have already reached the max.
	public static function hasReachedMaxChoiceForChoice($field="", $currentEventId="", $choiceValue="", $tag='@MAXCHOICE')
	{
		if ($choiceValue == "") return array();
		$choicesReached = self::getMaxChoiceReached($field, $currentEventId, $tag);
		return in_array($choiceValue, $choicesReached);
	}
	

	// ACTION TAGS: Find fields in POST array using @MAXCHOICE and @MAXCHOICE-SURVEY-COMPLETE action tags, check if they reached their max, and return any
	public static function hasReachedMaxChoiceInPostFields($post=array(), $fetched="", $currentEventId="")
	{
		global $Proj;
		// Get array of all fields utilizing maxchoice
		$maxChoiceFields = self::getMaxChoiceFields(array_keys($Proj->forms[$_GET['page']]['fields']), "@MAXCHOICE");
		$maxChoiceFields = array_merge($maxChoiceFields, self::getMaxChoiceFields(array_keys($Proj->forms[$_GET['page']]['fields']), "@MAXCHOICE-SURVEY-COMPLETE"));
		if (empty($maxChoiceFields)) return array();		
		$_GET['maxChoiceFieldsReached'] = array();
		// Build sql for data retrieval for checking if new data or if overwriting old data
		$current_data = array();
		$sql = "select field_name, value from ".\Records::getDataTable(PROJECT_ID)." where record = '" . db_escape($fetched) . "'
				and event_id = $currentEventId and project_id = " . PROJECT_ID .
				($Proj->hasRepeatingFormsEvents() ? " and instance ".($_GET['instance'] == '1' ? "is NULL" : "= ".$_GET['instance']) : "") .  
				" and field_name in (".prep_implode($maxChoiceFields).")";
		$q = db_query($sql);
		while ($row = db_fetch_array($q))
		{
			//Checkbox: Add data as array
			if ($Proj->isCheckbox($row['field_name'])) {
				$current_data[$row['field_name']][$row['value']] = $row['value'];
			//Non-checkbox fields: Add data as string
			} else {
				$current_data[$row['field_name']] = $row['value'];
			}
		}
		// Loop through POST fields
		foreach ($post as $field_name=>$value)
		{
			$post_key = $field_name;
			// Skip if blank value
			if ($value == '') continue;
			$reached = $is_checkbox = false;
			$chkval = '';
			// Reformat the fieldnames of any checkboxes
			if (substr($field_name, 0, 7) == '__chk__') {
				// Parse out the field name and the checkbox coded value
				list ($field_name, $chkval) = explode('_RC_', substr($field_name, 7), 2);
				$chkval = DataEntry::replaceDotInCheckboxCodingReverse($chkval);
				$is_checkbox = true;
			}
			// Skip if not maxchoice
			if (!in_array($field_name, $maxChoiceFields)) continue;
			// Because all GET/POST elements get HTML-escaped, we need to HTML-unescape them here
			$value = html_entity_decode($value, ENT_QUOTES);
			if (
				## OPTION 1: If data exists for this field (and it's not a checkbox), update the value
				(isset($current_data[$field_name]) && !$is_checkbox && $value !== $current_data[$field_name]) ||				
				## OR OPTION 3: If there is no data for this field (checkbox or non-checkbox)
				(((isset($chkval) && !isset($current_data[$field_name][$chkval]) && $is_checkbox) || (!isset($current_data[$field_name]) && !$is_checkbox)) 
					&& $value != '' && strpos($field_name, '___') === false)
			) {
			    // Does field have MAXCHOICE-SURVEY-COMPLETE or MAXCHOICE?
                $actionTag = strpos($Proj->metadata[$field_name]['misc'], '@MAXCHOICE-SURVEY-COMPLETE') !== false ? '@MAXCHOICE-SURVEY-COMPLETE' : '@MAXCHOICE';
				// If this choice for this field has been reached, then add to array to return
				$reached = self::hasReachedMaxChoiceForChoice($field_name, $currentEventId, $value, $actionTag);
				if ($reached) $_GET['maxChoiceFieldsReached'][] = $field_name;
			}
		}
		$_GET['maxChoiceFieldsReached'] = array_unique($_GET['maxChoiceFieldsReached']);
		// If empty, then just unset
		if (empty($_GET['maxChoiceFieldsReached'])) unset($_GET['maxChoiceFieldsReached']);
	}
	

	// ACTION TAGS: Get array of fields that have @MAXCHOICE action tag. 
	// Will check all fields in project or can check specific fields in $fields array provided.
	public static function getMaxChoiceFields($fields=array(), $actionTag="@MAXCHOICE")
	{
		global $Proj;
		$maxChoiceFields = array();
		if (empty($fields)) $fields = array_keys($Proj->metadata);
		$sql = "select field_name, misc from redcap_metadata where project_id = ".PROJECT_ID." 
				and field_name in (".prep_implode($fields).") and misc like '%$actionTag%'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {			
			$maxChoiceText = Form::getValueInParenthesesActionTag($row['misc'], $actionTag);
			if (!empty($maxChoiceText)) {
				$maxChoiceFields[] = $row['field_name'];
			}
		}
		return $maxChoiceFields;
	}
	

	// ACTION TAGS: Determine if field has a @READONLY action tag
	public static function disableFieldViaActionTag($action_tags, $isSurveyPage=false)
	{
        if ($action_tags === null) return false;
		// Convert line breaks to spaces
		$action_tags = str_replace(array("\r", "\n", "\t"), array(" ", " ", " "), $action_tags);
		// Explode all action tags into array
		$action_tags_array = explode(" ", $action_tags);
		// @READONLY
		if (in_array('@READONLY', $action_tags_array)) return true;
		// @READONLY-FORM
		if (!$isSurveyPage && in_array('@READONLY-FORM', $action_tags_array)) return true;
		// @READONLY-SURVEY
		if ($isSurveyPage && in_array('@READONLY-SURVEY', $action_tags_array)) return true;
		// Return false if we got tot his point
		return false;
	}
	

	// ACTION TAGS: Return the value after equals sign for certain action tags - allows quotes 
	// but they are not required (@WORDLIMIT, @CHARLIMIT, etc.)
	public static function getValueInActionTag($field_annotation, $actionTag="@WORDLIMIT")
	{
		if($field_annotation === null){
			return '';
		}

		// Obtain the quoted value via regex
		preg_match("/(".$actionTag."\s*=\s*)(([\"][^\"]+[\"])|(['][^']+['])|([\"]?[^\"]+[\"]?)|([']?[^']+[']?))/", $field_annotation, $matches);
		if (isset($matches[2]) && $matches[2] != '') {
			// Remove wrapping quotes
            $value = $matches[2];
            $wrappedInQuotes = (strpos($value, "'") === 0 || strpos($value, '"') === 0);
            // If not wrapped in quotes, remove any text occurring after whitespace (because it's not part of the action tag)
            if (!$wrappedInQuotes) {
                $value = preg_split('/[\\s]+/', $value);
                $value = $value[0];
            }
			$value = trim($value,"'");
            $value = trim($value,'"');
            $value = trim($value);
            if (!$wrappedInQuotes) {
                $value = explode(" ", $value, 2)[0]; // Reduce to only value if not wrapped in quotes
            }
		} else {
			$value = '';
		}
		// Return the value
		return $value;
	}
	

	// ACTION TAGS: Return the value inside quotes for certain action tags (@DEFAULT="?", @PLACEHOLDER='?', etc.)
	public static function getValueInQuotesActionTag($field_annotation, $actionTag="@DEFAULT")
	{
		// Obtain the quoted value via regex
		preg_match("/(".$actionTag."\s*=\s*)((\"[^\"]+\")|('[^']+'))/", $field_annotation, $matches);
		if (isset($matches[2]) && $matches[2] != '') {
			// Remove wrapping quotes
			$defaultValue = substr($matches[2], 1, -1);
		} else {
			$defaultValue = '';
		}
		// Return the value inside the quotes
		return $defaultValue;
	}

	// ACTION TAGS: Return the value inside parentheses for certain action tags - @MAXCHOICE(1=50,2=75)
	public static function getValueInParenthesesActionTag($string, $actionTag="@MAXCHOICE")
	{
		if (strpos($string, $actionTag) === false) return "";
		// REPLACE ANY STRINGS IN QUOTES WITH A PLACEHOLDER BEFORE DOING THE OTHER REPLACEMENTS:
		$lqp = new LogicQuoteProtector();
		$string = $lqp->sub($string);
		// Get string length
		$stringlen = strlen($string);
	    // Remove space between action tag and first parentheses
	    $string = preg_replace("/(".$actionTag.")(\s*)(\()/", "$1$3", $string);
	    // Find beginning of the parentheses
		$begin = strpos($string, $actionTag."(")+strlen($actionTag."(")-1;
		// Loop through each character until we hit an equal number of opening/closing parentheses
        $openParen = 0;
        for ($k = $begin; $k < $stringlen; $k++) {
            $thisChar = $string[$k];
            if ($thisChar == "(") $openParen++;
            elseif ($thisChar == ")") $openParen--;
            if ($openParen == 0) {
                // We found the closing parenthesis, so return only what is inside the outer parentheses
                $string = trim(substr($string, $begin+1, $k-$begin-1));
				// UNDO THE REPLACEMENT BEFORE EVALUATING THE EXPRESSION
				$string = $lqp->unsub($string);
                return $string;
            }
        }
		// We couldn't find an opening/closing parentheses pair
		return "";
	}

	// ACTION TAGS: Replace the evaluated value of @IF in a field's Field Annotation
	public static function replaceIfActionTag($misc, $project_id, $record, $event_id, $instrument, $instance)
	{
        if ($misc == null || $misc == '') return "";
        // Remove comments
        $misc = LogicParser::removeCommentsAndSanitize($misc);
        // Pre-format the logic for easier parsing below
        $misc = preg_replace("/@IF\s*\(\s*/", "@IF(", $misc);
        $i = 0;
		while (preg_match("/@IF\(/", $misc) && $i < 10000) {
			$ifText = Form::getValueInParenthesesActionTag($misc, "@IF");
			if ($ifText != "") {
				$evaldIfText = Form::evaluateIfActionTag($ifText, $project_id, $record, $event_id, $instrument, $instance);
				// Replace the @IF() expression with the evaluated one
				$misc = preg_replace("/@IF\(\s*" . preg_quote($ifText, '/') . "\s*\)/", $evaldIfText, $misc);
			}
            $i++;
		}
        // If end up with 2 quotes, then revert to blank string
        if ($misc == '""' || $misc == "''") $misc = "";
        // Return new misc value
        return $misc;
	}

	// ACTION TAGS: Return the evaluated value inside @IF(condition,val1,val2)
    // $ifText should be provided as all the text inside a single @IF(...)
	public static function evaluateIfActionTag($ifText, $project_id, $record, $event_id, $instrument, $instance)
	{
        if (trim($ifText) == '') return '';
		// Remove all text inside quotes for easier parsing
		$lqp = new LogicQuoteProtector();
		$ifText = $lqp->sub($ifText);
		// Do more pre-formatting for easier parsing
		$ifText = str_replace(["\r\n", "\r", "\n", "\t", "@IF("], [" ", " ", " ", " ", "\nif("], $ifText);
		$ifText = LogicTester::convertIfStatement("if(".$ifText.")", 0, "\n?{RCT}", "\n:{RCT}", "IF-RC");
		// Loop through each line and add line break in front of the first closing parenthesis on each line that does not have an opening parenthesis.
		// We're essentially doing this so that the True/False parts of the IF are isolating on their own lines, after which we can replace them and then un-replace them in the end.
		$ifText2 = "";
		foreach (explode("\n", $ifText) as $curstr) {
			// The entire IF statement MIGHT be in this_string. Check if it is (commas and parens in correct order).
			$curstr_len = strlen($curstr);
			$nested_paren_count = 0;
			// Loop through the string letter by letter
			for ($i = 0; $i < $curstr_len; $i++) {
				// Get current letter
				$letter = substr($curstr, $i, 1);
				// Perform logic based on current letter and flags already set
				if ($letter == "(") {
					// Increment the count of how many nested parentheses we're inside of
					$nested_paren_count++;
				} elseif ($letter == ")" && $nested_paren_count > 0) {
					// We just left a nested parenthesis, so reduce count by 1 and keep looping
					$nested_paren_count--;
				} elseif ($letter == ")" && $nested_paren_count == 0) {
					$curstr = substr($curstr, 0, $i) . "\n" . substr($curstr, $i);
					break;
				}
			}
			$ifText2 .= "\n".$curstr;
		}
		$ifText = trim($ifText2);
		// Go through text line by line and replace all True/False conditions with numbers that correspond to keys in a replacement array
		$ifTextReplacements = [];
		$ifTextKey = 0;
		$ifText2 = "";
		foreach (explode("\n", $ifText) as $curstr) {
			$beginsQM = (strpos($curstr, "?{RCT}") === 0);
			if ($beginsQM || strpos($curstr, ":{RCT}") === 0) {
				$locFirstSpace = strpos($curstr, " ");
				$trueFalsePart = trim(substr($curstr, $locFirstSpace));
				if (trim($trueFalsePart) != "") {
					// Set replacement for line and place in array
					$curstr = ($beginsQM ? "?{RCT}" : ":{RCT}") . " \"$ifTextKey\"";
					$ifTextReplacements[$ifTextKey] = $trueFalsePart;
					$ifTextKey++;
				}
			}
			$ifText2 .= "\n".$curstr;
		}
		// Un-replace the question marks and remove the line breaks
		$ifText = "if" . trim(str_replace(["?{RCT}", ":{RCT}", "\n", "IF-RC"], [",", ",", " ", "if"], $ifText2));
		$ifText = str_replace("ifif", "if", $ifText);
		$ifText = preg_replace("/\s+/", " ", $ifText);
		// Un-replace the text inside quotes
		$ifText = $lqp->unsub($ifText);
        // If record doesn't exist yet, then submit empty array of redcap_data
        $record_data = null;
        if ($GLOBALS['hidden_edit'] == 0 && PAGE != 'PdfController:index') {
            $record_data = [" "=>Records::getDefaultValues($project_id)];
        }
		// Now let's evaluate the expression to return a single value that matches a key in $ifTextReplacements
		$Proj = new Project($project_id);
		$evaluatedVal = REDCap::evaluateLogic($ifText, $project_id, $record, $event_id, $instance, ($Proj->isRepeatingForm($event_id, $instrument) ? $instrument : ""), $instrument, $record_data, true, $GLOBALS['hidden_edit']);
        $thisReturnVal = "";
		if ($evaluatedVal != null) {
			$evaluatedVal = (int)$evaluatedVal;
			if (isset($ifTextReplacements[$evaluatedVal])) {
				$thisReturnVal = $ifTextReplacements[$evaluatedVal];
				// Un-replace the text inside quotes one more time to catch the return values that had quotes
				$thisReturnVal = $lqp->unsub($thisReturnVal);
			}
		}
		// Return the evaluated value of the @IF()
		return $thisReturnVal;
	}


	// @DOWNLOAD-COUNT ACTION TAG: Get all fields that have @DOWNLOAD-COUNT referencing a specific File Upload field
	public static function getDownloadCountTriggerFields($project_id, $uploadField)
	{
        if (!isinteger($project_id)) return [];
	    $tag = '@DOWNLOAD-COUNT';
		$Proj = new Project($project_id);
		// Use cache
		if (is_array(Project::$download_count_fields) && isset(Project::$download_count_fields[$project_id][$uploadField])) {
			$fields = Project::$download_count_fields[$project_id][$uploadField];
        }
		// Find fields and add to cache
		else {
			$fields = [];
			foreach ($Proj->metadata as $field => $attr) {
				if ($attr['misc'] !== null && strpos($attr['misc'], $tag) !== false) {
					$downloadCountContent = Form::getValueInParenthesesActionTag($attr['misc'], $tag);
					$downloadCountContent = str_replace(["[", "]", " "], ["", "", ""], $downloadCountContent);
					if (isset($Proj->metadata[$downloadCountContent]) && $downloadCountContent == $uploadField) {
						$fields[] = $field;
					}
				}
			}
			Project::$download_count_fields[$project_id][$uploadField] = $fields;
        }
		// Return the array of fields
		return $fields;
	}
	

	// ACTION TAGS: Create regex string to detect all action tags being used in the Field Annotation
	public static function getActionTagMatchRegex()
	{
		$action_tags_regex_quote = array();
		foreach (self::getActionTags() as $this_trig=>$this_descrip) {
			$action_tags_regex_quote[] = preg_quote($this_trig);
		}
		return "/(" . implode("|", $action_tags_regex_quote) .")($|[\s*\(]|[^(\-)])/";
	}
	

	// ACTION TAGS: Create regex string to detect all action tags being used in the Field Annotation
	// for ONLINE DESIGNER only (this will only be a minority of the action tags)
	public static function getActionTagMatchRegexOnlineDesigner()
	{
		$action_tags_regex_quote = array();
		$od_tags = array_keys(self::getActionTags(true));
		usort($od_tags, function($a, $b) {
			// Sort by length descending to prioritize matching of longer tags
			// as otherwise @TAG will match @TAG-LONGER and the latter will be "lost".
			return strlen($b) - strlen($a); 
		});
		foreach ($od_tags as $this_trig) {
			$action_tags_regex_quote[] = preg_quote($this_trig);
		}
		return "/(" . implode("|", $action_tags_regex_quote) .")($|[^\-])/";
	}

	// ACTION TAGS: Create regex string to detect all action tags, including those from EMs
	public static function getActionTagMatchRegexOnlineDesignerInformational($pid = null)
	{
		$all_tags = Form::getActionTags();
		$modules_tags = $pid == null ? [] : \ExternalModules\ExternalModules::getActionTags($pid);
		foreach ($modules_tags as $prefix => $module_tags) {
			foreach ($module_tags as $tag) {
				$all_tags[$tag['tag']] = $tag['description'];
			}
		}
		$action_tags_regex_quote = array();
		$all_tags = array_keys($all_tags);
		usort($all_tags, function($a, $b) {
			// Sort by length descending to prioritize matching of longer tags
			// as otherwise @TAG will match @TAG-LONGER and the latter will be "lost".
			return strlen($b) - strlen($a); 
		});
		foreach ($all_tags as $this_trig) {
			$action_tags_regex_quote[] = preg_quote($this_trig);
		}
		return "/(" . implode("|", $action_tags_regex_quote) .")($|[^\-])/";
	}


	// Render data history log
	public static function renderDataHistoryLog($record, $event_id, $field_name, $instance)
	{
		global $lang, $require_change_reason, $Proj, $missingDataCodes, $user_rights;
        // Set field values
        $field_type = $Proj->metadata[$field_name]['element_type'];
        $field_form = $Proj->metadata[$field_name]['form_name'];
        $field_val_type = $Proj->metadata[$field_name]['element_validation_type'];
        // Version history enabled
        $version_history_enabled = ($field_type == 'file' && $field_val_type != 'signature' && Files::fileUploadVersionHistoryEnabledProject(PROJECT_ID));
		// Do URL decode of name (because it original was fetched from query string before sent via Post)
		$record = urldecode($record);
		// Set $instance
		$instance = is_numeric($instance) ? (int)$instance : 1;
		// Get data history log
		$time_value_array = array_values(self::getDataHistoryLog($record, $event_id, $field_name, $instance));
		// Get highest array key
		$max_dh_key = count($time_value_array)-1;
		// Set file download page
        $file_download_page = APP_PATH_WEBROOT . "DataEntry/file_download.php?pid=".PROJECT_ID."&page=" . $Proj->metadata[$field_name]['form_name']
                            . "&s=&record=$record&event_id=$event_id&field_name=$field_name&instance=$instance";
        $file_delete_page = APP_PATH_WEBROOT . "DataEntry/file_delete.php?pid=".PROJECT_ID."&page=" . $Proj->metadata[$field_name]['form_name'];
		// Loop through all rows and add to $rows
		$rows = "";
        foreach ($time_value_array as $key=>$row)
		{
		    $isLastRow = ($max_dh_key == $key);
			$rows .= RCView::tr(array('id'=>($isLastRow ? 'dh_table_last_tr' : '')),
						RCView::td(array('class'=>'data nowrap', 'style'=>'padding:5px 8px;text-align:center;width:170px;'),
							DateTimeRC::format_ts_from_ymd($row['ts'], true, true) .
							// Display "lastest change" label for the last row
							($isLastRow ? RCView::div(array('style'=>'color:#C00000;font-size:11px;padding-top:5px;'), $lang['dataqueries_277']) : '')
						) .
						RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;text-align:center;width:100px;word-wrap:break-word;'),
							$row['user']
						) .
						RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;'),
							($row['missing_data_code'] == ""
                                ? $row['value']
                                : RCView::i(array('style'=>'color:#777;'), $lang['missing_data_12']).RCView::br().$missingDataCodes[$row['missing_data_code']] . " (".$row['missing_data_code'].")"
                            )
						) .
                        ($version_history_enabled
                            ?   RCView::td(array('class'=>'data text-center', 'style'=>'color:#000088;border:1px solid #ddd;padding:3px 8px;width:60px;'),
                                    ($row['doc_version'] != '' ? "V".$row['doc_version'] : "")
                                ) .
                                RCView::td(array('class'=>'data text-left', 'style'=>'border:1px solid #ddd;padding:3px 8px;width:200px;'),
									($row['missing_data_code'] != ""
                                        ? ""
                                        : ($row['doc_deleted'] != ""
                                            // Show deletion time
                                            ? RCView::i(array('style'=>'color:#777;font-size:12px;'),
                                                $lang['data_entry_465'] . " " .
                                                DateTimeRC::format_ts_from_ymd($row['doc_deleted'], false, false) .
                                                "<br>" . $lang['form_renderer_06'] . " " . $row['user']
                                            )
                                            // Show download/delete buttons
                                            : RCView::button(array('class'=>'float-start btn btn-xs btn-primaryrc fs12', 'onclick'=>"window.open('$file_download_page&doc_id_hash=".Files::docIdHash($row['doc_id'])."&id=".$row['doc_id']."&doc_version=".$row['doc_version']."&doc_version_hash=".Files::docIdHash($row['doc_id']."v".$row['doc_version'])."','_blank');"),
                                                '<i class="fas fa-download"></i> '.$lang['docs_58']
                                              ) .
                                              (UserRights::hasDataViewingRights($user_rights['forms'][$field_form], "read-only") ? "" : // Don't display "delete" button if user has read-only rights
                                                  RCView::a(array('href'=>'javascript:;', 'class'=>'mt-1 fs11 float-end', 'style'=>'color:#A00000;', 'onclick'=>'deleteDocumentConfirm('.$row['doc_id'].',"'.$field_name.'","'.$record.'",'.$event_id.','.$instance.',"'.$file_delete_page.'","'.($isLastRow ? '' : $row['doc_version']).'","'.($isLastRow ? '' : Files::docIdHash($row['doc_id']."v".$row['doc_version'])).'");'),
                                                    '<i class="far fa-trash-alt"></i> '.$lang['global_19']
                                                  )
                                              )
                                        )
                                    )
                                )
                            : ""
                        ) .
						($require_change_reason
							? 	RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;'),
									$row['change_reason']
								)
							: 	""
						)
					);
		}
		// If no data history log exists yet for field, give message
		if (empty($time_value_array))
		{
			$rows .= RCView::tr('',
						RCView::td(array('class'=>'data', 'colspan'=>($require_change_reason ? '4' : '3'), 'style'=>'border-top: 1px #ccc;padding:6px 8px;text-align:center;'),
							$lang['data_history_05']
						)
					);
		}
		// Output the table headers as a separate table (so they are visible when scrolling)
		$table = RCView::table(array('class'=>'form_border', 'style'=>'table-layout:fixed;border:1px solid #ddd;width:95%;'),
					RCView::tr('',
						RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;width:170px;'),
                            ($version_history_enabled ? $lang['data_history_06'] : $lang['data_history_01'])
						) .
						RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;width:100px;'),
							$lang['global_17']
						) .
						RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;'),
                            ($version_history_enabled ? $lang['data_entry_466'] : $lang['data_history_03'])
						) .
                        ($version_history_enabled
                            ?   RCView::td(array('class'=>'label_header fs11 text-center wrap', 'style'=>'padding:5px 8px;width:60px;'),
                                    $lang['data_entry_458']
                                ) .
                                RCView::td(array('class'=>'label_header fs11 text-center', 'style'=>'padding:5px 8px;width:200px;'),
                                    $lang['data_entry_467']
                                )
                            : ""
                        ) .
						($require_change_reason
							? 	RCView::td(array('class'=>'label_header fs11', 'style'=>'padding:5px 8px;'),
									$lang['data_history_04']
								)
							: 	""
						)
					)
				);
		// Output table html
		$table .= RCView::div(array('id'=>'data_history3', 'style'=>'overflow-y:scroll;'),
					RCView::table(array('id'=>'dh_table', 'class'=>'form_border', 'style'=>'table-layout:fixed;border:1px solid #ddd;width:97%;'),
						$rows
					)
				  );
		// Return html
		return $table;
	}


	// Get log of data history (returns in chronological ASCENDING order)
	public static function getDataHistoryLog($record, $event_id, $field_name, $instance=1)
	{
		global $double_data_entry, $user_rights, $longitudinal, $Proj, $lang, $missingDataCodes;

		// Set field values
		$field_type = $Proj->metadata[$field_name]['element_type'];
        $field_val_type = $Proj->metadata[$field_name]['element_validation_type'];

		// Version history enabled
        $version_history_enabled = ($field_type == 'file' && $field_val_type != 'signature' && Files::fileUploadVersionHistoryEnabledProject(PROJECT_ID));
		
		// Does user have access to this field's form? If not, do not display the field's data.
		$hasFieldViewingRights = (isset($user_rights) && !UserRights::hasDataViewingRights($user_rights['forms'][$Proj->metadata[$field_name]['form_name']], "no-access"));

		// Determine if a multiple choice field (do not include checkboxes because we'll used their native logging format for display)
		$isMC = ($Proj->isMultipleChoice($field_name) && $field_type != 'checkbox');
		if ($isMC) {
			$field_choices = parseEnum($Proj->metadata[$field_name]['element_enum']);
		}

		// Format the field_name with escaped underscores for the query
		$field_name_q = str_replace("_", "\\_", $field_name);
		
		// REPEATING FORMS/EVENTS: Check for "instance" number if the form is set to repeat
		$instanceSql = "and data_values not like '[instance = %'";
		$isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($event_id, $Proj->metadata[$field_name]['form_name']);
		if ($isRepeatingFormOrEvent) {
			// Set $instance
			$instance = is_numeric($instance) ? (int)$instance : 1;
			if ($instance > 1) {
				$instanceSql = "and data_values like '[instance = $instance]%'";
			}
		}

		// Adjust record name for DDE
		if ($double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) {
			$record .= "--" . $user_rights['double_data'];
		}

		// Default
		$time_value_array = array();
		$arm = isset($Proj->eventInfo[$event_id]) ? $Proj->eventInfo[$event_id]['arm_num'] : getArm();

		// Retrieve history and parse field data values to obtain value for specific field
		$sql = "SELECT user, timestamp(ts) as ts, data_values, description, change_reason, event 
                FROM ".Logging::getLogEventTable(PROJECT_ID)." WHERE project_id = " . PROJECT_ID . " and pk = '" . db_escape($record) . "'
				and (
				(
					(event_id = $event_id " . ($longitudinal ? "" : "or event_id is null") . ")
					and legacy = 0 $instanceSql
					and
					(
						(
							event in ('INSERT', 'UPDATE')
							and description in ('Create record', 'Update record', 'Update record (import)',
								'Create record (import)', 'Merge records', 'Update record (API)', 'Create record (API)',
								'Update record (DTS)', 'Update record (DDP)', 'Erase survey responses and start survey over',
								'Update survey response', 'Create survey response', 'Update record (Auto calculation)',
								'Update survey response (Auto calculation)', 'Delete all record data for single form',
								'Delete all record data for single event', 'Update record (API) (Auto calculation)')
							and (data_values like '%\\n{$field_name_q} = %' or data_values like '{$field_name_q} = %' 
								or data_values like '%\\n{$field_name_q}(%) = %' or data_values like '{$field_name_q}(%) = %')
						)
						or
						(event = 'DOC_DELETE' and data_values = '$field_name')
						or
						(event = 'DOC_UPLOAD' and (data_values like '%\\n{$field_name_q} = %' or data_values like '{$field_name_q} = %' 
													or data_values like '%\\n{$field_name_q}(%) = %' or data_values like '{$field_name_q}(%) = %'))
					)
				)
				or 
				(event = 'DELETE' and description like 'Delete record%' and (event_id is null or event_id in (".prep_implode(array_keys($Proj->events[$arm]['events'])).")))
				)
				order by log_event_id";
		$q = db_query($sql);
		// Loop through each row from log_event table. Each will become a row in the new table displayed.
        $version_num = 0;
        $this_version_num = "";
        $rows = array();
        $deleted_doc_ids = array();
        while ($row = db_fetch_assoc($q))
        {
            $rows[] = $row;
            // For File Version History for file upload fields, get doc_id all any that were deleted
            if ($version_history_enabled) {
                $value = html_entity_decode($row['data_values'], ENT_QUOTES);
                foreach (explode(",\n", $value) as $this_piece) {
                    $doc_id = self::dataHistoryMatchLogString($field_name, $field_type, $this_piece);
                    if (is_numeric($doc_id)) {
                        $doc_delete_time = Files::wasEdocDeleted($doc_id);
                        if ($doc_delete_time) {
                            $deleted_doc_ids[$doc_id] = $doc_delete_time;
                        }
                    }
                }
            }
        }
        // Loop through all rows
		foreach ($rows as $row)
		{
			// If the record was deleted in the past, then remove all activity before that point
			if ($row['event'] == 'DELETE') {
				$time_value_array = array();
                $version_num = 0;
				continue;
			}
			// Flag to denote if found match in this row
			$matchedThisRow = false;
			// Get timestamp
			$ts = $row['ts'];
			// Get username
			$user = $row['user'];
			// Decode values
			$value = html_entity_decode($row['data_values'], ENT_QUOTES);
            // Default return string
            $this_value = "";
            // Split each field into lines/array elements.
            // Loop to find the string match
            foreach (explode(",\n", $value) as $this_piece)
            {
                $isMissingCode = false;
                // Does this line match the logging format?
                $matched = self::dataHistoryMatchLogString($field_name, $field_type, $this_piece);
                if ($matched !== false || ($field_type == "file" && ($this_piece == $field_name || strpos($this_piece, "$field_name = ") === 0)))
                {
                    // Set flag that match was found
                    $matchedThisRow = true;
                    // File Upload fields
                    if ($field_type == "file")
                    {
						if (isset($missingDataCodes[$matched])) {
							// Set text
							$this_value = $matched;
							$doc_id = null;
							$this_version_num = "";
							$isMissingCode = true;
						} elseif ($matched === false || $matched == '') {
                            // For File Version History, don't show separate rows for deleted files
                            if ($version_history_enabled) continue 2;
                            // Deleted
                            $doc_id = null;
                            $this_version_num = "";
                            // Set text
                            $this_value = RCView::span(array('style'=>'color:#A00000;'), $lang['docs_72']);
                        } elseif (is_numeric($matched)) {
                            // Uploaded
                            $doc_id = $matched;
                            $doc_name = Files::getEdocName($doc_id);
                            $version_num++;
                            $this_version_num = $version_num;
                            // Set text
                            $this_value = RCView::span(array('style'=>'color:green;'),
                                            $lang['data_import_tool_20']
                                            ). " - \"{$doc_name}\"";
                        }
                        break;
                    }
                    // Stop looping once we have the value (except for checkboxes)
                    elseif ($field_type != "checkbox")
                    {
                        $this_value = $matched;
                        break;
                    }
                    // Checkboxes may have multiple values, so append onto each other if another match occurs
                    else
                    {
                        $this_value .= $matched . "<br>";
                    }
                }
            }

            // If a multiple choice question, give label AND coding
            if ($isMC && $this_value != "")
            {
                if (isset($missingDataCodes[$this_value])) {
					$this_value = decode_filter_tags($missingDataCodes[$this_value]) . " ($this_value)";
                } else {
					$this_value = decode_filter_tags($field_choices[$this_value]) . " ($this_value)";
				}
            }

			// Add to array (if match was found in this row)
			if ($matchedThisRow) {			
				// If user does not have privileges to view field's form, redact data
				if (!$hasFieldViewingRights) {
					$this_value = "<code>".$lang['dataqueries_304']."</code>";
				} elseif ($field_type != "file") {
					$this_value = nl2br(htmlspecialchars(br2nl(label_decode($this_value)), ENT_QUOTES));
				}
				// Set array key as timestamp + extra digits for padding for simultaneous events
				$key = strtotime($ts)*100;
				// Ensure that we don't overwrite existing logged events
				while (isset($time_value_array[$key.""])) $key++;
				// Display missing data code?
				$returningMissingCode = (isset($missingDataCodes[$this_value]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$field_name]['misc']));
				// Add to array
				$time_value_array[$key.""] = array( 'ts'=>$ts, 'value'=>$this_value, 'user'=>$user, 'change_reason'=>nl2br($row['change_reason']??""),
                                                    'doc_version'=>$this_version_num, 'doc_id'=>(isset($doc_id) ? $doc_id : null),
                                                    'doc_deleted'=>(isset($doc_id) && isset($deleted_doc_ids[$doc_id]) ? $deleted_doc_ids[$doc_id] : ""),
                                                    'missing_data_code'=>($returningMissingCode ? $this_value : ''));
			}
		}

        // Fixed: Entries were displayed twice for secondary ID field
        $time_value_array = array_unique($time_value_array,SORT_REGULAR);

		// Sort by timestamp
		ksort($time_value_array);
		// Return data history log
		return $time_value_array;
	}


	// Determine if string matches REDCap logging format (based upon field type)
	public static function dataHistoryMatchLogString($field_name, $field_type, $string)
	{
		// If matches checkbox logging
		if ($field_type == "checkbox" && substr($string, 0, strlen("$field_name(")) == "$field_name(") // && preg_match("/^($field_name\()([a-zA-Z_0-9])(\) = )(checked|unchecked)$/", $string))
		{
			return $string;
		}
		// If matches logging for all fields (excluding checkboxes)
		elseif ($field_type != "checkbox" && substr($string, 0, strlen("$field_name = '")) == "$field_name = '")
		{
			// Remove apostrophe from end (if exists)
			if (substr($string, -1) == "'") $string = substr($string, 0, -1);
			$value = substr($string, strlen("$field_name = '"));
			return ($value === false ? '' : $value);
		}
		// Did not match this line
		else
		{
			return false;
		}
	}


	// Parse the element_enum column into the 3 slider labels (if only 1 assume Left; if 2 asssum Left&Right)
	public static function parseSliderLabels($element_enum)
	{
		// Explode into array, where strings should be delimited with pipe |
		$slider_labels  = array();
		$slider_labels2 = array('left'=>'','middle'=>'','right'=>'');
		foreach (explode("|", $element_enum, 3) as $label)
		{
			$slider_labels[] = trim($label);
		}
		// Set keys
		switch (count($slider_labels))
		{
			case 1:
				$slider_labels2['left']   = $slider_labels[0];
				break;
			case 2:
				$slider_labels2['left']   = $slider_labels[0];
				$slider_labels2['right']  = $slider_labels[1];
				break;
			case 3:
				$slider_labels2['left']   = $slider_labels[0];
				$slider_labels2['middle'] = $slider_labels[1];
				$slider_labels2['right']  = $slider_labels[2];
				break;
		}
		// Return array
		return $slider_labels2;
	}


	// Get all options for drop-down displaying all project fields
	public static function getFieldDropdownOptions($removeCheckboxFields=false, $includeMultipleChoiceFieldsOnly=false, $includeDAGoption=false, 
												   $includeEventsOption=false, $limitToValidationType=null, $addBlankOption=true, 
												   $addFormLabelDividers=true, $alsoIncludeRecordIdField=false, $limitToFieldType=null,
                                                   $overrideBlankOptionText=null, $includeSqlFieldsInMCFields=false)
	{
		global $Proj, $lang;
		$rc_fields = array();
		// Set array with initial "select a field" option
		if ($addBlankOption) {
			$rc_fields[''] = ($overrideBlankOptionText == null) ? '-- '.$lang['random_02'].' --' : $overrideBlankOptionText;
		}
		// Add the events field (if specified)
		if ($includeEventsOption) {
			$rc_fields[DataExport::LIVE_FILTER_EVENT_FIELD] = '['.$lang['global_45'].']';
		}
		// Add the DAG field (if specified)
		if ($includeDAGoption) {
			$rc_fields[DataExport::LIVE_FILTER_DAG_FIELD] = '['.$lang['global_22'].']';
		}
		// Set format of valtype param to array
		if ($limitToValidationType !== null && !is_array($limitToValidationType)) {
			$limitToValidationType = array($limitToValidationType);
        }
		// Set format of fieldtype param to array
		if (!empty($limitToFieldType) && !is_array($limitToFieldType)) {
			$limitToFieldType = array($limitToFieldType);
		}
		// Build an array of drop-down options listing all REDCap fields
		foreach ($Proj->metadata as $this_field=>$attr1) {
			// Add the record ID field?
			if (!($alsoIncludeRecordIdField && $this_field == $Proj->table_pk)) {
				// Skip descriptive fields
				if ($attr1['element_type'] == 'descriptive') continue;
				// Skip checkbox fields if flag is set
				if ($removeCheckboxFields && $attr1['element_type'] == 'checkbox') continue;
				// Skip non-multiple choice fields, if specified
				if ($includeMultipleChoiceFieldsOnly && !$Proj->isMultipleChoice($this_field)) {
                    if (!($includeSqlFieldsInMCFields && $Proj->isSqlField($this_field))) {
						continue;
                    } elseif (!$includeSqlFieldsInMCFields) {
                        continue;
                    }
				}
				// If limiting fields to a specific validation type(s), then exclude all others
				if ($limitToValidationType !== null && !in_array($attr1['element_validation_type'], $limitToValidationType)) continue;
				// If limiting fields to a specific field type(s), then exclude all others
				if (!empty($limitToFieldType) && !in_array($attr1['element_type'], $limitToFieldType)) continue;
			}
			// Add to fields/forms array. Get form of field.
			$this_form_label = $Proj->forms[$attr1['form_name']]['menu'];
			// Clean the label
			$attr1['element_label'] = trim(str_replace(array("\r\n", "\n"), array(" ", " "), strip_tags($attr1['element_label']."")));
			// Truncate label if long
			if (mb_strlen($attr1['element_label']) > 65) {
				$attr1['element_label'] = trim(mb_substr($attr1['element_label'], 0, 47)) . "... " . trim(mb_substr($attr1['element_label'], -15));
			}
			if ($addFormLabelDividers) {
				$rc_fields[$this_form_label][$this_field] = "$this_field \"{$attr1['element_label']}\"";
			} else {
				$rc_fields[$this_field] = "$this_field \"{$attr1['element_label']}\"";
			}
		}
		// Return all options
		return $rc_fields;
	}


	// Return boolean if a calc field's equation in Draft Mode is being changed AND that field contains some data
	public static function changedCalculationsWithData()
	{
		global $Proj, $status;
		// On error, return false
		if ($status < 1 || empty($Proj->metadata_temp)) return false;
		// Add field to array if has a calculation change
		$calcs_changed = array();
		// Loop through drafted changes
		foreach ($Proj->metadata_temp as $this_field=>$attr1) {
			// Skip non-calc fields
			if ($attr1['element_type'] != 'calc') continue;
			// If field does not yet exist, then skip
			if (!isset($Proj->metadata[$this_field])) continue;
			// Compare the equation for each
			if (trim(label_decode($attr1['element_enum'])) != trim(label_decode($Proj->metadata[$this_field]['element_enum']))) {
				$calcs_changed[] = $this_field;
			}
		}
		// Return false if no calculations changed
		if (empty($calcs_changed)) return false;
		// Query to see if any data exists for any of these changed calc fields
		$sql = "select 1 from ".\Records::getDataTable(PROJECT_ID)." where project_id = ".PROJECT_ID."
				and field_name in (".prep_implode($calcs_changed).") and value != '' limit 1";
		$q = db_query($sql);
		// Return true if any calc fields that were changed have data in them
		return (db_num_rows($q) > 0);
	}


	// Add web service values into cache table
	public static function addWebServiceCacheValues($project_id, $service, $category, $value, $label, $checkCache=true)
	{
		// First, check if it's already in the table. If so, return false
		if ($checkCache && self::getWebServiceCacheValues($project_id, $service, $category, $value) != '') {
			return false;
		}
		// Add to table
		$sql = "insert into redcap_web_service_cache (project_id, service, category, value, label)
				values ($project_id, '".db_escape($service)."', '".db_escape($category)."', '".db_escape($value)."', '".db_escape($label)."')";
		$q = db_query($sql);
		return db_insert_id();
	}


	// Obtain web service label from cache table of one item
	public static function getWebServiceCacheValues($project_id, $service, $category, $value)
	{
		// If value is blank, then return blank
		if ($value == '') return '';
		// Query table
		$sql = "select label from redcap_web_service_cache where project_id = $project_id and
				service = '".db_escape($service)."' and category = '".db_escape($category)."' and value = '".db_escape($value)."'";
		$q = db_query($sql);
		if (db_num_rows($q)) {
		    return db_result($q, 0);
        } else {
		    // If value has not had its label cached in the db table, then see if it has been cached for another project. If so, add it to this one.
			$sql = "select label from redcap_web_service_cache where 
                    service = '".db_escape($service)."' and category = '".db_escape($category)."' and value = '".db_escape($value)."' limit 1";
			$q = db_query($sql);
			// Label is not cached by any other project, so return blank value
			if (!db_num_rows($q)) return '';
			$label = db_result($q, 0);
            // Place value into cache table
			Form::addWebServiceCacheValues($project_id, $service, $category, $value, $label, false);
			// Return new label from other project
			return $label;
        }
	}


	// Obtain web service label from cache table of one item
	public static function getWebServiceCacheValuesBulk($project_id, $limitFields=array())
	{
		// Get fields using web service
		$fieldValuesLabels = $services = $categories = array();
		$limit = !empty($limitFields);
		$Proj = new Project($project_id);
		foreach ($Proj->metadata as $field=>$attr) {
			if ($limit && !in_array($field, $limitFields)) continue;
			if ($attr['element_type'] == 'text' && $attr['element_enum'] != '' && strpos($attr['element_enum'], ":") !== false) {
				// Get the name of the name of the web service API and the category (ontology) name
				list ($autosuggest_service, $autosuggest_cat) = explode(":", $attr['element_enum'], 2);
				$services[$autosuggest_service][$autosuggest_cat][$field] = array();
				$categories[] = $autosuggest_cat;
			}
		}
		// Query table
		$sql = "select service, category, value, label from redcap_web_service_cache where project_id = $project_id
				and service in (".prep_implode(array_keys($services)).") and category in (".prep_implode($categories).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Add this value/label to ALL fields using this ontology
			foreach (array_keys($services[$row['service']][$row['category']]) as $this_field) {
				$fieldValuesLabels[$this_field][$row['value']] = $row['label'];
			}
		}
		// Return array of fields with values/labels
		return $fieldValuesLabels;
	}


	// Perform server-side validation
	public static function serverSideValidation($postValues=array(), $sufValueJustChanged=false)
	{
		global $Proj, $fetched, $missingDataCodes;
        $secondary_pk = $Proj->project['secondary_pk'];
		$Proj_metadata = $Proj->getMetadata();
		// Set array to collect any errors in server side validation
		$errors = array();
		// Create array of all field validation types and their attributes
		$valTypes = getValTypes();
		
		//get missing data codes
		$missingDataCodeKeys=array_keys($missingDataCodes);
				
		// Loop through submitted fields
		foreach ($postValues as $field=>$val)
		{
			// Make sure this is a real field, first
			if (!isset($Proj_metadata[$field])) continue;
			// Skip the record ID field
			if ($field == $Proj->table_pk) continue;
			// If a blank value then skip
			if ($val == '' || in_array($val, $missingDataCodeKeys)) continue;
			// Get validation type
			$val_type = $Proj_metadata[$field]['element_validation_type'];
			// If field is multiple choice field, then validate its value
			if ($Proj->isMultipleChoice($field) || $Proj_metadata[$field]['element_type'] == 'sql') {
				// Parse the field's choices
				$enum = $Proj_metadata[$field]['element_enum'];
				$choices = ($Proj_metadata[$field]['element_type'] == 'sql') ? parseEnum(getSqlFieldEnum($enum, PROJECT_ID, $fetched, $_GET['event_id'], $_GET['instance'], null, null, $_GET['page'])) : parseEnum($enum);
				// If not a valid choice, then add to errors array
				if (!isset($choices[$val])) $errors[$field] = $val;
			} 
			// If field is text field with validation
			elseif ($Proj_metadata[$field]['element_type'] == 'text' && $val_type != '') {
				// Get the regex
				if ($val_type == 'int') $val_type = 'integer';
				elseif ($val_type == 'float') $val_type = 'number';
				if (!isset($valTypes[$val_type])) continue;
				$regex = $valTypes[$val_type]['regex_php'];
				// Run the value through the regex pattern
				preg_match($regex, $val, $regex_matches);
				// Was it validated? (If so, will have a value in 0 key in array returned.)
				$failed_regex = (!isset($regex_matches[0]));
				// Set error message if failed regex and make note if it is a required field
				if ($failed_regex) {
					$errors[$field] = $val;
					if ($Proj_metadata[$field]['field_req'] == "1") $_SESSION['requiredFieldResetByServerSideValidation'] = true;
				}
			}
		}
		
		// Remove any fields from POST if they failed server-side validation
		Form::removeFailedServerSideValidationsPost($errors);

        // Add the failed server-side validations to session to pick up elsewhere
        if (!empty($errors)) $_SESSION['serverSideValErrors'] = $errors;

        // If using Secondary Unique Field, also check for uniqueness during server-side post-submission
        if ($sufValueJustChanged && $secondary_pk != '' && isset($_POST[$secondary_pk])) {
            // Check for any duplicated values for the $secondary_pk field (exclude current record name when counting)
            $uniqueValueAlreadyExists = Records::checkSecondaryUniqueFieldValue($Proj->project_id, $secondary_pk, $fetched, $_POST[$secondary_pk]);
            if ($uniqueValueAlreadyExists) {
                // Set session flag to display error prompt and remove value from POST
                $_SESSION['serverSideSufError'] = true;
                unset($_POST[$secondary_pk]);
            }
        }
		
		// Return errors
		return $errors;
	}
	
	// Remove any fields from POST if they failed server-side validation
	public static function removeFailedServerSideValidationsPost($serverSideValErrors=array())
	{
		foreach ($serverSideValErrors as $field=>$val) {
			unset($_POST[$field]);
		}
	}
	
	// Display dialog if server-side validation was violated
	public static function displayFailedServerSideSufCheckPopup($is_survey)
    {
        global $Proj;

        // MultiLanguage
        $context = \REDCap\Context::Builder()
            ->project_id($Proj->project_id);
        if ($is_survey) {
            $context->is_survey();
        } else {
            $context->is_dataentry();
        }
        $context = $context->Build();

        $dialogTitle = MultiLanguage::getUITranslation($context, "data_entry_662");
        if ($is_survey) {
            $dialogContent = MultiLanguage::getUITranslation($context, "data_entry_665")." \"".RCView::b(strip_tags($Proj->metadata[$Proj->project['secondary_pk']]['element_label']))."\"";
        } else {
            $dialogContent = MultiLanguage::getUITranslation($context, "data_entry_663")." \"".RCView::b(strip_tags($Proj->metadata[$Proj->project['secondary_pk']]['element_label']))."\"";
        }

        // Javascript
        ?>
        <script type='text/javascript'>
          $(function(){
            setTimeout(function(){
              // POP-UP DIALOG
              simpleDialog('<?=js_escape($dialogContent)?>','<?=js_escape($dialogTitle)?>','serverside_suf_violated');
            },(isMobileDevice ? 1500 : 0));
          });
        </script>
        <?php
    }

	// Display dialog if server-side validation was violated
	public static function displayFailedServerSideValidationsPopup($serverSideValErrors, $is_survey)
	{
		global $Proj;
		$Proj_metadata = $Proj->getMetadata();

		// MultiLanguage
		$context = \REDCap\Context::Builder()
			->project_id($Proj->project_id);
		if ($is_survey) {
			$context->is_survey();
		}
		else {
			$context->is_dataentry();
		}
		$context = $context->Build();

		$data_entry_271 = MultiLanguage::getUITranslation($context, "data_entry_271");
		$data_entry_272 = MultiLanguage::getUITranslation($context, "data_entry_272");
		$data_entry_530 = MultiLanguage::getUITranslation($context, "data_entry_530");
		$calendar_popup_01 = MultiLanguage::getUITranslation($context, "calendar_popup_01");

		// Obtain the field labels
		$fieldLabels = array();
		$fields = explode(",", strip_tags($serverSideValErrors));
		foreach ($fields as $field) {
			if (!isset($Proj_metadata[$field])) continue;
			$label = MultiLanguage::getDDTranslation($context, "field-label", $field, "", $Proj_metadata[$field]['element_label']);
			$label = strip_tags(label_decode($label));
			if (mb_strlen($label) > 60) $label = mb_substr($label, 0, 40)."...".mb_substr($label, -18);
			$fieldLabels[] = $label;			
		}
		// Output hidden dialog div 
		print 	RCView::div(array('id'=>'serverside_validation_violated', 'class'=>'simpleDialog'),
					RCView::div(array('style'=>'padding-bottom:10px;'), $data_entry_271) .
					RCView::div(array('style'=>'font-weight:bold;'), $data_entry_272) .
					"<ul><li>\"" . implode("\"</li><li>\"", $fieldLabels) . "\"</li></ul>"
				);
		// Javascript
		?>
		<script type='text/javascript'>
		$(function(){
			setTimeout(function(){
				// POP-UP DIALOG
				$('#serverside_validation_violated').dialog({
					bgiframe: true, 
					modal: true, 
					width: (isMobileDevice ? $(window).width() : 500), 
					open: function(){fitDialog(this)},
					title: '<i class="fas fa-exclamation-triangle text-danger" style="vertical-align:middle;"></i> <span style="vertical-align:middle;" data-rc-lang="data_entry_530"><?=$data_entry_530?></span>',
					buttons: [
						{
							html: '<span data-rc-lang="calendar_popup_01"><?=$calendar_popup_01?></span>',
							click: function() { $(this).dialog('close'); }
						}
					]
				});
			},(isMobileDevice ? 1500 : 0));
		});
		</script>
		<?php
	}
	
	// Display dialog for @MAXCHOICE error pop-up message
	public static function displayFailedSaveMaxChoicePopup($maxChoiceErrors)
	{
		global $Proj;
		$Proj_metadata = $Proj->getMetadata();

		// Obtain the field labels
		$fieldLabels = array();
		$fields = explode(",", strip_tags($maxChoiceErrors));
		foreach ($fields as $field) {
			if (!isset($Proj_metadata[$field])) continue;
			$label = strip_tags(label_decode($Proj_metadata[$field]['element_label']));
			if (mb_strlen($label) > 60) $label = mb_substr($label, 0, 40)."...".mb_substr($label, -18);
			$fieldLabels[] = $label;			
		}
		// Output hidden dialog div 
		print 	RCView::div(array('id'=>'maxchoice_violated', 'class'=>'simpleDialog'),
					RCView::div(array('style'=>'padding-bottom:10px;'), RCView::tt("data_entry_423")) .
					RCView::div(array('style'=>'font-weight:bold;'), RCView::tt("data_entry_424")) .
					"<ul><li>\"" . implode("\"</li><li>\"", $fieldLabels) . "\"</li></ul>"
				);
		// Javascript
		?>
		<script type='text/javascript'>
		$(function(){
			setTimeout(function(){
				// POP-UP DIALOG
				$('#maxchoice_violated').dialog({ bgiframe: true, modal: true, width: (isMobileDevice ? $(window).width() : 780), open: function(){fitDialog(this)},
					title: '<?php echo js_escape(RCView::img(array('src'=>'exclamation_frame.png','style'=>'vertical-align:middle;')) . RCView::tt("data_entry_531", "span", array('style'=>'vertical-align:middle;'))) ?>',
					buttons: {
						'<?=RCView::tt_js("calendar_popup_01")?>': function() { $(this).dialog('close'); }
					}
				});
			},(isMobileDevice ? 1500 : 0));
		});
		</script>
		<?php
	}

    /**
     * Get all fields that have @MC-PARTICIPANT-JOINDATE action tag
     *
     * @param int $project_id
     * @return array
     */
    public static function getMyCapParticipantInstallDateFields($project_id)
    {
        if (!isinteger($project_id)) return [];
        $tag = Annotation::PARTICIPANT_JOINDATE;
        $Proj = new Project($project_id);
        // Use cache
        if (is_array(Project::$mycap_participant_installdate_fields) && isset(Project::$mycap_participant_installdate_fields[$project_id])) {
            $fields = Project::$mycap_participant_installdate_fields[$project_id];
        }
        // Find fields and add to cache
        else {
            $fields = [];
            foreach ($Proj->metadata as $field => $attr) {
                if ($attr['misc'] !== null && strpos($attr['misc'], $tag) !== false) {
                    $fields[] = $field;
                }
            }
            Project::$mycap_participant_installdate_fields[$project_id] = $fields;
        }
        // Return the array of fields
        return $fields;
    }

    /**
     * Check if project contains field to capture Install Date (Field with annotation @MC-PARTICIPANT-JOINDATE)
     *
     * @param int $projectId
     * @return void
     */
    public static function checkInstallDateFieldExists($projectId, $annotation = Annotation::PARTICIPANT_JOINDATE)
    {
        $dictionary = \REDCap::getDataDictionary($projectId, 'array');
        foreach ($dictionary as $field) {
            if (strpos(
                    $field['field_annotation'],
                    $annotation
                ) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add New MyCap Field - Install Date with action tag @MC-PARTICIPANT-JOINDATE
     *
     * @param int $projectId
     * @return void
     */
    public static function addMyCapInstallDateField($projectId) {
	    $Proj = new Project($projectId);
	    // Check if install date field exists or not
        $installDateFieldExists = self::checkInstallDateFieldExists($projectId);
        if ($installDateFieldExists == false) {
            $Proj->loadMetadata();
            $projectDesigner = new ProjectDesigner($Proj);

            $field['field_type'] = "text";
            $field['field_name'] = ActiveTask::getNewFieldName('par_joindate');
            $field['field_label'] = "Install Date";
            $field['field_annotation'] = Annotation::PARTICIPANT_JOINDATE . ' @HIDDEN';
            $field['val_type'] = "datetime_seconds_ymd";
            $projectDesigner->createField($Proj->firstForm, $field);
            // Reset metadata in Project object
            $Proj->loadMetadataByStatus();
        }
    }

    /**
     * Add New MyCap Field - Code with action tag @MC-PARTICIPANT-CODE @HIDDEN
     *
     * @param int $projectId
     * @return void
     */
    public static function addMyCapCodeField($projectId) {
        $Proj = new Project($projectId);
        // Check if code field exists or not
        $codeFieldExists = self::checkCodeFieldExists($projectId);
        if ($codeFieldExists == false) {
            $Proj->loadMetadata();
            $projectDesigner = new ProjectDesigner($Proj);

            $field['field_type'] = "text";
            $field['field_name'] = ActiveTask::getNewFieldName('par_code');
            $field['field_label'] = "Participant Code";
            $field['field_annotation'] = Annotation::PARTICIPANT_CODE . ' @HIDDEN';
            $projectDesigner->createField($Proj->firstForm, $field);
            // Reset metadata in Project object
            $Proj->loadMetadataByStatus();
        }
    }

    /**
     * Check if project contains field to capture Code (Field with annotation @MC-PARTICIPANT-CODE)
     *
     * @param int $projectId
     * @return void
     */
    public static function checkCodeFieldExists($projectId)
    {
        $dictionary = \REDCap::getDataDictionary($projectId, 'array');
        foreach ($dictionary as $field) {
            if (strpos(
                    $field['field_annotation'],
                    Annotation::PARTICIPANT_CODE
                ) !== false) {
                return true;
            }
        }
        return false;
    }

    // Get all fields that have @MC-PARTICIPANT-CODE action tag
    public static function getMyCapParticipantCodeFields($project_id)
    {
        if (!isinteger($project_id)) return [];
        $tag = Annotation::PARTICIPANT_CODE;
        $Proj = new Project($project_id);
	    if ($Proj->project['status'] > 0 && $Proj->project['draft_mode'] > 0) {
            $Proj->loadMetadataTemp();
        } else {
            $Proj->loadMetadata();
        }
        // Use cache
        if (is_array(Project::$mycap_participant_code_fields) && isset(Project::$mycap_participant_code_fields[$project_id])) {
            $fields = Project::$mycap_participant_code_fields[$project_id];
        }
        // Find fields and add to cache
        else {
            $fields = [];
            foreach ($Proj->metadata as $field => $attr) {
                if ($attr['misc'] !== null && strpos($attr['misc'], $tag) !== false) {
                    $fields[] = $field;
                }
            }
            Project::$mycap_participant_code_fields[$project_id] = $fields;
        }
        // Return the array of fields
        return $fields;
    }

    /**
     * Add New MyCap Field - Install Date (UTC) and timezone with action tag @MC-PARTICIPANT-JOINDATE-UTC and @MC-PARTICIPANT-TIMEZONE
     *
     * @param int $projectId
     * @return void
     */
    public static function addExtraMyCapInstallDateField($projectId) {
        $Proj = new Project($projectId);
        // Check if install date (UTC) field exists or not
        $installDateUtcFieldExists = self::checkInstallDateFieldExists($projectId, Annotation::PARTICIPANT_JOINDATE_UTC);
        if ($installDateUtcFieldExists == false) {
            $Proj->loadMetadata();
            $projectDesigner = new ProjectDesigner($Proj);

            $field = [];
            $field['field_type'] = "text";
            $field['field_name'] = ActiveTask::getNewFieldName('par_joindate_utc');
            $field['field_label'] = "Install Date (UTC)";
            $field['field_annotation'] = Annotation::PARTICIPANT_JOINDATE_UTC . ' @HIDDEN';
            $field['val_type'] = "datetime_seconds_ymd";
            $projectDesigner->createField($Proj->firstForm, $field);
            // Reset metadata in Project object
            $Proj->loadMetadataByStatus();
        }

        // Check if Participant timezone field exists or not
        $timezoneFieldExists = self::checkInstallDateFieldExists($projectId, Annotation::PARTICIPANT_TIMEZONE);
        if ($timezoneFieldExists == false) {
            $Proj->loadMetadata();
            $projectDesigner = new ProjectDesigner($Proj);

            $field = [];
            $field['field_type'] = "text";
            $field['field_name'] = ActiveTask::getNewFieldName('par_timezone');
            $field['field_label'] = "Participant Timezone";
            $field['field_annotation'] = Annotation::PARTICIPANT_TIMEZONE . ' @HIDDEN';
            $projectDesigner->createField($Proj->firstForm, $field);
            // Reset metadata in Project object
            $Proj->loadMetadataByStatus();
        }
    }

    /**
     * Get all fields that have @MC-PARTICIPANT-JOINDATE-UTC action tag
     *
     * @param int $project_id
     * @return array
     */
    public static function getMyCapParticipantInstallDateUTCFields($project_id)
    {
        if (!isinteger($project_id)) return [];
        $tag = Annotation::PARTICIPANT_JOINDATE_UTC;
        $Proj = new Project($project_id);
        // Use cache
        if (is_array(Project::$mycap_participant_installdate_utc_fields) && isset(Project::$mycap_participant_installdate_utc_fields[$project_id])) {
            $fields = Project::$mycap_participant_installdate_utc_fields[$project_id];
        }
        // Find fields and add to cache
        else {
            $fields = [];
            foreach ($Proj->metadata as $field => $attr) {
                if ($attr['misc'] !== null && strpos($attr['misc'], $tag) !== false) {
                    $fields[] = $field;
                }
            }
            Project::$mycap_participant_installdate_utc_fields[$project_id] = $fields;
        }
        // Return the array of fields
        return $fields;
    }

    /**
     * Get all fields that have @MC-PARTICIPANT-TIMEZONE action tag
     *
     * @param int $project_id
     * @return array
     */
    public static function getMyCapParticipantTimezoneFields($project_id)
    {
        if (!isinteger($project_id)) return [];
        $tag = Annotation::PARTICIPANT_TIMEZONE;
        $Proj = new Project($project_id);
        // Use cache
        if (is_array(Project::$mycap_participant_timezone_fields) && isset(Project::$mycap_participant_timezone_fields[$project_id])) {
            $fields = Project::$mycap_participant_timezone_fields[$project_id];
        }
        // Find fields and add to cache
        else {
            $fields = [];
            foreach ($Proj->metadata as $field => $attr) {
                if ($attr['misc'] !== null && strpos($attr['misc'], $tag) !== false) {
                    $fields[] = $field;
                }
            }
            Project::$mycap_participant_timezone_fields[$project_id] = $fields;
        }
        // Return the array of fields
        return $fields;
    }

    /**
     * Save values (install date, install date UTC, timezone) to all fields for records
     *
     * @param int $projectId
     * @param array $fields
     * @param string $record
     * @param string $newValue
     * @return array
     */
    public static function saveMyCapInstallDateInfo($projectId, $fields, $record, $newValue)
    {
        $records = json_decode(\REDCap::getData($projectId, 'json', [$record], $fields), true);
        $Proj = new Project($projectId);
        $instance = null;
        $event_id = null;
        foreach ($records as $attr) {
            if ($attr['redcap_repeat_instance'] == $instance || $attr['redcap_repeat_instance'] == "") {
                foreach ($fields as $field) {
                    if ($field != $Proj->table_pk) { // Skip when its primary key
                        // Save field value
                        $record_data = [[$Proj->table_pk => $record, $field => $newValue]];
                        if ($Proj->longitudinal) $record_data[0]['redcap_event_name'] = $attr['redcap_event_name'];
                        $hasRepeatingInstances = ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingForm($event_id, $attr['redcap_repeat_instrument']));
                        if ($hasRepeatingInstances) {
                            $record_data[0]['rexdcap_repeat_instrument'] = $attr['redcap_repeat_instrument'];
                            $record_data[0]['redcap_repeat_instance'] = $attr['redcap_repeat_instance'];
                        }

                        $params = ['project_id'=>$projectId, 'dataFormat'=>'json', 'data'=>json_encode($record_data)];
                        $response = \REDCap::saveData($params);
                    }
                }
            }
        }
    }

    /**
     * Check if field exists in a form
     *
     * @param int $project_id
     * @param string $form
     * @param string $field
     * @return boolean
     */
    public static function checkFieldExists($project_id, $form, $field) {
        $Proj = new Project($project_id);
        $Proj_metadata = $Proj->getMetadata();
        $Proj_forms = $Proj->getForms();

        return (isset($Proj_forms[$form]) && isset($Proj_metadata[$field]) && isset($Proj_forms[$form]['fields'][$field]));
    }

	/**
	 * Gets a url to a add a new form instance
	 * @param string|int|null $project_id 
	 * @param string $record 
	 * @param string|int $event_id 
	 * @param string $form 
	 * @return string 
	 */
	public static function getAddNewFormInstanceUrl($project_id, $record, $event_id, $form) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		$record = removeDDEending($record);
		$new_instance = RepeatInstance::getRepeatFormInstanceMaxCountOnly($record, $event_id, $form, $Proj) + 1;
		$url = APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id&id=".urlencode($record)."&event_id=$event_id&page=$form&instance=$new_instance&new";

		return $url;
	}
}
