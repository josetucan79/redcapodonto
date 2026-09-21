<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Determine if adding to very bottom of table or not// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

$_POST['field_name'] = preg_replace("/[^a-z0-9_]/", "", str_replace(" ", "_", strtolower(html_entity_decode($_POST['field_name'], ENT_QUOTES))));
$_POST['form_name'] = preg_replace("/[^a-z0-9_]/", "", str_replace(" ", "_", strtolower(html_entity_decode($_POST['form_name'], ENT_QUOTES))));

$sql = "SELECT 
            COUNT(*) AS total 
        FROM 
            $metadata_table 
        WHERE 
            project_id = $project_id AND field_name = '{$_POST['field_name']}' 
        LIMIT 1";
$q = db_query($sql);
$total_rows = db_result($q, 0, 'total');
if ($total_rows > 0) {
    list($root, $num, $padding) = determine_repeat_parts($_POST['field_name']);
    do {
        $num++;
        $suffix_padded = str_pad($num, $padding, '0', STR_PAD_LEFT);
        $new_field_name = $root . $suffix_padded;

        $sql = "SELECT 
                    COUNT(1) 
                FROM 
                    $metadata_table 
                WHERE 
                    project_id = $project_id AND field_name = '$new_field_name'";
        $varExists = db_result(db_query($sql), 0);
    } while ($varExists);

    $_POST['field_name'] = $new_field_name;
}

$is_last = ($_POST['this_sq_id'] == "") ? 1 : 0;

// Required Field value
$_POST['field_req'] = 0;
// Clean the variable name
$_POST['field_name'] = preg_replace("/[^0-9a-z_]/", "", strtolower($_POST['field_name']));

// Set below values to defaults for insert query
$_POST['edoc_id']   = "";
$_POST['video_url'] = "";
$_POST['video_display_inline'] = '0';
$_POST['edoc_display_img'] = 0;
$_POST['custom_alignment'] = "";
$has_ontology_provider = false;
$enable_ontology_auto_suggest_field = false;
$delete_row = "";
// If field_type is missing, then set as Text field
if (!isset($_POST['field_type']) || (isset($_POST['field_type']) && $_POST['field_type'] == "")) {
	$_POST['field_type'] = 'text';
}


switch($_POST['field_type']) {
    case "date":
        $_POST['field_type'] = "text";
        $_POST['val_type'] = "date_mdy";
        break;
    case "time":
        $_POST['field_type'] = "text";
        $_POST['val_type'] = "time";
        break;
    case "file":
        $_POST['val_type'] = '';
        break;
    case "number":
        $_POST['field_type'] = "text";
        $_POST['val_type'] = "float";
        break;
    case "value list":
        $_POST['field_type'] = "radio";
        $_POST['element_enum'] = DataEntry::autoCodeEnum($_POST['element_enum']);
        break;
	case "dropdown":
		$_POST['field_type'] = "select";
		break;
}

if ($_POST['field_type'] != "text")
{
    $_POST['val_type'] = "";
    $_POST['val_min'] = "";
    $_POST['val_max'] = "";
}
// set section header to 0 as we will not get datatype as section header
$is_section_header = 0;

// Field Annotation
$_POST['field_annotation'] = "";
if (isset($_POST['tinyId'])) {
	$_POST['field_annotation'] = "tinyId=".$_POST['tinyId'];
	$_POST['publicId'] = "";
	$_POST['questionId'] = "";
} else if(isset($_POST['publicId'])) {
	$_POST['field_annotation'] = "publicId=".$_POST['publicId'];
	$_POST['tinyId'] = "";
	$_POST['questionId'] = "";
} else if(isset($_POST['questionId'])) {
	$_POST['field_annotation'] = "questionId=".$_POST['questionId'];
	$_POST['tinyId'] = "";
	$_POST['publicId'] = "";
}

if (!isset($_POST['field_phi'])) $_POST['field_phi'] = "";
if (!isset($_POST['element_enum'])) $_POST['element_enum'] = "";
if (!isset($_POST['val_min'])) $_POST['val_min'] = "";
if (!isset($_POST['val_max'])) $_POST['val_max'] = "";
if (!isset($_POST['grid_name '])) $_POST['grid_name '] = "";

