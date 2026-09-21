<?php
namespace Vanderbilt\REDCap\Classes\Utility\REDCapData;

use Generator;
use Project;
use Vanderbilt\REDCap\Classes\Utility\REDCapData\RecordHandlers\RecordHandlerInterface;

class RecordProcessor
{
    /**
     *
     * @var Project
     */
    protected $project;
    protected $repeating_forms;
    protected $forms_metadata;

    public function __construct(Project $project) {
        $this->project = $project;
        $this->repeating_forms = $project->RepeatingFormsEvents;
        $this->forms_metadata = $project->forms;
    }

    /**
     * Generator to process records and yield them one at a time with structure.
     */
    /**
     *
     * @param [type] $result
     * @param RecordHandlerInterface[] $modifiers
     * @return Generator
     */
    public function process($result, $modifiers = []) {
        $current_record = null;
        $record_data = [];

        // Step 1: Loop through the result set
        while ($row = db_fetch_assoc($result)) {
            $record_id = $row['record'];
            $event_id = $row['event_id'];
            $instance = $row['instance'] ?? 1;
            $field_name = $row['field_name'];
            $value = $row['value'];

            // Step 2: If we have switched to a new record, yield the previous one
            if ($current_record !== null && $record_id !== $current_record) {
                // Apply all modifiers to the record data
                $record_data = $this->applyHandlers($current_record, $record_data, $modifiers);

                // Yield the modified record data (if not null)
                if ($record_data !== null) {
                    yield $current_record => $record_data;
                }

                // Reset for the new record
                $record_data = [];
            }

            // Update current record
            $current_record = $record_id;

            // Step 3: Determine if the form is repeating or not
            $form_name = $this->getFormNameForField($field_name);

            if ($this->isRepeatingForm($form_name, $event_id)) {
                // Step 4: Handle repeating forms
                $this->addDataToRepeatingForm($record_data, $event_id, $instance, $form_name, $field_name, $value);
            } else {
                // Step 5: Handle non-repeating forms (event-level data)
                $this->addDataToNonRepeatingForm($record_data, $event_id, $field_name, $value);
            }
        }

        // Step 6: Yield the last record (if any)
        if ($current_record !== null) {
            $record_data = $this->applyHandlers($current_record, $record_data, $modifiers);
            if ($record_data !== null) {
                yield $current_record => $record_data;
            }
        }
    }

    /**
     * Apply a series of modifiers to the record data.
     */
    private function applyHandlers($record_id, $record_data, array $modifiers) {
        foreach ($modifiers as $modifier) {
            // Ensure that each modifier implements the RecordHandlerInterface
            if (!$modifier instanceof RecordHandlerInterface) {
                throw new \InvalidArgumentException("Modifier must implement RecordModifierInterface");
            }

            // Apply each modifier in sequence
            $record_data = $modifier->handle($record_id, $record_data);

            // If a modifier returns null, stop processing and filter out the record
            if ($record_data === null) {
                return null;
            }
        }

        return $record_data;
    }

    /**
     * Helper method to add data to repeating forms.
     */
    private function addDataToRepeatingForm(&$data, $event_id, $instance, $form_name, $field_name, $value) {
        // Initialize the repeat_instances structure if it doesn't exist
        if (!isset($data['repeat_instances'])) {
            $data['repeat_instances'] = [];
        }

        // Initialize the event under repeat_instances if not set
        if (!isset($data['repeat_instances'][$event_id])) {
            $data['repeat_instances'][$event_id] = [];
        }

        // Initialize the form under the event if not set
        if (!isset($data['repeat_instances'][$event_id][$form_name])) {
            $data['repeat_instances'][$event_id][$form_name] = [];
        }

        // Initialize the specific instance
        if (!isset($data['repeat_instances'][$event_id][$form_name][$instance])) {
            $data['repeat_instances'][$event_id][$form_name][$instance] = [];
        }

        // Set the value for the specific field under this instance
        $data['repeat_instances'][$event_id][$form_name][$instance][$field_name] = $value;
    }

    /**
     * Helper method to add data to non-repeating forms.
     */
    private function addDataToNonRepeatingForm(&$data, $event_id, $field_name, $value) {
        // Initialize the event if not set
        if (!isset($data[$event_id])) {
            $data[$event_id] = [];
        }

        // Set the value for the specific field under the event
        $data[$event_id][$field_name] = $value;
    }

    /**
     * Determines if a form is a repeating form for the given event.
     */
    private function isRepeatingForm($form_name, $event_id, $repeating_forms =null) {
        $repeating_forms = $repeating_forms ?? $this->repeating_forms;
        return isset($repeating_forms[$event_id]) && in_array($form_name, array_keys($repeating_forms[$event_id]));
    }

    /**
     * Helper method to find the form name for a given field.
     */
    private function getFormNameForField($field_name, $forms_metadata=null) {
        $forms_metadata = $forms_metadata ?? $this->forms_metadata;
        foreach ($forms_metadata as $form_name => $form_data) {
            if (isset($form_data['fields']) && array_key_exists($field_name, $form_data['fields'])) {
                return $form_name;
            }
        }

        return null;  // Field not found in any form
    }
}
