<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs;

use DateTime;
use Vanderbilt\REDCap\Classes\DTOs\DTO;
use Vanderbilt\REDCap\Classes\Utility\TypeConverter as TC;

/**
 * This class represents a single value retrieved from an external data source 
 * during clinical data adjudication.
 * 
 * Each `FieldSourceValueDTO` instance contains metadata and properties related to 
 * the external value, including the source value itself, its timestamp, 
 * and flags for validation, equality, and range status.
 * 
 * This class is designed to encapsulate all necessary information for a single 
 * adjudication value, enabling efficient processing and comparison within 
 * adjudication workflows.
 */
class FieldSourceValueDTO extends DTO
{
    public $fhir_key; // The actual value received from the external source.
    public $src_value; // The actual value received from the external source.
    public $display; // The value that should be displayed.
    public $src_timestamp; // The timestamp associated with the source value.
    public $md_id; // Metadata identifier for the source value, linking it to a specific mapping or record.
    public $is_out_of_range = false; // Indicates if the value is outside the acceptable range for the given field.
    public $is_equal = false; // Specifies whether the source value matches the REDCap value.
    public $is_invalid = false; // Flag indicating whether the source value is invalid.
    public $is_locked = false; // Flag indicating whether the destination form is locked.
    public $timestamp; // A general-purpose timestamp associated with the source value.
    // public $preselect = false;

    public function getFhirKey() { return $this->fhir_key; }
    public function getSrcValue(): mixed { return $this->src_value; }
    public function getDisplay(): mixed { return $this->display; }
    public function getSrcTimestamp(): ?DateTime { return $this->src_timestamp; }
    public function getMdId(): int { return $this->md_id; }
    public function getIsOutOfRange(): bool { return $this->is_out_of_range; }
    public function getIsEqual():bool { return $this->is_equal; }
    public function getIsInvalid(): bool { return $this->is_invalid; }
    public function getIsLocked(): bool { return $this->is_locked; }
    public function getTimestamp(): DateTime { return $this->timestamp; }
    // public function getPreselect():bool { return $this->preselect; }

    public function setFhirKey($value) { $this->fhir_key = $value; }
    public function setSrcValue($value) { $this->src_value = $value; }
    public function setDisplay($value) { $this->display = $value; }
    public function setSrcTimestamp($value) { $this->src_timestamp = TC::toDateTime($value); }
    public function setMdId($value) { $this->md_id = TC::toInteger($value); }
    public function setIsOutOfRange($value) { return $this->is_out_of_range = TC::toBoolean($value); }
    public function setIsEqual($value) { return $this->is_equal = TC::toBoolean($value); }
    public function setIsInvalid($value) { return $this->is_invalid = TC::toBoolean($value); }
    public function setIsLocked($value) { return $this->is_locked = TC::toBoolean($value); }
    public function setTimestamp($value) { return $this->timestamp = TC::toDateTime($value); }
    // public function setPreselect(bool $value) { return $this->preselect = TC::toBoolean($value); }

    /**
     * Compare this instance with another FieldSourceValueDTO
     * to check if they are equal.
     * Two instances are considered equal if they have the same
     * src_value, md_id, and src_timestamp.
     *
     * @param self $other
     * @return boolean
     */
    public function isEqualTo(self $other): bool
    {
        if ($this->getFhirKey() != $other->getFhirKey()) return false;
        if ($this->getSrcValue() != $other->getSrcValue()) return false;

        
        $thisTimestamp = $this->getSrcTimestamp();
        $otherTimestamp = $other->getSrcTimestamp();

        if ($thisTimestamp && $otherTimestamp) {
            return $thisTimestamp->getTimestamp() == $otherTimestamp->getTimestamp();
        }

        if (is_null($thisTimestamp) && is_null($otherTimestamp)) {
            return true;
        }

        return false;
    }

}
