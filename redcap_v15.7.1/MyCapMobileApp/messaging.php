<?php

use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\Message;
use Vanderbilt\REDCap\Classes\MyCap\Participant;
use Vanderbilt\REDCap\Classes\MyCap\MyCapConfiguration;

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Initialize vars
$popupContent = $popupTitle = $appendHTML ="";

## RENDER DIALOG CONTENT FOR MESSAGE HISTORY
if (isset($_POST['action']) && $_POST['action'] == "view")
{
    $code = $_POST['participantCode'];
    // Response
    $details = Participant::getParticipantDetails($code);
    $popupTitle = "<i class=\"fas fa-comment-alt\"></i> ".$lang['messaging_112']." - ". $details[$code]['identifier'];
    $popupContent = Message::displayMessageHistory($code);
}
## SEND NEW MESSAGE
elseif (isset($_POST['action']) && $_POST['action'] == "send")
{
    $time = NOW;
    $uuid = MyCap::guid();
    // Add new message to db
    $sql = "INSERT INTO redcap_mycap_messages (uuid, project_id, `type`, from_server, `from`, `to`, body, sent_date) VALUES
            ('".$uuid."', '".PROJECT_ID."', '".Message::STANDARD."', '1', '".USERID."', '".db_escape($_POST['participantId'])."', '".db_escape($_POST['body'])."', '".$time."')";

    if (db_query($sql)) {
        $code = $_POST['participantId'];
        $details = Participant::getParticipantDetails($code);
        if (strlen($details[$code]['push_notification_ids'])) {
            $pushIds = json_decode($details[$code]['push_notification_ids']);
            if (is_array($pushIds)) {
                global $myCapProj;
                $project_code = $myCapProj->project['code'];
                $sentSuccess = MyCapConfiguration::postNotification([
                    'deviceIds' => $pushIds,
                    'category' => 1,
                    'data' => [
                        'event' => '.NewMessage',
                        'messageIdentifier' => $uuid,
                        'projectCode' => $project_code,
                        'participantCode' => $code
                    ]
                ]);
                // Error
                if (!$sentSuccess) {
                    db_query("delete from redcap_mycap_messages where uuid = '$uuid'");
                    exit('0');
                }
            }
        }
        // Response
        $popupTitle = $lang['system_config_368'];
        $popupContent = RCView::img(array('src'=>'tick.png')) . RCView::span(array('style'=>"color:green;"), $lang['survey_316']);
        // Recently added message block to append to existing
        $appendHTML = '<li>
                        <div class="timeline-badge">
                            <i class="fas fa-laptop"></i>
                        </div>
                        <div class="timeline-panel"><span style="font-size: 11px;" class="text-muted; float-end">'.USERID.'</span><div class="timeline-body">
                            <p>'.$_POST['body'].'</p>
                        </div>
                        <div style="font-size: 11px; padding-top: 10px;" class="text-muted">
                            <i class="far fa-clock"></i> '.Message::getFriendlyTimeAgo(strtotime($time), 1).'
                            <span class="float-end">'.$time.'</span>
                        </div>
                    </div>
                 </li>';
    } else {
        // Error Response
        $popupTitle = $lang['global_01'];
        $popupContent = RCView::img(array('src'=>'exclamation.png')) . RCView::span(array('style'=>"color:red;"), $lang['global_64']);
    }

    // Log the event
    Logging::logEvent($sql, "redcap_mycap_projects", "MANAGE", PROJECT_ID, "project_id = ".PROJECT_ID, "Send message to MyCap participant");
}
## SAVE ACTION NEEDED SETTINGS FOR MESSAGE
elseif (isset($_POST['action']) && $_POST['action'] == "saveActionNeeded")
{
    // Update message processed field to db
    $processed = ($_POST['is_action_needed'] == "false") ? '1' : '0';
    $sql = "UPDATE redcap_mycap_messages SET processed ='".$processed."', processed_by='".USERID."'
                WHERE project_id = ".PROJECT_ID." AND uuid = '".db_escape($_POST['message_id'])."'";
    if (db_query($sql)) {
        $appendHTML = '<span id="saveStatus" style="padding-left:20px;text-align:center;color:red;font-size:11px;font-weight:bold;">'.$lang['design_243'].'</span>';
    } else {
        $appendHTML = '';
    }

    // Log the event
    Logging::logEvent($sql, "redcap_mycap_projects", "MANAGE", PROJECT_ID, "project_id = ".PROJECT_ID, "Update 'Action Needed' attribute for MyCap message");
} else if ($_GET['action'] == 'saveAnnouncement')  {
    $status = $msg = '';
    $announcement_id = '';
    if (isset($_POST['index_modal_update']) && !empty($_POST['index_modal_update'])) {
        $announcement_id = $_POST['index_modal_update'];
    }

    global $user_rights;
    if (!empty($announcement_id)) {
        $sql = "UPDATE redcap_mycap_messages 
                SET 
                    body ='".db_escape($_POST['announcement_msg'])."',
                    from_server = '1'
                WHERE project_id = ".PROJECT_ID." AND uuid = '".db_escape($announcement_id)."'";
        $logDescription = "Edit MyCap announcement";
    } else {
        // Add new announcement
        $time = str_replace(array("-",":"," "), array("","",""), NOW);
        $uuid = MyCap::guid();
        $sql = "INSERT INTO redcap_mycap_messages (uuid, project_id, `type`, from_server, `from`, `to`,  body, sent_date) VALUES
            ('".$uuid."', '".PROJECT_ID."', '".Message::ANNOUNCEMENT."', '1', '".$user_rights['username']."', '', '".db_escape($_POST['announcement_msg'])."', '".$time."')";
        $logDescription = "Create MyCap announcement";
    }
    if (db_query($sql)) {
        // Post notification to MyCap Mobile app
        global $myCapProj;
        $project_code = $myCapProj->project['code'];

        $groupID = ($user_rights['group_id'] != '' ? $user_rights['group_id'] : array());

        $codesList = array();
        $sendAnnouncement = true;
        $notAssignedToDag = true;
        $sentSuccess = false;
        // Format $codesList as array
        if (!is_array($groupID) && $groupID == '0') {
            // If passing group_id as "0", assume we want to return unassigned records.
        } elseif (!empty($groupID) && is_numeric($groupID)) {
            $notAssignedToDag = false;
            $codesList = Participant::getParticipantsInDAG(array($groupID));
            if (empty($codesList))  $sendAnnouncement = false;
        } elseif (!is_array($groupID)) {
            $codesList = array();
            $notAssignedToDag = true;
        }
        if ($notAssignedToDag) { // User is not assigned to any DAG
            $allParticipants = Participant::getParticipants(PROJECT_ID);
            $disabledParList = [];
            $codesList = [];
            foreach ($allParticipants as $code => $participant) {
                if ($participant['is_deleted'] == 0) {
                    $codesList[$code]['push_notification_ids'] = $participant['push_notification_ids'];
                } else {
                    $disabledParList[] = $code;
                }
            }
            if (empty($disabledParList)) {
                $sentSuccess = MyCapConfiguration::postNotification([
                    'topic' => $project_code,
                    'category' => 1,
                    'data' => [
                        'event' => '.NewMessage',
                        'messageIdentifier' => $uuid,
                        'projectCode' => $project_code
                    ]
                ]);
            } else {
                foreach ($codesList as $code => $details) {
                    $details = Participant::getParticipantDetails($code);
                    if (strlen($details['push_notification_ids'])) {
                        $pushIds = json_decode($details['push_notification_ids']);
                        if (is_array($pushIds)) {
                            MyCapConfiguration::postNotification([
                                'deviceIds' => $pushIds,
                                'category' => 1,
                                'data' => [
                                    'event' => '.NewMessage',
                                    'messageIdentifier' => $uuid,
                                    'projectCode' => $project_code,
                                    'participantCode' => $code
                                ]
                            ]);
                        }
                    }
                }
            }

        } else {
            if ($sendAnnouncement == true) {
                // If user is assigned to DAG, send notification to only records/participants only from that DAG
                foreach ($codesList as $code) {
                    $details = Participant::getParticipantDetails($code);
                    if (strlen($details[$code]['push_notification_ids'])) {
                        $pushIds = json_decode($details[$code]['push_notification_ids']);
                        if (is_array($pushIds) && $details[$code]['is_deleted'] == 0) { // Exclude disabled participant
                            $sentSuccess = MyCapConfiguration::postNotification([
                                'deviceIds' => $pushIds,
                                'category' => 1,
                                'data' => [
                                    'event' => '.NewMessage',
                                    'messageIdentifier' => $uuid,
                                    'projectCode' => $project_code,
                                    'participantCode' => $code
                                ]
                            ]);
                        }
                    }
                }
            }
        }
        // Error
        if (!$sentSuccess) {
            db_query("delete from redcap_mycap_messages where uuid = '$uuid'");
            exit('0');
        }
        // Logging
        Logging::logEvent($sql,"redcap_mycap_messages","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, $logDescription);
        $status = "success";
    } else {
        $msg = "";
    }
    // Return message and status
    echo json_encode(array(
        'status' => $status,
        'message' => $msg
    ));
    exit;
} elseif (isset($_GET['action']) && $_GET['action'] == "removeAnnouncement" && isset($_POST['announcememt_id']))  {
    $status = $msg = '';
    // Delete announcement from db
    $sql = "DELETE FROM redcap_mycap_messages WHERE project_id = ".PROJECT_ID." AND uuid = '".db_escape($_POST['announcememt_id'])."'";
    if (db_query($sql)) {
        // Logging
        Logging::logEvent($sql,"redcap_mycap_messages","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, "Delete MyCap announcement");
        $status = "success";
    } else {
        $msg = "";
    }
    // Return message and status
    echo json_encode(array(
        'status' => $status,
        'message' => $msg
    ));
    exit;
}
// Send back JSON response
print json_encode_rc(array('content' => $popupContent, 'title' => $popupTitle, 'resultHtml' => $appendHTML));