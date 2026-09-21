<?php

$sql = "
-- Add config setting to disable the calendar feed feature
REPLACE INTO redcap_config (field_name, value) VALUES ('calendar_feed_enabled_global', '1');

-- Adding calendar feed hash to project
CREATE TABLE `redcap_events_calendar_feed` (
`feed_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`userid` int(11) DEFAULT NULL COMMENT 'NULL=survey participant',
`hash` varchar(100) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT NULL,
PRIMARY KEY (`feed_id`),
UNIQUE KEY `hash` (`hash`),
UNIQUE KEY `project_record_user` (`project_id`,`record`,`userid`),
KEY `project_userid` (`project_id`,`userid`),
KEY `userid` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `redcap_events_calendar_feed`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`userid`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;

REPLACE INTO redcap_config (field_name, value) VALUES
('oauth2_azure_ad_endpoint_version', 'V1');

ALTER TABLE `redcap_outgoing_email_sms_log` 
    CHANGE `type` `type` ENUM('EMAIL','SMS','VOICE_CALL','SENDGRID_TEMPLATE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EMAIL';

alter table redcap_projects 
    add sendgrid_enabled TINYINT(1) NOT NULL DEFAULT '0',
    add sendgrid_project_api_key TEXT NULL DEFAULT NULL;

alter table redcap_alerts 
    add sendgrid_template_id TEXT NULL DEFAULT NULL,
    add sendgrid_template_data TEXT NULL DEFAULT NULL;

ALTER TABLE `redcap_alerts` 
    CHANGE `alert_type` `alert_type` ENUM('EMAIL','SMS','VOICE_CALL','SENDGRID_TEMPLATE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EMAIL';

ALTER TABLE `redcap_alerts_sent_log` 
    CHANGE `alert_type` `alert_type` ENUM('EMAIL','SMS','VOICE_CALL','SENDGRID_TEMPLATE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EMAIL';

REPLACE INTO redcap_config (field_name, value) VALUES ('sendgrid_enabled_global', 1);
REPLACE INTO redcap_config (field_name, value) VALUES ('sendgrid_enabled_by_super_users_only', 0);
REPLACE INTO redcap_config (field_name, value) VALUES ('sendgrid_display_info_project_setup', 0);

update redcap_crons set cron_enabled = 'DISABLED' where cron_name in ('AutomatedSurveyInvitationsDatediffChecker2', 'AlertsNotificationsDatediffChecker');

REPLACE INTO redcap_config (field_name, value) VALUES ('openid_connect_name', '');
REPLACE INTO redcap_config (field_name, value) VALUES ('openid_connect_username_attribute', 'username');

ALTER TABLE `redcap_crons_datediff` 
    ADD `asi_last_update_start` DATETIME NULL DEFAULT NULL AFTER `asi_updated_at`, 
    ADD `alert_last_update_start` DATETIME NULL DEFAULT NULL AFTER `alert_updated_at`, 
    ADD INDEX `asi_last_update_status` (`asi_last_update_start`, `asi_status`),
    ADD INDEX `alert_last_update_status` (`alert_last_update_start`, `alert_status`);
";

print $sql;