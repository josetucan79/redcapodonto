<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart;

use REDCap;
use Logging;
use JsonSerializable;
use Vanderbilt\REDCap\Classes\Fhir\FhirUser;
use Vanderbilt\REDCap\Classes\Fhir\Logs\FhirLogger;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMapping;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;

/**
 * Model of the DataMart revision that is saved on the database
 * 
 * exposed properties:
 * @property integer $id The primary key for the model
 * @property integer $user_id ID of the user creating the revision
 * @property integer $project_id ID of the project associated to this revision
 * @property integer $request_id ID of the request associated to this revision
 * @property string $request_status status of the request (if applicable)
 * @property array $mrns list of MRN numbers 
 * @property \DateTime|string $date_min minimum date for temporal data
 * @property \DateTime|string $date_max maximum date for temporal data
 * @property array $fields list of fields to use when fetching data
 * @property array $date_range_categories list of categories that will use the date range restriction
 * @property boolean $approved the revision has been approved by an administrator
 * @property \DateTime $created_at creation date
 * @property \DateTime $executed_at date of first execution
 */
class DataMartRevision implements JsonSerializable
{

    /**
     * datetime in FHIR compatible format
     * https://www.hl7.org/fhir/datatypes.html#dateTime
     */
    const FHIR_DATETIME_FORMAT = "Y-m-d\TH:i:s\Z";

    /**
     * The primary key for the model
     *
     * @var int
     */
    private $id;

    /**
     * ID of the project associated to this revision
     *
     * @var integer
     */
    private $project_id;

    /**
     * ID of the user creating the revision
     *
     * @var int
     */
    private $user_id;
    
    /**
     * ID of the request associated to this revision
     *
     * @var integer
     */
    private $request_id;

    /**
     * status of the request (if applicable)
     *
     * @var string
     */
    private $request_status;

    /**
     * list of MRN numbers 
     *
     * @var array
     */
    private $mrns = array();

    /**
     * minimum date for temporal data
     *
     * @var \DateTime|string
     */
    private $date_min;

    /**
     * maximum date for temporal data
     *
     * @var \DateTime|string
     */
    private $date_max;

    /**
     * list of fields to use when fetching data
     *
     * @var array
     */
    private $fields = array();

    /**
     * list of categories that apply the date range
     *
     * @var array
     */
    private $date_range_categories = array();

    /**
     * the revision has been approved by an administrator
     *
     * @var boolean
     */
    private $approved = false;

    /**
     * creation date
     *
     * @var \DateTime
     */
    private $created_at;

    /**
     * date of first execution
     *
     * @var \DateTime
     */
    private $executed_at;

    /**
     * list of the instance variables that are public  for reading
     *
     * @var array
     */
    private static $readable_variables = array(
        'id',
        'project_id',
        'request_id',
        'user_id',
        'mrns',
        'date_min',
        'date_max',
        'fields',
        'date_range_categories',
        'approved',
        'created_at',
        'executed_at',
        'request_status',
    );

    /**
     * list of keys that can be provided in constructor
     *
     * @var array
     */
    private static $constructor_keys = array(
        'id',
        'project_id',
        'request_id',
        'user_id',
        'fields',
        'date_range_categories',
        'date_min',
        'date_max',
        'mrns',
        'approved',
        'created_at',
        'executed_at',
    );

    private static $table_name = 'redcap_ehr_datamart_revisions';
    private static $request_table_name = 'redcap_todo_list';

    /**
     * fields in the revisions table
     * used to build the update query for the database
     *
     * @var array
     */
    private static $fillable = array(
        'project_id',
        'request_id',
        // 'user_id',
        'mrns',
        'date_min',
        'date_max',
        'fields',
        'approved',
        'created_at',
        'executed_at',
    );

    private static $string_delimiter = "\n";
    private static $dateTimeFormat = 'Y-m-d H:i:s';
    private static $mandatory_fields = array(
        'project_id|request_id', //project_id OR request_id must be present
        'user_id',
        // 'mrns',
        'fields',
    );

    /**
     * constructor
     *
     * @param array $params an array with any value listed in self::$constructor_keys
     */
    function __construct($params=array())
    {
        try {
            $this->checkRequirements($params);
            // cycle through the permitetd constructor keys
            foreach (self::$constructor_keys as $key) {
                if(array_key_exists($key, $params)) $this->set($key, $params[$key]);
            }
        } catch (\Exception $e) {
            $messages = array(
                'Error instantianting the revision.',
                $e->getMessage(),
            );
            throw new \Exception(implode("\n", $messages));
        }
    }

