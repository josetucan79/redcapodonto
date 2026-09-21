<?php
namespace Vanderbilt\REDCap\Classes\Utility\Sleeper;

/**
 * InterruptibleSleeper
 *
 * Provides a sleep mechanism in PHP that can be interrupted based on a callback condition.
 * The callback is provided with the elapsed time to make more informed decisions.
 */
class StoppableSleeper {

    /**
     * Performs an interruptible sleep.
     *
     * @param int $seconds Number of seconds to sleep.
     * @return bool Returns true if completed without interruption, false if interrupted.
     */
    public static function sleep($seconds, $stopCondition = null) {
        $startTime = microtime(true);
        $endTime = $startTime + $seconds;
    
        while (microtime(true) < $endTime) {
            $currentTime = microtime(true);
            $elapsedTime = $currentTime - $startTime;
            // Check stop condition if it is provided
            if ($stopCondition !== null) {
                // Check if it's a callable
                if (is_callable($stopCondition)) {
                    if (call_user_func_array($stopCondition, [$elapsedTime])) {
                        return false;
                    }
                }
                // Check if it's an instance of StopSleepConditionInterface
                elseif ($stopCondition instanceof StopSleepConditionInterface) {
                    if ($stopCondition->shouldStop($elapsedTime)) {
                        return false;
                    }
                }
            }
    
            usleep(100000); // Sleep for 0.1 second at a time
        }
    
        return true;
    }
}
