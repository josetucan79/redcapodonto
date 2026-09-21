<?php
namespace Vanderbilt\REDCap\Classes\Fhir\MappingHelper;

use ReflectionClass;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Encounter;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Bundle;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Patient;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Observation;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\MedicationRequest;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\MedicationOrder;
use Vanderbilt\REDCap\Classes\Fhir\Resources\ResourceVisitorInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\AllergyIntolerance as AllergyIntolerance_R4;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\AllergyIntolerance as AllergyIntolerance_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2\Condition;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Condition as R4Condition;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Device;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\DocumentReference;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Immunization;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\OperationOutcome;
use Vanderbilt\REDCap\Classes\Utility\HtmlSanitizer;

/**
 * FHIR resource visitor
 * 
 * adjust the data based on the type of resource visited
 */
class ResourceVisitor implements ResourceVisitorInterface
{

    /**
     * setting to convert timestamps
     * to local timezone (where applicable)
     *
     * @var boolean
     */
    private $convertToLocalTime = false;

    /**
     *
     * @param FhirMappingHelper $fhirMappingHelper
     */
    private $fhirMappingHelper;

    /**
     * list of mapped fields in the current project
     *
     * @var array
     */
    private $mapped_fields = [];
    private $metadata_source;

    /**
     *  the FHIR category that will be visited
     * @var string
     */
    private $fhir_category;

    /**
     *
     * @param FhirMappingHelper $fhirMappingHelper
     * @param string $fhir_category
     */
    public function __construct($fhirMappingHelper, $fhir_category)
    {
        $this->fhirMappingHelper = $fhirMappingHelper;
        $this->fhir_category = $fhir_category;
        $this->mapped_fields = $this->fhirMappingHelper->getProjectMappingData();
        $this->metadata_source = $this->fhirMappingHelper->getMetadataSource();
        $systemConfigs = REDCapConfigDTO::fromDB();
        $this->convertToLocalTime = boolval($systemConfigs->fhir_convert_timestamp_from_gmt ?? 0);
    }

    /**
     * store modified data
     *
     * @var array
     */
    private $data = [];

    public function getData()
    {
      return $this->data;
    }
  
    public function addData($data)
    {
      $this->data[] = $data;
    }

    /**
     * this LOINC codes are not available for a specific reason
     */
    const BLOCKLIST_CODES = [
        '8716-3' => 'too generic', // refers to a generic 'vital signs' and is not mappable in REDCap
    ];

    /**
     * make a list of fields that are mapped in the payload
     * 
     * @param AbstractResource $resource
     * @return MappingStatus
     */
    function getMappingStatus($resource) {
        $mappingStatus = new MappingStatus();
        $data = $resource->getData();

        $class = get_class($resource);
        switch ($class) {
            case Observation::class:
                $field = 'code'; // check for code
                $system = $data[$field]['coding'][0]['system'] ?? null;
                if((preg_match("/loinc/", $system) !== 1)) {
                    $mappingStatus->setStatus(MappingStatus::STATUS_NOT_AVAILABLE, 'only LOINC codes are mappable');
                    break; // only LOINC codes are mapped
                }
                $code = $data[$field]['coding'][0]['code'] ?? null;
                if(!$code) break;
                if(array_key_exists($code, self::BLOCKLIST_CODES)) {
                    $reason = self::BLOCKLIST_CODES[$code] ?? '';
                    $mappingStatus->setStatus(MappingStatus::STATUS_NOT_AVAILABLE, $reason);
                }
                else if(in_array($code, $this->mapped_fields)) $mappingStatus->setStatus(MappingStatus::STATUS_MAPPED);
                else if(!array_key_exists($code, $this->metadata_source)) {
                    $mappingStatus->setStatus(MappingStatus::STATUS_NOT_AVAILABLE);
                }
                break;
            case Patient::class:
                // patient is different from other endpoint since it allows to map part of the payload
                $mapped_keys = array_intersect(array_keys($data), $this->mapped_fields);
                if(!empty($mapped_keys)) {
                    $mappingStatus->setStatus(MappingStatus::STATUS_MAPPED);
                    $mappingStatus->setMappedFields($mapped_keys);
                }
                break;
            case Immunization::class:
                $field = 'immunizations-list';
                if(!in_array($field, $this->mapped_fields)) break;
                $mappingStatus->setStatus(MappingStatus::STATUS_MAPPED);
                break;
            case Encounter::class:
                $field = 'encounters-list';
                if(!in_array($field, $this->mapped_fields)) break;
                $mappingStatus->setStatus(MappingStatus::STATUS_MAPPED);
                break;
            case AllergyIntolerance_R4::class:
            case AllergyIntolerance_DSTU2::class:
                $field = 'allergy-list';
                if(!in_array($field, $this->mapped_fields)) break;
                $mappingStatus->setStatus(MappingStatus::STATUS_MAPPED);
                break;
            case MedicationOrder::class:
            case MedicationRequest::class:
                $status = $data['status'] ?? null;
                $possibleField = 'medications-list'; // is also the suffix

                if (in_array($possibleField, $this->mapped_fields)) {
                    // Return any medication list
                    $mappingStatus->setStatus(MappingStatus::STATUS_MAPPED);
                } else  if ($status && in_array("$status-$possibleField", $this->mapped_fields)) {
                    $mappingStatus->setStatus(MappingStatus::STATUS_MAPPED);
                }
                break;
            case Condition::class:
            case R4Condition::class:
                $this->setConditionMappingStatus($mappingStatus, $resource);
                break;
            case DocumentReference::class:
                $this->setDocumentReferenceMappingStatus($mappingStatus, $resource);
                break;
            case Device::class:
                $this->setDeviceMappingStatus($mappingStatus, $resource);
                break;
            default:
                # code...
                break;
        }
        return $mappingStatus;
    }

