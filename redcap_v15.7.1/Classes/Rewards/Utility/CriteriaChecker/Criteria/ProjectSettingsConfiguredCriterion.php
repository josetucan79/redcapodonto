<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Vanderbilt\REDCap\Classes\Rewards\Settings\Project\ProjectSettingsValueObject as ProjectProjectSettingsValueObject;
use Vanderbilt\REDCap\Classes\Rewards\Settings\ProjectSettingsValueObject;

class ProjectSettingsConfiguredCriterion extends AbstractCriterion {
    /**
     *
     * @var ProjectSettingsValueObject
     */
    private $settings;

    /**
     *
     * @param ProjectSettingsValueObject $settings
     */
    public function __construct($settings) {
        $this->settings = $settings;
    }

    /**
     * Checks whether the criterion is met.
     *
     * @return bool Returns true if the criterion is met, false otherwise.
     */
    public function check() {
        try {
            $settings = $this->settings;
            if(!($settings instanceof ProjectProjectSettingsValueObject)) throw new \Exception("No settings availablefor this project.", 400);
            $requiredFields = [
                'email_template' => $settings->getEmailTemplate(),
                'email_subject' => $settings->getEmailSubject(),
                'email_from' => $settings->getEmailFrom(),
                'preview_expression' => $settings->getPreviewExpression(),
            ];
        
            foreach ($requiredFields as $field => $value) {
                if ($this->isEmpty($value, $field)) {
                    return false;
                }
            }
        
            return true;
        } catch (\Exception $e) {
            $this->addError($e);
            return false;
        }
    }

    /**
     *
     * @param mixed $value
     * @param string $field
     * @return boolean
     */
    private function isEmpty($value, $field) {
        if (empty($value)) {
            // add logging or error handling here if needed
            return true;
        }
        return false;
    }
    
    /**
     * Provides a title of the criterion.
     *
     * @return string A human-readable title of the criterion.
     */
    public function getTitle() {
        return $this->lang['rewards_project_settings_criterion_title'] ?? 'Project settings are configured';
    }

    /**
     * Gets the description of the criterion and steps to take if not met.
     *
     * @return string The detailed description of the criterion.
     */
    public function getDescription() {
        return $this->lang['rewards_project_settings_criterion_description'] ?? '';
    }
}

