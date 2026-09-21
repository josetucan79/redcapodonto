<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull;

use DynamicDataPull;
use Project;

/**
 * Processes queued clinical data pulls from a FHIR source.
 */
class ClinicalDataPullQueueManager {

	/**
     * @var array Stores log messages generated during processing.
     */
	private $logs = [];

	/**
     * @var int The maximum number of records to fetch per batch.
     */
	const FETCH_LIMIT_PER_BATCH = 10000;

	const STATUS_NULL = NULL;
	const STATUS_QUEUED = 'QUEUED';
	const STATUS_FETCHING = 'FETCHING';

	/**
     * Fetches queued records from the source and processes them.
     *
     * This method iterates over queued records in the database, fetches data
     * for each record, and processes it. It uses a generator to yield results
     * for each processed record.
     *
     * @global Project $Proj
     * @global int $project_id
     * @return \Generator Yields processing result for each record.
     */
	public function fetchQueuedRecordsFromSource()
	{
		global $Proj, $project_id;

		try {
			$processed = [];

			$project_mrid_list = $this->getProjectsWithQueuedRecords();
            if (!is_array($project_mrid_list)) $project_mrid_list = [];
			// $this->addLog("got list of mr_ids per project: " . json_encode($project_mrid_list, JSON_PRETTY_PRINT));
			
			$allMrIDs = $this->extractMrIDs($project_mrid_list);
			// $this->addLog("got mr_ids only: " . json_encode($allMrIDs, JSON_PRETTY_PRINT));
			
			$this->setMrIdStatus($allMrIDs, self::STATUS_FETCHING);
			// $this->addLog("set status for all mr_ids to 'FETCHING'");


			foreach ($project_mrid_list as $this_project_id => $mrid_list) {
				$project_id = $this_project_id;
				$Proj = new Project($this_project_id); // Assuming Project is a defined class
				$DDP = new DynamicDataPull($this_project_id, $Proj->project['realtime_webservice_type']);

				foreach ($mrid_list as $mr_id) {
					[$fetched, $record] = $this->processMrId($mr_id, $DDP, $Proj, $this_project_id);
					// $this->addLog("Marking mr_id $mr_id as processed.");
					$processed[] = $mr_id;
					yield [$this_project_id, $mr_id, $fetched];
				}
			}
		} finally {
			// Compute non-processed MR IDs
			if(!empty($processed)) {
				$this->setMrIdStatus($processed, self::STATUS_NULL, null);
				// $this->addLog("set status of processed mr_ids back to null, and the item_count to 0.");
			}else {
				// $this->addLog("no mr_ids were processed.");
			}
			$nonProcessedMrIDs = array_diff($allMrIDs, $processed);
			if(!empty($nonProcessedMrIDs)) {
				$this->setMrIdStatus($nonProcessedMrIDs, self::STATUS_QUEUED);
				// $this->addLog("set status of non-processed mr_ids back to queued.");
			}
		}
	}

	/**
     * Retrieves a list of project IDs with queued records.
     *
     * Executes a database query to fetch queued records, grouped by project ID.
     * Limits the number of fetched records according to FETCH_LIMIT_PER_BATCH.
     *
     * @return array An associative array of project IDs and their corresponding MR IDs.
     */
	public function getProjectsWithQueuedRecords()
	{
		// Get projects with gueued records
		$project_mrid_list = array();
		$sql = "SELECT project_id, mr_id FROM redcap_ddp_records
				WHERE fetch_status = 'QUEUED' ORDER BY updated_at LIMIT ?";
		$q = db_query($sql, [self::FETCH_LIMIT_PER_BATCH]);

		while ($row = db_fetch_assoc($q)) {
			// Add project_id to array
			$project_mrid_list[$row['project_id']][] = $row['mr_id'];
		}
		return $project_mrid_list;
	}

