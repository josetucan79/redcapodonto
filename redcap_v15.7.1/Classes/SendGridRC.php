<?php

use GuzzleHttp\Client;
use SendGrid\Mail\To;
use SendGrid\Mail\Cc;
use SendGrid\Mail\Bcc;
use SendGrid\Mail\From;
use SendGrid\Mail\Content;
use SendGrid\Mail\Mail;
use SendGrid\Mail\Personalization;
use SendGrid\Mail\Subject;
use SendGrid\Mail\Header;
use SendGrid\Mail\CustomArg;
use SendGrid\Mail\SendAt;
use SendGrid\Mail\Attachment;
use SendGrid\Mail\Asm;
use SendGrid\Mail\MailSettings;
use SendGrid\Mail\BccSettings;
use SendGrid\Mail\SandBoxMode;
use SendGrid\Mail\BypassListManagement;
use SendGrid\Mail\BypassSpamManagement;
use SendGrid\Mail\BypassBounceManagement;
use SendGrid\Mail\BypassUnsubscribeManagement;
use SendGrid\Mail\Footer;
use SendGrid\Mail\SpamCheck;
use SendGrid\Mail\TrackingSettings;
use SendGrid\Mail\ClickTracking;
use SendGrid\Mail\OpenTracking;
use SendGrid\Mail\SubscriptionTracking;
use SendGrid\Mail\Ganalytics;
use SendGrid\Mail\ReplyTo;

/**
 * SendGridRC
 * This class is used for processes related to sending emails via SendGrid's REST API
 */
class SendGridRC
{
	public static function init() {}


	// Retrive the SendGrid API key from the redcap_projects table using the project id
	public static function getAPIKeyByPid($pid)
	{
		try {
			$sql = "select sendgrid_project_api_key from redcap_projects
			where project_id = '".db_escape($pid)."' limit 1";
			$q = db_query($sql);
			$sendgrid_project_api_key  = db_result($q, 0, 'sendgrid_project_api_key');
		} catch (Exception $e) {
			REDCap::logEvent("Failed to get SendGrid API Key", $e->getMessage(), "", null, null, $pid);
		}
		return decrypt($sendgrid_project_api_key);
	}


	public static function verifyAPIKey($api_key, $project_id = null) {
		global $lang;

		$sg = new \SendGrid($api_key);
		$required_scopes = array('mail.send', 'templates.read', 'whitelabel.read', 'asm.groups.read');

		$verified = false;
		$error_msg = $lang['alerts_338'];
		try {
			$response = $sg->client->scopes()->get();
			if (in_array($response->statusCode(), [200])) {
				$verified = true;
				$error_msg = '';
				$body = json_decode($response->body(), TRUE);

				$missing_scopes = array();
				foreach ($required_scopes as $scope) {
					if (!in_array($scope, $body['scopes'])) {
						array_push($missing_scopes, $scope);
					}
				}

				if (count($missing_scopes) > 0) {
					$verified = false;
					$error_msg = $lang['alerts_339'] . ' ' . join(", ", $missing_scopes);;
				}
			};
		} catch (Exception $e) {
			REDCap::logEvent("Failed to Verify SendGrid API Key", $e->getMessage(), "", null, null, $project_id);
		}

		return array($verified, $error_msg);
	}


