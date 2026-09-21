<?php

namespace Vanderbilt\REDCap\ControlCenter;

use RCView;
use System;
use Logging;
use Renderer;

// If auto-finding FHIR token/authorize URLs
if (isset($_POST['url'])) 
{
	// Config for non-project pages
	require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
	// Call the URL
	$headers = array("Accept: application/json");
	$response = http_get($_POST['url'], 10, "", $headers);
	$metadata = json_decode($response, true);
	if (!is_array($metadata)) exit('0');
	// Get authorize endpoint URL and token endpoint URL
	$authorizeUrl = $tokenUrl = "";
	foreach ($metadata['rest'][0]['security']['extension'][0]['extension'] as $attr) {
		if ($attr['url'] == 'authorize') {
			$authorizeUrl = $attr['valueUri'];
		} elseif ($attr['url'] == 'token') {
			$tokenUrl = $attr['valueUri'];
		}
	}
	if ($authorizeUrl == "" || $tokenUrl == "") exit('0');
	// Return URLs
	exit("$authorizeUrl\n$tokenUrl");
}

include 'header.php';
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);
if (!ACCESS_SYSTEM_CONFIG) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); });</script>";

$saveMessages = [];

// If project default values were changed, update redcap_config table with new values
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ACCESS_SYSTEM_CONFIG)
{
	// store current settings for comparison
	$currentSettings = System::getConfigVals();
	/**
	 * run after that settings are updated
	 */
	$checkClientIdChanged = function($currentSettings, $newSettings) {
		$currentClientId = $currentSettings['fhir_client_id'] ?? null;
		$newClientId = $newSettings['fhir_client_id'] ?? null;
		if($currentClientId==$newClientId) return false;
		$query_string = 'DELETE FROM `redcap_ehr_access_tokens`';
		$result = db_query($query_string);
		if($result) Logging::logEvent($query_string,"redcap_ehr_access_tokens","MANAGE","",'',"Existing access tokens have been removed becuase the client ID is changed.");
		return $result;
	};


	$changes_log = array();
	$sql_all = array();
	foreach ($_POST as $this_field=>$this_value) {
		// Save this individual field value
		$sql = "UPDATE redcap_config SET value = '".db_escape($this_value)."' WHERE field_name = '".db_escape($this_field)."'";
		$q = db_query($sql);

		// Log changes (if change was made)
		if ($q && db_affected_rows() > 0) {
			if ($this_value != "" && in_array($this_field, System::$encryptedConfigSettings)) {
                $this_value = '[REDACTED]';
                $sql = "UPDATE redcap_config SET value = '".db_escape($this_value)."' WHERE field_name = '".db_escape($this_field)."'";
            }
            $sql_all[] = $sql;
			$changes_log[] = "$this_field = '$this_value'";
		}
	}

	$saveMessages[] = $lang['control_center_19'];

	// Log any changes in log_event table
	if (count($changes_log) > 0) {
		Logging::logEvent(implode(";\n",$sql_all),"redcap_config","MANAGE","",implode(",\n",$changes_log),"Modify system configuration");
		// check if the client ID has been changed
		$accessTokensDeleted =$checkClientIdChanged($currentSettings, $_POST);
		if($accessTokensDeleted) $saveMessages[] = 'Please note that existing access tokens were removed because the FHIR client ID has been updated.';
	}

}

// Retrieve data to pre-fill in form
$element_data = System::getConfigVals();


/**
 * CREATE A BLADE TEMPLATING MANAGER
 * @return BladeOne
 */
$makeBladeSettingsInstance = function($lang, $configVals) {
	$blade = Renderer::getBlade();
	$blade->share('form_data', $configVals); // the the options available for all views
	return $blade;
};
$blade = $makeBladeSettingsInstance($lang, $element_data);

// Set values if they are invalid
if (!is_numeric($element_data['fhir_stop_fetch_inactivity_days']) || $element_data['fhir_stop_fetch_inactivity_days'] < 1) {
	$element_data['fhir_stop_fetch_inactivity_days'] = 7;
}
if (!is_numeric($element_data['fhir_data_fetch_interval']) || $element_data['fhir_data_fetch_interval'] < 1) {
	$element_data['fhir_data_fetch_interval'] = 24;
}

?>


