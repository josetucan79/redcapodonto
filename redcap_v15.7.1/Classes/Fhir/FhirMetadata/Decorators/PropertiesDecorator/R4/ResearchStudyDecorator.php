<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\ResearchStudy;

class ResearchStudyDecorator extends PropertyDecorator
{
  public  function dataFunctions(): array
  {
    return [
      'id'                      => function(ResearchStudy $resource) { return $resource->getFhirID(); },
      'title'                   => function(ResearchStudy $resource) { return $resource->getTitle(); },
      'status'                  => function(ResearchStudy $resource) { return $resource->getStatus(); },
      'principal-investigator'  => function(ResearchStudy $resource) { return $resource->getPrincipalInvestigator(); },
    ];
  }
}