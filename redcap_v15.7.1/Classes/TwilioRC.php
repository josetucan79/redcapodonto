<?php

/**
 * TwilioRC
 * This class is used for processes related to Voice Calling & SMS via Twilio.com's REST API
 */
class TwilioRC
{
	// Set max length for any SMS. Real limit is 160, but need some buffer room to add "(1/total) " at beginning and ellipses at end
	const MAX_SMS_LENGTH = 150;

	// Pressing this button on a voice call skips the current question (saves value as blank) - if skippable
	const VOICE_SKIP_DIGIT = "*";
	
	// NUMBER OF INVALID RESPONSES AND PERIOD
    const INVALID_RESPONSE_WINDOW = 60;
    const INVALID_RESPONSE_THRESHOLD = 10;

	// Initialize Twilio classes and settings
	public static function init()
	{
		// If init has already been called once in this script, then nothing to do here
		if (class_exists('Services_Twilio')) return;
		// Call Twilio classes
		if (require_once(dirname(dirname(__FILE__)) . "/Libraries/Twilio/Services/Twilio.php")) {
			// Reset the class autoload function because Twilio's classes changed it
			spl_autoload_register($GLOBALS['rc_autoload_function']);
		}
	}
	
	
	// DETERMINE IF THE NUMBER OF INVALID RESPONSES IN THE INVALID_RESPONSE_WINDOW EXCEEDS THE INVALID_RESPONSE_THRESHOLD
    public static function preventInvalidResponseLoop($project_id, $record, $event_id, $field, $response) 
	{
        // Get number of invalid responses in window
       $count = self::getRecentInvalidResponsesCount($project_id, $record, $event_id, $field);
 
        // Log the new invalid response
        REDCap::logEvent("Invalid SMS Response", "'$response' is not valid for field '$field' (attempt $count of " . self::INVALID_RESPONSE_THRESHOLD . ")", "", $record, $event_id, $project_id);
 
       if ($count >= self::INVALID_RESPONSE_THRESHOLD) {
            REDCap::logEvent("Invalid SMS Response Loop Detected - Aborting", "", "", $record, $event_id, $project_id);
            exit();
       }
    }
 
 
    // QUERY FOR NUMBER OF RECENT INVALID RESPONSES
    private static function getRecentInvalidResponsesCount($project_id, $record, $event_id, $field) 
	{
        $ts_min = date('YmdHis') - self::INVALID_RESPONSE_WINDOW;
		$sql = "select 1 from ".Logging::getLogEventTable($project_id)." l where
				l.project_id = '" . db_escape($project_id) . "'
				and l.pk = '".db_escape($record)."'
				and l.event = 'OTHER'
				and l.description = 'Invalid SMS Response'
				and l.ts > ".intval($ts_min)."
				and l.event_id = $event_id
				and l.data_values like '%".db_escape("field '$field'")."%'";
       $q = db_query($sql);
       return (db_num_rows($q)+1);
    }


	// Get array of all voices (genders) available in Twilio voice calling service
	public static function getAllVoices()
	{
		return array('man', 'woman', 'alice');
	}


	// Get array of all languages available in Twilio voice calling service
	public static function getAllLanguages()
	{
		return array(
					// Male/Female only
					'en'=>'English, United States',
					'en-gb'=>'English, UK',
					'es'=>'Spanish, Spain',
					'fr'=>'French, France',
					'de'=>'German, Germany',
					'it'=>'Italian, Italy',
					// Alice only
					'da-DK'=>'Danish, Denmark',
					'de-DE'=>'German, Germany',
					'en-AU'=>'English, Australia',
					'en-CA'=>'English, Canada',
					'en-GB'=>'English, UK',
					'en-IN'=>'English, India',
					'en-US'=>'English, United States',
					'ca-ES'=>'Catalan, Spain',
					'es-ES'=>'Spanish, Spain',
					'es-MX'=>'Spanish, Mexico',
					'fi-FI'=>'Finnish, Finland',
					'fr-CA'=>'French, Canada',
					'fr-FR'=>'French, France',
					'it-IT'=>'Italian, Italy',
					'ja-JP'=>'Japanese, Japan',
					'ko-KR'=>'Korean, Korea',
					'nb-NO'=>'Norwegian, Norway',
					'nl-NL'=>'Dutch, Netherlands',
					'pl-PL'=>'Polish-Poland',
					'pt-BR'=>'Portuguese, Brazil',
					'pt-PT'=>'Portuguese, Portugal',
					'ru-RU'=>'Russian, Russia',
					'sv-SE'=>'Swedish, Sweden',
					'zh-CN'=>'Chinese (Mandarin)',
					'zh-HK'=>'Chinese (Cantonese)',
					'zh-TW'=>'Chinese (Taiwanese Mandarin)'
			);
	}


	// Get array of all Twilio languages spoken only by the 'man' voice (the rest will be spoken by 'alice')
	public static function getManOnlyLanguages()
	{
		return array('en', 'en-gb', 'es', 'fr', 'de', 'it');
	}


