<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

/**
 * Undocumented class
 */
class ConditionEncounterDiagnosis extends AbstractEndpoint
{

  const CATEGORY = 'encounter-diagnosis';

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::CONDITION_ENCOUNTER_DIAGNOSIS;
  }

  public function getSearchRequest($params=[])
  {
    $params['category'] = self::CATEGORY;
    return parent::getSearchRequest($params);
  }

}