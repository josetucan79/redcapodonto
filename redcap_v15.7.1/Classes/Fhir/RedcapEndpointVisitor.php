<?php namespace Vanderbilt\REDCap\Classes\Fhir;

use DateTime;
use DateInterval;
use DateTimeZone;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\Encounter;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\Condition;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\AdverseEvent;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\AllergyIntolerance;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroup;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ConditionProblems;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\MedicationRequest;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\MedicationOrder;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointVisitorInterface;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\Patient as Patient_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\Patient as Patient_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ObservationCoreCharacteristics;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ObservationLabs as ObservationLabs_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ObservationVitals as ObservationVitals_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\ObservationLabs as ObservationLabs_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\AllergyIntolerance as AllergyIntolerance_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\ObservationVitals as ObservationVitals_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ObservationSocialHistory as ObservationSocialHistory_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\ObservationSocialHistory as ObservationSocialHistory_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointsHelper;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\DocumentReferenceClinicalNotes;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\Procedure;

/**
 * FHIR endpoint visitor that generates parameters
 * for FHIR endpoints using REDCap mapping, projects settings
 * and system settings.
 */
class RedcapEndpointVisitor implements EndpointVisitorInterface
{

  /**
   *
   * @var FhirClient
   */
  private $fhirClient;

  /**
   * @var string
   */
  private $patient_id;

  /**
   *
   * @var FhirMappingGroup
   */
  private $mappingGroup;

  /**
   * @var array [DateTime, DateTime]
   */
  private $dateRange;

  /**
   *
   * @param FhirClient $fhirClient
   * @param string $patient_id
   * @param FhirMappingGroup $mappingGroup
   */
  function __construct($fhirClient, $patient_id, $mappingGroup)
  {
    
    $this->fhirClient = $fhirClient;
    $this->patient_id = $patient_id;
    $this->mappingGroup = $mappingGroup;
    $dateMin = $mappingGroup->getDateMin();
    $dateMax = $mappingGroup->getDateMax();
    $this->dateRange = $this->makeDateRange($dateMin, $dateMax);
  }

  /**
   * adjust the options for the endpoint
   * @param AbstractEndpoint $endpoint
   * @return array
   */
  function visit($endpoint)
  {
    $options = [];
    $class = get_class($endpoint);
    switch ($class) {
      case Patient_DSTU2::class:
      case Patient_R4::class:
        $options = $this->visitPatient($endpoint, $options);
        break;
      case AdverseEvent::class:
        $options = $this->visitAdverseEvents($endpoint, $options);
        break;
      case AllergyIntolerance::class:
        $options = $this->visitAllergy($endpoint, $options);
        break;
        case AllergyIntolerance_R4::class:
          $options = $this->visitAllergyR4($endpoint, $options);
          break;
      case Condition::class:
        $options = $this->visitCondition($endpoint, $options);
        break;
      case ConditionProblems::class:
        $options = $this->visitConditionProblems($endpoint, $options);
        break;
      case MedicationOrder::class:
      case MedicationRequest::class:
        $options = $this->visitMedication($endpoint, $options);
        break;
      case ObservationSocialHistory_DSTU2::class:
      case ObservationSocialHistory_R4::class:
      case ObservationVitals_DSTU2::class:
      case ObservationVitals_R4::class:
      case ObservationLabs_DSTU2::class:
      case ObservationLabs_R4::class:
      case ObservationCoreCharacteristics::class:
        $options = $this->visitObservation($endpoint, $options);
        break;
      case MedicationRequest::class:
        $options = $this->visitMedication($endpoint, $options);
        break;
      case Encounter::class:
        $options = $this->visitEncounter($endpoint, $options);
        break;
      case DocumentReferenceClinicalNotes::class:
        $options = $this->visitDocumentReferenceClinicalNotes($endpoint, $options);
        break;
      case Procedure::class:
        $options = $this->visitProcedure($endpoint, $options);
        break;
      default:
        $options['patient'] = $this->patient_id;
        break;
    }
    return $options;
  }

  /**
   *
   * @param Patient_DSTU2|Patient_R4 $endpoint
   * @return array
   */
  public function visitPatient($endpoint, $options)
  {
    $options['_id'] = $this->patient_id;
    return $options;
  }

  /**
   * PLEASE NOTE: date will not be applied in Epic systems
   * @param AllergyIntolerance $endpoint
   * @return array
   */
  public function visitAllergy($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['date'] = $this->dateRange;
    return $options;
  }

  /**
   * PLEASE NOTE: date will not be applied in Epic systems
   * @param AllergyIntolerance_R4 $endpoint
   * @return array
   */
  public function visitAllergyR4($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['clinical-status'] = $endpoint::CLINICAL_STATUS_ACTIVE;
    $options['date'] = $this->dateRange;
    return $options;
  }

  /**
   *
   * @param AdverseEvent $endpoint
   * @return array
   */
  public function visitAdverseEvents($endpoint, $options)
  {
    $endpointsHelper = new EndpointsHelper();

    $irbNumber = $endpointsHelper->getProjectIrbNumber();
    if(empty($irbNumber)) return;

    $studyFhirId = $endpointsHelper->getFhirStudyID($this->fhirClient, $irbNumber);

    $options['study'] = $studyFhirId;
    $options['subject'] = $this->patient_id;
    return $options;
  }

