<?php

function isTest()
{
	return (
	    (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'redcap.test')
	    || (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'localhost')
    );
}
if(isTest()) $GLOBALS['cookie_samesite'] = 'None'; // set to 'none' to allow authentication in iFrames in dev environments



use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;

define("EHR", true);
// NOAUTH will be overridden, if needed, by the FhirLauncher
define("NOAUTH", true);

// Disable authentication unless receiving REDCap login form submission
// if (!isset($_POST['redcap_login_a38us_09i85'])) define("NOAUTH", true);

require_once dirname(__FILE__) . "/Config/init_global.php";

// Config for project-level or non-project pages
/* if (isset($_GET['pid'])) {
	require_once dirname(__FILE__) . "/Config/init_project.php";
} else {
	require_once dirname(__FILE__) . "/Config/init_global.php";
} */

$fhirLauncher = new FhirLauncher();
$fhirLauncher->handleStates();