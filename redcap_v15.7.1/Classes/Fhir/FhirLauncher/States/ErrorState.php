<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use Exception;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\FhirCookieDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\SessionDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirRenderer;

class ErrorState extends State
{

	/**
	 *
	 * @param FhirLauncher $context
	 */
	public function __construct($context)
	{
		$this->context = $context;
	}

	public function run() {
		$this->removeCookie();
		
		// if($error = $_GET['error'] ?? false) $this->context->addError(new Exception($error, 1));
		// if($error_uri = $_GET['error_uri'] ?? false) $this->context->addError(new Exception($error_uri, 1));
		$errors = $this->context->getErrors();
		
		// init template variables
		$logs = [];
		$logViews = [];
		$launchDiagram = null;
		$launchType = null;
		$launchTypeStandalone = FhirLauncher::LAUNCHTYPE_STANDALONE;
		$launchTypeEhr = FhirLauncher::LAUNCHTYPE_EHR;

		$session = $this->context->getSession();
		if($session) {
			$logs = $session->getLogs();
			$launchType = $session->launchType;
			$templatesDir = dirname(__DIR__).'/templates';
			$diagramFileExtension = 'jpg'; // svg or png
			$logViews = $this->getLogViews($session);
			if($launchType===FhirLauncher::LAUNCHTYPE_STANDALONE) $launchDiagram = $templatesDir.'/assets/FHIR launch diagrams-Standalone launch.'.$diagramFileExtension;
			else if ($launchType===FhirLauncher::LAUNCHTYPE_EHR) $launchDiagram = $templatesDir.'/assets/FHIR launch diagrams-EHR launch.'.$diagramFileExtension;

			// destroy the session when an error is encountered (to avoid unwanted autologin, for example)
			$this->context->destroySession();
		}

		$renderer = FhirRenderer::engine();
		$html = $renderer->render('error.html.twig', [
			'errors' => $errors,
			'logs' => $logs,
			'logViews' => $logViews,
			'launchDiagram' => $launchDiagram,
			'launchType' => $launchType,
			'launchTypeStandalone' => $launchTypeStandalone,
			'launchTypeEhr' => $launchTypeEhr,
		]);
		print($html);

	}

	public function getLogViews(SessionDTO $session) {
		$sql = "SELECT * FROM redcap_log_view WHERE `page` LIKE '%ehr.php'AND `full_url` LIKE ? ORDER BY `log_view_id` DESC LIMIT 50";
		$params = ["%state=$session->state%"];
		$result = db_query($sql, $params);
		$logViews = [];
		while($row = db_fetch_assoc($result)) {
			$row['full_url'] = $this->obfuscateStateInUrl($row['full_url'], 3, 4);
			$logViews[] = $row;
		}
		return $logViews;
	}

	/**
     * Obfuscate state parameter in URL with configurable visible characters
     * 
     * @param string $url The URL to obfuscate
     * @param int $startChars Number of characters to show at the beginning
     * @param int $endChars Number of characters to show at the end
     * @param string $mask The masking characters to use (default: '****')
     * @return string The obfuscated URL
     */
    private function obfuscateStateInUrl($url, $startChars = 2, $endChars = 2, $mask = '****') {
        return preg_replace_callback('/state=([^&]+)/', function($matches) use ($startChars, $endChars, $mask) {
            $stateValue = $matches[1];
            $length = strlen($stateValue);
            
            // If state is too short to obfuscate meaningfully, just mask it completely
            $minLength = $startChars + $endChars + 1; // +1 to ensure there's something to hide
            if ($length <= $minLength) {
                return 'state=' . $mask;
            }
            
            // Extract start and end portions
            $start = substr($stateValue, 0, $startChars);
            $end = substr($stateValue, -$endChars);
            
            return 'state=' . $start . $mask . $end;
        }, $url);
    }

	/**
	 * remove the FHIR cookie whenever an error is displayed
	 *
	 * @return void
	 */
	function removeCookie() {
		FhirCookieDTO::destroy(FhirLauncher::COOKIE_NAME);
	}

}