<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull;

use Records;
use Vanderbilt\REDCap\Classes\Traits\PaginationTrait;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\DTOs\CachedEntryDTO;

class CachedDataRetriever {
    use PaginationTrait;

    protected $project_id;
    protected $config = [];

    public function __construct($project_id, $config = []) {
        $this->project_id = $project_id;
        $this->config = array_merge([
            'default_page' => 1,
            'default_per_page' => 10
        ], $config);
    }

    public function getCachedData($record_id = null, $page = null, $perPage = null, &$metadata = null) {
        $page = $page ?: $this->config['default_page'];
        $perPage = $perPage ?: $this->config['default_per_page'];
        $params = [$this->project_id, $this->project_id];
        if($record_id) array_push($params, $record_id);
        $metadataParams = $params;
        
        // Base SQL query without LIMIT and OFFSET
        $baseQuery = "SELECT 
                        mapping.map_id,
                        IFNULL(mapping.is_record_identifier, 0) AS is_record_identifier,
                        mapping.project_id,
                        mapping.event_id,
                        records.record,
                        mapping.field_name,
                        mapping.external_source_field_name,
                        mapping.temporal_field,
                        mapping.preselect AS preselect_strategy,
                        metadata1.form_menu_description AS form_description,
                        metadata.field_order,
                        metadata.form_name,
                        metadata.element_label,
                        metadata.element_type,
                        metadata.element_enum,
                        metadata.element_note,
                        metadata.element_validation_checktype,
                        metadata.element_validation_type,
                        metadata.element_validation_min,
                        metadata.element_validation_max,
                        records_data.md_id,
                        records_data.source_timestamp,
                        records_data.source_value,
                        records_data.source_value2,
                        records_data.adjudicated,
                        records_data.exclude
                    FROM 
                        redcap_ddp_mapping AS mapping
                    LEFT JOIN redcap_metadata AS metadata ON metadata.project_id = mapping.project_id AND metadata.field_name = mapping.field_name
                    LEFT JOIN (
                        SELECT form_menu_description, form_name 
                        FROM redcap_metadata 
                        WHERE project_id = ? AND form_menu_description IS NOT NULL
                    ) AS metadata1 ON metadata1.form_name = metadata.form_name
                    LEFT JOIN redcap_ddp_records AS records ON records.project_id = mapping.project_id
                    LEFT JOIN redcap_ddp_records_data AS records_data ON records_data.map_id = mapping.map_id AND records_data.mr_id = records.mr_id
                    WHERE mapping.project_id = ? AND records.record IS NOT NULL
                    AND (
                        records_data.source_value IS NOT NULL OR
                        records_data.source_value2 IS NOT NULL
                    )".
                    ($record_id ? " AND records.record = ?" : "").
                    " ORDER BY
                        mapping.project_id,
                        mapping.event_id," .
                        Records::getCustomOrderClause('records.record') . ",
                        metadata.form_name,
                        metadata.field_order,
                        mapping.map_id";
        
        // Apply pagination
        $limitSql = $this->applyPagination($page, $perPage, $params);
        
        // Final SQL query with LIMIT and OFFSET
        $sql = $baseQuery . $limitSql;
        $q = db_query($sql, $params);
        
        $cachedData = [];
        while ($row = db_fetch_assoc($q)) {
            $cachedData[] = new CachedEntryDTO($row);
        }

        // Populate metadata
        $this->populateMetadata($baseQuery, $metadataParams, $page, $perPage, $metadata);

        return $cachedData;
    }

    public function deleteCache($md_ids = []) {
        $params = [$this->project_id];
    
        // Base DELETE query
        $sql = "DELETE FROM redcap_ddp_records_data 
                WHERE md_id IN (
                    SELECT records_data.md_id 
                    FROM redcap_ddp_records_data AS records_data
                    INNER JOIN redcap_ddp_records AS records 
                    ON records_data.mr_id = records.mr_id
                    WHERE records.project_id = ?";
    
        // Add condition for specific md_ids if provided
        if (!empty($md_ids)) {
            $placeholders = implode(',', array_fill(0, count($md_ids), '?'));
            $sql .= " AND records_data.md_id IN ($placeholders)";
            $params = array_merge($params, $md_ids);
        }
    
        $sql .= ")";
    
        // Execute the DELETE query
        $result = db_query($sql, $params);
    
        // Return success or failure
        return $result ? db_affected_rows() : false;
    }
    
    

}
