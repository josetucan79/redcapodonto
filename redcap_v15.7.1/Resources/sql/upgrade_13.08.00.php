<?php

$sql = "
REPLACE INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('BackgroundDataImport', 'Import records in batches that are queued via the asynchronous/background data import process.', 'ENABLED', 60, 1800, 5, 0, NULL, 0, NULL);

CREATE TABLE `redcap_data_import` (
`import_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`user_id` int(10) DEFAULT NULL COMMENT 'User importing the data',
`filename` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`upload_time` datetime DEFAULT NULL,
`completed_time` datetime DEFAULT NULL,
`total_processing_time` int(10) DEFAULT NULL COMMENT 'seconds',
`status` enum('INITIALIZING','QUEUED','PROCESSING','COMPLETED','FAILED','CANCELED','PAUSED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INITIALIZING',
`csv_header` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`records_provided` int(10) DEFAULT NULL,
`records_imported` int(10) DEFAULT NULL,
`total_errors` int(10) DEFAULT NULL,
`delimiter` enum(',',';','TAB') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ',',
`date_format` enum('MDY','DMY') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MDY',
`overwrite_behavior` enum('normal','overwrite') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
`force_auto_number` tinyint(1) NOT NULL DEFAULT '0',
`change_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`import_id`),
KEY `completed_time` (`completed_time`),
KEY `project_id` (`project_id`),
KEY `status_completed_time` (`status`,`completed_time`),
KEY `upload_time` (`upload_time`),
KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_data_import_rows` (
`row_id` int(10) NOT NULL AUTO_INCREMENT,
`import_id` int(10) NOT NULL,
`record_provided` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`row_status` enum('QUEUED','PROCESSING','COMPLETED','FAILED','CANCELED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'QUEUED',
`start_time` datetime DEFAULT NULL,
`end_time` datetime DEFAULT NULL,
`total_time` int(10) DEFAULT NULL COMMENT 'milliseconds',
`row_data` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`error_count` int(10) DEFAULT NULL,
`errors` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`row_id`),
UNIQUE KEY `import_row_id` (`import_id`,`row_id`),
KEY `end_time` (`end_time`),
KEY `event_id` (`event_id`),
KEY `import_id_record_event_id` (`import_id`,`record`,`event_id`),
KEY `import_id_row_status` (`import_id`,`row_status`),
KEY `row_status_end_time` (`row_status`,`end_time`),
KEY `start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_data_import`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `redcap_data_import_rows`
ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`import_id`) REFERENCES `redcap_data_import` (`import_id`) ON DELETE CASCADE ON UPDATE CASCADE;

";

print $sql;