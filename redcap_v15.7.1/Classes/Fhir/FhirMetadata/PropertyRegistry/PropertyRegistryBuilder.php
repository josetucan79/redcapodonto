<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry;

use ReflectionClass;
use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Patient;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Encounter;
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

class PropertyRegistryBuilder
{
    private array $registeredVersions = [];

    public function __construct(
        private PropertyRegistry $registry
    ) {}

    /**
     * Configure the registry for a specific FHIR version
     */
    public function forFhirVersion(FhirVersionManager $fhirVersionManager): self
    {
        $fhirCode = $fhirVersionManager->getFhirCode();
        
        // Prevent duplicate registration of the same version
        if (in_array($fhirCode, $this->registeredVersions)) {
            return $this;
        }

        match ($fhirCode) {
            FhirVersionManager::FHIR_DSTU2 => $this->addDSTU2Classes(),
            FhirVersionManager::FHIR_R4 => $this->addR4Classes(),
            default => throw new \InvalidArgumentException("Unsupported FHIR version: {$fhirCode}")
        };

        $this->registeredVersions[] = $fhirCode;
        return $this;
    }

    /**
     * Add custom resource classes beyond the standard ones
     */
    public function withCustomClasses(array $classNames): self
    {
        foreach ($classNames as $className) {
            $this->addClass($className);
        }
        return $this;
    }

    /**
     * Add a single resource class
     */
    public function withClass(string $className): self
    {
        $this->addClass($className);
        return $this;
    }

    /**
     * Add manual property extractors (useful for testing or edge cases)
     */
    public function withManualExtractors(string $resourceName, array $extractors): self
    {
        $this->registry->register($resourceName, $extractors);
        return $this;
    }

    /**
     * Build and return the configured registry
     */
    public function build(): PropertyRegistry
    {
        return $this->registry;
    }

    /**
     * Create a new builder instance (fluent static constructor)
     */
    public static function create(): self
    {
        return new self(new PropertyRegistry);
    }

    /**
     * Register all DSTU2 resource classes
     */
    private function addDSTU2Classes(): void
    {
        $classes = [
            AllergyIntolerance_DSTU2::class,
            Condition_DSTU2::class,
            MedicationOrder::class,
            Observation::class,
            Patient::class,
        ];

        foreach ($classes as $className) {
            $this->addClass($className);
        }
    }

    /**
     * Register all R4 resource classes
     */
    private function addR4Classes(): void
    {
        $classes = [
            AdverseEvent::class,
            AllergyIntolerance_R4::class,
            Appointment::class,
            Condition_R4::class,
            Device::class,
            DocumentReference::class,
            Coverage::class,
            Encounter::class,
            Immunization::class,
            MedicationRequest::class,
            Observation::class,
            Patient::class,
            Procedure::class,
        ];

        foreach ($classes as $className) {
            $this->addClass($className);
        }
    }

    /**
     * Register a single class using reflection
     */
    private function addClass(string $className): void
    {
        try {
            $reflection = new ReflectionClass($className);
            
            if (!$reflection->hasMethod('getPropertyExtractors')) {
                return;
            }

            $method = $reflection->getMethod('getPropertyExtractors');
            if (!$method->isStatic() || !$method->isPublic()) {
                return;
            }

            $basename = $reflection->getShortName();
            $extractors = $className::getPropertyExtractors();
            $this->registry->register($basename, $extractors);

        } catch (\ReflectionException $e) {
            // Log or handle the error as needed
            // For now, silently skip invalid classes
        }
    }
}
