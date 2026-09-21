<?php

use Vanderbilt\REDCap\Classes\Fhir\EhrRedirectManager;
use Vanderbilt\REDCap\Classes\TwoFA\Duo\Duo;

/**
 * Authentication
 * This class is used for authentication-centric activities.
 */
class Authentication
{
	// Routes/pages that should not enforce authentication
	public static $noAuthPages = array("SendItController:download", "SendIt/download.php", "PubMatch/index.php", "PubMatch/index_ajax.php");

	// Set interval at which REDCap will turn on a listener for any clicking, typing, or mouse movent (used for auto-logout)
	const AUTO_LOGOUT_RESET_TIME = 3; // After X minutes, it will call ProjectGeneral/keep_alive.php

	// IP ranges of private network IP addresses
	const PRIVATE_IP_RANGES = '10.0.0.0-10.255.255.255,172.16.0.0-172.31.255.255,192.168.0.0-192.168.255.255';

	// Set parameter name of Twilio SMS or phone call response for 2FA
	const TWILIO_2FA_SUCCESS_FLAG = '__twofactor_success';

	// Set parameter name of Twilio 2FA phone call Twiml, whose response will trigger survey URL containing TWILIO_2FA_SUCCESS_FLAG
	const TWILIO_2FA_PHONECALL_FLAG = '__twofactor_phonecall';

    // Make sure that the 2FA verify code endpoint is not called more than 10 times per minute
	const MFA_RATE_LIMIT = 10;


	// Return array of all available options for the expiration time (in minutes) of 2FA verification codes (adjustible at user-level)
	public static function getTwoFactorCodeExpirationTimes()
	{
		return array(2, 5, 10, 20, 30);
	}


	// For drop-down display, return array of all available options for the expiration time (in minutes) of 2FA verification codes
	public static function getTwoFactorCodeExpirationTimesDropdown()
	{
		global $lang;
		$options = array();
		foreach (self::getTwoFactorCodeExpirationTimes() as $min) {
			$options[$min] = "$min {$lang['survey_428']}";
		}
		return $options;
	}
	
	
	// Get last time a table-based user's password was reset. Return null if doesn't exist, else return the timestamp.
	public static function getUserLastPasswordResetTime($username)
	{
		if (trim($username) == '') return null;
		$sql = "select timestamp(ts) from redcap_log_event where pk = '" . db_escape($username) . "'
				and event = 'MANAGE' and description in ('Reset user password', 'Reset own password')
				order by log_event_id desc limit 1";
		$q = db_query($sql);
		if ($q && db_num_rows($q)) {
			return db_result($q, 0);
		}
		return null;
	}

	// Display notice that password will expire soon (if utilizing $password_reset_duration for Table-based authentication)
	static function displayPasswordExpireWarningPopup()
	{
		global $lang;
		// If expiration time is in session, then display pop-up
		if (isset($_SESSION['expire_time']) && !empty($_SESSION['expire_time']))
		{
			?>
			<script type="text/javascript">
			$(function(){
                if ($('#two_factor_login_dialog').length) {
                    simpleDialog("<?php echo js_escape2("{$lang['pwd_reset_19']} (<b>{$_SESSION['expire_time']}</b>){$lang['period']} {$lang['pwd_reset_69']}") ?>",
                        "<?php echo js_escape2($lang['pwd_reset_20']) ?>", "expire_pwd_msg", 500);
                } else {
                    simpleDialog("<?php echo js_escape2("{$lang['pwd_reset_19']} (<b>{$_SESSION['expire_time']}</b>){$lang['period']} {$lang['pwd_reset_69']} {$lang['pwd_reset_70']}") ?>",
                        "<?php echo js_escape2($lang['pwd_reset_20']) ?>", "expire_pwd_msg", 500, null, '<?php echo js_escape($lang['pwd_reset_67']) ?>', function() {
                            $.post(app_path_webroot+'ControlCenter/user_controls_ajax.php?action=reset_password_as_temp', { }, function(data) {
                                if (data != '0') {
                                    window.location.reload();
                                } else {
                                    alert(woops);
                                }
                            });
                        }, '<?php echo js_escape($lang['pwd_reset_68']) ?>');
                }
			});
			</script>
			<?php
			// Remove variable from session so that the user doesn't keep getting prompted
			unset($_SESSION['expire_time']);
		}
	}


	/**
	 * Generate the login token from the login page
	 */
	public static function generateLoginToken()
	{
	    // Randomly determine length of base token (2-digit)
        $tokenLength = random_int(10, 99);
        // Create a token
        $token = generateRandomHash($tokenLength);
        // Append length to end of string
        $token .= $tokenLength;
        // Append and prepend "redcap" to token
        $token = "redcap" . $token . "redcap";
        // Encrypt the token
        $token = encrypt($token);
        // Return token
        return $token;
	}


	/**
	 * Verify the login token from the login page. Return boolean on success.
	 */
	public static function verifyLoginToken()
	{
	    // Get token from POST
        $token = isset($_POST['redcap_login_a38us_09i85']) ? $_POST['redcap_login_a38us_09i85'] : "";
        // Make sure that username/password are provided in POST and session doesn't exist yet
        if (!(isset($_POST['username']) && isset($_POST['password']) && !isset($_SESSION['username']))) return false;
	    // Make sure it has a value
        if (trim($token) == "") return false;
        // Decrypt it
		$token = decrypt($token."");
		// Does it begin AND end with "redcap"?
        $begin = substr($token, 0, 6);
        $end = substr($token, -6);
        if ($begin != "redcap" && $end != "redcap") return false;
        // Does the length match (last 2 digits on end)?
        $token = substr($token, 6, -6);
		$tokenLength = substr($token, -2)*1;
		if ((strlen($token)-2) != $tokenLength) return false;
		// If the base token has only alphanumeric characters, then we're good.
		$token = substr($token, 0, -2);
		$tokenFiltered = preg_replace("/[^a-zA-Z0-9]/", "", $token);
		return ($tokenFiltered === $token);
	}

