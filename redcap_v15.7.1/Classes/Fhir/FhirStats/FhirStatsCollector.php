<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirStats;

use Project;
use DynamicDataPull;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;
use Vanderbilt\REDCap\Classes\Utility\TransactionHelper;

class FhirStatsCollector
{
    /**
     *
     * @var integer
     */
    private $project_id;
    /**
     *
     * @var Project
     */
    private $project;
    /**
     * adjudication per project types
     */
    const REDCAP_TOOL_TYPE_CDM = 'CDM'; // Clinical Data Mart
    const REDCAP_TOOL_TYPE_CDP = 'CDP'; // Clinical Data Pull
    const REDCAP_TOOL_TYPE_CDP_INSTANT = 'CDP-I'; // Clinical Data Pull performed with "instant adjudication"

    const COUNTS_TABLE_NAME = 'redcap_ehr_resource_imports';
    const COUNTS_DETAILS_TABLE_NAME = 'redcap_ehr_resource_import_details';

    /**
     * map the fields category in FHIR external fields to a FHIR endpoint
     */
    private function getCategoryToResourceMapping() {
        
        // by default provide mapping for R4
        $mapping = [
            FhirCategory::ALLERGY_INTOLERANCE => EndpointIdentifier::ALLERGY_INTOLERANCE,
            FhirCategory::ADVERSE_EVENT => EndpointIdentifier::ADVERSE_EVENT,
            FhirCategory::DEMOGRAPHICS => EndpointIdentifier::PATIENT,
            FhirCategory::CONDITION => EndpointIdentifier::CONDITION,
            FhirCategory::CONDITION_PROBLEMS => EndpointIdentifier::CONDITION, // (Problems)
            FhirCategory::CONDITION_DENTAL_FINDING => EndpointIdentifier::CONDITION, // (Dental Finding)
            FhirCategory::CONDITION_GENOMICS => EndpointIdentifier::CONDITION, // (Genomics)
            FhirCategory::CONDITION_INFECTION => EndpointIdentifier::CONDITION, // (Infection)
            FhirCategory::CONDITION_MEDICAL_HISTORY => EndpointIdentifier::CONDITION, // (Medical History)
            FhirCategory::CONDITION_REASON_FOR_VISIT => EndpointIdentifier::CONDITION, // (Reason for Visit)
            FhirCategory::COVERAGE => EndpointIdentifier::COVERAGE,
            FhirCategory::DEVICE_IMPLANTS => EndpointIdentifier::DEVICE,
            FhirCategory::APPOINTMENT_APPOINTMENTS => EndpointIdentifier::APPOINTMENT,
            FhirCategory::APPOINTMENT_SCHEDULED_SURGERIES => EndpointIdentifier::APPOINTMENT, // (Scheduled Surgeries)
            FhirCategory::LABORATORY => EndpointIdentifier::OBSERVATION_LABS,
            FhirCategory::VITAL_SIGNS => EndpointIdentifier::OBSERVATION_VITALS,
            FhirCategory::CORE_CHARACTERISTICS => EndpointIdentifier::OBSERVATION_CORE_CHARACTERSITICS,
            FhirCategory::SMART_DATA => EndpointIdentifier::OBSERVATION_SMART_DATA,
            FhirCategory::SOCIAL_HISTORY => EndpointIdentifier::OBSERVATION_SOCIAL_HISTORY,
            FhirCategory::ENCOUNTER => EndpointIdentifier::ENCOUNTER,
            FhirCategory::IMMUNIZATION => EndpointIdentifier::IMMUNIZATION,
            FhirCategory::MEDICATIONS => EndpointIdentifier::MEDICATION_REQUEST,
            FhirCategory::RESEARCH_STUDY => EndpointIdentifier::RESEARCHSTUDY,
            FhirCategory::DIAGNOSIS => EndpointIdentifier::CONDITION, // (Diagnosis)
            FhirCategory::PROCEDURE => EndpointIdentifier::PROCEDURE,
            FhirCategory::PRACTITIONER => EndpointIdentifier::PRACTITIONER,
            FhirCategory::DOCUMENT_REFERENCE_CLINICAL_NOTES => EndpointIdentifier::DOCUMENT_REFERENCE_CLINICAL_NOTES,
        ];

        // Adjust the mapping based on the FHIR version
        switch ($this->fhirCode) {
            case FhirVersionManager::FHIR_DSTU2:
                $mapping[FhirCategory::MEDICATIONS] = EndpointIdentifier::MEDICATION_ORDER;
                break;
        }

        return $mapping;
    }

    /**
     * store all entries during CDIS operations
     * before storing them to the database
     * entries could have different types and could
     * be adjudicated or not
     *
     * @var array
     */
    private $entries = [];

    /**
     * stats could be for CDM or CDP
     *
     * @var string
     */
    private $type;

    /**
     * FHIR system for the project
     *
     * @var FhirSystem
     */
    private $fhirSystem;
    
    /**
     *
     * @var string
     */
    private $fhirCode;

    /**
     * create a stats collector
     *
     * @param integer $project_id
     * @param string $type type of the project (CDM or CDP)
     */
    public function __construct($project_id, $type)
    {
        // check if the provided type is valid
        $valid_types = [self::REDCAP_TOOL_TYPE_CDM, self::REDCAP_TOOL_TYPE_CDP, self::REDCAP_TOOL_TYPE_CDP_INSTANT];
        if(!in_array($type, $valid_types)) throw new \Exception("Invalid type", 1);
        $this->type = $type;
        $this->project_id = $project_id;
        $this->project = new \Project($project_id);
        // get info about the fhir system
        $this->fhirSystem = FhirSystem::fromProjectId($project_id);
        $this->fhirCode = FhirVersionManager::getInstance($this->fhirSystem)->getFhirCode();
    }

