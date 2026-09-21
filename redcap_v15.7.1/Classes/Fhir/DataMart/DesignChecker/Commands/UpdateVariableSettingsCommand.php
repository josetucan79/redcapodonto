<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;

use Vanderbilt\REDCap\Classes\ProjectDesigner;

class UpdateVariableSettingsCommand extends AbstractCommand {

    
    /**
     *
     * @var ProjectDesigner
     */
    protected $receiver;
    
    /**
     *
     * @var string
     */
    protected $formName;
    
    /**
     *
     * @var array
     */
    protected $settings;
    
    /**
     *
     * @var array
     */
    protected $settingsMismatch;

    protected $criticality = self::CRITICALITY_HIGH;
    protected $action_type = self::ACTION_TYPE_AUTO;

    /**
     *
     * @param ProjectDesigner $designChecker
     * @param string $formName
     * @param array $settings
     * @param array $settingsMismatch
     */
    public function __construct($receiver, $formName, $settings, $settingsMismatch)
    {
        parent::__construct(...func_get_args());
        $this->formName = $formName;
        $this->settings = $settings;
        $this->settingsMismatch = $settingsMismatch;
    }

    public function execute()
    {
        $variableName = $this->settings['field_name'] ?? '';
        // $settings = array_filter($this->settings, [Document::class, 'isVariablePrivateSetting'], ARRAY_FILTER_USE_KEY);
        return $this->receiver->updateField($this->formName, $variableName, $this->settings);
    }

    public function undo()
    {
        print 'undo';
    }

    public function __toString()
    {
        $getUpdateDetails = function() {
            $updateStrings = [];
            foreach ($this->settingsMismatch as $key => $value) {
                $updateStrings[] = sprintf('%s : %s', $key, $value);
            }
            $updateDetails = implode(PHP_EOL, $updateStrings);
            return $updateDetails;
        };
        $variableName = $this->settings['field_name'] ?? '';
        $updateDetails = $getUpdateDetails(); 
        return sprintf("update settings for variable `%s` in form `%s`:\n\r%s", $variableName, $this->formName, $updateDetails);
    }

}