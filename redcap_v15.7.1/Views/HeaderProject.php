<?php

// Prevent view from being called directly
require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
System::init();

use ExternalModules\ExternalModules;
use MultiLanguageManagement\MultiLanguage;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;
use Vanderbilt\REDCap\Classes\MyCap\Message;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirRenderer;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\SessionDTO;
use Vanderbilt\REDCap\Classes\Fhir\Utility\CdisPanelBuilder;
use Vanderbilt\REDCap\Classes\MyCap\SyncIssues;

// Page header
$objHtmlPage = new HtmlPage();
$objHtmlPage->addExternalJS(APP_PATH_JS . "Project.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "InstanceSelector.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "ReportFolders.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "TakeATour.js");
$objHtmlPage->addStylesheet("TakeATour.css", "screen");
$objHtmlPage->addExternalJS(APP_PATH_JS . "ProjectDashboardFolders.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "Libraries/velocity-min.js");
$objHtmlPage->addExternalJS(APP_PATH_JS . "Libraries/velocity-ui-min.js");
$objHtmlPage->addExternalJS(APP_PATH_WEBPACK . "css/tinymce/tinymce.min.js");
if (PAGE == 'DataEntry/index.php') {
	$objHtmlPage->addStylesheet("survey_text_very_large.css", "screen and (max-width: 767px)");
}
else if (PAGE == "MultiLanguageController:projectSetup") {
	$objHtmlPage->addStylesheet("multilanguage-setup.css", "screen,print");
	$objHtmlPage->addExternalJS(APP_PATH_JS . "Libraries/bundle-multilanguage.js");
}
if (defined("PROJECT_ID")) {
    $links = ExternalModules::getEnabledModules(PROJECT_ID);
    if (array_key_exists('mycap', $links)) {
        $objHtmlPage->addExternalJS(APP_PATH_JS . "MyCapProject.js");
    }
}
$objHtmlPage->setPageTitle(remBr(br2nl($app_title))." | REDCap");
$objHtmlPage->PrintHeader(false);

// Note to survey participants or form users about incompatibility with IE
if ($GLOBALS['isIE']) {
    print "<div class='red fs15 p-3' style='max-width:1150px;'><i class=\"fa-solid fa-triangle-exclamation fs16\"></i> ".RCView::tt("data_entry_602")."</div>";
}

// STATS: Check if need to report institutional stats to REDCap consortium
Stats::checkReportStats();

// PROJECT DELETED: If project has been scheduled for deletion, then display dialog that project can't be accessed (except by super users)
if ($date_deleted != "")
{
    $delete_project_day_lag = Project::getDeleteProjectDayLag();

	// Display "project was deleted" dialog
	$deleteProjDialog = RCView::tt_i("bottom_113", array(
		DateTimeRC::format_ts_from_ymd(date('Y-m-d H:i:s', strtotime($date_deleted) + 3600 * 24 * $delete_project_day_lag))
	));
	if ($super_user) {
		$deleteProjDialog .= "<br><br><b>" . RCView::tt("edit_project_77") . "</b> " . RCView::tt("bottom_68");
	}
	// Note that the popup cannot be closed
	$deleteProjDialog .= RCView::div(array(
			"style" => "color:#777;margin:15px 0 20px;"
		), RCView::tt("edit_project_155")
	);
	// "Return to My Projects" button
	$deleteProjDialog .= RCView::button(array(
		'href'=>'javascript:;', 
		'onclick'=>"window.location.href='".APP_PATH_WEBROOT_PARENT."index.php?action=myprojects';",
		'class'=>'jqbuttonmed'
	), RCView::tt("bottom_69"));
	// If a super user, show "Restore" button
	if ($super_user) {
		$deleteProjDialog .= RCView::SP . RCView::button(array(
			'href'=>'javascript:;',
			'onclick'=>"undelete_project($project_id)",
			'class'=>'jqbuttonmed'
		), RCView::tt("control_center_375"));
	}
	// Notice div that project was deleted
	print RCView::simpleDialog(RCView::div(array('style'=>'color:#C00000;'), $deleteProjDialog),$lang['global_03'].$lang['colon']." ".$lang['bottom_67'],"deleted_note");
	// Hidden "undelete project" div
	print RCView::simpleDialog("", $lang['control_center_378'], 'undelete_project_dialog');
	?>
	<script type="text/javascript">
	function openDelProjDialog() {
        if (page != 'index.php') {
            window.location.href = app_path_webroot+'index.php?pid='+pid;
        } else {
            $('#deleted_note').dialog({ bgiframe: true, modal: true, width: 500, close: function(){ setTimeout('openDelProjDialog()',10); } });
        }
	}
	$(function(){ openDelProjDialog(); });
	</script>
	<?php
}

// PROJECT COMPLETED: If project has been marked as Completed, then display dialog that project can't be accessed (except by super users)
if ($completed_time != "")
{
	// Display "project was  marked as Completed" dialog
	$completed_by_user_info = User::getUserInfo($completed_by);
	$completedProjDialog = "<div class='mb-3'>" . $lang['bottom_93'] . "</div>" .  $lang['bottom_103'] . " " .RCView::b(DateTimeRC::format_user_datetime($completed_time, 'Y-M-D_24'))
						 . " " . $lang['form_renderer_06'] . " " . RCView::b($completed_by)
						 . " " . $lang['leftparen'] . "{$completed_by_user_info['user_firstname']} {$completed_by_user_info['user_lastname']} - {$completed_by_user_info['user_email']}"
						 . $lang['rightparen'] . $lang['period'];
	if ($super_user) {
		$completedProjDialog .= "<br><br><b>{$lang['edit_project_77']}</b> {$lang['bottom_114']}";
	}
	// Note that the popup cannot be closed
	$completedProjDialog .= RCView::div(array('style'=>'color:#777;margin:15px 0 20px;'), $lang['edit_project_155']);
	// "Return to My Projects" button
	$completedProjDialog .= RCView::button(array('href'=>'javascript:;', 'onclick'=>"window.location.href='".APP_PATH_WEBROOT_PARENT."index.php?action=myprojects';", 'class'=>'btn btn-sm btn-defaultrc'), '<i class="far fa-list-alt"></i> '.$lang['bottom_69']);
	// If a super user, show "Restore" button
	if ($super_user) {
		$completedProjDialog .= RCView::SP . RCView::button(array('href'=>'javascript:;', 'onclick'=>"displayProjectUsers($project_id)", 'class'=>'btn btn-sm btn-primaryrc'), '<i class="fas fa-users"></i> '.$lang['bottom_104']);
		$completedProjDialog .= RCView::SP . RCView::button(array('href'=>'javascript:;', 'onclick'=>"uncomplete_project($project_id)", 'class'=>'btn btn-sm btn-rcgreen'), '<i class="fas fa-undo-alt"></i> '.$lang['bottom_95']);
	}
	// Notice div that project was deleted
	print RCView::simpleDialog(RCView::div(array('style'=>'color:#C00000;'), $completedProjDialog),$lang['global_03'].$lang['colon']." ".$lang['bottom_96'],"completed_note");
	// Hidden "undelete project" div
	print RCView::simpleDialog("", $lang['control_center_378'], 'undelete_project_dialog');
	?>
    <script type="text/javascript">
	function openCompletedProjDialog() {
        if (page != 'index.php') {
            window.location.href = app_path_webroot+'index.php?pid='+pid;
        } else {
            $('#completed_note').dialog({ bgiframe: true, modal: true, width: 600, close: function(){ setTimeout('openCompletedProjDialog()',10); } });
		}
	}
	$(function(){ openCompletedProjDialog(); });
    // Return a Completed project back to Analysis/Cleanup mode
    function uncomplete_project(this_pid) {
        if (!super_user) return;
        $.post(app_path_webroot+'ProjectGeneral/change_project_status.php?pid='+this_pid, { restore_completed: '1' }, function(data) {
			if (data == '1') {
				simpleDialog('<?php echo js_escape($lang['bottom_97']) ?>','<?php echo js_escape($lang['bottom_98']) ?>',null,null,"window.location.reload()");
			} else {
				alert(woops);
			}
        });
    }
    // Return a list of all project users and their rights
    function displayProjectUsers(this_pid) {
        if (!super_user) return;
        showProgress(1);
        $.post(app_path_webroot+'index.php?route=UserRightsController:displayRightsRolesTable&pid='+this_pid, { }, function(data) {
            showProgress(0,0);
            if (data != '') {
                simpleDialog('<div class="mt-2">'+data+'</div>','<?php echo js_escape($lang['setup_39']) ?>', 'displayProjectUsersDialog', $(document).width()-100);
                $('#addUsersRolesDiv').remove();
                $('#displayProjectUsersDialog a').each(function(){
                    $(this).after( $(this).text() ).remove();
				});
                fitDialog($('#displayProjectUsersDialog'));
            } else {
                alert(woops);
            }
        });
    }
    </script>
	<?php
}


// Project status label
$statusLabel = '<div>';
$statusLabel .= ($longitudinal) ? '<i class="fas fa-layer-group" title="'.js_escape2($lang['create_project_51']).'"></i>' : '<i class="fas fa-square" style="font-size:10px;" title="'.js_escape2($lang['create_project_49']).'"></i>';
$statusLabel .= ' '.$lang['edit_project_58'].'&nbsp; ';
// Set icon/text for project status
if ($status == '1') {
	$statusLabel .= RCView::tt("global_30", "b", ["style" => "color:green;"]);
	if ($draft_mode == "1") {
		$draft_mode_style = Design::isDraftPreview() ? "" : "display:none;";
		$draft_mode_class = ($GLOBALS["draft_preview_enabled"] ?? false) ? "text-danger" : "text-dark";
		$statusLabel .= RCView::span([
				"id" => "draft-preview-status",
				"style" => $draft_mode_style
			], 
			" - " . 
			RCView::a(
				[
					"title" => RCView::tt_attr("draft_preview_14"),
					"data-bs-toggle" => "tooltip",
					"style" => "cursor:pointer;",
					"onclick" => "setDraftPreviewDo(null, 'OFF');",
					"href" => "javascript:;",
				], 
				RCView::tt("draft_preview_05", "b", [ "class" => $draft_mode_class ])
				. RCIcon::ErrorNotificationTriangle("text-danger ms-1")
			)
		);
	}
} elseif ($status == '2') {
	$statusLabel .= '<b style="color:#A00000;">'.$lang['global_159'].'</b>';
	if ($data_locked) {
		$statusLabel .= '<i class="fas fa-lock ms-1" style="color:#A00000;" title="'.js_escape2($lang['bottom_102']).'"></i>';
    }
} else {
	// Development
	$save_changes_off = false;
	$statusLabel .= '<b style="color:#555;">'.RCView::tt("global_29").'</b>';
//	if ($user_rights['design'] || UserRights::isSuperUserNotImpersonator()) {
//		$statusLabel .= RCView::div([
//				"class" => "save-changes-toggle " . ($save_changes_off ? "is-disabled" : "is-enabled"),
//				"onclick" => "toggleSaveChanges();",
//			],
//			RCView::span([
//					"class" => "save-changes-enabled toggle-group",
//				],
//				RCView::fa("fa-solid fa-toggle-off toggle me-1") .
//				RCView::tt("design_ic_03") // "Disable \"Save your changes?\" prompt"
//			) .
//			RCView::span([
//					"class" => "save-changes-disabled toggle-group",
//				],
//				RCView::fa("fa-solid fa-toggle-on toggle me-1") .
//				RCView::tt("design_ic_04") // "\"Save your changes?\" prompt disabled!"
//			)
//		);
//	}
}
$statusLabel .= '</div>';

// Set values for Contact REDCap Administrator button/link on menu
$contactAdminBtnText = ($Proj->project['project_contact_name'] == '') ? $lang['bottom_76'] : $lang['index_09'] . " " . strip_tags($Proj->project['project_contact_name']);
if ($contact_admin_button_url == "") {
    // Use standard mailto link for admin
	$contactAdminBtnLink = "mailto:$project_contact_email?subject=" . rawurlencode($lang['bottom_77'] . " PID $project_id") . "&body=" . rawurlencode($lang['global_11'] . $lang['colon'] . " " . (defined("USERID") ? USERID : "") . "\n" . $lang['control_center_107'] . " \"" . strip_tags($app_title) . "\"\n" . $lang['bottom_81'] . " " . APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/index.php?pid=$project_id\n\n" . $lang['bottom_78'] . "\n\n" . $lang['bottom_79'] . "\n\n" . $lang['bottom_80'] . "\n$user_firstname $user_lastname\n");
} else {
    // Add custom email or custom URL and perform piping, if possible
	$contactAdminBtnLink = Piping::pipeSpecialTags($contact_admin_button_url, PROJECT_ID);
	// If just an email address (unexpected), then fallback to using mailto
    if (isEmail($contactAdminBtnLink)) {
		$contactAdminBtnLink = "mailto:$contactAdminBtnLink";
    }
}

/**
 * LOGO & LOGOUT
 */
$logoHtml = "<div id='menu-div'>
				<div class='menubox' style='padding:0px 10px 0px 7px;'>
					<div id='project-menu-logo'>
						<a href='".APP_PATH_WEBROOT_PARENT."index.php?action=myprojects" . (($auth_meth_global == "none" && $auth_meth_global != $auth_meth_global && $auth_meth_global != "shibboleth") ? "&logout=1" : "") . "'
							><img src='".APP_PATH_IMAGES."redcap-logo.png' title='REDCap' alt='REDCap logo' style='height:45px;'></a>
					</div>
					<div style='font-size:11px;color:#6f6f6f;margin:3px -10px 7px -2px;'>
						<i class=\"fas fa-lock mx-1 fs10\"></i>{$lang['bottom_01']} <span id='username-reference' style='font-weight:bold;color:#555;'>$userid</span>
						".($auth_meth_global == "none"
							? ""
							: 	((strlen($userid) < 14 && $auth_meth_global != "none")
									? " &nbsp;|&nbsp; <span>"
									: "<br><span style='padding:1px 0 0;'><img src='".APP_PATH_IMAGES."cross_small_circle_gray.png' style='top:5px;'> "
								) .
								"<a href='".PAGE_FULL."?".$_SERVER['QUERY_STRING']."&logout=1' style='font-size:11px;'>{$lang['bottom_02']}</a></span>"
						  )."
					</div>
					<div class='hang'>
						<i class='far fa-list-alt' style='position:relative;margin-left:-1px;margin-right:3px;'></i><a href='".APP_PATH_WEBROOT_PARENT."index.php?action=myprojects" . (($auth_meth_global == "none" && $auth_meth_global != $auth_meth_global && $auth_meth_global != "shibboleth") ? "&logout=1" : "") . "'>{$lang['bottom_03']}</a>
						" .
						(!defined("ACCESS_CONTROL_CENTER") || !ACCESS_CONTROL_CENTER ? "" :
							RCView::span(array('style'=>'color:#777;margin:0 8px 0 4px;'), $lang['global_47']) .
							"<i class='fas fa-cog'></i><a href='".APP_PATH_WEBROOT."ControlCenter/index.php'>{$lang['global_07']}</a>"
						) .
						"
					</div>" .
					($user_messaging_enabled
						? "<div class='hang user-messaging-left-item'>
								<i class='fas fa-comment-alt' style='top:1px;color:#3E72A8 !important;margin-right:3px;'></i><a href='javascript:;'>{$lang['messaging_09']}</a>".Messenger::renderHeaderIcon('project-page')."
							</div>"
						: ""
					) .
                    "<div class='hang'>
					    <i class='fas fa-envelope' style='top:1px;color:#A00000;margin-right:3px;'></i><a target='_blank' href='$contactAdminBtnLink' style='color:#A00000;' class='btn-contact-admin'>$contactAdminBtnText</a>
				    </div>" .
	                UserRights::renderImpersonateUserDropDown() . 
					UserRights::renderNavigateToPageWidget(true) . "
				</div>";
$homeSetupMenu = "<div class='menubox' style='padding-right:0px;'>	
					<div class='hang'>
						<span class='nowrap'><i class='fas fa-home'></i>&nbsp;<a href='".APP_PATH_WEBROOT."index.php?pid=$project_id'>".RCView::tt("bottom_119")."</a></span>" .
						($user_rights['design'] ?
							// Design rights: Home . Setup . Codebook
							"<span style='color:gray;margin:0 9px 0 8px;'>&middot;</span><i class=\"fas fa-tasks\"></i><a href='".APP_PATH_WEBROOT."ProjectSetup/index.php?pid=$project_id'>".RCView::tt("bottom_120")."</a><span style='color:gray;margin:0 7px 0 6px;'>&middot;</span><span class='nowrap'><i class=\"fas fa-book fs14\"></i><a href='".APP_PATH_WEBROOT."Design/data_dictionary_codebook.php?pid=$project_id'>{$lang['design_482']}</a></span>"
							// Regular user: Home . Codebook
							: "<span style='color:gray;margin:0 9px 0 8px;'>&middot;</span><span class='nowrap'><i class=\"fas fa-book fs12\"></i><a href='".APP_PATH_WEBROOT."Design/data_dictionary_codebook.php?pid=$project_id'>".RCView::tt("design_482")."</a></span>"
						) .
						"
					</div>" .
                    (!$user_rights['design'] ? "" : 
						// Design rights: Designer . Events . Data Dictionary
						"<div class='hang' style='margin-top:2px;'><span class='nowrap'><i class=\"fas fa-edit fs14\"></i> <a href='".APP_PATH_WEBROOT."Design/online_designer.php?pid=$project_id'>{$lang['design_781']}</a></span>" .
						($Proj->project["repeatforms"] ? "<span style='color:gray;margin:0 7px 0 6px;'>&middot;</span><span class='nowrap'><i class=\"fa-regular fa-calendar-plus fs14\"></i><a href='".APP_PATH_WEBROOT."Design/define_events.php?pid=$project_id'>".RCView::tt("bottom_121")."</a></span>" : "") .
                        "<span style='color:gray;margin:0 7px 0 6px;'>&middot;</span>" .
                        "<span class='nowrap'><img src='".APP_PATH_IMAGES."xls2.png' style='opacity:0.8;position:relative;top:-1px;'>&nbsp;<a href='".APP_PATH_WEBROOT."Design/data_dictionary_upload.php?pid=$project_id'>{$lang['design_780']}</a></span>" .
                        "</div>"
                    ) ."<div class='fs11 mt-1' style='color:#6f6f6f;'>$statusLabel</div>
				</div>
			</div>";

function makePanelTitle($title, $id, $collapsed=false) {
	$imgCollapsed = $collapsed ? "toggle-expand.png" : "toggle-collapse.png";
	ob_start();
	?>
	<div style="float:left"><?php print($title) ?></div>
		<div class="opacity65 projMenuToggle" id="<?php print($id) ?>">
			<a href=javascript:;">
				<img src="<?php print(APP_PATH_IMAGES.$imgCollapsed) ?>" />
			</a>
		</div>
	<?php
	$html = ob_get_contents();
	ob_end_clean();
	return $html;
}

// Set panel title text
$menu_id = 'home_setup_panel';
$homeSetupMenuCollapsed = UIState::getMenuCollapseState($project_id, $menu_id);
$homeSetupMenuTitle = makePanelTitle($lang['design_782'], $menu_id, $homeSetupMenuCollapsed);

// ONLY for DATA ENTRY FORMS, get record information
list ($fetched, $hidden_edit, $entry_num) = Records::getRecordAttributes();


// Build data entry form list
if (!empty($user_rights))
{
	$dataEntry = "<div class='menubox' style='padding-right:0px;'>";

    // MyCap Data Collection
    if ($mycap_enabled && $mycap_enabled_global && $user_rights['mycap_participants'])
    {
        global $myCapProj;
        // Get any unread MyCap messages
        Message::setActionNeededInboxMessagesCount(PROJECT_ID);
        $actionNeededCount = Message::$action_needed_messages;
        if ($myCapProj->project['acknowledged_app_link'] == 0) {
            $actionNeededCount += 1;
        }

        $actionNeededCount += SyncIssues::getUnresolvedIssuesCount(PROJECT_ID);

        $mycapMsgBlock = '';
        if ($actionNeededCount > 0) {
            $mycapMsgBlock = '<span id="allCountBlock" class="badgerc ms-2">'.$actionNeededCount.'</span>';
        }
        // Output menu link
        $dataEntry .= "<div class='hang'><img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:24px;position:relative;top:-2px;margin-left:-8px;'>&nbsp;<a href=\"" . APP_PATH_WEBROOT . "MyCapMobileApp/index.php?pid=$project_id\">{$lang['mycap_mobile_app_628']}</a>$mycapMsgBlock</div>";
        if ($status < 1) {
			$dataEntry .=  "<div class='menuboxsub'>- ".RCView::tt("mycap_mobile_app_629")."</div>";
		}
    }

	// Set text for Invite Participants link
	$invitePart = "";
	if ($surveys_enabled && $user_rights['participants']) {
		$invitePart = "<div class='hang'><i class=\"fas fa-chalkboard-teacher\" style='position:relative;top:2px;margin-left:-3px;'></i> <a href='".APP_PATH_WEBROOT."Surveys/invite_participants.php?pid=$project_id'>".RCView::tt("app_24")."</a></div>";
		if ($status < 1) {
			$invitePart .=  "<div class='menuboxsub'>- ".RCView::tt("invite_participants_01")."</div>";
		}
	}
	// Is user in a DAG? If so, display their DAG name.
	$dagDisplay = ($user_rights['group_id'] == '') ? '' : $Proj->getGroups($user_rights['group_id']);
	if ($dagDisplay != '') {
		$dagDisplay = "<div class='float-start mx-2 text-secondary'>&mdash;</div><div class='nowrap float-start' style='color:#008000;'>$dagDisplay</div>";
    }

	// Set panel title text
	$menu_id = 'projMenuDataCollection';
	$dataEntryCollapsed = UIState::getMenuCollapseState($project_id, $menu_id);
	$imgCollapsed = $dataEntryCollapsed ? "toggle-expand.png" : "toggle-collapse.png";
	$dataEntryTitle =  "<div class='float-start'>".RCView::tt("bottom_47")."</div>$dagDisplay
						<div class='opacity65 projMenuToggle' id='$menu_id'>"
						. RCView::a(array('href'=>'javascript:;'),
							RCView::img(array('src'=>$imgCollapsed))
						  ) . "
					   </div>";

	## DATA COLLECTION SECTION
	// Invite Participants
	$dataEntry .= $invitePart;

	// Scheduling
	if ($repeatforms && $scheduling && $user_rights['calendar'] == '1') {
		$dataEntry .= "<div class='hang'><i class=\"far fa-calendar-plus\" style='margin-left:2px;margin-left:1px;'></i> <a href='".APP_PATH_WEBROOT."Calendar/scheduling.php?pid=$project_id'>".$lang['global_25']."</a></div>";
		if ($status < 1) {
			$dataEntry .=  "<div class='menuboxsub'>- ".$lang['bottom_19']."</div>";
		}
	}

	## DATA STATUS GRID
	$dataEntry .= "<div class='hang' style='position:relative;'><i class=\"fas fa-th\" style=''></i> <a id='rsd-men-link' href='".APP_PATH_WEBROOT."DataEntry/record_status_dashboard.php?pid=$project_id'>".RCView::tt("global_91")."</a></div>";
	if ($status < 1) {
		$dataEntry .=  "<div class='menuboxsub' style='position:relative;'>- ".RCView::tt("bottom_60")."</div>";
	}

	// If user is on grid page or data entry page and record is selected, make grid icon a link back to grid page
	$dataEntry .=  "<div class='hang' style='position:relative;'>
                        <i class=\"fas fa-file-alt fs14\" style='color:#900000;'></i> <a href='".APP_PATH_WEBROOT."DataEntry/record_home.php?pid=$project_id' style='color:#A00000;'>".
						($user_rights['record_create'] ? RCView::tt("bottom_62") : RCView::tt("bottom_72"))."</a>
					</div>";
	if ($status < 1) {
		$dataEntry .=  "<div class='menuboxsub' style='position:relative;'>- ".
						(($user_rights['record_create'] && isset($user_rights['forms']) && UserRights::hasDataViewingRights($user_rights['forms'][$Proj->firstForm], "view-edit")) ? RCView::tt("bottom_64") : RCView::tt("bottom_73"))."</div>";
	}


	## Rewards
	if (!($status == '2' && $data_locked) && ($rewards_enabled_global && $rewards_enabled)) {
		$dataEntry .= "<div class='hang' style='position:relative;'><i class='fas fa-gift'></i> <a id='rewards-link' href='".APP_PATH_WEBROOT."Rewards/index.php?pid=$project_id'>{$lang['rewards_feature_name']}</a></div>";
		if ($status < 1) {
			$dataEntry .=  "<div class='menuboxsub' style='position:relative;'>- ".$lang['rewards_feature_description']."</div>";
		}
	}

	## DATAMART
	if (!($status == '2' && $data_locked) && $fhir_data_mart_create_project && DataMart::isEnabled($project_id)) {
		$dataEntry .= "<div class='hang' style='position:relative;'><i class='fas fa-shopping-cart'></i> <a id='data-mart-menu-link' href='".APP_PATH_WEBROOT."index.php?pid=$project_id&route=DataMartController:index'>{$lang['global_155']}</a></div>";
		if ($status < 1) {
			$dataEntry .=  "<div class='menuboxsub' style='position:relative;'>- ".$lang['bottom_83']."</div>";
		}
	}

	// If showing Scheduling OR Invite Participant links OR viewing a record in longitudinal...
	if ((isset($_GET['id']) && PAGE == "DataEntry/record_home.php")
		|| (isset($fetched) && PAGE == "DataEntry/index.php"))
	{
		// Show record name on left-hand menu (if a record is pulled up)
		$record_label = "";
		if ((isset($_GET['id']) && PAGE == "DataEntry/record_home.php")
			|| (isset($fetched) && PAGE == "DataEntry/index.php" && isset($_GET['event_id']) && is_numeric($_GET['event_id'])))
		{
			if (PAGE == "DataEntry/record_home.php") {
				$fetched = $_GET['id'];
			}
			$record_display = RCView::b(RCView::escape(isset($_GET['id']) ? $_GET['id'] : ''));

            // Replace any Smart Variable and prepend/append events and instances in Custom Record Label
            $custom_record_label_orig = $custom_record_label;
            if ($Proj->longitudinal) {
                $custom_record_label = LogicTester::logicPrependEventName($custom_record_label, (isset($_GET['event_id']) ? $Proj->getUniqueEventNames($_GET['event_id']) : $Proj->getUniqueEventNames($Proj->firstEventId)), $Proj);
            }
            $custom_record_label = Piping::pipeSpecialTags($custom_record_label, $Proj->project_id, $_GET['id'], $_GET['event_id']??$Proj->firstEventId, $_GET['instance'], USERID, false, null, $_GET['page']??null, false);
            if ($Proj->hasRepeatingFormsEvents()) {
                $custom_record_label = LogicTester::logicAppendInstance($custom_record_label, $Proj, $_GET['event_id']??$Proj->firstEventId, $_GET['page']??null, $_GET['instance']);
            }
			// Get Custom Record Label and Secondary Unique Field values (if applicable)
			$this_custom_record_label_secondary_pk = Records::getCustomRecordLabelsSecondaryFieldAllRecords(addDDEending($fetched), false, getArm(), true);
            $custom_record_label = $custom_record_label_orig; // Reset back to original
			if ($this_custom_record_label_secondary_pk != '') {
				$record_display2 = "&nbsp; $this_custom_record_label_secondary_pk";
			} else {
				$record_display2 = "";
			}

			// DISPLAY RECORD NAME: Set full string for record name with prepended label (e.g., Study ID 202)
			//if ($longitudinal || isDev()) {
				// Longitudinal project: Display record name as link and "select other record" link
				$record_label = RCView::div(array('style'=>'padding:0 0 4px;color:#800000;font-size:12px;line-height:1.2;'),
									RCView::div(array('style'=>'float:left;'),
										'<i class="fas fa-columns" style="margin-right:1px;"></i> ' .
										RCView::a(array('id'=>'record-home-link', 'style'=>'text-decoration:underline;','href'=>APP_PATH_WEBROOT."DataEntry/record_home.php?pid=$project_id&id=$fetched&arm=".getArm().(isset($_GET['auto']) ? "&auto=1" : "")),
											RCView::span(array(
													"data-mlm" => "record-id-label",
													"data-mlm-field" => $table_pk,
													"data-mlm-type" => "label",
												), 
												strip_tags(label_decode($table_pk_label))
											) . " " . $record_display
										) .
										$record_display2
									) .
									RCView::div(array('style'=>'float:right;'),
										RCView::a(array('id'=>'menuLnkChooseOtherRec','class'=>'opacity65','href'=>APP_PATH_WEBROOT."DataEntry/record_home.php?pid=$project_id"),
											RCView::tt("bottom_63")
										)
									) .
									RCView::div(array('class'=>'clear'), '')
								);
		}

		// Get event description for this event
		$event_label = "";
		if ($longitudinal && isset($_GET['event_id']) && is_numeric($_GET['event_id']))
		{
			// Get all repeating events
			$repeatingFormsEvents = $Proj->getRepeatingFormsEvents();
			// Add instance number if a repeating instance
			$is_repeating_event = (isset($repeatingFormsEvents[$_GET['event_id']]) && !is_array($repeatingFormsEvents[$_GET['event_id']]));
			$instanceNum = ($is_repeating_event) ? 
				RCView::tt_i("data_entry_519", array(
					$_GET['instance']
				), true, "span", array(
					"style" => "color:#800000;margin-left:3px;"
				)) : "";
			// Display event name
			$event_label = "<div style='padding:1px 0 5px;'>
								".RCView::tt("bottom_23")."&nbsp;".
								RCView::span(array(
									"style" => "color:#800000;font-weight:bold;",
									"data-mlm" => "event-name",
									"data-mlm-name" => $_GET["event_id"],
								), RCView::escape(strip_tags($Proj->eventInfo[$_GET['event_id']]['name_ext'])))."
								$instanceNum
							</div>";
		}

		$dataEntry .=  "<div class='menuboxsub' style='margin:8px 0 0;border-top:1px dashed #aaa;padding-top:5px;font-size:10px;'>
							$record_label
							$event_label
							" . (PAGE == "DataEntry/index.php" ? RCView::tt("global_238") : "") . "
						</div>";
	}

	// CLASSIC Only: Allow users to view the instruments without being in record context (legacy feature - now hidden by default)
	if (!$longitudinal && !(isset($fetched) && (PAGE == "DataEntry/index.php" || PAGE == "DataEntry/record_home.php")))
	{
		$showFormsList = (UIState::getUIStateValue(PROJECT_ID, 'sidebar', 'show-instruments-toggle') == '1');
		$hideFormsClass = $showFormsList ? '' : 'hidden';
		$showFormsClass = $showFormsList ? 'hidden' : '';
		$dataEntry .=  "<div style='margin-top:3px;'>
						<a class='show-instruments-toggle $hideFormsClass' onclick=\"showInstrumentsToggle(this,1);\" href='javascript:;'>".RCView::tt("global_136")." <span class='dropup'><span class='caret'></span></span></a>
						<a class='show-instruments-toggle $showFormsClass' onclick=\"showInstrumentsToggle(this,0);\" href='javascript:;'>".RCView::tt("global_135")." <span class='caret'></span></a>
						</div>";
	}

	## Render the form list for this project
	$dataEntry .= Form::renderFormMenuList($fetched, $hidden_edit);

	## LOCK / UNLOCK ENTIRE RECORDS
	//If user has ability to lock a record, give option to lock it for all forms (if record is pulled up on data entry page)
	if ($user_rights['lock_record_multiform'] && PAGE == "DataEntry/index.php" && isset($fetched))
	{
		//Adjust if double data entry for display in pop-up
		if ($double_data_entry && $user_rights['double_data'] != '0') {
			$fetched2 = $fetched . '--' . $user_rights['double_data'];
		//Normal
		} else {
			$fetched2 = $fetched;
		}
		// Is whole record locked?
		$locking = new Locking();
		$wholeRecordIsLocked = $locking->isWholeRecordLocked($project_id, addDDEending($fetched), getArm());
		//Show link "Lock entire record"
		if (!$wholeRecordIsLocked && $hidden_edit) {
			$dataEntry .=  "<div style='text-align:left;padding: 6px 0px 2px 0px;'>
								<a style='color:#A86700;font-size:12px' href='javascript:;' onclick=\"
									lockUnlockForms('".js_escape($fetched2)."','".js_escape($fetched)."','{$_GET['event_id']}','".getArm()."','0','lock');
									return false;
								\"><i class=\"fas fa-lock fs14 me-1\" style='color:#A86700;'></i>".RCView::tt("bottom_110")."</a>
							</div>";
		}
		//Show link "Unlock entire record"
		elseif ($wholeRecordIsLocked && $hidden_edit) {
			$dataEntry .=  "<div style='text-align:left;padding: 6px 0px 2px 0px;'>
								<a style='color:#666;font-size:12px' href='javascript:;' onclick=\"
									lockUnlockForms('".js_escape($fetched2)."','".js_escape($fetched)."','{$_GET['event_id']}','".getArm()."','0','unlock');
									return false;
								\"><i class=\"fas fa-lock-open fs14 me-1 opacity75\" style='color:#666;'></i>".RCView::tt("bottom_111")."</a>
							</div>";
		}
	}
    if ($record_locking_pdf_vault_enabled == '1' && $user_rights['lock_record_multiform'] && (PAGE == "DataEntry/index.php" || PAGE == "DataEntry/record_home.php") && isset($_GET['id']))
    {
		// PDF record-locking confirmation iframe
		Locking::renderRecordLockingPdfFrame($fetched);
	}

	$dataEntry .= "</div>";

    // Add JS language variables for locking/unlocking
    addLangToJS(array('global_49', 'data_entry_478', 'data_entry_479', 'create_project_97', 'data_entry_480', 'data_entry_481', 'data_entry_482', 'data_entry_483', 'global_53', 'questionmark', 'data_entry_484'));
}


/**
 * APPLICATIONS MENU
 * Show function links based on rights level (Don't allow designated Double Data Entry people to see pages displaying other user's data.)
 */
$menu_id = 'projMenuApplications';
$appsMenuCollapsed = UIState::getMenuCollapseState($project_id, $menu_id);
$appsMenuTitle = makePanelTitle($lang['bottom_25'], $menu_id, $appsMenuCollapsed);

$appsMenu = "<div class='menubox' style='padding-right:0;'>";
// Project Dashboards
if ($user_rights['design']) {
	$appsMenu .= "<div class='hang'>".RCView::img(array('src'=>'dashboard1.png', 'style'=>'margin-left:-2px;margin-right:1px;')) ."</i><a href='".APP_PATH_WEBROOT."index.php?pid=$project_id&route=ProjectDashController:index'>{$lang['global_182']}</a></div>";
}
// Alerts & Notifications
if ($user_rights['alerts']) {
	$appsMenu .= "<div class='hang'><i class=\"fas fa-bell\"></i> <a href='".APP_PATH_WEBROOT."index.php?pid=$project_id&route=AlertsController:setup'>{$lang['global_154']}</a></div>";
}
//Multi-Language Feature
if ($user_rights['design'] && MultiLanguage::showProjectMenuItem($project_id)) {
	$appsMenu .= "<div class=\"hang\"><i class=\"fas fa-globe\"></i> <a href=\"".APP_PATH_WEBROOT."index.php?pid={$project_id}&route=MultiLanguageController:projectSetup\">".RCView::tt("multilang_01")."</a>";
	if (MultiLanguage::isAdminDisabled($project_id)) {
		$appsMenu .= RCView::tt("global_23", "span", [
			"class" => "badge badge-danger",
			"style" => "margin-left:1em;text-transform:uppercase;"
		]); // Disabled
	}
	$appsMenu .= "</div>";
}
//Calendar
if ($user_rights['calendar']) {
	$appsMenu .= "<div class='hang'><i class=\"far fa-calendar-alt\"></i> <a href='".APP_PATH_WEBROOT."Calendar/index.php?pid=$project_id'>{$lang['app_08']}</a></div>";
}
// Data Exports, Reports, & Stats
if (isset($user_rights['data_export_tool']) && ($user_rights['reports'] || $user_rights['data_export_tool'] > 0 || ($enable_plotting > 0 && $user_rights['graphical']))) {
	$appsMenu .= "<div class='hang'><i class=\"fas fa-file-export\" style='margin-left:1px;'></i>&nbsp;<a href=\"" . APP_PATH_WEBROOT . "DataExport/index.php?pid=$project_id\">{$lang['app_23']}</a></div>";
}
//Data Import Tool
if (!($status == '2' && $data_locked) && $user_rights['data_import_tool']) {
	$appsMenu .= "<div class='hang'><i class=\"fas fa-file-import\" style='margin-right:2px;'></i>&nbsp;<a href=\"" . APP_PATH_WEBROOT . "index.php?pid=$project_id&route=DataImportController:index\">{$lang['app_01']}</a></div>";
}
//Data Comparison Tool
if (!($status == '2' && $data_locked) && $user_rights['data_comparison_tool']) {
	$appsMenu .= "<div class='hang'><i class=\"fas fa-not-equal\" style='margin-left:2px;'></i> <a href=\"" . APP_PATH_WEBROOT . "index.php?pid=$project_id&route=DataComparisonController:index\">{$lang['app_02']}</a></div>";
}
//Data Logging
if ($user_rights['data_logging'] == '1' || $user_rights['email_logging'] == '1') {
	$appsMenu .= "<div class='hang'>";
    if ($user_rights['data_logging']) {
		$appsMenu .= "<i class=\"fas fa-receipt\" style='margin-left:2px;'></i> <a href=\"" . APP_PATH_WEBROOT . "Logging/index.php?pid=$project_id\">" . $lang['app_07'] . "</a>";
	}
    // Email Logging (if enabled globally)
	if ($GLOBALS['email_logging_enable_global'] && $user_rights['email_logging'] == '1' && $user_rights['data_logging'] == '1') {
		$appsMenu .= RCView::span(array('style'=>'color:#777;margin:0 7px 0 6px;'), $lang['global_43']);
	}
	if ($GLOBALS['email_logging_enable_global'] && $user_rights['email_logging'] == '1') {
		$appsMenu .= "<i class=\"fas fa-mail-bulk\"></i> <a href=\"" . APP_PATH_WEBROOT . "index.php?route=EmailLoggingController:index&pid=$project_id\">".($Proj->project['twilio_enabled'] ? $lang['email_users_96'] : $lang['email_users_53'])."</a>";
	}
	$appsMenu .= "</div>";
}
// Field Comment Log
if ($data_resolution_enabled == '1') {
	$appsMenu .= "<div class='hang'><i class=\"fas fa-comments\" style='margin-left:2px;'></i> <a href=\"" . APP_PATH_WEBROOT . "DataQuality/field_comment_log.php?pid=$project_id\">{$lang['dataqueries_141']}</a></div>";
}
//File Repository
if ($user_rights['file_repository']) {
	$appsMenu .= "<div class='hang'><i class=\"fas fa-folder-open\" style='margin-left:2px;'></i> <a href=\"" . APP_PATH_WEBROOT . "index.php?pid=$project_id&route=FileRepositoryController:index\">{$lang['app_04']}</a></div>";
}
//User Rights
if ($user_rights['user_rights'] > 0 || $user_rights['data_access_groups']) {
	$appsMenu .= "<div class='hang'>";
	if ($user_rights['user_rights'] > 0) {
		$appsMenu .= "<i class=\"fas fa-user\" style='margin-left:2px;margin-right:1px;'></i> <a href=\"" . APP_PATH_WEBROOT . "UserRights/index.php?pid=$project_id\">{$lang['app_05']}</a>";
	}
	if ($user_rights['user_rights'] > 0 && $user_rights['data_access_groups']) {
		$appsMenu .= RCView::span(array('style'=>'color:#777;margin:0 7px 0 5px;'), $lang['global_43']);
	}
	if ($user_rights['data_access_groups']) {
		$appsMenu .= "<i class=\"fas fa-users\"></i> <a href=\"" . APP_PATH_WEBROOT . "index.php?route=DataAccessGroupsController:index&pid=$project_id\">{$lang['global_114']}</a>";
	}
	$appsMenu .= "</div>";
}
//Lock Record advanced setup
if ($user_rights['lock_record_customize'] > 0 || $user_rights['lock_record'] > 0) {
    $lockingMgmtPage = ($user_rights['lock_record_customize'] > 0) ? 'Locking/locking_customization.php' : 'Locking/esign_locking_management.php';
	$appsMenu .= "<div class='hang'><i class=\"fas fa-lock\" style='margin-left:2px;'></i> <a href=\"" . APP_PATH_WEBROOT . $lockingMgmtPage . "?pid=$project_id\">".$lang[($GLOBALS['esignature_enabled_global'] ? 'locking_36' : 'system_config_761')]."</a></div>";
}
// Randomization
if ($randomization && ($user_rights['random_setup'] || $user_rights['random_dashboard'])) {
	$rpage = ($user_rights['random_setup']) ? "index.php" : "dashboard.php";
	$appsMenu .= "<div class='hang'><i class=\"fas fa-random\" style='margin-left:2px;'></i> <a href=\"" . APP_PATH_WEBROOT . "Randomization/$rpage?pid=$project_id\">{$lang['app_21']}</a></div>";
}
// Data Quality
if ($user_rights['data_quality_design'] || $user_rights['data_quality_execute'] || ($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] > 0)) {
	$appsMenu .= "<div class='hang'>";
	if ($user_rights['data_quality_design'] || $user_rights['data_quality_execute']) {
		$appsMenu .= "<i class=\"fas fa-clipboard-check\" style='margin-left:2px;'></i> <a href=\"" . APP_PATH_WEBROOT . "DataQuality/index.php?pid=$project_id\">{$lang['app_20']}</a>";
	}
	if ($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] > 0) {
		$dqMenuSpace = "&nbsp;&nbsp;";
		if ($user_rights['data_quality_design'] || $user_rights['data_quality_execute']) {
			$appsMenu .= RCView::span(array('style'=>'color:#777;margin:0 4px;'), $lang['global_43']);
			$dqMenuSpace = "&nbsp;";
		}
		// Resolve Issues
		$appsMenu .= "<i class=\"fas fa-comments\"></i>$dqMenuSpace<a href=\"" . APP_PATH_WEBROOT . "DataQuality/resolve.php?pid=$project_id\">{$lang['dataqueries_148']}</a>";
	}
	$appsMenu .= "</div>";
}
// API
if ($api_enabled && ($user_rights['api_export'] || $user_rights['api_import'] || $user_rights['api_modules'])) {
	$appsMenu .= "<div class='hang'><i class=\"fas fa-laptop-code\" style='margin-right:2px;'></i>&nbsp;<a href=\"" . APP_PATH_WEBROOT . "API/project_api.php?pid=$project_id\">{$lang['setup_77']}</a>".
					RCView::span(array('style'=>'color:#777;margin:0 7px 0 5px;'), $lang['global_43']) .
					"<i class=\"fas fa-laptop-code\"></i> <a href=\"" . APP_PATH_WEBROOT . "API/playground.php?pid=$project_id\">{$lang['setup_143']}</a></div>";
}
// Mobile app
if ($mobile_app_enabled && $api_enabled && $user_rights['mobile_app'])
{
	$appsMenu .= "<div class='hang'><img src='" . APP_PATH_IMAGES . "phone_tablet.png' style='position:relative;top:-2px;margin-right:2px;'> <a href=\"" . APP_PATH_WEBROOT . "MobileApp/index.php?pid=$project_id\">{$lang['global_118']}</a></div>";
}
$appsMenu .= "</div>";




