<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Device;

class DeviceDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir_id'         => function(Device $resource) { return $resource->getFhirID(); },
      'device_name'     => function(Device $resource) { return $resource->getDeviceName(); },
      'type'            => function(Device $resource) { return $resource->getType(); },
      'model_number'    => function(Device $resource) { return $resource->getModelNumber(); },
      'expiration_date' => function(Device $resource) { return $resource->getExpirationDate(); },
      'site'            => function(Device $resource) { return $resource->getSite(); },
      'permanence'      => function(Device $resource) { return $resource->getPermanence(); },
      'laterality'      => function(Device $resource) { return $resource->getLaterality(); },
      'radioactive'     => function(Device $resource) { return $resource->getRadioactive(); },
      'note_time_1'     => function(Device $resource) { return $resource->getNoteTime(0); },
      'note_text_1'     => function(Device $resource) { return $resource->getNoteText(0); },
    ];
  }
}