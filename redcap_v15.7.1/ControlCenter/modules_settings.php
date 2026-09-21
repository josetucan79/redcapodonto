<?php

use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Facades\SystemSettings;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\AccessTokenEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProviderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardsProvider;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Config\TangoApiConfig;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities\TangoSystemSettingsVO;

include 'header.php';
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);
if (!ACCESS_SYSTEM_CONFIG) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); });</script>";

function saveTangoSettings(TangoSystemSettingsVO $settings) {
    $previousSettings = clone $settings;
    
    $tangoClientSecret = $_POST['tango-client-secret'] ?? null;
    $tangoClientId = $_POST['tango-client-id'] ?? null;
    $environment = $_POST['tango-environment'] ?? null;
    $baseURL = TangoApiConfig::getBaseUrl($environment);
    $tokenURL = TangoApiConfig::getTokenUrl($environment);
    
    $settings->setBaseUrl($baseURL);
    $settings->setTokenUrl($tokenURL);
    $settings->setClientId($tangoClientId);
    $settings->setClientSecret($tangoClientSecret);
    SystemSettings::save($settings);

    // delete access tokens if settings are updated
    if(!$previousSettings->equals($settings)) {
        $entityManager = EntityManager::get();
        $provider_id = $previousSettings->getProviderId();
        $providerReference = $entityManager->getReference(ProviderEntity::class, $provider_id);

        $atRepo = $entityManager->getRepository(AccessTokenEntity::class);
        $accessTokens = $atRepo->findBy(['provider' => $providerReference]);
        foreach ($accessTokens as $accessToken) {
            $entityManager->remove($accessToken);
        }
        $entityManager->flush();
    }
    return $settings;
}

function updateRewardsCronStatusFromPost() {
    // Determine whether rewards are enabled globally
    $enabled = isset($_POST['rewards_enabled_global']) && $_POST['rewards_enabled_global'] ? 1 : 0;

    // Prepare the query and parameters
    $query = "UPDATE redcap_crons SET cron_enabled = ? WHERE cron_name = ?";
    $params = [$enabled, 'ProcessScheduledRewardOrders'];

    // Run the update
    db_query($query, $params);
}

// Twilio setting is dependent upon another Twilio setting
?>
<script type="text/javascript">
function setTwilioDisplayInfo() {
	if ($('select[name="twilio_enabled_by_super_users_only"]').val() == '0' || $('select[name="twilio_enabled_by_super_users_only"]').val() == '2') {
		$('select[name="twilio_display_info_project_setup"]').val('0').prop('disabled', true);
		$('#twilio_display_info_project_setup-tr').fadeTo(0, 0.6);
	} else {
		$('select[name="twilio_display_info_project_setup"]').prop('disabled', false);
		$('#twilio_display_info_project_setup-tr').fadeTo(0, 1);
	}
}
function setMosioDisplayInfo() {
	if ($('select[name="mosio_enabled_by_super_users_only"]').val() == '0' || $('select[name="mosio_enabled_by_super_users_only"]').val() == '2') {
		$('select[name="mosio_display_info_project_setup"]').val('0').prop('disabled', true);
		$('#mosio_display_info_project_setup-tr').fadeTo(0, 0.6);
	} else {
		$('select[name="mosio_display_info_project_setup"]').prop('disabled', false);
		$('#mosio_display_info_project_setup-tr').fadeTo(0, 1);
	}
}
function setSendgridDisplayInfo() {
	if ($('select[name="sendgrid_enabled_by_super_users_only"]').val() == '0') {
		$('select[name="sendgrid_display_info_project_setup"]').val('0').prop('disabled', true);
		$('#sendgrid_display_info_project_setup-tr').fadeTo(0, 0.6);
	} else {
		$('select[name="sendgrid_display_info_project_setup"]').prop('disabled', false);
		$('#sendgrid_display_info_project_setup-tr').fadeTo(0, 1);
	}
}
function setRewardsDisplayInfo() {
	if ($('select[name="rewards_enabled_by_super_users_only"]').val() == '0') {
		$('select[name="rewards_display_info_project_setup"]').val('0').prop('disabled', true);
		$('#rewards_display_info_project_setup-tr').fadeTo(0, 0.6);
	} else {
		$('select[name="rewards_display_info_project_setup"]').prop('disabled', false);
		$('#rewards_display_info_project_setup-tr').fadeTo(0, 1);
	}
}
$(function(){
	setTwilioDisplayInfo();
	setMosioDisplayInfo();
    setSendgridDisplayInfo();
    setRewardsDisplayInfo();
});
</script>
<?php

$changesSaved = false;

/** @var TangoSystemSettingsVO $rewardsSettings **/
$rewardsSettings = SystemSettings::get(RewardsProvider::TANGO);

// If project default values were changed, update redcap_config table with new values
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ACCESS_SYSTEM_CONFIG)
{
	// Twilio setting is dependent upon another Twilio setting
	if ($_POST['twilio_enabled_by_super_users_only'] == '0') $_POST['twilio_display_info_project_setup'] = 0;
	if ($_POST['mosio_enabled_by_super_users_only'] == '0') $_POST['mosio_display_info_project_setup'] = 0;
    // SendGrid setting is dependent upon another SendGrid setting
	if ($_POST['sendgrid_enabled_by_super_users_only'] == '0') $_POST['sendgrid_display_info_project_setup'] = 0;
    
    // toggle the Rewards cronjob based on the rewards_enabled_global setting
    updateRewardsCronStatusFromPost();

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

    // Rewards
    $tangoSettings = saveTangoSettings($rewardsSettings);

	// Log any changes in log_event table
	if (count($changes_log) > 0) {
		Logging::logEvent(implode(";\n",$sql_all),"redcap_config","MANAGE","",implode(",\n",$changes_log),"Modify system configuration");
	}

	$changesSaved = true;
}

// Retrieve data to pre-fill in form
$element_data = System::getConfigVals();

if ($changesSaved)
{
	// Show user message that values were changed
	print  "<div class='yellow' style='margin-bottom: 20px; text-align:center'>
			<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
			{$lang['control_center_19']}
			</div>";
}
?>

<h4 style="margin-top: 0;"><i class="fas fa-cubes"></i> <?php echo $lang['control_center_4604'] ?></h4>

<form action='modules_settings.php' enctype='multipart/form-data' target='_self' method='post' name='form' id='form'>
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".System::getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0; width: 100%;">


<!-- External Modules settings -->
<tr>
    <td colspan="2">
        <h4 style="font-size:14px;padding:10px;color:#800000;"><?php echo $lang['system_config_642'] ?></h4>
    </td>
</tr>

