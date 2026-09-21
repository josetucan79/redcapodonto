<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Immunization;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;

class ImmunizationDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir_id'         => function(Immunization $resource) { return $resource->getFhirID(); },
      'text'            => function(Immunization $resource) { return $resource->getText(); },
      'date'            => function(Immunization $resource) { return $resource->getDate(); },
      'normalized_date' => function(Immunization $resource) { return $resource->getNormalizedTimestamp(); },
      'status'          => function(Immunization $resource) { return $resource->getStatus(); },
      'cvx_code'        => function(Immunization $resource) { return $resource->getCvxCode(); },
    ];
  }
}