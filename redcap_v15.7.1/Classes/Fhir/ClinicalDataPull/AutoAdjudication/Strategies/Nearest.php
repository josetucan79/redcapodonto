<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\Strategies;

use DateTime;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\CacheEntry;

class Nearest  extends AdjudicationStrategy
{
  use CanTransformTimestamps;


  /**
   * calculate the absolute time difference between
   * the reference date and the provided date
   *
   * @param DateTime $date_time
   * @return DateInterval
   */
  protected function getAbsoluteTimeDifference($date_time)
  {
    $reference_date = $this->cacheEntry->temporal_value;
    $absolute = true;
    $absolute_diff = $reference_date->diff($date_time, $absolute);
    return $absolute_diff;
  }

  /**
   * compare values
   * the record with the nearest date is better
   *
   * @param CacheEntry $a
   * @param CacheEntry $b
   * @return int
   */
  public function compare($a, $b)
  {
    $offset_a = $a->offset_seconds;
    $offset_b = $b->offset_seconds;
    if($offset_a===false && $offset_b===false) throw new \Exception('valid timestamps from the FHIR source data are needed to preselect a value', 1);
    if($offset_b===false) return -1; // a is the best option because contains a valid timestamp
    if($offset_a===false) return 1; // b is the best option because contains a valid timestamp

    $absolute_offset_a = abs($offset_a);
    $absolute_offset_b = abs($offset_b);
    
    if($absolute_offset_a==$absolute_offset_b) return 0;
    if($absolute_offset_a<$absolute_offset_b) return -1;
    if($absolute_offset_a>$absolute_offset_b) return 1;
  }
  
}