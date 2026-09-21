<?php

use MultiLanguageManagement\MultiLanguage;
use REDCap\Context;

/**
 * SurveyScheduler
 * This class is used for setup and execution of the survey scheduler.
 */
class SurveyScheduler
{
	// Current project_id for this object
	public $project_id = null;
	// Array of schedules from the surveys_scheduler table
	public $schedules = null;
	// Array with PK from surveys_scheduler table (ss_id) as array key and survey_id=>event_id as array subkeys.
	// Can be used to link directly to $schedules array using ss_id instead of survey_id-event_id.
	private $schedulePkLink = null;
	// Set default limit for number of emails to send in one batch per cron job instance.
	// This will be used if cannot be determined from values in redcap_surveys_emails_send_rate table.
	// (ideal batch = 5 minutes long to send, so default to ~3 emails/sec)
	const MAX_EMAILS_PER_BATCH = 1000;
	// Set minimum emails per batch
	const MIN_EMAILS_PER_BATCH = 100;
	// Set the ideal length of time for a full email batch to send
	const BATCH_LENGTH_MINUTES = 5;
	// Set the minimum number of emails sent in a batch that would constitute its email rate getting added to
	// the redcap_surveys_emails_send_rate table to thus be used in future calculations for determining email batch size.
	const MIN_RECORD_EMAILS_SENT = 20;
	// Is this scheduler being used for datediff+today cron
	public $datediff_today_check = false;
    public $datediff_cron_queued_records = null;
	// Fields set by cacheProject method
	public $logic_fields = null;
	public $logic_events = null;
	public $parser_cache = null;
	public $record_data = null;
	public $record_schedules = null;
	public $num_scheduled_total = 0;
	public $start_memory;
	public $debug=false;

	public function debug($msg)
	{
		// ABM: A NICE DEBUG TABLE TO SHOW PROGRESS IN EVALUATING PROJECTS
		// $debug_log_file = "/var/log/redcap/asi2_ss_debug.log";
		// $debug_log_file = APP_PATH_TEMP . date('YmdHis') . "_" . __CLASS__ . "-detail.log";
		// $msg = "[" . date("Y-m-d H:i:s") . "]\t" . $this->project_id . "\t" . $msg . "\n";
		// if (!empty($debug_log_file)) file_put_contents($debug_log_file, $msg, FILE_APPEND);
	}

	// ABM: Loops through each survey logic and caches parser functions, record data, and record schedules for the project
	public function cacheProjectSurveyFunctions()
	{
		$Proj = new Project($this->project_id);

		// Get array of all schedules for this project
		$this->setSchedules();

		// Create arrays to store logic fields, events, and parser functions used for this project
		$logic_fields = array();
		$logic_events = array();
		$parser_cache = array();    // funcNames/argMaps for parsing the ASI logic, stored as an array of [survey_id][event_id] = array(funcName, argMap)

		// Get unique event names (with event_id as key)
		$unique_events = $Proj->getUniqueEventNames();

        // Get smart variables
        $smartVars = Piping::getSpecialTagsFormatted(false, false);

		// Loop through surveys
		foreach ($this->schedules as $survey_id => $events)
        {
			// Loop through events
			foreach ($events as $event_id => $data)
            {
                $condition_logic = $data['condition_logic'] ?? "";

                // Optimization 1: Skip ASI if not datediff+today/now
                if ($this->datediff_today_check && !(strpos(strtolower($condition_logic), "datediff") !== false &&
                        (strpos(strtolower($condition_logic), "today") !== false || strpos(strtolower($condition_logic), "now") !== false))) {
                    continue;
                }

                // If logic contains smart variables, then we'll need to do the logic parsing *per item* rather than at the beginning
                $logicContainsSmartVariables = Piping::containsSpecialTags($condition_logic);

                // Optimization 2: Cache the parser functions and arguments
                $funcName = null;
                if (!$logicContainsSmartVariables) {
                    try {
                        // Instantiate logic parser
                        $parser = new LogicParser();
                        list ($funcName, $argMap) = $parser->parse($condition_logic, array_flip($unique_events));
                        unset($parser);
                        $parser_cache[$survey_id][$event_id] = array($funcName, $argMap);
                    } catch (LogicException $e) {
                        // TODO: Optional DEBUG statement to identify errors...
                        $this->debug("ERROR generating parser for $condition_logic " .
                            "in Project: $this->project_id / Survey: $survey_id / Event: $event_id / Error: " . $e->getMessage());
                        continue;
                    }
                } else {
                    $parser_cache[$survey_id][$event_id] = array();
                }

                // Optimization 3: Limit the fields/events to those used in the ASI function
                foreach (array_keys(getBracketedFields($condition_logic, true, true, false)) as $this_field)
                {
                    // Check if has dot (i.e. has event name included)
                    if (strpos($this_field, ".") !== false) {
                        list ($this_event_name, $this_field) = explode(".", $this_field, 2);
                        $logic_events[] = $this_event_name;
                    }
                    // Verify that the field really exists (may have been deleted). If so, skip it.
                    if (!isset($Proj->metadata[$this_field]) && !in_array($this_field, $smartVars)) {
                        $this->debug("ERROR: The logic from survey $survey_id in event $event_id contains an invalid field $this_field");
                        continue;
                    }
                    // Add field to array
                    $logic_fields[] = $this_field;
                }
			} // end events
		} // end surveys

		// Remove duplicates fields/events
		$logic_fields = array_values(array_unique($logic_fields));
		$logic_events = array_values(array_unique($logic_events));
		// If event array contains an event-name Smart Variable, then add ALL events instead
		foreach ($logic_events as $this_event_name) {
			if (strpos($this_event_name, "event-name") !== false) {
				$logic_events = array_values($Proj->getUniqueEventNames());
				break;
			}
		}

		// Store results in SurveyScheduler object
		$this->logic_fields = $logic_fields;
		$this->logic_events = $logic_events;
		$this->parser_cache = $parser_cache;

		return true;
	}

