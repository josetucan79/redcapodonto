<?php

namespace Vanderbilt\REDCap\Classes\Fhir;

/**
 * a JsonSerializable exception that can be consumed by clients
 */
class SerializableException extends \Exception implements \JsonSerializable
{
    /**
     * additional data can be attached to this exception
     *
     * @var mixed
     */
    private $data;

    public function __construct($message = null, $code = 0, $previous = null, $data = null)
    {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    /**
     * get additional data
     *
     * @return void
     */
    public function getData()
    {
        return $this->data;
    }

    public function jsonSerialize():array
    {
        $data = array(
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'previous' => $this->getPrevious(),
        );

        return $data;
    }
}