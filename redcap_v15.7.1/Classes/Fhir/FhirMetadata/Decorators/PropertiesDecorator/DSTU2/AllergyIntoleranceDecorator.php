<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\DSTU2;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\AllergyIntolerance;

class AllergyIntoleranceDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir_id'               => function(AllergyIntolerance $resource) { return $resource->getFhirID(); },
      'recorded_date'         => function(AllergyIntolerance $resource) { return $resource->recordedDate(); },
      'normalized_timestamp'  => function(AllergyIntolerance $resource) { return $resource->getNormalizedTimestamp(); },
      'clinical_status'       => function(AllergyIntolerance $resource) { return $resource->clinicalStatus(); },
      'text'                  => function(AllergyIntolerance $resource) { return $resource->getText(); },
      'ndf_rt_display'        => function(AllergyIntolerance $resource) { return $resource->ndfRtDisplay(); },
      'ndf_rt_code'           => function(AllergyIntolerance $resource) { return $resource->ndfRtCode(); },
      'fda_unii_display'      => function(AllergyIntolerance $resource) { return $resource->fdaUniiDisplay(); },
      'fda_unii_code'         => function(AllergyIntolerance $resource) { return $resource->fdaUniiCode(); },
      'snomed_display'        => function(AllergyIntolerance $resource) { return $resource->snomedDisplay(); },
      'snomed_code'           => function(AllergyIntolerance $resource) { return $resource->snomedCode(); },
      'rxnorm_display'        => function(AllergyIntolerance $resource) { return $resource->rxnormDisplay(); },
      'rxnorm_code'           => function(AllergyIntolerance $resource) { return $resource->rxnormCode(); },
    ];
  }
  
}