<?php


// Add new WebDAV auth setting if doesn't already exist
$file_upload_vault_filesystem_authtype = (isset($GLOBALS['file_upload_vault_filesystem_authtype']) && $GLOBALS['file_upload_vault_filesystem_authtype'] != '') ? $GLOBALS['file_upload_vault_filesystem_authtype'] : 'AUTH_DIGEST';
$pdf_econsent_filesystem_authtype = (isset($GLOBALS['pdf_econsent_filesystem_authtype']) && $GLOBALS['pdf_econsent_filesystem_authtype'] != '') ? $GLOBALS['pdf_econsent_filesystem_authtype'] : 'AUTH_DIGEST';
$record_locking_pdf_vault_filesystem_authtype = (isset($GLOBALS['record_locking_pdf_vault_filesystem_authtype']) && $GLOBALS['record_locking_pdf_vault_filesystem_authtype'] != '') ? $GLOBALS['record_locking_pdf_vault_filesystem_authtype'] : 'AUTH_DIGEST';
$sql = "
replace into redcap_config (field_name, value) values 
('file_upload_vault_filesystem_authtype', '".db_escape($file_upload_vault_filesystem_authtype)."'),
('pdf_econsent_filesystem_authtype', '".db_escape($pdf_econsent_filesystem_authtype)."'),
('record_locking_pdf_vault_filesystem_authtype', '".db_escape($record_locking_pdf_vault_filesystem_authtype)."');
";

print $sql;


$sql = "
ALTER TABLE `redcap_user_rights` 
    ADD `data_export_instruments` TEXT NULL DEFAULT NULL AFTER `data_export_tool`,
	CHANGE `data_export_tool` `data_export_tool` TINYINT(1) NULL DEFAULT NULL;
ALTER TABLE `redcap_user_roles` 
    ADD `data_export_instruments` TEXT NULL DEFAULT NULL AFTER `data_export_tool`,
	CHANGE `data_export_tool` `data_export_tool` TINYINT(1) NULL DEFAULT NULL;
";

print $sql;


// Add Messenger system notification
$title = "New feature: Survey Start Time and new Smart Variables";
$msg = "REDCap now collects when participants begin a survey (i.e., the initial time the survey page is opened). Going forward, any responses collected (partial or completed) will have their start time displayed at the top of the data entry form when viewing the response.

You can access the start time via piping by using the new Smart Variables <b>[survey-time-started:instrument]</b> and <b>[survey-date-started:instrument]</b>, which can be used inside the @DEFAULT or @CALCTEXT action tags, among other places. Additionally, you can obtain the total amount of time that has elapsed since the survey was started (in seconds, minutes, etc.) by using <b>[survey-duration:instrument:units]</b> and <b>[survey-duration-completed:instrument:units]</b>. See the Smart Variable documentation for more info.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);

// Add Messenger system notification
$title = "Improved data export privileges";
$msg = "You may now specify instrument-level privileges regarding a user's data export capabilities on the User Rights page in a project. A user may be given \"No Access\", \"De-Identified\", \"Remove All Identifier Fields\", or \"Full Data Set\" data export rights for EACH data collection instrument. This improvement will make it much easier to match a user's Data Exports Rights with their Data Viewing Rights, if you wish, and will give you more granular control regarding what data a user can export from your project.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);