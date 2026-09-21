<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

use Vanderbilt\REDCap\Classes\BreakTheGlass\GlassBreaker;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\Rewards\Utility\ActivationApprovalManager as RewardsActivationApprovalManager;

// If user does not have Project Setup/Design rights, do not show this page
if (!$user_rights['design']) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");

include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// TABS
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
// Render Utils
include APP_PATH_DOCROOT . "ProjectSetup/utils.php";

function renderRewardsSection(array $config): string {
	// Extract configuration values
	$rewards_enabled = $config['rewards_enabled'] ?? false;
	$smProjectSetupBtnStyle = $config['smProjectSetupBtnStyle'] ?? '';
	$moduleRewardsChecked = $config['moduleRewardsChecked'] ?? '';
	$moduleRewardsDisabled = $config['moduleRewardsDisabled'] ?? '';
	$requestStatus = $config['request_status'] ?? 'unknown';
	$pendingRequest = $config['pending_request'] ?? false;
	$nonAdminCanManageRewards = $config['nonAdminCanManageRewards'] ?? false;
	$isSuperUser = $config['isSuperUser'] ?? false;
	$lang = $config['lang'] ?? [];
	
	// Determine dynamic values based on rewards status
	$statusColor = $rewards_enabled ? 'green' : '#800000';
	$buttonText = $rewards_enabled ? ($lang['design_169'] ?? 'Enabled') : ($lang['survey_152'] ?? 'Disabled');
	// show pending request if the user cannot manage the option and the status is pending
	if(!$rewards_enabled && !$nonAdminCanManageRewards && !$isSuperUser && $pendingRequest === true) {
		$buttonText = $lang['control_center_4534'] ?? 'pending';
	}
	$iconClass = $rewards_enabled ? 'fa-check-circle' : 'fa-minus-circle';
	$rewardsTitle = $lang['rewards_project_setup_title'] ?? 'Rewards';
	$featureName = $lang['rewards_feature_name'] ?? 'Rewards Feature';
	$questionMark = $lang['questionmark'] ?? '?';
	$savedMessage = $lang['design_243'] ?? 'Saved';
	
	return <<<HTML
	<div style="{$smProjectSetupBtnStyle}margin-bottom:2px;color:{$statusColor};">
		<button id="enableRewardsBtn" class="btn btn-defaultrc btn-xs fs11" onclick="dialogRewardsEnable();" 
			{$moduleRewardsChecked}="{$moduleRewardsChecked}" 
			{$moduleRewardsDisabled}="{$moduleRewardsDisabled}">
			{$buttonText}&nbsp;
		</button>
		<i class="ms-1 fas {$iconClass}" style="text-indent:0;"></i> 
		{$rewardsTitle}
		<a href="javascript:;" class="help" title="{$featureName}" 
			onclick="$.get(app_path_webroot+'Rewards/partials/rewards_info.php', {}, function(data) {
					$('#dialogRewardsExplain').html(data).dialog({
						width: 900, bgiframe: true, modal: true, open: function(){fitDialog(this)}, 
						buttons: { Close: function() { $(this).dialog('close'); } }
					});
				});">{$questionMark}</a>
		<span class="savedMsg">{$savedMessage}</span>
	</div>
	HTML;
}

// Display action messages when 'msg' in URL
if (isset($_GET['msg']) && !empty($_GET['msg']))
{
	// Defaults
	$msgAlign = "center";
	$msgClass = "green";
	$msgText  = "<b>{$lang['setup_08']}</b> {$lang['setup_09']}";
	$msgIcon  = "tick.png";
	$timeVisible = 7; //seconds
	$showMsgDiv = true;
	// Determine which message to display
	switch ($_GET['msg'])
	{
		// Created project
		case "newproject":
			$msgText  = "<b>{$lang['new_project_popup_02']}</b><br>{$lang['new_project_popup_03']}";
			$msgAlign = "left";
			$timeVisible = 10;
			break;
		// Copied project
		case "copiedproject":
			$msgText  = "<b>{$lang['new_project_popup_16']}</b><br>{$lang['new_project_popup_17']}";
			$msgAlign = "left";
			$timeVisible = 10;
			break;
		// Modified project info
		case "projectmodified":
			break;
		// Moved to production
		case "movetoprod":
			$msgText  = "<b>{$lang['setup_08']}</b> {$lang['setup_15']}";
			break;
		// Moved back to development
		case "movetodev":
			$msgText  = "<b>{$lang['setup_08']}</b> {$lang['setup_72']}";
			break;
		// Sent request to move to production
		case "request_movetoprod":
			$msgText  = "<b>{$lang['setup_08']}</b> {$lang['setup_16']}";
			break;
		// Set secondary id
		case "secondaryidset":
			$msgText  = "<b>{$lang['setup_08']}</b> {$lang['setup_17']}";
			break;
		// REset secondary id
		case "secondaryidreset":
			$msgText  = "<b>{$lang['setup_08']}</b> {$lang['setup_18']}";
			break;
		// Enable Twilio services
		case "twilio_enabled":
        case "mycap_enabled":
			$showMsgDiv = false;
			break;
		// Error (general)
		case "error":
			$msgText  = "<b>{$lang['global_64']}</b>";
			$msgClass = "red";
			$msgIcon   = "exclamation.png";
			break;
	}
	// Display message
	if ($showMsgDiv) {
		displayMsg($msgText, "actionMsg", $msgAlign, $msgClass, $msgIcon, $timeVisible, true);
	}

	## CUSTOM POP-UP MESSAGES
	// Enabled Twilio or Mosio services, so auto-open the SMS/Voice setup dialog
	if ($_GET['msg'] == 'twilio_enabled' || $_GET['msg'] == 'mosio_enabled') {
		?>
		<script type="text/javascript">
		$(function(){
            $('#actionMsg').remove();
			setTimeout(function(){
                Swal.fire({
                    html: '<?=RCView::tt_js($_GET['msg'] == 'twilio_enabled' ? 'survey_1553' : 'survey_1552')?>', icon: 'success', timer: 3000
                }).then(function(){
                    $('#setupChklist-twilio').effect('highlight',{},5000);
                    $('#twilioSetupOpenDialogSpan').attr('title', '<?php print js_escape($lang['survey_1531']) ?>').tooltip2({ tipClass: 'tooltip4left', position: 'center right' });
                    setTimeout(function(){
                        $('#twilioSetupOpenDialogSpan').trigger('mouseover');
                        $('#setupChklist-twilio').mouseover(function(){
                            $('#twilioSetupOpenDialogSpan').trigger('mouseout');
                        });
                    },800);
                });
			},500);
		});
		</script>
		<?php
	}
    // Enabled Reward Services, so display custom dialog
    elseif ($_GET['msg'] == 'rewards_enabled') {
        ?>
        <script type="text/javascript">
            $(function(){
                $('#actionMsg').remove();
                setTimeout(function(){
                    Swal.fire({
                        html: '<b><?=RCView::tt_js('rewards_info_enabled_confirmation1')?></b><br><?=RCView::tt_js('rewards_info_enabled_confirmation2')?>', icon: 'success', timer: 6000
                    }).then(function(){
                        displayPopover('rewards-link', '<b><?=RCView::tt_js('rewards_info_enabled_confirmation1')?></b><br><?=RCView::tt_js('rewards_info_enabled_confirmation3')?>', '', 'right', 8);
                    });
                },500);
            });
        </script>
        <?php
    }
	// If DRW was just enabled, then give pop-up dialog with detailed instructions
	elseif ($_GET['msg'] == 'data_resolution_enabled')
	{
		// Data resolution workflow instructions dialog pop-up
		print 	RCView::div(array('id'=>'drw_instruction_popup', 'class'=>'simpleDialog',
					'title'=>$lang['dataqueries_137'] . $lang['colon'] . " " . $lang['global_24']),
					// Msg that DRW was just enabled
					RCView::div(array('class'=>'green', 'style'=>'font-size:13px;'),
						RCView::img(array('src'=>'tick.png')) .
						RCView::b($lang['dataqueries_260']) . RCView::br() .$lang['dataqueries_261']
					) .
					DataQuality::renderDRWinstructions()
				);
		?>
		<script type="text/javascript">
		$(function(){
			setTimeout(function(){
				$('#drw_instruction_popup').dialog({ bgiframe: true, modal: true, width: 700,
					open: function(){ fitDialog(this); $(window).scrollTop(0); },
					buttons: [{
						text: '<?php echo js_escape($lang['calendar_popup_01']) ?>',
						click: function() { $(this).dialog("close"); }
					}]
				});
			},200);
		});
		</script>
		<?php
	}
    // If Data Mart project was just create, give special notices
    elseif ($_GET['msg'] == 'newproject' && (DataMart::isEnabledInSystem() && DataMart::isEnabled($project_id)))
    {
        ?>
        <script type="text/javascript">
            $(function(){
                $('#data-mart-menu-link').popover({
                    'placement':'right',
                    'content':'<?php echo js_escape($lang['data_mart_refresh_014']) ?>',
                    'title':'<?php echo js_escape($lang['data_mart_refresh_013']) ?>'
                }).popover('show');
            });
        </script>
        <?php
    }
    // If MyCap was just enabled, then give pop-up dialog with detailed instructions
    elseif ($_GET['msg'] == 'mycap_enabled')
    {
        // Data resolution workflow instructions dialog pop-up
        print 	RCView::div(array('id'=>'mycap_instruction_popup', 'class'=>'simpleDialog',
            'title'=>$lang['mycap_mobile_app_101'] . $lang['colon'] . " " . $lang['global_24']),
            // Msg that MyCap was just enabled
            RCView::div(array('class'=>'green', 'style'=>'font-size:13px;'),
                RCView::img(array('src'=>'tick.png')) .
                RCView::b($lang['mycap_mobile_app_544'])
            ).
            MyCap::getMyCapSetupInstructions());
        ?>
        <script type="text/javascript">
            $(function(){
                simpleDialog(null,null,'mycap_instruction_popup',800);
                fitDialog($('#mycap_instruction_popup'));
            });
        </script>
        <?php
    }
}









/**
 * CHECKLIST
 */

$checkList = array();
// Set disabled status for any buttons/checkboxes whose pages relate to the Design/Setup user rights
$disableBtn = ($user_rights['design'] ? "" : "disabled");
// Set disabled status for any buttons/checkboxes that should NOT be changed while in production
$disableProdBtn = (($status < 1 || UserRights::isSuperUserNotImpersonator()) ? "" : "disabled");
// Counter
$stepnum = 1;
// Set project creation timestamp as integer for use in log_event queries (to help reduce query time)
$creation_time_sql = ($creation_time == "") ? "" : "ts > ".str_replace(array('-',' ',':'), array('','',''), $creation_time)." and";
// Get all checklist items that have already been manually checked off by user. Store in array.
$checkedOff = array();
$q = db_query("select name from redcap_project_checklist where project_id = $project_id");
while ($row = db_fetch_assoc($q))
{
	$checkedOff[$row['name']] = true;
}
// Small CSS indentation fix
$smProjectSetupBtnStyle = "text-indent:-75px;margin-left:75px;";

$reqPendingText = '';
$requestSent = ToDoList::isMyCapEnableRequestPending(PROJECT_ID);

$myCapEnabled = $Proj->project['mycap_enabled'];

$numTasks = 0;
if ($myCapEnabled) {
    $numTasks = count($myCapProj->tasks);
}

// Set longitudinal button disabled if mycap is enabled
//$disableLongBtn = ($mycap_enabled_global && $myCapEnabled) ? "disabled" : "";
$disableLongBtn = "";
//$disableMyCapBtn = (!$repeatforms) ? "" : "disabled";
$disableMyCapBtn = "";
if (!empty($myCapSettings['data']) && isset($enabledModules['mycap']) && !MyCap::isMyCapSetupExists()) {
    if (UserRights::isSuperUserNotImpersonator()) {
        $enableMyCapFn = "showMyCapMigrationDialog('showWarning');";
    } else {
        $enableMyCapFn = "";
        $disableMyCapBtn = "disabled";
    }
    $showMigrationBtn = true;
} else {
    if (isset($enabledModules['mycap'])) {
        $enableMyCapFn = "";
        $disableMyCapBtn = "disabled";
    } else {
        $sendMyCapRequestTodoList = ((UserRights::isSuperUserNotImpersonator() || $mycap_enable_type == 'auto') ? '0' : '1');
        $enableMyCapFn = "enableMyCap(1,$sendMyCapRequestTodoList);";

        if ($status > 0 && $draft_mode == 0) {
            $enableMyCapFn = "cannotEnableMyCapAsInProdMode()";
            ?>
            <script type='text/javascript'>
                // Function to give error message if try to enable MyCap when project is on PROD and not in draft mode
                function cannotEnableMyCapAsInProdMode() {
                    simpleDialog('<?php echo js_escape($lang['mycap_mobile_app_841']) ?>','<?php echo js_escape($lang['global_48'].$lang['colon']." ".$lang['mycap_mobile_app_352']) ?>');
                }
            </script>
            <?php
        }
        $showMigrationBtn = false;
    }

}

if ($requestSent && !isset($_GET['enable_mycap'])) {
    $disableMyCapBtn = "disabled";
    $enableMyCapFn = "";
    $reqPendingText = '<div class="mycap-enable-req-pending">'.$lang['setup_144'].'</div>';
}
// MAIN PROJECT SETTINGS
$optionRepeatformsChecked = ($repeatforms) ? "checked" : "";
$optionSurveysChecked = ($surveys_enabled) ? "checked" : "";
$modifyProjectStatus = (isset($checkedOff['modify_project']) || $status > 0) ? 2 : 0;
$checkList[$stepnum++] = array("header" => $lang['setup_105'], "status" => $modifyProjectStatus, "name" => "modify_project",
	"text" =>   // If in production, give note regarding why options above are disabled
				RCView::div(array('style'=>'color:#777;font-size:11px;padding-bottom:3px;'.(($status > 0 && !UserRights::isSuperUserNotImpersonator()) ? '' : 'display:none;')), $lang['setup_106']) .
				// Use surveys?
				(!$enable_projecttype_singlesurveyforms ? '' :
				RCView::div(array('class'=>'clearfix', 'style'=>$smProjectSetupBtnStyle.'color:#800000;padding:2px 0;font-size:13px;color:'.($surveys_enabled ? 'green' : '#800000').';'),
					RCView::button(array('id'=>'setupEnableSurveysBtn','class'=>'btn btn-defaultrc btn-xs fs11','style'=>'','onclick'=>(($surveys_enabled && count($Proj->surveys) > 0) ? "confirmUndoEnableSurveys()" : "saveProjectSetting($(this),'surveys_enabled','1','0',1);"),$disableBtn=>$disableBtn,$disableProdBtn=>$disableProdBtn,$optionSurveysChecked=>$optionSurveysChecked),
						($surveys_enabled ? $lang['control_center_153'] : $lang['survey_152'] . RCView::SP)
					) .
                    '<i class="ms-1 fas '.($surveys_enabled ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
					$lang['setup_96'] .
					// Question pop-up
					RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"simpleDialog(null,null,'useSurveysDialog');"), $lang['questionmark']) .
					// Invisible "saved" msg
					RCView::span(array('class'=>'savedMsg'), $lang['design_243']) .
					// Video link
                    RCView::a(array('href'=>'javascript:;','onclick'=>"popupvid('redcap_survey_basics02.mp4')", 'class'=>'d-none d-md-block float-end me-2 fs12 nowrap', 'style'=>'text-decoration:underline;'), '<i class="fas fa-film me-1" style="text-indent:0;"></i>' .$lang['training_res_63'])
				)) .
				// Use longitudinal?
				RCView::div(array('style'=>$smProjectSetupBtnStyle.'color:#800000;padding:2px 0;font-size:13px;color:'.($repeatforms ? 'green' : '#800000').';'),
                    RCView::button(array('id'=>'setupLongiBtn','class'=>'btn btn-defaultrc btn-xs fs11','style'=>'','onclick'=>($longitudinal ? "confirmUndoLongitudinal()" : "saveProjectSetting($(this),'repeatforms','1','0',1);"),$disableBtn=>$disableBtn,$disableProdBtn=>$disableProdBtn,$disableLongBtn=>$disableLongBtn,$optionRepeatformsChecked=>$optionRepeatformsChecked),
						($repeatforms ? $lang['control_center_153'] : $lang['survey_152'] . RCView::SP)
					) .
                    '<i class="ms-1 fas '.($repeatforms ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
					$lang['setup_162'] .
					// Question pop-up
					RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"simpleDialog(null,null,'longiDialog',600);"), $lang['questionmark']) .
					// Invisible "saved" msg
					RCView::span(array('class'=>'savedMsg'), $lang['design_243'])
				) .
                (!$mycap_enabled_global ? '' :
                    // Use MyCap?
                    RCView::div(array('style'=>$smProjectSetupBtnStyle.'color:#800000;padding:2px 0;font-size:13px;color:'.($myCapEnabled ? 'green' : '#800000').';'),
                        RCView::button(array('id'=>'setupMyCapBtn','class'=>'btn btn-defaultrc btn-xs fs11','style'=>'','onclick'=>($myCapEnabled ? "confirmUndoEnableMyCap()" : $enableMyCapFn),$disableBtn=>$disableBtn,$disableProdBtn=>$disableProdBtn,$disableMyCapBtn=>$disableMyCapBtn),
                            ($myCapEnabled ? $lang['control_center_153'] : $lang['survey_152'] . RCView::SP)
                        ) .
                        '<i class="ms-1 fas '.($myCapEnabled ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
                        $lang['setup_196'] .
                        // Question pop-up
                        RCView::a(array('href'=>'javascript:;', 'class'=>'fs12 ms-3 nowrap', 'style'=>'text-decoration:underline;', 'onclick'=>"simpleDialog(null,null,'myCapDialog',1000);"), '<i class="fa-solid fa-mobile-screen-button fs13 mr-1" style="text-indent:0;position:relative;top:1px;"></i>'.$lang['mycap_mobile_app_670']) .
                        $reqPendingText
                    )
                ) .
				// Make Additional Customizations button
				RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs13','style'=>'margin-top:13px;',$disableBtn=>$disableBtn,'onclick'=>'displayEditProjPopup();'), $lang['setup_100'])
);


