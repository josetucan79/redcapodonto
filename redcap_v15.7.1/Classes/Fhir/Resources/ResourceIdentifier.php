<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

abstract class ResourceIdentifier
{
  const BUNDLE = 'Bundle';
  const PATIENT = 'Patient';
  const CONDITION = 'Condition';
  const MEDICATION_REQUEST = 'MedicationRequest';
  const MEDICATION_ORDER = 'MedicationOrder';
  const ALLERGY_INTOLERANCE = 'AllergyIntolerance';
  const OBSERVATION = 'Observation';
  const ENCOUNTER = 'Encounter';
  const IMMUNIZATION = 'Immunization';
  const ADVERSE_EVENT = 'AdverseEvent';
  const RESEARCH_STUDY = 'ResearchStudy';
  const OPERATION_OUTCOME = 'OperationOutcome';
  const DOCUMENT_REFERENCE = 'DocumentReference';
  const BINARY = 'Binary';
  const PROCEDURE = 'Procedure';
  const COVERAGE = 'Coverage';
  const DEVICE = 'Device';
  const APPOINTMENT = 'Appointment';
}