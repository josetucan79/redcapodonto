<?php
namespace Vanderbilt\REDCap\Classes\AccountExpirationNotifier;

use User;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;


/**
 * Facade for creating an AccountExpirationNotifier based on REDcap configuration.
 */
class AccountExpirationNotifierFacade {
    public static function make($warningDays = null) {
        // Static number of days before expiration occurs to warn them (first warning, then second warning)
		$warningDays = $warningDays ?? [User::USER_EXPIRE_FIRST_WARNING_DAYS, User::USER_EXPIRE_SECOND_WARNING_DAYS]; // e.g. 14 days, then 2 days
        $redcapConfig = REDCapConfigDTO::fromDB();
        $templatefactory = new MessageTemplateFactory(
            $redcapConfig->user_custom_expiration_message,
            $redcapConfig->user_with_sponsor_custom_expiration_message
        );
        $messageFactory = new MessageFactory();
        $accountExpirationNotifier = new AccountExpirationNotifier($messageFactory, $templatefactory, $warningDays);
        return $accountExpirationNotifier;
    }
}
