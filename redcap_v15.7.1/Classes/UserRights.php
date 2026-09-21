<?php

/**
 * UserRights Class
 * Contains methods used with regard to user privileges
 */
class UserRights
{
	// Map pages to user_rights table values to determine rights for a given page (e.g., PAGE=>field from user_rights table).
	// Also maps Route from query string (&route=Class:Method), if exists.
	public $page_rights = array(
		// Routes that need to be allowlisted but are not mappable to a $user_rights element.
		// Their format will be "Class/Method"=>"" (the value should stay as an empty string).
		"ProjectDashController:view"=>"",
		"ProjectDashController:viewpanel"=>"",
		"ProjectDashController:colorblind"=>"",
		"DataEntryController:saveShowInstrumentsToggle"=>"",
		"DataEntryController:renderInstancesTable"=>"",
		"DataEntryController:assignRecordToDag"=>"",
		"DataEntryController:passwordVerify"=>"",
		"DataEntryController:openSurveyValuesChanged"=>"",
		"DataEntryController:getResponseContributors"=>"",
		"DataEntryController:buildRecordListCache"=>"",
		"DataEntryController:clearRecordListCache"=>"",
		"DataEntryController:getRepeatInstances"=>"",
		"DataEntryController:storeRepeatInstancesFilters"=>"",
		"DataEntryController:storeRepeatInstancesPageLength"=>"",
		"UserRightsController:impersonateUser"=>"",
		"UserRightsController:showHideSuspendedUsers"=>"",
		"PdfController:index"=>"",
		"DataAccessGroupsController:switchDag"=>"",
		"DataAccessGroupsController:downloadDag"=>"",
		"DataAccessGroupsController:downloadUserDag"=>"",
		"DataAccessGroupsController:uploadDag"=>"",
		"DataAccessGroupsController:uploadUserDag"=>"",
		"DesignController:fieldUsedInMultiplePlaces"=>"",
		"UserController:downloadCurrentUsersList"=>"",
		"BulkRecordDeleteController:fetchRecords"=>"",
		"BulkRecordDeleteController:checkRecordsExist"=>"",
		"BulkRecordDeleteController:renderFormEventList"=>"",
		"BulkRecordDeleteController:index"=>"",
		"BulkRecordDeleteController:loadBackgroundDeletionsTable"=>"",
		"BulkRecordDeleteController:cancelBackgroundDelete"=>"",
		"BulkRecordDeleteController:viewBackgroundDeleteDetails"=>"",
		"BulkRecordDeleteController:downloadBackgroundErrors"=>"",

		// Data Entry
		"DataEntryController:renameRecord"=>"record_rename",
		"DataEntryController:deleteRecord"=>"record_delete",
		"DataEntryController:deleteEventInstance"=>"record_delete",
		"DataEntryController:recordExists"=>"",

		// Export & Reports
		"DataExport/data_export_tool.php"=>"data_export_tool",
		"DataExport/data_export_csv.php"=>"data_export_tool",
		"DataExport/file_export_zip.php"=>"data_export_tool",
		"DataExport/data_export_ajax.php"=>"data_export_tool",
		"DataExport/report_order_ajax.php"=>"reports",
		"DataExport/report_edit_ajax.php"=>"reports",
		"DataExport/report_delete_ajax.php"=>"reports",
		"DataExport/report_user_access_list.php"=>"reports",
		"DataExport/report_copy_ajax.php"=>"reports",
		"DataExport/report_filter_ajax.php"=>"reports",
		"DataExport/report_public_enable.php"=>"reports",
		"ReportController:reportFoldersDialog"=>"reports",
		"ReportController:reportFolderCreate"=>"reports",
		"ReportController:reportFolderEdit"=>"reports",
		"ReportController:reportFolderDelete"=>"reports",
		"ReportController:reportFolderDisplayTable"=>"reports",
		"ReportController:reportFolderDisplayTableAssign"=>"reports",
		"ReportController:reportFolderDisplayDropdown"=>"reports",
		"ReportController:reportFolderAssign"=>"reports",
		"ReportController:reportFolderResort"=>"reports",
		"ReportController:reportSearch"=>"",

		// Import
		"DataImportController:index"=>"data_import_tool",
		"DataImportController:downloadTemplate"=>"data_import_tool",
		"DataImportController:loadBackgroundImportsTable"=>"data_import_tool",
		"DataImportController:viewBackgroundImportDetails"=>"data_import_tool",
		"DataImportController:fieldPreCheck"=>"data_import_tool",
		"DataImportController:downloadBackgroundErrors"=>"data_import_tool",
		"DataImportController:downloadBackgroundErrorData"=>"data_import_tool",
		"DataImportController:cancelBackgroundImport"=>"data_import_tool",

		// Data Comparison Tool
		"DataComparisonController:index"=>"data_comparison_tool",

		// Logging
		"Logging/index.php"=>"data_logging",
		"Logging/csv_export.php"=>"data_logging",

		// File Repository
		"FileRepositoryController:index"=>"file_repository",
		"FileRepositoryController:upload"=>"file_repository",
		"FileRepositoryController:move"=>"file_repository",
		"FileRepositoryController:getFolderDropdown"=>"file_repository",
		"FileRepositoryController:downloadMultiple"=>"file_repository",
		"FileRepositoryController:delete"=>"file_repository",
		"FileRepositoryController:deleteMultiple"=>"file_repository",
		"FileRepositoryController:deleteFolder"=>"file_repository",
		"FileRepositoryController:deleteNow"=>"file_repository",
		"FileRepositoryController:rename"=>"file_repository",
		"FileRepositoryController:renameFolder"=>"file_repository",
		"FileRepositoryController:createFolder"=>"file_repository",
		"FileRepositoryController:getFileList"=>"file_repository",
		"FileRepositoryController:getCurrentUsage"=>"file_repository",
		"FileRepositoryController:restore"=>"file_repository",
		"FileRepositoryController:share"=>"file_repository",
		"FileRepositoryController:editComment"=>"file_repository",
		"FileRepositoryController:getBreadcrumbs"=>"",
		"FileRepositoryController:download"=>"",

		// User Rights
		"UserRights/index.php"=>"user_rights",
		"UserRights/search_user.php"=>"user_rights",
		"UserRights/assign_user.php"=>"user_rights",
		"UserRights/edit_user.php"=>"user_rights",
		"UserRights/user_account_exists.php"=>"user_rights",
		"UserRights/set_user_expiration.php"=>"user_rights",
		"UserRights/import_export_users.php"=>"user_rights",
		"UserRights/import_export_roles.php"=>"user_rights",
		"UserRights/get_user_dag_role.php"=>"user_rights",
		"UserRightsController:displayRightsRolesTable"=>"user_rights",

        // Email Logging
		"EmailLoggingController:index"=>"email_logging",
		"EmailLoggingController:search"=>"email_logging",
		"EmailLoggingController:view"=>"email_logging",
		"EmailLoggingController:optin"=>"email_logging",
		"EmailLoggingController:resend"=>"email_logging",

		// DAGs
		"DataAccessGroupsController:index"=>"data_access_groups",
		"DataAccessGroupsController:ajax"=>"data_access_groups",
		"DataAccessGroupsController:saveUserDAG"=>"data_access_groups",
		"DataAccessGroupsController:getDagSwitcherTable"=>"data_access_groups",

		// Graphical & Stats
		"Graphical/index.php"=>"graphical",
		"Graphical/pdf.php"=>"graphical",
		"DataExport/plot_chart.php"=>"graphical",
		"DataExport/stats_highlowmiss.php"=>"graphical",
		"Graphical/image_base64_download.php"=>"graphical",

		// Calendar
		"Calendar/index.php"=>"calendar",
		"Calendar/calendar_popup.php"=>"calendar",
		"Calendar/calendar_popup_ajax.php"=>"calendar",
		"DataEntryController:renderUpcomingCalEvents"=>"calendar",
		"Calendar/scheduling.php"=>"calendar",
		"Calendar/scheduling_ajax.php"=>"calendar",

		// Locking records
		"Locking/locking_customization.php"=>"lock_record_customize",
		"Locking/esign_locking_management.php"=>"lock_record",

		// DTS
		"DtsController:adjudication"=>"dts",

		// Invite survey participants
		"Surveys/add_participants.php"=>"participants",
		"Surveys/invite_participants.php"=>"participants",
		"Surveys/delete_participant.php"=>"participants",
		"Surveys/edit_participant.php"=>"participants",
		"Surveys/participant_export.php"=>"participants",
		"Surveys/shorturl.php"=>"participants",
		"Surveys/shorturl_custom.php"=>"participants",
		"Surveys/participant_list.php"=>"participants",
		"Surveys/participant_list_enable.php"=>"participants",
		"Surveys/view_sent_email.php"=>"participants",
		"Surveys/get_access_code.php"=>"participants",
		"Surveys/invite_participant_popup.php"=>"participants",
		"Surveys/invitation_log_export.php"=>"participants",
		"SurveyController:changeLinkExpiration"=>"participants",
		"SurveyController:renderUpcomingScheduledInvites"=>"participants",
		"SurveyController:enableCaptcha"=>"participants",

		// Data Quality
		"DataQuality/execute_ajax.php"=>"data_quality_execute",
		"DataQuality/edit_rule_ajax.php"=>"data_quality_design",

		// Randomization
		"Randomization/index.php"=>"random_setup",
		"Randomization/upload_allocation_file.php"=>"random_setup",
		"Randomization/download_allocation_file.php"=>"random_setup",
		"Randomization/download_allocation_file_template.php"=>"random_setup",
		"Randomization/check_randomization_field_data.php"=>"random_setup",
		"Randomization/delete_allocation_file.php"=>"random_setup",
		"Randomization/save_randomization_setup.php"=>"random_setup",
		"Randomization/dashboard.php"=>"random_dashboard",
		"Randomization/dashboard_all.php"=>"random_dashboard",
		"Randomization/randomize_record.php"=>"random_perform",

		// Multi-language
		"MultiLanguageController:projectSetup"=>"design",
		"MultiLanguageController:ajax"=>"",

		// Setup & Design
		"ProjectGeneral/copy_project_form.php"=>"design",
		"ProjectGeneral/change_project_status.php"=>"design",
		"DesignController:renderDiffForFormCustomCSS"=>"design",
		"DesignController:saveFormCustomCSS"=>"design",
		"Design/add_field_via_fieldbank.php"=>"design",
		"Design/arm_upload.php"=>"design",
		"Design/arm_download.php"=>"design",
		"Design/branching_logic_builder.php"=>"design",
		"Design/calculation_equation_validate.php"=>"design",
		"Design/check_field_disable.php"=>"design",
		"Design/check_field_name.php"=>"design",
		"Design/check_matrix_group_name.php"=>"design",
		"Design/convert_to_matrix.php"=>"design",
		"Design/copy_field.php"=>"design",
		"Design/copy_instrument.php"=>"design",
		"Design/copy_section_header.php"=>"design",
		"Design/create_form.php"=>"design",
		"Design/data_dictionary_download.php"=>"design",
		"Design/data_dictionary_snapshot.php"=>"design",
		"Design/data_dictionary_upload.php"=>"design",
		"Design/define_events_ajax.php"=>"design",
		"Design/define_events.php"=>"design",
		"Design/delete_field_check_calcbranch.php"=>"design",
		"Design/delete_field.php"=>"design",
		"Design/delete_form.php"=>"design",
		"Design/delete_matrix.php"=>"design",
		"Design/designate_forms_ajax.php"=>"design",
		"Design/designate_forms.php"=>"design",
		// Design/draft_mode_approve.php is superuser only
		"Design/draft_mode_cancel.php"=>"design",
		"Design/draft_mode_enter.php"=>"design",
		// Design/draft_mode_notice.php gets included; cannot be called directly
		"Design/draft_mode_notified.php"=>"design",
		// Design/draft_mode_reject.php is superuser only
		// Design/draft_mode_reset.php is superuser only
		"Design/draft_mode_review.php"=>"design",
		"Design/edit_field_prefill.php"=>"design",
		"Design/edit_field.php"=>"design",
		"Design/edit_matrix_prefill.php"=>"design",
		"Design/edit_matrix.php"=>"design",
		"Design/event_download.php"=>"design",
		"Design/event_upload.php"=>"design",
		"Design/existing_choices.php"=>"design",
		"Design/field_bank_search.php"=>"design",
		// Design/file_attachment_upload.php is used in multiple context by various types of users
		"Design/form_display_logic_setup.php"=>"design",
		"Design/get_bioportal_explain_popup.php"=>"design",
		"Design/get_bioportal_token_popup.php"=>"design",
		"Design/get_events_auto_invites_for_form.php"=>"design",
		"Design/instrument_event_mapping_download.php"=>"design",
		"Design/instrument_event_mapping_upload.php"=>"design",
		// Design/logic_calc_test_record.php seems to be only relevant for users with design rights?
		// Design/logic_field_suggest.php is used in multiple context by various types of users
		// Design/logic_test_record.php is used in multiple context by various types of users
		// Design/logic_validate.php is used in multiple context by various types of users
		"Design/matrix_examples.php"=>"design",
		"Design/move_field.php"=>"design",
		"Design/mycap_task_issues.php"=>"design",
		"Design/online_designer_render_fields.php"=>"design",
		"Design/online_designer.php"=>"design",
        "Design/quick_update_fields.php"=>"design",
        "Design/rename_form.php"=>"design",
        // inline descriptive popups
        "Design/descriptive_popups.php"=>"design",
        "DescriptivePopupsController:deleteDataAllPopups"=>"design",
        "DescriptivePopupsController:deletePopup"=>"design",
        "DescriptivePopupsController:getPopupSummary"=>"design",
		// Design/repeating_asi_explain.php might be called by users without design rights
		"Design/set_auto_var_naming_ajax.php"=>"design",
		"Design/set_draft_preview_enabled.php"=>"design",
		"Design/set_survey_title_as_form_name.php"=>"design",
		"Design/set_task_title_as_form_name.php"=>"design",
		// Design/smart_variable_explain.php is used in multiple context by various types of users
		"Design/sql_field_explanation.php"=>"design",
		"Design/stop_actions.php"=>"design",
		"Design/survey_login_setup.php"=>"design",
		"Design/update_field_order.php"=>"design",
		"Design/update_form_order.php"=>"design",
		"Design/update_pk_popup.php"=>"design",
		"Design/zip_instrument_download.php"=>"design",
		"Design/zip_instrument_upload.php"=>"design",

		"RepeatInstanceController:renderSetup"=>"design",
		"RepeatInstanceController:saveSetup"=>"design",
		"ProjectGeneral/edit_project_settings.php"=>"design",
		"ProjectGeneral/modify_project_setting_ajax.php"=>"design",
		"ProjectGeneral/delete_project.php"=>"design",
		"ProjectGeneral/erase_project_data.php"=>"design",
		"ProjectSetup/other_functionality.php"=>"design",
		"ProjectSetup/project_revision_history.php"=>"design",
		"IdentifierCheckController:index"=>"design",
		"SharedLibrary/index.php"=>"design",
		"SharedLibrary/receiver.php"=>"design",
		"ProjectSetup/checkmark_ajax.php"=>"design",
		"ProjectSetup/export_project_odm.php"=>"design",
		"Surveys/edit_info.php"=>"design",
		"Surveys/create_survey.php"=>"design",
		"Surveys/survey_online.php"=>"design",
		"Surveys/delete_survey.php"=>"design",
		"ExternalLinks/index.php"=>"design",
		"ExternalLinks/edit_resource_ajax.php"=>"design",
		"ExternalLinks/save_resource_users_ajax.php"=>"design",
		"Surveys/automated_invitations_setup.php"=>"design",
		"Surveys/survey_queue_setup.php"=>"design",
		"Surveys/twilio_check_request_inspector.php"=>"design",
		"Surveys/theme_view.php"=>"design",
		"Surveys/theme_save.php"=>"design",
		"Surveys/theme_manage.php"=>"design",
		"Surveys/copy_design_settings.php"=>"design",
		"RecordDashboardController:save"=>"design",
		"RecordDashboardController:delete"=>"design",
		"SurveyController:reevalAutoInvites"=>"design",
		"SurveyController:displayAutoInviteSurveyEventCheckboxList"=>"design",
		"ProjectDashController:index"=>"design",
		"ProjectDashController:access"=>"design",
		"ProjectDashController:save"=>"design",
		"ProjectDashController:copy"=>"design",
		"ProjectDashController:dashFolderAssign"=>"design",
		"ProjectDashController:dashFolderCreate"=>"design",
		"ProjectDashController:dashFolderDelete"=>"design",
		"ProjectDashController:dashFolderEdit"=>"design",
		"ProjectDashController:dashFolderDisplayDropdown"=>"design",
		"ProjectDashController:dashFolderDisplayTable"=>"design",
		"ProjectDashController:dashFolderDisplayTableAssign"=>"design",
		"ProjectDashController:dashFoldersDialog"=>"design",
		"ProjectDashController:dashFolderResort"=>"design",
		"ProjectDashController:dashSearch"=>"",
		"ProjectDashController:delete"=>"design",
		"ProjectDashController:reorder"=>"design",
		"ProjectDashController:shorturl"=>"design",
		"ProjectDashController:remove_shorturl"=>"design",
		"ProjectDashController:reset_cache"=>"design",
		"ProjectDashController:request_public_enable"=>"design",
		"ProjectDashController:public_enable"=>"design",
		"ProjectDashController:get_qr_code_png"=>"design",
		"ProjectDashController:get_qr_code_svg"=>"design",
		"Design/form_display_logic_setup.php"=>"design",
		"EconsentController:index"=>"design",
		"EconsentController:loadTable"=>"design",
		"EconsentController:editSetup"=>"design",
		"EconsentController:saveSetup"=>"design",
		"EconsentController:surveySelectDialog"=>"design",
        "EconsentController:addConsentForm"=>"design",
        "EconsentController:viewConsentFormVersions"=>"design",
        "EconsentController:uploadConsentForm"=>"design",
        "EconsentController:deleteConsentForm"=>"design",
        "EconsentController:saveConsentForm"=>"design",
        "EconsentController:disable"=>"design",
        "EconsentController:reenable"=>"design",
        "EconsentController:removeConsentForm"=>"design",
        "EconsentController:viewConsentForm"=>"design",
        "PdfSnapshotController:disable"=>"design",
        "PdfSnapshotController:copy"=>"design",
        "PdfSnapshotController:reenable"=>"design",
		"PdfSnapshotController:index"=>"design",
		"PdfSnapshotController:editSetup"=>"design",
		"PdfSnapshotController:saveSetup"=>"design",
		"PdfSnapshotController:loadTable"=>"design",
		"PdfSnapshotController:triggerSnapshotDialog"=>"",
		"PdfSnapshotController:triggerSnapshot"=>"",
		// Alerts & Notifications
		"AlertsController:setup"=>"alerts",
		"AlertsController:getEdocName"=>"alerts",
		"AlertsController:saveAlert"=>"alerts",
		"AlertsController:downloadAttachment"=>"alerts",
		"AlertsController:saveAttachment"=>"alerts",
		"AlertsController:deleteAttachment"=>"alerts",
		"AlertsController:copyAlert"=>"alerts",
		"AlertsController:deleteAlert"=>"alerts",
		"AlertsController:deleteAlertPermanent"=>"alerts",
		"AlertsController:displayRepeatingFormTextboxQueue"=>"alerts",
		"AlertsController:deleteQueuedRecord"=>"alerts",
		"AlertsController:previewAlertMessage"=>"alerts",
		"AlertsController:previewAlertMessageByRecordDialog"=>"alerts",
		"AlertsController:previewAlertMessageByRecord"=>"alerts",
		"AlertsController:reevalAlerts"=>"alerts",
		"AlertsController:moveAlert"=>"alerts",
		"AlertsController:downloadAlerts"=>"alerts",
		"AlertsController:uploadAlerts"=>"alerts",
		"AlertsController:downloadLogs"=>"alerts",
		"AlertsController:uploadDownloadHelp"=>"alerts",
		"AlertsController:getSendgridData"=>"alerts",

		// Dynamic Data Pull (DDP)
		"DynamicDataPull/setup.php"=>"realtime_webservice_mapping",
		"DynamicDataPull/fetch.php"=>"realtime_webservice_adjudicate",
		"DynamicDataPull/save.php"=>"realtime_webservice_adjudicate",
		"DynamicDataPull/exclude.php"=>"realtime_webservice_adjudicate",
		"DynamicDataPull/purge_cache.php"=>"design",

		// DataMart
		"DataMartController:revisions"=>"",
		"DataMartController:getUser"=>"",
		"DataMartController:getSettings"=>"",
		"DataMartController:addRevision"=>"",
		"DataMartController:runRevision"=>"",
		"DataMartController:scheduleRevision"=>"",
		"DataMartController:getRevisionProgress"=>"",
		"DataMartController:exportRevision"=>"",
		"DataMartController:importRevision"=>"",
		"DataMartController:sourceFields"=>"",
		"DataMartController:approveRevision"=>"design",
		"DataMartController:deleteRevision"=>"design",
		"DataMartController:index"=>"",
		"DataMartController:searchMrns"=>"",
		"DataMartController:checkDesign"=>"",
		"DataMartController:fixDesign"=>"",
		"DataMartController:notifyFix"=>"",
		"DataMartController:executeFixCommand"=>"",

		// FHIR Mapping Helper
		"FhirMappingHelperController:index"=>"",
		"FhirMappingHelperController:getSettings"=>"",
		"FhirMappingHelperController:getResource"=>"",
		"FhirMappingHelperController:getResources"=>"",
		"FhirMappingHelperController:getFhirRequest"=>"",

		// Mobile App page
		"MobileApp/index.php"=>"mobile_app",
		"MobileApp/email_self.php"=>"mobile_app",

		// MyCap Mobile App page
		"MyCap/create_task.php"=>"design",
		"MyCap/edit_task.php"=>"design",
		"MyCapMobileApp/update.php"=>"design",
		"MyCapMobileApp/mycap_additional_setup.php"=>"design",
		"MyCap/tasks_list.php"=>"design",
		"MyCapMobileApp/custom_setup.php"=>"design",

		// Break the glass
		"GlassBreakerController:index"=>"",
		"GlassBreakerController:initialize"=>"",
		"GlassBreakerController:breakTheGlass"=>"",
		"GlassBreakerController:getProtectedMrnList"=>"",
		"GlassBreakerController:clearProtectedMrnList"=>"",
		"GlassBreakerController:removeMrn"=>"",
		"GlassBreakerController:getSettings"=>"",

		// Queue
		"QueueController:getMessages"=>"",

		// Clinical Data Interoperability Services
		"CdisController:getCdpAutoAdjudicationLogs"=>"",

		// Clinical Data Pull Mapping
		"CdpController:getSettings"=>"",
		"CdpController:setSettings"=>"design",
		"CdpController:setMappings"=>"design",
		"CdpController:importMappings"=>"design",
		"CdpController:exportMappings"=>"design",
		"CdpController:download"=>"design",
		"CdpController:getPreviewData"=>"design",
		"CdpController:getDdpRecordsDataStats"=>"design",
		"CdpController:processCachedData"=>"design",
		"CdpController:processField"=>"design",

		// FHIR Patient Portal (EHR launch)
		// "FhirPatientPortalController:addProject"=>"design",
		// "FhirPatientPortalController:removeProject"=>"design",
		// "FhirPatientPortalController:cleanSessionData"=>"design",
		"FhirPatientPortalController:createPatientRecord"=>"design",

		// Parcels
		"ParcelController:index" => "",
		"ParcelController:settings" => "",
		"ParcelController:show" => "",
		"ParcelController:list" => "",
		"ParcelController:get" => "",
		"ParcelController:command" => "",
		// Rewards
		"RewardsController:getRewardOption" => "",
		"RewardsController:listRewardOptions" => "",
		"RewardsController:createRewardOption" => "",
		"RewardsController:updateRewardOption" => "",
		"RewardsController:deleteRewardOption" => "",
		"RewardsController:restoreRewardOption" => "",
		"RewardsController:listProducts" => "",
		"RewardsController:getCatalog" => "",
		"RewardsController:getChoiceProducts" => "",
		"RewardsController:getChoiceProduct" => "",
		"RewardsController:listRecords" => "",
		"RewardsController:performAction" => "",
		"RewardsController:scheduleAction" => "",
		"RewardsController:sendOrderEmail" => "",
		"RewardsController:checkBalance" => "",
		"RewardsController:getSettings" => "",
		"RewardsController:clearCache" => "",
		"RewardsController:getEmailPreview" => "",
		"RewardsController:recalculateRecordStatus" => "",

		// Take A Tour
		"TakeATourController:load" => "",

		// CDIS Token controller
		"CdisTokenController:getSettings" => "",
		"CdisTokenController:getRules" => "",
		"CdisTokenController:saveChanges" => "",
		"CdisTokenController:addRule" => "",
		"CdisTokenController:updateRule" => "",
		"CdisTokenController:deleteRule" => "",
	);

