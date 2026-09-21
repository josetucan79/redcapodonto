<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\BaseDTO;
use Vanderbilt\REDCap\Classes\Rewards\Utility\StringConverter;

class Brand extends BaseDTO {
    
    public $brandKey;
    
    public $brandName;
    
    public $disclaimer;
    
    public $description;
    
    public $shortDescription;
    
    public $terms;
    
    /**
     *
     * @var DateTime
     */
    public $createdDate;

    public function createdDate($value) { return StringConverter::toDatetime($value); }
    
    /**
     *
     * @var DateTime
     */
    public $lastUpdateDate;
    
    public function lastUpdateDate($value) { return StringConverter::toDatetime($value); }
    
    /**
     *
     * @var BrandRequirement
     */
    public $brandRequirements;
    public function brandRequirements($value) { return new BrandRequirement($value); }
    
    /**
     *
     * @var array
     */
    public $imageUrls;
    
    public $status;
    

    /**
     *
     * @var Item[]
     */
    public $items;
    public function items($value) { return Item::collection($value); }

}