<?php
namespace Vanderbilt\REDCap\Classes\Fhir\MappingHelper;

use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\Condition;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointsHelper;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\AdverseEvent;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ConditionProblems;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointVisitorInterface;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\Patient as Patient_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\Patient as Patient_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\AllergyIntolerance as AllergyIntolerance_R4;

/**
 * visitor for the FHIR endpoints
 * 
 * adjusts parameters based on the visited endpoint:
 * provide mandatory parameters and adjust parameter's names
 */
class EndpointOptionsVisitor implements EndpointVisitorInterface
{

    /**
     *
     * @var string
     */
    private $patient_id;

    /**
     *
     * @var string
     */
    private $options;

    /**
     *
     * @var FhirClient
     */
    private $fhirClient;

    /**
     *
     * @param string $patient_id
     * @param array $options
     * @param FhirClient $fhirClient
     */
    public function __construct($patient_id, $options, $fhirClient)
    {
        $this->patient_id = $patient_id;
        $this->options = $options;
        $this->fhirClient = $fhirClient;
    }

    /**
     * Get options based on the endpoint.
     * 
     * translate the patient_id to the
     * correct parameter name for a specific endpoint
     * 
     * @param AbstractEndpoint $endpoint
     * @return array
     */
    public function visit($endpoint)
    {
        $applyDate = function($key, &$options) {
            if (isset($options['date'])) {
                $options[$key] = $options['date'];
                unset($options['date']);
            }
        };
        $options = $this->options;
        $class = get_class($endpoint);
        switch ($class) {
            case Patient_DSTU2::class:
            case Patient_R4::class:
                $options['_id'] = $this->patient_id;
                unset($options['date']);
                break;
            case Condition::class:
            case ConditionProblems::class:
                $options['patient'] = $this->patient_id;
                $applyDate('onset-date', $options);
                /* if (isset($options['date'])) {
                    $options['onset-date'] = $options['date'];
                    unset($options['date']);
                } */
                break;
            case AdverseEvent::class:
                $options = $this->visitAdverseEvents($endpoint);
                break;
            case AllergyIntolerance_R4::class:
                $options['patient'] = $this->patient_id;
                $options['clinical-status'] = AllergyIntolerance_R4::CLINICAL_STATUS_ACTIVE;
                break;
            default:
                $options['patient'] = $this->patient_id;
                break;
        }
        return $options;
    }

    /**
     *
     * use a provided study identifier to (the typical ID found in EHR systems)
     * to the study FHIR id
     * 
     * @param AdverseEvent $endpoint
     * @return void
     */
    public function visitAdverseEvents($endpoint)
    {
        $endpointsHelper = new EndpointsHelper();

        $studyID = $this->options['study_identifier'] ?? $endpointsHelper->getProjectIrbNumber();
        $options['study'] = $endpointsHelper->getFhirStudyID($this->fhirClient, $studyID);
        $options['subject'] = $this->patient_id;
        return $options;
    }
}