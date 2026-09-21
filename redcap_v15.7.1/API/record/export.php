<?php

use Vanderbilt\REDCap\Classes\Cache\REDCapCache;
use Vanderbilt\REDCap\Classes\Cache\CacheFactory;
use Vanderbilt\REDCap\Classes\Cache\States\DisabledState;
use Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies\ProjectActivityInvalidation;

global $format, $returnFormat, $post;

// If user has "No Access" export rights, then return error
if ($post['export_rights'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you have "No Access" data export rights. Higher level data export rights are required for this operation.'));
}
$logFields = array();
$fieldData = array();

// Get user rights
$user_rights_proj_user = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights_proj_user[PROJECT_ID][strtolower(USERID)];
$ur = new UserRights();
$user_rights = $ur->setFormLevelPrivileges($user_rights);
unset($user_rights_proj_user);

// Get project attributes
$Proj = new Project();
$longitudinal = $Proj->longitudinal;
// Validate unique event names
if ($Proj->longitudinal && isset($post['events']) && is_array($post['events']) && !empty($post['events'])) {
    $uniqueEventNames = $Proj->getUniqueEventNames();
    $invalidEvents = array();
    foreach ($post['events'] as $thisEventName) {
        if (!in_array($thisEventName, $uniqueEventNames)) {
            $invalidEvents[] = $thisEventName;
        }
    }
    $invalidEvents = array_unique($invalidEvents);
    if (!empty($invalidEvents)) {
        exit(RestUtility::sendResponse(400, 'The following values in the parameter "events" are not valid: '.prep_implode($invalidEvents)));
    }
}
// Initialize fields to empty array if not supplied
if (!isset($post['fields'])) {
	$post['fields'] = [];
}
else {
	// Convert field names to array if supplied as (comma-delimited) string
	if (!is_array($post['fields'])) {
		$post['fields'] = array_map(function($field) {
			return trim($field);
		}, explode(",", $post['fields']));
	}
	// Validate field names
	if (isset($post['fields']) && is_array($post['fields']) && !empty($post['fields'])) {
		$invalidFields = array();
		$allFields = MetaData::getFieldNames(PROJECT_ID);
		foreach ($post['fields'] as $fkey=>$thisField) {
            if (!is_string($thisField)) {
                $invalidFields[] = $thisField;
            } elseif (isset($Proj->metadata[$thisField]) && $Proj->metadata[$thisField]['element_type'] == 'descriptive') {
				unset($post['fields'][$fkey]);
			} elseif (!isset($Proj->metadata[$thisField]) && !in_array($thisField, $allFields)) {
				$invalidFields[] = $thisField;
			}
		}
		$invalidFields = array_unique($invalidFields);
		if (!empty($invalidFields)) {
			exit(RestUtility::sendResponse(400, 'The following values in the parameter "fields" are not valid: '.prep_implode($invalidFields)));
		} elseif (empty($post['fields'])) {
			exit(RestUtility::sendResponse(400, 'The variables provided in the parameter "fields" are not valid fields that contain data. This might happen if you only provided variables for Descriptive fields.'));
		}
	}
}
// Validate form names
if (isset($post['forms']) && is_array($post['forms']) && !empty($post['forms'])) {
    $invalidForms = array();
    foreach ($post['forms'] as $thisForm) {
        if (!is_string($thisForm) || !isset($Proj->forms[$thisForm])) {
            $invalidForms[] = $thisForm;
        }
    }
    $invalidForms = array_unique($invalidForms);
    if (!empty($invalidForms)) {
        exit(RestUtility::sendResponse(400, 'The following values in the parameter "forms" are not valid: '.prep_implode($invalidForms)));
    }
}

# get all the records to be exported
if ($format == 'odm' || $post['type'] == 'flat') {
	// Flat export
	$content = getRecordsFlat();
} else {
	// EAV export
	$result = getRecords();
	# structure the output data accordingly
	switch($format)
	{
		case 'json':
			$content = json($result);
			break;
		case 'xml':
			$content = xml($result);
			break;
		case 'csv':
			$content = csv($result);
			break;
	}
}
# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);
function json($dataset)
{
	global $post, $longitudinal, $hasRepeatedInstances;
	// EAV
	foreach ($dataset as $dkey=>$row)
	{
		$row2 = array();
		$row2['record'] = $row['record'];
		if ($longitudinal) {
			$row2['redcap_event_name'] = $row['redcap_event_name'];
		}
		if ($hasRepeatedInstances) {
			$row2['redcap_repeat_instrument'] = $row['redcap_repeat_instrument']."";
			$row2['redcap_repeat_instance'] = $row['redcap_repeat_instance'];		
		}
		$row2['field_name'] = $row['field_name'];
		$row2['value'] = html_entity_decode($row['value'], ENT_QUOTES);
		$dataset[$dkey] = $row2;
	}
	return json_encode($dataset);
}

function xml($dataset)
{
	global $post, $longitudinal, $hasRepeatedInstances;

	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<records>\n";
	foreach ($dataset as $row)
	{
		$output .= '<item>';
		$output .= '<record>'. $row['record'] .'</record>';
		if ($longitudinal) {
			$output .= '<redcap_event_name>'. $row['redcap_event_name'] .'</redcap_event_name>';
		}
		if ($hasRepeatedInstances) {
			// If ]]> is found inside this redcap_repeat_instrument, then "escape" it (cannot really escape it but can do clever replace with "]]]]><![CDATA[>")
			if (strpos($row['redcap_repeat_instrument'], "]]>") !== false) {
				$row['redcap_repeat_instrument'] = '<![CDATA['.str_replace("]]>", "]]]]><![CDATA[>", $row['redcap_repeat_instrument']).']]>';
			}
			$output .= '<redcap_repeat_instrument>'. $row['redcap_repeat_instrument'] .'</redcap_repeat_instrument>';
			$output .= '<redcap_repeat_instance>'.$row['redcap_repeat_instance'].'</redcap_repeat_instance>';
		}
		$output .= '<field_name>'. $row['field_name'] .'</field_name>';
		if ($row['value'] != "") {
			$row['value'] = html_entity_decode($row['value'], ENT_QUOTES);
			// If ]]> is found inside this value, then "escape" it (cannot really escape it but can do clever replace with "]]]]><![CDATA[>")
			if (strpos($row['value'], "]]>") !== false) {
				$row['value'] = str_replace("]]>", "]]]]><![CDATA[>", $row['value']);
			}
			$output .= '<value><![CDATA['. $row['value'] .']]></value>';
		} else {
			$output .= '<value></value>';
		}
		$output .= "</item>\n";
	}
	$output .= "</records>\n";

	return $output;
}

function csv($dataset)
{
	global $post, $fieldData, $longitudinal, $hasRepeatedInstances, $Proj;

	// Loop through array and remove repeating instance fields if not repeating instances in this data set
	if (!$hasRepeatedInstances || !$longitudinal) {
		foreach ($dataset as &$row) {
			if (!$hasRepeatedInstances) {
				unset($row['redcap_repeat_instrument'], $row['redcap_repeat_instance']);
			}
			if (!$longitudinal) {
				unset($row['redcap_event_name']);
			}
		}
		unset($row);
	}
	
	$csvDelimiter = (isset($post['csvDelimiter']) && DataExport::isValidCsvDelimiter($post['csvDelimiter'])) ? $post['csvDelimiter'] : ",";
	if ($csvDelimiter == 'tab' || $csvDelimiter == 'TAB') $csvDelimiter = "\t";
	
	return arrayToCsv($dataset, true, $csvDelimiter);
}



function getRecordsFlat()
{
	global $playground, $post, $logFields, $fieldData, $Proj, $user_rights;

	$project_id = $post['projectid'];
	$type = $post['type'];
	$eventName = strtolower($post['eventName']);
	
	$fieldsSpecified = ((isset($post['fields']) && is_array($post['fields']) && !empty($post['fields']))
					|| (isset($post['forms']) && is_array($post['forms']) && !empty($post['forms'])));

	// Determine if these params are arrays.  If not, make them into arrays
	$tempRecords = is_array($post['records']) ? $post['records'] : explode(",", $post['records']);
	$tempEvents = is_array($post['events']) ? $post['events'] : explode(",", $post['events']);
	$tempFields = $post['fields']; // This is guaranteed to be an array
	$tempForms = is_array($post['forms']) ? $post['forms'] : explode(",", $post['forms']);

	$records = array();
	$events = array();
	$fields = array();

	// Loop through all elements and remove any spaces
	foreach($tempRecords as $id => $value) {
		$records[] = trim(str_replace("'", "", (string)$value));
	}
	foreach($tempEvents as $id => $value) {
		$events[] = trim(str_replace(array("'",'"'), array("",""), (string)$value));
	}
	foreach($tempFields as $id => $value) {
		$fields[] = trim(str_replace(array("'",'"'), array("",""), (string)$value));
	}
	foreach($tempForms as $id => $value) {
        $fieldsPre = $Proj->forms[trim(str_replace(array("'",'"'), array("",""), (string)$value))]['fields'] ?? [];
		$fields = @array_merge($fields, array_keys($fieldsPre));
	}

	// Export DAGs?
	$dags = $Proj->getUniqueGroupNames();
	$exportDags = ($post['exportDataAccessGroups'] && !is_numeric($post['dataAccessGroupId']) && !empty($dags));

	// Export survey fields?
	$exportSurveyFields = ($post['exportSurveyFields'] && count($Proj->surveys) > 0);

	// Set fields
	$data_values['fields'] = $fields = (empty($fields) ? array_keys($Proj->metadata) : $fields);

	// Set filter
	$filterLogic = $post['filterLogic'];

	// De-Identification settings
	$hashRecordID = (isset($user_rights['forms_export'][$Proj->firstForm]) && $user_rights['forms_export'][$Proj->firstForm] > 1 && $Proj->table_pk_phi);
	if ($post['export_rights'] > 0) {
		// Determine what fields to remove based upon export de-id rights
		$fieldsToRemove = DataExport::deidFieldsToRemove($Proj->project_id, $fields, $post['export_rights_forms'], true);
		if (!empty($fieldsToRemove)) {
			$fields = array_diff($fields, $fieldsToRemove);
			// If ALL fields that were specified were removed due to De-Id rights, then return an error message
			if ($fieldsSpecified && empty($fields)) {
				exit(RestUtility::sendResponse(400, 'The API request cannot complete because all the fields you have specified in your request are fields that will '
					. 'be removed due to your limited Data Export privileges, so there is nothing to return. Higher level data export rights are required to retrieve data for the fields specified.'));
			}
		}
		unset($fieldsToRemove);
	}

	## Logging
	// Set data_values as JSON-encoded
	$data_values = array('export_format'=>strtoupper($post['format']), 'rawOrLabel'=>$post['rawOrLabel']);
	if ($exportDags) $data_values['export_data_access_group'] = 'Yes';
	if ($exportSurveyFields) $data_values['export_survey_fields'] = 'Yes';
	// Log it
	$log_event_id = Logging::logEvent("","redcap_data","data_export","",json_encode($data_values),"Export data (API$playground)");
	// If this is the mobile app initializing a project, then log that in the mobile app log
	if ($post['mobile_app'] && $post['project_init'] > 0) {
		$userInfo = User::getUserInfo(USERID);
		$mobile_app_event = ($post['project_init'] == '1') ? 'INIT_DOWNLOAD_DATA' : 'REINIT_DOWNLOAD_DATA';
                if ($post['uuid'] !== "")
                {
                        $presql1= "SELECT device_id, revoked FROM redcap_mobile_app_devices WHERE (uuid = '".db_escape($post['uuid'])."') AND (project_id = ".PROJECT_ID.") LIMIT 1;";
                        $preq1 = db_query($presql1);
                        $row = db_fetch_assoc($preq1);
                        if (!$row)  // no devices
                        {
                                $presql2 = "INSERT INTO redcap_mobile_app_devices (uuid, project_id) VALUES('".db_escape($post['uuid'])."', ".PROJECT_ID.");";
                                db_query($presql2);
                                $preq1 = db_query($presql1);
                                $row = db_fetch_assoc($preq1);
                        }
        
                        if ($row && ($row['revoked'] == "0"))
                        {
                                $sql = "insert into redcap_mobile_app_log (project_id, log_event_id, event, device_id, ui_id) values
                                               (".PROJECT_ID.", $log_event_id, '$mobile_app_event', ".$row['device_id'].", '".db_escape($userInfo['ui_id'])."')";
		                db_query($sql);
                        }
                        else
                        {
                                // revoked
                                return array();
                        }

                 }
                 else
                 {
		         $sql = "insert into redcap_mobile_app_log (project_id, log_event_id, event, ui_id) values
				        (".PROJECT_ID.", $log_event_id, '$mobile_app_event', '".db_escape($userInfo['ui_id'])."')";
			db_query($sql);
		}
	}

	$dateRangeBegin = "";
	$dateRangeEnd = "";
	if (isset($post['dateRangeBegin']) && $post['dateRangeBegin'] != '') {
		$dateRangeBegin = reformatDate($post['dateRangeBegin'], "begin");
		$dateRangeEnd = reformatDate(date("Y-m-d H:i:s"), "end");
	}
	if (isset($post['dateRangeEnd']) && $post['dateRangeEnd'] != '') {
		$dateRangeEnd = reformatDate($post['dateRangeEnd'], "end");
	}
	if ((isset($post['dateRangeBegin']) && $post['dateRangeBegin'] != '') || (isset($post['dateRangeEnd']) && $post['dateRangeEnd'] != '')) {
		if (!$dateRangeBegin) {
			$dateRangeBegin = reformatDate("2004-08-01 00:00:00", "begin");
		}
		if (!$dateRangeEnd) {
			$dateRangeEnd = reformatDate(NOW, "end");
		}
	}

	if ($dateRangeBegin && $dateRangeEnd) {
		// Get record names in the timespan
		$idArray = getModifiedAndNewRecordsInSpan($post['projectid'], $dateRangeBegin, $dateRangeEnd);
		if (empty($records)) {
			// Get current record list
			$recordList = array_values(Records::getRecordList($post['projectid']));
			$records = array_intersect($recordList, $idArray);
		} else {
			$records = array_intersect($records, $idArray);
		}
		if (empty($records)) {
			// nothing to return, so set records param as empty record name for force it to return an empty set
			$records = array('');
		}
	}

    $exportBlankForGrayFormStatus = (isset($post['exportBlankForGrayFormStatus']) && ($post['exportBlankForGrayFormStatus'] === true
                                    || (string)$post['exportBlankForGrayFormStatus'] === '1' || strtolower((string)$post['exportBlankForGrayFormStatus']) === 'true'));

    ## Rapid Retrieval: Cache salt
    // Use some user privileges as additional salt for the cache
    $cacheManager = CacheFactory::manager(PROJECT_ID);
    $cacheOptions = [REDCapCache::OPTION_INVALIDATION_STRATEGIES => [ProjectActivityInvalidation::signature(PROJECT_ID)]];
    $cacheOptions[REDCapCache::OPTION_SALT] = [];
    $cacheOptions[REDCapCache::OPTION_SALT][] = ['dag'=>$user_rights['group_id']];
    // Generate a form-level access salt for caching purposes: Create array of all forms represented by the report's fields
    $reportFields = $fields;
    sort($reportFields);
    $reportForms = [];
    foreach ($reportFields as $thisField) {
        $thisForm = $Proj->metadata[$thisField]['form_name'];
        if (isset($reportForms[$thisForm])) continue;
        $reportForms[$thisForm] = true;
    }
    $reportFormsAccess = array_intersect_key($user_rights['forms_export'], $reportForms);
    $reportFormsAccessSalt = [];
    foreach ($reportFormsAccess as $thisForm => $thisAccess) {
        $reportFormsAccessSalt[] = "$thisForm:$thisAccess";
    }
    $cacheOptions[REDCapCache::OPTION_SALT][] = ['form-export-rights' => implode(",", $reportFormsAccessSalt)];
    // If the report has filter logic containing datediff() with today or now, then add more salt since these will cause different results with no data actually changing.
    if (strpos($filterLogic, 'datediff') !== false) {
        list ($ddWithToday, $ddWithNow) = containsDatediffWithTodayOrNow($filterLogic);
        if ($ddWithNow) $cacheManager->setState(new DisabledState());  // disable the cache since will never be used
        elseif ($ddWithToday) $cacheOptions[REDCapCache::OPTION_SALT][] = ['datediff'=>TODAY];
    }
    // If the report has filter logic containing a [user-X] smart variable, then add the USERID to the salt
    if (strpos($filterLogic, '[user-') !== false) {
        $cacheOptions[REDCapCache::OPTION_SALT][] = ['user'=>USERID];
    }

	// Get data
	return $cacheManager->getOrSet([Records::class, 'getData'], [
        $post['projectid'], $post['format'], $records, $fields, $events, $post['dataAccessGroupId'], $post['combineCheckboxOptions'],
        $exportDags, $exportSurveyFields, $filterLogic, ($post['rawOrLabel'] == 'label'), ($post['rawOrLabelHeaders'] == 'label'),
        $hashRecordID, false, false, array(), false, ($post['format'] != 'odm'), false, false, $exportSurveyFields, $post['exportCheckboxLabel'], 'EVENT',
        false, false, false, false, true, null, 0, false, (isset($post['csvDelimiter']) ? $post['csvDelimiter'] : ","),
        (isset($post['decimalCharacter']) ? $post['decimalCharacter'] : null), false, 0, array(), false, array(), null, null, null,
        $exportBlankForGrayFormStatus
    ], $cacheOptions);
}

function getModifiedAndNewRecordsInSpan($project_id, $dateRangeBegin = "", $dateRangeEnd = "") {
	$idArray = array();
	if ($dateRangeBegin && $dateRangeEnd) {
		$logSql = "SELECT DISTINCT pk
				FROM ".Logging::getLogEventTable($project_id)."
				WHERE project_id = $project_id 
					AND ((object_type = 'redcap_data' AND event IN ('INSERT', 'UPDATE', 'DOC_DELETE', 'DOC_UPLOAD'))
						OR (page = 'PLUGIN' and event = 'OTHER'))
					AND ts >= $dateRangeBegin
					AND ts <= $dateRangeEnd";
		$logResult = db_query($logSql);
		while ($row = db_fetch_assoc($logResult)) {
			$idArray[] = $row['pk'];
		}
	}
	return $idArray;
}


# pads to two-digits
function pad($num) {
	if (strlen($num) == 1) {
		return "0".$num;
	}
	return $num;
}

# reformats to int YYYYMMDDHHIISS (format of database)
# returns empty string if invalid input
# $ymd = string in "YYYY-MM-DD [HH:II:SS]" format
# $type is from ["begin", "end"] depending on the type of date that is requested in $ymd
function reformatDate($ymd, $type = "begin") {
	# split at middle
	# cover for various ways of formatting datetime strings
	$nodes = preg_split("/[\sT]+/", $ymd);
	if (count($nodes) == 2) {
		$times = preg_split("/[:\.]/", $nodes[1]);
		if (count($times) == 3) {
			$hh = pad($times[0]);
			$ii = pad($times[1]);
			$ss = pad($times[2]);
		} else if (count($times) == 2) {
			$hh = pad($times[0]);
			$ii = pad($times[1]);
			$ss = "00";
		} else {
			if ($type == "begin") {
				$hh = "00";
				$ii = "00";
				$ss = "00";
			} else {
				$hh = "23";
				$ii = "59";
				$ss = "59";
			}
		}
	} else {
		if ($type == "begin") {
			$hh = "00";
			$ii = "00";
			$ss = "00";
		} else {
			$hh = "23";
			$ii = "59";
			$ss = "59";
		}
	}
	if (preg_match("/^\d+[\-\/]\d+[\-\/]\d+$/", $nodes[0])) {
		$dates = preg_split("/[\-\/]/", $nodes[0]);
		if (count($dates) == 3) {
			$yyyy = $dates[0];
			$mm = pad($dates[1]);
			$dd = pad($dates[2]);
		} else {
			return "";
		}
	} else {
		return "";
	}
	// Since number is too big to be an "int" data type, so keep as string
	return preg_replace("/[^0-9]/", "", $yyyy.$mm.$dd.$hh.$ii.$ss."")."";
}

function getRecords()
{
	global $playground, $post, $logFields, $fieldData, $Proj, $salt, $hasRepeatedInstances, $user_rights;

	$project_id = $post['projectid'];
	$dataAccessGroupId = $post['dataAccessGroupId'];
	$type = $post['type'];
	$rawOrLabel = strtolower($post['rawOrLabel']);
	$eventName = strtolower($post['eventName']);
	$exportSurveyFields = $post['exportSurveyFields'];
	
	// Does project have repeating forms or events?
	$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
	$hasRepeatingForms = $Proj->hasRepeatingForms();
	$hasRepeatedInstances = false; //default flag to determine if data set contains repeated forms/events data

	// Determine if these params are arrays.  If not, make them into arrays
	$tempRecords = is_array($post['records']) ? $post['records'] : explode(",", $post['records']);
	$tempEvents = is_array($post['events']) ? $post['events'] : explode(",", $post['events']);
	$tempFields = $post['fields']; // This is guaranteed to be an array
	$tempForms = is_array($post['forms']) ? $post['forms'] : explode(",", $post['forms']);

	$records = array();
	$events = array();
	$fields = array();
	$forms = array();

	// Loop through all elements and remove any spaces
	foreach($tempRecords as $id => $value) {
		$records[] = trim(str_replace("'", "", $value));
	}
	foreach($tempEvents as $id => $value) {
		$events[] = trim(str_replace(array("'",'"'), array("",""), $value));
	}
	foreach($tempFields as $id => $value) {
		$fields[] = trim(str_replace(array("'",'"'), array("",""), $value));
	}
	foreach($tempForms as $id => $value) {
		$forms[] = trim(str_replace(array("'",'"'), array("",""), $value));
	}

	$removeIdentifiers = false;
	$hashStudyId = false;
	$removeUnvalidatedTextFields = false;
	$removeFreeTextBoxes = false;
	$shiftDates = false;

	$recordSql = "";
	$eventSql = "";
	$eventNames = array();

	// Set filter
	$filterLogic = $post['filterLogic'];

	# get project information
	$longitudinal = $Proj->longitudinal;
	$multipleArms = $Proj->multiple_arms;
	$primaryKey = $Proj->table_pk;
	$hasSurveys = (!empty($Proj->surveys));
	if ($rawOrLabel == 'label') {
		$dags = array();
		foreach ($Proj->getGroups() as $group_id=>$label) {
			$dags[$group_id] = label_decode($label);
		}
	} else {
		$dags = $Proj->getUniqueGroupNames();
	}
	$exportDags = ($post['exportDataAccessGroups'] && !empty($dags) && !is_numeric($dataAccessGroupId));

	$dateRangeBegin = "";
	$dateRangeEnd = "";
	if (isset($post['dateRangeBegin']) && $post['dateRangeBegin'] != '') {
		$dateRangeBegin = reformatDate($post['dateRangeBegin'], "begin");
		$dateRangeEnd = reformatDate(date("Y-m-d H:i:s"), "end");
	}
	if (isset($post['dateRangeEnd']) && $post['dateRangeEnd'] != '') {
		$dateRangeEnd = reformatDate($post['dateRangeEnd'], "end");
	}
	if ((isset($post['dateRangeBegin']) && $post['dateRangeBegin'] != '') || (isset($post['dateRangeEnd']) && $post['dateRangeEnd'] != '')) {
		if (!$dateRangeBegin) {
			$dateRangeBegin = reformatDate("2004-08-01 00:00:00", "begin");
		}
		if (!$dateRangeEnd) {
			$dateRangeEnd = reformatDate(NOW, "end");
		}
	}

	# filter record list by filterLogic if defined
    $filteredRecords = array();
	if ($filterLogic != false) {
		$filteredRecords = Records::getData(
			$project_id, 'array', (empty($records) ? NULL : $records),
			$primaryKey, NULL, NULL, FALSE, FALSE, FALSE, $filterLogic
		);
		$records = array_keys($filteredRecords);
		// If no records are returned from the filter, add a blank placeholder record to prevent returning ALL records
		if (empty($records)) $records = array('');
	}
	unset($filteredRecords);

	if ($dateRangeBegin && $dateRangeEnd) {
		// Get record names in the timespan
		$idArray = getModifiedAndNewRecordsInSpan($post['projectid'], $dateRangeBegin, $dateRangeEnd);
		if (empty($records)) {
			// Get current record list
			$recordList = array_values(Records::getRecordList($post['projectid']));
			$records = array_intersect($recordList, $idArray);
		} else {
			$records = array_intersect($records, $idArray);
		}
		if (empty($records)) {
			// nothing to return, so set records param as empty record name for force it to return an empty set
			$records = array('');
		}
	}

	# create list of records to retrieve, if provided
	# if user is in a DAG, filter records accordingly
	if (isinteger($dataAccessGroupId))
	{
		$groupSql = "SELECT distinct record
				FROM ".\Records::getDataTable($project_id)."
				WHERE project_id = $project_id
					AND field_name = '__GROUPID__'
					AND value = '$dataAccessGroupId'";
		$groupResult = db_query($groupSql);

		$idArray = array();
		while ($row = db_fetch_assoc($groupResult)) {
			$idArray[] = $row['record'];
		}

		if ( count($idArray) > 0 )
		{
			if ( count($records) > 0 ) {
				$idArray = array_intersect($records, $idArray);
			}
			$recordSql = "AND record IN (" . prep_implode($idArray) . ")";
		}
		else
		{
			$recordSql = "";
		}
	}
	else
	{
		$recordSql = (count($records) > 0) ? "AND record IN (" . prep_implode($records) . ")" : '';
		// If exporting DAGs, then get group_id for all records
		$groupSql = "SELECT record, value
				FROM ".\Records::getDataTable($project_id)."
				WHERE project_id = $project_id
					AND field_name = '__GROUPID__'";
		$groupResult = db_query($groupSql);
		$dagRecords = array();
		while ($row = db_fetch_assoc($groupResult)) {
			$dagRecords[$row['record']] = $row['value'];
		}
	}

	# create list of events to retrieve records for, if provided
	if ($longitudinal && count($events) > 0)
	{
		$eventIds = Event::getEventIdByKey($project_id, $events);
		$eventSql = "AND event_id IN (".prep_implode($eventIds).")";
	} elseif (!$longitudinal) {
		$eventSql = "AND event_id IN (".$Proj->firstEventId.")";
	} else {
		$eventSql = "AND event_id IN (".prep_implode(array_keys($Proj->eventInfo)).")";;
	}

	# get all fields for a set of forms, if provided
    if (!empty($forms)) {
        $sql = "SELECT field_name FROM redcap_metadata
                WHERE project_id = $project_id
                    AND form_name IN (" . prep_implode($forms) . ")
                    AND element_type != 'descriptive'
                ORDER BY field_order";
        $fieldResults = db_query($sql);
        while ($row = db_fetch_assoc($fieldResults)) {
            $fields[] = $row['field_name'];
        }
    } else {
        if (empty($fields)) {
            $fields = array_keys($Proj->metadata);
        }
    }
    if ($exportDags) {
        $fields[] = $Proj->table_pk;
        $fields[] = '__GROUPID__';
    }

	// Set fields
	$data_values['fields'] = $fields = (empty($fields) ? array_keys($Proj->metadata) : array_unique($fields));

	// De-Identification settings
	$hashRecordID = (isset($user_rights['forms_export'][$Proj->firstForm]) && $user_rights['forms_export'][$Proj->firstForm] > 1 && $Proj->table_pk_phi);
	if ($post['export_rights'] > 0) {
		// Determine what fields to remove based upon export de-id rights
		$fieldsToRemove = DataExport::deidFieldsToRemove($Proj->project_id, $fields, $post['export_rights_forms'], true);
		if (!empty($fieldsToRemove)) $fields = array_diff($fields, $fieldsToRemove);
		unset($fieldsToRemove);
	}

	$logFields = $fields;
		
	// Determine any fields that we need to convert its decimal character
	$post['decimalCharacter'] = ($post['decimalCharacter'] ?? "");
	$fieldsDecimalConvert = Records::getFieldsDecimalConvert($project_id, $post['decimalCharacter'], $fields);
	$decimalCharacterConvertFrom = ($post['decimalCharacter'] == '.') ? ',' : '.';
	$convertDecimal = !empty($fieldsDecimalConvert);


	# If 'label' get the current event name, if 'unique' get the unique event name
	if ($eventName == "label") {
		$eventNames = array();
		foreach ($Proj->eventInfo as $event_id=>$attr) {
			$eventNames[$event_id] = label_decode($attr['name_ext']);
		}
	} else {
		$eventNames = $Proj->getUniqueEventNames();
	}

	# If surveys exist, get timestamp and identifier of all responses and place in array
	$timestamp_identifiers = array();
	if ($hasSurveys && $exportSurveyFields)
	{
		$query = "select r.record, r.completion_time, p.participant_identifier, s.form_name, p.event_id
				from redcap_surveys s, redcap_surveys_response r, redcap_surveys_participants p, redcap_events_metadata a
				where p.participant_id = r.participant_id and s.project_id = $project_id and s.survey_id = p.survey_id
				and p.event_id = a.event_id and r.first_submit_time is not null";
        if (!empty($records)) $query .= " and r.record in (".prep_implode($records).")";
        $query .= " order by r.record, r.completion_time";
		$rsSurveys = db_query($query);
		while ($row = db_fetch_assoc($rsSurveys))
		{
			# Replace double quotes with single quotes
			$row['participant_identifier'] = str_replace("\"", "'", label_decode($row['participant_identifier']));
			# If response exists but is not completed, note this in the export
			if ($row['completion_time'] == "") $row['completion_time'] = "[not completed]";
			# Add to array
			$timestamp_identifiers[$row['record']][$row['event_id']][$row['form_name']] = array('ts'=>$row['completion_time'], 'id'=>$row['participant_identifier']);
		}
	}

	// get field information from metadata
	$fieldData = MetaData::getFields($project_id, $longitudinal, $primaryKey, $hasSurveys, $fields, $rawOrLabel, $exportDags, $exportSurveyFields);

	## PIPING
	// If any dropdowns, radios, or checkboxes are using piping in their option labels, then get data for those and then inject them
	$piping_receiver_fields = $piping_transmitter_fields = $piping_record_data = array();
	if ($rawOrLabel != 'raw') {
		foreach ($fieldData['names'] as $this_field) {
			if (in_array(($Proj->metadata[$this_field]['element_type']??""), array('dropdown','select','radio','checkbox'))) {
				$this_field_enum = $Proj->metadata[$this_field]['element_enum'];
				// If has at least one left and right square bracket
				if ($this_field_enum != '' && strpos($this_field_enum, '[') !== false && strpos($this_field_enum, ']') !== false) {
					// If has at least one field piped
					$these_piped_fields = array_keys(getBracketedFields($this_field_enum, true, true, true));
					if (!empty($these_piped_fields)) {
						$piping_receiver_fields[] = $this_field;
						$piping_transmitter_fields = array_merge($piping_transmitter_fields, $these_piped_fields);
					}
				}
			}
		}
		if (!empty($piping_receiver_fields)) {
			// Get data for piping fields
			$piping_record_data = Records::getData('array', (empty($records) ? array() : $records), $piping_transmitter_fields);
			// Remove unneeded variables
			unset($piping_transmitter_fields);
		}
	}


	## CREATE ARRAY OF FIELD DEFAULTS SPECIFIC TO EVERY EVENT (BASED ON FORM-EVENT DESIGNATION)
	$field_defaults_events = array();
	// CLASSIC: Just add $field_defaults array as only array element
	if (!$longitudinal) {
		$field_defaults_events[$Proj->firstEventId] = $fieldData["defaults"];
	}
	// LONGITUDINAL: Loop through each event and set defaults based on form-event mapping
	else {
		// Loop through each event
		foreach (array_keys($Proj->eventInfo) as $event_id) {
			// Get $designated_forms from $Proj->eventsForms
			$designated_forms = (isset($Proj->eventsForms[$event_id])) ? $Proj->eventsForms[$event_id] : array();
			// Loop through each default field value and either keep or remove for this event
			foreach ($fieldData["defaults"] as $field=>$raw_value) {
				// Check if a checkbox OR a form status field (these are the only 2 we care about because they are the only ones with default values)
				$field_form = $Proj->metadata[$field]['form_name'] ?? "";
				if ($Proj->isCheckbox($field) || $field == $field_form."_complete") {
					// Is field's form designated for the current event_id?
					if (!in_array($field_form, $designated_forms)) {
						// Set both raw and label value as blank (appended with comma for delimiting purposes)
						if (is_array($raw_value)) {
							// Loop through all checkbox choices and set each individual value
							foreach (array_keys($raw_value) as $code) {
								$raw_value[$code] = "";
							}
						} else {
							$raw_value = "";
						}
					}
				}
				// Add to field defaults event array
				$field_defaults_events[$event_id][$field] = $raw_value;
			}
		}
	}
	
	
	$result = array();
	$fieldSql = "";

	$fieldList = "'" . implode("','", $fields) . "'";
	$fieldSql = (count($fields) > 0) ? "AND field_name IN ($fieldList)" : '';

	// Create array of the forms whose data is being exported (using fields or forms explicitly listed in request).
	// Put form_name as array key. Will be used when inserting survey pseudo-fields (e.g., timestamps)
	$formsExported = array();
	foreach ($fields as $this_field) {
        if (!isset($Proj->metadata[$this_field])) continue;
		$formsExported[$Proj->metadata[$this_field]['form_name']] = true;
	}

	if ($longitudinal) {
		$sql = "select d.record, d.event_id, d.field_name, d.value, d.instance
				from ".\Records::getDataTable($project_id)." d, redcap_events_metadata e, redcap_events_arms a
				where d.project_id = $project_id and d.project_id = a.project_id
				and a.arm_id = e.arm_id and e.event_id = d.event_id and d.record != ''
				".str_replace("AND record IN (", "AND d.record IN (", $recordSql)."
				".str_replace("AND event_id IN (", "AND d.event_id IN (", $eventSql)."
				".str_replace("AND field_name IN (", "AND d.field_name IN (", $fieldSql)."
				".($exportDags ? "" : "AND d.field_name != '__GROUPID__'")."
				ORDER BY d.record regexp '^[A-Z]', abs(d.record), left(d.record,1), CONVERT(SUBSTRING_INDEX(d.record,'-',-1),UNSIGNED INTEGER), CONVERT(SUBSTRING_INDEX(d.record,'_',-1),UNSIGNED INTEGER), d.record, 
				         a.arm_num, e.day_offset, e.descrip";
		if ($Proj->hasRepeatingFormsEvents()) {
		    $sql .= ", d.instance";
        }
	} else {
		$sql = "SELECT record, event_id, field_name, value, instance
				FROM ".\Records::getDataTable($project_id)."
				WHERE project_id = $project_id $recordSql $eventSql $fieldSql 
				".($exportDags ? "" : "AND field_name != '__GROUPID__'")."
				ORDER BY record regexp '^[A-Z]', abs(record), left(record,1), CONVERT(SUBSTRING_INDEX(record,'-',-1),UNSIGNED INTEGER), CONVERT(SUBSTRING_INDEX(record,'_',-1),UNSIGNED INTEGER), record";
        if ($Proj->hasRepeatingFormsEvents()) {
            $sql .= ", instance";
        }
	}
	$dsData = db_query($sql);

	$previousRecord = "";
	$previousEventId = "";
    $recordIdRows = [];
    $recordKeysSingle = [];
	while ($row = db_fetch_assoc($dsData))
	{
		$recordId = $row['record'];
		$eventId = $row['event_id'];
		
		# ignore blank values
		if ($row['value'] == "") continue;

        // Reset this single record use array
        if ($previousRecord != $recordId) {
            $recordKeysSingle = [];
        }
		
		// Convert the decimal character?
		if ($convertDecimal && isset($fieldsDecimalConvert[$row['field_name']])) {
			$row['value'] = str_replace($decimalCharacterConvertFrom, $post['decimalCharacter'], $row['value']);
		}

		# export label data for enum fields, if applicable
		if (isset($fieldData["enums"][$row['field_name']][$row['value']]) && $rawOrLabel == 'label') {
			$row['value'] = $fieldData["enums"][$row['field_name']][$row['value']];
		}
		if (($fieldData["types"][$row['field_name']]??null) == "truefalse")
		{
			if ($rawOrLabel == 'label')
				$row['value'] = ($row['value'] == 1) ? "True" : "False";
		}
		else if (($fieldData["types"][$row['field_name']]??null) == "yesno")
		{
			if ($rawOrLabel == 'label')
				$row['value'] = ($row['value'] == 1) ? "Yes" : "No";
		}
		
		# Repeating instruments or events: Add repeat instance and repeat_instrument fields
		$isRepeatEvent = ($hasRepeatingFormsEvents && $Proj->isRepeatingEvent($row['event_id']));
		$isRepeatForm  = $isRepeatEvent ? false : ($hasRepeatingFormsEvents && $Proj->isRepeatingForm($row['event_id'], $Proj->metadata[$row['field_name']]['form_name']));
		$isRepeatEventOrForm = ($isRepeatEvent || $isRepeatForm);
		$repeat_instrument = $isRepeatForm ? $Proj->metadata[$row['field_name']]['form_name'] : "";
		if ($row['instance'] === null) {
			$instance = $isRepeatEventOrForm ? 1 : "";
		} else {
			$instance = $row['instance'];
		}
		unset($row['instance']);
        // If this is the record ID field, and it doesn't exist on a repeating instrument or event, then skip it if it is higher than instance 1
        if ($row['field_name'] == $Proj->table_pk && !$isRepeatEventOrForm && $instance > 1) continue;
		// If using form-repeating, then add redcap_repeat_instrument column
		if ($hasRepeatingForms) {
			$row['redcap_repeat_instrument'] = ($rawOrLabel == 'label') ? ($Proj->forms[$repeat_instrument]['menu']??"") : $repeat_instrument;
		}
		// If instance=1, then display as blank (to prevent user confusion since instance 1 is base instance)
		$row['redcap_repeat_instance'] = ($repeat_instrument != '' || $Proj->isRepeatingEvent($row['event_id'])) ? $instance : "";
		// Add initial default data for this record-event
		if (!$hasRepeatedInstances && $isRepeatEventOrForm && !($isRepeatForm && $instance > 1 && $row['field_name'] == $Proj->table_pk)) {
			// Set flag
			$hasRepeatedInstances = true;
		}

		# get the event name from the id
		$row['redcap_event_name'] = $eventNames[$row['event_id']];
		unset($row['event_id']);

        // Skip any duplicate rows for the record ID field
        if ($row['field_name'] == $Proj->table_pk) {
            $recordIdRowKey = implode(",", $row);
            if (!isset($recordIdRows[$recordIdRowKey])) {
                $recordIdRows[$recordIdRowKey] = true;
            } else {
                // Skip this loop since it is a duplicate
                $previousRecord = $recordId;
                $previousEventId = $eventId;
                continue;
            }
        } else {
            // Skip duplicate rows for all other fields except checkboxes (single they can have multiple values)
            $thisrow = $row;
            if (isset($thisrow['value']) && !$Proj->isCheckbox($row['field_name'])) {
                // Don't include value in dup value check unless it is a checkbox field, which can have multiple values
                unset($thisrow['value']);
            }
            $recordIdRowKey = implode(",", $thisrow);
            unset($thisrow);
            if (!isset($recordKeysSingle[$recordIdRowKey])) {
                $recordKeysSingle[$recordIdRowKey] = true;
            } else {
                // Skip this loop since it is a duplicate
                $previousRecord = $recordId;
                $previousEventId = $eventId;
                continue;
            }
        }

		# add data point to global array
		$result[] = $row;

		# If project has any surveys, add the survey completion timestamp and identifier (if exists)
		if ($hasSurveys && $exportSurveyFields && ($previousRecord != $recordId || $previousEventId != $eventId) && isset($timestamp_identifiers[$recordId][$Proj->getFirstEventIdInArmByEventId($eventId)][$Proj->firstForm]))
		{
			//$thisRow['redcap_survey_identifier'] = $timestamp_identifiers[$recordId][$Proj->getFirstEventIdInArmByEventId($eventId)][$Proj->firstForm]['id'];
			$idRow = array();
			$idRow['record'] = $row['record'];
			$idRow['field_name'] = "redcap_survey_identifier";
			$idRow['value'] = $timestamp_identifiers[$recordId][$Proj->getFirstEventIdInArmByEventId($eventId)][$Proj->firstForm]['id'];
			$idRow['redcap_event_name'] = $row['redcap_event_name'];
			$result[] = $idRow;
		}
		if ($hasSurveys && $exportSurveyFields && ($previousRecord != $recordId || $previousEventId != $eventId) && isset($timestamp_identifiers[$recordId][$eventId]))
		{
			// Add the survey completion timestamp for each survey
			foreach ($timestamp_identifiers[$recordId][$eventId] as $this_form=>$attr) {
				if (isset($formsExported[$this_form])) {
					$tsRow = array();
					$tsRow['record'] = $row['record'];
					$tsRow['field_name'] = $this_form.'_timestamp';
					$tsRow['value'] = $attr['ts'];
					$tsRow['redcap_event_name'] = $row['redcap_event_name'];
					$result[] = $tsRow;
				}
			}
		}

		$previousRecord = $recordId;
		$previousEventId = $eventId;
	}

	// If hashing the
	if ($hashRecordID) {
		foreach ($result as &$this_item) {
			// Hash the record name using a system-level AND project-level salt
			$this_item['record'] = md5($salt . $this_item['record'] . $Proj->project['__SALT__']);
		}
        unset($this_item);
	}

	## Logging
	// Set data_values as JSON-encoded
	$data_values = array('export_format'=>strtoupper($post['format']), 'rawOrLabel'=>$post['rawOrLabel']);
	if ($exportDags) $data_values['export_data_access_group'] = 'Yes';
	if ($exportSurveyFields) $data_values['export_survey_fields'] = 'Yes';
	// Log it
	$log_event_id = Logging::logEvent("","redcap_data","data_export","",json_encode($data_values),"Export data (API$playground)");
	// If this is the mobile app initializing a project, then log that in the mobile app log
	if ($post['mobile_app'] && $post['project_init'] > 0) {
		$userInfo = User::getUserInfo(USERID);
		$mobile_app_event = ($post['project_init'] == '1') ? 'INIT_DOWNLOAD_DATA' : 'REINIT_DOWNLOAD_DATA';
		$sql = "insert into redcap_mobile_app_log (project_id, log_event_id, event, ui_id) values
				(".PROJECT_ID.", $log_event_id, '$mobile_app_event', '{$userInfo['ui_id']}')";
		db_query($sql);
	}
	return $result;
}

function ValueArray($sql)
{
	if (trim($sql) == "" || $sql == null) return "''";

	$values = array();

	$query = db_query(html_entity_decode($sql, ENT_QUOTES));
	if (db_num_rows($query) > 0)
	{
		while ($row = db_fetch_array($query)) {
			$values[] = $row[0];
		}
	}

	return $values;
}
