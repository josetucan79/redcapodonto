<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication;

use Logging;
use Renderer;
use Project;
use Exception;
use Generator;
use DynamicDataPull;
use Vanderbilt\REDCap\Classes\Queue\Queue;
use Vanderbilt\REDCap\Classes\Parcel\PostMaster;
use Vanderbilt\REDCap\Classes\Fhir\Utility\ProjectProxy;
use Vanderbilt\REDCap\Classes\SystemMonitors\ResourceMonitor;
use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStatsCollector;
use Vanderbilt\REDCap\Classes\Traits\CanMakeDateTimeFromInterval;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\CacheProcessor;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;

class AutoAdjudicator
{
    use CanMakeDateTimeFromInterval;

    /**
     * table names
     */
    const AUTO_ADJUDICATION_LOG_TABLE_TYPE = 'CDP_AUTO_ADJUDICATION';
    const CACHED_RECORDS_DATA_TABLE = 'redcap_ddp_records_data';
    const CACHED_RECORDS_TABLE = 'redcap_ddp_records';
    const DDP_MAPPING_TABLE = 'redcap_ddp_mapping';

    /**
     * format of the timestamp in redcap_ddp_records_data 
     */
    const SOURCE_DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * define the maximum number of records to get metedata
     * for when using getDdpRecordsDataStats
     */
    const RECORDS_METADATA_CHUNK_SIZE = 500;

    /**
    * username of current user
    *
    * @var string
    */
    private $user_id;

    /**
    * project ID
    *
    * @var int
    */
    private $project_id;

    /**
     * current project
     *
     * @var ProjectProxy
     */
    private $project;
    
    /**
    * Auto Adjudicate data in CDP projects
    *
    * @param int $project_id
    */
    public function __construct($project_id, $user_id=false)
    {
        $this->user_id = $user_id;
        $this->project_id = $project_id;
        $this->project = new ProjectProxy($project_id);

    }

