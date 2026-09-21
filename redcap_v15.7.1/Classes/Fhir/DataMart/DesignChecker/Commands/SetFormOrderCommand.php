<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;


use Vanderbilt\REDCap\Classes\ProjectDesigner;

/**
 * change the relative order of a variable
 * in its containing form
 */
class SetFormOrderCommand extends AbstractCommand {

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
    protected $formName;
    
    /**
     *
     * @var int
     */
    protected $redcapFormPosition;

    /**
     *
     * @param ProjectDesigner $designChecker
     * @param string $formName
     * @param int $index zero based index of a variable in the form
     */
    public function __construct($receiver, $formName, $index)
    {
        parent::__construct(...func_get_args());
        $this->formName = $formName;
        $this->redcapFormPosition = $index+1; // convert to REDCap numbering (1 based)
    }

    public function execute()
    {
        return $this->receiver->updateFormOrder($this->formName, $this->redcapFormPosition);
    }

    public function undo()
    {
        print 'undo';
    }

    public function __toString()
    {
        return sprintf("set order of form `%s` to %u", $this->formName, $this->redcapFormPosition);
    }

}