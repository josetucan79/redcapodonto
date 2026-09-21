<?php
namespace Vanderbilt\REDCap\Classes\Fhir;

use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\ConformanceStatement;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirMetadataSource;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointFactoryInterface;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\EndpointFactory as EndpointFactory_R4;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\ResourceFactory as ResourceFactory_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\EndpointFactory as EndpointFactory_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\ResourceFactory as ResourceFactory_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\ConformanceStatement as ResourcesConformanceStatement;
use Vanderbilt\REDCap\Classes\Fhir\Resources\ResourceFactoryInterface;

/**
 * Factory for factories.
 * Get the factory that will build endpoints for a specific FHIR version.
 */
class FhirVersionManager
{

  private static $instances = [];
  /**
   * major.minor.patch
   *
   * @var string
   */
  private $fhir_version;
  
  /**
   * DSTU2, R4, etc...
   *
   * @var string
   */
  private $fhir_code;

  /**
   * FHIR base URL
   *
   * @var string
   */
  private $base_url;
  
  /**
   * the conformance statement resource
   *
   * @var ResourcesConformanceStatement
   */
  private $conformance_statement;

  /**
   * FHIR versions
   */
  const FHIR_DSTU1 = 'DSTU1';
  const FHIR_DSTU2 = 'DSTU2';
  const FHIR_STU3 = 'STU3';
  const FHIR_R4 = 'R4';
  const SUPPORTED_VERSIONS = [self::FHIR_DSTU2,self::FHIR_R4];

  /**
   * manage tools and resources for a FHIR version
   *
   * @param FhirSystem $fhirSystem
   */
  private function __construct($fhirSystem)
  {
    $this->base_url = $fhirSystem->getFhirBaseUrl();
    $fhir_version = $this->getFhirVersion();
    $fhir_code = $this->getFhirCodeFromVersion($fhir_version);
    if(!in_array($fhir_code, self::SUPPORTED_VERSIONS)) throw new \Exception("The FHIR version '$fhir_code' is not supported.", 400);
    $this->fhir_version = $fhir_version;
    $this->fhir_code = $fhir_code;
    $ehrID = $fhirSystem->getEhrId();
    static::$instances[$ehrID] = $this;
  }

  /**
   *
   * @param FhirSystem $fhirSystem
   * @return FhirVersionManager
   */
  public static function getInstance($fhirSystem)
  {
    $ehrID = $fhirSystem->getEhrId();
    $instance = static::$instances[$ehrID] ?? null;
    if(!$instance) {
      static::$instances[$ehrID] = $instance = new self($fhirSystem);
    }
    return $instance;
  }

  public function getBaseUrl()
  {
    return $this->base_url;
  }

  public function getVersion()
  {
    return $this->fhir_version;
  }

  public function getFhirCode()
  {
    return $this->fhir_code;
  }

  /**
   * get the code name of the FHIR version based on its numeric code
   * @var string $fhir_version numeric version of the FHIR service (major.minor.patch)
   * @return string
   */
  public function getFhirCodeFromVersion($fhir_version)
  {
    if(!is_string($fhir_version)) return;
    preg_match("#(?<major>\d+)\.(?<minor>\d+)(?:\.(?<patch>\d+))?#", $fhir_version, $matches);
    $major = $matches['major'] ?? '';
    $version_mapping = [
      '0' => self::FHIR_DSTU1,
      '1' => self::FHIR_DSTU2,
      '3' => self::FHIR_STU3,
      '4' => self::FHIR_R4,
    ];
    return $version_mapping[$major] ?? '';
  }

  /**
   * get a factory based on the FHIR code version
   *
   * @param string $base_url base URL for the FHIR requests
   * @return EndpointFactoryInterface
   */
  public function getEndpointFactory()
  {
    switch ($this->fhir_code) {
      case self::FHIR_DSTU2:
        $factory = new EndpointFactory_DSTU2($this->base_url);
        break;
      case self::FHIR_R4:
        $factory = new EndpointFactory_R4($this->base_url);
        break;
      default:
        $factory = null;
        break;
    }
    return $factory;
  }

  /**
   *
   * @param FhirClient $fhirClient
   * @return ResourceFactoryInterface|null
   */
  public function getResourceFactory($fhirClient)
  {
    switch ($this->fhir_code) {
      case self::FHIR_DSTU2:
        $factory = new ResourceFactory_DSTU2($fhirClient);
        break;
      case self::FHIR_R4:
        $factory = new ResourceFactory_R4($fhirClient);
        break;
      default:
        $factory = null;
        break;
    }
    return $factory;
  }

  /**
   * TODO
   *
   * @return void
   */
  public function getParserFactory()
  {
    switch ($this->fhir_version) {
      case self::FHIR_DSTU2:
        $factory = null;
        break;
      case self::FHIR_R4:
        $factory = null;
        break;
      default:
        $factory = null;
        break;
    }
    return $factory;
  }

  private function fetchConformanceStatement()
  {
    try {
      $conformanceStatementEndpoint = new ConformanceStatement($this->base_url);
      $request = $conformanceStatementEndpoint->getMetadata();
			$response = $request->send();
			$payload = json_decode($response, true);
			return $payload;
		} catch (\Exception $e) {
			$message = $e->getMessage();
			throw new \Exception("Could not make a successful call to the Conformance Statement at {$request->getURL()}.\n\r{$message}", 400);
		}
  }

  /**
   * the cache table will depend on the FHIR base URL
   * to prevent issues where the wrong one is used
   *
   * @param string $key
   * @return string
   */
  private function makeCacheKey($key) {
    return $key.$this->base_url;
  }

  private function getFromCache($key) {
      $fileCache = new FileCache(__CLASS__);
      $fullKey = $this->makeCacheKey($key);
      return $fileCache->get($fullKey);
  }

  private function setCache($key, $value, $ttl=3600) {
    $fileCache = new FileCache(__CLASS__);
    $fullKey = $this->makeCacheKey($key);
    $fileCache->set($fullKey, $value, $ttl); // keep in cache for 1 hour
  }

  /**
   *
   * @return ResourcesConformanceStatement
   */
  public function getConformanceStatement()
  {
    if(!$this->conformance_statement) {
      $key = 'conformance_statement_payload';
      $cache = $this->getFromCache($key) ?? ''; //make sure we have a string
      $payload = json_decode($cache, true);
      if(!$payload) {
        $payload = $this->fetchConformanceStatement();
        if(!$payload) return;
        $encoded = json_encode($payload, JSON_PRETTY_PRINT);
        $this->setCache($key, $encoded, $ttl=60*60*1);
      }
      $this->conformance_statement = new ResourcesConformanceStatement($payload);
    }
    return $this->conformance_statement;
  }

  /**
   * get the current FHIR version from memory
   * or extract it from the conformance statement
   *
   * @return string
   */
  public function getFhirVersion()
  {
    $key = 'fhir_version';
    $fhir_version = $this->getFromCache($key);
    if(!$fhir_version) {
      $conformance_statement = $this->getConformanceStatement();
      if(!($conformance_statement instanceof ResourcesConformanceStatement)) return;
      $fhir_version = $conformance_statement->getFhirVersion();
      $this->setCache($key, $fhir_version, $ttl=60*60*1); // keep in cache for 1 hour
    }
    return $fhir_version;
  }

  /**
   * create a FhirMetadataGenerator 
   *
   * @return FhirMetadataSource
   */
  public function getFhirMetadataSource()
  {
    return new FhirMetadataSource($this->fhir_code);
  }
}