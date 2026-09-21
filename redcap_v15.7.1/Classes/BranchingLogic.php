<?php

class BranchingLogic
{
	private $_results = array();
	private $_equations = array();
	private $_fields_utilized = array();

    public function feedBranchingEquation($name, $string)
	{
		$string = html_entity_decode($string, ENT_QUOTES);
		$string = LogicParser::removeCommentsAndSanitize($string);

		// Format logic to JS format
		list ($string, $fields_utilized) = LogicTester::formatLogicToJS($string, false, (isset($_GET['event_id']) ? $_GET['event_id'] : null), true, PROJECT_ID);

		// Add to arrays
		$this->_results[] = $name;
		$this->_equations[] = $string;
		$this->_fields_utilized[$name] = $fields_utilized;
    }

	public function exportBranchingJS()
	{
		$specialPipingTags = Piping::getSpecialTagsFormatted(false, false);
		
		// Compile all trigger fields as keys in array with values as fields they trigger
		$triggerFields = array();
		foreach ($this->_fields_utilized as $receiver=>$triggers) {
			// Also find all fields that the receiver field is dependent upon (to deal with calc/branching chaining)
			$dependentFields = getDependentFields(array($receiver));
			foreach ($triggers as $tkey=>$trigger) {
				if (!in_array($trigger, $specialPipingTags)) {
					$triggerFields[$trigger][] = $receiver;
				}
				if (!empty($dependentFields)) {
					foreach ($dependentFields as $dkey=>$trigger2) {
						if (!in_array($trigger2, $specialPipingTags)) {
							$triggerFields[$trigger2][] = $receiver;
						}
					}
				}
				unset($triggers[$tkey]);
			}
			unset($this->_fields_utilized[$receiver], $triggers);
		}
		// Ensure uniqueness
		foreach ($triggerFields as $trigger=>$receivers) {
			$triggerFields[$trigger] = [...array_unique($receivers)];
		}
		// If the project-level setting is set to bypass the Erase Value prompt, then set "overrideEraseValuePrompt" as TRUE
		$overrideEraseValuePrompt = isset($GLOBALS['bypass_branching_erase_field_prompt']) && $GLOBALS['bypass_branching_erase_field_prompt'] == '1';
		// Assemble the JS code for each field's branching logic
		$bl_js = [];
		for ($i = 0; $i < sizeof($this->_results); $i++)
		{
			$this_field = $this->_results[$i];
			$this_eqn = $this->_equations[$i];
			$bl_js[$this_field] = "return ($this_eqn);";
		}
		$branchingLogic = [
			"displayErrors" => isset($_GET['__display_errors']),
			"initialExecution" => true,
			"overrideEraseValuePrompt" => $overrideEraseValuePrompt,
			"runAllAgain" => false,
			"triggerFields" => $triggerFields,
			"errorTracker" => array_combine($this->_results, array_fill(0, sizeof($this->_results), 0)),
			"jsCode" => $bl_js,
		];

		// Assemble JS
		$result  = "\n<!-- Branching Logic -->";
		$result .= "\n<script type=\"text/javascript\">\n";
		$result .= "var BranchingLogic = ".json_encode($branchingLogic).";\n";
		$result .= "setupBranchingLogic();\n";

		// Add javascript for form/survey page to show form table right before we execute the branching (but delay this if we are doing any field embedding)
		if (!Piping::instrumentHasEmbeddedVariables(PROJECT_ID, $_GET['page'])) {
			$result .= "displayQuestionTable();\n";
		}
		// For specific situations, this function needs to be run again after the page fully loads
		$result .= "$(function(){ hideSectionHeaders(); });\n";

		// Execute the branching logic 
		$result .= "doBranching('', false, true);\n";
		// Note: Error reporting for branching logic is handled togehter with that of calculations
		$result .= "\n</script>\n";

		return $result;
	}


