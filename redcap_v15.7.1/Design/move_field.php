<?php

use Vanderbilt\REDCap\Classes\ProjectDesigner;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTask;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Default response
$response = '0';

// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

// If moving field(s) to a newly created instrument...
$createNewForm = ($_POST['action'] == 'save' && isset($_POST['move_after_field']) && $_POST['move_after_field'] == 'new-instrument');
$moveToEmptyForm = ($_POST['action'] == 'save' && isset($_POST['move_after_field']) && strpos($_POST['move_after_field'], 'empty-instrument-') === 0);
$placeholderFieldToDelete = null;
if ($createNewForm || $moveToEmptyForm) {
	// Get last form name
	$allForms = ($status > 0) ? array_keys($Proj->forms_temp) : array_keys($Proj->forms);
	// Add new form or set to add field to empty form
	$projectDesigner = new ProjectDesigner($Proj);
	if ($createNewForm) {
		// Add new form
		$lastForm = array_pop($allForms);
		$created = $projectDesigner->createForm("new_instrument", $lastForm, "New Instrument");
		// Reset $Proj
		$Proj = new Project($project_id, true);
		// Get the *new* last form name
		$allForms = ($status > 0) ? array_keys($Proj->forms_temp) : array_keys($Proj->forms);
		$formCreateField = array_pop($allForms);
	} else {
		// Move field to empty form
		list ($nothing, $formCreateField) = explode('empty-instrument-', $_POST['move_after_field'], 2);
		// Validate form name
		if (!in_array($formCreateField, $allForms)) exit($response);
	}
	// Create new
	$fieldAttr = [];
	$fieldAttr['field_type'] = "text";
	$fieldAttr['field_name'] = $placeholderFieldToDelete = ActiveTask::getNewFieldName("placeholder_field_".substr(sha1(rand()), 0, 6));
	$fieldAttr['field_label'] = "Placeholder Field";
	$projectDesigner->createField($formCreateField, $fieldAttr, '', false, '', NULL, NULL, '', false);
	// Set field to be placed after what used to be the last field
	$_POST['move_after_field'] = $placeholderFieldToDelete;
	// Reset $Proj again
	$Proj = new Project($project_id, true);
}

$fields = 
	array_reverse(
		array_filter(
			explode(",", str_replace(" ", "", $_POST['field'])), 
			function($s) { return !empty($s); }
		)
	);
$field_exists = array();
$field_matrix_status = array();
$fields_labels = array();
$movedFieldForm = array();
$targetFieldForm = array();
$changedForm = array();
$targetFieldFormLabel = array();

// Get arrays of all fields and all fields in all grids, etc.
$ProjFields = ($status > 0) ? $Proj->metadata_temp : $Proj->metadata;
$ProjForms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;
$num_forms  = ($status > 0) ? $Proj->numFormsTemp  : $Proj->numForms;
$allFieldsInGrids = ($status > 0) ? $Proj->matrixGroupNamesTemp : $Proj->matrixGroupNames;

foreach ($fields as $index => $field) {
	// Check if we have all values needed
	if (!(isset($field) && isset($_POST['action']) && isset($_POST['grid_name']) && preg_match("/^[a-z0-9_]+$/", $field))) {
		exit('0');
	}
	// Validate field name
	array_push($fields_labels, $ProjFields[$field]['element_label']);
	array_push($field_exists, isset($ProjFields[$field]));
	// Note matrix status
	array_push($field_matrix_status, $ProjFields[$field]['grid_name'] ?? "");
	// Was the matrix moved to another form?
	array_push($movedFieldForm, $ProjFields[$field]['form_name']);
	if (!isset($_POST['move_after_field'])) {
		array_push($targetFieldForm, "");
		array_push($targetFieldFormLabel, "");
	} else {
		$target_field__form_name = starts_with($_POST['move_after_field'], "-top-") 
			? substr($_POST['move_after_field'], 5) 
			: $ProjFields[$_POST['move_after_field']]['form_name'];
		if ($target_field__form_name == "") {
			// Same form
			$target_field__form_name = $movedFieldForm[$index];
		}
		array_push($targetFieldForm, $target_field__form_name);
		array_push($targetFieldFormLabel, $ProjForms[$targetFieldForm[$index]]['menu']);
	}
	array_push($changedForm, ($movedFieldForm[$index] != $targetFieldForm[$index]));
}
// Validate grid_name
$grid_name = trim($_POST['grid_name']);
if ($grid_name != '') {
	$grid_name_exists = ($status > 0) ? isset($Proj->matrixGroupNamesTemp[$grid_name]) : isset($Proj->matrixGroupNames[$grid_name]);
	if (!$grid_name_exists) exit('0');
}
$unique_grid_names = array_unique($field_matrix_status);
// Never can have mixed grid names
if (count($unique_grid_names) > 1) {
	exit('0');
}
$unique_grid_name = $unique_grid_names[0];
// Validate target field and check for invalid combination of matrix and non-matrix fields when moving
if (isset($_POST['move_after_field']) && $_POST["grid_name"] == "") {
	$target_field = $_POST['move_after_field'] ?? "";
	if (!(isset($ProjFields[$target_field]) || $target_field == "-top-")) {
		exit('0');
	}
	$target_field_matrix = $target_field == "-top-" 
		? $unique_grid_name 
		: ($ProjFields[$target_field]['grid_name'] ?? "");
	if ($unique_grid_name != $target_field_matrix) {
		exit('0');
	}
	if ($grid_name != "" && $grid_name != $unique_grid_name) {
		exit('0');
	}
}
// Set grid name when empty and fields are in a grid
if ($grid_name == "" && count($fields)) {
	$_POST["grid_name"] = $grid_name = $unique_grid_name;
}

