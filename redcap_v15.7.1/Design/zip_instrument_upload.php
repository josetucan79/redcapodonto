<?php


// Only accept Post submission
if ($_SERVER['REQUEST_METHOD'] != 'POST') exit;

// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Reference list of valid image files that can be used as inline image for Descriptive fields
$image_file_ext = array('png', 'gif', 'jpg', 'jpeg', 'bmp');

// Get file attributes
$doc_name = strtolower(str_replace("'", "", html_entity_decode(stripslashes($_FILES['myfile']['name']), ENT_QUOTES)));
$doc_size = $_FILES['myfile']['size'];
$tmp_name = $_FILES['myfile']['tmp_name'];
if (empty($tmp_name)) exit;

// Check if file is larger than max file upload limit
if (($doc_size/1024/1024) > maxUploadSize() || (isset($_FILES['file']) && $_FILES['file']['error'] != UPLOAD_ERR_OK))
{
	// Give error response
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.alert('<?php echo "ERROR: CANNOT UPLOAD FILE!" ?>');
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

// Upload the file and access it via ZipArchive
$zip = new ZipArchive;
$res = $zip->open($tmp_name);
if ($res !== TRUE) {
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape($lang['random_13']) ?>',null,null,350);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

// Give error response if not a zip file
if (substr($doc_name, -4) != '.zip') {
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape($lang['design_537']) ?>',null,null,350);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

// Get instrument.csv
$instrumentDD = $zip->getFromName('instrument.csv');
if ($instrumentDD === false) {
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape($lang['design_538']) ?>',null,null,350);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}
$surveyCSV = $zip->getFromName('survey_settings.csv');
$instrumentDDextraCSV = $zip->getFromName('instrument_extra.csv');
// Obtain OriginID, AuthorID, Survey Settings, and InstrumentID (if available)
$OriginID = $zip->getFromName('OriginID.txt');
$AuthorID = $zip->getFromName('AuthorID.txt');
$InstrumentID = $zip->getFromName('InstrumentID.txt');
$myCapTaskCSV = $zip->getFromName('mycap_task_settings.csv');

// Get correct table we're using, depending on if in production
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
$surveys_table = "redcap_surveys";

## PROCESS THE "DATA DICTIONARY" FOR THE INSTRUMENT
// Store DD in the temp directory so we can read it
$dd_filename = APP_PATH_TEMP . date('YmdHis') . '_instrumentdd_' . $project_id . '_' . substr(sha1(rand()), 0, 6) . '.csv';
file_put_contents($dd_filename, $instrumentDD);
// Parse DD
$dd_array = Design::excel_to_array($dd_filename);
unlink($dd_filename);
// If DD returns false, then it's because of some unknown error
if ($dd_array === false || $dd_array == "") {
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape($lang['random_13']) ?>',null,null,350);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

// Obtain any attachments in the "attachments" or "survey_attachments" directory
$attachments_list = $attachments_index_list = array();
$attachments_dir = "attachments";
$surveyAttachments_list = $surveyAttachments_index_list = array();
$surveyAttachments_dir = "survey_attachments";
// Only survey settings directories possible
$validSurveySettingsDirectories = array("confirmation_email_attachment","logo");
for ($i = 0; $i < $zip->numFiles; $i++) {
	// Set full filename and base filename
    $this_file = $zip->getNameIndex($i);
	$this_file_base = basename($this_file);
	// Make sure the file is in the attachments dir
	if (substr($this_file, 0, strlen($attachments_dir)) == $attachments_dir) {
        // Make sure the sub-dir is a valid field in the DD
        $this_field = basename(dirname($this_file));
        if (!in_array($this_field, $dd_array['A'])) continue;
        // Add file to array list
        $attachments_list[$this_field] = $this_file_base;
        $attachments_index_list[$this_field . "/" . $this_file_base] = $i;
    }
	elseif (substr($this_file, 0, strlen($surveyAttachments_dir)) == $surveyAttachments_dir) {
	    $this_type = basename(dirname($this_file));
	    if (!in_array($this_type,$validSurveySettingsDirectories)) continue;
	    $surveyAttachments_list[$this_type] = $this_file_base;
	    $surveyAttachments_index_list[$this_type."/".$this_file_base] = $i;
    }
}

// Find any variables that are duplicated in the DD
$duplicate_fields = array();
if (isset($dd_array['A'])) {
    foreach ($dd_array['A'] as &$val) {
        if (!is_numeric($val) && !is_string($val)) $val = ""; // Ensure that array_count_values() doesn't fail if anything other than INTs and STRINGs are in array
    }
	foreach (array_count_values($dd_array['A']) as $this_field => $this_count) {
		if ($this_count < 2) continue;
		$duplicate_fields[] = $this_field;
	}
}
if (!empty($duplicate_fields)) {
	$msg = $lang['design_541'] . " \"<b>" . implode("</b>\", \"<b>", $duplicate_fields) . "</b>\"" . $lang['period'];
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape($msg) ?>',null,null,450);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

// Make sure that all the form names are the same and that it is unique
$current_forms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;
$unique_forms_new = isset($dd_array['B']) ? array_count_values($dd_array['B']) : array();
unset($unique_forms_new['']);
$unique_forms_new = array_keys($unique_forms_new);
// Set new unique form name
$this_form = preg_replace("/[^a-z0-9_]/", "", str_replace(" ", "_", strtolower($unique_forms_new[0])));
// Remove any double underscores, beginning numerals, and beginning/ending underscores
while (strpos($this_form, "__") !== false) 		$this_form = str_replace("__", "_", $this_form);
while (substr($this_form, 0, 1) == "_") 		$this_form = substr($this_form, 1);
while (substr($this_form, -1) == "_") 			$this_form = substr($this_form, 0, -1);
while (is_numeric(substr($this_form, 0, 1))) 	$this_form = substr($this_form, 1);
while (substr($this_form, 0, 1) == "_") 		$this_form = substr($this_form, 1);
// Cannot begin with numeral and cannot be blank
if (is_numeric(substr($this_form, 0, 1)) || $this_form == "") {
	$this_form = substr(preg_replace("/[0-9]/", "", md5($this_form)), 0, 4) . $this_form;
}
// Make sure it's less than 64 characters long
$this_form = substr($this_form, 0, 64);
while (substr($this_form, -1) == "_") $this_form = substr($this_form, 0, -1);

// Ensure uniqueness of the form
$form_exists = isset($current_forms[$this_form]);
while ($form_exists) {
	// Make sure no longer than 100 characters and append alphanums to end
	$this_form = substr(str_replace("__", "_", $this_form), 0, 94) . "_" . substr(sha1(rand()), 0, 6);
	// Does new form already exist?
	$form_exists = (isset($current_forms[$this_form]));
}
// Set all fields with single form name (just in case)
foreach ($dd_array['B'] as $key=>$this_field) {
	$dd_array['B'][$key] = $this_form;
}

// Array to capture renamed field names
$renamed_fields = array();
// Check for any variable name collisions and modify any if there are duplicates
$current_metadata = ($status == 0 ? $Proj->metadata : $Proj->metadata_temp);
foreach ($dd_array['A'] as $key=>$this_field) {
	// Set original field name
	$this_field_orig = $this_field;
	// If exists in project or is duplicated in the DD itself, then generate a new variable name
	$field_exists = isset($current_metadata[$this_field]);
	while ($field_exists) {
		// Make sure no longer than 100 characters and append alphanums to end
		$this_field = substr(str_replace("__", "_", $this_field), 0, 94) . "_" . substr(sha1(rand()), 0, 6);
		// Does new field exist in existing fields or new fields being added?
		$field_exists = (isset($current_metadata[$this_field]) || in_array($this_field, $dd_array['A']));
		// Add to array
		if (!$field_exists) {
			$renamed_fields[$this_field] = $this_field_orig;
		}
	}
	// Change field name in array
	$dd_array['A'][$key] = $this_field;
}

// Loop through all fields to change branching and calcs if field is used in those
if (!empty($renamed_fields)) {
	// Loop through calc fields
	foreach ($dd_array['F'] as $key=>$this_calc) {
		// Is field a calc field?
		if ($dd_array['D'][$key] != 'calc') continue;
		// Replace any renamed fields in equation (via looping)
		foreach ($renamed_fields as $this_field_new => $this_field_orig) {
			// If doesn't contain the field, then skip
			if (strpos($this_calc, "[$this_field_orig]") === false && strpos($this_calc, "[$this_field_orig(") === false) continue;
			// Replace field
			$dd_array['F'][$key] = $this_calc = str_replace("[$this_field_orig]", "[$this_field_new]", $this_calc);
			$dd_array['F'][$key] = $this_calc = str_replace("[$this_field_orig(", "[$this_field_new(", $this_calc); // checkboxes
		}
	}
	// Loop through branching logic
	foreach ($dd_array['L'] as $key=>$this_branching) {
		// If has no branching, then skip
		if ($this_branching == '') continue;
		// Replace any renamed fields in equation (via looping)
		foreach ($renamed_fields as $this_field_new => $this_field_orig) {
			// If doesn't contain the field, then skip
			if (strpos($this_branching, "[$this_field_orig]") === false && strpos($this_branching, "[$this_field_orig(") === false) continue;
			// Replace field
			$dd_array['L'][$key] = $this_branching = str_replace("[$this_field_orig]", "[$this_field_new]", $this_branching);
			$dd_array['L'][$key] = $this_branching = str_replace("[$this_field_orig(", "[$this_field_new(", $this_branching); // checkboxes
		}
	}
	// Loop through Action Tags/Field Annotation
	foreach ($dd_array['R'] as $key=>$this_item) {
		// If has no branching, then skip
		if ($this_item == '') continue;
		// Replace any renamed fields in equation (via looping)
		foreach ($renamed_fields as $this_field_new => $this_field_orig) {
			// If doesn't contain the field, then skip
			if (strpos($this_item, "[$this_field_orig]") === false && strpos($this_item, "[$this_field_orig(") === false) continue;
			// Replace field
			$dd_array['R'][$key] = $this_item = str_replace("[$this_field_orig]", "[$this_field_new]", $this_item);
			$dd_array['R'][$key] = $this_item = str_replace("[$this_field_orig(", "[$this_field_new(", $this_item); // checkboxes
		}
	}
	// Loop through attachment list of fields to confirm the field names
	foreach ($attachments_list as $this_field=>$this_file) {
		// Confirm the field exists first
		$array_key = array_search($this_field, $dd_array['A']);
		// If false, then check if it was renamed
		if ($array_key === false) {
			// Confirm the field exists in the renamed list of fields
			$renamed_array_key = array_search($this_field, $renamed_fields);
			if ($renamed_array_key === false) continue;
			// Set the key as the new renamed field, removing old one
			$attachments_list[$renamed_array_key] = $attachments_list[$this_field];
			unset($attachments_list[$this_field]);
			// Also redo the zip archive index number for this file
			$attachments_index_list[$renamed_array_key."/".$this_file] = $attachments_index_list[$this_field."/".$this_file];
			unset($attachments_index_list[$this_field."/".$this_file]);
		}
	}
}

// PREVENT MATRIX GROUP NAME DUPLICATION: Rename any matric group names that already exist in project to prevent duplication
$matrix_group_name_fields = ($status > 0) ? $Proj->matrixGroupNamesTemp : $Proj->matrixGroupNames;
$matrix_group_names = array_keys($matrix_group_name_fields);
$matrix_group_names_transform = array();
// Loop through fields being imported to find matrix group names
foreach ($dd_array['P'] as $this_mgn) {
	// Get matrix group name, if exists for this field
	if ($this_mgn == '') continue;
	$this_mgn_orig = $this_mgn;
	// Does matrix group name already exist?
	$mgn_exists = (in_array($this_mgn, $matrix_group_names) && !isset($matrix_group_names_transform[$this_mgn]));
	$mgn_renamed = false;
	while ($mgn_exists) {
		// Rename it
		// Make sure no longer than 50 characters and append alphanums to end
		$this_mgn = substr($this_mgn, 0, 43) . "_" . substr(sha1(rand()), 0, 6);
		// Does new field exist in existing fields or new fields being added?
		$mgn_exists = (in_array($this_mgn, $matrix_group_names) && !isset($matrix_group_names_transform[$this_mgn]));
		$mgn_renamed = true;
	}
	// Add to transform array
	if ($mgn_renamed) {
		$matrix_group_names_transform[$this_mgn_orig] = $this_mgn;
	}
}
// Loop through fields being imported to rename matrix group names
foreach ($dd_array['P'] as $key=>$this_mgn) {
	if (isset($matrix_group_names_transform[$this_mgn])) {
		$dd_array['P'][$key] = $matrix_group_names_transform[$this_mgn];
	}
}

// Return warnings and errors from file (and fix any correctable errors)
list ($errors_array, $warnings_array, $dd_array) = MetaData::error_checking($dd_array, true);
// Set up all actions as a transaction to ensure everything is done here
db_query("SET AUTOCOMMIT=0");
db_query("BEGIN");
// Save data dictionary in metadata table
$sql_errors = (empty($errors_array)) ? MetaData::save_metadata($dd_array, true) : [];
if (!empty($errors_array) || count($sql_errors) > 0) {
	// ERRORS OCCURRED, so undo any changes made
	db_query("ROLLBACK");
	// Set back to previous value
	db_query("SET AUTOCOMMIT=1");
	// Display error messages
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape(RCView::div(array('style'=>'font-weight:bold;font-size:14px;color:#C00000;margin-bottom:15px;'), $lang['random_13']). implode("</div><div style='margin:8px 0;'>", $errors_array) . ((SUPER_USER && count($sql_errors) > 0) ? "SQL errors (for super users only):<br>" . implode("</div><div style='margin:8px 0;'>", $sql_errors) : "")) ?>',null,null,500);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

## PROCESS THE "Survey Settings" FOR THE INSTRUMENT
// Database column names for redcap_surveys to be mapped from CSV
// Store in the temp directory so we can read it
$ss_filename = APP_PATH_TEMP . date('YmdHis') . '_surveysettings_' . $project_id . '_' . substr(sha1(rand()), 0, 6) . '.csv';
file_put_contents($ss_filename, $surveyCSV);

// Extract data from CSV file and rearrange it in a temp array
$delimiter = ",";
$surveysettings_columns = array();
$surveysettings_values = array();
$i = 1;
$removeQuotes = false;

if (($handle = fopen($ss_filename, "rb")) !== false)
{
    // Loop through each row
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false)
    {
        // Skip row 1
        if ($i == 1)
        {
            $surveysettings_columns = $row;
            // Get the array key of the survey "theme"
            $themeKey = array_search("theme", $surveysettings_columns);
            if (!isinteger($themeKey)) $themeKey = 31; // Default to use key "31"
            $i++;
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
            // Validate survey theme id
            if ($j == $themeKey && isinteger($row[$j])) {
                // If theme id does not exist, set to blank/null
                $sql = "select 1 from redcap_surveys_themes where theme_id = '".db_escape($row[$j])."'";
                if (!db_num_rows(db_query($q))) {
                    $row[$j] = "";
                }
            }
            // Add to array
            $surveysettings_values[$j] = $row[$j];
            // Use only for Japanese SJIS encoding
            if ($project_encoding == 'japanese_sjis')
            {
                $surveysettings_values[$j] = mb_convert_encoding($surveysettings_values[$j], 'UTF-8',  'sjis');
            }
        }
        $i++;
    }
    fclose($handle);
} else if($surveyCSV !== "") {
    // ERROR: File is missing
    $fileMissingText = (!SUPER_USER) ? $lang['period'] : " (".APP_PATH_TEMP."){$lang['period']}<br><br>{$lang['file_download_13']}";
    print 	RCView::div(array('class'=>'red'),
        RCView::b($lang['global_01'].$lang['colon'])." {$lang['file_download_08']} <b>\"".htmlspecialchars(basename($ss_filename), ENT_QUOTES)."\"</b>
                        {$lang['file_download_12']}{$fileMissingText}"
    );
    exit;
}
unlink($ss_filename);

// Set up survey settings for imported instrument
if (!empty($surveysettings_columns)) {
    if (!$surveys_enabled) {
        $sql = "update redcap_projects set surveys_enabled = '1' where project_id = $project_id";
        db_query($sql);
    }

    // Loop through survey settings attachment list
    $surveyEdocs = array();
    foreach ($surveyAttachments_list as $this_field=>$this_file) {
        // Extract file from zip
        $zip_index = $surveyAttachments_index_list[$this_field . "/" . $this_file];
        if (!is_numeric($zip_index)) continue;
        $this_filename = $zip->getNameIndex($zip_index);
        // Get file extension
        $this_file_ext = getFileExt($this_file);
        // If a URL file extension, then add as video_url attribute

        // Copy to temp from zip file
        $fp = $zip->getStream($this_filename);
        if (!$fp) continue;
        $temp_name = APP_PATH_TEMP . date('YmdHis') . "_pid" . PROJECT_ID . "_zipattachment{$zip_index}_" . substr(sha1(rand()), 0, 6) . "." . $this_file_ext;
        $ofp = fopen($temp_name, 'w');
        while (!feof($fp)) fwrite($ofp, fread($fp, 8192));
        fclose($fp);
        fclose($ofp);
        // Set file attributes
        $mime_type = (in_array(strtolower($this_file_ext), $image_file_ext)) ? "image/" . strtolower($this_file_ext) : "application/octet-stream";
        $file_attr = array('size' => filesize($temp_name), 'type' => $mime_type, 'name' => $this_file, 'tmp_name' => $temp_name);
        // Upload file to edocs directory
        $edoc_id = Files::uploadFile($file_attr);
        unlink($temp_name);
        if (!is_numeric($edoc_id)) continue;
        $surveyEdocs[$this_field] = $edoc_id;
    }

    // Set up insertion SQL statement based on which columns have values to import
    $sqlColsVals = $sqlColsValsUpdate = [];
    $sqlColsVals['project_id'] = $project_id;
    $sqlColsVals['form_name'] = $this_form;
    foreach ($validSurveySettingsDirectories as $surveyDirectory) {
        if (isset($surveyEdocs[$surveyDirectory]) && is_numeric($surveyEdocs[$surveyDirectory])) {
			$sqlColsVals[$surveyDirectory] = $surveyEdocs[$surveyDirectory];
			$sqlColsValsUpdate[] = "$surveyDirectory = ".checkNull($surveyEdocs[$surveyDirectory]);
        }
    }
    foreach ($surveysettings_columns as $column => $surveysettings_column) {
		$sqlColsVals[$surveysettings_column] = $surveysettings_values[$column];
		$sqlColsValsUpdate[] = "$surveysettings_column = ".checkNull($surveysettings_values[$column]);
    }
    // Save survey info
	$sql = "insert into redcap_surveys (".implode(', ', array_keys($sqlColsVals)).") values (".prep_implode($sqlColsVals, true, true).")
	        on duplicate key update ".implode(', ', $sqlColsValsUpdate);
    if (!db_query($sql)) {
        error_log($sql);
        exit("An error occurred. Please try again.");
    }
    $survey_id = db_insert_id();
}

## ADD ANY ATTACHMENTS
// Loop through attachment list of fields
foreach ($attachments_list as $this_field=>$this_file) {
	// Extract file from zip
	$zip_index = $attachments_index_list[$this_field."/".$this_file];
	if (!is_numeric($zip_index)) continue;
	$this_filename = $zip->getNameIndex($zip_index);
	// Get file extension
	$this_file_ext = getFileExt($this_file);
	// If a URL file extension, then add as video_url attribute
	if (strtolower($this_file_ext) == 'url') {
		// Get file contents
		$this_url_file_content = trim(str_replace(array("[InternetShortcut]\r\nURL=","[InternetShortcut]\rURL=","[InternetShortcut]\nURL="),
								 array("","",""),
								 $zip->getFromIndex($zip_index)));
		// Now add video_url to the field
		$sql = "update $metadata_table set video_url = '".db_escape($this_url_file_content)."', video_display_inline = 0
				where project_id = " . PROJECT_ID . " and field_name = '$this_field'";
		db_query($sql);
	}
	// If an edoc, then upload edoc file to field
	else {
		// Copy to temp from zip file
		$fp = $zip->getStream($this_filename);
		if (!$fp) continue;
		$temp_name = APP_PATH_TEMP . date('YmdHis') . "_pid" . PROJECT_ID . "_zipattachment{$zip_index}_" . substr(sha1(rand()), 0, 6) . "." . $this_file_ext;
		$ofp = fopen($temp_name, 'w');
		while (!feof($fp)) fwrite($ofp, fread($fp, 8192));
		fclose($fp);
		fclose($ofp);
		// Set file attributes
		$mime_type = (in_array(strtolower($this_file_ext), $image_file_ext)) ? "image/".strtolower($this_file_ext) : "application/octet-stream";
		$file_attr = array('size'=>filesize($temp_name), 'type'=>$mime_type, 'name'=>$this_file, 'tmp_name'=>$temp_name);
		// Upload file to edocs directory
		$edoc_id = Files::uploadFile($file_attr);
		unlink($temp_name);
		if (!is_numeric($edoc_id)) continue;
		// Is the file an image? If so, set as inline image automatically
		$edoc_display_img = (in_array(strtolower($this_file_ext), $image_file_ext)) ? 1 : 0;
		// Now add edoc_id to the field
		$sql = "update $metadata_table set edoc_id = $edoc_id, edoc_display_img = $edoc_display_img
				where project_id = " . PROJECT_ID . " and field_name = '$this_field'";
		db_query($sql);
	}
}

// Add any Stop Actions or the inline attribute for video URLs
if ($instrumentDDextraCSV != false)
{
    $instrumentDDextra = csvToArray($instrumentDDextraCSV);
    foreach ($instrumentDDextra as $attr) {
        // IF field was renamed, get new name
        if (in_array($attr['field_name'], $renamed_fields)) {
            $attr['field_name'] = array_search($attr['field_name'], $renamed_fields);
        }
        // Verify the stop action codings
        if ($attr['stop_actions'] == "") {
            unset($attr['stop_actions']);
        } else {
            $stop_actions = explode(",", $attr['stop_actions']);
            $dd_array_field_key = array_search($attr['field_name'], $dd_array['A']);
            if ($dd_array_field_key != false) {
                $enum = parseEnum(str_replace("|", "\\n", $dd_array['F'][$dd_array_field_key]));
                $stop_actions_verified = [];
                foreach ($stop_actions as $stop_action) {
                    if (isset($enum[$stop_action])) {
                        $stop_actions_verified[] = $stop_action;
                    }
                }
                if (empty($stop_actions_verified)) {
                    unset($attr['stop_actions']);
                } else {
                    $attr['stop_actions'] = implode(",", $stop_actions_verified);
                }
            }
        }
        // Verify the inline video URL attribute
        if ($attr['video_url'] == "" || !($attr['video_display_inline'] == '0' || $attr['video_display_inline'] == '1')) {
            unset($attr['video_display_inline']);
        }
        unset($attr['video_url']);
        // Add the attributes to the field
        if (count($attr) > 1)
        {
            $field_name = $attr['field_name'];
            unset($attr['field_name']);
            $subsql = [];
            foreach ($attr as $thiskey=>$thisval) {
                $subsql[] = "$thiskey = '".db_escape($thisval)."'";
            }
            $subsql = implode(", ", $subsql);
            $sql = "update $metadata_table set $subsql
                    where project_id = " . PROJECT_ID . " and field_name = '".db_escape($field_name)."'";
            db_query($sql);
        }
    }
}

$mycap_settings_text = "";
if ($myCapTaskCSV != "") {
    ## PROCESS THE "MyCap Task Settings" FOR THE INSTRUMENT
    // Database column names for redcap_mycap_tasks to be mapped from CSV
    // Store in the temp directory so we can read it
    $mycap_filename = APP_PATH_TEMP . date('YmdHis') . '_mycaptasks_' . $project_id . '_' . substr(sha1(rand()), 0, 6) . '.csv';
    file_put_contents($mycap_filename, $myCapTaskCSV);

    // Extract data from CSV file and rearrange it in a temp array
    $delimiter = ",";
    $tasksettings_columns = array();
    $tasksettings_values = array();
    $i = 1;
    $removeQuotes = false;

    if (($handle = fopen($mycap_filename, "rb")) !== false)
    {
        // Loop through each row
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false)
        {
            // Skip row 1
            if ($i == 1)
            {
                $tasksettings_columns = $row;
                $i++;
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
                $tasksettings_values[$j] = $row[$j];
                // Use only for Japanese SJIS encoding
                if ($project_encoding == 'japanese_sjis')
                {
                    $tasksettings_values[$j] = mb_convert_encoding($tasksettings_values[$j], 'UTF-8',  'sjis');
                }
            }
            $i++;
        }
        fclose($handle);
    } else {
        // ERROR: File is missing
        $fileMissingText = (!SUPER_USER) ? $lang['period'] : " (".APP_PATH_TEMP."){$lang['period']}<br><br>{$lang['file_download_13']}";
        print 	RCView::div(array('class'=>'red'),
            RCView::b($lang['global_01'].$lang['colon'])." {$lang['file_download_08']} <b>\"".htmlspecialchars(basename($mycap_filename), ENT_QUOTES)."\"</b>
                        {$lang['file_download_12']}{$fileMissingText}"
        );
        exit;
    }
    unlink($mycap_filename);

    // Set up task settings for imported instrument
    if (!empty($tasksettings_columns)) {
        // Set up insertion SQL statement based on which columns have values to import
        $sqlColsVals = $sqlColsValsUpdate = $sqlScheduleColsVals = $sqlScheduleColsValsUpdate = [];
        $sqlColsVals['project_id'] = $project_id;
        $sqlColsVals['form_name'] = $this_form;
        $sqlColsVals['enabled_for_mycap'] = 1;

        $i = 0;
        foreach ($tasksettings_columns as $column => $tasksettings_column) {
            $i++;
            if ($i <= 7) { // Take first 7 columns to insert into redcap_mycap_tasks DB table (as extended_config_json moved at task level from event level)
                $sqlColsVals[$tasksettings_column] = $tasksettings_values[$column];
                $sqlColsValsUpdate[] = "$tasksettings_column = ".checkNull($tasksettings_values[$column]);
            } else { // Take remaining columns to insert into redcap_mycap_tasks_schedules DB table
                $sqlScheduleColsVals[$tasksettings_column] = $tasksettings_values[$column];
                $sqlScheduleColsValsUpdate[] = "$tasksettings_column = ".checkNull($tasksettings_values[$column]);
            }
        }
        // Save task info
        $sql = "INSERT INTO redcap_mycap_tasks (".implode(', ', array_keys($sqlColsVals)).") values (".prep_implode($sqlColsVals, true, true).")
	        on duplicate key update ".implode(', ', $sqlColsValsUpdate);
        if (!db_query($sql)) {
            exit("An error occurred. Please try again.");
        }
        $task_id = db_insert_id();

        if (!empty($sqlScheduleColsVals) && !$Proj->longitudinal) {
            $sqlScheduleColsVals['task_id'] = $task_id;
            $sqlScheduleColsVals['event_id'] = $Proj->firstEventId;
            // Save task schedules info
            $sql = "INSERT INTO redcap_mycap_tasks_schedules (".implode(', ', array_keys($sqlScheduleColsVals)).") values (".prep_implode($sqlScheduleColsVals, true, true).")
	        on duplicate key update ".implode(', ', $sqlScheduleColsValsUpdate);
            if (!db_query($sql)) {
                exit("An error occurred. Please try again.");
            }
        }

        if (!$Proj->isRepeatingForm($Proj->firstEventId, $this_form)) {
            // Make this form as repeatable with default eventId as project is classic
            $sql = "INSERT INTO redcap_events_repeat (event_id, form_name) 
                VALUES ({$Proj->firstEventId}, '" . db_escape($this_form) . "')";
            db_query($sql);
        }
    }

    if (!$mycap_enabled) {
        $mycap_settings_text = "<br>".$lang['mycap_mobile_app_706'];
    }
}

// COMMIT CHANGES
db_query("COMMIT");
// Set back to previous value
db_query("SET AUTOCOMMIT=1");

// Add OriginID, AuthorID, and InstrumentID to db tables if we have them
if ($OriginID !== false && $OriginID != '') {
	// Add OriginID to table and increment its count
	$sql = "insert into redcap_instrument_zip_origins (server_name) values ('".db_escape($OriginID)."')
			on duplicate key update upload_count = upload_count + 1";
	db_query($sql);
}
if ($AuthorID !== false && $AuthorID != '') {
	// Get iza_id of AuthorID
	$sql = "select iza_id from redcap_instrument_zip_authors where author_name = '".db_escape($AuthorID)."'";
	$q = db_query($sql);
	if (db_num_rows($q) == 0) {
		$sql = "insert into redcap_instrument_zip_authors (author_name) values ('".db_escape($AuthorID)."')";
		db_query($sql);
		$iza_id = db_insert_id();
	} else {
		$iza_id = db_result($q, 0);
	}
	// Add InstrumentID to table and increment its count
	if ($InstrumentID !== false && $InstrumentID != '') {
		$sql = "insert into redcap_instrument_zip (iza_id, instrument_id) values ($iza_id, '".db_escape($InstrumentID)."')
				on duplicate key update upload_count = upload_count + 1";
		db_query($sql);
	}
}

// Do logging of file upload
Logging::logEvent("",$metadata_table,"MANAGE",$this_form,"form_name = '$this_form'","Create data collection instrument (via instrument ZIP file)");

// Delete temp file
if (file_exists($tmp_name)) @unlink($tmp_name);

// Create text if there were any renamed fields (to inform the user)
$renamed_fields_text = "";
if (!empty($renamed_fields)) {
	$renamed_fields_text = RCView::div(array('style'=>'line-height:14px;margin-bottom:5px;'), RCView::b($lang['global_03'].$lang['colon']) . " " . $lang['design_565']);
	foreach ($renamed_fields as $new_field=>$old_field) {
		$renamed_fields_text .= RCView::div(array('style'=>'margin:1px 0 1px 10px;line-height:12px;'), "- \"<b>$old_field</b>\" {$lang['design_566']} \"<b>$new_field</b>\"");
	}
	$renamed_fields_text = RCView::div(array('style'=>'height:100px;overflow-y:scroll;margin:20px 0 0;padding:5px;border:1px solid #ccc;'), $renamed_fields_text);
}

// Give response using javascript
?>
<script language="javascript" type="text/javascript">
window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
window.parent.window.document.getElementById('div_zip_instrument_success').style.display = 'block';
var renamed_fields_text = '<?php print js_escape($renamed_fields_text) ?>';
var mycap_settings_text = '<?php print js_escape($mycap_settings_text) ?>';
window.parent.window.reloadPageOnCloseZipPopup(renamed_fields_text, mycap_settings_text);
if (renamed_fields_text.length == 0) {
	window.parent.window.setTimeout(function(){
		window.parent.window.location.href = window.parent.window.app_path_webroot+'Design/online_designer.php?pid='+window.parent.window.pid;
	},2500);
}
</script>
