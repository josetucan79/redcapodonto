<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use HttpClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirRenderer;

class SelectEhrState extends State
{

	public function run() {
		$this->context->forceBlankSession();
		// Selecting the EHR system
		$fhirSystem = $this->getFhirSystemByParameters();
		if($fhirSystem instanceof FhirSystem) {
			$this->redirectToNextState($fhirSystem->getEhrId());
		}

		$renderer = FhirRenderer::engine();
		$ehrSystems = FhirSystem::getEhrSystems();
		$currentURL = $this->getCurrentURL();
		// $ehrSystems = [reset($ehrSystems)]; // force only one system for testing purposes
		$html = $renderer->render('select-ehr.html.twig', [
			'currentURL' => $currentURL,
			'ehrSystems' => $ehrSystems,
		]);
		// $html .= $debug = $renderer->run('partials.debug', ['session' => $this->context->getSession()]);
		print($html);
	}

	function stripPathFromUrl($url) {
		// Parse the URL and return an associative array containing its various components
		$parsedUrl = parse_url($url);
		
		// Build the URL without the path
		$strippedUrl = ($parsedUrl['scheme'] ?? '') . '://';
	
		// Check if the URL has user info
		if (isset($parsedUrl['user'])) {
			$strippedUrl .= $parsedUrl['user'];
	
			// Add password if it exists
			if (isset($parsedUrl['pass'])) {
				$strippedUrl .= ':' . $parsedUrl['pass'];
			}
	
			$strippedUrl .= '@';
		}
		
		// Add the host
		$strippedUrl .= $parsedUrl['host'] ?? '';
		
		// Add the port if it's present
		if (isset($parsedUrl['port'])) {
			$strippedUrl .= ':' . $parsedUrl['port'];
		}
		
		return $strippedUrl;
	}

	/**
	 * Retrieves the appropriate FHIR system instance based on request parameters.
	 *
	 * This function checks the query parameters for 'ehr_id' and 'iss'. If 'ehr_id' is present, 
	 * it creates and returns a new FHIR system instance using the 'ehr_id'. If not, it attempts 
	 * to create a FHIR system instance based on the 'iss' parameter, which is expected to be a URL.
	 *
	 * @return FhirSystem Returns an instance of FhirSystem based on the provided parameters.
	 */
	function getFhirSystemByParameters() {
		// Attempt to retrieve 'ehr_id' and 'iss' from the query parameters
		$iss = $_GET['iss'] ?? '';
		$ehr_id = $_GET[FhirLauncher::FLAG_EHR_ID] ?? '';

		// If 'ehr_id' is provided, return a new FHIR system instance using 'ehr_id'
		if ($ehr_id) {
			return new FhirSystem($ehr_id);
		}
		$issURL = $this->stripPathFromUrl($iss);
		// Otherwise, attempt to create a FHIR system instance from 'iss'
		return FhirSystem::fromIss($issURL);
	}

	function appendQueryParams($url, $params) {
		// Check if the URL already has query parameters
		$queryStart = strpos($url, '?') === false ? '?' : '&';
		$queryString = http_build_query($params);
		$newUrl = $url . $queryStart . $queryString;
		return $newUrl;
	}

	public function getCurrentURL() {
		$URL = $this->context->getRedirectUrl();
		$params = $_GET;
		$queryParams = http_build_query($params);
		return "$URL?$queryParams";
	}

	public function redirectToNextState($ehr_id) {
		$url = $this->getCurrentURL();
		$url = $this->appendQueryParams($url, [FhirLauncher::FLAG_EHR_ID => $ehr_id]);
		// EHR system detected, redirecting to next state
		HttpClient::redirect($url, true, 302);
	}

}