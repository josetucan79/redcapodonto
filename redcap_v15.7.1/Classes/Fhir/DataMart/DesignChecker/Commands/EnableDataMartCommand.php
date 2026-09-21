<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;

use Project;

class EnableDataMartCommand extends AbstractCommand {

    /**
     *
     * @var Project
     */
    protected $receiver;

    protected $criticality = self::CRITICALITY_HIGH;
    protected $action_type = self::ACTION_TYPE_AUTO;

    /**
     *
     * @param Project $receiver
     */
    public function __construct($receiver)
    {
        parent::__construct(...func_get_args());
    }

    public function execute()
    {
        return $this->receiver->enableDataMartFeature();
    }

    public function undo()
    {
        return $this->receiver->disableDataMartFeature();
    }

    public function __toString()
    {
        return sprintf('enable Data Mart');
    }

}