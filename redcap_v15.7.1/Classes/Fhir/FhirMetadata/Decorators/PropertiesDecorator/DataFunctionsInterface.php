<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator;

interface DataFunctionsInterface
{
    /**
     * Each decorator will implement its own data function mappings
     *
     * @return array
     */
    public function dataFunctions(): array;
}
