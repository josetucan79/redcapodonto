<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Get text for "Remove user?" dialog
if (isset($_GET["get-remove-user-text"])) {
	$user = trim(strip_tags(html_entity_decode($_GET["get-remove-user-text"], ENT_QUOTES)));
	$body = $user == USERID ? RCView::tt("rights_194") : (
		RCView::tt_i("rights_446", [$user]) . "<br><br>" .
		RCView::tt("rights_193")
	);
	if (MobileApp::userHasInitializedProjectInApp($user, PROJECT_ID)) {
		$body .= RCView::div([
				'style'=>'margin-top:10px;font-weight:bold;color:#C00000;'
			], 
			RCView::tt("rights_313")
		);
	}
	if ($user_messaging_enabled && $user != USERID) {
		// See if they're on any project conversations
		$convList = Messenger::getUserConvTitlesForProject($user, PROJECT_ID);
		if (!empty($convList)) {
			$body .= "<hr>" .
				RCView::tt("messaging_02") . 
				"<br><br>" . 
				"<b>" . RCView::tt("messaging_03") . "</b>" .
				"<br> - \"" . implode("\"<br> - \"", $convList) . "\"";
		}
	}
	print js_escape($body);
	exit;
}

// Get list of all roles in project
$roles = UserRights::getRoles();