/*
 ** REPORTS
 */
//Check to see if custom reports are specified for this project. If so, print the appropriate links.
//Build menu item for each separate report
list ($reportsListTitle, $reportsListCollapsed) = DataExport::outputReportPanelTitle();
// Reports built in Reports & Exports module
$reportsList = DataExport::outputReportPanel();

/**
 * EXTERNAL MODULES MENU
 */
$menu_id = 'projMenuExternalModules';
$externalModulesMenuCollapsed = UIState::getMenuCollapseState($project_id, $menu_id);
$imgCollapsed = $externalModulesMenuCollapsed ? "toggle-expand.png" : "toggle-collapse.png";
$externalModulesMenuTitle =   "<div style='float:left;'>
						{$lang['global_142']}
					</div>
					<div class='opacity65 projMenuToggle' id='$menu_id'>"
					. RCView::a(array('href'=>'javascript:;'),
						RCView::img(array('src'=>$imgCollapsed))
					  ). "
					</div>"
					. ExternalModules::getMenuHeaderLinks($project_id);
$externalModulesMenu = "<div class='menubox' style='color:#444;'></div>";

/**
 * HELP MENU
 */
$menu_id = 'projMenuHelp';
$helpMenuCollapsed = UIState::getMenuCollapseState($project_id, $menu_id);
$helpMenuTitle = makePanelTitle($lang['bottom_42'], $menu_id, $helpMenuCollapsed);

