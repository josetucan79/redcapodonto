<?php
require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
use Vanderbilt\REDCap\Classes\MyCap;

// If not using a type of project with mycap, then don't allow user to use this page.
if (!$mycap_enabled) redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");

global $myCapProj, $status, $Proj;
$form = (isset($_GET['page']) && isset($Proj->forms[$_GET['page']])) ? $_GET['page'] : null;

// If no task id, assume it's the first form and retrieve
if (!isset($_GET['task_id']))
{
	$_GET['task_id'] = MyCap\MyCap::getTaskId($form);
}

if (MyCap\MyCap::checkIfValidTaskOfProject($form, $_GET['task_id'])) {
    // Default message
    $msg = "";

    // Retrieve task info
    $q = db_query("SELECT * FROM redcap_mycap_tasks WHERE project_id = $project_id AND task_id = " . $_GET['task_id']);
    foreach (db_fetch_assoc($q) as $key => $value) {
        if ($value === null) {
            $$key = $value;
        } else {
            // Replace non-break spaces because they cause issues with html_entity_decode()
            $value = str_replace(array("&amp;nbsp;", "&nbsp;"), array(" ", " "), $value);
            // Don't decode if can not detect encoding
            if (function_exists('mb_detect_encoding') && (
                    (mb_detect_encoding($value) == 'UTF-8' && mb_detect_encoding(html_entity_decode($value, ENT_QUOTES)) === false)
                    || (mb_detect_encoding($value) == 'ASCII' && mb_detect_encoding(html_entity_decode($value, ENT_QUOTES)) === 'UTF-8')
                )) {
                $$key = trim($value);
            } else {
                $$key = trim(html_entity_decode($value, ENT_QUOTES));
            }
        }
    }

    $daysOfWeek = MyCap\Task::getDaysOfWeekList();

    $date_fields = MyCap\Task::getDataTypeBasedFieldsList('date', $form);
    $time_fields = MyCap\Task::getDataTypeBasedFieldsList('time', $form);
    $numeric_fields = MyCap\Task::getDataTypeBasedFieldsList('numeric', $form);

    list($isPromis, $isAutoScoringInstrument) = PROMIS::isPromisInstrument($form);

    $isBatteryInstrument = false;
    $triggers = array();
    if ($isPromis) {
        $issues = MyCap\Task::getUnsupportedPromisInstrumentsIssues($form);
        if (!empty($issues)) {
            echo implode("<br>", $issues);
            exit;
        }
        // Check if Battery Instrument
        $batteryInstrumentsList = MyCap\Task::batteryInstrumentsInSeriesPositions();
        if (array_key_exists($form, $batteryInstrumentsList)) {
            $isBatteryInstrument = true;
            $trigger = MyCap\ActiveTasks\Promis::triggerForBattery(
                $form,
                $batteryInstrumentsList
            );
        }
    }
    if (isset($trigger)) {
        $triggers[] = $trigger;
    }

    // Issue exists if its battery instrument and currently not at position 1
    $batteryInstrumentIssueExists = ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] != '1');

    $is_active_task = MyCap\ActiveTask::isActiveTask($question_format);
    $is_mtb_task = MyCap\ActiveTask::isMTBActiveTask($question_format);

    /**
     * PROCESS SUBMITTED CHANGES
     */
    if ($_SERVER['REQUEST_METHOD'] == "POST") {
        // Build "go back" button to specific page
        if (isset($_GET['redirectDesigner'])) {
            // Go back to Online Designer
            $goBackBtn = renderPrevPageBtn("Design/online_designer.php", RCView::tt('global_77'), false);
        }
        $msg = RCView::div(array('style' => 'padding:0 0 20px;'), $goBackBtn);

        // Assign Post array as globals
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'extendedConfig_') !== false) {
                $key_parts = explode('_', $key);
                if ($key == 'extendedConfig_numberOfDisks')  $value = (int) $value;

                $extendedConfigData[$key_parts[1]] = $value;
            } else {
                $$key = $value;
            }
        }
        $fixWarnings = true;
        // If project is in production and not in draft mode then do not fix missing annotation errors
        if ($status > 0 && $draft_mode == 0) {
            $fixWarnings = false;
        }
        if ($fixWarnings) {
            $missingAnnotations = MyCap\Task::getMissingAnnotationList($form);
            if (count($missingAnnotations) > 0) {
                MyCap\Task::fixMissingAnnotationsIssues($missingAnnotations, $form);
            }
        }

        $is_active_task = MyCap\ActiveTask::isActiveTask($question_format);

        if ($isPromis) {
            $taskObj = MyCap\ActiveTask::getActiveTaskObj($question_format);
            $taskObj->setupIfNeeded($form, PROJECT_ID);
        } else if ($is_active_task == 1) {
            $taskObj = MyCap\ActiveTask::getActiveTaskObj($question_format);
            $taskObj->buildExtendedConfig($extendedConfigData);
        }
        $extendedConfigAsString = isset($taskObj) ? MyCap\ActiveTask::extendedConfigAsString($taskObj) : null;
        // If some fields are missing from Post because disabled drop-downs don't post, then manually set their default value.
        // Set values
        $enabled_for_mycap = $_POST['task_enabled'];

        if ($card_display == MyCap\Task::TYPE_PERCENTCOMPLETE) {
            $x_date_field = $x_time_field = $y_numeric_field = '';
        }

        // Save Task info
        $sql = "UPDATE redcap_mycap_tasks SET task_title = '" . db_escape($task_title) . "', question_format = '" . db_escape($question_format) . "',
                    card_display = '" . db_escape($card_display) . "', x_date_field = '" . db_escape($x_date_field) . "',
                    x_time_field = '" . db_escape($x_time_field) . "', y_numeric_field = '" . db_escape($y_numeric_field) . "',
                    enabled_for_mycap = '" . $enabled_for_mycap . "', extended_config_json = '".db_escape($extendedConfigAsString)."' 
                    WHERE task_id = ?";

        if (db_query($sql, [$task_id])) {
            foreach ($_POST['tsevent'] as $eventId) {
                $active = (isset($_POST["tsactive-$eventId"]) && $_POST["tsactive-$eventId"] == 'on') ? '1' : '0';

                // Check if task/event already exists in DB
                $sql = "SELECT * FROM redcap_mycap_tasks_schedules WHERE task_id='".(int)$task_id."' AND event_id='".(int)$eventId."'";
                $q = db_query($sql);
                $num_rows = db_num_rows($q);

                if ($active == 1) {
                    $allow_retroactive_completion = (isset($_POST['allow_retroactive_completion-'.$eventId]) && $_POST['allow_retroactive_completion-'.$eventId] == 'on') ? '1' : '0';
                    $allow_saving = (isset($_POST['allow_saving-'.$eventId]) && $_POST['allow_saving-'.$eventId] == 'on') ? '1' : '0';
                    $instruction_step = (isset($_POST['instruction_step-'.$eventId]) && $_POST['instruction_step-'.$eventId] == 'on') ? '1' : '0';
                    $completion_step = (isset($_POST['completion_step-'.$eventId]) && $_POST['completion_step-'.$eventId] == 'on') ? '1' : '0';

                    if ($instruction_step == '0') {
                        $instruction_step_title = $instruction_step_content = '';
                    } else {
                        $instruction_step_title = $_POST['instruction_step_title-'.$eventId];
                        $instruction_step_content = $_POST['instruction_step_content-'.$eventId];
                    }

                    if ($completion_step == '0') {
                        $completion_step_title = $completion_step_content = '';
                    } else {
                        $completion_step_title = $_POST['completion_step_title-'.$eventId];
                        $completion_step_content = $_POST['completion_step_content-'.$eventId];
                    }

                    // Set Task Schedule variables
                    if (!isset($_POST['schedule_relative_to-'.$eventId])) {
                        $schedule_relative_to = MyCap\Task::RELATIVETO_JOINDATE;
                    } else {
                        $schedule_relative_to = $_POST['schedule_relative_to-'.$eventId];
                    }
                    // If no baseline date is defined for the project, then make sure the task isn't set to use a baseline date
                    if ($schedule_relative_to == MyCap\Task::RELATIVETO_ZERODATE && $myCapProj->project['baseline_date_field'] == '') {
                        $schedule_relative_to = MyCap\Task::RELATIVETO_JOINDATE;
                    }

                    $schedule_type = $_POST['schedule_type-'.$eventId];
                    $schedule_frequency = $_POST['schedule_frequency-'.$eventId];
                    $schedule_interval_week = $_POST['schedule_interval_week-'.$eventId];
                    $schedule_days_of_the_week = $_POST['schedule_days_of_the_week-'.$eventId];
                    $schedule_days_of_the_week_list = isset($schedule_days_of_the_week) && is_array($schedule_days_of_the_week) ? implode(",", $schedule_days_of_the_week) : "";
                    $schedule_interval_month = $_POST['schedule_interval_month-'.$eventId];
                    $schedule_days_of_the_month = $_POST['schedule_days_of_the_month-'.$eventId];
                    $schedule_days_fixed = $_POST['schedule_days_fixed-'.$eventId];
                    $schedule_relative_offset = $_POST['schedule_relative_offset-'.$eventId];
                    $schedule_ends = $_POST['schedule_ends-'.$eventId];
                    $schedule_end_count = $_POST['schedule_end_count-'.$eventId];
                    $schedule_end_after_days = $_POST['schedule_end_after_days-'.$eventId];
                    $schedule_end_date = $_POST['schedule_end_date-'.$eventId];

                    if (in_array($schedule_type, array(MyCap\Task::TYPE_ONETIME, MyCap\Task::TYPE_INFINITE))) {
                        $schedule_frequency = $schedule_interval_week = $schedule_days_of_the_week = $schedule_interval_month = $schedule_days_of_the_month = $schedule_days_fixed = '';
                        // Set "allow retroactive completion" to off if task is scheduled infinite times
                        if ($schedule_type == MyCap\Task::TYPE_INFINITE || $schedule_type == MyCap\Task::TYPE_ONETIME) {
                            $allow_retroactive_completion = 0;
                        }
                    } elseif ($schedule_type == MyCap\Task::TYPE_REPEATING) {
                        $schedule_days_fixed = '';
                        if ($schedule_frequency == MyCap\Task::FREQ_DAILY) {
                            $schedule_interval_week = $schedule_days_of_the_week = $schedule_interval_month = $schedule_days_of_the_month = '';
                        } elseif ($schedule_frequency == MyCap\Task::FREQ_WEEKLY) {
                            $schedule_interval_month = $schedule_days_of_the_month = '';
                        } elseif ($schedule_frequency == MyCap\Task::FREQ_MONTHLY) {
                            $schedule_interval_week = $schedule_days_of_the_week = '';
                        }
                    } elseif ($schedule_type == MyCap\Task::TYPE_FIXED) {
                        $schedule_frequency = $schedule_interval_week = $schedule_days_of_the_week = $schedule_interval_month = $schedule_days_of_the_month = '';
                    }

                    if (in_array($schedule_type, array(MyCap\Task::TYPE_ONETIME, MyCap\Task::TYPE_FIXED))) {
                        $schedule_ends = $schedule_end_count = $schedule_end_after_days = $schedule_end_date = '';
                        $_POST['schedule_ends_list-'.$eventId] = [];
                    }

                    if ($schedule_ends != MyCap\Task::ENDS_NEVER) {
                        $schedule_ends_list = $_POST['schedule_ends_list-'.$eventId];
                        if (isset($schedule_ends_list) && is_array($schedule_ends_list)) {
                            if (!in_array(MyCap\Task::ENDS_AFTERCOUNT, $schedule_ends_list))  $schedule_end_count = '';
                            if (!in_array(MyCap\Task::ENDS_AFTERDAYS, $schedule_ends_list))  $schedule_end_after_days = '';
                            if (!in_array(MyCap\Task::ENDS_ONDATE, $schedule_ends_list))  $schedule_end_date = '';
                            $schedule_ends = implode(",", $schedule_ends_list);
                        } else {
                            $schedule_ends = '';
                        }
                    }

                    $schedule_end_date = ($schedule_end_date != '') ? DateTimeRC::format_ts_to_ymd($schedule_end_date) : '';

                    // Convert 1,  7 to 1,7
                    if ($schedule_days_fixed != '') {
                        $schedule_days_fixed = MyCap\Task::removeSpaces($schedule_days_fixed);
                    }

                    // Join array of days selected with ";"
                    $schedule_days_of_the_week_list = is_array($schedule_days_of_the_week) ? implode(",", $schedule_days_of_the_week) : "";
                }
                $skipUpdate = false;
                if ($active == 1 && $num_rows == 0) { // if checked and not exists in DB then insert new entry
                    $sql = "INSERT INTO redcap_mycap_tasks_schedules (task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title,
			                              instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, 
                                          schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, 
                                          schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
                                VALUES ($task_id, '" . $eventId . "', '" . db_escape($allow_retroactive_completion) . "', '" . db_escape($allow_saving) . "', '" . db_escape($instruction_step) . "',
                                        '" . db_escape($completion_step) . "', '" . db_escape($instruction_step_title) . "',
                                        '" . db_escape($instruction_step_content) . "', '" . db_escape($completion_step_title) . "', '" . db_escape($completion_step_content) . "',
                                        '" . db_escape($schedule_relative_to) . "', '" .  db_escape($schedule_type) . "', " . checkNull($schedule_frequency) . ", 
                                        " . checkNull($schedule_interval_week) . ", " . checkNull($schedule_days_of_the_week_list) . ", " . checkNull($schedule_interval_month) . ",
                                        " . checkNull($schedule_days_of_the_month) . ", " . checkNull($schedule_days_fixed) . ", " . checkNull($schedule_relative_offset) . ",
                                        " . checkNull($schedule_ends) . ", " . checkNull($schedule_end_count) . ", " . checkNull($schedule_end_after_days) .", " . checkNull($schedule_end_date) ."
                                        )";
                } elseif ($active == 1 && $num_rows > 0) { // if checked and exists in DB then update existing entry
                    if (!$Proj->longitudinal && $eventId != $Proj->firstEventId) {
                        $skipUpdate = true;
                    }
                    if ($skipUpdate == false) {
                        // Save Task info
                        $sql = "UPDATE redcap_mycap_tasks_schedules SET 
                                    allow_retro_completion = '" . db_escape($allow_retroactive_completion) . "', allow_save_complete_later = '" . db_escape($allow_saving) . "', 
                                    include_instruction_step = '" . db_escape($instruction_step) . "', include_completion_step = '" . db_escape($completion_step) . "', 
                                    instruction_step_title = '" . db_escape($instruction_step_title) . "', instruction_step_content = '" . db_escape($instruction_step_content) . "', 
                                    completion_step_title = '" . db_escape($completion_step_title) . "', completion_step_content = '" . db_escape($completion_step_content) . "',
                                    schedule_relative_to = '" . db_escape($schedule_relative_to) . "', schedule_type = '" . db_escape($schedule_type) . "', schedule_frequency = " . checkNull($schedule_frequency) . ",
                                    schedule_interval_week = " . checkNull($schedule_interval_week) . ", schedule_days_of_the_week = " . checkNull($schedule_days_of_the_week_list) . ",
                                    schedule_interval_month = " . checkNull($schedule_interval_month) . ", schedule_days_of_the_month = " . checkNull($schedule_days_of_the_month) . ",
                                    schedule_days_fixed = " . checkNull($schedule_days_fixed) . ", schedule_relative_offset = " . checkNull($schedule_relative_offset) . ",
                                    schedule_ends = " . checkNull($schedule_ends) . ", schedule_end_count = " . checkNull($schedule_end_count) . ",
                                    schedule_end_after_days = " . checkNull($schedule_end_after_days) .", active = '1'";
                        $sql .= ", schedule_end_date = " . checkNull($schedule_end_date);
                        $sql .= " WHERE task_id = '".$task_id."' AND event_id = '".$eventId."'";
                    }
                } elseif ($active == 0) {
                    $sql = "UPDATE redcap_mycap_tasks_schedules SET active = '0' WHERE event_id = " . $eventId . " AND task_id = " . $task_id;
                }
                if ($skipUpdate == false) {
                    db_query($sql);
                }
            }
            $sql = "UPDATE redcap_mycap_tasks_schedules SET active = '0' WHERE event_id NOT IN (" . implode(", ", $_POST['tsevent']) . ") AND task_id = " . $task_id;
            db_query($sql);

            if ($Proj->longitudinal) {
                if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
                    $all_instruments = array_keys($batteryInstrumentsList);
                    foreach ($_POST['tsevent'] as $eventId) {
                        foreach ($all_instruments as $this_form) {
                            $sql = "select count(*) from redcap_events_forms where event_id = ".checkNull($eventId)." and form_name = '".db_escape($this_form)."'";
                            $q = db_query($sql);
                            $eventFormMappingFound = db_result($q, 0) != false;
                            if (!$eventFormMappingFound) {
                                $sql = "insert into redcap_events_forms (event_id, form_name)
                                     values (" . checkNull($eventId) . ", '" . db_escape($this_form) . "')";
                                db_query($sql);
                            }
                            if (!$Proj->isRepeatingForm($eventId, $this_form)) {
                                // Make this form as repeatable for eventId
                                $sql = "INSERT INTO redcap_events_repeat (event_id, form_name) 
                                        VALUES ($eventId, '" . db_escape($this_form) . "')";
                                db_query($sql);
                            }
                        }
                    }
                } else {
                    MyCap\Task::fixRepeatingFormIssues($form);
                }
            } else {
                if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
                    $all_instruments = array_keys($batteryInstrumentsList);
                } else {
                    $all_instruments = [$form];
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
            $msg .= RCView::div(array('id' => 'saveTaskMsg', 'class' => 'darkgreen', 'style' => 'display:none;vertical-align:middle;text-align:center;margin:0 0 25px;'),
                RCView::img(array('src' => 'tick.png')) . RCView::tt('control_center_48')
            );
        } else {
            $msg = RCView::div(array('id' => 'saveTaskMsg', 'class' => 'red', 'style' => 'display:none;vertical-align:middle;text-align:center;margin:0 0 25px;'),
                RCView::img(array('src' => 'exclamation.png')) . RCView::tt('survey_159')
            );
        }

        // Log the event
        Logging::logEvent($sql, "redcap_mycap_tasks", "MANAGE", $task_id, "task_id = $task_id", "Modify MyCap Task info");

        // Once the task is updated, redirect to Online Designer and display "saved changes" message
        redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&task_save=edit");
    }
}
// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// TABS
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";

