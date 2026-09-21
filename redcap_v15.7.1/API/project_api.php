<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$api_enabled || ($user_rights['api_import'] == '0' && $user_rights['api_export'] == '0')) {
	redirect(APP_PATH_WEBROOT."index.php?pid=".PROJECT_ID);
}

$db = new RedCapDB();
$token = UserRights::getAPIToken($userid, $project_id);

// API help
$instr = RCView::p(array('style' => 'margin-top:1em;'),
				$lang['system_config_114'] . ' ' .
				RCView::a(array('href' => APP_PATH_WEBROOT_PARENT . 'api/help/', 'style' => 'text-decoration:underline;', 'target' => '_blank'),
								$lang['edit_project_142']) .
				$lang['period'] . ' ');

// If using SSL, give reminder about checking SSL certificate in API request
if (SSL) {
	$instr .= RCView::p(array('class'=>'yellow', 'style'=>'margin:20px 0;'),
				RCView::img(array('src' => 'exclamation_orange.png')) .
				RCView::b($lang['api_09']) . RCView::br() .
				$lang['api_10'] . ' ' .
				RCView::a(array('href' => APP_PATH_WEBROOT_PARENT . 'api/help/?view=security', 'style' => 'text-decoration:underline;', 'target' => '_blank'),
								$lang['edit_project_142']) .
				$lang['period'] . ' '
			  );
}

$h = ''; // will hold the HTML to display in API div (all JS is included inline at the bottom)
$h .= RCView::span(array('id' => 'apiDialogContainerId', 'style' => 'display: none;'), '');

// dummy container used as a target for a loading overlay
$dummy = '';
// API token for selected project
$tok = '';
$tok .= RCView::div(array('class' => 'chklisthdr'), $lang['api_05'] . ' "' . RCView::escape($app_title) . '"');
$tok .= RCView::div(array('style' => 'margin:10px 0;'), $lang['edit_project_87']);
$tok .= RCView::div(array('style' => 'margin:25px 0 30px;', 'class'=>'clearfix'),
            RCView::div(array('style' => 'font-weight:bold;float:left;font-size:14px;margin:3px 1px 3px 0;'),
                RCView::img(array('src'=>'coin.png')) .
                $lang['control_center_333'] . $lang['colon']
            ) .
            '<input id="apiTokenId" value="'.$token.'" onclick="this.select();" readonly="readonly" class="staticInput text-dangerrc" style="float:left;font-size:14px;width:80%;max-width:340px;margin-bottom:5px;margin-right:5px;">
            <button class="btn btn-defaultrc btn-xs btn-clipboard" title="'.js_escape2($lang['global_137']).'" data-clipboard-target="#apiTokenId" style="float:left;padding:3px 8px 3px 6px;"><i class="fas fa-paste"></i></button>'
    );
$tok .= RCView::div(array('style' => 'margin:5px 0;'),
			RCView::button(array('class' => 'jqbuttonmed', 'onclick'=>"simpleDialog(null,null,'deleteTokenDialog',500,null,'".js_escape($lang['global_53'])."','deleteToken()','".js_escape($lang['edit_project_96'])."');"), $lang['edit_project_116']). '&nbsp; ' . $lang['edit_project_117']);
// Hidden delete dialog
$tok .= RCView::div(array('id'=>'deleteTokenDialog', 'title'=>$lang['edit_project_111'], 'class'=>'simpleDialog'),
			$lang['edit_project_112'].
			(!MobileApp::userHasInitializedProjectInApp(USERID, PROJECT_ID) ? '' :
				RCView::div(array('style'=>'margin-top:10px;font-weight:bold;color:#C00000;'), $lang['mobile_app_36']))
		);
$tok .= RCView::div(array('style' => 'margin:5px 0;'),
			RCView::button(array('class' => 'jqbuttonmed', 'onclick'=>"simpleDialog(null,null,'deleteRegenDialog',500,null,'".js_escape($lang['global_53'])."','regenerateToken()','".js_escape($lang['edit_project_97'])."');"), $lang['edit_project_118']) . '&nbsp; ' . $lang['edit_project_119']);
// Hidden regen dialog
$tok .= RCView::div(array('id'=>'deleteRegenDialog', 'title'=>$lang['edit_project_113'], 'class'=>'simpleDialog'),
			$lang['edit_project_114'].
			(!MobileApp::userHasInitializedProjectInApp(USERID, PROJECT_ID) ? '' :
				RCView::div(array('style'=>'margin-top:10px;font-weight:bold;color:#C00000;'), $lang['mobile_app_36']))
		);
