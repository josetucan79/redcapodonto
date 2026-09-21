<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Is the form valid?
$formValid = ($status > 0) ? isset($Proj->forms_temp[$_POST['form']]['menu']) : isset($Proj->forms[$_POST['form']]['menu']);
if (!isset($_POST['form']) || !$formValid) exit('0');

// Get form label
$formLabel = ($status > 0) ? $Proj->forms_temp[$_POST['form']]['menu'] : $Proj->forms[$_POST['form']]['menu'];

// Change MyCap task title in tasks table
$ok = \Vanderbilt\REDCap\Classes\MyCap\Task::setTaskTitleByForm($project_id, $_POST['form'], $formLabel);
exit($ok ? $formLabel : '0');
