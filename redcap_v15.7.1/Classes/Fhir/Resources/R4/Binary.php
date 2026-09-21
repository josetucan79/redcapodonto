<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\TimestampInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Binary extends AbstractResource
{
  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  

  /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts a Binary resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
   return [];
  }
  
}