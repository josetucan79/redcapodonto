<?php


define('NOAUTH', true);

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
// Initialize page display object
$objHtmlPage = new HtmlPage();
$objHtmlPage->addStylesheet("home.css", 'screen,print');
$objHtmlPage->PrintHeader();

// Clean post username
if (isset($_POST['username'])) {
	$_POST['username'] = trim(strip_tags(label_decode($_POST['username'])));
}

// Form for entering username
$usernameForm = "<center>
	<form id='pass-reset-form' method='post' action='".PAGE_FULL."'>
	<table style='margin:25px 0;padding:15px 20px;max-width:400px;' class='blue p-3'>
		<tr>
			<td style='padding:15px;vertical-align:top;'>
				<b>{$lang['global_11']}{$lang['colon']} &nbsp;</b>
			</td>
			<td style='padding:15px;vertical-align:top;'>
				<input type=\"text\" class='x-form-text x-form-field' name=\"username\" autocomplete='new-password' value=\"".(isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES) : "")."\">
			</td>
		</tr>
		<tr>
			<td style='padding:5px 15px 15px;'></td>
			<td style='padding:5px 15px 15px;'>
				<button class='btn btn-sm btn-primaryrc fs13' onclick=\"username.value=trim(username.value);if(username.value.length < 1) { simpleDialog('".js_escape($lang['pwd_reset_24'])."'); return false; } $('#pass-reset-form').submit();\"><i class='fas fa-envelope'></i> {$lang['pwd_reset_75']}</button>
			</td>
		</tr>
	</table>
	</form>
	</center>";


// Render instructions
print  "<h4 style='margin-top:40px;color:#800000;'>{$lang['pwd_reset_23']}</h4>
		<p>{$lang['pwd_reset_71']}</p>";


/**
 * CHECK IF IP IS BANNED
 * Check logging for past 24 hours to make sure this IP didn't get temporarily banned for this page in that period
 */
// Get timestamp for 1 day ago
$oneDayAgo = date("YmdHis", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-1,date("Y")));
// Get IP
$ip = System::clientIpAddress();
// Banned log description
$bannedLogDescription = "Temporarily ban IP address for Password Recovery page";
// Check logging table for IP
$sql = "select 1 from redcap_log_event where ts > $oneDayAgo and description = '$bannedLogDescription' and ip = '".db_escape($ip)."' limit 1";
$q = db_query($sql);
if (db_num_rows($q) > 0)
{
	// Message that user is locked out temporarily
	print RCView::p(array('class'=>'yellow'),
			RCView::img(array('src'=>'exclamation_orange.png')) .
			RCView::b($lang['pwd_reset_51']) . RCView::br() . $lang['pwd_reset_53'] . RCView::b($ip) . $lang['pwd_reset_54']
		);
	// Footer
	print RCView::div(array('class'=>'space'), "&nbsp;");
	$objHtmlPage->PrintFooter();
	exit;
}
## If IP is not banned, then check if they've accessed this page 20x in the past minute. If they have, ban them (i.e. log it).
// Get timestamp for 1 min ago
$oneMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-1,date("s"),date("m"),date("d"),date("Y")));
// Check log_view table for IP in past minute
$sql = "select 1 from redcap_log_view where ts > '$oneMinAgo' and page = '".PAGE."'	and ip = '".db_escape($ip)."'";
$q = db_query($sql);
if (db_num_rows($q) >= 20)
{
	// Logging: Log that use has been banned
	Logging::logEvent("","redcap_auth","MANAGE",$ip,"ip = '$ip'",$bannedLogDescription);
	// Redirect page to itself so that the banned IP message will be displayed
	redirect(PAGE_FULL);
}


## Display instructions and username text field
if ($_SERVER['REQUEST_METHOD'] != 'POST')
{
	// Enter username
	print $usernameForm;
}


