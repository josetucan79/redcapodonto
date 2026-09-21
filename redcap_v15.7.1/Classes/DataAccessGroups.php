<?php

class DataAccessGroups
{
	// Get array of users in current user's DAG, if in a DAG
	public static function getDagUsers($project_id, $group_id)
	{
		$dag_users_array = array();
		if ($group_id != "") {
			$sql = "select u.username from redcap_data_access_groups g, redcap_user_rights u where g.group_id = $group_id
                    and g.group_id = u.group_id and u.project_id = g.project_id and g.project_id = $project_id";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$dag_users_array[] = $row['username'];
			}
		}
		return $dag_users_array;
	}


	// Render main DAG page
	public static function renderPage()
	{
		extract($GLOBALS);

		// Detect if using Randomization with DAG as a strata
		// If so, then disable deleting of DAGs
		$randomizationDagStrata = '0';
		if ($randomization && Randomization::setupStatus()) {
			$randomizationDagStrata = (Randomization::randomizeByDAG()) ? '1' : '0';
		}

		include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
		include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";

		print "<div style='text-align:right;max-width:900px;'>".
			RCView::ConsortiumVideoLink(RCView::tt("data_access_groups_07"), "data_access_groups02.mp4", $lang["global_22"],"7:20")."</div>";

		print  "<p style='max-width:900px;'>{$lang['data_access_groups_01']} {$lang['data_access_groups_21']}
                <a href='javascript:;' style='text-decoration: underline;' onclick=\"$('#dag-instructions').show();$(this).remove();\">{$lang['data_export_tool_08b']}</a>
                </p>";

		//Data Access Groups (only show to users that are NOT in a group)
		if ($user_rights['group_id'] == "") {
			print  "<div id='dag-instructions' style='display:none;'><p style='max-width:900px;'>{$lang['data_access_groups_02']} {$lang['data_access_groups_ajax_40']}</p>
            <p style='max-width:900px;'>{$lang['data_access_groups_22']}</p></div>
			<div id='group_table'>";
			DataAccessGroups::ajax();
			print  "</div>";
		} else {
			//User does not have permission to be here because user is in a data access group.
			print  "<div class='red' style='margin-top:30px;'>
				<b>{$lang['data_access_groups_03']}</b><br>{$lang['data_access_groups_04']}
			</div>";
			include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
			exit;
		}

        // Determine how big the DAG Switcher table will be
        $maxNumUsersXDags = 5000;
        $numUsers = count(User::getProjectUsernames());
        $numDags = count($Proj->getGroups());
        $displayDSonPageLoad = ($numUsers * $numDags < $maxNumUsersXDags) ? 1 : 0;

		// DAG Switcher table
		$dagSwitcher = new DAGSwitcher();
		print "<div id='dag-switcher-config-container-parent'>" . $dagSwitcher->renderDAGPageTableContainer() . "</div>";


		// JavaScript
		loadJS('DataAccessGroups.js');
		addLangToJS(array('data_access_groups_ajax_38', 'rights_179', 'data_access_groups_ajax_17', 'rights_319', 'rights_318', 'rights_184', 'questionmark', 'rights_185', 'global_53', 'global_19'));
		?>
		<script type="text/javascript">
            var randomizationDagStrata = '<?=$randomizationDagStrata?>';
            var displayDSonPageLoad = <?=$displayDSonPageLoad?>;
		</script>
		<?php

        Design::alertRecentImportStatus();

		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

	}


	// DAG ajax requests on main DAG page
	public static function ajax()
	{
		extract($GLOBALS);

		if ($user_rights['group_id'] != "") exit("ERROR!");

		//If action is provided in AJAX request, perform action.
		if (isset($_GET['action'])) {
			switch ($_GET['action']) {
				case "delete":
					//Before deleting, make sure no users are in the group. If there are, don't delete.
					if (!is_numeric($_POST['item'])) exit('ERROR!');
					$_POST['item'] = (int)$_POST['item'];
					$gcount = db_result(db_query("select count(1) from redcap_user_rights where project_id = $project_id and group_id = {$_POST['item']}"), 0);

					$sql2 = "select count(1) from redcap_user_roles where project_id = $project_id and group_id = " . $_POST['item'];
					$query2 = db_query($sql2);
					$gcount2 = $query2 !== false ? db_result($query2, 0) : 0;

                    // Check for any non-deleted folders in the File Repository that are restricted to this DAG
                    $gcount3 = db_result(db_query("select count(1) from redcap_docs_folders where project_id = $project_id and dag_id = {$_POST['item']} and deleted = 0"), 0);

					// Are there any records in this project?
					$recordsInDag = Records::getRecordList($project_id, $_POST['item']);
					$numRecordsInDag = count($recordsInDag);

					if ($numRecordsInDag == 0 && $gcount + $gcount2 + $gcount3 == 0) {
						// Get group name
						$group_name = $Proj->getGroups($_POST['item']);
                        // Disassociate any File Repository folders with the DAG first
                        $sql = "update redcap_docs_folders set dag_id = null where project_id = $project_id and dag_id = " . $_POST['item'];
                        $q = db_query($sql);
						// Delete from DAG table
						$sql = "delete from redcap_data_access_groups where project_id = $project_id and group_id = " . $_POST['item'];
						$q = db_query($sql);
						// Also delete any instances of records being attributed to the DAG in the data table
						$sql2 = "delete from ".\Records::getDataTable($project_id)." where project_id = $project_id and field_name = '__GROUPID__'
						and value = '" . db_escape($_POST['item']) . "'";
						$q = db_query($sql2);
						// Logging
						if ($q) Logging::logEvent("$sql;\n$sql2", "redcap_data_access_groups", "MANAGE", $_POST['item'], "group_id = " . $_POST['item'], "Delete data access group");
						print  "<div class='red dagMsg hidden' style='max-width:700px;text-align:center;'>
						<img src='" . APP_PATH_IMAGES . "cross.png'>
						{$lang['global_78']} \"<b>$group_name</b>\" {$lang['data_access_groups_ajax_28']}
						</div>";
					} elseif ($numRecordsInDag > 0) {
						// Can't delete DAG because it has records
						print  "<div class='red dagMsg hidden' style='max-width:700px;'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png'> <b>{$lang['global_01']}{$lang['colon']}</b><br>
						{$lang['data_access_groups_ajax_43']}
						</div>";
					} elseif ($gcount3 > 0) {
						// Can't delete DAG because it has records
						print  "<div class='red dagMsg hidden' style='max-width:700px;'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png'> <b>{$lang['global_01']}{$lang['colon']}</b><br>
						{$lang['data_access_groups_ajax_55']}
						</div>";
					} else {
						// Can't delete DAG because it has users
						print  "<div class='red dagMsg hidden' style='max-width:700px;'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png'> <b>{$lang['global_01']}{$lang['colon']}</b><br>
						{$lang['data_access_groups_ajax_35']}
						</div>";
					}
					## What happens to the associated records that belong to a group that is deleted?
					break;
				case "add":
					$new_group_name = strip_tags(html_entity_decode(trim($_POST['item']), ENT_QUOTES));
					if ($new_group_name != "") {
						$sql = "insert into redcap_data_access_groups (project_id, group_name) values ($project_id, '" . db_escape($new_group_name) . "')";
						$q = db_query($sql);
						// Logging
						if ($q) {
							$dag_id = db_insert_id();
							Logging::logEvent($sql, "redcap_data_access_groups", "MANAGE", $dag_id, "group_id = $dag_id", "Create data access group");
						}
						print  "<div class='darkgreen dagMsg hidden' style='max-width:700px;text-align:center;'>
						<img src='" . APP_PATH_IMAGES . "tick.png'>
						{$lang['global_78']} \"<b>$new_group_name</b>\" {$lang['data_access_groups_ajax_29']}</div>";
					}
					break;
				case "rename":
					$group_id = substr($_POST['group_id'], 4);
					if (!is_numeric($group_id)) exit('ERROR!');
					$new_group_name = trim(strip_tags(rawurldecode(urldecode($_POST['item']))));
					if ($new_group_name != "") {
						$sql = "update redcap_data_access_groups set group_name = '" . db_escape($new_group_name) . "' where project_id = $project_id and group_id = $group_id";
						$q = db_query($sql);
						// Logging
						if ($q) Logging::logEvent($sql, "redcap_data_access_groups", "MANAGE", $group_id, "group_id = " . $group_id, "Rename data access group");
					}
					exit($new_group_name);
					break;
				case "add_user":
					if (!is_numeric($_POST['group_id']) && $_POST['group_id'] != '') exit('ERROR!');
					if ($_POST['group_id'] == "") {
						$assigned_msg = $lang['data_access_groups_ajax_31'];
						$_POST['group_id'] = "NULL";
						$logging_msg = "Remove user from data access group";
						// Get group name for user BEFORE we unassign them
						$this_user_rights = UserRights::getPrivileges($project_id, $_POST['user']);
						$this_user_rights = $this_user_rights[$project_id][strtolower($_POST['user'])];
						$group_name = $Proj->getGroups($this_user_rights['group_id']);
					} else {
						// Get group name
						$group_name = $Proj->getGroups($_POST['group_id']);
						$assigned_msg = "{$lang['data_access_groups_ajax_30']} \"<b>$group_name</b>\"{$lang['exclamationpoint']}";
						$logging_msg = "Assign user to data access group";
					}
					$sql = "update redcap_user_rights set group_id = {$_POST['group_id']} where username = '" . db_escape($_POST['user']) . "' and project_id = $project_id";
					$q = db_query($sql);
					// Logging
					$group_names = gettype($group_name) == 'array' ? implode(',', $group_name) : $group_name;
					if ($q) Logging::logEvent($sql, "redcap_user_rights", "MANAGE", $_POST['user'], "user = '{$_POST['user']}',\ngroup = '" . $group_names . "'", $logging_msg);
					print  "<div class='darkgreen dagMsg hidden'  style='max-width:700px;text-align:center;'>
					<img src='" . APP_PATH_IMAGES . "tick.png'>
					{$lang['global_17']} \"<b>" . remBr(RCView::escape($_POST['user'])) . "</b>\" $assigned_msg
					</div>";
					// If flag is set to display the User Rights table, then return its html and stop here
					if (isset($_GET['return_user_rights_table']) && $_GET['return_user_rights_table']) {
						exit(UserRights::renderUserRightsRolesTable());
					}
					break;
				case "select_group":
					$group_id = db_result(db_query("select group_id from redcap_user_rights where username = '" . db_escape($_GET['user']) . "' and project_id = $project_id"), 0);
					exit($group_id);
					break;
				case "select_role":
					$group_id = db_result(db_query("select group_id from redcap_user_roles where role_id = '" . db_escape($_GET['role_id']) . "' and project_id = $project_id"), 0);
					exit($group_id);
					break;
			}

		}

		// Reset groups in case were just modified above
		$Proj->resetGroups();

		// Render groups table and options to designated users/roles to groups
		print self::renderDataAccessGroupsTable();
	}



	/**
	 * RENDER DATA ACCESS GROUPS TABLE
	 * Return html for table to be displayed
	 */
	public static function renderDataAccessGroupsTable()
	{
		global  $lang, $Proj;

		// Add DAGs to array
		$groups = $Proj->getGroups();

		## DAG RECORD COUNT
		// Determine which records are in which group
		$recordsInDags = Records::getRecordCountAllDags(PROJECT_ID);

		// Get array of project users
		$projectUsers = User::getProjectUsernames(array(), true);

		// Get array of group users with first/last names appended
		$groupUsers = array();
		foreach ($Proj->getGroupUsers(null, true) as $this_group_id=>$these_users) {
			foreach ($these_users as $this_user) {
				// Put username+first/last in individual's group
				$groupUsers[$this_group_id][] = RCView::escape($projectUsers[$this_user]);
			}
		}

		// Now remove current user from $projectUsers so they don't get added to the Select User drop-down
		unset($projectUsers[USERID]);

        $csrf_token = System::getCsrfToken();

        // Import/Export buttons divs
        $buttons = RCView::div(array('style'=>'text-align:right; font-size:12px;font-weight:normal;max-width:900px; '),
                RCView::button(array('onclick'=>"showBtnDropdownList(this,event,'downloadUploadUsersDagsDropdownDiv');", 'class'=>'btn btn-xs fs13 btn-defaultrc'),
                    RCView::img(array('src'=>'xls.gif')) .
                    $lang['data_access_groups_26'] .
                    RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:2px;vertical-align:middle;position:relative;top:-1px;'))
                ) .
                // Button/drop-down options (initially hidden)
                RCView::div(array('id'=>'downloadUploadUsersDagsDropdownDiv', 'style'=>'text-align:left;display:none;position:absolute;'),
                    RCView::ul(array('id'=>'downloadUploadUsersDagsDropdown'),
                        RCView::li(array(),
                            RCView::a(array('href'=>'javascript:;', 'style'=>'color:#8A5502;', 'onclick'=>"simpleDialog(null,null,'importDAGsDialog',500,null,'".js_escape($lang['calendar_popup_01'])."',\"$('#importDAGForm').submit();\",'".js_escape($lang['design_530'])."');$('.ui-dialog-buttonpane button:eq(1)',$('#importDAGsDialog').parent()).css('font-weight','bold');"),
                                RCView::img(array('src'=>'arrow_up_sm_orange.gif')) .
                                RCView::SP . $lang['data_access_groups_27']
                            )
                        ) .
                        RCView::li(array(),
                            RCView::a(array('href'=>'javascript:;', 'style'=>'color:#8A5502;', 'onclick'=>"window.location.href = app_path_webroot+'index.php?route=DataAccessGroupsController:downloadDag&pid='+pid;"),
                                RCView::img(array('src'=>'arrow_down_sm_orange.gif')) .
                                RCView::SP . $lang['data_access_groups_28']
                            )
                        ) .
                        RCView::li(array(),
                            RCView::a(array('href'=>'javascript:;', 'style'=>'color:#333;', 'onclick'=>"simpleDialog(null,null,'importUserDAGDialog',500,null,'".js_escape($lang['calendar_popup_01'])."',\"$('#importUserDAGForm').submit();\",'".js_escape($lang['design_530'])."');$('.ui-dialog-buttonpane button:eq(1)',$('#importDAGDialog').parent()).css('font-weight','bold');"),
                                RCView::img(array('src'=>'arrow_up_sm.gif')) .
                                RCView::SP . $lang['data_access_groups_29']
                            )
                        ) .
                        RCView::li(array(),
                            RCView::a(array('href'=>'javascript:;', 'style'=>'color:#333;', 'onclick'=>"window.location.href = app_path_webroot+'index.php?route=DataAccessGroupsController:downloadUserDag&pid='+pid;"),
                                RCView::img(array('src'=>'arrow_down_sm.png')) .
                                RCView::SP . $lang['data_access_groups_30']
                            )
                        )
                    )
                )
            );

        // Hidden import dialog divs
        $hiddenImportDialog = RCView::div(array('id'=>'importDAGsDialog', 'class'=>'simpleDialog', 'title'=>$lang['data_access_groups_27']),
            RCView::div(array(), $lang['data_access_groups_ajax_44']) .
            RCView::div(array('style'=>'margin-top:15px;margin-bottom:5px;font-weight:bold;'), $lang['data_access_groups_ajax_45']) .
            RCView::form(array('id'=>'importDAGForm', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'index.php?route=DataAccessGroupsController:uploadDag&pid=' . PROJECT_ID),
                RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
                RCView::input(array('type'=>'file', 'name'=>'file'))
            )
        );
        $hiddenImportDialog .= RCView::div(array('id'=>'importDAGsDialog2', 'class'=>'simpleDialog', 'title'=>$lang['data_access_groups_27']." - ".$lang['design_654']),
            RCView::div(array(), $lang['api_125']) .
            RCView::div(array('id'=>'dag_preview', 'style'=>'margin:15px 0'), '') .
            RCView::form(array('id'=>'importDAGForm2', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'index.php?route=DataAccessGroupsController:uploadDag&pid=' . PROJECT_ID),
                RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
                RCView::textarea(array('name'=>'csv_content', 'style'=>'display:none;'), (isset($_SESSION['csv_content']) ? htmlspecialchars($_SESSION['csv_content'], ENT_QUOTES) : ""))
            )
        );
        $hiddenImportDialog .= RCView::div(array('id'=>'importUserDAGDialog', 'class'=>'simpleDialog', 'title'=>$lang['data_access_groups_29']),
            RCView::div(array(), $lang['data_access_groups_ajax_46']) .
            RCView::div(array('class' => 'yellow', 'style' => 'width:100%; margin:15px 0 25px;'), $lang['data_access_groups_ajax_54']) .
            RCView::div(array('style'=>'margin-top:15px;margin-bottom:5px;font-weight:bold;'), $lang['data_access_groups_ajax_47']) .
            RCView::form(array('id'=>'importUserDAGForm', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'index.php?route=DataAccessGroupsController:uploadUserDag&pid=' . PROJECT_ID),
                RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
                RCView::input(array('type'=>'file', 'name'=>'file'))
            )
        );
        $hiddenImportDialog .= RCView::div(array('id'=>'importUserDAGsDialog2', 'class'=>'simpleDialog', 'title'=>$lang['data_access_groups_29']." - ".$lang['design_654']),
            RCView::div(array(), $lang['api_125']) .
            RCView::div(array('id'=>'user_dag_preview', 'style'=>'margin:15px 0'), '') .
            RCView::form(array('id'=>'importUserDAGForm2', 'enctype'=>'multipart/form-data', 'method'=>'post', 'action'=>APP_PATH_WEBROOT . 'index.php?route=DataAccessGroupsController:uploadUserDag&pid=' . PROJECT_ID),
                RCView::input(array('type'=>'hidden', 'name'=>'redcap_csrf_token', 'value'=>$csrf_token)) .
                RCView::textarea(array('name'=>'csv_content', 'style'=>'display:none;'), (isset($_SESSION['csv_content']) ? htmlspecialchars($_SESSION['csv_content'], ENT_QUOTES) : ""))
            )
        );

		// Set html before the table
		$html = RCView::div(array('style'=>'margin:20px 0;font-size:12px;font-weight:normal;padding:10px;border:1px solid #ccc;background-color:#f1eeee;max-width:900px;'),
            RCView::div(array('style'=>'color:#444;'),
                $buttons
            ) .
			// Create new DAG
			RCView::div(array('style'=>'color:#444;'),
				RCView::span(array('style'=>'font-weight:bold;font-size:13px;color:#000;margin-right:5px;'), '<i class="fas fa-plus"></i> ' .$lang['rights_182']) .
				" " .$lang['rights_183']
			) .
			RCView::div(array('style'=>'margin:8px 0 0 29px;'),
				RCView::text(array('size'=>30, 'maxlength'=>100, 'id'=>'new_group', 'class'=>'x-form-text x-form-field',
					'style'=>'color:#999;margin-left:4px;font-size:13px;padding-top:0;',
					'onclick'=>"if(this.value=='".js_escape($lang['rights_179'])."'){this.value='';this.style.color='#000';}",
					'onfocus'=>"if(this.value=='".js_escape($lang['rights_179'])."'){this.value='';this.style.color='#000';}",
					'onblur'=>"if(this.value==''){this.value='".js_escape($lang['rights_179'])."';this.style.color='#999';}",
					'onkeydown'=>"if(event.keyCode==13) add_group();",
					'value'=>$lang['rights_179']
				)) .
				// Add Group button
				RCView::button(array('id'=>'new_group_button', 'class'=>'btn btn-xs fs13 btn-rcgreen', 'onclick'=>'add_group();'), '<i class="fas fa-plus"></i> ' .$lang['rights_180']) .
				// Hidden progress img
				RCView::span(array('id'=>'progress_img', 'style'=>'visibility:hidden;'),
					RCView::img(array('src'=>'progress_circle.gif'))
				)
			) .
			// Assign user to DAG
			RCView::div(array('style'=>'color:#444;margin-top:20px;'),
				RCView::span(array('style'=>'font-weight:bold;font-size:13px;color:#000;margin-right:5px;'), '<i class="fas fa-user-tag me-1"></i>' . $lang['data_access_groups_ajax_32']) .
				" " .$lang['data_access_groups_ajax_36']." " .$lang['data_access_groups_23']
			) .
			RCView::div(array('style'=>'margin:8px 0 0 29px;'),
				$lang['data_access_groups_ajax_12'] .
				// Drop-down of users (do NOT display users that are in a role because that would be confusing since their role's DAG assignment overrides their individual DAG assignment)
				RCView::select(array('id'=>'group_users', 'onchange'=>'select_group(this.value);', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 5px 0 7px;'),
					array(''=>"-- {$lang['data_access_groups_ajax_13']} --")+$projectUsers, '') .
				$lang['data_access_groups_ajax_14'] .
				RCView::select(array('id'=>'groups', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 10px 0 6px;'),
					(array(''=>"[{$lang['data_access_groups_ajax_16']}]") + $groups), '') .
				RCView::button(array('id'=>'user_group_button', 'class'=>'btn btn-xs fs13 btn-primaryrc', 'onclick'=>"add_user_to_group();"),
					'<i class="fas fa-user-tag me-1"></i>' . $lang['rights_181']
                ) .
				// Hidden progress img
				RCView::span(array('id'=>'progress_img_user', 'style'=>'visibility:hidden;'),
					RCView::img(array('src'=>'progress_circle.gif'))
				)
			)
		);

		// Append hidden import dialogs html to html variable
		$html .= $hiddenImportDialog;

		// Set table hdrs
		$hdrs = array(
			array(270, 	RCView::div(array('class'=>'wrap','style'=>'font-size:13px;font-weight:bold;'),
				$lang['global_22'])),
			array(230, 	RCView::div(array('class'=>'wrap','style'=>'font-weight:bold;'),
				$lang['data_access_groups_ajax_08']
			)),
			array(65, RCView::div(array('class'=>'wrap','style'=>'font-size:11px;font-weight:bold;line-height:13px;'), $lang['data_access_groups_ajax_25']), 'center', 'int'),
			array(155, RCView::div(array('class'=>'wrap','style'=>'font-size:11px;line-height:13px;'),
				"{$lang['data_access_groups_ajax_18']}
				<a href='javascript:;' onclick=\"simpleDialog('".js_escape($lang['data_access_groups_ajax_19'])."','".js_escape($lang['data_access_groups_ajax_18'])."');\"><img title=\"".js_escape2($lang['form_renderer_02'])."\" src='".APP_PATH_IMAGES."help.png'></a><br>
				{$lang['define_events_66']}")),
			array(60, RCView::div(array('class'=>'wrap','style'=>'font-size:11px;line-height:13px;'), $lang['data_access_groups_ajax_41']."<a style='margin-left:2px;' href='javascript:;' onclick=\"simpleDialog('".js_escape($lang['data_access_groups_ajax_42'])."','".js_escape($lang['data_access_groups_ajax_41'])."',null,600);\"><img title=\"".js_escape2($lang['form_renderer_02'])."\" src='".APP_PATH_IMAGES."help.png'></a>"), 'center'),
			array(40, RCView::div(array('class'=>'wrap','style'=>'font-size:11px;line-height:13px;'), $lang['data_access_groups_ajax_09']), 'center')
		);

		// Loop through each group and render as row
		$rows = array();
		foreach ($groups as $group_id=>$group_name)
		{
			// Set values for row
			$rows[] = array(
				RCView::span(array('id'=>"gid_{$group_id}", 'class'=>'wrap editText', 'title'=>$lang['data_access_groups_06'], 'style'=>'cursor:pointer;cursor:hand;display:block;color:#000066;font-weight:bold;font-size:12px;'), $group_name),
				RCView::div(array('class'=>'wrap'), "<div style='line-height:1.2;'>".implode(",</div><div style='line-height:1.2;'>", isset($groupUsers[$group_id]) ? $groupUsers[$group_id] : array())."</div>"),
				(isset($recordsInDags[$group_id]) ? $recordsInDags[$group_id] : 0),
				RCView::span(array('id'=>"ugid_{$group_id}", 'class'=>'wrap', 'style'=>'color:#777;'), $Proj->getUniqueGroupNames($group_id)),
				RCView::span(array('style'=>'color:#777;'), $group_id),
				RCView::a(array('href'=>'javascript:;'),
					RCView::img(array('src'=>'cross.png', 'onclick'=>"del_msg('$group_id','".js_escape($group_name)."')"))
				)
			);
		}
		// Add last row of unassigned users
		$rows[] = array(
			RCView::span(array('style'=>'color:#800000;font-size:12px;'), $lang['data_access_groups_ajax_24']),
			RCView::div(array('class'=>'wrap'), "<div style='line-height:1.2;'>".(isset($groupUsers[0]) ? implode(",</div><div style='line-height:1.2;'>", $groupUsers[0]) : "") . (empty($groupUsers[0]) ? "" : RCView::div(array('style'=>'color:#C00000;'), $lang['data_access_groups_ajax_26']))."</div>"),
			isset($recordsInDags[0]) ? $recordsInDags[0] : '',
			"",
			"",
			""
		);

		// Return the html for displaying the table
		return $html . renderGrid("dags_table", isset($title) ? $title : '', 900, "auto", $hdrs, $rows, true, false, false);
	}

	// Update Group Name by group_id and project_id
    public static function updateGroupName($project_id, $group_name, $group_id)
    {
        $project_id = (int)$project_id;
        $group_id = (int)$group_id;
        $group_name = db_escape($group_name);

        $sql = "
			UPDATE redcap_data_access_groups
			SET	group_name = '$group_name'
			WHERE group_id = $group_id
			AND project_id = $project_id
			LIMIT 1
		";

        $q = db_query($sql);
        return ($q && $q !== false);
    }

    // Add New Group Name
    public static function addGroupName($project_id, $group_name)
    {
        $project_id = (int)$project_id;
        $group_name = db_escape($group_name);

        $sql = "
			INSERT INTO redcap_data_access_groups (
				project_id, group_name
			) VALUES (
				$project_id, '$group_name'
			)
		";

        $q = db_query($sql);
        return ($q && $q !== false);
    }

    // Update User-DAG assignment
    public static function updateUserDAGMapping($project_id, $username, $group_id)
    {
        $project_id = (int)$project_id;
		$group_id = isinteger($group_id) ? $group_id : "null";

        $sql = "
			UPDATE redcap_user_rights
			SET	group_id = $group_id
			WHERE username = '".db_escape($username)."'
			AND project_id = $project_id
			LIMIT 1
		";

        $q = db_query($sql);
        if (!$q) return false;

        # Logging
        $Proj = new Project($project_id);
		Logging::logEvent($sql, "redcap_data_access_groups_users", "MANAGE", $username, "user = '$username',\ngroup = '" . $Proj->getGroups($group_id) . "'", "Assign user to data access group");
		return true;
    }

    // Delete DAG
    public static function delGroup($project_id, $group_id)
    {
        $project_id = (int)$project_id;
        $group_id = (int)$group_id;

        $sql = "
			DELETE FROM redcap_data_access_groups
			WHERE project_id = $project_id
			AND group_id = $group_id
			LIMIT 1
		";

        $q = db_query($sql);
        if($q && $q !== false)
        {
            return db_affected_rows();
        }
        return 0;
    }

    // Add new DAGs to a given project
    // Return array with count of DAGs added/updated and array of errors, if any
    public static function uploadDAGs($project_id, $data)
    {
        global $lang;

        $count = 0;
        $errors = array();

        $Proj = new Project($project_id);
        // Check for basic attributes needed
        if (empty($data) || !isset($data[0]['unique_group_name']) || !isset($data[0]['data_access_group_name'])) {
            $msg = $errors[] = $lang['design_641'] . " data_access_group_name, unique_group_name";
            if (defined("API")) {
                die(RestUtility::sendResponse(400, $msg));
            } else {
                return array($count, $errors);
            }
        }

        foreach($data as $dag)
        {
            $group_name = $dag['data_access_group_name'];
            $unique_group_name = trim($dag['unique_group_name']);

            if ($group_name == '') {
                $errors[] = "{$lang['data_access_groups_ajax_48']} \"{$unique_group_name}\" {$lang['design_638']}";
                continue;
            }
            if (strlen($group_name) > 100) {
                $errors[] = "{$lang['data_access_groups_ajax_49']} \"{$group_name}\" {$lang['design_834']}";
                continue;
            }
            if ($unique_group_name != '' && !$Proj->uniqueGroupNameExists($unique_group_name)) {
                $errors[] = "{$lang['data_access_groups_ajax_18']} \"{$unique_group_name}\" {$lang['design_643']} {$lang['design_1113']}";
                continue;
            }

            if (empty($errors))
            {
                if ($group_name != '' && $Proj->uniqueGroupNameExists($unique_group_name))
                {
                    $groups = $Proj->getUniqueGroupNames();
                    $group_id = array_search($unique_group_name, $groups);
                    self::updateGroupName(PROJECT_ID, $group_name, $group_id);
                    ++$count;
                    continue;
                }

                self::addGroupName(PROJECT_ID, $group_name);
                ++$count;
            }
        }

        // Return count and array of errors
        return array($count, $errors);
    }

    // Update User-DAG Assignment for a given project
    // Return array with count of mappings updated and array of errors, if any
    public static function uploadUserDAGMappings($project_id, $data)
    {
        global $lang;

        $count = 0;
        $errors = array();

        $Proj = new Project($project_id);
        // Check for basic attributes needed
        if (empty($data) || !isset($data[0]['username']) || !isset($data[0]['redcap_data_access_group'])) {
            $errors[] = $lang['design_641'] . " username, redcap_data_access_group";
        } else {
            $projectUsers = UserRights::getPrivileges($project_id);

            $projectUsers = UserRights::getPrivileges(PROJECT_ID);
            if ($projectUsers[PROJECT_ID][USERID]['group_id'] != "" && !SUPER_USER) {
                $errors[] = $lang['api_184'];
            } else {
                $row_count = array();
                foreach ($data as $mapping) {
                    $username = trim($mapping['username']);
                    $unique_group_name = trim($mapping['redcap_data_access_group']);
                    if (isset($row_count[$username])) {
                        $row_count[$username]++;
                    } else {
                        $row_count[$username] = 1;
                    }

                    if ($username == '') {
                        $errors[] = "{$lang['data_access_groups_ajax_51']} \"{$unique_group_name}\" {$lang['design_638']}";
                        continue;
                    }
                    if (strlen($username) > 255) {
                        $errors[] = "{$lang['pwd_reset_25']} \"{$username}\" {$lang['design_835']}";
                        continue;
                    }
                    if ($username != '' && !isset($projectUsers[$project_id][strtolower($username)])) {
                        $errors[] = "{$lang['pwd_reset_25']} \"{$username}\" {$lang['design_643']}";
                        continue;
                    }
                    if ($row_count[$username] > 1) {
                        $errors[] = "{$lang['pwd_reset_25']} \"{$username}\" {$lang['data_access_groups_ajax_53']} ";
                        continue;
                    }
                    if ($unique_group_name != '' && !$Proj->uniqueGroupNameExists($unique_group_name)) {
                        $errors[] = "{$lang['data_access_groups_ajax_18']} \"{$unique_group_name}\" {$lang['design_643']}";
                        continue;
                    }

                    if (empty($errors)) {
                        $groups = $Proj->getUniqueGroupNames();
                        // if $unique_group_name is non-empty, No need to check if group exists as already handled in validation
                        $group_id = ($unique_group_name != '') ? array_search($unique_group_name, $groups) : 'NULL';

                        if ($username != '') {
                            self::updateUserDAGMapping($project_id, $username, $group_id);
                            ++$count;
                            continue;
                        }

                        ++$count;
                    }
                }
            }
        }

        // Return count and array of errors
        return array($count, $errors);
    }
}