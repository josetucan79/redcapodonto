<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager;

use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\ExpirationSelector;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\PatientSelector;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules\PriorityRulesSelector;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules\RulesManager;

class FhirTokenManagerFactory {
    /**
     *
     * @param FhirSystem $fhirSystem
     * @param integer|null $user_id
     * @param integer|null $project_id
     * @return FhirTokenManager
     */
    public static function create($fhirSystem, $user_id = null, $project_id = null) {
        $rulesManager = RulesManager::instance();
        $priorityRulesSelector = new PriorityRulesSelector($rulesManager);

        $expirationSelector = new ExpirationSelector();
        $patientSelector = new PatientSelector();
        // add selectors in reversed order of importance
        $selector = $expirationSelector
            ->setNext($patientSelector)
            ->setNext($priorityRulesSelector);

        $tokenManager = new FhirTokenManager($fhirSystem, $selector, $user_id, $project_id);
        return $tokenManager;
    }
}