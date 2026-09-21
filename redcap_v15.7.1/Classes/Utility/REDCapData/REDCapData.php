<?php
namespace Vanderbilt\REDCap\Classes\Utility\REDCapData;

use Project;
use Vanderbilt\REDCap\Classes\Utility\GeneratorUtils;
use Vanderbilt\REDCap\Classes\Utility\REDCapData\RecordHandlers\RecordHandlerInterface;

class REDCapData {
    private $project;
    private $fields;
    private $start = 0;
    private $limit = null;
    /**
     *
     * @var RecordHandlerInterface[]
     */
    private $recordHandlers = [];
    
    // helper classes
    private $recordProcessor;
    private $conditionBuilder;
    private $eventFilter;
    private $queryBuilder;

    /**
     * Constructor initializes the project and helper classes.
     */
    private function __construct(Project $project) {
        $this->project = $project;
        $project->setRepeatingFormsEvents();
        
        // Initialize helper classes
        $metadataHelper = new ProjectMetadataHelper($project);
        $this->fields = array_keys($metadataHelper->getMetadata()); // Default to all fields
        $this->recordProcessor = new RecordProcessor($project);
        $this->conditionBuilder = new ConditionBuilder();
        $this->eventFilter = new EventFilter($project);
        $this->queryBuilder = new QueryBuilder($project);
    }

    /**
     * Static method to create an instance for a specific project.
     */
    public static function forProject($project_id) {
        $project = new Project($project_id);
        return new self($project);
    }

    /**
     * Filter by forms and extract fields from the specified forms.
     */
    public function whereForms(array $form_names) {
        // Extract the fields for the specified forms
        $form_fields = [];
        foreach ($form_names as $form_name) {
            if (isset($this->project->forms[$form_name]['fields'])) {
                $form_fields = array_merge($form_fields, array_keys($this->project->forms[$form_name]['fields']));
            }
        }

        // Filter the fields to include only those from the specified forms
        $this->fields = array_intersect($this->fields, $form_fields);
        return $this;
    }

    /**
     * Filter by specific fields.
     */
    public function whereFields(array $field_names) {
        // Filter the fields to include only the specified field names
        $this->fields = array_intersect($this->fields, $field_names);
        return $this;
    }

    /**
     * Add a condition to filter by specific events using the EventFilter.
     */
    public function whereEvents(array $event_ids) {
        $this->eventFilter->addEventIds($event_ids);
        return $this;
    }

    /**
     * Add a condition to filter by arms and convert them to event IDs.
     */
    public function whereArms(array $arm_numbers) {
        $this->eventFilter->addArmNumbers($arm_numbers);
        return $this;
    }

    /**
     * Add a condition to filter by specific records.
     */
    public function whereRecords(array $record_ids) {
        $this->conditionBuilder->addCondition('record', $record_ids, 'IN', 'AND');
        return $this;
    }

    /**
     * Set the starting point and limit for the query results.
     */
    public function limit(int $start, int $limit): self {
        $this->start = $start;
        $this->limit = $limit;
        return $this;
    }

    public function addRecordHandler(RecordHandlerInterface $handler) {
        $this->recordHandlers[] = $handler;
        return $this;
    }

    /**
     * Generate and execute the dynamic query using the QueryBuilder.
     */
    public function get(&$result = null) {

        // add events to the conditions
        $event_ids = $this->eventFilter->getEventIds();
        if(count($event_ids)) {
            $this->conditionBuilder->addCondition('event_id', $event_ids, 'IN', 'AND');
        }
        
        // add fields to the conditions
        if(count($this->fields)) {
            $this->conditionBuilder->addCondition('field_name', $this->fields, 'IN', 'AND');
        }

        // Build the main data query
        $sql = $this->queryBuilder
            ->applyConditions($this->conditionBuilder)
            ->build();

        // Get the parameters for the query
        $params = $this->queryBuilder->getParams();

        // Execute the query
        $result = db_query($sql, $params);

        // Process the result using RecordProcessor
        $generator = $this->recordProcessor->process($result, $this->recordHandlers);
        return GeneratorUtils::skip($generator, $this->start, $this->limit);
    }

    /**
     * Get project metadata based on the draft status.
     */
    public function getMetadata() {
        if ($this->projectIsDraftMode()) {
            return $this->project->metadata_temp;
        }
        return $this->project->metadata;
    }

    /**
     * Check if the project is in development mode.
     */
    public function projectIsDevelopment() {
        return intval($this->project->project['status'] ?? 0) === 0;
    }

    /**
     * Check if the project is in production mode.
     */
    public function projectIsProduction() {
        return !$this->projectIsDevelopment();
    }

    /**
     * Check if the project is in draft mode.
     */
    public function projectIsDraftMode() {
        if ($this->projectIsDevelopment()) return false;
        return intval($this->project->project['draft_mode'] ?? 0) === 1;
    }

    public function __invoke()
    {
        return $this->get();
    }


    /* public function get1() {
        // Step 1: Build the dynamic pivot query using the filtered fields
        if (empty($this->fields)) {
            return []; // No fields found, return empty array
        }
        $params = [];

        // Prepare dynamic CASE statements for each field
        $field_case_statements = [];
        foreach ($this->fields as $field_name) {
            $field_case_statements[] = "MAX(CASE WHEN field_name = '$field_name' THEN value END) AS `$field_name`";
        }
        $field_case_sql = implode(", ", $field_case_statements);

        // Step 2: Build the base query
        $base_sql = "
            SELECT record, event_id, $field_case_sql
            FROM redcap_data
            WHERE project_id = ?
        ";


        // Step 4: Complete the query
        $base_sql .= " GROUP BY record, event_id ORDER BY record, event_id";

        // Step 5: Execute the query
        $result = db_query($base_sql, $this->$params);

        // Step 6: Fetch the results using a generator
        while ($row = db_fetch_assoc($result)) {
            yield $row['record'] => $row;
        }
    } */


    /**
     * the logic for these where functions must be updated to filter the resulting records
     * currently it applies conditions on the query used to build the records data
     */

    /**
     * Add a simple where condition using the ConditionBuilder.
     */
    public function where($field_name, $value = null, $operator = '=') {
        $this->conditionBuilder->addCondition($field_name, $value, $operator, 'AND');
        return $this;
    }

    /**
     * Add an OR where condition.
     */
    public function orWhere($field_name, $value, $operator = '=') {
        $this->conditionBuilder->addCondition($field_name, $value, $operator, 'OR');
        return $this;
    }

    /**
     * Create a group of conditions with parentheses.
     */
    public function whereGroup(callable $callback, $logicalOperator = 'AND') {
        $this->conditionBuilder->addGroup($callback, $logicalOperator);
        return $this;
    }
}
