<?php
namespace Vanderbilt\REDCap\Classes\Queue;

use DateTime;
use Exception;
use Vanderbilt\REDCap\Classes\Queue\Message;
use Vanderbilt\REDCap\Classes\SystemMonitors\ResourceMonitor;

class Worker {

    const LOG_OOBJECT_TYPE = 'QUEUE_WORKER';

    /**
     *
     * @var Queue
     */
    private $queue;
    

    const MAX_EXECUTION_TIME = 60*10; // 600 seconds (10 minutes)

    /**
     * Constructor: Setup our enviroment, load the queue and then
     * process the message.
     */
    /**
     *
     * @param Queue $queue
     * @param int $max_processing
     * @param int $max_processing_per_type
     */
    public function __construct($queue)
    {
        # Get the queue
        $this->queue = $queue;
    }

    /**
     * cleanup operations
     */
    public function cleanup() {
        $this->queue->updateStuckMessages();
        $this->queue->deleteOldMessages();
    }


    /**
     * amount of time after which the worker
     * should stop processing messages
     *
     * @return integer
     */
    private function getMaximumExecutionTime()
    {
        return self::MAX_EXECUTION_TIME;
    }

    /**
     * Process a message and handle the task
     *
     * @param Message $message
     * @return string
     */
    public function processMessage($message)
    {
        try {
            $messageID = $message->getId();
            $task = $message->getTask();
            if(!$task) throw new Exception("Task assigned to Message has a wrong format", 400);
            
            $message->status = Message::STATUS_PROCESSING;
            $message->started_at = new DateTime();
            $this->queue->updateMessage($message);
            
            $task->handle(); // start processing
            $message->status = $status = Message::STATUS_COMPLETED;
            $message->completed_at = new DateTime();
            $message->message = $logMessage = "Message ID '{$messageID}' has been successfully processed.";
            $this->queue->updateMessage($message);

            \Logging::logEvent( $sql="", self::LOG_OOBJECT_TYPE, "MANAGE", $messageID, "", $logMessage);
            return $status;
        } catch (\Throwable $e) {
            $messageID = $message->getId();
            $message->status = $status = Message::STATUS_ERROR;
            $message->completed_at = new DateTime();
            $message->message = $logMessage = "Error processing Message ID '{$messageID}'. {$e->getMessage()} ({$e->getCode()})";
            $this->queue->updateMessage($message);
            \Logging::logEvent( $sql="", self::LOG_OOBJECT_TYPE, "MANAGE", $messageID, "", $logMessage);
            return $status;
        }finally {

        }
    }

    /**
     *
     * @return array
     */
    public function process()
    {
        $resourceMonitor = ResourceMonitor::create([
            'time' => $this->getMaximumExecutionTime()
        ]);

        $metadata = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
        ];

        while($message = $this->queue->getHighestPriorityMessage()) {
            $status = $this->processMessage($message);
            $metadata['processed']++;
            $metadata['successful'] += ($status===Message::STATUS_COMPLETED) ? 1 : 0;
            $metadata['failed'] += ($status!==Message::STATUS_COMPLETED) ? 1 : 0;
            
            // Check memory and time status using ResourceMonitor
            if (!$resourceMonitor->checkResources()) {
                break;  // Stop processing if memory or time exceeds the limit
            }
        }
        return $metadata;
    }

}