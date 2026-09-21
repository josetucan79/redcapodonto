<?php

# get project information
$Proj = new Project();
$longitudinal = $Proj->longitudinal;

// If user has "No Access" export rights, then return error
if ($post['export_rights'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you have "No Access" data export rights. Higher level data export rights are required for this operation.'));
}

// Get user's user rights
$user_rights = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights[PROJECT_ID][strtolower(USERID)];
$ur = new UserRights();
$user_rights = $ur->setFormLevelPrivileges($user_rights);

// Set vars
$project_id = $_GET['pid'] = $post['projectid'];
$record = (isset($post['record']) && $post['record'] != '') ? $post['record'] : '';
$form_name = (isset($post['instrument']) && $post['instrument'] != '') ? $post['instrument'] : '';
$eventName = (isset($post['event']) && $post['event'] != '') ? $post['event'] : '';
if (isset($post['compactDisplay']) && ($post['compactDisplay'] == '1' || strtolower($post['compactDisplay']."") === 'true')) {
	$_GET['compact'] = 1;
}

// Verify form name, if included
if ($form_name != '' && !isset($Proj->forms[$form_name])) {
	RestUtility::sendResponse(400, "Invalid instrument");
}

# check to make sure the record exists, if included
$eventId = $Proj->firstEventId;
if ($record != '') {
	// Get the event id for the item to be downloaded
	if ($longitudinal) {
		# check the event that was passed in and get the id associated with it
        if ($eventName == '') {
            $eventId = '';
        } else {
			$eventId = $Proj->getEventIdUsingUniqueEventName($eventName);
			if (!is_numeric($eventId)) {
				RestUtility::sendResponse(400, "Invalid event");
			}
		}
	}
	// Verify record exists
	if (!Records::recordExists(PROJECT_ID, $record)) {
		RestUtility::sendResponse(400, "The record '$record' does not exist");
	}
}
$_GET['event_id'] = $eventId;

// If project has repeating forms/events, then use the repeat_instance
$instance = (isset($post['repeat_instance']) && isinteger($post['repeat_instance']) && $post['repeat_instance'] > 0) ? $post['repeat_instance'] : 1;
if (!$Proj->isRepeatingForm($eventId, $form_name) && !($Proj->longitudinal && $Proj->isRepeatingEvent($eventId))) {
	$instance = 1;
}
$_GET['instance'] = $instance;

// Output PDF of all forms (ALL records)
$logging_data_values = "";
if (isset($post['allrecords']) || isset($post['allRecords'])) {
	$_GET['allrecords'] = '1';
	$logging_description = "Download all data entry forms as PDF (all records)";
}
// Output PDF of single form (blank)
elseif ($form_name != '' && $record == '') {
	$_GET['page'] = $form_name;
	$logging_data_values = "form_name = '$form_name'";
	$logging_description = "Download data entry form as PDF";
}
// Output PDF of single form (single record's data)
elseif ($form_name != '' && $record != '') {
	$_GET['id'] = $record;
	$_GET['page'] = $form_name;
	$logging_data_values = "record = '$record',\nform_name = '$form_name'";
	if ($eventId != '' && $Proj->longitudinal) $logging_data_values .= ",\nevent_id = $eventId";
	$logging_description = "Download data entry form as PDF (with data)";
}
// Output PDF of all forms (blank)
elseif ($form_name == '' && $record == '') {
	$_GET['all'] = '1';
	$logging_description = "Download all data entry forms as PDF";
}
// Output PDF of all forms (single record's data)
elseif ($form_name == '' && $record != '') {
	$_GET['id'] = $record;
	$logging_data_values = "record = '$record'";
	if ($eventId != '') $logging_data_values .= ",\nevent_id = $eventId";
	$logging_description = "Download all data entry forms as PDF (with data)";
}
// Unknown error
else {
	RestUtility::sendResponse(400, "Unknown error");
}

## OUTPUT PDF
PDF::output();

// log the event
Logging::logEvent($sql,"redcap_metadata","MANAGE",$record,$logging_data_values,"$logging_description (API$playground)");
