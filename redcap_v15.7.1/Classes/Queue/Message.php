<?php
namespace Vanderbilt\REDCap\Classes\Queue;

use JsonSerializable;
use Vanderbilt\REDCap\Classes\Traits\CanMakeDateTime;
use Vanderbilt\REDCap\Classes\Traits\CanPrintDate;

/**
 * The 'Queue' class will be our simple way to reference
 * the queue regardless of which stage we are at.
 * We're defining a constant arbitary integer that we'll use as the queue
 * identifier and two integer values that we will use to reference
 * the type of message in the queue.
 */
class Message implements JsonSerializable
{
    use CanMakeDateTime;
    use CanPrintDate;

    const PRIORITY_LOW = 10;
    const PRIORITY_NORMAL = 20;
    const PRIORITY_HIGH = 30;
    const PRIORITY_URGENT = 40;

    const STATUS_WAITING = 'waiting';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ERROR = 'error';
    const STATUS_WARNING = 'warning';
    const STATUS_CANCELED = 'canceled';

    private $properties = [
        'id' => null,
        'data' => null,
        'key' => null,
        'description' => null,
        'status' => self::STATUS_WAITING,
        'priority' => self::PRIORITY_NORMAL,
        'message' => '',
        'created_at' => null,
        'started_at' => null,
        'completed_at' => null,
    ];

    public function __construct($params)
    {
        /* $data = $params['data'];
        if(is_string($data)) {
            $params['data'] = unserialize($data);
        } */
        foreach ($params as $key => $value) {
            if(!array_key_exists($key, $this->properties)) continue;
            $this->{$key} = $value;
        }
    }

    public static function statuses() {
        return [
            self::STATUS_WAITING,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
            self::STATUS_ERROR,
            self::STATUS_WARNING,
            self::STATUS_CANCELED,
        ];
    }

    public function getTask() {
        $data = $this->getData();
        return Task::fromSerializedData($data);
    }

    /**
     * getKey: Returns the key
     */
    public function getId() { return $this->id; }
    public function getData() { return $this->data; }
    public function getKey() { return $this->key; }
    public function getDescription() { return $this->description; }
    public function getStatus() { return $this->status; }
    public function getPriority() { return $this->priority; }
    public function getMessage() { return $this->message; }
    public function getCreatedAt() { return $this->created_at; }
    public function getStartedAt() { return $this->started_at; }
    public function getCompletedAt() { return $this->completed_at; }

    public function getProperties() { return array_keys($this->properties); }

    public function toArray() {
        $createdAt = $this->getCreatedAt();
        $startedAt = $this->getStartedAt();
        $completedAt = $this->getCompletedAt();

        /** 
         * convert empty string to null value so it is
         * properly stored in the database
         */
        $dateOrNull = function($date) {
            $dateAsString = $this->printDate($date);
            if($dateAsString==='') return null;
            return $dateAsString;
        };
        return [
            'id' => $this->getId(),
            'data' => $this->getData(),
            'key' => $this->getKey(),
            'description' => $this->getDescription(),
            'status' => $this->getStatus(),
            'priority' => $this->getPriority(),
            'message' => $this->getMessage(),
            'created_at' => $dateOrNull($createdAt),
            'started_at' => $dateOrNull($startedAt),
            'completed_at' => $dateOrNull($completedAt),
        ];
    }


    public function __get($name) {
        if(array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        }

        $trace = debug_backtrace();
            trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }

    private function visit($key, $value) {
        switch ($key) {
            case 'id':
            case 'priority':
                $value = intval($value);
                break;
            case 'created_at':
            case 'started_at':
            case 'completed_at':
                $value = $this->makeDateTime($value);
                break;
            case 'status':
                break;
            case 'key':
            case 'description':
            case 'data':
            case 'message':
            default:
                break;
        }
        return $value;
    }

    public function __set($name, $value) {
        if(!array_key_exists($name, $this->properties)) return;
        $value = $this->visit($name, $value);
        $this->properties[$name] = $value;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        $data = $this->toArray();
        $data['data'] = ''; // remove task from payload
        return $data;
    }
}