	// Double Data Entry (only): DDE Person will have no rights to certain pages that display data.
	// List the restricted pages in an array
	private $pagesRestrictedDDE = array(
		"Calendar/index.php", "DataExport/data_export_tool.php", "DataImportController:index",
		"DataComparisonController:index", "Logging/index.php", "FileRepositoryController:index", "DataQuality/field_comment_log.php",
		"Locking/esign_locking_management.php", "Graphical/index.php", "DataQuality/index.php", "Reports/report.php"
	);

	// Constructor
	public function __construct($applyProjectPrivileges=false)
	{
		extract($GLOBALS);
		global $lang, $user_rights, $double_data_entry, $isAjax;
		// Automatically apply project-level user privileges
		if (!$applyProjectPrivileges) return;
		// Obtain the user's project-level user privileges
		$userAuthenticated = $this->checkPrivileges();
		if (!$userAuthenticated || ($userAuthenticated === '2' && !isset($_SESSION['impersonate_user'][PROJECT_ID]['impersonator'])))
		{
			if (!$GLOBALS['no_access'] && !$isAjax) {
				include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
				renderPageTitle();
			}
			$noAccessMsg = ($userAuthenticated === '2') ? $lang['config_04'] . "<br><br>" : "";
			$noAccessMsg2 = ($userAuthenticated === '2') ? $lang['config_06'] : "<a href=\"mailto:{$GLOBALS['project_contact_email']}\">{$GLOBALS['project_contact_name']}</a> {$lang['config_03']}";
			print  "<div class='red'>
						<i class=\"fas fa-exclamation-circle\"></i> <b>{$lang['global_05']}</b><br><br>$noAccessMsg {$lang['config_02']} $noAccessMsg2
					</div>";
			// Display special message if user has no access AND is a DDE user
			if ($double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) {
				print RCView::div(array('class'=>'yellow', 'style'=>'margin-top:20px;'), RCView::b($lang['global_02'].$lang['colon'])." ".$lang['rights_219']);
			}
			// Display link to My Projects page
			if ($GLOBALS['no_access']) {
				print RCView::div(array('style'=>'margin-top:20px;'), RCView::a(array('href'=>APP_PATH_WEBROOT_FULL.'index.php?action=myprojects'), $lang['bottom_69']) );
			} elseif (!$isAjax) {
				// Show left-hand menu unless it's been flagged to hide everything to prevent user from doing anything else
				include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
			}
			exit;
		}
	}
	
	/**
	 * Set SUPER USER privileges in $user_rights array. Returns true always.
	 */
	private function getSuperUserPrivileges()
	{
		global $data_resolution_enabled, $Proj, $DDP, $mobile_app_enabled, $api_enabled, $mycap_enabled_global, $mycap_enabled;
		// Manually set $user_rights array
		$user_rights = array('username'=>(defined("USERID") ? USERID : ""), 'expiration'=>'', 'group_id'=>'', 'role_id'=>'',
							 'lock_record'=>2, 'lock_record_multiform'=>1, 'lock_record_customize'=>1,
							 'data_export_tool'=>1, 'data_export_instruments'=>1, 'data_import_tool'=>1, 'data_comparison_tool'=>1, 'data_logging'=>1, 'email_logging'=>1, 'file_repository'=>1,
							 'user_rights'=>1, 'data_access_groups'=>1, 'design'=>1, 'alerts'=>1, 'calendar'=>1, 'reports'=>1, 'graphical'=>1,
							 'double_data'=>0, 'record_create'=>1, 'record_rename'=>1, 'record_delete'=>1, 'api_token'=>'', 'dts'=>1,
							 'participants'=>1, 'data_quality_design'=>1, 'data_quality_execute'=>1,
							 'data_quality_resolution'=>($data_resolution_enabled == '2' ? 3 : 0),
							 'api_export'=>1, 'api_import'=>1, 'api_modules'=>1, 'mobile_app'=>(($mobile_app_enabled && $api_enabled) ? 1 : 0),
							 'mobile_app_download_data'=>(($mobile_app_enabled && $api_enabled) ? 1 : 0),
                             'mycap_participants'=>(($mycap_enabled_global && $mycap_enabled) ? 1 : 0),
							 'random_setup'=>1, 'random_dashboard'=>1, 'random_perform'=>1,
							 'realtime_webservice_mapping'=>(is_object($DDP) && ((DynamicDataPull::isEnabledInSystem() && DynamicDataPull::isEnabled($Proj->project_id)) || (DynamicDataPull::isEnabledInSystemFhir() && DynamicDataPull::isEnabledFhir($Proj->project_id)))),
							 'realtime_webservice_adjudicate'=>(is_object($DDP) && ((DynamicDataPull::isEnabledInSystem() && DynamicDataPull::isEnabled($Proj->project_id)) || (DynamicDataPull::isEnabledInSystemFhir() && DynamicDataPull::isEnabledFhir($Proj->project_id)))),
							 'external_module_config'=>array()
							);

		// Set form-level rights
		foreach ($Proj->forms as $this_form=>$attr) {
			// If this form is used as a survey, give super user level 3 (survey response editing), else give level 1 for form-level edit rights
			$this_form_rights = ["view-edit", "delete"];
			if (isset($attr['survey_id'])) {
				$this_form_rights[] = "editresp";
			}
			$user_rights['forms'][$this_form] = self::encodeDataViewingRights($this_form_rights);
			$user_rights['forms_export'][$this_form] = '1';
		}

		// Put user_rights into global scope
		$GLOBALS['user_rights'] = $user_rights;

		// Return as true
		return true;
	}


	public static function addPrivileges($project_id, $rights)
	{
		$project_id = (int)$project_id;

		$cols_blank_defaults = array('expiration', 'data_entry', 'data_export_instruments');
		$keys = self::getApiUserPrivilegesAttr(false, $project_id);

		$cols = $vals = array();
		foreach($keys as $k=>$v)
		{
			$cols[] = $backEndKey = is_numeric($k) ? $v : $k;
			$vals[] = (($rights[$v]??"") == '' && !in_array($backEndKey, $cols_blank_defaults)) ? ($backEndKey == 'group_id' ? 'null' : 0) : checkNull($rights[$v]);
		}

		// If forms are missing for new user, then set all to 0
		if (!isset($rights['forms'])) {
			$formsRights = "";
			$Proj = new Project($project_id);
			foreach (array_keys($Proj->forms) as $this_form) {
				$formsRights .= "[$this_form,0]";
			}
			$vals[array_search('data_entry', $cols)] = checkNull($formsRights);
		}

		// If export forms are missing for new user, then set all to 0
		if (!isset($rights['forms_export'])) {
			$formsExportRights = "";
			$Proj = new Project($project_id);
			foreach (array_keys($Proj->forms) as $this_form) {
				$formsExportRights .= "[$this_form,0]";
			}
			$vals[array_search('data_export_instruments', $cols)] = checkNull($formsExportRights);
		}

		$sql = "INSERT INTO redcap_user_rights (project_id,	".implode(", ", $cols).") VALUES ($project_id, ".implode(", ", $vals).")";
		$q = db_query($sql);

        if ($q) {
            Logging::logEvent("","redcap_user_rights","insert",$rights['username'],"user = '".db_escape($rights['username'])."'","Add user");
            // DAGs?
            $dag = str_replace("'", "", $vals[array_search('group_id', $cols)]);
            if (isinteger($dag)) {
                $Proj = new Project($project_id);
                $group_name = $Proj->getUniqueGroupNames($dag);
                Logging::logEvent("", "redcap_user_rights", "MANAGE", $rights['username'], "user = '" . db_escape($rights['username']) . "',\ngroup = '$group_name'", "Assign user to data access group");
            }
        }

		return ($q && $q !== false);
	}


	public static function updatePrivileges($project_id, $rights)
	{
		$project_id = (int)$project_id;

        $existing_dag = UserRights::getPrivileges($project_id, $rights['username'])[$project_id][$rights['username']]['group_id'];

		$cols_blank_defaults = array('expiration', 'data_entry', 'data_export_instruments');
		$keys = self::getApiUserPrivilegesAttr(false, $project_id);
		$vals = array();
		foreach($keys as $k=>$v)
		{
			// If value was not sent, then do not update it
			if (!isset($rights[$v]) && $v != "data_access_group") continue;
			// Set update value
			$backEndKey = is_numeric($k) ? $v : $k;
			$vals[] = "$backEndKey = " . (($rights[$v] == '' && !in_array($backEndKey, $cols_blank_defaults) && $v != "data_access_group") ? 0 : checkNull($rights[$v]));
		}

		$sql = "UPDATE redcap_user_rights SET ".implode(", ", $vals)."
				WHERE project_id = $project_id AND username = '".db_escape($rights['username'])."'";
		$q = db_query($sql);

        if ($q) {
            Logging::logEvent("","redcap_user_rights","update",$rights['username'],"user = '".db_escape($rights['username'])."'","Edit user");
            // DAGs?
            $dag = $rights['data_access_group'];
            if (isinteger($dag) && $existing_dag != $dag) {
                // Assigning to a DAG
                $Proj = new Project($project_id);
                $group_name = $Proj->getUniqueGroupNames($dag);
                Logging::logEvent("", "redcap_user_rights", "MANAGE", $rights['username'], "user = '" . db_escape($rights['username']) . "',\ngroup = '$group_name'", "Assign user to data access group");
            } elseif (!isinteger($dag) && isinteger($existing_dag)) {
                // Removing user from DAG
                $Proj = new Project($project_id);
                $group_name = $Proj->getUniqueGroupNames($existing_dag);
                Logging::logEvent("", "redcap_user_rights", "MANAGE", $rights['username'], "user = '" . db_escape($rights['username']) . "',\ngroup = '$group_name'", "Remove user from data access group");
            }
        }

		return ($q && $q !== false);
	}


	/**
	 * Return array of attributes to be imported/export for users via API User Import/Export
	 */
	public static function getApiUserPrivilegesAttr($returnEmailAndName=false, $project_id=null)
	{
	    global $mycap_enabled_global, $mycap_enabled;
		$attrInfo = array('email', 'firstname', 'lastname');
		$attr = array('username', 'expiration', 'group_id'=>'data_access_group', 'design', 'alerts', 'user_rights', 'data_access_groups',
				'data_export_tool'=>'data_export', 'reports', 'graphical'=>'stats_and_charts',
				'participants'=>'manage_survey_participants', 'calendar', 'data_import_tool',
				'data_comparison_tool', 'data_logging'=>'logging', 'email_logging', 'file_repository',
				'data_quality_design'=>'data_quality_create', 'data_quality_execute',
				'api_export', 'api_import', 'api_modules', 'mobile_app', 'mobile_app_download_data',
				'record_create', 'record_rename', 'record_delete',
				'lock_record_customize'=>'lock_records_customization',
				'lock_record'=>'lock_records', 'lock_record_multiform'=>'lock_records_all_forms');
        if ($mycap_enabled_global && $mycap_enabled) {
            $attr['mycap_participants'] = 'mycap_participants';
        }
        $attr['data_entry'] = 'forms';
        $attr['data_export_instruments'] = 'forms_export';

        if ($project_id !== null) {
            $Proj = new Project($project_id);
            if ($Proj->project['data_resolution_enabled'] == '2') {
                $attr['data_quality_resolution'] = 'data_quality_resolution';
            }
            if ($Proj->project['double_data_entry'] == '1') {
                $attr['double_data'] = 'double_data';
            }
            if ($Proj->project['randomization'] == '1') {
                $attr['random_setup'] = 'random_setup';
                $attr['random_dashboard'] = 'random_dashboard';
                $attr['random_perform'] = 'random_perform';
            }
        }
		if ($returnEmailAndName) {
			unset($attr[0]);
			$attr = array_merge(array('username'), $attrInfo, $attr);
		}
		return $attr;
	}

	/**
	 * GET USER PRIVILEGES
	 *
	 */
	public static function getPrivileges($project_id=null, $userid=null)
	{
        // Transform all legacy export rights for all users and roles in project (if applicable)
        self::transformExportRightsAllUsers($project_id);
		// Put rights in array
		$user_rights = array();
		// Set subquery
		$sqlsub = "";
		if ($project_id != null || $userid != null) {
			$sqlsub = "where";
			if ($project_id != null) {
				$sqlsub .= " r.project_id = $project_id";
			}
			if ($project_id != null && $userid != null) {
				$sqlsub .= " and";
			}
			if ($userid != null) {
				$sqlsub .= " r.username = '".db_escape($userid)."'";
			}
		}
		// Check if a user for this project
		$sql = "select r.*, u.* from redcap_user_rights r left outer join redcap_user_roles u
				on r.role_id = u.role_id $sqlsub order by r.project_id, r.username";
		$q = db_query($sql);
		// Set $user_rights array, which will carry all rights for current user.
		while ($row = db_fetch_array($q, MYSQLI_NUM))
		{
			// Get current project_id and user to use as array keys
			$this_project_id = $row[0];
			$this_user = strtolower($row[1]); // Deal with case-sensitivity issues
			// Loop through fields using numerical indexes so we don't overwrite user values with NULLs if not in a role.
			foreach ($row as $this_field_num=>$this_value) {
				// Get name of field
				$this_field = db_field_name($q, $this_field_num);
				// If we hit the project_id again (from user_roles table) and it is null, then stop here so we don't overwrite
				// users values with NULLs since they are not in a role.
				if ($this_field == 'project_id' && $this_value === null && $this_field_num > 0) break;
				// Make sure username is lower case, for consistency
				if ($this_field == 'username') {
					$this_value = strtolower($this_value);
				}
				// External Modules config permissions: Decode the JSON
				elseif ($this_field == 'external_module_config' && $this_value !== null) {
					$this_value = json_decode($this_value, true);
					if (!is_array($this_value)) $this_value = array();
				} elseif ($this_field == 'group_id' && $this_value === null) {
					$this_value = "";
				}
				// Add value to array (and escape quotes)
                if ($this_value === null) $this_value = "";
                if (is_string($this_value)) $this_value = addslashes($this_value);
				$user_rights[$this_project_id][$this_user][$this_field] = $this_value;
			}
		}
		// Return array
		return $user_rights;
	}

	/**
	 * CHECK USER PRIVILEGES IN A GIVEN PROJECT
	 * Checks if user has rights to see this page
	 */
	public function checkPrivileges()
	{
		global $data_resolution_enabled, $data_locked, $status;

		// Initialize $user_rights as global variable as array
		global $user_rights;
		$user_rights = array();
		$this_project_id = PROJECT_ID;

		// If a SUPER USER, then manually set rights to full/max for all things
		if ((!defined("SUPER_USER") || SUPER_USER) && !self::isImpersonatingUser()) {
			return $this->getSuperUserPrivileges();
		} elseif ((!defined("SUPER_USER") || SUPER_USER) && self::isImpersonatingUser()) {
			$this_user = self::getUsernameImpersonating();
		} else {
			$this_user = USERID;
		}

		## NORMAL USERS
		// Check if a user for this project
		$user_rights_proj_user = $this->getPrivileges($this_project_id, $this_user);
		$user_rights = (isset($user_rights_proj_user[$this_project_id]) && isset($user_rights_proj_user[$this_project_id][strtolower($this_user)])) ? $user_rights_proj_user[$this_project_id][strtolower($this_user)] : [];
		unset($user_rights_proj_user);
		// Kick out if not a user and not a Super User
		if (count($user_rights) < 1) {
			//Still show menu if a user from a child/linked project
			$GLOBALS['no_access'] = 1;
			return false;
		}

		// Check user's expiration date (if exists)
		if ($user_rights['expiration'] != "" && $user_rights['expiration'] <= TODAY && !self::isImpersonatingUser())
		{
			$GLOBALS['no_access'] = 1;
			// Instead of returning 'false', return '2' specifically so we can note to user that the password has expired
			return '2';
		}

		// Data resolution workflow: disable rights if module is disabled
		if ($data_resolution_enabled != '2') $user_rights['data_quality_resolution'] = '0';

		// SET FORM-LEVEL RIGHTS: Loop through data entry listings and add each form as a new sub-array element
		$user_rights = $this->setFormLevelPrivileges($user_rights);

		// If project has Data Locked while in Analysis/Cleanup status, then
		if ($status == '2') {
			// Whether data is locked or not, prevent from creating new records (not allowed for this status)
			$user_rights['record_create'] = '0';
			// Further limit user rights if Data Locked is enabled
			if ($data_locked == '1') {
				// Disable the user's ability to create, rename, or delete records
				$user_rights['record_rename'] = '0';
				$user_rights['record_delete'] = '0';
				// If user has API access, then ensure that api_import is disabled
				$user_rights['api_import'] = '0';
				// Prevent ability to import data via Data Import Tool
				$user_rights['data_import_tool'] = '0';
				// If project has Data Locked, then remove edit form-level privileges and set to read-only
				foreach ($user_rights['forms'] as $this_form=>$this_form_rights) {
					if (self::hasDataViewingRights($this_form_rights, 'view-edit')) {
						$user_rights['forms'][$this_form] = self::encodeDataViewingRights("read-only");
					}
				}
				// Disable locking privileges
				$user_rights['lock_record'] = '0';
				$user_rights['lock_record_multiform'] = '0';
			}
		}

		// Remove array elements no longer needed
		unset($user_rights['data_entry'], $user_rights['data_export_instruments'], $user_rights['project_id']);

		// Check page-level privileges: Return true if has access to page, else false.
		return $this->checkPageLevelPrivileges();
	}

    // Transform all legacy export rights for all users and roles in project (if applicable)
    public static function transformExportRightsAllUsers($project_id)
    {
        if (!isinteger($project_id)) return;
        // Fix user_rights table
        $sql = "select username, data_export_tool from redcap_user_rights
				where project_id = $project_id and data_export_instruments is null";
        $q = db_query($sql);
        if (db_num_rows($q) > 0) {
            $Proj = new Project($project_id);
            while ($row = db_fetch_assoc($q)) {
                if ($row['data_export_tool'] == null) $row['data_export_tool'] = 0;
                $data_export_instruments = count($Proj->forms) == 0 ? "" : "[" . implode(",{$row['data_export_tool']}][", array_keys($Proj->forms)) . ",{$row['data_export_tool']}]";
                $sql = "update redcap_user_rights set data_export_tool = null, data_export_instruments = " . checkNull($data_export_instruments) . "
                        where project_id = $project_id and username = '" . db_escape($row['username']) . "'";
                db_query($sql);
            }
        }
        // Fix user_roles table
        $sql = "select role_id, data_export_tool from redcap_user_roles
				where project_id = $project_id and data_export_instruments is null";
        $q = db_query($sql);
        if (db_num_rows($q) > 0) {
            if (!isset($Proj)) $Proj = new Project($project_id);
            while ($row = db_fetch_assoc($q)) {
                if ($row['data_export_tool'] == null) $row['data_export_tool'] = 0;
                $data_export_instruments = count($Proj->forms) == 0 ? "" : "[" . implode(",{$row['data_export_tool']}][", array_keys($Proj->forms)) . ",{$row['data_export_tool']}]";
                $sql = "update redcap_user_roles set data_export_tool = null, data_export_instruments = " . checkNull($data_export_instruments) . "
                        where role_id = " . $row['role_id'];
                db_query($sql);
            }
        }
    }


