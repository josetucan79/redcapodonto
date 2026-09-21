<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class ObservationLabs extends AbstractObservation
{

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::OBSERVATION_LABS;
  }

  public function getSearchRequest($params=[])
  {
    $params['category'] = self::CATEGORY_LABORATORY;
    return parent::getSearchRequest($params);
  }

}