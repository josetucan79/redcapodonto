<?php


// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Set up all actions as a transaction to ensure everything is done here
db_query("SET AUTOCOMMIT=0");
db_query("BEGIN");

// Get correct table we're using, depending on if in production
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

// Clean affix string
$_POST['affix'] = preg_replace("/[^a-z0-9_]/", "", $_POST['affix']);
$affixLength = strlen($_POST['affix']);

// Set old form name
$old_form_name = $_POST['page'];

// Make sure that all the form names are the same and that it is unique
$current_forms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;
if (!isset($current_forms[$old_form_name]) || $_POST['affix'] == '' || $_POST['form_label'] == '') exit("0");

// Are we copying the first form?
$current_forms_values = array_keys($current_forms);
$copyingFirstForm = ($old_form_name == $current_forms_values[0]);

// Set new unique form name
$new_form_name = preg_replace("/[^a-z0-9_]/", "", str_replace(" ", "_", strtolower(html_entity_decode($_POST['form_label'], ENT_QUOTES))));
// Remove any double underscores, beginning numerals, and beginning/ending underscores
while (strpos($new_form_name, "__") !== false) 		$new_form_name = str_replace("__", "_", $new_form_name);
while (substr($new_form_name, 0, 1) == "_") 		$new_form_name = substr($new_form_name, 1);
while (substr($new_form_name, -1) == "_") 			$new_form_name = substr($new_form_name, 0, -1);
while (is_numeric(substr($new_form_name, 0, 1))) 	$new_form_name = substr($new_form_name, 1);
while (substr($new_form_name, 0, 1) == "_") 		$new_form_name = substr($new_form_name, 1);
// Cannot begin with numeral and cannot be blank
if (is_numeric(substr($new_form_name, 0, 1)) || $new_form_name == "") {
	$new_form_name = substr(preg_replace("/[0-9]/", "", md5($new_form_name)), 0, 4) . $new_form_name;
}
// Make sure it's less than 50 characters long
$new_form_name = substr($new_form_name, 0, 50);
while (substr($new_form_name, -1) == "_") $new_form_name = substr($new_form_name, 0, -1);
// Make sure this form value doesn't already exist
$formExists = (isset($current_forms[$new_form_name]));
while ($formExists) {
	// Make sure it's less than 64 characters long
	$new_form_name = substr($new_form_name, 0, 45);
	// Append random value to form_name to prevent duplication
	$new_form_name .= "_" . substr(sha1(rand()), 0, 4);
	// Try again
	$formExists = (isset($current_forms[$new_form_name]));
}

// Obtain fields for the form being copied
$form_fields = array_keys($current_forms[$old_form_name]['fields']);
if ($copyingFirstForm) {
	// If copying first form, remove the Record ID field first
	unset($form_fields[0]);
}

// Obtain the data ditionary for THIS FORM as an array
$dd_array_raw = MetaData::getDataDictionary('array', false, $form_fields, array(), false, ($status > 0 && $draft_mode > 0));

// Array to capture renamed field names
$renamed_fields = array();
// Convert metadata array to Excel-looking array with letters as array keys
$dd_array = array();
foreach ($dd_array_raw as $this_item) {
	$item = 0;
	foreach ($this_item as $key=>$value) {
		// If field_name, then append affix
		if ($item == 0) {
			$orig_field = $value;
			if (strlen($value) + $affixLength > 100) {
				$value = substr($value, 0, 100-$affixLength) . $_POST['affix'];
			} else {
				$value .= $_POST['affix'];
			}
			$renamed_fields[$orig_field] = $value;
		}
		// Determine letter
		$letter = strtoupper(chr(97+$item++));
		// Add item to array (start at key 2)
		if (empty($dd_array[$letter])) {
			$dd_array[$letter][2] = $value;
		} else {
			$dd_array[$letter][] = $value;
		}
	}
}

// Set new form name
foreach ($dd_array['B'] as $key=>$value) {
	$dd_array['B'][$key] = $new_form_name;
}

// Check for any variable name collisions and modify any if there are duplicates
$current_metadata = ($status == 0 ? $Proj->metadata : $Proj->metadata_temp);
$renamed_fields2 = array();
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
			// Get original original field
			$this_field_orig_orig = array_search($this_field_orig, $renamed_fields);
			if ($this_field_orig_orig !== false) {
				$renamed_fields[$this_field_orig_orig] = $this_field;
				$renamed_fields2[$this_field_orig] = $this_field;
			}
		}
	}
	// Change field name in array
	$dd_array['A'][$key] = $this_field;
}


