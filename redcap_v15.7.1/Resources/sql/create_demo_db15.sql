
-- SQL TO CREATE A REDCAP DEMO PROJECT --
set @project_title = 'MyCap Example Project';


-- Obtain default values --
set @institution = (select value from redcap_config where field_name = 'institution' limit 1);
set @site_org_type = (select value from redcap_config where field_name = 'site_org_type' limit 1);
set @grant_cite = (select value from redcap_config where field_name = 'grant_cite' limit 1);
set @project_contact_name = (select value from redcap_config where field_name = 'project_contact_name' limit 1);
set @project_contact_email = (select value from redcap_config where field_name = 'project_contact_email' limit 1);
set @headerlogo = (select value from redcap_config where field_name = 'headerlogo' limit 1);
set @auth_meth = (select value from redcap_config where field_name = 'auth_meth_global' limit 1);
set @pcode = upper(concat(LEFT(sha1(rand()),20)));
-- Create project --
INSERT INTO `redcap_projects`
(project_name, app_title, status, count_project, auth_meth, creation_time, production_time, institution, site_org_type, grant_cite, project_contact_name, project_contact_email, headerlogo, display_project_logo_institution, auto_inc_set, mycap_enabled, custom_record_label, survey_email_participant_field, surveys_enabled) VALUES
(concat('redcap_demo_',LEFT(sha1(rand()),6)), @project_title, 1, 0, @auth_meth, now(), now(), @institution, @site_org_type, @grant_cite, @project_contact_name, @project_contact_email, @headerlogo, 0, 1, 1, '[name] [email]', 'email', 1);
set @project_id = LAST_INSERT_ID();
-- Create single arm --
INSERT INTO redcap_events_arms (project_id, arm_num, arm_name) VALUES (@project_id, 1, 'Arm 1');
set @arm_id = LAST_INSERT_ID();
-- Create single event --
INSERT INTO redcap_events_metadata (arm_id, day_offset, offset_min, offset_max, descrip) VALUES (@arm_id, 0, 0, 0, 'Event 1');
set @event_id = LAST_INSERT_ID();
-- Set repeating instruments
INSERT INTO `redcap_events_repeat` (`event_id`, `form_name`, `custom_repeat_form_label`) VALUES
(@event_id, 'welcome', NULL),
(@event_id, 'morning_checkin', NULL),
(@event_id, 'weekend_plans', NULL),
(@event_id, 'peak_flow_rate', NULL),
(@event_id, 'tapping_interval_task', NULL),
(@event_id, 'tower_of_hanoi_active_task', NULL),
(@event_id, 'tone_audiometry_active_task', NULL),
(@event_id, 'timed_walk_active_task', NULL),
(@event_id, 'spatial_span_memory_test_active_task', NULL),
(@event_id, 'fitness_check_active_task', NULL),
(@event_id, 'reaction_time_active_task', NULL),
(@event_id, 'psat_active_task', NULL),
(@event_id, 'short_walk_active_task', NULL),
(@event_id, 'audio_active_task', NULL),
(@event_id, 'selfie_capture', NULL);