## ADD/EDIT/DELETE USER
if (isset($_POST['submit-action']))
{
	// Initialize $context_msg
	$context_msg = '';

	// Set and trim username/role name
	$user = trim(strip_tags(html_entity_decode($_POST['user'], ENT_QUOTES)));

	// Set submit-action flag and remove from Post to prevent issues
	$submit_action = $_POST['submit-action'];
	unset($_POST['submit-action']);

	/// Set context_msg
	if ($user != '' && $_POST['role_name'] == '') {
		// User
		$context_msg_update = "<div class='userSaveMsg darkgreen' style='max-width:600px;text-align:center;'><i class=\"fas fa-check\"></i> ".$lang['global_17']." \"<b>".RCView::escape($user)."</b>\" {$lang['rights_05']}</div>";
		$context_msg_insert = "<div class='userSaveMsg darkgreen' style='max-width:600px;text-align:center;'><i class=\"fas fa-check\"></i> ".$lang['global_17']." \"<b>".RCView::escape($user)."</b>\" {$lang['rights_06']}</div>";
		$context_msg_delete = "<div class='userSaveMsg red' style='max-width:600px;text-align:center;'><i class=\"fas fa-times\"></i> ".$lang['global_17']." \"<b>".RCView::escape($user)."</b>\" {$lang['rights_07']}</div>";
	} else {
		// Role
		if ($user == '0') {
			// New role
			$role_name = strip_tags(html_entity_decode($_POST['role_name'], ENT_QUOTES));
		} elseif (isset($_POST['role_name_edit'])) {
			// Edit role name or Copy role
			$role_name = html_entity_decode($_POST['role_name_edit'], ENT_QUOTES);
			// Logging (if renaming role)
			if ($submit_action == "edit_role" && $_POST['role_name_edit'] != $roles[$user]['role_name']) {
				Logging::logEvent('',"redcap_user_rights","update",$user,"role = '$role_name',\nold role = '{$roles[$user]['role_name']}'","Rename role");
			}
		} else {
			$role_name = $roles[$user]['role_name'];
		}
		$context_msg_update = "<div class='userSaveMsg darkgreen' style='max-width:600px;text-align:center;'><i class=\"fas fa-check\"></i> ".$lang['global_115']." \"<b>".RCView::escape($role_name)."</b>\" {$lang['rights_05']}</div>";
		$context_msg_insert = "<div class='userSaveMsg darkgreen' style='max-width:600px;text-align:center;'><i class=\"fas fa-check\"></i> ".$lang['global_115']." \"<b>".RCView::escape($role_name)."</b>\" {$lang['rights_06']}</div>";
		$context_msg_delete = "<div class='userSaveMsg red' style='max-width:600px;text-align:center;'><i class=\"fas fa-times\"></i> ".$lang['global_115']." \"<b>".RCView::escape($role_name)."</b>\" {$lang['rights_07']}</div>";
	}

	//Switch all checkboxes from 'on' to '1'
	foreach ($_POST as $key => $value) {
		if ($value == 'on') $_POST[$key] = 1;
	}
	// Set and format expiration date
	if (isset($_POST['expiration'])) {
		$_POST['expiration'] = preg_replace("/[^0-9\/\.-]/", "", $_POST['expiration']); // sanitize
		$_POST['expiration'] = DateTimeRC::format_ts_to_ymd($_POST['expiration']);
	}
	//Fix values for unchecked check boxes
	$_POST['data_export_tool'] = null; // Set as NULL since this value from the db is no longer used
	if (!isset($_POST['data_import_tool']) || $_POST['data_import_tool'] == '') 		$_POST['data_import_tool'] = 0;
	if (!isset($_POST['data_comparison_tool']) || $_POST['data_comparison_tool'] == '') 	$_POST['data_comparison_tool'] = 0;
	if (!isset($_POST['data_logging']) || $_POST['data_logging'] == '') 			$_POST['data_logging'] = 0;
	if (!isset($_POST['email_logging']) || $_POST['email_logging'] == '') 			$_POST['email_logging'] = 0;
	if (!isset($_POST['file_repository']) || $_POST['file_repository'] == '') 		$_POST['file_repository'] = 0;
	if (!isset($_POST['double_data']) || $_POST['double_data'] == '') 			$_POST['double_data'] = 0;
	if (!isset($_POST['user_rights']) || $_POST['user_rights'] == '') 			$_POST['user_rights'] = 0;
	if (!isset($_POST['data_access_groups']) || $_POST['data_access_groups'] == '') 	$_POST['data_access_groups'] = 0;
	if (!isset($_POST['lock_record']) || $_POST['lock_record'] == '') 			$_POST['lock_record'] = 0;
	if (!isset($_POST['lock_record_multiform']) || $_POST['lock_record_multiform'] == '') 	$_POST['lock_record_multiform'] = 0;
	if (!isset($_POST['lock_record_customize']) || $_POST['lock_record_customize'] == '') 	$_POST['lock_record_customize'] = 0;
	if (!isset($_POST['design']) || $_POST['design'] == '') 				$_POST['design'] = 0;
	if (!isset($_POST['alerts']) || $_POST['alerts'] == '') 				$_POST['alerts'] = 0;
	if (!isset($_POST['graphical']) || $_POST['graphical'] == '') 				$_POST['graphical'] = 0;
	if (!isset($_POST['reports']) || $_POST['reports'] == '') 				$_POST['reports'] = 0;
	if (!isset($_POST['calendar']) || $_POST['calendar'] == '') 				$_POST['calendar'] = 0;
	if (!isset($_POST['record_create']) || $_POST['record_create'] == '') 			$_POST['record_create'] = 0;
	if (!isset($_POST['record_rename']) || $_POST['record_rename'] == '') 			$_POST['record_rename'] = 0;
	if (!isset($_POST['record_delete']) || $_POST['record_delete'] == '') 			$_POST['record_delete'] = 0;
	if (!isset($_POST['participants']) || $_POST['participants'] == '') 			$_POST['participants'] = 0;
	if (!isset($_POST['data_quality_design']) || $_POST['data_quality_design'] == '') 	$_POST['data_quality_design'] = 0;
	if (!isset($_POST['data_quality_execute']) || $_POST['data_quality_execute'] == '') 	$_POST['data_quality_execute'] = 0;
	if (!isset($_POST['data_quality_resolution']) || $_POST['data_quality_resolution'] == '') $_POST['data_quality_resolution'] = 0;
	if (!isset($_POST['api_export']) || $_POST['api_export'] == '') $_POST['api_export'] = 0;
	if (!isset($_POST['api_import']) || $_POST['api_import'] == '') $_POST['api_import'] = 0;
	if (!isset($_POST['api_modules']) || $_POST['api_modules'] == '') $_POST['api_modules'] = 0;
	if (!isset($_POST['mobile_app']) || $_POST['mobile_app'] == '') $_POST['mobile_app'] = 0;
	if (!isset($_POST['mobile_app_download_data']) || $_POST['mobile_app_download_data'] == '') $_POST['mobile_app_download_data'] = 0;
	if (!isset($_POST['expiration']) || $_POST['expiration'] == '') 			$_POST['expiration'] = 'NULL'; else $_POST['expiration'] = "'".$_POST['expiration']."'";
	if (!isset($_POST['dts']) || $_POST['dts'] == '')	$_POST['dts'] = 0;
	if (!isset($_POST['random_setup']) || $_POST['random_setup'] == '') 			$_POST['random_setup'] = 0;
	if (!isset($_POST['random_dashboard']) || $_POST['random_dashboard'] == '') 		$_POST['random_dashboard'] = 0;
	if (!isset($_POST['random_perform']) || $_POST['random_perform'] == '') 		$_POST['random_perform'] = 0;
	if (!isset($_POST['realtime_webservice_mapping']) || $_POST['realtime_webservice_mapping'] == '') $_POST['realtime_webservice_mapping'] = 0;
	if (!isset($_POST['realtime_webservice_adjudicate']) || $_POST['realtime_webservice_adjudicate'] == '') $_POST['realtime_webservice_adjudicate'] = 0;
    if (!isset($_POST['mycap_participants']) || $_POST['mycap_participants'] == '') 			$_POST['mycap_participants'] = 0;

	if (SUPER_USER && isset($_POST['external_module_config']) && !empty($_POST['external_module_config']) && is_array($_POST['external_module_config'])) {
		$_POST['external_module_config'] = json_encode($_POST['external_module_config']);
	} elseif (SUPER_USER && !isset($_POST['external_module_config'])) {
		$_POST['external_module_config'] = '';
	} else {
		unset($_POST['external_module_config']);
	}

	if (!isset($_POST['group_role'])) {
        // If user already exists and is currently in a DAG and is editing their OWN rights, then don't reset group_role
        if ($user == USERID && $_POST['role_name'] == '') {
            $projectUsers = UserRights::getPrivileges($project_id);
            $_POST['group_role'] = $projectUsers[$project_id][USERID]['group_id'];
        } else {
            $_POST['group_role'] = '';
        }
    }

	// Delete role
	if ($submit_action == "delete_role") {
		// The $user is actually the role ID.
		if(UserRights::removeRole($project_id, $user, $role_name)){
			// Set context message
			$context_msg = $context_msg_delete;
		}

	// Copy role
	} elseif ($submit_action == "copy_role") {
		$sql = "select * from redcap_user_roles where project_id = $project_id and role_id = '".db_escape($user)."'";
		$q = db_query($sql);
		if ($q) {
			$row = db_fetch_assoc($q);
			// Remove project_id, role_name, and role_id from $row since we don't need them
			unset($row['project_id'], $row['role_id'], $row['role_name'], $row['unique_role_name']);
			// Loop through $row values and escape them for query
			foreach ($row as &$val) $val = checkNull($val);
			// Set the field names and corresponding values for query
			$role_fields = implode(", ", array_keys($row));
			$role_values = implode(", ", $row);
			$sql = "insert into redcap_user_roles (project_id, role_name, $role_fields) values ($project_id, '".db_escape($role_name)."', $role_values)";
			db_query($sql);
			// Get role_id
			$role_id = db_insert_id();
			// Set context message
			$context_msg = $context_msg_insert;
			// Logging
			Logging::logEvent($sql,"redcap_user_rights","insert",$user,"role = '$role_name'","Copy role");
			// Add hidden input on the page to denote which role was just copied
			print RCView::hidden(array('id'=>'copy_role_success', 'value'=>$role_id));
		}

	// Delete user
	} elseif ($submit_action == "delete_user") {
		if (UserRights::removePrivileges($project_id, $user, $ExtRes))
		{
			// Set context message
			$context_msg = $context_msg_delete;
		}

	// Edit existing role
	} elseif ($submit_action == "edit_role") {

		//Update project rights table
		$set_values =
			"role_name = '".db_escape($role_name)."',".
			"data_export_tool = null, ".
			"data_import_tool = '".db_escape($_POST['data_import_tool'])."',".
			"data_comparison_tool = '".db_escape($_POST['data_comparison_tool'])."', ".
			"data_logging = '".db_escape($_POST['data_logging'])."',".
			"email_logging = '".db_escape($_POST['email_logging'])."',".
			"file_repository = '".db_escape($_POST['file_repository'])."', ".
			"double_data = '".db_escape($_POST['double_data'])."',".
			"user_rights = '".db_escape($_POST['user_rights'])."', ".
			"data_access_groups = '".db_escape($_POST['data_access_groups'])."',".
			"lock_record = '".db_escape($_POST['lock_record'])."', ".
			"lock_record_multiform = '".db_escape($_POST['lock_record_multiform'])."',".
			"lock_record_customize = '".db_escape($_POST['lock_record_customize'])."', ".
			"design = '".db_escape($_POST['design'])."', ".
			"alerts = '".db_escape($_POST['alerts'])."',".
			"record_create = '".db_escape($_POST['record_create'])."',".
			"record_rename = '".db_escape($_POST['record_rename'])."',".
			"record_delete = '".db_escape($_POST['record_delete'])."',".
			"graphical = '".db_escape($_POST['graphical'])."',".
			"calendar = '".db_escape($_POST['calendar'])."',".
			"reports = '".db_escape($_POST['reports'])."',".
			"dts = '".db_escape($_POST['dts'])."',".
			"participants = '".db_escape($_POST['participants'])."',".
			"data_quality_design = '".db_escape($_POST['data_quality_design'])."',".
			"data_quality_execute = '".db_escape($_POST['data_quality_execute'])."',".
			"data_quality_resolution = '".db_escape($_POST['data_quality_resolution'])."',".
			"api_export = '".db_escape($_POST['api_export'])."',".
			"api_import = '".db_escape($_POST['api_import'])."',".
			"api_modules = '".db_escape($_POST['api_modules'])."',".
			"mobile_app = '".db_escape($_POST['mobile_app'])."',".
			"mobile_app_download_data = '".db_escape($_POST['mobile_app_download_data'])."',".
			"random_setup = '".db_escape($_POST['random_setup'])."',".
			"random_dashboard = '".db_escape($_POST['random_dashboard'])."',".
			"random_perform = '".db_escape($_POST['random_perform'])."',".
			"mycap_participants = '".db_escape($_POST['mycap_participants'])."',".
			"realtime_webservice_mapping = '".db_escape($_POST['realtime_webservice_mapping'])."',".
			"realtime_webservice_adjudicate = '".db_escape($_POST['realtime_webservice_adjudicate'])."',".
			(isset($_POST['external_module_config']) ? "external_module_config = ".checkNull($_POST['external_module_config'])."," : "") . 
			"data_entry = '";
		// DATA VIEWING: Process each form's radio button value
		foreach (array_keys($Proj->forms) as $form_name) {
			$this_rights = [];
			$this_field = "form-" . $form_name;
			$this_rights[] = ($_POST[$this_field] == '') ? "no-access" : $_POST[$this_field];
			// Checkboxes
			foreach (["form-delete-", "form-editresp-"] as $prefix) {
				$this_field = $prefix . $form_name;
				if (isset($_POST[$this_field]) && $_POST[$this_field] == 1) {
					$this_rights[] = explode("-", $prefix)[1];
				}
			}
			// Set value for this form
			$this_value = UserRights::encodeDataViewingRights($this_rights);
			$set_values .= "[$form_name,$this_value]";
		}
		// DATA EXPORT: Process each form's radio button value
		$set_values .= "', data_export_instruments = '";
		foreach (array_keys($Proj->forms) as $form_name) {
			$this_field = "export-form-" . $form_name;
			$this_value = ($_POST[$this_field] == '') ? 0 : $_POST[$this_field];
			// Set value for this form
			$set_values .= "[$form_name,$this_value]";
		}
		$set_values .= "'";
		$sql = "UPDATE redcap_user_roles SET $set_values WHERE role_id = '".db_escape($user)."' and project_id = $project_id";
		if (db_query($sql)) {
			//Set context message
			$context_msg = $context_msg_update;
			//Logging
			Logging::logEvent($sql,"redcap_user_rights","update",$user,"role = '$role_name'","Edit role");
		}


	// Edit existing user
	} elseif ($submit_action == "edit_user") {

		//Update project rights table
		$set_values =  "data_export_tool = null, data_import_tool = '".db_escape($_POST['data_import_tool'])."',
						data_comparison_tool = '".db_escape($_POST['data_comparison_tool'])."', data_logging = '".db_escape($_POST['data_logging'])."',
						email_logging = '".db_escape($_POST['email_logging'])."',
						file_repository = '".db_escape($_POST['file_repository'])."', double_data = '".db_escape($_POST['double_data'])."',
						user_rights = '".db_escape($_POST['user_rights'])."', data_access_groups = '".db_escape($_POST['data_access_groups'])."',
						lock_record = '".db_escape($_POST['lock_record'])."', lock_record_multiform = '".db_escape($_POST['lock_record_multiform'])."',
						lock_record_customize = '".db_escape($_POST['lock_record_customize'])."', design = '".db_escape($_POST['design'])."', alerts = '".db_escape($_POST['alerts'])."',
						expiration = {$_POST['expiration']} , record_create = '".db_escape($_POST['record_create'])."',
						record_rename = '".db_escape($_POST['record_rename'])."', record_delete = '".db_escape($_POST['record_delete'])."',
						graphical = '".db_escape($_POST['graphical'])."', calendar = '".db_escape($_POST['calendar'])."', reports = '".db_escape($_POST['reports'])."',
						dts = '".db_escape($_POST['dts'])."', participants = '".db_escape($_POST['participants'])."',
						data_quality_design = '".db_escape($_POST['data_quality_design'])."', data_quality_execute = '".db_escape($_POST['data_quality_execute'])."',
						data_quality_resolution = '".db_escape($_POST['data_quality_resolution'])."',
						api_export = '".db_escape($_POST['api_export'])."', api_import = '".db_escape($_POST['api_import'])."', api_modules = '".db_escape($_POST['api_modules'])."', mobile_app = '".db_escape($_POST['mobile_app'])."',
						mobile_app_download_data = '".db_escape($_POST['mobile_app_download_data'])."',
						random_setup = '".db_escape($_POST['random_setup'])."', random_dashboard = '".db_escape($_POST['random_dashboard'])."', random_perform = '".db_escape($_POST['random_perform'])."',
						realtime_webservice_mapping = '".db_escape($_POST['realtime_webservice_mapping'])."', realtime_webservice_adjudicate = '".db_escape($_POST['realtime_webservice_adjudicate'])."',
						mycap_participants = '".db_escape($_POST['mycap_participants'])."',
						" . (isset($_POST['external_module_config']) ? "external_module_config = ".checkNull($_POST['external_module_config'])."," : "") . "
						data_entry = '";
		// DATA VIEWING: Process each form's radio button value
		foreach (array_keys($Proj->forms) as $form_name) {
			$this_rights = [];
			$this_field = "form-" . $form_name;
			$this_rights[] = ($_POST[$this_field] == '') ? "no-access" : $_POST[$this_field];
			// Checkboxes
			foreach (["form-delete-", "form-editresp-"] as $prefix) {
				$this_field = $prefix . $form_name;
				if (isset($_POST[$this_field]) && $_POST[$this_field] == 1) {
					$this_rights[] = explode("-", $prefix)[1];
				}
			}
			// Set value for this form
			$this_value = UserRights::encodeDataViewingRights($this_rights);
			$set_values .= "[$form_name,$this_value]";
		}
		// DATA EXPORT: Process each form's radio button value
		$set_values .= "', data_export_instruments = '";
		foreach (array_keys($Proj->forms) as $form_name) {
			// Process each form's radio button value
			$this_field = "export-form-" . $form_name;
			$this_value = ($_POST[$this_field] == '') ? 0 : $_POST[$this_field];
			// Set value for this form
			$set_values .= "[$form_name,$this_value]";
		}
		$set_values .= "', group_id = ".checkNull($_POST['group_role']);
		$sql = "UPDATE redcap_user_rights SET $set_values WHERE username = '".db_escape($user)."' and project_id = $project_id";
		if (db_query($sql)) {
			//Set context message
			$context_msg = $context_msg_update;
			//Logging
			Logging::logEvent($sql,"redcap_user_rights","update",$user,"user = '".db_escape($user)."'","Edit user");
		}


	// Add new role
	} elseif ($submit_action == "add_role") {
		if(UserRights::addRole($Proj, $role_name, $user)){
			// Set context message
			$context_msg = $context_msg_insert;
		}


	// Add new user
	} elseif ($submit_action == "add_user") {

		// Insert user into user rights table
		$fields = "project_id, username, data_export_tool, data_import_tool, data_comparison_tool, data_logging, email_logging, file_repository, double_data, " .
				  "user_rights, design, alerts, expiration, lock_record, lock_record_multiform, lock_record_customize, data_access_groups, graphical, reports, calendar, " .
				  "record_create, record_rename, record_delete, dts, participants, data_quality_design, data_quality_execute, data_quality_resolution,
				  api_export, api_import, api_modules, mobile_app, mobile_app_download_data,
				  random_setup, random_dashboard, random_perform, realtime_webservice_mapping, realtime_webservice_adjudicate, external_module_config,
				  mycap_participants, 
				  data_entry, data_export_instruments, group_id";
		$values =  "$project_id, '".db_escape($user)."', null, '".db_escape($_POST['data_import_tool'])."', '".db_escape($_POST['data_comparison_tool'])."',
					'".db_escape($_POST['data_logging'])."', '".db_escape($_POST['email_logging'])."', '".db_escape($_POST['file_repository'])."', '".db_escape($_POST['double_data'])."', '".db_escape($_POST['user_rights'])."',
					'".db_escape($_POST['design'])."', '".db_escape($_POST['alerts'])."', {$_POST['expiration']}, '".db_escape($_POST['lock_record'])."', '".db_escape($_POST['lock_record_multiform'])."',
					'".db_escape($_POST['lock_record_customize'])."', '".db_escape($_POST['data_access_groups'])."', '".db_escape($_POST['graphical'])."', '".db_escape($_POST['reports'])."',
					'".db_escape($_POST['calendar'])."', '".db_escape($_POST['record_create'])."', '".db_escape($_POST['record_rename'])."', '".db_escape($_POST['record_delete'])."',
					'".db_escape($_POST['dts'])."', '".db_escape($_POST['participants'])."', '".db_escape($_POST['data_quality_design'])."', '".db_escape($_POST['data_quality_execute'])."', '".db_escape($_POST['data_quality_resolution'])."',
					'".db_escape($_POST['api_export'])."', '".db_escape($_POST['api_import'])."', '".db_escape($_POST['api_modules'])."', '".db_escape($_POST['mobile_app'])."', '".db_escape($_POST['mobile_app_download_data'])."', '".db_escape($_POST['random_setup'])."', '".db_escape($_POST['random_dashboard'])."',
					'".db_escape($_POST['random_perform'])."', '".db_escape($_POST['realtime_webservice_mapping'])."', '".db_escape($_POST['realtime_webservice_adjudicate'])."', ".checkNull($_POST['external_module_config'] ?? "").", 
                    '".db_escape($_POST['mycap_participants'])."', '";
		// DATA VIEWING: Process each form's radio button value
		foreach (array_keys($Proj->forms) as $form_name) {
			$this_field = "form-" . $form_name;
			$this_value = ($_POST[$this_field] == '') ? 0 : $_POST[$this_field];
			// If set survey responses to be editable, then set to value 3
			$editresp_chkbox_name = "form-editresp-" . $form_name;
			if ($this_value == '1' && isset($_POST[$editresp_chkbox_name]) && $_POST[$editresp_chkbox_name])
			{
				$this_value = 3;
			}
			$values .= "[$form_name,$this_value]";
		}
        // DATA EXPORT: Process each form's radio button value
		$values .= "', '";
		foreach (array_keys($Proj->forms) as $form_name) {
			$this_field = "export-form-" . $form_name;
			$this_value = ($_POST[$this_field] == '') ? 0 : $_POST[$this_field];
			$values .= "[$form_name,$this_value]";
		}
		$values .= "', ".checkNull($_POST['group_role']);
		// Insert user into user_rights table
		$sql = "INSERT INTO redcap_user_rights ($fields) VALUES ($values)";
		if (db_query($sql)) {
			// Set context message
			$context_msg = $context_msg_insert;
			// Logging
			Logging::logEvent($sql,"redcap_user_rights","insert",$user,"user = '".db_escape($user)."'","Add user");
		}

	}

	//If checkbox was checked to notify new user of their access, send an email (but don't send if one has just been sent)
	if (isset($_POST['notify_email']) && $_POST['notify_email'] && $submit_action == "add_user")
	{
		$email = new Message();
		$emailContents = "
			<html><body style='font-family:arial,helvetica;'>
			{$lang['global_21']}<br /><br />
			{$lang['rights_88']} \"<a href=\"".APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/index.php?pid=".PROJECT_ID."\">".strip_tags(str_replace("<br>", " ", label_decode($app_title)))."</a>\"{$lang['period']}
			{$lang['rights_89']} \"$user\", {$lang['rights_90']}<br /><br />
			".APP_PATH_WEBROOT_FULL."
			</body>
			</html>";
		//First need to get the email address of the user we're emailing
		$q = db_query("select user_firstname, user_lastname, user_email from redcap_user_information where username = '".db_escape($user)."'");
		$row = db_fetch_array($q);
		$email->setTo($row['user_email']);
		$email->setFrom($user_email);
		$email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
		$email->setSubject($lang['rights_122']);
		$email->setBody($emailContents);
		if (!$email->send()) {
			print  "<br><div style='font-size:12px;background-color:#F5F5F5;border:1px solid #C0C0C0;padding:10px;'>
					<div style='font-weight:bold;border-bottom:1px solid #aaaaaa;color:#A00000;'>
					<img src='".APP_PATH_IMAGES."exclamation.png' style='position:relative;top:3px;'>
					{$lang['rights_80']}
					</div><br>
					{$lang['global_37']} <span style='color:#666;'>$user_firstname $user_lastname &#60;$user_email&#62;</span><br>
					{$lang['global_38']} <span style='color:#666;'>".$row['user_firstname']." ".$row['user_lastname']." &#60;".$row['user_email']."&#62;</span><br>
					{$lang['rights_83']} <span style='color:#666;'>{$lang['rights_91']}</span><br><br>
					$emailContents<br>
					</div><br>";
		}
	}

	// Return html to redisplay the user/role table
	print $context_msg;
	print UserRights::renderUserRightsRolesTable();
	exit;
}







