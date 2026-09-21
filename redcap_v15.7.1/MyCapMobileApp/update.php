<?php
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\Page;
use Vanderbilt\REDCap\Classes\MyCap\Link;
use Vanderbilt\REDCap\Classes\MyCap\Contact;
use Vanderbilt\REDCap\Classes\MyCap\Message;
use Vanderbilt\REDCap\Classes\MyCap\MyCapConfiguration;
use Vanderbilt\REDCap\Classes\MyCap\Participant;
use Vanderbilt\REDCap\Classes\MyCap\Task;

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
global $lang, $myCapProj;
$return_status = $msg = '';

if (isset($_GET['action']) && $_GET['action'] == 'savePage')
{
    $fileUploaded = false;
    $page_id = '';
    if (isset($_POST['index_modal_update']) && !empty($_POST['index_modal_update'])) {
        $page_id = $_POST['index_modal_update'];
    }
    $old_logo = (isset($_POST['old_image'])) ? $_POST['old_image'] : '';
    // Upload custom image
    if ($_POST['image_type'] == Page::IMAGETYPE_CUSTOM && !empty($_FILES['logo']['name'])) {
        // Check if it is an image file
        $file_ext = getFileExt($_FILES['logo']['name']);
        if (in_array(strtolower($file_ext), array("jpeg", "jpg", "gif", "bmp", "png"))) {
            // Upload the image
            $logo = Files::uploadFile($_FILES['logo']);
            $fileUploaded = true;
        }
    } elseif ($_POST['image_type'] == Page::IMAGETYPE_SYSTEM && !empty($old_logo)) {
        // Mark existing field for deletion in edocs table
        $logo = db_result(db_query("SELECT custom_logo FROM redcap_mycap_aboutpages WHERE page_id = '".db_escape($page_id)."'"), 0);
        if (!empty($logo)) {
            db_query("UPDATE redcap_edocs_metadata SET delete_date = '".NOW."' WHERE doc_id = $logo");
        }
        // Set back to default values
        $logo = "";
    }

    if (!empty($page_id)) {
        // Update page
        if ($_POST['image_type'] == Page::IMAGETYPE_SYSTEM) {
            $custom_logo_update = ", custom_logo=''";
            $logo_update = ", system_image_name = ".checkNull($_POST['system_image']);
        } else {
            $custom_logo_update = (!empty($old_logo)) ? "" : ", custom_logo  = ".checkNull($logo);
            $logo_update = ", system_image_name = ''";
        }
        $dag_id = $_POST['dag_id'];
        $dag_update = ", dag_id = ".checkNull($dag_id);

        $sql = "UPDATE redcap_mycap_aboutpages SET page_title ='".db_escape($_POST['page_title'])."', page_content ='".db_escape($_POST['page_content'])."', 
                        image_type ='".db_escape($_POST['image_type'])."'".$logo_update.$custom_logo_update.$dag_update." 
                WHERE project_id = ".PROJECT_ID." AND page_id = '".db_escape($page_id)."'";
        Page::createAboutImagesZip(PROJECT_ID);
        $logDescription = "Create MyCap About page";
    } else {
        // Add new page
        // Get the next order number
        $sql = "SELECT MAX(page_order) FROM redcap_mycap_aboutpages WHERE project_id = " . PROJECT_ID;
        $q = db_query($sql);
        $max_order = db_result($q, 0);
        $page['page_order'] = (is_numeric($max_order) ? ($max_order + 1) : 1);
        $dag_id = $_POST['dag_id'];

        // Add new custom about page
        if ($_POST['image_type'] == Page::IMAGETYPE_CUSTOM) {
            $page['system_image_name'] = '';
            $page['custom_logo'] = (!empty($old_logo)) ? '' : $logo;
        } else {
            $page['custom_logo'] = '';
            $page['system_image_name'] = $_POST['system_image'];
        }

        $sql = "INSERT INTO redcap_mycap_aboutpages (project_id, identifier, page_title, page_content, sub_type, image_type, system_image_name, custom_logo, page_order, dag_id) 
				 VALUES ('".PROJECT_ID."', '".db_escape(MyCap::guid())."', '".db_escape($_POST['page_title'])."', '".db_escape($_POST['page_content'])."', '".Page::SUBTYPE_CUSTOM."', '".db_escape($_POST['image_type'])."', '".db_escape($page['system_image_name'])."', '".db_escape($page['custom_logo'])."', '".db_escape($page['page_order'])."', ".checkNull($dag_id).");";
        $logDescription = "Edit MyCap About page";
    }
    if (db_query($sql)) {
        if ($fileUploaded) {
            Page::createAboutImagesZip(PROJECT_ID);
        }
        // Logging
        Logging::logEvent($sql,"redcap_mycap_aboutpages","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, $logDescription);
        $return_status = "success";
    } else {
        $msg = "";
    }
} else if ($_GET['action'] == 'deletePage') {
    $sql = "DELETE FROM redcap_mycap_aboutpages WHERE project_id = ".PROJECT_ID." AND page_id = '".db_escape($_POST['page'])."'";
    if (db_query($sql)) {
        Page::createAboutImagesZip(PROJECT_ID);

        // Logging
        Logging::logEvent($sql,"redcap_mycap_aboutpages","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, "Delete MyCap About page");
        $return_status = "success";
    } else {
        $msg = "";
    }
} else if ($_GET['action'] == 'movePage') {
    global $lang;
    $move_page_id =  (int)$_REQUEST['page_id'];

    $pageObj = new Page();
    $pageData = $pageObj->getAboutPagesSettings(PROJECT_ID);

    if ($_POST['param'] == 'view') {
        // Build pages drop-down list
        $all_pages_dd = "<select id='move_after_page' style='font-weight:normal;width:100%;'>
                                <option value=''>-- {$lang['mycap_mobile_app_27']} --</option>";

        // Loop through all pages
        $page_number = 0;
        $title_confirm = '';
        foreach ($pageData as $page_id => $attr) {
            $page_number++;
            $pageTitle = (trim($attr['page_title']) == '') ? '' : $lang['colon'].' <span class="font-weight-normal">'.RCView::escape($attr['page_title']).'</span>';
            $pageTitleFull = $lang['mycap_mobile_app_02']." #" .$page_number.$pageTitle;
            if ($page_id == $move_page_id) {
                $title_confirm =  RCView::span(array('style'=>'color:#A00000;font-weight:bold;font-family:verdana;font-size:14px;'), '"' . $lang['mycap_mobile_app_02']." #" .$page_number.$pageTitle . '"').RCView::SP . RCView::SP .
                    RCView::br();
                $all_pages_dd .= "<optgroup label='".$lang['mycap_mobile_app_28']."'></optgroup>";
            } else {
                $all_pages_dd .= "<option value='$page_id'>$pageTitleFull</option>";
            }
        }
        // Add closing select list
        $all_pages_dd .= "</select>";

        // Popup content
        $html = RCView::div('',
            RCView::p('', $lang['mycap_mobile_app_29']) .
            RCView::div(array('style'=>'font-size:13px;width:95%;margin-top:15px;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;'),
                RCView::b($lang['mycap_mobile_app_30']) . RCView::SP . RCView::SP .$title_confirm ).
            RCView::div(array('style'=>'line-height:1.6em;margin:20px 0;font-weight:bold;background-color:#f5f5f5;border:1px solid #ccc;padding:10px;width:95%;'),
                $lang['mycap_mobile_app_32'] . RCView::br() . $all_pages_dd
            )
        );

        // Output JSON
        print json_encode_rc(array('payload' => $html, 'title' => $lang['mycap_mobile_app_31']));
        exit;
    }
    ## MOVE AND SAVE IN NEW POSITION
    elseif ($_POST['param'] == 'save' && isset($_POST['move_after_page']) && isset($pageData[$_POST['move_after_page']])) {
        $page_id = $_POST['page_id'];
        $after_page_id = $_POST['move_after_page'];
        $pid = $_GET['pid'];

        $pos = $pageData[$page_id]['page_order'];
        $new_pos = $pageData[$after_page_id]['page_order'];

        $page_number = 0;
        $pageTitle = '';
        foreach($pageData as $id => $pages) {
            $page_number++;
            if ($id == $page_id) {
                $title = (trim($pages['page_title']) == '') ? '' : $lang['colon']." ".RCView::escape($pages['page_title']);
                $pageTitle = $lang['mycap_mobile_app_02']." #" .$page_number.$title;
            }
        }

        if($pos != $new_pos) {
            if($new_pos > $pos) {
                $sql = "UPDATE redcap_mycap_aboutpages 
                            SET page_order = page_order -1 
                            WHERE project_id = '".$pid."' AND page_order <= '".$new_pos."' AND page_order > '".$pos."'";
                db_query($sql);

                $sql2 = "UPDATE redcap_mycap_aboutpages 
                             SET page_order='".$new_pos."' 
                             WHERE project_id = '".$pid."' AND page_id = '".db_escape($page_id)."'";
                db_query($sql2);
            } else {
                $sql = "UPDATE redcap_mycap_aboutpages 
                            SET page_order = page_order + 1 
                            WHERE project_id = '".$pid."' AND page_order > '".$new_pos."' AND page_order < '".$pos."'";
                db_query($sql);

                $sql2 = "UPDATE redcap_mycap_aboutpages 
                             SET page_order='".($new_pos + 1)."' 
                             WHERE project_id = '".$pid."' AND page_id = '".db_escape($page_id)."'";
                db_query($sql2);
            }
        }
        Logging::logEvent("", "redcap_mycap_aboutpages", "MANAGE", $page_id, strip_tags($pageTitle), "Reorder MyCap About page");
        // Set HTML success message
        $page_msg = RCView::div(array('class'=>'fs14'),
                RCView::b($pageTitle . $lang['colon']." ") . $lang['mycap_mobile_app_35']
            ) .
            RCView::div(array('class'=>'fs14 text-danger mt-3'), $lang['mycap_mobile_app_36']);
        $_SESSION['move_page_msg'] = $page_msg;
        $_SESSION['focus_page_id'] = $page_id;
    }
} else if ($_GET['action'] == 'saveLink')  {
    $link_id = '';
    if (isset($_POST['index_modal_update']) && !empty($_POST['index_modal_update'])) {
        $link_id = $_POST['index_modal_update'];
    }

    $append_project_code = (isset($_POST['append_project_code'])) ? 1 : 0;
    $append_participant_code = (isset($_POST['append_participant_code'])) ? 1 : 0;
    if (!empty($link_id)) {
        $sql = "UPDATE redcap_mycap_links 
                SET 
                    dag_id =".checkNull($_POST['dag_id']).",
                    link_name ='".db_escape($_POST['link_name'])."', 
                    link_url ='".db_escape($_POST['link_url'])."' , 
                    link_icon ='".db_escape($_POST['selected_icon'])."',
                    append_project_code ='$append_project_code',
                    append_participant_code ='$append_participant_code'
                WHERE project_id = ".PROJECT_ID." AND link_id = '".db_escape($link_id)."'";
        $logDescription = "Edit MyCap Link";
    } else {
        // Add new link
        // Get the next order number
        $sql = "SELECT MAX(link_order) FROM redcap_mycap_links WHERE project_id = " . PROJECT_ID;
        $q = db_query($sql);
        $max_order = db_result($q, 0);
        $dag_id = ($_POST['dag_id'] == '') ? NULL : $_POST['dag_id'];
        $link_order = (is_numeric($max_order) ? ($max_order + 1) : 1);
        $identifier = MyCap::guid();

        $sql = "INSERT INTO redcap_mycap_links (`dag_id`, `link_order`, `project_id`, `identifier`, `link_name`, `link_url`, `link_icon`, `append_project_code`, `append_participant_code`) 
				 VALUES (".checkNull($dag_id).", '".$link_order."', '".PROJECT_ID."', '".$identifier."', '".db_escape($_POST['link_name'])."', '".db_escape($_POST['link_url'])."', '".db_escape($_POST['selected_icon'])."', '".$append_project_code."', '".$append_participant_code."');";
        $logDescription = "Create MyCap Link";
    }
    if (db_query($sql)) {
        // Logging
        Logging::logEvent($sql,"redcap_mycap_links","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, $logDescription);
        $return_status = "success";
    } else {
        $msg = "";
    }
} else if ($_GET['action'] == 'deleteLink') {
    $sql = "DELETE FROM redcap_mycap_links WHERE project_id = ".PROJECT_ID." AND link_id = '".db_escape($_POST['link'])."'";
    if (db_query($sql)) {
        // Logging
        Logging::logEvent($sql,"redcap_mycap_links","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, "Delete MyCap Link");
        $return_status = "success";
    } else {
        $msg = "";
    }
} else if ($_GET['action'] == 'reorderLink') {
    // Validate ids
    if (!isset($_POST['link_ids'])) exit('0');

    // Remove comma on end
    if (substr($_POST['link_ids'], -1) == ',') $_POST['link_ids'] = substr($_POST['link_ids'], 0, -1);

    // Create array of link_ids
    $new_link_ids = explode(",", $_POST['link_ids']);

    // Get existing list of links to validate and compare number of items
    $old_link_ids = array();
    $allLinks = Link::getLinks(PROJECT_ID);
    foreach($allLinks as $linkId => $attr) {
        $old_link_ids[] = $linkId;
    }

    // Determine if any new link_ids were maliciously added
    $extra_link_ids = array_diff($new_link_ids, $old_link_ids);
    if (!empty($extra_link_ids)) exit('0');

    // Determine if any new link were added by another user simultaneously and are not in this list
    $append_link_ids = array_diff($old_link_ids, $new_link_ids);

    // Set up all actions as a transaction to ensure everything is done here
    db_query("SET AUTOCOMMIT=0");
    db_query("BEGIN");
    $errors = 0;
    // Set all link_orders to null
    $sql = "UPDATE redcap_mycap_links SET link_order = NULL WHERE project_id = ".PROJECT_ID;
    if (!db_query($sql)) $errors++;
    // Loop through link_ids and set new link_order
    $link_order = 1;
    foreach ($new_link_ids as $this_link_id) {
        $sql = "UPDATE redcap_mycap_links SET link_order = ".$link_order++."
			    WHERE project_id = ".PROJECT_ID." AND link_id = $this_link_id";
        if (!db_query($sql)) $errors++;
    }
    // Deal with orphaned link_ids added simultaneously by other user while this user reorders
    foreach ($append_link_ids as $this_link_id) {
        $sql = "UPDATE redcap_mycap_links SET link_order = ".$link_order++."
                WHERE project_id = ".PROJECT_ID." AND link_id = $this_link_id";
        if (!db_query($sql)) $errors++;
    }
    // If errors, do not commit
    $commit = ($errors > 0) ? "ROLLBACK" : "COMMIT";
    if (db_query($commit)) {
        $return_status = "success";
    } else {
        $msg = "";
    }
    if ($errors > 0) exit('0');
    // Set back to initial value
    db_query("SET AUTOCOMMIT=1");

    // Logging
    Logging::logEvent("", "redcap_mycap_links", "MANAGE", PROJECT_ID, "link_id = ".$_POST['link_ids'], "Reorder MyCap Links");
} else if ($_GET['action'] == 'saveContact')  {
    $contact_id = '';
    if (isset($_POST['index_modal_update']) && !empty($_POST['index_modal_update'])) {
        $contact_id = $_POST['index_modal_update'];
    }
    $dag_id = '';
    if (isset($_POST['dag_id']) && !empty($_POST['dag_id'])) {
        $dag_id = $_POST['dag_id'];
    }

    if (!empty($contact_id)) {
        $sql = "UPDATE redcap_mycap_contacts 
                SET 
                    dag_id =".checkNull($dag_id).",
                    contact_header ='".db_escape($_POST['header'])."', 
                    contact_title ='".db_escape($_POST['title'])."' , 
                    phone_number ='".db_escape($_POST['phone'])."',
                    email ='".db_escape($_POST['email'])."',
                    website ='".db_escape($_POST['weburl'])."',
                    additional_info ='".db_escape($_POST['info'])."'
                WHERE project_id = ".PROJECT_ID." AND contact_id = '".db_escape($contact_id)."'";
        $logDescription = "Edit MyCap Contact";
    } else {
        // Add new contact
        // Get the next order number
        $sql = "SELECT MAX(contact_order) FROM redcap_mycap_contacts WHERE project_id = " . PROJECT_ID;
        $q = db_query($sql);
        $max_order = db_result($q, 0);
        $contact_order = (is_numeric($max_order) ? ($max_order + 1) : 1);
        $identifier = MyCap::guid();
        $dag_id = (isset($_POST['dag_id']) && !empty($_POST['dag_id'])) ? $_POST['dag_id'] : NULL;

        $sql = "INSERT INTO redcap_mycap_contacts (`contact_order`, `project_id`, `identifier`, `dag_id`, `contact_header`, `contact_title`, `phone_number`, `email`, `website`, `additional_info`) VALUES 
                    ('".$contact_order."', '".PROJECT_ID."', '".$identifier."', ".checkNull($dag_id).", '".db_escape($_POST['header'])."', '".db_escape($_POST['title'])."', '".db_escape($_POST['phone'])."', '".db_escape($_POST['email'])."', '".db_escape($_POST['weburl'])."', '".db_escape($_POST['info'])."')";

        $logDescription = "Create MyCap Contact";
    }
    if (db_query($sql)) {
        // Logging
        Logging::logEvent($sql,"redcap_mycap_contacts","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, $logDescription);
        $return_status = "success";
    } else {
        $msg = "";
    }
} else if ($_GET['action'] == 'deleteContact') {
    $sql = "DELETE FROM redcap_mycap_contacts WHERE project_id = ".PROJECT_ID." AND contact_id = '".db_escape($_POST['contact'])."'";
    if (db_query($sql)) {
        // Logging
        Logging::logEvent($sql,"redcap_mycap_contacts","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, "Delete MyCap Contact");
        $return_status = "success";
    } else {
        $msg = "";
    }
} else if ($_GET['action'] == 'reorderContact') {
    // Validate ids
    if (!isset($_POST['contact_ids'])) exit('0');

    // Remove comma on end
    if (substr($_POST['contact_ids'], -1) == ',') $_POST['contact_ids'] = substr($_POST['contact_ids'], 0, -1);

    // Create array of contact_ids
    $new_contact_ids = explode(",", $_POST['contact_ids']);

    // Get existing list of contacts to validate and compare number of items
    $old_contact_ids = array();
    $allContacts = Contact::getContacts(PROJECT_ID);
    foreach($allContacts as $contactId => $attr) {
        $old_contact_ids[] = $contactId;
    }

    // Determine if any new contact_ids were maliciously added
    $extra_contact_ids = array_diff($new_contact_ids, $old_contact_ids);
    if (!empty($extra_contact_ids)) exit('0');

    // Determine if any new contacts were added by another user simultaneously and are not in this list
    $append_contact_ids = array_diff($old_contact_ids, $new_contact_ids);

    // Set up all actions as a transaction to ensure everything is done here
    db_query("SET AUTOCOMMIT=0");
    db_query("BEGIN");
    $errors = 0;
    // Set all contact_orders to null
    $sql = "UPDATE redcap_mycap_contacts SET contact_order = NULL WHERE project_id = ".PROJECT_ID;
    if (!db_query($sql)) $errors++;
    // Loop through contact_ids and set new contact_order
    $contact_order = 1;
    foreach ($new_contact_ids as $this_contact_id) {
        $sql = "UPDATE redcap_mycap_contacts SET contact_order = ".$contact_order++."
			    WHERE project_id = ".PROJECT_ID." AND contact_id = $this_contact_id";
        if (!db_query($sql)) $errors++;
    }
    // Deal with orphaned contact_ids added simultaneously by other user while this user reorders
    foreach ($append_contact_ids as $this_contact_id) {
        $sql = "UPDATE redcap_mycap_contacts SET contact_order = ".$contact_order++."
                WHERE project_id = ".PROJECT_ID." AND contact_id = $this_contact_id";
        if (!db_query($sql)) $errors++;
    }
    // If errors, do not commit
    $commit = ($errors > 0) ? "ROLLBACK" : "COMMIT";
    if (db_query($commit)) {
        $return_status = "success";
    } else {
        $msg = "";
    }
    if ($errors > 0) exit('0');
    // Set back to initial value
    db_query("SET AUTOCOMMIT=1");

    // Logging
    Logging::logEvent("", "redcap_mycap_contacts", "MANAGE", PROJECT_ID, "contact_id = ".$_POST['contact_ids'], "Reorder MyCap Contacts");
} else if ($_GET['action'] == 'saveTheme')  {
    $sql = "UPDATE redcap_mycap_themes 
            SET 
                primary_color ='".db_escape($_POST['primaryColor'])."', 
                light_primary_color ='".db_escape($_POST['lightPrimaryColor'])."' , 
                accent_color ='".db_escape($_POST['accentColor'])."',
                dark_primary_color ='".db_escape($_POST['darkPrimaryColor'])."',
                light_bg_color ='".db_escape($_POST['lightBackgroundColor'])."',
                theme_type ='".db_escape($_POST['themeType'])."',
                system_type ='".db_escape($_POST['systemType'])."'
            WHERE project_id = ".$_GET['pid'];
    if (db_query($sql)) {
        // Logging
        Logging::logEvent($sql,"redcap_mycap_themes","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, "Edit MyCap Theme");
        $return_status = "success";
    } else {
        $msg = "";
    }
} else if ($_GET['action'] == 'publishVersion')  {
    if ($myCapProj->publishConfigVersion(PROJECT_ID)) {
        $taskNonFixableErrors = Task::getMyCapTaskNonFixableErrors('');

        if (!empty($taskNonFixableErrors)) {
            $return_status = "warning";
        } else {
            $return_status = "success";
        }
    } else {
        $msg = "";
    }
} else if ($_GET['action'] == 'updateParStatus') {
    $is_deleted = ($_GET['flag'] == 'enable') ? 0 : 1;
    $sql = "UPDATE redcap_mycap_participants SET is_deleted = '".$is_deleted."' WHERE record = '".db_escape($_POST['record'])."' AND code = '".db_escape($_POST['participant_id'])."'";
    if (db_query($sql))
    {
        // Set response
        $return_status = ($is_deleted == 1 ? 'disabled' : 'enabled');
        if ($_POST['notify_participant'] == 1) {
            $code = $_POST['participant_id'];
            $message = $_POST['message'];
            $time = NOW;
            $uuid = MyCap::guid();
            // Add new message to db
            $sql = "INSERT INTO redcap_mycap_messages (uuid, project_id, `type`, from_server, `from`, `to`, body, sent_date) VALUES
            ('".$uuid."', '".PROJECT_ID."', '".Message::STANDARD."', '1', '".USERID."', '".db_escape($code)."', '".db_escape($message)."', '".$time."')";

            if (db_query($sql)) {
                $details = Participant::getParticipantDetails($code);
                if (strlen($details[$code]['push_notification_ids'])) {
                    $pushIds = json_decode($details[$code]['push_notification_ids']);
                    if (is_array($pushIds)) {
                        global $myCapProj;
                        $project_code = $myCapProj->project['code'];
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
                // Overwrite response
                $return_status = ($is_deleted == 1 ? 'disablednotified' : 'enablednotified');
            }
        }
         // Logging
        Logging::logEvent($sql,"redcap_mycap_participants","MANAGE",$_POST['participant_id'],"participant_id = {$_POST['participant_id']}", ($is_deleted == 1) ? "Disable MyCap participant" : "Enable MyCap participant");
    } else {
        $msg = "";
    }
} elseif (isset($_GET['action']) && $_GET['action'] == "renderProjectTitleSetup")  {
    // render project title setup popup
    print MyCap::renderEditProjectTitleSetup();
    exit;
} else if ($_GET['action'] == 'saveProjectTitle')  {
    $sql = "UPDATE redcap_mycap_projects
            SET 
                name ='".db_escape($_POST['project_title'])."'
            WHERE project_id = ".$_GET['pid'];
    if (db_query($sql)) {
        // Logging
        Logging::logEvent($sql,"redcap_mycap_projects","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, "Edit Project title in MyCap Mobile App");
        $return_status = "success";
    } else {
        $msg = "";
    }
} else if ($_GET['action'] == 'saveNotification')  {
    $notification_time = $_POST['notification_time'].":00"; // Store in hh:mm:ss format
    $sql = "UPDATE redcap_mycap_projects SET notification_time ='".db_escape($notification_time)."' WHERE project_id = ".$_GET['pid'];
    if (db_query($sql)) {
        // Logging
        Logging::logEvent($sql,"redcap_mycap_projects","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID, "Edit MyCap Notification Time");
        $return_status = "success";
    } else {
        $msg = "";
    }
}

// Return message and status
echo json_encode(array(
    'status' => $return_status,
    'message' => $msg
));