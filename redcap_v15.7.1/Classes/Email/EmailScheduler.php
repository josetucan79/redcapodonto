<?php
namespace Vanderbilt\REDCap\Classes\Email;

use Vanderbilt\REDCap\Classes\Queue\Queue;
use Vanderbilt\REDCap\Classes\Queue\Message;
use Vanderbilt\REDCap\Classes\SystemMonitors\ResourceMonitor;
use Vanderbilt\REDCap\Classes\Email\DTOs\EmailSchedulerMetadata;

class  EmailScheduler
{
	const MAX_EXECUTION_TIME = '30 minutes';
	const USERS_CHUNK_SIZE = 1000; // number of UI_IDs that are scheduled in a single task
	const TASK_PRIORITY = Message::PRIORITY_HIGH; // use a higher priority (standard is 20) for sending messages

	private $username;
	private $userInfo;
	private $senderEmail;
	private $options; // additional email options

	public function __construct($username, $senderEmail, $options=[])
	{
		$this->username = $username;
		$this->userInfo = \User::getUserInfo($username);
		$this->setSenderEmail($senderEmail);
		$this->options = $options;
	}

	/**
	 * get a list of the emails associated to the current user
	 *
	 * @return array
	 */
	private function getUserEmails() {
		$emails = [];
		if($email1 = @$this->userInfo['user_email']) $emails[] = $email1;
		if($email2 = @$this->userInfo['user_email2']) $emails[] = $email2;
		if($email3 = @$this->userInfo['user_email3']) $emails[] = $email3;
		return $emails;
	}

	/**
	 * a user could have multiple emails associated: user_email, user_email2, etc...
	 * set the preferred email using a number
	 *
	 * @param string $email
	 * @return void
	 */
	private function setSenderEmail($email)
	{
		$emails = $this->getUserEmails();
		if (!isEmail($email)) throw new \Exception("Error: the format of the email address '$email' is not valid.", 401);
		if(!in_array($email, $emails)) throw new \Exception("Error: the email address '$email' is not associated to the current user.", 401);
		$this->senderEmail = $email;
	}


	public function schedule($ui_ids, $emailSubject, $emailBody): array
	{
		$queue = new Queue();
		$username = $this->username;
		$senderEmail = $this->senderEmail;
		$options = $this->options;
		$schedulerClass = EmailScheduler::class; // full name for EmailScheduler
		// Create a closure that returns an email processing task
		$createEmailTask = function(array $ids) use ($schedulerClass, $username, $senderEmail, $emailSubject, $emailBody, $options) {
			return function() use ($ids, $schedulerClass, $username, $senderEmail, $emailSubject, $emailBody, $options) {
				$emailHelper = new $schedulerClass($username, $senderEmail, $options);
				$emailHelper->processList($ids, $emailSubject, $emailBody);
			};
		};

		$now = date('Y-m-d H:i:s');
		$chunks = array_chunk($ui_ids, self::USERS_CHUNK_SIZE);
		$queueKeys = [];
		$uniqueID = uniqid("$now.", $more_entropy = true);
		$messageOptions = ['priority' => self::TASK_PRIORITY];
		
		foreach ($chunks as $index => $ui_ids_chunk) {
			$messageKey = "$username-send-emails-$uniqueID-$index";
			$queue->addMessage($createEmailTask($ui_ids_chunk), $messageKey, 'Send email messages', $messageOptions);
			$queueKeys[] = $messageKey;
		}

		return $queueKeys;
	}

	/**
	 * process a list of emails.
	 *
	 * @param array $ui_ids
	 * @param string $emailSubject
	 * @param string $emailBody
	 * @return EmailSchedulerMetadata
	 */
	public function processList(array $ui_ids, string $emailSubject, string $emailBody): EmailSchedulerMetadata {
		$parser = new DynamicVariablesParser();
		$message = $this->initMessage($emailSubject, $emailBody);

		$resourceMonitor = ResourceMonitor::create([
			'memory' => 0.75,
			'time' => EmailScheduler::MAX_EXECUTION_TIME,
		]);

		$sentCounter = 0;
		$notSentCounter = 0;
		$processedUiIds = [];
		$processedEmails = [];

		$emails = $this->getEmails($ui_ids);
		foreach ($emails as $ui_id => $email) {
			$resourcesOk = $resourceMonitor->checkResources();
			if(!$resourcesOk) {
				break;
			}
			// $userInfo = User::getUserInfoByUiid($ui_id);
			// $useremail = $userInfo['user_email'] ?? null;

			// Mark this ui_id as processed
			$processedUiIds[] = $ui_id;
			$processedEmails[] = $email;

			if(!$email) {
				$notSentCounter++;
				continue;
			}
			$clonedMessage = clone $message; // make a copy so it not modified in the loop
			$sent = $this->sendMessage($parser, $clonedMessage, $email);
			if($sent==1) $sentCounter++;
			else $notSentCounter++;
		}

		$this->log($this->senderEmail, $emailSubject, $emailBody, $processedEmails);

		// the list of UI_IDS matching the emails could be different from the list of provided ui_ids
		$emailsUI_IDS = array_keys($emails);
		// Determine which ui_ids were not processed.
		$remainingUiIds = array_diff($emailsUI_IDS, $processedUiIds);
		if (!empty($remainingUiIds)) {
			// Schedule the remaining emails.
			$this->schedule($remainingUiIds, $emailSubject, $emailBody);
		}
		
		$metadata = new EmailSchedulerMetadata();
		$metadata->setSent($sentCounter);
		$metadata->setNotSent($notSentCounter);
		$metadata->setProcessedUiIds($processedUiIds);
		$metadata->setRemainingUiIds($remainingUiIds);

		return $metadata;
	}

