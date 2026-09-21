<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;

use Vanderbilt\REDCap\Classes\ProjectDesigner;

class MakeFormRepeatableCommand extends AbstractCommand {

    /**
     * @var ProjectDesigner
     */
    protected $receiver;

    /**
     * @var string
     */
    protected $formName;

    /**
     * @var int
     */
    protected $eventID;

    protected $criticality = self::CRITICALITY_HIGH;
    protected $action_type = self::ACTION_TYPE_AUTO;

    /**
     *
     * @param ProjectDesigner $designChecker
     * @param string $formName
     * @param int $eventID
     */
    public function __construct($receiver, $formName, $eventID)
    {
        parent::__construct(...func_get_args());
        $this->formName = $formName;
        $this->eventID = $eventID;
    }

    public function execute()
    {
        return $this->receiver->makeFormRepeatable($this->formName, $this->eventID);
    }

    public function undo()
    {
        return $this->receiver->makeFormNotRepeatable($this->formName, $this->eventID);
    }

    public function __toString()
    {
        return sprintf('make form `%s` repeatable', $this->formName);
    }

}