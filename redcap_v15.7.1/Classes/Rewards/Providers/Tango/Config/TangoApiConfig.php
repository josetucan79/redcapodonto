<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Config;

class TangoApiConfig
{
    // Environment Constants
    const ENVIRONMENT_PRODUCTION = 'production';
    const ENVIRONMENT_SANDBOX = 'sandbox';

    // Base URLs
    const BASE_URL_PRODUCTION = 'https://api.tangocard.com/raas/v2';
    const BASE_URL_SANDBOX = 'https://integration-api.tangocard.com/raas/v2';

    // Token URLs
    const TOKEN_URL_PRODUCTION = 'https://auth.tangocard.com/oauth/token';
    const TOKEN_URL_SANDBOX = 'https://sandbox-auth.tangocard.com/oauth/token';

    public static function getBaseUrl($environment)
    {
        switch ($environment) {
            case self::ENVIRONMENT_SANDBOX:
                return self::BASE_URL_SANDBOX;
            case self::ENVIRONMENT_PRODUCTION:
                return self::BASE_URL_PRODUCTION;
            default:
                return null;
        }
    }


    public static function getTokenUrl($environment)
    {
        switch ($environment) {
            case self::ENVIRONMENT_SANDBOX:
                return self::TOKEN_URL_SANDBOX;
            case self::ENVIRONMENT_PRODUCTION:
                return self::TOKEN_URL_PRODUCTION;
            default:
                return null;
        }
    }

    public static function getEnvironmentByTokenUrl($tokenUrl)
    {
        $normalizedTokenUrl = self::normalizeUrl($tokenUrl);
        
        switch ($normalizedTokenUrl) {
            case self::normalizeUrl(self::TOKEN_URL_PRODUCTION):
                return self::ENVIRONMENT_PRODUCTION;
            case self::normalizeUrl(self::TOKEN_URL_SANDBOX):
                return self::ENVIRONMENT_SANDBOX;
            default:
                return null;
        }
    }

    public static function getEnvironmentByBaseUrl($baseUrl)
    {
        $normalizedBaseUrl = self::normalizeUrl($baseUrl);
        
        switch ($normalizedBaseUrl) {
            case self::normalizeUrl(self::BASE_URL_PRODUCTION):
                return self::ENVIRONMENT_PRODUCTION;
            case self::normalizeUrl(self::BASE_URL_SANDBOX):
                return self::ENVIRONMENT_SANDBOX;
            default:
                return null;
        }
    }

    public static function getEnvironmentByUrl($url)
    {
        $normalizedUrl = self::normalizeUrl($url);

        if ($normalizedUrl === self::normalizeUrl(self::BASE_URL_PRODUCTION) || 
            $normalizedUrl === self::normalizeUrl(self::TOKEN_URL_PRODUCTION)) {
            return self::ENVIRONMENT_PRODUCTION;
        } elseif ($normalizedUrl === self::normalizeUrl(self::BASE_URL_SANDBOX) || 
                  $normalizedUrl === self::normalizeUrl(self::TOKEN_URL_SANDBOX)) {
            return self::ENVIRONMENT_SANDBOX;
        }

        return null;
    }


    private static function normalizeUrl($url)
    {
        $url = $url ?? '';
        return rtrim($url, '/');
    }

}