## Design your data collection instruments
$buildFieldsStatus = isset($checkedOff['design']) ? 2 : ($status > 0 ? 1 : 0);
// Set button
if ($user_rights['design'])
{
	$designBtn =   "{$lang['setup_44']}
					<a href='".APP_PATH_WEBROOT."index.php?route=PdfController:index&pid=$project_id'
						style='text-decoration:underline;color:#800000;'>{$lang['design_266']}</a>
					{$lang['global_46']}
					<a href='javascript:;' onclick='downloadDD(0,{$Proj->formsFromLibrary()});'
						style='text-decoration:underline;'>{$lang['design_119']} {$lang['global_09']}</a> ";
	if ($status > 0 && $draft_mode > 0) {
		$designBtn .=  "{$lang['global_46']}
						<a href='javascript:;' onclick='downloadDD(1,{$Proj->formsFromLibrary()});'
							style='text-decoration:underline;'>{$lang['design_121']} {$lang['global_09']} {$lang['design_122']}</a>";
	}
}
$designBtn .=  "<div class='chklistbtn' style='padding-top:10px;'>
					<span class='nowrap' style='line-height:24px;'>{$lang['setup_45']}&nbsp; <button $disableBtn class='btn btn-defaultrc btn-xs fs13' onclick=\"window.location.href=app_path_webroot+'Design/online_designer.php?pid=$project_id';\"><i class=\"fas fa-edit\"></i> {$lang['design_25']}</button></span>
					<span class='nowrap' style='line-height:24px;margin-right:30px;'>&nbsp;{$lang['global_47']}&nbsp; <button $disableBtn class='btn btn-defaultrc btn-xs fs13' style='white-space:nowrap;' onclick=\"window.location.href=app_path_webroot+'Design/data_dictionary_upload.php?pid=$project_id';\"><img src='".APP_PATH_IMAGES."xls2.png' style='position:relative;top:-1px;'> {$lang['global_09']}</button></span>
					".
					// Shared Library button
					((!$shared_library_enabled || ($status > 0 && $draft_mode == 0)) ? '' :
						"<span class='nowrap d-none d-sm-inline' style='font-size:11px;line-height:28px;'>
							{$lang['edit_project_185']}
							<button class='btn btn-defaultrc btn-xs fs11' style='color:#800000;margin-left:3px;padding:1px 5px 0;' onclick=\"$('form#browse_rsl').submit();\"><i class=\"fas fa-book-reader\" style='margin-right:4px;'></i>{$lang['design_37']}</button>
						</span>"
					).
					// Link to Check For Identifiers page
					(($status > 0 && $draft_mode == 0) ? '' :
						"<div class='d-none d-sm-block' style='font-size:12px;padding-top:12px;color:#666;'>
							{$lang['edit_project_55']}
							<a style='text-decoration:underline;font-size:12px;' href='".APP_PATH_WEBROOT."index.php?pid=$project_id&route=IdentifierCheckController:index'>{$lang['identifier_check_01']}</a> {$lang['edit_project_56']}
						</div>"
					) .
					"<div style='padding:".(($status > 0 && $draft_mode == 0) ? '12' : '8')."px 0px 2px;'>
						<span style='vertical-align:middle;color:#666;font-size:12px;margin-right:4px;'>
							{$lang['edit_project_186']}
						</span>
						<button class='btn btn-xs btn-rcgreen btn-rcgreen-light' style='margin-right:6px;font-size:11px;padding:0px 3px 1px;line-height:14px;'  onclick=\"smartVariableExplainPopup();return false;\">[<i class='fas fa-bolt fa-xs' style='margin:0 1px;'></i>] {$lang['global_146']}</button>
						<button class='btn btn-xs btn-rcpurple btn-rcpurple-light' style='margin-right:6px;font-size:11px;padding:0px 3px 1px;line-height: 14px;' onclick='pipingExplanation();return false;'><img src='".APP_PATH_IMAGES."pipe.png' style='width:12px;position:relative;top:-1px;margin-right:2px;'>{$lang['info_41']}</button>
						<button class='btn btn-xs btn-rcred btn-rcred-light' onclick=\"actionTagExplainPopup(1);return false;\" style='line-height: 14px;padding:1px 3px;font-size:11px;margin-right:6px;'>@ {$lang['global_132']}</button>
						<button class='btn btn-xs btn-rcyellow' style='font-size:11px;padding:1px 3px;line-height:14px;margin-right:6px;'  onclick=\"fieldEmbeddingExplanation();return false;\"><i class='fas fa-arrows-alt' style='margin:0 1px;'></i> {$lang['design_795']}</button>
    					<button class='btn btn-xs btn-primaryrc btn-primaryrc-light' style='font-size:11px;padding:1px 3px;line-height:14px;'  onclick=\"specialFunctionsExplanation();return false;\"><i class='fas fa-square-root-alt' style='margin:0 2px 0 1px;'></i> {$lang['design_839']}</button>
					</div>
				</div>";
if (!(!$shared_library_enabled || ($status > 0 && $draft_mode == 0))) {
	$designBtn .= SharedLibrary::renderBrowseLibraryForm();
}
// Survey + Forms
if ($surveys_enabled) {
	$checkList[$stepnum++] = array("header" => '<i class="fas fa-wrench"></i> '.$lang['setup_90'], "name"=>"design", "status" => $buildFieldsStatus,
		"text" =>  "{$lang['setup_29']} " . $lang['setup_91'] . " $designBtn"
	);
}
// Forms only
else {
	$checkList[$stepnum++] = array("header" => '<i class="fas fa-wrench"></i> '.$lang['setup_30'], "name"=>"design", "status" => $buildFieldsStatus,
		"text" =>  "{$lang['setup_31']}
					$designBtn"
	);
}


## Define My Events: For potentially longitudinal projects (may not have multiple events yet)
if ($repeatforms)
{
	$defineEvents_stepnum = $stepnum;
	$defineEventsStatusName = "define_events";
	if (isset($checkedOff[$defineEventsStatusName]) && $checkedOff[$defineEventsStatusName]) {
		$defineEventsStatus = 2;
	} else {
		$defineEventsStatus = (($status > 0 && !$enable_edit_prod_events) ? 2 : 1);
	}
	// Set button as disabled if in prod and not a super user
	$checkList[$stepnum++] = array("header" => '<i class="fas fa-wrench"></i> '.$lang['setup_33'], "name" => $defineEventsStatusName, "status" => $defineEventsStatus,
		"text" =>  "{$lang['setup_34']}
					<div class='chklistbtn'>
						{$lang['setup_45']}&nbsp; <button $disableBtn class='btn btn-defaultrc btn-xs fs13' onclick=\"window.location.href=app_path_webroot+'Design/define_events.php?pid=$project_id';\">{$lang['global_16']}</button>
						&nbsp;{$lang['global_47']}&nbsp; <button $disableBtn class='btn btn-defaultrc btn-xs fs13' onclick=\"window.location.href=app_path_webroot+'Design/designate_forms.php?pid=$project_id';\">{$lang['global_28']}</button>
					</div>"
	);
}


$sendTwilioRequestTodoList = ((UserRights::isSuperUserNotImpersonator() || $twilio_enabled_by_super_users_only == '0') ? '0' : '1');
// Do not need approval if already enabled and clicking on "Modify" button
if ($twilio_enabled && $twilio_account_sid != '')   $sendTwilioRequestTodoList = 0;

$enableTwilioFn = "dialogTwilioEnable($sendTwilioRequestTodoList);";

// MISCELLANEOUS MODULES (auto-numbering, randomization, scheduling, etc.)
$moduleAutoNumChecked = ($auto_inc_set) ? "checked" : "";
$moduleAutoNumDisabled = ($Proj->firstFormSurveyId == null && !$double_data_entry) ? "" : "disabled";
$moduleAutoNumClass = ($Proj->firstFormSurveyId == null && !$double_data_entry) ? "" : "opacity25";
$moduleRandChecked = ($randomization) ? "checked" : "";
$moduleTwilioChecked = ($twilio_enabled && $twilio_account_sid != '') ? "checked" : "";
$moduleTwilioDisabled = (UserRights::isSuperUserNotImpersonator() || in_array($twilio_enabled_by_super_users_only, ['0', '2'])) ? "" : "disabled";
if ($moduleTwilioDisabled == "" && $twilio_enabled && $mosio_api_key != '') $moduleTwilioDisabled = "disabled";
$moduleMosioChecked = ($twilio_enabled && $mosio_api_key != '') ? "checked" : "";
$moduleMosioDisabled = (UserRights::isSuperUserNotImpersonator() || in_array($mosio_enabled_by_super_users_only, ['0', '2'])) ? "" : "disabled";
if ($moduleMosioDisabled == "" && $twilio_enabled && $twilio_account_sid != '') $moduleMosioDisabled = "disabled";
$moduleSendgridChecked = ($sendgrid_enabled) ? "checked" : "";
$moduleSendgridDisabled = (UserRights::isSuperUserNotImpersonator() || !$sendgrid_enabled_by_super_users_only) ? "" : "disabled";
$moduleSchedChecked = ($repeatforms && $scheduling) ? "checked" : "";
$moduleSchedDisabled = ($repeatforms) ? "" : "disabled";
$moduleStatus = (isset($checkedOff['modules']) && $checkedOff['modules']) ? 2 : ($status > 0 ? 1 : "");
$moduleEmailFieldDisabled = "disabled";
if (UserRights::isSuperUserNotImpersonator() || $status == 0 || ($status > 0 && $survey_email_participant_field == '')) {
	$moduleEmailFieldDisabled = "";
}
$moduleEmailFieldChecked = ($survey_email_participant_field != '') ? "checked" : "";
$moduleDdpChecked = (DynamicDataPull::isEnabledInSystem() && DynamicDataPull::isEnabled($project_id)) ? "checked" : "";
$moduleDdpDisabled = (DynamicDataPull::isEnabledInSystem() && !UserRights::isSuperUserNotImpersonator()) ? "disabled" : "";
$moduleDdpFhirChecked = (DynamicDataPull::isEnabledInSystemFhir() && DynamicDataPull::isEnabledFhir($project_id)) ? "checked" : "";
$moduleDdpFhirDisabled = (DynamicDataPull::isEnabledInSystemFhir() && !UserRights::isSuperUserNotImpersonator()) ? "disabled" : "";
$displaySuperUserOnlySettings = (UserRights::isSuperUserNotImpersonator() && (DynamicDataPull::isEnabledInSystem() || DynamicDataPull::isEnabledInSystemFhir()
                                || ($twilio_enabled_global && $twilio_enabled_by_super_users_only == '1') || ($mosio_enabled_global && $mosio_enabled_by_super_users_only == '1')));
// REWARDS
$rewards_enabled = filter_var($rewards_enabled, FILTER_VALIDATE_BOOLEAN);
$rewardsApprovalManager = new RewardsActivationApprovalManager($project_id);
$moduleRewardsRequestStatus = $rewardsApprovalManager->getLastRequestStatus();
$moduleRewardsChecked = ($rewards_enabled) ? "checked" : "";

$nonAdminCanManageRewards = ($rewards_enable_type === 'auto' && $user_rights['design'] == 1);
$isSuperUser = UserRights::isSuperUserNotImpersonator();
$moduleRewardsDisabled = (
    (
        $moduleRewardsRequestStatus === 'pending' &&
        !$isSuperUser &&
        !$nonAdminCanManageRewards
    ) ||
    (
        $rewards_enabled === true &&
        !$isSuperUser &&
        !$nonAdminCanManageRewards
    )
) ? "disabled" : "";

$displaySuperUserOnlySettings = (
	UserRights::isSuperUserNotImpersonator()
	&& (
		DynamicDataPull::isEnabledInSystem()
		|| DynamicDataPull::isEnabledInSystemFhir()
    	|| ($twilio_enabled_global && $twilio_enabled_by_super_users_only)
		|| ($mosio_enabled_global && $mosio_enabled_by_super_users_only)
		|| ($rewards_enabled_global && $rewards_enabled_by_super_users_only)
	)
);
$moduleRepeatingInstanceChecked = ($Proj->hasRepeatingFormsEvents()) ? "checked" : "";
$disableBtnRepeating = (($status < 1 || UserRights::isSuperUserNotImpersonator() || $enable_edit_prod_repeating_setup) ? "" : "disabled");

$twilioRequestSent = ToDoList::isTwilioEnableRequestPending(PROJECT_ID);
if ($twilioRequestSent) {
    $moduleMosioDisabled = "disabled";
}
$disableTwilioBtn = $twilioReqPendingText = "";
if ($twilioRequestSent && !isset($_GET['enable_twilio'])) {
    $disableTwilioBtn = "disabled";
    $enableTwilioFn = "";
    $twilioReqPendingText = '<div class="twilio-enable-req-pending">'.$lang['setup_144'].'</div>';
}

$sendMosioRequestTodoList = ((UserRights::isSuperUserNotImpersonator() || $mosio_enabled_by_super_users_only == '0') ? '0' : '1');
// Do not need approval if already enabled and clicking on "Modify" button
if ($twilio_enabled && $mosio_api_key != '')   $sendMosioRequestTodoList = 0;

