<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Handler;

use MultiLanguageManagement\MultiLanguage;
use Vanderbilt\REDCap\Classes\MyCap\Annotation;
use Vanderbilt\REDCap\Classes\MyCap\Api\DB\ParticipantDB;
use Vanderbilt\REDCap\Classes\MyCap\Api\Field\BarcodeCaptureField;
use Vanderbilt\REDCap\Classes\MyCap\Api\Field\ImageCaptureField;
use Vanderbilt\REDCap\Classes\MyCap\Api\Field\ScaleField;
use Vanderbilt\REDCap\Classes\MyCap\Api\Field\TextChoiceField;
use Vanderbilt\REDCap\Classes\MyCap\Api\Field\VideoCaptureField;
use Vanderbilt\REDCap\Classes\MyCap\Api\Response;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\MyCapApi;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\Error\ProjectHandlerError;
use Vanderbilt\REDCap\Classes\MyCap\Api\DB\Project;
use Vanderbilt\REDCap\Classes\MyCap\Participant;
use Vanderbilt\REDCap\Classes\MyCap\ValidationType;

/**
 * MyCap API Project actions
 */
class ProjectHandler
{

    public static $actions = [
        "GET_STUDY_CONFIG" => "getStudyConfig",
        "GET_STUDY_IMAGES" => "getStudyImages",
        "GET_STUDY_FILE" => "getStudyFile",
        "GET_STUDY_TRANSLATIONS" => "getStudyTranslations",
    ];
    /**
     * REDCap fields that contain any of these strings within the "field_annotation" attribute will be ignored
     * (not included)
     */
    private static $fieldAnnotationIgnore = [
        "@HIDDEN", // This is a REDCap annotation that indicates that a field should be hidden in the instrument
        Annotation::FIELD_HIDDEN,
        Annotation::TASK_UUID,
        Annotation::TASK_STARTDATE,
        Annotation::TASK_ENDDATE,
        Annotation::TASK_SCHEDULEDATE,
        Annotation::TASK_ISDELETED,
        Annotation::TASK_STATUS,
        Annotation::TASK_SUPPLEMENTALDATA,
        Annotation::TASK_SERIALIZEDRESULT
    ];
    /**
     * REDCap fields that are unsupported in the app
     * @var array
     */
    private static $fieldTypeUnsupported = [
        'calc',
        'sql'
    ];