    /**
     * set the mapping status for the current document reference
     * compare the loinc codes with the ones in the mapped fields
     * '18842-5' => 'Discharge Documentation',
     * '11488-4' => 'Consultation',
     * '34117-2' => 'History & Physical',
     * '11506-3' => 'Progress Note',
     * '28570-0' => 'Procedure Note',
     * '34111-5' => 'Emergency Department Note',
     * '34746-8' => 'Nurse Note',
     * '74213-0' => 'Discharge Instructions',
     * '75492-9' => 'Risk assessment and screening note',
     * '51847-2' => 'Evaluation + Plan note',
     * @param MappingStatus $mappingStatus
     * @param DocumentReference $resource
     * @return void
     */
    private function setDocumentReferenceMappingStatus($mappingStatus, $resource) {
        $loincCodes = $resource->getLoincCodes();
        if(!$loincCodes) return;
        foreach ($loincCodes as $loincCode) {
            $key = "clinical-note-{$loincCode}";
            if(in_array($key, $this->mapped_fields)) {
                $mappingStatus->setStatus(MappingStatus::STATUS_MAPPED);
                return;
            }
        }
    }

    private function setDeviceMappingStatus($mappingStatus, $resource) {
        $field = 'device-list';
        if(in_array($field, $this->mapped_fields)) {
            $mappingStatus->setStatus(MappingStatus::STATUS_MAPPED);
        }
    }

    /**
     * set the mapping status for the current condition
     * 
     * @param MappingStatus $mappingStatus
     * @param AbstractResource $resource
     * @return void
     */
    private function setConditionMappingStatus($mappingStatus, $resource) {
        $status = $resource->getData()['clinical_status'] ?? null;

        switch ($this->fhir_category) {
            case FhirCategory::CONDITION_PROBLEMS:
                $possibleField = 'problem-list';
                break;
            case FhirCategory::CONDITION_DENTAL_FINDING:
                $possibleField = 'problem-dental-finding-list';
                break;
            case FhirCategory::CONDITION_GENOMICS:
                $possibleField = 'problem-genomics-list';
                break;
            case FhirCategory::CONDITION_INFECTION:
                $possibleField = 'problem-infection-list';
                break;
            case FhirCategory::CONDITION_MEDICAL_HISTORY:
                $possibleField = 'problem-medical-history-list';
                break;
            case FhirCategory::CONDITION_REASON_FOR_VISIT:
                $possibleField = 'problem-reason-for-visit-list';
                break;
            case FhirCategory::DIAGNOSIS:
                $possibleField = 'encounter-diagnosis-list';
                break;
            default:
                $possibleField = null;
                break;
        }
        if(!$possibleField) return;
        if (in_array($possibleField, $this->mapped_fields)) {
            // Return ANY condition list
            $mappingStatus->setStatus(MappingStatus::STATUS_MAPPED);
        } else  if ($status && in_array("$status-$possibleField", $this->mapped_fields)) {
            $mappingStatus->setStatus(MappingStatus::STATUS_MAPPED);
        }
    }

