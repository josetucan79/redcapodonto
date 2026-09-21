<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class LastNameReplacer extends BaseReplacer {
    use UserInformationTrait;

    public function __construct($useremail) {
        $this->value = $this->getUserFieldByMail($useremail, 'user_lastname');
    }

    public static function token() { return 'last_name'; }
}