// Check if $user is a role or a username
$isRole = false;
$role_id = $role_name = null;
if (isset($_POST['username']) && $_POST['username'] != '' && $_POST['role_id'] == '') {
	## NEW/EXISTING USER
	// Remove illegal characters (if somehow posted bypassing javascript)
	$user = preg_replace("/[^a-zA-Z0-9-'\s\.@_]/", "", $_POST['username']);
	if (!isset($_POST['username']) || $user != $_POST['username']) exit('');
	$user = $_POST['username'];
} elseif (isset($_POST['role_id']) && is_numeric($_POST['role_id']) && $_POST['role_id'] == '0') {
	## ADDING NEW ROLE
	$isRole = true;
	$role_id = '0';
	$role_name = strip_tags(html_entity_decode($_POST['username'], ENT_QUOTES));
} elseif ((is_numeric($_POST['username']) && isset($roles[$_POST['username']])) || ($_POST['username'] == '' && is_numeric($_POST['role_id']))) {
	## EXISTING ROLE
	$isRole = true;
	if (is_numeric($_POST['role_id'])) {
		$role_id = $_POST['role_id'];
		$role_name = $roles[$_POST['role_id']]['role_name'];
	} else {
		$role_id = $_POST['username'];
		$role_name = $roles[$_POST['username']]['role_name'];
	}
}


