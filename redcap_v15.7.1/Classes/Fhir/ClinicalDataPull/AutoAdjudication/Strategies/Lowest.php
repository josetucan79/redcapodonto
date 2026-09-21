<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\Strategies;

use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\CacheEntry;

class Lowest  extends AdjudicationStrategy
{
  /**
   * compare values
   * the record with the lowest value is better
   *
   * @param CacheEntry $a
   * @param CacheEntry $b
   * @return int
   */
  public function compare($a,$b)
  {
    $value_a = $a->getValue();
    $value_b = $b->getValue();
    if($value_a<$value_b) return -1;
    if($value_a==$value_b) return 0;
    if($value_a>$value_b) return 1;
  }
  
}