<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

class AllergyIntolerance extends AbstractEndpoint
{
  /**
   * @see https://www.hl7.org/fhir/codesystem-allergyintolerance-clinical.html
   */
  const CLINICAL_STATUS_ACTIVE = 'active';
  const CLINICAL_STATUS_INACTIVE = 'inactive';
  const CLINICAL_STATUS_RESOLVED = 'resolved';

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::ALLERGY_INTOLERANCE;
  }


}