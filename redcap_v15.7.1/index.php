<?php
use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManager;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenStatusHelper;

if (isset($_GET['pid'])) {
	require_once 'Config/init_project.php';
} else {
	require_once 'Config/init_global.php';
}
// Routing to controller: Check "route" param in query string (if exists)
$Route = new Route();
// If no pid is provided, then redirect to the REDCap Home page
if (!isset($_GET['pid'])) System::redirectHome();

/**
 * get a list of users with a boolean indicating access
 * to the EHR system
 *
 * @param int $project_id
 * @return array
 */
function getTokenStatusForUsersInProject($project_id) {
	$fhirTokens = FhirTokenManager::getAccessTokensForUsersinProject($project_id);
	// remove token information: just say if it is valid
	$statuses = [];
	foreach ($fhirTokens as $user => $fhirToken) {
		$statuses[$user] = ($fhirToken instanceof FhirTokenDTO) ? $fhirToken->getStatus() : null;
	}
	return $statuses;
}

// Header and tabs
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
if (isset($_GET["msg"]) && $_GET["msg"] == "draft-preview-no-new-records") {
	print "<div class='red mt-2' style='max-width:800px;'>".
				RCIcon::ErrorNotificationTriangle("me-1 text-danger").
				RCView::tt_i("draft_preview_09", [
					"<a style='color:inherit !important;' href='".APP_PATH_WEBROOT."Design/online_designer.php?pid=".PROJECT_ID."'>",
					"</a>"
				], false).
		  "</div>";
	print RCView::script("modifyURL(removeParameterFromURL(window.location.href, 'msg'));");

}
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
loadJS('Calendar.js');

// REDCap Hook injection point: Pass project_id to method
Hooks::call('redcap_project_home_page', array(PROJECT_ID));

// Determine if project is being used as a template project
$templateInfo = ProjectTemplates::getTemplateList($project_id);
$isTemplate = (!empty($templateInfo));
if ($isTemplate) {
	// Edit/remove template
	$templateTxt =  RCView::img(array('src'=>($templateInfo[$project_id]['enabled'] ? 'star.png' : 'star_empty.png'))) .
					RCView::span(array('style'=>'margin-right:10px;vertical-align: middle;'), $lang['create_project_91']) .
					RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:none;','onclick'=>"projectTemplateAction('prompt_addedit',$project_id)"),
						RCView::img(array('src'=>'pencil.png','title'=>$lang['create_project_90']))
					) .
					RCView::SP .
					RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:none;','onclick'=>"projectTemplateAction('prompt_delete',$project_id)"),
						RCView::img(array('src'=>'cross.png','title'=>$lang['create_project_93']))
					);
	$templateClass = 'yellow';
} else {
	// Add as template
	$templateTxt =  RCView::img(array('src'=>'star_empty.png')) .
					RCView::span(array('style'=>'margin-right:10px;vertical-align: middle;'), $lang['create_project_92']) .
					RCView::button(array('class'=>'btn btn-defaultrc btn-xs','style'=>'font-size:11px;margin-top:2px;','onclick'=>"projectTemplateAction('prompt_addedit',$project_id)"),
						$lang['design_171'] . RCView::SP
					);
	$templateClass = 'chklist';
}
$templateTxt = RCView::div(array('class'=>$templateClass,'style'=>'margin:0 0 4px;padding:2px 10px 4px 8px;float:right;'), $templateTxt);
// Data Query Tool link (SUPER USER only) 
$can_access_dqt = UserRights::isSuperUserNotImpersonator() && $GLOBALS['database_query_tool_enabled'] == '1';
if ($can_access_dqt) {
	$gotoDQT = 
		'<button type="button" class="btn btn-defaultrc btn-xs me-2 mt-1" onclick="window.open(\''.APP_PATH_WEBROOT_FULL.'redcap_v'.REDCAP_VERSION.'/ControlCenter/database_query_tool.php?table=redcap_data&project-id='.PROJECT_ID.'\', \'_blank\');"><i class="fa-solid fa-database me-1"></i> '.
		RCView::tt("control_center_4803"). // Database Query Tool
		' <i class="ms-1 fs9 fa-solid fa-arrow-up-right-from-square"></i></button>';
}

// Warning about using Randomization module with DDE
if ($double_data_entry && $randomization && Randomization::setupStatus()) {
    print RCView::div(array('class'=>'yellow'),
            '<i class="fas fa-exclamation-triangle"></i> '. RCView::b($lang['global_48']) . RCView::br() . $lang['data_entry_470']
          );
}

?>