	// Function to send a SendGrid Dynamic Template Email
	public static function sendDynamicTemplateEmail($api_key, $from_email, $to, $dynamic_template_id, $project_id,
		$record, $event_id, $instrument, $instance, $lang_id, $emailCategory, $cc=null, $bcc=null, $attachments=null, $dynamic_data=null,
		$mail_send_configuration=null)
	{
		global $lang;
		$sg = new \SendGrid($api_key);

		try {
			$mail = new Mail();

			$mail->setFrom(new From($from_email));
			$mail->setTemplateId($dynamic_template_id);

			$personalization = new Personalization();
			foreach ($dynamic_data as $key => $value) {
				$personalization->addDynamicTemplateData($key, $value);
			}
			$mail->addPersonalization($personalization);

			if (!empty($attachments)) {
				foreach ($attachments as $attachmentName=>$this_attachment_path) {
					$mime_type = \ExternalModules\ExternalModules::getContentType(str_replace(".", "", SendIt::getFileExtension(basename($this_attachment_path))));
					if (empty($mime_type)) $mime_type = "application/octet-stream";
					$mail->addAttachment(base64_encode(file_get_contents($this_attachment_path)), $mime_type, $attachmentName, 'attachment', null);
				}
			}

			foreach (preg_split("/[;,]+/", $to) as $thisTo) {
				$thisTo = trim($thisTo);
				if ($thisTo == '') continue;
				$mail->addTo($thisTo);
			}

			if ($cc) {
				foreach (preg_split("/[;,]+/", $cc) as $thisCc) {
					$thisCc = trim($thisCc);
					if ($thisCc == '') continue;
					$mail->addCc($thisCc);
				}
			}

			if ($bcc) {
				foreach (preg_split("/[;,]+/", $bcc) as $thisBcc) {
					$thisBcc = trim($thisBcc);
					if ($thisBcc == '') continue;
					$mail->addBcc($thisBcc);
				}
			}
		} catch (Exception $e) {
			REDCap::logEvent("Constructing SendGrid Email Failed", $e->getMessage(), "", $record, $event_id, $project_id);
		}

		try {
			$mail_settings = new MailSettings();

			if ($mail_send_configuration["bypass-list-management"]) {
				// bypass list management cannot be combined with any other bypass option
				$bypass_list_management = new BypassListManagement();
				$bypass_list_management->setEnable($mail_send_configuration["bypass-list-management"] ?? false);
				$mail_settings->setBypassListManagement($bypass_list_management);
			} else {
				$bypass_spam_management = new BypassSpamManagement();
				$bypass_spam_management->setEnable($mail_send_configuration["bypass-spam-management"] ?? false);
				$mail_settings->setBypassSpamManagement($bypass_spam_management);

				$bypass_bounce_management = new BypassBounceManagement();
				$bypass_bounce_management->setEnable($mail_send_configuration["bypass-bounce-management"] ?? false);
				$mail_settings->setBypassBounceManagement($bypass_bounce_management);

				$bypass_unsubscribe_management = new BypassUnsubscribeManagement();
				$bypass_unsubscribe_management->setEnable($mail_send_configuration["bypass-unsubscribe-management"] ?? false);
				$mail_settings->setBypassUnsubscribeManagement($bypass_unsubscribe_management);
			}

			$sandbox_mode = new SandboxMode();
			$sandbox_mode->setEnable($mail_send_configuration["sandbox-mode"] ?? false);
			$mail_settings->setSandboxMode($sandbox_mode);

			$mail->setMailSettings($mail_settings);
			
			$tracking_settings = new TrackingSettings();

			$click_tracking = new ClickTracking();
			$click_tracking->setEnable($mail_send_configuration["click-tracking"] ?? false);
			$tracking_settings->setClickTracking($click_tracking);

			$open_tracking = new OpenTracking();
			$open_tracking->setEnable($mail_send_configuration["open-tracking"] ?? false);
			$tracking_settings->setOpenTracking($open_tracking);
			
			if ($mail_send_configuration["subscription-tracking"] == false) {
				// For subscription tracking, setEnable(true) does not behave as expected.
				// if subscription tracking is true, meaning the user wants subscription
				// tracking links inserted in their email, then the settings should
				// be left out of the API request payload so the default behavior of 
				// inserting subscription tracking links can take place as desired.
				$subscription_tracking = new SubscriptionTracking();
				$subscription_tracking->setEnable(false);
				$tracking_settings->setSubscriptionTracking($subscription_tracking);
			}

			$mail->setTrackingSettings($tracking_settings);

			if ($mail_send_configuration["categories"]) {
				foreach ($mail_send_configuration["categories"] as $index => $category) {
					$mail->addCategory($category);
				}
			}

			if ($mail_send_configuration["unsubscribe-group-id"]) {
				$asm = new ASM();
				$asm->setGroupId((int)$mail_send_configuration["unsubscribe-group-id"]);
				$mail->setASM($asm);
			}
			
		}  catch (Exception $e) {
			REDCap::logEvent("Setting SendGrid Email Mail Send Configuration Failed", $e->getMessage(), "", $record, $event_id, $project_id);
		}

		$message = new Message($project_id, $record, $event_id, $instrument, $instance);
		$preview = "</table><br><table class='table' style='table-layout: fixed;'><tr><th>{$lang['alerts_335']}</th><th>{$lang['alerts_333']}</th><th>{$lang['data_import_tool_99']}</th></tr>";
		foreach ($dynamic_data as $key => $value) {
			$preview .= "<tr><td></td><td style='overflow-wrap: break-word;'>$key</td><td style='overflow-wrap: break-word;'>$value</td></tr>";
		}
		$preview .= "</table>";
		$email_log_message = array(
			'template_data'=>$dynamic_data,
			'mail_send_configuration'=>$mail_send_configuration
		);
		$message->logEmailContent($from_email, $to, json_encode($email_log_message), 'SENDGRID_TEMPLATE', $emailCategory, $project_id, $record,
			$cc, $bcc, $dynamic_template_id, $preview, $attachments, false, $event_id, $instrument, $instance, $lang_id);

		$success = false;
		try {
			$response = $sg->client->mail()->send()->post($mail);
			if (in_array($response->statusCode(), [200, 201, 202])) {
				$success = true;
				$message->logSuccessfulSend('sendgrid');
			} else {
				REDCap::logEvent("SendGrid Email Failed with status: " . $response->statusCode(), $response->body(), "", $record, $event_id, $project_id);
			}
		} catch (Exception $e) {
			REDCap::logEvent("SendGrid Email Failed", $e->getMessage(), "", $record, $event_id, $project_id);
		}

		return $success;
	}

