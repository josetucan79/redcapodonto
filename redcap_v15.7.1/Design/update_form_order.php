<?php

use Vanderbilt\REDCap\Classes\ProjectDesigner;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (isset($_POST['forms'])) {
	// Parse and validate the forms
	$forms = array();
	foreach (explode(",", $_POST['forms']) as $this_form) {
		if (!empty($this_form)) {
			$forms[] = $this_form;
		}
	}
    $projectDesigner = new ProjectDesigner($Proj);
	print ($projectDesigner->updateFormsOrder($forms) === true) ? "1" : "0";
}