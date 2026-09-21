<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Services;

use Records;
use Project;
use DateTime;
use DateTimeZone;
use Exception;
use Vanderbilt\REDCap\Classes\Fhir\Utility\DBDataNormalizer;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Utilities\TextNormalizer;


class DataCacheService
{
    const DDP_ENCRYPTION_KEY = "ds9p2PGh#hK4aV@GVH-YbPrtpWp*7SpeBW+RTujYHj%q35aOrQO/aCSVIFMKifl!S6Ql~JV";
    const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    private $project_id;
    private $Proj;

    public function __construct($project_id)
    {
        $this->project_id = $project_id;
        $this->Proj = new Project($project_id);
    }

    public function cacheFetchedData($response_data_array, $mappings, $mr_id, $convert_timestamp_from_gmt, &$nonTemporalMultipleValueFields=[])
    {
        // Initialize necessary variables
        $nonTemporalValueCount = array();

        // Create an instance of DBDataNormalizer (assuming it's an existing class)
        $dbDataNormalizer = new DBDataNormalizer();
        $tooLargeNotice = DBDataNormalizer::TOO_LARGE_NOTICE;
        $truncationPrefix = "--- $tooLargeNotice ---\n";
        $truncationSuffix = "\n...";

        foreach ($response_data_array as $item_key => $this_item) {
            // Loop through mappings of this data value's field
            if (isset($this_item['field'])) {
                $this_mapping = $mappings[$this_item['field']] ?? [];
            } else {
                $this_mapping = [];
            }
            foreach ($this_mapping as $this_event_id => $event_array) {
                foreach ($event_array as $this_rc_field => $rc_field_array) {
                    // Reset current_item to the original value of this_item
                    $current_item = $this_item;
                    // Get map_id
                    $this_map_id = $rc_field_array['map_id'];
                    if (!empty($current_item['timestamp'])) {
                        // Clean the timestamp in case it ends with ".0" or anything else
                        $current_item['timestamp'] = $this->formatTimestamp($current_item['timestamp']);
                        // If we're shifting the timestamp from GMT to local/server time, then convert time
                        if ($convert_timestamp_from_gmt) {
                            $current_item['timestamp'] = $this->convertTimeFromGMT($current_item['timestamp']);
                        }
                    }
                    // Prepare timestamp for the query
                    $this_timestamp = empty($current_item['timestamp']) ? null : $current_item['timestamp'];
                    // If a non-temporal field, increment counter
                    if (empty($current_item['timestamp'])) {
                        if (isset($nonTemporalValueCount[$this_rc_field])) {
                            $nonTemporalValueCount[$this_rc_field]++;
                        } else {
                            $nonTemporalValueCount[$this_rc_field] = 0;
                        }
                    }
                    $currentValue = $originalValue = $current_item['value'];
                    $currentSize = DBDataNormalizer::MAX_FIELD_SIZE;

                    do {
                        $this_encrypted_value = encrypt($currentValue, self::DDP_ENCRYPTION_KEY);
                        $tooBig = $dbDataNormalizer->isSizeExceeded($this_encrypted_value);
                        if ($tooBig) {
                            $currentValue = $dbDataNormalizer->truncate($originalValue, $currentSize, $truncationPrefix, $truncationSuffix);
                            $currentSize -= intval(DBDataNormalizer::MAX_FIELD_SIZE * 0.1); // Keep truncating at 10% increments
                        }
                    } while ($tooBig);

                    // Check if this timestamp-value already exists. If not, then add.
                    $sql = "SELECT md_id, source_value2 FROM redcap_ddp_records_data
                            WHERE map_id = ? AND mr_id = ? AND source_timestamp ";

                    $params = [$this_map_id, $mr_id];

                    if (empty($current_item['timestamp'])) {
                        // If the timestamp is empty, check for NULL
                        $sql .= "IS NULL";

                        // If the field is a checkbox, include source_value2 in the condition
                        if ($this->Proj->isCheckbox($this_rc_field)) {
                            $sql .= " AND source_value2 = ?";
                            $params[] = $this_encrypted_value;
                        }
                    } else {
                        // If the timestamp is not empty, compare it and include source_value2
                        $sql .= "= ? AND source_value2 = ?";
                        $params[] = $this_timestamp;
                        $params[] = $this_encrypted_value;
                    }

                    $sql .= " LIMIT 1";

                    $q = db_query($sql, $params);

                    $alreadyCached = (db_num_rows($q) > 0);
                    if ($alreadyCached) {
                        // Get existing md_id and source value
                        $response_data_array[$item_key]['md_id'] = db_result($q, 0, 'md_id');
                        $cachedSourceValue = db_result($q, 0, 'source_value2');
                        // Update value in values table if the non-temporal value has changed since it was cached
                        if ($cachedSourceValue != $this_encrypted_value) {
                            $md_id = $response_data_array[$item_key]['md_id'] ?? null;
                            if(!$md_id) trigger_error("md_id should not be null.", E_USER_WARNING);
                            $params2 = [$this_encrypted_value, $md_id];
                            $sql = "UPDATE redcap_ddp_records_data SET source_value2 = ? WHERE md_id = ?";
                            db_query($sql, $params2);
                        }
                    } else {
                        $params3 = [$this_map_id, $mr_id, $this_timestamp, $this_encrypted_value];
                        // Add value to values table
                        $sql = "INSERT INTO redcap_ddp_records_data (map_id, mr_id, source_timestamp, source_value2) VALUES (?, ?, ?, ?)";
                        db_query($sql, $params3);
                        // Add md_id to keymap array
                        $response_data_array[$item_key]['md_id'] = db_insert_id();
                    }
                    // You may need to handle FHIR stats collector here if applicable
                }
            }
        }
        // Handle non-temporal fields with multiple values if necessary
        // This could involve setting a property or returning the count
        // For example:
        $nonTemporalMultipleValueFields = [];
        foreach ($nonTemporalValueCount as $field => $count) {
            if ($count > 0 && !$this->Proj->isCheckbox($field)) {
                $nonTemporalMultipleValueFields[] = $field;
            }
        }
    }

