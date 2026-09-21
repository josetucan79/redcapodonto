<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\Promis;
use Vanderbilt\REDCap\Classes\ProjectDesigner;
use RCView;

class Task
{
    const AMSLERGRID = '.AmslerGrid';
    const AUDIO = '.Audio';
    const DBHLTONEAUDIOMETRY = '.DbhlToneAudiometry';
    const FITNESSCHECK = '.FitnessCheck';
    /** Custom task. Display all fields as a form */
    const FORM = '.Form';
    const HOLEPEG = '.HolePeg';
    /** PROMIS computer adaptive test from REDCap Shared Library */
    const PROMIS = '.PROMIS';
    const PSAT = '.PSAT';
    /** Custom task. Display all fields as individual qustions */
    const QUESTIONNAIRE = '.Questionnaire';
    const RANGEOFMOTION = '.RangeOfMotion';
    const REACTIONTIME = '.ReactionTime';
    const SHORTWALK = '.ShortWalk';
    const SPATIALSPANMEMORY = '.SpatialSpanMemory';
    const SPEECHINNOISE = '.SpeechInNoise';
    const SPEECHRECOGNITION = '.SpeechRecognition';
    const STROOP = '.Stroop';
    const TIMEDWALK = '.TimedWalk';
    const TONEAUDIOMETRY = '.ToneAudiometry';
    const TOWEROFHANOI = '.TowerOfHanoi';
    const TRAILMAKING = '.TrailMaking';
    const TWOFINGERTAPPINGINTERVAL = '.TwoFingerTappingInterval';
    /** Custom active task for Alex Gelbard */
    const VUMCAUDIORECORDING = '.VumcAudioRecording';
    const VUMCCONTRACTIONTIMER = '.VumcContractionTimer';

    const TYPE_CONTRACTIONTIMER = '.ContractionTimer';
    const TYPE_DATELINE = '.DateLine';
    const TYPE_PERCENTCOMPLETE = '.Percent';

    public static $typeEnum = [
        self::TYPE_CONTRACTIONTIMER,
        self::TYPE_DATELINE,
        self::TYPE_PERCENTCOMPLETE
    ];

    /** Task Schedule vars */

    const ENDS_NEVER = '.Never';
    const ENDS_AFTERCOUNT = '.AfterCountOccurrences';
    const ENDS_AFTERDAYS = '.AfterNDays';
    const ENDS_ONDATE = '.OnDate';

    const FREQ_DAILY = '.Daily';
    const FREQ_MONTHLY = '.Monthly';
    const FREQ_WEEKLY = '.Weekly';

    const TYPE_FIXED = '.Fixed';
    const TYPE_INFINITE = '.Infinite';
    const TYPE_ONETIME = '.OneTime';
    const TYPE_REPEATING = '.Repeating';

    const RELATIVETO_JOINDATE = '.JoinDate';
    const RELATIVETO_ZERODATE = '.ZeroDate';

    public static $requiredAnnotations = [
        Annotation::TASK_UUID,
        Annotation::TASK_STARTDATE,
        Annotation::TASK_ENDDATE,
        Annotation::TASK_SCHEDULEDATE,
        Annotation::TASK_STATUS,
        Annotation::TASK_SUPPLEMENTALDATA,
        Annotation::TASK_SERIALIZEDRESULT
    ];
     /**
     * Returns human readable string for the given format
     *
     * @param string $format
     * @return string
     */
    public static function toString($format)
    {
        switch ($format) {
            case self::FORM:
                $retVal = RCView::tt('global_54');
                break;
            case self::QUESTIONNAIRE:
                $retVal = RCView::tt('multilang_741');
                break;
            case self::PROMIS:
                $retVal = RCView::tt('mycap_mobile_app_892');
                break;
            default:
                $retVal = RCView::tt('mycap_mobile_app_893');
                break;
        }
        return $retVal;
    }

    /**
     * Get all fields of form having specific data type
     *
     * @param string $field_type
     * @param string $form
     * @return array
     */
    public static function getDataTypeBasedFieldsList($field_type, $form)
    {
        global $Proj, $lang;
        $fields[''] = '-- '.$lang['random_02'].' --';

        switch ($field_type) {
            case 'date':
                $fields_pre = \Form::getFieldDropdownOptions(true, false, false, false, array('date', 'date_ymd', 'date_mdy', 'date_dmy'), false, false);
                break;

            case 'time':
                $fields_pre = \Form::getFieldDropdownOptions(true, false, false, false, array('time', 'time_hh_mm_ss'), false, false);
                break;

            case 'numeric':
                $fields_pre = \Form::getFieldDropdownOptions(true, false, false, false, array('int', 'float'), false, false);
                break;
        }

        foreach ($fields_pre as $this_field=>$this_label) {
            $this_form_label = strip_tags($lang['alerts_243']." \"".$Proj->forms[$Proj->metadata[$this_field]['form_name']]['menu']."\"");
            $this_form = $Proj->metadata[$this_field]['form_name'];
            $this_label = preg_replace('/'.$this_field.'/', "[$this_field]", $this_label, 1);
            list ($this_label2, $this_label1) = explode(" ", $this_label, 2);
            if ($this_form == $form) {
                /*if ($Proj->longitudinal) {
                    foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
                        if (in_array($this_form, $these_forms)) {
                            if (!isset($datetime_fields[$this_form_label]["[$this_field]"])) {
                                $fields["[$this_field]"] = "$this_label1 " . $lang['alerts_237'] . " - $this_label2";
                            }
                            $this_event_name = $Proj->getUniqueEventNames($this_event_id);
                            $fields["[$this_event_name][$this_field]"] = "$this_label1 (".$Proj->eventInfo[$this_event_id]['name_ext'].") - $this_label2";
                        }
                    }
                } else {
                    $fields["[$this_field]"] = "$this_label1 $this_label2";
                }*/
                $fields["[$this_field]"] = "$this_label1 $this_label2";
            }
        }
        return $fields;
    }

    /**
     * Get days listing of week
     *
     * @return array
     */
    public static function getDaysOfWeekList() {
        global $lang;
        return 	array("1"=>$lang['global_99'], "2"=>$lang['global_100'], "3"=>$lang['global_101'],
            "4"=>$lang['global_102'], "5"=>$lang['global_103'], "6"=>$lang['global_104'],
            "7"=>$lang['global_105']);
    }

    /**
     * Display friendly string of task schedule description
     *
     * @param int $taskId
     * @return string
     */
    public static function displayTaskSchedule($taskId, $projectId = null)
    {
        if (is_null($projectId)) {
            global $Proj;
        } else {
            $Proj = new \Project($projectId);
        }
        $retVal = "";
        $schedules = self::getTaskSchedules($taskId, '', $projectId);

        if ($Proj->longitudinal) {
            if (!empty($schedules)) {
                $total = count($schedules);
                $i = 0;
                foreach ($schedules as $eventId => $schedule) {
                    $i++;
                    $scheduleStr = self::getDetailedSchedulesForEvent($schedule);
                    $retVal .= '<span style="color:#800000;font-size:11px; font-weight: bold;">'.$Proj->eventInfo[$eventId]['name_ext'].'</span><br><i>'.$scheduleStr.'</i>';
                    if ($i != $total)   $retVal .= '<hr style="border-bottom:1px dashed #aaa; margin-top:1%; margin-bottom:1%; width: 100%;" />';
                }
            }
        } else {
            $retVal = self::getDetailedSchedulesForEvent($schedules[$Proj->firstEventId]??[]);
        }

        return $retVal;
    }

    /**
     * Returns list of all mycap task settings
     *
     * @param integer $projectId
     * @param integer $taskId
     *
     * @return array
     */
    public static function getAllTasksSettings($projectId, $taskId = null)
    {
        global $Proj;

        $sql = "SELECT * FROM redcap_mycap_tasks WHERE project_id = $projectId";
        if (is_numeric($taskId)) $sql .= " AND task_id = $taskId";

        $q = db_query($sql);
        $tasks = array();
		$tasks_order = array();
        while ($row = db_fetch_assoc($q))
        {
            // Add task information
            foreach ($row as $key=>$value)
            {
                if ($key != 'project_id' && $key != 'task_id') {
                    // Remove any HTML from task title
                    if ($key == 'task_title') $value = label_decode($value);

                    // Add to array
                    $tasks[$row['task_id']][$key] = $value;
                }
            }
            // Make sure tasks are in form order
            $tasks_order[$row['task_id']] = isset($Proj->forms[$row['form_name']]) ? $Proj->forms[$row['form_name']]['form_number'] : 1;
        }
        // Make sure tasks are in form order
        asort($tasks_order);
        $tasks_ordered = array();
        foreach ($tasks_order as $this_task_id=>$order) {
            $tasks_ordered[$this_task_id] = $tasks[$this_task_id];
        }
        // Return array of task(s) attributes
        if ($taskId == null) {
            return $tasks_ordered;
        } else {
            return $tasks_ordered[$taskId];
        }
        return $tasks_ordered;
    }

