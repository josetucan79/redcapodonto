<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Logs;

class FhirLogEntry
{


    /**
     * fields as stored on the database
     *
     * @var array
     */
    private static $table_fields = array(
        'user_id',
        'fhir_id',
        'mrn',
        'project_id',
        'resource_type',
        'status',
        'context',
        'environment',
        'created_at',
    );

    /**
     * ID of the user using the FHIR endpoint
     *
     * @var integer
     */
    private $user_id;

    /**
     * FHIR identifier of the patient
     *
     * @var string
     */
    private $fhir_id;

    /**
     * Medical record number of the patient
     *
     * @var string
     */
    private $mrn;

    /**
     * ID of the project where the data has been pulled
     *
     * @var string
     */
    private $project_id;

    /**
     * FHIR endpoint
     *
     * @var string
     */
    private $resource_type;

    /**
     * status of the interaction (successful or not)
     *
     * @var integer
     */
    private $status;

    /**
     * app that triggered the request (CDM, CDP, etc...)
     *
     * @var string
     */
    private $context;
    
    /**
     * wheter it is a CRON or direct user request
     *
     * @var string
     */
    private $environment;

    /**
     * date and time of the interaction
     *
     * @var \DateTime
     */
    private $created_at;

    /**
     * create a FHIR log entry
     *
     * @param array $params
     */
    public function __construct($params=[])
    {
        foreach ($params as $key => $value) {
            $this->{$key} = $value;
        }
    }
    
        /**
     * magic getter for private properties
     *
     * @param string $name
     * @return void
     */
    public function __get($name)
    {
        $fields = self:: $table_fields;
        if (property_exists($this, $name) && in_array($name, $fields))
        {
            return $this->{$name};
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }

    public function __set($name, $value)
    {
        $fields = self:: $table_fields;
        if (!property_exists($this, $name) || !in_array($name, $fields)) return;
        return $this->{$name} = $value;
    }
}
