<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Middlewares;

use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\State;

/**
 * Interface for middleware classes.
 */
interface MiddlewareInterface {
    /**
     * Handles the middleware logic.
     *
     * @param State $state The state to process.
     * @return State Returns the processed state.
     */
    public function handle($state);
}