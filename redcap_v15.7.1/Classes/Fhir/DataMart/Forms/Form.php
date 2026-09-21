<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

abstract class Form
{

    /**
     * name of the form
     *
     * @var string
     */
    protected $form_name;

    protected $is_repeating;

    /**
     * list of keys that indicate uniqueness of a form
     *
     * @var array
     */
    protected $uniquenessFields = [];

    /**
     * contains the form structure in an associative array
     *
     * @var array
     */
    protected $form = [];
    /**
     * FHIR data to form fields mapping
     *
     * @var array
     */
    protected $data_mapping = [];

    /**
     *
     * @var Project
     */
    private $project;
    /**
     *
     * @var int
     */
    private $event_id;

    /**
     *
     * @param Project $project
     * @param int $event_id
     */
    function __construct($project, $event_id=null) {
        $this->project = $project;
        $this->event_id = $event_id ?: $this->project->firstEventId;
    }

    function isRepeating()
    {
        if(!isset($this->is_repeating)) {
            $form_name = $this->getFormName(); 
            $repeating_forms_events = $this->project->getRepeatingFormsEvents(); // list of repeating forms with custom label
            if(!array_key_exists($this->event_id, $repeating_forms_events)) $this->is_repeating = false;
            else if(!array_key_exists($form_name, $repeating_forms_events[$this->event_id])) $this->is_repeating = false;
            else $this->is_repeating = true;
        }
        return $this->is_repeating;
    }

    /**
     * getter for the uniqueness keys
     *
     * @return array
     */
    function getUniquenessFields()
    {
        return $this->uniquenessFields;
    }

    /**
	 * create a record data structure
	 *
	 * @param int $project_id
	 * @param int $event_id
	 * @param string $record_id
	 * @param string $field_name
	 * @param mixed $value
	 * @param int $instance_number
	 * @param array $record_seed
	 * @return array
	 */
	function reduceRecord($record_id, $event_id, $field_name, $value, $instance_number=null, $record_seed=[])
	{
        $addRepeatingData = function($form_name, $field_name, $value) use($record_id, $event_id, $instance_number, &$record_seed) {
            $record_seed[$record_id]['repeat_instances'][$event_id][$form_name][$instance_number][$field_name] = $value;
        };
        $addData = function($field_name, $value) use($record_id, $event_id, &$record_seed) {
            $record_seed[$record_id][$event_id][$field_name] = $value;
        };
        
        $form_name = $this->getFormName();
        $is_repeating = $this->isRepeating($event_id);
        if($is_repeating) $addRepeatingData($form_name, $field_name, $value);
        else $addData($field_name, $value);

        return $record_seed;
	}

    /**
     * map the FHIR data with the structure
     * of the form
     *
     * @return array
     */
    public function mapFhirData($fhirData)
    {
        $data = [];
        $mapValue = function($fhir_key, $value) use(&$data){
            if(array_key_exists($fhir_key, $this->data_mapping)) {
                $form_key = $this->data_mapping[$fhir_key];
                $data[$form_key] = $value;
            }
        };
        foreach ($fhirData as $fhir_key => $value) {
            $mapValue($fhir_key, $value);
        }
        return $data;
    }

    public function getFormName()
    {
        return $this->form_name;
    }

    /**
     * get the data structure as defined in the $form variable
     *
     * @return array
     */
    public function getFormData() {
        return $this->form;
    }

    public function getFhirFieldName()
    {
        $form_name = $this->getFormName();
        return "{$form_name}_fhir_id";
    }

    /**
     * get keys of the form from the mapping array
     *
     * @return array
     */
    public function getKeys()
    {
        $keys = array_values($this->data_mapping);
        return $keys;
    }

    public function __get($field_name) {
        $form_data = $this->getFormData();
        if(!array_key_exists($field_name, $form_data)) return;
        $value = $form_data[$field_name] ?: null;
        return $value;
    }

    /**
     * add to the provided data
     * the "{form_name}_complete" value
     * so that the record is marked as complete
     *
     * @return array
     */
    public function addCompleteFormData($data)
    {
        $form_name = $this->getFormName();
        $data["{$form_name}_complete"] = '2';
        return $data;
    }

}