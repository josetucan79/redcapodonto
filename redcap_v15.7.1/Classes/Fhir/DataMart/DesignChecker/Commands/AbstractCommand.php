<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;

use Exception;
use JsonSerializable;

abstract class AbstractCommand implements CommandInterface, JsonSerializable
{

    protected $id;

    /**
     *
     * @var mixed
     */
    protected $receiver;

    /**
     * list of parameters
     * to use during execution
     *
     * @var array
     */
    protected $parameters =[];

    /**
     * patch criticality levels
     */
    const CRITICALITY_LOW      = 1;
    const CRITICALITY_MEDIUM   = 2;
    const CRITICALITY_HIGH     = 3;
    const CRITICALITY_CRITICAL = 4;

    /**
     * type of action associted with the command
     */
    const ACTION_TYPE_AUTO = 'automatic_action'; // command will be executed automatically by REDCap
    const ACTION_TYPE_MANUAL = 'manual_action'; // command must be manually executed by the user

    /**
     * default criticality level is HIGH
     *
     * @var integer
     */
    protected $criticality = self::CRITICALITY_HIGH;

    /**
     * determine the type of action
     *
     * @var string
     */
    protected $action_type = null;

    /**
     *
     * @param mixed $receiver
     * @param mixed ...$parameters
     */
    public function __construct($receiver, ...$parameters)
    {
        $this->id = spl_object_hash($this); // create a unique ID for the command
        $this->receiver = $receiver;
        $this->parameters = $parameters;
    }

    /**
     * get a unique key for this object
     *
     * @return string
     */
    function getID() { return $this->id; }


    /**
     * criticality level of the patch
     *
     * @return integer
     */
    function getCriticality() { return $this->criticality; }


    /**
     * type of action associated with the command
     *
     * @return string
     */
    function getActionType() { return $this->action_type; }

    /**
     * execute a command
     *
     * @return mixed
     */
    public function execute()
    {
        print 'command executed';
    }

    public function undo()
    {
        print 'command';
    }

    /**
     * throw an error message and append the DB message if available
     *
     * @param string $message
     * @param integer $code
     * @return void
     */
    public function throwError($message, $code=1)
    {
        $dbError = db_error() ?? '';
        if($dbError) $message .= PHP_EOL.$dbError;
        throw new Exception($message, $code);
    }

    public function __toString()
    {
        return __CLASS__;
    }

    public function jsonSerialize():array
    {
        $data = [
            'id' => $this->getID(),
            'criticality' => $this->getCriticality(),
            'action_type' => $this->getActionType(),
            'parameters' => $this->parameters,
            'description' => strval($this),
        ];
        return $data;
    }

}