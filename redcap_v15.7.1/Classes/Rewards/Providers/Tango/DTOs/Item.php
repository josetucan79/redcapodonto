<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\BaseDTO;
use Vanderbilt\REDCap\Classes\Rewards\Utility\StringConverter;


class Item extends BaseDTO {
    
    public $utid;
    
    public $rewardName;
    
    public $currencyCode; // "USD"
    
    public $status;
    
    public $valueType;
    
    public $rewardType;
    
    public $isWholeAmountValueRequired;
    
    public function isWholeAmountValueRequired($value) { return StringConverter::toBoolean($value); }
    
    public $minValue;
    
    public function minValue($value) { return StringConverter::toNumber($value); }
    
    public $maxValue;
    
    public function maxValue($value) { return StringConverter::toNumber($value); }

    public $faceValue;
    
    public function faceValue($value) { return StringConverter::toNumber($value); }
    
    public $createdDate;
    
    public function createdDate($value) { return StringConverter::toDatetime($value); }
    
    public $lastUpdateDate;
    
    public function lastUpdateDate($value) { return StringConverter::toDatetime($value); }
    
    /** @var array */
    public $countries;
    
    /** @var array */
    public $credentialTypes;
    
    public $redemptionInstructions;
    public function redemptionInstructions($value) {return $value; }
}