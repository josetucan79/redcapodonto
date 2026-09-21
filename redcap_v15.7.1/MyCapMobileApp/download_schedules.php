<?php
use Vanderbilt\REDCap\Classes\MyCap\Task;
require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// If user is not a super user then do nothing
if (!$super_user) exit();

$result = Task::csvTaskSchedulesDownload();

$project_title = REDCap::getProjectTitle();
$filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($project_title, ENT_QUOTES)))), 0, 30)
    ."_TaskSchedules_".date("Y-m-d").".csv";

header('Pragma: anytextexeptno-cache', true);
header("Content-type: application/csv");
header('Content-Disposition: attachment; filename=' . $filename);

echo $result;