    /**
     * Get project config given a project code. E.g. P-00000000000000000000
     *
     * @param array $data
     */
    public function getConfig($data)
    {
        MyCapApi::validateParameters(
            $data,
            ["stu_code"]
        );

        $stu_code = $data['stu_code'];

        try {
            $myProj = new Project();
            $projects = $myProj->loadByCode($stu_code);

            $myCap = new MyCap($projects['project_id']);
            $fields = $myCap->getAllMyCapFormFields();

            $config = $myCap->config;

            $configArr = json_decode($config, true);

            // Add new flags based on participant code - if participant is deleted and is disabled
            if (isset($data['par_code'])) {
                $par_code = $data['par_code'];
                // Add new flags based on participant code - if participant is deleted and is disabled
                $parDetails = Participant::getParticipantDetails($par_code);
                if (empty($parDetails)) {
                    $parInfo['par_deleted'] = 1;
                } else {
                    $parInfo['par_deleted'] = 0;
                    $parInfo['par_disabled'] = $parDetails[$par_code]['is_deleted'];
                }
                $configArr['parDetails'] = $parInfo;

                // Load Participant
                $participant = new ParticipantDB();
                $participants = $participant->loadByCode($par_code);

                $Proj = new \Project($projects['project_id']);

                // Get Record ID
                $record = $participants['record'];
                // Get Project Data Access Groups list
                $dags = $Proj->getGroups();
                // Get DagId assigned to a record
                $recordDag = empty($dags) ? false : \Records::getRecordGroupId($projects['project_id'], $record);

                // Show all pages (not assigned to any DAG) to all participant otherwise show only DAG specific pages at app-side
                if (!empty($configArr['pages'])) {
                    $idx = 0;
                    foreach ($configArr['pages'] as $pageIndex => $pageArr) {
                        if (is_null($pageArr['dag_id']) || $pageArr['dag_id'] == $recordDag) {
                            unset($pageArr['dag_id']);
                            $newPageArr[$idx] = $pageArr;
                            $idx++;
                        }
                    }
                    $configArr['pages'] = $newPageArr;
                }

                // Show all contacts (not assigned to any DAG) to all participant otherwise show only DAG specific contacts at app-side
                if (!empty($configArr['contacts'])) {
                    $idx = 0;
                    foreach ($configArr['contacts'] as $contactIndex => $contactArr) {
                        if (is_null($contactArr['dag_id']) || $contactArr['dag_id'] == $recordDag) {
                            unset($contactArr['dag_id']);
                            $newContactArr[$idx] = $contactArr;
                            $idx++;
                        }
                    }
                    $configArr['contacts'] = $newContactArr;
                }

                // Show all links (not assigned to any DAG) to all participant otherwise show only DAG specific links at app-side
                if (!empty($configArr['links'])) {
                    $idx = 0;
                    foreach ($configArr['links'] as $linkIndex => $linkArr) {
                        if (is_null($linkArr['dag_id']) || $linkArr['dag_id'] == $recordDag) {
                            unset($linkArr['dag_id']);
                            $newLinkArr[$idx] = $linkArr;
                            $idx++;
                        }
                    }
                    $configArr['links'] = $newLinkArr;
                }
            }

            $isFDLSet = ($Proj->project['form_activation_mycap_support'] == 1);

            if ($isFDLSet && isset($data['par_code'])) {
                // Load Participant
                $participant = new ParticipantDB();
                $participants = $participant->loadByCode($data['par_code']);
                $record = $participants['record'];
            }
            if ($Proj->longitudinal) {
                if (isset($data['par_code'])) {
                    $par_code = $data['par_code'];
                    // Load Participant
                    $participant = new ParticipantDB();
                    $participants = $participant->loadByCode($par_code);
                    $allowedArm = $Proj->eventInfo[$participants['event_id']]['arm_num'];

                    if (!empty($configArr['tasks'])) {
                        foreach ($configArr['tasks'] as $taskIndex => $taskArr) {
                            $form_name = $taskArr['identifier'];
                            if (!empty($taskArr['longitudinalSchedule'])) {
                                foreach ($taskArr['longitudinalSchedule'] as $scheduleIndex => $schedule) {
                                    $eventId = $schedule['identifier'];
                                    if ($isFDLSet) {
                                        $formsAccess = \FormDisplayLogic::getAccess('record_status_dashboard', $record, $eventId, $form_name, 1, [], $projects['project_id']);
                                    } else {
                                        $formsAccess[$record][$eventId][$form_name] = 1;
                                    }

                                    //$configArr['tasks'][$taskIndex]['longitudinalSchedule'][$scheduleIndex]['show'] = true;
                                    if ((isset($formsAccess[$record][$eventId][$form_name]) && $formsAccess[$record][$eventId][$form_name] != 1)
                                        || $schedule['arm'] != $allowedArm) {
                                        // Exclude longitudinal schedule if not from arm that participant belongs to OR not accessible as per FDL set
                                        unset($configArr['tasks'][$taskIndex]['longitudinalSchedule'][$scheduleIndex]);
                                        // Future Requirement - Include this task schedule with "show" flag set to "false" - If not from arm that participant belongs to OR not accessible as per FDL set
                                        //$configArr['tasks'][$taskIndex]['longitudinalSchedule'][$scheduleIndex]['show'] = false;
                                    }
                                }
                                $newScheduleArr = array();
                                $idx = 0;
                                foreach($configArr['tasks'][$taskIndex]['longitudinalSchedule'] as $ts) {
                                    $newScheduleArr[$idx] = $ts;
                                    $idx++;
                                }
                                $configArr['tasks'][$taskIndex]['longitudinalSchedule'] = $newScheduleArr;
                            }
                        }
                    }
                }
            } else {
                if ($isFDLSet && isset($data['par_code'])) {
                    $eventId = $Proj->firstEventId;
                    $formsAccess = \FormDisplayLogic::getAccess('record_status_dashboard', $record, $eventId, null, 1, [], $projects['project_id']);

                    foreach ($configArr['tasks'] as $taskIndex => $taskArr) {
                        $form_name = $taskArr['identifier'];
                        //$configArr['tasks'][$taskIndex]['show'] = true;
                        if (isset($formsAccess[$record][$eventId][$form_name]) && $formsAccess[$record][$eventId][$form_name] != 1) {
                            // Exclude this task if not accessible as per FDL set
                            unset($configArr['tasks'][$taskIndex]);
                            // Future Requirement - Include this task schedule with "show" flag set to "false" - If not from arm that participant belongs to OR not accessible as per FDL set
                            //$configArr['tasks'][$taskIndex]['show'] = false;
                        }
                    }
                }
            }

            // Add Server configuration info
            $serverInfo['redcapVersion'] = REDCAP_VERSION;
            $serverInfo['phpVersion'] = PHP_VERSION;
            $configArr['serverConfiguration'] = $serverInfo;

            $config = json_encode($configArr);

            $mergeResults = self::mergeConfig(
                $config,
                $fields
            );

            if ($mergeResults['success']) {
                Response::sendSuccess($mergeResults['config']);
            } else {
                Response::sendError(
                    400,
                    ProjectHandlerError::INVALID_CONFIG,
                    implode(
                        ",",
                        $mergeResults['errors']
                    )
                );
            }

        } catch (\Exception $e) {
            Response::sendError(
                400,
                ProjectHandlerError::CODE_NOT_FOUND,
                $e->getMessage()
            );
        }
    }