<div style="font-size:18px;">
	<h4 class="float-start fs18" style="margin-top:10px;"><i class="fas fa-fire"></i> <?= $lang['ws_262'] ?></h4>
	<div class="float-end" style="margin-right:30px;">
		<?= RCView::img(array('src'=>'ehr_fhir.png')) ?>
	</div>
</div>
<div class="clear"></div>

<?php
if (!empty($saveMessages)) :
	$messagesList = "\n<p>".implode("</p>\n<p>", $saveMessages)."<p>\n";
	// Show user message that values were changed
?>
	<div class="mt-2 alert alert-success alert-dismissible fade show" role="alert">
		<span type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></span>
		<p>
			<i class="fas fa-exclamation-circle"></i>
			<strong>Success!</strong>
		</p>
		<?php foreach ($saveMessages as $message) : ?>
			<p><?php print $message; ?></p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<!-- display Epic upgrade alert -->
<?= $blade->run('control-center.cdis.epic-update-info') ?>
<?= $redcapAppVersion??"" ?>


<!-- resources -->
<p> <?= $lang['ws_207'] . " " . $lang['ws_297'] ?> </p>
<p><?= $lang['ws_317'] ?></p>
<div class="card mb-4">
	<div class="card-body">
		<h6 class="card-title"><?= $lang['ws_335'] ?></h6>
		<div style="text-decoration:underline;" class="d-flex flex-column gap-2">
			<a href="<?= APP_PATH_WEBROOT."Resources/misc/redcap_fhir_overview.pdf" ?>" target="_blank" >
				<i class="fas fa-file-pdf fa-fw"></i><span class="ms-2"><?= $lang['ws_296'] ?></span>
			</a>
			<a href="<?= APP_PATH_WEBROOT."DynamicDataPull/info.php?type=fhir" ?>" target="_blank" >
				<i class="fas fa-info-circle fa-fw"></i><span class="ms-2"><?= $lang['ws_266'] ?></span>
			</a>
			<a href="<?= APP_PATH_WEBROOT."Resources/misc/redcap_fhir_setup.zip" ?>" target="_blank" download>
				<i class="fas fa-file-archive fa-fw"></i><span class="ms-2"><?= $lang['ws_236'] ?></span>
			</a>
			<a href="#" onclick="simpleDialog(null,null,'cdis-diff',1000);fitDialog($('#cdis-diff'));return false;">
				<i class="fas fa-lightbulb fa-fw"></i><span class="ms-2"><?= $lang['ws_294'] ?></span>
			</a>
			<a href="https://redcap.link/mappingrequest" target="_blank">
				<i class="fas fa-tasks fa-fw"></i><span class="ms-2"><?= $lang['ws_336'] ?></span>
			</a>
			<a href="<?= APP_PATH_WEBROOT."Resources/misc/redcap_fhir_metadata_DSTU2.csv" ?>" target="_blank" download>
				<i class="fas fa-file-csv fa-fw"></i><span class="ms-2"><?= $lang['ws_334'] ?> (DSTU2)</span>
			</a>
			<a href="<?= APP_PATH_WEBROOT."Resources/misc/redcap_fhir_metadata_R4.csv" ?>" target="_blank" download>
				<i class="fas fa-file-csv fa-fw"></i><span class="ms-2"><?= $lang['ws_334'] ?> (R4)</span>
			</a>
		</div>
	</div>
</div>
<!-- end resources -->

<!-- CDP vs Data Mart dialog -->
<style type="text/css">
    #cdis-diff {display:none;}
    #cdis-diff table {background-color: #fff;}
    #cdis-diff td {padding:7px 10px;}
    #cdis-diff ul {margin:0px;margin-block-start:0em;margin-block-end:0em;padding-inline-start:10px;}