	/**
	 * AUTHENTICATE THE USER
	 */
	public static function authenticate()
	{
		global $auth_meth_global, $app_name, $username, $password, $hostname, $db, $institution, $double_data_entry,
			   $project_contact_name, $autologout_timer, $lang, $isMobileDevice, $password_reset_duration, $enable_user_allowlist,
			   $homepage_contact_email, $homepage_contact, $isAjax, $rc_autoload_function, $two_factor_auth_enabled;

		// If PAGE/route is in $noAuthPages array, then set NOAUTH constant and do not authenticate the user
		if (defined("PAGE") && in_array(PAGE, self::$noAuthPages) && !defined("NOAUTH")) {
			define("NOAUTH", true);
		}

		// Check if authentication was manually disabled for the current page. If so, exit this function.
		// Setting OVERRIDE-NOAUTH after a NOAUTH bootstrap will re-enable authentication for a session.  This was introduced by Andy Martin to facilitate REDCap-based authentication for the community platform as an option.
		if (defined("NOAUTH") && !defined("OVERRIDE-NOAUTH")) return true;

		// Start the session before PEAR Auth does so we can check if auth session was lost or not (from load balance issues)
		Session::init();


		// Set default value to determine later if we need to make left-hand menu disappear so user has access to nothing
		$GLOBALS['no_access'] = 0;

		// If logging in, trim the username to prevent confusion and accidentally creating a new user
		if (isset($_POST['redcap_login_a38us_09i85']) && $auth_meth_global != "none")
		{
		    // Validate the login token
			if (!self::verifyLoginToken()) {
				// Render error message and show login screen again
				print RCView::div(array('class'=>'red','style'=>'max-width:100%;width:100%;font-weight:bold;'), RCView::img(array('src'=>'exclamation.png')).$lang['global_64']);
				loginFunction();
				exit;
			}

			// If we just reset a password, in which it is encrypted in the query string
			if (isset($_POST['redcap_login_password_temp'])) {
				$_POST['password'] = $_POST['redcap_login_password_temp'];
				unset($_POST['redcap_login_password_temp']);
			}
			// Trim
			$_POST['username'] = trim($_POST['username']);
			// Make sure it's not longer than 255 characters to prevent attacks via hitting upper bounds
			if (strlen($_POST['username']) > 255) {
				$_POST['username'] = mb_substr($_POST['username'], 0, 255);
			}
		}
		
		EhrRedirectManager::detectEhrContext();

		## AUTHENTICATE and GET USERNAME: Determine method of authentication
		// No authentication is used
		if ($auth_meth_global == 'none') {
			$userid = 'site_admin'; //Default user
		}

		////***<AAF Modification>***Aaf modification for aaf_table and aaf 
        elseif($auth_meth_global=='aaf' || ($auth_meth_global=='aaf_table' && (isset($_POST["assertion"]) || isset($_SESSION['tli'])))){
                global $aafSecret,$aafIss,$aafAud,$aafEmpostfix,$aafMigration,$aafPrimaryField;//How will $aafEmpostfix,$aafMigration be entered ?
                if(!isset($_SESSION['username'])){
                        if(isset($_POST["assertion"])){
                                require_once APP_PATH_LIBRARIES . "aafAuthenticator.php";
                                $aafAuthenticator=new aafAuthenticator;
                                $aafMap=$aafAuthenticator->getAAFMap($_POST["assertion"],$aafSecret,$aafIss,$aafAud);
                                $un1=$aafMap->mail;//mail is the default field for username.
                                if(isset($aafMap->$aafPrimaryField)){
                                        $un1=$aafMap->$aafPrimaryField;//Assigns username to redcap_config.aafPrimaryField
                                }
                                error_log("AAF Login - gn: ".$aafMap->givenname." sn: ".$aafMap->surname." (mail): ".$aafMap->mail." (mail or full pn): ".$un1);
                                $aafUserInfo = User::getAafUser($un1,$aafMap->mail);
                                if($aafMigration=='off'){
                                        if(db_num_rows($aafUserInfo)==1){
                                                while ($row = db_fetch_assoc($aafUserInfo)){
                                                        $_POST['username']=$row['username'];
                                                }
                                                $userid=$_SESSION['username']=$_POST['username'];
                                        }
                                        if(db_num_rows($aafUserInfo)==0){
                                                self::recordNewAAFUser($un1,$aafMap);
                                                $userid=$_SESSION['username']=$un1;
                                        }
                                }else{//<SYD UNI SPECIFIC>
                if(db_num_rows($aafUserInfo)==1){
                                                while ($row = db_fetch_assoc($aafUserInfo)){
                                                        $_POST['username']=$row['username'];
                                                }
                                                if($_POST['username']!=$un1){//**if the user has been found with email, not username, update username..
                                                        User::updateUsernameForAaf($un1,$_POST['username'],$aafMap->organizationname);
                                                        $_POST['username']=$un1;
                                                }
                                                $userid=$_SESSION['username']=$_POST['username'];
                                        }
                                        if(db_num_rows($aafUserInfo)==0){//$un1 and email has not found user in db
                                                $un2=$un1;
                                                if(isset($aafMap->edupersonprincipalname) && isset($aafEmpostfix) && strpos($aafMap->edupersonprincipalname,$aafEmpostfix)>0){
                                                        $un2=strstr($aafMap->edupersonprincipalname, $aafEmpostfix, true);//only locals can have this
                                                }
                                                /*if(!isset($un2)){
                                                        exit('AAF edupersonprincipalname in AAF return does not include a prefix for: '.$aafMap->givenname." ".$aafMap->surname);
                                                }*/
                                                $aafUserInfo2ndAttempt=User::getAafUser($un2,$aafMap->mail);//try finding the user with unikey prefix for locals
                                                if(db_num_rows($aafUserInfo2ndAttempt)==1){
                                                        while ($row = db_fetch_assoc($aafUserInfo2ndAttempt)){
                                                                $_POST['username']=$row['username'];
                                                        }
                                                        if($_POST['username']!=$un1){//updating old username with new in db
                                                                User::updateUsernameForAaf($un1,$_POST['username'],$aafMap->organizationname);
                                                                $_POST['username']=$un1;
                                                        }
                                                        $userid=$_SESSION['username']=$_POST['username'];
                                                }
                                                if(db_num_rows($aafUserInfo2ndAttempt)==0){
                                                        self::recordNewAAFUser($un1,$aafMap);
                                                        $userid=$_SESSION['username']=$un1;
                                                        //For users who were in against projects etc with their edupersonprincipalnameprefix but not in user table ! FMD.
                                                        if($un2!=$un1){
                                                                User::updateUsernameForAaf($un1,$un2,$aafMap->organizationname);
                                                        }
                                                }
                                        }
                                }// </SYD UNI SPECIFIC>
                                if(db_num_rows($aafUserInfo)>1){
                                   exit('Possible dupe: '.$aafMap->givenname.' '.$aafMap->surname.'. Username: '.$un1.'. email: '.$aafMap->mail);
                                }
                                self::setUserLastLoginTimestamp($userid);  //Not session id dependent.
                                $_SESSION['tli']=0;//setting non table login sess param
                                self::setAAFCsrfToken($userid);
                        }
                }else{
                        $userid=$_SESSION['username'];
                }
        }
		//***</AAF Modification>***

		// RSA SecurID two-factor authentication (using PHP Pam extension)
		elseif ($auth_meth_global == 'rsa') {
			// If username in session doesn't exist and not on login page, then force login
			if (!isset($_SESSION['rsa_username']) && !isset($_POST['redcap_login_a38us_09i85'])) {
				loginFunction();
			}
			// User is attempting to log in, so try to authenticate them using PAM
			elseif (isset($_POST['redcap_login_a38us_09i85']))
			{
				// Make sure RSA password is not longer than 14 characters to prevent attacks via hitting upper bounds
				// (8 char max for PIN + 6-digit tokencode)
				if (strlen($_POST['password']) > 14) {
					$_POST['password'] = substr($_POST['password'], 0, 14);
				}
				// If PHP PECL package PAM is not installed, then give error message
				if (!function_exists("pam_auth")) {
					if (isDev()) {
						// For development purposes only, allow passthru w/o valid authentication
						$userid = $_SESSION['username'] = $_SESSION['rsa_username'] = strtolower($_POST['username']);
					} else {
						// Display error
						renderPage(
							RCView::div(array('class'=>'red'),
								RCView::div(array('style'=>'font-weight:bold;'), $lang['global_01'].$lang['colon']) .
								"The PECL PAM package in PHP is not installed! The PAM package must be installed in order to use
								the pam_auth() function in PHP to authenticate tokens via RSA SecurID. You can find the offical
								documentation on PAM at <a href='http://pecl.php.net/package/PAM' target='_blank'>http://pecl.php.net/package/PAM</a>."
							)
						);
					}
				}
				// If have logged in, then try to authenticate the user
				elseif (pam_auth($_POST['username'], $_POST['password'], $err, false) === true) {
					$userid = $_SESSION['username'] = $_SESSION['rsa_username'] = strtolower($_POST['username']);
					// Log that they successfully logged in in log_view table
					Logging::logPageView("LOGIN_SUCCESS", $userid);
					// Set the user's last_login timestamp
					self::setUserLastLoginTimestamp($userid);
				}
				// Error
				else {

                    // Render error message and show login screen again
                    print   RCView::div(array('class'=>'red','style'=>'max-width:100%;width:100%;font-weight:bold;'),
                        RCView::fa("fa-solid fa-circle-exclamation text-danger me-1") .
                        RCView::tt("config_functions_128") // ERROR: You entered an invalid user name or password!
                    );
                    loginFunction();
				}
			}
			// If already logged in, the just set their username
			elseif (isset($_SESSION['rsa_username'])) {
				$userid = $_SESSION['username'] = strtolower($_SESSION['rsa_username']);
			}
		}
        // OpenID Connect
        elseif ($auth_meth_global == 'openid_connect') {
            if (isset($_SESSION['username'])) {
                $userid = $_SESSION['username'];
            } else {
                // Capture original URL so that we can redirect back to it
                if (!isset($_GET['code']) && !isset($_GET['state']) && !isset($_GET['iss']) && !(isset($_GET['action']) && $_GET['action'] == 'openid_connect_login')) {
                    $redirectUrl = (isset($_GET['logout']) && !empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'http') === false) ? $_SERVER['HTTP_REFERER'] : $_SERVER['REQUEST_URI'];
                    $redirectUrl = str_replace(["?logout=1", "&logout=1"], "", $redirectUrl);
                    savecookie('auth_redirect_url', encrypt($redirectUrl), 120);
                }
                // Authenticate
                $userid = self::authenticateOpenIDConnect();
                if ($userid !== null) {
                    // Redirect back to original URL, else fallback to REDCap Home Page
                    if (isset($_COOKIE['auth_redirect_url'])) {
                        $redirectUrl = decrypt($_COOKIE['auth_redirect_url']);
                        deletecookie('auth_redirect_url');
                        redirect($redirectUrl);
                    }
                    // Remove code and state params from query string and reload page
                    $parsed = parse_url($_SERVER['REQUEST_URI']);
                    $query = $parsed['query'];
                    parse_str($query, $params);
                    unset($params['code'], $params['state']);
                    redirect($parsed['path'].(empty($params) ? "" : "?".http_build_query($params)));
                }
            }
        }
        // OpenID Connect + Table
        elseif ($auth_meth_global == 'openid_connect_table' && ($_GET['action']??"" != 'passwordreset')) {
            // User has a session running
            $userid = $_SESSION['username'] ?? null;

            // Capture original URL so that we can redirect back to it
            if ($userid === null && !isset($_GET['code']) && !isset($_GET['state']) && !isset($_GET['iss']) && !(isset($_GET['action']) && $_GET['action'] == 'openid_connect_login')) {
                $redirectUrl = $_SERVER['REQUEST_URI'];
                savecookie('auth_redirect_url', encrypt($redirectUrl), 120);
            }
            if (isset($userid) && $userid != null && !isset($_GET['code']) && !isset($_GET['state'])) {
               // Do nothing
			} elseif (isset($userid) && $userid != null && EhrRedirectManager::isCurrentPageEhr()) {
               // Do nothing: Logged in and in a local EHR page where the $_GET['state'] is available
            } elseif ((isset($_GET['action']) && $_GET['action'] == 'openid_connect_login') || (isset($_GET['code']) && isset($_GET['state']))) {
                // Authenticate
                $userid = self::authenticateOpenIDConnect();
                if ($userid !== null) {
					 if (isset($_COOKIE['auth_redirect_url'])) {
						// Redirect back to original URL, else fallback to REDCap Home Page
                        $redirectUrl = decrypt($_COOKIE['auth_redirect_url']);
                        deletecookie('auth_redirect_url');
                        redirect($redirectUrl);
                    }
                    // Remove code and state params from query string and reload page
                    $parsed = parse_url($_SERVER['REQUEST_URI']);
                    $query = $parsed['query'];
                    parse_str($query, $params);
                    unset($params['code'], $params['state']);
                    redirect($parsed['path'].(empty($params) ? "" : "?".http_build_query($params)));
                }
            } elseif (isset($_POST['redcap_login_a38us_09i85']) && isset($_POST['username']) && isset($_POST['password'])) {
                // Validate table-based users
                if (self::isTableUser($_POST['username'])) {
                    $userid = self::tableBasedLogin();
                }
                if (!$userid) {
					// Display error message that table-based login failed
					print  '<!DOCTYPE HTML>' . "\n"; // Quick fix to prevent browser using Quirks Mode
					print   RCView::div(array('class'=>'red','style'=>'max-width:100%;width:100%;font-weight:bold;'),
                        RCView::fa("fa-solid fa-circle-exclamation text-danger me-1") .
                        RCView::tt("config_functions_128") // ERROR: You entered an invalid user name or password!
                    );
					?><script type="text/javascript">setTimeout(function(){ document.getElementById('rc-login-form').style.display='block'; },1000)</script><?php
                    loginFunction();
                }
            } else {
				// Display error message that login failed
				if (isset($_REQUEST['error'])) {
					$desc = isset($_REQUEST['error_description']) ? rawurldecode(urldecode($_REQUEST['error_description'])) : 'Unknown login error';
					print  '<!DOCTYPE HTML>' . "\n"; // Quick fix to prevent browser using Quirks Mode
					print   RCView::div(array('class'=>'red','style'=>'max-width:100%;width:100%;font-weight:bold;'),
                                RCView::img(array('src'=>'exclamation.png')) .
                                "{$lang['global_01']}{$lang['colon']} ".strip_tags($desc)
                            );
				}
                loginFunction();
            }
        }
		// Shibboleth authentication (Apache module)
		elseif ($auth_meth_global == 'shibboleth') {
			// Determine if there are any Shibboleth sites defined in the Control Center
			$shibboleth_config = json_decode($GLOBALS['shibboleth_table_config'], TRUE);
			$shibboleth_sites_defined = self::anyShibbolethSitesDefined($shibboleth_config);

			if (!$shibboleth_sites_defined || !isset($_SESSION['username']) || $_SESSION['username'] == null ) {
				// Check is custom username field is set for Shibboleth. If so, use it to determine username.
				$GLOBALS['shibboleth_username_field'] = trim($GLOBALS['shibboleth_username_field']);
				if ( isDev() ) {
					// For development purposes only, allow passthru w/o valid authentication
					$userid = $_SESSION['username'] = 'taylorr4';
				} elseif ( strlen($GLOBALS['shibboleth_username_field']) > 0 ) {
					// Custom username field
					$userid = $_SESSION['username'] = strtolower($_SERVER[$GLOBALS['shibboleth_username_field']]);
				} else {
					// Default value
					$userid = $_SESSION['username'] = strtolower($_SERVER['REMOTE_USER']);
				}
				if (!$userid && $shibboleth_sites_defined) {
					loginFunction();
				}
				// Update user's "last login" time if not yet updated for this session (for Shibboleth only since we can't know when users just logged in).
				// Only do this if coming from outside REDCap.
				if (
					!isset($_SERVER['HTTP_REFERER']) || (isset($_SERVER['HTTP_REFERER'])
						&& substr($_SERVER['HTTP_REFERER'], 0, strlen(APP_PATH_WEBROOT_FULL)) != APP_PATH_WEBROOT_FULL)
				) {
					self::setLastLoginTime($userid, true);
					if ( $GLOBALS['shibboleth_set_userinfo'] && self::userInAllowList($userid) ) {
						self::setShibbolethUserInfo($userid, $GLOBALS['shibboleth_override_userinfo']);
					}
				}
			}
			else {
				$userid = $_SESSION['username'];
				if (!$userid && $shibboleth_sites_defined) {
					loginFunction();
				}
			}
		}
 		// Shibboleth and table authentication
         elseif ($auth_meth_global == 'shibboleth_table' && $_GET['action'] != 'passwordreset') {
             // TODO: create user if not exists
             if(!isset($_SESSION['username']) || $_SESSION['username'] == null) {
                 if ( $GLOBALS['shibboleth_username_field'] = trim($GLOBALS['shibboleth_username_field']) )
                 // null == trim(null) if no shibboleth is setup...
                 {
                     if (isDev()) {
                         // For development purposes only, allow passthru w/o valid authentication
                         $userid = $_SESSION['username'] = 'taylorr4';
                     } elseif (strlen($GLOBALS['shibboleth_username_field']) > 0 && $_SERVER[$GLOBALS['shibboleth_username_field']] != null) {
                         // Does not seem to recognize null
                         // Custom username field
                         $userid = $_SESSION['username'] = strtolower($_SERVER[$GLOBALS['shibboleth_username_field']]);
                     } elseif (strlen($_SERVER['REMOTE_USER']) > 0) {
                         // Default value
                         $userid = $_SESSION['username'] = strtolower($_SERVER['REMOTE_USER']);
 
                     } else {
                         // Choose to login via table even with shib data
                         $userid = self::tableBasedLogin();
                     }
                     // Update user's "last login" time if not yet updated for this session (for Shibboleth only since we can't know when users just logged in).
                     // Only do this if coming from outside REDCap.
                     if (!isset($_SERVER['HTTP_REFERER']) || (isset($_SERVER['HTTP_REFERER'])
                                 && substr($_SERVER['HTTP_REFERER'], 0, strlen(APP_PATH_WEBROOT_FULL)) != APP_PATH_WEBROOT_FULL)
                        ) {
                         self::setLastLoginTime($userid, true);
                         if ($GLOBALS['shibboleth_set_userinfo'] && self::userInAllowList($userid) ) {
    						 self::setShibbolethUserInfo($userid, $GLOBALS['shibboleth_override_userinfo']);
                         }
                     }
                 } else {
                     //TODO: What triggers this?
                     $userid = $_SESSION['username'];
                     if (!$userid) {
                         loginFunction();
                     }
                     self::setLastLoginTime($userid, true);
                    if ( $GLOBALS['shibboleth_set_userinfo'] && self::userInAllowList($userid) ) {
                        self::setShibbolethUserInfo($userid, $GLOBALS['shibboleth_override_userinfo']);
                    }
                 }
             }
             else {
                 // User has a session running
                 $userid = $_SESSION['username'];
                 // Validate table-based users
                 if (self::isTableUser($userid)) {
                     $userid = self::tableBasedLogin();
                 }
                 if (!$userid) {
                     loginFunction();
                 }
             }
         }
		// SAMS authentication (specifically used by the CDC)
		elseif ($auth_meth_global == 'sams') {
			// Hack for development testing
			// if (isDev() && isset($_GET['sams'])) {
				// $_SERVER['HTTP_EMAIL'] = 'rob.taylor@vumc.org';
				// $_SERVER['HTTP_FIRSTNAME'] = 'Rob';
				// $_SERVER['HTTP_LASTNAME'] = 'Taylor';
				// $_SERVER['HTTP_USERACCOUNTID'] = '0014787563';
			// }
			// Make sure we have all 4 HTTP headers from SAMS
			$http_headers = get_request_headers();
			if (isset($_SESSION['redcap_userid']) && !empty($_SESSION['redcap_userid']))
			{
				global $project_contact_email;
				// DEBUGGING: If somehow the userid in the header changes mid-session, end the session and email the administrator.
				if ($http_headers['Useraccountid'] != $_SESSION['redcap_userid'])
				{
					// Get user information and login info
					$userInfo = User::getUserInfo($_SESSION['redcap_userid']);
					$userInfo2 = User::getUserInfo($http_headers['Useraccountid']);
					$sql = "select ts from redcap_log_view where user = '".db_escape($_SESSION['redcap_userid'])."'
							and event = 'LOGIN_SUCCESS' order by log_view_id desc limit 1";
					$q = db_query($sql);
					$lastLoginTime = db_result($q, 0);
					// Build debug message
					$debugMsg = "<html><body style='font-family:arial,helvetica;'>
						An authentication error just occurred in REDCap. All relevant information is listed below.<br><br>
						<b>Current REDCap user: \"{$_SESSION['redcap_userid']}\" ({$userInfo['user_firstname']} {$userInfo['user_lastname']}, {$userInfo['user_email']})</b><br>
						 - Last login time for \"{$_SESSION['redcap_userid']}\": $lastLoginTime<br><br>
						REDCap just received an HTTP header with a *different* Useraccountid: <b>\"{$http_headers['Useraccountid']}\" ({$userInfo2['user_firstname']} {$userInfo2['user_lastname']}, {$userInfo2['user_email']})</b><br><br>
						Current server time (time of incident): ".NOW."<br>
						REDCap server: ".APP_PATH_WEBROOT_FULL."<br>
						Request method: ".$_SERVER['REQUEST_METHOD']."<br>
						Current request URL: ".$_SERVER['REQUEST_URI']."<br>
						Current REDCap project_id: ".(defined("PROJECT_ID") ? PROJECT_ID : "[none]")."
						<br><br><b>POST parameters (if a POST request):</b><br>".nl2br(print_r($_POST, true))."
						<br><br><b>HTTP HEADERS:</b><br>".nl2br(print_r($http_headers, true))."
						<br><br><b>REDCap session information:</b><br>".nl2br(print_r($_SESSION, true))."
						<br><br><b>REDCap cookies:</b><br>".nl2br(print_r($_COOKIE, true))."
						</body></html>";
					// Email session/request info to the administrator
					REDCap::email($project_contact_email, \Message::useDoNotReply($project_contact_email), 'REDCap/SAMS authentication error', $debugMsg);
                    // End the session/force logout
					print  "<div style='padding:20px;color:#A00000;'><b>ERROR!</b> YOU ARE CURRENTLY SHARING THIS REDCAP SESSION WITH ANOTHER USER.<br /><br />
                            <i><a href='{$GLOBALS['sams_logout']}'>Click here to log off completely</a> and end this shared session.</i> After you’ve logged off, close your browser immediately.<br /><br />
                            To continue using REDCap, you need to open a new browser and log in to REDCap as you normally would.<br /><br />
                            If you have any questions or need additional information, contact the CDC REDCap Application Team at (<a href='mailto:$project_contact_email'>$project_contact_email</a>).</div>";
					// Log the logout
					Logging::logPageView("LOGOUT", $_SESSION['redcap_userid']);
					// Destroy session and erase userid
                    Session::destroyUserSession();
					exit;
				}
				// Set the userid as the SAMS useraccountid value from the session
				$userid = $_SESSION['username'] = strtolower($_SESSION['redcap_userid']);
			}
			elseif (isset($http_headers['Useraccountid']) && isset($http_headers['Email']) && isset($http_headers['Firstname']) && isset($http_headers['Lastname'])) {
				// If we have the SAMS headers, add the sams user account id to PHP Session (to keep throughout this user's session to know they've already authenticated)
				$userid = $_SESSION['username'] = $_SESSION['redcap_userid'] = strtolower($http_headers['Useraccountid']);
				// Log that they successfully logged in in log_view table
				Logging::logPageView("LOGIN_SUCCESS", $userid);
				// Set the user's last_login timestamp
				self::setUserLastLoginTimestamp($userid);
			}
			else {
				// Error: Could not find an existing session or the SAMS headers
				exit("{$lang['global_01']}{$lang['colon']} Your SAMS authentication session has ended. You may <a href='{$GLOBALS['sams_logout']}'>click here to log in again</a>.");
			}
		}
        // OpenID (general)
        elseif ($auth_meth_global == 'openid') {
            // Authenticate via OpenID provider
            $userid = self::authenticateOpenID();
            // Now redirect back to our original page in order to remove all the "openid..." parameters in the query string
            if (isset($_GET['openid_return_to'])) redirect(urldecode($_GET['openid_return_to']));
        }
		// OpenID (Google's Oauth2 - OpenID Connect)
		elseif ($auth_meth_global == 'openid_google') {
			// Authenticate via OpenID provider
			$userid = strtolower(self::authenticateOpenIDGoogle());
			// Now redirect back to our original page in order to remove all the "openid..." parameters in the query string
			if (isset($_GET['openid_return_to'])) redirect(urldecode($_GET['openid_return_to']));
		}
		// Oauth2 Azure AD
		elseif ($auth_meth_global == 'oauth2_azure_ad') {
            if (!isset($_SESSION['username']) && !isset($_GET['code']) && !isset($_GET['state']) && !isset($_GET['iss'])) {
                $redirectUrl = $_SERVER['REQUEST_URI'];
                savecookie('auth_redirect_url', encrypt($redirectUrl), 120);
            }
			// auth via azure ad
			try {
				$userid = strtolower(self::authenticateOauth2AzureAD());
			} catch (\Throwable $e) {
                // If any kind of fatal error occurs, kill the session and reload the page
				deletecookie('auth_redirect_url');
				Session::destroyUserSession();
				redirect($_SERVER['REQUEST_URI']);
			}
			// Now redirect back to our original page in order to remove all the "openid..." parameters in the query string
			if (isset($_GET['openid_return_to'])) redirect(urldecode($_GET['openid_return_to']));
		}
		// Oauth2 Azure AD & Table
        elseif ($auth_meth_global == 'oauth2_azure_ad_table' && ($_GET['action']??"" != 'passwordreset')) {
            // User has a session running
            $userid = $_SESSION['username'] ?? null;
            // Capture original URL so that we can redirect back to it
            if ($userid === null && !isset($_GET['code']) && !isset($_GET['state']) && !isset($_GET['iss']) && !(isset($_GET['action']) && $_GET['action'] == 'oauth2_azure_ad_login')) {
                $redirectUrl = $_SERVER['REQUEST_URI'];
                savecookie('auth_redirect_url', encrypt($redirectUrl), 120);
            }
            if (isset($userid) && $userid != null && !isset($_GET['code']) && !isset($_GET['state'])) {
                // Do nothing
            } elseif ((isset($_GET['action']) && $_GET['action'] == 'oauth2_azure_ad_login') || (isset($_GET['code']) && isset($_GET['state']))) {
                // Authenticate via azure ad
	            try {
		            $userid = strtolower(self::authenticateOauth2AzureAD());
	            } catch (\Throwable $e) {
		            // If any kind of fatal error occurs, kill the session and reload the page
		            deletecookie('auth_redirect_url');
		            Session::destroyUserSession();
		            redirect($_SERVER['REQUEST_URI']);
                }
                if ($userid !== null) {
                    // Redirect back to original URL, else fallback to REDCap Home Page
                    if (isset($_COOKIE['auth_redirect_url'])) {
                        $redirectUrl = decrypt($_COOKIE['auth_redirect_url']);
                        deletecookie('auth_redirect_url');
                        redirect($redirectUrl);
                    }
                    // Remove code and state params from query string and reload page
                    $parsed = parse_url($_SERVER['REQUEST_URI']);
                    $query = $parsed['query'];
                    parse_str($query, $params);
                    unset($params['code'], $params['state']);
                    redirect($parsed['path'].(empty($params) ? "" : "?".http_build_query($params)));
                }
            } elseif (isset($_POST['redcap_login_a38us_09i85']) && isset($_POST['username']) && isset($_POST['password'])) {
                // Validate table-based users
                if (self::isTableUser($_POST['username'])) {
                    $userid = self::tableBasedLogin();
                }
                if (!$userid) {
                    // Display error message that table-based login failed
                    print  '<!DOCTYPE HTML>' . "\n"; // Quick fix to prevent browser using Quirks Mode
                    print   RCView::div(array('class'=>'red','style'=>'max-width:100%;width:100%;font-weight:bold;'),
                        RCView::fa("fa-solid fa-circle-exclamation text-danger me-1") .
                        RCView::tt("config_functions_128") // ERROR: You entered an invalid user name or password!
                    );
                    ?><script type="text/javascript">setTimeout(function(){ document.getElementById('rc-login-form').style.display='block'; },1000)</script><?php
                    loginFunction();
                }
            } else if (isset($userid) && $userid != null && (isset($_GET['type']) && strcasecmp($_GET['type'], 'duo')===0) && isset($_GET['state'])) {
				// do nothing: user has a session running (is authenticated) and DUO 2FA process is going on
            } else {
                // Display error message that login failed
                if (isset($_REQUEST['error'])) {
                    $desc = isset($_REQUEST['error_description']) ? rawurldecode(urldecode($_REQUEST['error_description'])) : 'Unknown login error';
                    print  '<!DOCTYPE HTML>' . "\n"; // Quick fix to prevent browser using Quirks Mode
                    print   RCView::div(array('class'=>'red','style'=>'max-width:100%;width:100%;font-weight:bold;'),
                        RCView::img(array('src'=>'exclamation.png')) .
                        "{$lang['global_01']}{$lang['colon']} ".strip_tags($desc)
                    );
                }
                loginFunction();
            }
        }
		// Error was made in Control Center for authentication somehow
		elseif ($auth_meth_global == '') {
			if ($userid == '') {
				// If user is navigating directing to a project page but hasn't created their account info yet, redirect to home page.
				redirect(APP_PATH_WEBROOT_FULL);
			} else {
				// Project has no authentication somehow, which needs to be fixed in the Control Center.
				exit("{$lang['config_functions_20']}
					  <a target='_blank' href='". APP_PATH_WEBROOT . "ControlCenter/edit_project.php?project=".PROJECT_ID."'>REDCap {$lang['global_07']}</a>.");
			}
		}
		// Table-based and/or LDAP authentication
		else {
            $userid = self::tableBasedLogin();
		}

		EhrRedirectManager::detectEhrRedirect();

		// Reset autoload function in case one of the authentication frameworks changed it
		spl_autoload_register($rc_autoload_function);

		// If $userid is somehow blank (e.g., authentication server is down), then prevent from accessing.
		if ($userid === null || trim($userid) == '')
		{
			// If using Shibboleth authentication and user is on API Help page but somehow lost their username
			// (or can't be used in /api directory due to Shibboleth setup), then just redirect to the target page itself.
			if ($auth_meth_global == 'shibboleth' && strpos(PAGE_FULL, '/api/help/index.php') !== false) {
				redirect(APP_PATH_WEBROOT . "API/help.php");
			}
			// Display error message
			$objHtmlPage = new HtmlPage();
			$objHtmlPage->addExternalJS(APP_PATH_JS . "base.js");
			$objHtmlPage->addStylesheet("home.css", 'screen,print');
			$objHtmlPage->PrintHeader();
			print RCView::br() . RCView::br()
				. RCView::errorBox($lang['config_functions_82']." <a href='mailto:$homepage_contact_email'>$homepage_contact</a>{$lang['period']}")
				. RCView::button(array('onclick'=>"window.location.href='".APP_PATH_WEBROOT_FULL."index.php?logout=1';"), "Try again");
			$objHtmlPage->PrintFooter();
			exit;
		}

		// LOGOUT: Check if need to log out
		self::checkLogout();

		// If user just logged in successfully (assuming we got this far with certain expected parameters in place), then set flag to be referenced later in this request
		System::$userJustLoggedIn = (isset($_POST['redcap_login_a38us_09i85']) && isset($_POST['username']) && isset($_POST['password']) && isset($_SESSION['username']));

		// USER AllowList: If using external auth and user allowlist is enabled, the validate user as in allowlist
		if ($enable_user_allowlist && $auth_meth_global != 'none' && $auth_meth_global != 'table')
		{
			// They're an external auth user, so make sure they're in the allowlist
			if (!self::isTableUser($userid))
			{
                $sql = "select 1 from redcap_user_allowlist where username = '" . db_escape($userid) . "'";
				$inAllowlist = db_num_rows(db_query($sql));
				// If not in allowlist, then give them error page
                if (!$inAllowlist)
				{
					// Give notice that user cannot access REDCap
					$objHtmlPage = new HtmlPage();
					$objHtmlPage->addExternalJS(APP_PATH_JS . "base.js");
					$objHtmlPage->addStylesheet("home.css", 'screen,print');
					$objHtmlPage->PrintHeader();
					print  "<div class='red' style='margin:40px 0 20px;padding:20px;'>
								{$lang['config_functions_78']} \"<b>$userid</b>\"{$lang['period']}
								{$lang['config_functions_79']} <a href='mailto:$homepage_contact_email'>$homepage_contact</a>{$lang['period']}
							</div>
							<button onclick=\"window.location.href='".APP_PATH_WEBROOT_FULL."index.php?logout=1';\">Go back</button>";
					$objHtmlPage->PrintFooter();
					exit;
				}
			}
		}

		// If logging in, update Last Login time in user_information table
		// (but NOT if they are suspended - could be confusing if last login occurs AFTER suspension)
		if (isset($_POST['redcap_login_a38us_09i85']))
		{
			self::setUserLastLoginTimestamp($userid);
		}

		// If just logged in, redirect back to same page to avoid $_POST conflict on certain pages.
		// Do NOT simply redirect if user lost their session when saving data so that their data will be resurrected.
		if (isset($_POST['redcap_login_a38us_09i85']) && !isset($_POST['redcap_login_post_encrypt_e3ai09t0y2']))
		{
			// Set URL of redirect-to page
			$url = $_SERVER['REQUEST_URI'];
			// If user is logging into main Home page, then redirect them to My Projects page if they've had access to REDCap for > 7 days
			if ($_SERVER['REQUEST_URI'] == APP_PATH_WEBROOT_PARENT || $_SERVER['REQUEST_URI'] == APP_PATH_WEBROOT_PARENT."index.php") {
				// Get user info
				$row = User::getUserInfo($userid);
				// Was their first visit > 7 days ago?
				if ($row['user_firstvisit'] != "" && (time() - strtotime($row['user_firstvisit']))/3600/24 > 7) {
					// Set redirect URL as My Projects page
					$url = APP_PATH_WEBROOT_PARENT."index.php?action=myprojects";
				}
			}
			// Redirect to same page
			redirect($url);
		}

		// CHECK USER INFO: Make sure that we have the user's email address and name in redcap_user_information. If not, prompt user for it.
		if (!defined("PAGE") || (PAGE != "Profile/user_info_action.php" && PAGE != "Authentication/password_reset.php")) {
			// Set super_user default value
			$super_user = $account_manager = 0;
			// Get user info
			$row = User::getUserInfo($userid);
			// If user has no email address or is not in user_info table, then prompt user for their name and email
			if (empty($row) || $row['user_email'] == "" || ($row['user_email'] != "" && $row['email_verify_code'] != "")) {
				// Prevent CRSF attacks by checking a custom token
				System::checkCsrfToken();
				// REDCap Hook injection point: Pass PROJECT_ID constant (if defined).
				Hooks::call('redcap_every_page_before_render');
				// Prompt user for values
				include APP_PATH_DOCROOT . "Profile/user_info.php";
				exit;
			} else {
				// Define user's name and email address for use throughout the application
				$ui_id 	= $row['ui_id'];
				$user_email 	= $row['user_email'];
				$user_phone 	= $row['user_phone'];
				$user_phone_sms 	= $row['user_phone_sms'];
				$user_firstname = $row['user_firstname'];
				$user_lastname 	= $row['user_lastname'];
				$super_user 	= $row['super_user'];
				$account_manager 	= $row['account_manager'];
				$access_system_config 	= $row['access_system_config'];
				$access_system_upgrade 	= $row['access_system_upgrade'];
				$access_external_module_install 	= $row['access_external_module_install'];
				$access_admin_dashboards 	= $row['access_admin_dashboards'];
				$admin_rights 	= $row['admin_rights'];
				$user_firstactivity = $row['user_firstactivity'];
				$user_lastactivity = $row['user_lastactivity'];
				$user_firstvisit = $row['user_firstvisit'];
				$user_lastlogin = $row['user_lastlogin'];
				$user_access_dashboard_view = $row['user_access_dashboard_view'];
				$allow_create_db 	= $super_user ? 1 : $row['allow_create_db'];
				$datetime_format 	= $row['datetime_format'];
				$number_format_decimal = $row['number_format_decimal'];
				$csv_delimiter = $row['csv_delimiter'];
				$ui_state = ($row['ui_state'] == "") ? array() : unserialize($row['ui_state'], ['allowed_classes'=>false]);
				// If thousands separator is blank, then assume a space (since MySQL cannot do a space for an ENUM data type)
				$number_format_thousands_sep = ($row['number_format_thousands_sep'] == 'SPACE') ? ' ' : $row['number_format_thousands_sep'];
				// Do not let the secondary/tertiary emails be set unless they have been verified first
				$user_email2 	= ($row['user_email2'] != '' && $row['email2_verify_code'] == '') ? $row['user_email2'] : "";
				$user_email3 	= ($row['user_email3'] != '' && $row['email3_verify_code'] == '') ? $row['user_email3'] : "";
				$messaging_email_preference = $row['messaging_email_preference'];
				$messaging_email_urgent = $row['messaging_email_urgent_all'];
			}
			// TWO-FACTOR AUTHENTICATION: Add user's two factor auth secret hash
			if ($row['two_factor_auth_secret'] == "")
			{
				$row['two_factor_auth_secret'] = self::createTwoFactorSecret($userid);
			}
			// If we have not recorded time of user's first visit, then set it
			if ($row['user_firstvisit'] == "")
			{
				User::updateUserFirstVisit($userid);
			}
			// If we have not recorded time of user's last login, then set it based upon first page view of current session
			if ($row['user_lastlogin'] == "")
			{
				self::setLastLoginTime($userid);
			}
			// Check if user account has been suspended
			if ($row['user_suspended_time'] != "")
			{
				// Give notice that user cannot access REDCap
				global $homepage_contact_email, $homepage_contact;
				$objHtmlPage = new HtmlPage();
				$objHtmlPage->addExternalJS(APP_PATH_JS . "base.js");
				$objHtmlPage->addStylesheet("home.css", 'screen,print');
				$objHtmlPage->PrintHeader();
				$user_firstlast = ($user_firstname == "" && $user_lastname == "") ? "" : " (<b>$user_firstname $user_lastname</b>)";
				print  "<div class='red' style='margin:40px 0 20px;padding:20px;'>
							{$lang['config_functions_75']} \"<b>$userid</b>\"{$user_firstlast}{$lang['period']}
							{$lang['config_functions_76']} <a href='mailto:$homepage_contact_email'>$homepage_contact</a>{$lang['period']}
						</div>
						<button onclick=\"window.location.href='".APP_PATH_WEBROOT_FULL."index.php?logout=1';\">Go back</button>";
				$objHtmlPage->PrintFooter();
				exit;
			}
		}

		//Define user variables
		defined("USERID") or define("USERID", strtolower($userid));
		defined("UI_ID") or define("UI_ID", $ui_id);
        defined("SUPER_USER") or define("SUPER_USER", $super_user);
        defined("ACCOUNT_MANAGER") or define("ACCOUNT_MANAGER", $account_manager);
        defined("ACCESS_SYSTEM_CONFIG") or define("ACCESS_SYSTEM_CONFIG", $access_system_config);
        defined("ACCESS_SYSTEM_UPGRADE") or define("ACCESS_SYSTEM_UPGRADE", $access_system_upgrade);
        defined("ACCESS_EXTERNAL_MODULE_INSTALL") or define("ACCESS_EXTERNAL_MODULE_INSTALL", $access_external_module_install);
        defined("ADMIN_RIGHTS") or define("ADMIN_RIGHTS", $admin_rights);
        defined("ACCESS_ADMIN_DASHBOARDS") or define("ACCESS_ADMIN_DASHBOARDS", $access_admin_dashboards);
        defined("ACCESS_CONTROL_CENTER") or define("ACCESS_CONTROL_CENTER", (SUPER_USER || ACCOUNT_MANAGER || ACCESS_SYSTEM_CONFIG || ACCESS_SYSTEM_UPGRADE || ACCESS_EXTERNAL_MODULE_INSTALL || ADMIN_RIGHTS || ACCESS_ADMIN_DASHBOARDS));
		$GLOBALS['userid'] = strtolower($userid);
		$GLOBALS['super_user'] = $super_user;
		$GLOBALS['account_manager'] = $account_manager;
		$GLOBALS['user_email'] = $user_email;
		$GLOBALS['user_email2'] = $user_email2;
		$GLOBALS['user_email3'] = $user_email3;
		$GLOBALS['user_phone'] = $user_phone;
		$GLOBALS['user_phone_sms'] = $user_phone_sms;
		$GLOBALS['user_firstname'] = $user_firstname;
		$GLOBALS['user_lastname'] = $user_lastname;
		$GLOBALS['user_firstactivity'] = $user_firstactivity;
		$GLOBALS['user_access_dashboard_view'] = $user_access_dashboard_view;
		$GLOBALS['allow_create_db'] = $allow_create_db;
		$GLOBALS['datetime_format'] = $datetime_format;
		$GLOBALS['number_format_decimal'] = $number_format_decimal;
		$GLOBALS['number_format_thousands_sep'] = $number_format_thousands_sep;
		$GLOBALS['csv_delimiter'] = $csv_delimiter;
		$GLOBALS['ui_state'] = $ui_state;
		$GLOBALS['messaging_email_preference'] = $messaging_email_preference;
		$GLOBALS['messaging_email_urgent'] = $messaging_email_urgent;

		// If user is being impersonated
		if (UserRights::isImpersonatingUser()) {
		    $userInfoImpersonated = User::getUserInfo(UserRights::getUsernameImpersonating());
			$GLOBALS['allow_create_db'] = $userInfoImpersonated['allow_create_db'] ?? 0;
		}

		## DEAL WITH COOKIES
		// Remove authchallenge cookie created by Pear Auth because it's not necessary
		if (isset($_COOKIE['authchallenge'])) {
			unset($_COOKIE['authchallenge']);
			deletecookie('authchallenge');
		}

		## TWO FACTOR AUTHENTICATION
		// Enforce 2FA here if enabled and user has not authenticated via two factor
		if (self::checkToDisplayTwoFactorLoginPage()) {
			// Display the two-factor login screen
			self::renderTwoFactorLoginPage();
		}
		// If a user is inside a "Force 2FA" project and hasn't done a 2FA login during this session (because the trust cookie was used).
		if (self::enforceTwoFactorByManualForceProject()) {
			// Display the two-factor login screen
			self::renderTwoFactorLoginPage(true);
		}
		// If user bypassed the 2FA login (due to cookie or IP), then make sure their 1-step login
		// got logged (because we didn't log it when it happened prior to 2FA detection)
		if ($auth_meth_global != 'none' && $two_factor_auth_enabled && !isset($_SESSION['two_factor_auth'])
			&& !isset($_SESSION['two_factor_auth_bypass_login']) && !in_array(PAGE, self::getTwoFactorAllowlistedPages())) {
			// Set flag so that we know we logged their 1-step login
			$_SESSION['two_factor_auth_bypass_login'] = "1";
			// Log the login
			Logging::logPageView("LOGIN_SUCCESS", USERID, null, true);
		}
	}