    /**
     * Merges the project config and the field config
     *
     * @param string $config JSON project config
     * @param string $fields JSON string
     * @return array
     */
    public static function mergeConfig($config, $fields)
    {
        $ret_val = array(
            "success" => false,
            "errors" => array(),
            "config" => "{}"
        );

        $fields = json_decode($fields);
        // 1) Group redcap fields by form name and process them into a MyCap-friendly format

        $aryFormFields = self::groupFieldsByFormName($fields);

        // 2) Merge the project configuration with field configuration

        $config_json = json_decode($config);
        $ret_val["config"] = $config_json;

        if (!property_exists(
            $config_json,
            "tasks"
        )) {
            $ret_val["errors"][] = "Invalid config: Config missing property \"tasks\". ";
        } else {

            $tasks = $config_json->tasks;
            $validTasks = [];

            foreach ($tasks as $idx => $task) {
                if (!property_exists(
                    $task,
                    "fields"
                )) {
                    $ret_val["errors"][] = "Invalid config: Task missing property \"fields\". ";
                    continue;
                }

                if (!property_exists(
                    $task,
                    "identifier"
                )) {
                    $ret_val["errors"][] = "Invalid config: Task missing property \"identifier\". ";
                    continue;
                }

                if (!isset($aryFormFields[$task->identifier])) {
                    // This is NOT an error. If someone renames a REDCap instrument from "Foo" to "Foobar" then both
                    // "Foo" and "Foobar" will exist in the config. Just ignore the old "Foo" task so it won't be
                    // included
                    continue;
                }

                if (!$task->enabled) {
                    continue;
                }

                if (count($aryFormFields[$task->identifier]) == 0) {
                    // There must be at least 1 question
                    continue;
                }
                $task->fields = $aryFormFields[$task->identifier];

                $validTasks[] = $task;
            }

            $ret_val['config']->tasks = $validTasks;

        }

        if (count($ret_val["errors"]) == 0) {
            $ret_val["success"] = true;
        }
        return $ret_val;
    }

    /**
     * Groups REDCap fields by "form_name" and converts them into a MyCap-friendly format
     *
     * @param array $fields REDCap fields returned from API
     * @return array
     */
    private static function groupFieldsByFormName($fields)
    {
        $ret_val = [];

        foreach ($fields as $field) {
            $formName = $field->form_name;
            if (!isset($ret_val[$formName])) {
                $ret_val[$formName] = [];
            }

            $skip = false;

            // A REDCap instrument may contain fields that we do not want to capture via the mobile app. Exclude them.
            $field_annotation = strtoupper($field->field_annotation);
            $annotations = explode(
                " ",
                $field_annotation
            );
            foreach ($annotations as $annotation) {
                if (in_array(
                    $annotation,
                    self::$fieldAnnotationIgnore
                )) {
                    $skip = true;
                    break;
                }
            }

            if (in_array($field->field_type, self::$fieldTypeUnsupported)) {
                $skip = true;
            }
            if (!$skip) {
                $ret_val[$formName][] = self::formatFieldForMyCap($field);
            }
        }

        return $ret_val;
    }

    /**
     * Converts REDCap field metadata into a ResearchKit-friendly format
     *
     * @param object $field A REDCap field object
     * @return object
     */
    private static function formatFieldForMyCap($field)
    {
        $ret_val = [];

        $ret_val["identifier"] = $field->field_name;
        $ret_val["title"] = htmlentities($field->field_label);
        $ret_val["required"] = ($field->required_field == "y") ? true : false;

        if (strlen($field->field_note) > 0) {
            $ret_val["fieldNote"] = $field->field_note;
        }

        if (strlen($field->branching_logic) > 0) {
            $ret_val["branchingLogic"] = $field->branching_logic;
        }

        if (strlen($field->section_header) > 0) {
            $ret_val["sectionTitle"] = htmlentities($field->section_header);
        }

        switch ($field->field_type) {
            case "checkbox":
                $ret_val["answerFormat"] = "ORKTextChoiceAnswerFormat";
                $ret_val["choiceAnswerStyle"] = ".MultipleChoice";

                $choiceOptions = self::parseChoices($field->select_choices_or_calculations);
                $ret_val["textChoices"] = TextChoiceField::choicesFromRedcapChoiceOptions($choiceOptions);
                break;

            case "dropdown":
                $ret_val["answerFormat"] = "ORKValuePickerAnswerFormat";
                $ret_val["choiceAnswerStyle"] = ".SingleChoice";
                $choiceOptions = self::parseChoices($field->select_choices_or_calculations);
                $ret_val["textChoices"] = TextChoiceField::choicesFromRedcapChoiceOptions($choiceOptions);
                break;

            case "file":
                self::makeFileField(
                    $field,
                    $ret_val
                );
                break;

            case "notes":
                self::makeNotesField(
                    $ret_val
                );
                break;

            case "radio":
                $ret_val["answerFormat"] = "ORKTextChoiceAnswerFormat";
                $ret_val["choiceAnswerStyle"] = ".SingleChoice";
                $choiceOptions = self::parseChoices($field->select_choices_or_calculations);
                $ret_val["textChoices"] = TextChoiceField::choicesFromRedcapChoiceOptions($choiceOptions);
                break;

            case "slider":
                self::makeScaleField(
                    $field,
                    $ret_val
                );
                break;

            case "text":
                self::makeTextField(
                    $field,
                    $ret_val
                );
                break;

            case "truefalse":
                $ret_val["answerFormat"] = "ORKTextChoiceAnswerFormat";
                $ret_val["choiceAnswerStyle"] = ".SingleChoice";
                $ret_val["textChoices"] = [
                    (object)["text" => "True", "value" => "1"],
                    (object)["text" => "False", "value" => "0"]
                ];
                break;

            case "yesno":
                $ret_val["answerFormat"] = "ORKBooleanAnswerFormat";
                break;

            case "descriptive":
                $ret_val["answerFormat"] = "ORKDescriptiveAnswerFormat";
                break;

            default:
                // Just make a text field if the type is not implemented
                /*Log::message(
                    'Unhandled text format: ' . $field->field_type,
                    __FILE__,
                    __DIR__
                );*/
                self::makeTextField(
                    $field,
                    $ret_val
                );
        }

        if (strlen($field->field_annotation)) {
            self::processAnnotation(
                $field->field_annotation,
                $ret_val
            );
        }
        return (object)$ret_val;
    }

