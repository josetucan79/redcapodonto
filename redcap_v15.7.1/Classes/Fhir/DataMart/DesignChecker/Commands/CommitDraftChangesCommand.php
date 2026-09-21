<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;


class CommitDraftChangesCommand extends AbstractCommand {

    protected $criticality = self::CRITICALITY_HIGH;
    protected $action_type = self::ACTION_TYPE_MANUAL;

    public function __construct($receiver)
    {
        parent::__construct(...func_get_args());
    }

    public function execute()
    {
        return;
    }

    public function undo()
    {
        return;
    }

    public function __toString()
    {
        return sprintf('visit the project designer page and commit the draft changes');
    }

}