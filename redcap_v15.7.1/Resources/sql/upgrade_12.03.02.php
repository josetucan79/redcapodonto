<?php

$sql = "
-- Enable the REDCap URL Shortener
UPDATE redcap_config SET value = '1' WHERE field_name = 'enable_url_shortener_redcap';

CREATE TABLE `redcap_crons_datediff` (
`dd_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`asi_updated_at` datetime DEFAULT NULL COMMENT 'Last evaluation for ASIs',
`asi_status` enum('QUEUED','PROCESSING') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Status for ASIs',
`alert_updated_at` datetime DEFAULT NULL COMMENT 'Last evaluation for Alerts',
`alert_status` enum('QUEUED','PROCESSING') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Status for Alerts',
PRIMARY KEY (`dd_id`),
UNIQUE KEY `project_record` (`project_id`,`record`),
KEY `alert_status_updated_at` (`alert_status`,`alert_updated_at`),
KEY `alert_updated_at` (`alert_updated_at`),
KEY `asi_status_updated_at` (`asi_status`,`asi_updated_at`),
KEY `asi_updated_at` (`asi_updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_crons_datediff`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('QueueRecordsDatediffCheckerCrons', 'Queue records that are ready to be evaluated by the datediff cron jobs.', 'ENABLED', 600, 1800, 1, 0, NULL, 0, NULL),
('AlertsNotificationsDatediffChecker2', 'Process records that are already queued for the Alerts datediff cron job.', 'ENABLED', 60, 3600, 5, 0, NULL, 0, NULL),
('AutomatedSurveyInvitationsDatediffChecker3', 'Process records that are already queued for the ASI datediff cron job.', 'ENABLED', 60, 3600, 5, 0, NULL, 0, NULL);
";

print $sql;