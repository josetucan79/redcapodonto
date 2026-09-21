<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator;

use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataAbstractDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataDecoratorInterface;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;

class PropertiesDecorator extends FhirMetadataAbstractDecorator
{
  /**
  *
  * @var FhirVersionManager
  */
  private $fhirVersionManager;
  
  /**
  *
  * @param FhirVersionManager $fhirVersionManager
  * @param FhirMetadataDecoratorInterface $metadataSource
  */
  public function __construct($fhirVersionManager, FhirMetadataDecoratorInterface $metadataSource)
  {
    $this->fhirVersionManager = $fhirVersionManager;
    parent::__construct($metadataSource);
  }
  
  /**
  *
  * @param array $list
  * @return array
  */
  public function getList()
  {
    $fhirCode = $this->fhirVersionManager->getFhirCode();
    $metadata_array = $this->fhirMetadata->getList();
    foreach ($metadata_array as $key => $metadata) {
      $metadata_array[$key]['properties'] = [];
      $category = $metadata['category'] ?? null;
      if(!$category) continue;
      $decorator = DecoratorFactory::getDecoratorForCategory($fhirCode, $category);
      if (!$decorator instanceof PropertyDecorator) continue;
      $dataFunctions = $decorator->dataFunctions();
      $metadata_array[$key]['properties'] = array_keys($dataFunctions);
    }
    return $metadata_array;
  }
}