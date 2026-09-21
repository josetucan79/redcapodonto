<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use Exception;
use HttpClient;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\AccessTokenRequestDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\AccessTokenResponseDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;

/**
 * - request an access token
 * - store the payload in the session
 */
class RequestAccessTokenState extends State
{

	public function run() {
		$code = $_GET['code'] ?? null;
		$state = $_GET['state'] ?? null;
		$session = $this->context->getSession();
		$fhirSystem = $this->context->getFhirSystem();

		if(!$code) throw new Exception("no auth code has been received", 400);
		// - auth code has been received
		
		// - redeem auth code for access token
		$session->accessToken = $accessTokenDTO = $this->getAccessToken($fhirSystem, $code);
		// - access token has been received
		// - redirect to EHR user mapping page
		$URL = $this->context->getRedirectUrl();
		$params = ['state' => $state, FhirLauncher::FLAG_EXTRACT_FHIR_USER=>1];
		$query = http_build_query($params);
		HttpClient::redirect("$URL?$query", true, 302);
	}

	/**
	 * get an access token from the FHIR token URL
	 *
	 * @param FhirSystem $fhirSystem
	 * @param string $code
	 * @return AccessTokenResponseDTO
	 */
	public function getAccessToken($fhirSystem, $code) {
		$clientID = $fhirSystem->getClientId();
		$clientSecret = $fhirSystem->getClientSecret();
		
		$URL = $fhirSystem->getFhirTokenUrl();
		$accessTokenRequestDTO = AccessTokenRequestDTO::fromArray([
			'grant_type' => 'authorization_code',
			'code' => $code,
			'redirect_uri' => $this->context->getRedirectUrl(),
			'client_id' => $clientID,
			// 'code_verifier' => '', // optional
		]);
		// generate the tokenBearer. NOTE: cerner does not have a secret, so no token bearer is generated
		$tokenBearer = $this->getBearer( $clientID, $clientSecret);
		// - token bearer was created using client ID and client secret
		// - sending authorization code to FHIR 'token' URL
		$accessTokenDTO = $this->sendAccessTokenRequest($URL, $accessTokenRequestDTO, $tokenBearer);
		return $accessTokenDTO;
	}

	/**
	 * Send the request to get an access token.
	 * 
	 * If the client_id is omitted and a Bearer token is supplied in the headers,
	 * then a refresh token will be returned as well
	 *
	 * @param string $URL
	 * @param AccessTokenResponseDTO $params
	 * @param string $bearer
	 * @return AccessTokenResponseDTO
	 */
	public function sendAccessTokenRequest($URL, $params, $tokenBearer=null) {
		
		$http_options = [
			'headers' => [
				'Accept' => 'application/json',
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'form_params' => $params->getData(),
		];

		if($tokenBearer) {
			// do not pass the client ID if the client secret is available
			unset($http_options['form_params']['client_id']);
			// get the token bearer and add it to the headers
			// add the tokenBearer to the headers
			$http_options['headers']['Authorization'] = "Basic {$tokenBearer}";
		}
		
		$response = HttpClient::request('POST', $URL, $http_options);
		$payload = json_decode($response->getBody(), true);
		$accessTokenResponse = AccessTokenResponseDTO::fromArray($payload);
		return $accessTokenResponse;
	}

	/**
	 * make a bearer token for the request
	 *
	 * @param string $clientID
	 * @param string $clientSecret
	 * @return string|false
	 */
	public function getBearer($clientID, $clientSecret)
	{
		if(!$clientID || !$clientSecret) return false;
		return base64_encode($clientID.':'.$clientSecret);
	}

}