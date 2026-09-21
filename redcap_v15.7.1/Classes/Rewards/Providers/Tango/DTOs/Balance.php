<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\BaseDTO;

class Balance extends BaseDTO {
   public $accountID;
   public $accountName;
   public $currency;
   public $amount;
}