<!-- PROJECT DASHBOARD -->
<div style="max-width:800px;">
	<div class="d-flex flex-wrap gap-2">
		<div class="clearfix mb-3">
			<div class="float-start mb-2"><?php echo $lang['index_70'] ?></div>
			<?php if (ACCESS_SYSTEM_CONFIG && !UserRights::isImpersonatingUser()) { ?><div class="float-end"><?php echo $templateTxt ?></div><?php } ?>
			<?php if ($can_access_dqt) { ?><div class="float-start"><?=$gotoDQT?></div><?php } ?>
		</div>
<?php

/**
 * USER TABLE
 */

// Loop through user rights
$user_list = $proj_users = array();
$user_rights_all = UserRights::getPrivileges($project_id);
if (isset($user_rights_all[$project_id])) {
	$user_rights_all = $user_rights_all[$project_id];
	foreach ($user_rights_all as $this_user=>$attr) {
		$proj_users[] = $this_user = strtolower($this_user);
		$user_list[$this_user]['expiration']  = $attr['expiration'];
		$user_list[$this_user]['double_data'] = $attr['double_data'];
	}
}


// Get users' email, name, and suspension status
$user_info = array();
$q = db_query("select username, user_email, user_firstname, user_lastname, if(user_suspended_time is null, '0', '1') as suspended
			   from redcap_user_information where username in (".prep_implode($proj_users).")");
while ($row = db_fetch_array($q)) {
	$row['username'] = strtolower($row['username']);
	$user_info[$row['username']]['user_email'] 		= $row['user_email'];
	$user_info[$row['username']]['user_firstlast'] 	= $row['user_firstname'] . " " . $row['user_lastname'];
	$user_info[$row['username']]['suspended'] 		= $row['suspended'];
}
//Loop through user list to render each row of users table
$i = 0;
// get list of user with matching access to EHR system if FHIR enabled
$fhirEnabled = FhirEhr::isFhirEnabledInProject($project_id);
$tokensStatusForUsers = $fhirEnabled ? getTokenStatusForUsersInProject($project_id) : [];
$activeIcon = '<i class="fas fa-circle-check fa-fw text-success"></i>';
$inactiveIcon = '<i class="fas fa-times-circle fa-fw text-danger"></i>';

foreach ($user_list as $this_user=>$row) {
	//Render expiration date, if exists (expired users will display in red)
	if ($row['expiration'] == '') {
		$row['expiration'] = "<span style=\"color:gray\">{$lang['index_37']}</span>";
	} else {
		if (str_replace("-","",$row['expiration']) < date('Ymd')) {
			$row['expiration'] = "<span style=\"color:red\">".DateTimeRC::format_user_datetime($row['expiration'], 'Y-M-D_24')."</span>";
		} else {
			$row['expiration'] = DateTimeRC::format_user_datetime($row['expiration'], 'Y-M-D_24');
		}
	}
	// Add text if user is suspended
	$suspendedText = ((!isset($user_info[$this_user]) || !$user_info[$this_user]['suspended'])) ? '' :
						RCView::div(array('class'=>'text-dangerrc fs11'),
							$lang['rights_281']
						);
	//If user's name and email are recorded, display their name and email
	if (isset($user_info[$this_user])) {
		$name_email = "<span class='ms-2'>(<a href=\"mailto:".$user_info[$this_user]['user_email']."\">".js_escape($user_info[$this_user]['user_firstlast'])."</a>)</span>";
	} else {
		$name_email = "";
	}
	$row_data[$i][] = RCView::escape($this_user) . $name_email . $suspendedText;
	$row_data[$i][] = $row['expiration'];
	if($fhirEnabled) {
		$accessTokenStatus = $tokensStatusForUsers[$this_user] ?? false;
		$ehrAccessHTML = '<span>'.FhirTokenStatusHelper::getIcon($accessTokenStatus).'</span>';
		$row_data[$i][] = $ehrAccessHTML;
	}
	if ($double_data_entry) {
		if ($row['double_data'] == 0) $double_data_label = $lang['rights_51']; else $double_data_label = "#" . $row['double_data'];
		$row_data[$i][] = $double_data_label;
	}
	$i++;
}

$title = "<div style=\"display: flex; justify-content: space-between; align-items: center; padding:0;\">
    <div><i class=\"fas fa-user\"></i> {$lang['index_19']}<span style=\"margin-left:7px;font-weight:normal;\">($i)</span></div>
	<div class=\"dropdown\" id=\"currentUsersCSVDropdown\">
		<i class=\"fas fa-file-arrow-down\"  data-bs-toggle=\"dropdown\" aria-expanded=\"false\" style=\"cursor: pointer;\"></i>
		<ul class=\"dropdown-menu dropdown-menu-light p-1\" id=\"currentUsersCSVDropdownMenu\">
			<li>
				<a class=\"dropdown-item d-flex align-items-center\" href=\"#\" onclick=\"downloadCurrentUsersList()\">
					<img src=\"". APP_PATH_IMAGES ."xls.gif\" 
						alt=\"Excel Icon\" 
						class=\"me-2 align-middle\"> "
						. RCView::tt('index_73') .
					"</a>
			</li>
		</ul>
	</div>
</div>";

$col_widths_headers = array(
						array($lang['global_17'], "left"),
						array($lang['index_35'], "center")
					  );
if($fhirEnabled) {
	$col_widths_headers[] = [$lang['control_center_4895'], 'center'];
}
if ($double_data_entry)
{
	$col_widths_headers[] = array(RCView::div(array('class'=>'wrap'), $lang['index_36']), "left");
}
print '<div class="flex-grow-1 fs12">';
renderTable("user_list", $title, $col_widths_headers, $row_data);
print "</div>";


/**
 * PROJECT STATISTICS TABLE
 */
$title = '<div><i class="fas fa-clipboard-list"></i> ' . $lang['index_27'] . '</div>';

$file_space_usage_text = "<span style='cursor:pointer;cursor:hand;' onclick=\"simpleDialog(null,null,'fileuse_explain')\">{$lang['index_56']}</span>
						<div id='fileuse_explain' class='simpleDialog' title=\"".RCView::tt_js2('index_56')."\">
						{$lang['index_74']}
						</div>";

$col_widths_headers = array(
						array('', "left"),
						array('', "center")
					  );
$row_data = array(
				array($lang['index_22'], "<span id='projstats1'><span style='color:#888;'>{$lang['data_entry_64']}</span></span>"),
				//array($lang['index_23'], $num_data_exports),
				//array($lang['index_24'], $num_logged_events),
				array($lang['index_25'], "<span id='projstats2'>".($last_logged_event != "" ? DateTimeRC::format_user_datetime($last_logged_event, 'Y-M-D_24') : "<span style='color:#888;'>{$lang['data_entry_64']}</span>")."</span>"),
				array($file_space_usage_text, "<span id='projstats3'><span style='color:#888;'>{$lang['data_entry_64']}</span></span>")
			);
if ($double_data_entry)
{
	$row_data[] = array($lang['global_04'], $lang['index_30']);
}

print '<div class="flex-grow-1 fs12">';

// Render the table
print "<div>";
renderTable("stats_table", $title, $col_widths_headers, $row_data, false);
print "</div>";

/**
 * UPCOMING EVENTS TABLE
 * List any events scheduled on the calendar in the next 7 days (if any)
 */
// Do not show the calendar events if don't have access to calendar page
if ($user_rights['calendar']) print Calendar::renderUpcomingAgenda(7);

?>
</div>
</div>


<?php if(FhirEhr::isFhirEnabledInProject($project_id)) : ?>

	<hr>
	<div class="mb-2 flex-grow-1">
		<div class="card">
			<div class="card-header">
				<span ><strong><?= Language::tt('cdis_info_ehr_access_info_title') ?></strong></span>
			</div>
			<div class="card-body p-2">
			<?php require(APP_PATH_DOCROOT.'CDIS/partials/ehr_access_info.php') ?>
			</div>
		</div>
	</div>

<?php endif; ?>
</div>
<script type="text/javascript">
// AJAX call to fetch the stats table values
$(function(){
	$.get(app_path_webroot+'ProjectGeneral/project_stats_ajax.php', { pid: pid }, function(data){
		if (data!='0') {
			var json = jQuery.parseJSON(data);
			$('#projstats1').html(json[0]);
			$('#projstats2').html(json[1]);
			$('#projstats3').html(json[2]);
		}
	});
});
</script>
<?php


if(FhirEhr::isFhirEnabledInProject($project_id)) :
	$tokenStatusForUsers = getTokenStatusForUsersInProject($project_id);
?>

<script type="module">
	import {useModal} from '<?= APP_PATH_JS.'Composables/index.es.js.php' ?>'
	(function(global) {
		const modal = useModal()
		
		const showContent = (selector) => {
			const target = document.querySelector(selector)
			if(!target) return
			const content = target.innerHTML
			const title = target.getAttribute('data-title') ?? 'Info'
			modal.show({
				title: title,
				body: content,
				cancelText: null,
			})
		}
	})(window)
</script>
<?php endif; ?>
<?php

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
