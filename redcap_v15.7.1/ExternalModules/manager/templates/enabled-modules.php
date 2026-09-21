<?php
namespace ExternalModules;
set_include_path('.' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../redcap_connect.php';

use Exception;

ExternalModules::ensureFrameworkDevCopyIsUpToDate();
ExternalModules::addResource('css/style.css');

global $lang;
$pid = ExternalModules::getProjectId();

$versionsByPrefix = ExternalModules::getEnabledModules($pid);

$oldExampleModulePrefix = 'vanderbilt_configurationExample';
if(isset($versionsByPrefix[$oldExampleModulePrefix])){
	ExternalModules::setSystemSetting($oldExampleModulePrefix, ExternalModules::KEY_VERSION, 'v1.0');
	ExternalModules::query('update redcap_external_modules set directory_prefix = "module-development-examples" where directory_prefix = ?', $oldExampleModulePrefix);
	?>
	<script>location.reload()</script>
	<?php
	return;
}

uksort($versionsByPrefix, function($a, $b){
	$a = ExternalModules::getConfig($a);
	$b = ExternalModules::getConfig($b);

	return strcasecmp($a['name'], $b['name']);
});

?>

<div id="external-modules-download" class="simpleDialog" role="dialog">
	<!--= Do you wish to download the External Module named <b>{0}</b>? This will create a new directory folder for the module on the REDCap web server. -->
	<?=ExternalModules::tt("em_manage_35", \RCView::escape(rawurldecode(urldecode($_GET['download_module_title'] ?? ''))))?>
</div>

<div id="external-modules-disable-confirm-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">
					<!--= Disable module? -->
					<?=ExternalModules::tt("em_manage_36")?> 
					<span class="module-name"></span>
				</h4>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<!--= Are you sure you wish to disable this module ({0}) [for the current project]? -->
				<?=ExternalModules::tt_raw($pid ? "em_manage_38" : "em_manage_37", '<b><span id="external-modules-disable-confirm-module-name"></span>_<span id="external-modules-disable-confirm-module-version"></span></b>')?>
			</div>
			<div class="modal-footer">
				<button data-dismiss="modal">
					<!--= Cancel -->
					<?=ExternalModules::tt("em_manage_12")?>
				</button>
				<button id="external-modules-disable-button-confirmed" class="save">
					<!--= Disable module -->
					<?=ExternalModules::tt("em_manage_39")?>
				</button>
			</div>
		</div>
	</div>
</div>

<div id="external-modules-disabled-modal" class="modal fade" role="dialog">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title clearfix">
					<div class="float-left"><?=ExternalModules::tt('em_manage_130')?></div>
					<div class="float-right" style="margin-left:50px;">
						<!--= Search available modules -->
						<input type="text" id="disabled-modules-search" class="quicksearchsm" placeholder="<?=ExternalModules::tt("em_manage_40")?>" autofocus>
					</div>
				</h4>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<form>
				</form>
			</div>
		</div>
	</div>
</div>

<div id="external-modules-usage-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"></h4>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<div class="module-usage-project-list"></div>
				<button style='margin-top: 20px'>Export List With Design Rights Users</button>
				<script>
					$('#external-modules-usage-modal .modal-body button').click(() => {
						location.href = 'ajax/download-usage.php?prefix=' + ExternalModules.currentPrefix
					})
				</script>
			</div>
		</div>
	</div>
</div>

<style>
	.external-modules-usage-button{
		min-width: 90px;
	}

	#external-modules-export-settings-modal form,
	#external-modules-import-settings-modal form{
		margin-left: 25px;
	}

	#external-modules-import-settings-modal input[type=file]{
		width: 100%;
	}
</style>

<div id="external-modules-export-settings-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><?=ExternalModules::tt("em_manage_94") . ' ' . ExternalModules::tt("em_manage_98")?></h4>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<p><?=ExternalModules::tt("em_manage_95")?></p>
				<p style="color: #800000;"><em><?=ExternalModules::tt("em_manage_150")?></em></p>
				<br>
				<button class='select-all'><?=$lang['data_export_tool_52']?></button>
				<button class='deselect-all'><?=$lang['data_export_tool_53']?></button>
				<form>
					<div class='checkboxes'></div>
				</form>
			</div>
			<div class="modal-footer">
				<button class='export'><?=ExternalModules::tt("em_manage_94")?></button>
			</div>
		</div>
	</div>
