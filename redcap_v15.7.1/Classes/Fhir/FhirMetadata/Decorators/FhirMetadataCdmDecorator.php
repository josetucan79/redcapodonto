<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators;

/**
 * decorator made specifically for CDP projects
 */
class FhirMetadataCdmDecorator extends FhirMetadataAbstractDecorator
{

  /**
   * apply decorator and get a new list
   *
   * @param array $list
   * @return array
   */
  public function getList()
  {
    $metadata_array = $this->fhirMetadata->getList();

    // these will be hidden because user can individually select all available options
    $hiddenResources = [
      // 'encounter-diagnosis-list', // do not have a ststus
      'medications-list',
      'problem-list',
      'problem-dental-finding-list',
      // 'problem-genomics-list', // do not have a ststus
      'problem-infection-list',
      // 'problem-medical-history-list', // do not have a ststus
      // 'problem-reason-for-visit-list', // do not have a ststus
    ];
    foreach ($hiddenResources as $key) {
      $this->hideKey($key, $metadata_array);
    }

    return $metadata_array;
  }
}