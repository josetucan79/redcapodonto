<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication;

use REDCap;

class AdjudicationTrackingService
{
    private $projectId;

    public function __construct($projectId)
    {
        $this->projectId = $projectId;
    }

    /**
     * Retrieves adjudicated field keys for the given record and map IDs.
     *
     * @param string $record The record ID.
     * @param array $mapIdList List of md_ids from mappings.
     * @return array Array of adjudicated field keys ("event_id-rc_field").
     */
    public function trackAdjudications($record, $mapIdList)
    {
        // Prepare the array to store adjudicated field keys
        $adjudicatedFields = [];

        if (empty($mapIdList)) {
            return $adjudicatedFields;
        }

        $map_ids_placeholders = implode(',', array_fill(0, count($mapIdList), '?'));

        // Query to get adjudicated fields for the record
        $sql = "SELECT m.event_id, m.field_name
            FROM redcap_ddp_records_data rd
            JOIN redcap_ddp_records r ON rd.mr_id = r.mr_id
            JOIN redcap_ddp_mapping m ON rd.map_id = m.map_id
            WHERE r.project_id = ?
            AND r.record = ?
            AND rd.adjudicated = 1
            AND rd.map_id IN ($map_ids_placeholders)";
        $params = array_merge([$this->projectId, $record], $mapIdList);
        $result = db_query($sql, $params);

        while ($row = db_fetch_assoc($result)) {
            $eventId = $row['event_id'];
            $fieldName = $row['field_name'];
            $fieldKey = "{$eventId}-{$fieldName}";
            $adjudicatedFields[] = $fieldKey;
        }

        return $adjudicatedFields;
    }

    /**
     * Excludes or includes a source value based on user action.
     *
     * @param string $record The record ID.
     * @param int $md_id The md_id of the source value.
     * @param string $action The action to perform ('exclude' or 'include').
     * @throws \InvalidArgumentException If an invalid action is specified.
     */
    private function handleExclusions($record, $md_id, $action)
    {
        // Sanitize inputs
        $recordEscaped = db_escape($record);
        $mdIdInt = intval($md_id);

        if ($action === 'exclude') {
            // Insert into redcap_ddp_excluded
            $sql = "INSERT INTO redcap_ddp_excluded (project_id, record, md_id)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE md_id = md_id";
            db_query($sql, [$this->projectId, $recordEscaped, $mdIdInt]);
        } elseif ($action === 'include') {
            // Delete from redcap_ddp_excluded
            $sql = "DELETE FROM redcap_ddp_excluded
                    WHERE project_id = ?
                      AND record = ?
                      AND md_id = ?";
            db_query($sql, [$this->projectId, $recordEscaped, $mdIdInt]);
        } else {
            throw new \InvalidArgumentException("Invalid action specified: {$action}");
        }

        // Log the action
        REDCap::logEvent(
            "DDP Exclusion",
            "Action: {$action}, md_id: {$mdIdInt}",
            null,
            $record,
            null,
            $this->projectId
        );
    }
}
