<?php

declare(strict_types=1);

class SurveyQueueSetup
{
    private $project;

    private $surveyQueue;

    /** @var bool */
    private $surveyQueueExportEnabled;

    private static $API_call_prefix = 'SurveyQueueSetup';

    private static $SQS_db_fields = [
        'sq_id',
        'survey_id',
        'event_id',
        'active',
        'auto_start',
        'condition_surveycomplete_survey_id',
        'condition_surveycomplete_event_id',
        'condition_andor',
        'condition_logic'
    ];

    private static $csv_export_display_order = [
        'form_name',
        'event_name',
        'active',
        'condition_surveycomplete_form_name',
        'condition_surveycomplete_event_name',
        'condition_andor',
        'condition_logic',
        'auto_start'
    ];

    private static $SQS_import_fields = [];

    private $httpClient;

    function __construct($project_id = null)
    {
        $this->httpClient = new HttpClient();
        $this->project = new \Project($project_id);
        $this->surveyQueue = \Survey::getProjectSurveyQueue(true, true, $project_id);
        $this->surveyQueueExportEnabled = !empty($this->surveyQueue);
        self::$SQS_import_fields = array_filter(array_map(function ($field) {
            if ($field === 'sq_id') {
                return null;
            }
            $map = [
                'survey_id' => 'form_name',
                'event_id' => 'event_name',
                'condition_surveycomplete_survey_id' => 'condition_surveycomplete_form_name',
                'condition_surveycomplete_event_id' => 'condition_surveycomplete_event_name',
            ];
            return $map[$field] ?? $field;
        }, self::$SQS_db_fields));

        // remove 'event_name' and 'condition_surveycomplete_event_name' from expected fields list in non-longitudinal projects
        if (!$this->project->longitudinal) {
            self::$csv_export_display_order = array_reduce(self::$csv_export_display_order, function ($carry, $item) {
                if (!in_array($item, ['event_name', 'condition_surveycomplete_event_name'])) {
                    $carry[] = $item;
                }
                return $carry;
            }, []);
            self::$SQS_import_fields = array_reduce(self::$SQS_import_fields, function ($carry, $item) {
                if (!in_array($item, ['event_name', 'condition_surveycomplete_event_name'])) {
                    $carry[] = $item;
                }
                return $carry;
            }, []);
        }
    }