    /**
     * Returns list of all issues if instrument is from unsupported PROMIS instruments list
     *
     * @param string $instrument
     *
     * @return array
     */
    public static function getUnsupportedPromisInstrumentsIssues($instrument) {
        global $Proj;
        $key = \PROMIS::getPromisKey($instrument);
        $issues = array();
        if (in_array($key, Promis::unsupportedPromisInstruments())) {
            $issues[] = "The instrument \"".$Proj->forms[$instrument]['menu']."\" is a health measure that is not currently supported by MyCap.";
        }
        return $issues;
    }

    /**
     * Erase the sync issues for a record/instance or record/form/instance (if a user deletes all the data for a form)
     *
     * @param integer $project_id
     * @param string $record
     * @param string $instance
     *
     * @return void
     */
    public static function eraseMyCapSyncIssues($project_id, $record, $instance=1)
    {
        $uuids = self::getUUIDFieldValue($project_id, $record, $instance);
        // Remove MyCap Sync issues
        if (!empty($uuids)) {
            $sql = "DELETE FROM redcap_mycap_syncissues WHERE uuid IN ('".implode("', '", $uuids)."')";
            db_query($sql);
        }
    }

    /**
     * Check if instruments contains any error
     *
     * @param string $form
     * @param integer $projectId
     *
     * @return array
     */
    public static function checkErrors($form, $projectId)
    {
		global $lang;
        $Proj = new \Project($projectId);
		$errors = [];
        $warnings = [];

		// Error if this instrument contains a randomization target field
		if ($GLOBALS['randomization'] && \Randomization::setupStatus($projectId)) {
            $allRandomizationAttrs = \Randomization::getAllRandomizationAttributes($projectId);
            foreach ($allRandomizationAttrs as $rid => $ridAttr) {
			    $randomization_form = $Proj->metadata[$ridAttr['targetField']]['form_name'];
			    if ($form == $randomization_form) {
				    $errors[] = $lang['mycap_mobile_app_690'];
                    continue;
			    }
            }
		}

		if (empty($errors)) {
            $currentDictionary = \REDCap::getDataDictionary(
                $projectId,
                'array',
                false,
                array(),
                $form
            );

            $instrumentDictionary = self::splitDictionaryByInstrument($currentDictionary);
            $dictionary = self::joinDictionaryInstruments($instrumentDictionary);

            $dataDictionary = self::convertFlatMetadataToDDarray($dictionary);

            list ($errors, $warnings, $dataDictionary) = \MetaData::error_checking($dataDictionary, false, false, true, ($form != $Proj->firstForm));

            // Ignore Randamization error as this is already handled
            unset($errors[30]);
        }
        return array($errors, $warnings);
    }

    /**
     * Split Dictionary By Instrument
     *
     * @param array $dictionary
     *
     * @return array
     */
    private static function splitDictionaryByInstrument($dictionary)
    {
        $split = [];
        foreach ($dictionary as $fieldName => $field) {
            $split[$field['form_name']][$fieldName] = $field;
        }
        return $split;
    }

    /**
     * Join Dictionary Instruments
     *
     * @param array $splitDictionary
     *
     * @return array
     */
    private static function joinDictionaryInstruments($splitDictionary)
    {
        $join = [];
        foreach ($splitDictionary as $instrument => $fields) {
            $join = array_merge($join, $fields);
        }
        return $join;
    }

    /**
     * Convert a flat item-based metadata array into Data Dictionary array with specific Excel-cell-named keys-subkeys (e.g. A1)
     *
     * @param array $data
     *
     * @return array
     */
    public static function convertFlatMetadataToDDarray($data)
    {
        $csv_cols = \MetaData::getCsvColNames();
        $dd_array = array();
        $r = 1; // Start with 1 so that the record ID field gets row 2 position (assumes headers in row 1)

        foreach($data as $row)
        {
            ++$r;
            $row_keys = array_keys($row);

            foreach($csv_cols as $n => $l)
            {
                if(!isset($dd_array[$l]))
                {
                    $dd_array[$l] = array();
                }

                $dd_array[$l][$r] = $row[$row_keys[$n-1]];
            }
        }
        return $dd_array;
    }

    /**
     * Get list of missing annotations required for MyCap
     *
     * @param string $form
     *
     * @return array
     */
    public static function getMissingAnnotationList($form)
    {
        global $draft_mode, $status;
        $fields = \REDCap::getDataDictionary('array', false, array(), array($form), ($status > 0 && $draft_mode > 0));

        $requiredAnnotations = self::$requiredAnnotations;
        foreach ($fields as $field) {
            if (count($requiredAnnotations) === 0) {
                break;
            }
            foreach ($requiredAnnotations as $idx => $annotation) {
                if (strpos(
                        $field['field_annotation'],
                        $annotation
                    ) !== false) {
                    unset($requiredAnnotations[$idx]);
                    continue 2;
                }
            }
        }
        return $requiredAnnotations;
    }

    /**
     * Get Error text for missing annotations required for MyCap
     *
     * @param array $missingAnnotations
     *
     * @return string
     */
    public static function getMissingAnnotationErrorText($missingAnnotations)
    {
        global $lang;

        $errorText = '';
        if (count($missingAnnotations) > 0) {
            foreach ($missingAnnotations as $annotation) {
                $list[] = $annotation;
            }
            $errorText = $lang['mycap_mobile_app_703']."<br>".$lang['mycap_mobile_app_704']." ";
            if (!empty($list)) $errorText .= "<code><b>".implode(", ", $list)."</b></code>";
        }

        return $errorText;
    }

    /**
     * Get Error text for missing annotations required for MyCap
     *
     * @param array $missingAnnotations
     *
     * @return string
     */
    public static function getMissingAnnotationErrorTextForAll($forms)
    {
        global $lang, $Proj;
        $errorText = '';
        if (count($forms) > 0) {
            $errorText = $lang['mycap_mobile_app_703']."<br>".$lang['mycap_mobile_app_698']." ";
            $errorText .= '<ul>';
            foreach ($forms as $form) {
                $errorText .= '<li style="padding-top: 5px;"><code>'.$Proj->forms[$form]['menu'].'</code></li>';
            }
            $errorText .= '</ul>';
        }

        return $errorText;
    }

    /**
     * Fix Missing annotations issues for instrument
     *
     * @param array $missingAnnotations
     * @param string $form
     *
     * @return void
     */
    public static function fixMissingAnnotationsIssues($missingAnnotations, $form)
    {
        global $Proj, $draft_mode, $status;
        if ($draft_mode != '1' && $status > 0)  return;

        $fieldsArr = self::getFormFields($missingAnnotations);
        if (count($fieldsArr) > 0) {
            if ($status > 0) {
                $Proj->loadMetadataTemp();
            } else {
                $Proj->loadMetadata();
            }
            $projectDesigner = new ProjectDesigner($Proj);

            foreach ($fieldsArr as $field) {
                $field['field_name'] = ActiveTask::getNewFieldName($field['field_name']);
                $projectDesigner->createField($form, $field);
                if ($field['field_annotation'] == Annotation::TASK_UUID) {
                    $section_header_field = array('field_label' => 'MyCap App Fields - Do Not Modify',
                                                'field_type' => 'section_header');
                    $projectDesigner->createField($form, $section_header_field, $field['field_name'], true);
                }
            }
        }
    }