$tok .= RCView::div(array('style' => 'margin:20px 0 5px;', 'class'=>(SUPER_USER ? '' : 'hidden')),
					$lang['edit_project_115'] . '&nbsp; ' .
					RCView::span(array('id' => 'apiTokenUsersId', 'class' => 'code'), ''));
$dummy .= RCView::div(array('id' => 'apiTokenBoxId', 'class' => 'redcapAppCtrl', 'style' => 'display: none;'), $tok);
// API token request
$userInfo = $db->getUserInfoByUsername($userid);
$requestAuto = ($api_token_request_type == 'auto_approve_all' || ($api_token_request_type == 'auto_approve_selected' && $userInfo->api_token_auto_request == '1')) ? 1 : 0;
$req = '';
$req .= RCView::div(array('class' => 'chklisthdr'), $lang['api_139'] . ' "' . RCView::escape($app_title) . '"');
$req .= RCView::div(array('style' => 'margin:5px 0;'), ($requestAuto ? $lang['edit_project_183'] : $lang['edit_project_88']));
$ui_id = $userInfo->ui_id;
$todo_type = 'token access';
if(ToDoList::checkIfRequestExist($project_id, $ui_id, $todo_type) > 0){
	$reqAPIBtn = RCView::button(array('class' => 'api-req-pending'), $lang['api_03']);
	$reqP = RCView::p(array('class' => 'api-req-pending-text'), $lang['edit_project_179']);
}else{
	$reqAPIBtn = RCView::button(array('class' => 'jqbuttonmed', 'onclick' => "requestToken($requestAuto,0);"), ($requestAuto ? $lang['api_138'] : ($super_user ? $lang['api_08'] : $lang['api_03'])));
	$reqP = '';
}
$req .= RCView::div(array('class' => 'chklistbtn'), $reqAPIBtn.$reqP);
//if ($super_user && !defined("AUTOMATE_ALL")) {
//	$req .= RCView::br();
//	$approveLink = APP_PATH_WEBROOT . 'ControlCenter/user_api_tokens.php?action=createToken&api_username=' . $userid .
//		'&api_pid=' . $project_id . '&goto_proj=1';
//	$req .= RCView::button(array('onclick' =>"window.location.href='$approveLink';", 'class' => 'jqbuttonmed'), RCView::escape($lang['api_08'])) .
//	RCView::SP . RCView::span(array('style' => 'color: red;'), $lang['edit_project_77']);
//}
$dummy .= RCView::div(array('id' => 'apiReqBoxId', 'class' => 'redcapAppCtrl', 'style' => 'display: none;'), $req);

$h .= RCView::div(array('id' => 'apiDummyContainer'), $dummy);

// API Event names
$event_names = '';
$eventKeys = Event::getUniqueKeys($project_id);
$events = $db->getEvents($project_id);
// key the events by event ID
$tmp = array();
foreach ($events as $e) $tmp[$e->event_id] = $e;
$events = $tmp;
if ($longitudinal && count($events) > 0) {
	$eventRows = array($lang['edit_project_94'] . ' ' . RCView::span(array('style'=>'color:#800000;'), RCView::escape($app_title)));
	$eventRows[] = array($lang['define_events_65'], $lang['global_10'], $lang['global_08']);
	foreach ($eventKeys as $eventId => $eventKey) {
		$row = array();
		$row[] = RCView::font(array('class' => 'code'), RCView::escape($eventKey));
		$row[] = RCView::escape($events[$eventId]->descrip);
		$row[] = RCView::escape($events[$eventId]->arm_name);
		$eventRows[] = $row;
	}
	$event_names = RCView::div(array('style'=>'margin:20px 0;'), RCView::simpleGrid($eventRows, array(200, 200, 100)));
}

// If Data Access Groups exist, display them and their unique names here
$dag_names_table = '';
$dags = $Proj->getUniqueGroupNames();
if (!empty($dags))
{
	$dagRows = array($lang['data_access_groups_ajax_20'] . ' ' . RCView::span(array('style'=>'color:#800000;'), RCView::escape($app_title)));
	$dagRows[] = array($lang['data_access_groups_ajax_18'], $lang['data_access_groups_ajax_21']);
	foreach (array_combine($dags, $Proj->getGroups()) as $unique=>$label) {
		$dagRows[] = array(RCView::font(array('class' => 'code'), $unique), $label);
	}
	$dag_names_table = RCView::div(array('style'=>'margin:20px 0;'), RCView::simpleGrid($dagRows, array(200, 300)));
}

