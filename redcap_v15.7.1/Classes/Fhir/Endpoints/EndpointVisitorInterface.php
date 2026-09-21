<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

/**
 * Visit an endpoint to provide
 * custom behaviours (alter options, results, etc...)
 */
interface EndpointVisitorInterface
{

  /**
   * Apply a behavior based on the class
   * implementing the visitor
   *
   * @param AbstractEndpoint $endpoint
   * @return mixed
   */
  public function visit($endpoint);
}