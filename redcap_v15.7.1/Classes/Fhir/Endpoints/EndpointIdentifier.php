<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;

/**
 * Lis of FHIR endpoint names.
 * This names are used along with the FHIR base URL
 * to fetch data from an EHR system.
 */
abstract class EndpointIdentifier
{
  const ADVERSE_EVENT = 'AdverseEvent';
  const ALLERGY_INTOLERANCE = 'AllergyIntolerance';
  const APPOINTMENT = 'Appointment';
  const BINARY = 'Binary'; // any binary
  const BINARY_CCDA_DOCUMENTS = 'Binary'; // (CCDA Documents)
  const BINARY_CLINICAL_NOTES = 'Binary'; // (Clinical Notes)
  const BINARY_PRACTITIONER_PHOTO = 'Binary'; // (Practitioner Photo)
  const CARE_PLAN = 'CarePlan';
  const CARE_PLAN_ENCOUNTER_LEVEL_CARE_PLAN = 'CarePlan'; // (Encounter Level Care Plan)
  const CARE_PLAN_LONGITUDINAL_CARE_PLAN = 'CarePlan'; // (Longitudinal Care Plan)
  const CARE_TEAM = 'CareTeam';
  const CONDITION = 'Condition';
  const CONDITION_ENCOUNTER_DIAGNOSIS = 'Condition'; // (Encounter Diagnosis)
  const CONDITION_GENOMICS = 'Condition'; // (Genomics)
  const CONDITION_HEALTH_CONCERN = 'Condition'; // (Health Concern)
  const CONDITION_PROBLEMS = 'Condition'; // (Problems)
  const CONSENT = 'Consent';
  const COVERAGE = 'Coverage';
  const DEVICE = 'Device';
  const DIAGNOSTIC_REPORT = 'DiagnosticReport';
  const DOCUMENT_REFERENCE = 'DocumentReference';
  const DOCUMENT_REFERENCE_CLINICAL_NOTES = 'DocumentReference'; // (Clinical Notes)
  const ENCOUNTER = 'Encounter';
  const ENDPOINT = 'Endpoint';
  const EXPLANATION_OF_BENEFIT = 'ExplanationOfBenefit';
  const FAMILY_MEMBER_HISTORY = 'FamilyMemberHistory';
  const GOAL = 'Goal';
  const IMMUNIZATION = 'Immunization';
  const LIST = 'List';
  const LOCATION = 'Location';
  const MEDICATION = 'Medication';
  const MEDICATION_REQUEST_UNSIGNED_MEDICATION_ORDER = 'MedicationRequest'; // (Unsigned Medication Order)
  const MEDICATION_REQUEST = 'MedicationRequest';
  const MEDICATION_ORDER = 'MedicationOrder';
  const MEDICATIONSTATEMENT = 'MedicationStatement';
  const OBSERVATION_CORE_CHARACTERSITICS = 'Observation'; // (Core Charactersitics)
  const OBSERVATION = 'Observation'; // (generic observation)
  const OBSERVATION_LABS = 'Observation'; // (Labs)
  const OBSERVATION_LDA_W = 'Observation'; // (LDA-W)
  const OBSERVATION_OBSTETRIC_DETAILS = 'Observation'; // (Obstetric Details)
  const OBSERVATION_SOCIAL_HISTORY = 'Observation'; // (Social History)
  const OBSERVATION_VITALS = 'Observation'; // (Vitals)
  const OBSERVATION_SMART_DATA = 'Observation'; // (SmartData)
  const ORGANIZATION = 'Organization';
  const PATIENT = 'Patient';
  const PRACTITIONER = 'Practitioner';
  const PRACTITIONERROLE = 'PractitionerRole';
  const PROCEDURE = 'Procedure';
  const RELATEDPERSON = 'RelatedPerson';
  const RESEARCHSTUDY = 'ResearchStudy';
  const SCHEDULE = 'Schedule';
  const SERVICE_REQUEST_UNSIGNED_PROCEDURE_ORDER = 'ServiceRequest'; // (Unsigned Procedure Order)
  const SERVICE_REQUEST = 'ServiceRequest';
  const PROCEDURE_REQUEST = 'ProcedureRequest';
  const SLOT = 'Slot';

