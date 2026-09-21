<?php
namespace Vanderbilt\REDCap\Classes\Cache\Exceptions;

use Exception;

/**
 * Exception thrown when serialization fails due to memory constraints or other issues
 */
class SerializationMemoryException extends Exception
{
    const REASON_MEMORY_LIMIT           = 'memory_limit';
    const REASON_SERIALIZATION_ERROR    = 'serialization_error';
    const REASON_DATA_TOO_LARGE         = 'data_too_large';

    private $reason;
    private $dataType;
    private $memoryUsage;

    public function __construct($message = "", $reason = self::REASON_SERIALIZATION_ERROR, $dataType = 'unknown', $memoryUsage = null, $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->reason = $reason;
        $this->dataType = $dataType;
        $this->memoryUsage = $memoryUsage;
    }

    /**
     * Get the reason for the serialization failure
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * Get the data type that failed to serialize
     * @return string
     */
    public function getDataType()
    {
        return $this->dataType;
    }

    /**
     * Get the memory usage at the time of failure
     * @return int|null
     */
    public function getMemoryUsage()
    {
        return $this->memoryUsage;
    }

    /**
     * Check if the failure was due to memory constraints
     * @return bool
     */
    public function isMemoryRelated()
    {
        return in_array($this->reason, [self::REASON_MEMORY_LIMIT, self::REASON_DATA_TOO_LARGE]);
    }
}