<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\BaseDTO;

class Catalog extends BaseDTO {

    /**
     *
     * @var string
     */
    public $catalogName;
    
    /**
     * @var Brand[] $brands
     */
    public $brands;

    /**
     *
     * @param array $value
     * @return Brand[]
     */
    public function brands($value) { return Brand::collection($value); }

}