    /**
     * create a structure containing the data of a resource and some metadata (type)
     *
     * @param AbstractResource $resource
     * @param ?array $data override data
     * @return void
     */
    public function makeResult($resource, $data = null) {
        $getClassName = function($object) {
            $reflect = new ReflectionClass($object);
            return $reflect->getShortName();
        };
        $className = $getClassName($resource);

        $data ??= $resource->getData();
        $mappingStatus = $this->getMappingStatus($resource);
        $entry = [
            'type' => $className,
            'data' => $data,
            'mapping_status' => $mappingStatus,
        ];
        return $entry;
    }

    /**
     * manipulate the resource
     * return an array in each resource so that Bundle
     * can perform an array_merge. This is needed for resources
     * like "Observation" where we need to create a different entry for
     * each LOINC CODE
     * 
     * @param AbstractResource $resource
     * @return object
     */
    public function visit($resource)
    {
        $results = [];
        /**
         * NOTE: To use switch with get_class
         * I need to process the resources in specific
         * methods to avoid warnings from the IDE.
         * As an alternative I can add a comment before
         * using one of its methods.
         * E.g. :
         * // @var Bundle $resource
         * $entries = $resource->getEntries();
         * 
         * I can also use if statements with instanceof
         */
        $class = get_class($resource);
        switch ($class) {
            case Bundle::class:
                $results = $this->visitBundle($resource);
                break;
            case OperationOutcome::class:
                // OperationOutcome is skipped
                break;
            case DocumentReference::class:
                $results = $this->visitDocumentReference($resource);
                break;
            case Observation::class:
                $results = $this->visitObservation($resource);
                break;
            case Encounter::class:
                $results = $this->visitEncounter($resource);
                break;
            case MedicationOrder::class:
                $results = $this->visitMedicationOrder($resource);
                break;
            case MedicationRequest::class:
                $results = $this->visitMedicationRequest($resource);
                break;
            case Patient::class:
            case AllergyIntolerance_R4::class:
            case AllergyIntolerance_DSTU2::class:
            default:
                $data = $this->makeResult($resource);
                $results = [$data];
                break;
        }
        return $results;
    }


    /**
     * get data for Bundles
     *
     * @param Bundle $resource
     * @return array
     */
    private function visitBundle($resource)
    {
        $results = [];
        $generator = $resource->makeEntriesGenerator();
        while($entry = $generator->current()) {
            $generator->next();
            $result = $this->visit($entry);
            $results = array_merge($results, $result);
        }
        return $results;
    }

    /**
     *
     * @param DocumentReference $resource
     * @return array
     */
    private function visitDocumentReference($resource)
    {
        $sanitizer = new HtmlSanitizer([
            'mode' => HtmlSanitizer::MODE_PERMISSIVE,
            'disallowedTags' => ['script', 'iframe'],
            'disallowedAttributes' => ['onclick'],
        ]);

        $data = $resource->getData();
        $html = $resource->getHTML();
        $cleanHtml = $sanitizer->sanitize($html);

        $data['html'] = $cleanHtml;
        $result = $this->makeResult($resource, $data);
        return [$result];
    }

    /**
     *
     * @param Observation $resource
     * @return array
     */
    private function visitObservation($resource)
    {
        $results = [];
        $observations = $resource->split();
        foreach ($observations as $observation) {
            $results[] = $this->makeResult($observation);
        }
        return $results;
    }

    /**
     *
     * @param MedicationRequest $resource
     * @return array
     */
    private function visitMedicationRequest($resource)
    {
        $results = [];
        $medications = $resource->split();
        foreach ($medications as $medication) {
            $results[] = $this->makeResult($medication);
        }
        return $results;
    }

    /**
     *
     * @param MedicationOrder $resource
     * @return array
     */
    private function visitMedicationOrder($resource)
    {
        $results = [];
        $medications = $resource->split();
        foreach ($medications as $medication) {
            $results[] = $this->makeResult($medication);
        }
        return $results;
    }
    
    /**
     *
     * @param Encounter $resource
     * @return array
     */
    private function visitEncounter($resource)
    {
        $data = $resource->getData();
        $data['normalized_period-start'] = $resource->getTimestampStart($this->convertToLocalTime);
        $data['normalized_period-end'] = $resource->getTimestampEnd($this->convertToLocalTime);
        $result = $this->makeResult($resource, $data);
        return [$result];
    }
}