	// Determines if ALL fields provided in $fields would be hidden by branching logic
	// based on existing saved data values (also considers @HIDDEN and @HIDDEN-SURVEY). Returns boolean.
	public static function allFieldsHidden($record, $event_id=null, $form_name=null, $instance=1, $fields=array())
	{
		global $Proj, $longitudinal, $table_pk;
		// Return false if $fields is empty
		if ($record == null || empty($fields)) return false;
		// Loop through all fields and check to make sure they ALL have branching logic.
		// If at least one does NOT have branching logic, then return false.
		foreach ($fields as $field) {
			if ($Proj->metadata[$field]['branching_logic'] == '' && !Form::hasHiddenOrHiddenSurveyActionTag($Proj->metadata[$field]['misc'])) {
				return false;
			}
		}
		// Has repeating events/forms?
        $hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
		// If longitudinal, then get unique event name from event_id
		if ($event_id == null) $event_id = $Proj->getFirstEventIdArm(getArm());
		$unique_event_name = $Proj->getUniqueEventNames($event_id);
		// Obtain all dependent fields for the fields displayed
		$fieldsDependent = getDependentFields($fields, false, true);
        // Gather fields
        $getDataFields = array_merge($fieldsDependent, $fields, array($table_pk));
        if ($form_name != null && $event_id != null && $Proj->isRepeatingForm($event_id, $form_name)) {
            // Add form status field if this is a repeating instrument
            $getDataFields[] = $form_name."_complete";
        }
		// Obtain array of record data (including default values for checkboxes and Form Status fields)
        $getDataParams = [
			'records'=>$record,
			'fields'=>$getDataFields,
			'decimalCharacter' => '.',
			'returnBlankForGrayFormStatus' => true,
		];
        $record_data = Records::getData($getDataParams);
		$record_data = $record_data[$record] ?? [];
		// For longitudinal only, there might be cross-event logic that references events that dont' have any
		// data yet, which will cause it to return FALSE mistakenly in some cases. So for all events with no data,
		// add each event with empty values and add to $record_data array so that they are present (and blank) to be used in apply().
		if ($longitudinal) {
			// Get any missing events from $record_data
			$missing_event_ids = array_diff(array_keys($Proj->eventInfo), array_keys($record_data));
			// If there exist some events with no data, then loop through $record_data and add empty events
			if (!empty($missing_event_ids)) {
				$empty_data = array();
				foreach ($record_data as $this_event_id=>$these_fields) {
					// Loop through fields
					foreach ($these_fields as $this_field=>$this_value) {
						if (is_array($this_value)) {
							// Checkboxes
							foreach ($this_value as $this_code=>$this_checkbox_value) {
								// Add to array a 0 as default checkbox value
								$empty_data[$this_field][$this_code] = '0';
							}
						} else {
							// Non-checkbox fields
							// Set value as blank (but not for record ID field and not for Form Status fields)
							if ($this_field == $table_pk) {
								// Do nothing, leave record ID value as-is
							} elseif ($Proj->isFormStatus($this_field)) {
								// Set default value as 0
								$this_value = '0';
							} else {
								$this_value = '';
							}
							// Add to array
							$empty_data[$this_field] = $this_value;
						}
					}
					// Stop here since we only need just one event's field structure
					break;
				}
			}
			// Add empty event arrays to $record_data
			if (!empty($empty_data)) {
				// Loop through missing event_ids and add each event with blank event data
				foreach ($missing_event_ids as $this_event_id) {
					$record_data[$this_event_id] = $empty_data;
				}
			}
		}
		// Loop through all fields visible on survey and evaluate their branching logic one by one
        $countDescriptive = 0;
		foreach ($fields as $field) {
		    if ($Proj->metadata[$field]['element_type'] == 'descriptive') $countDescriptive++;
			// First, check if has HIDDEN or HIDDEN-SURVEY action tag
			if (Form::hasHiddenOrHiddenSurveyActionTag($Proj->metadata[$field]['misc'])) {
				// Field is hidden by action tag, so no need to check branching logic. Skip to next field.
				continue;
			}
			// Get branching logic for this field
			$logic = $Proj->metadata[$field]['branching_logic'];
			if ($logic == '') return false;
			// If this is a repeating event/form, then append [current-instance] to all repeating fields so that they get replaced properly via pipeSpecialTags()
            if ($hasRepeatingFormsEvents && ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingForm($event_id, $form_name))) {
                $logic = LogicTester::logicAppendCurrentInstance($logic, $Proj, $event_id);
            }
			// Pipe any special tags?
			$logic = Piping::pipeSpecialTags($logic, $Proj->project_id, $record, $event_id, $instance, USERID, true, null, $form_name, false, false, false, true, false, false, true);
			// If longitudinal, then inject the unique event names into logic (if missing)
			// in order to specific the current event.
			if ($longitudinal) {
				$logic = LogicTester::logicPrependEventName($logic, $unique_event_name, $Proj);
			}
			// Make sure that the field's branching logic has proper syntax before we evaluate it with data
			if (!LogicTester::isValid($logic)) return false;
			// Now evaluate the logic with data
			$displayField = LogicTester::apply($logic, $record_data);
			// If at least one field is to be displayed, then return false
			if ($displayField) return false;
		}
		// If all the fields are descriptive and at least one is displayed, then return false
        if (empty($record_data) && count($fields) == $countDescriptive) return false;
		// If we made it this far, then all fields must be hidden
		return true;
	}

	/**
	 * Save branching logic
	 * @param string $field_name The field name
	 * @param string $new_branching_logic The new branching logic
	 * @param bool $same_logic_fields If true, update fields with same logic
	 * @param null|int|string $project_id_override Project ID override
	 * @param bool $simulate When true, do not actually save ($field_name may be a dummy field name)
	 * @return string 1,2,3,4 signal validity, anything else is an error
	 */
	public static function save($field_name, $new_branching_logic, $same_logic_fields = false, $project_id_override = null, $simulate = false) 
	{
		$Proj = new Project($project_id_override);
		$status = $Proj->project["status"];
		$draft_mode = $Proj->project["draft_mode"];
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
		$ProjMetadata = $draft_mode == "1" ? $Proj->metadata_temp : $Proj->metadata;
		// Check if field exists
		if (!($simulate || array_key_exists($field_name, $ProjMetadata))) {
			return "ERROR - Field not found";
		}
		$stored_branching_logic = $simulate ? null : $ProjMetadata[$field_name]['branching_logic'];

		// If project is in production and another user just changed its draft_mode status, don't allow any actions here if not in draft mode
		if ($status > 0 && $draft_mode != "1") return "ERROR";

		// Obtain array of error fields that are not real fields
		$error_fields = Design::validateBranchingCalc($new_branching_logic);

		// Return list of fields that do not exist (i.e. were entered incorrectly), else continue.
		if (!empty($error_fields))
		{
			$response = RCView::tt("survey_470") . 
				RCView::br() . RCView::br() . 
				RCView::b(RCView::tt("survey_472")) . 
				RCView::ul([], "<li>" . implode("</li><li>", $error_fields) . "</li>");
			return $response;
		}

		// Check if branching logic is valid
		$newBranchingIsValid = LogicTester::isValid($new_branching_logic);

		// NON-SUPER USERS: Perform deeper inspection of syntax to make sure nothing malicious gets through
		$super_user = defined("SUPER_USER") && SUPER_USER == 1;
		if (!$super_user && $new_branching_logic != "" && !$newBranchingIsValid)
		{
			// Default: Contains syntax errors (general)
			$response = "<b>".RCView::tt("dataqueries_47").RCView::tt("colon")."</b><br>".RCView::tt("dataqueries_99");
			// Check the logic for illegal functions
			$parser = new LogicParser();
			try {
				$parser->parse($new_branching_logic, null, true, false, false, true);
			} catch (LogicException $e) {
				if (count($parser->illegalFunctionsAttempted) !== 0) {
					// Contains illegal functions
					$response = RCView::b([], 
						RCView::tt("dataqueries_47").RCView::tt("colon")
					) .
					RCView::br() . 
					RCView::tt("dataqueries_109") . 
					RCView::br() .
					RCView::br() .
					RCView::b([], RCView::tt("dataqueries_48")) .
					RCView::br() .
					RCView::ul([], "<li>" . implode("</li><li>", $parser->illegalFunctionsAttempted) . "</li>");
					return $response;
				}
			}
			// Check if the previous branching logic was valid (if existed)
			$response2 = "";
			if ($stored_branching_logic != "")
			{
				$response_text = RCView::b([], RCView::tt("global_02").RCView::tt("colon")).RCView::SP;
				if ($stored_branching_logic == $new_branching_logic) {
					// Branching logic has NOT changed, but it is NOW considered invalid because of security measures.
					// User can keep it as is or remove the branching.
					$response_text .= RCView::tt("design_439");
				} else {
					// Branching HAS changed but has incorrect syntax.
					$response_text .= RCView::tt("design_440");
				}
				$response_text .= " <a href='javascript:;' onclick=\"helpPopup('3','category_16_question_1_tab_3');\">".RCView::tt("bottom_27")."</a>.";
				$response2 = RCView::div(array('class'=>'yellow','style'=>'margin-top:10px;'), $response_text);
			}
			// Return error message
			return $response . $response2;
		}

		// Save the branching logic
		if (!$simulate) {
			// Check if user has selected to update all fields with the same old branching logic
			$branging_same_logic_sql = " AND field_name = '" . db_escape($field_name) . "'";
			if($same_logic_fields) {
				$branging_same_logic_sql =  " AND branching_logic = '" . db_escape($stored_branching_logic) . "'";
			}
			$sql = "UPDATE $metadata_table SET branching_logic = " . checkNull($new_branching_logic) . " where project_id = {$Proj->project_id}".$branging_same_logic_sql;
			$db_result = db_query($sql);
			if ($db_result) {
				// SURVEY QUESTION NUMBERING (DEV ONLY): Detect if form is a survey, and if so, if has any branching logic. If so, disable question auto numbering.
				$form_name = $ProjMetadata[$field_name]["form_name"];
				if (Design::checkDisableSurveyQuesAutoNum($form_name)) {
					$response = '2';
				}
				// Log the data change
				Logging::logEvent($sql, $metadata_table, "MANAGE", $field_name, "field_name = '$field_name'", "Add/edit branching logic", "", "", $Proj->project_id);
			}
		}
		else {
			$db_result  = true;
		}
		if ($db_result) {
			$response = '1';
			// If a super user and there is an allowable syntax error in the logic (e.g., custom javascript), then give special msg.
			if ($super_user && $new_branching_logic != "" && !$newBranchingIsValid) {
				$response = ($response == '2') ? '4' : '3';
			}
			// Return response
			return $response;
		} else {
			return "0";
		}
	}
}
