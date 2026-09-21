<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Make sure that the 2FA verify code endpoint is not called more than X times per minute
if ($two_factor_auth_enabled && isset($_POST['code']) && !Authentication::checkRateLimitTwoFactorCodeVerify())
{
    print "2";
}
// Make sure two-factor is enabled and that we have the verification code
elseif ($two_factor_auth_enabled && isset($_POST['code']) && Authentication::verifyTwoFactorCode($_POST['code']))
{
	// Set session variable to denote that user has performed two factor auth
	Authentication::twoFactorLoginSuccess(strToUpper($_POST['twoFactorMethod']));
	// If user selected that this computer can be trusted, then set cookie
	Authentication::twoFactorSetTrustCookie($_POST['two_factor_auth_trust']);
    // Set if they have enrolled in two factor
    Authentication::setHasEnrolledForTwoFactorAuthentication(USERID);
	// Return success flag
	print "1";
}
else
{
	// Return error flag
	print "0";
}