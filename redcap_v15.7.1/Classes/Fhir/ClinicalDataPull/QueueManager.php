<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull;

use Logging;
use DateTime;
use DateInterval;
use DynamicDataPull;
use Records;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\DTOs\NotQueuedRecordDTO;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\DTOs\QueuedRecordDTO;
use Vanderbilt\REDCap\Classes\Traits\PaginationTrait;
use Vanderbilt\REDCap\Classes\Utility\TypeConverter;

/**
 * Manages the manual queuing of records for data fetching in a specific project.
 * 
 * This class allows users to manually queue and unqueue records 
 * for data fetching from a remote EHR system, bypassing the automatic criteria used 
 * by the regular queuing system. This class provides methods to queue individual or 
 * multiple records, retrieve the currently queued records, and unqueue records as needed.
 * 
 */
class QueueManager {

    use PaginationTrait;

    protected $projectId;
    protected $config = [];

    public function __construct($projectId, $config=[]) {
        $this->projectId = $projectId;
        $this->config = array_merge([
            'default_page' => 1,
            'default_per_page' => 10
        ], $config);
    }

    public function queueRecord($recordId) {
        // Update the record's status to 'QUEUED' in the redcap_ddp_records table, or insert if it doesn't exist
        $sql = "INSERT INTO redcap_ddp_records (project_id, record, fetch_status) VALUES (?, ?, 'QUEUED')
                ON DUPLICATE KEY UPDATE fetch_status = 'QUEUED'";
        return db_query($sql, [$this->projectId, $recordId]);
    }

    public function queueAllRecords() {
        try {
            db_query("SET AUTOCOMMIT=0");
            db_query("BEGIN");
            // Fetch all record IDs for the specified project ID
            $sql = "SELECT record FROM redcap_ddp_records WHERE project_id = ?";
            $result = db_query($sql, [$this->projectId]);
            // Iterate through each record and update the fetch_status to 'QUEUED'
            while ($row = db_fetch_assoc($result)) {
                $recordId = $row['record'];
                $queued = $this->queueRecord($recordId);
            }
            db_query('COMMIT');
            // Set back to initial value
            //code...
        } catch (\Throwable $th) {
            //throw $th;
            db_query('ROLLBACK');
        } finally {
            db_query("SET AUTOCOMMIT=1");
        }
    }

    public function queueMultipleRecords($recordIds) {
        foreach ($recordIds as $recordId) {
            $this->queueRecord($recordId);
        }
    }

    public function getQueuedRecords($page = null, $perPage = null, &$metadata = null) {
        $page = $page ?: $this->config['default_page'];
        $perPage = $perPage ?: $this->config['default_per_page'];
        $params = [$this->projectId];
        // Apply pagination
        $limitSql = $this->applyPagination($page, $perPage, $params);
    
        // SQL query with LIMIT and OFFSET for pagination
        $baseQuery = "SELECT record 
                FROM redcap_ddp_records 
                WHERE project_id = ? AND fetch_status = 'QUEUED'
                ORDER BY " . Records::getCustomOrderClause();
        $sql = $baseQuery.$limitSql;
        $q = db_query($sql, $params);
        
        $queuedRecords = [];
        while ($row = db_fetch_assoc($q)) {
            $queuedRecords[] = new QueuedRecordDTO($row);
        }
    
        // Populate metadata
        $this->populateMetadata($baseQuery, [$this->projectId], $page, $perPage, $metadata);
    
        return $queuedRecords;
    }

    public function unqueueRecord($recordId) {
        // Update the record's status to NULL in the redcap_ddp_records table if it exists
        $sql = "UPDATE redcap_ddp_records SET fetch_status = NULL WHERE project_id = ? AND record = ?";
        db_query($sql, [$this->projectId, $recordId]);
    }

    public function unqueueMultipleRecords($recordIds) {
        foreach ($recordIds as $recordId) {
            $this->unqueueRecord($recordId);
        }
    }

    /**
     * Checks if the project meets the necessary prerequisites for processing.
     *
     * @return bool True if the project meets the prerequisites; otherwise, false.
     */
    private function checkProjectPrerequisites(): bool {
        $projectCheckQuery = "SELECT realtime_webservice_enabled, status, date_deleted, completed_time
                              FROM redcap_projects
                              WHERE project_id = ?";
        $project = db_fetch_assoc(db_query($projectCheckQuery, [$this->projectId]));
        return $project['realtime_webservice_enabled']
            && $project['status'] <= 1
            && is_null($project['date_deleted'])
            && is_null($project['completed_time']);
    }

