<?php

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

$logic = $_POST['logic'];

// Obtain array of error fields that are not real fields
$error_fields = Design::validateBranchingCalc($logic);

// If receiving record-event_id, then split it up to prepend variables in the logic with unique events names (to apply it to a specific event)
if ($longitudinal && isset($_POST['hasrecordevent']) && $_POST['hasrecordevent'] == '1') {
	// Split record-event_id
	$posLastDash = strrpos($_POST['record'], '-');
	$record = substr($_POST['record'], 0, $posLastDash);
	$event_id = substr($_POST['record'], $posLastDash+1);
	$logic = Piping::pipeSpecialTags($logic, $project_id, $record, $event_id, 1, USERID, true, null, null, false, false, false, true);
	if (trim($logic) != '') {
		$logic = LogicTester::logicPrependEventName($logic, $Proj->getUniqueEventNames($event_id), $Proj);
	}
} else {
	$record = $_POST['record'];
}

if (empty($error_fields))
{
	// Format calculation to PHP format
	$logic = LogicTester::formatLogicToPHP($logic, $Proj);
	// Get resulting calculation
	$val = LogicTester::evaluateLogicSingleRecord($logic, $record, null, null, 1, null, true);
	if ($val === "" || (is_float($val) && is_nan($val)) || !is_numeric($val))
		echo "[".$lang['design_708']."]";
	else
		echo $val;
}
else
{
	echo "[".$lang['design_708']."]";
}