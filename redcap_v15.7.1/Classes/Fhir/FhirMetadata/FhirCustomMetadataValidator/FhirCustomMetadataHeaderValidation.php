<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirCustomMetadataValidator;

use Exception;

class FhirCustomMetadataHeaderValidation implements FhirCustomMetadataValidationStrategy {
  private $expectedHeader;

  public function __construct($expectedHeader) {
    $this->expectedHeader = $expectedHeader;
  }

  public function validate($entries) {
    foreach ($entries as $entry) {
      $header = array_keys($entry);
      $result = array_diff($header, $this->expectedHeader);
      if (!empty($result)) throw new Exception(sprintf("Invalid header. Please use the expected format %s", implode(', ', $this->expectedHeader)));
    }
    return true;
  }
}