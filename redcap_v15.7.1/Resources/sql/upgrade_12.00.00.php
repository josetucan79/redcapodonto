<?php

$sql = "";

// Add Twilio error log table
$sql .= "
CREATE TABLE `redcap_twilio_error_log` (
`error_id` int(10) NOT NULL AUTO_INCREMENT,
`ssq_id` int(10) DEFAULT NULL,
`alert_sent_log_id` int(10) DEFAULT NULL,
`error_message` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`error_id`),
KEY `alert_sent_log_id` (`alert_sent_log_id`),
KEY `ssq_id` (`ssq_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_twilio_error_log`
ADD FOREIGN KEY (`alert_sent_log_id`) REFERENCES `redcap_alerts_sent_log` (`alert_sent_log_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`ssq_id`) REFERENCES `redcap_surveys_scheduler_queue` (`ssq_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";


// Add new col to user information table (if not added yet)
$sql2 = "SHOW CREATE TABLE redcap_user_information";
$q = db_query($sql2);
if ($q && db_num_rows($q) == 1)
{
	// Get the 'create table' statement to parse
	$result = db_fetch_array($q);
	$createTableStatement = strtolower($result[1]);
	if (strpos($createTableStatement, "messaging_email_queue_time") === false) {
		$sql .= "ALTER TABLE `redcap_user_information` ADD `messaging_email_queue_time` DATETIME NULL DEFAULT NULL AFTER `messaging_email_general_system`, ADD INDEX (`messaging_email_queue_time`);\n";
	}
}
$sql .= "update redcap_crons set cron_instances_max = 5 where cron_name = 'UserMessagingEmailNotifications';\n";


// Create SQL to importing existing Form Render Skip Logic module settings
$FormDisplayLogicImport = "";
$sql2 = "SELECT DISTINCT s.project_id, t.`key`, t.`value` FROM redcap_external_modules e, redcap_external_module_settings s, redcap_external_module_settings t
		WHERE e.directory_prefix = 'form_render_skip_logic' AND e.external_module_id = s.external_module_id AND s.project_id IS NOT NULL 
		AND s.`key` = 'enabled' AND s.`value` = 'true' AND t.project_id = s.project_id AND t.external_module_id = s.external_module_id";
$q = db_query($sql2);

$configSettings = array();
while ($row = db_fetch_assoc($q)) {
	$pid = $row['project_id'];
	if ($row['key'] == 'prevent_hidden_data') {
		$configSettings[$pid][$row['key']] = $row['value'];
	} else {
		$json = json_decode($row['value'], true);
		if (!is_array($json) || empty($json)) continue;

		$configSettings[$pid][$row['key']] = $json;
	}
}

$insertData = array();
if (!empty($configSettings)) {
	foreach ($configSettings as $pid => $attr) {
		$Proj = new Project($pid);
		$insertData[$pid]['prevent_hiding_filled_forms'] = ($configSettings[$pid]['prevent_hidden_data'] == 'true') ? 1 : 0;

		$no_of_controls = count($attr['control_fields']);
		for ($i = 0; $i < $no_of_controls; $i++) {
			if ($attr['control_mode'][$i] == 'default') {
				$field = "";
				if ($attr['control_event_id'][$i] != "") {
					$unique_event_name = $Proj->getUniqueEventNames($attr['control_event_id'][$i]);
					if ($unique_event_name != "") {
						$field = "[" . $unique_event_name . "]";
					}
				}
				$field .= "[".$attr['control_field_key'][$i]."]";
			} else if ($attr['control_mode'][$i] == 'advanced') {
				$field = $attr['control_piping'][$i];
			}
			$no_of_logics = count($attr['branching_logic'][$i]);
			for ($j = 0; $j < $no_of_logics; $j++) {
				$operator = $attr['condition_operator'][$i][$j];
				$value = $attr['condition_value'][$i][$j];
				if ($operator == '')  $operator = "=";
				if ($field != "" && $operator != "" && $value != "") {
					$elements['control-condition'] = $field.' '.$operator.' "'.$value.'"';
					$elements['form-name'] = array();

					foreach ($attr['target_forms'][$i][$j] as $form) {
						if ($form != '') {
							if (empty($attr['target_events'][$i][$j])) {
								$elements['form-name'][] = $form."-";
							} else {
								foreach ($attr['target_events'][$i][$j] as $eventId) {
									//if ($eventId == '') $eventId = $Proj->firstEventId;
									if (isset($attr['target_events_select'][$i][$j]) && !$attr['target_events_select'][$i][$j]) {
										$elements['form-name'][] = $form."-";
									} else {
										$elements['form-name'][] = $form."-".$eventId;
									}
								}
							}
						}
					}
					$insertData[$pid]['outer-list'][] = $elements;
				}
			}
		}
	}
}

if (!empty($insertData)) {
	foreach ($insertData as $pid => $list) {
		$hide_filled_forms = $list['prevent_hiding_filled_forms'] ? 0 : 1;

		$FormDisplayLogicImport .= "-- FRSL MIGRATION (PID $pid)\n";
		$FormDisplayLogicImport .= "UPDATE redcap_projects SET hide_filled_forms = '$hide_filled_forms', form_activation_survey_autocontinue = '1' WHERE project_id = '".$pid."';\n";
		$FormDisplayLogicImport .= "UPDATE redcap_external_modules e, redcap_external_module_settings s SET `value` = 'false' WHERE e.directory_prefix = 'form_render_skip_logic' AND e.external_module_id = s.external_module_id AND s.project_id = '".$pid."' AND `key` = 'enabled';\n";

		foreach ($list['outer-list'] as $insert_controls) {
			// Insert
			$FormDisplayLogicImport .= "INSERT INTO redcap_form_display_logic_conditions (project_id, control_condition) VALUES ('" . $pid . "', '" . db_escape($insert_controls['control-condition']) . "');\nset @condition_id = LAST_INSERT_ID();\n";
			foreach (array_unique($insert_controls['form-name']) as $form_event_pair) {
				list($form_name, $event_id) = explode("-", $form_event_pair);
				// Insert Target Forms
				$FormDisplayLogicImport .= "INSERT INTO redcap_form_display_logic_targets (control_id, form_name, event_id) VALUES (@condition_id, '" . db_escape($form_name) . "', " . checkNull($event_id) . ");\n";
			}
		}
	}
}
if ($FormDisplayLogicImport != '') {
	$FormDisplayLogicImport = "-- Import existing Form Render Skip Logic module settings\n$FormDisplayLogicImport";
}
$sql .= <<<EOF
-- Integrate Form Render Skip Logic external module
ALTER TABLE `redcap_projects` ADD `hide_filled_forms` TINYINT(1) NOT NULL DEFAULT '1';
ALTER TABLE `redcap_projects` ADD `form_activation_survey_autocontinue` TINYINT(1) NOT NULL DEFAULT '0';
CREATE TABLE `redcap_form_display_logic_conditions` (
`control_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`control_condition` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`control_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `redcap_form_display_logic_targets` (
`control_id` int(10) DEFAULT NULL,
`form_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
UNIQUE KEY `event_form_control` (`event_id`,`form_name`,`control_id`),
KEY `control_event` (`control_id`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `redcap_form_display_logic_conditions`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redcap_form_display_logic_targets`
ADD FOREIGN KEY (`control_id`) REFERENCES `redcap_form_display_logic_conditions` (`control_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;

$FormDisplayLogicImport

-- Disable Form Render Skip Logic module at the system level
DELETE s.* FROM redcap_external_modules e, redcap_external_module_settings s WHERE e.directory_prefix = 'form_render_skip_logic'
AND e.external_module_id = s.external_module_id AND s.project_id IS NULL AND `key` = 'version';
EOF;


// New tables
$sql .= "
CREATE TABLE `redcap_multilanguage_config` (
`project_id` int(10) DEFAULT NULL,
`lang_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
`value` text COLLATE utf8mb4_unicode_ci NOT NULL,
UNIQUE KEY `project_lang_name` (`project_id`,`lang_id`,`name`),
KEY `lang_name` (`lang_id`,`name`),
KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_multilanguage_config_temp` (
`project_id` int(10) DEFAULT NULL,
`lang_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
`value` text COLLATE utf8mb4_unicode_ci NOT NULL,
UNIQUE KEY `project_lang_name` (`project_id`,`lang_id`,`name`),
KEY `lang_name` (`lang_id`,`name`),
KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_multilanguage_metadata` (
`project_id` int(10) DEFAULT NULL,
`lang_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`type` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
`name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`index` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`hash` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
UNIQUE KEY `project_lang_type_name_index` (`project_id`,`lang_id`,`type`,`name`,`index`),
KEY `lang_type_name_index` (`lang_id`,`type`,`name`,`index`),
KEY `name` (`name`),
KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_multilanguage_metadata_temp` (
`project_id` int(10) DEFAULT NULL,
`lang_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`type` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
`name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`index` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`hash` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
UNIQUE KEY `project_lang_type_name_index` (`project_id`,`lang_id`,`type`,`name`,`index`),
KEY `lang_type_name_index` (`lang_id`,`type`,`name`,`index`),
KEY `name` (`name`),
KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_multilanguage_ui` (
`project_id` int(10) DEFAULT NULL,
`lang_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`item` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
`translation` text COLLATE utf8mb4_unicode_ci NOT NULL,
UNIQUE KEY `project_lang_item` (`project_id`,`lang_id`,`item`),
KEY `item` (`item`),
KEY `lang_item` (`lang_id`,`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_multilanguage_ui_temp` (
`project_id` int(10) DEFAULT NULL,
`lang_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`item` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
`translation` text COLLATE utf8mb4_unicode_ci NOT NULL,
UNIQUE KEY `project_lang_item` (`project_id`,`lang_id`,`item`),
KEY `item` (`item`),
KEY `lang_item` (`lang_id`,`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_multilanguage_snapshots` (
`snapshot_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) NOT NULL,
`edoc_id` int(10) DEFAULT NULL,
`created_by` int(10) DEFAULT NULL COMMENT 'References a uu_id in the redcap_user_information table',
`deleted_by` int(10) DEFAULT NULL COMMENT 'References a uu_id in the redcap_user_information table',
PRIMARY KEY (`snapshot_id`),
KEY `project_id` (`project_id`),
KEY `edoc_id` (`edoc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_multilanguage_config`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_multilanguage_config_temp`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_multilanguage_metadata`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_multilanguage_metadata_temp`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_multilanguage_ui`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_multilanguage_ui_temp`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_multilanguage_snapshots`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`edoc_id`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`created_by`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`deleted_by`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;
";

// Additions to existing tables
$sql .= "
ALTER TABLE `redcap_outgoing_email_sms_log` ADD `lang_id` VARCHAR(50) NULL DEFAULT NULL AFTER `hash`, ADD INDEX `lang_id` (`lang_id`);
ALTER TABLE `redcap_surveys_emails` ADD `lang_id` VARCHAR(50) NULL DEFAULT NULL AFTER `append_survey_link`, ADD INDEX `lang_id` (`lang_id`);
";


print $sql;

// Add Messenger system notification
$title = "New feature: Multi-Language Management";
$msg = "Using REDCap's new Multi-Language Management feature, you can create and configure multiple display languages for your projects for surveys, data entry forms, alerts, survey invitations, etc. You can design data collection instruments and have them be displayed in any language that you have defined and translated so that your survey participants or data entry persons can view the text in their preferred language. This eliminates the need to create multiple instruments or projects to handle multiple languages.

When entering data on a data entry form or survey, your users and participants will be able to choose their language from a drop-down list on the page to easily switch to their preferred language for the text displayed on the page. This feature allows you to translate all text related to the data entry process, both for surveys and for data entry forms. Even various survey settings and email text can be translated. 

If you have Project Design/Setup privileges in a project, you will see a link to the Multi-Language Management page on the left-hand menu. All information for this new feature is located there if you wish to learn more.

<b class=\"fs15\">New feature: Form Display Logic</b>
A new feature called Form Display Logic provides a way to use conditional logic to disable specific data entry forms that are displayed on the Record Status Dashboard, Record Home Page, or the form list on the left-hand menu. You might think of it as \"form-level branching logic\". Form Display Logic can be very useful if you wish to prevent users from entering data on a specific form or event until certain conditions have been met. For more information, click the \"Form Display Logic\" button in the Online Designer.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);