  /**
   *
   * @param Condition $endpoint
   * @return array
   */
  public function visitCondition($endpoint, $options)
  {
    $fields = $this->mappingGroup->getFields();
    $options['patient'] = $this->patient_id;
    $options['onset'] = $this->dateRange;
    $options['clinicalStatus'] = $endpoint->getStatusParam($fields);
    return $options;
  }

  /**
   *
   * @param ConditionProblems $endpoint
   * @return array
   */
  public function visitConditionProblems($endpoint, $options)
  {
    $fields = $this->mappingGroup->getFields();
    $options['patient'] = $this->patient_id;
    $options['clinical-status'] = $endpoint->getStatusParam($fields);
    $options['onset-date'] = $this->dateRange;
    return $options;
  }

  /**
   *
   * @param Procedure $endpoint
   * @return array
   */
  public function visitProcedure($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['date'] = $this->dateRange;
    return $options;
  }

  /**
   *
   * @param AbstractObservation_DSTU2|AbstractObservation_R4 $endpoint
   * @return array
   */
  public function visitObservation($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['date'] = $this->dateRange;
    return $options;
  }

  /**
   *
   * @param Encounter $endpoint
   * @return array
   */
  public function visitEncounter($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['date'] = $this->dateRange;
    // $options['_include'] = 'encounter:Practitioner'; // this also load data for the referenced practitioners
    return $options;
  }

  /**
   * Undocumented function
   *
   * @param MedicationOrder|MedicationRequest $endpoint
   * @return array
   */
  public function visitMedication($endpoint, $options)
  {
    $fields = $this->mappingGroup->getFields();
    $options['patient'] = $this->patient_id;
    $options['status'] = $endpoint->getStatusParam($fields);
    return $options;
  }

  /**
   * Undocumented function
   *
   * @param DocumentReferenceClinicalNotes $endpoint
   * @return array
   */
  public function visitDocumentReferenceClinicalNotes($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['date'] = $this->dateRange;
    return $options;
  }

  /**
   * create a date range to use when filtering by date
   *
   * @param DateTime $date_min
   * @param DateTime $date_max
   * @return array
   * 
   * @see https://www.hl7.org/fhir/search.html#date
   */
  protected function makeDateRange($date_min, $date_max)
  {
    /**
     * apply the system timezone to a date
     * @param DateTime|string $date
     * @param string $timezoneID
     */
    $applyTimeZone = function($date, $timezoneID) {
      if(!($date instanceof DateTime)) $date = new DateTime($date);
      $systemTimezone = new DateTimeZone($timezoneID);
      $gmtTimezone = new DateTimeZone('GMT');
      $modifiedDate = clone $date;
      $modifiedDate->setTimezone($gmtTimezone);
      $offset = $systemTimezone->getOffset($modifiedDate);
      $interval = DateInterval::createFromDateString((string)$offset . ' seconds');
      $date->add($interval);
      return $date;
    };
    /**
     * Where possible, the system should correct for time zones when performing queries.
     * Dates do not have time zones, and time zones should not be considered.
     * Where both search parameters and resource element date times do not have time zones,
     * the servers local time zone should be assumed.
     * 
     * @see https://www.hl7.org/fhir/search.html#date
     */
    $convertFromGmt = function(&$dateTime, $timezoneID) use($applyTimeZone){
      $configVals = \System::getConfigVals();
      $fhir_convert_timestamp_from_gmt = boolval($configVals['fhir_convert_timestamp_from_gmt'] ?? 0);
      if(!$fhir_convert_timestamp_from_gmt) return;
      if($dateTime instanceof \DateTime) $dateTime = $applyTimeZone($dateTime, $timezoneID);
    };


    /** 
     * get the formatted time zone: +/-nn:nn
     * NOTE: results are different for daylight saving
     */
    $getFormattedTimezone = function($datetime, $timezoneID) {
      $tz = new DateTimeZone($timezoneID);
      $offset = $tz->getOffset($datetime) . ' seconds';
      $dateOffset = clone $datetime;
      $dateOffset->sub(DateInterval::createFromDateString($offset));
      
      $interval = $dateOffset->diff($datetime);
      $formatted = $interval->format('%R%H:%I');
      return $formatted;
    };

    $formatDate = function($datetime, $timezoneID) use($getFormattedTimezone) {
      $fhirCode = $this->fhirClient->getFhirVersionCode();
      if($fhirCode===FhirVersionManager::FHIR_DSTU2) {
        // for DSTU2 return just the date since time does not work (in Epic)
        $fhir_datetime_format = "Y-m-d";
        return $datetime->format($fhir_datetime_format);
      }
      $fhir_datetime_format = "Y-m-d\TH:i:s";
      $formattedDateTime = $datetime->format($fhir_datetime_format);
      $formattedTimezone = $getFormattedTimezone($datetime, $timezoneID);
      return "{$formattedDateTime}{$formattedTimezone}";
    };

    $timezoneID = getTimeZone();
    // $convertFromGmt($date_max, $timezoneID);
    // $convertFromGmt($date_max, $timezoneID);

    $params = [];
    if($date_min instanceof DateTime) $params[] = "ge{$formatDate($date_min, $timezoneID)}";
    if($date_max instanceof DateTime) $params[] = "le{$formatDate($date_max, $timezoneID)}";
    return $params;
  }

}