    /**
     * Some active tasks require provider info to help name files or identify fields
     * 
     * @param string $redcapFieldAnnotation
     * @param array $ret_val 
     * @return void 
     */
    private static function processAnnotation($redcapFieldAnnotation, &$ret_val) {

        $annotationToProviderInfo = [
            Annotation::TASK_ACTIVE_AMS_LEFT_IMAGE => '{ "amslerGridResultProcessorFileType": ".LeftImage" }',
            Annotation::TASK_ACTIVE_AMS_LEFT_JSON => '{ "amslerGridResultProcessorFileType": ".LeftJSON" }',
            Annotation::TASK_ACTIVE_AMS_RIGHT_IMAGE => '{ "amslerGridResultProcessorFileType": ".RightImage" }',
            Annotation::TASK_ACTIVE_AMS_RIGHT_JSON => '{ "amslerGridResultProcessorFileType": ".RightJSON" }',
            Annotation::TASK_ACTIVE_AUD_COUNTDOWN => '{ "audioResultProcessorFileType": ".CountdownAudio" }',
            Annotation::TASK_ACTIVE_AUD_MAIN => '{ "audioResultProcessorFileType": ".MainAudio" }',
            Annotation::TASK_ACTIVE_FIT_WALK_PEDOMETER => '{ "fitnessCheckResultProcessorFileType": ".Pedometer" }',
            Annotation::TASK_ACTIVE_FIT_WALK_ACCELEROMETER => '{ "fitnessCheckResultProcessorFileType": ".WalkAccelerometer" }',
            Annotation::TASK_ACTIVE_FIT_WALK_DEVICEMOTION => '{ "fitnessCheckResultProcessorFileType": ".WalkDeviceMotion" }',
            Annotation::TASK_ACTIVE_FIT_WALK_LOCATION => '{ "fitnessCheckResultProcessorFileType": ".WalkLocation" }',
            Annotation::TASK_ACTIVE_FIT_REST_ACCELEROMETER => '{ "fitnessCheckResultProcessorFileType": ".RestAccelerometer" }',
            Annotation::TASK_ACTIVE_FIT_REST_DEVICEMOTION => '{ "fitnessCheckResultProcessorFileType": ".RestDeviceMotion" }',
            Annotation::TASK_ACTIVE_REA => '{ "reactionTimeResultProcessorFieldType": ".Json"}',
            Annotation::TASK_ACTIVE_REC_AUD => '{ "audioRecordingResultProcessorFileType":".Audio" }',
            Annotation::TASK_ACTIVE_RMO_DEVICEMOTION => '{ "rangeOfMotionResultProcessorFieldType": ".DeviceMotion"}',
            Annotation::TASK_ACTIVE_RMO_EXTENSION => '{ "rangeOfMotionResultProcessorFieldType": ".Extension"}',
            Annotation::TASK_ACTIVE_RMO_FLEXION => '{ "rangeOfMotionResultProcessorFieldType": ".Flexion"}',
            Annotation::TASK_ACTIVE_SEL => '{ "selfieCaptureResultProcessorFileType":".Photo" }',
            Annotation::TASK_ACTIVE_SHO_OUTBOUND_ACCELEROMETER => '{ "shortWalkResultProcessorFileType": ".OutboundAccelerometer" }',
            Annotation::TASK_ACTIVE_SHO_OUTBOUND_DEVICEMOTION => '{ "shortWalkResultProcessorFileType": ".OutboundDeviceMotion" }',
            Annotation::TASK_ACTIVE_SHO_RETURN_ACCELEROMETER => '{ "shortWalkResultProcessorFileType": ".ReturnAccelerometer" }',
            Annotation::TASK_ACTIVE_SHO_RETURN_DEVICEMOTION => '{ "shortWalkResultProcessorFileType": ".ReturnDeviceMotion" }',
            Annotation::TASK_ACTIVE_SHO_REST_ACCELEROMETER => '{ "shortWalkResultProcessorFileType": ".RestAccelerometer" }',
            Annotation::TASK_ACTIVE_SHO_REST_DEVICEMOTION => '{ "shortWalkResultProcessorFileType": ".RestDeviceMotion" }',
            Annotation::TASK_ACTIVE_SPR_AUDIO => '{ "speechRecognitionResultProcessorFileType": ".Audio" }',
            Annotation::TASK_ACTIVE_TIM_TRIAL1 => '{ "timedWalkResultProcessorFieldType": ".Trial1" }',
            Annotation::TASK_ACTIVE_TIM_TRIAL2 => '{ "timedWalkResultProcessorFieldType": ".Trial2" }',
            Annotation::TASK_ACTIVE_TIM_TURNAROUND => '{ "timedWalkResultProcessorFieldType": ".TurnAround" }',
            Annotation::TASK_ACTIVE_TWO_LEFT => '{ "twoFingerTappingIntervalResultProcessorType": ".LeftJson" }',
            Annotation::TASK_ACTIVE_TWO_LEFT_ACCELEROMETER => '{ "twoFingerTappingIntervalResultProcessorType": ".LeftAccelerometer" }',
            Annotation::TASK_ACTIVE_TWO_RIGHT => '{ "twoFingerTappingIntervalResultProcessorType": ".RightJson" }',
            Annotation::TASK_ACTIVE_TWO_RIGHT_ACCELEROMETER => '{ "twoFingerTappingIntervalResultProcessorType": ".RightAccelerometer" }'
        ];

        foreach ($annotationToProviderInfo as $annotation => $json) {
            if (Annotation::matchExists($annotation, $redcapFieldAnnotation)) {
                $ret_val["providerInfo"] = $json;
                break;
            }
        }
        
        // A text field or notes field might use the barcode capture annotation
        if (Annotation::matchExists(Annotation::FIELD_TEXT_BARCODE, $redcapFieldAnnotation)) {
            $barcodeCaptureField = new BarcodeCaptureField();
            $ret_val["barcodeCaptureField"] = $barcodeCaptureField->toArray();
        }
        if (Annotation::matchExists(Annotation::FIELD_TEXT_BARCODE_SCANDIT, $redcapFieldAnnotation)) {
            $barcodeCaptureField = new BarcodeCaptureField();
            $barcodeCaptureField->engine = BarcodeCaptureField::ENGINE_SCANDIT;
            $ret_val["barcodeCaptureField"] = $barcodeCaptureField->toArray();
        }
    }