elseif (isset($_POST['username']))
{
	## CHECK USERNAME
	## First, make sure they don't already have 5 failed attempts in past 15 minutes (or whatever the custom settings are). If so, don't process further.
	$logout_fail_window = (isinteger($logout_fail_window) && $logout_fail_window > 0) ? $logout_fail_window : 15;
	$logout_fail_limit = (isinteger($logout_fail_limit) && $logout_fail_limit > 0) ? $logout_fail_limit : 5;
	$xMinAgo = date("YmdHis", mktime(date("H"),date("i")-$logout_fail_window,date("s"),date("m"),date("d"),date("Y")));
	// Check logging table for failed attempts in past X minutes
	$sql = "select 1 from redcap_log_event where ts > $xMinAgo and user = '".db_escape($_POST['username'])."'
			and description = 'Failed to reset own password'";
	$q = db_query($sql);
	if (db_num_rows($q) >= ($logout_fail_limit-1))
	{
		// Message that user is locked out temporarily
		print RCView::p(array('class'=>'yellow'),
				RCView::img(array('src'=>'exclamation_orange.png')) .
				RCView::b($lang['pwd_reset_51']) . RCView::br() . $lang['pwd_reset_52']
			);
		// Footer
		print RCView::div(array('class'=>'space'), "&nbsp;");
		$objHtmlPage->PrintFooter();
		exit;
	}
	// Query tables to verify as user
	$sql = "select a.username, a.password_question, a.password_answer, i.username as info_username, i.user_email, i.user_suspended_time
			from redcap_auth a right outer join redcap_user_information i
			on a.username = i.username where i.username = '".db_escape($_POST['username'])."'";
	$q = db_query($sql);
	// Check if a user
	$isUser = (db_num_rows($q) > 0);
    // Is the REDCap user suspended? If so, do not send email.
    $isUserSuspended = $isUser ? (db_result($q, 0, 'user_suspended_time') != null) : null;
	// Is a REDCap user, so check if they are a table-based user
	$isTableBasedUser = $isUser ? (db_result($q, 0, 'username') != null) : null;
	// Get email address
	$user_email = $isUser ? (db_result($q, 0, 'user_email')??"") : "";
    // Set HTML for successfully reset message
    $questionFormSuccess = RCView::div(array('class'=>'darkgreen ','style'=>'margin:20px 0 25px;padding:12px 15px 15px;'),
                                RCView::h5(['class'=>'text-successrc'], '<i class="fas fa-envelope"></i> '.$lang['survey_524']) .
                                "{$lang['pwd_reset_73']} {$lang['leftparen']}<b>".RCView::escape($_POST['username'])."</b>{$lang['rightparen']} {$lang['pwd_reset_74']}
                                        <div style='padding-top:20px;font-size:11px;'>{$lang['pwd_reset_44']}</div>"
                            ) .
                            RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs13','onclick'=>'window.location.href="'.APP_PATH_WEBROOT_FULL.'";'), $lang['pwd_reset_45']);

	/**
	// Is a real user AND is table-based user?
    $questionForm = "";
	// If not a real username or if not a table-based user, display custom text or info saying it can't be determined why the user cannot log in
	if (!$isUser || !$isTableBasedUser) {
		// Use custom password reset text, if exists
		$resetPassInvalidText = (trim($password_recovery_custom_text) != '')
			? nl2br(decode_filter_tags($password_recovery_custom_text))
			: "{$lang['pwd_reset_78']} \"<b>".RCView::escape($_POST['username'])."</b>\" {$lang['pwd_reset_79']}";
		// Either not a REDCap user OR not a table-based user OR hasn't set up security question yet. Give error msg.
		$questionForm =	$usernameForm .
			RCView::div(array('class'=>'yellow','style'=>'margin:20px 0;max-width:100%;'),
				RCView::img(array('src'=>'exclamation_orange.png')) .
				"<b>{$lang['pwd_reset_31']} \"<span style='color:#800000;'>".RCView::escape($_POST['username'])."\"</span></b><br><br>
							$resetPassInvalidText<br><br>
							{$lang['pwd_reset_32']} <a href='mailto:$homepage_contact_email'>{$lang['bottom_39']}</a>{$lang['period']}"
			);
	} elseif ($isUser && $isUserSuspended === true) {
		// User is suspended, so they can't reset their password until they tell an admin to unsuspend them
		$questionForm =	$usernameForm .
						RCView::div(array('class'=>'yellow','style'=>'margin:20px 0;max-width:100%;'),
							"<b><i class=\"fas fa-exclamation-triangle\"></i> {$lang['pwd_reset_31']} \"<span style='color:#800000;'>".RCView::escape($_POST['username'])."\"</span></b><br><br>
							{$lang['pwd_reset_76']}<br><br>
							{$lang['pwd_reset_32']} <a href='mailto:$homepage_contact_email'>{$lang['bottom_39']}</a>{$lang['period']}"
						);
	}
	if ($isUser && $isUserSuspended === true) {
		// User is suspended, so they can't reset their password until they tell an admin to unsuspend them
		$questionForm =	$usernameForm .
			RCView::div(array('class'=>'yellow','style'=>'margin:20px 0;max-width:100%;'),
				"<b><i class=\"fas fa-exclamation-triangle\"></i> {$lang['pwd_reset_31']} \"<span style='color:#800000;'>".RCView::escape($_POST['username'])."\"</span></b><br><br>
							{$lang['pwd_reset_76']}<br><br>
							{$lang['pwd_reset_32']} <a href='mailto:$homepage_contact_email'>{$lang['bottom_39']}</a>{$lang['period']}"
			);
	}
	**/

    ## Email password reset link
    if ($user_email != '')
    {
        // Get reset password link
        $resetpasslink = Authentication::getPasswordResetLink($_POST['username']);
		// If not a table-based user, display custom text or info saying it can't be determined why the user cannot log in
		if (!$isTableBasedUser) {
			// Use custom password reset text, if exists
			$resetPassInvalidText = (trim($password_recovery_custom_text) != '')
				? nl2br(decode_filter_tags($password_recovery_custom_text))
				: "{$lang['pwd_reset_78']} \"<b>".RCView::escape($_POST['username'])."</b>\" {$lang['pwd_reset_79']}";
			// Either not a REDCap user OR not a table-based user OR hasn't set up security question yet. Give error msg.
			$emailContents = "<b>{$lang['pwd_reset_31']} \"<span style='color:#800000;'>".RCView::escape($_POST['username'])."\"</span></b><br><br>
							$resetPassInvalidText<br><br>
							{$lang['pwd_reset_32']} <a href='mailto:$homepage_contact_email'>{$lang['bottom_39']}</a>{$lang['period']}";
		} elseif ($isUserSuspended === true) {
			// User is suspended, so they can't reset their password until they tell an admin to unsuspend them
			$emailContents = "<b><i class=\"fas fa-exclamation-triangle\"></i> {$lang['pwd_reset_31']} \"<span style='color:#800000;'>".RCView::escape($_POST['username'])."\"</span></b><br><br>
							{$lang['pwd_reset_76']}<br><br>
							{$lang['pwd_reset_32']} <a href='mailto:$homepage_contact_email'>{$lang['bottom_39']}</a>{$lang['period']}";
		} else {
			// Typical email contents
			$emailContents = $lang['control_center_4828'].' "<b>'.$_POST['username'].'</b>"'.$lang['period'].' '.
							 $lang['control_center_4829'].'<br /><br /> 
                         	<a href="'.$resetpasslink.'">'.$lang['control_center_4487'].'</a><br /><br />
                        	'.$lang['control_center_98'].' '.$project_contact_name.' '.$lang['global_15'].' '.$project_contact_email.$lang['period'];
		}
        // Send email
        $email = new Message();
        $emailSubject = 'REDCap '.$lang['control_center_102'];
        $email->setTo($user_email);
        $email->setFrom(\Message::useDoNotReply($GLOBALS['project_contact_email']));
        $email->setFromName($GLOBALS['project_contact_name']);
        $email->setSubject($emailSubject);
        $email->setBody($emailContents, true);
        $email->send();
    }
	// Display message of success (regardless of whether it was sent or not)
	print $questionFormSuccess;
}

// Footer
print RCView::div(array('class'=>'space'), "&nbsp;");
print RCView::div(array('class'=>'space'), "&nbsp;");
$objHtmlPage->PrintFooter();