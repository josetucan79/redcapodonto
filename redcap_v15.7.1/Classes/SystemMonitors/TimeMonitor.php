<?php
namespace Vanderbilt\REDCap\Classes\SystemMonitors;

use Vanderbilt\REDCap\Classes\Traits\CanMakeDateTimeFromInterval;

class TimeMonitor {
    use CanMakeDateTimeFromInterval;

    const DEFAULT_THRESHOLD = '30 minutes';

    private $startTime;
    private $thresholdInSeconds;

    public function __construct($threshold = self::DEFAULT_THRESHOLD)
    {
        $this->startTime = time();  // Record the time when the monitor starts
        $this->setTimeThreshold($threshold);
    }

    /**
     * Reset the start time
     *
     * @return void
     */
    public function setTimeThreshold($threshold)
    {
        if (is_int($threshold)) {
            // If the threshold is an integer, assume it's in seconds
            $this->thresholdInSeconds = $threshold;
        } elseif (is_string($threshold)) {
            // If the threshold is a string, convert it to seconds using the trait
            $max_time = $this->getDateTimeFromInterval($threshold);
            $this->thresholdInSeconds = $max_time->getTimestamp() - time();  // Convert the future DateTime to seconds
        }
    }


    /**
     * Return true if within threshold
     *
     * @return boolean
     */
    public function withinThreshold()
    {
        $currentTime = time();  // Get the current time
        $elapsedTime = $currentTime - $this->startTime;  // Calculate how much time has passed

        return $elapsedTime < $this->thresholdInSeconds;
    }

    /**
     * Return true if not within threshold
     *
     * @return boolean
     */
    public function hasExceededThreshold()
    {
        return !$this->withinThreshold();
    }

    /**
     * Return the elapsed time since the start
     *
     * @return int
     */
    public function getElapsedTime()
    {
        return time() - $this->startTime;
    }

    /**
     * Reset the start time
     *
     * @return void
     */
    public function resetStartTime()
    {
        $this->startTime = time();
    }
}