if (!$isRole)
{
	//If the person using this application is in a Data Access Group, do not allow them to add a new user or edit user from another group.
	if ($user_rights['group_id'] != "") {
		//If we are not editing someone in our group, redirect back to previous page
		$is_in_group = db_result(db_query("select count(1) from redcap_user_rights where project_id = $project_id
										   and username = '".db_escape($user)."' and group_id = '".$user_rights['group_id']."'"),0);
		if ($is_in_group == 0) {
			//User not in our group, so give error
			exit('');
		}
	}

	// Don't allow Table-based auth users to be added if don't already exist in redcap_auth. They must be created in Control Center first.
	$this_user_rights = UserRights::getPrivileges($project_id, $user);
	$isAlreadyUserinProject = isset($this_user_rights[$project_id][$user]);
	if ($auth_meth_global == "table" && !$isAlreadyUserinProject && !Authentication::isTableUser($user))
	{
        print  "<div class='red'>
                <img src='".APP_PATH_IMAGES."exclamation.png'> <b>{$lang['global_03']}:</b><br><br>
                {$lang['rights_104']} \"<b>".RCView::escape($user)."</b>\" {$lang['rights_105']} ";
        if (!$super_user) {
            print  $lang['rights_146'];
        } else {
            print  "{$lang['rights_107']}
                <a href='".APP_PATH_WEBROOT."ControlCenter/create_user.php' target='_blank'
                    style='text-decoration:underline;'>{$lang['rights_108']}</a>
                {$lang['rights_109']}";
        }
        print  "</div>";
        exit;
	}

}



if ($isRole) {
	// Query for role
	$q = db_query("select * from redcap_user_roles where project_id = $project_id and role_id = '".db_escape($role_id)."' limit 1");
} else {
	// Query for user
	$q = db_query("select * from redcap_user_rights where project_id = $project_id and username = '".db_escape($user)."' limit 1");
}
// Set flag if a new user
$new_user = (!db_num_rows($q));




if (!$new_user)
{
	// User Messaging: Warning if user being deleted is part of conversations associated with this project
	$userMsgConvs = "";
	if ($user_messaging_enabled && !$isRole && $user != USERID)
	{
		// See if they're on any project conversations
		$convList = Messenger::getUserConvTitlesForProject($user, PROJECT_ID);
		if (!empty($convList)) {
			$userMsgConvs = "<hr>" . $lang['messaging_02'] . 
							"<br><br><b>" . $lang['messaging_03'] . "</b><br> - \"" . 
							implode("\"<br> - \"", $convList) . "\"";
		}
	}
	
	## EXISTING USER/ROLE
	// Set DELETE user/role javascript
	$deleteUserJs = "var delUserAction = function(){
						$('form#user_rights_form input[name=\'submit-action\']').val('".($isRole ? 'delete_role' : 'delete_user')."');
						saveUserFormAjax();
					};
					simpleDialog('".js_escape($isRole ? $lang['rights_192'] :
						(($user == USERID ? $lang['rights_194'] : $lang['rights_193']).(!MobileApp::userHasInitializedProjectInApp($user, PROJECT_ID) ? '' :
						RCView::div(array('style'=>'margin-top:10px;font-weight:bold;color:#C00000;'), $lang['rights_313'])).$userMsgConvs)).
						"','".js_escape(($isRole ? $lang['rights_190'] : $lang['rights_191']).$lang['questionmark'])."',null,550,null,'".js_escape($lang['global_53'])."',delUserAction,'".js_escape($isRole ? $lang['rights_190'] : $lang['rights_191'])."');";
	// Existing user/role
	if ($isRole) {
		$context_msg =  "<i class=\"fas fa-user-tag\"></i> {$lang['rights_157']} \"<b>".RCView::escape($role_name)."</b>\"";
		$submit_action = "edit_role";
		// To check if we can DELETE a role, get all user rights as array. Add a sub-array of users to each role that are assigned to it.
		$roleHasUsers = $roleHasUsersOtherDAG = false;
		foreach (UserRights::getRightsAllUsers(false) as $this_user=>$attr) {
			// Set flag if the user in this loop is assigned to a role
			if (is_numeric($attr['role_id']) && $attr['role_id'] == $role_id) {
				// Yes, at least one user is in this role
				$roleHasUsers = true;
				// If the user loading this page is in a DAG *and* users from another DAG or users not in a DAG are assigned to this role, set the flag to TRUE
				if ($user_rights['group_id'] != "" && (!is_numeric($attr['group_id'])
					|| (is_numeric($attr['group_id']) && $attr['group_id'] != $user_rights['group_id']))) {
					$roleHasUsersOtherDAG = true;
				}
			}
		}
		if ($roleHasUsers) {
			// Prevent user from deleting the role since it has users in it
			$deleteUserJs = "simpleDialog('".js_escape($lang['rights_164'])."','".js_escape($lang['rights_205'])."');";
		}
		// If the user loading this page is in a DAG *and* users from another DAG are assinged to this role, then prevent user from editing it
		if ($roleHasUsersOtherDAG) {
			// STOP HERE and prevent user from editing this role
			print 	RCView::div(array('class'=>'yellow', 'style'=>''),
						RCView::img(array('src'=>'exclamation_orange.png')) .
						RCView::b($lang['global_03'] . $lang['colon'] . " " . $lang['rights_223'] . " \"".RCView::escape($role_name)."\"") .
						RCView::div(array('style'=>'margin-top:10px;'), $lang['rights_224'])
					);
			exit;
		}

	} else {
		$context_msg =  "<i class=\"fas fa-user-edit\"></i> {$lang['rights_09']} \"<b>".RCView::escape($user)."</b>\"";
		$submit_action = "edit_user";
	}
	$submit_buttons =  "add_user_dialog_btns =
						[{ text: '".js_escape($isRole ? $lang['rights_190'] : $lang['rights_191'])."', click: function() {
							$deleteUserJs
						}},
						".
						// Copy role button
						(!$isRole ? '' : "
							{ text: '".js_escape($lang['rights_211'])."', click: function() {
								copyRoleName('".htmlspecialchars(RCView::escape($role_name), ENT_QUOTES)."')
							}},"
						)."
						{ text: '".js_escape($lang['global_53'])."', click: function() {
							$('#editUserPopup').dialog('destroy');
						}},
						{text: '".js_escape($lang['report_builder_28'])."', click: function() {
							saveUserFormAjax();
						}}];";
	$submit_text = $lang['report_builder_28'];
	$context_msg_color = "alert alert-primary";
	//Get variable for pre-filling checkboxes
	$this_user = db_fetch_assoc($q);
	$data_export_tool = $this_user['data_export_tool'];
	$data_import_tool = $this_user['data_import_tool'];
	$data_comparison_tool = $this_user['data_comparison_tool'];
	$data_logging = $this_user['data_logging'];
	$email_logging = $this_user['email_logging'];
	$file_repository = $this_user['file_repository'];
	$double_data = $this_user['double_data'];
	$user_rights1 = $this_user['user_rights'];
	$expiration = $this_user['expiration'] ?? "";
	$group_id = $this_user['group_id'] ?? "";
	$lock_record = $this_user['lock_record'];
	$lock_record_multiform = $this_user['lock_record_multiform'];
	$lock_record_customize = $this_user['lock_record_customize'];
	$data_access_groups = $this_user['data_access_groups'];
	$graphical = $this_user['graphical'];
	$reports1 = $this_user['reports'];
	$chbx_email_newuser = "";
	$design = $this_user['design'];
	$dts = $this_user['dts'];
	$calendar = $this_user['calendar'];
	$alerts = $this_user['alerts'];
	$record_create = $this_user['record_create'];
	$record_rename = $this_user['record_rename'];
	$record_delete = $this_user['record_delete'];
	$participants = $this_user['participants'];
	$data_quality_design = $this_user['data_quality_design'];
	$data_quality_execute = $this_user['data_quality_execute'];
	$data_quality_resolution = $this_user['data_quality_resolution'];
	$api_export = $this_user['api_export'];
	$api_import = $this_user['api_import'];
	$api_modules = $this_user['api_modules'];
	$mobile_app = $this_user['mobile_app'];
	$mobile_app_download_data = $this_user['mobile_app_download_data'];
	$random_setup = $this_user['random_setup'];
	$random_dashboard = $this_user['random_dashboard'];
	$random_perform = $this_user['random_perform'];
	$realtime_webservice_mapping = $this_user['realtime_webservice_mapping'];
	$realtime_webservice_adjudicate = $this_user['realtime_webservice_adjudicate'];
    $mycap_participants = $this_user['mycap_participants'];
	//Loop through data entry forms and parse their values
	$dataEntryArr = UserRights::convertFormRightsToArrayPre($this_user['data_entry']);
	foreach ($dataEntryArr as $keyval)
	{
        if (strpos($keyval, ",") === false) continue;
		list($key, $value) = explode(",", $keyval, 2);
		$this_user["form-".$key] = $value;
	}
	//Loop through data export forms and parse their values
	if ($this_user['data_export_instruments'] == null && isinteger($this_user['data_export_tool'])) {
        // Transform legacy values
		$this_user['data_export_instruments'] = count($Proj->forms) == 0 ? "" : "[" . implode(",{$this_user['data_export_tool']}][", array_keys($Proj->forms)) . ",{$this_user['data_export_tool']}]";
	}
	$dataExportArr = UserRights::convertFormRightsToArrayPre($this_user['data_export_instruments']);
	foreach ($dataExportArr as $keyval)
	{
		if (strpos($keyval, ",") === false) continue;
		list($key, $value) = explode(",", $keyval, 2);
		$this_user["export-form-".$key] = $value;
	}
	unset($this_user['data_entry'], $this_user['data_export_instruments']);

}

// New user/role
else
{
	if ($isRole) {
		// New role
		$context_msg =  "<i class=\"fas fa-user-tag\"></i> {$lang['rights_159']} \"<b>".RCView::escape($role_name)."</b>\"";
		$submit_action = "add_role";
	} else {
		// New user
		$context_msg =  "<i class=\"fas fa-user-plus\"></i> {$lang['rights_11']} \"<b>".RCView::escape($user)."</b>\"";
		$submit_action = "add_user";

		## CUSTOM USERNAME VERIFICATION SCRIPT (FOR EXTERNAL AUTHENTICATION)
		// If custom PHP script is specified in Control Center, call the custom validation function.
		// If a message is returned, then output the message in a red div and do an EXIT().
		if (!Authentication::isTableUser($user)) {
			Hooks::call('redcap_custom_verify_username', array($user));
		}
	}
	$submit_buttons =  "add_user_dialog_btns =
						[{ text: '".js_escape($lang['global_53'])."', click: function() {
							$('#editUserPopup').dialog('destroy');
						}},
						{text: '".js_escape($isRole ? $lang['rights_158'] : $lang['rights_187'])."', click: function() {
							saveUserFormAjax();
						}}];";
	$submit_text = ($isRole ? $lang['rights_158'] : $lang['rights_187']);
	$context_msg_color = "alert alert-success";
	//Set variables to default for new user
	$data_export_tool = 2;
	$data_import_tool = 0;
	$data_comparison_tool = 0;
	$data_logging = 0;
    $email_logging = 0;
	$file_repository = 1;
	$double_data = 0;
	$user_rights1 = 0;
	$expiration = '';
	$group_id = '';
	$lock_record = 0;
	$lock_record_multiform = 0;
	$lock_record_customize = 0;
	$data_access_groups = 0;
	$graphical = 1;
	$reports1 = 1;
	$design = 0;
	$alerts = 0;
	$dts = 0;
	$calendar = 1;
	$record_create = 1;
	$record_rename = 0;
	$record_delete = 0;
	$participants = 1;
	$data_quality_design = 0;
	$data_quality_execute = 0;
	$data_quality_resolution = 1;
	$api_export = 0;
	$api_import = 0;
	$api_modules = 0;
	$mobile_app = 0;
	$mobile_app_download_data = 0;
	$random_setup = 0;
	$random_dashboard = 0;
	$random_perform = ($randomization ? 1 : 0);
	$realtime_webservice_mapping = 0;
	$realtime_webservice_adjudicate = 0;
	$mycap_participants = 1;
	//If we already have this new user's email address on file, provide the ability to notify them of their project access via email
	$chbx_email_newuser = isset($user) ? db_result(db_query("select user_email from redcap_user_information where username = '".db_escape($user)."' and username != ''"),0) : "";
	if ($chbx_email_newuser != "") {
		$chbx_email_newuser =  "<div style='margin:20px 0 0;position: relative;top:6px;z-index:106;color:#505050;width:160px;font-weight:bold;font-size:11px;text-align:center;background:#eee;padding:2px;border:1px solid #bbb;border-bottom-width: 0px;'>
									{$lang['rights_202']}
								</div>
								<div style='position: relative;border:1px solid #bbb;background:#eee;padding:10px 14px;'>
									<img src='".APP_PATH_IMAGES."email.png'>&nbsp;&nbsp;{$lang['rights_112']}
									&nbsp;<input type='checkbox' name='notify_email' checked>
								</div>";
	}
}

// Get project information
$Proj = new Project();
$groups = $Proj->getGroups();
$dags_options = (!empty($groups)) ? RCView::select(array('id'=>'group_role', 'name'=>'group_role', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 10px 0 6px;'),
    (array(''=>"[{$lang['data_access_groups_ajax_16']}]") + $groups), $group_id)
    : "";

$assign_to_group_html = '';

$currentDagId = 0;
if (!empty($user_rights['group_id'])) $currentDagId = $user_rights['group_id'];

if ($dags_options != '' && !isinteger($role_id) && $currentDagId == 0) {
    $assign_to_group_html = "<div style='margin:20px 0 0;position: relative;top:6px;z-index:106;color:#505050;width:250px;font-weight:bold;font-size:11px;text-align:center;background:#eee;padding:2px;border:1px solid #bbb;border-bottom-width: 0px;'>
                                        {$lang['rights_397']}
                                    </div>
                                    <div style='position: relative;border:1px solid #bbb;background:#eee;padding:10px 14px;'>
                                        <i class='fas fa-user-tag me-1'></i>{$lang['rights_398']}
                                        &nbsp;".$dags_options."
                                    </div>";
}

// Instructions
print 	RCView::div(array('style'=>'margin-bottom:10px;'),
			"{$lang['rights_44']} \"$submit_text\" {$lang['rights_45']}"
		);


// Show message if adding/editing user
print 	RCView::div(array('class'=>'fs14 '.$context_msg_color,'style'=>'text-align:center;margin:15px 0;'),
			// "Adding new user" msg
			$context_msg
		);
		
// Display note about editing a super user's rights
if (!$isRole && User::isSuperUser($user)) {
	print 	RCView::div(array('class'=>"alert alert-warning mb-3"),
				// "User is super user" msg
				$lang['rights_353']
			);
}

// Display add/edit user/role form
print  "<form id='user_rights_form' name='user_rights_form' method='post' action='".APP_PATH_WEBROOT."UserRights/index.php?pid=$project_id'>";

// Hide dialog attributes inside hidden divs that will be using by JavaScript (i.e. dialog title)
print 	RCView::div(array('class'=>'hidden'),
			RCView::div(array('id'=>'dialog_title'), $context_msg) .
			// Submit action (add/edit user/role)
			RCView::hidden(array('name'=>'submit-action', 'value'=>$submit_action)) .
			// Submit buttons
			RCView::div(array('id'=>'submit-buttons'),
				$submit_buttons
			)
		);

// Begin table
print  "<table style='width:100%;'>
		<tr><td valign='top' style='width:475px;'>
			<div class='card' style='border-color:#00000060;'>
			<div class='card-header font-weight-bold fs14' style='background-color:#00000017;'>
				{$lang['rights_431']}
			</div>
			<div class='card-body p-3' style='background-color:#00000007;'>
			<table id='user-rights-left-col'>";

if ($isRole) {
	// Edit nole name
	if (!$new_user) {
		print  "<tr>
					<td valign='top' colspan='2' style='padding-bottom:5px;'>
						<img src='".APP_PATH_IMAGES."vcard.png' >
						&nbsp;&nbsp;{$lang['rights_199']}
						<input type='text' value=\"".RCView::escape($role_name)."\" class='x-form-text x-form-field' style='margin:0 0 5px 8px;width:150px;' name='role_name_edit' onblur=\"$(this).val($(this).val().trim()); if ($(this).val()=='') simpleDialog('".js_escape($lang['rights_358'])."',null,null,null,function(){ $('input[name=role_name_edit]').focus(); },'Close')\">
					</td>
				</tr>";
	}
} else {
	// Expiration Date (users only)
	print  "<tr>
				<td valign='top' style='padding-bottom:5px;'>
					<i class=\"far fa-calendar-times\"></i>&nbsp;&nbsp;{$lang['rights_54']}
					<div style='font-size:10px;color:#777;margin-left:20px;'><i>{$lang['rights_55']}</i></div>
				</td>
				<td valign='top' style='padding-top:5px;'>
					<!-- hidden input to get focus on dialog open -->
					<input type='text' class='ui-helper-hidden-accessible'>
					<input type='text' value='".DateTimeRC::format_ts_from_ymd($expiration)."' class='x-form-text x-form-field' style='width:80px;' maxlength='10' id='expiration' name='expiration' onchange=\"redcap_validate(this,'','','hard','date_'+user_date_format_validation,1,1,user_date_format_delimiter);\" onkeydown='if(event.keyCode == 13) return false;'>
					<span class='df' style='padding-left:5px;'>(".DateTimeRC::get_user_format_label().")</span>
				</td>
			</tr>";
}


print  "<tr>
			<td valign='top' colspan='2' style='border-top:1px solid #00000030;padding:6px 0 10px;color:#A00000;font-size:11px;'>
				{$lang['rights_299']}
			</td>
		</tr>";

// Project Setup/Design
print "<tr><td valign='top'><i class=\"fas fa-tasks\"></i>&nbsp;&nbsp;{$lang['rights_135']}</td><td valign='top' style='padding-top:2px;'> <input type='checkbox' name='design' ";
if ($design == 1) print "checked";
print " onclick=\"$('.ext_mod_user_right_item input.no-require-perm').prop('checked',$(this).prop('checked'));\"> </td></tr>";

//User Rights
print "<tr><td valign='top'><i class=\"fas fa-user\"></i>&nbsp;&nbsp;{$lang['app_05']}</td><td valign='top' style='padding-top:2px;'>";

print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='user_rights' value='0' "; if ($user_rights1 == '0') print "checked"; print "> {$lang['rights_47']}</div>";
print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='user_rights' value='2' "; if ($user_rights1 == '2') print "checked"; print "> {$lang['rights_61']}</div>";
print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='user_rights' value='1' "; if ($user_rights1 == '1') print "checked"; print "> {$lang['rights_440']}</div>";
print "</td></tr>";

//Data Access Groups
print "<tr><td valign='top' style='padding-bottom:10px;'><i class=\"fas fa-users\"></i>&nbsp;{$lang['global_22']}</td><td valign='top' style='padding-top:2px;'> <input type='checkbox' name='data_access_groups' ";
if ($data_access_groups == 1) print "checked";
print "> </td></tr>";


print  "<tr>
			<td valign='top' colspan='2' style='border-top:1px solid #00000030;padding:6px 0 10px;color:#A00000;font-size:11px;'>
				{$lang['rights_300']}
			</td>
		</tr>";


// MyCap Mobile App
if ($mycap_enabled_global == 1 && $mycap_enabled == 1) {
    print  "<tr>
                <td valign='top'>
                    <img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:24px;position:relative;top:-2px;margin-left:-9px;'>&nbsp;".$lang['rights_437']."
                </td>
                <td valign='top' style='padding-top:2px;'>
                    <input type='checkbox' name='mycap_participants' " . ($mycap_participants == 1 ? "checked" : "") . ">
                </td>
            </tr>
    </tr>";
}
else {
    print RCView::hidden(array('name' => 'mycap_participants', 'value' => $mycap_participants));
}

//Invite Participants rights
if ($surveys_enabled)
{
	print "<tr><td valign='top'><div style='text-indent: -32px;margin-left: 32px;'><i class=\"fas fa-chalkboard-teacher\" style='margin-right:2px;text-indent: -3px;'></i> ".$lang['app_24']."</div></td><td valign='top' style='padding-top:2px;'> <input type='checkbox' name='participants' ";
	if ($participants == 1) print "checked";
	print "> </td></tr>";
} else {
	print "<input type='hidden' name='participants' value='$participants'>";
}

// Alerts & Notifications
print "<tr><td valign='top'><i class=\"fas fa-bell\"></i>&nbsp;&nbsp;{$lang['global_154']}";
print "</td><td valign='top' style='padding-top:2px;'> <input type='checkbox' name='alerts' ";
if ($alerts == 1) print "checked";
print "> </td></tr>";

//Calendar rights
print "<tr><td valign='top'><i class=\"far fa-calendar-alt\"></i>&nbsp;&nbsp;{$lang['app_08']}";
if ($scheduling) {
    print " ".$lang['rights_357'];
}
print "</td><td valign='top' style='padding-top:2px;'> <input type='checkbox' name='calendar' ";
if ($calendar == 1) print "checked";
print "> </td></tr>";

// Reports & Report Builder
print "<tr>
		<td valign='top'>
			<i class=\"fas fa-search\"></i>&nbsp;&nbsp;{$lang['rights_356']}
			<div style='line-height:12px;padding:0px 0px 4px 22px;text-indent:-6px;font-size:11px;color:#999;'>
				&nbsp; {$lang['report_builder_130']}
			</div>
		</td>
		<td valign='top' style='padding-top:2px;'> <input type='checkbox' name='reports' ";
if ($reports1 == 1) print "checked";
print  ">
		</td>
	</tr>";

//Graphical Data View & Stats
if ($enable_plotting > 0) {
	print "<tr><td valign='top' style='padding-bottom:5px;'><img src='".APP_PATH_IMAGES."chart_bar.png'>&nbsp;&nbsp;{$lang['report_builder_78']}</td><td valign='top' style='padding-top:2px;padding-bottom:5px;'> <input type='checkbox' name='graphical' ";
	if ($graphical == 1) print "checked";
	print "> </td></tr>";
} else {
	print "<input type='hidden' name='graphical' value='$graphical'>";
}

//Only show if a Double Data Entry project
if ($double_data_entry) {
	print "<tr><td valign='top'><i class=\"fas fa-users\"></i>&nbsp;&nbsp;{$lang['rights_50']} </td><td valign='top' style='padding-top:2px;font-size:11px;color:#808080;'>
			<input type='radio' name='double_data' value='0' "; if ($double_data == 0) print "checked";
	print "> {$lang['rights_51']}<br>";
	//If data entry person #1 or #2 are already designated, do not allow user to designate another person as #1 or #2.
	$sql = "(select 1 from redcap_user_roles where double_data = '1' and project_id = $project_id " . ($isRole ? "and role_id != $role_id" : "") . " limit 1)
			union
			(select 1 from redcap_user_rights where double_data = '1' and project_id = $project_id and role_id is null " . ($isRole ? "" : "and username != '".db_escape($user)."'") . " limit 1)";
	$q1 = db_query($sql);
	if (!db_num_rows($q1)) {
		print "<input type='radio' name='double_data' value='1' ";
		if ($double_data == 1) print "checked";
		print "> {$lang['rights_52']} #1<br>";
	}
	$sql = "(select 1 from redcap_user_roles where double_data = '2' and project_id = $project_id " . ($isRole ? "and role_id != $role_id" : "") . " limit 1)
			union
			(select 1 from redcap_user_rights where double_data = '2' and project_id = $project_id and role_id is null " . ($isRole ? "" : "and username != '".db_escape($user)."'") . " limit 1)";
	$q2 = db_query($sql);
	if (!db_num_rows($q2)) {
		print "<input type='radio' name='double_data' value='2' "; if ($double_data == 2) print "checked";
		print "> {$lang['rights_52']} #2</td></tr>";
	}
} else {
	//Leave double_data as hidden field if not a Double Data Entry project
	print "<input type='hidden' name='double_data' value='$double_data'>";
}

print "<tr><td valign='top'><i class=\"fas fa-file-import\"></i>&nbsp;&nbsp;{$lang['app_01']} </td><td style='padding-top:2px;' valign='top'> <input type='checkbox' name='data_import_tool' ";	if ($data_import_tool == 1) print "checked";
print "> </td></tr>
	<tr><td valign='top'><i class=\"fas fa-not-equal\"></i>&nbsp;&nbsp;{$lang['app_02']} </td><td style='padding-top:2px;' valign='top'> <input type='checkbox' name='data_comparison_tool' ";	if ($data_comparison_tool == 1) print "checked";
print "> </td></tr>
	<tr><td valign='top'><i class=\"fas fa-receipt\" style='margin-left:2px;margin-right:2px;'></i>&nbsp;&nbsp;{$lang['app_07']} </td><td style='padding-top:2px;' valign='top'> <input type='checkbox' name='data_logging' ";	if ($data_logging == 1) print "checked";
print "> </td></tr>";
if ($email_logging_enable_global) {
    print "<tr><td valign='top'><i class=\"fas fa-mail-bulk\" style='margin-right:4px;'></i>".($Proj->project['twilio_enabled'] ? $lang['email_users_96'] : $lang['email_users_53'])." </td><td style='padding-top:2px;' valign='top'> <input type='checkbox' name='email_logging' ";
    if ($email_logging == 1) print "checked";
    print "> </td></tr>";
}
print "<tr><td valign='top'><i class=\"fas fa-folder-open\"></i>&nbsp;&nbsp;{$lang['app_04']} </td>
        <td style='padding-top:2px;' valign='top'> <input type='checkbox' name='file_repository' ";
if ($file_repository == 1) print "checked";
print "> </td></tr>";

// Randomization
if ($randomization) {
	$randHelp = RCView::a(array('id' => 'randHelpLinkId', 'href' => 'javascript:;', 'onclick'=>"simpleDialog(null,null,'randHelpDialogId');", 'style' => 'font-size:11px;text-decoration:underline;'), $lang['rights_145']);
	print  "<tr><td valign='top'>
				<i class=\"fas fa-random\"></i>&nbsp;&nbsp;{$lang['app_21']}
				<div style='padding:0px 0 0px 18px;font-size:11px;color:#777;'>$randHelp</div>
			</td>
			<td valign='top' style='padding-top:2px;'> <input type='checkbox' name='random_setup' ";
	if ($random_setup == 1) print "checked";
	print  "> {$lang['rights_142']}<br/>
			<input type='checkbox' name='random_dashboard' ";
	if ($random_dashboard == 1) print "checked";
	print "> {$lang['rights_143']}<br/>
			<input type='checkbox' name='random_perform' ";
	if ($random_perform == 1) print "checked";
	print  "> {$lang['rights_144']}</td></tr>";
}
else {
	print RCView::hidden(array('name' => 'random_setup', 'value' => $random_setup));
	print RCView::hidden(array('name' => 'random_dashboard', 'value' => $random_dashboard));
	print RCView::hidden(array('name' => 'random_perform', 'value' => $random_perform));
}

// Data Quality (design & execute rights are separate)
print  "<tr>
			<td valign='top'>
				<i class=\"fas fa-clipboard-check\"></i>&nbsp;&nbsp;{$lang['app_20']}
				<div style='padding:0px 0 0px 18px;font-size:11px;color:#777;'>
					<a href='javascript:;' style='font-size:11px;text-decoration:underline;' onclick=\"
						$('#explainDataQuality').dialog({ bgiframe: true, title: '".js_escape($lang['dataqueries_100'])."', modal:true, width:550, buttons:{Close:function(){\$(this).dialog('close');}}});
					\">{$lang['dataqueries_100']}</a>
				</div>
			</td>
			<td valign='top' style='padding-top:2px;'>
				<input type='checkbox' name='data_quality_design' ".($data_quality_design == 1 ? "checked" : "").">
				{$lang['dataqueries_40']}<br>
				<input type='checkbox' name='data_quality_execute' ".($data_quality_execute == 1 ? "checked" : "").">
				{$lang['dataqueries_41']}</td>
		</tr>";

// Data Quality resolution
if ($data_resolution_enabled == '2') {

	print "<tr><td valign='top' style='width:180px;'>
				<i class='fas fa-comments'></i>&nbsp;&nbsp;{$lang['dataqueries_137']}
				<div style='padding:0px 0 0px 18px;font-size:11px;color:#777;'>
					<a href='javascript:;' style='font-size:11px;text-decoration:underline;' onclick=\"
						$('#explainDRW').dialog({ bgiframe: true, title: '".js_escape($lang['dataqueries_155'])."', modal:true, width:550, buttons:{Close:function(){\$(this).dialog('close');}}});
					\">{$lang['dataqueries_155']}</a>
				</div>
			</td>
			<td style='padding-top:2px;' valign='top' style='font-size:11px;color:#808080;'>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='0' "; if ($data_quality_resolution == '0') print "checked"; print "> {$lang['rights_47']}</div>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='1' "; if ($data_quality_resolution == '1') print "checked"; print "> {$lang['dataqueries_143']}</div>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='4' "; if ($data_quality_resolution == '4') print "checked"; print "> {$lang['dataqueries_289']}</div>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='2' "; if ($data_quality_resolution == '2') print "checked"; print "> {$lang['dataqueries_138']}</div>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='5' "; if ($data_quality_resolution == '5') print "checked"; print "> {$lang['dataqueries_290']}</div>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='3' "; if ($data_quality_resolution == '3') print "checked"; print "> {$lang['dataqueries_139']}</div>";
	print "</td></tr>";
} else {
	print "<input type='hidden' name='data_quality_resolution' value='$data_quality_resolution'>";
}

// API
if ($api_enabled) {
	$apiHelp = RCView::a(array('id' => 'apiHelpLinkId', 'href' => 'javascript:;', 'onclick'=>"simpleDialog(null,null,'apiHelpDialogId');", 'style' => 'font-size:11px;text-decoration:underline;'), $lang['rights_141']);
	print  "<tr><td valign='top'>
				<i class=\"fas fa-laptop-code\"></i>&nbsp;&nbsp;{$lang['setup_77']}
				<div style='padding:0px 0 0px 18px;font-size:11px;color:#777;'>$apiHelp</div>
			</td>
			<td valign='top' style='padding-top:2px;'>";
	print "<input type='checkbox' name='api_export'" . ($api_export == 1 ? " checked" : "") . "> " . RCView::tt("rights_139");
	print "<br>";
	print "<input type='checkbox' name='api_import'" . ($api_import == 1 ? " checked" : "") . "> " . RCView::tt("rights_314");
	if (method_exists("ExternalModules\\ExternalModules", "getEnabledApiActions")) {
		// TODO: Remove once EM Framework support is present
		print "<br>";
		print "<input type='checkbox' name='api_modules'" . ($api_modules == 1 ? " checked" : "") . "> " . RCView::tt("rights_439");
	}
	print "</td></tr>";
}
else {
	print RCView::hidden(array('name' => 'api_export', 'value' => $api_export));
	print RCView::hidden(array('name' => 'api_import', 'value' => $api_import));
	print RCView::hidden(array('name' => 'api_modules', 'value' => $api_modules));
}

// DDP (only if enabled for whole system AND this project)
if (is_object($DDP) && (
	(DynamicDataPull::isEnabledInSystem() && DynamicDataPull::isEnabled($project_id)) ||
	(DynamicDataPull::isEnabledInSystemFhir() && DynamicDataPull::isEnabledFhir($project_id))
	)
) {
	$user_rights_super_users_only = DynamicDataPull::isEnabledFhir($project_id) ? $fhir_user_rights_super_users_only : $realtime_webservice_user_rights_super_users_only;
	?>
	<tr>
		<td valign="top" style="padding-top:8px;">
			<div style="margin-left:1.4em;text-indent:-1.4em;line-height: 13px;">
                <i class="fas fa-database" style="text-indent: 0;"></i>&nbsp;&nbsp;<?php echo ($DDP->isEnabledFhir($project_id) ? $lang['ws_210'] : $lang['ws_51']) . " " . $DDP->getSourceSystemName() ?>
			</div>
		</td>
		<td valign="top" style="padding-top:8px;">
			<div style="margin-left:1.4em;text-indent:-1.4em;">
				<!-- Mapping rights -->
				<input type="checkbox" name="realtime_webservice_mapping" <?php if ($realtime_webservice_mapping == 1) echo 'checked' ?>
					<?php if (!$super_user && $user_rights_super_users_only) echo 'disabled'; ?>>
				<?php if (!$super_user && $user_rights_super_users_only) { ?>
					<input type="hidden" name="realtime_webservice_mapping" value="<?php echo $realtime_webservice_mapping ?>">
				<?php } ?>
				<?php echo $lang['ws_19'] ?>
			</div>
			<div style="margin-left:1.4em;text-indent:-1.4em;">
				<!-- Adjudication rights -->
				<input type="checkbox" name="realtime_webservice_adjudicate" <?php if ($realtime_webservice_adjudicate == 1) echo 'checked' ?>
					<?php if (!$super_user && $user_rights_super_users_only) echo 'disabled'; ?>>
				<?php if (!$super_user && $user_rights_super_users_only) { ?>
					<input type="hidden" name="realtime_webservice_adjudicate" value="<?php echo $realtime_webservice_adjudicate ?>">
				<?php } ?>
				<?php echo $lang['ws_20'] ?>
			</div>
		</td>
	</tr>
	<tr>
		<td valign="top" colspan="2" style="padding:0 0 10px 24px;color:#A00000;font-size:10px;">
			<div style="line-height:8px;<?php if ($realtime_webservice_user_rights_super_users_only) { ?>float:left;margin-right:40px;<?php } ?>">
				<a style='text-decoration:underline;font-size:10px;' href='javascript:;' onclick="simpleDialog(null,null,'explainDDP'); return false;"><?php echo ($DDP->isEnabledFhir($project_id) ? $lang['ws_290'] : $lang['ws_36']) ?></a>
			</div>
			<?php if ($realtime_webservice_user_rights_super_users_only) { ?>
				<div style="float:left;">
					<?php echo $lang['rights_134'] ?>
				</div>
			<?php } ?>
			<div class="clear"></div>
		</td>
	</tr>
	<?php
} else {
	// Hide input fields to maintain values if setting is disabled at project level
	?>
	<input type="hidden" name="realtime_webservice_mapping" value="<?php echo $realtime_webservice_mapping ?>">
	<input type="hidden" name="realtime_webservice_adjudicate" value="<?php echo $realtime_webservice_adjudicate ?>">
	<?php
}

// DTS (only if enabled for whole system AND this project) - do NOT allow this for ROLES
if (!$isRole && $dts_enabled_global && $dts_enabled)
{
	?>
	<tr>
		<td valign="top">
			<div style="margin-left:1.4em;text-indent:-1.4em;">
                <i class="fas fa-database" style="text-indent: 0;"></i>&nbsp;&nbsp;<?php echo $lang["rights_132"] ?>
			</div>
		</td>
		<td valign="top" style="padding-top:2px;">
			<?php if ($super_user) { ?>
				<div style="margin-left:1.4em;text-indent:-1.4em;">
					<input type="checkbox" name="dts" <?php if ($dts == 1) echo 'checked' ?>>
					<span style="color:#A00000;font-size:10px;"><?php echo $lang['rights_134'] ?></span>
				</div>
			<?php } else { ?>
				<div style="margin-left:1.4em;text-indent:-1.4em;">
					<input type="checkbox" <?php if ($dts == 1) echo 'checked' ?> disabled="disabled">
					<input type="hidden" name="dts" value="<?php echo $dts ?>">
					<span style="color:#A00000;font-size:10px;"><?php echo $lang['rights_134'] ?></span>
				</div>
			<?php } ?>
		</td>
	</tr>
	<?php
}

// Mobile App
if ($mobile_app_enabled) {
	$appHelp = RCView::a(array('id' => 'apiHelpLinkId', 'href' => 'javascript:;', 'onclick'=>"simpleDialog(null,null,'appHelpDialogId',600);", 'style' => 'font-size:11px;text-decoration:underline;'), $lang['rights_308']);
	print  "<tr>
				<td valign='top' colspan='2' style='border-top:1px solid #00000030;padding:6px 0 10px;color:#A00000;font-size:11px;'>
					{$lang['rights_309']}
				</td>
			</tr>
			<tr>
				<td valign='top'>
					<i class=\"fas fa-tablet-alt\"></i>&nbsp;&nbsp;{$lang['global_118']}
					<div style='padding:0px 0 0px 18px;font-size:11px;color:#777;'>$appHelp</div>
				</td>
				<td valign='top' style='padding-top:2px;'>
					<input type='checkbox' name='mobile_app' style='float:left;' onclick=\"if ($(this).prop('checked')) simpleDialog(null,null,'mobileAppEnableConfirm',600,function(){
						$('#user_rights_form input[name=mobile_app]').prop('checked', false);
					},'".js_escape($lang['global_53'])."',function(){
						$('#user_rights_form input[name=mobile_app]').prop('checked', true);
					},'".js_escape($lang['rights_305'])."');\" ".($mobile_app == 1 ? 'checked' : '').">
					<div style='width: 100px;padding: 1px 0 0 8px;float:left;line-height:12px;font-size:11px;color:#777;'>
						{$lang['rights_307']}
					</div>
				</td>
			</tr>
			<tr>
				<td valign='top' style='line-height: 11px;font-size:11px;padding:10px 3px 10px 22px;'>
					{$lang['rights_306']}
				</td>
				<td valign='top' style='padding-top:12px;'>
					<div style='margin-left:1.4em;text-indent:-1.4em;'> <input type='checkbox' name='mobile_app_download_data' "; if ($mobile_app_download_data == '1'){ print "checked"; } print "></div>
				</td>
			</tr>";
}
else {
	print RCView::hidden(array('name' => 'mobile_app', 'value' => $mobile_app));
}

