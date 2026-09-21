<?php
try {
    $error = false;
    // Required files
    require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

	## PERFORMANCE: Kill any currently running processes by the current user/session on THIS page
	System::killConcurrentRequests(30, 5);

    // Instantiate DataQuality object
    $dq = new DataQuality();

    $rule_id = $_GET['rule_id'];
	$isRuleAorB = ($rule_id == 'pd-3' || $rule_id == 'pd-6');

    // Execute this rule
    $dq->executeRule($rule_id, $_GET['record'], ($_GET['dag']??null), true);

    // Get the html for the results table data
    $discrepancies = $dq->getResultsExport($isRuleAorB);

    // Get project information
    $Proj = new Project();

    $dags = $Proj->getGroups();

	$default_header = $Proj->table_pk . ',';
	if ($Proj->longitudinal) {
		$default_header .= 'redcap_event_name,';
	}
    if ($Proj->hasRepeatingFormsEvents()) {
        $default_header .= 'redcap_repeat_instrument,redcap_repeat_instance,';
    }
	if (!empty($dags)) {
		$default_header .= 'redcap_data_access_group,';
	}
    $default_header .= 'result-status,result-is-excluded';

    $content = (!empty($discrepancies)) ? arrayToCsv($discrepancies) : $default_header;
    $project_title = REDCap::getProjectTitle();

    $rule_info = $dq->getRule($rule_id);
    $rule_name = strip_tags($rule_info['name']);
    $rule_name = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($rule_name, ENT_QUOTES)))), 0, 30);

    $filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($project_title, ENT_QUOTES)))), 0, 30)
        ."_DataQualityDiscrepancies_".$rule_name."_".date("Y-m-d").".csv";
} catch(Exception $e) {
    $error = true;
}

if ($error == false) {
	Logging::logEvent("", "redcap_data_quality_rules", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Download data quality results");
    header('Pragma: anytextexeptno-cache', true);
    header("Content-type: application/csv");
    header('Content-Disposition: attachment; filename=' . $filename);
    echo addBOMtoUTF8($content);
    savecookie('fileDownload', true);
} else {
    savecookie('fileDownload', false);
}