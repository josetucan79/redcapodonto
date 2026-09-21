<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;


use Vanderbilt\REDCap\Classes\ProjectDesigner;

/**
 * change the relative order of a variable
 * in its containing form
 */
class ClonePrimaryKeyEntriesCommand extends AbstractCommand {

    /**
     *
     * @var ProjectDesigner
     */
    protected $receiver;
    
    /**
     *
     * @var string
     */
    protected $currentPrimaryKey;
    
    /**
     *
     * @var string
     */
    protected $targetPrimaryKey;

    protected $criticality = self::CRITICALITY_HIGH;
    protected $action_type = self::ACTION_TYPE_MANUAL;
    

    /**
     *
     * @param ProjectDesigner $designChecker
     * @param string $primaryKey
     */
    public function __construct($receiver, $currentPrimaryKey, $targetPrimaryKey)
    {
        parent::__construct(...func_get_args());
        $this->currentPrimaryKey = $currentPrimaryKey;
        $this->targetPrimaryKey = $targetPrimaryKey;
    }

    public function execute()
    {

    }

    public function undo()
    {
        print 'undo';
    }

    public function __toString()
    {
        return sprintf("wrong primary key detected; please change your primary key from `%s` to `%s` or your existing data could be orphaned", $this->currentPrimaryKey, $this->targetPrimaryKey);
    }

}