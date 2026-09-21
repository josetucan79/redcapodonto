<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers;

abstract class UsernameMatcher implements UsernameMatcherInterface {

    private $next = null;

    /**
     *
     * @param UsernameMatcherInterface $handler
     * @return UsernameMatcherInterface
     */
    public function setNext($handler) {
        $this->next = $handler;
        return $handler;
    }

    /**
     *
     * @param string $redcapUsername
     * @param string $duoUsername
     * @return boolean
     */
    public function match($redcapUsername, $duoUsername) {
        if ($this->next) {
            return $this->next->match($redcapUsername, $duoUsername);
        }

        return false;
    }
}