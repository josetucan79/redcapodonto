<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication;

use REDCap;
use Records;
use Project;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs\MappingInfoDTO;

class DataRetrievalService
{
    // Properties
    private $projectId;
    private $project;
    private $ddp;
    private $mapping;

    // Constructor
    public function __construct(Project $project, $ddp)
    {
        $this->projectId = $project->project_id;
        $this->project = $project;
        $this->ddp = $ddp;
    }

    public function getFieldMappings()
    {
        if(!$this->mapping) {
            // Retrieve field mappings between external source and REDCap fields
            $this->mapping = $this->ddp->getMappedFields();
        }
        return $this->mapping;
    }

    /**
     *
     * @param array $mappings
     * @return MappingInfoDTO
     */
    public function processMappedFields() {
        $mappings = $this->getFieldMappings();
        ## GET EXISTING REDCAP DATA
		// Loop through all mapped fields to get fields/event_ids needed for REDCap data pull
		$record_identifiers = $rc_mapped_fields = $rc_mapped_events = $map_ids = $map_id_list = $temporal_fields = [];
		foreach ($mappings as $src_field=>$event_attr) {
			foreach ($event_attr as $this_event_id=>$field_attr) {
				// Add event_id
				$rc_mapped_events[] = $this_event_id;
				// Loop through fields
				foreach ($field_attr as $rc_field=>$attr) {
					// Add field
					$rc_mapped_fields[] = $rc_field;
					// Segregate map_id's into separate array with event_id-field as keys
					$map_ids[$src_field][$this_event_id][$rc_field] = $attr['map_id'];
					// Put all map_ids in an array
					$map_id_list[] = $attr['map_id'];
                    // track record identifiers
                    if ($attr['is_record_identifier'] == true) {
                        $record_identifiers[$this_event_id] = $rc_field;
                    }
					// Add temporary field to array
					if ($attr['temporal_field'] != '') {
						$temporal_fields[$rc_field] = $attr['temporal_field'];
					}
				}
			}
		}
		$rc_mapped_events = array_unique($rc_mapped_events);
		$rc_mapped_fields = array_unique($rc_mapped_fields);
		
		// Loop through mapped RC fields and create array of JUST the multiple choice fields with their enums as a sub-array
		// (to use for displaying the option choice in adjudication table).
		$rc_mapped_fields_choices = array();
		foreach ($rc_mapped_fields as $this_field) {
			if ($this->project->isMultipleChoice($this_field)) {
				$rc_mapped_fields_choices[$this_field] = parseEnum($this->project->metadata[$this_field]['element_enum']);
			}
		}
		
		// If project is using repeating forms, then include the form status field of each mapped field so that
		// every instance of that form is returned from getData()
		if ($this->project->hasRepeatingFormsEvents()) {
			$rc_mapped_fields_form_status = array();
			foreach ($rc_mapped_fields as $this_field) {
				$rc_mapped_fields_form_status[] = $this->project->metadata[$this_field]['form_name']."_complete";
			}
			$rc_mapped_fields = array_merge($rc_mapped_fields, array_unique($rc_mapped_fields_form_status));
		}

        
        $mappingInfoParams = [
            'mapped_fields' => $rc_mapped_fields,
            'mapped_events' => $rc_mapped_events,
            'map_id_list' => $map_id_list,
            'record_identifiers' => $record_identifiers,
            'temporal_fields' => $temporal_fields,
        ];
        return new MappingInfoDTO($mappingInfoParams);
    }

    /**
     *
     * @param string $record
     * @param MappingInfoDTO $mappingInfo
     * @return array
     */
    public function fetchRedcapData($record, $mappingInfo)
    {
        $fields = $mappingInfo->getMappedFields();
        $events = $mappingInfo->getMappedEvents();
        $temporal_fields = array_unique($mappingInfo->getTemporalFields());
        $allFields = array_merge($fields, $temporal_fields);
        // Fetch existing REDCap data for the specified record, fields, and events
        return Records::getData($this->projectId, 'array', [$record], $allFields, $events);
    }

    /**
     * when in a form, merge any existing data with the one entered in the form
     *
     * @param array $existingData
     * @param array $formData
     * @param string $record
     * @param ?integer $event_id
     * @param integer $instance
     * @param string $repeat_instrument
     * @return void
     */
    public function mergeFormData($existingData, $formData, $record, $event_id, $instance, $repeat_instrument)
    {
        // Merge unsaved form data with existing REDCap data
        if (!empty($formData) && is_numeric($event_id)) {
            foreach ($formData as $key => $val) {
                if ($instance < 1) {
                    $existingData[$record][$event_id][$key] = $val;
                } else {
                    // Add in repeating instrument format
                    $existingData[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$key] = $val;
                }
            }
        }
        return $existingData;
    }


    public function getLockedFormsAndEvents($record)
    {
        // Retrieve information about locked forms/events for the given record
        $lockedFormsEvents = array();
        $sql = "SELECT event_id, form_name, instance FROM redcap_locking_data 
                WHERE project_id = {$this->projectId} AND record = '" . db_escape($record) . "'";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Set instance to 0 for non-repeating forms
            if (!$this->project->isRepeatingForm($row['event_id'], $row['form_name']) && !$this->project->isRepeatingEvent($row['event_id'])) {
                $row['instance'] = 0;
            }
            $lockedFormsEvents[$row['event_id']][$row['form_name']][$row['instance']] = true;
        }
        return $lockedFormsEvents;
    }

    public function prepareSourceData($data_array_src)
    {
        // Process and structure the source data for efficient merging
        $sourceData = [];
        foreach ($data_array_src as $attr) {
            if (isset($attr['timestamp'])) {
                // Clean the timestamp
                $attr['timestamp'] = substr($attr['timestamp'], 0, 19);
                $sourceData[$attr['field']][] = array(
                    'src_value' => $attr['value'],
                    'src_timestamp' => $attr['timestamp'],
                    'md_id' => $attr['md_id']
                );
            } else {
                $sourceData[$attr['field']][] = array(
                    'src_value' => $attr['value'],
                    'md_id' => $attr['md_id']
                );
            }
        }
        return $sourceData;
    }
}
