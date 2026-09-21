<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry\PropertySetInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodeableConcept;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;
use Vanderbilt\REDCap\Classes\Traits\CanConvertMimeTypeToExtension;

class Procedure extends AbstractResource
{
  use CanNormalizeTimestamp;
  use CanConvertMimeTypeToExtension;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  public function getFhirID() {
    return $this->scraper()->id->join('');
  }

  public function getStatus() {
    return $this->scraper()->status->join('');
  }

  public function getEncounterReference()
  {
    return $this->scraper()->encounter->reference->join('');
  }

  public function getCodeText() {
    return $this->scraper()->code->text->join('');
  }

  public function getCodeCpt() {
    return $this->scraper()->code->coding->where('system','~', CodingSystem::AMA_CPT)->code->join('');
  }

  public function getDisplayCpt() {
    return $this->scraper()->code->coding->where('system','~', CodingSystem::AMA_CPT)->display->join('');
  }

  public function getCategory() {
    return $this->scraper()->category->text->join('');
  }

  public function getCategoryDisplay($index=0) {
    return $this->scraper()->category->coding[$index]->display->join('');
  }

  public function getReason() {
    return $this->scraper()->reasonCode->text->join('');
  }

  public function getReasonDisplay($index=0) {
    return $this->scraper()->reasonCode->coding[$index]->display->join('');
  }

  public function getAuthorType($index=0) {
    return $this->scraper()->author[$index]->type->join('');
  }

  public function getPerformedDateTime() {
    return $this->scraper()->performedDateTime->join('');
  }


  public function getOutcome() {
    $codingsPayload = $this->scraper()->type[0]->getData();
    return new CodeableConcept($codingsPayload);
  }

  public function getComplication() {
    $codingsPayload = $this->scraper()->type[0]->getData();
    return new CodeableConcept($codingsPayload);
  }

  public function getNote() {
    $notes = $this->scraper()->note->getData();
    $text = '';
    if(!is_array(($notes))) return $text;
    foreach ($notes as $note) {
      $text .= $note['text'];
    }
    return $text;
  }

  public function getNormalizedCategory()
  {
    return $this->getCategoryDisplay() ?: ($this->getCategory() ?: '');
  }

  public function getNormalizedReason()
  {
    return $this->getReasonDisplay() ?: ($this->getReason() ?: '');
  }

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts a Procedure resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
    return [
      'fhir_id'              => fn(self $resource) => $resource->getFhirID(),
      'status'               => fn(self $resource) => $resource->getStatus(),
      'category'             => fn(self $resource) => $resource->getNormalizedCategory(),
      'category-display'     => fn(self $resource) => $resource->getCategoryDisplay(),
      'category-text'        => fn(self $resource) => $resource->getCategory(),
      'reason'               => fn(self $resource) => $resource->getNormalizedReason(),
      'reason-display'       => fn(self $resource) => $resource->getReasonDisplay(),
      'reason-text'          => fn(self $resource) => $resource->getReason(),
      'outcome'              => fn(self $resource) => $resource->getOutcome(),
      'complication'         => fn(self $resource) => $resource->getComplication(),
      'encounter_reference'  => fn(self $resource) => $resource->getEncounterReference(),
      'performed-date-time'  => fn(self $resource) => $resource->getPerformedDateTime(),
      'code'                 => fn(self $resource) => $resource->getCodeText(),
      'cpt-code'             => fn(self $resource) => $resource->getCodeCpt(),
      'cpt-display'          => fn(self $resource) => $resource->getDisplayCpt(),
      'note'                 => fn(self $resource) => $resource->getNote(),
    ];
  }


}