<?php
namespace Vanderbilt\REDCap\Classes\TwoFA;

use Symfony\Component\Translation\Writer\TranslationWriterInterface;

/**
 * Facade for the Duo 2FA
 */
class AbstractTwoFactorAuthentication implements TwoFactorAuthenticationInterface {


    const TWO_FA_LAUNCH_ENDPOINT = "/twoFA/index.php";
    const TWO_FA_CALLBACK_ENDPOINT = "/twoFA/callback.php";
    
    /**
     * return the callback URL
     *
     * @return string
     */
    public static function getCallbackUri() {
        $allArgs = [
            'type' => static::getType(),
        ];
        $arguments = http_build_query($allArgs);
        return $redirectUri = APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . self::TWO_FA_CALLBACK_ENDPOINT . "?" . $arguments;
    }

    public static function getLaunchUri() {
        return $redirectUri = APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . self::TWO_FA_LAUNCH_ENDPOINT;
    }
    
    public static function getType(): string { return ''; }
}