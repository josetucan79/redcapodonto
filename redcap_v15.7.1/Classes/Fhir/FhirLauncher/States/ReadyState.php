<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirRenderer;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;

class ReadyState extends State
{

	public function run() {
		$standaloneLaunchURL = FhirLauncher::getStandaloneLaunchURL();
		// FHIR launcher is ready
		$renderer = FhirRenderer::engine();
		$ehrSystems = FhirSystem::getEhrSystems();
		// $ehrSystems = [reset($ehrSystems)]; // force only one system for testing purposes
		$html = $renderer->render('standalone-launch.html.twig', compact('standaloneLaunchURL', 'ehrSystems'));
		// $html .= $debug = $renderer->run('partials.debug', ['session' => $this->context->getSession()]);
		print($html);
	}

}