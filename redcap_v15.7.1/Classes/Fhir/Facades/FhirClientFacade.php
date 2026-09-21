<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Facades;

use Vanderbilt\REDCap\Classes\BreakTheGlass\GlassBreaker;
use Vanderbilt\REDCap\Classes\BreakTheGlass\GlassTokenTrigger;
use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\Logs\FhirLogger;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManagerFactory;

/**
 * provides a facade to the FhirClient
 */
class FhirClientFacade {

    /**
     * Get an instance of the FhirClient based on the provided project ID and user ID.
     * Also, attach the FhirTokenManager and the FhirLogger.
     *
     * @param int|FhirSystem|null $ehrIdentifierOrInstance The EHR ID as an integer or an instance of FhirSystem.
     * @param int|null $project_id The project ID.
     * @param int|null $user_id The user ID.
     * @return FhirClient An instance of the FhirClient.
     */
    public static function getInstance($ehrIdentifierOrInstance = null, $project_id = null, $user_id = null) {
        // Your method implementation
        $fhirSystem = $ehrIdentifierOrInstance instanceof FhirSystem ? $ehrIdentifierOrInstance : new FhirSystem($ehrIdentifierOrInstance);
        $tokenManager = FhirTokenManagerFactory::create($fhirSystem, $user_id, $project_id);
        $fhirLogger = new FhirLogger();

        $fhirClient = new FhirClient($project_id, $tokenManager);
        
        $fhirClient->attach($fhirLogger, FhirClient::NOTIFICATION_RESOURCE_RECEIVED);
        $fhirClient->attach($tokenManager, FhirClient::NOTIFICATION_PATIENT_IDENTIFIED);
		$fhirClient->attach($tokenManager, FhirClient::NOTIFICATION_RESOURCE_ERROR);

        if(GlassBreaker::isAvailable($project_id)) {
            $glassBreaker = new GlassBreaker($project_id, $user_id);
            $fhirClient->attach($glassBreaker, FhirClient::NOTIFICATION_ERROR);
            $fhirClient->attach($glassBreaker, FhirClient::NOTIFICATION_ENTRY_RECEIVED);

            // Add our new GlassTokenTrigger observer, passing the GlassBreaker instance
            $glassTrigger = new GlassTokenTrigger($project_id, $user_id, $glassBreaker);
            $fhirClient->attach($glassTrigger, FhirClient::NOTIFICATION_ENTRIES_RECEIVED);
            $fhirClient->attach($glassTrigger, FhirClient::NOTIFICATION_RESOURCE_ERROR);

        }

        return $fhirClient;
    }

    /* public static function __callStatic($name, $arguments)
    {
        if(!method_exists(__CLASS__, $name)) return;
        $method = new ReflectionMethod(__CLASS__, $name);
        $instance = null; // instance can be null for static methods
        if(!$method->isStatic()) {
            $instance = new static();
        }
        return $method->invokeArgs($instance, $arguments);
    } */
}