$learnAdvDesignFeatures = "";
if ($user_rights['design']) {
    $learnAdvDesignFeatures = "
                <!-- Advanced Design Features -->
				<div class='hang'>
					<i class='fa-regular fa-lightbulb' style='font-size:13px;margin-right:3px;' aria-hidden='true'></i>
					<a href='javascript:;' onclick=\"
						$('#adv-design-features-menu').toggle('blind',{},250,
							function(){
								var objDiv = document.getElementById('west');
								objDiv.scrollTop = objDiv.scrollHeight;
							}
						);
					\">".$lang['bottom_116']."</a>
				</div>
				<div id='adv-design-features-menu' style='display:none;line-height:1.8;padding:10px 0 20px 5px;border-bottom:1px dashed #ccc;margin-bottom:5px;'>
					<div style='color:#666;font-size:12px;'>
                        {$lang['edit_project_186']}
                    </div>
                    <button class='btn btn-xs btn-rcgreen btn-rcgreen-light' style='margin-right:6px;font-size:11px;padding:0px 3px 1px;line-height:14px;'  onclick=\"smartVariableExplainPopup();return false;\">[<i class='fas fa-bolt fa-xs text-white' style='margin:0 1px;'></i>] {$lang['global_146']}</button>
                    <button class='btn btn-xs btn-rcpurple btn-rcpurple-light' style='margin-right:6px;font-size:11px;padding:0px 3px 1px;line-height: 14px;' onclick='pipingExplanation();return false;'><img src='".APP_PATH_IMAGES."pipe.png' style='width:12px;position:relative;top:-1px;margin-right:2px;'>{$lang['info_41']}</button>
                    <button class='btn btn-xs btn-rcred btn-rcred-light' onclick=\"actionTagExplainPopup(1);return false;\" style='line-height: 14px;padding:1px 3px;font-size:11px;margin-right:6px;'>@ {$lang['global_132']}</button>
                    <button class='btn btn-xs btn-rcyellow' style='font-size:11px;padding:1px 3px;line-height:14px;margin-right:6px;'  onclick=\"fieldEmbeddingExplanation();return false;\"><i class='fas fa-arrows-alt' style='margin:0 1px;'></i> {$lang['design_795']}</button>
                    <button class='btn btn-xs btn-primaryrc btn-primaryrc-light' style='font-size:11px;padding:1px 3px;line-height:14px;'  onclick=\"specialFunctionsExplanation();return false;\"><i class='fas fa-square-root-alt text-white' style='margin:0 2px 0 1px;'></i> {$lang['design_839']}</button>
                </div>";
}

