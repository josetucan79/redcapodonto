<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry\PropertySetInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Immunization extends AbstractResource
{
  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function getStatus()
  {
    return $this->scraper()->status->join('');
  }

  public function getText()
  {
    return $this->scraper()->vaccineCode->text->join('');
  }

  public function getDate()
  {
    return $this->scraper()->occurrenceDateTime->join('');
  }

  public function getCvxCode()
  {
    return $this->scraper()
      ->vaccineCode->coding
      ->where('system', 'like', CodingSystem::CVX)
      ->code->join('');
  }


  public function getNormalizedTimestamp()
  {
    $timestamp = $this->getDate();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts an Immunization resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
    return [
      'fhir_id'              => fn(self $resource) => $resource->getFhirID(),
      'text'                 => fn(self $resource) => $resource->getText(),
      'date'                 => fn(self $resource) => $resource->getDate(),
      'normalized_date'      => fn(self $resource) => $resource->getNormalizedTimestamp(),
      'status'               => fn(self $resource) => $resource->getStatus(),
      'cvx_code'             => fn(self $resource) => $resource->getCvxCode(),
    ];
  }
  
}