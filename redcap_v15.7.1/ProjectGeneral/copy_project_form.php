<?php

use MultiLanguageManagement\MultiLanguage;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// If user is not allowed to create/copy projects, then redirect back to Project Setup page
if (!$allow_create_db && !($super_user && !UserRights::isImpersonatingUser()))
{
	redirect(APP_PATH_WEBROOT . "ProjectSetup/index.php?pid=$project_id");
}

// Count project records
$num_records = Records::getRecordCount($project_id);

// Are modules enabled?
$hasModules = false;
if (defined("APP_PATH_EXTMOD")) {
	$versionsByPrefix = \ExternalModules\ExternalModules::getEnabledModules(PROJECT_ID);
	$hasModules = !empty($versionsByPrefix);
}

include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

renderPageTitle();


/**
 * Modify Project Settings FORM
 */
print  "<br><br><div style='max-width:800px;border:1px solid #d0d0d0;padding:0px 15px 15px 15px;background-color:#f5f5f5;'>";
print  "<h5 style='border-bottom: 1px solid #aaa; margin-top:.5rem;padding: 5px 3px 10px; font-weight: bold;color:#800000;font-size:18px;'>
			<i class=\"far fa-copy\"></i> {$lang['edit_project_175']}
		</h5>";
print  "<p>" . $lang['copy_project_02'] . " (\"<b>" . htmlspecialchars(filter_tags(str_replace('<br>',' ',$app_title)), ENT_QUOTES) . "</b>\"), " .
		$lang['copy_project_16'] . "<br>";
if ($superusers_only_create_project && !$super_user) {
	print  "<p style='color:#800000;padding:5px 0;'>
				<img src='" . APP_PATH_IMAGES . "exclamation.png'>
				<b>{$lang['global_02']}:</b><br>
				{$lang['copy_project_05']} (".RCView::escape($user_email).") {$lang['copy_project_06']}
			</p>
		</p><br>
		<form name='createdb' action='".APP_PATH_WEBROOT."ProjectGeneral/notifications.php?pid=$project_id&type=request_copy' method='post'>";
} else {
	print  "
		</p><br>
		<form name='createdb' action='".APP_PATH_WEBROOT."ProjectGeneral/create_project.php' method='post'>";
}
// If normal user is requesting copy when it must be approved, include name and email as hidden fields here
$btn_text = $lang['control_center_4548'];
if ($superusers_only_create_project && (!$super_user || (isset($_GET['username']) && UserRights::isSuperUserNotImpersonator()))) {
	if (UserRights::isSuperUserNotImpersonator()) {
		print  "<input type='hidden' name='user_email' value='".RCView::escape($_GET['user_email'])."'>
				<input type='hidden' name='username' value='".RCView::escape($_GET['username'])."'>";
	} else {
		$btn_text = $lang['control_center_4721'];
		print  "<input type='hidden' name='user_email' value='".RCView::escape($user_email)."'>
				<input type='hidden' name='username' value='".RCView::escape($userid)."'>";
	}
}



// JS to execute when click button
$createdb_js = (SUPER_USER || $survey_pid_create_project == '') ? 'document.createdb.submit();' : "openSurveyDialogIframe('".Survey::getProjectStatusPublicSurveyLink('survey_pid_create_project')."');";

// Prepare a "certification" pop-up message when user clicks Create button if text has been set
$certify_text_js = "if (setFieldsCreateFormChk()) { showProgress(1); $createdb_js }";
if (hasPrintableText($certify_text_create) && (!$super_user || ($super_user && !isset($_GET['user_email']))))
{
	print "<div id='certify_create' title='".js_escape($lang['global_03'])."' style='display:none;text-align:left;'>".filter_tags(nl2br(html_entity_decode($certify_text_create, ENT_QUOTES)))."</div>";
	$certify_text_js = "if (setFieldsCreateFormChk()) {
							$('#certify_create').dialog({ bgiframe: true, modal: true, width: 500, buttons: {
								'".js_escape($lang['global_53'])."': function() { $(this).dialog('close'); },
								'".js_escape($lang['create_project_72'])."': function() {
									$(this).dialog('close');
									showProgress(1); $createdb_js
								}
							} });
						}";
}


