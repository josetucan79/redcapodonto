<?php

use MultiLanguageManagement\MultiLanguage;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$message = [
	"title" => $lang["global_01"],
	"text" => $lang["design_1339"],
	"type" => "error"
];

$ProjFields = ($status > 0) ? $Proj->metadata_temp : $Proj->metadata;
$ProjForms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

do {
	// Validate inputs
	if (!isset($_POST['field_names']) || !isset($_POST['form_name'])) {
		break;
	}
	$form_name = $_POST['form_name'];
	if (!array_key_exists($form_name, $ProjForms)) {
		break;
	}
	$field_names = trim($_POST['field_names']);
	$fields_array = explode(',', $field_names);
	foreach ($fields_array as $this_field) {
		if (ends_with($this_field, "-sh"))$this_field = substr($this_field, 0, -3);
		if (!array_key_exists($this_field, $ProjForms[$form_name]['fields'])) {
			break 2;
		}
	}
	$field_name = trim($_POST['field_name'] ?? "");
	if (!array_key_exists(str_replace("-sh", "", $field_name), $ProjForms[$form_name]['fields'])) {
		break;
	}
	$section_header = $_POST['section_header'] ?? "";
	if ($section_header != "" && !array_key_exists($section_header, $ProjForms[$form_name]['fields'])) {
		break;
	}

	//Before modifying, get current field_order number for first field on this form
	$first_field_on_form = array_key_first($ProjForms[$form_name]['fields']);
	$order_num = intval($ProjFields[$first_field_on_form]['field_order']);
	if ($order_num == "" || $order_num < 1) {
		$sql = "SELECT field_order 
		        FROM $metadata_table 
				WHERE project_id = ? AND form_name = ? 
				ORDER BY field_order LIMIT 1";
		$order_num = db_result(db_query($sql, [$project_id, $form_name]), 0);
		if ($order_num == "" || $order_num < 1) $order_num = 1;
	}
	// Signal success (empty message)
	$message = null;

	// Loop through new order of fields for this form and reorder
	if ($section_header == "") {
		// Loop and collect new order in array
		foreach ($fields_array as $this_field) {
			// Ignore section headers during reordering
			if (substr($this_field, -3) != "-sh" && $this_field != "") {
				// Update each field with new field_order
				$sql = "UPDATE $metadata_table SET field_order = ? WHERE project_id = ? AND field_name = ?";
				$q = db_query($sql, [$order_num, $project_id, $this_field]);
				if (!$q) {
					// Errors occurred, so undo any changes made
					db_query("ROLLBACK");
					// Send error response
					break 2;
				}
				// Increment counter
				$order_num++;
			}
		}
		// Set order for form_status field too
		$sql = "UPDATE $metadata_table SET field_order = ? WHERE project_id = ? AND field_name = ?";
		$params = [$order_num, $project_id, $form_name."_complete"];
		$q = db_query($sql, $params);

		// MLM: Keep track of things to do
		$mlm_FROM_prev = null;
		$mlm_FROM_dest = null;
		$mlm_FROM_mode = 1;
		$mlm_TO_prev = null;
		$mlm_TO_dest = null;
		$mlm_TO_mode = 1;
	
		// If field was moved FROM directly under a Section Header, and the field after it also has a Section Header, must move Section Header value onto that field to merge with the next field's
		if (strpos($field_names, "$field_name-sh,") !== false && strpos($field_names, "$field_name,$field_name-sh,") !== false) {
			// Get field after this one because it will get this field's SH added to it
			$next_field_key = array_search($field_name, $fields_array) + 2;
			if (isset($fields_array[$next_field_key]) && strpos($fields_array[$next_field_key], "-sh") !== false) {
				$new_field_attach_sh = substr($fields_array[$next_field_key], 0, -3);
				// Get section headers of both fields
				$sh_value1 = $ProjFields[$field_name]["element_preceding_header"];
				$sh_value2 = $ProjFields[$new_field_attach_sh]["element_preceding_header"];
				$sh_value = "$sh_value1<br><br>$sh_value2";
				// Add merged section header to the next field
				$sql = "UPDATE $metadata_table SET element_preceding_header = ? 
				        WHERE project_id = ? AND field_name = ?";
				db_query($sql, [$sh_value, $project_id, $new_field_attach_sh]);
				// Set old section header value to null
				$sql = "UPDATE $metadata_table SET element_preceding_header = NULL 
				        WHERE project_id = ? AND field_name = ?";
				db_query($sql, [$project_id, $field_name]);
				// Set MLM info
				$mlm_FROM_dest = $new_field_attach_sh;
				$mlm_FROM_prev = $field_name;
				$mlm_FROM_mode = 2;
			}
		}
	
		// If field was moved FROM directly under a Section Header, must move Section Header value onto that field
		else if (strpos($field_names, "$field_name-sh,") !== false && strpos($field_names, "$field_name-sh,$field_name,") === false) {
			// Get field name for attaching SH to
			$sh_pos = strpos($field_names, "$field_name-sh,") + strlen("$field_name-sh,");
			$comma_pos = strpos(substr($field_names, $sh_pos), ",");
			$new_field_attach_sh = substr($field_names, $sh_pos, $comma_pos);
			// Move section header to other field
			if ($new_field_attach_sh != "") {
				// Set new section header value for moved field after obtaining it first
				$sh_value = $ProjFields[$field_name]["element_preceding_header"];
				$sql = "UPDATE $metadata_table SET element_preceding_header = ? 
					    WHERE project_id = ? AND field_name = ?";
				db_query($sql, [$sh_value, $project_id, $new_field_attach_sh]);
				// Set old section header value to null
				$sql = "UPDATE $metadata_table SET element_preceding_header = NULL 
					    WHERE project_id = ? AND field_name = ?";
				db_query($sql, [$project_id, $field_name]);
				// Set MLM info
				$mlm_FROM_dest = explode("-", $new_field_attach_sh)[0];
				$mlm_FROM_prev = $field_name;
				$mlm_FROM_mode = 0;
			}
		}
	
		// If field was moved TO directly under a Section Header, must move Section Header value onto that field
		if (strpos($field_names, "-sh,$field_name,") !== false && strpos($field_names, "$field_name-sh,$field_name,") === false) {
			// Loop to get section header before current field
			$sh = "";
			foreach ($fields_array as $this_field) {
				if ($this_field == $field_name) {
					$sh = substr($prev_field, 0, -3);
				}
				$prev_field = $this_field;
			}
			// Move section header to newly moved field
			if ($sh != "") {
				// Set new section header value for moved field after obtaining it first
				// If field moved had a SH, add it to new one if two SH's end up adjacent
				$sh_value = ((isset($sh_value) && $sh_value != "" && preg_match("/($field_name-sh,)([a-z0-9_]+)(-sh,$field_name,)/", $field_names)) ? "$sh_value<br><br>" : "") . $ProjFields[$sh]["element_preceding_header"];
				$sql = "UPDATE $metadata_table SET element_preceding_header = ?
						WHERE project_id = ? AND field_name = ?";
				db_query($sql, [$sh_value, $project_id, $field_name]);
				// Set old section header value to null
				$sql = "UPDATE $metadata_table SET element_preceding_header = NULL 
						WHERE project_id = ? AND field_name = ?";
				db_query($sql, [$project_id, $sh]);
				// Set MLM info
				$mlm_TO_dest = $field_name;
				$mlm_TO_prev = $sh;
			}
		}
	
		// If field was moved FROM directly under a Section Header TO immeditately above that Section Header, must move Section Header to the field below it
		else if (strpos($field_names, "$field_name-sh,") !== false && $section_header == "" && strpos($field_names, "$field_name,$field_name-sh,") !== false) {
			// Get field name for attaching SH to
			$sh_pos = strpos($field_names, "$field_name-sh,") + strlen("$field_name-sh,");
			$comma_pos = strpos(substr($field_names, $sh_pos), ",");
			$new_field_attach_sh = substr($field_names, $sh_pos, $comma_pos);
			// Move section header to other field
			if ($new_field_attach_sh != "") {
				// Set new section header value for moved field after obtaining it first
				$sh_value = $ProjFields[$field_name]["element_preceding_header"];
				$sql = "UPDATE $metadata_table SET element_preceding_header = ?
						WHERE project_id = ? AND field_name = ?";
				db_query($sql, [$sh_value, $project_id, $new_field_attach_sh]);
				// Set old section header value to null
				$sql = "UPDATE $metadata_table SET element_preceding_header = NULL
						WHERE project_id = ? AND field_name = ?";
				db_query($sql, [$project_id, $field_name]);
				// Set MLM info
				$mlm_FROM_dest = explode("-", $new_field_attach_sh)[0];
				$mlm_FROM_prev = $field_name;
				$mlm_FROM_mode = 0;
			}
		}
	
		// MLM: Update any translations of that section header if already set (only in DEVELOPMENT status)
		if ($status == "0") {
			if (strlen($mlm_TO_dest??"") && strlen($mlm_TO_prev??"")) {
				MultiLanguage::moveSectionHeader($project_id, $mlm_TO_prev, $mlm_TO_dest, $mlm_TO_mode);
			}
			else if (strlen($mlm_FROM_dest??"") && strlen($mlm_FROM_prev??"")) {
				// Note: Depending on a potential merge fix, the merge mode may need to be set to 2 here
				MultiLanguage::moveSectionHeader($project_id, $mlm_FROM_prev, $mlm_FROM_dest, $mlm_FROM_mode);
			}
		}
	
		## Reset the form_menu_description for the first field on form (in case the first field is no longer the first field)
		Design::fixFormLabels();
	
		// Check if the table_pk has changed during the recording. If so, give back different response so as to inform the user of change.
		if (Design::recordIdFieldChanged()) {
			// This should never actually happen
			$message = [
				"title" => $lang["global_01"],
				"text" => "The record id field was changed. This should never happen!",
				"type" => "error"
			];
		}
	
		// Logging
		Logging::logEvent("",$metadata_table,"MANAGE",$form_name,"form_name = '$form_name'","Reorder project fields");
	} 
	
	// If the "field" moved was a Section Header, move only the section header value and
	// do logic checks to ensure no problems occur (such as 2 adjacent section headers)
	else {
		// Get section header value
		$sh_value = $ProjFields[$section_header]["element_preceding_header"];
	
		// Get new destination field that section header will be attached to
		$sh_dest_field = "";
		$prev_field = "";
		$double_secthdr = false;
		foreach ($fields_array as $this_field) {
			if ($prev_field == "$section_header-sh" || $double_secthdr) {
				// If we have two section headers in a row, then iterate one more loop
				if (substr($this_field, -3) == "-sh") {
					$double_secthdr = true;
				// Found destination field!
				} else {
					$double_secthdr = false;
					$sh_dest_field = $this_field;
				}
			}
			$prev_field = $this_field;
		}
	
		// If section header is being moved to another field...
		if ($sh_dest_field != "" && $sh_dest_field != $section_header) {
			// First, see if destination field already has a section header. If so, then merge the two (no other way to deal with this issue).
			$sh_dest_field_value = $ProjFields[$sh_dest_field]["element_preceding_header"] ?? "";
			if ($sh_dest_field_value != "") {
				// Append new section header to existing one and separate with line breaks
				$sh_value = "$sh_dest_field_value<br><br>$sh_value";
				// Set response as 2 to prompt alert box for user that section headers were merged
				$message = [
					"title" => $lang["global_48"],
					"text" => $lang["design_1340"],
					"type" => "warning"
				];
			}
			// Set new section header value
			$sql = "UPDATE $metadata_table SET element_preceding_header = ?
					WHERE project_id = ? AND field_name = ?";
			db_query($sql, [$sh_value, $project_id, $sh_dest_field]);
			// Set old section header value as NULL
			$sql = "UPDATE $metadata_table SET element_preceding_header = NULL 
					WHERE project_id = ? AND field_name = ?";
			db_query($sql, [$project_id, $section_header]);
			// Multi-Language Management
			// Update any translations of that section header if already set (only in DEVELOPMENT status)
			if ($status == "0") {
				MultiLanguage::moveSectionHeader($project_id, $section_header, $sh_dest_field);
			}
			// Logging
			Logging::logEvent("",$metadata_table,"MANAGE",$form_name,"form_name = '$form_name'","Reorder project fields");
		}
	}

} while (false);

