<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMapping\GroupDecorators;

use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMartRevision;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroupInterface;

/**
 * decorate the dates functions to return null is the category is not allowed
 * to use dateRange
 */
class DataMartGroupDecorator extends BaseGroupDecorator
{
  private $revision;

  /**
   *
   * @param FhirMappingGroupInterface $fhirMappingGroup
   * @param DataMartRevision $revision
   */
  public function __construct(FhirMappingGroupInterface $fhirMappingGroup, DataMartRevision $revision) {
    parent::__construct($fhirMappingGroup);
    $this->revision = $revision;
  }

  public function getDateMin() {
    $category = $this->fhirMappingGroup->getCategory();
    $dateRangecategories = $this->revision->date_range_categories;
    if (!in_array($category, $dateRangecategories)) return;
    return $this->fhirMappingGroup->getDateMin();
  }
  public function getDateMax() {
    $category = $this->fhirMappingGroup->getCategory();
    $dateRangecategories = $this->revision->date_range_categories;
    if (!in_array($category, $dateRangecategories)) return;
    return $this->fhirMappingGroup->getDateMax();
  }
}