	// Return dropdown options (as array) of all languages available in Twilio voice calling service
	public static function getDropdownAllLanguages()
	{
		global $lang;
		// Get all languages available
		$allLang = self::getAllLanguages();
		// Get all voices available
		$allVoices = self::getAllVoices();
		// Get all 'man'-only languages (the rest will be spoken by 'alice')
		$manLang = self::getManOnlyLanguages();
		// Build an array of drop-down options listing all voices/languages
		$options = array();
		foreach ($allLang as $this_lang=>$this_label) {
			// Is alice voice?
			$isAlice = (!in_array($this_lang, $manLang));
			// Get group name
			$this_group_label = $isAlice ? $lang['survey_723'] : $lang['survey_724'];
			// Add to array
			$options[$this_group_label][$this_lang] = "$this_label ($this_group_label)";
		}
		// Return array of options
		return $options;
	}


	// Initialize Twilio client object

	public static function client()
	{
		// Get global variables, or if don't have them, set locals from above as globals
		global $twilio_account_sid, $twilio_auth_token, $twilio_from_number;
		// If not in a project (e.g. entering Survey Access Code) and Twilio is posting,
		// then use the AccountSid passed to retrive the Twilio auth token from the redcap_projects table.
		if (!defined("PROJECT_ID") && isset($_SERVER['HTTP_X_TWILIO_SIGNATURE']) && isset($_POST['AccountSid'])) {
			$twilio_account_sid = $_POST['AccountSid'];
			list ($twilio_auth_token, $twilio_from_number) = self::getTokenByAcctSid($twilio_account_sid);
		}
		// Instantiate a new Twilio Rest Client
		return new Services_Twilio($twilio_account_sid, $twilio_auth_token);
	}


	// Retrive the Twilio auth token from the redcap_projects table using the Twilio account SID
	public static function getTokenByAcctSid($twilio_account_sid)
	{
		$sql = "select twilio_auth_token, twilio_from_number from redcap_projects
				where twilio_account_sid = '".db_escape($twilio_account_sid)."' limit 1";
		$q = db_query($sql);
		$twilio_auth_token  = db_result($q, 0, 'twilio_auth_token');
		$twilio_from_number = db_result($q, 0, 'twilio_from_number');
		return array($twilio_auth_token, $twilio_from_number);
	}


	// Obtain the voice call "language" setting for the project
	public static function getLanguage()
	{
		global $twilio_voice_language;
		// Get all languages available
		$allLang = self::getAllLanguages();
		// Return abbreviation for language that is set for this request
		return ($twilio_voice_language == null || ($twilio_voice_language != null && !isset($allLang[$twilio_voice_language])))
				? 'en' : $twilio_voice_language;
	}


	// Obtain the gender of the "voice" for the voice call based upon the language selected.
	// Note: Use "man" for en, en-gb, es, fr, de, and it, but for all other languages, use "alice".
	public static function getVoiceGender()
	{
		// Get current language selected
		$current_language = self::getLanguage();
		// Get all 'man'-only languages (the rest will be spoken by 'alice')
		$manLang = self::getManOnlyLanguages();
		// Is alice voice?
		return (in_array($current_language, $manLang)) ? 'man' : 'alice';
	}


