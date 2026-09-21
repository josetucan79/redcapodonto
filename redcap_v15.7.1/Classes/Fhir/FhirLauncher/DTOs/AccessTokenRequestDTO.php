<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

/**
 * Parameters sent using HTTP POST to the token endpoint
 */
final class AccessTokenRequestDTO extends DTO {
    
    /**
     * This should contain the value authorization_code.
     *
     * @var string
     */
    public $grant_type;

    /**
     * This parameter contains the authorization code sent from Epic's authorization server to your application as a querystring parameter on the redirect URI as described above.
     *
     * @var string
     */
    public $code;

    /**
     * This parameter must contain the same redirect URI that you provided in the initial access request.
     * The value of this parameter needs to be URL encoded.
     *
     * @var string
     */
    public $redirect_uri;

    /**
     * This parameter must contain the application's client ID issued by Epic that you provided in the initial request.
     * 
     * NOTE: The client_id parameter is not passed in the the POST body if you use client secret authentication,
     * which is different from the access token request for apps that do not use refresh tokens.
     * You will instead pass an Authorization HTTP header with client_id and client_secret
     * URL encoded and passed as a username and password.
     * Conceptually the Authorization HTTP header will have this value: base64(client_id:client_secret).
     * @var string
     */
    public $client_id;

    /**
     * This optional parameter is used to verify against your code_challenge parameter when using PKCE.
     * This parameter is passed as free text and must match the code_challenge parameter used in your
     * authorization request once it is hashed on the server using the code_challenge_method.
     * This parameter is available starting in the August 2019 version of Epic.
     *
     * @var string
     */
    public $code_verifier;
}