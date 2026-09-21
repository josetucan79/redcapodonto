<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Queue\Message;
use Vanderbilt\REDCap\Classes\Queue\Queue;
use Vanderbilt\REDCap\Classes\Queue\Worker;

class QueueTest extends TestCase
{
	const TASK_KEY = 'test-task';
	const TASK_TEXT = 'This is a test';

	public function setUp(): void
	{
		// $_SERVER['REQUEST_URI'] = 'somepage';
	}

	public function testAddTask() {
		$queue = new Queue();
		$logFilePath = $this->getLogFilePath();
		$logEntry = $this->getLogEntry(self::TASK_TEXT);
		$callable = function() use($logFilePath, $logEntry) {
			file_put_contents($logFilePath, $logEntry, FILE_APPEND);
		};
		$messageID = $queue->addMessage($callable, self::TASK_KEY, $description='Test adding new task');
		$this->assertIsNumeric($messageID);
	}

	public function testDoNotAddTaskIfExists() {
		$queue = new Queue();
		$logFilePath = $this->getLogFilePath();
		$callable = function() use($logFilePath){
			$entry = 'task with existing key was run at ' . (new DateTime())->format('Y-m-d H:i:s') . PHP_EOL;
			file_put_contents($logFilePath, $entry, FILE_APPEND);
		};
		$result = $queue->addMessageIfNotExists($callable, self::TASK_KEY, $description='Test adding task with existing key');
		$this->assertFalse($result);
	}

	public function testGetMessageListUsingQuery() {
		$queue = new Queue();
		$generator = $queue->getList(0,0,self::TASK_KEY);
		$list = iterator_to_array($generator);
		$totalMessages = count($list);
		$this->assertGreaterThan(0, $totalMessages);
	}

	public function testGetHighestPriorityMessage() {
		$queue = new Queue();
		$message = $queue->getHighestPriorityMessage();
		$this->assertTrue($message instanceof Message);
	}

	public function testProcessTask() {
		$queue = new Queue();
		$worker = new Worker($queue);
		$metadata = $worker->process();
		$worker->cleanup(); // update and delete items in the queue table

		$totalProcesed = $metadata['processed'] ?? 0;
		$totalSuccessful = $metadata['successful'] ?? 0;
		$totalFailed = $metadata['failed'] ?? 999;
		$this->assertGreaterThan(0, $totalProcesed);
		$this->assertGreaterThan(0, $totalSuccessful);
		$this->assertEquals(0, $totalFailed);
	}

	public function testTaskWasRun() {
		$logFilePath = $this->getLogFilePath();
		$lastEntry = $this->getLastLineInFile($logFilePath);
		$this->assertStringContainsString(self::TASK_TEXT, $lastEntry);
	}

	public function testDeleteAllMessages() {
		$queue = new Queue();
		$generator = $queue->getList();
		$results = [];
		/** @var Message $message */
		foreach ($generator as $message) {
			$results[] = $queue->deleteMessage($message->getId());
		}
		$totalDeleted = count($results);
		$this->assertGreaterThan(0, $totalDeleted);
	}

	private function getLastLineInFile($filePath) {
		if (!file_exists($filePath)) return;
		// Read the file into an array, one line per element.
		$lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		
		// Get the last line from the array
		$lastLine = array_pop($lines);
		
		return $lastLine;
	}

	private function getLogFilePath() {
		return APP_PATH_TEMP . 'queue_test.log';
	}

	private function getLogEntry($text) {
		return $text . ' - ' . (new DateTime())->format('Y-m-d H:i:s') . PHP_EOL;
	}
}