$helpMenu = "<div class='menubox' style='color:#444;'>

				<!-- Help & FAQ -->
				<div class='hang'>
					<span class='fas fa-question-circle' style='font-size:13px;' aria-hidden='true'></span>&nbsp;
					<a href='javascript:;' onclick='helpPopup()'>".$lang['bottom_27']."</a>
				</div>

				<!-- Video Tutorials -->
				<div class='hang'>
					<span class='fas fa-film' style='font-size:13px;' aria-hidden='true'></span>&nbsp;
					<a href='".APP_PATH_WEBROOT_PARENT."index.php?action=training' target='_blank'>".$lang['bottom_28']."</a>
				</div>

				$learnAdvDesignFeatures

				<!-- Suggest a New Feature -->
				<div class='hang'>
					<span class='fas fa-share-square' style='font-size:13px;margin-right:2px;' aria-hidden='true'></span>
					<a target='_blank' href='https://redcap.vumc.org/enduser_survey_redirect.php?redcap_version=$redcap_version&server_name=".SERVER_NAME."'>".$lang['bottom_52']."</a>
				</div>

				<div style='padding-top:15px;'>
					<a target='_blank' href='$contactAdminBtnLink' class='btn-contact-admin btn btn-primaryrc btn-xs fs13' style='color:#fff;'><span class='fas fa-envelope' style='color:#fff !important;'></span> $contactAdminBtnText</a>
				</div>

			</div>";


