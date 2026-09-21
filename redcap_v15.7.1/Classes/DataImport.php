<?php

/**
 * DataImport
 * This class is used for processes related to reports and the Data Import Tool.
 */
class DataImport
{
    // Transpose a CSV string
    public static function transposeCSV($csv_filepath, $delimiter = ",")
    {
        if ($delimiter == null) $delimiter = ",";

        // Open the input file for reading
        $inputHandle = fopen($csv_filepath, 'rb');

        // Read the CSV data into an array
        $rows = [];
        $i = 0;
        while (($data = fgetcsv($inputHandle, 0, $delimiter, '"', '')) !== FALSE) {
            if ($i == 0) {
                $i++;
                continue;
            }
            $rows[] = $data;
        }
        fclose($inputHandle);

        // Transpose the array
	    if (count($rows) === 1) {
		    // Pad with empty arrays to allow transposition
		    $rowLength = count($rows[0]);
		    $emptyRow = array_fill(0, $rowLength, null);
		    $rows[] = $emptyRow;
	    }
	    $transposed = array_map(null, ...$rows);

        // Open the output file for writing
        $outputFile = APP_PATH_TEMP . date("YmdHis") . '_' . PROJECT_ID . '_dataimport_'.generateRandomHash(6).'.csv';
        $outputHandle = fopen($outputFile, 'w');

        // Write the transposed data to the output file
        foreach ($transposed as $row) {
            fputcsv($outputHandle, $row, $delimiter, '"', '');
        }
        fclose($outputHandle);

        // Read the transposed CSV string from the output file
        $newCsv = file_get_contents($outputFile);
        unlink($outputFile);
        return $newCsv;
    }

	// Process uploaded Excel file, return references to (1) an array of fieldnames and (2) an array of items to be updated
	public static function csvToArray($csv_filepath, $format='rows', $delimiter = ",")
	{
		global $lang, $table_pk, $longitudinal, $Proj, $user_rights, $project_encoding;

		// Extract data from CSV file and rearrange it in a temp array
		$newdata_temp = array();
		$found_pk = false;
		$i = 0;

		$removeQuotes = false;
		$resetKeys = false; // Set flag to reset array keys if any headers are blank

		// CHECKBOXES: Create new arrays with all checkbox fields and the translated checkbox field names
		$fullCheckboxFields = array();
		foreach (MetaData::getCheckboxFields(PROJECT_ID) as $field=>$value) {
			foreach ($value as $code=>$label) {
				$code = (Project::getExtendedCheckboxCodeFormatted($code));
				$fullCheckboxFields[$field . "___" . $code] = array('field'=>$field, 'code'=>$code);
			}
		}

		// Always remove the BOM if the CSV file contains one
		$csvString1 = $csvString2 = file_get_contents($csv_filepath);
		$csvString1 = removeBOM($csvString1);
		if ($csvString1 !== $csvString2) {
		    // Re-save the file without the BOM
		    file_put_contents($csv_filepath, $csvString1);
        }

        if ($delimiter == null) $delimiter = ",";

		unset($csvString1, $csvString2);

		if (($handle = fopen($csv_filepath, "rb")) !== false)
		{
			// Loop through each row
			while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false)
			{
				// Detect if all values are blank in row (so we can ignore it)
				$numRowValuesBlank = 0;

				if ($i == 0)
				{
					## CHECK DELIMITER
					// Determine if comma- or tab-delimited (if can't find comma, it will revert to tab delimited)
					$firstLine = implode($delimiter, $row);
					 
					// If we find X number of tab characters, then we can safely assume the file is tab delimited
					$numTabs = 0;
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
				}

				// Find record identifier field
				if (!$found_pk)
				{
					if ($i == 0 && preg_replace("/[^a-z_0-9]/", "", $row[0]) == $table_pk) {
						$found_pk = true;
					} elseif ($i == 1 && preg_replace("/[^a-z_0-9]/", "", $row[0]) == $table_pk && $format == 'cols') {
						$found_pk = true;
						$newdata_temp = array(); // Wipe out the headers that already got added to array
						$i = 0; // Reset
					}
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
					// Reads as records in rows (default)
					if ($format == 'rows')
					{
						// Santize the variable name
						if ($i == 0) {
							$row[$j] = preg_replace("/[^a-zA-Z_0-9]/", "", $row[$j]);
							if ($row[$j] == '') {
								$resetKeys = true;
								continue;
							}
						} elseif (!isset($newdata_temp[0][$j]) || $newdata_temp[0][$j] == '') {
							continue;
						}
						// If value is blank, then increment counter
						if ($row[$j] == '') $numRowValuesBlank++;
						// Add to array
						$newdata_temp[$i][$j] = $row[$j];
						if ($project_encoding == 'japanese_sjis')
						{ // Use only for Japanese SJIS encoding
							$newdata_temp[$i][$j] = mb_convert_encoding($newdata_temp[$i][$j], 'UTF-8',  'sjis');
						}
					}
					// Reads as records in columns
					else
					{
						// Santize the variable name
						if ($j == 0) {
							$row[$j] = preg_replace("/[^a-zA-Z_0-9]/", "", $row[$j]);
							if ($row[$j] == '') {
								$resetKeys = true;
								continue;
							}
						} elseif ($newdata_temp[0][$i] == '') {
							continue;
						}
						$newdata_temp[$j][$i] = $row[$j];
						if ($project_encoding == 'japanese_sjis')
						{ // Use only for Japanese SJIS encoding
							$newdata_temp[$j][$i] = mb_convert_encoding($newdata_temp[$j][$i], 'UTF-8',  'sjis');
						}
					}
				}
				// If whole row is blank, then skip it
				if ($numRowValuesBlank == count($row)) {
					$resetKeys = true;
					unset($newdata_temp[$i]);
				}
				// Increment col counter
				$i++;
			}
			unset($row);
			fclose($handle);
		} else {
			// ERROR: File is missing
			$fileMissingText = (!SUPER_USER) ? $lang['period'] : " (".APP_PATH_TEMP."){$lang['period']}<br><br>{$lang['file_download_13']}";
			print 	RCView::div(array('class'=>'red'),
						RCView::b($lang['global_01'].$lang['colon'])." {$lang['file_download_08']} <b>\"".htmlspecialchars(basename($csv_filepath), ENT_QUOTES)."\"</b>
						{$lang['file_download_12']}{$fileMissingText}"
					);
			exit;
		}
		
		// If importing records as columns, remove any columns that are completely empty
		if ($format == 'cols') {
			$recCount = count($newdata_temp);
			for ($i=1; $i<$recCount; $i++) {
				// Set default for each record
				$recordEmpty = true;
				if (!isset($newdata_temp[$i])) continue;
				foreach ($newdata_temp[$i] as $val) {
					// If found a value, then skip to next record
					if ($val != '') {
						$recordEmpty = false;
						break;
					}
				}
				// Remove record
				if ($recordEmpty) {
					unset($newdata_temp[$i]);
				}
			}
			// If record count is now different, then re-index the array
			if ($recCount > count($newdata_temp)) {
				$newdata_temp = array_values($newdata_temp);
			}
		}

		// Give error message if record identifier variable name could not be found in expected places
		if (!$found_pk)
		{
			if ($format == 'rows') {
				$found_pk_msg = "{$lang['data_import_tool_134']} (\"$table_pk\") {$lang['data_import_tool_135']}";
			} else {
				$found_pk_msg = "{$lang['data_import_tool_134']} (\"$table_pk\") {$lang['data_import_tool_136']}";
			}
			print  "<div class='red' style='margin-bottom:15px;'>
						<b>{$lang['global_01']}:</b><br>
						$found_pk_msg<br><br>
						{$lang['data_import_tool_76']}
					</div>";
			renderPrevPageLink("index.php?route=DataImportController:index");
			exit;
		}

		// Shift the fieldnames  into a separate array called $fieldnames_new
		$fieldnames_new = array_shift($newdata_temp);

		//	Ensure that all record names are in proper UTF-8 format, if UTF-8 (no black diamond characters)
		if (function_exists('mb_detect_encoding')) {
			foreach ($newdata_temp as $key=>$row) {
				$this_record = $row[0];
				if (mb_detect_encoding($this_record) == 'UTF-8' && $this_record."" !== mb_convert_encoding($this_record, 'UTF-8', 'UTF-8')."") {
					// Convert to true UTF-8 to remove black diamond characters
					$newdata_temp[$key][0] = utf8_encode_rc($this_record);
				}
			}
			unset($row);
		}

		// If any columns were removed, reindex the arrays so that none are missing
		if ($resetKeys) {
			// Reindex the header array
			$fieldnames_new = array_values($fieldnames_new);
			// Loop through ALL records and reindex each
			foreach ($newdata_temp as $key=>&$vals) {
				$vals = array_values($vals);
			}
		}

		// If longitudinal, get array key of redcap_event_name field
		if ($longitudinal) {
			$eventNameKey = array_search('redcap_event_name', $fieldnames_new);
		}

		// Check if DAGs exist
		$groups = $Proj->getGroups();

		// If has DAGs, try to find DAG field
		if (!empty($groups)) {
			$groupNameKey = array_search('redcap_data_access_group', $fieldnames_new);
		}
		
		// Determine if using repeating instances
		$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
		$repeat_instance_index = $repeat_instrument_index = $importHasRepeatingFormsEvents = false;
		if ($hasRepeatingFormsEvents) {
			$repeat_instrument_index = array_search('redcap_repeat_instrument', $fieldnames_new);
			$repeat_instance_index = array_search('redcap_repeat_instance', $fieldnames_new);
			$importHasRepeatingFormsEvents = ($repeat_instance_index !== false);
		}

        /**
		// If repeating instance is "new", then compensate for this by making the instance "new#" to ensure uniqueness
		if ($importHasRepeatingFormsEvents)
		{
			$newInstanceKeyCount = array();
			foreach ($newdata_temp as $i => $element) {
				$repeat_instance = $element[$repeat_instance_index];
                // Get max instance in this import to check for conflict and also add this new one
                if ($repeat_instance == "new") {
					$record = $element[0];
					$event_id = $element[$eventNameKey];
					$repeat_instrument = isset($element[$repeat_instrument_index]) ? $element[$repeat_instrument_index] : "";
                    if (isset($newInstanceKeyCount["$record-$event_id-$repeat_instrument"])) {
                        $currentInstance = max($newInstanceKeyCount["$record-$event_id-$repeat_instrument"]) + 1;
                    } else {
                        $currentInstance = 1;
                    }
                    $newInstanceKeyCount["$record-$event_id-$repeat_instrument"][] = $currentInstance;
                    $repeat_instance .= $currentInstance;
					$newdata_temp[$i][$repeat_instance_index] = $repeat_instance;
                }
			}
		}
        */

		## PUT ALL UPLOADED DATA INTO $updateitems
		$updateitems = $invalid_eventids = array();
		foreach ($newdata_temp as $i => $element)
		{
			// Trim the record name, just in case
			$newdata_temp[$i][0] = $element[0] = trim($element[0]);
			// Get event_id to add as subkey for record
			$event_id = ($longitudinal) ? $Proj->getEventIdUsingUniqueEventName($element[$eventNameKey]) : $Proj->firstEventId;
			if ($longitudinal && $event_id === false) {
				// Invalid unique event name was used.
				$invalid_eventids[] = $element[$eventNameKey];
				continue;
			}
			// Loop through data array and add each record values to $updateitems
			for ($j = 0; $j < count($fieldnames_new); $j++) {
				// Get this field and value
				$this_field = trim($fieldnames_new[$j]);
				$this_value = trim($element[$j]);
				// Skip if field is blank
				if ($this_field == "") continue;
				elseif ($this_field == "redcap_repeat_instance" || $this_field == "redcap_repeat_instrument") {
					if ($hasRepeatingFormsEvents) continue;
					else {
						// Stop if uploading repeating fields when project is not set to repeat forms/events
						print  "<div class='red' style='margin-bottom:15px;'>
									<b>{$lang['global_01']}{$lang['colon']} {$lang['data_import_tool_252']}</b><br>
									{$lang['data_import_tool_253']}
								</div>";
						renderPrevPageLink("index.php?route=DataImportController:index");
						exit;
					}
				}
				// Is this row a repeating instance?
				$rowIsRepeatingInstance = false;
				if ($importHasRepeatingFormsEvents) {					
					$repeat_instance = $element[$repeat_instance_index];
					$repeat_instrument = $repeat_instrument_index ? $element[$repeat_instrument_index] : "";
					$rowIsRepeatingInstance = ($repeat_instance.$repeat_instrument."" != "");
				}
				if ($rowIsRepeatingInstance) {
					// Repeating instance
					if (isset($fullCheckboxFields[$this_field]) && isset($fullCheckboxFields[$this_field]['field'])) {
						// Checkbox
						$updateitems[$element[0]]['repeat_instances'][$event_id][$repeat_instrument][$repeat_instance][$fullCheckboxFields[$this_field]['field']][$fullCheckboxFields[$this_field]['code']] = $this_value;
					} else {
						// Non-checkbox
						$updateitems[$element[0]]['repeat_instances'][$event_id][$repeat_instrument][$repeat_instance][$this_field] = $this_value;
					}
				} else {
					// Regular non-repeating instance
					if (isset($fullCheckboxFields[$this_field]) && isset($fullCheckboxFields[$this_field]['field'])) {
						// Checkbox
						$updateitems[$element[0]][$event_id][$fullCheckboxFields[$this_field]['field']][$fullCheckboxFields[$this_field]['code']] = $this_value;
					} else {
						// Non-checkbox
						$updateitems[$element[0]][$event_id][$this_field] = $this_value;
					}
				}
			}
		}
		
