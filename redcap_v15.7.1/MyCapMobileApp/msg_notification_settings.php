<?php
use Vanderbilt\REDCap\Classes\MyCap\Message;

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
// Initialize vars
$return_status = $msg = '';

## SAVE CONDITIONS SETTINGS
if (isset($_GET['action']) && $_GET['action'] == "saveMsgNotification")
{
    $existingDags = array_keys(Message::getAllNotifications());

    $postDags = [];
    if (!empty($_POST['dag_ids'])) {
        foreach ($_POST['dag_ids'] as $dag_id) {
            $postDags[] = $dag_id;
            $notify_user = (isset($_POST['notify_user_'.$dag_id]) && isset($_POST['notify_user_'.$dag_id]) == 'on') ? 1 : 0;

            $post_user_emails = $_POST['user_emails_'.$dag_id];
            // remove blank lines from input
            $lines = explode("\n", trim($post_user_emails));
            foreach ($lines as $k=>$v) if(empty(trim($v))) unset($lines[$k]);
            $user_emails = implode("\n", $lines);

            $message = $_POST['custom_text_'.$dag_id];

            if (in_array($dag_id, $existingDags)) {
                if ($dag_id == 0)  {
                    $dagCond = "dag_id is NULL";
                } else {
                    $dagCond = "dag_id = '".$dag_id."'";
                }
                // Edit SQL
                $sql = "UPDATE redcap_mycap_message_notifications 
                            SET notify_user = '".$notify_user."', user_emails = '".db_escape($user_emails)."', custom_email_text = '".db_escape($message)."'
                            WHERE project_id = '".PROJECT_ID."' AND ".$dagCond;
                if (db_query($sql)) {
                    // Logging
                    Logging::logEvent($sql,"redcap_mycap_message_notifications","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, "Updated MyCap Message Notification Setting");
                    $return_status = "success";
                } else {
                    $msg = "";
                }
            } else {
                if ($dag_id == 0)  {
                    $dag_id = NULL;
                }
                // Insert SQL
                $sql = "INSERT INTO redcap_mycap_message_notifications (project_id, dag_id, notify_user, user_emails, custom_email_text) VALUES
                            ('".PROJECT_ID."', ".checkNull($dag_id).", '".$notify_user."', '".db_escape($user_emails)."', '".db_escape($message)."')";

                if (db_query($sql)) {
                    // Logging
                    Logging::logEvent($sql,"redcap_mycap_message_notifications","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, "Added MyCap Message Notification Setting");
                    $return_status = "success";
                } else {
                    $msg = "";
                }
            }
        }
    }

    // Log the event
    Logging::logEvent($sql, "redcap_mycap_message_notifications", "MANAGE", PROJECT_ID, "project_id = ".PROJECT_ID, "Modify MyCap Email Notification Settings");
}

// Send back JSON response
// Return message and status
echo json_encode(array(
    'status' => $return_status,
    'message' => $msg
));