    /**
     * @param $httpClient
     * @return void
     *  This method allows for setting different client during testing
     */
    public function setHttpClient($httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Indicates whether survey queue export is enabled
     * @return bool 
     */
    public function isSurveyQueueExportEnabled()
    {
        return $this->surveyQueueExportEnabled;
    }

    /**
     * import settings from CSV
     * in case of error rollback every change made to the database
     *
     * @return void
     */
    public function import()
    {
        global $lang;

        // get the uploaded files
        $files = FileManager::getUploadedFiles();
        // check that it's just one file
        if(count($files)>1)
        {
            throw new Exception("only one file can be uploaded", 1);
        }
        else if (count($files)==0)
        {
            throw new Exception("no file uploaded", 1);
        }
        $file = array_pop($files);
        // get the settings from the csv file
        $csv = FileManager::readCSV($file['tmp_name'], 0, User::getCsvDelimiter());


        $all_SQS_settings = FileManager::csvToAssociativeArray($csv);
        $response = array(
            'success' => true,
            'data' => array(),
        );

        // remove empty rows
        foreach ($all_SQS_settings as $idx => $surveySettings) {
            if (empty(trim(implode("", array_values($surveySettings))))) {
                unset($all_SQS_settings[$idx]);
            }
        }

        if (empty($all_SQS_settings[0])) {
            $response = array(
                'warning' => true,
                'message' => RCView::tt("sqs_006")
            );
            $this->httpClient::printJSON($response);
        }

        $errors = [];
        if (count(array_unique(array_keys($all_SQS_settings[0]))) !== count(self::$SQS_import_fields)) {
            $errors[] = "One or more required fields was not found or there is more than the number of valid fields";
        }
        foreach (array_unique(array_keys($all_SQS_settings[0])) as $setting) {
            if (!in_array($setting, self::$SQS_import_fields)) {
                $errors[] = "$setting is not a valid field header";
            }
        }
        /*
         map $all_SQS_settings to below format, and check that each combination of (form_name, event_name) in import file is unique.
              [
                  form_name_1 => [
                      event_name_1,
                      event_name_2,
                      ...,
                  ],
                  form_name_2 => [
                      event_name_1,
                      event_name_2,
                      ...,
                  ],
                  ...,
              ]
         */
        array_reduce($all_SQS_settings, function ($result, $row) use (&$errors) {
            $formName = $row['form_name'];
            $eventName = $row['event_name'] ?? 'any_event_name'; // event name is the same for non-longitudinal projects
            if (!isset($result[$formName])) {
                $result[$formName] = [];
            }
            if (in_array($eventName, $result[$formName])) {
                $errors[] = "Event names must be distinct for each survey: $eventName";
            }
            $result[$formName][] = $eventName;
            return $result;
        }, []);

        // handle all errors
        if (!empty($errors)) {
            $response = array(
                'error' => true,
                'message' => $this->stringifyErrors($errors)
            );
            $this->httpClient::printJSON($response);
        }
        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");
        foreach ($all_SQS_settings as $settings) {
            try {
                // convert event and survey names to ids
                $event_id = $this->project->getEventIdUsingUniqueEventName($settings['event_name'] ?? null);
                if ($event_id == false) {
                    if ($this->project->longitudinal) {
                        throw new ValidationException("Invalid event_name {$settings['event_name']}");
                    } else {
                        $event_id = $this->project->firstEventId;
                    }
                    $settings['condition_surveycomplete_event_name'] = Event::getEventNameById($this->project->getId(), $event_id);
                }
                // if we have reached this point then we know we'll always have an even_id and therefore an event_name (a random name is applied by REDCap for non-longitudinal projects)
                $survey_id = Survey::getSurveyId($settings['form_name']);
                $surveyCompleteSurveyId = Survey::getSurveyId($settings['condition_surveycomplete_form_name'] ?? null);
                $surveyCompleteEventId = $this->project->getEventIdUsingUniqueEventName($settings['condition_surveycomplete_event_name'] ?? null);
                $settings['condition_surveycomplete_survey_id'] = $surveyCompleteSurveyId !== "" ? $surveyCompleteSurveyId : null;
                $settings['condition_surveycomplete_event_id'] = $surveyCompleteEventId !== false ? $surveyCompleteEventId : null;
                $settings['condition_andor'] = strtoupper($settings['condition_andor']);
                if (!in_array($settings['condition_andor'], ["AND", "OR"])) {
                    throw new ValidationException("conditional operator must be AND/OR");
                }
                $conditionSurveyCompleteAND = !empty($settings['condition_surveycomplete_survey_id']) && !empty($settings['condition_surveycomplete_event_id']);
                $conditionSurveyCompleteEmpty = !$conditionSurveyCompleteAND;
                if (empty($survey_id) || ($conditionSurveyCompleteEmpty && empty($settings['condition_logic']))) {
                    $names = rtrim((empty($survey_id) ? "\n form_name : {$settings['form_name']}, " : "") . (empty($settings['condition_surveycomplete_survey_id']) ? "\n condition_surveycomplete_form_name : {$settings['condition_surveycomplete_form_name']}, " : " " ) . (empty($settings['condition_surveycomplete_event_id']) ? "\n condition_surveycomplete_event_name : {$settings['condition_surveycomplete_event_name']}, " : " "), ", ");
                    throw new ValidationException("Invalid \n $names");
                }
                $survey_title = Survey::getSurveyPropertyById($this->project->getId(), (int)$survey_id, 'title');
                $event_title = Event::getEventNameById($this->project->getId(), $event_id);
                $survey_active = (int)$settings['active'];
                if ($survey_active < 0 || $survey_active > 1) {
                    throw new ValidationException("Invalid active: {$settings['active']}");
                }
                if ($settings['auto_start'] != "1" && $settings['auto_start'] != "0") {
                    throw new ValidationException("Invalid autostart: {$settings['auto_start']}");
                }
                if ( ($conditionSurveyCompleteEmpty || $conditionSurveyCompleteAND) && !LogicTester::isValid($settings['condition_logic']) ) {
                    throw new ValidationException("Invalid logic: {$settings['condition_logic']}");
                }
                // prepare array for setting SQS record
                if (isset($settings['event_name'])) unset($settings['event_name']);
                if (isset($settings['condition_surveycomplete_event_name'])) unset($settings['condition_surveycomplete_event_name']);
                unset($settings['form_name']);
                unset($settings['condition_surveycomplete_form_name']);
                $settings['survey_id'] = $survey_id;
                $settings['event_id'] = $event_id;

                // Set SQS for this row
                $this->setSQSRecord($settings);
                $response['data'][] = compact(array('survey_id', 'event_id', 'survey_title', 'event_title'));
            } catch (\Exception $e) {
                if (!($e instanceof ValidationException)) {
                    db_query("ROLLBACK");
                }
                db_query("SET AUTOCOMMIT=1");
                $error = $e->getMessage();
                $response = array(
                    'error' => true,
                    'message' => $error
                );
                $this->httpClient::printJSON($response);
            }
        }
        db_query("COMMIT");
        db_query("SET AUTOCOMMIT=1");
        // Log the event
        Logging::logEvent("", "redcap_surveys_queue", "MANAGE", $this->project->getId(), "project_id = ".$this->project->getId(), "Upload settings for survey queue");
        // Return response
        $this->httpClient::printJSON($response);
    }

    /**
     * 
     * @param array $record 
     * @return void 
     */
    private function setSQSRecord($record)
    {
        $keyOrder = self::$SQS_db_fields;
        unset($keyOrder[0]); // take off 'sq_id' field

        if (count(array_unique(array_keys($record))) !== count($keyOrder)) {
            throw new Exception("The number of parameters provided does not math the required number of parameters for the query.");
        }
        $settings = [];
        foreach ($keyOrder as $key) {
            $settings[$key] = $record[$key];
        }
        $query = "REPLACE INTO redcap_surveys_queue ( ". implode(',', $keyOrder). ") VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        db_query($query, $settings);

        if($error = db_error()){
            throw new \Exception($query.': '.$error);
        }

        // Logging
        // $existingSchedule = (db_affected_rows() == 2);
        // $logDescrip = ($existingSchedule) ? "Edit settings for survey queue setup" : "Add settings for survey queue setup";
        // Logging::logEvent($query,"redcap_surveys_queue","MANAGE",$this->project->getId(),"survey_id = {$settings['survey_id']}\nevent_id = {$settings['event_id']}",$logDescrip);
    }

    /**
     * Deletes all records in the survey queue table
     * @return void 
     */
    public function deleteAllSQSRecords()
    {
        $sql = "DELETE FROM redcap_surveys_queue 
                WHERE survey_id IN (
                    SELECT survey_id from redcap_surveys WHERE project_id = ?
                )";
        db_query($sql, [$this->project->getId()]);
        // Logging
        Logging::logEvent($sql, "redcap_surveys_queue", "MANAGE", $this->project->getId(), "survey_id = *\nevent_id = *", "Delete all survey queue settings");
    }

    /**
     * 
     * @return void 
     */
    public function export()
    {
        $data_rows = array();
        foreach ($this->surveyQueue as $survey_id => $event) {
            foreach($event as $event_id => $settings) {
                // convert event and survey ids to name
                $form_name = \Survey::getSurveyPropertyById($this->project->getId(), (int)$survey_id, 'form_name');
                $event_name = Event::getEventNameById($this->project->getId(), (int)$event_id);
                $condition_surveycomplete_form_name = \Survey::getSurveyPropertyById($this->project->getId(), (int)$settings['condition_surveycomplete_survey_id'], 'form_name');
                $condition_surveycomplete_event_name = Event::getEventNameById($this->project->getId(), $settings['condition_surveycomplete_event_id']);

                // remove columns with ids from the settings
                unset($settings['sq_id']);
                unset($settings['condition_surveycomplete_survey_id']);
                unset($settings['condition_surveycomplete_event_id']);

                // add the new columns to the exported settings
                $this_row = array_merge(compact('form_name', 'event_name', 'condition_surveycomplete_form_name', 'condition_surveycomplete_event_name'), $settings);
                // Remove event_name columns if non-longitudinal
                if (!$this->project->longitudinal) {
                    foreach (array_keys($this_row) as $key) {
                        if (strpos($key, 'event_name') !== false) {
                            unset($this_row[$key]);
                        }
                    }
                }
                // Add to rows
                $data_rows[] = $this_row;
            }
        }
        $data_rows = array_map(function ($row) {
            return array_replace(array_flip(self::$csv_export_display_order), $row);
        }, $data_rows);
        // Log the event
        Logging::logEvent("", "redcap_surveys_queue", "MANAGE", $this->project->getId(), "project_id = ".$this->project->getId(), "Download survey queue settings");
        // Export the CSV file
        $filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($this->project->project['app_title'], ENT_QUOTES)))), 0, 30)
                  . "_SurveyQueue_".date("Y-m-d_Hi");
        FileManager::exportCSV($data_rows, $filename);
    }

    /**
     * 
     * @return string[] 
     */
    public function getHelpFieldsList()
    {
        global $lang;
        $Proj = new Project($this->project->getId());
        $SQSimport_help_fields_list = array(
            'form_name ('.$lang['design_244'].')',
            'event_name ('.$lang['define_events_65'].')',
            'active ('.$lang['asi_011'].')',
            'auto_start ('.$lang['survey_999'].')',
            'condition_surveycomplete_form_name ('.$lang['asi_008'].')',
            'condition_surveycomplete_event_name ('.$lang['asi_009'].')',
            'condition_andor (AND, OR)',
            'condition_logic ('.$lang['asi_012'].')'
        );

        // Remove event columns for non-longitudinal projects
        foreach ($SQSimport_help_fields_list as $key => $val) {
            list ($this_col, $val2) = explode(" ", $val, 2);
            if (!$Proj->longitudinal && strpos($this_col, 'event_name') !== false) {
                unset($SQSimport_help_fields_list[$key]);
                continue;
            }
            // Style the column name as bold
            $SQSimport_help_fields_list[$key] = RCView::b($this_col)." $val2";
        }
        return $SQSimport_help_fields_list;
    }

    /**
     * 
     * @return void 
     */
    public function listen()
    {
        if(isset($_GET[self::$API_call_prefix."-export"]))
        {
            $this->export();
        } elseif (isset($_POST[self::$API_call_prefix."-import"])) {
            $this->import();
        }
    }

    /**
     * 
     * @param array $errors 
     * @param string $message 
     * @return string 
     */
    private function stringifyErrors($errors = [], $message = '')
    {
        if (!empty($errors) && isset($_POST[self::$API_call_prefix."-import"])) {
            $message =  $message."<ul style='margin-top:10px;font-weight:bold;'><li>".implode("</li><li>", array_unique($errors))."</li></ul>";
        } else {
            if (!empty($errors))
                $message = implode(", ", array_unique($errors));
        }
        return $message;
    }

}