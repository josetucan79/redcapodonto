<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\DB;

use Nyholm\Psr7\UploadedFile;
use Vanderbilt\REDCap\Classes\MyCap\Annotation;
use Vanderbilt\REDCap\Classes\MyCap\Api\Field\Choice;
use Vanderbilt\REDCap\Classes\MyCap\Api\Field\File;
use Vanderbilt\REDCap\Classes\MyCap\Api\Field\Notes;
use Vanderbilt\REDCap\Classes\MyCap\Api\Field\Radio;
use Vanderbilt\REDCap\Classes\MyCap\Api\Field\TrueFalse;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\Api\Exceptions\SaveException;

/**
 * Base class for interacting with REDCap data via Developer Tools.
 *
 * TODO: This should be split into Mapper (an abstract class),
 *                                 Mapper\Instrument (extends Mapper) and
 *                                 Mapper\Instance (extends Mapper)
 * TODO: Tackle this when you support longitudinal projects. Class is too complex.
 *
 * @see https://redcap.vumc.org/redcap_v7.0.0/Plugins/index.php REDCap Developer Tools
 * @package REDCapExt
 */
class ProjectMapper
{
    const FIELD_STATUS_INCOMPLETE = 0;
    const FIELD_STATUS_UNVERIFIED = 1;
    const FIELD_STATUS_COMPLETE = 2;

    public static $requiredAnnotationsMapping = [
        Annotation::TASK_UUID => 'uuid',
        Annotation::TASK_STARTDATE => 'start_time',
        Annotation::TASK_ENDDATE => 'end_time',
        Annotation::TASK_SCHEDULEDATE => 'schedule_time',
        Annotation::TASK_STATUS => 'status',
        Annotation::TASK_SUPPLEMENTALDATA => '',
        Annotation::TASK_SERIALIZEDRESULT => 'serialized_result_doc_id'
    ];
    /**
     * @var array $fields Array map of REDCap fieldnames or annotations that we care about
     *
     * Given $fields definition:
     * [
     *     'foo' => 'bar',
     *     '@MYANNOTATION' => 'baz'
     * ]
     *
     * The redcap field 'foo' will be aliased to 'bar'
     * The redcap field that contains the '@MYANNOTATION' string will be aliased to 'baz'
     *
     * Generates $fieldMap:
     *
     * [
     *     'foo' => 'bar',
     *     'actual_redcap_fieldname' => 'baz'
     * ]
     *
     * So $mapper->all()->results() would return an array that looks like:
     * [
     *     [
     *         'bar' => '1234',
     *         'baz' => '5678'
     *     ],
     *     etc...
     * ]
     *
     * NOTE: Repeating instance project mappers MUST include the REDCap record id in the field definition. By default
     * REDCap names the field "record_id", but it can be named something else.
     */
    public $fields = [];
    /** @var string|null Repeating instance name. Form/instrument name */
    public $repeatInstanceName = null;
    /** @var string|null Repeating record identifier */
    public $repeatRecordId = null;
    /** @var int $pid REDCap project ID */
    protected $pid = 0;
    /** @var boolean $longitudinal REDCap project longitudinal or not? */
    public $longitudinal = false;
    /**
     * See $fields. Cache for fieldMap data
     *
     * @var array|null
     */
    private $fieldMap = null;

    /** @var array $results */
    private $results;

    /** @var array Array of forms => [REDCapExt\Api\Field] */
    public $forms = [];

    /**
     * Construct
     *
     * @param int $pid
     * @param string|null $repeatRecordId
     * @throws \Exception
     */
    public function __construct($pid, $repeatRecordId = null)
    {
        if (!isset($pid)) {
            throw new \Exception('ProjectMapper instantiated without required project id.');
        }
        $this->pid = $pid;

        // If we are mapping to a repeated instrument then we need to know which record we are dealing with
        if (isset($repeatRecordId)) {
            $this->repeatRecordId = $repeatRecordId;
        }
    }

