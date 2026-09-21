<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Utility;

use Project;

class InstanceSeeker
{
    private $project;
    private $project_id;
    private $event_id;
    /**
     * list of all fields in the form
     * specified in the settings
     *
     * @var array
     */
    private $form_fields;

    public static function unit_separator() { return chr(31); }
    public static function record_separator() { return chr(30); }

    /**
     *
     * @param Project $project
     * @param int $event_id
     * @param string $form_name
     */
    public function __construct($project, $form_name, $event_id=null)
    {
        $this->project = $project;
        $this->project_id = $project->project_id;
        $event_id = $event_id ?: $project->firstEventId; // default to the first event of the project
        $this->event_id = $event_id;
        if(!array_key_exists($form_name, $this->project->forms))$this->project->forms[$form_name] = [];
        $form_fields_data = $this->project->forms[$form_name]['fields'] ?? [];
        $this->form_fields = array_keys($form_fields_data);
    }

    /**
     * get the record ID for a specified field_name
     *
     * @param string $key
     * @param mixed $value
     * @return string|null
     */
    static function getRecordId($project_id, $event_id, $key, $value)
    {
      $query_string = sprintf(
        "SELECT record FROM ".\Records::getDataTable($project_id)."
        WHERE `project_id` = %u
        AND `event_id` = %u
        AND `field_name`=%s AND `value`=%s",
        $project_id, $event_id,
        checkNull($key), checkNull($value)
      );
      $result = db_query($query_string);
      if($row = db_fetch_assoc($result)) {
        return $row['record'] ?? null;
      }
      return;
    }


    /**
     * get the query to rotate data for a REDCap form
     * and a specific record and fields
     *
     * @return string
     */
    private function getPivotRotationSubQuery($record, $fields)
    {
      // helper to perform a table rotation
      $getPivotRotation = function($fields, $unit_separator) {
        // GROUP_CONCAT(CASE WHEN field_name = 'vitals_label' THEN value ELSE NULL END) AS vitals_label,
        $cases = [];
        foreach ($fields as $field) {
                $cases[] = sprintf("GROUP_CONCAT(CASE WHEN `field_name` = '%s' THEN value ELSE NULL END ORDER BY `value` ASC SEPARATOR '%s') AS `%s`", $field, $unit_separator, $field);
        }
        return implode(", \n", $cases);
      };

      $project_id = $this->project_id;
      $event_id = $this->event_id;

      $pivot = $getPivotRotation($fields, self::unit_separator());
      $query_string = sprintf(
        "SELECT `record`, IFNULL(instance, 1) `normalized_instance`,
            %s
        FROM ".\Records::getDataTable($project_id)." 
        WHERE `project_id` = %u
        AND `event_id` = %u
        AND `record`= %s
        GROUP BY record, normalized_instance
        ORDER BY record, normalized_instance",
        $pivot,
        $project_id, $event_id, checkNull($record)
      );
      return $query_string;
    }

    /**
     * find if a record contains an instance matching
     * the data.
     * return the instance number or null if nothing
     * is found.
     *
     * @param mixed $record
     * @param array $data
     * @param array $fields
     * @return int|null
     */
    public function findMatches($record, $data, $fields=[])
    {      
      $buildWhereClause = function($data) {
        $wheres = array_map(function($key, $value) {
          return sprintf("`%s`<=>%s", $key, checkNull($value));
        }, array_keys($data), $data);
        return implode(' AND ', $wheres);
      };

      // only check provided fields
      $valid_data = array_intersect_key($data, array_flip($fields));

      // $fields_list = DatabaseQueryHelper::getQueryList($fields);
      $subQuery = $this->getPivotRotationSubQuery($record, array_keys($valid_data));
      $whereClause = $buildWhereClause($valid_data);

      $query_string = sprintf(
        "SELECT `normalized_instance` FROM (
          %s
        ) AS pivot
        WHERE %s",
        $subQuery,
        $whereClause
      );
      // Logging::writeToFile('findMatches.txt', $query_string);
      $result = db_query($query_string);
      $total_matches = db_num_rows($result);

