<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirCustomMetadataValidator;

use Exception;

class FhirCustomMetadataNonEmptyValidation implements FhirCustomMetadataValidationStrategy {
  public function validate($entries) {
    if(!is_array($entries) || count($entries)===0) throw new Exception("No data was detected");
    return true;
  }
}