    /**
     *
     * @param string $record_id
     * @return Generator
     */
    public function getNonAdjudicatedFieldsInRecord($record_id)
    {
        $params = [];
        $entriesSubQuery = CacheEntry::getCacheEntriesQuery($this->project_id, $params);
        $queryString =
            "SELECT * FROM ($entriesSubQuery) AS sub
            WHERE sub.project_id=? AND sub.record=?";
        $params = array_merge($params, [$this->project_id, $record_id]);
        $result = db_query($queryString, $params);
        while($result && ($row = db_fetch_assoc($result))) {
            yield $row;
        }
    }

    
    public static function getLogsForProject($project_id, $limit=0, $start=0)
    {
        $log_table = \Logging::getLogEventTable($project_id);
        $query_string = sprintf("SELECT * FROM %s WHERE object_type='%s' LIMIT %u,%u", $log_table, self::AUTO_ADJUDICATION_LOG_TABLE_TYPE, $limit, $start);
        
        $result = db_query($query_string);
        if(!$result) {
            $message = sprintf("There was a problem retrieving the logs for project ID %s", $project_id);
            throw new \Exception($message, 400);
        }
        $rows = [];
        while($row = db_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

     /**
     * get a list of fields with
     * - non-empty
     * - non-adjudicated
     * - not excluded values
     *
     * @return Generator
     */
    public function getProcessableFields()
    {
        $params = [];
        $subQuery = CacheEntry::getCacheEntriesQuery($this->project_id, $params);
        $query_string =
            "SELECT project_id, event_id, record, field_name
            FROM ({$subQuery}) AS entries
            WHERE entries.project_id=?
            AND entries.adjudicated=0
            AND entries.exclude=0
            GROUP BY project_id, event_id, record, field_name";
        $params = array_merge($params, [$this->project_id]);
        $result = db_query($query_string, $params);
        if(!$result) throw new \Exception(sprintf("There was an error retrieving cached data from the database for project %u", $this->project_id), 400);
        
        while($row = db_fetch_assoc($result)) {
            yield $row;
        }
    }

    /**
     * randomly throw an exception
     * the frequency depends on the specified percentage
     *
     * @param integer $percentage
     * @throws \Exception
     * @return void
     */
    private function getRandomError($percentage=80) {
        $random = rand(0,100);
        if($random>$percentage) {
            $error_messages = [
                'validation error',
                'something bad happened',
                'something REALLY bad happened',
                'timeout error',
                'bad data format',
                'unexpected error',
                'something REALLY bad happened',
                'connection error',
            ];
            $error_index = rand(0, count($error_messages)-1);
            $message = $error_messages[$error_index];
            throw new \Exception($message, 400);
        }
    }


    /**
     * process a single field
     *
     * @param string $record_id
     * @param int $event_id
     * @param string $field_name
     * @return array [adjudicated, excluded, error]
     * @throws Exception
     */
    public function processField($record_id, $event_id, $field_name) {
        try {
            $adjudicatedCount = 0;
            $excludedCount = 0;
            $error = null;

            $cacheProcessor = new CacheProcessor($this->project_id, $record_id, $event_id, $field_name);
            // $this->getRandomError(80);
            $cacheProcessor->process();
            $selected = $cacheProcessor->getSelected();
            if($selected instanceof CacheEntry) $this->save($selected);
            // mark as adjudicated and excluded if no errors
            $adjudicatedCount += $cacheProcessor->adjudicate(); 
            $excludedCount += $cacheProcessor->exclude();
            if($record_id  && ($adjudicatedCount>0 || $excludedCount>0)) $this->updateDdpRecordsCounter($record_id);
        } catch (\Throwable $th) {
            $error = $th->getMessage();
        }finally {
            return [
                'adjudicated' => $adjudicatedCount,
                'excluded' => $excludedCount,
                'error' => $error,
            ];
        }
    }


    /**
     * process every record ID and adjudicate data
     *
     * @param boolean $background wheter to start the process in background or not
     * @return mixed
     */
    public function processCachedData($background=false, $send_feedback=false)
    {
        if($background==true) {
            return $this->scheduleProcessRecords($send_feedback);
        }
        return $this->processRecords($background, $send_feedback);
    }

    public function scheduleProcessRecords($send_feedback) {
        $projectID = $this->project_id;
        $user_id = $this->user_id;
        $className = AutoAdjudicator::class; //AutoAdjudicator
        $closure = function() use($className, $projectID, $user_id, $send_feedback) {
            global $project_id;
            $project_id = $projectID; // needed for logging
            $auto_adjudicator = new $className($projectID, $user_id);
            return $auto_adjudicator->processRecords($background=true, $send_feedback);
        };
        $queue_key = "AutoAdjudication_{$projectID}";
        $queue = new Queue();
        return $queue->addMessageIfNotExists($closure, $queue_key, 'CDP auto-adjudication');
    }

    public function processRecords($background, $send_feedback)
    {
        $resourceMonitor = ResourceMonitor::create(['time' => '30 minutes']);
        $fieldsGenerator = $this->getProcessableFields();

        $errors = []; // list of errors
        $total = 0; //total records processed
        $successful = 0; // total successful records processed
        $excluded = 0; // total excluded values
        $adjudicated = 0; // total adjudicated values

        while($field = $fieldsGenerator->current()) {
            $resourcesOk = $resourceMonitor->checkResources();
            if($background && (!$resourcesOk)) {
                // schedule next and exit
                $this->processCachedData($background, $send_feedback);
                break; // break the outer loop
            }

            $fieldsGenerator->next();
            $total++;
            $record = $field['record'];
            $event_id = $field['event_id'];
            $field_name = $field['field_name'];
            list('adjudicated' => $adjudicatedCount, 'excluded' => $excludedCount, 'error' => $error) = $this->processField($record, $event_id, $field_name);
            // mark as adjudicated and excluded if no errors
            if($error) $errors[] = $error;
            else {
                $successful++;
                $adjudicated += $adjudicatedCount;
                $excluded += $excludedCount;
            }
        }

        // this response, with no errors, is used when sending a message
        $response = [
            'total fields' => $total,
            'successful fields' => $successful,
            'adjudicated values' => $adjudicated,
            'excluded values' => $excluded,
            'total values' => $adjudicated+$excluded,
            'errors' => count($errors),
        ];
        // this is what we return
        $responseWithErrors = array_merge($response, ['errors' => $errors]);
        
        $somethingHappended = function() use($adjudicated, $errors) {
            $totalErrors = count($errors);
            return ($totalErrors+$adjudicated)>0;
        };

        if($send_feedback && $somethingHappended()) {
            $this->sendFeedback($response, $errors);
        };
        return $responseWithErrors;
    }

    public function save(CacheEntry $cacheEntry) {
        $record = $cacheEntry->getRecord();
        $save_response = \REDCap::saveData($this->project_id, 'array', $record);
        $errors = $save_response['errors'] ?? [];
        if(!empty($errors)) {
            $save_errors = is_string($errors) ? $errors : implode(';', $errors);
            $message = "Error updating REDCap record {$cacheEntry->record} - {$save_errors}";
            throw new \Exception($message, 400);
        }

        // log statistics for adjudicated FHIR data
        $count = intval($save_response['item_count']);
        if($count===0) return; // nothing to log

        // only collect stats if CDP project (no DDP)
        $realtimeWebService = $this->project->getRealtimeWebserviceType();
        if($realtimeWebService==='FHIR') {
            $fhirStatsCollector = new FhirStatsCollector($this->project_id, FhirStatsCollector::REDCAP_TOOL_TYPE_CDP_INSTANT);
            $fhirCategory = DynamicDataPull::getMappedFhirResourceFromFieldName($this->project_id, $cacheEntry->field_name, $cacheEntry->event_id);
            $fhirStatsCollector->addEntry($cacheEntry->record, $fhirCategory, $count);
            $fhirStatsCollector->logEntries();
        }
    }

    /**
     * title used as title for feedback messages
     */
    const FEEDBACK_MESSAGE_TITLE = 'Instant Adjudication Completed';
    const FEEDBACK_MESSAGE_DELAY = 60*30;

    public function sendFeedback($data, $errors) {
        $getImageAsDataUri = function($image)
        {
            $type = pathinfo($image, PATHINFO_EXTENSION);
            $data = file_get_contents($image);
            $dataUri = 'data:image/' . $type . ';base64,' . base64_encode($data);
            return $dataUri;
        };
        $redcap_logo_path = APP_PATH_DOCROOT.'Resources/images/redcap-logo-large.png';
        // $redcap_image_uri = $getImageAsDataUri($redcap_logo_path);
        
        $user_id = $this->user_id;
        
        $project_id = $this->project_id;
        $project = new \Project($project_id);
        $project_creator = $project->project['created_by'] ?? '';

        $blade = Renderer::getBlade();
        $blade->share('project_id', $project_id);
        // $blade->share('redcap_image_uri', $redcap_image_uri);

        $html = $blade->run('cdp.auto-adjudication.adjudication-complete-message', compact('data', 'errors'));

        $to = $user_id;
        $title = self::FEEDBACK_MESSAGE_TITLE;
        $scopedTitle = "[{$title} - pid {$this->project_id}]";

        $postMaster = new PostMaster();
        $postMaster->sendParcel($to, $from='REDCap - AutoAdjudication', $subject=$scopedTitle, $body=$html);
        return;
    }

     /**
     * Get a list of record ID that have potential data to be adjudicated.
     * Empty values are not counted.
     * 
     * @param integer $offset offset of the records
     * @return array list of IDs with metadata
     */
    public function getDdpRecordsMetadata1($offset=0)
    {
        $loadData = function($offset) {
            $params = [];
            $subQuery = CacheEntry::getCacheEntriesQuery($this->project_id, $params);
            $query_string =
                "SELECT project_id, event_id, record, field_name, COUNT(1) AS total
                FROM ({$subQuery}) AS entries
                WHERE entries.project_id=?
                AND entries.adjudicated=0
                AND entries.exclude=0
                GROUP BY project_id, event_id, record, field_name";
            $params = array_merge($params, [$this->project_id]);
            $totalResult = db_query($query_string, $params);
            if(!$totalResult) throw new \Exception(sprintf("There was an error retrieving cached data from the database for project %u", $this->project_id), 400);
            $total = db_num_rows($totalResult);
    
            $limit_query_string = $query_string . " LIMIT ?, ?";
            $limitParams = array_merge($params, [$offset, self::RECORDS_METADATA_CHUNK_SIZE]);
            $result = db_query($limit_query_string, $limitParams);
            if(!$result) throw new \Exception(sprintf("There was an error retrieving cached data from the database for project %u", $this->project_id), 400);
            $data = [];
            while($row = db_fetch_assoc($result)) $data[] = $row;
            return ['data' => $data, 'total'=>$total];
        };

        
        $offset = intval($offset);
        list('data'=>$data, 'total' => $total) = $loadData($offset);
        $nextOffset = $offset+self::RECORDS_METADATA_CHUNK_SIZE;
        $loadMore = $nextOffset<$total;


        $metadata = [
            'total' => $total,
            'next_offset' => $nextOffset = $offset+self::RECORDS_METADATA_CHUNK_SIZE,
            'load_more' => $loadMore,
        ];

        $response = [
            'metadata' => $metadata,
            'data' => $data,
        ];
        return $response;
    }

    /**
     * Get a list of record IDs that have potential data to be adjudicated.
     * Empty values are not counted.
     * 
     * @param integer $page page number of the records (starting from 1)
     * @param integer $per_page number of records per page
     * @return array list of IDs with metadata
     */
    public function getDdpRecordsMetadata($page = 1, $per_page = self::RECORDS_METADATA_CHUNK_SIZE)
    {
        $page = max(1, intval($page)); // Ensure page is at least 1
        $per_page = intval($per_page);

        $total = $this->getTotalRecords();
        $data = $this->getPaginatedData($page, $per_page);

        $totalPages = ceil($total / $per_page);
        $totalCurrentPage = count($data);
        $nextPage = ($page < $totalPages) ? $page + 1 : null; // Only set next page if it exists

        $metadata = [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $totalPages,
            'total_current_page' => $totalCurrentPage,
            'next_page' => $nextPage, // Null if there are no more pages
        ];

        $response = [
            'metadata' => $metadata,
            'data' => $data,
        ];
        return $response;
    }

    /**
     * Get the total number of records matching the criteria, without pagination.
     * 
     * @return integer total number of records
     */
    private function getTotalRecords()
    {
        $params = [];
        $subQuery = CacheEntry::getCacheEntriesQuery($this->project_id, $params);
        $count_query_string = 
            "SELECT COUNT(DISTINCT project_id, event_id, record, field_name) AS total
            FROM ({$subQuery}) AS entries
            WHERE entries.project_id=?
            AND entries.adjudicated=0
            AND entries.exclude=0";
        $params = array_merge($params, [$this->project_id]);
        $countResult = db_query($count_query_string, $params);
        if (!$countResult) {
            throw new \Exception(sprintf("Error retrieving total count for project %u", $this->project_id), 400);
        }
        $totalRow = db_fetch_assoc($countResult);
        return $totalRow['total'] ?? 0;
    }

    /**
     * Get paginated data for the given page and per-page limit.
     * 
     * @param integer $page page number (starting from 1)
     * @param integer $per_page number of records per page
     * @return array list of records for the specified page
     */
    private function getPaginatedData($page, $per_page)
    {
        $offset = ($page - 1) * $per_page;
        $params = [];
        $subQuery = CacheEntry::getCacheEntriesQuery($this->project_id, $params);
        $query_string = 
            "SELECT project_id, event_id, record, field_name, COUNT(1) AS total
            FROM ({$subQuery}) AS entries
            WHERE entries.project_id=?
            AND entries.adjudicated=0
            AND entries.exclude=0
            GROUP BY project_id, event_id, record, field_name
            LIMIT ?, ?";
        $params = array_merge($params, [$this->project_id, $offset, $per_page]);
        $result = db_query($query_string, $params);
        if (!$result) {
            throw new \Exception(sprintf("Error retrieving paginated data for project %u", $this->project_id), 400);
        }

        $data = [];
        while ($row = db_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }


    /**
     * Count the not adjudicated values for a record.
     *
     * @param mixed $record_id
     * @return int
     */
    public function countNonAdjudicatedValues($record_id)
    {
        $total = 0;
        $query_string = sprintf(
            "SELECT records.record, COUNT(1) AS total
            FROM %s AS records
            LEFT JOIN %s AS data ON records.mr_id=data.mr_id
            WHERE project_id=%u AND records.record=%s
            AND (adjudicated=0 AND `exclude`=0)
            GROUP BY project_id, records.record",
            self::CACHED_RECORDS_TABLE,
            self::CACHED_RECORDS_DATA_TABLE,
            $this->project_id,
            checkNull($record_id)
        );
        $result = db_query($query_string);
        if($result==false) throw new \Exception(sprintf("There was an error counting not adjudicated data from the table `%s` in project %u", self::CACHED_RECORDS_DATA_TABLE, $this->project_id), 400);
        $row = db_fetch_assoc($result);
        $total = intval($row['total'] ?? 0);
        return $total;
    }

    /**
     * update the count of items to be adjudicated
     * also set the fetch status to QUEUED
     * NOTE: updated_at stores the last fetch date,
     * so should not be changed here
     *
     * @param mixed $record_id
     * @return int
     */
    public function updateDdpRecordsCounter($record_id)
    {
        $total = $this->countNonAdjudicatedValues($record_id);
        $query_string = sprintf(
            "UPDATE %s
            SET item_count=%u
            WHERE project_id=%u AND record=%s",
            self::CACHED_RECORDS_TABLE,
            $total,
            $this->project_id, checkNull($record_id)
        );
        $result = db_query($query_string);
        if($result==false) throw new \Exception(sprintf("Error updating the table `%s` in project %u", self::CACHED_RECORDS_TABLE, $this->project_id), 400);
        $affected_rows = db_affected_rows();
        Logging::logEvent($query_string, self::CACHED_RECORDS_TABLE, "MANAGE", $record_id,"total = {$total}",$message="updated non-adjudicated item_count to {$total}");
        return $affected_rows;
    }

    /**
     * set all records for the current project in QUEUE fetch_status
     * NOTE: updated_at stores the last fetch date,
     * so should not be changed here
     * 
     * @return void
     */
    public function queueAllRecords()
    {
        // $now = date(self::SOURCE_DATE_FORMAT);
        $query_string = sprintf(
            "UPDATE %s
            SET fetch_status='QUEUED'
            WHERE project_id=%u",
            self::CACHED_RECORDS_TABLE,
            $this->project_id
        );
        $result = db_query($query_string);
        if($result==false) throw new \Exception(sprintf("Error updating the table `%s` in project %u", self::CACHED_RECORDS_TABLE, $this->project_id), 400);
        $affected_rows = db_affected_rows();
        Logging::logEvent($query_string, self::CACHED_RECORDS_TABLE, "MANAGE", $this->user_id,"username = '" . db_escape($this->user_id) . "'",$message="all records have been queued");
        return $affected_rows;
    }

    /**
	 * check if auto-adjudication is allowed at system level
	 */
	public static function isAllowed()
	{
		$config = \System::getConfigVals();
		if(!isset($config['fhir_cdp_allow_auto_adjudication'])) return false;
		$allowed = $config['fhir_cdp_allow_auto_adjudication'] == 1;
		return $allowed;
    }
    
    /**
	 * check if the auto adjudication is enabled for a CDP project
	 */
	public static function isEnabled($project_id) {
        $project = new \Project($project_id);
		$auto_adjudication_enabled = $project->project['fhir_cdp_auto_adjudication_enabled'] ?: 0;
		return boolval($auto_adjudication_enabled);
    }
    
    public static function isEnabledAndAllowed($project_id)
    {
        return self::isAllowed() && self::isEnabled($project_id);
    }

    public static function isCronjobEnabled($project_id) {
        $enabledAndAllowed = static::isEnabledAndAllowed($project_id);
        if(!$enabledAndAllowed) return false;
        $project = new \Project($project_id);
        $cronjob_enabled = $project->project['fhir_cdp_auto_adjudication_cronjob_enabled'] ?: 0;
		return boolval($cronjob_enabled);
    }

    public static function getCronEnabledProjects() {
        $query_string = 'SELECT `project_id` FROM `redcap_projects` WHERE
                    `fhir_cdp_auto_adjudication_cronjob_enabled`=1
                    AND `fhir_cdp_auto_adjudication_enabled`=1
                    AND status!=2 AND completed_time IS NULL';
        $result = db_query($query_string);
        $list = [];
        while($row = db_fetch_assoc($result)) {
            $projectID = $row['project_id'] ?? null;
            if($projectID) $list[] = $projectID;
        }
        return $list;
    }
}
    