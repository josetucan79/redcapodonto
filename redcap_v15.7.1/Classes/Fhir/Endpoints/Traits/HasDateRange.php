<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\Traits;

use DateTime;

trait HasDateRange
{

  
  /**
   * datetime in FHIR compatible format
   * 
   * @see https://www.hl7.org/fhir/datatypes.html#dateTime
   */
  private static $fhir_datetime_format = "Y-m-d\TH:i:s\Z";


  /**
   * Get the min and max date parameters.
   * check the 'fhir_convert_timestamp_from_gmt' system setting and performs
   * additions/sottactions accordingly
   *
   * @param DateTime $date_min
   * @param DateTime $date_max
   * @return array
   */
  protected static function getDateRangeQueryParams($date_min, $date_max)
  {
      $params = array();
      
      if ($date_min instanceof DateTime) {
          $params['date_min'] = "ge{$date_min->format(self::$fhir_datetime_format)}";
      }
      if ($date_max instanceof DateTime) {
          $params['date_max'] = "le{$date_max->format(self::$fhir_datetime_format)}";
      }
      return $params;
  }
}