<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Queue\Queue;
use Vanderbilt\REDCap\Classes\Queue\Task;
use Vanderbilt\REDCap\Classes\Queue\Worker;

class TaskTest extends TestCase {
    
    public function testAddRawTask() {
        $queue = new Queue();
        $data = 'O:47:"Laravel\SerializableClosure\SerializableClosure":1:{s:12:"serializable";O:46:"Laravel\SerializableClosure\Serializers\Native":5:{s:3:"use";a:0:{}s:8:"function";s:0:"";s:5:"scope";N;s:4:"this";N;s:4:"self";s:32:"0000000000001b3f0000000000000000";}}';
		$messageID = $queue->addRawMessage($data, 'test-malformed-task', $description='Test adding new malformed task');
		$this->assertIsNumeric($messageID);
    }

    public function testProcessTask() {
		$queue = new Queue();
		$worker = new Worker($queue);
		$metadata = $worker->process();
		$worker->cleanup(); // update and delete items in the queue table

		$totalFailed = $metadata['failed'] ?? 0;
		$this->assertGreaterThan(0, $totalFailed);
	}

}