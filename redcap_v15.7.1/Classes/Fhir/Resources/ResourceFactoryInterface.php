<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

/**
 * factory that creates FHIR Resources
 */
interface ResourceFactoryInterface
{

  /**
   * required interface to generate a request
   *
   * @param string $payload
   * @return AbstractResource
   */
  public function make($payload);
  
  
}