<?php

/**
 * LogicTester
 * This class is used for execution/testing of logic used in Data Quality, branching logic, Automated Invitations, etc.
 */
class LogicTester
{
	/**
	 * Tests the logic with existing data and returns boolean as TRUE if all variables have values
	 * and the logic evaluates as true. Otherwise, return FALSE.
	 * @param string $logic is the raw logic provided by the user in the branching logic/Data Quality logic format.
	 * @param array $record_data holds the record data with event_id as first key, field name as second key,
	 * and value as data value (if checkbox, third key is raw coded value with value as 0/1).
	 */
	public static function apply($logic, $record_data=array(), $Proj=null, $returnValue=false, $useAnonFunction=false)
	{
		if ($Proj == null) {
			global $Proj;
		}
		// Get unique event names (with event_id as key)
		$events = $Proj->getUniqueEventNames();
		// If there is an issue in the logic, then return an error message and stop processing
		$funcName = null;
		try {
			// Instantiate logic parser
			$parser = new LogicParser();
			list ($funcName, $argMap) = $parser->parse($logic, array_flip($events), true, false, $useAnonFunction, false, false, $Proj);
		}
		catch (LogicException $e) {
			return false;
		}
		// Execute the logic to return boolean (return TRUE if is 1 and not 0 or FALSE)
		$logicApplied = self::applyLogic($funcName, $argMap, $record_data, $Proj->firstEventId, $returnValue, $Proj->project_id);
		if ($returnValue === false) {
			return ($logicApplied === 1);
		} else {
			return $logicApplied;
		}
	}


	/**
	 * Check if the logic is syntactically valid
	 */
	public static $validLogicCache = array();
	public static function isValid($logic)
	{
		$key = bin2hex($logic);
		if (!array_key_exists($key, self::$validLogicCache)) {
			$parser = new LogicParser();
			try {
				$parser->parse($logic, null, false);
				self::$validLogicCache[$key] = true;
			} catch (LogicException $e) {
				self::$validLogicCache[$key] = false;
			}
		}
		return self::$validLogicCache[$key];
	}

	/**
	 * Evaluate a logic string for a given record
	 */
	public static function evaluateLogicSingleRecord($raw_logic, $record, $record_data=null, $project_id_override=null,
													 $repeat_instance=1, $repeat_instrument=null, $returnValue=false, $bypassIsValid=false, $useAnonFunction=false)
	{
		// Check the logic to see if it's syntactically valid
		if (!$bypassIsValid && !self::isValid($raw_logic)) {
			return false;
		}
		// Get $Proj object
		if (is_numeric($project_id_override)) {
			$Proj = new Project($project_id_override);
		} else {
			global $Proj;
		}
		// Array to collect list of all fields used in the logic
		$fields = array();
		$extraDataFields = array();
		$events = ($Proj->longitudinal) ? array() : array($Proj->firstEventId);
		$Proj_metadata = $Proj->getMetadata();
		$specialPipingTags = Piping::getSpecialTagsFormatted(false, false);
		// Loop through fields used in the logic. Also, parse out any unique event names, if applicable
		foreach (array_keys(getBracketedFields($raw_logic, true, true, false)) as $this_field)
		{
			// Check if has dot (i.e. has event name included)
			if (strpos($this_field, ".") !== false) {
				list ($this_event_name, $this_field) = explode(".", $this_field, 2);
				$events[] = $this_event_name;
			}
			// Verify that the field really exists (may have been deleted). If not, stop here with an error.
			$isSmartVar = in_array($this_field, $specialPipingTags);
			if (!isset($Proj_metadata[$this_field]) && !$isSmartVar) return false;
			// Add field to array
			if (!$isSmartVar) {
				$fields[] = $this_field;
				// If field is on a repeating instrument (for any event), add the form status field
				$this_form = $Proj_metadata[$this_field]['form_name'];
				if (!in_array($this_form, $extraDataFields) && $Proj->isRepeatingFormAnyEvent($this_form)) {
					$extraDataFields[] = $this_form."_complete";
				}
			}
		}
		$events = array_unique($events);
		// Obtain array of record data (including default values for checkboxes and Form Status fields)
		if ($record_data == null) {
			// Retrieve data from data table since $record_data array was not passed as parameter
			$getDataParams = [
				'project_id'=>$Proj->project_id,
				'records'=>[$record],
				'fields'=>array_merge($fields, $extraDataFields),
				'events'=>$events,
				'returnEmptyEvents'=>true,
				'decimalCharacter'=>'.',
				'returnBlankForGrayFormStatus' => true
			];
			$record_data = Records::getData($getDataParams);
		}
		// If some events don't exist in $record_data because there are no values in data table for that event,
		// then add empty event with default values to $record_data (or else the parse will throw an exception).
        $recordDataEventIds = (!isset($record_data[$record]) || $record_data[$record] === null) ? array() : array_keys($record_data[$record]);
        $recordDataEventIdsRepeatKey = is_array($recordDataEventIds) ? array_search('repeat_instances', $recordDataEventIds) : false;
        if ($recordDataEventIdsRepeatKey !== false) {
            unset($recordDataEventIds[$recordDataEventIdsRepeatKey]);
        }
        if (is_array($events) && count($events) > count($recordDataEventIds)) {
            // Get unique event names (with event_id as key)
            $unique_events = $Proj->getUniqueEventNames();
            // Loop through each event
            foreach ($events as $event) {
                // If classical, the 'events' array will have just then firstEventId
                // If longitudinal, 'events' will be an array of unique_event_names
                $this_event_id = is_numeric($event) ? $event : array_search($event, $unique_events);
                if (!isset($record_data[$record][$this_event_id])) {
					// Add all fields from $fields with defaults for this event
					foreach ($fields as $this_field) {
						// If a checkbox, set all options as "0" defaults
						if ($Proj->isCheckbox($this_field)) {
							foreach (parseEnum($Proj_metadata[$this_field]['element_enum']) as $this_code=>$this_label) {
								$record_data[$record][$this_event_id][$this_field][$this_code] = "0";
							}
						}
						// If a Form Status field, give "0" default
						elseif ($this_field == $Proj_metadata[$this_field]['form_name']."_complete") {
							$record_data[$record][$this_event_id][$this_field] = "0";
						} else {
							$record_data[$record][$this_event_id][$this_field] = "";
						}
					}
				}
			}
		}
        /*
		// If this is not the first instance of a repeating instrument, then modify $record_data to remove the extra instances of
		// this instrument and set the $repeat_instance as instance 1 (so that LogicTester::apply can interpret it correctly).
		if ($repeat_instance > 0 || $repeat_instrument != "") 
		{
			$record_data_repeat_instance = array();
			$removeRepeatInstancesKey = false;
			foreach ($record_data[$record]['repeat_instances'] as $this_event_id=>&$attr) {
				if (!isset($attr[$repeat_instrument][$repeat_instance])) continue;
				// Loop through instance's fields, and if they exist on this instrument, then overwrite on main event for instance 1
				foreach ($attr[$repeat_instrument][$repeat_instance] as $this_field=>$this_val) {
					// If this field is somehow not on the repeating form, then skip
					if ($repeat_instrument != "" && !isset($Proj->getForms()[$repeat_instrument]['fields'][$this_field])) continue;
					// Overwrite instance 1's data with this instance
					$record_data[$record][$this_event_id][$this_field] = $this_val;
					$removeRepeatInstancesKey = true;
				}
			}
			// Remove all the instances since they are not needed
			if ($removeRepeatInstancesKey) unset($record_data[$record]['repeat_instances']);
		}
		*/
		// Apply the logic and return the result (TRUE = all conditions are true)
		return self::apply($raw_logic, $record_data[$record] ?? [], $Proj, $returnValue, $useAnonFunction);
	}

	// Sanitize logic by removing all invalid PHP functions
	public static function removeInvalidPhpFunctions($string)
	{
		$matches = array();
		do {
			$thisstring = $string;
			// Find all potential illegal functions via regex
			if (preg_match_all('/(.)([A-Za-z0-9_]+|([A-Za-z0-9_]+::[A-Za-z0-9_]+))(\s*)(\()/', " ".$string, $matches, PREG_PATTERN_ORDER))
			{
				$replacements = array();
				foreach ($matches[2] as $key=>$thisFunc) {
					$thisFuncReplacement = $matches[0][$key];
					$isCheckboxField = ($matches[1][$key] == '['); // Checkboxes might get flagged here since they follow the syntax [checkbox(1)], which looks similar to a function.
					if (strpos($thisFunc, "::")) {
						// No class methods should be used
						list ($thisClass, $thisMethod) = explode("::", $thisFunc, 2);
						if (!$isCheckboxField && method_exists($thisClass, $thisMethod)) {
							$replacements[$thisFuncReplacement] = "(";
						}
					} elseif (!$isCheckboxField && function_exists($thisFunc) && !isset(LogicParser::$allowedFunctions[$thisFunc])) {
						// No functions that are not listed in $ should be used
						$replacements[$thisFuncReplacement] = "(";
					}
				}
				// If we found illegal functions, replace them
				if (!empty($replacements)) {
					$string = str_replace(array_keys($replacements), $replacements, $string);
				}
			}
		} while ($thisstring != $string);
		return $string;
	}

