<?php namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull;

use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Utility\HTMLTruncator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Device;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Coverage;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Encounter;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Procedure;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Patient;
use Vanderbilt\REDCap\Classes\Fhir\Utility\DBDataNormalizer;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\AdverseEvent;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Immunization;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;

use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroup;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Observation;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\DocumentReference;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\MedicationRequest;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\MedicationOrder;
use Vanderbilt\REDCap\Classes\Fhir\Resources\ResourceVisitorInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Condition as Condition_R4;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\Condition as Condition_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\AllergyIntolerance as AllergyIntolerance_R4;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\AllergyIntolerance as AllergyIntolerance_DSTU2;
use Vanderbilt\REDCap\Classes\Utility\HtmlSanitizer;

/**
 * resource visitor that uses
 * the mapping format of REDCap CDIS projects:
 * [fields, dateMin, dateMax]
 */
class ResourceVisitor implements ResourceVisitorInterface
{

  /**
   *
   * @var FhirMappingGroup
   */
  private $mappingGroup;

  /**
   * list of mapped fields
   *
   * @var array
   */
  private $fields;
  /**
   * date range specified for these fields
   *
   * @var array
   */
  private $dateRange;


  /**
   * store modified data
   *
   * @var array
   */
  private $data = [];

  /**
   * text that separates multiple entries
   * in some categories (e.g. Medications, Conditions, etc...)
   *
   * @var string
   */
  private static $new_line_separator = "\r\n-----\r\n";

  /**
   *
   * @param FhirMappingGroup $mappingGroup
   */
  function __construct($mappingGroup)
  {
    $this->mappingGroup = $mappingGroup;
    $this->fields = $mappingGroup->getFields();
    $this->dateRange = [];
    if($dateMin = $mappingGroup->getDateMin()) $this->dateRange[] = $dateMin;
    if($dateMax = $mappingGroup->getDateMax()) $this->dateRange[] = $dateMax;
  }

  public function getData()
  {
    return $this->data;
  }

  public function addData($field, $value, $timestamp=null)
  {
     $data = [
      'field' => $field,
      'value' => $value,
      'timestamp' => $timestamp,
    ];
    $this->data[] = $data;
  }

  /**
   * update an entry at the specified index
   *
   * @param int $index
   * @param array $data
   * @return void
   */
  public function updateData($index, $data)
  {
    $previousEntry = $this->data[$index] ?? null;
    if(!$previousEntry) return;
    // make sure to get just the suitable data
    $updatedData = [
      'field' => $data['field'] ?? '',
      'value' => $data['value'] ?? '',
      'timestamp' => $data['timestamp'] ?? '',
    ];
    $this->data[$index] = $updatedData;
  }

  /**
   * get the index of the first corresponding
   * field in the data array.
   * this is an helper method used mostly 
   * for resources not meant to be repeatable (e.g. Medications, Conditions, Immunizations...)
   *
   * @param string $field
   * @return int|false
   */
  private function findEntry($field) {
      $fields = array_column($this->data, 'field');
      $index = array_search($field, $fields);
      return $index;
  }

  /**
   * squash text values for a resource that is not
   * meant to be repeatable (e.g. Medications, Conditions, Immunizations...)
   *
   * @param int $index position of the existing entry
   * @param string $text text to append to the existing entry
   * @return void
   */
  private function appendText($index, $text) {
    // update existing data if found (concat text)
    $previousEntry = $this->data[$index] ?? null;
    if(!$previousEntry) return;
    $previousValue = $previousEntry['value'] ?? '';
    $previousEntry['value'] = $previousValue.self::$new_line_separator.$text;
    $this->updateData($index, $previousEntry);
  }

  /**
   * adjust the data for the resource
   * always return an array of entries to
   * normalize the behaviour of observations where
   * we want to maitain one LOINC code per row
   * 
   * @param AbstractResource $resource
   * @return array
   */
  function visit($resource)
  {
    $class = get_class($resource);
    switch ($class) {
      case AdverseEvent::class:
        $this->visitAdverseEvent($resource);
        break;
      case Patient::class:
        $this->visitPatient($resource);
        break;
      case Observation::class:
        $this->visitObservation($resource);
        break;
      case Encounter::class:
        $this->visitEncounter($resource);
        break;
      case Coverage::class:
        $this->visitCoverage($resource);
        break;
      case Procedure::class:
        $this->visitProcedure($resource);
        break;
      case Device::class:
        $this->visitDevice($resource);
        break;
      case AllergyIntolerance_DSTU2::class:
      case AllergyIntolerance_R4::class:
        $this->visitAllergy($resource);
        break;
      case Immunization::class:
        $this->visitImmunization($resource);
        break;
      case Condition_DSTU2::class:
      case Condition_R4::class:
        $this->visitCondition($resource);
        break;
      case MedicationOrder::class:
      case MedicationRequest::class:
        $this->visitMedication($resource);
        break;
      case DocumentReference::class:
        $this->visitDocumentReference($resource);
        break;
      default:
        [$resource->getData()];
        break;
    }
  }