    /** GETTERS */
    public function getId() { return $this->id; }
    public function getUserId() { return $this->user_id; }
    public function getProjectId() { return $this->project_id; }
    public function getRequestId() { return $this->request_id; }
    public function getRequestStatus() { return $this->request_status; }
    public function getMrns() { return $this->mrns; }
    public function getDateMin() { return $this->date_min; }
    public function getDateMax() { return $this->date_max; }
    public function getFields() { return $this->fields; }
    public function getDateRangeCategories() { return $this->date_range_categories; }
    public function getApproved() { return $this->approved; }
    public function getCreatedAt() { return $this->created_at; }
    public function getExecutedAt() { return $this->executed_at; }

    /**
     *
     * @return FhirUser
     */
    public function getCurrentUser()
    {
        global $userid;
        $user_id = \User::getUIIDByUsername($userid); // get current user
        $project_id = $this->project_id;
        $fhir_user = new FhirUser($user_id, $project_id);
        return $fhir_user;
    }

    /**
     * check minimum requirements from revision creation
     *
     * @param array $params
     * @return void
     */
    private function checkRequirements($params)
    {
        foreach (self::$mandatory_fields as $field) {
            $valid = false;
            foreach (array_keys($params) as $key) {
                preg_match("/^{$field}$/", $key, $matches);
                $valid = !empty($matches);
                if($valid) break;
            }
            if(!$valid)
                throw new \Exception("Mandatory field '{$field}' is missing.", 1);
        }
    }

    /**
     * get Data Mart settings for a project
     * the settings are divided in revisions
     *
     * @param int $project_id
     * 
     * @return array list of DataMartRevision
     */
    public static function all($project_id)
    {
        $select_query = self::getSelectQuery();
        $order_by_query_clause = self::getOrderByQueryClause();
        $query = $select_query . " AND r.project_id = ? " . $order_by_query_clause;
        $params = [$project_id];

        $result = db_query($query, $params);
        //print_array($query);

        if (!$result) return [];

        $revisions = [];
        while ($data = db_fetch_array($result))
        {
            $revision = new self($data);
            $revisions[] = $revision;
        }

        return $revisions;
    }

    /**
     * return the active revision for a project
     * the revision must be approved and not soft-deleted
     *
     * @param integer $project_id
     * @return DataMartRevision|false
     */
    public static function getActive($project_id)
    {
        $tableName = self::$table_name;
        $query_string = "SELECT id
            FROM  $tableName
            WHERE project_id=? AND is_deleted!=1
            ORDER BY created_at DESC, id DESC
            LIMIT 1";
        $result = db_query($query_string, [$project_id]);
        if($result && $row = db_fetch_assoc($result))
        {
            $revision_id = $row['id'];
            return self::get($revision_id);
        }
        return false;
    }

    /**
     * check if a revision is active
     *
     * @throws \Exception
     * @return boolean
     */
    public function isActive()
    {
        $project_id = $this->project_id;
        $active_revision = self::getActive($project_id);
        if(!$active_revision) throw new \Exception("There are no active revisions for this project", 400);
        // check if the revision we are trying to run is the active one
        if($this->id !== $active_revision->id) throw new \Exception("This is not the active revision for this project", 400);
        return true;
    }

    /**
     * get a revision from the database using the ID
     *
     * @param int $id
     * @return DataMartRevision|false
     */
    public static function get($id)
    {
        $select_query = self::getSelectQuery();
        $order_by_query_clause = self::getOrderByQueryClause();
        $query_string = $select_query." AND r.id=? ".$order_by_query_clause;

        $result = db_query($query_string, [$id]);
        if($result && $params=db_fetch_assoc($result)) return new self($params);
        else return false;
    }

    /**
     * create a revision
     *
     * @param array $settings
     * 
     * @return DataMartRevision
     */
    public static function create($settings)
    {
        try {
            $revision = new self($settings);
            return $revision->save();
        } catch (\Throwable $th) {
            $message = 'There was an error saving the revision - '
                . $th->getMessage()
                . ' - '
                . $th->getCode();
            $code = $th->getCode();
            $code = ($code >= 400) ?: 400; // make sure it is at least a 400 error
            throw new \Exception($message, $code);
            
        }
    }

