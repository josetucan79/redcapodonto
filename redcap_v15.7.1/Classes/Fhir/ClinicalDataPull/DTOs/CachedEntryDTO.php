<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\DTOs;

use DateTime;
use DynamicDataPull;
use Vanderbilt\REDCap\Classes\DTOs\DTO;
use Vanderbilt\REDCap\Classes\Utility\TypeConverter;

class CachedEntryDTO extends DTO {
    use RecordLinkTrait;
    
    const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    /** @var int */
    public $md_id;

    /** @var int */
    public $map_id;

    /** @var boolean */
    public $is_record_identifier;

    /** @var int */
    public $project_id;

    /** @var int */
    public $event_id;

    /** @var int|string */
    public $record;

    /** @var string */
    public $field_name;

    /** @var string */
    public $external_source_field_name;

    /** @var string */
    public $temporal_field;

    /** @var string */
    public $preselect;

    /** @var string */
    public $form_menu_description;

    /** @var int */
    public $field_order;

    /** @var string */
    public $form_name;

    /** @var string */
    public $element_label;

    /** @var string */
    public $element_type;

    /** @var string */
    public $element_enum;

    /** @var string */
    public $element_note;

    /** @var string */
    public $element_validation_checktype;

    /** @var string */
    public $element_validation_type;

    /** @var int */
    public $element_validation_min;

    /** @var int */
    public $element_validation_max;

    /** @var DateTime */
    public $source_timestamp;

    /** @var string */
    public $source_value;

    /** @var string */
    public $source_value2;

    /** @var string */
    public $adjudicated;

    /** @var string */
    public $exclude;

    /** @var string */
    private $value;

    public function getValue() {
        if(!isset($this->value)) {
            $use_mcrypt = $this->source_value2=='';
            $encrypted_data = $use_mcrypt ? $this->source_value : $this->source_value2;
            $this->value = decrypt($encrypted_data, DynamicDataPull::DDP_ENCRYPTION_KEY, $use_mcrypt);
          }
          return $this->value;
    }

    public function getSourceTimeStamp() {
        if(!$this->source_timestamp instanceof DateTime) return '';
        return $this->source_timestamp->format(self::TIMESTAMP_FORMAT);
    }

    public function setSourceTimeStamp($value) {
        $this->source_timestamp = TypeConverter::toDateTime($value, self::TIMESTAMP_FORMAT);
    }
}