<?php

use Vanderbilt\REDCap\Classes\AccountExpirationNotifier\AccountExpirationNotifier;

include 'header.php';
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);
if (!ACCESS_SYSTEM_CONFIG) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); });</script>";

$changesSaved = false;

// If project default values were changed, update redcap_config table with new values
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ACCESS_SYSTEM_CONFIG)
{
	$changes_log = array();
	$sql_all = array();
	// Change checkbox "on" value to 0 or 1
	$_POST['auto_prod_changes_check_identifiers'] = (isset($_POST['auto_prod_changes_check_identifiers']) && $_POST['auto_prod_changes_check_identifiers'] == 'on') ? '1' : '0';
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

<h4 style="margin-top: 0;"><i class="fas fa-user-cog"></i> <?php echo $lang['system_config_156'] ?></h4>

<form enctype='multipart/form-data' target='_self' method='post' name='form' id='form' onSubmit="return validateEmailDomainAllowlist();">
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".System::getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0;">

<tr>
	<td colspan="2">
		<h4 style="margin-top:5px;font-size:14px;padding:10px;color:#800000;"><?php echo $lang['system_config_654'] ?></h4>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['system_config_12'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-width:98%;" name="superusers_only_create_project">
			<option value='0' <?php echo ($element_data['superusers_only_create_project'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_15'] ?></option>
			<option value='1' <?php echo ($element_data['superusers_only_create_project'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_14'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_13'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_16'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-width:98%;" name="superusers_only_move_to_prod">
			<option value='0' <?php echo ($element_data['superusers_only_move_to_prod'] == '0') ? "selected" : "" ?>><?php echo $lang['system_config_146'] ?></option>
			<option value='1' <?php echo ($element_data['superusers_only_move_to_prod'] == '1') ? "selected" : "" ?>><?php echo $lang['system_config_18'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_17'] ?>
		</div>
	</td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['system_config_747'] ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="max-width:98%;" name="new_form_default_prod_user_access">
            <option value='0' <?php echo ($element_data['new_form_default_prod_user_access'] == '0') ? "selected" : "" ?>><?php echo $lang['rights_47'] ?></option>
            <option value='2' <?php echo ($element_data['new_form_default_prod_user_access'] == '2') ? "selected" : "" ?>><?php echo $lang['rights_138'] . " / " . $lang['rights_48'] ?></option>
            <option value='1' <?php echo ($element_data['new_form_default_prod_user_access'] == '1') ? "selected" : "" ?>><?php echo $lang['rights_138'] . " / " . $lang['rights_49'] ?></option>
        </select><br/>
        <div class="cc_info">
            <?php echo $lang['system_config_748'] ?>
        </div>
    </td>
</tr>

<tr>
    <td colspan="2">
        <h4 style="margin-top:5px;border-top:1px solid #ddd;font-size:14px;padding:10px;color:#800000;"><i class="fa-regular fa-envelope"></i> <?php echo $lang['system_config_796'] ?></h4>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_790'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="admin_email_external_user_creation">
            <option value='0' <?php echo ($element_data['admin_email_external_user_creation'] == "0" ? "selected" : "") ?>><?php echo $lang['design_99'] ?></option>
            <option value='1' <?php echo ($element_data['admin_email_external_user_creation'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_792'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['system_config_791'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_798'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="user_welcome_email_external_user_creation">
            <option value='0' <?php echo ($element_data['user_welcome_email_external_user_creation'] == "0" ? "selected" : "") ?>><?php echo $lang['design_99'] ?></option>
            <option value='1' <?php echo ($element_data['user_welcome_email_external_user_creation'] == "1" ? "selected" : "") ?>><?php echo $lang['system_config_797'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['system_config_799'] . RCView::div(['style'=>'color:#a00000;margin-top:3px;padding:3px 5px;border:1px solid #ddd;'], User::getTextNewUserWelcomeEmail("USERNAME")) ?>
        </div>
    </td>
</tr>

<tr>
    <td colspan="2" class="cc_info px-2 pt-1 pb-1 fs12" style="color:#800000;">
        <?php echo RCView::div(array('class'=>'pt-2', 'style'=>'border-top:1px solid #ddd;'), RCView::span(array('class'=>'fs13 font-weight-bold'), $lang['control_center_4768']).RCView::br().$lang['control_center_4769']) ?>
        <?php echo RCView::div(array('class'=>'mt-2', 'style'=>'color:#555;'), " <i class=\"far fa-lightbulb\"></i> ". preg_replace('/<code>/', '<code class="a11y_c62c83_f0f0f0">', $lang['control_center_4767'])) ?>
    </td>
</tr>
<tr>
    <td class="cc_data">
        <?php echo $lang['control_center_4770'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field survey_pid'  type='text' name='survey_pid_create_project' value='<?php echo htmlspecialchars($element_data['survey_pid_create_project'], ENT_QUOTES) ?>'
               onblur="redcap_validate(this,'1','999999','hard','int')" style="width:56px;vertical-align:middle;" />
        <span class="cc_info ms-2"><?php echo $lang['control_center_4726'] ?></span>
    </td>
</tr>
<tr>
    <td class="cc_data">
        <?php echo $lang['control_center_4728'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field survey_pid'  type='text' name='survey_pid_move_to_prod_status' value='<?php echo htmlspecialchars($element_data['survey_pid_move_to_prod_status'], ENT_QUOTES) ?>'
               onblur="redcap_validate(this,'1','999999','hard','int')" style="width:56px;vertical-align:middle;" />
        <span class="cc_info ms-2"><?php echo $lang['control_center_4726'] ?></span>
    </td>
</tr>
<tr>
    <td class="cc_data">
        <?php echo $lang['control_center_4729'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field survey_pid'  type='text' name='survey_pid_move_to_analysis_status' value='<?php echo htmlspecialchars($element_data['survey_pid_move_to_analysis_status'], ENT_QUOTES) ?>'
               onblur="redcap_validate(this,'1','999999','hard','int')" style="width:56px;vertical-align:middle;" />
        <span class="cc_info ms-2"><?php echo $lang['control_center_4726'] ?></span>
    </td>
</tr>
<tr>
    <td class="cc_data">
        <?php echo $lang['control_center_4730'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field survey_pid'  type='text' name='survey_pid_mark_completed' value='<?php echo htmlspecialchars($element_data['survey_pid_mark_completed'], ENT_QUOTES) ?>'
               onblur="redcap_validate(this,'1','999999','hard','int')" style="width:56px;vertical-align:middle;" />
        <span class="cc_info ms-2"><?php echo $lang['control_center_4726'] ?></span>
    </td>
</tr>

<tr>
    <td colspan="2">
        <hr size=1>
        <h4 style="font-size:14px;padding:10px;color:#800000;"><?php echo $lang['system_config_292'] ?></h4>
    </td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['api_141'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-width:375px;" name="api_token_request_type">
			<option value='admin_approve' <?php echo ($element_data['api_token_request_type'] == 'admin_approve') ? "selected" : "" ?>><?php echo $lang['api_143'] ?></option>
			<option value='auto_approve_selected' <?php echo ($element_data['api_token_request_type'] == 'auto_approve_selected') ? "selected" : "" ?>><?php echo $lang['api_145'] ?></option>
			<option value='auto_approve_all' <?php echo ($element_data['api_token_request_type'] == 'auto_approve_all') ? "selected" : "" ?>><?php echo $lang['api_144'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['api_142'] ?>
		</div>
	</td>
</tr>

<!-- Set time of inactivity after which users get auto-suspended -->
<tr>
	<td class="cc_label"><?php echo $lang['control_center_4391'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-max-width:375px;" name="suspend_users_inactive_type">
			<option value='' <?php echo ($element_data['suspend_users_inactive_type'] == '') ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<?php if (!($auth_meth_global == "table" || strpos($auth_meth_global, "table") === false)) { ?><option value='table' <?php echo ($element_data['suspend_users_inactive_type'] == 'table') ? "selected" : "" ?>><?php echo $lang['control_center_4389'] ?></option><?php } ?>
			<option value='all' <?php echo ($element_data['suspend_users_inactive_type'] == 'all') ? "selected" : "" ?>><?php echo $lang['control_center_4390'] ?></option>
		</select>
		<div style="padding-top:5px;">
			<span style="font-weight:bold;vertical-align:middle;margin-right:5px;"><?php echo $lang['control_center_4393'] ?></span>
			<input class='x-form-text x-form-field '  type='text' name='suspend_users_inactive_days' value='<?php echo htmlspecialchars($element_data['suspend_users_inactive_days'], ENT_QUOTES) ?>'
				onblur="redcap_validate(this,'1','','hard','int')" style="width:36px;vertical-align:middle;" />
			<span style="vertical-align:middle;"><?php echo $lang['define_events_31'] ?></span>
		</div>
		<div style="padding-bottom:5px;">
			<span style="font-weight:bold;vertical-align:middle;margin-right:5px;"><?php echo $lang['control_center_4422'] ?></span>
			<select class="x-form-text x-form-field" style="" name="suspend_users_inactive_send_email">
				<option value='0' <?php echo ($element_data['suspend_users_inactive_send_email'] == '0') ? "selected" : "" ?>><?php echo $lang['design_99'] ?></option>
				<option value='1' <?php echo ($element_data['suspend_users_inactive_send_email'] == '1') ? "selected" : "" ?>><?php echo $lang['design_100'] ?></option>
			</select>
		</div>
		<div class="cc_info">
			<?php echo $lang['control_center_4423'] . " " . $lang['control_center_4425'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['global_02'].$lang['colon']." ".$lang['control_center_4394'] ?>
		</div>
	</td>
</tr>

<!-- User SPONSOR Dashboard settings -->
<tr>
	<td class="cc_label">
		<?php echo $lang['rights_330'] ?>
		<div class="cc_info">
			<?php echo $lang['rights_343'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="user_sponsor_dashboard_enable">
			<option value='0' <?php echo ($element_data['user_sponsor_dashboard_enable'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['user_sponsor_dashboard_enable'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div style="padding-top:5px;">
			<div style="font-weight:bold;vertical-align:middle;margin-right:5px;"><?php echo $lang['rights_345'] ?></div>
			<input class='x-form-text x-form-field '  type='text' name='user_sponsor_set_expiration_days' value='<?php echo htmlspecialchars($element_data['user_sponsor_set_expiration_days'], ENT_QUOTES) ?>'
				onblur="redcap_validate(this,'1','','hard','int')" style="width:36px;vertical-align:middle;" />
			<span style="vertical-align:middle;"><?php echo $lang['define_events_31'] ?></span>
		</div>
		<div class="cc_info">
			<?php echo $lang['rights_346'] ?>
		</div>
	</td>
</tr>

<!-- User Access Dashboard settings -->
<tr id="tr-user_access_dashboard_enable">
	<td class="cc_label">
		<?php echo $lang['rights_226'] ?>
		<div class="cc_info">
			<?php echo $lang['rights_245'] ?>
		</div>
		<div class="cc_info" style="margin-top:15px;">
			<?php echo $lang['rights_254'] ?>
		</div>
		<div class="cc_info" style="margin-top:15px;">
			<?php echo RCView::b($lang['rights_261'])."<br>".$lang['rights_262']." ".
				RCView::span(array('style'=>'color:#800000;'), "\"".$lang['rights_242']."\"") ?>
		</div>
	</td>
	<td class="cc_data">
		<div style="margin:0 0 1px;"><?php echo $lang['rights_260'] ?></div>
		<select class="x-form-text x-form-field" style="max-width:375px;" name="user_access_dashboard_enable">
			<option value='0' <?php echo ($element_data['user_access_dashboard_enable'] == '0') ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['user_access_dashboard_enable'] == '1') ? "selected" : "" ?>><?php echo $lang['rights_246'] ?></option>
			<option value='2' <?php echo ($element_data['user_access_dashboard_enable'] == '2') ? "selected" : "" ?>><?php echo $lang['rights_247'] ?></option>
			<option value='3' <?php echo ($element_data['user_access_dashboard_enable'] == '3') ? "selected" : "" ?>><?php echo $lang['rights_248'] ?></option>
		</select>
		<div>
			<div style="margin:12px 0 1px;"><?php echo $lang['rights_259'] ?></div>
			<textarea class='x-form-field notesbox' style='height:50px;' id='user_access_dashboard_custom_notification' name='user_access_dashboard_custom_notification'><?php echo $element_data['user_access_dashboard_custom_notification'] ?></textarea><br/>
			<div id='user_access_dashboard_custom_notification-expand' style='text-align:right;'>
				<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#6E6E68;font-family:tahoma;font-size:10px;'
					onclick="growTextarea('user_access_dashboard_custom_notification')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
			</div>
		</div>
		<div class="cc_info" style="margin:0;">
			<b><?php echo $lang['rights_249'] ?></b><br>
			<u><?php echo $lang['global_23'] ?></u> - <?php echo $lang['rights_250'] ?><br>
			<u><?php echo $lang['rights_246'] ?></u> - <?php echo $lang['rights_251'] ?><br>
			<u><?php echo $lang['rights_247'] ?></u> - <?php echo $lang['rights_252'] ?><br>
			<u><?php echo $lang['rights_248'] ?></u> - <?php echo $lang['rights_253'] ?>
		</div>
	</td>
</tr>

<!-- Allow users to edit survey responses -->
<tr>
	<td class="cc_label"><?php echo $lang['system_config_185'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_edit_survey_response">
			<option value='0' <?php echo ($element_data['enable_edit_survey_response'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_edit_survey_response'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_186'] ?>
		</div>
	</td>
</tr>

<!-- Auto production changes -->
<tr id="tr-auto_prod_changes">
	<td class="cc_label">
		<?php echo $lang['system_config_198'] ?>
		<div class="cc_info" style="font-weight:normal;">
			<?php echo $lang['system_config_199'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-width:375px;" name="auto_prod_changes">
			<option value='0' <?php echo ($element_data['auto_prod_changes'] == '0') ? "selected" : "" ?>><?php echo $lang['system_config_200'] ?></option>
			<option value='2' <?php echo ($element_data['auto_prod_changes'] == '2') ? "selected" : "" ?>><?php echo $lang['system_config_201'] ?></option>
			<option value='3' <?php echo ($element_data['auto_prod_changes'] == '3') ? "selected" : "" ?>><?php echo $lang['system_config_203'] ?></option>
			<option value='4' <?php echo ($element_data['auto_prod_changes'] == '4') ? "selected" : "" ?>><?php echo $lang['system_config_204'] ?></option>
			<option value='1' <?php echo ($element_data['auto_prod_changes'] == '1') ? "selected" : "" ?>><?php echo $lang['system_config_202'] ?></option>
		</select>
		<div  style="text-indent: -1.3em;margin-left: 1.5em;line-height: 14px;margin-bottom: 15px;font-size: 12px;">
			<input type="checkbox" style="position:relative;top:3px;" name="auto_prod_changes_check_identifiers" <?php if ($element_data['auto_prod_changes_check_identifiers'] == '1') print "checked"; ?>>
			<?php echo $lang['system_config_551'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_205'] ?>
		</div>
		<div class="cc_info">
			<?php echo "<b style='font-size:12px;'>{$lang['system_config_220']}</b><br>{$lang['system_config_221']}" ?>
		</div>
	</td>
</tr>

<!-- Allow users to modify repeating instances setup while in production -->
<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_578'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-width:375px;" name="enable_edit_prod_repeating_setup">
			<option value='0' <?php echo ($element_data['enable_edit_prod_repeating_setup'] == '0') ? "selected" : "" ?>><?php echo $lang['system_config_580'] ?></option>
			<option value='1' <?php echo ($element_data['enable_edit_prod_repeating_setup'] == '1') ? "selected" : "" ?>><?php echo $lang['system_config_581'] ?></option>
		</select>
	</td>
</tr>

<!-- Add/edit events while in production -->
<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_190'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-width:375px;" name="enable_edit_prod_events">
			<option value='0' <?php echo ($element_data['enable_edit_prod_events'] == '0') ? "selected" : "" ?>><?php echo $lang['system_config_192'] ?></option>
			<option value='1' <?php echo ($element_data['enable_edit_prod_events'] == '1') ? "selected" : "" ?>><?php echo $lang['system_config_193'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_191']." ".$lang['system_config_653'] ?>
		</div>
	</td>
</tr>

<!-- Domain allowlist for user email addresses -->
<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_232'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_233'] ?>
		</div>
	</td>
	<td class="cc_data">
		<textarea class='x-form-field notesbox' id='email_domain_allowlist' name='email_domain_allowlist'><?php echo $element_data['email_domain_allowlist'] ?></textarea><br/>
		<div id='email_domain_allowlist-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#6E6E68;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('email_domain_allowlist')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
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
	<td class="cc_label"><?php echo $lang['system_config_103'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="my_profile_enable_edit">
			<option value='0' <?php echo ($element_data['my_profile_enable_edit'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_105'] ?></option>
			<option value='1' <?php echo ($element_data['my_profile_enable_edit'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_106'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_104'] ?>
		</div>
	</td>
</tr>

<tr>
    <td class="cc_label"><?php echo $lang['system_config_726'] ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="my_profile_enable_primary_email_edit">
            <option value='0' <?php echo ($element_data['my_profile_enable_primary_email_edit'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_105'] ?></option>
            <option value='1' <?php echo ($element_data['my_profile_enable_primary_email_edit'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_106'] ?></option>
        </select><br/>
        <div class="cc_info">
            <?php echo $lang['system_config_727'] ?>
        </div>
    </td>
</tr>

<tr>
    <td class="cc_label"><?php echo $lang['system_config_851'] ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="allow_auto_variable_naming">
            <option value='0' <?php echo ($element_data['allow_auto_variable_naming'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_852'] ?></option>
            <option value='1' <?php echo ($element_data['allow_auto_variable_naming'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_853'] ?></option>
            <option value='2' <?php echo ($element_data['allow_auto_variable_naming'] == 2) ? "selected" : "" ?>><?php echo $lang['system_config_854'] ?></option>
        </select>
    </td>
</tr>

<!-- Default settings for new users -->
<tr>
	<td colspan="2">
		<hr size=1>
		<h4 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['system_config_291'] ?></h4>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['system_config_163'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="font-family:tahoma;" name="allow_create_db_default">
			<option value='0' <?php echo ($element_data['allow_create_db_default'] == 0) ? "selected" : "" ?>><?php echo $lang['design_99'] ?></option>
			<option value='1' <?php echo ($element_data['allow_create_db_default'] == 1) ? "selected" : "" ?>><?php echo $lang['design_100'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['system_config_142'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['user_82'] ?></td>
	<td class="cc_data">
		<?php echo RCView::select(array('name'=>'default_datetime_format', 'class'=>'x-form-text x-form-field', 'style'=>'font-family:tahoma;'),
					DateTimeRC::getDatetimeDisplayFormatOptions(), $element_data['default_datetime_format']) ?>
		<div style='color:#800000;font-size:11px;padding-top:3px;'>(e.g., 12/31/2004 22:57 or 31/12/2004 10:57pm)</div>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['user_83'] ?></td>
	<td class="cc_data">
		<?php echo RCView::select(array('name'=>'default_number_format_decimal', 'class'=>'x-form-text x-form-field', 'style'=>'font-family:tahoma;'),
					User::getNumberDecimalFormatOptions(), $element_data['default_number_format_decimal']) ?>
		<div style='color:#800000;font-size:11px;padding-top:3px;'>(e.g., 3.14 or 3,14)</div>
	</td>
</tr>

<tr>
	<td class="cc_label"><?php echo $lang['user_84'] ?></td>
	<td class="cc_data">
		<?php echo RCView::select(array('name'=>'default_number_format_thousands_sep', 'class'=>'x-form-text x-form-field', 'style'=>'font-family:tahoma;'),
					User::getNumberThousandsSeparatorOptions(), $element_data['default_number_format_thousands_sep']) ?>
		<div style='color:#800000;font-size:11px;padding-top:3px;'>(e.g., 1,000,000 or 1.000.000 or 1 000 000)</div>
	</td>
</tr>

    <tr>
        <td class="cc_label"><?php echo $lang['user_109'] ?></td>
        <td class="cc_data">
			<?php echo RCView::select(array('name'=>'default_csv_delimiter', 'class'=>'x-form-text x-form-field', 'style'=>'font-family:tahoma;'),
				User::getCsvDelimiterOptions(), $element_data['default_csv_delimiter']) ?>
            <div style='color:#800000;font-size:11px;padding-top:3px;'>(e.g., "record,age,bmi" or "record;age;bmi")</div>
        </td>
    </tr>

<!-- Project Dashboards -->
<tr>
    <td colspan="2">
        <hr size=1>
        <h4 style="font-size:14px;padding:0 10px;color:#800000;"><?php echo $lang['dash_115'] ?></h4>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['dash_116'] ?>
        <div class="cc_info">
            <?php echo $lang['dash_117'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="max-width: 370px;" name="reports_allow_public">
            <option value='0' <?php echo ($element_data['reports_allow_public'] == 0) ? "selected" : "" ?>><?php echo $lang['dash_119'] ?></option>
            <option value='1' <?php echo ($element_data['reports_allow_public'] == 1) ? "selected" : "" ?>><?php echo $lang['dash_120'] ?></option>
            <option value='2' <?php echo ($element_data['reports_allow_public'] == 2) ? "selected" : "" ?>><?php echo $lang['dash_62'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['dash_118'] ?>
            <ol style="margin-block-start:0.5em;padding-inline-start:25px;">
                <li><?php echo $lang['dash_121'] ?></li>
                <li><?php echo $lang['dash_122'] ?></li>
                <li><?php echo $lang['dash_123'] ?></li>
            </ol>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['dash_63'] ?>
        <div class="cc_info">
            <?php echo $lang['dash_65'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="max-width: 370px;" name="project_dashboard_allow_public">
            <option value='0' <?php echo ($element_data['project_dashboard_allow_public'] == 0) ? "selected" : "" ?>><?php echo $lang['dash_61'] ?></option>
            <option value='1' <?php echo ($element_data['project_dashboard_allow_public'] == 1) ? "selected" : "" ?>><?php echo $lang['dash_60'] ?></option>
            <option value='2' <?php echo ($element_data['project_dashboard_allow_public'] == 2) ? "selected" : "" ?>><?php echo $lang['dash_62'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['dash_64'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['dash_124'] ?>
        <div class="cc_info">
			<?php echo $lang['dash_67'] ?>
        </div>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style="width:80px;" type='text' name='project_dashboard_min_data_points' value='<?php echo htmlspecialchars($element_data['project_dashboard_min_data_points'], ENT_QUOTES) ?>' onblur="redcap_validate(this,'0','999999999','soft_typed','int')" />
        <div class="cc_info">
			<?php echo $lang['dash_114'] ?>
        </div>
    </td>
</tr>
<!-- account expiration custom text -->
<tr>
    <td colspan="2">
		<div style="padding:0 10px">
			<hr size="1">
			<h4 style="font-size:14px;color:#800000;"><?= $lang['dash_131'] ?></h4>
			<div class="mb-3">
				<?= $lang['dash_126'] ?>
			</div>
		</div>
    </td>
</tr>
<tr>

	<td class="cc_label pt-2" colspan="2">
		<?= RCView::fa('fa-regular fa-envelope')." ".RCView::tt('dash_127') ?>
		<div class="cc_info mb-3">
			<?= RCView::tt('dash_128') ?>
		</div>
		<textarea id='custom-text-user' class='x-form-field notesbox mceEditor' name='user_custom_expiration_message' style='height:250px;'><?= $element_data['user_custom_expiration_message'] ?></textarea>
		<div class="d-flex gap-2 mt-2">
			<div id="userExpirationDynamicVariables"></div>
			<button type="button" id="preview-expiration-user" class="btn btn-sm btn-secondary"
				data-target-text="#custom-text-user"
				data-template-strategy="preview-expiration-user"
			>Preview...</button>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label pt-4" colspan="2">
		<?= RCView::fa('fa-regular fa-envelope')." ".RCView::tt('dash_129') ?>
		<div class="cc_info mb-3">
			<?= RCView::tt('dash_130') ?>
		</div>
		<textarea id='custom-text-user-sponsor' class='x-form-field notesbox mceEditor' name='user_with_sponsor_custom_expiration_message' style='height:250px;'><?= $element_data['user_with_sponsor_custom_expiration_message'] ?></textarea>
		<div class="d-flex gap-2 mt-2">
			<div id="userSponsorExpirationDynamicVariables"></div>
			<button type="button" id="preview-expiration-sponsor" class="btn btn-sm btn-secondary"
				data-target-text="#custom-text-user-sponsor"
				data-template-strategy="preview-expiration-sponsor"
			>Preview...</button>
		</div>
	</td>
</tr>

</table><br/><br/>
<div style="text-align: center;"><input type='submit' value='<?=js_escape($lang['control_center_4876'])?>'/></div><br/>
</form>

<div class="modal fade" tabindex="-1" id="userExpirationModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <span></span>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
// Validate the domain names submitted for the email_domain_allowlist field
function validateEmailDomainAllowlist() {
	// First, trim the value
	$('#email_domain_allowlist').val( trim($('#email_domain_allowlist').val()));
	// If it's blank, then ignore and just submit the form
	var domainAllowlist = $('#email_domain_allowlist').val();
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
		simpleDialog('<?php echo js_escape($lang['system_config_235']) ?><br><br><?php echo js_escape($lang['system_config_236']) ?><br><b>'+failedDomains.join('<br>')+'</b>','<?php echo js_escape($lang['global_01']) ?>',null,null,"$('#email_domain_allowlist').focus();");
		return false;
	}
	// Set field's value with new cleaned value (trimmed and removed blank lines)
	$('#email_domain_allowlist').val( passedDomains.join("\n") );
	return true;
}
$(function(){
   $(':input.survey_pid').blur(function(){
       var val = $(this).val().trim();
       var name = $(this).attr('name');
       if (val == '' || !isNumeric(val)) return;
       // If more than one of these Custom Public Survey fields have the same value, then give warning
       var inputsSameValue = 0;
       $(':input.survey_pid').each(function(){
           if ($(this).val() == val) inputsSameValue++;
       })
       if (inputsSameValue > 1) {
           simpleDialog('<?php echo js_escape($lang['control_center_4732']) ?>', '<?php echo js_escape($lang['global_01']) ?>', 'survey-pid-dialog', 500, "$(':input[name="+name+"]').focus();", '<?php echo js_escape($lang['bottom_90']) ?>');
       }
   });
});
</script>

<style>
	@import url('<?= APP_PATH_JS ?>CustomDropdown/style.css');
</style>
<script type="module">
	import {CustomDropdown} from '<?= getJSpath('CustomDropdown/index.es.js') ?>'

	async function getPreview(strategy, text) {
		const params = {
			route: 'ControlCenterController:getTemplatePreview',
			redcap_csrf_token: window.redcap_csrf_token ?? '',
			strategy: strategy,
		}
		const baseURL = window.app_path_webroot
		const searchParams = new URLSearchParams()
		for (const [key, value] of Object.entries(params)) {
			searchParams.set(key, value)
		}
		const fullURL = `${baseURL}?${searchParams}`
		const response = await fetch(fullURL, {
			"headers": {
				"accept": "application/json, text/plain, */*",
				"x-requested-with": "XMLHttpRequest"
			},
			"method": "POST",
			"mode": "cors",
			body: JSON.stringify({
				text: text
			})
		})
		const data = await response.json()
		return data
	}

	function registerPreviewClickEvent(element) {
		element.addEventListener('click', async function() {
			const textSelector = this.getAttribute('data-target-text')
			const text = document.querySelector(textSelector)
			const strategy = this.getAttribute('data-template-strategy')
			const data = await getPreview(strategy, text.value ?? '')
			const modalElement = document.getElementById('userExpirationModal')
			const modal = new bootstrap.Modal(modalElement)
			modalElement.querySelector('.modal-body > span').innerHTML = data.preview ?? ''
			modal.show()
		})
	}

	const previewExpirationUserButton = document.getElementById('preview-expiration-user')
	const previewExpirationSponsorButton = document.getElementById('preview-expiration-sponsor')
	registerPreviewClickEvent(previewExpirationUserButton)
	registerPreviewClickEvent(previewExpirationSponsorButton)
	

	/**
	 * get a reference by id to a tinyMCE editor
	 */
	const getTinyMceEditor = (id) => {
		try {
			if(id) return window.tinyMCE.get(id)
			const tinyMceEditor = window.tinymce.activeEditor
			return tinyMceEditor
		} catch (error) {
			console.log(error)
		}
	}

	/**
	 * insert text in a tinyMCE editor 
	 */
	function onVariableSelected(editor, variable) {
		if (!editor) return
		const normalizedVariable = `[${variable}]`
		editor.insertContent(normalizedVariable)
		const updatedMessage = editor.getContent()
	}

	// Example usage of the function
	const menuData = <?= json_encode(AccountExpirationNotifier::getGroupedPlaceholders(), JSON_PRETTY_PRINT) ?>

	/**
	 * generate a click listener that will interface with
	 * a specific tinyMCE editor
	 */
	const useDynamicVariablesMenu = (id) => {
		const editor = getTinyMceEditor(id)
		return (menuItem) =>{

			const {value = ''} = menuItem
			onVariableSelected(editor, value)
			let parent = menuItem.parent
			while(parent) {
				if(!('close' in parent)) break
				parent.close()
				parent = parent.parent
			}
		}
	}

	/**
	 * init the dropdown menus with the dynamic variables
	 */
	setTimeout(() => {
		// wait for tinymce to be ready
		const userEmailTemplateVariablesClickListener = useDynamicVariablesMenu('custom-text-user')
		const targetUser = document.getElementById('userExpirationDynamicVariables')
		const dropdown1 = new CustomDropdown(targetUser, menuData, userEmailTemplateVariablesClickListener, "Dynamic Variables")
		/* dropdown1.target.addEventListener('menu-item-clicked', (e) => {
			console.log('clicked', e.detail.menuItem.value)
		}) */
		
		const userSponsorEmailTemplateVariablesClickListener = useDynamicVariablesMenu('custom-text-user-sponsor')
		const targetUserSponsor = document.getElementById('userSponsorExpirationDynamicVariables')
		const dropdown2 = new CustomDropdown(targetUserSponsor, menuData, userSponsorEmailTemplateVariablesClickListener, "Dynamic Variables")

	}, 50);

	// console.log(CustomDropdown)
</script>

<?php include 'footer.php'; ?>
