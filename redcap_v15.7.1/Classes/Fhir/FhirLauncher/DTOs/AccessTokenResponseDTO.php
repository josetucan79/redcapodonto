<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;
use Vanderbilt\REDCap\Classes\Traits\CanDecodeJWT;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\OpenIdDTO;

/**
 * Response received from the token endpoint
 */
final class AccessTokenResponseDTO extends DTO {
    use CanDecodeJWT;
    
    /**
     * This parameter contains the access token issued by the FHIR server to your application and is used in future requests.
     *
     * @var string
     */
    public $access_token;
    
    /**
     * This parameter contains the refresh token issued by
     * the FHIR server to your application and can be used to obtain a new access token.
     * 
     * @var string
     */
    public $refresh_token;
    
    /**
     * usually bearer.
     * 
     * @var string
     */
    public $token_type;
    
    /**
     *  This parameter contains the number of seconds for which the access token is valid.
     * 
     * @var string
     */
    public $expires_in;
    
    /**
     *  This parameter describes the access your application is authorized for.
     * 
     * @var string
     */
    public $scope;
    
    /**
     * Returned only for applications that have requested an openid scope.
     * See below for more info on OpenID Connect id_tokens.
     * This parameter follows the guidelines of the OpenID Connect (OIDC) Core 1.0 specification.
     * It is signed but not encrypted.
     * 
     * @var string
     */
    public $id_token;

    /**
     * decoded JWT token associated to the id_token
     *
     * @var OpenIdDTO
     */
    private $decodedToken;
    
    /**
     *  This parameter identifies provides the FHIR ID for the patient,
     * if a patient is in context at time of launch.
     * 
     * @var string
     */
    public $patient;
    
    /**
     *  This parameter identifies the DSTU2 FHIR ID for the patient,
     * if a patient is in context at time of launch.
     * 
     * NOTE: The actual name in the payload is epic.dstu2.patient
     * 
     * @var string
     */
    public $epic_dstu2_patient;
    
    /**
     *  This parameter identifies the FHIR ID for the patient’s encounter,
     * if in context at time of launch.
     * The encounter token corresponds to the FHIR Encounter resource.
     * 
     * @var string
     */
    public $encounter;
    
    /**
     *  This parameter identifies the FHIR ID for the encounter department,
     * if in context at time of launch.
     * The location token corresponds to the FHIR Location resource.
     * 
     * @var string
     */
    public $location;
    
    /**
     *  This parameter identifies the FHIR ID for the patient’s appointment,
     * if appointment context is available at time of launch.
     * The appointment token corresponds to the FHIR Appointment resource.
     * 
     * @var string
     */
    public $appointment;
    
    /**
     *  This parameter identifies the FHIR ID of the user's login department for provider-facing EHR launches.
     * The loginDepartment token corresponds to the FHIR Location resource.
     * 
     * @var string
     */
    public $loginDepartment;
    
    /**
     *  This parameter will have the same value as the earlier state parameter. 
     * 
     * @var string
     */
    public $state;

    /**
     * FHIR username provided in a Cerner EHR launch context
     *
     * @var string
     */
    public $username;

    /**
     * decode and return the OPEN ID JWT token
     *
     * @return OpenIdDTO|null
     */
    public function getDecodedIdToken() {
        if(!isset($this->decodedToken)) {
            $decodedToken = $this->decodeJWT($this->id_token);
		    $this->decodedToken = OpenIdDTO::fromArray($decodedToken);
        }
        return $this->decodedToken;
    }

}