</style>
<div id="cdis-diff" class="mt-2 simpleDialog" title="<?=js_escape2($lang['ws_294'])?>">
	<table class="table">
		<thead>
			<tr>
				<th scope="col">
				</th>
				<th scope="col" class="boldish clearfix">
					<div class="float-start fs15 mt-1" style="color:#000066;">
						<i class="fas fa-database"></i>
						<?= $lang['ws_265'] ?>
					</div>
					<div class="float-end">
						<button class="btn btn-xs invisible"><?=$lang['scheduling_35']?></button>
					</div>
				</th>
				<th scope="col" class="boldish clearfix">
					<div class="float-start fs15 mt-1" style="color:#A00000;">
						<i class="fas fa-shopping-cart"></i>
						<?= $lang['global_155'] ?>
					</div>
					<div class="float-end">
						<button class="btn btn-xs btn-defaultrc" onclick="$('#cdis-diff button').hide();$('#cdis-diff td, #cdis-diff th').css({'padding-bottom':'10px','vertical-align':'top','font-family':'arial'});printDiv('cdis-diff');return false;"><?=$lang['scheduling_35']?></button>
					</div>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<th class="boldish" scope="row">
					<?= $lang['cdis_diff_category_1'] ?>
				</th>
				<td>
					<ul>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_1'] ?></li>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_2'] ?></li>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_3'] ?></li>
					</ul>
				</td>
				<td>
					<ul>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_1'] ?></li>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_2'] ?></li>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_3'] ?></li>
					</ul>
				</td>
			</tr>
			<tr>
				<th class="boldish" scope="row">
					<?= $lang['cdis_diff_category_2'] ?>
				</th>
				<td>
					<ul>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_4'] ?></li>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_5'] ?></li>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_6'] ?></li>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_7'] ?></li>
					</ul>
				</td>
				<td>
					<ul>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_4'] ?></li>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_5'] ?></li>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_6'] ?></li>
					</ul>
				</td>
			</tr>
			<tr>
				<th class="boldish" scope="row">
					<?= $lang['cdis_diff_category_3'] ?>
				</th>
				<td>
					<ul>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_8'] ?></li>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_9'] ?></li>
					</ul>
				</td>
				<td>
					<ul>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_7'] ?></li>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_8'] ?></li>
					</ul>
				</td>
			</tr>
			<tr>
				<th class="boldish" scope="row">
					<?= $lang['cdis_diff_category_4'] ?>
				</th>
				<td>
					<ul>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_10'] ?></li>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_11'] ?></li>
					</ul>
				</td>
				<td>
					<ul>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_9'] ?></li>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_10'] ?></li>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_11'] ?></li>
					</ul>
				</td>
			</tr>
			<tr>
				<th class="boldish" scope="row">
					<?= $lang['cdis_diff_category_5'] ?>
				</th>
				<td>
					<ul>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_12'] ?></li>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_13'] ?></li>
						<li class="diff_ddp"><?= $lang['cdis_diff_ddp_14'] ?></li>
					</ul>
				</td>
				<td>
					<ul>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_12'] ?></li>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_13'] ?></li>
						<li class="diff_ddm"><?= $lang['cdis_diff_ddm_14'] ?></li>
					</ul>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<!-- END CDP vs Data Mart dialog -->

<div id="cdis-settings"></div>
<style>
	#cdis-settings-wrapper {
		border: 1px solid #ccc;
		background-color: #f0f0f0;
		width: 100%;
	}
	#cdis-settings-wrapper .settings-title {
		color: #C00000;
		font-weight: bold;
	}
	#cdis-settings-wrapper .settings-subtitle {
		font-weight: bold;
	}
	#cdis-settings-wrapper .row + .row{
		padding: 10px 0;
	}