    /**
     * Return a single record matching filter params. May only be used by repeating instruments. Only use this
     * when you KNOW that you will never have multiple rows returned by your filter. If your filter SHOULD
     * return multiple rows and you use oneByFilter then you will get ZERO records returned. This all has to
     * do with the nuances of filtering by RECORD vs EVENT in conjunction with REPEATING vs NON-REPEATING
     * instruments. This method is only useful when you have a field in a repeating instrument that contains
     * unique values.
     *
     * @param null $filter
     * @return $this
     * @throws \Exception
     */
    public function oneByFilter($filter = null)
    {
        if (!$this->isRepeatingInstrument()) {
            throw new \Exception("This method can only be used by repeating instruments");
        }

        if (isset($filter)) {
            $filter = $this->processFilter($filter);
        }

        $records = [$this->repeatRecordId];

        $data = \REDCap::getData(
            $this->pid,
            'array',
            $records,
            null,
            $this->eventId,
            null,
            false,
            false,
            false,
            $filter
        );
        $records = $this->processData($data);
        if (count($records)) {
            $this->results = reset($records);
        }
        return $this;
    }

    /**
     * Is the mapper mapping to a repeating instrument?
     *
     * @return bool
     */
    private function isRepeatingInstrument()
    {
        return empty($this->repeatInstanceName) ? false : true;
    }

    /**
     * Replace object field name with corresponding REDCap field name within a filter string
     *
     * Given field definition:
     *   $fields = [
     *       'foo' => 'cat',
     *       'bar' => 'dog'
     *   ]
     *
     * Turns:
     *   $filter = '[cat] = "4" AND [dog] = "9"
     *
     * Into:
     *   $filter = '[foo] = "4" AND [bar] = "9"
     *
     *
     * @param string $filter
     * @return string
     */
    private function processFilter($filter)
    {
        $str = $filter;

        $map = array_flip($this->fieldMap());

        foreach ($map as $field => $redcapField) {
            $str = preg_replace(
                '/\[' . $field . '\]/',
                "[$redcapField]",
                $str
            );
        }

        return $str;
    }

    /**
     * Use REDCap's data dictionary and the object's field definition to map REDCap fields to the fields
     * we use in code.
     *
     * @param bool $forceRefresh Field map is cached after the first call
     * @return array
     */
    public function fieldMap($forceRefresh = false)
    {
        if (is_array($this->fieldMap) && $forceRefresh == false) {
            return $this->fieldMap;
        }

        $dictionaryFields = $this->dictionary();

        $fieldNames = array_keys($this->fields);

        $map = [];

        foreach ($dictionaryFields as $name => $field) {
            if (in_array(
                $name,
                $fieldNames
            )) {
                $map[$name] = $this->fields[$name];
            } elseif (strlen($field['field_annotation'])) {
                // It's possible, and highly likely for MyCap, for two different repeating instruments to contain
                // the same annotation. Make sure we are using the correct instrument/form
                if ($this->isRepeatingInstrument() && $field['form_name'] != $this->repeatInstanceName) {
                    continue;
                }


                // Replace multiple spaces and newlines with a single space. 
                $cleanedFieldAnnotation = preg_replace('/\s+/', ' ', trim($field['field_annotation']));
                $annotations = explode(
                    ' ',
                    $cleanedFieldAnnotation
                );
                $intersect = array_intersect(
                    $annotations,
                    $fieldNames
                );
                if (count($intersect)) {
                    $foundAnnotationName = implode(
                        '',
                        $intersect
                    );
                    $map[$field['field_name']] = $this->fields[$foundAnnotationName];
                }
            }
        }

        // REDCap Checkbox fields may have their field_name listed as variable+'___'+optionCode and its value as either
        // '0' or '1' (unchecked or checked, respectively). If we see a fieldname containing a triple underscore then
        // treat it as a checkbox field and include it
        foreach ($fieldNames as $fieldName) {
            if (strpos(
                $fieldName,
                '___'
            ) !== false) {
                $map[$fieldName] = $fieldName;
            }
        }

        $this->fieldMap = $map;

        return $map;
    }

    /**
     * Get data dictionary for the project
     *
     * @param array $instruments
     * @param array $fields
     * @see /redcap_vX.X.X/Plugins/index.php?REDCapMethod=getDataDictionary
     * @return array
     * @throws Exception
     */
    public function dictionary($instruments = null, $fields = null)
    {
        /** @var array $dictionary */
        $dictionary = \REDCap::getDataDictionary(
            $this->pid,
            'array',
            false,
            $fields,
            $instruments
        );
        return $dictionary;
    }

