<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry\PropertySetInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class AllergyIntolerance extends AbstractResource
{
  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function recordedDate()
  {
    return $this->scraper()->recordedDate->join('');
  }

  public function clinicalStatus()
  {
    return $this->scraper()->status->join('');
  }

  public function getText()
  {
    return $this->scraper()->substance->text->join('');
  }

  public function getNdfRt()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::NDF_RT)[0]
      ->getData();
  }

  public function getFdaUnii()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::FDA_UNII)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_2)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_3)[0]
      ->getData();
  }

  public function getRxnorm()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::RxNorm)
      ->orWhere('system', 'like', CodingSystem::RxNorm_2)[0]
      ->getData();
  }

  public function getSnomed()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::SNOMED_CT)
      ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)[0]
      ->getData();
  }

  public function ndfRtDisplay()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::NDF_RT)
      ->orWhere('system', 'like', CodingSystem::NDF_RT_1)[0]
      ->display->join('');
  }

  public function ndfRtCode()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::NDF_RT)
      ->orWhere('system', 'like', CodingSystem::NDF_RT_1)[0]
      ->code->join('');
  }

  public function fdaUniiDisplay()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::FDA_UNII)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_2)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_3)[0]
      ->display->join('');
  }

  public function fdaUniiCode()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::FDA_UNII)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_2)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_3)[0]
      ->code->join('');
  }

  public function rxnormDisplay()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::RxNorm)
      ->orWhere('system', 'like', CodingSystem::RxNorm_2)[0]
      ->display->join('');
  }

  public function rxnormCode()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::RxNorm)
      ->orWhere('system', 'like', CodingSystem::RxNorm_2)[0]
      ->code->join('');
  }
  
  public function snomedDisplay()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::SNOMED_CT)
      ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)[0]
      ->display->join('');
  }

  public function snomedCode()
  {
    return $this->scraper()
      ->substance->coding
      ->where('system', 'like', CodingSystem::SNOMED_CT)
      ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)[0]
      ->code->join('');
  }

  public function getNormalizedTimestamp()
  {
    $timestamp = $this->recordedDate();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public static function getPropertyExtractors(): array
  {
    return [
      'fhir_id'             => fn(self $resource) => $resource->getFhirID(),
      'recorded_date'       => fn(self $resource) => $resource->recordedDate(),
      'normalized_timestamp'=> fn(self $resource) => $resource->getNormalizedTimestamp(),
      'clinical_status'     => fn(self $resource) => $resource->clinicalStatus(),
      'text'                => fn(self $resource) => $resource->getText(),
      'ndf_rt_display'      => fn(self $resource) => $resource->ndfRtDisplay(),
      'ndf_rt_code'         => fn(self $resource) => $resource->ndfRtCode(),
      'fda_unii_display'    => fn(self $resource) => $resource->fdaUniiDisplay(),
      'fda_unii_code'       => fn(self $resource) => $resource->fdaUniiCode(),
      'snomed_display'      => fn(self $resource) => $resource->snomedDisplay(),
      'snomed_code'         => fn(self $resource) => $resource->snomedCode(),
      'rxnorm_display'      => fn(self $resource) => $resource->rxnormDisplay(),
      'rxnorm_code'         => fn(self $resource) => $resource->rxnormCode(),
    ];
  }
  
}