$enableMosioFn = "dialogMosioEnable($sendMosioRequestTodoList);";
$mosioRequestSent = ToDoList::isMosioEnableRequestPending(PROJECT_ID);
if ($mosioRequestSent) {
    $moduleTwilioDisabled = "disabled";
}
$disableMosioBtn = $mosioReqPendingText = "";
if ($mosioRequestSent && !isset($_GET['enable_mosio'])) {
    $disableMosioBtn = "disabled";
    $enableMosioFn = "";
    $mosioReqPendingText = '<div class="twilio-enable-req-pending">'.$lang['setup_144'].'</div>';
}

$checkList[$stepnum++] = array("header" => '<i class="fas fa-cubes fs14"></i> '.$lang['setup_95'], "status" => $moduleStatus, "name" => "modules",
	"text" =>   // If in production, give note regarding why options above are disabled
				RCView::div(array('style'=>'color:#777;font-size:11px;padding-bottom:3px;'.(($status > 0 && !UserRights::isSuperUserNotImpersonator()) ? '' : 'display:none;')), $lang['setup_106']) .
				// REPEATING FORMS AND EVENTS
				RCView::div(array('id'=>'enableRepeatingFormsEventsOption', 'class'=>'clearfix', 'style'=>$smProjectSetupBtnStyle.'margin-bottom:2px;color:'.($Proj->hasRepeatingFormsEvents() ? 'green' : '#800000').';'),
					RCView::button(array('id'=>'enableRepeatingFormsEventsBtn','class'=>'btn btn-defaultrc btn-xs fs11','style'=>'','onclick'=>"dialogRepeatingInstance();",
						$disableBtn=>$disableBtn,$disableBtnRepeating=>$disableBtnRepeating,$moduleRepeatingInstanceChecked=>$moduleRepeatingInstanceChecked),
						($Proj->hasRepeatingFormsEvents() ? $lang['design_169'] . RCView::SP : $lang['survey_152'] . RCView::SP)
					) .
                    '<i class="ms-1 fas '.($Proj->hasRepeatingFormsEvents() ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
					($longitudinal ? $lang['setup_146'] : $lang['setup_145']) .
					// Tell Me More link
					RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"$.get(app_path_webroot+'ProjectSetup/repeating_instruments_events_info.php',{ },function(data){ $('#dialogRepeatingInstanceExplain').html(data).dialog({ width: 800, bgiframe: true, modal: true, open: function(){fitDialog(this)}, buttons: { Close: function() { $(this).dialog('close'); } } }); });"), $lang['questionmark']) .
					// Invisible "saved" msg
					RCView::span(array('class'=>'savedMsg'), $lang['design_243'])
                ) .
				// AUTO-NUMBERING FOR RECORDS
				RCView::div(array('style'=>$smProjectSetupBtnStyle.'margin-bottom:2px;color:'.($auto_inc_set ? 'green' : '#800000').';'),
					RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11','style'=>'','onclick'=>"saveProjectSetting($(this),'auto_inc_set','1','0',1,'setupChklist-modules');",$disableBtn=>$disableBtn,$disableProdBtn=>$disableProdBtn,$moduleAutoNumChecked=>$moduleAutoNumChecked, $moduleAutoNumDisabled=>$moduleAutoNumDisabled),
						($auto_inc_set ? $lang['control_center_153'] : $lang['survey_152'] . RCView::SP)
					) .
                    '<i class="ms-1 fas '.($auto_inc_set ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
					$lang['setup_94'] .
					// Tell Me More link
					RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"simpleDialog(null,null,'autoNumDialog');"), $lang['questionmark']) .
					// Invisible "saved" msg
					RCView::span(array('class'=>'savedMsg'), $lang['design_243'])
				) .			
				// SCHEDULING MODULE
				RCView::div(array('style'=>$smProjectSetupBtnStyle.'margin-bottom:2px;color:'.(($repeatforms && $scheduling) ? 'green' : '#800000').';'),
					RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11','style'=>'','onclick'=>"saveProjectSetting($(this),'scheduling','1','0',1,'setupChklist-modules');",$disableBtn=>$disableBtn,$disableProdBtn=>$disableProdBtn,$moduleSchedChecked=>$moduleSchedChecked, $moduleSchedDisabled=>$moduleSchedDisabled),
						(($repeatforms && $scheduling) ? $lang['control_center_153'] : $lang['survey_152'] . RCView::SP)
					) .
                    '<i class="ms-1 fas '.(($repeatforms && $scheduling) ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
					$lang['define_events_19'] . RCView::SP . $lang['setup_97'] .
					// Tell Me More link
					RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"simpleDialog(null,null,'schedDialog');"), $lang['questionmark']) .
					// Invisible "saved" msg
					RCView::span(array('class'=>'savedMsg'), $lang['design_243'])
				) .
				// Randomization module
				(!$randomization_global ? '' :
					RCView::div(array('style'=>$smProjectSetupBtnStyle.'margin-bottom:2px;color:'.($randomization ? 'green' : '#800000').';'),
						RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11','style'=>'','onclick'=>"saveProjectSetting($(this),'randomization','1','0',1,'setupChklist-modules');",$disableBtn=>$disableBtn,$disableProdBtn=>$disableProdBtn,$moduleRandChecked=>$moduleRandChecked),
							($randomization ? $lang['control_center_153'] : $lang['survey_152'] . RCView::SP)
						) .
                        '<i class="ms-1 fas '.($randomization ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
						$lang['setup_98'] .
						// Tell Me More link
						RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"simpleDialog(null,null,'randDialog');"), $lang['questionmark']) .
						// Invisible "saved" msg
						RCView::span(array('class'=>'savedMsg'), $lang['design_243'])
					)
				) .
				// Additional email field for survey invitations
				RCView::div(array('style'=>$smProjectSetupBtnStyle.'margin-bottom:2px;color:'.($survey_email_participant_field != '' ? 'green' : '#800000').';'),
					RCView::button(array('id'=>'enableSurveyPartEmailFieldBtn','class'=>'btn btn-defaultrc btn-xs fs11','style'=>'','onclick'=>"dialogSurveyEmailField(".($survey_email_participant_field != '' ? '0' : '1').");",$disableBtn=>$disableBtn,$moduleEmailFieldDisabled=>$moduleEmailFieldDisabled,$moduleEmailFieldChecked=>$moduleEmailFieldChecked),
						($survey_email_participant_field != '' ? $lang['control_center_153'] : $lang['survey_152'] . RCView::SP)
					) .
                    '<i class="ms-1 fas '.($survey_email_participant_field != '' ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
					$lang['setup_191'] .
					// Tell Me More link
					RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"simpleDialog(null,null,'surveyEmailFieldDialog',700);"), $lang['questionmark']) .
					// Invisible "saved" msg
					RCView::span(array('class'=>'savedMsg'), $lang['design_243']) .
					// If email field is already designated, then list it here as informational info
					($survey_email_participant_field == '' ? '' :
						RCView::div(array('style'=>'padding-left:82px;color:#666;text-overflow:ellipsis;white-space:nowrap;overflow:hidden;max-width:500px;'),
							$lang['setup_121'] . " <b>$survey_email_participant_field</b>&nbsp; (\"<i>".trim(strip_tags($Proj->metadata[$survey_email_participant_field]['element_label']))."</i>\")"
						)
					)
				) .
				//
				(!$displaySuperUserOnlySettings ? '' :
				// Make Additional Customizations button
					RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs13','style'=>'margin-top:10px;',$disableBtn=>$disableBtn,'onclick'=>'displayCustomizeProjPopup();'), 
						'<i class="ms-1 fas fa-external-link-alt"></i> '.$lang['setup_104']
					) .
					RCView::div(array('style'=>'margin:12px 0 4px;color:#555;font-size:11px;'),
						$lang['edit_project_156']
					)
				) .
				// DYNAMIC DATA PULL (DDP) - only display for super users OR if a normal user in which the global setting to display this is set to 1
				(!(DynamicDataPull::isEnabledInSystem() && (UserRights::isSuperUserNotImpersonator() || (!UserRights::isSuperUserNotImpersonator() && $realtime_webservice_display_info_project_setup))) ? '' :
					RCView::div(array('style'=>$smProjectSetupBtnStyle.'margin-bottom:2px;color:'.(DynamicDataPull::isEnabled($project_id) ? 'green' : '#800000').';'),
						RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11','style'=>'',
							'onclick'=>(UserRights::isSuperUserNotImpersonator() ? "saveProjectSetting($(this),'realtime_webservice_enabled','1','0',1,'setupChklist-modules');" : "ddpExplainDialog(0);"),
							$moduleDdpChecked=>$moduleDdpChecked,$moduleDdpDisabled=>$moduleDdpDisabled),
							(DynamicDataPull::isEnabled($project_id) ? $lang['control_center_153'] : $lang['survey_152'] . RCView::SP)
						) .
                        '<i class="ms-1 fas '.(DynamicDataPull::isEnabled($project_id) ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
						$lang['ws_51'] . " " . DynamicDataPull::getSourceSystemName(false) .
						// Tell Me More link
						RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"ddpExplainDialog(0);"), $lang['questionmark']) .
						// Invisible "saved" msg
						RCView::span(array('class'=>'savedMsg'), $lang['design_243'])
					)
				) .
				// DDP on FHIR - only display for super users OR if a normal user in which the global setting to display this is set to 1
				(!(DynamicDataPull::isEnabledInSystemFhir() && (UserRights::isSuperUserNotImpersonator() || (!UserRights::isSuperUserNotImpersonator() && $fhir_display_info_project_setup))) ? '' :
					RCView::div(array('style'=>$smProjectSetupBtnStyle.'margin-bottom:2px;color:'.(DynamicDataPull::isEnabledFhir($project_id) ? 'green' : '#800000').';'),
						RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11','style'=>'',
							'onclick'=>(UserRights::isSuperUserNotImpersonator() ? "saveProjectSetting($(this),'fhir_ddp_enabled','1','0',1,'setupChklist-modules');" : "ddpExplainDialog(1);"),
							$moduleDdpFhirChecked=>$moduleDdpFhirChecked,$moduleDdpFhirDisabled=>$moduleDdpFhirDisabled),
							(DynamicDataPull::isEnabledFhir($project_id) ? $lang['control_center_153'] : $lang['survey_152'] . RCView::SP)
						) .
                        '<i class="ms-1 fas '.(DynamicDataPull::isEnabledFhir($project_id) ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
						$lang['ws_210'] . " " . DynamicDataPull::getSourceSystemName(true) .
						// Tell Me More link
						RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"ddpExplainDialog(1, $project_id);"), $lang['questionmark']) .
						// Invisible "saved" msg
						RCView::span(array('class'=>'savedMsg'), $lang['design_243'])
					)
				) .
				// Twilio SMS/voice services (show only to super users unless it's enabled to advertise it in all projects)
                (!($twilio_enabled_global == 1 && (UserRights::isSuperUserNotImpersonator() || $twilio_display_info_project_setup || in_array($twilio_enabled_by_super_users_only, ['0', '2']))) ? '' :
					RCView::div(array('style'=>$smProjectSetupBtnStyle.'margin-bottom:2px;color:'.($twilio_enabled && $twilio_account_sid != '' ? 'green' : '#800000').';'),
						RCView::button(array('id'=>'enableTwilioBtn','class'=>'btn btn-defaultrc btn-xs fs11','style'=>'','onclick'=>$enableTwilioFn,
							$disableBtn=>$disableBtn,$disableProdBtn=>$disableProdBtn,$moduleTwilioDisabled=>$moduleTwilioDisabled,$disableTwilioBtn=>$disableTwilioBtn,$moduleTwilioChecked=>$moduleTwilioChecked),
							($twilio_enabled && $twilio_account_sid != '' ? $lang['design_169'] . RCView::SP : $lang['survey_152'] . RCView::SP)
						) .
                        '<i class="ml-1 fas '.($twilio_enabled && $twilio_account_sid != '' ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
						$lang['survey_1284'] .
						// Tell Me More link
						RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"$.get(app_path_webroot+'Surveys/twilio_info.php',{ },function(data){ $('#dialogTwilioExplain').html(data).dialog({ title: '".RCView::tt_js('survey_1284')."', width: 900, bgiframe: true, modal: true, open: function(){fitDialog(this)}, buttons: { Close: function() { $(this).dialog('close'); } } }); });"), $lang['questionmark']) .
                        $twilioReqPendingText .
						// Invisible "saved" msg
						RCView::span(array('class'=>'savedMsg'), $lang['design_243'])
					)
				) .
				// Mosio SMS services (show only to super users unless it's enabled to advertise it in all projects)
				(!($mosio_enabled_global && (UserRights::isSuperUserNotImpersonator() || $mosio_display_info_project_setup || in_array($mosio_enabled_by_super_users_only, ['0', '2']))) ? '' :
					RCView::div(array('style'=>$smProjectSetupBtnStyle.'margin-bottom:2px;color:'.($twilio_enabled && $mosio_api_key != '' ? 'green' : '#800000').';'),
						RCView::button(array('id'=>'enableMosioBtn','class'=>'btn btn-defaultrc btn-xs fs11','style'=>'','onclick'=>$enableMosioFn,
							$disableBtn=>$disableBtn,$disableProdBtn=>$disableProdBtn,$moduleMosioDisabled=>$moduleMosioDisabled,$disableMosioBtn=>$disableMosioBtn,$moduleMosioChecked=>$moduleMosioChecked),
							($twilio_enabled && $mosio_api_key != '' ? $lang['design_169'] . RCView::SP : $lang['survey_152'] . RCView::SP)
						) .
                        '<i class="ml-1 fas '.($twilio_enabled && $mosio_api_key != '' ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
						$lang['survey_1526'] .
						// Tell Me More link
						RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"$.get(app_path_webroot+'Surveys/mosio_info.php',{ },function(data){ $('#dialogTwilioExplain').html(data).dialog({ title: '".RCView::tt_js('survey_1526')."', width: 900, bgiframe: true, modal: true, open: function(){fitDialog(this)}, buttons: { Close: function() { $(this).dialog('close'); } } }); });"), $lang['questionmark']) .
                        $mosioReqPendingText .
                        // Invisible "saved" msg
						RCView::span(array('class'=>'savedMsg'), $lang['design_243'])
					)
				) .
                // SendGrid Alerts & Notifications settings (show only to super users unless it's enabled to advertise it in all projects)
                (!($sendgrid_enabled_global && (UserRights::isSuperUserNotImpersonator() || $sendgrid_display_info_project_setup || !$sendgrid_enabled_by_super_users_only)) ? '' :
                    RCView::div(array('style'=>$smProjectSetupBtnStyle.'margin-bottom:2px;color:'.($sendgrid_enabled ? 'green' : '#800000').';'),
                        RCView::button(array('id'=>'enableSendgridBtn','class'=>'btn btn-defaultrc btn-xs fs11','style'=>'','onclick'=>"dialogSendgridEnable();",
                            $disableBtn=>$disableBtn,$disableProdBtn=>$disableProdBtn,$moduleSendgridDisabled=>$moduleSendgridDisabled,$moduleSendgridChecked=>$moduleSendgridChecked),
                            ($sendgrid_enabled ? $lang['design_169'] . RCView::SP : $lang['survey_152'] . RCView::SP)
                        ) .
                        '<i class="ms-1 fas '.($sendgrid_enabled ? 'fa-check-circle' : 'fa-minus-circle').'" style="text-indent:0;"></i> ' .
                        $lang['survey_1387'] .
                        // Tell Me More link
                        RCView::a(array('href'=>'javascript:;','class'=>'help','title'=>$lang['global_58'],'onclick'=>"$.get(app_path_webroot+'Surveys/sendgrid_info.php',{ },function(data){ $('#dialogSendgridExplain').html(data).dialog({ width: 900, bgiframe: true, modal: true, open: function(){fitDialog(this)}, buttons: { Close: function() { $(this).dialog('close'); } } }); });"), $lang['questionmark']) .
                        // Invisible "saved" msg
                        RCView::span(array('class'=>'savedMsg'), $lang['design_243'])
                    )
                ) .
				// Rewards
                // (!($rewards_enabled_global && (UserRights::isSuperUserNotImpersonator() || $rewards_display_info_project_setup || !$rewards_enabled_by_super_users_only)) ? '' :
                (!($rewards_enabled_global) ? '' :
                    renderRewardsSection([
						'rewards_enabled' => $rewards_enabled,
						'smProjectSetupBtnStyle' => $smProjectSetupBtnStyle,
						'moduleRewardsChecked' => $moduleRewardsChecked,
						'moduleRewardsDisabled' => $moduleRewardsDisabled,
						'request_status' => $moduleRewardsRequestStatus,
						'pending_request' => $moduleRewardsRequestStatus === 'pending',
						'nonAdminCanManageRewards' => $nonAdminCanManageRewards,
						'isSuperUser' => $isSuperUser,
						'lang' => $lang,
					])
                ) .
                // Make Additional Customizations button
                ($displaySuperUserOnlySettings
                    ?
                    // Link to "Learn about Data Collection Strategies for Repeating Surveys"
                    RCView::div(array('class'=>'float-end fs11 text-secondary me-4 my-2'.($surveys_enabled && $Proj->hasRepeatingFormsEvents() ? '' : ' hide')),
                        "<i class='fas fa-info-circle' style='text-indent:0;'></i> {$lang['design_747']} <a href='javascript:;' onclick='repeatingSurveyExplainPopup();' style='text-decoration:underline;font-size:11px;'>{$lang['design_1026']}</a>"
                    )
                    :
					RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs13 mb-1','style'=>'margin-top:10px;',$disableBtn=>$disableBtn,'onclick'=>'displayCustomizeProjPopup();'), $lang['setup_104']) .
                    // Link to "Learn about Data Collection Strategies for Repeating Surveys"
                    RCView::div(array('class'=>'float-end fs11 text-secondary me-4 mt-3 mb-2'.($surveys_enabled && $Proj->hasRepeatingFormsEvents() ? '' : ' hide')),
                        "<i class='fas fa-info-circle' style='text-indent:0;'></i> {$lang['design_747']} <a href='javascript:;' onclick='repeatingSurveyExplainPopup();' style='text-decoration:underline;font-size:11px;'>{$lang['design_1026']}</a>"
                    )
				)
);