    /**
     * persist a revision to the database
     * 
     * @throws Exception if the revision can not be saved
     *
     * @return DataMartRevision
     */
    public function save()
    {
        $new_instance = empty($this->id); //check if we are creating a new instance
        $queryString = null;
        $queryParams = [];
        if($new_instance) {
            $this->set('created_at', NOW); // set the creation date using PHP time
            $queryString = $this->getInsertQuery($queryParams);
        }else {
            $queryString = $this->getUpdateQuery($queryParams);
        }
        if($result = db_query($queryString, $queryParams)) {
            if($id=db_insert_id()) $this->id = $id; // set the revision ID if inserting
            $log_message = ($new_instance===true) ? 'Create Clinical Data Mart revision' : 'Update Clinical Data Mart revision';
            \Logging::logEvent($queryString, "redcap_ehr_datamart_revisions", "MANAGE", $this->project_id, sprintf("revision_id = %u", $this->id), $log_message);
            return self::get($this->id); // get the revision from the database
        }else {
            throw new \Exception("Could not save the revision to the database",1);
        }
    }

    /**
     * set the exectuted_at property of a revision
     *
     * @param string $time
     * @return DataMartRevision
     */
    public function setExecutionTime($time=null)
    {
        $time = $time ? $time : NOW;
        return $this->set('executed_at', $time);
    }

    /**
     * set the request_id property of a revision
     *
     * @param string $request_id
     * @return DataMartRevision
     */
    public function setRequestId($request_id)
    {
        return $this->set('request_id', $request_id);
    }

    /**
     * set the project_id property of a revision
     *
     * @param string $project_id
     * @return DataMartRevision
     */
    public function setProjectId($project_id)
    {
        return $this->set('project_id', $project_id);
    }

    /**
     * approve a revision
     * 
     * @return DataMartRevision
     */
    public function approve()
    {
        $revision = $this->set('approved', true);
        // create empty fields for each mrn in this revision which is not already available in the project
        $revision->createRecords();
        return $revision;
    }

    /**
     * delete the revision
     * defaults to a soft delete
     *
     * @param boolean $soft_delete
     * @throws Exception if no revision can be returned
     * 
     * @return DataMartRevision
     */
    public function delete($soft_delete=true)
    {
        $params = [];
        $tableName = self::$table_name;
        if($soft_delete==true)
        {
            $query_string = "UPDATE `$tableName` SET is_deleted=1 WHERE id= ?";
            $params[] = $this->id;
        }else
        {
            $query_string = "DELETE FROM `$tableName` WHERE id= ?";
            $params[] = $this->id;
        }
        
        // check if query is successful and the $id is valid
        if($result = db_query($query_string, $params))
        {
            \Logging::logEvent($query_string,"redcap_ehr_datamart_revisions","MANAGE",$this->project_id,sprintf("revision_id = %u",$this->id),'Delete Clinical Data Mart revision');
            return true;
        }else
        {
            throw new \Exception("Could't delete the revision from the database",1);
        }
    }

    /**
     * get a query string to UPDATE a Revision on the database 
     *
     * @param array $queryParams
     * @return string
     */
    private function getUpdateQuery(&$queryParams)
    {
        $dbFormatted = $this->toDatabaseFormat();
        $query_string = sprintf("UPDATE %s", self::$table_name);
        $set_fields = [];
        $queryParams = [];
        foreach (self::$fillable as $key)
        {
            if(!empty($dbFormatted->{$key})) {
                $queryParams[] = $dbFormatted->{$key};
                $set_fields[] = sprintf( "`%s`= ?", $key );
            }
        }
        $query_string .= "\nSET ".implode(', ', $set_fields);
        $query_string .= "\nWHERE id=?";
        $queryParams[] = $dbFormatted->id;
        return $query_string;
    }

    /**
     * get a query string to INSERT into the database a new Revision
     *
     * @param array $queryParams
     * @return string
     */
    private function getInsertQuery(&$queryParams)
    {
        $dbFormatted = $this->toDatabaseFormat();
        $query_fields = array(
            'user_id' => $dbFormatted->user_id,
            'mrns' => $dbFormatted->mrns,
            'date_min' => $dbFormatted->date_min,
            'date_max' => $dbFormatted->date_max,
            'date_range_categories' => $dbFormatted->date_range_categories,
            'fields' => $dbFormatted->fields,
            'approved' => $dbFormatted->approved,
            'created_at' => $dbFormatted->created_at,
        );
        if($project_id = $dbFormatted->project_id) $query_fields['project_id'] = $project_id;
        if($request_id = $dbFormatted->request_id) $query_fields['request_id'] = $request_id;
        $keys = array_keys($query_fields);
        $queryParams = array_values($query_fields);
        $keysList = implode(', ', array_map(function($key){return sprintf("`%s`", $key);}, $keys));
        $valuesList = implode(', ', array_fill(0, count($queryParams), '?'));

        $query_string = sprintf("INSERT INTO %s", self::$table_name);
        $query_string .= "\n( $keysList )\nVALUES( $valuesList )";
        return $query_string;
    }

