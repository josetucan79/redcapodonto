<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class EmailReplacer extends BaseReplacer {

    public function __construct($useremail) {
        $this->value = $useremail;
    }

    public static function token() { return 'email'; }
}