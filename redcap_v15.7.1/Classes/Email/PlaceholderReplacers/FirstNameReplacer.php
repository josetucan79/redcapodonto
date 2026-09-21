<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class FirstNameReplacer extends BaseReplacer {
    use UserInformationTrait;
    
    public function __construct($useremail) {
        $this->value = $this->getUserFieldByMail($useremail, 'user_firstname');
    }

    public static function token() { return 'first_name'; }
}