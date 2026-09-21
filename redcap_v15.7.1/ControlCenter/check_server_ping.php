<?php

// Disable authentication IF "noauthkey" is passed in the query string with the correct value (MD5 hash of $salt and today's date+hour)
if (isset($_POST['noauthkey'])) {
	// Get $salt from database.php
	require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'database.php';
	// Validate $salt
	if (!isset($salt)) exit;
	// Validate "noauthkey"
	if ($_POST['noauthkey'] == md5($salt . date('YmdH'))) {
		// Disable authentication
		define("NOAUTH", true);
	} else {
		// Failed, so stop here
		exit;
	}
}

// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// List of pingable URLs and expected responses
//   = means exact match
//   ~ means contains
//   | means OR
//   (all case sensitive)
$pings = [
	"https://cde.nlm.nih.gov" => "~CDE",
	"https://www.google.com/recaptcha/api/siteverify" => "~missing-input-secret|~invalid-input-response",
	"https://redcap.link" => "~Missing Authentication Token",
	"https://api.twilio.com" => "~</TwilioResponse>",
	"https://api.sendgrid.com/v3" => "~\"message\":\"authorization required\"",
	CONSORTIUM_WEBSITE."ping.php" => "=1",
	$promis_api_base_url => "~CAT Instrument APIs",
	$bioportal_api_url => "~{\"links\":{\"annotator\":",
	APP_PATH_SURVEY_FULL => "~survey_code_form|~redcap-offline-message",
	Mosio::API_BASE.Mosio::API_PING_ENDPOINT => "~\"ping\":\"pong\"",
	// These two are not used any more and their failures are not reported.
	// Thus, these two should be removed here and in check.php
	// Commented out in order to not make unnecessary requests.
	// "https://is.gd" => "~is.gd",
	// "http://api.bit.ly/v3/shorten" => "~DEPRECATED_ENDPOINT",
];

// Get and validate the URL
$page_to_ping = $_POST['url'] ?? "INVALID";
if (!array_key_exists($page_to_ping, $pings)) {
	exit("This URL is not on the restricted list");
}
// Get response
if (isset($_POST['type']) && $_POST['type'] == 'post') {
	$response = http_post($page_to_ping);
}
else {
	$response = http_get($page_to_ping);
}
// Check response
$expected = array_filter(explode("|", $pings[$page_to_ping]), function($s) { return !empty($s); });
$pass = count($expected) ? false : !empty($response);
foreach ($expected as $exp) {
	$op = substr($exp, 0, 1);
	$exp = substr($exp, 1);
	if ($op == '=') {
		$pass = $pass || ($response == $exp);
	}
	else if ($op == '~') {
		$pass = $pass || (strpos($response, $exp) !== false);
	}
	if ($pass) break;
}
exit($pass ? "1" : "0");
