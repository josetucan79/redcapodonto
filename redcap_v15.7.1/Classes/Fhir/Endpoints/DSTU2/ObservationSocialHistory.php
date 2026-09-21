<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class ObservationSocialHistory extends AbstractObservation
{

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::OBSERVATION_SOCIAL_HISTORY;
  }

  public function getSearchRequest($params=[])
  {
    $params['category'] = self::CATEGORY_SOCIAL_HISTORY;
    return parent::getSearchRequest($params);
  }

}