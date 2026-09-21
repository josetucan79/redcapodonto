<?php
namespace Vanderbilt\REDCap\Classes\Queue;

use DateTime;

/**
 * Queues allow you to defer the processing of a time consuming task, such as sending an email,
 * until a later time.
 * Deferring these time consuming tasks drastically speeds up web requests to REDCap.
 * 
 * Each 'message' added to the queue is translated into a 'task' and processed by a 'worker'.
 * A cron job checks the queue every minute and creates a worker if there are messages 'waiting' to be processed.
 * If a worker cannot process all 'messages' in a minute, another worker will help processing 'messages'.
 * A maximum of 5 workers can run at the same time, but this number can be increased.
 * If a worker has been working for more than 10 minutes it will not process any further message.
 * 
 * Tasks can be specialised classes that implement the TaskInterface or can be Closures.
 * Examples for both cases are in the 'ClinicalDataMartDataFetch' Job.
 * 
 */
class Queue
{

    const TABLE_NAME = 'redcap_queue';
    const LOG_OBJECT_TYPE = 'QUEUE';
    const MAX_COMPLETED_LIFESPAN = '2 DAY'; // max lifespan for completed messages
    const MAX_PROCESSING_TIME = '5 HOUR'; // max executime time allowed for a single task
    const MAX_DATA_SIZE_IN_BITS = 512000; // 512000 = 64Kb = max size of a BLOB field in MySQL


    public function __construct() {

    }

    public function getTableName() { return self::TABLE_NAME; }

    /**
     * alternate way to get a list using the page and perPage logic
     *
     * @param integer $page
     * @param integer $perPage
     * @param string $query
     * @return array
     */
    public function getListAtPage($page=1, $perPage=50, $query='') {
        if($page<1) $page = 1;
        $start = ($page*$perPage)-$perPage;
        $generator = $this->getList($start, $perPage, $query, $metadata);
        $response = [
            'metadata' => $metadata,
            'data' => [],
        ];
        while($message = $generator->current()) {
            $generator->next();
            $response['data'][] = $message;
        }
        return $response;
    }
  
    /**
     * getList: Returns a generator with Messages ready to be processed.
     * @param integer $start
     * @param integer $limit
     * @param string $query
     * @return \Generator
     */
    public function getList($start=0, $limit=0, $query=null, &$metadata=null)
    {
        // init metadata
        $metadata = [
            'partialTotal' => 0,
            'total' => 0,
        ];
        $tableName = $this->getTableName();
        $allQueryString = "SELECT * FROM $tableName";
        $result = db_query($allQueryString, []);
        $total = db_num_rows($result);
        
        $queryParams = [];
        $queryString = "SELECT * FROM $tableName";
        if($query) {
            $sanitizedQuery = db_escape($query);
            $queryParams[] = "%$sanitizedQuery%";
            $queryString .= "\nWHERE CONCAT_WS('-', `key`,`description`,`status`,`priority`,`message`) LIKE ?";
            // $queryParams[] = $query;
        }
        $queryString .= "\nORDER BY `id` ASC";
        if($start>=0 && $limit>0) {
            $queryString .= "\nLIMIT ?, ?";
            $queryParams = array_merge($queryParams, [$start, $limit]); // add for prepare
        }
        
        $result = db_query($queryString, $queryParams);
        $partialTotal = db_num_rows($result);
        $metadata = [
            'partialTotal' => $partialTotal,
            'total' => $total,
        ];

        while($row = db_fetch_assoc($result)) {
            $message = new Message($row);
            yield $message;
        }
    }

    /**
     * get a message from the database
     *
     * @param int $message_id
     * @return Message|null
     */
    public function getMessage($message_id)
    {
        $query_string = "SELECT * FROM {$this->getTableName()} WHERE `id`=?";
        $result = db_query($query_string, [$message_id]);
        $message = null;
        if($row = db_fetch_assoc($result)) {
            $message = new Message($row);
        }
        return $message;
    }

    /**
     * get all messages with a specific key and optionally
     * with one or more statuses
     *
     * @param string $messageKey
     * @param array $statuses
     * @return Message[]
     */
    public function getMessagesByKey($messageKey, $statuses=[]) {
        $addQuotes = function($str) {
            return sprintf("'%s'", $str);
        };
        $query_string = "SELECT * FROM {$this->getTableName()} WHERE `key`=?";
        if(!empty($statuses)) {
            $validStatuses = array_reduce($statuses, function($carry, $status) {
                if(in_array($status, Message::statuses())) $carry[] = $status;
                return $carry;
            }, []);
            $quotesStatuses =  implode(',', array_map($addQuotes, $validStatuses));
            $query_string .= sprintf("\nAND `status` IN (%s)", $quotesStatuses);
        }
        $result = db_query($query_string, [$messageKey]);
        $messages = [];
        while($row = db_fetch_assoc($result)) {
            $messages[] = new Message($row);
        }
        return $messages;
    }