## Twilio SMS/voice services
if (($twilio_enabled_global || $mosio_enabled_global) && $twilio_enabled)
{
	$twilioServices_stepnum = $stepnum;
	// Check table to determine progress of twilio setup
	$twilioStatus = (isset($checkedOff['twilio']) && $checkedOff['twilio']) ? 2 : 0;
    // Set Twilio/Mosio specific text
    if ($Proj->messaging_provider == Messaging::PROVIDER_TWILIO) {
        // Twilio
        $twilioHdr = "<img src='".APP_PATH_IMAGES."twilio.png' style='vertical-align:middle;position:relative;top:-1px;'> <span id='twilioSetupOpenDialogSpan'>{$lang['survey_711']}</span>";
        $twilioInstr = $lang['survey_1285'];
    } else {
        // Mosio
        $twilioHdr = "<img src='".APP_PATH_IMAGES."mosio.png' style='vertical-align:middle;position:relative;top:-1px;height:16px;width:16px;'> <span id='twilioSetupOpenDialogSpan'>{$lang['dashboard_131']}</span>";
        $twilioInstr = $lang['survey_1530'];
    }
	// Set button as disabled if in prod and not a super user
	$checkList[$stepnum++] = array("header" => $twilioHdr,
		"name" => "twilio",
		"status" => $twilioStatus,
		"text" =>  "$twilioInstr
					<div class='chklistbtn'>
						{$lang['setup_45']}&nbsp; <button $disableBtn class='btn btn-defaultrc btn-xs fs13' onclick=\"dialogTwilioSetup();\"><i class=\"fas fa-cog\"></i> {$lang['survey_1529']}</button>
						&nbsp;{$lang['global_47']}&nbsp;
						<button $disableBtn class='btn btn-defaultrc btn-xs fs13' onclick=\"dialogTwilioAnalyzeSurveys();\"><i class=\"fas fa-shield-alt\"></i> ".($Proj->messaging_provider == Messaging::PROVIDER_TWILIO ? $lang['survey_869'] : $lang['survey_1532'])."</button>
					</div>"
	);
}

## DDP or CDP
if (is_object($DDP) && ((DynamicDataPull::isEnabledInSystem() && DynamicDataPull::isEnabled($project_id)) || (DynamicDataPull::isEnabledInSystemFhir() && DynamicDataPull::isEnabledFhir($project_id))))
{
	// Check table to determine progress of ddp setup
	$rtwsStatus = (isset($checkedOff['webservice']) && $checkedOff['webservice']) ? 2 : ($DDP->isMappingSetUp() ? 1 : 0);
	// Disable button if don't have mapping rights
	$disableDdpMappingBtn = ($DDP->userHasMappingRights()) ? '' : "disabled";
	// Set button as disabled if in prod and not a super user
	$checkList[$stepnum++] = array("header" => (DynamicDataPull::isEnabledFhir($project_id) ? '<i class="fas fa-database"></i> ' . $lang['ws_213'] . " " . $DDP->getSourceSystemName(true) : '<i class="fas fa-database"></i> ' . $lang['ws_26'] . " " . $DDP->getSourceSystemName(false)),
		"name" => "webservice",
		"status" => $rtwsStatus,
		"text" =>  ($realtime_webservice_type == 'FHIR' ? $lang['ws_286'] : $lang['ws_13'])." <a href='javascript:;' onclick='ddpExplainDialog(".($realtime_webservice_type == 'FHIR' ? 1 : 0).", $project_id);' style='text-decoration:underline;'>{$lang['global_58']}</a>
					<div class='chklistbtn'>
						{$lang['setup_45']}&nbsp; <button $disableDdpMappingBtn class='btn btn-defaultrc btn-xs fs13' onclick=\"window.location.href=app_path_webroot+'DynamicDataPull/setup.php?pid=$project_id';\">".($realtime_webservice_type == 'FHIR' ? $lang['ws_287'] : $lang['ws_29'])."</button>
					</div>"
	);
}


## MyCap
if ($mycap_enabled && $mycap_enabled_global)
{
    print RCView::simpleDialog(MyCap::getMyCapSetupInstructions(), $lang['mycap_mobile_app_640'], "mycap-instructions-dialog");
	// Check table to determine progress of randomization setup
	$mycapStatus = (isset($checkedOff['mycap']) && $checkedOff['mycap']) ? 2 : 0;
	// Set button as disabled if in prod and not a super user
	$checkList[$stepnum++] = array("header" => "<img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:24px;position:relative;top:-2px;'> ".$lang['mycap_mobile_app_631'],
		"name" => "mycap",
		"status" => $mycapStatus,
		"text" =>  "{$lang['mycap_mobile_app_878']}
					<div class='chklistbtn'>
						{$lang['setup_45']}&nbsp; <button class='btn btn-defaultrc btn-xs fs13' onclick=\"window.location.href=app_path_webroot+'MyCapMobileApp/index.php?about=1&pid=$project_id';\"><i class='fa-solid fa-mobile-screen-button fs15' style='position:relative;top:1px;'></i> {$lang['mycap_mobile_app_877']}</button>
						{$lang['global_47']}&nbsp;
						<button class='btn btn-defaultrc btn-xs fs13' onclick=\"simpleDialog(null,null,'mycap-instructions-dialog',800);fitDialog($('#mycap-instructions-dialog'));\"><i class=\"fa-solid fa-circle-info\"></i> {$lang['mycap_mobile_app_639']}</button>
					</div>"
	);
}


## Randomization
if ($randomization)
{
	// Check table to determine progress of randomization setup
	$randomizeStatus = (isset($checkedOff['randomization']) && $checkedOff['randomization'] || $status > 0) ? 2 : 0;
	if ($randomizeStatus < 2)
	{
		$sql = "select distinct r.rid, a.project_status from redcap_randomization r
				left outer join redcap_randomization_allocation a on r.rid = a.rid
				where r.project_id = $project_id";
		$q = db_query($sql);
		$randomizeStatus = db_num_rows($q);
	}
	$disableBtnRandomization = ($user_rights['random_setup'] || $user_rights['random_dashboard']) ? "" : "disabled";
	// Set button as disabled if in prod and not a super user
	$rpage = ($user_rights['random_setup']) ? "index.php" : "dashboard.php";
	$checkList[$stepnum++] = array("header" => '<i class="fas fa-random"></i> '.$lang['setup_81'],
		"name" => "randomization",
		"status" => $randomizeStatus,
		"text" =>  "{$lang['setup_82']}
					<div class='chklistbtn'>
						{$lang['setup_45']}&nbsp; <button $disableBtnRandomization class='btn btn-defaultrc btn-xs fs13' onclick=\"window.location.href=app_path_webroot+'Randomization/$rpage?pid=$project_id';\">{$lang['setup_83']}</button>
					</div>"
	);
}

## ------ DataMart ------ ##

if(UserRights::isSuperUserNotImpersonator() && (DataMart::isEnabledInSystem() && DataMart::isEnabled($project_id)))
{
	$printDataMartPanel = function() {
		global $lang, $Proj, $checkedOff;

		// get status of the panel
		$getStatus = function($checkedOff, $project_id) {
			if (isset($checkedOff['datamart']) && $checkedOff['datamart']) {
				$ExtResStatus = 2;
			} else {
				$sql = "select 1 from redcap_external_links where project_id = $project_id limit 1";
				$q = db_query($sql);
				$ExtResStatus = db_num_rows($q) ? 1 : "";
			}
			return $ExtResStatus;
		};

		$getFormattedDate = function($date_string, $format='Y-m-d') {
			if(empty($date_string)) return '';
			$time = strtotime($date_string);
			$new_format = date($format,$time);
			return $new_format;
		};
		$datamart_cron_end_date = $Proj->project['datamart_cron_end_date'] ?: '';

		
		$data = [
			'lang' => $lang,
			'datamart_allow_repeat_revision' => $Proj->project['datamart_allow_repeat_revision']==1,
			'datamart_allow_create_revision' => $Proj->project['datamart_allow_create_revision']==1,
			'datamart_cron_enabled' => $Proj->project['datamart_cron_enabled']==1,
			'datamart_cron_end_date' => $getFormattedDate($datamart_cron_end_date),
			'datamart_cron_end_date_alt' => $getFormattedDate($datamart_cron_end_date, 'm-d-Y'),
		];

		$renderer = Renderer::getBlade();
		$html = $renderer->run('project-setup.panels.datamart', $data);
		
		return array(
			"header" => "<i class=\"fas fa-shopping-cart\"></i> {$lang['data_mart_refresh_009']}",
			"status" => $getStatus($checkedOff, $Proj->project_id),
			"name" => "datamart",
			"text" => $html,
		);
	};
	$checkList[$stepnum++] = $printDataMartPanel();
}
## ------ DataMart ------ ##

## ------ break the glass ------ ##
if (UserRights::isSuperUserNotImpersonator() && GlassBreaker::isAvailable($project_id)) {
	
	function printBreakTheGlassPanel() {
		global $Proj, $lang, $project_id,
		$checkedOff, $smProjectSetupBtnStyle,
		$disableBtn, $disableProdBtn;
		
		$break_the_glass_enabled = $Proj->project['break_the_glass_enabled']==1;

		$moduleBreakTheGlassEnabledChecked = ($break_the_glass_enabled) ? "checked" : "";
	
			if (isset($checkedOff['break-the-glass']) && $checkedOff['break-the-glass']) {
				$ExtResStatus = 2;
			} else {
				$sql = "select 1 from redcap_external_links where project_id = $project_id limit 1";
				$q = db_query($sql);
				$ExtResStatus = db_num_rows($q) ? 1 : "";
			}
			/**
			 * get the javascript logic to be executed on the buttons
			 */
			$JSFunctions = function($key)
			{
				global $lang;
				$project_setting_name = 'break_the_glass_enabled';
				$title = $lang['project_setup_modal_title'];
				$body = $lang['project_setup_modal_body'];
				$okButton = $lang['control_center_153'];
				$functions = array(
					'enable' => $repeatJS = "saveProjectSetting($(this),'{$project_setting_name}','1','0',1)",
					'confirm_enable' => sprintf("modalDialog('%s', '%s', '%s').done(function(){%s})", $title, $body, $okButton, $repeatJS),
				);
				$text = array_key_exists($key, $functions) ? $functions[$key] : '';
				return $text;
			};
			$panelHTML = array(
				"header" => "<i class=\"fas fa-hammer\"></i> {$lang['break_glass_project_setup_enable_header']}", "status" => $ExtResStatus, "name" => "break-the-glass",
				"text" =>  "{$lang['break_glass_project_setup_enable_text']}".
							RCView::div(array('style'=>$smProjectSetupBtnStyle.'color:#800000;padding:5px 0 2px;font-size:13px;color:'.($break_the_glass_enabled ? 'green' : '#800000').';'),
								RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs11','style'=>'',
									'onclick'=>(($break_the_glass_enabled) ? $JSFunctions('confirm_enable') : $JSFunctions('enable')),
									$disableBtn=>$disableBtn,$disableProdBtn=>$disableProdBtn,$moduleBreakTheGlassEnabledChecked=>$moduleBreakTheGlassEnabledChecked),
									($break_the_glass_enabled ? $lang['control_center_153'] : $lang['survey_152'] . RCView::SP)
								) .
								RCView::i(array('class'=>($break_the_glass_enabled ? 'ms-1 fas fa-check-circle' : 'ms-1 fas fa-minus-circle'), 'style'=>'text-indent:0;')) . " " .
								$lang['break_glass_project_setup_enable_button_info'] .
								// Invisible "saved" msg
								RCView::span(array('class'=>'savedMsg'), $lang['design_243'])
							)
			);
			return $panelHTML;
		}
	
		$checkList[$stepnum++] = printBreakTheGlassPanel();
}
## ------ break the glass ------ ##


## Project Bookmarks
$ExtRes_stepnum = $stepnum;
if (isset($checkedOff['external_resources']) && $checkedOff['external_resources']) {
	$ExtResStatus = 2;
} else {
	$sql = "select 1 from redcap_external_links where project_id = $project_id limit 1";
	$q = db_query($sql);
	$ExtResStatus = db_num_rows($q) ? 1 : "";
}
$checkList[$stepnum++] = array("header" => "<i class=\"fas fa-bookmark\"></i> {$lang['setup_78']} {$lang['global_06']}", "status" => $ExtResStatus, "name" => "external_resources",
	"text" =>  "{$lang['setup_80']}
				<div class='chklistbtn'>
					{$lang['setup_45']}&nbsp;
					<button class='btn btn-defaultrc btn-xs fs13' $disableBtn onclick=\"window.location.href=app_path_webroot+'ExternalLinks/index.php?pid=$project_id';\">{$lang['setup_79']}</button>
				</div>"
);

## User Rights and DAGs
$dagText = $lang['setup_38'];
$dagBtn  = "&nbsp;{$lang['global_47']}&nbsp; <button class='btn btn-defaultrc btn-xs fs13' ".($user_rights['data_access_groups'] ? "" : "disabled")." onclick=\"window.location.href=app_path_webroot+'index.php?route=DataAccessGroupsController:index&pid=$project_id';\">{$lang['global_22']}</button>";
$userRights_stepnum = $stepnum;
$checkList[$stepnum++] = array("header" => '<i class="fas fa-user"></i> '.$lang['setup_39'], "status" => (isset($checkedOff['user_rights']) && $checkedOff['user_rights'] ? 2 : ""), "name" => "user_rights",
	"text" =>  "{$lang['setup_40']} $dagText
				<div class='chklistbtn'>
					{$lang['setup_45']}&nbsp;
					<button class='btn btn-defaultrc btn-xs fs13' ".($user_rights['user_rights'] == '1' ? "" : "disabled")." onclick=\"window.location.href=app_path_webroot+'UserRights/index.php?pid=$project_id';\">{$lang['app_05']}</button>
					$dagBtn
				</div>"
);

