<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class ObservationVitals extends AbstractObservation
{

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::OBSERVATION_VITALS;
  }

  public function getSearchRequest($params=[])
  {
    $params['category'] = self::CATEGORY_VITAL_SIGNS;
    return parent::getSearchRequest($params);
  }

}