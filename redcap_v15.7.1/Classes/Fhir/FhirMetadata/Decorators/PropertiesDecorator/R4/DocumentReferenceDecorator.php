<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\DocumentReference;

class DocumentReferenceDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'type'                  => function(DocumentReference $resource) { return $resource->getTypeText(); },
      'date'                  => function(DocumentReference $resource) { return $resource->getDate(); },
      'practice_setting'      => function(DocumentReference $resource) { return $resource->getPracticeSettingText(); },
      'normalized_timestamp'  => function(DocumentReference $resource) { return $resource->getNormalizedTimestamp(); },
      'local_timestamp'       => function(DocumentReference $resource) { return $resource->getNormalizedLocalTimestamp(); },
      'author_type'           => function(DocumentReference $resource) { return $resource->getAuthorType(); },
      'author_display'        => function(DocumentReference $resource) { return $resource->getAuthorDisplay(); },
      'html'                  => function(DocumentReference $resource) { return $resource->getHTML(); },
    ];
  }
}