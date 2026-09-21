<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirSystem;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

class FhirSystemSettingsDTO extends DTO
{
    const NO_EHR_NAME_PLACEHOLDER = '-- no name --';
    
    /**
     * @var int
     */
    public $ehr_id;

    /**
     * @var int
     */
    public $order;

    /**
     * @var string
     */
    public $ehr_name;

    /**
     * @var string
     */
    public $client_id;

    /**
     * @var string
     */
    public $client_secret;

    /**
     * @var string
     */
    public $fhir_base_url;

    /**
     * @var string
     */
    public $fhir_token_url;

    /**
     * @var string
     */
    public $fhir_authorize_url;

    /**
     * @var string
     */
    public $fhir_identity_provider;

    /**
     * @var string
     */
    public $patient_identifier_string;

    /**
     * @var string
     */
    public $fhir_custom_auth_params;

    public function visitProperty($key, $value) {
        // make sure to convert the fhir_custom_auth_params to a JSON object
        if($key==='fhir_custom_auth_params') {
            $value = json_decode($value ?? '[]', true);
            if(!is_array($value)) $value = [];
        }
        return $value;
    }
}