?>
<script type="text/javascript">
// Display "saved changes" message, if just saved task settings
$(function(){
    if ($('#saveTaskMsg').length) {
        setTimeout(function(){
            $('#saveTaskMsg').slideToggle('normal');
        },200);
        setTimeout(function(){
            $('#saveTaskMsg').slideToggle(1200);
        },2500);
    }
});
</script>

<p style="margin-bottom:20px;"><?php print RCView::tt('mycap_mobile_app_132'); ?></p>

<?php
// If form name does not exist (except only in Draft Mode), then give error message
if (($form == null || !isset($myCapProj->tasks[$form]['task_id'])) && $status > 0 && $draft_mode >= 1)
{
    print 	RCView::div(array('class'=>'yellow','style'=>''),
        RCView::img(array('src'=>'exclamation_orange.png')) .
        RCView::b($lang['global_01'].$lang['colon']) . " " . $lang['mycap_mobile_app_708']
    );

    include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    exit;
}

$disabledSaveBtn = "";
if ($Proj->longitudinal) {
    $events = MyCap\Task::getEventsList($form);
    $message = MyCap\Task::checkFormEventsBindingError($events);
    if ($message != "") {
        print $message;
        exit;
    }
    $disabledSaveBtn = (count($events) > 0) ? "" : "disabled";
}
$schedules = MyCap\Task::getTaskSchedules($task_id, 'all');
$scheduledEvents = array_keys($schedules);