// Loop through calc fields
foreach ($dd_array['F'] as $key=>$this_calc) {
	// Is field a calc field?
	if ($dd_array['D'][$key] != 'calc') continue;
	// Replace any renamed fields in equation (via looping)
	foreach ($renamed_fields as $this_field_orig=>$this_field_new) {
		// If doesn't contain the field, then skip
		if (strpos($this_calc, "[$this_field_orig]") !== false) {
			// Replace field
			$dd_array['F'][$key] = $this_calc = str_replace("[$this_field_orig]", "[$this_field_new]", $this_calc);
		}
		// If doesn't contain the field, then skip
		elseif (strpos($this_calc, "[$this_field_orig(") !== false) {
			// Replace field
			$dd_array['F'][$key] = $this_calc = str_replace("[$this_field_orig(", "[$this_field_new(", $this_calc);
		}
	}
}
// Loop through branching logic
foreach ($dd_array['L'] as $key=>$this_branching) {
	// If has no branching, then skip
	if ($this_branching == '') continue;
	// Replace any renamed fields in equation (via looping)
	foreach ($renamed_fields as $this_field_orig=>$this_field_new) {
		// If doesn't contain the field, then skip
		if (strpos($this_branching, "[$this_field_orig]") !== false) {
			// Replace field
			$dd_array['L'][$key] = $this_branching = str_replace("[$this_field_orig]", "[$this_field_new]", $this_branching);
		}
		// If doesn't contain the field, then skip
		elseif (strpos($this_branching, "[$this_field_orig(") !== false) {
			// Replace field
			$dd_array['L'][$key] = $this_branching = str_replace("[$this_field_orig(", "[$this_field_new(", $this_branching);
		}
	}
}

// FIELD EMBEDDING replacement
// Loop through field labels, section headers, notes, and choices
$fieldEmbedCols = array('C', 'E', 'F', 'G');
foreach ($fieldEmbedCols as $thisDDcol) {
	foreach ($dd_array[$thisDDcol] as $key => $this_item_original) {
		$current_item = $this_item_original;
		$processing_encoding = 'UTF-8';
		if ($this_item_original == '') {
			continue;
		}
		if ($project_encoding == 'japanese_sjis') {
			$converted_item = mb_convert_encoding($current_item, $processing_encoding, 'sjis');
			if ($converted_item === false) {
				error_log("Encoding conversion failed for key $key in column $thisDDcol. Original item (binary): " . bin2hex($this_item_original));
				continue;
			}
			$current_item = $converted_item;
		}
		// Replace any renamed fields in equation (via looping)
		foreach ($renamed_fields as $this_field_orig => $this_field_new) {
			$search_original = "{" . $this_field_orig . "}";
			$search_icon = "{" . $this_field_orig . ":icons}";
			$replace_original = "{" . $this_field_new . "}";
			$replace_icon = "{" . $this_field_new . ":icons}";
			$contains_field_or_icon = mb_strpos($current_item, $search_original, 0, $processing_encoding) !== false || mb_strpos($current_item, $search_icon, 0, $processing_encoding) !== false;
			if ($contains_field_or_icon) {
				$old_current_item = $current_item;
				if (mb_strpos($current_item, $search_original, 0, $processing_encoding) !== false) {
					$current_item = str_replace($search_original, $replace_original, $current_item);
				}
				if (mb_strpos($current_item, $search_icon, 0, $processing_encoding) !== false) {
					$current_item = str_replace($search_icon, $replace_icon, $current_item);
				}
			}
		}
		$final_item = $current_item;
		$dd_array[$thisDDcol][$key] = $final_item;
	}
}

// If has a video_url, then add it to array for copying separately
$video_urls = array();
foreach ($renamed_fields as $this_field_orig=>$this_field_new) {
	if ($current_metadata[$this_field_orig]['element_type'] == 'descriptive' && $current_metadata[$this_field_orig]['video_url'] != '') {
		$video_urls[$this_field_new]['video_url'] = $current_metadata[$this_field_orig]['video_url'];
		$video_urls[$this_field_new]['video_display_inline'] = $current_metadata[$this_field_orig]['video_display_inline'];
	}
}


// PREVENT MATRIX GROUP NAME DUPLICATION: Rename any matric group names that already exist in project to prevent duplication
$matrix_group_name_fields = ($status > 0) ? $Proj->matrixGroupNamesTemp : $Proj->matrixGroupNames;
$matrix_group_names = array_keys($matrix_group_name_fields);
$matrix_group_names_transform = array();
// Loop through fields being imported to find matrix group names
foreach ($dd_array['P'] as $key=>$this_mgn) {
	// Get matrix group name, if exists for this field
	if ($this_mgn == '') continue;
	$this_mgn_orig = $this_mgn;
	$this_mgn .= $_POST['affix'];
	// Does matrix group name already exist?
	$mgn_exists = (in_array($this_mgn, $matrix_group_names) && !isset($matrix_group_names_transform[$this_mgn]));
	while ($mgn_exists || strlen($this_mgn) > 50) {
		// Rename it
		// Make sure no longer than 50 characters and append alphanums to end
		$this_mgn = rtrim(substr($this_mgn, 0, 43), "_") . "_" . substr(sha1(rand()), 0, 6);
		// Does new field exist in existing fields or new fields being added?
		$mgn_exists = (in_array($this_mgn, $matrix_group_names) && !isset($matrix_group_names_transform[$this_mgn]));
	}
	// Add to transform array
	$matrix_group_names_transform[$this_mgn_orig] = $this_mgn;
}
// Loop through fields being imported to rename matrix group names
foreach ($dd_array['P'] as $key=>$this_mgn) {
	if (isset($matrix_group_names_transform[$this_mgn])) {
		$dd_array['P'][$key] = $matrix_group_names_transform[$this_mgn];
	}
}


