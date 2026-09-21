<?php
namespace Vanderbilt\REDCap\Classes\Traits;

use DateTime;

trait CanMakeDateTimeFromInterval {
    

	  /**
     * get a DateTime to use as reference
     *
     * @param string $time_string
     * @return DateTime
     */
    public function getDateTimeFromInterval($time_string='30 minutes') {
        $start = new \DateTime();
        $max_execution = \DateInterval::createFromDateString($time_string);
        $max_time = $start->add($max_execution);
        return $max_time;
    }
}