	/**
	 * OBTAIN USER RIGHTS INFORMATION FOR ALL USERS IN THIS PROJECT
	 * Also includes users' first and last name and email address
	 * Return array with username as key (sorted by username)
	 */
	public static function getRightsAllUsers($enableDagLimiting=true)
	{
		global $Proj, $lang, $user_rights;
        if (!defined("PROJECT_ID")) return array();
        // Transform all legacy export rights for all users and roles in project (if applicable)
       self::transformExportRightsAllUsers(PROJECT_ID);
		// Update group_id if logged in as user
        if (defined("USERID") && (!defined("SUPER_USER") || !SUPER_USER)) {
            $stored_user_rights = self::getPrivileges(PROJECT_ID, USERID);
            $user_rights['group_id'] = $stored_user_rights[PROJECT_ID][USERID]['group_id'] ?? "";
        }
		// Pull all user/role info for this project
		$users = array();
		$group_id = $user_rights['group_id'] ?? null;
		$group_sql = ($enableDagLimiting && $group_id != "") ? "and u.group_id = '$group_id'" : "";
		$sql = "select u.*, i.user_firstname, i.user_lastname, trim(concat(i.user_firstname, ' ', i.user_lastname)) as user_fullname
				from redcap_user_rights u left outer join redcap_user_information i on i.username = u.username
				where u.project_id = " . PROJECT_ID . " $group_sql order by u.username";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Set username so we can set as key and remove from array values
			$username = $row['username']= strtolower($row['username']);
			unset($row['username']);
			// Add to array
			$users[$username] = $row;
		}
		// Return array
		return $users;
	}


	/**
	 * OBTAIN ALL USER ROLES INFORMATION FOR THIS PROJECT (INCLUDES SYSTEM-LEVEL ROLES)
	 * Return array with role_id as key (sorted with project-level roles first, then system-level roles)
	 */
	public static function getRoles($project_id=null)
	{
		if (!isinteger($project_id)) {
			if (!defined("PROJECT_ID")) return array();
			$project_id = PROJECT_ID;
		}
        // Transform all legacy export rights for all users and roles in project (if applicable)
        self::transformExportRightsAllUsers($project_id);
		// Pull all user/role info for this project
		$roles = array();
		$sql = "select * from redcap_user_roles where project_id = " . $project_id . "
				order by project_id desc, role_name";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Set role_id so we can set as key and remove from array values
			$role_id = $row['role_id'];
			unset($row['role_id']);
            if ($row['unique_role_name'] == '') {
                $row['unique_role_name'] = self::addUniqueUserRoleName($role_id);
            }
			// Add to array
			$roles[$role_id] = $row;
		}
		// Return array
		return $roles;
	}


	/**
	 * SET FORM-LEVEL PRIVILEGES & FORM-LEVEL EXPORT PRIVILEGES
	 * Loop through data entry listings and add each form as a new sub-array element
	 * Does not return anything
	 */
	public function setFormLevelPrivileges($user_rights=[])
	{
		// SET FORM-LEVEL PRIVILEGES
		$allForms = self::convertFormRightsToArrayPre($user_rights['data_entry']);
		foreach ($allForms as $forminfo)
		{
			if (strpos($forminfo, ",")) {
				list($this_form, $this_form_rights) = explode(",", $forminfo, 2);
			} else {
				$this_form = $forminfo;
				$this_form_rights = self::encodeDataViewingRights("no-access");
			}
			$user_rights['forms'][$this_form] = $this_form_rights;
		}

		// SET FORM-LEVEL EXPORT PRIVILEGES
		// Transform old export rights: If form-level export rights aren't set yet, then extrapolate the existing export right to all forms and save it
		if (defined("PROJECT_ID") && $user_rights['data_export_instruments'] == null && isinteger($user_rights['data_export_tool']))
		{
			$Proj = new Project(PROJECT_ID);
			$user_rights['data_export_instruments'] = count($Proj->forms) == 0 ? "" : "[".implode(",{$user_rights['data_export_tool']}][", array_keys($Proj->forms)).",{$user_rights['data_export_tool']}]";
			if ($user_rights['role_id'] == '') {
				$sql = "update redcap_user_rights set data_export_tool = null, data_export_instruments = ".checkNull($user_rights['data_export_instruments'])."
						where project_id = ".PROJECT_ID." and username = '" . USERID . "'";
			} else {
				$sql = "update redcap_user_roles set data_export_tool = null, data_export_instruments = ".checkNull($user_rights['data_export_instruments'])."
						where role_id = ".$user_rights['role_id'];
			}
			$q = db_query($sql);
		}
		// Set the export privileges and co-opt the legacy "data_export_tool" right to serve as general status of data export ability (0=no export, 1=can export)
		$user_rights['data_export_tool'] = '0'; // default
		$userCanExportSomeData = false;
		$allForms = self::convertFormRightsToArrayPre($user_rights['data_export_instruments']);
		foreach ($allForms as $forminfo)
		{
			if (strpos($forminfo, ",")) {
				list($this_form, $this_form_rights) = explode(",", $forminfo, 2);
			} else {
				$this_form = $forminfo;
				$this_form_rights = 0;
			}
			$user_rights['forms_export'][$this_form] = $this_form_rights;
			// If user can export the fields of at least one instrument, then set data_export_tool=1 to serve as general status of export ability
			if ($this_form_rights > 0 && !$userCanExportSomeData) {
				$userCanExportSomeData = true;
				$user_rights['data_export_tool'] = '1';
			}
		}
        // If user rights have all forms with de-id or remove identifiers export rights, then blanket set data_export_tool rights as 2 or 3
        if ($userCanExportSomeData && self::allFormsDeidExportRights($user_rights)) {
            $user_rights['data_export_tool'] = '2';
        } elseif ($userCanExportSomeData && self::allFormsRemoveIdentifierExportRights($user_rights)) {
            $user_rights['data_export_tool'] = '3';
        }

		// AUTO FIX FORM-LEVEL RIGHTS: Double check to make sure that the form-level rights are all there
		$user_rights = $this->autoFixFormLevelPrivileges($user_rights);

		return $user_rights;
	}

    // Return boolean regarding if user's user rights contain De-id Export rights (2) for ALL instruments
    public static function allFormsDeidExportRights($user_rights)
    {
        foreach ($user_rights['forms_export'] as $right) {
            if ($right != '2') return false;
        }
        return true;
    }

    // Return boolean regarding if user's user rights contain Remove Identifiers rights (3) for ALL instruments
    public static function allFormsRemoveIdentifierExportRights($user_rights)
    {
        foreach ($user_rights['forms_export'] as $right) {
            if ($right != '3') return false;
        }
        return true;
    }

    // Return boolean regarding if user's user rights contain Full Data Export rights (1) for ALL instruments
    public static function allFormsFullDataExportRights($user_rights)
    {
        foreach ($user_rights['forms_export'] as $right) {
            if ($right != '1') return false;
        }
        return true;
    }

	/**
	 * AUTO FIX FORM-LEVEL PRIVILEGES (IF NEEDED)
	 * Double check to make sure that the form-level rights are all there (old bug would sometimes cause
	 * them to go missing, thus disrupting things).
	 * Does not return anything
	 */
	private function autoFixFormLevelPrivileges($user_rights=[])
	{
		global $Proj;
		if (!defined("PROJECT_ID") || !defined("USERID") || !(isset($Proj->project) && is_array($Proj->project))) return $user_rights;
        // If in production, do all users get "no access" by default?
        $newFormRight = ($Proj->project['status'] == '0' || ($GLOBALS['new_form_default_prod_user_access'] == '1' || $GLOBALS['new_form_default_prod_user_access'] == '2')) 
			? self::encodeDataViewingRights("view-edit") 
			: self::encodeDataViewingRights("no-access");
		$newFormExportRight = ($Proj->project['status'] == '0' || $GLOBALS['new_form_default_prod_user_access'] == '1') ? "1" : ($GLOBALS['new_form_default_prod_user_access'] == '2' ? "2" : "0");
		$userCanExportSomeData = false;
		// Loop through all forms and check user rights for each
		foreach (array_keys($Proj->forms) as $this_form)
		{
			// DATA ENTRY
			if (!isset($user_rights['forms'][$this_form])) {
				// Add to user_rights table (give user Full Edit rights to the form as default, if missing)
				if ($user_rights['role_id'] == '') {
					$sql = "update redcap_user_rights set data_entry = concat(data_entry,'[$this_form,$newFormRight]')
							where project_id = ".PROJECT_ID." and username = '" . USERID . "'";
				} else {
					$sql = "update redcap_user_roles set data_entry = concat(data_entry,'[$this_form,$newFormRight]')
							where role_id = ".$user_rights['role_id'];
				}
				$q = db_query($sql);
				if (db_affected_rows() < 1) {
					// Must have a NULL as data_entry value, so fix it
					if ($user_rights['role_id'] == '') {
						$sql = "update redcap_user_rights set data_entry = '[$this_form,$newFormRight]'
								where project_id = ".PROJECT_ID." and username = '" . USERID . "'";
					} else {
						$sql = "update redcap_user_roles set data_entry = '[$this_form,$newFormRight]'
								where role_id = ".$user_rights['role_id'];
					}
					$q = db_query($sql);
				}
				// Also add to $user_rights array
				$user_rights['forms'][$this_form] = $newFormRight;
			}

			// DATA EXPORT
			if (!isset($user_rights['forms_export'][$this_form])) {
				// Add to user_rights table (give user Full Edit rights to the form as default, if missing)
				if ($user_rights['role_id'] == '') {
					$sql = "update redcap_user_rights set data_export_instruments = concat(data_export_instruments,'[$this_form,$newFormExportRight]')
							where project_id = ".PROJECT_ID." and username = '" . USERID . "'";
				} else {
					$sql = "update redcap_user_roles set data_export_instruments = concat(data_export_instruments,'[$this_form,$newFormExportRight]')
							where role_id = ".$user_rights['role_id'];
				}
				$q = db_query($sql);
				if (db_affected_rows() < 1) {
					// Must have a NULL as data_export_instruments value, so fix it
					if ($user_rights['role_id'] == '') {
						$sql = "update redcap_user_rights set data_export_instruments = '[$this_form,$newFormExportRight]'
								where project_id = ".PROJECT_ID." and username = '" . USERID . "'";
					} else {
						$sql = "update redcap_user_roles set data_export_instruments = '[$this_form,$newFormExportRight]'
								where role_id = ".$user_rights['role_id'];
					}
					$q = db_query($sql);
				}
				// Also add to $user_rights array
				$user_rights['forms_export'][$this_form] = $newFormExportRight;
			}

			// If user can export the fields of at least one instrument, then set data_export_tool=1 to serve as general status of export ability
			if ($user_rights['forms_export'][$this_form] > 0 && !$userCanExportSomeData) {
				$userCanExportSomeData = true;
				$user_rights['data_export_tool'] = '1';
			}
		}
        // If user rights have all forms with de-id or remove identifiers export rights, then blanket set data_export_tool rights as 2 or 3
        if ($userCanExportSomeData && self::allFormsDeidExportRights($user_rights)) {
            $user_rights['data_export_tool'] = '2';
        } elseif ($userCanExportSomeData && self::allFormsRemoveIdentifierExportRights($user_rights)) {
            $user_rights['data_export_tool'] = '3';
        }

		return $user_rights;
	}


	/**
	 * CHECK A USER'S PAGE-LEVEL USER PRIVILEGES
	 * Return true if they have access to the current page, else return false if they do not.
	 */
	private function checkPageLevelPrivileges()
	{
		global $user_rights, $double_data_entry, $Proj;

		// Check Data Entry page rights (edit/read-only/none), if we're on that page
		if (defined("PAGE") && PAGE == 'DataEntry/index.php')
		{
			// If 'page' is not a valid form, then redirect to home page
			if (isset($_GET['page']) && !isset($Proj->forms[$_GET['page']])) {
				redirect(APP_PATH_WEBROOT . "index.php?pid=" . PROJECT_ID);
			}
			// If user does not have rights to this form, then return false
			if (!isset($user_rights['forms'][$_GET['page']])) {
				return false;
			}
			// If user has no access to form, kick out; otherwise set as full access or disabled
			if (isset($user_rights['forms'][$_GET['page']])) {
				return !self::hasDataViewingRights($user_rights['forms'][$_GET['page']], "no-access");
			}
		}

		// DDE Person will have no rights to certain pages or routes that display data
		if ($double_data_entry && $user_rights['double_data'] != 0 && defined("PAGE") && in_array(PAGE, $this->pagesRestrictedDDE)) {
			return false;
		}

        // If user has readonly rights to the User Rights page, disallow them from using all UserRights folder endpoints (excluding UserRights/index.php)
        if (defined("PAGE") && $user_rights['user_rights'] == '2' && PAGE != 'UserRights/index.php' && starts_with(PAGE, "UserRights/")) {
            return false;
        }

		// Determine if user has rights to current page
		if (defined("PAGE") && isset($this->page_rights[PAGE]) && isset($user_rights[$this->page_rights[PAGE]]))
		{
			// Does user have access to this page (>0)?
			return ($user_rights[$this->page_rights[PAGE]] > 0);
		}

		// If you got here, then you're on a page not dictated by rights in the $user_rights array, so allow access
		return true;
	}


	/**
	 * RENDER COMPREHENSIVE USER RIGHTS/ROLES TABLE
	 * Return true if they have access to the current page, else return false if they do not.
	 */
	public static function renderUserRightsRolesTable()
	{
		global $user_rights, $lang, $Proj, $double_data_entry, $dts_enabled_global, $dts_enabled, $mobile_app_enabled, $email_logging_enable_global,
				$api_enabled, $randomization, $enable_plotting, $data_resolution_enabled, $DDP, $scheduling, $mycap_enabled_global, $mycap_enabled;

        // Update group_id if logged in as user
        if  (!SUPER_USER) {
            $stored_user_rights = self::getPrivileges(PROJECT_ID, USERID);
            $user_rights['group_id'] = $stored_user_rights[PROJECT_ID][USERID]['group_id'] ?? "";
        }
        // Transform all legacy export rights for all users and roles in project (if applicable)
        self::transformExportRightsAllUsers(PROJECT_ID);
		// Check if DAGs exist and retrieve as array
		$dags = $Proj->getGroups();

		// Set image variables
		$imgYes = RCView::i(['class'=>'fa-solid fa-check text-success fs18', 'title'=>$lang['rights_440']]);
		$imgNo = RCView::i(['class'=>'fa-solid fa-xmark text-danger fs18', 'title'=>$lang['rights_47']]);
		$imgReadonly = RCView::i(['class'=>'fa-solid fa-eye fs15 text-secondary', 'title'=>$lang['rights_61']]);
		$imgShield = RCIcon::ESigned("text-success");

		// Set up array of all possible headers for the table (some columns will be hidden depending on project or system settings)
		$rightsHdrs = array(
			'role_name' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:13px;'), $lang['rights_148']).RCView::div(array('class'=>'userrights-table-hdr-sub'), $lang['rights_206']), 'enabled' => true, 'width'=>200, 'align'=>'left'),
			'username' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:13px;'), $lang['global_11'])." ".$lang['rights_150'].RCView::div(array('class'=>'userrights-table-hdr-sub'), $lang['rights_174']), 'enabled' => true, 'width'=>270, 'align'=>'left'),
			'expiration' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:12px;'), $lang['rights_95']).RCView::div(array('class'=>'userrights-table-hdr-sub'), $lang['rights_209']), 'enabled' => true, 'width'=>80),
			'group_id' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:12px;'), $lang['global_78']).
				($user_rights['group_id'] != '' ? '' : RCView::div(array('class'=>'userrights-table-hdr-sub'), $lang['rights_210'])),
				'enabled' => !empty($dags), 'width'=>130),
			'design' => array('hdr' => RCView::b($lang['rights_135']), 'enabled' => true, 'width'=>60),
			'user_rights' => array('hdr' => RCView::b($lang['app_05']), 'enabled' => true, 'width'=>40),
			'data_access_groups' => array('hdr' => RCView::b($lang['global_22']), 'enabled' => true),
			'data_entry' => array('hdr' => RCView::b($lang['rights_373']), 'enabled' => true, 'width'=>85),
			'data_export_tool' => array('hdr' => RCView::b($lang['rights_428']), 'enabled' => true, 'width'=>85),
			'alerts' => array('hdr' => RCView::b($lang['global_154']), 'enabled' => true, 'width'=>80),
			'reports' => array('hdr' => RCView::b($lang['rights_448']), 'enabled' => true),
			'graphical' => array('hdr' => RCView::b($lang['report_builder_78']), 'enabled' => $enable_plotting > 0),
			'participants' => array('hdr' => RCView::b($lang['app_24']), 'enabled' => !empty($Proj->surveys), 'width'=>65),
			'calendar' => array('hdr' => RCView::b($lang['app_08'] . ($scheduling ? " ".$lang['rights_357'] : "")), 'enabled' => true, 'width'=>60),
			'data_import_tool' => array('hdr' => RCView::b($lang['app_01']), 'enabled' => true, 'width'=>60),
			'data_comparison_tool' => array('hdr' => RCView::b($lang['app_02']), 'enabled' => true, 'width'=>70),
			'data_logging' => array('hdr' => RCView::b($lang['app_07']), 'enabled' => true, 'width'=>45),
			'email_logging' => array('hdr' => RCView::b($Proj->project['twilio_enabled'] ? $lang['email_users_96'] : $lang['email_users_53']), 'enabled' => $email_logging_enable_global, 'width'=>45),
			'file_repository' => array('hdr' => RCView::b($lang['app_04']), 'enabled' => true, 'width'=>60),
			'double_data' => array('hdr' => RCView::b($lang['rights_50']), 'enabled' => $double_data_entry),
			'lock_record_customize' => array('hdr' => RCView::b($lang['app_11']), 'enabled' => true, 'width'=>90),
			'lock_record' => array('hdr' => RCView::b($lang['rights_97']." ".$lang['rights_371']), 'enabled' => true, 'width'=>70),
            'lock_record_multiform' => array('hdr' => RCView::b($lang['rights_370']), 'enabled' => true, 'width'=>90),
			'randomization' => array('hdr' => RCView::b($lang['app_21']), 'enabled' => $randomization, 'width'=>90),
			'data_quality_design' => array('hdr' => RCView::b($lang['dataqueries_38']), 'enabled' => true),
			'data_quality_execute' => array('hdr' => RCView::b($lang['dataqueries_39']), 'enabled' => true),
			'data_quality_resolution' => array('hdr' => RCView::b($lang['dataqueries_137']), 'enabled' => ($data_resolution_enabled == '2')),
			'api' => array('hdr' => RCView::b($lang['setup_77']), 'enabled' => $api_enabled, 'width'=>40),
			'mobile_app' => array('hdr' => RCView::b($lang['global_118']), 'enabled' => ($mobile_app_enabled && $api_enabled), 'width'=>50),
			'realtime_webservice_mapping' => array('hdr' => RCView::b((DynamicDataPull::isEnabledInSystemFhir() ? $lang['ws_210'] : $lang['ws_51'])." {$DDP->getSourceSystemName()}<div style='font-weight:normal;'>({$lang['ws_19']})</div>"), 'enabled' => (is_object($DDP) && ((DynamicDataPull::isEnabledInSystem() && $DDP->isEnabledInProject()) || (DynamicDataPull::isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir())))),
			'realtime_webservice_adjudicate' => array('hdr' => RCView::b((DynamicDataPull::isEnabledInSystemFhir() ? $lang['ws_210'] : $lang['ws_51'])." {$DDP->getSourceSystemName()}<div style='font-weight:normal;'>({$lang['ws_20']})</div>"), 'enabled' => (is_object($DDP) && ((DynamicDataPull::isEnabledInSystem() && $DDP->isEnabledInProject()) || (DynamicDataPull::isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir())))),
			'dts' => array('hdr' => RCView::b($lang['rights_132']), 'enabled' => $dts_enabled_global && $dts_enabled),
            'mycap_participants' => array('hdr' => RCView::b($lang['rights_437']), 'enabled' => $mycap_enabled_global && $mycap_enabled),
			'record_create' => array('hdr' => RCView::b($lang['rights_99']), 'enabled' => true, 'width'=>45),
			'record_rename' => array('hdr' => RCView::b($lang['rights_100']), 'enabled' => true, 'width'=>45),
			'record_delete' => array('hdr' => RCView::b($lang['rights_101']), 'enabled' => true, 'width'=>45),
            'role_id' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:13px;'), $lang['rights_403']).RCView::div(array('class'=>'userrights-table-hdr-sub2'), $lang['define_events_66']), 'enabled' => true, 'width'=>80, 'align'=>'center', 'sort_type'=>'int'),
            'unique_role_name' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:13px;'), $lang['rights_404']).RCView::div(array('class'=>'userrights-table-hdr-sub2'), $lang['define_events_66']), 'enabled' => true, 'width'=>95, 'align'=>'center')
		);

		// Get all user rights as array
		$rightsAllUsers = self::getRightsAllUsers();

		// Get all suspended users in project (so we can note which are currently suspended)
		$suspendedUsers = User::getSuspendedUsers();

		// Get all user roles as array
		$roles = self::getRoles();

		// Loop through $roles and add a sub-array of users to each role that are assigned to it
		foreach ($rightsAllUsers as $this_username=>$attr) {
			// If has role_id value, then add username to that role in $roles
			if (is_numeric($attr['role_id'])) {
				$roles[$attr['role_id']]['role_users_assigned'][] = $this_username;
			}
		}
		//print_array($rightsAllUsers);
		//print_array($roles);

		// Set default column width in table
		$defaultColWidth = 70;

		// Set table width (loop through headers and calculate)
		$tableColPadding = 13;
		$tableWidth = 0;

		// Set up the table headers
		$hdrs = array();
		foreach ($rightsHdrs as $this_colname=>$attr) {
			// If this column is not enabled, skip it
			if (!$attr['enabled']) continue;
			// Determine col width
			$this_width = (isset($attr['width'])) ? $attr['width'] : $defaultColWidth;
			// Increment the table width
			$tableWidth += ($this_width + $tableColPadding);
			// Determine col alignment
			$this_align = (isset($attr['align'])) ? $attr['align'] : 'center';
            $this_sort_type = (isset($attr['sort_type'])) ? $attr['sort_type'] : 'string';
			// Add to $hdrs array to be displayed
			$hdrs[] = array($this_width, RCView::div(array('class'=>'wrap','style'=>'line-height:1.1;'), $attr['hdr']), $this_align, $this_sort_type);
		}

		## ADD TABLE ROWS
		// Add rows of users/roles (start with users not in a role, then go role by role listing users in each role)
		$rows = array();
		$rowkey = 0;
		foreach ($rightsAllUsers as $this_username=>$row) {
			// If has role_id value, then skip. We'll handle users in roles later.
			if (is_numeric($row['role_id'])) continue;
			$isSuspendedUser = in_array(strtolower($this_username), $suspendedUsers);
			// Add to $rows array
			$rows[$rowkey] = array();
			// Loop through each column
			foreach ($rightsHdrs as $rightsKey => $r)
			{
				// If this column is not enabled, skip it
				if (!$r['enabled']) continue;
				// Initialize vars
				$cellContent = '';
				// Output column's content (depending on which column we're on)
				if ($rightsKey == 'username') {
					// Set icon if has API token
					$apiIcon = ($row['api_token'] == '' ? '' :
							RCView::span(array('class'=>'nowrap', 'style'=>'color:#A86700;font-size:11px;margin-left:8px;'),
								RCView::img(array('src'=>'coin.png', 'style'=>'vertical-align:middle;')) .
								RCView::span(array('style'=>'vertical-align:middle;'),
									$lang['control_center_333']
								)
							)
						);
					// Set text if user's account is suspended
					$suspendedText = ($isSuspendedUser)
									? RCView::span(array('class'=>'nowrap', 'style'=>'color:red;font-size:11px;margin-left:8px;'),
										$lang['rights_281']
									  )
									: "";
					$this_username_name = RCView::b(RCView::escape($this_username)) . ($row['user_fullname'] == '' ? '' : " ({$row['user_fullname']})");
                    if ($user_rights['user_rights'] == '1') {
                        $cellContent = 	RCView::div(array('class'=>'userNameLinkDiv', 'data-suspended-user'=> ($isSuspendedUser ? 1 : 0)),
                            RCView::a(array('href'=>'javascript:;', 'class'=>'userLinkInTable text-primaryrc fs13', 'style'=>'vertical-align:middle;text-decoration:none;', 'title'=>$lang['rights_178'],
                                'inrole'=>'0', 'userid'=>$this_username), $this_username_name) .
                            $suspendedText . $apiIcon
                        );
                    } else {
                        $cellContent = 	RCView::div(array('class'=>'userNameLinkDiv'),
                            RCView::span(array('class'=>'text-primaryrc fs13', 'style'=>'vertical-align:middle;text-decoration:none;'), $this_username_name) .
                            $suspendedText . $apiIcon
                        );
                    }
				}
				elseif (in_array($rightsKey, array('role_name', 'role_id', 'unique_role_name'))) {
					$cellContent = RCView::div(array('style'=>'color:#999;'), "&mdash;");
				}
				elseif ($rightsKey == 'expiration') {
					$this_class = ($row['expiration'] == "" ? 'userRightsExpireN' : (str_replace("-","",$row['expiration']) < date('Ymd') ? 'userRightsExpired' : 'userRightsExpire'));
                    if ($user_rights['user_rights'] == '1') {
                        $cellContent = 	RCView::div(array('class'=>'expireLinkDiv'),
                            RCView::a(array('href'=>'javascript:;', 'class'=>$this_class, 'title'=>$lang['rights_201'],
                                'userid'=>$this_username,
                                'expire'=>($row['expiration'] == "" ? "" : DateTimeRC::format_ts_from_ymd($row['expiration']))),
                                ($row['expiration'] == "" ? $lang['rights_171'] : DateTimeRC::format_ts_from_ymd($row['expiration']))
                            )
                        );
                    } else {
                        $cellContent = 	RCView::div(array('class'=>''),
                            RCView::span(array('class'=>'fs11', 'style'=>'color:#999;'),
                                ($row['expiration'] == "" ? $lang['rights_171'] : DateTimeRC::format_ts_from_ymd($row['expiration']))
                            )
                        );
                    }
				}
				elseif ($rightsKey == 'group_id') {
					// Display the DAG of this user
					if ($row['group_id'] == '') {
						$this_link_label = '&mdash;';
						$this_link_style = 'color:#999;';
					} else {
						$this_link_label = $dags[$row['group_id']];
						$this_link_style = 'color:#008000;';
					}
					if ($user_rights['group_id'] == '') {
                        if ($user_rights['data_access_groups'] == '1') {
                            $cellContent = RCView::div(array('class' => 'dagNameLinkDiv'),
                                RCView::a(array('href' => 'javascript:;', 'style' => $this_link_style, 'title' => $lang['rights_149'],
                                    'gid' => $row['group_id'], 'uid' => $this_username), $this_link_label)
                            );
                        } else {
                            $cellContent = 	RCView::div(array('class'=>'dagNameLinkDiv'),
                                RCView::span(array('style'=>$this_link_style), $this_link_label)
                            );
                        }
					} else {
						$cellContent = 	RCView::div(array('class'=>'dagNameLinkDiv', 'style'=>$this_link_style), $this_link_label);
					}
				}
				elseif ($rightsKey == 'realtime_webservice_mapping') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'realtime_webservice_adjudicate') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'data_entry') {
					$cellContent = self::convertFormRightsToText($row[$rightsKey]);
				}
				elseif ($rightsKey == 'data_export_tool') {
					$cellContent = self::convertFormRightsToText($row['data_export_instruments'], true);
				}
				elseif ($rightsKey == 'data_quality_resolution') {
					if ($row[$rightsKey] == "0") $cellContent = $imgNo;
					elseif ($row[$rightsKey] == "1") $cellContent = $lang['dataqueries_143'];
					elseif ($row[$rightsKey] == "4") $cellContent = $lang['dataqueries_289'];
					elseif ($row[$rightsKey] == "5") $cellContent = $lang['dataqueries_290'];
					elseif ($row[$rightsKey] == "2") $cellContent = $lang['dataqueries_138'];
					elseif ($row[$rightsKey] == "3") $cellContent = $lang['dataqueries_139'];
				}
				elseif ($rightsKey == 'double_data') {
					$cellContent = ($row[$rightsKey] > 0) ? 'DDE Person #'.$row[$rightsKey] : $lang['rights_51'];
				}
				elseif ($rightsKey == 'lock_record_customize') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
                elseif ($rightsKey == 'lock_record_multiform') {
                    $cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
                }
				elseif ($rightsKey == 'user_rights') {
					$cellContent = ($row[$rightsKey] > 0) ? ($row[$rightsKey] == '1' ? $imgYes : $imgReadonly) : $imgNo;
				}
				elseif ($rightsKey == 'lock_record') {
					$cellContent = ($row[$rightsKey] > 0) ? ($row[$rightsKey] == 1 ? $imgYes : $imgShield) : $imgNo;
				}
				elseif ($rightsKey == 'api') {
					// Set text
					$cellContent = [];
					if ($row["api_export"] == 1) $cellContent[] = RCView::tt("global_71");
					if ($row["api_import"] == 1) $cellContent[] = RCView::tt("global_72");
					if ($row["api_modules"] == 1) $cellContent[] = RCView::tt("global_142");
					$cellContent = count($cellContent) ? join(RCView::br(), $cellContent) : $imgNo;
				}
				elseif ($rightsKey == 'randomization') {
					if ($row['random_setup'] == 1) $cellContent .= $lang['rights_142'] . RCView::br();
					if ($row['random_dashboard'] == 1) $cellContent .= $lang['rights_143'] . RCView::br();
					if ($row['random_perform'] == 1) $cellContent .= $lang['rights_144'];
					if ($cellContent == '') $cellContent = $imgNo;
				}
				else {
					$cellContent = ($row[$rightsKey] == 1) ? $imgYes : $imgNo;
				}
				// Render table cell for this column
				$rows[$rowkey][] = RCView::div(array('class'=>'wrap'), $cellContent);
			}
			// Increment rowkey
			$rowkey++;
		}
		// Now add roles
		foreach ($roles as $role_id=>$row) {
			// Add to $rows array
			$rows[$rowkey] = array();
			// Loop through each column
			foreach ($rightsHdrs as $rightsKey => $r)
			{
				// If this column is not enabled, skip it
				if (!$r['enabled']) continue;
				// Initialize vars
				$cellContent = '';
				// Output column's content (depending on which column we're on)
				if ($rightsKey == 'username') {
					if (empty($row['role_users_assigned'])) {
						$this_role_userlist = RCView::div(array('style'=>'color:#aaa;font-size:11px;'),
												((($rightsAllUsers[USERID]['group_id'] ?? '') == '' || SUPER_USER) ? $lang['rights_151'] : $lang['rights_222'])
											  );
					} else {
						$these_username_names = array();
						$i = 0;
						foreach ($row['role_users_assigned'] as $this_user_assigned)
						{
							$isRoleSuspendedUser = in_array(strtolower($this_user_assigned), $suspendedUsers);
							// Set icon if has API token
							$apiIcon = ($rightsAllUsers[$this_user_assigned]['api_token'] == '' ? '' :
									RCView::span(array('class'=>'nowrap', 'style'=>'color:#A86700;font-size:11px;margin-left:8px;'),
										RCView::img(array('src'=>'coin.png', 'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'),
											$lang['control_center_333']
										)
									)
								);
							// Set text if user's account is suspended
							$suspendedText = (in_array(strtolower($this_user_assigned), $suspendedUsers))
											? RCView::span(array('class'=>'nowrap', 'style'=>'color:red;font-size:11px;margin-left:8px;'),
												$lang['rights_281']
											  )
											: "";
							$this_username_name = RCView::b(RCView::escape($this_user_assigned)) . ($rightsAllUsers[$this_user_assigned]['user_fullname'] == '' ? '' : " ({$rightsAllUsers[$this_user_assigned]['user_fullname']})");
							$these_username_names[] =
								RCView::div(array('class'=>'userNameLinkDiv', 'style'=>($i==0 ? '' : 'border-top:1px solid #eee;'), 'data-role-suspended-user' => ($isRoleSuspendedUser ? 1 : 0)),
									RCView::a(array('href'=>'javascript:;', 'class'=>'userLinkInTable text-primaryrc fs13', 'style'=>'vertical-align:middle;text-decoration:none;', 'title'=>$lang['rights_217'],
										                    'inrole'=>'1', 'userid'=>$this_user_assigned), $this_username_name) .
									$suspendedText . $apiIcon
								);
							$i++;
						}
						$this_role_userlist = implode("", $these_username_names);
					}
					$cellContent = 	RCView::div(array('style'=>'color:#800000;'),
										$this_role_userlist
									);
				}
				elseif ($rightsKey == 'role_name') {
					// Set different color for system-level roles
                    if ($user_rights['user_rights'] == '1' || SUPER_USER) {
                        $cellContent = RCView::a(array('href' => 'javascript:;', 'class' => 'text-dangerrc font-weight-bold fs13',
                            'title' => $lang['rights_152'], 'id' => 'rightsTableUserLinkId_' . $role_id),
                            RCView::escape($row['role_name'])
                        );
                    } else {
                        $cellContent = RCView::span(array('class' => 'text-dangerrc font-weight-bold fs13'),
                            RCView::escape($row['role_name'])
                        );
                    }
				}
				elseif ($rightsKey == 'expiration') {
					$these_rows = array();
					$i = 0;
					if(isset($row['role_users_assigned']))
					{
						foreach ($row['role_users_assigned'] as $this_user_assigned) {
							$this_expiration = $rightsAllUsers[$this_user_assigned]['expiration'];
							$this_class = ($this_expiration == ""
							? 'userRightsExpireN'
							: (str_replace("-","",$this_expiration) < date('Ymd')
								? 'userRightsExpired'
								: 'userRightsExpire'));
							$these_rows[] =
								RCView::div(array('class'=>'expireLinkDiv', 'style'=>($i==0 ? '' : 'border-top:1px solid #eee;')),
									RCView::a(array('href'=>'javascript:;', 'class'=>$this_class, 'title'=>$lang['rights_201'],
									'userid'=>$this_user_assigned,
										'expire'=>($this_expiration == "" ? "" : DateTimeRC::format_ts_from_ymd($this_expiration))),
										($this_expiration == "" ? $lang['rights_171'] : DateTimeRC::format_ts_from_ymd($this_expiration))
									)
								);
							$i++;
						}
					}
					$cellContent = implode("", $these_rows);
				}
				elseif ($rightsKey == 'group_id') {
					// Display the DAGs of all users in this role
					$these_dagnames = array();
					$i = 0;
					if(isset($row['role_users_assigned']))
					{
						foreach ($row['role_users_assigned'] as $this_user_assigned) {
							$this_group_id = $rightsAllUsers[$this_user_assigned]['group_id'];
							if ($rightsAllUsers[$this_user_assigned]['group_id'] == '') {
								$this_link_label = '&mdash;';
								$this_link_style = 'color:#999;';
							} else {
								$this_link_label = $dags[$this_group_id];
								$this_link_style = 'color:#008000;';
							}
							if ($user_rights['group_id'] == '') {
								$these_dagnames[] = RCView::div(array('class'=>'dagNameLinkDiv', 'style'=>($i==0 ? '' : 'border-top:1px solid #eee;')),
														RCView::a(array('href'=>'javascript:;', 'style'=>$this_link_style, 'title'=>$lang['rights_149'],
														'gid'=>$this_group_id, 'uid'=>$this_user_assigned), $this_link_label)
								);
							} else {
								$these_dagnames[] = RCView::div(array('class'=>'dagNameLinkDiv', 'style'=>$this_link_style.($i==0 ? '' : 'border-top:1px solid #eee;')),
								$this_link_label
								);
							}
							$i++;
						}
					}
					$cellContent = implode("", $these_dagnames);
				}
				elseif ($rightsKey == 'realtime_webservice_mapping') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'realtime_webservice_adjudicate') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'data_entry') {
					$cellContent = self::convertFormRightsToText($row[$rightsKey]);
				}
				elseif ($rightsKey == 'data_export_tool') {
					$cellContent = self::convertFormRightsToText($row['data_export_instruments'], true);
				}
				elseif ($rightsKey == 'data_quality_resolution') {
					if ($row[$rightsKey] == "0") $cellContent = $imgNo;
					elseif ($row[$rightsKey] == "1") $cellContent = $lang['dataqueries_143'];
					elseif ($row[$rightsKey] == "4") $cellContent = $lang['dataqueries_289'];
					elseif ($row[$rightsKey] == "5") $cellContent = $lang['dataqueries_290'];
					elseif ($row[$rightsKey] == "2") $cellContent = $lang['dataqueries_138'];
					elseif ($row[$rightsKey] == "3") $cellContent = $lang['dataqueries_139'];
				}
				elseif ($rightsKey == 'double_data') {
					$cellContent = ($row[$rightsKey] > 0) ? 'DDE Person #'.$row[$rightsKey] : $lang['rights_51'];
				}
				elseif ($rightsKey == 'lock_record_customize') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
                elseif ($rightsKey == 'lock_record_multiform') {
                    $cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
                }
				elseif ($rightsKey == 'user_rights') {
					$cellContent = ($row[$rightsKey] > 0) ? ($row[$rightsKey] == '1' ? $imgYes : $imgReadonly) : $imgNo;
				}
				elseif ($rightsKey == 'lock_record') {
					$cellContent = ($row[$rightsKey] > 0) ? (($row[$rightsKey] == 1) ? $imgYes : $imgShield) : $imgNo;
				}
				elseif ($rightsKey == 'api') {
					$cellContent = [];
					if ($row["api_export"] == 1) $cellContent[] = RCView::tt("global_71");
					if ($row["api_import"] == 1) $cellContent[] = RCView::tt("global_72");
					if ($row["api_modules"] == 1) $cellContent[] = RCView::tt("global_142");
					$cellContent = count($cellContent) ? join(RCView::br(), $cellContent) : $imgNo;
				}
				elseif ($rightsKey == 'randomization') {
					if ($row['random_setup'] == 1) $cellContent .= $lang['rights_142'] . RCView::br();
					if ($row['random_dashboard'] == 1) $cellContent .= $lang['rights_143'] . RCView::br();
					if ($row['random_perform'] == 1) $cellContent .= $lang['rights_144'];
					if ($cellContent == '') $cellContent = $imgNo;
				}
                elseif ($rightsKey == 'role_id') {
                    $cellContent = $role_id;
                }
                elseif ($rightsKey == 'unique_role_name') {
                    $cellContent = $row['unique_role_name'];
                }
				else {
					$cellContent = ($row[$rightsKey] == 1) ? $imgYes : $imgNo;
				}
				// Render table cell for this column
				$rows[$rowkey][] = RCView::div(array('class'=>'wrap'), $cellContent);
			}
			// Increment rowkey
			$rowkey++;
		}
		
		// Set disabled attribute for input and button for adding new users if current user is in a DAG
		$addUserDisabled = ($user_rights['group_id'] == '') ? '' : 'disabled';

		// Create "add new user" text box
		$usernameTextboxJsFocus = "$('#new_username_assign').val('".js_escape($lang['rights_421'])."').css('color','#999');
									if ($(this).val() == '".js_escape($lang['rights_154'])."') {
									$(this).val(''); $(this).css('color','#000');
								  }";
		$usernameTextboxJsBlur = "$(this).val( trim($(this).val()) );
								  if ($(this).val() == '') {
									$(this).val('".js_escape($lang['rights_154'])."'); $(this).css('color','#999');
								  }";
		$usernameTextbox = RCView::text(array('id'=>'new_username', $addUserDisabled=>$addUserDisabled, 'class'=>'x-form-text x-form-field', 'maxlength'=>'255',
							'style'=>'margin-left:4px;width:300px;color:#999;font-size:13px;padding-top:0;','value'=>$lang['rights_154'],
							'onkeydown'=>"if(event.keyCode==13) $('#addUserBtn').click();",
							'onfocus'=>$usernameTextboxJsFocus,'onblur'=>$usernameTextboxJsBlur));

		// Create "assign new user" text box
		$usernameTextboxJsFocusAssign = "$('#new_username').val('".js_escape($lang['rights_154'])."').css('color','#999');
										 if ($(this).val() == '".js_escape($lang['rights_421'])."') {
											$(this).val(''); $(this).css('color','#000');
										  }";
		$usernameTextboxJsBlurAssign =  "$(this).val( trim($(this).val()) );
										  if ($(this).val() == '') {
											$(this).val('".js_escape($lang['rights_421'])."'); $(this).css('color','#999');
										  } else {
											userAccountExists($(this).val());
										  }";
		$usernameTextboxAssign = RCView::text(array('id'=>'new_username_assign', $addUserDisabled=>$addUserDisabled, 'class'=>'x-form-text x-form-field', 'maxlength'=>'255',
							'style'=>'margin-left:4px;width:300px;color:#999;font-size:13px;padding-top:0;','value'=>$lang['rights_421'],
							'onkeydown'=>"if(event.keyCode==13) { $('#assignUserBtn').click(); userAccountExists($(this).val()); }",
							'onfocus'=>$usernameTextboxJsFocusAssign,'onblur'=>$usernameTextboxJsBlurAssign));

		// Create "new role name" text box
		$userroleTextboxJsFocus = "if ($(this).val() == '".js_escape($lang['rights_155'])."') {
									$(this).val(''); $(this).css('color','#000');
								  }";
		$userroleTextboxJsBlur = "$(this).val( trim($(this).val()) );
								  if ($(this).val() == '') {
									$(this).val('".js_escape($lang['rights_155'])."'); $(this).css('color','#999');
								  }";
		$userroleTextbox = RCView::text(array('id'=>'new_rolename', 'class'=>'x-form-text x-form-field', 'maxlength'=>'150',
							'style'=>'margin-left:4px;width:300px;color:#999;font-size:13px;padding-top:0;font-weight:normal;','value'=>$lang['rights_155'],
							'onkeydown'=>"if(event.keyCode==13) $('#createRoleBtn').click();",
							'onfocus'=>$userroleTextboxJsFocus,'onblur'=>$userroleTextboxJsBlur));

		$csrf_token = System::getCsrfToken();
        // Import/Export buttons divs
        $buttons = RCView::div(array('style'=>'text-align:right; font-size:12px;font-weight:normal;max-width:900px; '),
            RCView::button(array('onclick'=>"showBtnDropdownList(this,event,'downloadUploadUsersDropdownDiv');", 'class'=>'btn btn-xs fs13 btn-defaultrc'),
                RCView::img(array('src'=>'xls.gif')) .
                $lang['rights_376'] .
                RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:2px;vertical-align:middle;position:relative;top:-1px;'))
            ) .
            RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"showUploadDownloadCSVHelp();"), $lang['questionmark']) .
            // Button/drop-down options (initially hidden)
            RCView::div(array('id'=>'downloadUploadUsersDropdownDiv', 'style'=>'text-align:left;display:none;position:absolute;z-index:1;'),
                RCView::ul(array('id'=>'downloadUploadUsersDropdown'),
                    RCView::li(array(),
                        RCView::a(array('href'=>'javascript:;', 'style'=>'color:#8A5502;', 'onclick'=>"simpleDialog(null,null,'importUsersDialog',500,null,'".js_escape($lang['calendar_popup_01'])."',\"$('#importUserForm').submit();\",'".js_escape($lang['design_530'])."');$('.ui-dialog-buttonpane button:eq(1)',$('#importUsersDialog').parent()).css('font-weight','bold');"),
                            RCView::img(array('src'=>'arrow_up_sm_orange.gif')) .
                            RCView::SP . $lang['rights_377']
                        )
                    ) .
                    RCView::li(array(),
                        RCView::a(array('href'=>'javascript:;', 'style'=>'color:#8A5502;', 'onclick'=>"window.location.href = app_path_webroot+'UserRights/import_export_users.php?action=download&pid='+pid;"),
                            RCView::img(array('src'=>'arrow_down_sm_orange.gif')) .
                            RCView::SP . $lang['rights_378']
                        )
                    ) .
                    RCView::li(array(),
                        RCView::a(array('href'=>'javascript:;', 'style'=>'color:#333;', 'onclick'=>"simpleDialog(null,null,'importRolesDialog',500,null,'".js_escape($lang['calendar_popup_01'])."',\"$('#importRoleForm').submit();\",'".js_escape($lang['design_530'])."');$('.ui-dialog-buttonpane button:eq(1)',$('#importRolesDialog').parent()).css('font-weight','bold');"),
                            RCView::img(array('src'=>'arrow_up_sm.gif')) .
                            RCView::SP . $lang['rights_407']
                        )
                    ) .
                    RCView::li(array(),
                        RCView::a(array('href'=>'javascript:;', 'style'=>'color:#333;', 'onclick'=>"window.location.href = app_path_webroot+'UserRights/import_export_roles.php?action=download&pid='+pid;"),
                            RCView::img(array('src'=>'arrow_down_sm.png')) .
                            RCView::SP . $lang['rights_408']
                        )
                    ).
                    RCView::li(array(),
                        RCView::a(array('href'=>'javascript:;', 'style'=>'color:#8A5502;', 'onclick'=>"simpleDialog(null,null,'importUserRoleDialog',500,null,'".js_escape($lang['calendar_popup_01'])."',\"$('#importUserRoleForm').submit();\",'".js_escape($lang['design_530'])."');$('.ui-dialog-buttonpane button:eq(1)',$('#importRolesDialog').parent()).css('font-weight','bold');"),
                            RCView::img(array('src'=>'arrow_up_sm_orange.gif')) .
                            RCView::SP . $lang['rights_415']
                        )
                    ) .
                    RCView::li(array(),
                        RCView::a(array('href'=>'javascript:;', 'style'=>'color:#8A5502;', 'onclick'=>"window.location.href = app_path_webroot+'UserRights/import_export_roles.php?action=downloadMapping&pid='+pid;"),
                            RCView::img(array('src'=>'arrow_down_sm_orange.gif')) .
                            RCView::SP . $lang['rights_416']
                        )
                    )
                )
            )
        );

        $notify_email_html = "<img src='".APP_PATH_IMAGES."email.png'>&nbsp;&nbsp;{$lang['rights_112']}
									&nbsp;<input type='checkbox' name='notify_email' value='1' checked>";
        // Hidden import dialog divs
        $hiddenImportDialog = RCView::div(array('id'=>'importUsersDialog', 'class'=>'simpleDialog', 'title'=>$lang['rights_377']),
            RCView::div(array(), $lang['rights_379']) .
            RCView::div(array('style'=>'margin-top:15px;margin-bottom:5px;font-weight:bold;'), $lang['rights_380']) .
            RCView::form(array('id'=>'importUserForm', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'UserRights/import_export_users.php?pid=' . PROJECT_ID),
                RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
                RCView::input(array('type'=>'file', 'name'=>'file'))
            )
        );

        $hiddenImportDialog .= RCView::div(array('id' => 'importUsersDialog2', 'class' => 'simpleDialog', 'title' => $lang['rights_377'] . " - " . $lang['design_654']),
            RCView::div(array(), $lang['api_125']) .
            RCView::form(array('id' => 'importUsersForm2', 'enctype' => 'multipart/form-data', 'method' => 'post', 'action' => APP_PATH_WEBROOT . 'UserRights/import_export_users.php?pid=' . PROJECT_ID),
                RCView::input(array('type' => 'hidden', 'name' => 'redcap_csrf_token', 'value' => $csrf_token)) .
                RCView::div(array('id' => 'notifyUsers', 'style' => 'display: none; margin:15px 0;'), $notify_email_html) .
                RCView::textarea(array('name' => 'csv_content', 'style' => 'display:none;'), (isset($_SESSION['csv_content']) ? htmlspecialchars($_SESSION['csv_content'], ENT_QUOTES) : ""))
            ) .
            RCView::div(array('id' => 'user_preview', 'style' => 'margin:15px 0'), '')
        );

        $hiddenImportDialog .= RCView::div(array('id'=>'importRolesDialog', 'class'=>'simpleDialog', 'title'=>$lang['rights_407']),
            RCView::div(array(), $lang['rights_409']) .
            RCView::div(array('style'=>'margin-top:15px;margin-bottom:5px;font-weight:bold;'), $lang['rights_410']) .
            RCView::form(array('id'=>'importRoleForm', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'UserRights/import_export_roles.php?pid=' . PROJECT_ID),
                RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
                RCView::input(array('type'=>'file', 'name'=>'file'))
            )
        );

        $hiddenImportDialog .= RCView::div(array('id' => 'importRolesDialog2', 'class' => 'simpleDialog', 'title' => $lang['rights_407'] . " - " . $lang['design_654']),
            RCView::div(array('id' => 'rolesInstr'), $lang['api_125']) .
            RCView::div(array('style' => 'display:none;', 'class' => 'error', 'id' => 'noRoleChangesFound'), "<b>".$lang['api_docs_094']."</b>".$lang['database_mods_76']) .
            RCView::form(array('id' => 'importRolesForm2', 'enctype' => 'multipart/form-data', 'method' => 'post', 'action' => APP_PATH_WEBROOT . 'UserRights/import_export_roles.php?pid=' . PROJECT_ID),
                RCView::input(array('type' => 'hidden', 'name' => 'redcap_csrf_token', 'value' => $csrf_token)) .
                RCView::textarea(array('name' => 'csv_content', 'style' => 'display:none;'), (isset($_SESSION['csv_content']) ? htmlspecialchars($_SESSION['csv_content'], ENT_QUOTES) : ""))
            ) .
            RCView::div(array('id' => 'role_preview', 'style' => 'margin:15px 0'), '')
        );

        $hiddenImportDialog .= RCView::div(array('id'=>'importUserRoleDialog', 'class'=>'simpleDialog', 'title'=>$lang['rights_415']),
            RCView::div(array(), $lang['rights_418']) .
            RCView::div(array('style'=>'margin-top:15px;margin-bottom:5px;font-weight:bold;'), $lang['rights_417']) .
            RCView::form(array('id'=>'importUserRoleForm', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'UserRights/import_export_roles.php?action=uploadMapping&pid=' . PROJECT_ID),
                RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
                RCView::input(array('type'=>'file', 'name'=>'file'))
            )
        );
        $hiddenImportDialog .= RCView::div(array('id'=>'importUserRoleDialog2', 'class'=>'simpleDialog', 'title'=>$lang['rights_415']." - ".$lang['design_654']),
            RCView::div(array('id' => 'userRolesInstr'), $lang['api_125']) .
            RCView::div(array('style' => 'display:none;', 'class' => 'error', 'id' => 'noMappingChangesFound'), "<b>".$lang['api_docs_094']."</b>".$lang['database_mods_76']) .
            RCView::div(array('id'=>'user_role_preview', 'style'=>'margin:15px 0'), '') .
            RCView::form(array('id'=>'importUserRoleForm2', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'UserRights/import_export_roles.php?action=uploadMapping&pid=' . PROJECT_ID),
                RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
                RCView::textarea(array('name'=>'csv_content', 'style'=>'display:none;'), (isset($_SESSION['csv_content']) ? htmlspecialchars($_SESSION['csv_content'], ENT_QUOTES) : ""))
            )
        );
		// Set html before the table
        $html = "";
        if ($user_rights['user_rights'] == '1')
        {
            $html = RCView::div(array('id' => 'addUsersRolesDiv', 'style' => 'margin:20px 0 30px;font-size:12px;font-weight:normal;padding:10px;border:1px solid #ccc;background-color:#eee;max-width:850px;'),
                RCView::div(array('style' => 'color:#444;margin-bottom:3px;'),
                    $buttons
                ) .
                // Add new user with custom rights
                RCView::div(array('style' => ($user_rights['group_id'] == '' ? 'color:#444;' : 'color:#aaa;') . 'padding-top:10px;'),
                    //If user is in DAG, only show info from that DAG and give note of that
                    ($user_rights['group_id'] == "" ? '' :
                        RCView::div(array('style' => 'color:#C00000;margin-bottom:10px;'), "{$lang['global_02']}{$lang['colon']} {$lang['rights_92']}")
                    ) .
                    RCView::span(array('style' => ($user_rights['group_id'] == '' ? 'color:#000;' : 'color:#aaa;') . 'font-weight:bold;font-size:13px;margin-right:5px;'), $lang['rights_168']) .
                    " " . $lang['rights_162']
                ) .
                RCView::div(array('style' => 'margin:8px 0 0 29px;'),
                    $usernameTextbox .
                    // Add User button
                    RCView::button(array('id' => 'addUserBtn', $addUserDisabled => $addUserDisabled, 'class' => 'btn btn-xs fs13 btn-rcgreen'), RCView::fa('fas fa-plus me-1') . $lang['rights_165'])
                ) .
                // - OR -
                RCView::div(array('style' => 'margin:2px 0 1px 60px;color:#999;'),
                    "&#8212; {$lang['global_46']} &#8212;"
                ) .
                // Add new user - assign to role
                RCView::div(array('style' => 'margin:0 0 0 29px;'),
                    $usernameTextboxAssign .
                    // Assign User button
                    RCView::button(array('id' => 'assignUserBtn', $addUserDisabled => $addUserDisabled, 'class' => 'btn btn-xs fs13 btn-rcgreen', 'style' => 'margin-top:2px;'),
                        RCView::fa('fas fa-user-tag me-1') .
                        $lang['rights_156'] .
                        RCView::img(array('src' => 'arrow_state_grey_expanded.png', 'style' => 'margin-left:5px;vertical-align:middle;position:relative;top:-1px;'))
                    )
                ) .
                // Create new user role
                RCView::div(array('style' => 'margin:20px 0 0;color:#444;'),
                    RCView::span(array('style' => 'font-weight:bold;font-size:13px;color:#000;margin-right:5px;'), $lang['rights_170']) .
                    " " . $lang['rights_169']
                ) .
                RCView::div(array('style' => 'margin:8px 0 0 29px;font-weight:bold;color:#2C5178;'),
                    $userroleTextbox .
                    RCView::button(array('id' => 'createRoleBtn', 'class' => 'btn btn-xs fs13 btn-primaryrc'),
                        RCView::fa('fas fa-plus me-1') . $lang['rights_158'])
                ) .
                RCView::div(array('style' => 'margin:2px 0 0 35px;font-size:10px;color:#999;'),
                    $lang['rights_218']
                )
            );
        }

        // Display the show/hide suspended users button
        if (!empty($suspendedUsers))
        {
            $showSuspendedUsersUI = UIState::getUIStateValue(null, "user-rights", "show_suspended_users");
            $showSuspendedUsers = ($showSuspendedUsersUI != 'hide');
            // Show suspended
            $html .= RCView::div(array('class' => 'mt-0 mb-3'),
                RCView::button(array('id'=>'hideSusUsersBtn', 'style'=>'display:'.($showSuspendedUsers?'':'none').';', 'class'=>'btn btn-xs btn-outline-danger mb-2'), $lang['rights_433'] . " " . count($suspendedUsers) . " " . $lang['rights_434']) .
                RCView::button(array('id'=>'showSusUsersBtn', 'style'=>'display:'.($showSuspendedUsers?'none':'').';', 'class'=>'btn btn-xs btn-outline-danger mb-2'), $lang['rights_432'] . " " . count($suspendedUsers) . " " . $lang['rights_434'])
            );
        }

        $html .= $hiddenImportDialog;
        $html .= RCView::div(['id'=>'downloadUploadRightsDialogHelp', "style"=>"display:none;"], "");

		// Create SELECT BOX OF USER ROLES to choose from
        $all_roles = array();
        foreach ($roles as $role_id=>$attr) {
            $all_roles[$role_id] = $attr['role_name'];
        }

        $roles_options = (!empty($all_roles)) ? RCView::select(array('id'=>'user_role', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 10px 0 6px;'),
                                            (array(''=>"{$lang['rights_399']}") + $all_roles), '')
                                            : "";

        $groups = $Proj->getGroups();
		$dags_options = (!empty($groups)) ? RCView::select(array('id'=>'user_dag', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 10px 0 6px;'),
                                        (array(''=>"[{$lang['data_access_groups_ajax_16']}]") + $groups), '')
                                          : "";
		$html .= RCView::div(array('id'=>'assignUserDropdownDiv', 'style'=>'display:none;position:absolute;z-index:22;'),
					RCView::div(array('id'=>'notify_email_role_option', 'style'=>'color:#555;font-size:11px;padding:3px;border:1px solid #aaa;border-bottom:0;background-color:#eee;', 'ignore'=>'1'),
						"<img src='".APP_PATH_IMAGES."mail_small2.png' style='vertical-align:middle;position:relative;top:-2px;'> {$lang['rights_315']}
						&nbsp;<input type='checkbox' id='notify_email_role' name='notify_email_role' checked>"
					) .
                    (($dags_options != '') ? RCView::div(array('id'=>'dag_option', 'style'=>'display:none;color:#555;font-size:11px;padding:3px;border:1px solid #aaa;border-bottom:0;background-color:#eee;', 'ignore'=>'1', 'title'=>$lang['rights_397']),
                        "<i class='fas fa-user-tag me-1'></i> {$lang['rights_398']}
						&nbsp;".$dags_options
                    ) : '') .
                    (($roles_options != '') ? RCView::div(array('id'=>'roles_option', 'style'=>'color:#555;font-size:11px;padding:3px;border:1px solid #aaa;border-bottom:0;background-color:#eee;', 'ignore'=>'1'),
                        "<i class='fas fa-user-plus me-1'></i> {$lang['data_access_groups_ajax_33']}:
						&nbsp;".$roles_options
                    ) : '').
                    RCView::div(array('style'=>'padding:3px; border:1px solid #aaa;background-color:#eee; text-align:right;'),
                        // Assign DAG/Role
                        RCView::button(array('id'=>'assignDagRoleBtn', 'class'=>'jqbuttonmed'), $lang['rights_181']) .
                        RCView::a(array('id'=>'tooltipRoleCancel', 'href'=>'javascript:;', 'style'=>'margin-left:2px;color:#333;font-size:11px;text-decoration:underline;', 'onclick'=>"$('#userClickDagName').hide();"), $lang['global_53'])
                    )
				);


		// TOOLTIP div when CLICK USERNAME IN TABLE
		$admin_only_goto_user = "";
		if (UserRights::isSuperUserNotImpersonator()) {
			$admin_only_goto_user = RCView::button([
					"class" => "float-right btn btn-link btn-xs fs12 pr-0 pt-0",
					"style" => "color:#68caf9;",
					"onclick" => "navigateToUserInfo($('#tooltipHiddenUsername').val());",
					"title" => RCView::tt_attr("rights_447"),
				],
				RCView::fa("fa-solid fa-id-card mr-1").RCView::tt('global_84')
			);
		}
		$html .= RCView::div(array('id'=>'userClickTooltip'),
					RCView::div(array('class'=>'font-weight-bold fs13 mb-2'), 
						RCView::tt("rights_172") . 
						$admin_only_goto_user
					) .
					// Set custom rights button
					RCView::div(array('id'=>'tooltipBtnSetCustom', 'style'=>'padding-bottom:4px;'),
						RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs13 text-primaryrc', 'onclick'=>"openAddUserPopup( $('#tooltipHiddenUsername').val());"),
                            '<i class="fa-solid fa-user-edit me-1"></i>'.RCView::tt("rights_153")
                        )
					) .
					// Assign User button
					RCView::div(array('id'=>'tooltipBtnAssignRole', 'style'=>'padding-bottom:4px;'),
						RCView::button(array('id'=>'assignUserBtn2', 'class'=>'btn btn-xs btn-defaultrc fs13 text-successrc'),
                            '<i class="fa-solid fa-user-tag me-1"></i>'.RCView::tt("rights_156") .
							RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:5px;'))
						)
					) .
					// Re-assign User button
					RCView::div(array('id'=>'tooltipBtnReassignRole', 'style'=>'padding-bottom:4px;'),
						RCView::button(array('id'=>'assignUserBtn3', 'class'=>'btn btn-xs btn-defaultrc fs13 nowrap text-primaryrc'),
							'<i class="fa-solid fa-user-tag me-1"></i>'.RCView::tt("rights_173") .
							RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:5px;'))
						)
					) .
                    // Remove from Role button
                    RCView::div(array('id'=>'tooltipBtnRemoveRole', 'style'=>'padding-bottom:4px;'),
                        RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs13 text-dangerrc', 'onclick'=>"assignUserRole( $('#tooltipHiddenUsername').val(),0)"),
                            '<i class="fa-solid fa-user-times me-1"></i>'.RCView::tt("rights_175")
                        )
                    ) .
                    // Remove from Project button
                    RCView::div(array('id'=>'tooltipBtnRemoveProject', 'style'=>'padding-bottom:4px;', "class" => "mt-1"),
                        RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs13 text-dangerrc', 'onclick'=>"removeUserFromProject( $('#tooltipHiddenUsername').val())"),
                            '<i class="fa-solid fa-user-slash me-1"></i>'.RCView::tt("rights_445")
                        )
                    ) .
					// Hidden input where username is store for the user just clicked, which opened this tooltip (so we know which was clicked)
					RCView::hidden(array('id'=>'tooltipHiddenUsername'))
				);

        Design::alertRecentImportStatus();

        $html .= "<script type='text/javascript'>
						$(function(){
						    $('#downloadUploadUsersDropdown').menu();
                            $('#downloadUploadUsersDropdownDiv ul li a').click(function(){
                                $('#downloadUploadUsersDropdownDiv').hide();
                            });
						});
				  </script>";
		// Return the html for displaying the table
		$html .= renderGrid("user_rights_roles_table", '', $tableWidth, "auto", $hdrs, $rows, true, true, false);

        // Hide suspended (if UIState dictates such)
        if (!empty($suspendedUsers) && !$showSuspendedUsers)
        {
            $html .= "<script type='text/javascript'>hideSusUsersRows();restripeUserTableRows();</script>";
        }

        return $html;
	}

	// Detect if a single user has User Rights privileges in *any* project (i.e. is a project owner) - includes roles that user is in
	public static function hasUserRightsPrivileges($user)
	{
		// Query to see if have User Rights privileges in at least one project (consider roles rights in this)
		$sql = "select 1 from redcap_user_rights u left join redcap_user_roles r
				on r.role_id = u.role_id where u.username = '".db_escape($user)."'
				and ((u.user_rights = 1 and r.user_rights is null) or r.user_rights = 1) limit 1";
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0);
	}

	// Detect if a single user's privileges have expired in a projecxt
	public static function hasUserRightsExpired($project_id, $user)
	{
		// Query to see if have User Rights privileges in at least one project (consider roles rights in this)
		$sql = "select 1 from redcap_user_rights where project_id = $project_id and username = '".db_escape($user)."' 
				and expiration is not null and expiration != '' and expiration <= '".TODAY."' limit 1";
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0);
	}
	
	// External Modules: Display project menu link only to super users or to users with Design Setup 
	// rights *if* one or more modules are already enabled *or* if at least one module has been set as "discoverable" in the system
	public static function displayExternalModulesMenuLink()
	{
		global $user_rights;
		// If Ext Mods not enabled, do not display
		if (!defined("APP_PATH_EXTMOD")) return false;
		// Always show the link to admins
		if (UserRights::isSuperUserNotImpersonator() || \ExternalModules\ExternalModules::isAdminWithModuleInstallPrivileges()) return true;
		// Check if project has any modules enabled or if any modules are discoverable
		$systemHasDiscoverableModules = (method_exists('\ExternalModules\ExternalModules', 'hasDiscoverableModules') 
										&& \ExternalModules\ExternalModules::hasDiscoverableModules());
		$enabledModules = \ExternalModules\ExternalModules::getEnabledModules(defined("PROJECT_ID") ? PROJECT_ID : null);
		$projectHasModulesEnabled = !empty($enabledModules);
		// If the project doesn't have modules enabled AND system doesn't have any discoverable modules, then don't show
		if (!$projectHasModulesEnabled && !$systemHasDiscoverableModules) {
			return false;
		}
		// If user has Design/Setup rights AND project has modules enabled or modules are discoverable, then show
		if (isset($user_rights['design']) && $user_rights['design'] == '1') {
			return true;
		}
		// Determine if user has permission to configure at least one module in this project
		foreach ($enabledModules as $moduleDirectoryPrefix=>$moduleVersion) {
			$thisConfigUserPerm = \ExternalModules\ExternalModules::getSystemSetting($moduleDirectoryPrefix, \ExternalModules\ExternalModules::KEY_CONFIG_USER_PERMISSION);
			$userHasConfigPermissions = ($thisConfigUserPerm != '' && $thisConfigUserPerm != false);
			// User has permission to configure module
			if ($userHasConfigPermissions && isset($user_rights['external_module_config'])
                && is_array($user_rights['external_module_config']) && in_array($moduleDirectoryPrefix, $user_rights['external_module_config'])
            ) {
				return true;
			}
		}
		// Return false if we got this far
		return false;
	}
	
	// External Modules: Display checkbox for each enabled module in a project in the Edit User dialog on the User Rights page
	public static function getExternalModulesUserRightsCheckboxes()
	{
		// If Ext Mods not enabled, do not display
		if (!defined("APP_PATH_EXTMOD")) return false;
		if (!method_exists('\ExternalModules\ExternalModules', 'getModulesWithCustomUserRights')) return false;
		// Get array of all enabled modules with attributes
		return \ExternalModules\ExternalModules::getModulesWithCustomUserRights(PROJECT_ID);
	}

	// Render the Impersonate User drop-down for admins
	public static function renderImpersonateUserDropDown()
	{
		global $lang;
		if (!self::isSuperUserOrImpersonator()) return '';
		$selected = '';
		if (defined("PROJECT_ID") && isset($_SESSION['impersonate_user'][PROJECT_ID])) {
			$selected = $_SESSION['impersonate_user'][PROJECT_ID]['impersonating'];
		}
		// Get the current user's username
		$currentUser = defined("PROJECT_ID") && isset($_SESSION['impersonate_user'][PROJECT_ID]['impersonator']) ? $_SESSION['impersonate_user'][PROJECT_ID]['impersonator'] : USERID;
		// Remove the current user from this list of users so that they cannot choose themselves
		$options = UserRights::getUsersRoles();
		foreach ($options as $role=>$users) {
			foreach ($users as $key=>$val) {
				if ($key == $currentUser) {
					unset($options[$role][$key]);
				}
			}
			if (empty($options[$role])) {
				unset($options[$role]);
			}
		}
		if (count($options) == 1 && isset($options[$lang['rights_361']])) {
			$options = $options[$lang['rights_361']];
		}
		$blankValText = defined("PROJECT_ID") && isset($_SESSION['impersonate_user'][PROJECT_ID]['impersonator']) ? $lang['rights_368'] : $lang['rights_363'];
		$options = array(''=>$blankValText)+$options;
		// Render drop-down
		$dd = RCView::select(array('id'=>'impersonate-user-select', 'class'=>'x-form-text x-form-field fs11 py-0 ms-1', 'style'=>'max-width:150px;'), $options, $selected);
		$div = 	RCView::div(array('class'=>'fs11 nowrap boldish', 'style'=>'margin: 5px 0 2px;'),
					'<span style="position:relative;top:1px;"><i class="fas fa-user-tie" style="margin-left:2px;margin-right:6px;"></i>'.$lang['rights_362'].'</span>'.$dd
				);
		return $div;
	}

	public static function renderNavigateToPageWidget($project = false) {
		if (!self::isSuperUserNotImpersonator()) return "";
		addLangToJS([
			"control_center_4900",
		]);
		$pre = $project
			? '<div><span style="color:#000;margin:4px 0 1px;" class="nav-link">'
			: '<ul class="nav navbar-nav ml-auto"><li class="nav-item"><span style="color:#000;margin-top:4px;white-space:nowrap;" class="nav-link">';
		$post = $project 
			? '</span></div>' 
			: '</span></li></ul>';
		$style = $project
			? 'margin-right:2px;vertical-align:middle;'
			: 'margin-right:5px;vertical-align:middle;font-size:12px;';
		$title = $project
			? ''
			: RCView::tt_js2("control_center_4707");
		$placeholder = $project
			? RCView::tt_js2("control_center_4707")
			: 'PID'; 
		$width = $project
			? 'width:180px;height:19px;padding:1px 5px;margin-left:1px;'
			: 'width:46px;';
		$widget = '
			<i id="goto-page-help" class="fas fa-sign-in-alt" style="cursor:help;'.$style.'"></i>
			<form id="goto-page-form" action="javascript:void(0);" style="display:inline-block;"><input type="text" id="goto-page-input" class="x-form-text x-form-field fs11" style="'.$width.'" autocomplete="off" placeholder="'.$placeholder.'" title="'.$title.'"></form>
		';
		$styles = RCView::style(<<<END
			.goto-page-popover {
				--bs-popover-max-width: 700px;
				top: .5em !important;
			}
			.goto-page-popover .popover-arrow {
				translate: 0 -.5em;
			}
			.goto-page-popover .popover-header {
				background-color: var(--bs-gray-700);
				color: white;
			}
			.goto-page-popover p {
				margin: 0;
			}
			.goto-page-popover p.target {
				font-size: .8em;
				margin-bottom: 2px;
			}
			.goto-page-popover p.target a {
				font-size: inherit;
				cursor: pointer;
				font-family: inherit !important;
			}
			.goto-page-popover p.target a.no-link,
			.goto-page-popover p.target a.no-link:hover {
				cursor: default;
				text-decoration:none;
				color: var(--bs-gray-600);
			}
			.goto-page-popover p.target a:hover {
				text-decoration: underline;
				outline:none;
				color: red;
				font-family: inherit;
				font-size: inherit;
			}
			.goto-page-popover h4 {
				font-size: .9em;
				font-weight: 500;
				margin: 5px -5px;
				padding: 3px 8px;
				background-color: var(--bs-gray-200);
			}
			.goto-page-popover div.goto-table {
				display: flex;
				gap: 10px;
			}
			.goto-page-popover code {
				border: 2px lightgray solid;
				border-radius: 0.4em;
				line-height: normal;
				padding: 0.15em;
				margin: 0 0.1em;
			}
			.goto-page-popover div.goto-col {
				display: flex;
				flex-direction: column;
				padding: 5px;
			}
			#goto-page-input.invalid-target {
				outline: 2px red solid;
			}
		END);
		$goto = UserRights::getGotoTargets();
		foreach($goto as $realm => &$item) {
			foreach ($item as $_ => &$val) {
				$label = self::renderNavigateToPageWidget_addTags($val["label"]);
				$ini_label = $val["langkey"] ? RCView::getLangStringByKey($val["langkey"]) : strip_tags($label);
				if (strip_tags($label) != $ini_label) {
					$label .= " (".$ini_label.")";
				}
				$val["label"] = $label;
			}
		}
		$targets = [];
		foreach ($goto as $realm => $data) {
			$targets[$realm] = "";
			foreach ($data as $shortcut => $this_item) { 
				if ($shortcut != "") {
					$targets[$realm] .= self::renderNavigateToPageWidget_constructLink($realm, $shortcut, $this_item);
				}
			}
		}
		$html = 
			RCView::tt("control_center_4901", "p") .
			RCView::div(["class" => "goto-table"], 
				RCView::div(["class" => "goto-col"],
					RCView::tt("global_65", "h4") . // Project
					$targets["project"]
				) .
				RCView::div(["class" => "goto-col"],
					RCView::tt("global_07", "h4") . // Control Center
					$targets["cc"]
				) .
				RCView::div(["class" => "goto-col"],
					RCView::tt("pub_028", "h4") . // Other
					$targets[""]
				) 
			) .
			RCView::tt("control_center_4902", "p");
		$goto_map = array_map(function($cat) {
				// Remove label and key - not needed
				foreach ($cat as &$item) {
					unset($item["label"]);
					unset($item["langkey"]);
				}
				return $cat;
			}, self::getGotoTargets());
		$goto_map["cc"] = array_merge($goto_map["cc"], $goto_map[""]);
		unset($goto_map[""]);
		$config = json_encode([
			// Set 'debug' to true to enable console logging. 
			// No navigation will occur (to navigate, click on the link output to console).
			"debug" => false,
			"html" => $html,
			"map" => $goto_map,
		], JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE);
		$script = <<<END
			REDCapGotoPage = (() => {
				let config = {};
				const init = (data) => {
					config = data;
					config.input = $('#goto-page-input');
					config.trigger = $('#goto-page-help');
					config.popover = bootstrap.Popover.getOrCreateInstance(config.input.get(0), {
						html: true,
						title: lang.control_center_4900,
						content: config.html,
						sanitize: false,
						customClass: 'goto-page-popover',
						trigger: 'manual'
					});
					config.input.on('blur', () => close());
					config.input.on('shown.bs.popover', () => {
						config.input.trigger('focus')
					});
					config.trigger.on('click', () => help());
					// Keyboard support - CTRL-ALT-N
					document.addEventListener("keydown", function (e) {
						if ((e.ctrlKey || e.metaKey) && e.altKey && e.code == 'KeyN') {
							e.preventDefault();
							// Already focused? Then show help
							if (config.input.is(':focus')) {
								help();
							}
							else {
								config.input.trigger('focus');
							}
						}
						else if (e.target.id === 'goto-page-input') {
							// Prevent form submission
							if (e.key === 'Enter') {
								e.preventDefault();
								goto(config.input.val(), e.ctrlKey);
								return false;
							}

						}
					}, false);
					if (config.debug) console.log('GotoPageWidget:', config);
				};
				const goto = (dest, blank) => {
					config.input.removeClass('invalid-target');
					if (typeof dest != 'string' || dest.trim() == '') return;
					const parts = dest.trim().split(' ');
					let pid = parts[0] == 'cc' ? 'cc' : Number.parseInt(parts[0]);
					let key = parts.length >= 2 ? parts[1].trim().toLowerCase() : '';
					let record = parts.length == 3 ? parts[2].trim() : null;
					if (pid != 'cc' && isNaN(pid)) {
						key = parts[0].trim().toLowerCase();
						if (isinteger(window['pid'])) {
							pid = window['pid'];
							if (parts.length == 2) record = parts[1].trim();
						}
						else {
							pid = 'cc';
						}
					}
					// Construct URL
					const page = config.map[pid == 'cc' ? 'cc' : 'project'][key] ?? false;
					if (!page) {
						if (config.debug) console.log('Invalid destination:', dest);
						config.input.addClass('invalid-target');
						config.input.val(dest);
						return;
					}
					const rcv = page.path.substr(0, 1) == '/' ? '' : ('redcap_v' + redcap_version + '/');
					const url = new URL(app_path_webroot_full + rcv + page.path.substr(rcv == '' ? 1 : 0));
					if (pid != 'cc') {
						const pidName = typeof page.pid == 'undefined' ? 'pid' : page.pid;
						if (pidName !== null) {
							url.searchParams.append(pidName, pid);
						}
					}
					if (page.route) {
						url.searchParams.append('route', page.route);
					}
					if (page.record) {
						url.searchParams.append(page.record, record);
					}
					for (const param in (page.additional ?? {})) {
						url.searchParams.append(param, page.additional[param]);
					}
					// DQT Context
					if (pid == 'cc' && key == 'dqt') {
						const currentUrl = new URL(window.location);
						for (const name of currentUrl.searchParams.keys()) {
							let param = '';
							switch (name) {
								case 'pid': param = 'project-id'; break;
								case 'id': param = 'record-name'; break;
								case 'page': param = 'instrument-name'; break;
								case 'event_id': param = 'event-id'; break;
								case 'instance': param = 'current-instance'; break;
							}
							if (param) url.searchParams.append(param, currentUrl.searchParams.get(name));
						}
						if (window['page'] && window['page'] == 'Logging/index.php') {
							url.searchParams.append('table', 'redcap_log_event');
						}
					}
					if (config.debug) {
						console.log('Goto [' + dest + '] -> ', pid, key, record, url.toString());
					}
					else {
						// Navigate
						if (blank) {
							config.input.val('');
							window.open(url.toString(), '_blank');
						}
						else {
							window.location.href = url.toString();
						}
					}
					return false;
				};
				const link = (e, dest) => {
					goto(dest, e.ctrlKey);
				};
				const close = () => {
					if (!config.debug) config.popover.hide();
				};
				const help = () => {
					const action = config.popover.tip ? 'hide' : 'show';
					config.popover[action]();
				};
				return {
					init: init,
					goto: goto,
					link: link,
					help: help
				};
			})();
			REDCapGotoPage.init($config);
