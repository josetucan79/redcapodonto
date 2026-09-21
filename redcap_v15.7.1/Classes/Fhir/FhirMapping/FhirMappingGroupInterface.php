<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMapping;

use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMapping;


interface FhirMappingGroupInterface
{
  public function setCategory(string $category);
  public function getCategory();
  public function getFields();
  public function getDateMin();
  public function getDateMax();
  public function getMappings();
  public function getSubcategories();
  
  /**
   *
   * @param FhirMapping $mapping
   * @param array $mapping_data metadata associated with the mapping based on metadata_source
   * @return void
   */
  function add($mapping, $mapping_data);

}