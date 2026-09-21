<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\Shared;

use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Observation;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;

class ObservationDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir-id'               => function(Observation $resource) { return $resource->getId(); },
      'code'                  => function(Observation $resource) { return $resource->getCode()->getData(); },
      'category'              => function(Observation $resource) { return $resource->getCategory(); },
      'timestamp'             => function(Observation $resource) { return $resource->getDate(); },
      'normalized_timestamp'  => function(Observation $resource) { return $resource->getNormalizedTimestamp(); },
      'local_timestamp'       => function(Observation $resource) { return $resource->getNormalizedLocalTimestamp(); },
      'value'                 => function(Observation $resource) { return $resource->getValue(); },
      'valueUnit'             => function(Observation $resource) { return $resource->getValueUnit(); },
      'valueQuantity'         => function(Observation $resource) { return $resource->getValueQuantity(); },
      'valueString'           => function(Observation $resource) { return $resource->getValueString(); },
      'valueCodeableConcept'  => function(Observation $resource) { return $resource->getValueCodeableConcept(); },
      'valueBoolean'          => function(Observation $resource) { return $resource->getValueBoolean(); },
      'valueInteger'          => function(Observation $resource) { return $resource->getValueInteger(); },
      'valueRange'            => function(Observation $resource) { return $resource->getValueRange(); },
      'valueRatio'            => function(Observation $resource) { return $resource->getValueRatio(); },
      'valueSampledData'      => function(Observation $resource) { return $resource->getValueSampledData(); },
      'valueTime'             => function(Observation $resource) { return $resource->getValueTime(); },
      'valueDateTime'         => function(Observation $resource) { return $resource->getValueDateTime(); },
      'valuePeriod'           => function(Observation $resource) { return $resource->getValuePeriod(); },
      'component'             => function(Observation $resource) { return $resource->getComponent(); },
    ];
  }
}
