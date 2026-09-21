<?php
namespace Vanderbilt\REDCap\Classes\AccountExpirationNotifier;

use Vanderbilt\REDCap\Classes\Utility\TemplateEngine;

/**
 * Factory for creating email body content based on user status and admin settings.
 */
class MessageTemplateFactory {
    /**
     * Custom text for emails sent to users without a sponsor.
     * @var string|null
     */
    private $customUserOnlyText;

    /**
     * Custom text for emails sent to users with a sponsor.
     * @var string|null
     */
    private $customUserWithSponsorText;

    /**
     *
     * @param string|null $customUserOnlyText Custom text for user-only emails.
     * @param string|null $customUserWithSponsorText Custom text for user-with-sponsor emails.

     */
    public function __construct($customUserOnlyText = '', $customUserWithSponsorText = '') {
        $this->customUserOnlyText = trim($customUserOnlyText) !== '' ? $customUserOnlyText : null;
        $this->customUserWithSponsorText = trim($customUserWithSponsorText) !== '' ? $customUserWithSponsorText : null;;
    }

    public function getMessageBody($user, $placeholders) {
        $template = $this->getTemplate($user);
        return TemplateEngine::render($template, $placeholders);
    }

    /**
     * Determines the appropriate email body based on user status and custom texts.
     *
     * @param array $user User data array.
     * @return string The email body content.
     */
    public function getTemplate($user) {
        if (isset($user['user_sponsor'])) {
            return $this->customUserWithSponsorText ?: $this->getDefaultUserWithSponsorEmail($user);
        } else {
            return $this->customUserOnlyText ?: $this->getDefaultUserOnlyEmail($user);
        }
    }

    // Return the default email body for users without a sponsor
    public function getDefaultUserOnlyEmail($user) {
        global $lang;

        $user_firstname_ph = Placeholders::USER_FIRSTNAME;
        $user_lastname_ph = Placeholders::USER_LASTNAME;
        $expiration_date_friendly_ph = Placeholders::EXPIRATION_DATE_FRIENDLY;
        $expiration_time_friendly_ph = Placeholders::EXPIRATION_TIME_FRIENDLY;
        $template =   "$lang[cron_02]<br><br>$lang[cron_03] \"<b>[username]</b>\"
                            (<b>[$user_firstname_ph] [$user_lastname_ph]</b>) $lang[cron_06]
                            <b>[$expiration_date_friendly_ph] ([$expiration_time_friendly_ph])</b>$lang[period]
                            $lang[cron_37] $lang[cron_24] <a href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a> $lang[cron_39]";
        return $template;
    }

    // Return the default email body for users with a sponsor
    public function getDefaultUserWithSponsorEmail($user) {
        global $lang, $user_sponsor_dashboard_enable;

        $user_firstname_ph = Placeholders::USER_FIRSTNAME;
        $user_lastname_ph = Placeholders::USER_LASTNAME;
        $expiration_date_friendly_ph = Placeholders::EXPIRATION_DATE_FRIENDLY;
        $expiration_time_friendly_ph = Placeholders::EXPIRATION_TIME_FRIENDLY;
        $sponsor_username_ph = Placeholders::SPONSOR_USERNAME;
        $sponsor_user_firstname_ph = Placeholders::SPONSOR_USER_FIRSTNAME;
        $sponsor_user_lastname_ph = Placeholders::SPONSOR_USER_LASTNAME;

        $template =  "$lang[cron_02]<br><br>$lang[cron_13] \"<b>[username]</b>\"
                            (<b>[$user_firstname_ph] [$user_lastname_ph]</b>) $lang[cron_06]
                            <b>[$expiration_date_friendly_ph] ([$expiration_time_friendly_ph])</b>$lang[period]
                            $lang[cron_37] $lang[cron_38] \"<b>[$sponsor_username_ph]</b>\"
                            (<b>[$sponsor_user_firstname_ph] [$sponsor_user_lastname_ph]</b>)$lang[cron_15]
                            <a href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a> $lang[cron_39]";
        if ($user_sponsor_dashboard_enable) {
            $template .= "<br><br>$lang[cron_40] <a href=\"".APP_PATH_WEBROOT_FULL."index.php?action=user_sponsor_dashboard\">$lang[rights_330]</a>$lang[period]";
        }
        return $template;
    }
}
