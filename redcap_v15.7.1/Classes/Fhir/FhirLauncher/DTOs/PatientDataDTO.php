<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;


/**
 * Parameters sent using HTTP POST to the token endpoint
 */
final class PatientDataDTO extends DTO {

    /**
     *
     * @var string
     */
    public $FirstName;
    
    /**
     *
     * @var string
     */
    public $LastName;
    
    /**
     *
     * @var string
     */
    public $BirthDate;
    
    /**
     *
     * @var string
     */
    public $ID;
    
    /**
     *
     * @var string
     */
    public $MRN;
    
    /**
     *
     * @var array
     */
    public $identifiers;
    
}