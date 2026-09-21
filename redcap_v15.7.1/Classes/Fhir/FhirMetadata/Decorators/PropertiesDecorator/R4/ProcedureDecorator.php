<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Procedure;

class ProcedureDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir_id'             => function(Procedure $resource) { return $resource->getFhirID(); },
      'status'              => function(Procedure $resource) { return $resource->getStatus(); },
      'category'            => function(Procedure $resource) { return $resource->getNormalizedCategory(); },
      'category-display'    => function(Procedure $resource) { return $resource->getCategoryDisplay(); },
      'category-text'       => function(Procedure $resource) { return $resource->getCategory(); },
      'reason'              => function(Procedure $resource) { return $resource->getNormalizedReason(); },
      'reason-display'      => function(Procedure $resource) { return $resource->getReasonDisplay(); },
      'reason-text'         => function(Procedure $resource) { return $resource->getReason(); },
      'outcome'             => function(Procedure $resource) { return $resource->getOutcome(); },
      'complication'        => function(Procedure $resource) { return $resource->getComplication(); },
      'encounter_reference' => function(Procedure $resource) { return $resource->getEncounterReference(); },
      'performed-date-time' => function(Procedure $resource) { return $resource->getPerformedDateTime(); },
      'code'                => function(Procedure $resource) { return $resource->getCodeText(); },
      'cpt-code'            => function(Procedure $resource) { return $resource->getCodeCpt(); },
      'cpt-display'         => function(Procedure $resource) { return $resource->getDisplayCpt(); },
      'note'                => function(Procedure $resource) { return $resource->getNote(); },
    ];
  }
}