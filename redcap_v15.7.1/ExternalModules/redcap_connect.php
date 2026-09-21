<?php

if(defined('APP_PATH_WEBROOT')){
    // REDCap has already been initialized.  No need to require redcap_connect.php again.
    // Also, we don't want to define PLUGIN anywhere that it shouldn't be defined.
    return;
}

if(!defined('PLUGIN')){
    // Since a change to redcap_connect.php on 4/6/18, this is required to make sure REDCap is initialized for command line calls like cron jobs.
    define('PLUGIN', true);
}

$connectPath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "redcap_connect.php";
if (!file_exists($connectPath)) {
    // We must be using the "external_modules" folder to override the version of the framework bundled with REDCap.
    $connectPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "redcap_connect.php";
}

// REDCap removes the CSRF token, so we must save it here for later checking.
$_POST['redcap_external_module_csrf_token'] = $_POST['redcap_csrf_token'] ?? null;

require_once $connectPath;