/**
 * ADDING NEW QUESTION
 */

// Reformat value if adding field directly above a Section Header (i.e. ends with "-sh")
if (substr($_POST['this_sq_id'], -3) == "-sh") {
    $_POST['this_sq_id'] = substr($_POST['this_sq_id'], 0, -3);
    $possible_sh_attached = false;
} else {
    // Set flag and check later if field directly below has a Section Header (i.e. are we adding a field "between" a SH and a field?)
    $possible_sh_attached = true;
}

// EXISTING FORM
$form_menu_description = "NULL";
// Determine if adding to very bottom of table or not. If so, get position of last field on form + 1
if ($is_last) {
    $sql = "SELECT MAX(field_order) FROM $metadata_table WHERE project_id = $project_id AND form_name = '{$_POST['form_name']}'";
} else {
    $sql = "SELECT field_order FROM $metadata_table WHERE project_id = $project_id AND field_name = '".db_escape($_POST['this_sq_id'])."' LIMIT 1";
}

// Get the following question's field order
$new_field_order = db_result(db_query($sql), 0);
// Increment added to all fields occurring after this new one. If creating a new form, also add extra increment
// number for field_order to give extra room for the Form Status field created
$increase_field_order = 1;

// Increase field_order of all fields after this new one
db_query("UPDATE $metadata_table SET field_order = field_order + $increase_field_order WHERE project_id = $project_id AND field_order >= $new_field_order");
// Set associated values for query
$element_validation_checktype = "";
if ($_POST['field_type'] == "text") {
    $element_validation_checktype = "soft_typed";
// Parse multiple choices
}
// Query to create new field
$sql = "INSERT INTO $metadata_table (project_id, field_name, field_phi, form_name, form_menu_description, field_order,
        field_units, element_preceding_header, element_type, element_label, element_enum, element_note, element_validation_type,
        element_validation_min, element_validation_max, element_validation_checktype, branching_logic, field_req,
        edoc_id, edoc_display_img, custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc, video_url, video_display_inline)
        VALUES
        ($project_id, '".db_escape($_POST['field_name'])."', " . checkNull($_POST['field_phi']) . ", "
     . "'{$_POST['form_name']}', $form_menu_description, '$new_field_order', NULL, NULL, "
     . checkNull($_POST['field_type']) . ", "
     . checkNull($_POST['field_label']) . ", "
     . checkNull($_POST['element_enum']) . ", "
     . checkNull($_POST['field_note']) . ", "
     . checkNull($_POST['val_type']) . ", "
     . checkNull($_POST['val_min']) . ", "
     . checkNull($_POST['val_max']) . ", "
     . checkNull($element_validation_checktype) . ", "
     . "NULL, "
     . "'{$_POST['field_req']}', "
     . checkNull($_POST['edoc_id']) . ", "
     . $_POST['edoc_display_img'] . ", "
     . checkNull($_POST['custom_alignment']) . ", "
     . "NULL, "
     . checkNull(isset($_POST['question_num']) ? $_POST['question_num'] : null) . ", "
     . checkNull($grid_name) . ", "
     . "0, "
     . checkNull(trim($_POST['field_annotation'])) . ", "
     . checkNull($_POST['video_url']) . ", "
     . checkNull($_POST['video_display_inline'])
     . ")";
$q = db_query($sql);

// Logging
if ($q) {
    Logging::logEvent($sql,$metadata_table,"MANAGE",$_POST['field_name'],"field_name = '{$_POST['field_name']}'","Create project field from Field Bank");
    //Query to insert in redcap_cde_field_mapping
    $sql = "INSERT INTO redcap_cde_field_mapping (project_id, field_name, tinyId, publicId, questionId, web_service, steward, org_selected)
        VALUES
        ($project_id, ".checkNull($_POST['field_name']).", " . checkNull($_POST['tinyId']) . ", " . checkNull($_POST['publicId']) . ", " . checkNull($_POST['questionId']) . ", " . checkNull($_POST['service_name']) . ", " . checkNull($_POST['steward']) .", " . checkNull($_POST['used_by']) .")";

    $query = db_query($sql);
} else {
    // UNDO previous "reorder" query: Decrease field_order of all fields after where this new one should've gone
    db_query("UPDATE $metadata_table SET field_order = field_order - $increase_field_order
                 WHERE project_id = $project_id AND field_order >= ".($new_field_order + $increase_field_order));
    // If field failed to save, then give error msg and reload form completely
    print  "<script type='text/javascript'>
            window.parent.window.alert(window.parent.window.woops);
            window.parent.window.reloadDesignTable('{$_POST['form_name']}');
            </script>";

    exit;
}

## SECTION HEADER PLACEMENT
// Check if we are adding a field "between" a SH and a field? If so, move SH to new field from one directly after it.
$reloadDesignTable = false;
if ($possible_sh_attached && !$is_last)
{
    $sql = "SELECT element_preceding_header FROM $metadata_table WHERE project_id = $project_id AND form_name = '{$_POST['form_name']}'
            AND field_order = (SELECT field_order+1 FROM $metadata_table WHERE project_id = $project_id
            AND field_name = '{$_POST['field_name']}' LIMIT 1) AND element_preceding_header IS NOT NULL LIMIT 1";
    $q = db_query($sql);
    if (db_num_rows($q) > 0) {
        // Yes, we are adding a field "between" a SH and a field. Move the SH to the field we just created.
        $sh_value = db_result($q, 0);

        $sql = "UPDATE $metadata_table SET element_preceding_header = " . checkNull($sh_value) . " WHERE project_id = $project_id
                AND field_name = '{$_POST['field_name']}' LIMIT 1";
        $q = db_query($sql);
        // Get name of field directly after the new one we created.
        $sql = "SELECT field_name FROM $metadata_table WHERE project_id = $project_id AND form_name = '{$_POST['form_name']}'
                AND field_order = ".($new_field_order+1)." LIMIT 1";
        $following_field = db_result(db_query($sql), 0);
        // Set SH value from other field to NULL now that we have copied it to new field
        $sql = "UPDATE $metadata_table SET element_preceding_header = NULL WHERE project_id = $project_id AND field_name = '$following_field' LIMIT 1";
        $q = db_query($sql);
        // Set value for row in table to be deleted in DOM (delete section header on following field, which is now null)
        $delete_row = $following_field . "-sh";
        // Set flag to reload table on page
        $reloadDesignTable = true;
    }
}

## FORM MENU: Always make sure the form_menu_description value stays only with first field on form
// Set all field's form_menu_description as NULL
$sql = "UPDATE $metadata_table SET form_menu_description = NULL WHERE project_id = $project_id AND form_name = '{$_POST['form_name']}'";
db_query($sql);
// Now set form_menu_description for first field
$form_menu = ($status > 0) ? $Proj->forms_temp[$_POST['form_name']]['menu'] : $Proj->forms[$_POST['form_name']]['menu'];
$sql = "UPDATE $metadata_table SET form_menu_description = '".db_escape(label_decode($form_menu))."'
        WHERE project_id = $project_id AND form_name = '{$_POST['form_name']}' ORDER BY field_order LIMIT 1";
db_query($sql);

// Reload form completely in order to associate section header with newly added field below it
if ($reloadDesignTable) {
    print  "<script type='text/javascript'>
            window.parent.window.reloadDesignTable('{$_POST['form_name']}');
            </script>";
    exit;
}

// Insert row into table
print  "<script type='text/javascript'>
        window.parent.window.insertRowFromQuestion('draggable', '{$_POST['field_name']}', '{$_POST['field_type']}', '".htmlspecialchars($_POST['this_sq_id'], ENT_QUOTES)."', $is_last, '$delete_row');        
        </script>";


// Field Embedding: Get a list of all embedded fields on this instrument to toggle the button/div for each field
$Proj->loadMetadata();
$embeddedVarsThisInstrument = Piping::getEmbeddedVariables(PROJECT_ID, $_POST['form_name'], null, true);
$embeddedVarsThisField = Piping::getEmbeddedVariablesForField(PROJECT_ID, $_POST['field_name'], true);
foreach ($embeddedVarsThisInstrument as &$this_field) $this_field = "tr#".$this_field."-tr";
print  "<script type='text/javascript'>
		window.parent.window.toggleEmbeddedFieldsButtonDesigner('".implode(",", $embeddedVarsThisInstrument)."');
		</script>";