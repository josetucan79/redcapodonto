<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;

use Vanderbilt\REDCap\Classes\ProjectDesigner;

class CreateFormCommand extends AbstractCommand {

    
    /**
     * @var ProjectDesigner
     */
    protected $receiver;

    /**
     * @var string
     */
    protected $formName;

    /**
     * @var string
     */
    protected $formLabel;

    protected $criticality = self::CRITICALITY_MEDIUM;
    protected $action_type = self::ACTION_TYPE_AUTO;

    /**
     *
     * @param ProjectDesigner $receiver
     * @param string $formName
     * @param string $formLabel
     */
    public function __construct($receiver, $formName, $formLabel)
    {
        parent::__construct(...func_get_args());
        $this->formName = $formName;
        $this->formLabel = $formLabel;
    }

    public function execute()
    {
        $result = $this->receiver->createForm($this->formName, null, $this->formLabel);
        if(!$result) $this->throwError("Error creating the form '{$this->formName}'");
    }

    public function undo()
    {
        $result = $this->receiver->deleteForm($this->formName);
        if(!$result) $this->throwError("Error deleting the form '{$this->formName}'");
    }

    public function __toString()
    {
        return sprintf('create form `%s` (%s)', $this->formName, $this->formLabel);
    }

}