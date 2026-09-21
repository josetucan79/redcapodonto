<?php

if(version_compare(phpversion(), '8.1', '>=')){
    /**
     * We replace these classes with empty versions to avoid PHP 8.2 related warnings.
     */
    require_once __DIR__ . '/replaced-classes/SerializableClosure.php';
    require_once __DIR__ . '/replaced-classes/ListResource.php';
    require_once __DIR__ . '/replaced-classes/Node.php';
}

/**
 * Required to avoid the following error on Mark's local:
 * Error message: 16384 - The PSR-0 `Requests_...` class names in the Requests library are deprecated. Switch to the PSR-4 `WpOrg\Requests\...` class names at your earliest convenience., File: /home/mark/sites/redcap/redcap_v15.0.9/Libraries/vendor/rmccue/requests/src/Autoload.php, Line: 168
 */
define('REQUESTS_SILENCE_PSR0_DEPRECATIONS', true);

require_once __DIR__ . '/../redcap_connect.php';