	// ABM: Cache Record Data and Record-specific Schedules
	public function cacheProjectSurveyData($Proj=null)
	{
		if ($Proj == null) $Proj = (object)[];
		// If logic_fields are not set, then return false - nothing to do
		if (empty($this->logic_fields)) return false;

		// Build record list cache if not yet built for this project
		Records::buildRecordListCacheCurl($this->project_id);

		// Load the data for this project based on filters generated above
		$data_params = ['project_id'=>$this->project_id, 'returnEmptyEvents'=>true, 'fields'=>$this->logic_fields, 'events'=>$this->logic_events];
        // If we're not processing all records, then we should ONLY process the records queued in the redcap_crons_datediff table
        if ($this->datediff_today_check) {
            $data_params['records'] = array_keys($this->datediff_cron_queued_records[$this->project_id]) ?? [];
        }
		$this->record_data = Records::getData($data_params);

		// Add default values array
		if ($Proj->longitudinal && count($this->logic_events) > 1) 
		{
			// Get default values for all records (all fields get value '', except Form Status and checkbox fields get value 0)
			$default_values = array();
			foreach ($this->logic_fields as $this_field)
			{
				// If is a real field or not
				if (isset($Proj->metadata[$this_field]))
				{
					// Loop through all designated events so that each event
					foreach ($this->logic_events as $this_event_name)
					{
						// Get event id
						$this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event_name);
						// For longitudinal projects, ensure that this instrument has been designated for an event
						if ($Proj->longitudinal && (!isset($Proj->metadata[$this_field]['form_name']) || !isset($Proj->eventsForms[$this_event_id])
								|| !in_array($Proj->metadata[$this_field]['form_name'], $Proj->eventsForms[$this_event_id]))) {
							continue;
						}
						// Check a checkbox or Form Status field
						if ($Proj->metadata[$this_field]['element_type'] == 'checkbox') {
							// Loop through all choices and set each as 0
							foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $choice) {
								$default_values[$this_event_id][$this_field][$choice] = '0';
							}
						} elseif ($this_field == $Proj->metadata[$this_field]['form_name'] . "_complete") {
							// Set as 0
							$default_values[$this_event_id][$this_field] = '0';
						} else {
							// Set as ''
							$default_values[$this_event_id][$this_field] = '';
						}
					}
				}
			}
			// Now add default values for any missing events for each record
			if (!empty($default_values)) {
				foreach ($this->record_data as $record=>$event_data) {
					$missingEvents = array_diff(array_keys($default_values), array_keys($event_data));
					if (!empty($missingEvents)) {
						foreach ($missingEvents as $missingEventId) {
							// Only add this default data to event if we're utilizing the event in the logic
							if (!isset($event_data[$missingEventId])) {
								$this->record_data[$record][$missingEventId] = $default_values[$missingEventId];
							}
						}
					}
				}
			}
		}

		// For each record, calculate and cache the available schedules for that record.
		$this->record_schedules = array();
		foreach ($this->record_data as $record_id => $data) {
			$this->record_schedules[$record_id] = $this->getAvailableSchedulesForRecord($record_id);
		}

		return true;
	}

    // Gather project_ids/records of all the projects with queued records for this batch (ordered last updated time to most recent updated time)
    public function checkAutomatedSurveyInvitationsBulkGetQueuedRecords()
    {
        $this->datediff_cron_queued_records = [];
        $sql = "select project_id, record from redcap_crons_datediff
                where asi_status = 'QUEUED'
                order by asi_updated_at, dd_id
                limit " . Jobs::RECORD_EVAL_LIMIT_PER_BATCH;
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $this->datediff_cron_queued_records[$row['project_id']][$row['record']] = true;
        }
        // Set all records to PROCESSING status
        foreach ($this->datediff_cron_queued_records as $project_id=>$recordKeys)
        {
            $sql = "update redcap_crons_datediff set asi_status = 'PROCESSING', asi_last_update_start = '".date('Y-m-d H:i:s')."'
                    where project_id = $project_id and record in (" . prep_implode(array_keys($recordKeys)) . ")";
            db_query($sql);
        }
    }

	// ABM: Custom workflow designed for bulk checking of all ASI's for the project
	public function checkAutomatedSurveyInvitationsBulk($datediff_cron_queued_records)
	{
        $this->datediff_cron_queued_records = $datediff_cron_queued_records;
		// Preload all survey parsing fields/events/functions
		if (!$this->cacheProjectSurveyFunctions()) {
			// error caching project
			return false;
		};

		// Get $Proj object
		global $Proj;
		if (!isset($Proj) || !is_array($Proj) || $Proj->project_id != $this->project_id) {
			$Proj = new Project($this->project_id);
		}

		// Preload all survey data and record schedules
		if (!$this->cacheProjectSurveyData($Proj)) {
			// error caching data
			return false;
		};

        // Get unique event names (with event_id as key)
        $events = $Proj->getUniqueEventNames();
        $eventsFlipped = array_flip($events);

		// Get event_id => event_name array
		$unique_events = $Proj->getUniqueEventNames();

		// Set initial return value as 0
		$numInvitationsScheduled = 0;

		// Collect survey_id/event_id of scheduled invitations that need to be removed because logic is false
		$schedulesToRemove = array();

		// Loop each survey_id from cache
		foreach ($this->parser_cache as $survey_id => $event_ids)
        {
			// Loop through each event
			foreach ($event_ids as $event_id => $funcArgArray)
            {
                // If we have reached response limit, then skip to next event
                if (Survey::reachedResponseLimit($this->project_id, $survey_id, $event_id)) {
                    $this->debug("Response limit reached in ASI for survey $survey_id / event $event_id");
                    continue;
                }

                // Reset a invitation counter for this survey-event
                $survey_event_invitations_scheduled = 0;

                $instanceNum = 1;

                // Load the funcName and argMap once for all records
                if (!empty($funcArgArray)) list($funcName, $argMap) = $funcArgArray;

                // Loop through each record and evaluate the function
                foreach ($this->record_data as $record => $record_data) {

                    // Verify that this record should be evaluated (as determined by the setSchedules function)
                    if (empty($this->record_schedules[$record][$survey_id][$event_id])) {
                        continue;
                    }

                    // Load the current record's schedule
                    $thisSchedule = $this->record_schedules[$record][$survey_id][$event_id];

                    // If we have Smart Variables, parse logic with Smart Variables right here
                    if (empty($funcArgArray)) {
                        $funcName = null;
                        try {
                            // Instantiate logic parse
                            $parser = new LogicParser();
                            $logicThisItem = Piping::pipeSpecialTags($this->schedules[$survey_id][$event_id]['condition_logic'], $this->project_id, $record, $event_id, $instanceNum, null, true, null, $Proj->surveys[$survey_id]['form_name'], false, false, false, true, false, false, true);
                            list($funcName, $argMap) = $parser->parse($logicThisItem, $eventsFlipped);
                        } catch (LogicException $e) {
                            continue;
                        }
                    }

                    // For longitudinal projects, ensure that $argMap has an event_id for key 0 for each variable in order to get processed correctly
                    if ($Proj->longitudinal) {
                        foreach ($argMap as $thisKey=>$thisAttr) {
                            if ($thisAttr[0] == "") $argMap[$thisKey][0] = $event_id;
                        }
                    }

                    // A modified checkConditions that includes more cached data
                    $readyToSchedule = $this->checkConditionsOfRecordToScheduleBulk($thisSchedule, $record, $record_data, $funcName, $argMap, $Proj->firstEventId);

                    if ($readyToSchedule) {
                        // Schedule the participant's survey invitation to be sent by adding it to the scheduler_queue table
                        $invitationWasScheduled = $this->scheduleParticipantInvitation($survey_id, $event_id, $record, $instanceNum);
                        if ($invitationWasScheduled) {
                            // Increment number of invitations scheduled just now
                            $survey_event_invitations_scheduled++;
                        }
                    } // If it is not ready to schedule but is to be re-evaluated, double-check status
                    elseif ($thisSchedule['reeval_before_send'] == '1') {
                        $schedulesToRemove[$record][$survey_id][$event_id] = true;
                    }
                } // end records

                // Increment the total project invitations
                $numInvitationsScheduled += $survey_event_invitations_scheduled;
			} // end event
		} // end survey

		// Remove any schedules that have been scheduled but data values changed and caused ASI to be nullified
		foreach ($schedulesToRemove as $record => $schedules) {
			if (!empty($schedules)) {
				$invitationsDeleted = $this->deleteInvitationsForRecord($record, $schedules);
				if ($invitationsDeleted > 0) {
					$this->debug("Deleted $invitationsDeleted queued invites for record $record due to reeval logic: " . json_encode($schedules));
				}
			}
		}

        // Set as evaluated in redcap_crons_datediff (whether scheduled, sent, deleted, or none)
        $recordBatches = array_chunk(array_keys($this->datediff_cron_queued_records[$this->project_id]), Jobs::RECORD_EVAL_LIMIT_PER_QUERY, true);
        foreach ($recordBatches as $recordBatch) {
            $sql = "update redcap_crons_datediff set asi_status = null, asi_updated_at = '".date('Y-m-d H:i:s')."'
                    where project_id = {$this->project_id} and record in (".prep_implode($recordBatch).")";
            db_query($sql);
        }

		$this->num_scheduled_total = $numInvitationsScheduled;

		return $numInvitationsScheduled;
	}


	// ABM: Modified version of checkConditionsOfRecordToSchedule that takes the schedule and cached data
	// It also just applies logic instead of using the logic tester to evaluate one record at a time
	public function checkConditionsOfRecordToScheduleBulk($thisSchedule, $record, $record_data, $funcName, $argMap, $firstEventId=null)
	{
		// If conditional upon survey completion, check if completed survey
		$conditionsPassedSurveyComplete = ($thisSchedule['condition_andor'] == 'AND'); // Initial true value if using AND (false if using OR)
		if (is_numeric($thisSchedule['condition_surveycomplete_survey_id']) && is_numeric($thisSchedule['condition_surveycomplete_event_id']))
		{
            $Proj = new Project($this->project_id);
            $instanceNum = 1;
			// Is it a completed response?
			$conditionsPassedSurveyComplete = Survey::isResponseCompleted($thisSchedule['condition_surveycomplete_survey_id'], $record, $thisSchedule['condition_surveycomplete_event_id'], $instanceNum);
			// If not listed as a completed response, then also check Form Status (if entered as plain record data instead of as response), just in case
			if (!$conditionsPassedSurveyComplete) {
				$conditionsPassedSurveyComplete = self::isFormStatusCompleted($thisSchedule['condition_surveycomplete_survey_id'], $thisSchedule['condition_surveycomplete_event_id'], $record, $instanceNum);
			}
		}
		// If conditional upon custom logic
		$conditionsPassedLogic = ($thisSchedule['condition_andor'] == 'AND'); // Initial true value if using AND (false if using OR)
		if ($thisSchedule['condition_logic'] != ''
			// If using AND and $conditionsPassedSurveyComplete is false, then no need to waste time checking evaluateLogicSingleRecord().
			// If using OR and $conditionsPassedSurveyComplete is true, then no need to waste time checking evaluateLogicSingleRecord().
			&& (($thisSchedule['condition_andor'] == 'OR' && !$conditionsPassedSurveyComplete)
				|| ($thisSchedule['condition_andor'] == 'AND' && $conditionsPassedSurveyComplete)))
		{
			// Does the logic evaluate as true?
			// ABM: Instead of evaluateLogicSingleRecord we have the funcName and argMap so we can process immediately
			$conditionsPassedLogic = LogicTester::applyLogic($funcName, $argMap, $record_data, $firstEventId, false, $this->project_id);
		}
		// Check pass/fail values and return boolean if record is ready to have its invitation for this survey/event
		if ($thisSchedule['condition_andor'] == 'OR') {
			// OR
			return ($conditionsPassedSurveyComplete || $conditionsPassedLogic);
		} else {
			// AND (default)
			return ($conditionsPassedSurveyComplete && $conditionsPassedLogic);
		}
	}


	/**
	 * CONSTRUCTOR
	 */
	public function __construct($this_project_id=null, $datediff_today_check=false)
	{
		// Set project_id for this object
        $this->project_id = ($this_project_id == null && defined("PROJECT_ID")) ? PROJECT_ID : $this_project_id;
        // Set flag for datediff cron job
        $this->datediff_today_check = $datediff_today_check;
        $this->datediff_cron_queued_records = null; // Default: Reset if needed
	}


	// Determine the number of emails to send per batch (optimally 5-min worth) based upon values
	// of previously sent emails in redcap_surveys_emails_send_rate table.
	public static function determineEmailsPerBatch()
	{
		// Get average emails_per_minute from last 20 batches
		$sql = "select round(avg(emails_per_minute)*" . self::BATCH_LENGTH_MINUTES . ")
				from redcap_surveys_emails_send_rate order by esr_id desc limit 20";
		$q = db_query($sql);
		if ($q && db_num_rows($q) > 0) {
			// Return average send time for last 20 batches
			$emails_per_minute = db_result($q, 0);
			// If calculated value is less than minimum, then use minimum instead
			return ($emails_per_minute < self::MIN_EMAILS_PER_BATCH ? self::MIN_EMAILS_PER_BATCH : $emails_per_minute);
		} else {
			// If could not determine from table, then use hard-coded default
			return self::MAX_EMAILS_PER_BATCH;
		}
	}

	// Return array of survey_id/event_id's of any surveys that are dependent upon *this* survey_id/event_id
	// being completed in order to trigger Automated Invitations. (Check this to prevent infinite looping of triggers.)
	static private function getDependentSurveyEventIds($survey_id, $event_id)
	{
		$dependentSurveyEventIds = array();
		$sql = "select survey_id, event_id from redcap_surveys_scheduler where condition_surveycomplete_survey_id = $survey_id
				and condition_surveycomplete_event_id = $event_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$dependentSurveyEventIds[] = $row['survey_id']."-".$row['event_id'];
		}
		return $dependentSurveyEventIds;
	}


	// Return array of days of week choices for scheduling
	public static function daysofWeekOptions()
	{
		global $lang;
		return 	array(''=>$lang['survey_416'], "DAY"=>$lang['global_96'], "WEEKDAY"=>$lang['global_97'], "WEEKENDDAY"=>$lang['global_98'],
					"SUNDAY"=>$lang['global_99'], "MONDAY"=>$lang['global_100'], "TUESDAY"=>$lang['global_101'],
					"WEDNESDAY"=>$lang['global_102'], "THURSDAY"=>$lang['global_103'], "FRIDAY"=>$lang['global_104'],
					"SATURDAY"=>$lang['global_105']);
	}


	// Output HTML table for setting up the conditional survey invitation schedule for a given survey/event
	public function renderConditionalInviteSetupTable($survey_id, $event_id)
	{
		// Set variables needed
		global $Proj, $longitudinal, $lang, $user_firstname, $user_lastname, $user_email, $twilio_enabled;
		// Fill up $schedules array with schedules
		$this->setSchedules(true);
		// Add days of the week + work day + weekend day as drop-down list options
		$daysOfWeekDD = self::daysofWeekOptions();
		// Get list of survey_id-event_id's that are dependent upon this $survey_id and $event_id.
		// Prevent users from creating infinite loop with triggering via survey completion.
		$dependentSurveyEventIds = self::getDependentSurveyEventIds($survey_id, $event_id);

		// Create list of all surveys/event instances as array to use for looping below and also to feed a drop-down
		$surveyEvents = array();
		$surveyDD = array(''=>'--- '.$lang['survey_404'].' ---');
		// Loop through all events (even for classic)
		foreach ($Proj->eventsForms as $this_event_id=>$forms)
		{
			// Go through each form and see if it's a survey
			foreach ($forms as $form)
			{
				// Get survey_id
				$this_survey_id = isset($Proj->forms[$form]['survey_id']) ? $Proj->forms[$form]['survey_id'] : null;
				// Only display surveys, so ignore if does not have survey_id
				if (!is_numeric($this_survey_id)) continue;
				// Add form, event_id, and survey_id to drop-down array
				$title = $Proj->surveys[$this_survey_id]['title'];
				$event = $Proj->eventInfo[$this_event_id]['name_ext'];
				// Don't add this current survey-event option to drop-down (would create infinite loop)
				if (!($survey_id == $this_survey_id && $this_event_id == $event_id)) {
					// If title is blank, then use the form name instead
					if ($title == "") {
						$title = $Proj->forms[$form]['menu'];
					}
					$title = strip_tags($title);
					$form_display = strip_tags($Proj->forms[$form]["menu"]);
					$option_label = "\"$title\"";
					if ($title != $form_display) {
						$option_label .= " [$form_display]";
					}
					if ($longitudinal) {
						$option_label .= " - $event";
					}
					// Add to array
					$surveyDD["$this_survey_id-$this_event_id"] = $option_label;
				}
				// Add values to array
				$surveyEvents[] = array('event_id'=>$this_event_id, 'event_name'=>$event, 'form'=>$form,
										'survey_id'=>$this_survey_id, 'survey_title'=>$title);
			}
		}

		// Check if survey_id/event_id have a saved schedule
		$savedSchedule = $this->schedules[$survey_id][$event_id] ?? false;
		$isScheduleSaved = true;
		if ($savedSchedule === false) {
			$savedSchedule = getTableColumns('redcap_surveys_scheduler');
			if (isset($savedSchedule['ss_id'])) unset($savedSchedule['ss_id']);
			foreach ($savedSchedule as &$attr) $attr = null;
			$isScheduleSaved = false;
		}
		// Set row attributes
		$emailSubject = label_decode($savedSchedule['email_subject']);
		$emailContent = decode_filter_tags($savedSchedule['email_content']);
		$emailSender = label_decode($savedSchedule['email_sender']);
		$emailSenderDisplay = label_decode($savedSchedule['email_sender_display']);
		$conditionSurveyId = $savedSchedule['condition_surveycomplete_survey_id'];
		$conditionEventId = $savedSchedule['condition_surveycomplete_event_id'];
		$conditionSurveyCompSelected = (is_numeric($conditionSurveyId) && is_numeric($conditionEventId)) ? "$conditionSurveyId-$conditionEventId" : '';
        $conditionSurveyCompChecked = (is_numeric($conditionSurveyId) && is_numeric($conditionEventId)) ? 'checked' : '';
		$conditionAndOr = (isset($savedSchedule['condition_andor']) && $savedSchedule['condition_andor'] != '') ? label_decode($savedSchedule['condition_andor']) : 'AND';
		$conditionLogic = (isset($savedSchedule['condition_logic']) && $savedSchedule['condition_logic'] != '') ? label_decode($savedSchedule['condition_logic'], false) : '';
		$conditionLogicChecked = (isset($savedSchedule['condition_logic']) && $savedSchedule['condition_logic'] != '') ? 'checked' : '';
		$reevalBeforeSendChecked = (isset($savedSchedule['reeval_before_send']) && $savedSchedule['reeval_before_send'] == '1') ? 'checked' : '';
		$conditionSendTimeSelectedImmediately = (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'IMMEDIATELY') ? 'checked' : '';
		$conditionSendTimeSelectedTimeLag = (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'TIME_LAG') ? 'checked' : '';
		$conditionSendTimeSelectedNextOccur = (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'NEXT_OCCURRENCE') ? 'checked' : '';
		$conditionSendTimeSelectedExactTime = (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'EXACT_TIME') ? 'checked' : '';
		$conditionSendTimeLagDays = '';
		$conditionSendTimeLagHours = '';
		$conditionSendTimeLagMinutes = '';
		$conditionSendTimeLagField = '';
		$conditionSendTimeLagFieldAfter = 'after';
		$conditionSendNextDayType = '';
		$conditionSendNextTime = '';
		$conditionExactTimeValue = '';
        $conditionMaxRecurrence = $savedSchedule['max_recurrence'];
        $conditionNumRecurrence = ($savedSchedule['num_recurrence'] == '') ? '0' : $savedSchedule['num_recurrence'];
        $conditionUnitsRecurrence = ($savedSchedule['units_recurrence'] == '') ? 'DAYS' : $savedSchedule['units_recurrence'];
        $conditionRecurrenceSelectedOnce = ($conditionNumRecurrence == '0') ? 'checked' : '';
        $conditionRecurrenceSelectedMultiple = ($conditionNumRecurrence != '0') ? 'checked' : '';
		if (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'TIME_LAG') {
			$conditionSendTimeLagDays = (isset($savedSchedule['condition_send_time_lag_days']) && $savedSchedule['condition_send_time_lag_days'] != '') ? $savedSchedule['condition_send_time_lag_days'] : 0;
			$conditionSendTimeLagHours = (isset($savedSchedule['condition_send_time_lag_hours']) && $savedSchedule['condition_send_time_lag_hours'] != '') ? $savedSchedule['condition_send_time_lag_hours'] : 0;
			$conditionSendTimeLagMinutes = (isset($savedSchedule['condition_send_time_lag_minutes']) && $savedSchedule['condition_send_time_lag_minutes'] != '') ? $savedSchedule['condition_send_time_lag_minutes'] : 0;
			$conditionSendTimeLagField = $savedSchedule['condition_send_time_lag_field'];
			$conditionSendTimeLagFieldAfter = $savedSchedule['condition_send_time_lag_field_after'];
		} elseif (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'NEXT_OCCURRENCE') {
			$conditionSendNextDayType = (isset($savedSchedule['condition_send_next_day_type']) && $savedSchedule['condition_send_next_day_type'] != '') ? $savedSchedule['condition_send_next_day_type'] : '';
			$conditionSendNextTime = (isset($savedSchedule['condition_send_next_time']) && $savedSchedule['condition_send_next_time'] != '') ? substr($savedSchedule['condition_send_next_time'], 0, 5) : '';
		} elseif (isset($savedSchedule['condition_send_time_option']) && $savedSchedule['condition_send_time_option'] == 'EXACT_TIME' && $savedSchedule['condition_send_time_exact'] != '') {
			list ($this_date, $this_time) = explode(" ", $savedSchedule['condition_send_time_exact']);
			$conditionExactTimeValue = trim(DateTimeRC::format_ts_from_ymd($this_date) . " " . substr($this_time, 0, 5));
		}
		if (isset($savedSchedule['active'])) {
			$scheduleActiveSelected = ($savedSchedule['active'] == '1') ? 'checked' : '';
			$scheduleInactiveSelected = ($savedSchedule['active'] == '0') ? 'checked' : '';
			$scheduleActiveClass = ($savedSchedule['active'] == '1') ? 'darkgreen' : 'red';
		} else {
			$scheduleActiveSelected = $scheduleInactiveSelected = '';
			$scheduleActiveClass = 'gray';
		}
		$reminder_type = (isset($savedSchedule['reminder_type']) && $savedSchedule['reminder_type'] != '') ? $savedSchedule['reminder_type'] : '';
		$reminder_num = '1';
		$reminder_timelag_days = '';
		$reminder_timelag_hours = '';
		$reminder_timelag_minutes = '';
		$reminder_nextday_type = '';
		$reminder_nexttime = '';
		$reminder_exact_time = '';
		$emailComposeBoxDisplay = '';
		if ($reminder_type == 'TIME_LAG') {
			$reminder_timelag_days = (isset($savedSchedule['reminder_timelag_days']) && $savedSchedule['reminder_timelag_days'] != '') ? $savedSchedule['reminder_timelag_days'] : 0;
			$reminder_timelag_hours = (isset($savedSchedule['reminder_timelag_hours']) && $savedSchedule['reminder_timelag_hours'] != '') ? $savedSchedule['reminder_timelag_hours'] : 0;
			$reminder_timelag_minutes = (isset($savedSchedule['reminder_timelag_minutes']) && $savedSchedule['reminder_timelag_minutes'] != '') ? $savedSchedule['reminder_timelag_minutes'] : 0;
			$reminder_num = $savedSchedule['reminder_num'];
		} elseif ($reminder_type == 'NEXT_OCCURRENCE') {
			$reminder_nextday_type = (isset($savedSchedule['reminder_nextday_type']) && $savedSchedule['reminder_nextday_type'] != '') ? $savedSchedule['reminder_nextday_type'] : '';
			$reminder_nexttime = (isset($savedSchedule['reminder_nexttime']) && $savedSchedule['reminder_nexttime'] != '') ? substr($savedSchedule['reminder_nexttime'], 0, 5) : '';
			$reminder_num = $savedSchedule['reminder_num'];
		} elseif ($reminder_type == 'EXACT_TIME' && $savedSchedule['reminder_exact_time'] != '') {
			list ($this_date, $this_time) = explode(" ", $savedSchedule['reminder_exact_time']);
			$reminder_exact_time = trim(DateTimeRC::format_ts_from_ymd($this_date) . " " . substr($this_time, 0, 5));
		}
		$reminder_type_selected_timelag = ($reminder_type == 'TIME_LAG') ? 'checked' : '';
		$reminder_type_selected_next_occurrence = ($reminder_type == 'NEXT_OCCURRENCE') ? 'checked' : '';
		$reminder_type_selected_exacttime = ($reminder_type == 'EXACT_TIME') ? 'checked' : '';
		$reminder_checkbox_checked = ($reminder_type != '') ? 'checked' : '';
		$reminder_div_display = ($reminder_type == '') ? 'display:none;' : '';
		$reminders_text1_display = ($reminder_type == '') ? 'display:none;' : 'display:inline;';
		$reminder_num_disabled = ($reminder_type == 'EXACT_TIME') ? "disabled" : "";
		if ($savedSchedule['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $savedSchedule['delivery_type'] == 'SMS_INITIATE'
			 || $savedSchedule['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $savedSchedule['delivery_type'] == 'SMS_INVITE_WEB') {
			$emailSubjectDisplay = $emailFromDisplay = "display:none;";
		} elseif ($savedSchedule['delivery_type'] == 'VOICE_INITIATE') {
			$emailComposeBoxDisplay = $emailSubjectDisplay = $emailFromDisplay = "display:none;";
		} else {
			$emailComposeBoxDisplay = $emailSubjectDisplay = $emailFromDisplay = "";
		}

		// Get all datetime/datetime_seconds fields and put in array
		$datetime_fields_pre = Form::getFieldDropdownOptions(true, false, false, false, array('date', 'date_ymd', 'date_mdy', 'date_dmy', 'datetime',
			'datetime_ymd', 'datetime_mdy', 'datetime_dmy', 'datetime_seconds_ymd', 'datetime_seconds_dmy', 'datetime_seconds_mdy'), false, false);
		$datetime_fields = array();
		$datetime_fields[$lang['alerts_355']][''] = $lang['alerts_353'];
		$datetime_fields[$lang['alerts_355']]['[survey-date-completed:'.$Proj->surveys[$survey_id]['form_name'].']'] = $lang['alerts_354'];
		foreach ($datetime_fields_pre as $this_field=>$this_label) {
			$this_form_label = strip_tags($lang['alerts_243']." \"".$Proj->forms[$Proj->metadata[$this_field]['form_name']]['menu']."\"");
			$this_form = $Proj->metadata[$this_field]['form_name'];
			$this_label = preg_replace('/'.$this_field.'/', "[$this_field]", $this_label, 1);
			list ($this_label2, $this_label1) = explode(" ", $this_label, 2);
			if ($longitudinal) {
				foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
					if (in_array($this_form, $these_forms)) {
						if (!isset($datetime_fields[$this_form_label]["[$this_field]"])) {
							$datetime_fields[$this_form_label]["[$this_field]"] = "$this_label1 " . $lang['alerts_237'] . " - $this_label2";
						}
						$this_event_name = $Proj->getUniqueEventNames($this_event_id);
						$datetime_fields[$this_form_label]["[$this_event_name][$this_field]"] = "$this_label1 (".$Proj->eventInfo[$this_event_id]['name_ext'].") - $this_label2";
					}
				}
			} else {
				$datetime_fields[$this_form_label]["[$this_field]"] = "$this_label1 $this_label2";
			}
		}

		// For new schedules, auto-add the survey-link text to the compose box
		if (!$isScheduleSaved) {
            $emailContent = "<p>{$lang['survey_1224']}</p><p>{$lang['survey_134']}<br/>[survey-link]</p><p>{$lang['survey_135']}<br/>[survey-url]</p><p>{$lang['survey_137']}</p>";
		}

        global $mycap_enabled_global, $mycap_enabled;
        // Set survey title
        $survey_title = trim(strip_tags($Proj->surveys[$_GET['survey_id']]['title']));
        $this_survey_title = $survey_title;
        $this_form_label = trim(strip_tags($Proj->forms[$Proj->surveys[$_GET['survey_id']]['form_name']]['menu']));
        if ($this_survey_title == "") {
            $this_title = "\"$this_form_label\"";
        } elseif ($this_survey_title == $this_form_label) {
            $this_title = "\"$this_survey_title\"";
        } else {
            $this_title = "\"$this_survey_title\" [$this_form_label]";
        }
		// Create HTML content
		$html = // Instructions
				RCView::div(array('style'=>'padding-bottom:5px;line-height:1.2;'),
					$lang['survey_744'] . RCView::SP .
					// "Tell me more" link
					RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:underline;','onclick'=>"$(this).hide();$('#defineAutoInvitesMoreInfo').toggle('fade',function(){ fitDialog($('#popupSetUpCondInvites')); $('#popupSetUpCondInvites').dialog('option', 'position', { my: 'center', at: 'center', of: window }); });"), $lang['global_58']) .
					RCView::div(array('id'=>'defineAutoInvitesMoreInfo', 'style'=>'padding-top:10px;display:none;'),
						$lang['survey_745'] . RCView::br(). RCView::br() .
						$lang['survey_1582'] . RCView::br(). RCView::br() . $lang['survey_414'] .
						($longitudinal ? RCView::br(). RCView::br() . $lang['survey_407'] : '') . RCView::br()
					)
				) .
				// Hidden input for preventing dependent survey/events's from being selected in the drop-down (to prevent infinite looping of automated invites)
				RCView::input(array('id'=>'dependent-survey-event','type'=>'hidden','value'=>implode(',', $dependentSurveyEventIds))) .
				// Table with set-up options
				RCView::table(array('cellspacing'=>'0','border'=>'0','style'=>'table-layout:fixed;'),
					RCView::tr('',
						RCView::td(array('valign'=>'top','style'=>'width:730px;padding:6px 10px 0 0;'),
							// Check response limit (if enabled)
							(($Proj->surveys[$survey_id]['response_limit'] > 0 && Survey::reachedResponseLimit(PROJECT_ID, $survey_id, $event_id))
								? 	RCView::div(array('class'=>'red', 'style'=>'margin-bottom:10px;'),
										($longitudinal ? $lang['survey_1115'] : $lang['survey_1114'])
									)
								: 	""
							) .
							// ACTIVATED?
							RCView::fieldset(array('id'=>'condSurvPopupActiveBox','class'=>$scheduleActiveClass,'style'=>'padding:5px 8px 0;margin-bottom:15px;'),
								RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
									'<i class="fas fa-power-off"></i> ' . $lang['asi_048']
								) .
								RCView::div(array('style'=>'padding:7px 8px 8px 2px;font-size:14px;'),
									// Survey title
									RCView::div(array('style'=>'color:#A00000;'),
										RCView::b($lang['survey_310']) .
										RCView::span(array('style'=>'margin-left:8px;'),
											$this_title
										)
									) .
									// Event name (if longitudinal)
									RCView::div(array('style'=>'color:#000066;padding-top:3px;' . ($longitudinal ? '' : 'display:none;')),
										RCView::b($lang['bottom_23']) .
										RCView::span(array('style'=>'margin-left:8px;'),
											RCView::escape($Proj->eventInfo[$_GET['event_id']]['name_ext'])
										)
									)
								) .
								RCView::div(array('style'=>'padding:6px 4px 10px 2px;border-top:1px dashed #ccc;'),
									RCView::div(array('class'=>'fs11 mb-2'),
										$lang['asi_049'].
										RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'style'=>'margin-left:3px;font-size:11px;',
											'title'=>$lang['alerts_32'], 'onclick'=>"simpleDialog('".RCView::tt_js('asi_049')." ".RCView::tt_js('asi_050')."','".RCView::tt_js('asi_048')."');"), '?')
									) .
									RCView::div(array('class'=>'boldish'),
										RCView::radio(array('name'=>"ssactive-$survey_id-$event_id",'onclick'=>"$('#condSurvPopupActiveBox').removeClass('gray').removeClass('red').addClass('darkgreen');",'value'=>'1',$scheduleActiveSelected=>$scheduleActiveSelected, 'style'=>'position:relative;top:2px;')) .
										$lang['survey_432'] . RCView::SP . RCView::SP . RCView::SP .
										RCView::radio(array('name'=>"ssactive-$survey_id-$event_id",'onclick'=>"$('#condSurvPopupActiveBox').removeClass('gray').removeClass('darkgreen').addClass('red');",'value'=>'0',$scheduleInactiveSelected=>$scheduleInactiveSelected, 'style'=>'position:relative;top:2px;')) .
										$lang['survey_433']
									)
								)
							) .
                            (($mycap_enabled_global && $mycap_enabled) ?
                            RCView::div(array('style'=>'padding:0 5px;'),
                                RCView::div(array('style'=>'font-size:11px;color:#D00000;padding-bottom:8px;line-height:13px;'),
                                    '<i class="far fa-lightbulb"></i> ' . RCView::b($lang['survey_105']) . RCView::SP . RCView::tt('asi_062')
                                )
                            ) : '') .
							// If TWILIO is enabled, give option to send as SMS or VOICE
							(!($twilio_enabled && $Proj->twilio_enabled_surveys) ? '' :
								RCView::fieldset(array('style'=>'padding:5px 0 2px 8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom:10px;'),
									RCView::legend(array('style'=>'color:#333;'),
										'<i class="fas fa-share"></i> ' .
										RCView::b($lang['survey_741'] . " " . $lang['survey_687']). " " . $lang['survey_691']
									) .
									RCView::div(array('style'=>'padding:6px 2px 6px 2px;'),
										RCView::select(array('name'=>'delivery_type', 'class'=>'x-form-text x-form-field', 'style'=>'max-width: 90%;', 'onchange'=>"setInviteDeliveryMethod(this);$('#ssemail-$survey_id-$event_id').trigger('blur');"),
											Survey::getDeliveryMethods(true, true), $savedSchedule['delivery_type']) .
										RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'style'=>'margin-left:5px;font-size: 12px;',
											'title'=>$lang['form_renderer_02'], 'onclick'=>"deliveryPrefExplain();"), '?')
									) .
									RCView::div(array('id'=>'surveyLinkWarningDeliveryType', 'style'=>'font-size:11px;line-height:13px;padding:0 2px 6px 2px;color:#C00000;display:none;'),
										'<i class="far fa-lightbulb"></i> '. $lang['survey_1236']
									)
								)
							) .
							## COMPOSE EMAIL SUBJECT AND MESSAGE
							RCView::fieldset(array('id'=>'compose_email_form_fieldset', 'style'=>$emailComposeBoxDisplay.'padding-top:5px;padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;'),
								RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
									'<i class="fas fa-envelope"></i> ' .
									(($twilio_enabled && $Proj->twilio_enabled_surveys) ? $lang['survey_742'] : $lang['survey_741']) . " " . $lang['survey_692']
								) .
								RCView::div(array('style'=>'padding:10px 0 5px 2px;'),
									RCView::table(array('cellspacing'=>'0','border'=>'0','width'=>'100%'),
										// From
										RCView::tr(array('id'=>'compose_email_from_tr', 'style'=>$emailFromDisplay),
											RCView::td(array('style'=>'vertical-align:top;width:50px;padding-top:2px;'),
												$lang['global_37']
											) .
											RCView::td(array('style'=>'vertical-align:top;color:#555;'),
												'<div class="clearfix nowrap">
													<div class="float-start" style="width:160px;margin-right:3px;">
															<input type="text" id="email_sender_display" name="email_sender_display" class="x-form-text x-form-field" value="'.RCView::escape($emailSenderDisplay).'" style="'.($GLOBALS['use_email_display_name']?'':'display:none;').'width:100%;" placeholder="'.js_escape2($lang['survey_1270']).'">
													</div>
													<div class="float-start" style="width:65%;max-width:320px;">' .
												User::emailDropDownListAllUsers($emailSender, true, 'email_sender', 'email_sender') .
												'</div>
													</div>' .
												RCView::div(array('style'=>'padding:2px 0 0 2px;font-size:11px;color:#777;'),
													$lang['survey_417']
												)
											)
										) .
										// To
										RCView::tr('',
											RCView::td(array('style'=>'vertical-align:middle;width:50px;padding-top:10px;'),
												$lang['global_38']
											) .
											RCView::td(array('style'=>'vertical-align:middle;padding-top:10px;color:#666;font-weight:bold;'),
												$lang['survey_338']
											)
										) .
										// Subject
										RCView::tr(array('id'=>'compose_email_subject_tr', 'style'=>$emailSubjectDisplay),
											RCView::td(array('valign'=>'top', 'style'=>'padding:13px 0 0;width:50px;'),
												$lang['survey_103']
											) .
											RCView::td(array('valign'=>'top', 'style'=>'padding:10px 0 5px;'),
												'<input class="x-form-text x-form-field" style="width:380px;" type="text" id="sssubj-'."$survey_id-$event_id".'" onkeydown="if(event.keyCode == 13){return false;}" value="'.js_escape2(str_replace('"', '&quot;', label_decode($emailSubject))).'"/>' .
												(!($twilio_enabled && $Proj->twilio_enabled_surveys) ? "" : '<div class="show_for_part_pref show_for_sms show_for_voice" style="padding-top:1px;line-height:11px;color:#000066;font-size:11px;">'.$lang['survey_917'].'</div>')
											)
										) .
										// Message
										RCView::tr('',
											RCView::td(array('colspan'=>'2','style'=>'padding:0 0 10px;'),
												'
												<div class="text-end mb-1 me-5">
													<a href="javascript:;" class="fs11" onclick="textareaTestPreviewEmail(\'#ssemail-'."$survey_id-$event_id".'\',1,\'#sssubj-'."$survey_id-$event_id".'\',\'#email_sender option:selected\');">'.$lang['design_700'].'</a>
												</div>
												<textarea class="x-form-field notesbox mceEditor" onblur="checkComposeForSurveyLink(this);" id="ssemail-'."$survey_id-$event_id".'" style="height:300px;width:98%;">'.nl2br(label_decode($emailContent)).'</textarea>' .
												(!($twilio_enabled && $Proj->twilio_enabled_surveys) ? "" : '<div class="show_for_voice show_for_part_pref" style="line-height:11px;color:#000066;font-size:11px;">'.$lang['survey_918'].'</div>')
											)
										)
									)
								) .
								// Extra instructions
								RCView::div(array('style'=>'padding:0 5px;'),
									RCView::div(array('style'=>'font-size:11px;color:#D00000;padding-bottom:8px;line-height:13px;'),
										'<i class="far fa-lightbulb"></i> ' . RCView::b($lang['survey_105']) . RCView::SP . ($twilio_enabled && $Proj->twilio_enabled_surveys ? $lang['survey_1226'] : $lang['survey_1225'])
									) .
									// Piping link
									RCView::div(array('style'=>'padding-bottom:6px;'),
										RCView::img(array('src'=>'pipe_small.gif')) .
										RCView::a(array('href'=>'javascript:;','style'=>'font-size:11px;color:#3E72A8;text-decoration:underline;','onclick'=>'pipingExplanation();'), $lang['design_468']) .
                                        RCView::span(array('class'=>'fs11 text-secondary ms-4'), '<i class="fa-solid fa-circle-info"></i> '.$lang['design_1045'])
									)
								)
							)
						) .
						## SCHEDULER CONDITIONAL SETTINGS
						RCView::td(array('valign'=>'top','style'=>'padding:6px 0 0 10px;width:570px;'),
							RCView::fieldset(array('style'=>'padding-top:5px;padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom: 10px;'),
								RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
									'<i class="fas fa-filter"></i> ' .
									($twilio_enabled && $Proj->twilio_enabled_surveys ? $lang['survey_743'] : $lang['survey_742']) . " " . $lang['survey_341']
								) .
								RCView::div(array('style'=>'padding:10px 0 5px 2px;'),
									// Select a condition
									RCView::div(array('style'=>'font-weight:bold;margin-bottom:2px;font-size:13px;color:#800000;'),
										$lang['survey_418']
									) .
									// When survey is completed
									RCView::div(array('style'=>'padding:3px 0 1px;'),
										RCView::checkbox(array('id'=>"sscondoption-surveycomplete-$survey_id-$event_id",$conditionSurveyCompChecked=>$conditionSurveyCompChecked)) .
										$lang['survey_419'] .
										RCView::br() .
										RCView::div(['style'=>'margin-left:1.9em;margin-right:1em;'], 
											// Drop-down of surveys/events
											RCView::select(array('id'=>"sscondoption-surveycompleteids-$survey_id-$event_id",'class'=>'x-form-text x-form-field','style'=>'margin-top:3px;font-size:12px;width:100%;max-width:420px;',
												'onchange'=>"$('#sscondoption-surveycomplete-$survey_id-$event_id').prop('checked', (this.value.length > 0) ); if (this.value.length > 0) hasDependentSurveyEvent(this);"), $surveyDD, $conditionSurveyCompSelected, 200)
										)
									) .
									// AND/OR drop-down list for conditions
									RCView::div(array('style'=>'padding:9px 0 8px;'),
										RCView::select(array('id'=>"sscondoption-andor-$survey_id-$event_id",'style'=>'font-size:12px;'), array('AND'=>$lang['global_87'],'OR'=>$lang['global_46']), $conditionAndOr)
									) .
									// When logic becomes true
									RCView::div(array('style'=>'text-indent:-1.9em;margin-left:1.9em;'),
										RCView::checkbox(array('id'=>"sscondoption-logic-$survey_id-$event_id",$conditionLogicChecked=>$conditionLogicChecked)) .
										$lang['survey_420'] . RCView::br() .
										RCView::textarea(array('id'=>"sscondlogic-$survey_id-$event_id",'class'=>'x-form-field', 'hasrecordevent'=>'1', 'onfocus'=>'openLogicEditor($(this))', 'style'=>'line-height:14px;font-size:12px;width:100%;max-width:420px;height:50px;resize:auto;margin-top:3px;', 'onkeydown' => 'logicSuggestSearchTip(this, event);', 'onblur'=>"var val = this; setTimeout(function() { logicHideSearchTip(val); this.value=trim(val.value); if(val.value.length > 0) { $('#sscondoption-logic-$survey_id-$event_id').prop('checked',true); } if(!checkLogicErrors(val.value,1,true)){validate_auto_invite_logic($(val));} }, 0);"), $conditionLogic
										) . logicAdd("sscondlogic-$survey_id-$event_id") .

										RCView::div(array('style'=>'text-indent:-1.9em;margin-left:1.9em;font-size:11px;color:#777;padding-right:10px;'),
												RCView::div(array(),
													($longitudinal ? "(e.g., [enrollment_arm_1][age] > 30 and [enrollment_arm_1][sex] = \"1\")" : "(e.g., [age] > 30 and [sex] = \"1\")")
												)
											) .
										RCView::table(array('style'=>'margin-top: 0px; margin-left: 25px; margin-right: 25px; margin-bottom: 4px; border: 0; width: 330px;'),
                                            RCView::tr(array('style' => 'border: 0;'),
                                                RCView::td(array('style'=>'text-align:left;'),
                                                        RCView::span(array('id' => "sscondlogic-$survey_id-$event_id".'_Ok', 'style'=>'color: green; height: 20px; font-weight: bold;'), "&nbsp;")
                                                        ).
                                                        RCView::td(array('style'=>'text-align:right;'),
                                                            RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:underline;font-size:10px;','onclick'=>"helpPopup('5','category_33_question_1_tab_5')"), $lang['dataqueries_79'])
                                                        )
                                                ) .
											RCView::tr(array('style' => 'border: 0; padding-top: 4px;'),
												RCView::td(array('colspan' => '2', 'style' => 'text-align: left; border: 0;'),
													"<span class='logicTesterRecordDropdownLabel'>{$lang['design_705']}</span> ".
													Records::renderRecordListAutocompleteDropdown($Proj->project_id, false, 1000, "logicTesterRecordDropdown", "x-form-text x-form-field", "", "", null, $lang['global_291'],
                                                        'var circle=\''.APP_PATH_IMAGES.'progress_circle.gif\'; if (this.value !== \'\') $(\'#sscondlogic-'.$survey_id.'-'.$event_id.'_res\').html(\'<img src=\'+circle+\'>\'); else $(\'#sscondlogic-'.$survey_id.'-'.$event_id.'_res\').html(\'\'); logicCheck($(\'#sscondlogic-'.$survey_id.'-'.$event_id.'\'), \'branching\', '.($longitudinal ? 'true' : 'false').', \'\', this.value+'.'\'||'.$event_id.'\', \''.js_escape2($lang['design_706']).'\', \''.js_escape2($lang['design_707']).'\', \''.js_escape2($lang['design_713']).'\', [\''.js_escape2($lang['design_716']).'\', \''.js_escape2($lang['design_717']).'\', \''.js_escape2($lang['design_708']).'\'], \'sscondlogic-'.$survey_id.'-'.$event_id.'\');')
												)
											) .
											RCView::tr(array('style' => 'border: 0; padding-top: 0px;'),
												RCView::td(array('colspan' => '2', 'style' => 'text-align: left; border: 0;'),
													RCView::span(array('id' => 'sscondlogic-'.$survey_id.'-'.$event_id.'_res', 'style'=>'color: green; font-weight: bold;'), "")
												)
											)
										).
										RCView::div(array('style'=>'text-indent:-1.9em;margin-left:1.9em;margin-top:4px;'),
											RCView::checkbox(array('id'=>"sscondoption-reeval_before_send-$survey_id-$event_id",$reevalBeforeSendChecked=>$reevalBeforeSendChecked)) .
											$lang['survey_922'] .
											RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$lang['survey_189'], 'style'=>'','onclick'=>"simpleDialog('".js_escape($lang['survey_923'])."','".js_escape($lang['survey_922'])."');"), '?')
										) .
                                        RCView::div(array('class'=>'text-end mt-1 me-2'),
                                            RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration: underline;', 'class'=>'fs11 text-primaryrc', 'onclick'=>"simpleDialog('".js_escape($lang['survey_1259']."<br><br>".$lang['survey_1260'])."','".js_escape($lang['survey_1258'])."',null,650);"), '<i class="far fa-lightbulb me-1" style="text-indent:0;"></i>'.$lang['survey_1258'])
                                        )
									)
								)
							) .
							## WHEN TO SEND ONCE CONDITION IS MET
							RCView::fieldset(array('style'=>'padding-top:5px;padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom: 10px;'),
								RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
									'<i class="fas fa-stopwatch"></i> ' .
									($twilio_enabled && $Proj->twilio_enabled_surveys ? $lang['survey_780'] : $lang['survey_743']) . " " . $lang['survey_421']
								) .
								RCView::div(array('style'=>'padding:5px 0 10px 2px;'),
									## When to send once condition is met
									RCView::div(array('id'=>"sscondtimes-$survey_id-$event_id"),
										// Immediately
										RCView::div(array('style'=>'padding:4px 0 1px;'),
											RCView::radio(array('name'=>"sscondwhen-$survey_id-$event_id",'value'=>'IMMEDIATELY',$conditionSendTimeSelectedImmediately=>$conditionSendTimeSelectedImmediately)) .
											$lang['survey_422']
										) .
										// Next occurrence of (e.g., Work day at 11:00am)
										RCView::div(array('style'=>'padding:13px 0 7px;'),
											RCView::radio(array('name'=>"sscondwhen-$survey_id-$event_id",'value'=>'NEXT_OCCURRENCE',$conditionSendTimeSelectedNextOccur=>$conditionSendTimeSelectedNextOccur)) .
											$lang['survey_423'] . RCView::SP . RCView::SP .
											RCView::select(array('id'=>"sscond-nextdaytype-$survey_id-$event_id", 'class'=>'x-form-text x-form-field','style'=>'font-size:11px;'), $daysOfWeekDD, $conditionSendNextDayType) . RCView::SP .
											$lang['survey_424'] . RCView::SP . RCView::SP .
											RCView::input(array('id'=>"sscond-nexttime-$survey_id-$event_id",'type'=>'text', 'class'=>'x-form-text x-form-field time2', 'value'=>$conditionSendNextTime,
												'style'=>'text-align:center;font-size:11px;width:40px;padding:1px 4px;', 'onblur'=>"redcap_validate(this,'','','soft_typed','time',1)",
												'onfocus'=>"if( $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}")) .
											RCView::span(array('class'=>'df'), 'H:M')
										).
										// Time lag of X amount of days/hours/minutes
										RCView::div(array('style'=>'padding:8px 0 12px;'),
											RCView::radio(array('name'=>"sscondwhen-$survey_id-$event_id",'value'=>'TIME_LAG',$conditionSendTimeSelectedTimeLag=>$conditionSendTimeSelectedTimeLag)) .
											$lang['survey_1289'] .
											(count($datetime_fields) == 1 ? RCView::SP . $lang['survey_1293'] : "") .
											RCView::SP . RCView::SP .
											RCView::span(array('style'=>'font-size:11px;'),
												RCView::input(array('id'=>"sscond-timelagdays-$survey_id-$event_id",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:38px;padding:1px;', 'value'=>$conditionSendTimeLagDays, 'maxlength'=>'4', 'onblur'=>"redcap_validate(this,'0','9999','hard','int');")) .
												$lang['survey_426'] . RCView::SP . RCView::SP .
												RCView::input(array('id'=>"sscond-timelaghours-$survey_id-$event_id",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:25px;padding:1px;', 'value'=>$conditionSendTimeLagHours, 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');")) .
												$lang['survey_427'] . RCView::SP . RCView::SP .
												RCView::input(array('id'=>"sscond-timelagminutes-$survey_id-$event_id",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:25px;padding:1px;', 'value'=>$conditionSendTimeLagMinutes, 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');")) .
												$lang['survey_428']
											) .
											(count($datetime_fields) == 1 ? '' :
												'<div class="mt-1 nowrap" style="margin-left:18px;">'.
													RCView::select(array('id'=>"sscond-timelagfieldafter-$survey_id-$event_id", 'class'=>'x-form-text x-form-field fs11 ms-1', 'style'=>'max-width:400px;'), array('before'=>$lang['alerts_245'], 'after'=>$lang['alerts_238']), $conditionSendTimeLagFieldAfter, 200).
													RCView::select(array('id'=>"sscond-timelagfield-$survey_id-$event_id", 'class'=>'x-form-text x-form-field fs11 ms-1', 'style'=>'max-width:420px;'), $datetime_fields, $conditionSendTimeLagField, 200).
													'<a href="javascript:;" class="help2" data-toggle="popover" data-html="true" data-trigger="hover" data-title="'.js_escape2($lang['global_03']).'" data-content="'.js_escape2($lang['alerts_320']).'">?</a>
												</div>'
											)
										) .
										// Exact time
										RCView::div(array('style'=>'padding:1px 0;'),
											RCView::radio(array('name'=>"sscondwhen-$survey_id-$event_id",'value'=>'EXACT_TIME', $conditionSendTimeSelectedExactTime=>$conditionSendTimeSelectedExactTime)) .
											$lang['survey_429'] . RCView::SP . RCView::SP .
											RCView::input(array('id'=>"ssdt-$survey_id-$event_id", 'type'=>'text', 'class'=>'ssdt x-form-text x-form-field',
												'value'=>$conditionExactTimeValue, 'style'=>'width:102px;font-size:11px;padding-bottom:1px;padding:1px 4px;',
												'onkeydown'=>"if(event.keyCode==13){return false;}",
												'onfocus'=>"this.value=trim(this.value); if(this.value.length == 0 && $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}" ,
												'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
											RCView::span(array('class'=>'df'), DateTimeRC::get_user_format_label().' H:M')
										)
									)
								)
							) .

                            ## RECURRENCE
                            RCView::fieldset(array('style'=>'padding-top:5px;padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom: 10px;'.($Proj->isRepeatingFormOrEvent($event_id, $Proj->surveys[$survey_id]['form_name']) ? '' : 'display:none;')),
                                RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
                                    '<i class="fas fa-redo"></i> ' .
                                    ($twilio_enabled && $Proj->twilio_enabled_surveys ? $lang['survey_781'] : $lang['survey_780']) . " " .
                                    $lang['survey_1393'] . "<span style='font-weight:normal;margin-left:5px;'>{$lang['survey_1394']}</span>"
                                ) .
                                RCView::div(array('style'=>'padding:5px 0 10px 2px;'),
                                    // Just Once
                                    RCView::div(array('class'=>'mt-1 mb-2'),
                                        RCView::radio(array('name'=>"ssrepeat-$survey_id-$event_id",'value'=>'ONCE',$conditionRecurrenceSelectedOnce=>$conditionRecurrenceSelectedOnce)) .
                                        $lang['alerts_61']
                                    ) .
                                    // Multiple times on a recurring basis
                                    RCView::div(array('class'=>'mb-1'),
                                        RCView::radio(array('name'=>"ssrepeat-$survey_id-$event_id",'value'=>'MULTIPLE',$conditionRecurrenceSelectedMultiple=>$conditionRecurrenceSelectedMultiple)) .
                                        $lang['alerts_232']
                                    ) .
                                    // Send every X days/hours/minutes
                                    RCView::div(array('style'=>'margin:10px 0 0 18px;'),
                                        '<i class="fas fa-redo" style="margin-right:1px;"></i> '.$lang['survey_735'].
                                        '<input type="text" value="'.$conditionNumRecurrence.'" id="'."ssrepeat-num-$survey_id-$event_id".'" onblur="if (redcap_validate(this,\'0\',\'9999\',\'soft_typed\',\'number\',1) && !isNumeric($(this).val())) $(this).val(\'0\');" class="x-form-text x-form-field" style="text-align:center;width:38px;font-size:11px;padding:0px 4px;margin:0 4px 0 6px;">' .
                                        RCView::select(array('id'=>"ssrepeat-units-$survey_id-$event_id",'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;'),
                                            array('MINUTES'=>$lang['survey_428'], 'HOURS'=>$lang['survey_427'], 'DAYS'=>$lang['survey_426']), $conditionUnitsRecurrence) .
                                        $lang['alerts_152'].$lang['period'] .
                                        '<div class="fs12" style="color:#999;margin-left:18px;margin-top:1px;"><i>'.$lang['survey_1513'].'</i></div>'
                                    ) .
                                    // Send up to X times
                                    RCView::div(array('style'=>'margin:10px 0 0 18px;'),
                                        '<i class="far fa-calendar-times" style="margin-left:1px;margin-right:2px;"></i> ' . $lang['survey_737'] .
                                        RCView::input(array('id'=>"ssrepeat-max-$survey_id-$event_id", 'type'=>'text', 'class'=>'x-form-text x-form-field', 'maxlength'=>'4',
                                            'value'=>$conditionMaxRecurrence, 'style'=>'text-align:center;width:30px;font-size:11px;padding:0px 4px;margin:0 3px 0 6px;',
                                            'onkeydown'=>"if(event.keyCode==13){return false;}",
                                            'onblur'=>"redcap_validate(this,'0','9999','hard','int');")) .
                                        $lang['alerts_233'] .
                                        '<div class="fs12" style="color:#999;margin-left:18px;margin-top:1px;"><i>'.$lang['survey_1396'].'</i></div>'
                                    ) .
                                    "<div style='margin-top:10px;margin-left:5px;font-size:11px;color:#777;'>
                                        <i class='fas fa-info-circle'></i> {$lang['design_747']} <a href='javascript:;' onclick='repeatingSurveyExplainPopup();' style='text-decoration:underline;font-size:11px;'>{$lang['design_1026']}</a>
                                    </div>"
                                )
                            ) .

							## REMINDERS
							RCView::fieldset(array('style'=>'padding-top:5px;padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom: 10px;'),
								RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
									'<i class="fas fa-bell"></i> ' .
									$lang['survey_746'] . " " . $lang['survey_733']
								) .
								RCView::div(array('style'=>'padding:5px 0 10px 2px;'),
									// Instructions
									RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;padding:3px 15px 3px 0;color:#444;'),
										RCView::checkbox(array('id'=>"enable_reminders_chk", 'style'=>'margin-right:3px;', $reminder_checkbox_checked=>$reminder_checkbox_checked)) .
										$lang['survey_734'] .
										RCView::span(array('id'=>'reminders_text1', 'style'=>$reminders_text1_display), $lang['survey_749'])
									) .
									## When to send once condition is met
									RCView::div(array('id'=>"reminders_choices_div", 'style'=>'margin-left:20px;'.$reminder_div_display),
										// Next occurrence of (e.g., Work day at 11:00am)
										RCView::div(array('style'=>'padding:4px 0 1px;'),
											RCView::radio(array('name'=>"reminder_type",'value'=>'NEXT_OCCURRENCE', $reminder_type_selected_next_occurrence=>$reminder_type_selected_next_occurrence)) .
											$lang['survey_735'] . RCView::SP . RCView::SP .
											RCView::select(array('name'=>"reminder_nextday_type", 'class'=>'x-form-text x-form-field','style'=>'font-size:11px;', 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=NEXT_OCCURRENCE]').prop('checked',true).trigger('change'); }"), SurveyScheduler::daysofWeekOptions(), $reminder_nextday_type) . RCView::SP .
											$lang['survey_424'] . RCView::SP . RCView::SP .
											RCView::input(array('name'=>"reminder_nexttime",'type'=>'text', 'class'=>'x-form-text x-form-field time2',
												'style'=>'text-align:center;font-size:11px;width:40px;', 'value'=>$reminder_nexttime,
												'onfocus'=>"if( $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}",  'onblur'=>"redcap_validate(this,'','','soft_typed','time',1)",
												'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=NEXT_OCCURRENCE]').prop('checked',true).trigger('change'); }")) .
											RCView::span(array('class'=>'df', 'style'=>'padding-left: 5px;'), 'H:M')

										).
										// Time lag of X amount of days/hours/minutes
										RCView::div(array('style'=>'padding:1px 0;'),
											RCView::radio(array('name'=>"reminder_type",'value'=>'TIME_LAG', $reminder_type_selected_timelag=>$reminder_type_selected_timelag)) .
											$lang['survey_735'] . RCView::SP . RCView::SP .
											RCView::span(array('style'=>'font-size:11px;'),
												RCView::input(array('name'=>"reminder_timelag_days",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:31px;', 'value'=>$reminder_timelag_days, 'maxlength'=>'3', 'onblur'=>"redcap_validate(this,'0','999','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) .
												$lang['survey_426'] . RCView::SP . RCView::SP .
												RCView::input(array('name'=>"reminder_timelag_hours",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:25px;', 'value'=>$reminder_timelag_hours, 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) .
												$lang['survey_427'] . RCView::SP . RCView::SP .
												RCView::input(array('name'=>"reminder_timelag_minutes",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:25px;', 'value'=>$reminder_timelag_minutes, 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) .
												$lang['survey_428']
											)
										) .
										// Exact time
										RCView::div(array('style'=>'padding:1px 0;'),
											RCView::radio(array('name'=>"reminder_type",'value'=>'EXACT_TIME', $reminder_type_selected_exacttime=>$reminder_type_selected_exacttime)) .
											$lang['survey_429'] . RCView::SP . RCView::SP .
											RCView::input(array('name'=>"reminder_exact_time", 'type'=>'text', 'class'=>'reminderdt x-form-text x-form-field',
												'value'=>$reminder_exact_time, 'style'=>'width:102px;font-size:11px;padding-bottom:1px;',
												'onkeydown'=>"if(event.keyCode==13){return false;}",
												'onfocus'=>"this.value=trim(this.value); if(this.value.length == 0 && $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}" ,
												'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);",
												'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=EXACT_TIME]').prop('checked',true).trigger('change'); }")) .
											RCView::span(array('class'=>'df', 'style'=>'padding-left: 5px;'), DateTimeRC::get_user_format_label().' H:M')
										) .
										// Recurrence
										RCView::div(array('style'=>'margin:4px 0 5px -15px;color:#999;'),
											"&ndash; " . $lang['global_87'] . " &ndash;"
										) .
										RCView::div(array('style'=>''),
											$lang['survey_739'] . RCView::SP . RCView::SP .
											RCView::select(array('name'=>"reminder_num", $reminder_num_disabled=>$reminder_num_disabled, 'style'=>'font-size:11px;'), array('1'=>$lang['survey_736'], '2'=>"{$lang['survey_737']} 2 {$lang['survey_738']}",
												'3'=>"{$lang['survey_737']} 3 {$lang['survey_738']}", '4'=>"{$lang['survey_737']} 4 {$lang['survey_738']}",
												'5'=>"{$lang['survey_737']} 5 {$lang['survey_738']}", ), $reminder_num)
										)
									)
								)
							)
						)
					)
				);

		// Return the HTML
		return $html;
	}


	// Fill up array with the survey schedules for this project
	public function setSchedules($returnInactiveSchedules=false)
	{
		// Set $schedules as array
		if ($this->schedules == null)
		{
		    $Proj = new Project($this->project_id);
			// Set these as arrays
			$this->schedules = array();
			$this->schedulePkLink = array();
			// Query to get schedules for project and put in array
			$sql = "select r.*, s.form_name from redcap_surveys_scheduler r, redcap_surveys s
					where s.survey_id = r.survey_id and s.project_id = " . $this->project_id . "
					and r.event_id in (" . prep_implode(array_keys($Proj->eventInfo)) . ")";
			if (!$returnInactiveSchedules) $sql .= " and r.active = 1";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// Make sure the survey hasn't been deleted (and thus orphaned)
				if (!isset($Proj->forms[$row['form_name']])) continue;
				// Use survey_id and event_id for array keys
				$survey_id = $row['survey_id'];
				$event_id = $row['event_id'];
				$ss_id = $row['ss_id'];
				$instance = $row['instance'];
				// Remove unnecessary items
				unset($row['survey_id'], $row['event_id'], $row['ss_id'], $row['form_name'], $row['instance']);
				// Add to arrays
				$this->schedules[$survey_id][$event_id] = $row;
				$this->schedulePkLink[$ss_id][$survey_id] = $event_id;
			}
		}
	}


	// Return array of drop-down options of ALL surveys and, if longitudinal, the events for which they're designated
	public function getInvitationLogSurveyList($includeAllEventsOptionsForLongitudinal=true)
	{
		global $lang, $Proj, $longitudinal;
		$surveyEventOptions = array();
		// If longitudinal, then first display list of all surveys for ALL events (set 0 for event in drop-down value)
		if ($includeAllEventsOptionsForLongitudinal && $longitudinal) {
			foreach ($Proj->surveys as $this_survey_id=>$survey_attr) {
				// Add this survey/event as drop-down option
				$surveyEventOptions["$this_survey_id-0"] = "\"{$survey_attr['title']}\" ".$lang['survey_434'];
			}
		}
		// Loop through each event and output each where this form is designated
		foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
			// Loop through forms
			foreach ($these_forms as $form_name) {
				// Ignore if not a survey
				if (!isset($Proj->forms[$form_name]['survey_id'])) continue;
				// Get survey_id
				$this_survey_id = $Proj->forms[$form_name]['survey_id'];
				// If longitudinal, add event name
				$event_name = ($longitudinal) ? " - ".$Proj->eventInfo[$this_event_id]['name_ext'] : "";
				// If survey title is blank (because using a logo instead), then insert the instrument name
				$survey_title = ($Proj->surveys[$this_survey_id]['title'] == "") ? $Proj->forms[$form_name]['menu'] : $Proj->surveys[$this_survey_id]['title'];
				// Truncate survey title if too long
				if (mb_strlen($survey_title.$event_name) > 70) {
					$survey_title = mb_substr($survey_title, 0, 67-mb_strlen($event_name)) . "...";
				}
				// Add this survey/event as drop-down option
				$surveyEventOptions["$this_survey_id-$this_event_id"] = "\"$survey_title\"$event_name";
			}
		}
		// Return the array of surveys
		return $surveyEventOptions;
	}

	// Obtain the survey invitation log as an array - (past, present, and future) with filters and paging
	public function getSurveyInvitationLog($record=null, $scheduledInNextXdays=null, $returnCountOnly=false)
	{
		// Initialize vars
		global  $Proj, $longitudinal, $table_pk, $lang, $user_rights,
				$enable_participant_identifiers, $survey_email_participant_field, $survey_phone_participant_field, $twilio_enabled;

		// Set error msg default
		$errorMsg = '';

		// Set NOW in user defined date format but with military time
		$now_user_date_military_time = DateTimeRC::format_ts_from_ymd(TODAY).date(' H:i');

        // Determine which active ASI as the longest recurrence cron interval to determine the end time
        $surveyScheduler = new SurveyScheduler($this->project_id);
        $surveyScheduler->setSchedules();
        $asi_settings = $surveyScheduler->schedules;
        $maxAsiCronInterval = 0;
        foreach ($asi_settings as $row1) {
            foreach ($row1 as $row) {
                if (!$row['active']) continue;
                $intervalMinutes = $row['num_recurrence'] * ($row['units_recurrence'] == 'DAYS' ? 1440 : ($row['units_recurrence'] == 'HOURS' ? 60 : 1));
                if ($intervalMinutes > $maxAsiCronInterval) $maxAsiCronInterval = $intervalMinutes;
            }
        }
        // Show end time as 5x the longest cron interval
        $maxAsiCronInterval = 5*$maxAsiCronInterval;

		## DEFINE FILTERING VALUES
		// Set defaults
		if (isset($_GET['pagenum']) && (is_numeric($_GET['pagenum']) || $_GET['pagenum'] == 'last')) {
			// do nothing
		} elseif (!isset($_GET['pagenum'])) {
			$_GET['pagenum'] = 1;
		} else {
			$_GET['pagenum'] = 'ALL';
		}
		if (!isset($_GET['filterInviteType']) || (isset($_GET['filterInviteType']) && !in_array($_GET['filterInviteType'], array('1','0','-1','-2')))) $_GET['filterInviteType'] = '';
		if (!isset($_GET['filterResponseType'])) $_GET['filterResponseType'] = '';
		if (!isset($_GET['filterSurveyEvent'])) $_GET['filterSurveyEvent'] = '0-0';
		$_GET['filterRecord'] = isset($_GET['filterRecord']) ? urldecode(rawurldecode($_GET['filterRecord'])) : '';
		// Run the value through the regex pattern
		if (!isset($_GET['filterBeginTime'])) {
			// Default beginTime = right now
			$_GET['filterBeginTime'] = $now_user_date_military_time;
		}
		if (!isset($_GET['filterEndTime'])) {
			// Default endTime
			$_GET['filterEndTime'] = '';
		}
        if ($maxAsiCronInterval > 0 && $_GET['filterEndTime'] == '') {
            // Default endTime
            $_GET['filterEndTime'] = DateTimeRC::format_ts_from_ymd(date('Y-m-d H:i:s', strtotime(NOW . " + $maxAsiCronInterval minutes")), true, false);
        }
		if (!isset($_GET['filterReminders'])) {
			$_GET['filterReminders'] = '1';
		}
		// Set survey_id and event_id for filtering
		list ($survey_id, $event_id) = explode('-', $_GET['filterSurveyEvent'], 2);
		if (!is_numeric($survey_id) || $survey_id == '0') $survey_id = null;
		if (!is_numeric($event_id)  || $event_id  == '0') $event_id = null;
		// If project is not longitudinal, then constrict to only single event
		if (!$longitudinal) $event_id = $Proj->firstEventId;

        ## PERFORM MORE FILTERING
        // Now filter $invitationLog by filters defined
        if ($_GET['filterBeginTime'] != '') {
            $filterBeginTimeYmd = DateTimeRC::format_ts_to_ymd($_GET['filterBeginTime']);
        }
        if ($_GET['filterEndTime'] != '') {
            $filterEndTimeYmd = DateTimeRC::format_ts_to_ymd($_GET['filterEndTime']);
        }
        // Make sure begin time occurs *before* end time. If not, display error message to user.
//        if (isset($filterBeginTimeYmd) && isset($filterEndTimeYmd) && $filterBeginTimeYmd > $filterEndTimeYmd) {
//            $errorMsg = RCView::div(array('class'=>'yellow','style'=>'margin-bottom:10px;'),
//                RCView::b($lang['global_01'].$lang['colon']).' '.$lang['survey_402']
//            );
//        }

		// If user is in a DAG, only allow them to see participants in their DAG
		$dag_records = array();
		if ($user_rights['group_id'] != '')
		{
			// Validate DAG that user is in
			$dags = $Proj->getGroups();
			if (isset($dags[$user_rights['group_id']])) {
				$dag_records = Records::getData('array', ($record===null ? array() : $record), $table_pk, array(), $user_rights['group_id']);
			}
		}

		// Get list of participant_ids/records (if record exists) - will use later to insert record name into log table
		$participantRecordsComplete = $participantRecords = array();
		$sql = "select r.participant_id, r.record, if (r.first_submit_time is null, 0, if (r.completion_time is null, 1, 2)) as completed
				from redcap_surveys s, redcap_surveys_emails e, redcap_surveys_emails_recipients er,
				redcap_surveys_response r where s.project_id = ".$this->project_id." and s.survey_id = e.survey_id and e.email_id = er.email_id
				and r.participant_id = er.participant_id";
		if ($record !== null) $sql .= " and r.record = '".db_escape($record)."'";
		if (is_numeric($survey_id)) $sql .= " and s.survey_id = $survey_id";
		$q = db_query($sql);
		// Loop through all rows
		while ($row = db_fetch_assoc($q))
		{
			$participantRecordsComplete[$row['participant_id']] = $row['completed'];
			$participantRecords[$row['participant_id']] = label_decode($row['record']);
		}

		// send_time > $scheduledInNextXdays
		$scheduledInNextXdaysTS = "";
		if (is_numeric($scheduledInNextXdays)) {
			$scheduledInNextXdaysTS = "and q.status = 'QUEUED' and q.scheduled_time_to_send <= '"
									. date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"), date("m"), date("d")+$scheduledInNextXdays, date("Y"))) . "'";
		}

		// Get invitation log info for table
		$invitationLog = $record_names = array();
		$sql_deleted = ($_GET['filterInviteType'] == '-2') ? "and q.status = 'DELETED'" : "and (q.status != 'DELETED' or q.status is null)";
		$sql = "select er.delivery_type, q.ssq_id, if (q.status is null, e.email_sent,
				if (q.status = 'SENT', q.time_sent, q.scheduled_time_to_send)) as send_time, q.reminder_num,
				if ((q.reason_not_sent is null and q.status = 'SENT') or (q.status is null and e.email_sent is not null), 1,
					if (q.reason_not_sent is null, 0, -1)) as was_sent,
				p.participant_id, p.survey_id, p.event_id, p.hash, er.email_recip_id, p.participant_email,
				p.participant_phone, p.participant_identifier, er.static_email,
				q.status as scheduled_status, q.reason_not_sent, q.instance, l.error_message
				from redcap_surveys s, redcap_surveys_emails e, redcap_surveys_participants p, redcap_surveys_emails_recipients er
				left join redcap_surveys_scheduler_queue q on q.email_recip_id = er.email_recip_id
				left join redcap_twilio_error_log l on l.ssq_id = q.ssq_id
				where s.project_id = ".$this->project_id." and s.survey_id = e.survey_id and e.email_id = er.email_id
				and p.participant_id = er.participant_id $scheduledInNextXdaysTS
				$sql_deleted
				and (q.time_sent is not null or q.scheduled_time_to_send is not null or e.email_sent is not null)";
		if (is_numeric($survey_id)) $sql .= " and s.survey_id = $survey_id";
		if (is_numeric($event_id))  $sql .= " and p.event_id = $event_id";
		if ($record !== null) $sql .= " and q.record = '".db_escape($record)."'";
		$sql .= " order by if (q.status is null, e.email_sent, if (q.status = 'SENT', q.time_sent, q.scheduled_time_to_send)),
				p.participant_email, abs(p.participant_identifier), p.participant_identifier";
		$q = db_query($sql);
		// Loop through all rows and store values in array
		$rownum = 0;
		while ($row = db_fetch_assoc($q))
		{
			if ($row['instance'] == "") $row['instance'] = '1';
			// Merge recipient emails
			if ($row['participant_email'] == "" && $row['static_email'] != "") {
				$row['participant_email'] = $row['static_email'];
			}
			// Add record name and completed status (if record exists)
			if (isset($participantRecords[$row['participant_id']])) {
				$row['record'] = $participantRecords[$row['participant_id']];
				$row['completed'] = $participantRecordsComplete[$row['participant_id']];
			} else {
				$row['record'] = "";
				$row['completed'] = "0";
			}
			if ($record !== null && $row['record'] != $record) continue;
			if (!isset($Proj->surveys[$row['survey_id']])) continue;
			// If longitudinal, ensure that this survey is still designated for this event
			if ($Proj->longitudinal && !isset($Proj->eventsForms[$row['event_id']])) continue;
			if ($Proj->longitudinal && $row['survey_id'] != '' && $row['event_id'] != '' && isset($Proj->surveys[$row['survey_id']])
				&& !in_array($Proj->surveys[$row['survey_id']]['form_name'], $Proj->eventsForms[$row['event_id']])
			) {
				continue;
			}
			// If has a record name, then add to array to obtain email/identifier/phone in next section
			if ($row['record'] != "") {
				$record_names[$rownum] = $row['record'];
			}
			if ($row['reminder_num'] == '') $row['reminder_num'] = 0;
            $row['ssr_id'] = null;
			// Unset some values we don't need
			unset($row['static_email']);
			if (!($twilio_enabled && $Proj->twilio_enabled_surveys)) {
				unset($row['participant_phone']);
			}
			// Add this invitation to array
			$invitationLog[] = $row;
			// Increment counter
			$rownum++;
		}
		// Remove unneeded arrays
		unset($participantRecords, $participantRecordsComplete);


        // RECURRING ASI'S: Now add all projected future invitations to the invitation log
        // (SKIP THIS SECTION if we're looking at past timestamps only - this is only for future projections)
        $recurrences = array();
        $recordInstances = array();
        if (
            // Only display future invitations
            (!isset($filterEndTimeYmd) || $filterEndTimeYmd == '' || $filterEndTimeYmd > substr(NOW, 0, 16))
            // AND only display "scheduled" invitations
            && ($_GET['filterInviteType'] == '0' || $_GET['filterInviteType'] == '')
            // AND only display unresponded response statuses
            && ($_GET['filterResponseType'] == '' || $_GET['filterResponseType'] == '0')
        ) {
            $sql = "select r.ssr_id, a.delivery_type, r.record, a.survey_id, a.event_id, a.reminder_num, r.first_send_time,
                    r.times_sent, a.num_recurrence, a.units_recurrence, a.max_recurrence, s.survey_expiration, a.ss_id, 
                    a.condition_logic, a.reeval_before_send
                    from redcap_surveys_scheduler_recurrence r, redcap_surveys_scheduler a, redcap_surveys s, redcap_events_repeat e
                    where r.ss_id = a.ss_id and a.survey_id = s.survey_id and s.project_id = " . PROJECT_ID . "
                    and a.active = 1 and (a.num_recurrence > 0 and (a.max_recurrence is null or r.times_sent < a.max_recurrence))
                    and e.event_id = a.event_id and (e.form_name is null or e.form_name = s.form_name)";
            if ($record !== null) $sql .= " and r.record = '" . db_escape($record) . "'";
            if ($_GET['filterRecord'] != '') $sql .= " and r.record = '" . db_escape($_GET['filterRecord']) . "'";
            if (is_numeric($survey_id)) $sql .= " and a.survey_id = $survey_id";
            if (is_numeric($event_id)) $sql .= " and a.event_id = $event_id";
            $sql .= " order by r.first_send_time";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                if (!isset($Proj->surveys[$row['survey_id']])) continue;
                $recurrences[] = $row;
            }

            $scheduledInNextXdaysTime = null;
            if (is_numeric($scheduledInNextXdays)) {
                $scheduledInNextXdaysTime = date('Y-m-d H:i:s', strtotime(NOW . " + 7 days"));
            }

            // If any scheduled repeating ASIs have been negated by stop logic in the conditional logic, then do not display them in the Invitation Log
            foreach ($recurrences as $key=>$row) {
                // If this ASI does not have conditional logic with reeval_before_send, then skip it
                if ($row['reeval_before_send'] == '0' || $row['condition_logic'] == '') continue;
                // Evaluate the logic
                $form = $Proj->surveys[$row['survey_id']]['form_name'];
                $passedLogicTest = REDCap::evaluateLogic($row['condition_logic'], PROJECT_ID, $row['record'], $row['event_id'], 1, ($Proj->isRepeatingForm($row['event_id'], $form) ? $form : ""), $form);
                // Remove this recurrence schedule if logic now fails
                if (!$passedLogicTest) {
                   unset($recurrences[$key]);
                }
            }

            // Loop through all rows and store values in array
            $maxLoops = 100; // How many instances of EACH recurrence should we show (max)?
            $recurringInstruments = [];
            $recurringEventIds = [];
            foreach ($recurrences as $key=>$row)
            {
                $intervalMinutes = ($row['units_recurrence'] == 'DAYS' ? 1440 : ($row['units_recurrence'] == 'HOURS' ? 60 : 1));
                $form = $Proj->surveys[$row['survey_id']]['form_name'];
                $recurringInstruments[$form] = true;
                $recurringEventIds[$row['event_id']] = true;
                $i = 0;
                for ($recurrenceNum = 0; $recurrenceNum < $maxLoops; $recurrenceNum++)
                {
                    $totalMinutes = round(($row['times_sent'] + $recurrenceNum) * $row['num_recurrence'] * $intervalMinutes);
                    $row['send_time'] = date('Y-m-d H:i:s', strtotime($row['first_send_time'] . " + $totalMinutes minutes"));
                    // If this projected time is in the past (how?) or if user set an end time filter, then skip to next
                    if ($row['send_time'] < NOW) continue;
                    if ($scheduledInNextXdaysTime !== null && $row['send_time'] > $scheduledInNextXdaysTime) continue;
                    if (isset($filterEndTimeYmd) && substr($row['send_time'], 0, 16) > $filterEndTimeYmd) {
                        break;
                    }
                    // If a recurrence maximum is set, then if we've already hit the max, don't show any more of this recurrence.
                    if ($row['max_recurrence'] != '' && ($row['times_sent'] + $i) >= $row['max_recurrence']) {
                        break;
                    }
                    // If survey will expire at a certain time, then don't project any future invitations past that time
                    if ($row['survey_expiration'] != '' && $row['send_time'] > $row['survey_expiration']) break;
                    // Remove extras
                    $ss_id = $row['ss_id'];
                    $row2 = $row;
                    unset($row2['first_send_time'], $row2['times_sent'], $row2['num_recurrence'], $row2['units_recurrence'], $row2['ss_id']);
                    // Add others
                    $row2['ssq_id'] = '';
                    $row2['reminder_num'] = '0';
                    $row2['was_sent'] = '0';
                    $row2['participant_id'] = '';
                    $row2['email_recip_id'] = '';
                    $row2['participant_email'] = '';
                    $row2['participant_identifier'] = '';
                    $row2['scheduled_status'] = 'QUEUED';
                    $row2['reason_not_sent'] = '';
                    $row2['error_message'] = '';
                    $row2['completed'] = '0';
                    $row2['repeating'] = '1';
                    // Find the instance number by determining what the last one was
                    if (!isset($recordInstances[$row2['record']][$ss_id])) {
                        $sql = "select ifnull(max(q.instance),0)+1 as nextinstance
                                from redcap_surveys_scheduler_recurrence r, redcap_surveys_scheduler_queue q
                                where r.ss_id = $ss_id and r.ss_id = q.ss_id and q.record = '".db_escape($row2['record'])."'";
                        $q2 = db_query($sql);
                        $recordInstances[$row2['record']][$ss_id] = ($q2 && db_num_rows($q2) > 0) ? db_result($q2, 0) : 1;
                    } else {
                        $recordInstances[$row2['record']][$ss_id]++;
                    }
                    $row2['instance'] = $recordInstances[$row2['record']][$ss_id];
                    // Determine the survey hash for this survey instance
                    $row2['hash'] = REDCap::getSurveyLink($row2['record'], $form, $row2['event_id'], $row2['instance'], $Proj->project_id, false, true);
                    // Add record to array
                    $record_names[$rownum] = $row['record'];
                    // Add to array
                    $invitationLog[] = $row2;
                    $i++;
                    // Increment counter
                    $rownum++;
                }
                unset($recurrences[$key]);
            }
        }

        // Check record data for recurring ASIs in case some future repeating survey instances have already been completed (so we can show them as completed in the Invitation Log)
        $completedRecurringSurveyData = [];
        if (!empty($record_names) && !empty($recurringInstruments) && !empty($recurringEventIds))
        {
            // Gather form status fields
            $recurringInstrumentStatus = [$Proj->table_pk];
            foreach (array_keys($recurringInstruments) as $this_form) {
                if ($Proj->isRepeatingFormAnyEvent($this_form)) {
                    $recurringInstrumentStatus[] = $this_form."_complete";
                }
            }
            // Get relevant data for repeating invitations
            $completedRecurringSurveyData = Records::getData(['records'=>$record_names, 'fields'=>$recurringInstrumentStatus, 'events'=>array_keys($recurringEventIds)]);
            foreach ($record_names as $logkey=>$this_record)
            {
                // Only deal with repeating invitations
                if (!isset($invitationLog[$logkey]['repeating'])) continue;
                $this_instrument = $Proj->surveys[$invitationLog[$logkey]['survey_id']]['form_name'];
                $this_event_id = $invitationLog[$logkey]['event_id'];
                $this_instance = $invitationLog[$logkey]['instance'];
                $repeat_instrument = $Proj->isRepeatingForm($this_event_id, $this_instrument) ? $this_instrument : "";
                if (isset($completedRecurringSurveyData[$this_record]['repeat_instances'][$this_event_id][$repeat_instrument][$this_instance][$this_instrument."_complete"])) {
                    // Remove this because it will never get sent out.
                    unset($invitationLog[$logkey], $record_names[$logkey]);
                }
            }
        }

		// For existing records, get participant identifier and email (if don't have them - i.e. because this is a follow-up survey)
		if (!empty($record_names))
		{
			// Get emails/identifiers
			$recordsEmail = Survey::getResponsesEmailsIdentifiers($record_names, null);
			// Loop through those that are missing and add those to $invitationLog from $recordsEmail
			foreach ($record_names as $logkey=>$this_record)
			{
				if ($invitationLog[$logkey]['participant_email'] == "") {
					$invitationLog[$logkey]['participant_email'] = $recordsEmail[$this_record]['email'];
				}
				if ($invitationLog[$logkey]['participant_identifier'] == "") {
					$invitationLog[$logkey]['participant_identifier'] = $recordsEmail[$this_record]['identifier'];
				}
				if ($twilio_enabled && $Proj->twilio_enabled_surveys && $invitationLog[$logkey]['participant_phone'] == "") {
					$invitationLog[$logkey]['participant_phone'] = $recordsEmail[$this_record]['phone'];
				}
			}
			unset($recordsEmail);
		}

		// Determine if designated email address is being used
		$designatedEmailFieldRecord = $designatedPhoneFieldRecord = array();
		// Create array of records for these participants
		$surveyEmailInvitationFields = $Proj->getSurveyEmailInvitationFields(true);
		if ((!empty($surveyEmailInvitationFields) || $survey_phone_participant_field != '') && !empty($record_names))
		{
			$surveyEmailPhoneInvitationFields = $surveyEmailInvitationFields;
			if ($survey_phone_participant_field != '') {
				$surveyEmailPhoneInvitationFields['phone'] = $survey_phone_participant_field;
			}
			$survey_email_part_field_data = Records::getData('array', $record_names, $surveyEmailPhoneInvitationFields);
			// Loop through data and get non-blank email values and store for each record
			foreach ($survey_email_part_field_data as $this_record=>$event_data) {
				// Loop through all event data for this record
				foreach ($event_data as $this_event_id=>$field_data) {
					if ($this_event_id != 'repeat_instances') {
						// Non-repeating data
						foreach ($surveyEmailPhoneInvitationFields as $thisSurveyId=>$thisSurveyEmailPhoneInvitationField) {
							$thisValType = $Proj->metadata[$thisSurveyEmailPhoneInvitationField]['element_validation_type'];
							if ($field_data[$thisSurveyEmailPhoneInvitationField] != '' && $thisValType == 'email') {
								$designatedEmailFieldRecord[$this_record][$thisSurveyId] = $field_data[$thisSurveyEmailPhoneInvitationField];
							}
							if (isset($field_data[$survey_phone_participant_field]) && $field_data[$survey_phone_participant_field] != '' && $thisValType != 'email') {
								$designatedPhoneFieldRecord[$this_record] = $field_data[$survey_phone_participant_field];
							}
						}
					} else {
						// Repeating data
						foreach ($field_data as $event_data2) {
							foreach ($event_data2 as $instance_data) {
								foreach ($instance_data as $field_data2) {
									foreach ($surveyEmailPhoneInvitationFields as $thisSurveyId=>$thisSurveyEmailPhoneInvitationField) {
										$thisValType = $Proj->metadata[$thisSurveyEmailPhoneInvitationField]['element_validation_type'];
										if ($field_data2[$thisSurveyEmailPhoneInvitationField] != '' && $thisValType == 'email') {
											$designatedEmailFieldRecord[$this_record][$thisSurveyId] = $field_data2[$thisSurveyEmailPhoneInvitationField];
										}
										if (isset($field_data2[$survey_phone_participant_field]) && $field_data2[$survey_phone_participant_field] != '' && $thisValType != 'email') {
											$designatedPhoneFieldRecord[$this_record] = $field_data2[$survey_phone_participant_field];
										}
									}
								}
							}
						}
					}
				}
			}
			unset($record_names, $survey_email_part_field_data);
		}

		// Loop through all invitations and add display_id attribute
		$displayed_records = array();
		foreach ($invitationLog as $key=>$attr)
		{
			// Display record name only if not an anonymous survey response
			$invitationLog[$key]['display_id'] = "";
			if ($attr['record'] != '') {
				// Deal with survey-level email invitation fields
				if (isset($designatedEmailFieldRecord[$attr['record']][$attr['survey_id']]) && $designatedEmailFieldRecord[$attr['record']][$attr['survey_id']] != '') {
					$invitationLog[$key]['participant_email'] = $attr['participant_email'] = $designatedEmailFieldRecord[$attr['record']][$attr['survey_id']];
				}
				if (
					// Display record name if participant has an Identifier
					$attr['participant_identifier'] != ''
					// OR if the email address originates from the designated email field
					|| ($survey_email_participant_field != ''
							&& isset($designatedEmailFieldRecord[$attr['record']][''])
							&& ($attr['participant_email'] == $designatedEmailFieldRecord[$attr['record']][''])
						)
					// OR if the email address originates from the designated SURVEY-LEVELemail field
					|| (isset($designatedEmailFieldRecord[$attr['record']][$attr['survey_id']])
							&& ($attr['participant_email'] == $designatedEmailFieldRecord[$attr['record']][$attr['survey_id']])
						)
                    // OR if the phone number originates from the designated phone field
                    || ($survey_phone_participant_field != ''
                            && isset($designatedPhoneFieldRecord[$attr['record']])
                            && (formatPhone($attr['participant_phone']) == formatPhone($designatedPhoneFieldRecord[$attr['record']]))
                        )
                )
				{
					$invitationLog[$key]['display_id'] = $displayed_records[$attr['record']] = $attr['record'];					
				}
			}
		}
		natcasesort($displayed_records);

		// Loop through all invitations and remove those that should be filtered
		foreach ($invitationLog as $key=>$attr)
		{
			// Filter by *displayed* record named
			if ($_GET['filterRecord'] != '' && $attr['display_id'] != $_GET['filterRecord']) {
				unset($invitationLog[$key]); continue;
			}
			// If this is a reminder invitation and we're not displaying reminders, then skip this loop
			if ($_GET['filterReminders'] == '0' && $attr['reminder_num'] > 0) {
				unset($invitationLog[$key]); continue;
			}
			// Filter if sent/not sent
			if ($_GET['filterInviteType'] != '' && $_GET['filterInviteType'] != '-2' && $attr['was_sent'] != $_GET['filterInviteType']) {
				unset($invitationLog[$key]); continue;
			}
			// Filter if viewing only deleted invitations
			if ($_GET['filterInviteType'] == '-2' && $attr['scheduled_status'] != 'DELETED') {
				unset($invitationLog[$key]); continue;
			}
			// Filter by response type
			if ($_GET['filterResponseType'] != '' && $attr['completed'] != $_GET['filterResponseType']) {
				unset($invitationLog[$key]); continue;
			}
			// Filter by begin time
			if (isset($filterBeginTimeYmd) && substr($attr['send_time'], 0, 16) < $filterBeginTimeYmd) {
				unset($invitationLog[$key]); continue;
			}
			// Filter by end time
			if (isset($filterEndTimeYmd) && substr($attr['send_time'], 0, 16) > $filterEndTimeYmd) {
				unset($invitationLog[$key]); continue;
			}
			// Filter by DAG (if current user is assigned to a DAG)
			if ($user_rights['group_id'] != '' && $attr['record'] != '' && !isset($dag_records[$attr['record']])) {
				unset($invitationLog[$key]); continue;
			}
			// If have identifiers disabled AND not using survey email field AND is an existing record
			// AND has an email AND does not have an identifier, then obscure the email address from the user (preserve anonymity).
			if (!$enable_participant_identifiers && $survey_email_participant_field == '' 
				&& !isset($surveyEmailInvitationFields[$attr['survey_id']]) && $attr['record'] != ""
				&& $attr['participant_email'] != "" && $attr['participant_identifier'] == "")
			{
				$invitationLog[$key]['participant_email'] = $lang['survey_499'];
			}
			if (!$enable_participant_identifiers && $survey_phone_participant_field == '' && $attr['record'] != ""
				&& isset($attr['participant_phone']) && $attr['participant_phone'] != "" && $attr['participant_identifier'] == "")
			{
				$invitationLog[$key]['participant_phone'] = $lang['survey_903'];
			}
		}
		// Return log as array
		if ($returnCountOnly) {
			return count($invitationLog);
		} else {
			return array($invitationLog, $displayed_records);
		}
	}


	// Display a table listing all survey invitations (past, present, and future) with filters and paging
	public function renderSurveyInvitationLog($record=null, $showFullTableDisplay=true, $scheduledInNextXdays=null)
	{
		// Initialize vars
		global  $Proj, $longitudinal, $lang, $twilio_enabled;

		// Get the invitation log
		list ($invitationLog, $displayed_records) = $this->getSurveyInvitationLog($record, $scheduledInNextXdays);

		// Set NOW in user defined date format but with military time
		$now_user_date_military_time = DateTimeRC::format_ts_from_ymd(TODAY).date(' H:i');

		## BUILD THE DROP-DOWN FOR PAGING THE INVITATIONS
		// Get participant count
		$invite_count = count($invitationLog);
		// Section the Participant List into multiple pages
		$num_per_page = 100;
		//Calculate number of pages of for dropdown
		$pageDropdown = "";
		$num_pages = ceil($invite_count/$num_per_page);
		if ($num_pages == 0) {
			$pageDropdown .= "<option value=''>0</option>";
		} else {
			$pageDropdown .= "<option value='ALL'>-- {$lang['docs_44']} --</option>";
		}
		// Limit
		$limit_begin  = 0;
		if (isset($_GET['pagenum']) && $_GET['pagenum'] == 'last') {
			$_GET['pagenum'] = $num_pages;
		}
		if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum']) && $_GET['pagenum'] > 1) {
			$limit_begin = ($_GET['pagenum'] - 1) * $num_per_page;
		}
		## Build the paging drop-down for participant list
		$pageDropdown = "<select id='pageNumInviteLog' onchange='loadInvitationLog(this.value)' style='vertical-align:middle;font-size:11px;'>";
		//Loop to create options for dropdown
		for ($i = 1; $i <= $num_pages; $i++) {
			$end_num   = $i * $num_per_page;
			$begin_num = $end_num - $num_per_page + 1;
			$value_num = $end_num - $num_per_page;
			if ($end_num > $invite_count) $end_num = $invite_count;
			$pageDropdown .= "<option value='$i' " . ($_GET['pagenum'] == $i ? "selected" : "") . ">$begin_num - $end_num</option>";
		}
		$pageDropdown .= "</select>";
		$pageDropdown  = "{$lang['survey_45']} $pageDropdown {$lang['survey_133']} $invite_count";

		// If viewing ALL invitations, then set $num_per_page to null to return all invitations
		if ($_GET['pagenum'] == 'ALL' || !$showFullTableDisplay) $num_per_page = null;

		// Set some flags to disable buttons
		$disableViewPastInvites = $disableViewFutureInvites = "";
		// Set flags (if timestamp is within the same hour as now, then consider it now)
		if ($_GET['filterBeginTime'] == '' && substr($_GET['filterEndTime'], 0, -2) == substr($now_user_date_military_time, 0, -2)) {
			$disableViewPastInvites = "disabled";
		}
		if ($_GET['filterEndTime'] == '' && substr($_GET['filterBeginTime'], 0, -2) == substr($now_user_date_military_time, 0, -2)) {
			$disableViewFutureInvites = "disabled";
		}

		// Loop through all invitations for THIS PAGE and build table
		$rownum = 0;
		foreach (array_slice($invitationLog, $limit_begin, $num_per_page) as $row)
		{
			// Set color of timestamp (green if already sent, red if failed) and icon
			$tsColor = ($row['was_sent'] == '0') ? "gray" : ($row['was_sent'] == '1' ? "green" : "red");
			if ($row['scheduled_status'] == 'DELETED') $tsColor = 'red';
			$tsIcon  = ($row['was_sent'] == '0') ? "clock_small.png" : ($row['was_sent'] == '1' ? "tick_small_circle.png" : "bullet_delete.png");
			$tsIconStyle = ($row['reminder_num'] == '0' ? 'margin-right:2px;' : '');
			if ($row['scheduled_status'] == 'DELETED') $tsIconStyle = 'display:none;';

			// If this invitation is a reminder, then display bell icon
			$reminderIcon = ($row['reminder_num'] == '0' ? '' :
								RCView::span(array('class'=>"remn", 'title'=>$lang['survey_754'] . " " . $row['reminder_num']),
									"(" . RCView::img(array('src'=>"bell_small.png", 'class'=>'opacity75', 'style'=>'margin-right:-2px;')) .
									$row['reminder_num'] . ")"
								)
							);

			// If scheduled and not sent yet, display cross icon to delete the invitation
			$deleteEditInviteIcons = '';
            if ($showFullTableDisplay) {
                if ($row['was_sent'] == '0' && $row['scheduled_status'] != 'DELETED' && $row['email_recip_id'] != '') {
                    $deleteEditInviteIcons = RCView::a(array('href' => 'javascript:;', 'style' => 'margin-left:8px;', 'onclick' => "editSurveyInviteTime({$row['email_recip_id']},{$row['reminder_num']})"),
                            RCView::img(array('src' => 'pencil_small.png', 'class' => 'inviteLogDelIcon opacity50', 'title' => $lang['survey_490']))
                        ) .
                        RCView::a(array('href' => 'javascript:;', 'style' => 'margin:0 2px 0 3px;', 'onclick' => "deleteSurveyInvite({$row['email_recip_id']},{$row['reminder_num']})"),
                            RCView::img(array('src' => 'cross_small2.png', 'class' => 'inviteLogDelIcon opacity50', 'title' => $lang['survey_486']))
                        );
                } elseif ($row['scheduled_status'] == 'DELETED') {
                    $deleteEditInviteIcons = RCView::div(array('class' => 'fs11', 'style' => 'color:red;'), $lang['asi_032']);
                } elseif (isset($row['repeating']) && $row['repeating']) {
                    $deleteEditInviteIcons = '<i class="fas fa-redo fs11" style="color:#999;margin-left:8px;margin-right:5px;" title="' . RCView::tt_js2('survey_1502') . '"></i>' .
                        RCView::a(array('href' => 'javascript:;', 'style' => 'margin:0 2px 0 3px;', 'onclick' => "deleteSurveyInviteRecurrence({$row['ssr_id']})"),
                            RCView::img(array('src' => 'cross_small2.png', 'class' => 'inviteLogDelIcon opacity50', 'title' => $lang['survey_1504']))
                        );
                }
            }

			// Get the form name of this survey_id
			$form = $Proj->surveys[$row['survey_id']]['form_name'];

			// Send time (and icon)
			$rows[$rownum][] = 	// Invisible YMD timestamp (for sorting purposes
								RCView::span(array('class'=>'hidden'), $row['send_time']) .
								// Display time and icon
								RCView::span(array('style'=>"color:$tsColor;"),
									RCView::img(array('src'=>$tsIcon, 'style'=>$tsIconStyle)) .
									DateTimeRC::format_ts_from_ymd($row['send_time']) .
									$deleteEditInviteIcons .
									$reminderIcon
								);

			if (!$showFullTableDisplay) {
				// Survey title (and event)
				$rows[$rownum][] = 	RCView::div(array('class'=>"wrap", 'style'=>"line-height:1.1;"),
                                        RCView::div(array('style'=>"color:#800000;"),
                                            // Survey title
                                            ($Proj->surveys[$row['survey_id']]['title'] == '' ? $Proj->forms[$form]['menu'] : $Proj->surveys[$row['survey_id']]['title'])
                                        ) .
                                        RCView::div(array('style'=>"color:#777;"),
                                            // Display event (if longitudinal)
                                            (!$longitudinal ? "" : $Proj->eventInfo[$row['event_id']]['name_ext'])
                                        )
									);
				// Stop here for limited view (no more to display after title)
				$rownum++;
				continue;
			}

			// View message - set delivery preference icon
			if ($row['delivery_type'] == 'VOICE_INITIATE') {
				$deliv_pref_icon = RCView::img(array('src'=>'phone.gif', 'title'=>$lang['survey_884']));
			} else if ($row['delivery_type'] == 'SMS_INITIATE') {
				$deliv_pref_icon = RCView::img(array('src'=>'balloons_box.png', 'title'=>$lang['survey_767']));
			} else if ($row['delivery_type'] == 'SMS_INVITE_MAKE_CALL') {
				$deliv_pref_icon = RCView::img(array('src'=>'balloon_phone.gif', 'title'=>$lang['survey_690']));
			} else if ($row['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL') {
				$deliv_pref_icon = RCView::img(array('src'=>'balloon_phone_receive.gif', 'title'=>$lang['survey_801']));
			} else if ($row['delivery_type'] == 'SMS_INVITE_WEB') {
				$deliv_pref_icon = RCView::img(array('src'=>'balloon_link.gif', 'title'=>$lang['survey_955']));
			} else {
				$deliv_pref_icon = RCView::img(array('src'=>'mail_open_document.png', 'title'=>$lang['survey_902']));
			}
            if (isinteger($row['ssr_id'])) {
                $rows[$rownum][] = RCView::a(array('href' => 'javascript:;', 'onclick' => "viewRecurringEmail('{$row['ssr_id']}','{$row['instance']}', '{$row['send_time']}');"),
                    $deliv_pref_icon
                );
            } else {
                $rows[$rownum][] = RCView::a(array('href' => 'javascript:;', 'onclick' => "viewEmail('{$row['email_recip_id']}','{$row['ssq_id']}');"),
                    $deliv_pref_icon
                );
            }

			// Email address
			if ($row['participant_email'] != "") {
				$rows[$rownum][] = RCView::div(array('class'=>'wrapemail'), $row['participant_email']);
			} else {
				$rows[$rownum][] = RCView::span(array('style'=>"color:#777;"), $lang['survey_284']);
			}

			// Phone number
			if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
				$rows[$rownum][] = RCView::div(array('class'=>'wrapemail'), formatPhone($row['participant_phone']));
			}
			
			// Record ID (if not anonymous response)
			$rows[$rownum][] = 	RCView::div(array('class'=>'wrap', 'style'=>'word-wrap:break-word;'), 
									($row['record'] == '' ? "" : ($row['display_id'] == '' ? '<i class="far fa-eye-slash" style="color:#ddd;"></i>' :
										RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/index.php?pid=".$this->project_id."&page=$form&event_id={$row['event_id']}&id={$row['record']}&instance={$row['instance']}", 'style'=>'font-size:12px;text-decoration:underline;'), $row['display_id']) .
										($Proj->isRepeatingFormOrEvent($row['event_id'], $form) ? "&nbsp;&nbsp;<span style='color:#777;'>(#{$row['instance']})</span>" : "")
									))
								);

			// Participant Identifier
			$rows[$rownum][] = RCView::div(array('class'=>'wrapemail'), $row['participant_identifier']);

			// Survey title (and event)
            $survey_title = trim(strip_tags($Proj->surveys[$row['survey_id']]['title']));
            $this_survey_title = $survey_title;
            $this_form_label = trim(strip_tags($Proj->forms[$Proj->surveys[$row['survey_id']]['form_name']]['menu']));
            if ($this_survey_title == "") {
                $this_title = "\"$this_form_label\"";
            } elseif ($this_survey_title == $this_form_label) {
                $this_title = "\"$this_survey_title\"";
            } else {
                $this_title = "\"$this_survey_title\" [$this_form_label]";
            }
			$rows[$rownum][] = 	RCView::div(array('style'=>"color:#800000;"),
									// Survey title
                                    $this_title
								) .
								RCView::div(array('style'=>"color:#777;"),
									// Display event (if longitudinal)
									(!$longitudinal ? "" : strip_tags($Proj->eventInfo[$row['event_id']]['name_ext']))
								);

			// Display "open survey" link (if not completed yet)
			if ($row['completed'] == "2") {
				$rows[$rownum][] = "-";
			} else {
				$rows[$rownum][] = 	RCView::a(array('target'=>'_blank','href'=>APP_PATH_SURVEY_FULL."index.php?s={$row['hash']}"),
										RCView::img(array('src'=>'link.png','style'=>'','title'=>$lang['survey_246']))
									);
			}

			## Response-completed status
			if ($row['completed'] == "1") {
				// Partial response
				$completedIcon = "circle_orange_tick.png";
			} elseif ($row['completed'] == "2") {
				// Completed response
				$completedIcon = "circle_green_tick.png";
			} else {
				// Response doesn't exist yet (not started survey)
				$completedIcon = "stop_gray.png";
			}
			// If record exists and has an identifier, then make icon a link to the record
			if ($row['completed'] != "" && $row['display_id'] != "") {
				$rows[$rownum][] = 	RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/index.php?pid=".$this->project_id."&page=$form&event_id={$row['event_id']}&id={$row['record']}",'target'=>'_blank'),
										RCView::img(array('src'=>$completedIcon,'title'=>$lang['survey_245'],'class'=>'viewresponse'))
									);
			}
			// Display only icon with no link
			else {
				$rows[$rownum][] = RCView::img(array('src'=>$completedIcon,'class'=>'noviewresponse'));
			}

			// Reason not sent
			if ($row['error_message'] != "") {
				$rows[$rownum][] = RCView::div(array('class'=>'wrap', 'style'=>'line-height:12px;'), $row['reason_not_sent']." - ".$row['error_message']);
			} elseif ($row['reason_not_sent'] != "") {
				$rows[$rownum][] = RCView::div(array('class'=>'wrap', 'style'=>'line-height:12px;'), $row['reason_not_sent']);
			} else {
				$rows[$rownum][] = "";
			}

			// Checkbox to delete invite
			if ($showFullTableDisplay) {
				$rows[$rownum][] = ($row['was_sent'] != '0' || $row['scheduled_status'] == 'DELETED' || $row['email_recip_id'] == '') ? "" : RCView::checkbox(array('id'=>'delssq_'.$row['ssq_id']));
			}
			
			// Increment counter
			$rownum++;
		}

		// Give message if no invitations were sent
		if (empty($rows)) {
			$rows[$rownum] = array(RCView::div(array('class'=>'wrap','style'=>'color:#800000;'), $lang['survey_435']),"","","");
		}

		// Define table headers
		$headers = array();
		if ($showFullTableDisplay) {
			$headers[] = array(190, RCView::img(array('class'=>'survlogsendarrow', 'src'=>'draw-arrow-down.png', 'style'=>'vertical-align:middle;')) .
									RCView::img(array('class'=>'survlogsendarrow', 'src'=>'draw-arrow-up.png', 'style'=>'display:none;vertical-align:middle;')) .
									RCView::SP .
									$lang['survey_436']);
			$headers[] = array(28,  RCView::span(array('class'=>'wrap'), $lang['survey_901']), "center");
			if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
				// Phone number
				$headers[] = array(108, RCView::span(array('class'=>'wrap'), $lang['survey_392']));
				$headers[] = array(90, RCView::span(array('class'=>'wrap'), $lang['survey_1055']));
			} else {
				$headers[] = array(160, RCView::span(array('class'=>'wrap'), $lang['survey_392']));
			}
			$headers[] = array(60, RCView::div(array('class'=>'wrap'), $lang['global_49']), "center");
			$headers[] = array(100, RCView::span(array('class'=>'wrap'), $lang['survey_250']));
			$headers[] = array(195, $lang['survey_437']);
			$headers[] = array(38,  RCView::span(array('class'=>'wrap'), $lang['global_90']), "center");
			$headers[] = array(67,  $lang['survey_47'], "center");
			$headers[] = array(70, RCView::span(array('class'=>'wrap'), $lang['survey_1056']));
			$headers[] = array(30, RCView::checkbox(array('onclick'=>"selectOrDeselectAllDeleteInvite()")), "center");
		} else {
			// Limited display
			$headers[] = array(160, $lang['survey_436']);
			$headers[] = array(343, "");
		}
		// Add checkbox as checked for "Display reminders?"
		$filterRemindersChecked = ($_GET['filterReminders'] == '1') ? "checked" : "";
		// Define title
		$title = "";
		if ($showFullTableDisplay) {
			$title =	RCView::div(array('style'=>''),
							RCView::div(array('style'=>'padding:2px 20px 0 5px;float:left;font-size:14px;'),
								'<i class="fas fa-mail-bulk"></i> ' .
								$lang['survey_350'] . RCView::br() .
								RCView::span(array('style'=>'line-height:24px;color:#666;font-size:11px;font-weight:normal;'),
									$lang['survey_570']
								) . RCView::br() . RCView::br() .
								RCView::span(array('style'=>'color:#555;font-size:11px;font-weight:normal;'),
									$pageDropdown
								)
							) .
							## QUICK BUTTONS
							RCView::div(array('style'=>'font-weight:normal;float:left;font-size:11px;padding-left:12px;border-left:1px solid #ccc;'),
								RCView::button(array($disableViewPastInvites=>$disableViewPastInvites, 'class'=>'jqbuttonsm', 'style'=>'margin-top:12px;font-size:11px;color:green;display:block;',
									'onclick'=>"$('#filterBeginTime').val('');$('#filterEndTime').val('$now_user_date_military_time');loadInvitationLog('last')"), $lang['survey_571']) .
								RCView::button(array($disableViewFutureInvites=>$disableViewFutureInvites, 'class'=>'jqbuttonsm', 'style'=>'margin-top:12px;font-size:11px;color:#000066;display:block;',
									'onclick'=>"$('#filterBeginTime').val('$now_user_date_military_time');$('#filterEndTime').val('');loadInvitationLog(1)"), $lang['survey_572'])
							) .
							## FILTERS
							RCView::div(array('style'=>'white-space:normal;max-width:680px;width:680px;font-weight:normal;float:left;font-size:11px;padding-left:15px;margin-left:15px;border-left:1px solid #ccc;'),
								// Date/time range
								$lang['survey_439'] .
								RCView::text(array('id'=>'filterBeginTime','value'=>$_GET['filterBeginTime'],'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-right:8px;margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
								$lang['survey_440'] .
								RCView::text(array('id'=>'filterEndTime','value'=>$_GET['filterEndTime'],'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
								RCView::span(array('class'=>'df','style'=>'color:#777;'), '('.DateTimeRC::get_user_format_label().' H:M)') . RCView::br() .
								// Display invitations types and responses status types
								$lang['survey_441'] .
								RCView::select(array('id'=>'filterInviteType','style'=>'margin-left:3px;font-size:11px;'),
									array(''=>$lang['survey_443']." &nbsp;".$lang['asi_031'], '1'=>$lang['survey_444'], '0'=>$lang['survey_445'], '-1'=>$lang['survey_479'], '-2'=>$lang['asi_030']), $_GET['filterInviteType'], 500) .
								" {$lang['global_43']} " .
								RCView::select(array('id'=>'filterResponseType','style'=>'font-size:11px;'),
									array(''=>$lang['survey_446'], '0'=>$lang['survey_447'], '1'=>$lang['survey_448'], '2'=>$lang['survey_449']),$_GET['filterResponseType']) .
								RCView::br() .
								// Display specific surveys
								$lang['survey_441'] .
								RCView::select(array('id'=>'filterSurveyEvent','style'=>'margin-left:3px;font-size:11px;'),
									array_merge(array('0-0'=>$lang['survey_450']), $this->getInvitationLogSurveyList()),$_GET['filterSurveyEvent'],300) .
								RCView::br() .
								// Display record names displayed in this view								
								$lang['survey_441'] .
                                Records::renderRecordListAutocompleteDropdown($Proj->project_id, true, 5000, 'filterRecord',
                                    "", "margin-left:3px;font-size:11px;", $_GET['filterRecord'], $lang['reporting_37'], $lang['alerts_205']) .
								RCView::br() .
								// Display invitation reminders
								RCView::checkbox(array('id'=>'filterReminders', 'style'=>'', $filterRemindersChecked=>$filterRemindersChecked)) .
								$lang['survey_740'] .
								RCView::div(array('class'=>'clearfix mt-1'),
									// "Apply filters" button
									RCView::button(array('class'=>'jqbuttonsm','style'=>'float:left;margin-top:5px;font-size:11px;color:#800000;','onclick'=>"loadInvitationLog(1)"), $lang['survey_442']) .
									RCView::a(array('href'=>PAGE_FULL."?pid=".$this->project_id."&email_log=1",'style'=>'float:left;vertical-align:middle;margin-left:15px;margin-top:6px;text-decoration:underline;font-weight:normal;font-size:11px;'), $lang['setup_53']) .
									// "Download log" button
									RCView::button(array('class'=>'btn btn-xs btn-defaultrc','style'=>'float:left;margin:5px 0 0 70px;font-size:11px;color:#006000;','onclick'=>"window.location.href = app_path_webroot+'Surveys/invitation_log_export.php'+window.location.search;"),
										RCView::img(array('src'=>'xls.gif', 'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_1053'])
									) .
									// "Delete selected" button
									RCView::button(array('class'=>'btn btn-xs btn-defaultrc','style'=>'float:right;margin:5px 0 0;font-size:11px;color:#A00000;','onclick'=>"deleteMultipleInvites();"),
										'<i class="fas fa-check-square" style="vertical-align:middle;"></i> ' .
										RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_1212'])
									)
								)
							) .
							RCView::div(array('class'=>'clear'), '')
						);
		}
		$width = !$showFullTableDisplay ? 530 : (1070+($twilio_enabled && $Proj->twilio_enabled_surveys ? 50 : 0));
		// Build Invitation Log table
		return renderGrid("email_log_table", $title, $width, 'auto', $headers, $rows, true, true, false);
	}


	// Return true if a record's survey invitation is already scheduled for a given survey/event
	public static function checkIfRecordScheduled($survey_id, $event_id, $record)
	{
		$sql = "select 1 from redcap_surveys_scheduler s, redcap_surveys_scheduler_queue q
				where s.ss_id = q.ss_id and s.survey_id = $survey_id and s.event_id = $event_id
				and q.record = '" . db_escape($record) . "' limit 1";
		$q = db_query($sql);
		// Return true if has been scheduled
		return (db_num_rows($q) > 0);
	}


	// Return true if a record's Form Status value for a given survey/event is Complete (=2)
	public static function isFormStatusCompleted($survey_id, $event_id, $record, $instance=1)
	{
		// Set SQl for instance
		$instance = (int)$instance;
		$instanceSql = ($instance > 1) ? "and d.instance = '".db_escape($instance)."'" : "and d.instance is null";
		// Query data table for value of 2
        $project_id = Survey::getProjectIdFromSurveyId($survey_id);
		$sql = "select 1 from ".\Records::getDataTable($project_id)." d, redcap_surveys s where d.project_id = s.project_id
				and d.event_id = $event_id and d.record = '" . db_escape($record) . "' and s.survey_id = $survey_id
				and d.field_name = concat(s.form_name, '_complete') and d.value = '2' $instanceSql limit 1";
		$q = db_query($sql);
		// Return true if has been scheduled
		return (db_num_rows($q) > 0);
	}


	// Determine if this record needs to have a survey invitation scheduled
	public function checkConditionsOfRecordToSchedule($survey_id, $event_id, $record, $instanceNum=1)
	{
		// Fill up $schedules array with schedules
		$this->setSchedules();
		// Check the schedule's attributes
		if (!isset($this->schedules[$survey_id][$event_id])) return false;
		$thisSchedule = $this->schedules[$survey_id][$event_id];
		// Check response limit (if enabled) - do not schedule invitation for this survey/event if hit limit already
		if (Survey::reachedResponseLimit($this->project_id, $survey_id, $event_id)) {
			return false;
		}
        $Proj = new Project($this->project_id);
		// If conditional upon survey completion, check if completed survey
		$conditionsPassedSurveyComplete = ($thisSchedule['condition_andor'] == 'AND'); // Initial true value if using AND (false if using OR)
		if (is_numeric($thisSchedule['condition_surveycomplete_survey_id']) && is_numeric($thisSchedule['condition_surveycomplete_event_id']))
		{
            // If we're checking if another survey on the same *repeating event* is complete, then use the current instance, otherwise use instance 1.
            $conditionCompleteInstanceNum = 1;
            if ($Proj->longitudinal && $event_id == $thisSchedule['condition_surveycomplete_event_id'] && $Proj->isRepeatingEvent($event_id)) {
                $conditionCompleteInstanceNum = $instanceNum;
            }
			// Is it a completed response?
			$conditionsPassedSurveyComplete = Survey::isResponseCompleted($thisSchedule['condition_surveycomplete_survey_id'], $record, $thisSchedule['condition_surveycomplete_event_id'], $conditionCompleteInstanceNum);
			// If not listed as a completed response, then also check Form Status (if entered as plain record data instead of as response), just in case
			if (!$conditionsPassedSurveyComplete) {
				$conditionsPassedSurveyComplete = self::isFormStatusCompleted($thisSchedule['condition_surveycomplete_survey_id'], $thisSchedule['condition_surveycomplete_event_id'], $record, $conditionCompleteInstanceNum);
			}
		}
        // If conditional upon custom logic
		$conditionsPassedLogic = ($thisSchedule['condition_andor'] == 'AND'); // Initial true value if using AND (false if using OR)
		if ($thisSchedule['condition_logic'] != ''
			// If using AND and $conditionsPassedSurveyComplete is false, then no need to waste time checking evaluateLogicSingleRecord().
			// If using OR and $conditionsPassedSurveyComplete is true, then no need to waste time checking evaluateLogicSingleRecord().
			&& (($thisSchedule['condition_andor'] == 'OR' && !$conditionsPassedSurveyComplete)
				|| ($thisSchedule['condition_andor'] == 'AND' && $conditionsPassedSurveyComplete)))
		{
            $Proj = new Project($this->project_id);
            // If longitudinal and logic is missing prepended unique event names, prepend them
            if ($Proj->longitudinal) {
                $thisSchedule['condition_logic'] = LogicTester::logicPrependEventName($thisSchedule['condition_logic'], $Proj->getUniqueEventNames($event_id), $Proj);
            }
            if ($Proj->hasRepeatingFormsEvents()) {
                $thisSchedule['condition_logic'] = LogicTester::logicAppendCurrentInstance($thisSchedule['condition_logic'], $Proj, $event_id);
            }
			// Pipe any special piping tags
			if (Piping::containsSpecialTags($thisSchedule['condition_logic'])) {
				$form = $Proj->surveys[$survey_id]['form_name'];
				$thisSchedule['condition_logic'] = Piping::pipeSpecialTags($thisSchedule['condition_logic'], $this->project_id, $record, $event_id, $instanceNum, null, true, null, $form, false, false, false, true, false, false, true);
			}
			// Does the logic evaluate as true?
			$conditionsPassedLogic = LogicTester::evaluateLogicSingleRecord($thisSchedule['condition_logic'], $record, null, $this->project_id, $instanceNum, null, false, false, true);
		}
		// Check pass/fail values and return boolean if record is ready to have its invitation for this survey/event
		if ($thisSchedule['condition_andor'] == 'OR') {
			// OR
			return ($conditionsPassedSurveyComplete || $conditionsPassedLogic);
		} else {
			// AND (default)
			return ($conditionsPassedSurveyComplete && $conditionsPassedLogic);
		}
	}


	// Get all schedules where record has already had invitations scheduled. Return array with survey_id and event_id as key/subkey
	public function getAlreadyScheduledForRecord($record)
	{
        if ($this->schedulePkLink === null) $this->setSchedules();
        if (empty($this->schedulePkLink)) return [];
		// Initial empty array
		$alreadyScheduled = [];
		// Query surveys_scheduler_queue table to find any previous invitations already scheduled
		$sql = "select ss_id from redcap_surveys_scheduler_queue where record = '" . db_escape($record) . "'
				and ss_id in (" . implode(",", array_keys($this->schedulePkLink)) . ")";
		$q = db_query($sql);
		if($q !== false)
		{
			while ($row = db_fetch_assoc($q)) {
				foreach ($this->schedulePkLink[$row['ss_id']] as $survey_id=>$event_id) {
                    $alreadyScheduled[$survey_id][$event_id] = true;
				}
			}
		}
		// Return array
		return $alreadyScheduled;
	}


	// Check if we're ready to schedule the participant's survey invitation to be sent. Return boolean regarding if was scheduled.
	public function checkToScheduleParticipantInvitation($record, $isNewRecord=false, $surveysEvents=array(), $is_dry_run=false)
	{
		// Set initial return value as 0
		$numInvitationsScheduled = 0;

		// Collect survey_id/event_id of any invitations that have been scheduled and need to be removed
		$schedulesToRemove = array();
		$recordsAffected = array();

		// Fill up $schedules array with schedules
		$this->setSchedules();

		// Find available schedules by removing all irrelevant ones
		// (e.g., exact time schedules, any that are already scheduled, schedules dependent upon other available schedules)
		$availableSchedules = $this->getAvailableSchedulesForRecord($record, $isNewRecord);

		// Only perform the check for specific surveys/events?
		$onlyCheckCertainSurveysEvents = (is_array($surveysEvents) && !empty($surveysEvents));

		// Loop through all relevant schedules
		foreach ($availableSchedules as $survey_id=>$events)
		{
			foreach (array_keys($events) as $event_id)
            {
                // Only perform the check for specific surveys/events?
                if ($onlyCheckCertainSurveysEvents && !isset($surveysEvents[$survey_id][$event_id])) continue;
                // Determine if this record needs to have a survey invitation scheduled
                $readyToSchedule = $this->checkConditionsOfRecordToSchedule($survey_id, $event_id, $record);
                if ($readyToSchedule) {
                    // If this is a repeating ASI with logic+reeval_before_send, then check if this is a non-first instance. If not first instance, then set flag $addRecurrenceSchedule
                    $addRecurrenceSchedule = false; // default
                    $isRepeatingAsi = ($events[$event_id]['num_recurrence'] > 0 && $events[$event_id]['reeval_before_send'] == '1' && $events[$event_id]['condition_logic'] != '');
                    if ($isRepeatingAsi) {
                        // If this repeating ASI already has instance #1 already sent/scheduled, then we should ensure that the recurrence schedule exists (in case it was removed
                        $sql = "select max(ifnull(q.instance, 1)) 
                                from redcap_surveys_scheduler_queue q, redcap_surveys_scheduler s
                                where q.ss_id = s.ss_id and q.record = '" . db_escape($record) . "'
                                and s.event_id = $event_id and s.survey_id = $survey_id";
                        $q = db_query($sql);
                        $max_instance = db_result($q, 0);
                        if ($max_instance == "") $max_instance = 0;
                        $nextRepeatingInstanceNum = ($max_instance+1);
                        $addRecurrenceSchedule = ($nextRepeatingInstanceNum > 1);
                    }
                    // Schedule the participant's survey invitation to be sent by adding it to the scheduler_queue table
                    $invitationWasScheduled = $this->scheduleParticipantInvitation($survey_id, $event_id, $record, 1, false, $addRecurrenceSchedule, false, $is_dry_run);
                    if ($invitationWasScheduled) {
                        // Increment number of invitations scheduled just now
                        $numInvitationsScheduled++;
                        $recordsAffected[$record] = true;
                    }
                } // If it is not ready to schedule but
                elseif ($availableSchedules[$survey_id][$event_id]['reeval_before_send'] == '1') {
                    $schedulesToRemove[$survey_id][$event_id] = true;
                }
			}
		}

        $invitationsDeleted = 0;

        // Find any unsent invitations/reminders that were scheduled manually (not ASIs)
        $sql = "select distinct q.ssq_id, r.record, s.survey_id, p.event_id, r.instance, q.reminder_num
				from redcap_surveys s, redcap_surveys_emails e, redcap_surveys_emails_recipients er, 
				     redcap_surveys_response r, redcap_surveys_scheduler_queue q, redcap_surveys_participants p
				where s.project_id = ".$this->project_id." and r.record = '".db_escape($record)."'  
				and r.completion_time is not null and q.status = 'QUEUED' and q.ss_id is null
				and s.survey_id = e.survey_id and e.email_id = er.email_id and q.email_recip_id = er.email_recip_id 
				and r.participant_id = er.participant_id and r.participant_id = p.participant_id 
				and r.record = q.record and r.instance = q.instance";
        $q = db_query($sql);
        $ssq_ids = [];
        while ($row = db_fetch_assoc($q))
        {
            // Prevent re-running same delete query
            if (isset($ssq_ids[$row['ssq_id']])) continue;
            $ssq_ids[$row['ssq_id']] =  true;
            // Remove from table
            $sql2 = "delete a.* from redcap_surveys_scheduler_queue a where a.ssq_id = ".$row['ssq_id'];
            $q2 = db_query($sql2);
            if (db_affected_rows() > 0) {
                $invitationsDeleted++;
                // Log the deletion
                if ($row['reminder_num'] == 0) {
                    Logging::logEvent($sql2,"redcap_surveys_scheduler_queue","MANAGE",$record,
                        "survey_id = {$row['survey_id']},\nevent_id = {$row['event_id']},\nrecord = '$record',\nssq_id = {$row['ssq_id']}",
                        "Automatically remove scheduled survey invitation", "", "SYSTEM", $row['project_id'], true, $row['event_id']);
                }
                $recordsAffected[$record] = true;
            }
        }

		// Remove any ASI schedules that have been scheduled but data values changed and cause it to be nullified
		if (!empty($schedulesToRemove)) {
			$invitationsDeleted += $this->deleteInvitationsForRecord($record, $schedulesToRemove);
			if ($invitationsDeleted > 0) {
				$recordsAffected[$record] = true;
			}
		}

		// Return count of invitation scheduled, if any
		return array($numInvitationsScheduled, $invitationsDeleted, count($recordsAffected));
	}


	// Delete all scheduled invitations for given surveys/events for this record
	public function deleteInvitationsForRecord($record, $survey_event_id_array=array())
	{
		// Initialize vars
		$ssq_ids = array();
		$invitationsDeleted = 0;
		// Build sub-sql from array
		$subsub = array();
        foreach ($survey_event_id_array as $this_survey_id=>$events) {
            foreach (array_keys($events) as $this_event_id) {
                $subsub[] = "(s.survey_id = $this_survey_id and s.event_id = $this_event_id)";
            }
        }
		if (empty($subsub)) return $invitationsDeleted;

		// If invitation is already queued, then set it as DID NOT SEND with reason_not_sent of SURVEY ALREADY COMPLETED
		$sql = "select q.ssq_id, s.survey_id, s.event_id, p.project_id, q.reminder_num
				from redcap_surveys_scheduler_queue q, redcap_surveys_scheduler s, redcap_surveys p
				where p.survey_id = s.survey_id and q.ss_id = s.ss_id and q.record = '" . db_escape($record) . "'
				and q.status = 'QUEUED' and (".implode(" or ", $subsub).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) 
		{
			$sql2 = "delete a.* from redcap_surveys_scheduler_queue a where a.ssq_id = ".$row['ssq_id'];
			$q2 = db_query($sql2);
			if (db_affected_rows() > 0) {
				$invitationsDeleted++;
				// Log the deletion
				if ($row['reminder_num'] == 0) {
					Logging::logEvent($sql2,"redcap_surveys_scheduler_queue","MANAGE",$record,
						"survey_id = {$row['survey_id']},\nevent_id = {$row['event_id']},\nrecord = '$record',\nssq_id = {$row['ssq_id']}",
						"Automatically remove scheduled survey invitation", "", "SYSTEM", $row['project_id'], true, $row['event_id']);
				}
			}
		}

        // Also delete any recurrences
        $sql = "select q.ssr_id from redcap_surveys_scheduler_recurrence q, redcap_surveys_scheduler s
				where q.ss_id = s.ss_id and q.record = '" . db_escape($record) . "' and (".implode(" or ", $subsub).")";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q))
        {
            db_query("delete from redcap_surveys_scheduler_recurrence where ssr_id = ".$row['ssr_id']);
        }

		return $invitationsDeleted;
	}


	// Return all surveys/events where survey has been completed for this record (so we can exclude them)
	private function getAlreadyCompletedForRecord($record)
	{
		// Put completed surveys/events in array
		$alreadyCompleted = array();
		// Get list of all available schedules for this record
		$availableSchedules = $this->schedules;
		// Query response table
		if (!empty($availableSchedules)) {
			$sql = "select p.survey_id, p.event_id, r.completion_time
                    from redcap_surveys_participants p, redcap_surveys_response r
					where r.participant_id = p.participant_id and p.survey_id in (".prep_implode(array_keys($availableSchedules)).")
					and r.record = '" . db_escape($record) . "' and r.completion_time is not null and r.instance = 1";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				if (isset($availableSchedules[$row['survey_id']][$row['event_id']])) {
					$alreadyCompleted[$row['survey_id']][$row['event_id']] = $row['completion_time'];
				}
			}
		}
		// Return array
		return $alreadyCompleted;
	}


	// Find available schedules by removing all irrelevant ones
	// (e.g., exact time schedules, any that are already scheduled, schedules dependent upon other available schedules)
	private function getAvailableSchedulesForRecord($record, $isNewRecord=false)
	{
		// Get all schedules where record has already had invitations scheduled
		$alreadyScheduled = $this->getAlreadyScheduledForRecord($record);

		// Get all surveys/events where survey has been completed for this record (so we can exclude them)
		$alreadyCompleted = $this->getAlreadyCompletedForRecord($record);
		
		// Get available schedules for project
		$availableSchedules = $this->schedules;
		
		// If project is on multiple arms but record is not on all arms, then ignore events where record doesn't exist
		$ignoreEvents = array();
		$Proj = new Project($this->project_id);
		if ($Proj->longitudinal && $Proj->multiple_arms && !$isNewRecord) {
			$armsOnSchedule = array();
			foreach ($availableSchedules as $survey_id=>$events) {
				foreach (array_keys($events) as $event_id) {
					$armsOnSchedule[] = $Proj->eventInfo[$event_id]['arm_num'];
				}
			}
			$armsOnSchedule = array_unique($armsOnSchedule);
			foreach ($armsOnSchedule as $this_arm) {
				$recordsThisArm = Records::getRecordListPerArm($this->project_id, array($record), $this_arm);
				if (!isset($recordsThisArm[$this_arm][$record])) {
					foreach (array_keys($Proj->events[$this_arm]['events']) as $event_id) {
						$ignoreEvents[$event_id] = true;
					}
				}
			}
		}

		// First, get only Conditional schedules to put in array AND remove all schedules already scheduled or completed for this record
		foreach ($availableSchedules as $survey_id=>$events) {
			foreach ($events as $event_id=>$attr) {
                // Get max instance number of the dependent record/event/survey, if a repeating ASI
                $instanceNum = 1;
                // Ignore if survey has already been completed
                if ((isset($alreadyCompleted[$survey_id][$event_id]))
                    // OR if invitations have already been scheduled for this survey/event
                    || (isset($alreadyScheduled[$survey_id][$event_id]) && $attr['reeval_before_send'] == '0')
                    // OR if the schedule is set as Inactive
                    || !$attr['active']
                    // OR if record doesn't exist in this event's arm
                    || isset($ignoreEvents[$event_id])
                ) {
                    unset($availableSchedules[$survey_id][$event_id]);
                    // If event_id sub-array is not empty them remove it
                    if (empty($availableSchedules[$survey_id][$event_id])) unset($availableSchedules[$survey_id][$event_id]);
                    // If survey_id sub-array is not empty them remove it
                    if (empty($availableSchedules[$survey_id])) unset($availableSchedules[$survey_id]);
                }
                // If it's dependent upon another survey being completed, then check if participant has completed it. If so, then we can remove it
                elseif (is_numeric($attr['condition_surveycomplete_survey_id']) && is_numeric($attr['condition_surveycomplete_event_id'])
                    // Check if they've completed this survey
                    && (isset($alreadyCompleted[$attr['condition_surveycomplete_survey_id']][$attr['condition_surveycomplete_event_id']])
                        || Survey::isResponseCompleted($attr['condition_surveycomplete_survey_id'], $record, $attr['condition_surveycomplete_event_id'], $instanceNum)
                        || self::isFormStatusCompleted($attr['condition_surveycomplete_survey_id'], $attr['condition_surveycomplete_event_id'], $record, $instanceNum)
                    )
                ) {
                    unset($availableSchedules[$attr['condition_surveycomplete_survey_id']][$attr['condition_surveycomplete_event_id']]);
                    // If event_id sub-array is not empty them remove it
                    if (empty($availableSchedules[$attr['condition_surveycomplete_survey_id']][$attr['condition_surveycomplete_event_id']])) unset($availableSchedules[$attr['condition_surveycomplete_survey_id']][$attr['condition_surveycomplete_event_id']]);
                    // If survey_id sub-array is now empty them remove it
                    if (empty($availableSchedules[$attr['condition_surveycomplete_survey_id']])) unset($availableSchedules[$attr['condition_surveycomplete_survey_id']]);
                }
			}
		}

		// Now remove all schedules that are dependent upon other completed surveys in this schedule
		// and put them in dependentAvailableSchedules array (e.g., if Week 2 requires that Week 1 be finished first).
		$dependentAvailableSchedules = array();
		do {
			// Initial value
			$removedSchedule = false;
			// Loop through all available schedules and remove those that are dependent upon other available ones (cascading issue)
            foreach ($availableSchedules as $survey_id=>$events) {
                foreach ($events as $event_id=>$attr) {
                    // If schedule is dependent upon an available schedule OR is dependent upon another dependent schedule, then remove, unless there is OR'ed logic.
                    $has_available_schedule = isset($availableSchedules[$attr['condition_surveycomplete_survey_id']][$attr['condition_surveycomplete_event_id']]);
                    $has_dependent_schedule = isset($dependentAvailableSchedules[$attr['condition_surveycomplete_survey_id']][$attr['condition_surveycomplete_event_id']]);
                    $has_or_condition = $attr["condition_andor"] == "OR" && !empty($attr["condition_logic"]);
                    if (!$has_or_condition && ($has_available_schedule || $has_dependent_schedule)) {
                        // Set flag so that we'll know to loop over this whole survey again
                        $removedSchedule = true;
                        unset($availableSchedules[$survey_id][$event_id]);
                        // Add schedule that was removed to the dependentAvailableSchedules array
                        $dependentAvailableSchedules[$survey_id][$event_id] = $attr;
                    }
                }
				// If survey_id sub-array is now empty them remove it
				if (empty($availableSchedules[$survey_id])) unset($availableSchedules[$survey_id]);
			}
		} while ($removedSchedule);

		// Return array of available schedules
		return $availableSchedules;
	}


	// Calculate the date/time when the survey invitation should be send to this participant
	private function calculateParticipantInvitationTime($survey_id, $event_id, $record, $instanceNum=1, $lastInstanceNum=1)
	{
		// Get this schedule's attributes
		$attr = $this->schedules[$survey_id][$event_id];

		// SEND AT EXACT TIME
		if ($attr['condition_send_time_option'] == 'EXACT_TIME')
		{
			// Set invitation time as the "exact date/time" specified
			$invitationTime = $attr['condition_send_time_exact'];
		}

		// IMMEDIATELY SEND
		elseif ($attr['condition_send_time_option'] == 'IMMEDIATELY')
		{
			// Set invitation time as current time right now
			$invitationTime = NOW;
		}

		// SEND AFTER SPECIFIED LAPSE OF TIME
		elseif ($attr['condition_send_time_option'] == 'TIME_LAG')
		{
			// Get temporal components
			$days = $attr['condition_send_time_lag_days'];
			$hours = $attr['condition_send_time_lag_hours'];
			$minutes = $attr['condition_send_time_lag_minutes'];
			if ($days == '') $days = 0;
			if ($hours == '') $hours = 0;
			if ($minutes == '') $minutes = 0;
			// If using datetime field for time lag, get the field and its value
			$dataField = $attr['condition_send_time_lag_field'];
			$beforeAfter = $attr['condition_send_time_lag_field_after'];
            // Is this a same-day-at-midnight invitation time?
            $sameDayMidnight = ($dataField != '' && strpos($dataField, "[survey-date-completed:") === 0);
            // Is this based off a date or date/time field?
			if ($dataField != '' && !$sameDayMidnight) {
				// Format the field logic to prep for piping
				$Proj = new Project($this->project_id);
				if ($Proj->longitudinal) $dataField = LogicTester::logicPrependEventName($dataField, 'event-name', $Proj);
				$dataField = LogicTester::logicAppendCurrentInstance($dataField, $Proj, $event_id);
				$instrument = $Proj->surveys[$survey_id]['form_name'];
				$dataValue = trim(Piping::replaceVariablesInLabel($dataField, $record, $event_id, $lastInstanceNum, array(), false, $this->project_id, false,
                                    $instrument, 1, false, false, $instrument, null, true, false, false,
                                    false, true));
                // Make sure the date value is not a missing data code
				$missingDataCodes = parseEnum($Proj->project['missing_data_codes']);
				$dataIsMissingCode = (!empty($missingDataCodes) && in_array($dataValue, $missingDataCodes));
				// Don't schedule this invitation if we don't have a valid value
				if ($dataValue == '' || $dataIsMissingCode) return false;
				// If timing is set to send "before" the value of this field, change all the numbers to negative
				if ($beforeAfter == 'before') {
					$days = -1*$days;
					$hours = -1*$hours;
					$minutes = -1*$minutes;
				}
				// Calculate invitation time from field value
				$invitationTime = date("Y-m-d H:i:s", strtotime($dataValue) + ($days*86400) + ($hours*3600) + ($minutes*60));
			}
            // Calculate invitation time by adding time lag to current time or current date
            elseif ($sameDayMidnight) {
                // Same day at midnight
                $invitationTime = date_mktime("Y-m-d H:i:s", $hours, $minutes, 0, date("m"), date("d")+$days, date("Y"));
            } else {
                // When ASI is triggered
                $invitationTime = date_mktime("Y-m-d H:i:s", date("H")+$hours,date("i")+$minutes,date("s"),date("m"),date("d")+$days,date("Y"));
            }
		}

		// SEND ON NEXT SPECIFIED DAY/TIME
		elseif ($attr['condition_send_time_option'] == 'NEXT_OCCURRENCE')
		{
			// Set time component of the timestamp
			$timeTS = $attr['condition_send_next_time'];
			// Set the date component of the timestamp
			// If day type is "WEEKEND DAY"
			if ($attr['condition_send_next_day_type'] == 'WEEKENDDAY') {
				// If today is Saturday, then next weekend day = next Sunday (i.e. tomorrow)
				if (date('D') == 'Sat') {
					$dateTS = date('Y-m-d', strtotime('NEXT SUNDAY'));
				}
				// If today is any day other than Saturday, then next weekend day is next Saturday
				else {
					$dateTS = date('Y-m-d', strtotime('NEXT SATURDAY'));
				}
			}
			// Any other day type (can use strtotime to parse into date)
			else {
				$dateTS = date('Y-m-d', strtotime('NEXT '.$attr['condition_send_next_day_type']));
			}
			// Combine date and time components
			$invitationTime = "$dateTS $timeTS";
		}

		// Validate the date/time with regex (in case components are missing or are calculated incorrectly)
		$datetime_regex = '/^(\d{4})([-\/.])?(0[1-9]|1[012])\2?(0[1-9]|[12][0-9]|3[01])\s([0-9]|[0-1][0-9]|[2][0-3]):([0-5][0-9]):([0-5][0-9])$/';
		if (!preg_match($datetime_regex, $invitationTime)) $invitationTime = false;

		// Return invitation date/time
		return $invitationTime;
	}


	// SURVEY REMINDERS: Calculate the date/time when the survey reminder should be send to this participant
	// $baseInvitationTime represents the time in which this reminder is being added to (e.g. 4 hours after $baseInvitationTime)
	public static function calculateReminderTime($attr, $baseInvitationTime)
	{
		// Set strtotime version of base invite time
		$strtotimeBaseInvitationTime = strtotime($baseInvitationTime);

		// SEND AT EXACT TIME
		if ($attr['reminder_type'] == 'EXACT_TIME')
		{
			// Set invitation time as the "exact date/time" specified
			$invitationTime = $attr['reminder_exact_time'];
		}

		// SEND AFTER SPECIFIED LAPSE OF TIME
		elseif ($attr['reminder_type'] == 'TIME_LAG')
		{
			// Get temporal components
			$days = (!is_numeric($attr['reminder_timelag_days']) ? 0 : $attr['reminder_timelag_days']);
			$hours = (!is_numeric($attr['reminder_timelag_hours']) ? 0 : $attr['reminder_timelag_hours']);
			$minutes = (!is_numeric($attr['reminder_timelag_minutes']) ? 0 : $attr['reminder_timelag_minutes']);
			// Calculate invitation time by adding time lag to current time
			$invitationTime = 	date_mktime("Y-m-d H:i:s", 
									date("H", $strtotimeBaseInvitationTime)+$hours,
									date("i", $strtotimeBaseInvitationTime)+$minutes,
									date("s", $strtotimeBaseInvitationTime),
									date("m", $strtotimeBaseInvitationTime),
									date("d", $strtotimeBaseInvitationTime)+$days,
									date("Y", $strtotimeBaseInvitationTime)
								);
		}

		// SEND ON NEXT SPECIFIED DAY/TIME
		elseif ($attr['reminder_type'] == 'NEXT_OCCURRENCE')
		{
			// Set time component of the timestamp
			$timeTS = $attr['reminder_nexttime'].":00";
			$timeTS = substr($timeTS, 0, 8);
			// Set the date component of the timestamp
			// If day type is "WEEKEND DAY"
			if ($attr['reminder_nextday_type'] == 'WEEKENDDAY') {
				// If today is Saturday, then next weekend day = next Sunday (i.e. tomorrow)
				if (date('D', $strtotimeBaseInvitationTime) == 'Sat') {
					$dateTS = date('Y-m-d', strtotime('NEXT SUNDAY', $strtotimeBaseInvitationTime));
				}
				// If today is any day other than Saturday, then next weekend day is next Saturday
				else {
					$dateTS = date('Y-m-d', strtotime('NEXT SATURDAY', $strtotimeBaseInvitationTime));
				}
			}
			// Any other day type (can use strtotime to parse into date)
			else {
				$dateTS = date('Y-m-d', strtotime('NEXT '.$attr['reminder_nextday_type'], $strtotimeBaseInvitationTime));
			}
			// Combine date and time components
			$invitationTime = "$dateTS $timeTS";
		}

		// Validate the date/time with regex (in case components are missing or are calculated incorrectly)
		$datetime_regex = '/^(\d{4})([-\/.])?(0[1-9]|1[012])\2?(0[1-9]|[12][0-9]|3[01])\s([0-9]|[0-1][0-9]|[2][0-3]):([0-5][0-9]):([0-5][0-9])$/';
		if (!preg_match($datetime_regex, $invitationTime)) $invitationTime = false;

		// Return invitation date/time
		return $invitationTime;
	}


	// Schedule the participant's survey invitation to be sent by adding it to the scheduler_queue table. Return boolean
	public function scheduleParticipantInvitation($survey_id, $event_id, $record, $instanceNum=1, $isRecurring=false, $addRecurrenceSchedule=false, $preventTriggerEmailInviteMethod=false, $is_dry_run=false)
	{
		// In case we somehow got here mistakenly, if this record has already been scheduled for this survey/event, then nothing to do here.
        $addRecurrenceScheduleOnly = false;
        if (!$isRecurring) {
            $sql = "select 1 from redcap_surveys_scheduler_queue q, redcap_surveys_scheduler s 
                    where s.ss_id = q.ss_id and q.record = '" . db_escape($record) . "'
                    and s.survey_id = $survey_id and s.event_id = $event_id 
                    limit 1";
            $q = db_query($sql);
            if (db_num_rows($q) > 0) {
                if ($addRecurrenceSchedule) {
                    // Set flag to use below
                    $addRecurrenceScheduleOnly = true;
                } else {
                    // Nothing to do here
                    return false;
                }
            }
        }

		// Get Proj object
        if (isinteger($this->project_id)) {
			$Proj = new Project($this->project_id);
		} else {
            $Proj = new Project(PROJECT_ID);
        }

		// If longitudinal, ensure that this survey is still designated for this event
		if ($Proj->longitudinal && !isset($Proj->eventsForms[$event_id])) return false;
		if ($Proj->longitudinal && isset($Proj->surveys[$survey_id]) && !in_array($Proj->surveys[$survey_id]['form_name'], $Proj->eventsForms[$event_id])) {
			return false;
		}

		// Fill up $schedules array with schedules
		$this->setSchedules();

		// Get the schedule for this survey/event
		$thisSchedule = $this->schedules[$survey_id][$event_id];

		// Capture all queries in array for logging purposes
		$sql_all = array();

        // Get highest instance number for record/survey/event
        $lastInstanceNum = 1;

		// First, make sure that there is a placeholder set in the participants table for this record-survey-event
		list ($participant_id, $hash) = Survey::getFollowupSurveyParticipantIdHash($survey_id, $record, $event_id, false, $instanceNum);

		// Calculate the date/time when the survey invitation should be sent to this participant
        if ($isRecurring) {
            // If this is a recurring ASI, then we're always sending it Immediately/Now
            $invitationTime = NOW;
        } else {
            // Calculate send-time based on ASI settings
            $invitationTime = $this->calculateParticipantInvitationTime($survey_id, $event_id, $record, $instanceNum, $lastInstanceNum);
            if ($invitationTime === false) return false;
        }

		## PIPING: Obtain email subject/content to see if piping should be performed
		$sql = "select email_subject, email_content, email_sender, email_sender_display, delivery_type 
                from redcap_surveys_scheduler
				where survey_id = $survey_id and event_id = $event_id";
		$q = db_query($sql);
		$row = db_fetch_assoc($q);
		// MLM - translate sender display name, subject, and content
		$context = Context::Builder()
			->project_id($Proj->project_id)
			->is_survey()
			->survey_id($survey_id)
			->event_id($event_id)
			->instance($instanceNum)
			->instrument($Proj->surveys[$survey_id]["form_name"])
			->record($record)
			->Build();
		$lang_id = MultiLanguage::translateASIAttributes($context, $row);
		// Perform filtering on email subject/content
		$row['email_sender']  = label_decode($row['email_sender']);
		$row['email_sender_display'] = decode_filter_tags($row['email_sender_display']);
		$row['email_content'] = decode_filter_tags($row['email_content']);
		$row['email_subject'] = decode_filter_tags($row['email_subject']);
		// If delivery type if partipant's preference, then determine their preference and set it
		$delivery_type_orig = $row['delivery_type'];
		if ($row['delivery_type'] == 'PARTICIPANT_PREF') {
			// Get delivery method
			$participantAttributes = Survey::getResponsesEmailsIdentifiers(array($record), $survey_id, $this->project_id);
			$row['delivery_type'] = isset($participantAttributes[$record]) ? $participantAttributes[$record]['delivery_preference'] : 'EMAIL';
		}
		// Set flags
		$doPiping = $doPipingContent = $doPipingSubject = false;
		$piping_fields = array();
		// EMAIL CONTENT PIPING
		if (strpos($row['email_content'], '[') !== false && strpos($row['email_content'], ']') !== false) {
			// Parse the label to pull out the field names
			$piping_fields_content = array_keys(getBracketedFields($row['email_content'], true, true, true));
			// Validate the field names
			foreach ($piping_fields_content as $key=>$this_field) {
				// If not a valid field name, then remove
				if (!isset($Proj->metadata[$this_field])) unset($piping_fields_content[$key]);
			}
			// Set flag to true if some fields were indeed piped
			if (!empty($piping_fields_content)) {
				$doPiping = $doPipingContent = true;
			}
			if (!$doPipingContent && Piping::containsSpecialTags($row['email_content'])) {
				$doPiping = $doPipingContent = true;
			}
		}
		// EMAIL SUBJECT PIPING
		if (strpos($row['email_subject'], '[') !== false && strpos($row['email_subject'], ']') !== false) {
			// Parse the label to pull out the field names
			$piping_fields_subject = array_keys(getBracketedFields($row['email_subject'], true, true, true));
			// Validate the field names
			foreach ($piping_fields_subject as $key=>$this_field) {
				// If not a valid field name, then remove
				if (!isset($Proj->metadata[$this_field])) unset($piping_fields_subject[$key]);
			}
			// Set flag to true if some fields were indeed piped
			if (!empty($piping_fields_subject)) {
				$doPiping = $doPipingSubject = true;
			}
			if (!$doPipingSubject && Piping::containsSpecialTags($row['email_subject'])) {
				$doPiping = $doPipingSubject = true;
			}
		}
		$containsIdentifierFields = $doPiping ? containsIdentifierFields($row['email_content'], $this->project_id) : false;
		// Set subject
		$row['email_subject'] = ($doPipingSubject) ? strip_tags(Piping::replaceVariablesInLabel($row['email_subject'], $record, $event_id, $instanceNum, array(), true, $this->project_id, true, "", 1, false, false, $Proj->surveys[$survey_id]['form_name'], $participant_id, false, false, false, false, false, $lang_id)) : $row['email_subject'];
		// Set content
		$row['email_content'] = ($doPipingContent) ? Piping::replaceVariablesInLabel($row['email_content'], $record, $event_id, $instanceNum, array(), true, $this->project_id, false, "", 1, false, false, $Proj->surveys[$survey_id]['form_name'], $participant_id, false, false, true, false, false, $lang_id) : $row['email_content'];
		## REMINDERS
		$participantSendTimes = array(0=>$invitationTime);
		## If reminders are enabled, then add times of all reminders in array
		$addReminders = ($thisSchedule['reminder_type'] != '');
		if ($addReminders) {
			// Loop through each reminder
			$thisReminderTime = $invitationTime;
			for ($k = 1; $k <= $thisSchedule['reminder_num']; $k++) {
				// Get reminder time for next reminder
				$participantSendTimes[$k] = $thisReminderTime = SurveyScheduler::calculateReminderTime($thisSchedule, $thisReminderTime);
			}
		}

		## SCHEDULE THE INVITATIONS AND REMINDERS
		// Keep track of any errors
		$insertErrors = 0;
		// Add to surveys_emails table
        if (!$addRecurrenceScheduleOnly)
        {
            $sql_all[] = $sql = "insert into redcap_surveys_emails (survey_id, email_subject, email_content, email_static, email_sender_display, append_survey_link, lang_id) values
                                ($survey_id, '" . db_escape($row['email_subject']) . "', '" . db_escape($row['email_content']) . "', 
                                '" . db_escape($row['email_sender']) . "', " . checkNull($row['email_sender_display']) . ", '" . Survey::getAppendSurveyLink($delivery_type_orig) . "', " . checkNull($lang_id) . ")";
            if (db_query($sql)) {
                // Get email_id
                $email_id = db_insert_id();
                // Now add to surveys_emails_recipients table
                $sql_all[] = $sql = "insert into redcap_surveys_emails_recipients (email_id, participant_id, delivery_type)
                                     values ($email_id, $participant_id, '" . db_escape($row['delivery_type']) . "')";
                if (db_query($sql)) {
                    // Get email_recip_id
                    $email_recip_id = db_insert_id();
                    // Now add to scheduler_queue table (loop through orig invite + any reminder invites)
                    foreach ($participantSendTimes as $reminder_num => $thisSendTime) {
                        $sql_all[] = $sql = "insert into redcap_surveys_scheduler_queue (ss_id, email_recip_id, record, instance, scheduled_time_to_send, reminder_num)
                                            (select ss_id, '$email_recip_id', '" . db_escape($record) . "', '$instanceNum', '$thisSendTime', '" . db_escape($reminder_num) . "'
                                            from redcap_surveys_scheduler where survey_id = $survey_id and event_id = $event_id)
                                            on duplicate key update scheduled_time_to_send = '$thisSendTime'";
                        if (!db_query($sql)) $insertErrors++;
                        // Get ssq_id from insert
                        $ssq_id = db_insert_id();
                        // If email content/subject contains any identifier fields, then note this in a separate table
                        if ($containsIdentifierFields) {
                            db_query("insert into redcap_outgoing_email_sms_identifiers (ssq_id) values ($ssq_id)");
                        }
                        // If need to send the invite right now, then send it here NOW (except for Data Import Tool and API data import, which use transactions)
                        $pagesExcludeSendNow = array("DataImportController:index", "api/index.php", "API/index.php");
                        if ($thisSendTime == NOW && !in_array(PAGE, $pagesExcludeSendNow) && !defined("CRON") && !$preventTriggerEmailInviteMethod) {
                            if (!$is_dry_run) {
                                self::emailInvitations($ssq_id);
                            }
                        }
                    }
                }
            }
        }

		if ($insertErrors == 0)
		{
            // Is this an ASI that is seeding a recurring ASI (i.e., num_recurrence > 0)? If so, then also add to recurrence table.
            if (!$isRecurring) {
                $sql = "select ss_id from redcap_surveys_scheduler 
                        where survey_id = $survey_id and event_id = $event_id and num_recurrence > 0";
                $q = db_query($sql);
                $ss_id = ($q && db_num_rows($q) > 0) ? db_result($q, 0) : null;
                if (isinteger($ss_id)) {
                    // Check if exists first
                    $sql = "select 1 from redcap_surveys_scheduler_recurrence where ss_id = $ss_id and record = '" . db_escape($record) . "' and event_id = $event_id
                            and instrument = '" . db_escape($Proj->surveys[$survey_id]['form_name']) . "'";
                    $q = db_query($sql);
                    if (!db_num_rows($q)) {
                        // Add recurrence to table
                        $lastSent = ($invitationTime == NOW) ? NOW : "";
                        $sql_all[] = $sql = "insert into redcap_surveys_scheduler_recurrence (ss_id, creation_date, record, event_id, instrument, times_sent, first_send_time, last_sent)
                                             values ($ss_id, '" . NOW . "', '" . db_escape($record) . "', $event_id, '" . db_escape($Proj->surveys[$survey_id]['form_name']) . "', 1, '" . db_escape($invitationTime) . "', " . checkNull($lastSent) . ")";
                        $q = db_query($sql);
                    } elseif ($addRecurrenceScheduleOnly) {
                        // If already exists in table AND we didn't add any schedule invitations, then return false since nothing was done
                        return false;
                    }
                }
            }

            // Log this event, if scheduled
            if (!$is_dry_run) {
                Logging::logEvent(implode(";\n", $sql_all),"redcap_surveys_scheduler_queue","MANAGE",$record,
                    "survey_id = $survey_id,\nevent_id = $event_id,\nrecord = '$record',\ninstance = $instanceNum,\nssq_id = $ssq_id,\nemail_recip_id = $email_recip_id",
                    "Automatically schedule survey invitation", "", "SYSTEM", $this->project_id);
            }
		}

		// Return true if was scheduled successfully
		return ($insertErrors == 0);
	}

	// MAILER: Send one batch of invitations (limit based on determineEmailsPerBatch())
	// or send single invitation if ssq_id is provided.
	// Ignore all inactive, archived, and "deleted" projects.
	public static function emailInvitations($ssq_id=null) {

		global $lang, $enable_url_shortener;
		// Count of emails sent successfully and failed
		$emailCountSuccess = 0;
		$emailCountFail    = 0;


		## SEND INVITATIONS FOR RECURRING ASI'S (don't run the repeating ASI sender when we're sending emails for a single invite - i.e, when ssq_id != null)
		if ($ssq_id == null) {
			// If any invites have been stuck in SENDING status for more than one hour (which means they likely won't ever send), then set them back to IDLE.
			$oneHourAgo = date("Y-m-d H:i:s", mktime(date("H") - 1, date("i"), date("s"), date("m"), date("d"), date("Y")));
			$sql = 
			   "UPDATE redcap_surveys_scheduler_recurrence 
				SET `status` = 'IDLE', 
					next_send_time = NULL 
				WHERE next_send_time IS NOT NULL 
					AND next_send_time <= '$oneHourAgo' 
					AND `status` = 'SENDING'";
			db_query($sql);
			// Set invites with SENDING status if they should be sent right now
			$ssr_ids = array();
			$sql = 
			   "SELECT DISTINCT r.ssr_id 
				FROM redcap_surveys_scheduler_recurrence r, redcap_surveys_scheduler a, redcap_surveys s, redcap_projects p, redcap_events_repeat e
				WHERE r.ss_id = a.ss_id 
					AND a.survey_id = s.survey_id 
					AND s.project_id = p.project_id
					AND e.event_id = a.event_id 
					AND (e.form_name IS NULL OR e.form_name = s.form_name)
					AND a.active = 1 
					AND p.status <= 1 
					AND p.date_deleted IS NULL 
					AND p.completed_time IS NULL 
					AND r.status = 'IDLE'
					AND (a.num_recurrence > 0 AND (a.max_recurrence IS NULL OR r.times_sent < a.max_recurrence))
					AND DATE_ADD(r.first_send_time, INTERVAL ROUND(r.times_sent * a.num_recurrence * (IF(a.units_recurrence = 'DAYS', 1440, IF(a.units_recurrence = 'HOURS', 60, 1)))) MINUTE) <= '" . NOW . "'
					LIMIT " . SurveyScheduler::determineEmailsPerBatch();
			$q = db_query($sql);
			if (db_num_rows($q) > 0) {
				## Get all ssr_id's and put in array
				while ($row = db_fetch_assoc($q)) {
					// Set the ssr_id's status as SENDING
					// (Don't change status unless still IDLE in case other simultaneous cron isn't lagging behind with the SELECT query above)
					$sql = 
					   "UPDATE redcap_surveys_scheduler_recurrence r, redcap_surveys_scheduler a 
						SET r.status = 'SENDING', 
							r.next_send_time = DATE_ADD(r.first_send_time, INTERVAL ROUND(r.times_sent * a.num_recurrence * (IF(a.units_recurrence = 'DAYS', 1440, IF(a.units_recurrence = 'HOURS', 60, 1)))) MINUTE) 
						WHERE r.ss_id = a.ss_id 
							AND r.ssr_id = {$row['ssr_id']} 
							AND r.status = 'IDLE'";
					db_query($sql);
					// If already set as SENDING, then skip it here because another cron must've picked it up
					if (db_affected_rows() == 0) continue;
					// Add ssq_id's to array
					$ssr_ids[] = $row['ssr_id'];
				}
			}
			// SEND RECURRING INVITATIONS
			if (!empty($ssr_ids)) {
				// Now loop though all ssr_id's with status of SENDING and send invitation for each
				$sql = "SELECT a.ss_id, r.ssr_id, a.survey_id, a.event_id, r.record, s.project_id, 
							IF (a.reeval_before_send='1' AND a.condition_logic != '', 1, 0) AS ensure_logic_still_true
						FROM redcap_surveys_scheduler_recurrence r, redcap_surveys_scheduler a, redcap_surveys s
						WHERE r.ss_id = a.ss_id 
							AND a.survey_id = s.survey_id 
							AND r.next_send_time IS NOT NULL 
							AND r.ssr_id IN (" . prep_implode($ssr_ids) . ")
						ORDER BY r.next_send_time";
				$q = db_query($sql);
				// Loop through all invitations to be sent and then send them
				while ($row = db_fetch_assoc($q)) {
					// Instantiate object
					$ss_obj = new SurveyScheduler($row['project_id']);
					// Get the next instance number to be used for this invitation for this record/survey/event
					$sql = 
					   "SELECT IFNULL(MAX(q.instance),0)+1 as nextinstance
						FROM redcap_surveys_scheduler_recurrence r, redcap_surveys_scheduler_queue q
						WHERE r.ss_id = {$row['ss_id']} 
							AND r.ss_id = q.ss_id 
							AND q.record = '" . db_escape($row['record']) . "'";
					$q2 = db_query($sql);
					$instanceNum = ($q2 && db_num_rows($q2) > 0) ? db_result($q2, 0) : 1;
					// Has the repeating survey already been completed? If so, don't send an invitation.
					if (Survey::isResponseCompleted($row['survey_id'], $row['record'], $row['event_id'], $instanceNum)) {
						// We need to add this to the queue table with DELETED status to create a placeholder for future recurrences to skip over when calculating the next instance (otherwise we get stuck here next time)
						$sqli = 
						   "INSERT INTO redcap_surveys_scheduler_queue (ss_id, record, instance, `status`) 
							VALUES ({$row['ss_id']}, '" . db_escape($row['record']) . "', '$instanceNum', 'DELETED')";
						db_query($sqli);
						// Increment the times_sent so that it moves on to the next instance next time
						$sql = 
						   "UPDATE redcap_surveys_scheduler_recurrence
							SET `status` = 'IDLE', 
								times_sent = times_sent+1
							WHERE ssr_id = " . $row['ssr_id'];
					} else {
						// If ASI has conditional logic and "Ensure logic is still true" is checked, then check logic
						$conditionsPassedLogic = true; // default
						if ($row['ensure_logic_still_true']) {
							$conditionsPassedLogic = $ss_obj->checkConditionsOfRecordToSchedule($row['survey_id'], $row['event_id'], $row['record'], $instanceNum);
						}
						// Send it now
						$wasSent = false; // default
						if ($conditionsPassedLogic) {
							$wasSent = $ss_obj->scheduleParticipantInvitation($row['survey_id'], $row['event_id'], $row['record'], $instanceNum, true, false, true); // Make sure we don't recursively trigger emailInvitations()
						}
						// Reset the values for the next recurrence
						if ($wasSent) {
							$sql = 
							   "UPDATE redcap_surveys_scheduler_recurrence
								SET `status` = 'IDLE', 
									times_sent = times_sent+1, last_sent = '" . NOW . "'
								WHERE ssr_id = " . $row['ssr_id'];
						} else {
							$sql = 
							   "UPDATE redcap_surveys_scheduler_recurrence 
								SET `status` = 'IDLE' 
								WHERE ssr_id = " . $row['ssr_id'];
						}
					}
					// Set recurrence back to IDLE status
					db_query($sql);
				}
			}
		}

		## SEND NORMAL INVITATIONS
		// First, get ssq_id of all records for this batch to have invitations scheduled
		$ssq_ids = array();
		// Collect languages for each project in case one is not in English so we can display email text in that language
		$language_by_ssq_id = $project_id_by_ssq_id = array();
		// Add ssq_id's to arrays while looping
		$ssq_ids_emails = $ssq_ids_voice_sms = $static_emails = $static_phones = array();
		// If single ssq_id is provided, then limit query only to this one
		$sqlsub   = (is_numeric($ssq_id)) ? "and ssq_id = $ssq_id" : "";
		$sqllimit = (is_numeric($ssq_id)) ? "1" : self::determineEmailsPerBatch();
		$sql = "SELECT a.project_id, q.record, ss.ss_id, ss.condition_logic, ss.reeval_before_send,
                    q.ssq_id, a.project_language, r.static_email, r.static_phone, q.email_recip_id, p.participant_id, 
                    s.survey_time_limit_days, s.survey_time_limit_hours, s.survey_time_limit_minutes, q.reminder_num,
                    p.event_id, s.form_name, q.instance, s.survey_id
				FROM redcap_surveys_emails_recipients r, redcap_surveys_participants p, redcap_surveys s,
				  redcap_projects a, redcap_surveys_scheduler_queue q
				LEFT JOIN redcap_surveys_scheduler ss ON q.ss_id = ss.ss_id
				WHERE q.time_sent IS NULL AND q.email_recip_id = r.email_recip_id AND p.survey_id = s.survey_id
                    AND r.participant_id = p.participant_id AND s.project_id = a.project_id AND a.date_deleted IS NULL AND a.completed_time IS NULL
                    AND q.scheduled_time_to_send <= '" . NOW . "' AND a.status <= 1 AND a.online_offline = 1 AND q.status = 'QUEUED' $sqlsub
				ORDER BY q.scheduled_time_to_send, q.ssq_id
				LIMIT $sqllimit";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			## Get all ssq_id's and put in array
			while ($row = db_fetch_assoc($q))
			{
				// Set the ssq_id's status as SENDING
				// (Don't change status unless still QUEUED in case other simultaneous cron isn't lagging behind with the SELECT query above)
				$sql = 
				   "UPDATE redcap_surveys_scheduler_queue 
					SET `status` = 'SENDING'
					WHERE ssq_id = {$row['ssq_id']} AND `status` = 'QUEUED'";
				db_query($sql);
				// If already set as SENDING, then skip it here because another cron must've picked it up
				if (db_affected_rows() == 0) continue;
                // Check if form still exists in the project. If not, set as DID NOT SEND.
                if ($row['form_name'] != '') {
                    $sqlf = "select 1 from redcap_metadata where project_id = ? and form_name = ? limit 1";
                    $qf = db_query($sqlf, [$row['project_id'], $row['form_name']]);
                    $formExists = (db_num_rows($qf) > 0);
                    if (!$formExists) {
                        // Mark invitations as DID NOT SEND since this instrument no longer exists
                        $sql = "UPDATE redcap_surveys_scheduler_queue SET `status` = 'DID NOT SEND' WHERE ssq_id = ?";
                        db_query($sql, $row['ssq_id']);
                    } else {
	                    // If longitudinal, ensure that this survey is still designated for this event
	                    $sqlf2 = "select 1 from redcap_events_arms a, redcap_events_metadata e, redcap_projects p
           						where p.project_id = ? and a.project_id = p.project_id and p.repeatforms = 1
								and a.arm_id = e.arm_id limit 2";
	                    $qf2 = db_query($sqlf2, $row['project_id']);
	                    $isLongitudinal = (db_num_rows($qf2) > 1); // If more than one event exists, then the project is longitudinal
						if ($isLongitudinal) {
							$sqlf2 = "select 1 from redcap_events_forms where event_id = ? and form_name = ?";
							$qf2 = db_query($sqlf2, [$row['event_id'], $row['form_name']]);
							$formNotDesignated = (db_num_rows($qf2) == 0);
							if ($formNotDesignated) {
								// Mark invitations as DID NOT SEND since this instrument is no longer designated for this event
								$sql = "UPDATE redcap_surveys_scheduler_queue SET `status` = 'DID NOT SEND' WHERE ssq_id = ?";
								db_query($sql, $row['ssq_id']);
							}
						}
                    }
                }
				// If "re-eval before send" is enabled, then re-check logic
				if ($row['reeval_before_send'] == '1' && $row['condition_logic'] != '') {
					// Not only do we need to check the logic, but we also might need to check survey completion status (depending on the ASI trigger conditions)
					$ss_obj = new SurveyScheduler($row['project_id']);
					$conditionsPassedLogic = $ss_obj->checkConditionsOfRecordToSchedule($row['survey_id'], $row['event_id'], $row['record']);
					if (!$conditionsPassedLogic) {
						// Conditions are no longer true, so delete this invitation and begin next loop.
						// Also delete any reminder invitations that have not been sent yet.
						$sql = 
						   "SELECT q.ssq_id, q.record, s.survey_id, s.event_id, p.project_id, q.reminder_num
							FROM redcap_surveys_scheduler_queue q, redcap_surveys_scheduler s, redcap_surveys p
							WHERE p.survey_id = s.survey_id 
								AND q.ss_id = s.ss_id 
								AND s.ss_id = {$row['ss_id']}
								AND q.email_recip_id = {$row['email_recip_id']} 
								AND q.record = '" . db_escape($row['record']) . "'
								AND q.status IN ('QUEUED', 'SENDING', 'DELETED')";
						$q2 = db_query($sql);
						while ($row2 = db_fetch_assoc($q2)) 
						{
							$sql = "DELETE b.* FROM redcap_surveys_scheduler_queue b WHERE b.ssq_id = ".$row2['ssq_id'];
							db_query($sql);
							if (db_affected_rows() > 0) {
								// Log the deletion
								if ($row2['reminder_num'] == 0) {
									Logging::logEvent($sql,"redcap_surveys_scheduler_queue","MANAGE",$row2['record'],
										"survey_id = {$row2['survey_id']},\nevent_id = {$row2['event_id']},\nrecord = '{$row2['record']}',\nssq_id = {$row2['ssq_id']}",
										"Automatically remove scheduled survey invitation", "", "SYSTEM", $row2['project_id'], true, $row2['event_id']);
								}
							}
						}
						// Skip to next loop
						continue;
					}
				}

				// Reminders only: Calculate "Time Limit for Survey Completion" in seconds for this survey
				// so that we don't send reminders if a survey link has already expiration for the participant.
				if ($row['reminder_num'] > 0 && ($row['survey_time_limit_days'] != '' || $row['survey_time_limit_hours'] != '' || $row['survey_time_limit_minutes'] != '')
					&& !Survey::checkSurveyTimeLimit($row['participant_id'], $row['survey_time_limit_days'], $row['survey_time_limit_hours'], $row['survey_time_limit_minutes']))
				{
					// The link has expired already, so don't send this reminder AND also mark it as error so that we know why it didn't send
					$sql = 
					   "UPDATE redcap_surveys_scheduler_queue 
						SET `status` = 'DID NOT SEND', reason_not_sent = 'LINK HAD ALREADY EXPIRED' 
						WHERE ssq_id = " . $row['ssq_id'];
					db_query($sql);
					// Skip to next invitation
					continue;
				}

				// Add ssq_id's to array
				$ssq_ids[] = $row['ssq_id'];
				$language_by_ssq_id[$row['ssq_id']] = $row['project_language'];
				$project_id_by_ssq_id[$row['ssq_id']] = $row['project_id'];
				// Add phone number to arrays, if applicable
				if ($row['static_phone'] != '') {
					// SMS or VOICE number
					$ssq_ids_voice_sms[$row['ssq_id']] = $static_phones[$row['ssq_id']] = $row['static_phone'];
				}
				// Add email address to arrays, if applicable
				if ($row['static_email'] != '') {
					// EMAIL address
					$ssq_ids_emails[$row['ssq_id']] = $static_emails[$row['ssq_id']] = label_decode($row['static_email']);
				}
			}

			## GET EMAIL ADDRESSES AND PHONE (if we didn't already get them from the manual static values in emails_recipients table)
			// First, get any emails connected to a Participant List for all the records involved here.
			// The first part of union query gets emails for initial surveys (i.e. record is null, doesn't exist yet),
			// while second part of query gets emails of followup surveys (i.e. existing records).
			$sql = "
				(
					SELECT q.ssq_id, p.participant_email, p.participant_phone
					FROM redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients e, redcap_surveys_participants p
					WHERE q.record IS NULL 
						AND q.email_recip_id = e.email_recip_id 
						AND p.participant_id = e.participant_id
						AND p.participant_email IS NOT NULL
						AND q.ssq_id IN (" . prep_implode($ssq_ids) . ")
				) UNION (
					SELECT q.ssq_id, p2.participant_email, p2.participant_phone
					FROM redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients e, redcap_surveys_participants p,
					redcap_surveys s, redcap_surveys s2, redcap_surveys_participants p2, redcap_surveys_response r
					WHERE q.record IS NOT NULL 
						AND q.email_recip_id = e.email_recip_id 
						AND p.participant_email IS NOT NULL
						AND p.participant_id = e.participant_id 
						AND p.survey_id = s.survey_id 
						AND s.project_id = s2.project_id
						AND s2.survey_id = p2.survey_id 
						AND p2.participant_id = r.participant_id 
						AND p2.participant_email IS NOT NULL
						AND r.record = q.record 
						AND q.ssq_id IN (" . prep_implode($ssq_ids) . ")
				)";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				if ($row['participant_phone'] != '' && !isset($static_phones[$row['ssq_id']])) {
					// SMS or VOICE delivery
					$ssq_ids_voice_sms[$row['ssq_id']] = $row['participant_phone'];
				}
				if ($row['participant_email'] != '' && !isset($static_emails[$row['ssq_id']])) {
					// EMAIL delivery
					$ssq_ids_emails[$row['ssq_id']] = label_decode($row['participant_email']);
				}
			}
            // Get all data tables used by the projects in $project_id_by_ssq_id
            $dataTables = [];
            foreach ($project_id_by_ssq_id as $this_ssq_id=>$thispid) {
                $thistable = \Records::getDataTable($thispid);
                $dataTables[$thistable][] = $this_ssq_id;
            }
			// Secondly, if we are missing any emails that are NOT in a Participant List but project
			// uses special "participant email" data field, get value for that in redcap_data table.
            foreach ($dataTables as $thistable=>$these_ssq_ids) // Loop through all data tables for each project
            {
                $sql = "SELECT q.ssq_id, d.value, 
                        IF (d.field_name = s.email_participant_field, 'survey-email', 
                            IF (d.field_name = p2.survey_email_participant_field, 'project-email', 'phone')) AS value_type
                        FROM redcap_surveys_scheduler_queue q, redcap_surveys_emails_recipients r,
                        redcap_surveys_emails e, redcap_surveys_participants p, redcap_projects p2, redcap_surveys s,
                        $thistable d WHERE q.email_recip_id = r.email_recip_id AND e.email_id = r.email_id
                        AND p.participant_id = r.participant_id AND s.survey_id = e.survey_id
                        AND s.project_id = p2.project_id AND p2.project_id = d.project_id AND d.record = q.record
                        AND (
                            (d.field_name = s.email_participant_field AND s.email_participant_field IS NOT NULL
                                and s.email_participant_field != '') 
                            OR 
                            (d.field_name = p2.survey_email_participant_field AND p2.survey_email_participant_field IS NOT NULL
                                AND p2.survey_email_participant_field != '') 
                            OR 
                            (d.field_name = p2.survey_phone_participant_field AND p2.survey_phone_participant_field IS NOT NULL 
                                AND p2.survey_phone_participant_field != '')
                        )
                        AND q.ssq_id IN (" . prep_implode($these_ssq_ids) . ")
                        ORDER BY q.ssq_id, value_type";
                $q = db_query($sql);
                while ($row = db_fetch_assoc($q)) {
                    if ($row['value'] == '') continue;
                    if ($row['value_type'] == 'phone' && !isset($static_phones[$row['ssq_id']])) {
                        // SMS or VOICE number
                        $ssq_ids_voice_sms[$row['ssq_id']] = $row['value'];
                    } elseif (($row['value_type'] == 'project-email' || $row['value_type'] == 'survey-email') && !isset($static_emails[$row['ssq_id']])) {
                        // EMAIL address
                        $ssq_ids_emails[$row['ssq_id']] = label_decode($row['value']);
                    }
                }
            }

			## SEND EMAILS, VOICE CALLS, OR SMS TEXT MESSAGES
			// Initialize counter of number of invitations sent
			$numEmailsSent = 0;
			// Now loop though all ssq_id's with status of SENDING and send invitation for each
			$sql = "SELECT q.ss_id, q.ssq_id, q.reminder_num, e.email_id, e.email_subject, e.email_content, e.append_survey_link, e.lang_id, pr.project_id, 
					IF (e.email_static IS NULL, (SELECT IF (e.email_account=1, u.user_email, IF (e.email_account=2, u.user_email2, u.user_email3))
						FROM redcap_user_information u WHERE u.ui_id = e.email_sender), e.email_static) AS email_static, e.email_sender_display,
					p.hash, p.participant_id, s.title, s.survey_id, r.delivery_type, s2.delivery_type AS delivery_type_asi,
					pr.twilio_enabled, pr.twilio_account_sid, pr.twilio_auth_token, pr.twilio_from_number, pr.twilio_modules_enabled, 
       				pr.protected_email_mode, pr.protected_email_mode_trigger, q.record
					FROM redcap_surveys_emails_recipients r, redcap_surveys_emails e, redcap_surveys_participants p, 
					     redcap_surveys s, redcap_projects pr, redcap_surveys_scheduler_queue q 
					LEFT JOIN redcap_surveys_scheduler s2 ON s2.ss_id = q.ss_id
					WHERE q.email_recip_id = r.email_recip_id
					AND e.email_id = r.email_id AND p.participant_id = r.participant_id AND s.survey_id = e.survey_id
					AND pr.project_id = s.project_id AND q.ssq_id IN (" . prep_implode($ssq_ids) . ") AND q.status = 'SENDING'
					ORDER BY q.scheduled_time_to_send";
			$q = db_query($sql);
			// Enable invitation time sent tracker to count time it takes to send them all
			$mtime = explode(" ", microtime());
			$starttime = $mtime[1] + $mtime[0];
			// Set language
			$prev_language = 'English';
			// Loop through all invitations to be sent and then send them
			while ($row = db_fetch_assoc($q))
			{
				// Double check one last time that the invitation has not already been sent (just in case a lagging simultaneous cron just sent it).
				// If not in SENDING state, then skip this invitation and move to next loop.
				$sql = 
				   "SELECT 1 
					FROM redcap_surveys_scheduler_queue 
					WHERE ssq_id = {$row['ssq_id']} AND `status` = 'SENDING'";
				$q1 = db_query($sql);
				if (db_num_rows($q1) < 1) continue;

				// Set variables for this loop
				$invitationSent = false;
				$ssq_id = $row['ssq_id'];
				$email_id = $row['email_id'];
				$reminder_num = $row['reminder_num'];
				// Get the TO email of the participant
				$participantEmail = (isset($ssq_ids_emails[$ssq_id]) && isEmail($ssq_ids_emails[$ssq_id])) ? $ssq_ids_emails[$ssq_id] : null;
				// Get the phone number of the participant
				$participantPhone = (isset($ssq_ids_voice_sms[$ssq_id])) ? $ssq_ids_voice_sms[$ssq_id] : null;
				// Get this project's language
				$this_language = $language_by_ssq_id[$row['ssq_id']];
				// If project language is not the same as the previous loop's language, then get this project's language,
				// thus replacing $lang variable temporarily.
				if ($this_language != $prev_language) {
					$lang = Language::getLanguage($this_language);
				}
				// MLM: Set the context for this row if MLM lang is set
				$mlm_row_context = $row["lang_id"] == null ? null : Context::Builder()
					->project_id($row["project_id"])
					->is_survey()
					->survey_id($row["survey_id"])
					->record($row["record"])
					->lang_id($row["lang_id"])
					->Build();

				// Differentiate between ASI and manually sent invites
				$category = ($row['ss_id'] == '') ? 'SURVEY_INVITE_MANUAL' : 'SURVEY_INVITE_ASI';

				// Determine how to deliver it
				if ($row['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $row['delivery_type'] == 'SMS_INITIATE' || $row['delivery_type'] == 'SMS_INVITE_WEB'
					|| $row['delivery_type'] == 'VOICE_INITIATE' || $row['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL') {
					## TWILIO SMS/VOICE
					// Set Twilio values twilio_modules_enabled
					$twilio_enabled = ($row['twilio_enabled'] && ($row['twilio_modules_enabled'] == 'SURVEYS' || $row['twilio_modules_enabled'] == 'SURVEYS_ALERTS'));
					$twilio_from_number = $row['twilio_from_number'];
					// Determine SMS or Voice Call delivery method
					if (!($twilio_enabled && ($GLOBALS['twilio_enabled_global'] || $GLOBALS['mosio_enabled_global']))) {
						// TWILIO OR MOSIO IS DISABLED
						$sql = 
						   "UPDATE redcap_surveys_scheduler_queue 
							SET `status` = 'DID NOT SEND', reason_not_sent = 'VOICE/SMS SETTING DISABLED' 
							WHERE ssq_id = $ssq_id";
					} 
					elseif ($participantPhone == null) {
						// MISSING THE PHONE NUMBER
						$sql = 
						   "UPDATE redcap_surveys_scheduler_queue 
							SET `status` = 'DID NOT SEND', reason_not_sent = 'PHONE NUMBER NOT FOUND' 
							WHERE ssq_id = $ssq_id";
					} 
					elseif ($row['delivery_type'] == 'SMS_INVITE_MAKE_CALL' || $row['delivery_type'] == 'SMS_INITIATE' || $row['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL' || $row['delivery_type'] == 'SMS_INVITE_WEB') {
						## SMS DELIVERY
						// Get message to send
						$sms_message = $row['email_content'];
						// Get the survey access code for this survey link
						$survey_access_code = Survey::getAccessCode(Survey::getParticipantIdFromHash($row['hash']), false, false, true);
						// Change SMS message based on whether it is an SMS survey or voice survey with SMS invite
						if ($row['delivery_type'] == 'SMS_INVITE_MAKE_CALL') {
							// Send phone number + access code via SMS
							if ($sms_message != '') $sms_message .= " -- ";
							$msg_template = $mlm_row_context ? MultiLanguage::getUITranslation($mlm_row_context, "survey_1583") : $lang["survey_1583"]; //= To begin the phone survey, call {0:PhoneNumber}
							$sms_message .= RCView::interpolateLanguageString($msg_template, formatPhone($twilio_from_number));
							// Add phone number and access code to table
							TwilioRC::addSmsAccessCodeForPhoneNumber($participantPhone, $twilio_from_number, $survey_access_code, $project_id_by_ssq_id[$row['ssq_id']]);
						} 
						elseif ($row['delivery_type'] == 'SMS_INVITE_RECEIVE_CALL') {
							// Send access code via SMS for them to receive a call
							if ($sms_message != '') $sms_message .= " -- ";
							$sms_message .= $mlm_row_context ? MultiLanguage::getUITranslation($mlm_row_context, "survey_866") : $lang["survey_866"]; //= To begin the phone survey, reply to this message with any text to receive a phone call.
							// Add phone number and access code to table
							TwilioRC::addSmsAccessCodeForPhoneNumber($participantPhone, $twilio_from_number, Survey::PREPEND_ACCESS_CODE_NUMERAL . $survey_access_code, $project_id_by_ssq_id[$row['ssq_id']]);
						} 
						elseif ($row['delivery_type'] == 'SMS_INVITE_WEB') {
							// Set survey link
							$this_survey_link = APP_PATH_SURVEY_FULL . '?s=' . $row['hash'];
							// Append the survey link?
							if ($row['append_survey_link']) {
								if ($sms_message != '') $sms_message .= " -- ";
								$msg_template = $mlm_row_context ? MultiLanguage::getUITranslation($mlm_row_context, "survey_1584") : $lang["survey_1584"]; //= To begin the survey, visit {0:SurveyLink}
								$sms_message .= RCView::interpolateLanguageString($msg_template, $this_survey_link);
							}
						} 
						else {
							// Send access code via SMS
							if ($sms_message != '') $sms_message .= " -- ";
							$sms_message .= $mlm_row_context ? MultiLanguage::getUITranslation($mlm_row_context, "survey_865") : $lang["survey_865"]; //= To begin the survey, reply to this message with any text.
							$sms_message = trim($sms_message);
							// Add phone number and access code to table
							TwilioRC::addSmsAccessCodeForPhoneNumber($participantPhone, $twilio_from_number, $survey_access_code, $project_id_by_ssq_id[$row['ssq_id']]);
						}
						// Send SMS to the phone number (using the project's specified messaging provider)
						$success = (new Messaging($project_id_by_ssq_id[$row['ssq_id']]))->send($sms_message, $participantPhone, 'sms', $row['record'], $category, true);
						if ($success === true) {
							// Set query to update as SENT with timestamp when sent
							$sql = 
							   "UPDATE redcap_surveys_scheduler_queue 
								SET `status` = 'SENT', time_sent = '".NOW."'
								WHERE ssq_id = $ssq_id";
							$invitationSent = true;
							$emailCountSuccess++;
						} else {
							$default_error_sql = 
							   "UPDATE redcap_surveys_scheduler_queue 
								SET `status` = 'DID NOT SEND', reason_not_sent = 'ERROR SENDING SMS' 
								WHERE ssq_id = $ssq_id";
							try {
								if (strpos($success,"violates a bl") !== false // "violates a blacklist rule" (or in the future possibly, "violates a blocklist rule")
									|| strpos($success,"unsubscribed recipient") !== false // "Attempt to send to unsubscribed recipient"
								) {
									$sql = "UPDATE redcap_surveys_scheduler_queue 
											SET `status` = 'DID NOT SEND', reason_not_sent = 'PARTICIPANT OPTED OUT' 
											WHERE ssq_id = $ssq_id";
								} 
								else {
									$sql = $default_error_sql;
								}
							} catch (Exception $success_e ) {
								// Default functionality
								$sql = $default_error_sql;
							}
							$emailCountFail++;
							// Log the returned error message
							db_query(
							   "INSERT INTO redcap_twilio_error_log (ssq_id, error_message) 
								VALUES ($ssq_id, '".db_escape($success)."')"
							);
						}
					} 
					else {
						## VOICE DELIVERY
						// Set the survey URL that Twilio will make the request to
						$question_url = APP_PATH_SURVEY_FULL . '?s=' . $row['hash'];
						// Instantiate a client to Twilio's REST API
						$twilioClient = new Services_Twilio($row['twilio_account_sid'], $row['twilio_auth_token']);
						// Call the phone number
						try {
							// Create hash so that we can add it to callback url
							$callback_hash = generateRandomHash(50);
							$call = $twilioClient->account->calls->create(Messaging::formatNumber($twilio_from_number), Messaging::formatNumber($participantPhone), $question_url, array(
								"StatusCallback" => APP_PATH_SURVEY_FULL . "?__sid_hash=$callback_hash",
								"FallbackUrl" => APP_PATH_SURVEY_FULL . "?__sid_hash=$callback_hash&__error=1",
								"IfMachine"=>"Continue"
							));
							// Add the sid and sid_hash to the db table so that we can delete the log for this event once it has completed
							TwilioRC::addEraseCall($project_id_by_ssq_id[$ssq_id], $call->sid, $callback_hash);
							// Set query to update as SENT with timestamp when sent
							$sql = 
							   "UPDATE redcap_surveys_scheduler_queue 
								SET `status` = 'SENT', time_sent = '".NOW."'
								WHERE ssq_id = $ssq_id";
							$invitationSent = true;
							$emailCountSuccess++;
						} catch (Exception $e) {
							// Set default query in case of failure
							$sql = 
							   "UPDATE redcap_surveys_scheduler_queue 
								SET `status` = 'DID NOT SEND', reason_not_sent = 'ERROR MAKING VOICE CALL' 
								WHERE ssq_id = $ssq_id";
							$emailCountFail++;
							// Log the returned error message
							db_query("insert into redcap_twilio_error_log (ssq_id, error_message) values ($ssq_id, '".db_escape($e->getMessage())."')");
						}
					}
				}
				else {
					## EMAIL DELIVERY
					// Set default query (failure to send because email address doesn't exist)
					$sql = 
					   "UPDATE redcap_surveys_scheduler_queue 
						SET `status` = 'DID NOT SEND', reason_not_sent = 'EMAIL ADDRESS NOT FOUND' 
						WHERE ssq_id = $ssq_id";
					// If fromEmail is missing, then have it sent from the recipient themself
					$fromEmail = empty($row['email_static']) ? $participantEmail : $row['email_static'];
					// If we have an email for this ssq_id, then send it. Otherwise, mark it as DID NOT SEND
					if ($participantEmail != null) {
						// Decode subject/content (just in case)
						$row['email_content'] = label_decode($row['email_content']);
						$row['email_subject'] = strip_tags(label_decode($row['email_subject']));
						// If this is a reminder (not the first invitation), then prepend "[Reminder]" (survey_732) to the subject
						// MLM: This needs to be translated based on the language
						$reminder_prefix = $lang["survey_732"];
						$mlm_rtl = false;
						if ($mlm_row_context) {
							$reminder_prefix = MultiLanguage::getUITranslation($mlm_row_context, "survey_732");
							$mlm_rtl = MultiLanguage::isRtl($mlm_row_context->project_id, $mlm_row_context->lang_id);
						}
						if ($reminder_num > 0) $row['email_subject'] = $reminder_prefix . " " . $row['email_subject'];
						// Build email message content
						// Note: Since long the append_survey_link flag is always 0. Therefore, the following
						// if-block of code is obsolete. $emailContentsAppend will always be empty!
						// Therefore, no MLM translations are needed here.
						$emailContentsAppend = "";
						if ($row['append_survey_link'] && $row['delivery_type_asi'] != 'PARTICIPANT_PREF') {
							$emailContentsAppend = '<br /><br />
								'.$lang['survey_134'].'<br />
								<a href="' . APP_PATH_SURVEY_FULL . '?s=' . $row['hash'] . '">'
								.($row['title'] == "" ? APP_PATH_SURVEY_FULL . '?s=' . $row['hash'] : strip_tags(label_decode($row['title']))).'</a><br /><br />
								'.$lang['survey_135'].'<br />
								' . APP_PATH_SURVEY_FULL . '?s=' . $row['hash'] . '<br /><br />
								'.$lang['survey_137'];
						}
						$emailContents = '<html><body style="font-family:arial,helvetica;">'.nl2br($row['email_content']).$emailContentsAppend.'</body></html>';
						// Construct email components
						$email = new Message($project_id_by_ssq_id[$row['ssq_id']], $row['record'], ($row['event_id'] ?? null), ($row['form_name']??null), ($row['instance']??null), $mlm_rtl);
						$email->setTo($participantEmail);
						$email->setFrom($fromEmail);
						$email->setFromName($row['email_sender_display']); // Add survey invitation sender's email display name (sent via ASI, Participant List, Form)
						$email->setSubject($row['email_subject']);
						$email->setBody($emailContents);
						// If Protected Email Mode is enabled for this project AND the email message contains piped identifier fields, then do protected email stuff
						$enforceProtectedEmail = ($row['protected_email_mode'] && ($row['protected_email_mode_trigger'] == 'ALL'
												|| ($row['protected_email_mode_trigger'] == 'PIPING' && $email->doesMessageContainPipedIdentifiersBySSQID($ssq_id))));
						// Send email
						$invitationSent = $email->send(false, true, $enforceProtectedEmail, $category, $row["lang_id"]);
						if (!$invitationSent && $email->emailApiConnectionError) {
							// If email failed to send due to a connection with a Web Email API, skip this to re-queue it to be picked up later to be sent.
							$sql = 
							   "UPDATE redcap_surveys_scheduler_queue 
								SET `status` = 'QUEUED'
								WHERE ssq_id = $ssq_id";
						} 
						elseif ($invitationSent) {
							// Set query to update as SENT with timestamp when sent
							$sql = 
							   "UPDATE redcap_surveys_scheduler_queue 
								SET `status` = 'SENT', time_sent = '".NOW."'
								WHERE ssq_id = $ssq_id";
							$emailCountSuccess++;
						} 
						else {
							// Mark as DID NOT SEND with reason why
							$sql = 
							   "UPDATE redcap_surveys_scheduler_queue 
								SET `status` = 'DID NOT SEND', reason_not_sent = 'EMAIL ATTEMPT FAILED' 
								WHERE ssq_id = $ssq_id";
							$emailCountFail++;
						}
					}
				}

				// Execute query after email was sent or did not send
				$q2 = db_query($sql);
				// If email was sent successfully, then also add it to surveys_emails table to "log" it
				if ($q2 && $invitationSent) {
					// Update surveys_emails table
					$sql = 
					   "UPDATE redcap_surveys_emails 
						SET email_sent = '".NOW."'
						WHERE email_id = $email_id AND email_sent is null";
					db_query($sql);
				}
				// Set language for next loop
				$prev_language = $this_language;
				// Increment counter
				$numEmailsSent++;
			}

			// Free up memory
			db_free_result($q);
			unset($ssq_ids, $ssq_ids_emails, $ssq_ids_voice_sms);

			// If last loop's project language was NOT English, then reset $lang back to English
			if ($prev_language != 'English') {
				$lang = Language::getLanguage('English');
			}

			// Now that all emails have been sent, record in table how long it took to send them (to use rate in future batches)
			if ($numEmailsSent >= self::MIN_RECORD_EMAILS_SENT) {
				// Stop the email time sent tracker to count time it takes to send all emails
				$mtime = explode(" ", microtime());
				$endtime = $mtime[1] + $mtime[0];
				$totalTimeEmailsSent = ($endtime - $starttime);
				// Calculate the email sending rate in emails/minute
				$emailsSentPerMinuteCalculated = round(($numEmailsSent / $totalTimeEmailsSent) * 60);
				// Now add this value to the redcap_surveys_emails_send_rate table
				$sql = "INSERT INTO redcap_surveys_emails_send_rate (sent_begin_time, emails_per_batch, emails_per_minute)
						VALUES ('" . NOW . "', $numEmailsSent, $emailsSentPerMinuteCalculated)";
				db_query($sql);
			}
		}
		// Return email-sending success/fail count
		return array($emailCountSuccess, $emailCountFail);
	}


	// If this was a survey response, and it was just completed BEFORE an invitation was sent out (when it was alrady queued to send),
	// then remove it from the scheduler_queue table (if already in there).
	public static function deleteInviteIfCompletedSurvey($survey_id, $event_id, $record, $instance=1)
	{
		// Make sure the response is completed first
		if (!Survey::isResponseCompleted($survey_id, $record, $event_id, $instance)) return false;
		// Initialize vars
		$ssq_ids = array();
		$wasDeleted = false;
		// If invitation is already queued, then set it as DID NOT SEND with reason_not_sent of SURVEY ALREADY COMPLETED
		$sql = "select distinct q.ssq_id, s.project_id, q.reminder_num
				from redcap_surveys_participants p, redcap_surveys_response r,
				redcap_surveys_emails_recipients e, redcap_surveys s, redcap_surveys_scheduler_queue q
                left join redcap_surveys_scheduler ss on q.ss_id = ss.ss_id
				where p.survey_id = $survey_id and p.survey_id = s.survey_id
				and p.event_id = $event_id and r.participant_id = p.participant_id and p.participant_email is not null
				and q.email_recip_id = e.email_recip_id and p.participant_id = e.participant_id
				and q.status = 'QUEUED' and r.record = '" . db_escape($record) . "' and r.instance = $instance and r.instance = q.instance 
				and (q.ss_id is null or q.ss_id = ss.ss_id)";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
            $sql = "delete from redcap_surveys_scheduler_queue where ssq_id = ".$row['ssq_id'];
			$wasDeleted = db_query($sql);
			if (db_affected_rows() > 0) {
				// Log the deletion
				if ($row['reminder_num'] == 0) {
					Logging::logEvent($sql,"redcap_surveys_scheduler_queue","MANAGE",$record,
						"survey_id = $survey_id,\nevent_id = $event_id,\nrecord = '$record',\nssq_id = {$row['ssq_id']}",
						"Automatically remove scheduled survey invitation", "", "SYSTEM", $row['project_id'], true, $event_id);
				}
			}
		}
		// Return true if removed it from queue table
		return $wasDeleted;
	}

	// Get previously-used email display names for the Participant List
	// (limit to 10 most recent unique usages per user per project)
	public static function getDisplayNamesParticipantList()
	{
		global $Proj;
		$survey_ids = array_keys($Proj->surveys);
		$displayNames = array();
		$sql = "select distinct email_sender_display from redcap_surveys_emails
				where survey_id in (".prep_implode($survey_ids).") and email_sender = ".UI_ID."
				and email_sender_display is not null
				order by email_id desc limit 10";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$displayNames[$row['email_sender_display']] = $row['email_sender_display'];
		}
		natcasesort($displayNames);
		return $displayNames;
	}

	// Trigger all or specific ASIs for all records in a project
	public function triggerASIs($project_id, $surveysEvents=array(), $is_dry_run=false)
	{
		$num_scheduled_total = 0;
		$recordsAffected = array();
		// Get list of all records in project
		$recordList = Records::getRecordList($project_id);
		if (empty($recordList)) return $num_scheduled_total;
		// Go through each record and check if each has any invitations that need to be scheduled
		foreach ($recordList as $key=>$record) {
			// Check if record needs any schedulings done, and increment count of invitations scheduled, if any
			list ($numInvitationsScheduled, $numInvitationsDeleted, $numRecordsAffected) = $this->checkToScheduleParticipantInvitation($record, false, $surveysEvents, $is_dry_run);
			// If record was affected, then put it in array
            if ($numInvitationsScheduled > 0) {
                $recordsAffected[$record]['sent_or_scheduled'] = $numInvitationsScheduled;
            } elseif ($numInvitationsDeleted > 0) {
                $recordsAffected[$record]['removed'] = $numInvitationsDeleted;
            }
			// Unset from memory
			unset($recordList[$key]);
		}
        // return all records affected
        return $recordsAffected;
	}

	// Display list of checkboxes for all survey/events for re-triggering ASIs for all records in a project
	public function displayAutoInviteSurveyEventCheckboxList($project_id)
	{
		global $lang;
		$Proj = new Project($project_id);
		// Fill up $schedules array with schedules
		$this->setSchedules();
		$html = "";
		foreach ($this->schedules as $survey_id=>$attr1) {
			$form = $Proj->surveys[$survey_id]['form_name'];
			$formLabel = strip_tags($Proj->forms[$form]['menu']);
			foreach ($attr1 as $event_id=>$attr) {
				if (!$attr['active']) continue;
				if ($Proj->longitudinal) {
					$html .= RCView::div(array('class'=>'boldish mt-2'),
						$Proj->eventInfo[$event_id]['name_ext']
					);
				}
				$html .= RCView::div(array('class'=>'mt-1'),
							RCView::checkbox(array('id'=>'se_'.$survey_id.'-'.$event_id, 'checked'=>'checked')) .
							$formLabel .(!$Proj->longitudinal ? "" : "&nbsp;&nbsp;<span style='color:#777;'>-&nbsp;".$Proj->eventInfo[$event_id]['name_ext']."</span>")
						 );
			}
		}
		return RCView::p(array('class'=>'mt-0'), $lang['asi_025']) .
						RCView::p(array(), RCView::b($lang['asi_044']).$lang['asi_043']).
						RCView::p(array(), $lang['asi_026']) .
                        RCView::iife('
                            var checkbox = document.getElementById("asi-dry-run-toggle-switch");
                            var paragraph = document.querySelector(".dry-run");
                            checkbox.addEventListener("change", function() {
                                if (checkbox.checked) {
                                    paragraph.style.animation = "DryRunSlideFadeInMssg 0.5s ease-in-out";
                                    paragraph.style.display = "block";
                                } else {
                                    paragraph.style.animation = "DryRunSlideFadeOutMssg 0.5s ease-in-out";
                                    setTimeout(function() {
                                        paragraph.style.display = "none";
                                    }, 500);
                                }
                            });
                            var styleSheet = document.styleSheets[0];
                            var keyframes = styleSheet.cssRules;
                            var fadeInRuleExists = false;
                            var fadeOutRuleExists = false;
                            for (var i = 0; i < keyframes.length; i++) {
                                if (keyframes[i].name === "DryRunSlideFadeInMssg") {
                                    fadeInRuleExists = true;
                                }
                                if (keyframes[i].name === "DryRunSlideFadeOutMssg") {
                                    fadeOutRuleExists = true;
                                }
                            }
                            if (!fadeInRuleExists) {
                                styleSheet.insertRule("@keyframes DryRunSlideFadeInMssg { from { opacity: 0; transform: translateY(-100%); } to { opacity: 1; transform: translateY(0); } }");
                            }
                            if (!fadeOutRuleExists) {
                                styleSheet.insertRule("@keyframes DryRunSlideFadeOutMssg { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-100%); } }");
                            }
                        ') .
                        RCView::p(array('class' => 'dry-run text-dangerrc fs13 my-3', 'style' => 'display:none;'), RCView::fa('fa-solid fa-flask-vial mr-1') . $lang['asi_058']) .
						RCView::div(array('class'=>'gray mb-0'),
							RCView::span(array('class'=>'mb-0 font-weight-bold', 'style'=>'color:#A00000;'), $lang['asi_027']) .
							RCView::a(array('class'=>'ms-5 fs11', 'onclick'=>"$('#reeval_asi_dlg input[type=checkbox]').not('#asi-dry-run-toggle-switch').prop('checked',true);", 'style'=>'text-decoration:underline'), $lang['survey_41']) .
							RCView::a(array('class'=>'ms-2 fs11', 'onclick'=>"$('#reeval_asi_dlg input[type=checkbox]').not('#asi-dry-run-toggle-switch').prop('checked',false);", 'style'=>'text-decoration:underline'), $lang['survey_42']) .
                            (!empty($html) ? RCView::div(array('style' => "float:right; text-align:start;"), RCView::toggle(array('id' => 'asi-dry-run-toggle-switch','style' => 'width:48px;height:24px;'), RCView::label(array('class' => "font-weight-bold fs11 mb-1"), $lang['alerts_402']))) : '') .
							$html
						);
	}

	// Determine if an invitation (using ssq_id) originated from an ASI being triggered
	public static function invitationBelongsToASI($ssq_id, $column='ssq_id')
	{
		if (!isinteger($ssq_id)) return false;
		if ($column != 'email_recip_id') $column = 'ssq_id';
		$sql = "select 1 from redcap_surveys_scheduler_queue where ss_id is not null and $column = $ssq_id";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}

	// Return boolean if any active surveys' confirmation emails have an email body with identifier fields in it
	public function anyASIContainIdentifierFields()
	{
		$Proj = new Project($this->project_id);
		$this->setSchedules();
		foreach ($this->schedules as $attr2) {
			foreach ($attr2 as $attr) {
				if (containsIdentifierFields($attr['email_content'], $this->project_id)) {
					return true;
				}
			}
		}
		return false;
	}
}
