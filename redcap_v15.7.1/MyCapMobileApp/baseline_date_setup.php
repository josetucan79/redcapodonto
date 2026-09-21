<?php

use Vanderbilt\REDCap\Classes\MyCap\ZeroDateTask;
use Vanderbilt\REDCap\Classes\MyCap\Page;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\Task;

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
// Initialize vars
$popupContent = $popupTitle = "";

## RENDER DIALOG CONTENT FOR SETTING UP CONDITIONS
if (isset($_POST['action']) && $_POST['action'] == "view")
{
    // Response
    $popupTitle = "<i class=\"fa-solid fa-calendar-day\"></i> ".$lang['mycap_mobile_app_453'];
    $popupContent = ZeroDateTask::displaySetupTable();
}


## SAVE CONDITIONS SETTINGS
elseif (isset($_POST['action']) && $_POST['action'] == "save")
{
    global $Proj;
    $_POST['use_baseline'] = (isset($_POST['use_baseline']) && $_POST['use_baseline'] == "on") ? 1 : 0;
    $_POST['include_instructions'] = (isset($_POST['include_instructions']) && $_POST['include_instructions'] == "on") ? 1 : 0;

    $baseline_date_field = "";
    if ($_POST['use_baseline'] == 1) {
        $data['enabled'] = true;
        if (is_array($_POST['baseline_date_field'])) {
            $baseline_date_field = implode("|", $_POST['baseline_date_field']);
        } else {
            $baseline_date_field = $_POST['baseline_date_field'];
        }

        if ($_POST['include_instructions'] == 1) {
            $instrData['type'] = Page::TYPE_TASKINSTRUCTIONSTEP;
            $instrData['identifier'] = MyCap::guid();
            $instrData['subType'] = Page::IMAGETYPE_CUSTOM;
            $instrData['title'] = db_escape($_POST['instruction_title']);
            $instrData['content'] = trim($_POST['instruction_content']);
            $instrData['imageName'] = "";
            $instrData['imageType'] = "";
            $instrData['sortOrder'] = 1;

            $data['instructionStep'] = $instrData;
        } else {
            $data['instructionStep'] = null;
        }

        $data['title'] = db_escape($_POST['title']);
        $data['question1'] = db_escape($_POST['yesnoquestion']);
        $data['question2'] = db_escape($_POST['datequestion']);
    } else {
        $data = ZeroDateTask::getDefaultBaselineDateSettings();
    }

    $json_config = json_encode($data);

    $sql = "UPDATE redcap_mycap_projects SET baseline_date_config = '" . db_escape($json_config) . "', baseline_date_field = '".db_escape($baseline_date_field)."' WHERE project_id = " . PROJECT_ID;
    db_query($sql);

    // If no baseline date is defined for the project, then make sure that no task is set to use a baseline date
    if (!$_POST['use_baseline']) {
        $sql = "SELECT task_id FROM redcap_mycap_tasks WHERE project_id = ".PROJECT_ID;
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $sql = "UPDATE redcap_mycap_tasks_schedules SET schedule_relative_to = '".Task::RELATIVETO_JOINDATE."' WHERE task_id = '".$row['task_id']."'";
            db_query($sql);
        }
    } elseif ($Proj->longitudinal && $Proj->multiple_arms) {
        // If no baseline date field is selected for an arm, then make sure that no task is set to use a baseline date enabled for events of those arms
        foreach ($_POST['baseline_date_field'] as $eventField) {
            if ($eventField != "") {
                list ($eventId, $field) = explode ("-", $eventField);
                $arms_enabled[] = $Proj->eventInfo[$eventId]['arm_num'];
            }
        }
        $sql = "SELECT task_id FROM redcap_mycap_tasks WHERE project_id = ".PROJECT_ID;
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $sql2 = "SELECT task_id, event_id FROM redcap_mycap_tasks_schedules WHERE task_id = '".$row['task_id']."'";
            $q2 = db_query($sql2);
            while ($row2 = db_fetch_assoc($q2)) {
                if (!in_array($Proj->eventInfo[$row2['event_id']]['arm_num'], $arms_enabled)) {
                    $sql3 = "UPDATE redcap_mycap_tasks_schedules SET schedule_relative_to = '".Task::RELATIVETO_JOINDATE."' WHERE task_id = '".$row2['task_id']."' AND event_id = '".$row2['event_id']."'";
                    db_query($sql3);
                }
            }
        }
    }

    // Response
    $popupTitle = $lang['design_243'];
    $popupContent = RCView::img(array('src'=>'tick.png')) . RCView::span(array('style'=>"color:green;"), $lang['mycap_mobile_app_461']);
    // Log the event
    Logging::logEvent($sql, "redcap_mycap_projects", "MANAGE", PROJECT_ID, "project_id = ".PROJECT_ID, "Modify MyCap baseline date settings");
}

// Send back JSON response
print json_encode_rc(array('content'=>$popupContent, 'title'=>$popupTitle));