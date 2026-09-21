<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers;

interface UsernameMatcherInterface {

    /**
     *
     * @param UsernameMatcherInterface $handler
     * @return UsernameMatcherInterface
     */
    public function setNext($handler);

    /**
     *
     * @param string $redcapUsername
     * @param string $duoUsername
     * @return void
     */
    public function match($redcapUsername, $duoUsername);
}