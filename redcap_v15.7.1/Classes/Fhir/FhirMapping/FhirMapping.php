<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMapping;

use DateTime;
use JsonSerializable;
use Vanderbilt\REDCap\Classes\Traits\CanMakeDateTime;

/**
 * define a set of options that will be used
 * to retrieve data from the FHIR system
 */
class FhirMapping implements JsonSerializable
{
  use CanMakeDateTime;

  const DATE_FORMAT = 'Y-m-d H:i:s';

  protected $name;
  protected $dateMin;
  protected $dateMax;

  public function __construct($name, $dateMin=null, $dateMax=null) {
    $this->name = $name;
    $this->dateMin = $this->makeDateTime($dateMin);
    $this->dateMax = $this->makeDateTime($dateMax);
  }


  /**
   *
   * @return string
   */
  public function getName() { return $this->name; }

  /**
   *
   * @return DateTime|null
   */
  public function getDateMin() { return $this->dateMin; }

  /**
   *
   * @return DateTime|null
   */
  public function getDateMax() { return $this->dateMax; }

  /**
   * serialized object
   *
   * @return array
   */
  #[\ReturnTypeWillChange]
  public function jsonSerialize()
  {
    $timestamp_min = $this->getDateMin();
    $timestamp_max = $this->getDateMax();
    return [
      'field' => $this->getName(),
      'timestamp_min' => ($timestamp_min instanceof DateTime) ? $timestamp_min->format(self::DATE_FORMAT) : $timestamp_min,
      'timestamp_max' => ($timestamp_max instanceof DateTime) ? $timestamp_max->format(self::DATE_FORMAT) : $timestamp_max,
    ];
  }

}