<?php
namespace Vanderbilt\REDCap\Classes\Fhir;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

/**
 * define the list of FHIR categories available in REDCap.
 * These categories are used by the Endpoint Factories and
 * match the categories in the metadata file.
 */
abstract class FhirCategory
{
  /**
   * list of available FHIR categories in REDCap
   */
  const ALLERGY_INTOLERANCE = 'Allergy Intolerance';
  const ADVERSE_EVENT = 'Adverse Event';
  const DEMOGRAPHICS = 'Demographics';
  const CONDITION = 'Condition';
  const CONDITION_PROBLEMS = 'Condition - Problems';
  
  const CONDITION_DENTAL_FINDING = 'Condition - Dental Finding'; // new!!!!!
  const CONDITION_GENOMICS = 'Condition - Genomics'; // new!!!!!
  const CONDITION_INFECTION = 'Condition - Infection'; // new!!!!!
  const CONDITION_MEDICAL_HISTORY = 'Condition - Medical History'; // new!!!!!
  const CONDITION_REASON_FOR_VISIT = 'Condition - Reason for Visit'; // new!!!!!
  const COVERAGE = 'Coverage'; // new!!!!!
  const DEVICE_IMPLANTS = 'Device - Implants'; // new!!!!!
  const APPOINTMENT_APPOINTMENTS = 'Appointment - Appointments';
  const APPOINTMENT_SCHEDULED_SURGERIES = 'Appointment - Scheduled Surgeries';

  const LABORATORY = 'Laboratory'; // observation
  const VITAL_SIGNS = 'Vital Signs'; // observation
  const CORE_CHARACTERISTICS = 'Core Characteristics'; // an Epic only observation
  const SMART_DATA = 'SmartData'; // an Epic only observation
  const SOCIAL_HISTORY = 'Social History';
  const ENCOUNTER = 'Encounter';
  const IMMUNIZATION = 'Immunization';
  const MEDICATIONS = 'Medications';
  const RESEARCH_STUDY = 'Research Study';
  const DIAGNOSIS = 'Diagnosis'; // another type of condition
  const PROCEDURE = 'Procedure';
  const PRACTITIONER = 'Practitioner';
  const DOCUMENT_REFERENCE_CLINICAL_NOTES = 'Document Reference - Clinical Notes';
  
  protected static $categoryData = [
    self::ALLERGY_INTOLERANCE => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::ALLERGY_INTOLERANCE,
      FhirVersionManager::FHIR_DSTU2 => EndpointIdentifier::ALLERGY_INTOLERANCE,
    ],
    self::ADVERSE_EVENT => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::ADVERSE_EVENT,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::DEMOGRAPHICS => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::PATIENT,
      FhirVersionManager::FHIR_DSTU2 => EndpointIdentifier::PATIENT,
    ],
    self::CONDITION_PROBLEMS => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::CONDITION_PROBLEMS,
      FhirVersionManager::FHIR_DSTU2 => EndpointIdentifier::CONDITION_PROBLEMS,
    ],
    self::CONDITION => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::CONDITION,
      FhirVersionManager::FHIR_DSTU2 => EndpointIdentifier::CONDITION,
    ],
    self::CORE_CHARACTERISTICS => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::OBSERVATION_CORE_CHARACTERSITICS,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::SOCIAL_HISTORY => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::OBSERVATION_SOCIAL_HISTORY,
      FhirVersionManager::FHIR_DSTU2 => EndpointIdentifier::OBSERVATION_SOCIAL_HISTORY,
    ],
    self::ENCOUNTER => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::ENCOUNTER,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::IMMUNIZATION => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::IMMUNIZATION,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::MEDICATIONS => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::MEDICATION_REQUEST,
      FhirVersionManager::FHIR_DSTU2 => EndpointIdentifier::MEDICATION_ORDER,
    ],
    self::LABORATORY => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::OBSERVATION_LABS,
      FhirVersionManager::FHIR_DSTU2 => EndpointIdentifier::OBSERVATION_LABS,
    ],
    self::VITAL_SIGNS => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::OBSERVATION_VITALS,
      FhirVersionManager::FHIR_DSTU2 => EndpointIdentifier::OBSERVATION_VITALS,
    ],
    self::RESEARCH_STUDY => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::RESEARCHSTUDY,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::DIAGNOSIS => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::CONDITION_ENCOUNTER_DIAGNOSIS,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::PRACTITIONER => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::PRACTITIONER,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::DOCUMENT_REFERENCE_CLINICAL_NOTES => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::DOCUMENT_REFERENCE_CLINICAL_NOTES,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::PROCEDURE => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::PROCEDURE,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::SMART_DATA => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::OBSERVATION_SMART_DATA,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::CONDITION_DENTAL_FINDING => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::CONDITION,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::CONDITION_GENOMICS => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::CONDITION,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::CONDITION_INFECTION => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::CONDITION,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::CONDITION_MEDICAL_HISTORY => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::CONDITION,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::CONDITION_REASON_FOR_VISIT => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::CONDITION,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::COVERAGE => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::COVERAGE,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::DEVICE_IMPLANTS => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::DEVICE,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::APPOINTMENT_APPOINTMENTS => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::APPOINTMENT,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
    self::APPOINTMENT_SCHEDULED_SURGERIES => [
      FhirVersionManager::FHIR_R4 => EndpointIdentifier::APPOINTMENT,
      FhirVersionManager::FHIR_DSTU2 => null,
    ],
  ];

  /**
   *
   * @param string $category
   * @param string $fhirVersion
   * @return string|null
   */
  public static function getFhirResource($category, $fhirVersion = FhirVersionManager::FHIR_R4)
    {
      return self::$categoryData[$category][$fhirVersion] ?? null;
    }


}