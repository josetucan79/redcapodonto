<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Exception;
use Vanderbilt\REDCap\Classes\Rewards\Settings\Project\ProjectSettingsValueObject as ProjectProjectSettingsValueObject;
use Vanderbilt\REDCap\Classes\Rewards\Settings\ProjectSettingsValueObject;

class EmailTemplateSmartVariableCriterion extends AbstractCriterion {
    const SMART_VARIABLE_REDEEM_LINK = '[redeem-link]';
    const SMART_VARIABLE_REDEEM_URL = '[redeem-url]';
    /**
     * The smart variables to check for in the email template.
     * 
     * @var array
     */
    private $requiredSmartVariables;

    /**
     *
     * @var ProjectSettingsValueObject
     */
    private $settings;


    /**
     *
     * @param ProjectSettingsValueObject $project
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->requiredSmartVariables = [
            self::SMART_VARIABLE_REDEEM_LINK,
            self::SMART_VARIABLE_REDEEM_URL
        ];
    }

    /**
     * Checks whether the criterion is met by verifying if the email template contains the required smart variable.
     *
     * @return bool Returns true if the criterion is met, false otherwise.
     */
    public function check() {
        try {
            $settings = $this->settings;
            if(!($settings instanceof ProjectProjectSettingsValueObject)) throw new Exception("No settings availablefor this project.", 400);
    
            $email_template = $settings->getEmailTemplate() ?? '';
            // Check if at least one of the required smart variables is present
            foreach ($this->requiredSmartVariables as $smartVariable) {
                if (strpos($email_template, $smartVariable) !== false) {
                    return true;
                }
            }
    
            return false;
        } catch (\Exception $e) {
            $this->addError($e);
            return false;
        }
    }
    
    /**
     * Provides a title of the criterion.
     *
     * @return string A human-readable title of the criterion.
     */
    public function getTitle() {
        return $this->lang['rewards_email_template_smart_variable_criterion_title'] ?? 'Email Template Contains Required Smart Variable';
    }

    /**
     * Gets the description of the criterion and steps to take if not met.
     *
     * @return string The detailed description of the criterion.
     */
    public function getDescription() {
        return $this->lang['rewards_email_template_smart_variable_criterion_description'] ?? '';
    }
}
