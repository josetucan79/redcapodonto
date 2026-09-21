<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;

class ResearchStudy extends AbstractResource
{
  
  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function getTitle()
  {
    return $this->scraper()->title->join('');
  }

  public function getStatus()
  {
    return $this->scraper()->status->join('');
  }

  public function getPrincipalInvestigator()
  {
    return $this->scraper()->principalInvestigator->display->join('');
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts a ResearchStudy resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
    return [
      'id'                      => fn(self $resource) => $resource->getFhirID(),
      'title'                   => fn(self $resource) => $resource->getTitle(),
      'status'                  => fn(self $resource) => $resource->getStatus(),
      'principal-investigator'  => fn(self $resource) => $resource->getPrincipalInvestigator(),
    ];
  }
  
}