<?php
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if ($_GET['action'] == 'download') {
    Logging::logEvent("", "redcap_user_rights", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Download users (CSV)");

    $users = UserRights::getUserDetails(PROJECT_ID);
    foreach ($users as &$user) {
        // Remove unnecessary items
        unset($user['email'], $user['firstname'], $user['lastname']);
        // Format form-level rights
		$forms_string = array();
        foreach($user['forms'] as $form => $right) {
            if (!isset($Proj->forms[$form])) continue;
            $forms_string[] = "$form:$right";
        }
		$user['forms'] = implode(",", $forms_string);
        $forms_string = array();
        foreach($user['forms_export'] as $form => $right) {
            if (!isset($Proj->forms[$form])) continue;
            $forms_string[] = "$form:$right";
        }
        $user['forms_export'] = implode(",", $forms_string);
    }
    unset($user);

    global $mycap_enabled_global, $mycap_enabled;
    $mycap_rights = '';
    if ($mycap_enabled_global && $mycap_enabled) {
        $mycap_rights = 'mycap_participants,';
    }

    $content = (!empty($users)) ? arrayToCsv($users) : 'username,expiration,data_access_group,data_access_group_id,design,alerts,user_rights,data_access_groups,data_export,reports,stats_and_charts,manage_survey_participants,calendar,data_import_tool,data_comparison_tool,logging,file_repository,data_quality_create,data_quality_execute,api_export,api_import,api_modules,record_create,record_rename,record_delete,lock_records_all_forms,lock_records,lock_records_customization,'.$mycap_rights.'forms,forms_export';

    $filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode(REDCap::getProjectTitle(), ENT_QUOTES)))), 0, 30)."_Users_".date("Y-m-d").".csv";

    header('Pragma: anytextexeptno-cache', true);
    header("Content-type: application/csv");
    header('Content-Disposition: attachment; filename=' . $filename);
    echo addBOMtoUTF8($content);
    exit;
} else if ($_GET['action'] == 'help') {
// Add popup for help - Upload or download Users CSV
    $br = RCView::br();
    $helpText = RCView::div(array('style'=>'font-weight:bold;color: #A00000;font-size:15px;'), $lang['rights_378']) .
        RCView::div(array('style'=>'margin-top:5px;'), $lang['rights_390']) .
        RCView::div(array('class'=>'attributes-list'), implode(", ", UserRights::getApiUserPrivilegesAttr(false, PROJECT_ID))) .
        RCView::b($lang['api_docs_227']) . $br . $lang['api_docs_358'] . $br . $lang['api_docs_380'] . $br . $lang['api_docs_229'] .
        RCView::div(array('style'=>'margin-top:25px;font-weight:bold;color: #A00000;font-size:15px;'), $lang['rights_377']) .
        RCView::div(array('style'=>'margin-top:5px;'), $lang['rights_379']. " " . $lang['rights_391']) .
        RCView::div(array('style'=>'margin-top:5px;'), $lang['rights_384']) ;

    // Add Seperator
    $helpText .= RCView::div(array('style'=>'margin-top:15px; border-top: 1px solid #AAAAAA;'), '');
    // Upload or download User Roles CSV
    $helpText .= RCView::div(array('style'=>'margin-top:15px; font-weight:bold;color: #A00000;font-size:15px;'), $lang['rights_408']) .
        RCView::div(array('style'=>'margin-top:5px;'), $lang['rights_412']) .
        RCView::div(array('class'=>'attributes-list'), implode(", ", UserRights::getApiUserRolesAttr(true, PROJECT_ID))) .
        RCView::b($lang['api_docs_227']) . $br . $lang['api_docs_358'] . $br . $lang['api_docs_380'] . $br . $lang['api_docs_229'] .
        RCView::div(array('style'=>'margin-top:25px;font-weight:bold;color: #A00000;font-size:15px;'), $lang['rights_407']) .
        RCView::div(array('style'=>'margin-top:5px;'), $lang['rights_409']. " " . $lang['rights_413']) ;

    // Add Seperator
    $helpText .= RCView::div(array('style'=>'margin-top:15px; border-top: 1px solid #AAAAAA;'), '');
    // Upload or download User-Role assignment CSV
    $helpText .= RCView::div(array('style'=>'margin-top:15px; font-weight:bold;color: #A00000;font-size:15px;'), $lang['rights_416']) .
        RCView::div(array('style'=>'margin-top:5px;'), $lang['rights_420']) .
        RCView::div(array('class'=>'attributes-list'), 'username, unique_role_name') .
        RCView::div(array('style'=>'margin-top:25px;font-weight:bold;color: #A00000;font-size:15px;'), $lang['rights_415']) .
        RCView::div(array('style'=>'margin-top:5px;'), $lang['rights_418']) ;
    header('Content-Type: application/json');
    print json_encode([
        "title" => $lang['rights_376'],
        "content" => $helpText
    ]);
    exit;
} else {

    $count = 0;
    $errors = array();
    $csv_content = $preview = "";
    $commit = false;
    if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name']) && !empty($_FILES['file']['tmp_name'])) {
        $csv_content = file_get_contents($_FILES['file']['tmp_name']);
    } elseif (isset($_POST['csv_content']) && $_POST['csv_content'] != '') {
        if (!isset($_POST['notify_email']) || $_POST['notify_email'] == '') {
            $_POST['notify_email'] = 0;
        }
        $csv_content = $_POST['csv_content'];
        $commit = true;
    }

    if ($csv_content != "")
    {
        $data = csvToArray(removeBOM($csv_content));

        foreach ($data as $key=>$this_user) {
            $data[$key]['forms_preview'] = $this_user['forms'];
            $data[$key]['forms_export_preview'] = $this_user['forms_export'];
            if (isset($this_user['forms']) && $this_user['forms'] != '') {
                $these_forms = array();
                foreach (explode(",", $this_user['forms']) as $this_pair) {
                    list ($this_form, $this_right) = explode(":", $this_pair, 2);
                    $these_forms[$this_form] = $this_right;
                }
                $data[$key]['forms'] = $these_forms;
            }
            if (isset($this_user['forms_export']) && $this_user['forms_export'] != '') {
                $these_forms = array();
                foreach (explode(",", $this_user['forms_export']) as $this_pair) {
                    list ($this_form, $this_right) = explode(":", $this_pair, 2);
                    $these_forms[$this_form] = $this_right;
                }
                $data[$key]['forms_export'] = $these_forms;
            }
        }

        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        # get user information (does NOT include role-based rights for user)
        $sql = "SELECT ur.*, ui.user_email, ui.user_firstname, ui.user_lastname, ui.super_user
			FROM redcap_user_rights ur
			LEFT JOIN redcap_user_information ui ON ur.username = ui.username
			WHERE ur.project_id = ".PROJECT_ID;
        $q = db_query($sql);
        $userRights = array();
        while ($row = db_fetch_assoc($q)) {
            $userRights[$row['username']] = $row;
        }
        $Proj = new Project(PROJECT_ID);
        $groups = $Proj->getUniqueGroupNames();

        list ($count, $errors) = UserRights::uploadUsers(PROJECT_ID, $data);
        // Build preview of changes being made
        if (!$commit && empty($errors))
        {
            $cells = "";
            foreach (array_keys($data[0]??[]) as $this_hdr) {
				if ($this_hdr == "data_access_group_id") continue;
                $cells .= ($this_hdr != 'forms_preview' && $this_hdr != 'forms_export') ? RCView::td(array('style'=>'padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'), $this_hdr) : "";
            }
            $rows = RCView::tr(array(), $cells);

            foreach($data as $user)
            {
                $user['expiration'] = ($user['expiration'] != '') ? date("Y-m-d",strtotime($user['expiration'])) : "";
                $cells = "";
                // Get user info
                $username = $user['username'];

                $userExists = (is_array($userRights[$username]) && !empty($userRights[$username])) ? '1' : '0';
                $userInfo = $userRights[$username];

                if ($userExists == 0) {
                    $new_users[] = $user;
                    foreach ($user as $key => $this_user) {
                        if ($key == "data_access_group_id") continue;
                        elseif ($key == 'forms') {
                            $this_user = $user['forms_preview'];
                            $attr['style'] = "word-wrap: break-word";
                        }
                        elseif ($key == 'forms_export') {
                            $this_user = $user['forms_export_preview'];
                            $attr['style'] = "word-wrap: break-word";
                        }
                        $attr['class'] = 'green';
                        $cells .= ($key != 'forms_preview' && $key != 'forms_export_preview') ? RCView::td($attr, $this_user) : "";
                    }
                } else {
                    foreach ($user as $key => $this_user) {
                        if ($key == 'forms_preview' || $key == 'forms_export_preview') break;
                        if ($key == "data_access_group_id") continue;
                        $key = str_replace(array('email', 'firstname', 'lastname', 'stats_and_charts', 'manage_survey_participants', 'logging', 'user_email_data_logging', 'data_quality_create', 'lock_records_all_forms', 'lock_records_customization', 'lock_records'),
                                           array('user_email', 'user_firstname', 'user_lastname', 'graphical', 'participants', 'data_logging', 'email_logging', 'data_quality_design', 'lock_record_multiform', 'lock_record_customize', 'lock_record'),
                                           $key);
                        $attr = [];
                        switch($key) {
                            case "user_email":
                            case "user_firstname":
                            case "user_lastname":
                                $this_user = "";
                                break;

                            case "data_access_group":
                                // if $unique_group_name is non-empty, No need to check if group exists as already handled in validation
                                $group_id = ($this_user != '') ? array_search($this_user, $groups) : 'NULL';
                                $colclass = ($group_id != (int) $userInfo['group_id'] && !($group_id == 'NULL' && $userInfo['group_id'] == '')) ? 'yellow' : 'gray';
								$oldValue = ($userInfo['group_id'] == "") ? "" : $Proj->getUniqueGroupNames($userInfo['group_id']);
                                break;
                            case "forms":
                                $isFormRightsUpdated = UserRights::isFormRightsUpdated($this_user, $userInfo['data_entry'], $userInfo['super_user']);
                                $colclass = ($isFormRightsUpdated == true) ? 'yellow' : 'gray';
                                $this_user = $user['forms_preview'];
                                // Convert data_entry to CSV format
                                $userInfo['data_entry'] = str_replace(",", ":", $userInfo['data_entry']);
                                $userInfo['data_entry'] = str_replace("][", ",", substr(trim($userInfo['data_entry']), 1, -1));
                                $oldValue = $userInfo['data_entry'];
                                $attr['style'] = "word-wrap: break-word";
                                break;
                            case "forms_export":
                                $isFormRightsUpdated = UserRights::isFormRightsUpdated($this_user, $userInfo['data_export_instruments'], $userInfo['super_user']);
                                $colclass = ($isFormRightsUpdated == true) ? 'yellow' : 'gray';
                                $this_user = $user['forms_export_preview'];
                                // Convert data_entry to CSV format
                                $userInfo['data_export_instruments'] = str_replace(",", ":", $userInfo['data_export_instruments']);
                                $userInfo['data_export_instruments'] = str_replace("][", ",", substr(trim($userInfo['data_export_instruments']), 1, -1));
                                $oldValue = $userInfo['data_export_instruments'];
                                $attr['style'] = "word-wrap: break-word";
                                break;
                            default:
                                if ($key != 'forms_preview' && $key != 'forms_export_preview') {
                                    $colclass = ($userInfo[$key] != $this_user) ? 'yellow' : 'gray';
                                    $oldValue = ($userInfo[$key] != '') ? $userInfo[$key] : $lang['data_entry_137'];
                                }
                                break;
                        }
                        $oldValue = ($colclass == 'gray') ? "" :
                            RCView::div(array('style'=>'color:#777;font-size:11px;'), "(".$oldValue.")");

                        $attr['class'] = $colclass;

                        $cells .= RCView::td($attr, $this_user. $oldValue);
                    }
                }
                $rows .= RCView::tr(array(), $cells);
            }
            $preview = RCView::table(array('cellspacing'=>1), $rows);
        }
        if ($commit && empty($errors)) {
            //If checkbox was checked to notify new user of their access, send an email (but don't send if one has just been sent)
            if (isset($_POST['notify_email']) && $_POST['notify_email']) {
                foreach ($data as $user) {
                    // Get user info
                    $username = $user['username'];
                    $userExists = (is_array($userRights[$username]) && !empty($userRights[$username])) ? '1' : '0';
                    if ($userExists == 0) {
                        //First need to get the email address of the user we're emailing
                        $q = db_query("SELECT user_firstname, user_lastname, user_email FROM redcap_user_information WHERE username = '".db_escape($username)."'");
                        $row = db_fetch_array($q);

                        if (!is_null($row['user_email']) || $row['user_email'] != '') {
                            $user_info = User::getUserInfo(USERID);
                            $email = new Message();
                            $emailContents = "
                                <html><body style='font-family:arial,helvetica;'>
                                {$lang['global_21']}<br /><br />
                                {$lang['rights_88']} \"<a href=\"".APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/index.php?pid=".PROJECT_ID."\">".strip_tags(str_replace("<br>", " ", label_decode($app_title)))."</a>\"{$lang['period']}
                                {$lang['rights_89']} \"$username\", {$lang['rights_90']}<br /><br />
                                ".APP_PATH_WEBROOT_FULL."
                                </body>
                                </html>";
                            $email->setTo($row['user_email']);
                            $email->setFrom($user_info['user_email']);
                            $email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
                            $email->setSubject($lang['rights_122']);
                            $email->setBody($emailContents);
                            $email->send();
                        }
                    }
                }
            }
            // Commit
            Logging::logEvent("", "redcap_user_rights", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Upload users (CSV)");
            $csv_content = "";
            db_query("COMMIT");
            db_query("SET AUTOCOMMIT=1");
        } else {
            // ERROR: Roll back all changes made and return the error message
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
        }

        $_SESSION['imported'] = 'users';
        $_SESSION['count'] = $count;
        $_SESSION['errors'] = $errors;
        $_SESSION['csv_content'] = $csv_content;
        $_SESSION['preview'] = $preview;
    }
    redirect(APP_PATH_WEBROOT . 'UserRights/index.php?pid=' . PROJECT_ID);
}