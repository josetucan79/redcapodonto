<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Condition;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Encounter;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Bundle;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Patient;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\AdverseEvent;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Immunization;
use Vanderbilt\REDCap\Classes\Fhir\Resources\ResourceIdentifier;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Observation;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\MedicationRequest;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\AllergyIntolerance;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\OperationOutcome;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResourceFactory;

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
      case ResourceIdentifier::ADVERSE_EVENT:
        $resource = new AdverseEvent($payload);
        break;
      case ResourceIdentifier::CONDITION:
        $resource = new Condition($payload);
        break;
      case ResourceIdentifier::ENCOUNTER:
        $resource = new Encounter($payload);
        break;
      case ResourceIdentifier::IMMUNIZATION:
        $resource = new Immunization($payload);
        break;
      case ResourceIdentifier::PATIENT:
        $resource = new Patient($payload);
        break;
      case ResourceIdentifier::MEDICATION_REQUEST:
        $resource = new MedicationRequest($payload);
        break;
      case ResourceIdentifier::OBSERVATION:
        $resource = new Observation($payload);
        break;
      case ResourceIdentifier::RESEARCH_STUDY:
        $resource = new ResearchStudy($payload);
        break;
      case ResourceIdentifier::OPERATION_OUTCOME:
        $resource = new OperationOutcome($payload);
        break;
      case ResourceIdentifier::DOCUMENT_REFERENCE:
          $resource = new DocumentReference($payload, $this->fhirClient);
          break;
      case ResourceIdentifier::PROCEDURE:
          $resource = new Procedure($payload);
          break;
      case ResourceIdentifier::COVERAGE:
          $resource = new Coverage($payload);
          break;
      case ResourceIdentifier::DEVICE:
          $resource = new Device($payload);
          break;
      case ResourceIdentifier::APPOINTMENT:
          $resource = new Appointment($payload);
          break;
      case ResourceIdentifier::BINARY:
          $resource = new Binary($payload);
          break;
      default:
        $resource = null;
        break;
    }
    return $resource;
  }
}