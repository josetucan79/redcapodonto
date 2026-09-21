<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use Exception;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Scopes\Scopes;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLaunchContexts;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Scopes\AuthorizationScopes;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Traits\CanSetSessionCookie;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\AuthorizationRequestDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Traits\CanGetAuthorizationToken;

class EhrLaunchState extends State
{
	use CanGetAuthorizationToken;
	use CanSetSessionCookie;

	public function run() {
		$session = $this->context->getSession();
		$session->launchType = FhirLauncher::LAUNCHTYPE_EHR; // save the launch type
		$state = $session->state;
		if($fhirUser = $_GET['user'] ?? '') {
			// capture ehr user if available in the URL (Epic)
			// it is important to capture the user this way only in launch from EHR context!
			$session->fhirUser = trim(rawurldecode(urldecode($fhirUser)));
		}

		$scopes = new AuthorizationScopes();
		$scopes->setLevel(Scopes::PATIENT_LEVEL); // set provider level for resource scopes
		
		$launch = $_GET['launch'] ?? '';
		$iss = $_GET['iss'] ?? '';
		$ehr_id = $_GET[FhirLauncher::FLAG_EHR_ID] ?? '';
		$fhirSystem = new FhirSystem($ehr_id);
		$session->ehrID = $fhirSystem->getEhrId(); // store the EHR id
		$aud = $this->getIdentityProvider($fhirSystem->getFhirBaseUrl(), $fhirSystem->getFhirIdentityProvider(), $iss);

		$authorizationDTO = AuthorizationRequestDTO::fromArray([
			'response_type' => AuthorizationRequestDTO::RESPONSE_TYPE_CODE,
			'client_id' => $fhirSystem->getClientId() ?? '',
			'redirect_uri' => $this->context->getRedirectUrl(),
			'scope' => strval($scopes),
			'launch' => $launch, // only set during launch from EHR 
			'aud' => $aud,
			'state' => $state,
			// 'legacy_login_page' => 0, // optional, Epic-only parameter that forces the authentication to use the legacy OAuth2
		]);
		$URL = $fhirSystem->getFhirAuthorizeUrl();
		// setting params for authorization URL
		$params = $authorizationDTO->getData();
		$this->applyConfigOverrides(FhirLaunchContexts::EHR_LAUNCH, $params);

		$this->setCookie($state, FhirLauncher::LAUNCHTYPE_EHR);
		
		// request authorization via OAuth2: redirecting to $URL
		$this->redirectToAuthorizeURL($URL, $params);
	}


}