    static function tableBasedLogin() {
		global $auth_meth_global, $app_name, $username, $password, $hostname, $db, $institution, $double_data_entry,
			   $project_contact_name, $autologout_timer, $lang, $isMobileDevice, $password_reset_duration, $enable_user_allowlist,
			   $homepage_contact_email, $homepage_contact, $isAjax, $rc_autoload_function, $two_factor_auth_enabled;
			// Set DSN arrays for Table-based auth and/or LDAP auth
			self::setDSNs();
			// This variable sets the timeout limit if server activity is idle
			$autologout_timer = ($autologout_timer == "") ? 0 : $autologout_timer;
			// In case of users having characters in password that were stripped out earlier, restore them (LDAP only)
			if (isset($_POST['password'])) $_POST['password'] = html_entity_decode($_POST['password'], ENT_QUOTES);
			// Check if user is logged in
			self::checkLogin("", $auth_meth_global);

			// Set username variable passed from PEAR Auth
			$userid = strtolower($_SESSION['username']);
            // Are we using an "X & Table-based" authentication method?
            $usingXandTableBasedAuth = !($auth_meth_global == "table" || strpos($auth_meth_global, "table") === false);
			// Check if table-based user has a temporary password. If so, direct them to page to set it.
			if ($auth_meth_global == "table" || ($usingXandTableBasedAuth && $auth_meth_global != "aaf_table") || ($auth_meth_global == "aaf_table" && !isset($_SESSION['tli'])))
			{
				$q = db_query("select * from redcap_auth where username = '".db_escape($userid)."'");
				$isTableBasedUser = db_num_rows($q);
				// User is table-based user
				if ($isTableBasedUser)
				{
					// Get values from auth table
					$temp_pwd 					= db_result($q, 0, 'temp_pwd');
					$password_question 			= db_result($q, 0, 'password_question');
					$password_answer 			= db_result($q, 0, 'password_answer');
					$password_question_reminder = db_result($q, 0, 'password_question_reminder');
					$legacy_hash 				= db_result($q, 0, 'legacy_hash');
					$hashed_password			= db_result($q, 0, 'password');
					$password_salt 				= db_result($q, 0, 'password_salt');
					$password_reset_key			= db_result($q, 0, 'password_reset_key');

					// If using table-based auth and enforcing password reset after X days, check if need to reset or not
					if (isset($_POST['redcap_login_a38us_09i85']) && !empty($password_reset_duration))
					{
						// Also add to auth_history table
						$sql = "select timestampdiff(MINUTE,timestamp,'".NOW."')/60/24 as daysExpired,
								timestampadd(DAY,$password_reset_duration,timestamp) as expirationTime from redcap_auth_history
								where username = '$userid' order by timestamp desc limit 1";
						$q = db_query($sql);
						$daysExpired = db_result($q, 0, "daysExpired");
						$expirationTime = db_result($q, 0, "expirationTime");

						// If the number of days expired has passed, then redirect them to the password reset page
						if (db_num_rows($q) > 0 && $daysExpired > $password_reset_duration)
						{
							// Set the temp password flag to prompt them to enter new password
							db_query("UPDATE redcap_auth SET temp_pwd = 1 WHERE username = '$userid'");
							// Redirect to password reset page with flag set
							redirect(APP_PATH_WEBROOT . "Authentication/password_reset.php?msg=expired");
						}
						// If within 7 days of expiring, then give a notice on next page load.
						elseif ($daysExpired > $password_reset_duration-7)
						{
							// Put expiration time in session in order to prompt user on next page load
							$_SESSION['expire_time'] = DateTimeRC::format_ts_from_ymd($expirationTime);
						}
					}

					// PASSWORD RESET (non-email): If temporary password flag is set, then redirect to allow user to set new password
					if ($temp_pwd == '1' && PAGE != "Authentication/password_reset.php")
					{
						redirect(APP_PATH_WEBROOT . "Authentication/password_reset.php");
					}

					// UPDATE LEGACY PASSWORD HASH: If table-based user is logging in (successfully) and is using a legacy hashed password,
					// then update password to newer salted hash.
					if (isset($_POST['redcap_login_a38us_09i85']) && $legacy_hash && md5($_POST['password'].$password_salt) == $hashed_password)
					{
						// Generate random salt for this user
						$new_salt = self::generatePasswordSalt();
						// Create the one-way hash for this new password
						$new_hashed_password = self::hashPassword($_POST['password'], $new_salt);
						// Update a table-based user's hashed password and salt
						self::setUserPasswordAndSalt($userid, $new_hashed_password, $new_salt);
					}
				}
			}
        return $userid;
    }

	//***<AAF Modification>*** Adds new user who has come through AAF
	static function recordNewAAFUser($un1,$aafMap)
	{
		global $allow_create_db_default, $default_datetime_format, $default_number_format_decimal, $default_number_format_thousands_sep, $aafAllowLocalsCreateDB,$aafDisplayOnEmailUsers,$aafScopeTarget;
		$db = new RedCapDB();
		$ui_id=null;
		$_POST['username'] = trim(strip_tags(label_decode($un1)));
		$_POST['user_firstname'] = trim(strip_tags(label_decode($aafMap->givenname)));
		$_POST['user_lastname'] = trim(strip_tags(label_decode($aafMap->surname)));
		$_POST['user_email'] = trim(strip_tags(label_decode($aafMap->mail)));
		$_POST['user_inst_id'] = $aafMap->organizationname;
        $isLocal=0;
        // Determine if user is local
        if(isset($aafScopeTarget) && isset($aafMap->edupersonscopedaffiliation)){
            $delimiters = [',', '\n'];
            $scopeTargets = preg_split('/[' . implode('', $delimiters) . ']+/', $aafScopeTarget);
            $scopeTargets = array_map('trim', $scopeTargets);
            $edupersonscopedaffiliation = explode(';', $aafMap->edupersonscopedaffiliation);
            $edupersonscopedaffiliation = array_map('trim', $edupersonscopedaffiliation);
            $isLocal = (!empty(array_intersect($scopeTargets, $edupersonscopedaffiliation))) ? 1 : 0; // Mark as a local if there is a common element in both the aafScopeTarget and eduPersonScopedAffiliation arrays
        }
        $allow_create_db=($isLocal && $aafAllowLocalsCreateDB=="on") ? 1 : $allow_create_db_default; // Locals can create DB, otherwise, default user setting
        $display_on_email_users=($isLocal && $aafDisplayOnEmailUsers=="locals" || $aafDisplayOnEmailUsers=="on") ? 1 : 0;
        $pass = generateRandomHash(8);
		$_POST['user_email2'] = trim(strip_tags(label_decode($_POST['user_email2'])));
		$_POST['user_email3'] = trim(strip_tags(label_decode($_POST['user_email3'])));
		$_POST['user_inst_id'] = trim(strip_tags(label_decode($_POST['user_inst_id'])));
		$_POST['user_comments'] = trim(strip_tags(label_decode($_POST['user_comments'])));
		if ($_POST['user_comments'] == '') $_POST['user_comments'] = null;
		$_POST['user_expiration'] = trim(strip_tags(label_decode($_POST['user_expiration'])));
		$_POST['user_expiration'] = ($_POST['user_expiration'] == '') ? NULL : DateTimeRC::format_ts_to_ymd($_POST['user_expiration']).':00';
		$_POST['user_sponsor'] = ($_POST['user_sponsor'] == '') ? NULL : trim(strip_tags(label_decode($_POST['user_sponsor'])));
		$_POST['user_phone'] = ($_POST['user_phone'] == '') ? NULL : preg_replace("/[^0-9,]/", '', $_POST['user_phone']);
		$_POST['user_phone_sms'] = ($_POST['user_phone_sms'] == '') ? NULL : preg_replace("/[^0-9,]/", '', $_POST['user_phone_sms']);
		if (!isset($_POST['messaging_email_preference'])) $_POST['messaging_email_preference'] = '4_HOURS';
		if (!isset($_POST['messaging_email_urgent_all']) || $_POST['messaging_email_urgent_all'] != '1') $_POST['messaging_email_urgent_all'] = '0';
		$_POST['api_token_auto_request'] = (isset($_POST['api_token_auto_request']) && $_POST['api_token_auto_request'] == "on") ? 1 : 0;
		$isAaf=1;
		$sql = $db->saveUser($ui_id, $_POST['username'], $_POST['user_firstname'],
				$_POST['user_lastname'], $_POST['user_email'], $_POST['user_email2'], $_POST['user_email3'], $_POST['user_inst_id'],
				$_POST['user_expiration'], $_POST['user_sponsor'], $_POST['user_comments'], $allow_create_db, $pass,
				$default_datetime_format, $default_number_format_decimal, $default_number_format_thousands_sep, $display_on_email_users,
				$_POST['user_phone'], $_POST['user_phone_sms'], $_POST['messaging_email_preference'], $_POST['messaging_email_urgent_all'],
				$_POST['api_token_auto_request'],$isAaf);
		if (count($sql) === 0) {
			exit('Redcap could not add the user: '.$_POST['user_firstname'].''.$_POST['user_lastname'].'. mail: '.$_POST['user_email'].' '.$_POST['username']);
		} else {
            User::notifyAdminNewUser($_POST['username']);
            User::notifyNewUserWelcomeEmail($_POST['username']);
            Logging::logEvent(implode(";\n", $sql),"redcap_auth","MANAGE",$_POST['username'],"user = '{$_POST['username']}'","Create user via AAF","","admin");
        }
	}
	
	public static function setAAFCsrfToken($userid)
	{
		 global $salt;

		 // Initialize array if does not exist
		 if (!isset($_SESSION['redcap_csrf_token']) || !is_array($_SESSION['redcap_csrf_token'])) {
			  $_SESSION['redcap_csrf_token'] = array();
		 }
		 // If more than X number of elements exist in array, then remove the oldest
		 $maxTokens = 50;
		 if (count($_SESSION['redcap_csrf_token']) > $maxTokens) {
			   array_shift($_SESSION['redcap_csrf_token']);
		 }
		 // Generate token and put in array
		 $_SESSION['redcap_csrf_token'][NOW] = md5($salt . NOW . $userid);
		 $_POST['redcap_csrf_token']=$_SESSION['redcap_csrf_token'][NOW];
	}
	//***</AAF Modification>***

	// Set allowlist of pages where 2FA should not be implemented or checked
	public static function getTwoFactorAllowlistedPages()
	{
		return array(
			"Authentication/generate_qrcode.php", "Authentication/two_factor_verify_code.php", "Authentication/two_factor_send_code.php",
			"ProjectGeneral/keep_alive.php", "Authentication/two_factor_check_login_status.php", "Authentication/two_factor_check_duo_status.php",
			"Authentication/password_reset.php", "Profile/user_info_action.php", "twoFA/index.php", "twoFA/callback.php"
		);
	}


	// Determine if we need to display the 2FA login page. Return true if we do.
	public static function checkToDisplayTwoFactorLoginPage()
	{
		global $auth_meth_global, $two_factor_auth_enabled;
		// Return true if we need to display the 2FA login page
		return ($auth_meth_global != 'none' && $two_factor_auth_enabled && !isset($_SESSION['two_factor_auth'])
				// If not on an allowlisted page
				&& (defined("PAGE") && !in_array(PAGE, self::getTwoFactorAllowlistedPages()))
                // If using the "Enforce two-factor authentication ONLY for Table-based users?" setting and using an "X & Table-based" auth,
                // make sure we enforce 2FA if user is Table-based.
                && self::enforceTwoFactorTableBasedUserOnly()
				// If using IP ranges to enforce 2FA, then check IP in acceptable ranges.
				&& self::enforceTwoFactorByIP()
				// If a user belongs to a 2FA Exempt project, then make sure we enforce 2FA if on My Profile page OR if in a non-exempt project.
				&& self::enforceTwoFactorByExemptProject()
        );
	}


	// If using the "Enforce two-factor authentication ONLY for Table-based users?" setting and using an "X & Table-based" auth,
	// make sure we enforce 2FA if user is Table-based. Return true if two_factor_auth_enforce_table_users_only=0 or if using "table" authentication or
    // if using "X & Table-based" auth AND user is NOT a Table-based user.
	public static function enforceTwoFactorTableBasedUserOnly()
	{
		global $auth_meth_global, $two_factor_auth_enforce_table_users_only;
        // Return true if setting is not even enabled
        if ($two_factor_auth_enforce_table_users_only == '0') return true;
        // Return true if using Table-based auth because this setting doesn't even apply to that auth method
        if ($auth_meth_global == "table") return true;
        // Are we using an "X & Table-based" authentication method?
        $usingXandTableBasedAuth = !($auth_meth_global == "table" || strpos($auth_meth_global, "table") === false);
		// Return true if using an "X & Table-based" authentication method AND user is a Table-based user
		return ($usingXandTableBasedAuth && User::isTableUser(USERID));
	}


	// If a user belongs to a 2FA Exempt project, then make sure we enforce 2FA
	// if on My Profile page OR if in a non-exempt project. Return true if user does NOT have access to any exempt projects
	// OR return true if user DOES have access to an exempt project and is currently on My Profile page OR in a non-exempt project.
	public static function enforceTwoFactorByExemptProject()
	{
		global $two_factor_exempt_project;
		// If user is currently inside a 2FA Exempt project, then return false (do NOT need to force 2FA here)
		if (isset($_GET['pid']) && $two_factor_exempt_project) return false;
		// Return true if user does NOT have access to any 2FA Exempt projects
		if (!self::hasAccessTwoFactorExemptProject()) return true;
		// Detect if in the Control Center
		$isControlCenter = (strpos(PAGE, "ControlCenter/") === 0);
		// Return true if on My Profile page OR a Control Center page OR if in a non-exempt project
		return ((isset($_GET['pid']) && !$two_factor_exempt_project) || $isControlCenter || PAGE == 'Profile/user_profile.php');
	}


	// If a user is inside a "Force 2FA" project, in which 2FA login is ALWAYS enforced on that session even if the Trust Cookie is used
	// to bypass the 2FA login, then make sure we enforce 2FA login. Return true if user does NOT have access to any exempt projects
	// OR return true if user DOES have access to an exempt project and is currently on My Profile page OR in a non-exempt project.
	public static function enforceTwoFactorByManualForceProject()
	{
		global $two_factor_force_project;
		// If user didn't bypass login via cookie, then they must've really done 2FA login, so return false.
		if (!isset($_SESSION['two_factor_auth_cookie_login'])) return false;
		// If user is currently inside a 2FA Force project, then return true to trigger 2FA login page.
		if (isset($_GET['pid']) && $two_factor_force_project) {
			// Also remove the flag from the session so we don't come into this function again on next page load.
			unset($_SESSION['two_factor_auth_cookie_login']);
			// Trigger 2FA login page.
			return true;
		} else {
			return false;
		}
	}


	// If a user belongs to at least one 2FA Exempt project, then return true
	// (exclude "deleted" projects and include inactive/archived projects)
	public static function hasAccessTwoFactorExemptProject()
	{
		$sql = "select 1 from redcap_projects p, redcap_user_rights u
				where u.project_id = p.project_id and u.username = '".db_escape(USERID)."'
				and p.date_deleted is null and p.completed_time is null and p.two_factor_exempt_project = 1 limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}


	// Create user's two factor authentication secret
	public static function createTwoFactorSecret($userid)
	{
		// Generate secret value
		$ga = new GoogleAuthenticator();
		$two_factor_auth_secret = $ga->createSecret(32); // Set length of 32 characters to get a secret length of 160 bits, which meets security standards
		// Update table with secret value
		$sql = "update redcap_user_information set two_factor_auth_secret = '".db_escape($two_factor_auth_secret)."'
				where username = '".db_escape($userid)."'";
		$q = db_query($sql);
		return $two_factor_auth_secret;
	}