END;
		return $pre.$widget.$post.$styles.RCView::script($script);
	}

	private static function renderNavigateToPageWidget_addTags($s, $tag = "u") {
		$result = [];
		$esc = false;
		$len =  mb_strlen($s);
		for ($i = 0; $i < $len; $i++) {
			$c = mb_substr($s, $i, 1);
			if ($c === "\\") {
				if ($esc) {
					$result[] = "\\";
				}
				else {
					$esc = true;
				}
				continue;
			}
			if ($c === "_") {
				if ($esc) {
					$result[] = "_";
					$esc = false;
				}
				else if ($i < $len - 1) {
					$result[] = "<$tag>".mb_substr($s, $i + 1, 1)."</$tag>";
					$i++;
				}
				continue;
			}
			$result[] = $c;
		}
		$result = join("", $result);
		do {
			$len = mb_strlen($result);
			$result = str_replace("</$tag><$tag>", "", $result);
		} while ($len > mb_strlen($result));
		return $result;
	}
	
	private static function renderNavigateToPageWidget_constructLink($realm, $shortcut, $item) {
		$in_project = defined("PROJECT_ID");
		$prefix = "";
		if ($realm == "project" && !$in_project) $prefix = "?? ";
		if ($realm == "cc" && $in_project) $shortcut = "cc ".$shortcut;
		$attr = ($item["no-link"] ?? false) || ($realm == "project" && !defined("PROJECT_ID")) 
			? "class=\"no-link\"" 
			: "href=\"javascript:;\" onclick=\"REDCapGotoPage.link(event, '$shortcut');\"";
		$html = "<p class=\"target\">$prefix<b>$shortcut</b> &ndash; " .
			"<a $attr>{$item["label"]}</a>" . 
			"</p>";
		return $html;
	}
	
	

	public static function getGotoTargets() {
		$em_ext = defined('EXTMOD_EXTERNAL_INSTALL') && EXTMOD_EXTERNAL_INSTALL;
		// Format (keys): shortcut => [ path, route, pid, record, additional ]
		// "path" is required (use leading / to omit REDCap version folder)
		// "route", when present, is the route parameter
		// "pid" (optional, projects only) will insert the project id and is assumed to be "pid" unless overridden
		// "record" (project only), when present gives the name of the query param for the record id
		// "additional" holds any other querey params (key-value pairs)
		return [
			// Project-specific targets
			"project" => [
				"" => [
					"label" => "_Project _Home",
					"langkey" => "bottom_44",
					"path" => "index.php"
				],
				"aer" => [
					"label" => "_Add / _Edit _Records",
					"langkey" => "bottom_62",
					"path" => "DataEntry/record_home.php",
				],
				"an" => [
					"label" => "_Alerts & _Notifications",
					"langkey" => "global_154",
					"path" => "index.php", 
					"route" => "AlertsController:setup"
				],
				"api" => [
					"label" => "_A_P_I",
					"langkey" => "setup_77",
					"path" => "API/project_api.php"
				],
				"apg" => [
					"label" => "_API _Play_ground",
					"langkey" => "setup_143",
					"path" => "API/playground.php"
				],
				"cal" => [
					"label" => "_C_a_lendar",
					"langkey" => "app_08",
					"path" => "Calendar/index.php"
				],
				"cb" => [
					"label" => "Data Dictionary _Code_book",
					"langkey" => "global_116",
					"path" => "Design/data_dictionary_codebook.php"
				],
				"cc" => [
					"label" => "_Control _Center",
					"langkey" => "global_07",
					"path" => "ControlCenter/index.php",
					"pid" => null
				],
				"dag" => [
					"label" => "_Data _Access _Groups",
					"langkey" => "global_22",
					"path" => "index.php",
					"route" => "DataAccessGroupsController:index"
				],
				"dd" => [
					"label" => "_Data _Dictionary",
					"langkey" => "global_09",
					"path" => "Design/data_dictionary_upload.php"
				],
				"ders" => [
					"label" => "_Data _Exports, _Reports, and _Stats",
					"langkey" => "app_23",
					"path" => "DataExport/index.php",
				],
				"dit" => [
					"label" => "_Data _Import _Tool",
					"langkey" => "app_01",
					"path" => "index.php",
					"route" => "DataImportController:index"
				],
				"dq" => [
					"label" => "_Data _Quality",
					"langkey" => "app_20",
					"path" => "DataQuality/index.php"
				],
				"el" => [
					"label" => "_Email _Logging",
					"langkey" => "email_users_53",
					"path" => "index.php",
					"route" => "EmailLoggingController:index"
				],
				"eml" => [
					"label" => "_External _Module _Logs",
					"langkey" => "em_manage_113",
					"path" => $em_ext ? "/external_modules/manager/logs.php" : "ExternalModules/manager/logs.php"
				],
				"emm" => [
					"label" => "_External _Modules - Project Module _Manager",
					"langkey" => "em_manage_8",
					"path" => $em_ext ? "/external_modules/manager/project.php" : "ExternalModules/manager/project.php"
				],
				"eps" => [
					"label" => "_Edit _project _settings",
					"langkey" => "project_settings_64",
					"path" => "ControlCenter/edit_project.php",
					"pid" => "project"
				],
				"fcl" => [
					"label" => "_Field _Comment _Log",
					"langkey" => "dataqueries_141",
					"path" => "DataQuality/field_comment_log.php"
				],
				"fr" => [
					"label" => "_File _Repository",
					"langkey" => "app_04",
					"path" => "index.php",
					"route" => "FileRepositoryController:index"
				],
				"log" => [
					"label" => "_L_o_gging",
					"langkey" => "app_07",
					"path" => "Logging/index.php"
				],
				"mlm" => [
					"label" => "_Multi-_Language _Management",
					"langkey" => "multilang_01",
					"path" => "index.php",
					"route" => "MultiLanguageController:projectSetup"
				],
				"od" => [
					"label" => "_Online _Designer",
					"langkey" => "design_25",
					"path" => "Design/online_designer.php"
				],
				"oeo" => [
					"label" => "_Other _Export _Options",
					"langkey" => "data_export_tool_213",
					"path" => "DataExport/index.php",
					"additional" => [
						"other_export_options" => 1
						]
					],
				"of" => [
					"label" => "_Other _Functionality",
					"langkey" => "setup_68",
					"path" => "ProjectSetup/other_functionality.php"
				],
				"pd" => [
					"label" => "_Project _Dashboards",
					"langkey" => "global_182",
					"path" => "index.php",
					"route" => "ProjectDashController:index"
				],
				"ph" => [
					"label" => "_Project _Home",
					"langkey" => "bottom_44",
					"path" => "index.php"
				],
				"pl" => [
					"label" => "_Participant _List",
					"langkey" => "survey_37",
					"path" => "Surveys/invite_participants.php",
					"additional" => [
						"participant_list" => 1,
						]
					],
				"ps" => [
					"label" => "_Project _Setup",
					"langkey" => "app_17",
					"path" => "ProjectSetup/index.php"
				],
				"prh" => [
					"label" => "_Project _Revision _History",
					"langkey" => "app_18",
					"path" => "ProjectSetup/project_revision_history.php"
				],
				"psl" => [
					"label" => "_Public _Survey _Link",
					"langkey" => "survey_279",
					"path" => "Surveys/invite_participants.php"
				],
				"rhp" => [
					"label" => "_Record _Home _Page",
					"langkey" => "grid_42",
					"no-link" => true,
					"path" => "DataEntry/record_home.php",
					"record" => "id",
					"additional" => [
						"arm" => 1
					]
				],
				"rsd" => [
					"label" => "_Record _Status _Dashboard",
					"langkey" => "global_91",
					"path" => "DataEntry/record_status_dashboard.php"
				],
				"sdt" => [
					"label" => "_Survey _Distribution _Tools",
					"langkey" => "app_24",
					"path" => "Surveys/invite_participants.php"
				],
				"sil" => [
					"label" => "_Survey _Invitation _Log",
					"langkey" => "survey_350",
					"path" => "Surveys/invite_participants.php",
					"additional" => [
						"email_log" => 1
					]
				],
				"ur" => [
					"label" => "_User _Rights",
					"langkey" => "app_05",
					"path" => "UserRights/index.php"
				],
			],
			// Control Center targets
			"cc" => [
				"" => [
					"label" => "_Control _Center",
					"langkey" => "global_07",
					"path" => "ControlCenter/index.php"
				],
				"ap" => [
					"label" => "_Administrator _Privileges",
					"langkey" => "control_center_4734",
					"path" => "ControlCenter/superusers.php"
				],
				"apidoc" => [
					"label" => "_A_P_I _D_o_cumentation",
					"langkey" => "control_center_445",
					"path" => "/api/help/index.php"
				],
				"bp" => [
					"label" => "_Browse _Projects",
					"langkey" => "control_center_110",
					"path" => "ControlCenter/view_projects.php",
				],
				"bu" => [
					"label" => "_Browse _Users",
					"langkey" => "control_center_109",
					"path" => "ControlCenter/view_users.php",
				],
				"cal" => [
					"label" => "_Custom _Application _Links",
					"langkey" => "extres_55",
					"path" => "ControlCenter/external_links_global.php",
				],
				"cdis" => [
					"label" => "_Clinical _Data _Interoperability _Services",
					"langkey" => "ws_262",
					"path" => "ControlCenter/ddp_fhir_settings.php",
				],
				"cron" => [
					"label" => "_Cron _Jobs",
					"langkey" => "control_center_287",
					"path" => "ControlCenter/cron_jobs.php",
				],
				"dam" => [
					"label" => "_Database _Activity _Monitor",
					"langkey" => "control_center_4807",
					"path" => "ControlCenter/mysql_dashboard.php",
				],
				"ddp" => [
					"label" => "_Dynamic _Data _Pull (DDP)",
					"langkey" => "ws_63",
					"path" => "ControlCenter/ddp_settings.php",
				],
				"dps" => [
					"label" => "_Default _Project _Settings",
					"langkey" => "control_center_136",
					"path" => "ControlCenter/project_settings.php",
				],
				"dqt" => [
					"label" => "_Database _Query _Tool",
					"langkey" => "control_center_4803",
					"path" => "ControlCenter/database_query_tool.php",
				],
				"emdoc" => [
					"label" => "_External _Modules _D_o_cumentation",
					"langkey" => null,
					"path" => "Plugins/index.php?page=ext_mods_docs/"
				],
				"eml" => [
					"label" => "_External _Module _Logs",
					"langkey" => "em_manage_113",
					"path" => $em_ext ? "/external_modules/manager/logs.php" : "ExternalModules/manager/logs.php"
				],
				"emm" => [
					"label" => "_External _Modules - Module _Manager",
					"langkey" => "em_manage_14",
					"path" => $em_ext ? "/external_modules/manager/control_center.php" : "ExternalModules/manager/control_center.php"
				],
				"eu" => [
					"label" => "_Email _Users",
					"langkey" => "email_users_02",
					"path" => "ControlCenter/email_users.php"
				],
				"fus" => [
					"label" => "_File _Upload _Settings",
					"langkey" => "system_config_214",
					"path" => "ControlCenter/file_upload_settings.php"
				],
				"fs" => [
					"label" => "_Footer _Settings (All Projects)",
					"langkey" => "control_center_4398",
					"path" => "ControlCenter/footer_settings.php"
				],
				"fvt" => [
					"label" => "_Field _Validation _Types",
					"langkey" => "control_center_150",
					"path" => "ControlCenter/validation_type_setup.php"
				],
				"gc" => [
					"label" => "_General _Configuration",
					"langkey" => "control_center_125",
					"path" => "ControlCenter/general_settings.php"
				],
				"hookdoc" => [
					"label" => "Plugin, _H_o_o_k, & External Module _D_o_cumentation",
					"langkey" => "control_center_4605",
					"path" => "Plugins/index.php"
				],
				"hps" => [
					"label" => "_Home _Page _Settings",
					"langkey" => "control_center_4397",
					"path" => "ControlCenter/homepage_settings.php"
				],
				"info" => [
					"label" => "redcap\__i_n_f_o()",
					"langkey" => null,
					"path" => "Plugins/index.php?page=redcap_info"
				],
				"mlm" => [
					"label" => "_Multi-_Language _Management",
					"langkey" => "multilang_01",
					"path" => "index.php",
					"route" => "MultiLanguageController:systemConfig"
				],
				"msc" => [
					"label" => "_Modules/_Services _Configuration",
					"langkey" => "control_center_4604",
					"path" => "ControlCenter/modules_settings.php"
				],
				"pt" => [
					"label" => "_Project _Templates",
					"langkey" => "create_project_79",
					"path" => "ControlCenter/project_templates.php",
				],
				"sa" => [
					"label" => "_Security & _Authentication",
					"langkey" => "control_center_113",
					"path" => "ControlCenter/security_settings.php",
				],
				"sll" => [
					"label" => "_Survey _Link _Lookup",
					"langkey" => "control_center_4702",
					"path" => "ControlCenter/survey_link_lookup.php",
				],
				"tokens" => [
					"label" => "API _T_o_k_e_n_s",
					"langkey" => "control_center_245",
					"path" => "ControlCenter/user_api_tokens.php",
				],
				"ual" => [
					"label" => "_User _Activity _Log",
					"langkey" => "control_center_4809",
					"path" => "ControlCenter/todays_activity.php",
				],
				"us" => [
					"label" => "_User _Settings",
					"langkey" => "control_center_315",
					"path" => "ControlCenter/user_settings.php",
				],
			],
			// Other
			"" => [
				"hf" => [
					"label" => "_Help & _FAQ",
					"langkey" => "bottom_27",
					"path" => "/index.php?action=help",
				],
				"home" => [
					"label" => "_H_o_m_e",
					"langkey" => "home_21",
					"path" => "/index.php",
				],
				"mp" => [
					"label" => "_My _Projects",
					"langkey" => "bottom_03",
					"path" => "/index.php?action=myprojects",
				],
				"np" => [
					"label" => "_New _Project",
					"langkey" => "home_61",
					"path" => "/index.php?action=create",
				],
				"sd" => [
					"label" => "_Sponsor _Dashboard",
					"langkey" => "rights_330",
					"path" => "/index.php?action=user_sponsor_dashboard",
				],
				"send" => [
					"label" => "_S_e_n_d-It",
					"langkey" => "form_renderer_25",
					"path" => "index.php", 
					"route" => "SendItController:upload",
				],
				"tv" => [
					"label" => "_Training _Videos",
					"langkey" => "home_62",
					"path" => "/index.php?action=training",
				],
			]
		];
	}

	// Get all roles and users in the project in an associative array with role name as array key
	public static function getUsersRoles()
	{
		global $lang;
		$all_users_roles = array();
		$roles = UserRights::getRoles();
		$proj_users = UserRights::getRightsAllUsers(false);
		foreach ($proj_users as $this_user=>$attr) {
			if ($this_user == '') continue;
			if (is_numeric($attr['role_id'])) {
				$attr['role_id'] = $roles[$attr['role_id']]['role_name'];
			} else {
				$attr['role_id'] = $lang['rights_361'];
			}
			$all_users_roles[$attr['role_id']][$this_user] = $this_user . ($attr['user_fullname'] == '' ? '' : " ({$attr['user_fullname']})");
		}
		natcaseksort($all_users_roles);
		foreach ($all_users_roles as &$these_users) {
			natcaseksort($these_users);
		}
		return $all_users_roles;
	}

	// Is the current user an admin (including possibly impersonating a non-super user)?
	public static function isSuperUserOrImpersonator()
	{
		return (defined("SUPER_USER") && SUPER_USER || self::isImpersonatingUser());
	}

	// Is the current user an admin and is NOT currently impersonating a non-super user?
	public static function isSuperUserNotImpersonator()
	{
		return (defined("SUPER_USER") && SUPER_USER && !self::isImpersonatingUser());
	}

	// Is the current user impersonating another user in this project?
	public static function isImpersonatingUser()
	{
		return (defined("PROJECT_ID") && isset($_SESSION['impersonate_user'][PROJECT_ID]));
	}

	// Get the name of the user being impersonated by an admin
	public static function getUsernameImpersonating()
	{
		return self::isImpersonatingUser() ? $_SESSION['impersonate_user'][PROJECT_ID]['impersonating'] : '';
	}

	// Impersonate a user (admins only)
	public static function impersonateUser()
	{
		global $lang;
		if (!isset($_POST['user']) || !self::isSuperUserOrImpersonator()) {
			exit('0');
		}
		// Verify that user is a project user
		$proj_users = UserRights::getRightsAllUsers(false);
		if (!isset($proj_users[$_POST['user']]) && $_POST['user'] != '') exit('0');
		// Add to session or remove it if blank
		if ($_POST['user'] == '') {
			$msg =  $lang['rights_369'];
			$log = "(Admin only) Stop viewing project as user \"{$_SESSION['impersonate_user'][PROJECT_ID]['impersonating']}\"";
			unset($_SESSION['impersonate_user'][PROJECT_ID]);
		} else {
			$msg = $lang['rights_364'] . " \"" . RCView::b($_POST['user']) . "\"" . $lang['period'] . " " . $lang['rights_365'];
			$log = "(Admin only) View project as user \"{$_POST['user']}\"";
			$_SESSION['impersonate_user'][PROJECT_ID] = array('impersonator'=>USERID, 'impersonating'=>$_POST['user']);
		}
		// Log the event
		Logging::logEvent("","redcap_user_rights","MANAGE",$_POST['user'],"user = '{$_POST['user']}'", $log);
		// Return success
		print RCView::div(array('class'=>'green'),
			'<i class="fas fa-check"></i> ' . $msg
		);
	}

	// If impersonating another user in this project, display banner as reminder
	public static function renderImpersonatingUserBanner()
	{
		global $lang;
		if (!self::isImpersonatingUser()) return '';
		$impersonating = $_SESSION['impersonate_user'][PROJECT_ID]['impersonating'];
		$userInfo = User::getUserInfo($impersonating);
		$impersonatingName = isset($userInfo['user_firstname']) ? trim($userInfo['user_firstname']." ".$userInfo['user_lastname']) : "";
		if ($impersonatingName != '') $impersonatingName = " ($impersonatingName)";
		return "<div class='green fs13 py-2 pe-1' style='margin-left:-20px;max-width:100%;text-indent:-11px;padding-left:30px;border-radius:0;'>
				<i class=\"fas fa-user-tie\"></i>{$lang['rights_366']} <b>\"$impersonating\"$impersonatingName</b>{$lang['rights_367']}</div>";
	}

	public static function removePrivileges($project_id, $user, $ExtRes = null)
	{
		// Delete user from project rights table
		$sql = "DELETE FROM redcap_user_rights WHERE project_id = $project_id and username = '".db_escape($user)."'";
		
		$result = db_query($sql);
		if ($result)
		{
			$sql2 = null;
			if($ExtRes){
				// Also delete from project bookmarks users table as well
				$sql2 = "DELETE FROM redcap_external_links_users WHERE username = '".db_escape($user)."' and ext_id in
				(" . implode(",", array_keys($ExtRes->getResources())) . ")";
				db_query($sql2);
			}
			
			// Also delete from redcap_reports_access_users table
			$sql3 = "DELETE FROM redcap_reports_access_users WHERE username = '".db_escape($user)."' and report_id in
					(select report_id from redcap_reports where project_id = $project_id)";
			db_query($sql3);
			
			// Remove from any linked conversations in Messenger.
			Messenger::removeUserFromLinkedProjectConversation($project_id, $user);
			
			// Logging
			Logging::logEvent("$sql;\n$sql2;\n$sql3","redcap_user_rights","delete",$user,"user = '".db_escape($user)."'","Delete user");
		}

		return $result;
	}

	public static function addRole($Proj, $role_name, $user)
	{
        $attributes = self::getApiUserRolesAttr(false, $Proj->project_id);

        // Remap keys, if needed
        foreach ($attributes as $key=>$val) {
            if (isinteger($key) || !isset($_POST[$val])) continue;
            $_POST[$key] = $_POST[$val];
        }

        if (!isset($_POST['alerts'])) $_POST['alerts'] = '0';

		//Insert user into user rights table
		$fields = "project_id, role_name, data_export_tool, data_import_tool, data_comparison_tool, data_logging, email_logging, file_repository, double_data, " .
		"user_rights, design, alerts, lock_record, lock_record_multiform, lock_record_customize, data_access_groups, graphical, reports, calendar, " .
		"record_create, record_rename, record_delete, dts, participants, data_quality_design, data_quality_execute, data_quality_resolution,
		api_export, api_import, api_modules, mobile_app, mobile_app_download_data,
		random_setup, random_dashboard, random_perform, realtime_webservice_mapping, realtime_webservice_adjudicate, external_module_config,
		mycap_participants,
		data_entry, data_export_instruments";
		$double_data = in_array($_POST['double_data'], array('0', '1', '2'), true) 
			? $_POST['double_data'] 
			: '0';
		$data_quality_resolution = in_array($_POST['data_quality_resolution'], array('0', '1', '2', '3', '4', '5'), true) 
			? $_POST['data_quality_resolution'] 
			: '0';
		$values =  "{$Proj->project_id},".
		"'".db_escape($role_name)."',".
		"null,".
		"'".db_escape($_POST['data_import_tool'])."',".
		"'".db_escape($_POST['data_comparison_tool'])."',".
		"'".db_escape($_POST['data_logging'])."',".
		"'".db_escape($_POST['email_logging'])."',".
		"'".db_escape($_POST['file_repository'])."',".
		"'".$double_data."',".
		"'".db_escape($_POST['user_rights'])."',".
		"'".db_escape($_POST['design'])."',".
		"'".db_escape($_POST['alerts'])."',".
		"'".db_escape($_POST['lock_record'])."',".
		"'".db_escape($_POST['lock_record_multiform'])."',".
		"'".db_escape($_POST['lock_record_customize'])."',".
		"'".db_escape($_POST['data_access_groups'])."',".
		"'".db_escape($_POST['graphical'])."',".
		"'".db_escape($_POST['reports'])."',".
		"'".db_escape($_POST['calendar'])."',".
		"'".db_escape($_POST['record_create'])."',".
		"'".db_escape($_POST['record_rename'])."',".
		"'".db_escape($_POST['record_delete'])."',".
		"'".db_escape($_POST['dts'])."',".
		"'".db_escape($_POST['participants'])."',".
		"'".db_escape($_POST['data_quality_design'])."',".
		"'".db_escape($_POST['data_quality_execute'])."',".
		"'".$data_quality_resolution."',".
		"'".db_escape($_POST['api_export'])."',".
		"'".db_escape($_POST['api_import'])."',".
		"'".db_escape($_POST['api_modules'])."',".
		"'".db_escape($_POST['mobile_app'])."',".
		"'".db_escape($_POST['mobile_app_download_data'])."',".
		"'".db_escape($_POST['random_setup'])."',".
		"'".db_escape($_POST['random_dashboard'])."',".
		"'".db_escape($_POST['random_perform'])."',".
		"'".db_escape($_POST['realtime_webservice_mapping'])."',".
		"'".db_escape($_POST['realtime_webservice_adjudicate'])."',".
		"".checkNull($_POST['external_module_config'] ?? "").", ".
		"'".db_escape($_POST['mycap_participants'])."',".
		"'";
		foreach (array_keys($Proj->forms) as $form_name)
		{
			// Process each form's radio button value
			$this_field = "form-" . $form_name;
			$this_value = ($_POST[$this_field] == '') ? 0 : (int)$_POST[$this_field];
			// If set survey responses to be editable, then set to value 3
			$editresp_chkbox_name = "form-editresp-" . $form_name;
			if ($this_value == '1' && isset($_POST[$editresp_chkbox_name]) && $_POST[$editresp_chkbox_name])
			{
				$this_value = 3;
			}
			$values .= "[$form_name,$this_value]";
		}
		$values .= "', '";
		foreach (array_keys($Proj->forms) as $form_name)
		{
			// Process each form's radio button value
			$this_field = "export-form-" . $form_name;
			$this_value = ($_POST[$this_field] == '') ? 0 : $_POST[$this_field];
			$values .= "[$form_name,$this_value]";
		}
		$values .= "'";
		// Insert user into user_rights table
		$sql = "INSERT INTO redcap_user_roles ($fields) VALUES ($values)";
		$result = db_query($sql);
		if ($result) {
			// Logging
			Logging::logEvent($sql,"redcap_user_rights","insert",$user,"role = '$role_name'","Add role");
		}

		return $result;
	}

	public static function removeRole($project_id, $role_id, $role_name)
    {
        // Disassociate any File Repository folders with the role first
        $sql = "update redcap_docs_folders set role_id = null where role_id = '".db_escape($role_id)."'";
        $q = db_query($sql);
		// Delete user from project rights table
		$sql = "DELETE FROM redcap_user_roles WHERE project_id = $project_id and role_id = '".db_escape($role_id)."'";
		$result = db_query($sql);
		if ($result)
		{
			/*
			// For ALL users in role, set role_id to NULL and give the user the exact same rights as the role deleted in order to maintain continuity of privileges
			$this_role_rights = $roles[$user];
			$this_role_users = $this_role_rights['role_users_assigned'];
			// Set role_id to NULL and give the user the exact same rights as the role they were removed from in order to maintain continuity of privileges
			unset($this_role_rights['role_name'], $this_role_rights['project_id'], $this_role_rights['role_users_assigned']);
			// Loop through each user that was in the role
			$sql_all = $sqla = array();
			foreach ($this_role_rights as $key=>$val) $sqla[] = "$key = ".checkNull($val);
			foreach ($this_role_users as $this_role_user) {
				$sql_all[] = $sql = "update redcap_user_rights set role_id = null, " . implode(", ", $sqla) . "
									 where project_id = $project_id and username = '".db_escape($this_role_user)."'";
				db_query($sql);
			}
			*/
			// Logging
			Logging::logEvent($sql,"redcap_user_rights","delete",$role_id,"role = '$role_name'","Delete role");
		}

		return $result;
	}

	// Convert form-level viewing and export rights (e.g. [demographics,3][baseline_data,1]) to array as key/value pairs
	public static function convertFormRightsToArrayPre($rightsString)
	{
		if ($rightsString == null) return [];
		return explode("][", substr(trim($rightsString), 1, -1));
	}

	// Convert form-level viewing and export rights (e.g. [demographics,3][baseline_data,1]) to array as key/value pairs
	public static function convertFormRightsToArray($rightsString)
	{
		$formLevelRights = [];
		$allForms = self::convertFormRightsToArrayPre($rightsString);
		foreach ($allForms as $forminfo) {
			if (strpos($forminfo, ",")) {
				list($this_form, $this_form_rights) = explode(",", $forminfo, 2);
			} else {
				$this_form = $forminfo;
				$this_form_rights = 0;
			}
			$formLevelRights[$this_form] = $this_form_rights;
		}
		return $formLevelRights;
	}

	/**
	 * Convert form-level viewing and export rights from array format to string
	 * @param array $form_rights 
	 * @return string 
	 */
	public static function convertFormRightsFromArray($form_rights)
	{
		$rightsString = "";
		foreach ($form_rights as $this_form=>$this_right) {
			$rightsString .= "[{$this_form},{$this_right}]";
		}
		return $rightsString;
	}

	// Convert form-level viewing and export rights (e.g. [demographics,3][baseline_data,1]) to readable text (e.g., 6 No Access, 1 De-Identified)
	public static function convertFormRightsToText($rightsString, $isExportRights=false)
	{
		global $lang, $Proj;
		$formText = [];

		$formSum = $isExportRights 
			? [
				0=>0, 
				2=>0, 
				3=>0, 
				1=>0
			] 
			: [
				'no-access' => 0,
				'read-only' => 0,
				'view-edit' => 0,
				'editresp' => 0,
				'delete' => 0,
			];
		$formRights = self::convertFormRightsToArray($rightsString);
		if ($isExportRights) {
			foreach ($formRights as $this_form=>$this_right) {
				if (!isset($Proj->forms[$this_form])) continue;
				$formSum[(int)$this_right]++;
			}
		}
		else {
			foreach ($formRights as $this_form=>$this_right) {
				if (!isset($Proj->forms[$this_form])) continue;
				foreach($formSum as $this_key=>$this_count) {
					if (self::hasDataViewingRights($this_right, $this_key)) {
						$formSum[$this_key]++;
					}
				}
			}
		}
		foreach ($formSum as $this_right=>$this_count) {
			$thisText = "";
			$parens = false;
            if ($isExportRights) {
                // Use form export rights language
                if ($this_right == '1') {
                    $thisText = $lang['rights_49'];
                } elseif ($this_right == '2') {
                    $thisText = $lang['rights_48'];
                } elseif ($this_right == '3') {
                    $thisText = $lang['data_export_tool_290'];
                } else {
                    $thisText = $lang['rights_47'];
                }
            } else {
				switch ($this_right) {
					case 'no-access':
						$thisText = "{$lang['rights_47']}<br>{$lang['rights_395']}";
						break;
					case 'read-only':
						$thisText = $lang['rights_61'];
						break;
					case 'view-edit':
						$thisText = $lang['rights_138'];
						break;
					case 'delete':
						$thisText = $lang['global_19'];
						$parens = true;
						break;
				}
            }
			if ($thisText != "" && $this_count > 0) {
				$thisText = "<code class='fs11'>$this_count</code> $thisText";
				$thisText = $parens ? "($thisText)" : $thisText;
				$formText[] = $thisText;
			}
		}
		$divAttr = "class='userRightsTableForms'";
		return "<div class='text-start'><div $divAttr>".implode("</div><div $divAttr>", $formText)."</div></div>";
	}

	// Get User Details for export users functionality
	public static function getUserDetails($projectId, $mobile_app_enabled=false)
	{
	    global $user_rights, $mobile_app_enabled, $mycap_enabled_global, $mycap_enabled;
        $Proj = new Project();

        // Get all user's rights (includes role's rights if they are in a role)
        $user_priv = UserRights::getPrivileges($projectId);
        $user_priv = $user_priv[$projectId];

        # get user information (does NOT include role-based rights for user)
        $sql = "SELECT ur.*, ui.user_email, ui.user_firstname, ui.user_lastname, ui.super_user
			FROM redcap_user_rights ur
			LEFT JOIN redcap_user_information ui ON ur.username = ui.username
			WHERE ur.project_id = ".PROJECT_ID.
            (!isinteger($user_rights['group_id']??"") ? "" : " AND group_id = " . $user_rights['group_id']);
        $users = db_query($sql);
        $result = array();
        $r = 0;
        while ($row = db_fetch_assoc($users))
        {
            // Decode and set any nulls to ""
            foreach ($row as &$val) {
                if (is_array($val)) continue;
                if ($val == null) $val = '';
                $val = html_entity_decode($val, ENT_QUOTES);
            }

            // Convert username to lower case to prevent case sensitivity issues with arrays
            $row["username"] = strtolower($row["username"]);

			$dataEntryArr = self::convertFormRightsToArrayPre($user_priv[$row["username"]]['data_entry']);
			$forms = array();
			foreach ($dataEntryArr as $keyval)
			{
				if ($keyval == '') continue;
				list($key, $value) = explode(",", $keyval, 2);
				if ($key == '') continue;
				$forms[$key] = isinteger($value) ? (int)$value : $value;
			}

			$dataEntryArr = self::convertFormRightsToArrayPre($user_priv[$row["username"]]['data_export_instruments']);
			$formsExport = array();
			foreach ($dataEntryArr as $keyval)
			{
				if ($keyval == '') continue;
				list($key, $value) = explode(",", $keyval, 2);
				if ($key == '') continue;
				$formsExport[$key] = isinteger($value) ? (int)$value : $value;
			}

            // Check group_id
            $unique_group_name = "";
            if (is_numeric($row['group_id'])) {
                $unique_group_name = $Proj->getUniqueGroupNames($row['group_id']);
                if (empty($unique_group_name)) {
                    $unique_group_name = $row['group_id'] = "";
                }
            }

            // Set array entry for this user
            $result[$r] = array(
                'username'					=> $row['username'],
                'email'						=> $row['user_email'],
                'firstname'					=> $row['user_firstname'],
                'lastname'					=> $row['user_lastname'],
                'expiration'				=> $row['expiration'],
                'data_access_group'			=> $unique_group_name,
                'data_access_group_id'		=> (isinteger($row['group_id']) ? (int)$row['group_id'] : $row['group_id'])
            );

            // Rights that might be governed by roles
            $rights = array(
                'design', 'alerts', 'user_rights', 'data_access_groups',
                'reports', 'graphical'=>'stats_and_charts',
                'participants'=>'manage_survey_participants', 'calendar',
                'data_import_tool', 'data_comparison_tool', 'data_logging'=>'logging', 'email_logging',
                'file_repository', 'data_quality_design'=>'data_quality_create', 'data_quality_execute',
                'api_export', 'api_import', 'api_modules',
                'mobile_app', 'mobile_app_download_data',
                'record_create', 'record_rename', 'record_delete', 'record_create',
                'lock_record_multiform'=>'lock_records_all_forms',
                'lock_record'=>'lock_records',
                'lock_record_customize'=>'lock_records_customization'
            );
            if ($mycap_enabled_global && $mycap_enabled) {
                $rights['mycap_participants'] = 'mycap_participants';
            }

            if ($Proj->project['data_resolution_enabled'] == '2') {
                $rights['data_quality_resolution'] = 'data_quality_resolution';
            }

            if ($Proj->project['double_data_entry'] == '1') {
                $rights['double_data'] = 'double_data';
            }

            if ($Proj->project['randomization'] == '1') {
                $rights['random_setup'] = 'random_setup';
                $rights['random_dashboard'] = 'random_dashboard';
                $rights['random_perform'] = 'random_perform';
            }

            foreach($rights as $right=>$right_formatted)
            {
                $thisPriv = $user_priv[$row['username']][(is_numeric($right) ? $right_formatted : $right)];
                $result[$r][$right_formatted] = (isinteger($thisPriv) ? (int)$thisPriv : $thisPriv);
            }

            // Add form rights at end
            $result[$r]['forms'] = $forms;
            $result[$r]['forms_export'] = $formsExport;

            // If mobile app is not enabled, then remove the mobile_app user privilege attributes
            if (!$mobile_app_enabled) {
                unset($result[$r]['mobile_app'], $result[$r]['mobile_app_download_data']);
            }

            // Set for next loop
            $r++;
        }
        return $result;
    }

    // Update Users for a given project
    // Return array with count of users updated and array of errors, if any
    public static function uploadUsers($project_id, $data)
    {
        global $lang, $user_rights;

        $rights_fields = array('design','user_rights','data_access_groups','reports','stats_and_charts','manage_survey_participants',
                                'calendar','data_import_tool','data_comparison_tool','logging','file_repository','data_quality_create',
                                'data_quality_execute','api_export','api_import','api_modules','mobile_app','mobile_app_download_data','record_create',
                                'record_rename','record_delete','lock_records_all_forms','lock_records','lock_records_customization',
                                'mycap_participants');

        $count = 0;
        $errors = array();

        $Proj = new Project($project_id);
        $dags = $Proj->getUniqueGroupNames();
        // Check for basic attributes needed
        foreach ($data as $key=>&$this_user) {
            $this_user['username'] = trim($this_user['username']);
            // If username is missing
            if (!isset($this_user['username']) || $this_user['username'] == '') {
                $errors[] = $lang['api_118'] . ($key+1) . " " . $lang['api_119'];
                continue;
            }
            // Validation username format
            if (!preg_match("/^([a-zA-Z0-9'_\s\.\-\@])+$/", $this_user['username'])) {
                $errors[] = "username \"".RCView::escape($this_user['username'], false)."\" " . $lang['api_151'] . " " . $lang['rights_443'];
            }
            if ($user_rights['group_id']??"" != "") {
                // Since you have been assigned to a Data Access Group, you are only able to view/edit users from your group, and you are not allowed to add new users to this project.
                $privileges = UserRights::getPrivileges(PROJECT_ID, $this_user['username']);
                if(empty($privileges))
                {
                    $errors[] = "username \"".RCView::escape($this_user['username'], false)."\" " . $lang['rights_424'] ." ". $lang['rights_92'];
                } else {
                    $userDagId = $privileges[PROJECT_ID][strtolower($this_user['username'])]['group_id'];
                    if($Proj->getUniqueGroupNames($userDagId) != $this_user['data_access_group']) {
                        $errors[] = "data_access_group \"".RCView::escape($this_user['data_access_group'], false)."\" " . $lang['rights_423'] ." ". $lang['rights_92'];
                    }
                }
            }
            // Validate DAG (if provided)
            if ($this_user['data_access_group'] != '' && !array_search($this_user['data_access_group'], $dags)) {
                $errors[] = "data_access_group \"".RCView::escape($this_user['data_access_group'], false)."\" " . $lang['api_111'];
            }

            // Check Other attribute rights values
            foreach ($rights_fields as $field) {
                if (isset($this_user[$field]) && !empty($this_user[$field])) {
                    $validValues = ($field == 'lock_records' || $field == 'user_rights') ? [0,1,2] : [0,1];
                    if (!is_numeric($this_user[$field]) || !in_array($this_user[$field], $validValues)) {
                        $errors[] = $lang['rights_385']. " \"".RCView::escape($field, false)."\" ".$lang['rights_386']." \"".RCView::escape($this_user['username'], false)."\"  ".$lang['api_116']." \"".RCView::escape($this_user[$field], false)."\" ".$lang['rights_387']." ".$lang['rights_388'];
                    }
                }
            }
            // Check Data Export rights value
//            if (isset($this_user['data_export']) && !empty($this_user['data_export'])) {
//                if (!in_array($this_user['data_export'], array(0,1,2,3))) {
//                    $errors[] = $lang['rights_385']. " \"data_export\" ".$lang['rights_386']." \"{$this_user['username']}\"  ".$lang['api_116']." \"{$this_user['data_export']}\" ".$lang['rights_387']." ".$lang['rights_389'];
//                }
//            }
            // Check form-level rights
            if (isset($this_user['forms']) && is_array($this_user['forms']) && !empty($this_user['forms'])) {
                // Parse the forms
                $these_forms = array();
                foreach ($this_user['forms'] as $this_form=>$this_right) {
                    // Is valid form and right level value?
                    if (!isset($Proj->forms[$this_form])) {
                        $errors[] = $lang['api_113'] . " \"".RCView::escape($this_form, false)."\" " . $lang['api_114'] . " \"".RCView::escape($this_user['username'], false)."\" " . $lang['api_115'];
                    } elseif (!self::isValidDataViewingRightsValue($this_right)) {
                        $errors[] = $lang['api_113'] . " \"".RCView::escape($this_form, false)."\" " . $lang['api_116'] . " \"".RCView::escape($this_right, false)."\" " . $lang['api_117'];
                    } else {
                        $these_forms[] = $this_form;
                    }
                }
                // If some forms are not provided, then by default set their rights to 0.
                $missing_forms = array_diff(array_keys($Proj->forms), $these_forms);
                foreach ($missing_forms as $this_form) {
                    $this_user['forms'][$this_form] = 0;
                }
                // Reformat form-level rights to back-end format
                $data_entry = "";
                foreach ($this_user['forms'] as $this_form=>$this_val) {
                    $data_entry .= "[$this_form,$this_val]";
                }
                $this_user['forms'] = $data_entry;
            }
			// Check form-level export rights
			if (isset($this_user['forms_export']) && is_array($this_user['forms_export']) && !empty($this_user['forms_export'])) {
				// Parse the forms
				$these_forms = array();
				foreach ($this_user['forms_export'] as $this_form=>$this_right) {
					// Is valid form and right level value?
					if (!isset($Proj->forms[$this_form])) {
						$errors[] = $lang['api_113'] . " \"".RCView::escape($this_form, false)."\" " . $lang['api_114'] . " \"".RCView::escape($this_user['username'], false)."\" " . $lang['api_115'];
					} elseif (!is_numeric($this_right) || !($this_right >= 0 && $this_right <=3)) {
						$errors[] = $lang['api_113'] . " \"".RCView::escape($this_form, false)."\" " . $lang['api_116'] . " \"".RCView::escape($this_right, false)."\" " . $lang['api_117'];
					} else {
						$these_forms[] = $this_form;
					}
				}
				// If some forms are not provided, then by default set their rights to 0.
				$missing_forms = array_diff(array_keys($Proj->forms), $these_forms);
				foreach ($missing_forms as $this_form) {
					$this_user['forms_export'][$this_form] = 0;
				}
				// Reformat form-level rights to back-end format
				$data_entry = "";
				foreach ($this_user['forms_export'] as $this_form=>$this_val) {
					$data_entry .= "[$this_form,$this_val]";
				}
				$this_user['forms_export'] = $data_entry;
			}
            // Convert unique DAG name to group_id
            if ($this_user['data_access_group'] != '' && array_search($this_user['data_access_group'], $dags)) {
                $this_user['data_access_group'] = array_search($this_user['data_access_group'], $dags);
            }
            // Remove email, first name, and last name (if included)
            unset($this_user['email'], $this_user['firstname'], $this_user['lastname'], $this_user['data_export']);
        }

        unset($this_user);

        if (empty($errors)) {
            foreach($data as $ur)
            {
                $ur['expiration'] = ($ur['expiration'] != '') ? date("Y-m-d",strtotime($ur['expiration'])) : "";
                $privileges = UserRights::getPrivileges(PROJECT_ID, $ur['username']);
                if(empty($privileges))
                {
                    if(UserRights::addPrivileges(PROJECT_ID, $ur))
                    {
                        $count++;
                    }
                }
                else
                {
                    // If user is in a role, then return an error
                    if (is_numeric($privileges[PROJECT_ID][strtolower($ur['username'])]['role_id'])) {
                        if (PAGE == "UserRights/import_export_users.php") {
                            $errors[] = "The user \"{$ur['username']}\" " . $lang['rights_396'];
                        } else {
                            $errors[] = "The user \"{$ur['username']}\" " . $lang['api_112'];
                        }
                        continue;
                    }

                    // Update
                    if ($ur['data_access_group'] == 0) {
                        $ur['data_access_group'] = NULL;
                    }
                    if(self::updatePrivileges(PROJECT_ID, $ur))
                    {
                        $count++;
                    }
                }
            }
        }

        // Return count and array of errors
        return array($count, $errors);
    }

    public static function isFormRightsUpdated($csvDataEntry, $dataEntry, $isSuperUser = 0) {
        // Parse data entry rights
        if ($isSuperUser == 1) {
            return false;
        } else {
            // Regular user
            $allForms = self::convertFormRightsToArrayPre($dataEntry);
            $formsArr = array();
            foreach ($allForms as $form)
            {
                list($this_form, $this_form_rights) = explode(",", $form, 2);
                $formsArr[$this_form] = $this_form_rights;
                if (isset($csvDataEntry[$this_form]) && $csvDataEntry[$this_form] != $this_form_rights) {
                    return true;
                }
            }
            if (is_array($csvDataEntry) && !empty(array_diff_key($csvDataEntry, $formsArr))) {
                return true;
            }
            return false;
        }
    }

    // Add unique user role name for role
    private static function addUniqueUserRoleName($role_id)
    {
        // Prefix
        $prefix = "U-"; // User Role prefix
        $success = false;
        while (!$success) {
            // Generate new unique name (start with 3 digit number followed by 7 alphanumeric chars) - do not allow zeros
            $unique_name = $prefix . str_replace("0", random_int(1, 9), str_pad(random_int(0, 999), 3, 0, STR_PAD_LEFT)) . generateRandomHash(7, false, true);
            // Update the table
            $sql = "UPDATE redcap_user_roles SET unique_role_name = '" . db_escape($unique_name) . "' WHERE role_id = $role_id";
            $success = db_query($sql);
        }
        return $unique_name;
    }

    // Get User Role Details for export user roles functionality
    public static function getUserRolesDetails($projectId, $mobile_app_enabled=false)
    {
        // Transform all legacy export rights for all users and roles in project (if applicable)
        self::transformExportRightsAllUsers($projectId);
        // Init roles in case any unique role names need to be generated and set
        UserRights::getRoles($projectId);

        # get user roles information
        $sql = "SELECT * FROM redcap_user_roles WHERE project_id = ".$projectId;
        $userRoles = db_query($sql);
        $result = array();
        $r = 0;
        while ($row = db_fetch_assoc($userRoles))
        {
            // Decode and set any nulls to ""
            foreach ($row as &$val) {
                if (is_array($val)) continue;
                if ($val == null) $val = '';
                $val = html_entity_decode($val, ENT_QUOTES);
            }
            $dataEntryArr = self::convertFormRightsToArrayPre($row['data_entry']);
            $forms = array();
            foreach ($dataEntryArr as $keyval)
            {
                if ($keyval == '') continue;
                list($key, $value) = explode(",", $keyval, 2);
                if ($key == '') continue;
                $forms[$key] = isinteger($value) ? (int)$value : $value;
            }
			$dataEntryArr = self::convertFormRightsToArrayPre($row['data_export_instruments']);
			$formsExport = array();
			foreach ($dataEntryArr as $keyval)
			{
				if ($keyval == '') continue;
				list($key, $value) = explode(",", $keyval, 2);
				if ($key == '') continue;
				$formsExport[$key] = isinteger($value) ? (int)$value : $value;
			}

            // Set array entry for this user
            $result[$r] = array(
                'unique_role_name'			=> $row['unique_role_name'],
                'role_label'				=> $row['role_name']
            );

            // Rights that might be governed by roles
            $rights = self::getApiUserRolesAttr(false, $projectId);

            foreach($rights as $right => $right_formatted)
            {
                $storedKey = is_numeric($right) ? $right_formatted : $right;
                $result[$r][$right_formatted] = $row[$storedKey];
            }

            // Add form rights at end
            $result[$r]['forms'] = $forms;
            $result[$r]['forms_export'] = $formsExport;

            // If mobile app is not enabled, then remove the mobile_app user privilege attributes
            if (!$mobile_app_enabled) {
                unset($result[$r]['mobile_app'], $result[$r]['mobile_app_download_data']);
            }

            unset($result[$r]['data_export']);

            // Set for next loop
            $r++;
        }
        return $result;
    }

    // Edit User Role details
    public static function editRole($Proj, $role_name, $role_id)
    {
        $attributes = self::getApiUserRolesAttr(false, $Proj->project_id);
        $values[] = "role_name = '".db_escape($role_name)."'";
        foreach($attributes as $key => $val)
        {
            // If value was not sent, then do not update it
            // Set update value
            $backEndKey = is_numeric($key) ? $val : $key;
            if (isset($_POST[$val]) && $_POST[$val] != '') {
                $values[] = "$backEndKey = " . db_escape($_POST[$val]);
            }
        }
        $set_values = implode(", ", $values);

		$allRoles = self::getRoles();
		$role = $allRoles[$role_id] ?? [];

        $formArr = array();
        $allForms = self::convertFormRightsToArrayPre($role['data_entry'] ?? '');
        foreach ($allForms as $forms) {
            list($form, $value) = explode(",",$forms);
            $formArr[$form] = $value;
        }

		$formArrExport = array();
		$allForms = self::convertFormRightsToArrayPre($role['data_export_instruments'] ?? '');
		foreach ($allForms as $forms) {
			list($form, $value) = explode(",",$forms);
			$formArrExport[$form] = $value;
		}

		$form_values = '';
        foreach (array_keys($Proj->forms) as $form_name)
        {
            // If value was not sent, do not update and set to stored/original value
            if (isset($_POST["form-" . $form_name]) && $_POST["form-" . $form_name] != '') {
                $this_value = $_POST["form-" . $form_name];
            } else {
                $this_value = $formArr[$form_name];
            }
            // If set survey responses to be editable, then set to value 3
            if ($this_value == '1' && isset($_POST["form-editresp-" . $form_name]) && $_POST["form-editresp-" . $form_name]) {
                $this_value = 3;
            }
            if ($this_value != '') {
                // Set value for this form
                $form_values .= "[$form_name,$this_value]";
            }
        }
        if ($form_values != '') {
            $set_values .= ",data_entry = '".$form_values."'";
        }

		$form_values = '';
		foreach (array_keys($Proj->forms) as $form_name)
		{
			// If value was not sent, do not update and set to stored/original value
			if (isset($_POST["export-form-" . $form_name]) && $_POST["export-form-" . $form_name] != '') {
				$this_value = $_POST["export-form-" . $form_name];
			} else {
				$this_value = $formArrExport[$form_name];
			}
			if ($this_value != '') {
				// Set value for this form
				$form_values .= "[$form_name,$this_value]";
			}
		}
		if ($form_values != '') {
			$set_values .= ",data_export_instruments = '".$form_values."'";
		}

        $sql = "UPDATE redcap_user_roles SET ".$set_values."
				WHERE role_id = '".db_escape($role_id)."' AND project_id = $Proj->project_id";
//        print htmlentities(print_r($_POST, true), ENT_QUOTES);
//        print "     SQL: ".htmlentities($sql, ENT_QUOTES);
        $result = db_query($sql);
        if ($result) {
            //Logging
            Logging::logEvent($sql,"redcap_user_roles","update",$role_id,"role = '$role_name'","Edit role");
        }

        return $result;
    }

    // Upload User Roles for a given project
    // Return array with count of user roles updated and array of errors, if any
    public static function uploadUserRoles($project_id, $data) {
        global $lang;
        $Proj = new Project($project_id);

        $rights_fields = array('design','alerts','user_rights','data_access_groups','reports','stats_and_charts',
            'manage_survey_participants', 'calendar','data_import_tool','data_comparison_tool','logging',
            'file_repository','data_quality_create','data_quality_execute','api_export','api_import','api_modules', 'mobile_app',
            'mobile_app_download_data','record_create', 'record_rename','record_delete','lock_records_all_forms',
            'mycap_participants',
            'lock_records','lock_records_customization');

        if ($Proj->project['data_resolution_enabled'] == '2') {
            $rights_fields[] = 'data_quality_resolution';
        }

        if ($Proj->project['double_data_entry'] == '1') {
            $rights_fields[] = 'double_data';
        }

        if ($Proj->project['randomization'] == '1') {
            $rights_fields[] = 'random_setup';
            $rights_fields[] = 'random_dashboard';
            $rights_fields[] = 'random_perform';
        }

        $count = 0;
        $errors = array();
        $invalidRoles = array();

        // Get all roles in the project
        $roleArr = $Proj->getUniqueRoleNames();

        // Check for basic attributes needed
        foreach ($data as $key=>&$this_role) {
            $this_role['unique_role_name'] = trim($this_role['unique_role_name'] ?? "");

            if (!empty($this_role['unique_role_name']) && !$Proj->uniqueRoleNameExists($this_role['unique_role_name'])) {
                $invalidRoles[] = $this_role['unique_role_name'];
            }
            if (empty($this_role['role_label'])) {
                $errors[] = $lang['rights_411'];
            }
            // Check Other attribute rights values
            foreach ($rights_fields as $rights => $field) {
                if (!is_numeric($rights)) {
                    $this_role[$field] = $this_role[$rights];
                }
                if (isset($this_role[$field]) && !empty($this_role[$field])) {
                    if (!is_numeric($this_role[$field])) {
                        $errors[] = $lang['rights_385']. " \"{$field}\" ".$lang['rights_405']." \"{$this_role['unique_role_name']}\"  ".$lang['api_116']." \"{$this_role[$field]}\" ".$lang['rights_387'];
                    }
                }
            }
            // Check form-level rights (viewing)
            if (isset($this_role['forms']) && !empty($this_role['forms'])) {
                // Parse the forms
                $these_forms = array();
                foreach ($this_role['forms'] as $this_form=>$this_right) {
                    // Is valid form and right level value?
                    if (!isset($Proj->forms[$this_form])) {
                        $errors[] = $lang['api_113'] . " \"$this_form\" " . $lang['api_175'] . " \"{$this_role['unique_role_name']}\" " . $lang['api_115'];
                    } elseif (!self::isValidDataViewingRightsValue($this_right)) {
                        $errors[] = $lang['api_113'] . " \"$this_form\" " . $lang['api_116'] . " \"$this_right\" " . $lang['api_117'];
                    } else {
                        $these_forms[] = $this_form;
                    }
                }
                // If some forms are not provided, then by default set their rights to 0.
                $missing_forms = array_diff(array_keys($Proj->forms), $these_forms);
                foreach ($missing_forms as $this_form) {
                    if (!is_string($this_form)) continue;
                    $this_role['forms'][$this_form] = 0;
                }
                // Reformat form-level rights to back-end format
                $data_entry = "";
                foreach ($this_role['forms'] as $this_form=>$this_val) {
                    $data_entry .= "[$this_form,$this_val]";
                }
                $this_role['forms'] = $data_entry;
            }
            // Check form-level rights (export)
            if (isset($this_role['forms_export']) && !empty($this_role['forms_export'])) {
                // Parse the forms
                $these_forms = array();
                foreach ($this_role['forms_export'] as $this_form=>$this_right) {
                    // Is valid form and right level value?
                    if (!isset($Proj->forms[$this_form])) {
                        $errors[] = $lang['api_113'] . " \"$this_form\" " . $lang['api_175'] . " \"{$this_role['unique_role_name']}\" " . $lang['api_115'];
                    } elseif (!is_numeric($this_right) || !($this_right >= 0 && $this_right <=3)) {
                        $errors[] = $lang['api_113'] . " \"$this_form\" " . $lang['api_116'] . " \"$this_right\" " . $lang['api_117'];
                    } else {
                        $these_forms[] = $this_form;
                    }
                }
                // If some forms are not provided, then by default set their rights to 0.
                $missing_forms = array_diff(array_keys($Proj->forms), $these_forms);
                foreach ($missing_forms as $this_form) {
                    if (!is_string($this_form)) continue;
                    $this_role['forms_export'][$this_form] = 0;
                }
                // Reformat form-level rights to back-end format
                $data_entry = "";
                foreach ($this_role['forms_export'] as $this_form=>$this_val) {
                    $data_entry .= "[$this_form,$this_val]";
                }
                $this_role['forms_export'] = $data_entry;
            }
        }

        if (!empty($invalidRoles)) {
            $errors[] = $lang['rights_406']." ".implode(", ", $invalidRoles);
        }
        unset($this_role);
        $keysSetDefault = array('dts', 'realtime_webservice_mapping', 'realtime_webservice_adjudicate');
        if (empty($errors))
        {
            foreach($data as $ur)
            {
                if (isset($ur['forms_preview'])) {
                    $form_rights = explode(",", $ur['forms_preview']);
                    foreach ($form_rights as $right) {
                        list($key, $value) = explode(":", $right);
                        $_POST["form-" . $key] = $value;
                    }
                }
                if (isset($ur['forms_export_preview'])) {
                    $form_export_rights = explode(",", $ur['forms_export_preview']);
                    foreach ($form_export_rights as $right) {
                        list($key, $value) = explode(":", $right);
                        $_POST["export-form-" . $key] = $value;
                    }
                }
                if (isset($ur['forms'])) {
                    $form_rights = self::convertFormRightsToArray($ur['forms']);
                    foreach ($form_rights as $this_form=>$right) {
                        $_POST["form-" . $this_form] = $right;
                    }
                }
                if (isset($ur['forms_export'])) {
                    $form_rights = self::convertFormRightsToArray($ur['forms_export']);
                    foreach ($form_rights as $this_form=>$right) {
                        $_POST["export-form-" . $this_form] = $right;
                    }
                }
                foreach ($rights_fields as $rights => $rights_formatted) {
                    if (is_numeric($rights)) {
                        $_POST[$rights_formatted] = $ur[$rights_formatted] ?? '0';
                    } else {
                        $_POST[$rights_formatted] = $ur[$rights] ?? '0';
                    }
                }
                foreach ($keysSetDefault as $key) {
                    $_POST[$key] = 0;
                }
                $_POST['expiration'] = $_POST['group_role'] = "";

                if (SUPER_USER) {
                    $_POST['external_module_config'] = '';
                }

                if(empty($ur['unique_role_name']))
                {
                    $role_name = strip_tags(html_entity_decode($ur['role_label'], ENT_QUOTES));
                    if (self::addRole($Proj, $role_name, ''))
                    {
                        $count++;
                    }
                }
                else
                {
                    $role_name = html_entity_decode($ur['role_label'], ENT_QUOTES);
                    $role_id = array_search($ur['unique_role_name'], $roleArr);
                    if (self::editRole($Proj, $role_name, $role_id))
                    {
                        $count++;
                    }
                }
            }
        }

        // Return count and array of errors
        return array($count, $errors);
    }

    // Update User-Role Assignment for a given project
    // Return array with count of mappings updated and array of errors, if any
    public static function uploadUserRoleMappings($project_id, $data)
    {
        global $lang, $user_rights;

        $count = 0;
        $errors = array();
        $invalidRoles = array();
        $invalidUsers = array();

        $Proj = new Project($project_id);
        // Check for basic attributes needed
        if (empty($data) || !isset($data[0]['username']) || !isset($data[0]['unique_role_name'])) {
            $errors[] = $lang['design_641'] . " username, unique_role_name";
        } else {
            $projectUsers = UserRights::getPrivileges($project_id);
            $dags = $Proj->getUniqueGroupNames();
            $row_count = array();

            foreach($data as $mapping) {
                $username = trim($mapping['username']);
                $group_id = $projectUsers[$project_id][strtolower($username)]['group_id'] ?? null;
                $unique_role_name = trim($mapping['unique_role_name']);
                $row_count[$username]++;
                if (!SUPER_USER && !empty($user_rights['group_id']) && $user_rights['group_id'] != $group_id) {
                    $invalidUsers[] = $username;
                    continue;
                }
                if ($username == '') {
                    $errors[] = "{$lang['api_174']} \"{$unique_role_name}\" {$lang['design_638']}";
                    continue;
                }
                if (strlen($username) > 255) {
                    $errors[] = "{$lang['pwd_reset_25']} \"{$username}\" {$lang['design_835']}";
                    continue;
                }
                if ($row_count[$username] > 1) {
                    $errors[] = "{$lang['pwd_reset_25']} \"{$username}\" {$lang['data_access_groups_ajax_53']} ";
                    continue;
                }
                if ($unique_role_name != '' && !$Proj->uniqueRoleNameExists($unique_role_name)) {
                    $invalidRoles[] = $unique_role_name;
                    continue;
                }
            }
            if (!empty($invalidUsers)) {
                $errors[] = $lang['rights_422']." ".implode(", ", $invalidUsers);
            }
            if (!empty($invalidRoles)) {
                $errors[] = $lang['rights_406']." ".implode(", ", $invalidRoles);
            }
            unset($mapping);
            if (empty($errors))
            {
                $roles = $Proj->getUniqueRoleNames();
                foreach ($data as $ur) {
                    $username = $ur['username'];
                    if ($username != '') {
                        $dag_name = $ur['data_access_group'] ?? "";
                        $unique_role_name = trim($ur['unique_role_name']);
                        // if $unique_group_name is non-empty, No need to check if group exists as already handled in validation
                        $role_id = ($unique_role_name != '') ? array_search($unique_role_name, $roles) : 'NULL';
                        // If user doesn't exist in project yet, then add first to user_rights table
                        if (!isset($projectUsers[$project_id][strtolower($username)])) {
                            $rights = ['username' => strtolower($username)];
                            foreach (self::getApiUserPrivilegesAttr() as $key){
                                if (!isset($rights[$key])) {
                                    $rights[$key] = null;
                                }
                            }
                            // Add to project
                            self::addPrivileges($project_id, $rights);
                        }
                        // Add user to role
                        self::updateUserRoleMapping($project_id, $username, $role_id);
                        // Add user to DAG, if provided
                        if ($dag_name != "" && in_array($dag_name, $dags))
                        {
                            $group_id = array_search($dag_name, $dags);
                            $sql = "update redcap_user_rights set group_id = ? where project_id = ? and username = ?";
                            $q = db_query($sql, [$group_id, $project_id, $username]);
                            // Logging
                            if ($q) Logging::logEvent($sql, "redcap_user_rights", "MANAGE", $username, "user = '$username',\ngroup = '" . $dag_name . "'", "Assign user to data access group");
                        }
                        // Continue to next loop
                        ++$count;
                        continue;
                    }
                }
            }
        }

        // Return count and array of errors
        return array($count, $errors);
    }

    // Update User-Role assignment
    public static function updateUserRoleMapping($project_id, $username, $role_id)
    {
        $project_id = (int)$project_id;
		$role_id = isinteger($role_id) ? $role_id : "null";

        $sql = "
			UPDATE redcap_user_rights
			SET	role_id = $role_id
			WHERE username = '".db_escape($username)."'
			AND project_id = $project_id
			LIMIT 1
		";

        $q = db_query($sql);
        if ($q) {
			$roles = UserRights::getRoles($project_id);
			$role_name = $roles[$role_id]['role_name'];
        	// Log it
			Logging::logEvent($sql,"redcap_user_rights","insert",$username,"user = '$username',\nrole = '$role_name'","Assign user to role");
        	return true;
		} else {
        	return false;
		}
    }

    /**
     * Return array of attributes to be imported/export for user roles via API User Role Import/Export
     */
    public static function getApiUserRolesAttr($returnAllAttr = false, $project_id=null)
    {
        global $mycap_enabled_global, $mycap_enabled;
        $attrInfo = array('unique_role_name', 'role_label');
        $attr = array('design', 'alerts', 'user_rights', 'data_access_groups',
            'reports', 'graphical' => 'stats_and_charts',
            'participants' => 'manage_survey_participants', 'calendar',
            'data_import_tool', 'data_comparison_tool', 'data_logging' => 'logging', 'email_logging',
            'file_repository', 'data_quality_design' => 'data_quality_create', 'data_quality_execute',
            'api_export', 'api_import', 'api_modules',
            'mobile_app', 'mobile_app_download_data',
            'record_create', 'record_rename', 'record_delete',
            'lock_record_customize' => 'lock_records_customization', 'lock_record' => 'lock_records', 'lock_record_multiform' => 'lock_records_all_forms');

        if ($mycap_enabled_global && $mycap_enabled) {
            $attr['mycap_participants'] = 'mycap_participants';
        }
        $attr['data_entry'] = 'forms';
        $attr['data_export_instruments'] = 'forms_export';

        if ($project_id !== null) {
            $Proj = new Project($project_id);
            if ($Proj->project['data_resolution_enabled'] == '2') {
                $attr[] = 'data_quality_resolution';
            }
            if ($Proj->project['double_data_entry'] == '1') {
                $attr[] = 'double_data';
            }
            if ($Proj->project['randomization'] == '1') {
                $attr[] = 'random_setup';
                $attr[] = 'random_dashboard';
                $attr[] = 'random_perform';
            }
        }
        if ($returnAllAttr) {
            $attr = array_merge($attrInfo, $attr);
        }
        return $attr;
    }

    /** Returns a count of the number of API tokens assigned on the given project. */
    public static function countAPITokensByProject($project_id)
    {
        $sql = "SELECT COUNT(*) FROM redcap_user_rights
                WHERE project_id = ? AND api_token IS NOT NULL";
        $q = db_query($sql, $project_id);
        return db_result($q, 0);
    }

    /**
     * Gets a user's API token for a given project.
     * @param type $username
     * @param type $project_id
     * @return string the API token or null if it does not exist.
     */
    public static function getAPIToken($username, $project_id)
    {
        $sql = "SELECT api_token FROM redcap_user_rights WHERE username = ? AND project_id = ?";
        $q = db_query($sql, [$username, $project_id]);
        return db_result($q, 0);
    }

    // Determine if current user can delete an entire record or part of any record (instrument-level data). Return boolean.
    public static function canDeleteWholeOrPartRecord()
    {
        global $user_rights;
		// Ignore this for the bg processing cron job
		if (defined("CRON")) return true;
        // If user has record delete rights, then return true
        return ($user_rights['record_delete'] == '1' || UserRights::hasDataDeleteRightsAnyForm());
    }

    // Determine if current user can delete the instrument-level data for at least one form. Return boolean.
    public static function hasDataDeleteRightsAnyForm()
    {
        global $user_rights, $Proj;
		// Ignore this for the bg processing cron job
		if (defined("CRON")) return true;
        // If user has record delete rights for at least ONE form, then return true
	    foreach ($user_rights['forms'] as $form=>$rights) {
		    if (isset($Proj->forms[$form]) && UserRights::hasDataViewingRights($rights, "delete")) {
				return true;
		    }
	    }
        return false;
    }

	/** List of valid data viewing rights */
	const DATA_VIEWING_RIGHTS = array('no-access', 'read-only', 'view-edit', 'editresp', 'delete');

	/**
	 * Determine if current user has the specified data viewing rights
	 * This is a direct mapping to the "Data Viewing Rights" table.
	 * @param string|int $value 
	 * @param string $right One of: 'no-access', 'read-only', 'view-edit', 'editresp', 'delete'
	 * @throws Exception
	 * @return bool 
	 */
	public static function hasDataViewingRights($value, $right) {
		if (!in_array($right, self::DATA_VIEWING_RIGHTS, true)) {
			throw new Exception("Invalid data viewing right: " . $right);
		}
		// When invalid value, return true for "no-access", else false
		if (!is_numeric($value) || $value === "") return $right == 'no-access';
		$value = intval($value);
		$grant = false;
		if ($value < 128) {
			// Legacy:
			// 0 = no-access, 1 = view-edit, 2 = read-only, 3 = editresp
			switch ($right) {
				case 'no-access':
					$grant = ($value == 0);
					break;
				case 'read-only':
					$grant = ($value == 2);
					break;
				case 'view-edit':
					$grant = ($value == 1) || ($value == 3);
					break;
				case 'editresp':
					$grant = ($value == 3);
					break;
				case 'delete':
					$grant = false;
					break;
			}
		}
		else {
			// New bitmask:
			// 128 = Marker for new bitmask
			// 1 = read-only, 2 = view-edit, 8 = editresp, 16 = delete
			switch($right) {
				case 'no-access':
					$grant = ($value == 128);
					break;
				case 'read-only':
					$grant = ($value & 1) == 1;
					break;
				case 'view-edit':
					$grant = ($value & 2) == 2;
					break;
				case 'editresp':
					$grant = ($value & 2) == 2 && ($value & 8) == 8;
					break;
				case 'delete':
					$grant = ($value & 2) == 2 && ($value & 16) == 16;
					break;
			}
		}
		return $grant;
	}

	/**
	 * Encode data viewing rights into a single value
	 * @param string|array<string> $rights 
	 * @return string 
	 * @throws Exception 
	 */
	public static function encodeDataViewingRights($rights) : string {
		if (!is_array($rights)) $rights = array($rights); // Convert to array if not already
		$value = 128;
		// Check that all rights are valid
		foreach ($rights as $right) {
			if (!in_array($right, self::DATA_VIEWING_RIGHTS)) {
				throw new Exception("Invalid data viewing right: " . $right);
			}
		}
		// Base rights
		if (in_array("view-edit", $rights)) {
			$value += 2;
		}
		else if (in_array("read-only", $rights)) {
			$value += 1;
		}
		// Only for view-edit consider additional rights
		if ($value == 130) {
			if (in_array("editresp", $rights)) {
				$value += 8;
			}
			if (in_array("delete", $rights)) {
				$value += 16;
			}
		}
		return (string)$value;
	}

	/**
	 * Convert new bitmask data viewing rights to legacy value
	 * @param string $value 
	 * @return string 
	 */
	public static function convertToLegacyDataViewingRights(string $value) : string {
		if ($value === null || !is_string($value) || $value == "") return "0";
		$value = intval($value);
		if ($value < 128) {
			// Already legacy - simply return
			return (string)$value;
		}
		// Convert bit mask to legacy value
		// 128 = Marker for new bitmask
		// 1 = read-only, 2 = view-edit, 8 = editresp, 16 = delete
		// Legay: 0 = no-access, 1 = view-edit, 2 = read-only, 3 = editresp
		if ($value == 128) return "0";
		if ($value == 129) return "2";
		// View & Edit + Edit Survey Responses
		if (($value & 8) == 8) return "3";
		// View & Edit
		return "1";
	}

	public static function isValidDataViewingRightsValue($value) {
		if (!is_numeric($value)) return false;
		$value = $value * 1;
		if (intval($value) != $value) return false;
		// Legacy
		if ($value > -1 && $value < 4) return true;
		// Bitmask
		// We limit to the currently possible (i.e., meaningful) values
		if (in_array($value, [128, 129, 130, 138, 146, 154])) return true;
		return false;
	}
}