print  "<table style='width:100%;table-layout:fixed;'>";
// Include the page with the form
include APP_PATH_DOCROOT . "ProjectGeneral/create_project_form.php";
addLangToJS(["create_project_140", "create_project_141", "create_project_142", "create_project_143",
    "create_project_144", "create_project_145", "create_project_146", "create_project_147",
    "create_project_148", "create_project_149", "create_project_150"]);
// Note about projects with surveys
if ($surveys_enabled && !empty($Proj->surveys))
{
	print  "<tr valign='top'>
				<td style=''>
				</td>
				<td>
					<div class='yellow' style='font-family:tahoma;font-size:11px;'>
						<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
						<b>{$lang['survey_1512']}</b><br>
						{$lang['copy_project_50']}<br><br>
						{$lang['copy_project_51']}<br><br>
						{$lang['copy_project_18']}";
	foreach ($Proj->surveys as $this_survey_id=>$survey_attr) {
		// Do NOT display any orphaned surveys (in which their instrument was deleted but are still in surveys table)
		if (!isset($Proj->forms[$survey_attr['form_name']]['survey_id'])) continue;
        // Set survey title
        $survey_title = trim(strip_tags($survey_attr['title']));
        $this_survey_title = $survey_title;
        $this_form_label = trim(strip_tags($Proj->forms[$Proj->surveys[$this_survey_id]['form_name']]['menu']));
        if ($this_survey_title == "") {
            $this_title = "\"$this_form_label\"";
        } elseif ($this_survey_title == $this_form_label) {
            $this_title = "\"$this_survey_title\"";
        } else {
            $this_title = "\"$this_survey_title\" [$this_form_label]";
        }
		// Add survey to list
		print  "<br> &bull; <b>$this_title</b>";
	}
	print  "		</div>
				</td>
			</tr>";
}

if ($mycap_enabled && !empty($myCapProj->tasks))
{
    print  "<tr valign='top'>
				<td style=''>
				</td>
				<td>
					<div class='yellow' style='font-family:tahoma;font-size:11px;'>
						<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
						<b>{$lang['mycap_mobile_app_665']}</b><br>
						{$lang['mycap_mobile_app_350']}<br><br>{$lang['mycap_mobile_app_351']}";
    foreach ($myCapProj->tasks as $this_task => $task_attr) {
        // Do NOT display any orphaned tasks (in which their instrument was deleted but are still in task table)
        if (!isset($Proj->forms[$this_task])) continue;

        if ($task_attr['title'] == '') {
            $task_attr['title'] = $Proj->forms[$task_attr['redcap_instrument']]['menu'];
        }
        // Add task to list
        print  "<br> &bull; <b>".RCView::escape(strip_tags($task_attr['title']))."</b>";
    }
    print  "		</div>
				</td>
			</tr>";
}

$dags = $Proj->getGroups();
$sq = Survey::getProjectSurveyQueue(false, false);
$surveyScheduler = new SurveyScheduler(PROJECT_ID);
$surveyScheduler->setSchedules(true);
$asi = $surveyScheduler->schedules;
$alert = new Alerts();
$alerts = $alert->getAlertSettings();
$dashboards = RecordDashboard::getRecordDashboardsList();
$dashOb = new ProjectDashboards();
$projectDashboards = $dashOb->getDashboards(PROJECT_ID);
$dashboard_folders = DataExport::getReportFolders(PROJECT_ID, 'project_dashboard');
$report_folders = DataExport::getReportFolders(PROJECT_ID);
$dq = new DataQuality();
$dq_rules = $dq->getRules();
foreach ($dq_rules as $key=>$attr) if (!is_numeric($key)) unset($dq_rules[$key]);
$roles = UserRights::getRoles();
$reports = DataExport::getReportNames(null, false, false, false);
$extlinks = new ExternalLinks();
$bookmarks = $extlinks->getResources();
$folders = ProjectFolders::forProjects(User::getUserInfo(USERID), [['project_id'=>PROJECT_ID]]);
$randConfig = Randomization::getAllRandomizationAttributes(PROJECT_ID);