    /**
     * get the SELECT query for the revisions
     * select only the revisions that have not been marked as deleted
     *
     * @return string
     */
    private static function getSelectQuery()
    {
        return "SELECT r.*, t.status AS request_status FROM redcap_ehr_datamart_revisions AS r
                LEFT JOIN redcap_todo_list AS t ON r.request_id=t.request_id
                WHERE is_deleted != 1";
    }

    /**
     * get the ORDER BY clause query for the revisions
     *
     * @return string
     */
    private static function getOrderByQueryClause()
    {
        return "ORDER BY created_at ASC";
    }

    /**
     * get a revision from the database using the request_id
     *
     * @param int $request_id
     * @return DataMartRevision|false
     */
    public static function getRevisionFromRequest($request_id)
    {
        $select_query = self::getSelectQuery();
        $order_by_query_clause = self::getOrderByQueryClause();
        $query_string = $select_query . " AND r.request_id=? " . $order_by_query_clause;

        $result = db_query($query_string, [$request_id]);
        if ($result && $params = db_fetch_assoc($result)) {
            return new self($params);
        } else {
            return false;
        }
    }


    /**
     * get a range of dates compatible with the FHIR specifiction
     *
     * @return array
     */
    public function getFHIRDateRange()
    {
        $date_min = $this->date_min;
        $date_max = $this->date_max;
        // check if $date_max is in the future
        if( !empty($date_max) && $date_max->getTimestamp() >= time() ) $date_max = '';
        if( !empty($date_min) && !empty($date_max) && $date_min > $date_max)
        {
            // If min is bigger than max, then simply swap them
            $temp_max = $date_max;
            $date_max = $date_min;
            $date_min = $temp_max;
        }
        // Reformat dates for temporal window
        if( !empty($date_min) ) $date_min = $date_min->setTime(0, 0, 0); //->format(self::FHIR_DATETIME_FORMAT);
        if( !empty($date_max) ) $date_max = $date_max->setTime(23, 59, 59); //->format(self::FHIR_DATETIME_FORMAT);

        return array(
            'date_min' => $date_min,
            'date_max' => $date_max,
        );
    }

    /**
     * get a normalized array of mapped fields
     * along with date range suitable for
     * fetching and saving FHIR data
     *
     * @param string $mrn
     * @return array
     */
    public function getNormalizedMapping($mrn)
    {
        $dateRange = $this->getTemporalDataDateRangeForMrn($mrn);
        list($date_min, $date_max) = $dateRange;
        $fields = $this->fields;
        $mapping = [];
        foreach ($fields as $field) {
            // $normalized[] = ['field'=>$field, 'timestamp_min'=>$date_min, 'timestamp_max'=>$date_max];
            $mapping[] = new FhirMapping($field, $date_min, $date_max);
        }
        return $mapping;
    }

    /**
     * return an object in a db compatible format
     *
     * @return object
     */
    public function toDatabaseFormat()
    {
        $date_min = ($this->date_min instanceof \DateTime) ? $this->date_min->format(self::$dateTimeFormat) : null;
        $date_max = ($this->date_max instanceof \DateTime) ? $this->date_max->format(self::$dateTimeFormat) : null;
        $executed_at = ($this->executed_at instanceof \DateTime) ? $this->executed_at->format(self::$dateTimeFormat) : null;
        $created_at = ($this->created_at instanceof \DateTime) ? $this->created_at->format(self::$dateTimeFormat) : null;
        
        $db_format = (object) array(
            'id' => $this->id,
            'project_id' => $this->project_id,
            'request_id' => $this->request_id,
            'user_id' => $this->user_id,
            'mrns' => implode(self::$string_delimiter, $this->mrns),
            'date_min' => $date_min,
            'date_max' => $date_max,
            'fields' => implode(self::$string_delimiter, $this->fields),
            'date_range_categories' => implode(self::$string_delimiter, $this->date_range_categories),
            'approved' => (int)!!$this->approved,
            'executed_at' => $executed_at,
            'created_at' => $created_at,
        );
        // remove null or empty values
        /* foreach ($db_format as $key => $value) {
            if(empty($value)) unset($db_format->{$key});
        } */
        return $db_format;
    }

    /**
     * check if a revision is duplicated
     *
     * @param array $settings
     * @return boolean
     */
    public function isDuplicate($settings)
    {
        $date_min = self::getDate($settings['date_min']);
        $date_max = self::getDate($settings['date_max']);
        $sameSettings = self::compareArrays($this->mrns, $settings['mrns']) &&
                        self::compareArrays($this->fields, $settings['fields']) &&
                        self::compareArrays($this->date_range_categories, $settings['date_range_categories']) &&
                        self::compareDates($this->date_min, $date_min) &&
                        self::compareDates($this->date_max, $date_max);
        return $sameSettings;
    }

