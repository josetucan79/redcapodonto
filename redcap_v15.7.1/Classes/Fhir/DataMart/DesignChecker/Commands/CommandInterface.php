<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;

interface CommandInterface {

    public function execute();

    public function undo();

}