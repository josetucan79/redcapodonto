<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirCustomMetadataService;

class FhirMetadataCustomDecorator extends FhirMetadataAbstractDecorator
{

  /**
   *
   * @param array $list
   * @return array
   */
  public function getList()
  {
    $customMetadataService = new FhirCustomMetadataService();
    $rows = $customMetadataService->getData();
    $metadata_array = $this->fhirMetadata->getList();
    foreach ($rows as $row) {
      $fieldName = $row['field'];
      // if(array_key_exists($fieldName, $metadata_array)) continue; // do not overwrite existing keys
      $metadata_array[$fieldName] = $row;
    }


    return $metadata_array;
  }
}