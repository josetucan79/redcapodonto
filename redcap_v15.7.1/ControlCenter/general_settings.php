<?php


## HTTP COMPRESSION: If zlib PHP extension, not installed, then set $element_data['enable_http_compression'] to 0
error_reporting(0);
// Try to set compression to see if it sets it
ini_set('zlib.output_compression', 4096);
ini_set('zlib.output_compression_level', -1);
// Set boolean parameter if it is able to enable compression
$canEnableHttpCompression = (function_exists('ob_gzhandler') && ini_get('zlib.output_compression'));

require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// If the system-level language changed, go ahead and refresh it now
if (ACCESS_SYSTEM_CONFIG && isset($_POST['language_global']) && $_POST['language_global'] != $GLOBALS['language_global']) {
    $GLOBALS['lang'] = Language::getLanguage($_POST['language_global']);
}


include 'header.php';
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);
if (!ACCESS_SYSTEM_CONFIG) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); });</script>";

$changesSaved = false;

// If project default values were changed, update redcap_config table with new values
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ACCESS_SYSTEM_CONFIG)
{
	$changes_log = array();
	$sql_all = array();
	foreach ($_POST as $this_field=>$this_value) {
		// Rich text editors: Remove line breaks in the HTML to support legacy non-rich-text-editor text
		if (in_array($this_field, array('helpfaq_custom_text', 'certify_text_create', 'certify_text_prod', 'create_project_custom_text'))) {
			$this_value = str_replace(array("\r", "\n"), array("", ""), $this_value);
		}
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

	// Log any changes in log_event table
	if (count($changes_log) > 0) {
		Logging::logEvent(implode(";\n",$sql_all),"redcap_config","MANAGE","",implode(",\n",$changes_log),"Modify system configuration");
	}

	$changesSaved = true;
}

// Retrieve data to pre-fill in form
$element_data = System::getConfigVals();

// Is Read Replica available for use?
$readReplicaAvailable = System::readReplicaConnVarsFound();
if (!$readReplicaAvailable) $element_data['read_replica_enable'] = 0;

// Set value of enable_http_compression to 0 if don't have Zlib library
if (!$canEnableHttpCompression) $element_data['enable_http_compression'] = '0';

if ($changesSaved)
{
	// Show user message that values were changed
	print  "<div class='yellow' style='margin-bottom: 20px; text-align:center'>
			<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
			{$lang['control_center_19']}
			</div>";
}

// Verify Mandrill API token if that is updated
if(!empty($_POST["mandrill_api_key"]))
{
	$GLOBALS["mandrill_api_key"] = $_POST["mandrill_api_key"];

	$output = Message::sendMandrillRequest([],"metadata/list.json");
	$output = json_decode($output,true);
	if($output["status"] == "error") {
		print  "<div class='red' style='margin-bottom: 20px;'>
				<img src='".APP_PATH_IMAGES."exclamation.png'>
				Mandrill API Key cannot be validated: ".$output["message"]."
				</div>";
	}
}
?>

<h4 style="margin-top: 0;"><i class="fas fa-sliders-h"></i> <?php echo $lang['control_center_125'] ?></h4>

<form action='general_settings.php' enctype='multipart/form-data' target='_self' method='post' name='form' id='form' onSubmit="return validateEmailDomainAllowlist();">
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".System::getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0;">

<tr>
    <td colspan="2">
        <h4 style="font-size:14px;padding:10px 10px 0;color:#800000;">
            <?php echo $lang['system_config_531'] ?></h4>
        </div>
    </td>
</tr>

<tr  id="system_offline-tr" sq_id="system_offline">
	<td class="cc_label">
		<img src="<?php echo APP_PATH_IMAGES ?>off.png">
		<?php echo $lang['system_config_02'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_03'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="system_offline">
			<option value='0' <?php echo ($element_data['system_offline'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_05'] ?></option>
			<option value='1' <?php echo ($element_data['system_offline'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_04'] ?></option>
		</select>
		<div class="cc_info" style="margin-top:15px;font-weight:bold;">
			<?php echo $lang['system_config_240'] ?>
		</div>
		<textarea style='height:45px;' class='x-form-field notesbox' id='system_offline_message' name='system_offline_message'><?php echo $element_data['system_offline_message'] ?></textarea>
		<div id='system_offline_message-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('system_offline_message')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_195'] ?>
		</div>
	</td>
</tr>


<tr  id="language_global-tr" sq_id="language_global">
    <td class="cc_label"><i class="fa-solid fa-globe"></i> <?php echo $lang['system_config_112'] ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="language_global"
                onchange="alert('<?php echo $lang['global_02'] ?>:\n<?php echo js_escape($lang['system_config_113']) ?>');">
            <?php
            $languages = Language::getLanguageList();
            foreach ($languages as $language) {
                $selected = ($element_data['language_global'] == $language) ? "selected" : "";
                echo "<option value='$language' $selected>$language</option>";
            }
            ?>
        </select><br/>
        <div class="cc_info">
            <?php echo $lang['system_config_107'] ?>
            <a href="<?php echo APP_PATH_WEBROOT ?>LanguageUpdater/" target='_blank' style='text-decoration:underline;'><?=RCView::tt('lang_updater_02')?></a>
            <?php echo $lang['system_config_108'] ?>
            <a href='https://redcap.vumc.org/plugins/redcap_consortium/language_library.php' target='_blank' style='text-decoration:underline;'><?=js_escape($lang['upgrade_027'])?></a>.
            <br/><br/><?php echo $lang['system_config_109']." ".dirname(APP_PATH_DOCROOT).DS."languages".DS ?>
        </div>
    </td>
</tr>

<tr>
    <td class="cc_label">
        <i class="fa-solid fa-server"></i> <?=RCView::tt('system_config_861')?>
        <div class="cc_info">
            <?=RCView::tt('system_config_862')?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="read_replica_enable" <?=($readReplicaAvailable) ? "" : "disabled"?>>
            <option value='0' <?php echo ($element_data['read_replica_enable'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['read_replica_enable'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <?=($readReplicaAvailable) ? "" : RCView::div(['class'=>'text-dangerrc mt-1 fs11', 'style'=>'line-height:1.1;'], '<i class="fa-solid fa-circle-info"></i> '.$lang['system_config_859'])?>
        <div class="cc_info mt-3">
            <?=RCView::tt('system_config_863')?>
        </div>
        <div class="mt-3">
            <a href="javascript:;" onclick="simpleDialog(null,null,'replica-setup-dialog',900);fitDialog($('#replica-setup-dialog'));"><u><i class="fa-solid fa-circle-info"></i> <?=RCView::tt('system_config_864')?></u></a>
        </div>
        <div id="replica-setup-dialog" class="simpleDialog" title="<?=RCView::tt_js2('system_config_864')?>">
            <?=RCView::tt('system_config_855')?>
            <ol class="mt-3">
                <li class="mt-2"><?=RCView::tt('system_config_856')?></li>
                <li class="mt-3">
                    <?=RCView::tt('system_config_857')?><pre>$read_replica_hostname   = 'your_replica_host_name';
$read_replica_db     	 = 'your_replica_db_name';
$read_replica_username   = 'your_replica_db_username';
$read_replica_password   = 'your_replica_db_password';</pre>
                    <?=RCView::tt('system_config_858')?><pre>$read_replica_db_ssl_key  	= '';	// e.g., '/etc/mysql/ssl/client-key.pem'
$read_replica_db_ssl_cert 	= '';	// e.g., '/etc/mysql/ssl/client-cert.pem'
$read_replica_db_ssl_ca   	= '';	// e.g., '/etc/mysql/ssl/ca-cert.pem'
$read_replica_db_ssl_capath 	= NULL;
$read_replica_db_ssl_cipher 	= NULL;
$read_replica_db_ssl_verify_server_cert = false; // Set to TRUE to force the database connection to verify the SSL certificate</pre>
                </li>
                <li class="mt-3"><?=RCView::tt('system_config_860')?></li>
                <li class="mt-3"><?=RCView::tt_i('system_config_865',[System::REPLICA_LAG_MAX, System::REPLICA_LAG_MAX])?></li>
            </ol>
        </div>
    </td>
</tr>

<tr id="auto_report_stats-tr">
    <td class="cc_label"><?php echo $lang['system_config_28'] ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="auto_report_stats">
            <option value='0' <?php echo ($element_data['auto_report_stats'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_30'] ?></option>
            <option value='1' <?php echo ($element_data['auto_report_stats'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_31'] ?></option>
        </select>
        &nbsp;&nbsp;
        <a href="javascript:;" style="padding-left:5px;font-size:10px;font-family:tahoma;text-decoration:underline;" onclick="simpleDialog('<?php echo js_escape($lang['dashboard_94']." ".$lang['dashboard_125']) ?>','<?php echo js_escape($lang['dashboard_77']) ?>');"><?php echo $lang['dashboard_77'] ?></a>
        <div class="cc_info">
            <?php echo $lang['dashboard_90'] ?>
        </div>
    </td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['pub_105'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='redcap_base_url' value='<?php echo htmlspecialchars($element_data['redcap_base_url'], ENT_QUOTES) ?>'  onblur="
			var a = dirname(dirname(dirname(document.URL)))+'/';
			if (a != this.value && a != this.value+'/') {
				simpleDialog('<?php print js_escape($lang['control_center_4439']) ?><br><br><?php print js_escape($lang['control_center_4440']) ?> <b>'+a+'</b>');
			}
		"><br/>
		<div class="cc_info">
			<?php echo $lang['pub_110'] ?>
		</div>
		<script type="text/javascript">
		$(function(){
			var old_base_url = '<?php print js_escape($element_data['redcap_base_url']) ?>';
			var a = dirname(dirname(dirname(document.URL)))+'/';
			if (a != old_base_url && a != old_base_url+'/') {
				$('#base_url_error_msg').show();
			}
		});
		</script>
		<div id="base_url_error_msg" class="<?php echo ($redcap_base_url_display_error_on_mismatch ? "red" : "yellow") ?>" style="display:none;margin-top:5px;font-size:11px;">
			<?php if ($redcap_base_url_display_error_on_mismatch) { ?>
				<img src="<?php echo APP_PATH_IMAGES ?>bullet_delete.png">
				<b><?php echo $lang['global_48'].$lang['colon'] ?></b>
			<?php } else { ?>
				<b><?php echo $lang['global_02'].$lang['colon'] ?></b>
			<?php } ?>
			<?php echo $lang['control_center_318'] ?>
			<b><?php echo APP_PATH_WEBROOT_FULL ?></b><br><?php echo $lang['control_center_319'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_404'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_402'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='redcap_survey_base_url' value='<?php echo htmlspecialchars($element_data['redcap_survey_base_url'], ENT_QUOTES) ?>' ><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_403'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['system_config_187'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_239'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='proxy_hostname' value='<?php echo htmlspecialchars($element_data['proxy_hostname'], ENT_QUOTES) ?>'  /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_188'] ?><br>(e.g., https://10.151.18.250:211)
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['system_config_533'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_239'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='proxy_username_password' value='<?php echo htmlspecialchars($element_data['proxy_username_password'], ENT_QUOTES) ?>'  /><br/>
		<div class="cc_info">
			e.g., redcapuser:MyPassword1234
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['system_config_566'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="is_development_server">
			<option value='0' <?php echo ($element_data['is_development_server'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_567'] ?></option>
			<option value='1' <?php echo ($element_data['is_development_server'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_568'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['system_config_569'] ?>
		</div>
	</td>
</tr>

<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_631'] ?>
        <div class="cc_info">
            <?php echo $lang['system_config_632'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="allow_outbound_http">
            <option value='0' <?php echo ($element_data['allow_outbound_http'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_634'] ?></option>
            <option value='1' <?php echo ($element_data['allow_outbound_http'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_633'] ?></option>
        </select>
    </td>
</tr>

<!-- Path of custom functions PHP script  -->
<tr id="hook_functions_file-tr">
    <td class="cc_label">
        <img src="<?php echo APP_PATH_IMAGES ?>hook.png">
        <?php echo $lang['system_config_299'] ?>
        <div class="cc_info">
            <?php echo $lang['system_config_301'] ?>
        </div>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' type='text' name='hook_functions_file' value='<?php echo htmlspecialchars($element_data['hook_functions_file'], ENT_QUOTES) ?>'  />
        <div class="cc_info">
            <?php echo $lang['system_config_302'] ?>
        </div>
        <div class="cc_info" style="margin:10px 0 15px;">
            <?php echo "{$lang['system_config_64']} <span style='color:#800000;'>".dirname(dirname(dirname(__FILE__))).DS."hooks.php</span>" ?>
        </div>
    </td>
</tr>


<tr>
    <td colspan="2">
        <h4 style="border-top:1px solid #ccc;font-size:14px;padding:10px 10px 0;color:#800000;">
            <i class="fas fa-envelope" style="text-indent: 0;"></i>&nbsp;&nbsp;<?php echo $lang['system_config_637'] ?></h4>
        </div>
    </td>
</tr>

<!-- Universal FROM email address -->
<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_325'] ?>
        <div class="cc_info">
            <?php echo $lang['system_config_638'] ?>
        </div>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field ' type='text' name='from_email' value='<?php echo htmlspecialchars($element_data['from_email'], ENT_QUOTES) ?>' onblur="redcap_validate(this,'','','hard','email')"  /><br/>
        <div class="cc_info">
            <?php echo "{$lang['system_config_64']} <span style='color:#800000;'>no-reply@vanderbilt.edu, donotreply@" . SERVER_NAME ?>
        </div>
        <div class="cc_info" style="margin:10px 0 0;">
            <?php echo $lang['system_config_326'] ?>
        </div>
    </td>
</tr>

<!-- Universal DO NOT REPLY email address -->
<tr>
    <td class="cc_label">
        <?php echo $lang['control_center_4946'] ?>
        <div class="cc_info">
            <?php echo $lang['control_center_4947'] ?>
        </div>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field ' type='text' name='do_not_reply_email' value='<?php echo htmlspecialchars($element_data['do_not_reply_email'], ENT_QUOTES) ?>' onblur="redcap_validate(this,'','','hard','email')"  /><br/>
        <div class="cc_info">
            <?php echo "{$lang['system_config_64']} <span style='color:#800000;'>no-reply@" . SERVER_NAME ?>
        </div>
        <div class="cc_info" style="margin:12px 0 0;">
            <?php echo $lang['control_center_4948'] ?>
        </div>
    </td>
</tr>

<!-- Email domain allowlist for exclusion from using Universal FROM email address -->
<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_635'] ?>
        <div class="cc_info">
            <?php echo $lang['system_config_636'] ?>
        </div>
    </td>
    <td class="cc_data">
        <textarea class='x-form-field notesbox' id='from_email_domain_exclude' name='from_email_domain_exclude'><?php echo $element_data['from_email_domain_exclude'] ?></textarea><br/>
        <div id='from_email_domain_exclude-expand' style='text-align:right;'>
            <a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
               onclick="growTextarea('from_email_domain_exclude')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
        </div>
        <div class="cc_info">
            <?php echo $lang['system_config_234'] ?>
        </div>
        <div class="cc_info" style="padding:2px;border:1px solid #ccc;width:200px;">
            vumc.org<br>
            vanderbilt.edu<br>
        </div>
    </td>
</tr>

<!-- Enable/disable the use of Display Name in all emails -->
<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_647'] ?>
        <div class="cc_info">
			<?php echo $lang['system_config_650'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="max-width:380px;" name="use_email_display_name">
            <option value='0' <?php echo ($element_data['use_email_display_name'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_649'] ?></option>
            <option value='1' <?php echo ($element_data['use_email_display_name'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_648'] ?></option>
        </select>
        <div class="cc_info mt-3">
			<?php echo $lang['system_config_651'] ?>
        </div>
    </td>
</tr>

<!-- Mandrill Email API Settings -->
<tr>
    <td colspan="2">
        <h4 style="font-size:14px;padding:10px 10px 0;color:#800000;"><i class="fas fa-envelope" style="text-indent: 0;"></i>&nbsp;&nbsp;<?php echo $lang['system_config_660']."<span class='font-weight-normal ms-2'>".$lang['system_config_668'] ?></span></h4>
    </td>
</tr>
<tr>
    <td colspan="2" class="cc_data">
        <?php echo $lang['system_config_662'] ?>
        <a href="https://mandrillapp.com/api/docs/" target="_blank" style="text-decoration: underline;">https://mandrillapp.com/api/docs/</a><?php echo $lang['system_config_663'] ?></td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_661'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field '  type='text' name='mandrill_api_key' value='<?php echo htmlspecialchars($element_data['mandrill_api_key'], ENT_QUOTES) ?>'  /><br/><br/><br/>
    </td>
</tr>

<!-- SendGrid Email API Settings -->
<tr>
    <td colspan="2">
        <h4 style="font-size:14px;padding:10px 10px 0;color:#800000;"><i class="fas fa-envelope" style="text-indent: 0;"></i>&nbsp;&nbsp;<?php echo $lang['system_config_664']."<span class='font-weight-normal ms-2'>".$lang['system_config_668'] ?></span></h4>
    </td>
</tr>
<tr>
    <td colspan="2" class="cc_data">
        <?php echo $lang['system_config_666'] ?>
        <a href="https://app.sendgrid.com/guide" target="_blank" style="text-decoration: underline;">https://app.sendgrid.com/guide</a><?php echo $lang['system_config_667'] ?></td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_665'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field '  type='text' name='sendgrid_api_key' value='<?php echo htmlspecialchars($element_data['sendgrid_api_key'], ENT_QUOTES) ?>'  /><br/><br/><br/>
    </td>
</tr>

<!-- Mailgun Email API Settings -->
<tr>
    <td colspan="2">
        <h4 style="font-size:14px;padding:10px 10px 0;color:#800000;"><i class="fas fa-envelope" style="text-indent: 0;"></i>&nbsp;&nbsp;<?php echo $lang['system_config_688']."<span class='font-weight-normal ms-2'>".$lang['system_config_668'] ?></span></h4>
    </td>
</tr>
<tr>
    <td colspan="2" class="cc_data">
        <?php echo $lang['system_config_690'] ?>
        <a href="https://app.mailgun.com/app/domains" target="_blank" style="text-decoration: underline;">https://app.mailgun.com/app/domains</a><?php echo $lang['system_config_691'] ?></td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_689'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field '  type='text' name='mailgun_api_key' value='<?php echo htmlspecialchars($element_data['mailgun_api_key'], ENT_QUOTES) ?>'  />
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_692'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field '  type='text' name='mailgun_domain_name' value='<?php echo htmlspecialchars($element_data['mailgun_domain_name'], ENT_QUOTES) ?>'  />
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_849'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field '  type='text' name='mailgun_api_endpoint' value='<?php echo htmlspecialchars($element_data['mailgun_api_endpoint'], ENT_QUOTES) ?>'  />
        <div class="cc_info" style="margin-top:0;"><?=$lang['system_config_850']?></div>
    </td>
</tr>

<!-- Azure Communication Services -->
<tr>
    <td colspan="2">
        <h4 style="font-size:14px;padding:10px 10px 0;color:#800000;"><i class="fas fa-envelope" style="text-indent: 0;"></i>&nbsp;&nbsp;<?php echo $lang['system_config_817']."<span class='font-weight-normal ms-2'>".$lang['system_config_668'] ?></span></h4>
    </td>
</tr>
<tr>
    <td colspan="2" class="cc_data">
        <?php echo $lang['system_config_818'] ?>
        <a href="https://learn.microsoft.com/en-us/rest/api/communication/email" target="_blank" style="text-decoration: underline;">https://learn.microsoft.com/en-us/rest/api/communication/email</a><?php echo $lang['system_config_819'] ?></td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_820'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field '  type='text' name='azure_comm_api_key' value='<?php echo htmlspecialchars($element_data['azure_comm_api_key'], ENT_QUOTES) ?>'  /><br/><br/><br/>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_821'] ?>
        <div class="cc_info">(e.g., https://my-resource.communication.azure.com/)</div></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field '  type='text' name='azure_comm_api_endpoint' value='<?php echo htmlspecialchars($element_data['azure_comm_api_endpoint'], ENT_QUOTES) ?>'  /><br/><br/><br/>
    </td>
</tr>
<tr>
    <td colspan="2" class="cc_data" style="padding-top:0;">
        <div class="cc_info" style="margin-top:0;"><?=$lang['system_config_822']?></div>
    </td>
</tr>


<tr>
    <td colspan="2">
        <h4 style="border-top:1px solid #ccc;font-size:14px;padding:10px 10px 0;color:#800000;">
            <?php echo $lang['system_config_532'] ?></h4>
        </div>
    </td>
</tr>

<tr  id="project_contact_name-tr" sq_id="project_contact_name">
	<td class="cc_label"><?php echo $lang['system_config_549'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='project_contact_name' value='<?php echo htmlspecialchars($element_data['project_contact_name'], ENT_QUOTES) ?>'  /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_92'] ?>
		</div>
	</td>
</tr>
<tr  id="project_contact_email-tr" sq_id="project_contact_email">
	<td class="cc_label"><?php echo "{$lang['system_config_550']}" ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='project_contact_email' value='<?php echo htmlspecialchars($element_data['project_contact_email'], ENT_QUOTES) ?>'
			onblur="redcap_validate(this,'','','hard','email')" /><br/>
	</td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_776'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field ' type='text' name='contact_admin_button_url' value='<?php echo htmlspecialchars($element_data['contact_admin_button_url'], ENT_QUOTES) ?>'  />
        <div class="cc_info">
			<?php echo $lang['system_config_777'] ?>
        </div>
    </td>
</tr>
<tr  id="institution-tr" sq_id="institution">
	<td class="cc_label"><?php echo $lang['system_config_97'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='institution' value='<?php echo htmlspecialchars($element_data['institution'], ENT_QUOTES) ?>'  /><br/>
	</td>
</tr>
<tr  id="site_org_type-tr" sq_id="site_org_type">
	<td class="cc_label"><?php echo $lang['system_config_98'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='site_org_type' value='<?php echo htmlspecialchars($element_data['site_org_type'], ENT_QUOTES) ?>'  /><br/>
	</td>
</tr>
<tr  id="grant_cite-tr" sq_id="grant_cite">
	<td class="cc_label"><?php echo $lang['system_config_565'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='grant_cite' value='<?php echo htmlspecialchars($element_data['grant_cite'], ENT_QUOTES) ?>'  /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_100'] ?>
		</div>
	</td>
</tr>
<tr  id="headerlogo-tr" sq_id="headerlogo">
	<td class="cc_label"><?php echo $lang['system_config_312'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' type='text' name='headerlogo' value='<?php echo htmlspecialchars($element_data['headerlogo'], ENT_QUOTES) ?>'  /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_102'] ?>
		</div>
	</td>
</tr>


<!-- Field Comment Log default -->
<tr >
	<td class="cc_label"><?php echo $lang['system_config_328'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="field_comment_log_enabled_default">
			<option value='0' <?php echo ($element_data['field_comment_log_enabled_default'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['field_comment_log_enabled_default'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['system_config_329'] ?>
		</div>
	</td>
</tr>

<!-- Set max number of records allowed in development projects -->
<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_943'] ?>
		<div class="cc_info"><?php echo $lang['system_config_945'] ?></div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field' style='max-width:100px;' type='text' name='max_records_development_global' value='<?php echo htmlspecialchars($element_data['max_records_development_global'], ENT_QUOTES) ?>'
			onblur="redcap_validate(this,'0','','hard','int'); if (this.value == '') this.value = '0';" size='11' />
		<span style="margin-left:20px;color: #888;"><?php echo $lang['system_config_944'] ?></span>
        <div class="cc_info mt-3"><?php echo $lang['system_config_952']." ".$lang['system_config_953'] ?></div>
	</td>
</tr>

<!-- Page hit threshold per minute by IP -->
<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_265'] ?>
		<div class="cc_info"><?php echo $lang['system_config_266'] ?></div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='page_hit_threshold_per_minute' value='<?php echo htmlspecialchars($element_data['page_hit_threshold_per_minute'], ENT_QUOTES) ?>'
			onblur="redcap_validate(this,'0','','hard','int'); if (this.value == '0' || this.value == '') {
				$('#div_rate_limiter_ip_range').addClass('opacity50');
			} else {
				$('#div_rate_limiter_ip_range').removeClass('opacity50');
			}" size='10' />
		<div style="color: #888;"><?php echo $lang['system_config_267'] ?></div>
        <div class="cc_info mt-2 text-dangerrc"><?php echo $lang['config_functions_120'] ?></div>
		<div class="cc_info mt-3 mb-2">
			<?php echo $lang['system_config_894'] ?>
		</div>
        <div id="div_rate_limiter_ip_range" <?php if ($element_data['page_hit_threshold_per_minute'] == "" || $element_data['page_hit_threshold_per_minute'] == 0) print 'class="opacity50"'; ?>>
            <?php echo RCView::b($lang['system_config_428']) . " " . $lang['system_config_893'] ?> <code>1.2.3.*, 1.2.3.0-1.2.3.255, 21DA:00D3:0000:2F3B::/64</code>
            <textarea class='x-form-field notesbox' style='margin-top:3px;height:40px;' name='rate_limiter_ip_range' onblur="var invalid_ips = validateIpRanges(this.value); if (invalid_ips !== true) simpleDialog('<?php print js_escape($lang['system_config_892']) ?><br> &bull; <b>'+invalid_ips.split(',').join('</b><br> &bull; <b>')+'</b>',null,null,null,function(){ $('textarea[name=rate_limiter_ip_range]').focus(); });"><?php echo $element_data['rate_limiter_ip_range'] ?></textarea>
        </div>
	</td>
</tr>

<!-- Enable HTTP Compression -->
<tr >
	<td class="cc_label">
		<?php echo $lang['system_config_259'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_260'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_http_compression">
			<option value='0' <?php echo ($element_data['enable_http_compression'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_http_compression'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<?php if (!$canEnableHttpCompression) { ?>
		<div class="red cc_info" style="color:#C00000;">
			<?php echo $lang['system_config_264'] ?>
			<a href="http://php.net/manual/en/book.zlib.php" target="_blank" style="text-decoration:underline;">Zlib extension</a><?php echo $lang['period'] ?>
		</div>
		<?php } ?>
	</td>
</tr>

<!-- Configurable Project Deletion Lag -->
<tr>
    <td class="cc_label">
        <span class="fas fa-trash-restore" style="text-indent:0;"></span>
        <?=RCView::tt("control_center_4944")?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:50px;' type='number' min='1' step='1' name='delete_project_day_lag' value='<?php echo htmlspecialchars($GLOBALS['delete_project_day_lag'], ENT_QUOTES) ?>'
            onblur="this.value=this.value.replace(/[^0-9]/g,'');if(this.value == '' || this.value == '0'){ this.value = '1'; }">
        <div class="cc_info">
        <?=RCView::tt("control_center_4945")?>
        </div>
    </td>
</tr>

<!-- MySQL binlog_format -->
<tr >
    <td class="cc_label">
        <?php echo $lang['system_config_685'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="max-width:350px;" name="db_binlog_format">
            <option value='' <?php echo ($element_data['db_binlog_format'] == '') ? "selected" : "" ?>><?php echo $lang['system_config_684'] ?></option>
            <option value='STATEMENT' <?php echo ($element_data['db_binlog_format'] == 'STATEMENT') ? "selected" : "" ?>>STATEMENT</option>
            <option value='ROW' <?php echo ($element_data['db_binlog_format'] == 'ROW') ? "selected" : "" ?>>ROW</option>
            <option value='MIXED' <?php echo ($element_data['db_binlog_format'] == 'MIXED') ? "selected" : "" ?>>MIXED</option>
        </select>
        <div class="cc_info" style="color:#A00000;">
			<?php echo $lang['system_config_686'] ?>
        </div>
    </td>
</tr>

<tr  id="identifier_keywords-tr" sq_id="identifier_keywords">
    <td class="cc_label">
        <img src="<?php echo APP_PATH_IMAGES ?>find.png"> <?php echo "{$lang['identifier_check_01']} - {$lang['system_config_115']}" ?>
        <div class="cc_info">
			<?php echo "{$lang['system_config_116']} {$lang['identifier_check_01']}{$lang['period']} {$lang['system_config_117']}" ?>
        </div>
    </td>
    <td class="cc_data">
        <textarea class='x-form-field notesbox' id='identifier_keywords' name='identifier_keywords'><?php echo $element_data['identifier_keywords'] ?></textarea><br/>
        <div id='identifier_keywords-expand' style='text-align:right;'>
            <a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
               onclick="growTextarea('identifier_keywords')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
        </div>
        <div class="cc_info mt-0">
            <?php echo "<b>{$lang['system_config_64']}</b><br>" . System::identifier_keywords_default; ?>
        </div>
    </td>
</tr>

<tr>
    <td class="cc_label pt-5" colspan="2">
        <div class="pb-3"><?php echo $lang['system_config_226'] ?></div>
		<textarea class='x-form-field notesbox mceEditor' style='height:250px;' id='helpfaq_custom_text' name='helpfaq_custom_text'><?php echo $element_data['helpfaq_custom_text'] ?></textarea>
	</td>
</tr>

<tr>
    <td class="cc_label pt-5" colspan="2">
        <div class="pb-3"><?php echo $lang['system_config_926'] ?></div>
		<textarea class='x-form-field notesbox mceEditor' style='height:250px;' id='create_project_custom_text' name='create_project_custom_text'><?php echo $element_data['create_project_custom_text'] ?></textarea>
	</td>
</tr>

<tr>
    <td class="cc_label pt-5" colspan="2">
        <div class="pb-3"><?php echo $lang['system_config_927'] ?></div>
		<textarea class='x-form-field notesbox mceEditor' style='height:250px;' id='certify_text_create' name='certify_text_create'><?php echo $element_data['certify_text_create'] ?></textarea>
		<div class="cc_info">
			<?php echo $lang['system_config_39'] ?>
		</div>
	</td>
</tr>

<tr>
    <td class="cc_label py-5" colspan="2">
        <div class="pb-3"><?php echo $lang['system_config_928'] ?></div>
		<textarea class='x-form-field notesbox mceEditor' style='height:250px;' id='certify_text_prod' name='certify_text_prod'><?php echo $element_data['certify_text_prod'] ?></textarea>
		<div class="cc_info">
			<?php echo $lang['system_config_41'] ?>
		</div>
	</td>
</tr>
</table><br/>
<div style="text-align: center;"><input type='submit' name='' value='<?=js_escape($lang['control_center_4876'])?>' /></div><br/>
</form>

<script type="text/javascript">
    // Validate the domain names submitted for the email_domain_allowlist field
    function validateEmailDomainAllowlist() {
        // First, trim the value
        $('#from_email_domain_exclude').val( trim($('#from_email_domain_exclude').val()));
        // If it's blank, then ignore and just submit the form
        var domainAllowlist = $('#from_email_domain_exclude').val();
        if (domainAllowlist.length < 1) return true;
        // Loop through each domain (i.e. each line)
        var domainAllowlistArray = domainAllowlist.split("\n");
        var failedDomains = new Array();
        var passedDomains = new Array();
        var k = 0;
        var h = 0;
        for (var i=0; i<domainAllowlistArray.length; i++) {
            var thisDomain = trim(domainAllowlistArray[i]);
            if (thisDomain != '') {
                if (!isDomainName(thisDomain)) {
                    failedDomains[k] = thisDomain;
                    k++;
                } else {
                    passedDomains[h] = thisDomain;
                    h++;
                }
            }
        }
        // Display error message for the invalid domains
        if (k > 0) {
            simpleDialog('<?php echo js_escape($lang['system_config_639']) ?><br><br><?php echo js_escape($lang['system_config_236']) ?><br><b>'+failedDomains.join('<br>')+'</b>','<?php echo js_escape($lang['global_01']) ?>',null,null,"$('#email_domain_allowlist').focus();");
            return false;
        }
        // Set field's value with new cleaned value (trimmed and removed blank lines)
        $('#from_email_domain_exclude').val( passedDomains.join("\n") );
        return true;
    }
</script>

<?php
// Footer
include 'footer.php';
