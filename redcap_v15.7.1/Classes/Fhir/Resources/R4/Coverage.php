<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry\PropertySetInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;

class Coverage extends AbstractResource implements PropertySetInterface
{

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function getStatus() {
    return $this->scraper()
      ->status->join('');
  }

  public function getKind() {
    return $this->scraper()
      ->kind->join('');
  }

  public function getPlanName() {
    return $this->scraper()
      ->class
      ->where('type.coding.code', 'like', '/plan/i')
      ->name->join('');
  }

  public function getNetwork() {
    return $this->scraper()
      ->network->join('');
  }

  public function getPeridoStart() {
    return $this->scraper()
      ->period->start->join('');
  }

  public function getPeridoEnd() {
    return $this->scraper()
      ->period->end->join('');
  }

  public function getPeriod() {
    $start = $this->getPeridoStart();
    $end = $this->getPeridoEnd();
    $parts = [];
    $parts[] = $start !== '' ? $start : '---';
    $parts[] = $end !== '' ? $end : '---';
    return join(' / ', $parts);
  }

  public function getOrder() {
    return $this->scraper()
      ->order->join('');
  }

  public function getPayor($index=0) {
    return $this->scraper()
      ->payor[$index]->display->join('');
  }

  public function getTypeText() {
    return $this->scraper()
      ->type->text->join('');
  }

  public function getCostToBeneficiary() {
    return $this->scraper()
      ->costToBeneficiary->getData();
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
      'fhir_id'             => fn(self $resource) => $resource->getFhirID(),
      'plan_name'           => fn(self $resource) => $resource->getPlanName(),
      'payor_1'             => fn(self $resource) => $resource->getPayor(0),
      'network'             => fn(self $resource) => $resource->getNetwork(),
      'status'              => fn(self $resource) => $resource->getStatus(),
      'period_start'        => fn(self $resource) => $resource->getPeridoStart(),
      'period_end'          => fn(self $resource) => $resource->getPeridoEnd(),
      'period'              => fn(self $resource) => $resource->getPeriod(),
      'order'               => fn(self $resource) => $resource->getOrder(),
      'type_text'           => fn(self $resource) => $resource->getTypeText(),
      'cost_to_beneficiary' => fn(self $resource) => $resource->getCostToBeneficiary(),
    ];
  }
  
}