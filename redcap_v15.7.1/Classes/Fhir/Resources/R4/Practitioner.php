<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;


class Practitioner extends AbstractResource
{

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }
  
  public function getNameGiven()
  {
    return $this->scraper()
    ->name->where('use', '=', 'usual')
    ->given->join('');
  }
  
  public function getNameFamily()
  {
    return $this->scraper()
      ->name->where('use', '=', 'usual')
      ->family->join('');
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts a Practitioner resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
   return [
    'fhir_id'     => fn(self $resource) => $resource->getFhirID(),
    'name-given'  => fn(self $resource) => $resource->getNameGiven(),
    'name-family' => fn(self $resource) => $resource->getNameFamily(),
   ];
  }
  
}