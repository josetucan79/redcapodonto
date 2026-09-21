<?php

class Mosio
{
    // API endpoints and other attributes
    const API_BASE = "https://api.mosio.com/api/";  // DEV: https://devredcap.mosio.com/api/aaaa-bbbb-cccc-dddd
    const API_VERIFY_ENDPOINT = "redcap_verification";
    const API_SEND_SMS_ENDPOINT = "redcap_send_sms";
    const API_PING_ENDPOINT = "ping";

	// Project-level components
	private $pid;
	private $apiKey;

	public function __construct($project_id)
	{
		if (!isinteger($project_id)) throw new Exception("No project_id provided", 1);
		$this->pid = $project_id;
		$this->setApiKey();
	}

    // Get a project's API key
    private function setApiKey()
    {
		$this->apiKey = db_result(db_query("select mosio_api_key from redcap_projects where project_id = ".$this->pid), 0);
	}

    // Verify the Mosio API key that is provided (typically via incoming SMS)
    public function verifyApiKey($apiKey)
    {
		return ($this->apiKey == $apiKey);
	}

    // Verifies a user's Mosio account and also sets the REDCap URL, ProjectId, and ProjectName on the Mosio side.
	// Returns array of [boolean success, error/info message]
    public function verifyAccount()
    {
		if ($this->apiKey == null) return [false, "Mosio API key is missing"];
        // Get project name
        $projectName = strip_tags(label_decode(db_result(db_query("select app_title from redcap_projects where project_id = ".$this->pid), 0)));
        // Call the API
		$params = ['RedcapUrl'=>APP_PATH_SURVEY_FULL."index.php?pid=".$this->pid, 'ProjectId'=>$this->pid, 'ProjectName'=>$projectName];
        $response = http_post(Mosio::API_BASE . Mosio::API_VERIFY_ENDPOINT, $params, null, 'application/json', $this->apiKey.":");
        if ($response === false) {
            return [false, "Mosio API key is not valid"];
        } else {
            try {
                $respArr = json_decode($response, true);
            } catch (Throwable $e) {
                return [false, "Mosio response not valid: $response"];
            }
        }
		// Return true if success=1 and no error message was returned
		if (!is_array($respArr)) {
			return [false, "Mosio response not valid: $response"];
		} elseif (!(isset($respArr['Success']) && $respArr['Success'] == '1' && $respArr['ErrorMessage'] == '')) {
			return [false, "Mosio error: ".$respArr['ErrorMessage']];
		} else {
			// Success!
			return [true, $respArr['AccountStatus']??""];
		}
    }

	// Send the SMS to recipient
    public function sendSMS($text, $number_to_sms, $record=null, $category=null, $addToEmailLog=false)
    {
		// Clean inputs
		$text = Messaging::cleanSmsText($text);
		$number_to_sms = formatPhone(Messaging::formatNumber($number_to_sms));
        // SMS should not be over 1600 characters. If so, then break up into multiple SMS messages.
        $strings = explode("|--RCBREAK--|", wordwrap($text, 1590, "|--RCBREAK--|"));
        $stringCount = count($strings);
        foreach ($strings as $key=>$str) { if ($key < $stringCount-1) $strings[$key] = trim($str)."..."; } // append an ellipsis to each except the last
        // Loop through each SMS
        foreach ($strings as $thistext) {
            // Set params
            $params = ['RedcapUrl'=>APP_PATH_SURVEY_FULL."index.php?pid=".$this->pid, 'ProjectId'=>$this->pid, 'Phone'=>$number_to_sms, 'Body'=>$thistext];
            if ($record != null) $params['RecordId'] = $record;
            // Call the API
            $response = http_post(Mosio::API_BASE . Mosio::API_SEND_SMS_ENDPOINT, $params, null, 'application/json', $this->apiKey.":");
            // Parse response
            try {
                $respArr = json_decode($response, true);
            } catch (Throwable $e) {
                return "Could not parse response from Mosio";
            }
            $success = (is_array($respArr) && isset($respArr['Success']) && $respArr['Success'] == '1' && $respArr['ErrorMessage'] == '');
            // Return error message if failed
            if (!$success) return $respArr['ErrorMessage'];
        }
        // Log it in table
        $message_sms = new Message($this->pid);
        $message_sms->logSuccessfulSend('mosio_sms');
        if ($addToEmailLog) {
            $message_sms->logEmailContent("", $number_to_sms, $text, 'SMS', $category, $this->pid, $record);
        }
		// Return true if success=1 and no error message was returned
		return true;
    }

    // Validate Mosio Settings
    public function validateSetup($postArr) {
        $twilio_enabled = $postArr['twilio_enabled'];
        $mosio_api_key = $postArr['mosio_api_key'];
        // Error msg
        $error_msg = "";
        // Make sure another project does not have this same API key
        $sql = "select 1 from redcap_projects where project_id != $this->pid and mosio_api_key = ".checkNull($mosio_api_key)." limit 1";

        // Check for projects where users have sent mosio details for approvals and request still pending
        $sql2 = "select 1 from redcap_twilio_credentials_temp where project_id != $this->pid and mosio_api_key = ".checkNull($mosio_api_key)." limit 1";

        if (db_num_rows(db_query($sql)) || db_num_rows(db_query($sql2))) {
            // Display error message
            $error_msg = RCView::tt('survey_1561');
        } else {
            ## MOSIO CHECK: Check connection to Mosio and also set the voice/sms URLs to the REDCap survey URL, if not set yet
            if ($twilio_enabled) {
                list ($success, $errors) = $this->verifyAccount();
                if (!$success) {
                    $error_msg = $errors;
                }
            }
        }

        return $error_msg;
    }
}