	/**
	 * get a list of unique email addresses
	 * using a list of UI_IDs
	 *
	 * @param array $uiids
	 * @return array
	 */
	public function getEmails($uiids) {
		// Get unique list of uiid's
		$placeholders = dbQueryGeneratePlaceholdersForArray($uiids);
		$sql = "SELECT MIN(ui_id) AS ui_id, user_email
				FROM redcap_user_information WHERE user_email != ''
				AND user_email IS NOT NULL AND ui_id IN ($placeholders) GROUP BY user_email";
		$q = db_query($sql, $uiids);
		$useremail_list = [];
		while ($row = db_fetch_assoc($q))
		{
			if (isEmail($row['user_email'])) {
				$ui_id = $row['ui_id'] ?? null;
				$useremail_list[$ui_id] = $row['user_email'];
			}
		}
		return $useremail_list;
	}

	/**
	 * log the scheduling request
	 *
	 * @param string $fromEmail
	 * @param string $emailSubject
	 * @param string $emailBody
	 * @param array $useremail_list
	 * @return void
	 */
	function log($fromEmail, $emailSubject, $emailBody, $useremail_list) {
		// Get basic values sent
		$emailContents = '<html><body style="font-family:arial,helvetica;">'.decode_filter_tags($emailBody).'</body></html>';
		$emailSubject  = decode_filter_tags($emailSubject);

			// Logging
		$log_vals = "From: $fromEmail\n"
					. "To: " . implode(", ",$useremail_list) . "\n"
					. "Subject: $emailSubject\n"
					. "Message:\n$emailContents";
		\Logging::logEvent("","","MANAGE","",$log_vals,"Email users");
	}


	/**
	 * init a message but do not send it
	 *
	 * @param string $emailSubject
	 * @param string $emailBody
	 * @return \Message
	 */
	public function initMessage($emailSubject, $emailBody) {
		$senderEmail = $this->senderEmail;
		$emailSubject = decode_filter_tags($emailSubject);
		$emailBody = decode_filter_tags($emailBody);
		// Set up email to be sent
		$message = new \Message();
		$message->setFrom($senderEmail);
		$primaryEmail = $this->userInfo['user_email'] ?? '';
		if ($senderEmail==$primaryEmail) {
			$message->setFromName(""); // Add user's secondary and tertiary display name
		} else {
			$firstName = $this->userInfo['user_firstname'] ?? '';
			$lastName = $this->userInfo['user_lastname'] ?? '';
			$message->setFromName("$firstName $lastName");
		}
		$message->setSubject($emailSubject);
		$message->setBody($emailBody);
		return $message;
	}


	/**
	 * send a message to a list of users
	 *
	 * @param DynamicVariablesParser $parser
	 * @param \Message $message
	 * @param array $user_list
	 * @return int -1 email not valid, 0 not sent, 1 sent
	 */
	public function sendMessage($parser, $message, $email)
	{
		if (!$email) return;
		$message->setTo($email);
		
		// apply dynamic variables to body
		$body = $message->getBody();
		$parsedBody = $parser->parse($body, $email);
		$message->setBody($parsedBody);
		
		// apply dynamic variables to subject
		$subject = $message->getSubject();
		$parsedSubject = $parser->parse($subject, $email);
		$message->setSubject($parsedSubject);

		$this->applyOptions($message);
		$success = $message->send();
		return intval($success);
	}

	private function applyOptions(\Message $message) {
		$fromName = $this->options['fromName'] ?? null;
		$cc = $this->options['cc'] ?? null;
		$bcc = $this->options['bcc'] ?? null;
		if($fromName) $message->setFromName($fromName);
		if($cc) $message->setCc($cc);
		if($bcc) $message->setBcc($bcc);
	}
}