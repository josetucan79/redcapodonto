<?php namespace ExternalModules;

/**
 * jsmo-ajax.php
 * 
 * This file handles EM Framework ajax requests that are initiated from the JSMO.ajax() method.
 */

if (!(defined("EM_ENDPOINT") || defined("EM_SURVEY_ENDPOINT") || defined("EM_ENDPOINT_TEST"))) {
    echo "This file cannot be called directly.";
    return;
}

/**
 * This line is required to let REDCap know this is an ajax request,
 * and to avoid creating new CSRF tokens, which eventually cause
 * AJAX requests to fail after enough are made to cycle the
 * original CSRF token out of $_SESSION['redcap_csrf_token'].
 */
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';

// Get payload
$jsmo_ajax_payload_data = $_POST;

if (isset($_GET['NOAUTH'])) {
    define('NOAUTH', true);
}

// Initialize REDCap
/**
 * In case NOAUTH is not set and there is no valid user session (either because a bad request was 
 * sent or the login session has expired) the caller will get the REDCap login page HTML as 
 * response. They will have to deal with this in their Promise.catch implementation.
 */
require_once __DIR__ . '/../redcap_connect.php';

// We got here, which means either a valid session is still active or we are on a survey page.
// Handle the request:
$response = ExternalModules::handleAjaxRequest($jsmo_ajax_payload_data);

// Return result
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
