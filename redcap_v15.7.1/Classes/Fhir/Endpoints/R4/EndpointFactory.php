<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;


use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpointFactory;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\Condition;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;

class EndpointFactory extends AbstractEndpointFactory
{

  /**
   * get an endpoint
   *
   * @param string $category
   * @return AbstractEndpoint
   */
  public function makeEndpoint($category)
  {
    $base_url = $this->getBaseUrl();
    $mapping = [
      FhirCategory::ALLERGY_INTOLERANCE => AllergyIntolerance::class,
      FhirCategory::ADVERSE_EVENT => AdverseEvent::class,
      FhirCategory::DEMOGRAPHICS => Patient::class,
      FhirCategory::CONDITION => Condition::class,
      FhirCategory::CONDITION_PROBLEMS => ConditionProblems::class,
      FhirCategory::CORE_CHARACTERISTICS => ObservationCoreCharacteristics::class,
      FhirCategory::SMART_DATA => ObservationSmartData::class,
      FhirCategory::SOCIAL_HISTORY => ObservationSocialHistory::class,
      FhirCategory::ENCOUNTER => Encounter::class,
      FhirCategory::IMMUNIZATION => Immunization::class,
      FhirCategory::MEDICATIONS => MedicationRequest::class,
      FhirCategory::LABORATORY => ObservationLabs::class,
      FhirCategory::VITAL_SIGNS => ObservationVitals::class,
      FhirCategory::RESEARCH_STUDY => ResearchStudy::class,
      FhirCategory::DIAGNOSIS => ConditionEncounterDiagnosis::class,
      FhirCategory::DOCUMENT_REFERENCE_CLINICAL_NOTES => DocumentReferenceClinicalNotes::class,
      FhirCategory::PROCEDURE => Procedure::class,
      FhirCategory::CONDITION_DENTAL_FINDING => ConditionDentalFinding::class,
      FhirCategory::CONDITION_GENOMICS => ConditionGenomics::class,
      FhirCategory::CONDITION_INFECTION => ConditionInfection::class,
      FhirCategory::CONDITION_MEDICAL_HISTORY => ConditionMedicalHistory::class,
      FhirCategory::CONDITION_REASON_FOR_VISIT => ConditionReasonForVisit::class,
      FhirCategory::COVERAGE => Coverage::class,
      FhirCategory::DEVICE_IMPLANTS => Device::class,
      FhirCategory::APPOINTMENT_APPOINTMENTS => AppointmentAppointments::class,
      FhirCategory::APPOINTMENT_SCHEDULED_SURGERIES => AppointmentScheduledSurgeries::class,
    ];
    $endpointClass = $mapping[$category] ?? null;
    if(!$endpointClass) return null;
    return new $endpointClass($base_url);
  }

}