<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirMetadataSource;

abstract class FhirMetadataAbstractDecorator implements FhirMetadataDecoratorInterface
{

  protected $fhirMetadata;
  
  /**
   * @param FhirMetadataSource $fhirMetadata
   */
  public function __construct($fhirMetadata)
  {

    $this->fhirMetadata = $fhirMetadata;
  }

  protected function disableKey($key, $reason, &$metadata_array) {
    if(!array_key_exists($key, $metadata_array)) return $metadata_array; // return as is since the key is not available
    $metadata_array[$key]['disabled'] = true;
    $metadata_array[$key]['disabled_reason'] = $reason;
  }

  protected function hideKey($key, &$metadata_array) {
    if(!array_key_exists($key, $metadata_array)) return; // not found; go on
    unset($metadata_array[$key]);
  }

  /**
   * check if the server is a testing environment
   *
   * @return boolean
   */
  protected function isTestServer() {
    return (
        (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'redcap.test')
        || (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'localhost')
      );
  }
}