// Build field types array for this form after reloading metadata
if ($status > 0) {
	$Proj->loadMetadataTemp();
	$ProjForms = $Proj->forms_temp;
	$ProjFields = $Proj->metadata_temp;
} else {
	$Proj->loadMetadata();
	$ProjForms = $Proj->forms;
	$ProjFields = $Proj->metadata;
}
$qef_meta = array();

// ACTION TAGS: Create regex string to detect all action tags being used in the Field Annotation
$action_tags_regex = Form::getActionTagMatchRegexOnlineDesigner();
$action_tags_regex2 = "/(@[A-Z0-9\-]+)($|[^(\-)])/"; // Display all action tags, including those not bundled in REDCap (i.e., from External Modules)

foreach ($ProjForms[$form_name]["fields"] as $this_field_name => $_) {
	$row = $ProjFields[$this_field_name];
	$stop_actions = (isset($Proj->forms[$form_name]["survey_id"])) ? DataEntry::parseStopActions($row["stop_actions"] ?? "") : ""; // Yes, use $Proj here!

	$qef_meta["design-".$this_field_name] = [
		"name" => $this_field_name,
		"isFormStatus" => $this_field_name == "{$form_name}_complete",
		"hasSectionHeader" => !empty($row["element_preceding_header"]),
		"type" => $row["element_type"],
		"validation" => $row["element_validation_type"],
		"hasBranchingLogic" => !empty(trim($row["branching_logic"] ?? "")),
		"hasAttachment" => intval($row["edoc_id"]) > 0,
		"hasVideo" => !empty(trim($row['video_url'] ?? "")),
		"hasStopActions" => !empty($stop_actions),
		"customAlignment" => $row["custom_alignment"] ?? "RV",
		"isRequired" => $row["field_req"] == "1",
		"isPHI" => $row["field_phi"] == "1",
		"hasAnnotation" => !empty($row["misc"]),
		"hasActionTags" => preg_match($action_tags_regex, $row["misc"]??"") || preg_match($action_tags_regex2, $row["misc"]??""),
		"misc" => $row["misc"] ?? "",
		"isMatrixField" => !empty($row["grid_name"]),
		"matrixGroup" => trim($row['grid_name'] ?? ""),
		"questionNum" => $row["question_num"],
		"order" => intval($row["field_order"]),
	];
}

// Send response
header("Content-Type: application/json");
print json_encode_rc([
	"message" => $message,
	"fieldTypes" => $qef_meta
]);

