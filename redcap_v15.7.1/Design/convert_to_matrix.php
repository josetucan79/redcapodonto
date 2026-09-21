<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// If project is in production and another user just changed its draft_mode status, don't allow any actions here if not in draft mode
if ($status > 0 && $draft_mode != '1') exit("ERROR");

if (!isset($_POST["fields"]) || !isset($_POST["form"])) exit("0");

// Get form and fields
$form = strip_tags($_POST["form"]);
$fields = explode(', ', strip_tags($_POST["fields"]));

// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
$ProjForms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;
$ProjFields = ($status > 0) ? $Proj->metadata_temp : $Proj->metadata;

// Validate form and fields
if (!array_key_exists($form, $ProjForms)) exit("0");
foreach ($fields as $field) {
    if (!array_key_exists($field, $ProjFields) || $ProjFields[$field]['form_name'] != $form) exit("0");
}
// First field must be checkbox, radio, yesno, or truefalse
if (!in_array($ProjFields[$fields[0]]['element_type'], array("checkbox","radio","yesno","truefalse"))) exit("0");

// Find a suitable matrix group name
$existing_matrix_groups = [];
foreach ($ProjFields as $field) {
    if (!empty($field["grid_name"]) && !in_array($field["grid_name"], $existing_matrix_groups)) {
        $existing_matrix_groups[] = $field["grid_name"];
    };
}
do {
    $grid_name = "mg_".substr(sha1(rand()), 0, 6);
}
while (in_array($grid_name, $existing_matrix_groups));

// Apply choices from first field to all fields
$element_enum = $ProjFields[$fields[0]]['element_enum'];
$element_type = $ProjFields[$fields[0]]['element_type'];
if ($element_type != "checkbox") $element_type = "radio";

// Remove all section headers but from the first field
$all_fields_but_first = array_slice($fields, 1);
$all_sql = [];
if (count($all_fields_but_first) > 0) {
    $sql = "UPDATE $metadata_table 
            SET element_preceding_header = NULL
            WHERE project_id = ? 
              AND field_name IN ('" . implode("', '", $all_fields_but_first) . "')";
    $q = db_query($sql, [ $Proj->project_id ]);
    $all_sql[] = $sql;
}
// Update all fields with the matrix name and the choices of the first field
$sql = "UPDATE $metadata_table 
        SET grid_name = ?, grid_rank = '0', element_type = ?, element_enum = ?, element_note = NULL, custom_alignment = NULL 
        WHERE project_id = ? 
          AND field_name IN ('" . implode("', '", $fields) . "')";
$q = db_query($sql, [ $grid_name, $element_type, $element_enum, $Proj->project_id ]);
$all_sql[] = $sql;

// Log this event
$fieldNamesLog = "grid_name = '$grid_name}'\nfield_name = '" . implode("'\nfield_name = '", $fields) . "'";
Logging::logEvent(implode("\n\n", $all_sql), $metadata_table, "MANAGE", $grid_name, $fieldNamesLog, "Convert to matrix of fields");

exit("1");