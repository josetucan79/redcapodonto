<?php
namespace Vanderbilt\REDCap\Classes\DTOs;

use System;


/**
 * REDCap configuration variables
 * for a REDCap project
 */
final class REDCapProjectDTO extends DTO {
    /**
     *
     * @var int
     */
    public $project_id;
    
    /**
     *
     * @var string
     */
    public $project_name;
    
    /**
     *
     * @var string
     */
    public $app_title;
    
    /**
     *
     * @var int
     */
    public $status;
    
    /**
     *
     * @var string //datetime
     */
    public $creation_time;
    
    /**
     *
     * @var string //datetime
     */
    public $production_time;
    
    /**
     *
     * @var string //datetime
     */
    public $inactive_time;
    
    /**
     *
     * @var string //datetime
     */
    public $completed_time;
    
    /**
     *
     * @var string
     */
    public $completed_by;
    
    /**
     *
     * @var bool
     */
    public $data_locked;
    
    /**
     *
     * @var string
     */
    public $log_event_table;
    
    /**
     *
     * @var int
     */
    public $created_by;
    
    /**
     *
     * @var int
     */
    public $draft_mode;
    
    /**
     *
     * @var int
     */
    public $surveys_enabled;
    
    /**
     *
     * @var int
     */
    public $repeatforms;
    
    /**
     *
     * @var int
     */
    public $scheduling;
    
    /**
     *
     * @var int
     */
    public $purpose;
    
    /**
     *
     * @var string
     */
    public $purpose_other;
    
    /**
     *
     * @var int
     */
    public $show_which_records;
    
    /**
     *
     * @var string
     */
    public $__SALT__;
    
    /**
     *
     * @var int
     */
    public $count_project;
    
    /**
     *
     * @var string
     */
    public $investigators;
    
    /**
     *
     * @var string
     */
    public $project_note;
    
    /**
     *
     * @var int
     */
    public $online_offline;
    
    /**
     *
     * @var string
     */
    public $auth_meth_global;
    
    /**
     *
     * @var int
     */
    public $double_data_entry;
    
    /**
     *
     * @var string
     */
    public $project_language;
    
    /**
     *
     * @var string // enum
     */
    public $project_encoding;
    
    /**
     *
     * @var string
     */
    public $is_child_of;
    
    /**
     *
     * @var int
     */
    public $date_shift_max;
    
    /**
     *
     * @var string
     */
    public $institution;
    
    /**
     *
     * @var string
     */
    public $site_org_type;
    
    /**
     *
     * @var string
     */
    public $grant_cite;
    
    /**
     *
     * @var string
     */
    public $project_contact_name;
    
    /**
     *
     * @var string
     */
    public $project_contact_email;
    
    /**
     *
     * @var string
     */
    public $headerlogo;
    
    /**
     *
     * @var int
     */
    public $auto_inc_set;
    
    /**
     *
     * @var string
     */
    public $custom_data_entry_note;
    
    /**
     *
     * @var string
     */
    public $custom_index_page_note;
    
    /**
     *
     * @var string
     */
    public $order_id_by;
    
    /**
     *
     * @var string
     */
    public $custom_reports;
    
    /**
     *
     * @var string
     */
    public $report_builder;
    
    /**
     *
     * @var int
     */
    public $disable_data_entry;
    
    /**
     *
     * @var string
     */
    public $google_translate_default;
    
    /**
     *
     * @var int
     */
    public $require_change_reason;
    
    /**
     *
     * @var int
     */
    public $dts_enabled;
    
    /**
     *
     * @var string
     */
    public $project_pi_firstname;
    
    /**
     *
     * @var string
     */
    public $project_pi_mi;
    
    /**
     *
     * @var string
     */
    public $project_pi_lastname;
    
    /**
     *
     * @var string
     */
    public $project_pi_email;
    
    /**
     *
     * @var string
     */
    public $project_pi_alias;
    
    /**
     *
     * @var string
     */
    public $project_pi_username;
    
    /**
     *
     * @var int
     */
    public $project_pi_pub_exclude;
    
    /**
     *
     * @var string
     */
    public $project_pub_matching_institution;
    
    /**
     *
     * @var string
     */
    public $project_irb_number;
    
    /**
     *
     * @var string
     */
    public $project_grant_number;
    
