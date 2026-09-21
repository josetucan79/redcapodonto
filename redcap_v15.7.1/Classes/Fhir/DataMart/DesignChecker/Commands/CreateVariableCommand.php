<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;


use Vanderbilt\REDCap\Classes\ProjectDesigner;

class CreateVariableCommand extends AbstractCommand {

    
    /**
     * @var ProjectDesigner
     */
    protected $receiver;
    
    /**
     * @var string
     */
    protected $formName;
    
    /**
     * @var array
     */
    protected $settings;

    protected $criticality = self::CRITICALITY_HIGH;
    protected $action_type = self::ACTION_TYPE_AUTO;

    /**
     *
     * @param ProjectDesigner $designChecker
     * @param string $formName
     * @param array $settings [groupName, name, mandatory, label, dataType, fieldType, validationType, choices[value, label]
     */
    public function __construct($receiver, $formName, $settings)
    {
        parent::__construct(...func_get_args());
        $this->settings = $settings;
        $this->formName = $formName;
    }

    public function execute()
    {
        // $settings = array_filter($this->settings, [Document::class, 'isVariablePrivateSetting'], ARRAY_FILTER_USE_KEY);
        return $this->receiver->createField($this->formName, $this->settings, $next_field_name='');  
    }

    public function undo()
    {
        print 'undo';
    }

    public function __toString()
    {
        $variableName = $this->settings['field_name'] ?? '';
        return sprintf('create variable `%s` in form `%s`', $variableName, $this->formName);
    }

}