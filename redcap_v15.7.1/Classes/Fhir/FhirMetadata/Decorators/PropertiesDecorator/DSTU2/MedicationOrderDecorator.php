<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\DSTU2;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\MedicationOrder;

class MedicationOrderDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir_id'                   => function(MedicationOrder $resource) { return $resource->getFhirID(); },
      'status'                    => function(MedicationOrder $resource) { return $resource->getStatus(); },
      'display'                   => function(MedicationOrder $resource) { return $resource->getMedicationReference(); },
      'timestamp'                 => function(MedicationOrder $resource) { return $resource->getDateWritten(); },
      'normalized_timestamp'      => function(MedicationOrder $resource) { return $resource->getNormalizedTimestamp(); },
      'dosage'                    => function(MedicationOrder $resource) { return $resource->getDosageText(); },
      'dosage_instruction_route'  => function(MedicationOrder $resource) { return $resource->getDosageInstructionRoute(); },
      'dosage_instruction_timing' => function(MedicationOrder $resource) { return $resource->getDosageInstructionTiming(); },
      'rxnorm_code'               => function(MedicationOrder $resource) { return $resource->rxnormDisplay(); },
      'rxnorm_display'            => function(MedicationOrder $resource) { return $resource->rxnormCode(); },
      'medicationCodeableConcept' => function(MedicationOrder $resource) { return $resource->getNormalizedMedicationCodeableConcept(); },
      'text'                      => function(MedicationOrder $resource) { return $resource->getText(); },
    ];
  }
}