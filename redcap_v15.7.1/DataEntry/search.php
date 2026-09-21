<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Retrieve matching records to populate auto-complete box.
// Make sure field is valid and that user has data entry rights to the form this field is on.
if ($isAjax && isset($_GET['term']))
{
	// If field is passed, make sure it's valid for this project
	if (isset($_GET['field']) && $_GET['field'] != '' && (!isset($Proj->metadata[$_GET['field']])
			|| (UserRights::hasDataViewingRights($user_rights['forms'][$Proj->metadata[$_GET['field']]['form_name']], "no-access") && $_GET['field'] != $Proj->table_pk)))
	{
		exit('[]');
	}

	## PERFORMANCE: Kill any currently running processes by the current user/session on THIS page
	System::killConcurrentRequests(5);

	// Make sure this process does not last more than 3 minutes
	ini_set('max_execution_time', 180);
	@set_time_limit(180);

	// Decode the search string
	$queryString = trim(label_decode(urldecode($_GET['term'])));
	$queryStringLength = strlen($queryString);

	// Retrieve record list (exclude non-DAG records if user is in a DAG)
	$group_sql = "";
	if ($user_rights['group_id'] != "") {
		$group_sql = "and record in (" . prep_implode(Records::getRecordListSingleDag($project_id, $user_rights['group_id'])) . ")";
	}

	// Modify SQL if using double data entry as DDE person
	$sql_dde_record_append = '';
	if ($double_data_entry && $user_rights['double_data'] != "0") {
		$sql_record_field = "substring(record,1,locate('--',record)-1) as record";
		$sql_dde_record_append = "and record like '%--{$user_rights['double_data']}'";
	} else {
		$sql_record_field = "record";
		$entry_num = "";
	}

	// Set the LIKE clause for the query
	$sql_value_like = "value like '%".db_escape($queryString)."%'";

	// Check if we should also search for the escaped value of the string
	$queryStringEscaped = htmlspecialchars($queryString, ENT_QUOTES);
	if ($queryString != $queryStringEscaped) {
		$sql_value_like .= " or value like '%".db_escape($queryStringEscaped)."%'";
	}

	// If query field is the table_pk and project is longitudinal, then only return a single entry for first event on each arm
	$sql_table_pk = "";
	if ($longitudinal) {
		$sql_table_pk = "and event_id in (" . implode(",", array_keys($Proj->eventInfo)) . ")";
	} else {
		$sql_table_pk = "and event_id = " . $Proj->firstEventId;
	}

	// Search on specific field
	if (isset($_GET['field']) && $_GET['field'] != '') {
		$sql_field = "and field_name = '".db_escape($_GET['field'])."'";
	} else {
		// Build array of fields that user has read-only or edit access to
		$fields = array();
		$exclude_fieldtypes = array("file", "descriptive", "checkbox", "dropdown", "select", "radio", "yesno", "truefalse");
		foreach ($Proj->metadata as $field=>$row)
		{
			// Do not include certain field types
			if (in_array($row['element_type'], $exclude_fieldtypes)) continue;
			// Do not include fields from forms the user does not have access to
			if (UserRights::hasDataViewingRights($user_rights['forms'][$row['form_name']], "no-access") && $field != $Proj->table_pk) continue;
			// Build list option
			$fields[] = $field;
		}
		$sql_field = "and field_name in (".prep_implode($fields).")";
	}

	// Custom record labels and/or Secondary unique field
	$customLabels = array();
	if ($secondary_pk != '' || $custom_record_label != '')
	{
		// Get record list
		$recordsMatch = array();
		$sql = "select distinct $sql_record_field
				from ".\Records::getDataTable($project_id)." where project_id = $project_id $sql_field
				$group_sql and ($sql_value_like) $sql_dde_record_append $sql_table_pk";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$recordsMatch[] = $row['record'];
		}
		$customLabels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($recordsMatch, true, getArm());
	}

	// Query the project data
	$sql = "select distinct event_id, field_name, value, instance, $sql_record_field
			from ".\Records::getDataTable($project_id)." where project_id = $project_id $sql_field
			$group_sql and ($sql_value_like) $sql_dde_record_append $sql_table_pk
			order by abs(value), value";
	//Execute query
	$q = db_query($sql);
	$rowcount = db_num_rows($q);
	$recs = array();
	$recinfo = $userMatchScore = array();
	if ($q && $rowcount > 0)
	{
		// Retrieve all matches
		$key = 0;
		$usedValues = array();
		while ($result = db_fetch_assoc($q))
		{
			// Set string to collect any custom labels to return
			$record_custom_labels = isset($customLabels[$result['record']]) ? " ".$customLabels[$result['record']] : "";
			// Set variables
			$record = $result['record'];
			$result_raw = decode_filter_tags($result['value']);
			$result_len = strlen($result_raw);
			$form = $Proj->metadata[$result['field_name']]['form_name'];
			// If project is longitudinal, make sure form is designated for this event
			if (!in_array($form, ($Proj->eventsForms[$result['event_id']] ?? []))) {
				if ($result['field_name'] == $Proj->table_pk) {
					// If record ID field, then just use the first viable form in this event
					$form = $Proj->eventsForms[$result['event_id']][0];
                    // If user doesn't have rights to this form, then just skip this result
                    if (UserRights::hasDataViewingRights($user_rights['forms'][$form], "no-access")) continue;
				} else {
					// Field is not designated or this event, so skip it (invalid location)
					continue;
				}
			}

			// Set what will be seen by user in auto complete list
			$record_display = $table_pk_label . " <b>$record</b>" . "<i>$record_custom_labels</i>";

			// Append the event info if longitudinal
			if ($longitudinal) {
				$record_display .= " {$lang['global_108']} <span>" . decode_filter_tags($Proj->eventInfo[$result['event_id']]['name_ext']) . "</span>";
			}

			// Set label
			$result_display = RCView::TrimAndHighlightForDropdownDisplay($result_raw, $queryString, 30);
			$label = "&quot;" . $result_display . "&quot; " . $lang['global_107'].' ' . $record_display;

			// Instance: Set as 1 for record ID field so as not to return multiple instances of record ID
			$isRecordIdField = ($result['field_name'] == $table_pk || (isset($_GET['field']) && $_GET['field'] == $table_pk));
			if ($result['instance'] == '' || $isRecordIdField) {
				$result['instance'] = '1';
			}

			// If user is searching on the record ID field, but they don't have access to the first form, then send them to
			if ($_GET['field'] == $Proj->table_pk && UserRights::hasDataViewingRights($user_rights['forms'][$Proj->metadata[$_GET['field']]['form_name']], "no-access")) {
				foreach ($user_rights['forms'] as $this_form=>$this_level) {
					if (UserRights::hasDataViewingRights($this_level, "no-access")) continue;
					$form = $this_form;
					break;
				}
			}

            // If user does not have access to $form, then leave blank so that we direct them to the Record Home Page
            if (UserRights::hasDataViewingRights($user_rights['forms'][$form], "no-access")) $form = '';

            // Get arm number
            $arm = $Proj->eventInfo[$result['event_id']]['arm_num'] ?? '1';

			// Set the record, event_id, and form (delimited with a pipe)
			$values = "{$result['instance']}|$form|{$result['event_id']}|$arm|$record";

			// Prevent multiple instances of record ID field if first form is repeating
			if ($isRecordIdField && isset($usedValues[$values])) continue;

			// Add to arrays
			$recinfo[$key] = array('value'=>$values, 'label'=>$label);
			$usedValues[$values] = true;

			// Scoring
			if (strpos($result_raw, " ") !== false) {
				$value_words = explode(" ", trim(strtolower($result_raw)));
			} else {
				$value_words = array(trim(strtolower($result_raw)));
			}
			// Add score for partial match
			$userMatchScore[$key] = 10;
			// If matches EXACTLY, do a +100 on score
			if (in_array(trim(strtolower($queryString)), $value_words)) {
				$userMatchScore[$key] = $userMatchScore[$key]+100;
			}

			// Increment key
			$key++;
		}
	}

	// Sort by matching score
	array_multisort($userMatchScore, SORT_NUMERIC, SORT_DESC, $recinfo);

	// Limit to top X number of matching results
	$limit_results = 25;
	if (count($recinfo) > $limit_results) {
		$recinfo = array_slice($recinfo, 0, $limit_results);
	}

	//Render JSON
	header("Content-Type: application/json");
	print json_encode_rc($recinfo);
}
elseif ($isAjax)
{
	print '[]';
}
else
{
	// User should not be here! Redirect to index page.
	redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");
}