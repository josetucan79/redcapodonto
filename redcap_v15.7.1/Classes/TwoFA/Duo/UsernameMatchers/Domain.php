<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers;

class Domain extends UsernameMatcher {

    /**
     * Compare Strings removing the domain 
     *
     * @param string $redcapUsername
     * @param string $duoUsername
     * @return bool
     */
    public function match($redcapUsername, $duoUsername) {
        $replaceCallback = function($matches) {
            return $matches['username'] ?? false;
        };
        $regExp = '(?<domain>.+\\)(?<username>.+)';
        $normalized = preg_replace_callback("/$regExp/", $replaceCallback, $redcapUsername);
        $match = strcasecmp($normalized, $duoUsername)===0;
        if($match===true) return true;
        return parent::match($redcapUsername, $duoUsername);
    }


}