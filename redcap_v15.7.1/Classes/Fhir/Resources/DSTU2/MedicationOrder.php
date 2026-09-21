<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry\PropertySetInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodeableConcept;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class MedicationOrder extends AbstractResource
{

  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function getStatus()
  {
    return $this->scraper()->status->join('');
  }

  public function getDosageText()
  {
    return $this->scraper()->dosageInstruction->text->join('');
  }

  public function getDosageInstructionRoute()
  {
    return $this->scraper()->dosageInstruction->route->text->join('');
  }

  public function getDosageInstructionTiming()
  {
    return $this->scraper()->dosageInstruction->timing->code->text->join('');
  }

  public function getDateWritten()
  {
    return $this->scraper()->dateWritten->join('');
  }

  public function getPrescriber()
  {
    return $this->scraper()->prescriber->display->join('');
  }

  public function getMedicationReference()
  {
    return $this->scraper()->medicationReference->display->join('');
  }

  public function getMedicationCodeableConcept()
  {
    $payload = $this->scraper()->medicationCodeableConcept->getData();
    return new CodeableConcept($payload);
  }

  public function rxnormDisplay()
  {
    return $this->scraper()
      ->medicationCodeableConcept->coding
      ->where('system', 'like', CodingSystem::RxNorm)
      ->orWhere('system', 'like', CodingSystem::RxNorm_2)
      ->display->join('');
  }

  public function rxnormCode()
  {
    return $this->scraper()
      ->medicationCodeableConcept->coding
      ->where('system', 'like', CodingSystem::RxNorm)
      ->orWhere('system', 'like', CodingSystem::RxNorm_2)
      ->code->join();
  }

  public function split()
  {
    $codeableConcept = $this->getMedicationCodeableConcept();
    $codings = $codeableConcept->getCoding();
    if(empty($codings)) return [$this];
    if(count($codings)<=1) return [$this];
    $text = $codeableConcept->getText();
    $parentPayload = $this->getPayload();
    $list = array_map(function($coding) use($text, $parentPayload) {
      // make a new code payload that will replace the existing one
      $payload = [
        'coding' => [$coding],
        'text' => $text,
      ];
      return $this->replacePayload($parentPayload, $payload);
    }, $codings);
    return $list;
  }

  public function getNormalizedTimestamp()
  {
    $timestamp = $this->getDateWritten();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getText()
  {
    // add medication concept if available
    $medicationCodeableConcept = $this->getMedicationCodeableConcept();
    return $medicationCodeableConcept->getText();
  }

  public function getNormalizedMedicationCodeableConcept() {
    $medicationCodeableConcept = $this->getMedicationCodeableConcept();
    return$medicationCodeableConcept->isEmpty() ? '' : $medicationCodeableConcept->getData();
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts a MedicationOrder resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
    return [
      'fhir_id'                   => fn(self $resource) => $resource->getFhirID(),
      'status'                    => fn(self $resource) => $resource->getStatus(),
      'display'                   => fn(self $resource) => $resource->getMedicationReference(),
      'timestamp'                 => fn(self $resource) => $resource->getDateWritten(),
      'normalized_timestamp'      => fn(self $resource) => $resource->getNormalizedTimestamp(),
      'dosage'                    => fn(self $resource) => $resource->getDosageText(),
      'dosage_instruction_route'  => fn(self $resource) => $resource->getDosageInstructionRoute(),
      'dosage_instruction_timing' => fn(self $resource) => $resource->getDosageInstructionTiming(),
      'rxnorm_code'               => fn(self $resource) => $resource->rxnormCode(),
      'rxnorm_display'            => fn(self $resource) => $resource->rxnormDisplay(),
      'medicationCodeableConcept' => fn(self $resource) => $resource->getNormalizedMedicationCodeableConcept(),
      'text'                      => fn(self $resource) => $resource->getText(),
    ];
  }
  
}