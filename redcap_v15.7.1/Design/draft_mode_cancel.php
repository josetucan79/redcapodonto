<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if ($status > 0)
{
	// First, delete all fields for this project in metadata temp table (i.e. in Draft Mode)
	$q1 = db_query("delete from redcap_metadata_temp where project_id = $project_id");
	$q1_forms = db_query("delete from redcap_forms_temp where project_id = $project_id");
	$q1_mlm1 = db_query("delete from redcap_multilanguage_metadata_temp where project_id = $project_id");
	$q1_mlm2 = db_query("delete from redcap_multilanguage_config_temp where project_id = $project_id");
	$q1_mlm3 = db_query("delete from redcap_multilanguage_ui_temp where project_id = $project_id");
	$q1 = $q1 && $q1_forms && $q1_mlm1 && $q1_mlm2 && $q1_mlm3;
	
    $Proj = new Project($project_id);
    $newForms = array_diff(array_keys($Proj->forms_temp??[]), array_keys($Proj->forms??[]));
    foreach ($newForms as $form) {

        // Delete entry from tasks table in case of added form is Active task or MTB task
        db_query("delete from redcap_mycap_tasks where project_id = $project_id and form_name = '".$form."'");
        $sql = "delete from redcap_mycap_tasks_schedules where task_id in (" . pre_query("SELECT task_id FROM redcap_mycap_tasks WHERE project_id = " . $project_id . " AND form_name = '".db_escape($form)."'") . ")";
        db_query($sql);
    }

	// Now set draft_mode to "0"
	$q2 = db_query("update redcap_projects set draft_mode = 0 where project_id = $project_id");

	// Logging
	Logging::logEvent("","redcap_projects","MANAGE",$project_id,"project_id = $project_id","Cancel draft mode");

	// Also cancel any draft preview mode that may be on
	Design::cancelDraftPreview($project_id);
}

// Redirect back to previous page
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
	redirect($_SERVER['HTTP_REFERER'] . "&msg=cancel_draft_mode");
} else {
	// If can't find referer, just send back to Online Designer
	redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&msg=cancel_draft_mode");
}