    /**
     * show if the revision has already been executed
     *
     * @return boolean
     */
    public function hasBeenExecuted()
    {
        return !empty($this->executed_at);
    }

    /**
     * Constructs the SQL query for fetching data within specified date ranges.
     *
     * @param array &$params An array to hold the parameters for the query, which may already contain values.
     *                       New parameters required for this query will be appended to this array.
     *                       Defaults to an empty array.
     * @return string The constructed SQL query string.
     */
    public function getQueryForFetchableData(&$params=[])
    {
        $now = new \DateTime();
        $formatted_now = $now->format('Y-m-d H:i');
        $us = chr(31); // unit separator

        $data_table = \Records::getDataTable($this->project_id);

        // Build the SQL query with placeholders
        $query_string = "
            SELECT
                GROUP_CONCAT(
                    CASE WHEN `field_name` = 'mrn' THEN `value` ELSE NULL END
                    ORDER BY `value` ASC SEPARATOR '{$us}'
                ) AS `mrn`,
                GROUP_CONCAT(
                    CASE WHEN `field_name` = 'fetch_date_start' THEN `value` ELSE NULL END
                    ORDER BY `value` ASC SEPARATOR '{$us}'
                ) AS `fetch_date_start`,
                GROUP_CONCAT(
                    CASE WHEN `field_name` = 'fetch_date_end' THEN `value` ELSE NULL END
                    ORDER BY `value` ASC SEPARATOR '{$us}'
                ) AS `fetch_date_end`
            FROM $data_table
            WHERE `project_id` = ?
                AND `field_name` IN ('mrn', 'fetch_date_start', 'fetch_date_end')
            GROUP BY `record`
            HAVING (
                (fetch_date_start IS NULL OR fetch_date_start < ?)
                AND (fetch_date_end IS NULL OR fetch_date_end > ?)
            )
        ";

        // Merge new parameters into the existing params array
        $params = array_merge($params, [
            $this->project_id,
            $formatted_now,
            $formatted_now
        ]);

        return $query_string;
    }


    /**
     * Get the total number of MRNs that have not been fetched successfully after the creation date
     * of the revision. This is usually used for users with limited privileges.
     * 
     * NOTE: This is user-independent, i.e., any user could have fetched the data.
     *
     * @return int
     */
    protected function getTotalNonFetchedMrns()
    {
        $created_at = $this->created_at->format(self::$dateTimeFormat);
        $params = []; // Initialize the params array

        // Get the subquery for fetchable data
        $sub_query = $this->getQueryForFetchableData($params);

        // Build the main query
        $query_string = sprintf(
            "SELECT count(`mrn`) AS `total` FROM (%s) AS `rotated_data`
            WHERE `mrn` NOT IN (
                SELECT DISTINCT CASE mrn
                    WHEN mrn IS NULL THEN (SELECT mrn FROM redcap_ehr_access_tokens WHERE patient = fhir_id LIMIT 1)
                    ELSE mrn
                END as mrn
                FROM `%s`
                WHERE `project_id` = ? AND `status` = ? AND `created_at` > ? AND mrn <> ''
            )",
            $sub_query,
            FhirLogger::TABLE_NAME
        );

        // Merge additional parameters for the main query
        $params = array_merge($params, [
            $this->project_id,
            FhirLogger::STATUS_OK,
            $created_at
        ]);

        // Execute the query
        $result = db_query($query_string, $params);
        if ($row = db_fetch_assoc($result)) {
            return intval($row['total'] ?? 0);
        }

        return 0;
    }


    /**
     * get the total number of
     * fetchable MRNs for the current user
     *
     * @return int
     */
    public function getTotalFetchableMrnsProxy()
    {
        $fhir_user = $this->getCurrentUser();
        if($fhir_user->can_repeat_revision) {
            return $this->getTotalFetchableMrns();
        }
        return $this->getTotalNonFetchedMrns();
    }


    /**
     * Count MRNs with a fetchable date range.
     *
     * @return int
     */
    public function getTotalFetchableMrns()
    {
        // Initialize the params array
        $params = [];

        // Get the subquery for fetchable data and populate the params array
        $sub_query = $this->getQueryForFetchableData($params);

        // Build the main query
        $query_string = "SELECT COUNT(`mrn`) as `total` FROM ($sub_query) AS `rotated_data`";

        // Execute the query using the accumulated params
        $result = db_query($query_string, $params);
        
        if ($row = db_fetch_assoc($result)) {
            return intval($row['total'] ?? 0);
        }
        
        return 0;
    }


