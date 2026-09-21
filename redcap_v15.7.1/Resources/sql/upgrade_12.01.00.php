<?php

// New features
$sql = "
ALTER TABLE `redcap_surveys` ADD `end_survey_redirect_next_survey_logic` TEXT NULL DEFAULT NULL AFTER `end_survey_redirect_next_survey`;

REPLACE INTO `redcap_validation_types` (`validation_name`, `validation_label`, `regex_js`, `regex_php`, `data_type`, `visible`) 
VALUES ('time_hh_mm_ss', 'Time (HH:MM:SS)', '/^(\\d|[01]\\d|(2[0-3]))(:[0-5]\\d){2}$/', '/^(\\d|[01]\\d|(2[0-3]))(:[0-5]\\d){2}$/', 'time', 1);
";



// Get FKs to drop and re-add for redcap_log_view_requests
$q = db_query( "select constraint_name from information_schema.KEY_COLUMN_USAGE 
				where CONSTRAINT_SCHEMA = '{$GLOBALS['db']}' and TABLE_NAME = 'redcap_log_view_requests' and referenced_column_name is not null");
$fks = [];
while ($row = db_fetch_assoc($q)) {
    $constraint_name = $row["constraint_name"] ?? ($row["CONSTRAINT_NAME"] ?? "");
    if ($constraint_name == '') continue;
	$fks[] = "ALTER TABLE `redcap_log_view_requests` DROP FOREIGN KEY `{$constraint_name}`;\n";
}
$dropFks = implode("", $fks);
// Get current max log_view_id+10000
$q = db_query("SELECT max(log_view_id)+10000 FROM redcap_log_view");
$max_log_view_id = db_result($q, 0);
if ($max_log_view_id == '') $max_log_view_id = "1";

$sql .= "
-- Drop all FKs for redcap_log_view_requests
$dropFks
-- Copy the redcap_log_view table
DROP TABLE IF EXISTS redcap_log_view_old;
RENAME TABLE redcap_log_view TO redcap_log_view_old;

CREATE TABLE `redcap_log_view` (
`log_view_id` bigint(19) NOT NULL AUTO_INCREMENT,
`ts` timestamp NULL DEFAULT NULL,
`user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event` enum('LOGIN_SUCCESS','LOGIN_FAIL','LOGOUT','PAGE_VIEW') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`browser_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`browser_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`full_url` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`project_id` int(10) DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`record` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`form_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`miscellaneous` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`session_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`log_view_id`),
KEY `browser_name` (`browser_name`(191)),
KEY `browser_version` (`browser_version`(191)),
KEY `event` (`event`),
KEY `ip` (`ip`),
KEY `page_ts_project_id` (`page`(191),`ts`,`project_id`),
KEY `project_event_record` (`project_id`,`event_id`,`record`(191)),
KEY `project_record` (`project_id`,`record`(191)),
KEY `session_id` (`session_id`),
KEY `ts_user_event` (`ts`,`user`(191),`event`),
KEY `user_project` (`user`(191),`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Set new auto-increment and backfill 30-minutes of events for temporary continuity purposes
ALTER TABLE redcap_log_view AUTO_INCREMENT = $max_log_view_id;
insert into redcap_log_view select * from redcap_log_view_old where ts > DATE_SUB(NOW(), INTERVAL 30 MINUTE);
";


print $sql;

// Add Messenger system notification
$title = "New feature: Dynamic min/max range limits for fields";
$msg = "Instead of using exact values as the minimum or maximum range of Textbox fields (e.g., \"2021-12-07\"), you may now also use \"<b>today</b>\" and \"<b>now</b>\" as the min or max so that the current date or time is always used. These can be used to prevent a date/time field from having a value in the past or in the future. Additionally, you can now <b>pipe a value from another field</b> into the field's min or max range setting - e.g., [visit_date] or [event_1_arm_1][age]. This can help ensure that a Textbox field (whether a date, time, or number) has a larger or smaller value than another field, regardless of whether the field is on the same instrument or not.
 
<b class=\"fs15\">New action tag: @FORCE-MINMAX</b>
The action tag @FORCE-MINMAX can be used on Textbox fields that have a min or max validation range defined so that no one will not be able to enter a value into the field unless it is within the field's specified validation range. This is different from the default behavior in which out-of-range values are permissible. Note: @FORCE-MINMAX is also enforced for data imports to ensure the value is always within the specified range.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);