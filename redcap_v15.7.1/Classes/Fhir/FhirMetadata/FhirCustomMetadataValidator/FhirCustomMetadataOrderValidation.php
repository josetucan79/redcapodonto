<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirCustomMetadataValidator;

use Exception;

class FhirCustomMetadataOrderValidation implements FhirCustomMetadataValidationStrategy {
  private $expectedOrder;

  public function __construct($expectedOrder) {
    $this->expectedOrder = $expectedOrder;
  }

  public function validate($header, $rows) {
    $headerOrder = array_flip($header);
    $expectedOrder = array_flip($this->expectedOrder);
    if ($headerOrder !== $expectedOrder) {
      throw new Exception("Invalid order of headers");
    }
    return true;
  }
}