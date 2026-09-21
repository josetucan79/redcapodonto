<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

use System;

abstract class AbstractEndpointFactory implements EndpointFactoryInterface
{

  private $base_url;

  public function __construct($base_url)
  {
    $this->base_url = $base_url;
  }

  public function getBaseUrl()
  {
    return $this->base_url;
  }
  
  /**
   * Undocumented function
   *
   * @param AbstractEndpoint $endpoint
   * @param string $identifier
   * @param string $interaction
   * @param array $options
   * @return FhirRequest
   */
  public function makeRequest($endpoint, $identifier, $interaction, $options=[])
  {
    if(!$endpoint instanceof AbstractEndpoint) return;
    switch ($interaction) {
      case AbstractEndpoint::INTERACTION_READ:
        $request = $endpoint->getReadRequest($identifier);
        break;
        case AbstractEndpoint::INTERACTION_SEARCH:
        $request = $endpoint->getSearchRequest($options);
        break;
      default:
        $request = null;
        break;
    }
    return $request;
  }

  /**
   * create an endpoint based on a category and
   * return a FHIR request based on the interaction
   *
   * @param string $category
   * @param string $patient_id
   * @param string $interaction
   * @param array $options
   * @return FhirRequest
   */
  public function make($category, $patient_id, $interaction, $options=[])
  {
    $endpoint = $this->makeEndpoint($category);
    $request = $this->makeRequest($endpoint, $patient_id, $interaction, $options);
    return $request;
  }

}