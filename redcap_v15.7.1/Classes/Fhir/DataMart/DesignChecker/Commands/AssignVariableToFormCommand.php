<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;

use Vanderbilt\REDCap\Classes\ProjectDesigner;

class AssignVariableToFormCommand extends AbstractCommand {

    /**
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
     * @var string
     */
    protected $previousForm;

    protected $criticality = self::CRITICALITY_HIGH;
    protected $action_type = self::ACTION_TYPE_AUTO;

    /**
     *
     * @param ProjectDesigner $receiver
     * @param string $formName
     * @param array $settings
     * @param string $previousForm
     */
    public function __construct($receiver, $formName, $settings, $previousForm)
    {
        parent::__construct(...func_get_args());
        $this->formName = $formName;
        $this->settings = $settings;
        $this->previousForm = $previousForm; // in case we want to undo
    }

    public function execute()
    {
        $variableName = $variableName = $this->settings['field_name'] ?? '';
        return $this->receiver->assignFieldToForm($variableName, $this->formName);
    }

    public function undo()
    {
        $variableName = $variableName = $this->settings['field_name'] ?? '';
        return $this->receiver->assignFieldToForm($variableName, $this->previousForm);
    }

    public function __toString()
    {
        $variableName = $this->settings['field_name'] ?? '';
        return sprintf('assign variable `%s` to form `%s`', $variableName, $this->formName);
    }

}