<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

/**
 * Open ID object available in the Access Token response when open_id is in the scopes
 */
final class OpenIdDTO extends DTO {
    
    /**
     * Issuer of the JWT. This is set to the token endpoint that should be used by the client.
     *
     * @var string
     */
    public $iss;

    /**
     * STU3+ FHIR ID for the resource representing the user launching the app.
     *
     * @var string
     */
    public $sub;

    /**
     * Audiences that the ID token is intended for. This will be set to the client ID for the application that was just authorized during the SMART on FHIR launch.
     *
     * @var string
     */
    public $aud;

    /**
     * Time integer for when the JWT was created, expressed in seconds since the "Epoch" (1970-01-01T00:00:00Z UTC).	
     *
     * @var string
     */
    public $iat;

    /**
     * Expiration time integer for this authentication JWT, expressed in seconds since the "Epoch" (1970-01-01T00:00:00Z UTC).
     *
     * @var string
     */
    public $exp;

    /**
     * Absolute reference to the FHIR resource representing the user launching the app. See the HL7 documentation for more details.
     *
     * @var string
     */
    public $fhirUser;

    /**
     * The nonce for this client session.
     *
     * @var string
     */
    public $nonce;

    /**
     * The user's LDAP/AD down-level logon name.	
     *
     * @var string
     */
    public $preferred_username;

}