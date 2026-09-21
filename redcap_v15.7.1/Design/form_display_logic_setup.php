<?php

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Initialize vars
$fdl = new FormDisplayLogicSetup($_GET['pid'] ?? PROJECT_ID);
$popupContent = $popupTitle = "";
$storedData = array();


## RENDER DIALOG CONTENT FOR SETTING UP CONDITIONS
if (isset($_POST['action']) && $_POST['action'] == "view")
{
	// Response
	$popupTitle = "<i class=\"fas fa-eye-slash\"></i> ".$lang['design_985'];
	$popupContent = FormDisplayLogic::displayFormDisplayLogicTable();
	$storedData = FormDisplayLogic::getFormDisplayLogicTableValues(PROJECT_ID);
    // Pre-validate all logic in order to not cause unnecessary ajax requests
    foreach ($storedData["controls"] as &$control) {
        $control["valid"] = FormDisplayLogic::validateControlConditionLogic($control["control-condition"]);
    }
}


## SAVE CONDITIONS SETTINGS
elseif (isset($_POST['action']) && $_POST['action'] == "save")
{
	$forms_list = array();
    $sql_all = array();
    db_query("SET AUTOCOMMIT=0");
    db_query("BEGIN");
    try {
        FormDisplayLogic::saveConditionsSettings($_POST, $forms_list, $sql_all);
    } catch (\Exception $e) {
        $error = $e->getMessage();
        db_query("ROLLBACK");
        db_query("SET AUTOCOMMIT=1");
    }
    db_query("COMMIT");
    db_query("SET AUTOCOMMIT=1");
    // Response
    $popupTitle = $lang['design_243'];
    $forms_list_text = "";
	if (!empty($forms_list)) {
	    $forms_list_text = "<p>".$lang['design_963']."</p>";
        $forms_list_text .= "<ul>";
        foreach ($forms_list as $form) {
            $forms_list_text .= "<li>".$form."</li>";
        }
        $forms_list_text .= "</ul>";
    }
    $popupContent = RCView::img(array('src'=>'tick.png')) .
        RCView::span(array('style'=>"color:green;"), $lang['design_987']).
        $forms_list_text;
	// Log the event
	Logging::logEvent(implode(";\n", $sql_all), "redcap_form_render_skip_logic", "MANAGE", PROJECT_ID, "project_id = ".PROJECT_ID, "Edit settings for Form Render Skip Logic");
}

elseif (isset($_GET['action']) && $_GET['action'] == 'toggleFormDisplayLogicSetupExport') {
    $formDisplayLogicSetupExportDisabled = !$fdl->isFormDisplayLogicEnabled();
}

elseif (isset($_POST['action']) && $_POST['action'] == "clearSurveyQueue") {
    $fdl->deleteAllFormDisplayConditions();
    exit('1');
}

// Send back JSON response
//header("Content-Type: application/json");
echo json_encode_rc(array(
    'error' => $error ?? false,
    'content' => $popupContent,
    'title' => $popupTitle,
    'stored_data' => $storedData,
    'form_display_logic_setup_export_disabled' => $formDisplayLogicSetupExportDisabled ?? null
));