    /**
     *
     * @var int
     */
    public $history_widget_enabled;
    
    /**
     *
     * @var string
     */
    public $secondary_pk;
    
    /**
     *
     * @var bool
     */
    public $secondary_pk_display_value;
    
    /**
     *
     * @var bool
     */
    public $secondary_pk_display_label;
    
    /**
     *
     * @var string
     */
    public $custom_record_label;
    
    /**
     *
     * @var int
     */
    public $display_project_logo_institution;
    
    /**
     *
     * @var int
     */
    public $imported_from_rs;
    
    /**
     *
     * @var int
     */
    public $display_today_now_button;
    
    /**
     *
     * @var int
     */
    public $auto_variable_naming;
    
    /**
     *
     * @var int
     */
    public $randomization;
    
    /**
     *
     * @var int
     */
    public $enable_participant_identifiers;
    
    /**
     *
     * @var string
     */
    public $survey_email_participant_field;
    
    /**
     *
     * @var string
     */
    public $survey_phone_participant_field;
    
    /**
     *
     * @var string
     */
    public $data_entry_trigger_url;
    
    /**
     *
     * @var int
     */
    public $template_id;
    
    /**
     *
     * @var string //datetime
     */
    public $date_deleted;
    
    /**
     *
     * @var int
     */
    public $data_resolution_enabled;
    
    /**
     *
     * @var int
     */
    public $field_comment_edit_delete;
    
    /**
     *
     * @var int
     */
    public $realtime_webservice_enabled;
    
    /**
     *
     * @var string // enum
     */
    public $realtime_webservice_type;
    
    /**
     *
     * @var float
     */
    public $realtime_webservice_offset_days;
    
    /**
     *
     * @var string // enum
     */
    public $realtime_webservice_offset_plusminus;
    
    /**
     *
     * @var string //datetime
     */
    public $last_logged_event;
    
    /**
     *
     * @var int
     */
    public $edoc_upload_max;
    
    /**
     *
     * @var int
     */
    public $file_attachment_upload_max;
    
    /**
     *
     * @var string
     */
    public $survey_queue_custom_text;
    
    /**
     *
     * @var bool
     */
    public $survey_queue_hide;
    
    /**
     *
     * @var int
     */
    public $survey_auth_enabled;
    
    /**
     *
     * @var string
     */
    public $survey_auth_field1;
    
    /**
     *
     * @var int
     */
    public $survey_auth_event_id1;
    
    /**
     *
     * @var string
     */
    public $survey_auth_field2;
    
    /**
     *
     * @var int
     */
    public $survey_auth_event_id2;
    
    /**
     *
     * @var string
     */
    public $survey_auth_field3;
    
    /**
     *
     * @var int
     */
    public $survey_auth_event_id3;
    
    /**
     *
     * @var string // enum
     */
    public $survey_auth_min_fields;
    
    /**
     *
     * @var int
     */
    public $survey_auth_apply_all_surveys;
    
    /**
     *
     * @var string
     */
    public $survey_auth_custom_message;
    
    /**
     *
     * @var int
     */
    public $survey_auth_fail_limit;
    
    /**
     *
     * @var int
     */
    public $survey_auth_fail_window;
    
    /**
     *
     * @var int
     */
    public $twilio_enabled;
    
    /**
     *
     * @var string // enum
     */
    public $twilio_modules_enabled;
    
    /**
     *
     * @var bool
     */
    public $twilio_hide_in_project;
    
    /**
     *
     * @var string
     */
    public $twilio_account_sid;
    
    /**
     *
     * @var string
     */
    public $twilio_auth_token;
    
    /**
     *
     * @var bigint
     */
    public $twilio_from_number;
    
    /**
     *
     * @var string
     */
    public $twilio_voice_language;
    
    /**
     *
     * @var bool
     */
    public $twilio_option_voice_initiate;
    
    /**
     *
     * @var bool
     */
    public $twilio_option_sms_initiate;
    
    /**
     *
     * @var bool
     */
    public $twilio_option_sms_invite_make_call;
    
    /**
     *
     * @var bool
     */
    public $twilio_option_sms_invite_receive_call;
    
    /**
     *
     * @var bool
     */
    public $twilio_option_sms_invite_web;
    
    /**
     *
     * @var string // enum
     */
    public $twilio_default_delivery_preference;
    