if (empty($task_title)) {
    $task_title = $Proj->forms[$form]['menu'];
}
if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
    $other_promis_forms = [];
    if (!$Proj->longitudinal) {
        foreach ($batteryInstrumentsList as $instrument => $arr) {
            if ($instrument == $form || $arr['firstInstrument'] == $form) {
                if (!$Proj->isRepeatingFormAnyEvent($instrument)) {
                    $other_promis_forms[] = "\"" . $Proj->forms[$instrument]['menu'] . "\"";
                }
            }
        }
        $titles = implode(", ", $other_promis_forms);
    } else {
        print RCView::div(array('class'=>'yellow','style'=>'max-width:910px;'),
            RCView::img(array('src'=>'exclamation_orange.png')) .
            RCView::tt('mycap_mobile_app_916')
        );
    }
} else {
    $titles = "\"".$task_title."\"";
}
if (!$Proj->longitudinal) {
    // For non-longitudinal projects, instrument need to be repeating
    if (!$Proj->isRepeatingFormAnyEvent($form) || !empty($other_promis_forms)) {
        print 	RCView::div(array('class'=>'yellow','style'=>'max-width:910px;'),
            RCView::img(array('src'=>'exclamation_orange.png')) .
            RCView::b(RCView::tt('global_03').RCView::tt('colon')) . " ".RCView::tt('mycap_mobile_app_534')." ".$titles
        );
    }
}

