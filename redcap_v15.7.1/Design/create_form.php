<?php

use Vanderbilt\REDCap\Classes\ProjectDesigner;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$projectDesigner = new ProjectDesigner($Proj);
$created = $projectDesigner->createForm($_POST['form_name'], $_POST['after_form']);
if(!$created) exit('0');
print '1';