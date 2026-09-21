<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Shared;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry\PropertySetInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;
use Vanderbilt\REDCap\Classes\Fhir\Utility\ArrayUtils;

class Observation extends AbstractResource
{

  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  /**
   * get the local or GMT timestamp
   * 
   * @param boolean $localTimestamp
   * @return string
   */
  public function getTimestamp($localTimestamp=false)
  {
    $callable = $this->getTimestampCallable($this->getDate(), self::TIMESTAMP_FORMAT);
    return $callable($localTimestamp);
  }

  public function getId()
  {
    return $this->scraper()->id->join('');
  }

  /**
   * return the category of the observation (Laboratory, Lab, Vital Signs)
   *
   * @return void
   */
  public function getCategory()
  {
    return $this->scraper()->category->text->getData();
  }

  /**
   * select a loinc code
   * valid selections are:
   * - '#/code/coding/\d+': \d+, any number, means that we are selecting the list of coding
   * - '#/code/coding/.*': other version the dot (.) means any character
   * @return array
   */
  public function getLoincCodes()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::LOINC)
      ->code->getData();
  }

  public function getLoincDisplays()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::LOINC)
      ->display->getData();
  }

  public function getDate()
  {
    $scraper =  $this->scraper();
    $effectiveDateTime = $scraper->effectiveDateTime->join('');
    $issued = $scraper->issued->join(''); // fallback to issued time
    return $effectiveDateTime ?? $issued ?? '';
  }

  public function getValueQuantity()
  {
    return $this->scraper()->valueQuantity[0]->getData();
  }

  public function getValueString()
  { 
    return $this->scraper()->valueString->join('');
  }
  public function getValueCodeableConcept()
  {
    return $this->scraper()->valueCodeableConcept[0]->getData();
  }
  public function getValueCodeableConceptCode()
  {
    return $this->scraper()->valueCodeableConcept->coding->code->getData();
  }
  public function getValueCodeableConceptText()
  {
    return $this->scraper()->valueCodeableConcept->text->join('');
  }
  public function getValueCodeableConceptValue()
  {
    $valueCodeableConceptParts = [
      'codeableConceptCode' => $codeableConceptCode = $this->getValueCodeableConceptCode(),
      'codeableConceptText' => $codeableConceptText = $this->getValueCodeableConceptText(),
    ];
    $valueCodeableConcept = ($codeableConceptCode || $codeableConceptText) ? implode(' - ', $valueCodeableConceptParts) : '';
    return $valueCodeableConcept;
  }

  public function getValueBoolean()
  {
    return $this->scraper()->valueBoolean->join('');
  }
  public function getValueInteger()
  {
    return $this->scraper()->valueInteger->join('');
  }
  public function getValueRange()
  {
    return $this->scraper()->valueRange->getData();
  }
  public function getValueRatio()
  {
    return $this->scraper()->valueRatio->getData();
  }
  public function getValueSampledData()
  {
    return $this->scraper()->valueSampledData->getData();
  }
  public function getValueTime()
  {
    return $this->scraper()->valueTime->join('');
  }
  public function getValueDateTime()
  {
    return $this->scraper()->valueDateTime->join('');
  }
  public function getValuePeriod()
  {
    return $this->scraper()->valuePeriod->getData();
  }

  public function getValue()
  {
    $callables = [
      'valueString' => function() { return $this->scraper()->valueString->join(''); },
      'valueBoolean' => function() { return $this->scraper()->valueBoolean->join(''); },
      'valueInteger' => function() { return $this->scraper()->valueInteger->join(''); },
      'valueTime' => function() { return $this->scraper()->valueTime->join(''); },
      'valueDateTime' => function() { return $this->scraper()->valueDateTime->join(''); },
      'valueCodeableConcept' => function() {
        $data = $this->getValueCodeableConcept();
        $codings = $data['condings'] ?? [];
        $parts = [];
        foreach ($codings as $coding) {
          $code = $coding['code'] ?? null;
          if($code) {
            $parts[] = $code;
            break;
          }
        }
        $parts[] = $text = $data['text'] ?? '';
        return implode(' - ', $parts);
      },
      'valueQuantity' => function() {
        $data = $this->getValueQuantity();
        $parts = [];
        $addPartIfKeyExists = function($key) use($data, &$parts) {
          $part = $data[$key] ?? null;
          if($part) $parts[] = $part;
        };
        $addPartIfKeyExists('comparator');
        $addPartIfKeyExists('value');
        return implode(' ', $parts);
      },
      'valuePeriod' => function() {
        $data = $this->getValuePeriod();
        $parts = [];
        if($start = $data['start'] ?? null) $parts[] = $start;
        if($end = $data['end'] ?? null) $parts[] = $end;
        return implode(' -> ', $parts);
      },
      'valueRatio' => function() {
        $data = $this->getValueRatio();
        $parts = [];
        if($numerator = $data['numerator']['value'] ?? null) $parts[] = $numerator;
        if($denominator = $data['denominator']['value'] ?? null) $parts[] = $denominator;
        return implode(' / ', $parts);
      },
      'valueRange' => function() {
        $data = $this->getValueRange();
        $parts = [];
        $lowValue = $data['low']['value'] ?? '';
        $lowUnit = $data['low']['unit'] ?? '';
        $highValue = $data['high']['value'] ?? '';
        $highUnit = $data['high']['unit'] ?? '';
        $parts[] = $lowValue . ($lowUnit ? ' ' . $lowUnit : '');
        $parts[] = $highValue . ($highUnit ? ' ' . $highUnit : '');
        return implode(' - ', $parts);
      },
      'valueSampleData' => function() {
        $data = $this->getValueSampledData();
        $parts = [];
        $lowValue = $data['low']['value'] ?? '';
        $lowUnit = $data['low']['unit'] ?? '';
        $highValue = $data['high']['value'] ?? '';
        $highUnit = $data['high']['unit'] ?? '';
        $parts[] = $lowValue . ($lowUnit ? ' ' . $lowUnit : '');
        $parts[] = $highValue . ($highUnit ? ' ' . $highUnit : '');
        return implode(' - ', $parts);
      },
    ];
    
    $callable = ArrayUtils::find($callables, function($callable) {
      return $callable() !== '';
    });
    if(!is_callable($callable)) return '';
    return $callable();
  }

  public function getValueUnit()
  {
    return $this->scraper()->valueQuantity->unit->join('');
  }

  /**
   * components are subset that need to be split.
   * each component only contains
   * - 1 `code` with 1 or more coding systems
   * - 0 or 1 `value`
   * @see https://www.hl7.org/fhir/observation.html
   *
   * @return array
   */
  public function getComponent()
  {
    return $this->scraper()->component->getData();
  }

  
  public function getCodingSystems()
  {
    return $this->scraper()->code->coding->getData();
  }

  /**
   * create a CodeableConcept from the code
   * portion of the payload
   *
   * @return CodeableConcept
   */
  public function getCode()
  {
    $payload = $this->scraper()->code->getData();
    return new CodeableConcept($payload);
  }

  /**
   * create separate observations
   * for each component
   *
   * @return Observation[]
   */
  public function splitComponents()
  {
    $components = $this->getComponent();
    if(empty($components)) return [$this];
    $parentPayload = $this->getPayload();
    unset($parentPayload['component']);
    $list = array_map(function($payload) use($parentPayload) {
      return $this->replacePayload($parentPayload, $payload);
    }, $components);
    return $list;
  }

  /**
   * create separate observations
   * for each coding
   *
   * @return Observation[]
   */
  public function splitCodings()
  {
    $codeableConcept = $this->getCode();
    $codings = $codeableConcept->getCoding();
    if(empty($codings)) return [$this];
    if(count($codings)<=1) return [$this];
    $text = $codeableConcept->getText();
    $parentPayload = $this->getPayload();
    $list = array_map(function($coding) use($parentPayload, $text) {
      // make a new code payload that will replace the existing one
      $payload = [
        'code' => [
          'coding' => [$coding],
          'text' => $text,
        ]
      ];
      return $this->replacePayload($parentPayload, $payload);
    }, $codings);
    return $list;
  }

  /**
   * observation resources should always be returned
   * as array because could contain components.
   * 
   * splits first based on components, then based on codings
   * @see https://www.hl7.org/fhir/observation-definitions.html#Observation.component
   *
   * @return Observation[]
   */
  public function split()
  {
    $reduceObservations = function($carry, $observation) {
      $list = $observation->splitCodings();
      foreach ($list as $splitted) {
          $carry[] = $splitted;
      }
      return $carry;
    };
    $observations = $this->splitComponents();
    $list = array_reduce($observations, $reduceObservations, []);
    return $list;
  }

  public function getNormalizedTimestamp()
  {
    $timestamp = $this->getDate();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getNormalizedLocalTimestamp() {
    $timestamp = $this->getDate();
    return $this->getLocalTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public static function getPropertyExtractors(): array
  {
    return [
      'fhir-id'               => fn($resource) => $resource->getId(),
      'code'                  => fn($resource) => $resource->getCode()->getData(),
      'category'              => fn($resource) => $resource->getCategory(),
      'timestamp'             => fn($resource) => $resource->getDate(),
      'normalized_timestamp'  => fn($resource) => $resource->getNormalizedTimestamp(),
      'local_timestamp'       => fn($resource) => $resource->getNormalizedLocalTimestamp(),
      'value'                 => fn($resource) => $resource->getValue(),
      'valueUnit'             => fn($resource) => $resource->getValueUnit(),
      'valueQuantity'         => fn($resource) => $resource->getValueQuantity(),
      'valueString'           => fn($resource) => $resource->getValueString(),
      'valueCodeableConcept'  => fn($resource) => $resource->getValueCodeableConcept(),
      'valueBoolean'          => fn($resource) => $resource->getValueBoolean(),
      'valueInteger'          => fn($resource) => $resource->getValueInteger(),
      'valueRange'            => fn($resource) => $resource->getValueRange(),
      'valueRatio'            => fn($resource) => $resource->getValueRatio(),
      'valueSampledData'      => fn($resource) => $resource->getValueSampledData(),
      'valueTime'             => fn($resource) => $resource->getValueTime(),
      'valueDateTime'         => fn($resource) => $resource->getValueDateTime(),
      'valuePeriod'           => fn($resource) => $resource->getValuePeriod(),
      'component'             => fn($resource) => $resource->getComponent(),
    ];
  }
  
}