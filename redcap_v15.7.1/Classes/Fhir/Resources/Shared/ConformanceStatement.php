<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Shared;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;

/**
 * Factory for factories.
 * Get the factory that will build endpoints for a specific FHIR version.
 */
class ConformanceStatement extends AbstractResource
{

  public function getFhirVersion()
  {
    return $this->scraper()->fhirVersion->join('');
  }

  public function getSoftwareName()
  {
    return $this->scraper()->software->name->join('');
  }
  
  public function getResources() {
    
    return $this->scraper()->rest->resource->getData();
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts a Contact resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
    return [
      'fhirVersion'  => fn(self $resource) => $resource->getFhirVersion(),
      'resources'    => fn(self $resource) => $resource->getResources(),
    ];
  }
}