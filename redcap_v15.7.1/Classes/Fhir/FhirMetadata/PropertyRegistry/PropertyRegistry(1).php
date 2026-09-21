<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry;

class PropertyRegistry
{
    /**
     * @var array<string, array<string, callable>>
     */
    private array $registry = [];

    public function register(string $resourceName, array $propertyExtractors): void
    {
        if (!isset($this->registry[$resourceName])) {
            $this->registry[$resourceName] = [];
        }

        $this->registry[$resourceName] = array_merge(
            $this->registry[$resourceName],
            $propertyExtractors
        );
    }

    public function getProperties(string $resourceName): array
    {
        return array_keys($this->registry[$resourceName] ?? []);
    }

    public function getExtractor(string $resourceName, string $property): ?callable
    {
        return $this->registry[$resourceName][$property] ?? null;
    }

    public function getAll(): array
    {
        return $this->registry;
    }

    public function hasProperty(string $resourceName, string $property): bool
    {
        return isset($this->registry[$resourceName][$property]);
    }
}