function makeCDISPanel() {
	global $project_id, $lang;

    $builder = new CdisPanelBuilder($project_id, $lang);
    $menu_id = 'cdis_panel';
    $collapsed = UIState::getMenuCollapseState($project_id, $menu_id);
    $title = makePanelTitle($lang['ws_262'], $menu_id, $collapsed);
    $body = $builder->buildBody();

    return renderPanel($title, $body, $menu_id, $collapsed);
}

function makeDdpPanel() {
	global $project_id, $lang;
	// only visible to super users
	$isSuperUser = UserRights::isSuperUserNotImpersonator();
	if(!$isSuperUser) return '';

	$makeBody = function() use($project_id, $lang) {
		ob_start();
		?>
		<div class="menubox">

			<?php if(UserRights::isSuperUserNotImpersonator()): ?>
			<span class="d-block">
				<i class="fas fa-gauge-high"></i>
				<a href="<?= DynamicDataPull::getDashboardURL($project_id) ?>" ><?= $lang['cdp_dashboard_label_ddp'] ?></a>
			</span>
			<?php endif; ?>

		</div>
		<?php
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	};
	$menu_id = 'ddp_panel';
	$collapsed = UIState::getMenuCollapseState($project_id, $menu_id);
	$title = makePanelTitle($lang['ws_265'], $menu_id, $collapsed);
	$body = $makeBody();
	return renderPanel($title, $body, $menu_id, $collapsed);
}

