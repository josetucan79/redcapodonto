<?php
global $Proj, $format, $post, $project_id, $user_rights;

$GLOBALS['project_id'] = $project_id = (is_null($project_id)) ? $Proj->project_id : $project_id;
$user_rights = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights[PROJECT_ID][strtolower(USERID)];
$ur = new UserRights();
$user_rights = $ur->setFormLevelPrivileges($user_rights);;

if (!$user_rights['random_perform'] && !(defined('SUPER_USER') && SUPER_USER)) {
    RestUtility::sendResponse(403, "Cannot randomize. You do not have the Randomize permission.", $format);
}

$rid = $post['randomization_id'];
$record = $post['record'];
$returnAlt = $post['returnAlt']=='true' || $post['returnAlt']==1;

$rid = Randomization::getRid($rid);
if ($rid==false) {
    RestUtility::sendResponse(200, "Invalid data supplied", $format);
}

$randAttr = Randomization::getRandomizationAttributes($rid, $Proj->project_id);

if (!Records::recordExists($Proj->project_id, $record, $Proj->eventInfo[$randAttr['targetEvent']]['arm_num'])) {
    RestUtility::sendResponse(200, "Cannot randomize. Record does not exist.", $format);
}

if (Randomization::wasRecordRandomized($record, $rid)) {
    RestUtility::sendResponse(200, "Cannot randomize. Randomization already completed for record.", $format);
}

list($fields, $group_id, $missing) = Randomization::readStratificationData($rid, $record);

if (count($missing) > 0) {
    if (array_search('redcap_data_access_group', $missing) !== false) {
        RestUtility::sendResponse(200, "Cannot randomize. Record not assigned to a DAG", $format);    
    }
    RestUtility::sendResponse(200, "Cannot randomize. Missing stratification data for fields: ".implode(',',$missing), $format);
}

$randomizeResult = Randomization::randomizeRecord($rid, $record, $fields, $group_id);

if ($randomizeResult===false) {
    RestUtility::sendResponse(400, "Cannot randomize. An error occurred.", $format);

} else if (is_string($randomizeResult)) {
    if ($randomizeResult == '0') {
        RestUtility::sendResponse(200, "Cannot randomize. No allocations available.", $format);
    } else {
        RestUtility::sendResponse(200, "Cannot randomize. $randomizeResult", $format);
    }
}

Randomization::saveRandomizationResultToDataTable($rid, $record);

Logging::logEvent("", "redcap_data", "MANAGE", $record, $Proj->table_pk." = '$record'\nrandomization_id = $rid", "Randomize record (API)");

list ($target_field, $target_field_value, $target_field_alt_value, $rand_time_server, $rand_time_utc) = Randomization::getRandomizedValue($record, $rid);

$result = array(
    'randomization_id' => $rid,
    'record' => $record,
    'target_field' => $target_field_value,
    'target_field_name' => $target_field
);

if ($returnAlt) {
    if ($randAttr['isBlinded']) {
        // do not allow return of group for blinded randomisations
        $result['target_field_alt'] = '*';
    } else {
        $result['target_field_alt'] = $target_field_alt_value;
    }
}

switch($format)
{
	case 'json': // as per API/project/export.php
		$content = json_encode($result);
		break;
	case 'xml': // as per API/project/export.php
        $content = '<?xml version="1.0" encoding="UTF-8" ?>';
        $content .= "\n<items>\n";
        foreach ($result as $item => $value) {
            if ($value != "")
                $content .= "<$item><![CDATA[" . $value . "]]></$item>";
            else
                $content .= "<$item></$item>";
        }
        $content .= "\n</items>\n";
        break;
	case 'csv': // as per API/project/export.php
    	$fp = fopen('php://memory', "x+");
        fputcsv($fp, array_keys($result), User::getCsvDelimiter(), '"', '');
    	fputcsv($fp, $result, User::getCsvDelimiter(), '"', '');
    	fseek($fp, 0);
        $content = stream_get_contents($fp);
		break;
}

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);