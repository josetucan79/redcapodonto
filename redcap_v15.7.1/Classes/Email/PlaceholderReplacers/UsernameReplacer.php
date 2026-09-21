<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class UsernameReplacer extends BaseReplacer {
    use UserInformationTrait;

    public function __construct($useremail) {
        $this->value = $this->getUserFieldByMail($useremail, 'username');
    }

    public static function token() { return 'username'; }
}