	// Make sure that the 2FA verify code endpoint is not called more than X times per minute
	public static function checkRateLimitTwoFactorCodeVerify()
	{
        $oneMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-1,date("s"),date("m"),date("d"),date("Y")));
        $sql = "select count(1) from redcap_log_view where page = 'Authentication/two_factor_verify_code.php' and ts >= ? and user = ?";
        $q = db_query($sql, [$oneMinAgo, USERID]);
        $total_hits = db_result($q, 0);
        return ($total_hits <= Authentication::MFA_RATE_LIMIT);
	}


	// Verify a user's two-factor auth verification code that they submittted
	public static function verifyTwoFactorCode($code)
	{
		// Get user info
		$user_info = User::getUserInfo(USERID);
		// Get code expiration time (a user-level feature with 2-min default)
		$code_expiration = (isset($user_info['two_factor_auth_code_expiration']) && is_numeric($user_info['two_factor_auth_code_expiration']))
						   ? $user_info['two_factor_auth_code_expiration'] : 2;
		// Verify the code and return boolean regarding success
		$ga = new GoogleAuthenticator();
		return $ga->verifyCode($user_info['two_factor_auth_secret'], $code, $code_expiration * 2);    // 2 = 2*30sec clock tolerance
	}


	// Two factor: After successfully logging in via the second factor, store this in sessin and note this in log_view table
	public static function twoFactorLoginSuccess($twoFactorMethod=null)
	{
		global $two_factor_auth_enabled;
		// Verify that we're using 2FA
		if (!$two_factor_auth_enabled) return;
		// Add to session
		$_SESSION['two_factor_auth'] = "1";
		// Add to log_view table and denote which 2FA method was used
		Logging::logPageView("LOGIN_SUCCESS", USERID, $twoFactorMethod);
		// Remove flag now that we've logged the login success
		unset($_SESSION['two_factor_auth_todo_login_success']);
	}


	// Create 2FA trust cookie with expiration of $two_factor_auth_trust_period_days
	public static function twoFactorCreateTrustCookie($cookie_value)
	{
		global $two_factor_auth_trust_period_days, $two_factor_auth_trust_period_days_alt;
		// Because a single cookie can contain different user's info with different trust periods, set cookie expiration
		// as max of the primary and secondary.
		$max_cookie_trust_period_days = max(array($two_factor_auth_trust_period_days, $two_factor_auth_trust_period_days_alt));
		// Create cookie
		setcookie('two_factor_auth_trust', $cookie_value, time()+($max_cookie_trust_period_days*3600*24), '/', '');
		// Also set cookie to default to the "private computer" security option when doing 2-step login. Remember this option for 1 year.
		setcookie('two_factor_auth_trust_private', 'private', time()+(365*3600*24), '/', '');
	}


	// Determine if we should set the value of the 2FA trust cookie
	public static function twoFactorSetTrustCookie($two_factor_auth_trust=null)
	{
		// Obtain the user's trust period (if they have one)
		$user_trust_period_days = self::getUserTwoFactorTrustPeriodDays();
		// Set cookie?
		if (is_numeric($user_trust_period_days) && $user_trust_period_days > 0 && $two_factor_auth_trust == '1') {
			// If cookie already exists, then parse it into an array first since it contains data for possibly multiple users on this device
			$cookie_array = array();
			if (isset($_COOKIE['two_factor_auth_trust'])) {
				// Decrypt the cookie's value and unserialize the cookie to an array
				$cookie_array = unserialize(decrypt($_COOKIE['two_factor_auth_trust']), ['allowed_classes'=>false]);
				// If not an array, then return false
				if (!is_array($cookie_array)) $cookie_array = array();
			}
			// Set time for the current user
			$cookie_array[USERID] = time();
			// Add cookie to preserve the respondent's login "session" across multiple surveys in a project.
			// Set the cookie value as the current time in encrypted format (to validate it's true create time)
			self::twoFactorCreateTrustCookie(encrypt(serialize($cookie_array)));
		} elseif (isset($_COOKIE['two_factor_auth_trust_private'])) {
			// Not setting trust cookie, so make sure we delete the two_factor_auth_trust_default cookie
			unset($_COOKIE['two_factor_auth_trust_private']);
			deletecookie('two_factor_auth_trust_private');
		}
	}


	// Verify the value of the 2FA trust cookie and return true if cookie is still active.
	// If cookie is no longer active (passed trust period), then delete it.
	public static function twoFactorVerifyTrustCookie()
	{
		// Obtain the user's trust period (if they have one)
		$user_trust_period_days = self::getUserTwoFactorTrustPeriodDays();
		// Return false to force two factor auth login if not using the trust cookie
		if (!(is_numeric($user_trust_period_days) && $user_trust_period_days > 0)) return false;
		// Return false to force two factor auth login if user has no trust cookie
		if (!isset($_COOKIE['two_factor_auth_trust'])) return false;
		// Decrypt the cookie's value and unserialize the cookie to an array
		$cookie_array = unserialize(decrypt($_COOKIE['two_factor_auth_trust']), ['allowed_classes'=>false]);
		// If not an array, then return false
		if (!is_array($cookie_array) || !isset($cookie_array[USERID])) return false;
		// Now get the cookie's time for the current user
		$cookie_time = $cookie_array[USERID];
		// Get current Unix timestamp
		$current_time = time();
		// Make sure cookie's time is numeric (for authenticity)
		if (!is_numeric($cookie_time) || $cookie_time > $current_time) return false;
		// Calculate cookie's age
		$cookie_age = ($current_time - $cookie_time);
		// Return false to force two factor auth login if cookie's age is greater than the max trust period
		return ($cookie_age < ($user_trust_period_days*3600*24));
	}


	// Delete a user's value in the 2FA trust cookie, but do NOT delete the whole cookie
	// because it can be used for multiple users on the current device.
	public static function twoFactorDeleteTrustCookie()
	{
		// Return false to force two factor auth login if user has no trust cookie
		if (!isset($_COOKIE['two_factor_auth_trust'])) return false;
		// Decrypt the cookie's value and unserialize the cookie to an array
		$cookie_array = unserialize(decrypt($_COOKIE['two_factor_auth_trust']), ['allowed_classes'=>false]);
		// Go ahead and delete the cookie (we'll re-create it below if it needs to have its value modified)
		unset($_COOKIE['two_factor_auth_trust']);
		deletecookie('two_factor_auth_trust');
		// If not an array, then really delete the cookie because sometime has gone wrong and its no longer usable
		if (!is_array($cookie_array)) return false;
		// Remove the user's entry from the array
		unset($cookie_array[USERID]);
		// If array is now empty, then delete the cookie
		if (empty($cookie_array)) return false;
		// Now re-add modified array back to cookie
		self::twoFactorCreateTrustCookie(encrypt(serialize($cookie_array)));
	}


	// Obtain the user's trust period (if they have one)
	public static function getUserTwoFactorTrustPeriodDays()
	{
		global 	$two_factor_auth_trust_period_days, $two_factor_auth_trust_period_days_alt, $two_factor_auth_ip_range_alt;
		// First, check if using secondary trust interval based on IP
		if (is_numeric($two_factor_auth_trust_period_days_alt) && $two_factor_auth_trust_period_days_alt > 0
			// Is user's IP in range?
			&& self::ip_in_ranges(System::clientIpAddress(), explode(",", $two_factor_auth_ip_range_alt)))
		{
			return $two_factor_auth_trust_period_days_alt;
		}
		// Now, check primary trust interval (if set)
		if (is_numeric($two_factor_auth_trust_period_days) && $two_factor_auth_trust_period_days > 0)
		{
			return $two_factor_auth_trust_period_days;
		}
		// Return '0' since neither are being used
		return '0';
	}


    // Set the user's two_factor_auth_enrolled attribute
    public static function setHasEnrolledForTwoFactorAuthentication($user, $hasEnrolled=true)
    {
        $sql = "update redcap_user_information set two_factor_auth_enrolled = '" . ($hasEnrolled ? 1 : 0) . "' 
                where username = '" . db_escape($user) ."'";
        return (db_query($sql));
    }


	// Display the two-factor login screen
	public static function renderTwoFactorLoginPage($bypassCookieCheck=false)
	{
		global 	$lang, $two_factor_auth_twilio_enabled, $isTablet, $isIOS, $isMobileDevice, $two_factor_auth_duo_enabled,
				$two_factor_auth_duo_ikey, $two_factor_auth_duo_skey, $two_factor_auth_duo_hostname, $salt, $two_factor_force_project,
				$two_factor_auth_authenticator_enabled, $two_factor_auth_email_enabled, $system_offline;
				
		// If system is offline, then stop here if a regular user
		if (!SUPER_USER && $system_offline == '1') return;

		// Check 2FA trust cookie, if exists
		if (!$bypassCookieCheck) {
			if (self::twoFactorVerifyTrustCookie()) {
				// Add login to log_view table and set session variable
				self::twoFactorLoginSuccess('2FA_COOKIE');
				// Add flag to session so that we know the user logged in via cookie for this session.
				// If user is currently inside a 2FA Force project, then don't add this to session.
				if (!(isset($_GET['pid']) && $two_factor_force_project)) {
					$_SESSION['two_factor_auth_cookie_login'] = '1';
					// Cookie was verified, so skip two factor login process.
					return;
				}
			} else {
				// Cookie was NOT verified, so make sure we delete the user's entry from the cookie, if exists
				self::twoFactorDeleteTrustCookie();
			}
		}

		// Get user info
		$user_info = User::getUserInfo(USERID);
		// If we have to phone number for user, then disallow the SMS option
		$sms_disabled = ($user_info['user_phone_sms'] == '');
		$phone_call_disabled = ($user_info['user_phone'] == '');
		// Output page with login dialog
		$HtmlPage = new HtmlPage();
		$HtmlPage->addExternalJS(APP_PATH_JS . "TwoFactorAuthLogin.js");
		$HtmlPage->PrintHeader();
		addLangToJS(array("global_53"));
		?>
		<style type="text/css">
		#pagecontainer, div.current { display: none !important; }
		table#two_factor_choices_table {
			border-bottom: 1px solid #ccc;
		}
		table#two_factor_choices_table tr td {
			border: 1px solid #ccc;
			border-bottom: 0;
			background-color:#f1f1f1;
			padding: 10px;
		}
		table#two_factor_choices_table tr td input[type="radio"] {
			vertical-align:middle;
			margin-right:22px;
		}
		</style>
		<script type="text/javascript">
		var lang2FA_01 = '<?php print js_escape($lang['system_config_343']) ?>';
		var lang2FA_02 = '<?php print js_escape(($isMobileDevice ? $lang['system_config_441'] : $lang['system_config_331'])) ?>';
		var lang2FA_03 = '<?php print js_escape($lang['system_config_347']) ?>';
		var lang2FA_04 = '<?php print js_escape($lang['system_config_846']) ?>';
		var this_page_url = '<?php print js_escape($_SERVER['REQUEST_URI']) ?>';
		var langSubmit = '<?php print js_escape($lang['survey_200']) ?>';
		</script>
		<?php
		// Integer field on MOBILE devices and TABLETS only (switch to number pad instead of regular keyboard)
		$input_type = "text";
		$input_pattern = "";
		if ($isTablet || $isMobileDevice) {
			if ($isIOS) {
				// iOS
				$input_pattern = "\d*";
			} else {
				// Android, etc.
				$input_type = "number";
			}
		}
		// Set default pre-selected option for Trust Period radio. For mobile devices, default to private.
		$two_factor_auth_trust_private_checked = (isset($_COOKIE['two_factor_auth_trust_private']) || $isMobileDevice) ? "checked" : "";
		// HTML for Twilio options
		$two_factor_auth_twilio_sms_option = $two_factor_auth_twilio_call_option = "";
		if ($two_factor_auth_twilio_enabled) {
			// SMS
			$two_factor_auth_twilio_sms_option =
				RCView::tr(array(),
					RCView::td(array('style'=>($sms_disabled ? "background-color:#ddd;" : "cursor:pointer;cursor:hand;"),
									 'class'=>($sms_disabled ? "opacity75" : ""),
									 'onclick'=>($sms_disabled ? "" : "selectTFStep1('sms');sendTFAcode('sms');")),
						RCView::div(array('style'=>'float:left;width:110px;margin-top:5px;'),
							RCView::radio(array('name'=>'two_factor_option', 'value'=>'sms', 'style'=>($sms_disabled ? "visibility:hidden;" : ""))) .
							RCView::img(array('src'=>'sms_big.png', 'style'=>'vertical-align:middle;height:44px;'))
						) .
						RCView::div(array('style'=>($isMobileDevice ? 'width:120px;' : 'width:500px;').'float:left;vertical-align:middle;'),
							($isMobileDevice
								? RCView::b($lang['system_config_336'])
								: RCView::b($lang['system_config_336'].$lang['colon']) . " " . $lang['system_config_337']
							) .
							RCView::br() .
							($sms_disabled
								? 	RCView::div(array('style'=>($isMobileDevice ? 'font-size:12px;' : '').'color:#C00000;font-size:12px;line-height:13px;'),
										RCView::b($lang['system_config_455'].$lang['colon']) . " " .
										($isMobileDevice ? $lang['system_config_454'] : $lang['system_config_477'])
									)
								:	RCView::span(array('style'=>($isMobileDevice ? 'font-size:12px;' : '').'color:#C00000;'),
										$lang['system_config_349'] . " " .
										concealPhone(formatPhone($user_info['user_phone_sms']))
									)
							)
						)
					)
				);
			// Phone Call
			$two_factor_auth_twilio_call_option =
				RCView::tr(array(),
					RCView::td(array('style'=>($phone_call_disabled ? "background-color:#ddd;" : "cursor:pointer;cursor:hand;"),
									 'class'=>($phone_call_disabled ? "opacity75" : ""),
									 'onclick'=>($phone_call_disabled ? "" : "selectTFStep1('voice');sendTFAcode('voice');")),
						RCView::div(array('style'=>'float:left;width:110px;'),
							RCView::radio(array('name'=>'two_factor_option', 'value'=>'voice', 'style'=>($phone_call_disabled ? "visibility:hidden;" : ""))) .
							RCView::img(array('src'=>'phone_big.png', 'style'=>'vertical-align:middle;'))
						) .
						RCView::div(array('style'=>($isMobileDevice ? 'width:120px;' : 'width:500px;').($phone_call_disabled ? '' : 'margin-top:5px;').'float:left;vertical-align:middle;'),
							($isMobileDevice
								? RCView::b($lang['system_config_474'])
								: RCView::b($lang['system_config_474'].$lang['colon']) . " " . $lang['system_config_475']
							) .
							RCView::br() .
							($phone_call_disabled
								? 	RCView::div(array('style'=>($isMobileDevice ? 'font-size:12px;' : '').'color:#C00000;font-size:12px;line-height:13px;'),
										RCView::b($lang['system_config_455'].$lang['colon']) . " " .
										($isMobileDevice ? $lang['system_config_454'] : $lang['system_config_477'])
									)
								:	RCView::span(array('style'=>($isMobileDevice ? 'font-size:12px;' : '').'color:#C00000;'),
										$lang['system_config_476'] . " " .
										concealPhone(formatPhone($user_info['user_phone']))
									)
							)
						)
					)
				);
		}

		// Obtain the user's trust period (if they have one)
		$user_trust_period_days = self::getUserTwoFactorTrustPeriodDays();
		if($two_factor_auth_duo_enabled) {
			// get the authenticated user
			$currentUser = @$_SESSION['username'] ?: '';
			$currentPage = $_SERVER['REQUEST_URI'];
			// make a custom session for duo
			$duoSessionID = Duo::makeSession($currentUser, $currentPage);
		}
		// TWO FACTOR LOGIN DIALOG
		print
				// Main dialog (choose two factor method to use)
				RCView::div(array('id'=>'two_factor_login_dialog', 'class'=>'simpleDialog', 'style'=>'font-size:14px;'),
					// Instructions
					RCView::div(array('style'=>($isMobileDevice ? 'font-size:13px;' : 'margin-bottom:5px;')),
						($isMobileDevice ? $lang['system_config_440'] : $lang['system_config_432'])
					) .
					// TRUST PERIOD (if enabled): Give choice to remember device's two-factor login
					((!is_numeric($user_trust_period_days) || $user_trust_period_days == '0') ? '' :
						// Name the form "duo_form" so that Duo will also utilize it when it gets passed in its iframe's Post request.
						// But we'll use this for all 2FA methods despite the form name.
						RCView::form(array('method'=>'post', 'id'=>'duo_form', 'style'=>'margin:15px 0 0;font-size:13px;color:#800000;'),
							// Checkbox
							RCView::div(array('style'=>'text-indent:-2em;margin-left:2em;'),
								RCView::checkbox(array('name'=>'two_factor_auth_trust', 'value'=>'1', $two_factor_auth_trust_private_checked=>$two_factor_auth_trust_private_checked)) .
								RCView::span(array('onclick'=>"var ob=$('input[name=\"two_factor_auth_trust\"]'); ob.prop('checked', !ob.prop('checked'));"),
									$lang['system_config_500'] .
									($user_trust_period_days <= 1
										? " " . round($user_trust_period_days*24) . " " . $lang['survey_427']
										: " $user_trust_period_days " . $lang['survey_426']
									)
								)
							)
						)
					) .
					// Table of 2FA choices
					RCView::table(array('id'=>'two_factor_choices_table', 'cellspacing'=>0, 'style'=>'margin-top:10px;width:100%;table-layout:fixed;'),
						// Duo option
						(!$two_factor_auth_duo_enabled ? '' :
							RCView::tr(array(),
								RCView::td(array('style'=>'cursor:pointer;cursor:hand;', 'onclick'=>"selectTFStep1('duo', {state:'$duoSessionID'});"),
									RCView::div(array('style'=>'float:left;width:110px;'),
										RCView::radio(array('name'=>'two_factor_option', 'value'=>'duo')) .
										RCView::img(array('src'=>'duo_big.gif', 'style'=>'vertical-align:middle;'))
									) .
									RCView::div(array('style'=>($isMobileDevice ? 'width:120px;margin-top:10px;' : 'width:500px;margin-top:5px;').'float:left;vertical-align:middle;'),
										($isMobileDevice
											? RCView::b($lang['system_config_420'])
											: RCView::b($lang['system_config_420'].$lang['colon']) . " " . $lang['system_config_421']
										)
									)
								)
							)
						) .
						// Twilio SMS and Phone Call options (if not disabled)
						($sms_disabled ? '' : $two_factor_auth_twilio_sms_option) .
						($phone_call_disabled ? '' : $two_factor_auth_twilio_call_option) .
						// Google Authenticator app option
						(!$two_factor_auth_authenticator_enabled ? '' :
							RCView::tr(array(),
								RCView::td(array('style'=>'cursor:pointer;cursor:hand;', 'onclick'=>"selectTFStep1('authenticator');"),
									RCView::div(array('style'=>'float:left;width:110px;'),
										RCView::radio(array('name'=>'two_factor_option', 'value'=>'authenticator', 'style'=>'margin-right:20px;')) .
										RCView::img(array('src'=>'microsoft_authenticator.png', 'style'=>'vertical-align:middle;height:50px;margin-top:3px;'))
									) .
									RCView::div(array('style'=>($isMobileDevice ? 'width:120px;' : 'width:500px;').'float:left;vertical-align:middle;'),
										($isMobileDevice
											? RCView::b($lang['system_config_706'])
											: RCView::b($lang['system_config_706'].$lang['colon'])." ".$lang['system_config_707']
										)
									)
								)
							)
						) .
						// Email option
						(!$two_factor_auth_email_enabled ? '' :
							RCView::tr(array(),
								RCView::td(array('style'=>'cursor:pointer;cursor:hand;', 'onclick'=>"selectTFStep1('email');sendTFAcode('email');"),
									RCView::div(array('style'=>'float:left;width:110px;'),
										RCView::radio(array('name'=>'two_factor_option', 'value'=>'email')) .
										RCView::img(array('src'=>'email_big.png', 'style'=>'vertical-align:middle;'))
									) .
									RCView::div(array('style'=>($isMobileDevice ? 'width:120px;' : 'width:500px;').'float:left;vertical-align:middle;margin-top:5px;'),
										($isMobileDevice
											? RCView::b($lang['system_config_338'])
											: RCView::b($lang['system_config_338'].$lang['colon']) . " " . $lang['system_config_339']
										) .
										RCView::br() .
										RCView::span(array('style'=>($isMobileDevice ? 'font-size:12px;' : '').'color:#C00000;'),
											$lang['system_config_349'] . " " . $user_info['user_email']
										)
									)
								)
							)
						) .
						// Twilio SMS and Phone Call options (if disabled)
						($sms_disabled ? $two_factor_auth_twilio_sms_option : '') .
						($phone_call_disabled ? $two_factor_auth_twilio_call_option : '')
					)
				);
		// PHONE CALL DIALOG
		print 	RCView::div(array('id'=>'tf_verify_step_voice', 'style'=>'display:none;font-size:15px;', 'title'=>$lang['system_config_474']),
					RCView::img(array('src'=>'phone_big.png', 'style'=>'vertical-align:middle;height:44px;')) .
					RCView::b($lang['system_config_474']) .
					RCView::div(array('style'=>'margin:20px 0 0;'),
						RCView::span(array('id'=>'two_factor_option_success_login_voice_text'),
							RCView::img(array('src'=>'progress_circle.gif')) .
							$lang['system_config_481'] . " " .
							concealPhone(formatPhone($user_info['user_phone']))
						) .
						// Progress icon
						RCView::span(array('id'=>'two_factor_option_success_login_voice', 'style'=>'display:none;margin-left:10px;font-weight:bold;color:green;font-size:14px;'),
							RCView::img(array('src'=>'tick.png')) .
							$lang['global_79']
						)
					)
				);
		// VERIFY CODE DIALOG: Hidden dialog to verify the code
		print 	RCView::div(array('id'=>'tf_verify_step', 'style'=>'display:none;font-size:15px;', 'title'=>$lang['system_config_433']),
					// Text input for verification code to be entered
					RCView::div(array('style'=>'margin:0 0 10px;'),
						$lang['system_config_434']
					) .
					// SMS
					RCView::div(array('id'=>'tf_verify_step_sms', 'style'=>''),
						RCView::img(array('src'=>'sms_big.png', 'style'=>'vertical-align:middle;height:44px;')) .
						RCView::b($lang['system_config_336']) .
						RCView::SP . RCView::SP . RCView::SP .
						RCView::SP . RCView::SP . RCView::SP .
						// Progress icons
						RCView::span(array('class'=>'nowrap'),
							RCView::span(array('id'=>'two_factor_option_progress_sms', 'style'=>'color:#555;'),
								RCView::img(array('src'=>'progress_circle.gif')) .
								$lang['system_config_439']
							) .
							RCView::span(array('id'=>'two_factor_option_success_sms', 'style'=>'color:green;'),
								RCView::img(array('src'=>'tick.png')) .
								$lang['system_config_368']
							) .
							RCView::span(array('id'=>'two_factor_option_fail_sms', 'style'=>'color:#C00000;'),
								RCView::img(array('src'=>'exclamation.png')) .
								$lang['system_config_369']
							)
						)
					) .
					// Google Authenticator
					RCView::div(array('id'=>'tf_verify_step_ga', 'style'=>''),
						RCView::img(array('src'=>'microsoft_authenticator.png', 'style'=>'vertical-align:middle;height:50px;')) .
						RCView::b($lang['system_config_706'])
					) .
					// Email
					RCView::div(array('id'=>'tf_verify_step_email', 'style'=>''),
						RCView::img(array('src'=>'email_big.png', 'style'=>'vertical-align:middle;')) .
						RCView::b($lang['system_config_338']) .
						RCView::SP . RCView::SP . RCView::SP .
						RCView::SP . RCView::SP . RCView::SP .
						// Progress icons
						RCView::span(array('class'=>'nowrap'),
							RCView::span(array('id'=>'two_factor_option_progress_email', 'style'=>'color:#555;'),
								RCView::img(array('src'=>'progress_circle.gif')) .
								$lang['system_config_439']
							) .
							RCView::span(array('id'=>'two_factor_option_success_email', 'style'=>'color:green;'),
								RCView::img(array('src'=>'tick.png')) .
								$lang['system_config_368']
							) .
							RCView::span(array('id'=>'two_factor_option_fail_email', 'style'=>'color:#C00000;'),
								RCView::img(array('src'=>'exclamation.png')) .
								$lang['system_config_369']
							)
						)
					) .
					RCView::div(array('style'=>'margin:30px 0;'),
						// Text box and submit button
						RCView::input(array('id'=>'two_factor_verification_code', 'type'=>$input_type, 'pattern'=>$input_pattern, 'class'=>'x-form-text x-form-field',
							'style'=>'width:110px;font-size:15px;padding: 3px 8px;', 'autocomplete'=>'off', 'onkeydown'=>"if(event.keyCode == 13) $('#two_factor_verification_code_btn').click();")) .
						RCView::SP . RCView::SP . RCView::SP .
						RCView::span(array('class'=>'nowrap'),
							RCView::button(array('id'=>'two_factor_verification_code_btn', 'class'=>'jqbuttonmed', 'style'=>'font-weight:bold;font-size:14px;color:#333;',
								'onclick'=>"verify2FAcode($('#two_factor_verification_code').val());"), $lang['survey_200']) .
							RCView::a(array('id'=>'two_factor_verification_code_cancel', 'style'=>'margin:0 20px 0 10px;font-size:14px;text-decoration:underline;',
								'href'=>'javascript:;', 'onclick'=>"$('#tf_verify_step').dialog('close');"), $lang['global_53']) .
							// Progress icons
							RCView::img(array('id'=>'two_factor_option_progress_login', 'src'=>'progress_circle.gif')) .
							RCView::span(array('id'=>'two_factor_option_success_login', 'style'=>'margin-left:10px;font-weight:bold;color:green;font-size:14px;'),
								RCView::img(array('src'=>'tick.png')) .
								$lang['global_79']
							)
						)
					) .
					// How to set up GA?
					RCView::div(array('id'=>'tf_verify_step_ga_setup_instr', 'style'=>'margin:20px 10px 10px 0;'),
						RCView::a(array('href'=>'javascript:;', 'onclick'=>"$('#tf_verify_step_ga_setup').toggle('fade');", 'style'=>'font-size:13px;color:#777;text-decoration:underline;'),
							$lang['system_config_709']
						) .
						RCView::div(array('id'=>'tf_verify_step_ga_setup', 'style'=>'display:none;font-size:12px;margin-top:5px;line-height:13px;color:#800000;'),
                            ($user_info['two_factor_auth_enrolled']
                                ? $lang['system_config_708']
                                : (RCView::b($lang['system_config_812']).RCView::br().
                                    User::renderTwoFactorInstructionsAuthenticator(USERID) .
                                    "<script type='text/javascript'>$(function(){ $('#two_factor_totp_setup').show(); });</script>")
                            )
						)
					)
				);

		// Footer
		$HtmlPage->PrintFooter();
		exit;
	}


	/**
	 * SET USER'S "LAST LOGIN" TIME IF NOT SET (MAINLY FOR SHIBBOLETH SINCE WE CAN'T KNOW WHEN USERS JUST LOGGED IN)
	 */
	static function setLastLoginTime($userid, $addLoginEvent=false)
	{
		// Skip empty users
		if (empty($userid)) return false;
		// Get session id
		if (!Session::sessionId()) return false;
		// Only get first 32 chars (in case longer in some systems)
		$session_id = Session::sessionId();
		// Set last login time for user (but NOT if they are suspended)
		$sql = "update redcap_user_information i, (select ifnull(min(ts), '".NOW."') as ts from redcap_log_view 
		            where user = '" . db_escape($userid) . "' and session_id = '$session_id') l
				set i.user_lastlogin = l.ts
				where i.username = '" . db_escape($userid) . "' and l.ts is not null and i.user_suspended_time is null";
		$q = db_query($sql);
		// If we need to add a login event, then if we don't have a login event yet for this session, add one
        if ($addLoginEvent) {
			$sql = "select ts from redcap_log_view 
                    where user = '" . db_escape($userid) . "' and session_id = '$session_id' and event = 'LOGIN_SUCCESS'
                    order by ts limit 1";
			$q = db_query($sql);
			if (!db_num_rows($q)) {
			    // If no login event exists, then add one
				$sql = "select ifnull(min(ts), '".NOW."') from redcap_log_view 
                        where user = '" . db_escape($userid) . "' and session_id = '$session_id'";
				$q = db_query($sql);
				$login_time = db_result($q, 0);
				// Log that they successfully logged in in log_view table
				Logging::logPageView("LOGIN_SUCCESS", $userid, null, false, $login_time);
            }
        }
	}


	/**
	 * RESET USER'S PASSWORD TO A RANDOM TEMPORARY VALUE AND RETURN THE PASSWORD THAT WAS SET
	 */
	static function resetPassword($username,$loggingDescription="Reset user password")
	{
		// Set new temp password valkue
		$pass = generateRandomHash(8);
		// Update table with new password
		$sql = "update redcap_auth set password = '" . Authentication::hashPassword($pass, '', $username) . "',
				temp_pwd = 1 where username = '" . db_escape($username) . "'";
		$q = db_query($sql);
		if ($q) {
			// For logging purposes, make sure we've got a username to attribute the logging to
			defined("USERID") or define("USERID", $username);
			// Log this action as last activity and last login time
			Logging::logEvent($sql,"redcap_auth","MANAGE",$username,"username = '" . db_escape($username) . "'",$loggingDescription);
            Authentication::setUserLastLoginTimestamp($username);
			// Return password
			return $pass;
		}
		// Return false if failed
		return false;
	}


	/**
	 * RESET USER'S PASSWORD AND SEND THEM THE EMAIL
	 */
	static function resetPasswordSendEmail($username, $exitOnError=false)
	{
		global $lang, $project_contact_name, $project_contact_email, $user_email;
		// Get the user's email address
		$this_user_email = db_result(db_query("select user_email from redcap_user_information where username = '" . db_escape($username) . "'"), 0);
		if ($this_user_email == "") {
			if ($exitOnError) {
				exit("ERROR: The user does not have an email address listed. The password was not reset.");
			} else {
				return false;
			}
		}
        // Get reset password link
        $resetpasslink = Authentication::getPasswordResetLink($username);
        // Send email
        $email = new Message();
        $emailSubject = 'REDCap '.$lang['control_center_102'];
        $emailContents = $lang['control_center_4828'].' "<b>'.$username.'</b>"'.$lang['period'].' '.
                         $lang['control_center_4829'].'<br /><br />
                         <a href="'.$resetpasslink.'">'.$lang['control_center_4487'].'</a><br /><br />
                        '.$lang['control_center_98'].' '.$project_contact_name.' '.$lang['global_15'].' '.$project_contact_email.$lang['period'];
        $email->setTo($this_user_email);
        $email->setFrom(\Message::useDoNotReply($GLOBALS['project_contact_email']));
        $email->setFromName($project_contact_name);
        $email->setSubject($emailSubject);
        $email->setBody($emailContents, true);
        if ($email->send()) {
            return true;
        } elseif ($exitOnError) {
            exit("<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['control_center_66']} $this_user_email {$lang['control_center_4490']} {$lang['control_center_4491']} "
                . '<a style="text-decoration:underline;" href="'.$resetpasslink.'">'.$lang['control_center_4487'].'</a>');
        } else {
            return false;
        }
		if ($exitOnError) {
			exit("{$lang['global_01']}{$lang['colon']} {$lang['control_center_68']}");
		} else {
			return false;
		}
	}


	/**
	 * CHECK IF USER IS LOGGED IN
	 */
	static function checkLogin($action, $auth_meth_global)
	{
		global $ldapdsn, $autologout_timer, $isMobileDevice, $logout_fail_limit, $logout_fail_window,
			   $lang, $project_contact_email, $project_contact_name;

		// Start the session
		Session::init();

		// Check to make sure user hasn't had a failed login X times in Y minutes (based upon Control Center values)
		if (isset($_POST['redcap_login_a38us_09i85']) && $auth_meth_global != "none" && $logout_fail_limit != "0"
			&& $logout_fail_window != "0" && trim($_POST['username']) != '')
		{
			// Use default values for limit/window if admin somehow entered invalid values
			if (!isinteger($logout_fail_window)) $logout_fail_window = 15;
			if (!isinteger($logout_fail_limit)) $logout_fail_limit = 5;
			// Get window of time to query
			$YminAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-((int)$logout_fail_window),date("s"),date("m"),date("d"),date("Y")));
			$YminAgoInt = date("YmdHis", mktime(date("H"),date("i")-((int)$logout_fail_window),date("s"),date("m"),date("d"),date("Y")));
			// Get timestamp of last successful login in our window of time
			$sql = "select max(log_view_id) as log_view_id from redcap_log_view
					where ts >= '$YminAgo' and user = '" . db_escape($_POST['username']) . "'
					and event = 'LOGIN_SUCCESS'";
			$tsLastSuccessfulLogin = db_result(db_query($sql), 0);
			$subsql = (is_null($tsLastSuccessfulLogin) || $tsLastSuccessfulLogin == '') ? "" : "and log_view_id > '$tsLastSuccessfulLogin'";
			// Get timestamp of last time the user's password was reset by an admin in our window of time
			$sql = "select timestamp(max(ts)) as ts from redcap_log_event
					where ts >= $YminAgoInt and pk = '" . db_escape($_POST['username']) . "'
					and event = 'MANAGE' and description = 'Reset user password'";
			$tsLastPasswordReset = db_result(db_query($sql), 0);
			$subsql2 = (is_null($tsLastPasswordReset) || $tsLastPasswordReset == '') ? "" : "and ts > '$tsLastPasswordReset'";
			// Get count of failed logins in window of time
			$sql = "select count(1) from redcap_log_view where ts >= '$YminAgo' and user = '" . db_escape($_POST['username']) . "'
					and event = 'LOGIN_FAIL' $subsql $subsql2";
			$failedLogins = db_result(db_query($sql), 0);
			// If failed logins in window of time exceeds set limit
			if ($failedLogins >= $logout_fail_limit)
			{
				// Give user lock-out message
				$objHtmlPage = new HtmlPage();
				$objHtmlPage->addStylesheet("home.css", 'screen,print');
				$objHtmlPage->PrintHeader();
				print  "<div class='red' style='margin:60px 0;'>
							<b>{$lang['global_05']}</b><br><br>
							{$lang['config_functions_69']} (<b>$logout_fail_window {$lang['config_functions_72']}</b>){$lang['period']}
							{$lang['config_functions_70']}<br><br>
							{$lang['config_functions_71']}
							<a href='mailto:$project_contact_email'>$project_contact_name</a>{$lang['period']}
						</div>";
				$objHtmlPage->PrintFooter();
				exit;
			}
		}

		// Set time for auto-logout
		$auto_logout_minutes = ($autologout_timer == "") ? 0 : $autologout_timer;

		// Build and return the DSN array used for Table-based, LDAP-Table, and LDAP authentication
		$dsn = self::buildDsnArray();

		// Default
		$GLOBALS['authFail'] = 0;

		//if ldap and table authentication Loop through the available servers & authentication methods
		foreach ($dsn as $key=>$dsnvalue)
		{
			if (isset($a)) unset($a);
			$GLOBALS['authFail'] = 1;
			$a = new Auth($dsnvalue['type'], $dsnvalue['dsnstuff'], "loginFunction");

			// Expiration settings
			$oneDay = 86400; // in seconds
			$auto_logout_minutes = ($auto_logout_minutes == 0) ? ($oneDay/60) : $auto_logout_minutes; // if 0, set to 24 hour logout

			$a->setExpire($oneDay);
			$a->setIdle(round($auto_logout_minutes * 60));

			// DEBUGGING
			// print "<br>Seconds until it would have logged you out: ".($a->idle+$a->session['idle']-time());
			// print "<br> Idle time: ".(time()-$a->session['idle']);
			// print "<br> 2-min warning at: ".date("H:i:s", mktime(date("H"),date("i"),date("s")+$a->idle-120,date("m"),date("d"),date("Y")));
			// print "<div style='text-align:left;'>";print_array($dsnvalue['dsnstuff']);print "</div>";
			
			// Table-based authentication hack during login to bypass Pear DB because of SSL compatibility reasons with "MySQL over SSL"
			if ($dsnvalue['type'] == 'DB' && isset($_POST['redcap_login_a38us_09i85']) && self::verifyTableUsernamePassword($_POST['username'], $_POST['password']))
			{
				$a->setAuth($_POST['username']);
			}

			$a->start();  	// If authentication fails the loginFunction is called and since
							// the global variable $authFail is true the loginFunction will
							// return control to this point again

			if ($a->getAuth())
			{
				//print "<div style='text-align:left;'>";print_array($a);print "</div>";
				$_SESSION['username'] = $a->getUsername();
				// Make sure password is not left blank AND check for logout
				if ($action == "logout" || (isset($_POST['redcap_login_a38us_09i85']) && isset($_POST['password']) && trim($_POST['password']) == ""))
				{
					$GLOBALS['authFail'] = 0;
					$a->logout();
					$a->start();
				}
				// Log the successful login
				elseif (isset($_POST['redcap_login_a38us_09i85']))
				{
					// If there is a username conflict where a username exists as a Table-based user but is also a valid LDAP user,
					// then when the LDAP user logs in, display an error that a Table-based user already exists under that username.
					if ($GLOBALS['auth_meth_global'] == 'ldap_table' && $dsnvalue['type'] == 'LDAP' && self::isTableUser($_SESSION['username'])) {
						// Reset auth values
						$GLOBALS['authFail'] = 0;
						$a->logout();
						// Display error to user
						$objHtmlPage = new HtmlPage();
						$objHtmlPage->PrintHeaderExt();
						print  "<div class='red' style='margin:60px 0;'>
									<b>{$lang['config_functions_116']}</b><br><br>
									{$lang['config_functions_117']} \"<b>{$_SESSION['username']}</b>\"{$lang['period']}
									{$lang['config_functions_118']} <a href='mailto:$project_contact_email'>$project_contact_name</a>
									{$lang['config_functions_119']}
								</div>";
						$objHtmlPage->PrintFooterExt();
						exit;
					}
					// Log the successful login
					Logging::logPageView("LOGIN_SUCCESS", $_SESSION['username']);
				}
				return 1;
			} else {
				//print  "<div class='red' style='max-width:100%;width:100%;font-weight:bold;'>FAIL</div>";
			}
		}
		
		// Display error message that login failed
        print  '<!DOCTYPE HTML>' . "\n"; // Quick fix to prevent browser using Quirks Mode
		print   RCView::div(array('class'=>'red','style'=>'max-width:100%;width:100%;font-weight:bold;'),
            RCView::fa("fa-solid fa-circle-exclamation text-danger me-1") .
            RCView::tt("config_functions_128") // ERROR: You entered an invalid user name or password!
        );
		?><script type="text/javascript">setTimeout(function(){ document.getElementById('rc-login-form').style.display='block'; },1000)</script><?php
				
		// In case some users accidentally enter their password into the username field, we will not log the username entered unless it is a valid user's username.
		$sql = "select 1 from redcap_user_information where username = '".db_escape($_POST['username'])."'";
		$q = db_query($sql);
		$isRealUser = (db_num_rows($q) > 0);
		$failedUsernameEntered = $isRealUser ? $_POST['username'] : "[not_valid_username]";
		
		// Log the failed login
		Logging::logPageView("LOGIN_FAIL", $failedUsernameEntered);

		$GLOBALS['authFail'] = 0;
		$a->start();
		return 1;
	}
	
	// Build and return the DSN array used for Table-based, LDAP-Table, and LDAP authentication
	public static function buildDsnArray()
	{
		global $auth_meth_global, $mysqldsn, $ldapdsn;
		$dsn = array();
        // Are we using an "X & Table-based" authentication method?
        $usingXandTableBasedAuth = !($auth_meth_global == "table" || strpos($auth_meth_global, "table") === false);
		// Auth includes Table
        if ($auth_meth_global == "table" || ($usingXandTableBasedAuth && $auth_meth_global != "aaf_table") || ($auth_meth_global == "aaf_table" && !isset($_SESSION['tli'])))
        {
            $dsn[] = array('type'=>'DB', 'dsnstuff'=>$mysqldsn);
        }
        // Auth includes LDAP
		if ($auth_meth_global == "ldap_table" || $auth_meth_global == "ldap")
		{
			if (is_array(end($ldapdsn))) {
				// Loop through all LDAP configs and add 
				foreach ($ldapdsn as $this_ldapdsn) {
					$dsn[] = array('type'=>'LDAP', 'dsnstuff'=>$this_ldapdsn);
				}
			} else {
				// Add single LDAP config                
				$dsn[] = array('type'=>'LDAP', 'dsnstuff'=>$ldapdsn);
			}
        }
		return $dsn;
	}
	
	// Create a login session as if a user just logged in (bypasses regular authentication)
	public static function autoLogin($username)
	{
		global $auto_logout_minutes;
        if (!class_exists('Auth')) require_once APP_PATH_DOCROOT . 'Libraries/PEAR/Auth.php';
		$a = new Auth('DB', '', "loginFunction");
		// Expiration settings
		$oneDay = 86400; // in seconds
		$auto_logout_minutes = ($auto_logout_minutes == 0) ? ($oneDay/60) : $auto_logout_minutes; // if 0, set to 24 hour logout
		$a->setExpire($oneDay);
		$a->setIdle(round($auto_logout_minutes * 60));
		$a->setAuth($username);
		$a->start();
		if ($a->getAuth()) {
			$_SESSION['username'] = $a->getUsername();
		}
	}

	// If logout variable exists in URL, destroy the session
	// and reset the $userid variable to remove all user context
	static function checkLogout()
	{
		global $auth_meth_global;
		if (isset($_GET['logout']) && $_GET['logout'])
		{
            // If using OpenID Connect auth, fetch the id_token from the session before the session is destroyed
            $openid_connect_id_token = "";
            if (($auth_meth_global == 'openid_connect' || $auth_meth_global == 'openid_connect_table') && isset($_SESSION['openid_connect_id_token'])) {
                $openid_connect_id_token = $_SESSION['openid_connect_id_token'];
            }
			// Log the logout
			Logging::logPageView("LOGOUT", $_SESSION['username']);
            $currentUser = $_SESSION['username'];
			// Destroy session and erase userid
            Session::destroyUserSession();
			// Default value (remove 'logout' from query string, if exists)
			$logoutRedirect = str_replace(array("logout=1&","&logout=1","logout=1","&amp;"), array("","","","&"), curPageURL());
			if (substr($logoutRedirect, -1) == '&' || substr($logoutRedirect, -1) == '?') $logoutRedirect = substr($logoutRedirect, 0, -1);
			// If using Shibboleth, redirect to Shibboleth logout page
			if ( ( $auth_meth_global == 'shibboleth_table' || $auth_meth_global == 'shibboleth' ) && strlen($GLOBALS['shibboleth_logout']) > 0) {
				$logoutRedirect = $GLOBALS['shibboleth_logout'];
			}
			// If using SAMS, redirect to SAMS logout page
			elseif ($auth_meth_global == 'sams' && strlen($GLOBALS['sams_logout']) > 0) {
				$logoutRedirect = $GLOBALS['sams_logout'];
			}
            // If using OpenID Connect, redirect to logout page
			elseif ($auth_meth_global == 'openid_connect' || ($auth_meth_global == 'openid_connect_table' && !self::isTableUser($currentUser))) {
                try {
                    $oidc = self::initOpenIDConnect();
                    $logoutRedirect = (strlen($GLOBALS['openid_connect_logout']) > 0) ? $GLOBALS['openid_connect_logout'] : APP_PATH_WEBROOT_FULL."index.php?logout=1";
                    $oidc->signOut($openid_connect_id_token, $logoutRedirect);
                } catch (Exception $e) { }
			}
			// If using Oauth2 Azure AD, redirect to AD logout page
            elseif ($auth_meth_global == 'oauth2_azure_ad' || ($auth_meth_global == 'oauth2_azure_ad_table' && !self::isTableUser($currentUser))) {
                $azureParams = [
					'clientId' => $GLOBALS['oauth2_azure_ad_client_id'],
					'clientSecret' => $GLOBALS['oauth2_azure_ad_client_secret'],
					'redirectUri' => APP_PATH_WEBROOT_FULL
                ];
				if ($GLOBALS['proxy_hostname'] != '') {
					$azureParams['proxy'] = $GLOBALS['proxy_hostname'];
					$azureParams['verify'] = false;
				}
				$provider = new TheNetworg\OAuth2\Client\Provider\Azure($azureParams);
				$logoutRedirect = $provider->getLogoutUrl($logoutRedirect);
			}
			// Reload same page or redirect to login page
			redirect($logoutRedirect);
		}
	}


	/**
	 * SEARCH REDCAP_AUTH TABLE FOR USER (return boolean)
	 */
	public static function isTableUser($user)
	{
		$q = db_query("select 1 from redcap_auth where username = '".db_escape($user)."' limit 1");
		return ($q && db_num_rows($q) > 0);
	}


	// If logging in, update Last Login time in user_information table
	// (but NOT if they are suspended - could be confusing if last login occurs AFTER suspension)
	public static function setUserLastLoginTimestamp($userid)
	{
		$sql = "update redcap_user_information set user_lastlogin = '" . NOW . "'
				where username = '" . db_escape($userid) . "' and user_suspended_time is null";
		db_query($sql);
	}

	private static function authenticateOauth2AzureAD()
	{
		// Get global vars
		global  $auth_meth_global, $login_logo, $institution, $login_custom_text, $homepage_contact_email, $homepage_contact,
				$lang, $oauth2_azure_ad_client_id, $oauth2_azure_ad_client_secret,
				$rc_autoload_function, $oauth2_azure_ad_primary_admin, $oauth2_azure_ad_secondary_admin, $oauth2_azure_ad_username_attribute,
				$oauth2_azure_ad_endpoint_version, $oauth2_azure_ad_tenant;

		$is_azure_ad_endpoint_v1 = $oauth2_azure_ad_endpoint_version === 'V1' || empty($oauth2_azure_ad_endpoint_version);
		$is_azure_ad_endpoint_v2 = $oauth2_azure_ad_endpoint_version === 'V2';

		// Initialize $userid
		$userid = '';

		// Check session first
		if (isset($_SESSION['redcap_userid']) && !empty($_SESSION['redcap_userid']))
		{
			// If redcap_userid exists in the session, then user is authenticated and set it as their REDCap username
			$userid = $_SESSION['username'] = $_SESSION['redcap_userid'];
		}
		else
		{
			// do the auth
			$azureParams = [
				'clientId' => $GLOBALS['oauth2_azure_ad_client_id'],
				'clientSecret' => $GLOBALS['oauth2_azure_ad_client_secret'],
				'redirectUri' => APP_PATH_WEBROOT_FULL,
				'tenant' => ($oauth2_azure_ad_tenant == '' ? 'common' : $oauth2_azure_ad_tenant)
			];
			if ($GLOBALS['proxy_hostname'] != '') {
				$azureParams['proxy'] = $GLOBALS['proxy_hostname'];
				$azureParams['verify'] = false;
			}
			$provider = new TheNetworg\OAuth2\Client\Provider\Azure($azureParams);
			
			if ($is_azure_ad_endpoint_v1) {
				// Just do basic read of /me endpoint for v1.0 API.
				$provider->scope = ['offline_access User.Read'];
				$provider->urlAPI = "https://graph.microsoft.com/v1.0/";
				$provider->defaultEndPointVersion = TheNetworg\OAuth2\Client\Provider\Azure::ENDPOINT_VERSION_1_0;
				// This tells the library not to pass resource reqd for v2.0
				$provider->authWithResource = false;
			} elseif ($is_azure_ad_endpoint_v2) {
				// See: https://github.com/TheNetworg/oauth2-azure#usage.
				$provider->scope = 'openid profile email offline_access ' . $provider->getRootMicrosoftGraphUri(null) . '/User.Read';
				$provider->defaultEndPointVersion = TheNetworg\OAuth2\Client\Provider\Azure::ENDPOINT_VERSION_2_0;
			}
			
			// Obtain the auth code
			if (!isset($_GET['code'])) {
				if ($is_azure_ad_endpoint_v1) {
					$authUrl = $provider->getAuthorizationUrl();
				} elseif ($is_azure_ad_endpoint_v2) {
					$authUrl = $provider->getAuthorizationUrl(array('scope' => $provider->scope));
				}
				$_SESSION['oauth2state'] = $provider->getState();
				redirect($authUrl);
				exit;
			
			// State validation 
			} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
				// If any kind of fatal error occurs, kill the session and reload the page
				Session::destroyUserSession();
				redirect($_SERVER['REQUEST_URI']);
			
			} else {
				// Exchange auth code for tokens
				// Token will be in '$token->getToken();'
				$params = array('code' => $_GET['code']);
				if ($is_azure_ad_endpoint_v1) {
					$params['resource'] = 'https://graph.microsoft.com';
				} elseif ($is_azure_ad_endpoint_v2) {
					$params['scope'] = $provider->scope;
				}
				$token = $provider->getAccessToken('authorization_code', $params);
				
				// Now we can call /me endpoint of MS Graph 
				try {
					if ($is_azure_ad_endpoint_v1) {
						$userinfo = $provider->get('me?$select=userPrincipalName,mail,surname,givenName,onPremisesSamAccountName,employeeID', $token);
                        $userinfo['mail'] = str_ireplace("#EXT#", "", $userinfo['mail']??""); // In some cases, the email address or username can contain #EXT#, and if so, we need to remove it.
                        // Set username based on $oauth2_azure_ad_username_attribute setting (samAccountName vs userPrincipalName)
						if ($oauth2_azure_ad_username_attribute != '' && isset($userinfo[$oauth2_azure_ad_username_attribute])) {
                            $userinfo[$oauth2_azure_ad_username_attribute] = str_ireplace("#EXT#", "", $userinfo[$oauth2_azure_ad_username_attribute]); // In some cases, the email address or username can contain #EXT#, and if so, we need to remove it.
							$userid = $_SESSION['username'] = $_SESSION['redcap_userid'] = $userinfo[$oauth2_azure_ad_username_attribute]; // e.g., tjin at SBM if attribute="samAccountName"
						} else {
                            $userinfo['userPrincipalName'] = str_ireplace("#EXT#", "", $userinfo['userPrincipalName']); // In some cases, the email address or username can contain #EXT#, and if so, we need to remove it.
                            $userid = $_SESSION['username'] = $_SESSION['redcap_userid'] = $userinfo['userPrincipalName']; // e.g., tony.jin@stonybrookmedicine.edu at SBM
						}
					} elseif ($is_azure_ad_endpoint_v2) {
						// Fetch user data from resource owner endpoint.
						$resource_owner = $provider->getResourceOwner($token);
                        $azureADemail = str_ireplace("#EXT#", "", $resource_owner->claim('email')); // In some cases, the email address can contain #EXT#, and if so, we need to remove it.
                        // Set user info
						$userinfo = array(
							'userPrincipalName' => ($oauth2_azure_ad_username_attribute === 'userPrincipalName' ? $azureADemail : ($resource_owner->claim($oauth2_azure_ad_username_attribute) != null ? $resource_owner->claim($oauth2_azure_ad_username_attribute) : $resource_owner->claim('preferred_username'))),
							'mail' => $azureADemail,
							'givenName' => $resource_owner->getFirstName(),
							'surname' => $resource_owner->getLastName(),
						);
						// Check a different endpoint if any user data is missing.
						if (count($userinfo) !== count(array_filter($userinfo))) {
							$oidc_userinfo = $provider->get($provider->getRootMicrosoftGraphUri($token) . '/oidc/userinfo', $token);
                            $oidc_userinfo['email'] = str_ireplace("#EXT#", "", $oidc_userinfo['email']??""); // In some cases, the email address can contain #EXT#, and if so, we need to remove it.
							if (empty($userinfo['userPrincipalName']) && !empty($oidc_userinfo['email'])) {
								$userinfo['userPrincipalName'] = $oidc_userinfo['email'];
							}
							if (empty($userinfo['mail']) && !empty($oidc_userinfo['email'])) {
								$userinfo['mail'] = $oidc_userinfo['email'];
							}
							if (empty($userinfo['givenName']) && !empty($oidc_userinfo['given_name'])) {
								$userinfo['givenName'] = $oidc_userinfo['given_name'];
							}
							if (empty($userinfo['surname']) && !empty($oidc_userinfo['family_name'])) {
								$userinfo['surname'] = $oidc_userinfo['family_name'];
							}
						}
                        $userinfo['userPrincipalName'] = str_ireplace("#EXT#", "", $userinfo['userPrincipalName']); // In some cases, the email address can contain #EXT#, and if so, we need to remove it.
                        $userid = $_SESSION['username'] = $_SESSION['redcap_userid'] = $userinfo['userPrincipalName'];
					}
                    // Vanderbilt only: Convert legacy VUMCID/VUnetID (onPremisesSamAccountName) to UPN (email address) in relevant db tables
                    if (isVanderbilt() || isDev()) {
                        self::convertVanderbiltUsername($userinfo, ($resource_owner??null)); // use V2 for VUMC Test/Prod, but V1 for VUMC Demo/others
                    }
					// Auto-set email and name if this is the first time logging in
					$rc_userinfo = User::getUserInfo($userid);
					if ($rc_userinfo['user_email'] == '') {
						global $allow_create_db_default, $default_datetime_format, $default_number_format_decimal, $default_number_format_thousands_sep;
						$super_admin = 0;
						if (strtolower($userid) === strtolower($oauth2_azure_ad_primary_admin) || strtolower($userid) === strtolower($oauth2_azure_ad_secondary_admin)) {
							$super_admin = 1;
						}
						$sql = "insert into redcap_user_information (username, user_email, user_firstname, user_lastname, user_firstvisit,
								allow_create_db, user_creation, datetime_format, number_format_decimal, number_format_thousands_sep,
								super_user, account_manager, access_system_config, access_system_upgrade, access_external_module_install, admin_rights, access_admin_dashboards
								) values ('".db_escape($userid)."', '".db_escape($userinfo['mail'])."', '".db_escape($userinfo['givenName'])."',
								'".db_escape($userinfo['surname'])."', '".NOW."', $allow_create_db_default, '".NOW."', '".db_escape($default_datetime_format)."',
								'".db_escape($default_number_format_decimal)."', '".db_escape($default_number_format_thousands_sep)."',
								'".db_escape($super_admin)."', '".db_escape($super_admin)."', '".db_escape($super_admin)."', '".db_escape($super_admin)."', '".db_escape($super_admin)."', '".db_escape($super_admin)."', '".db_escape($super_admin)."'
								)
								on duplicate key
								update user_email = '".db_escape($userinfo['mail'])."', user_firstname = '".db_escape($userinfo['givenName'])."',
								user_lastname = '".db_escape($userinfo['surname'])."', ui_id = LAST_INSERT_ID(ui_id)";
						db_query($sql);
                        // Email admin and/or user about new account?
                        if (db_affected_rows() > 0) {
                            User::notifyAdminNewUser($userid);
                            User::notifyNewUserWelcomeEmail($userid);
                        }
					}
					// Log that they successfully logged in in log_view table
					Logging::logPageView("LOGIN_SUCCESS", $userid);
					// Set the user's last_login timestamp
					self::setUserLastLoginTimestamp($userid);
					// Redirect back to main page
                    if (isset($_COOKIE['auth_redirect_url'])) {
                        $redirect = decrypt($_COOKIE['auth_redirect_url']);
                        deletecookie('auth_redirect_url');
                    } elseif (isset($_SESSION['redcap_page'])) {
						$redirect = $_SESSION['redcap_page'];
						unset($_SESSION['redcap_page']);
					} else {
						$redirect = APP_PATH_WEBROOT_FULL;
					}
					redirect($redirect);
			
				} catch (Exception $e) {
					exit('Failed to call the me endpoint of MS Graph.');
				}
			}
		}
		return($userid);
	}

    // Vanderbilt only: Convert legacy VUMCID/VUnetID (onPremisesSamAccountName) to UPN (email address) in relevant db tables
    private static function convertVanderbiltUsername($authinfo, $resource_owner=null)
    {
        // table=>column to update
        $tablesToChange = [
            'redcap_user_information'=>'username',
            'redcap_user_rights'=>'username',
            'redcap_data_access_groups_users'=>'username',
            'redcap_external_links_users'=>'username',
            'redcap_project_dashboards_access_users'=>'username',
            'redcap_reports_access_users'=>'username',
            'redcap_reports_edit_access_users'=>'username',
            'redcap_user_allowlist'=>'username',
            'redcap_sendit_docs'=>'username',
            'redcap_esignatures'=>'username',
            'redcap_locking_data'=>'username',
            'redcap_locking_records'=>'username'
        ];
        // Get legacy username and UPN
        $oldUsername = $authinfo['onPremisesSamAccountName'] ?? null;
        $newUsername = $authinfo['mail'] ?? null; // Use mail instead, not userPrincipalName, which might be super long Outlook email address
        // For VU guest users, the onPremisesSamAccountName won't be populated, so use other attribute to get their VUnetID
        if ($resource_owner !== null && $oldUsername == null) {
            // Try claim "vunetid" or alternatively "User.extensionattribute4"
            try {
                $oldUsername = $resource_owner->claim('vunetid');
            } catch (\Throwable $e) { }
            if ($oldUsername == null) {
                try {
                    $oldUsername = $resource_owner->claim('extensionattribute4');
                } catch (\Throwable $e) { }
            }
        }
        // Make sure we have values for these
        if ($oldUsername == null || $newUsername == null) return;
        // If onPremisesSamAccountName is somehow the email address (has an @ sign in it), then do nothing
        if (strpos($oldUsername, "@") !== false) return;
        // If legacy username does not exist, then do nothing
        if (User::getUserInfo($oldUsername) === false) return;
        // If new username already exists, then do nothing
        if (User::getUserInfo($newUsername) !== false) return;
        // If old username exists in the user_information table, then replace it in all tables (excluding log tables)
        foreach ($tablesToChange as $table=>$col) {
            $sql = "update $table set $col = '".db_escape($newUsername)."' where $col = '".db_escape($oldUsername)."'";
            db_query($sql);
        }
        // Log this in the user comments and logging table
        $sql = "update redcap_user_information set user_comments = concat(?,ifnull(user_comments,'')) where username = ?";
        $log = "Username auto-changed from \"$oldUsername\" to \"$newUsername\"";
        $text = NOW . " $log \n\n";
        db_query($sql, [$text, $newUsername]);
        // Get UI_ID
	    $ui_id = null;
        if (!defined("UI_ID")) {
            $ui_id = db_result(db_query("select ui_id from redcap_user_information where username = ?", $newUsername)) ?? null;
        } else {
	        $ui_id = UI_ID;
        }
        // Add to Vanderbilt-only db table used to keep track of before->after username
        $sql2 = "insert into vanderbilt_username_conversion (convert_time, ui_id, old_username, new_username) values (?, ?, ?, ?)";
        db_query($sql2, [NOW, $ui_id, $oldUsername, $newUsername]);
        /**
         * CREATE TABLE `vanderbilt_username_conversion` (
         * `convert_id` int(10) NOT NULL AUTO_INCREMENT,
         * `convert_time` datetime DEFAULT NULL,
         * `ui_id` int(10) DEFAULT NULL,
         * `old_username` varchar(191) DEFAULT NULL,
         * `new_username` varchar(191) DEFAULT NULL,
         * PRIMARY KEY (`convert_id`),
         * KEY `ui_id` (`ui_id`),
         * KEY `old_username` (`old_username`),
         * KEY `new_username` (`new_username`),
         * KEY `convert_time` (`convert_time`),
         * CONSTRAINT `vanderbilt_username_conversion_ibfk_1` FOREIGN KEY (`ui_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE
         * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
         */
        // Add logged event
        Logging::logEvent($sql."\n".$sql2,"redcap_user_information","MANAGE",$newUsername,"old_username = '$oldUsername', new_username: '$newUsername'",$log);
    }

    // Return boolean if using OAuth2 for coursera.org
    private static function isCourseraAuth()
    {
        return (strpos($GLOBALS['openid_connect_metadata_url'], 'coursera.org') !== false);
    }

    // Initialize the generic OpenID Connect and return instantiated object
    private static function initOpenIDConnect()
    {
        try {
            $oidc = new Jumbojett\OpenIDConnectClient($GLOBALS['openid_connect_provider_url'], $GLOBALS['openid_connect_client_id'], $GLOBALS['openid_connect_client_secret']);
            // Call metadata endpoint to obtain provider config parameters
            $configParams = ($GLOBALS['openid_connect_metadata_url'] != "") ? json_decode(http_get($GLOBALS['openid_connect_metadata_url']), true) : [];
            if (is_array($configParams) && !empty($configParams)) {
                foreach ($configParams as $configParamsKey => $configParamsVal) {
                    $oidc->providerConfigParam(array($configParamsKey => $configParamsVal));
                }
            }
            // Other settings
            $oidc->addRegistrationParam(array('post_logout_redirect_uris' => $_COOKIE['auth_redirect_url'] ?? APP_PATH_WEBROOT_FULL));
            $oidc->setRedirectURL(APP_PATH_WEBROOT_FULL . "index.php");
            if ($GLOBALS['proxy_hostname'] != '') $oidc->setHttpProxy($GLOBALS['proxy_hostname']);
			if (isset($GLOBALS['openid_connect_override_scope']) && !empty($GLOBALS['openid_connect_override_scope'])) {
				$scopes = explode(",", str_replace(" ", "", $GLOBALS['openid_connect_override_scope']));
			} elseif (isset($configParams['scopes_supported']) && is_array($configParams['scopes_supported']) && !empty($configParams['scopes_supported'])) {
                $scopes = $configParams['scopes_supported'];
            } else {
                $scopes = ['openid', 'profile', 'email'];
            }
            if (isset($GLOBALS['openid_connect_additional_scope']) && $GLOBALS['openid_connect_additional_scope'] != '') {
                $scopes[] = $GLOBALS['openid_connect_additional_scope'];
            }
            $oidc->addScope($scopes);
            if ($GLOBALS['openid_connect_response_type'] == 'form_post') {
                $oidc->addAuthParam(array('response_mode' => 'form_post'));
            }
        } catch (Exception $e) {
            $oidc = null;
            if (isDev()) {
                print_array("OpenID Connect Initialization Error: " . $e->getMessage());
            }
        }
       return $oidc;
    }

	// Authenticate via generic OpenID Connect and return the userid
	private static function authenticateOpenIDConnect()
	{
        $userid = null;

        // Custom Coursera.org OIDC Authentication
        if (self::isCourseraAuth())
        {
            if (isset($_REQUEST['error'])) {
                exit("OpenID Connect Initialization Error: ".$_REQUEST['error']);
            } elseif (!isset($_REQUEST['code'])) {
                // Redirect there and return with code
                $redirectUrl = $GLOBALS['openid_connect_provider_url'] . "?scope=view_profile+access_business_api&redirect_uri=" . rawurlencode(APP_PATH_WEBROOT_FULL . "index.php")
                    . "&access_type=offline&grant_type=authorization_code&response_type=code&client_id=" . $GLOBALS['openid_connect_client_id'];
                redirect($redirectUrl);
            } else {
                // Get access token
                $tokenUrl = dirname($GLOBALS['openid_connect_provider_url'])."/token";
                $params = ['redirect_uri'=>APP_PATH_WEBROOT_FULL . "index.php", 'client_id'=>$GLOBALS['openid_connect_client_id'], 'client_secret'=>$GLOBALS['openid_connect_client_secret'],
                            'access_type'=>'offline', 'grant_type'=>'authorization_code', 'code'=>$_REQUEST['code']];
                $response = json_decode(http_post($tokenUrl, $params), ENT_QUOTES);
                if (!isset($response['access_token'])) exit("Authentication error!");
                if (isset($response['id_token'])) {
                    $_SESSION['openid_connect_id_token'] = $response['id_token'];
                }
                // Get refresh token
                // $params = ['client_id'=>$GLOBALS['openid_connect_client_id'], 'client_secret'=>$GLOBALS['openid_connect_client_secret'],
                //            'refresh_token'=>$response['refresh_token'], 'grant_type'=>'refresh_token'];
                // $response = json_decode(http_post($tokenUrl, $params), ENT_QUOTES);
                // if (!isset($response['access_token'])) exit("Authentication error!");
                // Get user's username, which should be their email address
                $userInfoUrl = dirname($GLOBALS['openid_connect_provider_url'])."/userInfo"
                    . "?client_id=".rawurlencode($GLOBALS['openid_connect_client_id'])
                    . "&client_secret=".rawurlencode($GLOBALS['openid_connect_client_secret'])
                    . "&access_token=".rawurlencode($response['access_token']);
                $response = json_decode(http_get($userInfoUrl), true);
                if (!isset($response['email'])) exit("Authentication error!");
                // Set username as email address
                $_SESSION['username'] = $userid = $response['email'];
                list ($firstname, $lastname) = explode(" ", $response['fullName'], 2);
                // Update user's "last login" time if not yet updated for this session
                // Only do this if coming from outside REDCap.
                if (!isset($_SERVER['HTTP_REFERER']) || (isset($_SERVER['HTTP_REFERER']) && substr($_SERVER['HTTP_REFERER'], 0, strlen(APP_PATH_WEBROOT_FULL)) != APP_PATH_WEBROOT_FULL)) {
                    self::setLastLoginTime($userid, true);
                }
                // Add user to user_information table if not added yet
                $userinfo = User::getUserInfo($userid);
                if ($userinfo['user_email'] == '')
                {
                    // Set primary/secondary admin as super_user if logging in the first time
                    $super_admin = (strtolower($userid) === strtolower($GLOBALS['openid_connect_primary_admin']) || strtolower($userid) === strtolower($GLOBALS['openid_connect_secondary_admin'])) ? 1 : 0;
                    $sql = "replace into redcap_user_information (username, user_email, user_firstname, user_lastname, user_firstvisit,
                            allow_create_db, user_creation, datetime_format, number_format_decimal, number_format_thousands_sep,
                            super_user, account_manager, access_system_config, access_system_upgrade, access_external_module_install, admin_rights, access_admin_dashboards) values (
                            '".db_escape($userid)."', '".db_escape($userid)."', '".db_escape($firstname)."',
                            '".db_escape($lastname)."', '".NOW."', '".db_escape($GLOBALS['allow_create_db_default'])."', '".NOW."', '".db_escape($GLOBALS['default_datetime_format'])."',
                            '".db_escape($GLOBALS['default_number_format_decimal'])."', '".db_escape($GLOBALS['default_number_format_thousands_sep'])."',
                            '".db_escape($super_admin)."', '".db_escape($super_admin)."', '".db_escape($super_admin)."', '".db_escape($super_admin)."', '".db_escape($super_admin)."', '".db_escape($super_admin)."', '".db_escape($super_admin)."'
                            )";
                    db_query($sql);
                    // Email admin and/or user about new account?
                    if (db_affected_rows() > 0) {
                        User::notifyAdminNewUser($userid);
                        User::notifyNewUserWelcomeEmail($userid);
                    }
                }
            }
        }

        // Typical OIDC Authentication
        if ($userid == null) {
            try {
                $oidc = self::initOpenIDConnect();
                // Init the authentication
                try {
					$success = $oidc->authenticate();
				} catch (Throwable $e) {
					$success = false;
					print RCView::errorBox("OpenID Connect Initialization: " . $e->getMessage());
				}
                // Get user info, if successfully logged in
                if ($success) {
                    // Set username - use the defined user info param ("username" by default), and if not available, fall back to using the "email" attribute
                    $userid = $_SESSION['username'] = ($oidc->requestUserInfo($GLOBALS['openid_connect_username_attribute']) != '')
                        ? strtolower($oidc->requestUserInfo($GLOBALS['openid_connect_username_attribute']))
                        : strtolower($oidc->requestUserInfo('email'));

                    // Set id_token to use during logout process
                    $_SESSION['openid_connect_id_token'] = $oidc->getIdToken();

                    // Update user's "last login" time if not yet updated for this session
                    // Only do this if coming from outside REDCap.
                    if (!isset($_SERVER['HTTP_REFERER']) || (isset($_SERVER['HTTP_REFERER']) && substr($_SERVER['HTTP_REFERER'], 0, strlen(APP_PATH_WEBROOT_FULL)) != APP_PATH_WEBROOT_FULL)) {
                        self::setLastLoginTime($userid, true);
                    }

                    // Add user to user_information table if not added yet
                    $userinfo = User::getUserInfo($userid);
                    if ($userinfo['user_email'] == '') {
                        // Set primary/secondary admin as super_user if logging in the first time
                        $super_admin = (strtolower($userid) === strtolower($GLOBALS['openid_connect_primary_admin']) || strtolower($userid) === strtolower($GLOBALS['openid_connect_secondary_admin'])) ? 1 : 0;
                        $sql = "replace into redcap_user_information (username, user_email, user_firstname, user_lastname, user_firstvisit,
                                allow_create_db, user_creation, datetime_format, number_format_decimal, number_format_thousands_sep,
                                super_user, account_manager, access_system_config, access_system_upgrade, access_external_module_install, admin_rights, access_admin_dashboards) values (
                                '" . db_escape($userid) . "', '" . db_escape($oidc->requestUserInfo('email')) . "', '" . db_escape($oidc->requestUserInfo('given_name')) . "',
                                '" . db_escape($oidc->requestUserInfo('family_name')) . "', '" . NOW . "', '" . db_escape($GLOBALS['allow_create_db_default']) . "', '" . NOW . "', '" . db_escape($GLOBALS['default_datetime_format']) . "',
                                '" . db_escape($GLOBALS['default_number_format_decimal']) . "', '" . db_escape($GLOBALS['default_number_format_thousands_sep']) . "',
                                '" . db_escape($super_admin) . "', '" . db_escape($super_admin) . "', '" . db_escape($super_admin) . "', '" . db_escape($super_admin) . "', '" . db_escape($super_admin) . "', '" . db_escape($super_admin) . "', '" . db_escape($super_admin) . "'
                                )";
                        db_query($sql);
                        // Email admin and/or user about new account?
                        if (db_affected_rows() > 0) {
                            User::notifyAdminNewUser($userid);
                            User::notifyNewUserWelcomeEmail($userid);
                        }
                    }
                }
            } catch (Exception $e) {
                if (isDev()) {
                    print_array("OpenID Connect Authentication Error: " . $e->getMessage());
                }
            }
        }

        // Return username
        return $userid;
    }


    // Authenticate via OpenID provider
    private static function authenticateOpenID()
    {
        // Get global vars
        global  $login_logo, $institution, $login_custom_text, $homepage_contact_email, $homepage_contact,
                $openid_provider_url, $openid_provider_name, $lang;
        // Initialize $userid
        $userid = '';
        // Check session first
        if (isset($_SESSION['redcap_userid']) && !empty($_SESSION['redcap_userid'])) {
            // If redcap_userid exists in the session, then user is authenticated and set it as their REDCap username
            $userid = $_SESSION['username'] = $_SESSION['redcap_userid'];
        } else {
            // User is logging in with OpenID
            try {
                // Double check the OpenID provider URL and name values
                if ($openid_provider_url == "") {
                    exit("ERROR: OpenID provider URL has not been defined in the Control Center!");
                }
                if ($openid_provider_name == "") $openid_provider_name = "[OpenID provider]";
                // Instantiate openid object
                $openid = new LightOpenID(SERVER_NAME);

                if (!$openid->mode) {
                    // If just clicked button to navigate to OpenID provider, then redirect them to the provider
                    if (isset($_POST['redcap_login_openid_Re8D2_8uiMn'])) {
                        $openid->identity = $openid_provider_url;
                        $openid->required = array('contact/email', 'namePerson');
                        $openid->optional = array('namePerson/friendly');
                        redirect($openid->authUrl());
                    }
                    // If just coming into REDCap, give notice page that they'll need to authenticate via OpenID
                    else {
                        // Header
                        $objHtmlPage = new HtmlPage();
                        $objHtmlPage->PrintHeaderExt();
                        // Logo and "log in" text
                        print 	RCView::div('', RCView::img(array('src'=>'redcap-logo-large.png')));
                        print 	RCView::h3(array('style'=>'margin:20px 0;padding:3px;border-bottom:1px solid #AAAAAA;color:#000000;font-weight:bold;'),
                            $lang['config_functions_45']
                        );
                        // Institutional logo (optional)
                        if (trim($login_logo) != "") {
                            print RCView::div(array('style'=>'margin-bottom:20px;text-align:center;'),
                                "<img src='$login_logo' title=\"".RCView::escape($institution)."\" alt=\"".RCView::escape($institution)."\" style='max-width:850px; expression(this.width > 850 ? 850 : true);'>"
                            );
                        }
                        // Show custom login text (optional)
                        if (trim($login_custom_text) != "") {
                            print RCView::div(array('style'=>'border:1px solid #ccc;background-color:#f5f5f5;margin:15px 10px 15px 0;padding:10px;'), nl2br(decode_filter_tags($login_custom_text)));
                        }
                        // Login instructions
                        print 	RCView::p(array('style'=>'margin:10px 0 30px;'),
                            $lang['config_functions_84'] . " " .
                            RCView::span(array('style'=>'color:#800000;font-weight:bold;font-size:13px;'), RCView::escape($openid_provider_name)) . $lang['period'] . " " .
                            $lang['config_functions_86'] . " " .
                            $lang['config_functions_83'] . " " .
                            RCView::a(array('style'=>'text-decoration:underline;', 'href'=>"mailto:$homepage_contact_email"), RCView::escape($homepage_contact)) .
                            $lang['period']
                        );
                        // Form with submit button
                        print 	RCView::form(array('method'=>'post','action'=>$_SERVER['REQUEST_URI'],'style'=>'text-align:center;margin:20px 0 40px;'),
                            RCView::input(array('class'=>'jqbuttonmed', 'style'=>'padding:5px 10px !important;font-size:13px;', 'type'=>'submit', 'id'=>'redcap_login_openid_Re8D2_8uiMn', 'name'=>'redcap_login_openid_Re8D2_8uiMn', 'value'=>$lang['config_functions_85']." ".RCView::escape($openid_provider_name)))
                        );
                        // Footer
                        print "<script type='text/javascript'> $(function(){ $('#footer').show() }); </script>";
                        $objHtmlPage->PrintFooterExt();
                        exit;
                    }
                } elseif($openid->mode == 'cancel') {
                    // echo 'User has canceled authentication!';
                } elseif ($openid->validate()) {
                    //echo 'User ' . ($openid->validate() ? $openid->identity . ' has ' : 'has not ') . 'logged in.';
                    // print_array($openid->getAttributes());
                    // print_array($openid->data);
                    // exit;
                    $openidAttr = $openid->getAttributes();
                    // Set email address as REDCap username and add it to the session
                    if (isset($openidAttr['contact/email'])) {
                        $userid = $_SESSION['username'] = $_SESSION['redcap_userid'] = $openidAttr['contact/email'];
                    } else {
                        // If did not return an email address, then use substring of end of openid_identity as a unique id
                        $userid = $_SESSION['username'] = $_SESSION['redcap_userid'] = "user_" . substr($openid->data['openid_identity'], -10);
                    }
                    // Log the login
                    Logging::logPageView("LOGIN_SUCCESS", $userid);
                    // Set the user's last_login timestamp
                    self::setUserLastLoginTimestamp($userid);
                }
            } catch(ErrorException $e) {
                // Error message if failed
                echo RCView::div(array('style'=>'padding:20px;'),
                    RCView::b("OpenID authentication error: ").$e->getMessage()
                );
            }
        }
        // Return userid
        return $userid;
    }


	// Authenticate via Google's OAuth2 - OpenID Connect
	private static function authenticateOpenIDGoogle()
	{
		// Get global vars
		global  $login_logo, $institution, $login_custom_text, $homepage_contact_email, $homepage_contact,
				$lang, $google_oauth2_client_id, $google_oauth2_client_secret;
		// Initialize $userid
		$userid = '';
		// If using OpenID for Google specifically, then manually set the provider URL and name
		$openid_provider_name = "Google";

		// Check session first
		if (isset($_SESSION['redcap_userid']) && !empty($_SESSION['redcap_userid']))
		{
			// If redcap_userid exists in the session, then user is authenticated and set it as their REDCap username
			$userid = $_SESSION['username'] = $_SESSION['redcap_userid'];
		}
		else
		{
			// Set up Google Client for authentication
			$client = new Google_Client();
			$client->setClientId($google_oauth2_client_id);
			$client->setClientSecret($google_oauth2_client_secret);
			$client->setRedirectUri(APP_PATH_WEBROOT_FULL);
			$client->addScope('https://www.googleapis.com/auth/userinfo.email');

			// If just clicked button to navigate to OpenID provider, then redirect them to the provider
			if (isset($_POST['redcap_login_openid_Re8D2_8uiMn'])) {
				// Add current URL to session temporarily so we can redirect to it after we authenticate with Google
				$_SESSION['redcap_page'] = $_SERVER['REQUEST_URI'];
				// Redirect to Google page to authenticate
				redirect($client->createAuthUrl());
			}
			// If we have a code back from the OAuth 2.0 flow, we need to exchange that with the authenticate()
			// function. We store the resultant access token bundle in the session, and redirect to ourself.
			elseif (isset($_GET['code'])) {
				// Authenticate the user
				$client->authenticate($_GET['code']);
				// Get user's email address from Google Account and set as REDCap userid
				$service = new Google_Service_Oauth2($client);
				$userinfo = $service->userinfo->get();
				$userid = $_SESSION['username'] = $_SESSION['redcap_userid'] = $userinfo->email;
				// Auto-set email and name if this is the first time logging in
				$userinfo = User::getUserInfo($userid);
				if ($userinfo['user_email'] == '') {
					global $allow_create_db_default, $default_datetime_format, $default_number_format_decimal, $default_number_format_thousands_sep;
					$sql = "insert into redcap_user_information (username, user_email, user_firstname, user_lastname, user_firstvisit,
							allow_create_db, user_creation, datetime_format, number_format_decimal, number_format_thousands_sep) values
							('".db_escape($userid)."', '".db_escape($userinfo->email)."', '".db_escape($userinfo->givenName)."',
							'".db_escape($userinfo->familyName)."', '".NOW."', $allow_create_db_default, '".NOW."', '".db_escape($default_datetime_format)."',
							'".db_escape($default_number_format_decimal)."', '".db_escape($default_number_format_thousands_sep)."')
							on duplicate key
							update user_email = '".db_escape($userinfo->email)."', user_firstname = '".db_escape($userinfo->givenName)."',
							user_lastname = '".db_escape($userinfo->familyName)."', ui_id = LAST_INSERT_ID(ui_id)";
					db_query($sql);
                    // Email admin and/or user about new account?
                    if (db_affected_rows() > 0) {
                        User::notifyAdminNewUser($userid);
                        User::notifyNewUserWelcomeEmail($userid);
                    }
				}
				// Log that they successfully logged in in log_view table
				Logging::logPageView("LOGIN_SUCCESS", $userid);
				// Set the user's last_login timestamp
				self::setUserLastLoginTimestamp($userid);
				// Redirect back to main page
				if (isset($_SESSION['redcap_page'])) {
					$redirect = $_SESSION['redcap_page'];
					unset($_SESSION['redcap_page']);
				} else {
					$redirect = APP_PATH_WEBROOT_FULL;
				}
				redirect($redirect);
			}
			// If just coming into REDCap, give notice page that they'll need to authenticate via OpenID
			else {
				// Header
				$objHtmlPage = new HtmlPage();
				$objHtmlPage->PrintHeaderExt();
				// If using OpenID for Google specifically, then display Google logo
				$openid_logo = RCView::img(array('src'=>'google_logo.png', 'style'=>'vertical-align:bottom;margin-right:5px;'));
				// Logo and "log in" text
				print 	RCView::div('', RCView::img(array('src'=>'redcap-logo-large.png')));
				print 	RCView::h4(array('style'=>'margin:20px 0;padding:3px;border-bottom:1px solid #AAAAAA;color:#000000;font-weight:bold;'),
							$openid_logo . $lang['config_functions_45']
						);
				// Institutional logo (optional)
				if (trim($login_logo) != "") {
					print RCView::div(array('style'=>'margin-bottom:20px;text-align:center;'),
							"<img src='$login_logo' title=\"".js_escape2($institution)."\" alt=\"".js_escape2($institution)."\" style='max-width:850px;'>"
						  );
				}
				// Show custom login text (optional)
				if (trim($login_custom_text) != "") {
					print RCView::div(array('style'=>'border:1px solid #ccc;background-color:#f5f5f5;margin:15px 10px 15px 0;padding:10px;'), nl2br(decode_filter_tags($login_custom_text)));
				}
				// Login instructions
				print 	RCView::p(array('style'=>'margin:10px 0 30px;'),
							$lang['config_functions_84'] . " " .
							RCView::span(array('style'=>'color:#800000;font-weight:bold;font-size:13px;'), RCView::escape($openid_provider_name)) . $lang['period'] . " " .
							$lang['config_functions_86'] . " " .
							$lang['config_functions_83'] . " " .
							RCView::a(array('style'=>'text-decoration:underline;', 'href'=>"mailto:$homepage_contact_email"), $homepage_contact) .
							$lang['period']
						);
				// Check for Google Oauth2 client ID and secret
				if (self::checkGoogleOauth2ClientIdSecret()) {
					// Form with submit button
					print 	RCView::form(array('method'=>'post','action'=>$_SERVER['REQUEST_URI'],'style'=>'text-align:center;margin:20px 0 40px;'),
								RCView::input(array('class'=>'jqbuttonmed', 'style'=>'padding:5px 10px !important;font-size:13px;', 'type'=>'submit', 'id'=>'redcap_login_openid_Re8D2_8uiMn', 'name'=>'redcap_login_openid_Re8D2_8uiMn', 'value'=>$lang['config_functions_85']." ".RCView::escape($openid_provider_name)))
							);
				} else {
					// Error message because we're missing Google Oauth2 client id and secret
					print 	RCView::div(array('class'=>'red', 'style'=>'margin:20px 0 30px;'),
								RCView::hidden(array('id'=>'redcap_login_openid_Re8D2_8uiMn')) . // Hidden input to prevent auto logout dialog from displaying unnecessarily
								RCView::img(array('src'=>'exclamation.png')) .
								RCView::b($lang['global_01'] . $lang['colon'] . " ") .
								RCView::b("There is an issue with Google OAuth2 that needs to be addressed.") .
								RCView::div(array('style'=>'margin-top:10px;'),
									"Google has deprecated OpenID 2.0,
									which was used by REDCap in versions prior to REDCap 6.5.0. Google OAuth2 Connect (OAuth2) now serves as its replacement and requires
									that you first set up some things on the Google Developers Console webpage before you can continue using Google OAuth2 for
									authentication in REDCap. We apologize for this inconvenience. If you are a REDCap administrator, please
									follow the instructions below to quickly address this issue, after which your REDCap authentication
									will continue to work in the same way that it has previously."
								) .
								RCView::div(array('class'=>'hang', 'style'=>'font-weight:bold;margin-top:20px;'),
									"Instructions for REDCap administrators only:"
								) .
								RCView::div(array('class'=>'hang', 'style'=>'margin-top:10px;'),
									"1.) Go to the " .
									RCView::a(array('href'=>'https://console.developers.google.com', 'style'=>'', 'target'=>'_blank'),
										"Google Developers Console"
									) .
									" and create a new project on that page (you may name it whatever you wish (e.g. \"REDCap Auth\"). Note: You may want to avoid logging in
									to that page using your personal Google account since you will be creating Google API credentials used by REDCap at your institution."
								) .
								RCView::div(array('class'=>'hang', 'style'=>'margin-top:10px;'),
									"2.) Once you have created the new project on the Google Developers Console webpage,
									select 'Credentials' under the 'APIs & auth' section on the left-hand menu. On the Credentials page
									under the OAuth section, click the button to create a new Client ID, and select
									the 'web application' as the application type. On the next screen, all you have to provide is the Product Name (e.g., 'REDCap'),
									and click the Save button. You may leave the 'Authorized JavaScript Origins' box blank (it is not needed here), but be sure to enter the URL below
									into the 'Authorized Redirect URIs' text box, otherwise the REDCap login process will not work."
								) .
								RCView::div(array('style'=>'font-weight:bold;margin-top:8px;margin-left:1.8em;'),
									"Authorized Redirect URIs:" . RCView::br() .
									RCView::textarea(array('readonly'=>'readonly', 'style'=>'font-size:12px;width:90%;height:22px;', 'onclick'=>'this.select();'), APP_PATH_WEBROOT_FULL)
								) .
								RCView::div(array('class'=>'hang', 'style'=>'margin-top:10px;'),
									"3.) Once the Client ID has been created, it will auto-generate a Client ID name and a Client Secret.
									Copy the SQL queries from the box below and replace &lt;YOUR_CLIENT_ID&gt; and &lt;YOUR_CLIENT_SECRET&gt; with your
									Client ID and Client Secret, respectively, and then execute that SQL in your MySQL database where the
									REDCap database tables are located."
								) .
								RCView::div(array('style'=>'font-weight:bold;margin-top:8px;margin-left:1.8em;'),
									"Execute these SQL queries in MySQL after replacing the ID and Secret values:" . RCView::br() .
									RCView::textarea(array('readonly'=>'readonly', 'style'=>'font-size:12px;width:95%;height:40px;', 'onclick'=>'this.select();'),
										"UPDATE redcap_config SET value = '<YOUR_CLIENT_ID>' WHERE field_name = 'google_oauth2_client_id';\nUPDATE redcap_config SET value = '<YOUR_CLIENT_SECRET>' WHERE field_name = 'google_oauth2_client_secret';"
									)
								) .
								RCView::div(array('class'=>'hang', 'style'=>'margin-top:10px;margin-bottom:20px;'),
									"4.) Once all the steps above have been completed, reload this page, and you should be able to log in as normal."
								)
							);
				}
				// Footer
				print "<script type='text/javascript'> $(function(){ $('#footer').show() }); </script>";
				$objHtmlPage->PrintFooterExt();
				exit;
			}
		}
		// Return userid
		return $userid;
	}


	/**
	 * HASH A USER'S PASSWORD USING SET PASSWORD ALGORITHM IN REDCAP_CONFIG
	 * Input the password and EITHER the salt or userid of an existing user (in which we can fetch the salt from the redcap_auth table).
	 * Returns the hashed string of the password.
	 */
	public static function hashPassword($password, $salt='', $userid='')
	{
		global $password_algo;
		// If missing necessary components, return false
		if ($salt == '' && $userid == '') return false;
		// If have userid, then get salt from table
		if ($salt == '' && $userid != '') {
			// Salted SHA hash used
			$salt = self::getUserPasswordSalt($userid);
			// Determine if a user's password is using the old legacy hash or a newer salted hash
			if (self::isUserPasswordLegacy($userid)) {
				// Unsalted MD5 hash used, so simply return the MD5 of the password
				return md5($password . $salt);
			}
		}
		// Return hash by inputing password+salt
		return hash($password_algo, $password . $salt);
	}
	
	
	/**
	 * DETERMINE IF A USERNAME/PASSWORD PAIR ARE VALID FOR A TABLE-BASED USER
	 * Returns boolean
	 */
	public static function verifyTableUsernamePassword($userid='', $password='')
	{
		// If missing necessary components, return false
		if ($password == '' || $userid == '') return false;
		// Hash the password provided
		$hashedPassword = self::hashPassword($password, '', $userid);
		// Check if the hashed password is the same for this user in the redcap_auth table		
		$sql = "select password from redcap_auth where username = '".prep($userid)."'";
		$q = db_query($sql);
		// Return true if password matches
		return (db_result($q, 0)."" === $hashedPassword."");
	}


	/**
	 * Retrieve the password salt for a specific table-based user
	 */
	public static function getUserPasswordSalt($userid)
	{
		$sql = "select password_salt from redcap_auth where username = '".db_escape($userid)."'";
		$q = db_query($sql);
		return db_result($q, 0);
	}


	/**
	 * Determine if a user's password is using the old legacy hash or a newer salted hash.
	 * Returns boolean (true if using old legacy hash).
	 */
	public static function isUserPasswordLegacy($userid)
	{
		$sql = "select legacy_hash from redcap_auth where username = '".db_escape($userid)."'";
		$q = db_query($sql);
		// Return salt
		return ($q && db_result($q, 0) == '1');
	}


	/**
	 * Generate a random password salt and return it
	 */
	public static function generatePasswordSalt()
	{
		$num_chars = 100;
		return generateRandomHash($num_chars, true);
	}


	/**
	 * Determine the highest SHA-based hashing algorithm for the server (up to SHA-512).
	 */
	public static function getBestHashAlgo()
	{
		// Put algos in an array
		$algos = hash_algos();
		// Put SHA algos in an array by number
		$sha_algos = array();
		// Loop through algos
		foreach ($algos as $algo) {
			// Ignore if not a SHA-based algo
			if (substr($algo, 0, 3) != 'sha') continue;
            // Ignore if contains other characters
            if (!is_numeric(substr($algo, 3))) continue;
			// Get SHA number
			$sha_num = substr($algo, 3)*1;
			// If higher than 512, then skip
			if ($sha_num > 512) continue;
			// Add SHA number to array
			$sha_algos[] = $sha_num;
		}
		// Return the highest SHA-based algorithm found (up to 512)
		return 'sha' . max($sha_algos);
	}


	/**
	 * Update a table-based user's hashed password and salt
	 */
	public static function setUserPasswordAndSalt($user, $hashed_password, $salt, $legacy_hash=0)
	{
		$sql = "update redcap_auth set password = '".db_escape($hashed_password)."', password_salt = '".db_escape($salt)."',
				legacy_hash = '".db_escape($legacy_hash)."' where username = '".db_escape($user)."'";
		return db_query($sql);
	}

	/**
	 * Set DSN array for LDAP auth
	 */
	public static function setDSNs()
	{
		global $lang, $auth_meth_global, $username, $password, $hostname, $db, $redcap_version,
			   $db_ssl_key, $db_ssl_cert, $db_ssl_ca, $db_ssl_capath, $db_ssl_cipher;
		// PEAR Auth
		if (!include_once 'Auth.php') {
			exit("{$lang['global_01']}{$lang['colon']} {$lang['config_functions_22']}");
		}
		// LDAP Connection Information for your Institution
		$GLOBALS['ldapdsn'] = array();
		if ($auth_meth_global == "ldap" || $auth_meth_global == "ldap_table") {
			include APP_PATH_WEBTOOLS . 'ldap/ldap_config.php';
		}
	}


	/**
	 * Set password reset key for inclusion in "password reset" email, and return the key
	 */
	public static function setPasswordResetKey($userid)
	{
		global $password_algo, $salt;
		// Generate key unique to user
		$password_reset_key = hash($password_algo, generateRandomHash(50, true) . $userid . $salt);
		// Save key in table
		$sql = "update redcap_auth set password_reset_key = '".db_escape($password_reset_key)."'
				where username = '".db_escape($userid)."'";
		$q = db_query($sql);
		// Return the key if successful, else return FALSE
		return ($q ? $password_reset_key : false);
	}


	/**
	 * Obtain the existing password reset key for inclusion in "password reset" email, and return the key
	 */
	public static function getPasswordResetKey($userid)
	{
		// Save key in table
		$sql = "select password_reset_key from redcap_auth where username = '".db_escape($userid)."'";
		$q = db_query($sql);
		$key = db_result($q, 0);
		// Return the key if successful, else return FALSE
		return ($q && $key != '' ? $key : false);
	}


	/**
	 * Verify the password reset key for a given user
	 */
	public static function verifyPasswordResetKey($userid, $key)
	{
		// Find in table
		$sql = "select 1 from redcap_auth where password_reset_key = '".db_escape($key)."' and username = '".db_escape($userid)."'";
		$q = db_query($sql);
		// Return the key if successful, else return FALSE
		return (db_num_rows($q) > 0);
	}


	/**
	 * Obtain the password reset URL for a user for inclusion in "password reset" email
	 */
	public static function getPasswordResetLink($userid, $generateNewKey=true)
	{
		global $redcap_version;
		// Generate key unique to user
		if ($generateNewKey) {
			$password_reset_key = self::setPasswordResetKey($userid);
		} else {
			// If there is no existing key in the db (but we do need one), generate a new one and add to db table
			$password_reset_key = self::getPasswordResetKey($userid);
			if ($password_reset_key === false) $password_reset_key = self::setPasswordResetKey($userid);
		}
		if ($password_reset_key === false) return false;
		// Generate base64 encoded username
		$user64 = base64_encode($userid);
		// Construct URL
		$url = APP_PATH_WEBROOT_FULL . "index.php?action=passwordreset&u=".rawurlencode($user64)."&k=".rawurlencode($password_reset_key);
		// Return the URL
		return $url;
	}


	/**
	 * Build the SMS_URL value to be used for the Twilio 2FA number
	 */
	public static function getTwilioTwoFactorSuccessSmsUrl()
	{
		// Make sure this constant is defined. If not, then call init global file.
		if (!defined('APP_PATH_SURVEY_FULL')) {
			// Init global
			require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
		}
		// Return URL
		return APP_PATH_SURVEY_FULL . "index.php?" . self::TWILIO_2FA_SUCCESS_FLAG . "=1";
	}


	/**
	 * Test Twilio credentials for Two Factor Authentication's SMS option.
	 * Returns TRUE if all is okay, but if not, returns error message (string).
	 */
	public static function testTwilioCrendentialsTwoFactor($sid, $token, $phone_number, $updateSmsUrl=false)
	{
		global $lang;
		// Make sure we have all we need
		$sid = trim($sid);
		$token = trim($token);
		$phone_number = trim($phone_number);
		if ($sid == '' || $token == '' || $phone_number == '') return $lang['system_config_363'];
		// Instantiate a new Twilio Rest Client
		$twilioClient = new Services_Twilio($sid, $token);
		// Error msg
		$error_msg = "";
		// SET URLS: Loop over the list of numbers and get the sid of the phone number
		$numberBelongsToAcct = false;
		$allNumbers = array();
		try {
			foreach ($twilioClient->account->incoming_phone_numbers as $number) {
				// Collect number in array
				$allNumbers[] = $number->phone_number;
				// If number does not match, then skip
				if (substr($number->phone_number, -1*strlen($phone_number)) != $phone_number) {
					continue;
				}
				// We verified that the number belongs to this Twilio account
				$numberBelongsToAcct = true;
				// Check SmsUrl: Set SmsUrl for this number, if not set yet, so that it will allow SMS response to let the user in
				if ($number->sms_url != self::getTwilioTwoFactorSuccessSmsUrl()) {
					if ($updateSmsUrl) {
						// Update the value
						$number->update(array("SmsUrl"=>self::getTwilioTwoFactorSuccessSmsUrl()));
					} else {
						// Return an error msg to report this issue
						$error_msg = $lang['system_config_484'];
					}
				}
			}
			// If number doesn't belong to account
			if (!$numberBelongsToAcct) {
				// Set error message
				$error_msg = $lang['survey_920'];
				if (empty($allNumbers)) {
					$error_msg .= RCView::div(array('style'=>'margin-top:10px;font-weight:bold;'), $lang['survey_843']);
				} else {
					$error_msg .= RCView::div(array('style'=>'margin-top:5px;font-weight:bold;'), " &nbsp; " . implode("<br> &nbsp; ", $allNumbers));
				}
			}
		} catch (Exception $e) {
			// Set error message
			$error_msg = $lang['survey_919'];
		}
		// Return TRUE or error msg
		return ($error_msg == '' ? true : $error_msg);
	}


	// Check for Google Oauth2 client ID and secret
	private static function checkGoogleOauth2ClientIdSecret()
	{
		global $google_oauth2_client_id, $google_oauth2_client_secret;
		// If we have the client id and secret, then return true, else false
		return ($google_oauth2_client_id != '' && $google_oauth2_client_secret != '');
	}


	// If Two Factor is using IP exceptions, then check if IP is in the range of exceptions.
	// Return TRUE if IP range is not enabled OR if IP range is enabled but the user's IP is outside the range.
	public static function enforceTwoFactorByIP()
	{
		global $two_factor_auth_ip_check_enabled, $two_factor_auth_ip_range, $two_factor_auth_ip_range_include_private;
		// If range check not enabled, then return true
		if (!$two_factor_auth_ip_check_enabled) return true;
		// Parse all IP ranges to check into an array
		$ip_ranges = explode(",", $two_factor_auth_ip_range);
		// If including the private ranges, then add them to the array
		if ($two_factor_auth_ip_range_include_private) {
			$ip_ranges = array_merge($ip_ranges, explode(",", self::PRIVATE_IP_RANGES));
		}
		// If user's IP is not in a range, then return TRUE so that 2FA is enforced
		return (!self::ip_in_ranges(System::clientIpAddress(), $ip_ranges));
	}


	// Determine if IP address is within at least one range in an array of ranges.
	// Return true if IP is in at least one range.
	public static function ip_in_ranges($ip, $ip_ranges=array())
	{
		// Validate inputs
		if (!is_array($ip_ranges) || $ip == '') return false;
		// Loop through each IP range. If IP is in *any* range, then return FALSE.
		foreach ($ip_ranges as $range) {
            $range = trim($range);
			if ($range == '') continue;
            if ($ip == $range)  return true;  // return true if ip address is same as one of the ip in list
			if (self::ip_in_range($ip, $range)) return true;
		}
		// Not in any range provide, so return false.
		return false;
	}


	// ip_in_range
	// This function takes 2 arguments, an IP address and a "range" in several
	// different formats.
	// Network ranges can be specified as:
	// 1. Wildcard format:     1.2.3.*
	// 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
	// 3. Start-End IP format: 1.2.3.0-1.2.3.255
	// The function will return true if the supplied IP is within the range.
	// Note little validation is done on the range inputs - it expects you to
	// use one of the above 3 formats.
	public static function ip_in_range($ip, $range)
	{
	    // IPv6
		if (Requests_IPv6::check_ipv6($ip))
		{
		    try {
		        return CIDR::match($ip, $range);
			} catch (TypeError $e) {
				return false;
			}
        }

	    // IPv4
	  if (strpos($range, '/') !== false) {
		// $range is in IP/NETMASK format
		list($range, $netmask) = explode('/', $range, 2);
		if (strpos($netmask, '.') !== false) {
		  // $netmask is a 255.255.0.0 format
		  $netmask = str_replace('*', '0', $netmask);
		  $netmask_dec = ip2long($netmask);
		  return ( (ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec) );
		} else {
		  // $netmask is a CIDR size block
		  // fix the range argument
		  $x = explode('.', $range);
		  while(count($x)<4) $x[] = '0';
		  list($a,$b,$c,$d) = $x;
		  $range = sprintf("%u.%u.%u.%u", empty($a)?'0':$a, empty($b)?'0':$b,empty($c)?'0':$c,empty($d)?'0':$d);
		  $range_dec = ip2long($range);
		  $ip_dec = ip2long($ip);

		  # Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
		  #$netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));

		  # Strategy 2 - Use math to create it
		  $wildcard_dec = pow(2, (32-$netmask)) - 1;
		  $netmask_dec = ~ $wildcard_dec;

		  return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
		}
	  } else {
		// range might be 255.255.*.* or 1.2.3.0-1.2.3.255
		if (strpos($range, '*') !==false) { // a.b.*.* format
		  // Just convert to A-B format by setting * to 0 for A and 255 for B
		  $lower = str_replace('*', '0', $range);
		  $upper = str_replace('*', '255', $range);
		  $range = "$lower-$upper";
		}

		if (strpos($range, '-')!==false) { // A-B format
		  list($lower, $upper) = explode('-', $range, 2);
		  $lower_dec = (float)sprintf("%u",ip2long($lower));
		  $upper_dec = (float)sprintf("%u",ip2long($upper));
		  $ip_dec = (float)sprintf("%u",ip2long($ip));
		  return ( ($ip_dec>=$lower_dec) && ($ip_dec<=$upper_dec) );
		}

		// Range argument is not in 1.2.3.4/24 or 1.2.3.4/255.255.255.0 format
		return false;
	  }
	}


	// Add Two Factor SMS response reference for a given phone number to the redcap_two_factor_response table
	public static function addTwoFactorCodeForPhoneNumber($phone)
	{
		// Remove all non-numeral characters
		$phone = preg_replace('/[^0-9]+/', '', $phone);
		if ($phone == '') return null;
		// Remove "1" as U.S. prefix
		if (strlen($phone) == 11 && substr($phone, 0, 1) == '1') {
			$phone = substr($phone, 1);
		}
		// Get user's ui_id
		$user_info = User::getUserInfo(USERID);
		// Add to table
		$sql = "insert into redcap_two_factor_response (user_id, time_sent, phone_number)
				values ('".db_escape($user_info['ui_id'])."', '".db_escape(NOW)."', '".db_escape($phone)."')";
		$q = db_query($sql);
		// Return false on fail or auto increment id on success
		return ($q ? db_insert_id() : false);
	}


	// Verify the Two Factor SMS Code for a given phone number from the redcap_two_factor_response table
	// and set it as "verified" in the table.
	public static function verifyTwoFactorCodeForPhoneNumber($phone)
	{
		// Remove all non-numeral characters
		$phone = preg_replace('/[^0-9]+/', '', $phone);
		if ($phone == '') return null;
		// Remove "1" as U.S. prefix
		if (strlen($phone) == 11 && substr($phone, 0, 1) == '1') {
			$phone = substr($phone, 1);
		}
		// Update only the most recent entry for this phone number
		$sql = "update redcap_two_factor_response set verified = '1'
				where phone_number = '".db_escape($phone)."' order by tf_id desc limit 1";
		// Return true on success or false on fail
		return db_query($sql);
	}


	// Using the tf_id key, check if the Two Factor SMS Code for a given phone number from the redcap_two_factor_response table
	// has been set as "verified" in the table for the current user. Only check rows with timestamp within the last minute from now.
	public static function checkVerifiedTwoFactorCodeByTwcId($tf_id)
	{
		// Make sure numeric
		if (!is_numeric($tf_id)) return false;
		// Get user's ui_id
		$user_info = User::getUserInfo(USERID);
		// Set timestamp of one minute ago
		$oneMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-1,date("s"),date("m"),date("d"),date("Y")));
		// Add to table
		$sql = "select 1 from redcap_two_factor_response where tf_id = $tf_id and user_id = '".db_escape($user_info['ui_id'])."'
				and verified = 1 and time_sent > '$oneMinAgo'";
		$q = db_query($sql);
		// Return true if verified
		return (db_num_rows($q) > 0);
	}


	// When a user is logging in via 2FA Twilio Phone Call option, return TWIML saying that
	// user just needs to press a key to complete the login process.
	public static function outputTwoFactorPhoneCallTwiml()
	{
		// Set text to be spoken to user
		$spoken_text = "If you were expecting this call, press any key on your phone's keypad. Otherwise, please hang up.";
		// Init global
		require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
		// Set TWIML
		$twiml = new Services_Twilio_Twiml();
		// Set the survey URL that Twilio will make the request to
		$question_url = self::getTwilioTwoFactorSuccessSmsUrl();
		// Set the gather array params
		$gather_params = array('method'=>'POST', 'action'=>$question_url, 'timeout'=>5, 'numDigits'=>'1');
		$say_array = array('voice'=>'alice', 'language'=>'en-US');
		// Pause at first to give user time to get phone to their ear
		$twiml->pause("");
		// Ask question
		$gather = $twiml->gather($gather_params);
		$gather->say($spoken_text, $say_array);
		// Do another instance of the same text to give user more initial time to respond
		$gather = $twiml->gather($gather_params);
		$gather->say($spoken_text, $say_array);
		// Return TWIML
		print $twiml;
	}


	// Thank the user after entering a value on their phone's keyapad when logging in via 2FA Twilio Phone Call option
	public static function outputTwoFactorPhoneCallTwimlThankYou()
	{
		// Set text to be spoken to user
		$spoken_text = "Thank you. Goodbye.";
		// Init global
		require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
		// Set TWIML
		$twiml = new Services_Twilio_Twiml();
		$twiml->say($spoken_text, array('voice'=>'alice', 'language'=>'en-US'));
		// Output the Twiml
		print $twiml;
	}


	// Return boolean if authentication is an "X & Table-based" method
	public static function usingXandTableBasedAuth()
	{
        global $auth_meth_global;
        return !($auth_meth_global == "table" || strpos($auth_meth_global, "table") === false);
	}

    // Extract user info from $_SERVER superglobal and update/set user information if needed
    public static function setShibbolethUserInfo($userid, $force_update)
    {
        $rc_userinfo = User::getUserInfo($userid);
		$user_does_not_exist = $rc_userinfo['user_email'] == '';
        $update_info = $user_does_not_exist || $force_update;

        // No update needed
        if (!$update_info) {
            return false;
        }

        global $allow_create_db_default, $default_datetime_format, $default_number_format_decimal, $default_number_format_thousands_sep;

        $GLOBALS['shibboleth_user_firstname_field'] = trim($GLOBALS['shibboleth_user_firstname_field']);
        $GLOBALS['shibboleth_user_lastname_field'] = trim($GLOBALS['shibboleth_user_lastname_field']);
        $GLOBALS['shibboleth_user_email_field'] = trim($GLOBALS['shibboleth_user_email_field']);

        // Server fields
        $user_email_field = $GLOBALS['shibboleth_user_email_field'] ?: 'mail';
        $user_firstname_field = $GLOBALS['shibboleth_user_firstname_field'] ?: 'givenName';
        $user_lastname_field = $GLOBALS['shibboleth_user_lastname_field'] ?: 'sn';

        // User information (fall back on existing values if new ones are blank)
        $user_email = $_SERVER[$user_email_field] ?: $rc_userinfo['user_email'];
        $user_firstname = $_SERVER[$user_firstname_field] ?: $rc_userinfo['user_firstname'];
        $user_lastname = $_SERVER[$user_lastname_field] ?:$rc_userinfo['user_lastname'];

        $sql = "insert into redcap_user_information (username, user_email, user_firstname, user_lastname, user_firstvisit,
                allow_create_db, user_creation, datetime_format, number_format_decimal, number_format_thousands_sep,
                super_user, account_manager, access_system_config, access_system_upgrade, access_external_module_install, 
                admin_rights, access_admin_dashboards) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                on duplicate key
                update user_email = ?, user_firstname = ?,
                user_lastname = ?, ui_id = LAST_INSERT_ID(ui_id)";
        $parameters = [ 
            $userid, $user_email, $user_firstname, $user_lastname, NOW, $allow_create_db_default, NOW, 
            $default_datetime_format, $default_number_format_decimal, $default_number_format_thousands_sep, '0', '0', 
            '0', '0', '0', '0', '0', $user_email, $user_firstname, $user_lastname 
        ];
        db_query($sql, $parameters);
        // Email admin and/or user about new account?
        if (db_affected_rows() > 0 && $user_does_not_exist) {
            User::notifyAdminNewUser($userid);
            User::notifyNewUserWelcomeEmail($userid);
        }
    }

	public static function anyShibbolethSitesDefined($shibboleth_table_config) {
		// Determine if there are Shibboleth sites defined in the Control Center
		$site_data          = $shibboleth_table_config['institutions'] ?? [];
		if (sizeof($site_data) === 0) {
			return false;
		}
		$first_login_option       = $site_data[0]['login_option'] ?? '';
		return trim($first_login_option) != '';
	}

	// If using external auth and user allowlist is enabled, then return whether userid is in allowlist
	// If not allowlist is disabled, return true
	public static function userInAllowList($userid) {
		global $enable_user_allowlist, $auth_meth_global;
		if (!$enable_user_allowlist) {
			return true;
		}
		if ($auth_meth_global != 'none' && $auth_meth_global != 'table')
		{
			$sql = "select 1 from redcap_user_allowlist where username = ?";
			return db_num_rows(db_query($sql, [$userid])) > 0;
		}
	}

}
