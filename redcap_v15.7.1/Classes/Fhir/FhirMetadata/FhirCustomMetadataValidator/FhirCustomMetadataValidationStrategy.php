<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirCustomMetadataValidator;



interface FhirCustomMetadataValidationStrategy
{
  /**
   * validation interface
   *
   * @param array $entries
   * @return bool
   */
  public function validate($entries);
}