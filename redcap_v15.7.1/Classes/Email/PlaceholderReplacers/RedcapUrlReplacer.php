<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class RedcapUrlReplacer extends BaseReplacer {

    public function __construct() {
        $this->value = defined('APP_PATH_WEBROOT_FULL') ? APP_PATH_WEBROOT_FULL : '';
    }

    public static function token() { return 'redcap_url'; }
}