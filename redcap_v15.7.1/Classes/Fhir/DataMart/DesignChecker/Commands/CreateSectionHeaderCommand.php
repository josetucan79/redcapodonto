<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;

use Project;
use Vanderbilt\REDCap\Classes\ProjectDesigner;

class CreateSectionHeaderCommand extends AbstractCommand {

        
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
     * @var string
     */
    protected $variableName;
    
    /**
     *
     * @var string
     */
    protected $text;

    protected $criticality = self::CRITICALITY_LOW;
    protected $action_type = self::ACTION_TYPE_AUTO;

    /**
     *
     * @param ProjectDesigner $receiver
     * @param string $formName
     * @param string $variableName
     * @param string $text
     */
    public function __construct($receiver, $formName, $variableName, $text)
    {
        parent::__construct(...func_get_args());
        $this->formName = $formName;
        $this->variableName = $variableName;
        $this->text = $text;
    }

    public function execute()
    {
        return $this->receiver->createSectionHeader($this->text, $this->variableName);
    }

    public function undo()
    {
        print 'undo';
    }

    public function __toString()
    {
        return sprintf("create section header '%s' in form `%s` before variable `%s`", $this->text, $this->formName, $this->variableName);
    }

}