// Create/Rename/Delete Records
print  "<tr>
			<td valign='top' colspan='2' style='border-top:1px solid #00000030;padding:6px 0 10px;color:#A00000;font-size:11px;'>
				{$lang['rights_119']}
				&nbsp;&nbsp;
				<a style='text-decoration:underline;' href='javascript:;' onclick='userRightsRecordsExplain(); return false;'>{$lang['rights_123']}</a>
			</td>
		</tr>
		<tr>
			<td valign='top'>
				<i class=\"fas fa-plus-square\"></i>&nbsp;&nbsp;{$lang['rights_99']}
			</td>
			<td valign='top' style='padding-top:2px;'>
				<input type='checkbox' name='record_create' " . ($record_create == 1 ? "checked" : "") . ">
			</td>
		</tr>
		<tr>
			<td valign='top'>
				<i class=\"fas fa-exchange-alt\"></i>&nbsp;{$lang['rights_100']}
			</td>
			<td valign='top' style='padding-top:2px;'>
				<input type='checkbox' name='record_rename' " . ($record_rename == 1 ? "checked" : "") . ">
			</td>
		</tr>
		<tr>
			<td valign='top' style='padding:2px 0 4px;'>
				<i class=\"fas fa-minus-square\"></i>&nbsp;&nbsp;".RCView::tt("rights_101")."<span class=\"text-muted\">*</span>
			</td>
			<td valign='top' style='padding:2px 0 4px;'>
				<input type='checkbox' name='record_delete' " . ($record_delete == 1 ? "checked" : "") . ">
			</td>
		</tr>
		<tr>
		    <td colspan='2' style='padding:0 15px 10px;color:#888;font-size:11px;line-height:1;'>
		        <div style='margin-left:10px;text-indent:-10px;'>".RCView::tt("rights_453")."</div>
            </td>
		</tr>
