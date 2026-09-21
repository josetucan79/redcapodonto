<?php

/**
 * Messaging
 * This class is used for all messaging (SMS, Facebook Messenger, WhatsApp) for Twilio, Mosio, and other providers
 */
class Messaging
{
    public $project_id;
    public $Proj;
    public $twilioClient;
    public $mosioClient;
    const PROVIDER_TWILIO = 'twilio';
    const PROVIDER_MOSIO = 'mosio';

    // Constructor
    public function __construct($this_project_id)
    {
        // Set project_id for this object
        $this->project_id = $this_project_id;
        $this->Proj = new Project($this->project_id);
        // Init specific providers
        if ($this->Proj->messaging_provider == self::PROVIDER_MOSIO) {
            // Mosio
            $this->mosioClient = new Mosio($this->project_id);
        } elseif ($this->Proj->messaging_provider == self::PROVIDER_TWILIO) {
            // Twilio
            $this->twilioClient = new Services_Twilio($this->Proj->project['twilio_account_sid'], $this->Proj->project['twilio_auth_token']);
        }
    }

    // Send a message of a certain type (SMS, Facebook Messenger, WhatsApp) using the project's messaging provider
    public function send($message, $recipientPhoneNumber, $type='sms', $record=null, $category=null, $addToEmailLog=false)
    {
        ## Mosio
        if ($this->Proj->messaging_provider == self::PROVIDER_MOSIO)
        {
            // Mosio SMS
            return $this->mosioClient->sendSMS($message, $recipientPhoneNumber, $record, $category, $addToEmailLog);
        }
        ## Twilio
        elseif ($this->Proj->messaging_provider == self::PROVIDER_TWILIO)
        {
            // Twilio SMS
            if ($type == 'sms') {
                return TwilioRC::sendSMS($message, $recipientPhoneNumber, $this->twilioClient, $this->Proj->project['twilio_from_number'], true, $this->project_id,
                                         $addToEmailLog, $record, $category, $this->Proj->project['twilio_alphanum_sender_id']);
            }
            // Twilio WhatsApp
            elseif ($type == 'whatsapp') {

            }
        }
        // If we got this far, return false
        return false;
    }

    // Determine if this is an incoming request from a messaging provider (Twilio, Mosio, etc.)
    public static function isIncomingRequest()
    {
        return (
            // Twilio
            isset($_SERVER['HTTP_X_TWILIO_SIGNATURE'])
            // Mosio
            || (isset($_POST['MosioAccountId']) && isset($_POST['ApiKey']))
        );
    }

    // Determine if this is an incoming request from a messaging provider (Twilio, Mosio, etc.)
    public static function getIncomingRequestType()
    {
        // Twilio
        if (isset($_SERVER['HTTP_X_TWILIO_SIGNATURE'])) {
            return self::PROVIDER_TWILIO;
        }
        // Mosio
        elseif (isset($_POST['MosioAccountId']) && isset($_POST['ApiKey'])) {
            return self::PROVIDER_MOSIO;
        }
        // Return null if got this far
        return null;
    }

    // Convert phone number to E.164 format before handing off to Twilio
    public static function formatNumber($phoneNumber)
    {
        // If number contains an extension (denoted by a comma between the number and extension), then separate here and add later
        $phoneExtension = "";
        if (strpos($phoneNumber, ",") !== false) {
            list ($phoneNumber, $phoneExtension) = explode(",", $phoneNumber, 2);
        }
        // Remove all non-numerals
        $phoneNumber = preg_replace("/[^0-9]/", "", $phoneNumber);
        // Prepend number with + for international use cases (except for short codes, which are 5 or 6 digits in length)
        if (strlen($phoneNumber) > 6) {
            $phoneNumber = (isPhoneUS($phoneNumber) ? "+1" : "+") . $phoneNumber;
        }
        // If has an extension, re-add it
        if ($phoneExtension != "") $phoneNumber .= ",$phoneExtension";
        // Return formatted number
        return $phoneNumber;
    }

    // Remove all HTML and clean text for sending via SMS. Also, optionally maintain line breaks.
    public static function cleanSmsText($text)
    {
        $text = br2nl(label_decode($text));
        $text = replaceHtmlLinkWithUrl($text, true);
        $text = trim(replaceNBSP(strip_tags(str_replace(array("\r\n", "\r", "\t", "</p>\n", "</p>", "\n", "  "), array("\n", "\n", " ", "</p>", "</p>\n\n", " \n", " "), $text))));
        return $text;
    }

}