$numSqlFields = $Proj->getCountSqlFields();
$sqlFieldWarning = "";
if ($numSqlFields > 0) {
    $sqlFieldWarning = "<div class='mt-3 fs11 text-dangerrc'><i class=\"fa-solid fa-lightbulb\"></i> ".RCView::tt_i('copy_project_49',[$numSqlFields])."</div>";
}

$ec = new Econsent();
$econsentSettings = $ec->getEconsentSettings(PROJECT_ID);
$rs = new PdfSnapshot();
$pdfSnapshotSettings = $rs->getSnapshots(PROJECT_ID);
$dps = DescriptivePopup::getLinkTextAllPopups();

// Do not copy records if project already has more than the max limit of records while in development - limit is not imposed on admins though
$max_records_development_global = (!SUPER_USER && isinteger($GLOBALS['max_records_development_global']) && $GLOBALS['max_records_development_global'] > 0) ? $GLOBALS['max_records_development_global'] : 0;
$copy_records_disabled = ($max_records_development_global > 0 && Records::getRecordCount(PROJECT_ID) > $max_records_development_global) ? "disabled" : "";
$copy_records_disabled_note = ($copy_records_disabled == "") ? "<br>" : RCView::tt('system_config_954', 'div', ['class'=>'text-dangerrc ms-3 mb-1 fs11']);

// Custom copy settings
print  "<tr valign='top'>
			<td style='padding-top:25px;'>
				<b>{$lang['copy_project_07']}</b><br>
				<i>{$lang['global_06']}</i>
			</td>
			<td id='copy_checkboxes' style='padding-top:25px;vertical-align:middle;'>
				<input type='checkbox' name='copy_records' id='copy_records' $copy_records_disabled> {$lang['copy_project_34']}<b><span style='font-size:14px;'>".User::number_format_user($num_records)."</span> {$lang['copy_project_15']}</b>$copy_records_disabled_note
				<input type='checkbox' name='copy_users' id='copy_users' checked> {$lang['copy_project_35']} ".(empty($dags) ? "" : $lang['copy_project_30'])."<br>
				<input type='checkbox' name='copy_roles' id='copy_roles' ".(!empty($roles) ? "checked" : "disabled")."> {$lang['copy_project_36']}<br>
				<input type='checkbox' name='copy_record_dash' id='copy_record_dash' ".(!empty($dashboards) ? "checked" : "disabled")."> {$lang['copy_project_42']}<br>
				<input type='checkbox' name='copy_reports' id='copy_reports' ".(!empty($reports) ? "checked" : "disabled")."> {$lang['app_06']}<br>
				<input type='checkbox' name='copy_report_folders' id='copy_report_folders' ".(!empty($report_folders) ? "checked" : "disabled")."> {$lang['copy_project_37']}<br>
				<input type='checkbox' name='copy_project_dashboards' id='copy_project_dashboards' ".(!empty($projectDashboards) ? "checked" : "disabled")."> {$lang['copy_project_38']}<br>
                <input type='checkbox' name='copy_dashboard_folders' id='copy_dashboard_folders' ".(!empty($dashboard_folders) ? "checked" : "disabled")."> {$lang['dash_133']}<br>
				<input type='checkbox' name='copy_dq_rules' id='copy_dq_rules' ".(!empty($dq_rules) ? "checked" : "disabled")."> {$lang['copy_project_39']}<br>
				<input type='checkbox' name='copy_alerts' id='copy_alerts' ".(!empty($alerts) ? "checked" : "disabled")."> {$lang['global_154']}
					 <div style='margin-left:17px;color:#777;font-size:11px;'>{$lang['copy_project_31']}</div>
				<input type='checkbox' name='copy_randomization' id='copy_randomization' ".(!empty($randConfig) ? "checked" : "disabled")."> {$lang['random_208']}<br>
				<input type='checkbox' name='copy_descriptive_popups' id='copy_descriptive_popups' ".(!empty($dps) ? "checked" : "disabled")."> {$lang['descriptive_popups_01']}<br>
				<input type='checkbox' name='copy_external_links' id='copy_external_links' ".(!empty($bookmarks) ? "checked" : "disabled")."> {$lang['copy_project_41']}<br>
				<input type='checkbox' name='copy_folders' id='copy_folders' value='1' ".(!empty($folders) ? "checked" : "disabled")."> {$lang['copy_project_40']}
				<br><input type='checkbox' name='copy_econsent_pdf_snapshots' id='copy_econsent_pdf_snapshots' ".(!empty($econsentSettings) || !empty($pdfSnapshotSettings) ? "checked" : "disabled")."> {$lang['econsent_120']}
				<br><input type='checkbox' name='copy_survey_queue_auto_invites' id='copy_survey_queue_auto_invites' ".(!empty($asi) || !empty($sq) ? "checked" : "disabled")."> {$lang['copy_project_45']}
					<div style='margin-left:17px;color:#777;font-size:11px;'>{$lang['copy_project_24']}</div>
				".(!defined("APP_PATH_EXTMOD") ? "" : "<input type='checkbox' name='copy_module_settings' id='copy_module_settings' ".($hasModules ? "checked" : "disabled")."> {$lang['copy_project_46']}<br>")."
			    <input type='checkbox' name='copy_formdisplaylogic' id='copy_formdisplaylogic' ".(FormDisplayLogic::isEnabled(PROJECT_ID) ? "checked" : "disabled")."> {$lang['copy_project_43']}
				<br><input type='checkbox' name='copy_languages' id='copy_languages' ".(MultiLanguage::isActive(PROJECT_ID) && MultiLanguage::hasLanguages(PROJECT_ID) ? "checked" : "disabled")."> {$lang['copy_project_44']}
				<br><input type='checkbox' name='copy_mycap_mobile_app_content' id='copy_mycap_mobile_app_content' ".($Proj->project['mycap_enabled'] ? "checked" : "disabled")."> {$lang['global_260']}
				    <div style='margin-left:17px;color:#777;font-size:11px;'>{$lang['copy_project_47']}".((!UserRights::isSuperUserNotImpersonator() && $GLOBALS['mycap_enable_type'] == 'admin') ? " <u>".$lang['copy_project_48']."</u>" : "")."</div>
				<div class='mt-2'>
				    <a href='javascript:;' style='text-decoration: underline;' class='fs12' onclick=\"$('#copy_checkboxes input[type=checkbox]:not(:disabled)').prop('checked',true);\">{$lang['data_export_tool_52']}</a>
				    | <a href='javascript:;' style='text-decoration: underline;' class='fs12' onclick=\"$('#copy_checkboxes input[type=checkbox]').prop('checked',false);\">{$lang['data_export_tool_53']}</a>
                </div>
                $sqlFieldWarning
			</td>
		</tr>";