    /**
     * get total number of messages being processed that did not exceed
     * the MAX_PROCESSING_TIME
     *
     * @return integer
     */
    public function getTotalActiveMessages() {
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $maxInterval = self::MAX_PROCESSING_TIME;
        $queryString = "SELECT count(1) AS `total` FROM {$this->getTableName()} WHERE `status`=? AND `started_at` < DATE_SUB(?, INTERVAL {$maxInterval})";
        $result = db_query($queryString, [Message::STATUS_PROCESSING, $now]);
        $total = 0;
        if($row = db_fetch_assoc($result)) $total = $row['total'];
        return intval($total);
    }

    /**
     * get the list of messages that have been in the PROCESSING stats for
     * more than the MAX_PROCESSING_TIME 
     *
     * @return Message[]
     */
    public function getStuckMessages() {
        $maxInterval = self::MAX_PROCESSING_TIME;
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $queryString = "SELECT * FROM {$this->getTableName()} WHERE `status`=? AND `created_at` < DATE_SUB(?, INTERVAL {$maxInterval})";
        $result = db_query($queryString, [Message::STATUS_PROCESSING, $now]);
        $messages = [];
        while($row = db_fetch_assoc($result)) {
            $messages[] = new Message($row);
        }
        return $messages;
    }

    /**
     * delete old messages that have been processed
     *
     * @return boolean
     */
    public function deleteOldMessages() {
        $lifespan = self::MAX_COMPLETED_LIFESPAN;
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $queryString = "DELETE FROM {$this->getTableName()}
                WHERE (
                    `completed_at` IS NOT NULL AND
                    `completed_at` < DATE_SUB(?, INTERVAL {$lifespan})
                ) OR (
                    `completed_at` IS NULL AND
                    `created_at` < DATE_SUB(?, INTERVAL {$lifespan})
                )";
        return db_query($queryString, [$now, $now]);
    }

    public function deleteMessage($messageID) {
        if(!isinteger($messageID)) return;
        $table = $this->getTableName();
        $queryString = "DELETE FROM $table WHERE `id` = ? AND `status` != ?";
        return db_query($queryString, [$messageID, Message::STATUS_PROCESSING]);
    }

    /**
     * update the status of the stuck messages.
     * a message is condidered 'stuck' if the task has been running for
     * longer than expected
     *
     * @return void
     */
    public function updateStuckMessages() {
        $messages = $this->getStuckMessages();
        foreach ($messages as $message) {
            $message->status = Message::STATUS_WARNING;
            $message->completed_at = new DateTime();
            $message->message = sprintf('Warning: the execution time for the task associated to this message took longer than expected (%s).', self::MAX_PROCESSING_TIME);
            $this->updateMessage($message);
        }
    }

    /**
     * return the highest priority message
     * that is also waiting to be processed;
     *
     * @return Message|false
     */
    public function getHighestPriorityMessage() {
        $queryString = "SELECT * FROM {$this->getTableName()}
                        WHERE `status`=?
                        ORDER BY
                        `priority` DESC,
                        `created_at` ASC
                        LIMIT 1";
        $result = db_query($queryString, [Message::STATUS_WAITING]);
        if(!$result) return false;
        $row = db_fetch_assoc($result);
        if(!$row) return false; 
        $message = new Message($row);
        return $message;
    }


     /**
     * Adds a raw message to the database.
     *
     * @param string $serializedTask Serialized task string
     * @param string $messageKey Identifier for the message
     * @param string $description Description of the task/message
     * @param array $options additional parameters for the Message
     * @return int The ID of the inserted message
     * @throws \Exception If the insert fails
     */
    public function addRawMessage($serializedTask, $messageKey = '', $description = '', $options =[]) {
        if($messageKey==='') {
            $calleeInfo = $this->getCalleeInfo();
            $messageKey = $calleeInfo;
        }

        $defaultParams = [
            'data'        => $serializedTask,
            'key'         => $messageKey,
            'description' => $description,
            'created_at'  => new DateTime(),
        ];

        // Merge options with defaults.
        // Note: array_merge($options, $defaultParams) ensures that any keys in $defaultParams
        // will override the corresponding keys in $options.
        $params = array_merge($options, $defaultParams);
        
        $message = new Message($params);
        
        $messageData = $message->toArray();
        $fields = [];
        $params = [];
        foreach ($messageData as $key => $value) {
            $fields[] = "`$key`";
            $params[] = $value;
        }
        $placeholders = dbQueryGeneratePlaceholdersForArray($params);
        $tableName = $this->getTableName();
        $queryString = "INSERT INTO $tableName ";
        $queryString .= sprintf('(%s)', join(',', $fields));
        $queryString .= " VALUES ($placeholders)"; // fill with question marks for prepared statement
        $result = db_query($queryString, $params);

        if($result && $id=db_insert_id()) {
            \Logging::logEvent( $sql=$queryString, self::LOG_OBJECT_TYPE, "MANAGE", "", "", "Message added to the queue.");
            return $id;
        }else {
            \Logging::logEvent( $sql=$queryString, self::LOG_OBJECT_TYPE, "ERROR", "", "", "Error adding message to the queue.");
            throw new \Exception("Error adding message to queue", 1);
        }
    }