    /**
     * Get data dictionary for one or more instruments
     *
     * @param array $instruments
     * @return array
     * @throws Exception
     */
    public function dictionaryForInstruments($instruments)
    {
        return $this->dictionary($instruments);
    }

    /**
     * Process data returned from REDCap::getData
     *
     * @param array $data
     * @param integer $instance_stored
     * @return array
     */
    private function processData($data)
    {
        $map = $this->fieldMap();

        $rows = [];

        // REDCap returns many fields that we don't care about. Only get data for fields specified
        // in the class' $fields array.
        foreach ($data as $id => $record) {
            if (!$this->isRepeatingInstrument()) {
                foreach ($record as $event_id => $fields) {
                    // skip repeating instruments/forms
                    if ($event_id == 'repeat_instances') {
                        continue;
                    }
                    $intersect = array_intersect_key(
                        $fields,
                        $map
                    );
                    $row = [];
                    foreach ($intersect as $name => $value) {
                        $row[$map[$name]] = $value;
                    }
                    $rows[] = $row;
                }
            } else {
                $repeating_instances = $record['repeat_instances'];
                foreach ($repeating_instances as $event_id => $instrument) {
                    foreach ($instrument as $name => $instrument_instances) {
                        if ($name != $this->repeatInstanceName) {
                            continue;
                        }
                        foreach ($instrument_instances as $instance_id => $fields) {
                            // always include instance id because all repeating instruments need it
                            $row = [
                                'redcap_repeat_instance' => $instance_id,
                                'event_id' => $event_id
                            ];

                            // only include fields that we care about
                            $intersect = array_intersect_key(
                                $fields,
                                $map
                            );
                            foreach ($intersect as $name => $value) {
                                $row[$map[$name]] = $value;
                            }
                            // indexing by instance_id so $this->one() can find the correct record quickly
                            $rows[$instance_id] = $row;
                        }
                    }
                }
            }
        }
        return $rows;
    }

    /**
     * Insert a new record
     *
     * @param $data
     * @return array Record inserted
     * @throws \Exception
     */
    public function insert($data)
    {
        if ($this->isRepeatingInstrument()) {
            $data['redcap_repeat_instance'] = $this->nextId();
        } else {
            throw new \Exception("TODO: Insert is not implemented for non-repeating instruments");
        }

        return $this->save($data);
    }

    /**
     * Get next sequence ID for the project or repeating instrument. Returns a GUID if the project uses non-integer IDs
     *
     * @return int|string
     * @throws \Exception
     */
    public function nextId()
    {
        $nextId = null;
        if ($this->isRepeatingInstrument()) {
            $Proj = new \Project($this->pid);
            $nextId = \RepeatInstance::getNextRepeatingInstance($this->pid, $this->repeatRecordId, $this->eventId, $this->repeatInstanceName);
        } else {
            $nextId = \DataEntry::getAutoId($this->pid);
            if ($nextId === 0) {
                $nextId = MyCap::guid();
            }
        }
        return $nextId;
    }

    /**
     * Save a single record to REDCap
     *
     * @see /redcap_vX.X.X/Plugins/index.php?REDCapMethod=saveData
     * @param array $data
     * @return array Record saved
     * @throws Exception
     * @throws SaveException
     */
    public function save($data)
    {
        $map = array_flip($this->fieldMap());
        $fieldNames = array_keys($map);
        $record = [];

        $myCapProj = new MyCap($this->pid);
        if ($myCapProj->tasks[$data['instrument']]['enabled_for_mycap'] != 1) {
            throw new \Exception('You must provide task which is enabled for MyCap.');
        }
        if ($this->isRepeatingInstrument()) {
            if (!isset($data['redcap_repeat_instance'])) {
                throw new \Exception('You must provide "redcap_repeat_instance" when saving a repeating instrument');
            }
            $record['redcap_repeat_instance'] = $data['redcap_repeat_instance'];
            $record['redcap_repeat_instrument'] = $this->repeatInstanceName;
        }

        $record['redcap_event_name'] = $data['redcap_event_name'];
        foreach ($data as $name => $value) {
            if (in_array(
                $name,
                $fieldNames
            )) {
                $record[$map[$name]] = $value;
            } elseif (self::endsWith(
                $name,
                '_complete'
            )) {
                // REDCap has a few special fields that indicate whether a record is complete or not. The end
                // in "_complete". Let these fields go through our filter.
                $record[$name] = $value;
            }
        }

        // We are saving a single record. REDCap expects an array of records, so wrap $data in an array.
        $results = \REDCap::saveData(
            $this->pid,
            'json',
            json_encode([$record]),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false,
            null,
            null,
            true
        );

        // REDCap might return an array or a string
        if ((is_array($results['errors']) && count($results['errors'])) ||
            (is_string($results['errors']) && strlen($results['errors']))
        ) {
            $issues = is_array($results['errors']) ? $results['errors'] : array($results['errors']);
            throw new SaveException('Error saving data to REDCap', $issues);
        }

        if (count($results['warnings'])) {
            // TODO: Pass along REDCap's warnings?
            // throw new Exception($results['warnings']);
        }

        if (count($results['ids']) == 1) {
            if ($this->isRepeatingInstrument()) {
                $newRecord = $this->one($data['redcap_repeat_instance'])->results();
            } else {
                $newRecord = $this->one($results['ids'][0])->results();
            }
        } else {
            throw new SaveException(
                "Expected 1 record to be affected. Got: " . count($results['ids']),
                $results['ids']
            );
        }

        return $newRecord;
    }

