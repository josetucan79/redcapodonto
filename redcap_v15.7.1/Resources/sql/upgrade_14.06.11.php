<?php
use Vanderbilt\REDCap\Classes\MyCap\ActiveTask;
// Create SQL to copy schedules fields from redcap_mycap_tasks table to redcap_mycap_task_schedules DB table
$extendedConfigUpdate = "";
$researchkit_formats = ActiveTask::getResearchKitActiveTasksFormats();
$sql2 = "SELECT * FROM redcap_mycap_tasks WHERE enabled_for_mycap = 1 and project_id is not null and question_format in (" . prep_implode($researchkit_formats) . ")";
$q2 = db_query($sql2);

$taskSettings = $allMyCalPIDs = array();
while ($row = db_fetch_assoc($q2)) {
    $pid = $row['project_id'];
    $taskId = $row['task_id'];
    $Proj = new Project($pid);

    $sql3 = "SELECT * FROM redcap_mycap_tasks_schedules WHERE task_id = $taskId LIMIT 1";
    $q3 = db_query($sql3);
    while ($row3 = db_fetch_assoc($q3)) {
        $extendedConfigUpdate .= "UPDATE redcap_mycap_tasks SET extended_config_json = '" . db_escape($row3['extended_config_json']) . "' WHERE task_id = '" . $taskId . "';\n";
    }
}

if ($extendedConfigUpdate != '') {
    $extendedConfigUpdate = "-- Copy first event's extended_config_json field value to redcap_mycap_tasks\n".$extendedConfigUpdate;
}
$sql = <<<EOF
-- Move "extended_config_json" from redcap_mycap_tasks_schedules to redcap_mycap_tasks table
ALTER TABLE `redcap_mycap_tasks` ADD `extended_config_json` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Extended Config JSON string for active task';
$extendedConfigUpdate
-- Remove "extended_config_json" from redcap_mycap_tasks_schedules table
ALTER TABLE `redcap_mycap_tasks_schedules` DROP `extended_config_json`;

replace into redcap_config (field_name, value) values ('google_recaptcha_default', '0');
EOF;

print $sql;