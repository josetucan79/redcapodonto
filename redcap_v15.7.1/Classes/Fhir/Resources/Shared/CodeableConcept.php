<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Shared;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;

/**
 * A codeable concept is a reference to a terminology.
 * Contains
 * - a "coding" element (array)
 * - a "text" element (string)
 * 
 * @see https://www.hl7.org/fhir/datatypes.html#CodeableConcept
 */
class CodeableConcept extends AbstractResource
{
  /**
   * create a codeable concept
   *
   * @param string $name
   * @param array $payload
   */
  public function __construct($payload)
  {
    parent::__construct($payload);
  }

  public function getCoding()
  {
    return $this->scraper()
      ->coding->getData();
  }

  public function getText()
  {
    return $this->scraper()
      ->text->join();
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts a CodeableConcept resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
    return [
      'text' => fn(self $resource) => $resource->getText(),
      'coding' => fn(self $resource) => $resource->getCoding(),
    ];
  }
  
}