  /**
   * @param Patient $resource
   * @return void
   */
  public function visitPatient($resource)
  {
    $properties = Patient::getPropertyExtractors();
    // extract only mapped data
    foreach ($this->fields as $key) {
      $propertyFn = $properties[$key] ?? fn() => '';
      $value = $propertyFn($resource);
      $this->addData($key, $value);
    }
  }

  /**
   * filter based on mapped LOINC codes
   *
   * @param Observation $resource
   * @return void
   */
  public function  visitObservation($resource)
  {
    $observations = $resource->splitComponents(); // observation must be treated as a list because of 'components'
    
    foreach ($observations as $observation) {
      $codeableConcept = $observation->getCode();
      $codingList = $codeableConcept->getCoding();
      if(empty($codingList)) continue;
      $codes = array_column($codingList, 'code');
      // check all codings for matching LOINC codes
      array_walk($codingList, function($coding) use($observation) {
        $match = preg_match(CodingSystem::LOINC, ($coding['system'] ?? ''));
        if(!$match) return false;
        $code = $coding['code'] ?? '';
        $found = in_array($code, $this->fields);
        if(!$found) return false;
        $value = $observation->getValue();
        $timestamp = $observation->getNormalizedTimestamp();
        $this->addData($code, $value, $timestamp);
      });
    }
  }

  /**
   * get only medications with mapped status
   *
   * @param DocumentReference $resource
   * @return void
   */
  public function visitDocumentReference($resource)
  {
    $sanitizer = new HtmlSanitizer([
        'mode' => HtmlSanitizer::MODE_PERMISSIVE,
        'disallowedTags' => ['script', 'iframe'],
        'disallowedAttributes' => ['onclick'],
    ]);
      
    $codeableConcept = $resource->getType();
    $codingList = $codeableConcept->getCoding();
    if(empty($codingList)) return;
    // check all codings for matching LOINC codes
    array_walk($codingList, function($coding) use($resource, $sanitizer) {
      $match = preg_match(CodingSystem::LOINC, ($coding['system'] ?? ''));
      if(!$match) return false;
      $code = $coding['code'] ?? '';
      $key = 'clinical-note-'.$code;
      $found = in_array($key, $this->fields);
      if(!$found) return false;
      $timestamp = $resource->getNormalizedTimestamp();
      $html = $resource->getHTML();
      // sanitize
      $cleanHtml = $sanitizer->sanitize($html);
      $text = HTMLTruncator::truncate($cleanHtml, [
        HTMLTruncator::OPTION_MAX_LENGTH => DBDataNormalizer::MAX_FIELD_SIZE,
        HTMLTruncator::OPTION_TOO_LARGE_NOTICE => '',
      ]); // truncate html. it will also be truncated by the DBNormalizer
      
      if(empty($text)) return false;

      $this->addData($key, $text, $timestamp);
    });
  }

  /**
   * get only medications with mapped status
   *
   * @param MedicationOrder|MedicationRequest $resource
   * @return void
   */
  public function visitMedication($resource)
  {
    // helper function to extract text from the resource data
    $getText = function() use($resource) {
      $text = '';
      $medicationReference = $resource->getMedicationReference();
      $display = $medicationReference ?: $resource->getText();
      if($display) $text .= $display;
      if($dosage_timing = $resource->getDosageInstructionTiming()) $text .= ", {$dosage_timing}";
      if($status = $resource->getStatus()) $text .= " - {$status}";
      if($timestamp = $resource->getNormalizedTimestamp()) $text .= " - {$timestamp}";
      return $text;
    };
    $mappingIdentifier = 'medications-list';
    $statusList = preg_replace("/-.+-list$/",'', $this->fields);
    $status = $resource->getStatus();
    $statusIsMapped = in_array($status, $statusList);
    $allIsMapped = in_array($mappingIdentifier, $statusList);
    if(!$statusIsMapped && !$allIsMapped) return; // exit if the specific status or 'all' was not mapped

    $key = "$status-$mappingIdentifier"; // key as it is set in the metadata file
    $text = $getText();
    // record all 
    if($allIsMapped) $this->addOrAppendText($mappingIdentifier, $text);
    // record the specific status
    if($statusIsMapped) $this->addOrAppendText($key, $text);
  }

