<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\Resources\ResourceFactoryInterface;

/**
 * List of FHIR resource types
 * 
 * The resource type is inferred by the resourceType
 * property in the payload
 * 
 */
abstract class AbstractResourceFactory implements ResourceFactoryInterface
{

  /**
   *
   * @var FhirClient
   */
  protected $fhirClient;

  /**
   *
   * @param FhirClient $fhirClient
   */
  public function __construct($fhirClient) {
    $this->fhirClient = $fhirClient;
  }

  /**
   *
   * @param array $payload
   * @return AbstractResource
   */
  abstract public function make($payload);
}