    /**
     *
     * @var string //datetime
     */
    public $twilio_request_inspector_checked;
    
    /**
     *
     * @var int
     */
    public $twilio_request_inspector_enabled;
    
    /**
     *
     * @var int
     */
    public $twilio_append_response_instructions;
    
    /**
     *
     * @var string // enum
     */
    public $twilio_multiple_sms_behavior;
    
    /**
     *
     * @var string
     */
    public $twilio_delivery_preference_field_map;
    
    /**
     *
     * @var bool
     */
    public $two_factor_exempt_project;
    
    /**
     *
     * @var bool
     */
    public $two_factor_force_project;
    
    /**
     *
     * @var bool
     */
    public $disable_autocalcs;
    
    /**
     *
     * @var string
     */
    public $custom_public_survey_links;
    
    /**
     *
     * @var string
     */
    public $pdf_custom_header_text;
    
    /**
     *
     * @var bool
     */
    public $pdf_show_logo_url;
    
    /**
     *
     * @var bool
     */
    public $pdf_hide_secondary_field;
    
    /**
     *
     * @var bool
     */
    public $pdf_hide_record_id;
    
    /**
     *
     * @var bool
     */
    public $shared_library_enabled;
    
    /**
     *
     * @var bool
     */
    public $allow_delete_record_from_log;
    
    /**
     *
     * @var int
     */
    public $delete_file_repository_export_files;
    
    /**
     *
     * @var string
     */
    public $custom_project_footer_text;
    
    /**
     *
     * @var string
     */
    public $custom_project_footer_text_link;
    
    /**
     *
     * @var bool
     */
    public $google_recaptcha_enabled;
    
    /**
     *
     * @var bool
     */
    public $datamart_allow_repeat_revision;
    
    /**
     *
     * @var bool
     */
    public $datamart_allow_create_revision;
    
    /**
     *
     * @var bool
     */
    public $datamart_enabled;
    
    /**
     *
     * @var bool
     */
    public $break_the_glass_enabled;
    
    /**
     *
     * @var bool
     */
    public $datamart_cron_enabled;
    
    /**
     *
     * @var string //datetime
     */
    public $datamart_cron_end_date;
    
    /**
     *
     * @var bool
     */
    public $fhir_include_email_address_project;
    
    /**
     *
     * @var bool
     */
    public $file_upload_vault_enabled;
    
    /**
     *
     * @var bool
     */
    public $file_upload_versioning_enabled;
    
    /**
     *
     * @var string
     */
    public $missing_data_codes;
    
    /**
     *
     * @var bool
     */
    public $record_locking_pdf_vault_enabled;
    
    /**
     *
     * @var string
     */
    public $record_locking_pdf_vault_custom_text;
    
    /**
     *
     * @var bool
     */
    public $fhir_cdp_auto_adjudication_enabled;
    
    /**
     *
     * @var bool
     */
    public $fhir_cdp_auto_adjudication_cronjob_enabled;
    
    /**
     *
     * @var int
     */
    public $project_dashboard_min_data_points;
    
    /**
     *
     * @var bool
     */
    public $bypass_branching_erase_field_prompt;
    
    /**
     *
     * @var bool
     */
    public $protected_email_mode;
    
    /**
     *
     * @var string
     */
    public $protected_email_mode_custom_text;
    
    /**
     *
     * @var string // enum
     */
    public $protected_email_mode_trigger;
    
    /**
     *
     * @var int
     */
    public $protected_email_mode_logo;
    
    /**
     *
     * @var bool
     */
    public $hide_filled_forms;
    
    /**
     *
     * @var bool
     */
    public $hide_disabled_forms;
    
    /**
     *
     * @var bool
     */
    public $form_activation_survey_autocontinue;
    
    /**
     *
     * @var bool
     */
    public $sendgrid_enabled;
    
    /**
     *
     * @var string
     */
    public $sendgrid_project_api_key;

    /**
     * get a set of REDCap configuration directly from the database
     *
     * @return REDCapProjectDTO
     */
    public static function fromProjectID($projectID) {
        $queryString = "SELECT * FROM redcap_projects WHERE project_id=?";
        $result = db_query($queryString, [$projectID]);
        $data = [];
        if($row = db_fetch_assoc($result)) $data = $row;
        return REDCapProjectDTO::fromArray($data);
    }
}