<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;


use Vanderbilt\REDCap\Classes\ProjectDesigner;

/**
 * change the relative order of a variable
 * in its containing form
 */
class SetVariableRelativeOrderCommand extends AbstractCommand {

    protected $criticality = self::CRITICALITY_HIGH;
    protected $action_type = self::ACTION_TYPE_AUTO;

    /**
     *
     * @var ProjectDesigner
     */
    protected $receiver;
    /**
     *
     * @var string
     */
    protected $variableName;
    /**
     *
     * @var int
     */
    protected $relativePosition;

    /**
     *
     * @param ProjectDesigner $designChecker
     * @param string $variableName
     * @param int $relativePosition zero based position of a variable in the form
     */
    public function __construct($receiver, $variableName, $relativePosition)
    {
        parent::__construct(...func_get_args());
        $this->variableName = $variableName;
        $this->relativePosition = $relativePosition;
    }

    public function execute()
    {
        return $this->receiver->setFieldRelativePosition($this->variableName, $this->relativePosition);
    }

    public function undo()
    {
        print 'undo';
    }

    public function __toString()
    {
        return sprintf("set order of variable `%s` to %u (relative to its container form)", $this->variableName, $this->relativePosition+1);
    }

}