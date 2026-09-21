<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;


/**
 * factory that creates FHIR requests based on:
 * - category
 * - patient identifier
 * - REDCap mapping
 */
interface EndpointFactoryInterface
{

  /**
   * required interface to generate a request
   *
   * @param string $category
   * @param string $patient_id
   * @param string $interaction one of the AbstractEndpoint::INTERACTION_*
   * @param array $options
   * @return FhirRequest
   */
  public function make($category, $patient_id, $interaction, $options=[]);
  
  /**
   * get an endpoint
   *
   * @param string $category
   * @return AbstractEndpoint
   */
  public function makeEndpoint($category);

  /**
   * required interface to generate a request
   *
   * @param AbstractEndpoint $endpoint
   * @param string $patient_id
   * @param string $interaction one of the AbstractEndpoint::INTERACTION_*
   * @return FhirRequest
   */
  public function makeRequest($endpoint, $patient_id, $interaction);

}