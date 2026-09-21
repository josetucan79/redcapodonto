<?php
namespace Vanderbilt\REDCap\Classes\TwoFA;

/**
 * each 2FA provider must provide its name
 */
interface TwoFactorAuthenticationInterface {

    /**
     * return the type/name of the 2FA provider
     *
     * @return string
     */
    public static function getType(): string;

}