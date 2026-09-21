<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Traits;

use DateTime;

trait HasDates
{
  private static $fhir_date_format = 'Y-m-d\TH:i:s\Z';

  private function getDateFromString($string)
  {
    $date = DateTime::createFromFormat($this->fhir_date_format, $string);
    return $date;
  }
}