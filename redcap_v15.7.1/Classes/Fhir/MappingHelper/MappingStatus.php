<?php
namespace Vanderbilt\REDCap\Classes\Fhir\MappingHelper;

use JsonSerializable;

/**
 * representation of the mapping status of a FHIR resource.
 * - if the status is 'mapped' and no $mapped_keys are listed, then the enrty is considered mapped
 * - if the status is mapped and a list of $mapped_keys is provided, then only the individual keys are mapped (e.g.: Patient)
 * - in some cases the status could be marked as not applicable (e.g.: LOINC 8716-3)
 */
class MappingStatus implements JsonSerializable
{
    const STATUS_MAPPED = 'mapped';
    const STATUS_PARTIALLY_MAPPED = 'partially mapped'; // for Patient?
    const STATUS_NOT_MAPPED = 'not mapped';
    const STATUS_NOT_AVAILABLE = 'not available';

    private $status = self::STATUS_NOT_MAPPED;
    private $reason;
    private $mapped_fields = [];

    function setStatus($status, $reason=null) {
        $status_list = [
            self::STATUS_MAPPED,
            self::STATUS_NOT_MAPPED,
            self::STATUS_NOT_AVAILABLE,
        ];
        if(!in_array($status, $status_list)) return;
        if($reason) $this->reason = $reason;
        $this->status = $status;
    }

    function setMappedFields($fields) {
        $this->mapped_fields = array_values($fields); // make sure the index is erset to 0 using array_values
    }

    function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'reason' => $this->reason,
            'mapped_fields' => $this->mapped_fields,
        ];
    }
}