</div>

<div id="external-modules-import-settings-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><?=ExternalModules::tt("em_manage_99") . ' ' . ExternalModules::tt("em_manage_98")?></h4>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<p><?=ExternalModules::tt("em_manage_110")?></p>
				<input type='file'><br>
				<p style="color: #800000;"><em><?=ExternalModules::tt("em_manage_151")?></em></p>
				<br>
				<p>
					<input type='checkbox' class='confirmation-checkbox'>
					<span><?=ExternalModules::tt("em_manage_109")?></span>
				</p>
			</div>
			<div class="modal-footer">
				<button class='import'><?=ExternalModules::tt("em_manage_99")?></button>
			</div>
		</div>
	</div>
</div>

<div id="external-modules-loading-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog modal-lg" style="width: 400px">
		<div class="modal-content">
			<div class="modal-body text-center">
				<h5 class="message"></h5>
				<div class="spinner" style="min-height: 70px; position: relative"></div>
			</div>
		</div>
	</div>
</div>

<p>
	<!--= External Modules are individual packages of software that can be downloaded and installed by a REDCap administrator. Modules can extend REDCap's current functionality, and can also provide customizations and enhancements for REDCap's existing behavior and appearance at the system level or project level. -->
	<?=ExternalModules::tt("em_manage_41")?>
</p>
<?php if (isset($pid) && ExternalModules::isSuperUser()) { ?>
<p>
	<!--= As a REDCap administrator, you may enable any module that has been installed in REDCap for this project. Some configuration settings might be required to be set, in which administrators or users in this project with Project Setup/Design privileges can modify the configuration of any module at any time after the module has first been enabled by an administrator. Note: Normal project users will not be able to enable or disable modules. -->
	<?=ExternalModules::tt("em_manage_42")?>
</p>
<?php } elseif (isset($pid) && !ExternalModules::isSuperUser()) { ?>
<p>
	<!--= As a user with Project Setup/Design privileges in this project, you can modify the configuration (if applicable) of any enabled module. Note: Only REDCap administrators are able to enable or disable modules. -->
	<?=ExternalModules::tt("em_manage_93")?>
</p>
<?php } else { ?>
<p>
	<!--= You may click the 'View modules' button below to navigate to the REDCap Repo (Repository of External Modules), which is a centralized catalog of curated modules that have been submitted by various REDCap partner institutions. If you find a module in the repository that you wish to download, you will be able to install it, enable it, and then set any configuration settings (if applicable). If you choose not to enable the module in all REDCap projects by default, then you will need to navigate to the External Modules page on the left-hand menu of a given project to enable it there for that project. Some project-level configuration settings, depending on the module, may also need to set on the project page. -->
	<?=ExternalModules::tt("em_manage_44")?>
</p>

<b><?=ExternalModules::tt('em_manage_119')?></b>
<?php ExternalModules::addResource('js/tests.js'); ?>
<ul>
	<li><a href="javascript:void(0)" class='external-module-security-scan-link'><?=ExternalModules::tt('em_manage_118')?></a></li>
	<script>
		$('.external-module-security-scan-link').click(() => {
			$.get(<?=json_encode(APP_URL_EXTMOD_RELATIVE . 'manager/ajax/scan-script-details.php')?>, (data) => {
				simpleDialog(data, <?=json_encode(ExternalModules::tt('em_manage_118'))?>, null, 650)
			})
		})
	</script>
	<?php if (!ExternalModules::isProduction()){ ?>
		<li><a href="#" onclick="ExternalModuleTests.run(this); return false">Run Module Framework JavaScript Unit Tests</a></li>
	<?php } ?>
</ul>

<?php 
// Display alert message in Control Center if any modules have updates in the REDCap Repo
ExternalModules::renderREDCapRepoUpdatesAlert();

ExternalModules::renderComposerCompatibilityIssues($versionsByPrefix);
?>

<?php } ?>

