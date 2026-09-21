<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Encounter;

class EncounterDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir_id'       => function(Encounter $resource) { return $resource->getFhirID(); },
      'type'          => function(Encounter $resource) { return $resource->getNormalizedType(); },
      'reason'        => function(Encounter $resource) { return $resource->getNormalizedReason(); },
      'class'         => function(Encounter $resource) { return $resource->getClass(); },
      'status'        => function(Encounter $resource) { return $resource->getStatus(); },
      'location'      => function(Encounter $resource) { return $resource->getLocation(); },
      'period-start'  => function(Encounter $resource) { return $resource->getPeriodStart(); },
      'period-end'    => function(Encounter $resource) { return $resource->getPeriodEnd(); },
    ];
  }
}