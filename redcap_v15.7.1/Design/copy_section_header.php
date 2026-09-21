<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// If project is in production and another user just changed its draft_mode status, don't allow any actions here if not in draft mode
if ($status > 0 && $draft_mode != '1') exit("0");

$required_post = ["form", "srcField", "targetField"];
foreach ($required_post as $key) {
    if (!isset($_POST[$key]) || empty($_POST[$key])) exit("0");
}

// Get items from POST
$form = strip_tags($_POST["form"]);
$src_field = strip_tags($_POST["srcField"]);
$target_field = strip_tags($_POST["targetField"]);

// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
$ProjForms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;
$ProjFields = ($status > 0) ? $Proj->metadata_temp : $Proj->metadata;

// Validate form and fields
if (!array_key_exists($form, $ProjForms)) exit("0");
foreach ([$src_field, $target_field] as $field) {
    if (!array_key_exists($field, $ProjFields) || $ProjFields[$field]['form_name'] != $form) exit("0");
}
// Source must have a SH, target must not
if ($ProjFields[$src_field]["element_preceding_header"] == null) exit("0");
if ($ProjFields[$target_field]["element_preceding_header"] != null) exit("0");

// Set up SQL and logging statements
$sql = "UPDATE $metadata_table 
        SET element_preceding_header = ? 
        WHERE project_id = ? AND field_name = ?";
$params = [
    $ProjFields[$src_field]["element_preceding_header"],
    $Proj->project_id,
    $target_field,
];
$log_description = "Copy section header";
$log_type = "element_preceding_header";

// Execute the query
$q = db_query($sql, $params);

// Log this event
$fieldNamesLog = "source_field = '$src_field'\ntarget_field = '$target_field'";
Logging::logEvent($sql, $metadata_table, "MANAGE", $log_type, $fieldNamesLog, $log_description);

exit("1");