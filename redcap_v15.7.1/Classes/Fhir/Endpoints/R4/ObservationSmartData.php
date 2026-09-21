<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class ObservationSmartData extends AbstractObservation
{

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::OBSERVATION_SMART_DATA;
  }

  public function getSearchRequest($params=[])
  {
    $params['category'] = self::CATEGORY_SMART_DATA;
    return parent::getSearchRequest($params);
  }

}