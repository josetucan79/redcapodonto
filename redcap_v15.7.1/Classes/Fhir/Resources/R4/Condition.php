<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry\PropertySetInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodeableConcept;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Condition extends AbstractResource implements PropertySetInterface
{
  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';


  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  /**
   * for backward compatibility with DSTU2,
   * where the clinical status could be a string
   *
   * @return string
   */
  public function getClinicalStatus() {
    return $this->getClinicalStatusCode();
  }

  public function getClinicalStatusCode()
  {
    return $this->scraper()
      ->clinicalStatus->coding->code->join('');
  }

  public function getCategoryCode()
  {
    return $this->scraper()
      ->category->coding->code->join('');
  }

  public function getRecordedDate()
  {
    return $this->scraper()
      ->recordedDate->join('');
  }

  function getNormalizedTimestamp() {
    $timestamp = $this->getRecordedDate();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getVerificationStatus()
  {
    return $this->scraper()->verificationStatus->coding->code->join('');
  }

  public function getLabel()
  {
    return $this->scraper()->code->text->join('');
  }

  /**
   *
   * @return CodeableConcept
   */
  public function getCode()
  {
    $payload = $this->scraper()
      ->code->getData();
    return new CodeableConcept($payload);
  }

  /**
   * get data from the code
   * portion of the payload
   *
   * @return array
   */
  public function getIcd10()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::ICD_10_CM)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CM_1)
      ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO)
      ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO_DUTCH_VARIANT)
      ->orWhere('system', 'like', CodingSystem::ICD_10_AE)
      ->orWhere('system', 'like', CodingSystem::ICD_10_PCS)
      ->orWhere('system', 'like', CodingSystem::ICD_10_AM)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA_1)
      ->orWhere('system', 'like', CodingSystem::ICD_10_NL)
      ->orWhere('system', 'like', CodingSystem::ICD_10_NL_1)
      [0]->getData();
  }

  /**
   * get data from the code
   * portion of the payload
   *
   * @return array
   */
  public function getIcd9()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::ICD_9_CM)
      [0]->getData();
  }

  /**
   * get data from the code
   * portion of the payload
   *
   * @return array
   */
  public function getSnomed()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::SNOMED_CT)
      ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)
      [0]->getData();
  }

  public function getDisplayIcd10()
  {
    return $this->scraper()
        ->code->coding
        ->where('system', 'like', CodingSystem::ICD_10_CM)
        ->orWhere('system', 'like', CodingSystem::ICD_10_CM_1)
        ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO)
        ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO_DUTCH_VARIANT)
        ->orWhere('system', 'like', CodingSystem::ICD_10_AE)
        ->orWhere('system', 'like', CodingSystem::ICD_10_PCS)
        ->orWhere('system', 'like', CodingSystem::ICD_10_AM)
        ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA)
        ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA_1)
        ->orWhere('system', 'like', CodingSystem::ICD_10_NL)
        ->orWhere('system', 'like', CodingSystem::ICD_10_NL_1)
        ->display->join('');
  }

  public function getCodeIcd10()
  {
    return $this->scraper()->code->coding
      ->where('system', 'like', CodingSystem::ICD_10_CM)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CM_1)
      ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO)
      ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO_DUTCH_VARIANT)
      ->orWhere('system', 'like', CodingSystem::ICD_10_AE)
      ->orWhere('system', 'like', CodingSystem::ICD_10_PCS)
      ->orWhere('system', 'like', CodingSystem::ICD_10_AM)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA_1)
      ->orWhere('system', 'like', CodingSystem::ICD_10_NL)
      ->orWhere('system', 'like', CodingSystem::ICD_10_NL_1)
      ->code->join('');
  }

  public function getDisplayIcd9()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::ICD_9_CM)
      ->display->join('');
  }

  public function getCodeIcd9()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::ICD_9_CM)
      ->code->join('');
  }

  public function getDisplaySnomedCt()
  {
    return $this->scraper()->code->coding
      ->where('system', 'like', CodingSystem::SNOMED_CT)
      ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)
      ->dislpay->join('');
  }

  public function getCodeSnomedCt()
  {
    return $this->scraper()->code->coding
    ->where('system', 'like', CodingSystem::SNOMED_CT)
    ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)
    ->code->join('');
  }

  public function getCodeText() {
    return $this->scraper()->code->text->join('');
  }

  public function getNote()
  {
    return $this->scraper()
      ->note->text->join('');
  }

  public function getEncounterReference()
  {
    return $this->scraper()
      ->encounter->reference->join('');
  }

  public function getEncounterDisplay()
  {
    return $this->scraper()
      ->encounter->display->join('');
  }

  public function getRecorder()
  {
    return $this->scraper()
      ->recorder->display->join('');
  }

  public function getRecorderType()
  {
    return $this->scraper()
      ->recorder->type->join('');
  }

  public function getBodySite($index=0) {
    return $this->scraper()
      ->bodySite[$index]->text->join('');
  }

  /**
   * fallback to label if code and !display
   *
   * @return string
   */
  public function getNormalizedDisplayIcd10() {
    $label = $this->getLabel();
    $icd10_code = $this->getCodeIcd10();
    return $this->getDisplayIcd10() ?: ($icd10_code ? $label : '');
  }

  /**
   * fallback to label if code and !display
   *
   * @return string
   */
  public function getNormalizedDisplayIcd9() {
    $label = $this->getLabel();
    $icd9_code = $this->getCodeIcd9();
    return $this->getDisplayIcd9() ?: ($icd9_code ? $label : '');
  }

  /**
   * fallback to label if code and !display
   *
   * @return string
   */
  public function getNormalizedDisplaySnomedCt() {
    $label = $this->getLabel();
    $snomedCt_code = $this->getCodeSnomedCt();
    return $this->getDisplaySnomedCt() ?: ($snomedCt_code ? $label : ''); //fallback to label if code and !display
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts an AllergyIntolerance resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
   return [
    'fhir_id'               => fn(self $resource) => $resource->getFhirID(),
    'clinical_status'       => fn(self $resource) => $resource->getClinicalStatus(),
    'timestamp'             => fn(self $resource) => $resource->getRecordedDate(),
    'normalized_timestamp'  => fn(self $resource) => $resource->getNormalizedTimestamp(),
    'label'                 => fn(self $resource) => $resource->getLabel(),
    'icd-10-code'           => fn(self $resource) => $resource->getCodeIcd10(),
    'icd-10-display'        => fn(self $resource) => $resource->getNormalizedDisplayIcd10(),
    'icd-9-code'            => fn(self $resource) => $resource->getCodeIcd9(),
    'icd-9-display'         => fn(self $resource) => $resource->getNormalizedDisplayIcd9(),
    'snomed-ct-code'        => fn(self $resource) => $resource->getCodeSnomedCt(),
    'snomed-ct-display'     => fn(self $resource) => $resource->getNormalizedDisplaySnomedCt(),
    'verification-status'   => fn(self $resource) => $resource->getVerificationStatus(),
    'recorder'              => fn(self $resource) => $resource->getRecorder(),
    'recorder_type'         => fn(self $resource) => $resource->getRecorderType(),
    'note'                  => fn(self $resource) => $resource->getNote(),
    'encounter_reference'   => fn(self $resource) => $resource->getEncounterReference(),
    'encounter_label'       => fn(self $resource) => $resource->getEncounterDisplay(),
    'body_site_1'           => fn(self $resource) => $resource->getBodySite(0),
    'category_code'         => fn(self $resource) => $resource->getCategoryCode(),
   ];
  }
  
}