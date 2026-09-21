<?php
global $format, $returnFormat, $post, $require_change_reason;

// If user has "No Access" export rights, then return error
if ($post['export_rights'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you have "No Access" data export rights. Higher level data export rights are required for this operation.'));
}
// Check for logging privileges
$user_rights = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights[PROJECT_ID][strtolower(USERID)];
$ur = new UserRights();
$user_rights = $ur->setFormLevelPrivileges($user_rights);
if ($user_rights['data_logging'] == '0') {
    exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you do not have "Logging" privileges, which are required for this operation.'));
}

$_GET = array('filters_download_all' => 1,
            'logtype' => $_POST['logtype'] ?? "",
            'record' => $_POST['record'] ?? "",
            'usr' => $_POST['user'] ?? "",
            'dag' => $_POST['dag'] ?? "",
            'beginTime' => $_POST['beginTime'] ?? "",
            'endTime' => $_POST['endTime'] ?? ""
);

$Proj = new Project();
$project_id = $Proj->project_id;
include APP_PATH_DOCROOT . 'Logging/filters.php';
// Increase memory limit in case needed for intensive processing
System::increaseMemory(2048);
$require_change_reason = $Proj->project['require_change_reason'];
// Query logging table
$result = db_query($logging_sql);
// Set headers
$header = "timestamp,username,action,details,record";
// If project-level flag is set, then add "reason changed" to row data
if ($Proj->project['require_change_reason']) $header .= ",reason";

if ($result)
{
    // Set values for this row and write to file
    $i = 0;
    while ($row = db_fetch_assoc($result))
    {
        if (!SUPER_USER && (strpos($row['description'], "(Admin only) Stop viewing project as user") === 0 || strpos($row['description'], "(Admin only) View project as user") === 0)) {
            continue;
        }
        $resultRow = Logging::renderLogRow($row, false);
        // Get record name, if a record-centric event
        $record = "";
        // Add to array
        $logs[$i] = array(
            'timestamp' => $resultRow[0],
            'username'  => $resultRow[1],
            'action'    => $resultRow[2],
            'details'   => $resultRow[3],
            'record'    => $resultRow[4]
        );
        if ($Proj->project['require_change_reason']) {
            $logs[$i]['reason'] = $resultRow[5];
        }
        $i++;
    }
}
else
{
    print $lang['global_01'];
}
# structure the output data accordidngly
switch($format)
{
    case 'json':
    	if (!is_array($logs)) $logs = [];
        $content = json_encode_rc($logs);
        break;
    case 'xml':
        $content = xml($logs);
        break;
    case 'csv':
        $content = (!empty($logs)) ? arrayToCsv($logs) : $header;
        break;
}

/************************** log the event **************************/

# Logging
Logging::logEvent("", Logging::getLogEventTable(PROJECT_ID), "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export Logging (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
    $output = '<?xml version="1.0" encoding="UTF-8" ?>';
    $output .= "\n<logs>\n";
    if (is_array($dataset)) {
		foreach ($dataset as $row) {
			$line = '';
			foreach ($row as $item => $value) {
				if ($value != "")
					$line .= "<$item><![CDATA[" . html_entity_decode($value, ENT_QUOTES) . "]]></$item>";
				else
					$line .= "<$item></$item>";
			}
			$output .= "<log>$line</log>\n";
		}
	}
    $output .= "</logs>\n";
    return $output;
}