</tr>";

// Lock & E-sign Record
$displayEsignOption = ($GLOBALS['esignature_enabled_global'] == '1'); // Default for roles and users
if (isset($user) && $user != null) {
    // For users, check if this individual user will be able to e-sign based on authentication method, 2FA settings, etc.
    list ($canEsignWithPassword, $canEsignWithPIN) = User::canEsignWithPasswordOr2faPin($user);
    $displayEsignOption = ($canEsignWithPassword || $canEsignWithPIN);
}
print  "<tr>
			<td valign='top' colspan='2' style='border-top:1px solid #00000030;padding:6px 0 10px;color:#A00000;font-size:11px;'>
				{$lang['rights_130']}
			</td>
		</tr>
		<tr>
			<td valign='top'>
				<div style='margin-left:1.4em;text-indent:-1.4em;'>
				    <i class=\"fas fa-lock\" style='text-indent:0;'></i>&nbsp;&nbsp;{$lang['app_11']}
				</div>
			</td>
			<td valign='top' style='padding-top:6px;'>
				<input type='checkbox' name='lock_record_customize' "; if ($lock_record_customize == 1){print "checked";} print ">
			</td>
		</tr>
		<tr>
			<td valign='top'>
				<div style='margin-left:1.4em;text-indent:-1.4em;'><i class=\"fas fa-unlock-alt\" style='text-indent:0;'></i>&nbsp;&nbsp;{$lang['rights_97']} {$lang['rights_371']}</div>
				<div style='line-height:12px;padding:4px 0 4px 22px;font-size:11px;color:#777;'>
					{$lang['rights_113']}
					<div style='padding:7px 0 4px;'>
						<i class='fas fa-film'></i>
						<a onclick=\"popupvid('locking02.mp4')\" style='color:#3E72A8;font-size:11px;' href='javascript:;'>{$lang['rights_131']}</a>
					</div>
				</div>
			</td>
			<td valign='top' style='padding-top:2px;'>
				<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='lock_record' value='0' " . ($lock_record == '0' ? "checked" : "") . " onclick=\"document.user_rights_form.lock_record_multiform.checked=false;\"> {$lang['global_23']}</div>
				<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='lock_record' value='1' " . (($lock_record == '1' || ($lock_record == '2' && !$displayEsignOption)) ? "checked" : "") . "> {$lang['rights_115']}</div>
				".
                (!$displayEsignOption ? "" : "
                    <div style='line-height:13px;margin-left:1.4em;text-indent:-1.4em;'>
                        <input type='radio' name='lock_record' value='2' " . ($lock_record == '2' ? "checked" : "") . " onclick=\"
                            if (this.checked) {
                                setTimeout(function(){
                                    simpleDialog('" . js_escape($lang['rights_375']) . "','" . js_escape($lang['global_03']) . "');
                                },50);
                            }
                        \"> {$lang['rights_116']}<br>
                        <a style='text-decoration:underline;font-size:10px;' href='javascript:;' onclick='esignExplainLink(); return false;'>{$lang['rights_117']}</a>
                    </div>"
                )
				."
			</td>
		</tr>
		<tr>
			<td valign='top'>
				<div style='margin-left:1.4em;text-indent:-1.4em;'><i class=\"fas fa-unlock-alt\" style='text-indent:0;'></i>&nbsp;&nbsp;{$lang['rights_370']}</div>
			</td>
			<td valign='top' style='padding-top:2px;'>
				<div style='margin-left:1.4em;text-indent:-1.4em;margin-top:4px;'> <input type='checkbox' name='lock_record_multiform' "; if ($lock_record_multiform == '1'){ print "checked"; } print "></div>
			</td>
		</tr>
		<tr>
			<td colspan='2' valign='top' class='py-2 ps-2 pe-0 fs11' style='color:#A00000;line-height:1.1;'>
				{$lang['rights_372']}
			</td>
		</tr>";

