<?php

namespace Vanderbilt\REDCap\Classes\Utility\REDCapData;

use Records;

class QueryBuilder {
    private $project;
    private $params = [];
    private $conditionBuilder;

    public function __construct($project) {
        $this->project = $project;
    }

    /**
     * Apply conditions to the query.
     */
    public function applyConditions(ConditionBuilder $conditionBuilder) {
        $this->conditionBuilder = $conditionBuilder;
        return $this;
    }

    /**
     * Build the final SQL query.
     */
    public function build() {
        $data_table = Records::getDataTable($this->project->project_id);
        $sql = "SELECT `record`, `event_id`, `instance`, `field_name`, `value`
                FROM $data_table
                WHERE `project_id` = ?";

        $params = [$this->project->project_id];

        // Apply conditions
        if($this->conditionBuilder instanceof ConditionBuilder) {
            $conditionResult = $this->conditionBuilder->build();
            if (!empty($conditionResult['clause'])) {
                $sql .= ' AND (' . $conditionResult['clause'] . ')';
                $params = array_merge($params, $conditionResult['params']);
            }
        }

        // Order the results
        $sql .= " ORDER BY CAST(`record` AS UNSIGNED) ASC, `event_id` ASC, `instance` ASC";

        // Set the params for later use
        $this->params = $params;

        return $sql;
    }

    /* public function buildCountQuery() {
        $data_table = Records::getDataTable($this->project->project_id);
        $sql = "SELECT COUNT(DISTINCT record, event_id) AS total
                FROM $data_table
                WHERE project_id = ?";
    
        $params = [$this->project->project_id];
    
        // Apply record filters
        if (!empty($this->recordIds)) {
            $placeholders = implode(', ', array_fill(0, count($this->recordIds), '?'));
            $sql .= " AND record IN ($placeholders)";
            $params = array_merge($params, $this->recordIds);
        }
    
        // Apply event filters
        if (!empty($this->eventIds)) {
            $placeholders = implode(', ', array_fill(0, count($this->eventIds), '?'));
            $sql .= " AND event_id IN ($placeholders)";
            $params = array_merge($params, $this->eventIds);
        }
    
        // Apply conditions
        $conditionResult = $this->conditionBuilder->build();
        if (!empty($conditionResult['clause'])) {
            $sql .= ' AND (' . $conditionResult['clause'] . ')';
            $params = array_merge($params, $conditionResult['params']);
        }
    
        // Apply field filters
        if (!empty($this->fields)) {
            $fieldPlaceholders = implode(', ', array_fill(0, count($this->fields), '?'));
            $sql .= " AND field_name IN ($fieldPlaceholders)";
            $params = array_merge($params, $this->fields);
        }
    
        return ['sql' => $sql, 'params' => $params];
    } */
    

    /**
     * Get the parameters for the query.
     */
    public function getParams() {
        return $this->params;
    }
}
