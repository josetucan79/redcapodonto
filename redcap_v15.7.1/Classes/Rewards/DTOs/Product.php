<?php
namespace Vanderbilt\REDCap\Classes\Rewards\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\BaseDTO;

class Product extends BaseDTO {
   public $product_id;
   public $name;
   public $image;
   public $disclaimer;
   public $description;
   public $imageUrls = [];
   public $imageLarge;
   public $value;
   public $minValue;
   public $maxValue;
   public $currencyCode;
   public $terms;
   public $redemptionInstructions;
}