    /**
     * Get list of form fields to add Missing annotations fields for instrument
     *
     * @param array $missingAnnotations
     *
     * @return array
     */
    public static function getFormFields($missingAnnotations) {
        $hide_on_survey_annotation = " @HIDDEN-SURVEY";
        foreach ($missingAnnotations as $annotation) {
            switch ($annotation) {
                case Annotation::TASK_UUID:
                    $fieldArr[] = array('field_name' => 'uuid',
                                        'field_label' => 'UUID',
                                        'field_type' => 'text',
                                        'field_annotation' => Annotation::TASK_UUID.$hide_on_survey_annotation);
                    break;
                case Annotation::TASK_STARTDATE:
                    $fieldArr[] = array('field_name' => 'startdate',
                                        'field_label' => 'Start Date',
                                        'field_type' => 'text',
                                        'field_annotation' => Annotation::TASK_STARTDATE.$hide_on_survey_annotation);
                    break;
                case Annotation::TASK_ENDDATE:
                    $fieldArr[] = array('field_name' => 'enddate',
                                        'field_label' => 'End Date',
                                        'field_type' => 'text',
                                        'field_annotation' => Annotation::TASK_ENDDATE.$hide_on_survey_annotation);
                    break;
                case Annotation::TASK_SCHEDULEDATE:
                    $fieldArr[] = array('field_name' => 'scheduledate',
                                        'field_label' => 'Schedule Date',
                                        'field_type' => 'text',
                                        'field_annotation' => Annotation::TASK_SCHEDULEDATE.$hide_on_survey_annotation);
                    break;
                case Annotation::TASK_STATUS:
                    $choices = "0, Deleted \\n 1, Completed \\n 2, Incomplete";
                    $fieldArr[] = array('field_name' => 'status',
                                        'field_label' => 'Status',
                                        'field_type' => 'select',
                                        'element_enum' => $choices,
                                        'field_annotation' => Annotation::TASK_STATUS.$hide_on_survey_annotation);
                    break;
                case Annotation::TASK_SUPPLEMENTALDATA:
                    $fieldArr[] = array('field_name' => 'supplementaldata',
                                        'field_label' => 'Supplemental Data (JSON)',
                                        'field_type' => 'textarea',
                                        'field_annotation' => Annotation::TASK_SUPPLEMENTALDATA.$hide_on_survey_annotation);
                    break;
                case Annotation::TASK_SERIALIZEDRESULT:
                    $fieldArr[] = array('field_name' => 'serializedresult',
                                        'field_label' => 'Serialized Result',
                                        'field_type' => 'file',
                                        'field_annotation' => Annotation::TASK_SERIALIZEDRESULT.$hide_on_survey_annotation);
                    break;
            }
        }
        return $fieldArr;
    }

