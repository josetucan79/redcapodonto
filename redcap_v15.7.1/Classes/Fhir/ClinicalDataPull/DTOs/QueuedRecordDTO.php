<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

class QueuedRecordDTO extends DTO {
    use RecordLinkTrait;
    
    /** @var int|string */
    public $record;
}