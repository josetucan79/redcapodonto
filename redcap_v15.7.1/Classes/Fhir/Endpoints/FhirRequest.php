<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

use System;
use HttpClient;
use JsonSerializable;
use GuzzleHttp\Psr7\Query;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\Utility\ArrayUtils;

/**
 * request action returned by one of the
 * FHIR endpoints interactions
 */
class FhirRequest implements JsonSerializable
{

  const METHOD_GET      = 'GET';
  const METHOD_POST     = 'POST';
  const METHOD_PUT      = 'PUT';
  const METHOD_DELETE   = 'DELETE';

  /**
   * HTTP method
   *
   * @var string
   */
  private $method;

  /**
   * request URL
   *
   * @var string
   */
  private $url;

  /**
   * associative array of options provided by the user
   * @see https://docs.guzzlephp.org/en/stable/request-options.html
   * @var array ['headers', 'query', ...]
   */
  private $userOptions = [];

  /**
   * list of processed options with default values
   * and transformations to the parameters
   *
   * @var array
   */
  private $options = [];

  private $default_options = [
    'headers' => [
      'Accept' => 'application/json',
      'Content-Type' => 'application/x-www-form-urlencoded',
    ],
    'query' => [],
  ];
  
  /**
   * most recent set of options sent via the send method
   *
   * @param array $lastSentOptions
   */
  private $lastSentOptions;


  /**
   *
   * @param string $method
   * @param string $url
   */
  public function __construct($url, $method=self::METHOD_GET, $options=[])
  {
    $this->method = $method;
    $this->url = $url;
    $this->userOptions = $options;
  }

  /**
   * get data from a FHIR endpoint
   *
   * @param string $url
   * @param string $access_token
   * @param bool $debug show debug info for the request
   * @return string HTTP response body
   */
  public function send($access_token=null, $debug=false)
  {
    $http_options = $this->getOptions();
    if($access_token) $http_options['headers']['Authorization'] = "Bearer {$access_token}";
    if(isset($debug)) $http_options['debug'] = $debug;
    
    $this->setLastSentOptions($http_options);
    $response = HttpClient::request($this->method, $this->url, $http_options);
    return $response->getBody();
  }

  /**
   * set the last set of options
   *
   * @param array $options
   * @return void
   */
  private function setLastSentOptions($options) { $this->lastSentOptions = $options; }

  /**
   * get the last set of options sent via send
   *
   * @return void
   */
  public function getLastSentOptions() { return $this->lastSentOptions; }

  /**
   * set the HTTP options to send with the request
   *
   * @param array $options
   * @return void
   */
  public function setOptions($options)
  {
    $this->userOptions = $options;
  }

  /**
   * get the options as they were provided by the user
   *
   * @return array
   */
  public function getUserOptions() { return $this->userOptions; }


  public function getDefaultOptions() { return $this->default_options; }

  public function setDefaultOptions($options) { $this->default_options = $options; }

  /**
   * add default parameters and
   * apply transformations to the options
   * provided by the user
   *
   * @param array $options
   * @return array
   */
  private function getOptions() {
    $userOptions = $this->getUserOptions();
    $defaultOptions = $this->getDefaultOptions();
    $http_options = array_merge_recursive($userOptions, $defaultOptions);
    
    /**
     * merge the query params in the URL (if any)
     * with those in the query array
     */
    $mergeUrlQueryParams = function($query_params=[]) {
      $url_query = parse_url($this->url, PHP_URL_QUERY);
      if(empty($url_query)) return $query_params;
      $parsed_query_string = Query::parse($url_query);
      foreach ($parsed_query_string as $key => $value) {
        if(!array_key_exists($key, $query_params)) {
          $query_params[$key] = $value;
        }
      }
      return $query_params;
    };
    /**
     * transform the query array to string
     */
    $queryParamsToString = function($query_params=[]) {
      if(!is_array($query_params)) return;
      $query_string = Query::build($query_params, PHP_QUERY_RFC1738);
      $query_params = $query_string;
      return $query_params;
    };
    $http_options['query'] = $http_options['query'] ?? [];
    $http_options['query'] = $mergeUrlQueryParams($http_options['query']);
    $http_options['query'] = $queryParamsToString($http_options['query']);
    return $http_options;
  }

