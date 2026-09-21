<?php
namespace Vanderbilt\REDCap\Classes\Utility\Sleeper;



/**
 * Interface for defining an interrupt condition callback for InterruptibleSleeper.
 */
interface StopSleepConditionInterface {
    /**
     * Determines whether the sleep should be interrupted based on the elapsed time.
     *
     * @param float $elapsedTime The elapsed time in seconds.
     * @return bool Returns true to interrupt the sleep, false to continue.
     */
    public function shouldStop($elapsedTime);
}
