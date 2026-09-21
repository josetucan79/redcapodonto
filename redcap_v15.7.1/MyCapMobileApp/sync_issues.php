<?php

use Vanderbilt\REDCap\Classes\MyCap\SyncIssues;
require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Initialize vars
$popupContent = $popupTitle = "";

## RENDER DIALOG CONTENT FOR SETTING UP SYNC ISSUE DETAILS
if (isset($_POST['action']) && $_POST['action'] == "view")
{
    // Response
    $popupTitle = "<i class=\"fas fa-sync\"></i> ".$lang['mycap_mobile_app_501'];
    $popupContent = SyncIssues::displaySyncIssueDetails($_POST['projectCode'], $_POST['participantCode'], $_POST['issueId']);
}


## SAVE SYNC ISSUE RESOLUTION AND COMMENT
elseif (isset($_POST['action']) && $_POST['action'] == "save")
{
    $_POST['is_resolved'] = (isset($_POST['is_resolved'])) ? '1' : '0';
    $sql = "UPDATE redcap_mycap_syncissues 
            SET resolved = '" . db_escape($_POST['is_resolved']) . "',
                resolved_comment = '" . db_escape($_POST['resolution_comment']) . "' 
            WHERE project_code = '".db_escape($_POST['projectCode'])."' 
                AND participant_code = '".db_escape($_POST['participantCode'])."' 
                AND uuid = '".db_escape($_POST['issueId'])."'";
    db_query($sql);

    // Response
    $popupTitle = $lang['design_243'];
    $popupContent = RCView::img(array('src'=>'tick.png')) . RCView::span(array('style'=>"color:green;"), $lang['mycap_mobile_app_507']);
    // Log the event
    Logging::logEvent($sql, "redcap_mycap_syncissues", "MANAGE", PROJECT_ID, "project_id = ".PROJECT_ID, "Edit resolution for MyCap app sync issue");
}

// Send back JSON response
print json_encode_rc(array('content'=>$popupContent, 'title'=>$popupTitle));