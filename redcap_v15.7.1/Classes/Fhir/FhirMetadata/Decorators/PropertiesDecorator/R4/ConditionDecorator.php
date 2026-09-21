<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Condition;

class ConditionDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir_id'               => function(Condition $resource) { return $resource->getFhirID(); },
      'clinical_status'       => function(Condition $resource) { return $resource->getClinicalStatus(); },
      'timestamp'             => function(Condition $resource) { return $resource->getRecordedDate(); },
      'normalized_timestamp'  => function(Condition $resource) { return $resource->getNormalizedTimestamp(); },
      'label'                 => function(Condition $resource) { return $resource->getLabel(); },
      'icd-10-code'           => function(Condition $resource) { return $resource->getCodeIcd10(); },
      'icd-10-display'        => function(Condition $resource) { return $resource->getNormalizedDisplayIcd10(); },
      'icd-9-code'            => function(Condition $resource) { return $resource->getCodeIcd9(); },
      'icd-9-display'         => function(Condition $resource) { return $resource->getNormalizedDisplayIcd9(); },
      'snomed-ct-code'        => function(Condition $resource) { return $resource->getCodeSnomedCt(); },
      'snomed-ct-display'     => function(Condition $resource) { return $resource->getNormalizedDisplaySnomedCt(); },
      'verification-status'   => function(Condition $resource) { return $resource->getVerificationStatus(); },
      'recorder'              => function(Condition $resource) { return $resource->getRecorder(); },
      'recorder_type'         => function(Condition $resource) { return $resource->getRecorderType(); },
      'note'                  => function(Condition $resource) { return $resource->getNote(); },
      'encounter_reference'   => function(Condition $resource) { return $resource->getEncounterReference(); },
      'encounter_label'       => function(Condition $resource) { return $resource->getEncounterDisplay(); },
      'body_site_1'           => function(Condition $resource) { return $resource->getBodySite(0); },
      'category_code'         => function(Condition $resource) { return $resource->getCategoryCode(); },
    ];
  }



}