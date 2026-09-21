<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\Traits\CanRemoveExtraSlashesFromUrl;

class ConformanceStatement
{
  use CanRemoveExtraSlashesFromUrl;

  private $base_url;

  /**
   *
   * @param string $base_url
   */
  public function __construct($base_url)
  {
    $this->base_url = $base_url;
  }

  /**
   * get request to get the conformance statement
   *
   * @return FhirRequest
   */
  public function getMetadata()
  {
    $method = FhirRequest::METHOD_GET;
    $http_options = array(
      'headers' => array(
        'Accept' => 'application/json'
      ),
    );
    $URL = $this->removeExtraSlashesFromUrl(sprintf("%s/metadata", $this->base_url));
    return new FhirRequest($URL, $method, $http_options);
  }
}