    /**
     * count MRNs in a project
     *
     * @return int
     */
    public function getTotalMrns()
    {
        $data_table = \Records::getDataTable($this->project_id);

        // Build the query with a placeholder for the project ID
        $query_string = "SELECT COUNT(DISTINCT `value`) as `total` 
            FROM $data_table
            WHERE `project_id` = ? AND `field_name` = 'mrn'";

        // Execute the query using the parameterized query
        $result = db_query($query_string, [$this->project_id]);
        
        if ($row = db_fetch_assoc($result)) {
            return intval($row['total'] ?? 0);
        }
        
        return 0;
    }


    /**
     * check if a MRN has already been fetched
     * by a user using the current revision
     * 
     * @param FhirUser $user
     * @param string $mrn
     * @return bool
     */
    public function hasPreviouslyFetchedMrn($fhir_user, $mrn)
    {
        $created_at = $this->created_at->format(self::$dateTimeFormat);
        $tableName = FhirLogger::TABLE_NAME;
        $query_string = "SELECT 1 FROM `$tableName`
            WHERE project_id = ? AND user_id = ?
            AND status = ? AND created_at > ?
            AND CASE 
                WHEN mrn IS NULL OR TRIM(mrn) = '' THEN
                    fhir_id = (SELECT patient FROM redcap_ehr_access_tokens WHERE mrn = ?)
                ELSE
                    mrn = ?
            END";
        $queryParams = [
            $this->project_id,
            $fhir_user->id,
            FhirLogger::STATUS_OK,
            $created_at,
            $mrn,
            $mrn,
        ];
        $result = db_query($query_string, $queryParams);
        $count = intval(db_num_rows($result));
        return boolval($count>0);
    }

    /**
     * check if a MRN can be fetched using this revision.
     * Users that can repeat revisions can always fetch data.
     * Users with minor privileges cannort fetch data twice
     * for an MRN in the same revision
     *
     * @param FhirUser $user
     * @param string $mrn
     * @return boolean
     */
    public function canFetchMrn($fhir_user, $mrn)
    {
        if($fhir_user->can_repeat_revision) return true;
        return !$this->hasPreviouslyFetchedMrn($fhir_user, $mrn);
    }

    /**
     * return a list of the MRNs stores in the records of the revision's project
     *
     * @return array
     */
    public function getProjectMrnList()
    {
        $query_string = "SELECT DISTINCT value FROM ".\Records::getDataTable($this->project_id)."
            WHERE project_id=? AND field_name='mrn'";
        $result = db_query($query_string, [$this->project_id]);
        $mrns = [];
        while($row = db_fetch_object($result))
        {
            $mrns[] = $row->value;
        }
        return $mrns;
    }


    /**
     * Get the next MRN with a valid individual
     * date range (as set in the "project settings" instrument).
     * If no MRN is provided then the first one will be returned
     *
     * @param FhirUser $fhirUser
     * @param string $mrn
     * @return string|null
     */
    public function getNextMrnWithValidDateRange($fhirUser, $mrn = null)
    {
        $getMrnSubsetQuery = function (&$params=[]) use ($fhirUser) {
            if (!$fhirUser->id) return ''; // Do not apply subquery if user is null (e.g., CRON)
            if ($fhirUser->can_repeat_revision) return ''; // No restrictions if user can repeat a revision
    
            $created_at = $this->created_at->format(self::$dateTimeFormat);
            $subset_query = sprintf(
                " AND `mrn` NOT IN (
                    SELECT `mrn` FROM `%s` WHERE `project_id` = ? 
                    AND `status` = ? AND `created_at` > ?
                )",
                FhirLogger::TABLE_NAME
            );
    
            // Merge new parameters into the existing params array
            $params = array_merge($params, [
                $this->project_id,
                FhirLogger::STATUS_OK,
                $created_at
            ]);
    
            return $subset_query;
        };
    
        $now = new \DateTime();
        $formatted_now = $now->format('Y-m-d H:i');
        $us = chr(31); // Unit separator
        $mrn = $mrn ?: ''; // Default to an empty string
        $dataTable = \Records::getDataTable($this->project_id);
    
        // Initialize the parameters array
        $params = [];
    
        // Generate the subset query to limit MRNs based on user privileges
        $subQuery = $getMrnSubsetQuery($params);
    