INSERT INTO `redcap_surveys` (`project_id`, `form_name`, `title`, `instructions`, `offline_instructions`, `acknowledgement`, `stop_action_acknowledgement`, `stop_action_delete_response`, `question_by_section`, `display_page_number`, `question_auto_numbering`, `survey_enabled`, `save_and_return`, `save_and_return_code_bypass`, `logo`, `hide_title`, `view_results`, `min_responses_view_results`, `check_diversity_view_results`, `end_survey_redirect_url`, `survey_expiration`, `promis_skip_question`, `survey_auth_enabled_single`, `edit_completed_response`, `hide_back_button`, `show_required_field_text`, `confirmation_email_subject`, `confirmation_email_content`, `confirmation_email_from`, `confirmation_email_from_display`, `confirmation_email_attach_pdf`, `confirmation_email_attachment`, `text_to_speech`, `text_to_speech_language`, `end_survey_redirect_next_survey`, `end_survey_redirect_next_survey_logic`, `theme`, `text_size`, `font_family`, `theme_text_buttons`, `theme_bg_page`, `theme_text_title`, `theme_bg_title`, `theme_text_sectionheader`, `theme_bg_sectionheader`, `theme_text_question`, `theme_bg_question`, `enhanced_choices`, `repeat_survey_enabled`, `repeat_survey_btn_text`, `repeat_survey_btn_location`, `response_limit`, `response_limit_include_partials`, `response_limit_custom_text`, `survey_time_limit_days`, `survey_time_limit_hours`, `survey_time_limit_minutes`, `email_participant_field`, `end_of_survey_pdf_download`, `pdf_save_to_field`, `pdf_save_to_event_id`, `pdf_save_translated`, `pdf_auto_archive`, `pdf_econsent_version`, `pdf_econsent_type`, `pdf_econsent_firstname_field`, `pdf_econsent_firstname_event_id`, `pdf_econsent_lastname_field`, `pdf_econsent_lastname_event_id`, `pdf_econsent_dob_field`, `pdf_econsent_dob_event_id`, `pdf_econsent_allow_edit`, `pdf_econsent_signature_field1`, `pdf_econsent_signature_field2`, `pdf_econsent_signature_field3`, `pdf_econsent_signature_field4`, `pdf_econsent_signature_field5`, `survey_width_percent`, `survey_show_font_resize`, `survey_btn_text_prev_page`, `survey_btn_text_next_page`, `survey_btn_text_submit`, `survey_btn_hide_submit`, `survey_btn_hide_submit_logic`) VALUES
(@project_id, 'participant_intake', 'Participant Intake', NULL, NULL, '<p><strong>Use one of the options below to join the project on MyCap:</strong></p>\n<ol>\n<li>[mycap-participant-link:Click this MyCap link] while on your mobile device, which will prompt you to install MyCap if it\'s not already installed. After MyCap is open, tap \"Join Project\". (Note: If you have other MyCap projects, you may need to go to your Profile and click \"Join Another Project\").</li>\n<li>To scan the QR Code below, install the MyCap app on your mobile device (iOS: <a href=\"https://apps.apple.com/us/app/pacym/id6448734173\"><u>App Store</u></a>, Android: <a href=\"https://play.google.com/store/apps/details?id=org.vumc.victr.mycap\"><u>Play Store</u></a>), open the MyCap app, and tap \"Join Project\".<br /><img src=\"http://localhost/redcap_standard/redcap_v13.0.0/MyCap/participant_info.php?action=displayParticipantQrCode&pid=[project-id]&preview_pid=80&par_code=[mycap-participant-code]\" width=\"185\" height=\"185\" /></li>\n</ol>', NULL, 0, 0, 0, 1, 1, 0, 0, NULL, 0, 0, 10, 0, NULL, NULL, 0, 0, 0, 0, 1, 'Join Your Project', '<p><strong>Use one of the options below to join the project on MyCap:</strong></p>\n<ol>\n<li>[mycap-participant-link:Click this MyCap link] while on your mobile device, which will prompt you to install MyCap if it\'s not already installed. After MyCap is open, tap \"Join Project\". (Note: If you have other MyCap projects, you may need to go to your Profile and click \"Join Another Project\").</li>\n<li>To scan the QR Code below, install the MyCap app on your mobile device (iOS: <a href=\"https://itunes.apple.com/us/app/mycap/id1209842552?ls=1&mt=8\"><u>App Store</u></a>, Android: <a href=\"https://play.google.com/store/apps/details?id=org.vumc.victr.mycap\"><u>Play Store</u></a>), open the MyCap app, and tap \"Join Project\".<br /><img src=\"http://localhost/redcap_standard/redcap_v13.0.0/MyCap/participant_info.php?action=displayParticipantQrCode&pid=[project-id]&preview_pid=80&par_code=[mycap-participant-code]\" width=\"185\" height=\"185\" /></li>\n</ol>', 'mycap@vumc.org', 'MyCap', 0, NULL, 0, 'en', 0, NULL, NULL, 1, 16, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 'HIDDEN', NULL, 1, '<p>Thank you for your interest; however, the survey is closed because the maximum number of responses has been reached.</p>', NULL, NULL, NULL, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 0, NULL);

