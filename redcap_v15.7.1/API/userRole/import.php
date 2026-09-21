<?php
global $format, $returnFormat, $post;

// Check for required privileges
if ($post['user_rights'] != '1') die(RestUtility::sendResponse(400, $lang['api_171'], $returnFormat));

# Logging
Logging::logEvent("", "redcap_user_roles", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Import user roles (API$playground)");

# put all the records to be imported
$content = putItems();

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function putItems()
{
	global $post, $format, $lang;
	$count = 0;
	$errors = array();
	$data = $post['data'];

	$Proj = new Project();
	switch($format)
	{
	case 'json':
		// Decode JSON into array
		$data = json_decode($data, true);
		if ($data == '') return $lang['data_import_tool_200'];
		break;
	case 'xml':
		// Decode XML into array
		$data = Records::xmlDecode(html_entity_decode($data, ENT_QUOTES));
		if ($data == '' || !isset($data['userRoles']['item'])) return $lang['data_import_tool_200'];
		$data = (isset($data['userRoles']['item'][0])) ? $data['userRoles']['item'] : array($data['userRoles']['item']);
		break;
	case 'csv':
		// Decode CSV into array
		$data = str_replace(array('&#10;', '&#13;', '&#13;&#10;'), array("\n", "\r", "\r\n"), $data);
		$data = csvToArray($data);
		// Reformat form-level rights for CSV only
		foreach ($data as $key=>$this_user) {
			if (isset($this_user['forms']) && $this_user['forms'] != '') {
				$these_forms = array();
				foreach (explode(",", $this_user['forms']) as $this_pair) {
					list ($this_form, $this_right) = explode(":", $this_pair, 2);
					$these_forms[$this_form] = $this_right;
				}
				$data[$key]['forms'] = $these_forms;
			}
            if (isset($this_user['forms_export']) && $this_user['forms_export'] != '') {
                $these_forms = array();
                foreach (explode(",", $this_user['forms_export']) as $this_pair) {
                    list ($this_form, $this_right) = explode(":", $this_pair, 2);
                    $these_forms[$this_form] = $this_right;
                }
                $data[$key]['forms_export'] = $these_forms;
            }
		}
		break;
	}

	// Begin transaction
	db_query("SET AUTOCOMMIT=0");
	db_query("BEGIN");

    list ($count, $errors) = UserRights::uploadUserRoles(PROJECT_ID, $data);

	if (!empty($errors)) {
		// ERROR: Roll back all changes made and return the error message
		db_query("ROLLBACK");
		db_query("SET AUTOCOMMIT=1");
		die(RestUtility::sendResponse(400, implode("\n", $errors)));
	}

	db_query("COMMIT");
	db_query("SET AUTOCOMMIT=1");

	return $count;
}
