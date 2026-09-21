<?php

use MultiLanguageManagement\MultiLanguage;
use REDCap\Context;
use PHPMailer\PHPMailer\PHPMailer;

class Message
{
	// Current project_id for this object (and other contextual info)
	private $project_id = null;
	private $record = null;
	private $event_id = null;
	private $form = null;
	private $instance = null;

    // @var to string
    // @access private
    private $to = '';

	// @var toName string
    // @access private
    private $toName = '';

    // @var from string
    // @access private
    private $from = '';

	// @var fromName string
    // @access private
    private $fromName = '';

    // @var from string
    // @access private
    private $cc = '';

    // @var from string
    // @access private
    private $bcc = '';

    // @var subject string
    // @access private
    private $subject = '';

    // @var body string
    // @access private
    private $body = '';

    // @var attachments array
    // @access public
    public $attachments = array();

	// @var attachmentsNames array
	// @access public
	public $attachmentsNames = array();

	// @var ErrorInfo string
	// @access public
	public $ErrorInfo = false;

	// @var forceSendAsSMTP boolean
	// @access public
	public static $forceSendAsSMTP = false;

	// @var emailApiConnectionError boolean
	// @access public
	public $emailApiConnectionError = false;

	// @var cids array
	// @access private
	private $cids = array();

	// @var $protectedEmailHash string
	// @access public
	public $protectedEmailHash = null;

	// @var $protectedEmailCookie string
	// @access private
	private static $protectedEmailCookie = 'redcap_protected_email';

	// @var $xMonthsToRetainProtectedModeAttachments string
	// @access private
	private static $xMonthsToRetainProtectedModeAttachments = 3;

	// @var $platform string
	// @access private
	private $platform = 'smtp';

	// @var $rtl boolean Right-to-left status
	private $rtl = false;

    /*
    * METHODS
    */
	public function __construct($this_project_id=null, $this_record=null, $this_event_id=null, $this_form=null, $this_instance=null, $this_rtl = false)
	{
		// Set project_id and record for this object
		$this->project_id = $this_project_id;
		$this->record = $this_record;
		$this->event_id = $this_event_id;
		$this->form = $this_form;
		$this->instance = $this_instance;
		$this->rtl = $this_rtl;
	}

    function getTo()            { return $this->to; }

	function getCc()            { return $this->cc; }

	function getBcc()           { return $this->bcc; }

    function getFrom() 			{ return $this->from; }

	function getFromName()      { return $this->fromName; }

    function getSubject()       {
        global $lang;
        return ($this->subject == '' ? $lang['survey_397'] : $this->subject);
    }

    function getBody()          { return $this->body; }

	function getAllRecipientAddresses()
	{
		$email_to = str_replace(array(" ",","), array("",";"), $this->getTo());
		$email_cc = str_replace(array(" ",","), array("",";"), $this->getCc() ?? "");
		$email_bcc = str_replace(array(" ",","), array("",";"), $this->getBcc() ?? "");
		$email_recipient_string = $email_to.";".$email_cc.";".$email_bcc;
		$email_recipient_array = array();
		foreach (explode(";", $email_recipient_string) as $this_email) {
			if ($this_email == "") continue;
			$email_recipient_array[] = $this_email;
		}
		return $email_recipient_array;
	}

    function setTo($val)        { $this->to = $val; }

    function setCc($val)       	{ $this->cc = $val; }

    function setBcc($val)       { $this->bcc = $val; }

    function setFrom($val)      { $this->from = $val; }

	function setFromName($val) 	{ $this->fromName = $val; }

    function setSubject($val)   { $this->subject = $val; }

	/**
	 * Attaches a file
	 * @param string $file_full_path The full file path of a file (including its file name)
	 */
    function setAttachment($file_full_path, $filename="")
	{
		if (!empty($file_full_path)) {
			if ($filename == "") {
				$filename = basename($file_full_path);
			}
			$this->attachments[] = $file_full_path;
			$this->attachmentsNames[] = $filename;
		}
	}

    function getAttachments()
	{
    	return $this->attachments;
    }

    function getAttachmentsWithNames()
	{
		$attachmentsNames = array();
		$attachments = $this->getAttachments();
		if (!empty($attachments)) {
			foreach ($attachments as $attachment_key=>$this_attachment_path) {
				$attachmentName = $this->attachmentsNames[$attachment_key];
				// If another attachment has the same name, then rename it on the fly to prevent conflict
				if (isset($attachmentsNames[$attachmentName])) {
					// Prepend file name with timestamp and random alphanum to ensure uniqueness
					$attachmentName = date("YmdHis")."_".substr(md5(rand()), 0, 4)."_".$attachmentName;
				}
				$attachmentsNames[$attachmentName] = $this_attachment_path;
			}
		}
		return $attachmentsNames;
	}

	/**
	 * Sets the content of this HTML email.
	 * @param string $val the HTML that makes up the email.
	 * @param boolean $onlyBody true if the $html parameter only contains the message body. If so,
	 * then html/body tags will be automatically added, and the message will be prepended with the
	 * standard REDCap notice.
	 */
    function setBody($val, $onlyBody=false) {
		global $lang;
		if ($this->rtl) {
			if (strpos($val, "<html") !== false) {
				$val = str_replace("<html", "<html dir=\"rtl\"", $val);
			}
			else {
				$val = "<div dir=\"rtl\">$val</div>";
			}
		}
		// If want to use the "automatically sent from REDCap" message embedded in HTML
		if ($onlyBody) {
			$val =
				"<html>\r\n" .
				"<body style=\"font-family:arial,helvetica;\">\r\n" .
				$lang['global_21'] . "<br /><br />\r\n" .
				$val .
				"</body>\r\n" .
				"</html>";
		}
		// For compatibility purposes, make sure all line breaks are \r\n (not just \n) 
		// and that there are no bare line feeds (i.e., for a space onto a blank line)
		$val = trim(str_replace(array("\r\n", "\r", "\n", "\r\n\r\n"), array("\n", "\n", "\r\n", "\r\n \r\n"), $val));
		// Set body for email message
		$this->body = $val;
	}

	// Format email body for text/plain: Replace HTML link with "LINKTEXT (URL)" and fix tabs and line breaks
	public function formatPlainTextBody($body)
	{
		$plainText = $body;
		if (preg_match_all("/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU", $plainText, $matches)) {
			foreach ($matches[0] as $key=>$this_match) {
				$plainText = str_replace($this_match, $matches[3][$key]." (".$matches[2][$key].")", $plainText);
			}
		}
		$plainText = preg_replace("/\n\t+/", "\n", $plainText);
		$plainText = trim(preg_replace("/\t+/", " ", $plainText));
		$plainText = str_replace("</p><p>", "</p>\n\n<p>", $plainText);
		$plainText = trim(str_replace(array("\r\n", "\r"), array("\n", "\n"), $plainText));
		$plainText = trim(strip_tags(br2nl($plainText)));
		return $plainText;
	}

	// Ensure that the same email address is not duplicated in To, CC, and/or BCC (can throw an error for some services)
    public function checkDuplicateRecipients()
	{
        $all = [];
		foreach (preg_split("/[;,]+/", $this->getTo()) as $thisTo) {
			$thisTo = strtolower(trim($thisTo));
			if ($thisTo != '' && !in_array($thisTo, $all)) {
				$all[] = $thisTo;
			}
		}
		$this->setTo(implode(";", $all));
		if ($this->getCc() != "") {
            $cc = [];
			foreach (preg_split("/[;,]+/", $this->getCc()) as $thisCc) {
				$thisCc = strtolower(trim($thisCc));
                if ($thisCc != '' && !in_array($thisCc, $all)) {
	                $all[] = $cc[] = $thisCc;
                }
			}
			$this->setCc(implode(";", $cc));
		}
		if ($this->getBcc() != "") {
            $bcc = [];
			foreach (preg_split("/[;,]+/", $this->getBcc()) as $thisBcc) {
				$thisBcc = strtolower(trim($thisBcc));
				if ($thisBcc != '' && !in_array($thisBcc, $all)) {
					$all[] = $bcc[] = $thisBcc;
				}
			}
			$this->setBcc(implode(";", $bcc));
		}
	}