    /**
     * Does string end with string?
     *
     * @param $haystack
     * @param $needle
     * @return bool
     */
    private static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr(
            $haystack,
            -$length
        ) === $needle);
    }

    /**
     * Returns results
     *
     * @return array
     */
    public function results()
    {
        return $this->results;
    }

    /**
     * Find record matching an id or a repeating instance id
     *
     * E.g. $mapper->one('4')->results();
     *
     * @see /redcap_vX.X.X/Plugins/index.php?REDCapMethod=getData
     * @param string $id Record ID, or redcap_repeat_instance for repeating instruments
     * @return ProjectMapper
     */
    public function one($id)
    {
        if ($this->isRepeatingInstrument()) {
            // REDCap::getData doesn't seem to have a good way to filter by repeating instance id. Just get all records.
            // Remember that $id is redcap_repeat_instance in this context.
            $results = $this->all()->results();
            $this->results = $results[$id];
        } else {
            $records = $this->processData(
                \REDCap::getData(
                    $this->pid,
                    'array',
                    $id
                )
            );
            $this->results = $records[0];
        }

        return $this;
    }

    /**
     * Find all records matching filters.
     *
     * E.g. $mapper->all("[usr_id] = '4'")->results();
     *
     * @param string $filter (optional) REDCap filter command. E.g. "[usr_id] = '4' OR [usr_id] = '6'"
     * @return ProjectMapper
     */
    public function all($filter = null)
    {
        if (isset($filter)) {
            $filter = $this->processFilter($filter);
        }

        $filterType = 'RECORD';
        $records = null;
        if ($this->isRepeatingInstrument()) {
            $filterType = 'EVENT';
            $records = [$this->repeatRecordId];
        }

        $data = \REDCap::getData(
            $this->pid,
            'array',
            $records,
            null,
            null,
            null,
            false,
            false,
            false,
            $filter,
            false,
            false,
            false,
            false,
            false,
            array(),
            false,
            false,
            false,
            false,
            false,
            false,
            $filterType
        );

        $this->results = $this->processData($data);
        return $this;
    }

    /**
     * Each instrument/form has a special *_complete field to track the "Completed?" status.
     *
     * @see CompleteFieldStatus
     * @return string
     */
    public function completeFieldName()
    {
        $completeField = $this->repeatInstanceName . '_complete';
        return $completeField;
    }

    /**
     * Call setSystemLog($name) before save() when saving a record using a system process. Will have no effect if called
     * within a process where a logged-in user exists.
     *
     * The REDCap Logging->logEvent() method is called during the REDCap::saveData() method. saveData() does not have a
     * parameter to let us specify $userid_override. Define USERID so the logEvent() method will have something to use.
     *
     * @param $name
     */
    public function logAs($name)
    {
        if (defined("USERID")) {
            return;
        }
        define("USERID", $name);
    }

    /**
     * Process data returned from REDCap::getData
     *
     * @param array $data
     * @return array
     */
    public function getFormattedData($data) {
        return $this->processData($data);
    }
}