<?php if (isset($pid)) { ?>

<p style="color:#800000;font-size:11px;line-height:13px;">
	<!--= DISCLAIMER: Please be aware that External Modules are not part of the REDCap software but instead are add-on packages that, in most cases, have been created by software developers at other REDCap institutions. Be aware that the entire risk as to the quality and performance of the module as it is used in your REDCap project is borne by you and your local REDCap administator. If you experience any issues with a module, your REDCap administrator should contact the author of that particular module. -->
	<?=ExternalModules::tt("em_manage_45")?>
</p>

<?php
	// Show custom external modules text (optional)
	if (isset($GLOBALS['external_modules_project_custom_text']) && trim($GLOBALS['external_modules_project_custom_text']) != "") {
		print \RCView::div(array('id'=>'external_modules_project_custom_text', 'style'=>'max-width:800px;border:1px solid #ccc;background-color:#f5f5f5;margin:15px 0;padding:8px;'), nl2br(decode_filter_tags($GLOBALS['external_modules_project_custom_text'])));
	}

}
else{
	// Control Center

	if (!isVanderbilt() && defined("EXTMOD_EXTERNAL_INSTALL") && EXTMOD_EXTERNAL_INSTALL) { 
		?>
		<p class="yellow" style="max-width:600px;color:#800000;font-size:11px;line-height:13px;">
			<?=ExternalModules::tt("em_manage_46", APP_PATH_EXTMOD)?>
		</p>
		<?php
	}
}

if(ExternalModules::userCanEnableDisableModule()){
	$displayModuleDialogBtn = true;
	$moduleDialogBtnText = ExternalModules::tt("em_manage_48"); //= Enable a module
	$moduleDialogBtnImg = "fas fa-plus-circle";
}
else{
	$displayModuleDialogBtn = isset($pid) && ExternalModules::hasDiscoverableModules();
	$moduleDialogBtnText = ExternalModules::tt("em_manage_49"); //= View available modules
	$moduleDialogBtnImg = "fas fa-info-circle";
}

?>
<br>
<?php if($displayModuleDialogBtn) { ?>
	<button id="external-modules-enable-modules-button" class="btn btn-success btn-sm">
		<span class="<?=$moduleDialogBtnImg?>" aria-hidden="true"></span>
		<?=$moduleDialogBtnText?>
	</button> &nbsp; 
<?php } ?>

<?php if (isset($pid)) { ?>
	<button id="external-modules-export-settings-button" class="btn btn-primary btn-primaryrc btn-sm">
		<?=ExternalModules::tt("em_manage_94") . ' ' . ExternalModules::tt("em_manage_98")?>
	</button> &nbsp;
	<button id="external-modules-import-settings-button" class="btn btn-primary btn-primaryrc btn-sm">
		<?=ExternalModules::tt("em_manage_99") . ' ' . ExternalModules::tt("em_manage_98")?>
	</button> &nbsp;
<?php } elseif (ExternalModules::isAdminWithModuleInstallPrivileges()) { ?>
	<button id="external-modules-download-modules-button" class="btn btn-primary btn-primaryrc btn-sm">
		<span class="fas fa-download" aria-hidden="true"></span>
		<!--= View modules available in the REDCap Repo -->
		<?=ExternalModules::tt("em_manage_50")?>
	</button> &nbsp;
	<button id='external-modules-configure-crons'  class="btn btn-primary btn-defaultrc btn-sm">
		<span class="fas fa-calendar-alt" aria-hidden="true"></span>
		<!--= Configure Cron Start Times -->
		<?=ExternalModules::tt("em_manage_86")?>
	</button>
	<form id="download-new-mod-form" action="<?=APP_URL_EXTMOD_LIB?>login.php" method="post" enctype="multipart/form-data" target="_blank">
		<input type="hidden" name="user" value="<?=ExternalModules::getUsername()?>">
		<input type="hidden" name="name" value="<?=htmlspecialchars($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname'], ENT_QUOTES)?>">
		<input type="hidden" name="email" value="<?=htmlspecialchars($GLOBALS['user_email'] ?? '', ENT_QUOTES)?>">
		<input type="hidden" name="server" value="<?=SERVER_NAME?>">		
		<input type="hidden" name="referer" value="<?=htmlspecialchars(APP_URL_EXTMOD."manager/control_center.php", ENT_QUOTES)?>">
		<input type="hidden" name="php_version" value="<?=PHP_VERSION?>">
		<input type="hidden" name="redcap_version" value="<?=REDCAP_VERSION?>">		
		<input type="hidden" name="institution" value="<?=htmlspecialchars($GLOBALS['institution'], ENT_QUOTES)?>">
		<?php foreach (\ExternalModules\ExternalModules::getModulesInModuleDirectories() as $thisModule) { ?>
			<input type="hidden" name="downloaded_modules[]" value="<?=$thisModule?>">
		<?php } ?>
	</form>
<?php } ?>
<div style="display:flex;" class="mt-2">
	<?=ExternalModules::tt("em_manage_154")?> 
	<button class="btn btn-xs btn-rcred btn-rcred-light ms-2" id="external-modules-action-tags-popup-button" style="line-height: 14px;padding:1px 3px;font-size:11px;">@ Action Tags</button>
	<button class="btn btn-xs ms-1" id="external-modules-api-actions-popup-button" style="line-height: 14px;padding:1px 3px;font-size:11px;background-color:#17a2b8;color:white;"><?=ExternalModules::tt("em_manage_155") // API Methods?></button>