	// Add random hash to table for erasing a Twilio call/message call once they have completed
	public static function addEraseCall($project_id, $sid, $sid_hash='', $account_sid=null)
	{
		// If project_id is missing, but we have the $account_sid, then determine project_id based upon $account_sid.
		$project_ids = [];
		if (!is_numeric($project_id) && $account_sid != null) {
			// If we have multiple projects with the same account_sid, just loop through all of them to ensure we get them all
			$sql = "select project_id from redcap_projects where twilio_enabled = 1 
					and twilio_account_sid = '".db_escape($account_sid)."'";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$project_ids[] = $row['project_id'];
			}
		} else {
			$project_ids[] = $project_id;
		}
		// Loop through all possible projects that have this account_sid
		foreach ($project_ids as $this_project_id) {
			// Add to table
			$sql = "insert into redcap_surveys_erase_twilio_log (project_id, ts, sid, sid_hash) values
					(" . checkNull($this_project_id) . ", '" . NOW . "', '" . db_escape($sid) . "', " . checkNull($sid_hash) . ")";
			db_query($sql);
		}
		return true;
	}


	// Use an sid hash that will be passed by Twilio for its callback when a request ends in order to then
	// call Twilio to delete the log of the Twilio event. This cleans up the Twilio log right after the event.
	// Returns the original SID of the event.
	public static function eraseCallLog($sid_hash)
	{
		$sql = "select tl_id, sid, project_id from redcap_surveys_erase_twilio_log where sid_hash = '".db_escape($sid_hash)."'";
		$q = db_query($sql);
		if (!db_num_rows($q)) return array(false, false);
		// Get sid
		$sid = db_result($q, 0, 'sid');
		$tl_id = db_result($q, 0, 'tl_id');
		$project_id = db_result($q, 0, 'project_id');
		// Delete row from table
		$sql = "delete from redcap_surveys_erase_twilio_log where tl_id = $tl_id";
		$q = db_query($sql);
		// Return the sid
		return array($project_id, $sid);
	}


	// Delete the Twilio back-end and front-end log of a given SMS (will try every second for up to 30 seconds)
	public static function deleteLogForSMS($sid, $twilioClient)
	{
		// Delete the log of this SMS (try every second for up to 30 seconds)
		for ($i = 0; $i < 30; $i++) {
			// Pause for 1 second to allow SMS to get delivered to carrier
			if ($i > 0) sleep(1);
			// Has it been delivered yet? If not, wait another second.
			$log = $twilioClient->account->sms_messages->get($sid);
			if ($log->status != 'delivered') continue;
			// Yes, it was delivered, so delete the log of it being sent.
			$twilioClient->account->messages->delete($sid);
			return true;
		}
		// Failed
		return false;
	}


	// Delete the Twilio back-end and front-end log of a given call (will try every second for up to 30 seconds)
	public static function deleteLogForCall($sid, $twilioClient)
	{
		// Delete the log of this SMS (try every second for up to 30 seconds)
		for ($i = 0; $i < 30; $i++) {
			// Pause for 1 second to allow SMS to get delivered to carrier
			if ($i > 0) sleep(1);
			// Has it been delivered yet? If not, wait another second.
			$log = $twilioClient->account->calls->get($sid);
			if ($log->status != 'completed') continue;
			// Yes, it was delivered, so delete the log of it being sent.
			$twilioClient->account->calls->delete($sid);
			return true;
		}
		// Failed
		return false;
	}

	// Function to send SMS message. Segments by 160 characters per SMS, if body is longer.
	public static function sendSMS($text, $number_to_sms, $twilioClient, $twilio_from_number_alt=null, $deleteSmsFromLog=true, $project_id=null,
                                   $addToEmailLog=false, $record=null, $category=null, $twilio_alphanum_sender_id=null)
	{
		// Determine project_id
		$project_id = ($project_id !== null) ? $project_id : (defined("PROJECT_ID") ? PROJECT_ID : null);
		// Get the 'From' number
		if ($twilio_from_number_alt == null) {
			global $twilio_from_number;
		} else {
			$twilio_from_number = $twilio_from_number_alt;
		}
		// Clean string
		$text = Messaging::cleanSmsText($text);
		// If From and To number are the same, return an error
		if (str_replace(array(" ", "(", ")", "-"), array("", "", "", ""), $twilio_from_number) == str_replace(array(" ", "(", ")", "-"), array("", "", "", ""), $number_to_sms)) {
			return "ERROR: The From and To number cannot be the same ($number_to_sms).";
		}
        // Using normal phone number or Twilio's Alphanumeric Sender ID?
        if ($twilio_alphanum_sender_id != null) {
            $formatted_from_number = $twilio_from_number = $twilio_alphanum_sender_id;
        } else {
            $twilio_from_number = Messaging::formatNumber($twilio_from_number);
            $formatted_from_number = formatPhone($twilio_from_number);
        }
		// SMS should not be over 1600 characters. If so, then break up into multiple SMS messages.
		$strings = explode("|--RCBREAK--|", wordwrap($text, 1590, "|--RCBREAK--|"));
		$stringCount = count($strings);
		foreach ($strings as $key=>$str) { if ($key < $stringCount-1) $strings[$key] = trim($str)."..."; } // append an ellipsis to each except the last
		// Loop through each SMS
		foreach ($strings as $thistext) {
			try {
				// Send SMS
				$sms = $twilioClient->account->messages->sendMessage($twilio_from_number, Messaging::formatNumber($number_to_sms), $thistext);
				// Wait till the SMS sends completely and then remove it from the Twilio logs
				// Add the sid and sid_hash to the db table so that we can delete the log for this event once it has completed
				if ($deleteSmsFromLog && $project_id !== null) {
					TwilioRC::addEraseCall($project_id, $sms->sid, '', $sms->account_sid);
				}
			} catch (Exception $e) {
				// On failure, return error message
                if (isDev()) exit($e->getMessage());
				return $e->getMessage();
			}
			// Log it in table
			$message_sms = new Message($project_id);
			$message_sms->logSuccessfulSend('twilio_sms');
			if ($addToEmailLog) {
				$message_sms->logEmailContent($formatted_from_number, formatPhone(Messaging::formatNumber($number_to_sms)), $text, 'SMS', $category, $project_id, $record);
			}
		}
		// Successful, so return true
		return true;
	}


	// Clean and format the phone numbers used to query the redcap_surveys_phone_codes table
	public static function formatSmsAccessCodePhoneNumbers($participant_phone, $twilio_phone='')
	{
		// Remove all non-numeral characters
		$participant_phone = preg_replace('/[^0-9]+/', '', $participant_phone);
		$twilio_phone = preg_replace('/[^0-9]+/', '', $twilio_phone);
		// Remove "1" as U.S. prefix
		if (strlen($participant_phone) == 11 && substr($participant_phone, 0, 1) == '1') {
			$participant_phone = substr($participant_phone, 1);
		}
		if (strlen($twilio_phone) == 11 && substr($twilio_phone, 0, 1) == '1') {
			$twilio_phone = substr($twilio_phone, 1);
		}
		// Return numbers
		return array($participant_phone, $twilio_phone);
	}


	// Obtain the project_id using the Twilio Phone number associated with the project
	public static function getProjectIdFromTwilioPhoneNumber($twilio_phone)
	{
		// Remove non-numerals
		$twilio_phone = preg_replace("/[^0-9]/", "", $twilio_phone);
		// Return null if either numbers are blank
		if ($twilio_phone == '') return null;
		// Check if in table
		$sql = "select project_id from redcap_projects where twilio_from_number = '".db_escape($twilio_phone)."'";
		// Remove "1" as U.S. prefix as alternate
		if (strlen($twilio_phone) == 11 && substr($twilio_phone, 0, 1) == '1') {
			$sql .= " or twilio_from_number = '".db_escape(substr($twilio_phone, 1))."'";
		}
		$q = db_query($sql);
		$num_rows = db_num_rows($q);
		if ($num_rows == 0) return null;
		return db_result($q, 0);
	}


	// Obtain the Survey Access Code for a given phone number from the redcap_surveys_phone_codes table
	public static function getSmsAccessCodeFromPhoneNumber($participant_phone, $twilio_phone)
	{
		// Clean and format the phone numbers used to query the redcap_surveys_phone_codes table
		list ($participant_phone, $twilio_phone) = self::formatSmsAccessCodePhoneNumbers($participant_phone, $twilio_phone);
		// Return null if either numbers are blank
		if ($participant_phone == '' || $twilio_phone == '') return null;
		// Check if in table
		$sql = "select c.access_code, p.twilio_multiple_sms_behavior
				from redcap_surveys_phone_codes c, redcap_projects p, redcap_surveys_participants a
				left join redcap_surveys_response r on a.participant_id = r.participant_id
				where c.phone_number = '".db_escape($participant_phone)."' and c.twilio_number = '".db_escape($twilio_phone)."'
				and c.project_id = p.project_id and a.access_code_numeral = replace(c.access_code, 'V', '') and a.participant_email is not null
				and r.completion_time is null order by c.pc_id desc";
		$q = db_query($sql);		
		$num_rows = db_num_rows($q);
		// If failed to find the access code, then check public survey link
		if ($num_rows == 0) {
			$sql = "select distinct c.access_code, p.twilio_multiple_sms_behavior
					from redcap_surveys_phone_codes c, redcap_projects p, redcap_surveys_participants a
					where c.phone_number = '".db_escape($participant_phone)."' and c.twilio_number = '".db_escape($twilio_phone)."'
					and c.project_id = p.project_id and a.access_code_numeral = replace(c.access_code, 'V', '') 
					and a.participant_email is null order by c.pc_id desc";
			$q = db_query($sql);		
			$num_rows = db_num_rows($q);
		}
		// Return access code
		if ($num_rows > 0) {
			// Return code
			$access_codes = array();
			while ($row = db_fetch_assoc($q)) {
				// Set this loop's values
				$sms_behavior = $row['twilio_multiple_sms_behavior'];
				$access_code = $row['access_code'];
				// RETURN LAST INVITE'S ACCESS CODE or the ONLY ACCESS CODE: If this project is set to return only the LAST SMS invite sent, then ignore the rest
				if ($num_rows == 1 || $sms_behavior == 'OVERWRITE') {
					// Return access code
					return $access_code;
				}
				// RETURN ALL INVITES with their access codes as an array
				else {
					$access_codes[] = $access_code;
				}
			}
			// If only returning the FIRST invitation's access code (chronologically speaking), then return here (will be last here because we're doing DESC order)
			if ($sms_behavior == 'FIRST') {
				return $access_code;
			}
			// Since we're returning all access codes available, return them as an array
			return $access_codes;
		} else {
			// Return null since the number has no stored code
			return null;
		}
	}


    // Obtain the current participant's session_id for a given PID from the redcap_surveys_phone_codes table
    public static function getSmsSessionIdFromPhoneAndPid($participant_phone, $project_id, $access_code=null)
    {
        list ($participant_phone, $nothing) = TwilioRC::formatSmsAccessCodePhoneNumbers($participant_phone);
        // Check if in table
        $sql = "select s.session_id from redcap_surveys_phone_codes c, redcap_sessions s
				where c.phone_number = '" . db_escape($participant_phone) . "' and c.project_id = '" . db_escape($project_id) . "'";
		if ($access_code !== null) $sql .= " and c.access_code = '" . db_escape($access_code) . "'";
		$sql .= " and c.session_id = s.session_id and s.session_expiration > '" . NOW . "'
                  order by s.session_id desc limit 1";
        $q = db_query($sql);
        return db_num_rows($q) ? db_result($q, 0) : null;
    }


    // Set the current participant's session_id (if null or expired) for a given PID from the redcap_surveys_phone_codes table.
    // Return current session id stored for the participant.
    public static function setSmsSessionIdFromPhoneAndPid($participant_phone, $project_id, $access_code)
    {
        // Not needed for Twilio
        if (Messaging::getIncomingRequestType() == Messaging::PROVIDER_TWILIO) return;
        // Check if currently has valid session ID in table
        $smsSessionId = self::getSmsSessionIdFromPhoneAndPid($participant_phone, $project_id, $access_code);
        if ($smsSessionId === null && Session::sessionId() != false) {
            list ($participant_phone, $nothing) = TwilioRC::formatSmsAccessCodePhoneNumbers($participant_phone);
            $sql = "replace into redcap_surveys_phone_codes (phone_number, access_code, project_id, session_id) 
                    values ('" . db_escape($participant_phone) . "', '" . db_escape($access_code) . "', '" . db_escape($project_id) . "', '" . db_escape(Session::sessionId()) . "')";
            if (db_query($sql)) {
                $smsSessionId = Session::sessionId();
            }
        }
        // Return current session id stored
        return $smsSessionId;
    }

	// Obtain the Survey Access Code for a given PID from the redcap_surveys_phone_codes table
	public static function getSmsAccessCodeFromPhoneAndPid($participant_phone, $project_id)
	{
        list ($participant_phone, $nothing) = TwilioRC::formatSmsAccessCodePhoneNumbers($participant_phone, $twilio_phone);
		// Check if in table
		$sql = "select c.access_code, p.twilio_multiple_sms_behavior
				from redcap_surveys_phone_codes c, redcap_projects p, redcap_surveys_participants a
				left join redcap_surveys_response r on a.participant_id = r.participant_id
				where c.phone_number = '".db_escape($participant_phone)."' and c.project_id = '" . db_escape($project_id) . "'
				and c.project_id = p.project_id and a.access_code_numeral = replace(c.access_code, 'V', '') and a.participant_email is not null
				and r.completion_time is null order by c.pc_id desc";
		$q = db_query($sql);
		$num_rows = db_num_rows($q);
		// If failed to find the access code, then check public survey link
		if ($num_rows == 0) {
			$sql = "select distinct c.access_code, p.twilio_multiple_sms_behavior
					from redcap_surveys_phone_codes c, redcap_projects p, redcap_surveys_participants a
					where c.phone_number = '".db_escape($participant_phone)."' and c.project_id = '" . db_escape($project_id) . "'
					and c.project_id = p.project_id and a.access_code_numeral = replace(c.access_code, 'V', '') 
					and a.participant_email is null order by c.pc_id desc";
			$q = db_query($sql);
			$num_rows = db_num_rows($q);
		}
		// Return access code
		if ($num_rows > 0) {
			// Return code
			$access_codes = array();
			while ($row = db_fetch_assoc($q)) {
				// Set this loop's values
				$sms_behavior = $row['twilio_multiple_sms_behavior'];
				$access_code = $row['access_code'];
				// RETURN LAST INVITE'S ACCESS CODE or the ONLY ACCESS CODE: If this project is set to return only the LAST SMS invite sent, then ignore the rest
				if ($num_rows == 1 || $sms_behavior == 'OVERWRITE') {
					// Return access code
					return $access_code;
				}
				// RETURN ALL INVITES with their access codes as an array
				else {
					$access_codes[] = $access_code;
				}
			}
			// If only returning the FIRST invitation's access code (chronologically speaking), then return here (will be last here because we're doing DESC order)
			if ($sms_behavior == 'FIRST') {
				return $access_code;
			}
			// Since we're returning all access codes available, return them as an array
			return $access_codes;
		} else {
			// Return null since the number has no stored code
			return null;
		}
	}


	// Delete the Survey Access Code for a given phone number from the redcap_surveys_phone_codes table
	public static function deleteSmsAccessCodeFromPhoneNumber($participant_phone, $twilio_phone, $access_code=null)
	{
		// Clean and format the phone numbers used to query the redcap_surveys_phone_codes table
		list ($participant_phone, $twilio_phone) = self::formatSmsAccessCodePhoneNumbers($participant_phone, $twilio_phone);
		// Return null if either numbers are blank
		if ($participant_phone == '') return false;
		// Now delete the code from the table since we no longer need it
		$sql = "delete from redcap_surveys_phone_codes where phone_number = '".db_escape($participant_phone)."'
				and twilio_number = '".db_escape($twilio_phone)."'";
		if ($access_code != null) {
			$sql .= " and access_code = '".db_escape($access_code)."'";
		}
		return db_query($sql);
	}


	// Add Survey Access Code for a given phone number to the redcap_surveys_phone_codes table
	public static function addSmsAccessCodeForPhoneNumber($participant_phone, $twilio_phone, $access_code, $project_id='')
	{
		// Remove all non-numeral characters
		$participant_phone = preg_replace('/[^0-9]+/', '', $participant_phone);
		if ($participant_phone == '') return null;
		$twilio_phone = preg_replace('/[^0-9]+/', '', $twilio_phone);
		// Remove "1" as U.S. prefix
		if (strlen($participant_phone) == 11 && substr($participant_phone, 0, 1) == '1') {
			$participant_phone = substr($participant_phone, 1);
		}
		if (strlen($twilio_phone) == 11 && substr($twilio_phone, 0, 1) == '1') {
			$twilio_phone = substr($twilio_phone, 1);
		}
		// Add to table (update table if phone number already exists for this survey)
		$sql = "insert into redcap_surveys_phone_codes (phone_number, twilio_number, access_code, project_id)
				values ('".db_escape($participant_phone)."', '".db_escape($twilio_phone)."', '".db_escape($access_code)."', ".checkNull($project_id).")";
		// Return true on success or false on fail
		return db_query($sql);
	}


	// Obtain the project_id of a project from one or more number survey access codes
	public static function getProjectIdFromNumericAccessCode($codes)
	{
		// If not an array, then convert to array
		if (!is_array($codes)) $codes = array($codes);
		// Remove the "V" at beginning of any codes because we're checking on access_code_numeral only
		foreach ($codes as &$code) {
			$code = str_replace("V", "", $code);
		}
		// Get project_id
		$sql = "select s.project_id from redcap_surveys_participants p, redcap_surveys s
				where p.access_code_numeral in (".prep_implode($codes).") and s.survey_id = p.survey_id limit 1";
		$q = db_query($sql);
		return db_result($q, 0);
	}


	// Obtain an array of survey access codes (as keys) and survey titles (values) from one or more number survey access codes
	public static function getSurveyTitlesFromNumericAccessCode($codes, $truncateSurveyTitle=true)
	{
		// If not an array, then convert to array
		if (!is_array($codes)) $codes = array($codes);
		// Remove the "V" at beginning of any codes because we're checking on access_code_numeral only
		$codes_sql = $codes_orig = array();
		foreach ($codes as $code) {
			$code_numeric = str_replace("V", "", $code);
			$codes_sql[] = $code_numeric;
			$codes_orig[$code_numeric] = $code;
		}
		// Get titles
		$sql = "select s.title, p.access_code_numeral
				from redcap_surveys_participants p, redcap_surveys s, redcap_surveys_phone_codes c
				where p.access_code_numeral in (".prep_implode($codes_sql).") and s.survey_id = p.survey_id
				and p.access_code_numeral = replace(c.access_code, 'V', '') order by c.pc_id";
		$q = db_query($sql);
		$phone_codes_titles = array();
		while ($srow = db_fetch_assoc($q)) {
			// Limit title to 20 chars (replacing middle chars with ellipsis)
			$srow['title'] = strip_tags(label_decode($srow['title']));
			if ($truncateSurveyTitle && mb_strlen($srow['title']) > 20) {
				$srow['title'] = mb_substr($srow['title'], 0, 12) . "..." . mb_substr($srow['title'], -6);
			}
			// Add to array
			$phone_codes_titles[$codes_orig[$srow['access_code_numeral']]] = $srow['title'];
		}
		return $phone_codes_titles;
	}


	// Determine if a field's multiple choice options all have numerical coded values
	// $enum is provided as the element_enum string
	public static function allChoicesNumerical($enum)
	{
		foreach (parseEnum($enum) as $this_code=>$this_label) {
			if (!is_numeric($this_code)) return false;
		}
		return true;
	}

	// Determine if a field's usage in a SMS or voice call survey is viable for those mediums.
	// Provide $field_type of the field and $type as "SMS" or "VOICE", as well as its validation type $val_type, if applicable.
	// Return FALSE if the field is not viable for the given medium, which means that the field will be skipped in the survey.
	public static function fieldUsageIVR($type, $field_name)
	{
		// Get globals
		global $Proj, $lang;

		// Get all validation types
		$all_val_types = getValTypes();

		// Get field attributes
		$field_type = $Proj->metadata[$field_name]['element_type'];
		$choices = $Proj->metadata[$field_name]['element_enum'];
		$val_type = convertLegacyValidationType($Proj->metadata[$field_name]['element_validation_type']);
		$data_type = ($field_type == 'text' && $val_type != '') ? $all_val_types[$val_type]['data_type'] : '';
		
		// Inform user that action tags don't work in Twilio
		// If field_annotation has @, then assume it might be an action tag
		$tagWarning = "";
		$tagFound = false;
		if (strpos($Proj->metadata[$field_name]['misc'], '@') !== false) {
			// Match triggers via regex
			$action_tags_regex = Form::getActionTagMatchRegex();
			preg_match_all($action_tags_regex, $Proj->metadata[$field_name]['misc'], $this_misc_match);
			if (isset($this_misc_match[1]) && !empty($this_misc_match[1])) {
				$tagWarning = " " . $lang['survey_1153'] . " " . implode(" ", $this_misc_match[1]) . $lang['period'];
				$tagFound = true;
			}
		}

		## SMS
		if ($type == "SMS") {
			if ($field_type == 'text') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'textarea') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'calc') {
				return $lang['survey_886'] . $tagWarning;
			} elseif ($field_type == 'select') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'radio') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'yesno') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'truefalse') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'checkbox') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'file') {
				return $lang['survey_888'] . $tagWarning;
			} elseif ($field_type == 'slider') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'descriptive') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'sql') {
				return ($tagFound ? $tagWarning : true);
			} else {
				return $lang['survey_887'] . $tagWarning;
			}
		}

		## VOICE CALL
		else {
			if ($field_type == 'text') {
				// Only allow number or integer data types
				return ($data_type == 'integer' || $data_type == 'number' ? ($tagFound ? $tagWarning : true) : $lang['survey_889'] . $tagWarning);
			} elseif ($field_type == 'textarea') {
				return $lang['survey_892'] . $tagWarning;
			} elseif ($field_type == 'calc') {
				return $lang['survey_886'] . $tagWarning;
			} elseif ($field_type == 'select') {
				return (self::allChoicesNumerical($choices) ? ($tagFound ? $tagWarning : true) : $lang['survey_890'] . $tagWarning);
			} elseif ($field_type == 'radio') {
				return (self::allChoicesNumerical($choices) ? ($tagFound ? $tagWarning : true) : $lang['survey_890'] . $tagWarning);
			} elseif ($field_type == 'yesno') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'truefalse') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'checkbox') {
				return $lang['survey_891'] . $tagWarning;
			} elseif ($field_type == 'file') {
				return $lang['survey_888'] . $tagWarning;
			} elseif ($field_type == 'slider') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'descriptive') {
				return ($tagFound ? $tagWarning : true);
			} elseif ($field_type == 'sql') {
				return (self::allChoicesNumerical(getSqlFieldEnum($choices)) ? ($tagFound ? $tagWarning : true) : $lang['survey_890'] . $tagWarning);
			} else {
				return $lang['survey_887'] . $tagWarning;
			}
		}
	}


	// Take enum array and return int of the maximum number of numerical digits that a set of choices has
	public static function getChoicesMaxDigits($this_enum_array)
	{
		// Set defaults
		$num_digits = 1;
		// In not array, return default
		if (!is_array($this_enum_array)) return $num_digits;
		// Loop through choices
		foreach ($this_enum_array as $key=>$val) {
			// If not numeric, then skip
			if (!is_numeric($key)) continue;
			// If numeric, then count digits
			$num_digits = strlen($key."");
		}
		// Return count
		return $num_digits;
	}


	// Use $_SERVER['HTTP_X_TWILIO_SIGNATURE'] to validate that this request is truly coming from Twilio
	public static function verifyTwilioServerSignature($twilioAuthToken, $current_url)
	{
		require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
		// Initialize Twilio classes
		self::init();
		// Instatiate the validator
		$validator = new Services_Twilio_RequestValidator($twilioAuthToken);
		// Validate the signature
		return $validator->validate($_SERVER['HTTP_X_TWILIO_SIGNATURE'], $current_url, $_POST);
	}


	/**
	 * ERASE TWILIO CALL/SMS LOGS FROM THE TWILIO ACCOUNT
	 * Clear all items from redcap_surveys_erase_twilio_log table.
	 */
	public static function EraseTwilioWebsiteLog()
	{
		// See if any are in the table
		$sql = "select l.tl_id, l.project_id, l.sid, p.twilio_account_sid, p.twilio_auth_token, p.twilio_from_number
				from redcap_surveys_erase_twilio_log l, redcap_projects p
				where p.project_id = l.project_id order by l.ts desc";
		$q = db_query($sql);
		$rowsDeleted = 0;
		$phoneNumbers = array();
		if (db_num_rows($q) > 0) {
			// Loop through results
			while ($row = db_fetch_assoc($q)) {
				// Erase the log of this event (either SMS or call)
				try {
					// Set Twilio client
					$twilioClient = new Services_Twilio($row['twilio_account_sid'], $row['twilio_auth_token']);
					// Delete this SID
					if (substr($row['sid'], 0, 2) == 'SM') {
						$twilioClient->account->messages->delete($row['sid']);
					} else {
						$twilioClient->account->calls->delete($row['sid']);
					}
				} catch (Exception $e) { }
				// Add to $phoneNumbers					
				if (!isset($phoneNumbers[$row['project_id']])) {
					$phoneNumbers[$row['project_id']] = array('sid'=>$row['twilio_account_sid'], 'token'=>$row['twilio_auth_token'], 'phone'=>$row['twilio_from_number']);
				}
				// Delete row from table
				$sql = "delete from redcap_surveys_erase_twilio_log where tl_id = " . $row['tl_id'];
				db_query($sql);
				$rowsDeleted += db_affected_rows();
			}
			// Do extra cleanup of ALL the phone number's logs (for compliance purposes, just in case we've missed something)
			foreach ($phoneNumbers as $attr) {
				self::EraseAllTwilioWebsiteLog($attr['sid'], $attr['token'], $attr['phone']);
			}
		}
		// Delete any rows from table that have no project_id or sid_hash (because we can't do anything with these)
		$sql = "delete from redcap_surveys_erase_twilio_log where sid_hash is null and project_id is null";
		db_query($sql);
		$rowsDeleted += db_affected_rows();
		// Return count of rows deleted
		return $rowsDeleted;
	}


	/**
	 * ERASE *ALL* TWILIO CALL/SMS LOGS FOR A GIVEN PHONE NUMBER
	 * Serves as extra cleaning in case EraseTwilioWebsiteLog() missed something
	 */
	public static function EraseAllTwilioWebsiteLog($sid, $token, $phone)
	{
		// Format phone
		if ($phone == '') return;
		$phone = Messaging::formatNumber($phone);
		// Set Twilio client
		$twilioClient = new Services_Twilio($sid, $token);	
		// ToFrom array
		$toFroms = array("From", "To");
		try {
			$itemTypes = array($twilioClient->account->calls, $twilioClient->account->messages);
			// Loop through both calls and SMS
			foreach ($itemTypes as $itemType) {
				// Loop through both To and From calls/SMS or this phone number
				foreach ($toFroms as $toFrom) {
					// Loop through each call/SMS in the log and delete it
					foreach ($itemType->getIterator(0, 50, array($toFrom => $phone)) as $item) {		
						// Delete the log of this call/SMS
						try {
							$itemType->delete($item->sid);
						} catch (Exception $e) { }
					}
				}
			}
		} catch (Exception $e) { }
	}

    // Validate Twilio settings
    public static function validateTwilioSetup($postArr, $project_id) {
        $twilio_from_number = $postArr['twilio_from_number'];
        $twilio_alphanum_sender_id  = $postArr['twilio_alphanum_sender_id'];
        $twilio_enabled = (isset($postArr['twilio_enabled']) && $postArr['twilio_enabled'] == '1') ? '1' : '0';
        $error_msg = '';
        // Make sure that Twilio number is not used by another project
        if ($twilio_from_number != '') {
            $sql1 = "select 1 from redcap_projects where project_id != $project_id and ";
            $condition = '';
            // If this is a U.S. number, try both with and without the country code
            if ((strlen($twilio_from_number) == 10 && isPhoneUS($twilio_from_number)) || (strlen($twilio_from_number) == 11 && substr($twilio_from_number, 0, 1) == '1' && isPhoneUS(right($twilio_from_number, 10)))) {
                $twilio_from_number_no_cc = right($twilio_from_number, 10);
                $condition = "(twilio_from_number = '".db_escape('1'.$twilio_from_number_no_cc)."' or twilio_from_number = '".db_escape($twilio_from_number_no_cc)."')";
            } else {
                $condition = "twilio_from_number = '".db_escape($twilio_from_number)."'";
            }

            $sql1 .= $condition;
            $q1 = db_query($sql1);

            // Check for projects where users have sent twilio details for approvals and request still pending
            $sql2 = "select 1 from redcap_twilio_credentials_temp where project_id != $project_id and ";
            $sql2 .= $condition;
            $q2 = db_query($sql2);

            if (db_num_rows($q1) || db_num_rows($q2)) {
                // ERROR: Another project has this number
                $error_msg .= RCView::span(array('style'=>''),
                    RCView::tt('survey_958') . " " . RCView::b(formatPhone($twilio_from_number)) . " " . RCView::tt('survey_959')
                );
                $twilio_from_number = '';
            }
        }
        ## TWILIO CHECK: Check connection to Twilio and also set the voice/sms URLs to the REDCap survey URL, if not set yet
        // Instantiate a new Twilio Rest Client
        $twilioClient = self::client();
        // SET URLS: Loop over the list of numbers and get the sid of the phone number (don't do this if we don't have a value for $twilio_from_number)
        if ($twilio_from_number != '') {
            try {
                $numberBelongsToAcct = false;
                $allNumbers = array();
                foreach ($twilioClient->account->incoming_phone_numbers as $number) {
                    // Collect number in array
                    $allNumbers[] = $number->phone_number;
                    // If number does not match, then skip
                    if (substr($number->phone_number, -1 * strlen($twilio_from_number)) != $twilio_from_number) {
                        continue;
                    }
                    // We verified that the number belongs to this Twilio account
                    $numberBelongsToAcct = true;
                    // Set VoiceUrl and SmsUrl for this number, if not set yet
                    if ($number->voice_url != APP_PATH_SURVEY_FULL || $number->sms_url != APP_PATH_SURVEY_FULL) {
                        $number->update(array("VoiceUrl" => APP_PATH_SURVEY_FULL, "SmsUrl" => APP_PATH_SURVEY_FULL));
                    }
                }
                // If number doesn't belong to account
                if (!$numberBelongsToAcct) {
                    // Set error message
                    $error_msg .= RCView::tt('survey_920');
                    if (empty($allNumbers)) {
                        $error_msg .= RCView::div(array('style' => 'margin-top:10px;font-weight:bold;'), RCView::tt('survey_843'));
                    } else {
                        $error_msg .= RCView::div(array('style' => 'margin-top:5px;font-weight:bold;'), " &nbsp; " . implode("<br> &nbsp; ", $allNumbers));
                    }
                }
            } catch (Exception $e) {
                // Set error message
                $error_msg .= RCView::tt('survey_919');
                // Make sure Localhost isn't being used as REDCap base URL (not valid for Twilio)
                if (strpos(APP_PATH_SURVEY_FULL, "http://localhost") !== false || strpos(APP_PATH_SURVEY_FULL, "https://localhost") !== false) {
                    $error_msg .= "<br><br>" . RCView::tt('survey_841');
                }
            }
        }
        // If we are missing the phone number or if an error occurred with Twilio, then disable this module
        if ($twilio_enabled && (($twilio_from_number == '' && $twilio_alphanum_sender_id == '') || $error_msg != '')) {
            $sql = "update redcap_projects set twilio_enabled = 0 where project_id = $project_id";
            db_query($sql);
            // If Twilio credentials worked but no phone number was entered, then let them know that the module was NOT enabled
            if ($twilio_from_number == '' && $error_msg == '') {
                $error_msg = RCView::tt('survey_842');
            }
        }
        return $error_msg;
    }
}