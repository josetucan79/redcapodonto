<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Set appropriate data sources / table names
$ProjForms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;
$ProjFields = ($status > 0) ? $Proj->metadata_temp : $Proj->metadata;
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

// GET
if (isset($_GET["goto-field"]) && isset($_GET["form"])) {
    if (!array_key_exists($_GET["form"], $ProjForms)) exit("ERROR");
    // Prepend current form's fields
    $all_fields = array_unique(array_merge(array_keys($ProjForms[$_GET["form"]]["fields"]), array_keys($ProjFields)));
    // Create dropdown
    $all_fields_dd = "<option data-verification='qef-goto-fields' value=''></option>";
    $prevform = "";
    foreach ($all_fields as $thisfield) {
        $attr = $ProjFields[$thisfield];
        // Get current form
        $thisform = $attr['form_name'];
        // Exclude if this form is active task added for MyCap
        if (($GLOBALS["myCapProj"]->tasks[$thisform]['is_active_task'] ?? null) == 1) continue;
        // If we're beginning a new form, then display form menu label as optgroup label
        if (count($ProjForms) > 1 && $thisform != $prevform) {
            // Close previous optgroup
            if ($prevform != "") $all_fields_dd .= "</optgroup>";
            // Add optgroup
            $all_fields_dd .= "<optgroup label='" . RCView::escape($attr['form_menu_description']) . "'>";
        }
        // If there are no fields in this form, add a corresponding note
        if (count($ProjForms[$thisform]["fields"]) == 1) {
            $all_fields_dd .= "<option value='-form-{$thisform}'>{$lang['design_1120']}</option>";
        }
        // Do not include the Form Status fields
        if ($thisfield == $thisform.'_complete') continue;
        // Add the field
        // Add option
        $label = strip_tags(label_decode($attr['element_label']));
        $short_label = (mb_strlen($attr['element_label']) > 60) ? mb_substr($label, 0, 58)."..." : $label;
        $search = base64_encode(mb_strtolower($thisfield." ".str_replace(["\r\n", "\n", "\r"], " ", $label)));
        $all_fields_dd .= "<option data-form='$thisform' data-search=\"$search\" value='$thisfield'>$thisfield " . RCView::SP . RCView::escape('"' . $short_label . '"') . "</option>";
        // Set for next loop
        $prevform = $thisform;
    }
    print $all_fields_dd;
    exit;
}

// UI State update
if (($_POST['uiState'] ?? "0") == "1") {
    if (isset($_POST["qef-preferred-location"])) {
        $state = $_POST["qef-preferred-location"];
        if (in_array($state, ["right", "top"], true)) {
            $current = UIState::getUIStateValue("", "online-designer", "qef-preferred-location");
            if ($current != $state) {
                UIState::saveUIStateValue("", "online-designer", "qef-preferred-location", $state);
            }
        }
        else exit("0");
    }
    else if (isset($_POST["dismissed_new_drag_and_drop_info"])) {
        if ($_POST["dismissed_new_drag_and_drop_info"] != "1") exit("0");
        UIState::saveUIStateValue("", "online-designer", "dismissed_new_drag_and_drop_info", "1");
    }
    exit("1");
}

// If project is in production and another user just changed its draft_mode status, don't allow any actions here if not in draft mode
if ($status > 0 && $draft_mode != '1') exit("ERROR");

$required_post = ["fields", "form", "type", "mode"];
foreach ($required_post as $key) {
    if (!isset($_POST[$key]) || empty($_POST[$key])) exit("0");
}

// Get required items from POST
$form = strip_tags($_POST["form"]);
$all_fields = explode(', ', strip_tags($_POST["fields"]));
$type = strip_tags($_POST["type"]);
$mode = strip_tags($_POST["mode"]);
// Get optional items
$current = isset($_POST["current"]) ? trim(strip_tags($_POST["current"])) : "";
$custom = isset($_POST["custom"]) ? $_POST["custom"] : "";
// Reset current for certain types
if (in_array($type, ["align", "phi", "required"], true)) {
    $current = "";
}

// Validate form and fields
if (!array_key_exists($form, $ProjForms)) exit("0");
$fields = [];
foreach ($all_fields as $field) {
    if (!array_key_exists($field, $ProjFields) || $ProjFields[$field]['form_name'] != $form) exit("0");
    // Skip all descriptive fields if type is "phi" or "required"
    if (in_array($type, ["phi", "required"], true) && $ProjFields[$field]["element_type"] == "descriptive") continue;
    // Skip all grid fields if type is "align"
    if ($type == "align" && !empty($ProjFields[$field]["grid_name"])) continue;
    $fields[] = $field;
}
// Current, when present, must be one of the fields
if ($current !== "" && !in_array($current, $fields, true)) exit("0");

