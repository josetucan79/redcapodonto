<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\AdverseEvent;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;

class AdverseEventDecorator extends PropertyDecorator
{

  public function dataFunctions(): array
  {
    return [
      'fhir_id'               => function(AdverseEvent $resource) { return $resource->getFhirID(); },
      'actuality'             => function(AdverseEvent $resource) { return $resource->getActuality(); },
      'event'                 => function(AdverseEvent $resource) { return $resource->getEvent(); },
      'causality'             => function(AdverseEvent $resource) { return $resource->getCausality(); },
      'seriousness'           => function(AdverseEvent $resource) { return $resource->getSeriousness(); },
      'severity'              => function(AdverseEvent $resource) { return $resource->getSeverityDisplay() ?? $resource->getSeverityText();},
      'outcome'               => function(AdverseEvent $resource) { return $resource->getOutcome(); },
      'studies'               => function(AdverseEvent $resource) { return $resource->getStudies(); },
      'timestamp'             => function(AdverseEvent $resource) { return $resource->getDate() ?? $resource->getDetected() ?? $resource->getRecordedDate(); },
      'normalized_timestamp'  => function(AdverseEvent $resource) { return $resource->getNormalizedTimestamp(); }, // convert to proper date
    ];
  }
}