## Test your project
$checkList[$stepnum++] = array("header" => $lang['setup_123'], "status" => ((isset($checkedOff['test_project']) && $checkedOff['test_project']) ? 2 : 0),
	"name" => "test_project", "text" =>  $lang['setup_124']
);

## Move to production
// Check log_event table to see if they've sent a request before (if project requests have been enabled)
$todo_type = 'move to prod';
$request_count = ToDoList::checkIfRequestExist($project_id, UI_ID, $todo_type);
$moveToProdStatus = ($status > 0) ? 2 : ($superusers_only_move_to_prod && $request_count > 0 ? 1 : 0);
if($request_count > 0){
	$checkList[$stepnum++] = array("header" => $lang['setup_41'], "status" => $moveToProdStatus,
		"text" =>  "{$lang['setup_153']}
					<div class='chklistbtn' style='display:" . ($status > 0 ? "none" : "block") . ";'>
						{$lang['setup_45']}&nbsp; <button $disableBtn class='btn btn-defaultrc btn-xs fs13' style='color:rgba(0, 0, 0, 0.63);'>{$lang['setup_43']}</button>
						<b style='color:#C00000;margin-left:10px;'>{$lang['setup_144']} <button class='btn btn-defaultrc btn-xs fs13' onclick=\"cancelRequest(pid,'move to prod',".UI_ID.")\" class='cancel-req-btn'>{$lang['global_128']}</button></b>
					</div>"
	);
}else{
	$checkList[$stepnum++] = array("header" => $lang['setup_41'], "status" => $moveToProdStatus,
	"text" =>  "{$lang['setup_153']}
	<div class='chklistbtn' style='display:" . ($status > 0 ? "none" : "block") . ";'>
	{$lang['setup_45']}&nbsp; <button $disableBtn class='btn btn-defaultrc btn-xs fs13' onclick=\"btnMoveToProd();\">{$lang['setup_43']}</button>
	</div>"
);
}







## Show the PROJECT STATUS (and link to survey how-to video, if applicable)
// Project status label
$statusLabel = '<b style="color:#000;float:none;border:0;">'.$lang['edit_project_58'].'</b>&nbsp; ';
// Set icon/text for project status
if ($status == '1') {
	$iconstatus = '<span style="color:#00A000;font-weight:normal;">'.$statusLabel.'<i class="far fa-check-square"></i> '.$lang['global_30'].'</span>';
} elseif ($status == '2') {
	$iconstatus = '<span style="color:#A00000;font-weight:normal;">'.$statusLabel.'<i class="ms-1 fas fa-minus-circle"></i> '.$lang['global_159'].'</span>';
} else {
	$iconstatus = '<span style="color:#666;font-weight:normal;">'.$statusLabel.'<i class="ms-1 fas fa-wrench"></i> '.$lang['global_29'].'</span>';
}
// Determine how many steps have been completed thus far
// Only show Steps Completed text when in development
// $stepsCompletedText = "";
// if ($status < 1) {
	$stepsTotal = count($checkList);
	$stepsCompleted = 0;
	$doneStatuses = array('2', '4', '5'); // Status that denote that a step is "done"
	foreach ($checkList as $attr) {
		if (in_array($attr['status'], $doneStatuses)) $stepsCompleted++;
	}
	$stepsCompletedText = "<div style='color:#800000;margin-right:10px;'>
								{$lang['edit_project_120']} <b id='stepsCompleted'>$stepsCompleted</b> {$lang['survey_133']}
								<b id='stepsTotal'>$stepsTotal</b>
							</div>";
// }
// Output to page above checklist
print  "<div style='clear:both;padding-bottom:5px;max-width:700px;'>
			<table cellspacing=0 width=100%>
				<tr>
					<td valign='top'>
						$iconstatus
					</td>
					<td valign='top' style='text-align:right;'>
						$stepsCompletedText
					</td>
				</tr>
			</table>
		</div>
		<div style='max-width:800px;'>";

## RENDER THE CHECKLIST
ProjectSetup::renderSetupCheckList($checkList, $checkedOff);

print "</div>";


## HIDDEN DIALOG DIVS
// Repeating instance explanation - hidden dialog
print RCView::simpleDialog('', ($longitudinal ? $lang['setup_146'] : $lang['setup_145']), 'dialogRepeatingInstanceExplain');
// Twilio explanation - hidden dialog
print RCView::simpleDialog('', '', 'dialogTwilioExplain');
// SendGrid explanation - hidden dialog
print RCView::simpleDialog('', "SendGrid", 'dialogSendgridExplain');
// Rewards explanation - hidden dialog
print RCView::simpleDialog('', "Rewards", 'dialogRewardsExplain');
// Longitudinal enable - hidden dialog
print RCView::simpleDialog($lang['create_project_113'].RCView::div(array('style'=>'margin-top:15px;'), "<b>{$lang['create_project_114']}</b>{$lang['create_project_115']} <b>{$lang['create_project_116']}</b>"), $lang['setup_162'], 'longiDialog');
// Longitudinal pre-disable confirmation - hidden dialog
print RCView::simpleDialog($lang['setup_110'], $lang['setup_109'], 'longiConfirmDialog');
// Surveys enable - hidden dialog
print RCView::simpleDialog($lang['create_project_71'], $lang['setup_96'], 'useSurveysDialog');
// Surveys pre-disable confirmation - hidden dialog
print RCView::simpleDialog($lang['setup_112'], $lang['setup_111'], 'useSurveysConfirmDialog');
// Auto-numbering enable - hidden dialog
print RCView::simpleDialog($lang['edit_project_44'] .
	(($Proj->firstFormSurveyId != null && $auto_inc_set) ? RCView::div(array('style'=>'color:red;margin-top:10px;'), RCView::b($lang['global_03'].$lang['colon'])." ".$lang['setup_107']) : ''),
	$lang['edit_project_43'], 'autoNumDialog');
// Scheduling enable - hidden dialog
print RCView::simpleDialog($lang['create_project_54'] .
	(!$repeatforms ? RCView::div(array('style'=>'color:red;margin-top:10px;'), RCView::b($lang['global_03'].$lang['colon'])." ".$lang['setup_108']) : ''),
	$lang['define_events_19'], 'schedDialog');
// Randomization enable - hidden dialog
print RCView::simpleDialog($lang['random_01']."<br><br>".$lang['create_project_63'], $lang['setup_98'], 'randDialog');
// Survey email field enable - hidden dialog
print RCView::simpleDialog($lang['setup_192']."<br><br>".$lang['setup_122']."<br><br>".
		RCView::span(array('style'=>'color:#C00000;'), $lang['setup_133']) . RCView::br() . RCView::br() .
		RCView::b($lang['global_02'].$lang['colon']) . " " . $lang['setup_115'] . " " . $lang['setup_168'] . RCView::br() . RCView::br() .
		RCView::b($lang['setup_165'].$lang['colon']). " " . $lang['setup_171'] . " " . $lang['setup_169'] . " " . $lang['setup_172'], 
	  $lang['setup_191'], 'surveyEmailFieldDialog');
// Data Entry Trigger explanation - hidden dialog
print RCView::simpleDialog($lang['edit_project_160']."<br><br>".$lang['edit_project_128'] .
	RCView::div(array('style'=>'padding:12px 0 2px;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('project_id')." - ".$lang['edit_project_129']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('username')." - ".RCView::tt_i("edit_project_222", [System::SURVEY_RESPONDENT_USERID])).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('instrument')." - ".$lang['edit_project_130']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('record')." - ".$lang['edit_project_131'].$lang['period']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_event_name')." - ".$lang['edit_project_132']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_data_access_group')." - ".$lang['edit_project_133']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('[instrument]_complete')." - ".$lang['edit_project_134']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_repeat_instance')." - ".$lang['edit_project_181']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_repeat_instrument')." - ".$lang['edit_project_182']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_url')." - ".$lang['edit_project_144']."<br>i.e., ".APP_PATH_WEBROOT_FULL).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('project_url')." - ".$lang['edit_project_145']."<br>i.e., ".APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/index.php?pid=XXXX").
	RCView::div(array('style'=>'padding:20px 0 5px;color:#C00000;'), $lang['global_02'].$lang['colon'].' '.$lang['edit_project_135'])
	,$lang['edit_project_122'],'dataEntryTriggerDialog');

// MyCap enable - hidden dialog
if ($mycap_enabled_global)
{
	$appendAdminOnlyNotice = '';
	if (!SUPER_USER && $showMigrationBtn) {
		$appendAdminOnlyNotice = RCView::div(array('class' => 'red mt-3'), RCView::b($lang['global_03'] . $lang['colon']) . " " . $lang['setup_200']);
	}
	$mycapDlgContent = MyCap::getMyCapAboutInstructions() . $appendAdminOnlyNotice;
	print RCView::simpleDialog($mycapDlgContent, $lang['setup_193'], 'myCapDialog');
	// Disable MyCap Confirmation dialog
	print RCView::simpleDialog($lang['setup_195'], $lang['setup_194'], 'myCapConfirmDialog');
}





## MOVE TO PRODUCTION LOGIC
// Get randomization setup status (set to 0 by default for super users approving move-to-prod request, so they don't get prompt)
$randomizationStatus = ($randomization && !(isset($_GET['type']) && $_GET['type'] == "move_to_prod" && UserRights::isSuperUserNotImpersonator()) && Randomization::setupStatus()) ? '1' : '0';
$randProdAllocTableExists = ($randomizationStatus == '1' && Randomization::allocTableExists(1)) ? '1' : '0';
// Set up status-specific language and actions
$status_dialog_title = $lang['edit_project_09'];
$status_dialog_btn = $lang['setup_141'];
$type = isset($_GET['type']) ? $_GET['type'] : '';
$user_email = isset($_GET['user_email']) ? $_GET['user_email'] : $user_email;
$status_dialog_btn_action = "doChangeStatus(0,'{$type}','".RCView::escape($user_email)."',$randomizationStatus,$randProdAllocTableExists);";
$iconstatus = '<img src="'.APP_PATH_IMAGES.'page_white_edit.png"> <span style="color:#666;">'.$lang['global_29'].'</span>';
$recommendProtectedEmailMode = '';
if (Message::recommendProtectedEmailMode($project_id)) {
	$recommendProtectedEmailMode = "<div style='line-height:1.3;' class='gray my-4 fs12'>
                                        <div style='color:#800000;margin-bottom:5px;'><i class=\"fas fa-lightbulb\"></i> <b>{$lang['email_users_51']}</b></div>
                                        {$lang['email_users_52']}
                                    </div>";
}

$myCapNote = $myCapEnabled ? "<br>".$lang['mycap_mobile_app_707'] : "";

$status_dialog_text  = "{$lang['edit_project_178']}
						<div style='margin:15px 0;'>
							<img src='" . APP_PATH_IMAGES . "star.png' style='position:relative;top:-2px;'> {$lang['edit_project_55']}
							<a style='text-decoration:underline;' target='_blank' href='".APP_PATH_WEBROOT."index.php?pid=$project_id&route=IdentifierCheckController:index'>{$lang['identifier_check_01']}</a>
							{$lang['edit_project_56']}
						</div>
						$recommendProtectedEmailMode
						<fieldset class='yellow' data-test='".$super_user."'>
							<legend style='font-weight:bold;color:#A00000;font-size: 13px;padding: 0 5px;'>{$lang['edit_project_176']}</legend>
							<div style='padding:0 5px 5px 15px;color:#A00000;'>
								<div style='text-indent:-18px;margin-left:18px;'>
									<input type='radio' name='data' id='keep_data' "
										. ((isset($_GET['type']) && $_GET['type'] == "move_to_prod" && UserRights::isSuperUserNotImpersonator() && $_GET['delete_data'] == "0") ? "checked" : "") . ">
									<span style='cursor:pointer;' onclick=\"$('#keep_data').prop('checked',true);\">{$lang['edit_project_174']}</span>
									&nbsp;(<b style='color:#C00000;'>".Records::getRecordCount($project_id)." {$lang['data_entry_173']}</b>)
								</div>
								<div style='text-indent:-18px;margin-left:18px;margin-top:5px;'>
									<input type='radio' name='data' id='delete_data' "
										. ((isset($_GET['type']) && $_GET['type'] == "move_to_prod" && UserRights::isSuperUserNotImpersonator() && $_GET['delete_data'] == "1") ? "checked" : "") . ">
									<span style='cursor:pointer;' onclick=\"$('#delete_data').prop('checked',true);\">{$lang['edit_project_217']}".$myCapNote."</span>
								</div>
							</div>
						</fieldset>
						<div style='margin-top:20px;'>{$lang['edit_project_180']}</div>";
						
// If request to move project to prod was CANCELLED, then give dialog noting this
if (isset($_GET['request_id']) && is_numeric($_GET['request_id']) && !ToDoList::checkIfRequestPendingById($_GET['request_id']))
{	
	?>
	<script type='text/javascript'>
	$(function(){
		simpleDialog('<?php print js_escape($lang['edit_project_223']) ?>','<?php print js_escape($lang['edit_project_189']) ?>');
	});
	</script>
	<?php	
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;
}

// If only Super Users can move to production, then give different text for normal users
if (!UserRights::isSuperUserNotImpersonator() && $superusers_only_move_to_prod == '1')//from here!!!
{
	$status_dialog_text .= "<br>
							<p style='color:#800000;'>
								<img src='" . APP_PATH_IMAGES . "exclamation.png'>
								<b>{$lang['global_02']}:</b><br>
								{$lang['edit_project_17']} (".RCView::escape($user_email).") {$lang['edit_project_18']}
							</p>";
	$status_dialog_btn = $lang['setup_142'];
	$status_dialog_title = $lang['edit_project_19'];
	// Javascript to send email to REDCap admin for approval to move to production
	print  "<script type='text/javascript'>
			function doChangeStatus2() {
                var delete_data = 0;
                if ($randomizationStatus == 1 && $randProdAllocTableExists == 0) {
                    simpleDialog('".js_escape($lang['setup_136'])."');
                    return false;
                }
                var alertMessage =  '<div class=\"select-radio-button-msg\" style=\"color: #C00000; font-size: 16px; margin-top: 10px;\">Please select one of the options above before moving to production.</div>';
                if ($('#delete_data:checked').val() !== undefined) {
                    if ($('#delete_data:checked').val() == 'on') {
                        delete_data = 1;
                        $('.select-radio-button-msg').remove();
                        // Make user confirm that they want to delete data
                        if (!confirm('".js_escape($lang['setup_137'])."\\n\\n".js_escape($lang['setup_147'])."')) {
                            return false;
                        }
                    } else if ($randomizationStatus == 1) {
                        // If not deleting all data BUT using randomization module, remind that the randomization field's values will be erased
                        if (!confirm('".js_escape($lang['setup_139'])."\\n\\n".js_escape($lang['setup_140'])."')) {
                            return false;
                        }
                    }
                }else if($('#keep_data:checked').val() !== undefined){
                    if ($('#keep_data:checked').val() == 'on') {
                      delete_data = 0;
                      $('.select-radio-button-msg').remove();
                    }
                }else{//if both undefined display message
                    $('.select-radio-button-msg').remove();
                    $('#status_dialog .yellow').append(alertMessage);
                    return false;
                }
                $.get(app_path_webroot+'ProjectGeneral/notifications.php', { pid: pid, type: 'move_to_prod', delete_data: delete_data, survey_pid_move_to_prod_status: survey_pid_move_to_prod_status_record },
                    function(data) {
                        $('#status_dialog').dialog('close');
                        if (data == '1') {
                            window.location.href = app_path_webroot+page+'?pid='+pid+'&msg=request_movetoprod';
                        } else {
                            alert('".js_escape("{$lang['global_01']}{$lang['colon']} {$lang['edit_project_20']}")."');
                        }
                    }
                );
			}
			</script>";
	$status_dialog_btn_action = "doChangeStatus2();";
}