// If there are no fields to update, exit
if (empty($fields)) exit("1");

// Default respnse
$response = "1";
// Validate type and mode and set up SQL and logging statements 
if ($type === "align") {
    if (!in_array($mode, ["RV", "RH", "LV", "LH"], true)) exit("0");
    $sql = "UPDATE $metadata_table 
            SET custom_alignment = ? 
            WHERE project_id = ? 
              AND field_name IN ('" . implode("', '", $fields) . "')";
    $params = [
        $mode == "RV" ? null : $mode,
        $Proj->project_id
    ];
    $log_description = "Quick-modify field(s) - Set custom alignment to $mode";
    $log_type = "custom_alignment";
}
else if ($type === "phi") {
    if (!in_array($mode, ["ON", "OFF"], true)) exit("0");
    $sql = "UPDATE $metadata_table 
            SET field_phi = ? 
            WHERE project_id = ? 
              AND field_name IN ('" . implode("', '", $fields) . "')";
    $params = [
        $mode == "ON" ? 1 : null,
        $Proj->project_id
    ];
    $log_description = "Quick-modify field(s) - Set identifier to $mode";
    $log_type = "field_phi";
}
else if ($type === "required") {
    if (!in_array($mode, ["ON", "OFF"], true)) exit("0");
    $sql = "UPDATE $metadata_table 
            SET field_req = ? 
            WHERE project_id = ? 
              AND field_name IN ('" . implode("', '", $fields) . "')";
    $params = [
        $mode == "ON" ? 1 : 0,
        $Proj->project_id
    ];
    $log_description = "Quick-modify field(s) - Set required to $mode";
    $log_type = "field_req";
}
else if ($type == "branchinglogic") {
    if (empty($current)) exit("0");
    if (!in_array($mode, ["clear", "copy", "new"], true)) exit("0");
    // Remove branching logic
    if ($mode == "clear") {
        $sql = "UPDATE $metadata_table 
                SET branching_logic = NULL 
                WHERE project_id = ? 
                  AND field_name IN ('" . implode("', '", $fields) . "')";
        $params = [
            $Proj->project_id
        ];
        $log_description = "Quick-modify field(s) - Clear branching logic";
        $log_type = "branching_logic";
    }
    // Copy branching logic
    else if ($mode == "copy") {
        $current_bl = $ProjFields[$current]["branching_logic"];
        if (empty($current_bl)) exit("0");
        unset($fields[$current]);
        if (count($fields) < 1) exit("0");
        $sql = "UPDATE $metadata_table 
                SET branching_logic = ? 
                WHERE project_id = ? 
                  AND field_name IN ('" . implode("', '", $fields) . "')";
        $params = [
            $current_bl,
            $Proj->project_id
        ];
        $log_description = "Quick-modify field(s) - Copy branching logic from $current";
        $log_type = "branching_logic";
    }
    // Set new branching logic
    else if ($mode == "new") {
        if (empty($custom)) exit("0");
        $response = [];
        foreach ($fields as $field) {
            $this_response = BranchingLogic::save($field, $custom);
            if (!in_array($this_response, ["1", "2", "3", "4"])) $response[] = $this_response;
        }
        $sql = "";
        $response = empty($response) ? "1" : implode("<br>", $response);
        $log_description = "Quick-modify field(s) - Set branching logic";
        $log_type = "branching_logic";
    }
}
else if ($type == "actiontags") {
    if (empty($current)) exit("0");
    if (!in_array($mode, ["clear", "copy", "append", "deactivate", "reactivate", "update"], true)) exit("0");
    if ($mode == "append" && empty($custom)) exit("0");
    // Remove action tags / field annotation
    if ($mode == "clear") {
        $sql = "UPDATE $metadata_table 
                SET misc = NULL 
                WHERE project_id = ? 
                  AND field_name IN ('" . implode("', '", $fields) . "')";
        $params = [
            $Proj->project_id
        ];
        $log_description = "Quick-modify field(s) - Clear action tags / field annotation";
        $log_type = "misc";
    }
    // Copy action tags / field annotation
    else if ($mode == "copy") {
        $current_misc = $ProjFields[$current]["misc"];
        if (empty($current_misc)) exit("0");
        unset($fields[$current]);
        if (count($fields) < 1) exit("0");
        $sql = "UPDATE $metadata_table 
                SET misc = ? 
                WHERE project_id = ? 
                  AND field_name IN ('" . implode("', '", $fields) . "')";
        $params = [
            $current_misc,
            $Proj->project_id
        ];
        $log_description = "Quick-modify field(s) - Copy action tags / field annotation from $current";
        $log_type = "misc";
    }
    // Append custom content to misc for all selected fields
    else if ($mode == "append") {
        foreach ($fields as $field) {
            $misc = $ProjFields[$field]["misc"] ?? "";
            $misc = trim($misc."\n".$custom);
            if ($misc == "") $misc = null;
            $sql[] = "UPDATE $metadata_table
                      SET misc = ?
                      WHERE project_id = ?
                        AND field_name = ?";
            $params[] = [
                $misc,
                $Proj->project_id,
                $field
            ];
        }
        $log_description = "Quick-modify field(s) - Append to action tags / field annotation";
        $log_type = "misc";
    }
    // Deactivate all action tags (by replacing the @ with @.OFF.)
    if ($mode == "deactivate") {
        foreach ($fields as $field) {
            $misc = $ProjFields[$field]["misc"];
            if (empty($misc) || strpos($misc, "@") === false) continue;
            // Are there any filters?
            $filter_by = array_unique(ActionTags::getActionTags($custom ?? ""));
            // Deactivate
            $misc = ActionTags::deactivateActionTags($misc, $filter_by);
            // Add the deactivation marker
            if (ActionTags::hasDeactivatedActionTags($misc)) {
                $misc = ActionTags::addDeactivatedActionTagsMarker($misc);
            }
            else {
                $misc = ActionTags::removeDeactivatedActionTagsMarker($misc);
            }
            $sql[] = "UPDATE $metadata_table 
                    SET misc = ? 
                    WHERE project_id = ? AND field_name = ?";
            $params[] = [
                $misc,
                $Proj->project_id,
                $field
            ];
        }
        $log_description = "Quick-modify field(s) - Deactivate all action tags";
        $log_type = "misc";
    }
    // Reactivate all action tags (by replacing @.OFF. with @)
    if ($mode == "reactivate") {
        foreach ($fields as $field) {
            $misc = $ProjFields[$field]["misc"];
            if (empty($misc)) continue;
            // Are there any filters?
            $filter_by = array_unique(ActionTags::getActionTags($custom ?? ""));
            // Reactivate
            $misc = ActionTags::reactivateActionTags($misc, $filter_by);
            // When there are no more deactivated tags, remove the @DEACTIVATED-ACTION-TAGS marker
            if (!ActionTags::hasDeactivatedActionTags($misc)) {
                $misc = ActionTags::removeDeactivatedActionTagsMarker($misc);
            }
            else {
                $misc = ActionTags::addDeactivatedActionTagsMarker($misc);
            }
            $sql[] = "UPDATE $metadata_table 
                      SET misc = ? 
                      WHERE project_id = ? AND field_name = ?";
            $params[] = [
                $misc,
                $Proj->project_id,
                $field
            ];
        }
        $log_description = "Quick-modify field(s) - Reactivate all action tags";
        $log_type = "misc";
    }
    // Update the current field's action tags / annotation
    if ($mode == "update") {
        $sql[] = "UPDATE $metadata_table 
                  SET misc = ? 
                  WHERE project_id = ? AND field_name = ?";
        $params[] = [
            $custom,
            $Proj->project_id,
            $current
        ];
        $fields = [$current];
        $log_description = "Quick-modify field(s) - Edit action tags / field annotation";
        $log_type = "misc";
    }
}
else if ($type == "question-number") {
    if (!in_array($mode, ["set"], true)) exit("0");
    if ($custom == "") $custom = null;
    if ($mode == "set") {
        $sql = "UPDATE $metadata_table 
                  SET question_num = ? 
                  WHERE project_id = ? AND field_name = ?";
        $params = [
            $custom,
            $Proj->project_id,
            $fields[0]
        ];
        $log_description = "Quick-modify field(s) - Set question number";
        $log_type = "question_num";
    }
}
else if ($type == "choices") {
    if (!in_array($mode, ["append", "copy", "convert", "edit"], true)) exit("0");
    // Validate fields and determine if any are in a matrix group
    $apply_to_fields = [];
    $grids = [];
    foreach ($fields as $field) {
        $attr = $ProjFields[$field];
        if (!in_array($attr["element_type"], ["radio", "checkbox", "select", "yesno", "truefalse"], true)) exit("0");
        // Skip yesno/truefalse fields
        if ($attr["element_type"] == "yesno" || $attr["element_type"] == "truefalse") continue;
        if (!empty($attr["grid_name"])) {
            $grids[$attr["grid_name"]] = true;
        }
        $apply_to_fields[] = $field;
    }
    // Add all fields from all matrix groups
    if (count($grids)) {
        foreach ($ProjForms[$form]["fields"] as $form_field => $_) {
            if (array_key_exists($ProjFields[$form_field]["grid_name"], $grids)) {
                $apply_to_fields[] = $form_field;
            }
        }
    }
    $fields = array_unique($apply_to_fields);

    // Copy
    if ($mode == "copy") {
        unset($fields[$current]);
        // Any targets left?
        if (count($fields) < 1) exit("0");
        // Does source field have choices?
        $source_enum = $ProjFields[$current]['element_enum'] ?? "";
        if (count(parseEnum($source_enum)) < 1) exit("0");
        // Prepare SQL
        $sql = "UPDATE $metadata_table 
                SET element_enum = ? 
                WHERE project_id = ? AND field_name IN ('" . implode("', '", $fields) . "')";
        $params = [
            $source_enum,
            $Proj->project_id
        ];
        $log_description = "Quick-modify field(s) - Copy choices from $current";
        $log_type = "element_enum";
    }
    else if ($mode == "append") {
        // Nothing to do? This should NEVER happen qua client rules
        if (!is_array($custom) || count($custom) == 0) exit("0");
        foreach ($fields as $field) {
            // Get current choices
            $choices = parseEnum($ProjFields[$field]['element_enum'] ?? "");
            // Add (merge) new choices
            foreach ($custom as $code_label) {
                $choices[$code_label["code"]] = $code_label["label"];
            }
            $updated_enum = implode("\n", array_map(function ($code, $label) {
                return $code . ", " . $label;
            }, array_keys($choices), array_values($choices)));
            // Prepare SQL
            $sql[] = "UPDATE $metadata_table 
                      SET element_enum = ? 
                      WHERE project_id = ? AND field_name = ?";
            $params[] = [
                $updated_enum,
                $Proj->project_id,
                $field
            ];
        }
        $log_description = "Quick-modify field(s) - Append choices";
        $log_type = "element_enum";
    }
    else if ($mode == "convert") {
        if (!in_array($custom, ["radio", "checkbox", "select", "autocomplete"], true)) exit("0");
        $changed_fields = [];
        foreach ($fields as $field) {
            // Set type
            $element_type = $custom == "autocomplete" ? "select" : $custom;
            // Skip grid fields when converting to select/autocomplete
            if ($element_type == "select" && !empty($ProjFields[$field]["grid_name"])) continue;
            $element_validation_type = $custom == "autocomplete" ? "autocomplete" : null;
            // Prepare SQL
            $sql[] = "UPDATE $metadata_table 
                      SET element_type = ?, element_validation_type = ?, grid_rank = '0' 
                      WHERE project_id = ? AND field_name = ?";
            $params[] = [
                $element_type,
                $element_validation_type,
                $Proj->project_id,
                $field
            ];
            $changed_fields[] = $field;
        }
        $fields = $changed_fields;
        $log_description = "Quick-modify field(s) - Convert field type to $custom";
        $log_type = "element_type";
    }
    else if ($mode == "edit") {
        // At least one choice must be present
        $custom = trim($custom);
        if (count(parseEnum($custom)) < 1) exit("0");
        // Prepare SQL
        $sql = "UPDATE $metadata_table 
                SET element_enum = ? 
                WHERE project_id = ? AND field_name = ?";
        $params = [
            $custom,
            $Proj->project_id,
            $current
        ];
        $log_description = "Quick-modify field(s) - Update choices";
        $log_type = "element_enum";
    }
}
else {
    // Invalid type
    exit("0");
}

// Execute the query or queries
$log_sql = [];
if (!empty($sql)) {
    if (!is_array($sql)) {
        $sql = [$sql];
        $params = [$params];
    }
    for ($i = 0; $i < count($sql); $i++) {
        $q = db_query($sql[$i], $params[$i]);
        $log_sql[] = System::pseudoInsertQueryParameters($sql[$i], $params[$i], true);
    }
}
// Log this event
$fieldNamesLog = "fields: " . implode(", ", $fields);
Logging::logEvent(implode("\n", $log_sql), $metadata_table, "MANAGE", $log_type, $fieldNamesLog, $log_description);


exit($response);