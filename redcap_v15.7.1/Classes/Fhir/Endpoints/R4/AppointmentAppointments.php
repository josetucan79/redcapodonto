<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class AppointmentAppointments extends AbstractEndpoint
{

  const CATEGORY = 'appointment';

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