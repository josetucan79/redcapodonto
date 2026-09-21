<?php

namespace Vanderbilt\REDCap\Classes\Fhir;

class EhrRedirectManager
{

    /**
     * Constant for the cookie name used to store the EHR redirect URL.
     */
    private const EHR_REDIRECT_COOKIE = 'ehr_redirect_url';

    /**
     * Checks if the current request was redirected from a SMART on FHIR authentication process.
     *
     * This method analyzes the HTTP referrer to determine if the request originated
     * from an EHR context, specifically checking if the URL path ends with 'ehr.php'.
     *
     * @return string|false Returns the referrer URL if it is an EHR context; otherwise, false.
     */
    public static function isEhrContext() {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $parsedUrl = parse_url($referrer);
        $path = $parsedUrl['path'];
        $isEHRContext = preg_match('/ehr\.php$/', $path) === 1;
        if (!$isEHRContext) return false;
        return $referrer;
    }

    public static function isCurrentPageEhr() {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        return preg_match('/ehr\.php$/', $scriptName) === 1;
    }
    

    /**
     * Detects and stores the EHR context URL during the authentication process.
     *
     * This method captures the EHR context URL, if applicable, and stores it in
     * a cookie to be used later for redirection after
     * successful authentication.
     *
     * @return void
     */
    public static function detectEhrContext() {
        // Capture the EHR context URL if applicable
        $userid = $_SESSION['username'] ?? null;
        if ($userid === null && ($ehrRedirectURL = self::isEhrContext())) {
            savecookie(self::EHR_REDIRECT_COOKIE, encrypt($ehrRedirectURL), 120);
        }
    }

    /**
     * Handles redirection to the EHR context URL after authentication.
     *
     * This method checks if the user is authenticated and if an EHR context URL is
     * stored in a cookie. If both conditions are met, it decrypts the URL and performs
     * a redirect, subsequently removing the cookie to prevent further use.
     *
     * @return void
     */
    public static function detectEhrRedirect() {
        $userid = $_SESSION['username'] ?? null;
        $encryptedUrl = $_COOKIE[self::EHR_REDIRECT_COOKIE] ?? null;
        if ($userid && $encryptedUrl) {
            $redirectUrl = decrypt($encryptedUrl);

            deletecookie(self::EHR_REDIRECT_COOKIE);
            redirect($redirectUrl);
        }
    }

    /**
     * Disables the EHR redirect by removing the associated cookie.
     *
     * This method ensures that `detectEhrRedirect` will no longer trigger a redirect.
     *
     * @return void
     */
    public static function disableEhrRedirect() {
        unset($_COOKIE[self::EHR_REDIRECT_COOKIE]);
        deleteCookie(self::EHR_REDIRECT_COOKIE);
    }


}