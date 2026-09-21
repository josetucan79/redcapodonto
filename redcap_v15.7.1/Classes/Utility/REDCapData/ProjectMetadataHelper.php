<?php
namespace Vanderbilt\REDCap\Classes\Utility\REDCapData;

use Project;

class ProjectMetadataHelper {
    /**
     * @var Project
     */
    private $project;

    public function __construct(Project $project) {
        $this->project = $project;
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
}