  /**
   *
   * @param Condition_R4|Condition_DSTU2 $resource
   * @return void
   */
  public function visitCondition($resource)
  {
    /**
     * Generates a readable string summarizing a condition resource.
     * Format: "<coding systems> - <status> - <timestamp> - <encounter>"
     * Each part is optional and included only if available.
     */
    $getText = function() use($resource) {

      // Adds " - " only if previous text exists
      $dashIf = function($text) { return $text ? ' - ' : ''; };

      $label = $resource->getLabel() ?: ''; // default text
      $printCodingSystem = function($codingSystem, $system=null) use($label){
        $system = $system ?: ($codingSystem['system'] ?? '');
        $display =  $codingSystem['display'] ?? $label;
        $code = $codingSystem['code'] ?? '';
        return sprintf('%s (%s %s)', $display, $system, $code);
      };
      $codingSystems = [];
      if($icd9Cm = $resource->getIcd9()) $codingSystems[] = $printCodingSystem($icd9Cm, CodingSystem::ICD_9_CM_NAME);
      if($icd10Cm = $resource->getIcd10()) $codingSystems[] = $printCodingSystem($icd10Cm, CodingSystem::ICD_10_CM_NAME);
      if($snomedCt = $resource->getSnomed()) $codingSystems[] = $printCodingSystem($snomedCt, CodingSystem::SNOMED_CT_NAME);

      $text = '';
      $text .= implode(', ', $codingSystems);

      // Use label if no codes are present
      if (!$text && $label) $text = $label;
      
      $status = $resource->getClinicalStatus() ?: ($resource->getClinicalStatusCode() ?: '');
      if($status) $text .= $dashIf($text) . $status;
      
      if($timestamp = $resource->getRecordedDate()) $text .= $dashIf($text) . $timestamp;

      if($encounter = $resource->getEncounterDisplay()) $text .= $dashIf($text) . $encounter;

      return $text;
    };

    $anyIdentifier = '__ANY__'; // identifier used to match any status
    // retrieve the list of mapped statuses, including the 'any' for all statuses
    $getStatusList = function($mappingIdentifier) use($anyIdentifier) {
      $statuses = [];
      foreach ($this->fields as $field) {
        if($field === $mappingIdentifier) $statuses[] = $anyIdentifier;
        else {
          $status = str_replace("-$mappingIdentifier", "", $field);
          $statuses[] = $status;
        }
      }
      return $statuses;
    };
    
    /*
    // generic expression with labeled capturing groups
    $pattern = '/^(?P<status>[^-]+)-(?P<category>.+)-list$/';
    if (preg_match($pattern, $string, $matches)) {
      // Extract the 'status', 'identifier' and the whole 'list' part
      $status = $matches['status'];
      $identifier = $matches['identifier'];
    }
    */

    $mappingCategory = $this->mappingGroup->getCategory();
    
    switch ($mappingCategory) {
      case FhirCategory::CONDITION_PROBLEMS:
        $mappingIdentifier = 'problem-list';
        break;
      case FhirCategory::CONDITION_DENTAL_FINDING:
        $mappingIdentifier = 'problem-dental-finding-list';
        break;
      case FhirCategory::CONDITION_GENOMICS:
        $mappingIdentifier = 'problem-genomics-list';
        break;
      case FhirCategory::CONDITION_INFECTION:
        $mappingIdentifier = 'problem-infection-list';
        break;
      case FhirCategory::CONDITION_MEDICAL_HISTORY:
        $mappingIdentifier = 'problem-medical-history-list';
        break;
      case FhirCategory::CONDITION_REASON_FOR_VISIT:
        $mappingIdentifier = 'problem-reason-for-visit-list';
        break;
      case FhirCategory::DIAGNOSIS:
        $mappingIdentifier = 'encounter-diagnosis-list';
        break;
      default:
        $mappingIdentifier = null;
        break;
    }

    if(!$mappingIdentifier) return;

    // this applies to all
    $statusList = $getStatusList($mappingIdentifier);
    $status = $resource->getClinicalStatus();
    $statusIsMapped = in_array($status, $statusList);
    $allIsMapped = in_array($anyIdentifier, $statusList);
    if(!$statusIsMapped && !$allIsMapped) return; // exit if the specific status or 'all' was not mapped
    // $categoryCode = $resource->getCategoryCode();
    $key = "$status-$mappingIdentifier"; // key as it is set in the metadata file
    $text = $getText();
    // record all 
    if($allIsMapped) $this->addOrAppendText($mappingIdentifier, $text);
    // record the specific status
    if($statusIsMapped) $this->addOrAppendText($key, $text);
  }

