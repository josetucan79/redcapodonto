<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Is the form valid?
$formValid = ($status > 0) ? isset($Proj->forms_temp[$_POST['form']]['menu']) : isset($Proj->forms[$_POST['form']]['menu']);
if (!isset($_POST['form']) || !$formValid) exit('0');

// Get form label
$formLabel = ($status > 0) ? $Proj->forms_temp[$_POST['form']]['menu'] : $Proj->forms[$_POST['form']]['menu'];

// Change survey title 
$ok = $Proj->setSurveyTitle($_POST['form'], $formLabel);
if ($ok) {
	exit($formLabel);
}
else {
	exit('0');
}