    /**
     * Make a ResearchKit Image or Video capture field given a REDCap File Upload field.
     *
     * http://researchkit.org/docs/Classes/ORKImageCaptureStep.html
     * http://researchkit.org/docs/Classes/ORKVideoCaptureStep.html
     *
     * @param Object $redcapField A REDCap field object
     * @param array $ret_val Passthrough array that represents a MyCap field
     */
    private static function makeFileField($redcapField, &$ret_val)
    {
        $ret_val["answerFormat"] = "ORKFileResult";
        if (Annotation::matchExists(Annotation::FIELD_FILE_IMAGECAPTURE, $redcapField->field_annotation)) {
            $imageCaptureField = new ImageCaptureField();
            // Skipping validation check because we have not implemented hint or instruction.
            $ret_val["imageCaptureField"] = $imageCaptureField->toArray();
            return;
        }

        // Regex: @MC-FIELD-FILE-VIDEOCAPTURE=2.5:YES:OFF:BACK == [..., 2.5, YES, OFF, BACK]
        preg_match(
            Annotation::pattern(Annotation::FIELD_FILE_VIDEOCAPTURE),
            $redcapField->field_annotation,
            $matches
        );
        if (count($matches) == 5) {
            $duration = $matches[1];
            $audioMute = strtoupper($matches[2]);
            $flashMode = strtoupper($matches[3]);
            $devicePosition = strtoupper($matches[4]);

            $videoCaptureField = new VideoCaptureField();
            if (strlen($duration)) {
                $videoCaptureField->duration = $duration;
            }
            if (strlen($audioMute)) {
                $audioMute = strtoupper($audioMute);
                if ($audioMute == 'YES') {
                    $videoCaptureField->audioMute = true;
                } elseif ($audioMute == 'NO') {
                    $videoCaptureField->audioMute = false;
                }
            }
            if (strlen($flashMode)) {
                $flashMode = strtoupper($flashMode);
                if ($flashMode == 'ON') {
                    $videoCaptureField->flashMode = VideoCaptureField::FLASHMODE_ON;
                } elseif ($flashMode == 'OFF') {
                    $videoCaptureField->flashMode = VideoCaptureField::FLASHMODE_OFF;
                } elseif ($flashMode == 'AUTO') {
                    $videoCaptureField->flashMode = VideoCaptureField::FLASHMODE_AUTO;
                }
            }
            if (strlen($devicePosition)) {
                $devicePosition = strtoupper($devicePosition);
                if ($devicePosition == 'BACK') {
                    $videoCaptureField->devicePosition = VideoCaptureField::POSITION_BACK;
                } elseif ($devicePosition == 'FRONT') {
                    $videoCaptureField->devicePosition = VideoCaptureField::POSITION_FRONT;
                } elseif ($devicePosition == 'UNSPECIFIED') {
                    $videoCaptureField->devicePosition = VideoCaptureField::POSITION_UNSPECIFIED;
                }
            }
            $errors = $videoCaptureField->validate();
            if ($errors) {
                // Never allow an invalid scale field to hit the mobile app as ResearchKit will crash the app.
                /*Log::message(
                    "File field named (" . $redcapField->field_name . ") containing annotation (" . $redcapField->field_annotation . ") did not pass video validation due to errors: " . implode(
                        ',',
                        $errors
                    ) . ". This should have been caught and addressed during the configuration validation phase.",
                    __FILE__,
                    __LINE__
                );*/
                $videoCaptureField = new VideoCaptureField();
            }
            $ret_val["videoCaptureField"] = $videoCaptureField->toArray();
            return;
        }        
    }

