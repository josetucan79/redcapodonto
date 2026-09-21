<?php

$sql = <<<EOF
ALTER TABLE `redcap_outgoing_email_counts` ADD `twilio_sms` INT(10) NOT NULL DEFAULT '0' AFTER `mandrill`;

ALTER TABLE `redcap_alerts_sent_log` CHANGE `message` `message` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

CREATE TABLE `redcap_outgoing_email_sms_log` (
`email_id` int(10) NOT NULL AUTO_INCREMENT,
`type` enum('EMAIL','SMS','VOICE_CALL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EMAIL',
`category` enum('SURVEY_INVITE_MANUAL','SURVEY_INVITE_ASI','ALERT','SYSTEM') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`time_sent` datetime DEFAULT NULL,
`sender` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email or phone number',
`recipients` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Emails or phone numbers',
`email_cc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`email_bcc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`email_subject` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`message` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`message_html` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`attachment_names` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`attachment_doc_ids` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`project_id` int(10) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`instrument` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`instance` smallint(4) DEFAULT NULL,
`hash` varchar(100) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT NULL,
PRIMARY KEY (`email_id`),
UNIQUE KEY `hash` (`hash`),
KEY `attachment_names` (`attachment_names`(150)),
KEY `category` (`category`),
KEY `email_bcc` (`email_bcc`(150)),
KEY `email_cc` (`email_cc`(150)),
KEY `email_subject` (`email_subject`(150)),
KEY `event_id` (`event_id`),
KEY `message` (`message`(150)),
KEY `project_message` (`project_id`,`message_html`(150)),
KEY `project_record` (`project_id`,`record`),
KEY `project_subject_message` (`project_id`,`email_subject`(100),`message`(100)),
KEY `project_time_sent` (`project_id`,`time_sent`),
KEY `recipients` (`recipients`(150)),
KEY `sender` (`sender`),
KEY `time_sent` (`time_sent`),
KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_outgoing_email_sms_log`
ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`);

CREATE TABLE `redcap_outgoing_email_sms_identifiers` (
`ident_id` int(10) NOT NULL AUTO_INCREMENT,
`ssq_id` int(10) DEFAULT NULL,
PRIMARY KEY (`ident_id`),
UNIQUE KEY `ssq_id` (`ssq_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_outgoing_email_sms_identifiers`
ADD FOREIGN KEY (`ssq_id`) REFERENCES `redcap_surveys_scheduler_queue` (`ssq_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_projects` 
    ADD `protected_email_mode` TINYINT(1) NOT NULL DEFAULT '0' AFTER `bypass_branching_erase_field_prompt`,
    ADD `protected_email_mode_custom_text` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `protected_email_mode`,
    ADD `protected_email_mode_trigger` enum('ALL','PIPING') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ALL' AFTER `protected_email_mode_custom_text`;

REPLACE INTO redcap_config (field_name, value) VALUES ('protected_email_mode_global', '1');
REPLACE INTO redcap_config (field_name, value) VALUES ('email_logging_install_time', now());
REPLACE INTO redcap_config (field_name, value) VALUES ('email_logging_enable_global', '1');
-- Add option to prevent users from changing their primary email address on My Profile page
INSERT INTO `redcap_config` (`field_name`,`value`) VALUES ('my_profile_enable_primary_email_edit','1');

-- Fix table encoding by removing and rebuilding table redcap_new_record_cache
set foreign_key_checks=0;
DROP TABLE IF EXISTS redcap_new_record_cache;
CREATE TABLE `redcap_new_record_cache` (
`project_id` int(10) NOT NULL DEFAULT '0',
`event_id` int(10) DEFAULT NULL,
`arm_id` int(11) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`creation_time` datetime DEFAULT NULL,
UNIQUE KEY `proj_record_event` (`project_id`,`record`),
KEY `arm_id` (`arm_id`),
KEY `creation_time` (`creation_time`),
KEY `event_id` (`event_id`),
KEY `project_id` (`project_id`),
KEY `record_arm` (`record`,`arm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Save new record names to prevent record duplication';
ALTER TABLE `redcap_new_record_cache`
ADD FOREIGN KEY (`arm_id`) REFERENCES `redcap_events_arms` (`arm_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
set foreign_key_checks=1;

-- Disable Copy Change Reason module at the system level
delete s.* from redcap_external_modules e, redcap_external_module_settings s where e.directory_prefix = 'copy_change_reason_on_import'
and e.external_module_id = s.external_module_id and s.project_id is null and `key` = 'version';

-- New FHIR settings
INSERT INTO redcap_config (field_name, value) VALUES
('fhir_break_the_glass_department_type', ''),
('fhir_break_the_glass_patient_type', '');

-- Modify log_view_requests table
ALTER TABLE `redcap_log_view_requests` CHANGE `lvr_id` `lvr_id` BIGINT(19) NOT NULL AUTO_INCREMENT;
EOF;

print $sql;


// Add Messenger system notification
$title = "New Action Tag: @IF";
$msg = "The @IF action tag allows you to set condition-specific action tags for a field that is very similar to using the if() function in branching logic or calculations - e.g., <code>@IF(CONDITION, ACTION TAGS if condition is TRUE, ACTION TAGS if condition is FALSE)</code>. Simply provide a condition using normal logic syntax, and it will implement one set of action tags or another based on whether that condition is true or false. For example, you can have <code>@IF([yes_no] = '1', @HIDDEN, @HIDE-CHOICE='3' @READ-ONLY)</code>, in which it will implement @HIDDEN if the 'yes_no' field's value is '1', otherwise, it will implement the two action tags @HIDECHOICE='3' and @READ-ONLY.

Click the red Action Tags button on the Project Setup page for more details.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);
// <b class=\"fs15\">New feature: \":inline\" Piping Option</b>