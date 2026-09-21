<?php

include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

# Validate form and field names
$field = $_POST['field'];
if (!isset($Proj->metadata[$field])) {
	header("HTTP/1.0 503 Internal Server Error");
	return;
}

// If we have a allowlist of records/events due to report filtering, unserialize it
$includeRecordsEvents = (isset($_POST['includeRecordsEvents'])) ? unserialize(decrypt($_POST['includeRecordsEvents']), ['allowed_classes'=>false]) : array();
// If $includeRecordsEvents is passed and not empty, then it will be the record/event allowlist
$checkIncludeRecordsEvents = (!empty($includeRecordsEvents));


// If fields being used exist on a repeating form, then add to other array
$fields = $fields_orig = [$field];
foreach ($fields_orig as $this_field) {
    $this_form = $Proj->metadata[$this_field]['form_name'];
    if ($Proj->isRepeatingFormAnyEvent($this_form)) {
        $fields[] = $this_form . "_complete";
    }
}
// Get data for records
$records = $checkIncludeRecordsEvents ? array_keys($includeRecordsEvents) : [];
$field_data_missing = Records::getData('array', $records, array_merge(array($table_pk), $fields), array(), $user_rights['group_id'], false, false, false, '',
                                        false, false, false, false, false, array(), false, false, false, false, false, false, 'EVENT', false, false, false, true);
// Remove all non-applicable fields for events and repeating forms, then remove non-blank values
Records::removeNonApplicableFieldsFromDataArray($field_data_missing, $Proj, true);

// Now we have an array with all missing values for all records-events, so loop through it and add to results
$data = array();
foreach ($field_data_missing as $record=>$event_data) {
    foreach (array_keys($event_data) as $event_id) {
        if ($event_id == 'repeat_instances') {
            $eventNormalized = $event_data['repeat_instances'];
        } else {
            $eventNormalized = array();
            $eventNormalized[$event_id][""][0] = $event_data[$event_id];
        }
        foreach ($eventNormalized as $event_id => $data1) {
            foreach ($data1 as $repeat_instrument => $data2) {
                foreach ($data2 as $instance => $data3) {
                    foreach ($data3 as $field => $value) {
                        $form = $Proj->metadata[$field]['form_name'];
	                    if ($instance == '' || $instance == '0') $instance = '1';
                        // If we have a record/event allowlist, then check the record/event
                        if ($checkIncludeRecordsEvents) {
                            // If a repeating form or event
                            if ($Proj->isRepeatingFormOrEvent($event_id, $form)) {
                                if ($Proj->isRepeatingEvent($event_id)) {
                                    // Repeating event (no repeating instrument = blank)
                                    $repeat_instrument = "";
                                } else {
                                    // Repeating form
                                    $repeat_instrument = $form;
                                }
                                if (!isset($includeRecordsEvents[$record][$event_id][$instance."-".$repeat_instrument])) {
                                    continue;
                                }
                            }
                            // Non-repeating
                            elseif (!isset($includeRecordsEvents[$record][$event_id])) {
                                continue;
                            }
                        }                        
                        // Is event_id valid for this field's form?
                        if (!$longitudinal || ($longitudinal && in_array($form, $Proj->eventsForms[$event_id]))) {
                            // Only add to output if field's form is used for this event
                            $data[] = removeDDEending($record) . ":" . $event_id . ":" . $instance;
                        }
                    }
                }
            }
        }
    }
    unset($field_data_missing[$record]);
}

// Output response
header('Content-type: text/plain');
print count($data) . '|' . implode('|', $data);
