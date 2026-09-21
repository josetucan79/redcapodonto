<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

interface EndpointInterface
{
  /**
   * return the FHIR resource identifier for the endpoint
   *
   * @return string
   */
  public function getResourceIdentifier();


  /**
   *
   * @param EndpointVisitorInterface $visitor
   * @return mixed
   */
  public function accept($visitor);
}