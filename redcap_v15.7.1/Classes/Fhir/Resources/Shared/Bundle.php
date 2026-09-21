<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Shared;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\FhirRequest;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\ResourceFactoryInterface;

class Bundle extends AbstractResource
{

  private $entries;
  /**
   * Resource Factory
   *
   * @var ResourceFactoryInterface
   */
  private $resourceFactory;

  /**
   *
   * @param Object $payload
   * @param ResourceFactoryInterface $resource_factory
   */
  public function __construct($payload, $resource_factory)
  {
    $this->resourceFactory = $resource_factory;
    parent::__construct($payload);
  }


  /**
   * generator that creates requests for next pages
   *
   * @return FhirRequest
   */
  public function getNextRequest()
  {
    $url = $this->scraper()
      ->link
      ->where('relation','=','next')
      ->url->join('');
    if(empty($url)) return false;
    $method = 'GET';
    $request = new FhirRequest($url, $method);
    return $request;
  }

  public function hasMoreEntries()
  {
    return $this->getNextRequest()!==false;
  }

  /**
   * create a list of resources based on the bundle
   * entry list
   *
   * @return AbstractResource[]
   */
  public function getEntries()
  {
    if(!isset($this->entries)) {
      $this->entries = [];
      $generator = $this->makeEntriesGenerator();
      while($entry=$generator->current()) {
        $generator->next();
        $this->entries[] = $entry;
      }
    }
    return $this->entries;
  }

  /**
   * create a list of resources based on the bundle
   * entry list
   *
   * @return Generator
   */
  public function makeEntriesGenerator()
  {
      $entries_payload = $this->scraper()->entry->resource->getData() ?? [];
      foreach ($entries_payload as $entry_payload) {
        $entry = $this->resourceFactory->make($entry_payload);
        if($entry) yield $entry;
      }
  }

  public function getMetaData()
  {
    $metadata = parent::getMetadata();
    $metadata['next_page'] = $this->getNextRequest();
    return $metadata;
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts a Bundle resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
    return [
      'entry' => fn(self $resource) => $resource->getEntries(),
    ];
  }
  
}