<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\BaseDTO;
use Vanderbilt\REDCap\Classes\Rewards\Utility\StringConverter;


class Account  extends BaseDTO {

    
    public $accountIdentifier; // string
    
    public $accountNumber; // string
    
    public $displayName; // string
    
    public $currencyCode; // USD
    
    public $currentBalance; // 850.9
    
    public function currentBalance($value) { return StringConverter::toCurrency($value); }
    
    public $createdAt; // 2016-07-19T18:19:30.855Z
    
    public function createdAt($value) { return StringConverter::toDatetime($value); }
    
    public $status; // ACTIVE
    
    public $contactEmail; // email@email.com

}