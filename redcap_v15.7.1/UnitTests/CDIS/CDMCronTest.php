<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Queue\Queue;
use Vanderbilt\REDCap\Classes\Queue\Worker;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMartBackgroundRunner;

class CDMCronTest extends TestCase
{
	public function setUp(): void
	{
	}

	public function testGetCronEnabledRevisions() {
		$dataMart = new DataMart(0); // do not provide any specific user ID
		$revisions = $dataMart->getCronEnabledRevisions();
		$this->assertIsArray($revisions);
	}

	public function testRunScheduledRevision() {
		$dataMart = new DataMart(0); // do not provide any specific user ID
		$bgRunner = new DataMartBackgroundRunner($dataMart);
		$revisions = $dataMart->getCronEnabledRevisions();
		$firstRevision = reset($revisions);
		if(!$firstRevision) return $this->assertTrue(true, 'no revisions available for testing');
		$messageID = $bgRunner->schedule($firstRevision, $mrn_list=null, $sendFeedback=false);
		$this->assertIsInt($messageID, 'message was scheduled successfully');
		$queue = new Queue();
		$message = $queue->getMessage($messageID);
		$worker = new Worker($queue);
		$worker->processMessage($message);
		$deleted = $queue->deleteMessage($messageID);
		$this->assertTrue($deleted, 'message was deleted successfully');
	}

	public function testBackgroundRunner() {
		$dataMart = new DataMart(0); // do not provide any specific user ID
		$bgRunner = new DataMartBackgroundRunner($dataMart);
		$revisions = $dataMart->getCronEnabledRevisions();
		$firstRevision = reset($revisions);
		if(!$firstRevision) return $this->assertTrue(true, 'no revisions available for testing');
		$bgRunner->process($firstRevision->id);
		$this->assertTrue(true, 'revision run successfully');
	}
	
}

