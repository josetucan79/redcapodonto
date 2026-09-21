<?php

// This same code below was added to 15.0.23 LTS. This means that anyone upgrading from 15.0.23 or higher (but less than 15.1.0), should NOT rerun this script.
if (!(Upgrade::getDecVersion($current_version) >= 150023 && Upgrade::getDecVersion($current_version) < 150100))
{
	require_once dirname(dirname(dirname(__FILE__)))."/Classes/MyCap/Participant.php";
	require_once dirname(dirname(dirname(__FILE__)))."/Classes/MyCap/DynamicLink.php";

	// SQL to add new field "acknowledged_app_link" to DB table "redcap_mycap_projects"
	$sql = "
-- add acknowledged_app_link to mycap projects table
ALTER TABLE `redcap_mycap_projects`
	ADD `acknowledged_app_link` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'User acknowledged a change from join URL/Dynamic link to new app link if TRUE';
\n";

	// Get all values for each project (currently enable for MyCap + Projects enabled for MyCap in past) and transform
	$project_sql = "SELECT project_id FROM redcap_mycap_projects ORDER BY project_id;";
	$q = db_query($project_sql);
	while ($row = db_fetch_assoc($q)) {

		$project_id = $row['project_id'];
		$Proj = new Project($project_id);

		// Update access html in all alerts
		$alertOb = new \Alerts();
		foreach ($alertOb->getAlertSettings($project_id) as $alert_id => $alert) {
			$alert_message = \Vanderbilt\REDCap\Classes\MyCap\Participant::translateToNewAppLinkUrls($alert['alert_message']);
			$saved_alert_message = str_replace('&amp;', '&', $alert['alert_message']);
			if ($alert_message != $saved_alert_message) {
				$sql .= "UPDATE redcap_alerts SET alert_message = " . checkNull($alert_message) . " WHERE project_id = " . $project_id . " AND alert_id = $alert_id;\n";
			}
		}

		// Update access html in all surveys - completion texts
		foreach ($Proj->surveys as $this_survey_id => $survey_attr) {
			// Update Survey Completion text
			$acknowledgement = \Vanderbilt\REDCap\Classes\MyCap\Participant::translateToNewAppLinkUrls($survey_attr['acknowledgement']);
			$saved_acknowledgement = str_replace('&amp;', '&', $survey_attr['acknowledgement']);
			if ($acknowledgement != $saved_acknowledgement) {
				$sql .= "UPDATE redcap_surveys SET acknowledgement = " . checkNull($acknowledgement) . " WHERE project_id = " . $project_id . " AND survey_id = $this_survey_id;\n";
			}

			// Update Survey Confirmation Email content text
			$confirmation_email_content = \Vanderbilt\REDCap\Classes\MyCap\Participant::translateToNewAppLinkUrls($survey_attr['confirmation_email_content']);
			$saved_confirmation_email_content = str_replace('&amp;', '&', $survey_attr['confirmation_email_content']);
			if ($confirmation_email_content != $saved_confirmation_email_content) {
				$sql .= "UPDATE redcap_surveys SET confirmation_email_content = " . checkNull($confirmation_email_content) . " WHERE project_id = " . $project_id . " AND survey_id = $this_survey_id;\n";
			}

			// Update Survey Scheduler Email content text
			$email_contents = array();
			$scheduler_sql = "SELECT ss_id, email_content FROM redcap_surveys_scheduler WHERE survey_id = '" . $this_survey_id . "';";
			$qs = db_query($scheduler_sql);
			while ($row_s = db_fetch_assoc($qs)) {
				$email_contents[$row_s['ss_id']] = $row_s['email_content'];
			}

			if (count($email_contents) > 0) {
				foreach ($email_contents as $ssId => $emailContent) {
					$newEmailContent = \Vanderbilt\REDCap\Classes\MyCap\Participant::translateToNewAppLinkUrls($emailContent);
					if ($newEmailContent != $emailContent) {
						$sql .= "UPDATE redcap_surveys_scheduler SET email_content = " . checkNull($newEmailContent) . " WHERE ss_id = '" . $ssId . "';\n";
					}
				}
			}
		}

		// Update field labels (replace old dynamic link to new app link)
		foreach ($Proj->metadata as $field => $attr) {
			$field_label = \Vanderbilt\REDCap\Classes\MyCap\Participant::translateToNewAppLinkUrls($attr['element_label']);
			$saved_field_label = str_replace('&amp;', '&', $attr['element_label']);
			if ($field_label != $saved_field_label) {
				$sql .= "UPDATE redcap_metadata SET element_label = '" . db_escape($field_label) . "' WHERE project_id = " . $project_id . " AND field_name = '" . db_escape($attr['field_name']) . "';\n";
			}
		}

		// Make acknowledged_app_link = 1 in db for existing projects with no records/participants
		list ($participant_list, $participant_count) = \Vanderbilt\REDCap\Classes\MyCap\Participant::getParticipantList($project_id);
		if (empty($participant_list)) {
			$sql .= "UPDATE redcap_mycap_projects SET acknowledged_app_link = '1' WHERE project_id = '" . $project_id . "';\n";
		}

		// Remove project from cache to free up memory
		unset(Project::$project_cache[$project_id]);
	}

	
	print $sql;

	// Add Messenger system notification
	$title = "MyCap Notice: \"Dynamic Link\" replaced with \"App Link\" solution";
	$msg = "Google Firebase will stop supporting their <a href='https://firebase.google.com/docs/dynamic-links' target='_blank'>Dynamic Link</a> solution after August 25, 2025. In response, MyCap has a replacement solution called App Links for joining projects in MyCap. QR codes are not affected.\n
	Your MyCap projects have been updated, so no immediate action is needed. New participants will receive the new App Link when they are invited to join the project and the former Dynamic Link has been replaced with the new App Link anywhere you used the MyCap template text for inviting participants.\n
	Note: If existing participants need to rejoin your project after August 25, 2025, they will need the updated App Link or the QR code. Please consider sending an announcement to your MyCap participants to contact you if they change devices. Please read 
	<a href='https://projectmycap.org/wp-content/uploads/2025/04/MyCapApp_NewAppAppLinks.pdf' target='_blank' class='fs13' style='text-decoration:underline' rel='noopener noreferrer'>New App Links in MyCap Guide</a>";

	if ($GLOBALS['mycap_enabled_global'] == '1') print Messenger::generateNewSystemNotificationSQL($title, $msg);
}