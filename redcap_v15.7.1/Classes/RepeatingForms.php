<?php

/*
 * For longitudinal projects, the returned data is in the form of:
    [record_id 1]
        [event_id 1]
            [instance a form]       => {form data}
            [instance b form]       => {form data}
            . . .
        [event_id 2]
            [instance a form]       => {form data}
            [instance b form]       => {form data}
            . . .
        [event_id n]
            [instance a form]       => {form data}
            [instance b form]       => {form data}
            . . .
    [record_id 2]
        [event_id 1]
            [instance a form]       => {form data}
        [event_id y]
            [instance a form]       => {form data}


 * For classical projects, the returned data is in the form of:
    [record_id 1]
        [instance 1 form]       => {form data}
        [instance 2 form]       => {form data}
        . . .


The instance identifiers (i.e. a, b) are used to depict the first and second instances.  The number of the instance
may not be uniformly increasing numerically since some instances may have been deleted. For instance, instance 2 may
have been deleted, so the instance numbers would be instance 1 and instance 3.

If using the instance filter, only instances which match the filter criteria will be returned so the instance numbers
will vary.

*/


/**
 * Class RepeatingForms
 * @package Stanford\Utilities
 *
 */
class RepeatingForms
{
    // Metadata
    private $Proj;
    private $pid;
    private $is_longitudinal;
    private $data_dictionary;
    private $fields;
    private $events_enabled = array();    // Array of event_ids where the instrument is enabled
    private $instrument;

    // Instance
    private $event_id;
    private $data;
    private $data_loaded = false;
    private $record_id;

