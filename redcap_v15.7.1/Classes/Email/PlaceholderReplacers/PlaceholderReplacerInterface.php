<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

interface PlaceholderReplacerInterface {

    /**
     *
     * @return string
     */
    public static function token();
    
    /**
     *
     * @param string $subject
     * @return string
     */
    public function replace($subject);

}