    // Send the email
    public function send($removeDisplayName=false, $recipientIsSurveyParticipant=null, $enforceProtectedEmail=false, $emailCategory=null, $lang_id=null)
	{
		// Have email Display Names been disabled at the system level?
		global $use_email_display_name;
		if (isset($use_email_display_name) && $use_email_display_name == '0') {
			$removeDisplayName = true;
		}

		// Reset flags
		$this->emailApiConnectionError = false;
		$this->cids = array();

        // Ensure that the same email address is not duplicated in To, CC, and/or BCC (can throw an error for some services)
        $this->checkDuplicateRecipients();

		// Call the email hook
		$sendEmail = Hooks::call('redcap_email', array($this->getTo(), $this->getFrom(), $this->getSubject(), $this->getBody(), $this->getCc(),
								$this->getBcc(), $this->getFromName(), $this->getAttachmentsWithNames()));

		if (!$sendEmail) {
			// If the hook returned FALSE, then exit here without sending the email through normal methods below
			return true; // Return TRUE to note that the email was sent successfully because FALSE would imply some sort of error
		}

		// Get the Universal FROM Email address (if being used)
		$from_email = System::getUniversalFromAddess();

		// Suppress Universal FROM Address? (based on the sender's address domain)
		if (System::suppressUniversalFromAddress($this->getFrom())) {
			$from_email = ''; // Set Universal FROM address as blank so that it is ignored for this outgoing email
		}

		// Using the Universal FROM email?
		$usingUniversalFrom = ($from_email != '');

		// Set the From email for this message
		$this_from_email = (!$usingUniversalFrom ? $this->getFrom() : $from_email);

		// If the FROM email address is not valid, then return false
		if (!isEmail($this_from_email)) return false;

		if ($this->getFromName() == '') {
			// If no Display Name, then use the Sender address as the Display Name if using Universal FROM address
			$fromDisplayName = $usingUniversalFrom ? $this->getFrom() : "";
			$replyToDisplayName = '';
		} else {
			// If has a Display Name, then use the Sender address+real Display Name if using Universal FROM address
			$fromDisplayName = $usingUniversalFrom ? $this->getFromName()." (".$this->getFrom().")" : $this->getFromName();
			$replyToDisplayName = $this->getFromName();
		}
		// Remove the display name(s), if applicable
		if ($removeDisplayName) {
			$fromDisplayName = $replyToDisplayName = '';
		}

		// Replace any <img> with src="..." with "cid:" reference to attachment
        $this->replaceImagesWithCidsInBody();

		// Replace any file download relative links with valid full links
		$this->replaceFileDownloadLinks($recipientIsSurveyParticipant);

        // Set email platform type (smtp, sendgrid, etc.)
        if (!empty($GLOBALS["azure_comm_api_key"]) && !empty($GLOBALS["azure_comm_api_endpoint"]) && !self::$forceSendAsSMTP) {
            $this->platform = 'azure_comm_api_key';
        } elseif (!empty($GLOBALS["sendgrid_api_key"]) && !self::$forceSendAsSMTP) {
            $this->platform = 'sendgrid';
        } elseif (!empty($GLOBALS["mandrill_api_key"]) && !self::$forceSendAsSMTP) {
			$this->platform = 'mandrill';
        } elseif (isset($_SERVER['APPLICATION_ID'])) {
			$this->platform = 'google_app_engine';
        } elseif (!empty($GLOBALS["mailgun_api_key"]) && !empty($GLOBALS["mailgun_domain_name"]) && !self::$forceSendAsSMTP) {
			$this->platform = 'mailgun';
        } else {
			$this->platform = 'smtp';
        }

        ## Azure Communication Services ONLY
        if ($this->platform == 'azure_comm_api_key')
        {
            try {
                $messageData['senderAddress'] = $this_from_email;

                $messageData['content'] = [
                    "subject" => $this->getSubject(),
                    "plainText" => $this->formatPlainTextBody($this->getBody()),
                    "html" => $this->getBody()
                ];

                $toAddress = [];
                foreach (preg_split("/[;,]+/", $this->getTo()) as $thisTo) {
                    $thisTo = trim($thisTo);
                    if ($thisTo == '') continue;

                    $to['address'] = $thisTo;
                    $to['displayName'] = $thisTo;
                    $toAddress[] = $to;
                }
                $messageData['recipients']['to'] = $toAddress;

                if ($this->getCc() != "") {
                    $ccAddress = [];
                    foreach (preg_split("/[;,]+/", $this->getCc()) as $thisCc) {
                        $thisCc = trim($thisCc);
                        if ($thisCc == '') continue;

                        $cc['address'] = $thisCc;
                        $cc['displayName'] = $thisCc;
                        $ccAddress[] = $cc;
                    }
                    $messageData['recipients']['cc'] = $ccAddress;
                }

                if ($this->getBcc() != "") {
                    $bccAddress = [];
                    foreach (preg_split("/[;,]+/", $this->getBcc()) as $thisBcc) {
                        $thisBcc = trim($thisBcc);
                        if ($thisBcc == '') continue;

                        $bcc['address'] = $thisBcc;
                        $bcc['displayName'] = $thisBcc;
                        $bccAddress[] = $bcc;
                    }
                    $messageData['recipients']['bcc'] = $bccAddress;
                }

                // add reply to
                $messageData['replyTo'] = array(array('address' => $this->getFrom(),
                                                      'displayName' => ($replyToDisplayName ? $replyToDisplayName.' <'.$this->getFrom().'>'  : $this->getFrom())));
                $messageData['userEngagementTrackingDisabled'] = true;

                // Attachments, if any
                $attachments = $this->getAttachmentsWithNames();
                if (!empty($attachments) && !$enforceProtectedEmail) {
                    $messageData["attachments"] = [];
                    foreach ($attachments as $attachmentName=>$this_attachment_path) {
                        $mime_type = \ExternalModules\ExternalModules::getContentType(str_replace(".", "", SendIt::getFileExtension(basename($this_attachment_path))));
                        if (empty($mime_type)) $mime_type = "application/octet-stream";
                        $attachment['contentInBase64'] = base64_encode(file_get_contents($this_attachment_path));
                        $attachment['contentType'] = $mime_type;
                        $attachment['name'] = $attachmentName;
                        $messageData["attachments"][] = $attachment;
                    }
                }

                $email_id = $this->logEmailContent($this->getFrom(), implode("; ", preg_split("/[;,]+/", $this->getTo() ?? "")), $this->formatPlainTextBody($this->getBody()), 'EMAIL', $emailCategory, $this->project_id, $this->record,
                    implode("; ", preg_split("/[;,]+/", $this->getCc() ?? "")), implode("; ", preg_split("/[;,]+/", $this->getBcc() ?? "")), $this->getSubject() ?? "", $this->getBody() ?? "", $this->getAttachmentsWithNames(), $enforceProtectedEmail, $this->event_id, $this->form, $this->instance, $lang_id);
                if ($email_id === false && $enforceProtectedEmail) {
                    $this->ErrorInfo = "Both a sender and recipient are required.";
                    return false;
                }

                // If we're using Protected Email mode, then replace the email body
                if ($enforceProtectedEmail) {
                    $cidToAdd = $this->setProtectedBody($lang_id);
                    // Add the logo CID manually to attachments (because the img-to-CID code was already run on the real email text beforehand)
                    if (!empty($cidToAdd)) {
                        // Add the logo CID manually to attachments (because the img-to-CID code was already run on the real email text beforehand)
                        if (!empty($cidToAdd)) {
                            $mime_type = \ExternalModules\ExternalModules::getContentType(str_replace(".", "", SendIt::getFileExtension(basename($cidToAdd['filename']))));
                            if (empty($mime_type)) $mime_type = "application/octet-stream";
                            $attachment['contentInBase64'] = base64_encode(file_get_contents($cidToAdd['filename']));
                            $attachment['contentType'] = $mime_type;
                            $attachment['name'] = $cidToAdd['filename'];
                            $messageData["attachments"][] = $attachment;
                        }
                    }
                    // Modify existing email body with new text
                    $messageData['content']['plainText'] = $this->formatPlainTextBody($this->getBody());
                    $messageData['content']['html'] = $this->getBody();
                }

                # Make the call to the client
                $output = self::sendAzureCommServiceRequest($messageData);
                $response = json_decode($output);
                if ($response->id != "" && $response->status != "" && $response->error == null) {
                    $this->logSuccessfulSend('azure');
                    return true;
                } else {
                    error_log("Email: Failed send", true);
                    $this->ErrorInfo = "Email: Failed send";
                    $this->removeEmailLogOnFail($email_id);
                    return false;
                }
            } catch (Exception $e) {
                error_log("Email: Failed send ".$e->getMessage());
                $this->ErrorInfo = $e->getMessage();
                $this->removeEmailLogOnFail($email_id);
                return false;
            }

            // If sent, return true
            return true;
        }

		## SENDGRID ONLY
		if ($this->platform == 'sendgrid')
		{
			try {
                // If running PHP 8.1 with error reporting=E_ALL, temporarily disable warnings from SendGrid (not fully PHP 8.1 compatible)
                if (error_reporting() === E_ALL && version_compare(System::getPhpVersion(), '8.1.0', '>=')) {
                    $origErrorReporting = error_reporting();
					error_reporting(0);
                }
                // Prep email
				$email = new \SendGrid\Mail\Mail();
				$email->setFrom($this_from_email, $fromDisplayName);
				$email->setReplyTo($this->getFrom(), $replyToDisplayName);
				$email->setSubject($this->getSubject());
				foreach (preg_split("/[;,]+/", $this->getTo()) as $thisTo) {
					$thisTo = trim($thisTo);
					if ($thisTo == '') continue;
					$email->addTo($thisTo);
				}
				if ($this->getCc() != "") {
					foreach (preg_split("/[;,]+/", $this->getCc()) as $thisCc) {
						$thisCc = trim($thisCc);
						if ($thisCc == '') continue;
						$email->addCc($thisCc);
					}
				}
				if ($this->getBcc() != "") {
					foreach (preg_split("/[;,]+/", $this->getBcc()) as $thisBcc) {
						$thisBcc = trim($thisBcc);
						if ($thisBcc == '') continue;
						$email->addBcc($thisBcc);
					}
				}
				// Attachments, if any
				$attachments = $this->getAttachmentsWithNames();
				if (!empty($attachments) && !$enforceProtectedEmail) {
					foreach ($attachments as $attachmentName=>$this_attachment_path) {
						$mime_type = \ExternalModules\ExternalModules::getContentType(str_replace(".", "", SendIt::getFileExtension(basename($this_attachment_path))));
						if (empty($mime_type)) $mime_type = "application/octet-stream";
						$cid = isset($this->cids[$this_attachment_path]) ? $this->cids[$this_attachment_path] : null;
						$disposition = isset($this->cids[$this_attachment_path]) ? 'inline' : null;
						$email->addAttachment(base64_encode(file_get_contents($this_attachment_path)), $mime_type, $attachmentName, $disposition, $cid);
					}
				}

				$email_id = $this->logEmailContent($this->getFrom(), implode("; ", preg_split("/[;,]+/", $this->getTo() ?? "")), $this->formatPlainTextBody($this->getBody()), 'EMAIL', $emailCategory, $this->project_id, $this->record,
					implode("; ", preg_split("/[;,]+/", $this->getCc() ?? "")), implode("; ", preg_split("/[;,]+/", $this->getBcc() ?? "")), $this->getSubject() ?? "", $this->getBody() ?? "", $this->getAttachmentsWithNames(), $enforceProtectedEmail, $this->event_id, $this->form, $this->instance, $lang_id);
                if ($email_id === false && $enforceProtectedEmail) {
                    $this->ErrorInfo = "Both a sender and recipient are required.";
                    return false;
                }

				// If we're using Protected Email mode, then replace the email body
				if ($enforceProtectedEmail) {
                    $cidToAdd = $this->setProtectedBody($lang_id);
					// Add the logo CID manually to attachments (because the img-to-CID code was already run on the real email text beforehand)
					if (!empty($cidToAdd)) {
						$email->addAttachment(base64_encode(file_get_contents($cidToAdd['filename'])), $cidToAdd['mime_type'], basename($cidToAdd['filename']), 'inline', $cidToAdd['cid']);
					}
				}

				$email->addContent("text/plain", $this->formatPlainTextBody($this->getBody()));
				$email->addContent("text/html", $this->getBody());
				$sendgrid = new \SendGrid($GLOBALS["sendgrid_api_key"]);
				$response = $sendgrid->send($email);
				$json_response = json_decode($response->body(), true);
				if ($response->statusCode() >= 429) $this->emailApiConnectionError = true;
				if (is_array($json_response) && isset($json_response['errors'])) {
					if (isDev()) {
						print_array($response);
					}
					error_log("Email: Failed send ".print_r($json_response['errors'], true));
					$this->ErrorInfo = $json_response['errors'][0]['message'];
                    $this->removeEmailLogOnFail($email_id);
					return false;
				}
				$this->logSuccessfulSend('sendgrid');
                // Reset error_reporting to original value, if altered
                if (isset($origErrorReporting)) error_reporting($origErrorReporting);
                // Success
				return true;
			} catch (Exception $e) {
				// echo 'Caught exception: ' . $e->getMessage() . "\n";
				error_log("Email: Failed send ".$e->getMessage());
				$this->ErrorInfo = $e->getMessage();
                $this->removeEmailLogOnFail($email_id);
				return false;
			}
		}

		## MANDRILL API ONLY (does not appear to support attachments)
		if ($this->platform == 'mandrill')
		{
			$messageData = [
					"to" => [],
					"from_email" => $this_from_email,
					"from_name" => $fromDisplayName,
					"headers" => ["Reply-To" => $this->getFrom()],
					"subject" => $this->getSubject(),
					"text" => $this->formatPlainTextBody($this->getBody()),
					"html" => $this->getBody()
			];
			foreach (preg_split("/[;,]+/", $this->getTo()) as $thisTo) {
				$thisTo = trim($thisTo);
				if ($thisTo == '') continue;
				$messageData["to"][] = ["email" => $thisTo,"type" => "to"];
			}
			if ($this->getCc() != "") {
				foreach (preg_split("/[;,]+/", $this->getCc()) as $thisCc) {
					$thisCc = trim($thisCc);
					if ($thisCc == '') continue;
					$messageData["to"][] = ["email" => $thisCc,"type" => "cc"];
				}
			}
			if ($this->getBcc() != "") {
				foreach (preg_split("/[;,]+/", $this->getBcc()) as $thisBcc) {
					$thisBcc = trim($thisBcc);
					if ($thisBcc == '') continue;
					$messageData["to"][] = ["email" => $thisBcc,"type" => "bcc"];
				}
			}
			// Attachments, if any
			$attachments = $this->getAttachmentsWithNames();
			if (!empty($attachments) && !$enforceProtectedEmail) {
				$messageData["attachments"] = [];
				foreach ($attachments as $attachmentName=>$this_attachment_path) {
					$mime_type = \ExternalModules\ExternalModules::getContentType(str_replace(".", "", SendIt::getFileExtension(basename($this_attachment_path))));
					if (empty($mime_type)) $mime_type = "application/octet-stream";
					// How to add CID attachments? Does not seem to be supported by Mandrill.
					$messageData["attachments"][] = ["type"=>$mime_type, "name"=>$attachmentName, "content"=>file_get_contents($this_attachment_path)];
				}
			}

			$email_id = $this->logEmailContent($this->getFrom(), implode("; ", preg_split("/[;,]+/", $this->getTo() ?? "")), $this->formatPlainTextBody($this->getBody()), 'EMAIL', $emailCategory, $this->project_id, $this->record,
				implode("; ", preg_split("/[;,]+/", $this->getCc() ?? "")), implode("; ", preg_split("/[;,]+/", $this->getBcc() ?? "")), $this->getSubject() ?? "", $this->getBody() ?? "", $this->getAttachmentsWithNames(), $enforceProtectedEmail, $this->event_id, $this->form, $this->instance, $lang_id);
            if ($email_id === false && $enforceProtectedEmail) {
                $this->ErrorInfo = "Both a sender and recipient are required.";
                return false;
            }

			// If we're using Protected Email mode, then replace the email body
			if ($enforceProtectedEmail) {
				$cidToAdd = $this->setProtectedBody($lang_id);
				// Add the logo CID manually to attachments (because the img-to-CID code was already run on the real email text beforehand)
				if (!empty($cidToAdd)) {
					// Doesn't support CIDs?
				}
			    // Modify existing email body with new text
				$messageData["text"] = $this->formatPlainTextBody($this->getBody());
				$messageData["html"] = $this->getBody();
			}

			$data = [
				"message" => $messageData
			];

			$output = self::sendMandrillRequest($data,"messages/send.json");
			if (empty($output)) {
				error_log("Email: Failed send - Unknown reason (Mandrill not available?)");
			}
			$decodedOutput = json_decode($output, true);
			## Check for error message and log if needed
			if ($decodedOutput["status"] == "error") {
				if ($decodedOutput["name"] != "GeneralError") $this->emailApiConnectionError = true;
				error_log("Email: Failed send ".$decodedOutput["message"]);
				$this->ErrorInfo = $decodedOutput["message"];
                $this->removeEmailLogOnFail($email_id);
				return false;
			}
			if ($decodedOutput[0]["status"] == "rejected") {
				if ($decodedOutput[0]["name"] != "GeneralError") $this->emailApiConnectionError = true;
				error_log("Email: Failed send from ".$this_from_email." rejected because ".$decodedOutput[0]["reject_reason"]);
				$this->ErrorInfo = $output;
                $this->removeEmailLogOnFail($email_id);
				return false;
			}
			// mandrill won't send email if we've reached our limit
			if ($decodedOutput[0]["status"] == "queued") {
				$this->emailApiConnectionError = false;
				if (isset($decodedOutput[0]["queued_reason"])) {
					$errorReason = $decodedOutput[0]["queued_reason"];
				} else {
					$errorReason = "Unknown Reason";
				}
				error_log("Email: Failed send from ".$this_from_email." rejected because ".$errorReason);
				$this->ErrorInfo = $output;
				$this->removeEmailLogOnFail($email_id);
				return false;
			}
			$this->logSuccessfulSend('mandrill');
			return true;
		}

		## GOOGLE APP ENGINE ONLY
		if ($this->platform == 'google_app_engine')
		{
			try
			{
				// Set up email params
				$message = new \google\appengine\api\mail\Message();
				$message->setSender($this_from_email);
				$message->setReplyTo($this->getFrom());
				$message->addTo($this->getTo());
				if ($this->getCc() != "") {
					$message->addCc($this->getCc());
				}
				if ($this->getBcc() != "") {
					$message->addBcc($this->getBcc());
				}
				$message->setSubject($this->getSubject());
				// Attachments, if any
				$attachments = $this->getAttachmentsWithNames();
				if (!empty($attachments) && !$enforceProtectedEmail) {
					foreach ($attachments as $attachmentName=>$this_attachment_path) {
						$cid = isset($this->cids[$this_attachment_path]) ? $this->cids[$this_attachment_path] : sha1(rand());
						$message->addAttachment($attachmentName, file_get_contents($this_attachment_path), "<".$cid.">");
					}
				}

				$email_id = $this->logEmailContent($this->getFrom(), implode("; ", preg_split("/[;,]+/", $this->getTo() ?? "")), $this->formatPlainTextBody($this->getBody()), 'EMAIL', $emailCategory, $this->project_id, $this->record,
					implode("; ", preg_split("/[;,]+/", $this->getCc() ?? "")), implode("; ", preg_split("/[;,]+/", $this->getBcc() ?? "")), $this->getSubject() ?? "", $this->getBody() ?? "", $this->getAttachmentsWithNames(), $enforceProtectedEmail, $this->event_id, $this->form, $this->instance, $lang_id);
                if ($email_id === false && $enforceProtectedEmail) {
                    $this->ErrorInfo = "Both a sender and recipient are required.";
                    return false;
                }

				// If we're using Protected Email mode, then replace the email body
				if ($enforceProtectedEmail) {
					$cidToAdd = $this->setProtectedBody($lang_id);
					// Add the logo CID manually to attachments (because the img-to-CID code was already run on the real email text beforehand)
					if (!empty($cidToAdd)) {
						$message->addAttachment(basename($cidToAdd['filename']), file_get_contents($cidToAdd['filename']), "<".$cidToAdd['cid'].">");
					}
				}

				$message->setHtmlBody($this->getBody());

				// Send email
				try {
					$message->send();
					$this->logSuccessfulSend();
				} catch (InvalidArgumentException $e) {
                    $this->removeEmailLogOnFail($email_id);
                }
				return true;
			}
			catch (InvalidArgumentException $e)
			{
				print "<br><b>ERROR: ".$e->getMessage()."</b>";
                $this->removeEmailLogOnFail($email_id);
				return false;
			}
		}

        ## MAILGUN ONLY
        if ($this->platform == 'mailgun')
        {
            try {
                $messageData = [
                    "subject" => $this->getSubject(),
                    "text" => $this->formatPlainTextBody($this->getBody()),
                    "html" => $this->getBody()
                ];
                $toAddress = [];
                foreach (preg_split("/[;,]+/", $this->getTo()) as $thisTo) {
                    $thisTo = trim($thisTo);
                    if ($thisTo == '') continue;
                    $toAddress[] = $thisTo;
                }
                $messageData['to'] = $toAddress;

                if ($this->getCc() != "") {
                    $ccAddress = [];
                    foreach (preg_split("/[;,]+/", $this->getCc()) as $thisCc) {
                        $thisCc = trim($thisCc);
                        if ($thisCc == '') continue;
                        $ccAddress[] = $thisCc;
                    }
                    $messageData['cc'] = $ccAddress;
                }

                if ($this->getBcc() != "") {
                    $bccAddress = [];
                    foreach (preg_split("/[;,]+/", $this->getBcc()) as $thisBcc) {
                        $thisCc = trim($thisBcc);
                        if ($thisBcc == '') continue;
                        $bccAddress[] = $thisBcc;
                    }
                    $messageData['bcc'] = $bccAddress;
                }

				$messageData['from'] = $this->getFromName() ? $this->getFromName()." <{$this_from_email}>" : $this_from_email;


				// add reply to
				$messageData['h:Reply-To'] = ($replyToDisplayName) ? $replyToDisplayName.' <'.$this->getFrom().'>' : $this->getFrom();

                // Attachments, if any
                $attachments = $this->getAttachmentsWithNames();
                if (!empty($attachments) && !$enforceProtectedEmail) {
                    $messageData["attachment"] = [];
                    foreach ($attachments as $attachmentName => $this_attachment_path) {
						$cid = isset($this->cids[$this_attachment_path]) ? $this->cids[$this_attachment_path] : null;
						$disposition = isset($this->cids[$this_attachment_path]) ? 'inline' : 'attachment';
                        $messageData[$disposition][] = ["filename" => $attachmentName, "filePath" => $this_attachment_path];
                    }
                }
                // Set the base URL, if specified
                $mailgun_endpoint = (isset($GLOBALS['mailgun_api_endpoint']) && trim($GLOBALS['mailgun_api_endpoint']) != '') ? $GLOBALS['mailgun_api_endpoint'] : 'https://api.mailgun.net';
                $mailgun_endpoint = rtrim($mailgun_endpoint, '/');
                $mg = \Mailgun\Mailgun::create($GLOBALS["mailgun_api_key"], $mailgun_endpoint);

				$email_id = $this->logEmailContent($this->getFrom(), implode("; ", preg_split("/[;,]+/", $this->getTo() ?? "")), $this->formatPlainTextBody($this->getBody()), 'EMAIL', $emailCategory, $this->project_id, $this->record,
					implode("; ", preg_split("/[;,]+/", $this->getCc() ?? "")), implode("; ", preg_split("/[;,]+/", $this->getBcc() ?? "")), $this->getSubject() ?? "", $this->getBody() ?? "", $this->getAttachmentsWithNames(), $enforceProtectedEmail, $this->event_id, $this->form, $this->instance, $lang_id);
                if ($email_id === false && $enforceProtectedEmail) {
                    $this->ErrorInfo = "Both a sender and recipient are required.";
                    return false;
                }

				// If we're using Protected Email mode, then replace the email body
				if ($enforceProtectedEmail) {
					$cidToAdd = $this->setProtectedBody($lang_id);
					// Add the logo CID manually to attachments (because the img-to-CID code was already run on the real email text beforehand)
					if (!empty($cidToAdd)) {
						// Not sure how to get this to work yet
						$messageData['inline'][] = ["filePath" => $cidToAdd['filename']];
						// $messageData['inline'][] = ["filePath" => basename($filename)];
					}
					// Modify existing email body with new text
					$messageData["text"] = $this->formatPlainTextBody($this->getBody());
					$messageData["html"] = $this->getBody();
				}

                # Make the call to the client.
                $response = $mg->messages()->send($GLOBALS["mailgun_domain_name"], $messageData);
                if ($response->getId() != "" && $response->getMessage() != "") {
                    $this->logSuccessfulSend('mailgun');
                    return true;
                } else {
                    error_log("Email: Failed send", true);
                    $this->ErrorInfo = "Email: Failed send";
                    $this->removeEmailLogOnFail($email_id);
                    return false;
                }
            } catch (Exception $e) {
                error_log("Email: Failed send ".$e->getMessage());
                $this->ErrorInfo = $e->getMessage();
                $this->removeEmailLogOnFail($email_id);
                return false;
            }
        }

		## NORMAL ENVIRONMENT (using PHPMailer)
		// Init
		$mail = new PHPMailer;
		$mail->CharSet = 'UTF-8';
		$mail->Subject = $this->getSubject();
		// HTML body
		$mail->msgHTML($this->getBody());
		// Format email body for text/plain: Replace HTML link with "LINKTEXT (URL)" and fix tabs and line breaks
		$mail->AltBody = $this->formatPlainTextBody($this->getBody());
		// From, Reply-To, and Return-Path. Also, set Display Name if possible.
		// From/Sender and Reply-To
		$mail->setFrom($this_from_email, $fromDisplayName, false);
		$mail->addReplyTo($this->getFrom(), $replyToDisplayName);
		$mail->Sender = $this_from_email; // Return-Path; This also represents the -f header in mail().
		// To, CC, and BCC
		foreach (preg_split("/[;,]+/", $this->getTo()) as $thisTo) {
			$thisTo = trim($thisTo);
			if ($thisTo == '') continue;
			$mail->addAddress($thisTo);
		}
		if ($this->getCc() != "") {
			foreach (preg_split("/[;,]+/", $this->getCc()) as $thisCc) {
				$thisCc = trim($thisCc);
				if ($thisCc == '') continue;
				$mail->addCC($thisCc);
			}
		}
		if ($this->getBcc() != "") {
			foreach (preg_split("/[;,]+/", $this->getBcc()) as $thisBcc) {
				$thisBcc = trim($thisBcc);
				if ($thisBcc == '') continue;
				$mail->addBCC($thisBcc);
			}
		}
		// Attachments
		$attachments = $this->getAttachmentsWithNames();
		if (!empty($attachments) && !$enforceProtectedEmail) {
			foreach ($attachments as $attachmentName=>$this_attachment_path) {
				$cid = isset($this->cids[$this_attachment_path]) ? $this->cids[$this_attachment_path] : null;
                $isImage = in_array(strtolower(getFileExt($this_attachment_path)), Files::supported_image_types);
				if ($cid == null || !$isImage) { // Don't do inline if not an image
					$mail->addAttachment($this_attachment_path, $attachmentName);
				} else {
					$mail->addAttachment($this_attachment_path, $cid, PHPMailer::ENCODING_BASE64, '', 'inline');
				}
			}
		}

		/*
		// Use DKIM?
		$dkim = new DKIM();
		if ($dkim->isEnabled())
		{
			$mail->DKIM_domain = $dkim->DKIM_domain;
			$mail->DKIM_private_string = $dkim->privateKey;
			$mail->DKIM_selector = $dkim->DKIM_selector;
			$mail->DKIM_passphrase = $dkim->DKIM_passphrase;
			$mail->DKIM_copyHeaderFields = false;
			// $mail->DKIM_extraHeaders = ['List-Unsubscribe', 'List-Help'];
			// $mail->DKIM_identity = $mail->From;
		}
		*/

		$email_id = $this->logEmailContent($this->getFrom(), implode("; ", preg_split("/[;,]+/", $this->getTo() ?? "")), $this->formatPlainTextBody($this->getBody()), 'EMAIL', $emailCategory, $this->project_id, $this->record,
			implode("; ", preg_split("/[;,]+/", $this->getCc() ?? "")), implode("; ", preg_split("/[;,]+/", $this->getBcc() ?? "")), $this->getSubject() ?? "", $this->getBody() ?? "", $this->getAttachmentsWithNames(), $enforceProtectedEmail, $this->event_id, $this->form, $this->instance, $lang_id);
        if ($email_id === false && $enforceProtectedEmail) {
            $this->ErrorInfo = "Both a sender and recipient are required.";
            return false;
        }

		// If we're using Protected Email mode, then replace the email body
		if ($enforceProtectedEmail) {
			$cidToAdd = $this->setProtectedBody($lang_id);
			// Add the logo CID manually to attachments (because the img-to-CID code was already run on the real email text beforehand)
			if (!empty($cidToAdd)) {
				$mail->addAttachment($cidToAdd['filename'], $cidToAdd['cid'], PHPMailer::ENCODING_BASE64, '', 'inline');
			}
			// Modify existing email body with new text
			$mail->msgHTML($this->getBody());
			$mail->AltBody = $this->formatPlainTextBody($this->getBody());
		}

		// Send it
		$sentSuccessfully = $mail->send();
		// Add error message, if failed to send
		if (!$sentSuccessfully) {
            // Note the error and remove it from the email log
            $this->ErrorInfo = $mail->ErrorInfo;
            $this->removeEmailLogOnFail($email_id);
		} else {
			$this->logSuccessfulSend();
		}
		// Return boolean for success/fail
		return $sentSuccessfully;
    }