	// Wrap all field names with chkNull (except date/time fields)
	public static function addChkNull($string, $Proj=null)
	{
		if ($Proj === null) return $string;
		$valTypes = getValTypes();
		$missingDataCodes = $GLOBALS['missingDataCodes'] ?? [];
		// Loop through all fields used in logic
		$all_logic_fields = $all_logic_fields_events = array();
		foreach (array_keys(getBracketedFields($string, true, true)) as $field) {
			if (strpos($field, ".") !== false) {
				// Event is prepended
				list ($event, $field) = explode(".", $field);
				if ($Proj->isCheckbox($field)) {
					// Loop through all options
					foreach (array_keys(parseEnum($Proj->metadata[$field]['element_enum'])) as $code) {
						$all_logic_fields_events["[$event][$field($code)]"] = "chkNull([$event--RCEVT--$field($code)])";
					}
				} else {
					// Ignore if a date/time field (they shouldn't get wrapped in chkNull)
					if (isset($Proj->metadata[$field])) {
						$fieldType = $Proj->metadata[$field]['element_type'];
						// Is this a MC field with all-numeric codings?
						$mcFieldWithNumCodings = ($Proj->isMultipleChoice($field) && arrayHasOnlyNums(array_keys($missingDataCodes+parseEnum($Proj->metadata[$field]['element_enum']))));
						$fieldValidation = $Proj->metadata[$field]['element_validation_type'];
						if ($fieldValidation == 'float') $fieldValidation = 'number';
						if ($fieldValidation == 'int') $fieldValidation = 'integer';
						$fieldDataType = $valTypes[$fieldValidation]['data_type'] ?? '';
						if ($mcFieldWithNumCodings || $fieldDataType == 'number' || $fieldDataType == 'number_comma_decimal' || $fieldDataType == 'integer' || $fieldType == 'calc' || $fieldType == 'slider' ||
							$fieldType == 'yesno' || $fieldType == 'truefalse' || $fieldType == 'file') {
							$all_logic_fields_events["[$event][$field]"] = "chkNull([$event--RCEVT--$field])"; // Add --RCEVT-- to replace later on so that non-event field replacement won't interfere
						}
					}
				}
			} else {
				// Normal field syntax (no prepended event)
				if ($Proj->isCheckbox($field)) {
					// Loop through all options
					foreach (array_keys(parseEnum($Proj->metadata[$field]['element_enum'])) as $code) {
						$all_logic_fields["[$field($code)]"] = "chkNull([$field($code)])";
					}
				} else {
					// Ignore if a date/time field (they shouldn't get wrapped in chkNull)
					if (isset($Proj->metadata[$field])) {
						// Is this a MC field with all-numeric codings?
						$fieldType = $Proj->metadata[$field]['element_type'];
						$mcFieldWithNumCodings = ($Proj->isMultipleChoice($field) && arrayHasOnlyNums(array_keys($missingDataCodes+parseEnum($Proj->metadata[$field]['element_enum']))));
						$fieldValidation = $Proj->metadata[$field]['element_validation_type'];
						if ($fieldValidation == 'float') $fieldValidation = 'number';
						if ($fieldValidation == 'int') $fieldValidation = 'integer';
						$fieldDataType = $valTypes[$fieldValidation]['data_type'] ?? '';
						if ($mcFieldWithNumCodings || $fieldDataType == 'number' || $fieldDataType == 'number_comma_decimal' || $fieldDataType == 'integer' || $fieldType == 'calc' || $fieldType == 'slider' ||
							$fieldType == 'yesno' || $fieldType == 'truefalse' || $fieldType == 'file') {
							$all_logic_fields["[$field]"] = "chkNull([$field])";
						}
					}
				}
			}
		}
		// Now through through all replacement strings and replace
		foreach ($all_logic_fields_events as $orig=>$repl) {
			$string = str_replace($orig, $repl, $string);
		}
		foreach ($all_logic_fields as $orig=>$repl) {
			$string = str_replace($orig, $repl, $string);
		}
		$string = str_replace("--RCEVT--", "][", $string);
		// Fix any repeating instances that did not get replaced correctly
		if ($Proj->hasRepeatingFormsEvents()) {
			$regex = '/(\)?)(\]\)\[)(\d+|first-instance|last-instance|previous-instance|next-instance|current-instance)(\])/';
			$string = preg_replace($regex, "$1][$3$4)", $string);
		}
		// Return the filtered string with chkNull
		return $string;
	}

	// Format logic to PHP format
	public static function formatLogicToPHP($string, $Proj2=null)
	{
		// Replace tabs and line breaks and reset any operators back to user-facing specs
		$string = html_entity_decode(rawurldecode($string??''), ENT_QUOTES);
        // Remove inline comments
        $string = LogicParser::removeCommentsAndSanitize($string);
        // Translate operators
		$string = str_replace(array("\r\n", "\n", "\r", "\t", "!=", " && ",  " || ", " AND ", " OR ", "\\"),
							  array(" ",    " ",  " ",  " ",  "<>", " and ", " or ", " and ", " or ", ""),
							  $string);
		// Replace any instances of ":value]" where a user has attempted to use [field:value] in the logic/calculation
		$string = str_replace(":value]", "]", $string);
		// Pre-format the string with regard to exponent format - (x)^(y)
		$string = self::replaceExponents($string, true);
		// Replace any instances of round() with roundRC()
		$string = Calculate::replaceRoundRC($string);
		// Wrap all field names with chkNull (except date/time fields)
		$string = self::addChkNull($string, $Proj2);
		// Replace ="" with ="NaN" for better parsing
		$string = Calculate::replaceEqualNull($string);
		// Replace commas with dots for "numbers w/ comma decimals"
		$string = Calculate::replaceCommasForDecimals($string);
		// Replace all instances of "NaN" and 'NaN' in string with ""
		$string = Calculate::replaceNaN($string);
		// Replace all instances of "log" in string with "logRC" to handle non-numbers
		$string = Calculate::replaceLog($string);
		// Replace all instances of "min" in string with "minRC" to handle non-numbers
		$string = Calculate::replaceMin($string);
		// Replace all instances of "max" in string with "maxRC" to handle non-numbers
		$string = Calculate::replaceMax($string);
		// Replace all literal date values inside datediff()
		$string = Calculate::replaceDatediffLiterals($string, $Proj2);
		// Replace isblankormissingcode(chkNull([field])) with isblankormissingcode(([field])) - same for ismissingcode(), hasmissingcode(), and isblanknotmissingcode()
		$string = preg_replace("/(isblankormissingcode|ismissingcode|isblanknotmissingcode|hasmissingcode)(\()(\s*)(chkNull)(\s*)(\()/", "$1$2$6", $string);
		// Stop if logic is blank
		if (trim($string) == "") return "";
		// Sanitize the generated code by removing all invalid PHP functions
		$string = self::removeInvalidPhpFunctions($string);
		// Return formatted string
		return $string;
	}


	/**
	 * Runs the logic function and returns the *COMPLEMENT* of the result;
	 * @param string $funcName the name of the function to execute.
	 * @param array $recordData first key is the event name, second key is the
	 * field name, and third key is either the field value, or if the field is
	 * a checkbox, it will be an array of checkbox codes => values.
	 * @param string $currEventId the event ID of the current record being examined.
	 * @param array $rule_attr a description of the Data Quality rule.
	 * @param array $args used to inform the caller of the arguments that were
	 * actually used in the rule logic function.
	 * @param array $useArgs if given, this function will use these arguments
	 * instead of running $this->buildLogicArgs().
	 * @return 0 if the function returned false, 1 if the result is non-false, and
	 * false if an exception was thrown.
	 */
	public static function applyLogic($funcName, $argMap=array(), $recordData=array(), $firstEventId=null, $returnValue=false, $project_id=null)
	{
		$args = array();
		try {
			if (!self::buildLogicArgs($argMap, $recordData, $args, $firstEventId, $project_id)) {
				throw new Exception("recordData does not contain the parameters we need");
			}
            if ($funcName === null || !is_callable($funcName) || (is_string($funcName) && strpos($funcName, "redcap_func_") === 0 && !function_exists($funcName))) {
                return false;
            }
			$logicCheckResult = call_user_func_array($funcName, $args);
			if ($returnValue === false) {
				return ($logicCheckResult === false ? 0 : 1);
			} else {
				return $logicCheckResult;
			}
		}
		catch (Throwable $e) {
			return false;
		}
	}

