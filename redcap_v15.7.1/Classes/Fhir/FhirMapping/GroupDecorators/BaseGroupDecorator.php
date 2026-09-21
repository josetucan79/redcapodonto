<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMapping\GroupDecorators;

use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMapping;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroupInterface;

/**
 * define a set of options that will be used
 * to retrieve data from the FHIR system
 * 
 * this is the syntax for a mapping:
 * - mapping => resource?options
 * - resource => field_name.fields
 * - fields => field,field
 * - options => option&option
 * - option => key=value
 */
abstract class BaseGroupDecorator implements FhirMappingGroupInterface
{
  protected $fhirMappingGroup;

  public function __construct(FhirMappingGroupInterface $fhirMappingGroup) {
    $this->fhirMappingGroup = $fhirMappingGroup;
  }

  /**
   *
   * @param FhirMapping $mapping
   * @param array $mapping_data metadata associated with the mapping based on metadata_source
   * @return void
   */
  public function add($mapping, $mapping_data) { $this->fhirMappingGroup->add($mapping, $mapping_data); }
  public function setCategory($category) { $this->fhirMappingGroup->setCategory($category); }
  public function getCategory() { return $this->fhirMappingGroup->getCategory(); }
  public function getFields() { return $this->fhirMappingGroup->getFields(); }
  public function getDateMin() { return $this->fhirMappingGroup->getDateMin(); }
  public function getDateMax() { return $this->fhirMappingGroup->getDateMax(); }
  public function getMappings() { return $this->fhirMappingGroup->getMappings(); }
  public function getSubcategories() { return $this->fhirMappingGroup->getSubcategories(); }
}