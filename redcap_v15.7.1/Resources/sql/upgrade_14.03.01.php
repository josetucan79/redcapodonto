<?php
use Vanderbilt\REDCap\Classes\MyCap\Task;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\ZeroDateTask;
// Create SQL to copy schedules fields from redcap_mycap_tasks table to redcap_mycap_task_schedules DB table
$TaskSchedulesImport = "";
$sql2 = "SELECT * FROM redcap_mycap_tasks WHERE enabled_for_mycap = 1 and project_id is not null";
$q2 = db_query($sql2);

$taskSettings = $allMyCalPIDs = array();
while ($row = db_fetch_assoc($q2)) {
	$pid = $row['project_id'];
    $taskId = $row['task_id'];
    $form = $row['form_name'];
    $Proj = new Project($pid);
    $Proj->loadEvents();
    $Proj->loadEventsForms();

    if ($Proj->longitudinal) {
        $allMyCalPIDs[] = $pid;
        if (isinteger($taskId)) {
            $sql3 = "SELECT * FROM redcap_mycap_tasks_schedules WHERE task_id = $taskId";
            $q3 = db_query($sql3);
            while ($row3 = db_fetch_assoc($q3)) {
                $eventId = $row3['event_id'];
                if ($Proj->isRepeatingEvent($eventId)) {
                    // Make this event as non-repeatable with eventId as project is longitudinal
                    $TaskSchedulesImport .= "DELETE FROM redcap_events_repeat WHERE event_id = '" . $eventId . "';\n";
                }
                if (!$Proj->isRepeatingForm($eventId, $form)) {
                    // Make this form as repeatable with eventId as project is longitudinal
                    $TaskSchedulesImport .= "INSERT INTO redcap_events_repeat (event_id, form_name) VALUES ($eventId, '" . db_escape($form) . "');\n";
                }
                $TaskSchedulesImport .= "UPDATE 
                                                redcap_mycap_tasks_schedules 
                                        SET allow_retro_completion = '" . db_escape($row['allow_retro_completion']) . "', allow_save_complete_later = '" . db_escape($row['allow_save_complete_later']) . "',
                                            include_instruction_step = '" . db_escape($row['include_instruction_step']) . "', include_completion_step = '" . db_escape($row['include_completion_step']) . "', 
                                            instruction_step_title = '" . db_escape($row['instruction_step_title']) . "', instruction_step_content = '" . db_escape($row['instruction_step_content']) . "', 
                                            completion_step_title = '" . db_escape($row['completion_step_title']) . "', completion_step_content = '" . db_escape($row['completion_step_content']) . "',
                                            schedule_relative_to = '" . db_escape($row['schedule_relative_to']) . "', schedule_type = '" . Task::TYPE_ONETIME . "', 
                                            schedule_relative_offset = '" . $Proj->eventInfo[$eventId]['day_offset'] . "', extended_config_json = '" . db_escape($row['extended_config_json']) . "'
                                        WHERE ts_id = '" . $row3['ts_id'] . "';\n";
            }
        }
    } else {
        $TaskSchedulesImport .= "INSERT INTO redcap_mycap_tasks_schedules 
                                    (task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date, extended_config_json) 
                                VALUES 
                                    (".checkNull($row['task_id']).", '".$Proj->firstEventId."', '" . db_escape($row['allow_retro_completion']) . "', '" . db_escape($row['allow_save_complete_later']) . "', '" . db_escape($row['include_instruction_step']) . "', '" . db_escape($row['include_completion_step']) . "', '" . db_escape($row['instruction_step_title']) . "', '" . db_escape($row['instruction_step_content']) . "', '" . db_escape($row['completion_step_title']) . "', '" . db_escape($row['completion_step_content']) . "', '" . db_escape($row['schedule_relative_to']) . "', 
                                    '".db_escape($row['schedule_type'])."', ".checkNull($row['schedule_frequency']).", ".checkNull($row['schedule_interval_week']).", ".checkNull($row['schedule_days_of_the_week']).", ".checkNull($row['schedule_interval_month']).",
                                ".checkNull($row['schedule_days_of_the_month']).", ".checkNull($row['schedule_days_fixed']).", ".checkNull($row['schedule_relative_offset']).", ".checkNull($row['schedule_ends']).", ".checkNull($row['schedule_end_count']).", ".checkNull($row['schedule_end_after_days']).", ".checkNull($row['schedule_end_date']).", '" . db_escape($row['extended_config_json']) . "');\n";
    }

}

