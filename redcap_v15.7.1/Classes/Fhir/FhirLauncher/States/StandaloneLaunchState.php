<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Scopes\Scopes;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Scopes\AuthorizationScopes;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\AuthorizationRequestDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLaunchContexts;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Traits\CanGetAuthorizationToken;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Traits\CanSetSessionCookie;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;

class StandaloneLaunchState extends State
{
	use CanGetAuthorizationToken;
	use CanSetSessionCookie;

	public function run() {
		$session = $this->context->getSession();
		// Starting standalone launch
		$session->launchType = FhirLauncher::LAUNCHTYPE_STANDALONE; // save the launch type
		$state = $session->state;
		// set the FHIR system
		$ehr_id = $_GET[FhirLauncher::FLAG_EHR_ID] ?? '';
		$fhirSystem = new FhirSystem($ehr_id);
		$session->ehrID = $ehr_id; // store the EHR id

		$scopes = new AuthorizationScopes();
		$scopes->setLevel(Scopes::USER_LEVEL); // set provider level for resource scopes
		$scopes->setFilter('^launch'); // launch scope must not be provided in a standalone launch

		$aud = $this->getIdentityProvider($fhirSystem->getFhirBaseUrl());

		$authorizationDTO = AuthorizationRequestDTO::fromArray([
			'response_type' => AuthorizationRequestDTO::RESPONSE_TYPE_CODE,
			'client_id' => $fhirSystem->getClientId() ?? '',
			'redirect_uri' => $this->context->getRedirectUrl(),
			'scope' => strval($scopes),
			// 'launch' => '', // only set during launch from EHR
			'aud' => $aud,
			'state' => $state,
			'legacy_login_page' => 0, // optional, Epic-only parameter that forces the authentication to use the legacy OAuth2
		]);
		$URL = $fhirSystem->getFhirAuthorizeUrl();
		// - setting params for authorization URL
		$params = $authorizationDTO->getData();
		$this->applyConfigOverrides(FhirLaunchContexts::STANDALONE_LAUNCH, $params);

		$this->setCookie($state, FhirLauncher::LAUNCHTYPE_STANDALONE);
		
		// - request authorization via OAuth2: redirecting to $URL
		$this->redirectToAuthorizeURL($URL, $params);
	}

}