// Submit buttons
print  "<tr valign='top'>
			<td>
			</td>
			<td style='padding:25px 0 15px;'>
				<button class='btn btn-rcgreen' onclick=\"
					if ($('#currenttitle').val() == $('#app_title').val()) {
						simpleDialog('".js_escape($lang['copy_project_11'])."');
						return false;
					}
					$certify_text_js
					return false;
				\">$btn_text</button>
				&nbsp; 
				<button class='btn btn-defaultrc cancel-copy' onclick='history.go(-1);return false;'>{$lang['global_53']}</button>
			</td>
		</tr>";

print  "</table>";
// Hidden field to denote that we are copying a project
print  "<input type='hidden' name='copyof' value='$project_id'>";
// Hidden field to for checking against to prevent duplicate titles, which may cause confusion
print  "<input type='hidden' id='currenttitle' value='" . RCView::escape($app_title) . "'>";
print  "</form>";
print  "</div>";

if (isset($_GET['username']) && $superusers_only_create_project && $super_user)
{
	// If only Super Users can copy db and they are responding to request, pre-fill with request info
	print  "<script type='text/javascript'>
			$(function(){
				setTimeout(function(){
                    $('#app_title').val('".str_replace("'", "&#039;", filter_tags(html_entity_decode($_GET['app_title'], ENT_QUOTES)))."');
                    $('#app_title').val($('#app_title').val().replace(/&#039;/g,\"'\"));
					$('#purpose').val({$_GET['purpose']});
					if ($('#purpose').val() == '1') {
						$('#purpose_other_span').css({'visibility':'visible'});
						$('#purpose_other_text').val('" . js_escape(html_entity_decode(filter_tags(html_entity_decode($_GET['purpose_other'], ENT_QUOTES)), ENT_QUOTES)) . "');
						$('#purpose_other_text').css('display','');
					}
					if ($('#purpose').val() == '2') {
						$('#purpose_other_span').css({'visibility':'visible'});
						$('#purpose_other_research').css('display','');
						$('#project_pi_irb_div').css('display','');
						$('#project_pi_firstname').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_firstname'], ENT_QUOTES))) . "');
						$('#project_pi_mi').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_mi'], ENT_QUOTES))) . "');
						$('#project_pi_lastname').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_lastname'], ENT_QUOTES))) . "');
						$('#project_pi_email').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_email'], ENT_QUOTES))) . "');
						$('#project_pi_alias').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_alias'], ENT_QUOTES))) . "');
						$('#project_pi_username').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_username'], ENT_QUOTES))) . "');
						$('#project_irb_number').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_irb_number'], ENT_QUOTES))) . "');
						$('#project_grant_number').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_grant_number'], ENT_QUOTES))) . "');
						var purposeOther = '".js_escape(filter_tags(html_entity_decode($_GET['purpose_other'], ENT_QUOTES)))."';
						var purposeArray = purposeOther.split(',');
						for (i = 0; i < purposeArray.length; i++) {
							if (document.getElementById('purpose_other['+purposeArray[i]+']') != null) {
								document.getElementById('purpose_other['+purposeArray[i]+']').checked = true;
							}
						}
					}
					$('#repeatforms_chk_div').css({'display':'block'});
					$('#datacollect_chk').prop('checked',true);
					$('#projecttype".($_GET['surveys_enabled'] == '1' ? '2' : ($_GET['surveys_enabled'] == '2' ? '0' : '1'))."').prop('checked',true);
					$('#repeatforms_chk".($_GET['repeatforms'] ? '2' : '1')."').prop('checked',true);
					if ({$_GET['scheduling']} == 1) $('#scheduling_chk').prop('checked',true);
					if ({$_GET['randomization']} == 1) $('#randomization_chk').prop('checked',true);
					setFieldsCreateForm();
					//Format table for this view
					$('#row_primary_use').css({'display':'none'});
					$('#row_projecttype_title').css({'display':'none'});
					$('#row_projecttype').css({'display':'none'});
					$('#row_purpose1').css({'padding':'0px'});
					$('#row_purpose2').css({'padding':'0px'});
					//Copy users/reports
					$('#copy_users').prop('checked'," . (($_GET['c_users'] == "on") ? "true" : "false") . ");
					$('#copy_roles').prop('checked'," . (($_GET['c_roles'] == "on") ? "true" : "false") . ");
					$('#copy_reports').prop('checked'," . (($_GET['c_reports'] == "on") ? "true" : "false") . ");
					$('#copy_records').prop('checked'," . (($_GET['c_records'] == "on") ? "true" : "false") . ");
                    $('#copy_folders').prop('checked'," . (($_GET['c_folders'] == "1") ? "true" : "false") . ");
                    $('#copy_survey_queue_auto_invites').prop('checked'," . (($_GET['c_queue_asi'] == "on") ? "true" : "false") . ");                    
					$('#copy_report_folders').prop('checked'," . (($_GET['c_report_folders'] == "on") ? "true" : "false") . ");                   
					$('#copy_project_dashboards').prop('checked'," . (($_GET['c_project_dashboards'] == "on") ? "true" : "false") . ");
					$('#copy_dq_rules').prop('checked'," . (($_GET['c_dq_rules'] == "on") ? "true" : "false") . ");
					$('#copy_external_links').prop('checked'," . (($_GET['c_external_links'] == "on") ? "true" : "false") . ");
                    $('#copy_record_dash').prop('checked'," . (($_GET['c_record_dash'] == "on") ? "true" : "false") . ");
                    $('#copy_alerts').prop('checked'," . (($_GET['c_alerts'] == "on") ? "true" : "false") . ");                    
                    $('form[name=\"createdb\"]').append('<input type=\"hidden\" value=\"'+getParameterByName('survey_pid_create_project')+'\" name=\"survey_pid_create_project\">');
				},1);
			});
			</script>";
} else {
	// Use javascript to pre-fill form with existing info
	print  "<script type='text/javascript'>
			$(function(){
			setTimeout(function(){
                $('#app_title').val('".str_replace("'", "&#039;", filter_tags(html_entity_decode($app_title, ENT_QUOTES)))."');
                $('#app_title').val($('#app_title').val().replace(/&#039;/g,\"'\"));
				$('#purpose').val($purpose);
				if ($('#purpose').val() == '1') {
					$('#purpose_other_span').css({'visibility':'visible'});
					$('#purpose_other_text').val('" . js_escape(filter_tags(html_entity_decode($purpose_other, ENT_QUOTES))) . "');
					$('#purpose_other_text').css('display','');
				}
				if ($('#purpose').val() == '2') {
					$('#purpose_other_span').css({'visibility':'visible'});
					$('#purpose_other_research').css('display','');
					$('#project_pi_irb_div').css('display','');
					$('#project_pi_firstname').val('" . js_escape(filter_tags($project_pi_firstname)) . "');
					$('#project_pi_mi').val('" . js_escape(filter_tags($project_pi_mi)) . "');
					$('#project_pi_lastname').val('" . js_escape(filter_tags($project_pi_lastname)) . "');
					$('#project_pi_email').val('" . js_escape(filter_tags($project_pi_email)) . "');
					$('#project_pi_alias').val('" . js_escape(filter_tags($project_pi_alias)) . "');
					$('#project_pi_username').val('" . js_escape(filter_tags(html_entity_decode($project_pi_username, ENT_QUOTES))) . "');
					$('#project_irb_number').val('" . js_escape(filter_tags(html_entity_decode($project_irb_number, ENT_QUOTES))) . "');
					$('#project_grant_number').val('" . js_escape(filter_tags(html_entity_decode($project_grant_number, ENT_QUOTES))) . "');
					var purposeOther = '".js_escape(filter_tags(html_entity_decode($purpose_other, ENT_QUOTES)))."';
					var purposeArray = purposeOther.split(',');
					for (i = 0; i < purposeArray.length; i++) {
						if (document.getElementById('purpose_other['+purposeArray[i]+']') != null) {
							document.getElementById('purpose_other['+purposeArray[i]+']').checked = true;
						}
					}
				}
				$('#repeatforms_chk_div').css({'display':'block'});
				$('#datacollect_chk').prop('checked',true);
				$('#projecttype".($surveys_enabled ? '2' : '1')."').prop('checked',true);
				$('#repeatforms_chk".($repeatforms ? '2' : '1')."').prop('checked',true);
				if ($scheduling == 1) $('#scheduling_chk').prop('checked',true);
				if ($randomization == 1) $('#randomization_chk').prop('checked',true);
				setFieldsCreateForm();
				//Format table for this view
				$('#row_primary_use').css({'display':'none'});
				$('#row_projecttype_title').css({'display':'none'});
				$('#row_projecttype').css({'display':'none'});
				$('#row_purpose1').css({'padding':'0px'});
				$('#row_purpose2').css({'padding':'0px'});
			},1);
			});
			</script>";
}


// If project contains one or more Misc File Attachments, which might be hard-coded throughout the project, display them in table format
// so that user understand that these need to be re-pointed/re-uploaded in the new project.
$docsTable = FileRepository::getMiscFileAttachmentsTable();
if ($docsTable != "") {
    print  "<div class='simpleDialog' id='misc-file-attach-warning-dialog' title='".js_escape($lang['data_export_tool_310'])."'>
                <i class='fa-solid fa-circle-info'></i> {$lang['data_export_tool_309']}
                $docsTable
            </div>";
    print  "<script type='text/javascript'>
			$(function(){
                if (!inIframe()) {
                    setTimeout(function(){
                        simpleDialog(null,null,'misc-file-attach-warning-dialog',820,null,'" . js_escape($lang['data_export_tool_312']) . "');
                        fitDialog($('#misc-file-attach-warning-dialog'));
                    },1000);
                }
			});
			</script>";
}

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
