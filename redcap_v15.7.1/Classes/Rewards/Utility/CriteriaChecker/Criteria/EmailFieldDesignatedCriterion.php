<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Project;

class EmailFieldDesignatedCriterion extends AbstractCriterion {
    

    /**
     *
     * @var Project
     */
    private $project;

    /**
     *
     * @param Project $project
     */
    public function __construct($project) {
        $this->project = $project;
    }

    /**
     * Checks whether the criterion is met.
     *
     * @return bool Returns true if the criterion is met, false otherwise.
     */
    public function check() {
        $emailFields = $this->project->getSurveyEmailInvitationFields($includeProjectLevelEmailField=true);
        return is_array($emailFields) && count($emailFields) > 0;
    }
    
    /**
     * Provides a title of the criterion.
     *
     * @return string A human-readable title of the criterion.
     */
    public function getTitle() {
        return $this->lang['rewards_email_field_criterion_title'] ?? 'Email Field Designation';
    }

    /**
     * Gets the description of the criterion and steps to take if not met.
     *
     * @return string The detailed description of the criterion.
     */
    public function getDescription() {
        return $this->lang['rewards_email_field_criterion_description'] ?? '';
    }
}