</div>
<script>
	document.querySelector('#external-modules-action-tags-popup-button').addEventListener('click', () => {
		showProgress(true)
		$.post(app_path_webroot+"Design/action_tag_explain.php?modules-only", { hideBtns: 1 }, function(data) {
			showProgress(false, 0)
			const json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
			if (json_data.length < 1) {
				alert(<?=ExternalModules::tt_js("em_errors_176")?>);
				return false;
			}
			simpleDialog(json_data.content,json_data.title,'action_tag_explain_popup',1000);
			fitDialog($('#action_tag_explain_popup'));
		});
	})
			
	document.querySelector('#external-modules-api-actions-popup-button').addEventListener('click', () => {
		showProgress(true)
		$.post('ajax/get-module-api-actions.php', { pid: pid }, function(response) {
			showProgress(false, 0)
			const data = JSON.parse(response);
			simpleDialog(data.content, data.title, 'api_actions_popup', 1000);
			fitDialog($('#api_actions_popup'));
		});
	})
</script>
<br>
<br>

<h4 class="clearfix" style="max-width: 800px;">
	<div class="float-left"><b>
	<?php 
		if (isset($pid)) {
			//= Currently Enabled Modules
			echo ExternalModules::tt("em_manage_51");
		} 
		else {
			//= Modules Currently Available on this System
			echo ExternalModules::tt("em_manage_52");
		}
	?>
	</b></div>
	<div class="float-right">
		<!--= Search enabled modules -->
		<input type="text" id="enabled-modules-search" class="quicksearch" placeholder="<?=ExternalModules::tt("em_manage_53")?>" autocomplete="off">
	</div>
</h4>

<script type="text/javascript">
	var override = '<?=ExternalModules::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS?>';
	var enabled = '<?=ExternalModules::KEY_ENABLED?>';
	var overrideSuffix = '<?=ExternalModules::OVERRIDE_PERMISSION_LEVEL_SUFFIX?>';
	$(function(){
		var searchField = $('input#enabled-modules-search')

		// Enable module search
		searchField.quicksearch('table#external-modules-enabled tbody tr', {
			selector: 'td:eq(0)',
            noResults: 'tr#module_no_enable_results'
		});

		// The focus() method is used here because the 'autofocus' attribute cannot be used since it interferes with the 'autofocus' attribute on the search in the disabled modules modal.
		searchField[0].focus()
	});
</script>