    public function cacheData($record_identifier_rc, $response_data_array, $mappings, $temporal_fields, $convert_timestamp_from_gmt)
    {
        // Get or create MR_ID
        $mr_id = $this->getOrCreateMrId($record_identifier_rc, $isNewMrId);

        $this->cacheFetchedData($response_data_array, $mappings, $mr_id, $convert_timestamp_from_gmt);

        // Update fetch status and future dates
        $future_dates = $this->countFutureDates($record_identifier_rc, $temporal_fields);
        $this->updateFetchStatus($mr_id, $future_dates);
    }

    private function getOrCreateMrId($record_identifier_rc, &$isNew=false)
    {
        $sql = "SELECT mr_id FROM redcap_ddp_records WHERE project_id = ? AND record = ? LIMIT 1";
        $q = db_query($sql, [$this->project_id, $record_identifier_rc]);
        if (db_num_rows($q)) {
            return db_result($q, 0);
        } else {
            $sql = "INSERT INTO redcap_ddp_records (project_id, record) VALUES (?, ?)";
            $result = db_query($sql, [$this->project_id, $record_identifier_rc]);
            $isNew = true;
            return ($result ? db_insert_id() : false);
        }
    }

    private function countFutureDates($record_identifier_rc, $temporal_fields)
    {
        if(empty($temporal_fields)) return 0;
        $future_dates = 0;
        $data_temporal_fields = Records::getData($this->project_id, 'array', $record_identifier_rc, $temporal_fields);
        $record_temporal_fields = $data_temporal_fields[$record_identifier_rc] ?? [];
        foreach ($record_temporal_fields as $this_event_id => $these_fields) {
            if ($this_event_id == 'repeat_instances') {
                foreach ($these_fields as $attr) {
                    foreach ($attr as $bttr) {
                        foreach ($bttr as $cttr) {
                            foreach ($cttr as $this_field => $this_value) {
                                if ($this_value != '' && $this_value >= date('Y-m-d')) {
                                    $future_dates++;
                                }
                            }
                        }
                    }
                }
            } else {
                foreach ($these_fields as $this_field => $this_value) {
                    if ($this_value != '' && $this_value >= date('Y-m-d')) {
                        $future_dates++;
                    }
                }
            }
        }
        return $future_dates;
    }

    /**
     * Helper function to format a timestamp.
     *
     * @param string|null $timestamp The input timestamp string.
     * @return string|null Formatted date in 'Y-m-d H:i:s' format or null if invalid.
     */
    function formatTimestamp(?string $timestamp): ?string
    {
        if (empty($timestamp)) {
            return null;
        }

        try {
            $date = new DateTime($timestamp);
            return $date->format(self::TIMESTAMP_FORMAT); // Return in MySQL-compatible datetime format
        } catch (Exception $e) {
            // Log or handle invalid timestamp if needed
            return null;
        }
    }