    /**
     * react to notifications (from the FHIR client)
     *
     * @param SplSubject $subject
     * @param string $event
     * @param mixed $data
     * @return void
     */
    public function update($subject, $event = null, $data = null)
    {
        switch ($event) {
            case DynamicDataPull::NOTIFICATION_DATA_COLLECTED_FOR_SAVING:
                // increment the FHIR statistic counter for the adjudicated data
                $fhirCategory = DynamicDataPull::getMappedFhirResourceFromFieldName($data['project_id'], $data['redcap_field'], $data['redcap_event']);
                if($fhirCategory) $this->addEntry($data['record_id'], $fhirCategory, $data['increment']);
                break;
            case DynamicDataPull::NOTIFICATION_DATA_SAVED_FOR_ALL_EVENTS:
                $this->logEntries(); // persist FHIR statistics
                break;
            case DynamicDataPull::NOTIFICATION_DATA_SAVED:
                // record has been saved in a specific event
                break;
            default:
                # code...
                break;
        }
    }

    public function getProjectId()
    {
        return $this->project_id;
    }

    /**
     * Logs resource counts for a specific record and EHR ID into the database.
     *
     * @param integer $ehr_id The unique identifier for the EHR system.
     * @param string $record_id The unique identifier for the record being logged.
     * @param array $stats An associative array of resource types and their respective counts.
     *                     Example:
     *                     [
     *                         'Patient' => 5,
     *                         'Observation' => 10,
     *                         'Condition' => 0,
     *                         // ...
     *                     ]
     *                     Counts with a value of 0 or less are ignored.
     * 
     * @return bool True if all operations succeed, false if any operation fails.
     */
    public function log($ehr_id, $record_id, $stats = [])
    {
        $category_to_resource_mapping = $this->getCategoryToResourceMapping();
        // Define main and details table names
        $main_table = self::COUNTS_TABLE_NAME; // `redcap_ehr_resource_imports`
        $details_table = self::COUNTS_DETAILS_TABLE_NAME;
        // Prepare the base timestamp and parameters for the main table
        $timestamp = date('Y-m-d H:i:s');
        $base_params = [
            $timestamp,
            $this->type,
            $this->project_id,
            $ehr_id,
            $record_id
        ];
        // Start the transaction
        TransactionHelper::beginTransaction();
        // Insert into the main counts table and get the inserted ID
        $main_query = "INSERT INTO $main_table (`ts`, `type`, `project_id`, `ehr_id`, `record`) 
                        VALUES (?, ?, ?, ?, ?)";
        $result = db_query($main_query, $base_params);
        if ($result === false) {
            // Rollback if the main insert fails
            TransactionHelper::rollbackTransaction();
            return false;
        }
        // Retrieve the ID of the newly inserted row in `redcap_ehr_resource_imports`
        $ehr_import_count_id = db_insert_id(); // Assumes db_insert_id() retrieves the last inserted ID
        // Prepare the query for the details table
        $details_query = "INSERT INTO $details_table (`ehr_import_count_id`, `category`, `resource`, `count`) 
                            VALUES (?, ?, ?, ?)";
        // Loop through each stat category and insert into the details table
        foreach ($stats as $category => $count) {
            $resource = $category_to_resource_mapping[$category] ?? null;
            if (!$resource || $count <= 0) continue; // Skip if count is zero or less
            $details_params = [
                $ehr_import_count_id,
                $category,      // REDCap category
                $resource,      // EHR resource
                intval($count)  // count
            ];
            // Execute the insert query for each resource type and count
            $result = db_query($details_query, $details_params);
            if ($result === false) {
                // Rollback transaction if any detail insert fails
                TransactionHelper::rollbackTransaction();
                return false;
            }
        }
        // Commit the transaction if all inserts succeed
        TransactionHelper::commitTransaction();
        return true;
    }

    /**
     * get the list of entries
     *
     * @return array
     */
    public function getEntries()
    {
        return $this->entries;
    }
    
    /**
     * Log all stored entries with EHR ID.
     *
     * @return void
     */
    public function logEntries()
    {
        $entries = $this->getEntries();
        foreach ($entries as $ehr_id => $records) {
            foreach ($records as $record_id => $stats) {
                $this->log($ehr_id, $record_id, $stats);
            }
        }
    }

    /**
     * Increment the counter of the FHIR resource for a specific record and EHR ID.
     *
     * @param integer $ehr_id
     * @param integer $record_id
     * @param string $fhir_resource_type
     * @param integer $count
     * @return void
     */
    public function addEntry($record_id, $fhir_resource_type, $count = 1)
    {
        $ehr_id = $this->fhirSystem->getEhrId();
        $fhirCategories = array_keys($this->getCategoryToResourceMapping());
        if (!in_array($fhir_resource_type, $fhirCategories)) {
            return;
        }
        
        // Initialize the array for the EHR ID if it doesn't exist
        if (!isset($this->entries[$ehr_id])) {
            $this->entries[$ehr_id] = [];
        }
        
        // Initialize the array for the record ID if it doesn't exist under the given EHR ID
        if (!isset($this->entries[$ehr_id][$record_id])) {
            $this->entries[$ehr_id][$record_id] = [];
        }
        
        // Increment the count for the specified resource type
        $this->entries[$ehr_id][$record_id][$fhir_resource_type] = 
            ($this->entries[$ehr_id][$record_id][$fhir_resource_type] ?? 0) + intval($count);
    }

}