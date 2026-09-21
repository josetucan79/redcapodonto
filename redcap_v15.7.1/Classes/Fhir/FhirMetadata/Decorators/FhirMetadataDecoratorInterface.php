<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators;

interface FhirMetadataDecoratorInterface
{
  /**
   * apply decorator and get a new list
   *
   * @param array $list
   * @return array
   */
  public function getList();
}