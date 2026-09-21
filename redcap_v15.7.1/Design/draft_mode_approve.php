<?php

use MultiLanguageManagement\MultiLanguage;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Set up all actions as a transaction to ensure everything is done here
db_query("SET AUTOCOMMIT=0");
db_query("BEGIN");


// Retrieve pr_id from metadata production revisions table for this revision (where ts_approved is NULL)
$sql = "select pr_id from redcap_metadata_prod_revisions where project_id = $project_id and ts_approved is null limit 1";
$q = db_query($sql);
$readyToApprove = (db_num_rows($q) > 0);
if ($readyToApprove) {
	$pr_id = db_result($q, 0);
}

// Were calculations changed?
$calcs_changed = (Form::changedCalculationsWithData() ? 1 : 0);


// Only allow changes to be made if user is Super User and status=waiting approval
if (($super_user || ($auto_prod_changes > 0 && isset($_GET['auto_change_token']) && $_GET['auto_change_token'] == sha1($__SALT__)))
	&& $status == 1 && $draft_mode == 2 && $readyToApprove)
{
	// In case 2 users are working at same time, prevent the metadata table from being wiped out
	$q = db_query("select count(1) from redcap_metadata_temp where project_id = $project_id");
	if ($q && db_result($q, 0) == '0') {
		// Now set draft_mode back to "0" to put it back into pre-Draft Mode
		$sql = "update redcap_projects set draft_mode = 0 where project_id = $project_id";
		$q = db_query($sql);
		// Commit changes
		db_query("COMMIT");
		db_query("SET AUTOCOMMIT=1");
		// Errors occurred
		redirect(APP_PATH_WEBROOT . "Design/project_modifications.php?pid=$project_id&ref=Design/online_designer.php");
	}

	// First, move all existing metadata fields to metadata_archive table as a backup
    $metadataCols = implode(", ", array_keys(getTableColumns('redcap_metadata')));
	$sql = "insert into redcap_metadata_archive ($metadataCols, pr_id) select $metadataCols, '$pr_id' from redcap_metadata where project_id = $project_id";
	$q1 = db_query($sql);

	## User Rights for form-level rights for new/removed forms
    // If in production, do all users get "no access" by default?
	$newFormRight = ($Proj->project['status'] == '0' || ($GLOBALS['new_form_default_prod_user_access'] == '1' || $GLOBALS['new_form_default_prod_user_access'] == '2')) ? "1" : "0";
	$newFormExportRight = ($Proj->project['status'] == '0' || $GLOBALS['new_form_default_prod_user_access'] == '1') ? "1" : ($GLOBALS['new_form_default_prod_user_access'] == '2' ? "2" : "0");
	// Build array for values to be set
	$set_vals = array();
	$set_vals_string = "";
	$set_vals_export_string = "";
	// Set form-level rights as "1" for all users for any new forms
	$sql = "select distinct(form_name) from redcap_metadata_temp where project_id = $project_id and
			form_name not in (" . pre_query("select form_name from redcap_metadata where project_id = $project_id") . ")";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		$set_vals_string .= "[{$row['form_name']},$newFormRight]";
		$set_vals_export_string .= "[{$row['form_name']},$newFormExportRight]";
	}
	if ($set_vals_string != "") {
		$set_vals[] = "data_entry = concat(data_entry, '$set_vals_string')";
        $set_vals[] = "data_export_instruments = concat(data_export_instruments, '$set_vals_export_string')";
	}
	// Delete form-level rights from all users for any deleted forms
	$sql = "select distinct(form_name) from redcap_metadata where project_id = $project_id and
			form_name not in (" . pre_query("select distinct(form_name) from redcap_metadata_temp where project_id = $project_id") . ")";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		// Get name of form to be deleted
		$deleted_form = $row['form_name'];
		// Delete form from all tables EXCEPT metadata tables and user_rights table
		Form::deleteFormFromTables($deleted_form);
		// Catch all 3 possible instances of form-level rights (0, 1, and 2)
		for ($i = 0; $i <= 2; $i++) {
			$set_vals[] = "data_entry = replace(data_entry, '[$deleted_form,$i]', '')";
			$set_vals[] = "data_export_instruments = replace(data_export_instruments, '[$deleted_form,$i]', '')";
		}
	}
	// Run query to adjust form-level rights
	$q7 = true;
	if (!empty($set_vals)) {
		$q7 = db_query("update redcap_user_rights set " . implode(", ", $set_vals) . " where project_id = $project_id");
		db_query("update redcap_user_roles set " . implode(", ", $set_vals) . " where project_id = $project_id");
	}

	## REMOVE FOR MULTIPLE SURVEYS????? (Should we ALWAYS assume that if first form is a survey that we should preserve first form as survey?)
	// If using first form as survey and form is renamed in DD, then change form_name in redcap_surveys table to the new form name
	/* 
	if (isset($Proj->forms[$Proj->firstForm]['survey_id']))
	{
		$sql = "select form_name from redcap_metadata_temp where project_id = " . PROJECT_ID . " order by field_order limit 1";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			$newFirstForm = db_result($q, 0);
			// Do not rename in table if the new first form is ALSO a survey (assuming it even exists)
			if ($Proj->firstForm != $newFirstForm && !isset($Proj->forms[$newFirstForm]['survey_id']))
			{
				// Change form_name of survey to the new first form name
				$sql = "update redcap_surveys set form_name = '$newFirstForm' where survey_id = ".$Proj->forms[$Proj->firstForm]['survey_id'];
				db_query($sql);
			}
		}
	}
	*/
	
	// If "now" was passed in query string (from previous script that was redirected, use it instead of NOW to maintain timestamp synchronicity)
	$now = (isset($_GET['now'])) ? urldecode($_GET['now']) : NOW;

	## DELETE UNUSED EDOCS
	// Check for any edocs that have been deleted in Draft Mode and set them as deleted in edocs table
	$sql = "update redcap_edocs_metadata set delete_date = '$now' where doc_id in ("
		 . pre_query("select m.edoc_id from redcap_metadata m, redcap_metadata_temp t where m.project_id = $project_id
					  and m.project_id = t.project_id and m.field_name = t.field_name
					  and m.edoc_id is not null and (m.edoc_id != t.edoc_id or t.edoc_id is null)")
		 . ") and project_id = $project_id";
	$q8 = db_query($sql);
	// If a field was deleted in Draft Mode, then set its edoc as deleted in edocs table
	$sql = "update redcap_edocs_metadata set delete_date = '$now' where doc_id in ("
		 . pre_query("select edoc_id from redcap_metadata where project_id = $project_id and edoc_id is not null and field_name
					  not in (select field_name from redcap_metadata_temp where project_id = $project_id)")
		 . ") and project_id = $project_id";
	$q9 = db_query($sql);

	// Now delete all fields from metadata table now that they've been archived
	$q2 = db_query("delete from redcap_metadata where project_id = $project_id");
	// As well as from the less important tables -- Rob: Do we need archives for these as well?
	$q2_forms = db_query("DELETE FROM redcap_forms WHERE project_id = $project_id");
	$q2_mlm_1 = db_query("DELETE FROM redcap_multilanguage_metadata WHERE project_id = $project_id");
	$q2_mlm_2 = db_query("DELETE FROM redcap_multilanguage_config WHERE project_id = $project_id");
	$q2_mlm_3 = db_query("DELETE FROM redcap_multilanguage_ui WHERE project_id = $project_id");
	$q2 = $q2 && $q2_forms && $q2_mlm_1 && $q2_mlm_2 && $q2_mlm_3;

	// Move all existing metadata temp fields to metadata table
	$q3 = db_query("insert into redcap_metadata (".formatTableColumnsForQuery("redcap_metadata").") select ".formatTableColumnsForQuery("redcap_metadata_temp")." from redcap_metadata_temp where project_id = $project_id");
	$q3_forms = db_query("insert into redcap_forms (".formatTableColumnsForQuery("redcap_forms").") select ".formatTableColumnsForQuery("redcap_forms_temp")." from redcap_forms_temp where project_id = $project_id");
	$q3_mlm_1 = db_query("INSERT INTO redcap_multilanguage_metadata (
			`project_id`, `lang_id`, `type`, `name`, `index`, `hash`, `value`
		)
		SELECT 
			`project_id`, `lang_id`, `type`, `name`, `index`, `hash`, `value`
		FROM redcap_multilanguage_metadata_temp 
		WHERE project_id = $project_id"
	);
	$q3_mlm_2 = db_query("INSERT INTO redcap_multilanguage_config (
			`project_id`, `lang_id`, `name`, `value`
		)
		SELECT 
			`project_id`, `lang_id`, `name`, `value`
		FROM redcap_multilanguage_config_temp 
		WHERE project_id = $project_id"
	);
	$q3_mlm_3 = db_query("INSERT INTO redcap_multilanguage_ui (
			`project_id`, `lang_id`, `item`, `hash`, `translation`
		)
		SELECT 
			`project_id`, `lang_id`, `item`, `hash`, `translation`
		FROM redcap_multilanguage_ui_temp 
		WHERE project_id = $project_id"
	);
	$q3 = $q3 && $q3_forms && $q3_mlm_1 && $q3_mlm_2 && $q3_mlm_3;

	// Now delete all fields from metadata temp table now that they've been committed to metadata table
	$q4 = db_query("delete from redcap_metadata_temp where project_id = $project_id");
	$q4_forms = db_query("delete from redcap_forms_temp where project_id = $project_id");
	$q4_mlm_1 = db_query("DELETE FROM redcap_multilanguage_metadata_temp WHERE project_id = $project_id");
	$q4_mlm_2 = db_query("DELETE FROM redcap_multilanguage_config_temp WHERE project_id = $project_id");
	$q4_mlm_3 = db_query("DELETE FROM redcap_multilanguage_ui_temp WHERE project_id = $project_id");
	$q4 = $q4 && $q4_forms && $q4_mlm_1 && $q4_mlm_2 && $q4_mlm_3;

    if ($mycap_enabled_global == 1 && $mycap_enabled == 1) { // Update languages attribute of config JSON only for mycap-enabled projects
        $myCapProj = new MyCap($project_id);
        $return = $myCapProj->updateMLMConfigJSON($project_id);
    }
	// Now set draft_mode back to "0" since the changes were approved
	$q5 = db_query("update redcap_projects set draft_mode = 0 where project_id = $project_id");

	// Set ts_approved value in metadata production revisions table
	$q6 = db_query("update redcap_metadata_prod_revisions set ts_approved = '$now',
					   ui_id_approver = (select ui_id from redcap_user_information where username = '".db_escape($userid)."' limit 1)
					   where project_id = $project_id and ts_approved is null");

	// Finalize transaction
	if (!$q1 || !$q2 || !$q3 || !$q4 || !$q5 || !$q6 || !$q7 || !$q8 || !$q9)
	{
		// Errors occurred
		db_query("ROLLBACK");
		redirect(APP_PATH_WEBROOT . "Design/project_modifications.php?pid=$project_id");
	}
	else
	{
		// ALL GOOD - COMMIT CHANGES!
		db_query("COMMIT");
		db_query("SET AUTOCOMMIT=1");

		// SURVEY QUESTION NUMBERING: Detect if any forms are a survey, and if so, if has any branching logic. If so, disable question auto numbering.
		foreach (array_keys($Proj->surveys) as $this_survey_id)
		{
			if ($Proj->surveys[$this_survey_id]['question_auto_numbering'] && Design::checkSurveyBranchingExists($Proj->surveys[$this_survey_id]['form_name']))
			{
				// Survey is using auto question numbering and has branching, so set to custom numbering
				$sql = "update redcap_surveys set question_auto_numbering = 0 where survey_id = $this_survey_id";
				db_query($sql);
			}
		}

		// Logging
		$logTextAppend = (isset($_GET['auto_change_token'])) ? " (automatic)" : "";
		Logging::logEvent("","redcap_projects","MANAGE",$project_id,"project_id = $project_id","Approve production project modifications".$logTextAppend);

		// MLM: Automatically create a snapshot (only when active and when there are any languages)
		if (MultiLanguage::isActive($Proj->project_id) && MultiLanguage::hasLanguages($Proj->project_id)) {
			MultiLanguage::createSnapshot($Proj->project_id, USERID);
		}

		// Set email contents based upon this is a super user OR a normal user with auto prod changes enabled
		$emailContents = "";
		if (!isset($_GET['auto_change_token'])) {
			// Super user
			$emailContents = $lang['draft_mode_02'].'
				"<a href="'.APP_PATH_WEBROOT_FULL.'redcap_v'.$redcap_version.'/index.php?pid='.$project_id.'">'.$app_title.'</a>"
				'.$lang['draft_mode_03']
				.'<br /><br />'.($GLOBALS['new_form_default_prod_user_access'] == '1' ? $lang['draft_mode_32'] : ($GLOBALS['new_form_default_prod_user_access'] == '2' ? $lang['draft_mode_33'] : $lang['draft_mode_31']))
				.'<br /><br />'.$lang['draft_mode_04'].' '.$user_firstname.' '.$user_lastname.' '.$lang['draft_mode_05'];
		}
		// Add extra text to email if calculations were changed
		if ($calcs_changed) {
			$emailContents .= '<br /><br />'.RCView::b($lang['design_516'])." ".$lang['design_517'];
		}
		// Add extra text to email about publishing new MyCap version, if applicable
		if ($mycap_enabled_global && $mycap_enabled) {
			$emailContents .= '<br /><br />'.$lang['mycap_mobile_app_678'];
		}

		## SEND EMAIL BACK TO USER TO INFORM THEM THAT CHANGES WERE COMMITTED
		//Get user info for email
		$q = db_query("select u.username, u.user_firstname, u.user_lastname, u.user_email from redcap_metadata_prod_revisions r, redcap_projects p,
						  redcap_user_information u where p.project_id = r.project_id and p.project_id = $project_id and r.ts_approved is not null
						  and u.ui_id = r.ui_id_requester order by r.ts_approved desc limit 1");
		$srow = db_fetch_array($q);
		// If user here is also the requester (and is super user) or if changes were automatically approved, then don't send confirmation email -> superfluous.
		if (!($super_user && $userid == $srow['username']) && !isset($_GET['auto_change_token']))
		{
			//Email the user
			$email = new Message();
			$email->setTo($srow['user_email']);
			$email->setFrom($GLOBALS['project_contact_email']);
			$email->setFromName($GLOBALS['project_contact_name']);
			$email->setSubject('[REDCap] ' . $lang['draft_mode_07']);
			$email->setBody($emailContents, true);
			if (!$email->send())
			{
				print "<div style='width:600px;font-size:13px;'><b><u>{$lang['draft_mode_06']} {$srow['user_email']}{$lang['colon']}</u></b><br><br>";
				exit($emailContents);
			}
		}
		
		if (!isset($_GET['auto_change_token'])) { // Don't update To-Do List status if auto-approving itself
			ToDoList::updateTodoStatus($project_id, 'draft changes','completed');
		}

		// AUTO CHANGES: If changes made automatically, then redirect here (no need to send email)
		if (isset($_GET['auto_change_token']))
		{
			redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&msg=autochangessaved&calcs_changed=$calcs_changed");
		}
	}

}

redirect(APP_PATH_WEBROOT . "Design/draft_mode_notified.php?action=approve&pid=$project_id&user_email={$srow['user_email']}&user_firstname={$srow['user_firstname']}&user_lastname={$srow['user_lastname']}");