$missingAnnotations = MyCap\Task::getMissingAnnotationList($form);
if (!empty($missingAnnotations)) {
    if ($status > 0 && $draft_mode == 0) {
        $showWarning = true;
    }
    // If project is in production and not in draft mode then give error message
    $missingAnnotationsError = MyCap\Task::getMissingAnnotationErrorText($missingAnnotations);
    print 	RCView::div(array('class'=>'yellow','style'=>'max-width:910px;'),
        RCView::img(array('src'=>'exclamation_orange.png')) .
        RCView::b(RCView::tt('global_03').RCView::tt('colon')) . " ".$missingAnnotationsError.
        ($showWarning ? RCView::br().RCView::span(array('class' => 'text-dangerrc boldish'), RCView::tt('mycap_mobile_app_858')) : '')
    );
}

$nonFixableErrors = MyCap\Task::getMyCapTaskNonFixableErrors($form);
if (!empty($nonFixableErrors)) {
    print 	RCView::div(array('class'=>'red','style'=>'max-width:910px;'),
        '<i class="fa fa-circle-exclamation"></i> ' .
        RCView::b(RCView::tt('global_01').RCView::tt('colon')) . " <br>".implode("<br><br>", $nonFixableErrors)
    );
}

// Display error message, if exists
if (!empty($msg)) print $msg;
?>

