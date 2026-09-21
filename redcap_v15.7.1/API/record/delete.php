<?php

global $format, $returnFormat, $post;

// Check for required privileges
if ($post['record_delete'] != '1' || $post['api_import'] != '1') die(RestUtility::sendResponse(400, $lang['api_135'], $returnFormat));
if (!is_array($post['records'])) die(RestUtility::sendResponse(400, $lang['api_136'], $returnFormat));
if (empty($post['records'])) die(RestUtility::sendResponse(400, $lang['api_137'], $returnFormat));

// Instantiate project objecct
$Proj = new Project(PROJECT_ID);	

// delete the records 
$content = delRecords();

// Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function delRecords()
{
	global $post, $Proj, $lang, $playground;
	// If Arm was passed, then get its arm_id
	$arm_id = null;
	if (isset($_POST['arm']) && !empty($_POST['arm']) && $Proj->longitudinal && $Proj->multiple_arms) {
		$arm_id = $Proj->getArmIdFromArmNum($_POST['arm']);
		// Error: arm is incorrecct
		if (!$arm_id) die(RestUtility::sendResponse(400, $lang['api_132']));
		// Set event_id (for logging only) so that the logging denotes the correct arm
		$_GET['event_id'] = $Proj->getFirstEventIdArm($_POST['arm']);
	}
	// First check if all records submitted exist
	$existingRecords = Records::getData('array', $post['records'], $Proj->table_pk);
	// Return error if some records don't exist
	if (count($existingRecords) != count($post['records'])) {
		die(RestUtility::sendResponse(400, $lang['api_131'] . " " . implode(", ", array_diff($post['records'], array_keys($existingRecords)))));
	}
    // Delete record data from logging? (default to yes if project-level setting is enabled, otherwise if not enabled, default to no)
    $allow_delete_record_from_log = !(isset($post['delete_logging']) && $post['delete_logging'] == '0');
    // Begin transaction
    db_query("SET AUTOCOMMIT=0");
    db_query("BEGIN");
    $errors = array();
	$count = 0;
	// Loop through all and delete each
	foreach($post['records'] as $r)
    {
        $deleted = REDCap::deleteRecord(PROJECT_ID,
                                        $r,
                                        !empty($post['arm']) ? $post['arm'] : null,
                                        !empty($post['event']) ? $post['event'] : null,
                                        !empty($post['instrument']) ? $post['instrument'] : null,
										!empty($post['repeat_instance']) ? $post['repeat_instance'] : 1,
                                        $allow_delete_record_from_log);

        if (!is_bool($deleted)) {
            $errors[] = $r.": ".$deleted;
        } else {
            // `$deleted` would be `false` when for example the instrument was specified in the POST data and instrument data for the record is empty; we only count it as a deletion if there was some data to delete in the first place.
            if ($deleted) $count++;
        }
    }
    if (!empty($errors)) { // If errors, rollback
        db_query("ROLLBACK");
        db_query("SET AUTOCOMMIT=1");
        die(RestUtility::sendResponse(400, $lang['api_176'] . " " . implode(", ", $errors)));
    }
    // If no errors, commit the changes
    db_query("COMMIT");
    db_query("SET AUTOCOMMIT=1");

	return $count;
}
