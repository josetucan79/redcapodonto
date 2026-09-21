<?php
global $format, $returnFormat, $post;

// Check for required privileges
if ($post['user_rights'] == '0') die(RestUtility::sendResponse(400, $lang['api_229'], $returnFormat));

# get all the records to be exported
$result = getItems();

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
		$content = csv($result);
		break;
}

/************************** log the event **************************/



# Logging
Logging::logEvent("", "redcap_user_rights", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export users (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
	global $mobile_app_enabled;
	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<users>\n";
	foreach ($dataset as $row)
	{
		$output .= "<item>";
		foreach ($row as $key=>$val)
		{
			if ($key == 'forms') {
				$output .= "<forms>";
				foreach ($row['forms'] as $form => $right) {
					$output .= "<$form>$right</$form>";
				}
				$output .= "</forms>";
			} elseif ($key == 'forms_export') {
				$output .= "<forms_export>";
				foreach ($row['forms_export'] as $form => $right) {
					$output .= "<$form>$right</$form>";
				}
				$output .= "</forms_export>";
			} else {
				$output .= "<$key>" . htmlspecialchars($val, ENT_XML1, 'UTF-8') . "</$key>";
			}
		}
		$output .= "</item>\n";
	}
	$output .= "</users>\n";
	return $output;
}

function csv($dataset)
{
	foreach ($dataset as $index => $user) {
		$forms_string = array();
		foreach($user['forms'] as $form => $right) {
			$forms_string[] = "$form:$right";
		}
		$dataset[$index]['forms'] = implode(",", $forms_string);

		$forms_string = array();
		foreach($user['forms_export'] as $form => $right) {
			$forms_string[] = "$form:$right";
		}
		$dataset[$index]['forms_export'] = implode(",", $forms_string);
	}
	return arrayToCsv($dataset);
}

function getItems()
{
	global $post, $mobile_app_enabled;
    $result = UserRights::getUserDetails($post['projectid'], $mobile_app_enabled);

	return $result;
}
