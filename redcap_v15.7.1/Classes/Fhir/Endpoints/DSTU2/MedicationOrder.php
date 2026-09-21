<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class MedicationOrder extends AbstractEndpoint
{
  const STATUS_ACTIVE = 'active';
  const STATUS_COMPLETED = 'completed';
  const STATUS_ON_HOLD = 'on-hold';
  const STATUS_STOPPED = 'stopped';

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::MEDICATION_ORDER;
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