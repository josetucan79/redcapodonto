<?php

use Vanderbilt\REDCap\Classes\MyCap\Participant;
require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Initialize vars
$popupContent = $popupTitle = "";

## RENDER DIALOG CONTENT FOR SETTING UP CONDITIONS
if (isset($_POST['action']) && $_POST['action'] == "view")
{
    // Response
    $popupTitle = "<i class=\"fas fa-eye-slash\"></i> ".$lang['mycap_mobile_app_376'];
    $popupContent = Participant::displayLogicTable();
}


## SAVE CONDITIONS SETTINGS
elseif (isset($_POST['action']) && $_POST['action'] == "save")
{
    $sql = "UPDATE redcap_mycap_projects SET participant_allow_condition = '" . db_escape($_POST['allow-participant-condition']) . "' WHERE project_id = " . PROJECT_ID;
    db_query($sql);

    // Response
    $popupTitle = $lang['design_243'];
    $popupContent = RCView::img(array('src'=>'tick.png')) . RCView::span(array('style'=>"color:green;"), $lang['mycap_mobile_app_400']);
    // Log the event
    Logging::logEvent($sql, "redcap_mycap_projects", "MANAGE", PROJECT_ID, "project_id = ".PROJECT_ID, "Edit MyCap Participant Display Logic");
}

// Send back JSON response
print json_encode_rc(array('content'=>$popupContent, 'title'=>$popupTitle));