  /* const ENDPOINT_TO_REDCAP_MAPPING = [
    self::ADVERSE_EVENT => FhirCategory::ADVERSE_EVENT , // AdverseEvent
    self::ALLERGY_INTOLERANCE => FhirCategory::ALLERGY_INTOLERANCE , // AllergyIntolerance
    self::APPOINTMENT => null , // Appointment
    self::BINARY_CCDA_DOCUMENTS => null , // Binary (CCDA Documents)
    self::BINARY_CLINICAL_NOTES => null , // Binary (Clinical Notes)
    self::BINARY_PRACTITIONER_PHOTO => null , // Binary (Practitioner Photo)
    self::CARE_PLAN => null , // CarePlan
    self::CARE_PLAN_ENCOUNTER_LEVEL_CARE_PLAN => null , // CarePlan (Encounter Level Care Plan)
    self::CARE_PLAN_LONGITUDINAL_CARE_PLAN => null , // CarePlan (Longitudinal Care Plan)
    self::CARE_TEAM => null , // CareTeam
    self::CONDITION => FhirCategory::CONDITION , // Condition
    self::CONDITION_ENCOUNTER_DIAGNOSIS => null , // Condition (Encounter Diagnosis)
    self::CONDITION_GENOMICS => null , // Condition (Genomics)
    self::CONDITION_HEALTH_CONCERN => null , // Condition (Health Concern)
    self::CONDITION_PROBLEMS => FhirCategory::CONDITION_PROBLEMS , // Condition (Problems)
    self::CONSENT => null , // Consent
    self::COVERAGE => null , // Coverage
    self::DEVICE => null , // Device
    self::DIAGNOSTIC_REPORT => null , // DiagnosticReport
    self::DOCUMENT_REFERENCE => null , // DocumentReference
    self::DOCUMENT_REFERENCE_CLINICAL_NOTES => null , // DocumentReference (Clinical Notes)
    self::ENCOUNTER => FhirCategory::ENCOUNTER , // Encounter
    self::ENDPOINT => null , // Endpoint
    self::EXPLANATION_OF_BENEFIT => null , // ExplanationOfBenefit
    self::FAMILY_MEMBER_HISTORY => null , // FamilyMemberHistory
    self::GOAL => null , // Goal
    self::IMMUNIZATION => FhirCategory::IMMUNIZATION , // Immunization
    self::LIST => null , // List
    self::LOCATION => null , // Location
    self::MEDICATION => FhirCategory::MEDICATIONS , // Medication
    self::MEDICATION_REQUEST_UNSIGNED_MEDICATION_ORDER => FhirCategory::MEDICATIONS , // MedicationRequest (Unsigned Medication Order)
    self::MEDICATION_REQUEST => FhirCategory::MEDICATIONS , // MedicationRequest
    self::MEDICATION_ORDER => FhirCategory::MEDICATIONS , // MedicationOrder
    self::MEDICATIONSTATEMENT => null , // MedicationStatement
    self::OBSERVATION_CORE_CHARACTERSITICS => FhirCategory::CORE_CHARACTERISTICS , // Observation (Core Charactersitics)
    self::OBSERVATION_LABS => FhirCategory::LABORATORY , // Observation (Labs)
    self::OBSERVATION_LDA_W => null , // Observation (LDA-W)
    self::OBSERVATION_OBSTETRIC_DETAILS => null , // Observation (Obstetric Details)
    self::OBSERVATION_SOCIAL_HISTORY => FhirCategory::SOCIAL_HISTORY , // Observation (Social History)
    self::OBSERVATION_VITALS => FhirCategory::VITAL_SIGNS , // Observation (Vitals)
    self::ORGANIZATION => null , // Organization
    self::PATIENT => FhirCategory::DEMOGRAPHICS , // Patient
    self::PRACTITIONER => FhirCategory::PRACTITIONER , // Practitioner
    self::PRACTITIONERROLE => null , // PractitionerRole
    self::PROCEDURE => FhirCategory::PROCEDURE , // Procedure
    self::RELATEDPERSON => null , // RelatedPerson
    self::RESEARCHSTUDY => FhirCategory::RESEARCH_STUDY , // ResearchStudy
    self::SCHEDULE => null , // Schedule
    self::SERVICE_REQUEST_UNSIGNED_PROCEDURE_ORDER => null , // ServiceRequest (Unsigned Procedure Order)
    self::SERVICE_REQUEST => null , // ServiceRequest
    self::PROCEDURE_REQUEST => null , // ProcedureRequest
    self::SLOT => null , // Slot
  ]; */

  /* public static function getMappedREDCapCategory($identifier) {
    $category = self::ENDPOINT_TO_REDCAP_MAPPING[$identifier] ?? null;
    return $category;
  } */

}