<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication;

use DateTime;
use DynamicDataPull;

class CacheEntry
{

    public $md_id;

    public $project_id;

    public $event_id;
    
    public $record;

    public $field_name;

    public $strategy;

    public $redcap_value;

    public $temporal_field;

    public $temporal_value;

    public $offset_seconds;

    public $adjudicated;

    public $exclude;
    
    public $source_timestamp;

    public $source_value;
    
    public $source_value2;

    const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    private $value;
    
    private $dateTime;

    
    /**
    * Auto Adjudicate data in CDP projects
    *
    * @param array $db_row
    */
    public function __construct($db_row)
    {
        $this->project_id = intval($db_row['project_id'] ?? 0);
        $this->md_id = intval($db_row['md_id'] ?? 0);
        $this->event_id = intval($db_row['event_id'] ?? 0);
        $this->record = $db_row['record'] ?? '';
        $this->field_name = $db_row['field_name'] ?? '';
        $this->strategy = $db_row['strategy'] ?? '';
        $this->redcap_value = $db_row['redcap_value'] ?? '';
        $this->temporal_field = $db_row['temporal_field'] ?? '';
        $this->temporal_value = $db_row['temporal_value'] ?? '';
        $this->offset_seconds = (isset($db_row['offset_seconds'])) ? intval($db_row['offset_seconds'] ?? 0) : false;
        $this->adjudicated = boolval($db_row['adjudicated'] ?? 0);
        $this->exclude = boolval($db_row['exclude'] ?? 0);
        $this->source_timestamp = $db_row['source_timestamp'] ?? '';
        $this->source_value = $db_row['source_value'] ?? '';
        $this->source_value2 = $db_row['source_value2'] ?? '';
    }

    public function getValue()
    {
        if(!isset($this->value)) {
            $use_mcrypt = $this->source_value2=='';
            $encrypted_data = $use_mcrypt ? $this->source_value : $this->source_value2;
            $this->value = decrypt($encrypted_data, DynamicDataPull::DDP_ENCRYPTION_KEY, $use_mcrypt);
          }
          return $this->value;
    }

    public function getDateTime() {
        if(!isset($this->dateTime)) {
            if(!($this->source_timestamp)) $this->dateTime = false;
            else $this->dateTime = DateTime::createFromFormat(self::TIMESTAMP_FORMAT, $this->source_timestamp);
        }
        return $this->dateTime;
    }

    /**
     * check if the entry is temporal
     *
     * @return bool
     */
    public function isTemporal():bool {
        $dateTime = $this->getDateTime();
        return ( ($dateTime instanceof DateTime) || !(empty($this->temporal_field)) );
    }

    public function isEmpty() { return $this->getValue()===''; }

    /**
     * get a REDCap compatible structure for a record with the field
     * connected to this entry
     *
     * @return array
     */
    public function getRecord() {
        $recordID = $this->record;
        $eventID = $this->event_id;
        $fieldName = $this->field_name;
        $value = $this->getValue();
        $data = [
            $recordID => [
                $eventID => [
                    $fieldName => $value,
                ],
            ]
        ];
        return $data;
    }


    /**
     * get the query that will list all cached entries in a the database.
     * this will be used as subquery: apply project ID
     * 
     * @return string
     */
    public static function getCacheEntriesQuery($project_id, &$params=[]) {
        $queryString = "SELECT cache.*,
            records.project_id, records.record,
            mapping.field_name, mapping.temporal_field, mapping.event_id, mapping.preselect AS strategy,
            TIMESTAMPDIFF( SECOND, cache.source_timestamp, temporal_data.value ) AS offset_seconds,
            temporal_data.value AS temporal_value,
            data.value AS redcap_value
            FROM redcap_ddp_records_data AS cache
            LEFT JOIN redcap_ddp_mapping AS mapping ON cache.map_id=mapping.map_id
            LEFT JOIN redcap_ddp_records AS records ON cache.mr_id=records.mr_id
            LEFT JOIN ".\Records::getDataTable($project_id)." AS data ON (data.project_id=records.project_id AND data.record=records.record AND data.event_id=mapping.event_id AND data.field_name=mapping.field_name)
            LEFT JOIN ".\Records::getDataTable($project_id)." AS temporal_data ON (temporal_data.project_id=records.project_id AND temporal_data.record=records.record AND temporal_data.event_id=mapping.event_id AND temporal_data.field_name=mapping.temporal_field)
            WHERE records.project_id = ?
            ORDER BY records.project_id, mapping.event_id, CAST(records.record AS unsigned), records.record, mapping.field_name";
        $params[] = $project_id;
        return $queryString;
    }
}
