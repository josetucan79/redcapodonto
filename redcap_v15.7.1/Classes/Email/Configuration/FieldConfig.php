<?php
namespace Vanderbilt\REDCap\Classes\Email\Configuration;

use JsonSerializable;

class FieldConfig implements JsonSerializable {
    public $name;
    public $label;
    public $conditions;

    public function __construct($name = '', $label = '', $conditions = []) {
        $this->name = $name;
        $this->label = $label;
        $this->conditions = $conditions;
    }

    public function jsonSerialize(): mixed {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'conditions' => $this->conditions,
        ];
    }
}