  /**
   * getter for the method
   *
   * @return string
   */
  public function getMethod() { return $this->method; }

  /**
   * getter for the URL
   *
   * @return string
   */
  public function getURL() { return $this->url; }

  public function extractIdentifier()
  {
    $extractQueryParams = function() {
      $query = parse_url($this->getURL(), PHP_URL_QUERY) ?? '';
      parse_str($query, $params);
      return $params;
    };
    switch ($this->method) {
      // extract from get requests
      case self::METHOD_GET:
        
        $userOptions = $this->getUserOptions();
        $query = $userOptions['query'] ?? [];
        $queryParams = $extractQueryParams();
        $query = array_merge($query, $queryParams);

        // deal with .read request (no userOptions provided)
        if(empty($query)) {
          preg_match('/.*\/(?<identifier>.+)\/?$/', $this->getURL(), $matches);
          return $matches['identifier'] ?? null;
        }

        // deal with .search
        $identifierKey = ArrayUtils::find(array_keys($query), function($key) {
          return in_array($key, [
            'subject', 'patient', '_id', 'identifier', // standard identifiers
            '_getpages', // get pages when pagination is detected; the FHIR ID matched the bundle
          ]); // look if there is one of the possible ID related keys
        });
        if($identifierKey) {
          $identifierValue = $query[$identifierKey] ?? null;
          // make sure to return a single item
          if($identifierValue && is_array($identifierValue)) $identifierValue = $identifierValue[0] ?? null;
          return $identifierValue;
        }
        break;
      case self::METHOD_POST:
      case self::METHOD_PUT:
      case self::METHOD_DELETE:
      default:
        return $identifier = null;
        break;
    }
    return $identifier = null;
  }

  /**
   * extract the part of the URL that identifies a FHIR resource
   *
   * @param string $fhirBaseUrl
   * @return string
   */
  public function getResourceName(string $fhirBaseUrl) {
    $getPath = function($URL) {
      $parts = parse_url($URL);
      return $parts['path'] ?? '';
    };

    $url = $this->getURL();
    $fullUrlPath = $getPath($url);
    $baseUrlPath = $getPath($fhirBaseUrl);
    $regExp = addcslashes("$baseUrlPath/?(?<resource>[^/]*)", '/');
    preg_match("/$regExp/", $fullUrlPath, $matches);
    $found = $matches['resource'] ?? false;
    return $found;
  }

  function getResourceTypeFromFhirUrl($url) {
    // Parse the URL into its components
    $urlComponents = parse_url($url);

    // Split the path into its components and remove the leading '/'
    $pathComponents = explode('/', trim($urlComponents['path'], '/'));

    // Check if the URL ends with an ID (read URL)
    if (preg_match('/\/[a-zA-Z0-9]+\b$/', $urlComponents['path'], $matches)) {
        array_pop($pathComponents);
    }

    // Check if the URL has no ID and ends with a resource type (write URL)
    if (empty($urlComponents['query']) && empty($matches) && !empty($pathComponents) && !is_numeric($pathComponents[count($pathComponents)-1])) {
        return $pathComponents[count($pathComponents)-1];
    }

    // Return the second component in the path (assuming it's the resource type)
    return isset($pathComponents[1]) ? $pathComponents[1] : '';
  }



  public function jsonSerialize(): array {
    return [
      'url' => $this->getURL(),
      'method' => $this->getMethod(),
      'userOptions' => $this->getUserOptions(),
      'lastSentOptions' => $this->getLastSentOptions(),
    ];
  }

}