print "</td>
	</tr>";

print  "</table>";

print "</td><td valign='top' style='width:700px;padding-left:15px;'>";





// Show all FORMS for setting rights level for each
?>
<div class='card' style='border-color:#00000060;'>
	<div class='card-header font-weight-bold fs14' style='background-color:#00000017;'>
		<?=RCView::tt("data_export_tool_291")?>
	</div>
	<div class='card-body p-0' style='background-color:#00000007;'>
		<table id='form_rights' style='width:100%;font-size:12px;color:#A00000;'>
			<tr>
				<td colspan='3' style='padding:12px 12px 15px;line-height:1.1;color:#777;font-size:11px;'>
					<?=RCView::tt("rights_429")?>
				</td>
			</tr>
			<tr>
				<td valign='top' style='border-right:1px solid #FFA3A3;'>&nbsp;</td>
				<td valign='top' class='fs13 pb-2 text-center font-weight-bold' style='border-right:1px solid #FFA3A3;color:#000;'>
					<?=RCView::tt("rights_373")?>
				</td>
				<td valign='top' class='fs13 pb-2 text-center font-weight-bold' style='color:#B00000;'>
					<?=RCView::tt("rights_428")?>
				</td>
			</tr>	
			<tr>
				<td valign='top' style='border-right:1px solid #FFA3A3;'>&nbsp;</td>
				<td valign='top' style='border-right:1px solid #FFA3A3;text-align:left;width:285px;color:#000;font-size:11px;' data-bs-toggle='tooltip' data-bs-trigger='hover' data-bs-placement='top' data-bs-offset='0,35' title="<?=RCView::tt_js2("rights_450")?> <?=RCView::tt_js2("rights_451")?>">
					<div style='float:left;padding:2px 8px;white-space:normal;width:62px;line-height: 12px;cursor:pointer;' data-rc-dvr-set-all="no-access">
						<?=RCView::tt("rights_47")?><br><?=RCView::tt("rights_395")?>
					</div>
					<div style='float:left;padding:2px 8px;white-space:normal;width:44px;line-height: 12px;cursor:pointer;' data-rc-dvr-set-all="read-only">
						<?=RCView::tt("rights_61")?>
					</div>
					<div style='float:left;padding:2px 8px;white-space:normal;width:50px;line-height: 12px;cursor:pointer;' data-rc-dvr-set-all="view-edit">
						<?=RCView::tt("rights_138")?>
					</div>
					<div style='float:left;padding:2px 8px;white-space:normal;width:50px;line-height: 12px;cursor:pointer;vertical-align:bottom;' data-rc-dvr-set-all="delete" data-bs-toggle='tooltip' data-bs-trigger='hover click' data-bs-placement='bottom' data-bs-html="true" title="">
						<?=RCView::tt("global_19")?>
					</div>
					<?php if ($enable_edit_survey_response && !empty($Proj->surveys)): ?>
					<div style='float:left;padding:2px 8px;white-space:normal;width:75px;line-height: 12px;cursor:pointer;' data-rc-dvr-set-all="editresp">
						<?=RCView::tt("rights_449")?>
					</div>
					<?php endif; ?>
				</td>
				<td valign='top' style='text-align:left;width:250px;color:#B00000;font-size:11px;' data-bs-toggle='tooltip' data-bs-trigger='hover' data-bs-placement='top' data-bs-offset='0,35' title="<?=RCView::tt_js2("rights_450")?>">
					<div style='float:left;padding:2px 8px;white-space:normal;width:58px;line-height: 12px;cursor:pointer;'  data-rc-der-set-all="0">
						<?=RCView::tt("rights_47")?>
					</div>
					<div style='float:left;padding:2px 8px;white-space:normal;width:72px;line-height: 12px;cursor:pointer;' data-rc-der-set-all="2">
						<?=RCView::tt("rights_48")?>*
					</div>
					<div style='float:left;padding:2px 8px;white-space:normal;width:65px;line-height: 12px;cursor:pointer;' data-rc-der-set-all="3">
						<?=RCView::tt("data_export_tool_290")?>
					</div>
					<div style='float:left;padding:2px 8px;white-space:normal;width:45px;line-height: 12px;cursor:pointer;' data-rc-der-set-all="1">
						<?=RCView::tt("rights_49")?>
					</div>
				</td>
			</tr>
