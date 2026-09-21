<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
// Initialize page display object
$objHtmlPage = new HtmlPage();
$objHtmlPage->addStylesheet("home.css", 'screen,print');
$objHtmlPage->PrintHeader();


$q = db_query("select * from redcap_auth where username = '".db_escape($userid)."'");
$row = db_fetch_array($q);
$display_page = $row['temp_pwd'];
//Redirect to main page if password has already been set
if (!$display_page) redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");


//Catch the new password submitted by user
if (!empty($_POST))
{
    $post_password = $_POST['password'];
    switch ($password_complexity) {
        case "0":
            // one letter, and one number
            $password_complexity_condition = preg_match("/\d+/", $post_password) && preg_match("/[a-zA-Z]+/", $post_password);
            break;
        case "1":
            // one lower-case letter, one upper-case letter, and one number
            $password_complexity_condition = preg_match("/\d+/", $_POST['password']) && preg_match("/[a-z]+/", $_POST['password']) && preg_match("/[A-Z]+/", $_POST['password']);
            break;
        case "2":
            // one lower-case letter, one upper-case letter, and either one number or one special character
            $password_complexity_condition = preg_match("/[a-z]+/", $post_password) && preg_match("/[A-Z]+/", $post_password)
                                             && (preg_match("/\d+/", $post_password)
                                                 || (preg_match("/[\!@#\$%\^&\*\(\)_\+\|~=’,\/\-:\"\;\?,\.]+/", $post_password)
                                                     && !preg_match("/[\<\>\\\\]+/", $post_password)));
            break;
        case "3":
            // one lower-case letter, one upper-case letter, one number, and one special character
            $password_complexity_condition = preg_match("/[a-z]+/", $post_password) && preg_match("/[A-Z]+/", $post_password)
                                             && preg_match("/\d+/", $post_password)
                                             && (preg_match("/[\!@#\$%\^&\*\(\)_\+\|~=’,\/\-:\"\;\?,\.]+/", $post_password)
                                                && !preg_match("/[\<\>]+/", $post_password));
            break;
    }
	// Check password length and complexity
	if (isset($_POST['password']) && $_POST['password'] != "" && isset($_POST['password2']) && $_POST['password'] == $_POST['password2']
		&& $password_complexity_condition
		&& strlen($_POST['password']) >= $password_length)
	{
		// Set default flag to reset password
		$resetPass = true;
		$sql_all = array();
		// If limit is set on preventing re-use of last 5 passwords, then check auth_history table for past 5 passwords
		if ($password_history_limit)
		{
			// Get last 5 passwords
			$sql_all[] = $sql = "select password from redcap_auth_history where username = '".db_escape($userid)."' order by timestamp desc limit 5";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				if ($row['password'] == Authentication::hashPassword($_POST['password'], '', $userid)) {
					// Password is being re-used, so prompt the user again for another password value to set
					$resetPass = false;
				}
			}
		}
		// Check if we can reset the password
		if ($resetPass)
		{
			// Set the new password in redcap_auth
			$hashed_password = Authentication::hashPassword($_POST['password'], '', $userid);
			$sql_all[] = $sql = "UPDATE redcap_auth SET password = '$hashed_password', temp_pwd = 0, password_reset_key = NULL
								 WHERE username = '".db_escape($userid)."'";
			if (db_query($sql))
			{
				// Also add to auth_history table
				$sql_all[] = $sql = "insert into redcap_auth_history (username, password, timestamp) values ('".db_escape($userid)."', '$hashed_password', '".NOW."')";
				db_query($sql);
				// Log this action as last activity and last login time
				Logging::logEvent(implode(";\n", $sql_all),"redcap_auth","MANAGE",$userid,"username = '" . db_escape($userid) . "'","Change own password");
				Authentication::setUserLastLoginTimestamp($userid);
				// Redirect to success page so that no one can re-post password values (i.e., password stored in browser memory)
				redirect(PAGE_FULL);
			} else {
				exit("ERROR!");
			}
		}
	}
}