<div id="errMsgContainerModal" class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;max-width:910px;"></div>
<div class="blue" style="max-width:910px;">
    <div style="float:left;">
        <i class="fas fa-pencil-alt"></i>
        <?php
        print RCView::tt('mycap_mobile_app_102');
        print " ".RCView::tt('setup_89')." \"<b>".RCView::escape($Proj->forms[$form]['menu'])."</b>\"";
        ?>
    </div>
    <button class="btn btn-defaultrc btn-xs float-end" onclick="history.go(-1);return false;"><?php print RCView::tt_js2('global_53'); ?></button>
    <button type="button" <?php echo $disabledSaveBtn?> class="btn btn-primaryrc btn-xs float-end me-2" onclick="$('#taskSettingsSubmit').trigger('click');"><?php print RCView::tt_js2('report_builder_28'); ?></button>
    <div class="clear"></div>
</div>
<div style="background-color:#FAFAFA;border:1px solid #DDDDDD;padding:0 6px;max-width:910px;">
<?php
    include APP_PATH_DOCROOT . "MyCap/task_info_table.php";
    print "</div>";
    // Footer
    include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

addLangToJS(array('mycap_mobile_app_398', 'mycap_mobile_app_399', 'mycap_mobile_app_448', 'mycap_mobile_app_449', 'global_53', 'mycap_mobile_app_450', 'folders_11'));