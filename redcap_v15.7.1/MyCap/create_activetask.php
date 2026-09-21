<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

use Vanderbilt\REDCap\Classes\MyCap;
use Vanderbilt\REDCap\Classes\ProjectDesigner;

$return_status = $msg = $form_name = $note = '';
/**
 * PROCESS SUBMITTED CHANGES
 */
if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if (isset($_POST['action']) && $_POST['action'] == 'validateActiveTask') {
        $extendedConfigData = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'extendedConfig_') !== false) {
                $key_parts = explode('_', $key);
                $extendedConfigData[$key_parts[1]] = $value;
            }
        }
        $taskObj = MyCap\ActiveTask::getActiveTaskObj($_POST['question_format']);
        $errors = $taskObj->validateExtendedConfigParams($extendedConfigData);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $msg .= '<li>'.$error.'</li>';
            }
        }
        $return_status = 'success';
    } else {
        if (!isset($_POST['selected_active_task']) || !isset($_POST['new_form_label'])) exit("0");

        $task_format = $_POST['selected_active_task'];
        $new_form_label = $_POST['new_form_label'];

        $taskObj = MyCap\ActiveTask::getActiveTaskObj($task_format);

        list($created, $form_name) = MyCap\ActiveTask::createREDCapForm($new_form_label);
        $fieldsArr = $taskObj->getFormFields();

        $task_id = ""; // Not defined for some reason

        // Log the event
        Logging::logEvent("", "redcap_mycap_tasks", "MANAGE", $task_id, "task_id = $task_id", "Set up MyCap Active Task\n(Format: ".$task_format.")");
        if ($created) {
            global $Proj, $status;
            if ($status > 0) {
                $Proj->loadMetadataTemp();
            } else {
                $Proj->loadMetadata();
            }

            // Add {prefix}jsonexport{blank/_sp} JSON File field for all MTB tasks - On DEMO SERVER ONLY e.g. arw_jsonexport will be added to Arrows task will
            if (SERVER_NAME == 'redcapdemo.vumc.org'
                && MyCap\ActiveTask::isMTBActiveTask($task_format)
                && $task_format != MyCap\ActiveTask::SHAPECOLORSORTING_DEPRECATED)
            {
                $arr = ['ArrangingPictures' => 'arp_', 'Arrows' => 'arw_', 'FNAMELearning' => 'fnl_', 'FNAMETest' => 'fnt_',
                        'NumberMatch' => 'nsm_', 'Sequences' => 'mfs_', 'ShapeColorSorting' => 'scs_', 'Spelling' => 'spl_',
                        'WordMeaning1' => 'wm1_', 'WordMeaning2' => 'wm2_', 'BlockRotation' => 'ibr_', 'LettersAndNumbers' => 'iln_',
                        'PuzzleCompletion' => 'ipc_', 'VarietyTest' => 'ivt_', 'WordProblems' => 'iwp_'];
                $allMTBFormats = MyCap\ActiveTask::getResearchKitActiveTasksFormats('mtb');
                foreach ($allMTBFormats as $format) {
                    $taskName = str_replace(['Mtb', 'Spanish'], "", $format);
                    $mapping[$format] = $arr[$taskName];
                }

                $allMTBTasks = MyCap\ActiveTask::getAllActiveTasks('mtb');
                foreach ($allMTBTasks as $category => $taskDetails) {
                    foreach ($taskDetails as $taskDetail) {
                        $postfix = "";
                        if (str_ends_with($task_format, "Spanish")) { // Add _sp at end in varname for all Spanish tasks
                            $postfix = "_sp";
                        }
                        $fieldDetails[$taskDetail['addHref']]['field_name'] = $mapping[$task_format]."jsonexport".$postfix; // exa. arw_jsonexport_sp
                        $fieldDetails[$taskDetail['addHref']]['field_label'] = $taskDetail['name']." JSON Export"; // exa. Arrows Spanish JSON Export
                    }
                }
                $field_name = MyCap\ActiveTask::getNewFieldName($fieldDetails[$task_format]['field_name']);
                $jsonexportField[] = ['field_name' => $field_name,
                                    'field_label' => $fieldDetails[$task_format]['field_label'],
                                    'field_type' => 'file'];
                // Add new field to
                $fieldsArr = array_merge($fieldsArr, $jsonexportField);
            }

            $projectDesigner = new ProjectDesigner($Proj);
            foreach ($fieldsArr as $field) {
                // Check if $field['field_name'] is unique or not. If no, generate new unique field name
                $field['field_name'] = MyCap\ActiveTask::getNewFieldName($field['field_name']);
                $projectDesigner->createField($form_name, $field);
            }

            $taskObj->buildExtendedConfig();

            $extendedConfigAsString = MyCap\ActiveTask::extendedConfigAsString($taskObj);
            $return = MyCap\ActiveTask::insertDefaultTaskSetting($form_name, $new_form_label, $task_format, $extendedConfigAsString);
            if ($return == true) {
                $return_status = "success";
            }
            if (!$Proj->isRepeatingForm($Proj->firstEventId, $form_name)) {
                // Make this form as repeatable with default eventId as project is classic
                $sql = "INSERT INTO redcap_events_repeat (event_id, form_name) 
			    VALUES ({$Proj->firstEventId}, '".db_escape($form_name)."')";
                db_query($sql);
            }
            // Add 7 new annotations required for MyCap
            $missingAnnotations = MyCap\Task::getMissingAnnotationList($form_name);
            if (count($missingAnnotations) > 0) {
                MyCap\Task::fixMissingAnnotationsIssues($missingAnnotations, $form_name);
            }
        } else {
            $msg = "";
        }
    }
}

// Return message and status
echo json_encode(array(
    'status' => $return_status,
    'message' => $msg,
    'instrument_name' => $form_name,
    'note' => $note
));
