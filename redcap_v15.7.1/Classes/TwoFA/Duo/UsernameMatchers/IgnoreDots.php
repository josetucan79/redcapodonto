<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers;

class IgnoreDots extends UsernameMatcher {

    /**
     * compare removing domain and extension from the username 
     *
     * @param string $redcapUsername
     * @param string $duoUsername
     * @return bool
     */
    public function match($redcapUsername, $duoUsername) {
        $regExp = '\.';
        $normalized = preg_replace("/$regExp/", '', $redcapUsername);
        $match = strcasecmp($normalized, $duoUsername)===0;
        if($match===true) return true;
        return parent::match($redcapUsername, $duoUsername);
    }


}