        // Build the main query
        $query_string = "SELECT
                record, mrn, fetch_date_start, fetch_date_end
            FROM (
                SELECT `record`,
                    GROUP_CONCAT(CASE WHEN `field_name` = 'mrn' THEN value ELSE NULL END ORDER BY `value` ASC SEPARATOR '{$us}') AS `mrn`,
                    GROUP_CONCAT(CASE WHEN `field_name` = 'fetch_date_start' THEN value ELSE NULL END ORDER BY `value` ASC SEPARATOR '{$us}') AS `fetch_date_start`,
                    GROUP_CONCAT(CASE WHEN `field_name` = 'fetch_date_end' THEN value ELSE NULL END ORDER BY `value` ASC SEPARATOR '{$us}') AS `fetch_date_end`
                FROM $dataTable
                WHERE `project_id` = ?
                    AND `field_name` IN ('mrn', 'fetch_date_start', 'fetch_date_end')
                    AND `record` IN (
                        SELECT DISTINCT `record` 
                        FROM $dataTable 
                        WHERE `project_id` = ? 
                        AND `field_name` = 'record_id' 
                        AND `value` IS NOT NULL
                    )
                GROUP BY `record`
            ) AS aggregated
            WHERE 
                `mrn` > ?
                $subQuery
                AND (fetch_date_start IS NULL OR fetch_date_start < ?)
                AND (fetch_date_end IS NULL OR fetch_date_end > ?)
            ORDER BY `mrn` ASC
            LIMIT 1";
    
        // Merge additional parameters for the main query
        $params = array_merge($params, [
            $this->project_id,
            $this->project_id,
            $mrn,
            $formatted_now,
            $formatted_now
        ]);
    
        // Execute the query using the accumulated parameters
        $result = db_query($query_string, $params);
    
        if ($row = db_fetch_assoc($result)) {
            return $row['mrn'] ?? '';
        }
    