	/**
	 * Builds the arguments to an anonymous function given record data.
	 * @param string $funcName the name of the function to build args for.
	 * @param array $recordData first key is the event name, second key is the
	 * field name, and third key is either the field value, or if the field is
	 * a checkbox, it will be an array of checkbox codes => values.
	 * @param array $args used to inform the caller of the arguments that were
	 * actually used in the rule logic function.
	 * @return boolean true if $recordData contained all data necessary to
	 * populate the function parameters, false if not.
	 */
	static public function buildLogicArgs($argMap=array(), $recordData=array(), &$args=array(), $firstEventId=null, $project_id=null)
	{
		$isValid = true;
		// Get first event ID for the relevant project
		if ($firstEventId == null && $project_id == null && defined("PROJECT_ID")) {
			global $Proj;
			$firstEventId = $Proj->firstEventId;
			$project_id = $Proj->project_id;
		} elseif (is_numeric($firstEventId) && !is_numeric($project_id)) {
			// Obtain project_id from event_id
			$sql = "select a.project_id from redcap_events_metadata e, redcap_events_arms a 
					where a.arm_id = e.arm_id and e.event_id = $firstEventId limit 1";
			$q = db_query($sql);
			if (!db_num_rows($q)) throw new Exception("Could not determine project_id");
			$project_id = db_result($q, 0);
			$Proj = new Project($project_id);
			$firstEventId = $Proj->firstEventId;
		} elseif ((!is_numeric($firstEventId) || !isset($Proj)) && is_numeric($project_id)) {
			$Proj = new Project($project_id);
			$firstEventId = $Proj->firstEventId;
		}
		if (!is_numeric($firstEventId) || !is_numeric($project_id)) {
			throw new Exception("Could not determine project_id or first event_id");
		}
		try {
			$Proj_metadata = $Proj->getMetadata();
			foreach ($argMap as $argData)
			{
				// Get event_id, variable, and (if a checkbox) checkbox choice
				list ($eventVar, $projectVar, $cboxChoice, $instanceVar) = $argData;
				// If missing the event_id, assume the first event_id in the project
				if ($eventVar == "") $eventVar = $firstEventId;
				$eventId = is_numeric($eventVar) ? $eventVar : $Proj->getEventIdUsingUniqueEventName($eventVar);
				// Determine repeating instrument based on event_id and field's form
				$isRepeatInstance = false;
				if (is_numeric($instanceVar) && $Proj->isRepeatingEvent($eventId)) {
					$repeat_instance = $instanceVar;
					$repeat_instrument = "";
					$isRepeatInstance = true;
				} elseif (is_numeric($instanceVar) && $Proj->isRepeatingForm($eventId, $Proj_metadata[$projectVar]['form_name'])) {
					$repeat_instrument = $Proj_metadata[$projectVar]['form_name'];
					$repeat_instance = $instanceVar;
					$isRepeatInstance = true;
				} else {
					$repeat_instrument = "";
					$repeat_instance = 0;
				}
				// If instance=0, then we may be using relative instance references (previous-instance), so return ""
                if ($isRepeatInstance && $repeat_instance < 1) {
                    $args[] = "";
                    continue;
                }
				// Check event key
				if (!isset($recordData[$eventId]) && !isset($recordData['repeat_instances'][$eventId])) {
					throw new Exception("Missing event: $eventId");
				}
				$projFields = array();
				if ($isRepeatInstance && isset($recordData['repeat_instances'][$eventId][$repeat_instrument][$repeat_instance])) {
					$projFields = $recordData['repeat_instances'][$eventId][$repeat_instrument][$repeat_instance];
				} elseif (isset($recordData[$eventId])) {
					$projFields = $recordData[$eventId];
				}
//				print "\n\$isRepeatInstance: ";var_dump($isRepeatInstance);
//				print "\n\$projectVar: ";var_dump($projectVar);
//				print "\n\$eventId: ";var_dump($eventId);
//				print "\n\$instanceVar: ";var_dump($instanceVar);
//				print "\n\$projFields:  ";print_r($projFields);
				// Check field key
				if (!isset($projFields[$projectVar])) {
					throw new Exception("Missing project field: $projectVar");
				}
				// Set value, then validate it based on field type
				$value = $projFields[$projectVar];
				if ($cboxChoice === null && is_array($value) || $cboxChoice !== null && !is_array($value))
					throw new Exception("checkbox/value mismatch! " . (is_array($value) ? json_encode($value) : htmlspecialchars($value, ENT_QUOTES)));
				if ($cboxChoice !== null && !isset($value[$cboxChoice]))
					throw new Exception("Missing checkbox choice: $cboxChoice");
				if ($cboxChoice !== null) {
					$value = $value[$cboxChoice];
				}
				// Add value to args array
				$args[] = $value;
			}
		}
		catch (Exception $e) {
			$isValid = false;
		}
		// Return if all arguments are valid and accounted for
		return $isValid;
	}

	// For fastest processing, preformat the logic by prepending [event-name] and/or appending [current-instance] where appropriate
	public static function preformatLogicEventInstanceSmartVariables($logic, $Proj, $isRepeatingContext=null)
	{
		// Remove comments
		$logic = LogicParser::removeCommentsAndSanitize($logic);
		// First, remove all instances of [current-instance] and [event-name] to start with a clean slate (will be added back later, if appropriate)
		$logic = str_replace("][current-instance]", "]", $logic);
		if ($Proj->longitudinal) {
			$logic = str_replace("[event-name][", "[", $logic);
		}
		// Only prepend/append smart variables if we have repeating instances in the project
		if ($Proj->hasRepeatingFormsEvents())
		{
			// Now set our starting point in case we need to revert to it
			$logic_original = $logic;
			// Prepend [event-name]
			if ($Proj->longitudinal) {
				$logic = LogicTester::logicPrependEventName($logic, 'event-name', $Proj);
			}
			// Append [current-instance] to all fields on repeating forms/events (this aids with parsing)
			$logic = LogicTester::logicAppendCurrentInstance($logic, $Proj);
			// If we have no x-instance smart variables, which must be evaluated for each array item (i.e., slow),
			// then revert back to having no [event-name] prepended, which will make the logic processing way faster.
			if ($Proj->longitudinal && !Piping::containsInstanceSpecialTags($logic)) {
				$logic = $logic_original;
			}
		}
		return $logic;
	}


	/**
	 * For a general logic string, prepend all variables with a unique event name provided if the
	 * variable is not already prepended with a unique event name.
	 * (Used to define an event explicitly before being evaluated for a record.)
	 */
	public static function logicPrependEventName($logic, $unique_event_name, $Proj)
	{
		// Set $Proj if not set
        if ((!isset($Proj) || !is_object($Proj)) && defined("PROJECT_ID")) {
			$Proj = new Project(PROJECT_ID);
		}
        if (!$Proj->longitudinal) return $logic;
		// First, prepend fields with unique event name
		$logic = preg_replace("/([^\]])(\[)/", "$1[$unique_event_name]$2", " " . $logic);
		// Get all unique event names for this project
		$events = array();
        if (isset($Proj) && is_object($Proj)) {
			foreach ($Proj->getUniqueEventNames() as $this_event) {
				$events[] = preg_quote("[$this_event]");
			}
		}
        // Remove instances of double event names in logic as cleanup
		if (!empty($events)) {
			// This can possibly return NULL in edge cases where there are LOTS of events, so handle it if it returns NULL
			$eventBatches = array_chunk($events, 100);
			foreach ($eventBatches as $eventBatch) {
				$result = preg_replace("/(\[{$unique_event_name}\])(" . implode("|", $eventBatch) . ")/", "$2", $logic);
				if ($result !== null) {
					$logic = $result;
				}
			}
		}
		// Remove instances of double event names with special piping tags in logic as cleanup
		$specialPipingTags = array();
		foreach (Piping::getSpecialTagsFormatted(true) as $tag) {
			$specialPipingTags[$tag] = "[$unique_event_name]$tag";
		}
		$logic = str_replace($specialPipingTags, array_keys($specialPipingTags), $logic);
		// Return the formatted logic
		return $logic;
	}