/**
 * EXTERNAL PAGE LINKAGE
 */
if (defined("USERID") && defined("PROJECT_ID")) {
	$ExtRes = new ExternalLinks();
	$externalLinkage = $ExtRes->renderHtmlPanel();
}

// Build the HTML panels for the left-hand menu
// Make sure that 'pid' in URL is defined (otherwise, we shouldn't be including this file)
if (isset($_GET['pid']) && is_numeric($_GET['pid']))
{
	// PROJECT DASHBOARDS: Check to see if project dashboards are specified for this project. If so, print the appropriate links.
	$dashBoardOb = new ProjectDashboards();
	list ($dashboardsListTitle, $dashboardsListCollapsed) = $dashBoardOb->outputDashboardPanelTitle();
	$dashboardsList = $dashBoardOb->outputDashboardPanel();

	$westHtml = renderPanel('', $logoHtml)
              . renderPanel($homeSetupMenuTitle, $homeSetupMenu, 'home_setup_panel', $homeSetupMenuCollapsed)
			  . renderPanel((isset($dataEntryTitle) ? $dataEntryTitle : ''), (isset($dataEntry) ? $dataEntry : ''), 'data-collection-menu', $dataEntryCollapsed)
			  .renderPanel($appsMenuTitle, $appsMenu, 'app_panel', $appsMenuCollapsed);
	
	if( FhirEhr::isFhirEnabledInProject($project_id) ) {
		$westHtml .= makeCDISPanel();
	}
	$ddp1 = new DynamicDataPull($_GET['pid']);
	if( $ddp1->isEnabledInProject()) {
		$westHtml .= makeDdpPanel();
	}
	if ($externalLinkage != "") {
		$westHtml .= $externalLinkage;
	}
	if ($dashboardsList != "") {
		$westHtml .= renderPanel($dashboardsListTitle, $dashboardsList, 'dashboard_panel', $dashboardsListCollapsed);
	}
	if ($reportsList != "") {
		$westHtml .= renderPanel($reportsListTitle, $reportsList, 'report_panel', $reportsListCollapsed);
	}
	$westHtml .= renderPanel($externalModulesMenuTitle, $externalModulesMenu, 'external_modules_panel', $externalModulesMenuCollapsed);
	$westHtml .= renderPanel($helpMenuTitle, $helpMenu, 'help_panel', $helpMenuCollapsed);

}
else
{
	// Since no 'pid' is in URL, then give warning that header/footer will not display properly
	$westHtml = renderPanel("&nbsp;", "<div style='padding:20px 15px;'><img src='".APP_PATH_IMAGES."exclamation.png'> <b style='color:#800000;'>{$lang['bottom_54']}</b><br>{$lang['bottom_55']}</div>");
}

