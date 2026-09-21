<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\Strategies;

use DateTime;

trait CanTransformTimestamps
{
  private function stringToDateTime($string, $format='Y-m-d H:i:s')
  {
    if(empty($string)) return false;
    $date_time = DateTime::createFromFormat($format, $string);
    if($date_time instanceof DateTime) return $date_time;
    return false;  
  }
}