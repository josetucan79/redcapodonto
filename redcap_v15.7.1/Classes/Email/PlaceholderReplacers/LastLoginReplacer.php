<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class LastLoginReplacer extends BaseReplacer {
    use UserInformationTrait;

    public function __construct($useremail) {
        $this->value = $this->getUserFieldByMail($useremail, 'user_lastlogin');
    }

    public static function token() { return 'last_login'; }
}