<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

class ResultDTO extends DTO {

    const STATUS_SKIPPED = 'skipped';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_NOT_ACCEPTED = 'not accepted';

    /**
     *
     * @var String
     */
    public $mrn;

    /**
     *
     * @var String
     */
    public $status;

    /**
     *
     * @var String
     */
    public $details;

    public function setDetails($value) {
        $details = $value;
        if(empty($details)) $details = '';
        if(is_array($details)) {
            $details = json_encode($details, JSON_PRETTY_PRINT);
        }
        if(!is_string($details)) $details = strval($details);
        $this->details = $details;
    }

}