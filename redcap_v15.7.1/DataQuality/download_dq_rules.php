<?php
// Required files
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

Logging::logEvent("", "redcap_data_quality_rules", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Download Data Quality Rules");

// Instantiate DataQuality object
$dq = new DataQuality();
$dq_rules = $dq->getRulesRecords();
$delimiter = User::getCsvDelimiter();

$content = (!empty($dq_rules)) ? arrayToCsv($dq_rules) : "rule_name{$delimiter}rule_logic{$delimiter}real_time_execution";

$project_title = REDCap::getProjectTitle();
$filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($project_title, ENT_QUOTES)))), 0, 30)
    ."_DataQualityRules_".date("Y-m-d").".csv";

header('Pragma: anytextexeptno-cache', true);
header("Content-type: application/csv");
header('Content-Disposition: attachment; filename=' . $filename);
echo addBOMtoUTF8($content);
exit;