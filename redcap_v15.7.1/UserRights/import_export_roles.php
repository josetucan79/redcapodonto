<?php
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (isset($_GET['action']) && $_GET['action'] == 'download') {
    Logging::logEvent("", "redcap_user_roles", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Download Roles (CSV)");

    global $post, $mobile_app_enabled;
    $roles = UserRights::getUserRolesDetails(PROJECT_ID, $mobile_app_enabled);
    foreach ($roles as &$role) {
        // Format form-level rights
		$forms_string = array();
        foreach($role['forms'] as $form => $right) {
            if (!isset($Proj->forms[$form])) continue;
            $forms_string[] = "$form:$right";
        }
        $role['forms'] = implode(",", $forms_string);
        $forms_string = array();
        foreach($role['forms_export'] as $form => $right) {
            if (!isset($Proj->forms[$form])) continue;
            $forms_string[] = "$form:$right";
        }
        $role['forms_export'] = implode(",", $forms_string);
    }
    unset($role);
    $content = (!empty($roles)) ? arrayToCsv($roles) : implode(",", UserRights::getApiUserRolesAttr(true, PROJECT_ID));

    $filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode(REDCap::getProjectTitle(), ENT_QUOTES)))), 0, 30)."_UserRoles_".date("Y-m-d").".csv";

    header('Pragma: anytextexeptno-cache', true);
    header("Content-type: application/csv");
    header('Content-Disposition: attachment; filename=' . $filename);
    echo addBOMtoUTF8($content);
    exit;

} else if (isset($_GET['action']) && $_GET['action'] == 'downloadMapping') {
    Logging::logEvent("", "redcap_user_roles", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Download user-role assignment (CSV)");

    $result = Project::getUserRoleRecords();
    $content = (!empty($result)) ? arrayToCsv($result) : 'username,unique_role_name';

    $filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode(REDCap::getProjectTitle(), ENT_QUOTES)))), 0, 30)."_UserRoleAssignments_".date("Y-m-d").".csv";

    header('Pragma: anytextexeptno-cache', true);
    header("Content-type: application/csv");
    header('Content-Disposition: attachment; filename=' . $filename);
    echo addBOMtoUTF8($content);
    exit;
} else if (isset($_GET['action']) && $_GET['action'] == 'uploadMapping') {
    global $lang;
    $count = 0;
    $errors = array();
    $csv_content = $preview = "";
    $commit = false;
    if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name'])) {
        $csv_content = file_get_contents($_FILES['file']['tmp_name']);
    } elseif (isset($_POST['csv_content']) && $_POST['csv_content'] != '') {
        $csv_content = $_POST['csv_content'];
        $commit = true;
    }

    if ($csv_content != "")
    {
        $data = csvToArray(removeBOM($csv_content));

        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

		Logging::logEvent("", "redcap_user_roles", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Upload user role assignments (CSV)");

        $Proj = new Project(PROJECT_ID);
        $roles = $Proj->getUniqueRoleNames();
        $dags = $Proj->getUniqueGroupNames();
        $projectUsers = UserRights::getPrivileges(PROJECT_ID);
        list ($count, $errors) = UserRights::uploadUserRoleMappings(PROJECT_ID, $data);
        // Build preview of changes being made
        if (!$commit && empty($errors))
        {
            $cells = "";
            foreach (array_keys($data[0]) as $this_hdr) {
                $cells .= RCView::td(array('style'=>'padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'), $this_hdr);
            }
            $rows = RCView::tr(array(), $cells);

            $includesDags = isset($data[0]['data_access_group']) && !empty($dags);

            foreach($data as $mapping)
            {
                $username = $mapping['username'];
                $unique_role_name = trim($mapping['unique_role_name']);
                $dag_name = $mapping['data_access_group'] ?? "";
                if ($dag_name != '') {
                    $dag_id = array_search($dag_name, $dags);
                    if (!isinteger($dag_id)) {
                        $dag_id = "";
                    }
                } else {
                    $dag_id = "";
                }

                // if $unique_role_name is non-empty, No need to check if role exists as already handled in validation
                $role_id = ($unique_role_name != '') ? array_search($unique_role_name, $roles) : '';

                // Check for changes
                $user_rights = $projectUsers[PROJECT_ID][strtolower($username)];

                $col2class = ($user_rights['role_id'] != $role_id) ? 'yellow' : 'gray';
                $old_role_name = ($user_rights['role_id'] == '') ? $lang['rights_361'] : $roles[$user_rights['role_id']];
                $old_role_name = ($col2class == 'gray') ? "" : RCView::div(array('style'=>'color:#777;font-size:11px;'), "({$old_role_name})");
                if ($unique_role_name == '' && $col2class == 'yellow') {
                    $unique_role_name = $lang['rights_361'];
                }

                $col3class = ($dag_id != "" && $dag_id != $user_rights['group_id']) ? 'yellow' : 'gray';

                // Add row
                $rows .= RCView::tr(array(),
                    RCView::td(array('class'=>'gray'),
                        $username
                    ) .
                    RCView::td(array('class'=>$col2class),
                        $unique_role_name . $old_role_name
                    ) .
                    (!$includesDags ? "" :
                        RCView::td(array('class'=>$col3class),
                            $dag_name
                        )
                    )
                );
            }
            $preview = RCView::table(array('cellspacing'=>1), $rows);
        }
        if ($commit && empty($errors)) {
            // Commit
            $csv_content = "";
            db_query("COMMIT");
            db_query("SET AUTOCOMMIT=1");
        } else {
            // ERROR: Roll back all changes made and return the error message
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
        }

        $_SESSION['imported'] = 'userroleMapping';
        $_SESSION['count'] = $count;
        $_SESSION['errors'] = $errors;
        $_SESSION['csv_content'] = $csv_content;
        $_SESSION['preview'] = $preview;
    }
    redirect(APP_PATH_WEBROOT . 'UserRights/index.php?pid=' . PROJECT_ID);
} else {
    global $lang;
    $count = 0;
    $errors = array();
    $csv_content = $preview = "";
    $commit = false;
    if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name']) && !empty($_FILES['file']['tmp_name'])) {
        $csv_content = file_get_contents($_FILES['file']['tmp_name']);
    } elseif (isset($_POST['csv_content']) && $_POST['csv_content'] != '') {
        $csv_content = $_POST['csv_content'];
        $commit = true;
    }

    if ($csv_content != "")
    {
        $data = csvToArray(removeBOM($csv_content));

        foreach ($data as $key => $this_role) {
            $data[$key]['forms_preview'] = $this_role['forms'];
            if (isset($this_role['forms']) && $this_role['forms'] != '') {
                $these_forms = array();
                foreach (explode(",", $this_role['forms']) as $this_pair) {
                    list ($this_form, $this_right) = explode(":", $this_pair, 2);
                    $these_forms[$this_form] = $this_right;
                }
                $data[$key]['forms'] = $these_forms;
            }
            $data[$key]['forms_export_preview'] = $this_role['forms_export'];
            if (isset($this_role['forms_export']) && $this_role['forms_export'] != '') {
                $these_forms = array();
                foreach (explode(",", $this_role['forms_export']) as $this_pair) {
                    list ($this_form, $this_right) = explode(":", $this_pair, 2);
                    $these_forms[$this_form] = $this_right;
                }
                $data[$key]['forms_export'] = $these_forms;
            }
        }

        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

		Logging::logEvent("", "redcap_user_roles", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Upload user roles (CSV)");

        # get user role information
        $allRoles = UserRights::getRoles();
        $userRoles = array();
        foreach ($allRoles as $roleId => $roleInfo) {
            $userRoles[$roleInfo['unique_role_name']] = $roleInfo;
        }

        $Proj = new Project(PROJECT_ID);

        list ($count, $errors) = UserRights::uploadUserRoles(PROJECT_ID, $data);
        // Build preview of changes being made
        if (!$commit && empty($errors))
        {
            $cells = "";
            foreach (array_keys($data[0]) as $this_hdr) {
                $cells .= ($this_hdr != 'forms_preview' && $this_hdr != 'forms_export_preview') ? RCView::td(array('style'=>'padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'), $this_hdr) : "";
            }
            $rows = RCView::tr(array(), $cells);

            foreach($data as $role)
            {
                $cells = "";
                // Get user info
                $unique_role_name = $role['unique_role_name'];
                $roleExists = $Proj->uniqueRoleNameExists($unique_role_name);

                if ($roleExists == 0) {
                    $new_roles[] = $role;
                    foreach ($role as $key => $this_role) {
                        if ($key == 'forms') {
                            $this_role = $role['forms_preview'];
                            $attr['style'] = "word-wrap: break-word";
                        }
                        elseif ($key == 'forms_export') {
                            $this_role = $role['forms_export_preview'];
                            $attr['style'] = "word-wrap: break-word";
                        }
                        $attr['class'] = 'green';
                        $cells .= ($key != 'forms_preview' && $key != 'forms_export_preview') ? RCView::td($attr, $this_role) : "";
                    }
                } else {
                    foreach ($role as $key => $this_role) {
                        if ($key == 'forms_preview' || $key == 'forms_export_preview') break;
                        $key = str_replace(array('role_label', 'data_export', 'stats_and_charts', 'manage_survey_participants', 'logging', 'data_quality_create', 'lock_records_all_forms', 'lock_records_customization', 'lock_records'),
                                           array('role_name', 'data_export_tool', 'graphical', 'participants', 'data_logging', 'data_quality_design', 'lock_record_multiform', 'lock_record_customize', 'lock_record'),
                                           $key);

                        $attr = [];
                        switch($key) {
                            case "forms":
                                $isFormRightsUpdated = UserRights::isFormRightsUpdated($this_role, $userRoles[$unique_role_name]['data_entry']);
                                $colClass = ($isFormRightsUpdated == true) ? 'yellow' : 'gray';
                                $this_role = $role['forms_preview'];
                                // Convert data_entry to CSV format
                                $userRoles[$unique_role_name]['data_entry'] = str_replace(",", ":", $userRoles[$unique_role_name]['data_entry']);
                                $userRoles[$unique_role_name]['data_entry'] = str_replace("][", ",", substr(trim($userRoles[$unique_role_name]['data_entry']), 1, -1));
                                $oldValue = $userRoles[$unique_role_name]['data_entry'];
                                $attr['style'] = "word-wrap: break-word";
                                break;
                            case "forms_export":
                                $isFormRightsUpdated = UserRights::isFormRightsUpdated($this_role, $userRoles[$unique_role_name]['data_export_instruments']);
                                $colClass = ($isFormRightsUpdated == true) ? 'yellow' : 'gray';
                                $this_role = $role['forms_export_preview'];
                                // Convert data_entry to CSV format
                                $userRoles[$unique_role_name]['data_export_instruments'] = str_replace(",", ":", $userRoles[$unique_role_name]['data_export_instruments']);
                                $userRoles[$unique_role_name]['data_export_instruments'] = str_replace("][", ",", substr(trim($userRoles[$unique_role_name]['data_export_instruments']), 1, -1));
                                $oldValue = $userRoles[$unique_role_name]['data_export_instruments'];
                                $attr['style'] = "word-wrap: break-word";
                                break;
                            default:
                                if ($key != 'forms_preview' && $key != 'forms_export_preview') {
                                    $colClass = ($userRoles[$unique_role_name][$key] != $this_role) ? 'yellow' : 'gray';
                                    $oldValue = ($userRoles[$unique_role_name][$key] != '') ? $userRoles[$unique_role_name][$key] : $lang['data_entry_137'];
                                }
                                break;
                        }
                        $oldValue = ($colClass == 'gray') ? "" :
                            RCView::div(array('style'=>'color:#777;font-size:11px;'), "(".$oldValue.")");

                        $attr['class'] = $colClass;

                        $cells .= RCView::td($attr, $this_role. $oldValue);
                    }
                }
                $rows .= RCView::tr(array(), $cells);
            }
            $preview = RCView::table(array('cellspacing'=>1), $rows);
        }
        if ($commit && empty($errors)) {
            // Commit
            $csv_content = "";
            db_query("COMMIT");
            db_query("SET AUTOCOMMIT=1");
        } else {
            // ERROR: Roll back all changes made and return the error message
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
        }

        $_SESSION['imported'] = 'userroles';
        $_SESSION['count'] = $count;
        $_SESSION['errors'] = $errors;
        $_SESSION['csv_content'] = $csv_content;
        $_SESSION['preview'] = $preview;
    }
    redirect(APP_PATH_WEBROOT . 'UserRights/index.php?pid=' . PROJECT_ID);
}