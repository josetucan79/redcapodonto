<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Email\DynamicVariablesParser;
use Vanderbilt\REDCap\Classes\Email\EmailScheduler;
use Vanderbilt\REDCap\Classes\Queue\Message;
use Vanderbilt\REDCap\Classes\Queue\Queue;
use Vanderbilt\REDCap\Classes\Queue\Worker;
use Vanderbilt\REDCap\UnitTests\Utility\UserManager;

class EmailSchedulerTest extends TestCase
{
	private $testSenderUsername = 'email-scheduler-test-user';
	private $testSenderProperties = [
		'first_name' => 'John',
		'last_name' => 'Doe',
		'email' => 'john.doe@example.com',
	];

	private $testRecipientUsername = 'email-recipient-test-user';
	private $testRecipientProperties = [
		'first_name' => 'Jane',
		'last_name' => 'Doe',
		'email' => 'jane.doe@example.com',
	];


	public function setUp(): void {}

	public function tearDown():void {}

	private function getEmailScheduler() {
		$userInfo = User::getUserInfo($this->testSenderUsername);
		$username = $userInfo['username'] ?? null;
		$user_email = $userInfo['user_email'] ?? null;
		if(!$username || !$user_email) throw new Exception("Cannot find the user $this->testSenderUsername", 400);
		
		$emailScheduler = new EmailScheduler($username, $user_email);
		return $emailScheduler;
	}

	public function testCreateUsers() {
		$userInfo1 = UserManager::createUser($this->testSenderUsername, $this->testSenderProperties);
		$this->assertEquals($this->testSenderUsername, $userInfo1['username']);

		$userInfo2 = UserManager::createUser($this->testRecipientUsername, $this->testRecipientProperties);
		$this->assertEquals($this->testRecipientUsername, $userInfo2['username']);
	}

	public function testParseDynamicVariables() {
		$emailSubject = 'this is a test';
		$emailContents = <<<EOT
		<p>you have been selected by [redcap_institution] for a reward. it will be sent to [first_name] [last_name] [email]</p>
		<p>last login: [last_login]
		redcap_institution: [redcap_institution]
		redcap_url: [redcap_url]
		first_name: [first_name]
		last_name: [last_name]
		username: [username]
		email: [email]
		last_login: [last_login]
		</p>
		EOT;

		$emailScheduler = $this->getEmailScheduler();
		$message = $emailScheduler->initMessage($emailSubject, $emailContents);
		$email = $this->testRecipientProperties['email'];
		$message->setTo($email);
		$parser = new DynamicVariablesParser();
		$parsed = $parser->parse($emailContents, $email);
		$messageBody = $message->getBody();
		$this->assertTrue($parsed !== $messageBody);
	}

	public function testMessageWithNoPlaceholders() {
		$emailSubject = 'this is a test';
		$emailContents = "this is a test";

		$emailScheduler = $this->getEmailScheduler();

		$message = $emailScheduler->initMessage($emailSubject, $emailContents);
		$email = $this->testRecipientProperties['email'];
		$message->setTo($email);
		$parser = new DynamicVariablesParser();
		$parsed = $parser->parse($emailContents, $email);
		$messageBody = $message->getBody();
		$this->assertTrue($parsed === $messageBody);
	}

	public function testMessageWithFirstNamePlaceholder() {
		$emailSubject = 'this is a test';
		$emailContents = "this is a test for [first_name]";

		$emailScheduler = $this->getEmailScheduler();

		$message = $emailScheduler->initMessage($emailSubject, $emailContents);
		$email = $this->testRecipientProperties['email'];
		$message->setTo($email);
		$parser = new DynamicVariablesParser();
		$parsed = $parser->parse($emailContents, $email);
		$firstname = $this->testRecipientProperties['first_name'];
		$this->assertTrue($parsed === "this is a test for $firstname");
	}

	public function testScheduleMessage() {
		$emailScheduler = $this->getEmailScheduler();
		$emailSubject = 'This is a test message';
		$userInfo = User::getUserInfo($this->testRecipientUsername);
		$emailMessage = <<<EOT
		Lorem ipsum dolor sit amet consectetur adipisicing elit.
		Sapiente, id autem impedit qui, natus alias fugit tenetur non odit
		eaque maiores vitae ipsam tempora nobis officia harum, voluptate animi in.
		EOT;
		$uiids = [$userInfo['ui_id']];
		$scheduleKeys = $emailScheduler->schedule($uiids, $emailSubject, $emailMessage);
		$this->assertIsArray($scheduleKeys);
	}

	public function testProcessList() {
		$emailScheduler = $this->getEmailScheduler();
		$emailSubject = 'This is a test message';
		$userInfo = User::getUserInfo($this->testRecipientUsername);
		$emailMessage = <<<EOT
		Lorem ipsum dolor sit amet consectetur adipisicing elit.
		Sapiente, id autem impedit qui, natus alias fugit tenetur non odit
		eaque maiores vitae ipsam tempora nobis officia harum, voluptate animi in.
		EOT;
		$ui_id = $userInfo['ui_id'] ?? null;
		$uiids = [$ui_id];
		$metadata = $emailScheduler->processList($uiids, $emailSubject, $emailMessage);
		$processed_ui_ids = $metadata->getProcessedUiIds();
		$this->assertTrue(in_array($ui_id, $processed_ui_ids));
	}

	public function testProcessScheduledMessages() {
		$queue = new Queue();
		$worker = new Worker($queue);
		$scheduledMessages = $queue->getList(0,0,'send-emails_');
		$status = [];
		/** @var Message $message */
		foreach ($scheduledMessages as $message) {
			if($message->getStatus() !== Message::STATUS_WAITING) continue;
			$status[] = $worker->processMessage($message);
		}
		$totalProcessed = count($status);
		$this->assertGreaterThan(0, $totalProcessed);
	}

	/**
	 * cleanup
	 *
	 * @return void
	 */
	public function testDeleteTestUsers() {
		$deleted = [];
		$deleted[] = UserManager::deleteUser($this->testSenderUsername);
		$deleted[] = UserManager::deleteUser($this->testRecipientUsername);
		$totalDeleted = count($deleted);
		$this->assertEquals(2, $totalDeleted);
	}
	
}