	public static function getVerifiedSenders($api_key, $project_id = null) {
		$sg = new \SendGrid($api_key);
		try {
			$response = $sg->client->verified_senders()->get();
			$body = json_decode($response->body(), TRUE);
			if ($response->statusCode() != 200) {
				REDCap::logEvent("Failed to get Verified SendGrid Senders with status: " . $response->statusCode(), $response->body(), "", null, null, $project_id);
			}
		} catch (Exception $e) {
			REDCap::logEvent("Failed to get Verified SendGrid Senders", $e->getMessage(), "", null, null, $project_id);
		}
		return $body['results'];
	}

	public static function getAuthenticatedDomains($api_key, $project_id = null) {
		$sg = new \SendGrid($api_key);
		$query_params = json_decode('{"valid": "true"}');
		try {
			$response = $sg->client->whitelabel()->domains()->get(null, $query_params);
			$domains = json_decode($response->body(), TRUE);
			if ($response->statusCode() != 200) {
				REDCap::logEvent("Failed to get Authenticated Domains with status: " . $response->statusCode(), $response->body(), "", null, null, $project_id);
			}
		} catch (Exception $e) {
			REDCap::logEvent("Failed to get Authenticated Domains", $e->getMessage(), "", null, null, $project_id);
		}
		return $domains;
	}

	public static function getDynamicTemplates($api_key, $project_id = null) {
		$sg = new \SendGrid($api_key);

		// looks like we need to use guzzle client to traverse next pages
		$client = new GuzzleHttp\Client([
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key
			]
		]);

		$query_params = json_decode('{
			"generations": "dynamic",
			"page_size": 18
		}');

		$templates = array();
		try {
			$response = $sg->client->templates()->get(null, $query_params);
			$body = json_decode($response->body(), TRUE);
			$templates = array_merge($templates, $body['result']);

			$response_metdata = $body['_metadata'];
			while (array_key_exists('next', $response_metdata)) {
				try {
					$uri = $response_metdata['next'];
					$response = $client->request('GET', $uri);
					$body = json_decode($response->getBody(), TRUE);
					$templates = array_merge($templates, $body['result']);
					$response_metdata = $body['_metadata'];
				} catch (Exception $e) {
					$response_metdata = array();
					REDCap::logEvent("Failed to get next page of SendGrid Dynamic Templates", $e->getMessage(), "", null, null, $project_id);
				}
			}
		} catch (Exception $e) {
			REDCap::logEvent("Failed to get SendGrid Dynamic Templates", $e->getMessage(), "", null, null, $project_id);
		}
		return $templates;
	}

	public static function verifyFromEmail($api_key, $from_email, $project_id=null) {
		if (filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
			$authenticated_domains = SendGridRC::getAuthenticatedDomains($api_key, $project_id);
			$verified_senders = SendGridRC::getVerifiedSenders($api_key, $project_id);
			$verified_sender_emails = array();
			foreach ($verified_senders as $sender) {
				array_push($verified_sender_emails, $sender['from_email']);
			}
			$allowed_domains = array();
			foreach ($authenticated_domains as $domain) {
				array_push($allowed_domains, $domain['domain']);
			}

			if (in_array($from_email, $verified_sender_emails)) {
				return true;
			}
			$from_email_domain = explode('@', $from_email)[1];
			if (in_array($from_email_domain, $allowed_domains)) {
				return true;
			}
		}
		return false;
	}

	public static function verifyTemplateId($api_key, $template_id, $project_id = null) {
		$templates = SendGridRC::getDynamicTemplates($api_key, $project_id);
		$valid_template_ids = array();
		foreach ($templates as $template) {
			array_push($valid_template_ids, $template['id']);
		}
		if (in_array($template_id, $valid_template_ids)) {
			return true;
		}
		return false;
	}

	public static function getUnsubscribeGroups($api_key, $project_id = null) {
		$sg = new \SendGrid($api_key);
		$groups = array();
		try {
			$response = $sg->client->asm()->groups()->get();
			$groups = json_decode($response->body(), TRUE);
			if ($response->statusCode() != 200) {
				REDCap::logEvent("Failed to get Unsubscribe Groups with status: " . $response->statusCode(), $response->body(), "", null, null, $project_id);
			}
		} catch (Exception $e) {
			REDCap::logEvent("Failed to get Unsubscribe Groups", $e->getMessage(), "", null, null, $project_id);
		}
		return $groups;
	}
}