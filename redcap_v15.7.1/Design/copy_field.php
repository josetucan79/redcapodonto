<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// If project is in production and another user just changed its draft_mode status, don't allow any actions here if not in draft mode
if ($status > 0 && $draft_mode != '1') exit("0");

// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
$ProjFields = ($status > 0) ? $Proj->metadata_temp : $Proj->metadata;
$ProjForms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;

// Validate parameters and field names (must be on same form)
$fields_to_copy = explode(", ", $_POST['fields'] ?? "");
$form_name = $_POST['form'] ?? "";
$copy_mode = $_POST['mode'] ?? "after-each";
if (count($fields_to_copy) < 1) exit("0");
if (!in_array($copy_mode, ["after-each", "after-last", "copy-matrix"])) exit("0");
// When mode is "copy-matrix", add all fields of the matrix group
if ($copy_mode == "copy-matrix") {
	$src_grid_name = $fields_to_copy[0];
	$all_grid_names = [];
	$fields_to_copy = [];
	foreach ($ProjFields as $field_name => $field) {
		if ($field['grid_name'] == $src_grid_name) {
			$fields_to_copy[] = $field_name;
		}
		$all_grid_names[$field['grid_name']] = true;
	}
	$all_grid_names = array_keys($all_grid_names);
	list($root, $num, $padding) = determine_repeat_parts($src_grid_name);
	do {
		$num++;
		$suffix_padded = str_pad($num, $padding, '0', STR_PAD_LEFT);
		$copy_matrix__new_grid_name = $root . $suffix_padded;
	} while (in_array($copy_matrix__new_grid_name, $all_grid_names));
	// Set mode to "after-last"
	$copy_mode = "after-last";
	// Set section header if there is one
	$copy_matrix__section_header = $ProjFields[$fields_to_copy[0]]['element_preceding_header'];
}
else {
	$copy_matrix__new_grid_name = null;
}
if (!array_key_exists($form_name, $ProjForms)) exit("0");
foreach ($fields_to_copy as $field_name) {
	if (!array_key_exists($field_name, $ProjFields)) exit("0");
	if ($ProjFields[$field_name]['form_name'] != $form_name) exit("0");
}

/** @var string[] Keeps track of all field names, including the newly added ones */
$all_field_names = array_keys($ProjFields);
/** @var array Keeps track of the order of all fields on this form */
$form_ordered_fields = [];
foreach ($ProjForms[$form_name]['fields'] as $field => $_) {
	$form_ordered_fields[intval($ProjFields[$field]['field_order'])] = $field;
}
ksort($form_ordered_fields);

// Begin transaction
db_query("SET AUTOCOMMIT=0");
db_query("BEGIN");
$commit = true;
$all_sql = [];
$all_params = [];

// Insert after last?
if ($copy_mode == "after-last") {
	$num_fields = count($fields_to_copy);
	$insert_position = $ProjFields[$fields_to_copy[$num_fields - 1]]['field_order'] + 1;
	// Move all other fields
	$sql = "UPDATE $metadata_table 
			SET field_order = field_order + ? 
			WHERE project_id = ? AND field_order >= ?";
	$params = [$num_fields, $project_id, $insert_position];
	$commit = $commit && db_query($sql, $params);
	$all_sql[] = $sql;
	$all_params[] = $params;
}