    /**
     * @throws Exception
     */
    function __construct($pid, $instrument_name)
    {
		$this->Proj = new \Project($pid);
        if (empty($this->Proj)) {
            throw new \Exception("The supplied project id does not match any existing project");
        }
        $this->pid = $pid;
        // Find the fields on this repeating instrument
        $this->instrument = $instrument_name;
        $this->data_dictionary = \REDCap::getDataDictionary($pid, 'array', false, null, array($instrument_name));
        $this->fields = array_keys($this->data_dictionary);
        // Is this project longitudinal?
        $this->is_longitudinal = $this->Proj->longitudinal;
        // If this is not longitudinal, retrieve the event_id
        if (!$this->is_longitudinal) {
            $this->event_id = array_keys($this->Proj->eventInfo)[0];
        }
        // Retrieved events
        $all_events = $this->Proj->getRepeatingFormsEvents();
        // See which events have this form enabled
        foreach (array_keys($all_events) as $event) {
            $fields_in_event = \REDCap::getValidFieldsByEvents($this->pid, $event, false);
            $field_intersect = array_intersect($fields_in_event, $this->fields);
            if (isset($field_intersect) && sizeof($field_intersect) > 0) {
                array_push($this->events_enabled, $event);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function setUp($record_id, $event_id): void
    {
        if ($this->is_longitudinal && is_null($event_id)) {
            throw new \Exception("Event ID required");
        } else if (!$this->is_longitudinal) {
            $event_id = $this->event_id;
        }
        // Check to see if we have the correct data loaded. If not, load it.
        if ($this->data_loaded == false || $this->record_id != $record_id || $this->event_id != $event_id) {
            $this->loadData($record_id, $event_id, null);
        }
    }

    /**
     * This function will load data internally from the database using the record, event and optional
     * filter in the calling arguments here as well as pid and instrument name from the constructor.  The data
     * is saved internally in $this->data.  The calling program must then call one of the get* functions
     * to retrieve the data.
     *
     * @param $record_id
     * @param null $event_id
     * @param null $filter
     * @return Void
     */
    public function loadData($record_id, $event_id=null, $filter=null)
    {
        $this->record_id = $record_id;
        if (!is_null($event_id)) {
            $this->event_id = $event_id;
        }
        // Filter logic will only return matching instances
        $return_format = 'array';
        $repeating_forms = \REDCap::getData($this->pid, $return_format, array($record_id), $this->fields, $this->event_id, NULL, false, false, false, $filter, true);
        // If this is a classical project, we are not adding event_id.
        // When this is a repeating event, there is a blank for the form since all forms are repeated
        foreach (array_keys($repeating_forms) as $record) {
            foreach ($this->events_enabled as $event) {
                if (!is_null($repeating_forms[$record]["repeat_instances"][$event]) and !empty($repeating_forms[$record_id]["repeat_instances"][$event])) {
                    $repeat_data = $repeating_forms[$record]["repeat_instances"][$event];
                    $repeat_type = array_keys($repeating_forms[$record]["repeat_instances"][$event])[0];
                    if (empty($repeat_type)) {
                        $form_data = $repeat_data[''];
                    } else {
                        $form_data = $repeat_data[$this->instrument];
                    }

                    if ($this->is_longitudinal) {
                        $this->data[$record_id][$event] = $form_data;
                    } else {
                        $this->data[$record_id] = $form_data;
                    }
                }
            }
        }
        $this->data_loaded = true;
    }

    /**
     * This function will return the data retrieved based on a previous loadData call. All instances of an
     * instrument fitting the criteria specified in loadData will be returned. See the file header for the
     * returned data format.
     *
     * @param $record_id
     * @param null $event_id
     * @return array (of data loaded from loadData) or false if an error occurred
     */
    public function getAllInstances($record_id, $event_id=null)
    {
        $this->setUp($record_id, $event_id);
        return $this->data;
    }

    /**
     * This function will return one instance of data retrieved in dataLoad using the $instance_id.
     *
     * @param $record_id
     * @param $instance_id
     * @param null $event_id
     * @return array|false
     * @throws Exception
     */
    public function getInstanceById($record_id, $instance_id, $event_id=null)
    {
        $this->setUp($record_id, $event_id);
        // If the record and optionally event match, return the data.
        if ($this->is_longitudinal) {
            if (!empty($this->data[$record_id][$event_id][$instance_id]) &&
                !is_null($this->data[$record_id][$event_id][$instance_id])) {
                return $this->data[$record_id][$event_id][$instance_id];
            } else {
                throw new \Exception("Invalid instance number");
            }
        } else {
            if (!empty($this->data[$record_id][$instance_id]) && !is_null($this->data[$record_id][$instance_id])) {
                return $this->data[$record_id][$instance_id];
            } else {
                throw new \Exception("Invalid instance number");
            }
        }
    }

    /**
     * This function will return the first instance_id for this record and optionally event. This function
     * does not return data. If the instance data is desired, call getInstanceById using the returned instance id.
     *
     * @param $record_id
     * @param null $event_id
     * @return int (instance number) or false (if an error occurs)
     * @throws Exception
     */
    public function getFirstInstanceId($record_id, $event_id=null)
    {
        $this->setUp($record_id, $event_id);
        // If the record and optionally event match, return the data.
        if ($this->is_longitudinal) {
            if (!empty(array_keys($this->data[$record_id][$event_id])[0]) &&
                !is_null(array_keys($this->data[$record_id][$event_id])[0])) {
                return array_keys($this->data[$record_id][$event_id])[0];
            } else {
                throw new \Exception("Instance not found");
            }
        } else {
            if (!empty(array_keys($this->data[$record_id])[0]) && !is_null(array_keys($this->data[$record_id])[0])) {
                return array_keys($this->data[$record_id])[0];
            } else {
                throw new \Exception("Instance not found");
            }
        }
    }

    /**
     * This function will return the last instance_id for this record and optionally event. This function
     * does not return data. To retrieve data, call getInstanceById using the returned $instance_id.
     *
     * @param $record_id
     * @param null $event_id
     * @return int
     * @throws Exception
     */
    public function getLastInstanceId($record_id, $event_id=null)
    {
        $this->setUp($record_id, $event_id);
        // If the record_ids (and optionally event_ids) match, return the data.
        if ($this->is_longitudinal) {
            $size = sizeof($this->data[$record_id][$event_id]);
            if ($size < 1) {
                throw new \Exception("Instance not found");
            } else {
                return array_keys($this->data[$record_id][$event_id])[$size - 1];
            }
        } else {
            $size = sizeof($this->data[$record_id]);
            if ($size < 1) {
                throw new \Exception("Instance not found");
            } else {
                return array_keys($this->data[$record_id])[$size - 1];
            }
        }
    }


    /**
     * This function will return the next instance_id in the sequence that does not currently exist.
     * If there are no current instances, it will return 1.
     *
     * @param $record_id
     * @param null $event_id
     * @return int
     * @throws Exception
     */
    public function getNextInstanceId($record_id, $event_id=null)
    {
        // Find the last instance and add 1 to it. If there are no current instances, return 1.
        $last_index = $this->getLastInstanceId($record_id, $event_id);
        if (!$last_index) {
            return 1;
        } else {
            return ++$last_index;
        }
    }

    /**
     * This function will return an array of instance_ids for this record/event.
     *
     * @param $record_id
     * @param null $event_id
     * @return array of instance IDs
     */
    public function getAllInstanceIds($record_id, $event_id=null)
    {
        $this->setUp($record_id, $event_id);
        // All instance IDs
        if ($this->is_longitudinal) {
            return array_keys($this->data[$record_id][$event_id] ?? []);
        } else if (is_null($this->data) || is_null($this->data[$record_id])) {
            return null;
        } else {
            return array_keys($this->data[$record_id]);
        }
    }


    /**
     * This function will save an instance of data.  If the instance_id is supplied, it will overwrite
     * the current data for that instance with the supplied data. An instance_id must be supplied since
     * instance 1 is actually stored as null in the database.  If an instance is not supplied, an error
     * will be returned.
     *
     * @param $record_id
     * @param $data
     * @param null $instance_id
     * @param null $event_id
     * @return true | false (if an error occurs)
     * @throws Exception
     */
    public function saveInstance($record_id, $data, $instance_id = null, $event_id = null)
    {
        if ($this->is_longitudinal && is_null($event_id)) {
            throw new \Exception("Event ID Required");
        } else if (!$this->is_longitudinal) {
            $event_id = $this->event_id;
        }
        // If the instance ID is null, get the next one because we are saving a new instance
        if (is_null($instance_id)) {
            throw new \Exception("Instance ID required");
        } else {
            $next_instance_id = $instance_id;
        }
        // Include instance and format into REDCap expected format
        $new_instance[$record_id]['repeat_instances'][$event_id][$this->instrument][$next_instance_id] = $data;

        $return = \REDCap::saveData($this->pid, 'array', $new_instance);
        if (!isset($return["errors"]) and ($return["item_count"] <= 0)) {
            throw new \Exception("Errors encountered while trying to save instance");
        } else {
            return true;
        }
    }

    /**
     * This function will delete the specified instance of a repeating form or repeating event.
     *
     * @param $record_id
     * @param $instance_id
     * @param null $event_id
     * @return int $log_id - log entry number for this delete action
     * @throws Exception
     */
    public function deleteInstance($record_id, $instance_id, $event_id = null, $change_reason="", $userid_logging="")
    {
        // If longitudinal and event_id = null, send back an error
        if ($this->is_longitudinal && is_null($event_id)) {
            throw new \Exception("Event ID Required");
        }
        $log_id = \Records::deleteForm($this->pid, $record_id, $this->instrument, $event_id, $instance_id, null, $change_reason, $userid_logging);
        return $log_id;
    }

    /**
     * Return the data dictionary for this form
     *
     * @return array
     */
    public function getDataDictionary()
    {
        return $this->data_dictionary;
    }

    /**
     * Returns whether or not this project is longitudinal or not
     *
     * @return boolean
     */
    public function isProjectLongitudinal()
    {
        return $this->is_longitudinal;
    }

    /**
     * This function will look for the data supplied in the given record/event and send back the instance
     * number if found.  The data supplied does not need to be all the data in the instance, just the data that
     * you want to search on.
     *
     * @param $needle
     * @param $record_id
     * @param null $event_id
     * @return int | false (if an error occurs)
     * @throws Exception
     */
    public function exists($needle, $record_id, $event_id=null)
    {
        $this->setUp($record_id, $event_id);
        // Look for the supplied data in an already created instance
        $found_instance_id = null;
        $size_of_needle = sizeof($needle);
        if ($this->is_longitudinal) {
            foreach ($this->data[$record_id][$event_id] as $instance_id => $instance) {
                $intersected_fields = array_intersect_assoc($instance, $needle);
                if (sizeof($intersected_fields) == $size_of_needle) {
                    $found_instance_id = $instance_id;
                }
            }
        } else {
            foreach ($this->data[$this->record_id] as $instance_id => $instance) {
                $intersected_fields = array_intersect_assoc($instance, $needle);
                if (sizeof($intersected_fields) == $size_of_needle) {
                    $found_instance_id = $instance_id;
                }
            }
        }
        // Supplied data did not match any instance data
        if (is_null($found_instance_id)) {
            throw new \Exception("Instance not found");
        }
        return $found_instance_id;
    }

}