// Get array of fields in grid being moved (if moving a matrix)
$fieldsInGrid = array();
if ($grid_name != "") {
	$fieldsInGrid = $allFieldsInGrids[$grid_name];
}

// DISPLAY INSTRUCTIONS
if ($_POST["action"] == "view") {
	// Set view mode
	$view_mode = "move-field";
	if (empty($_POST["field"]) && !empty($_POST["grid_name"])) $view_mode = "move-grid";
	if (!empty($_POST["field"]) && !empty($_POST["grid_name"])) $view_mode = "move-within-grid";
	if ($_POST["move_sh"] ?? "0" == "1") $view_mode = "move-sh";

	// Build field drop-down list
	$all_fields_dd = "<select id='move_after_field' style='font-weight:normal;width:100%;'>" . 
		"<option value=''>-- {$lang['random_02']} --</option>";

	if ($view_mode == "move-field" || $view_mode == "move-grid" || $view_mode == "move-sh") {
		$disqualifiedMatrixFields = array();
		// Create array of all fields in all grids WITHOUT the first or last field in each grid, because
		// when building the field drop-down, we don't want to display fields in the middle of 
		// a grid but only the first or last field in the grid, depending on the view mode.
		foreach ($allFieldsInGrids as $thisgrid=>$thesefields) {
			if ($view_mode == "move-sh") {
				array_shift($thesefields); // Remove first field
			}
			else {
				array_pop($thesefields); // Remove last field
			}
			$disqualifiedMatrixFields = array_merge($disqualifiedMatrixFields, $thesefields);
		}

		global $myCapProj;
		$prevform = '';
		foreach ($ProjFields as $thisfield=>$attr) {
			// Get current form
			$thisform = $attr['form_name'];

			// Exclude if this form is active task added for MyCap
			if (($myCapProj->tasks[$thisform]['is_active_task'] ?? null) == 1) continue;

			// If we're beginning a new form, then display form menu label as optgroup label
			if ($num_forms > 1 && $thisform != $prevform) {
				// Close previous optgroup
				if ($prevform != '') $all_fields_dd .= "</optgroup>";
				// Add optgroup
				$all_fields_dd .= "<optgroup label='" . RCView::escape($attr['form_menu_description']) . "'>";
				// If this is not the first form, add a "Top" option
				if ($prevform != '' && $view_mode != "move-sh") {
					$all_fields_dd .= "<option value='-top-$thisform'>{$lang['design_1238']}</option>";
				}
			}
			// Highlight the location of the current field being moved (to give perspective)
			foreach ($fields as $field) {
				if ($thisfield == $field) {
					$all_fields_dd .= "</optgroup><optgroup label=\"" . strip_tags(RCView::tt_i("design_1219", [$thisfield])) . "\">";
				}
			}
			// Do not include the field/matrix we're moving OR Form Status fields OR any disqualified matrix fields
			if ($thisfield != $thisform.'_complete' 
				&& !in_array($thisfield, $disqualifiedMatrixFields) 
				&& (
					($grid_name == '' && !in_array($thisfield, $fields)) 
					|| 
					($grid_name != '' && !in_array($thisfield, $fieldsInGrid))
				)
				&& !($view_mode == "move-sh" && $thisfield == $Proj->table_pk)
				)
			{
				$this_field_grid_name = $ProjFields[$thisfield]['grid_name'];
				$sh_mark = ($view_mode == "move-sh" && !empty($ProjFields[$thisfield]["element_preceding_header"])) ? "*" : "";
				// Add option
				$label_len = empty($this_field_grid_name) ? 58 : 30;
				$label = strip_tags(label_decode($attr['element_label']));
				$label = (mb_strlen($attr['element_label']) > 60) ? mb_substr($label, 0, 58)."&hellip;" : $label;
				if (!empty($this_field_grid_name)) {
					$label_display = $lang["design_502"] . RCView::SP . RCView::escape($this_field_grid_name) . RCView::SP . "&mdash;" . RCView::SP . $sh_mark . $thisfield . RCView::SP . RCView::escape('"' . $label . '"');
				}
				else {
					$label_display = $sh_mark . $thisfield . RCView::SP . RCView::escape('"' . $label . '"');
				}
				$all_fields_dd .= "<option value='$thisfield'>" . $label_display . "</option>";
			}
			// If an instrument is empty (only contains the form status field), then display a placeholder option to allow the field to be moved there
			if (isset($Proj->forms[$thisform]) && count($Proj->forms[$thisform]['fields']) == 1) {
				$all_fields_dd .= "<option value='empty-instrument-$thisform'>" . RCView::tt('design_1120') . "</option>";
			}

			// Set for next loop
			$prevform = $thisform;
		}
		// Add closing optgroup and close select list
		$all_fields_dd .= "</optgroup>";
		if ($view_mode != "move-sh") {
			// Add "Create new instrument and ..." option
			$all_fields_dd .= "<optgroup label='" . RCView::escape($lang['design_1119']) . "'>";
			$all_fields_dd .= "<option value='new-instrument'>{$lang['design_1118']}</option>";
		}

		// Set variables for displayed text
		if ($grid_name == '') {
			// Single field or section header
			if ($view_mode == "move-sh") {
				$text1 = RCView::tt("design_1330");
				$text2 = RCView::tt("design_1331");
				$text3 = RCView::tt("design_1332");
				$title = RCView::tt("design_1329");
			}
			else {
				$text1 = RCView::tt("design_1246");
				$text2 = RCView::tt("design_1245");
				$text3 = RCView::tt("design_1244");
				$title = RCView::tt("design_1242");
			}
		} else {
			// Matrix of fields
			$text1 = RCView::tt("design_1247");
			$text2 = RCView::tt("design_343");
			$text3 = RCView::tt("design_1235");
			$title = RCView::tt("design_1243");
		}
		$title_confirm = "";
		if ($grid_name == "") {
			foreach ($fields as $index => $field){
				$padding = '';
				if($index != 0){
					$padding = 'padding-left:125px;';
				}
				$title_confirm .= RCView::span(array('style'=>'color:#800000;font-weight:bold;font-family:verdana;'.$padding), $field) . RCView::SP . RCView::SP .
					($fields_labels[$index] == '' ? '' : RCView::span(array('style'=>'color:#800000;'), '"' . RCView::escape(trim(strip_tags($fields_labels[$index]))) . '"')).RCView::br();
			}
		}
		else {
			$title_confirm = RCView::span(array('style'=>'color:#800000;font-weight:bold;font-family:verdana;'), $grid_name);
		}
	}
	else {
		// Move within grid
		$text1 = RCView::tt("design_1233");
		$text2 = RCView::tt("design_1234");
		$text3 = RCView::tt("design_1244");
		$title = RCView::tt("design_1232");
		$title_confirm = "";
		foreach ($fields as $index => $field){
			$padding = '';
			if($index != 0){
				$padding = 'padding-left:125px;';
			}
			$title_confirm .= RCView::span(array('style'=>'color:#800000;font-weight:bold;font-family:verdana;'.$padding), $field) . RCView::SP . RCView::SP .
				($fields_labels[$index] == '' ? '' : RCView::span(array('style'=>'color:#800000;'), '"' . RCView::escape(trim(strip_tags($fields_labels[$index]))) . '"')).RCView::br();
		}
		$all_fields_dd .= "<option value='-top-'>{$lang['design_1239']}</option>";
		foreach ($fieldsInGrid as $thisfield) {
			if (in_array($thisfield, $fields)) {
				$all_fields_dd .= "</option><optgroup label=\"" . strip_tags(RCView::tt_i("design_1219", [$thisfield])) . "\">";
			}
			else {
				$attr = $ProjFields[$thisfield];
				$label = strip_tags(label_decode($attr['element_label']));
				$label = (mb_strlen($attr['element_label']) > 60) ? mb_substr($label, 0, 58)."..." : $label;
				$all_fields_dd .= "<option value='$thisfield'>$thisfield " . RCView::SP . RCView::escape('"' . $label . '"') . "</option>";
			}
		}
	}
	$all_fields_dd .= "</optgroup></select>";

	// Popup content
	$js = "$('#move_after_field').select2();";
	$html = RCView::div('',
				RCView::p('', $text1) .
				RCView::div(array('style'=>'font-size:13px;width:95%;margin-top:15px;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;'),
					RCView::b($text2) . RCView::SP . RCView::SP .$title_confirm ).
				RCView::div(array('style'=>'line-height:1.6em;margin:20px 0;background-color:#f5f5f5;border:1px solid #ccc;padding:10px;width:95%;'),
					"<div class='mb-1'>$text3</div>" . $all_fields_dd
				)
			).
			RCView::script($js);

	// Output JSON
	header("Content-Type: application/json");
	print json_encode_rc(array('payload'=>$html, 'title'=>$title));
	exit;
}

