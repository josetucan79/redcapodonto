<?php

$sql = "
CREATE TABLE `redcap_record_background_delete` (
`delete_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`user_id` int(10) DEFAULT NULL COMMENT 'User deleting the records',
`request_time` datetime DEFAULT NULL,
`start_time` datetime DEFAULT NULL,
`completed_time` datetime DEFAULT NULL,
`status` enum('INITIALIZING','QUEUED','PROCESSING','COMPLETED','FAILED','CANCELED','PAUSED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INITIALIZING',
`form_event` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`records_provided` int(10) DEFAULT NULL,
`records_deleted` int(10) DEFAULT NULL,
`total_errors` int(10) DEFAULT NULL,
`total_processing_time` int(10) DEFAULT NULL COMMENT 'seconds',
`remove_log_details` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'If data details for record should be wiped from the log',
`change_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`delete_id`),
KEY `completed_time` (`completed_time`),
KEY `project_id` (`project_id`),
KEY `request_time` (`request_time`),
KEY `start_time` (`start_time`),
KEY `status_completed_time` (`status`,`completed_time`),
KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_record_background_delete_items` (
`dr_id` int(10) NOT NULL AUTO_INCREMENT,
`delete_id` int(10) NOT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`arm_id` int(10) DEFAULT NULL,
`row_status` enum('QUEUED','PROCESSING','COMPLETED','FAILED','CANCELED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'QUEUED',
`start_time` datetime DEFAULT NULL,
`end_time` datetime DEFAULT NULL,
`total_time` int(10) DEFAULT NULL COMMENT 'milliseconds',
`error_count` int(10) DEFAULT NULL,
`errors` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`dr_id`),
UNIQUE KEY `delete_dr_id` (`delete_id`,`dr_id`),
KEY `arm_id_record` (`arm_id`,`record`),
KEY `delete_id_record_arm_id` (`delete_id`,`record`,`arm_id`),
KEY `delete_id_row_status` (`delete_id`,`row_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_record_background_delete`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_record_background_delete_items`
ADD FOREIGN KEY (`arm_id`) REFERENCES `redcap_events_arms` (`arm_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`delete_id`) REFERENCES `redcap_record_background_delete` (`delete_id`) ON DELETE CASCADE ON UPDATE CASCADE;
                                                                                                   
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) 
	VALUES ('BackgroundRecordDelete', 'Delete records in batches that are queued via the background bulk record delete process.', 'ENABLED', 60, 1800, 5, 0, NULL, 0, NULL);

CREATE TABLE `redcap_twilio_credentials_temp` (
`tc_id` int(10) NOT NULL AUTO_INCREMENT,
`request_id` int(10) DEFAULT NULL,
`project_id` int(10) DEFAULT NULL,
`twilio_account_sid` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`twilio_auth_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`twilio_from_number` bigint(16) DEFAULT NULL,
`twilio_alphanum_sender_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`mosio_api_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`tc_id`),
KEY `request_id` (`request_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_twilio_credentials_temp`
ADD FOREIGN KEY (`request_id`) REFERENCES `redcap_todo_list` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_mycap_projects` ADD `prevent_lang_switch_mtb` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Prevent participants to switch language from Spanish to English or vice a versa';
";

// Set processed = '1' (action needed = no) to automated messages sent from app upon deleting project from app-side exa. "#DELETED PROJECT--PushID:{PushID}"
// These messages are not visible in message list and after executing below SQL, these will not counted as messages needed action
$sql .= "
UPDATE 
    redcap_mycap_messages 
SET 
    processed = '1' 
WHERE body LIKE '#DELETED PROJECT%';
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print $sql;