    public function convertTimeFromGMT($timestamp) {
		$userTimezone = new DateTimeZone(getTimeZone());
        $gmtTimezone = new DateTimeZone('GMT');
        $dateTime = new DateTime($timestamp, $gmtTimezone);
        $dateTime->setTimezone($userTimezone);
        return $dateTime->format(self::TIMESTAMP_FORMAT);
	}

    public function getCachedData($record_identifier_rc, $mappings)
    {
        $map_ids = $this->extractMapIds($mappings);
        $response_data_array = array();

        $map_ids_placeholders = implode(',', array_fill(0, count($map_ids), '?'));
        $sql = "SELECT m.field_name, d.md_id, m.external_source_field_name, d.source_timestamp, m.event_id, d.source_value, d.source_value2
                FROM redcap_ddp_mapping m
                JOIN redcap_ddp_records_data d ON m.map_id = d.map_id
                JOIN redcap_ddp_records r ON d.mr_id = r.mr_id
                WHERE m.project_id = ?
                AND r.project_id = m.project_id
                AND r.record = ?
                AND m.map_id IN ($map_ids_placeholders)";
        $params = array_merge([$this->project_id, $record_identifier_rc], $map_ids);
        $q = db_query($sql, $params);
        while ($row = db_fetch_assoc($q)) {
            $use_mcrypt = ($row['source_value2'] == '');
            $source_value = $use_mcrypt ? $row['source_value'] : $row['source_value2'];
            $response_data_array[] = array(
                'field' => $row['external_source_field_name'],
                'timestamp' => $row['source_timestamp'],
                'value' => decrypt($source_value, self::DDP_ENCRYPTION_KEY, $use_mcrypt),
                'md_id' => $row['md_id'],
                'event_id' => $row['event_id'],
                'rcfield' => $row['field_name']
            );
        }
        return $response_data_array;
    }

    public function updateFetchStatus($mr_id, $future_dates)
    {
        // Update the fetch status and future date count in the database
        $sql = "UPDATE redcap_ddp_records SET updated_at = ?, fetch_status = NULL,
                future_date_count = ? WHERE mr_id = ?";
        db_query($sql, [NOW, $future_dates, $mr_id]);
    }

    private function extractMapIds($mappings)
    {
        $map_ids = array();
        foreach ($mappings as $this_src_field => $this_evt_array) {
            foreach ($this_evt_array as $this_event_id => $these_rc_fields) {
                foreach ($these_rc_fields as $this_rc_field => $rc_field_attr) {
                    $map_ids[] = $rc_field_attr['map_id'];
                }
            }
        }
        return $map_ids;
    }

    /**
	* Compares existing 2 sets of cached data.
	* For example, compare existing cached values with newly fetched data and returns the list of md_ids
	* where the values have changed.
	*
	* Only entries with matching field names and timestamps are compared. New entries that did not
	* exist in the cached data are ignored.
	*
	* Note: If multiple existing records share the same field+timestamp combination, this function
	* may produce false positives for changes. Each field+timestamp key should ideally be unique
	* in the existing data to ensure accurate comparison.
	*
	* @param array<int, array{
	*     field: string,
	*     timestamp: string|null,
	*     value: string,
	*     md_id: int
	* }> $existingData The previously cached data.
	*
	* @param array<int, array{
	*     field: string,
	*     timestamp: string|null,
	*     value: string
	* }> $newData The newly fetched data (no md_id yet).
	*
	* @return array<int> List of md_id values for records that have changed.
	*/
    public function compareCachedData(array $existingData, array $newData): array
    {
		$changedMdIds = [];

		// Normalize existing data: [field][timestamp] => [['value' => ..., 'source_value' => ..., 'md_id' => ...], ...]
		$existingLookup = [];
		foreach ($existingData as $item) {
			$key = $item['field'] . '|' . ($item['timestamp'] ?? '');
			$existingLookup[$key][] = [
				'value' => $item['value'],
				'md_id' => $item['md_id']
			];
		}

		// Compare with new data
		foreach ($newData as $item) {
			$key = $item['field'] . '|' . ($item['timestamp'] ?? '');
			$newValue = TextNormalizer::normalizeText($item['value']);

			if (isset($existingLookup[$key])) {
				$found = false;
				foreach ($existingLookup[$key] as $existingItem) {
					if ($newValue === TextNormalizer::normalizeText($existingItem['value'])) {
						$found = true;
						break;
					}
				}
				
				// If no exact match found, mark all existing items with this key as changed
				if (!$found) {
					foreach ($existingLookup[$key] as $existingItem) {
						$changedMdIds[] = $existingItem['md_id'];
					}
				}
			}
		}

		return array_unique($changedMdIds);
	}