        return null;
    }
    

    /**
     * check if a project contains an MRN
     *
     * @return boolean
     */
    public function projectContainsMrn($mrn)
    {
        $query_string = 
            "SELECT * FROM ".\Records::getDataTable($this->project_id)."
            WHERE project_id= ?
            AND field_name='mrn'
            AND value= ?";
        $result = db_query($query_string, [$this->project_id, $mrn]);
        return db_num_rows($result);
    }

    /**
     * create empty records if the revision contains MRNs
     *
     * @return array results from saved data
     */
    public function createRecords()
    {
        // Remove MRNs if already existing in project
        $filterRecords = function($mrns=[]) {
            if (empty($mrns)) return [];
            $placeholders = implode(',', array_fill(0, count($mrns), '?'));
            $tableName = \Records::getDataTable($this->project_id);
            $query_string = sprintf(
                "SELECT `record`, `field_name`, `value`
                FROM $tableName WHERE project_id=? AND `field_name`='mrn' AND `value` IN (%s)",
                $placeholders
            );
            $queryParams = array_merge([$this->project_id], $mrns);
            $result = db_query($query_string, $queryParams);

            while($row=db_fetch_assoc($result)) {
                $value = $row['value'] ?? '';
                $index = array_search($value, $mrns);
                if($index !== false) unset($mrns[$index]);
            }
            return $mrns;
        };
        if(count($this->mrns)===0) return;
        $project = new \Project($this->project_id);
        $event_id = $project->firstEventId;
        $mrns = $filterRecords($this->mrns);
        foreach ($mrns as $mrn) {
            $record_id = \DataEntry::getAutoId($this->project_id); // get auto record number
            $record = [
                $record_id => [
                    $event_id => [
                        'record_id' => $record_id,
                        'mrn' => $mrn,
                    ]
                ]
            ];
            $result = REDCap::saveData($this->project_id, 'array', $record);
            $errors = $result['errors'] ?? [];
            if(!empty($errors)) {
                $errorText = implode(PHP_EOL, $errors);
                Logging::logEvent('', 'redcap_data', "ERROR", $record_id, $errorText, "Error creating new record in the Data Mart project ID {$this->project_id}");
            }
        }
    }


    /**
     * compare 2 arrays
     *
     * @param array $array_a
     * @param array $array_b
     * @return void
     */
    private static function compareArrays($array_a, $array_b)
    {
        sort($array_a);
        sort($array_b);
        return $array_a == $array_b;
    }

    private static function compareDates($date_a, $date_b)
    {
        $date_a = ($date_a instanceof \DateTime) ? $date_a->format(self::$dateTimeFormat) : $date_a;
        $date_b = ($date_b instanceof \DateTime) ? $date_b->format(self::$dateTimeFormat) : $date_b;
        return $date_a === $date_b;
    }

    /**
     * transform a string into a DateTime or in a null value
     *
     * @param string $date_string
     * @return null|\DateTime
     */
    private static function getDate($date_string)
    {
        if(empty($date_string)) return null;
        $time = strtotime($date_string);
        $date_time = new \DateTime();
        $date_time->setTimestamp($time);
        return $date_time;
    }


    /**
     * get info about the user who created the revision
     */
    public function getCreator()
    {
        return new FhirUser($this->user_id, $this->project_id);
    }

    /**
     * get the data of the revision
     *
     * @return array
     */
    public function getData()
    {
        return array(
            'mrns' => $this->mrns,
            'fields' => $this->fields,
            'date_range_categories' => $this->date_range_categories,
            'dateMin' => ($this->date_min instanceof \DateTime) ? $this->date_min->format(self::$dateTimeFormat) : '',
            'dateMax' => ($this->date_max instanceof \DateTime) ? $this->date_max->format(self::$dateTimeFormat) : '',
        );
    }

    /**
     * magic getter for properties specified in self::$readable_variables
     *
     * @param string $name
     * @return void
     */
    public function __get($property)
    {
        if (property_exists($this, $property) && in_array($property, self::$readable_variables)) {
            return $this->$property;
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }

    /**
     * setter for instance properties
     * helps to set the right format for dates, arrays and booleans
     *
     * @param string|array $property
     * @param mixed $value
     * @return DataMartRevision
     */
    private function set($property, $value=null)
    {
        $toArray = function($val) {
            if(is_array($val)) return $val;
            if(!is_string($val)) return [];
            $text = trim($val);
            if(strlen($text)===0) return [];
            return explode(self::$string_delimiter, $text);
        };
        if(!property_exists($this, $property)) return $this;
        switch ($property) {
            case 'mrns':
            case 'fields':
            case 'date_range_categories':
                $list = $toArray($value);
                $list = array_unique($list, SORT_STRING); // discard duplicates
                $this->{$property} = $list;
                break;
            case 'date_min':
            case 'date_max':
            case 'executed_at':
            case 'created_at':
                if ($value instanceof \DateTime)
                {
                    $this->{$property} = $value; // assign it if it is a DateTime
                } else
                {
                    $this->{$property} = self::getDate($value);
                }
                break;
            case 'approved':
                $this->{$property} =  boolval($value); //convert to boolean
                break;
            default:
                $this->{$property} = $value;
                break;
        }
        return $this;
    }

    /**
     * Get the date range for an MRN considering both revision level date range
     * and record level date range; record level date range has higher priority.
     * 
     * This date range affects temporal data like labs and vitals.
     *
     * @param string $mrn
     * @return DateTime[] ['date_min', 'date_max']
     */
    public function getTemporalDataDateRangeForMrn($mrn)
    {
        if(empty($this->project_id)) return;
        // set default values
        $date_min = '';
        $date_max = '';
        $datamart_record = new DataMartRecord($this->project_id, $mrn);
        $record_date_range = $datamart_record->getDateRange();
        // priority to record date range
        if(empty($record_date_range))
        {
            // use the revision date range if no date range has been specified in the 'Project Settings' instrument 
            $revision_date_range = $this->getFHIRDateRange(); // get a date range compatible with the FHIR specification
            $date_min = $revision_date_range['date_min'];
            $date_max = $revision_date_range['date_max'];
        }else {
            // use the date range specified in the instrument 'Project Settings' if available
            $date_min = $record_date_range['date_min'];
            $date_max = $record_date_range['date_max'];
        }
        return [$date_min, $date_max];
    }

    /**
    * Returns data which can be serialized
    * this format is used in the client javascript app
    *
    * @return array
    */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $serialized = array(
            'metadata' => array(
                'id' => $this->id,
                'request_id' => $this->request_id,
                'request_status' => $this->request_status,
                'date' => $this->created_at->format(self::$dateTimeFormat),
                // 'date' => ($this->created_at instanceof DateTime) ? $this->created_at->format(self::$dateTimeFormat) : '',
                'executed' => $this->hasBeenExecuted(),
                'executed_at' => ($this->executed_at instanceof \DateTime) ? $this->executed_at->format(self::$dateTimeFormat) : '',
                'approved' => boolval($this->approved),
                'creator' => $this->getCreator(),
                'total_project_mrns' => $this->getTotalMrns(),
                'total_non_fetched_mrns' => $this->getTotalNonFetchedMrns(),
                'total_fetchable_mrns' => $this->getTotalFetchableMrnsProxy(),
            ),
            'data' => $this->getData(),
        );
        return $serialized;
    }

    /**
     * print a DataMart Revision as a string
     *
     * @return string
     */
    public function __toString()
    {
        $string = '';
        $string .= $this->id;
        return $string;
    }
    
}
