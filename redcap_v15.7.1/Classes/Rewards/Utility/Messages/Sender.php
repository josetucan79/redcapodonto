<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\Messages;
class Sender {



    public function __construct() {

    }

    public function sendMessage(MessageInterface $message) {
        
        return $message->send();
    }

    public function addMessageToQueue() {

        
    }
}