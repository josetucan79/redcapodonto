<?php

namespace Vanderbilt\REDCap\Classes\Utility\REDCapData;

use Project;
use Vanderbilt\REDCap\Classes\Rewards\Utility\ProjectArmFetcher;

class EventFilter {
    private $project;
    private $eventIds = [];

    public function __construct(Project $project) {
        $this->project = $project;
    }

    /**
     * Add specific event IDs to the filter.
     */
    public function addEventIds(array $event_ids) {
        $this->eventIds = array_unique(array_merge($this->eventIds, $event_ids));
        return $this;
    }

    /**
     * Add arms and convert them to event IDs.
     */
    public function addArmNumbers(array $arm_numbers) {
        $arms_data = ProjectArmFetcher::getProjectArms($this->project->project['project_id']);

        foreach ($arm_numbers as $arm_number) {
            if (isset($arms_data[$arm_number]['events'])) {
                $events = array_keys($arms_data[$arm_number]['events']);
                $this->addEventIds($events);
            }
        }

        return $this;
    }
    
    public function getEventIds(): array { return $this->eventIds; }

    /**
     * Helper method to get event IDs for the project.
     */
    public function getProjectEventIds(): array {
        $event_ids = [];
    
        // Check if the project has arms and events
        if (isset($this->project->events)) {
            foreach ($this->project->events as $arm) {
                if (isset($arm['events']) && is_array($arm['events'])) {
                    // Loop through the events in each arm and collect the event ids
                    foreach ($arm['events'] as $event_id => $event_data) {
                        $event_ids[] = $event_id;
                    }
                }
            }
        }
    
        return $event_ids;
    }
}
