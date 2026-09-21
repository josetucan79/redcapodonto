<?php

/*
* Rename instrument (display) and form names
* 
* Hard errors will exit with '0' (incl. tampering attempts), leading to a woops; 
* validation errors will lead to a JSON response with appropriate error messages.
* In case of success, '1' is returned
* 
*/

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

const MAX_NAME_LENGTH = 50;
const MAX_LABEL_LENGTH = 200;

$forms = $Proj->project['draft_mode'] == 1 ? $Proj->forms_temp : $Proj->forms;
$fields = $Proj->project['draft_mode'] == 1 ? $Proj->metadata_temp : $Proj->metadata;
$metadata_table = $Proj->project['draft_mode'] == 1 ? "redcap_metadata_temp" : "redcap_metadata";

// Hard requirements
$required_post = ['action', 'displayName', 'prevFormName', 'newFormName', 'changeSurvey', 'changeTask'];
foreach ($required_post as $key) {
    if (!array_key_exists($key, $_POST)) exit('0');
}
$action = strip_tags(label_decode($_POST['action']));

// Action allowed?
if (!in_array($action, ['edit-form-name'], true)) exit('0');

// Validation messages
$val_messages = [];

// Extract from $_POST
$form_name = strip_tags(label_decode($_POST['prevFormName']));
$new_form_name = strip_tags(label_decode($_POST['newFormName']));
$new_form_label = strip_tags(label_decode($_POST['displayName']));
$change_survey_title = $_POST['changeSurvey'] == "1";
$change_task_title = $_POST['changeTask'] == "1";

// Check if form exists
if (!array_key_exists($form_name, $forms)) exit('0');
$form_label = $forms[$form_name]['menu'];
if (mb_strlen($new_form_label) < 1) {
    $val_messages['efn-displayname'] = RCView::getLangStringByKey('design_1372');
}
else if (mb_strlen($new_form_label) > MAX_LABEL_LENGTH) {
    // Hard error - the user did something to circumvent the character limit
    exit('0');
}

// Check if the new form name differs and whether it is legal
if ($new_form_name != $form_name) {
    // Hard error when attempting to change the form name when not allowed
    if (!$Proj->canEditFormName($form_name)) {
        exit('0');
    }
    if (strlen($new_form_name) > MAX_NAME_LENGTH) {
        exit('0');
    }
    // Soft Errors
    if ($new_form_name == '') {
        $val_messages['efn-formname'] = RCView::getLangStringByKey('design_1375');
    }
    else if (array_key_exists($new_form_name, $forms)) {
        $val_messages['efn-formname'] = RCView::getLangStringByKey('design_1373');
    }
    else if (!preg_match('/(^[a-z]+$)|(^[a-z][a-z0-9_]*[^_]$)/', $new_form_name)) {
        $val_messages['efn-formname'] = RCView::getLangStringByKey('design_1374');
    }
    else if (array_key_exists($new_form_name.'_complete', $fields)) {
        // Name with '_complete' suffix must not exist as a field
        $val_messages['efn-formname'] = RCView::getLangStringByKey('design_1377');
    }
}

// Any errors? If so, terminate with error message response
if (count($val_messages) > 0) {
    print json_encode($val_messages);
    exit;
};

// All good! Now set the new form name and menu label and/or change the display name

if ($form_label == $new_form_label && $form_name == $new_form_name 
    && !$change_survey_title && !$change_task_title) {
    // Nothing to do. This is a success!
    exit ('1');
}

// Helper to update the form label in the metadata table
$update_display_name = function($form_name, $new_form_label) use ($project_id, $metadata_table) {
    // Get lowest field_order in form - if such a field does not exist, something is wrong
    $sql = "SELECT field_name FROM $metadata_table 
            WHERE form_name = ? AND project_id = ? 
            ORDER BY field_order LIMIT 1";
    $q = db_query($sql, [ $form_name, $project_id ]);
    $min_field_order_var = db_result($q, 0);
    if (!$min_field_order_var) return false;
    // First set all form_menu_description as null
    $sql = "UPDATE $metadata_table SET form_menu_description = NULL
            WHERE form_name = ? AND project_id = ?";
    $q = db_query($sql, [ $form_name, $project_id ]);
    // Now add the new form menu label
    $sql = "UPDATE $metadata_table SET form_menu_description = ?
            WHERE field_name = ? AND project_id = ?";
    $q = db_query($sql, [ $new_form_label, $min_field_order_var, $project_id ]);
    return $q;
};

$updated = false;
// Update the form name?
$updateFormName = ($new_form_name != $form_name);
if ($updateFormName) {
    $updated = \Design::changeFormNameInBackend($project_id, $form_name, $new_form_name);
    // Logging
    if ($updated) {
        Logging::logEvent("", $metadata_table, "MANAGE", $form_name, "form_name = '".db_escape($new_form_name)."'","Rename data collection instrument");
        // Update the form name for downstream processing
        $form_name = $new_form_name;
    }
    else {
        exit('0');
    }
}
// Update display name?
if ($new_form_label != $form_label) {
    $updated = $update_display_name($form_name, $new_form_label);
    // Logging
    if ($updated) {
        Logging::logEvent("", $metadata_table, "MANAGE", $form_name, "form_menu_description = '".db_escape($new_form_label)."'","Rename data collection instrument");
    }
}
// Update survey title
if ($change_survey_title) {
    if ($updateFormName) $Proj = new \Project($project_id, true);
    $Proj->setSurveyTitle($form_name, $new_form_label);
}
// Update task title
if ($change_task_title) {
    \Vanderbilt\REDCap\Classes\MyCap\Task::setTaskTitleByForm($project_id, $form_name, $new_form_label);
}

// Return success signal
exit ('1');
