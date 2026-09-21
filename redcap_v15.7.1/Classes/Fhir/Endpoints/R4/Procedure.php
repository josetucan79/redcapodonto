<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class Procedure extends AbstractEndpoint
{
  const CATEGORY_SURGICAL_HISTORY = '387713003'; // SNOMED code
  const CATEGORY_ORDERS = '103693007'; // SNOMED code
  const CATEGORY_NURSING_INTERVENTION = '9632001'; // SNOMED code
  const CATEGORY_RESTRICTING_INTERVENTION = '225317005'; // SNOMED code

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::PROCEDURE;
  }

  public function categories() {
    return [
      '387713003' => 'surgical-history',
      '103693007' => 'orders',
      '9632001' => 'nursing-intervention',
      '225317005' => 'restricting-intervention',
    ];
  }

  /**
   * convert REDCap procedure mapping to a 
   * FHIR compatible param
   * @param array $fields
   * @return string|null
   */
  public function getCategory($fields=[])
  {
    $categories = $this->categories();
    $reg_exp = '/^procedure-(?<category>.*)$/i';
    foreach ($fields as $field) {
      $matched = preg_match($reg_exp, $field, $matches);
      if($matched!==1) continue;
      $category = $matches['category'] ?? null;
      if(array_key_exists($category, $categories)) return $categories[$category];
    }
    return;

  }

}