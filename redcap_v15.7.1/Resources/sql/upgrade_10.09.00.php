<?php

$sql = "
ALTER TABLE `redcap_projects` 
    ADD `twilio_delivery_preference_field_map` VARCHAR(100) NULL DEFAULT NULL AFTER `twilio_multiple_sms_behavior`;
ALTER TABLE `redcap_surveys` 
    ADD `offline_instructions` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `instructions`,
    ADD `stop_action_acknowledgement` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `acknowledgement`,
    ADD `stop_action_delete_response` TINYINT(1) NOT NULL DEFAULT '0' AFTER `stop_action_acknowledgement`;
";

print $sql;


// Add Messenger system notification
$title = "New survey features!";
$msg = "REDCap has new survey options that we hope you'll enjoy. Details on the features described below can be found on the Online Designer->Survey Settings page for any given survey instrument.

<b class=\"fs14\">Alternative survey completion text if the survey ends via a Stop Action</b>
You can now set alternative survey completion text that is displayed in place of your standard survey completion text whenever a survey ends via a Stop Action on any field. For example, this could be useful if it doesn't make sense for ineligible participants to see the same survey completion text as those who completed the survey fully.

<b class=\"fs14\">Prevent survey responses from being saved if the survey ends via a Stop Action</b>
If desired, you can choose to prevent submitted responses from being saved in the project if the survey ends via a Stop Action on any field. This is useful if you do not wish to keep the data for ineligible participants, for example. This means that if a one-page public survey is started but ends via Stop Action, no data from that response will be saved into the project (i.e., no new record will be created).

<b class=\"fs14\">Custom offline message for surveys in offline status</b>
Provide custom text that is displayed to participants only when the survey is offline. This custom text will be displayed in place of the default offline text on the survey while it is in offline mode. This text can be set at the top of the Survey Settings page.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);