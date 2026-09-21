<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator;

use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\Shared\PatientDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\Shared\ObservationDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\DSTU2\ConditionDecorator as DSTU2_ConditionDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\DSTU2\MedicationOrderDecorator as DSTU2_MedicationOrderDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\DSTU2\AllergyIntoleranceDecorator as DSTU2_AllergyIntoleranceDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4\DeviceDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4\CoverageDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4\EncounterDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4\ProcedureDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4\ConditionDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4\AppointmentDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4\ImmunizationDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4\AdverseEventDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4\MedicationRequestDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4\DocumentReferenceDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4\AllergyIntoleranceDecorator;

class DecoratorFactory
{
    const MAP = [
        FhirCategory::LABORATORY => [
            FhirVersionManager::FHIR_DSTU2 => ObservationDecorator::class,
            FhirVersionManager::FHIR_R4 => ObservationDecorator::class
        ],
        FhirCategory::ALLERGY_INTOLERANCE => [
            FhirVersionManager::FHIR_DSTU2 => DSTU2_AllergyIntoleranceDecorator::class,
            FhirVersionManager::FHIR_R4 => AllergyIntoleranceDecorator::class
        ],
        FhirCategory::VITAL_SIGNS => [
            FhirVersionManager::FHIR_DSTU2 => ObservationDecorator::class,
            FhirVersionManager::FHIR_R4 => ObservationDecorator::class
        ],
        FhirCategory::SOCIAL_HISTORY => [
            FhirVersionManager::FHIR_DSTU2 => ObservationDecorator::class,
            FhirVersionManager::FHIR_R4 => ObservationDecorator::class
        ],
        FhirCategory::MEDICATIONS => [
            FhirVersionManager::FHIR_DSTU2 => DSTU2_MedicationOrderDecorator::class,
            FhirVersionManager::FHIR_R4 => MedicationRequestDecorator::class
        ],
        FhirCategory::CONDITION_PROBLEMS => [
            FhirVersionManager::FHIR_DSTU2 => DSTU2_ConditionDecorator::class,
            FhirVersionManager::FHIR_R4 => ConditionDecorator::class
        ],
        FhirCategory::DIAGNOSIS => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => ConditionDecorator::class
        ],
        FhirCategory::DEMOGRAPHICS => [
            FhirVersionManager::FHIR_DSTU2 => PatientDecorator::class,
            FhirVersionManager::FHIR_R4 => PatientDecorator::class
        ],
        FhirCategory::ENCOUNTER => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => EncounterDecorator::class
        ],
        FhirCategory::IMMUNIZATION => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => ImmunizationDecorator::class
        ],
        FhirCategory::CORE_CHARACTERISTICS => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => ObservationDecorator::class
        ],
        FhirCategory::ADVERSE_EVENT => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => AdverseEventDecorator::class
        ],
        FhirCategory::PROCEDURE => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => ProcedureDecorator::class
        ],
        FhirCategory::SMART_DATA => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => ObservationDecorator::class
        ],
        FhirCategory::CONDITION_DENTAL_FINDING => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => ConditionDecorator::class
        ],
        FhirCategory::CONDITION_GENOMICS => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => ConditionDecorator::class
        ],
        FhirCategory::CONDITION_INFECTION => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => ConditionDecorator::class
        ],
        FhirCategory::CONDITION_MEDICAL_HISTORY => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => ConditionDecorator::class
        ],
        FhirCategory::CONDITION_REASON_FOR_VISIT => [
            FhirVersionManager::FHIR_DSTU2 => ConditionDecorator::class,
            FhirVersionManager::FHIR_R4 => ConditionDecorator::class
        ],
        FhirCategory::COVERAGE => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => CoverageDecorator::class
        ],
        FhirCategory::DEVICE_IMPLANTS => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => DeviceDecorator::class
        ],
        FhirCategory::APPOINTMENT_APPOINTMENTS => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => AppointmentDecorator::class
        ],
        FhirCategory::APPOINTMENT_SCHEDULED_SURGERIES => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => AppointmentDecorator::class
        ],
        FhirCategory::DOCUMENT_REFERENCE_CLINICAL_NOTES => [
            FhirVersionManager::FHIR_DSTU2 => null,
            FhirVersionManager::FHIR_R4 => DocumentReferenceDecorator::class
        ],
    ];

    /**
     * Get the resource class for the specified FHIR category and version.
     *
     * @param string $fhirCode The FHIR category.
     * @param string $fhirCategory The FHIR category.
     * @return PropertyDecorator|null The class name of the resource or null if not found.
     */
    public static function getDecoratorForCategory($fhirCode, $fhirCategory)
    {
        // Determine the correct map to use based on FHIR version
        $resourceClass = static::MAP[$fhirCategory][$fhirCode] ?? null;
        if(!$resourceClass) return null;
        return new $resourceClass;
    }
}
