<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\Traits\CanGetRedcapConfiguration;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\Traits\HasDateRange;

class Immunization extends AbstractEndpoint
{

  use HasDateRange;
  use CanGetRedcapConfiguration;

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::IMMUNIZATION;
  }


}