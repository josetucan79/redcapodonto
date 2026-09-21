<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry\PropertySetInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodeableConcept;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Encounter extends AbstractResource
{

  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  /**
   * get the local or GMT timestamp
   * of the start encounter
   * 
   * @param boolean $localTimestamp
   * @return string
   */
  public function getTimestampStart($localTimestamp=false)
  {
    $callable = $this->getTimestampCallable($this->getPeriodStart(), self::TIMESTAMP_FORMAT);
    return $callable($localTimestamp);
  }

  /**
   * get the local or GMT timestamp
   * of the end encounter
   * 
   * @param boolean $localTimestamp
   * @return string
   */
  public function getTimestampEnd($localTimestamp=false)
  {
    $callable = $this->getTimestampCallable($this->getPeriodEnd(), self::TIMESTAMP_FORMAT);
    return $callable($localTimestamp);
  }

  
  public function getTypeDisplay()
  {
    return $this->scraper()->type->coding[0]->display->join('');
  }

  public function getTypeText()
  {
    return $this->scraper()->type->text->join('');
  }

  public function getPeriodStart()
  {
    return $this->scraper()->period->start->join('');
  }

  public function getPeriodEnd()
  {
    return $this->scraper()->period->end->join('');
  }

  public function getLocation()
  {
    return $this->scraper()->location->location->display->join('');
  }

  public function getReasonCodeDisplay()
  {
    return $this->scraper()->reasonCode->coding[0]->display->join('');
  }

  public function getReasonCodeText()
  {
    return $this->scraper()->reasonCode->text->join('');
  }

  /**
   * create a CodeableConcept from the code
   * portion of the payload
   *
   * @return CodeableConcept
   */
  public function getReasonCode()
  {
    $payload = $this->scraper()->reasonCode->getData();
    return new CodeableConcept($payload);
  }

  public function getClass()
  {
    return $this->scraper()->class->display->join('');
  }

  public function getStatus()
  {
    return $this->scraper()->status->join('');
  }

  public function getNormalizedType() {
    return $this->getTypeDisplay() ?? $this->getTypeText();
  }

  public function getNormalizedReason() {
    return $this->getReasonCodeDisplay() ?? $this->getReasonCodeText();
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts an Encounter resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
    return [
      'fhir_id'      => fn(self $resource) => $resource->getFhirID(),
      'type'         => fn(self $resource) => $resource->getNormalizedType(),
      'reason'       => fn(self $resource) => $resource->getNormalizedReason(),
      'class'        => fn(self $resource) => $resource->getClass(),
      'status'       => fn(self $resource) => $resource->getStatus(),
      'location'     => fn(self $resource) => $resource->getLocation(),
      'period-start' => fn(self $resource) => $resource->getPeriodStart(),
      'period-end'   => fn(self $resource) => $resource->getPeriodEnd(),
    ];
  }
  
}