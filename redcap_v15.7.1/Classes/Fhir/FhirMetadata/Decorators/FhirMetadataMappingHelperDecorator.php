<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointsHelper;

/**
 * decorator made specifically for CDP projects
 */
class FhirMetadataMappingHelperDecorator extends FhirMetadataAbstractDecorator
{

  private $project_id;

  /**
   * @param FhirMetadata $fhirMetadata
   * @param int $project_id
   */
  public function __construct($fhirMetadata, $project_id)
  {
    parent::__construct($fhirMetadata);
    $this->project_id = $project_id;
  }

  /**
   * apply decorator and get a new list
   *
   * @param array $list
   * @return array
   */
  public function getList()
  {
    $endpointsHelper = new EndpointsHelper();
    $irbNumber = $endpointsHelper->getProjectIrbNumber();

    $hiddenResources = [];
    if(!$irbNumber) {
      $hiddenResources[] = 'adverse-events-list';
    }

    $metadata_array = $this->fhirMetadata->getList();
    // these will be hidden because user can individually select all available options
    foreach ($hiddenResources as $key) {
      $this->hideKey($key, $metadata_array);
    }

    return $metadata_array;
  }
}