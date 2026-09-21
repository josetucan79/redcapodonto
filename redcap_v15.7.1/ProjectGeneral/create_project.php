<?php

// Config for non-project pages
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use MultiLanguageManagement\MultiLanguage;
use Vanderbilt\REDCap\Classes\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\Annotation;
use Vanderbilt\REDCap\Classes\MyCap\Task;

// Begin transaction
db_query("SET AUTOCOMMIT=0");
db_query("BEGIN");

// Ensure this is a POST request
if (!isset($_POST['app_title']) || empty($_POST['app_title'])) exit("ERROR!");

// Make sure user has ability to create projects
$userInfo = User::getUserInfo(USERID);
if (!$userInfo['allow_create_db'] && !$super_user) exit("ERROR: You do not have Create Project privileges!");

// If a normal user tries to create a project when only super users can create projects, then return error
if ($superusers_only_create_project && !$super_user) exit("ERROR: You do not have Create Project privileges!");

// Check if any errors occurred when uploading an ODM file (if applicable)
if (isset($_FILES['odm'])) ODM::checkErrorsOdmFileUpload($_FILES['odm']);

// Remove any HTML in the title
$_POST['app_title'] = Project::cleanTitle($_POST['app_title']);

// Create new name derived from Project Title (and check for duplication with existing projects)
$new_app_name = Project::getValidProjectName($_POST['app_title']);

// Set flag if creating the project from a template
$isTemplate = (isset($_POST['copyof']) && isinteger($_POST['copyof']) && isset($_POST['project_template_radio']) && $_POST['project_template_radio'] == '1');
$templateCopyRecords = $templateMyCapEnabled = false;
// Set default form completion status to "Complete"
$taskCompletionStatus = 2;
if ($isTemplate) {
    $sql = "select copy_records from redcap_projects_templates where project_id = ".$_POST['copyof'];
    $q = db_query($sql);
    $templateCopyRecords = (db_result($q, 0) == '1');
    $sql = "select mycap_enabled, task_complete_status from redcap_projects where project_id = ".$_POST['copyof'];
    $q = db_query($sql);
    $templateMyCapEnabled = (db_result($q, 0, 'mycap_enabled') == '1');
    if ($templateMyCapEnabled) {
        // If MyCap is enabled on the template, then use whatever Form Completion Status setting that the template has.
        $taskCompletionStatus = db_result($q, 0, 'task_complete_status');
    } else {
        // If MyCap is NOT enabled on the template, always set Form Completion Status setting to Complete.
        $taskCompletionStatus = 2;
    }
}

// Catch if user selected multiple Research options for Purpose
$_POST['purpose_other'] = isset($_POST['purpose_other']) ? Project::purpToStr($_POST['purpose_other']) : '';

// Make sure other parameters were set properly
$_POST['repeatforms']     = (isset($_POST['repeatforms']) && in_array($_POST['repeatforms'], array(0, 1))) ? (int)$_POST['repeatforms'] : 0;
$_POST['purpose']         = (isset($_POST['purpose']) && isinteger($_POST['purpose'])) ? (int)$_POST['purpose'] : 'NULL';
$_POST['scheduling']      = (isset($_POST['scheduling']) && in_array($_POST['scheduling'], array(0, 1))) ? (int)$_POST['scheduling'] : 0;
$_POST['surveys_enabled'] = (isset($_POST['surveys_enabled']) && isinteger($_POST['surveys_enabled'])) ? (int)$_POST['surveys_enabled'] : 0;
$_POST['randomization']   = (isset($_POST['randomization']) && in_array($_POST['randomization'], array(0, 1))) ? (int)$_POST['randomization'] : 0;
$_POST['mycap_enabled']   = (($templateMyCapEnabled || (isset($_POST['copy_mycap_mobile_app_content']) && $_POST['copy_mycap_mobile_app_content'] == 'on'))
                            && (SUPER_USER || $mycap_enable_type != 'admin')) ? 1 : 0;

// Enable auto-numbering for all new projects
$auto_inc_set = 1;
// Data Mart project?
$ehrDataMartProject = ($fhir_data_mart_create_project && $_POST['project_template_radio'] == '3');
// Set the log_event table
$log_event_table = Logging::getSmallestLogEventTable();
$data_table = Records::getSmallestDataTable();

/**
 * Insert defaults and user-defined values for this new project
 */
// Insert into redcap_projects table
$sql = "insert into redcap_projects (project_name, scheduling, repeatforms, purpose, purpose_other, app_title, creation_time, created_by,
		project_pi_firstname, project_pi_mi, project_pi_lastname, project_pi_email, project_pi_alias, project_pi_username, project_irb_number,
		project_grant_number, surveys_enabled, mycap_enabled, auto_inc_set, randomization, template_id, data_resolution_enabled, project_note, 
        datamart_enabled, log_event_table, data_table, google_recaptcha_enabled, task_complete_status) values
		('$new_app_name', {$_POST['scheduling']}, {$_POST['repeatforms']}, {$_POST['purpose']},
		" . checkNull($_POST['purpose_other']) . ",
		'".db_escape($_POST['app_title'])."', '".NOW."', (select ui_id from redcap_user_information where username = '".db_escape($userid)."' limit 1),
		" . ((!isset($_POST['project_pi_firstname']) || $_POST['project_pi_firstname'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_firstname'])."'") . ",
		" . ((!isset($_POST['project_pi_mi']) || $_POST['project_pi_mi'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_mi'])."'") . ",
		" . ((!isset($_POST['project_pi_lastname']) || $_POST['project_pi_lastname'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_lastname'])."'") . ",
		" . ((!isset($_POST['project_pi_email']) || $_POST['project_pi_email'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_email'])."'") . ",
		" . ((!isset($_POST['project_pi_alias']) || $_POST['project_pi_alias'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_alias'])."'") . ",
		" . ((!isset($_POST['project_pi_username']) || $_POST['project_pi_username'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_username'])."'") . ",
		" . ((!isset($_POST['project_irb_number']) || $_POST['project_irb_number'] == "") ? "NULL" : "'".db_escape($_POST['project_irb_number'])."'") . ",
		" . ((!isset($_POST['project_grant_number']) || $_POST['project_grant_number'] == "") ? "NULL" : "'".db_escape($_POST['project_grant_number'])."'") . ",
		{$_POST['surveys_enabled']}, {$_POST['mycap_enabled']}, $auto_inc_set, {$_POST['randomization']}, 
		".($isTemplate ? $_POST['copyof'] : "null").", '".($field_comment_log_enabled_default == '0' ? '0' : '1')."',
		".checkNull(trim($_POST['project_note'])).", ".($ehrDataMartProject ? '1' : '0').", '$log_event_table', '$data_table', {$GLOBALS['google_recaptcha_default']}, $taskCompletionStatus)";
$q = db_query($sql);
if (!$q || db_affected_rows() != 1) {
	print db_error();
	queryFail($sql);
}
// Get this new project's project_id
$new_project_id = db_insert_id();
define("PROJECT_ID", $new_project_id);
$Proj = new Project($new_project_id);
$user = isset($_POST['username']) ? $_POST['username'] : $userid;
ProjectFolders::addNewProjectFolders(User::getUserInfo($user), $new_project_id, $_POST);

// Get default values for redcap_projects table columns
$redcap_projects_defaults = getTableColumns('redcap_projects');

// Insert project defaults into redcap_projects
Project::setDefaults($new_project_id);


/**
 * COPYING PROJECT OR CREATING NEW PROJECT USING TEMPLATE
 */
