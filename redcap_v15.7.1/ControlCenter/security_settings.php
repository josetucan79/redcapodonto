<?php



include 'header.php';
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);
if (!ACCESS_SYSTEM_CONFIG) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); });</script>";

$changesSaved = false;

// If project default values were changed, update redcap_config table with new values
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ACCESS_SYSTEM_CONFIG)
{
	// Change checkbox "on" value to 0 or 1
	$_POST['two_factor_auth_ip_range_include_private'] = (isset($_POST['two_factor_auth_ip_range_include_private']) && $_POST['two_factor_auth_ip_range_include_private'] == 'on') ? '1' : '0';
	// Remove spaces and line breaks. Replace any semi-colons with commas.
	$_POST['two_factor_auth_ip_range'] = str_replace(array(";", "\r", "\n", "\t", " "), array(",", "", "", "", ""), ($_POST['two_factor_auth_ip_range']??""));
	$_POST['two_factor_auth_ip_range_alt'] = str_replace(array(";", "\r", "\n", "\t", " "), array(",", "", "", "", ""), $_POST['two_factor_auth_ip_range_alt']);

	// Set minimum password length to "9" if left blank
    if (empty($_POST['password_length'])) {
        $_POST['password_length'] = 9;
    }
	// Loop
	$changes_log = array();
	$sql_all = array();
	foreach ($_POST as $this_field=>$this_value) {
        $this_value = (is_array($this_value) ? json_encode($this_value) : $this_value );
		// Rich text editors: Remove line breaks in the HTML to support legacy non-rich-text-editor text
		if (in_array($this_field, array('login_custom_text'))) {
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


if ($changesSaved)
{
	// Show user message that values were changed
	print  "<div class='yellow' style='margin-bottom: 20px; text-align:center'>
			<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
			{$lang['control_center_19']}
			</div>";
}


// TWO FACTOR VIA TWILIO: If have Twlio credentials saved, then quickly check them to ensure they are correct
if ($element_data['two_factor_auth_twilio_enabled']) {
	$twilio_two_factor_error = Authentication::testTwilioCrendentialsTwoFactor($element_data['two_factor_auth_twilio_account_sid'], $element_data['two_factor_auth_twilio_auth_token'],
									$element_data['two_factor_auth_twilio_from_number'], ($_SERVER['REQUEST_METHOD'] == 'POST'));
	if ($twilio_two_factor_error !== true) {
		print  "<div class='red' style='margin-bottom: 20px;'>
				<img src='".APP_PATH_IMAGES."exclamation.png'>
				$twilio_two_factor_error
				</div>";
	}
}
?>

<!--***<AAF Modification>***-->
<script>
function validateAaf(){
        var docEleArr=document.getElementsByTagName("*");
        var authMeth='';
        var accessUrl='';
        var iss='';
        var aud='';
        var createDb='';
        var scopeTarget='';
        var result=true;

        for (var i=0, max=docEleArr.length; i < max; i++) {
                if(docEleArr[i].name=="auth_meth_global")
                        authMeth=docEleArr[i].value;
                if(docEleArr[i].name=="aafAccessUrl" && docEleArr[i].value!='')
                        accessUrl=docEleArr[i].value;
                if(docEleArr[i].name=="aafAud" && docEleArr[i].value!='')
                        aud=docEleArr[i].value;
                if(docEleArr[i].name=="aafIss" && docEleArr[i].value!='')
                        iss=docEleArr[i].value; 
                if(docEleArr[i].name=="aafAllowLocalsCreateDB" && docEleArr[i].value!='')
                        createDb=docEleArr[i].value;
                if(docEleArr[i].name=="aafScopeTarget" && docEleArr[i].value!='')
                        scopeTarget=docEleArr[i].value;
        }
        if(authMeth.indexOf("aaf")>-1){
                if(accessUrl=='' || aud=='' || iss==''){
                        alert('<?php echo $lang['system_config_842'] ?>');
                        document.getElementById("aafAnch").focus();
                        result=false;
                        
                }
                if(result && createDb=='on' && scopeTarget==''){
                        alert('<?php echo $lang['system_config_564'] ?>');
                        document.getElementById("aafAnch").focus();
                        result=false;
                }
        }       
        return result;
}
</script>
<!--***</AAF Modification>***-->

<h4 style="margin-top: 0;"><i class="fas fa-shield-alt"></i> <?php echo $lang['control_center_112'] ?></h4>

<form action='security_settings.php' enctype='multipart/form-data' target='_self' method='post' name='form' id='form' onsubmit='return validateAaf()'><!--***<AAF Modification******-->
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".System::getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0;">


<!-- Auth & Login Settings -->
<tr>
	<td colspan="2">
		<h4 style="font-size:14px;padding:10px;color:#800000;">
			<img src="<?php print APP_PATH_IMAGES ?>icon_key.gif"> <?php echo $lang['system_config_352'] ?>
		</h4>
	</td>
</tr>
<tr  id="auth_meth_global-tr" sq_id="auth_meth_global">
	<td class="cc_label"><?php echo $lang['system_config_228'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_229'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-width:98%;" name="auth_meth_global">
			<option value='none' <?php echo ($element_data['auth_meth_global'] == "none" ? "selected" : "") ?>><?php echo $lang['system_config_08'] ?></option>
			<option value='table' <?php echo ($element_data['auth_meth_global'] == "table" ? "selected" : "") ?>><?php echo $lang['system_config_09'] ?></option>
			<option value='ldap' <?php echo ($element_data['auth_meth_global'] == "ldap" ? "selected" : "") ?>>LDAP</option>
			<option value='ldap_table' <?php echo ($element_data['auth_meth_global'] == "ldap_table" ? "selected" : "") ?>>LDAP & <?php echo $lang['system_config_09'] ?></option>
			<option value='shibboleth' <?php echo ($element_data['auth_meth_global'] == "shibboleth" ? "selected" : "") ?>>Shibboleth <?php echo $lang['system_config_251'] ?></option>
            <option value='shibboleth_table' <?php echo ($element_data['auth_meth_global'] == "shibboleth_table" ? "selected" : "") ?>>Shibboleth & <?php echo $lang['system_config_09'] ?> <?php echo $lang['system_config_251'] ?></option>
			<?php if ($element_data['auth_meth_global'] == "openid") { ?><option value='openid' selected>OpenID v1 (legacy)</option><?php } ?>
			<option value='openid_google' <?php echo ($element_data['auth_meth_global'] == "openid_google" ? "selected" : "") ?>>Google OAuth2 <?php echo $lang['system_config_251'] ?></option>
			<option value='oauth2_azure_ad' <?php echo ($element_data['auth_meth_global'] == "oauth2_azure_ad" ? "selected" : "") ?>>Microsoft Entra ID (formerly Azure AD) <?php echo $lang['system_config_251'] ?></option>
            <option value='oauth2_azure_ad_table' <?php echo ($element_data['auth_meth_global'] == "oauth2_azure_ad_table" ? "selected" : "") ?>>Microsoft Entra ID (formerly Azure AD) & <?php echo $lang['system_config_09'] . " " . $lang['system_config_251'] ?></option>
            <option value='rsa' <?php echo ($element_data['auth_meth_global'] == "rsa" ? "selected" : "") ?>>RSA SecurID (two-factor authentication)</option>
			<option value='sams' <?php echo ($element_data['auth_meth_global'] == "sams" ? "selected" : "") ?>>SAMS (for CDC)</option>
		    <option value='aaf' <?php echo ($element_data['auth_meth_global'] == "aaf" ? "selected" : "") ?>>AAF (Australian Access Federation)</option>
			<option value='aaf_table' <?php echo ($element_data['auth_meth_global'] == "aaf_table" ? "selected" : "") ?>>AAF (Australian Access Federation) & <?php echo $lang['system_config_09'] ?></option>
			<option value='openid_connect' <?php echo ($element_data['auth_meth_global'] == "openid_connect" ? "selected" : "") ?>><?php echo $lang['global_254'] ?> <?php echo $lang['system_config_251'] ?></option>
			<option value='openid_connect_table' <?php echo ($element_data['auth_meth_global'] == "openid_connect_table" ? "selected" : "") ?>><?php echo $lang['global_256'] ?> <?php echo $lang['system_config_251'] ?></option>
        </select>
		<div class="cc_info" style="font-weight:normal;">
			<?php echo $lang['system_config_222'] ?>
			<a href="https://redcap.vumc.org/community/post.php?id=691" target="_blank" style="text-decoration:underline;"><?php echo $lang['system_config_223'] ?></a><?php echo $lang['system_config_224'] ?>
		</div>
		<div class="cc_info" style="margin-bottom:5px;">
			<a href="<?php echo APP_PATH_WEBROOT . "ControlCenter/ldap_troubleshoot.php" ?>" style="color:#800000;text-decoration:underline;"><?php echo $lang['control_center_317'] ?></a>
		</div>
	</td>
</tr>

<!-- Two Factor Auth Settings -->
<tr>
	<td colspan="2">
		<h4 style="border-top:1px solid #ccc;font-size:14px;padding:10px 10px 0;color:#800000;">
			<img src="<?php print APP_PATH_IMAGES ?>smartphone_key.png">
			<?php echo $lang['system_config_350'] . " " . RCView::span(array('style'=>'font-weight:normal;'), $lang['system_config_354']) ?></h4>
		<div style="padding:5px 10px;line-height: 14px;">
			<?php print $lang['system_config_523'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_350'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_522'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="two_factor_auth_enabled">
			<option value='0' <?php echo ($element_data['two_factor_auth_enabled'] == "0" ? "selected" : "") ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['two_factor_auth_enabled'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
	</td>
</tr>


<tr>
	<td colspan="2" style="font-size:13px;font-weight:bold;padding:10px 10px 0;color:#800000;">
		<?php echo $lang['system_config_466'] ?>
	</td>
</tr>

<!-- Enforce on Table-based users only? (if applicable) -->
<tr>
    <td class="cc_label" style="padding-top:15px;">
        <i class="fas fa-user-alt-slash"></i> <?php echo $lang['system_config_768'] ?>
        <div class="cc_info">
            <i class="fas fa-info-circle"></i> <?php echo $lang['system_config_769'] ?>
        </div>
    </td>
    <td class="cc_data" style="padding-top:15px;">
        <select class="x-form-text x-form-field" style="max-width:95%;" name="two_factor_auth_enforce_table_users_only">
            <option value='0' <?php echo ($element_data['two_factor_auth_enforce_table_users_only'] == "0" ? "selected" : "") ?>><?php echo $lang['system_config_771'] ?></option>
            <option value='1' <?php echo ($element_data['two_factor_auth_enforce_table_users_only'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_770'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['system_config_772'] ?>
        </div>
    </td>
</tr>

<!-- Enable IP range -->
<tr>
	<td class="cc_label">
		<div class="hang">
			<img src="<?php echo APP_PATH_IMAGES ?>network_ip_local.png">
			<?php echo $lang['system_config_423'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_742'] ?>
		</div>
	</td>
	<td class="cc_data" style="padding-bottom:0;">
        <select class="x-form-text x-form-field" style="max-width:400px;" name="two_factor_auth_ip_check_enabled" onchange="
            if (this.value == '0') {
                $('textarea[name=\'two_factor_auth_ip_range\']').prop('disabled', true);
                $('input[name=\'two_factor_auth_ip_range_include_private\']').prop('disabled', true);
            } else {
                $('textarea[name=\'two_factor_auth_ip_range\']').prop('disabled', false);
                $('input[name=\'two_factor_auth_ip_range_include_private\']').prop('disabled', false);
            }">
			<option value='0' <?php echo ($element_data['two_factor_auth_ip_check_enabled'] == "0" ? "selected" : "") ?>><?php echo $lang['system_config_425'] ?></option>
			<option value='1' <?php echo ($element_data['two_factor_auth_ip_check_enabled'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_426'] ?></option>
		</select>
		<div id="div_two_factor_auth_ip_range" style="margin:18px 0 0 0;">
			<?php echo RCView::b($lang['system_config_428']) . " " . $lang['system_config_893'] ?> <code class="a11y_c62c83_f0f0f0">1.2.3.*, 1.2.3.0-1.2.3.255, 21DA:00D3:0000:2F3B::/64</code>
			<textarea class='x-form-field notesbox' style='margin-top:3px;height:70px;' name='two_factor_auth_ip_range' onblur="var invalid_ips = validateIpRanges(this.value); if (invalid_ips !== true) simpleDialog('<?php print js_escape($lang['system_config_485']) ?><br> &bull; <b>'+invalid_ips.split(',').join('</b><br> &bull; <b>')+'</b>',null,null,null,function(){ $('textarea[name=two_factor_auth_ip_range]').focus(); });" <?php if ($element_data['two_factor_auth_ip_check_enabled'] == "0") print 'disabled'; ?>><?php echo $element_data['two_factor_auth_ip_range'] ?></textarea><br/>
			<div class="hang">
				<input type="checkbox" name="two_factor_auth_ip_range_include_private" <?php if ($element_data['two_factor_auth_ip_range_include_private'] == '1') print "checked";
                    if ($element_data['two_factor_auth_ip_check_enabled'] == "0") print 'disabled'; ?>>
				<?php echo $lang['system_config_427'] ?>
				(<?php echo implode(", ", explode(",", Authentication::PRIVATE_IP_RANGES)) ?>)
			</div>
		</div>
	</td>
</tr>

<!-- 2FA trust period -->
<tr>
	<td class="cc_label" style="padding-top:16px;">
		<i class="far fa-handshake"></i>
        <?php echo $lang['system_config_517'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_464'] ?>
		</div>
	</td>
	<td class="cc_data" style="padding-top:20px;">
		<input class='x-form-text x-form-field '  type='text' name='two_factor_auth_trust_period_days' value='<?php echo htmlspecialchars($element_data['two_factor_auth_trust_period_days'], ENT_QUOTES) ?>'
			onblur="redcap_validate(this,'0','','hard','float')" size='5' />
		<div style="color: #6E6E68;"><?php echo $lang['system_config_462'] .
			RCView::SP . RCView::SP . RCView::SP . $lang['system_config_521'] ?></div>
	</td>
</tr>

<!-- Secondary auth interval for specific IP range -->
<tr>
	<td class="cc_label">
		<div style="text-indent:-3em;margin-left:3em;">
            <i class="far fa-handshake" style="text-indent:0;"></i> <img src="<?php echo APP_PATH_IMAGES ?>network_ip.png">
			<?php echo $lang['system_config_518'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_519'] ?>
		</div>
	</td>
	<td class="cc_data" style="padding-bottom:0;">
		<input class='x-form-text x-form-field '  type='text' name='two_factor_auth_trust_period_days_alt' value='<?php echo htmlspecialchars($element_data['two_factor_auth_trust_period_days_alt'], ENT_QUOTES) ?>'
			onblur="redcap_validate(this,'0','','hard','float')" size='5' />
		<div style="color: #6E6E68;"><?php echo $lang['system_config_462'] .
			RCView::SP . RCView::SP . RCView::SP . $lang['system_config_521'] ?></div>
		<div style="margin-top:15px;">
			<?php echo RCView::b($lang['system_config_520']) ?><br>
			<textarea class='x-form-field notesbox' style='margin-top:3px;height:40px;' name='two_factor_auth_ip_range_alt' onblur="var invalid_ips = validateIpRanges(this.value); if (invalid_ips !== true) simpleDialog('<?php print js_escape($lang['system_config_485']) ?><br> &bull; <b>'+invalid_ips.split(',').join('</b><br> &bull; <b>')+'</b>',null,null,null,function(){ $('textarea[name=two_factor_auth_ip_range_alt]').focus(); });"><?php echo $element_data['two_factor_auth_ip_range_alt'] ?></textarea><br/>
			<div class="fs11"><?php echo $lang['system_config_893'] ?> <code class="fs12 a11y_c62c83_f0f0f0">1.2.3.*, 1.2.3.0-1.2.3.255, 21DA:00D3:0000:2F3B::/64</code></div>
        </div>
	</td>
</tr>

<!-- Allow users to use their 6-digit 2FA PIN in place of their password when esigning (e.g., e-signature) -->
<tr>
    <td class="cc_label" style="padding-top:15px;">
        <i class="fas fa-file-signature fs14"></i>
        <?php echo $lang['data_entry_582'] ?>
    </td>
    <td class="cc_data" style="padding-top:15px;">
        <select class="x-form-text x-form-field" style="" name="two_factor_auth_esign_pin">
            <option value='0' <?php echo ($element_data['two_factor_auth_esign_pin'] == "0" ? "selected" : "") ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['two_factor_auth_esign_pin'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <div class="cc_info" style="margin-top:10px;">
            <?php echo $lang['data_entry_583'] ?>
        </div>
    </td>
</tr>

<!-- Allow users to only have to e-sign once per session -->
<tr>
    <td class="cc_label" style="padding-top:15px;">
        <i class="fas fa-file-signature fs14"></i>
        <?php echo $lang['data_entry_682']." ".$lang['data_entry_686'] ?>
    </td>
    <td class="cc_data" style="padding-top:15px;">
        <select class="x-form-text x-form-field" style="" name="two_factor_auth_esign_once_per_session">
            <option value='0' <?php echo ($element_data['two_factor_auth_esign_once_per_session'] == "0" ? "selected" : "") ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['two_factor_auth_esign_once_per_session'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <div class="cc_info" style="margin-top:10px;">
			<?php echo $lang['data_entry_683'] ?>
        </div>
        <div class="cc_info" style="margin-top:10px;">
            <?php echo $lang['data_entry_583'] ?>
        </div>
    </td>
</tr>

<tr>
	<td colspan="2" style="font-size:13px;font-weight:bold;padding:10px 10px 0;color:#800000;">
		<?php echo $lang['system_config_467'] ?>
	</td>
</tr>

<!-- Enable Google Authenticator app -->
<tr>
	<td class="cc_label">
		<div class="hang">
			<img src="<?php echo APP_PATH_IMAGES ?>microsoft_authenticator.png" style="margin-right:1px;width:16px;">
			<?php echo $lang['system_config_710'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_711'] ?>
		</div>
	</td>
	<td class="cc_data" style="padding-top:15px;">
		<select class="x-form-text x-form-field" style="" name="two_factor_auth_authenticator_enabled">
			<option value='0' <?php echo ($element_data['two_factor_auth_authenticator_enabled'] == "0" ? "selected" : "") ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['two_factor_auth_authenticator_enabled'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div class="cc_info" style="margin-top:20px;">
			<?php echo $lang['system_config_712'] ?>
		</div>
	</td>
</tr>

<!-- Enable email option for 2FA -->
<tr>
	<td class="cc_label">
		<div class="hang">
			<img src="<?php echo APP_PATH_IMAGES ?>email.png" style="margin-right:1px;">
			<?php echo $lang['system_config_459'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_460'] ?>
		</div>
	</td>
	<td class="cc_data" style="padding-top:15px;">
		<select class="x-form-text x-form-field" style="" name="two_factor_auth_email_enabled">
			<option value='0' <?php echo ($element_data['two_factor_auth_email_enabled'] == "0" ? "selected" : "") ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['two_factor_auth_email_enabled'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div class="cc_info" style="margin-top:10px;color:#800000;">
			<?php echo $lang['system_config_493']. " " . RCView::b($project_contact_email) ?>
		</span>
		<div class="cc_info" style="margin-top:10px;">
			<?php echo $lang['system_config_461'] ?>
		</div>
	</td>
</tr>

<!-- Twilio 2FA settings -->
<tr>
	<td class="cc_label">
		<div class="hang">
			<img src="<?php echo APP_PATH_IMAGES ?>twilio.png" style="margin-right:1px;">
			<?php echo $lang['system_config_405'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_409'] ?>
		</div>
	</td>
	<td class="cc_data" style="padding-top:15px;">
		<select class="x-form-text x-form-field" style="" name="two_factor_auth_twilio_enabled">
			<option value='0' <?php echo ($element_data['two_factor_auth_twilio_enabled'] == "0" ? "selected" : "") ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['two_factor_auth_twilio_enabled'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<span style="margin-left:12px;">
			<?php echo $lang['system_config_317'] ?>
			&nbsp;&nbsp;<button class="jqbuttonmed" onclick="testUrl('https://api.twilio.com','post','');return false;"><?php echo $lang['edit_project_138'] ?></button>
		</span>
		<div class="cc_info">
			<?php echo $lang['survey_712'] ?>
			<b>https://api.twilio.com</b><?php echo $lang['period']." ".$lang['survey_713'] ?>
			<a href='https://www.twilio.com' style='text-decoration:underline;' target='_blank'>https://www.twilio.com</a><?php echo $lang['period'] ?>
		</div>
		<!-- Twilio credentials -->
		<div style="margin:15px 0 0;">
			<div style="margin:5px 0;color:#800000;font-weight:bold;">
				<img src="<?php echo APP_PATH_IMAGES ?>twilio.png">
				<?php print $lang['survey_717'] ?>
			</div>
			<div style="margin:5px 0;">
				<?php print $lang['system_config_375'] ?>
				<a href='javascript:;' onclick="simpleDialog(null,null,'twilio2FAsetupExplain',550);" style='text-decoration:underline;'><?php echo $lang['system_config_376'] ?></a>
			</div>
			<table cellspacing=4 style="width:100%;">
				<tr>
					<td class="nowrap" style='color:#800000;'><?php print $lang['survey_715'] ?></td>
					<td>
						<input class='x-form-text x-form-field' style='width:260px;' type='text' name='two_factor_auth_twilio_account_sid' value='<?php echo htmlspecialchars($element_data['two_factor_auth_twilio_account_sid'], ENT_QUOTES) ?>' />
					</td>
				</tr>
				<tr>
					<td class="nowrap" style='color:#800000;'><?php print $lang['survey_716'] ?></td>
					<td>
						<input class='x-form-text x-form-field' style='width:150px;' type='password' name='two_factor_auth_twilio_auth_token' value='<?php echo htmlspecialchars($element_data['two_factor_auth_twilio_auth_token'], ENT_QUOTES) ?>' />
						<a href="javascript:;" class="cclink password-mask-reveal" style="text-decoration:underline;font-size:7pt;margin-left:5px;" onclick="$(this).remove();showPasswordField('two_factor_auth_twilio_auth_token');"><?php print $lang['survey_720'] ?></a>
					</td>
				</tr>
				<tr>
					<td class="nowrap" style='color:#800000;'><?php print $lang['survey_718'] ?></td>
					<td>
						<input class='x-form-text x-form-field' style='width:120px;' type='text' name='two_factor_auth_twilio_from_number' value='<?php echo htmlspecialchars($element_data['two_factor_auth_twilio_from_number'], ENT_QUOTES) ?>' onblur="this.value = this.value.replace(/\D/g,''); redcap_validate(this,'','','soft_typed','integer',1);" />
					</td>
				</tr>
                <tr>
                    <td style='padding-top:15px;vertical-align:top;color:#800000;font-size:11px;line-height:1.1;'><?php print $lang['global_228'] ?></td>
                    <td style='padding-top:15px;vertical-align:top;'>
                        <input class='x-form-text x-form-field' style='width:120px;' type='text' name='two_factor_auth_twilio_from_number_voice_alt' value='<?php echo htmlspecialchars($element_data['two_factor_auth_twilio_from_number_voice_alt'], ENT_QUOTES) ?>' onblur="this.value = this.value.replace(/\D/g,''); redcap_validate(this,'','','soft_typed','integer',1);" />
                        <div class="cc_info" style="line-height:1;"><?php print $lang['global_229'] ?></div>
                    </td>
                </tr>
				<tr>
					<td></td>
					<td style="padding-top:15px;">
						<button class="jqbuttonmed" onclick="
							$.post(app_path_webroot+'Authentication/two_factor_check_twilio_credentials.php',{ sid: $('input[name=two_factor_auth_twilio_account_sid]').val(),
								token: $('input[name=two_factor_auth_twilio_auth_token]').val(), phone_number: $('input[name=two_factor_auth_twilio_from_number]').val() },function(data){
								if (data == '1') {
									simpleDialog('<?php echo js_escape($lang['system_config_364']) ?>','<?php echo js_escape($lang['global_79']) ?>');
								} else {
									simpleDialog(data,'<?php echo js_escape($lang['global_01']) ?>');
								}
							});
							return false;"><?php echo $lang['system_config_362'] ?></button>
					</td>
				</tr>
			</table>
		</div>
	</td>
</tr>
<!-- Duo 2FA settings -->
<tr>
	<td class="cc_label">
		<div class="hang">
			<img src="<?php echo APP_PATH_IMAGES ?>duo.png" style="margin-right:1px;">
			<?php echo $lang['system_config_408'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_416'] ?>
		</div>
	</td>
	<td class="cc_data" style="padding-top:15px;">
		<select class="x-form-text x-form-field" style="" name="two_factor_auth_duo_enabled">
			<option value='0' <?php echo ($element_data['two_factor_auth_duo_enabled'] == "0" ? "selected" : "") ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['two_factor_auth_duo_enabled'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<!-- Duo credentials -->
		<div style="margin:15px 0 0;">
			<div style="margin:5px 0;color:green;font-weight:bold;">
				<img src="<?php echo APP_PATH_IMAGES ?>duo.png">
				<?php print $lang['system_config_411'] ?>
			</div>
			<div style="margin:5px 0;">
				<?php print $lang['system_config_410'] ?>
				<a href='javascript:;' onclick="simpleDialog(null,null,'duo2FAsetupExplain',550);" style='text-decoration:underline;'><?php echo $lang['system_config_376'] ?></a>
			</div>
			<table cellspacing=4 style="width:100%;">
				<tr>
					<td style='color:green;'><?php print $lang['system_config_412'] ?></td>
					<td>
						<input class='x-form-text x-form-field' style='width:260px;' type='text' name='two_factor_auth_duo_ikey' value='<?php echo htmlspecialchars($element_data['two_factor_auth_duo_ikey'], ENT_QUOTES) ?>' />
					</td>
				</tr>
				<tr>
					<td style='color:green;'><?php print $lang['system_config_413'] ?></td>
					<td>
						<input class='x-form-text x-form-field' style='width:180px;' type='password' name='two_factor_auth_duo_skey' value='<?php echo htmlspecialchars($element_data['two_factor_auth_duo_skey'], ENT_QUOTES) ?>' />
						<a href="javascript:;" class="cclink password-mask-reveal" style="text-decoration:underline;font-size:7pt;margin-left:5px;" onclick="$(this).remove();showPasswordField('two_factor_auth_duo_skey');"><?php print $lang['system_config_415'] ?></a>
					</td>
				</tr>
				<tr>
					<td style='color:green;'><?php print $lang['system_config_414'] ?></td>
					<td>
						<input class='x-form-text x-form-field' style='width:220px;' type='text' name='two_factor_auth_duo_hostname' value='<?php echo htmlspecialchars($element_data['two_factor_auth_duo_hostname'], ENT_QUOTES) ?>' />
					</td>
				</tr>
			</table>
		</div>
	</td>
</tr>

<!-- Login Settings -->
<tr>
	<td colspan="2">
		<h4 style="border-top:1px solid #ccc;font-size:14px;padding:10px 10px 0;color:#800000;">
			<img src="<?php print APP_PATH_IMAGES ?>list_keys.gif">
			<?php echo $lang['system_config_353'] ?>
			<?php echo RCView::span(array('style'=>'font-weight:normal;font-size:13px;margin-left:5px;'), $lang['system_config_396']) ?>
		</h4>
	</td>
</tr>
<tr  id="autologout_timer-tr" sq_id="autologout_timer">
	<td class="cc_label"><?php echo $lang['system_config_160'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='autologout_timer' value='<?php echo htmlspecialchars($element_data['autologout_timer'], ENT_QUOTES) ?>'
			onblur="if(this.value == ''){this.value = '0';} if(this.value != '0'){redcap_validate(this,'3','1440','hard','float');}" size='10' />
		<span style="color: #6E6E68;"><?php echo $lang['system_config_22'] ?></span><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_161'] ?>
		</div>
	</td>
</tr>
<!-- Login logo -->
<tr  id="login_logo-tr" sq_id="login_logo">
	<td class="cc_label"><?php echo $lang['system_config_127'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='login_logo' value='<?php echo htmlspecialchars($element_data['login_logo'], ENT_QUOTES) ?>' /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_128'] ?>
		</div>
	</td>
</tr>
<!-- Custom login text -->
<tr>
    <td class="cc_label py-4" colspan="2">
		<?php echo $lang['system_config_194'] ?>
		<div class="cc_info mb-3" style="font-weight:normal;">
			<?php echo $lang['system_config_196'] ?>
		</div>
		<textarea class='x-form-field notesbox mceEditor' id='login_custom_text' name='login_custom_text' style='height:250px;'><?php echo $element_data['login_custom_text'] ?></textarea>
	</td>
</tr>

<tr  id="logout_fail_limit-tr" sq_id="logout_fail_limit">
	<td class="cc_label"><?php echo $lang['system_config_120'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='logout_fail_limit' value='<?php echo htmlspecialchars($element_data['logout_fail_limit'], ENT_QUOTES) ?>'
			onblur="redcap_validate(this,'0','','hard','int')" size='10' />
		<span style="color: #6E6E68;"><?php echo $lang['system_config_121'] ?></span><br/>
	</td>
</tr>
<tr  id="logout_fail_window-tr" sq_id="logout_fail_window">
	<td class="cc_label"><?php echo $lang['system_config_122'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='logout_fail_window' value='<?php echo htmlspecialchars($element_data['logout_fail_window'], ENT_QUOTES) ?>'
			onblur="redcap_validate(this,'0','','hard','int')" size='10' />
		<span style="color: #6E6E68;"><?php echo $lang['system_config_123'] ?></span><br/>
	</td>
</tr>
<tr  id="login_autocomplete_disable-tr" sq_id="login_autocomplete_disable">
	<td class="cc_label"><?php echo $lang['system_config_32'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="login_autocomplete_disable">
			<option value='0' <?php echo ($element_data['login_autocomplete_disable'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_35'] ?></option>
			<option value='1' <?php echo ($element_data['login_autocomplete_disable'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_34'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo "{$lang['global_02']}{$lang['colon']} {$lang['system_config_33']}" ?>
		</div>
	</td>
</tr>


<!-- Additional Tabled-based Authentication Settings -->
<tr>
	<td colspan="2">
		<hr size=1>
		<h4 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['system_config_162'] ?></h4>
	</td>
</tr>
<!-- Password recovery custom text -->
<tr>
	<td class="cc_label pb-3" colspan="2">
		<?php echo $lang['system_config_268'] ?>
		<div class="cc_info mb-3" style="font-weight:normal;">
			<?php echo $lang['control_center_4830'] ?>
		</div>
		<textarea class='x-form-field notesbox mceEditor' style='height:220px;' id='password_recovery_custom_text' name='password_recovery_custom_text'><?php echo $element_data['password_recovery_custom_text'] ?></textarea>
		<div class="cc_info" style="font-weight:normal;">
			<?php
			echo $lang['system_config_270'] .
				RCView::div(array('style'=>'color:#800000;'),
					"\"".$lang['pwd_reset_78']." XXXXXX ".$lang['pwd_reset_79']."\""
				);
			?><br>
			<?php echo $lang['control_center_4831'] ?>
        </div>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_136'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="password_history_limit">
			<option value='0' <?php echo ($element_data['password_history_limit'] == 0) ? "selected" : "" ?>><?php echo $lang['design_99'] ?></option>
			<option value='1' <?php echo ($element_data['password_history_limit'] == 1) ? "selected" : "" ?>><?php echo $lang['design_100'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_137'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_138'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='password_reset_duration' value='<?php echo htmlspecialchars($element_data['password_reset_duration'], ENT_QUOTES) ?>'
			onblur="redcap_validate(this,'0','','hard','float')" size='10' />
		<span style="color: #6E6E68;"><?php echo $lang['system_config_140'] ?></span><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_139'] ?>
		</div>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_693'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' type='text' name='password_length' value='<?php echo htmlspecialchars($element_data['password_length'], ENT_QUOTES) ?>'
               onblur="redcap_validate(this,'6','99','hard','integer',1)" size='2' maxlength="2" />
        <div class="cc_info">
            <?php echo $lang['system_config_694'] ?>
        </div>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_695'] ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="max-width:400px;" name="password_complexity">
            <option value='0' <?php echo ($element_data['password_complexity'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_696'] ?></option>
            <option value='1' <?php echo ($element_data['password_complexity'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_697'] ?></option>
            <option value='2' <?php echo ($element_data['password_complexity'] == 2) ? "selected" : "" ?>><?php echo $lang['system_config_698'] ?></option>
            <option value='3' <?php echo ($element_data['password_complexity'] == 3) ? "selected" : "" ?>><?php echo $lang['system_config_699'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['system_config_700'] ?><br><code class="fs12 a11y_c62c83_f0f0f0">!@#$%^&*()/_+|~=’,-*+:\";?.</code> (excluding <code class="fs12 a11y_c62c83_f0f0f0">><\</code> )
        </div>
    </td>
</tr>
<!-- Additional Google OAuth2 Settings -->
<tr>
	<td colspan="2">
		<hr size=1>
		<h4 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['system_config_381'] ?>
		</h4>
		<div class="cc_info" style="margin: 5px 10px;">
			<b><?php echo $lang['system_config_387'] ?></b><br><?php echo $lang['system_config_384'] ?>
			<a href="https://console.developers.google.com" target="_blank" style="text-decoration:underline;">Google Developers Console</a>
			<?php echo $lang['system_config_385'] ?> <b style="color:#800000;"><?php echo APP_PATH_WEBROOT_FULL ?></b>
			<?php echo $lang['system_config_386'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_382'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field'  type='text' name='google_oauth2_client_id' value='<?php echo htmlspecialchars($element_data['google_oauth2_client_id'], ENT_QUOTES) ?>'  /><br/>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_383'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field'  type='text' name='google_oauth2_client_secret' value='<?php echo htmlspecialchars($element_data['google_oauth2_client_secret'], ENT_QUOTES) ?>'  /><br/>
	</td>
</tr>

<!-- Additional Microsoft Entra ID (formerly Azure AD) Settings -->
<tr>
	<td colspan="2">
		<hr size=1>
		<h4 style="font-size:14px;padding:0 10px;color:#800000;">
			<span style="vertical-align:middle;margin-left:2px;"> <?php echo $lang['system_config_886'] ?></span>
		</h4>
		<div class="cc_info" style="margin: 5px 10px;">
			<?php echo $lang['system_config_885'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_887'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field'  type='text' name='oauth2_azure_ad_client_id' value='<?php echo htmlspecialchars($element_data['oauth2_azure_ad_client_id'], ENT_QUOTES) ?>'  /><br/>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_888'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field'  type='password' name='oauth2_azure_ad_client_secret' value='<?php echo htmlspecialchars($element_data['oauth2_azure_ad_client_secret'], ENT_QUOTES) ?>'  /><br/>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_890'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="oauth2_azure_ad_endpoint_version">
			<option value='V1' <?php echo ($element_data['oauth2_azure_ad_endpoint_version'] == "V1" ? "selected" : "") ?>>V1 <?=RCView::tt('multilang_225')?></option>
			<option value='V2' <?php echo ($element_data['oauth2_azure_ad_endpoint_version'] == "V2" ? "selected" : "") ?>>V2</option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_889'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_891'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_815'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field'  type='text' name='oauth2_azure_ad_tenant' value='<?php echo htmlspecialchars($element_data['oauth2_azure_ad_tenant'], ENT_QUOTES) ?>'  /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_814'] ?>
		</div>
	</td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_883'] ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="oauth2_azure_ad_username_attribute">
            <option value='userPrincipalName' <?php echo ($element_data['oauth2_azure_ad_username_attribute'] == "userPrincipalName" ? "selected" : "") ?>>userPrincipalName <?=RCView::tt('multilang_225')?></option>
            <option value='onPremisesSamAccountName' <?php echo (($element_data['oauth2_azure_ad_username_attribute'] == "onPremisesSamAccountName" || $element_data['oauth2_azure_ad_username_attribute'] == "samAccountName") ? "selected" : "") ?>>onPremisesSamAccountName</option>
            <option value='mail' <?php echo ($element_data['oauth2_azure_ad_username_attribute'] == "mail" ? "selected" : "") ?>>mail</option>
            <option value='employeeId' <?php echo ($element_data['oauth2_azure_ad_username_attribute'] == "employeeId" ? "selected" : "") ?>>employeeId</option>
         </select><br/>
        <div class="cc_info">
            <?php echo $lang['system_config_741'] ?>
        </div>
    </td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_679'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field'  type='text' name='oauth2_azure_ad_primary_admin' value='<?php echo htmlspecialchars($element_data['oauth2_azure_ad_primary_admin'], ENT_QUOTES) ?>'  /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_682'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_680'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field'  type='text' name='oauth2_azure_ad_secondary_admin' value='<?php echo htmlspecialchars($element_data['oauth2_azure_ad_secondary_admin'], ENT_QUOTES) ?>'  /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_683'] ?>
		</div>
	</td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['global_292'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field'  type='text' name='oauth2_azure_ad_name' value='<?php echo htmlspecialchars($element_data['oauth2_azure_ad_name'], ENT_QUOTES) ?>'  />
        <div class="cc_info">
            <?php echo $lang['global_294'] ?>
        </div>
    </td>
</tr>

<!-- Additional OpenID Connect Settings -->
<tr>
    <td colspan="2">
        <hr size=1>
        <h4 style="font-size:14px;padding:0 10px;color:#800000;">
            <span style="vertical-align:middle;margin-left:2px;"> <?php echo $lang['system_config_749'] ?></span>
        </h4>
        <div class="cc_info" style="margin: 5px 10px;">
            <?php echo $lang['system_config_755'] .
                "<div class='mt-2 fs13'>REDIRECT_URL = <code class='fs13 a11y_c62c83_f0f0f0'>".APP_PATH_WEBROOT_FULL."index.php</code>
                 <br>POST_LOGOUT_REDIRECT_URI = <code class='fs13 a11y_c62c83_f0f0f0'>".APP_PATH_WEBROOT_FULL."index.php?logout=1</code></div>" ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_750'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field'  type='text' name='openid_connect_client_id' value='<?php echo htmlspecialchars($element_data['openid_connect_client_id'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_751'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field'  type='password' name='openid_connect_client_secret' value='<?php echo htmlspecialchars($element_data['openid_connect_client_secret'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_847'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field'  type='text' name='openid_connect_additional_scope' value='<?php echo htmlspecialchars($element_data['openid_connect_additional_scope'], ENT_QUOTES) ?>'  />
        <div class="cc_info">
            <?php echo $lang['system_config_848'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_939'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field'  type='text' name='openid_connect_override_scope' value='<?php echo htmlspecialchars($element_data['openid_connect_override_scope'], ENT_QUOTES) ?>'  />
        <div class="cc_info">
            <?php echo $lang['system_config_940'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_752'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field'  type='text' name='openid_connect_provider_url' value='<?php echo htmlspecialchars($element_data['openid_connect_provider_url'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_753'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field'  type='text' name='openid_connect_metadata_url' value='<?php echo htmlspecialchars($element_data['openid_connect_metadata_url'], ENT_QUOTES) ?>'  /><br/>
        <div class="cc_info">
            <?php echo preg_replace('/<code>/', '<code class="a11y_c62c83_f0f0f0">', $lang['system_config_754']) ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_766'] ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="openid_connect_username_attribute">
            <option value='username' <?php echo ($element_data['openid_connect_username_attribute'] == "username" ? "selected" : "") ?>>username <?=RCView::tt('multilang_225')?></option>
            <option value='preferred_username' <?php echo ($element_data['openid_connect_username_attribute'] == "preferred_username" ? "selected" : "") ?>>preferred_username</option>
            <option value='email' <?php echo ($element_data['openid_connect_username_attribute'] == "email" ? "selected" : "") ?>>email</option>
            <option value='nickname' <?php echo ($element_data['openid_connect_username_attribute'] == "nickname" ? "selected" : "") ?>>nickname</option>
            <option value='sub' <?php echo ($element_data['openid_connect_username_attribute'] == "sub" ? "selected" : "") ?>>sub</option>
        </select><br/>
        <div class="cc_info">
            <?php echo $lang['system_config_767'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo preg_replace('/<code>/', '<code class="a11y_c62c83_f0f0f0">', $lang['system_config_788']) ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="openid_connect_response_type">
            <option value='query' <?php echo ($element_data['openid_connect_response_type'] == "query" ? "selected" : "") ?>>query <?=RCView::tt('multilang_225')?></option>
            <option value='form_post' <?php echo ($element_data['openid_connect_response_type'] == "form_post" ? "selected" : "") ?>>form_post</option>
        </select>
        <div class="cc_info">
            <?php echo $lang['system_config_789'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_679'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field'  type='text' name='openid_connect_primary_admin' value='<?php echo htmlspecialchars($element_data['openid_connect_primary_admin'], ENT_QUOTES) ?>'  /><br/>
        <div class="cc_info">
            <?php echo $lang['system_config_682'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_680'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field'  type='text' name='openid_connect_secondary_admin' value='<?php echo htmlspecialchars($element_data['openid_connect_secondary_admin'], ENT_QUOTES) ?>'  />
        <div class="cc_info">
            <?php echo $lang['system_config_683'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['global_264'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field'  type='text' name='openid_connect_name' value='<?php echo htmlspecialchars($element_data['openid_connect_name'], ENT_QUOTES) ?>'  />
        <div class="cc_info">
            <?php echo $lang['global_265'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_900'] ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' type='text' name='openid_connect_logout' value='<?php echo htmlspecialchars($element_data['openid_connect_logout'], ENT_QUOTES) ?>'  /><br/>
        <div class="cc_info">
            <?php echo $lang['system_config_47'] ?>
        </div>
    </td>
</tr>

<!-- Additional Shibboleth Authentication Settings -->
<script>
// Allow entry of custom values in a dropdown menu
// if a value is entered that is not in $_SERVER, behavior will default to "None"
// adapted from https://stackoverflow.com/a/20532400
(function ($) {

    $.fn.otherize = function (option_text, texts_placeholder_text) {
        oSel = $(this);
        option_id = oSel.attr('id') + '_other';
        textbox_id = option_id + "_tb";

        this.append("<option value='' id='" + option_id + "' class='otherize' >" + option_text + "</option>");
        this.after("<input type='text' id='" + textbox_id + "' style='display: none; border-bottom: 1px solid black' placeholder='" + texts_placeholder_text + "'/>");
        this.change(

        function () {
            oTbox = oSel.parent().children('#' + textbox_id);
            oSel.children(':selected').hasClass('otherize') ? oTbox.show() : oTbox.hide();
        });

        $("#" + textbox_id).change(

        function () {
            $("#" + option_id).val($("#" + textbox_id).val());
        });
    };

    $.fn.fillShibuserVal = function (selected_option) {
        var presets = ['none', 'REMOTE_USER', 'HTTP_REMOTE_USER', 'HTTP_AUTH_USER', 'HTTP_SHIB_EDUPERSON_PRINCIPAL_NAME','Shib-EduPerson-Principal-Name']; 
        if (!presets.includes(selected_option)) {
            var custom_option = (`<option value='${selected_option}' selected>${selected_option}</option>`);
            this.append(custom_option);
        };
    };

}(jQuery));

$(function () {
    // passing $GLOBALS['shibboleth_username_field'] here results in the previous value being displayed until the page is navigated to again
    // this may be due to $GLOBALS being processed "just-in-time" which is after the DOM has loaded
    $("#otherize").fillShibuserVal('<?php echo js_escape($element_data['shibboleth_username_field']); ?>');

    $("#otherize").otherize("other", "Enter your own");
});
</script>


<tr>
	<td colspan="2">
		<hr size=1>
		<h4 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['system_config_158'] ?></h4>
	</td>
</tr>
<tr>
    <td colspan="2">
        <a style="padding:0 10px;" href="<?php echo APP_PATH_WEBROOT . "Help/shib_table_help.php" ?>" target="_blank"><?php echo RCView::tt('system_config_913') ?></a>
    </td>
</tr>
<tr  id="shibboleth_username_field-tr" sq_id="shibboleth_username_field">
	<td class="cc_label"><?php echo $lang['system_config_44'] ?></td>
	<td class="cc_data">
		<select id="otherize" class="x-form-text x-form-field" style="" name="shibboleth_username_field">
			<option value='none' <?php echo ($element_data['shibboleth_username_field'] == "none" ? "selected" : "") ?>><?php echo $lang['system_config_45'] ?></option>
			<option value='REMOTE_USER' <?php echo ($element_data['shibboleth_username_field'] == "REMOTE_USER" ? "selected" : "") ?>>REMOTE_USER</option>
			<option value='HTTP_REMOTE_USER' <?php echo ($element_data['shibboleth_username_field'] == "HTTP_REMOTE_USER" ? "selected" : "") ?>>HTTP_REMOTE_USER</option>
			<option value='HTTP_AUTH_USER' <?php echo ($element_data['shibboleth_username_field'] == "HTTP_AUTH_USER" ? "selected" : "") ?>>HTTP_AUTH_USER</option>
			<option value='HTTP_SHIB_EDUPERSON_PRINCIPAL_NAME' <?php echo ($element_data['shibboleth_username_field'] == "HTTP_SHIB_EDUPERSON_PRINCIPAL_NAME" ? "selected" : "") ?>>HTTP_SHIB_EDUPERSON_PRINCIPAL_NAME</option>
			<option value='Shib-EduPerson-Principal-Name' <?php echo ($element_data['shibboleth_username_field'] == "Shib-EduPerson-Principal-Name" ? "selected" : "") ?>>Shib-EduPerson-Principal-Name</option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_324'] ?>
		</div>
	</td>
</tr>
<tr  id="shibboleth_logout-tr" sq_id="shibboleth_logout">
	<td class="cc_label"><?php echo $lang['system_config_46'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='shibboleth_logout' value='<?php echo htmlspecialchars($element_data['shibboleth_logout'], ENT_QUOTES) ?>'  /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_47'] ?>
		</div>
	</td>
</tr>

<!-- Shibboleth User Information Settings -->

<tr>
	<td colspan="2">
		<hr size=1>
		<h4 style="font-size:14px;padding:0 10px;color:#800000;">
			<?php echo RCView::tt('system_config_907') ?>
		</h4>
		<div class="cc_info" style="margin: 5px 10px;font-weight:normal;"><?php echo RCView::tt('system_config_908') ?></div>	
	</td>
</tr>
<tr id="shibboleth_set_userinfo-tr" sq_id="shibboleth_set_userinfo">
	<td class="cc_label"><?php echo RCView::tt('system_config_911') ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="shibboleth_set_userinfo">
			<option value='0' <?php echo ($element_data['shibboleth_set_userinfo'] == "0" ? "selected" : "") ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['shibboleth_set_userinfo'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo RCView::tt('system_config_912') ?>
		</div>
	</td>
</tr>
<tr id="shibboleth_override_userinfo-tr" sq_id="shibboleth_override_userinfo">
	<td class="cc_label"><?php echo RCView::tt('system_config_909') ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="shibboleth_override_userinfo">
			<option value='0' <?php echo ($element_data['shibboleth_override_userinfo'] == 0) ? "selected" : "" ?>><?php echo $lang['design_99'] ?></option>
			<option value='1' <?php echo ($element_data['shibboleth_override_userinfo'] == 1) ? "selected" : "" ?>><?php echo $lang['design_100'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo RCView::tt('system_config_910') ?>
		</div>
	</td>
</tr>
<tr id="shibboleth_user_firstname_field-tr" sq_id="shibboleth_user_firstname_field">
	<td class="cc_label"><?php echo RCView::tt('system_config_901') ?></td>
	<td class="cc_data">
		<input class="x-form-text x-form-field" type="text" name="shibboleth_user_firstname_field" value="<?php echo htmlspecialchars($element_data['shibboleth_user_firstname_field'], ENT_QUOTES) ?>" /><br/>
		<div class="cc_info">
			<?php echo RCView::tt('system_config_902') ?>
		</div>
	</td>
</tr>
<tr id="shibboleth_user_lastname_field-tr" sq_id="shibboleth_user_lastname_field">
	<td class="cc_label"><?php echo RCView::tt('system_config_903') ?></td>
	<td class="cc_data">
		<input class="x-form-text x-form-field" type="text" name="shibboleth_user_lastname_field" value="<?php echo htmlspecialchars($element_data['shibboleth_user_lastname_field'], ENT_QUOTES) ?>" /><br/>
		<div class="cc_info">
			<?php echo RCView::tt('system_config_904') ?>
		</div>
	</td>
</tr>
<tr id="shibboleth_user_email_field-tr" sq_id="shibboleth_user_email_field">
	<td class="cc_label"><?php echo RCView::tt('system_config_905') ?></td>
	<td class="cc_data">
		<input class="x-form-text x-form-field" type="text" name="shibboleth_user_email_field" value="<?php echo htmlspecialchars($element_data['shibboleth_user_email_field'], ENT_QUOTES) ?>" /><br/>
		<div class="cc_info">
			<?php echo RCView::tt('system_config_906') ?>
		</div>
	</td>
</tr>

<!-- Shibboleth & Table Authentication Settings -->

<?php
    $shibboleth_table_config = json_decode($element_data['shibboleth_table_config'], TRUE);
    $IdPData = $shibboleth_table_config['institutions'][0];
?>

<script>
    const shibTableConfig = <?php echo $element_data["shibboleth_table_config"]; ?>;
    const repeatShibParams = shibTableConfig.institutions;

    // Append additional IdP options to dropdown
    // TODO: if 'inst-login1' is set as default, 'inst-login<LAST>' will end up being set on page load due to $.clone
    function appendDropdown(index) {
        const prevEntry = $('[value="inst-login' + (index - 1) + '"]').last();
        var newEntry = prevEntry.clone();
        newEntry.insertAfter(prevEntry);
        newEntry.text(repeatShibParams[index]['login_option'])
                .attr('value', 'inst-login' + index);
    }

</script>

<tr>
    <td colspan="2">
        <hr size=1>
        <h4 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['auth_01']; ?></h4>
    </td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['auth_02']; ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="shibboleth_table_config[splash_default]">
            <option value='non-inst-login' <?php echo ($shibboleth_table_config['splash_default'] == 'non-inst-login') ? "selected" : "" ?>><?php echo $lang['system_config_09']; ?></option>
            <option value='inst-login0' <?php echo ($shibboleth_table_config['splash_default'] == 'inst-login0') ? "selected" : "" ?>><?php echo $IdPData['login_option']; ?></option>
        </select><br/>
    </td>
</tr>
<tr  id="shibboleth_table_table_login_option-tr" sq_id="shibboleth_table_table_login_option">
    <td class="cc_label"><?php echo $lang['auth_03']; ?></td>
    <td class="cc_data">
        <input class='x-form-text x-form-field '  type='text' name='shibboleth_table_config[table_login_option]' value='<?php echo htmlspecialchars($shibboleth_table_config['table_login_option'], ENT_QUOTES) ?>'  />
        <br/>
        <div class="cc_info">
            <?php echo $lang['auth_04']; ?>
        </div>
    </td>
</tr>


<!-- Repeat for Multiple IdPs -->

<script>
      $(document).ready(function() {

          $(document).on('click', '.addIdP', function(event) {
              event.preventDefault();
              appendIdPOptions(true, $('.repeatingIdP').length - 1);
          });

          $(document).on('click', '.deleteIdP', function(event) {
              event.preventDefault();
              $(this).parents('tbody').fadeOut('normal', function () { $(this).remove(); });
          });

          function populateData() {
              const numIdPs = repeatShibParams.length;
              for (let i = 1; i < numIdPs; i++) {
                  appendIdPOptions(false, i - 1);
                  appendDropdown(i);
              }
          }
          populateData();

          function appendIdPOptions(userCreated = false, lastIdPIndex = null) {
              var prevEntry = $('.repeatingIdP').last();
              if (lastIdPIndex === 0) {
                  // Only allow IdP deletion with >1 IdP entries
                  $(prevEntry).find('.addIdP').after('<button class="deleteIdP"><?php echo $lang['auth_15']; ?></button>');
              }
              var newEntry = prevEntry.clone();
              prevEntry.find('.addIdP').remove();
              newEntry.insertAfter(prevEntry);

              if (userCreated) {
                  const lastIdPIndex = $('.repeatingIdP').length - 1;
              }
              const newIdPIndex = lastIdPIndex + 1;

              // Increment indices and blank inputs
              newEntry.find('input').each(function(name, input) {
                  input.name = input.name.replace("[" + lastIdPIndex + "]", "[" + newIdPIndex + "]");
                  const inputParam = input.name.split('[').pop().slice(0, -1);
                  if (!userCreated) {
                    try {
                        input.value = repeatShibParams[newIdPIndex][inputParam];
                    } catch (err) {
                        input.value = '';
                    }
                  } else {
                      input.value = '';
                  }
              });

              if (userCreated) {
                  newEntry.hide()
                          .effect("highlight", {color: '#ffedc9'}, 1000);
              }
          }

      });
</script>


<tbody class="repeatingIdP">
    <tr>
        <td colspan="2">
            <hr size=1>
            <h4 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['auth_05']; ?></h4>
        </td>
    </tr>

    <tr id="shibboleth_table_shibboleth_login_option-tr" sq_id="shibboleth_table_shibboleth_login_option">
        <td class="cc_label"><?php echo $lang['auth_06']; ?></td>
        <td class="cc_data">
            <input class='x-form-text x-form-field ' type='text' name='shibboleth_table_config[institutions][0][login_option]' value='<?php echo htmlspecialchars($IdPData['login_option'], ENT_QUOTES) ?>' />
            <br/>
            <div class="cc_info">
                <?php echo $lang['auth_07']; ?>
            </div>
        </td>
    </tr>

    <tr id="shibboleth_table_shibboleth_login_text-tr" sq_id="shibboleth_table_shibboleth_login_text">
        <td class="cc_label"><?php echo $lang['auth_08']; ?></td>
        <td class="cc_data">
            <input class='x-form-text x-form-field ' type='text' name='shibboleth_table_config[institutions][0][login_text]' value='<?php echo htmlspecialchars($IdPData['login_text'], ENT_QUOTES) ?>' />
            <br/>
            <div class="cc_info">
                <?php echo $lang['auth_09']; ?>
            </div>
        </td>
    </tr>

    <tr id="shibboleth_table_login_image-tr" sq_id="shibboleth_table_login_image">
        <td class="cc_label"><?php echo $lang['auth_10']; ?></td>
        <td class="cc_data">
            <input class='x-form-text x-form-field ' type='text' name='shibboleth_table_config[institutions][0][login_image]' value='<?php echo htmlspecialchars($IdPData['login_image'], ENT_QUOTES) ?>' />
            <br/>
            <div class="cc_info">
                <?php echo $lang['auth_11']; ?>
            </div>
        </td>
    </tr>

    <tr id="shibboleth_table_shibboleth_login_url-tr" sq_id="shibboleth_table_shibboleth_login_url">
        <td class="cc_label"><?php echo $lang['auth_12']; ?></td>
        <td class="cc_data">
            <input class='x-form-text x-form-field ' type='text' name='shibboleth_table_config[institutions][0][login_url]' value='<?php echo htmlspecialchars($IdPData['login_url'], ENT_QUOTES) ?>' />
            <br/>
            <div class="cc_info">
                <?php echo $lang['auth_16'] ?>
            </div>
        </td>
    </tr>

    <tr>
        <td class="cc_label"><?php echo $lang['auth_13']; ?></td>
        <td class="cc_data">
            <button class='addIdP'><?php echo $lang['auth_14']; ?></button>
        </td>
    </tr>
</tbody>
<!-- End Repeat for Multiple IdPs -->

<!--***<AAF Modification>***-->
<tr>
        <td colspan="2">
                <hr size=1>
                <h4 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['system_config_833'] ?></h4>
        <div class="cc_info" style="margin: 5px 10px;font-weight:normal;">
			<?php echo $lang['system_config_827'] ?>
			<a href="https://redcap.vumc.org/community/post.php?id=57043" target="_blank" style="text-decoration:underline;"><?php echo $lang['system_config_828'] ?></a><?php echo $lang['system_config_829'] ?>
		</div>
        </td>
</tr>

<tr  id="aafAccessUrl-tr" sq_id="aafAccessUrl">
        <td class="cc_label"><?php echo $lang['system_config_834'] ?></td>
        <td class="cc_data">
                <input class='x-form-text x-form-field' type='text' name='aafAccessUrl' value='<?php echo htmlspecialchars($element_data['aafAccessUrl'], ENT_QUOTES) ?>' id='aafAnch'/><br/>
                <div class="cc_info">
                        <?php echo $lang['system_config_830'] ?>
                </div>
        </td>
</tr>

<tr  id="aafAud-tr" sq_id="aafAud">
        <td class="cc_label"><?php echo $lang['system_config_835'] ?></td>
        <td class="cc_data">
                <input class='x-form-text x-form-field'  type='text' name='aafAud' value='<?php echo htmlspecialchars($element_data['aafAud'], ENT_QUOTES) ?>'  /><br/>
                <div class="cc_info">
                        <?php echo $lang['system_config_831'] ?>
                </div>
        </td>
</tr>

<tr  id="aafIss-tr" sq_id="aafIss">
        <td class="cc_label"><?php echo $lang['system_config_836'] ?></td>
        <td class="cc_data">
                <input class='x-form-text x-form-field'  type='text' name='aafIss' value='<?php echo htmlspecialchars($element_data['aafIss'], ENT_QUOTES) ?>' /><br/>
                <div class="cc_info">
                        <?php echo $lang['system_config_832'] ?>
                </div>
        </td>
</tr>

<tr  id="aafScopeTarget-tr" sq_id="aafScopeTarget">
        <td class="cc_label"><?php echo $lang['system_config_556'] ?></td>
        <td class="cc_data">

		 <textarea class='x-form-field notesbox' style='margin-top:3px;height:80px;' name='aafScopeTarget' ><?php echo htmlspecialchars($element_data['aafScopeTarget'], ENT_QUOTES) ?></textarea><br/>
                <div class="cc_info">
                        <?php echo preg_replace('/<code>/', '<code class="a11y_c62c83_f0f0f0">', $lang['system_config_837']) ?>
                </div>
        </td>
</tr>
<tr>
        <td class="cc_label"><?php echo $lang['system_config_838'] ?></td>
        <td class="cc_data">
                <select class="x-form-text x-form-field" style="" name="aafAllowLocalsCreateDB">
                        <option value='off' <?php echo ($element_data['aafAllowLocalsCreateDB'] == 'off') ? "selected" : "" ?>><?php echo $lang['design_99'] ?></option>
                        <option value='on' <?php echo ($element_data['aafAllowLocalsCreateDB'] == 'on') ? "selected" : "" ?>><?php echo $lang['design_100'] ?></option>
                </select><br/>
                <div class="cc_info">
                        <?php echo $lang['system_config_826'] ?>
                </div>
        </td>
</tr>

<tr>
        <td class="cc_label"><?php echo $lang['system_config_839'] ?></td>
        <td class="cc_data">
                <select class="x-form-text x-form-field" style="" name="aafDisplayOnEmailUsers">
                        <option value='off' <?php echo ($element_data['aafDisplayOnEmailUsers'] == 'off') ? "selected" : "" ?>><?php echo $lang['system_config_843'] ?></option>
                        <option value='locals' <?php echo ($element_data['aafDisplayOnEmailUsers'] == 'locals') ? "selected" : "" ?>><?php echo $lang['system_config_844'] ?></option>
                        <option value='on' <?php echo ($element_data['aafDisplayOnEmailUsers'] == 'on') ? "selected" : "" ?>><?php echo $lang['system_config_845'] ?></option>
                </select><br/>
        </td>
</tr>

<tr  id="aafPrimaryField-tr" sq_id="aafPrimaryField">
        <td class="cc_label"><?php echo $lang['system_config_840'] ?></td>
        <td class="cc_data">
                <select class="x-form-text x-form-field" style="" name="aafPrimaryField">
                        <option value='cn' <?php echo ($element_data['aafPrimaryField'] == "cn" ? "selected" : "") ?>>cn</option>
                        <option value='mail' <?php echo ($element_data['aafPrimaryField'] == "mail" ? "selected" : "") ?>>mail</option>
                        <option value='displayname' <?php echo ($element_data['aafPrimaryField'] == "displayname" ? "selected" : "") ?>>displayname</option>
                        <option value='edupersontargetedid' <?php echo ($element_data['aafPrimaryField'] == "edupersontargetedid" ? "selected" : "") ?>>edupersontargetedid</option>
                        <option value='edupersonprincipalname' <?php echo ($element_data['aafPrimaryField'] == "edupersonprincipalname" ? "selected" : "") ?>>edupersonprincipalname</option>
                </select><br/>
                <div class="cc_info">
                        <?php echo $lang['system_config_841'] ?>
                </div>
        </td>
</tr>

<!--***</AAF Modification>****-->



<!-- Additional SAMS Authentication Settings -->
<tr>
	<td colspan="2">
		<hr size=1>
		<h4 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['system_config_303'] ?></h4>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_304'] ?></td>
	<td class="cc_data">
		<input class='x-form-text x-form-field '  type='text' name='sams_logout' value='<?php echo htmlspecialchars($element_data['sams_logout'], ENT_QUOTES) ?>'  /><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_47'] ?>
		</div>
	</td>
</tr>

<!-- Access-Control-Allow-Origin -->
<tr>
	<td colspan="2">
		<hr size=1>
		<h4 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['system_config_543'] ?></h4>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<div style="text-indent:-3em;margin-left:3em;">
			<img src="<?php echo APP_PATH_IMAGES ?>hand_shake.png" style="position:relative;top:4px;"><img src="<?php echo APP_PATH_IMAGES ?>network_ip.png" style="position:relative;top:4px;left:-4px;">
			<?php echo $lang['system_config_544'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_545'] ?>
		</div>
	</td>
	<td class="cc_data" style="padding-bottom:0;">
		<textarea class='x-form-field notesbox' style='margin-top:3px;height:80px;' name='cross_domain_access_control' ><?php echo $element_data['cross_domain_access_control'] ?></textarea><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_546'] ?>
			<div style='font-size:11px;margin:10px 0;'>
				<b><?php echo $lang['edit_project_125'] ?></b><br>
				http://example.com<br>http://www.mysite.edu
			</div>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<div style="text-indent:-3em;margin-left:3em;">
			<img src="<?php echo APP_PATH_IMAGES ?>select.png" style="position:relative;top:4px;"><img src="<?php echo APP_PATH_IMAGES ?>network_ip.png" style="position:relative;top:4px;left:-4px;">
			<?php echo $lang['system_config_573'] ?>
		</div>
	</td>
	<td class="cc_data" style="padding-bottom:0;">
		<select class="x-form-text x-form-field" style="max-width:375px;" name="clickjacking_prevention">
			<option value='0' <?php echo ($element_data['clickjacking_prevention'] == "0" ? "selected" : "") ?>><?php echo $lang['system_config_576'] ?></option>
			<option value='1' <?php echo ($element_data['clickjacking_prevention'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_575'] ?></option>
		</select>
	</td>
</tr>
<tr>
	<td colspan=2 class="cc_label" style="padding-top:0;padding-bottom:20px;">
		<div class="cc_info">
			<?php echo $lang['system_config_574'] ?>
		</div>
	</td>
</tr>

<tr>
    <td class="cc_label">
        <i class="fa-solid fa-ban"></i> <?php echo $lang['docs_1137'] ?>
        <div class="cc_info">
            <?php echo $lang['docs_1138'] ?>
        </div>
    </td>
    <td class="cc_data">
        <textarea class='x-form-field notesbox' id='restricted_upload_file_types' name='restricted_upload_file_types'><?php echo $element_data['restricted_upload_file_types'] ?></textarea><br/>
        <div id='restricted_upload_file_types-expand' style='text-align:right;'>
            <a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#6E6E68;font-family:tahoma;font-size:10px;'
               onclick="growTextarea('restricted_upload_file_types')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
        </div>
        <div class="cc_info mt-0">
            <?php echo "<b>{$lang['system_config_64']}</b><br>exe, js, msi, msp, jar, bat, cmd, com" ?>
        </div>
    </td>
</tr>


</table><br/>
<div style="text-align: center;"><input type='submit' name='' value='<?=js_escape($lang['control_center_4876'])?>' /></div><br/>
</form>

<?php
// Dialog for Twilio setup explanation
print 	RCView::div(array('id'=>'twilio2FAsetupExplain', 'class'=>'simpleDialog', 'title'=>$lang['survey_717']),
			$lang['system_config_377'] . " " .
			RCView::a(array('href'=>'https://www.twilio.com', 'target'=>'_blank', 'style'=>'font-size:13px;text-decoration:underline;'),
				"www.twilio.com"
			) . $lang['period'] . " " .
			$lang['system_config_378'] . RCView::br() . RCView::br() .
			$lang['system_config_379']
		);
// Dialog for Duo setup explanation
print 	RCView::div(array('id'=>'duo2FAsetupExplain', 'class'=>'simpleDialog', 'title'=>$lang['system_config_411']),
			$lang['system_config_417'] . " " .
			RCView::a(array('href'=>'https://admin.duosecurity.com', 'target'=>'_blank', 'style'=>'font-size:13px;text-decoration:underline;'),
				"https://admin.duosecurity.com"
			) . $lang['period'] . " " .
			$lang['system_config_418'] . RCView::br() . RCView::br() .
			$lang['system_config_419']
		);

include 'footer.php';