$status_dialog_btn_action = (UserRights::isSuperUserNotImpersonator() || $survey_pid_move_to_prod_status == '')
        ? $status_dialog_btn_action
        : "if ($(':input[name=\"data\"]:checked').length) { openSurveyDialogIframe('".Survey::getProjectStatusPublicSurveyLink('survey_pid_move_to_prod_status')."'); } else { $status_dialog_btn_action }";

// Prepare a "certification" pop-up message when user clicks Move To Prod button if text has been set
if ($status == 0 && hasPrintableText($certify_text_prod) && (!UserRights::isSuperUserNotImpersonator() || (UserRights::isSuperUserNotImpersonator() && !isset($_GET['user_email']))))
{
	print "<div id='certify_prod' title='".js_escape($lang['global_03'])."' style='display:none;text-align:left;'>".filter_tags(nl2br(label_decode($certify_text_prod)))."</div>";
	// Javascript function for when clicking the 'move to production' button
	print  "<script type='text/javascript'>
			function btnMoveToProd() {
				$('#certify_prod').dialog({ bgiframe: true, modal: true, width: 500, buttons: {
					'".js_escape($lang['global_53'])."': function() { $(this).dialog('close'); },
					'".js_escape($lang['setup_135'])."': function() {
						$(this).dialog('close');
						$('#status_dialog').dialog({ bgiframe: true, modal: true, width: 650, close: function(){ closeToDoListFrame(); }, buttons: {
							'".js_escape($lang['global_53'])."': function() { $(this).dialog('close'); },
							'".js_escape($status_dialog_btn)."': function() { $status_dialog_btn_action }
						} });
					}
				} });
			}
			</script>";
} else {
	// Javascript function for when clicking the 'move to production' button
	print  "<script type='text/javascript'>
			function btnMoveToProd() {
				$('#status_dialog').dialog({ bgiframe: true, modal: true, width: 650, close: function(){ closeToDoListFrame(); }, buttons: {
					'".js_escape($lang['global_53'])."': function() { $(this).dialog('close'); },
					'".js_escape($status_dialog_btn)."': function() { $status_dialog_btn_action }
				} });
			}
			</script>";
}
// If Super User has been sent email to approve request to move db to production (and project has not been deleted),
// then display pop-up to super user to move to production.
if (UserRights::isSuperUserNotImpersonator() && $status == 0 && isset($_GET['type']) && $_GET['type'] == "move_to_prod" && $date_deleted == '')
{
	?>
	<script type='text/javascript'>
	$(function(){
		btnMoveToProd();
	});
	</script>
	<?php
}
// Invisible div for status change
print  "<div id='status_dialog' title='".js_escape($status_dialog_title)."' style='display:none;'><p style=''>$status_dialog_text</p></div>";





/**
 * MODIFY PROJECT SETTINGS FORM AS POP-UP
 */
?>
<div id="edit_project" style="display:none;" title="<?php print js_escape2($lang['config_functions_30']) ?>">
	<div class="round chklist" style="padding:10px 20px;">
		<form id="editprojectform" action="<?php echo APP_PATH_WEBROOT ?>ProjectGeneral/edit_project_settings.php?pid=<?php echo $project_id ?>" method="post">
		<table style="width:100%;" cellpadding=0 cellspacing=0>
		<?php
		// Include the page with the form
		include APP_PATH_DOCROOT . 'ProjectGeneral/create_project_form.php';
		?>
		</table>
		</form>
	</div>
</div>
<?php










/**
 * CUSTOMIZE PROJECT SETTINGS FORM AS POP-UP
 */