-- Insert into redcap_metadata --
INSERT INTO `redcap_metadata` (`project_id`, `field_name`, `field_phi`, `form_name`, `form_menu_description`, `field_order`, `field_units`, `element_preceding_header`, `element_type`, `element_label`, `element_enum`, `element_note`, `element_validation_type`, `element_validation_min`, `element_validation_max`, `element_validation_checktype`, `branching_logic`, `field_req`, `edoc_id`, `edoc_display_img`, `custom_alignment`, `stop_actions`, `question_num`, `grid_name`, `grid_rank`, `misc`, `video_url`, `video_display_inline`) VALUES
(@project_id, 'record_id', NULL, 'participant_intake', 'Participant Intake', 1, NULL, NULL, 'text', 'Record ID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'name', NULL, 'participant_intake', NULL, 2, NULL, NULL, 'text', 'Name/Study ID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 1, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'site', NULL, 'participant_intake', NULL, 3, NULL, NULL, 'text', 'Site ID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'email', NULL, 'participant_intake', NULL, 4, NULL, NULL, 'text', '<div class=\"rich-text-field-label\"><p>Email <br /><br /><span style=\"font-weight: normal;\">After completing this survey, a QR Code and Dynamic Link to join the project will be displayed. You can use either method to join. If you enter your email address, you will receive an email with this information, too.</span></p></div>', NULL, NULL, 'email', NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'baseline', NULL, 'participant_intake', NULL, 5, NULL, NULL, 'text', 'Baseline', NULL, NULL, 'date_mdy', NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN', NULL, 0),
(@project_id, 'par_joindate', NULL, 'participant_intake', NULL, 5.1, NULL, NULL, 'text', 'Install Date', NULL, NULL, 'datetime_seconds_ymd', NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-PARTICIPANT-JOINDATE @HIDDEN', NULL, 0),
(@project_id, 'participant_intake_complete', NULL, 'participant_intake', NULL, 6, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'wel_whyinterested', NULL, 'welcome', 'Welcome', 7, NULL, NULL, 'radio', 'RADIO BUTTON: Why are you interested in MyCap?', '1, I am a researcher and want to test MyCap\\n2, I am interested in participating in a project that uses MyCap\\n3, Other', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'wel_whyinterestedother', NULL, 'welcome', NULL, 8, NULL, NULL, 'textarea', 'NOTES BOX: Describe why you are interested in MyCap', NULL, NULL, NULL, NULL, NULL, NULL, '[wel_whyinterested] = \'3\'', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'wel_hasfavoritefood', NULL, 'welcome', NULL, 9, NULL, NULL, 'yesno', 'YES - NO: Do you have a favorite food?', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'true_false', NULL, 'welcome', NULL, 10, NULL, NULL, 'truefalse', 'TRUE - FALSE: Santa Claus is real.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'wel_favoritefood', NULL, 'welcome', NULL, 11, NULL, NULL, 'text', 'TEXT BOX (Required): What is your favorite food?', NULL, NULL, NULL, NULL, NULL, 'soft_typed', '[wel_hasfavoritefood] = \'1\'', 1, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'wel_favoriteday', NULL, 'welcome', NULL, 12, NULL, NULL, 'text', 'TEXT BOX (datetime validation): What time did you wake up today?', NULL, NULL, 'datetime_mdy', NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, 'RH', NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'wel_age', NULL, 'welcome', NULL, 13, NULL, NULL, 'select', 'DROP-DOWN: What is your age?', '1, Under 18\\n2, 18 to 30\\n3, 31 to 45\\n4, 46 to 67\\n5, 68+', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'wel_howmanypeople', NULL, 'welcome', NULL, 14, NULL, NULL, 'slider', 'BASIC SLIDER (step by 2): Rate how happy you feel today (0 - very unhappy, 10 is very happy)', NULL, NULL, 'number', NULL, '10', NULL, NULL, 0, NULL, 0, 'RH', NULL, NULL, NULL, 0, '@MC-FIELD-SLIDER-BASIC=0:10:2', NULL, 0),
(@project_id, 'cont_slider', NULL, 'welcome', NULL, 15, NULL, NULL, 'slider', 'CONTINUOUS SLIDER: Rate how tired you are where 0 = Full of Energy and 100 = totally exhausted.', NULL, NULL, 'number', NULL, NULL, NULL, NULL, 0, NULL, 0, 'RH', NULL, NULL, NULL, 0, '@MC-FIELD-SLIDER-CONTINUOUS=0:100:0', NULL, 0),
(@project_id, 'image', NULL, 'welcome', NULL, 16, NULL, NULL, 'file', 'IMAGE CAPTURE: Take a photo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-FIELD-FILE-IMAGECAPTURE', NULL, 0),
(@project_id, 'video', NULL, 'welcome', NULL, 17, NULL, NULL, 'file', 'VIDEO CAPTURE: Record a 10 second video', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-FIELD-FILE-VIDEOCAPTURE=10:YES:OFF:BACK', NULL, 0),
(@project_id, 'wel_uuid', NULL, 'welcome', NULL, 18, NULL, 'Mobile App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-UUID', NULL, 0),
(@project_id, 'wel_startdate', NULL, 'welcome', NULL, 19, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'wel_enddate', NULL, 'welcome', NULL, 20, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'wel_scheduledate', NULL, 'welcome', NULL, 21, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE @HIDDEN', NULL, 0),
(@project_id, 'wel_status', NULL, 'welcome', NULL, 22, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS @HIDDEN', NULL, 0),
(@project_id, 'wel_supplementaldata', NULL, 'welcome', NULL, 23, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'wel_serializedresult', NULL, 'welcome', NULL, 24, NULL, NULL, 'file', 'serializedresult', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'welcome_complete', NULL, 'welcome', NULL, 25, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'mor_didtakemeds', NULL, 'morning_checkin', 'Morning Checkin', 26, NULL, NULL, 'yesno', 'YES-NO: Did you take your medications this morning?', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'mor_howhappy', NULL, 'morning_checkin', NULL, 27, NULL, NULL, 'slider', 'CONTINUOUS SLIDER: How happy are you feeling right now? 0 = Unhappy, 10 = Very Happy', NULL, NULL, 'number', NULL, '10', NULL, NULL, 1, NULL, 0, 'RH', NULL, NULL, NULL, 0, '@MC-FIELD-SLIDER-CONTINUOUS=0:10:1', NULL, 0),
(@project_id, 'mor_uuid', NULL, 'morning_checkin', NULL, 28, NULL, 'Mobile App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-UUID', NULL, 0),
(@project_id, 'mor_startdate', NULL, 'morning_checkin', NULL, 29, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'mor_enddate', NULL, 'morning_checkin', NULL, 30, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'mor_scheduledate', NULL, 'morning_checkin', NULL, 31, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE @HIDDEN', NULL, 0),
(@project_id, 'mor_status', NULL, 'morning_checkin', NULL, 32, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS @HIDDEN', NULL, 0),
(@project_id, 'mor_supplementaldata', NULL, 'morning_checkin', NULL, 33, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'mor_serializedresult', NULL, 'morning_checkin', NULL, 34, NULL, NULL, 'file', 'serializedresult', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'morning_checkin_complete', NULL, 'morning_checkin', NULL, 35, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'wee_plans', NULL, 'weekend_plans', 'Weekend Plans', 36, NULL, NULL, 'checkbox', 'CHECKBOX: What are you going to do this weekend?', '1, Go out to eat\\n2, Play a sport\\n3, Watch a movie\\n4, Visit with friends or family\\n5, Travel', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'wee_weather', NULL, 'weekend_plans', NULL, 37, NULL, NULL, 'radio', 'RADIO BUTTON: What does the weather look like for this weekend?', '1, Rainy\\n2, Stormy\\n3, Sunny\\n4, Cloudy', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'wee_uuid', NULL, 'weekend_plans', NULL, 38, NULL, 'Mobile App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-UUID', NULL, 0),
(@project_id, 'wee_startdate', NULL, 'weekend_plans', NULL, 39, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'wee_enddate', NULL, 'weekend_plans', NULL, 40, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'wee_scheduledate', NULL, 'weekend_plans', NULL, 41, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE @HIDDEN', NULL, 0),
(@project_id, 'wee_status', NULL, 'weekend_plans', NULL, 42, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS @HIDDEN', NULL, 0),
(@project_id, 'wee_supplementaldata', NULL, 'weekend_plans', NULL, 43, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'wee_serializedresult', NULL, 'weekend_plans', NULL, 44, NULL, NULL, 'file', 'serializedresult', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'weekend_plans_complete', NULL, 'weekend_plans', NULL, 45, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'pea_date', NULL, 'peak_flow_rate', 'Peak Flow Rate', 46, NULL, NULL, 'text', 'TEXT BOX (Date validation): Date', NULL, NULL, 'date_mdy', NULL, NULL, 'soft_typed', NULL, 1, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'pea_time', NULL, 'peak_flow_rate', NULL, 47, NULL, NULL, 'text', 'TEXT BOX (Time validation): Time', NULL, NULL, 'time', NULL, NULL, 'soft_typed', NULL, 1, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'pea_value', NULL, 'peak_flow_rate', NULL, 48, NULL, NULL, 'text', 'TEXT BOX (Integer validation): Pick a number between 1 and 10', NULL, NULL, 'int', '0', '10', 'soft_typed', NULL, 1, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'pea_uuid', NULL, 'peak_flow_rate', NULL, 49, NULL, 'Mobile App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-UUID', NULL, 0),
(@project_id, 'pea_startdate', NULL, 'peak_flow_rate', NULL, 50, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'pea_enddate', NULL, 'peak_flow_rate', NULL, 51, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'pea_scheduledate', NULL, 'peak_flow_rate', NULL, 52, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'pea_status', NULL, 'peak_flow_rate', NULL, 53, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS @HIDDEN', NULL, 0),
(@project_id, 'pea_supplementaldata', NULL, 'peak_flow_rate', NULL, 54, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@HIDDEN @MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'pea_serializedresult', NULL, 'peak_flow_rate', NULL, 55, NULL, NULL, 'file', 'serializedresult', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'peak_flow_rate_complete', NULL, 'peak_flow_rate', NULL, 56, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'leftjson', NULL, 'tapping_interval_task', 'Tapping Interval Task', 57, NULL, NULL, 'textarea', 'Left Hand JSON', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-TWO-LEFT', NULL, 0),
(@project_id, 'leftaccelerometer', NULL, 'tapping_interval_task', NULL, 58, NULL, NULL, 'file', 'Left Hand Accelerometer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-TWO-LEFT-ACCELEROMETER', NULL, 0),
(@project_id, 'rightjson', NULL, 'tapping_interval_task', NULL, 59, NULL, NULL, 'textarea', 'Right Hand JSON', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-TWO-RIGHT', NULL, 0),
(@project_id, 'rightaccelerometer', NULL, 'tapping_interval_task', NULL, 60, NULL, NULL, 'file', 'Right Hand Accelerometer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-TWO-RIGHT-ACCELEROMETER', NULL, 0),
(@project_id, 'uuid', NULL, 'tapping_interval_task', NULL, 61, NULL, 'MyCap App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-UUID', NULL, 0),
(@project_id, 'startdate', NULL, 'tapping_interval_task', NULL, 62, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'enddate', NULL, 'tapping_interval_task', NULL, 63, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'scheduledate', NULL, 'tapping_interval_task', NULL, 64, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'status', NULL, 'tapping_interval_task', NULL, 65, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS', NULL, 0),
(@project_id, 'supplementaldata', NULL, 'tapping_interval_task', NULL, 66, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'serializedresult', NULL, 'tapping_interval_task', NULL, 67, NULL, NULL, 'file', 'Serialized Result', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'tapping_interval_task_complete', NULL, 'tapping_interval_task', NULL, 68, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'json', NULL, 'tower_of_hanoi_active_task', 'Tower of Hanoi Active Task', 69, NULL, NULL, 'textarea', 'JSON', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-TOW', NULL, 0),
(@project_id, 'uuid_11', NULL, 'tower_of_hanoi_active_task', NULL, 70, NULL, 'MyCap App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-UUID', NULL, 0),
(@project_id, 'startdate_11', NULL, 'tower_of_hanoi_active_task', NULL, 71, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'enddate_11', NULL, 'tower_of_hanoi_active_task', NULL, 72, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'scheduledate_11', NULL, 'tower_of_hanoi_active_task', NULL, 73, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'status_11', NULL, 'tower_of_hanoi_active_task', NULL, 74, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS', NULL, 0),
(@project_id, 'supplementaldata_11', NULL, 'tower_of_hanoi_active_task', NULL, 75, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'serializedresult_11', NULL, 'tower_of_hanoi_active_task', NULL, 76, NULL, NULL, 'file', 'Serialized Result', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'tower_of_hanoi_active_task_complete', NULL, 'tower_of_hanoi_active_task', NULL, 77, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'json_2', NULL, 'tone_audiometry_active_task', 'Tone Audiometry Active Task', 78, NULL, NULL, 'textarea', 'JSON', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-TON', NULL, 0),
(@project_id, 'uuid_10', NULL, 'tone_audiometry_active_task', NULL, 79, NULL, 'MyCap App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-UUID', NULL, 0),
(@project_id, 'startdate_10', NULL, 'tone_audiometry_active_task', NULL, 80, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'enddate_10', NULL, 'tone_audiometry_active_task', NULL, 81, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'scheduledate_10', NULL, 'tone_audiometry_active_task', NULL, 82, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'status_10', NULL, 'tone_audiometry_active_task', NULL, 83, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS', NULL, 0),
(@project_id, 'supplementaldata_10', NULL, 'tone_audiometry_active_task', NULL, 84, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'serializedresult_10', NULL, 'tone_audiometry_active_task', NULL, 85, NULL, NULL, 'file', 'Serialized Result', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'tone_audiometry_active_task_complete', NULL, 'tone_audiometry_active_task', NULL, 86, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'trial1', NULL, 'timed_walk_active_task', 'Timed Walk Active Task', 87, NULL, NULL, 'text', 'Trial 1 Distance', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-TIM_TRIAL1', NULL, 0),
(@project_id, 'turnaround', NULL, 'timed_walk_active_task', NULL, 88, NULL, NULL, 'text', 'Turn Around Distance', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-TIM_TURNAROUND', NULL, 0),
(@project_id, 'trial2', NULL, 'timed_walk_active_task', NULL, 89, NULL, NULL, 'text', 'Trial 2 Distance', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-TIM_TRIAL2', NULL, 0),
(@project_id, 'uuid_9', NULL, 'timed_walk_active_task', NULL, 90, NULL, 'MyCap App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-UUID', NULL, 0),
(@project_id, 'startdate_9', NULL, 'timed_walk_active_task', NULL, 91, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'enddate_9', NULL, 'timed_walk_active_task', NULL, 92, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'scheduledate_9', NULL, 'timed_walk_active_task', NULL, 93, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'status_9', NULL, 'timed_walk_active_task', NULL, 94, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS', NULL, 0),
(@project_id, 'supplementaldata_9', NULL, 'timed_walk_active_task', NULL, 95, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'serializedresult_9', NULL, 'timed_walk_active_task', NULL, 96, NULL, NULL, 'file', 'Serialized Result', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'timed_walk_active_task_complete', NULL, 'timed_walk_active_task', NULL, 97, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'json_3', NULL, 'spatial_span_memory_test_active_task', 'Spatial Span Memory Test Active Task', 98, NULL, NULL, 'textarea', 'JSON', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-SPA', NULL, 0),
(@project_id, 'uuid_8', NULL, 'spatial_span_memory_test_active_task', NULL, 99, NULL, 'MyCap App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-UUID', NULL, 0),
(@project_id, 'startdate_8', NULL, 'spatial_span_memory_test_active_task', NULL, 100, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'enddate_8', NULL, 'spatial_span_memory_test_active_task', NULL, 101, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'scheduledate_8', NULL, 'spatial_span_memory_test_active_task', NULL, 102, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'status_8', NULL, 'spatial_span_memory_test_active_task', NULL, 103, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS', NULL, 0),
(@project_id, 'supplementaldata_8', NULL, 'spatial_span_memory_test_active_task', NULL, 104, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'serializedresult_8', NULL, 'spatial_span_memory_test_active_task', NULL, 105, NULL, NULL, 'file', 'Serialized Result', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'spatial_span_memory_test_active_task_complete', NULL, 'spatial_span_memory_test_active_task', NULL, 106, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'pedometer', NULL, 'fitness_check_active_task', 'Fitness Check Active Task', 107, NULL, NULL, 'file', 'Pedometer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-FIT-WALK-PEDOMETER', NULL, 0),
(@project_id, 'walkacc', NULL, 'fitness_check_active_task', NULL, 108, NULL, NULL, 'file', 'Walk Accelerometer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-FIT-WALK-ACCELEROMETER', NULL, 0),
(@project_id, 'walkdevice', NULL, 'fitness_check_active_task', NULL, 109, NULL, NULL, 'file', 'Walk Device Motion', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-FIT-WALK-DEVICEMOTION', NULL, 0),
(@project_id, 'walkloc', NULL, 'fitness_check_active_task', NULL, 110, NULL, NULL, 'file', 'Walk Location', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-FIT-WALK-LOCATION', NULL, 0),
(@project_id, 'restacc', NULL, 'fitness_check_active_task', NULL, 111, NULL, NULL, 'file', 'Rest Accelerometer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-FIT-REST-ACCELEROMETER', NULL, 0),
(@project_id, 'restdevice', NULL, 'fitness_check_active_task', NULL, 112, NULL, NULL, 'file', 'Rest Device Motion', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-FIT-REST-DEVICEMOTION', NULL, 0),
(@project_id, 'uuid_7', NULL, 'fitness_check_active_task', NULL, 113, NULL, 'MyCap App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-UUID', NULL, 0),
(@project_id, 'startdate_7', NULL, 'fitness_check_active_task', NULL, 114, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'enddate_7', NULL, 'fitness_check_active_task', NULL, 115, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'scheduledate_7', NULL, 'fitness_check_active_task', NULL, 116, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'status_7', NULL, 'fitness_check_active_task', NULL, 117, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS', NULL, 0),
(@project_id, 'supplementaldata_7', NULL, 'fitness_check_active_task', NULL, 118, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'serializedresult_7', NULL, 'fitness_check_active_task', NULL, 119, NULL, NULL, 'file', 'Serialized Result', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'fitness_check_active_task_complete', NULL, 'fitness_check_active_task', NULL, 120, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'json_4', NULL, 'reaction_time_active_task', 'Reaction Time Active Task', 121, NULL, NULL, 'textarea', 'JSON', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-REA', NULL, 0),
(@project_id, 'uuid_6', NULL, 'reaction_time_active_task', NULL, 122, NULL, 'MyCap App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-UUID', NULL, 0),
(@project_id, 'startdate_6', NULL, 'reaction_time_active_task', NULL, 123, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'enddate_6', NULL, 'reaction_time_active_task', NULL, 124, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'scheduledate_6', NULL, 'reaction_time_active_task', NULL, 125, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'status_6', NULL, 'reaction_time_active_task', NULL, 126, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS', NULL, 0),
(@project_id, 'supplementaldata_6', NULL, 'reaction_time_active_task', NULL, 127, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'serializedresult_6', NULL, 'reaction_time_active_task', NULL, 128, NULL, NULL, 'file', 'Serialized Result', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'reaction_time_active_task_complete', NULL, 'reaction_time_active_task', NULL, 129, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'json_5', NULL, 'psat_active_task', 'PSAT Active Task', 130, NULL, NULL, 'textarea', 'JSON', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-PSA', NULL, 0),
(@project_id, 'uuid_5', NULL, 'psat_active_task', NULL, 131, NULL, 'MyCap App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-UUID', NULL, 0),
(@project_id, 'startdate_5', NULL, 'psat_active_task', NULL, 132, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'enddate_5', NULL, 'psat_active_task', NULL, 133, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'scheduledate_5', NULL, 'psat_active_task', NULL, 134, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'status_5', NULL, 'psat_active_task', NULL, 135, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS', NULL, 0),
(@project_id, 'supplementaldata_5', NULL, 'psat_active_task', NULL, 136, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'serializedresult_5', NULL, 'psat_active_task', NULL, 137, NULL, NULL, 'file', 'Serialized Result', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'psat_active_task_complete', NULL, 'psat_active_task', NULL, 138, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'outacc', NULL, 'short_walk_active_task', 'Short Walk Active Task', 139, NULL, NULL, 'file', 'Outbound Accelerometer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-SHO-OUTBOUND-ACCELEROMETER', NULL, 0),
(@project_id, 'outdevice', NULL, 'short_walk_active_task', NULL, 140, NULL, NULL, 'file', 'Outbound Device Motion', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-SHO-OUTBOUND-DEVICEMOTION', NULL, 0),
(@project_id, 'returnacc', NULL, 'short_walk_active_task', NULL, 141, NULL, NULL, 'file', 'Return Accelerometer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-SHO-RETURN-ACCELEROMETER', NULL, 0),
(@project_id, 'returndevice', NULL, 'short_walk_active_task', NULL, 142, NULL, NULL, 'file', 'Return Device Motion', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-SHO-RETURN-DEVICEMOTION', NULL, 0),
(@project_id, 'restacc_2', NULL, 'short_walk_active_task', NULL, 143, NULL, NULL, 'file', 'Rest Accelerometer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-SHO-REST-ACCELEROMETER', NULL, 0),
(@project_id, 'restdevice_2', NULL, 'short_walk_active_task', NULL, 144, NULL, NULL, 'file', 'Rest Device Motion', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-SHO-REST-DEVICEMOTION', NULL, 0),
(@project_id, 'uuid_4', NULL, 'short_walk_active_task', NULL, 145, NULL, 'MyCap App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-UUID', NULL, 0),
(@project_id, 'startdate_4', NULL, 'short_walk_active_task', NULL, 146, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'enddate_4', NULL, 'short_walk_active_task', NULL, 147, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'scheduledate_4', NULL, 'short_walk_active_task', NULL, 148, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'status_4', NULL, 'short_walk_active_task', NULL, 149, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS', NULL, 0),
(@project_id, 'supplementaldata_4', NULL, 'short_walk_active_task', NULL, 150, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'serializedresult_4', NULL, 'short_walk_active_task', NULL, 151, NULL, NULL, 'file', 'Serialized Result', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'short_walk_active_task_complete', NULL, 'short_walk_active_task', NULL, 152, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'audio', NULL, 'audio_active_task', 'Audio Active Task', 153, NULL, NULL, 'file', 'Audio Recording', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-REC-AUD', NULL, 0),
(@project_id, 'uuid_3', NULL, 'audio_active_task', NULL, 154, NULL, 'MyCap App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-UUID', NULL, 0),
(@project_id, 'startdate_3', NULL, 'audio_active_task', NULL, 155, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'enddate_3', NULL, 'audio_active_task', NULL, 156, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'scheduledate_3', NULL, 'audio_active_task', NULL, 157, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'status_3', NULL, 'audio_active_task', NULL, 158, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS', NULL, 0),
(@project_id, 'supplementaldata_3', NULL, 'audio_active_task', NULL, 159, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'serializedresult_3', NULL, 'audio_active_task', NULL, 160, NULL, NULL, 'file', 'Serialized Result', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'audio_active_task_complete', NULL, 'audio_active_task', NULL, 161, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'selfie', NULL, 'selfie_capture', 'Selfie Capture', 162, NULL, NULL, 'file', 'Selfie Capture', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ACTIVE-SEL', NULL, 0),
(@project_id, 'uuid_2', NULL, 'selfie_capture', NULL, 163, NULL, 'MyCap App Fields - Do Not Modify', 'text', 'UUID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-UUID', NULL, 0),
(@project_id, 'startdate_2', NULL, 'selfie_capture', NULL, 164, NULL, NULL, 'text', 'Start Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STARTDATE', NULL, 0),
(@project_id, 'enddate_2', NULL, 'selfie_capture', NULL, 165, NULL, NULL, 'text', 'End Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-ENDDATE', NULL, 0),
(@project_id, 'scheduledate_2', NULL, 'selfie_capture', NULL, 166, NULL, NULL, 'text', 'Schedule Date', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SCHEDULEDATE', NULL, 0),
(@project_id, 'status_2', NULL, 'selfie_capture', NULL, 167, NULL, NULL, 'select', 'Status', '0, Deleted\\n1, Completed\\n2, Incomplete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-STATUS', NULL, 0),
(@project_id, 'supplementaldata_2', NULL, 'selfie_capture', NULL, 168, NULL, NULL, 'textarea', 'Supplemental Data (JSON)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SUPPLEMENTALDATA', NULL, 0),
(@project_id, 'serializedresult_2', NULL, 'selfie_capture', NULL, 169, NULL, NULL, 'file', 'Serialized Result', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@MC-TASK-SERIALIZEDRESULT', NULL, 0),
(@project_id, 'selfie_capture_complete', NULL, 'selfie_capture', NULL, 170, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0);

INSERT INTO `redcap_mycap_aboutpages` (`project_id`, `identifier`, `page_title`, `page_content`, `sub_type`, `image_type`, `system_image_name`, `custom_logo`, `page_order`) VALUES
(@project_id, 'F75458BD-E7EA-4D0B-A6F8-73D212054D68', 'MyCap Sample Project', 'This demo project was created to illustrate how fields appear within the app, various scheduling options, and how to use things like \"About\" pages, \"Contacts\", and \"Links\" to tailor the app to your project.', '.Home', '.System', '.Info', 0, 1),
(@project_id, '6BAC503B-7F55-4467-A222-1E2E2FBD49EA', 'What to expect in the Demo?', 'We have listed the field type and any validations in place at the beginning of each question to help you recognize how REDCap settings appear in the MyCap App for participants.', '.Custom', '.System', '.Info', 0, 2),
(@project_id, '54EB528B-613F-4720-ACFB-8E0AC891930B', 'The Demo Schedule', 'We scheduled tasks using the various scheduling options available in MyCap, including one-time tasks, infinite tasks, repeating and fixed tasks, as well as tasks that stop recurring. Some tasks are scheduled based on the date the participant installs your project and others are based on the baseline date entered. \n\nScroll through the calendar to view what tasks are scheduled in the future.', '.Custom', '.System', '.Info', 0, 3);

INSERT INTO `redcap_mycap_contacts` (`project_id`, `identifier`, `contact_header`, `contact_title`, `phone_number`, `email`, `website`, `additional_info`, `contact_order`) VALUES
(@project_id, '18ADEF10-D83E-41C3-ACF0-6E3D82EFF2F8', 'MyCap Support', NULL, NULL, 'mycap@vumc.org', 'www.projectmycap.org', NULL, 1);

INSERT INTO `redcap_mycap_links` (`project_id`, `identifier`, `link_name`, `link_url`, `link_icon`, `append_project_code`, `append_participant_code`, `link_order`) VALUES
(@project_id, '2A344E51-39AB-48BE-9FBE-6D22DE55DD90', 'MyCap Resources', 'https://projectmycap.org/mycap-resources/', 'ic_library_books', 0, 0, 1),
(@project_id, '4C99C435-657A-467C-9D6A-446437B8D5AD', 'MyCap Use Cases', 'https://projectmycap.org/mycap-use-cases/', 'ic_face', 0, 0, 2);

-- INSERT INTO `redcap_mycap_projectfiles` (`project_code`, `doc_id`, `name`, `category`) VALUES
-- (concat('P-', @pcode), 3, 'ImagePack15.zip', 3);

INSERT INTO `redcap_mycap_projects` (`code`, `hmac_key`, `project_id`, `name`, `allow_new_participants`, `participant_custom_field`, `participant_custom_label`, `participant_allow_condition`, `config`, `baseline_date_field`, `baseline_date_config`, `status`) VALUES
(concat('P-', @pcode), LEFT(sha1(rand()),64), @project_id, @project_title, 1, '', '[name] - [email]', '', '', 'baseline', '{\"enabled\":true,\"instructionStep\":{\"type\":\".TaskInstructionStep\",\"identifier\":\"D5A29702-C4B0-4964-835B-9CEF606C80E8\",\"subType\":\".Custom\",\"title\":\"About the Baseline Date\",\"content\":\"Projects can enable a baseline date if they wish to trigger tasks to be completed after a participant-specific event (e.g., hospital discharge). You can also schedule tasks based on the participant\'s install date. The trigger (install or baseline date) is unique to each task schedule.\\r\\n\\r\\nWhen using a baseline date, if the baseline date is not entered into REDCap when the participant joins your project, they MUST complete the baseline task before any other tasks can be completed, even if the tasks doesn\'t trigger based on the baseline date.\\r\\n\\r\\nThe following questions are an example of how the participant will see the \\\"baseline date\\\" questions in the app.\",\"imageName\":\"\",\"imageType\":\"\",\"sortOrder\":1},\"title\":\"Baseline Date\",\"question1\":\"Were you discharged from the hospital today?\",\"question2\":\"What day were you discharged from the hospital?\"}', 1);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'welcome', 1, 'Welcome', '.Questionnaire', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'morning_checkin', 1, 'Morning Check-in (Daily)', '.Questionnaire', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'weekend_plans', 1, 'Weekend Plans (Recurs on Fridays)', '.Form', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'peak_flow_rate', 1, 'Peak Flow Rate', '.Questionnaire', '.DateLine', '[pea_date]', '[pea_time]', '[pea_value]', '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'tapping_interval_task', 1, 'Tapping Interval Task', '.TwoFingerTappingInterval', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'tower_of_hanoi_active_task', 1, 'Tower of Hanoi', '.TowerOfHanoi', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'tone_audiometry_active_task', 1, 'Tone Audiometry', '.ToneAudiometry', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'timed_walk_active_task', 1, 'Timed Walk', '.TimedWalk', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'spatial_span_memory_test_active_task', 1, 'Spatial Span Memory Test', '.SpatialSpanMemory', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'fitness_check_active_task', 1, 'Fitness Check Active Task', '.FitnessCheck', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'reaction_time_active_task', 1, 'Reaction Time Active Task', '.ReactionTime', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'psat_active_task', 1, 'PSAT Active Task', '.PSAT', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'short_walk_active_task', 1, 'Short Walk Active Task', '.ShortWalk', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'audio_active_task', 1, 'Audio Active Task', '.AudioRecording', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_tasks` (`project_id`, `form_name`, `enabled_for_mycap`, `task_title`, `question_format`, `card_display`, `x_date_field`, `x_time_field`, `y_numeric_field` , `extended_config_json`) VALUES
(@project_id, 'selfie_capture', 1, 'Selfie Capture', '.SelfieCapture', '.Percent', NULL, NULL, NULL, '');
INSERT INTO redcap_mycap_tasks_schedules
(task_id, event_id, allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title, instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency, schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset, schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date)
VALUES
    (LAST_INSERT_ID(), @event_id, 0, 0, 0, 0, '', '', '', '', '',
     '.Infinite', NULL, NULL, NULL, NULL,
     NULL, NULL, NULL, '.Never', NULL, NULL, NULL);

INSERT INTO `redcap_mycap_themes` (`project_id`, `primary_color`, `light_primary_color`, `accent_color`, `dark_primary_color`, `light_bg_color`, `theme_type`, `system_type`) VALUES
(@project_id, '#00A8F2', '#B5E5FB', '#F65722', '#178ACE', '#EEF8FA', '.System', '.Blue');

INSERT INTO `redcap_projects_templates` (`project_id`, `title`, `description`, `enabled`)
	VALUES (@project_id,  @project_title,  'Various examples of MyCap tasks and active tasks for collecting data from participants using the MyCap app on a mobile device.',  '1');