  /**
   *
   * @param AllergyIntolerance_DSTU2|AllergyIntolerance_R4 $resource
   * @return void
   */
  public function visitAllergy($resource)
  {
    // helper function to extract text from the resource data
    $getText = function() use($resource) {
      $printCodingSystem = function($codingSystem, $system=null) {
        $system = $system ?: ($codingSystem['system'] ?? '');
        $display = $codingSystem['display'] ?? '';
        $code = $codingSystem['code'] ?? '';
        return sprintf('%s (%s %s)', $display, $system, $code);
      };
      $codingSystems = [];
      if($rxnorm = $resource->getRxnorm()) $codingSystems[] = $printCodingSystem($rxnorm, CodingSystem::RxNorm_NAME);
      if($ndfRt = $resource->getNdfRt()) $codingSystems[] = $printCodingSystem($ndfRt, CodingSystem::NDF_RT_NAME);
      if($fdaUnii = $resource->getFdaUnii()) $codingSystems[] = $printCodingSystem($fdaUnii, CodingSystem::FDA_UNII_NAME);
      if($snomed = $resource->getSnomed()) $codingSystems[] = $printCodingSystem($snomed, CodingSystem::SNOMED_CT_NAME);

      $text = '';
      $text .= implode(', ', $codingSystems);
      $substanceText = $resource->getText();
      if(empty($codingSystems) && $substanceText) $text .= $substanceText;
      $timestamp = $resource->getNormalizedTimestamp();
      if($timestamp) $text .= " - {$timestamp}";
      return $text;
    };
    // value:"LATEX (RxNorm 1314891), LATEX (FDA UNII 2LQ0UUW8IN) - 2024-07-12"
    $key = "allergy-list"; // key as it is set in the metadata file
    $text = $getText();
    $this->addOrAppendText($key, $text);
  }

  /**
   *
   * @param AdverseEvent $resource
   * @return void
   */
  public function visitAdverseEvent($resource)
  {
    // helper function to extract text from the resource data
    $getText = function() use($resource) {
      $text = '';
      if($event = $resource->getEvent()) $text .= $event;
      if($causality = $resource->getCausality()) $text .= sprintf(' - %s', $causality);
      if($studies = $resource->getStudies()) $text .= sprintf(' (%s)', $studies);
      if($seriousness = $resource->getSeriousness()) $text .= sprintf(' - %s', $seriousness);
      if($outcome = $resource->getOutcome()) $text .= sprintf(', %s', $outcome);
      if($timestamp = $resource->getNormalizedTimestamp()) $text .= sprintf(' - %s', $timestamp);
      return $text;
    };
    $key = "adverse-events-list"; // key as it is set in the metadata file
    $text = $getText();
    $this->addOrAppendText($key, $text);
  }

  /**
   *
   * @param Immunization $resource
   * @return void
   */
  public function visitImmunization($resource)
  {
    // helper function to extract text from the resource data
    $getText = function() use($resource) {
      $text = '';
      if($label = $resource->getText()) $text .= $label;
      if($cdv_code = $resource->getCvxCode()) $text .= sprintf(' (CVX %s)', $cdv_code);
      if($status = $resource->getStatus()) $text .= sprintf(' - %s', $status);
      if($timestamp = $resource->getNormalizedTimestamp()) $text .= sprintf(' - %s', $timestamp);
      return $text;
    };
    $key = "immunizations-list"; // key as it is set in the metadata file
    $text = $getText();
    $this->addOrAppendText($key, $text);
  }