	/**
	 * For a general logic string, append all variables with a repeating instance number provided if the
	 * variable is from a repeating event/form and does not already the instance number appended.
	 */
	public static function logicAppendInstance($logic, $Proj, $event_id, $instrument, $instance)
	{
		$fieldsReplace = array();
		if (!is_numeric($instance) || $instance == '0') return $logic;
		// If this is a repeating event, then gather all fields designated to this event
		$Proj_metadata = $Proj->getMetadata();
		if ($Proj->isRepeatingEvent($event_id)) {
			foreach ($Proj->eventsForms[$event_id] as $thisForm) {
				$thisForm_fields = $Proj->getFormFields($thisForm);
				foreach ($thisForm_fields as $thisField) {
					if ($thisField == $Proj->table_pk) continue;
					if ($Proj->isCheckbox($thisField)) {
						foreach (array_keys(parseEnum($Proj_metadata[$thisField]['element_enum'])) as $thisChoice) {						
							$fieldsReplace["[$thisField($thisChoice)]"] = "[$thisField($thisChoice)][$instance]";
							$fieldsReplace["[$thisField($thisChoice)][$instance]["] = "[$thisField($thisChoice)][";
						}
					} else {
						$fieldsReplace["[$thisField]"] = "[$thisField][$instance]";
						$fieldsReplace["[$thisField][$instance]["] = "[$thisField][";
					}
				}
			}
		}
		// If this is a repeating instrument, then gather all fields on this instrument
		if (isset($Proj->eventsForms[$event_id]) && is_array($Proj->eventsForms[$event_id]) && in_array($instrument, $Proj->eventsForms[$event_id]) && $Proj->isRepeatingForm($event_id, $instrument)) {
			$form_fields = $Proj->getFormFields($instrument);
			foreach ($form_fields as $thisField) {
				if ($thisField == $Proj->table_pk) continue;
				if ($Proj->isCheckbox($thisField)) {
					foreach (array_keys(parseEnum($Proj_metadata[$thisField]['element_enum'])) as $thisChoice) {						
						$fieldsReplace["[$thisField($thisChoice)]"] = "[$thisField($thisChoice)][$instance]";
						$fieldsReplace["[$thisField($thisChoice)][$instance]["] = "[$thisField($thisChoice)][";
					}
				} else {
					$fieldsReplace["[$thisField]"] = "[$thisField][$instance]";
					$fieldsReplace["[$thisField][$instance]["] = "[$thisField][";
				}
			}
		}
		$fieldsReplace = array_unique($fieldsReplace);
		// Perform the replacements
		if (!empty($fieldsReplace)) {
			$logic = str_replace(array_keys($fieldsReplace), $fieldsReplace, $logic);
		}
		// Return the formated logic
		return $logic;
	}


    /**
     * For a general logic string, append all variables with [current-instance] if the
     * variable is from a repeating event/form and does not already the instance number appended.
     */
    public static function logicAppendCurrentInstance($logic, $Proj, $current_event_id=null)
    {
        // Do nothing if there are no repeating events/forms
        if (!$Proj->hasRepeatingFormsEvents()) return $logic;
        $fieldsReplace = $events = $fields = $eventsInstruments = array();
        // Get smart variables
		$smartVars = Piping::getSpecialTags();
		// Get unique event names
		$eventsUnique = $Proj->getUniqueEventNames();
        // Parse the label to pull out the events/fields used therein
        $fieldsEventsLogic = array_keys(getBracketedFields($logic, true, true, false));
		$fieldsLogic = array();
		$Proj_metadata = $Proj->getMetadata();
        foreach ($fieldsEventsLogic as $this_key=>$this_field)
        {
            // If longitudinal with a dot, parse it out and put unique event name in $events array
            if (strpos($this_field, '.') !== false) {
                // Separate event from field
                list ($this_event, $this_field) = explode(".", $this_field, 2);
                // Put event in $events array
                $this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event);
                if (isset($Proj->eventInfo[$this_event_id])) {
                    if (!isset($events[$this_event_id])) $events[$this_event_id] = $this_event;
                    $eventsInstruments[] = $this_event_id."-".$Proj_metadata[$this_field]['form_name'];
                } elseif ($Proj->longitudinal && in_array($this_event, $smartVars)) {
                	// If field has a X-event smart variable, then just add all events as a possibility
					$events = $eventsUnique;
					foreach ($events as $this_event_id2=>$nothing) {
						if (is_array($Proj->eventsForms) && (!isset($Proj->eventsForms[$this_event_id2]) || !in_array($Proj_metadata[$this_field]['form_name'], $Proj->eventsForms[$this_event_id2]))) continue;
						$eventsInstruments[] = $this_event_id2 . "-" . $Proj_metadata[$this_field]['form_name'];
					}
				}
            } else {
                $eventsInstruments[] = (($Proj->longitudinal && $current_event_id != null) ? $current_event_id : $Proj->firstEventId)."-".(isset($Proj_metadata[$this_field]) ? $Proj_metadata[$this_field]['form_name'] : "");
            }
			$fieldsLogic[$this_field] = true;
        }
        $eventsInstruments = array_unique($eventsInstruments);

        // If this is a repeating event, then gather all fields designated to this event
        if ($Proj->longitudinal && $Proj->hasRepeatingEvents()) {
        	if (empty($events)) $events = $Proj->eventInfo;
        	$fieldsOnRepeatingEvent = array();
            foreach (array_keys($events) as $event_id) {
                if ($Proj->isRepeatingEvent($event_id)) {
                	// Replace relevant fields from this repeating event
					if (!isset($Proj->eventsForms[$event_id])) continue;
                    foreach ($Proj->eventsForms[$event_id] as $thisForm) {
						$thisForm_fields = $Proj->getFormFields($thisForm);
                        foreach ($thisForm_fields as $this_field) {
                            if ($this_field == $Proj->table_pk) continue;
                            if (!isset($fieldsLogic[$this_field])) continue;
							$fieldsOnRepeatingEvent[$this_field] = true;
                            if ($Proj->isCheckbox($this_field)) {
                                foreach (array_keys(parseEnum($Proj_metadata[$this_field]['element_enum'])) as $thisChoice) {
                                    $fieldsReplace["[$this_field($thisChoice)]"] = "[$this_field($thisChoice)][current-instance]";
                                    $fieldsReplace["[$this_field($thisChoice)][current-instance]["] = "[$this_field($thisChoice)][";
                                }
                            } else {
                                $fieldsReplace["[$this_field]"] = "[$this_field][current-instance]";
                                $fieldsReplace["[$this_field][current-instance]["] = "[$this_field][";
                            }
                        }
                    }
                }
            }
            // Does logic have any prepended event-name smart variables?
			$hasEventNameSmartVars = (strpos($logic, "event-name][") !== false);
			// In case a field exists on both a repeating and non-repeating event, we need to undo the replacements made above
			foreach (array_keys($fieldsLogic) as $this_field) {
				// If this field doesn't exist on a repeating event, then we're not worried about it
				if (!isset($fieldsOnRepeatingEvent[$this_field])) continue;
				// Find all non-repeating events that have this field
				$this_field_form = $Proj_metadata[$this_field]['form_name'];
				foreach ($Proj->eventsForms as $event_id=>$forms) {
					if ($Proj->isRepeatingEvent($event_id)) continue;
					if (!in_array($this_field_form, $forms)) continue;
					$unique_event_name = $Proj->getUniqueEventNames($event_id);
					if ($Proj->isCheckbox($this_field)) {
						foreach (array_keys(parseEnum($Proj_metadata[$this_field]['element_enum'])) as $thisChoice) {
							$fieldsReplace["[$unique_event_name][$this_field($thisChoice)][current-instance]"] = "[$unique_event_name][$this_field($thisChoice)]";
						}
					} else {
						$fieldsReplace["[$unique_event_name][$this_field][current-instance]"] = "[$unique_event_name][$this_field]";
					}
				}
				// If logic contains an event-name smart variable prepended to fields, then remove [current-instance] from the end of these for better processing
				if ($hasEventNameSmartVars) {
					if ($Proj->isCheckbox($this_field)) {
						foreach (array_keys(parseEnum($Proj_metadata[$this_field]['element_enum'])) as $thisChoice) {
							$fieldsReplace["event-name][$this_field($thisChoice)][current-instance]"] = "event-name][$this_field($thisChoice)]";
						}
					} else {
						$fieldsReplace["event-name][$this_field][current-instance]"] = "event-name][$this_field]";
					}
				}
			}
        }

