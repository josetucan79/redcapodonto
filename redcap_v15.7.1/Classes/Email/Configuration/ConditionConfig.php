<?php
namespace Vanderbilt\REDCap\Classes\Email\Configuration;

use JsonSerializable;

class ConditionConfig implements JsonSerializable {
    public $inputType;
    public $requiresValue;
    public $options;

    const INPUT_TYPE_STRING = 'string';
    const INPUT_TYPE_SELECT = 'select';
    const INPUT_TYPE_MULTI = 'multi';
    const INPUT_TYPE_DATE = 'date';
    const INPUT_TYPE_DATE_RANGE = 'date_range';
    const INPUT_TYPE_NULL = 'null';

    public function __construct($inputType, $requiresValue = true, $options = []) {
        $this->inputType = $inputType ?? self::INPUT_TYPE_STRING;
        $this->requiresValue = $requiresValue;
        $this->options = $options;
    }

    public function jsonSerialize(): mixed {
        return [
            'inputType' => $this->inputType,
            'requiresValue' => $this->requiresValue,
            'options' => $this->options,
        ];
    }
}