		// Invalid unique event name was used.
		if (!empty($invalid_eventids)) 
		{
		    foreach ($invalid_eventids as &$val) $val = RCView::escape($val);
			print  "<div class='red' style='margin-bottom:15px;'>
						<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['data_import_tool_254']}
						\"<b>".implode("</b>\", \"<b>", $invalid_eventids)."</b>\"
					</div>";
			renderPrevPageLink("index.php?route=DataImportController:index");
			exit;
		}

		// If project has DAGs and redcap_data_access_group column is included and user is IN a DAG, then tell them they must remove the column
		if ($user_rights['group_id'] != '' && !empty($groups) && in_array('redcap_data_access_group', $fieldnames_new))
		{
			print  "<div class='red' style='margin-bottom:15px;'>
						<b>{$lang['global_01']}{$lang['colon']} {$lang['data_import_tool_171']}</b><br>
						{$lang['data_import_tool_172']}
					</div>";
			renderPrevPageLink("index.php?route=DataImportController:index");
			exit;
		}
		// DAG check to make sure that a single record doesn't have multiple values for 'redcap_data_access_group'
		elseif ($user_rights['group_id'] == '' && !empty($groups) && $groupNameKey !== false)
		{
			// Creat array to collect all DAG designations for each record (each should only have one DAG listed)
			$dagPerRecord = array();
			foreach ($newdata_temp as $thisrow) {
				// Get record name
				$record = $thisrow[0];
				// Get DAG name for this row/record
				$dag = $thisrow[$groupNameKey];
				// Add to array
				$dagPerRecord[$record][$dag] = true;
			}
			unset($thisrow);
			// Now loop through all records and remove all BUT those with duplicates
			foreach ($dagPerRecord as $record=>$dags) {
				if (count($dags) <= 1) {
					unset($dagPerRecord[$record]);
				}
			}
			// If there records with multiple DAG designations, then stop here and throw error.
			if (!empty($dagPerRecord))
			{
				print  "<div class='red' style='margin-bottom:15px;'>
							<b>{$lang['global_01']}{$lang['colon']} {$lang['data_import_tool_173']}</b><br>
							{$lang['data_import_tool_174']} <b>".implode("</b>, <b>", array_keys($dagPerRecord))."</b>{$lang['period']}
						</div>";
				renderPrevPageLink("index.php?route=DataImportController:index");
				exit;
			}
		}

		return $updateitems;
	}


	// Display errors/warnings in table format. Return HTML string.
	public static function displayErrorTable($errors, $warnings)
	{
		global $lang;
		$altrow = 1;
		$errortable =  "<br><table id='errortable'><tr><th scope=\"row\" class=\"comp_fieldname\" bgcolor=\"black\" colspan=4>
						<font color=\"white\">{$lang['data_import_tool_97']}</th></tr>
						<tr><th scope='col'>{$lang['global_49']}</th><th scope='col'>{$lang['data_import_tool_98']}</th>
						<th scope='col'>{$lang['data_import_tool_99']}</th><th scope='col'>{$lang['data_import_tool_100']}</th></tr>";
		foreach ($errors as $item) {
			$altrow = $altrow ? 0 : 1;
			$errortable .= $altrow ? "<tr class='alt'>" : "<tr>";
			$errortable .= "<th>".RCView::escape($item[0])."</th>";
			$errortable .= "<td class='comp_new'>".RCView::escape($item[1])."</td>";
			$errortable .= "<td class='comp_new_error'>".RCView::escape($item[2])."</td>";
			$errortable .= "<td class='comp_new'>".RCView::escape($item[3])."</td>";
		}
		foreach ($warnings as $item) {
			$altrow = $altrow ? 0 : 1;
			$errortable .= $altrow ? "<tr class='alt'>" : "<tr>";
			$errortable .= "<th>".RCView::escape($item[0])."</th>";
			$errortable .= "<td class='comp_new'>".RCView::escape($item[1])."</td>";
			$errortable .= "<td class='comp_new_warning'>".RCView::escape($item[2])."</td>";
			$errortable .= "<td class='comp_new'>".RCView::escape($item[3])."</td>";
		}
		$errortable .= "</table>";
		return $errortable;
	}


	// Display data comparison table
	public static function displayComparisonTable($updateitems, $format='rows', $doODM=false)
	{
		global $lang, $table_pk, $user_rights, $longitudinal, $Proj;
		
		// Get record names being imported (longitudinal will not have true record name as array key
		$record_names = array();
		foreach (array_keys($updateitems) as $key) {
			list ($this_record, $nothing, $nothing, $nothing) = explode_right("-", $key, 4);
			$record_names[] = $this_record;
		}
		$record_names = array_values(array_unique($record_names));

		// Determine if imported values are a new or existing record by gathering all existing records into an array for reference
		$existing_records = array();
		foreach (Records::getRecordList($Proj->project_id, $user_rights['group_id'],false, false,null,null, 0, $record_names) as $this_record) {
			$existing_records[$this_record.""] = true;
		}

		$comparisontable = array();
		$rowcounter = 0;
		$columncounter = 0;
		$fieldsIgnore = array();

        $missingDataCodes = parseEnum($Proj->project['missing_data_codes']);
        $hasMissingDataCodes = !empty($missingDataCodes);

		//make "header" column (leftmost column) with fieldnames
		foreach ($updateitems as $studyevent) {
			foreach (array_keys($studyevent) as $fieldname) {
				if (isset($Proj->metadata[$fieldname]) &&
                    ($Proj->metadata[$fieldname]['element_type'] == 'calc'
                    || (!$doODM && $Proj->metadata[$fieldname]['element_type'] == 'file' && !$hasMissingDataCodes)
                    || ($Proj->metadata[$fieldname]['element_type'] == "text" && (Calculate::isCalcDateField($Proj->metadata[$fieldname]['misc']) || Calculate::isCalcTextField($Proj->metadata[$fieldname]['misc'])))
                    )
                ) {
					$fieldsIgnore[$fieldname] = true;
					continue;
				}
				$comparisontable[$rowcounter++][$columncounter] = "<th scope='row' class='comp_fieldname'>$fieldname</th>";
			}
			$columncounter++;
			break;
		}
		
		// If "Require Reason for Change" is enabled, then check which forms have data
		if ($GLOBALS['require_change_reason']) {
			$formStatusValues = Records::getFormStatus(PROJECT_ID, $record_names);
		}

		// Create array of all new records
		$newRecords = array();
		// Loop through all values
		foreach ($updateitems as $key=>$studyevent)
		{
			if (!isset($studyevent[$table_pk]['new'])) continue;
			list ($this_record, $this_event, $this_repeat_instrument, $this_instance) = explode_right("-", $key, 4);
			$this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event);
			
			$rowcounter = 0;
			// Get record and evenet_id
			$studyid = $studyevent[$table_pk]['new'];
			$event_id = ($longitudinal) ? $Proj->getEventIdUsingUniqueEventName($studyevent['redcap_event_name']['new']) : $Proj->firstEventId;
			// Check if a new record or not
			$newrecord = !isset($existing_records[$studyid.""]);
			// Increment new record count
			if ($newrecord) $newRecords[] = $studyid;
			// Loop through fields/values
			foreach ($studyevent as $fieldname=>$studyrecord)
			{
				if (isset($fieldsIgnore[$fieldname])) {
					continue;
				}
				$this_form = (isset($Proj->metadata[$fieldname]) ? $Proj->metadata[$fieldname]['form_name'] : "");
				$this_form_instance = is_numeric($this_instance) ? $this_instance : 1;
				//print "<br>$studyid, $event_id, $fieldname: ".$updateitems[$key][$fieldname]['new'];
				if ($rowcounter == 0){ //case of column header (cells contain the record id)
					// Check if a new record or not
					$existing_status_class = '';
					if (isset($_REQUEST['forceAutoNumber']) && $_REQUEST['forceAutoNumber'] == '1') {
						$existing_status = "<div class='new_impt_rec'>({$lang['data_import_tool_268']})</div>";
					} elseif (!$newrecord) {
						$existing_status_class = 'exist_impt_rec';
						$existing_status = "<div class='$existing_status_class'>({$lang['data_import_tool_144']})</div>";
					} else {
						$existing_status = "<div class='new_impt_rec'>({$lang['data_import_tool_145']})</div>";
					}
					// Render record number as table header
					$comparisontable[$rowcounter][$columncounter] = "<th scope='col' class='comp_recid'><span id='record-{$columncounter}' class='$existing_status_class'>".htmlspecialchars($studyid, ENT_QUOTES)."</span>
																	 <span style='display:none;' id='event-{$columncounter}'>$event_id</span>$existing_status</th>";
				} else {
				//3 cases: new (+ errors or warnings), old, and update (+ errors or warnings)
					// Display redcap event name normally
					if (!(isset($updateitems[$key][$fieldname]))){
						$comparisontable[$rowcounter][$columncounter] = "<td class='comp_old'>&nbsp;</td>";
					} else {
						if ($updateitems[$key][$fieldname]['status'] == 'add'){
							if (isset($updateitems[$key][$fieldname]['validation'])){
								//if error
								if ($updateitems[$key][$fieldname]['validation'] == 'error'){
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_new_error'>" . RCView::escape($updateitems[$key][$fieldname]['new']) . "</td>";
								}
								elseif ($updateitems[$key][$fieldname]['validation'] == 'warning'){ //if warning
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_new_warning'>" . RCView::escape($updateitems[$key][$fieldname]['new']) . "</td>";
								}
								else {
									//shouldn't be a case of this
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_new'>problem!</td>";
								}
							}
							else{
								// If requiring reason for change, check if form has any data
								$formHasDataClass = "";
								if ($GLOBALS['require_change_reason']) {
									$formHasData = (isset($formStatusValues[$this_record][$this_event_id][$this_form][$this_form_instance]) && is_numeric($formStatusValues[$this_record][$this_event_id][$this_form][$this_form_instance]));
									if ($formHasData) $formHasDataClass = "form_has_data";
								}
								$comparisontable[$rowcounter][$columncounter] = "<td class='comp_new $formHasDataClass'>" . RCView::escape($updateitems[$key][$fieldname]['new']) . "</td>";
							}
						}
						elseif ($updateitems[$key][$fieldname]['status'] == 'keep'){
							if ($updateitems[$key][$fieldname]['old'] != ""){
								$comparisontable[$rowcounter][$columncounter] = "<td class='comp_old'>" . RCView::escape($updateitems[$key][$fieldname]['old']) . "</td>";
							} else {
								$comparisontable[$rowcounter][$columncounter] = "<td class='comp_old'>&nbsp;</td>";
							}
						}
						elseif ($updateitems[$key][$fieldname]['status'] == 'update' || $updateitems[$key][$fieldname]['status'] == 'delete'){
							if (isset($updateitems[$key][$fieldname]['validation'])){
								//if error
								if ($updateitems[$key][$fieldname]['validation'] == 'error'){
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_update_error'>" . RCView::escape($updateitems[$key][$fieldname]['new']) . "</td>";
								} elseif ($updateitems[$key][$fieldname]['validation'] == 'warning'){ //if warning
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_update_warning'>" . RCView::escape($updateitems[$key][$fieldname]['new']);
									if (!$newrecord) {
										$comparisontable[$rowcounter][$columncounter] .= "<br><span class='comp_oldval'>("
											. RCView::escape($updateitems[$key][$fieldname]['old'])
											. ")</span>";
									}
									$comparisontable[$rowcounter][$columncounter] .= "</td>";
								} else {
									//shouldn't be a case of this
									$comparisontable[$rowcounter][$columncounter] = "<td class='comp_new'>problem!</td>";
								}
							} else {
								// If requiring reason for change, check if form has any data
								$formHasDataClass = "";
								if ($GLOBALS['require_change_reason']) {
									$formHasData = (isset($formStatusValues[$this_record][$this_event_id][$this_form][$this_form_instance]) && is_numeric($formStatusValues[$this_record][$this_event_id][$this_form][$this_form_instance]));
									if ($formHasData) $formHasDataClass = "form_has_data";
								}
								// Show new and old value
								$comparisontable[$rowcounter][$columncounter] = "<td class='comp_update $formHasDataClass'>"
																			  . RCView::escape($updateitems[$key][$fieldname]['new']);
								if (!$newrecord) {
									$comparisontable[$rowcounter][$columncounter] .= "<br><span class='comp_oldval'>("
																				  . RCView::escape($updateitems[$key][$fieldname]['old'])
																				  . ")</span>";
								}
								$comparisontable[$rowcounter][$columncounter] .= "</td>";
							}
						}
					}
				}
				$rowcounter++;
			}
			$columncounter++;
		}

		// Build table (format as ROWS)
		if ($format == 'rows')
		{
			$comparisonstring = "<table id='comptable'><tr><th scope='row' class='comp_fieldname' colspan='$rowcounter' bgcolor='black'><font color='white'><b>{$lang['data_import_tool_28']}</b></font></th></tr>";
			for ($rowi = 0; $rowi <= $columncounter; $rowi++)
			{
				$comparisonstring .= "<tr>";
				for ($colj = 0; $colj < $rowcounter; $colj++)
				{
					$comparisonstring .= isset($comparisontable[$colj][$rowi]) ? $comparisontable[$colj][$rowi] : '';
				}
				$comparisonstring .= "</tr>";
			}
			$comparisonstring .= "</table>";
		}
		// Build table (format as COLUMNS)
		else
		{
			$comparisonstring = "<table id='comptable'><tr><th scope='row' class='comp_fieldname' colspan='" . ($columncounter+1) . "' bgcolor='black'><font color='white'><b>{$lang['data_import_tool_28']}</b></font></th></tr>";
			foreach ($comparisontable as $rowi => $rowrecord)
			{
				$comparisonstring .= "<tr>";
				foreach ($rowrecord as $colj =>$cellpoint)
				{
					$comparisonstring .= $comparisontable[$rowi][$colj];
				}
				$comparisonstring .= "</tr>";
			}
			$comparisonstring .= "</table>";
		}

		// If user is not allowed to create new records, then stop here if new records exist in uploaded file
		if (!$user_rights['record_create'] && !empty($newRecords))
		{
			print  "<div class='red' style='margin-bottom:15px;'>
						<b>{$lang['global_01']}{$lang['colon']}</b><br>
						{$lang['data_import_tool_159']} <b>
						".implode("</b>, <b>", $newRecords)."</b>{$lang['period']}
					</div>";
			renderPrevPageLink("index.php?route=DataImportController:index");
			exit;
		}

		return $comparisonstring;
	}

	
	// Download import template CSV file
	public static function downloadCSVImportTemplate()
	{
		extract($GLOBALS);

		//Choosing Delimiter ("," comma by default)
		$delimit = ',';
		if (isset($_GET['delimiter']) and $_GET['delimiter']<>"") {
			$delimiterget = $_GET['delimiter'];
			switch ($delimiterget) {
				case 'comma': $delimit = ","; break;
				case 'semicolon': $delimit = ";"; break;
				case 'tab': $delimit = "\t"; break;
				default: $delimit = ","; break;
			}
		}
		
		//Make column headers (COLUMN format only)
		$data = '';
		if ($_GET['format'] == 'cols') {
			$data = "Variable / Field Name";
			for ($k=1; $k<=20; $k++) {
				$data .= $delimit."Record";
			}
			// Line break
			$data .= "\r\n";
		}

		// Check if DAGs exist. Add redcap_data_access_group field if DAGs exist AND user is not in a DAG
		$dags = $Proj->getGroups();
		$addDagField = (!empty($dags) && $user_rights['group_id'] == '');

		//Get the field names from metadata table
		$select =  "SELECT field_name, element_type, element_enum FROM redcap_metadata WHERE element_type != 'calc'
					and element_type != 'file' and element_type != 'descriptive' and project_id = $project_id ORDER BY field_order";
		$export = db_query($select);
		while ($row = db_fetch_array($export))
		{
			// If a checkbox field, then loop through choices to render pseudo field names for each choice
			if ($row['element_type'] == "checkbox")
			{
				foreach (array_keys(parseEnum($row['element_enum'])) as $this_value) {
					//Write data for each cell
					$data .= Project::getExtendedCheckboxFieldname($row['field_name'], $this_value);
					// Line break OR comma
					$data .= ($_GET['format'] == 'rows') ? $delimit : "\r\n";
				}
			}
			// Normal non-checkbox fields
			else
			{
				//Write data for each cell
				$data .= $row['field_name'];
				// Line break OR comma
				$data .= ($_GET['format'] == 'rows') ? $delimit : "\r\n";
			}
			// If we're on the first field and project is longitudinal, add redcap_event_name
			if ($row['field_name'] == $table_pk)
			{
				if ($longitudinal) {
					//Write data for each cell
					$data .= "redcap_event_name";
					// Line break OR comma
					$data .= ($_GET['format'] == 'rows') ? $delimit : "\r\n";
				}
				if ($Proj->hasRepeatingFormsEvents()) {
					//Write data for each cell
					$data .= "redcap_repeat_instrument";
					// Line break OR comma
					$data .= ($_GET['format'] == 'rows') ? $delimit : "\r\n";
					//Write data for each cell
					$data .= "redcap_repeat_instance";
					// Line break OR comma
					$data .= ($_GET['format'] == 'rows') ? $delimit : "\r\n";
				}
				if ($addDagField) {
					//Write data for each cell
					$data .= "redcap_data_access_group";
					// Line break OR comma
					$data .= ($_GET['format'] == 'rows') ? $delimit : "\r\n";
				}
			}
		}

		// Logging
		Logging::logEvent("","redcap_metadata","MANAGE",$project_id,"project_id = $project_id","Download data import template");

		// Begin output to file
		$file_name = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 30)."_ImportTemplate_".date("Y-m-d").".csv";
		header('Pragma: anytextexeptno-cache', true);
		header("Content-type: application/csv");
		header("Content-Disposition: attachment; filename=$file_name");

		// Output the data
		print $data;
	}


	// Convert associative array into a single CSV row
	public static function getSingleCsvRowFromArray($array, $delimiter)
	{
		$fp = fopen('php://memory', "x+");
		fputcsv($fp, $array, $delimiter, '"', '');
		fseek($fp, 0);
		$csvString = trim(stream_get_contents($fp));
		fclose($fp);
		return $csvString;
	}


	// Add all rows of the CSV file to a db table. Return boolean regarding success
	public static function storeAsyncDataRows($data, $delimiter, $date_format, $overwrite_behavior, $force_auto_number, $import_filename, $change_reason)
	{
        global $user_rights;

		$Proj = new Project(PROJECT_ID);

		// Add CSV string to memory file so we can parse it
		$h = fopen('php://memory', "x+");
		fwrite($h, removeBOM($data));
		fseek($h, 0);

		// Get CSV headers first, and obtain record/event_id keys
		$row = fgetcsv($h, 0, $delimiter, '"', '');
		if ($row === false) return false;
		$recordKey = array_search($Proj->table_pk, $row);
		if ($recordKey === false) return false;
		$eventKey = $Proj->longitudinal ? array_search('redcap_event_name', $row) : false;
		$csvHeaders = self::getSingleCsvRowFromArray($row, $delimiter);
        $dag_id = UserRights::isSuperUserNotImpersonator() ? null : $user_rights['group_id'];
        if ($dag_id == '') $dag_id = null;

		// Put import info and headers into db table
		$sql = "insert into redcap_data_import (project_id, user_id, upload_time, csv_header, delimiter, date_format, 
                overwrite_behavior, force_auto_number, filename, change_reason, dag_id) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$params = [PROJECT_ID, User::getUserInfo(USERID)['ui_id'], date("Y-m-d H:i:s"), $csvHeaders, ($delimiter == "\t" ? "TAB" : $delimiter), $date_format, $overwrite_behavior, $force_auto_number, $import_filename, $change_reason, $dag_id];
		if (!db_query($sql, $params)) return false;
		$import_id = db_insert_id();

		// Now read the rest of the CSV file
		$recordsRowKeys = [];
		$recordsRowCount = [];
        $thresholdBeginCheckRowSize = 100; // After hitting this # of rows added for single record/row, start checking the size of the field value to prevent it from getting crazy big.
        $thresholdRowSizeMax = 26214400; // Max byte size for a given record; 26214400=25MB
        $lastCsvRow = '';
        $lastRecord = '';
        $recordCsvRow = '';
		while (($row = fgetcsv($h, 0, $delimiter, '"', '')) !== false) {
			// If row is completely blank, then skip it
			if (mb_strlen(trim(implode("", $row))) === 0) continue;
			// Get this row of data from the CSV file
			$thisCsvRow = self::getSingleCsvRowFromArray($row, $delimiter);
			// Get record and event_id
			$event_id = ($eventKey !== false && isset($row[$eventKey])) ? $Proj->getEventIdUsingUniqueEventName($row[$eventKey]) : $Proj->firstEventId;
			if (!is_numeric($event_id)) $event_id = $Proj->firstEventId;
			$record = ($recordKey !== false && isset($row[$recordKey])) ? $row[$recordKey] : null;
            // Make sure we're not stuck in some loop or that the same data is being repeated
            if ($record === null || ($lastCsvRow == $thisCsvRow && $lastRecord == $record)) {
				$recordCsvRow = '';
                continue;
            }
			// Add record to $recordsRowKeys to keep data together in same row_id for each individual record (especially in case record auto-numbering is used)
			if (isset($recordsRowKeys[$record])) {
				// Already added to table, so obtain record's row_id
				$row_id = $recordsRowKeys[$record];
				// Update the row with the new data - do not use concat() in the query because it can sometimes cause performance issues when using many times repeatedly
				$sql = "select row_data from redcap_data_import_rows where row_id = ?";
				$recordCsvRow = db_result(db_query($sql, $row_id));
				$recordCsvRow = ($recordCsvRow === false ? "" : $recordCsvRow . "\n") . $thisCsvRow;
				$sql = "update redcap_data_import_rows set row_data = ? where row_id = ?";
				$params = [$recordCsvRow, $row_id];
				if (db_query($sql, $params)) {
                    $recordsRowCount[$record]++;
                    $lastCsvRow = $thisCsvRow;
                    $lastRecord = $record;
                }
                // Check size: After hitting a certain # of rows added for single record/row, start checking the size of the field value to prevent it from getting crazy big.
                if ($recordsRowCount[$record] > $thresholdBeginCheckRowSize) {
                    $sql = "select length(row_data) from redcap_data_import_rows where row_id = ?";
                    $rowSize = db_result(db_query($sql, $row_id), 0);
                    if ($rowSize > $thresholdRowSizeMax) {
                        // If record has gotten too big to properly parse, stop here with an error
                        return false;
                    }
                }
			} else {
				// Store CSV row of data
                $recordCsvRow = $thisCsvRow;
				$sql = "insert into redcap_data_import_rows (import_id, record_provided, event_id, row_data)  values (?, ?, ?, ?)";
				$params = [$import_id, $record, $event_id, $recordCsvRow];
				if (db_query($sql, $params)) {
					$recordsRowKeys[$record] = $row_id = db_insert_id();
                    $recordsRowCount[$record] = 1;
                    $lastCsvRow = $thisCsvRow;
                    $lastRecord = $record;
				}
			}
		}
		unset($thisCsvRow, $row, $recordCsvRow);
		fclose($h);

		if (empty($recordsRowKeys)) {
			// If nothing was added, then return false
			$sql = "delete from redcap_data_import where import_id = ?";
			db_query($sql, $import_id);
			return false;
		} else {
			// Add records_provided to redcap_data_import
			$sql = "update redcap_data_import 
					set records_provided = ?, records_imported = 0, total_errors = 0, status = 'QUEUED'
					where import_id = ?";
			$params = [count($recordsRowKeys), $import_id];
			db_query($sql, $params);
			return true;
		}
	}


	// Get import process attributes by import_id. Return as array.
	public static function getAsyncDataImportAttr($import_id)
	{
        $sql = "select * from redcap_data_import where import_id = ?";
        $q = db_query($sql, $import_id);
        return (db_num_rows($q) ? db_fetch_assoc($q) : []);
    }


	// Given an import_id, is the current user the same as the uploader who began the import?
	public static function currentUserIsAsyncUploader($import_id)
	{
        return self::getAsyncDataImportAttr($import_id)['user_id'] == UI_ID;
    }


	// If a specific asynchronous/background import batch has been completed, then mark as completed
	public static function checkCompleteAsyncDataImport($import_id, $records_provided)
	{
		global $lang;
		// If job already completed, return false
		$sql = "select status from redcap_data_import where import_id = ?";
		$q = db_query($sql, $import_id);
		if (db_result($q, 0) == 'COMPLETED') return false;
		// Get counts
		$sql = "select count(*) as thiscount, round(sum(total_time)/1000) as total_time, sum(error_count) as error_count
				from redcap_data_import_rows where import_id = ? and row_status in ('COMPLETED', 'FAILED')";
		$q = db_query($sql, $import_id);
		if (!db_num_rows($q)) return false;
		$totalRecordsProcessed = db_result($q, 0, 'thiscount');
		$totalProcessingTime = db_result($q, 0, 'total_time');
		$errorCount = db_result($q, 0, 'error_count');
		if ($errorCount == null) $errorCount = 0;
		if ($totalRecordsProcessed == $records_provided)
		{
			// Mark job as completed (but make sure it wasn't already completed running another cron job)
			$sql = "update redcap_data_import set status = 'COMPLETED' where import_id = ?";
			$q = db_query($sql, $import_id);
			if ($q && db_affected_rows() > 0)
			{
				// Get total records imported
				$sql = "select count(*) from redcap_data_import_rows where import_id = ? and row_status = 'COMPLETED'";
				$q = db_query($sql, $import_id);
				$recordsImported = db_result($q, 0);
				// Also set timestamp of completion
				$sql = "update redcap_data_import 
						set completed_time = ?, total_processing_time = ?, records_imported = ?, total_errors = ? 
						where import_id = ?";
				db_query($sql, [date("Y-m-d H:i:s"), $totalProcessingTime, $recordsImported, $errorCount, $import_id]);
				// Notify the user that their data import has completed
				$sql = "select i.user_email, i.datetime_format, d.project_id, d.upload_time, d.completed_time, d.records_provided
						from redcap_data_import d, redcap_user_information i
               			where d.import_id = ? and d.user_id = i.ui_id";
				$q = db_query($sql, $import_id);
				$row = db_fetch_assoc($q);
                // Convert times into user's preferred datetime format
                $row['upload_time'] = DateTimeRC::format_user_datetime($row['upload_time'], 'Y-M-D_24', $row['datetime_format']);
                $row['completed_time'] = DateTimeRC::format_user_datetime($row['completed_time'], 'Y-M-D_24', $row['datetime_format']);
				// Send email
				$subject = "[REDCap] ".$lang['data_import_tool_315']." (PID {$row['project_id']})";
                if ($errorCount > 0) $subject .= " ".strip_tags(RCView::tt_i("data_import_tool_341", [$errorCount]));
				// Add link to view results/errors
				$message = RCView::tt_i("data_import_tool_316", [$row['upload_time'], $row['completed_time'], $row['records_provided'], $recordsImported, $errorCount]);
                if ($errorCount > 0) $message .= RCView::br().RCView::br().$lang['data_import_tool_342'];
				$message .= RCView::br().RCView::br().RCView::a(['href'=>APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/index.php?pid={$row['project_id']}&route=DataImportController:index&import_id=$import_id"], $lang['data_import_tool_317']).RCView::br();
				REDCap::email($row['user_email'], \Message::useDoNotReply($GLOBALS['project_contact_email']), $subject, $message, '', '', $GLOBALS['project_contact_name'], [], $row['project_id']);
				return true;
			}
		}
		return false;
	}


	// Dynamically determine the batch size of a single batch of asynchronous/background data import
	const BATCH_LENGTH_MINUTES = 3;
	const MIN_RECORDS_PER_BATCH = 50;
	const MAX_RECORDS_PER_BATCH = 3000;
	public static function getBatchSizeAsyncDataImport()
	{
		// Get timestamp of 1 day ago
		$xDaysAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-1,date("Y")));
		// Get average processing time over the past day
		$sql = "select round(avg(total_time)) from (
					select total_time from redcap_data_import_rows
					where row_status = 'COMPLETED' and end_time > '".db_escape($xDaysAgo)."'
					order by row_id desc
				) x";
		$q = db_query($sql);
		$avg_time_per_record_ms = db_result($q, 0);
		if ($avg_time_per_record_ms != null && $avg_time_per_record_ms > 0) {
			$est_records_per_batch = round((self::BATCH_LENGTH_MINUTES*60)/($avg_time_per_record_ms/1000));
			// If calculated value is less than minimum, then use minimum instead
			if ($est_records_per_batch < self::MIN_RECORDS_PER_BATCH) {
				return self::MIN_RECORDS_PER_BATCH;
			} elseif ($est_records_per_batch > self::MAX_RECORDS_PER_BATCH) {
				return self::MAX_RECORDS_PER_BATCH;
			} elseif (isinteger($est_records_per_batch) && $est_records_per_batch > 0) {
				return $est_records_per_batch;
			} else {
				return self::MIN_RECORDS_PER_BATCH;
			}
		} else {
			// If could not determine from table, then use hard-coded default
			return self::MIN_RECORDS_PER_BATCH;
		}
	}


	// Process a single batch of asynchronous/background data imports row by row
	public static function processAsyncDataImports()
	{
        $oneHourAgo = date("Y-m-d H:i:s", mktime(date("H")-1,date("i"),date("s"),date("m"),date("d"),date("Y")));
        $twoDaysAgo = date("Y-m-d H:i:s", mktime(date("H")-48,date("i"),date("s"),date("m"),date("d"),date("Y")));

        // If any uploads have been stuck initializing for more than an hour, set them as cancelled (since they are obviously never going to start)
        $sql = "select import_id from redcap_data_import where status = 'INITIALIZING' and upload_time < '$oneHourAgo'";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            self::cancelBackgroundImportAction($row['import_id']);
        }

        // If any uploads have finished imported all records but are somehow still stuck as PROCESSING, set to COMPLETED
        $sql = "select import_id, records_provided from redcap_data_import 
                where status = 'PROCESSING' and upload_time > '$twoDaysAgo' and records_imported = records_provided";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            self::checkCompleteAsyncDataImport($row['import_id'], $row['records_provided']);
        }

        // If any individual batches have been stuck processing for more than 48 hours, set them as cancelled (since they are obviously never going to start)
        $sql = "select import_id from redcap_data_import where status = 'PROCESSING' and upload_time < '$twoDaysAgo'";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Get the completion/cancel time to apply to this batch
            $sql = "select greatest(max(end_time), max(start_time)) from redcap_data_import_rows where import_id = ?";
            $completionTime = db_result(db_query($sql, $row['import_id']), 0);
            if ($completionTime == '') $completionTime = date("Y-m-d H:i:s");
            // If all rows are completed, then set batch as completed using the max timestamp
            $sql = "select 1 from redcap_data_import_rows where import_id = ? and row_status = 'PROCESSING' limit 1";
            $someStuckProcessing = (db_num_rows(db_query($sql, $row['import_id'])) > 0);
            if ($someStuckProcessing) {
                // STUCK: This batch is stuck and will never finish, so cancel it
                self::cancelBackgroundImportAction($row['import_id'], $completionTime);
            } else {
                // COMPLETED: This batch actually completed importing all rows, so mark as completed
                $sql = "update redcap_data_import 
                        set status = 'COMPLETED', completed_time = ?
                        where import_id = ? and status = 'PROCESSING'";
                db_query($sql, [$completionTime, $row['import_id']]);
            }
        }

		// Check if we're done with the whole batch
		$prev_import_id = null;
		$prev_records_provided = null;
		$recordsProcessed = 0;

		// Find dynamic ways to limit these based on server processing speed
		$limit = self::getBatchSizeAsyncDataImport();

		// Gather all the processes that might get done in this batch
		$sql = "select r.row_id
				from redcap_data_import i, redcap_data_import_rows r
				where i.import_id = r.import_id and i.status in ('PROCESSING', 'QUEUED') and i.completed_time is null and r.row_status = 'QUEUED'
				order by r.import_id, r.row_id
				limit $limit";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
            // Add row_id's to array
            $row_ids = [];
            while ($row = db_fetch_assoc($q)) {
                $row_ids[] = $row['row_id'];
            }
            // Set all rows in this batch to PROCESSING status so that other simultaneous crons won't pick them up in the query above
            $sql = "update redcap_data_import_rows 
                    set row_status = 'PROCESSING' 
                    where row_status = 'QUEUED' and row_id in (".implode(',', $row_ids).")";
            db_query($sql);
            // Now loop through
			foreach ($row_ids as $key=>$row_id)
            {
                $sql = "select i.import_id, r.row_id, i.project_id, u.username, i.csv_header, i.delimiter, i.date_format, i.status, i.change_reason,
                        i.force_auto_number, r.row_data, r.record_provided, r.event_id, i.records_provided, i.overwrite_behavior, r.row_status, i.dag_id
                        from redcap_data_import i, redcap_data_import_rows r, redcap_user_information u
                        where i.import_id = r.import_id and u.ui_id = i.user_id and r.row_id = ?";
                $q = db_query($sql, $row_id);
                if (db_num_rows($q) == 0) continue;
                $row = db_fetch_assoc($q);
				// Change QUEUED to PROCESSING for the whole batch, if applicable
				if ($row['status'] == 'QUEUED') {
					$sql = "update redcap_data_import set status = 'PROCESSING' where status = 'QUEUED' and import_id = ?";
					db_query($sql, $row['import_id']);
				}
				// If a specific import batch has been completed, then mark as completed
				if ($prev_import_id !== null && $row['import_id'] != $prev_import_id) {
					self::checkCompleteAsyncDataImport($row['import_id'], $row['records_provided']);
				}
                // If this row had already been imported by another cron or if the batch has been cancelled by the user, then skip this row
                if ($row['row_status'] == 'COMPLETED' || $row['row_status'] == 'FAILED' || $row['row_status'] == 'CANCELED'
                    || $row['status'] == 'CANCELED' || $row['status'] == 'PAUSED') continue;
                // If using "Reason for Change" setting, add it to the record/event's logging
                $changeReasons = [];
                if ($row['force_auto_number'] == '0' && $row['change_reason'] != '') { // This won't be done if record auto-numbering is enabled for the import or if no reason is provided
                    // Make sure reason for change setting is still enabled in the project, and make sure the record exists first (should not add reason for new records being created)
                    $requireChangeReason = db_result(db_query("select require_change_reason from redcap_projects where project_id = ?", $row['project_id']), 0);
                    if ($requireChangeReason == '1' && Records::recordExists($row['project_id'], $row['record_provided'])) {
                        // Add the reason for the record/event
                        $changeReasons[$row['record_provided']][$row['event_id']] = $row['change_reason'];
                    }
                }
				// Set this row as PROCESSING with a start time
				$sql = "update redcap_data_import_rows set row_status = 'PROCESSING', start_time = ? where row_id = ?";
				db_query($sql, [date("Y-m-d H:i:s"), $row['row_id']]);
				// Clock how long it takes to import the record
				$start_time = microtime(true);
				// Add the data
				$params = array('project_id' => $row['project_id'], 'dataFormat' => 'csv', 'data' => $row['csv_header'] . "\n" . $row['row_data'], 'overwriteBehavior' => $row['overwrite_behavior'],
                                'changeReasons'=>$changeReasons, 'dateFormat' => $row['date_format'], 'addingAutoNumberedRecords' => ($row['force_auto_number'] == '1'),
                                'csvDelimiter' => ($row['delimiter'] == "TAB" ? "\t" : $row['delimiter']), 'loggingUser'=>"SYSTEM\n".$row['username'], 'group_id'=>$row['dag_id']);
                try {
                    $dataSuccess = Records::saveData($params);
                } catch (Throwable $e)  {
                    // Return any errors that occur
                    $dataSuccess = [];
                    $dataSuccess['errors'][] = $e->getMessage();
                }
				// Calculate total import time (rounded to milliseconds)
				$total_time = round((microtime(true)-$start_time)*1000);
				// If doing record auto-numbering, then add new record name to the row
				$record = ($row['force_auto_number'] == '1') ? ($dataSuccess['ids'][$row['record_provided']] ?? null) : $row['record_provided'];
                // Get error count
                $numErrors = is_array($dataSuccess) && is_array($dataSuccess['errors']) ? count($dataSuccess['errors']) : 1; // 'errors' should always be an array, so if not, there must be an error (set it to "1")
				// Set final status of this individual imported record
				if ($numErrors === 0) {
					// Successfully imported this row
					$sql = "update redcap_data_import_rows 
                            set row_status = 'COMPLETED', end_time = ?, record = ?, error_count = 0, total_time = ? 
                            where row_id = ?";
					db_query($sql, [date("Y-m-d H:i:s"), $record, $total_time, $row['row_id']]);
                    // Update records_imported in redcap_data_import (only do this every 10 records or if record took >15s to import)
                    if ($total_time > 15000 || $key % 10 === 0) {
                        self::updateRecordsImported($row['import_id']);
                    }
				} else {
					// Error
					$sql = "update redcap_data_import_rows 
                            set row_status = 'FAILED', end_time = ?, record = ?, error_count = ?, errors = ?, total_time = ? 
                            where row_id = ?";
					db_query($sql, [date("Y-m-d H:i:s"), $record, $numErrors, (is_array($dataSuccess['errors']) ? implode("\n", $dataSuccess['errors']) : $dataSuccess['errors']), $total_time, $row['row_id']]);
                    // Increment error count in redcap_data_import
                    $sql = "update redcap_data_import set total_errors = total_errors + ? where import_id = ?";
                    db_query($sql, [$numErrors, $row['import_id']]);
				}
				// Set for next loop
				$prev_import_id = $row['import_id'];
				$prev_records_provided = $row['records_provided'];
				$recordsProcessed++;
			}
			// LOOPING DONE: If a specific import batch has been completed, then mark as completed
            self::updateRecordsImported($prev_import_id);
			self::checkCompleteAsyncDataImport($prev_import_id, $prev_records_provided);
		}

		// Return the total number of records (not rows) that were imported
		return $recordsProcessed;
	}


    // Update records_imported in redcap_data_import
	public static function updateRecordsImported($import_id)
	{
        $sql = "update redcap_data_import 
                set records_imported = (select count(*) from redcap_data_import_rows where import_id = ? and row_status = 'COMPLETED')
                where import_id = ?";
        return db_query($sql, [$import_id, $import_id]);
    }


	// AJAX request to view a project's background imports
	public static function loadBackgroundImportsTable()
	{
		global $lang;
		$formatDecimal = User::get_user_number_format_decimal();
		$formatThousands = User::get_user_number_format_thousands_separator();
		// Get list of processes
		$sql = "select i.import_id, i.filename, u.username, i.upload_time, i.completed_time, i.total_processing_time, 
       			i.status, i.records_provided, i.records_imported, i.total_errors
				from redcap_data_import i 
				left join redcap_user_information u on i.user_id = u.ui_id
				where i.project_id = ?
				order by i.import_id desc";
		$q = db_query($sql, PROJECT_ID);
		$rows = [];
		while ($row = db_fetch_assoc($q))
		{
			// Format some things
			$import_id = $row['import_id'];
            $row['total_processing_time'] = strtotime(($row['completed_time'] == null) ? NOW : $row['completed_time']) - strtotime($row['upload_time']);
            $row['total_processing_time'] = round($row['total_processing_time']/60);
            if ($row['total_processing_time'] > 0) {
                $total_processing_time_display = number_format($row['total_processing_time'], 0, $formatDecimal, $formatThousands);
            } else {
                $total_processing_time_display = '< 1';
            }
            if ($row['status'] == 'FAILED'|| $row['status'] == 'CANCELED') {
                $total_processing_time_display = $row['total_processing_time'] = "";
            }
            $row['total_processing_time'] = ['sort'=>$row['total_processing_time'], 'display'=>$total_processing_time_display];
			$row['records_provided'] = ['sort'=>$row['records_provided'], 'display'=>number_format(($row['records_provided']??0), 0, $formatDecimal, $formatThousands)];
			$row['records_imported'] = ['sort'=>$row['records_imported'], 'display'=>number_format(($row['records_imported']??0), 0, $formatDecimal, $formatThousands)];
			$row['upload_time'] = ['sort'=>$row['upload_time'], 'display'=>"<div class='nowrap fs12'>" . DateTimeRC::format_user_datetime($row['upload_time'], 'Y-M-D_24') . "</div>"];
			$row['completed_time'] = ['sort'=>$row['completed_time'], 'display'=>"<div class='nowrap fs12'>" . DateTimeRC::format_user_datetime($row['completed_time'], 'Y-M-D_24') . "</div>"];
			$row['filename'] = "<div class='fs12' style='line-height:1.1;'>" . $row['filename'] . "</div>";
			$row['username'] = "<div class='fs12'>" . $row['username'] . "</div>";
            $status = $row['status'];
			if ($row['status'] == 'COMPLETED') {
				$row['status'] = "<div class='nowrap boldish text-successrc'><i class=\"fa-solid fa-check\"></i> {$lang['edit_project_207']}</div>";
			} elseif ($row['status'] == 'FAILED') {
				$row['status'] = "<div class='nowrap boldish text-dangerrc'><i class=\"fa-solid fa-circle-exclamation\"></i> {$lang['data_import_tool_337']}</div>";
			} elseif ($row['status'] == 'CANCELED') {
				$row['status'] = "<div class='nowrap boldish text-dangerrc'><i class=\"fa-solid fa-xmark\"></i> {$lang['scheduling_74']}</div>";
			} elseif ($row['status'] == 'PROCESSING') {
				$row['status'] = "<div class='nowrap boldish'><i class=\"fa-solid fa-spinner\"></i> {$lang['data_import_tool_338']}</div>";
			} elseif ($row['status'] == 'PAUSED') {
				$row['status'] = "<div class='nowrap boldish'><i class=\"fa-solid fa-pause\"></i> {$lang['data_import_tool_375']}</div>";
			} elseif ($row['status'] == 'INITIALIZING') {
				$row['status'] = "<div class='nowrap boldish'><i class=\"fa-solid fa-hourglass\"></i> {$lang['data_import_tool_383']}</div>";
			} else { // QUEUED
				$row['status'] = "<div class='nowrap boldish'><i class=\"fa-solid fa-pause\"></i> {$lang['data_import_tool_339']}</div>";
			}
            $row['status'] .= "<input id='import_id_$import_id' type='hidden' value='$import_id'>";
            $errorDisplay = RCView::div(['class'=>($row['total_errors'] > 0 ? 'boldish' : '')], number_format(($row['total_errors']??0), 0, $formatDecimal, $formatThousands));
            if ($status == 'PROCESSING' || $status == 'QUEUED') {
                // Still processing, so add a Cancel button
                $errorDisplay .= RCView::div(['class'=>'mt-1'], RCView::button(['class'=>'btn btn-xs fs11 btn-rcred nowrap px-1 py-0', 'onclick'=>"cancelBgImport($import_id);"], $lang['data_import_tool_390']));
            } elseif ($row['total_errors'] > 0) {
                // Done processing
                $errorDisplay .= RCView::div(['class'=>'mt-1'], RCView::button(['class'=>'btn btn-xs fs11 btn-rcgreen nowrap px-1 py-0', 'onclick'=>"viewBgImportDetails($import_id);"], $lang['data_import_tool_355']));
            }
            $row['total_errors'] = ['sort'=>$row['total_errors'], 'display'=>$errorDisplay];
			// Remove some non-displayable values
			unset($row['import_id']);
			// Add to row
			$rows[] = $row;
		}
        header('Content-Type: application/json');
		echo json_encode(['data' => $rows], JSON_PRETTY_PRINT);
	}


	// Render Data Import Tool page
	public static function renderDataImportToolPage()
	{
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			## PERFORMANCE: Kill any currently running processes by the current user/session on THIS page
			System::killConcurrentRequests(30);
		}

		extract($GLOBALS);

        if (isset($_GET['import_id']) && !isinteger($_GET['import_id'])) $_GET['import_id'] = '';
		
		// Increase memory limit in case needed for intensive processing
		System::increaseMemory(2048);

		renderPageTitle("<i class=\"fas fa-file-import me-1\"></i>".RCView::tt("app_01"));
		if (Design::isDraftPreview()) {
			print "<div class='yellow draft-preview-banner mt-2 mb-2'>
				<i class='fa-solid fa-triangle-exclamation text-danger draft-preview-icon me-2'></i>" .
				RCView::lang_i("draft_preview_16", [
					"<a style='color:inherit !important;' href='".APP_PATH_WEBROOT."Design/online_designer.php?pid=".PROJECT_ID."'>",
					"</a>"
				], false) . "
			</div>";
		}

        loadJS('DataImport.js');
        addLangToJS(['data_import_tool_394']);
		
		// Set extra set of reserved field names for survey timestamps and return codes pseudo-fields
		$extra_reserved_field_names = explode(',', implode("_timestamp,", array_keys($Proj->forms)) . "_timestamp"
							   . "," . implode("_return_code,", array_keys($Proj->forms)) . "_return_code");

		$doODM = (isset($_GET['type']) && $_GET['type'] == 'odm');
		$this_file = $_SERVER['REQUEST_URI'];
		$doMobileAppDataDump = (isset($_GET['doc_id']) && isset($_GET['doc_id_hash']));

		#Set official upload directory
		$upload_dir = APP_PATH_TEMP;
		if (!is_writeable($upload_dir)) {
			print "<br><br><div class='red'>
				<img src='".APP_PATH_IMAGES."exclamation.png'> <b>{$lang['global_01']}:</b><br>
				{$lang['data_import_tool_104']} <b>$upload_dir</b> {$lang['data_import_tool_105']}</div>";
			include APP_PATH_VIEWS . 'FooterProject.php';
			exit();
		}

		// Set parameters for saveData()
		$forceAutoNumber = ($auto_inc_set && isset($_REQUEST['forceAutoNumber']) && $_REQUEST['forceAutoNumber'] == '1');
		$overwriteBehavior = (isset($_REQUEST['overwriteBehavior']) && $_REQUEST['overwriteBehavior'] == 'overwrite') ? 'overwrite' : 'normal';
		$saveDataFormat = $doODM ? 'odm' : 'csv';
		$dateFormat = $doODM || !isset($_REQUEST['date_format']) ? 'YMD' : $_REQUEST['date_format'];
		if ($doODM) $_REQUEST['format'] = 'rows';

		// BACKGROUND CRON JOB: Perform the import via asynchronous cron job?
		if (isset($_POST['async']) && $_POST['async'] == '1')
		{
			# If Excel file, save the uploaded file (copy file from temp to folder) and prefix a timestamp to prevent file conflicts
			$uploadedfile_name = date('YmdHis') . "_" . $app_name . "_import_data.csv";
			$uploadedfile_name = str_replace("\\", "\\\\", $upload_dir . $uploadedfile_name);
			$uploadedfile_tmp_name = $_FILES['uploadedfile']['tmp_name'];
			# If moving or copying the uploaded file fails, print error message and exit
			if (!move_uploaded_file($uploadedfile_tmp_name, $uploadedfile_name))
			{
				if (!copy($uploadedfile_tmp_name, $uploadedfile_name))
				{
					print '<p><br><table width=100%><tr><td class="comp_new_error"><font color=#800000>' .
						"<b>{$lang['data_import_tool_48']}</b><br>{$lang['data_import_tool_49']} $project_contact_name " .
						"{$lang['global_15']} <a href=\"mailto:$project_contact_email\">$project_contact_email</a> {$lang['data_import_tool_50']}</b></font></td></tr></table>";
					include APP_PATH_VIEWS . 'FooterProject.php';
					exit;
				}
			}
            // Change reason provided?
            $change_reason = null;
            if ($require_change_reason) {
                if (isset($_POST['change_reason'])) {
                    $change_reason = $_POST['change_reason'];
                } else {
                    print "<p>{$lang['data_import_tool_408']}</p>";
                    include APP_PATH_VIEWS . 'FooterProject.php';
                    exit;
                }
            }
			// Add all rows of the CSV file to a db table
			$success = self::storeAsyncDataRows(trim(file_get_contents($uploadedfile_name)), $_REQUEST['delimiter'], $dateFormat, $overwriteBehavior, $forceAutoNumber, basename($_FILES['uploadedfile']['name']), $change_reason);
			$url = APP_PATH_WEBROOT . "index.php?pid=".PROJECT_ID."&route=DataImportController:index&import_id=&async_success=" . ($success ? "1" : "0");
			redirect($url);
		}

		// Display instructions when initially viewing the page but not after uploading a file
		if (!isset($_REQUEST['submit']) && !isset($_POST['updaterecs']))
		{
			//Print instructions
			print  "<div style='padding-right:10px;line-height:1.4em;max-width:850px;'>";

			//If user is in DAG, only show info from that DAG and give note of that
			$dagWarning = "";
			if ($user_rights['group_id'] != "") {
				$dagWarning = "<p style='color:#800000;'>{$lang['global_02']}{$lang['colon']} {$lang['data_import_tool_106']}</p>";
			}

			$devWarning = "";
			if ($status < 1) {
				$devRecordLimitText = "";
				if (isinteger($Proj->getMaxRecordCount()) && $Proj->getMaxRecordCount() < 10000) {
					$devRecordLimitText = RCView::tt_i('system_config_950', [Records::getRecordCount($Proj->project_id), $Proj->getMaxRecordCount()]);
				}
				$devWarning =  "<div class='yellow fs14 my-3'><i class='fa-solid fa-circle-exclamation me-1' style='color:#a83f00;'></i>".RCView::tt("data_entry_532")." $devRecordLimitText</div>";
			}

			print RCView::p(array(), $lang['data_import_tool_241']) . $dagWarning . $devWarning;
			// Tabs
			$tabs = array("index.php?route=DataImportController:index" =>	RCView::img(array('src'=>'csv.gif', 'style'=>'vertical-align:middle;')).
													RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_import_tool_242']),
						  "index.php?route=DataImportController:index&type=odm"=>RCView::img(array('src'=>'xml.png', 'style'=>'vertical-align:middle;')).
													RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_import_tool_243']),
						  "index.php?route=DataImportController:index&import_id=".($_GET['import_id'] ?? "")=>RCView::i(array('class'=>'fas fa-table me-1')).
													RCView::span(array('id'=>'view-bg-import-tab', 'style'=>'vertical-align:middle;'), $lang['data_import_tool_332']));
			RCView::renderTabs($tabs);


			// VIEW BACKGROUND IMPORTS
			if (isset($_GET['import_id']))
			{
				DataImport::renderBackgroundImportResults();
				include APP_PATH_VIEWS . 'FooterProject.php';
				exit;
			}

			// CDISC import
			elseif ($doODM)
			{
				print  "	<p style='font-size:14px;font-weight:bold;margin-top:0;'>
								{$lang['global_24']}{$lang['colon']}
							</p>
							<p>
								{$lang['data_import_tool_248']} {$lang['data_import_tool_249']}
							</p>
							<p>
								<font color=#800000>{$lang['data_import_tool_17']}</font>
								{$lang['data_import_tool_19']}
								{$lang['data_import_tool_22']}
							</p>";
			}

			// CSV import
			else
			{
				print  "	<p style='font-size:14px;font-weight:bold;margin-top:0;'>
								{$lang['global_24']}{$lang['colon']}
							</p>
							<div style='text-indent:-1.5em;margin:0 0 0.5em 2em;'>
								1.) {$lang['data_import_tool_319']}
								<a href='" . APP_PATH_WEBROOT . "index.php?pid=$project_id&route=DataImportController:downloadTemplate&format=rows' style='text-decoration:underline;' class='boldish'>{$lang['data_import_tool_04']}</a>{$lang['period']} {$lang['design_832']} 
								<a href='" . APP_PATH_WEBROOT . "index.php?pid=$project_id&route=DataImportController:downloadTemplate&format=rows&delimiter=semicolon' style='text-decoration:underline;' class='boldish'>{$lang['global_164']}</a>{$lang['comma']}
								<a href='" . APP_PATH_WEBROOT . "index.php?pid=$project_id&route=DataImportController:downloadTemplate&format=rows&delimiter=tab' style='text-decoration:underline;' class='boldish'>{$lang['global_163']}</a>
								{$lang['data_import_tool_320']} <a href='" . APP_PATH_WEBROOT . "index.php?pid=$project_id&route=DataImportController:downloadTemplate&format=cols' style='text-decoration:underline;'>{$lang['data_import_tool_321']}</a>{$lang['period']} {$lang['design_832']} 
								<a href='" . APP_PATH_WEBROOT . "index.php?pid=$project_id&route=DataImportController:downloadTemplate&format=cols&delimiter=semicolon' style='text-decoration:underline;'>{$lang['global_164']}</a>{$lang['comma']}
								<a href='" . APP_PATH_WEBROOT . "index.php?pid=$project_id&route=DataImportController:downloadTemplate&format=cols&delimiter=tab' style='text-decoration:underline;'>{$lang['global_163']}</a>{$lang['period']}
							</div>
							<div style='text-indent:-1.5em;margin:0 0 0.5em 2em;'>
								2.) {$lang['data_import_tool_323']} {$lang['data_import_tool_377']} {$lang['data_import_tool_11']} {$lang['data_import_tool_378']}
									<a href='" . APP_PATH_WEBROOT . "Design/data_dictionary_codebook.php?pid=$project_id' style='text-decoration:underline;'>{$lang['design_482']}</a>{$lang['period']}
							</div>
							<div style='text-indent:-1.5em;margin:0 0 0.5em 2em;'>
								3.) {$lang['data_import_tool_322']}
							</div>".
                            (!$Proj->hasRepeatingFormsEvents() ? "" :
                                "<div style='text-indent:-1.1em;margin:0 0 0.5em 2em;color:#C00000;'>
                                    <i class=\"far fa-lightbulb\" style='text-indent:0;'></i> {$lang['data_import_tool_296']}
                                </div>"
                            )."
						</div>";

				// HELP SECTION for using redcap_data_access_group and redcap_event_name
				// If DAGs exist and user is NOT in a DAG, then give instructions on how to use redcap_data_access_group field
				$dags = $Proj->getGroups();
				$canAssignDags = (!empty($dags) && $user_rights['group_id'] == "");
				if ($longitudinal || $canAssignDags)
				{
					$html = "";
					if ($canAssignDags) {
						$html .= RCView::div(array('style'=>'font-weight:bold;font-size:12px;color:#3E72A8;'),
									'<i class="far fa-lightbulb"></i> ' . $lang['data_import_tool_176']
								) .
								$lang['data_import_tool_177'] . RCView::SP .
								RCView::a(array('style'=>'text-decoration:underline;','href'=>APP_PATH_WEBROOT."index.php?route=DataAccessGroupsController:index&pid=$project_id"), $lang['global_22']) . " " . $lang['global_14'] . $lang['period'];
					}
					if ($longitudinal) {
						$html .= RCView::div(array('style'=>'font-weight:bold;font-size:12px;color:#3E72A8;'.($longitudinal && $canAssignDags ? 'margin-top:15px;' : '')),
								'<i class="far fa-lightbulb"></i> ' . $lang['data_import_tool_178']
								) .
								$lang['data_import_tool_179'] . RCView::SP .
								RCView::a(array('style'=>'text-decoration:underline;','href'=>APP_PATH_WEBROOT."Design/define_events.php?pid=$project_id"), $lang['global_16']) . " " . $lang['global_14'] . $lang['period'] . RCView::SP .
								$lang['data_import_tool_180'];
					}
					print RCView::div(array('style'=>'color:#333;background-color:#f5f5f5;border:1px solid #ccc;margin-top:15px;max-width:850px;padding:5px 8px 8px;'), $html);
				}
			}
		}


		## FILE UPLOAD FORM
		// CDISC import
		if ($doODM)
		{
			print  "<br><form action='$this_file' method='POST' name='form' enctype='multipart/form-data'>
					<div class='darkgreen' style='max-width:850px;padding:20px;'>
						<div id='uploadmain'>

							<div style='padding-bottom:8px;'>
								<b>{$lang['data_import_tool_231']}</b> 
								<select name='overwriteBehavior' class='x-form-text x-form-field ml-2' style='font-family:tahoma;padding-right:0;padding-top:0;height:22px;' onchange=\"
									if (this.value == 'normal') return;
									simpleDialog('".js_escape($lang['data_import_tool_236'])."','".js_escape($lang['survey_369'])."',null,null,function(){
										$('select[name=overwriteBehavior]').val('normal');
									},'".js_escape($lang['global_53'])."','','".js_escape($lang['rights_305'])."');
								\">
									<option value='normal' ".((!isset($_REQUEST['overwriteBehavior']) || $_REQUEST['overwriteBehavior'] == 'normal') ? "selected" : "").">{$lang['data_import_tool_245']}</option>
									<option value='overwrite' ".((isset($_REQUEST['overwriteBehavior']) && $_REQUEST['overwriteBehavior'] == 'overwrite') ? "selected" : "").">{$lang['data_import_tool_246']}</option>
								</select>
							</div>
							
							".(!$auto_inc_set ? "" : 
								"<div style='padding-bottom:3px;'>
									<b>{$lang['data_import_tool_386']}</b><a href='javascript:;' class='help' onclick=\"simpleDialog('".js_escape($lang['data_import_tool_388']." ".$lang['data_import_tool_389'])."','".js_escape($lang['data_import_tool_387'])."',null,550);\">?</a>&nbsp;
									<select name='forceAutoNumber' class='x-form-text x-form-field ml-2' style='font-family:tahoma;padding-right:0;padding-top:0;height:22px;'>
										<option value='0' ".((!isset($_REQUEST['forceAutoNumber']) || $_REQUEST['forceAutoNumber'] == '0') ? "selected" : "").">{$lang['data_import_tool_266']}</option>
										<option value='1' ".((isset($_REQUEST['forceAutoNumber']) && $_REQUEST['forceAutoNumber'] == '1') ? "selected" : "").">{$lang['data_import_tool_385']}</option>
									</select>
								</div>"
							)."

							<div style='padding-top:18px;font-weight:bold;padding-bottom:5px;'><img src='".APP_PATH_IMAGES."xml.png'>
								{$lang['data_import_tool_244']}
							</div>
							<input type='file' name='uploadedfile' size='50'>
							<div style='padding-top:5px;'>
								<input type='submit' id='submit' name='submit' value='{$lang['data_import_tool_20']}' onclick=\"
									if (document.forms['form'].elements['uploadedfile'].value.length < 1) {
										simpleDialog('".js_escape($lang['data_import_tool_114'])."');
										return false;
									}
									var file_ext = getfileextension(trim(document.forms['form'].elements['uploadedfile'].value.toLowerCase()));
									if (file_ext != 'xml') {
										simpleDialog('".js_escape($lang['data_import_tool_269'])."');
										return false;
									}
									document.getElementById('uploadmain').style.display='none';
									document.getElementById('progress').style.display='block';\">
							</div>
						</div>
						<div id='progress' style='display:none;background-color:#FFF;width:500px;border:1px solid #A5CC7A;color:#800000;'>
							<table cellpadding=10><tr>
							<td valign=top><img src='" . APP_PATH_IMAGES . "progress.gif'></td>
							<td valign=top style='padding-top:20px;'>
								<b>{$lang['data_import_tool_44']}</b><br>{$lang['data_import_tool_45']}<br>{$lang['data_import_tool_46']}</td>
							</tr></table>
						</div>
					</div>
					</form>";
		}

		// CSV import
		elseif (!$doMobileAppDataDump)
		{
			print RCView::script("
					$('[data-bs-toggle=\"popover\"]').hover(function(e) {
						// Show popup
						popover = new bootstrap.Popover(e.target, {
							html: true,
							title: $(this).data('title'),
							content: $(this).data('content')
						});
						popover.show();
					}, function() {
						// Hide popup
						bootstrap.Popover.getOrCreateInstance(this).dispose();
					});", true);
			print  "<br><form action='$this_file' method='POST' id='form' name='form' enctype='multipart/form-data'>
					<div class='darkgreen' style='max-width:850px;padding:20px;'>
						<div id='uploadmain'>
						
							<div style='padding-bottom:15px;'>
								<div class='d-inline-block' style='width:260px;'>
									<b><i class=\"fas fa-file-import fs14\" style='margin-right:1px;'></i> {$lang['data_import_tool_325']}</b>
								</div>
								<select id='async' name='async' class ='x-form-text x-form-field fs12' style='max-width:500px;' onchange=\"if(this.value=='1'){ $('#display_comparison_div').addClass('opacity35'); }else{ $('#display_comparison_div').removeClass('opacity35'); }\"> 
									<option value='0' selected>{$lang['data_import_tool_310']}</option>
									<option value='1'>{$lang['data_import_tool_311']}</option>
								</select>
								<a href='javascript:;' class='help' data-bs-toggle=\"popover\" data-bs-placement=\"right\" data-content=\"".js_escape2($lang['data_import_tool_309'])."\" data-title=\"".js_escape2($lang['data_import_tool_325'])."\">?</a>
							</div>
							
							<div style='padding-bottom:15px;'>
								<div class='d-inline-block' style='width:260px;'><b><i class=\"fa-solid fa-file-csv fs15\"></i> {$lang['data_import_tool_330']}</b></div>
								<input type='file' id='uploadedfile' name='uploadedfile' size='50'>
							</div>
						
							<div style='padding-bottom:15px;' id='display_comparison_div'>
								<div class='d-inline-block ".($Proj->project['require_change_reason'] == '1' ? "opacity50" : "")."' style='width:260px;'>
									<b><i class=\"fas fa-table-list fs14\" style='margin-right:1px;'></i> {$lang['data_import_tool_333']}</b>
								</div>
								<select name='display_comparison_table' class='x-form-text x-form-field fs12' style='max-width:500px;' ".($Proj->project['require_change_reason'] == '1' ? "disabled" : "")."> 
									<option value='0' ".((isset($_POST['display_comparison_table'])  && $_POST['display_comparison_table'] == '0') ? "selected" : "").">{$lang['data_import_tool_334']}</option>
									<option value='1' ".((!isset($_POST['display_comparison_table']) || $_POST['display_comparison_table'] == '1') ? "selected" : "").">{$lang['data_import_tool_335']}</option>
								</select>
								<a href='javascript:;' class='help' data-bs-toggle=\"popover\" data-bs-placement=\"right\" data-content=\"".js_escape2($lang['data_import_tool_336'].($Proj->project['require_change_reason'] == '1' ? " ".RCView::b($lang['data_import_tool_382']) : ""))."\" data-title=\"".js_escape2($lang['data_import_tool_333'])."\">?</a>
							</div>
							
							".(!$auto_inc_set ? "" :
							"<div style='padding-bottom:15px;'>
								<div class='d-inline-block' style='width:260px;'>
									<b><i class=\"fa-solid fa-arrow-down-1-9 fs15\"></i> {$lang['data_import_tool_386']}</b>
								</div>
								<select name='forceAutoNumber' class='x-form-text x-form-field fs12' style='max-width:500px;'>
									<option value='0' ".((!isset($_REQUEST['forceAutoNumber']) || $_REQUEST['forceAutoNumber'] == '0') ? "selected" : "").">{$lang['data_import_tool_266']}</option>
									<option value='1' ".((isset($_REQUEST['forceAutoNumber']) && $_REQUEST['forceAutoNumber'] == '1') ? "selected" : "").">{$lang['data_import_tool_385']}</option>
								</select>
								<a href='javascript:;' class='help' data-bs-toggle=\"popover\" data-bs-placement=\"right\" data-content=\"".js_escape2(RCView::b($lang['data_import_tool_387'])."<br><br>".$lang['data_import_tool_388']." ".$lang['data_import_tool_389'])."\" data-title=\"".js_escape2($lang['data_import_tool_386'])."\">?</a>
							</div>"
							)."

							<div style='padding-bottom:15px;'>
								<div class='d-inline-block' style='width:260px;'>
									<b><i class=\"fa-solid fa-eraser fs14\"></i> {$lang['data_import_tool_327']}</b>
								</div>
								<select name='overwriteBehavior' class='x-form-text x-form-field fs12' style='max-width:500px;' onchange=\"
									if (this.value == 'normal') return;
									simpleDialog('".js_escape($lang['data_import_tool_236'])."','".js_escape($lang['survey_369'])."',null,null,function(){
										$('select[name=overwriteBehavior]').val('normal');
									},'".js_escape($lang['global_53'])."','','".js_escape($lang['rights_305'])."');
								\">
									<option value='normal' ".((!isset($_REQUEST['overwriteBehavior']) || $_REQUEST['overwriteBehavior'] == 'normal') ? "selected" : "").">{$lang['data_import_tool_245']}</option>
									<option value='overwrite' ".((isset($_REQUEST['overwriteBehavior']) && $_REQUEST['overwriteBehavior'] == 'overwrite') ? "selected" : "").">{$lang['data_import_tool_246']}</option>
								</select>
								<a href='javascript:;' class='help' data-bs-toggle=\"popover\" data-bs-placement=\"right\" data-content=\"".js_escape2($lang['data_import_tool_331'])."\" data-title=\"".js_escape2($lang['data_import_tool_327'])."\">?</a>
							</div>

							<div>
								<div style='padding-bottom:3px;'>
									<b><i class=\"fa-solid fa-sliders fs14\" style='margin-right:1px;'></i> {$lang['data_import_tool_328']}</b> 
								</div>
								<div style='padding:1px 5px 3px 18px;'>									
									<div style='padding-bottom:3px;'>
										<div class='d-inline-block' style='width:244px;'>{$lang['data_import_tool_324']}</div>
										<select name='delimiter' class='x-form-text x-form-field fs12' style='max-width:500px;'> 
											<option value=',' selected>{$lang['global_162']}</option>
											<option value='\t'>{$lang['global_163']}</option>
											<option value=';'>{$lang['global_164']}</option>
										</select>
									</div>
		
									<div style='padding-bottom:3px;'>
										<div class='d-inline-block' style='width:244px;'>{$lang['data_import_tool_186']}</div>
										<select name='date_format' class='x-form-text x-form-field fs12' style='max-width:500px;'>
											<option value='MDY' ".((!isset($_REQUEST['date_format']) && DateTimeRC::get_user_format_base() != 'DMY') || (isset($_REQUEST['date_format']) && $_REQUEST['date_format'] == 'MDY') ? "selected" : "").">MM/DD/YYYY {$lang['global_47']} YYYY-MM-DD</option>
											<option value='DMY' ".((!isset($_REQUEST['date_format']) && DateTimeRC::get_user_format_base() == 'DMY') || (isset($_REQUEST['date_format']) && $_REQUEST['date_format'] == 'DMY') ? "selected" : "").">DD/MM/YYYY {$lang['global_47']} YYYY-MM-DD</option>
										</select>
									</div>
		
									<div style='padding-bottom:3px;'>
										<div class='d-inline-block' style='width:244px;'>{$lang['data_import_tool_329']}</div>
										<select name='format' class='x-form-text x-form-field fs12' style='max-width:500px;'>
											<option value='rows' ".((!isset($_REQUEST['format']) || $_REQUEST['format'] == 'rows') ? "selected" : "").">{$lang['data_import_tool_112']}</option>
											<option value='cols' ".(( isset($_REQUEST['format']) && $_REQUEST['format'] == 'cols') ? "selected" : "").">{$lang['data_import_tool_113']}</option>
										</select>
									</div>
								</div>
							</div>
							
							<div style='padding-top:15px;padding-left:16px;'>
								<button id='submit' name='submit' onclick='return submitBtnClick();' class='btn btn-xs btn-rcgreen fs15'><i class=\"fa-solid fa-cloud-arrow-up\"></i> {$lang['data_import_tool_20']}</button>
							</div>
						</div>

                        <script type='text/javascript'>
                        function submitBtnClick() {   
                            // Initial checking
                            if (document.forms['form'].elements['uploadedfile'].value.length < 1) {
                                simpleDialog('".js_escape($lang['data_import_tool_114'])."');
                                return false;
                            }
                            var file_ext = getfileextension(trim(document.forms['form'].elements['uploadedfile'].value.toLowerCase()));
                            if (file_ext != 'csv') {
                                simpleDialog(null,null,'filetype_mismatch_div');
                                return false;
                            }
                            if (!$('#change_reason').length && $('#async').val() == '1' && '{$Proj->project['require_change_reason']}' == '1') {
                                simpleDialog('<div class=\"mb-2\">".js_escape($lang['data_import_tool_380'])."</div><textarea id=\"change_reason_dlg\" class=\"x-form-textarea x-form-field\" style=\"width:95%;height:80px;\"></textarea>','".js_escape($lang['data_entry_69'])."',
                                    'change-reason-dialog',500,null,'".js_escape($lang['global_53'])."',function(){
                                        // Add reason to form
                                        var reason = $('#change_reason_dlg').val().trim();
                                        if (reason == '') {                                                
                                            alert('".js_escape($lang['data_import_tool_381'])."');
                                            setTimeout('submitBtnClick();',100);
                                            return false;
                                        }
                                        $('#form').append('<input type=\"hidden\" id=\"change_reason\" name=\"change_reason\" value=\"'+htmlspecialchars(reason)+'\">');
                                        $('#submit').trigger('click');
                                    },'".js_escape($lang['data_import_tool_20'])."');
                                return false;
                            } else {                                
                                // Begin upload
                                document.getElementById('uploadmain').style.display='none';
                                document.getElementById('progress').style.display='block';
                                $('#comptable, #center div.red:last, #center div.blue:last, #center div.green:last').remove();
                                $('#form').submit();
                                return true;
                            }
                        }
                        // Check if CSV data file is the correct one before uploading via background process
                        $(function(){
                            $('#async, #uploadedfile').change(function(){                           
                               var fileInput = document.getElementById('uploadedfile');
                               if (fileInput.value.length < 1) return;
                               var fileSize = fileInput.files[0].size/1024; // KB
                               if ($('#async').val() == '1') {
                                   // Background processing selected
                                   var fileReader = new FileReader();
                                   fileReader.readAsText(fileInput.files[0]);               
                                   fileReader.onload = function(e){
                                        if (e.target && e.target.result) {
                                            var file_ext = getfileextension(trim(fileInput.value.toLowerCase()));
                                            if (file_ext != 'csv') {
                                                simpleDialog(null,null,'filetype_mismatch_div');
                                                return false;
                                            }
                                            var maxDisplayLine = 70;
                                            var csvString = fileReader.result.toString();
                                            var posNL = csvString.indexOf(\"\\n\");
                                            var headers = csvString.slice(0, posNL).trim();
                                            var firstLine = csvString.slice(0, (posNL > maxDisplayLine ? maxDisplayLine : posNL)) + (posNL > maxDisplayLine ? '...' : '');
                                            csvString = trim(firstLine)+\"\\n\"+trim(csvString.slice(posNL, maxDisplayLine+posNL))+'...';
                                            if (fileSize > 1024) {
                                                fileSize = round(fileSize/1024, 2)+' MB';
                                            } else {
                                                fileSize = round(fileSize, 1)+' KB';
                                            }
                                            // Send the headers to the pre-check, just in case something is wrong                                            
                                            $.post(app_path_webroot+'index.php?route=DataImportController:fieldPreCheck&pid='+pid, { fields: headers }, function(data){
                                                if (data != '1') {
                                                    // Errors in header fields
                                                    simpleDialog(data,lang.global_01,'import-hdr-errors');
                                                    fitDialog($('#import-hdr-errors'));
                                                    // If error, deselect file
                                                    document.forms['form'].elements['uploadedfile'].value = '';
                                                } else {
                                                    // No errors, so display confirmation dialog
                                                    simpleDialog('<div class=\"mb-4\">'+lang.data_import_tool_343+'</div>'
                                                                +'<div class=\"mb-1\">'+lang.docs_19+' <span class=\"boldish text-dangerrc\">'+(fileInput.files[0].name)+'</span></div>'
                                                                +'<div class=\"mb-3\">'+lang.docs_57+' <span class=\"boldish text-dangerrc\">'+fileSize+'</span></div>'
                                                                +'<div class=\"mb-1 boldish\">'+lang.data_import_tool_345+'</span></div>'
                                                                +'<pre style=\"overflow-x:hidden;\">'+htmlspecialchars(csvString)+'</pre>',lang.data_import_tool_344,null,670,function(){
                                                        // If cancel, prompt user to choose another file
                                                        document.forms['form'].elements['uploadedfile'].value = '';
                                                        $('#uploadedfile').trigger('click');
                                                    },lang.global_53,'',lang.design_654);
                                                }
                                            }); 
                                       }
                                    }
                               } else if ($('#async').val() != '1' && fileSize > 1024) {
                                    // Real-time processing selected. If file >1MB, then ask to use background process.
                                    simpleDialog(lang.data_import_tool_362,lang.data_import_tool_363,null,600,null,lang.data_import_tool_365,function(){
                                        $('#async').val('1').effect('highlight',{},3000).trigger('change');
                                    },lang.data_import_tool_364);
                               }
                            });
                        });
                        </script>
                        
						<div id='progress' style='display:none;background-color:#FFF;width:500px;border:1px solid #A5CC7A;color:#800000;'>
							<table cellpadding=10><tr>
							<td valign=top><img src='" . APP_PATH_IMAGES . "progress.gif'></td>
							<td valign=top style='padding-top:20px;'>
								<b>{$lang['data_import_tool_44']}</b><br>{$lang['data_import_tool_45']}<br>{$lang['data_import_tool_46']}</td>
							</tr></table>
						</div>
					</div>
					</form>";
                    addLangToJS(['data_import_tool_343', 'data_import_tool_344', 'design_654', 'global_53', 'docs_57', 'docs_19', 'data_import_tool_345', 'data_import_tool_362', 'data_import_tool_363',
                                 'data_import_tool_364', 'data_import_tool_365','global_01']);
		}

		// Div for displaying popup dialog for file extension mismatch (i.e. if XLS or other)
		?>
		<br><br>
		<div id="filetype_mismatch_div" title="<?php echo js_escape2($lang['random_12']) ?>" style="display:none;">
			<p>
				<?php echo $lang['data_import_tool_160'] ?>
				<a href="https://support.office.com/en-us/article/Import-or-export-text-txt-or-csv-files-5250ac4c-663c-47ce-937b-339e391393ba" target="_blank"
					style="text-decoration:underline;"><?php echo $lang['data_import_tool_116'] ?></a>
				<?php echo $lang['data_import_tool_117'] ?>
			</p>
			<p>
				<b style="color:#800000;"><?php echo $lang['data_import_tool_110'] ?></b><br>
				<?php echo $lang['data_import_tool_118'] ?>
			</p>
		</div>
		<?php





		###############################################################################
		# This page has 3 states:
		# (1) plain page shows "browse..." textbox and upload button.
		# (2) 'submit' -- user has just uploaded an Excel file. page parses the file, validates the data, and displays an error table or a "Data is okay, do you want to commit?" button
		# (3) 'updaterecs' -- user has chosen to update records. page re-parses previously uploaded Excel file (to avoid passing SQL from page to page) and executes  SQL to update the project.
		###############################################################################

		# Check if a file has been submitted
		if (!isset($_REQUEST['updaterecs']) && isset($_REQUEST['submit']))
		{
			// Mobile App data dump
			$doc_id_file_name = "";
			if (isset($_REQUEST['doc_id']) && isset($_REQUEST['doc_id_hash']) && ($_REQUEST['doc_id_hash'] == Files::docIdHash($_REQUEST['doc_id'])))
			{
				// Copy the file into temp, which is where it is expected
				$doc_id_file_name = Files::copyEdocToTemp($_REQUEST['doc_id'], true, true);
			}
			
			// Uploading first time
			if (isset($_REQUEST['submit'])) {

				foreach ($_POST as $key=>$value) {
					$_POST[$key] = db_escape($value);
				}

				# Save the file details that are passed to the page in the _FILES array
				foreach ($_FILES as $fn=>$f) {
					$$fn = $f;
					foreach ($f as $k=>$v) {
						$name = $fn . "_" . $k;
						$$name = $v;
					}
				}

				if ($doc_id_file_name != "") {
					$uploadedfile_name = $doc_id_file_name;
				}

				# If filename is blank, reload the page
				if ($uploadedfile_name == "") {
					redirect(PAGE_FULL."?".$_SERVER['QUERY_STRING']);
					exit;
				}

				// Check the field extension
				$filetype = strtolower(substr($uploadedfile_name,strrpos($uploadedfile_name,".")+1,strlen($uploadedfile_name)));
				$msg = "";
				if ($doODM) {
					// If uploaded anything other than XML for ODM
					if ($filetype != "xml") $msg = RCView::tt("data_import_tool_305");
				} elseif (!$doODM && $filetype != "csv") {
					// If uploaded as XLSX or CSV, tell user to save as XLS and re-uploade
					if ($filetype == "xls" || $filetype == "xlsx") {
						$msg = RCView::tt("design_960");
					} else {
						$msg = RCView::tt("data_import_tool_306");
					}
				}
				if ($msg != "") {
					// Display error message
					print  '<div class="red" style="margin:30px 0;">
								<img src="'.APP_PATH_IMAGES.'exclamation.png"> '.$msg.'
							</div>';
					include APP_PATH_VIEWS . 'FooterProject.php';
					exit;
				}

				if (!$doMobileAppDataDump) 
				{
					# If Excel file, save the uploaded file (copy file from temp to folder) and prefix a timestamp to prevent file conflicts
					$uploadedfile_name = date('YmdHis') . "_" . $app_name . "_import_data." . $filetype;
					$uploadedfile_name = str_replace("\\", "\\\\", $upload_dir . $uploadedfile_name);
					# If moving or copying the uploaded file fails, print error message and exit
					if (!move_uploaded_file($uploadedfile_tmp_name, $uploadedfile_name))
					{
						if (!copy($uploadedfile_tmp_name, $uploadedfile_name))
						{
							print '<p><br><table width=100%><tr><td class="comp_new_error"><font color=#800000>' .
								 "<b>{$lang['data_import_tool_48']}</b><br>{$lang['data_import_tool_49']} $project_contact_name " .
								 "{$lang['global_15']} <a href=\"mailto:$project_contact_email\">$project_contact_email</a> {$lang['data_import_tool_50']}</b></font></td></tr></table>";
							include APP_PATH_VIEWS . 'FooterProject.php';
							exit;
						}
					}
				}
			}
			# Process uploaded Excel file
			if (!$doODM) {
				// Do not return anything from csvToArray() because it's only used for preliminary error checking
				DataImport::csvToArray($uploadedfile_name, $_REQUEST['format'], $_REQUEST['delimiter']);
			}
			if (!$doODM && $_REQUEST['format'] == 'cols') {
				$importData = DataImport::transposeCSV($uploadedfile_name, $_REQUEST['delimiter']);
			} else {
				$importData = removeBOM(file_get_contents($uploadedfile_name));
			}

			// Do test import to check for any errors/warnings
			$result = Records::saveData($saveDataFormat, $importData, $overwriteBehavior, $dateFormat, 'flat',
										$user_rights['group_id'], true, true, false, false, true, array(), true, !$doODM, false, $forceAutoNumber, null, ($_REQUEST['delimiter']??null));
			// Check if error occurred
			$warningcount = isset($result['warnings']) && is_array($result['warnings']) ? count($result['warnings']) : 0;
			$errorcount = isset($result['errors']) && is_array($result['errors']) ? count($result['errors']) : 0;
			$warnings = $errors = array();
			if ($errorcount > 0) {
				// Parse errors: Save as CSV file in order to parse it
				$filename = APP_PATH_TEMP . date('YmdHis') . "_csv_" . substr(sha1(rand()), 0, 6) . ".csv";
				file_put_contents($filename, implode("\n", $result['errors']));
				if (($handle = fopen($filename, "rb")) !== false) {
					while (($row = fgetcsv($handle, 0, ",", '"', '')) !== false) {
						$errors[] = $row;
					}
					fclose($handle);
				}
				unlink($filename);
			} elseif ($warningcount > 0) {
				// Parse warnings: Save as CSV file in order to parse it
				$filename = APP_PATH_TEMP . date('YmdHis') . "_csv_" . substr(sha1(rand()), 0, 6) . ".csv";
				file_put_contents($filename, implode("\n", $result['warnings']));
				if (($handle = fopen($filename, "rb")) !== false) {
					while (($row = fgetcsv($handle, 0, ",", '"', '')) !== false) {
						$warnings[] = $row;
					}
					fclose($handle);
				}
				unlink($filename);
			}

			// If there are any errors or warnings, display the table and message
			if (($errorcount + $warningcount) > 0)
			{
				// If any errors, automatically delete the uploaded file on the server.
				if ($errorcount > 0 && !$doMobileAppDataDump) {
					unlink($uploadedfile_name);
				}

				$usermsg = "<br>
							<div class='".($errorcount > 0 ? 'red' : 'yellow')."'>
								<img src='".APP_PATH_IMAGES."".($errorcount > 0 ? 'exclamation.png' : 'exclamation_orange.png')."'>
								<b>".($errorcount > 0 ? $lang['data_import_tool_51'] : $lang['data_import_tool_237'])."</b>";

				if ($errorcount + $warningcount > 1){
					$usermsg .= "<br><br>{$lang['data_import_tool_52']} ";
				} else {
					$usermsg .= "<br><br>{$lang['data_import_tool_53']} ";
				}

				if ($errorcount > 1){
					$usermsg .= $errorcount . " {$lang['data_import_tool_54']} {$lang['data_import_tool_56']} ";
				}else if ($errorcount == 1){
					$usermsg .= $errorcount . " {$lang['data_import_tool_41']} {$lang['data_import_tool_56']} ";
				}

				if (($errorcount > 0)&&($warningcount > 0)){
						$usermsg .= " {$lang['global_43']} ";
				}

				if ($warningcount > 1){
					$usermsg .= $warningcount . " {$lang['data_import_tool_58']} {$lang['data_import_tool_60']} ";
				}else if ($warningcount == 1){
					$usermsg .= $warningcount . " {$lang['data_import_tool_43']} {$lang['data_import_tool_60']} ";
				}

					$usermsg .= " {$lang['data_import_tool_61']} ";

					if ($errorcount > 0){
						$usermsg .= " {$lang['data_import_tool_376']}";
					} else {
						$usermsg .= " {$lang['data_import_tool_63']}";
					}

				$usermsg .= "</div><br>";
				print $usermsg;
				// Create the error/warning table to display (if any errors/warnings exist)
				print self::displayErrorTable($errors, $warnings);

			} else {
				//Display confirmation that file was uploaded successfully
				print  "<br>
						<div class='green' style='padding:10px 10px 13px;'>
							<img src='".APP_PATH_IMAGES."accept.png'>
							<b>{$lang['data_import_tool_24']}</b><br>
							{$lang['data_import_tool_24b']}<br>
						</div>";
			}


			### Instructions and Key for Data Display Table
			if ($errorcount == 0)
			{
				// Check for errors
				if (is_array($_REQUEST['format'])) $_REQUEST['format'] = "rows";
				if (!is_array($result) || !is_array($result['values'])) {
					print RCView::div(array('class'=>'red'), '<i class="fa-solid fa-triangle-exclamation"></i> '.$lang['data_import_tool_308']);
					exit;
				}

				// Render Data Display table
				$displayComparisonTable = !(isset($_POST['display_comparison_table']) && $_POST['display_comparison_table'] == '0');
				if ($displayComparisonTable)
				{
					print  "<div class='blue' style='font-size:12px;margin:25px 0;'>
								<b style='font-size:15px;'>{$lang['data_import_tool_102']}</b><br><br>
								{$lang['data_import_tool_25']}<br><br>
								<table style='background-color:#FFF;color:#000;font-size:11px;border:1px;'>
									<tr><th scope='row' class='comp_fieldname' style='background-color:#000;color:#FFF;font-size:11px;'>
										{$lang['data_import_tool_33']}
									</th></tr>
									<tr><td class='comp_update' style='background-color:#FFF;font-size:11px;'>
										{$lang['data_import_tool_35']} = {$lang['data_import_tool_36']}
									</td></tr>
									<tr><td class='comp_old' style='background-color:#FFF;font-size:11px;'>
										{$lang['data_import_tool_37']} = {$lang['data_import_tool_38']}
									</td></tr>
									<tr><td class='comp_old' style='font-size:11px;'>
										<span class='comp_oldval'>{$lang['data_import_tool_27']} = {$lang['data_import_tool_39']}</span>
									</td></tr>
								</table>
							</div>";
					print DataImport::displayComparisonTable($result['values'], $_REQUEST['format'], $doODM);
				}

				// Using jQuery, manually add "data change reason" text boxes for each record, if option is enabled
				if ($require_change_reason)
				{
					?>
					<script type="text/javascript">
                        var langCopy = '<?php echo $lang['data_import_tool_304'];?>';
					$(function(){

						// Set up functions and variables
						function renderReasonBox(record_count) {
							return "<td class='yellow' style='border:1px solid gray;width:210px;'>"
								 + "<textarea id='reason-"+record_count+"' onblur=\"charLimit('reason-"+record_count+"',200)\" class='change_reason x-form-textarea x-form-field' style='width:200px;height:60px;'></textarea>"
                                 + "<a class='btn btn-xs text-primaryrc text-end copy-to-all' onclick='javascript: copyReasonToAll(this);' style='float:right;' href='javascript:;'><small><i class='far fa-copy me-1'></i>"+langCopy+"<i class='fas fa-arrows-alt-v ms-1'></i></small></a></td>"
						}
						var reason_hdr = "<th class='yellow' style='color:#800000;border:1px solid gray;font-weight:bold;'><?php echo $lang['data_import_tool_132'] ?></th>";
						var new_rec_td = "<td class='comp_new'> </td>";
						var record_count = 1;

					<?php if (isset($_REQUEST['format']) && $_REQUEST['format'] == 'rows') {?>

                        var colspan = $('#comptable tr:first th').prop('colSpan');
						// Row data format
						$(".comp_recid").each(function() {
							var hasDataChanges = ($(this).find('div.exist_impt_rec').length && $(this).parent().find('.comp_new.form_has_data, .comp_update.form_has_data').length > 0);
							if (hasDataChanges) { // only for existing records with at least 1 value changed
							    $('#comptable tr:first th').attr('colspan', (colspan + 1));
								$(this).after(renderReasonBox(record_count));
							} else {
								$(this).after(new_rec_td);
							}
							record_count++;
						});
						$("#comptable").find('th').filter(':nth-child(2)').before(reason_hdr);

					<?php } else { ?>

						// Column data format
						var reasonRow = "";
						$(".comp_recid").each(function() {
							var hasDataChanges = ($(this).find('div.exist_impt_rec').length && $(this).parent().find('.comp_new.form_has_data, .comp_update.form_has_data').length > 0);
							reasonRow += hasDataChanges ? renderReasonBox(record_count) : new_rec_td; // only for existing records with at least 1 value changed
							record_count++;
						});
						var rows = document.getElementById('comptable').tBodies[0].rows;
						$(rows[1]).after("<tr>"+reason_hdr+reasonRow+"</tr>");

					<?php } ?>

					});
					</script>
					<?php
				}

				print  "<br><br>";

                $missingDataCodes = parseEnum($Proj->project['missing_data_codes']);
                $hasMissingDataCodes = !empty($missingDataCodes);

				// If ALL fields are old, then there's no need to update anything
				$field_counter = 0;
				$old_counter = 0;
				foreach ($result['values'] as $studyid => $studyrecord) {
					foreach ($studyrecord as $fieldname => $datapoint){
						if (isset($Proj->metadata[$fieldname]) && ($Proj->metadata[$fieldname]['element_type'] == 'calc' || (!$doODM && $Proj->metadata[$fieldname]['element_type'] == 'file' && !$hasMissingDataCodes))) {
							continue;
						}
						if ($datapoint['status'] == 'keep') {
							$old_counter++;
						}
						$field_counter++;
					}
				}
				if ($field_counter != $old_counter) 
				{
					$doc_id_text = "";
					if (isset($_REQUEST['doc_id']))
					{
						$doc_id_text = (($doc_id_name != "") ? "<input type='text' id='doc_id' name='doc_id' value='".$_REQUEST['doc_id']."'><input type='hidden' id='doc_id_hash' name='doc_id_hash' value='".Files::docIdHash($_REQUEST['doc_id'])."'>" : "");
					}
					// Button for committing to import
					print  "<div id='commit_import_div' class='darkgreen' style='max-width:850px;padding:20px;'>
								<form action='$this_file' method='post' id='form2' name='form2' enctype='multipart/form-data'>
								<div id='uploadmain2'>
									<b>{$lang['data_import_tool_66']}</b><br>{$lang['data_import_tool_67']}
									<input type='hidden' name='fname' value='".htmlspecialchars($uploadedfile_name, ENT_QUOTES)."'> " . $doc_id_text . "
									<input type='hidden' id='event_string' name='event_string' value='" . (isset($_POST['event_string']) ? $_POST['event_string'] : '') . "'>
									<input type='hidden' name='format' value='".((isset($_REQUEST['format']) && $_REQUEST['format'] == 'cols') ? "cols" : "rows")."'>
									<input type='hidden' name='date_format' value='".((isset($_REQUEST['date_format']) && $_REQUEST['date_format'] == 'DMY') ? "DMY" : "MDY")."'>
									<input type='hidden' name='overwriteBehavior' value='$overwriteBehavior'>
									<input type='hidden' name='forceAutoNumber' value='$forceAutoNumber'>
									<input type='hidden' name='updaterecs' value=''>
									
									<!-- additional parameter to keep track of the delimiter -->
									<input type='hidden' name='delimiter' value='".(isset($_REQUEST['delimiter']) ? $_REQUEST['delimiter'] : ",")."'> 
									<!-- end of changes -->

									<div style='padding-top:10px;'>
										<button name='updaterecs' class='btn btn-xs btn-rcgreen fs14' onclick='importDataSubmit($require_change_reason); return false;'>{$lang['data_import_tool_29']}</button>
										<a href='{$_SERVER['REQUEST_URI']}' style='margin-left:15px;'>{$lang['global_53']}</a>
									</div>
									<div id='change-reasons-div' style='display:none;'></div>
								</div>
								<div id='progress2' style='display:none;background-color:#FFF;width:500px;border:1px solid #A5CC7A;color:#800000;'>
									<table cellpadding=10><tr>
										<td valign=top>
											<img src='" . APP_PATH_IMAGES . "progress.gif'>
										</td>
										<td valign=top style='padding-top:20px;'>
											<b>{$lang['data_import_tool_64']}<br>{$lang['data_import_tool_65']}</b><br>
											{$lang['data_import_tool_46']}
										</td>
									</tr></table>
								</div>
								</form>
							</div>";

				} else {

					//Message saying that there are no new records (i.e. all the uploaded records already exist in project)
					//Button for committing to record import
					print  "<div id='commit_import_div' class='red' style='padding:20px;'>
								<img src='" . APP_PATH_IMAGES . "exclamation.png'>
								<b>{$lang['data_import_tool_68']}</b><br>";
								if (isset($_REQUEST['doc_id'])) {
									print $lang['mobile_app_117']."<br><br>".RCView::a(array("href"=>APP_PATH_WEBROOT."MobileApp/index.php?files=1&pid=".PROJECT_ID), $lang['mobile_app_115']);
								} else {
									print $lang['data_import_tool_69'];
								}
					print "</div>";

					//Delete the uploaded file from the server since its data cannot be imported
					unlink($uploadedfile_name);
				}

			}

			print "<br><br><br>";

		}









		/**
		 * USER CLICKED "IMPORT DATA" BUTTON
		 */
		elseif (isset($_REQUEST['updaterecs']))
		{
			// If submitted "change reason" then reconfigure as array with record as key to add to logging.
			$change_reasons = array();
			if ($require_change_reason && isset($_POST['records']) && isset($_POST['events']))
			{
                // Check for reasons submitted
                if (empty($_POST['reasons'] ?? [])) {
                    print "<p class='red'>{$lang['data_import_tool_408']}</p>";
                    include APP_PATH_VIEWS . 'FooterProject.php';
                    exit;
                }
				foreach ($_POST['records'] as $this_key=>$this_record)
				{
					$event_id = $_POST['events'][$this_key];
                    $thisReason = $_POST['reasons'][$this_key] ?? "";
                    if ($thisReason == "") {
                        // Reason is missing for this record
                        print "<p class='red'>{$lang['data_import_tool_408']}</p>";
                        include APP_PATH_VIEWS . 'FooterProject.php';
                        exit;
                    }
					$change_reasons[$this_record][$event_id] = $thisReason;
				}
				unset($_POST['records'],$_POST['reasons'],$_POST['events']);
			}

			// Process uploaded Excel file
			$uploadedfile_name = $_POST['fname'];

			// Set parameters for saveData()
			$forceAutoNumber = ($auto_inc_set && isset($_REQUEST['forceAutoNumber']) && $_REQUEST['forceAutoNumber'] == '1');
			$overwriteBehavior = (isset($_REQUEST['overwriteBehavior']) && $_REQUEST['overwriteBehavior'] == 'overwrite') ? 'overwrite' : 'normal';
			$saveDataFormat = $doODM ? 'odm' : 'csv';
			$dateFormat = $doODM ? 'YMD' : $_REQUEST['date_format'];
			if ($doODM) $_REQUEST['format'] = 'rows';

            # Process uploaded Excel file
            if (!$doODM) {
                // Do not return anything from csvToArray() because it's only used for preliminary error checking
                DataImport::csvToArray($uploadedfile_name, $_REQUEST['format'], $_REQUEST['delimiter']);
            }
            if (!$doODM && $_REQUEST['format'] == 'cols') {
                $importData = DataImport::transposeCSV($uploadedfile_name, $_REQUEST['delimiter']);
            } else {
                $importData = file_get_contents($uploadedfile_name);
            }

            // Do test import to check for any errors/warnings
			$result = Records::saveData($saveDataFormat, $importData, $overwriteBehavior, $dateFormat, 'flat',
										$user_rights['group_id'], true, true, true, false, true, $change_reasons, false, !$doODM, false, $forceAutoNumber, false, $_REQUEST["delimiter"]);

			// Count records added/updated
			$numRecordsImported = count($result['ids']);

			// Delete the uploaded file from the server now that its data has been imported
			unlink($uploadedfile_name);

			// Give user message of successful import
			print  "<br><br>
					<div class='green' style='padding-top:10px;'>
						<img src='".APP_PATH_IMAGES."accept.png'> <b>{$lang['data_import_tool_133']}</b>
						<span style='font-size:16px;color:#800000;margin-left:8px;margin-right:1px;font-weight:bold;'>".User::number_format_user($numRecordsImported)."</span>
						<span style='color:#800000;'>".($numRecordsImported == '1' ? $lang['data_import_tool_183'] : $lang['data_import_tool_184'])."</span>
						<br><br>";
			if (isset($_REQUEST['doc_id'])) {
				print $lang['mobile_app_116']."<br><br>".RCView::a(array("href"=>APP_PATH_WEBROOT."MobileApp/index.php?files=1&pid=".PROJECT_ID), $lang['mobile_app_115']);
			} else {
				print $lang['data_import_tool_70'];
			}
			if ($forceAutoNumber && !empty($result['ids'])) {
				print "<br><br>".$lang['data_import_tool_270']."<br>";
				print "<table id='comptable' style='background-color:#fff;margin-top:10px;'>";
				print "<tr><td class='comp_new'><b>".$lang['data_import_tool_271']."</b></th><td class='comp_new'><b>".$lang['data_import_tool_272']."</b></th></tr>";
				foreach ($result['ids'] as $origId=>$savedId) {
					print "<tr><td class='comp_new'>$origId</td><td class='comp_new'>$savedId</td></tr>";
				}
				print "</table>";
			}
			print "</div>";

		}
	}

    // Cancel a background import (only possible if user is the uploader)
	public static function cancelBackgroundImport($import_id, $action='view')
	{
        if (!isinteger($import_id)) exit('0');
        if (!self::currentUserIsAsyncUploader($import_id) && !UserRights::isSuperUserNotImpersonator()) {
            exit('2');
        } elseif ($action == 'view') {
            exit('1');
        } elseif ($action == 'save') {
            exit(self::cancelBackgroundImportAction($import_id) ? '1' : '0');
        } else {
            exit('0');
        }
    }

    // Cancel a background import
	public static function cancelBackgroundImportAction($import_id, $cancelTime=null)
	{
        if (!isinteger($import_id)) return false;
        $sql = "update redcap_data_import 
                set status = 'CANCELED', completed_time = ?
                where import_id = ? and status in ('INITIALIZING','QUEUED','PROCESSING')";
        if ($cancelTime == null) $cancelTime = date("Y-m-d H:i:s");
        $q = db_query($sql, [$cancelTime, $import_id]);
        if ($q) {
            $sql = "update redcap_data_import_rows
                    set row_status = 'CANCELED'
                    where import_id = ? and row_status not in ('COMPLETED', 'FAILED')";
            return db_query($sql, [$import_id]);
        }
        return false;
    }

    // View the details of a background import
	public static function viewBackgroundImportDetails($import_id)
	{
        if (!isinteger($import_id)) exit('ERROR');
        $isUploaderOrAdmin = (self::currentUserIsAsyncUploader($import_id) || UserRights::isSuperUserNotImpersonator());
        $btnDisabled = $isUploaderOrAdmin ? "" : "disabled";
        $importAttr = self::getAsyncDataImportAttr($import_id);
        $html = "<div>
					".RCView::tt_i('data_import_tool_358', [$importAttr['total_errors'],  number_format($importAttr['records_provided']-$importAttr['records_imported'], 0, User::get_user_number_format_decimal(), User::get_user_number_format_thousands_separator())])."
				 </div>";
        $html .= "<div class='text-center mt-4'>
                    <button class='btn btn-sm btn-primaryrc' onclick=\"window.location.href=app_path_webroot+'index.php?route=DataImportController:downloadBackgroundErrors&import_id=$import_id&pid='+pid;\" $btnDisabled><i class=\"fs14 fa-solid fa-file-arrow-down\"></i> ".RCView::tt('data_import_tool_370')."</button>
                  </div>";
        $html .= "<div class='text-center mt-3'>
                    <button class='btn btn-sm btn-rcgreen' onclick=\"window.location.href=app_path_webroot+'index.php?route=DataImportController:downloadBackgroundErrorData&import_id=$import_id&pid='+pid;\" $btnDisabled><i class=\"fs14 fa-solid fa-file-csv\"></i> ".RCView::tt('data_import_tool_371')."</button>
                  </div>";
        if (!$isUploaderOrAdmin) {
            $html .= "<div class='red mt-4 mb-2'>
                        <i class=\"fa-solid fa-circle-exclamation\"></i> ".RCView::tt('data_import_tool_372')."</button>
                      </div>";
        }
        print $html;
    }


    // Download errors from a background import
    public static function downloadBackgroundErrors($import_id)
    {
        if (!isinteger($import_id) || (!self::currentUserIsAsyncUploader($import_id) && !UserRights::isSuperUserNotImpersonator())) exit('ERROR');
        global $lang;
        // Get attributes of the import
        $importAttr = self::getAsyncDataImportAttr($import_id);
        // Get delimiter supplied by the user
        $origDelimiter = $importAttr['delimiter'];
        if ($origDelimiter == 'TAB') $origDelimiter = "\t";
        // CSV file
        $timeImported = str_replace([' ','-',':'], ['_','',''], $importAttr['upload_time']);
        $filename = "DataImportErrors".$import_id."_PID".PROJECT_ID."_".$timeImported.".csv";
        // Open connection to create file in memory and write to it
        $fp = fopen('php://memory', "x+");
        $headers = array(($importAttr['force_auto_number'] ? $lang['data_import_tool_373'] : $lang['global_49']), $lang['global_44'], $lang['data_import_tool_99'], $lang['data_import_tool_100']);
        // Headers
        fputcsv($fp, $headers, $origDelimiter, '"', '');
        // Get the errors from the database
        $sql = "select r.errors, r.error_count from redcap_data_import_rows r, redcap_data_import i
                where i.import_id = ? and i.import_id = r.import_id and r.row_status = 'FAILED'
                order by r.row_id";
        $q = db_query($sql, $import_id);
        $lines = [];
        while ($row = db_fetch_assoc($q)) {
            // Split up into separate rows
            if ($row['error_count'] > 1) {
                foreach (explode("\n", $row['errors']) as $thisRow) {
                    $lines[] = trim($thisRow);
                }
            } else {
                $lines[] = $row['errors'];
            }
        }
        foreach ($lines as $line) {
            // Convert from always-comma CSV to user-defined CSV
            $line = explode(",", $line, 4);
            // Remove quotes around each part, if needed
            foreach ($line as &$thisline) {
                $firstChar = substr($thisline, 0, 1);
                if ($firstChar == '"' || $firstChar == "'") {
                    $thisline = substr($thisline, 1, -1);
                }
            }
            // Add row
            fputcsv($fp, $line, $origDelimiter, '"', '');
        }
        // Open file for reading and output to user
        fseek($fp, 0);
        // Logging
        Logging::logEvent("", "redcap_data_import", "MANAGE", $import_id, "import_id = " . $import_id, "Download error file for background data import");
        // Output to file
        header('Pragma: anytextexeptno-cache', true);
        header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=$filename");
        print addBOMtoUTF8(stream_get_contents($fp));
    }


    // Download the CSV data for the records that failed to import from a background import due to errors
    public static function downloadBackgroundErrorData($import_id)
    {
        if (!isinteger($import_id) || (!self::currentUserIsAsyncUploader($import_id) && !UserRights::isSuperUserNotImpersonator())) exit('ERROR');
        global $lang;
        // Get attributes of the import
        $importAttr = self::getAsyncDataImportAttr($import_id);
        // CSV file
        $timeImported = str_replace([' ','-',':'], ['_','',''], $importAttr['upload_time']);
        $filename = "DataImport".$import_id."_PID".PROJECT_ID."_".$timeImported.".csv";
        // Get headers provided by the user
        $csvString = $importAttr['csv_header'];
        // Get the data from the database
        $sql = "select r.row_data from redcap_data_import_rows r, redcap_data_import i
                where i.import_id = ? and i.import_id = r.import_id and r.row_status != 'COMPLETED'
                order by r.row_id";
        $q = db_query($sql, $import_id, null, MYSQLI_USE_RESULT); // Use unbuffered query method
        while ($row = db_fetch_assoc($q)) {
            $csvString .= "\n" . $row['row_data'];
        }
        // Logging
        Logging::logEvent("", "redcap_data_import", "MANAGE", $import_id, "import_id = " . $import_id, "Download data file of failed records for background data import");
        // Output to file
        header('Pragma: anytextexeptno-cache', true);
        header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=$filename");
        print addBOMtoUTF8($csvString);
    }

	// Render page for Background Import Results
	public static function renderBackgroundImportResults()
	{
        addLangToJS(['data_import_tool_346','data_import_tool_347','data_import_tool_348','data_import_tool_349','data_import_tool_350','data_import_tool_351','data_import_tool_352',
                     'data_import_tool_353','data_import_tool_354','data_import_tool_390','data_import_tool_357','data_import_tool_391','data_import_tool_366','data_import_tool_393',
                     'data_import_tool_368','global_79','data_import_tool_369','data_import_tool_392','email_users_112','data_import_tool_374']);
		$html = loadJS('DataImportBackground.js', false);
        // Display success if BACKGROUND UPLOAD WAS A SUCCESS
        if (isset($_GET['async_success']))
        {
            if ($_GET['async_success'] == '1') {
                $userEmail = User::getUserInfo(USERID)['user_email'];
                $html .=   "<div id='async_success_dialog' class='simpleDialog' title='".RCView::tt_js('data_import_tool_340')."'>
                            <div class='darkgreen'><i class=\"fa-solid fa-check\"></i> <span style='font-size:14px;'>".RCView::tt("data_import_tool_313")." ".
                            RCView::a(['href'=>'mailto:'.$userEmail, 'style'=>'text-decoration:underline;'], $userEmail)." ".RCView::tt("data_import_tool_314")."</span></div></div>";
            } else {
                $html .= "<div id='async_success_dialog' class='simpleDialog' title='".RCView::tt_js('global_01')."'>
						  <div class='red'><i class=\"fa-solid fa-check\"></i> <span style='font-size:14px;'>".RCView::tt("data_import_tool_312")."</span></div></div>";
            }
        }
        // Display the progress table
		$html .= "<div id='background-import-table-parent'>
					<table id='background-import-table'></table>
				 </div>";
		print $html;
	}

    // Check the fields in the CSV headers prior to the background import official import
    public static function fieldPreCheck($fields)
    {
        global $Proj, $lang;
        // Remove any single/double quotes from the header field list
        $fields = str_replace(['"', "'"], '', $fields);
        // Get delimiter
        $delim = null;
        if (strpos($fields, ",") !== false) {
            $delim = ",";
        } elseif (strpos($fields, ";") !== false) {
            $delim = ";";
        } elseif (strpos($fields, "\t") !== false) {
            $delim = "\t";
        }
        if ($delim == null) {
            // Single field (record id)
            $fields = [$fields];
        } else {
            // Multiple fields
            $fields = explode($delim, $fields);
        }
        // Set extra set of reserved field names for survey timestamps
        $reserved_field_names = array("redcap_event_name", "redcap_survey_timestamp", "redcap_survey_identifier", "redcap_data_access_group", "redcap_repeat_instance", "redcap_repeat_instrument");
        $reserved_field_names = array_merge($reserved_field_names, explode(',', implode("_timestamp,", array_keys($Proj->forms)) . "_timestamp"));
        // Get all fields in export version of their names
        foreach (REDCap::getExportFieldNames() as $field) {
            if (is_array($field)) {
                foreach ($field as $field2) {
                    $reserved_field_names[] = $field2;
                }
            } else {
                $reserved_field_names[] = $field;
            }
        }
        // Validate the fields (ignore any file upload fields uploaded since they'll automatically be ignored anyway)
        $invalidFields = [];
        foreach ($fields as $field) {
            if (!in_array($field, $reserved_field_names) && $Proj->metadata[$field]['element_type'] != 'file') {
                $invalidFields[] = RCView::escape($field, false);
            }
        }
        // If invalid fields exist, return them as CSV delimited, else return "1"
        print empty($invalidFields) ? "1" : "<div class='mb-3'>{$lang['data_import_tool_379']}</div><ul class='fs12'><li>".implode("</li><li>", $invalidFields)."</li></ul>";
    }
}