    // Remove email logged event from redcap_outgoing_email_sms_log if email failed to send for whatever reason
    private function removeEmailLogOnFail($email_id)
    {
        if ($email_id == null || !isinteger($email_id)) return;
        $sql = "delete from redcap_outgoing_email_sms_log where email_id = $email_id";
        return db_query($sql);
    }


	/**
	 * Increment the emails sent counter in the database
	 */
	public function logSuccessfulSend($type='smtp')
	{
		$sql = "insert into redcap_outgoing_email_counts (`date`, $type) values ('".TODAY."', 1)
				on duplicate key update send_count = send_count+1, $type = $type+1";
		if (!db_query($sql)) return false;
		// Also delete any file attachments that were generated in the temp directory
		foreach (array_keys($this->cids) as $tempFilePath)
		{
			if (file_exists($tempFilePath)) {
				unlink($tempFilePath);
			}
		}
		// Return true on success
		return true;
	}

	/**
	 * Add email content to email logging table
	 */
	public function logEmailContent($sender, $recipients, $message, $type='EMAIL', $category=null, $project_id=null, $record=null,
									$email_cc=null, $email_bcc=null, $email_subject=null, $message_html=null, $attachments=null,
                                    $enforceProtectedEmail=false, $event_id=null, $form=null, $instance=null, $lang_id=null)
	{
		// Do not log any emails from the Protected Email page (which would be sending one-time codes)
		if (PAGE == 'surveys/index.php' && isset($_GET['__email'])) return false;
        // If the sender or recipient is blank, then return false because it is undeliverable
        if ($recipients == '') return false;
		// Gather values
		$now = date('Y-m-d H:i:s');
		$hash = generateRandomHash(100);
		if ($project_id == null && defined("PROJECT_ID") && isset($_GET['pid']) && $_GET['pid'] == PROJECT_ID) {
			$project_id = PROJECT_ID;
		}
		// Set attachment names
		$attachment_doc_ids_csv = null;
        $attachment_names = '';
        if (is_array($attachments)) {
			$attachment_names = implode("; ", array_keys($attachments));
			// If we're using Protected Email Mode, then the attachments need to be saved permanently for X months
            if ($enforceProtectedEmail)
            {
				$attachment_doc_ids = [];
                foreach ($attachments as $attachmentName=>$attachmentFullPath) {
					// Add attachment to edocs_metadata table
					$permFile = array('name'=>basename($attachmentName), 'type'=>mime_content_type($attachmentFullPath),
						              'size'=>filesize($attachmentFullPath), 'tmp_name'=>$attachmentFullPath);
					$permFile_edoc_id = Files::uploadFile($permFile, $project_id);
					if (isinteger($permFile_edoc_id)) {
						$attachment_doc_ids[] = $permFile_edoc_id;
                    }
                }
                if (!empty($attachment_doc_ids)) {
					$attachment_doc_ids_csv = implode(",", $attachment_doc_ids);
					// Preemptively set the delete time as X months in the future (to allow auto-deletion of these temporary files)
					$xMonthsFromNow = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m")+self::$xMonthsToRetainProtectedModeAttachments,date("d"),date("Y")));
					foreach ($attachment_doc_ids as $doc_id) {
						$sql = "update redcap_edocs_metadata set delete_date = '$xMonthsFromNow' where doc_id = $doc_id";
						$q = db_query($sql);
                    }
                }
            }
        }
        // Set $instance=1 if we have a record and event_id when instance is null
        if ($instance == null && $record != null & $event_id != null) {
            $instance = 1;
		}
		// Add to table
		$sql = "insert into redcap_outgoing_email_sms_log (hash, project_id, record, event_id, instrument, instance, type, category, time_sent, sender, recipients, email_cc, email_bcc, 
				email_subject, message, message_html, attachment_names, attachment_doc_ids, lang_id) VALUES (".checkNull($hash).", ".checkNull($project_id).", 
				".checkNull($record).", ".checkNull($event_id).", ".checkNull($form).", ".checkNull($instance).", ".checkNull($type).", ".checkNull($category).", ".checkNull($now).", ".checkNull($sender).", ".checkNull($recipients).", ".checkNull($email_cc).", 
				".checkNull($email_bcc).", ".checkNull($email_subject).", ".checkNull($message).", ".checkNull(trim($message_html)).", ".checkNull($attachment_names).", ".checkNull($attachment_doc_ids_csv).", ".checkNull($lang_id).")";
		$q = db_query($sql);
		if ($q) {
			// Set value
			$this->protectedEmailHash = $hash;
			// Return autoincrement id
			return db_insert_id();
		} else if(db_errno() === 2006) {
			 /**
			 * A "MySQL server has gone away" error has been detected.
			 * Log an error instead of looping infinitely.
			 * This line is typically only reached inside a shutdown handler because the database connection has been intentionally closed.
			 * An exception is not thrown because it would prevent shutdown handlers from finishing (especially the one in the External Modules Framework).
			 */
			error_log("A DB error occurred in logEmailContent(): " . db_error());
			return false;
		}
		else {
			// Recursive if hash already exists
			return $this->logEmailContent($sender, $recipients, $message, $type, $category, $project_id, $record, $email_cc, $email_bcc, $email_subject,
										  $message_html, $attachments, $enforceProtectedEmail, $event_id, $form, $instance, $lang_id);
		}
	}

	/**
	 * Get all attributes for an email in the email logging table by using the email hash
	 */
	public static function getEmailContentByHash($hash)
	{
		$sql = "select * from redcap_outgoing_email_sms_log where hash = '".db_escape($hash)."'";
		$q = db_query($sql);
		return (!$q || db_num_rows($q) == 0) ? false : db_fetch_assoc($q);
	}

	/**
	 * Get post-piped text of the custom header text for the Protected Email Mode
	 */
	private function pipeCustomHeaderProtectedEmail($text)
	{
        // Get $repeat_instrument
		$repeat_instrument = "";
        if (isinteger($this->project_id) && $this->event_id != null && $this->form != null) {
            $Proj = new Project($this->project_id);
            if ($Proj->isRepeatingForm($this->event_id, $this->form)) {
				$repeat_instrument = $this->form;
            }
        }
        // Pipe and return the content (also add HTML line breaks, if needed)
        return nl2br(Piping::replaceVariablesInLabel($text, $this->record, $this->event_id, $this->instance, [], true, $this->project_id, false, $repeat_instrument, 1, false, false, $this->form));
	}

	/**
	 * Set the surrogate email body for Protected Email Mode
	 */
    public function setProtectedBody($lang_id)
    {
        $Proj = new Project($this->project_id);
        $context = Context::Builder()->project_id($this->project_id)->lang_id($lang_id)->Build();
        // Build link to the protected email message
        $emailHashUrl = APP_PATH_SURVEY_FULL . "?__email=".$this->protectedEmailHash;
        // Set the sender display name for the body
        $senderDisplay = ($this->getFromName() == '' ? "<a href='mailto:".$this->getFrom()."' style='color:#000066;'>".$this->getFrom()."</a>" : $this->getFromName() . " (<a href='mailto:".$this->getFrom()."' style='color:#000066;'>".$this->getFrom()."</a>)");
        $sender_info_tpl = MultiLanguage::getUITranslation($context, "email_users_37"); //= {0} has sent you a secure email message.
        $sender_info = RCView::interpolateLanguageString($sender_info_tpl, [strip_tags($senderDisplay)]); 
        // Logo (if applicable)
        $logo = self::renderProtectedEmailModeLogo($Proj->project_id);
        if ($logo != "") $logo = "<div style='padding:0 0 10px;'>$logo</div>";
        // Banner text
        $banner_text = $this->pipeCustomHeaderProtectedEmail(MultiLanguage::getProtectEmailModeCustomText($context));
        // Button
        $button_label = MultiLanguage::getUITranslation($context, "email_users_38");
        // Learn about link
        $learn_url = self::getProtectedEmailInfoLink($Proj->project_id);
        $learn_link = MultiLanguage::getUITranslation($context, "email_users_39");
        // Add values to the email content
        $bodyTemplate = '
        <table style="max-width:600px;font-family:\'Open Sans\',\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;"><tbody>
            <tr>
                <td style="color:#000;background-color:#d0d0d0;padding:5px 10px;width:500px;">
                    {logo}
                    <div>
                        {banner-text}
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="font-size:16px;line-height:18px;color:#505050;padding:15px;text-align:center">
                    <center><br>
                    <div>
                        {sender-info}
                    </div>
                    <br><br><br>
                    <div style="color:#ffffff;padding:10px;font-size:16px;max-width:240px;vertical-align:middle;">
                        <a href="{email-hash-url}" style="text-decoration:none;color:#000;background-color:#d0d0d0;padding:10px 30px 10px 30px !important;" target="_blank" >&#128274; {button-label}</a>
                    </div>
                    <br><br><br>
                    <div style="background-color:#f4f4f4;padding:15px 10px;">
                        <a href="{learn-url}" style="color:#000066;text-decoration:none" target="_blank">{learn-link}</a>
                    </div>
                    </center>
                </td>
            </tr>
        </tbody></table>';
        $replacements = array(
            "{logo}" => $logo,
            "{banner-text}" => $banner_text,
            "{sender-info}" => $sender_info,
            "{email-hash-url}" => $emailHashUrl,
            "{button-label}" => $button_label,
            "{learn-url}" => $learn_url,
            "{learn-link}" => $learn_link,
        );
        $protectedEmailBody = str_replace(array_keys($replacements), array_values($replacements), $bodyTemplate);
        // Set the new email content
        $this->setBody($protectedEmailBody);
        // Replace any <img> with src="..." with "cid:" reference to attachment
        return $this->replaceImagesWithCidsInBody(true);
    }

	/**
	 * Create the encrypted value for the email cookie
	 */
	private static function generateProtectedEmailCookieValue()
	{
		$encrypted = encrypt("redcap-protected-email ".NOW." ".$GLOBALS['salt']." ".generateRandomHash(24));
		$ee = rtrim(base64_encode($encrypted), '=');
		return $ee;
	}

	/**
	 * Verify the encrypted value for the email cookie
	 */
	private static function verifyProtectedEmailCookieValue($cookie_value)
	{
	    // If we're actually able to decrypt the cookie value successfully and it begins with "redcap-protected-email", then it has been verified
		return (strpos(decrypt(base64_decode($cookie_value)), "redcap-protected-email") === 0);
	}

    private static function getProtectedEmailInfoLink($project_id) {
        return "https://projectredcap.org/redcap-secure-messaging/";
    }

	/**
	 * Display all the attributes of an email from the email logging table by providing the email attributes returned from self::getEmailContentByHash()
	 */
	public function renderProtectedEmailPage($emailAttr, $forceDisplayEmail=false)
	{
	    global $lang, $project_contact_email;
	    $project_id = $emailAttr['project_id'];
        $Proj = new Project($project_id);

		// Translate (i.e. swap out $lang entries)
		$context = Context::Builder()
			->project_id($emailAttr["project_id"])
			->lang_id($emailAttr["lang_id"])
			->Build();
		MultiLanguage::translateProtectedEmailPage($context);

	    // Gather full recipient list (including CC and BCC)
	    $fullRecipientString = $emailAttr['recipients'];
	    if ($emailAttr['email_cc']) $fullRecipientString .= ";" . $emailAttr['email_cc'];
	    if ($emailAttr['email_bcc']) $fullRecipientString .= ";" . $emailAttr['email_bcc'];
		$recipients = explode(";", str_replace(" ", "", strtolower($fullRecipientString)));
		$recipients = array_combine($recipients, $recipients);

	    // Display the email if we already have a cookie that's verified authentic
		$displayEmail = ($forceDisplayEmail || (isset($_COOKIE[self::$protectedEmailCookie]) && self::verifyProtectedEmailCookieValue($_COOKIE[self::$protectedEmailCookie])));

	    // Generate the one-time code (for use in several places below)
		$ga = new GoogleAuthenticator();
		$code_verified = null;
		$code = $ga->getCode($fullRecipientString);
		// If code was submitted, then verify it
        $codeExpirationTimeMinutes = 5;
        if (isset($_POST['code'])) {
			$code_verified = $ga->verifyCode($fullRecipientString, $_POST['code'], $codeExpirationTimeMinutes * 2);    // 5 minutes = x*30sec clock tolerance
        }

		// Logo (if applicable)
		$logo = self::renderProtectedEmailModeLogo($Proj->project_id);
		if ($logo != "") $logo = "<div style='padding:0 0 10px;'>$logo</div>";
        // Banner text
        $banner_text = $Proj->project['protected_email_mode_custom_text'] == '' ? $lang['email_users_36'] : $this->pipeCustomHeaderProtectedEmail($Proj->project['protected_email_mode_custom_text']);
        // Verification code and expiration
        $verification_code = RCView::interpolateLanguageString($lang["email_users_99"], [$code]); //= Your REDCap Secure Messaging code is <b>{0}</b>
        $code_expiration = RCView::interpolateLanguageString($lang["email_users_100"], [$codeExpirationTimeMinutes]); //= (This code will expire in {0} minutes)
        // Learn more link
        $learn_url = self::getProtectedEmailInfoLink($project_id);
        $learn_link = $lang['email_users_39'];

		// Template for email for sending the one-time code
		$sendCodeBodyTemplate = '
        <table style="max-width:600px;font-family:\'Open Sans\',\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;"><tbody>
            <tr>
                <td style="color:#000;background-color:#d0d0d0;padding:5px 10px;width:500px;">
                    {logo}
                    <div>
                        {banner-text}
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="font-size:16px;line-height:18px;color:#505050;padding:15px;text-align:center">
                    <center><br>
                    <div>
                        {verification-code}<br><br>{code-expiration}
                    </div>
                    <br><br><br>
                    <div style="background-color:#f4f4f4;padding:15px 10px;">
                        <a href="{learn-url}" style="color:#000066;text-decoration:none" target="_blank">{learn-link}</a>
                    </div>
                    </center>
                </td>
            </tr>
        </tbody></table>';
        $replacements = array(
            "{logo}" => $logo,
            "{banner-text}" => $banner_text,
            "{verification-code}" => $verification_code,
            "{code-expiration}" => $code_expiration,
            "{learn-url}" => $learn_url,
            "{learn-link}" => $learn_link,
        );
        $sendCodeBody = str_replace(array_keys($replacements), array_values($replacements), $sendCodeBodyTemplate);
        if (!$displayEmail)
        {
            // 1) Make sure that the viewer has the cookie that is created after entering the one-time code. If they don't have cookie, give prompt to email the code.
            if (!isset($_COOKIE[self::$protectedEmailCookie]) && ($code_verified === false || $_SERVER['REQUEST_METHOD'] == 'GET')) {
                // Create drop-down list of recipients, if multiple recipients
                if (strpos($fullRecipientString, ";")) {
                    $recipientsDD = RCView::select(['class'=>'x-form-text x-form-field', 'name'=>'send_code'], $recipients, $recipients[0]);
                    $multipleRecipients = true;
                } else {
                    $multipleRecipients = false;
                    // Go ahead and send the email with the code
                    if ($code_verified !== false) {
                        REDCap::email($fullRecipientString, \Message::useDoNotReply($GLOBALS['project_contact_email']), $lang['global_234'], $sendCodeBody);
                    }
                }
                // Prepare some stuff to be output
                // Logo (if applicable)
                $logo = self::renderProtectedEmailModeLogo($Proj->project_id, $_GET['__email']);
                if ($logo != "") $logo = "<div style='padding:0 0 10px;'>$logo</div>";
                // Banner text
                $banner_text = $Proj->project['protected_email_mode_custom_text'] == '' ? '<i class="far fa-envelope me-2"></i> '.RCView::tt("email_users_36") : $this->pipeCustomHeaderProtectedEmail($Proj->project['protected_email_mode_custom_text']);
                addLangToJS(array(
                    "email_users_50",
                ));
                ?>
                <script type="text/javascript">
                $(function(){
                    $('#send-code-btn').click(function(){
                        $.post(window.location.href, { send_code: $('select[name="send_code"]').val() }, function(data){
                           simpleDialog(data, window.lang.email_users_50, 'send-code-email');
                           $('select[name="send_code"], #send-code-btn').prop('disabled', true);
                           setTimeout(function(){
                               $('#send-code-email').dialog('close');
                           }, 3000);
                        });
                    });
                    $('#submit-code-btn').click(function(){
                        $('select[name="send_code"]').removeAttr('name'); // Prevent this from getting submitted
                        showProgress(1);
                        $('#protected-email-form').submit();
                    });

                });
                </script>
                <style type="text/css">
                    body { background-color: #fcfcfc; }
                    p, .p { max-width: 100%; font-size: initial; }
                    #footer { max-width: 1000px; text-align: center; }
                    #footer a { text-decoration: none !important; }
                    A:link, A:visited, A:active, A:hover { text-decoration: underline !important; font-size: inherit; }
                </style>
                <div class="col-lg-12 my-3" style="max-width:1000px;">
                    <div class="clearfix mb-2">
                        <div class="mt-1 mb-3 mx-2 text-secondary float-start">
                            <?=$logo?>
                            <div data-mlm data-mlm-type="protmail-protected_email_mode_custom_text">
                                <?=$banner_text?>
                            </div>
                        </div>
                        <div class="mx-2 float-end">
                            <a href="<?self::getProtectedEmailInfoLink($Proj->project_id)?>" target="_blank"><img src="<?=APP_PATH_IMAGES?>redcap-logo-small.png"></a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header fs20">
                            <i class="fas fa-shield-alt"></i> <?=RCView::tt("email_users_40") // Quick security check before viewing your email ?>
                        </div>
                        <div class="card-body pt-4 px-4">
                            <form action="<?=$_SERVER['REQUEST_URI']?>" enctype="multipart/form-data" target="_self" method="post" id="protected-email-form">
                            <?php if ($multipleRecipients) { ?>
                                <p><?=RCView::tt("email_users_46") // Because you have not visited this page before or in a long time, we need to send you <u>a new email containing a security code</u> that will be used to verify your identify here. <b>Please select your email address from the list of all recipients below, and click the 'Send code' button</b>, which will send you a new email with a code. Then return to your inbox to retrieve that security code, and enter it below. ?></p>
                                <p class="mt-4 mb-2">
                                    <?=$recipientsDD?>
                                    <button id="send-code-btn" class="btn btn-xs btn-rcgreen fs16" onclick="return false;"><i class="far fa-envelope"></i> <?=RCView::tt("email_users_42") // Send code to this address ?></button>
                                </p>
                                <p class="mt-5"><?=RCView::tt("email_users_45") // After receiving the code via email, enter it below. ?></p>
                            <?php } else { ?>
                                <p><?=RCView::tt("email_users_47") // Because you have not visited this page before or in a long time, we just sent you <u>a new email containing a security code</u> that will be used to verify your identify here. Please return to your inbox right now to retrieve that security code, and then enter it below. Thanks! ?></p>
                            <?php } ?>
                            <p class="mt-4 mb-2 text-secondary fs14">
                                <input type="checkbox" name="public-computer" id="public-computer" style="position:relative;top:1px;" <?php if ((isset($_POST['public-computer']) && $_POST['public-computer'] == 'on') || $_SERVER['REQUEST_METHOD'] == 'GET') print "checked"; ?>>
                                <label for="public-computer"><?=RCView::tt("email_users_44") // This is a public computer ?><span style="color:#aaa;" class="fs11 ms-4"><?=RCView::tt("email_users_82") // (Unchecking this checkbox will store a cookie on this device.) ?></span></label>
                            </p>
                            <p class="mt-4 mb-2">
                                <input type="text" maxlength="6" name="code" data-rc-lang-attrs="placeholder=email_users_101" placeholder="<?=RCView::tt_attr("email_users_101") // Enter code ?>" class="x-form-text x-form-field">
                                <button id="submit-code-btn" class="btn btn-xs btn-primaryrc fs16"><?=RCView::tt("email_users_43") // Submit code ?></button>
                            </p>
                            <?php if ($code_verified === false) { ?>
                                <div class="red fs15 mt-4"><?=RCView::tt("email_users_41") // ERROR: Incorrect code was entered. Please try again! ?></div>
                            <?php } ?>
                            </form>
                        </div>
                    </div>
                </div>
                <?php
            }
            // 2) Post action for multi-recipient email that sends the one-time code to the selected recipient
            elseif (!isset($_COOKIE[self::$protectedEmailCookie]) && isset($_POST['send_code'])) {
                // Go ahead and send the email with the code
                if (isEmail($_POST['send_code']) && isset($recipients[$_POST['send_code']])) {
                    REDCap::email($_POST['send_code'], \Message::useDoNotReply($GLOBALS['project_contact_email']), $lang['global_234'], $sendCodeBody);
                    print RCView::interpolateLanguageString($lang['email_users_35'], [$_POST['send_code']]);
                } else {
                    print "ERROR!"; // ttfy?
                }
            }
            // 3) Cookie exists but could not be verified, so delete it and redirect back to start over
            elseif (isset($_COOKIE[self::$protectedEmailCookie]) && !self::verifyProtectedEmailCookieValue($_COOKIE[self::$protectedEmailCookie])) {
                deletecookie(self::$protectedEmailCookie);
                redirect($_SERVER['REQUEST_URI']);
            }
            // 4) Recipient entered the code they received via email. Set a cookie, and redirect the page.
            elseif (isset($_POST['code']) && $code_verified) {
                $displayEmail = true;
                // If we're on a public computer, do not save the cookie
                if (!(isset($_POST['public-computer']) && $_POST['public-computer'] == 'on')) {
                    savecookie(self::$protectedEmailCookie, self::generateProtectedEmailCookieValue(), 2592000); // Set to expire after 1 month
                }
            }
        }
		// 5) Output the email contents
		if ($displayEmail) {
			// File download URL base for any email attachments
			$file_download_page = APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=" . urlencode("DataEntry/file_download.php");
			// Separate date and time components and then later recombine
			list ($data_value_date, $data_value_time) = explode(" ", DateTimeRC::format_ts_from_ymd($emailAttr['time_sent'], true), 2);
			// Convert time format and recombine (if datetime)
			$time_sent = trim($data_value_date . " " . DateTimeRC::format_time($data_value_time));
			// Set the "X min ago" text
			// If timestamp is NOW, then return "just now" text
			$ts = $emailAttr['time_sent'];
			if ($ts == NOW) {
				$ago = RCView::tt("ws_176"); // just now
			} else {
				// First convert to minutes
				$ts = (strtotime(NOW) - strtotime($ts)) / 60;
				// Return if less than 60 minutes
				if ($ts < 60) {
					$ago = $ts < 1 ? 
                        RCView::tt("ws_177") : 
                        RCView::tt_i(floor($ts) == 1 ? "ws_178" : "ws_179", [floor($ts)]);
				} else {
                    $ts = $ts / 60;
					if ($ts < 24) {
                        // Convert to hours if less than 24 hours
						$ago = RCView::tt_i(floor($ts) == 1 ? "ws_180" : "ws_181", [floor($ts)]);
					} else {
						// Convert to days
						$ts = $ts / 24;
						$ago = RCView::tt_i(floor($ts) == 1 ? "ws_182" : "ws_183", [floor($ts)]); 
					}
				}
			}
			// Attachments?
			$attachments = [];
			$totalAttachmentSize = 0;
			$someAttachmentsWereDeleted = false;
			if ($emailAttr['attachment_doc_ids'] == '' && $emailAttr['attachment_names'] != '') {
				foreach (explode("; ", $emailAttr['attachment_names']) as $fileName) {
					$attachments[] = ['name' => $fileName, 'size' => '', 'id' => ''];
				}
			} elseif ($emailAttr['attachment_doc_ids'] != '') {
				foreach (explode(",", $emailAttr['attachment_doc_ids']) as $edoc_id) {
					list ($fileName, $fileSize) = Files::getEdocNameAndSize($edoc_id);
					$attachments[] = ['name' => $fileName, 'size' => $fileSize, 'id' => $edoc_id];
					$totalAttachmentSize += $fileSize;
					if (Files::edocWasDeleted($edoc_id)) {
						$someAttachmentsWereDeleted = true;
					}
				}
			}

			// Don't display attachment sizes and download links if we don't have doc_ids (and assuming they haven't been deleted)
			$haveAttachmentSizes = ($totalAttachmentSize > 0 && !$someAttachmentsWereDeleted);
			// Set email body
			$emailBody = linkify(filter_tags(remove_html_tags(($emailAttr['message_html'] == '' ? $emailAttr['message'] : $emailAttr['message_html']), ["html", "body"])));
            // Add appropriate line breaks for SMS messages
            if ($emailAttr['type'] == "SMS") {
				$emailBody = nl2br($emailBody);
			}
			// Replace any embedded images via their cid tag
			if (strpos($emailBody, "cid:") !== false) {
				foreach ($attachments as $key=>$attr) {
					// Look for images with CID
					$this_attachment = $attr['name'];
					$this_attachment_ext = getFileExt($this_attachment);
                    if (in_array($this_attachment_ext, array('png', 'gif', 'jpg', 'jpeg', 'bmp'))) {
						$this_attachment_cid = "cid:".rtrim($this_attachment, ".".$this_attachment_ext);
						if (strpos($emailBody, $this_attachment_cid) !== false) {
                            // Remove from attachments list
                            unset($attachments[$key]);
                            // If we have an edoc_id, then create image view link to it
                            if (isinteger($attr['id'])) {
                                $image_view_page = APP_PATH_SURVEY . "index.php?pid=$project_id&__email={$_GET['__email']}&__passthru=".urlencode("DataEntry/image_view.php");
								$this_file_image_src = $image_view_page.'&doc_id_hash='.Files::docIdHash($attr['id']).'&id='.$attr['id'];
								$emailBody = str_replace($this_attachment_cid, $this_file_image_src, $emailBody);
							}
						}
					}
                }
		    }
            // Prepare some stuff to be output
            // Logo (if applicable)
            $logo = self::renderProtectedEmailModeLogo($Proj->project_id, $_GET['__email']??"");
            if ($logo != "") $logo = "<div style='padding:0 0 10px;'>$logo</div>";
            // Banner text
            $banner_text = $Proj->project['protected_email_mode_custom_text'] == '' ? '<i class="far fa-envelope me-2"></i> '.RCView::tt("email_users_36") : $this->pipeCustomHeaderProtectedEmail($Proj->project['protected_email_mode_custom_text']);
            ?>
            <script type="text/javascript">
                $(function(){
                    $('.email-body a').prop('target', '_blank');
                });
            </script>
            <style type="text/css">
                body { background-color: #fcfcfc; }
                p, .p { max-width: 100%; font-size: initial; }
                #footer { max-width: 1000px; text-align: center; }
                #footer a { text-decoration: none !important;}
                A:link, A:visited, A:active, A:hover { text-decoration: underline !important; font-size: inherit; }
            </style>
            <div class="col-lg-12 my-3" style="max-width:1000px;">
                <div class="clearfix mb-2">
                    <div class="mt-1 mb-3 mx-2 text-secondary float-start">
                        <?=$logo?>
                        <div data-mlm data-mlm-type="protmail-protected_email_mode_custom_text">
                            <?=$banner_text?>
                        </div>
                    </div>
                    <div class="mx-2 float-end">
                        <a href="<?=self::getProtectedEmailInfoLink($Proj->project_id)?>" target="_blank"><img src="<?=APP_PATH_IMAGES?>redcap-logo-small.png"></a>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="title d-flex align-items-center justify-content-between">
                            <div class="email-subject d-flex align-items-center fs20 font-weight-bold">
								<?=filter_tags($emailAttr['email_subject'])?>
                            </div>
                            <div class="icons">
                                <a href="javascript:window.print();"><i class="fas fa-print fs16 hide_in_print"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-3 px-4">
                        <div class="email-content">
                            <div class="email-head">
                                <div class="email-head-sender d-flex align-items-center justify-content-between flex-wrap">
                                    <div class="d-block align-top">
                                        <div class="sender align-left align-top">
                                            <div class="mb-1 fs16 boldish"><?=RCView::tt("global_37") // From: ?> <a class="text-decoration-underline fs16" href="mailto:<?=strip_tags($emailAttr['sender'])?>"><?=strip_tags($emailAttr['sender'])?></a></div>
                                            <div class="text-secondary fs14"><?=RCView::tt("global_38") // To: ?> <?=strip_tags($emailAttr['recipients'])?></div>
                                            <?php if ($emailAttr['email_cc'] != '') { ?>
                                                <div class="text-secondary fs14">CC: <?=strip_tags($emailAttr['email_cc'])?></div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="date align-top fs14"><?=$time_sent." <span class=\"text-secondary ms-1\">($ago)</span>"?><br><br><br></div>
                                </div>
                            </div>
                            <div class="email-body my-3 py-1">
                                <?=$emailBody?>
                            </div>
                            <?php if (!empty($attachments)) { ?>
                                <div class="email-attachments">
                                    <div class="title mb-1"><?=RCView::tt("alerts_128") // Attachments ?> <span>(<?=RCView::tt_i(count($attachments) == 1 ? "email_users_49" : "email_users_48", [count($attachments)])?><?php if ($haveAttachmentSizes) { ?>, <?=round($totalAttachmentSize/1024,1)?> KB</span><?php } ?>)</div>
                                    <ul>
                                        <?php foreach ($attachments as $attr) { ?>
                                            <li>
                                                <?php
                                                if ($haveAttachmentSizes) {
                                                    print "<a target='_blank' href='$file_download_page&type=email_attachment&doc_id_hash=".Files::docIdHash($attr['id'])."&id={$attr['id']}&pid=$project_id&__email={$_GET['__email']}'>";
                                                    ?><i class="fas fa-paperclip"></i> <?=$attr['name']?><span class="text-muted ms-2">(<?=round($attr['size']/1024,1)?> KB)</span></a>
                                                <?php } else { ?>
                                                    <i class="fas fa-paperclip"></i> <?=$attr['name']?>
                                                <?php } ?>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
		}
	}

    // Output the IMG tag of the custom logo (if applicable) for Protected Email Mode
    private static function renderProtectedEmailModeLogo($project_id, $emailHash="")
    {
        global $lang;
		$Proj = new Project($project_id);
		if ($Proj->project['protected_email_mode'] && isinteger($Proj->project['protected_email_mode_logo']))
        {
			$logo = $Proj->project['protected_email_mode_logo'];
			//Set max-width for logo (include for mobile devices)
			$logo_width = (isset($GLOBALS['isMobileDevice']) && $GLOBALS['isMobileDevice']) ? '340' : '820';
			// Get img dimensions (local file storage only)
			$thisImgMaxWidth = $logo_width;
			$styleDim = "max-width:{$thisImgMaxWidth}px;";
			list ($thisImgWidth, $thisImgHeight) = Files::getImgWidthHeightByDocId($logo, true);
			if (is_numeric($thisImgHeight)) {
				$thisImgMaxHeight = round($thisImgMaxWidth/$thisImgWidth*$thisImgHeight);
				if ($thisImgWidth < $thisImgMaxWidth) {
					// Use native dimensions
					$styleDim = "width:{$thisImgWidth}px;max-width:{$thisImgWidth}px;height:{$thisImgHeight}px;max-height:{$thisImgHeight}px;";
				} else {
					// Shrink size
					$styleDim = "width:{$thisImgMaxWidth}px;max-width:{$thisImgMaxWidth}px;height:{$thisImgMaxHeight}px;max-height:{$thisImgMaxHeight}px;";
				}
			}
			return "<img src='" . APP_PATH_SURVEY . "index.php?pid=$project_id&__email=$emailHash&doc_id_hash=".Files::docIdHash($logo)."&__passthru=".urlencode("DataEntry/image_view.php")."&id=$logo' alt='".js_escape($lang['survey_1140'])."' title='".js_escape($lang['survey_1140'])."' style='max-width:{$logo_width}px;$styleDim'>";
		}
        return "";
	}

    // If any ASIs, alerts, or survey confirmation emails have email bodies with identifier fields piped in them, then return true.
    public static function recommendProtectedEmailMode($project_id)
	{
        // If system-level setting is disabled, then don't recommend
        if ($GLOBALS['protected_email_mode_global'] == "0") return false;
        // If Protected Email Mode is already enabled, then don't recommend
        $Proj = new Project($project_id);
        if ($Proj->project['protected_email_mode']) return false;
        // Alerts
        $alerts = new Alerts();
		if ($alerts->anyAlertsContainIdentifierFields($project_id)) return true;
        // Survey confirmation emails
        if (Survey::anySurveyConfEmailsContainIdentifierFields($project_id)) return true;
        // ASIs
		$surveyScheduler = new SurveyScheduler($project_id);
		if ($surveyScheduler->anyASIContainIdentifierFields()) return true;
        // Nothing has identifier fields
        return false;
	}

	/**
	 * Returns HTML suitable for displaying to the user if an email fails to send.
	 */
	function getSendError()
	{
		global $lang;
		return  "<div style='font-size:12px;background-color:#F5F5F5;border:1px solid #C0C0C0;padding:10px;'>
			<div style='font-weight:bold;border-bottom:1px solid #aaaaaa;color:#800000;'>
			<img src='".APP_PATH_IMAGES."exclamation.png'>
			{$lang['control_center_243']}
			</div><br>
			{$lang['global_37']} <span style='color:#666;'>{$this->fromName} &#60;{$this->from}&#62;</span><br>
			{$lang['global_38']} <span style='color:#666;'>{$this->toName} &#60;{$this->to}&#62;</span><br>
			{$lang['control_center_28']} <span style='color:#666;'>{$this->subject}</span><br><br>
			{$this->body}<br>
			</div><br>";
	}

	## Set up a curl call to the specified Mandrill endpoint and attach the API key to the data to be sent
	## Return the response data, or else return an error message if HTTP response code is not 200
	/**
	 * Set up a curl call to the specified Mandrill endpoint and attach the API key to the data to be sent
	 * Return the response data, or else return an error message if HTTP response code is not 200
	 * @param $data array
	 * @param $endpoint string
	 * @return string
	 */
	public static function sendMandrillRequest($data,$endpoint)
	{
		## Don't send if API key doesn't exist
		if(empty($GLOBALS["mandrill_api_key"])) return false;

		## Append API key to data to send
		$data["key"] = $GLOBALS["mandrill_api_key"];

		$data = http_build_query($data);
		$url = 'https://mandrillapp.com/api/1.0/'.$endpoint;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_POST,true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mandrill-Curl/1.0');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		$output = curl_exec($ch);
		$httpCode = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
		curl_close($ch);

		if($httpCode != 200) {
			$output = ["status" => "error","message" => "$url returned a status $httpCode :\r\n".var_export($output,true)];
			$output = json_encode($output);
		}

		## Return the response
		return $output;
	}

	// Replace any <img> with src="..." with "cid:" reference to attachment
    private function replaceImagesWithCidsInBody($protectedEmailMode=false)
	{
        // Find Image SRC attributes containing MyCap/participant_info.php
		if ($GLOBALS['mycap_enabled_global'] && (!defined("PROJECT_ID") || (defined("PROJECT_ID") && $GLOBALS['mycap_enabled']))
            && strpos($this->getBody(), "MyCap/participant_info.php") !== false)
        {
            require_once APP_PATH_LIBRARIES . "phpqrcode/lib/full/qrlib.php";
            preg_match_all('/(src)=[\"\'](.+?)[\"\'].*?/i', $this->getBody(), $result);
            foreach ($result[2] as $key => $img_src)
            {
                if (strpos($img_src, "MyCap/participant_info.php") === false) continue;
                // Parse the URL and validate its components
                $redirectUrlParts = parse_url($img_src);
                parse_str($redirectUrlParts['query'], $urlPart);
                if (!(isset($urlPart['pid']) && isinteger($urlPart['pid']) && isset($urlPart['par_code']))) {
                    continue;
                }
                // Save file to TEMP to handle non-local storage types
                $filename_pre = APP_PATH_TEMP . date('YmdHis') . "_email_inline_img_" . substr(sha1(rand()), 0, 10);
                $filename = $filename_pre . ".png";
                // Get the needed parameters
                $myCapProj = new Vanderbilt\REDCap\Classes\MyCap\MyCap($urlPart['pid']);
                $project_code = $myCapProj->project['code'];
                // Build the QR code and store it in temp directory
                Vanderbilt\REDCap\Classes\MyCap\Participant::makeParticipantImage(
                    Vanderbilt\REDCap\Classes\MyCap\MyCapConfiguration::ENDPOINT,
                    $project_code,
                    $urlPart['par_code'],
                    APP_PATH_DOCROOT.'Resources/images/mycap_qr_overlay.png',
                    $filename
                );
                // Add attachments to array
                $this->setAttachment($filename);
                // Replace img SRC in message with CID
                $cid = basename($filename_pre);
                // Add to array to reference later when adding the attachment
                $this->cids[$filename] = $cid;
                // If inline image, add CID for src. If not an image, add as plain text filename label + add as regular attachment.
                $this->setBody(str_replace($img_src, "cid:$cid", $this->getBody()));
                // If using Protected Email Mode, then we need to add the logo CID manually to attachments (because the img-to-CID code was already run on the real email text beforehand)
                if ($protectedEmailMode) {
                    // Get mime type
                    $mime_type = \ExternalModules\ExternalModules::getContentType(str_replace(".", "", SendIt::getFileExtension(basename($filename))));
                    if (empty($mime_type)) $mime_type = "application/octet-stream";
                    // Return these attributes to be added manually for each platform/library
                    return ['cid'=>$cid, 'mime_type'=>$mime_type, 'filename'=>$filename];
                }
            }
        }

        // Find Image SRC attributes containing DataEntry/image_view.php
		if (strpos($this->getBody(), "DataEntry/image_view.php") !== false || strpos($this->getBody(), "&__passthru=".urlencode("DataEntry/image_view.php")) !== false)
		{
			preg_match_all('/(src|rc-src-replace)=[\"\'](.+?)[\"\'].*?/i', $this->getBody(), $result);
			foreach ($result[2] as $key=>$img_src)
            {
				// Parse the URL and validate its components
				$redirectUrlParts = parse_url($img_src);
				parse_str($redirectUrlParts['query'], $urlPart);
				if (!(isset($urlPart['id']) && isinteger($urlPart['id']) && isset($urlPart['doc_id_hash']))) {
					continue;
				}
				$edoc = $urlPart['id'];
				$doc_id_hash = $urlPart['doc_id_hash'];
				// Get project-level SALT using PID
				$pid = $urlPart['pid'] ?? null;
				$pidSalt = ($pid === null) ? null : Project::getProjectSalt($pid);
				// Validate doc id hash
				if ($doc_id_hash != Files::docIdHash($edoc, $pidSalt)) continue;
				$isImgFile = ($result[1][$key] == 'src');
				// Obtain the file's name and contents
				list ($mimeType, $docName, $fileContent) = Files::getEdocContentsAttributes($edoc);
				// Save file to TEMP to handle non-local storage types
				$filename_pre = APP_PATH_TEMP . date('YmdHis') . "_email_inline_img_" . substr(sha1(rand()), 0, 10);
				$filename = $filename_pre . getFileExt($docName, true);
				file_put_contents($filename, $fileContent);
				// Add attachments to array
				$this->setAttachment($filename);
				// Replace img SRC in message with CID
				$cid = basename($filename_pre);
				// Add to array to reference later when adding the attachment
				$this->cids[$filename] = $cid;
				// If inline image, add CID for src. If not an image, add as plain text filename label + add as regular attachment.
				$this->setBody(str_replace($img_src, ($isImgFile ? "cid:$cid" : ""), $this->getBody()));
                // Add the stored file name as the "lsrc" attribute for the image so that it can be redisplayed later in the Notification Log
                $this->setBody(str_replace( ["src='cid:$cid'",                   'src="cid:'.$cid.'"'],
                                            ["src='cid:$cid' lsrc='$img_src'",  'src="cid:'.$cid.'" lsrc="'.$img_src.'"'],
                                            $this->getBody()));
                // If using Protected Email Mode, then we need to add the logo CID manually to attachments (because the img-to-CID code was already run on the real email text beforehand)
				if ($protectedEmailMode) {
                    // Get mime type
					$mime_type = \ExternalModules\ExternalModules::getContentType(str_replace(".", "", SendIt::getFileExtension(basename($filename))));
					if (empty($mime_type)) $mime_type = "application/octet-stream";
                    // Return these attributes to be added manually for each platform/library
                    return ['cid'=>$cid, 'mime_type'=>$mime_type, 'filename'=>$filename];
				}
			}
		}
        return [];
	}

	// Replace any file download relative links with valid full links
	private function replaceFileDownloadLinks($recipientIsSurveyParticipant=false)
	{
		if (strpos($this->getBody(), "DataEntry/file_download.php") !== false || strpos($this->getBody(), "&__passthru=".urlencode("DataEntry/file_download.php")) !== false)
		{
			preg_match_all('/(href)=[\"\'](.+?)[\"\'].*?/i', $this->getBody(), $result);
			foreach ($result[2] as $key=>$href)
			{
				$hrefOrig = $href;
				// Parse the URL and validate its components
				$redirectUrlParts = parse_url($href);
				parse_str($redirectUrlParts['query'], $urlPart);
				if (!(isset($urlPart['id']) && isset($urlPart['pid']) && isinteger($urlPart['id'])
					&& isset($urlPart['doc_id_hash']) && isinteger($urlPart['pid']))) {
					continue;
				}
				$isDownloadLinkForm = (strpos($href, APP_PATH_WEBROOT."DataEntry/file_download.php") === 0);
				$isDownloadLinkSurvey = (strpos($href, APP_PATH_SURVEY) === 0 && strpos($href, "&__passthru=".urlencode("DataEntry/file_download.php")) !== false);
				if (!$isDownloadLinkForm && !$isDownloadLinkSurvey) continue;
				$edoc = $urlPart['id'];
				$doc_id_hash = $urlPart['doc_id_hash'];
				// If link has survey hash and recipient is not a survey participant, remove it up front (because we will validate it and re-add it later)
				if (isset($urlPart['s']) && $urlPart['s'] != '') {
					$href = str_replace("&s=".$urlPart['s'], "", $href);
					$href = str_replace("?s=".$urlPart['s'], "", $href);
				} else {
					$href = str_replace("?s=", "", $href);
					$href = str_replace("&s=", "", $href);
				}
				$surveyRecord = $urlPart['record'];
				$surveyEventId = $urlPart['event_id'];
				$surveyInstrument = $urlPart['page'];
				$surveyInstance = $urlPart['instance'];
				// Get project-level SALT using PID
				$pid = $urlPart['pid'];
				$pidSalt = Project::getProjectSalt($pid);
				// Validate doc id hash
				if ($doc_id_hash != Files::docIdHash($edoc, $pidSalt)) continue;
				// If the email recipient is a survey participant, make sure the download link is the survey version of the download link
				if ($recipientIsSurveyParticipant) {
					// Get the survey hash for this specific record/event/survey/instance
					$Proj = new Project($pid);
					if ($urlPart['s'] == '') {
						// The current "page" in the download link might not refer to a survey-enabled instrument, so if not, grab the instrument/event_id for any survey in the project as a suitable replacement
						if (!isset($Proj->forms[$surveyInstrument]['survey_id'])) {
							// "page" is not a survey, so grab the first available one in the project
							$allSurveyIds = array_keys($Proj->surveys);
							$randomSurveyId = array_shift($allSurveyIds);
							$surveyInstrument = $Proj->surveys[$randomSurveyId]['form_name'];
							$surveyInstance = 1;
							foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
								if (in_array($surveyInstrument, $these_forms)) {
									$surveyEventId = $this_event_id;
									break;
								}
							}
						}
					}
					// Use our survey parameters to build us a valid survey hash (and not a public one) to use in the download URL
					$surveyLinkForFile = REDCap::getSurveyLink($surveyRecord, $surveyInstrument, $surveyEventId, $surveyInstance, $pid);
					if ($surveyLinkForFile == null) continue;
					$redirectUrlParts2 = parse_url($surveyLinkForFile);
					parse_str($redirectUrlParts2['query'], $urlPartSurvey);
					if ($urlPartSurvey['s'] == '') continue;
					// We need to add __response_hash__ to the survey link
					$thisResponseHash = Survey::getResponseHashFromRecordEvent($surveyRecord, $surveyInstrument, $surveyEventId, $surveyInstance, $pid);
					$href .= "&__response_hash__=$thisResponseHash";
					// Rebuild download URL
					if ($isDownloadLinkForm) {
						$href = str_replace(APP_PATH_WEBROOT."DataEntry/file_download.php?", APP_PATH_SURVEY_FULL."?s={$urlPartSurvey['s']}&__passthru=".urlencode("DataEntry/file_download.php")."&", $href);
					} elseif ($isDownloadLinkSurvey) {
						$href = str_replace(APP_PATH_SURVEY."index.php?", APP_PATH_SURVEY_FULL."index.php?s={$urlPartSurvey['s']}&", $href);
						$href = str_replace(APP_PATH_SURVEY."?", APP_PATH_SURVEY_FULL."?s={$urlPartSurvey['s']}&", $href);
					}
				} else {
					// Rebuild download URL
					if ($isDownloadLinkForm) {
						$href = str_replace(APP_PATH_WEBROOT."DataEntry/file_download.php?", APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/DataEntry/file_download.php?", $href);
					} elseif ($isDownloadLinkSurvey) {
						$href = str_replace(APP_PATH_SURVEY, APP_PATH_SURVEY_FULL, $href);
						// Re-add survey hash if link originally contained it
						$surveyLinkForFile = REDCap::getSurveyLink($surveyRecord, $surveyInstrument, $surveyEventId, $surveyInstance, $pid);
						$redirectUrlParts2 = parse_url($surveyLinkForFile);
						parse_str($redirectUrlParts2['query'], $urlPartSurvey);
						$href .= "&s=".$urlPartSurvey['s'];
						// We need to add __response_hash__ to the survey link
						$thisResponseHash = Survey::getResponseHashFromRecordEvent($surveyRecord, $surveyInstrument, $surveyEventId, $surveyInstance, $pid);
						$href .= "&__response_hash__=$thisResponseHash";
					}
				}
				// Perform the replace of the HREF link attribute
				if ($hrefOrig != $href) {
					$this->setBody(str_replace($hrefOrig, $href, $this->getBody()));
				}
			}
		}
	}

	// Using the ssq_id, query table to determine if this message is noted as containing piped identifier fields
	public function doesMessageContainPipedIdentifiersBySSQID($ssq_id)
	{
	    if (!isinteger($ssq_id)) return false;
        $sql = "select 1 from redcap_outgoing_email_sms_identifiers where ssq_id = $ssq_id";
        $q = db_query($sql);
        return (db_num_rows($q) > 0);
	}

    /**
     * Set up a curl call to the specified Azure Communication Services endpoint and attach the API key to the data to be sent
     * Return the response data, or else return an error message if HTTP response code is not 202
     * @param $messageData array
     * @return string
     */
    public static function sendAzureCommServiceRequest($messageData)
    {
        $endpoint = rtrim($GLOBALS["azure_comm_api_endpoint"], "/") . "/";

        $secret = $GLOBALS["azure_comm_api_key"];

        $requestUri = $endpoint.'emails:send?api-version=2023-03-31';

        $serializedBody = json_encode($messageData);

        // Specify the 'x-ms-date' header as the current UTC timestamp according to the RFC1123 standard
        $date = gmdate("D, d M Y H:i:s T");

        // Compute a content hash for the 'x-ms-content-sha256' header.
        $contentHash = base64_encode(hash('sha256', $serializedBody, true));

        $url = parse_url($requestUri);
        // Prepare a string to sign.
        $stringToSign = "POST\n".$url['path']."?".$url['query']."\n".$date.";".$url['host'].";".$contentHash;
        // Compute the signature.
        $hashedBytes = hash_hmac('sha256', $stringToSign, base64_decode($secret), true);
        $signature = base64_encode($hashedBytes);

        // Concatenate the string, which will be used in the authorization header.
        $authorizationHeader = "HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=".$signature;

        $headers = array("x-ms-date: ".$date,
                        "x-ms-content-sha256: ".$contentHash,
                        "Authorization: ".$authorizationHeader,
                        "Content-Type: application/json; charset=utf-8",
                        "Host: ".parse_url($requestUri, PHP_URL_HOST));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_POST,true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $serializedBody);

        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if($httpCode != 202) {
            $output = ["status" => "error","message" => "$requestUri returned a status $httpCode :\r\n".var_export($output,true)];
            $output = json_encode($output);
        }

        ## Return the response
        return $output;
    }

    // Obtain the do-not-reply email address, if enabled. If not available, return the provided fallback email instead.
    public static function useDoNotReply($fallback)
    {
        $donotreply = trim($GLOBALS['do_not_reply_email'] ?? "");
        return $donotreply != "" ? $donotreply : $fallback;
    }
}