<?php
// Loop through all forms
foreach ($Proj->forms as $form_name=>$form_attr)
{
	// If editing a user that does not have any form-level rights (because it didn't get added automatically), then set default to full for each form
	if (!isset($this_user["form-".$form_name])) {
		// If in production, all users get "no access" by default for security purposes
        if ($Proj->project['status'] > 0) {
			$this_user["form-" . $form_name] = "0";
		} else {
			$this_user["form-" . $form_name] = ($enable_edit_survey_response && isset($form_attr['survey_id']) ? "3" : "1");
		}
	}
	if (!isset($this_user["export-form-".$form_name]) || $new_user) {
		// If in production, all users get "no access" by default for security purposes
        $newFormExportRight = $Proj->project['status'] > 0 ? "0" : "1";
        $this_user["export-form-" . $form_name] = $newFormExportRight;
        // If the user is somehow missing this form's rights (maybe because the form was recently created),
        // manually set the user's form-level rights for this form in the user_rights db table.
        if (!$new_user) {
            if (!$isRole) {
                $sql = "update redcap_user_rights set data_export_instruments = concat(data_export_instruments,'[$form_name,$newFormExportRight]')
                        where project_id = ".PROJECT_ID." and username = '" . db_escape($user) . "'";
            } else {
                $sql = "update redcap_user_roles set data_export_instruments = concat(data_export_instruments,'[$form_name,$newFormExportRight]')
                        where role_id = ".$role_id;
            }
            db_query($sql);
        }
	}
	// Add row
	?>
			<tr>
				<td class='derights1' style='line-height:1.1;padding-left:6px;'>
					<?=RCView::escape($form_attr['menu'])?>
					<?php if (isset($form_attr['survey_id'])): ?>
					<span class='text-secondary fs10 ms-2'>(<?=RCView::tt("global_59")?>)</span>
					<?php endif; ?>
				</td>
				<?php // Data Viewing Rights ?>
				<td valign='middle' class='nobr derights1 derights2'>
					<input type='radio' style='margin-left:15px;' name='form-<?=$form_name?>' value='no-access' <?=UserRights::hasDataViewingRights($this_user["form-".$form_name], "no-access") ? "checked" : ""?>>
					<input type='radio' style='margin-left:45px;' name='form-<?=$form_name?>' value='read-only' <?=UserRights::hasDataViewingRights($this_user["form-".$form_name], "read-only") ? "checked" : ""?>>
					<input type='radio' style='margin-left:35px;' name='form-<?=$form_name?>' value='view-edit' <?=(UserRights::hasDataViewingRights($this_user["form-".$form_name], "view-edit") || $new_user) ? "checked" : ""?>>
					<input type='checkbox' style='margin-left:33px;' id='form-delete-<?=$form_name?>' name='form-delete-<?=$form_name?>' <?=UserRights::hasDataViewingRights($this_user["form-".$form_name], "delete") ? "checked" : ""?>>
					<?php if ($enable_edit_survey_response && isset($form_attr['survey_id'])): ?>
					<input type='checkbox' style='margin-left:40px;' id='form-editresp-<?=$form_name?>' name='form-editresp-<?=$form_name?>' <?=UserRights::hasDataViewingRights($this_user["form-".$form_name], "editresp") ? "checked" : ""?>>
					<?php endif; ?>
				</td>
				<?php // Data Export Rights ?>
				<td valign='middle' class='nobr derights2'>
					<input type='radio' style='margin-left:10px;' name='export-form-<?=$form_name?>' value='0' <?=($this_user["export-form-".$form_name] == "0") ? "checked" : ""?>>
					<input type='radio' style='margin-left:50px;' name='export-form-<?=$form_name?>' value='2' <?=($this_user["export-form-".$form_name] == "2") ? "checked" : ""?>>
					<input type='radio' style='margin-left:60px;' name='export-form-<?=$form_name?>' value='3' <?=($this_user["export-form-".$form_name] == "3") ? "checked" : ""?>>
					<input type='radio' style='margin-left:40px;' name='export-form-<?=$form_name?>' value='1' <?=($this_user["export-form-".$form_name] == "1") ? "checked" : ""?>>
				</td>
			</tr>
<?php
}
?>
		</table>
	</div>
</div>
<div style='line-height:12px;padding:20px 10px 10px 15px;text-indent:-8px;font-size:11px;color:#999;'>
	* <?=RCView::tt("data_export_tool_181")?>
</div>
<?php
print $assign_to_group_html;

// External Modules: Display checkbox for each enabled module in a project in the Edit User dialog on the User Rights page
$modules = UserRights::getExternalModulesUserRightsCheckboxes();
if (!empty($modules)) 
{
	// Loop through modules to build checkbox HTML
	$moduleCheckboxes = $moduleAsteriskText = "";
	$countModsNotReqConfig = $countModsNoConfig = 0;
	$this_external_module_config = json_decode($this_user['external_module_config'], true);
	if (!is_array($this_external_module_config)) $this_external_module_config = array();
	foreach ($modules as $module_prefix=>$attr) {
		$hasReqConfigRightsSaved = in_array($module_prefix, $this_external_module_config);
		$reqConfigRights = ($attr['require-config-perm'] == '1');
		$hasProjectConfig = ($attr['has-project-config'] == '1');
		$checked = ($hasProjectConfig && ((!$reqConfigRights && $design) || ($reqConfigRights && $hasReqConfigRightsSaved) || (SUPER_USER && !$isRole && USERID == $user))) ? "checked" : "";
		$disabled = (!SUPER_USER || !$reqConfigRights || !$hasProjectConfig) ? "disabled" : "";
		$reqConfigClass = $reqConfigRights ? "" : "no-require-perm";
		$disabledAsterisk = $reqConfigRights ? "" : "<span class='em-ast'>*</span>";
		$noConfigAsterisk = $hasProjectConfig ? "" : "<span class='em-ast'>**</span>";
		if (!$hasProjectConfig) {
			$reqConfigClass = $disabledAsterisk = "";
			$countModsNoConfig++;
		}
		if ($disabledAsterisk != "") $countModsNotReqConfig++;
		$moduleCheckboxes .= "<div class='ext_mod_user_right_item'>
								<input type='checkbox' class='$reqConfigClass' name='external_module_config[]' value='$module_prefix' $checked $disabled style='top:2px;position:relative;'>
								{$attr['name']}{$disabledAsterisk}{$noConfigAsterisk}
							  </div>";
	}
	// Display asterisk text
	if ($countModsNotReqConfig > 0) {
		$moduleAsteriskText .= "<div style='margin-top:10px;color:#c20808;font-size:10px;line-height:11px;'>".$lang['rights_327']."</div>";
	}
	if ($countModsNoConfig > 0) {
		$asteriskMargin = ($countModsNotReqConfig > 0) ? "2" : "10";
		$moduleAsteriskText .= "<div style='margin-top:{$asteriskMargin}px;color:#c20808;font-size:10px;line-height:11px;'>".$lang['rights_329']."</div>";
	}
	// Output box of modules as HTML
	print  "<div style='margin:20px 0 0;position: relative;top:6px;z-index:106;color:rgb(76, 92, 146);width:310px;font-weight:bold;font-size:11px;text-align:center;background:#eee;padding:2px;border:1px solid #8495d0;border-bottom-width: 0px;'>
				{$lang['rights_326']}
			</div>
			<div style='position: relative;border:1px solid #8495d0;background:#eee;padding:10px 14px;'>
				<div style='font-size:11px;line-height:12px;margin-bottom:5px;'>{$lang['rights_328']}</div>
				$moduleCheckboxes
				$moduleAsteriskText
			</div>";
}

print $chbx_email_newuser;

?>
				</td>
			</tr>
		</table>
	</div>
	<input type='hidden' name='user' value='<?=js_escape($isRole ? $role_id : htmlspecialchars($user, ENT_QUOTES))?>'>
	<input type='hidden' name='role_name' value='<?=RCView::escape($role_name)?>'>
</form>
<script>
	lang.rights_452 = <?=json_encode($lang['rights_452'])?>;
	setupDataViewingExportRightsBehaviors();
	bootstrap.Tooltip.Default.allowList.kbd = [];
	$('#editUserPopup [data-bs-toggle="tooltip"]').tooltip({ html: true });
</script>
