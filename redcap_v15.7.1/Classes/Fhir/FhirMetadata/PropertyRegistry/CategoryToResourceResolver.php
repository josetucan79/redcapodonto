<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry;

use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Encounter;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Patient;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Observation;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\MedicationRequest;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\MedicationOrder;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\AllergyIntolerance as AllergyIntolerance_R4;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\AllergyIntolerance as AllergyIntolerance_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Condition as Condition_R4;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\Condition as Condition_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\AdverseEvent;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Appointment;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Coverage;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Device;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\DocumentReference;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Immunization;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Procedure;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\OperationOutcome;

class CategoryToResourceResolver
{
    /**
     * Mapping of REDCap FHIR categories to resource class names
     * depending on FHIR version.
     */
    protected const MAP = [
        FhirCategory::LABORATORY => [
            FhirVersionManager::FHIR_DSTU2 => Observation::class,
            FhirVersionManager::FHIR_R4 => Observation::class,
        ],
        FhirCategory::ALLERGY_INTOLERANCE => [
            FhirVersionManager::FHIR_DSTU2 => AllergyIntolerance_DSTU2::class,
            FhirVersionManager::FHIR_R4 => AllergyIntolerance_R4::class,
        ],
        FhirCategory::VITAL_SIGNS => [
            FhirVersionManager::FHIR_DSTU2 => Observation::class,
            FhirVersionManager::FHIR_R4 => Observation::class,
        ],
        FhirCategory::SOCIAL_HISTORY => [
            FhirVersionManager::FHIR_DSTU2 => Observation::class,
            FhirVersionManager::FHIR_R4 => Observation::class,
        ],
        FhirCategory::MEDICATIONS => [
            FhirVersionManager::FHIR_DSTU2 => MedicationOrder::class,
            FhirVersionManager::FHIR_R4 => MedicationRequest::class,
        ],
        FhirCategory::CONDITION_PROBLEMS => [
            FhirVersionManager::FHIR_DSTU2 => Condition_DSTU2::class,
            FhirVersionManager::FHIR_R4 => Condition_R4::class,
        ],
        FhirCategory::DIAGNOSIS => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Condition_R4::class,
        ],
        FhirCategory::DEMOGRAPHICS => [
            FhirVersionManager::FHIR_DSTU2 => Patient::class,
            FhirVersionManager::FHIR_R4 => Patient::class,
        ],
        FhirCategory::ENCOUNTER => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Encounter::class,
        ],
        FhirCategory::IMMUNIZATION => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Immunization::class,
        ],
        FhirCategory::CORE_CHARACTERISTICS => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Observation::class,
        ],
        FhirCategory::ADVERSE_EVENT => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => AdverseEvent::class,
        ],
        FhirCategory::PROCEDURE => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Procedure::class,
        ],
        FhirCategory::SMART_DATA => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Observation::class,
        ],
        FhirCategory::CONDITION_DENTAL_FINDING => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Condition_R4::class,
        ],
        FhirCategory::CONDITION_GENOMICS => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Condition_R4::class,
        ],
        FhirCategory::CONDITION_INFECTION => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Condition_R4::class,
        ],
        FhirCategory::CONDITION_MEDICAL_HISTORY => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Condition_R4::class,
        ],
        FhirCategory::CONDITION_REASON_FOR_VISIT => [
            FhirVersionManager::FHIR_DSTU2 => Condition_DSTU2::class,
            FhirVersionManager::FHIR_R4 => Condition_R4::class,
        ],
        FhirCategory::COVERAGE => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Coverage::class,
        ],
        FhirCategory::DEVICE_IMPLANTS => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Device::class,
        ],
        FhirCategory::APPOINTMENT_APPOINTMENTS => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Appointment::class,
        ],
        FhirCategory::APPOINTMENT_SCHEDULED_SURGERIES => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => Appointment::class,
        ],
        FhirCategory::DOCUMENT_REFERENCE_CLINICAL_NOTES => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => DocumentReference::class,
        ],
    ];

    /**
     * Resolves a REDCap FHIR category and FHIR version
     * to a FHIR resource class name (e.g., 'Patient', 'Condition').
     *
     * @param string $category
     * @param string $fhirVersion
     * @return string|null
     */
    public static function resolve(string $category, string $fhirVersion): ?string
    {
        return self::MAP[$category][$fhirVersion] ?? null;
    }
}
