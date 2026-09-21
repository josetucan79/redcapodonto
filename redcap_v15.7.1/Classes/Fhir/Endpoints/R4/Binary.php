<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

/**
 * Undocumented class
 */
class Binary extends AbstractEndpoint
{

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::BINARY;
  }

  public function getSearchRequest($params=[])
  {
    return parent::getSearchRequest($params);
  }

}