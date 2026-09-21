<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Practitioner;

class PractitionerDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir_id'     =>  function(Practitioner $resource) { return $resource->getFhirID(); },
      'name-given'  =>  function(Practitioner $resource) { return $resource->getNameGiven(); },
      'name-family' =>  function(Practitioner $resource) { return $resource->getNameFamily(); },
    ];
  }
}