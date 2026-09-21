<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers;

class CaseInsensitive extends UsernameMatcher {

    /**
     * Compare Strings without Case Sensitivity 
     *
     * @param string $redcapUsername
     * @param string $duoUsername
     * @return void
     */
    public function match($redcapUsername, $duoUsername) {
        $match = strcasecmp($redcapUsername, $duoUsername)===0;
        if($match===true) return true;
        return parent::match($redcapUsername, $duoUsername);
    }

}