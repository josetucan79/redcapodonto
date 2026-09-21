<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpointFactory;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;

class EndpointFactory extends AbstractEndpointFactory
{
  /**
   * get an endpoint
   *
   * @param string $category
   * @return AbstractEndpoint
   */
  public function makeEndpoint($category)
  {
    $base_url = $this->getBaseUrl();
    switch ($category) {
      case FhirCategory::ALLERGY_INTOLERANCE:
        $endpoint = new AllergyIntolerance($base_url);
        break;
      case FhirCategory::DEMOGRAPHICS:
        $endpoint = new Patient($base_url);
        break;
      case FhirCategory::CONDITION_PROBLEMS:
        $endpoint = new Condition($base_url);
        break;
      case FhirCategory::MEDICATIONS:
        $endpoint = new MedicationOrder($base_url);
        break;
      case FhirCategory::LABORATORY:
        $endpoint = new ObservationLabs($base_url);
        break;
      case FhirCategory::VITAL_SIGNS:
        $endpoint = new ObservationVitals($base_url);
        break;
      case FhirCategory::SOCIAL_HISTORY:
        $endpoint = new ObservationSocialHistory($base_url);
        break;
      default:
        $endpoint = null;
        break;
    }
    return $endpoint;
  }
  
}