    /**
     * Make a ResearchKit Note field given a REDCap Note field.
     * @param array $ret_val Passthrough array that represents a MyCap field
     */
    private static function makeNotesField(&$ret_val)
    {
        $ret_val["answerFormat"] = "ORKTextAnswerFormat";
        $ret_val["textField"] = (object)[
            "multipleLines" => true,
        ];
    }

    /**
     * Make a ResearchKit Scale field given a REDCap Scale field. REDCap only handles 0...100 sliders, but ResearchKit
     * has basic and continuous sliders. We make use of custom field annotations to specify expected behavior. If a
     * custom field annotation is not specified then we create a continuous slider with range 0...100.
     *
     * http://researchkit.org/docs/Classes/ORKScaleAnswerFormat.html
     * http://researchkit.org/docs/Classes/ORKContinuousScaleAnswerFormat.html
     *
     * @param Object $redcapField A REDCap field object
     * @param array $ret_val Passthrough array that represents a MyCap field
     */
    private static function makeScaleField($redcapField, &$ret_val)
    {
        $scaleField = new ScaleField();

        $redcapMin = is_numeric($redcapField->text_validation_min) ? $redcapField->text_validation_min : ScaleField::DEFAULT_MIN;
        $redcapMax = is_numeric($redcapField->text_validation_max) ? $redcapField->text_validation_max : ScaleField::DEFAULT_MAX;
        $scaleField->redcapMin = $redcapMin;
        $scaleField->redcapMax = $redcapMax;

        // Regex: @MC-FIELD-SLIDER-BASIC=0:10:1 == [..., 'BASIC','0','10','1']
        preg_match(
            Annotation::pattern(Annotation::FIELD_SLIDER_BASIC),
            $redcapField->field_annotation,
            $matches
        );
        if (count($matches) == 5) {
            if (strtoupper($matches[1]) == 'BASIC') {
                $scaleField->type = ScaleField::TYPE_BASIC;
            } elseif (strtoupper($matches[1]) == 'CONTINUOUS') {
                $scaleField->type = ScaleField::TYPE_CONTINUOUS;
            } else {
                // Annotation format is invalid
            }

            $scaleField->min = $matches[2];
            $scaleField->max = $matches[3];
            $scaleField->stepBy = $matches[4];

            // NOTE: REDCap forces scale to be 0...100 and integer. Do NOT allow fractional digits for continuous scale
            // To change this, you would need to teach people to use a text field instead of a REDCap slider field.
            // Annotation would be placed on the text field. Seems like a poor solution.
            if ($scaleField->type == ScaleField::TYPE_CONTINUOUS) {
                $scaleField->stepBy = 0;
            }
            if (is_numeric($scaleField->min) && $scaleField->min < ScaleField::RANGE_MIN) {
                $scaleField->min = ScaleField::RANGE_MIN;
            }
            if (is_numeric($scaleField->max) && $scaleField->max > ScaleField::RANGE_MAX) {
                $scaleField->max = ScaleField::RANGE_MAX;
            }
        }

        // Regex: @DEFAULT=5 == [..., '5']
        preg_match(
            '/@DEFAULT=(-?\d+)/',
            $redcapField->field_annotation,
            $matches
        );
        if (count($matches) == 2) {
            $scaleField->default = $matches[1];
        }

        $labels = ScaleField::parseLabels($redcapField->select_choices_or_calculations);
        $scaleField->minimumDescription = $labels['LEFT'];
        $scaleField->middleDescription = $labels['MIDDLE'];
        $scaleField->maximumDescription = $labels['RIGHT'];

        if ($redcapField->custom_alignment == "RV" || $redcapField->custom_alignment == "LV") {
            $scaleField->vertical = true;
        }

        $errors = $scaleField->validate();
        if ($errors) {
            // Never allow an invalid scale field to hit the mobile app as ResearchKit will crash the app.
            /*Log::message(
                "Slider/scale field named (" . $redcapField->field_name . ") containing annotation (" . $redcapField->field_annotation . ") did not pass validation due to errors: " . implode(
                    ',',
                    $errors
                ) . ". This should have been caught and addressed during the configuration validation phase.",
                __FILE__,
                __LINE__
            );*/
            $scaleField = new ScaleField();
        }

        if ($scaleField->type == ScaleField::TYPE_BASIC) {
            $ret_val["answerFormat"] = "ORKScaleAnswerFormat";
        } else {
            $ret_val["answerFormat"] = "ORKContinuousScaleAnswerFormat";
        }
        $ret_val["scaleField"] = $scaleField->toArray();
    }