// Return warnings and errors from file (and fix any correctable errors)
list ($errors_array, $warnings_array, $dd_array) = MetaData::error_checking($dd_array, true);

// Save data dictionary in metadata table
$sql_errors = (empty($errors_array)) ? MetaData::save_metadata($dd_array, true) : 0;
if (!empty($errors_array) || count($sql_errors) > 0) {
	// ERRORS OCCURRED, so undo any changes made
	db_query("ROLLBACK");
	// Set back to previous value
	db_query("SET AUTOCOMMIT=1");
	exit('0');
}

// Set form label for this new form
$sql = "update $metadata_table set form_menu_description = '".db_escape($_POST['form_label'])."'
		where project_id = $project_id and form_name = '$new_form_name' order by field_order limit 1";
db_query($sql);


// If any fields are descriptive fields with attachments, then transfer those to the new fields
foreach ($current_metadata as $this_field=>$attr) {
	// Only look at descriptives with attachments
	if (!($attr['form_name'] == $old_form_name && $attr['element_type'] == 'descriptive' && is_numeric($attr['edoc_id']))) continue;
	// If edoc_id exists, then copy file on server
	$new_edoc_id = copyFile($attr['edoc_id']);
	if (is_numeric($new_edoc_id)) {
		// Now update new field's edoc_id value
		$sql = "update $metadata_table set edoc_id = $new_edoc_id, edoc_display_img = '{$attr['edoc_display_img']}'
				where project_id = $project_id and field_name = '".$renamed_fields[$this_field]."'";
		$q = db_query($sql);
	}
}

// Copy any video_urls
foreach ($video_urls as $this_field=>$attr) {
	$sql = "update $metadata_table set video_url = '".db_escape($attr['video_url'])."',
			video_display_inline = '".db_escape($attr['video_display_inline'])."'
			where project_id = $project_id and field_name = '$this_field'";
	$q = db_query($sql);
}

// SHARED LIBRARY: If form was downloaded from shared library, then copy the attributes for it
$sql = "insert into redcap_library_map (project_id, form_name, type, library_id, upload_timestamp, acknowledgement, promis_key)
		select project_id, '$new_form_name', type, library_id, upload_timestamp, acknowledgement, promis_key
		from redcap_library_map	where project_id = $project_id and form_name = '$old_form_name'";
$q = db_query($sql);

// SURVEYS: If instrument is a survey, then copy survey settings
if ($surveys_enabled) {
	$rs_cols = array();
	foreach (array_keys(getTableColumns("redcap_surveys")) as $this_col) {
		$rs_cols[$this_col] = $this_col;
	}
	unset($rs_cols['survey_id'], $rs_cols['logo'], $rs_cols['confirmation_email_attachment']);
	$rs_cols['form_name'] = "'".db_escape($new_form_name)."'";
	$sql = "insert into redcap_surveys (".implode(", ", array_keys($rs_cols)).")
			select ".implode(", ", $rs_cols)."
			from redcap_surveys	where project_id = $project_id and form_name = '$old_form_name'";
	$q = db_query($sql);
	// If any logos or email confirmation attachment exists, copy those too
	$sql = "select logo, confirmation_email_attachment from redcap_surveys where project_id = $project_id and form_name = '$old_form_name'";
	$q = db_query($sql);
	if (db_num_rows($q)) {
		$row = db_fetch_assoc($q);
		if (is_numeric($row['logo'])) {
			// Copy logo
			$new_edoc_id = copyFile($row['logo']);
			if (is_numeric($new_edoc_id)) {
				$sql = "update redcap_surveys set logo = $new_edoc_id
						where project_id = $project_id and form_name = '$new_form_name'";
				$q = db_query($sql);
			}
		}
		if (is_numeric($row['confirmation_email_attachment'])) {
			// Copy attachment
			$new_edoc_id = copyFile($row['confirmation_email_attachment']);
			if (is_numeric($new_edoc_id)) {
				$sql = "update redcap_surveys set confirmation_email_attachment = $new_edoc_id
						where project_id = $project_id and form_name = '$new_form_name'";
				$q = db_query($sql);
			}
		}
	}
	// Copy any stop actions
    $stop_action_fields = array();
    $sql = "select field_name, stop_actions from $metadata_table 
		    where project_id = $project_id and field_name in (".prep_implode(array_keys($renamed_fields)).")
		    and stop_actions is not null and stop_actions != ''";
    $q = db_query($sql);
    while ($row = db_fetch_assoc($q)) {
        $stop_action_fields[$row['field_name']] = $row['stop_actions'];
    }
    foreach ($stop_action_fields as $this_field=>$this_stop_actions) {
        $sql = "update $metadata_table set stop_actions = '".db_escape($this_stop_actions)."'
		        where project_id = $project_id and field_name = '".$renamed_fields[$this_field]."'";
        db_query($sql);
    }
}