//Check if the user has changed their password from the default value
if ($display_page)
{
	print  "<style type='text/css'>#pagecontent {margin: 0px;}</style>";
	print RCView::div(array(), RCView::img(array('src'=>'redcap-logo-large.png')));
	print "<h4 style='color:#800000;'>{$lang['pwd_reset_10']}</h4>";

	$password_complexity_text = $lang['pwd_reset_56'].$password_length.$lang['pwd_reset_61'];
    $note_text = "";
	if ($password_complexity == 0) {
        $password_complexity_text .= $lang['pwd_reset_62'];
    }
    if ($password_complexity == 1) {
        $password_complexity_text .= $lang['pwd_reset_63'];
    }
    if ($password_complexity == 2) {
        $password_complexity_text .= $lang['pwd_reset_64'];
        $note_text = $lang['system_config_700'] . ' <code>!@#$%^&*()/_+|~=’,-*+:\";?.</code> (excluding <code>><\</code> )';
    }
    if ($password_complexity == 3) {
        $password_complexity_text .= $lang['pwd_reset_65'];
		$note_text = $lang['system_config_700'] . ' <code>!@#$%^&*()/_+|~=’,-*+:\";?.</code> (excluding <code>><\</code> )';
    }

	print "<p>{$lang['pwd_reset_01']} <b>".$password_complexity_text."</b></p>";
    if ($note_text != "") {
        print $note_text;
    }
	// If setting is set to limit on using past 5 passwords and entering one of those five, give error msg to re-enter a new one.
	if ($password_history_limit && isset($resetPass) && !$resetPass)
	{
		print  "<p class='red'>
					<img src='".APP_PATH_IMAGES."exclamation.png'>
					<b>{$lang['global_01']}</b><br>{$lang['pwd_reset_15']} {$lang['pwd_reset_18']}
				</p>";
	}
	// Give note that password has expired, if using password
	if (!empty($password_reset_duration) && isset($_GET['msg']) && $_GET['msg'] == 'expired')
	{
		print  "<p class='red'>
					<img src='".APP_PATH_IMAGES."exclamation.png'>
					<b>{$lang['global_03']}</b><br>
					{$lang['pwd_reset_16']} <b>$password_reset_duration {$lang['scheduling_25']}</b>{$lang['period']}
					{$lang['pwd_reset_17']} ";
		if ($password_history_limit) {
			print $lang['pwd_reset_18'];
		}
		print  "</p>";
	}
	print "<center>";
	print "<form method='post' name='passform' action='{$_SERVER['REQUEST_URI']}'> ";
	print "<table style='font-size:13px;margin:25px 0 15px;' class='blue'>
			<tr>
				<td style='padding:5px;'>{$lang['global_11']}{$lang['colon']} </td>
				<td style='color:#800000;font-size:14px;padding:5px;'>$userid</td>
			</tr>
			<tr>
				<td style='padding:5px;'>{$lang['global_32']}{$lang['colon']} </td>
				<td style='padding:5px;'>
					<input autocomplete='new-password' type=\"password\" class='x-form-text x-form-field' name=\"password\"
					onkeydown='if(event.keyCode == 13) return false;' onBlur=\"
						this.value = trim(this.value);
						if(this.value.length > 0) {
							if(!chk_cont(this, $password_complexity)) {
								simpleDialog('".js_escape(strip_tags($password_complexity_text))."',null,null,500,'document.passform.password.focus();');
								return false;
							}
							if(this.value.length < $password_length) {
								simpleDialog('".js_escape($lang['pwd_reset_59']).$password_length.js_escape(" ".$lang['pwd_reset_66'])."',null,null,500,'document.passform.password.focus();');
								return false;
							}
						}
					\">
				</td>
			</tr>
			<tr>
				<td style='padding:5px;'>{$lang['pwd_reset_05']}{$lang['colon']} </td>
				<td style='padding:5px;'>
					<input autocomplete='new-password' type=\"password\" class='x-form-text x-form-field'
					onkeydown='if(event.keyCode == 13) return false;' name=\"password2\" onBlur=\"
						this.value = trim(this.value);
						if(this.value.length > 0) {
							if(!chk_cont(this, $password_complexity)) {
								simpleDialog('".js_escape(strip_tags($password_complexity_text))."',null,null,500,'document.passform.password2.focus();');
								return false;
							}
							if(this.value.length < $password_length) {
								simpleDialog('".js_escape($lang['pwd_reset_59']).$password_length.js_escape(" ".$lang['pwd_reset_66'])."',null,null,500,'document.passform.password2.focus();');
								return false;
							}
						}
					\">
				</td>
			</tr>
		</table>";
	print "<p style='text-align:center;margin-bottom:50px;'><input type='submit' class='btn' value='".js_escape($lang['survey_200'])."'
		onclick=\"if(document.passform.password.value.length < 1) return false; if(trim(document.passform.password.value) != trim(document.passform.password2.value) && document.passform.password.value.length > 1) {
			simpleDialog('".js_escape($lang['pwd_reset_14'])."',null,null,500,'document.passform.password.value=\'\'; document.passform.password2.value=\'\'; document.passform.password.focus();');
			return false; } \"> ";
	print "</form> ";
	print "</center>";
} else {
	print "<h4>{$lang['pwd_reset_06']}</h4>";
}


$objHtmlPage->PrintFooter();