## If copying an existing project
if (isset($_POST['copyof']) && is_numeric($_POST['copyof']))
{
	// Message flag used for dialog pop-up
	$msg_flag = ($isTemplate) ? "newproject" : "copiedproject";

	$copyof_project_id = $_POST['copyof'];

	// Log this in the copied project
	if (!$isTemplate) {
		Logging::logEvent("","redcap_projects","MANAGE",$copyof_project_id,"project_id = $copyof_project_id",
			"Copy project as PID=$new_project_id (\"{$_POST['app_title']}\")", "", "", $copyof_project_id);
	}

	// Log this in the newly created project
	$copiedProjectTitle = strip_tags(db_result(db_query("select app_title from redcap_projects where project_id = $copyof_project_id"), 0));
	Logging::logEvent("","redcap_projects","MANAGE",$new_project_id,"project_id = $new_project_id",($isTemplate ? "Create project using template" : "Copy project from PID=$copyof_project_id (\"$copiedProjectTitle\")"));

	// Verify project_id of original
	$q = db_query("select randomization from redcap_projects where project_id = $copyof_project_id limit 1");
	if (!$q || db_num_rows($q) < 1) {
		db_query("ROLLBACK");
		db_query("SET AUTOCOMMIT=1");
		exit("ERROR!");
	}
	$row = db_fetch_assoc($q);

	// Set randomization flag for project
	$randomization = (isset($row['randomization'])) ? $row['randomization'] : 0;

	// Copy metadata fields
	$sql = "insert into redcap_metadata (project_id, field_name, field_phi, form_name, form_menu_description, field_order,
			field_units, element_preceding_header, element_type, element_label, element_enum, element_note, element_validation_type,
			element_validation_min, element_validation_max, element_validation_checktype, branching_logic, field_req, edoc_id,
			edoc_display_img, custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc, video_url, video_display_inline)
			select '$new_project_id', field_name, field_phi, form_name, form_menu_description, field_order,
			field_units, element_preceding_header, element_type, element_label, element_enum, element_note, element_validation_type,
			element_validation_min, element_validation_max, element_validation_checktype, branching_logic, field_req, NULL,
			edoc_display_img, custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc, video_url, video_display_inline
			from redcap_metadata where project_id = $copyof_project_id";
	$q = db_query($sql);

	## CHECK FOR EDOC FILE ATTACHMENTS: Copy all files on the server, if being used (one at a time)
	$sql = "select field_name, edoc_id from redcap_metadata where project_id = $copyof_project_id and edoc_id is not null";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		// Copy file on server
		$new_edoc_id = copyFile($row['edoc_id'], $new_project_id);
		if (is_numeric($new_edoc_id))
		{
			// Now update new field's edoc_id value
			$sql = "update redcap_metadata set edoc_id = $new_edoc_id where project_id = $new_project_id and field_name = '{$row['field_name']}'";
			db_query($sql);
		}
	}

	// Copy arms/events (one event at a time)
	$eventid_translate = array(); // Store old event_id as key and new event_id as value
	$q = db_query("select arm_id, arm_num, arm_name from redcap_events_arms where project_id = $copyof_project_id");
	while ($row = db_fetch_assoc($q)) {
		// Copy arm
		db_query("insert into redcap_events_arms (project_id, arm_num, arm_name) values ($new_project_id, {$row['arm_num']}, '".db_escape($row['arm_name'])."')");
		$this_arm_id = db_insert_id();
		$q2 = db_query("select * from redcap_events_metadata where arm_id = {$row['arm_id']}");
		while ($row2 = db_fetch_assoc($q2))
		{
			// Copy event
			db_query("insert into redcap_events_metadata (arm_id, day_offset, offset_min, offset_max, descrip, custom_event_label) values
						 ($this_arm_id, {$row2['day_offset']}, {$row2['offset_min']}, {$row2['offset_max']}, 
						 '".db_escape($row2['descrip'])."', ".checkNull($row2['custom_event_label']).")");
			$this_event_id = db_insert_id();
			// Get old event_id of copied project and translate to new equivalent event_id for new project
			$eventid_translate[$row2['event_id']] = $this_event_id;
			// Copy events-forms matching
			db_query("insert into redcap_events_forms (event_id, form_name) select '$this_event_id', form_name from redcap_events_forms where event_id = {$row2['event_id']}");
		}
	}

	// Copy some defined project-level values from the project being copied
	$projectFieldsCopy = array( "repeatforms", "scheduling", "randomization", "surveys_enabled", "field_comment_edit_delete",
								"display_today_now_button", "auto_inc_set", "require_change_reason", "secondary_pk", "secondary_pk_display_value", "secondary_pk_display_label",
								"history_widget_enabled", "order_id_by", "custom_record_label", "enable_participant_identifiers",
								"survey_email_participant_field", "data_resolution_enabled", "project_language", "project_encoding",
								"display_project_logo_institution", "survey_auth_enabled", "survey_auth_field1", "survey_auth_event_id1",
								"survey_auth_field2", "survey_auth_event_id2", "survey_auth_field3", "survey_auth_event_id3",
								"survey_auth_min_fields", "survey_auth_apply_all_surveys", "survey_auth_custom_message",
								"survey_auth_fail_limit", "survey_auth_fail_window", "disable_autocalcs", "custom_index_page_note",
								"custom_data_entry_note", "realtime_webservice_type", "realtime_webservice_offset_days", "realtime_webservice_offset_plusminus",
								"missing_data_codes", "survey_queue_hide", "survey_queue_custom_text", "project_dashboard_min_data_points", "bypass_branching_erase_field_prompt",
								"allow_delete_record_from_log", "delete_file_repository_export_files", "two_factor_exempt_project", "two_factor_force_project",
								"twilio_modules_enabled", "twilio_voice_language", "twilio_option_voice_initiate", "twilio_option_sms_initiate", "twilio_option_sms_invite_make_call",
								"twilio_option_sms_invite_receive_call", "twilio_option_sms_invite_web", "twilio_default_delivery_preference", "twilio_append_response_instructions",
								"twilio_multiple_sms_behavior", "twilio_multiple_sms_behavior",
								"double_data_entry", "date_shift_max", "shared_library_enabled", "data_entry_trigger_url",
								"protected_email_mode", "protected_email_mode_custom_text", "protected_email_mode_trigger", "protected_email_mode_logo",
								"google_recaptcha_enabled", "allow_econsent_allow_edit", "store_in_vault_snapshots_containing_completed_econsent");
	// Also include the custom project-level settings
	if (!$isTemplate) $projectFieldsCopy = array_merge($projectFieldsCopy, Project::$overwritableGlobalVars);

    if (!$isTemplate) $projectFieldsCopy = array_merge($projectFieldsCopy, ['task_complete_status']);
	// Retrieve field values from project being copied and update newly created project
	$sql = "select " . implode(", ", $projectFieldsCopy) . " from redcap_projects where project_id = $copyof_project_id";
	$q = db_query($sql);
	$row = db_fetch_assoc($q);
	$updateVals = array();
	foreach ($projectFieldsCopy as $this_field)
	{
		// If users are not allowed to create surveys (global setting), then set surveys_enabled = 0
		if (!$enable_projecttype_singlesurveyforms && $this_field == "surveys_enabled") {
			$row[$this_field] = '0';
		}
		// Translate some event_ids (if applicable)
		if (substr($this_field, 0, -1) == "survey_auth_event_id" && is_numeric($row[$this_field])) {
			$row[$this_field] = $eventid_translate[$row[$this_field]];
		}
		// Copy the logo file and get new edoc_id
		if ($this_field == 'protected_email_mode_logo' && !empty($row['protected_email_mode_logo']))
		{
			$edoc_id = copyFile($row['protected_email_mode_logo'], $new_project_id);
			if (!empty($edoc_id)) {
				$row[$this_field] = $edoc_id;
			}
		}
        if ($this_field == 'mycap_enabled' && !empty($row['mycap_enabled']))
        {
            $mycap_enabled = $row['mycap_enabled'];
        }
		// ADD VALUE: Use checkNull if column's default value is NULL
		if (array_key_exists($this_field, $redcap_projects_defaults) && $redcap_projects_defaults[$this_field] === null) {
			$updateVals[] = $this_field . " = " . checkNull(label_decode($row[$this_field]));
		} else {
			$updateVals[] = $this_field . " = '" . db_escape(label_decode($row[$this_field])) . "'";
		}
	}
	$sql = "update redcap_projects set " . implode(", ", $updateVals) . " where project_id = $new_project_id";
	db_query($sql);

	// Copy any Shared Library instrument mappings
	$sql = "insert into redcap_library_map (project_id, form_name, `type`, library_id, upload_timestamp, acknowledgement, acknowledgement_cache, promis_key)
			select '$new_project_id', form_name, `type`, library_id, upload_timestamp, acknowledgement, acknowledgement_cache, promis_key
			from redcap_library_map where project_id = $copyof_project_id";
	$q = db_query($sql);

	// Copy any surveys
	$surveyid_translate = array(); // Store old survey_id as key and new survey_id as value
	$sql = "select * from redcap_surveys where project_id = $copyof_project_id";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		$sql = "insert into redcap_surveys (project_id, form_name, title, instructions, acknowledgement, question_by_section,
				question_auto_numbering, survey_enabled, save_and_return, hide_title, view_results, min_responses_view_results, check_diversity_view_results,
				end_survey_redirect_url, end_survey_redirect_next_survey_logic, promis_skip_question,
				survey_auth_enabled_single, edit_completed_response, hide_back_button, show_required_field_text,
				confirmation_email_subject, confirmation_email_content, confirmation_email_from, confirmation_email_attach_pdf, text_to_speech,
				text_to_speech_language, end_survey_redirect_next_survey, theme, text_size, font_family,
				theme_bg_page, theme_text_buttons, theme_text_title, theme_bg_title,
				theme_text_question, theme_bg_question, theme_text_sectionheader, theme_bg_sectionheader,
				enhanced_choices, repeat_survey_enabled, repeat_survey_btn_text, repeat_survey_btn_location, 
				response_limit, response_limit_include_partials, response_limit_custom_text,
				survey_time_limit_days, survey_time_limit_hours, survey_time_limit_minutes, end_of_survey_pdf_download,
				save_and_return_code_bypass, email_participant_field, 
				offline_instructions, stop_action_acknowledgement, stop_action_delete_response,
                survey_width_percent, survey_show_font_resize, survey_btn_text_prev_page, survey_btn_text_next_page,
                survey_btn_text_submit, survey_btn_hide_submit, survey_btn_hide_submit_logic, display_page_number
                ) values ($new_project_id, ".checkNull($row['form_name']).", ".checkNull($row['title']).", ".checkNull($row['instructions']).",
				".checkNull($row['acknowledgement']).", {$row['question_by_section']},
				{$row['question_auto_numbering']}, 1, {$row['save_and_return']}, {$row['hide_title']}, {$row['view_results']},
				{$row['min_responses_view_results']}, {$row['check_diversity_view_results']},
				".checkNull(label_decode($row['end_survey_redirect_url'])).",
				".checkNull(label_decode($row['end_survey_redirect_next_survey_logic'])).",
				{$row['promis_skip_question']},
				{$row['survey_auth_enabled_single']}, {$row['edit_completed_response']}, {$row['hide_back_button']}, {$row['show_required_field_text']},
				".checkNull($row['confirmation_email_subject']).", ".checkNull($row['confirmation_email_content']).", ".checkNull($row['confirmation_email_from']).", ".checkNull($row['confirmation_email_attach_pdf']).",
				{$row['text_to_speech']}, '{$row['text_to_speech_language']}', '{$row['end_survey_redirect_next_survey']}',
				".checkNull($row['theme']).", ".checkNull($row['text_size']).", ".checkNull($row['font_family']).",
				".checkNull($row['theme_bg_page']).", ".checkNull($row['theme_text_buttons']).", ".checkNull($row['theme_text_title']).", ".checkNull($row['theme_bg_title']).",
				".checkNull($row['theme_text_question']).", ".checkNull($row['theme_bg_question']).", ".checkNull($row['theme_text_sectionheader']).", ".checkNull($row['theme_bg_sectionheader']).",
				".checkNull($row['enhanced_choices']).", ".checkNull($row['repeat_survey_enabled']).", ".checkNull($row['repeat_survey_btn_text']).", ".checkNull($row['repeat_survey_btn_location']).",
				".checkNull($row['response_limit']).", ".checkNull($row['response_limit_include_partials']).", ".checkNull($row['response_limit_custom_text']).",
				".checkNull($row['survey_time_limit_days']).", ".checkNull($row['survey_time_limit_hours']).", ".checkNull($row['survey_time_limit_minutes']).", 
				".checkNull($row['end_of_survey_pdf_download']).",".checkNull($row['save_and_return_code_bypass']).",
				".checkNull($row['email_participant_field']).",
				".checkNull($row['offline_instructions']).", 
				".checkNull($row['stop_action_acknowledgement'])."
				,".checkNull($row['stop_action_delete_response'])."
				,".checkNull($row['survey_width_percent'])."
				,".checkNull($row['survey_show_font_resize'])."
				,".checkNull($row['survey_btn_text_prev_page'])."
				,".checkNull($row['survey_btn_text_next_page'])."
				,".checkNull($row['survey_btn_text_submit'])."
				,".checkNull($row['survey_btn_hide_submit'])."
				,".checkNull($row['survey_btn_hide_submit_logic'])."
				,".checkNull($row['display_page_number'])."
				)";
		db_query($sql);
		$this_survey_id = db_insert_id();
		// Get old event_id of copied project and translate to new equivalent event_id for new project
		$surveyid_translate[$row['survey_id']] = $this_survey_id;
		// Copy the logo file and get new edoc_id
		if (!empty($row['logo']))
		{
			$edoc_id = copyFile($row['logo'], $new_project_id);
			// Add new edoc_id to surveys table for this survey
			if (!empty($edoc_id)) {
				$sql = "update redcap_surveys set logo = $edoc_id where survey_id = $this_survey_id";
				db_query($sql);
			}
		}
		// Copy the email confirmation attachment and get new edoc_id
		if (!empty($row['confirmation_email_attachment']))
		{
			$edoc_id = copyFile($row['confirmation_email_attachment'], $new_project_id);
			// Add new edoc_id to surveys table for this survey
			if (!empty($edoc_id)) {
				$sql = "update redcap_surveys set confirmation_email_attachment = $edoc_id where survey_id = $this_survey_id";
				db_query($sql);
			}
		}
	}

    if ($templateMyCapEnabled || (isset($_POST['copy_mycap_mobile_app_content']) && $_POST['copy_mycap_mobile_app_content'] == "on")) {
        // COPY ANY MYCAP TASKS
        $sql = "SELECT * FROM redcap_mycap_tasks WHERE project_id = $copyof_project_id";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $sql = "INSERT INTO redcap_mycap_tasks (project_id, form_name, enabled_for_mycap, task_title, question_format,
                            card_display, x_date_field, x_time_field, y_numeric_field, extended_config_json) 
                    VALUES
                        ($new_project_id, " . checkNull($row['form_name']) . ", {$row['enabled_for_mycap']}, " . checkNull($row['task_title']) . ", " . checkNull($row['question_format']) . ",
                        " . checkNull($row['card_display']) . ", " . checkNull($row['x_date_field']) . "," . checkNull($row['x_time_field']) . "," . checkNull($row['y_numeric_field']) . ", " . checkNull($row['extended_config_json']) . ")";
            db_query($sql);
            $this_task_id = db_insert_id();

            // COPY ANY MYCAP TASKS SCHEDULES
            $sql2 = "SELECT * FROM redcap_mycap_tasks_schedules WHERE task_id = '".$row['task_id']."' AND active = '1'";
            $q2 = db_query($sql2);
            while ($row2 = db_fetch_assoc($q2)) {
                $sql_schedule = "INSERT INTO redcap_mycap_tasks_schedules (task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title,
                            instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency,
                            schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset,
                            schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date) 
                                VALUES ($this_task_id, ".$eventid_translate[$row2['event_id']].", {$row2['allow_retro_completion']}, {$row2['allow_save_complete_later']}, {$row2['include_instruction_step']}, {$row2['include_completion_step']},
                        " . checkNull($row2['instruction_step_title']) . ", " . checkNull($row2['instruction_step_content']) . ", " . checkNull($row2['completion_step_title']) . ", 
                        " . checkNull($row2['completion_step_content']) . ", " . checkNull($row2['schedule_relative_to']) . ", " . checkNull($row2['schedule_type']) . ", 
                        " . checkNull($row2['schedule_frequency']) . ", " . checkNull($row2['schedule_interval_week']) . ", " . checkNull($row2['schedule_days_of_the_week']) . ", 
                        " . checkNull($row2['schedule_interval_month']) . ", " . checkNull($row2['schedule_days_of_the_month']) . ", " . checkNull($row2['schedule_days_fixed']) . ", 
                        " . checkNull($row2['schedule_relative_offset']) . ", " . checkNull($row2['schedule_ends']) . ", " . checkNull($row2['schedule_end_count']) . ", 
                        " . checkNull($row2['schedule_end_after_days']) . ", " . checkNull($row2['schedule_end_date']) . ")";
                db_query($sql_schedule);
            }
        }
    }

	// Copy redcap_events_repeat for repeating forms/events
	$q = db_query("select * from redcap_events_repeat where event_id in (".prep_implode(array_keys($eventid_translate)).")");
	while ($row = db_fetch_assoc($q)) {
		db_query("insert into redcap_events_repeat (event_id, form_name, custom_repeat_form_label) 
				  values (".$eventid_translate[$row['event_id']].", '".db_escape($row['form_name'])."', ".checkNull($row['custom_repeat_form_label']).")");
	}

	// Copy data access groups (do one at a time to grab old/new values for matching later)
	$groupid_array = array();
	$q = db_query("select * from redcap_data_access_groups where project_id = $copyof_project_id");
	while ($row = db_fetch_assoc($q)) {
		db_query("insert into redcap_data_access_groups (project_id, group_name) values ($new_project_id, '".db_escape($row['group_name'])."')");
		$groupid_array[$row['group_id']] = db_insert_id();

	}
	// Copy DAG Switcher assignments
	if (!empty($groupid_array)) {
		// db_query("insert into redcap_data_access_groups_users (project_id, group_id, username) select '$new_project_id', from  where project_id = $copyof_project_id");
		$q = db_query("select group_id, username from redcap_data_access_groups_users where project_id = $copyof_project_id");
		while ($row = db_fetch_assoc($q)) {
			if ($row['group_id'] != null && !isset($groupid_array[$row['group_id']])) continue;
			db_query("insert into redcap_data_access_groups_users (project_id, group_id, username) values ($new_project_id, ".checkNull($groupid_array[$row['group_id']]).", '".db_escape($row['username'])."')");
		}
	}

	## COPY LOCKING CUSTOMIZATIONS
	$sql = "insert into redcap_locking_labels (project_id, form_name, label, display, display_esignature)
			select '$new_project_id', form_name, label, display, display_esignature	from redcap_locking_labels
			where project_id = $copyof_project_id";
	db_query($sql);

	## COPY RECORD STATUS DASHBOARDS
	if ($isTemplate || (isset($_POST['copy_record_dash']) && $_POST['copy_record_dash'] == "on"))
	{		
		$q = db_query("select * from redcap_record_dashboards where project_id = $copyof_project_id");
		while ($row = db_fetch_assoc($q)) {
			if ($row['selected_forms_events'] != '') {
				$selected_forms_events = array();
				foreach (explode(",", $row['selected_forms_events']) as $attr) {
					list ($this_event_id, $this_form) = explode(":", $attr, 2);
					$this_event_id = $eventid_translate[$this_event_id];
					$selected_forms_events[] = $this_event_id.":".$this_form;
				}
				$row['selected_forms_events'] = implode(",", $selected_forms_events);
			}
			db_query("insert into redcap_record_dashboards (project_id, title, description, filter_logic, orientation, group_by, selected_forms_events, arm, sort_event_id, sort_field_name, sort_order) 
					  values ('$new_project_id', ".checkNull($row['title']).", ".checkNull($row['description']).", ".checkNull($row['filter_logic']).", ".checkNull($row['orientation']).", ".checkNull($row['group_by']).", ".checkNull($row['selected_forms_events']).", ".checkNull($row['arm']).", ".checkNull($eventid_translate[$row['sort_event_id']]).", ".checkNull($row['sort_field_name']).", ".checkNull($row['sort_order']).")");
		}
	}

	## COPY USER ROLES (do one at a time to grab old/new values for matching later)
	$userRoleId_array = array();
	if ($isTemplate || (isset($_POST['copy_roles']) && $_POST['copy_roles'] == "on"))
	{
		$q = db_query("select * from redcap_user_roles where project_id = $copyof_project_id");
		while ($row = db_fetch_assoc($q)) {
			// Set role_id before we remove it
			$this_role_id = $row['role_id'];
			// Remove project_id and role_id from $row since we don't need them
			unset($row['project_id'], $row['role_id']);
			// Loop through $row values and escape them for query
			foreach ($row as &$val) $val = checkNull($val);
			// Set the field names and corresponding values for query
			$role_fields = implode(", ", array_keys($row));
			$role_values = implode(", ", $row);
			db_query("insert into redcap_user_roles (project_id, $role_fields) values ($new_project_id, $role_values)");
			// Add role_id to array for later
			$userRoleId_array[$this_role_id] = db_insert_id();
		}
	}

	## COPY REPORTS (if a template OR if desired for copy)
	if ($isTemplate || (isset($_POST['copy_reports']) && $_POST['copy_reports'] == "on"))
	{
		// List of all db tables relating to reports, excluding redcap_reports
		$tables = array('redcap_reports_fields', 'redcap_reports_filter_events', 'redcap_reports_access_dags', 'redcap_reports_filter_dags');
		// If copying users/roles, then include the user/role report tables
		if (isset($_POST['copy_users']) && $_POST['copy_users'] == "on") {
			$tables[] = 'redcap_reports_access_users';
		}
        if (isset($_POST['copy_roles']) && $_POST['copy_roles'] == "on") {
            $tables[] = 'redcap_reports_access_roles';
        }
		// Loop through ALL reports one by one
		$reportid_translate = array();
		$sql = "select * from redcap_reports where project_id = $copyof_project_id order by report_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$report_id = $row['report_id'];
			// Set project_id of new project
			$row['project_id'] = $new_project_id;
			unset($row['report_id'], $row['short_url'], $row['hash'], $row['is_public']);
			// Unset optional pk_id column
			unset($row['pk_id']);
			// If we're not copying users, then set report access to ALL
			if (!isset($_POST['copy_users']) && !isset($_POST['copy_roles'])) {
				$row['user_access'] = 'ALL';
			}
			// Insert into reports table
			$sqlr = "insert into redcap_reports (".implode(', ', array_keys($row)).") values (".prep_implode($row, true, true).")";
			$qr = db_query($sqlr);
			$new_report_id = db_insert_id();
			$reportid_translate[$report_id] = $new_report_id;
			// Now loop through all other report tables and add
			foreach ($tables as $table_name) {
				// Loop through all rows in this table
				$sqlr2 = "select * from $table_name where report_id = $report_id";
				$q2 = db_query($sqlr2);
				while ($row2 = db_fetch_assoc($q2)) {
					// Unset optional pk_id column
					unset($row2['pk_id']);
					// Set new report_id
					$row2['report_id'] = $new_report_id;
					// If has event_id, role_id, or group_id, then replace with new project's values
					if (isset($row2['event_id'])) {
						$row2['event_id'] = $eventid_translate[$row2['event_id']];
					}
					if (isset($row2['limiter_event_id'])) {
						$row2['limiter_event_id'] = $eventid_translate[$row2['limiter_event_id']];
					}
					if (isset($row2['group_id'])) {
						$row2['group_id'] = $groupid_array[$row2['group_id']];
					}
					if (isset($row2['role_id'])) {
						$row2['role_id'] = $userRoleId_array[$row2['role_id']];
					}
					// Insert
					$sqlr3 = "insert into $table_name (".implode(', ', array_keys($row2)).") values (".prep_implode($row2, true, true).")";
					$q3 = db_query($sqlr3);
				}
			}
		}
		// COPY REPORT FOLDERS: Loop through ALL report folders one by one
		if ($isTemplate || (isset($_POST['copy_report_folders']) && $_POST['copy_report_folders'] == "on"))
		{
			$sql = "select * from redcap_reports_folders where project_id = $copyof_project_id order by position";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// Unset optional pk_id column
				unset($row['pk_id']);
				$folder_id = $row['folder_id'];
				unset($row['folder_id']);
				$row['project_id'] = $new_project_id;
				// Insert into redcap_reports_folders table
				$sqlr = "insert into redcap_reports_folders (".implode(', ', array_keys($row)).") values (".prep_implode($row, true, true).")";
				$qr = db_query($sqlr);
				$new_folder_id = db_insert_id();
				// Now add this report folders' reports
				$sqlr2 = "select report_id from redcap_reports_folders_items where folder_id = $folder_id";
				$q2 = db_query($sqlr2);
				while ($row2 = db_fetch_assoc($q2)) {
					$sqlr3 = "insert into redcap_reports_folders_items (folder_id, report_id) 
							  values ($new_folder_id, '".db_escape($reportid_translate[$row2['report_id']])."')";
					db_query($sqlr3);
				}
			}
		}
	}

	## COPY THE PROJECT BOOKMARKS
	if ($isTemplate || (isset($_POST['copy_external_links']) && $_POST['copy_external_links'] == "on"))
	{
		$sql = "insert into redcap_external_links (project_id, link_order, link_url, link_label, open_new_window, link_type,
				user_access, append_record_info, append_pid, link_to_project_id)
				select '$new_project_id', link_order, link_url, link_label, open_new_window, link_type,
				user_access, append_record_info, append_pid, link_to_project_id
				from redcap_external_links
				where project_id = $copyof_project_id";
		db_query($sql);
	}


	## COPY PROJECT DASHBOARDS (if a template OR if desired for copy)
	if ($isTemplate || (isset($_POST['copy_project_dashboards']) && $_POST['copy_project_dashboards'] == "on"))
	{
		// List of all db tables relating to dashboards, excluding dashboards
		$tables = array('redcap_project_dashboards_access_dags');
		// If copying users/roles, then include the user/role dashboard tables
		if (isset($_POST['copy_users']) && $_POST['copy_users'] == "on") {
			$tables[] = 'redcap_project_dashboards_access_users';
		}
		if (isset($_POST['copy_roles']) && $_POST['copy_roles'] == "on") {
			$tables[] = 'redcap_project_dashboards_access_roles';
		}
		// Loop through ALL dashboards one by one
		$dashid_translate = array();
		$sql = "select * from redcap_project_dashboards where project_id = $copyof_project_id order by dash_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$dash_id = $row['dash_id'];
			// Set project_id of new project
			$row['project_id'] = $new_project_id;
			unset($row['dash_id'], $row['hash'], $row['short_url'], $row['cache_time'], $row['cache_content']);
			// If we're not copying users, then set dashboard access to ALL
			if (!isset($_POST['copy_users']) && !isset($_POST['copy_roles'])) {
				$row['user_access'] = 'ALL';
			}
			// Insert into dashboards table
			$sqlr = "insert into redcap_project_dashboards (".implode(', ', array_keys($row)).") values (".prep_implode($row, true, true).")";
			$qr = db_query($sqlr);
			$new_dash_id = db_insert_id();
			$dashid_translate[$dash_id] = $new_dash_id;
			// Now loop through all other dashboard tables and add
			foreach ($tables as $table_name) {
				// Loop through all rows in this table
				$sqlr2 = "select * from $table_name where dash_id = $dash_id";
				$q2 = db_query($sqlr2);
				while ($row2 = db_fetch_assoc($q2)) {
					// Set new dash_id
					$row2['dash_id'] = $new_dash_id;
					// If has event_id, role_id, or group_id, then replace with new project's values
					if (isset($row2['group_id'])) {
						$row2['group_id'] = $groupid_array[$row2['group_id']];
					}
					if (isset($row2['role_id'])) {
						$row2['role_id'] = $userRoleId_array[$row2['role_id']];
					}
					// Insert
					$sqlr3 = "insert into $table_name (".implode(', ', array_keys($row2)).") values (".prep_implode($row2, true, true).")";
					$q3 = db_query($sqlr3);
				}
			}
		}

        // COPY DASHBOARD FOLDERS: Loop through ALL dashboard folders one by one
        if ($isTemplate || (isset($_POST['copy_dashboard_folders']) && $_POST['copy_dashboard_folders'] == "on"))
        {
            $sql = "select * from redcap_project_dashboards_folders where project_id = $copyof_project_id order by position";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q))
            {
                $folder_id = $row['folder_id'];
                unset($row['folder_id']);
                $row['project_id'] = $new_project_id;
                // Insert into redcap_project_dashboards_folders table
                $sqlr = "insert into redcap_project_dashboards_folders (".implode(', ', array_keys($row)).") values (".prep_implode($row, true, true).")";
                $qr = db_query($sqlr);
                $new_folder_id = db_insert_id();
                $sqlr2 = "select dash_id from redcap_project_dashboards_folders_items where folder_id = $folder_id";
                $q2 = db_query($sqlr2);
                while ($row2 = db_fetch_assoc($q2)) {
                    $sqlr3 = "insert into redcap_project_dashboards_folders_items (folder_id, dash_id) 
							  values ($new_folder_id, '".db_escape($dashid_translate[$row2['dash_id']])."')";
                    db_query($sqlr3);
                }
            }
        }
	}


	## COPY DATA QUALITY RULES (if a template OR if desired for copy)
	if ($isTemplate || (isset($_POST['copy_dq_rules']) && $_POST['copy_dq_rules'] == "on"))
	{
		$sql = "insert into redcap_data_quality_rules (project_id, rule_order, rule_name, rule_logic, real_time_execute)
				select '$new_project_id', rule_order, rule_name, rule_logic, real_time_execute from redcap_data_quality_rules
				where project_id = $copyof_project_id";
		db_query($sql);
	}

    ## COPY SETTINGS FOR ECONSENT AND PDF SNAPSHOTS (if a template OR if desired for copy)
    if ($isTemplate || (isset($_POST['copy_econsent_pdf_snapshots']) && $_POST['copy_econsent_pdf_snapshots'] == "on")) {
        // COPY ECONSENT SETTINGS
        $sql = "select * from redcap_econsent where project_id = $copyof_project_id order by consent_id";
        $q = db_query($sql);
        $econsentid_translate = [];
        while ($row = db_fetch_assoc($q)) {
            $this_consent_id = $row['consent_id'];
            unset($row['consent_id']);
            $row['project_id'] = $new_project_id;
            $row['survey_id'] = $surveyid_translate[$row['survey_id']];
            $row['firstname_event_id'] = $eventid_translate[$row['firstname_event_id']] ?? null;
            $row['lastname_event_id'] = $eventid_translate[$row['lastname_event_id']] ?? null;
            $row['dob_event_id'] = $eventid_translate[$row['dob_event_id']] ?? null;
            $sql = "insert into redcap_econsent (".implode(", ", array_keys($row)).") values (".prep_implode($row, true, true).")";
            db_query($sql);
            $econsentid_translate[$this_consent_id] = db_insert_id();
        }
        // COPY ECONSENT FORMS (only the current active one though)
        if (!empty($econsentid_translate)) {
            $sql = "select * from redcap_econsent_forms where consent_id in (" . prep_implode(array_keys($econsentid_translate)) . ") 
                    and consent_form_active = 1 order by consent_form_id";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                unset($row['consent_form_id']);
                // Skip MLM-filtered consent form if MLM settings are not being copied
                if ($row['consent_form_filter_lang_id'] != null && !(isset($_POST['copy_languages']) && $_POST['copy_languages'] == "on")) {
                    continue;
                }
                // Copy PDF?
                if ($row['consent_form_pdf_doc_id'] != null) {
                    $row['consent_form_pdf_doc_id'] = copyFile($row['consent_form_pdf_doc_id'], $new_project_id);
                }
                $row['consent_form_filter_dag_id'] = $groupid_array[$row['consent_form_filter_dag_id']] ?? null;
                $row['consent_id'] = $econsentid_translate[$row['consent_id']] ?? null;
                $row['creation_time'] = null;
                $row['uploader'] = null;
                $sql = "insert into redcap_econsent_forms (".implode(", ", array_keys($row)).") values (".prep_implode($row, true, true).")";
                db_query($sql);
            }
        }
        // COPY PDF SNAPSHOT SETTINGS
        $sql = "select * from redcap_pdf_snapshots where project_id = $copyof_project_id order by snapshot_id";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            unset($row['snapshot_id']);
            if ($row['selected_forms_events'] != null) {
                foreach ($eventid_translate as $oldEventId=>$newEventId) {
                    // If contains event_id in the middle
                    $row['selected_forms_events'] = str_replace(",".$oldEventId.":", ",".$newEventId.":", $row['selected_forms_events']);
                    // If contains event_id at the beginning
                    if (strpos($row['selected_forms_events'], $oldEventId.":") === 0) {
                        list ($nothing, $ending) = explode($oldEventId.":", $row['selected_forms_events'], 2);
                        $row['selected_forms_events'] = $newEventId.":".$ending;
                    }
                }
            }
            $row['project_id'] = $new_project_id;
            $row['trigger_surveycomplete_survey_id'] = $surveyid_translate[$row['trigger_surveycomplete_survey_id']];
            $row['consent_id'] = $econsentid_translate[$row['consent_id']] ?? null;
            $row['trigger_surveycomplete_event_id'] = $eventid_translate[$row['trigger_surveycomplete_event_id']] ?? null;
            $row['pdf_save_to_event_id'] = $eventid_translate[$row['pdf_save_to_event_id']] ?? null;
            $sql = "insert into redcap_pdf_snapshots (".implode(", ", array_keys($row)).") values (".prep_implode($row, true, true).")";
            db_query($sql);
        }
    }

	## COPY SETTINGS FOR SURVEY QUEUE AND AUTOMATED SURVEY INVITATIONS (if a template OR if desired for copy)
	if ($isTemplate || (isset($_POST['copy_survey_queue_auto_invites']) && $_POST['copy_survey_queue_auto_invites'] == "on"))
	{
		// COPY SURVEY QUEUE
		$sql = "select distinct q.* from redcap_surveys_queue q, redcap_surveys s, redcap_metadata m, redcap_events_metadata e,
				redcap_events_arms a where s.survey_id = q.survey_id and s.project_id = $copyof_project_id and m.project_id = s.project_id
				and s.form_name = m.form_name and q.event_id = e.event_id and e.arm_id = a.arm_id order by q.sq_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$sql = "insert into redcap_surveys_queue (survey_id, event_id, active, auto_start, condition_surveycomplete_survey_id,
					condition_surveycomplete_event_id, condition_andor, condition_logic) values
					('".db_escape($surveyid_translate[$row['survey_id']])."', '".db_escape($eventid_translate[$row['event_id']])."',
					'".db_escape($row['active'])."', '".db_escape($row['auto_start'])."',
					".checkNull($surveyid_translate[$row['condition_surveycomplete_survey_id']]).",
					".checkNull($eventid_translate[$row['condition_surveycomplete_event_id']]).",
					'".db_escape($row['condition_andor'])."', ".checkNull($row['condition_logic']).")";
			db_query($sql);
		}
		// COPY AUTOMATED SURVEY INVITATIONS
		$sql = "select distinct q.* from redcap_surveys_scheduler q, redcap_surveys s, redcap_metadata m, redcap_events_metadata e,
				redcap_events_arms a where s.survey_id = q.survey_id and s.project_id = $copyof_project_id and m.project_id = s.project_id
				and s.form_name = m.form_name and q.event_id = e.event_id and e.arm_id = a.arm_id order by q.ss_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$sql = "insert into redcap_surveys_scheduler (survey_id, event_id, instance, active, email_subject, email_content, email_sender, email_sender_display,
					condition_surveycomplete_survey_id, condition_surveycomplete_event_id, condition_surveycomplete_instance, condition_andor, condition_logic,
					condition_send_time_option, condition_send_time_lag_days, condition_send_time_lag_hours,
					condition_send_time_lag_minutes, condition_send_next_day_type, condition_send_next_time, condition_send_time_exact,
					delivery_type, reminder_type, reminder_timelag_days, reminder_timelag_hours, reminder_timelag_minutes, 
					reminder_nextday_type, reminder_nexttime, reminder_exact_time, reminder_num, reeval_before_send, condition_send_time_lag_field, condition_send_time_lag_field_after,
                    num_recurrence, units_recurrence, max_recurrence) values
					('".db_escape($surveyid_translate[$row['survey_id']])."', '".db_escape($eventid_translate[$row['event_id']])."', '".db_escape($row['instance'])."',
					'0', '".db_escape($row['email_subject'])."', '".db_escape($row['email_content'])."', '".db_escape($row['email_sender'])."', ".checkNull($row['email_sender_display']).",
					".checkNull($surveyid_translate[$row['condition_surveycomplete_survey_id']]).",
					".checkNull($eventid_translate[$row['condition_surveycomplete_event_id']]).",
					".checkNull($row['condition_surveycomplete_instance']).",
					'".db_escape($row['condition_andor'])."', ".checkNull($row['condition_logic']).", '".db_escape($row['condition_send_time_option'])."',
					".checkNull($row['condition_send_time_lag_days']).", ".checkNull($row['condition_send_time_lag_hours']).",
					".checkNull($row['condition_send_time_lag_minutes']).", ".checkNull($row['condition_send_next_day_type']).",
					".checkNull($row['condition_send_next_time']).", ".checkNull($row['condition_send_time_exact']).",
					".checkNull($row['delivery_type']).", ".checkNull($row['reminder_type']).", ".checkNull($row['reminder_timelag_days']).",
					".checkNull($row['reminder_timelag_hours']).", ".checkNull($row['reminder_timelag_minutes']).", ".checkNull($row['reminder_nextday_type']).",
					".checkNull($row['reminder_nexttime']).", ".checkNull($row['reminder_exact_time']).", ".checkNull($row['reminder_num']).", 
					".checkNull($row['reeval_before_send']).", ".checkNull($row['condition_send_time_lag_field']).", ".checkNull($row['condition_send_time_lag_field_after']).",
					".checkNull($row['num_recurrence']).", ".checkNull($row['num_recurrence']).", ".checkNull($row['max_recurrence'])."
					)";
			db_query($sql);
		}
	}

    ## COPY ALL ACTIVE ALERTS (if a template OR if desired for copy)
	$alertid_translate = [];
    if ($isTemplate || (isset($_POST['copy_alerts']) && $_POST['copy_alerts'] == "on"))
    {
        $sql = "select * from redcap_alerts where project_id = $copyof_project_id and email_deleted = 0 order by alert_id";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Copy any file attachments and get new edoc_id
            $alertAttachFields = array('email_attachment1', 'email_attachment2', 'email_attachment3', 'email_attachment4', 'email_attachment5');
            foreach ($alertAttachFields as $thisAttachField) {
                if (!empty($row[$thisAttachField])) {
                    $row[$thisAttachField] = copyFile($row[$thisAttachField], $new_project_id);
                }
            }
			$thisAlertId = $row['alert_id'];
            unset($row['alert_id'], $row['email_timestamp_sent'], $row['email_sent']);
            $row['project_id'] = $new_project_id;
            $row['email_deleted'] = 1; // Set all alerts to be deactivated, just in case
            $row['form_name_event'] = $eventid_translate[$row['form_name_event']];
            $sql = "insert into redcap_alerts (".implode(', ', array_keys($row)).") 
                    values (".prep_implode($row, true, true).")";
            db_query($sql);
			$alertid_translate[$thisAlertId] = db_insert_id();
        }
    }

    ## COPY ALL RANDOMIZATION SETTINGS (if a template OR if desired for copy)
    if ($isTemplate || (isset($_POST['copy_randomization']) && $_POST['copy_randomization'] == "on"))
    {
        $sql = "select * from redcap_randomization where project_id = $copyof_project_id order by rid";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            unset($row['rid']);
            $row['project_id'] = $new_project_id;
            foreach ($row as $key=>$val) {
                if (strpos($key, "event") !== false && isinteger($val) && isset($eventid_translate[$val])) {
                    $row[$key] = $eventid_translate[$val];
                }
            }
            $sql = "insert into redcap_randomization (".implode(', ', array_keys($row)).") 
                    values (".prep_implode($row, true, true).")";
            db_query($sql);
        }
    }

    ## COPY ALL DESCRIPTIVE POPUP SETTINGS (if a template OR if desired for copy)
    if ($isTemplate || (isset($_POST['copy_descriptive_popups']) && $_POST['copy_descriptive_popups'] == "on"))
    {
        $sql = "select * from redcap_descriptive_popups where project_id = $copyof_project_id order by popup_id";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            unset($row['popup_id']);
            $row['project_id'] = $new_project_id;
            $sql = "insert into redcap_descriptive_popups (".implode(', ', array_keys($row)).") 
                    values (".prep_implode($row, true, true).")";
            db_query($sql);
        }
    }

	## COPY RECORDS (if applicable)
	if ((!$isTemplate && isset($_POST['copy_records']) && $_POST['copy_records'] == "on") || ($isTemplate && $templateCopyRecords))
	{
		// COPY BIOONTOLOGY WEB SERVICE'S CACHED DATA
		$sql = "insert into redcap_web_service_cache (project_id, service, category, value, label)
				select '$new_project_id', service, category, value, label from redcap_web_service_cache 
				where project_id = $copyof_project_id";
		db_query($sql);
		
		## COPY DATA: Transfer data one event at a time (do not copy records if project already has more than the max limit of records while in development) - limit is not imposed on admins though
		$max_records_development_global = (!SUPER_USER && isinteger($GLOBALS['max_records_development_global']) && $GLOBALS['max_records_development_global'] > 0) ? $GLOBALS['max_records_development_global'] : 0;
		$willExceedDevRecordLimit = ($max_records_development_global > 0 && Records::getRecordCount($copyof_project_id) > $max_records_development_global);
		if (!$willExceedDevRecordLimit) {
			// The project object needs to be reset for the new project due to updates above in metadata
			$Proj = new Project($new_project_id, true);
			// Loop through each event
			foreach ($eventid_translate as $old_event_id => $new_event_id) {
				$params = ['project_id' => $copyof_project_id, 'return_format' => 'csv', 'events' => $old_event_id, 'exportDataAccessGroups' => !empty($groupid_array), 'returnBlankForGrayFormStatus' => true];
				$dataCsv = Records::getData($params);
				$params = ['project_id' => $new_project_id, 'dataFormat' => 'csv', 'data' => $dataCsv, 'skipFileUploadFields' => false, 'bypassValidationCheck' => true];
				$saveDataResponse = Records::saveData($params);
				unset($dataCsv, $params);
			}
		}

		## COPY EDOCS: Move the "file" field type values separately (because the docs will have to be copied in the file system)
		$sql = "select distinct d.* from redcap_metadata m, ".\Records::getDataTable($new_project_id)." d where m.project_id = $new_project_id
				and m.project_id = d.project_id and m.field_name = d.field_name and m.element_type = 'file'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Make sure edoc_id is numerical. If so, copy file. If not, fix this corrupt data and don't copy file.
			$edoc_id = $row['value'];
			// Get edoc_id of new file copy
			$new_edoc_id = (is_numeric($edoc_id)) ? copyFile($edoc_id, $new_project_id) : '';
			// Set the new edoc_id value in the redcap_data table
			$sql = "update ".\Records::getDataTable($row['project_id'])." set value = '$new_edoc_id' where project_id = {$row['project_id']} and event_id = {$row['event_id']}
					and record = '" . db_escape($row['record']) . "' and field_name = '{$row['field_name']}'";
			$sql .= " and instance ".($row['instance'] == '' ? "is NULL" : "= '".db_escape($row['instance'])."'");
			db_query($sql);
		}

        $foundAnnotation = false;
        $dd_array = MetaData::getDataDictionary('array', false);
        foreach ($dd_array as $fieldName => $props) {
            if (strpos($props['field_annotation'], Annotation::PARTICIPANT_CODE) !== false) {
                $foundAnnotation = true;
                break;
            }
        }

        // Get all fields (with @MC-PARTICIPANT-CODE annotations) values
        if ($foundAnnotation)
        {
            $sql = "select distinct d.* from redcap_metadata m, ".\Records::getDataTable($new_project_id)." d where m.project_id = $new_project_id
                    and m.project_id = d.project_id and m.field_name = d.field_name and m.misc LIKE '%" . Annotation::PARTICIPANT_CODE . "%'";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                // Generate participant code for new project
                $new_participant_code = MyCap\Participant::generateUniqueCode($new_project_id);
                // Set the new participant code value in the redcap_data table
                $sql = "UPDATE ".\Records::getDataTable($row['project_id'])." SET value = '$new_participant_code' where project_id = {$row['project_id']} and event_id = {$row['event_id']}
                        and record = '" . db_escape($row['record']) . "' and field_name = '{$row['field_name']}'";
                $sql .= " and instance " . ($row['instance'] == '' ? "is NULL" : "= '" . db_escape($row['instance']) . "'");
                db_query($sql);
            }
        }
	}

    ## COPY ALL FORM ACTIVATION CONDITIONS AND RESPECTIVE TARGETS
    if ($isTemplate || (isset($_POST['copy_formdisplaylogic']) && $_POST['copy_formdisplaylogic'] == "on"))
    {
        // Get project's hide_filled_forms value
        $sql = "SELECT hide_filled_forms, hide_disabled_forms, form_activation_survey_autocontinue, form_activation_mycap_support FROM redcap_projects WHERE project_id = " . $copyof_project_id;
        $q = db_query($sql);
        $hide_filled_forms = db_result($q, 0, "hide_filled_forms");
        $hide_disabled_forms = db_result($q, 0, "hide_disabled_forms");
        $form_activation_survey_autocontinue = db_result($q, 0, "form_activation_survey_autocontinue");
        $form_activation_mycap_support = db_result($q, 0, "form_activation_mycap_support");

        // Update hide_filled_forms value of new project
        $sql = "UPDATE redcap_projects SET hide_filled_forms = '$hide_filled_forms', hide_disabled_forms = '$hide_disabled_forms', form_activation_survey_autocontinue = '$form_activation_survey_autocontinue', form_activation_mycap_support = '$form_activation_mycap_support' where project_id = $new_project_id";
        db_query($sql);

        $sql = "SELECT * FROM redcap_form_display_logic_conditions where project_id = $copyof_project_id order by control_id";
        $q = db_query($sql);
		$controlid_translate = [];
        while ($row = db_fetch_assoc($q)) {
            $control_id = $row['control_id'];
            unset($row['control_id']);
            $row['project_id'] = $new_project_id;
            $sql = "INSERT INTO redcap_form_display_logic_conditions (".implode(', ', array_keys($row)).") 
                    VALUES (".prep_implode($row, true, true).")";
            db_query($sql);
            $this_control_id = db_insert_id();
            $controlid_translate[$control_id] = $this_control_id;
        }

        // Copy redcap_form_display_logic_targets
		if (!empty($controlid_translate))
		{
			$q2 = db_query("SELECT * FROM redcap_form_display_logic_targets WHERE control_id IN (" . prep_implode(array_keys($controlid_translate)) . ")");
			while ($row2 = db_fetch_assoc($q2)) {
				$new_event_id = '';
				if ($row2['event_id'] != '') {
					$new_event_id = $eventid_translate[$row2['event_id']];
				}
				if (isset($controlid_translate[$row2['control_id']])) {
					db_query("INSERT INTO redcap_form_display_logic_targets (control_id, form_name, event_id) 
						  	  VALUES (" . db_escape($controlid_translate[$row2['control_id']]) . ", '" . db_escape($row2['form_name']) . "', " . checkNull($new_event_id) . ")");
				}
			}
		}
    }
	
	// COPY DDP MAPPINGS
	$sql = "select * from redcap_ddp_mapping where project_id = $copyof_project_id";
	$q = db_query($sql);
	if (db_num_rows($q) > 0) {
		while ($row = db_fetch_assoc($q))
		{
			$sql = "insert into redcap_ddp_mapping (external_source_field_name, is_record_identifier, project_id, 
					event_id, field_name, temporal_field, preselect) values
					(".checkNull($row['external_source_field_name']).", ".checkNull($row['is_record_identifier']).", $new_project_id, 
					".checkNull($eventid_translate[$row['event_id']]).", ".checkNull($row['field_name']).", ".checkNull($row['temporal_field']).", ".checkNull($row['preselect']).")";
			db_query($sql);
		}
		$sql = "insert into redcap_ddp_preview_fields (project_id, field1, field2, field3, field4, field5) 
				select $new_project_id, field1, field2, field3, field4, field5 from redcap_ddp_preview_fields where project_id = $copyof_project_id";
		db_query($sql);
	}

	# USER RIGHTS
	// COPY USER RIGHTS (OF SINGLE USER OF ALL USERS)
	if (isset($_POST['username']) && $superusers_only_create_project && $super_user) {
		// Set username of the user requesting copy
		$single_user_copy = $_POST['username'];
	} else {
		// Set username of the user requesting copy
		$single_user_copy = $userid;
	}
	if ($isTemplate) {
		// ADD USER RIGHTS FOR CREATOR/REQUESTER ONLY (SINCE IT'S A TEMPLATE)
		$sql = "INSERT INTO redcap_user_rights (project_id, username, data_entry, data_export_tool, design, data_quality_design, data_quality_execute,
				random_setup, random_dashboard, random_perform, mobile_app, mobile_app_download_data, alerts)
				VALUES ($new_project_id, '".db_escape($single_user_copy)."', '', 1, 1, 1, 1, $randomization, $randomization, $randomization,
				".(($mobile_app_enabled && $api_enabled) ? '1' : '0').", ".(($mobile_app_enabled && $api_enabled) ? '1' : '0').", 1)";
		$q = db_query($sql);
	} else {
        // Copy this user (and others, if applicable)
        $sql = "insert into redcap_user_rights (project_id, username, expiration, role_id, group_id, lock_record, lock_record_multiform, data_export_instruments,
				data_import_tool, data_comparison_tool, data_logging, file_repository, double_data, user_rights, data_access_groups, graphical,
				reports, design, calendar, data_entry, record_create, record_rename, record_delete, participants, data_quality_design, data_quality_execute,
				data_quality_resolution, random_setup, random_dashboard, random_perform, alerts)
				select '$new_project_id', username, expiration, role_id, group_id, lock_record, lock_record_multiform, data_export_instruments, data_import_tool,
				data_comparison_tool, data_logging, file_repository, double_data, user_rights, data_access_groups, graphical, reports, design,
				calendar, data_entry, record_create, record_rename, record_delete, participants, data_quality_design, data_quality_execute,
				data_quality_resolution, random_setup, random_dashboard, random_perform, alerts
				from redcap_user_rights where project_id = $copyof_project_id";
        if (isset($_POST['copy_users']) && $_POST['copy_users'] == "on") {
            // Copy all users
            $q = db_query($sql);
        } else {
            // Only copy the current normal user
            $q = db_query($sql . " and username = '$single_user_copy'");
        }
        // For super users that were not originally on the project being copied, make sure they get added as well
        if ($super_user && $single_user_copy == $userid) {
            // Give default rights for everything since they're a super user and can access everything anyway
            $sql = "insert into redcap_user_rights (project_id, username, mobile_app, mobile_app_download_data)
					values ($new_project_id, '" . db_escape($userid) . "',
					" . (($mobile_app_enabled && $api_enabled) ? '1' : '0') . ", " . (($mobile_app_enabled && $api_enabled) ? '1' : '0') . ")";
            $q = db_query($sql);
        } // If the current user is a normal user and is also in a role, make sure we first given them their role's privileges before we remove them from the role in the new project
        elseif (!$super_user) {
            $oldRights = UserRights::getPrivileges($copyof_project_id, $userid);
            $oldRights = $oldRights[$copyof_project_id][$userid];
            if ($oldRights['role_id'] != '') {
                unset($oldRights['role_id'], $oldRights['group_id'], $oldRights['api_token'], $oldRights['external_module_config'], $oldRights['role_name'], $oldRights['forms'], $oldRights['project_id'], $oldRights['username']);
                $sql = array();
                foreach ($oldRights as $key => $val) {
                    $sql[] = "$key = " . checkNull($val);
                }
                $sql = "update redcap_user_rights set " . implode(", ", $sql) . " 
                        where project_id = $new_project_id and username = '" . db_escape($userid) . "'";
                $q = db_query($sql);
            }
        }
        // Loop through all users and update their rights with the new group_ids
        if (count($groupid_array) > 0) {
            foreach ($groupid_array as $old_id => $new_id) {
                db_query("update redcap_user_rights set group_id = $new_id where group_id = $old_id and project_id = $new_project_id");
            }
        }
        // Loop through all users and update their rights with the new role_ids
        if (count($userRoleId_array) > 0 && isset($_POST['copy_roles']) && $_POST['copy_roles'] == "on") {
            foreach ($userRoleId_array as $old_id => $new_id) {
                db_query("update redcap_user_rights set role_id = $new_id where role_id = $old_id and project_id = $new_project_id");
            }
        } else {
			// If we're not copying roles but some users are in a role, make sure to transfer their role's rights onto them individually
	        $rolesCopyOfProject = UserRights::getRoles($copyof_project_id);
			$sql = "select username, role_id from redcap_user_rights where project_id = $copyof_project_id and role_id is not null";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$this_role_rights = $rolesCopyOfProject[$row['role_id']] ?? [];
				if (empty($this_role_rights)) continue;
				unset($this_role_rights['role_name'], $this_role_rights['project_id'], $this_role_rights['unique_role_name']);
				$sqla = array();
				foreach ($this_role_rights as $key=>$val) $sqla[] = "$key = ".checkNull($val);
				$sql = "update redcap_user_rights set role_id = null, " . implode(", ", $sqla) . "
						where project_id = $new_project_id and username = '".db_escape($row['username'])."'";
				db_query($sql);
			}
            // Since we're not copying roles, make sure no user is in a role that might've been copied
            db_query("update redcap_user_rights set role_id = null where project_id = $new_project_id");
        }
		// ALWAYS make sure that the user doing the copying has access to User Rights, Setup/Design, and is NOT in a role
		db_query("update redcap_user_rights set user_rights = 1, design = 1, role_id = null,
				  mobile_app = ".(($mobile_app_enabled && $api_enabled) ? '1' : '0').",
				  mobile_app_download_data = ".(($mobile_app_enabled && $api_enabled) ? '1' : '0')."
				  where username = '".db_escape($userid)."' and project_id = $new_project_id");
	}
	// Log "create new user" for all users initially added to this new project
	$sql = "select username from redcap_user_rights where project_id = $new_project_id";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		Logging::logEvent("","redcap_user_rights","insert",$row['username'],"user = '".db_escape($row['username'])."'","Add user");
	}

	if(isset($_POST['copy_folders']) && $_POST['copy_folders'] == '1')
	{
		$user = isset($_POST['username']) ? $_POST['username'] : $userid;
		ProjectFolders::copyProjectFolders(User::getUserInfo($user), $copyof_project_id, $new_project_id);
	}

	// COPY ANY EXTERNAL MODULES ENABLED FOR PROJECT (BUT LEAVE THEM DISABLED BY DEFAULT)
	if (isset($_POST['copy_module_settings']) && $_POST['copy_module_settings'] == "on") 
	{
		if (method_exists('\ExternalModules\ExternalModules', 'copySettings')) {
			\ExternalModules\ExternalModules::copySettings($copyof_project_id, $new_project_id);
		}
		else{
			$sql = "insert into redcap_external_module_settings (external_module_id, project_id, `key`, type, value)
				   select external_module_id, '$new_project_id', `key`, type, value from redcap_external_module_settings
				   where project_id = $copyof_project_id and `key` != 'enabled'";
			db_query($sql);
		}
	}

	// COPY TRANSLATED LANGUAGES
	if ($isTemplate || (isset($_POST['copy_languages']) && $_POST['copy_languages'] == "on"))
	{
		$mlmXmlSettings = MultiLanguage::getProjectSettingsForProjectXml($copyof_project_id);
		$mlmXmlSettings = MultiLanguage::adaptProjectSettingsFromProjectXml($new_project_id, $mlmXmlSettings);
		MultiLanguage::save($new_project_id, $mlmXmlSettings);
	}

    ## COPY MYCAP MOBILE APP DATA (If desired for copy)
    if ($templateMyCapEnabled || (isset($_POST['copy_mycap_mobile_app_content']) && $_POST['copy_mycap_mobile_app_content'] == "on"))
    {
        // Copy redcap_mycap_aboutpages - About contents
        $q = db_query("SELECT * FROM redcap_mycap_aboutpages WHERE project_id = '".$copyof_project_id."' ORDER BY page_order");
        $i = 1;
        while ($row = db_fetch_assoc($q)) {
            $subType = $row['sub_type'];
            $imageType = $row['image_type'];
            if ($i == 1 && $row['image_type'] != MyCap\Page::IMAGETYPE_CUSTOM) {
                $subType = MyCap\Page::SUBTYPE_HOME;
                $imageType = MyCap\Page::IMAGETYPE_CUSTOM;
                $new_edoc_id = MyCap\Page::uploadDefaultImageFile($new_project_id);
            } else {
                if ($row['image_type'] == MyCap\Page::IMAGETYPE_CUSTOM) {
                    $new_edoc_id = copyFile($row['custom_logo'], $new_project_id);
                } else {
                    $new_edoc_id = $row['custom_logo'];
                }
            }
            $i++;

            db_query("INSERT INTO redcap_mycap_aboutpages (project_id, identifier, page_title, page_content, sub_type, image_type, system_image_name, custom_logo, page_order) 
				  VALUES (".$new_project_id.", '".db_escape($row['identifier'])."', '".db_escape($row['page_title'])."', '".db_escape($row['page_content'])."', '".db_escape($subType)."', '".db_escape($imageType)."', '".db_escape($row['system_image_name'])."', '".$new_edoc_id."', '".db_escape($row['page_order'])."')");
        }

        // Copy redcap_mycap_contacts - Contact contents
        $q = db_query("SELECT * FROM redcap_mycap_contacts WHERE project_id = '".$copyof_project_id."' ORDER BY contact_order");
        while ($row = db_fetch_assoc($q)) {
            db_query("INSERT INTO redcap_mycap_contacts (project_id , identifier, contact_header, contact_title, phone_number, email, website, additional_info, contact_order) 
				            VALUES (".$new_project_id.", '".db_escape($row['identifier'])."', '".db_escape($row['contact_header'])."', '".db_escape($row['contact_title'])."', '".db_escape($row['phone_number'])."', '".db_escape($row['email'])."', '".db_escape($row['website'])."', '".db_escape($row['additional_info'])."', '".db_escape($row['contact_order'])."')");
        }

        // Copy redcap_mycap_links - Links contents
        $q = db_query("SELECT * FROM redcap_mycap_links WHERE project_id = '".$copyof_project_id."' ORDER BY link_order");
        while ($row = db_fetch_assoc($q)) {
            db_query("INSERT INTO redcap_mycap_links (project_id , identifier, link_name, link_url, link_icon, append_project_code, append_participant_code, link_order) 
				            VALUES (".$new_project_id.", '".db_escape($row['identifier'])."', '".db_escape($row['link_name'])."', '".db_escape($row['link_url'])."', '".db_escape($row['link_icon'])."', '".db_escape($row['append_project_code'])."', '".db_escape($row['append_participant_code'])."', '".db_escape($row['link_order'])."')");
        }

        // Copy redcap_mycap_themes - Theme Setting
        $sql = "INSERT INTO redcap_mycap_themes (project_id , primary_color, light_primary_color, accent_color, dark_primary_color, light_bg_color, theme_type, system_type)
				   SELECT '$new_project_id', primary_color, light_primary_color, accent_color, dark_primary_color, light_bg_color, theme_type, system_type
				        FROM redcap_mycap_themes
				        WHERE project_id = $copyof_project_id";
        db_query($sql);

        $sql = "SELECT converted_to_flutter FROM redcap_mycap_projects WHERE project_id = $copyof_project_id LIMIT 1";
        $convertedToFlutter = db_result(db_query($sql), 0);

        // Project is enabled first time update default initial config
        $myCap = new MyCap\MyCap();
        $sql = "INSERT INTO redcap_mycap_projects (code, hmac_key, project_id, name, converted_to_flutter) 
                VALUES ('".$myCap->generateUniqueCode()."',
                    '".$myCap->generateHmacKey()."',
                    '".$new_project_id."',
                    '".db_escape($_POST['app_title'])."',
                    '".$convertedToFlutter."')";
        $addedToMyCapProjects = db_query($sql);

        if ($isTemplate) {
            $sqlConvertToFlutter = "UPDATE redcap_mycap_projects SET converted_to_flutter = '1', acknowledged_app_link = '1' WHERE project_id = '".$new_project_id."'";
            db_query($sqlConvertToFlutter);
        }
    } elseif ($_POST['mycap_enabled']) {
        $myCap = new MyCap\MyCap($new_project_id);
        $response = $myCap->initMyCap($new_project_id);
    }

	// If using survey_pid_create_project public survey, then store the PID of this new project in the "project_id" field of that project
	Survey::savePidForCustomPublicSurveyStatusChange('survey_pid_create_project', $_POST['survey_pid_create_project'] ?? null, $new_project_id);

    // If user requested copy, then send user email confirmation of copy
	if (isset($_POST['username']) && $superusers_only_create_project && $super_user) {
		// Email the user requesting this db
		$email = new Message();
		$email->setFrom($project_contact_email);
		$email->setFromName($GLOBALS['project_contact_name']);
		$email->setTo($_POST['user_email']);
		if ($isTemplate) {
			// Create project email
			$emailSubject  =   "[REDCap] {$lang['create_project_32']}";
			$emailContents =   "{$lang['create_project_33']}
								<b>" . html_entity_decode($_POST['app_title'], ENT_QUOTES) . "</b>.<br><br>
								<a href='" . APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/ProjectSetup/index.php?pid=$new_project_id&msg=newproject'>{$lang['create_project_31']}</a>";
		} else {
			// Copy project email
			$emailSubject  =   "[REDCap] {$lang['create_project_28']}";
			$emailContents =   "{$lang['create_project_30']}
								<b>" . html_entity_decode($_POST['app_title'], ENT_QUOTES) . "</b>.<br><br>
								<a href='" . APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/ProjectSetup/index.php?pid=$new_project_id&msg=newproject'>{$lang['create_project_31']}</a>";
		}
		$email->setBody($emailContents, true);
		$email->setSubject($emailSubject);
		$email->send();
		//update redcap_todo_list
		ToDoList::updateTodoStatusNewProject((int)$_POST['request_id'], $new_project_id);
		// Commit to db
		db_query("COMMIT");db_query("SET AUTOCOMMIT=1");
		// Redirect super user to a confirmation page
		redirect(APP_PATH_WEBROOT_PARENT . "index.php?action=approved_copy&user_email=" . $_POST['user_email']);
	}

}

/**
 * CREATING A NEW PROJECT
 */
else {
	// Determine project creation type
	$odmUpload = (isset($_FILES['odm']) && $_FILES['odm']['size'] > 0);
	$odmEdocId = (isset($_POST['odm_edoc_id']) && is_numeric($_POST['odm_edoc_id']));
	// Logging
	$logDescrip = "Create project";
	if ($odmEdocId	|| $odmUpload) $logDescrip .= " using REDCap XML file";
	elseif ($ehrDataMartProject) $logDescrip .= " (Clinical Data Mart)";
	Logging::logEvent("","redcap_projects","MANAGE",$new_project_id,"project_id = $new_project_id",$logDescrip);

	// Message flag used for dialog pop-up
	$msg_flag = "newproject";

	// ODM file import or EHR data mart (which also using ODM file)
	if ($odmEdocId	|| $odmUpload || $ehrDataMartProject) {
		// Set flag
		define("CREATE_PROJECT_ODM", true);
		// Get contents from file
		if ($ehrDataMartProject) {
			$ehrDataMartOdm = DataMart::getProjectTemplatePath();
			$odm = file_get_contents($ehrDataMartOdm);
			if (empty($odm)) {
				db_query("ROLLBACK");
				db_query("SET AUTOCOMMIT=1");
				exit("ERROR: Could not find the following file on the server: $ehrDataMartOdm");
			}
		} elseif ($odmUpload) {
			$odm = file_get_contents($_FILES['odm']['tmp_name']);
			unlink($_FILES['odm']['tmp_name']);
		} else {
			list ($odm_mime_type, $odm_doc_name, $odm) = Files::getEdocContentsAttributes($_POST['odm_edoc_id']);
		}
		// Get uploaded file's contents and parse it
		$odm_response = ODM::parseOdm($odm);
        global $mycap_enabled_global;
		$hasMyCapData = ($mycap_enabled_global && ($odm_response['hasMyCapData']??false));
        $errors = $odm_response['errors'];
		// EHR Data Mart: Retrieve EHR data and add to new project
		if (empty($errors) && $ehrDataMartProject) 
		{
			try {
				$uiid = User::getUIIDByUsername($userid);
				$dataMart = new DataMart($uiid);
				$dataMartSettings = $_POST['datamart'];
				if($request_id = $dataMartSettings['request_id'])
				{
					// approving a revision request
					$revision = $dataMart->getRevisionFromRequest($request_id);
					if($revision) {
						$revision = $revision->setProjectId($new_project_id);
						$revision = $dataMart->approveRevision($revision); //approve revision and save to database
					}
				}else
				{
					// add the first revision to the project
					$revision = $dataMart->addRevision(array(
						'user_id' => $uiid,
						'project_id' => $new_project_id,
						'mrns' => $dataMartSettings['mrns'],
						'date_min' => $dataMartSettings['daterange']['min'],
						'date_max' => $dataMartSettings['daterange']['max'],
						'fields' => $dataMartSettings['fields'],
						'date_range_categories' => $dataMartSettings['date_range_categories'],
					));
				}
			} catch (\Exception $e) {
				$errors = array('datamart' => $e->getMessage());
			}
		}
		// Check for errors
		if (!empty($errors))
		{
			$objHtmlPage = new HtmlPage();
			$objHtmlPage->PrintHeaderExt();
			// TABS			
			include APP_PATH_VIEWS . 'HomeTabs.php';
			// Errors
			print RCView::div(array('style'=>'text-align:left;margin:60px 0;width:100%;max-width:800px;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;margin:5px 0;'), $lang['create_project_129']) .
					"<ul><li>" . implode("</li><li>", $errors) . "</li></ul>"
				  );
			$objHtmlPage->PrintFooter();
			// Undo all changes if any errors occur
			db_query("ROLLBACK");
			db_query("SET AUTOCOMMIT=1");
			deleteProjectNow($new_project_id, false);
			exit;
		}
		
		// Set $form_names array
		$form_names = array();
		$sql = "select distinct form_name from redcap_metadata where project_id = $new_project_id";
		$q = db_query($sql);
		while ($rowf = db_fetch_assoc($q)) {
			$form_names[] = $rowf['form_name'];
		}
	}
	else
	{
		// Give this new project an arm and an event (default)
		Project::insertDefaultArmAndEvent($new_project_id);
		// Now add the new project's metadata
		$form_names = createMetadata($new_project_id, $_POST['surveys_enabled']);
	}

	// If using survey_pid_create_project public survey, then store the PID of this new project in the "project_id" field of that project
	Survey::savePidForCustomPublicSurveyStatusChange('survey_pid_create_project', $_POST['survey_pid_create_project'] ?? null, $new_project_id);

	## USER RIGHTS
	if (isset($_POST['username']) && $superusers_only_create_project && $super_user)
	{
		// Insert user rights for this new project for user REQUESTING the project
		$mobile_app = ($mobile_app_enabled && $api_enabled) ? 1 : 0;
		Project::insertUserRightsProjectCreator($new_project_id, $_POST['username'], 0, $mobile_app, $form_names);

		// Email the user requesting this db
		$email = new Message();
		$email->setFrom($project_contact_email);
		$email->setFromName($GLOBALS['project_contact_name']);
		$email->setTo($_POST['user_email']);
		$emailSubject  =   "[REDCap] {$lang['create_project_32']}";
		$emailContents =   "{$lang['create_project_33']}
							<b>" . html_entity_decode($_POST['app_title'], ENT_QUOTES) . "</b>.<br><br>
							<a href='" . APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/ProjectSetup/index.php?pid=$new_project_id&msg=newproject'>{$lang['create_project_31']}</a>";
		$email->setBody($emailContents, true);
		$email->setSubject($emailSubject);
		//update redcap_todo_list first
		ToDoList::updateTodoStatusNewProject((int)$_POST['request_id'], $new_project_id);
		$email->send();

		// Commit to db
		db_query("COMMIT");db_query("SET AUTOCOMMIT=1");
		// Redirect super user to a confirmation page
		redirect(APP_PATH_WEBROOT_PARENT . "index.php?action=approved_new&new_pid=$new_project_id&user_email=" . $_POST['user_email']);
	}
	else
	{
		// Insert user rights for this new project for user CREATING the project
		$mobile_app = ($mobile_app_enabled && $api_enabled) ? 1 : 0;
		Project::insertUserRightsProjectCreator($new_project_id, $userid, $_POST['randomization'], $mobile_app, $form_names);
	}
}


// Commit to db
db_query("COMMIT");db_query("SET AUTOCOMMIT=1");

## If copying an existing project
if (isset($_POST['copyof']) && is_numeric($_POST['copyof'])) {

    ## COPY MYCAP MOBILE APP DATA (If desired for copy)
    if ($templateMyCapEnabled || (isset($_POST['copy_mycap_mobile_app_content']) && $_POST['copy_mycap_mobile_app_content'] == "on"))
    {
        if ($addedToMyCapProjects) {
            MyCap\Page::createAboutImagesZip($new_project_id, true);
        }

        if ((!$isTemplate && isset($_POST['copy_records']) && $_POST['copy_records'] == "on") || ($isTemplate && $templateCopyRecords)) {
            // COPY ANY MYCAP PARTICIPANTS
            $myCap->copyProjectParticipants($copyof_project_id, $new_project_id, $eventid_translate);
        }
        // COPY ANY MYCAP PARTICIPANTS SETTINGS IF MYCAP MOBILE APP OPTION SELECTED
        $myCap->copyProjectParticipantSettings($copyof_project_id, $new_project_id, $eventid_translate);
        if ($addedToMyCapProjects) {
            $myCap->updateProjectConfig($new_project_id);
            $myCap->publishConfigVersion($new_project_id);
        }
        // Reset Baseline Date and Join date in new project
        MyCap\MyCap::resetBaselineJoinDates($new_project_id);

        // Add new date field name="Install Date" with annotation @MC-PARTICIPANT-JOINDATE @HIDDEN
        Form::addMyCapInstallDateField($new_project_id);
        // Add new date field name="Install Date (UTC)" with annotation @MC-PARTICIPANT-JOINDATE_UTC @HIDDEN and timezone field
        Form::addExtraMyCapInstallDateField($new_project_id);
        // Add new date field name="Code" with annotation @MC-PARTICIPANT-CODE @HIDDEN
        Form::addMyCapCodeField($new_project_id);
    }
} elseif (isset($hasMyCapData) && $hasMyCapData) {
    $myCap = new MyCap\MyCap();
    // Insert into redcap_mycap_projects table
    $sql = "INSERT INTO redcap_mycap_projects (code, hmac_key, project_id, name) 
            VALUES ('".$myCap->generateUniqueCode()."',
                    '".$myCap->generateHmacKey()."',
                    '".$new_project_id."',
                    '".db_escape($_POST['app_title'])."')";
    $q = db_query($sql);
    if (!$q || db_affected_rows() != 1) {
        print db_error();
        queryFail($sql);
    }

    if ($Proj->longitudinal) {
        if (isset($odm_response['mycap_settings']['baseline_date_field']) && $odm_response['mycap_settings']['baseline_date_field'] != '') {
            if ($Proj->multiple_arms) {
                $baseline_date_field = [];
                $date_arr = explode('|', $odm_response['mycap_settings']['baseline_date_field']);
                if (count($date_arr) > 0) {
                    foreach ($date_arr as $dateArm) {
                        $arr = explode('-', $dateArm);
                        if (count($arr) == 2 && $arr[0] !='') {
                            $eventId = $Proj->getEventIdUsingUniqueEventName($arr[0]);
                            $baseline_date_field[] = $eventId.'-'.$arr[1];
                        }
                    }
                }
                $odm_response['mycap_settings']['baseline_date_field'] = implode("|", $baseline_date_field);
            } else {
                $arr = explode('-', $odm_response['mycap_settings']['baseline_date_field']);
                if (count($arr) == 2 && $arr[0] !='') {
                    $eventId = $Proj->getEventIdUsingUniqueEventName($arr[0]);
                }
                $odm_response['mycap_settings']['baseline_date_field'] = $eventId.'-'.$arr[1];
            }
        }
    }
    // Set Baseline Date settings and participant related settings
    MyCap\MyCap::setMyCapSettings($odm_response['mycap_settings'], $new_project_id);

    // Created project via xml upload and original project is having PROMIS instruments: in new project PROMIS instrument is not PROMIS
    // Set task Question format and Chart Display to default values, also set "extended_config_json" as blank
    $myCapTasks = Task::getAllTasksSettings($new_project_id);
    foreach ($myCapTasks as $taskId => $attr) {
        if ($attr['question_format'] == Task::PROMIS) {
            $sql = "UPDATE redcap_mycap_tasks SET question_format = '".Task::QUESTIONNAIRE."', card_display = '".Task::TYPE_PERCENTCOMPLETE."', extended_config_json = NULL
                    WHERE project_id = '".$new_project_id."' AND task_id = '".$taskId."'";
            db_query($sql);
        }
    }

    $myCap->updateProjectConfig($new_project_id);
    MyCap\Page::createAboutImagesZip($new_project_id, true);

    // Reset Baseline Date and Join date in new project
    MyCap\MyCap::resetBaselineJoinDates($new_project_id);
}
$project_info = array($new_project_id, $msg_flag, $_POST['app_title'], USERID);
\ExternalModules\ExternalModules::callHook('redcap_module_project_save_after', $project_info);
// Redirect to the new project
redirect(APP_PATH_WEBROOT . "ProjectSetup/index.php?pid=$new_project_id&msg=$msg_flag&__record_cache_complete=1"); // Add __record_cache_complete flag to prevent building the cache immediately
