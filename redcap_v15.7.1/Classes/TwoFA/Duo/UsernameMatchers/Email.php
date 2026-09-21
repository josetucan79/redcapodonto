<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers;

class Email extends UsernameMatcher {

    /**
     * compare removing domain and extension from the username 
     *
     * @param string $redcapUsername
     * @param string $duoUsername
     * @return bool
     */
    public function match($redcapUsername, $duoUsername) {
        $replaceCallback = function($matches) {
            return $matches['username'] ?? false;
        };
        $regExp = '(?<username>^[^@]+)@(?<domain>[^\.]+)\.(?<extension>.{2,4})';
        $normalized = preg_replace_callback("/$regExp/", $replaceCallback, $redcapUsername);
        $match = strcasecmp($normalized, $duoUsername)===0;
        if($match===true) return true;
        return parent::match($redcapUsername, $duoUsername);
    }

}