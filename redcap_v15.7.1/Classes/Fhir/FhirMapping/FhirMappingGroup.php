<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMapping;

use DateTime;
use Vanderbilt\REDCap\Classes\Traits\CanMakeDateTime;

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
class FhirMappingGroup implements FhirMappingGroupInterface
{
  use CanMakeDateTime;

  protected $category;
  protected $mappings = [];
  /**
   *
   * @var array
   */
  protected $dateMin = null;
  protected $dateMax = null;
  protected $fields = [];
  protected $subcategories = [];

  public function __construct($category) {
    $this->setCategory($category);
  }

  public function setCategory($category) {
    $this->category = $category;
  }
  /**
   *
   * @param FhirMapping $mapping
   * @param array $mapping_data metadata associated with the mapping based on metadata_source
   * @return void
   */
  function add($mapping, $mapping_data)
  {
    if(in_array($mapping, $this->mappings)) return;
    $this->mappings[] = $mapping;
    $this->parseField($mapping);
    $this->parseDates($mapping);
    $this->parseSubcategories($mapping, $mapping_data);
  }

  public function getCategory() { return $this->category; }
  public function getFields() { return $this->fields; }
  public function getDateMin() { return $this->dateMin; }
  public function getDateMax() { return $this->dateMax; }
  public function getMappings() { return $this->mappings; }
  public function getSubcategories() { return $this->subcategories; }

  /**
   * compare 2 dates and get the best choice based on a strategy
   * @param DateTime $date_a
   * @param DateTime $date_b
   * @param string $strategy min|max
   * @return DateTime|false
   */
  protected function getBestDate($date_a, $date_b, $strategy) {
    if(!$date_a && !$date_b) false;
    if(!$date_a) return $date_b;
    if(!$date_b) return $date_a;
    switch($strategy) {
      case('min'):
        $best = $date_a<$date_b ? $date_a : $date_b;
        break;
      case('max'):
        $best = $date_a>$date_b ? $date_a : $date_b;
        break;
      default:
        $best = false;
        break;
    }
    return $best;
  }


  /**
   *
   * @param FhirMapping $mapping
   * @return void
   */
  protected function parseMapping(FhirMapping $mapping) {

  }

  /**
   * adjust the date range to account for the min and max date across
   * all stored mappings
   *
   * @param FhirMapping $mapping
   * @return void
   */
  protected function parseDates($mapping) {
    /* $temporal = $metadata_array['temporal'] ?? false;
    if(!$temporal) return; // not a temporal type of mapping; skip dates */
    if($date_min = $mapping->getDateMin()) {
      $this->dateMin = $this->getBestDate($this->dateMin, $date_min, 'min');
    }
    if($date_max = $mapping->getDateMax()) {
      $this->dateMax = $this->getBestDate($this->dateMax, $date_max, 'max');
    }
  }

  /**
   *
   * @param string $name
   * @param mixed $value
   * @return void
   */
  protected function addUniqueProperty($name, $value) {
    if(!is_array($this->{$name})) return;
    if(in_array($value, $this->{$name})) return;
    $this->{$name}[] = $value;
  }
  
  /**
   *
   * @param FhirMapping $mapping
   * @return void
   */
  protected function parseField($mapping) {
    $field = $mapping->getName();
    $this->addUniqueProperty('fields', $field);
  }

  /**
   *
   * @param FhirMapping $mapping
   * @param array $mapping_data
   * @return void
   */
  protected function parseSubcategories($mapping, $mapping_data) {
    $subcategory = $mapping_data['subcategory'] ?? false;
    if(!$subcategory) return;
    $this->addUniqueProperty('subcategories', $subcategory);
  }

  /**
  * group the mapping of a project
  * by category.
  * categories are listed in the class FhirCategory
  * 
  * @param FhirMetadataSource $fhirMetadataSource
  * @param FhirMapping[] $fhirMappings
  * @return array
  */
  public static function makeGroups($fhirMetadataSource, $fhirMappings)
  {
    $metadata_array = $fhirMetadataSource->getList();
    /** @var FhirMappingGroup[] $groups */
    $groups = [];
    foreach ($fhirMappings as $fhirMapping) {
      $mappingData = $metadata_array[$fhirMapping->getName()] ?? false;
      if(!$mappingData) continue;
      $disabled = $mappingData['disabled'] ?? false;
      if($disabled) continue; //skip disabled mappings (e.g. adverse events or emails)

      $category = $mappingData['category'] ?? false;
      if(!$category) continue;
      if(!array_key_exists($category, $groups)) {
        $groups[$category] = new self($category);
      }
      $fhirMappingGroup = $groups[$category];
      $fhirMappingGroup->add($fhirMapping, $mappingData);
    }

    return $groups;
  }
}