// Process all fields to be copied in the order they appear on the form
$new_field_names = [];
$log_descriptions = [];
$copy_iteration = 0;
foreach ($form_ordered_fields as $field_order => $field_name) {
	if (!in_array($field_name, $fields_to_copy)) continue;
	// Get a new field name
	list($root, $num, $padding) = determine_repeat_parts($field_name);
	do {
		$num++;
		$suffix_padded = str_pad($num, $padding, '0', STR_PAD_LEFT);
		$new_field_name = $root . $suffix_padded;
	} while (in_array($new_field_name, $all_field_names));
	$all_field_names[] = $new_field_name;
	$new_field_names[] = $new_field_name;
	$log_descriptions[$field_name] = $field_name . " -> " . $new_field_name;

	// If the field has a file attachment, copy the file on the web server and generate new edoc_id
	$new_edoc_id = copyFile($ProjFields[$field_name]['edoc_id']);
	if ($new_edoc_id === false) $new_edoc_id = null;

	// New              15 16 17 18 19 20 21 22 23
	// Orig 11 12 13 14          15 16 17 18 19 20 
	// Copy  *  *     *                           

	// New     12 13 14 15 16 17 18 19 20 21 22 23
	// Orig 11    12    13    14 15 16 17 18 19 20 
	// Copy  *     *     *                        

	// Determine the field order for the new field
	if ($copy_mode == "after-each") {
		$copy_iteration++;
		// Reset the field_order for all fields
		$new_field_order = $field_order + $copy_iteration;
		$sql = "UPDATE $metadata_table 
				SET field_order = field_order + 1 
				WHERE project_id = ? AND field_order > ?";
		$params = [$project_id, $field_order];
		$commit = $commit && db_query($sql, $params);
		$all_sql[] = $sql;
		$all_params[] = $params;
	}
	else {
		$new_field_order = $insert_position + $copy_iteration;
		$copy_iteration++;
	}

	// Copy the field from original (while setting new field_name and field_order)
	// If a matrix is copied, set new new matrix name (grid_name) and section header
	// (on first new matrix field only)
	$grid_name = $copy_matrix__new_grid_name 
		? ("'".db_escape($copy_matrix__new_grid_name)."'") // set new grid_name
		: "grid_name"; // refer to column in metadata table
	$element_preceding_header = $copy_matrix__section_header && $copy_iteration == 1
		? ("'".db_escape($copy_matrix__section_header)."'") // set new element_preceding_header
		: 'NULL';
	$sql = "INSERT INTO $metadata_table 
			(project_id, field_name, field_phi, form_name, form_menu_description, field_order,
			field_units, element_preceding_header, element_type, element_label, element_enum,
			element_note, element_validation_type, element_validation_min, element_validation_max,
			element_validation_checktype, branching_logic, field_req, edoc_id, edoc_display_img,
			custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc, video_url,
			video_display_inline)
			SELECT project_id, ?, field_phi, form_name, NULL, ?,
			field_units, $element_preceding_header, element_type, element_label, element_enum,
			element_note, element_validation_type, element_validation_min, element_validation_max,
			element_validation_checktype, branching_logic, field_req, ?, edoc_display_img,
			custom_alignment, stop_actions, question_num, $grid_name, grid_rank, misc, video_url, video_display_inline 
			FROM $metadata_table
			WHERE project_id = ? AND field_name = ?";
	$params = [$new_field_name, $new_field_order, $new_edoc_id, $project_id, $field_name];
	$commit = $commit && db_query($sql, $params);
	$all_sql[] = $sql;
	$all_params[] = $params;
	if (!$commit) break;
}
if ($commit) {
	// Finish transaction
	db_query("COMMIT");
	db_query("SET AUTOCOMMIT=1");
	// Logging
	$sql_log = "";
	for ($i = 0; $i < count($all_sql); $i++) {
		$sql_log .= $all_sql[$i]."\nParams: '".join("','", $all_params[$i])."'\n\n";
	}
	$display = count($log_descriptions) == 1 ? array_key_first($log_descriptions) : "(multiple fields)";
	Logging::logEvent(trim($sql_log), $metadata_table, "MANAGE", $display, join("\n", $log_descriptions), $copy_matrix__new_grid_name ? "Copy matrix group" : "Copy project field(s)");
	exit(join(", ", $new_field_names));
}
else {
	// Rollback
	db_query("ROLLBACK");
	db_query("SET AUTOCOMMIT=1");
	exit("0");
}
