<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;
use Vanderbilt\REDCap\Classes\Utility\TypeConverter as TC;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\ValueObjects\ProcessingWarning;

/**
 * This class represents a combination of data stored in REDCap 
 * and data received from an external source during clinical data adjudication.
 * 
 * The external source may return multiple items for the same mapping.
 * These are stored in the `src_values` array as `FieldSourceValueDTO` objects.
 * 
 */
class FieldDataDTO extends DTO
{
    // Define constants for metadata labels
    public const META_TOTAL_OUT_OF_RANGE = 'total_out_of_range';
    public const META_TOTAL_EQUAL        = 'total_equal';
    public const META_TOTAL_INVALID      = 'total_invalid';
    public const META_TOTAL_LOCKED       = 'total_locked';
    
    public $fhir_key = ''; // the key of the FHIR mapping.
    public $rc_value = null; // Stores the current value from REDCap.
    public $display = null; // represents the value that must be displayed
    public $isMultipleChoice = true;
    public $multipleChoices = [];
    public $src_values = []; // Holds all values received from the external source for the given mapping.
    public $rc_timestamp = null; // Represents the timestamp of the REDCap value, if applicable.
    public $temporal_field = null; // references the redcap field used as reference for temporal (time-dependent) mappings.
    public $preselect = null; // Pre-select strategy, if any, to use during the adjudication process.
    public $selection = null; // Stores the currently selected external value for adjudication.
    public $field_label = ''; // metadata related to the field
    public $field_name = ''; // metadata related to the field
    public $form_name = ''; // metadata related to the field
    public $form_label = ''; // metadata related to the field
    public $is_temporal = false; // Specifies whether the field has temporal data characteristics.
    public $fhir_metadata = []; // FHIR metadata values (field,label,description,temporal, etc...)
    public $metadata = []; // additional metadata based on the srcValues
    public $warnings = []; // ProcessingWarning objects related to this field

    public function getRcValue() { return $this->rc_value; }
    public function getDisplay() {
        $value = $this->getRcValue();
        if(trim($value) == '') return '';
        if(!$this->getIsMultipleChoice()) return $value;
        return ($this->getMultipleChoices()[$value] ?? '-') . " ($value)";
    }
    public function getIsMultipleChoice() { return $this->isMultipleChoice; }
    public function getMultipleChoices() { return $this->multipleChoices; }

    /**
     *
     * @return FieldSourceValueDTO[]
     */
    public function getFhirKey() { return $this->fhir_key; }
    public function getSrcValues() { return $this->src_values; }
    public function getRcTimestamp() { return $this->rc_timestamp; }
    public function getTemporalField() { return $this->temporal_field; }
    public function getPreselect() { return $this->preselect; }
    public function getSelection(): FieldSourceValueDTO { return $this->selection; }
    public function getFieldLabel() { return $this->field_label; }
    public function getFieldName() { return $this->field_name; }
    public function getFormName() { return $this->form_name; }
    public function getFormLabel() { return $this->form_label; }
    public function getIsTemporal() { return $this->is_temporal; }
    public function getFhirMetadata() { return $this->fhir_metadata; }
    public function getWarnings() { return $this->warnings; }

    public function setFhirKey($value) { $this->fhir_key = $value; }
    public function setRcValue($value) { $this->rc_value = $value; }
    public function setDisplay($value) { $this->display = $value; }
    public function setIsMultipleChoice($value) { $this->isMultipleChoice = TC::toBoolean($value); }
    public function setMultipleChoices(array $value) { $this->multipleChoices = $value; }
    public function setSrcValues($values) {
        $getDisplay = function($value) {
            if(!$this->getIsMultipleChoice()) return $value;
            return ($this->getMultipleChoices()[$value] ?? '-') . " ($value)";
        };
        $this->src_values = [];
        foreach ($values as $data) {
            $fieldSourceValueDTO = new FieldSourceValueDTO($data);
            $fieldSourceValueDTO->setFhirKey($this->getFhirKey());
            $value = $fieldSourceValueDTO->getSrcValue();
            $fieldSourceValueDTO->setDisplay($getDisplay($value));
            
            // Check for duplicates before adding
            $isDuplicate = false;
            foreach ($this->src_values as $existingValue) {
                if ($fieldSourceValueDTO->isEqualTo($existingValue)) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (!$isDuplicate) {
                $this->src_values[] = $fieldSourceValueDTO;
            }
        }
    }
    public function setRcTimestamp($value) { $this->rc_timestamp = TC::toDateTime($value); }
    public function setTemporalField($value) { $this->temporal_field = TC::toBoolean($value); }
    public function setPreselect($value) { $this->preselect = TC::toString($value); }
    public function setSelection(FieldSourceValueDTO $value) { $this->selection = $value; }
    public function setFieldLabel($value) { $this->field_label = TC::toString($value); }
    public function setFieldName($value) { $this->field_name = TC::toString($value); }
    public function setFormName($value) { $this->form_name = TC::toString($value); }
    public function setFormLabel($value) { $this->form_label = TC::toString($value); }
    public function setIsTemporal($value) { $this->is_temporal = TC::toBoolean($value); }
    public function setFhirMetadata($value) { $this->fhir_metadata = TC::toArray($value); }
    
    public function addWarning(ProcessingWarning $warning) {
        $this->warnings[] = $warning;
    }
    
    public function hasWarnings() {
        return !empty($this->warnings);
    }
    
    public function getWarningsByType($type) {
        return array_filter($this->warnings, function($warning) use ($type) {
            return $warning->getType() === $type;
        });
    }

    private function calculateMetadata() {
        $metadata = [
            self::META_TOTAL_OUT_OF_RANGE => 0,
            self::META_TOTAL_EQUAL => 0,
            self::META_TOTAL_INVALID => 0,
            self::META_TOTAL_LOCKED => 0,
        ];
        foreach ($this->getSrcValues() as $srcValue) {
            $metadata[self::META_TOTAL_OUT_OF_RANGE] += $srcValue->getIsOutOfRange() ? 1 : 0;
            $metadata[self::META_TOTAL_EQUAL] += $srcValue->getIsEqual() ? 1 : 0;
            $metadata[self::META_TOTAL_INVALID] += $srcValue->getIsInvalid() ? 1 : 0;
            $metadata[self::META_TOTAL_LOCKED] += $srcValue->getIsLocked() ? 1 : 0;
        }
        return $metadata;
    }

    public function getMetadata($refresh=false) {
        if($refresh || empty($this->metadata)) {
            $this->metadata = $this->calculateMetadata();
        }
        return $this->metadata;
    }

}