    /**
     * Identifies records that do not meet the criteria for being queued for data fetching.
     *
     * This method retrieves records from the specified project that do not qualify for data fetching based on the following criteria:
     * - Records that have future dates, determined by checking the `future_date_count` field.
     * - The `updated_at` field in `redcap_ddp_records` is within the range specified in x days ago, indicating recent data collection from the EHR system.
     * - The record has not been recently updated, as determined by the event log.
     *
     * Note: The project must have real-time webservice enabled, be active, not deleted, and not completed. These are considered prerequisites for evaluating the records.
     *
     * @return array An array of records, each with a reason explaining why it was not queued.
     */
    public function getNonQueuableRecords($page = null, $perPage = null, &$metadata = null) {
        $page = $page ?: $this->config['default_page'];
        $perPage = $perPage ?: $this->config['default_per_page'];
        $nonQueuableRecords = [];

        // Check if the project meets the prerequisites
        if (!$this->checkProjectPrerequisites()) {
            // The project does not meet the prerequisites, hence return empty
            return $nonQueuableRecords;
        }

        // Get the configuration
        $config = REDCapConfigDTO::fromDB();
        
        // Calculate the timestamp for "recently updated" threshold
        $xDaysAgo = (new DateTime())->sub(new DateInterval("P{$config->realtime_webservice_stop_fetch_inactivity_days}D"));
        $xDaysAgoFhir = (new DateTime())->sub(new DateInterval("P{$config->fhir_stop_fetch_inactivity_days}D"));
        $realTimeType = db_result(db_query("SELECT realtime_webservice_type FROM redcap_projects WHERE project_id = ?", [$this->projectId]), 0);
        $thresholdDate = ($realTimeType == DynamicDataPull::WEBSERVICE_TYPE_FHIR) ? $xDaysAgoFhir : $xDaysAgo;

        // Event log table name
        $eventLogTable = Logging::getLogEventTable($this->projectId);

        // Parameters for the main query
        $mainQueryParams = $metadataParams = [
            $thresholdDate->format('YmdHis'), // adjust to match the ts format (YYYYMMDDHHMMSS) in the event logs table
            $this->projectId,
            $thresholdDate->format('Y-m-d H:i:s'), // format to match the dates in the redcap_ddp_records table
        ];

        // Apply pagination
        $limitSql = $this->applyPagination($page, $perPage, $mainQueryParams);

        // Query to find non-queuable records based on the combined criteria with pagination

        $baseQuery = "SELECT r.record,
                r.future_date_count,
                r.updated_at,
                fetch_status,
                (SELECT COUNT(1) 
                FROM $eventLogTable e 
                WHERE e.project_id = r.project_id 
                    AND e.pk = r.record 
                    AND e.ts > ?
                    AND e.event IN ('UPDATE', 'INSERT', 'DELETE', 'DOC_UPLOAD', 'DOC_DELETE', 'LOCK_RECORD', 'ESIGNATURE')
                ) AS recent_event_count
            FROM redcap_ddp_records r
            WHERE
                r.project_id = ? 
                AND fetch_status IS NULL
            HAVING (
                r.future_date_count > 0
                OR (r.updated_at IS NOT NULL AND r.updated_at > ?)
                OR recent_event_count = 0
            )
            ORDER BY " . Records::getCustomOrderClause('r.record');
        $query = $baseQuery . $limitSql;
        $result = db_query($query, $mainQueryParams);

        while ($row = db_fetch_assoc($result)) {
            $reasons = [];
            $updated_at = TypeConverter::toDateTime($row['updated_at']);

            // Check each criterion and add the reason
            if ($row['future_date_count'] > 0) {
                $reasons[] = 'Record has mappings with future dates.';
            }
            if ($updated_at !== null && $updated_at > $thresholdDate) {
                $updated_at_format = $updated_at->format('Y-m-d H:i:s');
                $reasons[] = "Record data from EHR was collected recently ($updated_at_format).";
            }
            if ($row['recent_event_count'] == 0) {
                $reasons[] = 'Record was not updated recently.';
            }

            // If any reasons are found, the record is non-queuable
            if (!empty($reasons)) {
                $row['reasons'] = $reasons;
                $nonQueuableRecords[$row['record']] = new NotQueuedRecordDTO($row);
            }
        }

        // Populate metadata
        $this->populateMetadata($baseQuery, $metadataParams, $page, $perPage, $metadata);

        return $nonQueuableRecords;
    }
}