// MYCAP: If instrument enabled for MyCap, then copy mycap task settings
$myCapProj = new \Vanderbilt\REDCap\Classes\MyCap\MyCap($project_id);
if (isset($myCapProj->tasks[$old_form_name]['task_id']) && $myCapProj->tasks[$old_form_name]['task_id'] != '') {
    $old_task_id = $myCapProj->tasks[$old_form_name]['task_id'];
    $task_cols = array();
    foreach (array_keys(getTableColumns("redcap_mycap_tasks")) as $this_col) {
        $task_cols[$this_col] = $this_col;
    }
    unset($task_cols['task_id']);
    $task_cols['form_name'] = "'".db_escape($new_form_name)."'";
    $sql = "INSERT INTO redcap_mycap_tasks (".implode(", ", array_keys($task_cols)).")
			SELECT ".implode(", ", $task_cols)."
			FROM redcap_mycap_tasks	WHERE project_id = $project_id AND form_name = '$old_form_name'";
    if (db_query($sql)){
        $new_task_id = db_insert_id();
    }

    if (!$Proj->longitudinal) {
        $ts_cols = array();
        foreach (array_keys(getTableColumns("redcap_mycap_tasks_schedules")) as $this_col) {
            $ts_cols[$this_col] = $this_col;
        }
        unset($ts_cols['ts_id'], $ts_cols['task_id']);
        $q2 = db_query("select ts_id from redcap_mycap_tasks_schedules where task_id = {$old_task_id}");
        while ($row2 = db_fetch_assoc($q2))
        {
            $fields = implode(", ", array_keys($ts_cols));
            $values = implode(", ", $ts_cols);

            $old_ts_id = $row2['ts_id'];
            // Copy task schedules
            $sql = "INSERT INTO redcap_mycap_tasks_schedules (task_id, $fields)
                    SELECT $new_task_id, $values
                    FROM redcap_mycap_tasks_schedules WHERE ts_id = $old_ts_id";
            db_query($sql);
        }
        if (!$Proj->isRepeatingForm($Proj->firstEventId, $new_form_name)) {
            // Make this form as repeatable with default eventId as project is classic
            $sql = "INSERT INTO redcap_events_repeat (event_id, form_name) 
                VALUES ({$Proj->firstEventId}, '" . db_escape($new_form_name) . "')";
            db_query($sql);
        }
    }
}
// COMMIT CHANGES
db_query("COMMIT");
// Set back to previous value
db_query("SET AUTOCOMMIT=1");
// Do logging of file upload
Logging::logEvent("",$metadata_table,"MANAGE",$new_form_name,"form_name = '$new_form_name',\nold_form_name = '$old_form_name'","Copy data collection instrument");
// Return success message as JSON
$renamed_fields_text = "";
if (!empty($renamed_fields2)) {
	$renamed_fields_text = RCView::div(array('style'=>'line-height:14px;margin-bottom:5px;'), RCView::b($lang['global_03'].$lang['colon']) . " " . $lang['design_565']);
	foreach ($renamed_fields2 as $old_field=>$new_field) {
		$renamed_fields_text .= RCView::div(array('style'=>'margin:1px 0 1px 10px;line-height:12px;'), "- \"<b>$old_field</b>\" {$lang['design_566']} \"<b>$new_field</b>\"");
	}
	$renamed_fields_text = 	RCView::div(array('style'=>'height:100px;overflow-y:scroll;margin:20px 0 0;padding:5px;border:1px solid #ccc;'),
								$renamed_fields_text
							) .
							RCView::div(array('style'=>'margin:20px 0 0;text-align:right;'),
								RCView::button(array('class'=>'jqbuttonmed', 'onclick'=>"window.location.href = app_path_webroot+'Design/online_designer.php?pid='+pid;"), $lang['calendar_popup_01'])
							);
}
header("Content-Type: application/json");
print json_encode_rc(array('success'=>'1', 'renamed_fields_text'=>$renamed_fields_text));