  /**
   *
   * @param Encounter $resource
   * @return void
   */
  public function visitEncounter($resource) {
    $getText = function() use($resource) {
      $text = '';
      if($class = $resource->getClass()) $text .= $class;
      // if($type = $resource->getTypeDisplay() ?? $resource->getTypeText()) $text .= sprintf(' (%s)', $type);
      if($location = $resource->getLocation()) $text .= sprintf(' @ %s', $location);
      if($status = $resource->getStatus()) $text .= sprintf(', %s', $status);

      $dateRange = $this->makeDateTimeRange($resource->getPeriodStart(), $resource->getPeriodEnd());
      $text .= sprintf(' (%s)', $dateRange);
      return $text;
    };
    $key = "encounters-list"; // key as it is set in the metadata file
    $text = $getText();
    $this->addOrAppendText($key, $text);
  }

  /**
   *
   * @param Coverage $resource
   * @return void
   */
  public function visitCoverage($resource)
  {
    $getText = function() use($resource) {
      $text = '';
      if($planName = $resource->getPlanName()) $text .= sprintf('%s', $planName);
      if($payor_1 = $resource->getPayor(0)) $text .= sprintf(', %s', $payor_1);
      if($status = $resource->getStatus()) $text .= sprintf(' - %s', $status);

      $dateRange = $this->makeDateTimeRange($resource->getPeridoStart(), $resource->getPeridoEnd());
      $text .= sprintf(' (%s)', $dateRange);
      return $text;
    };
    $key = "coverage-list";
    $text = $getText();
    $this->addOrAppendText($key, $text);
  }

  /**
   *
   * @param Procedure $resource
   * @return void
   */
  public function visitProcedure($resource)
  {
    $getText = function() use($resource) {
      $text = '';
      if($category = $resource->getCategoryDisplay() ?? $resource->getCategory()) $text .= sprintf('%s', $category);
      if($code = $resource->getCodeText()) $text .= sprintf(', %s', $code);
      if($status = $resource->getStatus()) $text .= sprintf(' - %s', $status);
      if($datePerformed = $resource->getPerformedDateTime()) $text .= sprintf(' (%s)', $datePerformed);
      return $text;
    };
    $key = "procedure-list";
    $text = $getText();
    $this->addOrAppendText($key, $text);
  }

  /**
   *
   * @param Device $resource
   * @return void
   */
  public function visitDevice($resource)
  {
    $getText = function() use($resource) {
      $text = '';
      if($deviceName = $resource->getDeviceName()) $text .= sprintf('%s', $deviceName);
      if($type = $resource->getType()) $text .= sprintf(', %s', $type);
      if($modelNumber = $resource->getModelNumber()) $text .= sprintf(' (# %s)', $modelNumber);
      if($site = $resource->getSite()) $text .= sprintf(', %s', $site);
      if($permanence = $resource->getPermanence()) $text .= sprintf(', %s', $permanence);
      if($laterality = $resource->getLaterality()) $text .= sprintf(', %s', $laterality);
      if($expirationDate = $resource->getExpirationDate()) $text .= sprintf(' (%s)', $expirationDate);
      return $text;
    };
    $key = "device-list";
    $text = $getText();
    $this->addOrAppendText($key, $text);
  }

  /**
   * Adds a new text entry under a specified key or appends text to an existing entry.
   *
   * This method first attempts to find an existing entry using the provided key.
   * If an entry is found, the text is appended to it. If no entry is found, a new
   * entry with the key and text is created.
   *
   * @param string $key The key associated with the text entry.
   * @param string $text The text to be added or appended.
   */
  private function addOrAppendText($key, $text)
  {
    $previousEntryIndex = $this->findEntry($key);
    if($previousEntryIndex===false) $this->addData($key, $text);
    else $this->appendText($previousEntryIndex, $text);
  }

  /**
   * Creates a formatted date-time range string from given start and end values,
   * using a specified placeholder for null values.
   *
   * This method takes start and end date-time values and formats them into a 
   * single string. If either the start or end values are null, they are replaced 
   * with the specified placeholder. The default placeholder is '---'. The start 
   * and end values (or placeholders) are joined with ' / ' to signify the range.
   *
   * @param string|null $start The start date-time value. Can be null.
   * @param string|null $end The end date-time value. Can be null.
   * @param string $placeholder The string to use as a placeholder for null values.
   *                            Default is '---'.
   * @return string The formatted date-time range as a string.
   */
  private function makeDateTimeRange($start, $end, $placeholder = '---') {
    $periods = [];
    $periods[] = $start ?? $placeholder;
    $periods[] = $end ?? $placeholder;
    return join(' / ', $periods);
  }

}