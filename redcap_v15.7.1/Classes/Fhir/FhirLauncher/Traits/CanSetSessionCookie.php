<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Traits;

use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\FhirCookieDTO;

/**
 * trait shared by both the StandAloneLaunchState and the EhrLaunchState
 */
trait CanSetSessionCookie
{

	/**
	 * make a cookie that indicates we are in EHR launch
	 *
	 * @return void
	 */
	public function setCookie($state, $launchType) {
		$cookie = FhirCookieDTO::make(FhirLauncher::COOKIE_NAME);
		$cookie->state = $state;
		$cookie->launchType = $launchType;
		$cookie->save();
	}

}