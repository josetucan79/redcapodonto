<?php
global $format, $returnFormat;

// Check for required privileges
if ($post['dag_rights'] != '1') die(RestUtility::sendResponse(400, $lang['api_221'], $returnFormat));

# get all the records to be exported
$result = Project::getDAGRecords();

# structure the output data accordingly
switch($format)
{
	case 'json':
		$content = json_encode($result);
		break;
	case 'xml':
		$content = xml($result);
		break;
	case 'csv':
		$content = (!empty($result)) ? arrayToCsv($result) : 'data_access_group_name,unique_group_name,data_access_group_id';
		break;
}

/************************** log the event **************************/

# Logging
Logging::logEvent("", "redcap_data_access_groups", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export DAGs (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<dags>\n";

	foreach ($dataset as $row)
	{
		$line = '';
		foreach ($row as $item => $value)
		{
			if ($value != "")
				$line .= "<$item><![CDATA[" . html_entity_decode($value, ENT_QUOTES) . "]]></$item>";
			else
				$line .= "<$item></$item>";
		}

		$output .= "<item>$line</item>\n";
	}
	$output .= "</dags>\n";

	return $output;
}