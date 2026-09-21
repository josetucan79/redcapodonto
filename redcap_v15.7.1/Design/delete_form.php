<?php

use Vanderbilt\REDCap\Classes\ProjectDesigner;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$projectDesigner = new ProjectDesigner($Proj);
$deleted = $projectDesigner->deleteForm($_POST['form_name']);
if(!$deleted) {
	// One or more fields are on this form, so return error code
	exit("3");
}
// Send successful response (1 = OK)
print "1";