<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\MedicationRequest;

class MedicationRequestDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir_id'                   => function(MedicationRequest $resource) { return $resource->getFhirID(); },
      'status'                    => function(MedicationRequest $resource) { return $resource->getStatus(); },
      'display'                   => function(MedicationRequest $resource) { return $resource->getMedicationReference(); },
      'timestamp'                 => function(MedicationRequest $resource) { return $resource->getDateWritten(); },
      'normalized_timestamp'      => function(MedicationRequest $resource) { return $resource->getNormalizedTimestamp(); },
      'dosage'                    => function(MedicationRequest $resource) { return $resource->getDosageText(); },
      'dosage_instruction_route'  => function(MedicationRequest $resource) { return $resource->getDosageInstructionRoute(); },
      'dosage_instruction_timing' => function(MedicationRequest $resource) { return $resource->getDosageInstructionTiming(); },
      'rxnorm_display'            => function(MedicationRequest $resource) { return $resource->rxnormDisplay(); },
      'rxnorm_code'               => function(MedicationRequest $resource) { return $resource->rxnormCode(); },
      'medicationCodeableConcept' => function(MedicationRequest $resource) { return $resource->getNormalizedMedicationCodeableConcept(); },
      'text'                      => function(MedicationRequest $resource) { return $resource->getText(); },
    ];
  }
}