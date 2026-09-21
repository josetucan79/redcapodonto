<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\BaseDTO;
use Vanderbilt\REDCap\Classes\Rewards\Utility\StringConverter;


class Customer  extends BaseDTO {

    
    public $customerIdentifier; // string
    
    public $displayName; // string
    
    public $status; // active
    
    public $createdAt; // 2022-03-04T08:59:26.635Z
    
    /**
     *
     * @var Account[]
     */
    public $accounts; // []

    /**
     *
     * @param array $value
     * @return Brand[]
     */
    public function accounts($value) { return Account::collection($value); }
    
    public function createdAt($value) { return StringConverter::toDatetime($value); }
    
    public function hasAccount($accountOrAccountID) {
        $accountID = null;
        if(is_string($accountOrAccountID)) $accountID = $accountOrAccountID;
        else if($accountOrAccountID instanceof Account) $accountID = $accountOrAccountID->accountIdentifier;
        if(!$accountID) return false;
        foreach ($this->accounts as $account) {
            if($accountID == $account->accountIdentifier) return true;
        }
        return false;
    }

}