      if($row=db_fetch_assoc($result)) {
        // return the first valid match
        return $row['normalized_instance'] ?? false;
      }
      return false;
    }


    /**
     * create a query compatible list of elements
     *
     * @param array $items
     * @return string
     */
    private static function getQueryList($items) {
      $applyCheckull = function(&$value, $index) {
          $value = checkNull($value);
      };
      array_walk($items, $applyCheckull);
      return implode(',', $items);
    }


    /**
     * get a list of fields for a REDCap form
     * that do not match those in the provided data
     *
     * @param string $record_id
     * @param array $data [key=>value]
     * @return array
     */
    public function getNonMatchingFields($record_id, $data) {
      // make a common table expression (CTE) that represents all the possible field names
      $makeFieldNamesCTE = function($fieldNames=[]) {
        $cte = '';
        if(count($fieldNames)<1) return $cte;
        $currentField = current($fieldNames);
        $cte .= "SELECT '$currentField' AS field_name".PHP_EOL;
        while($currentField = next($fieldNames)) {
          $cte .= " UNION ALL SELECT '$currentField'".PHP_EOL;
        }
        return $cte;
      };
      $makeREDCapDataCTE = function($field_list) {
        // only check data with keys that fit the form
        $tableName = \Records::getDataTable($this->project_id);
        $dataCTE = sprintf(
          "SELECT field_name, value, record
          FROM $tableName WHERE project_id=? AND event_id=? AND record=?
          AND `field_name` IN (%s)", $field_list
        );
        return $dataCTE;
      };

      $validData = array_intersect_key($data, array_flip($this->form_fields));
      if(empty($validData)) return [];
      $field_list = self::getQueryList(array_keys($validData));

      $fieldNamesCTE = $makeFieldNamesCTE($this->form_fields);
      $redcapDataCTE = $makeREDCapDataCTE($field_list);
      
      $queryString = sprintf(
        "SELECT f.field_name, IFNULL(rd.value, '') AS value
          FROM (
            %s
          ) AS f
          LEFT JOIN (
            %s
          ) AS rd ON f.field_name = rd.field_name",
        $fieldNamesCTE, $redcapDataCTE
      );
      $params = [$this->project_id, $this->event_id, $record_id]; // applied to redcapDataCTE

      $result = db_query($queryString, $params);

      // Logging::writeToFile('instanceSeekerGetNonMatchingFields.txt', $query_string);
      $differentFields = array_keys($validData);
      while($row = db_fetch_assoc($result)) {
        $fieldName = $row['field_name'] ?? '';
        $index = array_search($fieldName, $differentFields);
        if($index===false) continue;
        $dbValue = $row['value'] ?? '';
        $newValue = $validData[$fieldName] ?? '';
        if($dbValue==$newValue) unset($differentFields[$index]);
      }
      return $differentFields;
    }

    /**
     * get the next available instance number for a form
     *
     * @param mixed $record_id
     * @return int
     */
    function getAutoInstanceNumber($record_id)
    {
      $project_id = $this->project_id;
      $event_id = $this->event_id;

      $field_list = self::getQueryList($this->form_fields);

      $tableName = \Records::getDataTable($project_id);
      $query_string = sprintf(
        "SELECT COALESCE(MAX(IFNULL(instance,1)),0)+1 AS next_instance
        FROM $tableName WHERE
        `project_id` = ?
        AND `event_id` = ?
        AND `record`= ?
        AND `field_name` IN (%s)", $field_list
      );
      $result = db_query($query_string, [$project_id, $event_id, $record_id]);
      if($row=db_fetch_assoc($result)) {
          $next_instance = $row['next_instance'] ?? 1;
          return intval($next_instance);
      }
      throw new \Exception("Error finding the next instance number in project {$this->project_id}, record {$record_id}", 1);
    }

 }