    /**
     * Create a task, assign it to a message, and store it in the database.
     *
     * @param callable $callable A callable function
     * @param string $messageKey Identifier for the message
     * @param string $description Description of the task/message
     * @param array $options additional parameters for the Message
     * @return int The ID of the inserted message
     * @throws \Exception If the insert fails
     */
    public function addMessage($callable, $messageKey = '', $description = '', $options=[]) {
        $task = new Task($callable);
        $serializedTask = $task->serialize();
        return $this->addRawMessage($serializedTask, $messageKey, $description, $options);
    }

    /**
     * Adds a message to the queue only if there is no existing message with the same key
     * that is currently in either the "waiting" or "processing" state.
     *
     * This method checks for existing messages using the provided key and, if any message
     * with a status of STATUS_WAITING or STATUS_PROCESSING is found, it will not add a new message.
     *
     * @param callable $callable   The callable function to be executed for the task.
     * @param string   $messageKey A unique identifier for the message/task.
     * @param string   $description Optional description of the task.
     * @return int|bool Returns the inserted message ID if the new message is added successfully;
     *                  returns false if a message with the same key and the specified statuses already exists.
     */
    public function addMessageIfNotExists($callable, $messageKey, $description='') {
        $existingMessages = $this->getMessagesByKey($messageKey, [Message::STATUS_WAITING, Message::STATUS_PROCESSING]);
        
        if (count($existingMessages) > 0) {
            return false;
        }
        
        return $this->addMessage($callable, $messageKey, $description);
    }

    /**
     * get info about the callee of a function
     *
     * @return void
     */
    private function getCalleeInfo() {
        //get the trace
        $trace = debug_backtrace();
        // Get the class that is asking for who awoke it
        $class = $trace[1]['class'];
        // +1 because we account for calling this function
        for ( $i=1; $i<count( $trace ); $i++ ) {
            if ( isset( $trace[$i] ) && $class != $trace[$i]['class'] ) {
                $infoClass = $trace[$i]['class'] ?? '';
                $infoType = $trace[$i]['type'] ?? '';
                $infoFunction = $trace[$i]['function'] ?? '';
                return implode('', [$infoClass, $infoType, $infoFunction]);
            }
        }
    }

    /**
     * add message without instantiating a queue object
     *
     * @param callable $callable
     * @param string $key
     * @param string $description
     * @return void
     */
    public static function add($callable, $key='', $description='') {
        $queue = new Queue();
        return $queue->addMessage($callable, $key, $description);
    }

    /**
     * use a message to update its content in the database
     *
     * @param Message $message
     * @return boolean
     * @throws Exception if message cannot be uodated
     */
    public function updateMessage(Message $message)
    {
        $id = $message->getId();
        if(!$id) return false;
        $queryString = "UPDATE {$this->getTableName()} SET";
        $messageData = $message->toArray();
        $setStatements = [];
        $queryParams = [];
        foreach ($messageData as $key => $value) {
            if($key==='id') continue; // skip id
            $setStatements[] = "\n`$key` = ?";
            $queryParams[] = $value;
        }
        $queryParams[] = $id;
        $queryString .= join(',',$setStatements);
        $queryString .= "\nWHERE `id` = ?";
        
        $result = db_query($queryString, $queryParams);
        if(!$result) {
            \Logging::logEvent( $sql=$queryString, self::LOG_OBJECT_TYPE, "MANAGE", "", "", $message="Error updating the message.");
            throw new \Exception($message, 400);
        }
        \Logging::logEvent( $sql=$queryString, self::LOG_OBJECT_TYPE, "MANAGE", "", "", $message="Queue message updated.");
        return $result;
    }

    /**
     * check the status of a stored message
     *
     * @param Message $message
     * @param string $status
     * @return string|false
     */
    public static function checkMessageStatus($message, $status)
    {
        $id = $message->getId();
        $query_string = sprintf("SELECT status FROM `%s` WHERE `id`='%u'", self::TABLE_NAME, $id);
        $result = db_query($query_string);
        if($row=db_fetch_object($result)) return $row->status==$status;
        return false;
    }

}