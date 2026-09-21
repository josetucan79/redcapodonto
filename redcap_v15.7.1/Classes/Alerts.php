<?php

use REDCap\Context;
use MultiLanguageManagement\MultiLanguage;

class Alerts
{
    private $alerts_settings = array();

    private $alerts_queue = array();

    // Fields set by cacheProject method
    public $logic_fields = null;
    public $logic_events = null;
    public $parser_cache = null;
    public $record_data = null;
    public $datediff_cron_queued_records = null;
    public $alertsRecordsSent = array();
    public $alertsRecordsScheduled = array();
    public $alertAttachmentsToDelete = array();

    const participant_email_var = '[survey-participant-email]';
    const participant_phone_var = '[survey-participant-phone]';

    const notification_log_num_per_page = 100;

    const MAX_ATTACHMENT_SIZE_MB = 20;

    const ALERT_UNIQUE_ID_PREFIX = 'A-';
    const CRON_SEND_EMAIL_ON_FIELDS = array('send-on',
                                            'send-on-date',
                                            'send-on-field',
                                            'send-on-time-lag-days',
                                            'send-on-time-lag-hours',
                                            'send-on-time-lag-minutes');

    public function getAlertDefaults()
    {
        $alert = getTableColumns('redcap_alerts');
        unset($alert['project_id'], $alert['alert_id']);
        foreach ($alert as $key=>$val) {
            unset($alert[$key]);
            $key = str_replace("_", "-", $key);
            $alert[$key] = $val;
        }
        return $alert;
    }

    public function getAlertSettings($pid = null)
    {
        if(!isset($pid) && defined('PROJECT_ID')){
            $pid = PROJECT_ID;
        }
        // If we already have the structure, return it
        $alertNum = 1;
        if (!isset($this->alerts_settings[$pid])) {
            // Return values if row exists
            $sql = "select * from redcap_alerts where project_id = $pid order by alert_order, alert_id";
            $q = db_query($sql);
            $this->alerts_settings[$pid] = array();
            while ($row = db_fetch_assoc($q)) {
                unset($row['project_id']);
                $row['alert_number'] = $alertNum++;
                $row['email_to'] = strip_tags($row['email_to']??"");
                $this->alerts_settings[$pid][$row['alert_id']] = $row;
            }
        }
        // Check the order of the alerts, if required reorder alerts
        $this->checkOrder($this->alerts_settings[$pid]);
        return $this->alerts_settings[$pid];
    }

    public function getAlertsQueue($pid = null)
    {
        if(!isset($pid) && defined('PROJECT_ID')){
            $pid = PROJECT_ID;
        }
        $Proj = new \Project($pid);
        // If we already have the structure, return it
        if (!isset($this->alerts_queue[$pid])) {
            // Return values if row exists
            $sql = "select a.project_id, a.email_deleted as deactivated, q.* 
                    from redcap_alerts a, redcap_alerts_recurrence q 
                    where a.project_id = $pid and q.alert_id = a.alert_id
                    order by a.alert_id, q.aq_id";
            $q = db_query($sql);
            $this->alerts_queue[$pid] = array();
            while ($row = db_fetch_assoc($q)) {
                $row['option'] = $row['send_option'];
                $row['event_id'] = ($row['event_id'] != '') ? $row['event_id'] : $Proj->firstEventId;
                $row['alert'] = $this->getKeyIdFromAlertId($pid, $row['alert_id']);
                unset($row['alert_id'], $row['send_option'], $row['form_name_event']);
                array_push($this->alerts_queue[$pid], $row);
                // $this->alerts_queue[$pid][] = $row;
            }
        }
        return $this->alerts_queue[$pid];
    }

    // Obtain array of records that have been queued for a given alert
    public function getAlertQueuedRecords($alert_id)
    {
        $sql = "select distinct record from redcap_alerts_recurrence where alert_id = $alert_id";
        $q = db_query($sql);
        $records = array();
        while ($row = db_fetch_assoc($q)) {
            $records[] = $row['record'];
        }
        natcasesort($records);
        return $records;
    }

    // Obtain array of records that have been sent for a given alert
    public function getAlertsSent($alert_id)
    {
        $sql = "select record from redcap_alerts_sent where alert_id = $alert_id";
        $q = db_query($sql);
        $records = array();
        while ($row = db_fetch_assoc($q)) {
            $records[] = $row['record'];
        }
        natcasesort($records);
        return $records;
    }

    public function getAlertSetting($key, $pid = null)
    {
        if (!isset($pid) && defined('PROJECT_ID')) $pid = PROJECT_ID;
        $key = str_replace("-", "_", $key);
        $settings = $this->getAlertSettings($pid);
        $thisSetting = array();
        foreach ($settings as $attr) {
            if (!array_key_exists($key, $attr)) return array();
            $thisSetting[] = $attr[$key];
        }
        return $thisSetting;
    }

    // Return array of alert_id's that match the form and event_id (or has null event_id) - ignore deleted alerts
    private function getAlertsForInstrumentSave($project_id, $record, $event_id, $instrument="", $repeat_instance=1, $repeat_instrument='')
    {
        $alerts = array();
        if ($repeat_instance == '') $repeat_instance = '1';
        // Get values from tables
        $sql = "select a.alert_id, if (r.aq_id is null, 0, 1) as scheduled, if (s.alert_sent_id is null, 0, 1) as sent
                from redcap_alerts a 
                left join redcap_alerts_recurrence r
                    on a.alert_id = r.alert_id and r.record = ".checkNull($record)." 
                    and (
                        (a.alert_stop_type = 'RECORD')
                        or (a.alert_stop_type = 'RECORD_EVENT' and r.event_id = '".db_escape($event_id)."')
                        or (a.alert_stop_type = 'RECORD_INSTRUMENT' and r.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name))
                        or (a.alert_stop_type = 'RECORD_EVENT_INSTRUMENT' and r.event_id = '".db_escape($event_id)."' 
                            and r.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name))
                        or (a.alert_stop_type = 'RECORD_EVENT_INSTRUMENT_INSTANCE' and r.event_id = '".db_escape($event_id)."' 
                            and r.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name) and r.instance = '".db_escape($repeat_instance)."')
                    )
                left join redcap_alerts_sent s 
                    on a.alert_id = s.alert_id and s.record = '".db_escape($record)."'
                    and (
                        (a.alert_stop_type = 'RECORD')
                        or (a.alert_stop_type = 'RECORD_EVENT' and s.event_id = '".db_escape($event_id)."')
                        or (a.alert_stop_type = 'RECORD_INSTRUMENT' and s.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name))
                        or (a.alert_stop_type = 'RECORD_EVENT_INSTRUMENT' and s.event_id = '".db_escape($event_id)."' 
                            and s.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name))
                        or (a.alert_stop_type = 'RECORD_EVENT_INSTRUMENT_INSTANCE' and s.event_id = '".db_escape($event_id)."' 
                            and s.instrument = if(a.form_name is null, '".db_escape($repeat_instrument)."', a.form_name) and s.instance = '".db_escape($repeat_instance)."')
                    )
                where a.project_id = $project_id and a.email_deleted = 0 
                and (
                    (a.form_name = '" . db_escape($instrument) . "' and a.form_name != '' 
                        and (a.form_name_event is null or a.form_name_event = '" . db_escape($event_id) . "'))
                    or
                    (a.alert_condition is not null and a.form_name is null)
                )";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $alerts[$row['alert_id']] = $row;
        }
        return $alerts;
    }

    public function saveRecordAction($project_id, $record, $instrument, $event_id, $repeat_instance=1, $survey_hash=null, $response_id=null,
                                     $dataValuesModified=null, $dataValuesModifiedIncludingCalcs=null, $isDataImport=false)
    {
        if ($project_id == '' || $record == '') return;
        // Get data for this record
        $Proj = new Project($project_id);
        // Is the current instrument a repeating instrument? If so set $repeat_instrument to $instrument, else ''.
        $repeat_instrument = $Proj->isRepeatingForm($event_id, $instrument) ? $instrument : '';
        // Get any viable alerts to trigger for this form/event
        $viableAlerts = $this->getAlertsForInstrumentSave($project_id, $record, $event_id, ($isDataImport ? "" : $instrument), $repeat_instance, $repeat_instrument);

        // Loop through all viable alerts to see if we need to trigger anything
        if (empty($viableAlerts)) return;

        $alerts = $this->getAlertSettings($project_id);

        // Remove non-viable alerts from $alerts
        $logicPipingVars = "";
        foreach ($alerts as $alert_id => $attr) {
	        // Remove non-viable alerts from $alerts
	        if (!isset($viableAlerts[$alert_id])) {
                unset($alerts[$alert_id]);
                continue;
	        }
            // Gather logic and piping variables as ONE BIG string
            $logicPipingVars .= " {$attr['alert_condition']} {$attr['email_to']} {$attr['email_cc']} {$attr['email_bcc']}"
                             .  " {$attr['email_subject']} {$attr['alert_message']} {$attr['email_attachment_variable']} {$attr['phone_number_to']} ";
			if ($attr['sendgrid_template_data'] != '') {
				$template_data = json_decode($attr['sendgrid_template_data'], TRUE);
				foreach ($template_data as $key => $value) {
					$logicPipingVars .= "{$value} ";
				}
			}
        }

        // Get all data needed for any piping or logic parsing for ALL viable alerts in this project
        $logicPipingVars = trim($logicPipingVars);
        $logic_events = array($event_id); // Initially include the current event context
        $logic_fields = array();
        if ($logicPipingVars == "") $logicPipingVars = "[".$Proj->table_pk."]"; // At least add record ID field so that $data is not empty
        foreach (array_keys(getBracketedFields($logicPipingVars, true, true, false)) as $this_field)
        {
            // Check if has dot (i.e. has event name included)
            if (strpos($this_field, ".") !== false) {
                list ($this_event_name, $this_field) = explode(".", $this_field, 2);
                if (Piping::containsEventSpecialTags("[$this_event_name]")) {
                    $this_event_name = Piping::pipeSpecialTags("[$this_event_name]", $project_id, $record, $event_id, $repeat_instance, null, false, null, $instrument, false, false, false, true, false, false, true);
                }
                $logic_events[] = $this_event_name;
            }
            // Verify that the field really exists (may have been deleted). If so, skip it.
            if (!isset($Proj->metadata[$this_field])) continue;
            // Add field to array
            $logic_fields[] = $this_field;
        }
        // If any -event-name smart variables are used, then just pull data from all events, just in case
        if (Piping::containsEventSpecialTags($logicPipingVars)) {
            $logic_events = array();
        }
        // Get the relevant data
        $getDataParams = [
            'project_id' => $project_id,
            'return_format' => 'array',
            'records' => $record,
            'fields' => $logic_fields,
            'events' => $logic_events,
            'decimalCharacter' => '.',
        ];
        $data = REDCap::getData($getDataParams);
        // Set if the current record/event/form/instance is complete
        $formCompleted = $this->isFormStatusCompleted($project_id, $record, $event_id, $instrument, $repeat_instance);
        // Is this a repeating form/event?
        $isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($event_id, $instrument);

        // Determine if we need to pre-fetch record data beforehand to hand off to REDCap::evaluateLogic() below to speed everything up when lots of alerts and/or records exist
        $logic_all = "";
        foreach ($alerts as $attr) {
            if ($attr['alert_condition'] != '') {
                $logic_all .= " ".$attr['alert_condition'];
            }
        }
	    $record_data = null;
        if (trim($logic_all) != "") {
            $logic_all_fields = array_keys(getBracketedFields($logic_all, true, true, true));
            unset($logic_all);
            if (!empty($logic_all_fields)) {
                $getDataParams = ['project_id'=>$project_id, 'records'=>[$record], 'fields'=>$logic_all_fields,
                                  'returnEmptyEvents'=>true, 'decimalCharacter'=>'.', 'returnBlankForGrayFormStatus' => true];
                $record_data = Records::getData($getDataParams);
            }
        }

        // Get all alerts for this project
        foreach ($alerts as $alert_id => $attr)
        {
            // Determine if notification is already sent or scheduled for this record/event/instrument/instance
            $recurrenceAlreadyCreated = $viableAlerts[$alert_id]['scheduled'];
            $alertAlreadySent = $viableAlerts[$alert_id]['sent'];
            // Determine status completion trigger setting
            $triggerOnCompleteStatus = !$triggerOnAnyStatus = ($attr['email_incomplete'] == '1');
            // Has conditional logic?
            $triggerOnLogic = ($attr['alert_condition'] != '');
            $triggerOnLogicOnly = ($triggerOnLogic && $attr['form_name'] == '');
            $ensureLogicStillTrue = ($triggerOnLogic && $attr['ensure_logic_still_true']);
            $doNotClearRecurrences = ($triggerOnLogic && $attr['do_not_clear_recurrences']);
            // Send alert every time data is added/modified? If no data was added/modified, then do nothing and skip this loop
            if ($attr['email_repetitive_change'] && !$attr['email_repetitive_change_calcs'] && $dataValuesModified === false) continue;
            if ($attr['email_repetitive_change'] && $attr['email_repetitive_change_calcs'] && $dataValuesModifiedIncludingCalcs === false) continue;
            // Send alert every time?
            $sendEveryTimeDataChanges = (  ($attr['email_repetitive_change'] && !$attr['email_repetitive_change_calcs'] && $dataValuesModified === true))
                                        || ($attr['email_repetitive_change'] && $attr['email_repetitive_change_calcs']  && $dataValuesModifiedIncludingCalcs === true);
            $sendEveryTime = ($attr['email_repetitive'] || $sendEveryTimeDataChanges);
            // Send now?
            $sendNow = ($attr['cron_send_email_on'] == 'now');
            $sendJustOnce = ($attr['cron_repeat_for'] == 0);
            $sendNowJustOnce = ($sendNow && $sendJustOnce);
            // Set as recurring?
            $recurring = (!$sendEveryTime && !$sendNowJustOnce);
            // Trigger it based on form status or by logic alone?
            if ($triggerOnLogicOnly || $triggerOnAnyStatus || ($triggerOnCompleteStatus && $formCompleted)) {
                // Unless sending EVERY time, do not send alert if already sent for this record/event/instrument/instance
                if (    $sendEveryTime
                    || (!$sendEveryTime && !$alertAlreadySent)
                    || ($recurrenceAlreadyCreated && $ensureLogicStillTrue)
                    || (!$recurrenceAlreadyCreated && $ensureLogicStillTrue && $alertAlreadySent && !$sendEveryTime)) // In case the original has been sent, and the recurrences have been removed but need to be re-added again
                {
                    // Trigger it based on logic?
                    if ($triggerOnLogic) {
                        if ($isRepeatingFormOrEvent) {
                            $passedLogicTest = REDCap::evaluateLogic($attr['alert_condition'], $project_id, $record, $event_id, $repeat_instance, $instrument, $instrument, $record_data);
                        } else {
                            $passedLogicTest = REDCap::evaluateLogic($attr['alert_condition'], $project_id, $record, $event_id, 1, "", $instrument, $record_data);
                        }
                        // If failed logic and has "ensure logic still true" enabled and already exists in recurrence table, then remove from table, unless set to be kept
                        if (!$passedLogicTest && $ensureLogicStillTrue && $recurrenceAlreadyCreated && !$doNotClearRecurrences) {
                            $this->deleteRecurrence($alert_id, $record, $event_id, $attr['form_name'], $repeat_instance);
                        }
                        // If passed logic and has "ensure logic still true" enabled and has already been sent, then make sure it doesn't send again now
                        if ($passedLogicTest && $ensureLogicStillTrue && $alertAlreadySent && !$sendEveryTime) {
                            $sendNow = false;
                        }
                    }
                    if (!$triggerOnLogic || $passedLogicTest) {
                        // Send alert now?
                        if ($sendNow) {
                            // If so, and MLM is active in the project, do not pass the potentially too 
                            // limited data available now, but pass null to force a reload of the full
                            // record data in the downstream logic.
                            $this->sendNotification($alert_id, $project_id, $record, $event_id, $instrument, $repeat_instance, MultiLanguage::isActive($project_id) ? null : $data);
                        }
                        // Schedule this alert to recur?
                        if ($recurring && !$recurrenceAlreadyCreated && !($alertAlreadySent && $sendJustOnce)) {
                            $this->createRecurrence($alert_id, $project_id, $record, $event_id, ($repeat_instrument != '' ? $repeat_instrument : $attr['form_name']), $repeat_instance, 0, '', $instrument);
                        }
                    }
                }
            }
        }
    }

    // Return true if a record's Form Status value for a given instrument/event/instance is Complete (=2)
    public static function isFormStatusCompleted($project_id, $record, $event_id, $instrument, $instance=1)
    {
        if (empty($instrument)) return false;
        // Set SQL for instance
        $instance = (int)$instance;
        $instanceSql = ($instance > 1) ? "and instance = '".db_escape($instance)."'" : "and instance is null";
        // Query data table for value of 2
        $sql = "select 1 from ".\Records::getDataTable($project_id)." where project_id = $project_id
				and event_id = $event_id and record = '" . db_escape($record) . "'
				and field_name = '" . db_escape($instrument) . "_complete' and value = '2' $instanceSql limit 1";
        $q = db_query($sql);
        // Return true if has been completed
        return (db_num_rows($q) > 0);
    }

        // Convert the key from the alerts_settings array to its corresponding alert_id
    public function getAlertIdFromKeyId($project_id, $id)
    {
        $settings = array_values($this->getAlertSettings($project_id));
        return isset($settings[$id]) ? $settings[$id]['alert_id'] : null;
    }

    // Convert the alert_id to the key from the alerts_settings array
    public function getKeyIdFromAlertId($project_id, $alert_id)
    {
        if (!is_numeric($alert_id)) return null;
        $alert_id .= "";
        $settings = array_values($this->getAlertSettings($project_id));
        foreach ($settings as $key=>$attr) {
            if ($attr['alert_id']."" === $alert_id) return $key;
        }
        return null;
    }

    // Does the recurrence already exist in the redcap_alerts_recurrence table?
    function recurrenceAlreadyCreated($alert_id, $record, $instance)
    {
        $sql = "select 1 from redcap_alerts_recurrence where alert_id = $alert_id
                and record = '".db_escape($record)."' and instance = '".db_escape($instance)."' limit 1";
        $q = db_query($sql);
        return (db_num_rows($q) > 0);
    }

    // Function called by the CRON to parse the logic function for each alert with datediff (called once per project)
    public function cacheProjectAlertFunctions($alerts, $Proj, $datediffsOnly=false)
    {
        // Create arrays to store logic fields, events, and parser functions used for this project
        $logic_fields = array();
        $logic_events = array();
        // $parser_cache = array();    // funcNames/argMaps for parsing the ASI logic, stored as an array of [survey_id][event_id] = array(funcName, argMap)

        // Get unique event names (with event_id as key)
        $unique_events = $Proj->getUniqueEventNames();

        // Get smart variables
        $smartVars = Piping::getSpecialTagsFormatted(false, false);

        // Loop through alerts
        foreach ($alerts as $alert_id => $data)
        {
            $condition_logic = $data['alert_condition'];

            // Optimization 1: Skip ASI if not datediff+today/now
            if ($datediffsOnly &&
                (!(strpos($condition_logic, "datediff") !== false &&
                  (strpos($condition_logic, "today") !== false || strpos($condition_logic, "now") !== false))
                )
            ) {
                continue;
            }

            // If logic contains smart variables, then we'll need to do the logic parsing *per item* rather than at the beginning
            // $logicContainsSmartVariables = Piping::containsSpecialTags($condition_logic);

            // Optimization 2: Cache the parser functions and arguments
//            $funcName = null;
//            if (!$logicContainsSmartVariables) {
//                try {
//                    // Instantiate logic parser
//                    $parser = new LogicParser();
//                    list ($funcName, $argMap) = $parser->parse($condition_logic, array_flip($unique_events));
//                    unset($parser);
//                    $parser_cache[$alert_id] = array( $funcName, $argMap );
//                }
//                catch (LogicException $e) {
//                    continue;
//                }
//            } else {
//                $parser_cache[$alert_id] = array();
//            }

            // Since we'll use logic_fields to build data used for piping and conditional logic testing, make sure we include all fields that might be used
            // Gather logic and piping variables as ONE BIG string
            $logicPipingVars = trim(" {$data['alert_condition']} {$data['email_to']} {$data['email_cc']} {$data['email_bcc']}"
                             .  " {$data['email_subject']} {$data['alert_message']} {$data['email_attachment_variable']} {$data['phone_number_to']} ");

            // Optimization 3: Limit the fields/events to those used in the ASI function
            foreach (array_keys(getBracketedFields($logicPipingVars, true, true, false)) as $this_field)
            {
                // Check if has dot (i.e. has event name included)
                if (strpos($this_field, ".") !== false) {
                    list ($this_event_name, $this_field) = explode(".", $this_field, 2);
                    $logic_events[] = $this_event_name;
                }
                // Verify that the field really exists (may have been deleted). If so, skip it.
                if (!isset($Proj->metadata[$this_field]) && !in_array($this_field, $smartVars)) continue;
                // Add field to array
                $logic_fields[] = $this_field;
            }
        }

        // Remove duplicates fields/events
        $logic_fields = array_values(array_unique($logic_fields));
        $logic_events = array_values(array_unique($logic_events));

        // Store results in SurveyScheduler object
        $this->logic_fields = $logic_fields;
        $this->logic_events = $logic_events;
        // $this->parser_cache = $parser_cache;

        // Also add all form status fields for repeating instruments to ensure we pick up all data structures
        foreach (array_keys($Proj->forms) as $this_form) {
            if ($Proj->isRepeatingFormAnyEvent($this_form)) {
                $this->logic_fields[] = $this_form."_complete";
            }
        }

        // return !empty($this->parser_cache);
        return true;
    }

    // Function called by the CRON to fetch data for datediff+today/now alerts
    public function cacheProjectAlertDataDatadiffCron($Proj, $processAllRecords=true)
    {
        // Build record list cache if not yet built for this project
	    Records::buildRecordListCacheCurl($Proj->project_id);
        // Load the data for this project based on filters generated above
        $getDataParams = [
            'project_id'=>$Proj->project_id,
            'fields'=>array_merge(array($Proj->table_pk), $this->logic_fields),'returnEmptyEvents'=>true,
            'decimalCharacter'=>'.',
	        'returnBlankForGrayFormStatus' => true
        ];
        // If we're not processing all records, then we should ONLY process the records queued in the redcap_crons_datediff table
        if (!$processAllRecords) {
            $getDataParams['records'] = array_keys($this->datediff_cron_queued_records[$Proj->project_id]) ?? [];
        }
        // Get the data
        $this->record_data = Records::getData($getDataParams);
    }

    public function setRecordsSentForAlerts($alert_ids=array(), $processAllRecords=true)
    {
        $this->alertsRecordsSent = array();
        $sql = "select distinct a.project_id, s.alert_id, s.record, s.event_id, s.instrument, s.instance from redcap_alerts_sent s, redcap_alerts a
                where a.alert_id in (".prep_implode($alert_ids).") and a.alert_id = s.alert_id";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // If we're not processing all records, then we should ONLY process the records queued in the redcap_crons_datediff table
            if (!$processAllRecords && !isset($this->datediff_cron_queued_records[$row['project_id']][$row['record']])) {
                continue;
            }
            $this->alertsRecordsSent[$row['alert_id']][$row['record']][$row['event_id']][$row['instrument']][$row['instance']] = true;
        }
    }

    public function setRecordsScheduledForAlerts($alert_ids=array(), $processAllRecords=true)
    {
        $this->alertsRecordsScheduled = array();
        $sql = "select distinct a.project_id, s.alert_id, s.record, s.event_id, s.instrument, s.instance from redcap_alerts_recurrence s, redcap_alerts a
                where a.alert_id in (".prep_implode($alert_ids).") and a.alert_id = s.alert_id";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // If we're not processing all records, then we should ONLY process the records queued in the redcap_crons_datediff table
            if (!$processAllRecords && !isset($this->datediff_cron_queued_records[$row['project_id']][$row['record']])) {
                continue;
            }
            $this->alertsRecordsScheduled[$row['alert_id']][$row['record']][$row['event_id']][$row['instrument']][$row['instance']] = true;
        }
    }

	// Function called by the CRON to check any alerts with datediff+today/now
	public function checkAlertsWithDatediffViaCron()
	{
        // Gather project_ids/records of all the projects with queued records for this batch (ordered last updated time to most recent updated time)
        $this->checkAlertsBulkGetQueuedRecords();
        // Eval all alerts for just the projects in this batch
        $records_affected = $this->checkAlertsBulk(array_keys($this->datediff_cron_queued_records), true);
        $numInvitationsScheduled = 0;
        $numInvitationsDeleted = 0;
        foreach ($records_affected as $projectId => $arr) {
            if (isset($arr['sent_or_scheduled'])) {
                $numInvitationsScheduled += count($arr['sent_or_scheduled']);
            }
            if (isset($arr['removed'])) {
                $numInvitationsDeleted += count($arr['removed']);
            }
        }
        return array($numInvitationsScheduled, $numInvitationsDeleted, $numInvitationsScheduled + $numInvitationsDeleted);
	}

    // Gather project_ids/records of all the projects with queued records for this batch (ordered last updated time to most recent updated time)
    public function checkAlertsBulkGetQueuedRecords()
    {
        $this->datediff_cron_queued_records = [];
        $sql = "select project_id, record from redcap_crons_datediff
                where alert_status = 'QUEUED'
                order by alert_updated_at, dd_id
                limit " . Jobs::RECORD_EVAL_LIMIT_PER_BATCH;
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $this->datediff_cron_queued_records[$row['project_id']][$row['record']] = true;
        }
        // Set all records to PROCESSING status
        foreach ($this->datediff_cron_queued_records as $project_id=>$recordKeys)
        {
            $sql = "update redcap_crons_datediff set alert_status = 'PROCESSING', alert_last_update_start = '".date('Y-m-d H:i:s')."'
                    where project_id = $project_id and record in (" . prep_implode(array_keys($recordKeys)) . ")";
            db_query($sql);
        }
    }

    // Function to check all alerts for one project or many projects
    public function checkAlertsBulk($project_ids=null, $datediffsOnly=false, $alert_ids=array(), $is_dry_run=false)
    {
		// Datediff cron: Return empty array if there are no projects/records to process
		if ($datediffsOnly && empty($this->datediff_cron_queued_records)) return [];

        if (isinteger($project_ids)) $project_ids = [$project_ids];
        elseif (!is_array($project_ids)) $project_ids = [];
        // Keep array of records affected
        $records_affected = array();

        // Sub-sql
        $sql1 = $datediffsOnly ? "AND a.form_name is null AND (a.alert_condition like '%datediff%(%today%,%)%' or a.alert_condition like '%datediff%(%now%,%)%')" : "";
        $sql2 = empty($project_ids) ? "" : "AND p.project_id in (".prep_implode($project_ids).")";
        $sql3 = empty($alert_ids) ? "" : "AND a.alert_id in (".prep_implode($alert_ids).")";

        // Get a list of all projects that are using active, time-based conditional logic for automated notifications
        $sql = "SELECT a.* FROM redcap_alerts a, redcap_projects p
                WHERE a.email_deleted = 0 AND p.status <= 1 AND p.date_deleted is null AND p.completed_time is null AND p.project_id = a.project_id 
                $sql1 $sql2 $sql3
                order by p.project_id desc, a.alert_id";
        $q = db_query($sql);
        $alerts = array();
        while ($row = db_fetch_assoc($q)) {
            $alerts[$row['project_id']][$row['alert_id']] = $row;
        }

        if (!empty($alerts)) System::increaseMemory(2048); // Increase memory to 2GB to prevent timeout

        // Loop through each project with datediff+today
        foreach ($alerts as $project_id=>$attr2)
        {
            // Set Proj object and other project-specific things for this loop
            $Proj = new Project($project_id);
            // Preload all survey parsing fields/events/functions
            if (!$this->cacheProjectAlertFunctions($attr2, $Proj, $datediffsOnly)) continue;
            // Preload all survey data and record schedules
            $this->cacheProjectAlertDataDatadiffCron($Proj, !$datediffsOnly);
            // Find any records that have already been sent or scheduled for these alerts
            $this->setRecordsSentForAlerts(array_keys($attr2), !$datediffsOnly);
            $this->setRecordsScheduledForAlerts(array_keys($attr2), !$datediffsOnly);
            // Loop through each alert for this project
            foreach ($attr2 as $alert_id=>$attr)
            {
                // Send now?
                $sendNow = ($attr['cron_send_email_on'] == 'now');
                $sendNowJustOnce = ($sendNow && $attr['cron_repeat_for'] == 0);
                // Send alert every time?
                $sendEveryTime = (int)$attr['email_repetitive'];
                // Set as recurring?
                $recurring = (!$sendEveryTime && !$sendNowJustOnce);
                $instrument = empty($attr['form_name']) ? '' : $attr['form_name'];
                // Has conditional logic?
                $triggerOnLogic = ($attr['alert_condition'] != '');
                $ensureLogicStillTrue = ($triggerOnLogic && $attr['ensure_logic_still_true']);
                $doNotClearRecurrences = ($triggerOnLogic && $attr['do_not_clear_recurrences']);
                // Loop through each record and evaluate the function
                foreach ($this->record_data as $record=>&$event_data)
                {
                    foreach ($event_data as $this_event_id1 => $_)
                    {
                        if ($this_event_id1 == 'repeat_instances') {
                            $eventNormalized = $event_data['repeat_instances'];
                        } else {
                            $eventNormalized = array();
                            $eventNormalized[$this_event_id1][""][1] = $event_data[$this_event_id1];
                        }
                        foreach ($eventNormalized as $event_id=>$data1)
                        {
                            $isRepeatingEvent = $Proj->isRepeatingEvent($event_id);
                            foreach ($data1 as $repeat_instrument=>$data2)
                            {
                                $isRepeatingForm = ($repeat_instrument != '');
                                $isRepeatingFormOrEvent = ($isRepeatingEvent || $isRepeatingForm);
                                foreach ($data2 as $instance=>$data3)
                                {
                                    // Don't try to evaluate anything that isn't defined at that event/instance
                                    if ($datediffsOnly && empty($data3[$Proj->table_pk])) continue;
                                    // Get current instrument (will not exist for Conditional Logic Only option UNLESS this is a repeating instrument)
                                    $current_instrument = ($repeat_instrument != '') ? $repeat_instrument : $instrument;
                                    // If this alert is to be triggered off of a repeating instrument, then ignore all other repeating instruments here
                                    if ($isRepeatingForm && $instrument != '' && $instrument != $repeat_instrument) continue;
                                    // Is alert scheduled?
                                    $alreadyScheduled = false;
                                    if (($attr['alert_stop_type'] == 'RECORD'   && isset($this->alertsRecordsScheduled[$alert_id][$record])) ||
                                        ($attr['alert_stop_type'] == 'RECORD_EVENT' && isset($this->alertsRecordsScheduled[$alert_id][$record][$event_id])) ||
                                        ($attr['alert_stop_type'] == 'RECORD_EVENT_INSTRUMENT'          && isset($this->alertsRecordsScheduled[$alert_id][$record][$event_id][$current_instrument])) ||
                                        ($attr['alert_stop_type'] == 'RECORD_EVENT_INSTRUMENT_INSTANCE' && isset($this->alertsRecordsScheduled[$alert_id][$record][$event_id][$current_instrument][$instance]))
                                    ) {
                                       $alreadyScheduled = true;
                                    }
                                    // Is alert sent?
                                    $alreadySent = false;
                                    if (($attr['alert_stop_type'] == 'RECORD'   && isset($this->alertsRecordsSent[$alert_id][$record])) ||
                                        ($attr['alert_stop_type'] == 'RECORD_EVENT' && isset($this->alertsRecordsSent[$alert_id][$record][$event_id])) ||
                                        ($attr['alert_stop_type'] == 'RECORD_EVENT_INSTRUMENT'          && isset($this->alertsRecordsSent[$alert_id][$record][$event_id][$current_instrument])) ||
                                        ($attr['alert_stop_type'] == 'RECORD_EVENT_INSTRUMENT_INSTANCE' && isset($this->alertsRecordsSent[$alert_id][$record][$event_id][$current_instrument][$instance]))
                                    ) {
                                        $alreadySent = true;
                                    }
                                    // If RECORD_INSTRUMENT stop type, then loop through all events first to find it
                                    if ($datediffsOnly && $attr['alert_stop_type'] == 'RECORD_INSTRUMENT')
                                    {
                                        if (isset($this->alertsRecordsSent[$alert_id][$record])) {
                                            foreach ($this->alertsRecordsSent[$alert_id][$record] as $formsAttr) {
                                                if (isset($formsAttr[$current_instrument])) {
                                                    $alreadySent = true;
                                                    break;
                                                }
                                            }
                                        }
                                        if (isset($this->alertsRecordsScheduled[$alert_id][$record])) {
                                            foreach ($this->alertsRecordsScheduled[$alert_id][$record] as $formsAttr) {
                                                if (isset($formsAttr[$current_instrument])) {
                                                    $alreadyScheduled = true;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    // Is sent OR scheduled?
                                    $alreadySentOrScheduled = $alreadySent || $alreadyScheduled;
                                    ## Check logic and/or Form save status
                                    $conditionsPassedLogic = null; // default
                                    $reevalWithFormSaveTrigger = (!$datediffsOnly && $instrument != '');
                                    $inFormSaveTriggerContext = ($instrument == $current_instrument && ($attr['form_name_event'] == '' || $attr['form_name_event'] == $event_id));
                                    // If alert is set to trigger on a form save (re-evals only)
                                    if ($reevalWithFormSaveTrigger && $inFormSaveTriggerContext) {
                                        // If doing a re-eval for an alert that requires a form status of Anything (any value in redcap_data)
                                        if ($attr['email_incomplete'] == '1') {
                                            $sql = "select value from ".\Records::getDataTable($project_id)." where project_id = $project_id and record = '".db_escape($record)."'
                                                     and event_id = '$event_id' and field_name = '".db_escape($instrument."_complete")."' and value != ''
                                                    and instance ".(($instance == '' || $instance == '1') ? "is null" : "= '".db_escape($instance)."'");
                                            $q = db_query($sql);
                                            $conditionsPassedLogic = (db_num_rows($q) > 0);
                                        } else {
                                            // If doing a re-eval for an alert that requires a form status of Complete
                                            $conditionsPassedLogic = $this->isFormStatusCompleted($project_id, $record, $event_id, $current_instrument, $instance);
                                        }
                                    } elseif ($reevalWithFormSaveTrigger && !$inFormSaveTriggerContext) {
                                        // If doing re-eval but this is not the target form/event, then set $conditionsPassedLogic=false so that it doesn't check any conditional logic (not needed if not on right form/event)
                                        $conditionsPassedLogic = false;
                                    }
                                    // If alert has conditional logic
                                    if ($conditionsPassedLogic !== false && $attr['alert_condition'] != '') {
                                        $conditionsPassedLogic = REDCap::evaluateLogic($attr['alert_condition'], $project_id, $record, $event_id, ($isRepeatingFormOrEvent ? $instance : 1), ($isRepeatingFormOrEvent ? $repeat_instrument : ""), $current_instrument, $this->record_data);
                                    }
                                    /**
                                     * Schedule/send the alert
                                     * The `$alreadySentOrScheduled` flag accounts for both alerts that have been sent and those that have been scheduled; therefore, if for example, an alert when it was first created was set to `Send immediately` and to send `Just once` but was later edited to send `Multiple times on a recurring basis`, then in order for this change to take effect, the corresponding alert in the `recap_alerts_sent` table must be deleted prior to the change being saved.
                                     */
                                    if ($conditionsPassedLogic && !$alreadySentOrScheduled) {
                                        // Send alert now?
                                        if ($sendNow) {
                                            if (!$is_dry_run) {
                                                $this->sendNotification($alert_id, $project_id, $record, $event_id, ($repeat_instrument == '' ? $current_instrument : $repeat_instrument), $instance, $this->record_data);
                                            }
                                            // Add this to array to prevent future loops from duplicating this when it should not
                                            $this->alertsRecordsSent[$alert_id][$record][$event_id][$current_instrument][$instance] = true;
                                        }
                                        // Schedule this alert to recur?
                                        if ($recurring) {
                                            $this->createRecurrence($alert_id, $project_id, $record, $event_id, ($repeat_instrument == '' ? $current_instrument : $repeat_instrument), $instance);
                                            // Add this to array to prevent future loops from duplicating this when it should not
                                            $this->alertsRecordsScheduled[$alert_id][$record][$event_id][$current_instrument][$instance] = true;
                                        }
                                        // Add to counts
                                        if ($sendNow || $recurring) {
                                            $records_affected[$project_id]['sent_or_scheduled'][] = $record;
                                        }
                                    }
                                    // If failed logic and has "ensure logic still true" enabled and already exists in recurrence table, then remove from table, unless set to be kept
                                    elseif (!$conditionsPassedLogic && $ensureLogicStillTrue && $alreadyScheduled && !$doNotClearRecurrences) {
                                        // Delete from recurrence table
                                        if ($this->deleteRecurrence($alert_id, $record, $event_id, $current_instrument, $instance) > 0) {
                                            $records_affected[$project_id]['removed'][] = $record;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // Set as evaluated in redcap_crons_datediff (whether scheduled, sent, deleted, or none)
            if ($datediffsOnly) {
                $recordBatches = array_chunk(array_keys($this->datediff_cron_queued_records[$project_id]), Jobs::RECORD_EVAL_LIMIT_PER_QUERY, true);
                foreach ($recordBatches as $recordBatch) {
                    $sql = "update redcap_crons_datediff set alert_status = null, alert_updated_at = '".date('Y-m-d H:i:s')."'
                            where project_id = $project_id and record in (".prep_implode($recordBatch).")";
                    db_query($sql);
                }
            }
        }

        // Fix any records somehow stuck in PROCESSING state
        if (is_array($this->datediff_cron_queued_records)) {
            foreach ($this->datediff_cron_queued_records as $project_id=>$recordKeys)
            {
                $recordBatches = array_chunk(array_keys($recordKeys), Jobs::RECORD_EVAL_LIMIT_PER_QUERY, true);
                foreach ($recordBatches as $recordBatch) {
                    $sql = "update redcap_crons_datediff set alert_status = null
                            where project_id = $project_id and record in (" . prep_implode($recordBatch) . ") and alert_status = 'PROCESSING'";
                    db_query($sql);
                }
            }
        }

        return $records_affected;
    }

    // Re-evaluate all alerts in a project
    public function reevalAlerts($action)
    {
        global $lang;
        if ($action == 'save') {
            $alert_ids = explode(",", $_POST['alert_ids']);
            $is_dry_run = $_POST['is_dry_run'] === '1';
            if ($is_dry_run) {
                db_query("SET AUTOCOMMIT=0");
                db_query("BEGIN");
                try {
                    $records_affected = $this->checkAlertsBulk(PROJECT_ID, false, $alert_ids, $is_dry_run);
                    $numInvitationsScheduled = isset($records_affected[PROJECT_ID]['sent_or_scheduled']) ? count($records_affected[PROJECT_ID]['sent_or_scheduled']) : 0;
                    $numInvitationsDeleted = isset($records_affected[PROJECT_ID]['removed']) ? count($records_affected[PROJECT_ID]['removed']): 0;
                    $numRecordsAffected = $numInvitationsScheduled + $numInvitationsDeleted;
                } catch (\Exception $e) {
                    // safety catch block to prevent exit without explicit rollback
                }
                db_query("ROLLBACK");
                db_query("SET AUTOCOMMIT=1");
                // now throw exception that would have been thrown
                if (isset($e)) {
                    throw $e;
                }
            } else {
                $records_affected = $this->checkAlertsBulk(PROJECT_ID, false, $alert_ids, $is_dry_run);
                $numInvitationsScheduled = isset($records_affected[PROJECT_ID]['sent_or_scheduled']) ? count($records_affected[PROJECT_ID]['sent_or_scheduled']) : 0;
                $numInvitationsDeleted = isset($records_affected[PROJECT_ID]['removed']) ? count($records_affected[PROJECT_ID]['removed']): 0;
                $numRecordsAffected = $numInvitationsScheduled + $numInvitationsDeleted;
            }
            $attr = $is_dry_run ? array('style' => 'color:#9c2626b3') : array('class'=>'text-success');
            if ($numRecordsAffected > 0) {
                $msg =	RCView::div($attr,
                            (!$is_dry_run ? RCView::b('<i class="fas fa-check"></i> '.$lang['global_79']) : RCView::b('<i class="fa-solid fa-circle-info"></i> '.$lang['alerts_410'])) . "<br>$numInvitationsScheduled " . $lang['alerts_255'] .
                            " $numInvitationsDeleted " . $lang['alerts_256'] . RCView::b(" $numRecordsAffected " . $lang['data_entry_173']) . $lang['period']
                        );
                $msglog = "$numInvitationsScheduled " . $lang['alerts_255'] . " $numInvitationsDeleted " . $lang['alerts_256'] . " $numRecordsAffected " . $lang['data_entry_173'] . $lang['period'];
            } else {
                $msg =	RCView::div($attr,
                            (!$is_dry_run ? '<i class="fas fa-check"></i> ' : '').$lang['alerts_254']
                        );
                $msglog = $lang['alerts_257'];
            }
            // Add CSV download of affected record names
            if ($numRecordsAffected > 0)
            {
                // Prep array for CSV
                $csvArray = [];
                foreach ($records_affected[PROJECT_ID]['sent_or_scheduled']??[] as $key=>$thisrecord) {
                    $csvArray[$key]['sent_or_scheduled'] = $thisrecord;
                }
                foreach ($records_affected[PROJECT_ID]['removed']??[] as $key=>$thisrecord) {
                    $csvArray[$key]['unscheduled'] = $thisrecord;
                }
                // Store CSV file of record names in edocs with 60 minute expiration (we don't want to keep in temp)
                $csvFilename = APP_PATH_TEMP . date('YmdHis') . "_pid_".PROJECT_ID."_alert_reeval_" . substr(sha1(rand()), 0, 6) . ".csv";
                file_put_contents($csvFilename, arrayToCsv($csvArray, true, User::getCsvDelimiter()));
                $doc_id = REDCap::storeFile($csvFilename, PROJECT_ID);
                unlink($csvFilename);
                // Set file to auto-delete in 60 minutes
                $sql = "update redcap_edocs_metadata set delete_date = ? where doc_id = ?";
                db_query($sql, [date("YmdHis", mktime(date("H")+1,date("i"),date("s"),date("m"),date("d"),date("Y"))), $doc_id]);
                // Create download link
                $msg .= RCView::button(['class'=>'btn btn-defaultrc btn-xs fs14 mt-3', 'onclick'=>"window.location.href='".Files::getDownloadLink($doc_id, PROJECT_ID)."';"],
                    RCView::img(['src'=>'xls.gif', 'style'=>'position:relative;top:3px;vertical-align:initial;']) . " " . RCView::tt('alerts_409')
                );
            }
            // Logging
            if (!$is_dry_run) {
                $alert_nums = array();
                foreach ($alert_ids as $alert_id) {
                    $alert_num = $this->getKeyIdFromAlertId(PROJECT_ID, $alert_id);
                    if (!isinteger($alert_num)) continue;
                    $alert_nums[] = $alert_num+1;
                }
                Logging::logEvent("", "redcap_alerts", "MANAGE", PROJECT_ID, "Re-evaluate alert #".implode(", #", $alert_nums).":\n".strip_tags($msglog), "Re-evaluate alerts");
            }
            // Output message
            print RCView::h1([], $lang['alerts_404']). $msg;
        } elseif ($action == 'view') {
            print $this->displayAlertCheckboxList(PROJECT_ID);
        } else {
            print '0';
        }
    }

    // Function called by the CRON to send the scheduled or recurring alerts
    public function sendNotificationsViaCron()
    {
        // First, deactivate any alerts that are expiring right now
        $sql = "select project_id, alert_id, email_deleted from redcap_alerts 
                where alert_expiration is not null and alert_expiration <= '" . NOW . "'";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q))
        {
            $sql = "update redcap_alerts set email_deleted = 1, alert_expiration = null where alert_id = " . $row['alert_id'];
            db_query($sql);
            // Log it
            if (!$row['email_deleted']) {
                $index = $this->getKeyIdFromAlertId($row['project_id'], $row['alert_id']) + 1;
                Logging::logEvent($sql, "redcap_alerts", "MANAGE", $index,"Alert #{$index} (".self::ALERT_UNIQUE_ID_PREFIX."{$row['alert_id']})", "Expire and deactivate alert", "",
                    "SYSTEM", $row['project_id']);
            }
        }

        // Second, if any alerts have been stuck in SENDING status for more than one hour (which means they likely won't ever send), then set them back to IDLE.
        $oneHourAgo = date("Y-m-d H:i:s", mktime(date("H")-1,date("i"),date("s"),date("m"),date("d"),date("Y")));
        $sql = "update redcap_alerts_recurrence set status = 'IDLE', next_send_time = null 
                where next_send_time is not null and next_send_time <= '$oneHourAgo' and status = 'SENDING'";
        db_query($sql);

        // Set notifications with SENDING status if they should be sent right now
        $aq_ids = array();
        $sql = "select r.aq_id from redcap_alerts a, redcap_alerts_recurrence r, redcap_projects p
                where a.alert_id = r.alert_id and a.email_deleted = 0 and p.status <= 1 and p.date_deleted is null and p.completed_time is null and p.project_id = a.project_id
                and a.cron_send_email_on = r.send_option and r.status = 'IDLE'
                and ((a.cron_repeat_for > 0 and (a.cron_repeat_for_max is null or r.times_sent < (a.cron_repeat_for_max - if(r.send_option != 'now',0,1)))) or (a.cron_repeat_for = 0 and r.times_sent = 0))
                and DATE_ADD(r.first_send_time, INTERVAL ((r.times_sent + if(r.send_option != 'now',0,1))*a.cron_repeat_for*(if(a.cron_repeat_for_units = 'DAYS', 1440, if(a.cron_repeat_for_units = 'HOURS', 60, 1)))) MINUTE) <= '" . NOW . "'
                limit " . SurveyScheduler::determineEmailsPerBatch();
        $q = db_query($sql);
        if (db_num_rows($q) > 0) {
            ## Get all aq_id's and put in array
            while ($row = db_fetch_assoc($q)) {
                // Set the aq_id's status as SENDING
                // (Don't change status unless still IDLE in case other simultaneous cron isn't lagging behind with the SELECT query above)
                $sql = "update redcap_alerts a, redcap_alerts_recurrence r set 
                        r.status = 'SENDING',
                        r.next_send_time = DATE_ADD(r.first_send_time, INTERVAL ((r.times_sent + if(r.send_option != 'now',0,1))*a.cron_repeat_for*(if(a.cron_repeat_for_units = 'DAYS', 1440, if(a.cron_repeat_for_units = 'HOURS', 60, 1)))) MINUTE)
                        where a.alert_id = r.alert_id and r.aq_id = {$row['aq_id']} and r.status = 'IDLE'";
                db_query($sql);
                // If already set as SENDING, then skip it here because another cron must've picked it up
                if (db_affected_rows() == 0) continue;
                // Add ssq_id's to array
                $aq_ids[] = $row['aq_id'];
            }
        }
        // SEND NOTIFICATIONS
        // Initialize counter of number of notification sent
        $numSent = $numFailed = 0;
        if (empty($aq_ids)) return array($numSent, $numFailed);
        // Now loop though all aq_id's with status of SENDING and send notification for each
        $sql = "select r.aq_id, a.alert_id, a.project_id, r.record, r.event_id, r.instrument, r.instance,
                a.cron_repeat_for, r.times_sent, a.alert_condition, a.ensure_logic_still_true, a.do_not_clear_recurrences
                from redcap_alerts a, redcap_alerts_recurrence r
                where a.alert_id = r.alert_id and r.next_send_time is not null
                and r.aq_id in (" . prep_implode($aq_ids) . ")
                order by r.next_send_time";
        $q = db_query($sql);
        // Loop through all notification to be sent and then send them
        while ($row = db_fetch_assoc($q))
        {
            // Double check one last time that the notification has not already been sent (just in case a lagging simultaneous cron just sent it).
            // If not in SENDING state, then skip this invitation and move to next loop.
            $sql = "select 1 from redcap_alerts_recurrence where aq_id = {$row['aq_id']} and next_send_time is not null";
            $q1 = db_query($sql);
            if (db_num_rows($q1) < 1) continue;

            // Has conditional logic?
            $triggerOnLogic = ($row['alert_condition'] != '');
            $ensureLogicStillTrue = ($triggerOnLogic && $row['ensure_logic_still_true']);
            $doNotClearRecurrences = ($triggerOnLogic && $row['do_not_clear_recurrences']);
            // Trigger it based on logic?
            if ($triggerOnLogic) {
                // Is this a repeating form/event?
                $Proj = new Project($row['project_id']);
                $isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($row['event_id'], $row['instrument']);
                // Check logic
                if ($isRepeatingFormOrEvent) {
                    $passedLogicTest = REDCap::evaluateLogic($row['alert_condition'], $row['project_id'], $row['record'], $row['event_id'], $row['instance'], $row['instrument']);
                } else {
                    $passedLogicTest = REDCap::evaluateLogic($row['alert_condition'], $row['project_id'], $row['record'], $row['event_id']);
                }
                // If failed logic and has "ensure logic still true" enabled and already exists in recurrence table, then remove from table
                if (!$passedLogicTest && $ensureLogicStillTrue) {
                    if (!$doNotClearRecurrences) {
                        $this->deleteRecurrence($row['alert_id'], $row['record'], $row['event_id'], $row['instrument'], $row['instance']);
                    }
                    else {
                        // Update status to IDLE
                        $sql = "UPDATE redcap_alerts_recurrence SET `status` = 'IDLE', next_send_time = NULL
                                WHERE aq_id = ?";
                        $q2 = db_query($sql, [$row['aq_id']]);
                    }
                    // Stop loop here to go to next record
                    continue;
                }
            }

            // Send notification
            $sent = $this->sendNotification($row['alert_id'], $row['project_id'], $row['record'], $row['event_id'], $row['instrument'], $row['instance']);
            if ($sent === null) {
                // If email failed to send due to a connection with a Web Email API, skip this to re-queue it to be picked up later to be sent.
                $sql = "update redcap_alerts_recurrence set status = 'IDLE', next_send_time = NULL
                        where aq_id = {$row['aq_id']}";
            } else {
                // If this is a one-time notification (no recurrence), then delete from recurrence table
                $deleteFromTable = ($row['cron_repeat_for'] == '0' && $row['times_sent'] == '0');
                // Successfully sent: Do we advance the recurrence schedule or remove it?
                if ($deleteFromTable) {
                    $sql = "delete from redcap_alerts_recurrence where aq_id = {$row['aq_id']}";
                } else {
                    $sql = "update redcap_alerts_recurrence 
                            set status = 'IDLE', next_send_time = NULL, times_sent = times_sent+1, last_sent = '".NOW."'
                            where aq_id = {$row['aq_id']}";
                    $numSent++;
                }
            }
            db_query($sql);
        }
        // Free up memory
        db_free_result($q);
        unset($aq_ids);
        // Return count of successes and failures
        return array($numSent, $numFailed);
    }

    private function deleteRecurrence($alert_id, $record, $event_id, $instrument="", $repeat_instance=1)
    {
        if ($alert_id == '' || $record == '' || $event_id == '') return 0;
        $select = "SELECT COUNT(aq_id) AS count ";
        $delete = "DELETE ";
        $sql = "FROM redcap_alerts_recurrence 
                WHERE alert_id = ".checkNull($alert_id)." AND record = ".checkNull($record)." AND event_id = ".checkNull($event_id)." 
                AND instrument = '".db_escape($instrument)."' AND instance = ".checkNull($repeat_instance);
        // Get affected
        $q = db_query($select.$sql);
        $count = db_fetch_assoc($q)["count"];
        if ($count > 0) {
            // Delete
            db_query($delete.$sql);
        }
        return $count * 1;
    }

    // Creates a new recurring notification
    function createRecurrence($alert_id, $project_id, $record, $event_id, $instrument, $instance, $times_sent=0, $last_sent='', $current_instrument='')
    {
        if ($alert_id == '' || $record == '' || $event_id == '') return false;
        $alert = $this->getKeyIdFromAlertId($project_id, $alert_id);
        $cron_send_email_on = $this->getAlertSetting("cron-send-email-on", $project_id)[$alert];
        // Prevent some NULLs so we can enforce unique keys properly
        if (!is_numeric($instance)) $instance = 1;
        if ($instrument === null) $instrument = '';
        // Determine when to send the first notification of this recurrence (all repetitions will be based on this)
        $first_send_time = $this->calculateNotificationFirstSendTime($project_id, $alert_id, $record, $event_id, $instrument, $instance, $current_instrument);
        if ($first_send_time === false) return false;
        // Add to table
        $sql = "insert into redcap_alerts_recurrence (alert_id, record, event_id, instrument, instance, creation_date, first_send_time, send_option, times_sent, last_sent) 
                values (".checkNull($alert_id).", ".checkNull($record).", ".checkNull($event_id).", '".db_escape($instrument)."', '".db_escape($instance)."', 
                ".checkNull(NOW).", ".checkNull($first_send_time).", ".checkNull($cron_send_email_on).", ".checkNull($times_sent).", ".checkNull($last_sent).")";
        $q = db_query($sql);
        if ($q) {
            return db_insert_id();
        }
        return false;
    }


    // Calculate the date/time when the survey invitation should be send to this participant
    private function calculateNotificationFirstSendTime($project_id, $alert_id, $record, $event_id, $instrument, $instance, $current_instrument='')
    {
        $alert = $this->getKeyIdFromAlertId($project_id, $alert_id);
        $cron_send_email_on = $this->getAlertSetting("cron-send-email-on", $project_id)[$alert];

        // SEND AT EXACT TIME
        if ($cron_send_email_on == 'date')
        {
            // Set invitation time as the "exact date/time" specified
            $invitationTime = $this->getAlertSetting("cron_send_email_on_date", $project_id)[$alert];
        }

        // IMMEDIATELY SEND
        elseif ($cron_send_email_on == 'now')
        {
            // Set invitation time as current time right now
            $invitationTime = NOW;
        }

        // SEND AFTER SPECIFIED LAPSE OF TIME
        elseif ($cron_send_email_on == 'time_lag')
        {
            // Get temporal components
            $days = $this->getAlertSetting("cron_send_email_on_time_lag_days", $project_id)[$alert];
            $hours = $this->getAlertSetting("cron_send_email_on_time_lag_hours", $project_id)[$alert];
            $minutes = $this->getAlertSetting("cron_send_email_on_time_lag_minutes", $project_id)[$alert];
            if ($days == '') $days = 0;
            if ($hours == '') $hours = 0;
            if ($minutes == '') $minutes = 0;
            // If using datetime field for time lag, get the field and its value
            $dataField = $this->getAlertSetting("cron_send_email_on_field", $project_id)[$alert];
            $beforeAfter = $this->getAlertSetting("cron_send_email_on_field_after", $project_id)[$alert];
            // Is this a same-day-at-midnight invitation time?
            $sameDayMidnight = ($dataField != '' && strpos($dataField, "[survey-date-completed:") === 0);
            // Is this based off a date or date/time field?
			if ($dataField != '' && !$sameDayMidnight) {
                // Format the field logic to prep for piping
                $Proj = new Project($project_id);
                if ($Proj->longitudinal) $dataField = LogicTester::logicPrependEventName($dataField, 'event-name', $Proj);
                $dataField = LogicTester::logicAppendCurrentInstance($dataField, $Proj, $event_id);
                $dataValue = trim(Piping::replaceVariablesInLabel($dataField, $record, $event_id, $instance, array(), false, $project_id, false, $instrument, 1, false, false, $current_instrument, null, true, false, false, false, true));
                // Make sure the date value is not a missing data code
                $missingDataCodes = parseEnum($Proj->project['missing_data_codes']);
                $dataIsMissingCode = (!empty($missingDataCodes) && in_array($dataValue, $missingDataCodes));
                // Don't schedule this alert if we don't have a valid value
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
                // Calculate invitation time by adding time lag to current time
                $invitationTime = date_mktime("Y-m-d H:i:s", date("H")+$hours,date("i")+$minutes,date("s"),date("m"),date("d")+$days,date("Y"));
            }
        }

        // SEND ON NEXT SPECIFIED DAY/TIME
        elseif ($cron_send_email_on == 'next_occurrence')
        {
            // Set time component of the timestamp
            $timeTS = $this->getAlertSetting("cron_send_email_on_next_time", $project_id)[$alert];
            $condition_send_next_day_type = $this->getAlertSetting("cron_send_email_on_next_day_type", $project_id)[$alert];
            // Set the date component of the timestamp
            // If day type is "WEEKEND DAY"
            if ($condition_send_next_day_type == 'WEEKENDDAY') {
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
                $dateTS = date('Y-m-d', strtotime('NEXT '.$condition_send_next_day_type));
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

    /**
     * Function that deletes a specific recurring notification
     */
    function deleteQueuedEmail($aq_id, $project_id){
        // Remove from table
        $aq_id = (int)$aq_id;
        $sql = "delete from redcap_alerts_recurrence where aq_id = ".$aq_id;
        if (db_query($sql)) {
            unset($this->alerts_queue[$project_id]);
            return true;
        } else {
            return false;
        }
    }

	// Sends a notification
	function sendNotification($alert_id, $project_id, $record, $event_id, $instrument, $instance=1, $data=array())
	{
		global $lang, $sendgrid_enabled_global, $sendgrid_display_info_project_setup, $sendgrid_enabled_by_super_users_only;
		// Get alert index id
		$Proj = new \Project($project_id);
		$id = $this->getKeyIdFromAlertId($project_id, $alert_id);
		// Get alert attributes
		$prevent_piping_identifiers = $this->getAlertSetting("prevent-piping-identifiers", $project_id)[$id];
		// Multi-Language Management - From Display Name, Subject, and Message might be translated
		$context = Context::Builder()
			->project_id($project_id)
			->alert_id($alert_id)
			->record($record)
			->event_id($event_id)
			->instance($instance)
			->instrument($instrument)
			->user_id(defined("USERID") ? USERID : null)
			->Build();
		$alert_translations = MultiLanguage::translateAlert($context);
		$mlm_message_lang = null;
		if (!empty($alert_translations)) {
			$fromDisplayName = $alert_translations["email_from_display"];
			$email_subject = $alert_translations["email_subject"];
			$alert_message = $alert_translations["alert_message"];
			$mlm_message_lang = $alert_translations["lang_id"];
			$mlm_rtl = $alert_translations["rtl"];
		}
		else {
			$fromDisplayName = trim($this->getAlertSetting("email-from-display", $project_id)[$id] ?? "");
			$email_subject = $this->getAlertSetting("email-subject", $project_id)[$id];
			$alert_message = $this->getAlertSetting("alert-message", $project_id)[$id];
			$mlm_rtl = false;
		}
		$alert_type = $this->getAlertSetting("alert-type", $project_id)[$id];
		// If Twilio is disabled at the system-level and also for alerts in this project. If not, reset alert type to EMAIL.
		if (!(($GLOBALS['twilio_enabled_global'] || $GLOBALS['mosio_enabled_global']) && $Proj->twilio_enabled_alerts) && $alert_type != "SENDGRID_TEMPLATE") $alert_type = 'EMAIL';
		// Set project and get data (if needed)
		$repeat_instrument = $Proj->isRepeatingForm($event_id, $instrument) ? $instrument : "";
		$isLongitudinal = $Proj->longitudinal;
		if (empty($data)) {
			$data = Records::getData($project_id, 'array', $record);
		}
		$alertSentSuccesfully = false; // default
		$alertInstrument = $this->getAlertSetting("form-name", $project_id)[$id];
		$alertEventId = $this->getAlertSetting("form-name-event", $project_id)[$id];
		if (($alertInstrument == '' || $alertEventId == '') && is_numeric($event_id)) $alertEventId = $event_id;
		if ($alertEventId == '') $alertEventId = $Proj->firstEventId;

        // Determine if we need to enforce protected email mode
        $enforceProtectedEmail = ($alert_type == 'EMAIL' && $Proj->project['protected_email_mode'] && ($Proj->project['protected_email_mode_trigger'] == 'ALL'
                                || ($Proj->project['protected_email_mode_trigger'] == 'PIPING' && !$prevent_piping_identifiers && containsIdentifierFields($alert_message, $Proj->project_id))));

		// Piping
		$alert_message = Piping::replaceVariablesInLabel($alert_message, $record, $event_id, $instance, $data,false,
							$project_id, false, $repeat_instrument, 1, false, false, $instrument, null, false, $prevent_piping_identifiers, true, false, false, $mlm_message_lang ?? false, true);
		$email_subject = Piping::replaceVariablesInLabel($email_subject, $record, $event_id, $instance, $data,false,
							$project_id, false, $repeat_instrument, 1, false, false, $instrument, null, false, $prevent_piping_identifiers, false, false, false, $mlm_message_lang ?? false, true);

		// Initialize values (even if we aren't sending via EMAIL)
		$mail = new Message($project_id, $record, $event_id, $instrument, $instance, $mlm_rtl);
		// Email Addresses
		$mail = $this->setEmailAddresses($mail, $project_id, $record, $event_id, $instrument, $instance, $id, $data, $alert_type);

        // Body and subject (will be picked up by later methods, even though we're not using emailing here)
        $mail->setBody($alert_message);
        $mail->setSubject($email_subject);

        // Is the recipient a survey participant?
        $email_to = $this->getAlertSetting("email-to", $project_id)[$id];
        $email_cc = $this->getAlertSetting("email-cc", $project_id)[$id];
        $email_bcc = $this->getAlertSetting("email-bcc", $project_id)[$id];
		$recipientIsSurveyParticipant = (strpos($email_to, self::participant_email_var) !== false ||
										strpos($email_cc ?? "", self::participant_email_var) !== false ||
										strpos($email_bcc ?? "", self::participant_email_var) !== false);

		if ($alert_type == 'EMAIL')
		{
			// Email From: Get the Reply-To and Display Name for this message
			$email_from = trim($this->getAlertSetting("email-from", $project_id)[$id]);
			if (!empty($email_from)) {
				if (!isEmail($email_from)) {
					$email_from = Piping::replaceVariablesInLabel($email_from, $record, $event_id, $instance, $data,false,
										$project_id, false, $repeat_instrument, 1, false, false, $instrument);
				}
				if (isEmail($email_from)) {
					// Set From and From Name
					$mail->setFrom($email_from);
					$mail->setFromName($fromDisplayName);
				} else {
					$this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id)[$id], $lang['alerts_55'], $lang['alerts_57']." (The \"From\" email address \"$email_from\" is not a valid email address - Project: $project_id, Record: $record, Alert #".($id+1).")");
				}
			} else {
				$this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id)[$id], $lang['alerts_56'], $lang['alerts_58']." (The \"From\" email address is missing - Project: $project_id, Record: $record, Alert #".($id+1).")");
			}
			// Attachments
			$mail = $this->setAttachments($mail, $project_id, $id);
			// Attchment from field variable
			$mail = $this->setAttachmentsREDCapVar($mail, $project_id, $data, $record, $event_id, $instrument, $instance, $id, $isLongitudinal);
		}
		// Get phone numbers if sending via SMS or VOICE CALL
		elseif ($alert_type == 'SMS' || $alert_type == 'VOICE_CALL')
		{
			$alertPhoneNumbersTo = array();
			// Gather all phone numbers to send the SMS to
			$phone_number_to = $this->getAlertSetting("phone_number_to", $project_id)[$id];
			foreach (explode(";", $phone_number_to) as $this_phone_number)
			{
				$this_phone_number = trim($this_phone_number);
				if ($this_phone_number == '') continue;
				// Replace participant phone variable
				if ($this_phone_number == self::participant_phone_var) {
					// Fetch email value
					$emailArray = Survey::getResponsesEmailsIdentifiers(array($record), $Proj->forms[$instrument]['survey_id'], $project_id);
					$participantPhone = isset($emailArray[$record]) ? $emailArray[$record]['phone'] : "";
					// Replace variable with email value
					$this_phone_number2 = str_replace(self::participant_phone_var, $participantPhone, $this_phone_number);
				} else {
					// If this is a variable, then replace it
					$this_phone_number2 = Piping::replaceVariablesInLabel($this_phone_number, $record, $event_id, $instance, $data,false,
											$project_id, false, $instrument, 1, false, false, $instrument, null, false);
				}
				// Remove all non-numerals
				$this_phone_number2 = preg_replace("/[^0-9]/", "", $this_phone_number2);
				if (isPhoneUS($this_phone_number2) && substr($this_phone_number2, 0, 1) != "1") $this_phone_number2 = "1".$this_phone_number2;
				// Add to array if not already in it
				if (!in_array($this_phone_number2, $alertPhoneNumbersTo)) {
					$alertPhoneNumbersTo[] = $this_phone_number2;
				}
			}
		}
        // SendGrid Dynamic Templates
        elseif ($alert_type == 'SENDGRID_TEMPLATE')
		{
			$from_email = $this->getAlertSetting("email-from", $project_id)[$id];
			$to = $mail->getTo();
			$cc = $mail->getCc();
			$bcc = $mail->getBcc();
			$dynamic_template_id = $this->getAlertSetting("sendgrid-template-id", $project_id)[$id] ?? "";
			$dynamic_template_data = json_decode($this->getAlertSetting("sendgrid-template-data", $project_id)[$id] ?? "[]", TRUE);
            $mail_send_configuration = json_decode($this->getAlertSetting("sendgrid-mail-send-configuration", $project_id)[$id] ?? "[]", TRUE);

			if (count($dynamic_template_data) > 0) {
				foreach ($dynamic_template_data as $key => $value) {
					if (!empty($alert_translations)) {
						$dynamic_template_data[$key] = Multilanguage::getDDTranslation($context, 'alert-sendgrid_template_data', $alert_id, $key, $value);
					}
					$dynamic_template_data[$key] = Piping::replaceVariablesInLabel($dynamic_template_data[$key], $record, $event_id, $instance, $data, false, $project_id, false, $repeat_instrument, 1, false, false, $instrument, null, false, $prevent_piping_identifiers, true);
				}
			} else {
				$dynamic_template_data = null;
			}
			$mail->setBody(json_encode($dynamic_template_data));
			$mail = $this->setAttachments($mail, $project_id, $id);
			$sendgrid_attachments = $mail->getAttachmentsWithNames();
			$mail->setFrom($from_email);
		}

		// Send as SMS
		if ($alert_type == 'SMS')
		{
			// Send SMS messages
			foreach ($alertPhoneNumbersTo as $this_phone_number)
			{
				// Send SMS to the phone number (using the project's specified messaging provider)
                $success = (new Messaging($project_id))->send($alert_message, $this_phone_number, 'sms', $record, 'ALERT', true);
				if ($success === true) $alertSentSuccesfully = true;
			}
		}

		// Send as VOICE CALL (Twilio only)
		elseif ($alert_type == 'VOICE_CALL' && $Proj->messaging_provider == Messaging::PROVIDER_TWILIO)
		{
			// Mark as sent (we will undo this later if the call fails for whatever reason) - for email and SMS, this is performed later
			list ($alert_sent_id, $alert_sent_log_id) = $this->addRecordSent($alert_id, $record, $alertEventId, ($alertInstrument == '' ? $repeat_instrument : $alertInstrument), $instance, $mail, $project_id);
			// Set the survey URL that Twilio will make the request to
			$twilio_url = APP_PATH_SURVEY_FULL . '?a=' . base64_encode(encrypt($alert_sent_log_id));
			// Call the phone numbers
			foreach ($alertPhoneNumbersTo as $this_phone_number)
			{
				try {
					// Create hash so that we can add it to callback url
					$callback_hash = generateRandomHash(50);
                    // Instantiate a client to Twilio's REST API
                    $twilioClient = new Services_Twilio($Proj->project['twilio_account_sid'], $Proj->project['twilio_auth_token']);
					$call = $twilioClient->account->calls->create(Messaging::formatNumber($Proj->project['twilio_from_number']), Messaging::formatNumber($this_phone_number), $twilio_url, array(
						"StatusCallback" => $twilio_url,
						"FallbackUrl" => APP_PATH_SURVEY_FULL . "?__sid_hash=$callback_hash&__error=1",
						"IfMachine"=>"Continue"
					));
					// Add the sid and sid_hash to the db table so that we can delete the log for this event once it has completed
					TwilioRC::addEraseCall($project_id, $call->sid, $callback_hash);
					$alertSentSuccesfully = true;
				} catch (Exception $e) {  }
			}
			// Undo these rows from db tables if call fails somehow
			if (!$alertSentSuccesfully && is_numeric($alert_sent_id))
			{
				$sql = "delete from redcap_alerts_sent where alert_sent_id = $alert_sent_id";
				db_query($sql);
			}
		}
		// Send as SENDGRID_TEMPLATE
		elseif ($alert_type == 'SENDGRID_TEMPLATE') {
			if (!($sendgrid_enabled_global && $Proj->project['sendgrid_enabled'])) {
				REDCap::logEvent("Skipped sending SendGrid Notification for Alert #{$alert_id},",
					"SendGrid is disabled globally or for the project", null, $record, $event_id, $project_id);
			} else {
				$emailCategory = 'ALERT';
				$api_key = SendGridRC::getAPIKeyByPid($project_id);
				$success = SendGridRC::sendDynamicTemplateEmail($api_key, $from_email, $to, $dynamic_template_id, $project_id, $record, $event_id, $instrument, $instance, $mlm_message_lang, $emailCategory, $cc, $bcc, $sendgrid_attachments, $dynamic_template_data, $mail_send_configuration);
				if ($success === true) $alertSentSuccesfully = true;
			}
		}
		// Send as EMAIL
		else
		{
			$alertSentSuccesfully = $mail->send(false, $recipientIsSurveyParticipant, $enforceProtectedEmail, 'ALERT', $mlm_message_lang);
		}

		// Delete any attachments stored temporarily in the Temp directory
		$this->deleteTempAttachments();

		if (!$alertSentSuccesfully && $mail->emailApiConnectionError)
		{
			// If email failed to send due to a connection with a Web Email API, skip this to re-queue it to be picked up later to be sent.
			// Return NULL to imply that it *might* have sent under normal conditions.
			$email_sent_ok = null;
		}
		elseif (!$alertSentSuccesfully)
		{
			// Failed to send
			if ($alert_type == 'EMAIL') {
			    $this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id)[$id],"Alert Error" ,"Alert error occurred in Project ".$project_id.", Record ".$record.", Alert #".($id+1)."<br>\nError: ".$mail->ErrorInfo);
			}
			$email_sent_ok = false;
		}
		else
		{
			$email_sent_ok = true;

			// Set last time sent and sent count in alerts table
			$sql = "update redcap_alerts 
					set email_timestamp_sent = '".date('Y-m-d H:i:s')."', email_sent = 1
					where alert_id = $alert_id";
			db_query($sql);

			// Mark as sent (but not for voice calls, which were added the table before the call was made)
			if ($alert_type != 'VOICE_CALL') {
				list ($alert_sent_id, $alert_sent_log_id) = $this->addRecordSent($alert_id, $record, $alertEventId, ($alertInstrument == '' ? $repeat_instrument : $alertInstrument), $instance, $mail, $project_id);
			}

			#Add some logs
			$email_list = array();
			foreach ($mail->getAllRecipientAddresses() as $email) {
				$email_list[] = $email;
			}

			// Log this alert being sent
			if ($alert_type == 'SENDGRID_TEMPLATE') {
				$changes_made = "From: '$from_email',\nTo: '$to',\nTemplate-ID: '$dynamic_template_id'";
			} elseif ($alert_type == 'VOICE_CALL') {
                $alertPhoneNumbersToFormatted = [];
                foreach ($alertPhoneNumbersTo as $key=>$num) $alertPhoneNumbersToFormatted[$key] = Messaging::formatNumber($num);
                $changes_made = "To: '".implode("; ", $alertPhoneNumbersToFormatted)."'";
            } elseif ($alert_type == 'SMS') {
                $alertPhoneNumbersToFormatted = [];
                foreach ($alertPhoneNumbersTo as $key=>$num) $alertPhoneNumbersToFormatted[$key] = Messaging::formatNumber($num);
                $changes_made = "To: '".implode("; ", $alertPhoneNumbersToFormatted)."',\nMessage: '".strip_tags($alert_message)."'";
            } else {
                $changes_made = "From: '".$mail->getFrom()."',\nTo: '".implode("; ", $email_list)."',\nSubject: '$email_subject',\nMessage: '".strip_tags($alert_message)."'";
            }
			$alert_number = $id+1;
			// \REDCap::logEvent($action_description, $changes_made, null, $record, $event_id, $project_id);
			Logging::logEvent($sql, "redcap_alerts", "UPDATE", $record,"Alert #{$alert_number} (".self::ALERT_UNIQUE_ID_PREFIX."$alert_id),\n$changes_made", "Send alert", "",
				(defined("USERID") ? USERID : "SYSTEM"), $project_id, true, $event_id, $instance);
		}

		// Return status
		return $email_sent_ok;
	}

    // Delete any attachments stored temporarily in the Temp directory
    private function deleteTempAttachments()
    {
        foreach ($this->alertAttachmentsToDelete as $file) unlink($file);
        $this->alertAttachmentsToDelete = array();
    }

    // Get the alert message sent from the alert log
    public function getAlertMessageByAlertSentLogId($alert_sent_log_id)
    {
        $sql = "select message from redcap_alerts_sent_log where alert_sent_log_id = $alert_sent_log_id";
        $q = db_query($sql);
        return db_result($q, 0);
    }

    // Get the project_id from the alert log id
    public function getAlertProjectIdByAlertSentLogId($alert_sent_log_id)
    {
        $sql = "select a.project_id from redcap_alerts_sent_log l, redcap_alerts_sent s, redcap_alerts a
                where l.alert_sent_log_id = $alert_sent_log_id and s.alert_sent_id = l.alert_sent_id and s.alert_id = a.alert_id";
        $q = db_query($sql);
        return db_result($q, 0);
    }

    /**
     * Function that adds the email addresses into the mail.
     * @param $mail
     * @param $project_id
     * @param $record
     * @param $event_id
     * @param $instrument
     * @param $instance
     * @param $data
     * @param $id
     * @param bool $isLongitudinal
     * @return mixed
     */
    function setEmailAddresses($mail, $project_id, $record, $event_id, $instrument, $instance, $id, $data=array(), $alert_type='EMAIL')
    {
        $Proj = new Project($project_id);

        $email_to = $this->getAlertSetting("email-to", $project_id)[$id] ?? "";
        $email_cc = $this->getAlertSetting("email-cc", $project_id)[$id] ?? "";
        $email_bcc = $this->getAlertSetting("email-bcc", $project_id)[$id] ?? "";

        // Replace participant email variable in to/cc/bcc
        $replace_participant_email =   (strpos($email_to, self::participant_email_var) !== false ||
                                        strpos($email_cc ?? "", self::participant_email_var) !== false ||
                                        strpos($email_bcc ?? "", self::participant_email_var) !== false);
        if ($replace_participant_email) {
            // Fetch email value
            $emailArray = Survey::getResponsesEmailsIdentifiers(array($record), (isset($Proj->forms[$instrument]['survey_id']) ? $Proj->forms[$instrument]['survey_id'] : null), $project_id);
            $participantEmail = isset($emailArray[$record]) ? $emailArray[$record]['email'] : "";
            // Replace variable with email value
            $email_to = str_replace(self::participant_email_var, $participantEmail, $email_to);
            $email_cc = str_replace(self::participant_email_var, $participantEmail, $email_cc);
            $email_bcc = str_replace(self::participant_email_var, $participantEmail, $email_bcc);
        }

        // Perform normal piping to replace field variables in to/cc/bcc
        $email_to = Piping::replaceVariablesInLabel($email_to, $record, $event_id, $instance, $data,false,
                        $project_id, false, $instrument, 1, false, false, $instrument);
        $email_cc = Piping::replaceVariablesInLabel($email_cc, $record, $event_id, $instance, $data,false,
                        $project_id, false, $instrument, 1, false, false, $instrument);
        $email_bcc = Piping::replaceVariablesInLabel($email_bcc, $record, $event_id, $instance, $data,false,
                        $project_id, false, $instrument, 1, false, false, $instrument);

        $email_to_ok = $this->check_email($email_to, $project_id, ($alert_type=='EMAIL'), $id);
        $email_cc_ok = $this->check_email($email_cc, $project_id, ($alert_type=='EMAIL'), $id);
        $email_bcc_ok = $this->check_email($email_bcc, $project_id, ($alert_type=='EMAIL'), $id);

        if(!empty($email_to_ok)) {
            foreach ($email_to_ok as $email) {
                $mail = $this->check_single_email($mail,$email, 'to', $project_id, ($alert_type=='EMAIL'), $id);
            }
        }

        if(!empty($email_cc_ok)){
            foreach ($email_cc_ok as $email) {
                $mail = $this->check_single_email($mail, $email, 'cc', $project_id, ($alert_type=='EMAIL'), $id);
            }
        }

        if(!empty($email_bcc_ok)){
            foreach ($email_bcc_ok as $email) {
                $mail = $this->check_single_email($mail,$email, 'bcc', $project_id, ($alert_type=='EMAIL'), $id);
            }
        }
        return $mail;
    }

    /**
     * Function that adds attachments into the mail
     * @param $mail
     * @param $project_id
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    function setAttachments($mail, $project_id, $id){
        for($i=1; $i<6 ; $i++){
            $edoc = $this->getAlertSetting("email-attachment".$i,$project_id)[$id];
            if(is_numeric($edoc)){
                $mail = $this->addNewAttachment($mail, $edoc, $project_id,'files',$id);
            }
        }
        return $mail;
    }

	/**
	 * Function that adds piped attachments into the mail
	 * @param $mail
	 * @param $project_id
	 * @param $data
	 * @param $record
	 * @param $event_id
	 * @param $instrument
	 * @param $repeat_instance
	 * @param $id
	 * @param bool $isLongitudinal
	 * @return mixed
	 * @throws \Exception
	 */
	function setAttachmentsREDCapVar($mail, $project_id, $data, $record, $event_id, $instrument, $repeat_instance, $id, $isLongitudinal=false)
	{
		$email_attachment_variable = trim($this->getAlertSetting("email-attachment-variable", $project_id)[$id] ?? "");
		$edocs = array();
		if (!empty($email_attachment_variable)) {
			$Proj = new Project($project_id);
			$var = preg_split("/[;,]+/", $email_attachment_variable);
			foreach ($var as $attachment) {
				if (\LogicTester::isValid(trim($attachment))) {
					if ($isLongitudinal) {
						$attachment = LogicTester::logicPrependEventName($attachment, $Proj->getUniqueEventNames($event_id), $Proj);
					}
					if ($Proj->hasRepeatingFormsEvents()) {
						$attachment = LogicTester::logicAppendInstance($attachment, $Proj, $event_id, $instrument, $repeat_instance);
					}
					$edoc = Piping::replaceVariablesInLabel($attachment, $record, $event_id, $repeat_instance, $data,false,
							$project_id, false, $instrument, 1, false, false, $instrument, null, false);
					$edoc = trim($edoc);
					if (is_numeric($edoc)) $edocs[] = $edoc;
				}
			}
		}
		if (!empty($edocs))  {
			$edocs = array_unique($edocs);
			foreach ($edocs as $edoc) {
				$this->addNewAttachment($mail, $edoc, $project_id, 'files', $id);
			}
		}
		return $mail;
	}

	// Check if email has been sent for this alert-record-instrument-etc.
	function alertAlreadySent($alert_id, $record, $event_id, $instrument, $instance)
	{
		$sql = "select 1 from redcap_alerts_sent where alert_id = '".db_escape($alert_id)."' and record = '".db_escape($record)."' 
				and event_id = '".db_escape($event_id)."' and instrument = '".db_escape($instrument)."' and instance = '".db_escape($instance)."'";
		$q = db_query($sql);
		return ($q && db_num_rows($q) == 1);
	}

	/**
	 * Function that if valid adds an email address to the mail
	 * @param $mail
	 * @param $email
	 * @param $option, if they are To or CC emails
	 * @param $project_id
	 * @return mixed
	 */
	function check_single_email($mail, $email, $option, $project_id, $sendFailedEmailOnFailure=true, $id=null)
	{
		global $lang;
		$email = trim($email);
		if (isEmail($email)) {
			if($option == "to"){
				$current = $mail->getTo();
				if ($current != '') $current .= ";";
				$mail->setTo($current.$email);
			}else if($option == "cc"){
				$current = $mail->getCc();
				if ($current != '') $current .= ";";
				$mail->setCc($current.$email);
			}else if($option == "bcc"){
				$current = $mail->getBcc();
				if ($current != '') $current .= ";";
				$mail->setBcc($current.$email);
			}
		} elseif ($sendFailedEmailOnFailure) {
			$this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id)[$id], $lang['alerts_55'], $lang['alerts_57']." ($email in Project: $project_id)");
		}
		return $mail;
	}

	/**
	 * Function to send an extra error email if there is a value in the configuration
	 * @param $emailFailed_var
	 * @param $subject
	 * @param $message
	 */
	function sendFailedEmailRecipient($emailFailed_var, $subject, $message)
	{
		global $project_contact_email;
		if (!empty($emailFailed_var))
		{
			## It's already an array, so don't convert
			if(is_array($emailFailed_var)) {
				$emailsFailed = $emailFailed_var;
			}
			else {
				$emailsFailed = preg_split("/[;,]+/", $emailFailed_var);
			}
			foreach ($emailsFailed as $failed) {
				REDCap::email(trim($failed), \Message::useDoNotReply($project_contact_email), $subject, $message);
			}
		}
	}

	/**
	 * Function that checks if the emails are valid and sends an error email in case there's an error
	 * @param $emails
	 * @param $project_id
	 * @return array|string
	 */
	function check_email($emails, $project_id, $sendFailedEmailOnFailure=true, $id=null)
	{
		global $lang;
		$email_list = array();
		$email_list_error = array();
		$emails = preg_split("/[;,]+/", $emails);
		foreach ($emails as $email){
			$email = trim($email);
			if(!empty($email)){
				if (isEmail($email)) {
					//VALID
					array_push($email_list,$email);
				}else{
					array_push($email_list_error,$email);

				}
			}
		}
		if ($sendFailedEmailOnFailure && !empty($email_list_error)) {
			$this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id)[$id], $lang['alerts_55'], $lang['alerts_57']." ($email in Project: $project_id)");
		}
		return $email_list;
	}

	/**
	 * Function that adds a ne attachment (file or image type) to the mail if the file exists in the DB and if it's no bigger than 3MB to send. Otherwise it sends an error email
	 * @param $mail
	 * @param $edoc
	 * @param $project_id
	 * @return mixed
	 */
	function addNewAttachment($mail, $edoc, $project_id, $type='files', $id=null)
	{
		global $edoc_storage_option, $lang;
		if (!empty($edoc))
		{
			list ($mimeType, $docName, $fileContent) = Files::getEdocContentsAttributes($edoc);
			if (strlen($fileContent) > (self::MAX_ATTACHMENT_SIZE_MB*1024*1024)) {
			   $this->sendFailedEmailRecipient($this->getAlertSetting('email-failed', $project_id)[$id], $lang['alerts_59'],
				   $lang['alerts_60']." ".self::MAX_ATTACHMENT_SIZE_MB." MB".$lang['period']." (Project: ".$project_id.")");
			} else {
				// Save file to TEMP to handle non-local storage types
				$filename = APP_PATH_TEMP . date('YmdHis') . "_alerts_pid" . $project_id . "_" . substr(sha1(rand()), 0, 6) . getFileExt($docName, true);
				if (file_put_contents($filename, $fileContent) !== false) {
                    // Add the attachment
                    $mail->setAttachment($filename, $docName);
                    $this->alertAttachmentsToDelete[] = $filename;
				}
			}
		}
		return $mail;
	}

	// Add this alert-record-instrument-etc to alerts_sent table
	function addRecordSent($alert_id, $record, $event_id, $instrument, $instance, $mailObject, $project_id)
	{
        global $lang;
		if (!is_numeric($instance)) $instance = 1;
		// Get from address (pull directly from alert definition in case using Universal FROM address)
		$id = $this->getKeyIdFromAlertId($project_id, $alert_id);
		$phone_number_to = trim($this->getAlertSetting("phone-number-to", $project_id)[$id] ?? "");
		$alert_type = trim($this->getAlertSetting("alert-type", $project_id)[$id]);
		$email_from = $mailObject->getFrom();
		// Obtain message settings to add
		$subject = $mailObject->getSubject();
		$message = $mailObject->getBody();
        if ($message === 'null') $message = "";
        if ($subject === $lang['survey_397']) $subject = "";
		$email_to = str_replace(array(" ",",",";"), array("",";","; "), $mailObject->getTo());
		$email_cc = str_replace(array(" ",",",";"), array("",";","; "), $mailObject->getCc() ?? "");
		$email_bcc = str_replace(array(" ",",",";"), array("",";","; "), $mailObject->getBcc() ?? "");
		$phone_number_to = str_replace(array(" ",",",";"), array("",";","; "), $phone_number_to);
		$attachment_names = array();
		foreach ($mailObject->getAttachments() as $attachment_key=>$this_attachment_path) {
			$attachment_names[] = $mailObject->attachmentsNames[$attachment_key];
		}
		$attachment_names = implode("; ", $attachment_names);
		// Add to tables
		$sql = "insert into redcap_alerts_sent (alert_id, record, event_id, instrument, instance, last_sent) values 
				('".db_escape($alert_id)."', '".db_escape($record)."', ".checkNull($event_id).", '".db_escape($instrument)."', 
				'".db_escape($instance)."', '".NOW."') 
				on duplicate key update alert_id = '".db_escape($alert_id)."', record = '".db_escape($record)."', 
				event_id = ".checkNull($event_id).", instrument = '".db_escape($instrument)."', instance = '".db_escape($instance)."', 
				last_sent = '".NOW."', alert_sent_id = LAST_INSERT_ID(alert_sent_id)";
		$q = db_query($sql);
		if ($q) {
			$alert_sent_id = db_insert_id();
			$sql = "insert into redcap_alerts_sent_log (alert_sent_id, time_sent, email_from, email_to, email_cc, email_bcc, subject, message, attachment_names, alert_type, phone_number_to) 
					values ($alert_sent_id, '".NOW."', ".checkNull($email_from).", ".checkNull($email_to).", ".checkNull($email_cc).", 
					".checkNull($email_bcc).", ".checkNull($subject).", ".checkNull($message).", ".checkNull($attachment_names).", ".checkNull($alert_type).", ".checkNull($phone_number_to).")";
			if (db_query($sql)) {
				$alert_sent_log_id = db_insert_id();
				return array($alert_sent_id, $alert_sent_log_id);
			}
		}
		return array(null, null);
	}

	// Find filename of edoc by doc_id
	public function getEdocNameById($edoc)
	{
		header('Content-type: application/json');
		echo json_encode(array(
			'edoc_id' => $edoc,
			'doc_name' => Files::getEdocName($edoc),
			'status' => 'success'
		));
	}

	// Check if an email address is acceptable regarding the "domain allowlist for user emails" (if enabled)
	public static function emailInDomainAllowlist($email='')
	{
		global $alerts_email_freeform_domain_allowlist;
		$email = trim($email);
		if ($alerts_email_freeform_domain_allowlist == '' || $email == '') return null;
		$email_domain_allowlist_array = explode("\n", str_replace("\r", "", $alerts_email_freeform_domain_allowlist));
		list ($emailFirstPart, $emailDomain) = explode('@', $email, 2);
		return (in_array($emailDomain, $email_domain_allowlist_array));
	}

	// Render the setup page
	public function renderSetup()
	{
		extract($GLOBALS);

		include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

		$projectData = $this->getAlertSettings(PROJECT_ID);
		$indexSubSet = count($projectData);

        $countDeactivated = 0;
        $countActivated = 0;
        foreach ($projectData as $attr) {
            if ($attr['email_deleted']) {
                $countDeactivated++;
            } else {
                $countActivated++;
            }
        }

		$hasRepeatingEvents = $Proj->hasRepeatingEvents();
		$hasRepeatingForms = $Proj->hasRepeatingForms();
		$hasRepeatingFormsOrEvents = ($hasRepeatingForms || $hasRepeatingEvents);

		// Set the "just once" option text depending on the project type
		$justOnceText = $lang['alerts_61'];

		// Get email addresses and names from table
		$fromEmails = array();
		foreach (User::getEmailAllProjectUsers(PROJECT_ID) as $thisEmail) {
			$fromEmails[$thisEmail] = $thisEmail;
		}
		if (SUPER_USER && !isset($fromEmails[$GLOBALS['user_email']])) {
			// If admin is not a user in the project, add their primary email to the drop-down
			$fromEmails[$GLOBALS['user_email']] = $GLOBALS['user_email'] . " " . $lang['leftparen'] . $GLOBALS['user_firstname'] . " " . $GLOBALS['user_lastname'] . $lang['rightparen'];
		}

		// Get user phone numbers
		$userPhones = array();
		foreach (User::getPhoneAllProjectUsers(PROJECT_ID, false, true) as $thisPhone=>$thisFirstLastName) {
			if (isPhoneUS($thisPhone) && substr($thisPhone, 0, 1) != "1") $thisPhone = "1".$thisPhone;
			$userPhones[$thisPhone] = formatPhone($thisPhone) . " " . $lang['leftparen'] . $thisFirstLastName . $lang['rightparen'];
		}
		if (SUPER_USER && !isset($userPhones[$GLOBALS['user_phone']]) && $GLOBALS['user_phone'] != '') {
			$thisPhone = preg_replace("/[^0-9]/", "", $GLOBALS['user_phone']);
			if (isPhoneUS($thisPhone) && substr($thisPhone, 0, 1) != "1") $thisPhone = "1".$thisPhone;
			// If admin is not a user in the project, add their primary email to the drop-down
			$userPhones[$thisPhone] = formatPhone($thisPhone) . " " . $lang['leftparen'] . $GLOBALS['user_firstname'] . " " . $GLOBALS['user_lastname'] . $lang['rightparen'];
		}
		if (SUPER_USER && !isset($userPhones[$GLOBALS['user_phone_sms']]) && $GLOBALS['user_phone_sms'] != '') {
			$thisPhone = preg_replace("/[^0-9]/", "", $GLOBALS['user_phone_sms']);
			if (isPhoneUS($thisPhone) && substr($thisPhone, 0, 1) != "1") $thisPhone = "1".$thisPhone;
			// If admin is not a user in the project, add their primary email to the drop-down
			$userPhones[$thisPhone] = formatPhone($thisPhone) . " " . $lang['leftparen'] . $GLOBALS['user_firstname'] . " " . $GLOBALS['user_lastname'] . $lang['rightparen'];
		}
		ksort($userPhones);

		// Set DD options for all File Upload fields (across all events)
		$fileFieldLabelMaxLength = $Proj->longitudinal ? 35 : 55;
		$fieldUploadFieldOptions = $fieldUploadFieldOptionsEvents = array();
		foreach ($Proj->metadata as $this_field=>$attr1) {
			if ($attr1['element_type'] != 'file') continue;
			// Clean the label
			$attr1['element_label'] = trim(str_replace(array("\r\n", "\n", "&nbsp;"), array(" ", " ", " "), strip_tags($attr1['element_label'])));
			// Truncate label if long
			if (strlen($attr1['element_label']) > $fileFieldLabelMaxLength) {
				$attr1['element_label'] = trim(mb_substr($attr1['element_label'], 0, ($fileFieldLabelMaxLength-18))) . "... " . trim(mb_substr($attr1['element_label'], -15));
			}
			$fieldUploadFieldOptions["[$this_field]"] = "[$this_field] \"{$attr1['element_label']}\"";
			if ($Proj->longitudinal) {
				$fieldUploadFieldOptions["[$this_field]"] .= " [Current Event]";
				foreach ($Proj->eventsForms as $thisEventId=>$theseForms) {
					$thisEventName = $Proj->getUniqueEventNames($thisEventId);
					$thisForm = $Proj->metadata[$this_field]['form_name'];
					if (in_array($thisForm, $theseForms)) {
						$fieldUploadFieldOptionsEvents["[$thisEventName][$this_field]"] = "[$thisEventName][$this_field] \"{$attr1['element_label']}\" (".$Proj->eventInfo[$thisEventId]['name_ext'].")";
					}
				}
			}
		}
		$fieldUploadFieldOptions = $fieldUploadFieldOptions + $fieldUploadFieldOptionsEvents;

		// Set the To phone numbers as the projects users + survey participant
		$toPhones = array();
		$ddProjectUserLabel = $lang['alerts_66'];
		$ddProjectVarLabel1 = (!$alerts_allow_phone_variables && SUPER_USER) ? $lang['alerts_65'] : "";
		$ddProjectFreeformLabel = (!$alerts_allow_phone_freeform && SUPER_USER) ? " ".$lang['alerts_65'] : "";
		$ddProjectVarLabel = "-- {$lang['alerts_206']} $ddProjectVarLabel1 --";
		if (!empty($Proj->surveys)) {
			$toPhones[self::participant_phone_var] = $lang['alerts_67'];
			$ddProjectUserLabel = $lang['alerts_68'];
		}
		foreach ($userPhones as $thisUserPhone=>$thisUserPhoneDisplay) {
			$toPhones[$ddProjectUserLabel][$thisUserPhone] = $thisUserPhoneDisplay;
		}
		// Add email-validated fields to multi-select fields
		if ($alerts_allow_phone_variables || SUPER_USER)
		{
			// Gather all phone validation types + integer validation
			$valTypes = getValTypes();
			$valTypesPhoneInteger = array('int');
			foreach ($valTypes as $valName=>$valType) {
				if ($valType['data_type'] == 'phone') {
					$valTypesPhoneInteger[] = $valName;
				}
			}
			// Get all phone and integer fields
			$phoneFieldsLabels = Form::getFieldDropdownOptions(false, false, false, false, $valTypesPhoneInteger);
			if (!empty($phoneFieldsLabels)) {
				foreach ($phoneFieldsLabels as $formLabel=>$thesePhoneFields) {
					if (!is_array($thesePhoneFields)) continue;
					foreach ($thesePhoneFields as $thisVar=>$thisOptionLabel) {
						list ($thisVarLabel, $thisOptionLabel) = explode(" ", $thisOptionLabel, 2);
						if ($longitudinal) {
							// $toPhones[$ddProjectVarLabel]["[$thisVar]"] = "[$thisVar] $thisOptionLabel ".$lang['alerts_70'];
							foreach ($Proj->eventsForms as $thisEventId=>$theseForms) {
								$thisEventName = $Proj->getUniqueEventNames($thisEventId);
								$thisForm = $Proj->metadata[$thisVar]['form_name'];
								if (in_array($thisForm, $theseForms)) {
									$toPhones[$ddProjectVarLabel]["[$thisEventName][$thisVar]"] = "[$thisEventName][$thisVar] $thisOptionLabel (".$Proj->eventInfo[$thisEventId]['name_ext'].")";
								}
							}
						} else {
							$toPhones[$ddProjectVarLabel]["[$thisVar]"] = "[$thisVar] $thisOptionLabel";
						}
					}
				}
			}
		}

		// Set the To email addresses as the projects users + survey participant
		$toEmails = array();
		$ddProjectUserLabel = $lang['alerts_66'];
		$ddProjectVarLabel1 = (!$alerts_allow_email_variables && SUPER_USER) ? $lang['alerts_65'] : "";
		$ddProjectFreeformLabel = (!$alerts_allow_email_freeform && SUPER_USER) ? " ".$lang['alerts_65'] : "";
		$ddProjectVarLabel = "-- {$lang['alerts_69']} $ddProjectVarLabel1 --";
		if (!empty($Proj->surveys)) {
			$toEmails[self::participant_email_var] = $lang['alerts_67'];
			$ddProjectUserLabel = $lang['alerts_68'];
		}
		foreach ($fromEmails as $thisFromEmail=>$thisFromEmailLabel) {
			$toEmails[$ddProjectUserLabel][$thisFromEmail] = $thisFromEmailLabel;
		}
		// Add email-validated fields to multi-select fields
		if ($alerts_allow_email_variables || SUPER_USER)
		{
			// Get data types of all field validations for ONLY Email fields
			$validationDataTypes = array('email');
			foreach (getValTypes() as $valType=>$valAttr)  {
				if ($valAttr['data_type'] == 'email') {
					$validationDataTypes[] = $valType;
				}
			}
			$validationDataTypes = array_unique($validationDataTypes);
			$emailFieldsLabels = Form::getFieldDropdownOptions(false, false, false, false, $validationDataTypes);
			if (!empty($emailFieldsLabels)) {
				foreach ($emailFieldsLabels as $formLabel=>$emailFields) {
					if (!is_array($emailFields)) continue;
					foreach ($emailFields as $thisVar=>$thisOptionLabel) {
						list ($thisVarLabel, $thisOptionLabel) = explode(" ", $thisOptionLabel, 2);
						if ($longitudinal) {
							$toEmails[$ddProjectVarLabel]["[$thisVar]"] = "[$thisVar] $thisOptionLabel ".$lang['alerts_411'];
							foreach ($Proj->eventsForms as $thisEventId=>$theseForms) {
								$thisEventName = $Proj->getUniqueEventNames($thisEventId);
								$thisForm = $Proj->metadata[$thisVar]['form_name'];
								if (in_array($thisForm, $theseForms)) {
									$toEmails[$ddProjectVarLabel]["[$thisEventName][$thisVar]"] = "[$thisEventName][$thisVar] $thisOptionLabel (".$Proj->eventInfo[$thisEventId]['name_ext'].")";
								}
							}
						} else {
							$toEmails[$ddProjectVarLabel]["[$thisVar]"] = "[$thisVar] $thisOptionLabel";
						}
					}
				}
			}
		}

		$message="";
		$message_text = array(
			//'C'=>'<b>Success!</b> The alert and its settings have been saved.',
			'A'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_71'],
			'U'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_72'],
			'P'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_73'],
			'D'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_74'],
			'B'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_75'],
			'R'=>'<b>'.$lang['api_docs_010'].'</b> '.$lang['alerts_76']
		);

		if (array_key_exists('message', $_REQUEST)){
			$message = $message_text[$_REQUEST['message']];
		}

		// HTML for form-event drop-down list
		$formAnyEventDropdownOptions = array('-'=>$lang['alerts_196']);
		$formEventDropdownOptions = array();
		foreach ($Proj->eventsForms as $this_event_id=>$these_forms)
		{
			foreach ($these_forms as $this_form)
			{
				if ($longitudinal) {
					if (!isset($formEventDropdownOptions["$this_form-"])) {
						$formAnyEventDropdownOptions["[Any event]"]["$this_form-"] = "\"{$Proj->forms[$this_form]['menu']}\" [Any event]";
					}
					$thisEvent = $Proj->eventInfo[$this_event_id]['name_ext'];
					$formEventDropdownOptions["$thisEvent"]["$this_form-$this_event_id"] = "\"{$Proj->forms[$this_form]['menu']}\" ($thisEvent)";
				} else {
					$formEventDropdownOptions["$this_form-$this_event_id"] = "\"{$Proj->forms[$this_form]['menu']}\"";
				}
			}
		}

		loadJS('Alerts.js');
		?>
		<link rel="stylesheet" type="text/css" href="<?php echo APP_PATH_CSS ?>Alerts.css" media="screen,print">
		<script type="text/javascript">
			var message = <?=json_encode($message)?>;
			var indexSubSet = <?=json_encode($indexSubSet)?>;
			var alerts_email_freeform_domain_allowlist = new Array(<?php echo ($alerts_email_freeform_domain_allowlist == '' ? '' : prep_implode(explode("\n", strtolower(str_replace("\r", "", $alerts_email_freeform_domain_allowlist))))) ?>);
			var sendgrid_alerts_email_domain_allowlist = [];
			var sendgrid_alerts_email_allowlist = [];
			var sendgrid_from_emails = [];
			var sendgrid_template_ids = [];
			var project_alert_data = <?php echo json_encode($projectData) ?>;
		    var pleaseSelectAlert = '<?php echo js_escape($lang['alerts_281']) ?>';
		    var uniqueIdPrefix = '<?=self::ALERT_UNIQUE_ID_PREFIX?>';
		</script>
		<?php
		// Add language used to Alerts.js
		addLangToJS(array(
			'alerts_24',
			'alerts_36',
			'alerts_37',
			'alerts_38',
			'alerts_39',
			'alerts_40',
			'alerts_41',
			'alerts_42',
			'alerts_43',
			'alerts_44',
			'alerts_45',
			'alerts_46',
			'alerts_47',
			'alerts_48',
			'alerts_49',
			'alerts_50',
			'alerts_51',
			'alerts_52',
			'alerts_53',
			'alerts_54',
			'alerts_197',
			'alerts_198',
			'alerts_214',
			'alerts_251',
			'alerts_252',
			'alerts_267',
			'alerts_309',
			'alerts_311',
			'alerts_325',
			'alerts_326',
			'alerts_327',
			'alerts_332',
			'alerts_333',
			'alerts_340',
			'alerts_342',
			'alerts_343',
			'alerts_351',
			'alerts_397',
			'alerts_398',
			'data_entry_64',
			'data_import_tool_99',
			'design_128',
			'docs_72',
			'global_01',
			'global_19',
			'global_27',
			'global_48',
			'period',
			'survey_1237',
			'training_res_46',
		));

		$tr_class = 'in';
		if ($indexSubSet > 0) {
			//collapse columns as there is some existing info
			$tr_class = '';
		}
		// Set defaults for new alerts
		$alertDefaults = $this->getAlertDefaults();
		$alertDefaults['alert-stop-type'] = 'RECORD';
		$alertDefaults['email-from'] = $user_email;
		$alertDefaults['email-incomplete'] = '1';
		?>

		<div class="projhdr"><i class="fas fa-bell"></i> <?=$lang['global_154']?></div>
		<div style="width:950px;max-width:950px;" class="d-none d-md-block mt-3 mb-2">
			<?=$lang['alerts_265']?>
			<a href='javascript:;' style='text-decoration:underline;' onclick="$(this).remove();$('.alert-instructions-more').addClass('d-md-block');"><?=$lang['alerts_32']?></a>
		</div>
		<div class="alert-instructions-more d-none mb-2" style="width:950px;max-width:950px;">
			<?=$lang['alerts_78']?>
		</div>
		<div class="alert-instructions-more d-none mb-2" style="width:950px;max-width:950px;">
			<?=$lang['alerts_79']?>
		</div>

		<div class="clearfix">
			<div id="sub-nav" class="d-none d-sm-block" style="margin:5px 0 15px;width:950px;">
				<ul>
					<li<?php echo (!isset($_GET['log']) ? ' class="active"' : '') ?>>
						<a href="<?php echo APP_PATH_WEBROOT ?>index.php?pid=<?php echo $project_id ?>&route=AlertsController:setup" style="font-size:13px;color:#393733;padding:6px 12px 7px 13px;"><i class="fas fa-bell me-1"></i><?=$lang['alerts_81']?></a>
					</li>
					<li<?php echo (isset($_GET['log']) ? ' class="active"' : '') ?>>
						<a href="<?php echo APP_PATH_WEBROOT ?>index.php?pid=<?php echo $project_id ?>&route=AlertsController:setup&log=1" style="font-size:13px;color:#393733;padding:6px 12px 7px 13px;"><i class="fas fa-table me-1"></i><?=$lang['alerts_20']?></a>
					</li>
				</ul>
			</div>
		</div>

		<div class="modal fade" id="external-modules-configure-modal-preview" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true">
			<div class="modal-dialog" role="document" style="width: 800px">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close closeCustomModal btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						<h4 class="modal-title" id="myModalLabel">
							<span id="myModalLabelA"><?=$lang['alerts_82']?></span>
							<span id="myModalLabelB"><?=$lang['alerts_83']?></span>
							<span id="modalPreviewNumber"></span
							></h4>
					</div>
					<div class="modal-body">
						<div id="modal_message_preview"></div>
					</div>

					<div class="modal-footer">
						<button type="button" class="btn btn-defaultrc" data-bs-dismiss="modal"><?=$lang['calendar_popup_01']?></button>
					</div>
				</div>
			</div>
		</div>

		<?php
		// LOGGING PAGE
		if (isset($_GET['log']))
		{
			?>
			<div class="modal fade" id="delete-recurrence-modal" tabindex="-1" role="dialog" data-toggle="modal" data-bs-backdrop="static" data-bs-keyboard="true">
				<div class="modal-dialog" role="document" style="width: 800px">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close closeCustomModal btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							<h4 class="modal-title"><?=$lang['alerts_84']?></h4>
						</div>
						<div class="modal-body">
							<div class="mb-3">
								<?=$lang['alerts_85']?>
							</div>
							<div><?=$lang['alerts_86']?> <b id="delete-recurrence-modal-body-alert"></b></div>
							<div>
								<?=$lang['dataqueries_93']?> <b id="delete-recurrence-modal-body-record"></b>&nbsp;
								<?php if ($Proj->longitudinal) { ?><b>(<span id="delete-recurrence-modal-body-event"></span>)</b><?php } ?>
							</div>
						</div>
						<div class="modal-footer">
							<button data-toggle="modal" class="btn btn-rcred" id="delete-recurrence-modal-body-submit" onclick="return false;"><?=$lang['alerts_87']?></button>
							<button class="btn btn-defaultrc" data-bs-dismiss="modal" onclick="return false;"><?=$lang['global_53']?></button>
						</div>
					</div>
				</div>
			</div>
			<?php
            // Display notice about scheduled ASIs not sending when in Analysis/Cleanup mode
            $cleanupModeWarning = ($status == '2') ? RCView::div(array('class'=>'yellow fs13 mb-2 font-weight-normal float-left', 'style'=>'max-width:480px;'), RCView::fa('fa-solid fa-circle-exclamation mr-1').RCView::tt('survey_1591')) : "";
            // Export uttons
			renderPageTitle("
               <div class='clearfix'>
				 <div class='float-right'>	
				    <span class='text-secondary font-weight-normal fs13 me-1'>{$lang['reporting_68']}</span>				
					<button class='jqbuttonmed' style='color:#004000;' onclick=\"window.location.href=app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:downloadLogs&download_all=1';\"><img src='" . APP_PATH_IMAGES . "xls.gif' style='position: relative;top: -1px;'> {$lang['alerts_315']}</button>
					<button class='jqbuttonmed' style='color:#004000;' onclick=\"window.location.href=app_path_webroot+'index.php'+window.location.search+'&filters_download_all=1&route=AlertsController:downloadLogs&filters_download_all=1';\"><img src='" . APP_PATH_IMAGES . "xls.gif' style='position: relative;top: -1px;'> {$lang['reporting_66']}</button>
				 </div>
				 $cleanupModeWarning
                </div>");
			print $this->renderNotificationLog();
			include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
			exit;
		}

        // Get all datetime/datetime_seconds fields and put in array
        $datetime_fields_pre = Form::getFieldDropdownOptions(true, false, false, false, array('date', 'date_ymd', 'date_mdy', 'date_dmy', 'datetime',
                                    'datetime_ymd', 'datetime_mdy', 'datetime_dmy', 'datetime_seconds_ymd', 'datetime_seconds_dmy', 'datetime_seconds_mdy'), false, false);
        $datetime_fields = array();
        $datetime_fields[$lang['alerts_355']][''] = $lang['alerts_399'];
        $datetime_fields[$lang['alerts_355']]['[survey-date-completed:placeholder]'] = $lang['alerts_400'];
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

        // Create array of multi-page survey instruments
        $multipageSurveys = array();
        foreach ($Proj->surveys as $attr) {
            if (!$attr['question_by_section']) continue;
            $multipageSurveys[] = $attr['form_name'];
        }

        // Add days of the week + work day + weekend day as drop-down list options
        $daysOfWeekDD = SurveyScheduler::daysofWeekOptions();
        unset($daysOfWeekDD['']);

        // Set the "stop type" drop-down options
        if (!$Proj->longitudinal && !$hasRepeatingFormsOrEvents) {
            // Classic, no repeating
            $stopTypes = array('RECORD'=>$lang['alerts_216'] . " " . $lang['leftparen'] . $lang['alerts_228'] . $lang['rightparen']);
        } elseif (!$Proj->longitudinal && $hasRepeatingFormsOrEvents) {
            // Classic, repeating
            $stopTypes = array('RECORD'=>$lang['alerts_216'] . " " . $lang['leftparen'] . $lang['alerts_228'] . $lang['rightparen'],
                               'RECORD_EVENT_INSTRUMENT_INSTANCE'=>$lang['alerts_216']." ".$lang['alerts_217']);
        } elseif ($Proj->longitudinal && !$hasRepeatingFormsOrEvents) {
            // Longitudinal, no repeating
            $stopTypes = array('RECORD'=>$lang['alerts_216'] . " " . $lang['leftparen'] . $lang['alerts_228'] . $lang['rightparen'],
                               'RECORD_INSTRUMENT'=>$lang['alerts_222'],
                               'RECORD_EVENT'=>$lang['alerts_218']);
        } elseif ($Proj->longitudinal && $hasRepeatingFormsOrEvents) {
            // Longitudinal, repeating
            $stopTypes = array('RECORD'=>$lang['alerts_216'] . " " . $lang['leftparen'] . $lang['alerts_228'] . $lang['rightparen'],
                               'RECORD_INSTRUMENT'=>$lang['alerts_222'],
                               // 'RECORD_EVENT_INSTRUMENT'=>$lang['alerts_224'],
                               'RECORD_EVENT'=>$lang['alerts_218'],
                               'RECORD_EVENT_INSTRUMENT_INSTANCE'=>$lang['alerts_218']." ".$lang['alerts_223']);
        }
        // Import/Export buttons divs
        $buttons = RCView::div(array('style'=>'text-align:right; font-size:12px;font-weight:normal;margin-bottom:5px;'),
                RCView::button(array('onclick'=>"showBtnDropdownList(this,event,'downloadUploadAlertsDropdownDiv');", 'class'=>'jqbuttonmed'),
                    RCView::img(array('src'=>'xls.gif', 'style'=>'vertical-align:middle;position:relative;top:-1px;')) .
                    RCView::span(array('style'=>'vertical-align:middle;'), $lang['alerts_276']) .
                    RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:2px;vertical-align:middle;position:relative;top:-1px;'))
                ) .
                RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"$.get(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:uploadDownloadHelp',{ },function(data){ $('#alertDownloadUploadDialog').html(data).dialog({ width: 900, bgiframe: true, modal: true, open: function(){fitDialog(this)}, buttons: { Close: function() { $(this).dialog('close'); } } }); });"), $lang['questionmark']) .
                // Button/drop-down options (initially hidden)
                RCView::div(array('id'=>'downloadUploadAlertsDropdownDiv', 'style'=>'text-align:left;display:none;position:absolute;z-index:1000;'),
                    RCView::ul(array('id'=>'downloadUploadAlertsDropdown'),
                        // Show upload button
                        RCView::li(array(),
                            RCView::a(array('href'=>'javascript:;', 'style'=>'color:#8A5502;', 'onclick'=>"simpleDialog(null,null,'importAlertsDialog',600,null,'".js_escape($lang['calendar_popup_01'])."',\"$('#importAlertForm').submit();\",'".js_escape($lang['design_530'])."');$('.ui-dialog-buttonpane button:eq(1)',$('#importAlertsDialog').parent()).css('font-weight','bold');"),
                                RCView::img(array('src'=>'arrow_up_sm_orange.gif')) .
                                RCView::SP . $lang['alerts_277']
                            )
                        ) .
                        RCView::li(array(),
                            RCView::a(array('href'=>'javascript:;', 'style'=>'color:#8A5502;', 'onclick'=>"window.location.href = app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:downloadAlerts';"),
                                RCView::img(array('src'=>'arrow_down_sm_orange.gif')) .
                                RCView::SP . $lang['alerts_278']
                            )
                        )
                    )
                )
            );

        $schedule_confirmation_box = '<div style="margin:15px 0px; display:none;" id="upload-alerts-schedule-confirmation">
                                        <div class="modal-dialog" style="max-width: 1000px;">
                                            <div class="modal-content">
                                                <div class="modal-header" style="padding: 5px 5px;">
                                                    <div class="modal-title" style="font-weight: bold;">'.$lang['alerts_274'].'</div>
                                                </div>
                                                <div class="modal-body" style="padding: 5px;">
                                                    '.$lang['alerts_280'].'
                                                </div>
                                                <div class="modal-footer" style="justify-content: left; background-color: #ccc;">
                                                    <input name="cron-queue" type="radio" value="1" checked> '.$lang['alerts_407'].'<br>
                                                    <input name="cron-queue" type="radio" value="0"> '.$lang['alerts_408'].'<br>
                                                </div>
                                            </div>
                                        </div>                
                                    </div>';
        $csrf_token = System::getCsrfToken();
        // Hidden import dialog divs
        $hiddenImportDialog = RCView::div(array('id' => 'importAlertsDialog', 'class' => 'simpleDialog', 'title' => $lang['alerts_277']),
            RCView::div(array('class'=>'mb-3'), $lang['alerts_282']) .
            RCView::div(array('class'=>'mb-3'), $lang['alerts_312']) .
            RCView::div(array('style' => 'margin-top:15px; margin-bottom:5px; font-weight:bold;color:#C00000;'), $lang['alerts_283']) .
            RCView::form(array('id' => 'importAlertForm', 'enctype' => 'multipart/form-data', 'method' => 'post', 'action' => APP_PATH_WEBROOT . 'index.php?route=AlertsController:uploadAlerts&pid=' . PROJECT_ID, 'onsubmit' => 'javascript: return checkFileUploadExt();'),
                RCView::input(array('type' => 'hidden', 'name' => 'redcap_csrf_token', 'value' => $csrf_token)) .
                RCView::input(array('type' => 'file', 'id' => 'alertsFile', 'name' => 'file'))
            )
        );
        $hiddenImportDialog .= RCView::div(array('id' => 'importAlertsDialog2', 'class' => 'simpleDialog', 'title' => $lang['alerts_277'] . " - " . $lang['design_654']),
            RCView::div(array('id' => 'statusInfo'), $lang['api_125']) .
            RCView::div(array('style' => 'display:none;', 'class' => 'error', 'id' => 'noChangesFound'), "<b>".$lang['api_docs_094']."</b>".$lang['database_mods_76']) .
            RCView::form(array('id' => 'importAlertForm2', 'enctype' => 'multipart/form-data', 'method' => 'post', 'action' => APP_PATH_WEBROOT . 'index.php?route=AlertsController:uploadAlerts&pid=' . PROJECT_ID),
                RCView::input(array('type' => 'hidden', 'name' => 'redcap_csrf_token', 'value' => $csrf_token)) .
                RCView::textarea(array('name' => 'csv_content', 'style' => 'display:none;'), (isset($_SESSION['csv_content']) ? htmlspecialchars($_SESSION['csv_content'], ENT_QUOTES) : "")) .
                $schedule_confirmation_box
            ) .
            RCView::div(array('id' => 'alert_preview', 'style' => 'margin:15px 0'), '')
        );
        print RCView::simpleDialog('', $lang['alerts_276'], 'alertDownloadUploadDialog');
        Design::alertRecentImportStatus();
        ?>

        <div id='errMsgContainer' class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;"></div>
        <div class="alert alert-success" style="max-width:800px;border-color: #b2dba1 !important;display: none;" id="succMsgContainer"></div>

        <script type="text/javascript">
        var multipageSurveys = new Array(<?=prep_implode($multipageSurveys)?>);
        $(function(){
            $('#downloadUploadAlertsDropdown').menu();
            $('#downloadUploadAlertsDropdownDiv ul li a').click(function(){
                $('#downloadUploadAlertsDropdownDiv').hide();
            });
            $('#addNewAlert').click(function() {
                editEmailAlert(<?=json_encode($alertDefaults)?>,"","");
                $('[field="cron-queue"]').hide();
            });
        });
        </script>
        <!-- Div for displaying popup dialog for file extension mismatch  -->
        <div id="filetype_mismatch_div" title="<?php echo $lang['random_12']; ?>" style="display:none;">
            <p>
                <?php echo $lang['data_import_tool_160'] ?>
                <a href="https://support.office.com/en-us/article/Import-or-export-text-txt-or-csv-files-5250ac4c-663c-47ce-937b-339e391393ba" target="_blank"
                    style="text-decoration:underline;"><?php echo $lang['data_import_tool_116'] ?></a>
                <?php echo $lang['design_134'] ?>
            </p>
        </div>
        <!-- ALERTS TABLE -->
        <div style="width:950px;max-width:950px;">
            <div><?=$buttons.$hiddenImportDialog;?></div>
            <div class="mb-1 clearfix">
                <button id='addNewAlert' type="button" class="btn btn-sm btn-rcgreen float-start"><i class="fas fa-plus"></i> <?=$lang['alerts_88']?></button>
                <button id='reevalAlerts' type="button" class="btn btn-sm btn-light float-start ms-4" onclick="dialogReevalAlerts();" <?php if (empty($projectData) || Records::getRecordCount(PROJECT_ID) == 0) echo "disabled"; ?>><i class="fas fa-redo"></i> <?=$lang['alerts_253']?></button>
                <div class="float-end mt-2 me-1 <?=($countActivated > 0 ? "" : "text-dangerrc font-weight-bold")?>">
                    <input value="" id="deleted_alerts" class="auto-submit" type="checkbox" name="deleted_alerts">
                    <label for="deleted_alerts"><?=RCView::tt_i('alerts_109',[$countDeactivated])?></label>
                </div>
            </div>
            <div>
                <?php if ($indexSubSet > 0) { ?>
                <table class="table table-bordered table-hover email_preview_forms_table" id="customizedAlertsPreview" style="display:none;width:100%;table-layout: fixed;">
                    <thead>
                        <tr class="table_header d-none">
                            <th><?=$lang['alerts_90']?></th>
                            <th style="width:350px;"><span class="fas fa-envelope"></span> <?=$lang['messaging_110']?></th>
                            <th style="display:none;">Active</th>
                            <th style="display:none;">Deleted</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // Loop through all alerts
                    $alert_number = 0;
                    $alerts = "";
                    foreach ($projectData as $alert_id=>$attr)
                    {
                        $index = $alert_number;
                        $email_sent = $attr['email_sent'];
                        $message_sent = "";
                        $alertNumDeleteClass = '';
                        $active_col = "Y";

                        $show_queue = "";
                        if($attr['email_repetitive'] == '1' || $attr['email_deleted'] == '1'){
                            $show_queue = "display:none;";
                        }

                        //DELETE
                        $deleted_text = $lang['alerts_91'];
                        if ($attr['email_deleted'] == '1') {
                            $alertNumDeleteClass = 'alert-deleted';
                            $deactivated_deleted_text = "<i class=\"fas fa-times\"></i> ".$lang['alerts_92'];
                            $message_sent .= "<div class='bg-danger text-white mt-2 p-2'>".$deactivated_deleted_text."</div>";
                            $deleted_modal = "external-modules-configure-modal-delete-confirmation";
                            $deleted_index = "index_modal_delete";
                            $deleted_col = "Y";
                            $deleted_text = $lang['alerts_93'];
                            $reactivate_button = '<a class="dropdown-item" href="#" onclick="reactivateEmailAlert('.$alert_id.');return true;"><i class="fas fa-power-off"></i> '.$lang['alerts_94'].'</a>';
                        } else {
                            $deleted_modal = "external-modules-configure-modal-delete-user-confirmation";
                            $deleted_index = "index_modal_delete_user";
                            $deleted_col = "N";
                            $reactivate_button = "";
                        }

                        $alert_number++;

                        $activity = $activityBox = "";

                        // List alerts that have been queued
                        $queuedRecords = $this->getAlertQueuedRecords($alert_id);
                        $numQueuedRecords = count($queuedRecords);
                        if ($numQueuedRecords > 0) {
                            $schedText = $numQueuedRecords == '1' ? $lang['alerts_95'] : $lang['alerts_96'];
                            $activity .= '<div class=""><i class="far fa-clock"></i> '.$numQueuedRecords.' '.$schedText.'
                                            &nbsp;(<a href="javascript:;" class="fs12" style="text-decoration:underline;margin-left:1px;margin-right:1px;" data-toggle="popover"
                                                data-title="'.js_escape2($numQueuedRecords.' '.$lang['alerts_97'].' #'.$alert_number).'"
                                                data-content=\''.js_escape('<div><a href="'.APP_PATH_WEBROOT.'index.php?pid='.PROJECT_ID.'&route=AlertsController:setup&log=1&pagenum=1&filterBeginTime='.rawurlencode(substr(DateTimeRC::format_ts_from_ymd(NOW, true, false), 0, 16)).'&filterEndTime=&filterRecord=&filterAlert='.$alert_id.'"><i class="fas fa-table"></i> '.$lang['alerts_105'].'</a></div>
                                                    <p>'.$lang['alerts_104'].' '.implode(", ", $queuedRecords).'</p>').'\'
                                            >'.$lang['alerts_98'].'</a>)
                                          </div>';
                        }
                        elseif ($attr['email_repetitive'] == '0' && $attr['cron_send_email_on'] != 'now' && $attr['cron_send_email_on_date'] != '')
                        {
                            // Only display this if alerts will ever be scheduled (as opposed to being sent immediately)
                            $activity .= '<div class="text-secondary"><i class="far fa-clock"></i> '.$lang['alerts_99'].'</div>';
                        }

                        // Get text for stop-type
                        if ($attr['alert_stop_type'] == 'RECORD_INSTRUMENT' && $Proj->longitudinal) {
                            $stopTypeText = $lang['alerts_222'];
                        } elseif ($attr['alert_stop_type'] == 'RECORD_EVENT_INSTRUMENT' && $Proj->longitudinal) {
                            $stopTypeText = $lang['alerts_224'];
                        } elseif ($attr['alert_stop_type'] == 'RECORD_EVENT' && $Proj->longitudinal) {
                            $stopTypeText = $lang['alerts_218'];
                        } elseif ($attr['alert_stop_type'] == 'RECORD_EVENT_INSTRUMENT_INSTANCE' && $Proj->hasRepeatingFormsEvents()) {
                            $stopTypeText = ($Proj->longitudinal ? $lang['alerts_218']." ".$lang['alerts_223'] : $lang['alerts_216']." ".$lang['alerts_217']);
                        } else {
                            $stopTypeText = $lang['alerts_216'] . " - " . $lang['alerts_228'];
                        }
                        $stopTypeText = RCView::span(array('class'=>'text-secondary ms-1 fs12'), $lang['leftparen'].$stopTypeText.$lang['rightparen']);

                        // Get text for maximum recurrence
                        $maxRecurText = '';
                        if (is_numeric($attr['cron_repeat_for_max'])) {
                             $maxRecurText = " &ndash; ".$lang['survey_737']." ".$attr['cron_repeat_for_max']." ".$lang['alerts_235'];
                        }

                        // List alerts that have been sent
                        $alerts_sent = $this->getAlertsSent($alert_id);
                        $num_alerts_sent = count($alerts_sent);
                        $email_records_sent = array_unique($alerts_sent);
                        if ($num_alerts_sent > 0) {
                            $sentText = $num_alerts_sent == '1' ? $lang['alerts_194'] : $lang['alerts_195'];
                            $activity .= '<div class="">
                                            <i class="far fa-envelope-open"></i> '.$num_alerts_sent.' '.$sentText.'
                                            &nbsp;(<a href="#" class="fs12" style="text-decoration:underline;margin-left:1px;margin-right:1px;" rel="popover" data-toggle="popover"
                                                data-title="'.js_escape2($num_alerts_sent.' '.$lang['alerts_100'].' #'.$alert_number).'"
                                                data-content=\''.js_escape('<div><a href="'.APP_PATH_WEBROOT.'index.php?pid='.PROJECT_ID.'&route=AlertsController:setup&log=1&pagenum=1&filterBeginTime=&filterEndTime='.rawurlencode(substr(DateTimeRC::format_ts_from_ymd(NOW, true, false), 0, 16)).'&filterRecord=&filterAlert='.$alert_id.'"><i class="fas fa-table"></i> '.$lang['alerts_106'].'</a></div>
                                                    <p>'.$lang['alerts_104'].' '.implode(", ", $email_records_sent).'</p>').'\'
                                            >'.$lang['alerts_98'].'</a>)
                                            <span class="ms-4 fs11" style="color:green;"><i class="fas fa-check"></i> '.$lang['alerts_101'].' '.
                                                DateTimeRC::format_user_datetime($attr['email_timestamp_sent'], 'Y-M-D_24').'
                                            </span>
                                          </div>';
                        } else {
                            $activity .= '<div class="text-secondary"><i class="far fa-envelope-open"></i> '.$lang['alerts_102'].'</div>';
                        }
                        $activityBox .= '<div class="clearfix">
                                            <div class="float-start boldish" style="color:#6320ac;width:90px;">
                                                <i class="fs14 fas fa-tachometer-alt"></i> '.$lang['alerts_103'].'
                                            </div>
                                            <div class="float-start">'.$activity.'</div>
                                          </div>';

                        $fileAttachments = 0;
                        $attachmentVar ='';
                        $attachmentFile ='';
                        $scheduled_email = '';
                        $formName = $triggerText = '';
                        $msg = '';
                        $previewMsgLinks = '';
                        $info_modal = array();
                        $daysOfWeekDD = SurveyScheduler::daysofWeekOptions();

                        foreach ($attr as $configKey => $configVal) {
                            // Convert dates/times
                            if ($configKey == 'cron_send_email_on_date' || $configKey == 'alert_expiration') {
                                $configVal = DateTimeRC::format_user_datetime($configVal, 'Y-M-D_24', DateTimeRC::get_user_format_full(), true);
                            }
                            // Format phone numbers (if applicable)
                            elseif ($configKey == 'phone_number_to' && $configVal !== null) {
                                $phone_number_tos = array();
                                foreach (explode(";", $configVal) as $this_phone_number)
                                {
                                    $this_phone_number = trim($this_phone_number);
                                    if ($this_phone_number == '') continue;
                                    $firstCharacter = substr($this_phone_number, 0, 1);
                                    if (is_numeric($firstCharacter)) {
                                        $this_phone_number = formatPhone($this_phone_number);
                                    }
                                    $phone_number_tos[] = $this_phone_number;
                                }
                                $phone_number_tos = implode("; ", $phone_number_tos);
                                $configVal = $phone_number_tos;
                            }
                            elseif ($configKey == 'alert_message' || $configKey == 'email_to') {
                                $configVal = decode_filter_tags($configVal);
                            }
                            // Store values in array to convert to JSON to use when loading the dialog
                            $info_modal[$index][str_replace("_", "-", $configKey)] = $configVal . "";
                        }

                        // Loop through this row's attributes
                        $scheduled_email = "";
                        foreach ($attr as $configKey => $configVal)
                        {
                            if ($configKey == 'cron_send_email_on' || $configKey == 'cron_send_email_on_date' || $configKey == 'cron_repeat_for') {
                                // SCHEDULE EMAIL INFO
                                if ($attr['email_repetitive'] != '1' && $attr['email_repetitive_change'] != '1')
                                {
                                    if ($configKey == 'cron_send_email_on') {
                                        $scheduled_email .= "<div class='mt-1' style='color:green;'>";
                                        if ($configVal == "date") {
                                            $scheduled_email .= "<i class=\"far fa-clock\"></i> ".$lang['alerts_107'];
                                            $scheduled_email .= " " . DateTimeRC::format_user_datetime($attr['cron_send_email_on_date'], 'Y-M-D_24');
                                        } elseif ($configVal == "next_occurrence") {
                                            $scheduled_email .= "<i class=\"far fa-clock\"></i> ".$lang['alerts_108'];
                                            $scheduled_email .= " ".$daysOfWeekDD[$attr['cron_send_email_on_next_day_type']];
                                            $scheduled_email .= " at ".DateTimeRC::format_user_datetime($attr['cron_send_email_on_next_time'], 'Y-M-D_24');
                                        } elseif ($configVal == "time_lag") {
                                            $scheduled_email .= "<i class=\"far fa-clock\"></i> ".$lang['alerts_246'] . " ";
                                            $time_lag_components = array();
                                            if ($attr['cron_send_email_on_time_lag_days'] > 0) $time_lag_components[] = $attr['cron_send_email_on_time_lag_days']." ".$lang['survey_426'];
                                            if ($attr['cron_send_email_on_time_lag_hours'] > 0) $time_lag_components[] = $attr['cron_send_email_on_time_lag_hours']." ".$lang['survey_427'];
                                            if ($attr['cron_send_email_on_time_lag_minutes'] > 0) $time_lag_components[] = $attr['cron_send_email_on_time_lag_minutes']." ".$lang['survey_428'];
                                            $scheduled_email .= " " . implode(", ", $time_lag_components);
                                            if ($attr['cron_send_email_on_field'] != '') {
                                                if (!empty($time_lag_components)) $scheduled_email .= " " .($attr['cron_send_email_on_field_after'] == 'before' ? $lang['alerts_245'] : $lang['alerts_238']);
                                                $scheduled_email .= " " .($attr['cron_send_email_on_field'] == "[survey-date-completed:placeholder]" ? $lang['alerts_400'] : $attr['cron_send_email_on_field']);
                                            } else {
                                                $scheduled_email .= " " . $lang['alerts_238'] . " " .$lang['alerts_399'];
                                            }
                                        } else {
                                            $scheduled_email .= "<i class=\"fas fa-share\"></i> ".$lang['global_1540'];
                                        }
                                        $scheduled_email .= "</div>";
                                    }
                                    if ($attr['cron_repeat_for'] == 0 && $configKey == "cron_send_email_on_date") {
                                        $scheduled_email .= "<div class='mt-1'><b class='code box-1x'>1x</b> {$lang['global_1541']} $stopTypeText</div>";
                                    } elseif ($attr['cron_repeat_for'] > 0 && $configKey == "cron_send_email_on_date") {
                                        if ($attr['cron_repeat_for'] == '1'){
                                            $scheduled_email .= "<div class='mt-1'><i class=\"fas fa-redo\"></i> {$lang['global_1542']}";
                                            if ($attr['cron_repeat_for_units'] == 'MINUTES') {
                                                $scheduled_email .= " {$lang['global_1543']}";
                                            } elseif ($attr['cron_repeat_for_units'] == 'HOURS') {
                                                $scheduled_email .= " {$lang['global_1544']}";
                                            } else {
                                                $scheduled_email .= " {$lang['global_1545']}";
                                            }
                                            $scheduled_email .= " $maxRecurText $stopTypeText</div>";
                                        }else{
                                            $scheduled_email .= "<div class='mt-1'><i class=\"fas fa-redo\"></i> {$lang['global_1542']} " . $attr['cron_repeat_for'];
                                            if ($attr['cron_repeat_for_units'] == 'MINUTES') {
                                                $scheduled_email .= " ".$lang['survey_428'];
                                            } elseif ($attr['cron_repeat_for_units'] == 'HOURS') {
                                                $scheduled_email .= " ".$lang['survey_427'];
                                            } else {
                                                $scheduled_email .= " ".$lang['survey_426'];
                                            }
                                            $scheduled_email .= " $maxRecurText $stopTypeText</div>";
                                        }
                                    }
                                } elseif ($attr['email_repetitive'] == '1') {
                                    $scheduled_email = "<div class='mt-1' style='color:green;'><i class=\"fas fa-share\"></i> {$lang['global_1540']}</div>";
                                    $scheduled_email .= "<div class='mt-1'><i class=\"fas fa-redo\"></i> {$lang['global_1546']}</div>";
                                } elseif ($attr['email_repetitive_change'] == '1') {
                                    $scheduled_email = "<div class='mt-1' style='color:green;'><i class=\"fas fa-share\"></i> {$lang['global_1540']}</div>";
                                    $scheduled_email .= "<div class='mt-1'><i class=\"fas fa-redo\"></i> {$lang['alerts_226']}</div>";
                                }
                                if ($configKey == "cron_repeat_for" && $attr['alert_expiration'] != '') {
                                    $scheduled_email .= "<div id='expire-descrip".$index."' class='mt-1 expire-descrip' style='color:#A00000;'>
                                                                <i class='far fa-calendar-times'></i> {$lang['global_1547']} " .
                                        DateTimeRC::format_user_datetime($attr['alert_expiration'], 'Y-M-D_24') .
                                        "</div>";
                                }
                            }else{
                                //NORMAL EMAIL
                                if (strpos($configKey, 'email_attachment') === 0 && $configKey != 'email_attachment_variable') {
                                    if (!empty($configVal)) {
                                        $fileAttachments++;
                                        $thisAttachName = Files::getEdocName($configVal);
                                        if ($thisAttachName) {
                                            $url = APP_PATH_WEBROOT."index.php?route=AlertsController:downloadAttachment&id=".$configVal."&alert_id=".$alert_id."&pid=".PROJECT_ID;
                                            $attachmentFile .= '<div class="ps-2 fs12 text-truncate"><i class="fas fa-paperclip me-1" style="position:relative;top:1px;"></i><a href="'.$url.'" class="fs12" target="_blank">'.$thisAttachName.'</a></div>';
                                        }
                                    }
                                } else {
                                    if ($configKey == 'form_name')
                                    {
                                        $move_alert_link = '';
                                        if (count($this->getAlertSettings(PROJECT_ID)) > 1) {
                                            // show move alert link only when there are atleast 2 alerts
                                            $move_alert_link = '<a class="dropdown-item" href="#" onclick="moveEmailAlert('.$alert_id.');"><i class="fas fa-arrows-alt"></i> '.$lang['alerts_267'].'</a>';
                                        }

//                                      // Alert number
                                        $deletedAlertClass = ($attr['email_deleted'] == '1') ? "alert-danger" : "alert-primary";
                                        $alertTitle = (trim($attr['alert_title'] ?? "") == '') ? '' : $lang['colon'].'<span class="font-weight-normal ms-1">'.RCView::escape($attr['alert_title']).'</span>';
                                        $formName .= '<div class="clearfix" style="margin-left: -11px;">
                                                        <div style="max-width:340px;" class="card-header alert-num-box '.$alertNumDeleteClass.' float-start text-truncate"><i class="fas fa-bell fs13" style="margin-right:5px;"></i>'.$lang['alerts_24'].' #'.$alert_number.$alertTitle.'</div>
                                                        <div class="btn-group nowrap float-start mb-1 ms-2" role="group">
                                                          <button type="button" class="btn btn-link fs13 py-1 ps-1 pe-2" onclick="__rcfunc_editEmailAlert_emailRow'.$index.'();">
                                                            <i class="fas fa-pencil-alt"></i> '.$lang['global_27'].'
                                                          </button>
                                                          <div class="btn-group dropdown" role="group">
                                                            <button id="btnGroupDrop1" type="button" class="btn btn-link fs13 py-1 ps-2 pe-0 dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                              <i class="fas fa-cog"></i> '.$lang['global_1549'].'
                                                            </button>
                                                            <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
                                                              '.$reactivate_button.'
                                                              <a class="dropdown-item" href="#" onclick="duplicateEmailAlert('.$alert_id.');return true;"><i class="fas fa-copy"></i> '.$lang['global_1548'].'</a>
                                                              <a class="dropdown-item" href="#" onclick="deleteEmailAlert('.$alert_id.',\''.$deleted_modal.'\',\''.$deleted_index.'\');return true;"><i class="fas fa-times"></i> '.$deleted_text.'</a>
                                                              '.$move_alert_link.'
                                                            </div>
                                                          </div>
                                                        </div>
                                                        <div style="padding:4px 4px 4px 5px; float: right;">
                                                            <span class="fs11"><span class="text-secondary">'.$lang['alerts_311'].'</span> <span style="color:#A00000;">'.self::ALERT_UNIQUE_ID_PREFIX.$alert_id.'</span></span>
                                                        </div>
													  </div>';
										$formName .= "<script type=\"text/javascript\">function __rcfunc_editEmailAlert_emailRow{$index}(){ editEmailAlert(".json_encode($info_modal[$index]).",".$alert_id.",".$alert_number.") }</script>";

										// Set form label text
										 $formLabel = "";
										if ($configVal != '') {
											$formLabel .= $lang['alerts_120']." \"<span class='boldish'>" . $Proj->forms[$configVal]['menu'];
											// Set event text
											if (\REDCap::isLongitudinal()) {
												$formLabel .= " " . ($attr['form_name_event'] == '' ? $lang['alerts_70'] : "(".$Proj->eventInfo[$attr['form_name_event']]['name_ext'].")");
											}
											$formLabel .= "</span>\" ".($attr['email_incomplete'] ? $lang['alerts_121'] : $lang['alerts_122']);
											if (trim($attr['alert_condition'] ?? "") == '') $formLabel .= $lang['period'];
										}
										if (trim($attr['alert_condition'] ?? "") != '') {
											// Conditional logic
											if ($configVal != '') {
												if ($attr['email_repetitive']) {
													$formLabel = $lang['alerts_123']." " . $formLabel.$lang['colon'];
												} else {
													$formLabel = $lang['alerts_124']." " . $formLabel.$lang['colon'];
												}
											} else {
												if ($attr['email_repetitive']) {
													$formLabel = $lang['alerts_125']." " . $formLabel;
												} else {
													$formLabel = $lang['alerts_126']." " . $formLabel;
												}
												$formLabel .= " ".$lang['alerts_127'];
											}
											$formLabel .= " <span class='code' style='font-size:85%;'>{$attr['alert_condition']}</span>";
										} else {
											if ($attr['email_repetitive']) {
												$formLabel = $lang['alerts_125']." " . $formLabel;
											} else {
												$formLabel = $lang['alerts_126']." " . $formLabel;
											}
										}

										// Display trigger: form and/or logic
										$triggerText .= '<div id="trigger-descrip'.$index.'" class="mb-1 trigger-descrip"><b class="fs14"><i class="fas fa-hand-point-right"></i></b> '.$formLabel.'</div>';


									} else if($configKey == 'email_attachment_variable' && $configVal !== null){
										$attchVar = preg_split("/[;,]+/",  $configVal);
										foreach ($attchVar as $var){
											if (!empty($var)){
												$fileAttachments++;
												$attachmentVar .= '<div class="ps-2 fs12 text-truncate"><i class="fas fa-paperclip me-1" style="position:relative;top:1px;"></i>'.trim($var).'</div>';
											}
										}
									}else if($configKey == 'email_from' && $attr['alert_type'] == "EMAIL") {
										$fromContent = '<a class="fs12" href="mailto:'.$configVal.'">'.($attr['email_from_display'] == '' ? $configVal : RCView::escape($attr['email_from_display'])." &lt;".$configVal."&gt;").'</a>';
										$msg .= '<li class="list-group-item py-1 px-3 text-truncate fs12">
													<span class="me-1 boldish">'.$lang['global_37'].'</span> 
													'.$fromContent.'
												 </li>';
									}else if($configKey == 'email_to' && ($attr['alert_type'] == "EMAIL" || $attr['alert_type'] == "SENDGRID_TEMPLATE")) {
										if ($Proj->twilio_enabled_alerts && ($attr['alert_type'] == "SMS" || $attr['alert_type'] == "VOICE_CALL")) {
											$phoneTos = array();
											foreach (preg_split("/[;,]+/", $attr['phone_number_to']) as $thisPhoneTo) {
												$thisPhoneTo = trim($thisPhoneTo);
												$phoneTos[] = (substr($thisPhoneTo, 0, 1) == "[") ? $thisPhoneTo : formatPhone($thisPhoneTo);
											}
											$to_text = implode('; ', $phoneTos);
										} else {
											$emailTos = array();
                                            if ($configVal != '') {
                                                foreach (preg_split("/[;,]+/", $configVal) as $thisEmailTo) {
                                                    if (!(isEmail($thisEmailTo) || (strpos($thisEmailTo, '[') !== false && strpos($thisEmailTo, ']') !== false))) continue;
                                                    $emailTos[] = '<a class="fs12" href="mailto:' . $thisEmailTo . '">' . $thisEmailTo . '</a>';
                                                }
                                            }
											$to_text = implode('; ', $emailTos);
										}
										$msg .= '<li class="list-group-item py-1 px-3 text-truncate fs12">
													<span class="me-1 boldish">'.$lang['global_38'].'</span> '.$to_text.'
												 </li>';
									} else if ($configKey == 'email_subject' && $attr['alert_type'] == "EMAIL") {
										$msg .= '<li class="list-group-item py-1 px-3 text-truncate fs12">
													<span class="me-1 boldish">'.$lang['control_center_28'].'</span> '.RCView::escape($configVal) . '
												 </li>';
									} else if ($configKey == 'email_from' && $attr['alert_type'] == "SENDGRID_TEMPLATE") {
										$msg .= '<li class="list-group-item py-1 px-3 text-truncate fs12">
													<span class="me-1 boldish">'.$lang['global_37'].'</span> '.RCView::escape($configVal) . '
													</li>';
									} else if ($configKey == 'sendgrid_template_id' && $attr['alert_type'] == "SENDGRID_TEMPLATE") {
										$msg .= '<li class="list-group-item py-1 px-3 text-truncate fs12">
													<span class="me-1 boldish">'.$lang['alerts_334'].'</span> '.RCView::escape($configVal) . '
													</li>';
									} else if ($configKey == 'alert_message' && $attr['alert_type'] != "SENDGRID_TEMPLATE") {
										$configVal = mb_substr(str_replace('&nbsp;', ' ', strip_tags(br2nl($configVal))), 0, 100);
										$msg .= '<li class="list-group-item py-1 px-3 text-truncate fs12">
													<span class="me-1 boldish"'.$lang['messaging_105'].'></span> '.RCView::escape($configVal) . '
												 </li>';
									}
								}
							}
						}
						$fileAttachmentText = "";
						if ($fileAttachments > 0) {
							$fileAttachmentText = "<li class='list-group-item pt-1 pb-2 px-3 fs12'>
												   <div class='boldish'>{$lang['alerts_128']} (".$fileAttachments."):</div>
												   ".$attachmentFile.$attachmentVar.'</li>';
						}

						// Output row
						$alerts .= "<tr id='alert_".$alert_id."' class='".$alertNumDeleteClass."'>";
						$alerts .= "<td class='pt-0 pb-4' style='border-right:0;' data-order='".$alert_number."'>
										".$formName."
										<div class='card mt-3'>
											<div class='card-body p-2'>".$triggerText.$scheduled_email.$message_sent."</div>
										</div>
										<div class='card mt-3'>
											<div class='card-body p-2'>$activityBox</div>
										</div>
										
									</td>";
						if ($attr['alert_type'] == "SMS") {
							$alertTypeText = "<i class='fas fa-sms fs15'></i> {$lang['alerts_201']}";
						} elseif ($attr['alert_type'] == "VOICE_CALL") {
							$alertTypeText = "<i class='fas fa-phone'></i> {$lang['alerts_202']}";
						} elseif ($attr['alert_type'] == "SENDGRID_TEMPLATE") {
							$alertTypeText = "<i class='fas fa-envelope'></i> {$lang['alerts_328']}";
						} else {
                            $alertTypeText = "<i class='fas fa-envelope'></i> {$lang['global_33']}";
						}
						$alerts .= "<td class='pt-3 pb-4' style='width:350px;border-left:0;'>
										<div class='card'>
											<div class='card-header bg-light py-1 px-3 clearfix' style='color:#004085;background-color:#d5e3f3 !important;'>
												<div class='float-start'>$alertTypeText</div>
												<div class=\"btn-group nowrap float-end\" role=\"group\">
												  <div class=\"btn-group dropdown\" role=\"group\">
													<button id=\"btnGroupDrop2\" type=\"button\" class=\"btn btn-link fs12 p-0 dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">
													  {$lang['design_699']}
													</button>
													<div class=\"dropdown-menu\" aria-labelledby=\"btnGroupDrop2\">
													  <a class=\"dropdown-item\" href=\"#\" onclick=\"previewEmailAlert('$index','$alert_number','$alert_id')\"><i class=\"far fa-envelope\"></i> {$lang['alerts_82']}</a>
													  <a class=\"dropdown-item\" href=\"#\" onclick=\"previewEmailAlertRecord('$index','$alert_number','$alert_id')\"><i class=\"far fa-envelope\"></i> {$lang['alerts_129']}</a>
													</div>
												  </div>
												</div>
											</div>
											<div class='card-body p-0'>
												<ul class='list-group list-group-flush'>
													 ".$msg.$fileAttachmentText."       
												</ul>                                                                  
											</div>
										</div>
										".$previewMsgLinks."
									</td>";
						$alerts .= "<td style='display:none;'>".$active_col."</td>";
						$alerts .= "<td style='display:none;'>".$deleted_col."</td>";
						$alerts .= "</tr>";
					}
					echo $alerts;
					}
					?>
					<tbody>
				</table>
			</div>

			<div class="col-md-12">
				<form class="form-horizontal" action="" method="post" id="saveAlert">
					<div class="modal fade" id="external-modules-configure-modal" name="external-modules-configure-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" data-bs-focus="false">
						<div class="modal-dialog" role="document" style="max-width: 950px !important;">
							<div class="modal-content">
								<div class="modal-header py-2">
									<button type="button" class="py-2 close closeCustomModal btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
									<h4 id="add-edit-title-text" class="modal-title form-control-custom"></h4>
								</div>
								<div class="modal-body pt-2">
									<div id="errMsgContainerModal" class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;"></div>
									<div class="mb-2">
										<?=$lang['alerts_130']?>
									</div>
									<table class="code_modal_table" id="code_modal_table_update">

										<!-- Triggers -->
										<tr class="form-control-custom">
											<td colspan="2" class="align-text-top pt-1">
												<label class="fs14 boldish"><?=$lang['alerts_131']?></label>
												<input type="text" name="alert-title" placeholder="<?=RCView::tt_js2('econsent_174')?>" class="d-inline ms-3" style="font-size:15px;width:500px;" maxlength="100">
											</td>
										</tr>
										<tr class="form-control-custom">
											<td colspan="2">
												<div class="form-control-custom-title clearfix">
													<div class="boldish fs14" style="margin-top:2px;"><i class="fas fa-hand-point-right"></i> <?=$lang['alerts_132']?></div>
												</div>
											</td>
										</tr>
										<tr class="form-control-custom" field="">
											<td class="align-text-top pt-1 pe-1">
												<label class="text-nowrap boldish"><?=RCView::span(array('style'=>'color:#0061b5;'), $lang['alerts_219'])." ".$lang['alerts_133']?></label>
											</td>
											<td class="external-modules-input-td">
												<div class="ms-2 nowrap">
													<input type="radio" id="alert-trigger1" name="alert-trigger" value="submit" style="height:20px;" class="external-modules-input-element align-middle">
													<label for="alert-trigger1" class="m-0 align-middle"><?=$lang['alerts_134']?><span class='em-ast'>*</span></label>
												</div>
												<div class="ms-2 nowrap">
													<input type="radio" id="alert-trigger2" name="alert-trigger" value="submit-logic" style="height:20px;" class="external-modules-input-element align-middle">
													<label for="alert-trigger2" class="m-0 align-middle"><?=$lang['alerts_316']?><span class='em-ast'>*</span></label>
												</div>
												<div class="ms-2 nowrap">
													<input type="radio" id="alert-trigger3" name="alert-trigger" value="logic" style="height:20px;" class="external-modules-input-element align-middle">
													<label for="alert-trigger3" class="m-0 align-middle"><?=$lang['alerts_317']?> <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?=js_escape2($lang['alerts_319'])?>" data-title="<?=js_escape2($lang['alerts_318'])?>"></i></label>
												</div>
											</td>
										</tr>
										<tr class="form-control-custom" field="">
											<td colspan="2" class="external-modules-input-td pb-1 boldish">
												<?=RCView::span(array('style'=>'color:#0061b5;'), $lang['alerts_220'])." ".$lang['alerts_137']?>
											</td>
										</tr>
										<tr class="form-control-custom" field="form-name">
											<td colspan="2" class="external-modules-input-td pb-1 ps-3">
												<div class="nowrap">
													<span class="me-1 boldish"><?=$lang['alerts_140']?> </span>
													<?=RCView::select(array('name'=>"form-name",'class'=>'external-modules-input-element d-inline p-1', 'style'=>'width:300px;max-width:300px;',
														'onchange'=>""), $formAnyEventDropdownOptions+$formEventDropdownOptions, "", 200)?>
													<?=RCView::select(array('name'=>"email-incomplete",'class'=>'external-modules-input-element d-inline p-1 ms-1', 'style'=>'width:250px;max-width:250px;'),
														array('1'=>$lang['alerts_138'],'0'=>$lang['alerts_139']), "0")?>
													<span class="ms-2 fs12" style="color:gray;"><?=$lang['alerts_141']?></span>
												</div>
											</td>
										</tr>
										<tr class="form-control-custom" field="condition-andor">
											<td colspan="2" class="external-modules-input-td boldish pt-1 pb-1 ps-3">
												<?=$lang['alerts_142']?>
											</td>
										</tr>
										<tr class="form-control-custom" field="alert-condition">
											<td colspan="2" class="external-modules-input-td pb-0 ps-3">
												<div class="mb-1 boldish condition-andor-text2"><?=$lang['alerts_143']?></div>
												<textarea type="text" id="alert-condition" name="alert-condition" onfocus="openLogicEditor($(this))" onkeydown="logicSuggestSearchTip(this, event);" onblur='var val = this; setTimeout(function() { logicHideSearchTip(val); if(!checkLogicErrors(val.value,1)){ validate_logic(val.value,"",0,""); }; }, 0);' class="external-modules-input-element ms-4" style="max-width:95%;"></textarea>
												<div id="LSC_id_alert-condition" class="fs-item-parent fs-item"></div>
												<div style='border: 0; font-weight: bold; text-align: left; vertical-align: middle; height: 20px;' id='alert-condition_Ok'>&nbsp;</div>
												<div class="clearfix">
													<div class='my-1 ms-4 fs11 float-start text-secondary'><?php echo ($longitudinal ? "(e.g., [enrollment_arm_1][age] > 30 and [enrollment_arm_1][sex] = \"1\")" : "(e.g., [age] > 30 and [sex] = \"1\")") ?></div>
													<div class="float-end me-3" style="margin-top:1px;">
														<a href="javascript:;" style="text-decoration: underline;" class="fs11" onclick="simpleDialog('<?=js_escape($lang['alerts_34']."<br><br>".$lang['alerts_35'])?>','<?=js_escape($lang['alerts_33'])?>',null,650);"><i class="far fa-stop-circle me-1"></i><?=$lang['alerts_33']?></a>
													</div>
												</div>
												<div id='alert-condition_Ok' class='logicValidatorOkay ms-4'></div>
												<script type='text/javascript'>logicValidate($('#alert-condition'), false, 1);</script>
												<?php
												print logicAdd("alert-condition");
												print RCView::div(array('class'=>'mt-2 ms-4 alters-logic-recurrences'),
														RCView::div(array(),
                                                            RCView::checkbox(array('id'=>"ensure-logic-still-true", 'name'=>"ensure-logic-still-true", 'style'=>'width:15px;height:15px;position:relative;top:3px;')) .
                                                            '<label class="boldish" for="ensure-logic-still-true">'.$lang['alerts_30'] . '</label>' .
                                                            RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$lang['survey_189'], 'style'=>'','onclick'=>"simpleDialog('".js_escape($lang['alerts_31'])."','".js_escape($lang['alerts_30'])."');"), '?')
                                                        ) .
                                                        RCView::div(array('id'=>'do-not-clear-recurrences-parent'),
                                                            RCView::checkbox(array('id'=>"do-not-clear-recurrences", 'name'=>"do-not-clear-recurrences", 'style'=>'width:15px;height:15px;position:relative;top:3px;')) .
                                                            '<label class="boldish me-1" for="do-not-clear-recurrences">'.RCView::tt("alerts_412").'</label>' .
                                                            RCView::tt("alerts_413") .
                                                            RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$lang['survey_189'], 'style'=>'','onclick'=>"simpleDialog('".RCView::tt_js("alerts_414")."','".RCView::tt_js("alerts_412")."');"), '?')
                                                        )
													);
												?>
											</td>
										</tr>

										<tr class="form-control-custom" field="alert-stop-type">
											<?php if (count($stopTypes) > 1) { ?>
												<td colspan="2" class="external-modules-input-td pt-4">
													<label class="text-nowrap boldish">
														<?=RCView::span(array('style'=>'color:#0061b5;'), $lang['alerts_221'])." ".$lang['alerts_215']?>
														<?=RCView::select(array('name'=>"alert-stop-type",'class'=>'external-modules-input-element d-inline p-1 ms-1', 'style'=>'width:600px;max-width:600px;',
															'onchange'=>"showHideTriggerLimitInstanceWarning();"), $stopTypes, "RECORD", 200)?>
													</label>
													<div class='mb-1 ms-3 fs11 text-secondary'><?=$lang['alerts_229']?></div>
													<div id="warn-cond-logic-every-instance-trigger-limit" class='my-1 mx-3 pe-3 fs11 text-danger'><i class="fas fa-info-circle"></i> <?=$lang['alerts_264']?></div>
												</td>
											<?php } else { ?>
												<td colspan="2" class="pt-1"></td>
											<?php } ?>
										</tr>

										<tr class="form-control-custom" field="">
                                            <td colspan="2" class="external-modules-input-td pt-2 pb-2 ps-2 fs11 asterisk-notice-retrigger" style="color:#666;">
                                                <span class="em-ast">*</span><?=$lang['alerts_263']?>
                                            </td>
                                        </tr>

										<!-- Schedule settings -->
										<tr class="form-control-custom">
											<td colspan="2">
												<div class="form-control-custom-title boldish fs14"><i class="far fa-clock"></i> <?=$lang['alerts_145']?></div>
											</td>
										</tr>
										<tr class="form-control-custom" field="">
											<td class="align-text-top pe-2 ps-3" style="padding-top:0.3rem;">
												<label class="text-nowrap boldish"><?=$lang['alerts_146']?></label>
											</td>
											<td class="external-modules-input-td pb-2">
												<div class="ms-2">
													<input type="radio" id="cron-send-email-on1" name="cron-send-email-on" style="height:20px;" class="external-modules-input-element align-middle" value="now">
													<label for="cron-send-email-on1" class="m-0 align-middle"><?=$lang['global_1540']?></label>
												</div>
												<div class="ms-2 mt-3">
													<input type="radio" id="cron-send-email-on2" name="cron-send-email-on" style="height:20px;" class="external-modules-input-element align-middle" value="next_occurrence">
													<label for="cron-send-email-on2" class="m-0 align-middle">
														<?php
														print   $lang['survey_423'] . RCView::SP . RCView::SP .
																RCView::select(array('name'=>"cron-send-email-on-next-day-type", 'class'=>'external-modules-input-element d-inline py-0 px-1 me-1 fs12', 'style'=>'height:24px;width: 110px;max-width: 110px;'), $daysOfWeekDD, "") .
																$lang['survey_424'] . " " .
																RCView::input(array('name'=>"cron-send-email-on-next-time",'type'=>'text', 'class'=>'ms-1 py-0 px-1 fs12 external-modules-input-element d-inline time2',
																	'style'=>'text-align:center;width:48px;height:26px;', 'onblur'=>"redcap_validate(this,'','','soft_typed','time',1)",
																	'onfocus'=>"if( $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}")) .
																RCView::span(array('class'=>'df'), 'H:M');
														?>
													</label>
												</div>
												<div class="ms-2" style="margin-top:17px;margin-bottom:18px;">
													<input type="radio" id="cron-send-email-on3" name="cron-send-email-on" style="height:20px;margin-top:4px;" class="external-modules-input-element align-top" value="time_lag">
													<label for="cron-send-email-on3" class="m-0 align-middle">
														<?=$lang['alerts_239']?>
														<?php
														if (count($datetime_fields) == 1) print " " . $lang['survey_1293'];
														?>
														<input type="text" name="cron-send-email-on-time-lag-days" maxlength="4" class="ms-1 fs12 external-modules-input-element d-inline text-end"
															   style="height:24px;width:44px;" onblur="redcap_validate(this,'0','9999','hard','integer',1)">
														<?=$lang['survey_426']?>
														<input type="text" name="cron-send-email-on-time-lag-hours" maxlength="3" class="ms-1 fs12 external-modules-input-element d-inline text-end"
															   style="height:24px;width:35px;" onblur="redcap_validate(this,'0','999','hard','integer',1)">
														<?=$lang['survey_427']?>
														<input type="text" name="cron-send-email-on-time-lag-minutes" maxlength="3" class="ms-1 fs12 external-modules-input-element d-inline text-end"
															   style="height:24px;width:35px;" onblur="redcap_validate(this,'0','999','hard','integer',1)">
														<?=$lang['survey_428']?>
														<?php
														if (count($datetime_fields) > 1) {
															print  '<div class="mt-2 nowrap">'.
																		RCView::select(array('name'=>"cron-send-email-on-field-after", 'class'=>'external-modules-input-element d-inline py-0 px-1 ms-1',
																			'style'=>'height:24px;max-width:80px;width:80px;'), array('before'=>$lang['alerts_245'], 'after'=>$lang['alerts_238']), 'after', 200).
																		RCView::select(array('name'=>"cron-send-email-on-field", 'class'=>'external-modules-input-element d-inline py-0 px-1 ms-1',
																			'style'=>'height:24px;max-width:500px;width:500px;'), $datetime_fields, '', 200).
																		'<a href="javascript:;" class="help2" data-toggle="popover" data-trigger="hover" data-title="'.js_escape2($lang['global_03']).'" data-content="'.js_escape2($lang['alerts_320']).'" data-html="true">?</a>
																	</div>';
														}
														?>
													</label>
												</div>
												<div class="ms-2 mt-1">
													<input type="radio" id="cron-send-email-on4" name="cron-send-email-on" style="height:20px;" class="external-modules-input-element align-middle" value="date">
													<label for="cron-send-email-on4" class="m-0 align-middle">
														<?=$lang['survey_429']?>
														<input type="text" name="cron-send-email-on-date" class="ms-1 fs12 alert-datetimepicker external-modules-input-element d-inline"
															   placeholder="<?=str_replace(array('M','D','Y'),array('MM','DD','YYYY'),DateTimeRC::get_user_format_label())." HH:MM"?>"
															   style="height:26px;width:140px;" onblur="redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter)">
													</label>
												</div>
											</td>
										</tr>
										<tr class="form-control-custom">
											<td class="align-text-top pe-2 ps-3" style="padding-top:1.2rem;">
												<label class="text-nowrap boldish"><?=$lang['alerts_148']?></label>
											</td>
											<td class="external-modules-input-td pb-3 pt-3">
												<div class="ms-2">
													<input type="radio" id="alert-send-how-many1" name="alert-send-how-many" style="height:20px;" class="external-modules-input-element align-middle" value="once">
													<label for="alert-send-how-many1" class="m-0 align-middle"><?=$justOnceText?></label>
												</div>
												<div class="ms-2 mt-2">
													<span class="align-top">
														<input type="radio" id="alert-send-how-many2" name="alert-send-how-many" style="height:20px;" class="external-modules-input-element align-middle" value="every">
													</span>
													<label for="alert-send-how-many2" class="m-0 align-middle">
														<?=$lang['alerts_225']?>
														<?=RCView::select(array('id'=>"every-time-type", 'class'=>'external-modules-input-element d-inline py-0 px-1 ms-1', 'style'=>'height:24px;max-width:360px;width:360px;position:relative;top:2px;'),
															array('every'=>$lang['alerts_227'], 'every-change'=>$lang['alerts_230']." ".$lang['alerts_231'], 'every-change-calcs'=>$lang['alerts_230']), "every")?>
														<div class="fs12" style="color:#999;"><i><?=$lang['alerts_141']?></i></div>
													</label>
													<input type="hidden" name="email-repetitive" value="0">
													<input type="hidden" name="email-repetitive-change" value="0">
													<input type="hidden" name="email-repetitive-change-calcs" value="0">
													<input type="hidden" name="email-deleted" value="0">
												</div>
												<div id="email-repetitive-multipage-warning" class="fs11" style="color:#C00000;margin-left:32px;">
													<?=$lang['alerts_150']?>
												</div>
												<div class="ms-2 mt-2">
													<span class="align-top">
														<input type="radio" id="alert-send-how-many3" name="alert-send-how-many" style="height:20px;" class="external-modules-input-element align-middle" value="schedule">
													</span>
													<label for="alert-send-how-many3" class="m-0 align-middle">
														<div><?=$lang['alerts_232']?></div>
														<div style="margin-top:0.35rem;">
															<i class="fas fa-redo" style="margin-right:1px;"></i> <?=$lang['survey_735']?>
															<input type="text" name="cron-repeat-for" onblur="if (redcap_validate(this,'0','9999','soft_typed','number',1) && !isNumeric($(this).val())) $(this).val('0');" class="ps-1 pe-2 py-0 ms-1 text-end external-modules-input-element d-inline" style="height:24px;width:50px;position:relative;top:2px;">
															<?=RCView::select(array('name'=>"cron-repeat-for-units",'class'=>'external-modules-input-element d-inline py-0 px-1 me-1', 'style'=>'height:24px;max-width:90px;width:90px;position:relative;top:2px;'),
																array('MINUTES'=>$lang['survey_428'], 'HOURS'=>$lang['survey_427'], 'DAYS'=>$lang['survey_426']), "DAYS")?>
															<?=$lang['alerts_152'].$lang['period']?>
														</div>
														<div class="fs12" style="color:#999;margin-left:18px;margin-top:4px;"><i><?=$lang['survey_1513']?></i></div>
														<div style="margin-top:0.3rem;">
															<i class="far fa-calendar-times" style="margin-right:3px;"></i> <?=$lang['survey_737']?>
															<input type="text" name="cron-repeat-for-max" onblur="if (isNumeric($(this).val())) {$(this).val($(this).val()*1);} if (redcap_validate(this,'2','9999','soft_typed','integer',1) && $(this).val() < 2) {$(this).val('');}" class="ps-1 pe-2 py-0 mx-1 text-end external-modules-input-element d-inline" maxlength="4" style="height:24px;width:42px;position:relative;top:2px;">
															<?=$lang['alerts_233']?>
														</div>
														<div class="fs12" style="color:#999;margin-left:18px;margin-top:4px;"><i><?=$lang['alerts_234']?></i></div>
													</label>
												</div>
												<input type="checkbox" name="cron-queue" class="d-none">
											</td>
										</tr>

										<!-- Expiration -->
										<tr class="form-control-custom">
											<td class="ps-3 pt-3 align-text-top">
												<label class="mb-1 boldish"><?=$lang['alerts_170']?></label>
												<div class="text-secondary"><?=$lang['global_06']?></div>
											</td>
											<td class="pt-3 external-modules-input-td">
												<input type="text" name="alert-expiration" class="ms-1 fs12 alert-datetimepicker external-modules-input-element d-inline"
													   placeholder="<?=str_replace(array('M','D','Y'),array('MM','DD','YYYY'),DateTimeRC::get_user_format_label())." HH:MM"?>"
													   style="height:26px;width:140px;" onblur="redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter)">
												<div class="ms-2 mt-1 fs12" style="color:gray;">
													<?=$lang['alerts_171']?>
												</div>
											</td>
										</tr>

										<!-- Message -->
										<tr class="form-control-custom">
											<td colspan="2">
												<div class="form-control-custom-title boldish fs14">
													<i class="fas fa-envelope"></i> <?=$lang['alerts_153']?>
												</div>
											</td>
										</tr>
										<?php
											$display_twilio_options = (
                                                    ($GLOBALS['twilio_enabled_global'] || $GLOBALS['mosio_enabled_global'])
											        && (UserRights::isSuperUserNotImpersonator()
											            || $Proj->twilio_enabled_alerts
											            || ($GLOBALS['twilio_enabled_global'] && $GLOBALS['twilio_display_info_project_setup'])
											            || !($GLOBALS['twilio_enabled_global'] && $GLOBALS['twilio_enabled_by_super_users_only'])
											            || ($GLOBALS['mosio_enabled_global'] && $GLOBALS['mosio_display_info_project_setup'])
											            || !($GLOBALS['mosio_enabled_global'] && $GLOBALS['mosio_enabled_by_super_users_only'])
                                                       )
                                           );
											$display_sendgrid_options = ($sendgrid_enabled_global && (UserRights::isSuperUserNotImpersonator() || $sendgrid_display_info_project_setup || !$sendgrid_enabled_by_super_users_only || $Proj->project['sendgrid_enabled']));
										?>
										<?php if ($display_twilio_options || $display_sendgrid_options) { ?>
											<tr class="form-control-custom" field="alert-type">
												<td class="ps-3 pt-1 align-top">
													<label class="mb-1 boldish"><?=$lang['alerts_199']?></label>
												</td>
												<td class="external-modules-input-td pb-3">
													<div class="clearfix">
														<div class="me-4 d-inline">
															<input type="radio" id="alert-type-email" name="alert-type" value="EMAIL" style="height:20px;" class="external-modules-input-element align-middle" onclick="checkMessageSettings();">
															<label for="alert-type-email" class="m-0 align-middle"><i class='fas fa-envelope'></i> <?=$lang['global_33']?></label>
														</div>
														<?php if ($display_twilio_options) { ?>
														<div class="me-4 d-inline <?=($Proj->twilio_enabled_alerts ? "" : "opacity50")?>" <?=($Proj->twilio_enabled_alerts ? "" : "onclick=\"simpleDialog(null,null,'twilio-options-dialog');\"")?> <?=($Proj->twilio_enabled_alerts ? "" : "title=\"".RCView::tt_js2('alerts_349')."\"")?>>
															<input type="radio" id="alert-type-sms" name="alert-type" value="SMS" style="height:20px;" class="external-modules-input-element align-middle" <?=($Proj->twilio_enabled_alerts ? "" : "disabled")?> onclick="checkMessageSettings();">
															<label for="alert-type-sms" class="m-0 align-middle"><i class='fas fa-sms fs15'></i> <?=$lang['alerts_201']?></label>
														</div>
														<div class="me-4 d-inline <?=($Proj->twilio_enabled_alerts && $Proj->messaging_provider == Messaging::PROVIDER_TWILIO ? "" : "opacity50")?>" <?=($Proj->twilio_enabled_alerts ? "" : "onclick=\"simpleDialog(null,null,'twilio-options-dialog');\"")?> <?=($Proj->twilio_enabled_alerts ? "" : "title=\"".RCView::tt_js2('alerts_349')."\"")?>>
															<input type="radio" id="alert-type-voicecall" name="alert-type" value="VOICE_CALL" style="height:20px;" class="external-modules-input-element align-middle" <?=($Proj->twilio_enabled_alerts && $Proj->messaging_provider == Messaging::PROVIDER_TWILIO ? "" : "disabled")?> onclick="checkMessageSettings();">
															<label for="alert-type-voicecall" class="m-0 align-middle"><i class='fas fa-phone'></i> <?=$lang['alerts_202']?></label>
														</div>
														<?php } ?>
														<?php if ($display_sendgrid_options) { ?>
														<div class="me-4 d-inline <?=($Proj->project['sendgrid_enabled'] ? "" : "opacity50")?>" <?=($Proj->project['sendgrid_enabled'] ? "" : "onclick=\"simpleDialog(null,null,'sendgrid-options-dialog');\"")?> <?=($Proj->project['sendgrid_enabled'] ? "" : "title=\"".RCView::tt_js2('alerts_349')."\"")?>>
															<input type="radio" id="alert-type-sendgrid" name="alert-type" value="SENDGRID_TEMPLATE" style="height:20px;" class="external-modules-input-element align-middle" <?=($Proj->project['sendgrid_enabled'] ? "" : "disabled")?> onclick="checkMessageSettings();">
															<label for="alert-type-sendgrid" class="m-0 align-middle"><i class='fas fa-envelope'></i> <?=$lang['alerts_328']?></label>
														</div>
														<?php } ?>
													</div>
													<?php if ($display_twilio_options && !$Proj->twilio_enabled_alerts) {
														?><div id="twilio-options-dialog" class="simpleDialog" title="<?=RCView::tt_js2('alerts_201').($Proj->messaging_provider == Messaging::PROVIDER_TWILIO ? " / ".RCView::tt_js2('alerts_202') : "")?>"><?=($Proj->messaging_provider == Messaging::PROVIDER_TWILIO ? $lang['alerts_350'] : $lang['alerts_401'])?></div><?php
													} ?>
													<?php if ($display_sendgrid_options && !$Proj->project['sendgrid_enabled']) {
														?><div id="sendgrid-options-dialog" class="simpleDialog" title="<?=RCView::tt_js2('alerts_328')?>"><?=$lang['alerts_321']?></div><?php
													} ?>
												</td>
											</tr>
											<tr class="form-control-custom" field="phone-number-to">
												<td class="ps-3 pt-2 align-top">
													<label class="mb-1 boldish"><?=$lang['alerts_203']?></label>
												</td>
												<td class="external-modules-input-td pb-3">
													<?php
													print RCView::select(array('name'=>"phone-number-to", 'id'=>"phone-number-to", 'multiple'=>'',
																'class'=>'external-modules-input-element fs12', 'style'=>'height:100px;'), $toPhones, "", 200);
													if ($alerts_allow_phone_freeform || SUPER_USER) {
														?><div class="my-2">
															<div class="text-secondary fs11 pt-1">
																<?=$lang['alerts_204']?>
															</div>
															<input type="text" name="phone-number-to-freeform" class="fs12 external-modules-input-element d-inline" style="width:100%;" placeholder="(615) 867-5309; +52 55 1234 5678; 2707545555">
														</div>
														<?php
													}
													?>
												</td>
											</tr>
										<?php } ?>
										<tr class="requiredm form-control-custom" field="email-from">
											<td class="ps-3">
												<label class="mb-1 boldish"><?=$lang['alerts_154']?></label><div class="requiredlabel p-0">* <?=$lang['data_entry_39']?></div>
											</td>
											<td class="external-modules-input-td clearfix nowrap">
												<div class="float-start me-2 mt-1" style="width:150px;<?php if (!$GLOBALS['use_email_display_name']) print "display:none;"; ?>">
													<input type="text" name="email-from-display" class="fs12 external-modules-input-element d-inline" style="width:100%;" placeholder="<?=js_escape2($lang['survey_1270'])?>">
												</div>
												<div class="float-start me-2 mt-1" style="width:65%;max-width:380px;">
												<?=RCView::select(array('name'=>"email-from",'class'=>'external-modules-input-element'), $fromEmails, $user_email, 200)?>
												</div>
											</td>
										</tr>
										<tr class="requiredm form-control-custom" field="sendgrid-from">
											<td class="ps-3">
												<label class="mb-1 boldish"><?=$lang['alerts_323']?></label><div class="requiredlabel p-0">* <?=$lang['data_entry_39']?></div>
											</td>
											<td class="external-modules-input-td">
												<input type="text" name="email-from" class="fs12 external-modules-input-element d-inline" list="sendgridFromList" placeholder="<?=js_escape2($lang['alerts_344'])?>">
												<div id="sendgrid-zero-senders-message" class="float-start requiredlabel p-0" hidden>* <?=$lang['alerts_348']?></div>
												<datalist id="sendgridFromList"></datalist>
											</td>
										</tr>
										<tr class="form-control-custom" field="email-to">
											<td class="align-text-top pt-2 ps-3">
												<label class="mb-1 boldish"><?=$lang['alerts_155']?></label>
												<div class="requiredlabel p-0">* <?=$lang['data_entry_39']?></div>
												<a id="showCC" href="javascript:;" class="d-block fs12 mt-2 ms-4 font-weight-light" style="text-decoration:underline;"><i class="fas fa-plus me-1"></i><?=$lang['alerts_156']?></a>
											</td>
											<td class="external-modules-input-td pt-2">
												<?php
												print RCView::select(array('name'=>"email-to", 'id'=>"email-to", 'multiple'=>'',
															'class'=>'external-modules-input-element fs12', 'style'=>'height:100px;'), $toEmails, "", 200);
												if ($alerts_allow_email_freeform || SUPER_USER) {
													?><div class="fs12 text-secondary my-2">
														<div class="float-start me-2 mt-1"><?=$lang['alerts_157']?><?=$ddProjectFreeformLabel?><?=$lang['colon']?></div>
														<div style="overflow:hidden;">
															<input type="text" name="email-to-freeform" class="fs12 external-modules-input-element d-inline" style="height:26px;" placeholder="jane@example.com; john@mysite.org" onkeydown="if(event.keyCode == 13) return false;">
														</div>
													</div>
													<?php
												}
												?>
											</td>
										</tr>
										<tr class="form-control-custom" field="email-cc">
											<td class="fs12 align-text-top pt-4 pe-5 text-end font-weight-light"><label class="boldish"><?=$lang['alerts_158']?></label></td>
											<td class="pt-3 pb-1 external-modules-input-td">
												<?php
												print RCView::select(array('name'=>"email-cc", 'id'=>"email-cc", 'multiple'=>'',
														'class'=>'external-modules-input-element fs12', 'style'=>'height:100px;'), $toEmails, "", 200);
												if ($alerts_allow_email_freeform || SUPER_USER) {
													?><div class="fs12 text-secondary my-2">
														<div class="float-start me-2 mt-1"><?=$lang['alerts_157']?><?=$ddProjectFreeformLabel?><?=$lang['colon']?></div>
														<div style="overflow:hidden;">
															<input type="text" name="email-cc-freeform" class="fs12 external-modules-input-element d-inline" style="height:26px;" placeholder="jane@example.com; john@mysite.org" onkeydown="if(event.keyCode == 13) return false;">
														</div>
													</div>
													<?php
												}
												?>
											</td>
										</tr>
										<tr class="form-control-custom" field="email-bcc">
											<td class="fs12 align-text-top pt-4 pe-5 text-end font-weight-light"><label class="boldish"><?=$lang['alerts_159']?></label></td>
											<td class="pt-3 pb-1 external-modules-input-td">
												<?php
												print RCView::select(array('name'=>"email-bcc", 'id'=>"email-bcc", 'multiple'=>'',
														'class'=>'external-modules-input-element fs12', 'style'=>'height:100px;'), $toEmails, "", 200);
												if ($alerts_allow_email_freeform || SUPER_USER) {
													?><div class="fs12 text-secondary my-2">
													<div class="float-start me-2 mt-1"><?=$lang['alerts_157']?><?=$ddProjectFreeformLabel?><?=$lang['colon']?></div>
														<div style="overflow:hidden;">
															<input type="text" name="email-bcc-freeform" class="fs12 external-modules-input-element d-inline" style="height:26px;" placeholder="jane@example.com; john@mysite.org" onkeydown="if(event.keyCode == 13) return false;">
														</div>
													</div>
													<?php
												}
												?>
											</td>
										</tr>
										<tr class="form-control-custom" field="email-failed">
											<td class="fs12 pt-4 pb-4 pe-5 text-end font-weight-light"><label class="boldish wrap mb-0" style="max-width:140px;width:140px;"><?=$lang['alerts_160']?></label></td>
											<td class="pt-3 pb-3 external-modules-input-td">
												<?=RCView::select(array('name'=>"email-failed",'id'=>"email-failed",'class'=>'fs12 external-modules-input-element',
													'style'=>"height:28px;"), array(''=>'')+$fromEmails, "", 200)?>
											</td>
										</tr>
										<tr class="requiredm form-control-custom" field="email-subject">
											<td class="ps-3">
												<label class="mb-1 boldish"><?=$lang['email_users_10']?></label><div class="requiredlabel p-0">* <?=$lang['data_entry_39']?></div>
											</td>
											<td class="external-modules-input-td">
												<input type="text" name="email-subject" class="external-modules-input-element" value="">
											</td>
										</tr>
										<tr class="requiredm form-control-custom" field="sendgrid-template-id">
											<td class="ps-3">
												<label class="mb-1 boldish"><?=$lang['alerts_322']?></label><div class="requiredlabel p-0">* <?=$lang['data_entry_39']?></div>
											</td>
											<td class="external-modules-input-td">
                                                <select id="sendgrid-template-id" name="sendgrid-template-id" class="external-modules-input-element"></select>
                                                <div id='sendgrid-zero-templates-message' class="float-start requiredlabel p-0" hidden>* <?=$lang['alerts_347']?></div>
											</td>
										</tr>
										<tr class="requiredm form-control-custom" field="sendgrid-template-data">
											<td class="ps-3" id='sendgrid-template-data-label-td'>
												<label class="mb-1 boldish"><?=$lang['alerts_329']?></label><div class="requiredlabel p-0"></div>
											</td>
											<td class="external-modules-input-td">
												<input id="sendgrid-template-data" type="text" name="sendgrid-template-data" class="external-modules-input-element" value="" hidden=true>
												<input id="new-sendgrid-template-data-key" onkeyup="updateSendgridTemplateDataButtonText();" type="text" name="new-sendgrid-template-data-key" class="external-modules-input-element" value="" placeholder="<?=$lang['alerts_330']?>">
												<br>
												<textarea id="new-sendgrid-template-data-value" style="resize: vertical;" placeholder="<?=$lang['alerts_331']?>"></textarea>
												<br>
												<button id="sendgrid-template-data-button" class='btn btn-xs btn-rcpurple btn-rcpurple-light float-end' style='margin-bottom:18px' type="button" onClick="addSendgridTemplateData();"><?=$lang['alerts_332']?></button>
												<table id="sendgrid-template-data-table" class='table table-bordered table-hover' style='table-layout: fixed;'></table>
												<div class="float-start" style="width:25px;" id="prevent-piping-identifiers-sendgrid"></div>
												<!-- Piping link -->
												<div style='padding:8px 0px 2px;color:#555;font-size:12px;'>
													<div class="mb-2"><?=$lang['alerts_341']?></div>
													<?=$lang['alerts_352']?>
													<button class='btn btn-xs btn-rcpurple btn-rcpurple-light' style='margin-left:3px;margin-right:2px;font-size:11px;padding:0px 3px 1px;line-height: 14px;' onclick='pipingExplanation();return false;'><img src='<?=APP_PATH_IMAGES?>pipe.png' style='width:12px;position:relative;top:-1px;margin-right:2px;'><?=$lang['info_41']?></button>
													<?=$lang['global_43']?>
													<button class='btn btn-xs btn-rcgreen btn-rcgreen-light' style='margin-left:3px;font-size:11px;padding:0px 3px 1px;line-height:14px;'  onclick="smartVariableExplainPopup();return false;">[<i class='fas fa-bolt fa-xs' style='margin:0 1px;'></i>] <?=$lang['global_146']?></button>
												</div>
											</td>
										</tr>
                                        <!-- Advanced SendGrid Settings -->
                                        <tr id="sendgrid-advanced-settings-button-row">
                                            <td style='padding:8px 8px 24px 24px;' colspan="2"><a id='sendgrid-advanced-settings-button' href="javascript:;" onclick="toggleAdvancedSendgridSettingsDisplay();"><?=$lang['alerts_397']?></a></td>
                                        </tr>
										<tr id="advanced-sendgrid-settings-banner" class="form-control-custom">
											<td colspan="2">
												<div class="form-control-custom-title boldish fs14">
													<i class="fas fa-gear"></i> <?=$lang['alerts_395']?>
												</div>
                                                <div class="mb-2" style="padding-top: 8px;">
                                                    <?=$lang['alerts_396']?>
                                                </div>
											</td>
										</tr>
                                        <input id="sendgrid-mail-send-configuration" type="text" name="sendgrid-mail-send-configuration" class="external-modules-input-element" value="" hidden=true>
                                        <tr class="requiredm form-control-custom" field="sendgrid-unsubscribe-group">
											<td class="ps-3" id='sendgrid-unsubscribe-group-label-td'>
												<label class="mb-1 boldish"><?=$lang['alerts_369']?></label>
											</td>
											<td class="external-modules-input-td">
                                                <select name="sendgrid-unsubscribe-group" id="sendgrid-unsubscribe-group" class="external-modules-input-element fs12"></select>
                                                <div id='sendgrid-zero-unsubscribe-groups-message' class="float-start requiredlabel p-0" hidden>* <?=$lang['alerts_370']?></div>
											</td>
										</tr>
                                        <tr class="requiredm form-control-custom" field="sendgrid-categories">
											<td class="ps-3" id='sendgrid-categories-label-td'>
												<label class="mb-1 boldish"><?=$lang['alerts_371']?></label>
											</td>
											<td class="external-modules-input-td">
                                                <input type="text" name="sendgrid-categories" id="sendgrid-categories" onkeyup="validateSendGridCategories();" placeholder="<?=$lang['alerts_372']?>" class="external-modules-input-element" value="">
                                                <div id='sendgrid-categories-error-message' class="float-start requiredlabel p-0" hidden>* <?=$lang['alerts_367']?></div>
                                            </td>
										</tr>
                                        <tr class="requiredm form-control-custom" field="sendgrid-mail-settings">
											<td class="ps-3" id='sendgrid-mail-settings-label-td'>
												<label class="mb-1 boldish"><?=$lang['alerts_373']?></label>
											</td>
											<td class="external-modules-input-td">
                                                <div class="me-2 mt-1">
                                                    <input type="checkbox" onchange="validateSendGridBypassOptions(this);" name="sendgrid-bypass-list-management" id="sendgrid-bypass-list-management" class="external-modules-input-element" value="">
                                                    <?=$lang['alerts_358']?> <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?=js_escape2($lang['alerts_387'])?>" data-title="<?=js_escape2($lang['alerts_358'])?>"></i>
                                                    <div class="requiredlabel p-0">* <?=$lang['alerts_368']?></div>
                                                </div>
                                                <div class="me-2 mt-1">
                                                    <input type="checkbox" onchange="validateSendGridBypassOptions(this);" name="sendgrid-bypass-spam-management" id="sendgrid-bypass-spam-management" class="external-modules-input-element" value="">
                                                    <?=$lang['alerts_359']?> <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?=js_escape2($lang['alerts_388'])?>" data-title="<?=js_escape2($lang['alerts_359'])?>"></i>
                                                </div>
                                                <div class="me-2 mt-1">
                                                    <input type="checkbox" onchange="validateSendGridBypassOptions(this);" name="sendgrid-bypass-bounce-management" id="sendgrid-bypass-bounce-management" class="external-modules-input-element" value="">
                                                    <?=$lang['alerts_360']?> <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?=js_escape2($lang['alerts_389'])?>" data-title="<?=js_escape2($lang['alerts_360'])?>"></i>
                                                </div>
                                                <div class="me-2 mt-1">
                                                    <input type="checkbox" onchange="validateSendGridBypassOptions(this);" name="sendgrid-bypass-unsubscribe-management" id="sendgrid-bypass-unsubscribe-management" class="external-modules-input-element" value="">
                                                    <?=$lang['alerts_361']?> <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?=js_escape2($lang['alerts_390'])?>" data-title="<?=js_escape2($lang['alerts_361'])?>"></i>
                                                </div>
                                                <div class="me-2 mt-1">
                                                    <input type="checkbox" name="sendgrid-sandbox-mode" id="sendgrid-sandbox-mode" class="external-modules-input-element" value="">
                                                    <?=$lang['alerts_362']?> <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?=js_escape2($lang['alerts_391'])?>" data-title="<?=js_escape2($lang['alerts_362'])?>"></i>
                                                </div>
											</td>
										</tr>
                                        <tr class="requiredm form-control-custom" field="sendgrid-tracking-settings">
											<td class="ps-3" id='sendgrid-tracking-settings-label-td'>
												<label class="mb-1 boldish"><?=$lang['alerts_363']?></label>
											</td>
											<td class="external-modules-input-td">
                                                <div class="me-2 mt-1">
                                                    <input type="checkbox" name="sendgrid-click-tracking" id="sendgrid-click-tracking" class="external-modules-input-element" value="">
                                                    <?=$lang['alerts_364']?> <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?=js_escape2($lang['alerts_392'])?>" data-title="<?=js_escape2($lang['alerts_364'])?>"></i>
                                                </div>
                                                <div class="me-2 mt-1">
                                                    <input type="checkbox" name="sendgrid-open-tracking" id="sendgrid-open-tracking" class="external-modules-input-element" value="">
                                                    <?=$lang['alerts_365']?> <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?=js_escape2($lang['alerts_393'])?>" data-title="<?=js_escape2($lang['alerts_365'])?>"></i>
                                                </div>
                                                <div class="me-2 mt-1">
                                                    <input type="checkbox" name="sendgrid-subscription-tracking" id="sendgrid-subscription-tracking" class="external-modules-input-element" value="">
                                                    <?=$lang['alerts_366']?> <i class="fas fa-info-circle text-secondary" data-toggle="popover" data-trigger="hover" data-content="<?=js_escape2($lang['alerts_394'])?>" data-title="<?=js_escape2($lang['alerts_366'])?>"></i>
                                                </div>
											</td>
										</tr>
										<tr class="requiredm form-control-custom" field="alert-message">
											<td class="align-text-top pt-2 ps-3"  id='alert-message-label-td'>
												<label class="mb-1 boldish"><?=$lang['messaging_105']?></label>
												<div class="requiredlabel p-0">* <?=$lang['data_entry_39']?></div>
												<div class="mt-4 me-3 p-2" style="overflow:hidden;color:#C00000;background-color:#f7f7f7;border:1px solid #ddd;"  id="prevent-piping-identifiers-box">
													<div class="float-start" style="width:25px;"><input type="checkbox" id="prevent-piping-identifiers" name="prevent-piping-identifiers" style="height: 15px;position: relative;top: 4px;"></div>
													<div style="overflow:hidden;">
														<label class="boldish fs12 m-0" for="prevent-piping-identifiers">
															<?=$lang['alerts_12']?>
															<a href="javascript:;" class="help ms-1" onclick="simpleDialog('<?=js_escape($lang['alerts_13'])?>','<?=js_escape($lang['alerts_12'])?>');">?</a>
														</label>
													</div>
												</div>
											</td>
											<td class="external-modules-input-td">
												<textarea class="external-modules-rich-text-field" name="alert-message" id="alert-message" onkeydown=""></textarea>
												<!-- Piping link -->
												<div style='padding:8px 0px 2px;color:#555;font-size:11px;'>
													<?=$lang['alerts_162']?>
													<button class='btn btn-xs btn-rcpurple btn-rcpurple-light' style='margin-left:3px;margin-right:2px;font-size:11px;padding:0px 3px 1px;line-height: 14px;' onclick='pipingExplanation();return false;'><img src='<?=APP_PATH_IMAGES?>pipe.png' style='width:12px;position:relative;top:-1px;margin-right:2px;'><?=$lang['info_41']?></button>
													<?=$lang['global_43']?>
													<button class='btn btn-xs btn-rcgreen btn-rcgreen-light' style='margin-left:3px;font-size:11px;padding:0px 3px 1px;line-height:14px;'  onclick="smartVariableExplainPopup();return false;">[<i class='fas fa-bolt fa-xs' style='margin:0 1px;'></i>] <?=$lang['global_146']?></button>
													<div style='margin-top:8px;color:#999;font-size:11px;font-family:verdana;'>
														<?=$lang['alerts_357']?>
													</div>
													<div style='margin-top:15px;font-size:11px;'>
														<i class="fas fa-info-circle"></i> <?=$lang['design_747']?> <a href="javascript:;" onclick="repeatingSurveyExplainPopup();" style="text-decoration:underline;font-size:11px;"><?=$lang['design_1026']?></a>
													</div>
													<div style='margin-top:8px;color:#999;font-size:11px;font-family:verdana;'>
														<?=$lang['alerts_356']?>
													</div>
												</div>
												<?=logicAdd("alert-message_ifr")?>
											</td>
										</tr>
										<tr field="email-attachment-btn" class="form-control-custom">
											<td>
												<button id="showAttachments" class="btn btn-rcgreen btn-xs fs12 py-1 px-2 ms-2 mb-1" style="">
													<i class="fas fa-paperclip me-1"></i><?=$lang['alerts_164']?>
												</button>
											</td>
											<td></td>
										</tr>
										<tr field="email-attachment-hdr" class="form-control-custom"><td colspan="2"><div class="form-control-custom-title boldish"><i class="fas fa-paperclip"></i> <?=$lang['alerts_165']?> <span class="ms-3 font-weight-normal fs12">(<?php echo $lang["data_entry_63"] . " " . self::MAX_ATTACHMENT_SIZE_MB ?>MB)</span></div></td></tr>
										<?php if (!empty($fieldUploadFieldOptions)) { ?>
										<tr field="email-attachment-variable" class="form-control-custom">
											<td class="align-text-top pt-2">
												<label><?=$lang['alerts_166']?></label>
												<div class="fs11" style="color:#888;">
													<?=$lang['alerts_167']?>
												</div>
											</td>
											<td class="external-modules-input-td pb-0">
												<?php
												print RCView::select(array('name'=>"email-attachment-variable", 'id'=>"email-attachment-variable", 'multiple'=>'',
															'class'=>'external-modules-input-element fs12', 'style'=>(count($fieldUploadFieldOptions) > 2 ? 'height:80px;' : 'height:45px;')), $fieldUploadFieldOptions, "", 200);
												?>
											</td>
										</tr>
										<tr class="form-control-custom email-attachment-andor">
											<td class="ps-3 fs12" colspan="2">
												<?=$lang['alerts_168']?>
											</td>
										</tr>
										<?php } ?>
										<tr field="email-attachment1" class="form-control-custom"><td class="email-attach-label-td align-text-top"><label><?=$lang['alerts_169']?> #1:</label></td><td class="external-modules-input-td align-text-top"><input type="file" name="email-attachment1" value="" class="external-modules-input-element" onchange="if(!fileTypeAllowed(basename($(this).val()))){$(this).val('');Swal.fire(window.lang.docs_1136,'','error');}"></td></tr>
										<tr field="email-attachment2" class="form-control-custom"><td class="email-attach-label-td align-text-top"><label><?=$lang['alerts_169']?> #2:</label></td><td class="external-modules-input-td align-text-top"><input type="file" name="email-attachment2" value="" class="external-modules-input-element" onchange="if(!fileTypeAllowed(basename($(this).val()))){$(this).val('');Swal.fire(window.lang.docs_1136,'','error');}"></td></tr>
										<tr field="email-attachment3" class="form-control-custom"><td class="email-attach-label-td align-text-top"><label><?=$lang['alerts_169']?> #3:</label></td><td class="external-modules-input-td align-text-top"><input type="file" name="email-attachment3" value="" class="external-modules-input-element" onchange="if(!fileTypeAllowed(basename($(this).val()))){$(this).val('');Swal.fire(window.lang.docs_1136,'','error');}"></td></tr>
										<tr field="email-attachment4" class="form-control-custom"><td class="email-attach-label-td align-text-top"><label><?=$lang['alerts_169']?> #4:</label></td><td class="external-modules-input-td align-text-top"><input type="file" name="email-attachment4" value="" class="external-modules-input-element" onchange="if(!fileTypeAllowed(basename($(this).val()))){$(this).val('');Swal.fire(window.lang.docs_1136,'','error');}"></td></tr>
										<tr field="email-attachment5" class="form-control-custom"><td class=" align-text-top"><label><?=$lang['alerts_169']?> #5:</label></td><td class="external-modules-input-td align-text-top"><input type="file" name="email-attachment5" value="" class="external-modules-input-element"></td></tr>

									</table>
									<input type="hidden" value="" id="index_modal_update" name="index_modal_update">
								</div>

								<div class="simpleDialog" id="prevent-piping-dialog" title="<?=js_escape2($lang['alerts_14'])?>">
									<?=$lang['alerts_15']?> <b><?=$lang['alerts_16']?></b>
								</div>

								<div class="modal-footer">
									<button class="btn btn-rcgreen" id="btnModalsaveAlert" onclick="return false;"><?=$lang['designate_forms_13']?></button>
									<button class="btn btn-defaultrc" id="btnCloseCodesModal" data-bs-dismiss="modal" onclick="return false;"><?=$lang['global_53']?></button>
								</div>
							</div>
						</div>
					</div>
					<div class="modal fade" id="external-modules-configure-modal-schedule-confirmation" tabindex="-1" role="dialog" data-toggle="modal" data-bs-backdrop="static" data-bs-keyboard="true">

						<div class="modal-dialog modal-lg" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<button type="button" class="close closeCustomModal btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
									<h4 class="modal-title" id="myModalLabel"><?=$lang['alerts_405']?></h4>
								</div>
								<div class="modal-body yellow">
									<?=$lang['alerts_406']?>
								</div>
								<div class="modal-footer">
									<button type="submit" form="saveAlert" class="btn btn-success" id="btnModalRescheduleForm"><?=$lang['alerts_407']?></button>
									<button type="submit" form="saveAlert" class="btn btn-warning" id="btnModalRescheduleForm2"><?=$lang['alerts_408']?></button>
									<button class="btn btn-defaultrc btn-cancel" id="btnCloseCodesModalDelete" data-bs-dismiss="modal"><?=$lang['global_53']?></button>
								</div>
							</div>
						</div>

					</div>
				</form>
			</div>

			<div class="modal fade" id="external-modules-configure-modal-delete-user-confirmation" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true">
				<form class="form-horizontal" action="" method="post" id='deleteUserForm'>
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<button type="button" class="close closeCustomModal btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								<h4 class="modal-title" id="myModalLabel"><?=$lang['alerts_176']?></h4>
							</div>
							<div class="modal-body">
								<span><?=$lang['alerts_177']?></span>
								<input type="hidden" value="" id="index_modal_delete_user" name="index_modal_delete_user">
								<input type="hidden" value="<?=APP_PATH_WEBROOT.'index.php?pid='.PROJECT_ID.'&route=AlertsController:deleteAlert'?>" id="url_modal_delete_user" name="url_modal_delete_user">
							</div>

							<div class="modal-footer">
								<button type="submit" form="deleteUserForm" class="btn btn-danger"><?=$lang['alerts_178']?></button>
								<button type="button" class="btn btn-defaultrc btn-cancel" data-bs-dismiss="modal"><?=$lang['global_53']?></button>
							</div>
						</div>
					</div>
				</form>
			</div>

			<div class="modal fade" id="external-modules-configure-modal-delete-confirmation" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true">
				<form class="form-horizontal" action="" method="post" id='deleteForm'>
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<button type="button" class="close closeCustomModal btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								<h4 class="modal-title" id="myModalLabel"><?=$lang['alerts_179']?></h4>
							</div>
							<div class="modal-body">
								<span><?=$lang['alerts_180']?></span>
								<br/>
								<span style="color:red;font-weight: bold"><?=$lang['alerts_181']?></span>
								<input type="hidden" value="" id="index_modal_delete" name="index_modal_delete">
							</div>

							<div class="modal-footer">
								<button type="submit" form="deleteForm" class="btn btn-default btn-delete"><?=$lang['global_19']?></button>
								<button type="button" class="btn btn-defaultrc btn-cancel" data-bs-dismiss="modal"><?=$lang['global_53']?></button>
							</div>
						</div>
					</div>
				</form>
			</div>


			<div class="modal fade" id="external-modules-configure-modal-record" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" data-bs-focus="false">
				<form class="form-horizontal" action="" method="post" id='selectPreviewRecord'>
					<div class="modal-dialog" role="document" style="width: 800px">
						<div class="modal-content">
							<div class="modal-header">
								<button type="button" class="close closeCustomModal btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								<h4 class="modal-title" id="myModalLabel"><?=$lang['alerts_183']?> <span id="modalRecordNumber"></span></h4>
							</div>
							<div class="modal-body form-control-custom">
								<div style="padding-bottom: 10px;"><?=$lang['alerts_182']?></div>
								<div id="load_preview_record"></div>
								<div>
									<input type="hidden" value="" id="index_modal_record_preview" name="index_modal_record_preview">
									<div id="modal_message_record_preview"></div>
								</div>
							</div>

							<div class="modal-footer">
								<button type="button" class="btn btn-defaultrc" data-bs-dismiss="modal"><?=$lang['calendar_popup_01']?></button>
							</div>
						</div>
					</div>
				</form>
			</div>
			<!-- MOVE ALERT DIALOG POP-UP -->
	        <div id="move_alert_popup" title="<?php echo js_escape2($lang['alerts_268']) ?>" style="display: none;"></div>
		<?php
		if (isset($_SESSION['move_alert_msg']) && !empty($_SESSION['move_alert_msg'])) {
		    $alertId = $_SESSION['focus_alert_id'];
		    $allAlerts = $this->getAlertSettings(PROJECT_ID);
		    $email_deleted = $allAlerts[$alertId]['email_deleted'];
            ?>
            <script type="text/javascript">
                simpleDialog('<?php echo js_escape($_SESSION['move_alert_msg']) ?>', '<?php echo js_escape($lang['design_346']) ?>', null, 600, "scrollToAlert('<?=$_SESSION['focus_alert_id']?>', '<?=$email_deleted?>')");
            </script>
            <?php
            unset($_SESSION['move_alert_msg']);
            unset($_SESSION['focus_alert_id']);
        }
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    }

    // Create a new alert or update an existing alert
    public function saveAlert()
    {
        global $lang;
        $Proj = new Project(PROJECT_ID);

        // Are we creating a new alert or updating an existing one?
        $newAlert = !(isset($_POST['index_modal_update']) && is_numeric($_POST['index_modal_update']));

        // Get default values from table
        $alert = getTableColumns('redcap_alerts');
        unset($alert['alert_id']);

        // Rework POST keys/values
        if ($newAlert) {
            $_POST['project_id'] = PROJECT_ID;
        } else {
            unset($alert['project_id']);
            $alert_id = (int)$_POST['index_modal_update'];
            $updateQueue = isset($_POST['cron-queue']);
            unset($_POST['index_modal_update'], $_POST['cron-queue']);
        }

        // Gather existing values for this alert
        $alertBefore = array();
        if (!$newAlert) {
            $sql = "select * from redcap_alerts where project_id = " . PROJECT_ID . " and alert_id = $alert_id";
            $q = db_query($sql);
            $alertBefore = db_fetch_assoc($q);
        }

		// Make sure we have all values from POST
		if (isset($_POST['alert-message-editor'])) {
			$_POST['alert-message'] = $_POST['alert-message-editor'];
		}
		$_POST['alert-message'] = trim($_POST['alert-message']);
		$_POST['phone-number-to'] = str_replace(array(",", "(", ")", " ", "+"), array(";","","","",""), $_POST['phone-number-to']);
		$_POST['phone-number-to-freeform'] = str_replace(array(",", "(", ")", "-", " ", "+"), array(";","","","","",""), $_POST['phone-number-to-freeform']);
		$_POST['sendgrid-template-id'] = trim($_POST['sendgrid-template-id'] ?? "");
		$_POST['sendgrid-template-data'] = trim($_POST['sendgrid-template-data'] ?? "");
		$_POST['email-to'] = str_replace(array(","," "), array(";",""), $_POST['email-to']);
		$_POST['email-cc'] = str_replace(array(","," "), array(";",""), $_POST['email-cc']);
		$_POST['email-bcc'] = str_replace(array(","," "), array(";",""), $_POST['email-bcc']);
		$_POST['email-to-freeform'] = str_replace(array(","," "), array(";",""), $_POST['email-to-freeform']);
		$_POST['email-cc-freeform'] = str_replace(array(","," "), array(";",""), $_POST['email-cc-freeform']);
		$_POST['email-bcc-freeform'] = str_replace(array(","," "), array(";",""), $_POST['email-bcc-freeform']);
		$_POST['email-incomplete'] = (isset($_POST['email-incomplete']) && $_POST['email-incomplete'] == '0') ? '0' : '1';
		$_POST['email-repetitive'] = (isset($_POST['email-repetitive']) && $_POST['email-repetitive'] == '1') ? '1' : '0';
		$_POST['email-repetitive-change'] = (isset($_POST['email-repetitive-change']) && $_POST['email-repetitive-change'] == '1') ? '1' : '0';
		$_POST['email-repetitive-change-calcs'] = (isset($_POST['email-repetitive-change-calcs']) && $_POST['email-repetitive-change-calcs'] == '1') ? '1' : '0';
		if ($_POST['email-repetitive-change'] == '1') $_POST['email-repetitive'] = '0';
		if ($_POST['alert-send-how-many'] == 'once') {
			$_POST['cron-repeat-for'] = '0';
		}
		if ($_POST['email-repetitive'] || $_POST['email-repetitive-change']) {
			$_POST['cron-repeat-for'] = '0';
			$_POST['cron-repeat-for-max'] = '';
			$_POST['cron-send-email-on'] = 'now';
			$_POST['cron-send-email-on-date'] = '';
			$_POST['alert-stop-type'] = 'RECORD_EVENT_INSTRUMENT_INSTANCE';
		}
		if (!isset($_POST['alert-stop-type'])) {
			$_POST['alert-stop-type'] = 'RECORD';
		}
		if ($_POST['cron-repeat-for'] == '0') {
			$updateQueue = false;
		}
		if ($_POST['cron-repeat-for'] == 0 || ($_POST['cron-repeat-for-units'] != 'MINUTES' && $_POST['cron-repeat-for-units'] != 'HOURS')) {
			$_POST['cron-repeat-for-units'] = 'DAYS';
		}
		if (!is_numeric($_POST['cron-repeat-for-max']) || $_POST['cron-repeat-for-max'] < 2 || $_POST['cron-repeat-for'] == '0') {
			$_POST['cron-repeat-for-max'] = '';
		}
		if ($_POST['cron-send-email-on'] == 'next_occurrence') {
			if ($_POST['cron-send-email-on-next-time'] == '') {
				$_POST['cron-send-email-on'] = 'now';
			} else {
				$_POST['cron-send-email-on-next-time'] .= ":00";
				$_POST['cron-send-email-on-time-lag-days'] = $_POST['cron-send-email-on-time-lag-hours'] = $_POST['cron-send-email-on-time-lag-minutes'] =
					$_POST['cron-send-email-on-date'] = $_POST['cron-send-email-on-field'] = '';
			}
		}
		if ($_POST['cron-send-email-on'] == 'time_lag') {
			if (!is_numeric($_POST['cron-send-email-on-time-lag-days'])) $_POST['cron-send-email-on-time-lag-days'] = '0';
			if (!is_numeric($_POST['cron-send-email-on-time-lag-hours'])) $_POST['cron-send-email-on-time-lag-hours'] = '0';
			if (!is_numeric($_POST['cron-send-email-on-time-lag-minutes'])) $_POST['cron-send-email-on-time-lag-minutes'] = '0';
			if ($_POST['cron-send-email-on-field'] == '' && $_POST['cron-send-email-on-time-lag-days'] + $_POST['cron-send-email-on-time-lag-hours'] + $_POST['cron-send-email-on-time-lag-minutes'] <= 0) {
				$_POST['cron-send-email-on'] = 'now';
			} else {
				$_POST['cron-send-email-on-next-day-type'] = $_POST['cron-send-email-on-next-time'] =
					$_POST['cron-send-email-on-date'] = '';
			}
		}
		if ($_POST['cron-send-email-on'] == 'date') {
			if ($_POST['cron-send-email-on-date'] == '') {
				$_POST['cron-send-email-on'] = 'now';
			} else {
				$_POST['cron-send-email-on-time-lag-days'] = $_POST['cron-send-email-on-time-lag-hours'] = $_POST['cron-send-email-on-time-lag-minutes'] =
					$_POST['cron-send-email-on-next-day-type'] = $_POST['cron-send-email-on-next-time'] = $_POST['cron-send-email-on-field'] = '';
			}
		}
		if ($_POST['cron-send-email-on'] == 'now') {
			$_POST['cron-send-email-on-time-lag-days'] = $_POST['cron-send-email-on-time-lag-hours'] = $_POST['cron-send-email-on-time-lag-minutes'] =
				$_POST['cron-send-email-on-next-day-type'] = $_POST['cron-send-email-on-next-time'] =
				$_POST['cron-send-email-on-date'] = $_POST['cron-send-email-on-field'] = '';
		}
		if ($_POST['cron-send-email-on'] == 'field') {
			$_POST['cron-send-email-on-time-lag-days'] = $_POST['cron-send-email-on-time-lag-hours'] = $_POST['cron-send-email-on-time-lag-minutes'] =
				$_POST['cron-send-email-on-next-day-type'] = $_POST['cron-send-email-on-next-time'] =
				$_POST['cron-send-email-on-date'] = '';
		}
		if ($_POST['cron-send-email-on-field'] == '') {
			$_POST['cron-send-email-on-field-after'] = 'after';
		}
		$_POST['cron-send-email-on-date'] = DateTimeRC::format_ts_to_ymd($_POST['cron-send-email-on-date']);
		$_POST['alert-expiration'] = DateTimeRC::format_ts_to_ymd($_POST['alert-expiration']);
		list ($_POST['form-name'], $_POST['form-name-event']) = explode("-", $_POST['form-name']??"", 2);
		if ($_POST['form-name'] != '' && !isset($Proj->forms[$_POST['form-name']])) $_POST['form-name'] = '';
		if ($_POST['alert-trigger'] == 'logic') $_POST['form-name'] = $_POST['form-name-event'] = '';
		if ($_POST['form-name-event'] != '' && !isset($Proj->eventInfo[$_POST['form-name-event']])) $_POST['form-name-event'] = '';
		$_POST['alert-condition'] = ($_POST['alert-trigger'] == 'submit') ? "" : trim($_POST['alert-condition']);
		$_POST['ensure-logic-still-true'] = (isset($_POST['ensure-logic-still-true']) && $_POST['alert-condition'] != '') ? '1' : '0';
		$_POST['do-not-clear-recurrences'] = (isset($_POST['do-not-clear-recurrences']) && $_POST['alert-condition'] != '') ? '1' : '0';
		$_POST['prevent-piping-identifiers'] = (isset($_POST['prevent-piping-identifiers'])) ? '1' : '0';
		if ($_POST['cron-send-email-on-next-day-type'] == '') $_POST['cron-send-email-on-next-day-type'] = 'DAY';
		$_POST['email-attachment-variable'] = isset($_POST['email-attachment-variable']) ? str_replace(array(","," "), array(";",""), $_POST['email-attachment-variable']) : "";
		if ($_POST['form-name'] == '') {
		    $_POST['email-repetitive'] = $_POST['email-repetitive-change'] = $_POST['email-repetitive-change-calcs'] = '0';
		}

        // Validate the From email address
        if (!isset($_POST['alert-type']) || $_POST['alert-type'] == "EMAIL")
        {
            // Get email addresses and names from table
            $fromEmails = User::getEmailAllProjectUsers(PROJECT_ID);
            if (!in_array(strtolower($GLOBALS['user_email']), $fromEmails)) {
                $fromEmails[] = strtolower($GLOBALS['user_email']);
            }
            // If the From email is not valid, then just set it to the user's primary email
            if (!in_array($_POST['email-from'], $fromEmails)) {
                $_POST['email-from'] = $GLOBALS['user_email'];
            }
        }

        // If restricting users from using email-validated fields, then remove if any that don't previously exist (admins are exempt)
        $restrictedFields = array('email-to', 'email-cc', 'email-bcc');
        if (!$GLOBALS['alerts_allow_email_variables'] && !SUPER_USER)
        {
            foreach ($restrictedFields as $this_field) {
                if (!isset($_POST[$this_field])) continue;
                $pieces = explode(";", $_POST[$this_field]);
                foreach ($pieces as $pkey=>$piece) {
                    // Only remove email field variables
                    if ($piece != self::participant_email_var && !isEmail($piece)) {
                        if ($newAlert) {
                            // Remove this since this is a new alert being created
                            unset($pieces[$pkey]);
                        } else {
                            // Check if already existed for this existing alert
                            $this_field_underscore = str_replace("-", "_", $this_field);
                            $piecesBefore = explode(";", $alertBefore[$this_field_underscore]);
                            if (!in_array($piece, $piecesBefore)) {
                                unset($pieces[$pkey]);
                            }
                        }
                    }
                }
                $_POST[$this_field] = implode(";", $pieces);
            }
        }

        // If restricting users from using freeform emails, then remove if any that don't previously exist (admins are exempt)
        $restrictedFieldsFreeform = array('email-to-freeform', 'email-cc-freeform', 'email-bcc-freeform');
        if (!$GLOBALS['alerts_allow_email_freeform'] && !SUPER_USER) {
            foreach ($restrictedFieldsFreeform as $this_field) {
                if (!isset($_POST[$this_field])) continue;
                $pieces = explode(";", $_POST[$this_field]);
                foreach ($pieces as $pkey=>$piece) {
                    if ($newAlert || !isEmail($piece)) {
                        unset($pieces[$pkey]);
                    } elseif (!$newAlert) {
                        // Check if already existed for this existing alert
                        $this_field_underscore = str_replace("-", "_", $this_field);
                        $piecesBefore = explode(";", $alertBefore[$this_field_underscore]);
                        if (!in_array($piece, $piecesBefore)) {
                            unset($pieces[$pkey]);
                        }
                    }
                }
                $_POST[$this_field] = implode(";", $pieces);
            }
        }
        $_POST['email-to'] = trim(trim(implode(";", array_merge( explode(";", $_POST['email-to']), explode(";", $_POST['email-to-freeform']) ))),";");
        $_POST['email-cc'] = trim(trim(implode(";", array_merge( explode(";", $_POST['email-cc']), explode(";", $_POST['email-cc-freeform']) ))),";");
        $_POST['email-bcc'] = trim(trim(implode(";", array_merge( explode(";", $_POST['email-bcc']), explode(";", $_POST['email-bcc-freeform']) ))),";");
        $_POST['phone-number-to'] = trim(trim(implode(";", array_merge( explode(";", $_POST['phone-number-to']), explode(";", $_POST['phone-number-to-freeform']) ))),";");

		if (isset($_POST['alert-type']) && $_POST['alert-type'] != "EMAIL" && $_POST['alert-type'] != "SENDGRID_TEMPLATE") {
			$_POST['email-from-display'] = $_POST['email-from'] = $_POST['email-to'] = $_POST['email-cc'] = $_POST['email-bcc'] = $_POST['email-subject'] = "";
		}

		if (isset($_POST['alert-type']) && $_POST['alert-type'] == "SENDGRID_TEMPLATE") {
			$_POST['email-from-display'] = $_POST['email-subject'] = "";
		}

		if (isset($_POST['alert-type']) && $_POST['alert-type'] != "SENDGRID_TEMPLATE") {
			// clear sendgrid specific alert data
			$_POST['sendgrid-template-id'] = '';
			$_POST['sendgrid-template-data'] = '{}';
            $_POST['sendgrid-mail-send-configuration'] = '{}';
		}

		// Add values from POST
		foreach ($_POST as $key=>$val) {
			$key = str_replace('-', '_', $key);
			if (!array_key_exists($key, $alert)) continue;
			$alert[$key] = $val;
		}

        // Add logging info
        $alertsLogging = array();
        $loggingIgnore = array('email_timestamp_sent', 'email_sent', 'email_deleted', 'project_id');
        foreach ($alert as $key => $val) {
            if (in_array($key, $loggingIgnore)) continue;
            // Rework some to be more user friendly
            if ($key == 'form_name_event') {
                if (!$Proj->longitudinal) continue;
                if ($val == '') {
                    $val = 'any';
                } else {
                    $val = $Proj->getUniqueEventNames($val);
                }
            }
            elseif ($key == 'email_incomplete') {
                if (isset($alert[$key]['form_name']) && $alert[$key]['form_name'] == '') continue;
                $key = 'trigger_on_instrument_save_status';
                $val = ($val == '1') ? 'any_status' : 'complete_status_only';
            }
            elseif ($key == 'alert_message') {
                $val = strip_tags($val);
            }
            elseif ($key == 'ensure_logic_still_true' || $key == 'do_not_clear_recurrences' || $key == 'prevent_piping_identifiers') {
                $val = ($val == '1') ? 'yes' : 'no';
            }
            elseif ($key == 'email_repetitive') {
                if ((isset($alert[$key]['form_name']) && $alert[$key]['form_name'] == '')
                    || (isset($alert[$key]['cron_send_email_on']) && $alert[$key]['cron_send_email_on'] != 'now')
                    || (isset($alert[$key]['cron_repeat_for']) && $alert[$key]['cron_repeat_for'] != '0'))
                {
                    continue;
                }
                $key = 'trigger_on_every_instrument_save';
                $val = ($val == '1') ? 'yes' : 'no';
            }
            elseif (in_array($key, array('email_to', 'email_cc', 'email_bcc'))) {
                $val = str_replace(';', '; ', $val);
            }
            // Add to logging array
            $alertsLogging[] = "$key = '$val'";
        }

        // ADD NEW ALERT
        if ($newAlert)
        {
            // Get the next order number
            $sql = "SELECT MAX(alert_order) FROM redcap_alerts WHERE project_id = " . PROJECT_ID;
            $q = db_query($sql);
            $max_alert_order = db_result($q, 0);
            $alert['alert_order'] = (is_numeric($max_alert_order) ? ($max_alert_order + 1) : 1);

            // Add to table
            $alertStatus = $alertMsg = '';
            $sql = "insert into redcap_alerts (".implode(', ', array_keys($alert)).") 
                    values (".prep_implode($alert, true, true).")";
            if (db_query($sql)) {
                $alertStatus = 'success';
                // Logging
                $alert_id = db_insert_id();
                unset($this->alerts_settings[PROJECT_ID]); // Reset this so that the new one will be auto-added
                $alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);
                Logging::logEvent($sql, "redcap_alerts", "MANAGE", $alert_number,
                    "Alert #" . $alert_number . " (".self::ALERT_UNIQUE_ID_PREFIX."$alert_id),\n" . implode(",\n", $alertsLogging), "Create alert", "", "", PROJECT_ID);
            } else {
                $alertMsg = 'Error: Alert could not be created! '.db_error();
            }
        }
        // UPDATE ALERT
        else
        {
            // Gather values for SQL update
            $updates = array();
            foreach ($alert as $key => $val) {
                if ($key == 'email_timestamp_sent' || $key == 'email_sent' || $key == 'alert_order') continue; // Do not overwrite these, which are for bookkeeping
                $updates[] = "$key = " . checkNull($val);
            }

            // Add to table
            $alertStatus = $alertMsg = '';
            $sql = "update redcap_alerts set " . implode(', ', $updates) . "
            where project_id = " . PROJECT_ID . " and alert_id = $alert_id";
            if (db_query($sql)) {
                $alertStatus = 'success';
                // Logging
                $alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);
                Logging::logEvent($sql, "redcap_alerts", "MANAGE", $alert_number,
                    "Alert #" . $alert_number . " (".self::ALERT_UNIQUE_ID_PREFIX."$alert_id),\n" . implode(",\n", $alertsLogging), "Modify alert", "", "", PROJECT_ID);
            } else {
                $alertMsg = 'Error: Alert could not be updated! ' . db_error();
            }

            // Already scheduled emails need to be updated
            if ($updateQueue && $alert['cron_send_email_on'] != $alertBefore['cron_send_email_on']) {
                if ($alert['cron_send_email_on'] == 'now' || ($alert['email_repetitive'] == '0' && ($alert['cron_send_email_on'] != 'now' && $alert['cron_send_email_on_date'] != ''))) {
                    // List records that have been queued
                    $queuedRecords = $this->getAlertQueuedRecords($alert_id);
                    if (!empty($queuedRecords)) {
                        // Update all queued records in the table with new send_option
                        $sql = "update redcap_alerts_recurrence set send_option = '" . db_escape($alert['cron_send_email_on']) . "' where alert_id = $alert_id";
                        $q = db_query($sql);
                        // Log the change
                        $changes_made = "Records with modified recurrences: " . prep_implode($queuedRecords);
                        Logging::logEvent($sql, "redcap_alerts", "MANAGE", $alert_number,
                            "Alert #" . $alert_number . " (".self::ALERT_UNIQUE_ID_PREFIX."$alert_id),\n" . $changes_made, "Modify alert recurrences", "", "", PROJECT_ID);
                    }
                }
            }
        }

        // Return message and status
        echo json_encode(array(
            'status' => $alertStatus,
            'message' => $alertMsg
        ));
    }

    // Download an alert's attachment file
    public function downloadAttachment()
    {
        global $lang;
        // If ID is not in query_string, then return error
        if (!is_numeric($_GET['id']) || !is_numeric($_GET['alert_id'])) exit("{$lang['global_01']}!");

        // Verify file
        $sql = "select m.* from redcap_edocs_metadata m, redcap_alerts a
        where m.project_id = ".PROJECT_ID." and m.doc_id = ".checkNull($_GET['id'])." and m.delete_date is null
        and a.alert_id = ".checkNull($_GET['alert_id'])." and (a.email_attachment1 = m.doc_id or a.email_attachment2 = m.doc_id or 
        a.email_attachment3 = m.doc_id or a.email_attachment4 = m.doc_id or a.email_attachment5 = m.doc_id)";
        $q = db_query($sql);
        if (!db_num_rows($q)) exit("<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['file_download_03']}");

        // Get file content
        list ($mimeType, $docName, $fileContent) = Files::getEdocContentsAttributes($_GET['id']);

        // Output file
        header('Content-type: application/octet-stream');
        header('Content-disposition: attachment; filename="'.$docName.'"');
        print $fileContent;
    }

    // Display attachment error message
    private function displayAttachmentError($files, $key, string $message): void{
        // Delete temp file
        unlink($files[$key]['tmp_name']);
        // Give error response
        header('Content-type: application/json');
        echo json_encode(array(
            'status' => $message
        ));
        exit;
    }

    // Upload an alert's attachment file
    public function saveAttachment()
    {
        global $lang;
        $index = isset($_GET['index']) ? (int)$_GET['index'] : null;
        $edoc = null;
        $myfiles = $edoc_ids = array();
        foreach ($_FILES as $key=>$value)
        {
            $myfiles[] = $key;
            if ($value) {
                $current_file_size_mb = round_up($value['size']/1024/1024);
                $message = '';
                if($current_file_size_mb > self::MAX_ATTACHMENT_SIZE_MB){
                    $message =  $lang['alerts_184']." ".$current_file_size_mb." MB {$lang['alerts_185']} ".self::MAX_ATTACHMENT_SIZE_MB." MB {$lang['alerts_186']} (".self::MAX_ATTACHMENT_SIZE_MB." MB){$lang['period']}";
                    self::displayAttachmentError($_FILES, $key, $message);
                }else if($current_file_size_mb > maxUploadSizeAttachment()){
                    $message =  $lang['alerts_184']." ".$current_file_size_mb." MB {$lang['alerts_185']} ".maxUploadSizeAttachment()." MB {$lang['period']}";
                    self::displayAttachmentError($_FILES, $key, $message);
                }else if($_FILES[$key]['error'] != UPLOAD_ERR_OK){
                    $message = $lang['alerts_187'];
                    self::displayAttachmentError($_FILES, $key, $message);
                }
                # use REDCap's uploadFile
                $edoc = \Files::uploadFile($_FILES[$key]);
                if ($edoc) {
                    $edoc_ids[] = $edoc;
                } else {
                    self::displayAttachmentError($_FILES, $key, $lang['alerts_187']);
                }
            }
        }

        header('Content-type: application/json');
        if ($edoc) {
            echo json_encode(array(
                'status' => 'success',
                'doc_ids' => implode(',', $edoc_ids)
            ));
        } else {
            echo json_encode(array(
                'myfiles' => json_encode($myfiles),
                '_POST' => json_encode($_POST),
                'status' => $lang['alerts_188']
            ));
        }
    }

    // Delete an alert's attachment file
    public function deleteAttachment()
    {
        $alertCols = getTableColumns('redcap_alerts');

        $edoc = (int)$_POST['edoc'];
        $key = str_replace("-", "_", $_POST['key']);
        $alert_id = (int)$_POST['index'];

        // Ensure that this edoc belongs to this alert
        $sql = "select 1 from redcap_alerts where alert_id = $alert_id and project_id = ".PROJECT_ID."
                and (email_attachment1 = $edoc or email_attachment2 = $edoc or email_attachment3 = $edoc or email_attachment4 = $edoc or email_attachment5 = $edoc)";
        $q = db_query($sql);
        $edocBelongsToAlert = ($q && db_num_rows($q));

        $statusMsg = 'fail';
        if ($alert_id && isinteger($alert_id) && $edoc && isinteger($edoc) && array_key_exists($key, $alertCols) && $edocBelongsToAlert)
        {
            // Set to null for the alert
            $sql = "update redcap_alerts set $key = null where alert_id = $alert_id and project_id = ".PROJECT_ID;
            if (db_query($sql) && db_affected_rows() > 0) {
                $statusMsg = 'success';
                // Delete the file
                Files::deleteFileByDocId($edoc, PROJECT_ID);
            }
            $type = "Delete $edoc";
        }

        header('Content-type: application/json');
        echo json_encode(array(
            'type' => $type,
            'status' => $statusMsg
        ));
    }

    // Copy an alert
    public function copyAlert()
    {
        $alert_id =  (int)$_REQUEST['index_duplicate'];
        $attachment_cols = array('email_attachment1', 'email_attachment2', 'email_attachment3', 'email_attachment4', 'email_attachment5');

        // Get the next order number
        $sql = "SELECT MAX(alert_order) FROM redcap_alerts WHERE project_id = " . PROJECT_ID;
        $q = db_query($sql);
        $max_alert_order = db_result($q, 0);

        // Copy an alert
        $sql = "select * from redcap_alerts where project_id = ".PROJECT_ID." and alert_id = $alert_id";
        $q = db_query($sql);
        $sql_all = array();
        while ($row = db_fetch_assoc($q)) {
            // Remove some columns that don't need to be copied
            unset($row['alert_id'], $row['email_timestamp_sent'], $row['email_sent']);
            $row['alert_order'] = (is_numeric($max_alert_order) ? ($max_alert_order + 1) : 1);
            // Add to table
            $sql_all[] = $sql = "insert into redcap_alerts (" . implode(", ", array_keys($row)) . ") 
                                 values (" . prep_implode($row, true, true) . ")";
            db_query($sql);
            $this_alert_id = db_insert_id();
            // Copy file(s)
            foreach ($attachment_cols as $col) {
                if (!empty($row[$col])) {
                    $edoc_id = copyFile($row[$col], PROJECT_ID);
                    if (!empty($edoc_id)) {
                        $sql_all[] = $sql = "update redcap_alerts set $col = $edoc_id where alert_id = $this_alert_id";
                        db_query($sql);
                    }
                }
            }
        }
        // Logging
        unset($this->alerts_settings[PROJECT_ID]); // Reset this so that the new one will be auto-added
        $action_description = "Copy alert";
        $alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);
        $new_alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $this_alert_id) + 1);
        Logging::logEvent(implode(";\n", $sql_all), "redcap_alerts", "MANAGE", $alert_number,"Alert #{$new_alert_number} (".self::ALERT_UNIQUE_ID_PREFIX."$this_alert_id) copied from Alert #{$alert_number} (".self::ALERT_UNIQUE_ID_PREFIX."$alert_id)", $action_description);

        echo json_encode(array(
            'status' => 'success',
            'message' => ""
        ));
    }

    // Delete an alert
    public function deleteAlert()
    {
        $alert_id = (int)$_POST['index_modal_delete_user'];
        $delete = isset($_POST['enable']) ? '0' : '1';
        $msg = ($delete ? "D" : "R");

        $sql = "update redcap_alerts set email_deleted = $delete where project_id = ".PROJECT_ID." and alert_id = $alert_id";
        $q = db_query($sql);
        // Logging
        $action_description = ($delete ? "Deactivate alert" : "Reactivate alert");
        $alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);
        Logging::logEvent($sql, "redcap_alerts", "MANAGE", $alert_number,"Alert #{$alert_number} (".self::ALERT_UNIQUE_ID_PREFIX."$alert_id)", $action_description);

        echo json_encode(array(
            'status' => 'success',
            'message' => $msg
        ));
    }

    // Delete an alert (permanently)
    public function deleteAlertPermanent()
    {
        $alert_id = (int)$_REQUEST['index_modal_delete'];
        $alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);

        // First set any edocs as "deleted"
        $sql = "update redcap_alerts a, redcap_edocs_metadata e 
                set e.delete_date = '".NOW."'
                where a.alert_id = $alert_id and a.project_id = ".PROJECT_ID." and a.project_id = e.project_id
                and (a.email_attachment1 = e.doc_id or a.email_attachment2 = e.doc_id or 
                a.email_attachment3 = e.doc_id or a.email_attachment4 = e.doc_id or a.email_attachment5 = e.doc_id)";
        db_query($sql);

        // Delete frm alerts table
        $sql2 = "delete from redcap_alerts where alert_id = $alert_id and project_id = ".PROJECT_ID;
        db_query($sql2);

        $action_description = "Permanently delete alert";
        Logging::logEvent($sql."; ".$sql2, "redcap_alerts", "MANAGE", $alert_number,"Alert #{$alert_number} (".self::ALERT_UNIQUE_ID_PREFIX."$alert_id)", $action_description);

        echo json_encode(array(
            'status' => 'success',
            'message' => ''
        ));
    }

    // Determine if we need to display repeating instrument textbox option when manually queueing an alert for a record
    public function displayRepeatingFormTextboxQueue()
    {
        global $lang;
        $event_id = (int)$_REQUEST['event'];
        $alert_id = (int)$_REQUEST['index_modal_queue'];
        $index = $this->getKeyIdFromAlertId(PROJECT_ID, $alert_id);
        $form_name = $this->getAlertSetting('form-name')[$index];

        $show_instance = "";
        $Proj = new \Project(PROJECT_ID);
        if ($Proj->isRepeatingForm($event_id, $form_name)) {
            $show_instance = '<div style="float:left;width: 280px;"><label style="font-weight: normal;padding-left: 15px;padding-right: 15px">
                                '.$lang['alerts_189'].'<br><span style="color:red">'.$lang['alerts_190'].'</span></label></div>
                                <div style="float:left;"><textarea class="form-control" id="queue_instances" rows="6"></textarea></div>';
        }

        echo json_encode(array(
            'status' => 'success',
            'instance' => $show_instance
        ));
    }

	// Delete a queued record for a given alert
	public function deleteQueuedRecord()
	{
		$aq_id =  (int)$_REQUEST['aq_id'];
		$alert_id =  (int)$_REQUEST['alert_id'];
		// Get record name
		$sql = "select record, event_id from redcap_alerts_recurrence where aq_id = ".checkNull($aq_id)." and alert_id = ".checkNull($alert_id);
		$q = db_query($sql);
		$record = db_result($q, 0, 'record');
		$event_id = db_result($q, 0, 'event_id');
		$Proj = new Project(PROJECT_ID);
		$event_name = '';
		if ($Proj->longitudinal) {
			$event_name = ",\nEvent: '".$Proj->eventInfo[$event_id]['name_ext']."'";
		}
		// Delete the recurrence
		$this->deleteQueuedEmail($aq_id, PROJECT_ID);
		// Logging
		$action_description = "Delete alert recurrence";
		$alert_number = ($this->getKeyIdFromAlertId(PROJECT_ID, $alert_id) + 1);
		Logging::logEvent("", "redcap_alerts", "MANAGE", $alert_number,"Alert #".$alert_number." (".self::ALERT_UNIQUE_ID_PREFIX."$alert_id),\nRecord: '$record'".$event_name, $action_description);

		echo json_encode(array(
			'status' => 'success'
		));
	}

	// Display table of an alert's message contents
	public function previewAlertMessage()
	{
		global $lang;
		$index = (int)$_REQUEST['index_modal_preview'];

		$alert_type = $this->getAlertSetting('alert-type')[$index];
		$phone_number_to = $this->getAlertSetting('phone-number-to')[$index];
		$email_from = $this->getAlertSetting('email-from')[$index];
		$email_to = $this->getAlertSetting('email-to')[$index];
		$email_cc = $this->getAlertSetting('email-cc')[$index];
		$email_bcc = $this->getAlertSetting('email-bcc')[$index];
		$email_subject = $this->getAlertSetting('email-subject')[$index];
		$alert_message = $this->getAlertSetting('alert-message')[$index];
		$sendgrid_from = $this->getAlertSetting('email-from')[$index];
		$sendgrid_template_id = $this->getAlertSetting('sendgrid-template-id')[$index];
		$sendgrid_template_data = $this->getAlertSetting('sendgrid-template-data')[$index];

		$preview = "<table style='margin:0 auto;width:100%'>";
		if ($alert_type == "EMAIL") {
			$preview .= "<tr><td>{$lang['global_37']}</td><td><a href=\"mailto:$email_from\">$email_from</a></td></tr>";
			$preview .= "<tr><td>{$lang['global_38']}</td><td><a href=\"mailto:$email_to\">$email_to</a></td></tr>";
			if ($email_cc != '') {
			$preview .= "<tr><td>{$lang['alerts_191']}</td><td><a href=\"mailto:$email_cc\">$email_cc</a></td></tr>";
			}
			if ($email_bcc != '') {
			$preview .= "<tr><td>{$lang['alerts_192']}</td><td><a href=\"mailto:$email_bcc\">$email_bcc</a></td></tr>";
			}
			$preview .= "<tr><td>{$lang['email_users_10']}</td><td>".strip_tags($email_subject)."</td></tr>";
		} elseif ($alert_type == "SENDGRID_TEMPLATE") {
			$preview .= "<tr><td>{$lang['global_37']}</td><td><a href=\"mailto:$sendgrid_from\">$sendgrid_from</a></td></tr>";
			$preview .= "<tr><td>{$lang['global_38']}</td><td><a href=\"mailto:$email_to\">$email_to</a></td></tr>";
			if ($email_cc != '') {
				$preview .= "<tr><td>{$lang['alerts_191']}</td><td><a href=\"mailto:$email_cc\">$email_cc</a></td></tr>";
			}
			if ($email_bcc != '') {
				$preview .= "<tr><td>{$lang['alerts_192']}</td><td><a href=\"mailto:$email_bcc\">$email_bcc</a></td></tr>";
			}
			$preview .= "<tr><td>{$lang['alerts_334']}</td><td>".strip_tags($sendgrid_template_id)."</td></tr>";
		} else {
			$phone_number_tos = array();
			foreach (explode(";", $phone_number_to) as $this_phone_number)
			{
				$this_phone_number = trim($this_phone_number);
				if ($this_phone_number == '') continue;
				$firstCharacter = substr($this_phone_number, 0, 1);
				if (is_numeric($firstCharacter)) {
					$this_phone_number = formatPhone($this_phone_number);
				}
				$phone_number_tos[] = $this_phone_number;
			}
			$phone_number_tos = implode("; ", $phone_number_tos);
			$preview .= "<tr><td>{$lang['global_38']}</td><td>$phone_number_tos</td></tr>";
			$alert_message = nl2br(Messaging::cleanSmsText($alert_message));
		}
		if ($alert_type != 'SENDGRID_TEMPLATE') {
			$preview .= "<tr><td>{$lang['messaging_105']}</td><td class='underline-all-links'>".filter_tags($alert_message)."</td></tr></table>";
		} else {
			$preview .= "</table><br><table class='table' style='table-layout: fixed;'><tr><th>{$lang['alerts_335']}</th><th>{$lang['alerts_333']}</th><th>{$lang['data_import_tool_99']}</th></tr>";
			$data = json_decode($sendgrid_template_data, TRUE);
			foreach ($data as $key => $value) {
				$preview .= "<tr><td></td><td style='overflow-wrap: break-word;'>$key</td><td style='overflow-wrap: break-word;'>$value</td></tr>";
			}
			$preview .= "</table>";
		}
        echo $preview;
    }

    // Display dialog of an alert's message contents for a specific record
    public function previewAlertMessageByRecordDialog()
    {
        global $lang, $user_rights;
        $index =  (int)$_REQUEST['index_modal_alert'];
        $Proj = new \Project(PROJECT_ID);

        $form_name = $this->getAlertSetting('form-name')[$index];
        $alert_id =  $this->getAlertSetting('alert_id')[$index];
        $event_id = $this->getAlertSetting('form-name-event')[$index];
        $repeatable = (($event_id != "" && $Proj->isRepeatingForm($event_id, $form_name)) || ($event_id == "" && $Proj->isRepeatingFormAnyEvent($form_name)));

        $event_selector = "";
        $numRecords = Records::getRecordCount(PROJECT_ID);
        if ($Proj->longitudinal || $repeatable || $numRecords > 1000) {
            $event_selector = "<div style='padding-bottom: 60px;'><input type='text' name='preview_record_id' id='preview_record_id' placeholder='".js_escape($lang['alerts_205'])."' style='width: 80%;float: left;' onkeydown='if(event.keyCode==13) return false;'>
                        <a href='#' class='btn btn-default save' onclick=\"loadPreviewEmailAlertRecord('','','','$alert_id')\" id='preview_record_id_btn' style='float: left;margin-left: 20px;padding-top: 8px;padding-bottom: 7px;'>{$lang['design_699']}</a></div>";
        } else {
            $record_list = Records::getRecordList(PROJECT_ID, $user_rights['group_id'], true);
            if (!empty($record_list)) {
                // Get any Custom Record Labels or Secondary Unique Field labels
                $extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($record_list);
                // Build drop-down list
                $event_selector = '<div style="padding-bottom:10px">'.
                    '<select class="external-modules-input-element" name="preview_record_id" onchange="loadPreviewEmailAlertRecord(\'\',\'\',\'\',\''.$alert_id.'\')"><option value="">'.$lang['alerts_193'].'</option>';
                if (empty($extra_record_labels)) {
                    foreach ($record_list as $this_record) {
                        $event_selector .= "<option value='$this_record'>$this_record</option>";
                        unset($record_list[$this_record]);
                    }
                } else {
                    foreach ($record_list as $this_record) {
                        $event_selector .= "<option value='$this_record'>$this_record ".($extra_record_labels[$this_record] ?? "")."</option>";
                        unset($record_list[$this_record]);
                    }
                }
                $event_selector .= '</select></div>';
            }
        }
        echo $event_selector;
    }

	// Display table inside dialog of an alert's message contents for a specific record
	public function previewAlertMessageByRecord($alert_sent_log_id=null, $aq_id=null)
	{
		global $lang;
		if (is_numeric($alert_sent_log_id)) {
			$sql = "select a.alert_id, s.record, s.event_id, s.instrument, s.instance, l.*,
					a.sendgrid_template_id, a.sendgrid_template_data
					from redcap_alerts a, redcap_alerts_sent s, redcap_alerts_sent_log l 
					where a.project_id = ".PROJECT_ID." and a.alert_id = s.alert_id and s.alert_sent_id = l.alert_sent_id
					and l.alert_sent_log_id = ".checkNull($alert_sent_log_id);
			$q = db_query($sql);
			$row = db_fetch_assoc($q);
			$alert_id = $row['alert_id'];
			$index = $this->getKeyIdFromAlertId(PROJECT_ID, $alert_id);
			$record = $row['record'];
			$form_name_event = $row['event_id'];
			$form_name = $row['instrument'];
			$instance = $row['instance'];
			$email_from = $row['email_from'];
			$email_subject = $row['subject'];
			$alert_message = $row['message'];
			$alert_type = $row['alert_type'];
			$phone_number_to = $row['phone_number_to'];
			$sendgrid_template_id = $row['sendgrid_template_id'];
			$sendgrid_template_data = $row['sendgrid_template_data'];
			$sendgrid_from = $row['email_from'];
		} elseif (is_numeric($aq_id)) {
			$sql = "select a.alert_id, a.alert_type, a.email_from, a.phone_number_to, r.record, r.event_id, r.instrument, r.instance,
					a.sendgrid_template_id, a.sendgrid_template_data
					from redcap_alerts a, redcap_alerts_recurrence r
					where a.project_id = " . PROJECT_ID . " and a.alert_id = r.alert_id and r.aq_id = ".checkNull($aq_id);
			$q = db_query($sql);
			$row = db_fetch_assoc($q);
			$alert_id = $row['alert_id'];
			$index = $this->getKeyIdFromAlertId(PROJECT_ID, $alert_id);
			$record = $row['record'];
			$form_name_event = $row['event_id'];
			$form_name = $row['instrument'];
			$instance = $row['instance'];
			$email_from = isset($row['email_from']) ? $row['email_from'] : "";
			$email_subject = $this->getAlertSetting('email-subject')[$index];
			$alert_message = $this->getAlertSetting('alert-message')[$index];
			$alert_type = $row['alert_type'];
			$phone_number_to = $row['phone_number_to'];
			$sendgrid_template_id = $row['sendgrid_template_id'];
			$sendgrid_template_data = $row['sendgrid_template_data'];
			$sendgrid_from = $row['email_from'];
		} else {
			$index = (int)$_POST['index_modal_record_preview'];
			$alert_id = $this->getAlertIdFromKeyId(PROJECT_ID, $index);
			$record = $_REQUEST['preview_record_id'];
			$form_name_event = $this->getAlertSetting('form-name-event')[$index];
			$form_name = $this->getAlertSetting('form-name')[$index];
			$email_from = $this->getAlertSetting('email-from')[$index];
			$email_subject = $this->getAlertSetting('email-subject')[$index];
			$alert_message = $this->getAlertSetting('alert-message')[$index];
			$alert_type = $this->getAlertSetting('alert-type')[$index];
			$phone_number_to = $this->getAlertSetting('phone-number-to')[$index];
			$sendgrid_template_id = $this->getAlertSetting('sendgrid-template-id')[$index];
			$sendgrid_template_data = $this->getAlertSetting('sendgrid-template-data')[$index];
			$sendgrid_from = $this->getAlertSetting('email-from')[$index];
			$instance = 1;
		}

		$prevent_piping_identifiers = $this->getAlertSetting("prevent-piping-identifiers")[$index];

		// If we're previewing the alert by record from the alerts page, construct the alert_message
		// from the alert's template data so it can be rendered later.
		if ($alert_type == 'SENDGRID_TEMPLATE' && $alert_message == null) {
			$template_data = json_decode($sendgrid_template_data ?? "", TRUE) ?? [];
			foreach ($template_data as $key => $value) {
				$template_data[$key] = Piping::replaceVariablesInLabel($value, $record, $form_name_event, $instance, $data, false, $project_id, false, $form_name, 1, false, false, $form_name, null, false, $prevent_piping_identifiers, true);
			}
			$alert_message = json_encode($template_data);
		}

		$phone_number_tos = "";
		if ($alert_type == "SMS" || $alert_type == "VOICE_CALL") {
			$phone_number_tos = array();
			foreach (explode(";", $phone_number_to) as $this_phone_number) {
				$this_phone_number = trim($this_phone_number);
				if ($this_phone_number == '') continue;
				$firstCharacter = substr($this_phone_number, 0, 1);
				if (is_numeric($firstCharacter)) {
					$this_phone_number = formatPhone($this_phone_number);
				}
				$phone_number_tos[] = $this_phone_number;
			}
			$phone_number_tos = implode("; ", $phone_number_tos);
		}

		// Get record data for piping
		$Proj = new Project(PROJECT_ID);
		$project_id = $Proj->project_id;
		$data = \REDCap::getData($project_id, 'array', $record);
		if (empty($data)) exit("<b>Record \"".htmlspecialchars($record, ENT_QUOTES)."\" does not exist.</b>");

		$alert_message = Piping::replaceVariablesInLabel($alert_message, $record, $form_name_event, $instance, $data,false,
							$project_id, false, $form_name, 1, false, false, $form_name, null, false, $prevent_piping_identifiers, true);
		$email_subject = Piping::replaceVariablesInLabel($email_subject, $record, $form_name_event, $instance, $data,false,
							$project_id, false, $form_name, 1, false, false, $form_name, null, false, $prevent_piping_identifiers);
		$phone_number_tos = Piping::replaceVariablesInLabel($phone_number_tos, $record, $form_name_event, $instance, $data,false,
							$project_id, false, $form_name, 1, false, false, $form_name, null, false, $prevent_piping_identifiers);
		if (!isEmail($email_from)) {
			$email_from = Piping::replaceVariablesInLabel($email_from, $record, $form_name_event, $instance, $data,false,
							$project_id, false, $form_name, 1, false, false, $form_name);
		}

		// Email Addresses
		if (is_numeric($alert_sent_log_id)) {
			$email_to = $row['email_to'];
			$email_cc = $row['email_cc'];
			$email_bcc = $row['email_bcc'];
		} else {
			$mail = new Message($project_id, $record, $form_name_event, $form_name, $instance);
			$mail = $this->setEmailAddresses($mail, $project_id, $record, $form_name_event, $form_name, $instance, $index, $data);
			$email_to = $mail->getTo();
			$email_cc = $mail->getCc();
			$email_bcc = $mail->getBcc();
		}

		// Display table
		$preview = "<table style='margin:0 auto;width:100%'>";
		if ($alert_type == "EMAIL") {
			$preview .= "<tr><td>{$lang['global_37']}</td><td><a href=\"mailto:$email_from\">$email_from</a></td></tr>";
			$preview .= "<tr><td>{$lang['global_38']}</td><td><a href=\"mailto:$email_to\">".str_replace(";", "; ", $email_to)."</a></td></tr>";
			if ($email_cc != '') {
			$preview .= "<tr><td>{$lang['alerts_191']}</td><td><a href=\"mailto:$email_cc\">".str_replace(";", "; ", $email_cc)."</a></td></tr>";
			}
			if ($email_bcc != '') {
			$preview .= "<tr><td>{$lang['alerts_192']}</td><td><a href=\"mailto:$email_bcc\">".str_replace(";", "; ", $email_bcc)."</a></td></tr>";
			}
		$preview .= "<tr><td>{$lang['email_users_10']}</td><td>".strip_tags($email_subject)."</td></tr>";
		} elseif ($alert_type == 'SENDGRID_TEMPLATE') {
			$preview .= "<tr><td>{$lang['global_37']}</td><td><a href=\"mailto:$sendgrid_from\">$sendgrid_from</a></td></tr>";
			$preview .= "<tr><td>{$lang['global_38']}</td><td><a href=\"mailto:$email_to\">".str_replace(";", "; ", $email_to)."</a></td></tr>";
			if ($email_cc != '') {
				$preview .= "<tr><td>{$lang['alerts_191']}</td><td><a href=\"mailto:$email_cc\">".str_replace(";", "; ", $email_cc)."</a></td></tr>";
			}
			if ($email_bcc != '') {
				$preview .= "<tr><td>{$lang['alerts_192']}</td><td><a href=\"mailto:$email_bcc\">".str_replace(";", "; ", $email_bcc)."</a></td></tr>";
			}
			$preview .= "<tr><td>{$lang['alerts_334']}</td><td>".strip_tags($sendgrid_template_id)."</td></tr>";
	 	} else {
			$preview .= "<tr><td>{$lang['global_38']}</td><td>$phone_number_tos</td></tr>";
			// Clean string
			$alert_message = nl2br(Messaging::cleanSmsText($alert_message));
		}

		if ($alert_type != 'SENDGRID_TEMPLATE') {
			$preview .= "<tr><td>{$lang['messaging_105']}</td><td class='underline-all-links'>".filter_tags($alert_message)."</td></tr></table>";
		} else {
			$preview .= "</table><br><table class='table' style='table-layout: fixed;'><tr><th>{$lang['alerts_335']}</th><th>{$lang['alerts_333']}</th><th>{$lang['data_import_tool_99']}</th></tr>";
			$template_data = json_decode($alert_message, TRUE) ?? [];
			foreach ($template_data as $key => $value) {
				$preview .= "<tr><td></td><td style='overflow-wrap: break-word;'>$key</td><td style='overflow-wrap: break-word;'>$value</td></tr>";
			}
			$preview .= "</table>";
		}

		echo $preview;
	}

    // Obtain the notification log as an array - (past, present, and future) with filters and paging
    public function getNotificationLog($record=null, $returnCountOnly=false)
    {
        // Initialize vars
        global $Proj, $table_pk, $user_rights;

        // Determine which active alert as the longest cron interval to determine the end time
        $alert_settings = $this->getAlertSettings(PROJECT_ID);
        $maxAlertCronInterval = 0;
        foreach ($alert_settings as $row) {
            if ($row['email_deleted']) continue;
            $intervalMinutes = $row['cron_repeat_for']*($row['cron_repeat_for_units'] == 'DAYS' ? 1440 : ($row['cron_repeat_for_units'] == 'HOURS' ? 60 : 1));
            if ($intervalMinutes > $maxAlertCronInterval) $maxAlertCronInterval = $intervalMinutes;
        }
        // Show end time as 5x the longest cron interval
        $maxAlertCronInterval = ceil(5*$maxAlertCronInterval);

        ## DEFINE FILTERING VALUES
        // Set defaults
        if (isset($_GET['pagenum']) && (is_numeric($_GET['pagenum']) || $_GET['pagenum'] == 'last')) {
            // do nothing
        } elseif (!isset($_GET['pagenum'])) {
            $_GET['pagenum'] = 1;
        } else {
            $_GET['pagenum'] = 'ALL';
        }
        $filterRecord = isset($_GET['filterRecord']) ? urldecode(rawurldecode($_GET['filterRecord'])) : '';
        $filterAlert = (isset($_GET['filterAlert']) && is_numeric($_GET['filterAlert'])) ? (int)$_GET['filterAlert'] : '';
        // Time filters
        if (!isset($_GET['filterBeginTime'])) {
            // Default beginTime = right now
            $_GET['filterBeginTime'] = substr(NOW, 0, 16);
        }
        if ($maxAlertCronInterval > 0 && (!isset($_GET['filterEndTime']) || (isset($_GET['filterEndTime']) && $_GET['filterEndTime'] == ''))) {
            // Default endTime
            $_GET['filterEndTime'] = DateTimeRC::format_ts_from_ymd(date('Y-m-d H:i:s', strtotime(NOW . " + $maxAlertCronInterval minutes")), true, false);
        }
        if ($_GET['filterBeginTime'] != '') {
            $filterBeginTimeYmd = DateTimeRC::format_ts_to_ymd($_GET['filterBeginTime']);
        }
        if (isset($_GET['filterEndTime']) && $_GET['filterEndTime'] != '') {
            $filterEndTimeYmd = DateTimeRC::format_ts_to_ymd($_GET['filterEndTime']);
        }
        // Make sure begin time occurs *before* end time. If not, swap them
        if (isset($filterBeginTimeYmd) && isset($filterEndTimeYmd) && $filterBeginTimeYmd > $filterEndTimeYmd) {
            $_GET['filterBeginTime'] = $filterEndTimeYmd;
            $_GET['filterEndTime'] = $filterBeginTimeYmd;
            $filterBeginTimeYmd = $_GET['filterBeginTime'];
            $filterEndTimeYmd = $_GET['filterEndTime'];
        }

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

        // Get all notifications that have already been sent
        $notificationLog = array();
        $sql = "SELECT l.alert_sent_log_id, a.alert_id, s.record, s.event_id, s.instrument, 
                    IFNULL(s.instance, 1) AS instance, l.time_sent AS send_time, l.email_to, l.subject, 
                    l.alert_type, l.phone_number_to
                FROM redcap_alerts a, redcap_alerts_sent s, redcap_alerts_sent_log l 
                WHERE a.project_id = ? AND a.alert_id = s.alert_id AND s.alert_sent_id = l.alert_sent_id";
        $params = [PROJECT_ID];
        $additonal_where = " ";
        if ($record != "") {
            $additonal_where .= " AND s.record = ?";
            $params[] = $record;
        }
        elseif ($filterRecord != "") {
            $additonal_where .= " AND s.record = ?";
            $params[] = $filterRecord;
        }
        if ($filterAlert != "") {
            $additonal_where .= " AND a.alert_id = ?";
            $params[] = $filterAlert;
        }
        if (isset($filterBeginTimeYmd) && $filterBeginTimeYmd != "") {
            $additonal_where .= " AND l.time_sent >= ?";
            $params[] = $filterBeginTimeYmd.":00";
        }
        if (isset($filterEndTimeYmd) && $filterEndTimeYmd != "") {
            $additonal_where .= " AND l.time_sent <= ?";
            $params[] = $filterEndTimeYmd.":59";
        }
        $sql .= "$additonal_where ORDER BY l.time_sent";
        $q = db_query($sql, $params);
        // Loop through all rows and store values in array
        while ($row = db_fetch_assoc($q)) {
            $row['aq_id'] = '';
            $row['was_sent'] = '1';
            $row["icon"] = "sent";
            $notificationLog[] = $row;
        }
        // Perform DAG filtering
        if ($user_rights["group_id"] != '') {
            foreach ($notificationLog as $key => $attr) {
                if ($attr['record'] != '' && !isset($dag_records[$attr['record']])) {
                    unset($notificationLog[$key]);
                }
            }
        }

        // Now add all projected future notifications to the notification log
        // (SKIP THIS SECTION if we're looking at past timestamps only - this is only for future projections)
        $recurrences = array();
        if (!isset($filterEndTimeYmd) || $filterEndTimeYmd == '' || $filterEndTimeYmd > substr(NOW, 0, 16))
        {
            $sql = "SELECT r.aq_id, a.alert_id, r.record, r.event_id, r.instrument, 
                        IFNULL(r.instance, 1) AS instance, a.email_to, a.email_subject AS subject, 
                        r.first_send_time, r.times_sent, a.cron_repeat_for_max, a.cron_repeat_for, a.cron_repeat_for_units, a.alert_expiration, a.alert_type, a.phone_number_to, r.send_option, a.do_not_clear_recurrences, a.alert_condition
                    FROM redcap_alerts a, redcap_alerts_recurrence r
                    WHERE a.project_id = ? AND a.alert_id = r.alert_id AND a.email_deleted = 0 
                        AND a.cron_send_email_on = r.send_option
                        AND (a.cron_repeat_for > 0 || (a.cron_repeat_for = 0 AND r.times_sent = 0))";
            $params = [PROJECT_ID];
            $additonal_where = " ";
            if ($record !== null) {
                $additonal_where .= " AND r.record = ?";
                $params[] = $record;
            }
            else if ($filterRecord !== "") {
                $additonal_where .= " AND r.record = ?";
                $params[] = $filterRecord;
            }
            if ($filterAlert !== "") {
                $additonal_where .= " AND a.alert_id = ?";
                $params[] = $filterAlert;
            }
            $sql .= "$additonal_where ORDER BY r.first_send_time";
            $q = db_query($sql, $params);
            $recordsToBeCached = [];
            while ($row = db_fetch_assoc($q)) {
                // Filter by DAG (if current user is assigned to a DAG)
                if ($user_rights['group_id'] != '' && $row['record'] != '' && !isset($dag_records[$row['record']])) {
                    continue;
                }
                // Add to array
                $row['alert_sent_log_id'] = '';
                $recurrences[] = $row;
                // Could this be a paused alert? If so, add to the list of records to be cached
                if ($row["do_not_clear_recurrences"] == "1") {
                    $recordsToBeCached[$row["record"]][] = $row["alert_condition"];
                }
            }

            // Cache record data for paused alert evaluation
            $record_data = null;
            if (!empty($recordsToBeCached)) {
                $logics_all = ["[{$Proj->table_pk}]"];// Ensure record id is included (to prevent loading full records in case the logic does not contain any fields)
                foreach ($recordsToBeCached as $record => $logics) {
                    $logics_all[] = trim(implode(" ", $logics));
                }
                $logics_all = implode(" ", $logics_all);
                $logics_fields = getBracketedFields($logics_all, true, true, true);
                $logics_fields = array_intersect(array_keys($logics_fields), array_keys($Proj->metadata));
                if (!empty($logics_fields)) {
                    $getDataParams = [
                        'project_id' => PROJECT_ID,
                        'records' => array_keys($recordsToBeCached),
                        'fields' => $logics_fields,
                        'returnEmptyEvents' => true,
                        'decimalCharacter'=>'.',
	                    'returnBlankForGrayFormStatus' => true
                    ];
                    $record_data = Records::getData($getDataParams);
                }
            }

            // Loop through all rows and store values in array
            $maxLoops = 100; // How many instances of EACH recurrence should we show (max)?
            foreach ($recurrences as $key => $row) {
                $intervalMinutes = $row['cron_repeat_for_units'] == 'DAYS'
                    ? 1440 
                    : ($row['cron_repeat_for_units'] == 'HOURS' ? 60 : 1);
                $i = 0;
                $recurrenceOffset = 0;
                for ($recurrenceNum = 0; $recurrenceNum < $maxLoops; $recurrenceNum++) {
                    $totalMinutes = round(
                        ($row['times_sent'] + $recurrenceNum + $recurrenceOffset) *
                        $row['cron_repeat_for'] * $intervalMinutes
                    );
                    $row['send_time'] = date('Y-m-d H:i:s', strtotime($row['first_send_time'] . " + $totalMinutes minutes"));
                    // If this is a one-time scheduled alert (not a recurrence), then stop here
                    if ($row['cron_repeat_for'] == '0' && $recurrenceNum >= 1) break;
                    // If this projected time is in the past (how?) or if user set an end time filter, then skip to next
                    if ($row['send_time'] < NOW) {
                        // This may be a "forever" alert. To get any projections into the desired time
                        // window, we calculate an offset.
                        $recurrenceOffset = rounddown(datediff('now', $row['send_time'], 'm') / max(1, $row['cron_repeat_for'] * $intervalMinutes), 0);
                        continue;
                    }
                    if (isset($filterEndTimeYmd) && substr($row['send_time'], 0, 16) > $filterEndTimeYmd) {
                        break;
                    }
                    // If a recurrence maximum is set, then if we've already hit the max, don't show any more of this recurrence.
                    if ($row['cron_repeat_for_max'] != '' && ($row['times_sent'] + $i + ($row['send_option'] != 'now' ? 0 : 1)) >= $row['cron_repeat_for_max']) {
                       break;
                    }
                    // If alert will expiration at a certain time, then don't project any future notifications past that time
                    if ($row['alert_expiration'] != '' && $row['send_time'] > $row['alert_expiration']) break;
                    // Create copy for modification
                    $row2 = $row;
                    // Add extras
                    $row2['was_sent'] = '0';
                    $row2['icon'] = "scheduled";
                    // Paused alert?
                    if ($row2["do_not_clear_recurrences"] == 1) {
                        // Evaluate alert condition
                        $passedLogicTest = REDCap::evaluateLogic($row2['alert_condition'], PROJECT_ID, $row2['record'], $row2['event_id'], $row2['instance'], "", "", $record_data);
                        $is_paused = !$passedLogicTest;
                        // Set icon to paused when the alert condition evaluates as false
                        if ($is_paused) $row2['icon'] = "paused";
                    }
                    // Remove unneeded
                    unset($row2['first_send_time'], $row2['times_sent'], $row2['cron_repeat_for'], $row2['cron_repeat_for_units'], $row2["do_not_clear_recurrences"], $row2["alert_condition"]);
                    // Add to array
                    $notificationLog[] = $row2;
                    $i++;
                }
            }
        }

        // Loop through all notifications to get all the record names
        $displayed_records = $send_times = array();
        foreach ($notificationLog as $key=>$attr)
        {
            $send_times[$key] = $attr['send_time'];
            $displayed_records[$attr['record']] = $attr['record'];
        }
        natcasesort($displayed_records);
        array_multisort($send_times, SORT_REGULAR, $notificationLog);

        // Return log as array
        if ($returnCountOnly) {
            return count($notificationLog);
        } else {
            return array($notificationLog, $displayed_records);
        }
    }


    // Display a table listing all survey invitations (past, present, and future) with filters and paging
    public function renderNotificationLog($record=null, $showFullTableDisplay=true)
    {
        // Initialize vars
        global $Proj, $longitudinal, $lang;

        // Get the invitation log
        list ($notificationLog, $displayed_records) = $this->getNotificationLog($record);
        $alerts_settings = $this->getAlertSettings(PROJECT_ID);
        $all_active_alerts = array();
        foreach ($alerts_settings as $attr) {
            if ($attr['email_deleted'] == '1') continue;
            $all_active_alerts[$attr['alert_id']] = $lang['alerts_24']." #".$attr['alert_number'];
            if ($attr['alert_title'] != '') {
                $all_active_alerts[$attr['alert_id']] .= $lang['colon'] . " " . $attr['alert_title'];
            }
            $all_active_alerts[$attr['alert_id']] .= " (".self::ALERT_UNIQUE_ID_PREFIX.$attr['alert_id'].")";
        }

        // Set NOW in user defined date format but with military time
        $now_user_date_military_time = DateTimeRC::format_ts_from_ymd(TODAY).date(' H:i');

        ## BUILD THE DROP-DOWN FOR PAGING THE INVITATIONS
        // Get participant count
        $notificationCount = count($notificationLog);
        // Section the Participant List into multiple pages
        $num_per_page = self::notification_log_num_per_page;
        // Calculate number of pages of for dropdown
        $num_pages = ceil($notificationCount/$num_per_page);
        $pageDropdown = "";
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
        $pageDropdown = "<select id='pageNumInviteLog' onchange='loadNotificationLog(this.value)' style='vertical-align:middle;font-size:11px;'>";
        //Loop to create options for dropdown
        for ($i = 1; $i <= $num_pages; $i++) {
            $end_num   = $i * $num_per_page;
            $begin_num = $end_num - $num_per_page + 1;
            if ($end_num > $notificationCount) $end_num = $notificationCount;
            $pageDropdown .= "<option value='$i' " . ($_GET['pagenum'] == $i ? "selected" : "") . ">$begin_num - $end_num</option>";
        }
        $pageDropdown .= "</select>";
        $pageDropdown  = "{$lang['survey_45']} $pageDropdown {$lang['survey_133']} $notificationCount";

        // If viewing ALL invitations, then set $num_per_page to null to return all invitations
        if ($_GET['pagenum'] == 'ALL' || !$showFullTableDisplay) $num_per_page = null;

        // Loop through all invitations for THIS PAGE and build table
        $rownum = 0;
		$status_icons = [
			"scheduled" => RCIcon::NotificationLogScheduled("fa-xs fs-10 me-1"),
			"paused" => RCIcon::NotificationLogPaused("fa-xs fs-10 me-1"),
			"sent" => RCIcon::NotificationLogSent("fa-xs fs-10 me-1"),
			"failed" => RCIcon::NotificationLogFailed("fa-xs fs-10 me-1"),
		];
        foreach (array_slice($notificationLog, $limit_begin, $num_per_page) as $row) {
            // Set color of timestamp (green if already sent, red if failed) and icon
            $tsColor = ($row['was_sent'] == '0') ? "gray" : ($row['was_sent'] == '1' ? "green" : "red");
            $tsIconAttr = $row["icon"] == "paused" ? [ "title" => RCView::tt_js("alerts_415")] : [];
            $alert_number = $alerts_settings[$row['alert_id']]['alert_number'];

            // If scheduled and not sent yet, display cross icon to delete the invitation
            $deleteEditInviteIcons = '';
            if ($showFullTableDisplay && $row['was_sent'] == '0') {
                $deleteEditInviteIcons =
                    RCView::a(array('href'=>'javascript:;','style'=>'margin:0 2px 0 5px;','onclick'=>"deleteRecurrence({$row['aq_id']},{$row['alert_id']},'{$alert_number}','".js_escape($row['record'])."','".js_escape($Proj->eventInfo[$row['event_id']]['name_ext'])."')"),
                        RCView::span([
                            "title" => $lang['alerts_29'],
                        ], RCIcon::NotificationLogDelete("fa-xs inviteLogDelIcon opacity50", "color: red;"))
                    );
            }

            // Send time (and icon)
            $rows[$rownum][] = 	// Invisible YMD timestamp (for sorting purposes
                RCView::span(array('class'=>'hidden'), $row['send_time']) .
                // Display time and icon
                RCView::span(array('style'=>"color:$tsColor;"),
                    RCView::span($tsIconAttr, $status_icons[$row['icon']]) .
                    DateTimeRC::format_ts_from_ymd($row['send_time']) .
                    $deleteEditInviteIcons
                );

            $rows[$rownum][] = '#'.$alert_number." ".$lang['leftparen'].self::ALERT_UNIQUE_ID_PREFIX.$row['alert_id'].$lang['rightparen'];

            $onclick = "loadPreviewEmailAlertRecord('{$row['alert_sent_log_id']}','{$row['aq_id']}','{$alert_number}','{$row['alert_id']}');";
            $rows[$rownum][] = 	RCView::a(array('href'=>'javascript:;', 'onclick'=>$onclick."return false;"),
                                    RCView::img(array('src'=>'mail_open_document.png', 'title'=>$lang['alerts_28']))
                                );

			// Record ID (if not anonymous response)
			if ($row['instrument'] != '' && $row['event_id'] != '') {
				$recordLink = "DataEntry/index.php?pid=".PROJECT_ID."&page={$row['instrument']}&event_id={$row['event_id']}&id={$row['record']}&instance={$row['instance']}";
			} else {
				$recordLink = "DataEntry/record_home.php?pid=".PROJECT_ID."&id={$row['record']}";
				if ($Proj->multiple_arms) {
					if ($row['event_id'] != '') {
						$recordLink .= "&arm=" . $Proj->eventInfo[$row['event_id']]['arm_num'];
					}
				}
			}
			$rows[$rownum][] = 	RCView::div(array('class'=>'wrap', 'style'=>'word-wrap:break-word;'),
				($row['record'] == '' ? "" : ($row['record'] == '' ? '<i class="far fa-eye-slash" style="color:#ddd;"></i>' :
					RCView::a(array('href'=>APP_PATH_WEBROOT.$recordLink, 'style'=>'font-size:12px;text-decoration:underline;'), $row['record']) .
					($Proj->isRepeatingFormOrEvent($row['event_id'], $row['instrument']) ? "&nbsp;&nbsp;<span style='color:#777;'>(#{$row['instance']})</span>" : "") .
					(!$longitudinal ? "" : "&nbsp;&nbsp;<span style='color:#777;'>-&nbsp;".$Proj->eventInfo[$row['event_id']]['name_ext']."</span>")
				))
			);

			if ($row['alert_type'] == "EMAIL") {
				$rows[$rownum][] = "<i class='fas fa-envelope me-1 opacity35'></i>".$row['email_to'];
				$rows[$rownum][] = strip_tags($row['subject']);
			} elseif ($row['alert_type'] == 'SENDGRID_TEMPLATE') {
				$rows[$rownum][] = "<i class='fas fa-envelope me-1 opacity35'></i>".$row['email_to'];
				$rows[$rownum][] = strip_tags("");
			} else {
				// Format all the numbers
				$phone_number_tos = array();
				if ($row['instrument'] != '' && $row['event_id'] != '') {
                    $recordLink = "DataEntry/index.php?pid=".PROJECT_ID."&page={$row['instrument']}&event_id={$row['event_id']}&id={$row['record']}&instance={$row['instance']}";
                } else {
                    $recordLink = "DataEntry/record_home.php?pid=".PROJECT_ID."&id={$row['record']}";
                    if ($Proj->multiple_arms) {
                        if ($row['event_id'] != '') {
                            $recordLink .= "&arm=" . $Proj->eventInfo[$row['event_id']]['arm_num'];
                        }
                    }
                }
				$missing_text = '<span style="font-size:12px; font-style: italic;">[<a style="color:#800000;" href="'.APP_PATH_WEBROOT.$recordLink.'">'.$lang['dataqueries_53'].'</a>]</span>';
				foreach (explode(";", $row['phone_number_to']??"") as $this_phone_number)
				{
					$this_phone_number = trim($this_phone_number);
					if ($this_phone_number == '') continue;
					$firstCharacter = substr($this_phone_number, 0, 1);
					if (is_numeric($firstCharacter)) {
						$this_phone_number = formatPhone($this_phone_number);
					}
					$phone_number_tos[] = $this_phone_number;
				}
				$phone_number_tos = implode("; ", $phone_number_tos);
				$phone_number_tos = Piping::replaceVariablesInLabel($phone_number_tos, $row['record'], $row['event_id'], $row['instance'], [], true, PROJECT_ID, false, "", 1, false, false, $row['instrument']);

				$phone_number_tos = str_replace(Piping::missing_data_replacement, $missing_text, $phone_number_tos);

				$rows[$rownum][] = ($row['alert_type'] == "SMS" ? "<i class='fas fa-sms me-1 opacity35 fs15'></i>" : "<i class='fas fa-phone me-1 opacity35'></i>") . $phone_number_tos;
				$rows[$rownum][] = "";
			}

			// Increment counter
			$rownum++;
		}

        // Give message if no invitations were sent
        if (empty($rows)) {
            $rows[$rownum] = array(RCView::div(array('class'=>'wrap','style'=>'color:#800000;'), $lang['alerts_25']),"","","");
        }

        // Define table headers
        $headers = array();
        if ($showFullTableDisplay) {
            $headers[] = array(160, RCView::img(array('class'=>'survlogsendarrow', 'src'=>'draw-arrow-down.png', 'style'=>'vertical-align:middle;')) .
                RCView::img(array('class'=>'survlogsendarrow', 'src'=>'draw-arrow-up.png', 'style'=>'display:none;vertical-align:middle;')) .
                RCView::SP .
                $lang['alerts_21']);
            $headers[] = array(64,  RCView::span(array('class'=>'wrap'), $lang['alerts_24']), "center");
            $headers[] = array(64,  RCView::span(array('class'=>'wrap'), $lang['alerts_22']), "center", "string", false);
            $headers[] = array(120, RCView::div(array('class'=>'wrap'), $lang['global_49']), "center");
            $headers[] = array(200, RCView::span(array('class'=>'wrap'), $lang['alerts_26']));
            $headers[] = array(260, RCView::span(array('class'=>'wrap'), $lang['alerts_23']));
        } else {
            // Limited display
            $headers[] = array(140, $lang['survey_436']);
            $headers[] = array(370, $lang['survey_437']);
        }
        // Set some flags to disable buttons
        $disableViewPastInvites = $disableViewFutureInvites = "";
        // Set flags (if timestamp is within the same hour as now, then consider it now)
        if ($_GET['filterBeginTime'] == '' && substr($_GET['filterEndTime'], 0, -2) == substr($now_user_date_military_time, 0, -2)) {
            $disableViewPastInvites = "disabled";
        }
        if ((!isset($_GET['filterEndTime']) || $_GET['filterEndTime'] == '') && substr($_GET['filterBeginTime'], 0, -2) == substr($now_user_date_military_time, 0, -2)) {
            $disableViewFutureInvites = "disabled";
        }
        // Define title
        $title = "";
        if ($showFullTableDisplay) {
            $title =	RCView::div(array('style'=>''),
                RCView::div(array('style'=>'padding:2px 20px 0 5px;float:left;font-size:14px;'),
                    $lang['alerts_20'] . RCView::br() .
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
                        'onclick'=>"$('#filterBeginTime').val('');$('#filterEndTime').val('$now_user_date_military_time');loadNotificationLog('last')"), $lang['alerts_18']) .
                    RCView::button(array($disableViewFutureInvites=>$disableViewFutureInvites, 'class'=>'jqbuttonsm', 'style'=>'margin-top:12px;font-size:11px;color:#000066;display:block;',
                        'onclick'=>"$('#filterBeginTime').val('$now_user_date_military_time');$('#filterEndTime').val('');loadNotificationLog(1)"), $lang['alerts_19'])
                ) .
                ## FILTERS
                RCView::div(array('style'=>'max-width:500px;font-weight:normal;float:left;font-size:11px;padding-left:15px;margin-left:15px;border-left:1px solid #ccc;'),
                    // Date/time range
                    $lang['survey_439'] .
                    RCView::text(array('id'=>'filterBeginTime','value'=>$_GET['filterBeginTime'],'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-right:8px;margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                    $lang['survey_440'] .
                    RCView::text(array('id'=>'filterEndTime','value'=>(isset($_GET['filterEndTime']) ? $_GET['filterEndTime'] : ""),'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                    RCView::span(array('class'=>'df','style'=>'color:#777;'), '('.DateTimeRC::get_user_format_label().' H:M)') . RCView::br() .
                    // Display all active alerts displayed in this view
                    $lang['survey_441'] .
                    RCView::select(array('id'=>'filterAlert','style'=>'font-size:11px;margin:2px 3px;'),
                        (array(''=>$lang['alerts_27'])+$all_active_alerts), ($_GET['filterAlert']??""),300) .
                    RCView::br() .
                    // Display record names displayed in this view
                    $lang['survey_441'] .
                    Records::renderRecordListAutocompleteDropdown(PROJECT_ID, true, 5000, 'filterRecord',
                        "", "margin-left:3px;font-size:11px;", ($_GET['filterRecord']??""), $lang['reporting_37'], $lang['alerts_205']) .
                    RCView::br() .
                    // "Apply filters" button
                    RCView::button(array('class'=>'jqbuttonsm','style'=>'margin-top:5px;font-size:11px;color:#800000;','onclick'=>"loadNotificationLog(1)"), $lang['survey_442']) .
                    RCView::a(array('href'=>PAGE_FULL."?pid=".PROJECT_ID."&route=AlertsController:setup&log=1",'style'=>'vertical-align:middle;margin-left:15px;text-decoration:underline;font-weight:normal;font-size:11px;'), $lang['setup_53'])
                ) .
                RCView::div(array('class'=>'clear'), '')
            );
        }
        $width = 948;
        // Build Invitation Log table
        return renderGrid("notification_log_table", $title, $width, 'auto', $headers, $rows, true, true, false);
    }


    // Display list of checkboxes for all alerts for re-triggering alerts for all records in a project
    public function displayAlertCheckboxList($project_id)
    {
        global $lang;
        $Proj = new Project($project_id);
        // Get a list of all projects that are using active alerts
        $sql = "SELECT a.* FROM redcap_alerts a, redcap_projects p
                WHERE a.email_deleted = 0 AND p.status <= 1 AND p.date_deleted is null AND p.completed_time is null AND p.project_id = a.project_id 
                AND p.project_id = $project_id
                order by a.alert_order, a.alert_id";
        $q = db_query($sql);
        $alerts = array();
        while ($row = db_fetch_assoc($q)) {
            $alerts[$row['alert_id']] = $row;
        }
        // Loop through each alert for this project
        $html = "";
        foreach ($alerts as $alert_id=>$attr)
        {
            $alert_num = $this->getKeyIdFromAlertId($project_id, $alert_id) + 1;
            $html .= RCView::div(array('class'=>'mt-1'),
                        RCView::checkbox(array('id'=>'alert_'.$alert_id, 'checked'=>'checked')) .
                        $lang['alerts_24']." #".$alert_num .
                        ($attr['alert_title'] == '' ? "" : $lang['colon']." ".strip_tags($attr['alert_title'])) .
                        " (".self::ALERT_UNIQUE_ID_PREFIX.$alert_id.")"
                     );

        }
        return RCView::p(array('class'=>'mt-0'), $lang['alerts_258']) .
                        RCView::p(array(), RCView::b($lang['alerts_261']).$lang['alerts_262']).
                        RCView::p(array(), $lang['alerts_259']) .
                        RCView::iife('
                          var checkbox = document.getElementById("alerts-dry-run-toggle-switch");
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
                          styleSheet.insertRule("@keyframes DryRunSlideFadeInMssg { from { opacity: 0; transform: translateY(-100%); } to { opacity: 1; transform: translateY(0); } }");
                          styleSheet.insertRule("@keyframes DryRunSlideFadeOutMssg { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-100%); } }");
                        ') .
                        RCView::p(array('class' => 'dry-run text-dangerrc fs13 my-3', 'style' => 'display:none;'), RCView::fa('fa-solid fa-flask-vial mr-1') . $lang['alerts_403']) .
                        RCView::div(array('class'=>'gray mb-0'),
                            RCView::span(array('class'=>'mb-0 font-weight-bold', 'style'=>'color:#A00000;'), $lang['alerts_260']) .
                            RCView::a(array('class'=>'ms-5 fs11', 'onclick'=>"$('#reeval_alert_dlg input[type=checkbox]').not('#alerts-dry-run-toggle-switch').prop('checked',true);", 'style'=>'text-decoration:underline'), $lang['survey_41']) .
                            RCView::a(array('class'=>'ms-2 fs11', 'onclick'=>"$('#reeval_alert_dlg input[type=checkbox]').not('#alerts-dry-run-toggle-switch').prop('checked',false);", 'style'=>'text-decoration:underline'), $lang['survey_42']) .
                            (!empty($alerts) ? RCView::div(array('style' => "float:right; text-align:start;"), RCView::toggle(array('id' => 'alerts-dry-run-toggle-switch','style' => 'width:48px;height:24px;margin-left:15px;'), RCView::label(array('class' => "font-weight-bold fs11 mb-1"), $lang['alerts_402']))) : '') .
                            $html
                        );
    }
    // Move an alert
    public function moveAlert()
    {
        global $lang;
        $move_alert_id =  (int)$_REQUEST['alert_id'];
        $projectData = $this->getAlertSettings(PROJECT_ID);

        if ($_POST['action'] == 'view')
        {
            // Build alerts drop-down list
            $all_alerts_dd = "<select id='move_after_alert' style='font-weight:normal;width:100%;'>
                                <option value=''>-- {$lang['alerts_269']} --</option>";


            // Loop through all alerts
            $alert_number = 0;
            $title_confirm = '';
            foreach ($projectData as $alert_id => $attr) {
                $alert_number++;
                $alertTitle = (trim($attr['alert_title']) == '') ? '' : $lang['colon'].' <span class="font-weight-normal">'.RCView::escape($attr['alert_title']).'</span>';
                $alertStatus = ($attr['email_deleted'] == '1') ? ' ['.$lang['alerts_288'].']' : '';
                $alertTitleFull = $lang['alerts_24']." #" .$alert_number.$alertTitle . $alertStatus;
                if ($alert_id == $move_alert_id) {
                    $title_confirm =  RCView::span(array('style'=>'color:#A00000;font-weight:bold;font-family:verdana;font-size:14px;'), '"' . $lang['alerts_24']." #" .$alert_number.$alertTitle . '"').RCView::SP . RCView::SP .
                                      RCView::span(array('style'=>'color:#A00000;font-family:verdana;font-size:14px;'), "(".self::ALERT_UNIQUE_ID_PREFIX.$alert_id.")") . RCView::br();
                    $all_alerts_dd .= "<optgroup label='".$lang['alerts_275']."'></optgroup>";
                } else {
                    $all_alerts_dd .= "<option value='$alert_id'>$alertTitleFull (".self::ALERT_UNIQUE_ID_PREFIX.$alert_id.")</option>";
                }
            }
            // Add closing select list
            $all_alerts_dd .= "</select>";

            $text1 = $lang['alerts_270'];
            $text2 = $lang['alerts_271'];
            $text3 = $lang['alerts_273'];
            $title = $lang['alerts_272'];

            // Popup content
            $html = RCView::div('',
                        RCView::p('', $text1) .
                        RCView::div(array('style'=>'font-size:13px;width:95%;margin-top:15px;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;'),
                            RCView::b($text2) . RCView::SP . RCView::SP .$title_confirm ).
                        RCView::div(array('style'=>'line-height:1.6em;margin:20px 0;font-weight:bold;background-color:#f5f5f5;border:1px solid #ccc;padding:10px;width:95%;'),
                            $text3 . RCView::br() . $all_alerts_dd
                        )
                    );

            // Output JSON
            print json_encode_rc(array('payload' => $html, 'title' => $title));
            exit;
        }
        ## MOVE AND SAVE IN NEW POSITION
        elseif ($_POST['action'] == 'save' && isset($_POST['move_after_alert']) && isset($projectData[$_POST['move_after_alert']])) {
            $alert_id = $_POST['alert_id'];
            if (!isinteger($alert_id)) exit("ERROR");
            $after_alert_id = $_POST['move_after_alert'];
            $pid = $_GET['pid'];

            $pos = $projectData[$alert_id]['alert_order'];
            $new_pos = $projectData[$after_alert_id]['alert_order'];

            $alert_number = 0;
            $alertTitle = '';
            foreach($projectData as $id => $alerts) {
                $alert_number++;
                if ($id == $alert_id) {
                    $title = (trim($alerts['alert_title']) == '') ? '' : $lang['colon']." ".RCView::escape($alerts['alert_title']);
                    $alertTitle = $lang['alerts_24']." #" .$alert_number.$title.' (<i>'.self::ALERT_UNIQUE_ID_PREFIX.$alert_id . '</i>)';
                }
            }
            if($pos != $new_pos) {
                if($new_pos > $pos) {
                    $sql = "UPDATE redcap_alerts 
                            SET alert_order = alert_order -1 
                            WHERE project_id = '".$pid."' AND alert_order <= '".$new_pos."' AND alert_order > '".$pos."'";
                    db_query($sql);

                    $sql2 = "UPDATE redcap_alerts 
                             SET alert_order='".$new_pos."' 
                             WHERE project_id = '".$pid."' AND alert_id = '".$alert_id."'";
                    db_query($sql2);
                } else {
                    $sql = "UPDATE redcap_alerts 
                            SET alert_order = alert_order + 1 
                            WHERE project_id = '".$pid."' AND alert_order > '".$new_pos."' AND alert_order < '".$pos."'";
                    db_query($sql);

                    $sql2 = "UPDATE redcap_alerts 
                             SET alert_order='".($new_pos + 1)."' 
                             WHERE project_id = '".$pid."' AND alert_id = '".$alert_id."'";
                    db_query($sql2);
                }
            }
            Logging::logEvent("", "redcap_alerts", "MANAGE", $alert_id, strip_tags($alertTitle), "Move alert");
            // Set HTML success message
            $alert_msg = RCView::div(array('class'=>'fs14'),
                            RCView::b($alertTitle . $lang['colon']." ") . $lang['alerts_279']
                         ) .
                         RCView::div(array('class'=>'fs14 text-danger mt-3'), $lang['alerts_313']);
            $_SESSION['move_alert_msg'] = $alert_msg;
            $_SESSION['focus_alert_id'] = $alert_id;
        }
    }

	// Get list of all required attributes for Download/Upload Alerts CSV
	public function getAlertsCSVAttributes() {
	    global $Proj;
	    $headerArr = array('alert-unique-id','alert-title','alert-trigger','unique-form-name', 'unique-event-name','saved-with-form-status','alert-condition',
	                 'ensure-logic-still-true', 'do-not-clear-recurrences', 'alert-stop-type','send-on','send-on-next-day-type',
	                 'send-on-next-time','send-on-time-lag-days','send-on-time-lag-hours',
	                 'send-on-time-lag-minutes','send-on-field-after','send-on-field',
	                 'send-on-date','alert-send-how-many', 'every-time-type', 'repeat-for','repeat-for-units','repeat-for-max',
	                 'alert-expiration','alert-type','email-from-display','email-from','email-to','email-cc','email-bcc','email-failed','email-subject',
	                 'alert-message', 'prevent-piping-identifiers', 'file-upload-fields','phone-number-to', 'alert-deactivated',
					 'sendgrid-template-id', 'sendgrid-template-data', 'sendgrid-mail-send-configuration');
	    if (!$Proj->longitudinal) {
	        $key = array_search('unique-event-name', $headerArr);
	        unset($headerArr[$key]);
	    }
	    return $headerArr;
	}

    // Download Alerts - CSV
    public function downloadAlerts() {
        Logging::logEvent("", "redcap_alerts", "MANAGE", PROJECT_ID, "", "Download alerts as CSV file");

        $defaultHeader = implode(",", $this->getAlertsCSVAttributes());

        // Instantiate Alerts object
        $projectData = $this->getAlertSettings(PROJECT_ID);

        $alerts = [];
        $Proj = new Project(PROJECT_ID);
        $longitudinal = $Proj->longitudinal;
        if ($longitudinal) $unique_events = $Proj->getUniqueEventNames();
        foreach ($projectData as $alertId => $row) {
            $form_unique_name = '';
            $event_unique_name = '';

            if (!empty($row['form_name'])) {
                $form_unique_name = $row['form_name'];
                if ($longitudinal) {
                    $event_unique_name = $unique_events[$row['form_name_event']];
                }
            }
            $alert_send_how_many = '';
            $every_time_type = '';
            if ($row['email_repetitive'] == '1' || $row['email_repetitive_change'] == '1') {
                $alert_send_how_many = 'every';
                if ($row['email_repetitive'] == '1') {
                    $every_time_type = 'every';
                } else if ($row['email_repetitive_change'] == '1' && $row['email_repetitive_change_calcs'] == '1') {
                    $every_time_type = 'every-change-calcs';
                } else {
                    $every_time_type = 'every-change';
                }
            } else if ($row['cron_repeat_for'] == '0' || $row['cron_repeat_for'] == '') {
                $alert_send_how_many = 'once';
            } else {
                $alert_send_how_many = 'schedule';
            }

            $trigger = '';
            if (($form_unique_name == '') && $row['alert_condition'] != '') {
                // Logic only
                $trigger = 'logic';
            } else if ($form_unique_name != '' && $row['alert_condition'] != '') {
                // Form submit + logic
                $trigger = 'submit-logic';
            } else {
                // Form submit only
                $trigger = 'submit';
            }
            $send_on_next_day_type = $send_on_next_time = '';
            if ($row['cron_send_email_on'] == 'next_occurrence') {
                $send_on_next_day_type = $row['cron_send_email_on_next_day_type'];
                $send_on_next_time = substr($row['cron_send_email_on_next_time'], 0, 5); // show in hh:mm format
            }
            $send_on_time_lag_days = $send_on_time_lag_hours = $send_on_time_lag_minutes = $send_on_field_after = $send_on_field = '';
            if ($row['cron_send_email_on'] == 'time_lag') {
                $send_on_time_lag_days = $row['cron_send_email_on_time_lag_days'];
                $send_on_time_lag_hours = $row['cron_send_email_on_time_lag_hours'];
                $send_on_time_lag_minutes = $row['cron_send_email_on_time_lag_minutes'];
                $send_on_field_after = $row['cron_send_email_on_field_after'];
                $send_on_field = $row['cron_send_email_on_field'];
            }

            $send_on_date = ($row['cron_send_email_on'] == 'date')
                            ? DateTimeRC::format_user_datetime($row['cron_send_email_on_date'], 'Y-M-D_24', 'M/D/Y_24')
                            : '';

            $repeat_for = $repeat_for_units = $repeat_for_max = '';
            if ($alert_send_how_many == 'schedule') {
                $repeat_for = $row['cron_repeat_for'];
                $repeat_for_units = $row['cron_repeat_for_units'];
                $repeat_for_max = $row['cron_repeat_for_max'];
            }
            if ($alert_send_how_many != 'every') {
                $every_time_type = '';
            }
            $alertArr = array('alert-unique-id' => self::ALERT_UNIQUE_ID_PREFIX.$row['alert_id'],
                            'alert-title' => $row['alert_title'],
                            'alert-trigger' => strtoupper($trigger),
                            'unique-form-name' => $form_unique_name,
                            'unique-event-name' => $event_unique_name,
                            'saved-with-form-status' => ($row['email_incomplete'] == 1) ? 'ANY' : 'COMPLETE',
                            'alert-condition' => $row['alert_condition'],
                            'ensure-logic-still-true' => ($row['ensure_logic_still_true'] == 1) ? 'Y' : 'N',
                            'do-not-clear-recurrences' => ($row['do_not_clear_recurrences'] == 1) ? 'Y' : 'N',
                            'alert-stop-type' => strtoupper($row['alert_stop_type']),
                            'send-on' => strtoupper($row['cron_send_email_on']),
                            'send-on-next-day-type' => strtoupper($send_on_next_day_type),
                            'send-on-next-time' => $send_on_next_time,
                            'send-on-time-lag-days' => $send_on_time_lag_days,
                            'send-on-time-lag-hours' => $send_on_time_lag_hours,
                            'send-on-time-lag-minutes' => $send_on_time_lag_minutes,
                            'send-on-field-after' => $send_on_field_after,
                            'send-on-field' => $send_on_field,
                            'send-on-date' => $send_on_date,
                            'alert-send-how-many' => strtoupper($alert_send_how_many),
                            'every-time-type' => strtoupper($every_time_type),
                            'repeat-for' => $repeat_for,
                            'repeat-for-units' => strtoupper($repeat_for_units),
                            'repeat-for-max' => $repeat_for_max,
                            'alert-expiration' => DateTimeRC::format_user_datetime($row['alert_expiration'], 'Y-M-D_24', 'M/D/Y_24'),
                            'alert-type' => $row['alert_type'],
                            'email-from-display' => $row['email_from_display'],
                            'email-from' => $row['email_from'],
                            'email-to' => $row['email_to'],
                            'email-cc' => $row['email_cc'],
                            'email-bcc' => $row['email_bcc'],
                            'email-failed' => $row['email_failed'],
                            'email-subject' => $row['email_subject'],
                            'alert-message' => $row['alert_message'],
							'sendgrid-template-id' => $row['sendgrid_template_id'],
							'sendgrid-template-data' => $row['sendgrid_template_data'],
                            'sendgrid-mail-send-configuration' => $row['sendgrid_mail_send_configuration'],
                            'prevent-piping-identifiers' => ($row['prevent_piping_identifiers'] == 1) ? 'Y' : 'N',
                            'file-upload-fields' => $row['email_attachment_variable'],
                            'phone-number-to' => $row['phone_number_to'],
                            'alert-deactivated' => ($row['email_deleted'] == 1) ? 'Y' : 'N');
            if (!$longitudinal) {
                unset($alertArr['unique-event-name']);
            }
            $alerts[] = $alertArr;
        }

        $content = (!empty($alerts)) ? arrayToCsv($alerts) : $defaultHeader;

        $project_title = REDCap::getProjectTitle();
        $filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($project_title, ENT_QUOTES)))), 0, 30)
                    ."_Alerts_".date("Y-m-d").".csv";

        header('Pragma: anytextexeptno-cache', true);
        header("Content-type: application/csv");
        header('Content-Disposition: attachment; filename=' . $filename);
        echo addBOMtoUTF8($content);
        exit;
    }

    // Get list of emails to compare with email-to, email-cc, email-bcc while upload CSV
    public function getEmailsList() {
        extract($GLOBALS);
        // Get email addresses from table
        $fromEmails = $this->getFromEmails();
        // Set the To email addresses as the projects users
        $toEmails = array();
        foreach ($fromEmails as $thisFromEmail) {
            $toEmails['project_users'][] = $thisFromEmail;
        }

        if ($alerts_allow_email_variables || SUPER_USER)
        {
            $emailFieldsLabels = Form::getFieldDropdownOptions(false, false, false, false);
            if (!empty($emailFieldsLabels)) {
                foreach ($emailFieldsLabels as $formLabel=>$emailFields) {
                    if (!is_array($emailFields)) continue;
                    foreach ($emailFields as $thisVar=>$thisOptionLabel) {
                        if ($longitudinal) {
                            $toEmails['email_fields'][] = "[$thisVar]";
                            foreach ($Proj->eventsForms as $thisEventId=>$theseForms) {
                                $thisEventName = $Proj->getUniqueEventNames($thisEventId);
                                $thisForm = $Proj->metadata[$thisVar]['form_name'];
                                if (in_array($thisForm, $theseForms)) {
                                    $toEmails['email_fields'][] = "[$thisEventName][$thisVar]";
                                }
                            }
                        } else {
                            $toEmails['email_fields'][] = "[$thisVar]";
                        }
                    }
                }
            }
        }
        return $toEmails;
    }

    // Upload Alerts - CSV
    public function uploadAlerts() {
        global $lang, $Proj;
        $csv_content = $preview = "";
        $commit = false;
        if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name'])) {
            $csv_content = file_get_contents($_FILES['file']['tmp_name']);
            $csv_content = fixUTF8($csv_content, true);
        } elseif (isset($_POST['csv_content']) && $_POST['csv_content'] != '') {
            $csv_content = $_POST['csv_content'];
            $commit = true;
        }

        if ($csv_content != "")
        {
            $uniqueEventNames = $Proj->getUniqueEventNames();
            $data = csvToArray(removeBOM($csv_content));

            // Begin transaction
            db_query("SET AUTOCOMMIT=0");
            db_query("BEGIN");

            $allAlerts = $this->getAlertSettings(PROJECT_ID);
            $storedAlerts = [];
            $toUppercaseKeys = array('alert-trigger', 'saved-with-form-status', 'ensure-logic-still-true', 'do-not-clear-recurrences', 'alert-type', 'alert-stop-type',
                                     'send-on', 'send-on-field-after', 'send-on-next-day-type', 'alert-send-how-many', 'every-time-type', 'repeat-for-units',
                                     'prevent-piping-identifiers', 'alert-deactivated');

            foreach ($allAlerts as $alert_id => $alertData) {
                $alertData['email_incomplete'] = str_replace(array('1', '0'), array('ANY', 'COMPLETE'), $alertData['email_incomplete']);
                $alertData['ensure_logic_still_true'] = str_replace(array('0', '1'), array('N', 'Y'), $alertData['ensure_logic_still_true']);
                $alertData['do_not_clear_recurrences'] = str_replace(array('0', '1'), array('N', 'Y'), $alertData['do_not_clear_recurrences']);
                $alertData['prevent_piping_identifiers'] = str_replace(array('0', '1'), array('N', 'Y'), $alertData['prevent_piping_identifiers']);
                $alertData['alert_message'] = htmlentities($alertData['alert_message']);
                if (($alertData['form_name'] == '-' || $alertData['form_name'] == '') && $alertData['alert_condition'] != '') {
                    // Logic only
                    $alertData['alert_trigger'] = 'logic';
                } else if ($alertData['form_name'] != '-' && $alertData['form_name'] != '' && $alertData['alert_condition'] != '') {
                    // Form submit + logic
                    $alertData['alert_trigger'] = 'submit-logic';
                } else {
                    // Form submit only
                    $alertData['alert_trigger'] = 'submit';
                }
                $every_time_type = '';
                if ($alertData['email_repetitive'] == '1' || $alertData['email_repetitive_change'] == '1') {
                    $alert_send_how_many = 'every';
                    if ($alertData['email_repetitive'] == '1') {
                        $every_time_type = 'every';
                    } else if ($alertData['email_repetitive_change'] == '1' && $alertData['email_repetitive_change_calcs'] == '1') {
                        $every_time_type = 'every-change-calcs';
                    } else {
                        $every_time_type = 'every-change';
                    }
                } else if ($alertData['cron_repeat_for'] == '0' || $alertData['cron_repeat_for'] == '') {
                    $alert_send_how_many = 'once';
                } else {
                    $alert_send_how_many = 'schedule';
                }
                $alertData['every_time_type'] = $every_time_type;
                $alertData['alert_send_how_many'] = $alert_send_how_many;
                $alertData['prevent_piping_identifiers'] = str_replace(array('0', '1'), array('n', 'y'), $alertData['prevent_piping_identifiers']);
                $alertData['alert_deactivated'] = str_replace(array('0', '1'), array('n', 'y'), $alertData['email_deleted']);
                if ($alertData['cron_send_email_on'] != 'next_occurrence') {
                    $alertData['cron_send_email_on_next_day_type'] = '';
                }
                if ($alertData['cron_send_email_on'] != 'time_lag') {
                    $alertData['cron_send_email_on_field_after'] = '';
                    $alertData['cron_send_email_on_field'] = '';
                }
                if ($alert_send_how_many != 'every') {
                    $alertData['every_time_type'] = '';
                }
                if ($alert_send_how_many != 'schedule') {
                    $alertData['cron_repeat_for'] = '';
                    $alertData['cron_repeat_for_units'] = '';
                }
                // Replace "_" to "-" from DB field labels to match with CSV keys
                $storedAlerts[$alert_id] = array_combine(array_map(function($str){ return str_replace(array("cron_","_email_","_"), array("","-","-"),$str); }, array_keys($alertData)),array_values($alertData));

                foreach ($toUppercaseKeys as $key) {
                    $storedAlerts[$alert_id][$key] = strtoupper($storedAlerts[$alert_id][$key]);
                }
            }
            foreach ($data as $key => $alert) {
                foreach ($toUppercaseKeys as $k) {
                    $data[$key][$k] = $alert[$k] = strtoupper($data[$key][$k]);
                }
                // Set saved-with-form-status default to "ANY"
                if (isset($alert['saved-with-form-status']) && !in_array($alert['saved-with-form-status'], array('ANY', 'COMPLETE'))) {
                    $data[$key]['saved-with-form-status'] = 'ANY';
                }
                // Set alert-type default to "EMAIL"
                if (isset($alert['alert-type']) && !in_array($alert['alert-type'], array('EMAIL', 'SMS', 'VOICE_CALL', 'SENDGRID_TEMPLATE'))) {
                    $data[$key]['alert-type'] = 'EMAIL';
                }

                // Set alert-stop-type default to "RECORD"
                if (isset($alert['alert-stop-type']) && !in_array($alert['alert-stop-type'], array('RECORD', 'RECORD_INSTRUMENT', 'RECORD_EVENT_INSTRUMENT', 'RECORD_EVENT', 'RECORD_EVENT_INSTRUMENT_INSTANCE'))) {
                    $data[$key]['alert-stop-type'] = 'RECORD';
                }

                // Set send-on default to "NOW"
                if (isset($alert['send-on']) && !in_array($alert['send-on'], array('NOW', 'NEXT_OCCURRENCE', 'TIME_LAG', 'DATE'))) {
                    $data[$key]['send-on'] = 'NOW';
                }

                if ($alert['send-on'] == 'TIME_LAG') {
                    // Set send-on-field-after default to "AFTER"
                    if (isset($alert['send-on-field-after']) && !in_array($alert['send-on-field-after'], array('AFTER', 'BEFORE'))) {
                        $data[$key]['send-on-field-after'] = 'AFTER';
                    }
                } else if ($alert['send-on'] == 'NEXT_OCCURRENCE') {
                    // Add days of the week + work day + weekend day in list options
                    $daysOfWeekDD = SurveyScheduler::daysofWeekOptions();
                    unset($daysOfWeekDD['']);

                    // Set send-on-next-day-type default to "DAY"
                    if (!in_array($alert['send-on-next-day-type'], array_keys($daysOfWeekDD))) {
                        $data[$key]['send-on-next-day-type'] = 'DAY';
                    }
                }

                if ($alert['send-on'] != 'NEXT_OCCURRENCE') {
                    $data[$key]['send-on-next-day-type'] = $data[$key]['send-on-next-time'] = '';
                }
                if ($alert['send-on'] != 'TIME_LAG') {
                    $data[$key]['send-on-time-lag-days'] = $data[$key]['send-on-time-lag-hours'] = $data[$key]['send-on-time-lag-minutes'] = '';
                    $data[$key]['send-on-field-after'] = $data[$key]['send-on-field'] = '';
                }
                if ($alert['send-on'] != 'DATE') {
                    $data[$key]['send-on-date'] = '';
                }
                // Set alert-send-how-many default to "ONCE"
                if (isset($alert['alert-send-how-many']) && !in_array($alert['alert-send-how-many'], array('ONCE', 'EVERY', 'SCHEDULE'))) {
                    $data[$key]['alert-send-how-many'] = 'ONCE';
                }
                if ($alert['alert-send-how-many'] == 'SCHEDULE') {
                    // Set repeat-for-units default to "DAYS"
                    if (isset($alert['repeat-for-units']) && !in_array($alert['repeat-for-units'], array('MINUTES', 'HOURS', 'DAYS'))) {
                        $data[$key]['repeat-for-units'] = 'DAYS';
                    }
                }
                if ($alert['alert-send-how-many'] == 'EVERY') {
                    // Set every-time-type default to "EVERY"
                    if (isset($alert['every-time-type']) && !in_array($alert['every-time-type'], array('EVERY', 'EVERY-CHANGE', 'EVERY-CHANGE-CALCS'))) {
                        $data[$key]['every-time-type'] = 'EVERY';
                    }
                    $data[$key]['email-repetitive'] = $data[$key]['email-repetitive-change'] = $data[$key]['email-repetitive-change-calcs'] = 0;
                    if ($alert['every-time-type'] == 'EVERY-CHANGE-CALCS') {
                        $data[$key]['email-repetitive'] = 0;
                        $data[$key]['email-repetitive-change'] = $data[$key]['email-repetitive-change-calcs'] = 1;
                    } else if ($alert['every-time-type'] == 'EVERY-CHANGE') {
                        $data[$key]['email-repetitive-change'] = 1;
                        $data[$key]['email-repetitive'] = $data[$key]['email-repetitive-change-calcs'] = 0;
                    } else {
                        $data[$key]['email-repetitive'] = 1;
                        $data[$key]['email-repetitive-change'] = $data[$key]['email-repetitive-change-calcs'] = 0;
                    }
                } else {
                    $data[$key]['every-time-type'] = '';
                }
                // Set alert-deactivated default to "N"
                if (!in_array($alert['alert-deactivated'], array('Y', 'N'))) {
                    if (empty($alert['alert-unique-id'])) {
                        // If new alert, set activated by default
                        $data[$key]['alert-deactivated'] = 'N';
                    } else {
                        $id = substr($alert['alert-unique-id'], strlen(self::ALERT_UNIQUE_ID_PREFIX));
                        // If existing alert, set to db value
                        $data[$key]['alert-deactivated'] = str_replace(array('1', '0'), array('Y', 'N'), $allAlerts[$id]['email_deleted']);
                    }
                }
                // Set ensure-logic-still-true default to 'N'
                if ($alert['ensure-logic-still-true'] != 'Y') {
                    $data[$key]['ensure-logic-still-true'] = 'N';
                }
                // Set do-not-clear-recurrences default to 'N'
                if ($alert['do-not-clear-recurrences'] != 'Y') {
                    $data[$key]['do-not-clear-recurrences'] = 'N';
                }
                // Set prevent-piping-identifiers default to "Y"
                if (!in_array($alert['prevent-piping-identifiers'], array('Y', 'N'))) {
                    $data[$key]['prevent-piping-identifiers'] = 'Y';
                }
            }
            list ($count, $errors) = $this->validateCSVContent($data);

            // Build preview of changes being made
            if (!$commit && empty($errors))
            {
                $Proj = new Project(PROJECT_ID);
                $cells = "";
                foreach (array_keys($data[0]) as $this_hdr) {
                    $cells .= RCView::td(array('style'=>'padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'), $this_hdr);
                }
                $rows = RCView::tr(array(), $cells);

                $row_num = 0;
                foreach($data as $alert)
                {
                    $row_num++;
                    if (empty($alert['alert-unique-id'])) {
                        // New Alert
                        $tds = '';
                        foreach ($alert as $key => $value) {
                            if ($key == 'alert-message') {
                                $text = '<div class="attributes-list">'.htmlentities($value).'</div>';
                                $value = RCView::simpleDialog($text, $lang['alerts_290']." - ".$lang['dataqueries_343']. " ".$row_num, 'originalBody_'.$row_num);
                                $value .= RCView::span(array('style'=>'cursor:pointer; text-decoration:underline;', 'onclick'=>"simpleDialog(null,null,'originalBody_".$row_num."', 900);"), $lang['alerts_290']);
                            }
                            $tds .= RCView::td(array('class'=>'green'), $value);
                        }
                        // Add row
                        $rows .= RCView::tr(array(), $tds);
                    } else {
                        // Updated Alert
                        $alert_id = substr($alert['alert-unique-id'], strlen(self::ALERT_UNIQUE_ID_PREFIX));
                        $tds = '';
                        $updatedKeys = [];
                        foreach ($alert as $key => $value) {
                            if (in_array($key, array('email-repetitive', 'email-repetitive-change', 'email-repetitive-change-calcs'))) continue;
                            $changedCronSendEmailOn = $highlight = false;
                            $class = 'gray';
                            $old_value = "";
                            if ($key == 'alert-unique-id') {
                                $class = 'gray';
                            } else {
                                if ($key == 'saved-with-form-status') $key = 'email-incomplete';
                                if ($key == 'file-upload-fields') $key = 'email-attachment-variable';
                                if ($key == 'alert-message') {
                                    $value = htmlentities(label_decode($value));
                                    $value = trim(preg_replace('/\s\s+/', ' ', $value));
                                    $value = str_replace(array("\n", "\r\n"), array(" ", " "), $value);

                                    $formattedEmailBody = htmlentities(label_decode($storedAlerts[$alert_id][$key]), ENT_QUOTES);
                                    $formattedEmailBody = trim(preg_replace('/\s\s+/', ' ', $formattedEmailBody));
                                    $formattedEmailBody = str_replace(array("\n", "\r\n"), array(" ", " "), $formattedEmailBody);

                                    if (trim($formattedEmailBody) != trim($value)) {
                                        $class = 'yellow';
                                        $old_value = $formattedEmailBody;
                                        $text = "<b>".$lang['alerts_292'].":</b><br><div class='attributes-list'>".$old_value."</div>
                                                 <br><b>".$lang['alerts_293'].":</b><br><div class='attributes-list'>".$value."</div>";
                                        $value = RCView::simpleDialog($text, $lang['alerts_291']." - ".$lang['dataqueries_343']. " ".$row_num, 'previewChange_'.$row_num);
                                        $value .= RCView::span(array('style'=>'cursor:pointer; text-decoration:underline;', 'onclick'=>"simpleDialog(null,null,'previewChange_".$row_num."', 900);"), $lang['alerts_291']);
                                    } else {
                                        $class = 'gray';
                                        $text = '<div class="attributes-list">'.$value.'</div>';
                                        $value = RCView::simpleDialog($text, $lang['alerts_290']." - ".$lang['dataqueries_343']. " ".$row_num, 'originalBody_'.$row_num);
                                        $value .= RCView::span(array('style'=>'cursor:pointer; text-decoration:underline;', 'onclick'=>"simpleDialog(null,null,'originalBody_".$row_num."', 900);"), $lang['alerts_290']);
                                    }
                                    $old_value = '';
                                } else if (in_array($key, array('alert-expiration', 'send-on-date'))) {
                                    $value = ($value != '') ? DateTimeRC::format_ts_to_ymd($value).':00': '';
                                    if ($value != '') {
                                        list($date, $time) = explode(" ", $value);
                                        list($hour, $min, $sec) = explode(":", $time);
                                        $value = str_replace("/", "-", $date)." ".str_pad($hour, 2, '0', STR_PAD_LEFT).":".str_pad($min, 2, '0', STR_PAD_LEFT).":".$sec;
                                    }
                                    if ($storedAlerts[$alert_id][$key] != $value) {
                                        $class = 'yellow';
                                        $old_value = $storedAlerts[$alert_id][$key];
                                    }
                                } else if ($key == 'unique-form-name') {
                                    $formattedFormName = $storedAlerts[$alert_id]['form-name'];
                                    if ($formattedFormName != $value) {
                                        $class = 'yellow';
                                        $old_value = $formattedFormName;
                                    }
                                } else if ($key == 'unique-event-name') {
                                    $event_id = array_search($value, $uniqueEventNames);
                                    $formattedEventId = $storedAlerts[$alert_id]['form-name-event'];
                                    if ($formattedEventId != $event_id) {
                                        $class = 'yellow';
                                        $old_value = $uniqueEventNames[$formattedEventId];
                                    }
                                } else if ($key == 'send-on-next-time') {
                                    if (substr($storedAlerts[$alert_id]['send-on-next-time'], 0, 5) != $value) {
                                        $class = 'yellow';
                                        $old_value = $storedAlerts[$alert_id][$key];
                                    }
                                } else if ($storedAlerts[$alert_id][$key] != $value) {
                                    $class = 'yellow';
                                    $old_value = $storedAlerts[$alert_id][$key];
                                }
                            }
                            $old_value = ($old_value != '') ? RCView::div(array('style'=>'color:#777;font-size:11px;'), "({$old_value})") : '';
                            $tds .= RCView::td(array('id'=>$key,'class'=>$class), ($key == 'alert-message' ? $value.$old_value : filter_tags($value.$old_value)));

                            if ($class == 'yellow') $updatedKeys[] = $key;
                            $changedCronSendEmailOn = ($changedCronSendEmailOn == false)
                                                        ? (count(array_intersect($updatedKeys, self::CRON_SEND_EMAIL_ON_FIELDS)) !== 0)
                                                        : true;
                            if ($alert['alert-send-how-many'] == 'SCHEDULE' && $changedCronSendEmailOn == true && $highlight == false) {
                                $highlight = true;
                            }
                        }

                        $attr['class'] = ($highlight == true) ? 'highlight-row' : '';
                        // Add row
                        $rows .= RCView::tr($attr, $tds);
                    }
                }
                $preview = RCView::table(array('cellspacing'=>1), $rows);
            }
            if ($commit && empty($errors)) {
                // Commit
                $csv_content = "";
                db_query("COMMIT");
                db_query("SET AUTOCOMMIT=1");
                Logging::logEvent("", "redcap_alerts", "MANAGE", PROJECT_ID, "$count ".$lang['alerts_284'], "Upload alerts as CSV file");
            } else {
                // ERROR: Roll back all changes made and return the error message
                db_query("ROLLBACK");
                db_query("SET AUTOCOMMIT=1");
            }
            $formattedErrors = [];
            foreach ($errors as $key => $errorsArr) {
                if (is_array($errorsArr)) {
                    $row_num_prefix = "<b>{$lang['dataqueries_343']}".($key + 1)."</b>: <br />";
                    $error_details = "<div style='padding-left: 20px;'>";
                    foreach ($errorsArr as $error) {
                        $error_details .= "<div><div style='float: left;'>- </div><div style='padding-left: 10px;'>".$error."</div></div>";
                    }
                    $error_details .= "</div>";
                    $formattedErrors[] = $row_num_prefix . $error_details;
                } else {
                    $formattedErrors[] = $errorsArr;
                }
            }

            $_SESSION['imported'] = 'emailalerts';
            $_SESSION['count'] = $count;
            $_SESSION['errors'] = $formattedErrors;
            $_SESSION['csv_content'] = $csv_content;
            $_SESSION['preview'] = $preview;
        }
        redirect(APP_PATH_WEBROOT . 'index.php?pid='.PROJECT_ID.'&route=AlertsController:setup');
    }

    // Validate CSV content
    public function validateCSVContent($data) {
        global $lang, $Proj;

        $count = 0;
        $row_count = 0;
        $errors = array();

        $csvAttr = $this->getAlertsCSVAttributes();
        $requiredFields = implode(", ", $csvAttr);
        $requiredFieldsError = $lang['design_641'] ." <div class='attributes-list'>". $requiredFields."</div>";
        if (empty($data)) {
            $errors[] = $requiredFieldsError;
            return array($count, $errors);
        } else {
            foreach ($csvAttr as $attr) {
                if (!isset($data[0][$attr])) {
                    $errors[] = $requiredFieldsError;
                    return array($count, $errors);
                }
            }
        }
        if (isset($_POST['cron-queue'])) {
            $updateQueue = $_POST['cron-queue'];
        }

		if (strpos(arrayToCsv($data), 'SENDGRID_TEMPLATE') !== false) {
			// If it looks like the csv data contains a SENDGRID_TEMPLATE alert,
			// then get sendgrid template ids and verified senders once here
			// to validate sendgrid alerts in the next foreach loop
			$sendgrid_api_key = SendGridRC::getAPIKeyByPid(PROJECT_ID);
			
			$sendgrid_verified_senders = SendGridRC::getVerifiedSenders($sendgrid_api_key, PROJECT_ID);
			$verified_sender_emails = array();
			foreach ($sendgrid_verified_senders as $sender) {
				array_push($verified_sender_emails, $sender['from_email']);
			}

			$sendgrid_authenticated_domains = SendGridRC::getAuthenticatedDomains($sendgrid_api_key, PROJECT_ID);
			$allowed_domains = array();
			foreach ($sendgrid_authenticated_domains as $domain) {
				array_push($allowed_domains, $domain['domain']);
			}

			$sendgrid_templates = SendGridRC::getDynamicTemplates($sendgrid_api_key, PROJECT_ID);
			$valid_template_ids = array();
			foreach ($sendgrid_templates as $template) {
				array_push($valid_template_ids, $template['id']);
			}

            $unsubscribe_groups = SendGridRC::getUnsubscribeGroups($sendgrid_api_key, PROJECT_ID);
            $valid_unsubscribe_group_ids = array();
            foreach ($unsubscribe_groups as $group) {
				array_push($valid_unsubscribe_group_ids, $group['id']);
			}
		}
	
        foreach($data as $alert)
        {
            // Set post array and pass to existing saveAlert function
            $_POST = $alert;
            $alert_unique_id = trim($alert['alert-unique-id']);
            $form_unique_name = trim($alert['unique-form-name']);
            $event_unique_name = trim($alert['unique-event-name']);
            $alert_message = trim($alert['alert-message']);
            $alert_logic = trim($alert['alert-condition']);
            $alert_type = trim($alert['alert-type']);
            $_POST['email-attachment-variable'] = $file_upload_fields = trim($alert['file-upload-fields']);
            $next_time = trim($alert['send-on-next-time']);

            ++$row_count;

            $storedPhone = $storedEmailTo = $storedEmailCC = $storedEmailBCC = '';
            if (!empty($alert_unique_id)) {
                if (substr($alert_unique_id, 0, strlen(self::ALERT_UNIQUE_ID_PREFIX)) !== self::ALERT_UNIQUE_ID_PREFIX) {
                    $errors[$row_count][] = $lang['alerts_285'];
                } else {
                    $alert_id = substr($alert_unique_id, strlen(self::ALERT_UNIQUE_ID_PREFIX));
                    $projectData = $this->getAlertSettings(PROJECT_ID);
                    if (!isset($projectData[$alert_id])) {
                        $errors[$row_count][] = $lang['alerts_286'];
                    } else {
                        $storedPhone = $projectData[$alert_id]['phone_number_to'];
                        $storedEmailTo = $projectData[$alert_id]['email_to'];
                        $storedEmailCC = $projectData[$alert_id]['email_cc'];
                        $storedEmailBCC = $projectData[$alert_id]['email_bcc'];
                        $_POST['index_modal_update'] = $alert_id;
                    }
                }
            }
            if ($_POST['ensure-logic-still-true'] != 'Y') {
                unset($_POST['ensure-logic-still-true']);
            }

            if ($_POST['do-not-clear-recurrences'] != 'Y') {
                unset($_POST['do-not-clear-recurrences']);
            }

            if ($_POST['prevent-piping-identifiers'] != 'Y') {
                unset($_POST['prevent-piping-identifiers']);
            }

            if ($form_unique_name == '' && $alert_logic == '') {
                $errors[$row_count][] = "{$lang['alerts_198']}";
            }

            if ($form_unique_name != '' && !isset($Proj->forms[$form_unique_name])) {
                $errors[$row_count][] = "{$lang['alerts_287']}";
            }

            $uniqueEventNames = $Proj->getUniqueEventNames();
            $event_id = '';
            if ($Proj->longitudinal) {
                if ($event_unique_name != '' && !in_array($event_unique_name, $uniqueEventNames)) {
                    $errors[$row_count][] = "{$lang['alerts_308']}";
                } else {
                    $event_id = array_search($event_unique_name, $uniqueEventNames);
                }
            } else {
                $event_id = $Proj->firstEventId;
            }

            if (trim($alert['alert-trigger']) != 'SUBMIT') {
                if ($alert_logic != '' && !LogicTester::isValid($alert_logic)) {
                    $errors[$row_count][] = "{$lang['design_713']} \"{$alert_logic}\"";
                }
            }

            if (!empty($next_time)) {
                $isError = false;
                if (strlen($next_time) != 5 && substr($next_time, 1, 1) != ':') {
                    $isError = true;
                } else if (intval(str_replace(':','',$next_time)) > 2359) {
                    $isError = true;
                }
                if ($isError == true) $errors[$row_count][] = "(<i>send-on-next-time</i>) ".$lang['config_functions_59'];
            }

            foreach (array('send-on-time-lag-days', 'repeat-for') as $numericField) {
                $isError = false;
                if (!empty($alert[$numericField])) {
                    if (!isinteger($alert[$numericField])) {
                        $isError = true;
                    } else if (!in_array($alert[$numericField], range(0,9999))) {
                        $isError = true;
                    }
                }
                if ($isError == true) $errors[$row_count][] = "(<i>".$numericField."</i>) ".$lang['alerts_306'];
            }
            $isError = false;
            if (!empty($alert['repeat-for-max'])) {
                if (!isinteger($alert['repeat-for-max'])) {
                    $isError = true;
                } else if (!in_array($alert['repeat-for-max'], range(2,9999))) {
                    $isError = true;
                }
            }
            if ($isError == true) $errors[$row_count][] = "(<i>repeat-for-max</i>) ".$lang['config_functions_57']." ".$lang['leftparen']."2 - 9999". $lang['rightparen'].$lang['period']." ".$lang['config_functions_58'];

            foreach (array('send-on-time-lag-hours', 'send-on-time-lag-minutes') as $numericField) {
                $isError = false;
                if (!empty($alert[$numericField])) {
                    if (!isinteger($alert[$numericField])) {
                        $isError = true;
                    } else if (!in_array($alert[$numericField], range(0,999))) {
                        $isError = true;
                    }
                }
                if ($isError == true) $errors[$row_count][] = "(<i>".$numericField."</i>) ".$lang['alerts_307'];
            }


            foreach (array('send-on-date', 'alert-expiration') as $datetimeField) {
                if (!empty(trim($alert[$datetimeField]))) {
                    if ($datetimeField == 'send-on-date') $alert['send-on'] = 'DATE';

                    if (false === DateTime::createFromFormat('m/d/Y H:i', trim($alert[$datetimeField]))) {
                        $errors[$row_count][] = "(<i>".$datetimeField."</i>) ".$lang['alerts_303'];
                    }
                }
            }

            if ($alert_type == 'EMAIL') {
                $storedEmails = array('email-to'=>explode(";", $storedEmailTo),
                                      'email-cc'=>explode(";",$storedEmailCC),
                                      'email-bcc'=>explode(";",$storedEmailBCC));
                $email_subject = trim($alert['email-subject']);
                $email_from = trim($alert['email-from']);
                $email_to = trim($alert['email-to']);
                $email_failed = trim($alert['email-failed']);

                $fromEmails = $this->getFromEmails();

                if ($email_from == '') {
                    $errors[$row_count][] = "{$lang['alerts_56']}";
                } else {
                    if (!in_array($email_from, $fromEmails)) {
                        $errors[$row_count][] = "{$lang['alerts_304']}";
                    }
                }
                if (!empty($email_failed)) {
                    if (!in_array($email_failed, $fromEmails)) {
                        $errors[$row_count][] = "{$lang['alerts_305']}";
                    }
                }
                if ($email_to == '') {
                    $errors[$row_count][] = "{$lang['alerts_197']}";
                }
                foreach (array('email-to', 'email-cc', 'email-bcc') as $email_field) {
                    if (trim($alert[$email_field]) != '') {
                        $error = $this->validateEmailFields(trim($alert[$email_field]), $email_field, $storedEmails[$email_field]);
                        if (!empty($error)) {
                            $errors[$row_count][] = $error;
                        }
                    }
                }
                if ($email_subject == '') {
                    $errors[$row_count][] = "{$lang['alerts_214']}";
                }
            } else {
                $storedPhoneList = explode(";", $storedPhone);
                $error = $this->validatePhoneFields(trim($alert['phone-number-to']), $storedPhoneList);
                if (!empty($error)) {
                    $errors[$row_count][] = $error;
                }
            }

            if ($alert_message == '' && $alert_type != 'SENDGRID_TEMPLATE') {
                $errors[$row_count][] = "{$lang['alerts_39']}";
            }

			if ($alert_type == 'SENDGRID_TEMPLATE' && $alert['sendgrid-template-data'] != '') {
				if(json_decode($alert['sendgrid-template-data'], true) === null) {
					$errors[$row_count][] = "{$lang['alerts_336']}";
				}		
			}

            if ($alert_type == 'SENDGRID_TEMPLATE' && $alert['sendgrid-mail-send-configuration'] != '') {
				if(json_decode($alert['sendgrid-mail-send-configuration'], true) === null) {
					$errors[$row_count][] = "{$lang['alerts_374']}";
				}		
			}

			if ($alert_type == 'SENDGRID_TEMPLATE') {
				// Input validation for email_from, email_to, email_cc, email_bcc, sendgrid_template_id
                // and sendgrid_mail_send_configuration
				$storedEmails = array('email-to'=>explode(";", $storedEmailTo),
									'email-cc'=>explode(";", $storedEmailCC),
									'email-bcc'=>explode(";", $storedEmailBCC));
				$email_subject = trim($alert['email-subject']);
				$sendgrid_from = trim($alert['email-from']);
				$email_to = trim($alert['email-to']);
				$sendgrid_template_id = trim($alert['sendgrid-template-id']);
				$fromEmails = $this->getFromEmails();

				if ($sendgrid_template_id == '') {
					$errors[$row_count][] = "{$lang['alerts_345']}";
				} else {
					$validTemplateId = false;
					if (in_array($sendgrid_template_id, $valid_template_ids)) {
						$validTemplateId = true;
					}
					if (!$validTemplateId) {
						$errors[$row_count][] = "{$lang['alerts_346']}";
					}
				}

				if ($sendgrid_from == '') {
					$errors[$row_count][] = "{$lang['alerts_56']}";
				} else {
					$validFromEmail = false;

					if (in_array($sendgrid_from, $verified_sender_emails)) {
						$validFromEmail = true;
					}

					$from_email_domain = explode('@', $sendgrid_from)[1];
					if (in_array($from_email_domain, $allowed_domains)) {
						$validFromEmail = true;
					}

					if (!$validFromEmail) {
						$errors[$row_count][] = "{$lang['alerts_304']}";
					}
				}
				if ($email_to == '') {
					$errors[$row_count][] = "{$lang['alerts_197']}";
				}
				foreach (array('email-to', 'email-cc', 'email-bcc') as $email_field) {
					if (trim($alert[$email_field]) != '') {
						$error = $this->validateEmailFields(trim($alert[$email_field]), $email_field, $storedEmails[$email_field]);
						if (!empty($error)) {
							$errors[$row_count][] = $error;
						}
					}
				}

                $mail_send_configuration = json_decode($alert['sendgrid-mail-send-configuration'], true);
                if ($mail_send_configuration != null) {
                    // check group id
                    $group_id = $mail_send_configuration['unsubscribe-group-id'];
                    $validGroupId = false;
					if ((in_array($group_id, $valid_unsubscribe_group_ids) && gettype($group_id) == 'integer') || $group_id == null) {
						$validGroupId = true;
					}
                  
					if (!$validGroupId) {
						$errors[$row_count][] = "{$lang['alerts_375']}";
					}
                    // check categories
                    $categories = $mail_send_configuration['categories'];
                    $validCategories = true;
                    if (is_array($categories)) {
                        if ((count($categories) !== count(array_unique($categories))) || count($categories) > 10) {
                            $validCategories = false;  
                        }
                        foreach ($categories as $key => $category) {
                            if ($category == "") {
                                $validCategories = false;
                                break;
                            }
                            if (is_string($category)) {
                                if (strlen($category) > 255) {
                                    $validCategories = false;
                                    break;
                                }
                            } else {
                                $validCategories = false;
                                break;
                            }
                        }
                    } else if ($categories == null) {
                        $validCategories = true;
                    } else {
                        $validCategories = false;
                    }

                    if (!$validCategories) {
                        $errors[$row_count][] = "{$lang['alerts_377']}";
                    }

                    // check types
                    $validTypes = array('NULL', 'boolean');
                    if (!in_array(gettype($mail_send_configuration['bypass-list-management']), $validTypes)) {
                        $errors[$row_count][] = "{$lang['alerts_379']}";
                    }
                    if (!in_array(gettype($mail_send_configuration['bypass-spam-management']), $validTypes)) {
                        $errors[$row_count][] = "{$lang['alerts_380']}";
                    }
                    if (!in_array(gettype($mail_send_configuration['bypass-bounce-management']), $validTypes)) {
                        $errors[$row_count][] = "{$lang['alerts_381']}";
                    }
                    if (!in_array(gettype($mail_send_configuration['bypass-unsubscribe-management']), $validTypes)) {
                        $errors[$row_count][] = "{$lang['alerts_382']}";
                    }
                    if (!in_array(gettype($mail_send_configuration['sandbox-mode']), $validTypes)) {
                        $errors[$row_count][] = "{$lang['alerts_383']}";
                    }
                    if (!in_array(gettype($mail_send_configuration['click-tracking']), $validTypes)) {
                        $errors[$row_count][] = "{$lang['alerts_384']}";
                    }
                    if (!in_array(gettype($mail_send_configuration['open-tracking']), $validTypes)) {
                        $errors[$row_count][] = "{$lang['alerts_385']}";
                    }
                    if (!in_array(gettype($mail_send_configuration['subscription-tracking']), $validTypes)) {
                        $errors[$row_count][] = "{$lang['alerts_386']}";
                    }

                    // check bypass settings
                    $validBypassSettings = true;
                    if ($mail_send_configuration['bypass-list-management'] && 
                    (
                        $mail_send_configuration['bypass-spam-management'] ||
                        $mail_send_configuration['bypass-bounce-management'] ||
                        $mail_send_configuration['bypass-unsubscribe-management']
                    )) {
                        $validBypassSettings = false;
                    }

                    if (!$validBypassSettings) {
                        $errors[$row_count][] = "{$lang['alerts_378']}";
                    }
                }

                $valid_mail_send_configuration_keys = array( 'unsubscribe-group-id', 'categories', 
                'bypass-list-management', 'bypass-spam-management', 'bypass-bounce-management', 'bypass-unsubscribe-management',
                'sandbox-mode','click-tracking','open-tracking', 'subscription-tracking');

                // check for unexpected data being passed in
                $contains_invalid_configuration = false;
                foreach ($mail_send_configuration as $key=>$value) {
                    if (!in_array($key, $valid_mail_send_configuration_keys)) {
                        $contains_invalid_configuration = true;
                    }
                }
                if ($contains_invalid_configuration) {
                    $errors[$row_count][] = "{$lang['alerts_376']}";
                }
			}

            if (!empty($file_upload_fields)) {
                $error_fileupload_fields = $this->validateFileUploadField($file_upload_fields);
                if (!empty($error_fileupload_fields)) {
                    $errors[$row_count][] = $error_fileupload_fields;
                }
            }

            $_POST['form-name'] = $form_unique_name."-".$event_id;

            if ($alert['alert-send-how-many'] == 'ONCE') {
                if ($alert['repeat-for'] != '0' && $alert['repeat-for'] != '') {
                    $errors[$row_count][] = $lang['alerts_314'];
                }
            }

            if ($alert['send-on'] == 'TIME_LAG') {
                if (!empty($alert['send-on-field'])) {
                    $error = $this->validateDateTimeFields($alert['send-on-field']);
                    if (!empty($error)) {
                        $errors[$row_count][] = $error;
                    }
                }
            }
            // Assigning cron-send post values
            $_POST['cron-send-email-on'] = $alert['send-on'];
            $_POST['cron-send-email-on-next-day-type'] = $alert['send-on-next-day-type'];
            $_POST['cron-send-email-on-next-time'] = $alert['send-on-next-time'];
            $_POST['cron-send-email-on-time-lag-days'] = $alert['send-on-time-lag-days'];
            $_POST['cron-send-email-on-time-lag-hours'] = $alert['send-on-time-lag-hours'];
            $_POST['cron-send-email-on-time-lag-minutes'] = $alert['send-on-time-lag-minutes'];
            $_POST['cron-send-email-on-field-after'] = $alert['send-on-field-after'];
            $_POST['cron-send-email-on-field'] = $alert['send-on-field'];
            $_POST['cron-send-email-on-date'] = $alert['send-on-date'];

            // Assigning cron-repeat-for post values
            $_POST['cron-repeat-for'] = $alert['repeat-for'];
            $_POST['cron-repeat-for-units'] = $alert['repeat-for-units'];
            $_POST['cron-repeat-for-max'] = $alert['repeat-for-max'];

            $_POST['email-incomplete'] = str_replace(array('ANY', 'COMPLETE'), array('1', '0'), $_POST['saved-with-form-status']);
            $_POST['email-deleted'] = str_replace(array('Y', 'N'), array('1', '0'), $_POST['alert-deactivated']);

            $toLowercaseKeys = array('alert-trigger', 'ensure-logic-still-true', 'send-on', 'send-on-field-after', 'alert-send-how-many', 'every-time-type', 'prevent-piping-identifiers');
            foreach ($toLowercaseKeys as $key) {
                if (isset($_POST[$key])) {
                    $_POST[$key] = strtolower($_POST[$key]);
                }
            }
            if (isset($updateQueue)) {
                $_POST['cron-queue'] = $updateQueue;
            }

            if (empty($errors))
            {
                $this->saveAlert();
                ++$count;
            }
        }
        // Return count and array of errors
        return array($count, $errors);
    }

    // Return list of From Emails
    public function getFromEmails () {
        $fromEmails = array();
        foreach (User::getEmailAllProjectUsers(PROJECT_ID) as $thisEmail) {
            $fromEmails[] = $thisEmail;
        }
        if (SUPER_USER && !isset($fromEmails[$GLOBALS['user_email']])) {
            // If admin is not a user in the project, add their primary email to the drop-down
            $fromEmails[] = $GLOBALS['user_email'];
        }
        return $fromEmails;
    }

    // Validate send-on-field value
    public function validateDateTimeFields ($datetime_field) {
        extract($GLOBALS);
        $error = '';
        // Get all datetime/datetime_seconds fields and put in array
        $datetime_fields_pre = Form::getFieldDropdownOptions(true, false, false, false, array('date', 'date_ymd', 'date_mdy', 'date_dmy', 'datetime',
                                    'datetime_ymd', 'datetime_mdy', 'datetime_dmy', 'datetime_seconds_ymd', 'datetime_seconds_dmy', 'datetime_seconds_mdy'), false, false);
        $datetime_fields = array();
        foreach ($datetime_fields_pre as $this_field=>$this_label) {
            if ($longitudinal) {
                $this_form = $Proj->metadata[$this_field]['form_name'];
                foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
                    if (in_array($this_form, $these_forms)) {
                        if (!in_array("[$this_field]", $datetime_fields)) {
                            $datetime_fields[] = "[$this_field]";
                        }
                        $this_event_name = $Proj->getUniqueEventNames($this_event_id);
                        $datetime_fields[] = "[$this_event_name][$this_field]";
                    }
                }
            } else {
                $datetime_fields[] = "[$this_field]";
            }
        }
        if (!in_array($datetime_field, $datetime_fields) && $datetime_field != "[survey-date-completed:placeholder]") {
            $error = $lang['alerts_302'];
        }
        return $error;
    }

    // Validate email fields (email-to, email-cc, email-bcc) for Alerts email related settings at system-level
    public function validateEmailFields($emailValue, $field_label = 'email-to', $storedEmailList=array()) {
        global $lang;
        $error = '';
        $toEmails = $this->getEmailsList();
        $emails = explode(";", $emailValue);
        $prefix = "(<i>".$field_label."</i>) ";
        foreach ($emails as $email) {
            // Validate Emails only if csv value is different from DB value
            if (!in_array($email, $storedEmailList)) {
                // Ignore [survey-participant-email]
                if ($email == '[survey-participant-email]') continue;
                if (in_array($email, $toEmails['email_fields'])) {
                    if (!$GLOBALS['alerts_allow_email_variables'] && !SUPER_USER) {
                        $error = $lang['alerts_50'];
                        continue;
                    }
                } else if (!in_array($email, $toEmails['project_users'])) {
                    if (!$GLOBALS['alerts_allow_email_freeform'] && !SUPER_USER) {
                        $error = $lang['alerts_50'];
                        continue;
                    } else if (!isEmail($email)) {
                        $error = $lang['alerts_50'];
                        continue;
                    }
                }
            }
        }
        $error = (!empty($error)) ? $prefix.$error : '';
        return $error;
    }

    // Validate phone-number-to value
    public function validatePhoneFields($phone_number_to, $storedPhones) {
        global $lang;
        $phone_number_to = str_replace(array(",", "(", ")", "-", " ", "+"), array(";","","","","",""), $phone_number_to);
        $phone_number_to = trim(trim(implode(";", array_merge(explode(";", $phone_number_to), explode(";", $phone_number_to) ))),";");
        $error = '';
        $toPhones = $this->getPhoneFieldsList();
        $phones = explode(";", $phone_number_to);
        foreach ($phones as $phone) {
            // Validate Phone only if csv value is different from DB value
            if (!in_array($phone, $storedPhones)) {
                if (in_array($phone, $toPhones)) {
                    if (!$GLOBALS['alerts_allow_phone_variables'] && !SUPER_USER) {
                        $error = $lang['alerts_301'];
                        continue;
                    }
                } else if (!is_numeric($phone)) {
                    $error = $lang['alerts_301'];
                    continue;
                } else if (!$GLOBALS['alerts_allow_phone_freeform'] && !SUPER_USER) {
                    $error = $lang['alerts_301'];
                    continue;
                }
            }
        }
        return $error;
    }

    // Get Phone fields list
    public function getPhoneFieldsList() {
        extract($GLOBALS);
        // Get user phone numbers
        $userPhones = array();
        foreach (User::getPhoneAllProjectUsers(PROJECT_ID, false, true) as $thisPhone=>$thisFirstLastName) {
            if (isPhoneUS($thisPhone) && substr($thisPhone, 0, 1) != "1") $thisPhone = "1".$thisPhone;
            $userPhones[] = $thisPhone;
        }
        if (SUPER_USER && !isset($userPhones[$GLOBALS['user_phone']]) && $GLOBALS['user_phone'] != '') {
            $thisPhone = preg_replace("/[^0-9]/", "", $GLOBALS['user_phone']);
            if (isPhoneUS($thisPhone) && substr($thisPhone, 0, 1) != "1") $thisPhone = "1".$thisPhone;
            // If admin is not a user in the project, add their primary email to the drop-down
            $userPhones[] = $thisPhone;
        }
        if (SUPER_USER && !isset($userPhones[$GLOBALS['user_phone_sms']]) && $GLOBALS['user_phone_sms'] != '') {
            $thisPhone = preg_replace("/[^0-9]/", "", $GLOBALS['user_phone_sms']);
            if (isPhoneUS($thisPhone) && substr($thisPhone, 0, 1) != "1") $thisPhone = "1".$thisPhone;
            // If admin is not a user in the project, add their primary email to the drop-down
            $userPhones[] = $thisPhone;
        }
        // Set the To phone numbers as the projects users + survey participant
        $toPhones = array();
        foreach ($userPhones as $thisUserPhone) {
            $toPhones[] = $thisUserPhone;
        }
        // Add email-validated fields to multi-select fields
        if ($alerts_allow_phone_variables || SUPER_USER)
        {
            // Gather all phone validation types + integer validation
            $valTypes = getValTypes();
            $valTypesPhoneInteger = array('int');
            foreach ($valTypes as $valName=>$valType) {
                if ($valType['data_type'] == 'phone') {
                    $valTypesPhoneInteger[] = $valName;
                }
            }
            // Get all phone and integer fields
            $phoneFieldsLabels = Form::getFieldDropdownOptions(false, false, false, false, $valTypesPhoneInteger);
            if (!empty($phoneFieldsLabels)) {
                foreach ($phoneFieldsLabels as $formLabel=>$thesePhoneFields) {
                    if (!is_array($thesePhoneFields)) continue;
                    foreach ($thesePhoneFields as $thisVar=>$thisOptionLabel) {
                        list ($thisVarLabel, $thisOptionLabel) = explode(" ", $thisOptionLabel, 2);
                        if ($longitudinal) {
                            $toPhones[] = "[$thisVar]";
                            foreach ($Proj->eventsForms as $thisEventId=>$theseForms) {
                                $thisEventName = $Proj->getUniqueEventNames($thisEventId);
                                $thisForm = $Proj->metadata[$thisVar]['form_name'];
                                if (in_array($thisForm, $theseForms)) {
                                    $toPhones[] = "[$thisEventName][$thisVar]";
                                }
                            }
                        } else {
                            $toPhones[] = "[$thisVar]";
                        }
                    }
                }
            }
        }
        return $toPhones;
    }

    // Validate file-upload-field for valid file fields
    public function validateFileUploadField($fields) {
        global $Proj, $lang;
        $error = '';
        $fieldUploadFieldOptions = $fieldUploadFieldOptionsEvents = array();
        foreach ($Proj->metadata as $this_field=>$attr1) {
            $fieldUploadFieldOptions[] = "[$this_field]";
            if ($Proj->longitudinal) {
                foreach ($Proj->eventsForms as $thisEventId=>$theseForms) {
                    $thisEventName = $Proj->getUniqueEventNames($thisEventId);
                    $thisForm = $Proj->metadata[$this_field]['form_name'];
                    if (in_array($thisForm, $theseForms)) {
                        $fieldUploadFieldOptionsEvents[] = "[$thisEventName][$this_field]";
                    }
                }
            }
        }
        $fieldUploadFieldOptions = $fieldUploadFieldOptions + $fieldUploadFieldOptionsEvents;
        foreach (explode(";", $fields) as $field) {
            if (!in_array($field, $fieldUploadFieldOptions)) {
                $error = $lang['alerts_310'];
                continue;
            }
        }
        return $error;
    }

    // Check the order of the alerts for alert_order to make sure they're not out of order
    public function checkOrder($alerts)
    {
        // Store the sum of the alert_order's and count of how many there are
        $sum   = 0;
        $count = 0;
        // Loop through existing resources
        foreach ($alerts as $alert_id=>$attr)
        {
            // Ignore pre-defined rules
            if (!is_numeric($alert_id)) continue;
            // Add to sum
            $sum += $attr['alert_order'] * 1;
            // Increment count
            $count++;
        }
        // Now perform check (use simple math method)
        if ($count * ($count + 1) / 2 != $sum)
        {
            // Out of order, so reorder
            $this->reorder($alerts);
        }
    }

    // Reset the order of the alerts for alert_order in the table
    public function reorder($alerts)
    {
        // Initial value
        $order = 1;
        // Loop through existing resources
        foreach (array_keys($alerts) as $alert_id)
        {
            // Ignore pre-defined rules
            if (!is_numeric($alert_id)) continue;
            $projectId = $this->getAlertProjectId($alert_id);
            // Save to table
            $sql = "UPDATE redcap_alerts SET alert_order = $order WHERE project_id = " . $projectId . " AND alert_id = $alert_id";
            $q = db_query($sql);
            // Increment the order
            $order++;
        }
    }

    // Get the project_id from the alert log id
    public function getAlertProjectId($alertId)
    {
        if (!isinteger($alertId)) return null;
        $sql = "SELECT project_id FROM redcap_alerts WHERE alert_id = $alertId";
        $q = db_query($sql);
        return db_result($q, 0);
    }

    // Get Help text for Download Alerts
    public function getDownloadKeysHelpText() {
        global $lang;
        $br = RCView::br();
        $tbl_attr = array('cellspacing'=>2, 'cellpadding'=>2);
        $td_attr = array('style'=>'border:1px solid #ccc;');
        $lp = $lang['leftparen'];
        $rp = $lang['rightparen'];

        $cells = RCView::td(array('style'=>'border:1px solid #ccc;font-weight:bold;'), $lang['data_import_tool_99']).
                 RCView::td(array('style'=>'border:1px solid #ccc;font-weight:bold;'), $lang['global_20']);
        $header_row = RCView::tr(array(), $cells);

        // alert-trigger description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'SUBMIT').
                 RCView::td($td_attr, $lang['alerts_134']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'SUBMIT-LOGIC').
                 RCView::td($td_attr, $lang['alerts_316']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'LOGIC').
                 RCView::td($td_attr, $lang['alerts_317']);
        $rows .= RCView::tr(array(), $cells);
        $preview_alert_trigger = RCView::table($tbl_attr, $rows);

        $output = RCView::b($lang['api_docs_227']) . $br .
                  RCView::b("alert-trigger: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['alerts_133'] . $rp) .
                  $preview_alert_trigger . $br;

        // saved-with-form-status description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'ANY').
                 RCView::td($td_attr, $lang['alerts_138']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'COMPLETE').
                 RCView::td($td_attr, $lang['alerts_139']);
        $rows .= RCView::tr(array(), $cells);
        $preview_saved_with_form_status = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("saved-with-form-status: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['alerts_137'] . $rp) .
                  $preview_saved_with_form_status . $br;

        // ensure-logic-still-true description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'Y').
                 RCView::td($td_attr, $lang['design_100']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'N').
                 RCView::td($td_attr, $lang['design_99']);
        $rows .= RCView::tr(array(), $cells);
        $preview_ensure_logic = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("ensure-logic-still-true: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['alerts_30'] . $rp) .
                  $preview_ensure_logic . $br;

        // do-not-clear-recurrences description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'Y').
                 RCView::td($td_attr, $lang['design_100']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'N').
                 RCView::td($td_attr, $lang['design_99']);
        $rows .= RCView::tr(array(), $cells);
        $preview_ensure_logic = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("do-not-clear-recurrences: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['alerts_412'] . $rp) .
                  $preview_ensure_logic . $br;

        // alert-stop-type description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'RECORD').
                 RCView::td($td_attr, $lang['alerts_216'] . " " . $lp . $lang['alerts_228'] . $rp);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'RECORD_INSTRUMENT').
                 RCView::td($td_attr, $lang['alerts_222']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'RECORD_EVENT_INSTRUMENT').
                 RCView::td($td_attr, $lang['alerts_224']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'RECORD_EVENT').
                 RCView::td($td_attr, $lang['alerts_218']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'RECORD_EVENT_INSTRUMENT_INSTANCE').
                 RCView::td($td_attr, $lang['alerts_218']." ".$lang['alerts_223']);
        $rows .= RCView::tr(array(), $cells);
        $preview_alert_stop_type = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("alert-stop-type: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['alerts_215'] . $rp) .
                  $preview_alert_stop_type . $br;

        // send-on description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'NOW').
                 RCView::td($td_attr, $lang['global_1540']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'NEXT_OCCURRENCE').
                 RCView::td($td_attr, $lang['survey_423']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'TIME_LAG').
                 RCView::td($td_attr, $lang['alerts_239']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'DATE').
                 RCView::td($td_attr, $lang['survey_429']);
        $rows .= RCView::tr(array(), $cells);
        $preview_send_email_on = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("send-on: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['alerts_146'] . $rp) .
                  $preview_send_email_on . $br;

        // send-on-next-day-type description
        $rows = $header_row;

        // Add days of the week + work day + weekend day in list options
        $daysOfWeekDD = SurveyScheduler::daysofWeekOptions();
        unset($daysOfWeekDD['']);

        foreach ($daysOfWeekDD as $value => $description) {
            $cells = RCView::td($td_attr, $value).
                     RCView::td($td_attr, $description);
            $rows .= RCView::tr(array(), $cells);
        }
        $preview_next_day_type = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("send-on-next-day-type: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['survey_423'] . $rp) .
                  $preview_next_day_type . $br;

        // alert-send-how-many description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'ONCE').
                 RCView::td($td_attr, $lang['alerts_61']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'EVERY').
                 RCView::td($td_attr, $lang['alerts_225']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'SCHEDULE').
                 RCView::td($td_attr, $lang['alerts_232']);
        $rows .= RCView::tr(array(), $cells);

        $preview_send_how_many = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("alert-send-how-many: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['alerts_148'] . $rp) .
                  $preview_send_how_many . $br;

        // every-time-type description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'EVERY').
                 RCView::td($td_attr, $lang['alerts_227']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'EVERY-CHANGE').
                 RCView::td($td_attr, $lang['alerts_230']." ".$lang['alerts_231']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'EVERY-CHANGE-CALCS').
                 RCView::td($td_attr, $lang['alerts_230']);
        $rows .= RCView::tr(array(), $cells);

        $preview_repeat_unit = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("every-time-type: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['alerts_225'] . $rp) .
                  $preview_repeat_unit . $br;

        // repeat-for-units description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'MINUTES').
                 RCView::td($td_attr, $lang['survey_428']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'HOURS').
                 RCView::td($td_attr, $lang['survey_427']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'DAYS').
                 RCView::td($td_attr, $lang['survey_426']);
        $rows .= RCView::tr(array(), $cells);

        $preview_repeat_unit = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("repeat-for-units: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['alerts_298'] . $rp) .
                  $preview_repeat_unit . $br;

        // alert-type description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'EMAIL').
                 RCView::td($td_attr, $lang['global_33']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'SMS').
                 RCView::td($td_attr, $lang['alerts_201']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'VOICE_CALL').
                 RCView::td($td_attr, $lang['alerts_202']);
        $rows .= RCView::tr(array(), $cells);
		
		$cells = RCView::td($td_attr, 'SENDGRID_TEMPLATE').
		RCView::td($td_attr, $lang['alerts_337']);
		$rows .= RCView::tr(array(), $cells);

        $preview_alert_type = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("alert-type: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp. $lang['alerts_199'] .$rp) .
                  $preview_alert_type . $br;

        // prevent-piping-identifiers description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'Y').
                 RCView::td($td_attr, $lang['design_100']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'N').
                 RCView::td($td_attr, $lang['design_99']);
        $rows .= RCView::tr(array(), $cells);
        $preview_ensure_logic = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("prevent-piping-identifiers: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['alerts_12'] . $rp) .
                  $preview_ensure_logic . $br;

        // alert-deactivated description
        $rows = $header_row;
        $cells = RCView::td($td_attr, 'Y').
                 RCView::td($td_attr, $lang['design_100']);
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'N').
                 RCView::td($td_attr, $lang['design_99']);
        $rows .= RCView::tr(array(), $cells);
        $preview_ensure_logic = RCView::table($tbl_attr, $rows);

        $output .= RCView::b("alert-deactivated: ") .
                  RCView::span(array('style'=>'color:#555;font-size:11px;'),$lp . $lang['alerts_91'] . $rp) .
                  $preview_ensure_logic;

        return $output;
    }

    // Get Help text for Upload Alerts
    public function getUploadKeysHelpText() {
        global $lang;
        $br = RCView::br();
        $tbl_attr = array('cellspacing'=>2, 'cellpadding'=>2);
        $td_attr = array('style'=>'border:1px solid #ccc;');
        $span_arr = array('style'=>'color:#555;font-size:11px;font-weight:normal');

        $cells = RCView::td(array('style'=>'border:1px solid #ccc;font-weight:bold; width:30%;'), $lang['global_40']).
                 RCView::td(array('style'=>'border:1px solid #ccc;font-weight:bold; width:50%;'), $lang['alerts_299']).
                 RCView::td(array('style'=>'border:1px solid #ccc;font-weight:bold; width:80%;'), $lang['alerts_300']);
        $header_row = RCView::tr(array(), $cells);

        // alert-trigger description
        $rows = $header_row;

        $cells = RCView::td($td_attr, 'alert-trigger').
                 RCView::td($td_attr, "'SUBMIT', 'SUBMIT-LOGIC', 'LOGIC'").
                 RCView::td($td_attr, 'SUBMIT');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'saved-with-form-status').
                 RCView::td($td_attr, "'ANY', 'COMPLETE'").
                 RCView::td($td_attr, 'ANY');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'ensure-logic-still-true').
                 RCView::td($td_attr, "'Y', 'N'").
                 RCView::td($td_attr, 'N');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'do-not-clear-recurrences').
                 RCView::td($td_attr, "'Y', 'N'").
                 RCView::td($td_attr, 'N');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'alert-type').
                 RCView::td($td_attr, "'EMAIL', 'SMS', 'VOICE_CALL', 'SENDGRID_TEMPLATE'").
                 RCView::td($td_attr, 'EMAIL');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'alert-stop-type').
                 RCView::td($td_attr, "'RECORD', 'RECORD_INSTRUMENT', 'RECORD_EVENT_INSTRUMENT',".$br." 'RECORD_EVENT', 'RECORD_EVENT_INSTRUMENT_INSTANCE'").
                 RCView::td($td_attr, 'RECORD');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'send-on').
                 RCView::td($td_attr, "'NOW', 'NEXT_OCCURRENCE', 'TIME_LAG', 'DATE'").
                 RCView::td($td_attr, 'NOW');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'send-on-field-after'.$br.$lang['leftparen'].RCView::span($span_arr,$lang['alerts_140'].' send-on=\'time_lag\'').$lang['rightparen']).
                 RCView::td($td_attr, "'AFTER', 'BEFORE'").
                 RCView::td($td_attr, 'AFTER');
        $rows .= RCView::tr(array(), $cells);

        // Add days of the week + work day + weekend day in list options
        $daysOfWeekDD = SurveyScheduler::daysofWeekOptions();
        unset($daysOfWeekDD['']);
        $possible_values = "'".implode("', '", array_keys($daysOfWeekDD))."'";

        $cells = RCView::td($td_attr, 'send-on-next-day-type'.$br.$lang['leftparen'].RCView::span($span_arr,$lang['alerts_140'].' send-on=\'next_occurrence\'').$lang['rightparen']).
                 RCView::td($td_attr, $possible_values).
                 RCView::td($td_attr, 'DAYS');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'alert-send-how-many').
                 RCView::td($td_attr, "'ONCE', 'EVERY', 'SCHEDULE'").
                 RCView::td($td_attr, 'ONCE');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'every-time-type'.$br.$lang['leftparen'].RCView::span($span_arr,$lang['alerts_140'].' alert-send-how-many=\'every\'').$lang['rightparen']).
                     RCView::td($td_attr, "'EVERY', 'EVERY-CHANGE', 'EVERY-CHANGE-CALCS'").
                 RCView::td($td_attr, 'EVERY');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'repeat-for-units'.$br.$lang['leftparen'].RCView::span($span_arr,$lang['alerts_140'].' alert-send-how-many=\'schedule\'').$lang['rightparen']).
                 RCView::td($td_attr, "'MINUTES', 'HOURS', 'DAYS'").
                 RCView::td($td_attr, 'DAYS');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'prevent-piping-identifiers').
                 RCView::td($td_attr, "'Y', 'N'").
                 RCView::td($td_attr, 'Y');
        $rows .= RCView::tr(array(), $cells);

        $cells = RCView::td($td_attr, 'alert-deactivated').
                 RCView::td($td_attr, "'Y', 'N'").
                 RCView::td($td_attr, 'N');
        $rows .= RCView::tr(array(), $cells);

        $preview_alert_trigger = RCView::table($tbl_attr, $rows);

        $output = $preview_alert_trigger . $br;
        return $output;
    }

    // Alerts - Upload/Download CSV Help Page
    public function uploadDownloadHelp() {
        // Disable authentication so this page can be used as general documentation
        define("NOAUTH", true);
        global $lang;
        // Add popup for help - Upload or download Alerts CSV
        $br = RCView::br();
        $helpText = RCView::div(array('style'=>'font-weight:bold;color:#A00000;font-size:15px;'), $lang['alerts_278']) .
            RCView::div(array('style'=>'margin-top:5px;'), $lang['alerts_289']) .
            RCView::div(array('class'=>'attributes-list'), implode(", ", $this->getAlertsCSVAttributes())) .
            $this->getDownloadKeysHelpText() .
            RCView::div(array('style'=>'margin-top:35px;font-weight:bold;color: #A00000;font-size:15px;'), $lang['alerts_277']) .
            RCView::div(array('style'=>'margin-top:5px;'), $lang['alerts_294']. " " . $lang['alerts_295']) . $br .
            RCView::div(array('style'=>'margin-top:5px;'), $lang['alerts_297']) .
            RCView::div(array('style'=>'margin-top:5px; font-weight:bold;'), $lang['alerts_300'].": ".RCView::span(array('style'=>'color:#555;font-size:11px;font-weight:normal;'),$lang['alerts_296'])) . $br .
            $this->getUploadKeysHelpText() ;
        print $helpText;
    }

    // Download Notification Logs - CSV
    public function downloadLogs() {
        global $lang, $Proj;

        // Open connection to create file in memory and write to it
        $fp = fopen('php://memory', "x+");
        // Set CSV header
        $header = array($lang['alerts_21'], $lang['survey_316'], str_replace(":", "", $lang['alerts_311']), $lang['global_49'], $lang['global_10'],
                        str_replace(":", "", $lang['alerts_199']), str_replace(":", "", $lang['alerts_154']),
                        $lang['alerts_26'], str_replace(":", "", $lang['alerts_158']), str_replace(":", "", $lang['alerts_159']), $lang['alerts_23'], $lang['messaging_110']);

        if ($_GET['download_all'] == 1) {
            $_GET['filterBeginTime']=$_GET['filterEndTime']=$_GET['filterRecord']=$_GET['filterAlert']='';
        }
        list ($notificationLog, $displayed_records) = $this->getNotificationLog();

        fputcsv($fp, $header, User::getCsvDelimiter(), '"', '');
        foreach ($notificationLog as $row) {
            $wasSent = ($row['was_sent'] == '1') ? $lang['design_100'] : $lang['design_99'];
            $sent_time = DateTimeRC::format_ts_from_ymd($row['send_time']);
            $email_to = $row['email_to'];
            $email_cc = $row['email_cc'];
            $email_bcc = $row['email_bcc'];
            $alert_sent_log_id = $row['alert_sent_log_id'];
            $aq_id = $row['aq_id'];

            if (is_numeric($alert_sent_log_id)) {
                $sql = "SELECT a.alert_id, s.record, s.event_id, s.instrument, s.instance, l.*
                        FROM redcap_alerts a, redcap_alerts_sent s, redcap_alerts_sent_log l 
                        WHERE a.project_id = ".PROJECT_ID." AND a.alert_id = s.alert_id AND s.alert_sent_id = l.alert_sent_id
                              AND l.alert_sent_log_id = ".checkNull($alert_sent_log_id);
                $q = db_query($sql);
                $row = db_fetch_assoc($q);
                $alert_id = $row['alert_id'];
                $index = $this->getKeyIdFromAlertId(PROJECT_ID, $alert_id);
                $record = $row['record'];
                $form_name_event = $row['event_id'];
                $form_name = $row['instrument'];
                $instance = $row['instance'];
                $email_from = $row['email_from'];
                $email_to = $row['email_to'];
                $email_cc = $row['email_cc'];
                $email_bcc = $row['email_bcc'];
                $email_subject = $row['subject'];
                $alert_message = $row['message'];
                $alert_type = $row['alert_type'];
                $phone_number_to = $row['phone_number_to'];
            } elseif (is_numeric($aq_id)) {
                $sql = "SELECT a.alert_id, a.alert_type, a.phone_number_to, r.record, r.event_id, r.instrument, r.instance
                        FROM redcap_alerts a, redcap_alerts_recurrence r
                        WHERE a.project_id = " . PROJECT_ID . " AND a.alert_id = r.alert_id AND r.aq_id = ".checkNull($aq_id);
                $q = db_query($sql);
                $row = db_fetch_assoc($q);
                $alert_id = $row['alert_id'];
                $index = $this->getKeyIdFromAlertId(PROJECT_ID, $alert_id);
                $record = $row['record'];
                $form_name_event = $row['event_id'];
                $form_name = $row['instrument'];
                $instance = $row['instance'];
                $email_from = isset($row['email_from']) ? $row['email_from'] : "";
                $email_subject = $this->getAlertSetting('email-subject')[$index];
                $alert_message = $this->getAlertSetting('alert-message')[$index];
                $alert_type = $row['alert_type'];
                $phone_number_to = $row['phone_number_to'];
            } else {
                $index = (int)$_POST['index_modal_record_preview'];
                $alert_id = $this->getAlertIdFromKeyId(PROJECT_ID, $index);
                $record = $_REQUEST['preview_record_id'];
                $form_name_event = $this->getAlertSetting('form-name-event')[$index];
                $form_name = $this->getAlertSetting('form-name')[$index];
                $email_from = $this->getAlertSetting('email-from')[$index];
                $email_subject = $this->getAlertSetting('email-subject')[$index];
                $alert_message = $this->getAlertSetting('alert-message')[$index];
                $alert_type = $this->getAlertSetting('alert-type')[$index];
                $phone_number_to = $this->getAlertSetting('phone-number-to')[$index];
                $instance = 1;
            }

            $phone_number_tos = '';
            if ($alert_type == "SMS" || $alert_type == "VOICE_CALL") {
                $email_to = $email_cc = $email_bcc = '';
                $phone_number_tos = array();
                foreach (explode(";", $phone_number_to) as $this_phone_number) {
                    $this_phone_number = trim($this_phone_number);
                    if ($this_phone_number == '') continue;
                    $firstCharacter = substr($this_phone_number, 0, 1);
                    if (is_numeric($firstCharacter)) {
                        $this_phone_number = formatPhone($this_phone_number);
                    }
                    $phone_number_tos[] = $this_phone_number;
                }
                $phone_number_tos = implode("; ", $phone_number_tos);
            }
            $line = array($sent_time,
                        $wasSent,
                        self::ALERT_UNIQUE_ID_PREFIX.$alert_id,
                        $record,
                        $Proj->eventInfo[$form_name_event]['name_ext'],
                        $alert_type,
                        $email_from,
                        ($alert_type == "SMS" || $alert_type == "VOICE_CALL") ? $phone_number_tos : $email_to,
                        $email_cc,
                        $email_bcc,
                        $email_subject,
                        $alert_message);
            // Write this line to CSV file
            fputcsv($fp, $line, User::getCsvDelimiter(), '"', '');
        }
        // Open file for reading and output to user
        fseek($fp, 0);
        $csv_file = addBOMtoUTF8(stream_get_contents($fp));
        $project_title = REDCap::getProjectTitle();
        $filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($project_title, ENT_QUOTES)))), 0, 30)
                    ."_NotificationLogs_".date("Y-m-d").".csv";

        // Output to file
        header('Pragma: anytextexeptno-cache', true);
        header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=$filename");
        print addBOMtoUTF8($csv_file);

        Logging::logEvent("","redcap_alerts_sent_log","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID,"Export entire Notification log");
        exit;
    }

	public function getSendgridData() {
		extract($GLOBALS);

		$sendgrid_project_api_key = SendGridRC::getAPIKeyByPid(PROJECT_ID);
		$sendgridVerifiedSenders = SendGridRC::getVerifiedSenders($sendgrid_project_api_key, PROJECT_ID);
		$sendgrid_authenticated_domains = SendGridRC::getAuthenticatedDomains($sendgrid_project_api_key, PROJECT_ID);
		
		$sendgrid_alerts_email_domain_allowlist = array();
		foreach ($sendgrid_authenticated_domains as $domain) {
			array_push($sendgrid_alerts_email_domain_allowlist, $domain['domain']);
		}

		$sendgrid_alerts_email_allowlist = array();
		foreach ($sendgridVerifiedSenders as $sender) {
			array_push($sendgrid_alerts_email_allowlist, $sender['from_email']);
		}

		$sendgridTemplates = SendGridRC::getDynamicTemplates($sendgrid_project_api_key, PROJECT_ID);
		$sendgridTemplatesSelectorData = array();
		foreach ($sendgridTemplates as $template) {
			$sendgridTemplatesSelectorData[$template['id']] =  $template['name'] . ' ('. $template['id'] . ')';
		}

		$fromEmails = array();
		foreach (User::getEmailAllProjectUsers(PROJECT_ID) as $thisEmail) {
			$fromEmails[$thisEmail] = $thisEmail;
		}
		if (SUPER_USER && !isset($fromEmails[$GLOBALS['user_email']])) {
			// If admin is not a user in the project, add their primary email to the drop-down
			$fromEmails[$GLOBALS['user_email']] = $GLOBALS['user_email'] . " " . $lang['leftparen'] . $GLOBALS['user_firstname'] . " " . $GLOBALS['user_lastname'] . $lang['rightparen'];
		}

		$sendgridFromEmails = array();
		foreach ($fromEmails as $email) {
			if (in_array($email, $sendgrid_alerts_email_allowlist)) {
				array_push($sendgridFromEmails, $email);
				continue;
			}
			$from_email_domain = explode('@', $email)[1];
			if (in_array($from_email_domain, $sendgrid_alerts_email_domain_allowlist)) {
				array_push($sendgridFromEmails, $email);
				continue;
			}
		}
		$sendgridFromEmails = array_unique(array_merge($sendgridFromEmails, $sendgrid_alerts_email_allowlist));

        $sendgrid_unsubscribe_groups = SendGridRC::getUnsubscribeGroups($sendgrid_project_api_key, PROJECT_ID);
		$sendgridUnsubscribeGroupsSelectorData = array();
		foreach ($sendgrid_unsubscribe_groups as $group) {
			$sendgridUnsubscribeGroupsSelectorData[$group['id']] =  $group['name'] . ' ('. $group['id'] . ')';
		}
		echo json_encode([
			'sendgrid_alerts_email_domain_allowlist' => $sendgrid_alerts_email_domain_allowlist,
			'sendgrid_alerts_email_allowlist' => $sendgrid_alerts_email_allowlist,
			'sendgrid_template_ids' => $sendgridTemplatesSelectorData,
			'sendgrid_from_emails' => $sendgridFromEmails,
            'sendgrid_unsubscribe_groups' => $sendgridUnsubscribeGroupsSelectorData
		]);
		exit;
	}

    // Return boolean if any active alerts in a project have an email body with identifier fields in it
    public function anyAlertsContainIdentifierFields($project_id)
    {
        $settings = $this->getAlertSettings($project_id);
        foreach ($settings as $attr) {
            if (!$attr['email_deleted'] && !$attr['prevent_piping_identifiers'] && containsIdentifierFields($attr['alert_message'], $project_id)) {
                return true;
            }
        }
        return false;
    }
}
