<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirRenderer;
use Vanderbilt\REDCap\Classes\Utility\TwigRenderer;

/**
 * - request an access token
 * - extract the fhirUser (if not already available)
 * - store the payload in the session
 * - if the user is not logged in and the fhirUser is mapped in the redcap database,
 * 	then perform autologin
 * 
 * if a patient IS NOT provided, then we are in 'standalone launch context':
 * the launcher will redirect to the previous page (if available)
 * 
 * if a patient IS provided, then we are in 'EHR launch' context:
 * the launcher will transition to the PortalState 
 */
class AuthSuccessState extends State
{

	public function run() {
		$session = $this->context->getSession();
		
		$renderer = FhirRenderer::engine();
		$launchPage = $session->launchPage;
		$html = $renderer->render('success.html.twig', ['launchPage' => $launchPage]);
		// $html .= $debug = $renderer->run('partials.debug', ['session' => $this->context->getSession()]);
		print($html);
	}

}