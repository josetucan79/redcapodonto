<?php
require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// If not using a type of project with mycap, then don't allow user to use this page.
if (!$mycap_enabled) redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");

use Vanderbilt\REDCap\Classes\MyCap;
// Determine the instrument
$form = (isset($_GET['page']) && isset($Proj->forms[$_GET['page']])) ? $_GET['page'] : null;

// If task has already been created (it shouldn't have been), then redirect to edit_task page to edit task
if (isset($myCapProj->tasks[$form]['task_id'])) {
	redirect(str_replace(PAGE, 'MyCap/edit_task.php', $_SERVER['REQUEST_URI']));
}

/**
 * PROCESS SUBMITTED CHANGES
 */
if ($_SERVER['REQUEST_METHOD'] == "POST")
{
	// Assign Post array as globals
	foreach ($_POST as $key => $value) $$key = $value;

    $fixWarnings = true;
    // If project is in production and not in draft mode then do not fix missing annotation errors
    if ($status > 0 && $draft_mode == 0) {
        $fixWarnings = false;
    }

    if ($fixWarnings == true) {
        $missingAnnotations = MyCap\Task::getMissingAnnotationList($form);
        if (count($missingAnnotations) > 0 ) {
            MyCap\Task::fixMissingAnnotationsIssues($missingAnnotations, $form);
        }
    }

	// Set values
    $enabled_for_mycap = $_POST['task_enabled'];

    if ($card_display == MyCap\Task::TYPE_PERCENTCOMPLETE) {
        $x_date_field = $x_time_field = $y_numeric_field = '';
    }

    if ($instruction_step == '0') {
        $instruction_step_title = $instruction_step_content = '';
    }

    if ($completion_step == '0') {
        $completion_step_title = $completion_step_content = '';
    }

    list ($isPromisInstrument, $isAutoScoringInstrument) = PROMIS::isPromisInstrument($form);
    if ($isPromisInstrument) {
        $isBatteryInstrument = false;
        $triggers = array();
        $batteryInstrumentsList = MyCap\Task::batteryInstrumentsInSeriesPositions();
        if (array_key_exists($form, $batteryInstrumentsList)) {
            $isBatteryInstrument = true;
        }
        if ($mtb_enabled && SERVER_NAME == 'redcapdemo.vumc.org') { // Release this feature to demo server ONLY for now
            // Add additional MTB related fields to each PROMIS instrument upon enabling for MyCap
            MyCap\Task::addMtbPromisFormFields($form);

            // Add these additional fields to all remaining instruments in battery series too
            if ($isBatteryInstrument) {
                foreach ($batteryInstrumentsList as $form_name => $arr) {
                    if ($arr['firstInstrument'] == $form && $arr['batteryPosition'] != 1) {
                        MyCap\Task::addMtbPromisFormFields($form_name);
                    }
                }
            }
        }

        if ($isBatteryInstrument && $batteryInstrumentsList[$form]['instrumentPosition'] != '1') {
            $question_format = MyCap\ActiveTask::PROMIS;
        }
        $taskObj = MyCap\ActiveTask::getActiveTaskObj($question_format);
        $taskObj->setupIfNeeded($form, PROJECT_ID);
        $extendedConfigAsString = MyCap\ActiveTask::extendedConfigAsString($taskObj);
    }

    // Save task info
    $sql = "REPLACE INTO redcap_mycap_tasks (project_id, form_name, enabled_for_mycap, task_title, question_format,
			card_display, x_date_field, x_time_field, y_numeric_field, extended_config_json)
			VALUES ($project_id, '" . db_escape($form) . "',
			'" . db_escape($enabled_for_mycap) . "', '" . db_escape($task_title) . "',
			'" . db_escape($question_format) . "', '" . db_escape($card_display) . "',
			'" . db_escape($x_date_field) . "', '" . db_escape($x_time_field) . "', '" . db_escape($y_numeric_field) . "', '" . db_escape($extendedConfigAsString) ."'
        )";


    if (!db_query($sql)) {
        exit("An error occurred. Please try again.");
    }
    $task_id = db_insert_id();
    foreach ($_POST['tsevent'] as $eventId) {
        $active = (isset($_POST["tsactive-$eventId"]) && $_POST["tsactive-$eventId"] == 'on') ? '1' : '0';
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

            // Convert 1,  7 to 1,7
            if ($schedule_days_fixed != '') {
                $schedule_days_fixed = MyCap\Task::removeSpaces($schedule_days_fixed);
            }

            if (in_array($schedule_type, array(MyCap\Task::TYPE_ONETIME, MyCap\Task::TYPE_FIXED))) {
                $schedule_ends = $schedule_end_count = $schedule_end_after_days = $schedule_end_date = '';
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

            // Join array of days selected with ";"
            $schedule_days_of_the_week_list = isset($schedule_days_of_the_week) && is_array($schedule_days_of_the_week) ? implode(",", $schedule_days_of_the_week) : "";

            if ($isPromisInstrument) {
                $triggers = array();
                if ($isBatteryInstrument && $batteryInstrumentsList[$form]['instrumentPosition'] != '1') {
                    $schedule_relative_to = MyCap\Task::RELATIVETO_JOINDATE;
                    $schedule_type = MyCap\Task::TYPE_INFINITE;
                    $schedule_ends = MyCap\Task::ENDS_NEVER;
                }
            }
            // Save task schedule
            $sql = "INSERT INTO redcap_mycap_tasks_schedules (task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, 
                                        instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, 
                                        schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, 
                                        schedule_ends, schedule_end_count, schedule_end_after_days".(($schedule_end_date != '') ? ', schedule_end_date' : '').")
                    VALUES ($task_id, '" . $eventId . "', '" . db_escape($allow_retroactive_completion) . "', '" . db_escape($allow_saving) . "', '" . db_escape($instruction_step) . "',
                                        '" . db_escape($completion_step) . "', '" . db_escape($instruction_step_title) . "',
                                        '" . db_escape($instruction_step_content) . "', '" . db_escape($completion_step_title) . "', '" . db_escape($completion_step_content) . "', 
                                        '" . db_escape($schedule_relative_to) . "', '" . db_escape($schedule_type) . "', " . checkNull($schedule_frequency) . ", 
                                        " . checkNull($schedule_interval_week) . ", " . checkNull($schedule_days_of_the_week_list) . ", " . checkNull($schedule_interval_month) . ",
                                        " . checkNull($schedule_days_of_the_month) . ", " . checkNull($schedule_days_fixed) . ", " . checkNull($schedule_relative_offset) . ",
                                        " . checkNull($schedule_ends) . ", " . checkNull($schedule_end_count) . ", " . checkNull($schedule_end_after_days).(($schedule_end_date != '') ? ", '" . db_escape($schedule_end_date) . "'" : '').")";
            db_query($sql);

            if ($Proj->longitudinal) {
                if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
                    $all_instruments = array_keys($batteryInstrumentsList);
                    foreach ($all_instruments as $this_form) {
                        if (!$Proj->isRepeatingForm($eventId, $this_form)) {
                            MyCap\Task::makeFormRepeatingForEvent($eventId, $this_form);
                        }
                    }
                } else {
                    MyCap\Task::makeFormRepeatingForEvent($eventId, $form);
                }
            }
        }
    }
    if (!$Proj->longitudinal) {
        if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] == '1') {
            $all_instruments = array_keys($batteryInstrumentsList);
        } else {
            $all_instruments = [$form];
        }

        foreach ($all_instruments as $instrument) {
            // Get default eventId as project is non-longitudinal
            $RepeatingFormsEvents = $Proj->getRepeatingFormsEvents();
            if ((isset($RepeatingFormsEvents[$Proj->firstEventId][$instrument]) && is_array($RepeatingFormsEvents[$Proj->firstEventId])) == false) {
                foreach ($RepeatingFormsEvents[$Proj->firstEventId] as $repeatingFormsEvent => $value) {
                    $_POST['repeat_form-'.$Proj->firstEventId.'-'.$repeatingFormsEvent] = "on";
                }
            }
            $_POST['repeat_form-'.$Proj->firstEventId.'-'.$instrument] = "on";
        }
        RepeatInstance::saveSetup();
    }


	// Log the event
	Logging::logEvent($sql, "redcap_mycap_tasks", "MANAGE", $task_id, "task_id = $task_id", "Set up MyCap Task");

	// Once the task is created, redirect to Online Designer and display "saved changes" message
	redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&task_save=create");
}

// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// TABS
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";

// Instructions
?>
<p style="margin-bottom:20px;">
	<?php
	print RCView::tt('mycap_mobile_app_104');
	?>
</p>
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

// Force user to click button to begin mycap-enabling process
if (!isset($_GET['view']))
{
	?>
	<div class="yellow" style="text-align:center;font-weight:bold;padding:10px;">
		<?php print RCView::tt('survey_151') ?>
		<br><br>
		<button class="jqbutton" onclick="window.location.href='<?php echo $_SERVER['REQUEST_URI'] ?>&view=showform';"
			><?php print RCView::tt('survey_152') ?> "<?php echo $Proj->forms[$form]['menu'] ?>" <?php print RCView::tt('survey_153') ?></button>
	</div>
	<?php
}


// Display form to enable task for mycap
elseif (isset($_GET['view']) && $_GET['view'] == "showform")
{
    if ($Proj->longitudinal) {
        $events = MyCap\Task::getEventsList($form);
        $message = MyCap\Task::checkFormEventsBindingError($events);
        if ($message != "") {
            print $message;
            exit;
        }
    }
    $schedules = $scheduledEvents = array();
    // Set defaults to pre-fill table
    $enabled_for_mycap = 1;
    $is_active_task = 0;
    $task_title = empty($Proj->forms[$form]['menu']) ? "" : $Proj->forms[$form]['menu'];

    $card_display = MyCap\Task::TYPE_PERCENTCOMPLETE;

    $date_fields = MyCap\Task::getDataTypeBasedFieldsList('date', $form);
    $time_fields = MyCap\Task::getDataTypeBasedFieldsList('time', $form);
    $numeric_fields = MyCap\Task::getDataTypeBasedFieldsList('numeric', $form);

    $x_date_field = $x_time_field = $y_numeric_field = "";

    $daysOfWeek = MyCap\Task::getDaysOfWeekList();
    $schedule_days_of_the_week_list = array();

    // Return warnings and errors for instrument
    list ($issues, $warnings) = MyCap\Task::checkErrors($form, PROJECT_ID);

    if (!empty($issues)) {
        echo '<span class="error">'. implode("<br>", $issues) . '</span>';
        exit;
    }

    list($isPromis, $isAutoScoringInstrument) = PROMIS::isPromisInstrument($form);

    $isBatteryInstrument = false;
    $triggers = array();
    if ($isPromis) {
        $issues = MyCap\Task::getUnsupportedPromisInstrumentsIssues($form);
        if (!empty($issues)) {
            echo '<span class="error">'. implode("<br>", $issues) . '</span>';
            exit;
        }
        $question_format = MyCap\Task::PROMIS;
        // Check if Battery Instrument
        $batteryInstrumentsList = MyCap\Task::batteryInstrumentsInSeriesPositions();
        if (array_key_exists($form, $batteryInstrumentsList)) {
            $isBatteryInstrument = true;
            $trigger = MyCap\ActiveTasks\Promis::triggerForBatterySeries(
                $form,
                $batteryInstrumentsList
            );
        }
    } else {
        $question_format = MyCap\Task::QUESTIONNAIRE;
    }
    if (isset($trigger)) {
        $triggers[] = $trigger;
    }

    // Issue exists if its battery instrument and currently not at position 1
    $batteryInstrumentIssueExists = ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] != '1');
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
        // If project is in production and not in draft mode then give error message
	    $showWarning = ($status > 0 && $draft_mode == 0);
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
    $disabledSaveBtn = "";

	?>
    <div id="errMsgContainerModal" class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;max-width:910px;"></div>
	<div class="darkgreen" style="max-width:910px;">
		<div style="float:left;">
            <i class="fas fa-plus"></i>
			<?php
			print RCView::tt('mycap_mobile_app_103');
			print " ".RCView::tt('setup_89')." \"<b>".RCView::escape($Proj->forms[$form]['menu'])."</b>\"";
			?>
		</div>
        <button class="btn btn-defaultrc btn-xs float-end" onclick="history.go(-1);return false;"><?php print RCView::tt_js2('global_53'); ?></button>
        <button type="button" class="btn btn-rcgreen btn-xs float-end me-2" onclick="$('#taskSettingsSubmit').trigger('click');"><?php print RCView::tt_js2('report_builder_28'); ?></button>
		<div class="clear"></div>
	</div>
	<div style="background-color:#FAFAFA;border:1px solid #DDDDDD;padding:0 6px;max-width:910px;">
		<?php
        include APP_PATH_DOCROOT . "MyCap/task_info_table.php";
		?>
	</div>
	<?php
}


// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
