<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2;

use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Bundle;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Patient;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\Condition;
use Vanderbilt\REDCap\Classes\Fhir\Resources\ResourceIdentifier;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Observation;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\MedicationOrder;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResourceFactory;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\OperationOutcome;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\AllergyIntolerance;

/**
 * List of FHIR resource types
 * 
 * The resource type is inferred by the resourceType
 * property in the payload
 * 
 */
class ResourceFactory extends AbstractResourceFactory
{

  /**
   *
   * @param array $payload
   * @return AbstractResource
   */
  public function make($payload)
  {
    $resourceType = $payload['resourceType'] ?? '';
    switch ($resourceType) {
      case ResourceIdentifier::BUNDLE:
        $resource = new Bundle($payload, $this);
        break;
      case ResourceIdentifier::ALLERGY_INTOLERANCE:
        $resource = new AllergyIntolerance($payload);
        break;
      case ResourceIdentifier::CONDITION:
        $resource = new Condition($payload);
        break;
      case ResourceIdentifier::PATIENT:
        $resource = new Patient($payload);
        break;
      case ResourceIdentifier::MEDICATION_ORDER:
        $resource = new MedicationOrder($payload);
        break;
      case ResourceIdentifier::OBSERVATION:
        $resource = new Observation($payload);
        break;
      case ResourceIdentifier::OPERATION_OUTCOME:
        $resource = new OperationOutcome($payload);
      default:
        $resource = null;
        break;
    }
    return $resource;
  }
}