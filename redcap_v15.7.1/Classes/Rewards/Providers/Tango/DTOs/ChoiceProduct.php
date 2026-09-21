<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\BaseDTO;

class ChoiceProduct extends BaseDTO {

   /**
    * @var string
    */
   public $utid;

   /**
    * @var string
    */
   public $rewardName;

   /**
    * @var string
    */
   public $currencyCode;

   /**
    * @var array
    */
   public $countries;
}