    /**
     * Fetches current REDCap record data for the mapped fields.
     * Returns raw REDCap data structure for comparison purposes.
     * Note: Currently only supports first instance (no repeating instances).
     *
     * @param string $record The record identifier
     * @param array $mappings The field mappings
     * @return array Raw REDCap data indexed by [event_id][field_name] => value
     */
    public function getRedcapRecordData($record, $mappings)
    {
        $fieldsToFetch = [];
        $eventIds = [];
        
        // Extract REDCap fields and event IDs from mappings
        foreach ($mappings as $externalField => $eventArray) {
            foreach ($eventArray as $eventId => $rcFieldArray) {
                foreach ($rcFieldArray as $rcField => $fieldInfo) {
                    $fieldsToFetch[] = $rcField;
                    $eventIds[] = $eventId;
                }
            }
        }
        
        $fieldsToFetch = array_unique($fieldsToFetch);
        $eventIds = array_unique($eventIds);
        
        if (empty($fieldsToFetch)) return [];
        
        // Fetch current REDCap data using Records::getData
        $recordData = Records::getData($this->project_id, 'array', $record, $fieldsToFetch, $eventIds);
        
        // Return only the record data (excluding repeat_instances for now)
        return $recordData[$record] ?? [];
    }

    /**
     * Compares current cache data with stored REDCap field values.
     * Returns md_ids where cached values differ from current REDCap values.
     * This is a separate check from cache-to-cache comparison.
     *
     * @param array $currentCache Current cached data with structure: [field, timestamp, value, md_id, event_id, rcfield]
     * @param array $redcapData Raw REDCap data indexed by [event_id][field_name] => value
     * @param array $mappings Field mappings to understand the relationships
     * @return array List of md_id values where cache differs from REDCap data
     */
    public function compareCacheWithRedcapData(array $currentCache, array $redcapData, array $mappings): array
    {
        $changedMdIds = [];
        
        // Create mapping lookup: external_field -> event_id -> rc_field
        $fieldMapping = [];
        foreach ($mappings as $externalField => $eventArray) {
            foreach ($eventArray as $eventId => $rcFieldArray) {
                foreach ($rcFieldArray as $rcField => $fieldInfo) {
                    $fieldMapping[$externalField][$eventId] = $rcField;
                }
            }
        }
        
        // Compare each cached item with corresponding REDCap value
        foreach ($currentCache as $cacheItem) {
            $externalField = $cacheItem['field'];
            $eventId = $cacheItem['event_id'];
            $cachedValue = TextNormalizer::normalizeText($cacheItem['value']);
            $mdId = $cacheItem['md_id'];
            
            // Find corresponding REDCap field
            if (isset($fieldMapping[$externalField][$eventId])) {
                $rcField = $fieldMapping[$externalField][$eventId];
                $redcapValue = TextNormalizer::normalizeText($redcapData[$eventId][$rcField] ?? '');
                
                // Compare normalized values
                if ($cachedValue !== $redcapValue) {
                    $changedMdIds[] = $mdId;
                }
            }
        }
        
        return array_unique($changedMdIds);
    }

    /**
     * Resets adjudication flags for a given list of md_id values by setting
     * adjudicated = 0 and exclude = 0 in the redcap_ddp_records_data table.
     *
     * @param array<int> $md_ids List of md_id values to update.
     * @return void
     */
    public function resetAdjudicationFlags(array $md_ids): void
    {
        if (empty($md_ids)) return;

        $placeholders = dbQueryGeneratePlaceholdersForArray($md_ids);
        $sql = "UPDATE redcap_ddp_records_data
                SET adjudicated = 0, exclude = 0
                WHERE md_id IN ($placeholders)";
        db_query($sql, $md_ids);
    }


}