$allMyCalPIDs = array_unique($allMyCalPIDs);
$BaselineDateUpdate = '';
foreach ($allMyCalPIDs as $pid) {
    if (ZeroDateTask::baselineDateEnabled($pid)) {
        $myCapProj = new MyCap($pid);
        $Proj = new Project($pid);
        $baseline_date_field = $myCapProj->project['baseline_date_field'];
        $list = explode("-", $baseline_date_field);
        if (count($list) == 1) {
            $form = $Proj->metadata[$baseline_date_field]['form_name'];
            $events = Task::getEventsList($form, $pid);
            if (!empty($events)) {
                $new_baseline_date = $events[0]."-".$baseline_date_field;
                $BaselineDateUpdate .= "UPDATE 
                                            redcap_mycap_projects 
                                    SET baseline_date_field = '" . db_escape($new_baseline_date)."'
                                    WHERE project_id = '" . $pid . "';\n";
            }
        }
    }
}

if ($BaselineDateUpdate != '') {
    $BaselineDateUpdate = "-- Updating existing Baseline date field names for Longitudinal projects to include event\n$BaselineDateUpdate";
}
if ($TaskSchedulesImport != '') {
    $TaskSchedulesImport = "-- Copying Existing MyCap Task Schedules\n$TaskSchedulesImport";
}
$sql = <<<EOF
-- Copy schedules data to task schedule table
ALTER TABLE `redcap_mycap_tasks_schedules` 
    ADD `allow_retro_completion` int(1) NOT NULL DEFAULT '0' COMMENT 'Allow retroactive completion?',
    ADD `allow_save_complete_later` int(1) NOT NULL DEFAULT '0' COMMENT 'Allow save and complete later?',
    ADD `include_instruction_step` int(1) NOT NULL DEFAULT '0' COMMENT 'Include Instruction Step?',
    ADD `include_completion_step` int(1) NOT NULL DEFAULT '0' COMMENT 'Include Completion Step?',
    ADD `instruction_step_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Instruction Step - Title',
    ADD `instruction_step_content` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Instruction Step - Content',
    ADD `completion_step_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Completion Step - Title',
    ADD `completion_step_content` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Completion Step - Content',
    ADD `schedule_relative_to` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Possible values are .JoinDate, .ZeroDate',
	ADD `schedule_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Possible values are .OneTime, .Infinite, .Repeating, .Fixed',
    ADD `schedule_frequency` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Possible values are .Daily, .Weekly, .Monthly',
    ADD `schedule_interval_week` int(2) DEFAULT NULL COMMENT 'Weeks from 1 to 24',
    ADD `schedule_days_of_the_week` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'List of days of the week',
    ADD `schedule_interval_month` int(2) DEFAULT NULL COMMENT 'Months from 1 to 12',
    ADD `schedule_days_of_the_month` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'List of days of the month',
    ADD `schedule_days_fixed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'List of days for type FIXED',
    ADD `schedule_relative_offset` int(10) DEFAULT NULL COMMENT 'Number of days to delay',
    ADD `schedule_ends` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Possible values are .Never or list of .AfterCountOccurrences, .AfterNDays, .OnDate',
    ADD `schedule_end_count` int(10) DEFAULT NULL COMMENT 'Ends after number of times',
    ADD `schedule_end_after_days` int(10) DEFAULT NULL COMMENT 'Ends after number of days have elapsed',
    ADD `schedule_end_date` date DEFAULT NULL,
    ADD `extended_config_json` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Extended Config JSON string for active task';
    
$TaskSchedulesImport

-- Remove scheduling fields from redcap_mycap_tasks table
ALTER TABLE `redcap_mycap_tasks`
  DROP `allow_retro_completion`,
  DROP `allow_save_complete_later`,
  DROP `include_instruction_step`,
  DROP `include_completion_step`,
  DROP `instruction_step_title`,
  DROP `instruction_step_content`,
  DROP `completion_step_title`,
  DROP `completion_step_content`,
  DROP `schedule_relative_to`,
  DROP `schedule_type`,
  DROP `schedule_frequency`,
  DROP `schedule_interval_week`,
  DROP `schedule_days_of_the_week`,
  DROP `schedule_interval_month`,
  DROP `schedule_days_of_the_month`,
  DROP `schedule_days_fixed`,
  DROP `schedule_relative_offset`,
  DROP `schedule_ends`,
  DROP `schedule_end_count`,
  DROP `schedule_end_after_days`,
  DROP `schedule_end_date`,
  DROP `extended_config_json`;
  $BaselineDateUpdate

-- Backfill missing event_ids in MyCap Participants table  
update redcap_mycap_participants m, redcap_projects p
set m.event_id = (select e.event_id from redcap_events_metadata e, redcap_events_arms a 
	where a.project_id = p.project_id and a.arm_id = e.arm_id order by a.arm_num, e.day_offset, e.descrip limit 1)
where m.event_id is null and p.project_id = m.project_id;
EOF;


print $sql;