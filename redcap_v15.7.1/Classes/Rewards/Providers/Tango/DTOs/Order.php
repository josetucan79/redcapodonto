<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\BaseDTO;

class Order extends BaseDTO {
   public $order_id;
   public $product_id;
   public $externalRefID;
   public $value;
   public $currency;
   public $status;
   public $createdAt;
   public $redemptionLink;
   public $_payload; // the complete, original payload as returned by the provider
}