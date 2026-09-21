<?php

declare(strict_types=1);

class FormDisplayLogicSetup extends FormDisplayLogic
{
    private $project;

    private static $API_call_prefix = 'FormDisplayLogicSetup';

    private static $FDL_db_fields = array(
        'redcap_projects' => array(
            'hide_filled_forms',
            'hide_disabled_forms',
            'form_activation_survey_autocontinue'
        ),
        'redcap_form_display_logic_conditions' => array(
            'control_id',
            'project_id',
            'control_condition'
        ),
        'redcap_form_display_logic_targets' => array(
            'control_id',
            'form_name',
            'event_id'
        )
    );

    private static $FDL_import_fields = [
        'form_name',
        'event_name',
        'control_condition'
    ];

    private $httpClient;

    public function __construct($project_id = null)
    {
        $this->httpClient = new \HttpClient();
        $this->project = new \Project($project_id);
        // remove 'event_name' from expected fields list in non-longitudinal projects
        if (!$this->project->longitudinal) {
            self::$FDL_import_fields = array_reduce(self::$FDL_import_fields, function ($carry, $item) {
                if ($item !== 'event_name') {
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

    public function isFormDisplayLogicEnabled(): bool
    {
        return $this::FormDisplayLogicEnabled($this->project->getId());
    }

    /**
     * import settings from CSV
     * in case of error rollback every change made to the database
     *
     * @return void
     */
    public function import()
    {
        global $lang, $Proj;
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


        $all_FDL_settings = FileManager::csvToAssociativeArray($csv);

        // remove empty rows
        foreach ($all_FDL_settings as $idx => $FDL_setting) {
            if (!is_array($FDL_setting) || empty(trim(implode("", array_values($FDL_setting))))) {
                unset($all_FDL_settings[$idx]);
            }
        }
        // check file is empty
        if (empty($all_FDL_settings[0])) {
            $this->sendFeedbackResponse("File is empty - Delete existing FDL Controls?", [], false, true);
        }
        // check no duplicate field names
        if (count(array_unique(array_keys($all_FDL_settings[0]))) !== count(self::$FDL_import_fields)) {
            $this->sendFeedbackResponse("One or more required fields was not found or there is more than the number of valid fields");
        }
        // check field names are valid
        foreach (array_unique(array_keys($all_FDL_settings[0])) as $setting) {
            if (!in_array($setting, self::$FDL_import_fields)) {
                $this->sendFeedbackResponse("$setting is not a valid field header");
            }
        }
        // prepare data for entry into database
        $post = array();
        $existingControls = self::getControlsByProjectId($this->project->getId());
        // mark existing controls for deletion
        $post['deleted_ids'] = json_encode(array_reduce($existingControls, function ($carry, $control) {
            $carry[] = $control['control_id'];
            return $carry;
        }, []));
        // set FDL conditions
        foreach ($all_FDL_settings as $fdlSetting) {
            $this->buildAsIfPostData($post, $fdlSetting);
        }
        // submit data for entry into database
        $f = $s = [];
        try {
            self::saveConditionsSettings($post, $f, $s);
        } catch (\Exception $e) {
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
            $this->sendFeedbackResponse($e->getMessage());
        }
        db_query("COMMIT");
        db_query("SET AUTOCOMMIT=1");
        // Log the event
        Logging::logEvent("", "redcap_form_render_skip_logic", "MANAGE", $Proj->getId(), "project_id = ".$Proj->getId(), "Edit settings for Form Render Skip Logic");
        // Format response appropriately (do some reformatting)
        $response = self::getControlsByProjectId($this->project->getId());
        foreach ($response as $key=>$attr) {
            foreach ($attr['form-name'] as $key2=>$thisform) {
                list ($thisform, $thiseventid) = explode("-", $thisform);
                $response[$key]['form-name'][$key2] = $thisform." ";
                if ($this->project->longitudinal) {
                    $response[$key]['form-name'][$key2] .= " (".($thiseventid == '' ? $lang['dataqueries_136'] : $this->project->getUniqueEventNames($thiseventid)).")";
                }
            }
        }
        $this->sendFeedbackResponse('', $response);
    }

    public function export(): void
    {
        $data_rows = array();
        $fdlTableValues = self::getFormDisplayLogicTableValues($this->project->getId());
        foreach ($fdlTableValues['controls'] as $count => $control) {
            foreach (self::$FDL_import_fields as $setting) {
                if (!isset($this_row_this_form[$count])) {
                    $this_row_this_form[$count] = [];
                }
                if ($setting === 'form_name') {
                    foreach ($control['form-name'] as $k => $fName) {
                        $form_event_name = explode('-', $fName);
                        $this_row_this_form[$count][$k][$setting] = $form_event_name[0];
                        // add 'event_name' column if longitudinal project
                        if ($this->project->longitudinal) {
                            $this_row_this_form[$count][$k]['event_name'] = Event::getEventNameById($this->project->getId(), (int)($form_event_name[1]));
                        }
                    }
                }
                if (is_array($this_row_this_form[$count][0]) && $setting === 'control_condition') {
                    foreach ($this_row_this_form[$count] as &$row) {
                        $row[$setting] = $control['control-condition'];
                        $data_rows[] = $row;
                    }
                }

            }
        }
        FileManager::exportCSV($data_rows, 'fdl_export_pid'.$this->project->getId());
    }

    public function getHelpFieldsList(): array
    {
        global $lang;
        $Proj = new Project($this->project->getId());

        $FDL_import_help_fields_list = array(
            'form_name ('.$lang['design_244'].')',
            'event_name ('.$lang['define_events_65'].')',
            'control_condition ('.$lang['asi_012'].')'
        );

        // Remove event columns for non-longitudinal projects
        foreach ($FDL_import_help_fields_list as $key => $val) {
            list ($this_col, $val2) = explode(" ", $val, 2);
            if (!$Proj->longitudinal && strpos($this_col, 'event_name') !== false) {
                unset($FDL_import_help_fields_list[$key]);
                continue;
            }
            // Style the column name as bold
            $FDL_import_help_fields_list[$key] = RCView::b($this_col)." $val2";
        }
        return $FDL_import_help_fields_list;
    }

    public function buildAsIfPostData(array &$post, array $row): void
    {
        // validate form name
        if (!isset($this->project->forms[$row['form_name']])) {
            $this->sendFeedbackResponse("{$row['form_name']} is not a valid form name!");
        }
        // validate event name
        if ($this->project->longitudinal && !$this->project->getEventIdUsingUniqueEventName($row['event_name'])) {
            if (!empty(trim($row['event_name']))) { // in longitudinal project, empty event name field in FDL import file is meant to denote that the condition applies to all events
                $this->sendFeedbackResponse("{$row['event_name']} is not a valid event name!");
            }
        }
        if (!array_key_exists("outer-list", $post)) {
            $post["outer-list"] = [];
        }
        $matchFound = false;
        foreach ($post["outer-list"] as &$existingRow) {
            if ($existingRow['control-condition'] === $row['control_condition']) {
                $existingRow['form-name'] = array_merge($existingRow['form-name'], (array)$row['form_name']);
                $matchFound = true;
                break;
            }
        }
        if (!$matchFound) {
            $logicErrors = $this->validateControlCondition($row['control_condition']);
            if ($logicErrors !== "1") {
                $this->sendFeedbackResponse("{$row['control_condition']} is not a valid control condition!");
            }
            $post['outer-list'][] = [
                'control_id' => '',
                'form-name' => (array)$row['form_name'],
                'control-condition' => $row['control_condition'],
            ];
        }
        foreach ($post['outer-list'] as &$setting) {
            foreach ($setting['form-name'] as $i => $fName) {
                if ($this->project->longitudinal) {
                    $setting['form-name'][$i] = $setting['form-name'][$i] . '-' . $this->project->getEventIdUsingUniqueEventName($row['event_name']); // blank Event Id if event_name field empty in import file.
                } else {
                    $setting['form-name'][$i] = $setting['form-name'][$i] . '-' . $this->project->firstEventId;
                }
            }
        }
    }

    protected function validateControlCondition(string $control_condition): string
    {
        $includeFormDisplayLogicControlCondition = $control_condition;
        $includeFormDisplayLogicForceMetadataTable = 1;
        ob_start();
        include(dirname(__DIR__) . '/Design/logic_validate.php');
        return ob_get_clean();
    }

    public function deleteAllFormDisplayConditions(): void
    {
        $post = array();
        // set defaults for FDL global settings
        $post['prevent_hiding_filled_forms'] = false;
        $post['hide_disabled_forms'] = false;
        $post['enable_survey_support'] = false;
        $post['enable_mycap_support'] = false;
        // delete all controls
        $f = $s = [];
        try {
            self::saveConditionsSettings($post, $f, $s);
        } catch (Exception $e) {
            $this->sendFeedbackResponse($e->getMessage());
        }
    }

    private function sendFeedbackResponse(string $responseMessage, array $data = [], bool $error = true, bool $warning = false): void
    {
        $response = array();
        if ($error && empty($data)) {
            $response['error'] = true;
        } elseif ($warning) {
            // send it as warning message instead
            $response['warning'] = true;
        } else {
            $response['success'] = true;
            $response['data'] = $data;
        }
        $response['message'] = $responseMessage;
        $this->httpClient::printJSON($response);
    }

    /**
     * @throws Exception
     */
    public function listen(): void
    {
        if(isset($_GET[self::$API_call_prefix."-export"]))
        {
            $this->export();
        } elseif (isset($_POST[self::$API_call_prefix."-import"])) {
            $this->import();
        }
    }
}
