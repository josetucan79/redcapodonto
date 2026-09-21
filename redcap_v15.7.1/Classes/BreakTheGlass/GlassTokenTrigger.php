<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass;

use SplObserver;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirClientResponse;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroup;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Bundle;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\OperationOutcome;

/**
 * Observer that checks for 403 errors during data fetching
 * and triggers a Demographics endpoint call to generate BTG token if needed
 */
class GlassTokenTrigger implements SplObserver
{
    /**
     * @var array
     */
    protected $detected403Errors = [];

    /**
     * @var bool
     */
    protected $demographicsRequested = false;

    /**
     * Constructor
     *
     * @param int $project_id
     * @param int $user_id
     * @param GlassBreaker|null $glassBreaker
     */
    public function __construct(
        protected $project_id,
        protected $user_id,
        protected GlassBreaker $glassBreaker
    ) {}

    /**
     * Observer update method
     *
     * @param FhirClient $subject
     * @param string|null $event
     * @param mixed $data
     * @return void
     */
    public function update($subject, $event = null, $data = null): void
    {
        if (!($subject instanceof FhirClient)) return;
        
        // Track if Demographics endpoint was requested
        if ($event === FhirClient::NOTIFICATION_ENTRIES_RECEIVED) {
            if (isset($data['category']) && $data['category'] === FhirCategory::DEMOGRAPHICS) {
                $this->demographicsRequested = true;
            }
        }
        
        // Track 403 errors
        if ($event === FhirClient::NOTIFICATION_RESOURCE_ERROR) {
            if ($data instanceof FhirClientResponse && $data->getStatus() == 403) {
                $this->detected403Errors[] = $data;
            }
        }
        
        // At the end of the data fetch process, check if we need to trigger a BTG token request
        if ($event === FhirClient::NOTIFICATION_ENTRIES_RECEIVED) {
            $this->checkAndTriggerBtgToken($subject);
        }
    }

    /**
     * Check if we need to trigger a BTG token request and do so if needed
     *
     * @param FhirClient $fhirClient
     * @return void
     */
    private function checkAndTriggerBtgToken(FhirClient $fhirClient)
    {
        // Only proceed if:
        // 1. Break the glass is available for this project
        // 2. We have 403 errors
        // 3. Demographics endpoint was not already requested
        if (!GlassBreaker::isAvailable($this->project_id) ||
            empty($this->detected403Errors) ||
            $this->demographicsRequested) {
            return;
        }

        // Get the MRN from the FhirClient
        $mrn = $fhirClient->getMrn();
        if (empty($mrn)) {
            return;
        }

        // Check if a BTG token already exists for this MRN
        $protectedPatient = $this->glassBreaker->getStoredPatient($mrn);
        if ($protectedPatient && !empty($protectedPatient->fhirBtgToken)) {
            return; // Token already exists
        }

        // Get patient ID (should already be cached from previous calls)
        $patientId = $fhirClient->getPatientID($mrn);
        if (!$patientId) {
            return;
        }
        
        // Make a request to the Demographics endpoint to trigger BTG token
        $params = ['_id' => $patientId];
        $endpoint = $fhirClient->getEndpointFactory()->makeEndpoint(FhirCategory::DEMOGRAPHICS);
        $request = $endpoint->getSearchRequest($params);
        $response = $fhirClient->sendRequest($request);

        $bundle = $response->getResource();
        if(!$bundle instanceof Bundle) return;
        // process also all entries from bundle
        $generator = $bundle->makeEntriesGenerator();
        foreach ($generator as $resource) {
            if(!$resource instanceof OperationOutcome) continue;

            $fhirBtgToken = $resource->getFhirBgtToken();
            if(!$fhirBtgToken) continue;
            $this->glassBreaker->storeProtectedPatient($mrn, $fhirBtgToken);
        }
    }
}