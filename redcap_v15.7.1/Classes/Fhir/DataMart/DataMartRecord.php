<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart;

/**
 * this clas contains logic related to the record level settings
 * in the "Project Settings" form of a Data Mart project
 */
class DataMartRecord
{

     /**
     * datetime in FHIR compatible format
     * https://www.hl7.org/fhir/datatypes.html#dateTime
     */
    const FHIR_DATETIME_FORMAT = "Y-m-d\TH:i:s\Z";
    
    private $project_id;
    
    private $mrn;

    /**
     *
     * @param integer $project_id
     * @param string $mrn
     */
    function __construct($project_id, $mrn)
    {
        $this->project_id = $project_id;
        $this->mrn = $mrn;
    }

    /**
     * retrieve date range settings for the specified MRN from the database.
     * This date range filters the temporal data (labs, vitals...) fetched from the EHR endpoints.
     *
     * @return object|false return an array of settings or false if no settings ar found
     */
    public function getDateRange()
    {
        $us = chr(31); // unit_separator
        $query_string = sprintf(
            "SELECT `record`,
            GROUP_CONCAT(CASE WHEN `field_name` = 'mrn' THEN value ELSE NULL END ORDER BY `value` ASC SEPARATOR '{$us}') AS `mrn`,
            GROUP_CONCAT(CASE WHEN `field_name` = 'date_min' THEN value ELSE NULL END ORDER BY `value` ASC SEPARATOR '{$us}') AS `date_min`,
            GROUP_CONCAT(CASE WHEN `field_name` = 'date_max' THEN value ELSE NULL END ORDER BY `value` ASC SEPARATOR '{$us}') AS `date_max`
            FROM ".\Records::getDataTable($this->project_id)."
            WHERE `project_id`=%u
            AND `field_name` IN ('mrn','date_min','date_max')
            GROUP BY `record`
            HAVING (`mrn`<=>%s)",
            $this->project_id,
            checkNull($this->mrn)
        );
        $result = db_query($query_string);
        if($row = db_fetch_assoc($result)) {
            $date_min = $row['date_min'] ?? '';
            $date_max = $row['date_max'] ?? '';
            if(!empty($date_min)) $date_min = $this->getFhirDate($date_min);
            if(!empty($date_max)) $date_max = $this->getFhirDate($date_max);
            if(empty($date_min) && empty($date_max)) return false;
            return compact('date_min', 'date_max');
        }
        return false;
    }

    /**
     * check if the specified date range for fetching data
     * is valid for a specific MRN
     *
     * @param string $mrn
     * @return boolean
     */
    public function isDateRangeValid()
    {
        $now = new \DateTime();
        $formatted_now = $now->format('Y-m-d H:i');
        $us = chr(31); // unit_separator
        $query_string = sprintf(
            "SELECT `record`,
            GROUP_CONCAT(CASE WHEN `field_name` = 'mrn' THEN value ELSE NULL END ORDER BY `value` ASC SEPARATOR '{$us}') AS `mrn`,
            GROUP_CONCAT(CASE WHEN `field_name` = 'fetch_date_start' THEN value ELSE NULL END ORDER BY `value` ASC SEPARATOR '{$us}') AS `fetch_date_start`,
            GROUP_CONCAT(CASE WHEN `field_name` = 'fetch_date_end' THEN value ELSE NULL END ORDER BY `value` ASC SEPARATOR '{$us}') AS `fetch_date_end`
            FROM ".\Records::getDataTable($this->project_id)."
            WHERE `project_id`=%u
            AND `field_name` IN ('mrn','fetch_date_start','fetch_date_end')
            GROUP BY `record`
            HAVING (
                `mrn`<=>%s AND
                (`fetch_date_start` IS NULL OR `fetch_date_start` < %s) AND
                (`fetch_date_end` IS NULL OR `fetch_date_end` > %s)
            )",
            $this->project_id,
            checkNull($this->mrn),
            checkNull($formatted_now), checkNull($formatted_now)
        );

        $result = db_query($query_string);
        if(!$result) return false;
        $row = db_fetch_assoc($result);
        return !empty($row);
    }

    /**
     * get a datetime compatible with the FHIR standard
     *
     * @param string $date_string
     * @return DateTime
     */
    private function getFhirDate($date_string)
    {
        $datetime = new \DateTime($date_string);
        return $datetime; //->format(self::FHIR_DATETIME_FORMAT);
    }

}