?>
<div id="customize_project" style="display:none;" title="<?php print js_escape2($lang['setup_104']) ?>">
	<div id="customize_project_sub">
	<p>
		<?php echo $lang['setup_52'] ?>
	</p>
	<div class="round chklist" style="padding:10px 20px;max-width:900px;">
		<form id="customizeprojectform" action="<?php echo APP_PATH_WEBROOT ?>ProjectGeneral/edit_project_settings.php?pid=<?php echo $project_id ?>&action=customize" method="post" enctype="multipart/form-data">
		<table style="width:100%;" cellspacing=0>

		<!-- Custom Record Label -->
		<tr>
			<td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
				<input type="checkbox" id="custom_record_label_chkbx" <?php if (!empty($custom_record_label)) print "checked"; ?>>
				&nbsp;
                <i class="fas fa-tag" style="text-indent: 0;"></i>
				<b style=""><u><?php echo $lang['edit_project_66'] ?></u></b><br>
				<?php
				echo $lang['edit_project_67'];
				if ($longitudinal) {
					echo " " . $lang['edit_project_187'];
				}
				?>
				<div id="custom_record_label_div" style="text-indent:0em;padding:10px 0 0;">
					<?php echo $lang['edit_project_68'] ?>&nbsp;
					<input type="text" class="x-form-text x-form-field" style="width:300px;" id="custom_record_label" name="custom_record_label" value="<?php echo str_replace('"', '&quot;', $custom_record_label) ?>"><br>
					<span style="color:#800000;font-family:tahoma;font-size:10px;">
						<?php echo $lang['edit_project_69'] ?>
					</span>
				</div>
			</td>
		</tr>
		<!-- Secondary unique field -->
		<tr>
			<td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
				<input type="checkbox" id="secondary_pk_chkbx" name="secondary_pk_chkbx" <?php if ($secondary_pk != '') print "checked"; ?>>
				&nbsp;
                <i class="fas fa-tags" style="text-indent: 0;"></i>
				<b style=""><u><?php echo $lang['edit_project_61'] ?></u></b><br>
				<?php echo $lang['setup_183'] ?>
				<?php if ($longitudinal) { echo $lang['edit_project_86']; } ?>
				<div id="secondary_pk_div" style="text-indent: 0em; padding: 10px 0px 0px;">
					<?php echo ProjectSetup::renderSecondIdDropDown("secondary_pk", "secondary_pk") ?>
					<div style="margin:5px;">
						<input type="checkbox" name="secondary_pk_display_value"<?php if ($secondary_pk_display_value) print " checked"; if ($secondary_pk == '') print ' disabled'; ?>>
						<?php echo $lang['edit_project_191'] ?>
					</div>
					<div style="margin:5px;">
						<input type="checkbox" name="secondary_pk_display_label"<?php if ($secondary_pk_display_label) print " checked"; if ($secondary_pk == '') print ' disabled'; ?>>
						<?php echo $lang['edit_project_192'] ?>
					</div>
				</div>
			</td>
		</tr>
		<!-- Order the records by another field -->
		<tr>
			<td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
				<input type="checkbox" id="order_id_by_chkbx" <?php if (!empty($order_id_by) && !$longitudinal) print "checked"; if ($longitudinal) print "disabled"; ?>>
				&nbsp;
				<img src="<?php echo APP_PATH_IMAGES ?>edit_list_order.png">
				<b style=""><u><?php echo $lang['edit_project_72'] ?></u></b><br>
				<?php
				echo $lang['edit_project_73'];
				if ($longitudinal) {
					echo " <b style='color:#800000;'>" . $lang['edit_project_143'] . "</b>";
				}
				?>
				<div id="order_id_by_div" style="text-indent:0em;padding:10px 0 0;">
					<select name="order_id_by" id="order_id_by" class="x-form-text x-form-field" style="" <?php if ($longitudinal) print "disabled"; ?>>
						<option value=''><?php echo $lang['edit_project_71'] ?></option>
					<?php
					// Get field/label list and put in array
					$order_by_id_fields = array();
					foreach ($Proj->metadata as $this_field=>$attr) {
						if ($attr['element_type'] == 'descriptive') continue;
						$order_by_id_fields[$this_field] = $attr['element_label'];
					}
					// Loop through all fields
					foreach ($order_by_id_fields as $this_field=>$this_label)
					{
						// Ignore first field (superfluous)
						if ($this_field == $table_pk) continue;
						$this_label = "$this_field - " . strip_tags(label_decode($this_label));
						// Ensure label is not too long
						if (mb_strlen($this_label) > 67) $this_label = mb_substr($this_label, 0, 50) . "..." . mb_substr($this_label, -15);
						// Add option
						echo "<option value='$this_field' " . (!$longitudinal && $this_field == $order_id_by ? "selected" : "") . ">$this_label</option>";
					}
					?>
					</select>
				</div>
			</td>
		</tr>

		<!-- Data Resolution -->
		<tr>
			<td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
				<input type="checkbox" id="data_resolution_enabled_chkbx" name="data_resolution_enabled_chkbx" <?php if ($data_resolution_enabled) print "checked"; ?>>
				&nbsp;
                <i class="ms-1 fas fa-comments" style="text-indent: 0;"></i>
				<b style=""><u><?php echo $lang['dataqueries_133'] ?></u></b><br>
				<?php echo $lang['dataqueries_134'] ?>
				<a href="javascript:;" style="text-decoration:underline;" onclick="$('#datares_explain_hidden').show();$(this).hide();"><?php echo $lang['edit_project_127'] ?></a>
				<div id="datares_explain_hidden" style="display:none;text-indent:0em;margin-top:8px;">
					<?php echo $lang['dataqueries_144'] ?>
					<?php echo $lang['dataqueries_273'] ?>
                    <i class="fas fa-film"></i>
					<a href="javascript:;" onclick="popupvid('data_resolution_workflow01.swf','<?php echo js_escape($lang['dataqueries_137']) ?>');" style="text-decoration:underline;"><?php echo $lang['global_80'] . " " . $lang['dataqueries_137'] ?></a>
				</div>
				<div id="data_resolution_enabled_div" style="text-indent:0em;padding:10px 0 0;">
					<?php echo $lang['dataqueries_142'] ?>&nbsp;
					<select name="data_resolution_enabled" id="data_resolution_enabled" class="x-form-text x-form-field" style="" onchange="
						if (this.value == '1') {
							$('#field_comment_edit_delete_chkbx').prop('disabled', false);
							$('#div-enable-field_comment_edit_delete').removeClass('opacity50');
							$('#drw_hide_closed_queries_from_dq_results_chkbx').prop('disabled', true).prop('checked', false);
							$('#div-enable-drw_hide_closed_queries_from_dq_results').addClass('opacity50');
						} else {
							$('#field_comment_edit_delete_chkbx').prop('disabled', true).prop('checked', false);
							$('#div-enable-field_comment_edit_delete').addClass('opacity50');
							$('#drw_hide_closed_queries_from_dq_results_chkbx').prop('disabled', false);
							$('#div-enable-drw_hide_closed_queries_from_dq_results').removeClass('opacity50');
						}
					">
						<option value='0' <?php if ($data_resolution_enabled == '0') print "selected"; ?>><?php echo $lang['dataqueries_259'] ?></option>
						<option value='1' <?php if ($data_resolution_enabled == '1') print "selected"; ?>><?php echo $lang['dataqueries_141'] ?></option>
						<option value='2' <?php if ($data_resolution_enabled == '2') print "selected"; ?>><?php echo $lang['dataqueries_137'] ?></option>
					</select>
					<div id="div-enable-field_comment_edit_delete" style="color:#555;margin-top:5px;" <?php if ($data_resolution_enabled != '1') print 'class="opacity50"'; ?>>
						<input type="checkbox" id="field_comment_edit_delete_chkbx" name="field_comment_edit_delete_chkbx" <?php if ($field_comment_edit_delete) print "checked"; if ($data_resolution_enabled != '1') print 'disabled'; ?>>
						<?php echo $lang['dataqueries_288'] ?>
					</div>
					<div id="div-enable-drw_hide_closed_queries_from_dq_results" style="color:#555;margin-top:5px;" <?php if ($data_resolution_enabled != '2') print 'class="opacity50"'; ?>>
						<input type="checkbox" id="drw_hide_closed_queries_from_dq_results_chkbx" name="drw_hide_closed_queries_from_dq_results_chkbx" <?php if ($drw_hide_closed_queries_from_dq_results) print "checked"; if ($data_resolution_enabled != '2') print 'disabled'; ?>>
						<?php echo $lang['dataqueries_357'] ?>
					</div>
				</div>
			</td>
		</tr>

		<!-- PDF Customizations -->
		<tr>
			<td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
				<input type="checkbox" id="pdf_customizations_enabled" name="pdf_customizations_enabled" <?php if (($pdf_custom_header_text != RCView::getLangStringByKey("global_237") && $pdf_custom_header_text !== null) || $pdf_show_logo_url == '0' || $pdf_hide_secondary_field == '1' || $pdf_hide_record_id == '1') print "checked"; ?>>
				&nbsp;
                <i class="fas fa-file-pdf fs14" style="text-indent: 0;"></i>
				<b style=""><u><?php echo $lang['setup_173'] ?></u></b><br>
				<?php echo $lang['setup_174'] ?>
				<div id="pdf_customizations_div" style="text-indent:0em;">
					<div style="padding:10px 0 0;margin-left:1.5em;text-indent:-1.5em;">
						<b>1)</b>&nbsp; <?php echo $lang['setup_206'] ?><br>
						<input type="text" class="x-form-text x-form-field" style="width:200px;margin-top:3px;" id="pdf_custom_header_text" name="pdf_custom_header_text" value="<?php echo ($pdf_custom_header_text === null ? RCView::getLangStringByKey("global_237") : str_replace('"', '&quot;', $pdf_custom_header_text)) ?>"> &nbsp;
						<span style="color:#A00000;font-family:tahoma;font-size:12px;">
							<?php echo $lang['setup_176'] ?> <i><?php print RCView::getLangStringByKey("global_237") ?></i>
						</span>
					</div>
					<div style="padding:10px 0 0;margin-left:1.5em;text-indent:-1.5em;">
						<b>2)</b>&nbsp; <?php echo $lang['setup_177'] ?><br>
						<label style="display:inline;font-weight:normal;color:#A00000;margin-bottom:2px;"><input type="radio" name="pdf_show_logo_url" value="1" <?php if ($pdf_show_logo_url == '1') print "checked"; ?>> <?php echo $lang['setup_178'] ?></label><br>
						<label style="display:inline;font-weight:normal;color:#A00000;margin-bottom:2px;"><input type="radio" name="pdf_show_logo_url" value="0" <?php if ($pdf_show_logo_url == '0') print "checked"; ?>> <?php echo $lang['setup_179'] ?> &nbsp;<i><?php print System::powered_by_redcap ?></i></label>
					</div>
					<div style="padding:10px 0 0;margin-left:1.5em;text-indent:-1.5em;">
						<b>3)</b>&nbsp; <?php echo $lang['setup_184'] ?><br>
						<select name="pdf_hide_secondary_field" id="pdf_hide_secondary_field" class="x-form-text x-form-field" style="margin-top:3px;">
							<option value='0' <?php if ($pdf_hide_secondary_field == '0') print "selected"; ?>><?php echo $lang['setup_185'] ?></option>
							<option value='1' <?php if ($pdf_hide_secondary_field == '1') print "selected"; ?>><?php echo $lang['setup_181'] ?></option>
						</select>
					</div>
					<div style="padding:10px 0 0;margin-left:1.5em;text-indent:-1.5em;">
						<b>4)</b>&nbsp; <?php echo $lang['setup_189'] ?><br>
						<select name="pdf_hide_record_id" id="pdf_hide_record_id" class="x-form-text x-form-field" style="margin-top:3px;">
							<option value='0' <?php if ($pdf_hide_record_id == '0') print "selected"; ?>><?php echo $lang['setup_185'] ?></option>
							<option value='1' <?php if ($pdf_hide_record_id == '1') print "selected"; ?>><?php echo $lang['setup_181'] ?></option>
						</select>
					</div>
				</div>
			</td>
		</tr>

        <!-- Record-level locking + PDF vault storage -->
        <?php if ($record_locking_pdf_vault_filesystem_type != '') { ?>
            <tr>
                <td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
                    <input type="checkbox" id="record_locking_pdf_vault_enabled" name="record_locking_pdf_vault_enabled" <?php if ($record_locking_pdf_vault_enabled) print "checked"; ?>>
                    &nbsp;
                    <i class="fas fa-file-pdf fs14" style="text-indent: 0;"></i>
                    <b style=""><u><?php echo $lang['data_entry_487'] ?></u></b><br>
                    <?php echo $lang['data_entry_488'] ?>
                </td>
            </tr>
        <?php } ?>
      
        <!-- Custom Missing Data Codes -->
        <tr>
            <td colspan="2" valign="top" style="margin-left:1.5em;text-indent: -0.5em;padding:10px 40px;">
                <img src="<?php echo APP_PATH_IMAGES ?>missing_active.png" style="position:relative;top:-2px;margin-right:2px;">
                <b style="font-family:verdana;"><u><?php echo $lang['missing_data_08'] ?></u></b><br>
                <?php echo $lang['missing_data_09'] ?>
                <div style="text-indent: 0;"><a href="javascript:;" onclick="$(this).hide();$('#moreInstructionsMissingness').toggle('fade');" style="text-decoration:underline;"><?php echo $lang['dataqueries_35'] ?></a></div>
                <div id="moreInstructionsMissingness" style="display:none;margin-top:10px;text-indent: 0;">
                    <b><?php echo $lang['missing_data_15'] ?></b>
                    <ul>
                        <li><?php echo $lang['missing_data_11'] ?></li>
                        <li><?php echo $lang['missing_data_16'] ?></li>
                        <li><?php echo $lang['missing_data_17'] ?></li>
                        <li><?php echo $lang['missing_data_18'] ?></li>
                        <li><?php echo $lang['missing_data_19'] ?></li>
                        <li><?php echo $lang['data_export_tool_243'] ?></li>
                    </ul>
                </div>
                <div class="clearfix">
                    <div class="float-start" style="width:60%;padding: 5px;">
                        <div class="font-weight-bold" style="margin:10px 0 3px;text-indent: 0;">
                            <?php echo $lang['missing_data_04'] ?>
							<?php if ($status > 0 && $missing_data_codes != '') { print RCView::button(array('class'=>'ms-4 btn btn-xs btn-rcgreen fs11', 'onclick'=>"var btn=$(this); simpleDialog('".js_escape($lang['missing_data_21'])."','".js_escape($lang['missing_data_20'])."',null,600,null,'".js_escape($lang['global_53'])."',function(){ $('#missing_data_codes').css('background-color','#fff').attr('readonly', false); btn.hide(); $('.addMissingCodeBtn').prop('disabled',false); },'".js_escape($lang['locking_30'])."');return false;"), '<i class="fas fa-pencil-alt me-2"></i>'.$lang['missing_data_20']); } ?>
                        </div>
                        <textarea id="missing_data_codes" name="missing_data_codes" class="x-form-textarea x-form-field" <?=($status > 0 && $missing_data_codes != '')?"readonly":""?> style="width:95%; height: 140px; resize: auto; <?=($status > 0 && $missing_data_codes != '')?"background-color:#ddd;":""?>"><?php echo htmlspecialchars($missing_data_codes, ENT_QUOTES) ?></textarea>
                    </div>
                    <?php
                    $defaultMissingDataCodesRows = "";
                    $missingDataCodeBtnDisabled = ($status > 0 && $missing_data_codes != '')?"disabled":"";
                    foreach (parseEnum(DataEntry::getDefaultMissingDataCodes()) as $mCode=>$mLabel) {
                        $defaultMissingDataCodesRows .=
                            RCView::tr(array(),
                                RCView::td(array('class'=>'nowrap', 'style'=>'text-align:center;background-color:#f5f5f5;color:#912B2B;padding:3px 15px 3px 12px;font-weight:bold;border:1px solid #ccc;border-bottom:0;border-right:0;'),
                                    RCView::button(array('class'=>'btn btn-xs btn-primaryrc addMissingCodeBtn', $missingDataCodeBtnDisabled=>$missingDataCodeBtnDisabled, 'style'=>'font-size:11px;', 'onclick'=>"addMissingCode(this,'".js_escape($mCode.", ".$mLabel)."'); return false;"), $lang['design_171'])
                                ) .
                                RCView::td(array('class'=>'nowrap', 'style'=>'background-color:#f5f5f5;color:#2e6da4;padding:3px;font-weight:bold;border:1px solid #ccc;border-bottom:0;border-left:0;border-right:0;'),
                                    $mCode
                                ) .
                                RCView::td(array('style'=>'font-size:12px;background-color:#f5f5f5;padding:3px;border:1px solid #ccc;border-bottom:0;border-left:0;'),
                                    $mLabel
                                )
                            );
                    }
                    print RCView::div(array('class'=>'float-end', 'style'=>'width:37%;height:195px;overflow-y:scroll;border:1px solid #ccc;margin-top:10px;'),
                            RCView::div(array('style'=>'text-indent:0;background-color:#eee;padding:2px 6px;','class'=>'boldish fs12'), $lang['missing_data_10']) .
                            RCView::table(array('style'=>'width:100%;border-bottom:1px solid #ccc;line-height:13px;'),
                                $defaultMissingDataCodesRows
                            )
                        );

                    ?>
                </div>
            </td>
        </tr>

        <!-- Data History Widget -->
        <tr>
            <td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
                <input type="checkbox" id="history_widget_enabled" name="history_widget_enabled" <?php if ($history_widget_enabled) print "checked"; ?>>
                &nbsp;
                <img src="<?php echo APP_PATH_IMAGES ?>history_active.png">
                <b style=""><u><?php echo $lang['edit_project_53'] ?></u></b><br>
                <?php echo $lang['edit_project_54'] ?>
            </td>
        </tr>
      
        <!-- File version history -->
        <?php if ($file_upload_versioning_global_enabled) { ?>
            <tr>
                <td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
                    <input type="checkbox" id="file_upload_versioning_enabled" name="file_upload_versioning_enabled" <?php if ($file_upload_versioning_enabled) print "checked"; ?>>
                    <i class="fas fa-history ms-2" style="text-indent: 0;"></i> <i class="fas fa-file-upload" style="text-indent: 0;margin:0 1px 0 2px;"></i>
                    <b style=""><u><?php echo $lang['data_entry_456'] ?></u></b><br>
                    <?php echo $lang['data_entry_613'] ?>
                </td>
            </tr>
        <?php } ?>

        <!-- File Upload verify + vault storage -->
        <?php if (Files::fileUploadPasswordVerifyExternalStorageEnabledSystem()) { ?>
        <tr>
            <td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
                <input type="checkbox" id="file_upload_vault_enabled" name="file_upload_vault_enabled" <?php if ($file_upload_vault_enabled) print "checked"; ?>>
                &nbsp;
                <i class="fas fa-key" style="text-indent: 0;"></i> <i class="fas fa-cloud-upload-alt" style="text-indent: 0;"></i>
                <b style=""><u><?php echo $lang['data_entry_451'] ?></u></b><br>
                <?php echo $lang['data_entry_452'] ?>
                <?php echo $lang['data_entry_475'] ?>
            </td>
        </tr>
        <?php } ?>
    
      <!-- Display Today/Now button -->
		<tr>
			<td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
				<input type="checkbox" id="display_today_now_button" name="display_today_now_button" <?php if ($display_today_now_button) print "checked"; ?>>
				&nbsp;
                <i class="fas fa-calendar-day" style="text-indent: 0;"></i>
				<b style=""><u><?php echo $lang['system_config_143'] ?></u></b><br>
				<?php echo $lang['system_config_144'] ?>
			</td>
		</tr>


        <!-- Prevent branching logic from hiding fields that have values -->
        <tr>
            <td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
                <input type="checkbox" id="bypass_branching_erase_field_prompt" name="bypass_branching_erase_field_prompt" <?=$bypass_branching_erase_field_prompt ? "checked" : ""?>>
				&nbsp;
				<?=RCIcon::BranchingLogic("", "text-indent:0;")?>
				<b><u><?=RCView::tt("project_settings_59")?></u></b><br><?=RCView::tt("project_settings_63")?> <?=RCView::tt("project_settings_62")?>
            </td>
        </tr>

		<!-- Require a reason -->
		<tr>
			<td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
				<input type="checkbox" id="require_change_reason" name="require_change_reason" <?php if ($require_change_reason) print "checked"; ?>>
				&nbsp;
                <i class="fas fa-pen-square fs14" style="text-indent:0;"></i>
				<b><u><?php echo $lang['edit_project_41'] ?></u></b><br>
				<?php echo $lang['edit_project_184'] ?>
            </td>
		</tr>

        <!-- Protected Email Mode -->
        <?php if ($protected_email_mode_global) { ?>
        <tr>
            <td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
                <input type="checkbox" id="protected_email_mode" name="protected_email_mode" <?php if ($protected_email_mode) print "checked"; ?>>
                &nbsp;
                <i class="fas fa-lock fs14 me-1" style="text-indent: 0;"></i><i class="fas fa-envelope fs14" style="text-indent: 0;"></i>
                <b><u><?php echo $lang['global_235'] ?></u></b><br>
				<?php echo $lang['system_config_723'] . '  ' . $lang['design_1005'] ?>
                <div class="container clearfix mx-0 mt-3" style="text-indent:-1.2em;">
                    <div class="row">
                        <div class="col-sm-6">
							<?php echo RCView::span(['style'=>'color:#A00000;'], $lang['system_config_718']) ?>
                        </div>
                        <div class="col-sm-6 me-0 mt-1">
                            <select name="protected_email_mode_trigger" id="protected_email_mode_trigger" class="x-form-text x-form-field" style="margin-top:3px;max-width:375px;">
                                <option value='ALL' <?php if ($protected_email_mode_trigger == 'ALL') print "selected"; ?>><?php echo $lang['system_config_720'] ?></option>
                                <option value='PIPING' <?php if ($protected_email_mode_trigger == 'PIPING') print "selected"; ?>><?php echo $lang['system_config_719'] ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-sm-6 mt-2">
							<?php echo RCView::span(['style'=>'color:#A00000;'], $lang['system_config_724']) ?>
                        </div>
                        <div class="col-sm-6 me-0 mt-2">
                            <textarea id="protected_email_mode_custom_text" name="protected_email_mode_custom_text" class="x-form-textarea x-form-field" style="width:100%; max-width:375px; height: 45px; resize: auto;" placeholder="<?=js_escape2($lang['email_users_36'])?>"><?php echo htmlspecialchars(($protected_email_mode_custom_text == '' ? $lang['email_users_36'] : $protected_email_mode_custom_text), ENT_QUOTES) ?></textarea>
                            <div class="cc_info" style="text-indent:0;margin-left: -13px;"><?=$lang['system_config_725']?><br><code class="fs11">VUMC Study&lt;br&gt;&lt;img src="https://redcap.mysite.edu/logo.png"&gt;</code></div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-sm-6">
                            <?php echo RCView::span(['style'=>'color:#A00000;'], $lang['edit_project_218']) ?>
                        </div>
                        <div class="col-sm-6 me-0 mt-1">
                            <input type="hidden" name="old_logo" id="old_logo" value="<?php echo $protected_email_mode_logo ?>">
                            <div id="old_logo_div" style=";color:#555;font-size:12px;display:<?php echo (!empty($protected_email_mode_logo) ? "block" : "none") ?>">
                                <?php echo $lang['edit_project_219'] ?> &nbsp;
                                <a href="javascript:;" style="font-size:12px;color:#A00000;text-decoration:none;" onclick='
                                    if (confirm("<?php echo js_escape(js_escape2($lang['survey_757'])) ?>")) {
                                        $("#new_logo_div").css({"display":"block"});
                                        $("#old_logo_div").css({"display":"none"});
                                        $("#old_logo").val("");
                                    }
                                '>[X] <?php echo $lang['survey_62'] ?></a>
                                <br>
                                <img src="<?php echo APP_PATH_WEBROOT ?>DataEntry/image_view.php?pid=<?php echo $project_id ?>&doc_id_hash=<?php echo Files::docIdHash($protected_email_mode_logo) ?>&id=<?php echo $protected_email_mode_logo ?>" alt="<?php print js_escape($lang['survey_1140']) ?>" title="<?php print js_escape($lang['survey_1140']) ?>" style="max-width:400px;">
                            </div>
                            <div id="new_logo_div" style="color:#555;font-size:12px;display:<?php echo (empty($protected_email_mode_logo) ? "block" : "none") ?>">
                                <div class="mb-1 font-weight-bold fs13"><?php echo $lang['edit_project_221'] ?></div>
                                <input type="file" name="logo" id="logo_id" size="30" style="font-size:13px;" onchange="checkLogo(this.value);">
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php } ?>

		<!-- Data Entry Trigger (if enabled in the Control Center -->
		<?php if ($data_entry_trigger_enabled) { ?>
		<tr>
			<td colspan="2" valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
				<input type="checkbox" id="data_entry_trigger_url_chkbx" <?php if (!empty($data_entry_trigger_url)) print "checked"; ?>>
				&nbsp;
                <i class="fas fa-hand-point-right" style="text-indent: 0;"></i>
				<b style=""><u><?php echo $lang['edit_project_122'] ?></u></b><br>
				<?php echo $lang['edit_project_160'] ?>
				<a href="javascript:;" onclick="simpleDialog(null,null,'dataEntryTriggerDialog',650);" class="nowrap" style="text-decoration:underline;"><?php echo $lang['edit_project_127'] ?></a>
				<div id="data_entry_trigger_url_div" style="text-indent:0em;padding:10px 0 0;">
					<?php echo $lang['edit_project_124'] ?>&nbsp;
					<input type="text" class="x-form-text x-form-field" style="width:350px;" id="data_entry_trigger_url" name="data_entry_trigger_url" value="<?php echo RCView::escape($data_entry_trigger_url) ?>" onblur="
						this.value=trim(this.value);
						if (this.value.length == 0) return;
						// Disallow localhost
						var localhost_array = new Array('localhost', 'http://localhost', 'https://localhost', 'localhost/', 'http://localhost/', 'https://localhost/');
						if (in_array(this.value, localhost_array)) {
							simpleDialog('<?php echo js_escape($lang['edit_project_126']) ?>','<?php echo js_escape($lang['global_01']) ?>',null,null,'$(\'#data_entry_trigger_url\').focus();');
							return;
						}
						// Validate URL as full or relative URL
						if (!isUrl(this.value) && this.value.substr(0,1) != '/') {
							if (this.value.substr(0,4).toLowerCase() != 'http' && isUrl('http://'+this.value)) {
								// Prepend 'http' to beginning
								this.value = 'http://'+this.value;
							} else {
								// Error msg
								simpleDialog('<?php echo js_escape($lang['edit_project_126']) ?>','<?php echo js_escape($lang['global_01']) ?>',null,null,'$(\'#data_entry_trigger_url\').focus();');
							}
						}
					">
					<button class="btn btn-defaultrc btn-xs fs13" style="" onclick="
						var det_url = $('#data_entry_trigger_url').val();
						if (det_url == '') {
							$('#data_entry_trigger_url').focus();
							return false;
						}
						var pre_url = (det_url.substr(0,1) == '/') ? '<?php echo js_escape((SSL ? "https://" : "http://") . SERVER_NAME) ?>' : '';
						testUrl(pre_url+det_url,'post');
						return false;
					"><?php echo $lang['edit_project_138'] ?></button>
                    <span style="margin:5px 0 0 25px;">
                        <img src="<?php echo APP_PATH_IMAGES ?>pipe.png">
                        <a href="javascript:;" style="font-weight:normal;color:#3E72A8;text-decoration:underline;" onclick="pipingExplanation();"><?php echo $lang['design_1054'] ?></a>
                    </span>
					<div style="color:#800000;font-family:tahoma;font-size:10px;">
						<?php echo $lang['edit_project_125'] ?> https://www.mywebsite.com/redcap_trigger_receive/
					</div>
					<div style="color:#800000;font-family:tahoma;font-size:10px;">
						<?php echo $lang['edit_project_125'] ?> /det/index.php
					</div>
				</div>
			</td>
		</tr>
		<?php } ?>

		</table>
		</form>
	</div>
	</div>
</div>




<script type='text/javascript'>
// Display the pop-up for project customization
function displayCustomizeProjPopup() {
	$('#customize_project').dialog({ bgiframe: true, modal: true, width: 950,
		open: function(){
			fitDialog(this);
		},
		close: function(){
          resetCustomizeProjPopupValues();
        },
		buttons: {
			Cancel: function() { $(this).dialog('close'); },
			Save: function() {
				$('#customizeprojectform').submit();
				$(this).dialog('close');
			}
		}
	});
}
// Store the initial input values of the Addl Customizations dialog
var addlCustDlgInitialValues = {};
function getCustomizeProjPopupInitValues() {
  $('#customize_project').find('input, textarea, select').each(function() {
    var key = $(this).attr('id') || $(this).attr('name');
    if (key) {
      if ($(this).is(':radio, :checkbox')) {
        addlCustDlgInitialValues[key] = $(this).prop('checked');
      } else {
        addlCustDlgInitialValues[key] = $(this).val();
      }
    }
  });
}
// Reset the input values of the Addl Customizations dialog to their original values
function resetCustomizeProjPopupValues() {
  $('#customize_project').find('input, textarea, select').each(function() {
    var key = $(this).attr('id') || $(this).attr('name');
    if (key && addlCustDlgInitialValues.hasOwnProperty(key)) {
      var isRadChk = $(this).is(':radio, :checkbox');
      if (isRadChk && $(this).prop('checked') != addlCustDlgInitialValues[key]) {
        $(this).trigger('click');
      } else if (!isRadChk && $(this).val() != addlCustDlgInitialValues[key]) {
        $(this).val(addlCustDlgInitialValues[key]).trigger('change');
      }
    }
  });
  checkPdfCustomizationsSetting();
}
// Display the pop-up for modifying project settings
function displayEditProjPopup() {
	$('#edit_project').dialog({ bgiframe: true, modal: true, width: 700,
		open: function(){
			fitDialog(this);
			if ($('#projecttype1').prop('checked') || $('#projecttype2').prop('checked') ) {
				$('#step2').fadeTo(0, 1);
				$('#additional_options').fadeTo(0, 1);
			} else {
				$('#step2').hide();
				$('#additional_options').hide();
			}
			if ($('#repeatforms_chk2').prop('checked')) {
				$('#step3').fadeTo(0, 1);
			}
		},
		buttons: {
			'<?php echo $lang['global_53'] ?>': function() { $(this).dialog('close'); },
			'<?php echo $lang['pub_085'] ?>': function() {
				if (setFieldsCreateFormChk()) {
					$('#editprojectform').submit();
					$(this).dialog('close');
				}
			}
		}
	});
}
// Enable/disable a survey
function surveyOnline(survey_id) {
	$.post(app_path_webroot+'Surveys/survey_online.php?pid='+pid+'&survey_id='+survey_id, { }, function(data){
		var json_data = jQuery.parseJSON(data);
		if (json_data.length < 1) {
			alert(woops);
			return false;
		}
		if (json_data.payload == '') {
			alert(woops);
			return false;
		} else {
			// Change HTML on Project Setup page
			$('#survey_active').html(json_data.payload);
			$('#survey_title_div').effect('highlight',2500);
			initWidgets();
			// If popup_content is specified, the show popup
			if (json_data.popup_content != '') {
				simpleDialog(json_data.popup_content,json_data.popup_title);
			}
		}
	});
}
function checkPdfCustomizationsSetting() {
	if ($('#pdf_customizations_enabled').prop('checked')) {
		$('#pdf_customizations_div').fadeTo('slow', 1);
		$('#pdf_customizations_div :input').prop('disabled',false);
	} else {
		$('#pdf_customizations_div').fadeTo('fast', 0.3);
		$('#pdf_custom_header_text').val('<?=RCView::tt_js("global_237")?>');
		$('input[name="pdf_show_logo_url"][value="1"]').prop('checked',true);
		$('#pdf_customizations_div :input').prop('disabled',true);
		$('#pdf_hide_secondary_field').val('0');
		$('#pdf_hide_record_id').val('0');
	}
}

$(function(){

    getCustomizeProjPopupInitValues();

	// Set up actions for Secondary ID field to be unique
	$('#customize_project #secondary_pk, .chklist #secondary_pk').change(function(){
		var ob = $(this);
		if (ob.val() != '') {
			$.get(app_path_webroot+'DataEntry/check_unique_ajax.php', { pid: pid, field_name: ob.val() }, function(data){
				if (data.length == 0) {
					alert(woops);
				} else if (data != '0') {
					simpleDialog('<?php echo js_escape($lang['edit_project_64']) ?>','"'+ob.val()+'" <?php echo js_escape($lang['edit_project_63']) ?>');
					ob.val('');
				}
			});
		}
	});
	// Set up actions for 'Customize project settings' form
	$('#data_resolution_enabled_chkbx').click(function(){
		if ($(this).prop('checked')) {
			$('#data_resolution_enabled_div').fadeTo('slow', 1);
			$('#data_resolution_enabled').prop('disabled',false);
		} else {
			$('#data_resolution_enabled_div').fadeTo('fast', 0.3);
			$('#data_resolution_enabled').val('').prop('disabled',true);
		}
	});
	$('#data_entry_trigger_url_chkbx').click(function(){
		if ($(this).prop('checked')) {
			$('#data_entry_trigger_url_div').fadeTo('slow', 1);
			$('#data_entry_trigger_url').prop('disabled',false);
		} else {
			$('#data_entry_trigger_url_div').fadeTo('fast', 0.3);
			$('#data_entry_trigger_url').val('').prop('disabled',true);
		}
	});
	$('#custom_record_label_chkbx').click(function(){
		if ($(this).prop('checked')) {
			$('#custom_record_label_div').fadeTo('slow', 1);
			$('#custom_record_label').prop('disabled',false);
		} else {
			$('#custom_record_label_div').fadeTo('fast', 0.3);
			$('#custom_record_label').prop('disabled',true);
			$('#custom_record_label').val('');
		}
	});	
	$('#pdf_customizations_enabled').click(function(){
		checkPdfCustomizationsSetting();
	});
	checkPdfCustomizationsSetting();
	$('#order_id_by_chkbx').click(function(){
		if ($(this).prop('checked')) {
			$('#order_id_by_div').fadeTo('slow', 1);
			$('#order_id_by').prop('disabled',false);
		} else {
			$('#order_id_by_div').fadeTo('fast', 0.3);
			$('#order_id_by').prop('disabled',true);
			$('#order_id_by').val('');
		}
	});
	$('#secondary_pk_chkbx').click(function(){
		$('input[name="secondary_pk_display_value"], input[name="secondary_pk_display_label"]').prop('checked',true);
		if ($(this).prop('checked')) {
			$('#secondary_pk_div').fadeTo('slow', 1);
			$('#customize_project #secondary_pk, input[name="secondary_pk_display_value"], input[name="secondary_pk_display_label"]').prop('disabled',false);
		} else {
			$('#secondary_pk_div').fadeTo('fast', 0.3);
			$('#customize_project #secondary_pk, input[name="secondary_pk_display_value"], input[name="secondary_pk_display_label"]').prop('disabled',true);
			$('#customize_project #secondary_pk').val('');
		}
	});
	// When load page, disabled drop-down if has no value
	if ($('#customize_project #secondary_pk').val().length < 1) {
		$('#secondary_pk_div').fadeTo(0, 0.3);
		$('#customize_project #secondary_pk').prop('disabled',true);
	}
	$('#purpose').change(function(){
		setTimeout(function(){
			fitDialog($('#edit_project'));
			$('#edit_project').dialog('option', 'position', 'center');
		},700);
	});


	// Use javascript to pre-fill 'modify project settings' form with existing info
	setTimeout(function()
	{
        $('#app_title').val('<?php echo str_replace("'", "&#039;", filter_tags(html_entity_decode($app_title, ENT_QUOTES))) ?>');
        $('#app_title').val($('#app_title').val().replace(/&#039;/g,"'"));
		$('#purpose').val('<?php echo $purpose ?>');
		if ($('#purpose').val() == '1') {
			$('#purpose_other_span').css({'visibility':'visible'});
			$('#purpose_other_text').val('<?php echo js_escape(filter_tags(html_entity_decode($purpose_other, ENT_QUOTES))) ?>');
			$('#purpose_other_text').css('display','');
		} else if ($('#purpose').val() == '2') {
			$('#purpose_other_span').css({'visibility':'visible'});
			$('#project_pi_irb_div').css('display','');
			$('#project_pi_firstname').val('<?php echo js_escape(filter_tags(html_entity_decode($project_pi_firstname, ENT_QUOTES))) ?>');
			$('#project_pi_mi').val('<?php echo js_escape(filter_tags(html_entity_decode($project_pi_mi, ENT_QUOTES))) ?>');
			$('#project_pi_lastname').val('<?php echo js_escape(filter_tags(html_entity_decode($project_pi_lastname, ENT_QUOTES))) ?>');
			$('#project_pi_email').val('<?php echo js_escape(filter_tags(html_entity_decode($project_pi_email, ENT_QUOTES))) ?>');
			$('#project_pi_alias').val('<?php echo js_escape(filter_tags(html_entity_decode($project_pi_alias, ENT_QUOTES))) ?>');
			$('#project_pi_username').val('<?php echo js_escape(filter_tags(html_entity_decode($project_pi_username, ENT_QUOTES))) ?>');
			$('#project_irb_number').val('<?php echo js_escape(filter_tags(html_entity_decode($project_irb_number, ENT_QUOTES))) ?>');
			$('#project_grant_number').val('<?php echo js_escape(filter_tags(html_entity_decode($project_grant_number, ENT_QUOTES))) ?>');
			$('#purpose_other_research').css('display','');
			var purposeOther = '<?php echo js_escape(filter_tags(html_entity_decode($purpose_other, ENT_QUOTES))) ?>';
			if (purposeOther != '') {
				var purposeArray = purposeOther.split(',');
				for (i = 0; i < purposeArray.length; i++) {
					document.getElementById('purpose_other['+purposeArray[i]+']').checked = true;
				}
			}
		}
		$('#repeatforms_chk_div').css({'display':'block'});
		$('#datacollect_chk').prop('checked',true);
		$('#projecttype<?php echo ($surveys_enabled ? '2' : '1') ?>').prop('checked',true);
		$('#repeatforms_chk<?php echo ($repeatforms ? '2' : '1') ?>').prop('checked',true);
	<?php if ($scheduling) { ?>
		$('#scheduling_chk').prop('checked',true);
	<?php } ?>
	<?php if ($randomization) { ?>
		$('#randomization_chk').prop('checked',true);
	<?php } ?>
	<?php if (empty($custom_record_label)) { ?>
		$('#custom_record_label_div').fadeTo(0, 0.3);
		$('#custom_record_label').val('').prop('disabled',true);
	<?php } ?>
	<?php if ($data_resolution_enabled == '0') { ?>
		$('#data_resolution_enabled_div').fadeTo(0, 0.3);
		$('#data_resolution_enabled').val('').prop('disabled',true);
	<?php } ?>
	<?php if (empty($data_entry_trigger_url)) { ?>
		$('#data_entry_trigger_url_div').fadeTo(0, 0.3);
		$('#data_entry_trigger_url').val('').prop('disabled',true);
	<?php } ?>
	<?php if (empty($order_id_by)) { ?>
		$('#order_id_by_div').fadeTo(0, 0.3);
		$('#order_id_by').val('').prop('disabled',true);
	<?php } ?>

	// Run function to set up the steps accordingly
	setFieldsCreateForm(false);

	<?php if ($status > 0 && !UserRights::isSuperUserNotImpersonator()) { ?>
		// Do not allow normal users to edit project settings if in Production
		$('#projecttype0').prop('disabled',true);
		$('#projecttype1').prop('disabled',true);
		$('#projecttype2').prop('disabled',true);
		$('#datacollect_chk').prop('disabled',true);
		$('#scheduling_chk').prop('disabled',true);
		$('#repeatforms_chk1').prop('disabled',true);
		$('#repeatforms_chk2').prop('disabled',true);
		$('#randomization_chk').prop('disabled',true);
		$('#primary_use_disable').show();
		// Add additional hidden fields to the form for disabled checkboxes to preserve current values
		$('#editprojectform').append('<input type="hidden" name="scheduling" value="<?php echo $scheduling ?>">');
	<?php } ?>

	},1);
    // Formatting of Missing Data codes
	$('#missing_data_codes').blur(function(){
	    var choicesnew = new Array(), thischoice, errors = new Array(), numcommas, e = 0, n = 0, vals_unique = new Array(), v = 0;
        $(this).val( filter_tags($(this).val()) );
        var choices = $(this).val().split("\n");
        for (var i=0; i<choices.length; i++) {
            thischoice = trim(choices[i]);
            if (thischoice == '') continue;
            var commaPos = thischoice.indexOf(",");
            if (commaPos < 0) {
                var val = '???';
                var label = thischoice;
            } else {
                var val = trim(thischoice.substring(0, commaPos));
                var label = trim(thischoice.substring(commaPos + 1));
            }
            if (in_array(val, vals_unique)) continue;
            choicesnew[n++] = thischoice = val+', '+label;
            vals_unique[v++] = val.toLowerCase();
            if (!isNumeric(val) && !val.match(/^[0-9A-Za-z._\-]*$/)) {
                errors[e++] = "<b>\"" + thischoice + "</b>\" " + '<?php echo js_escape($lang['setup_188']) ?>';
                continue;
            }
        }
        $(this).val(choicesnew.join("\n"));
        if (errors.length > 0) {
            simpleDialog('<?php echo js_escape($lang['setup_186']) ?><ul><li>'+errors.join('</li><li>')+'</li></ul>', '<?php echo js_escape($lang['global_01']) ?>', null, 600, "$('#missing_data_codes').effect('highlight',{},2000).focus();", '<?php echo js_escape($lang['calendar_popup_01']) ?>');
        }
    });
});
function addMissingCode(ob,newRow) {
    var $textarea = $('#missing_data_codes');
    $textarea.val($textarea.val()+"\n"+newRow).trigger('blur').effect('highlight',{},1000);
    $textarea.scrollTop($textarea[0].scrollHeight);
    highlightTableRowOb($(ob).parentsUntil('tr').parent(),1000);
}
</script>
<?php
addLangToJS(["create_project_140", "create_project_141", "create_project_142", "create_project_143",
    "create_project_144", "create_project_145", "create_project_146", "create_project_147",
    "create_project_148", "create_project_149", "create_project_150"]);
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';