    /**
     * Converts a REDCap text field into a MyCap field (which may or may not be a "text" field)
     *
     * @param Object $field A REDCap field object
     * @param array $ret_val Passthrough array that represents a MyCap field
     */
    private static function makeTextField($redcapField, &$ret_val)
    {
        switch ($redcapField->text_validation_type_or_show_slider_number) {
            case "":
                $ret_val["answerFormat"] = "ORKTextAnswerFormat";
                $ret_val["textField"] = (object)[];
                break;

            case ValidationType::EMAIL:
                $ret_val["answerFormat"] = "ORKEmailAnswerFormat";
                break;

            case ValidationType::NUMBER:
                // MyCap FieldNumeric expects strings
                $maximum = $redcapField->text_validation_max;
                $minimum = $redcapField->text_validation_min;
                if (!is_null($maximum)) {
                    $maximum = (string)$maximum;
                }
                if (!is_null($minimum)) {
                    $minimum = (string)$minimum;
                }
                $ret_val["answerFormat"] = "ORKNumericAnswerFormat";
                $ret_val["numericField"] = (object)[
                    "style" => ".Decimal",
                    "maximum" => $maximum,
                    "minimum" => $minimum
                ];
                break;

            case ValidationType::INTEGER:
                // MyCap FieldNumeric expects strings
                $maximum = $redcapField->text_validation_max;
                $minimum = $redcapField->text_validation_min;
                if (!is_null($maximum)) {
                    $maximum = (string)$maximum;
                }
                if (!is_null($minimum)) {
                    $minimum = (string)$minimum;
                }
                $ret_val["answerFormat"] = "ORKNumericAnswerFormat";
                $ret_val["numericField"] = (object)[
                    "style" => ".Integer",
                    "maximum" => $maximum,
                    "minimum" => $minimum
                ];
                break;

            case ValidationType::PHONE:
                $ret_val["answerFormat"] = "ORKPhoneAnswerFormat";
                // TODO, Sould pull expression from redcap_validation_types table?
                $ret_val["textField"] = (object)[
                    "validationRegularExpression" => '/^(?:\(?([2-9]0[1-9]|[2-9]1[02-9]|[2-9][2-9][0-9])\)?)\s*(?:[.-]\s*)?([2-9]\d{2})\s*(?:[.-]\s*)?(\d{4})(?:\s*(?:#|x\.?|ext\.?|extension)\s*(\d+))?$/',
                    "invalidMessage" => "Must be a 10 digit U.S. phone number (like 415 555 1212)",
                    "keyboardType" => ".NumbersAndPunctuation"
                ];
                break;

            case ValidationType::ZIPCODE:
                $ret_val["answerFormat"] = "ORKZipAnswerFormat";
                // TODO, Sould pull expression from redcap_validation_types table?
                $ret_val["textField"] = (object)[
                    "validationRegularExpression" => '/^\d{5}(-\d{4})?$/',
                    "invalidMessage" => "Must be a 5 or 9 digit U.S. ZIP Code",
                    "keyboardType" => ".NumbersAndPunctuation"
                ];
                break;

            case ValidationType::DATE_DMY:
            case ValidationType::DATE_MDY:
            case ValidationType::DATE_YMD:
                $ret_val["answerFormat"] = "ORKDateAnswerFormat";
                $ret_val["providerInfo"] = '{ "redcapFormat":"date" }';
                $ret_val["dateField"] = (object)[
                    "style" => ".Date",
                    "defaultDate" => ".Now"
                    // NOTE: Do we need to implement these?
                    //"minimumDate" = "",
                    //"maximumDate" = "",
                    //"calendar" = ".Gregorian"
                ];
                break;

            case ValidationType::DATETIME_DMY:
            case ValidationType::DATETIME_MDY:
            case ValidationType::DATETIME_YMD:
                $ret_val["answerFormat"] = "ORKDateAnswerFormat";
                $ret_val["providerInfo"] = '{ "redcapFormat":"datetime" }';
                $ret_val["dateField"] = (object)[
                    "style" => ".DateAndTime",
                    "defaultDate" => ".Now"
                    // NOTE: Do we need to implement these?
                    //"minimumDate" = "",
                    //"maximumDate" = "",
                    //"calendar" = ".Gregorian"
                ];
                break;

            case ValidationType::DATETIME_SECONDS_DMY:
            case ValidationType::DATETIME_SECONDS_MDY:
            case ValidationType::DATETIME_SECONDS_YMD:
                $ret_val["answerFormat"] = "ORKDateAnswerFormat";
                $ret_val["providerInfo"] = '{ "redcapFormat":"datetimeseconds" }';
                $ret_val["dateField"] = (object)[
                    "style" => ".DateAndTime",
                    "defaultDate" => ".Now"
                    // NOTE: Do we need to implement these?
                    //"minimumDate" = "",
                    //"maximumDate" = "",
                    //"calendar" = ".Gregorian"
                ];
                break;

            case ValidationType::TIME:
                $ret_val["answerFormat"] = "ORKTimeIntervalAnswerFormat";
                break;

            case ValidationType::TIME_SECONDS:
                $ret_val["answerFormat"] = "ORKTimeIntervalSecondsAnswerFormat";
                break;

            default:
                /*Log::message(
                    'Unhandled text format: ' . $redcapField->text_validation_type_or_show_slider_number,
                    __FILE__,
                    __DIR__
                );  */
        }
        $ret_val["fieldAnnotation"] = $redcapField->field_annotation;
    }

