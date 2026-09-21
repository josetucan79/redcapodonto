<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry\PropertySetInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class AdverseEvent extends AbstractResource
{
  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  /**
   * get the local or GMT timestamp
   * 
   * @param boolean $localTimestamp
   * @return string
   */
  public function getTimestamp($localTimestamp=false)
  {
    $date = $this->getDate() ?? $this->getDetected() ?? $this->getRecordedDate();
    $callable = $this->getTimestampCallable($date, self::TIMESTAMP_FORMAT);
    return $callable($localTimestamp);
  }

  public function getActuality()
  {
    return $this->scraper()->actuality->join('');
  }

  public function getEventDisplay()
  {
    return $this->scraper()->event->coding[0]->display->join('');
  }

  public function getEventText()
  {
    return $this->scraper()->event->text->join('');
  }

  function getEvent()
  {
    return $this->getEventDisplay() ?? $this->getEventText();
  }
  
  public function getSeriousnessDisplay()
  {
    return $this->scraper()->seriousness->coding[0]->display->join('');
  }

  public function getSeriousnessText()
  {
    return $this->scraper()->seriousness->text->join('');
  }

  function getSeriousness()
  {
    return $this->getSeriousnessDisplay() ?? $this->getSeriousnessText();
  }

  public function getSeverityDisplay()
  {
    return $this->scraper()->severity->coding[0]->display->join('');
  }

  public function getSeverityText()
  {
    return $this->scraper()->severity->text->join('');
  }
  
  public function getOutcomeDisplay()
  {
    return $this->scraper()->outcome->coding[0]->display->join('');
  }

  public function getOutcomeText()
  {
    return $this->scraper()->outcome->text->join('');
  }

  function getOutcome()
  {
    return $this->getOutcomeDisplay() ?? $this->getOutcomeText();
  }

  public function getDate()
  {
    return $this->scraper()->date->join('');
  }
  
  public function getDetected()
  {
    return $this->scraper()->detected->join('');
  }

  public function getRecordedDate()
  {
    return $this->scraper()->recordedDate->join('');
  }

  public function getStudies()
  {
    return $this->scraper()->study->display->join(', ');
  }

  public function getNormalizedTimestamp()
  {
    $timestamp = $this->getDate();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getCausality($entityIndex=0, $causalityIndex=0)
  {
    return $this->scraper()
            ->suspectEntity[$entityIndex]
            ->causality[$causalityIndex]
            ->assessment
            ->text->join('');
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts an AdverseEvent resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
    return [
      'fhir_id'               => fn(self $resource) => $resource->getFhirID(),
      'actuality'             => fn(self $resource) => $resource->getActuality(),
      'event'                 => fn(self $resource) => $resource->getEvent(),
      'causality'             => fn(self $resource) => $resource->getCausality(),
      'seriousness'           => fn(self $resource) => $resource->getSeriousness(),
      'severity'              => fn(self $resource) => $resource->getSeverityDisplay() ?? $resource->getSeverityText(),
      'outcome'               => fn(self $resource) => $resource->getOutcome(),
      'studies'               => fn(self $resource) => $resource->getStudies(),
      'timestamp'             => fn(self $resource) => $resource->getDate() ?? $resource->getDetected() ?? $resource->getRecordedDate(),
      'normalized_timestamp'  => fn(self $resource) => $resource->getNormalizedTimestamp(),
    ];
  }
  
}