	/**
     * Sets the fetch status for a list of MR IDs.
     *
     * Updates the fetch status (and optionally the item count) of the specified
     * MR IDs in the database.
     *
     * @param array $mr_ids The MR IDs to update.
     * @param string|null $status The new fetch status.
     * @param bool|int $item_count The item count to set, if applicable.
     * @return bool Returns true on successful query execution, false otherwise.
     */
	private function setMrIdStatus($mr_ids, $status = null, $item_count = false)
	{
		if(empty($mr_ids)) return;
		$sql = "UPDATE redcap_ddp_records SET fetch_status = ?";
		$params = [$status];
	
		if ($item_count !== false) {
			$sql .= ", item_count = ?";
			$params[] = $item_count;
		}
	
		// Construct the IN clause for multiple mr_id values
		$placeholders = implode(',', array_fill(0, count($mr_ids), '?'));
		$sql .= " WHERE mr_id IN ($placeholders)";
		$params = array_merge($params, $mr_ids);
	
		return db_query($sql, $params);
	}

	/**
     * Extracts and merges MR IDs from a list of projects.
     *
     * Given an associative array of project IDs and their MR IDs, this method
     * merges all MR IDs into a single array.
     *
     * @param array $project_mrid_list An associative array of project IDs and MR IDs.
     * @return array A flat array of all MR IDs.
     */
	private function extractMrIDs($project_mrid_list)
	{
		// Check if the input array is empty
		if (empty($project_mrid_list)) return [];
		return array_merge(...$project_mrid_list);
	}

	/**
     * Processes a single MR ID.
     *
     * Fetches data for the given MR ID and logs the process. Handles and logs exceptions.
     *
     * @param int $mr_id The MR ID to process.
     * @param DynamicDataPull $DDP The DynamicDataPull object for data fetching.
     * @param Project $Proj The Project object related to the current processing.
	 * @return array An array containing a boolean flag indicating the success of the process and the record identifier.
     */
	private function processMrId($mr_id, $DDP, $Proj)
	{
		try {
			$this_project_id = $Proj->project_id;
			$identifierData = $this->getSystemIdentifierForMrId($this_project_id, $mr_id);
			if(!$identifierData) {
				// $this->addLog("No system identifier data retrieved for mr_id $mr_id");
				return [false, null];
			}

			// $this->addLog("Retrieved system identifier data mr_id $mr_id: " . json_encode($identifierData, JSON_PRETTY_PRINT));

			$record = $identifierData['record'];
			$source_id = $identifierData['source_id'];

			$DDP->fetchData(
				$record,
				$event_id=null,
				$source_id,
				$form_data=[],
				$forceDataFetch=true,
				$record_exists=true,
				$instance=0,
				$repeat_instrument='',
				$Proj
			);
			// $this->addLog("Data fetched for record $record");
			return [true, $record];
		} catch (\Throwable $th) {
			// $this->addLog("Error fetching data for record $record. " . $th->getMessage());
			return [false, $record];
		}
	}

	/**
     * Retrieves the system identifier and related information for a given MR ID.
     *
     * Performs a database query to fetch the source system identifier and related
     * record information for the specified MR ID.
     *
     * @param int $project_id The project ID associated with the MR ID.
     * @param string $mr_id The MR ID to look up.
     * @return array|null An associative array with relevant data if found, null otherwise.
     */
	private function getSystemIdentifierForMrId($project_id, $mr_id)
	{
		// Query to get source ID field values and corresponding record name
		$data_table = \Records::getDataTable($project_id);
		$sql = "SELECT r.mr_id, d.record, d.value, d.event_id FROM
					redcap_ddp_mapping m, $data_table d, redcap_ddp_records r
					WHERE m.project_id = ?
					AND m.is_record_identifier = 1 AND d.project_id = m.project_id
					AND d.event_id = m.event_id AND d.field_name = m.field_name AND r.project_id = m.project_id
					AND r.record = d.record AND r.mr_id = ?";
		$q = db_query($sql, [$project_id, $mr_id]);
		if ($row = db_fetch_assoc($q)) {
			// Remove any blank values
			if ($row['value'] == '') return;
			// Add value to array with record name as key
			return [
				'record' => $row['record'],
				'source_id' => $row['value'],
				'event_id' => $row['event_id'],
			];
		}
	}

	/**
     * Adds a log entry to the internal log array.
     *
     * @param string $message The log message to add.
     */
	private function addLog($message)
	{
		$this->logs[] = $message;
	}

	/**
     * Retrieves all log entries.
     *
     * @return array An array of log messages.
     */
	public function getLogs()
	{
		return $this->logs;
	}
}
