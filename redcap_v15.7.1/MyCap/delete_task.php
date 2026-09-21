<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Ensure the task_id belongs to this project and that Post method was used
global $myCapProj;
$page = $_GET['page'];
if (!isset(($myCapProj->tasks[$page])) || !isinteger($_GET['task_id'])) exit("0");

$response = "0"; //Default

// Remove from table
$sql = "DELETE FROM redcap_mycap_tasks WHERE task_id = {$_GET['task_id']}";
if (db_query($sql))
{
    // Remove task schedules
    $sql = "DELETE FROM redcap_mycap_tasks_schedules WHERE task_id = {$_GET['task_id']}";
    db_query($sql);

	// Logging
    Logging::logEvent($sql,"redcap_mycap_tasks","MANAGE",$_GET['task_id'],"task_id = {$_GET['task_id']}","Delete MyCap Task settings");
    // Set response
    $response = "1";
}

print $response;