<!-- Enable/disable the user activation request button for EMs -->
<tr>
    <td class="cc_label"><i class="fas fa-user-check"></i> <?php echo $lang['system_config_643'] ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="max-width:350px;" name="external_modules_allow_activation_user_request">
            <option value='0' <?php echo ($element_data['external_modules_allow_activation_user_request'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_645'] ?></option>
            <option value='1' <?php echo ($element_data['external_modules_allow_activation_user_request'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_644'] ?></option>
        </select>
        <div class="cc_info">
			<?php echo $lang['system_config_646'] ?>
        </div>
    </td>
</tr>


<!-- Various modules/services -->
<tr>
	<td colspan="2">
        <hr size=1>
	<h4 style="font-size:14px;padding:10px;color:#800000;"><?php echo $lang['system_config_570'] ?></h4>
	</td>
</tr>

<!-- Enable/disable the use of surveys in projects -->
<tr>
	<td class="cc_label"><i class='fas fa-chalkboard-teacher'></i> <?php echo $lang['system_config_237'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_projecttype_singlesurveyforms">
			<option value='0' <?php echo ($element_data['enable_projecttype_singlesurveyforms'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_projecttype_singlesurveyforms'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
	</td>
</tr>

<!-- reCAPTCHA -->
<tr>
    <td class="cc_label pt-4">
        <i class="fas fa-redo"></i> <?php echo $lang['survey_1245'] ?>
        <div class="cc_info">
            <?php echo $lang['survey_1248'] ?>
        </div>
    </td>
    <td class="cc_data pt-4">
        <div>
            <?php echo $lang['survey_1246'] ?>
            <input type="text" class="x-form-text x-form-field" style="margin-left:15px;width:95%;max-width:280px;" name="google_recaptcha_site_key"  value="<?php echo htmlspecialchars($element_data['google_recaptcha_site_key'], ENT_QUOTES) ?>">
        </div>
        <div class="mt-2 mb-4">
            <?php echo $lang['survey_1247'] ?>
            <input type="password" class="x-form-text x-form-field" style="width:95%;max-width:200px;" name="google_recaptcha_secret_key" id="google_recaptcha_secret_key"  value="<?php echo htmlspecialchars($element_data['google_recaptcha_secret_key'], ENT_QUOTES) ?>">
            <a href="javascript:;" class="password-mask-reveal" style="margin-left:5px;text-decoration:underline;font-size:11px;font-weight:normal;" onclick="$(this).remove();showSecret('#google_recaptcha_secret_key');"><?php echo $lang['system_config_258'] ?></a>
        </div>
        <div class="mt-2 mb-4">
            <?php echo $lang['global_300'] ?>
            <select class="x-form-text x-form-field" style="" name="google_recaptcha_default">
                <option value='0' <?php echo ($element_data['google_recaptcha_default'] == 0) ? "selected" : "" ?>><?php echo $lang['global_299'] ?></option>
                <option value='1' <?php echo ($element_data['google_recaptcha_default'] == 1) ? "selected" : "" ?>><?php echo $lang['global_298'] ?></option>
            </select>
        </div>
        <a href="https://www.google.com/recaptcha/admin#list" target="_blank" style="text-decoration:underline;color:#A00000;"><?php echo $lang['survey_1249'] ?></a>
    </td>
</tr>


<tr  id="enable_url_shortener-tr" sq_id="enable_url_shortener">
	<td class="cc_label">
        <i class="fas fa-link"></i> <?php echo $lang['system_config_701'] ?>
        <div class="cc_info">
			<?php echo $lang['system_config_957'] ?>
        </div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_url_shortener">
			<option value='0' <?php echo ($element_data['enable_url_shortener'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_url_shortener'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['system_config_703'] ?>
		</div>
	</td>
</tr>

<!-- Randomization -->
<tr>
	<td class="cc_label"><i class="fas fa-random"></i> <?php echo $lang['app_21'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="randomization_global">
			<option value='0' <?php echo ($element_data['randomization_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['randomization_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_225'] ?>
		</div>
	</td>
</tr>

<!-- Shared Library -->
<tr  id="shared_library_enabled-tr" sq_id="shared_library_enabled">
	<td class="cc_label"><i class="fas fa-book-reader"></i> REDCap Shared Library</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="shared_library_enabled">
			<option value='0' <?php echo ($element_data['shared_library_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['shared_library_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_110'] ?>
			<a href="<?php echo SHARED_LIB_PATH ?>" style='text-decoration:underline;' target='_blank'>REDCap Shared Library</a>
			<?php echo $lang['system_config_111'] ?>
		</div>
	</td>
</tr>


<!-- REDCap Messenger -->
<tr >
	<td class="cc_label">
        <i class="fas fa-comment-alt"></i> <?php echo $lang['messaging_09'] ?>
		<div style="width:105px;margin-top:5px;background-color:#40699E;padding:3px 5px;"><img style="width:90px;" src="<?php echo APP_PATH_IMAGES ?>messenger_logo.png"></div> 
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="user_messaging_enabled">
			<option value='0' <?php echo ($element_data['user_messaging_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['user_messaging_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>		
		<select class="x-form-text x-form-field" style="margin-left:5px;" name="user_messaging_prevent_admin_messaging">
			<option value='0' <?php echo ($element_data['user_messaging_prevent_admin_messaging'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_607'] ?></option>
			<option value='1' <?php echo ($element_data['user_messaging_prevent_admin_messaging'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_608'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['messaging_21'] ?>
		</div>
	</td>
</tr>

<!-- API -->
<tr >
	<td class="cc_label"><i class="fas fa-laptop-code"></i> REDCap API</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="api_enabled">
			<option value='0' <?php echo ($element_data['api_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['api_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_114'] ?>
			<a href='<?php echo APP_PATH_WEBROOT_FULL ?>api/help/' style='text-decoration:underline;' target='_blank'>REDCap API help page</a><?php echo $lang['period'] ?>
		</div>
	</td>
</tr>


<!-- REDCap Mobile App -->
<tr>
	<td class="cc_label"><img src="<?php echo APP_PATH_IMAGES ?>phone_tablet.png"> <?php echo $lang['global_118'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="mobile_app_enabled">
			<option value='0' <?php echo ($element_data['mobile_app_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['mobile_app_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_330'] ?>
		</div>
	</td>
</tr>

<!-- Enable/disable the use of mycap in projects -->
<tr>
    <td class="cc_label pb-3">
        <img src="<?php echo APP_PATH_IMAGES ?>mycap_logo_black.png" width="30"> <?php echo $lang['global_260'] ?>
        <div class="cc_info">
            <?php echo $lang['system_config_778'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="mycap_enabled_global">
            <option value='0' <?php echo ($element_data['mycap_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['mycap_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <?php
        print RCView::a(array('href'=>'javascript:;', 'class'=>'ms-5', 'style'=>'text-decoration:underline;', 'onclick'=>"simpleDialog(null,null,'myCapDialog',1000);"), '<i class="fa-solid fa-mobile-screen-button fs13 me-1" style="text-indent:0;position:relative;top:1px;"></i>'.$lang['mycap_mobile_app_670']);
        ?>
        <div class="font-weight-bold mt-3 mb-1">
            <?php echo $lang['system_config_779'] ?>
        </div>
        <select class="x-form-text x-form-field" style="max-width:98%;" name="mycap_enable_type">
            <option value='auto' <?php echo ($element_data['mycap_enable_type'] == 'auto') ? "selected" : "" ?>><?php echo $lang['system_config_780'] ?></option>
            <option value='admin' <?php echo ($element_data['mycap_enable_type'] == 'admin') ? "selected" : "" ?>><?php echo $lang['system_config_781'] ?></option>
        </select>
        <?=RCView::simpleDialog(Vanderbilt\REDCap\Classes\MyCap\MyCap::getMyCapAboutInstructions(), $lang['setup_193'], 'myCapDialog')?>
    </td>
</tr>

<!-- External Modules alternate module directory paths (optional) -->
<tr>
	<td class="cc_label">
		<i class="fas fa-cube"></i> <?php echo $lang['global_142'] . $lang['colon'] . "<br>" . $lang['system_config_571'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_577'] ?>
		</div>	
	</td>
	<td class="cc_data">
		<input type="text" class="x-form-text x-form-field" style="width:95%;max-width:350px;" name="external_module_alt_paths"  value="<?php echo htmlspecialchars($element_data['external_module_alt_paths'], ENT_QUOTES) ?>">
		<div class="cc_info">
			e.g., /var/www/redcap/modules_staging/|/var/www/redcap/modules_internal/
		</div>
		<div class="cc_info">
			e.g., C:\xampp\htdocs\redcap\modules2\
		</div>
		
	</td>
</tr>

<!-- File Version History for File Upload fields -->
<tr>
    <td class="cc_label">
        <i class="fas fa-history" style="text-indent: 0;"></i> <i class="fas fa-file-upload" style="text-indent: 0;margin:0 1px 0 2px;"></i> <?php echo $lang['data_entry_456'] ?>
        <div class="cc_info">
            <?php echo $lang['data_entry_464'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="file_upload_versioning_global_enabled">
            <option value='0' <?php echo ($element_data['file_upload_versioning_global_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['file_upload_versioning_global_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select><br/>
        <div class="cc_info">
            <?php echo $lang['data_entry_463'] ?>
        </div>
    </td>
</tr>

<!-- Field Bank -->
<tr>
    <td class="cc_label">
        <?php echo $lang['design_937'] ?>
        <div class="cc_info">
            <?php echo $lang['design_939'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="field_bank_enabled">
            <option value='0' <?php echo ($element_data['field_bank_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['field_bank_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select><br/>
        <div class="cc_info">
            <?php echo $lang['design_938'] ?>
        </div>
    </td>
</tr>

<!-- Embedded videos for Descriptive fields -->
<tr>
	<td class="cc_label">
		<div class="hang">
            <i class="fab fa-youtube" style="text-indent: 0;"></i> <?php echo $lang['system_config_392'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_field_attachment_video_url">
			<option value='0' <?php echo ($element_data['enable_field_attachment_video_url'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_field_attachment_video_url'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_393'] ?>
		</div>
	</td>
</tr>


<!-- Allow text-to-speech service in surveys -->
<tr>
	<td class="cc_label">
		<div class="hang">
            <i class="fas fa-volume-up" style="text-indent:0;"></i> <?php echo $lang['system_config_394'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_survey_text_to_speech">
			<option value='0' <?php echo ($element_data['enable_survey_text_to_speech'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_survey_text_to_speech'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_534'] ?>
		</div>
	</td>
</tr>


<!-- Allow auto-suggest functionality for ontology search on forms/surveys -->
<tr>
	<td class="cc_label">
		<div class="hang">
			<img src="<?php echo APP_PATH_IMAGES ?>search_field.png" style="margin-right:2px;"> <?php echo $lang['system_config_397'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_ontology_auto_suggest">
			<option value='0' <?php echo ($element_data['enable_ontology_auto_suggest'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_ontology_auto_suggest'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div style="font-weight:bold;margin:8px 0 5px;">
			<?php echo $lang['design_592'] ?>
			<input type="text" class="x-form-text x-form-field" style="width:250px;margin-left:6px;" name="bioportal_api_token" value="<?php echo $bioportal_api_token ?>">
		</div>
		<div class="cc_info">
			<?php
			echo $lang['system_config_398'] . " <b>" . BioPortal::getApiUrl() . "</b>" . $lang['period'] . " " .
				 $lang['system_config_399']
			?>
			<a href="<?php echo BioPortal::$SIGNUP_URL ?>" target="_blank" style="text-decoration:underline;"><?php echo $lang['system_config_400'] ?></a><?php echo $lang['period'] . " " . $lang['system_config_401'] ?>
		</div>
	</td>
</tr>


<!-- Data Entry Trigger enable -->
<tr>
	<td class="cc_label">
		<div class="hang">
            <i class="fas fa-hand-point-right" style="text-indent: 0;"></i>
			<?php echo $lang['edit_project_136'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['edit_project_137'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="data_entry_trigger_enabled">
			<option value='0' <?php echo ($element_data['data_entry_trigger_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['data_entry_trigger_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['edit_project_160'] ?>
			<a href="javascript:;" onclick="simpleDialog(null,null,'dataEntryTriggerDialog',650);" class="nowrap" style="text-decoration:underline;"><?php echo $lang['edit_project_127'] ?></a>
		</div>
	</td>
</tr>


<!-- Project XML Export enable -->
<tr>
	<td class="cc_label">
		<div class="hang">
            <i class="fas fa-file-code fs14 me-1" style="text-indent: 0;"></i>
			<?php echo $lang['system_config_547'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="display_project_xml_backup_option">
			<option value='0' <?php echo ($element_data['display_project_xml_backup_option'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['display_project_xml_backup_option'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_548'] ?>
		</div>
	</td>
</tr>


<!-- Protected Email mode -->
<tr>
    <td class="cc_label">
        <div class="hang">
            <i class="fas fa-lock fs14 me-1" style="text-indent: 0;"></i><i class="fas fa-envelope fs14" style="text-indent: 0;"></i>
            <?php echo $lang['global_235'] ?>
        </div>
        <div class="cc_info">
			<?php echo $lang['system_config_722'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="protected_email_mode_global">
            <option value='0' <?php echo ($element_data['protected_email_mode_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['protected_email_mode_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select><br/>
        <div class="cc_info">
            <?php echo $lang['system_config_721'] ?>
        </div>
    </td>
</tr>


<!-- Email Logging -->
<tr>
    <td class="cc_label">
        <div class="hang">
            <i class="fas fa-mail-bulk fs14" style="text-indent: 0;"></i>
            <?php echo $lang['email_users_53'] ?>
        </div>
        <div class="cc_info">
            <?php echo $lang['email_users_83'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="email_logging_enable_global">
            <option value='0' <?php echo ($element_data['email_logging_enable_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['email_logging_enable_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select><br/>
        <div class="cc_info">
            <?php echo $lang['email_users_84'] ?>
        </div>
    </td>
</tr>


<!-- Bulk Record Delete -->
<tr>
    <td class="cc_label">
        <div class="hang">
            <i class="fas fa-times-circle fs14" style="text-indent: 0;"></i>
            <?php echo $lang['data_entry_619'] ?>
        </div>
        <div class="cc_info">
            <?php echo $lang['data_entry_660'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="bulk_record_delete_enable_global">
            <option value='0' <?php echo ($element_data['bulk_record_delete_enable_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['bulk_record_delete_enable_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['data_entry_661'] ?>
        </div>
    </td>
</tr>

<!-- E-signature -->
<tr>
    <td class="cc_label">
        <div>
            <?=RCIcon::ESigned("text-success me-1")?><?=RCView::tt("global_34")?>
        </div>
        <div class="cc_info">
            <?php echo $lang['system_config_758'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="esignature_enabled_global">
            <option value='0' <?php echo ($element_data['esignature_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['esignature_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['system_config_759'] ?>
        </div>
    </td>
</tr>

<!-- Calendar Feed -->
<tr>
    <td class="cc_label">
        <i class="far fa-calendar-alt"></i> <?php echo $lang['calendar_19'] ?>
        <div class="cc_info">
            <?php echo $lang['calendar_20'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="calendar_feed_enabled_global">
            <option value='0' <?php echo ($element_data['calendar_feed_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['calendar_feed_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
    </td>
</tr>

<!-- Image embedding for rich text editor -->
<tr>
    <td class="cc_label">
        <i class="far fa-image"></i> <?php echo $lang['system_config_773'] ?>
        <div class="cc_info">
            <?php echo $lang['system_config_774'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="rich_text_image_embed_enabled">
            <option value='0' <?php echo ($element_data['rich_text_image_embed_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['rich_text_image_embed_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['system_config_775'] ?>
        </div>
    </td>
</tr>

<!-- File attachment embedding for rich text editor -->
<tr>
    <td class="cc_label">
        <i class="fa-solid fa-paperclip"></i> <?php echo $lang['system_config_804'] ?>
        <div class="cc_info">
            <?php echo $lang['system_config_805'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="rich_text_attachment_embed_enabled">
            <option value='0' <?php echo ($element_data['rich_text_attachment_embed_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['rich_text_attachment_embed_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['system_config_806'] ?>
        </div>
    </td>
</tr>

<!-- Inline PDF in PDF via iMagick -->
<tr>
    <td class="cc_label">
        <i class="fa-solid fa-file-pdf fs15"></i> <?php echo $lang['system_config_807'] ?>
        <div class="cc_info">
            <?php echo $lang['system_config_808'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="display_inline_pdf_in_pdf">
            <option value='0' <?php echo ($element_data['display_inline_pdf_in_pdf'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['display_inline_pdf_in_pdf'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['system_config_809'] ?>
            <div class="mt-2"><?=(PDF::iMagickInstalled() ? $lang['system_config_810'] : '<div class="text-dangerrc"><i class="fa-solid fa-circle-exclamation font-weight-bold"></i> '.$lang['system_config_811'].'</div>')?></div>
        </div>
    </td>
</tr>

<!-- DTS -->
<tr  id="dts_enabled_global-tr" sq_id="dts_enabled_global">
	<td class="cc_label"><i class="fas fa-database"></i> <?php echo $lang['rights_132'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="dts_enabled_global">
			<option value='0' <?php echo ($element_data['dts_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['dts_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_124'] ?>
		</div>
	</td>
</tr>


<!-- CATs -->
<tr>
	<td colspan="2">
		<hr size=1>
		<div style="margin:8px;">
			<div class="float-start"><a href='https://www.assessmentcenter.net/' style='text-decoration:underline;' target='_blank'><img src="<?php echo APP_PATH_IMAGES ?>assessmentcenter.gif"></a></div>
            <div class="float-end"><a href='http://www.neuroqol.org/' style='text-decoration:underline;' target='_blank'><img src="<?php echo APP_PATH_IMAGES ?>neuroqol.gif"></a></div>
            <div class="float-end"><a href='http://www.nihpromis.org/' style='margin:0 20px 0 80px;text-decoration:underline;' target='_blank'><img src="<?php echo APP_PATH_IMAGES ?>promis.png"></a></div>
        </div>
	</td>
</tr>
<tr >
	<td class="cc_label">
        <i class="fas fa-flag"></i>
		<?php echo $lang['system_config_388'] ?>
		<div class="cc_info">
			<?php
			echo "{$lang['system_config_389']} <a href='http://www.nihpromis.org/' style='text-decoration:underline;' target='_blank'>{$lang['system_config_314']}</a>
				  {$lang['global_43']} <a href='http://www.neuroqol.org/' style='text-decoration:underline;' target='_blank'>{$lang['system_config_390']}</a>{$lang['system_config_391']}
				  {$lang['system_config_316']} <a href='https://www.assessmentcenter.net/' style='text-decoration:underline;' target='_blank'>Assessment Center API</a>{$lang['period']}";
			?>
		</div>
	</td>
	<td class="cc_data">
		<table cellspacing=0 width=100%>
			<tr>
				<td valign="top">
					<select class="x-form-text x-form-field" style="" name="promis_enabled">
						<option value='0' <?php echo ($element_data['promis_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
						<option value='1' <?php echo ($element_data['promis_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
					</select>
				</td>
				<td style="padding-left:15px;">
					<div style="margin:0 0 3px;">
						<?php echo $lang['system_config_317'] ?>
						&nbsp;&nbsp;<button class="jqbuttonmed" onclick="testUrl('<?php echo $promis_api_base_url ?>','post','',true);return false;"><?php echo $lang['edit_project_138'] ?></button>
					</div>
					<div style="margin:3px 0;font-size:11px;color:#6E6E68;line-height:11px;">
						<?php echo $lang['system_config_318'] . " " . RCView::span(array('style'=>'color:#C00000;'), $promis_api_base_url) ?>
					</div>
				</td>
			</tr>
		</table>
		<div class="cc_info" style="color:#800000;margin-top:20px;">
			<?php echo "{$lang['system_config_315']} <a href='https://www.assessmentcenter.net/' style='text-decoration:underline;' target='_blank'>Assessment Center</a>{$lang['period']}
						{$lang['system_config_322']}" ?>
		</div>
	</td>
</tr>




<!-- Twilio -->
<tr>
	<td colspan="2">
	<hr size=1>
	<h3 style="font-size:14px;padding:0 10px;color:#800000;">
		<img src="<?php echo APP_PATH_IMAGES ?>twilio.png">
		<?php echo $lang['survey_1284'] ?>
	</h3>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['survey_847'] ?>
		<div class="cc_info">
			<?php echo $lang['survey_848'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="twilio_enabled_global">
			<option value='0' <?php echo ($element_data['twilio_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['twilio_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<span style="margin-left:12px;">
			<?php echo $lang['system_config_317'] ?>
			&nbsp;&nbsp;<button class="jqbuttonmed" onclick="testUrl('https://api.twilio.com','post','',true);return false;"><?php echo $lang['edit_project_138'] ?></button>
		</span>
		<div class="cc_info">
			<?php echo $lang['survey_712'] ?>
			<b>https://api.twilio.com</b><?php echo $lang['period'] ?>
			<?php echo $lang['survey_853']." ".$lang['survey_713'] ?>
			<a href='https://www.twilio.com' style='text-decoration:underline;' target='_blank'>https://www.twilio.com</a><?php echo $lang['period'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['survey_908'] ?>
	</td>
	<td class="cc_data">
		<select onchange="setTwilioDisplayInfo()" class="x-form-text x-form-field" style="" name="twilio_enabled_by_super_users_only">
			<option value='0' <?php echo ($element_data['twilio_enabled_by_super_users_only'] == 0) ? "selected" : "" ?>><?php echo $lang['survey_909'] ?></option>
            <option value='2' <?php echo ($element_data['twilio_enabled_by_super_users_only'] == 2) ? "selected" : "" ?>><?php echo RCView::tt('system_config_955') ?></option>
			<option value='1' <?php echo ($element_data['twilio_enabled_by_super_users_only'] == 1) ? "selected" : "" ?>><?php echo $lang['survey_910'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['survey_911']."<br>". $lang['system_config_956'] ?>
		</div>
	</td>
</tr>
<tr id="twilio_display_info_project_setup-tr">
	<td class="cc_label">
		<?php echo $lang['survey_849'] ?>
		<div class="cc_info" style="color:#800000;">
			<?php echo $lang['survey_912'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="twilio_display_info_project_setup">
			<option value='0' <?php echo ($element_data['twilio_display_info_project_setup'] == 0) ? "selected" : "" ?>><?php echo $lang['survey_850'] ?></option>
			<option value='1' <?php echo ($element_data['twilio_display_info_project_setup'] == 1) ? "selected" : "" ?>><?php echo $lang['survey_851'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['survey_852'] ?>
		</div>
	</td>
</tr>




<!-- Mosio -->
<tr>
	<td colspan="2">
	<hr size=1>
	<h3 style="font-size:14px;padding:0 10px;color:#800000;">
        <img src="<?php echo APP_PATH_IMAGES ?>mosio.png" style="width:16px;height:16px;">
		<?php echo $lang['survey_1526'] ?>
	</h3>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['survey_1554'] ?>
		<div class="cc_info">
			<?php echo $lang['survey_1516'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="mosio_enabled_global">
			<option value='0' <?php echo ($element_data['mosio_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['mosio_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<span style="margin-left:12px;">
			<?php echo $lang['system_config_317'] ?>
			&nbsp;&nbsp;<button class="jqbuttonmed" onclick="testUrl('<?=Mosio::API_BASE.Mosio::API_PING_ENDPOINT?>','post','',true);return false;"><?php echo $lang['edit_project_138'] ?></button>
		</span>
		<div class="cc_info">
			<?php echo $lang['survey_1517'] ?>
			<b>https://api.mosio.com</b><?php echo $lang['period'] ?>
			<?php echo $lang['survey_1524']." ".$lang['survey_1525'] ?>
			<a href='https://www.mosio.com' style='text-decoration:underline;' target='_blank'>https://www.mosio.com</a><?php echo $lang['period'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['survey_1520'] ?>
	</td>
	<td class="cc_data">
		<select onchange="setMosioDisplayInfo()" class="x-form-text x-form-field" style="" name="mosio_enabled_by_super_users_only">
			<option value='0' <?php echo ($element_data['mosio_enabled_by_super_users_only'] == 0) ? "selected" : "" ?>><?php echo $lang['survey_909'] ?></option>
            <option value='2' <?php echo ($element_data['mosio_enabled_by_super_users_only'] == 2) ? "selected" : "" ?>><?php echo RCView::tt('system_config_955') ?></option>
			<option value='1' <?php echo ($element_data['mosio_enabled_by_super_users_only'] == 1) ? "selected" : "" ?>><?php echo $lang['survey_910'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['survey_1521']."<br>". $lang['system_config_956'] ?>
		</div>
	</td>
</tr>
<tr id="mosio_display_info_project_setup-tr">
	<td class="cc_label">
		<?php echo $lang['survey_1522'] ?>
		<div class="cc_info" style="color:#800000;">
			<?php echo $lang['survey_912'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="mosio_display_info_project_setup">
			<option value='0' <?php echo ($element_data['mosio_display_info_project_setup'] == 0) ? "selected" : "" ?>><?php echo $lang['survey_1518'] ?></option>
			<option value='1' <?php echo ($element_data['mosio_display_info_project_setup'] == 1) ? "selected" : "" ?>><?php echo $lang['survey_1519'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['survey_1523'] ?>
		</div>
	</td>
</tr>

<!-- SendGrid -->
<tr>
	<td colspan="2">
	<hr size=1>
	<h3 style="font-size:14px;padding:0 10px;color:#800000;">
		<img src="<?php echo APP_PATH_IMAGES ?>sendgrid.png" style="position:relative;top:-2px;">
		<?php echo $lang['survey_1387'] ?>
	</h3>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['survey_1375'] ?>
		<div class="cc_info">
			<?php echo $lang['survey_1376'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="sendgrid_enabled_global">
			<option value='0' <?php echo ($element_data['sendgrid_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['sendgrid_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<span style="margin-left:12px;">
			<?php echo $lang['system_config_317'] ?>
			&nbsp;&nbsp;<button class="jqbuttonmed" onclick="testUrl('https://api.sendgrid.com/v3','post','',true);return false;"><?php echo $lang['edit_project_138'] ?></button>
		</span>
		<div class="cc_info">
			<?php echo $lang['survey_1377'] ?>
			<b>https://api.sendgrid.com/v3</b><?php echo $lang['period'] ?>
			<?php echo $lang['survey_1378'] ?>
			<a href='https://sendgrid.com' style='text-decoration:underline;' target='_blank'>https://sendgrid.com</a><?php echo $lang['period'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['survey_1379'] ?>
	</td>
	<td class="cc_data">
		<select onchange="setSendgridDisplayInfo()" class="x-form-text x-form-field" style="" name="sendgrid_enabled_by_super_users_only">
			<option value='0' <?php echo ($element_data['sendgrid_enabled_by_super_users_only'] == 0) ? "selected" : "" ?>><?php echo $lang['survey_909'] ?></option>
			<option value='1' <?php echo ($element_data['sendgrid_enabled_by_super_users_only'] == 1) ? "selected" : "" ?>><?php echo $lang['survey_910'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['survey_1380'] ?>
		</div>
	</td>
</tr>

<tr id="sendgrid_display_info_project_setup-tr">
	<td class="cc_label">
		<?php echo $lang['survey_1381'] ?>
		<div class="cc_info" style="color:#800000;">
			<?php echo $lang['survey_912'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="sendgrid_display_info_project_setup">
			<option value='0' <?php echo ($element_data['sendgrid_display_info_project_setup'] == 0) ? "selected" : "" ?>><?php echo $lang['survey_1382'] ?></option>
			<option value='1' <?php echo ($element_data['sendgrid_display_info_project_setup'] == 1) ? "selected" : "" ?>><?php echo $lang['survey_1383'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['survey_1384'] ?>
		</div>
	</td>
</tr>


<!-- Rewards -->
<?php if(isVanderbilt() || $_SERVER['HTTP_HOST'] === 'redcap.test') : ?>
<tr>
	<td colspan="2">
	<hr size=1>
	<h3 style="font-size:14px;padding:0 10px;color:#800000;">
		<span><i class="fas fa-gift fa-fw"></i></span>
		<?php echo $lang['rewards_settings_title'] ?>
	</h3>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['rewards_settings_option_1_title'] ?>
		<div class="cc_info">
			<?php echo $lang['rewards_settings_option_1_description'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="rewards_enabled_global">
			<option value='0' <?php echo ($element_data['rewards_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['rewards_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['rewards_settings_option_1_note'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['rewards_settings_option_2_title'] ?>
	</td>
	<td class="cc_data">
		<select onchange="setRewardsDisplayInfo()" class="x-form-text x-form-field" style="" name="rewards_enabled_by_super_users_only">
			<option value='0' <?php echo ($element_data['rewards_enabled_by_super_users_only'] == 0) ? "selected" : "" ?>><?php echo $lang['survey_909'] ?></option>
			<option value='1' <?php echo ($element_data['rewards_enabled_by_super_users_only'] == 1) ? "selected" : "" ?>><?php echo $lang['survey_910'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['rewards_settings_option_2_note'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['rewards_settings_option_5_title'] ?>
	</td>
	<td class="cc_data">
		<select onchange="setRewardsDisplayInfo()" class="x-form-text x-form-field" name="rewards_enable_type">
			<option value='auto' <?php echo ($element_data['rewards_enable_type'] == 'auto') ? "selected" : "" ?>><?php echo $lang['rewards_settings_option_5_value_1'] ?></option>
			<option value='admin' <?php echo ($element_data['rewards_enable_type'] == 'admin') ? "selected" : "" ?>><?php echo $lang['rewards_settings_option_5_value_2'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['rewards_settings_option_5_note'] ?>
		</div>
	</td>
</tr>

<tr>
    <td class="cc_label">
        <?php echo $lang['rewards_settings_option_4_title'] ?>
        <div class="cc_info">
            <?php echo $lang['rewards_settings_option_4_description'] ?>
        </div>
    </td>
    <td class="cc_data">
        <textarea style='resize: vertical; height:65px;' class='x-form-field notesbox' id='rewards_enablement_message' name='rewards_enablement_message'><?php echo $element_data['rewards_enablement_message'] ?></textarea>
        <div class="cc_info">
            <?php echo $lang['system_config_195'] ?>
        </div>
    </td>
</tr>

<tr>
    <td colspan="2">
        <div style="padding:0 10px;">
            <?= $lang['rewards_settings_credentials_description'] ?>
        </div>
    </td>
</tr>

<tr>
    <td class="cc_label">
        <?= Language::tt('tango_environment_label') ?>
    </td>
    <td class="cc_data">
        <div class="input-group">
            <select
                class="form-select form-select-sm"
                name="tango-environment">
                <option data-base-url="<?= TangoApiConfig::getBaseUrl(TangoApiConfig::ENVIRONMENT_PRODUCTION) ?>"
                    value="<?= TangoApiConfig::ENVIRONMENT_PRODUCTION ?>" <?= ($rewardsSettings->environment() === TangoApiConfig::ENVIRONMENT_PRODUCTION) ? 'selected' : '' ?> >Production</option>
                <option data-base-url="<?= TangoApiConfig::getBaseUrl(TangoApiConfig::ENVIRONMENT_SANDBOX) ?>"
                    value="<?= TangoApiConfig::ENVIRONMENT_SANDBOX ?>" <?= ($rewardsSettings->environment() === TangoApiConfig::ENVIRONMENT_SANDBOX ) ? 'selected' : '' ?> >Sandbox</option>
            </select>
            <button id="tango-environment-check" class="btn btn-primary btn-sm" type="button" id="checkUrl">Check</button>
        </div>
        <div class="cc_info">
            <?= Language::tt('tango_environment_description_1') ?>
        </div>
    </td>
</tr>

<tr>
    <td class="cc_label">
        <?php echo $lang['rewards_settings_option_6_title'] ?>
    </td>
    <td class="cc_data">
        <input class="x-form-text x-form-field" style="width:300px;" autocomplete="new-password" type="text" name="tango-client-id" value="<?= htmlspecialchars($rewardsSettings->getClientId() ?? '', ENT_QUOTES) ?>"  /><br/>
    </td>
</tr>

<tr>
    <td class="cc_label">
        <?php echo $lang['rewards_settings_option_7_title'] ?>
    </td>
    <td class="cc_data">
        <input data-sensitive class="x-form-text x-form-field" style="width:300px;" autocomplete="new-password" type="text" name="tango-client-secret" value="<?= htmlspecialchars($rewardsSettings->getClientSecret() ?? '', ENT_QUOTES) ?>"  /><br/>
    </td>
</tr>

<!-- end rewards -->
 <?php endif; ?>

<tr>
	<td colspan="2">
	<hr size=1>
	<h3 style="font-size:14px;padding:0 10px;color:#800000;">
        <i class="fas fa-chart-bar fs15"></i>
		<?php echo $lang['system_config_172'] ?>
	</h3>
	</td>
</tr>
<tr  id="enable_plotting-tr" sq_id="enable_plotting">
	<td class="cc_label">
		<?php echo $lang['system_config_763'] ?>
		<div class="cc_info" style="font-weight:normal;"><?php echo $lang['system_config_323'] ?></div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_plotting">
			<option value='0' <?php echo ($element_data['enable_plotting'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='2' <?php echo ($element_data['enable_plotting'] == 2) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div class="cc_info" style="color:#800000;font-weight:normal;"><?php echo $lang['system_config_174'] ?></div>
	</td>
</tr>
<tr  id="enable_plotting_survey_results-tr" sq_id="enable_plotting_survey_results">
	<td class="cc_label">
		<?php echo $lang['system_config_176'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_plotting_survey_results">
			<option value='0' <?php echo ($element_data['enable_plotting_survey_results'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_plotting_survey_results'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_171'] ?>
		</div>
	</td>
</tr>


<!-- e-Consent Framework for PDF Auto-Archiver -->
<tr>
	<td colspan="2">
		<hr size=1>
		<h3 style="font-size:14px;padding:0 10px;color:#800000;"><i class="fas fa-file-pdf fs15"></i> <?php echo $lang['econsent_29'] ?></h3>
		<div style="padding:0 10px;"><?php echo $lang['econsent_124'] ?></div>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['survey_1202'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" name="pdf_econsent_system_enabled" style="max-width:360px;">
			<option value='0' <?php echo ($element_data['pdf_econsent_system_enabled'] == '0') ? "selected" : "" ?>><?php echo $lang['survey_1205'] ?></option>
			<option value='1' <?php echo ($element_data['pdf_econsent_system_enabled'] == '1') ? "selected" : "" ?>><?php echo $lang['survey_1204'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['econsent_123'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['survey_1218'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" name="pdf_econsent_system_ip" style="max-width:360px;">
			<option value='0' <?php echo ($element_data['pdf_econsent_system_ip'] == '0') ? "selected" : "" ?>><?php echo $lang['survey_1219'] ?></option>
			<option value='1' <?php echo ($element_data['pdf_econsent_system_ip'] == '1') ? "selected" : "" ?>><?php echo $lang['survey_1220'] ?></option>
		</select>
	</td>
</tr>

<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_625'] ?>
        <div class="cc_info">
            <?php echo $lang['econsent_185'] ?>
        </div>
    </td>
    <td class="cc_data">
        <textarea style='height:65px;' class='x-form-field notesbox' id='pdf_econsent_system_custom_text' name='pdf_econsent_system_custom_text'><?php echo $element_data['pdf_econsent_system_custom_text'] ?></textarea>
        <div id='pdf_econsent_system_custom_text-expand' style='text-align:right;'>
            <a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#6E6E68;font-family:tahoma;font-size:10px;'
               onclick="growTextarea('pdf_econsent_system_custom_text')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
        </div>
        <div class="cc_info">
            <?php echo $lang['system_config_195'] ?>
        </div>
    </td>
</tr>


<!-- Alerts & Notifications -->
<tr>
    <td colspan="2">
        <hr size=1>
        <h3 style="font-size:14px;padding:0 10px;color:#800000;"><i class="fas fa-bell fs15"></i> <?php echo $lang['global_154'] ?></h3>
        <div style="padding:0 10px;"><?php echo $lang['alerts_01'] ?></div>
    </td>
</tr>
<tr>
    <td colspan="2">
        <div style="padding:15px 0 0 10px;color:#C00000;font-weight:bold;"><?php echo $lang['alerts_207'] ?></div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['alerts_03'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="alerts_allow_email_variables" style="max-width:360px;">
            <option value='0' <?php echo ($element_data['alerts_allow_email_variables'] == '0') ? "selected" : "" ?>><?php echo $lang['alerts_06'] ?></option>
            <option value='1' <?php echo ($element_data['alerts_allow_email_variables'] == '1') ? "selected" : "" ?>><?php echo $lang['alerts_05'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['alerts_07'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['alerts_02'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="alerts_allow_email_freeform" style="max-width:360px;">
            <option value='0' <?php echo ($element_data['alerts_allow_email_freeform'] == '0') ? "selected" : "" ?>><?php echo $lang['alerts_06'] ?></option>
            <option value='1' <?php echo ($element_data['alerts_allow_email_freeform'] == '1') ? "selected" : "" ?>><?php echo $lang['alerts_05'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['alerts_08'] ?>
        </div>
    </td>
</tr>
<!-- Domain allowlist for user email addresses -->
<tr>
    <td class="cc_label">
        <?php echo $lang['alerts_09'] ?>
        <div class="cc_info">
            <?php echo $lang['alerts_10'] ?>
        </div>
    </td>
    <td class="cc_data">
        <textarea class='x-form-field notesbox' id='alerts_email_freeform_domain_allowlist' name='alerts_email_freeform_domain_allowlist'><?php echo $element_data['alerts_email_freeform_domain_allowlist'] ?></textarea><br/>
        <div id='alerts_email_freeform_domain_allowlist-expand' style='text-align:right;'>
            <a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#6E6E68;font-family:tahoma;font-size:10px;'
               onclick="growTextarea('alerts_email_freeform_domain_allowlist')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
        </div>
        <div class="cc_info">
            <?php echo $lang['system_config_234'] ?>
        </div>
        <div class="cc_info" style="padding:2px;border:1px solid #ccc;width:200px;">
            vanderbilt.edu<br>
            mc.vanderbilt.edu<br>
            mmc.edu<br>
        </div>
    </td>
</tr>

<tr>
    <td colspan="2">
        <div style="padding:15px 0 0 10px;color:#C00000;font-weight:bold;"><?php echo $lang['alerts_208'] ?></div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['alerts_209'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="alerts_allow_phone_variables" style="max-width:360px;">
            <option value='0' <?php echo ($element_data['alerts_allow_phone_variables'] == '0') ? "selected" : "" ?>><?php echo $lang['alerts_06'] ?></option>
            <option value='1' <?php echo ($element_data['alerts_allow_phone_variables'] == '1') ? "selected" : "" ?>><?php echo $lang['alerts_05'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['alerts_211'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['alerts_266'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="alerts_allow_phone_freeform" style="max-width:360px;">
            <option value='0' <?php echo ($element_data['alerts_allow_phone_freeform'] == '0') ? "selected" : "" ?>><?php echo $lang['alerts_06'] ?></option>
            <option value='1' <?php echo ($element_data['alerts_allow_phone_freeform'] == '1') ? "selected" : "" ?>><?php echo $lang['alerts_05'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['alerts_212'] ?>
        </div>
    </td>
</tr>


<!-- cache -->
<tr>
    <td colspan="2">
        <hr size=1>
        <h3 style="font-size:14px;padding:0 10px;color:#800000;">
            <i class="fas fa-rocket"></i>
            <?php echo $lang['system_config_871'] ?>
        </h3>
        <div style="padding:0 10px;"><?php echo $lang['system_config_870'] ?></div>
    </td>
</tr>
<tr>
    <td class="cc_label pt-3">
        <?php echo $lang['system_config_872'] ?>
        <div class="cc_info mt-3">
            <?= preg_replace('/<code>/', '<code class="a11y_c62c83_f0f0f0">', $lang['system_config_878']) ?>
        </div>
    </td>
    <td class="cc_data pt-3">
        <select class="x-form-text x-form-field" name="cache_storage_system">
            <option value='disabled' <?php echo ($element_data['cache_storage_system'] == 'disabled') ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='file' <?php echo ($element_data['cache_storage_system'] == 'file') ? "selected" : "" ?>><?php echo $lang['system_config_875'] ?></option>
            <option value='db' <?php echo ($element_data['cache_storage_system'] == 'db') ? "selected" : "" ?>><?php echo $lang['system_config_876'] ?></option>
        </select>
        <div class="cc_info mt-2">
            <?= $lang['system_config_882'] ?>
        </div>
        <div class="cc_info mt-2">
            <?= $lang['system_config_879'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?= $lang['system_config_877'] ?>
        <div class="cc_info">
            <?= $lang['system_config_880'] ?>
        </div>
    </td>
    <td class="cc_data">
        <input class="x-form-text x-form-field" style="width:400px;" autocomplete="new-password" type="text" name="cache_files_filesystem_path"  value="<?php echo htmlspecialchars($element_data['cache_files_filesystem_path'], ENT_QUOTES) ?>" placeholder="<?= APP_PATH_TEMP ?>" />
        <div class="cc_info mt-3">
            <?= $lang['system_config_881'] ?>
        </div>
    </td>
</tr>

<!-- File Upload password verification + Duplicate File External Storage -->
<tr>
    <td colspan="2">
        <hr size=1>
        <h3 style="font-size:14px;padding:0 10px;color:#800000;"><i class="fas fa-key"></i> <i class="fas fa-cloud-upload-alt"></i> &nbsp;<?php echo $lang['data_entry_445'] ?></h3>
        <div style="padding:0 10px;"><?php echo $lang['data_entry_446'] ?></div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['data_entry_501'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="file_upload_vault_filesystem_type">
            <option value='' <?php echo ($element_data['file_upload_vault_filesystem_type'] == '') ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='SFTP' <?php echo ($element_data['file_upload_vault_filesystem_type'] == 'SFTP') ? "selected" : "" ?>><?php echo $lang['data_entry_450'] ?> SFTP</option>
            <option value='WEBDAV' <?php echo ($element_data['file_upload_vault_filesystem_type'] == 'WEBDAV') ? "selected" : "" ?>><?php echo $lang['data_entry_450'] ?> WebDAV</option>
            <option value='AZURE_BLOB' <?php echo ($element_data['file_upload_vault_filesystem_type'] == 'AZURE_BLOB') ? "selected" : "" ?>>Microsoft Azure Blob Storage <?php echo $lang['system_config_735'] ?></option>
            <option value='S3' <?php echo ($element_data['file_upload_vault_filesystem_type'] == 'S3') ? "selected" : "" ?>>Amazon S3 <?php echo $lang['system_config_735'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['data_entry_448'] ?>
        </div>
    </td>
</tr>
<tr>
    <td colspan="2">
        <h4 style="font-size:13px;padding:10px 10px 0;color:#B00000;border-top:1px dashed #ddd;"><?php echo $lang['system_config_737'] ?></h4>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_736'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:300px;' autocomplete='new-password' type='text' name='file_upload_vault_filesystem_container' value='<?php echo htmlspecialchars($element_data['file_upload_vault_filesystem_container'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td colspan="2">
        <h4 style="font-size:13px;padding:10px 10px 0;color:#B00000;border-top:1px dashed #ddd;"><?php echo $lang['system_config_739'] ?></h4>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1196'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:300px;' autocomplete='new-password' type='text' name='file_upload_vault_filesystem_host' value='<?php echo htmlspecialchars($element_data['file_upload_vault_filesystem_host'], ENT_QUOTES) ?>'  /><br/>
        <div class="cc_info">
            <?php echo $lang['survey_1197'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1198'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:150px;' autocomplete='new-password' type='text' name='file_upload_vault_filesystem_username' value='<?php echo htmlspecialchars($element_data['file_upload_vault_filesystem_username'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1199'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:150px;' autocomplete='new-password' type='password' name='file_upload_vault_filesystem_password' value='<?php echo htmlspecialchars($element_data['file_upload_vault_filesystem_password'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1200'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:200px;' autocomplete='new-password' type='text' name='file_upload_vault_filesystem_path' value='<?php echo htmlspecialchars($element_data['file_upload_vault_filesystem_path'], ENT_QUOTES) ?>'  />
        <span class="cc_info" style="margin-left:6px;">e.g., /redcap/files/</span>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1201'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:200px;' autocomplete='new-password' type='text' name='file_upload_vault_filesystem_private_key_path' value='<?php echo htmlspecialchars($element_data['file_upload_vault_filesystem_private_key_path'], ENT_QUOTES) ?>'  />
        <span class="cc_info" style="margin-left:6px;">e.g., /credentials/redcap_sftp.pem</span>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_743'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="file_upload_vault_filesystem_authtype">
            <option value='AUTH_DIGEST' <?php echo ($element_data['file_upload_vault_filesystem_authtype'] == 'AUTH_DIGEST') ? "selected" : "" ?>><?php echo $lang['system_config_744'] ?></option>
            <option value='AUTH_NTLM' <?php echo ($element_data['file_upload_vault_filesystem_authtype'] == 'AUTH_NTLM') ? "selected" : "" ?>><?php echo $lang['system_config_745'] ?></option>
            <option value='AUTH_BASIC' <?php echo ($element_data['file_upload_vault_filesystem_authtype'] == 'AUTH_BASIC') ? "selected" : "" ?>><?php echo $lang['system_config_746'] ?></option>
        </select>
    </td>
</tr>

<!-- Record-level locking PDF to External Storage -->
<tr>
    <td colspan="2">
        <hr size=1>
        <h3 style="font-size:14px;padding:0 10px;color:#800000;"><i class="fas fa-file-pdf fs15"></i> <i class="fas fa-cloud-upload-alt"></i> &nbsp;<?php echo $lang['data_entry_485'] ?></h3>
        <div style="padding:0 10px;"><?php echo $lang['data_entry_486'] ?></div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1340'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="record_locking_pdf_vault_filesystem_type">
            <option value='' <?php echo ($element_data['record_locking_pdf_vault_filesystem_type'] == '') ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='SFTP' <?php echo ($element_data['record_locking_pdf_vault_filesystem_type'] == 'SFTP') ? "selected" : "" ?>><?php echo $lang['data_entry_450'] ?> SFTP</option>
            <option value='WEBDAV' <?php echo ($element_data['record_locking_pdf_vault_filesystem_type'] == 'WEBDAV') ? "selected" : "" ?>><?php echo $lang['data_entry_450'] ?> WebDAV</option>
            <option value='AZURE_BLOB' <?php echo ($element_data['record_locking_pdf_vault_filesystem_type'] == 'AZURE_BLOB') ? "selected" : "" ?>>Microsoft Azure Blob Storage <?php echo $lang['system_config_735'] ?></option>
            <option value='S3' <?php echo ($element_data['record_locking_pdf_vault_filesystem_type'] == 'S3') ? "selected" : "" ?>>Amazon S3 <?php echo $lang['system_config_735'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['data_entry_448'] ?>
        </div>
    </td>
</tr>
<tr>
    <td colspan="2">
        <h4 style="font-size:13px;padding:10px 10px 0;color:#B00000;border-top:1px dashed #ddd;"><?php echo $lang['system_config_737'] ?></h4>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_736'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:300px;' autocomplete='new-password' type='text' name='record_locking_pdf_vault_filesystem_container' value='<?php echo htmlspecialchars($element_data['record_locking_pdf_vault_filesystem_container'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td colspan="2">
        <h4 style="font-size:13px;padding:10px 10px 0;color:#B00000;border-top:1px dashed #ddd;"><?php echo $lang['system_config_739'] ?></h4>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1196'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:300px;' autocomplete='new-password' type='text' name='record_locking_pdf_vault_filesystem_host' value='<?php echo htmlspecialchars($element_data['record_locking_pdf_vault_filesystem_host'], ENT_QUOTES) ?>'  /><br/>
        <div class="cc_info">
            <?php echo $lang['survey_1197'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1198'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:150px;' autocomplete='new-password' type='text' name='record_locking_pdf_vault_filesystem_username' value='<?php echo htmlspecialchars($element_data['record_locking_pdf_vault_filesystem_username'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1199'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:150px;' autocomplete='new-password' type='password' name='record_locking_pdf_vault_filesystem_password' value='<?php echo htmlspecialchars($element_data['record_locking_pdf_vault_filesystem_password'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1200'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:200px;' autocomplete='new-password' type='text' name='record_locking_pdf_vault_filesystem_path' value='<?php echo htmlspecialchars($element_data['record_locking_pdf_vault_filesystem_path'], ENT_QUOTES) ?>'  />
        <span class="cc_info" style="margin-left:6px;">e.g., /redcap/files/</span>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1201'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:200px;' autocomplete='new-password' type='text' name='record_locking_pdf_vault_filesystem_private_key_path' value='<?php echo htmlspecialchars($element_data['record_locking_pdf_vault_filesystem_private_key_path'], ENT_QUOTES) ?>'  />
        <span class="cc_info" style="margin-left:6px;">e.g., /credentials/redcap_sftp.pem</span>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_743'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="record_locking_pdf_vault_filesystem_authtype">
            <option value='AUTH_DIGEST' <?php echo ($element_data['record_locking_pdf_vault_filesystem_authtype'] == 'AUTH_DIGEST') ? "selected" : "" ?>><?php echo $lang['system_config_744'] ?></option>
            <option value='AUTH_NTLM' <?php echo ($element_data['record_locking_pdf_vault_filesystem_authtype'] == 'AUTH_NTLM') ? "selected" : "" ?>><?php echo $lang['system_config_745'] ?></option>
            <option value='AUTH_BASIC' <?php echo ($element_data['record_locking_pdf_vault_filesystem_authtype'] == 'AUTH_BASIC') ? "selected" : "" ?>><?php echo $lang['system_config_746'] ?></option>
        </select>
    </td>
</tr>

<!-- e-Consent PDF External Storage -->
<tr>
    <td colspan="2">
        <hr size=1>
        <h3 style="font-size:14px;padding:0 10px;color:#800000;"><i class="fas fa-file-pdf fs15"></i> <i class="fas fa-cloud-upload-alt"></i> &nbsp;<?php echo $lang['survey_1192'] ?></h3>
        <div style="padding:0 10px;"><?php echo $lang['survey_1587'] ?></div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1340'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="pdf_econsent_filesystem_type">
            <option value='' <?php echo ($element_data['pdf_econsent_filesystem_type'] == '') ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='SFTP' <?php echo ($element_data['pdf_econsent_filesystem_type'] == 'SFTP') ? "selected" : "" ?>>SFTP</option>
            <option value='WEBDAV' <?php echo ($element_data['pdf_econsent_filesystem_type'] == 'WEBDAV') ? "selected" : "" ?>>WebDAV</option>
            <option value='AZURE_BLOB' <?php echo ($element_data['pdf_econsent_filesystem_type'] == 'AZURE_BLOB') ? "selected" : "" ?>>Microsoft Azure Blob Storage <?php echo $lang['system_config_735'] ?></option>
            <option value='S3' <?php echo ($element_data['pdf_econsent_filesystem_type'] == 'S3') ? "selected" : "" ?>>Amazon S3 <?php echo $lang['system_config_735'] ?></option>
        </select>
    </td>
</tr>
<tr>
    <td colspan="2">
        <h4 style="font-size:13px;padding:10px 10px 0;color:#B00000;border-top:1px dashed #ddd;"><?php echo $lang['system_config_737'] ?></h4>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_736'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:300px;' autocomplete='new-password' type='text' name='pdf_econsent_filesystem_container' value='<?php echo htmlspecialchars($element_data['pdf_econsent_filesystem_container'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td colspan="2">
        <h4 style="font-size:13px;padding:10px 10px 0;color:#B00000;border-top:1px dashed #ddd;"><?php echo $lang['system_config_739'] ?></h4>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1196'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:300px;' autocomplete='new-password' type='text' name='pdf_econsent_filesystem_host' value='<?php echo htmlspecialchars($element_data['pdf_econsent_filesystem_host'], ENT_QUOTES) ?>'  /><br/>
        <div class="cc_info">
            <?php echo $lang['survey_1197'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1198'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:150px;' autocomplete='new-password' type='text' name='pdf_econsent_filesystem_username' value='<?php echo htmlspecialchars($element_data['pdf_econsent_filesystem_username'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1199'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:150px;' autocomplete='new-password' type='password' name='pdf_econsent_filesystem_password' value='<?php echo htmlspecialchars($element_data['pdf_econsent_filesystem_password'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1200'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:200px;' autocomplete='new-password' type='text' name='pdf_econsent_filesystem_path' value='<?php echo htmlspecialchars($element_data['pdf_econsent_filesystem_path'], ENT_QUOTES) ?>'  />
        <span class="cc_info" style="margin-left:6px;">e.g., /redcap/files/</span>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1201'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:200px;' autocomplete='new-password' type='text' name='pdf_econsent_filesystem_private_key_path' value='<?php echo htmlspecialchars($element_data['pdf_econsent_filesystem_private_key_path'], ENT_QUOTES) ?>'  />
        <span class="cc_info" style="margin-left:6px;">e.g., /credentials/redcap_sftp.pem</span>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_743'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="pdf_econsent_filesystem_authtype">
            <option value='AUTH_DIGEST' <?php echo ($element_data['pdf_econsent_filesystem_authtype'] == 'AUTH_DIGEST') ? "selected" : "" ?>><?php echo $lang['system_config_744'] ?></option>
            <option value='AUTH_NTLM' <?php echo ($element_data['pdf_econsent_filesystem_authtype'] == 'AUTH_NTLM') ? "selected" : "" ?>><?php echo $lang['system_config_745'] ?></option>
            <option value='AUTH_BASIC' <?php echo ($element_data['pdf_econsent_filesystem_authtype'] == 'AUTH_BASIC') ? "selected" : "" ?>><?php echo $lang['system_config_746'] ?></option>
        </select>
    </td>
</tr>

<!-- AI Services -->
<tr>
    <td colspan="2">
        <hr size=1>
        <h3 style="font-size:14px;padding:0 10px;color:#800000;">
            <i class="fa-solid fa-wand-sparkles"></i>
            <?=RCView::tt('openai_070')?>
        </h3>
        <div class="mx-2 mb-3">
            <?=RCView::tt('openai_087')?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?=RCView::tt('openai_071')?>
        <div class="cc_info">
            <?=RCView::tt('openai_072')?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="ai_services_enabled_global">
            <option value='0' <?php echo ($element_data['ai_services_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['ai_services_enabled_global'] == 1) ? "selected" : "" ?>>Enabled using OpenAI Service</option>
            <option value='2' <?php echo ($element_data['ai_services_enabled_global'] == 2) ? "selected" : "" ?>>Enabled using Gemini AI Service</option>
        </select>
    </td>
</tr>
<tr>
    <td colspan="2">
        <h4 style="font-size:13px;padding:15px 10px 0;color:#B00000;">
            <?=RCView::tt('openai_133')?>
        </h4>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?=RCView::tt('openai_134')?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="ai_improvetext_service_enabled">
            <option value='0' <?php echo ($element_data['ai_improvetext_service_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['ai_improvetext_service_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <div class="cc_info">
            <?=RCView::tt('openai_074')?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?=RCView::tt('openai_135')?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="ai_datasummarization_service_enabled">
            <option value='0' <?php echo ($element_data['ai_datasummarization_service_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['ai_datasummarization_service_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <div class="cc_info">
            <?=RCView::tt('openai_076')?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?=RCView::tt('openai_136')?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="ai_mlmtranslator_service_enabled">
            <option value='0' <?php echo ($element_data['ai_mlmtranslator_service_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['ai_mlmtranslator_service_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <div class="cc_info">
            <?=RCView::tt('openai_098')?>
        </div>
    </td>
</tr>
<tr>
    <td colspan="2">
        <h4 style="font-size:13px;padding:15px 10px 0;color:#B00000;">
            <?=RCView::tt('openai_077')?>
        </h4>
        <div class="mx-2 mb-2"><?=RCView::tt('openai_137')?></div>
    </td>
</tr>
<tr>
    <td colspan="2"">
        <h3 style="font-size:15px;padding:20px 10px 5px;color:#666;"><?=RCView::tt('openai_120')?></h3>
    </td>
</tr>
<!-- Azure OpenAI Details -->
<tr>
    <td colspan="2" style="border-top:1px dashed #ccc;">
        <h3 style="font-size:14px;padding:10px;color:#800000;"><?=RCView::tt('openai_119')?></h3>
    </td>
</tr>
<tr>
    <td colspan="2">
        <div class="mx-2 mb-2">
            <h4 style="font-size:13px;padding:0px 10px 0;color:#B00000;"><?=RCView::tt('openai_128')?></h4>
            <ul>
                <li class="mb-3">
                    <?=RCView::tt_i('openai_124',['<a target="_blank" href="https://learn.microsoft.com/en-us/azure/ai-services/openai/how-to/create-resource?pivots=web-portal"><u>','</u></a>'],false)?>
                    <br>
                    <div class="cc_info"><?=RCView::tt('openai_125')?></div>
                </li>
                <li class="mb-3">
                    <?=RCView::tt_i('openai_126',
                        ['<a target="_blank" href="https://docs.mistral.ai/getting-started/quickstart/"><u>','</u></a>',
                            '<a target="_blank" href="https://docs.nebius.com/studio/inference/quickstart"><u>','</u></a>',
                            '<a target="_blank" href="https://console.groq.com/docs/quickstart"><u>','</u></a>',
                            '<a target="_blank" href="https://docs.together.ai/docs/quickstart"><u>','</u></a>'],false)?>
                </li>
                <li>
                    <?=RCView::tt_i('openai_127',
                        ['<a target="_blank" href="https://lmstudio.ai/docs/basics"><u>','</u></a>',
                            '<a target="_blank" href="https://localai.io/basics/getting_started/"><u>','</u></a>',
                            '<a target="_blank" href="https://docs.gpt4all.io/gpt4all_desktop/quickstart.html"><u>','</u></a>',
                            '<a target="_blank" href="https://ollama.com/download"><u>','</u></a>'],false)?>
                </li>
            </ul>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label wrapemail">
        <?=RCView::tt('openai_122')?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:400px;' autocomplete='new-password' placeholder="https://xxxx.openai.azure.com/openai/deployments/[AI_DEPLOYMENT_NAME]" type='text' name='openai_endpoint_url' value='<?php echo htmlspecialchars($element_data['openai_endpoint_url'], ENT_QUOTES) ?>'  /><br/>

    </td>
</tr>
<tr>
    <td class="cc_label">
        <?=RCView::tt('openai_083')?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:95%;max-width:300px;' autocomplete='new-password' type='password' id='openai_api_key' name='openai_api_key' value='<?php echo htmlspecialchars($element_data['openai_api_key'], ENT_QUOTES) ?>'  />
        <a href="javascript:;" class="password-mask-reveal" style="margin-left:5px;text-decoration:underline;font-size:11px;font-weight:normal;" onclick="$(this).remove();showSecret('#openai_api_key');"><?php echo $lang['system_config_258'] ?></a>
        <div class="cc_info"><?=RCView::tt('openai_129')?></div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?=RCView::tt('openai_123')?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:300px;' autocomplete='new-password' type='text' name='openai_api_version' value='<?php echo htmlspecialchars($element_data['openai_api_version'], ENT_QUOTES) ?>'  />
        <div class="cc_info">
            <?=RCView::tt('openai_130')?>
        </div>
    </td>
</tr>
<!-- Gemini AI Details -->
<tr>
    <td colspan="2" style="border-top:1px dashed #ccc;">
        <h3 style="font-size:14px;padding:10px;color:#800000;"><?=RCView::tt('openai_116')?></h3>
    </td>
</tr>
<tr>
    <td colspan="2" class="cc_data">
        <?=RCView::tt_i('openai_131',['<a target="_blank" href="https://aistudio.google.com/app/apikey"><u>','</u></a>'],false)?>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?=RCView::tt('openai_083')?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:95%;max-width:320px;' autocomplete='new-password' type='password' id='geminiai_api_key' name='geminiai_api_key' value='<?php echo htmlspecialchars($element_data['geminiai_api_key'], ENT_QUOTES) ?>'  />
        <a href="javascript:;" class="password-mask-reveal" style="margin-left:5px;text-decoration:underline;font-size:11px;font-weight:normal;" onclick="$(this).remove();showSecret('#geminiai_api_key');"><?php echo $lang['system_config_258'] ?></a>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?=RCView::tt('openai_117')?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:300px;' autocomplete='new-password' type='text' name='geminiai_api_model' value='<?php echo htmlspecialchars($element_data['geminiai_api_model'], ENT_QUOTES) ?>'  />
        <div class="cc_info">
            <?=RCView::tt('openai_132', 'a', ['href'=>'https://ai.google.dev/gemini-api/docs/models/gemini', 'target'=>'_blank', 'style'=>'text-decoration:underline;'])?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?=RCView::tt('openai_118')?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:300px;' autocomplete='new-password' type='text' name='geminiai_api_version' value='<?php echo htmlspecialchars($element_data['geminiai_api_version'], ENT_QUOTES) ?>'  />
        <div class="cc_info">

        </div>
    </td>
</tr>
</table><br/>
<div style="text-align: center;"><input type='submit' name='' value='<?=js_escape($lang['control_center_4876'])?>' /></div><br/>
</form>

<script type="module">
	import useSecret from '<?= getJSpath('modules/useSecret/index.js') ?>'
    import {useModal} from '<?= APP_PATH_JS.'Composables/index.es.js.php' ?>'
    
	useSecret('[data-sensitive]')

    async function checkURL(url, name='') {
        const modal = useModal()
        let message = ''
        let success = false

        try {
            const api = window.app_path_webroot_full+'redcap_v'+window.redcap_version+'/';
            
            const bodyParams = {
                url: url,
                name: name,
                redcap_csrf_token: window.redcap_csrf_token,
            }

            var params = new URLSearchParams();
            params.append('redcap_csrf_token', window.redcap_csrf_token);
            params.append('route', 'ControlCenterController:checkURL');

            const response = await fetch(`${api}?${params}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                mode: "cors",
                body: JSON.stringify(bodyParams),
            })
            const data = await response.json()

            if (data.success) {
                message = `URL is reachable.`;
                success = true
            } else {
                message = `URL is not reachable. Error: ${data.message || 'Unknown error'}`;
                success = false
            }
        } catch (error) {
            message = `Error: ${error.message}`;
            success = false
        } finally {
            await modal.show({
                title: success ? 'Success' : 'Error',
                body: message,
                // okText: "Ok",
                // cancelText: "Cancel",
                // size: undefined,
            })
            modal.destroy()
        }
    }

    function initTangoEnvironmentCheck() {
        const selectEnvironment = document.querySelector('[name="tango-environment"]')
        const buttonCheckEnvironment = document.querySelector('#tango-environment-check')
        buttonCheckEnvironment.addEventListener('click', async () => {
            const selectedOption = selectEnvironment.querySelector('option:checked')
            if(!selectedOption) return
            const url = selectedOption.getAttribute('data-base-url')
            await checkURL(url, 'Tango')
        })
    }

    initTangoEnvironmentCheck()

</script>
<?php
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

include 'footer.php'; ?>