<table id='external-modules-enabled' class="table">
    <tr id="module_no_enable_results" style="display: none"><td><?=ExternalModules::tt('em_manage_149')?></td></tr>
	<?php

	$configsByPrefix = array();

	if (empty($versionsByPrefix)) {
		echo 'None';
	} else {
		$_SESSION['external-module-configure-buttons-displayed'] = [];
		foreach ($versionsByPrefix as $prefix => $version) {
			// Ensure that language strings for all modules are available.
			ExternalModules::initializeLocalizationSupport($prefix, $version);
			$config = ExternalModules::getConfig($prefix, $version, $pid, true);

			if(empty($config)){
				// This module's directory may have been removed while it was still enabled.
				$config = ExternalModules::getDefaultConfigForBrokenModule($prefix, ExternalModules::tt('em_manage_132'));
			}

			## Add resources for custom javascript fields
			foreach(array_merge($config['project-settings'],$config['system-settings']) as $configRow) {
				if($configRow['source'] ?? null) {
					$sources = explode(",",$configRow['source']);
					foreach($sources as $sourceLocation) {
						/** @psalm-suppress PossiblyFalseOperand **/
						if(file_exists(ExternalModules::getModuleDirectoryPath($prefix,$version)."/".$sourceLocation)) {
							// include file from module directory
							ExternalModules::addResource(ExternalModules::getModuleDirectoryUrl($prefix,$version).$sourceLocation);
						}
						else if(file_exists(dirname(__DIR__)."/js/".$sourceLocation)) {
							// include file from external_modules directory
							ExternalModules::addResource("js/".$sourceLocation);
						}
					}
				}
			}


			$configsByPrefix[$prefix] = $config;

			if(
				(
					isset($pid)
					&&
					ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::KEY_ENABLED)
					&&
					(
						ExternalModules::isAdminWithModuleInstallPrivileges()
						||
						!ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::KEY_RESERVED_HIDE_FROM_NON_ADMINS_IN_PROJECT_LIST)
					)
				)
				||
				(!isset($pid) && isset($config['system-settings']))
			){
			?>
				<tr data-module='<?= $prefix ?>' data-version='<?= $version ?>'>
					<td>
						<?php require __DIR__ . '/module-table.php'; ?>
					</td>
					<td class="external-modules-action-buttons">
						<?php
						if((!empty($config['project-settings']) || (!empty($config['system-settings']) && !isset($pid)))
						&& ((!isset($pid) && ExternalModules::isAdminWithModuleInstallPrivileges()) || (isset($pid) && ExternalModules::hasProjectSettingSavePermission($prefix)))){
							$_SESSION['external-module-configure-buttons-displayed'][] = $prefix;
							?><button class='external-modules-configure-button'><!--= Configure --><?=ExternalModules::tt("em_manage_54")?></button><?php
						}
						
						if(ExternalModules::userCanEnableDisableModule($prefix)) {
							?><button class='external-modules-disable-button'><!--= Disable --><?=ExternalModules::tt("em_manage_55")?></button><?php
						}

						if(!isset($pid)) {
							?><button class='external-modules-usage-button'><!--= View Usage --><?=ExternalModules::tt("em_manage_56")?></button><?php
						}
						?>
					</td>
				</tr>
			<?php
			}
		}
	}

	?>
</table>

<?php
global $configsByPrefixJSON,$versionsByPrefixJSON;

// JSON_PARTIAL_OUTPUT_ON_ERROR was added here to fix an odd conflict between field-list and form-list types
// and some Hebrew characters on the "Israel: Healthcare Personnel (Hebrew)" project that could not be json_encoded.
// This workaround allows configs to be encoded anyway, even though the unencodable characters will be excluded
// (causing form-list and field-list to not work for any fields with unencodeable characters).
// I spent a couple of hours trying to find a solution, but was unable.  This workaround will have to do for now.
$configsByPrefixJSON = $versionsByPrefixJSON = false;
if ($configsByPrefixJSON === false || $configsByPrefixJSON === null) {
	$configsByPrefixJSON = json_encode($configsByPrefix, JSON_PARTIAL_OUTPUT_ON_ERROR);
}
if($configsByPrefixJSON === false || $configsByPrefixJSON === null){
	//= An error occurred while converting the configurations to JSON: {0}
	echo '<script type="text/javascript">alert(' . ExternalModules::tt("em_errors_75", json_last_error_msg()) . ');</script>';
	throw new Exception(ExternalModules::tt("em_errors_75", json_last_error_msg())); 
}

if ($versionsByPrefixJSON === false || $versionsByPrefixJSON === null) {
	$versionsByPrefixJSON = json_encode($versionsByPrefix, JSON_PARTIAL_OUTPUT_ON_ERROR);
}
if ($versionsByPrefixJSON === false || $versionsByPrefixJSON === null) {
	//= An error occurred while converting the versions to JSON: {0}
	echo '<script type="text/javascript">alert(' . ExternalModules::tt("em_errors_76", json_last_error_msg()) . ');</script>';
	throw new Exception(ExternalModules::tt("em_errors_76", json_last_error_msg())); 
}

require_once 'globals.php';

$url = 'ajax/get-configure-button-visibility.php' . ( empty($pid) ? '' : '?pid=' . intval($pid) );
?>
<script type="text/javascript">
	ExternalModules.initModuleTable()
	ExternalModules.moduleDependentRequest('<?=$url?>', function(response){
		for(var i in response){
			var prefix = response[i]
			$('tr[data-module=' + prefix + '] .external-modules-configure-button').hide()
		}
	})
</script>