</style>
<?php loadJS('Libraries/clipboard.js'); ?>
<script>
// Function to test the URL via web request and give popup message if failed/succeeded
function validateUrl(ob) {
	ob = $(ob);
	ob.val( trim(ob.val()) );
	var url = ob.val();
	if (url.length == 0) return;
	// Get or set the object's id
	if (ob.attr('id') == null) {
		var input_id = "input-"+Math.floor(Math.random()*10000000000000000);
		ob.attr('id', input_id);
	} else {
		var input_id = ob.attr('id');
	}
	// Disallow localhost
	var localhost_array = new Array('localhost', 'http://localhost', 'https://localhost', 'localhost/', 'http://localhost/', 'https://localhost/');
	if (in_array(url, localhost_array)) {
		simpleDialog('<?= js_escape($lang['edit_project_126']) ?>','<?= js_escape($lang['global_01']) ?>',null,null,"$('#"+input_id+"').focus();");
		return;
	}
	// Validate URL
	if (!isUrl(url)) {
		if (url.substr(0,4).toLowerCase() != 'http' && isUrl('http://'+url)) {
			// Prepend 'http' to beginning
			ob.val('http://'+url);
			// Now test it again
			validateUrl(ob);
		} else {
			// Error msg
			simpleDialog('<?= js_escape($lang['edit_project_126']) ?>','<?= js_escape($lang['global_01']) ?>',null,null,"$('#"+input_id+"').focus();");
		}
	}
}
// Perform the setup for testUrl()
function setupTestUrl(ob) {
	if (ob.val() == '') {
		ob.focus();
		return false;
	}
	// Get or set the object's id
	if (ob.attr('id') == null) {
		var input_id = "input-"+Math.floor(Math.random()*10000000000000000);
		ob.attr('id', input_id);
	} else {
		var input_id = ob.attr('id');
	}
	// Test it
	testUrl(ob.val(),'post',"$('#"+input_id+"').focus();");
}
// Auto-find the FHIR authorize and token URLs using base URL
var foundFhirUrls = false;
var metaurl, tokenUrl, authorizeUrl;
function autoFindFhirUrls() {
	foundFhirUrls = false;
	$('#fhir_endpoint_base_url').val().trim();
	var url = $('#fhir_endpoint_base_url').val().replace(/\/$/, "");
	if (url == '') {
		simpleDialog('<?=js_escape($lang['control_center_4884'])?>', '<?=js_escape($lang['control_center_4885'])?>');
		return;
	}
	var k = 0;
	// Start "working..." progress bar
	showProgress(1,0);
	// Loop through URL and sub-URLs till we find the right metadata path
	while (k < 25 && foundFhirUrls === false) {		
		if (url == '' || url == 'https:/' || url == 'http:/' || url == 'https:' || url == 'http:') {
			break;
		}
		// Do ajax request to test the URL
		var thisAjax = $.ajax({
			url: '<?= PAGE_FULL ?>',
			type: 'POST',
			data: { url: url+"/metadata", redcap_csrf_token: redcap_csrf_token },
			async: false,
			success:
				function(data){
					if (data != '0') foundFhirUrls = data;
					metaurl = url+"/metadata";
				}
		});
		// Prep for the next loop
		url = dirname(url);
		k++;
	}
	showProgress(0,0);
	if (foundFhirUrls !== false) {
		var urls = foundFhirUrls.split("\n");
		authorizeUrl = urls[0];
		tokenUrl = urls[1];
		simpleDialog("The FHIR URLs below for your Authorize endpoint and Token endpoint were found from the FHIR Conformance Statement (<i>"+metaurl+"</i>). "
			+ "You may copy these URLs into their corresponding text boxes on this page."
			+ "<div style='font-size:13px;padding:20px 0 5px;color:green;'>Token endpoint: &nbsp;<b>"+tokenUrl+"</b></div>"
			+ "<div style='font-size:13px;padding:5px 0;color:green;'>Authorize endpoint: &nbsp;<b>"+authorizeUrl+"</b></div>",
			"<img src='"+app_path_images+"tick.png' style='vertical-align:middle;'> <span style='color:green;vertical-align:middle;'>Success!</span>",null,600,null,'Close',function(){
				$('#fhir_endpoint_authorize_url').val(authorizeUrl).effect('highlight',{},3000);
				$('#fhir_endpoint_token_url').val(tokenUrl).effect('highlight',{},3000);
			},'Copy');
	} else {
		simpleDialog("The FHIR Conformance Statement that contains the values of the URLs for your FHIR Authorize endpoint and FHIR Token endpoint could not found under your FHIR base URL nor under any higher-level directories. "
			+ "You should consult your EHR's technical team to determine these two FHIR endpoints. The DDP on FHIR function cannot work successfully without these URLs being set.", "<img src='"+app_path_images+"cross.png' style='vertical-align:middle;'> <span style='color:#C00000;vertical-align:middle;'>Failed to find FHIR Conformance Statement</span>");
	}
}
// Copy the public survey URL to the user's clipboard
function copyUrlToClipboard(ob) {
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
// Copy-to-clipboard action
var clipboard = new Clipboard('.btn-clipboard');
$(function(){
	// Copy-to-clipboard action
	$('.btn-clipboard').click(function(){
		copyUrlToClipboard(this);
	});
});
</script>


<style>
@import url('<?= APP_PATH_JS ?>vue/components/dist/style.css');
</style>

<script type="module">
import {CdisSettings} from '<?= getJSpath('vue/components/dist/lib.es.js') ?>'

CdisSettings('#cdis-settings')

</script>


<?php include 'footer.php'; ?>