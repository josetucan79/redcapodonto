<?php

/**
 * Design
 * This class is used for design-centric methods (e.g., Online Designer).
 */
class Design
{
	// Reset and fix the Form Menu Description of any forms that somehow lost their form menu label (from moving fields, etc.)
	// Use $Proj->forms or $Proj->forms_temp (if in Draft Mode)
	public static function fixFormLabels()
	{
		global $Proj, $status;
		// Get forms array of all form info first
		$forms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
		// First get a list of all forms that are missing a form label for their first field and ONLY fix those
		$sql = "select y.field_name, y.form_name from (select min(field_order) as min_order from $metadata_table
				where project_id = " . PROJECT_ID . " group by form_name order by field_order) x, $metadata_table y
				where x.min_order = y.field_order and y.project_id = " . PROJECT_ID . " and y.form_menu_description is null";
		$q = db_query($sql);
		if ($q && db_num_rows($q) > 0)
		{
			while ($row = db_fetch_assoc($q)) {
				// Set vars
				$field = $row['field_name'];
				$form = $row['form_name'];
				// If can't find current form label, then generate one from the form_name itself
				$formLabel = isset($forms[$form]['menu']) ? $forms[$form]['menu'] : trim(ucwords(str_replace("_", " ", $form)));
				// First set all form menus to null for ALL fields on this form ONLY
				$sql = "update $metadata_table set form_menu_description = null
						where project_id = " . PROJECT_ID . " and form_name = '" . db_escape($form) . "'";
				db_query($sql);
				// Now set label for this form
				$sql = "update $metadata_table set form_menu_description = '".db_escape($formLabel)."'
						where project_id = " . PROJECT_ID . " and field_name = '".db_escape($field)."'";
				db_query($sql);
			}
		}
	}

	// Get the first field for a given form
	// Set to query the metadata or metadata_temp table (if in Draft Mode)
	public static function getFirstFieldOfForm($form,$metadata_table="redcap_metadata")
	{
		$sql = "select field_name from $metadata_table where project_id = " . PROJECT_ID . "
				and form_name = '" . db_escape($form) . "' order by field_order limit 1";
		$q = db_query($sql);
		if ($q && db_num_rows($q)) {
			return db_result($q, 0);
		} else {
			return false;
		}
	}

	// Determine if the PK (record identifier) field just changed in the current script
	// (Assumes we're in Design Mode and NOT in the middle of a transaction)
	public static function recordIdFieldChanged()
	{
		global $Proj, $status;
		// Get PK when this script began (assume we're in Design mode - e.g. use temp if in production)
		$current_table_pk = ($status > 0) ? $Proj->table_pk_temp : $Proj->table_pk;
		// Query metadata table to find current PK right now
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
		$sql = "select field_name from $metadata_table where project_id = " . PROJECT_ID . " order by field_order limit 1";
		$new_table_pk = db_result(db_query($sql), 0);
		// Return boolean of whether PK changed (true if changed)
		return ($current_table_pk != $new_table_pk);
	}

	// Determine if survey question auto-numbering should be disabled (because branching logic exists)
	// Return boolean (true = it disabled question auto-numbering)
	public static function checkDisableSurveyQuesAutoNum($form)
	{
		global $status, $Proj;

		if ($status < 1
			&& isset($Proj->forms[$form]['survey_id'])
			&& $Proj->surveys[$Proj->forms[$form]['survey_id']]['question_auto_numbering']
			&& self::checkSurveyBranchingExists($form))
		{
			// Survey is using auto question numbering and has branching, so set to custom numbering
			$sql = "update redcap_surveys set question_auto_numbering = 0 where survey_id = " . $Proj->forms[$form]['survey_id'];
			return (db_query($sql));
		}
		return false;
	}

	// SURVEY QUESTION NUMBERING: Detect if any survey questions have branching logic. Return true/false if so.
	public static function checkSurveyBranchingExists($form,$metadata_table="redcap_metadata")
	{
		global $Proj;
		// Make sure this form is enabled as a survey first
		$survey_id = $Proj->forms[$form]['survey_id'];
		if (!empty($survey_id))
		{
			// Find any fields in survey with branching logic
			$sql = "select 1 from $metadata_table where project_id = " . PROJECT_ID . " and branching_logic is not null
					and form_name = '".db_escape($form)."' limit 1";
			$q = db_query($sql);
			$hasBranching = db_num_rows($q);
			// Return if has branching or not
			return $hasBranching;
		}
		// Not a survey, so return false
		return false;
	}


	// For a given form in a longitudinal project, return a list of events for which a form is designated
	// in order to select one to set up or modify Automated Survey Invitations
	public static function getEventsAutomatedInvitesForForm($form)
	{
		global $Proj;
		// Create array to put events that have automated invites activated for THIS form
		$formEventsWithAutomatedInvites = array();
		$sql = "select ss.event_id, ss.active from redcap_surveys_scheduler ss, redcap_surveys s
				where s.survey_id = ss.survey_id and s.form_name = '".db_escape($form)."' and s.project_id = " . PROJECT_ID;
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$formEventsWithAutomatedInvites[$row['event_id']][$form] = $row['active'];
		}
		// Gather event names into separate divs/rows for the hidden div below
		$chooseEventRows = array();
		foreach ($Proj->eventInfo as $this_event_id=>$attr) {
			// Only use this event if form is designation for it
			if (!isset($Proj->eventsForms[$this_event_id]) || !in_array($form, $Proj->eventsForms[$this_event_id])) continue;
			// Set name of event
			$chooseEventRows[$this_event_id]['name'] = $attr['name_ext'];
			// Set 1 if automated invites are Active for this form/event
			$chooseEventRows[$this_event_id]['active'] = $formEventsWithAutomatedInvites[$this_event_id][$form] ?? '';
		}
		// Return array
		return $chooseEventRows;
	}


	// Create array of form_names that have automated invitations set for them (not checking more granular at event_id level)
	// Each form will have 0 and 1 subcategory to count number of active(1) and inactive(0) schedules for each.
	public static function formsWithAutomatedInvites()
	{
		global $Proj;
		// Collect values in an array
		$formsWithAutomatedInvites = array();
		$sql = "select s.form_name, ss.active, ss.event_id from redcap_surveys_scheduler ss, redcap_surveys s
				where s.survey_id = ss.survey_id and s.project_id = " . PROJECT_ID;
		$q = db_query($sql);
		// Loop through each instrument
		while ($row = db_fetch_assoc($q))
		{
			// If event is not valid (maybe project used to be longitudinal and had auto invites set), then skip it
			if (!isset($Proj->eventInfo[$row['event_id']])) continue;
			// If project is set to be longitudinal (has repeatforms flag enabled), then make sure the form is designated for this event_id
			if ($Proj->project['repeatforms'] && ($row['form_name'] == null || !isset($Proj->eventsForms[$row['event_id']]) || !in_array($row['form_name'], $Proj->eventsForms[$row['event_id']]))) {
				continue;
			}
			// Pre-fill with default values first
			if (!isset($formsWithAutomatedInvites[$row['form_name']])) {
				$formsWithAutomatedInvites[$row['form_name']] = array('0'=>0, '1'=>0);
			}
			// Increment number of active/inactive counts
			$formsWithAutomatedInvites[$row['form_name']][$row['active']]++;
		}
		return $formsWithAutomatedInvites;
	}
	
	// CHECK IF NEED TO DELETE ATTACHMENT FROM FIELD
	// If edoc_id exists for a field, then set as "deleted" in edocs_metadata table (development only OR if added then deleted in Draft Mode)
	public static function deleteEdoc($field_name)
	{
		global $status;
		//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
		// Get current edoc_id
		$q = db_query("select edoc_id from $metadata_table where project_id = ".PROJECT_ID." and field_name = '$field_name' limit 1");
		$current_edoc_id = db_result($q, 0);
		if (!empty($current_edoc_id))
		{
			// Check if in development - default value
			$deleteEdoc = ($status < 1);
			// If in production, check if edoc_id exists in redcap_metadata table. If not, set to delete.
			if (!$deleteEdoc)
			{
				$q = db_query("select 1 from redcap_metadata where project_id = ".PROJECT_ID." and edoc_id = $current_edoc_id limit 1");
				$deleteEdoc = (db_num_rows($q) < 1) ;
			}
			// Set edoc as deleted if met requirements for deletion
			if ($deleteEdoc)
			{
				db_query("update redcap_edocs_metadata set delete_date = '".NOW."' where project_id = ".PROJECT_ID." and doc_id = $current_edoc_id");
			}
		}
	}


	/*
	// Determine if a matrix-formatted field got moved during a field reorder
	// (need to know this in order to reload Online Designer table).
	// Use $Proj->metadata as the "old_order" of fields for the form.
	public static function matrixFieldsMoved($form,$new_order)
	{
		global $Proj, $status;
		// Get array of all metadata attribute (from $Proj based on status)
		$metadata = ($status > 0) ? $Proj->metadata_temp : $Proj->metadata;
		// Get old order
		$old_order = array();
		foreach ($metadata as $field=>$attr) {
			if ($attr['form_name'] == $form && $field != $form."_complete") {
				$old_order[] = $field;
			}
		}
		// Remove all Section Headers and blank values from $new_order
		$new_order_temp = array();
		foreach (explode(",", $new_order) as $field) {
			if ($field != "" && strpos($field, "-sh") === false) {
				$new_order_temp[] = $field;
			}
		}
		$new_order = $new_order_temp;
		// Move through the new order, and if any matrix fields have moved, return true
		foreach ($new_order as $order_num=>$field) {
			// If this field is a matrix field
			if ($metadata[$field]['grid_name'] != "") {
				// Get fields order num in old_order
				$order_num_old = array_search($field, $old_order);
				// If this field is not in same place, return true
				if ($order_num_old !== false && $order_num_old != $order_num) {
					return true;
				}
			}
		}
		// Unset arrays
		unset($metadata,$new_order,$old_order);
		// Return false if found nothing
		return false;
	}
	*/

	// Convert CSV file into an array
	public static function excel_to_array($excelfilepath, $delimiter = ",")
	{
		global $lang, $project_language, $surveys_enabled, $project_encoding;

		// Set up array to switch out Excel column letters
		$cols = MetaData::getCsvColNames();

		// Extract data from CSV file and rearrange it in a temp array
		$newdata_temp = array();
		$i = 1;
		$removeQuotes = false;

		if (($handle = fopen($excelfilepath, "rb")) !== false)
		{
			// Loop through each row
			while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false)
			{
				// Skip row 1
				if ($i == 1)
				{
					## CHECK DELIMITER
					// Determine if comma- or tab-delimited (if can't find comma, it will revert to tab delimited)
					$firstLine = implode($delimiter, $row);
					// If we find X number of tab characters, then we can safely assume the file is tab delimited
					$numTabs = 6;
					if (substr_count($firstLine, "\t") > $numTabs)
					{
						// Set new delimiter
						$delimiter = "\t";
						// Fix the $row array with new delimiter
						$row = explode($delimiter, $firstLine);
						// Check if quotes need to be replaced (added via CSV convention) by checking for quotes in the first line
						// If quotes exist in the first line, then remove surrounding quotes and convert double double quotes with just a double quote
						$removeQuotes = (substr_count($firstLine, '"') > 0);
					}
					// Increment counter
					$i++;
					// Check if legacy column Field Units exists. If so, tell user to remove it (by returning false).
					// It is no longer supported but old values defined prior to 4.0 will be preserved.
					if (strpos(strtolower($row[2]), "units") !== false)
					{
						return false;
					}
					continue;
				}
				// Loop through each column in this row
				for ($j = 0; $j < count($row); $j++)
				{
					// If tab delimited, compensate sightly
					if ($delimiter == "\t")
					{
						// Replace characters
						$row[$j] = str_replace("\0", "", $row[$j]);
						// If first column, remove new line character from beginning
						if ($j == 0) {
							$row[$j] = str_replace("\n", "", ($row[$j]));
						}
						// If the string is UTF-8, force convert it to UTF-8 anyway, which will fix some of the characters
						if (function_exists('mb_detect_encoding') && mb_detect_encoding($row[$j]) == "UTF-8")
						{
							$row[$j] = utf8_encode_rc($row[$j]);
						}
						// Check if any double quotes need to be removed due to CSV convention
						if ($removeQuotes)
						{
							// Remove surrounding quotes, if exist
							if (substr($row[$j], 0, 1) == '"' && substr($row[$j], -1) == '"') {
								$row[$j] = substr($row[$j], 1, -1);
							}
							// Remove any double double quotes
							$row[$j] = str_replace("\"\"", "\"", $row[$j]);
						}
					}
					// Add to array
					$newdata_temp[$cols[$j+1]][$i] = $row[$j];
					// Use only for Japanese SJIS encoding
					if ($project_encoding == 'japanese_sjis')
					{
						$newdata_temp[$cols[$j+1]][$i] = mb_convert_encoding($newdata_temp[$cols[$j+1]][$i], 'UTF-8',  'sjis');
					}
				}
				$i++;
			}
			fclose($handle);
		} else {
			// ERROR: File is missing
			$fileMissingText = (!SUPER_USER) ? $lang['period'] : " (".APP_PATH_TEMP."){$lang['period']}<br><br>{$lang['file_download_13']}";
			print 	RCView::div(array('class'=>'red'),
				RCView::b($lang['global_01'].$lang['colon'])." {$lang['file_download_08']} <b>\"".htmlspecialchars(basename($excelfilepath), ENT_QUOTES)."\"</b>
                        {$lang['file_download_12']}{$fileMissingText}"
			);
			exit;
		}

		// If file was tab delimited, then check if it left an empty row on the end (typically happens)
		if ($delimiter == "\t" && $newdata_temp['A'][$i-1] == "")
		{
			// Remove the last row from each column
			foreach (array_keys($newdata_temp) as $this_col)
			{
				unset($newdata_temp[$this_col][$i-1]);
			}
		}

		// Return array with data dictionary values
		return $newdata_temp;

	}

	/**
	 * RENDER DATA DICTIONARY ERRORS
	 */
	public static function renderErrors($errors_array) {
		global $lang;
		foreach ($errors_array as &$item) {
			$item = decode_filter_tags($item);
		}
		print 	"<div class='red' style='margin-top:10px;'>
                    <img src='".APP_PATH_IMAGES."exclamation.png'>
                    <b>{$lang['database_mods_59']}</b><br><br>
                    <p style='border-bottom:1px solid #aaa;font-weight:bold;font-size:16px;color:#800000 !important;'>{$lang['database_mods_60']}</p>
                    <p>" . implode("</p><p style='padding-top:5px;border-top:1px solid #aaa;'>", $errors_array) . "</p>
                </div>";
	}

	/**
	 * RENDER DATA DICTIONARY WARNINGS
	 */
	public static function renderWarnings($warnings_array) {
		global $lang;
		foreach ($warnings_array as &$item) {
			$item = decode_filter_tags($item);
		}
		//Display warnings
		if (count($warnings_array) > 0) {
			print "<div class='yellow' style='margin-top:15px;'>";
			print "<p style='border-bottom:1px solid #aaa;font-weight:bold;font-size:16px;color:#800000 !important;'>{$lang['database_mods_61']}</p>";
			print "<p>" . implode("</p><p style='padding-top:5px;border-top:1px solid #aaa;'>", $warnings_array) . "</p>";
			print "</div>";
		}
	}

	/**
	 * RENDER TABLE TO DISPLAY METADATA CHANGES
	 */
	public static function getMetadataDiff($num_records=0, $returnOnlyFieldsModified=false)
	{
		global $lang, $Proj, $enable_field_attachment_video_url;

		$html = "";

		// Build arrays with all old values, drafted values, and changes
		$metadata_new = array();
		$metadata_old = array();
		$metadata_changes = array();
		$fieldsCriticalIssues = array(); // Capture fields with critical issues

		// Metadata columns that need html decoding
		$metadataDecode = array("element_preceding_header", "element_label", "element_enum", "element_note", "branching_logic", "question_num");

		// Get existing field values
		$sql = "select field_name, element_preceding_header, element_type, element_label, element_enum,
                element_note, element_validation_type, element_validation_min, element_validation_max, field_phi,
                branching_logic, field_req, edoc_id, custom_alignment, stop_actions, question_num, grid_name, grid_rank
                ".($enable_field_attachment_video_url ? ", video_url, video_display_inline " : "").", misc
                from redcap_metadata where project_id = " . PROJECT_ID . " order by field_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Do html decoding for certain fields
			foreach ($metadataDecode as $col)
			{
				$row[$col] = label_decode($row[$col]);
			}
			foreach ($row as &$thisCol) {
				// Make sure that all line breaks (Linux, Windows, Mac) are converted to \n for consistency
				$thisCol = $thisCol == '' ? '' : str_replace(array("\r\n", "\r"), array("\n", "\n"), $thisCol);
			}
			// Add to array
			$metadata_old[$row['field_name']] = $row;
		}

		// Get new field values and store changes in array
		$sql = "select field_name, element_preceding_header, element_type, element_label, element_enum,
                element_note, element_validation_type, element_validation_min, element_validation_max, field_phi,
                branching_logic, field_req, edoc_id, custom_alignment, stop_actions, question_num, grid_name, grid_rank
                ".($enable_field_attachment_video_url ? ", video_url, video_display_inline " : "").", misc
                from redcap_metadata_temp where project_id = " . PROJECT_ID . " order by field_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Do html decoding for certain fields
			foreach ($metadataDecode as $col)
			{
				$row[$col] = label_decode($row[$col]);
			}
			foreach ($row as &$thisCol) {
				// Make sure that all line breaks (Linux, Windows, Mac) are converted to \n for consistency
				$thisCol = $thisCol == '' ? '' : str_replace(array("\r\n", "\r"), array("\n", "\n"), $thisCol);
			}
			$metadata_new[$row['field_name']] = $row;
			// Check to see if values are different from existing field. If they are, don't include in new array.
			if (!isset($metadata_old[$row['field_name']]) || $row !== $metadata_old[$row['field_name']]) {
				$metadata_changes[$row['field_name']] = $row;
			}
		}

		// If $returnOnlyFieldsModified=true, then return array of only field names of fields that changed
		if ($returnOnlyFieldsModified) {
			return array_keys($metadata_changes);
		}

		// Count number of changes
		$num_metadata_changes = count($metadata_changes);

		// Query to find fields with data
		$sql = "select distinct field_name, value from ".\Records::getDataTable(PROJECT_ID)." where project_id = ".PROJECT_ID."
                and field_name in (".prep_implode(array_keys($metadata_changes)).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// If value is blank, then skip this
			if ($row['value'] == '') continue;
			// Add field to array as key
			$fieldsWithData[$row['field_name']] = true;
		}

		//CSS to hide some elements
		if ($num_metadata_changes == 0) {
			$html .= "<style type='text/css'>
                    #tableChanges, #metadataCompareKey, #tableChangesPretext { display: none !important; }
                    </style>";
		}

		$html .= "<div id='tableChangesPretext' style='font-weight:bold;padding:30px 4px 8px;'>
                    {$lang['database_mods_62']}
                    <span id='ShowMoreAll' style='visibility:hidden;color:#777 !important;font-weight:normal;'>
                        <a href='javascript:;' onclick='metaDiffShowAll()' style='text-decoration:underline;margin:0 2px 0 50px;'>{$lang['database_mods_177']}</a>
                        {$lang['database_mods_178']}
                    </span>
                </div>";

		// Now loop through changes and new fields and render table
		$html .= "<table id='tableChanges' class='metachanges' border='1' cellspacing='0' cellpadding='10' style='width:100%;border:1px solid gray;font-family:Verdana,Arial;font-size:10px;'>
                    <tr style='background-color:#a0a0a0 !important;font-weight:bold;'>
                        <td>{$lang['global_44']}</td>
                        <td>{$lang['database_mods_65']}</td>
                        <td>{$lang['database_mods_66']}</td>
                        <td>{$lang['global_40']}</td>
                        <td>{$lang['database_mods_68']}</td>
                        <td>{$lang['database_mods_69']}</td>
                        <td>{$lang['database_mods_70']}</td>
                        <td>{$lang['database_mods_71']}</td>
                        <td>{$lang['database_mods_72']}</td>
                        <td>{$lang['database_mods_73']}</td>
                        <td>{$lang['database_mods_74']}</td>
                        <td>{$lang['database_mods_75']}</td>
                        <td>{$lang['database_mods_105']}</td>
                        <td>{$lang['design_212']}</td>
                        <td>{$lang['database_mods_108']}</td>
                        <td>{$lang['design_221']}</td>
                        <td>{$lang['database_mods_132']}</td>
                        <td>{$lang['design_504']}</td>
                        ".($enable_field_attachment_video_url ? "<td>{$lang['design_575']}</td><td>{$lang['design_579']}</td>" : "")."
                        <td>{$lang['design_527']}</td>
                    </tr>";
		// Collect names of fields being modified
		$fieldsModified = array();
		// Render each table row
		foreach ($metadata_changes as $field_name=>$attr)
		{
			// If a new field, set bgcolor to green, otherwise set as null
			$bgcolor_row = !isset($metadata_old[$field_name]) ? "style='background-color:#7BED7B !important;'" : "";
			// Begin row
			$html .= "<tr $bgcolor_row>";
			// Loop through each cell in row
			foreach ($attr as $key=>$value) {
				// Set default bgcolor for cell as null
				$bgcolor = "";
				// Tranform any raw legacy values to user-readable values
				$value = Design::transformMetaVals($value, $key);
				// Analyze changes for existing field
				if ($bgcolor_row == "") {
					// Retrieve existing value
					$old_value = $metadata_old[$field_name][$key];
					// Tranform any raw legacy values to user-readable values
					$old_value = Design::transformMetaVals($old_value, $key);
					// If new and existing values are different...
					if ($old_value != $value) {
						// Set bgcolor as yellow to denote changes
						$bgcolor = "style='background-color:#ffff80 !important;'";
						// Append existing value in gray text
						$value = nl2br(RCView::escape(br2nl(label_decode($value)), false)) . "<div class='diffold'>".nl2br(RCView::escape(br2nl(label_decode($old_value)), false))."</div>";
						// Check if field has data
						$fieldHasData = (isset($fieldsWithData[$field_name]));
						// Add any other info that may be helpful to prevent against data loss and other issues.
						// Allow $fieldHasData to be modified for MC fields that have options deleted where the option has NO data.
						list ($metadataChangeComment, $fieldHasData, $dataJson) = Design::metadataChangeComment($metadata_old[$field_name], $metadata_new[$field_name], $key, $fieldHasData);
						// If the field has a critical issue AND has data, add to array
						if ($metadataChangeComment != "" && $fieldHasData) {
							$fieldsCriticalIssues[] = $field_name;
						}
						// Truncate text if long
						$value = "<div id='$field_name-$key' recs='".json_encode_rc($dataJson)."'>" . Design::showMoreLink($field_name, $key, $value) . "</div>";
						// Add metadata change comment (if exists)
						$value .= $metadataChangeComment;
						// Place field_name in array
						$fieldsModified[$field_name] = true;
					} else {
                        $value = nl2br(RCView::escape(br2nl(label_decode($value)), false));
                    }
				} else {
					// New field
					$value = nl2br(RCView::escape(br2nl(label_decode($value)), false));
					// Truncate text if long
					$value = Design::showMoreLink($field_name, $key, $value);
				}
				// Add to row
				$html .= "<td $bgcolor>$value</td>";
			}
			// Finish row
			$html .= "</tr>";
		}
		// Finish table
		$html .= "</table>";
		$html .= "<br>";

		// Give message if there are no differences and hide table and other things that don't need to be shown
		if ($num_metadata_changes == 0) {
			// Message to user
			$html .= "<div class='yellow' style='font-weight:bold;font-size:14px;max-width:805px;'>
                        <i class=\"fas fa-exclamation-circle\"></i> {$lang['database_mods_76']}
                    </div>";
			//CSS to hide some elements
			$html .= "<style type='text/css'>
                    #tableChanges, #metadataCompareKey, #tableChangesPretext { display: none !important; }
                    </style>";
		}

		$html .= "<br>";

		// If have fields with critical issues, then check data to see if they have data. If no data, remove as critical issue.
		$numCriticalIssues = count($fieldsCriticalIssues);

		// If the Record ID field was changed, set $record_id_field_changed as TRUE
        $metadata_new_keys = array_keys($metadata_new);
        $metadata_old_keys = array_keys($metadata_old);
		$record_id_field_changed = (array_shift($metadata_new_keys) != array_shift($metadata_old_keys));
		// If project has data, then consider this a critical issue
		if ($record_id_field_changed && $num_records > 0) {
			$numCriticalIssues++;
		}

        // e-Consent related: If using e-Consent forms, and the consent form's "anchor" Descriptive field has been moved to
        // another instrument or has been deleted, then flag it as a critical issue
        $num_econsent_form_issues = 0;
        $ec = new Econsent();
        $econsentSettings = $ec->getAllEconsents(PROJECT_ID, true);
        foreach ($econsentSettings as $thisEconsentAttr) {
            $econsentForms = Econsent::getConsentFormsByConsentId($thisEconsentAttr['consent_id'], null, true);
            if (!empty($econsentForms)) {
                // Consent forms exist, so get placeholder field
                $anchorField = $thisEconsentAttr['consent_form_location_field'];
                if (
                    // Anchor field deleted
                    (isset($Proj->metadata[$anchorField]) && !isset($Proj->metadata_temp[$anchorField])) ||
                    // Anchor field moved to another form
                    ($Proj->metadata[$anchorField]['form_name'] != '' && $Proj->metadata[$anchorField]['form_name'] != $Proj->metadata_temp[$anchorField]['form_name'])
                ) {
                    $numCriticalIssues++;
                    $num_econsent_form_issues++;
                }
            }
        }

		// Return number of changes and HTML of modifications table
		return array($num_metadata_changes, count($fieldsModified), $record_id_field_changed, $numCriticalIssues, $html, $num_econsent_form_issues);
	}

	// Create "show more" link if text in cell is too long
	public static function showMoreLink($field_name, $key, $value)
	{
		global $lang;
		$max_text_length = 90;
		// Truncate text if long
		if (strlen($value) > $max_text_length && strlen(strip_tags($value)) > $max_text_length) {
			// Temporarily remove div tag so that we don't slice it in two
			$orig_value = $value;

			$value = strip_tags(br2nl(str_replace("<div class='diffold'>", "|**RCR3P**|", $value)));
			// Find space where to break it
			$valueBreakPos = strpos($value, " ", $max_text_length);
			if ($valueBreakPos !== false) {
				// Re-add div
				$replValue = str_replace("|**RCR3P**|", "<div class='diffold'>", substr($value, 0, $valueBreakPos)) . " ...</div>";
				// Split into truncated div and hidden div
				$value = RCView::div(array('id'=>"$field_name-$key-trunc"), nl2br(str_replace("\n\n", "\n", $replValue)))
					. RCView::a(array('href'=>"javascript:;", 'class'=>'meta-diff-show-more', 'onclick'=>"metaDiffShowMore(this,'$field_name-$key');"), $lang['create_project_94'])
					. RCView::div(array('id'=>"$field_name-$key-whole", 'style'=>'display:none;'), $orig_value);
			} else {
				$value = str_replace("|**RCR3P**|", "<div class='diffold'>", $value) . "</div>";
			}
		}
		return $value;
	}

	/**
	 * CHANGE RAW METADATA VALUES INTO USER-READABLE VALUES
	 */
	public static function transformMetaVals($value, $meta_field) {
		global $lang;
		// Choose action based upon which metadata field we're on
		switch ($meta_field) {
			// Select Choices / Calculations
			case 'element_enum':
				// For fields with Choices, replace \n with line break for viewing
				$value = preg_replace("/(\s*)(\\\\n)(\s*)/", "<br>", $value);
				break;
			case 'edoc_id':
				if (is_numeric($value)) {
					$value = "doc_id=".$value;
				}
				break;
		}
		// Translater array with old/new values to translate for all metadata field types
		$translator = array('element_type'				=> array('textarea'=>'notes', 'select'=>'dropdown'),
			'element_validation_type' 	=> array('int'=>'integer', 'float'=>'number'),
			'field_phi'					=> array('1'=>'Y'),
			'field_req'					=> array('1'=>'Y', '0'=>''),
			'grid_rank'					=> array('1'=>'Y', '0'=>''),
			'video_display_inline'		=> array('1'=>$lang['design_580'], '0'=>$lang['design_581'])
		);
		// Do any direct replacing of value, if required
		if (isset($translator[$meta_field][$value])) {
			$value = $translator[$meta_field][$value];
		}
		// Make sure that all line breaks (Linux, Windows, Mac) are converted to \n for consistency
		$value = str_replace(array("\r\n", "\r"), array("\n", "\n"), $value);
		// Return transformed values
		return $value;
	}

	/**
	 * COMPARE ELEMENT_ENUM CHOICES TO DETECT NEW OR CHANGED CHOICES
	 */
	public static function compareChoices($draft_choices, $current_choices)
	{
		// Set regex to replace non-alphnumeric characters in label when comparing the two
		$regex = "/[^a-z0-9 ]/";
		// Convert choices to array format
		$draft_choices   = parseEnum($draft_choices);
		$current_choices = parseEnum($current_choices);
		// Set initial count of labels changed
		$labels_changed = array();
		// Get count of MC choices that were removed
		$codes_removed  = array_keys(array_diff_key($current_choices, $draft_choices));
		// Loop through each choice shared by both fields and check if label has changed
		foreach (array_keys(array_intersect_key($current_choices, $draft_choices)) as $code)
		{
			// Clean each label to minimize false positives (e.g., if only change case of letters or add apostrophe)
			$draft_choices[$code] = preg_replace($regex, "", strtolower(trim(strip_tags(label_decode($draft_choices[$code])))));
			$current_choices[$code] = preg_replace($regex, "", strtolower(trim(strip_tags(label_decode($current_choices[$code])))));
			// If option text was changed, count it
			if ($draft_choices[$code] != $current_choices[$code]) {
				$labels_changed[] = $code;
			}
		}
		// Return counts
		return array($codes_removed, $labels_changed);
	}

	/**
	 * RENDER METADATA CHANGE COMMENT IN RED TEXT
	 */
	public static function renderChangeComment($text) {
		return "<div class='ChangeComment'>$text</div>";
	}

	/**
	 * RENDER METADATA CHANGE COMMENT IN GREEN TEXT
	 */
	public static function renderChangeCommentOkay($text) {
		return "<div class='ChangeCommentOkay'>$text</div>";
	}

	/**
	 * ADD HELPFUL COMMENTS FOR CHANGES IN A TABLE CELL
	 */
	public static function metadataChangeComment($old_field, $new_field, $meta_field, $fieldHasData=true)
	{
		global $lang, $Proj;

		// Set array of allowable field type changes (original type => only allowable types to change to)
		$allowedFieldTypeChanges = array(
			"text" => array("textarea"),
			"textarea" => array("text"),
			"calc" => array("text", "textarea"),
			"radio" => array("text", "textarea", "select", "checkbox"),
			"select" => array("text", "textarea", "radio", "checkbox"),
			"yesno" => array("text", "textarea", "truefalse"),
			"truefalse" => array("text", "textarea", "yesno"),
			"slider" => array("text", "textarea")
		);

		// Default string value
		$msg = "";
		$dataJson = array();
		// Choose action based upon which metadata field we're on
		switch ($meta_field) {
			// Field Label
			case 'element_label':
				$oldType = $old_field[$meta_field];
				$newType = $new_field[$meta_field];
				// If field label is changing for a matrix field that has data, then give error msg.
				if ($fieldHasData && $oldType != $newType && $new_field['grid_name'] != '')
				{
					$msg .= Design::renderChangeComment($lang['database_mods_185']);
				}
				break;
			// Field Type
			case 'element_type':
				$oldType = $old_field[$meta_field];
				$newType = $new_field[$meta_field];
				// If field type is changing AND it is changing to an incompatible type, then give error msg.
				// Exclude "descriptive" fields because they have no data, so they're harmless to change into another field type.
				if ($oldType != "descriptive" && $oldType != $newType
					&& (!isset($allowedFieldTypeChanges[$oldType]) || (isset($allowedFieldTypeChanges[$oldType]) && !in_array($newType, $allowedFieldTypeChanges[$oldType]))))
				{
					if ($fieldHasData) {
						$msg .= Design::renderChangeComment($lang['database_mods_77']);
					} else {
						$msg .= Design::renderChangeCommentOkay($lang['database_mods_133']);
					}
				}
				break;
			// Select Choices / Calculations
			case 'element_enum':
				// For fields with Choices, compare choice values and codings
				if (in_array($new_field['element_type'], array("advcheckbox", "radio", "select", "checkbox", "dropdown")))
				{
					list($codes_removed, $labels_changed) = Design::compareChoices($new_field['element_enum'], $old_field['element_enum']);
					$num_codes_removed = count($codes_removed);
					$num_labels_changed = count($labels_changed);
					$compareBtnClass = 'btn-success';
					if ($num_codes_removed + $num_labels_changed > 0)
					{
						// Set defaults
						$fieldHasDataForRemovedOptions = $fieldHasDataForChangedOptions = false;
						// Build full option list (old+new)
						$oldNewChoices = array_unique(array_merge(array_keys(parseEnum($new_field['element_enum'])), array_keys(parseEnum($old_field['element_enum']))));
						// Highlight any data loss if option was RELABELED
						if ($num_labels_changed > 0) {
							// If field has data, query the data table to see if it has data for the options being deleted
							if ($fieldHasData) {
								$sql = "select value, count(*) as thiscount from ".\Records::getDataTable(PROJECT_ID)." 
                                        where project_id = ".PROJECT_ID." and field_name = '{$new_field['field_name']}'
                                        and event_id in (".prep_implode(array_keys($Proj->eventInfo)).")
                                        and value in (".prep_implode($oldNewChoices).") and value != '' group by value";
								$q = db_query($sql);
								$fieldHasDataForChangedOptions = (db_num_rows($q) > 0);
								while ($row = db_fetch_assoc($q)) {
									$dataJson[$row['value']] = $row['thiscount'];
								}
							}
							if ($fieldHasDataForChangedOptions) {
								$msg .= Design::renderChangeComment($lang['database_mods_79']);
								$compareBtnClass = 'btn-danger';
							} else {
								$msg .= Design::renderChangeCommentOkay($lang['database_mods_153']);
							}
						}
						// Highlight any data loss if option was DELETED
						if ($num_codes_removed > 0)
						{
							// If field has data, query the data table to see if it has data for the options being deleted
							if ($fieldHasData) {
								$sql = "select value, count(*) as thiscount from ".\Records::getDataTable(PROJECT_ID)." 
                                        where project_id = ".PROJECT_ID." and field_name = '{$new_field['field_name']}'
                                        and event_id in (".prep_implode(array_keys($Proj->eventInfo)).")
                                        and value in (".prep_implode($oldNewChoices).") and value != '' group by value";
								$q = db_query($sql);
								$fieldHasDataForRemovedOptions = (db_num_rows($q) > 0);
								while ($row = db_fetch_assoc($q)) {
									$dataJson[$row['value']] = $row['thiscount'];
								}
							}
							if ($fieldHasDataForRemovedOptions) {
								$msg .= Design::renderChangeComment($lang['database_mods_78']);
								$compareBtnClass = 'btn-danger';
							} else {
								$msg .= Design::renderChangeCommentOkay($lang['database_mods_133']);
							}
						}
						// Add "compare" button
						$msg .= RCView::button(array('class'=>"choiceDiffBtn btn btn-xs $compareBtnClass", 'style'=>'margin-top:2px;', 'onclick'=>"choicesCompareBtnClick('{$new_field['field_name']}');"), $lang['data_comp_tool_02']);
						// If no options with data were removed or had their label changed, then we can flag this field as not
						// having data (effectively), and thus it will NOT be considered a critical issue.
						if ($fieldHasData && !$fieldHasDataForChangedOptions && !$fieldHasDataForRemovedOptions) {
							$fieldHasData = false;
						}
					}
				}
				break;
		}
		// Return msg, if any, and $fieldHasData (would be modified if MC field's option was deleted but has no data for that option)
		return array($msg, $fieldHasData, $dataJson);
	}

	/**
	 * GET FIELDS/FORMS TO BE ADDED AND DELETED
	 */
	public static function renderFieldsAddDel()
	{
		global $lang, $Proj, $longitudinal;

		$html = "";

		$html .= "<div style='font-size:12px;'>";

		// Array for collecting new/deleted field names
		$newFields = array();
		$delFields = array();

		//List all new fields to be added
		$newFields = array_diff(array_keys($Proj->metadata_temp), array_keys($Proj->metadata));
        $newFieldsRecordCount = array();
        if (!empty($newFields)) {
            $sql = "select field_name, count(*) as thiscount from " . \Records::getDataTable(PROJECT_ID) . " 
                    where project_id = " . PROJECT_ID . " and event_id in (" . prep_implode(array_keys($Proj->eventInfo)) . ")
                    and field_name in (" . prep_implode($newFields) . ") and value != '' group by field_name";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $newFieldsRecordCount[$row['field_name']] = $row['thiscount'];
            }
        }
		$html .= "	<div style='color:green !important;padding:5px;'>
                        <b><u>{$lang['database_mods_80']}</u></b>";
		foreach ($newFields as $field) {
			$thisLabel = $field . " \"" . RCView::escape(strip_tags(nl2br($Proj->metadata_temp[$field]['element_label']))) . "\"";
			if (mb_strlen($thisLabel) > 45) $thisLabel = mb_substr($thisLabel, 0, 35) . "... " . mb_substr($thisLabel, -8);
			$html .= "	<div style='font-size:11px;max-width:500px;' class='nowrap'>&nbsp;&nbsp;&nbsp;&nbsp;&bull; $thisLabel";
			if (isset($newFieldsRecordCount[$field])) {
				$html .= " (<b>{$newFieldsRecordCount[$field]}</b> ".($longitudinal ? $lang['database_mods_176'] : $lang['database_mods_175']).")";
			}
			$html .= "</div>";
		}
		if (empty($newFields)) {
			$html .= "	<i>{$lang['database_mods_81']}</i>";
		}
		$html .= "	</div>";

		//List all new forms to be added
		$newForms = array_diff(array_keys($Proj->forms_temp), array_keys($Proj->forms));
		$html .= "	<div style='color:green !important;padding:5px;'>
                        <b><u>{$lang['database_mods_98']}</u></b>";
		foreach ($newForms as $form) {
			$html .= "	<div style='max-width:500px;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;'>&nbsp;&nbsp;&nbsp;&nbsp;&bull; " .
				$form . " &nbsp;<span style='font-size:11px;font-family:tahoma;'>\"" .
				RCView::escape($Proj->forms_temp[$form]['menu']) . "</span>\"</div>";
		}
		if (empty($newForms)) {
			$html .= "	<i>{$lang['database_mods_81']}</i>";
		}
		$html .= "	</div>";

		//List all fields to be deleted
		$delFields = array_diff(array_keys($Proj->metadata), array_keys($Proj->metadata_temp));
        $delFieldsRecordCount = array();
        if (!empty($delFields)) {
            $sql = "select field_name, count(*) as thiscount from " . \Records::getDataTable(PROJECT_ID) . " 
                    where project_id = " . PROJECT_ID . " and event_id in (" . prep_implode(array_keys($Proj->eventInfo)) . ")
                    and field_name in (" . prep_implode($delFields) . ") and value != '' group by field_name";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $delFieldsRecordCount[$row['field_name']] = $row['thiscount'];
            }
        }
		$html .= "	<div style='color:#F00000 !important;padding:5px;'>
                        <b><u>{$lang['database_mods_82']}</u></b>";
		foreach ($delFields as $field) {
			$thisLabel = $field . " \"" . RCView::escape(strip_tags(nl2br($Proj->metadata[$field]['element_label']))) . "\"";
			if (mb_strlen($thisLabel) > 45) $thisLabel = mb_substr($thisLabel, 0, 35) . "... " . mb_substr($thisLabel, -8);
			$html .= "	<div style='font-size:11px;max-width:500px;' class='nowrap'>&nbsp;&nbsp;&nbsp;&nbsp;&bull; $thisLabel";
			if (isset($delFieldsRecordCount[$field])) {
				$html .= " (<b>{$delFieldsRecordCount[$field]}</b> ".($longitudinal ? $lang['database_mods_174'] : $lang['database_mods_173']).")";
			}
			$html .= "</div>";
		}
		if (empty($delFields)) {
			$html .= "	<i>{$lang['database_mods_81']}</i>";
		}
		$html .= "	</div>";

		//List all forms to be deleted (in case renamed/deleted form in DD)
		$delForms = array_diff(array_keys($Proj->forms), array_keys($Proj->forms_temp));
		$html .= "	<div style='color:#F00000 !important;padding:5px;'>
                        <b><u>{$lang['database_mods_97']}</u></b>";
		foreach ($delForms as $form) {
			$html .= "	<div style='max-width:500px;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;'>&nbsp;&nbsp;&nbsp;&nbsp;&bull; " .
				$form . " &nbsp;<span style='font-size:11px;font-family:tahoma;'>\"" .
				RCView::escape($Proj->forms[$form]['menu']) . "</span>\"</div>";
		}
		if (empty($delForms)) {
			$html .= "	<i>{$lang['database_mods_81']}</i>";
		}
		$html .= "	</div>";

		$html .= "</div>";

		return array($newFields, $delFields, $html);

	}

	/**
	 * DISPLAY KEY FOR METADATA CHANGES
	 */
	public static function renderMetadataCompareKey() {
		global $lang;
		?>
		<div id="metadataCompareKey" style="padding-left:25px;">
			<table cellspacing="0" cellpadding="0" border="1">
				<tr><td style="padding: 5px; text-align: left; background-color: black !important; color: white !important; font-weight: bold;">
						<?php echo $lang['database_mods_83'] ?>
					</td></tr>
				<tr><td style="padding: 5px; text-align: left;">
						<?php echo $lang['database_mods_84'] ?>
					</td></tr>
				<tr><td style="padding: 5px; text-align: left; background-color: #FFFF80 !important;">
						<?php echo $lang['database_mods_85'] ?>
						<font color="#909090"><?php echo $lang['database_mods_86'] ?></font>)
					</td></tr>
				<tr><td style="padding: 5px; text-align: left; background-color: #7BED7B !important;">
						<?php echo $lang['database_mods_87'] ?>
					</td></tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Display number of fields added/deleted during Draft Mode
	 */
	public static function renderCountFieldsAddDel() {
		global $lang;

		// Number of fields added
		$sql = "select count(1) from redcap_metadata_temp where project_id = " . PROJECT_ID . " and field_name
                not in (" . pre_query("select field_name from redcap_metadata where project_id = " . PROJECT_ID) . ")";
		$fields_added = db_result(db_query($sql), 0);
		// Number of fields deleted
		$sql = "select count(1) from redcap_metadata where project_id = " . PROJECT_ID . " and field_name
                not in (" . pre_query("select field_name from redcap_metadata_temp where project_id = " . PROJECT_ID) . ")";
		$field_deleted = db_result(db_query($sql), 0);
		// Field count of new metadata
		$sql = "select count(1) from redcap_metadata_temp where project_id = " . PROJECT_ID;
		$count_new = db_result(db_query($sql), 0);
		// Field count of existing metadata
		$sql = "select count(1) from redcap_metadata where project_id = " . PROJECT_ID;
		$count_existing = db_result(db_query($sql), 0);
		// Render text
		return "<p>
                    {$lang['database_mods_88']} <b>$fields_added</b>
                    &nbsp;/&nbsp;
                    {$lang['database_mods_89']} <b>$count_new</b><br>
                    {$lang['database_mods_90']} <b>$field_deleted</b>
                    &nbsp;/&nbsp;
                    {$lang['database_mods_91']} <b>$count_existing</b>
                </p>";
	}

	// Return array of form names with e-Consent Framework enabled that have fields that were added, deleted, or modified in Draft Mode
	public static function getEconsentFormsWithModifiedFields($returnOnlyFormsWithVersionSet=false)
	{
		global $Proj;
		// If no surveys have e-Consent Framework enabled, then stop here
		if (!$Proj->hasEconsentSurveys) return array();
		// Forms modified
		$forms = Design::getFormsWithModifiedFields();
		// Loop through fields to build array of forms
		$formsEconsent = array();
		foreach ($forms as $form) {
			if (!isset($Proj->forms[$form])) continue;
			if (!isset($Proj->forms[$form]['survey_id'])) continue;
			$survey_id = $Proj->forms[$form]['survey_id'];
			$eConsentEnabled = Econsent::econsentEnabledForSurvey($survey_id);
			if (!$eConsentEnabled) continue;
			if ($returnOnlyFormsWithVersionSet && trim(Econsent::getEconsentSurveySettings($survey_id)['version'] ?? '') == '') continue;
			$formsEconsent[] = $form;
		}
		return $formsEconsent;
	}

	// Return array of form names that have fields that were added, deleted, or modified in Draft Mode
	public static function getFormsWithModifiedFields()
	{
		global $Proj;
		// Fields modified
		$fields = Design::getFieldNamesAddedDeletedModified();
		// Loop through fields to build array of forms
		$forms = array();
		foreach ($fields as $field) {
			if (isset($Proj->metadata[$field])) {
				$form = $Proj->metadata[$field]['form_name'];
			} elseif (isset($Proj->metadata_temp[$field])) {
				$form = $Proj->metadata_temp[$field]['form_name'];
			} else {
				continue;
			}
			$forms[] = $form;
		}
		return array_values(array_unique($forms));
	}

	// Return array of field names for fields added, deleted, or modified in Draft Mode
	public static function getFieldNamesAddedDeletedModified()
	{
		global $Proj;
		// Fields deleted
		$field_name_deleted = array_diff(array_keys($Proj->metadata), array_keys($Proj->metadata_temp));
		// Fields modified or added
		$field_name_changed_added = Design::getMetadataDiff(0, true);
		// Return merged array
		return array_values(array_unique(array_merge($field_name_deleted, $field_name_changed_added)));
	}

	/**
	 * Display number of fields added/deleted during Draft Mode
	 */
	public static function renderCountFieldsAddDel2()
	{
		global $Proj;

		// Count project records
		$num_records = Records::getRecordCount(PROJECT_ID);
		// Number of fields added
		$fields_added = count(array_diff(array_keys($Proj->metadata_temp), array_keys($Proj->metadata)));
		// Fields deleted
		$field_name_deleted = array_diff(array_keys($Proj->metadata), array_keys($Proj->metadata_temp));
		// Number of fields deleted
		$field_deleted = count($field_name_deleted);
		// Field count of new metadata
		$count_new = count($Proj->metadata_temp);
		// Field count of existing metadata
		$count_existing = count($Proj->metadata);
		// Query to find fields deleted that have data
		$field_with_data_deleted = 0;
		if (!empty($field_name_deleted)) {
			$sql = "select count(distinct(field_name)) from ".\Records::getDataTable(PROJECT_ID)." where project_id = ".PROJECT_ID."
                    and field_name in (".prep_implode($field_name_deleted).") 
                    and event_id in (".prep_implode(array_keys($Proj->eventInfo)).")";
			$q = db_query($sql);
			$field_with_data_deleted = db_result($q, 0);
		}

		// Return values inside array
		return array($num_records, $fields_added, $field_deleted, $field_with_data_deleted, $count_new, $count_existing);
	}

	## Validate and clean all fields used in branching logic string. Return array of variables that are not real fields.
	public static function validateBranchingCalc($string, $forceMetadataTable=false)
	{
		global $status, $Proj;

		// Use correct metadata table depending on status
		if ($forceMetadataTable) {
			$metadata_table = "redcap_metadata";
		} else {
			$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
		}

		## Clean branching logic syntax
		// Removes trailing spaces and line breaks
		$br_orig = array("\r\n", "\r", "\n");
		$br_repl = array(" ", " ", " ");
		if ($string != "")
		{
			$string = trim(str_replace($br_orig, $br_repl, $string));
			// Remove any illegal characters inside the variable name brackets
			$string = preg_replace_callback("/(\[)([^\[]*)(\])/", "branchingCleanerCallback", html_entity_decode($string, ENT_QUOTES));
		}

		## Validate all fields used in branching logic
		// Create array with fields from submitted branching logic
		$branching_fields = array_keys(getBracketedFields(cleanBranchingOrCalc($string), true, true, true));

		// Get array of special piping tags that can be used in place of events/fields
		$specialPipingTags = Piping::getSpecialTagsFormatted(false, false);
		$branching_fields_exist = array_intersect($branching_fields, $specialPipingTags);

		// Create array with branching logic fields that actually exist in metadata
		$sql = "select field_name from $metadata_table where project_id = " . PROJECT_ID . " and field_name
                in ('" . (implode("','", $branching_fields)) . "')";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$branching_fields_exist[] = $row['field_name'];
		}

		// Also add unique event names so that they are not flagged as false positives
        if ($Proj->longitudinal) {
            foreach ($Proj->getUniqueEventNames() as $this_event_name) {
				$branching_fields_exist[] = $this_event_name;
			}
		}

		// Compare real fields and submitted fields
		$error_fields = array_diff($branching_fields, $branching_fields_exist);
		return $error_fields;
	}

	// Retrieve name of first form (check metadata or metadata_temp depending on if in development)
	public static function getFirstForm()
	{
		global $status;
		$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
		$sql = "select form_name from $metadata_table where project_id = " . PROJECT_ID . " order by field_order limit 1";
		return db_result(db_query($sql), 0);
	}

	// CHECK IF FIRST EVENT CHANGED. IF SO, GIVING WARNING ABOUT THE PUBLIC SURVEY LINK CHANGING
	public static function checkFirstEventChange($arm)
	{
		global $Proj, $lang;
		if (!is_numeric($arm)) return false;
		// Get first event after making the edit (to compare with previous first event)
		$sql = "select e.event_id from redcap_events_metadata e, redcap_events_arms a
			where a.project_id = ".PROJECT_ID." and a.arm_num = $arm and a.arm_id = e.arm_id
			order by e.day_offset, e.descrip limit 1";
		$q = db_query($sql);
		$newFirstEventId = db_result($q, 0);
		$oldFirstEventId = $Proj->getFirstEventIdArm($arm);
		// Check if first event has changed position AND if a public survey exists (i.e. a survey for the first form)
		$firstEventChanged = (!empty($Proj->events[$arm]['events']) && $newFirstEventId != $oldFirstEventId && isset($Proj->forms[$Proj->firstForm]['survey_id']));
		if ($firstEventChanged)
		{
			// Give warning
			?>
            <div class="red" style="margin:10px 0;">
                <b><?php echo $lang['survey_226'] ?></b><br/>
				<?php echo $lang['survey_415'] ?> <b><?php echo $Proj->eventInfo[$oldFirstEventId]['name'] ?></b>
				<?php echo $lang['survey_228'] ?> <b><?php echo $Proj->eventInfo[$newFirstEventId]['name'] ?></b><?php echo $lang['period'] ?>
            </div>
			<?php
		}
	}

	public static function alertRecentImportStatus()
	{
		global $lang;

		if (!isset($_SESSION['imported'])) return;

		$alert = null;
		$imported = isset($_SESSION['imported']) ? $_SESSION['imported'] : null;
		$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : null;
		$csv_content = isset($_SESSION['csv_content']) ? $_SESSION['csv_content'] : null;
		$count = isset($_SESSION['count']) ? $_SESSION['count'] : 0;
		$preview = isset($_SESSION['preview']) ? $_SESSION['preview'] : "";

		unset($_SESSION['imported'], $_SESSION['count'], $_SESSION['errors'], $_SESSION['csv_content'], $_SESSION['preview']);

		if (!empty($errors))
		{
			// Error popup
			$alert = $lang['design_640'] . "<br><br> &bull; " . implode("<br> &bull; ", $errors);
			$title = $lang['global_01'];
		}
        elseif($csv_content)
		{
			// Preview popup
			switch($imported)
			{
				case 'instr_event_map':
					?><script type="text/javascript">$(function(){ $('#mapping_preview').html('<?php print js_escape($preview) ?>');simpleDialog(null,null,'importEventsInstrDialog2',650,null,'<?php print js_escape($lang['calendar_popup_01']) ?>',"$('#importEventsInstrForm2').submit();",'<?php print js_escape($lang['design_530']) ?>'); fitDialog($('#importEventsInstrDialog2')); $('.ui-dialog-buttonpane button:eq(1)',$('#importEventsInstrDialog2').parent()).css('font-weight','bold'); });</script><?php
					break;
				case 'arms':
					?><script type="text/javascript">$(function(){ $('#arm_preview').html('<?php print js_escape($preview) ?>'); simpleDialog(null,null,'importArmsDialog2',500,null,'<?php print js_escape($lang['calendar_popup_01']) ?>',"$('#importArmsForm2').submit();",'<?php print js_escape($lang['design_530']) ?>'); fitDialog($('#importArmsDialog2')); $('.ui-dialog-buttonpane button:eq(1)',$('#importArmsDialog2').parent()).css('font-weight','bold'); });</script><?php
					break;
				case 'events':
					?><script type="text/javascript">$(function(){ $('#event_preview').html('<?php print js_escape($preview) ?>');simpleDialog(null,null,'importEventsDialog2',650,null,'<?php print js_escape($lang['calendar_popup_01']) ?>',"$('#importEventsForm2').submit();",'<?php print js_escape($lang['design_530']) ?>'); fitDialog($('#importEventsDialog2')); $('.ui-dialog-buttonpane button:eq(1)',$('#importEventsDialog2').parent()).css('font-weight','bold'); });</script><?php
					break;
                case 'dags':
                    ?><script type="text/javascript">$(function(){ $('#dag_preview').html('<?php print js_escape($preview) ?>');simpleDialog(null,null,'importDAGsDialog2',650,null,'<?php print js_escape($lang['calendar_popup_01']) ?>',"$('#importDAGForm2').submit();",'<?php print js_escape($lang['design_530']) ?>'); fitDialog($('#importDAGsDialog2')); $('.ui-dialog-buttonpane button:eq(1)',$('#importDAGsDialog2').parent()).css('font-weight','bold'); });</script><?php
                    break;
                case 'userdags':
                    ?><script type="text/javascript">$(function(){ $('#user_dag_preview').html('<?php print js_escape($preview) ?>');simpleDialog(null,null,'importUserDAGsDialog2',650,null,'<?php print js_escape($lang['calendar_popup_01']) ?>',"$('#importUserDAGForm2').submit();",'<?php print js_escape($lang['design_530']) ?>'); fitDialog($('#importUserDAGsDialog2')); $('.ui-dialog-buttonpane button:eq(1)',$('#importUserDAGsDialog2').parent()).css('font-weight','bold'); });</script><?php
                    break;
                case 'dqrules':
                    ?><script type="text/javascript">$(function(){ $('#dqrule_preview').html('<?php print js_escape($preview) ?>');simpleDialog(null,null,'importDQRulesDialog2',650,null,'<?php print js_escape($lang['calendar_popup_01']) ?>',"$('#importDQRuleForm2').submit();",'<?php print js_escape($lang['design_530']) ?>'); fitDialog($('#importDQRulesDialog2')); $('.ui-dialog-buttonpane button:eq(1)',$('#importDQRulesDialog2').parent()).css('font-weight','bold'); });</script><?php
                    break;
                case 'users':
                    ?><script type="text/javascript">$(function(){ $('#user_preview').html('<?php print js_escape($preview) ?>');simpleDialog(null,null,'importUsersDialog2',1050,null,'<?php print js_escape($lang['calendar_popup_01']) ?>',"$('#importUsersForm2').submit();",'<?php print js_escape($lang['design_530']) ?>'); fitDialog($('#importUsersDialog2')); $('.ui-dialog-buttonpane button:eq(1)',$('#importUsersDialog2').parent()).css('font-weight','bold'); if($('#user_preview td.green').length > 0) { $('#notifyUsers').show(); } else { $('#notifyUsers').hide(); } });</script><?php
                    break;
                case 'emailalerts':
                    ?><script type="text/javascript">$(function(){ $('#alert_preview').html('<?php print js_escape($preview) ?>');if($('tr.highlight-row').length>0) {$('#upload-alerts-schedule-confirmation').show();};simpleDialog(null,null,'importAlertsDialog2',1050,null,'<?php print js_escape($lang['calendar_popup_01']) ?>',"$('#importAlertForm2').submit();",'<?php print js_escape($lang['design_530']) ?>'); if($('td.yellow').length + $('td.green').length == 0) { $('.ui-dialog-buttonpane button:contains("<?php print js_escape($lang['design_530']) ?>")').hide(); $('#noChangesFound').show(); $('#statusInfo').hide(); }  fitDialog($('#importAlertsDialog2')); $('.ui-dialog-buttonpane button:eq(1)',$('#importAlertsDialog2').parent()).css('font-weight','bold'); });</script><?php
                    break;
                case 'userroles':
                    ?><script type="text/javascript">$(function(){ $('#role_preview').html('<?php print js_escape($preview) ?>');simpleDialog(null,null,'importRolesDialog2',1050,null,'<?php print js_escape($lang['calendar_popup_01']) ?>',"$('#importRolesForm2').submit();",'<?php print js_escape($lang['design_530']) ?>'); if($('td.yellow').length + $('td.green').length == 0) { $('.ui-dialog-buttonpane button:contains("<?php print js_escape($lang['design_530']) ?>")').hide(); $('#noRoleChangesFound').show(); $('#rolesInstr').hide(); }  fitDialog($('#importRolesDialog2')); $('.ui-dialog-buttonpane button:eq(1)',$('#importRolesDialog2').parent()).css('font-weight','bold'); });</script><?php
                    break;
                case 'userroleMapping':
                    ?><script type="text/javascript">$(function(){ $('#user_role_preview').html('<?php print js_escape($preview) ?>');simpleDialog(null,null,'importUserRoleDialog2',650,null,'<?php print js_escape($lang['calendar_popup_01']) ?>',"$('#importUserRoleForm2').submit();",'<?php print js_escape($lang['design_530']) ?>'); if($('td.yellow').length + $('td.green').length == 0) { $('.ui-dialog-buttonpane button:contains("<?php print js_escape($lang['design_530']) ?>")').hide(); $('#noMappingChangesFound').show(); $('#userRolesInstr').hide(); }  fitDialog($('#importUserRoleDialog2')); $('.ui-dialog-buttonpane button:eq(1)',$('#importUserRoleDialog2').parent()).css('font-weight','bold'); });</script><?php
                    break;
                case 'custom_queries':
                    ?><script type="text/javascript">$(function(){ $('#custom_queries_preview').html('<?php print js_escape($preview) ?>');simpleDialog(null,null,'importQueryDialog2',650,null,'<?php print js_escape($lang['calendar_popup_01']) ?>',"$('#importQueryForm2').submit();",'<?php print js_escape($lang['design_530']) ?>'); if($('td.yellow').length + $('td.green').length == 0) { $('.ui-dialog-buttonpane button:contains("<?php print js_escape($lang['design_530']) ?>")').hide(); $('#noMappingChangesFound').show(); $('#userRolesInstr').hide(); }  fitDialog($('#importUserRoleDialog2')); $('.ui-dialog-buttonpane button:eq(1)',$('#importQueryDialog2').parent()).css('font-weight','bold'); });</script><?php
                    break;
			}
			return;
		}
        elseif($imported)
		{
			// Confirmation popup of success
			$title = $lang['global_79'];
			switch($imported)
			{
				case 'instr_event_map':
					$alert = "$count {$lang['api_94']}";
					break;
				case 'arms':
					$alert = "$count {$lang['api_95']}";
					break;
				case 'events':
					$alert = "$count {$lang['api_96']}";
					break;
                case 'dags':
                    $alert = "$count {$lang['data_access_groups_ajax_50']}";
                    break;
                case 'userdags':
                    $alert = "$count {$lang['data_access_groups_ajax_52']}";
                    break;
                case 'dqrules':
                    $alert = "$count {$lang['dataqueries_340']}";
                    break;
                case 'users':
                    $alert = "$count {$lang['rights_381']}";
                    break;
                case 'emailalerts':
                    $alert = "$count {$lang['alerts_284']}";
                    break;
                case 'userroles':
                    $alert = "$count {$lang['rights_414']}";
                    break;
                case 'userroleMapping':
                    $alert = "$count {$lang['rights_419']}";
                    break;
                case 'custom_queries':
                    $alert = "$count {$lang['control_center_4925']}";
                    break;
			}
		}

		if($alert)
		{
			?><script type="text/javascript">$(function(){simpleDialog('<?php echo js_escape($alert) ?>','<?php echo js_escape($title) ?>');});</script><?php
		}
	}

	/**
	 * SPECIAL FUNCTION EXPLANATION
	 * Output general instructions and documentation on how to utilize special functions.
	 */
	public static function renderSpecialFunctionInstructions()
	{
		global $lang, $isAjax;
		// Place all HTML into $h
		$h = '';
		$h .= RCView::div(array('class' => 'clearfix'),
				RCView::div(array('style' => 'font-size:18px;font-weight:bold;float:left;padding:0 0 10px;'),
					"<i class='fas fa-square-root-alt'></i> " .
					RCView::span(array('style' => 'vertical-align:middle;'), $lang['design_839'])
				) .
				RCView::div(array('style' => 'text-align:right;float:right;'),
					($isAjax
						? RCView::a(array('href' => PAGE_FULL, 'target' => '_blank', 'style' => 'text-decoration:underline;'),
							$lang['survey_977']
						)
						: RCView::img(array('src' => 'redcap-logo.png'))
					)
				)
			) .
            // Instructions
			RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:15px;font-weight:bold;'), $lang['design_840']) .
			RCView::div('', $lang['design_841'] . " ".$lang['design_896']) .
			RCView::div(array('class'=>'my-3', 'style'=>'color:#A00000;'), $lang['global_174']) .
            // Examples
			RCView::div(array('class'=>'my-3'),
				RCView::h6(array('class'=>'fs15 boldish'), $lang['design_897']) .
				RCView::ol(array('class'=>''),
					RCView::li(array('class'=>'my-2'),
						$lang['design_899'] . RCView::br() .
						RCView::code(array('class' => 'fs13'), "datediff([date1], 'today', 'd')")
					) .
					RCView::li(array('class' => 'my-2'),
						$lang['design_898'] . RCView::br() .
						RCView::code(array('class' => 'fs13'), "rounddown(datediff([date_of_birth], 'today', 'y'))")
					) .
					RCView::li(array('class' => 'my-2'),
						$lang['design_900'] . RCView::br() .
						RCView::code(array('class' => 'fs13'), "round(([weight]*10000)/(([height])^(2)), 1)")
					) .
					RCView::li(array('class' => 'my-2'),
						$lang['design_901'] . RCView::br() .
						RCView::code(array('class' => 'fs13'), "round(([weight]/(([height])^(2))*703), 1)")
					) .
					RCView::li(array('class' => 'my-2'),
						$lang['design_902'] . RCView::br() .
						RCView::code(array('class' => 'fs13'), "mid([record-name], find('-', [record-name])+1, length([record-name])-find('-', [record-name])+1)")
					) .
					RCView::li(array('class' => 'my-2'),
						$lang['design_903'] . RCView::br() .
						RCView::code(array('class' => 'fs13'), "lower( concat( trim([first_name]), '_', trim([last_name]) ) )")
					) .
					RCView::li(array('class' => 'my-2'),
						$lang['design_904'] . RCView::br() .
						RCView::code(array('class' => 'fs13'), "right(concat('00', [my_integer]), 3)")
					)
				)
			) .
			// Table of functions
			RCView::table(array('id' => 'special-functions-table', 'style' => 'margin-top:20px;width:100%;border-bottom:1px solid #ccc;line-height:13px;'),
				RCView::tr('',
					RCView::td(array('class' => 'font-weight-bold fs15 p-3 bg-light'), $lang['design_845']) .
					RCView::td(array('class' => 'font-weight-bold fs15 p-3 bg-light'), $lang['design_846']) .
					RCView::td(array('class' => 'font-weight-bold fs15 p-3 bg-light'), $lang['design_847'])
				) .
				// FUNCTIONS
				RCView::tr('',
					RCView::td(array(), "if (" . $lang['design_842'] . ")") .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_843']) .
					RCView::td(array(), $lang['design_844'])
				) .
				RCView::tr('',
					RCView::td(array(), 'datediff ([date1], [date2], "units", returnSignedValue)') .
					RCView::td(array('class' => 'font-weight-bold'), "Datediff") .
					RCView::td(array(), $lang['design_848'])
				) .
				RCView::tr('',
					RCView::td(array(), 'isblankormissingcode (value)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_874']) .
					RCView::td(array(), $lang['design_875'])
				) .
				RCView::tr('',
					RCView::td(array(), 'isblanknotmissingcode (value)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_1413']) .
					RCView::td(array(), $lang['design_1414'])
				) .
				RCView::tr('',
					RCView::td(array(), 'ismissingcode (value)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_1411']) .
					RCView::td(array(), $lang['design_1412'])
				) .
				RCView::tr('',
					RCView::td(array(), 'hasmissingcode (value, missingDataCodes)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_1415']) .
					RCView::td(array(), $lang['design_1416'])
				) .
				RCView::tr('',
					RCView::td(array('colspan' => 3, 'class' => 'font-weight-bold p-2 text-light bg-dark'), $lang['design_877'])
				) .
				RCView::tr('',
					RCView::td(array(), 'round (number, decimal places)') .
					RCView::td(array('class' => 'font-weight-bold'), "Round") .
					RCView::td(array(), $lang['design_849'])
				) .
				RCView::tr('',
					RCView::td(array(), 'roundup (number,decimal places)') .
					RCView::td(array('class' => 'font-weight-bold'), "Round Up") .
					RCView::td(array(), $lang['design_850'])
				) .
				RCView::tr('',
					RCView::td(array(), 'rounddown (number,decimal places)') .
					RCView::td(array('class' => 'font-weight-bold'), "Round Down") .
					RCView::td(array(), $lang['design_851'])
				) .
				RCView::tr('',
					RCView::td(array(), 'sqrt (number)') .
					RCView::td(array('class' => 'font-weight-bold'), "Square Root") .
					RCView::td(array(), "E.g. sqrt([height]) or sqrt(([value1]*34)/98.3)")
				) .
				RCView::tr('',
					RCView::td(array(), '(number)^(exponent)') .
					RCView::td(array('class' => 'font-weight-bold'), "Exponents") .
					RCView::td(array(), $lang['design_852'])
				) .
				RCView::tr('',
					RCView::td(array(), 'abs (number)') .
					RCView::td(array('class' => 'font-weight-bold'), "Absolute Value") .
					RCView::td(array(), $lang['design_853'])
				) .
				RCView::tr('',
					RCView::td(array(), 'exponential (number)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_1079']) .
					RCView::td(array(), $lang['design_1078'])
				) .
				RCView::tr('',
					RCView::td(array(), 'min (number,number,...)') .
					RCView::td(array('class' => 'font-weight-bold'), "Minimum") .
					RCView::td(array(), $lang['design_854'])
				) .
				RCView::tr('',
					RCView::td(array(), 'max (number,number,...)') .
					RCView::td(array('class' => 'font-weight-bold'), "Maximum") .
					RCView::td(array(), $lang['design_855'])
				) .
				RCView::tr('',
					RCView::td(array(), 'mean (number,number,...)') .
					RCView::td(array('class' => 'font-weight-bold'), "Mean") .
					RCView::td(array(), $lang['design_856'])
				) .
				RCView::tr('',
					RCView::td(array(), 'median (number,number,...)') .
					RCView::td(array('class' => 'font-weight-bold'), "Median") .
					RCView::td(array(), $lang['design_857'])
				) .
				RCView::tr('',
					RCView::td(array(), 'mod (dividend,divisor)') .
					RCView::tt("design_1076", "td", ["class" => "font-weight-bold"]) .
					RCView::tt("design_1077", "td", [])
				) .
				RCView::tr('',
					RCView::td(array(), 'sum (number,number,...)') .
					RCView::td(array('class' => 'font-weight-bold'), "Sum") .
					RCView::td(array(), $lang['design_858'])
				) .
				RCView::tr('',
					RCView::td(array(), 'stdev (number,number,...)') .
					RCView::td(array('class' => 'font-weight-bold'), "Standard Deviation") .
					RCView::td(array(), $lang['design_859'])
				) .
				RCView::tr('',
					RCView::td(array(), 'log (number, base)') .
					RCView::td(array('class' => 'font-weight-bold'), "Logarithm") .
					RCView::td(array(), $lang['design_860'])
				) .
				RCView::tr('',
					RCView::td(array(), 'isnumber (value)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_861']) .
					RCView::td(array(), $lang['design_862'])
				) .
				RCView::tr('',
					RCView::td(array(), 'isinteger (value)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_863']) .
					RCView::td(array(), $lang['design_864'])
				) .
                // Date/datetime functions
				RCView::tr('',
					RCView::td(array('colspan' => 3, 'class' => 'font-weight-bold p-2 text-light bg-dark'),
                        $lang['design_1101']
                    )
				) .
				RCView::tr('',
					RCView::td(array(), 'year (date value)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_1095']) .
					RCView::td(array(), $lang['design_1096']. " " . $lang['design_1114'])
				) .
				RCView::tr('',
					RCView::td(array(), 'month (date value)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_1097']) .
					RCView::td(array(), $lang['design_1098']. " " . $lang['design_1114'])
				) .
				RCView::tr('',
					RCView::td(array(), 'day (date value)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_1099']) .
					RCView::td(array(), $lang['design_1100']. " " . $lang['design_1114'])
				) .
                // Test string functions
				RCView::tr('',
					RCView::td(array('colspan' => 3, 'class' => 'font-weight-bold p-2 text-light bg-dark'),
                        $lang['design_876'].
                        RCView::div(array('class' => 'mt-3 font-weight-normal fs11'),
                            $lang['design_1048']
                        )
                    )
				) .
				RCView::tr('',
					RCView::td(array(), 'contains (haystack, needle)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_865']) .
					RCView::td(array(), $lang['design_866'])
				) .
				RCView::tr('',
					RCView::td(array(), 'not_contain (haystack, needle)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_867']) .
					RCView::td(array(), $lang['design_868'])
				) .
				RCView::tr('',
					RCView::td(array(), 'starts_with (haystack, needle)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_870']) .
					RCView::td(array(), $lang['design_871'])
				) .
				RCView::tr('',
					RCView::td(array(), 'ends_with (haystack, needle)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_872']) .
					RCView::td(array(), $lang['design_873'])
				) .
				RCView::tr('',
					RCView::td(array(), 'left (text, number of characters)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_878']) .
					RCView::td(array(), $lang['design_879'])
				) .
				RCView::tr('',
					RCView::td(array(), 'right (text, number of characters)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_880']) .
					RCView::td(array(), $lang['design_881'])
				) .
				RCView::tr('',
					RCView::td(array(), 'length (text)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_882']) .
					RCView::td(array(), $lang['design_883'])
				) .
				RCView::tr('',
					RCView::td(array(), 'find (needle, haystack)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_884']) .
					RCView::td(array(), $lang['design_885'])
				) .
				RCView::tr('',
					RCView::td(array(), 'replace_text (haystack, search, replace)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_1074']) .
					RCView::td(array(), $lang['design_1075'])
				) .
				RCView::tr('',
					RCView::td(array(), 'mid (text, start position, number of characters)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_886']) .
					RCView::td(array(), $lang['design_887'])
				) .
				RCView::tr('',
					RCView::td(array(), 'concat (text, text,...)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_888']) .
					RCView::td(array(), $lang['design_889'])
				) .
				RCView::tr('',
					RCView::td(array(), 'concat_ws (separator, text, text, ...)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_1072']) .
					RCView::td(array(), $lang['design_1073'] . " " . $lang['design_1115'])
				) .
				RCView::tr('',
					RCView::td(array(), 'upper (text)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_890']) .
					RCView::td(array(), $lang['design_891'])
				) .
				RCView::tr('',
					RCView::td(array(), 'lower (text)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_892']) .
					RCView::td(array(), $lang['design_893'])
				) .
				RCView::tr('',
					RCView::td(array(), 'trim (text)') .
					RCView::td(array('class' => 'font-weight-bold'), $lang['design_894']) .
					RCView::td(array(), $lang['design_895'])
				)
			);
		// Return HTML
		return $h;
	}

    // Return boolean if a given field is designed on multiple events or exists on a repeating instrument or event
    public static function fieldUsedInMultiplePlaces()
    {
        $field = $_POST['field']??"";
        $Proj = new Project(PROJECT_ID);
        // If the field doesn't exist, return false
        if ($field == '' || !isset($Proj->metadata[$field])) return false;
        // Field's form
        $form = $Proj->metadata[$field]['form_name'];
        // If the field exists on a repeating instrument or repeating event, return true
        if ($Proj->isRepeatingFormAnyEvent($form)) return true;
        // If the field's form is designated for more than one event, return true
        if ($Proj->longitudinal) {
            $numEvents = 0;
            foreach ($Proj->eventsForms as $forms) {
                if (in_array($form, $forms)) $numEvents++;
                if ($numEvents > 1) return true;
            }
        }
        // If we got this far, return false
        return false;
    }

	#region Draft Preview

	/**
	 * Indicates whether Draft Preview mode is active for the user in the current project
	 * @param string|int|null $project_id 
	 * @return bool 
	 */
	public static function isDraftPreview($project_id = null) {
		if ($project_id == null) {
			$project_id = defined("PROJECT_ID") ? PROJECT_ID : null;
		}
		if ($project_id) {
			$Proj = new Project($project_id);
			if (intval($Proj->project["status"]) > 0 && $Proj->project["draft_mode"] != '1') return false;
			$draft_id = self::getDraftPreviewSessionKey($project_id);
			return isset($_SESSION[$draft_id]) && is_array($_SESSION[$draft_id]);
		}
		return false;
	}

	/**
	 * Enable Draft Preview mode for the user in the current (or given) project
	 * @param string|int|null $project_id 
	 * @return void 
	 */
	public static function enableDraftPreview($project_id = null) {
		if ($project_id == null) {
			$project_id = defined("PROJECT_ID") ? PROJECT_ID : null;
		}
		if ($project_id) {
			$draft_id = self::getDraftPreviewSessionKey($project_id);
			if (!isset($_SESSION[$draft_id])) {
				$_SESSION[$draft_id] = [
					"records" => [],
					"uploaded_files" => [],
					"ts" => NOW
				];
			}
			// Logging
			Logging::logEvent("", "", "MANAGE", null, "Note: Exiting draft preview might not be logged or logged delayed.", "Entered Draft Preview mode");
		}
	}


	/**
	 * Indicates whether Draft Preview mode can be used on this REDCap Instance
	 * @return bool 
	 */
	public static function canUseDraftPreview() {
		// PHP session serialization handler must be "php" or "php_binary" as otherwise we cannot 
		// deserialize it manually for deletion of file uploads
		return in_array(ini_get("session.serialize_handler"), ["php", "php_binary"]);
	}

	/**
	 * Cancel Draft Preview mode for the user in the current (or given) project
	 * @param string|int|null $project_id 
	 * @return void 
	 */
	public static function cancelDraftPreview($project_id = null) {
		if ($project_id == null) {
			$project_id = defined("PROJECT_ID") ? PROJECT_ID : null;
		}
		if ($project_id) {
			// Clean up files
			foreach ($_SESSION[self::getDraftPreviewSessionKey($project_id)]["uploaded_files"] ?? [] as $doc_id => $_) {
				self::removeDraftPreviewStoredFile($project_id, $doc_id, true);
			}
			$ts = $_SESSION[self::getDraftPreviewSessionKey($project_id)]["ts"];
			unset($_SESSION[self::getDraftPreviewSessionKey($project_id)]);
			// Logging (only when there is a timestamp)
			if ($ts != "") {
				Logging::logEvent("", "", "MANAGE", null, "", "Exited Draft Preview mode (entered: {$ts})");
			}
		}
	}

	/**
	 * Ensures that record data for the record viewed in DRAFT PREVIEW mode is available
	 * @param string|int $project_id 
	 * @param string $record 
	 * @return Array|false
	 */
	private static function checkDraftPreviewRecordExists($project_id, $record) {
		if (!self::isDraftPreview($project_id)) return false;
		$draft_id = self::getDraftPreviewSessionKey($project_id);
		if (!array_key_exists($record, $_SESSION[$draft_id]["records"])) {
			// Get the current data for the record
			$_SESSION[$draft_id]["records"][$record] = REDCap::getData($project_id, "array", $record);
		}
		$data = $_SESSION[$draft_id]["records"][$record]; // Using var for easy inspection during development
		return $data;
	}

	/**
	 * Indicates whether the record data for the record viewed in DRAFT PREVIEW mode is available
	 * @param string|int $project_id 
	 * @param string $record 
	 * @return bool 
	 */
	private static function hasDraftPreviewData($project_id, $record) {
		if (!self::isDraftPreview($project_id)) return false;
		$draft_id = self::getDraftPreviewSessionKey($project_id);
		return array_key_exists($record, $_SESSION[$draft_id]["records"]);
	}

	/**
	 * Updates the grid form status in DRAFT PREVIEW mode (Record Home Page)
	 * @param string|int $project_id 
	 * @param string $record 
	 * @param Array &$grid_form_status 
	 * @return void 
	 */
	public static function updateGridFormStatus($project_id, $record, &$grid_form_status) {
		if (!self::hasDraftPreviewData($project_id, $record)) return;
		// Update the grid form status
		foreach ($grid_form_status as $event_id => $forms) {
			foreach ($forms as $form => $form_instances) {
                if (empty($form_instances)) $form_instances[1] = "";
				foreach ($form_instances as $instance => $orig_status) {
					$status = self::updateFormStatus($project_id, $record, $event_id, $form, $instance, false, $orig_status);
					$grid_form_status[$event_id][$form][$instance] = $status;
				}
			}
		}
	}

	/**
	 * Updates the form status in DRAFT PREVIEW mode (Record Status Dashboard)
	 * @param string|int $project_id 
	 * @param Array &$formStatusValues 
	 * @return void 
	 */
	public static function updateFormStatusForRecordStatusDashboard($project_id, &$formStatusValues) {
		if (!self::isDraftPreview($project_id)) return;
		$draft_id = self::getDraftPreviewSessionKey($project_id);
		$records = array_keys($_SESSION[$draft_id]["records"]);
		foreach ($records as $record) {
			if (array_key_exists($record, $formStatusValues)) {
				self::updateGridFormStatus($project_id, $record, $formStatusValues[$record]);
			}
		}
	}

	/**
	 * Updates the form status in DRAFT PREVIEW mode (Data Collection menu)
	 * @param string|int $project_id 
	 * @param string $record 
	 * @param string|int $event_id 
	 * @param string $form 
	 * @param int $instance 
	 * @param boolean $is_survey 
	 * @param string $form_status_instance ("", "0", "1", "2" for data entry; "2" or original for survey)
	 * @param boolean $consider_all_instances (default: false) - relevant when stacked icons are displayed
	 * @return string 
	 */
	public static function updateFormStatus($project_id, $record, $event_id, $form, $instance, $is_survey, $form_status_instance, $consider_all_instances = false) {
		// Surveys not currently supported yet
		if ($is_survey) return $form_status_instance;
		if (!self::hasDraftPreviewData($project_id, $record)) return $form_status_instance;
		$Proj = new Project($project_id);
		$is_form_repeating = $Proj->isRepeatingForm($event_id, $form);
		$is_event_repeating = $Proj->isRepeatingEvent($event_id);
		$data = self::checkDraftPreviewRecordExists($project_id, $record);
		$form_complete = "";
		if ($is_form_repeating) {
			if ($consider_all_instances) {
				$form_status_all_instances = [];
				foreach ($data[$record]["repeat_instances"][$event_id][$form] as $instance => $fields) {
					$form_status_all_instances[] = $fields[$form."_complete"];
				}
				$form_status_all_instances = array_unique($form_status_all_instances);
				if (count($form_status_all_instances) == 1) {
					$form_complete = $form_status_all_instances[0];
				}
				else {
					$form_complete = "";
				}
			}
			else {
				$form_complete = $data[$record]["repeat_instances"][$event_id][$form][$instance][$form."_complete"] ?? "";
			}
		}
		else if ($is_event_repeating) {
			$form_complete = $data[$record]["repeat_instances"][$event_id][""][$instance][$form."_complete"] ?? "";
		}
		else {
			$form_complete = $data[$record][$event_id][$form."_complete"] ?? "";
		}
		if ($form_status_instance == "" && $form_complete == "0") {
			return $form_status_instance;
		}
		return $form_complete;
	}

	/**
	 * Adds a file/signature doc_id to the list of stored files in DRAFT PREVIEW mode
	 * @param string|int $project_id 
	 * @param string|int $doc_id 
	 * @return void 
	 */
	public static function addDraftPreviewStoredFile($project_id, $doc_id) {
		if (!self::isDraftPreview($project_id)) return false;
		$draft_id = self::getDraftPreviewSessionKey($project_id);
		$_SESSION[$draft_id]["uploaded_files"][intval($doc_id)] = true;
	}

	/**
	 * Checks if a file/signature doc_id is in the list of stored files in DRAFT PREVIEW mode
	 * @param string|int $project_id 
	 * @param string|int $doc_id 
	 * @return bool 
	 */
	public static function isDraftPreviewStoredFile($project_id, $doc_id) {
		if (!self::isDraftPreview($project_id)) return false;
		$draft_id = self::getDraftPreviewSessionKey($project_id);
		$files = $_SESSION[$draft_id]["uploaded_files"] ?? [];
		return $files[intval($doc_id)] ?? false;
	}

	/**
	 * Removes a file/signature doc_id from the list of stored files in DRAFT PREVIEW mode
	 * @param string|int $project_id 
	 * @param string|int $doc_id 
	 * @param bool $from_db When true, also remove the file from the database
	 * @return void 
	 */
	public static function removeDraftPreviewStoredFile($project_id, $doc_id, $from_db = false) {
		if (!self::isDraftPreview($project_id)) return false;
		$draft_id = self::getDraftPreviewSessionKey($project_id);
		$doc_id = intval($doc_id);
		$files = &$_SESSION[$draft_id]["uploaded_files"] ?? [];
		if ($files[$doc_id] ?? false) {
			unset($files[$doc_id]);
			if ($from_db) {
				$sql = "UPDATE redcap_edocs_metadata SET delete_date = ? WHERE doc_id = ?";
				$q = db_query($sql, [ NOW, $doc_id ]);
			}
		}
	}

	/**
	 * Save record for Draft Preview
	 * @param string|int $project_id 
	 * @param string $record 
	 * @param Array $get 
	 * @param Array $post 
	 * @return void 
	 */
	public static function saveRecordForDraftPreview($project_id, $record, $get, $post) {
		if (!self::isDraftPreview($project_id)) return;
		$draft_id = self::getDraftPreviewSessionKey($project_id);
		self::checkDraftPreviewRecordExists($project_id, $record);
		// Get context
		$form = $get["page"];
		$event_id = $get["event_id"];
		$instance = $get["instance"] ?? 1;
		$Proj = new Project($project_id);
		$Proj_metadata = $Proj->getMetadata();
		$form_fields = $Proj->getFormFields($form);
		$is_form_repeating = $Proj->isRepeatingForm($event_id, $form);
		$is_event_repeating = $Proj->isRepeatingEvent($event_id);
		// Update the data
		$data = &$_SESSION[$draft_id]["records"][$record];
		$updated_fields = [];
		foreach ($form_fields as $field) {
			$field_type = $Proj_metadata[$field]['element_type'];
			$field_validation = $Proj_metadata[$field]['element_validation_type'];
			// Files and Signatures
			if ($field_type == "file") {
				$val = intval($post[$field]);
				if ($val > 0) {
					$_SESSION[$draft_id]["uploaded_files"][$val] = true;
				}
				else {
					// A file has potentially been deleted - get the previous value
					if ($is_form_repeating) {
						$prev_doc_id = intval($data[$record]["repeat_instances"][$event_id][$form][$instance][$field] ?? 0);
					}
					else if ($is_event_repeating) {
						$prev_doc_id = intval($data[$record]["repeat_instances"][$event_id][""][$instance][$field] ?? 0);
					}
					else {
						$prev_doc_id = intval($data[$record][$event_id][$field] ?? 0);
					}
					if (self::isDraftPreviewStoredFile($project_id, $prev_doc_id)) {
						self::removeDraftPreviewStoredFile($project_id, $prev_doc_id, true);
					}
				}
			}
			if ($field_type == "checkbox") {
				// Checkbox fields need special handling
				$choices = parseEnum($Proj->metadata_temp[$field]['element_enum']);
				foreach ($choices as $choice => $_) {
					$cb_field = "__chk__".$field."_RC_".$choice;
					if (isset($post[$cb_field])) {
						$updated_fields[$cb_field] = true;
						$val = $post[$cb_field] == $choice ? "1" : "0";
						if ($is_form_repeating) {
							$data[$record]["repeat_instances"][$event_id][$form][$instance][$field][$choice] = $val;
						}
						else if ($is_event_repeating) {
							$data[$record]["repeat_instances"][$event_id][""][$instance][$field][$choice] = $val;
						}
						else {
							$data[$record][$event_id][$field][$choice] = $val;
						}
					}
				}
			}
			elseif (isset($post[$field])) {
				$updated_fields[$field] = true;
				$val = $post[$field];
				// Fix date/time fields
				if ($field_type == "text" && strpos($field_validation, "date") === 0) {
					switch ($field_validation) {
						case "date_dmy":
						case "datetime_dmy":
							$val = DateTimeRC::date_dmy2ymd($val);
							break;
						case "date_mdy":
						case "datetime_mdy":
							$val = DateTimeRC::date_mdy2ymd($val);
							break;
					}
				}
				if ($is_form_repeating) {
					$data[$record]["repeat_instances"][$event_id][$form][$instance][$field] = $val;
				}
				else if ($is_event_repeating) {
					$data[$record]["repeat_instances"][$event_id][""][$instance][$field] = $val;
				}
				else {
					$data[$record][$event_id][$field] = $val;
				}
			}
		}
		$calc_fields = Calculate::getCalcFieldsByTriggerField(array_keys($updated_fields), true, $Proj);
		Calculate::saveCalcFields([$record], $calc_fields);
	}

	/**
	 * Gets the data of the current record viewed in DRAFT PREVIEW mode
	 * @param string|int $project_id 
	 * @param string $record 
	 * @return Array
	 */
	public static function getRecordDataForDraftPreview($project_id, $record) {
		return self::checkDraftPreviewRecordExists($project_id, $record);
	}

	/**
	 * Stores data for the current record viewed in DRAFT PREVIEW mode
	 * @param string|int $project_id 
	 * @param string $record 
	 * @param Array $data Array-format REDCap data structure
	 * @return void 
	 */
	public static function setRecordDataForDraftPreview($project_id, $record, $data) {
		$_SESSION[self::getDraftPreviewSessionKey($project_id)]["records"][$record] = $data;
	}

	/**
	 * Gets the data for the renderForm method when DRAFT PREVIEW is enabled
	 * @param string|int $project_id 
	 * @param string $record 
	 * @param Array $elements 
	 * @param Array $get 
	 * @return array 
	 */
	public static function getRenderFormDataForDraftPreview($project_id, $record, $elements, $get) {
		$form = $get["page"];
		$event_id = $get["event_id"];
		$instance = $get["instance"] ?? 1;
		$Proj = new Project($project_id);
		$is_form_repeating = $Proj->isRepeatingForm($event_id, $form);
		$is_event_repeating = $Proj->isRepeatingEvent($event_id);
		$data = self::checkDraftPreviewRecordExists($project_id, $record);
		$element_data = [];
		foreach ($elements as $element) {
			if (isset($element["field"])) {
				$field = $element["field"];
				if ($field == $Proj->table_pk) {
					$element_data[$field] = $record;
					continue;
				}
				if (substr($field, 0, 1) == "_") continue;
                $repeat_form = $is_form_repeating ? $form : "";
                $this_data = ($is_form_repeating || $is_event_repeating) ? $data[$record]["repeat_instances"][$event_id][$repeat_form][$instance][$field] : $data[$record][$event_id][$field];
				if ($Proj->isCheckbox($field)) {
                    // Translate checkbox data into proper array format for DataEntry::render_checkboxes()
                    $this_checkbox_data = [];
                    foreach ($this_data as $code=>$checked) {
                        if ($checked == '1') $this_checkbox_data[] = $code."";
                    }
                    $element_data[$field] = $this_checkbox_data;
				}
				else {
					$element_data[$field] = $this_data;
				}
			}
		}
		return $element_data;
	}

	/**
	 * Copies DRAFT PREVIEW record data into the given record data array
	 * @param string|int $project_id 
	 * @param Array $record_data The record data array
	 * @param Array $expected_fields Expected data
	 */
	public static function injectDraftPreviewRecordData($project_id, &$record_data, $expected_fields) {
		$records = $_SESSION[self::getDraftPreviewSessionKey($project_id)]["records"] ?? [];
		foreach ($records as $record => $data) {
			// Is the record in the data array? If not, then skip
			if (!isset($record_data[$record])) continue;
			// Update the previewed record's data
			foreach ($data[$record] as $event_id => $event_data) {
				if ($event_id == "repeat_instances") {
					foreach ($event_data as $repeat_event_id=> $repeat_event_data) {
						if (!isset($expected_fields[$repeat_event_id])) continue;
						foreach ($repeat_event_data as $form => $form_data) {
							foreach ($form_data as $instance => $fields) {
								foreach ($fields as $field => $value) {
									if (isset($expected_fields[$repeat_event_id][$field])) {
										$record_data[$record]["repeat_instances"][$repeat_event_id][$form][$instance][$field] = $value;
									}
								}
							}
						}
						
					}
				}
				else {
					if (!isset($expected_fields[$event_id])) continue;
					foreach ($event_data as $field => $value) {
						if (isset($expected_fields[$event_id][$field])) {
							$record_data[$record][$event_id][$field] = $value;
						}
					}
				}
				
			}
		}
	}

	/**
	 * Get Draft Preview session key
	 * @param string|int $project_id 
	 * @return string 
	 */
	private static function getDraftPreviewSessionKey($project_id) {
		return $project_id."-draft-preview";
	}

	#endregion

	public static function changeFormNameInBackend($project_id, $form_name, $new_form_name) {
		$project_id = intval($project_id);
		if ($project_id < 1) return false; // Throw?

		$Proj = new \Project($project_id);
		if (!$Proj->canEditFormName($form_name)) return false; // Throw?
		$status = (int) $Proj->project['status'];

		// Wrap database queries in a transaction.
		db_query("SET AUTOCOMMIT=0");
		db_query("BEGIN");
		
		// Update redcap_metadata
		$metadata_table = $Proj->project['draft_mode'] == 1 ? "redcap_metadata_temp" : "redcap_metadata";
		$sql = "UPDATE $metadata_table SET form_name = ? WHERE form_name = ? AND project_id = ?";
		db_query($sql, [$new_form_name, $form_name, $project_id]);
		// Change variable name of the form's Form Status field
		$sql = "UPDATE $metadata_table SET field_name = ? WHERE field_name = ? AND project_id = ?";
		db_query($sql, ["{$new_form_name}_complete", "{$form_name}_complete", $project_id]);
		// Change actual data table field_names to reflect the changed Form Status field
		$data_table = \Records::getDataTable($project_id);
		$sql = "UPDATE $data_table SET field_name = ? WHERE field_name = ? AND project_id = ?";
		db_query($sql, ["{$new_form_name}_complete", "{$form_name}_complete", $project_id]);

		// Further updates ONLY in development projects
		if ($status < 1) {
			$db_form_name = db_escape($form_name);
			$db_new_form_name = db_escape($new_form_name);
			// Get event_ids
			$eventIds = pre_query(
				"SELECT m.event_id FROM redcap_events_arms a, redcap_events_metadata m 
				 WHERE a.arm_id = m.arm_id AND a.project_id = $project_id"
			);
			// Change back-end form name in event_forms table
			$sql = "UPDATE redcap_events_forms SET form_name = ? WHERE form_name = ? AND event_id IN ($eventIds)";
			db_query($sql, [$new_form_name, $form_name]);
			// Change back-end form name in redcap_events_repeat table
			$sql = "UPDATE redcap_events_repeat SET form_name = ? WHERE form_name = ? AND event_id in ($eventIds)";
			db_query($sql, [$new_form_name, $form_name]);
			// Change back-end form name in user_rights table
			$sql = "UPDATE redcap_user_rights SET
					data_entry = replace(data_entry, '[$db_form_name,', '[$db_new_form_name,'),
					data_export_instruments = replace(data_export_instruments, '[$db_form_name,', '[$db_new_form_name,')
					WHERE project_id = $project_id";
			db_query($sql);
			$sql = "UPDATE redcap_user_roles SET
					data_entry = replace(data_entry, '[$db_form_name,', '[$db_new_form_name,'),
					data_export_instruments = replace(data_export_instruments, '[$db_form_name,', '[$db_new_form_name,')
					WHERE project_id = $project_id";
			db_query($sql);
			// Change back-end form name in library_map table
			$sql = "UPDATE redcap_library_map SET form_name = ? WHERE form_name = ? AND project_id = ?";
			db_query($sql, [$new_form_name, $form_name, $project_id]);
			// Change back-end form name in locking tables
			$sql = "UPDATE redcap_locking_labels SET form_name = ? WHERE form_name = ? AND project_id = ?";
			db_query($sql, [$new_form_name, $form_name, $project_id]);
			$sql = "UPDATE redcap_locking_data SET form_name = ? WHERE form_name = ? AND project_id = ?";
			db_query($sql, [$new_form_name, $form_name, $project_id]);
			$sql = "UPDATE redcap_esignatures SET form_name = ? WHERE form_name = ? AND project_id = ?";
			db_query($sql, [$new_form_name, $form_name, $project_id]);
			// Change back-end form name in survey table
			$sql = "UPDATE redcap_surveys SET form_name = ? WHERE form_name = ? AND project_id = ?";
			db_query($sql, [$new_form_name, $form_name, $project_id]);
			// Change alerts tables
			$alertIds = pre_query(
				"SELECT alert_id FROM redcap_alerts 
				 WHERE project_id = $project_id AND form_name = '$db_form_name'"
			);
			$sql = "UPDATE redcap_alerts SET form_name = ? WHERE form_name = ? AND project_id = ?";
			db_query($sql, [$new_form_name, $form_name, $project_id]);
			$sql = "UPDATE redcap_alerts_recurrence SET instrument = ? 
					WHERE alert_id IN ($alertIds) AND instrument = ?";
			db_query($sql, [$new_form_name, $form_name]);
			$sql = "UPDATE redcap_alerts_sent SET instrument = ? 
					WHERE alert_id IN ($alertIds) AND instrument = ?";
			db_query($sql, [$new_form_name, $form_name]);
			// Change back-end form name in redcap_form_display_logic_targets table
			$sql = "UPDATE redcap_form_display_logic_targets SET form_name = ? 
					WHERE form_name = ? AND event_id IN ($eventIds)";
			db_query($sql, [$new_form_name, $form_name]);
			// Change back-end form name in redcap_forms table
			$sql = "UPDATE redcap_forms SET form_name = ? 
					WHERE form_name = ? AND project_id = ?";
			db_query($sql, [$new_form_name, $form_name, $project_id]);
			// Change back-end form name in mycap tasks table
			$sql = "UPDATE redcap_mycap_tasks SET form_name = ? WHERE form_name = ? AND project_id = ?";
			db_query($sql, [$new_form_name, $form_name, $project_id]);
			// Change MultiLanguage metadata table
			MultiLanguageManagement\MultiLanguage::updateFormNameDuringDevelopment($project_id, $form_name, $new_form_name);
		}

		// End transaction
		$q = db_query("COMMIT");
		db_query("SET AUTOCOMMIT=1");

		return $q;
	}

	

	/**
	 * Get the designate for event link
	 * @param string|int $project_id 
	 * @param string $form_name 
	 * @return string
	 */
	public static function getDesignateForEventLink($project_id, $form_name) {
        global $user_rights;
		$Proj = new Project($project_id);
		if (!$Proj->longitudinal) return "";
		$designateForEvent = "";
		$designated = false;
		foreach ($Proj->eventsForms as $forms) {
			if (in_array($form_name, $forms)) {
				$designated = true;
				break;
			}
		}
		if (!$designated) {
			if ($user_rights['design'] && ($Proj->isDevelopment() || $Proj->isDraftMode())) {
				$link = RCView::a([
						"href" => APP_PATH_WEBROOT."Design/designate_forms.php?pid=".PROJECT_ID
					],
					RCView::tt("design_1382")
				);
			} 
			else {
				$link = RCView::tt("design_1384");
			}
			$designateForEvent = RCView::span([
						"class" => "od-designate-for-event field-action-item",
						"data-bs-toggle" => "tooltip",
						"data-bs-placement" => "bottom",
						"title" => RCView::tt_attr("design_1383"),
					], 
				RCIcon::ErrorNotificationTriangle("me-1 text-danger") . 
				$link
			);
		}
		return $designateForEvent;
	}

	/**
	 * Get form custom CSS
	 * @param string|int|null $project_id 
	 * @param string $form_name 
	 * @param bool $tryDraftMode When true, drafted CSS will be returned if the project is in draft mode
	 * @return string 
	 * @throws Exception When no valid project id can be determined
	 */
	public static function getFormCustomCSS($project_id, $form_name, $tryDraftMode = false) {
		list($Proj, $project_id) = Project::requireProject($project_id);
		$draft_preview_enabled = ($GLOBALS["draft_preview_enabled"] ?? false);
		// Determine which table to use depending on whether we're in draft mode/draft preview
		$table = ($Proj->isDraftMode() && ($tryDraftMode || $draft_preview_enabled)) 
			? "redcap_forms_temp" : "redcap_forms";
		$css = "";
		$sql = "SELECT custom_css FROM $table WHERE project_id = ? AND form_name = ?";
		$q = db_query($sql, [$project_id, $form_name]);
		if ($q && db_num_rows($q) > 0) {
			$row = db_fetch_assoc($q);
			$css = $row['custom_css'] ?? "";
		}
		// Fix quotes
		$css = str_replace(["&#039;", "&quot;"], ["'", '"'], $css);
		return  $css;
	}

	/**
	 * Set form custom CSS
	 * @param string|int|null $project_id 
	 * @param string $form_name 
	 * @param string $css 
	 * @return bool 
	 * @throws Exception When no valid project id can be determined
	 */
	public static function setFormCustomCSS($project_id, $form_name, $css) {
		list($Proj, $project_id) = Project::requireProject($project_id);
		// In draft mode, always write to the temp table
		$table = $Proj->isDraftMode() ? "redcap_forms_temp" : "redcap_forms";
		$sql = "UPDATE $table SET custom_css = ? WHERE project_id = ? AND form_name = ?";
		$q = db_query($sql, [$css, $project_id, $form_name]);
		// Check if any rows were affected
		if ($q && db_affected_rows($q) == 0 && trim($css) != "") {
			// No rows were affected, so insert (but only when there is actual content)
			$sql = "INSERT INTO $table (project_id, form_name, custom_css) VALUES (?, ?, ?)";
			$q = db_query($sql, [$project_id, $form_name, $css]);
		}
		// Logging
		if ($q) {
			$sql_log = $sql."\nParams (JSON): ".json_encode([$project_id, $form_name, $css]);
			\Logging::logEvent($sql_log, $table, "MANAGE", $form_name, "form_name = '$form_name'", "Update form custom CSS");
		}
		return $q;
	}

	public static function getFormCustomCSSDraftedChanges($project_id, $counts_only = false) {
		list($Proj, $project_id) = Project::requireProject($project_id);
		// Drafted
		$sql = "SELECT form_name, custom_css FROM redcap_forms_temp WHERE project_id = ?";
		$q = db_query($sql, [$project_id]);
		$drafted = [];
		while ($row = db_fetch_assoc($q)) {
			$drafted[$row['form_name']] = trim($row['custom_css'] ?? "");
		}
		// Current live
		$sql = "SELECT form_name, custom_css FROM redcap_forms WHERE project_id = ?";
		$q = db_query($sql, [$project_id]);
		$live = [];
		while ($row = db_fetch_assoc($q)) {
			$live[$row['form_name']] = trim($row['custom_css'] ?? "");
		}
		// Compare
		$diff = [
			"counts" => [
				"changed" => 0,
				"removed" => 0,
				"added" => 0,
				"new" => 0,
			],
			"forms" => [],
			"draft" => [],
			"live" => [],
			"strings" => [
				"changed" => RCView::tt_strip_tags("design_1406"),
				"added" => RCView::tt_strip_tags("design_1407"),
				"removed" => RCView::tt_strip_tags("design_1408"),
				"new" => RCView::tt_strip_tags("design_1409"),
			],
		];
		foreach ($drafted as $form_name => $draft_css) {
			$type = "";
			if (array_key_exists($form_name, $live)) {
				$live_css = $live[$form_name];
				if ($draft_css == $live_css) continue;
				if ($draft_css == "") {
					$type = "removed";
				}
				else if ($live_css == "") {
					$type = "added";
				}
				else {
					$type = "changed";
				}
			}
			else if ($draft_css != "") {
				$type = "new";
			}
			$diff["counts"][$type] += 1;
			if (!$counts_only) {
				$diff["forms"][$form_name] = [
					"type" => $type,
					"menu" => RCView::TrimForDropdownDisplay(strip_tags($Proj->forms_temp[$form_name]["menu"]), 50),
				]; 
				$diff["draft"][$form_name] = $draft_css;
				if ($type != "new") {
					$diff["live"][$form_name] = $live_css;
				}
			}
		}
		// Return results
		if ($counts_only) {
			$sum = array_reduce($diff["counts"], function($a, $b) {
				return $a + $b;
			}, 0);
			return $sum == 0 ? false : $diff["counts"];
		}
		else {
			ksort($diff["forms"]);
			return $diff;
		}
	}

}