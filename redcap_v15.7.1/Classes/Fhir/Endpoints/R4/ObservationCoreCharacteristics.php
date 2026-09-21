<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class ObservationCoreCharacteristics extends AbstractObservation
{

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::OBSERVATION_CORE_CHARACTERSITICS;
  }

  public function getSearchRequest($params=[])
  {
    $params['category'] = self::CATEGORY_CORE_CHARACTERISTICS;
    return parent::getSearchRequest($params);
  }

}