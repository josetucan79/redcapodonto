<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\BaseDTO;
use Vanderbilt\REDCap\Classes\Rewards\Utility\StringConverter;

class BrandRequirement extends BaseDTO {
    public $displayInstructions;
    
    public $termsAndConditionsInstructions;
    
    public $disclaimerInstructions;
    
    public $alwaysShowDisclaimer;
    
    public function alwaysShowDisclaimer($value) { return StringConverter::toBoolean($value); }
    
}