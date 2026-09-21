<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\Traits\CanGetRedcapConfiguration;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\Traits\HasDateRange;

/**
 * the generic Observation class is used for similar endpoints with differents
 * purposes: labs, vitals, core characteristics
 */
abstract class AbstractObservation extends AbstractEndpoint
{
  use HasDateRange;
  use CanGetRedcapConfiguration;

  const CATEGORY_LABORATORY = 'laboratory';
  const CATEGORY_VITAL_SIGNS = 'vital-signs';
  const CATEGORY_SOCIAL_HISTORY = 'social-history';
  const CATEGORY_CORE_CHARACTERISTICS = 'core-characteristics';
  const CATEGORY_SMART_DATA = 'smartdata';


}