    /**
     * Get project images (a zip file) given a study code
     *
     * @param array $data
     */
    public function getImages($data)
    {
        MyCapApi::validateParameters(
            $data,
            ["stu_code"]
        );

        $stu_code = $data['stu_code'];

        try {
            $myProj = new Project();
            $projects = $myProj->loadByCode($stu_code);

            $error = MyCap::exportImagesPack($stu_code, $projects['project_id']);
            if (!empty($error)) {
                Response::sendError(
                    400,
                    ProjectHandlerError::IMAGES_NOT_FOUND,
                    $error
                );
            }
        } catch (\Exception $e) {
            Response::sendError(
                400,
                ProjectHandlerError::CODE_NOT_FOUND,
                $e->getMessage()
            );
        }

    }

    /**
     * Get a project file
     *
     * @param array $data
     */
    public function getFile($data)
    {
        MyCapApi::validateParameters(
            $data,
            ["stu_code","fil_name","fil_category"]
        );

        $stu_code = $data['stu_code'];
        $fil_category = $data['fil_category'];

        try {
            $proj = new Project();
            $projects = $proj->loadByCode($stu_code);

            if (!MyCap::isValidFileCategory($fil_category)) {
                Response::sendError(
                    400,
                    ProjectHandlerError::INVALID_FILE_CATEGORY,
                    "File category ($fil_category) is invalid"
                );
            }

            $doc_id = MyCap::getFileNames($fil_category, $data['fil_name'], $projects['project_id'], $stu_code);
            $filename = \Files::getEdocName($doc_id, true);
            $local_file = EDOC_PATH . \Files::getLocalStorageSubfolder($projects['project_id'], true) . $filename;
            if (!(file_exists($local_file) && is_file($local_file))) {
                Response::sendError(
                    400,
                    ProjectHandlerError::FILE_NOT_FOUND,
                    "Could not find file matching name and category"
                );
            }

            $fileAttr = \Files::getEdocContentsAttributes($doc_id);
            $file_contents = $fileAttr[2];

            Response::send(
                200,
                $file_contents
            );
        } catch (\Exception $e) {
            Response::sendError(
                400,
                ProjectHandlerError::CODE_NOT_FOUND,
                $e->getMessage()
            );
        }
    }

    /**
     * Parse REDCap text choices into an array of text/value objects.
     *
     * Given: "0, Deleted | 1, Completed | 2, Incomplete | 3, Something containing another comma (,)"
     * Return: [
     *   Option(text->'Deleted', value->'0')
     *   Option(text->'Completed', value->'1')
     *   Option(text->'Incomplete', value->'2')
     *   Option(text->'Something containing another comma (,)', value->'3')
     * ]
     *
     * @param string $select_choices_or_calculations
     * @return Option[]
     * @throws \Exception
     */
    public static function parseChoices($select_choices_or_calculations)
    {
        $ret_val = [];

        // Some PROMIS measures (NIH TB Hearing Handicap Ages 18-64) have empty length list of choices
        if (!strlen($select_choices_or_calculations)) {
            return $ret_val;
        }

        $choices = explode(
            "|",
            $select_choices_or_calculations
        );

        foreach ($choices as $choice) {
            $trimmed = trim($choice);
            if (!strlen($trimmed) || $trimmed === ',') {
                continue;
            }
            $pos = strpos($choice, ',');

            if ($pos === false) {
                throw new \Exception(
                    "Invalid choice. Expected to find a comma in: $choice"
                );
            }
            $value = substr($choice, 0, $pos);
            $text = substr($choice, $pos+1);

            $ob = new \stdClass();
            $ob->text = trim($text);
            $ob->value = trim($value);
            $ret_val[] = $ob;
        }

        return $ret_val;
    }

    /**
     * Get project tranlsations (JSON string) given a study code and language code
     *
     * @param array $data
     */
    public function getTranslations($data)
    {
        MyCapApi::validateParameters(
            $data,
            ["stu_code", "lang_code"]
        );

        $stu_code = $data['stu_code'];

        try {
            $myProj = new Project();
            $projects = $myProj->loadByCode($stu_code);
            $pid = $projects['project_id'];

            $result = MultiLanguage::exportMyCapTranslations($projects['project_id'], $data['lang_code']);

            if ($result['success']) {
                $timestamp = date("Ymd-His");
                // Create a snapshot ZIP
                $filename = "StudyTranslations_".$pid."_".$timestamp;
                $target_zip = APP_PATH_TEMP . $filename.".zip";
                if (is_file($target_zip)) unlink($target_zip);
                $zip = new \ZipArchive();
                $content = json_encode($result['translations'], JSON_PRETTY_PRINT);
                // Start writing to zip file
                if ($zip->open($target_zip, \ZipArchive::CREATE) === TRUE)
                {
                    $zip->addFromString($filename.".json", $content);
                }
                $zip->close();

                // Output file
                header('HTTP/1.1 200 OK');
                header('Content-type: application/zip; name="' . $filename . '".zip');
                ob_start();ob_end_flush();
                readfile_chunked($target_zip);

                unlink($target_zip);
            } else {
                Response::sendError(
                    400,
                    ProjectHandlerError::INVALID_LANGUAGE_CODE,
                    implode(
                        ",",
                        $result['errors']
                    )
                );
            }
        } catch (\Exception $e) {
            Response::sendError(
                400,
                ProjectHandlerError::CODE_NOT_FOUND,
                $e->getMessage()
            );
        }

    }
}