// MOVE AND SAVE IN NEW POSITION
elseif ($_POST['action'] == 'save') {
	// Validation
	if (!isset($_POST["move_after_field"])) exit("0");
	if (!(isset($ProjFields[$_POST['move_after_field']]) || starts_with($_POST['move_after_field'],"-top-"))) exit("0");
	$top_of_form = starts_with($_POST['move_after_field'],"-top-") ? substr($_POST['move_after_field'], 5) : "";
	if ($top_of_form != "" && !array_key_exists($top_of_form, $ProjForms)) exit("0");
	$move_sh = isset($_POST['move_sh']) && $_POST['move_sh'] == "1";
	// Capture all executed SQL
	$sql_all = array();
	//
	// PREPARATIONS
	//
	// Determine mode and perform any preparatory updates
	if ($move_sh) {
		$mode = "move-sh";
	}
	else if (empty($fields) && !empty($grid_name)) {
		// Moving a matrix of fields. Add first matrix field
		$index = 0;
		$field = $allFieldsInGrids[$grid_name][0];
		$fields = [$field];
		$mode = "move-matrix";
		// Was the matrix moved to another form?
		array_push($movedFieldForm, ($status > 0) ? $Proj->metadata_temp[$field]['form_name'] : $Proj->metadata[$field]['form_name']);
		if (!isset($_POST['move_after_field'])) {
			array_push($targetFieldForm, "");
			array_push($targetFieldFormLabel, "");
		} else {
			$target_field__form_name = starts_with($_POST['move_after_field'], "-top-")
				? substr($_POST['move_after_field'], 5)
				: (($status > 0)
					? $Proj->metadata_temp[$_POST['move_after_field']]['form_name']
					: $Proj->metadata[$_POST['move_after_field']]['form_name']
				);
			array_push($targetFieldForm, $target_field__form_name);
			array_push($targetFieldFormLabel, ($status > 0) ? $Proj->forms_temp[$targetFieldForm[$index]]['menu'] : $Proj->forms[$targetFieldForm[$index]]['menu']);
		}
		array_push($changedForm, ($movedFieldForm[$index] != $targetFieldForm[$index]));
		// Get all existing fields and their order and put them in arrays for later use and to determine
		// the last field of the previous form in case of moving to top of form.
		$currentFieldOrder = array();
		$all_fields_names = array();
		$sql = "SELECT field_name, field_order FROM $metadata_table WHERE project_id = $project_id ORDER BY field_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$currentFieldOrder[$row['field_name']] = $row['field_order'];
			array_push($all_fields_names,$row['field_name']);
		}
		// Update move_after_field in case of moving to top of form
		if (strpos($_POST['move_after_field'], "-top-") === 0) {
			// Find last field of the previous form
			$forms = array_keys($ProjForms);
			$last_field_prev_form = array_search($targetFieldForm[$index], $forms) - 1;
			$_POST["move_after_field"] = $forms[$last_field_prev_form]."_complete";
		}
	}
	else if (!empty($grid_name)) {
		$mode = "move-within-grid";
		if ($_POST['move_after_field'] == '-top-') {
			// Get all fields in order
			$fields_ordered = [];
			foreach ($ProjFields as $thisfield => $thisattr) {
				$fields_ordered[intval($thisattr["field_order"])] = $thisfield;
			}
			ksort($fields_ordered);
			// Set move_after_field to the field before the first matrix field
			$first_field_in_grid = $allFieldsInGrids[$grid_name][0];
			$first_field_in_grid_index = array_search($first_field_in_grid, $fields_ordered);
			$_POST['move_after_field'] = $fields_ordered[$first_field_in_grid_index - 1];
			// Copy the section header to this field
			if ($first_field_in_grid !== $fields[0]) {
				$this_section_header = $ProjFields[$first_field_in_grid]['element_preceding_header'];
				$this_section_header_db = checkNull($this_section_header);
				$sql = "UPDATE $metadata_table SET element_preceding_header = NULL 
						WHERE project_id = $project_id AND field_name = '" . db_escape($first_field_in_grid) . "'";
				$sql_all[] = $sql;
				db_query($sql);
				$sql = "UPDATE $metadata_table SET element_preceding_header = $this_section_header_db 
						WHERE project_id = $project_id AND field_name = '" . db_escape($fields[0]) . "'";
				$sql_all[] = $sql;
				db_query($sql);
			}
		}
	}
	else {
		$mode = "move-field";
		$field_with_form_menu_label = "";
		$clear_form_menu_description = true;
		if (!empty($top_of_form)) {
			// The field is to be moved to the top of a form
			// Get name of the preceding form
			$all_forms = array_keys($ProjForms);
			$form_index = array_search($top_of_form, $all_forms);
			$preceding_form = $all_forms[$form_index - 1];
			// Thus, the field should be moved to after the preceding form's status field
			$_POST['move_after_field'] = $preceding_form . "_complete";
			// The field will be the first field on the form. Thus, set the form menu label
			$field_with_form_menu_label = $fields[0];
			$first_field_in_form = array_keys($ProjForms[$top_of_form]['fields'])[0];
			$sql = "UPDATE $metadata_table SET form_menu_description = NULL
					WHERE project_id = $project_id AND field_name = '" . db_escape($first_field_in_form) . "'";
			$sql_all[] = $sql;
			db_query($sql);
			$form_menu_description_db = checkNull($ProjFields[$first_field_in_form]['form_menu_description']); 
			$sql = "UPDATE $metadata_table SET form_menu_description = $form_menu_description_db
					WHERE project_id = $project_id AND field_name = '" . db_escape($fields[0]) . "'";
			$sql_all[] = $sql;
			db_query($sql);
			$clear_form_menu_description = false;
			$mode = "move-top";
		}
	}
	//
	// EXECUTION
	//
	// Message if field's SH got merged with another SH
	$mergedSHmsg = "";
	$response = array();
	$fieldCount = count($fields);
	foreach ($fields as $index => $field)
	{
		// If we're processing multiple fields, reset the metadata in the Project object to keep it updated for the next loop
		if ($fieldCount > 1 && $index > 0) {
			if ($status > 0) {
				$Proj->loadMetadataTemp();
			} else {
				$Proj->loadMetadata();
			}
		}

		// Move section header
		if ($mode == "move-sh") {
			// There can be at most one field
			if (count($fields) != 1) exit("0");
			$move_from_field = $fields[0];
			$move_to_field = $_POST['move_after_field'];
			if ($move_from_field == $move_to_field) exit("0");
			// By default, append to the end of any existing section header on the target field
			$sh_merge_append = !(isset($_POST['sh_merge_append']) && $_POST['sh_merge_append'] == "0");
			$sh_merged = false;
			// Existing section header on source / target fields?
			$source_sh = label_decode($ProjFields[$move_from_field]['element_preceding_header'] ?? "");
			if ($source_sh == "") exit("0");
			$target_sh = label_decode($ProjFields[$move_to_field]['element_preceding_header'] ?? "");
			if ($target_sh != "") {
				// Merge
				if ($sh_merge_append) {
					$target_sh = $target_sh . "\n\n" . $source_sh;
				} else {
					$target_sh = $source_sh . "\n\n" . $target_sh;
				}
				// Note that a merge of section headers has been performed
				$response[] = RCView::div(array('class' => 'yellow', 'style' => 'margin-top:20px;font-size:12px;'),
				RCView::tt("design_1335"));
				$sh_merged = true;
			}
			else {
				$target_sh = $source_sh;
			}
			// Update database
			// Remove SH on source
			$sql = "UPDATE $metadata_table 
					SET element_preceding_header = NULL 
					WHERE project_id = ? AND field_name = ?";
			$params = [ $project_id, $move_from_field ];
			$q = db_query($sql, $params);
			$sql_all[] = System::pseudoInsertQueryParameters($sql, $params, true);
			// Set SH on target
			$sql = "UPDATE $metadata_table 
					SET element_preceding_header = ? 
					WHERE project_id = ? AND field_name = ?";
			$params = [ $target_sh, $project_id, $move_to_field ];
			$q = db_query($sql, $params);
			$sql_all[] = System::pseudoInsertQueryParameters($sql, $params, true);
			// Changed form?
			if ($changedForm[0]) {
				$response[] = RCView::div([],
					RCView::a([
						"href" => APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&page=$targetFieldForm[0]#{$move_to_field}-tr-sh",
						"style" => "text-decoration:underline;"
					], RCView::tt("design_1338"))
				);
			}
			$response[] = $changedForm[0] ? RCView::tt("design_1337") : RCView::tt("design_1336");
			// Set log description
			$descrLog = "Move section header";
			$fieldNamesLog = 
				"From: " . $move_from_field . "\n" . 
				"To: " . $move_to_field . (!$sh_merged 
					? "" 
					: " (merged: " . ($sh_merge_append ? "append" : "prepend") . ")"
				);
		}
		// Move single field
		else if ($mode != "move-matrix") {

			// If had a SH, then leave the SH on the field that originally followed the moved field
			$fieldSH = ($status > 0) ? $Proj->metadata_temp[$field]['element_preceding_header'] : $Proj->metadata[$field]['element_preceding_header'];

			if ($fieldSH != "") {
				// Remove SH from moved field
				$sql = $sql_all[] = "update $metadata_table set element_preceding_header = null
							where project_id = $project_id and field_name = '" . db_escape($field) . "'";
				db_query($sql);

				// Add SH to field that follows moved field in its original location
				$formFields = ($status > 0) ? $Proj->forms_temp[$movedFieldForm[$index]]['fields'] : $Proj->forms[$movedFieldForm[$index]]['fields'];
				$origFieldAfterMovedField = "";
				$getNextField = false;
				foreach (array_keys($formFields) as $thisfield) {
					if ($thisfield == $field) {
						$getNextField = true;
					} elseif ($getNextField) {
						$origFieldAfterMovedField = $thisfield;
						break;
					}
				}
				// Now that we have field directly after moved field, add moved field's SH to it
				if ($origFieldAfterMovedField != "") {
					// See if this field already has a SH before we add this new SH to it (if so, merge both SH together)
					$fieldAfterMovedFieldSH = $ProjFields[$origFieldAfterMovedField]['element_preceding_header'] ?? "";
					// Set flag
					$resurrectSH = true;
					// Message if field's SH got merged with another SH
					if ($fieldAfterMovedFieldSH != "") {
						if ($origFieldAfterMovedField == $movedFieldForm[$index] . "_complete") {
							// Cannot merge SH with a Form Status field's SH, so give msg that SH was lost
							$mergedSHmsgContent = "<b>".RCView::tt("design_362")."</b><br><br>".RCView::tt("design_363");
							// Set flag NOT to resurrect the SH
							$resurrectSH = false;
						} else {
							// Merge SH with following field's SH
							$mergedSHmsgContent = "<b>".RCView::tt("design_360")."</b><br><br>".RCView::tt("design_361");
						}
						// Set msg HTML
						$mergedSHmsg = RCView::div(array('class' => 'yellow', 'style' => 'margin-top:20px;font-size:12px;'),
							$mergedSHmsgContent
						);
					}
					if ($resurrectSH) {
						// Set the new SH text
						$fieldAfterMovedFieldSH = label_decode($fieldSH) . ($fieldAfterMovedFieldSH == "" ? "" : "\n\n" . label_decode($fieldAfterMovedFieldSH));
						// Add SH to field
						$sql = $sql_all[] = "UPDATE $metadata_table SET element_preceding_header = " . checkNull($fieldAfterMovedFieldSH) . "
									WHERE project_id = $project_id AND field_name = '" . db_escape($origFieldAfterMovedField) . "'";
						db_query($sql);
					}
				}
			}

			// Remove Form Title from moved field
			if ($clear_form_menu_description) {
				$sql = $sql_all[] = "update $metadata_table set form_menu_description = null
							where project_id = $project_id and field_name = '" . db_escape($field) . "'";
				db_query($sql);
			}
			// First get all existing fields and their order and put in array so that we only update those whose field order changed
			$currentFieldOrder = array();
			$all_fields_names = array();
			$sql = "select field_name, field_order from $metadata_table where project_id = $project_id order by field_order";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$currentFieldOrder[$row['field_name']] = $row['field_order'];
				array_push($all_fields_names,$row['field_name']);
			}

			// Loop through ALL field names and create array of them all, then insert matrix fields at desired target spot
			$fieldsNewOrder = array();
			foreach ($all_fields_names as $thisfield) {
				// Add field to array (only if not the field being moved)
				if ($thisfield != $field) {
					$fieldsNewOrder[] = $thisfield;
				}
				// If this field is our target field, add the matrix fields here
				if ($thisfield == $_POST['move_after_field']) {
					$fieldsNewOrder[] = $field;
				}
			}
			// Double check to make sure the counts add up
			if (count($all_fields_names) != count($fieldsNewOrder)) exit('0');

			// Now loop through ALL fields and set new field_order as such
			$field_order = 1;
			$errors = 0;
			foreach ($fieldsNewOrder as $thisfield) {
				// Only do update if field order number changed
				if ($currentFieldOrder[$thisfield] != $field_order) {
					// If field was moved to another form, don't forget to also change its form_name
					$form_name_sql = "";
					if ($changedForm[$index] && $thisfield == $field) {
						// Set sql to set form_name to target form for moved field
						$form_name_sql = ", form_name = '" . db_escape($targetFieldForm[$index]) . "'";
					}
					// Set new field order
					$sql = $sql_all[] = "update $metadata_table set field_order = $field_order $form_name_sql
								where project_id = $project_id and field_name = '" . db_escape($thisfield) . "'";
					if (!db_query($sql)) $errors++;
				}
				// Increment field order
				$field_order++;
			}

			if ($errors > 0) exit('0');
			// Set HTML response
			$other_form_link = RCView::div([
				'style' => 'margin-top:5px;margin-bottom:10px;padding-bottom:12px;border-bottom:1px dashed #ddd;'
				],
				"<a href='" . APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&page=$targetFieldForm[$index]#{$field}-tr' style='text-decoration:underline;'>".RCView::tt("design_351")."</a>");
			$response_msg = "";
			if ($mode == "move-within-grid") {
				$response_msg = RCView::tt("design_1236");
			}
			else if ($mode == "move-top") {
				$response_msg = RCView::tt_i("design_1240", [$targetFieldFormLabel[$index]]);
				if ($changedForm[$index]) {
					$response_msg .= $other_form_link;
				}
			}
			else {
				$response_msg = $changedForm[$index] 
					? (RCView::tt_i("design_1241", [
						"<a href='" . APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&page=$targetFieldForm[$index]' style='text-decoration:underline;'>$targetFieldFormLabel[$index]</a>"
					], false) . RCView::div([
							'style' => 'margin-top:5px;margin-bottom:10px;padding-bottom:12px;border-bottom:1px dashed #ddd;'
						],
						"<a href='" . APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&page=$targetFieldForm[$index]#{$field}-tr' style='text-decoration:underline;'>".RCView::tt("design_351")."</a>"
					))
					: RCView::tt("design_1237");
			}
			$response[] = RCView::div([],
				RCView::b($field . ": ") . $response_msg . $mergedSHmsg
			);

			// Add note if Record ID field changed (because it itself was moved)
			if (Design::recordIdFieldChanged()) {
				$sql = "select field_name from $metadata_table where project_id = $project_id order by field_order limit 1";
				$current_table_pk = db_result(db_query($sql), 0);
				$response[] = RCView::div(array('class' => 'red', 'style' => 'margin-top:20px;font-size:12px;'),
					"<b>{$lang['design_353']}</b><br><br>
						<b>{$lang['update_pk_07']}</b> {$lang['update_pk_02']} {$lang['update_pk_08']} \"<b>$current_table_pk</b>\"{$lang['period']}
						{$lang['update_pk_05']}<br><br>
						<b>{$lang['update_pk_03']}</b><br>" . ($status < 1 ? $lang['update_pk_10'] : $lang['update_pk_11'])
				);
			}
		} // Move whole matrix (w/ possible SH)
		else {
			// Existing fields ($all_fields_names) and field order ($currentFieldOrder) have already 
			// been loaded during prep steps

			// Loop through ALL field names and create array of them all, then insert matrix fields at desired target spot
			$fieldsNewOrder = array();
			foreach ($all_fields_names as $thisfield) {
				// Add field to array (only if not in matrix being moved)
				if (!in_array($thisfield, $fieldsInGrid)) {
					$fieldsNewOrder[] = $thisfield;
				}
				// If this field is our target field, add the matrix fields here
				if ($thisfield == $_POST['move_after_field']) {
					$fieldsNewOrder = array_merge($fieldsNewOrder, $fieldsInGrid);
				}
			}
			// Double check to make sure the counts add up
			if (count($all_fields_names) != count($fieldsNewOrder)) exit('0');

			// Now loop through ALL fields and set new field_order as such
			$field_order = 1;
			$sql_all = array();
			$errors = 0;
			foreach ($fieldsNewOrder as $thisfield) {
				// Only do update if field order number changed
				if ($currentFieldOrder[$thisfield] != $field_order) {
					// If field was moved to another form, don't forget to also change its form_name
					$form_name_sql = "";
					// For fields that are moved, may need to change the form name as well
					if ($changedForm && in_array($thisfield, $fieldsInGrid)) {
						// Set sql to set form_name to target form for moved field
						$form_name_sql = ", form_name = '" . db_escape($targetFieldForm[$index]) . "'";
						// Reset form label
						if (!starts_with($_POST['move_after_field'], "-top-")) {
							$form_name_sql .= ", form_menu_description = null";
						}
					}
					// Set new field order
					$sql = $sql_all[] = "update $metadata_table set field_order = $field_order $form_name_sql
									where project_id = $project_id and field_name = '" . db_escape($thisfield) . "'";
					if (!db_query($sql)) $errors++;
				}
				// Increment field order
				$field_order++;
			}
			if ($errors > 0) exit('0');
			// Set HTML response
			$response[] = RCView::div('',
				$lang['design_348'] . " " .
				($changedForm[$index]
					? $lang['design_350'] . " \"<a href='" . APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&page=$targetFieldForm[$index]' style='font-weight:bold;text-decoration:underline;'>$targetFieldFormLabel[$index]</a>\"" . $lang['period'] .
					RCView::div(array('style' => 'margin-top:15px;'),
						"<a href='" . APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&page=$targetFieldForm[$index]#{$field}-tr' style='text-decoration:underline;'>{$lang['design_352']}</a>"
					)
					: $lang['design_349'])
			);
		}

		if ($mode != "move-sh") {
			// Check if moved to beginning of a form. If so, deal with form menu description
			Design::fixFormLabels();
			$descrLog = ($grid_name == '') ? "Move project field" : "Move matrix of project fields";
			$fieldNamesLog = ($grid_name == '' ? "field_name = '$field'" : "grid_name = '$grid_name'\nfield_name = '" . implode("'\nfield_name = '", $fieldsInGrid) . "'");
		}

		// Log this event
		Logging::logEvent(implode(";\n", $sql_all), $metadata_table, "MANAGE", $grid_name, $fieldNamesLog, $descrLog);

	}

	// If field was moved to another form in a longitudinal project, give warning of possible data orphaning
	if ($Proj->longitudinal && Records::getRecordCount(PROJECT_ID) > 0)
	{
		$anyFieldChangedForm = false;
		foreach ($changedForm as $didChangeForm) {
			if ($didChangeForm) {
				$anyFieldChangedForm = true;
				break;
			}
		}
		if ($anyFieldChangedForm) {
			$response[] = RCView::div(['class'=>'yellow mb-2'],
				'<i class="fa-solid fa-circle-exclamation me-1"></i>'.RCView::tt("design_1080")
			);
		}
	}

	// If added a placeholder field (because we created a new instrument on the fly), delete that field
	if ($placeholderFieldToDelete !== null)
	{
		$sql = "DELETE FROM $metadata_table WHERE project_id = ? AND field_name = ?";
		db_query($sql, [PROJECT_ID, $placeholderFieldToDelete]);
	}

	// Return successful response
	$response = implode("", array_reverse($response));
	exit($response);
}

// ERROR
exit('0');
