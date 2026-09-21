<?php



// Default value regarding if user has already entered their username for an e-signature in this session
$alreadyEnteredUsername = ($auth_meth_global == 'none'); // False for all auth methods except 'none'

if (!$alreadyEnteredUsername)
{
	// Check within a window of 3 hours in the past
	$xHrsAgo = date("Y-m-d H:i:s", mktime(date("H")-3,date("i"),date("s"),date("m"),date("d"),date("Y")));
	// Get last login time during this session (will fail for 'none' and 'shibolleth' authentication)
	$sql = "select ts from redcap_log_view where ts > '$xHrsAgo' and event = 'LOGIN_SUCCESS' 
	        and session_id = '".db_escape(Session::sessionId())."' order by log_view_id desc limit 1";
	$q = db_query($sql);
	$lastLogin = ($q && db_num_rows($q) > 0) ? db_result($q, 0) : 0;
	// Get most recent e-signature during this session. If exists, then don't ask for username for e-signature anymore during this session.
	if ($lastLogin != 0)
	{
		$sql = "select 1 from redcap_esignatures where username = '".db_escape($userid)."' and timestamp > '$lastLogin' order by esign_id desc limit 1";
		$q = db_query($sql);
		$alreadyEnteredUsername = ($q && db_num_rows($q) > 0);
	}
}

// If two_factor_auth_esign_pin is enabled when using 2FA and we're not using LDAP, Table, or LDAP/Table, then display a warning
// only to non-Table users that they can't use the password e-sign option but must use the PIN option only.
list ($canEsignWithPassword, $canEsignWithPIN) = User::canEsignWithPasswordOr2faPin(defined("USERID") ? USERID : "");
$displayMsgCannotEsignWithPassword = ($canEsignWithPIN && !$canEsignWithPassword);
$msgCannotEsignWithPassword = $displayMsgCannotEsignWithPassword ? RCView::div(['class'=>'mt-3 text-dangerrc'], '<i class="fas fa-exclamation-triangle"></i> '.RCView::tt('data_entry_584')) : '';

// Set html for username form field
$esign_username_input = $alreadyEnteredUsername ? " value=\"".htmlspecialchars($userid, ENT_QUOTES)."\" readonly style=\"background:#ddd;\" " : " style=\"\" ";
$bypassEsignWithPIN = ($alreadyEnteredUsername && $canEsignWithPIN && !$canEsignWithPassword && $GLOBALS['two_factor_auth_esign_once_per_session']);
$esign_password_input = $bypassEsignWithPIN ? " value=\"".encrypt(Session::sessionId())."\" readonly style=\"background:#ddd;\" " : "";

?>
<!-- E-signature: username/password -->
<div id="esign_popup" title="E-signature: Username/password verification" style="display:none;">
	<p style="margin-bottom:25px;">
		<?php print $lang['esignature_21'] ?>
	</p>
	<div style="float:left;display:block;margin-left:50px;width:130px;font-weight:bold;"><?php print $lang['global_11'] . $lang['colon'] ?></div>
	<div style="float:left;display:block;">
		<input type="text" id="esign_username" autocomplete="new-password" class="x-form-text x-form-field" <?php echo $esign_username_input ?>>
	</div><br><br>
	<div style="float:left;display:block;margin-left:50px;width:130px;font-weight:bold;"><?php print ($GLOBALS['two_factor_auth_enabled'] && $GLOBALS['two_factor_auth_esign_pin'] ? ($displayMsgCannotEsignWithPassword ? $lang['global_307'] : $lang['global_306']) : $lang['global_240']) ?></div>
	<div style="float:left;display:block;">
		<input id="esign_password" type="password" autocomplete="new-password" class="x-form-text x-form-field" <?php echo $esign_password_input ?>>
	</div><br><br>
	<!-- Hidden error message -->
	<div id="esign_popup_error" class="red" style="display:none;">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
		<?php print $lang['esignature_24'] ?>
	</div>

    <?php if ($GLOBALS['two_factor_auth_enabled'] && $GLOBALS['two_factor_auth_esign_pin'] && !$bypassEsignWithPIN) { ?>
        <div class="mt-3">
            <i class="fas fa-info-circle"></i> <?=RCView::tt($displayMsgCannotEsignWithPassword ? "data_entry_667" : "data_entry_577")?>
            <?php if ($GLOBALS['two_factor_auth_twilio_enabled'] || $GLOBALS['two_factor_auth_email_enabled']) { ?>
                <?=RCView::tt("data_entry_578", 'div', ['class'=>'mb-2'])?>
                <?php if ($GLOBALS['two_factor_auth_email_enabled']) { ?>
                    <button class="btn btn-defaultrc btn-xs ms-3" onclick="sendTFAcode('email',true,1);$('#sent-msg-tfa-code2').removeClass('hide');setTimeout(function(){ $('#sent-msg-tfa-code2').addClass('hide'); },2000);"><i class="fa-solid fa-envelope"></i> <?=RCView::tt("data_entry_668")?></button>
                <?php } ?>
                <?php if ($GLOBALS['two_factor_auth_twilio_enabled']) { ?>
                    <button class="btn btn-defaultrc btn-xs ms-3" <?=($GLOBALS['user_phone_sms'] == "" ? "disabled" : "")?> onclick="sendTFAcode('sms',true,1);$('#sent-msg-tfa-code2').removeClass('hide');setTimeout(function(){ $('#sent-msg-tfa-code2').addClass('hide'); },2000);"><i class="fa-solid fa-comment"></i> <?=RCView::tt("data_entry_669")?></button>
                <?php } ?>
                <span id="sent-msg-tfa-code2" class="hide text-secondary ms-2"><img src="<?=APP_PATH_IMAGES."progress_circle.gif"?>"> <?=RCView::tt("system_config_439")?></span>
            <?php } ?>
            <?=$msgCannotEsignWithPassword?>
        </div>
    <?php } elseif ($bypassEsignWithPIN) { ?>
        <div class="mt-3">
            <i class="fas fa-info-circle"></i> <?=RCView::tt("data_entry_684")?>
        </div>
    <?php } ?>
</div>