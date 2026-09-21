<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class MedicationRequest extends AbstractEndpoint
{

  const STATUS_ACTIVE = 'active';
  const STATUS_COMPLETED = 'completed';
  const STATUS_ON_HOLD = 'on-hold';
  const STATUS_STOPPED = 'stopped';

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::MEDICATION_REQUEST;
  }

  /**
   * convert REDCap medication mapping to a 
   * FHIR compatible param
   * @param array $fields
   * @return array
   */
  public function getStatusParam($fields=[])
  {
    $status_list = [
      self::STATUS_ACTIVE,
      self::STATUS_COMPLETED,
      self::STATUS_ON_HOLD,
      self::STATUS_STOPPED,
    ];
    return $this->generateQueryParamValue($fields, $status_list);
  }

}