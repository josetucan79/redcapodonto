<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;

use Vanderbilt\REDCap\Classes\ProjectDesigner;

/**
 * move a section header from a REDCap variable to another
 */
class MoveSectionHeaderCommand extends AbstractCommand {

    
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
    protected $text;
    
    /**
     * name of suorce field
     * 
     * @var string
     */
    protected $to;
    
    /**
     * name of target field
     * 
     * @var string
     */
    protected $from;

    protected $criticality = self::CRITICALITY_LOW;
    protected $action_type = self::ACTION_TYPE_AUTO;


    public function __construct($receiver, $formName, $text, $to, $from)
    {
        parent::__construct(...func_get_args());
        $this->formName = $formName;
        $this->text = $text;
        $this->to = $to;
        $this->from = $from;

    }

    public function execute()
    {
        return $this->receiver->moveSectionHeader($this->from, $this->to);
    }

    public function undo()
    {
        print 'undo';
    }

    public function __toString()
    {
        return sprintf(
            "move section header '%s' of form `%s` from `%s` to `%s`",
            $this->text, $this->formName, $this->from, $this->to
        );
    }

}