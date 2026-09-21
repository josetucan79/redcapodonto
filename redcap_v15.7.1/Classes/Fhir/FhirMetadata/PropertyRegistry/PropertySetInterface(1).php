<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry;

interface PropertySetInterface {
    public static function getPropertyExtractors(): array; // [propertyName => callable]
}
