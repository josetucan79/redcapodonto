<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication;

use JsonSerializable;

final class AdjudicationResponse implements JsonSerializable
{
    /**
     *
     * @var array
     */
    public $excluded = 0;

    /**
     *
     * @var array
     */
    public $adjudicated = 0;

    /**
     *
     * @var array
     */
    public $record = [];

    /**
     *
     * @var array
     */
    public $errors = [];


    public function __construct($excluded, $adjudicated, $record=[], $errors=[])
    {
        $this->excluded = intval($excluded);
        $this->adjudicated = intval($adjudicated);
        $this->record = $record;
        $this->errors = $errors;
    }

    public function hasErrors() { return  !empty($this->errors); }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'excluded' => $this->excluded,
            'adjudicated' => $this->adjudicated,
            'record' => $this->record,
            'errors' => $this->errors,
            'hasErrors' => $$this->hasErrors()
        ];
    }
}
    