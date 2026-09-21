<?php
namespace Vanderbilt\REDCap\TwoFA;

use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\TwoFA\Duo\Duo;

require_once '../Config/init_global.php';

$config = REDCapConfigDTO::fromDB();
$twoFactorAuthenticationType = strtolower($_GET['type'] ?? null);
$rememberMe = boolval($_GET['remember-me'] ?? false);

/**
 *
 * @param REDCapConfigDTO $config
 * @return void
 */
function launchDuo($config, $rememberMe) {
	$state = @$_GET['state'];
	$two_factor_auth_duo_ikey = $config->two_factor_auth_duo_ikey;
	$two_factor_auth_duo_skey = $config->two_factor_auth_duo_skey;
	$two_factor_auth_duo_hostname = $config->two_factor_auth_duo_hostname;
	$facade = new Duo($two_factor_auth_duo_ikey, $two_factor_auth_duo_skey, $two_factor_auth_duo_hostname);
	$facade->launchUniversalPrompt($state, $rememberMe);
}

try {
	switch ($twoFactorAuthenticationType) {
		case strtolower(Duo::TWO_FACTOR_METHOD_NAME):
			// $session_id = @$_GET['session_id']; // this is the URL encoded and encrypted session ID created in Authentication.php
			launchDuo($config, $rememberMe);
			break;
		default:
			die('please use a supported 2FA method');
			break;
	}
} catch (\Throwable $th) {
	$code = $th->getCode();
	$message = $th->getMessage();
	print("Error $code - $message");
}