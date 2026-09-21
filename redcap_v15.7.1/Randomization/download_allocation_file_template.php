<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$rid = Randomization::getRid($_GET['rid']);
if (!$rid) exit($lang['random_11']);

// Get contents of allocation template file
$output = Randomization::getAllocTemplateFileContents($rid, $_GET['example_num']);

// Logging
Logging::logEvent("", "redcap_randomization", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID . "; rid = $rid", "Download randomization allocation template");

// Output to file
$filename = "RandomizationAllocationTemplate.csv";
header('Pragma: anytextexeptno-cache', true);
header("Content-type: application/csv");

header("Content-Disposition: attachment; filename=$filename");
print addBOMtoUTF8($output);
