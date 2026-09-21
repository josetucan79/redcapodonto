<?php
namespace Vanderbilt\REDCap\Classes\AccountExpirationNotifier;

use Message;

/**
 * Factory for creating email body content based on user status and admin settings.
 */
class MessageFactory {

    /**
     *
     * @return Message
     */
    public static function make() {
        return new Message();
    }
}
