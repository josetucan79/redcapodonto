<?php

// Check if coming from survey or authenticated form
if (isset($_GET['s']) && !empty($_GET['s']))
{
	// Call config_functions before config file in this case since we need some setup before calling config
	require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
	// Validate and clean the survey hash, while also returning if a legacy hash
	$hash = $_GET['s'] = Survey::checkSurveyHash();
	// Set all survey attributes as global variables
	Survey::setSurveyVals($hash);
	// Now set $_GET['pid'] before calling config
	$_GET['pid'] = $project_id;
	// Set flag for no authentication for survey pages
    defined("NOAUTH") or define("NOAUTH", true);
}


// Required files
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
$draft_preview_enabled = Design::isDraftPreview();
$Proj_metadata = $draft_preview_enabled ? $Proj->metadata_temp : $Proj->metadata;
$instance = (isset($_GET['instance']) && isinteger($_GET['instance']) && $_GET['instance'] > 1) ? $_GET['instance'] : 1;
$file_upload_delete_reason = (Files::fileUploadPasswordVerifyExternalStorageEnabledProject($project_id) && isset($_POST['file_upload_delete_reason']) && trim($_POST['file_upload_delete_reason']) != '') ? trim($_POST['file_upload_delete_reason']) : '';

// Older version of a file via File Version History in Data History popup
$version_log = $version_log2 = '';
$isOlderVersion = false;
if (isset($_GET['doc_version']) && isinteger($_GET['doc_version']) && isset($_GET['doc_version_hash']))
{
    if ($_GET['doc_version_hash'] != Files::docIdHash($_GET['id']."v".$_GET['doc_version'])) {
        exit(RCView::getLangStringByKey("global_01")); // MLM - No translation
    }
    $version_log = " (V{$_GET['doc_version']})";
    $version_log2 = " - V{$_GET['doc_version']}";
	$isOlderVersion = true;
}
// Surveys only: Perform double checking to make sure the survey participant has rights to this file
elseif (isset($_GET['s']) && !empty($_GET['s']))
{
	DataEntry::checkSurveyFileRights();
}
// Non-surveys: Check form-level rights and DAGs to ensure user has access to this file
elseif (!isset($_GET['s']) || empty($_GET['s']))
{
	DataEntry::checkFormFileRights();
}


if (isinteger($_GET['event_id']) && isinteger($_GET['id']) && isset($Proj_metadata[$_GET['field_name']]))
{
	// If user is a double data entry person, append --# to record id when saving
	if (isset($user_rights) && $double_data_entry && $user_rights['double_data'] != 0)
	{
		$_GET['record'] .= "--" . $user_rights['double_data'];
	}

	// If an older version, then set as deleted in edocs table
	if ($isOlderVersion) {
		if ($draft_preview_enabled) {
			// We must not allow deletion of any files that have not been added during draft preview mode!
			if (!Design::isDraftPreviewStoredFile($project_id, $_GET['id'])) {
				exit(RCView::getLangStringByKey("global_01"));
			}
			Design::removeDraftPreviewStoredFile($project_id, $_GET['id']);
		}
        $sql = "UPDATE redcap_edocs_metadata SET delete_date = '" . NOW . "' WHERE doc_id = " . $_GET['id'];
		$q = db_query($sql);
		Logging::logEvent($sql,"redcap_data","doc_delete",$_GET['record'],$_GET['field_name'],"Delete uploaded document".$version_log, "", "", "", true, null, $_GET['instance']);
    }

    if ($file_upload_delete_reason != '') {
        // Log this extra event (but not for surveys)
        defined("NOAUTH") or Logging::logEvent("", "redcap_data", "update", $_GET['record'], "Reason for document deletion (field = '{$_GET['field_name']}'$version_log2):\n$file_upload_delete_reason", "",
            "", "", "", true, null, $_GET['instance']);
    }

	// Boolean if a signature file upload type
	$signature_field = ($Proj_metadata[$_GET['field_name']]['element_validation_type'] == 'signature') ? '1' : '0';

	// Link text
	$file_link_text = ($signature_field) ? RCView::tt("form_renderer_31") : RCView::tt("form_renderer_23");
	$file_link_icon = ($signature_field) ? '<i class="fas fa-signature me-1"></i>' : '<i class="fas fa-upload me-1 fs12"></i>';

	// Send back HTML for uploading a new file (since this one has been removed)
	print  '<a href="javascript:;" class="fileuploadlink" onclick="filePopUp(\''.$_GET['field_name'].'\','.$signature_field.',0);return false;">'.$file_link_icon.$file_link_text.'</a>';
	// Add a script that refreshes translations
	print '<script>if(window.REDCap && window.REDCap.MultiLanguage) { window.REDCap.MultiLanguage.updateUI(); }</script>';
}