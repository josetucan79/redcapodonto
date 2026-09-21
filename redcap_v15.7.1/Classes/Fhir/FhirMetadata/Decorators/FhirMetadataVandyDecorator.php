<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators;

class FhirMetadataVandyDecorator extends FhirMetadataAbstractDecorator
{

  /**
   *
   * @param array $list
   * @return array
   */
  public function getList()
  {

    $metadata_array = $this->fhirMetadata->getList();
    if(isVanderbilt() || $this->isTestServer()) return $metadata_array; // do not filter if Vanderbilt
    // these will be deleted
    $hiddenResources = [];
    foreach ($hiddenResources as $hiddenResource) {
      $this->hideKey($hiddenResource, $metadata_array);
    }
    return $metadata_array;
  }
}