// EHR Portal: Display EHR navbar at top and hide project left-hand menu
$EhrEmbeddedPage = false;
/** @var SessionDTO $fhirSession */
$ehrLaunchContext = FhirLauncher::inEhrLaunchContext();
$fhirCookie = FhirLauncher::getFhirContextCookie();
$fhirSessionState = $fhirCookie->state;
$fhirSession = FhirLauncher::getSessionFromState($fhirSessionState);
if($ehrLaunchContext && ($fhirSession instanceOf SessionDTO) && ($patientData = @$fhirSession->patientData))
{
	$EhrEmbeddedPage = true;
	?>
	<style type="text/css">
	#west, #subheader { display: none !important; }
	#center { margin-top: 50px !important; }
	#formSaveTip { margin:50px 0 0 90px !important; }
	#dataEntryTopOptions { margin: 0 !important; }
	#dataEntryTopOptionsButtons { display:none !important; }
	</style>
	<script type="text/javascript">
	$(function(){
		$('#south').html('<?php print js_escape($lang['data_entry_390']) ?>').css({'color':'#777','font-size':'11px'});
	});
	</script>
	<?php
	$username = $fhirSession->user; 
	// $patientData = $fhirSession->patientData;
	$fhirUser = $fhirSession->fhirUser;
	$renderer = FhirRenderer::engine();
	$fhirNavbar = $renderer->render('partials/navbar.html.twig', [
		'session' => $fhirSession,
		'patientData' => $patientData,
		'username' => $username,
		'fhirUser' => $fhirUser,
	]);
	print($fhirNavbar);
}