    /**
     * Returns all values of fields having annotation set to "@MC-TASK-UUID"
     *
     * @param integer $projectId
     * @param string $record
     * @param integer $instanceNum
     *
     * @return array
     */
    public static function getUUIDFieldValue($projectId, $record, $instanceNum = '') {
        $dictionary = \REDCap::getDataDictionary($projectId, 'array', false, true);

        foreach ($dictionary as $field => $fieldDetails) {
            if (strpos($fieldDetails['field_annotation'], Annotation::TASK_UUID) !== false) {
                $map[$field] = $fieldDetails['field_annotation'];
            }
        }
        $data = \REDCap::getData(
            $projectId,
            'array',
            array($record)
        );
        $uuid = array();
        foreach ($data as $record=>&$event_data)
        {
            foreach (array_keys($event_data) as $event_id)
            {
                if ($event_id == 'repeat_instances') {
                    $eventNormalized = $event_data['repeat_instances'];
                } else {
                    $eventNormalized = array();
                    $eventNormalized[$event_id][""][0] = $event_data[$event_id];
                }
                foreach ($eventNormalized as $event_id=>&$data1)
                {
                    foreach ($data1 as $repeat_instrument=>&$data2)
                    {
                        foreach ($data2 as $instance=>&$data3)
                        {
                            if ($instanceNum != '') {
                                if ($instanceNum == $instance) {
                                    foreach ($data3 as $field=>$value)
                                    {
                                        if (array_key_exists($field, $map) && $value != '') {
                                            $uuid[] = $value;
                                        }
                                    }
                                }
                            } else {
                                foreach ($data3 as $field=>$value)
                                {
                                    if (isset($map) && is_array($map) && $value != '' && array_key_exists($field, $map)) {
                                        $uuid[] = $value;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            unset($data[$record], $event_data, $data1, $data2, $data3);
        }
        return $uuid;
    }

    /**
     * Returns all MyCap Task errors for selected instrument
     *
     * @param string $form
     *
     * @return array
     */
    public static function getMyCapTaskErrors($form = '') {
        global $Proj, $myCapProj, $lang;
        $errors = array();
        $invalidSetupForms = [];
	    $isBatteryInstrument = false;
        if ($form == '') {
            $batteryInstrumentsList = self::batteryInstrumentsInSeriesPositions();
            $other_promis_forms = [];
            foreach ($Proj->forms as $form => $attr) {
                if (isset($myCapProj->tasks[$form]['enabled_for_mycap']) && $myCapProj->tasks[$form]['enabled_for_mycap'] == 1) {
                    $missingAnnotations = self::getMissingAnnotationList($form);
                    if (!empty($missingAnnotations)) {
                        $missingAnnotationsForms[] = $form;
                    }
                    if (!$Proj->longitudinal && !$Proj->isRepeatingForm($Proj->firstEventId, $form)) {
                        $errorRepeatingForms[] = $form;
                    }

                    if ($Proj->longitudinal) {
                        $schedules = Task::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
                        $formsRepeating[$form] = self::getNonRepeatingFormErrors($form, $schedules);
                        $eventsRepeating[$form] = self::getRepeatingEventErrors($form, $schedules);

                        if (!empty($schedules)) {
                            foreach ($schedules as $eventId => $schedule) {
                                $validTaskSchedule = self::checkSchedulesForEvent($schedule);
                                if ($validTaskSchedule == false) {
                                    $invalidSetupForms[] = $Proj->forms[$form]['menu'] . ' ['. RCView::tt('api_25') . " ". $Proj->eventInfo[$eventId]['name_ext'] . ']';
                                }
                            }
                        }
                    } else {
                        $schedules = self::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
                        $validTaskSchedule = self::checkSchedulesForEvent($schedules[$Proj->firstEventId]);
                        if ($validTaskSchedule == false) {
                            $invalidSetupForms[] = $Proj->forms[$form]['menu'];
                        }
                    }

                    $isBatteryInstrument = array_key_exists($form, $batteryInstrumentsList);
                    if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
                        foreach ($batteryInstrumentsList as $instrument => $arr) {
                            if ($instrument == $form || $arr['firstInstrument'] == $form) {
                                if (!$Proj->longitudinal && !$Proj->isRepeatingForm($Proj->firstEventId, $instrument)) {
                                    $other_promis_forms[] = "\"" . $Proj->forms[$instrument]['menu'] . "\"";;
                                } else if ($Proj->longitudinal) {
                                    if (isset($myCapProj->tasks[$form]['task_id'])) {
                                        $schedules = self::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
                                        if (!empty($schedules)) {
                                            foreach ($schedules as $eventId => $schedule) {
                                                if (!$Proj->isRepeatingForm($eventId, $instrument)) {
                                                    $other_promis_forms[] = "\"" . $Proj->forms[$instrument]['menu'] . "\"";
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if (!empty($missingAnnotationsForms)) {
                $errors[] = self::getMissingAnnotationErrorTextForAll($missingAnnotationsForms);
            }
            if (!empty($invalidSetupForms)) {
                $errors[] = self::getMissingTaskSetupErrorTextForAll($invalidSetupForms);
            }
            if ($Proj->longitudinal) {
                $errorText = "";
                if (!empty($formsRepeating)) {
                    $errorText .= self::getNonRepeatingFormsErrorsText($formsRepeating);
                }
                if ($errorText != '') {
                    $errors[] = $lang['mycap_mobile_app_830'].$errorText;
                }

                $errorText = "";
                if (!empty($eventsRepeating)) {
                    $errorText .= self::getRepeatingEventsErrorsText($eventsRepeating);
                }
                if ($errorText != '') {
                    $errors[] = $lang['mycap_mobile_app_735'].$errorText;
                }
            }

            if (!empty($errorRepeatingForms)) {
                $errorText = $lang['mycap_mobile_app_699'];
                $errorText .= '<ul>';
                foreach ($errorRepeatingForms as $form) {
                    $note = '';
                    if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
                        $note = '<br>'.RCView::tt('mycap_mobile_app_917');
                    }
                    $errorText .= '<li style="padding-top: 5px;"><code>'.$Proj->forms[$form]['menu'].'</code>'.$note.'</li>';
                }
                $errorText .= '</ul>';
                if (!empty($list)) $errorText .= "<code><b>".implode(", ", $list)."</b></code>";
                $errors[] = $errorText;
            }
            if (!empty($other_promis_forms)) {
                $errorText = $lang['mycap_mobile_app_699'];
                $errorText .= '<ul>';
                foreach ($other_promis_forms as $promis_form) {
                    $errorText .= '<li style="padding-top: 5px;"><code>'.$promis_form.'</code></li>';
                }
                $errorText .= '</ul>';
                if (!empty($list)) $errorText .= "<code><b>".implode(", ", $list)."</b></code>";
                $errors[] = $errorText;
            }


            if (!empty($errorNonRepeatingEvent)) {
                $errorText = $lang['mycap_mobile_app_736']." ".$lang['mycap_mobile_app_737'];
                $errorText .= '<ul>';
                foreach ($errorNonRepeatingEvent as $error) {
                    $errorText .= '<li style="padding-top: 5px;">'.$error.'</li>';
                }
                $errorText .= '</ul>';
                $errors[] = $errorText;
            }
        } else {
            $missingAnnotations = self::getMissingAnnotationList($form);

            if (!empty($missingAnnotations)) {
                $missingAnnotationsError = self::getMissingAnnotationErrorText($missingAnnotations);
                $errors[] = $missingAnnotationsError;
            }
            $batteryInstrumentsList = self::batteryInstrumentsInSeriesPositions();
            if (array_key_exists($form, $batteryInstrumentsList)) {
                $isBatteryInstrument = true;
            }
            $schedules = self::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
            $other_promis_forms_exists = false;
            if ($Proj->longitudinal) {
                foreach ($schedules as $eventId => $schedule) {
                    if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
                        foreach ($batteryInstrumentsList as $instrument => $arr) {
                            if ($instrument == $form || $arr['firstInstrument'] == $form) {
                                if (!$Proj->isRepeatingForm($eventId, $instrument)) {
                                    $events[] = $Proj->eventInfo[$eventId]['name_ext'];
                                    $other_promis_forms_exists = true;
                                }
                            }
                        }
                    } else {
                        if (!$Proj->isRepeatingForm($eventId, $form)) {
                            $events[] = $Proj->eventInfo[$eventId]['name_ext'];
                        }
                    }
                    if ($Proj->isRepeatingEvent($eventId)) {
                        $repeatingEvents[] = $Proj->eventInfo[$eventId]['name_ext'];
                    }
                }
                if (!empty($events)) {
                    $text = $lang['mycap_mobile_app_829']." <code>".implode(", ", array_unique($events))."</code>";
                    if ($other_promis_forms_exists) {
                        $text .= '<br>'.RCView::tt('mycap_mobile_app_917');
                    }
                    $errors[] = $text;

                }
                if (!empty($repeatingEvents)) {
                    $errors[] = $lang['mycap_mobile_app_720']." <code>".implode(", ", $repeatingEvents)."</code>";
                }
                $invalidSetupEvents = [];
                if (!empty($schedules)) {
                    foreach ($schedules as $eventId => $schedule) {
                        $validTaskSchedule = self::checkSchedulesForEvent($schedule);
                        if ($validTaskSchedule == false) {
                            $invalidSetupEvents[] = $Proj->eventInfo[$eventId]['name_ext'];
                        }
                    }
                }
                if (!empty($invalidSetupEvents)) {
                    $eventsList = "<br>"."<b>".RCView::tt('mycap_mobile_app_847')."</b>";
                    $eventsList .= '<ul>';
                    foreach ($invalidSetupEvents as $event) {
                        $eventsList .= '<li style="padding-top: 5px;"><code>'.$event.'</code></li>';
                    }
                    $eventsList .= '</ul>';
                    $errors[] = RCView::tt('mycap_mobile_app_846')." ".$eventsList;
                }
            } else {
                if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
                    foreach ($batteryInstrumentsList as $instrument => $arr) {
                        if ($instrument == $form || $arr['firstInstrument'] == $form) {
                            if (!$Proj->isRepeatingForm($Proj->firstEventId, $instrument)) {
                                $other_promis_forms_exists = true;
                            }
                        }
                    }
                }
                $text = '';
                if (!$Proj->isRepeatingForm($Proj->firstEventId, $form)) {
                    $text = $lang['mycap_mobile_app_588'];
                }
                if ($other_promis_forms_exists) {
                    $text .= (($text!='') ? '<br>' : '').RCView::tt('mycap_mobile_app_917');
                }
                if ($text != '') {
                    $errors[] = $text;
                }
                $validTaskSchedule = self::checkSchedulesForEvent($schedules[$Proj->firstEventId]);
                if ($validTaskSchedule == false) {
                    $errors[] = RCView::tt('mycap_mobile_app_846')." ";
                }
            }

        }

        return $errors;
    }

    /**
     * Returns all MyCap Task errors for selected instrument those can not be fixed by button click
     *
     * @param string $form
     *
     * @return array
     */
    public static function getMyCapTaskNonFixableErrors($form_name = '') {
        global $Proj, $myCapProj, $lang;
        $errors = array();
        $baseline_date_field = $myCapProj->project['baseline_date_field'];
        $Proj_metadata = $Proj->getMetadata();

        if ($Proj->longitudinal) {
            if ($form_name == '') {
                foreach ($Proj->forms as $form => $attr) {
                    if (isset($myCapProj->tasks[$form]) && $myCapProj->tasks[$form]['enabled_for_mycap'] == 1) {
                        $instrumentFields = Task::getListExcludingMyCapFields($form);
                        if (empty($instrumentFields)) {
                            $error_details['no_fields'][] = $form;
                        }
                        $eventsList = self::getEventsList($form);
                        if (empty($eventsList)) {
                            $error_details['designate_instrument'][] = $form;
                        } else {
                            $eventsSchedules = self::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
                            if (empty($eventsSchedules)) {
                                $error_details['enable_atleast_one_event'][] = $form;

                            }
                        }
                    }
                }
                $errorText = '';
                if (!empty($error_details['no_fields'])) {
                    $errorText .= $lang['mycap_mobile_app_734'];
                    $errorText .= '<ul>';
                    foreach ($error_details['no_fields'] as $form) {
                        $errorText .= '<li style="padding-top: 5px;"><code>'.$Proj->forms[$form]['menu'].'</code></li>';
                    }
                    $errorText .= '</ul>';
                }

                if (!empty($error_details['designate_instrument'])) {
                    $errorText .= $lang['mycap_mobile_app_710'];
                    $errorText .= '<ul>';
                    foreach ($error_details['designate_instrument'] as $form) {
                        $errorText .= '<li style="padding-top: 5px;"><code>'.$Proj->forms[$form]['menu'].'</code></li>';
                    }
                    $errorText .= '</ul>';
                }
                if (!empty($error_details['enable_atleast_one_event'])) {
                    $errorText .= $lang['mycap_mobile_app_779'];
                    $errorText .= '<ul>';
                    foreach ($error_details['enable_atleast_one_event'] as $form) {
                        $errorText .= '<li style="padding-top: 5px;"><code>'.$Proj->forms[$form]['menu'].'</code></li>';
                    }
                    $errorText .= '</ul>';
                }
                if (!empty($errorText)) {
                    $errors[] = $errorText;
                }
                // Validate baseline date setting - Case when user edit name/validation for baseline date field, user need to correct baseline date setting manually
                if (!empty($baseline_date_field)) {
                    // Longitudinal projects with multi-arms
                    if ($Proj->multiple_arms) {
                        $allArms = $Proj->numArms;
                        $foundArr = [];
                        for ($arm = 1; $arm <= $allArms; $arm++) {
                            $eventsInArm = $Proj->getEventsByArmNum($arm);
                            $fields = explode("|", $baseline_date_field);
                            if (is_array($fields)) {
                                foreach ($fields as $field1) {
                                    list ($event_id, $field_name) = explode("-", $field1);
                                    if (in_array($event_id, $eventsInArm)) {
                                        if (is_array($Proj_metadata[$field_name])) {
                                            if ($Proj_metadata[$field_name]['element_type'] == 'text' && substr($Proj_metadata[$field_name]['element_validation_type']??"", 0, 4) == 'date') {
                                                $foundArr[] = true;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if (array_unique($foundArr) == false || count($foundArr) != $allArms) {
                            $errors[] = $lang['mycap_mobile_app_966'];
                        }
                    } else {
                        // Longitudinal projects
                        $found = false;
                        $date_arr = explode("-", $baseline_date_field);
                        if (count($date_arr) > 1) {
                            list ($eventId, $baseline_field) = $date_arr;
                            if (is_array($Proj_metadata[$baseline_field])) {
                                if ($Proj_metadata[$baseline_field]['element_type'] == 'text' && substr($Proj_metadata[$baseline_field]['element_validation_type']??"", 0, 4) == 'date') {
                                    $found = true;
                                }
                            }
                        }
                        if ($found == false) {
                            $errors[] = $lang['mycap_mobile_app_966'];
                        }
                    }
                }
            } else {
                $instrumentFields = Task::getListExcludingMyCapFields($form_name);
                if (empty($instrumentFields)) {
                    $errors[] = $lang['mycap_mobile_app_734'];
                }
                $eventsList = self::getEventsList($form_name);
                if (empty($eventsList)) {
                    $errors[] = $lang['mycap_mobile_app_710'];
                } else {
                    $eventsSchedules = self::getTaskSchedules($myCapProj->tasks[$form_name]['task_id']??'');
                    if (empty($eventsSchedules)) {
                        $errors[] = (PAGE == 'MyCap/edit_task.php') ? $lang['mycap_mobile_app_779'] : $lang['mycap_mobile_app_802'];
                    }
                }
            }
        } else {
            // For Non-longitudinal projects
            if ($form_name == '') {
                foreach ($Proj->forms as $form => $attr) {
                    if (isset($myCapProj->tasks[$form]['enabled_for_mycap']) && $myCapProj->tasks[$form]['enabled_for_mycap'] == 1) {
                        $instrumentFields = Task::getListExcludingMyCapFields($form);
                        if (empty($instrumentFields)) {
                            $error_details['no_fields'][] = $form;
                        }
                    }
                }
                $errorText = '';
                if (!empty($error_details['no_fields'])) {
                    $errorText .= $lang['mycap_mobile_app_734'];
                    $errorText .= '<ul>';
                    foreach ($error_details['no_fields'] as $form) {
                        $errorText .= '<li style="padding-top: 5px;"><code>'.$Proj->forms[$form]['menu'].'</code></li>';
                    }
                    $errorText .= '</ul>';
                }
                if (!empty($errorText)) {
                    $errors[] = $errorText;
                }

                // Validate baseline date setting for Non-longitudinal projects
                if (!empty($baseline_date_field)) {
                    $found = false;
                    if (is_array($Proj_metadata[$baseline_date_field])) {
                        if ($Proj_metadata[$baseline_date_field]['element_type'] == 'text' && substr($Proj_metadata[$baseline_date_field]['element_validation_type']??"", 0, 4) == 'date') {
                            $found = true;
                        }
                    }
                    if ($found == false) {
                        $errors[] = $lang['mycap_mobile_app_966'];
                    }
                }
            } else {
                $instrumentFields = Task::getListExcludingMyCapFields($form_name);
                if (empty($instrumentFields)) {
                    $errors[] = $lang['mycap_mobile_app_734'];
                }
            }
        }
        return $errors;
    }
    /**
     * Fix all MyCap Task errors for selected instrument
     *
     * @param string $form
     *
     * @return void
     */
    public static function fixMyCapTaskErrors($form) {
        global $Proj;
        if ($form == '') {
            $myCapProj = new MyCap(PROJECT_ID);
            foreach ($Proj->forms as $form => $attr) {
                if (isset($myCapProj->tasks[$form]['task_id'])) {
                    $missingAnnotations = self::getMissingAnnotationList($form);
                    if (count($missingAnnotations) > 0) {
                        self::fixMissingAnnotationsIssues($missingAnnotations, $form);
                    }
                    global $myCapProj;
                    if (!$Proj->longitudinal) {
                        $schedules = self::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
                        $validTaskSchedule = self::checkSchedulesForEvent($schedules[$Proj->firstEventId]);
                        if ($validTaskSchedule == false) {
                            self::fixSchedulesForEvent($form);
                        }
                    }
                    if ($Proj->longitudinal) {
                        self::fixRepeatingFormIssues($form);

                        $schedules = self::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
                        if (!empty($schedules)) {
                            foreach ($schedules as $eventId => $schedule) {
                                $validTaskSchedule = self::checkSchedulesForEvent($schedule);
                                if ($validTaskSchedule == false) {
                                    self::fixSchedulesForEvent($form, $eventId);
                                }
                            }
                        }
                    } else {
                        self::fixRepeatingPromisFormIssues($form);
                        if (!$Proj->isRepeatingForm($Proj->firstEventId, $form)) {
                            // Make this form as repeatable with default eventId as project is classic
                            $sql = "INSERT INTO redcap_events_repeat (event_id, form_name) 
                                VALUES ({$Proj->firstEventId}, '" . db_escape($form) . "')";
                            db_query($sql);
                        }
                    }
                }
            }
        } else {
            $missingAnnotations = self::getMissingAnnotationList($form);
            if (count($missingAnnotations) > 0) {
                self::fixMissingAnnotationsIssues($missingAnnotations, $form);
            }
            global $myCapProj;
            if (!$Proj->longitudinal) {
                $schedules = self::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
                $validTaskSchedule = self::checkSchedulesForEvent($schedules[$Proj->firstEventId]);
                if ($validTaskSchedule == false) {
                    self::fixSchedulesForEvent($form);
                }
            }
            if ($Proj->longitudinal) {
                self::fixRepeatingFormIssues($form);

                $schedules = self::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
                if (!empty($schedules)) {
                    foreach ($schedules as $eventId => $schedule) {
                        $validTaskSchedule = self::checkSchedulesForEvent($schedule);
                        if ($validTaskSchedule == false) {
                            self::fixSchedulesForEvent($form, $eventId);
                        }
                    }
                }
            } else {
                self::fixRepeatingPromisFormIssues($form);
                if (!$Proj->isRepeatingForm($Proj->firstEventId, $form)) {
                    // Make this form as repeatable with default eventId as project is classic
                    $sql = "INSERT INTO redcap_events_repeat (event_id, form_name) 
                    VALUES ({$Proj->firstEventId}, '" . db_escape($form) . "')";
                    db_query($sql);
                }
            }
        }

    }

    /**
     * Remove extra spaces from comma seperated string (Exa. Fixed Schedule 1,  7 should return as 1,7)
     *
     * @param string $string
     *
     * @return string
     */
    public static function removeSpaces($string) {
        $arr = explode(",", $string);
        foreach ($arr as $value) {
            $trimedArr[] = trim($value);
        }
        return implode(",", $trimedArr);
    }

    /**
     * Get list of events that utilizes form
     *
     * @param string $form
     * @param int $projectId
     *
     * @return array
     */
    public static function getEventsList($form, $projectId = null) {
        if (is_null($projectId)) {
            global $Proj;
        } else {
            $Proj = new \Project($projectId);
        }

        $events = array();
        foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
            foreach ($these_forms as $this_form) {
                if ($this_form == $form) {
                    $events[] = $this_event_id;
                }
            }
        }
        return $events;
    }

    /**
     * Get all schedules relative to each event that utilizes form
     *
     * @param integer $taskId
     * @param string $flag
     * @param integer $projectId
     *
     * @return array
     */
    public static function getTaskSchedules($taskId = '', $flag = '', $projectId = null) {
        if (!isinteger($taskId)) return [];
        if (is_null($projectId)) {
            global $Proj;
        } else {
            $Proj = new \Project($projectId);
        }
        $condition = "";
        if (!$Proj->longitudinal && $flag == '') {
            $condition = "AND event_id = {$Proj->firstEventId}";
        }
        $sql = "SELECT * FROM redcap_mycap_tasks_schedules WHERE task_id = $taskId AND active = '1' $condition ORDER BY event_id";
        $q = db_query($sql);
        $scheduleList = [];
        while ($row = db_fetch_assoc($q))
        {
            unset($row['ts_id'], $row['task_id']);
            $scheduleList[$row['event_id']] = $row;
        }
        return $scheduleList;
    }

    /**
     * Returns all MyCap Task warnings + errors for fix issues popup and publish config popup
     *
     * @param string $page
     * @param string $section   fix|publish
     *
     * @return string
     */
    public static function listMyCapTasksIssues($page = '', $section = 'fix') {
        global $lang;
        $taskErrors = self::getMyCapTaskErrors($page);
        $taskNonFixableErrors = self::getMyCapTaskNonFixableErrors($page);
        $data['count'] = count($taskErrors);
        $html = '';
        if (!empty($taskNonFixableErrors)) {
            $html .= '<div class="red" id="div_errors_list" '.(($section == 'publish') ? 'style="display:none;"' : "").'><i class="fa fa-circle-exclamation" style="color: red;"></i> <b>'.$lang['global_109'].'</b> ';
            $html .= '<ul>';
            foreach ($taskNonFixableErrors as $error) {
                $html .= '<li style="padding-top: 5px;">'.$error.'</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        if (!empty($taskErrors)) {
            $html .= '<div class="yellow" id="div_warnings_list" style="margin-top: 15px;'.(($section == 'publish') ? 'display:none;' : '').'"><i class="fa fa-warning" style="color:darkorange;"></i> <b>'.$lang['mycap_mobile_app_721'].'</b> ';
            $html .= (($_GET['page'] != '') ? $lang['mycap_mobile_app_589'] : $lang['mycap_mobile_app_701']);
            $html .= '<ul>';
            foreach ($taskErrors as $error) {
                $html .= '<li style="padding-top: 5px;">'.$error.'</li>';
            }
            $html .= '</ul>';
            if ($section == 'fix') {
                global $Proj, $myCapProj;
                $onClick = 'fixMyCapIssues(\''.$page.'\');';
                $form = (isset($page) && isset($Proj->forms[$page])) ? $page : null;
                $opacityClass = "";
                global $status, $draft_mode;
                if ($status > 0 && $draft_mode == 0) {
                    $onClick = 'cannotFixMyCapIssues();';
                    $opacityClass = " opacity35";
                } elseif (($form == null || !isset($myCapProj->tasks[$form]['task_id'])) && $status > 0 && $draft_mode >= 1) {
                    // Project is in draft mode but form does not exists (need to submit for review)
                    $opacityClass = " opacity35";
                    $onClick = 'cannotFixMyCapIssuesBeforeReview();';
                }
                $html .= '<button onclick="'.$onClick.'" class="btn btn-xs btn-rcgreen'.$opacityClass.'" id="fixBtn" style="font-size:13px;margin-right:30px;text-align: right;">
			                                <i class="fas fa-check"></i> '.$lang['mycap_mobile_app_722'].'</button>';
            }

            $html .= '</div>';
        }
        return $html;
    }

    /**
     * Make event as non-repeating :: LONGITUDINAL PROJECTS
     *
     * @param integer $eventId
     *
     * @return void
     */
    public static function makeEventNonRepeating($eventId) {
        global $Proj;
        if ($Proj->isRepeatingEvent($eventId)) {
            // Make this event as non-repeatable with eventId as project is longitudinal
            $sql_delete = "DELETE FROM redcap_events_repeat WHERE event_id = '".$eventId."'";
            db_query($sql_delete);
        }
    }

    /**
     * Returns all events - if instrument is non-repeatable for selected events
     *
     * @param string $form
     *
     * @return array
     */
    public static function getNonRepeatingFormErrors($form, $schedules) {
        global $Proj;
        $events = array();

        foreach ($schedules as $eventId => $schedule) {
            if (!$Proj->isRepeatingForm($eventId, $form)) {
                $events[] = $Proj->eventInfo[$eventId]['name_ext'];
            }
        }
        return $events;
    }
    /**
     * Returns all errors - if instrument is repeatable for selected events
     *
     * @param string $form
     *
     * @return array
     */
    public static function getRepeatingEventErrors($form, $schedules) {
        global $Proj;
        $events = array();

        foreach ($schedules as $eventId => $schedule) {
             if ($Proj->isRepeatingEvent($eventId)) {
                 $events[] = $Proj->eventInfo[$eventId]['name_ext'];
            }
        }
        return $events;
    }

    /**
     * Fix all errors - if instrument is non-repeatable for selected events, make it repeatable :: LONGITUDINAL PROJECTS
     *
     * @param string $form
     *
     * @return void
     */
    public static function fixRepeatingFormIssues($form) {
        global $myCapProj, $Proj;
        $isBatteryInstrument = false;
        $batteryInstrumentsList = self::batteryInstrumentsInSeriesPositions();
        if (array_key_exists($form, $batteryInstrumentsList)) {
            $isBatteryInstrument = true;
        }

        $schedules = self::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
        foreach ($schedules as $eventId => $schedule) {
            if (!$Proj->isRepeatingForm($eventId, $form)) {
                self::makeFormRepeatingForEvent($eventId, $form);
            } else if ($Proj->isRepeatingEvent($eventId)) {
                self::makeEventNonRepeating($eventId);
            }
        }
        if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
            $all_instruments = array_keys($batteryInstrumentsList);
            foreach ($all_instruments as $this_form) {
                $schedules = self::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
                foreach ($schedules as $eventId => $schedule) {
                    if (!$Proj->isRepeatingForm($eventId, $this_form)) {
                        self::makeFormRepeatingForEvent($eventId, $this_form);
                    }
                }
            }
        }
    }

    /**
     * Fix all errors - if instrument is repeatable for selected events, make it non-repeatable :: LONGITUDINAL PROJECTS
     *
     * @param string $form
     *
     * @return void
     */
    public static function checkFormEventsBindingError($events = array()) {
        global $lang;
        $error_message = "";
        if (empty($events)) {
            $error_message = RCView::div(array('class'=>'yellow','style'=>'padding:10px;'),
                RCView::div(array('style'=>'font-weight:bold;'),
                    RCView::img(array('src'=>'exclamation_orange.png')) .
                    $lang['mycap_mobile_app_723']
                ) .
                RCView::div(array('style'=>'padding-top:5px;'),
                    $lang['mycap_mobile_app_816']
                ).
                RCView::div(array('style'=>'padding-top:15px;'),
                    "<a href='" . APP_PATH_WEBROOT . "Design/designate_forms.php?pid=".PROJECT_ID."&page_edit=".$_GET['page']."' style='text-decoration:underline;'>{$lang['global_28']}</a>"
                )
            );
        }
        return $error_message;
    }

    /**
     * Get form-event binding utilized in MyCap task setup for project :: LONGITUDINAL PROJECTS
     *
     * @param integer $projectId
     *
     * @return array
     */
    public static function getFormEventsBindings() {
        global $Proj, $myCapProj;
        $binding = array();
        foreach ($Proj->forms as $form => $attr) {
            if ($myCapProj->tasks[$form]['enabled_for_mycap'] == 1) {
                $eventsSchedules = self::getTaskSchedules($myCapProj->tasks[$form]['task_id']);
                if (!empty($eventsSchedules)) {
                    foreach ($eventsSchedules as $eventId) {
                        $binding[$form][] = $eventId;
                    }
                }
            }
        }
        return $binding;
    }

    /**
     * Returns all errors text - if instrument is non-repeatable :: LONGITUDINAL PROJECTS
     *
     * @param array $formsRepeating
     *
     * @return array
     */
    public static function getNonRepeatingFormsErrorsText($formsRepeating) {
        global $Proj, $lang;
        $errorText = $list = "";
        $batteryInstrumentsList = self::batteryInstrumentsInSeriesPositions();
        foreach ($formsRepeating as $form => $events) {
	        $isBatteryInstrument = array_key_exists($form, $batteryInstrumentsList);
            $note = '';
            if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
                $note = '<br>'.RCView::tt('mycap_mobile_app_917');
            }
            if (!empty($events)) {
                $list .= '<li style="padding-top: 5px;">'.$lang['mycap_mobile_app_712'].' "'.$Proj->forms[$form]['menu'].'" '.$lang['mycap_mobile_app_828'].' <code>'.implode(", ", $events).'</code>'.$note;
            }
        }
        if ($list != '') {
            $errorText .= '<ul>'.$list.'</ul>';
        }
        return $errorText;
    }

    /**
     * Returns all errors text - if event is repeatable :: LONGITUDINAL PROJECTS
     *
     * @param array $repeatingFormsEventsErr
     *
     * @return array
     */
    public static function getRepeatingEventsErrorsText($eventsRepeating) {
        $errorText = "";
        $events = array();

        foreach ($eventsRepeating as $form => $eventArr) {
            foreach ($eventArr as $event) {
                $events[] = $event;
            }
        }
        $events = array_unique($events);
        if (!empty($events)) {
            $errorText .= '<ul>';
            foreach ($events as $event) {
                $errorText .= '<li style="padding-top: 5px;"><code>'.$event.'</code>';
            }
            $errorText .= '</ul>';
        }
        return $errorText;
    }

    /**
     * Returns all fields (Excluding fields having MyCap annotations)
     *
     * @param string $instrument
     *
     * @return array
     */
    public static function getListExcludingMyCapFields($instrument) {
        global $status, $draft_mode;
        $output = array();
        $instrumentFields = \REDCap::getDataDictionary('array', false, true, $instrument, ($status > 0 && $draft_mode > 0));
        foreach ($instrumentFields as $field) {
            $found = array();
            foreach (self::$requiredAnnotations as $annotation) {
                if (strpos(trim($field['field_annotation']), $annotation) !== false) {
                    $found[] = $annotation;
                }
            }
            if(empty($found)) {
                $output[] = $field;
            }
        }
        return $output;
    }

    /**
     * Returns CSV contents for all MyCap task schedules of projects
     *
     * @return string
     */
    public static function csvTaskSchedulesDownload() {
        global $Proj, $myCapProj;
        $result = [];
            $sql = "SELECT 
                        task_id, form_name, task_title, question_format, card_display, x_date_field, x_time_field, y_numeric_field, extended_config_json
                    FROM 
                        redcap_mycap_tasks 
                    WHERE enabled_for_mycap = 1
                        AND project_id = ?";

        $q = db_query($sql, [PROJECT_ID]);
        while ($row = db_fetch_assoc($q)) {
            $taskErrors = self::getMyCapTaskNonFixableErrors($row['form_name']);
            // Ignore tasks having non-fixable errors
            if (empty($taskErrors)) {
                $output = $row;
                $schedules = self::getTaskSchedules($row['task_id']);
                if (!$Proj->longitudinal) {
                    if (isset($schedules[$Proj->firstEventId])) {
                        $output = array_merge($output, $schedules[$Proj->firstEventId]);
                    }
                }
                unset($output['task_id'], $output['event_id']);
                $result[$row['form_name']][] = $output;
                // Make sure tasks are in form order
                $tasks_order[$row['form_name']] = $Proj->forms[$row['form_name']]['form_number'];
            }
        }
        asort($tasks_order);
        $tasks2 = array();
        foreach ($tasks_order as $this_form=>$order) {
            $tasks2[] = $result[$this_form][0];
        }
        if ($Proj->longitudinal) {
            foreach ($tasks2 as $task) {
                $form_name = $task['form_name'];
                $ts_id = $myCapProj->tasks[$form_name]['task_id'];
                $schedules = self::getTaskSchedules($ts_id);
                if (!empty($schedules)) {
                    foreach ($schedules as $eventId => $schedule) {
                        $a['form_name'] = $form_name;
                        $a['event_unique_name'] = $Proj->getUniqueEventNames($eventId);
                        $a = array_merge($a, $task);
                        unset($schedule['event_id']);
                        $ltask[] = array_merge($a, $schedule);
                    }
                }
            }
        }

        if ($Proj->longitudinal) {
            $tasks = $ltask;
        } else {
            $tasks = $tasks2;
        }
        $content = arrayToCsv($tasks);

        return $content;
    }

    /**
     * Get all schedules relative to each event that utilizes form
     *
     * @param integer $taskId
     * @param integer $eventId
     *
     * @return array
     */
    public static function getTaskSchedulesByEventId($taskId, $eventId = '') {
        $q = db_query("SELECT * FROM redcap_mycap_tasks_schedules WHERE event_id = ? AND task_id = ?", [$eventId, $taskId]);
        $schedules = array();
        while ($row = db_fetch_assoc($q))
        {
            $schedules = $row;
        }
        return $schedules;
    }

    /**
     * Display friendly string of task schedule description for each event
     *
     * @param array $details
     * @return string
     */
    public static function getDetailedSchedulesForEvent($details) {
        $retVal = '';
		if (isset($details['schedule_type'])) {
			if ($details['schedule_type'] == self::TYPE_ONETIME) {
				$retVal = 'One time';
			} elseif ($details['schedule_type'] == self::TYPE_INFINITE) {
				$retVal = 'Infinite';
			} elseif ($details['schedule_type'] == self::TYPE_REPEATING) {
				$retVal = 'Repeats';

				if ($details['schedule_frequency'] == self::FREQ_DAILY) {
					$retVal .= ' daily';
				} elseif ($details['schedule_frequency'] == self::FREQ_WEEKLY) {
					if (is_numeric($details['schedule_interval_week'])) {
						if ($details['schedule_interval_week'] == 1) {
							$retVal .= ' every week';
						} elseif ($details['schedule_interval_week'] > 1) {
							$retVal .= ' every ' . $details['schedule_interval_week'] . ' weeks';
						}

						if (strlen($details['schedule_days_of_the_week'])) {
							$dayInts = explode(',', $details['schedule_days_of_the_week']);
							$daysOfWeek = self::getDaysOfWeekList();
							if (count($dayInts)) {
								foreach ($dayInts as $day) {
									$dayStrings[] = $daysOfWeek[$day];
								}
								$retVal .= ' on ' . implode(', ', $dayStrings);
							}
						}
					}
				} elseif ($details['schedule_frequency'] == self::FREQ_MONTHLY) {
					if (is_numeric($details['schedule_interval_month'])) {
						if ($details['schedule_interval_month'] == 1) {
							$retVal .= ' every month';
						} elseif ($details['schedule_interval_month'] > 1) {
							$retVal .= ' every ' . $details['schedule_interval_month'] . ' months';
						}

						if (strlen($details['schedule_days_of_the_month'])) {
							$dayInts = explode(',', $details['schedule_days_of_the_month']);
							if (count($dayInts)) {
								foreach ($dayInts as $day) {
									if (substr($day, -1) == 1 && $day != 11) $dayStrings[] = $day . "st"; // check if last digit is 1 exa. 1,21,31
									elseif (substr($day, -1) == 2 && $day != 12) $dayStrings[] = $day . "nd"; // check if last digit is 2 exa. 2,22
									elseif (substr($day, -1) == 3 && $day != 13) $dayStrings[] = $day . "rd"; // check if last digit is 3 exa. 3,23
									else $dayStrings[] = $day . "th";
								}
								$retVal .= ' on ' . implode(', ', $dayStrings);
							}
						}
					}
				}
			} elseif ($details['schedule_type'] == self::TYPE_FIXED) {
				$retVal = 'Fixed';
			} else {
				$retVal = 'Invalid schedule';
			}
		} else {
			$retVal = 'Invalid schedule';
		}
        return $retVal;
    }

    /**
     * Make instrument for event non-repeating :: LONGITUDINAL PROJECTS
     *
     * @param integer $eventId
     * @param string $form
     *
     * @return void
     */
    public static function makeFormRepeatingForEvent($eventId, $form) {
        global $Proj;
        if ($Proj->isRepeatingEvent($eventId)) {
            self::makeEventNonRepeating($eventId);
        }
        if (!$Proj->isRepeatingForm($eventId, $form)) {
            // Make this form as repeatable with eventId as project is longitudinal
            $sql = "INSERT INTO redcap_events_repeat (event_id, form_name) VALUES ($eventId, '" . db_escape($form) . "');";
            db_query($sql);
        }
    }

    /**
     * Return HTML of drowpdown box "Copy below settings to:" for different sections
     *
     * @param string $form
     * @param $currentEventId integer From Event ID
     * @param $section string optional|schedules|activetasks
     *
     * @return string
     */
    public static function getCopyToDropdownHTML($form, $currentEventId, $section) {
        $events = self::getEventsList($form);
        $options = $html = '';
        if (count($events) > 2) {
            $options .= '<span id="select_all_links" style="font-weight: normal; font-size: 11px; padding-left: 10px;">
                            <a href="javascript:;" style="font-size:10px;" onclick="selectAllEvents(1, \''.$section.'\', '.$currentEventId.')">'.RCView::tt('data_export_tool_52').'</a> &nbsp;|&nbsp;
                            <a href="javascript:;" style="font-size:10px;" onclick="selectAllEvents(0, \''.$section.'\', '.$currentEventId.')">'.RCView::tt('data_export_tool_53').'</a>
                        </span>';
        }
        if (count($events) > 1) {
            global $Proj;
            foreach ($events as $eventId) {
                if ($eventId != $currentEventId) {
                    $options .= "<span class='dropdown-options fs12'>
                                    <label for='opt-".$section."-".$eventId."' style='color:#800000;'>
                                        <input type='checkbox' id='opt-".$section."-".$eventId."' value='".$eventId."' style='vertical-align:middle;'> ".$Proj->eventInfo[$eventId]['name_ext']."
                                    </label>
                                </span>";
                }
            }
        }

        if ($options != '') {
            $html = RCView::div(array('style' => 'float: right; ', 'id' => 'eventsListingBtn-'.$section.'-'.$currentEventId),
                RCView::button(array('onclick' => "showBtnDropdownList(this,event,'eventsListingDiv-".$section."-".$currentEventId."'); return false;", 'class' => 'nowrap btn btn-defaultrc btn-xs dropdown-toggle fs11 ms-1 mb-1', 'style' => 'padding-top:1px;padding-left: 6px;'),
                    RCView::span(array('style' => 'vertical-align:middle;'), "<i class='fa-solid fa-copy'></i> ".RCView::tt('mycap_mobile_app_837'))) .
                // Button/drop-down options (initially hidden)
                "<div class='dropdown-menu' id='eventsListingDiv-".$section."-".$currentEventId."'>
                        ".$options."
                        <div style='text-align: center;'>
                            <input type='button' style='font-size:11px;' value='".RCView::tt_js2('asi_017')."' onclick='copyTaskSettings($currentEventId, \"".$section."\");'>                            
                        </div>
                </div>");
        }
        return $html;

    }

    /**
     * Check if schedule is valid
     *
     * @param array $details
     * @return boolean
     */
    public static function checkSchedulesForEvent($details) {
        $validSchedule = false;
        if ($details['schedule_type'] == self::TYPE_ONETIME) {
            $validSchedule = true;
        } elseif ($details['schedule_type'] == self::TYPE_INFINITE) {
            $validSchedule = true;
        } elseif ($details['schedule_type'] == self::TYPE_REPEATING) {
            if ($details['schedule_frequency'] == self::FREQ_DAILY) {
                $validSchedule = true;
            } elseif ($details['schedule_frequency'] == self::FREQ_WEEKLY) {
                if (is_numeric($details['schedule_interval_week']) && in_array($details['schedule_interval_week'], range(1,7))) {
                    if ($details['schedule_days_of_the_week'] != '') {
                        $validSchedule = true;
                    }
                }
            } elseif ($details['schedule_frequency'] == self::FREQ_MONTHLY) {
                if (is_numeric($details['schedule_interval_month']) && in_array($details['schedule_interval_month'], range(1,12))) {
                    if ($details['schedule_days_of_the_month'] != '') {
                        $validSchedule = true;
                    }
                }
            }
        } elseif ($details['schedule_type'] == self::TYPE_FIXED) {
            $validSchedule = true;
        }
        if ($details['schedule_type'] == self::TYPE_REPEATING || $details['schedule_type'] == self::TYPE_INFINITE) {
            if ($details['schedule_ends'] != "" || is_null($details['schedule_ends'])) {
                if ($details['schedule_ends'] == Task::ENDS_NEVER) {
                    $validSchedule = true;
                } else {
                    $ends = explode(",", $details['schedule_ends']);
                    foreach ($ends as $end) {
                        if (in_array($end, array(Task::ENDS_AFTERCOUNT, Task::ENDS_AFTERDAYS, Task::ENDS_ONDATE))) {
                            $validSchedule = true;
                        } else {
                            $validSchedule = false;
                        }
                    }
                }
            }
        }

        return $validSchedule;
    }

    /**
     * Get Error text for missing/incorrect task schedule
     *
     * @param array $forms
     *
     * @return string
     */
    public static function getMissingTaskSetupErrorTextForAll($forms)
    {
        $errorText = '';
        if (count($forms) > 0) {
            $errorText .= RCView::tt('mycap_mobile_app_846')." ";
            $errorText .= "<br>"."<b>".RCView::tt('mycap_mobile_app_845')."</b>";
            $errorText .= '<ul>';
            foreach ($forms as $form) {
                $errorText .= '<li style="padding-top: 5px;"><code>'.$form.'</code></li>';
            }
            $errorText .= '</ul>';
        }

        return $errorText;
    }

    /**
     * Fix schedules errors - if task schedule stored in DB is incorrect
     *
     * @param string $form
     * @param integer $eventId
     *
     * @return void
     */
    public static function fixSchedulesForEvent($form, $eventId = '') {
        global $myCapProj, $Proj;
        $taskId = $myCapProj->tasks[$form]['task_id'];

        if (!$Proj->longitudinal || $eventId == '') {
            $eventId = $Proj->firstEventId;
        }
        $sql = "UPDATE redcap_mycap_tasks_schedules SET schedule_type = '" . self::TYPE_INFINITE . "', schedule_frequency = NULL, schedule_interval_week = NULL,
                                schedule_days_of_the_week = NULL, schedule_interval_month = NULL, schedule_days_of_the_month = NULL, schedule_days_fixed = NULL, 
                                schedule_relative_offset = NULL, schedule_ends = '" . self::ENDS_NEVER . "', schedule_end_count = NULL, schedule_end_after_days = NULL, schedule_end_date = NULL
                        WHERE task_id = '".$taskId."' AND event_id = '".$eventId."'";
        db_query($sql);
    }

    /**
     * Merges PROMIS battery instruments with the instrument list from the REDCap online designer. Intent is to
     * determine which instruments belong to the same battery (group) and in which position each instrument falls
     * within the battery. Returns a structure:
     * [
     *   'promis_instrument_a' => BatteryInstrument(
     *     'batteryPosition' = 1,
     *     'instrumentPosition' = 1,
     *     'title' = 'PROMIS Instrument A',
     *      'firstInstrument' = 'promis_instrument_a'
     *   ],
     *   'promis_instrument_b' => BatteryInstrument(
     *     'batteryPosition' = 1,
     *     'instrumentPosition' = 2,
     *     'title' = 'PROMIS Instrument B',
     *       'firstInstrument' = 'promis_instrument_a'
     *   ],
     *   'another_instrument_x' => BatteryInstrument(
     *     'batteryPosition' = 2,
     *     'instrumentPosition' = 1,
     *     'title' = 'Another instrument X',
     *       'firstInstrument' = 'promis_instrument_a'
     *   ],
     * ]
     *
     * @param $pid
     * @return array
     */
    public static function batteryInstrumentsInSeriesPositions()
    {
        global $Proj;
        $instruments = $Proj->forms;

        $batteryInstruments = PromisApi::batteryInstrumentsSeries();
        if (!count($batteryInstruments)) {
            return [];
        }
        $retVal = [];
        foreach ($batteryInstruments as $form => $form_arr) {
            $retVal[$form]['batteryPosition'] = $form_arr['batteryPosition'];
            $retVal[$form]['instrumentPosition'] = $Proj->forms[$form]['form_number'];
            $retVal[$form]['firstInstrument'] = $form_arr['firstInstrument'];
            $retVal[$form]['title'] = $Proj->forms[$form]['menu'];
        }
        return $retVal;
    }

    /**
     * Gets a MyCap task title by form; returns false if not found
     * @param string|int $project_id 
     * @param string $form 
     * @return string|false  
     * @throws Exception 
     */
    public static function getTaskTitleByForm($project_id, $form) {

        $sql = "SELECT task_title FROM redcap_mycap_tasks WHERE form_name = ? AND project_id = ?";
        $q = db_query($sql, [$form, $project_id]);
        if (db_num_rows($q) != 1) return false;
        $row = db_fetch_assoc($q);
        return $row['task_title'] ?? '';
    }

    /**
     * Updates a MyCap task title in the tasks table
     * @param string|int $project_id 
     * @param string $form 
     * @param string $title 
     * @return bool
     */
    public static function setTaskTitleByForm($project_id, $form, $title) {
        // Get current title
        $current_title = self::getTaskTitleByForm($project_id, $form);
        if ($current_title === false) return false;
        // No change? No need to do anything
        if ($current_title == $title) return true;
        // Update
        $sql = "UPDATE redcap_mycap_tasks SET task_title = ? WHERE form_name = ? AND project_id = ?";
        $q = db_query($sql, [$title, $form, $project_id]);
        if ($q) {
            // Logging
            \Logging::logEvent($sql, "redcap_mycap_tasks", "MANAGE", $form, 
                "form_name = '".db_escape($form)."'", "Modify MyCap Task Title");
        }
        return $q;
    }
  
    /**
     * Fix all errors - if PROMIS or subsequent instruments are non-repeatable for selected events, make it repeatable :: NON-LONGITUDINAL PROJECTS
     *
     * @param string $form
     *
     * @return void
     */
    public static function fixRepeatingPromisFormIssues($form) {
        global $Proj;
        $isBatteryInstrument = false;
        $batteryInstrumentsList = self::batteryInstrumentsInSeriesPositions();
        if (array_key_exists($form, $batteryInstrumentsList)) {
            $isBatteryInstrument = true;
        }

        if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
            $all_instruments = array_keys($batteryInstrumentsList);
        }
        foreach ($all_instruments as $instrument) {
            if (!$Proj->isRepeatingForm($Proj->firstEventId, $instrument)) {
                // Make this form as repeatable with default eventId as project is classic
                $sql = "INSERT INTO redcap_events_repeat (event_id, form_name) 
                            VALUES ({$Proj->firstEventId}, '" . db_escape($instrument) . "')";
                db_query($sql);
            }
        }
    }

    /**
     * Get list of form fields to add for PROMIS measures upon enabling for MyCap
     * @param string $form
     *
     * @return array
     */
    public static function getMtbPromisFormFields($form) {
        $hide_on_survey_annotation = " @HIDDEN-SURVEY";
        $fieldArr[] = array('field_name' => $form.'_uuid',
                            'field_label' => 'UUID',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_taskdata',
                            'field_label' => 'taskData',
                            'field_type' => 'file',
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_jsonexport',
                            'field_label' => 'jsonExport',
                            'field_type' => 'file',
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_starttime',
                            'field_label' => 'startTime',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_endtime',
                            'field_label' => 'endTime',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_status',
                            'field_label' => 'taskStatus',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_testversion',
                            'field_label' => 'testVersion',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_locale',
                            'field_label' => 'locale',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_starttheta',
                            'field_label' => 'startTheta',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_finaltheta',
                            'field_label' => 'finalTheta',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_rawscore',
                            'field_label' => 'rawScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_tscore',
                            'field_label' => 'TScore',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_setscore',
                            'field_label' => 'SE_TScore',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_item_count',
                            'field_label' => 'itemCount',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'field_annotation' => $hide_on_survey_annotation);

        $fieldArr[] = array('field_name' => $form.'_skip_count',
                            'field_label' => 'skipcount',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'field_annotation' => $hide_on_survey_annotation);

        return $fieldArr;
    }

    /**
     * Add form fields for PROMIS measures
     *
     * @param string $form
     *
     * @return void
     */
    public static function addMtbPromisFormFields($form)
    {
        global $Proj, $draft_mode, $status;
        if ($draft_mode != '1' && $status > 0)  return;

        $fieldsArr = self::getMtbPromisFormFields($form);
        if (count($fieldsArr) > 0) {
            if ($status > 0) {
                $Proj->loadMetadataTemp();
            } else {
                $Proj->loadMetadata();
            }
            $projectDesigner = new ProjectDesigner($Proj);

            foreach ($fieldsArr as $field) {
                $field['field_name'] = ActiveTask::getNewFieldName($field['field_name']);
                $projectDesigner->createField($form, $field);
            }
        }
    }
}
