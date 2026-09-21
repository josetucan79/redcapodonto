<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Coverage;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;

class CoverageDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir_id'             => function(Coverage $resource) { return $resource->getFhirID(); },
      'plan_name'           => function(Coverage $resource) { return $resource->getPlanName(); },
      'payor_1'             => function(Coverage $resource) { return $resource->getPayor(); },
      'network'             => function(Coverage $resource) { return $resource->getNetwork(); },
      'status'              => function(Coverage $resource) { return $resource->getStatus(); },
      'period_start'        => function(Coverage $resource) { return $resource->getPeridoStart(); },
      'period_end'          => function(Coverage $resource) { return $resource->getPeridoEnd(); },
      'period'              => function(Coverage $resource) { return $resource->getPeriod(); },
      'order'               => function(Coverage $resource) { return $resource->getOrder(); },
      'type_text'           => function(Coverage $resource) { return $resource->getTypeText(); },
      'cost_to_beneficiary' => function(Coverage $resource) { return $resource->getCostToBeneficiary(); },
    ];
  }
}