if (defined("PROJECT_ID"))
{
    // show FHIR tools menu if project is using FHIR services
	$DDP2 = DynamicDataPull::forProject($project_id);
	if (
		$DDP2 instanceof DynamicDataPull
		&& FhirEhr::isFhirEnabledInProject($project_id)
		&& (
			// Is a Data Mart user
			($fhir_data_mart_create_project && DataMart::isEnabled($project_id))
			// Or is a CDP user with adjudication rights
			|| ( $realtime_webservice_type == 'FHIR' && $DDP2->userHasAdjudicationRights() )
		)
	) {
		echo FhirEhr::renderFhirLaunchModal();
	}

    // DAG Switcher
	$DAGSwitcher = new DAGSwitcher();
	$DagSwitcherBanner = $DAGSwitcher->renderUserDAGInfo();
}

/**
 * PAGE CONTENT
 */
?>
<!-- top navbar for mobile -->
<nav class="rcproject-navbar navbar navbar-light navbar-expand-md fixed-top" style="background-color:#f8f8f8;border-bottom:1px solid #e7e7e7;" role="navigation">
	<span class="navbar-brand" style="max-width:78%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-right:0;"><?php echo filter_tags($app_title) ?></span>
	<button type="button" class="navbar-toggler float-end" onclick="toggleProjectMenuMobile($('#west'))">
		<span class="navbar-toggler-icon"></span>
	</button>
</nav>
<!-- main window -->
<div class="container-fluid mainwindow">
	<?php print Messenger::renderMessenger() ?>
	<div id="mainwindowsub" style="flex-wrap:nowrap;-ms-flex-wrap:nowrap;" class="row row-offcanvas row-offcanvas-left <?php echo (($_SESSION["mc_open"] ?? false) == '1' && $user_messaging_enabled == 1 ? 'body-override' : '') ?>" data_open="0">
        <!-- left-hand menu -->
		<div id="west" class="d-print-none d-none d-md-block px-0" role="navigation">
			<?php echo $westHtml ?>
		</div>
        <!-- right window -->
		<div id="center" class="col">
			<?= UserRights::renderImpersonatingUserBanner() . $DagSwitcherBanner ?>
            <div id="subheader" class="d-print-none">
				<?php if ($display_project_logo_institution) { ?>
					<?php if (trim($headerlogo) != "")
						echo "<img src='".RCView::escape($headerlogo)."' title='".RCView::escape(strip_tags($institution))."' alt='".RCView::escape(strip_tags($institution))."' style='margin:-5px 0 5px 20px;max-width:700px; expression(this.width > 700 ? 700 : true);'>";
					?>
					<div id="subheaderDiv1" class="bot-left">
						<?php echo decode_filter_tags($institution) . (($site_org_type == "") ? "" : "<br><span style='font-size:12px;'>".decode_filter_tags($site_org_type)."</span>") ?>
					</div>
				<?php } ?>
				<?php if (defined("PROJECT_ID")) { ?><div id="subheaderDiv2" class="bot-left"><?php echo decode_filter_tags($app_title) ?><span class="browseProjPid fs11 opacity65 ms-4 d-print-none">PID <?=PROJECT_ID?></span></div><?php } ?>
			</div>

<?php if( FhirEhr::isFhirEnabledInProject($project_id) ): ?>
<!-- CDIS messaging system -->
<style>
	@import url('<?=APP_PATH_JS?>vue/components/dist/style.css');
</style>
<script type="module">
import { ParcelBadge } from '<?= getJSpath('vue/components/dist/lib.es.js') ?>'

ParcelBadge('#parcel-link')

</script>
<!-- CDIS messaging system -->
<?php endif; ?>

<script type="text/javascript">
    var removeMyCapEMLink = '<?=(MyCap::isMyCapSetupExists() ? 1 : 0)?>';
</script>
<?php
if ($draft_mode == "1") {
	print RCView::script(<<<END
		$('#draft-preview-status [data-bs-toggle="tooltip"]').each(function() {
			new bootstrap.Tooltip(this, {
					trigger: 'hover',
					html: true,
					placement: 'bottom',
					delay: { "show": 300, "hide": 0 },
					customClass: 'draft-preview-tooltip'
				});
		});
	END);
	print RCView::style(<<<END
		.draft-preview-tooltip {
			--bs-tooltip-bg: #242924;
		}
	END);
}
// Get current modules enabled in project
$enabledModules = \ExternalModules\ExternalModules::getEnabledModules(PROJECT_ID);
if (isset($enabledModules['mycap'])) {
    $myCapSettings = MyCap::getMetaDataProjectSettings(PROJECT_ID);
    if (!empty($myCapSettings['data']))
    {
        addLangToJS(array('global_79', 'global_01', 'mycap_mobile_app_540', 'mycap_mobile_app_541'));
        ?>
        <script type="text/javascript">
            // Place button to migrate data from MyCap EM to REDCap core
            $(function() {
                $("#external_modules_panel").find("a").each(function() {
                    var data_link_str = $(this).attr('data-link-key');
                    if (data_link_str != undefined) {
                        if(data_link_str.startsWith('mycap-')) {
                            placeMyCapMigrationButton($(this));
                        }
                    }
                });
            });
        </script>
        <?php
    }
}