// If any modules enabled in the project provide API actions, list them 
$module_api_actions = "";
if (method_exists("ExternalModules\\ExternalModules", "getEnabledApiActions")) {
	// TODO: The wrapping guard clause should be removed after EM Framework support has been added
	$api_actions = \ExternalModules\ExternalModules::getEnabledApiActions($Proj->project_id);
	if (!empty($api_actions)) {
		$api_actions_title = RCView::p([], 
			RCView::fa("fa-solid fa-circle-info me-1") . 
			RCView::tt_i("api_218", [
				RCView::span(
					[
						"style" => "color:#800000;"
					], 
					RCView::escape($app_title))
			], false, "b") .
			RCView::br() .
			RCView::tt("api_219", "div", ["class" => "yellow"])
		);
		$api_actions_table = ExternalModules\ExternalModules::getApiActionsInfoTableForEnabledModules($Proj->project_id);
		$module_api_actions = RCView::div(
			[
				"id" => "external-modules-api-actions",
				"style" => "margin:20px 0; max-width: 950px;"
			], 
			$api_actions_title . $api_actions_table
		);
	}
}

// display the page
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
loadJS('Libraries/clipboard.js');
?>
<script type='text/javascript'>
// Copy the API token to the user's clipboard
function copyTokenToClipboard(ob) {
    copyTextToClipboard($('#apiTokenId').val());
    // Create progress element that says "Copied!" when clicked
    var rndm = Math.random()+"";
    var copyid = 'clip'+rndm.replace('.','');
    var clipSaveHtml = '<span class="clipboardSaveProgress" id="'+copyid+'">Copied!</span>';
    $(ob).after(clipSaveHtml);
    $('#'+copyid).toggle('fade','fast');
    setTimeout(function(){
        $('#'+copyid).toggle('fade','fast',function(){
            $('#'+copyid).remove();
        });
    },2000);
}
$(function() {
	<?php if (empty($token)) { ?>
	$("#apiReqBoxId").show();
	<?php } else { ?>
	$("#apiTokenBoxId").show();
	$.get(app_path_webroot + "API/project_api_ajax.php",
		{ action: 'getTokens', pid: pid },
		function(data) { $("#apiTokenUsersId").html(data); }
	);
	<?php } ?>
	$("#reqAPIRegenId").click(function() {
		$("#apiDialogRegenId").dialog({ bgiframe: true, modal: true, width: 500, buttons: {
			Cancel: function() { $(this).dialog('close'); },
			'<?php echo js_escape($lang['edit_project_97']) ?>': function() { $(this).dialog('close'); regenerateToken(); }}}
		);
		return false;
	});
    // Copy the API key to the clipboard
    $('.btn-clipboard').click(function(){
        copyTokenToClipboard(this);
    });
});
</script>
<?php
// Title
renderPageTitle('<i class="fas fa-laptop-code me-1"></i>'.RCView::tt("setup_77"));
if (Design::isDraftPreview()) {
	print "<div class='yellow draft-preview-banner mt-2 mb-2'>
		<i class='fa-solid fa-triangle-exclamation text-danger draft-preview-icon me-2'></i>" .
		RCView::lang_i("draft_preview_16", [
			"<a style='color:inherit !important;' href='".APP_PATH_WEBROOT."Design/online_designer.php?pid=".PROJECT_ID."'>",
			"</a>"
		], false) . "
	</div>";
}

// Page instructions
echo $instr;
// Tabs to view "my token" or all users' tokens (super users only)
if (SUPER_USER) {
	$tabs = array('API/project_api.php'=>RCView::img(array('src'=>'coin.png')) . $lang['control_center_340'],
				  'API/project_api.php?allUserTokens=1'=>RCView::img(array('src'=>'coins.png')) . $lang['control_center_341']);
	RCView::renderTabs($tabs);
}
if (SUPER_USER && isset($_GET['allUserTokens'])) {
	// Get JS dependencies
	loadJS('Libraries/underscore-min.js');
	loadJS('Libraries/backbone-min.js');
	loadJS('RedCapUtil.js');
	print RCView::div(array('style'=>'max-width:700px;'), $lang['control_center_342']);
	// List table of all users with token (super users only)
	include APP_PATH_DOCROOT . 'ControlCenter/user_api_tokens.php';
} else {
	// Box with user's API token and options
	echo $h;
	// Event and DAG tables
	echo $event_names;
	echo $dag_names_table;
	// Module API actions
	echo $module_api_actions;
}


// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