        // If this is a repeating instrument, then gather all fields in logic from this instrument
        foreach ($eventsInstruments as $eventsInstrument) {
            list ($event_id, $instrument) = explode("-", $eventsInstrument, 2);
            if (!isset($Proj->eventsForms[$event_id]) || !in_array($instrument, $Proj->eventsForms[$event_id])) continue;
            if ($Proj->isRepeatingForm($event_id, $instrument)) {
				$form_fields = $Proj->getFormFields($instrument);
                foreach ($form_fields as $this_field) {
                    if ($this_field == $Proj->table_pk) continue;
					if (!isset($fieldsLogic[$this_field])) continue;
                    if ($Proj->isCheckbox($this_field)) {
                        foreach (array_keys(parseEnum($Proj_metadata[$this_field]['element_enum'])) as $thisChoice) {
                            $fieldsReplace["[$this_field($thisChoice)]"] = "[$this_field($thisChoice)][current-instance]";
                            $fieldsReplace["[$this_field($thisChoice)][current-instance]["] = "[$this_field($thisChoice)][";
                        }
                    } else {
                        $fieldsReplace["[$this_field]"] = "[$this_field][current-instance]";
                        $fieldsReplace["[$this_field][current-instance]["] = "[$this_field][";
                    }
                }
            }
        }
        // Perform the replacements
        if (!empty($fieldsReplace)) {
            $logic = str_replace(array_keys($fieldsReplace), $fieldsReplace, $logic);
        }
        // Return the formated logic
        return $logic;
    }

	/**
	 * Evaluates a condition using existing data and returns value if all variables have values. Otherwise, return FALSE.
	 * @param string $logic is the raw logic provided by the user in the branching logic/Data Quality logic format.
	 * @param array $record_data holds the record data with event_id as first key, field name as second key,
	 * and value as data value (if checkbox, third key is raw coded value with value as 0/1).
	 */
	public static function evaluateCondition($logic=null, $record_data=array(), $funcName=null, $argMap=null, $Proj2=null)
	{
		// If we have neither logic nor function name, then return false
		if (empty($logic) && empty($funcName)) return false;
		// Get Proj object
		if ($Proj2 == null && defined("PROJECT_ID")) {
			global $Proj;
		} else {
			$Proj = $Proj2;
		}
		// If there is an issue in the logic, then return an error message and stop processing
		if ($funcName == null) {
			try {
				// Get unique event names (with event_id as key)
				$events = $Proj->getUniqueEventNames();
				// Instantiate logic parser
				$parser = new LogicParser();
				list ($funcName, $argMap) = $parser->parse($logic, array_flip($events));
				// print $funcName."(){ ".$parser->generatedCode." }";
			}
			catch (LogicException $e) {
				// print "Error: ".$e->getMessage();
				return false;
			}
		}
		// Execute the logic to return boolean (return TRUE if is 1 and not 0 or FALSE)
		return $funcName == "returnEmpty" ? "" : self::applyLogicForEvaluateCondition($funcName, $argMap, $record_data, $Proj);
	}

	/**
	 * Runs a logic condition's result and returns the *COMPLEMENT* of the result;
	 * @param string $funcName the name of the function to execute.
	 * @param array $recordData first key is the event name, second key is the
	 * field name, and third key is either the field value, or if the field is
	 * a checkbox, it will be an array of checkbox codes => values.
	 * @param string $currEventId the event ID of the current record being examined.
	 * @param array $rule_attr a description of the Data Quality rule.
	 * @param array $args used to inform the caller of the arguments that were
	 * actually used in the rule logic function.
	 * @param array $useArgs if given, this function will use these arguments
	 * instead of running $this->buildLogicArgs().
	 * @return 0 if the function returned false, 1 if the result is non-false, and
	 * false if an exception was thrown.
	 */
	static private function applyLogicForEvaluateCondition($funcName, $argMap=array(), $recordData=array(), $Proj=null)
	{
		$args = array();
		try {
			if (!LogicTester::buildLogicArgs($argMap, $recordData, $args, $Proj->firstEventId, $Proj->project_id)) {
				throw new Exception("recordData does not contain the parameters we need");
			}
            if (is_string($funcName) && strpos($funcName, "redcap_func_") === 0 && !function_exists($funcName)) {
                return "";
            }
            $logicCheckResult = call_user_func_array($funcName, $args);
            return $logicCheckResult;
		}
        catch (DivisionByZeroError $e)  { }
        catch (TypeError $e)  { }
        catch (ParseError $e) { }
        catch (Throwable $e)  { }
	}


	// Replace datediff()'s comma with --RC-DDC-- (to be removed later) so it does not interfere with ternary formatting later
	public static function replaceDatediff($string)
	{
		if (strpos($string, "datediff") !== false)
		{
			## Determine which format of datediff() they're using (can include or exclude certain parameters)
			// Include the 'returnSignedValue' parameter
			$regex = "/(datediff)(\s*)(\()([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(\))/";
			if (preg_match($regex, $string))
			{
				$string = preg_replace($regex, "datediff($4--RC-DDC--$6--RC-DDC--$8--RC-DDC--$10--RC-DDC--$12)", $string);
			}
			// Include the 'dateformat' parameter
			$regex = "/(datediff)(\s*)(\()([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(\))/";
			if (preg_match($regex, $string))
			{
				$string = preg_replace($regex, "datediff($4--RC-DDC--$6--RC-DDC--$8--RC-DDC--$10)", $string);
			}
			// Now try pattern without the 'dateformat' parameter (legacy)
			$regex = "/(datediff)(\s*)(\()([^,\(\)]+)(,)([^,\(\)]+)(,)([^,\(\)]+)(\))/";
			if (preg_match($regex, $string))
			{
				$string = preg_replace($regex, "datediff($4--RC-DDC--$6--RC-DDC--$8)", $string);
			}
		}
        return $string;
	}


	// Replace round()'s comma with -ROC- (to be removed later) so it does not interfere with ternary formatting later
	public static function replaceRound($string,$i=0)
	{
		// Deal with round(, if any are present
		if (strpos($string, "round") !== false)
		{
			$regex = "/(round)(\s*)(\()([^,((round)(\s*)(\())]+)(,)([^,((round)(\s*)(\())]+)(\))/";
			// Replace all instances of round() that contain a comma inside so it does not interfere with ternary formatting later
			while (preg_match($regex, $string) && $i++ < 20)
			{
				$string = preg_replace_callback($regex, "LogicTester::replaceRoundCallback", $string);
			}
		}
		// Replace back commas that are not used in round()
		$string = str_replace("-REMOVE-", ",", $string);
		return $string;
	}


	// Callback function for replacing round()'s comma
	public static function replaceRoundCallback($matches)
	{
		// If non-equal number of '(' vs. ')', then send back -REMOVE- to replace as comma to prevent function from
		// going into infinite loops. Otherwise, assume the comma belongs to this round().
		return ((substr_count($matches[4], "(") != substr_count($matches[4], ")")) ? "round(".$matches[4]."-REMOVE-".$matches[6].")" : "round(".$matches[4]."-ROC-".$matches[6].")");
	}


	//Replace ^ exponential form with javascript or php equivalent
	public static function replaceExponents($string, $replaceForPHP=false)
	{
		//First, convert any "sqrt" functions to javascript equivalent
		if (!$replaceForPHP) { // PHP already has a sqrt function
			$first_loop = true;
			while (preg_match("/(sqrt)(\s*)(\()/", $string)) {
				//Ready the string to location "sqrt(" substring easily
				if ($first_loop) {
					$string = preg_replace("/(sqrt)(\s*)(\()/", "sqrt(", $string);
					$first_loop = false;
				}
				//Loop through each character and find outer parenthesis location
				$last_char = strlen($string);
				$sqrt_pos  = strpos($string, "sqrt(");
				$found_end = false;
				$rpar_count = 0;
				$lpar_count = 0;
				$i = $sqrt_pos;
				//Since there are parentheses inside "sqrt", loop through each letter to localize and replace
				if (!preg_match("/(sqrt)(\()([^\(\)]{1,})(\))/", $string)) {
					while ($i <= $last_char && !$found_end) {
						//Keep count of left/right parentheses
						if (substr($string, $i, 1) == "(") {
							$lpar_count++;
						} elseif (substr($string, $i, 1) == ")") {
							$rpar_count++;
						}
						//If found the parentheses boundary, then end loop
						if ($rpar_count > 0 && $lpar_count > 0 && $rpar_count == $lpar_count) {
							$found_end = true;
						} else {
							$i++;
						}
					}
					$inside = substr($string, $sqrt_pos + 5, $i - $sqrt_pos - 5);
					//Replace this instance of "sqrt"
					$string = substr($string, 0, $sqrt_pos) . "Math.pow($inside-EXPC-0.5)" . substr($string, $i + 1);
				//There are no parentheses inside "sqrt", so do simple preg_replace
				} else {
					$string = preg_replace("/(sqrt)(\()([^\(\)]{1,})(\))/", "Math.pow($3-EXPC-0.5)", $string);
				}
			}
		}

		//Find all ^ and locate outer parenthesis for its number and exponent
		$powReplacement = ($replaceForPHP) ? "powRC(" : "Math.pow(";
		$powReplacementLen = strlen($powReplacement);
		$caret_pos = strpos($string, "^");
		$num_carets_total = substr_count($string, "^");
		while ($caret_pos !== false)
		{
			//For first half of string
			$found_end = false;
			$rpar_count = 0;
			$lpar_count = 0;
			$i = $caret_pos;
			while ($i >= 0 && !$found_end) {
				$i--;
				//Keep count of left/right parentheses
				if (substr($string, $i, 1) == "(") {
					$lpar_count++;
				} elseif (substr($string, $i, 1) == ")") {
					$rpar_count++;
				}
				//If found the parentheses boundary, then end loop
				if ($rpar_count > 0 && $lpar_count > 0 && $rpar_count == $lpar_count) {
					$found_end = true;
				}
			}
			//Completed first half of string
			$string = substr($string, 0, $i). $powReplacement . substr($string, $i);
			$caret_pos += $powReplacementLen; // length of "Math.pow(" or "pow("

			//For last half of string
			$last_char = strlen($string);
			$found_end = false;
			$rpar_count = 0;
			$lpar_count = 0;
			$i = $caret_pos;
			while ($i <= $last_char && !$found_end) {
				$i++;
				//Keep count of left/right parentheses
				if (substr($string, $i, 1) == "(") {
					$lpar_count++;
				} elseif (substr($string, $i, 1) == ")") {
					$rpar_count++;
				}
				//If found the parentheses boundary, then end loop
				if ($rpar_count > 0 && $lpar_count > 0 && $rpar_count == $lpar_count) {
					$found_end = true;
				}
			}
			//Completed last half of string
			$string = substr($string, 0, $caret_pos) . "-EXPC-" . substr($string, $caret_pos + 1, $i - $caret_pos) . ")" . substr($string, $i + 1);

			if ($num_carets_total == substr_count($string, "^")) {
				// If the replacement did NOT work, then stop looping or else it'll go on forever
				$caret_pos = false;
			} else {
				// Set again for checking in next loop
				$caret_pos = strpos($string, "^");
			}
		}

		// Re-replace the comma (PHP only)
		if ($replaceForPHP) {
			$string = str_replace("-EXPC-", ",", $string);
		}

		// Return string
		return $string;
	}

	// Convert a string with an IF statement from Excel format - e.g. if(cond, true, false) -
	// to PHP ternary operator format - e.g. if(cond ? true : false).
	public static function convertIfStatement($string, $recursions=0, $ternaryReplacementQuestionMark="?", $ternaryReplacementColon=":", $ternaryReplacementIf="")
	{
		// If we have any nested IFs, space them out some for more accurate parsing
		$string = str_replace("if(", "if( ", $string);
		// Check if has any IF statements
		if (preg_match("/(if)(\s*)(\()/i", $string) && substr_count($string, ",") >= 2 && $recursions < 1000)
		{
			// Remove spaces between "if" and parenthesis so we can more easily parse it downstream
			$string_temp = preg_replace("/(if)(\s*)(\()/i", "if(", $string);
			// Defaults
			$curstr = "";
			$nested_paren_count = 0; // how many nested parentheses we're inside of
			$found_first_comma = false;
			$found_second_comma = false;
			$location_first_comma = null;
			$location_second_comma = null;
			// Only begin parsing at first IF (i.e. only use string_temp)
			list ($cumulative_string, $string_temp) = explode("if(", $string_temp, 2);
			// First, find the first innermost IF in the string and get its location. We'll begin parsing there.
			$string_array = explode("if(", $string_temp);
			foreach ($string_array as $key => $this_string)
			{
				// Check if we should parse this loop
				if ($this_string != "")
				{
					// If current string is empty, then set it as this_string, otherwise prepend curstr from last loop to this_string
					$curstr .= $this_string;
					// Check if this string has ALL we need (2 commas, 1 right parenthesis, and num right parens = num left parens+1)
					$num_commas 	 = substr_count($curstr, ",");
					$num_left_paren  = substr_count($curstr, "(");
					$num_right_paren = substr_count($curstr, ")");
					$hasCompleteIfStatement = ($num_commas >= 2 && $num_right_paren > 0 && $num_right_paren > $num_left_paren);
					if ($hasCompleteIfStatement)
					{
						// The entire IF statement MIGHT be in this_string. Check if it is (commas and parens in correct order).
						$curstr_len = strlen($curstr);
						// Loop through the string letter by letter
						for ($i = 0; $i < $curstr_len; $i++)
						{
							// Get current letter
							$letter = substr($curstr, $i, 1);
							// Perform logic based on current letter and flags already set
							if ($letter == "(") {
								// Increment the count of how many nested parentheses we're inside of
								$nested_paren_count++;
							} elseif ($letter != ")" && $nested_paren_count > 0) {
								if ($i+1 == $curstr_len) {
									// This is the last letter of the string, and we still haven't completed the entire IF statement.
									// So reset curstr and go to next loop, which should have a nested IF (we'll work our way outwards)
									$cumulative_string .= "if($curstr";
									$curstr = "";
								} else {
									// We're inside a nested parenthesis, so there's nothing to do -> keep looping till we get out
								}
							} elseif ($letter == ")" && $nested_paren_count > 0) {
								// We just left a nested parenthesis, so reduce count by 1 and keep looping
								$nested_paren_count--;
							} elseif ($letter == "," && $nested_paren_count == 0 && !$found_first_comma) {
								// Found first valid comma AND not in a nested parenthesis
								$found_first_comma = true;
								$found_second_comma = false;
								$location_first_comma = $i;
								$location_second_comma = null;
							} elseif ($letter == "," && $nested_paren_count == 0 && $found_first_comma && !$found_second_comma) {
								// Found second valid comma AND not in a nested parenthesis
								$found_second_comma = true;
								$location_second_comma = $i;
							} elseif ($letter == ")" && $nested_paren_count == 0 && $found_first_comma && $found_second_comma) {
								// Found closing valid parenthesis of IF statement, so replace the commas with ternary operator format
								$cumulative_string .= "$ternaryReplacementIf(" . substr($curstr, 0, $location_first_comma)
													. " $ternaryReplacementQuestionMark " . substr($curstr, $location_first_comma+1, $location_second_comma-($location_first_comma+1))
													. " $ternaryReplacementColon " . substr($curstr, $location_second_comma+1);
								// Reset values for further processing
								$curstr = "";
								$found_first_comma = false;
								$found_second_comma = false;
								$location_first_comma = null;
								$location_second_comma = null;
							}
						}
					} else {
						// The entire IF statement is NOT in this_string, therefore there must be a nested IF after this one.
						// Reset curstr and begin anew with next nested IF (we'll work our way outwards from the innermost IFs)
						$cumulative_string .= "if($curstr";
						$curstr = "";
					}
				}
			}
			// If the string still has IFs because of nesting, then do recursively.
			return self::convertIfStatement($cumulative_string, ++$recursions, $ternaryReplacementQuestionMark, $ternaryReplacementColon, $ternaryReplacementIf);
		}
		// Now that we're officially done parsing, return the string
		return $string;
	}
	
	// Callback function for replacing [instance]
	public static function replaceInstanceCallback($matches)
	{
		global $Proj, $repeatingFieldsEventInfo;
		$Proj_metadata = $Proj->getMetadata();
		// Get parts
		$instance = isset($matches[4]) ? $matches[4] : null;
		$field = $matches[2];
		$event_name = $matches[1];
		if ($event_name == "") {
			$event_id = (isset($_GET['event_id']) ? $_GET['event_id'] : "");
		} else {
			$event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
			if (isset($_GET['event_id']) && $event_id == $_GET['event_id']) {
				// If prepended event name is same as the event we're on, then just remove it (not necessary)
				$event_name = "";
			}
		}
		// Find checkbox code, if any
		$checkboxCode = "";
		if (strpos($field, "(")) {
			list ($field, $checkboxCode) = explode("(", $field, 2);
		}
		if (substr($checkboxCode, -1) == ")") $checkboxCode = substr($checkboxCode, 0, -1);
		// Is this field on the current event-form-instance?
		$fieldOnCurrentPage = (is_numeric($instance) && $instance == $_GET['instance'] && $event_id == $_GET['event_id'] && isset($Proj_metadata[$field]) && $Proj_metadata[$field]['form_name'] == $_GET['page']);
		// Get name of HTML form
		$form = ($event_name == "") ? "form" : "form__".$event_name;
		if (is_numeric($instance) 
			// If field is currently on this page, then don't render using ____I format but normal format.
			&& !$fieldOnCurrentPage
		){
			// Has instance
			$repeat_instrument = $Proj->isRepeatingEvent($event_id) ? "" : ($Proj_metadata[$field]['form_name'] ?? "");
			if ($checkboxCode == "") {
				$returnString = "document.{$form}.{$field}____I{$instance}.value";
			} else {
				$returnString = "(if(document.forms['{$form}'].elements['__chk__{$field}_RC_".DataEntry::replaceDotInCheckboxCoding($checkboxCode)."____I{$instance}'].value=='',0,1))";
			}
		} else {
			// Has no instance
			$repeat_instrument = "";
			$form = ($event_name == "") ? "form" : "form__".$event_name;			
			if ($checkboxCode == "") {
				$returnString = "document.{$form}.{$field}.value";
			} else {
				$returnString = "(if(document.forms['{$form}'].elements['__chk__{$field}_RC_".DataEntry::replaceDotInCheckboxCoding($checkboxCode)."'].value=='',0,1))";
			}
		}
		// For repeating instances, add to $repeatingFieldsEventInfo array so we can manually add these to the HTML form downstream
		if ($instance != "") {
			$repeatingFieldsEventInfo[$event_id][$repeat_instrument][$instance][$field] = true;
		}
		// Return the JS string
		return $returnString;
	}
	
	// Format a logic string into JS notation (including converting fields into JS notation)
	public static function formatLogicToJS($string, $doExtraCalcFormatting=false, $current_event_id=null, $returnFieldsUtilized=false, $project_id=null)
	{
		global $Proj;
		
		// REPLACE ANY STRINGS IN QUOTES WITH A PLACEHOLDER BEFORE DOING THE OTHER REPLACEMENTS:
		$lqp = new LogicQuoteProtector();
		$string = $lqp->sub($string);

		//Replace operators in equation with javascript equivalents (Strangely, the < character causes issues with str_replace later when it has no spaces around it, so add spaces around it)
		$orig = array("\t", "\r\n", "\n", "\r", "<"  , "=" , "===", "====", "> ==", "< ==", ">  ==", "<  ==", ">==", "<==", "< >", "<>", " and ", " AND ", " or ", " OR ", "!==", "\\");
		$repl = array(" ",  " ",    " ",  " ",  " < ", "==", "==" , "=="  , ">="  , "<="  , ">="   , "<="   , ">=" , "<="  , "!=" , "!=", " && " , " && ", " || ", " || ", "!=",  "");
		$string = str_replace($orig, $repl, $string);
		
		// UNDO THE REPLACEMENT BEFORE EVALUATING THE EXPRESSION
		$string = $lqp->unsub($string, true);
			
		// For better parsing downstream, remove spaces around operators
        // $string = preg_replace("/(\s*)(=|!=|\<\>|\>|\>=|\<|\<=)(\s*)/", "$2", $string); // Not needed due to the changes in commit ba28351

		// Get list of field names used in string (Calc fields only)
		if ($doExtraCalcFormatting || $returnFieldsUtilized) {
			$fields_utilized = getBracketedFields($string, true, true, true);
		}

		// Replace field_name in brackets with javascript equivalent
		$string = preg_replace_callback('/(?:\[([a-z0-9][_a-z0-9]*)\])?\[([a-z][_.a-zA-Z0-9:\(\)-]*)\](\[(\d+)\])?/', "LogicTester::replaceInstanceCallback", $string);
		
		$valTypes = getValTypes();
		$eventNames = $Proj->getUniqueEventNames();

		$checkRepeatingInstances = ($Proj->hasRepeatingFormsEvents() && strpos($string, "____I"));

		$Proj_metadata = $Proj->getMetadata();
		// CALC FIELDS ONLY
		if ($doExtraCalcFormatting)
		{
			// Replace field names with javascript equivalent
			foreach (array_keys($fields_utilized) as $this_field)
			{
				if (!isset($Proj_metadata[$this_field])) continue;
				// If field is NOT a Text field OR is a number/integer-validated Text field, then wrap the field with chkNull function to
				// ensure that we get either a numerical value or NaN.
				$fieldType = $Proj_metadata[$this_field]['element_type'];
				// Is this a MC field with all-numeric codings?
				$mcFieldWithNumCodings = ($Proj->isMultipleChoice($this_field) && arrayHasOnlyNums(array_keys(parseEnum($Proj_metadata[$this_field]['element_enum']))));
				$fieldValidation = $Proj_metadata[$this_field]['element_validation_type'];
				if ($fieldValidation == 'float') $fieldValidation = 'number';
				if ($fieldValidation == 'int') $fieldValidation = 'integer';
				$fieldDataType = $valTypes[$fieldValidation]['data_type'] ?? '';
				if ($mcFieldWithNumCodings || $fieldDataType == 'number' || $fieldDataType == 'number_comma_decimal' || $fieldDataType == 'integer' || $fieldType == 'calc' || $fieldType == 'slider' ||
					$fieldType == 'yesno' || $fieldType == 'truefalse' || $fieldType == 'file')
				{
					// Replace
					$string = str_replace("document.form.$this_field.value", "chkNull(document.form.$this_field.value)", $string);
					// Replace fields on repeating forms/events
					if ($checkRepeatingInstances) {
						$string = preg_replace("/(document\.form\.{$this_field}____I)(\d+)(\.value)/", "chkNull($1$2$3)", $string);
					}
					// Also, if longitudinal, loop through all events where this field's form is utilized and replace in that format
					$fieldForm = $Proj_metadata[$this_field]['form_name'];
					if ($Proj->longitudinal) {
						foreach ($eventNames as $this_event_id=>$this_event_name) {
							// Skip if field not used on this event
							if (is_array($Proj->eventsForms) && (!isset($Proj->eventsForms[$this_event_id]) || !in_array($fieldForm, $Proj->eventsForms[$this_event_id]))) continue;
							// Replace event format
							$string = str_replace("document.form__$this_event_name.$this_field.value", "chkNull(document.form__$this_event_name.$this_field.value)", $string);
							// Replace fields on repeating forms/events
							if ($checkRepeatingInstances) {
								$string = preg_replace("/(document\.form__{$this_event_name}\.{$this_field}____I)(\d+)(\.value)/", "chkNull($1$2$3)", $string);
							}
						}
					}
				}
			}
			
			// While chkNull returns "NaN", if we have [date]="" or [date]<>"", it ends up as ""=="NaN" and ""!="NaN" when the field 
			// is blank, which is incorrect. So we need to make sure that all date fields being checked as blank/non-blank are
			// referencing "" and not "NaN", which is replaced by REDCap automatically upstream.
			foreach (array('"', "'") as $quote) {
                $string = preg_replace("/(\.value)(\s*)(==|!=)(\s*)({$quote})NaN({$quote})/", "$1$2$3$4$5$6", $string);
                $string = preg_replace("/({$quote})NaN({$quote})(\s*)(==|!=)(\s*)(document\.)/", "$1$2$3$4$5$6", $string);
			}

			// Now swap all "+" with "*1+1*" in the equation to work around possibility of JavaScript concatenation in some cases
			if (strpos($string, "+") !== false) {
				$hasConcat = (strpos($string, "concat") !== false && preg_match("/(concat)(\s*)(\()/", $string));
				$hasIf = (strpos($string, "if") !== false && preg_match("/(if)(\s*)(\()/", $string));
                $hasConcatOrIf = ($hasConcat || $hasIf);
				if ($hasConcatOrIf) {
					$lqp = new LogicQuoteProtector();
					$string = $lqp->sub($string);
				}
                LogicParser::xConcatJs($string);
				if ($hasConcatOrIf) {
					$string = $lqp->unsub($string, true);
				}
			}
		}
		// BRANCHING LOGIC ONLY
		else
		{
			// Wrap integer/number data type fields with chkNull() to allow proper processing of them as numbers
			// Replace field names with javascript equivalent
			if (empty($fields_utilized)) $fields_utilized = array();
			foreach (array_keys($fields_utilized) as $this_field)
			{
				if (!isset($Proj_metadata[$this_field])) continue;
				$fieldType = $Proj_metadata[$this_field]['element_type'];
				$fieldValidation = $Proj_metadata[$this_field]['element_validation_type'];
				if ($fieldValidation == 'float') $fieldValidation = 'number';
				if ($fieldValidation == 'int') $fieldValidation = 'integer';
				$fieldDataType = isset($valTypes[$fieldValidation]['data_type']) ? $valTypes[$fieldValidation]['data_type'] : '';
				if ($fieldType == 'calc' || $fieldType == 'slider' || $fieldDataType == 'number' || $fieldDataType == 'number_comma_decimal' || $fieldDataType == 'integer')
				{
					// Make sure that any fields being referenced as ="" or <>"" to replace "" with "NaN" since chkNull() will return "NaN" when the field is blank
					foreach (array('"', "'") as $quote) {
                        $string = preg_replace("/(document\.form\.{$this_field}\.value)(\s*)(==|!=)(\s*)({$quote})({$quote})/", "$1$2$3$4$5NaN$6", $string);
                        $string = preg_replace("/({$quote})({$quote})(\s*)(==|!=)(\s*)(document\.form\.{$this_field}\.value)/", "$1NaN$2$3$4$5$6", $string);
					}
					// Replace
					$string = str_replace("document.form.$this_field.value", "chkNull(document.form.$this_field.value,1)", $string);
					// Replace fields on repeating forms/events
					if ($checkRepeatingInstances) {
						$string = preg_replace("/(document\.form\.{$this_field}____I)(\d+)(\.value)/", "chkNull($1$2$3)", $string);
					}
					// Also, if longitudinal, loop through all events where this field's form is utilized and replace in that format
					if ($Proj->longitudinal) {
						$fieldForm = $Proj_metadata[$this_field]['form_name'];
						foreach ($eventNames as $this_event_id=>$this_event_name) {
							// Skip if field not used on this event
							if (is_array($Proj->eventsForms) && isset($Proj->eventsForms[$this_event_id]) && !in_array($fieldForm, $Proj->eventsForms[$this_event_id])) continue;
							// Make sure that any fields being referenced as ="" or <>"" to replace "" with "NaN" since chkNull() will return "NaN" when the field is blank
							foreach (array('"', "'") as $quote) {
                                $string = preg_replace("/(document\.form__{$this_event_name}\.{$this_field}\.value)(\s*)(==|!=)(\s*)({$quote})({$quote})/", "$1$2$3$4$5NaN$6", $string);
                                $string = preg_replace("/({$quote})({$quote})(\s*)(==|!=)(\s*)(document\.form__{$this_event_name}\.{$this_field}\.value)/", "$1NaN$2$3$4$5$6", $string);
							}
							// Replace event format
							$string = str_replace("document.form__$this_event_name.$this_field.value", "chkNull(document.form__$this_event_name.$this_field.value,1)", $string);
							// Replace fields on repeating forms/events
							if ($checkRepeatingInstances) {
								$string = preg_replace("/(document\.form__{$this_event_name}\.{$this_field}____I)(\d+)(\.value)/", "chkNull($1$2$3)", $string);
							}
						}
					}
				}
			}
		}

		// Replace ^ exponential form with javascript equivalent
		$string = self::replaceExponents($string);

		// Temporarily swap out commas in any datediff() functions (so they're not confused in IF statement processing).
		// They will be replaced back at the end.
		$string = self::replaceDatediff($string);

		// Temporarily swap out commas in any round() functions (so they're not confused in IF statement processing).
		// They will be replaced back at the end.
		$string = self::replaceRound($string);

		// REPLACE ANY STRINGS IN QUOTES WITH A PLACEHOLDER BEFORE DOING THE OTHER REPLACEMENTS:
		$lqp = new LogicQuoteProtector();
		$string = $lqp->sub($string);

		// If using conditional logic, format any conditional logic to Javascript ternary operator standards
		$string = self::convertIfStatement($string);

        // Replace year/month/day function param to pass an object instead of a value
        $dateFunctions = ['year', 'month', 'day'];
        $loops = 0;
        $maxLoops = 5000;
        while ($loops < $maxLoops && preg_match("/\b(".implode("|", $dateFunctions).")(\s*)(\()/", $string))
        {
            foreach ($dateFunctions as $thisDateFunc) {
                $stringInside = Form::getValueInParenthesesActionTag($string, $thisDateFunc);
                if ($stringInside != "") {
                    try {
                        $string = preg_replace("/\b(".$thisDateFunc.")\s*\(\s*(".preg_quote($stringInside).")(\s*\))/", "$1RC(".str_replace(".value", "", $stringInside).")", $string);
                    } catch (Throwable $e)  { }
                }
            }
            $loops++;
        }

		// UNDO THE REPLACEMENT BEFORE EVALUATING THE EXPRESSION
		$string = $lqp->unsub($string);

		// Auto-insert the datediff() date format parameter (if not present)
		if ($project_id != null && strpos($string, "--RC-DDC--") !== false)
		{
			$Proj = new Project($project_id);
            $hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
			$datediff_array = explode("datediff(", $string);
			foreach ($datediff_array as $key=>$item) {
				if (strpos($item, "--RC-DDC--") !== false) {
					$datediff_array[$key] = $item = array_map('ltrim', explode("--RC-DDC--", $item));
					// If the fourth parameter is missing, then determine the field's date format and add it as the fourth parameter IF it is dmy or mdy
					if (!isset($item[3]) && isset($item[2]))
					{
						$closingParen = strpos($item[2], ")");
						if ($closingParen !== false && (strpos($item[0], "document.") !== false || strpos($item[1], "document.") !== false)) {
							$datediff_field_param = (strpos($item[0], "document.") !== false) ? $item[0] : $item[1];
							$datediff_field_array = explode(".", $datediff_field_param, 4);
							$current_field = $datediff_field_array[2] ?? '';
                            if ($hasRepeatingFormsEvents && strpos($current_field, "____I") !== false) {
                                // If this field is referenced from another event from a repeating event/form, it will have ____I appended to the variable name in the JS
                                list ($current_field, $nothing) = explode("____I", $current_field, 2);
                            }
							if (isset($Proj_metadata[$current_field])) {
								$datediff_field_format = substr($Proj_metadata[$current_field]['element_validation_type'], -3);
								if ($datediff_field_format == 'mdy' || $datediff_field_format == 'dmy') {
									$item[2] = substr($item[2], 0, $closingParen) . "--RC-DDC--'$datediff_field_format'" . substr($item[2], $closingParen);
								}
							}
						}
					}
					// If the fourth parameter is false or true
					elseif (stripos($item[3], 'false') === 0 || stripos($item[3], '"false"') === 0 || stripos($item[3], "'false'") === 0
						 || stripos($item[3], 'true') === 0 || stripos($item[3], '"true"') === 0 || stripos($item[3], "'true'") === 0)
					{
						$datediff_field_param = (strpos($item[0], "document.") !== false) ? $item[0] : $item[1];
						$datediff_field_array = explode(".", $datediff_field_param, 4);
                        $current_field = $datediff_field_array[2] ?? '';
                        if ($hasRepeatingFormsEvents && strpos($current_field, "____I") !== false) {
                            // If this field is referenced from another event from a repeating event/form, it will have ____I appended to the variable name in the JS
                            list ($current_field, $nothing) = explode("____I", $current_field, 2);
                        }
						if (isset($Proj_metadata[$current_field])) {
							$datediff_field_format = substr($Proj_metadata[$current_field]['element_validation_type'], -3);
							if ($datediff_field_format == 'mdy' || $datediff_field_format == 'dmy') {
								$item[3] = "'$datediff_field_format'--RC-DDC--" . $item[3];
							}
						}
					}
					$datediff_array[$key] = $item = implode("--RC-DDC--", $item);
				}
			}
			$string = implode("datediff(", $datediff_array);
        }

		// Now swap datediff() commas back into the equation (was replaced with --RC-DDC--)
		if (strpos($string, "--RC-DDC--") !== false) $string = str_replace("--RC-DDC--", ",", $string);

		// Now swap round() commas back into the equation (was replaced with -ROC-)
		if (strpos($string, "-ROC-") !== false) $string = str_replace(array("-ROC-)","-ROC-"), array(")",","), $string);

		// Now swap sqrt() or exponential commas back into the equation (was replaced with --RC-DDC--)
		if (strpos($string, "-EXPC-") !== false) $string = str_replace("-EXPC-", ",", $string);

		// Replace isblankormissingcode(chkNull([field])) with isblankormissingcode(([field])) - same for ismissingcode(), hasmissingcode(), and isblanknotmissingcode()
		// See https://regex101.com/r/qWRtQ9/1
		$string = preg_replace("/(isblankormissingcode|ismissingcode|isblanknotmissingcode|hasmissingcode)(\()(\s*)(chkNull)(\s*)(\()(.*)(,1){0,1}\)(\))/U", "$1$2$7$9", $string);

		// Replace any instances of ":value.value" where a user has attempted to use [field:value] in the logic/calculation
		$string = str_replace(":value.value", ".value", $string);

		// If the form status field is referenced on its own form, typical JS will reference the value of the drop-down,
		// which is a problem if the form has a gray status icon, in which its true status="", not "0" (the value of the drop-down).
		// To get around this, replace the form status field of the current form in the JS with a slightly different JS attr on that element that stores its true value.
		if (defined("PAGE") && PAGE == "DataEntry/index.php" && isset($_GET['id']) && isset($_GET['page'])) {
			$string = str_replace("document.form.{$_GET['page']}_complete.value", "document.form.{$_GET['page']}_complete.getAttribute('realvalue')", $string);
		}

		// Return array of formatted string and fields utilized in the branching logic
		if ($returnFieldsUtilized) {
			// Return array
			return array($string, array_keys($fields_utilized));
		} 
		// Return formatted string
		else {
			return $string;
		}
	}

}