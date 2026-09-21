<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;


use JsonSerializable;
use Vanderbilt\REDCap\Classes\JsonParser\Nodes\Node;
use Vanderbilt\REDCap\Classes\JsonParser\Nodes\NodeFactory;

abstract class AbstractResource implements JsonSerializable, ResourceInterface
{
  
  /**
   *
   * @var Node
   */
  private $scraper;
  
  private $payload;

  public function __construct($payload)
  {
    $this->payload = $payload;
  }

  public function getPayload()
  {
    return $this->payload;
  }

  public function isEmpty()
  {
    return empty($this->payload);
  }

  public function scraper() {
    if(!$this->scraper) $this->scraper = NodeFactory::make($this->payload);
    return $this->scraper;
  }

  /**
   *
   * @param ResourceVisitorInterface $visitor
   * @return mixed
   */
  public function accept($visitor)
  {
    return $visitor->visit($this);
  }

  public function getMetadata()
  {
    $metadata = [
      'payload' => $this->payload,
    ];
    return $metadata;
  }

  #[\ReturnTypeWillChange]
  public function jsonSerialize()
  {
    $data = $this->getData();
    $metadata = $this->getMetadata();
    return compact('data', 'metadata');
  }

  /**
   * create a new observation
   * replacing the existing payload with
   * the provided one
   *
   * @param string $key
   * @param array $payload
   * @return AbstractResource
   */
  protected function replacePayload($parentPayload, $payload) {
    $merged = array_merge($parentPayload, $payload);
    return new static($merged);
  }

  /**
   * Returns all extracted data.
   *
   * @return array The full data array with all extractable properties.
   */
  public function getData(): array
  {
      return $this->resolveData();
  }

  /**
   * Returns only the specified fields from the extracted data.
   *
   * @param array $only Keys of the fields to include.
   * @return array The data array containing only the specified fields.
   */
  public function getDataOnly(array $only): array
  {
      return $this->resolveData($only, []);
  }

  /**
   * Returns all fields from the extracted data except the specified ones.
   *
   * @param array $except Keys of the fields to exclude.
   * @return array The data array excluding the specified fields.
   */
  public function getDataExcept(array $except): array
  {
      return $this->resolveData([], $except);
  }

  /**
   * Internal method to extract data based on inclusion or exclusion lists.
   *
   * @param array $only If not empty, only these keys will be included.
   * @param array $except Keys to exclude from the result.
   * @return array The resolved data array after applying filters.
   */
  private function resolveData(array $only = [], array $except = []): array
  {
      $data = [];
      $extractors = static::getPropertyExtractors();

      foreach ($extractors as $key => $extractor) {
          if (!empty($only) && !in_array($key, $only, true)) {
              continue;
          }
          if (in_array($key, $except, true)) {
              continue;
          }
          $data[$key] = $extractor($this);
      }

      return $data;
  }

}