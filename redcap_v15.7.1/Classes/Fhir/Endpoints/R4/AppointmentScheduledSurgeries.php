<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class AppointmentScheduledSurgeries extends AbstractEndpoint
{

  const CATEGORY = 'surgery';

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::APPOINTMENT;
  }

  public function getSearchRequest($params=[])
  {
    $params['service-category'] = self::CATEGORY;
    return parent::getSearchRequest($params);
  }

}