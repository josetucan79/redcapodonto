<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class ResearchStudy extends AbstractEndpoint
{

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::RESEARCHSTUDY;
  }


}