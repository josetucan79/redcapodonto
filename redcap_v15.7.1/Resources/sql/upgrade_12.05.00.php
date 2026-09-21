<?php

$sql = "
DELETE FROM `redcap_surveys_scheduler` WHERE instance = 'AFTER_FIRST';
ALTER TABLE `redcap_surveys_scheduler`
    ADD `num_recurrence` INT(5) NOT NULL DEFAULT '0' AFTER `instance`,
    ADD `units_recurrence` enum('DAYS','HOURS','MINUTES') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DAYS' AFTER `num_recurrence`,
    ADD `max_recurrence` INT(5) NULL DEFAULT NULL AFTER `units_recurrence`,
    DROP INDEX `survey_event_instance`, 
    ADD UNIQUE `survey_event` (`survey_id`, `event_id`);

CREATE TABLE `redcap_surveys_scheduler_recurrence` (
`ssr_id` int(10) NOT NULL AUTO_INCREMENT,
`ss_id` int(10) DEFAULT NULL,
`creation_date` datetime DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`instrument` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`times_sent` smallint(4) DEFAULT NULL,
`last_sent` datetime DEFAULT NULL,
`status` enum('IDLE','QUEUED','SENDING') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IDLE',
`first_send_time` datetime DEFAULT NULL,
`next_send_time` datetime DEFAULT NULL,
PRIMARY KEY (`ssr_id`),
UNIQUE KEY `ss_id_record_event_instrument` (`ss_id`,`record`,`event_id`,`instrument`),
KEY `creation_date` (`creation_date`),
KEY `event_record` (`event_id`,`record`),
KEY `first_send_time` (`first_send_time`),
KEY `last_sent` (`last_sent`),
KEY `next_send_time_status_ss_id` (`next_send_time`,`status`,`ss_id`),
KEY `ss_id_status_times_sent` (`status`,`ss_id`,`times_sent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_surveys_scheduler_recurrence`
ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`ss_id`) REFERENCES `redcap_surveys_scheduler` (`ss_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";

print $sql;


// Add Messenger system notification
$title = "New feature: Repeating ASIs";
$msg = "You can now set Automated Survey Invitations (ASIs) to send multiple times on a recurring basis for any given survey in your project. If the survey is a repeating instrument or if it exists on a repeating event, you will see a new section \"How many times to send it\" in the ASI setup popup in the Online Designer. There you may set the ASI to send survey invitations repeatedly at a regular interval, in which it can repeat forever or a set number of times. The new repeating ASI feature works similarly to recurring alerts for Alerts & Notifications.

<b class=\"fs15\">New Smart Variable: [new-instance]</b>
This new Smart Variable can be appended to [survey-link] or [form-link] to create a link to a new, not-yet-created repeating instance for the current record. For example, you can create a recurring alert that contains <code>[survey-url:repeating_survey][new-instance]</code> in the text, in which it will send the recipient a survey link for creating a new instance of a repeating survey.

<b class=\"fs15\">Embedding images in text & emails</b>
You may now embed an inline image into the text of a survey invitation, an alert, or a field label on a form/survey by clicking the <i class=\"far fa-image\"></i> icon in the rich text editor, and then uploading an image from your local device. Anywhere that the rich text editor is used, you may embed an image into its text (with one exception: the @RICHTEXT action tag on public surveys).";
print Messenger::generateNewSystemNotificationSQL($title, $msg);