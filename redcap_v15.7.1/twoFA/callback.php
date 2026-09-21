<?php
namespace Vanderbilt\REDCap\TwoFA;

use System;
use Vanderbilt\REDCap\Classes\TwoFA\Duo\Duo;

require_once '../Config/init_global.php';

$config = System::getConfigVals();
$twoFactorAuthenticationType = strtolower(@$_GET['type']);

function authenticateWithDuo($config) {
    $two_factor_auth_duo_ikey = @$config['two_factor_auth_duo_ikey'];
    $two_factor_auth_duo_skey = @$config['two_factor_auth_duo_skey'];
    $two_factor_auth_duo_hostname = @$config['two_factor_auth_duo_hostname'];
    $facade = new Duo($two_factor_auth_duo_ikey, $two_factor_auth_duo_skey, $two_factor_auth_duo_hostname);
    # Check for errors from the Duo authentication
    if (isset($_GET["error"])) {
        $error_msg = @$_GET["error"] . ":" . @$_GET["error_description"];
        // $logger->error($error_msg); // log the error here
        return "Got Error: " . $error_msg;
    }

    # Get authorization token to trade for 2FA
    $code = @$_GET["duo_code"];

    # Get state to verify consistency and originality
    $state = @$_GET["state"];

    $facade->authenticate($state, $code);
}

try {
    switch ($twoFactorAuthenticationType) {
        case strtolower(Duo::TWO_FACTOR_METHOD_NAME):
            authenticateWithDuo($config);
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