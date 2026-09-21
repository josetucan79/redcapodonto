<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Traits;

use \HttpClient;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\AuthorizationRequestDTO;


/**
 * trait shared by both the StandAloneLaunchState and the EhrLaunchState
 */
trait CanGetAuthorizationToken
{


	/**
	 * apply custom authorization parameters
	 * defined in the CDIS settings page
	 *
	 * @param string $currentContext
	 * @param array $params
	 * @return void
	 */
	function applyConfigOverrides($currentContext, &$params=[]) {
		$redcapConfig = REDCapConfigDTO::fromDB();
		$fhir_custom_auth_params = $redcapConfig->fhir_custom_auth_params;
		$decoded = json_decode($fhir_custom_auth_params, true);

		foreach ($decoded as $key => $data) {
			$value = $data['value'] ?? false;
			$context = $data['context'] ?? false;
			if($value===false || $context===false) continue;
			if($context!=='' && $context!==$currentContext) continue;
			$params[$key] = $value;
		}
	}

	/**
	 * Redirect to the autorize endpoint and get an authorization code.
	 * The autorization code will be exchanged for an access token
	 * 
	 * @param string $URL
	 * @param array $params
	 * @return void
	 */
	public function redirectToAuthorizeURL($URL, $params)
	{
		$query = http_build_query($params);
		$authorizationTokenURL = "$URL?$query";
		HttpClient::redirect($authorizationTokenURL, true, 302); // go back to the page where the 2FA process was started
	}


	/**
	 * get identity provider based on priority
	 *
	 * @param string $fhirBaseUrl
	 * @param string $fhir_identity_provider
	 * @param string $iss
	 * @return void
	 */
	public function getIdentityProvider($fhirBaseUrl, $fhir_identity_provider=null, $iss=null) {
		if($fhir_identity_provider) return $fhir_identity_provider;
		if($iss) return $iss;
		if($fhirBaseUrl) return $fhirBaseUrl;
	}

}