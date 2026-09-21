// Set var
var current_tf_id = null;
$(function(){
	// Display popup
	display2FALoginDialog();
	// Set row highlighting aesthetics
	$('table#two_factor_choices_table tr td').click(function(){
		if ($('input[type="radio"]', this).css('visibility') == 'visible') {
			$('table#two_factor_choices_table tr td').each(function(){
				if ($('input[type="radio"]', this).css('visibility') == 'visible') {
					$(this).css({'background':'#eee'});
				}
			});
			$(this).css({'background':'#d9ebf5'});
			$('input[type="radio"]', this).prop('checked', true);
		}
	});
	$('table#two_factor_choices_table tr td').mouseenter(function(){
		if ($('input[type="radio"]', this).css('visibility') == 'visible') {
			$(this).css({'background':'#d9ebf5'});
		}
	}).mouseleave(function(){
		if (!$('input[type="radio"]', this).prop('checked') && $('input[type="radio"]', this).css('visibility') == 'visible') {
			$(this).css({'background':'#eee'});
		}
	});
});
// Display 2FA login dialog
function display2FALoginDialog() {
	$('#two_factor_option_progress_email, #two_factor_option_success_email, #two_factor_option_fail_email, #two_factor_option_progress_sms, '
	 +'#two_factor_option_success_sms, #two_factor_option_fail_sms, #two_factor_option_progress_login, #two_factor_option_success_login').hide();
	$('#two_factor_login_dialog').dialog({ bgiframe: true, modal: true, width: (isMobileDevice ? $(window).width() : 690), open:function(){ if (!isMobileDevice) fitDialog(this);},
		close:function(){ window.location.href = this_page_url+(this_page_url.indexOf('?') < 0 ? '?' : '&')+'logout=1'; },
		title: '<img src="'+app_path_images+'lock.png" style="vertical-align:middle;margin-right:5px;"><span style="color:#A86700;font-size:15px;vertical-align:middle;">'
			 + lang2FA_02 + '</span>',
		buttons: [{
			text: window.lang.global_53,
			click: function() { $(this).dialog("close"); }
		}]
	});
}
function selectTFStep1(option, data) {
	const rememberMeChecked = function() {
		const twoFactorAuthTrustCheckbox = document.querySelector('input[name="two_factor_auth_trust"]');
		if(!twoFactorAuthTrustCheckbox) return 0;
  		return twoFactorAuthTrustCheckbox.checked ? 1 : 0;
	}
	if (typeof data == 'undefined') data = {};
	$('input[name="two_factor_option"][value="'+option+'"]').prop('checked', true);
	

	// For Duo, open Duo popup iframe
	if (option == 'duo') {
		const args = {
			type: 'duo',
			state: data.state || '',
			'remember-me': rememberMeChecked(),
		}
		const url = new URL(app_path_webroot_full + 'redcap_v' + redcap_version+'/twoFA');
		url.search = new URLSearchParams(args);
		location.href = url
	}
	// For verification code based options
	else {
		if (option == 'voice') {
			$('#tf_verify_step_sms, #tf_verify_step_ga, #tf_verify_step_email, #tf_verify_step_ga_setup_instr').hide();
			$('#tf_verify_step_voice').show().dialog({ bgiframe: true, modal: true, width: (isMobileDevice ? $(window).width() : 450), close:function(){ current_tf_id = null; },
				buttons: [{
					text: window.lang.global_53,
					click: function() { $(this).dialog("close"); }
				}] });
			return;
		} else if (option == 'sms') {
			$('#tf_verify_step_voice, #tf_verify_step_ga, #tf_verify_step_email, #tf_verify_step_ga_setup_instr').hide();
			$('#tf_verify_step_sms').show();
		} else if (option == 'authenticator') {
			$('#tf_verify_step_voice, #tf_verify_step_sms, #tf_verify_step_email').hide();
			$('#tf_verify_step_ga, #tf_verify_step_ga_setup_instr').show();
		} else {
			$('#tf_verify_step_voice, #tf_verify_step_sms, #tf_verify_step_ga, #tf_verify_step_ga_setup_instr').hide();
			$('#tf_verify_step_email').show();
		}
		$('#tf_verify_step').dialog({ bgiframe: true, modal: true, width: (isMobileDevice ? $(window).width() : 500), close:function(){ current_tf_id = null; } });
		$('#two_factor_verification_code').focus();
	}
}

// SMS Only: Set constant checking of tf_id to see if it's verified (i.e., if user has responded to SMS)
function check_login_status(tf_id) {
	if (tf_id != current_tf_id) return;
	// Check if trust cookie radio is checked off
	var two_factor_auth_trust = ($('input[name="two_factor_auth_trust"]').length && $('input[name="two_factor_auth_trust"]').prop('checked')) ? 1 : 0;
	// AJAX
	$.post(app_path_webroot+'Authentication/two_factor_check_login_status.php', { tf_id: tf_id, two_factor_auth_trust: two_factor_auth_trust, twoFactorMethod: $('input[name="two_factor_option"]:checked').val() }, function(data) {
		if (data == '1') {
			// Verified, so let the user in (simply redirect to current page)
			two_factor_login_success();
		} else {
			// Wait a second, and then call this function again
			setTimeout('check_login_status('+tf_id+')', 2000);
		}
	});
}


// Verify the Two Factor Auth code submitted by user
function verify2FAcode(code) {
	// Trim it and check if code is blank
	code = trim(code);
	var errmsg = "<div class='hang' style='font-size:14px;color:#C00000;'><img src='"+app_path_images+"exclamation.png'>&nbsp; "+lang2FA_01+"</div>";
	if (code == '') {
		simpleDialog(errmsg,lang2FA_03,null,400,"$('#two_factor_verification_code').val('').effect('highlight',{ },3000).focus();");
		return;
	}
	// Check if trust cookie radio is checked off
	var two_factor_auth_trust = ($('input[name="two_factor_auth_trust"]').length && $('input[name="two_factor_auth_trust"]').prop('checked')) ? 1 : 0;
	// Verify code via AJAX
	$('#two_factor_option_progress_login').show();
	$.post(app_path_webroot+"Authentication/two_factor_verify_code.php",{ code: code, two_factor_auth_trust: two_factor_auth_trust, twoFactorMethod: $('input[name="two_factor_option"]:checked').val() },function(data) {
		if (data == '1') {
			// Successful login
			two_factor_login_success();
		} else if (data == '2') {
			// Hit rate limit of X submissions per minute
			$('#two_factor_option_progress_login').hide();
			simpleDialog(lang2FA_04,null,null,400,"$('#two_factor_verification_code').val('').effect('highlight',{ },3000).focus();");
		} else {
			// Invalid code
			$('#two_factor_option_progress_login').hide();
			simpleDialog(errmsg,lang2FA_03,null,400,"$('#two_factor_verification_code').val('').effect('highlight',{ },3000).focus();");
		}
	});
}
// Successful login, so show success and reload page
function two_factor_login_success() {
	$('#two_factor_verification_code').prop('disabled', true);
	$('#two_factor_verification_code_btn').button('disable');
	$('#two_factor_option_progress_login, #two_factor_verification_code_cancel').hide();
	$('#two_factor_option_success_login, #two_factor_option_success_login_voice').show();
	$('#two_factor_option_success_login_voice_text img').css('visibility','hidden');
	$('#two_factor_option_success_login_voice_text').fadeTo(0,0.5);
	setTimeout(function(){
		window.location.href = this_page_url;
	},1500);
}