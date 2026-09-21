<?php

$sql = "
ALTER TABLE `redcap_surveys` 
    ADD `pdf_save_to_field` VARCHAR(100) NULL AFTER `end_of_survey_pdf_download`, 
    ADD `pdf_save_to_event_id` INT(10) NULL AFTER `pdf_save_to_field`, 
    ADD INDEX (`pdf_save_to_event_id`);
ALTER TABLE `redcap_surveys` ADD FOREIGN KEY (`pdf_save_to_event_id`) REFERENCES `redcap_events_metadata`(`event_id`) ON DELETE SET NULL ON UPDATE CASCADE;
UPDATE `redcap_crons` SET `cron_max_run_time` = '1800' WHERE cron_name = 'UserMessagingEmailNotifications';
ALTER TABLE `redcap_web_service_cache` ADD INDEX `service_cat_value` (`service`, `category`, `value`);
";

print $sql;


// Add Messenger system notification
$title = "New feature: Save Survey PDF to Field";
$msg = "The new \"Save Survey PDF to Field\" feature allows you to automatically save a PDF copy of a participant's survey response to a File Upload field upon completion of the survey. This may be useful if you want to store the snapshot of the response immediately after being submitted, or it can be used to send the PDF of the response as an email attachment via Alerts & Notifications, among other things. This new feature can be enabled on the Survey Settings page for any data collection instrument in the Online Designer.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);