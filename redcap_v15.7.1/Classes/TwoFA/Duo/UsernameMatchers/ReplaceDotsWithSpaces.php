<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers;

class ReplaceDotsWithSpaces extends UsernameMatcher {

    /**
     * compare replacing dots with spaces 
     *
     * @param string $redcapUsername
     * @param string $duoUsername
     * @return bool
     */
    public function match($redcapUsername, $duoUsername) {
        $regExp = '\.';
        $normalized = preg_replace("/$regExp/", ' ', $redcapUsername);
        $match = strcasecmp($normalized, $duoUsername)===0;
        if($match===true) return true;
        return parent::match($redcapUsername, $duoUsername);
    }


}