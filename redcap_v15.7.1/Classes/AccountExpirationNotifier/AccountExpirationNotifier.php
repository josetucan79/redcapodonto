<?php
namespace Vanderbilt\REDCap\Classes\AccountExpirationNotifier;

use User;
use DateTime;
use DateTimeRC;
use Exception;

class AccountExpirationNotifier {

    /** @var MessageFactory */
    private $messageFactory;

    /** @var MessageTemplateFactory */
    private $messageTemplateFactory;

    /** @var array */
    private $warningDays;

    /**
     * track total number of messages sent
     *
     * @var integer
     */
    protected $sentWarnings = [];
    protected $failedWarnings = [];

    /**
     * @param MessageFactory $messageFactory
     * @param MessageTemplateFactory $messageTemplateFactory
     * @param array $warningDays
     */
    public function __construct($messageFactory, $messageTemplateFactory, $warningDays) {
        $this->messageFactory = $messageFactory;
        $this->messageTemplateFactory = $messageTemplateFactory;
        $this->warningDays = $warningDays;
    }

    public function addSentWarnings($user) {
        $this->sentWarnings[] = $user;
    }
    public function addFailedWarnings($user) {
        $this->failedWarnings[] = $user;
    }

    /**
     *
     * @return array
     */
    public function getSentWarnings() {
        return $this->sentWarnings;
    }

    /**
     *
     * @return array
     */
    public function getFailedWarnings() {
        return $this->failedWarnings;
    }

    /**
     * Finds users eligible for expiration warnings.
     *
     * @return array List of users who should be notified.
     */
    public function findEligibleUsersForWarning() {
        $eligibleUsers = [];
        foreach ($this->warningDays as $daysBeforeExpiration) {
            // Calculate the date x days from now
            $xDaysFromNow = date("Y-m-d", strtotime("+$daysBeforeExpiration days"));

            // Query to find eligible users
            $sql = "SELECT username, user_email, user_expiration, user_sponsor, user_firstname, user_lastname
                    FROM redcap_user_information 
                    WHERE user_expiration IS NOT NULL 
                    AND user_suspended_time IS NULL 
                    AND DATE(user_expiration) = '$xDaysFromNow'";

            // Execute query and fetch results (this assumes you have a function db_query())
            $queryResult = db_query($sql);

            while ($row = db_fetch_assoc($queryResult)) {
                $eligibleUsers[] = $row;
            }
        }
        return $eligibleUsers;
    }

    /**
     * Sends warning messages to eligible users.
     *
     * Retrieves a list of users eligible for warnings using `findEligibleUsersForWarning`. 
     * Then, iterates over this list to send a message to each user. The method updates 
     * the `$users` array with the list of eligible users, and the `$sent` and `$failed` 
     * variables with the count of successfully sent and failed messages, respectively.
     *
     * @param array|null &$users A reference variable that will be populated with the array of eligible users. If null, the method will find the users.
     * @param array|null &$sent A reference variable that will be updated with the list of successfully sent messages.
     * @param array|null &$failed A reference variable that will be updated with the list of failed message attempts.
     * @return void
     */
    public function sendWarnings(&$users=null, &$sent=null, &$failed=null) {
        $users = $this->findEligibleUsersForWarning();
        foreach ($users as $user) {
            $this->sendMessageToUser($user);
        }
        $sent = $this->getSentWarnings();
        $failed = $this->getFailedWarnings();
    }

    /**
     * returns a boolean indicating if a user has a sponsor
     * also returns the sponsor user information by reference
     * 
     *
     * @param array $user
     * @param array $sponsorUserInfo
     * @return boolean
     */
    private function userHasSponsor($user, &$sponsorUserInfo) {
        $sponsorUserInfo = User::getUserInfo($user['user_sponsor']);
        if ($sponsorUserInfo !== false && $sponsorUserInfo['user_email'] != '') {
           return true;
        }
        return false;
    }

    /**
     * apply placeholders and render the message
     *
     * @param array $user
     * @return string
     */
    public function renderMessage($user) {
        $placeholders = $this->makePlaceholders($user);
        return $this->messageTemplateFactory->getMessageBody($user, $placeholders);
    }

    public function sendMessageToUser($user) {
        global $lang, $project_contact_email, $project_contact_name;

        $daysLeft = $this->calculateDaysLeft($user['user_expiration']);

        $sponsorUserInfo = []; // default (this line might be needed to reset this from previous calls to the sendMessageToUser method)
        $hasSponsor = $this->userHasSponsor($user, $sponsorUserInfo);
        $cc = $hasSponsor ? $sponsorUserInfo['user_email'] : '';

        $messageBody = $this->renderMessage($user);
        
        $message = $this->messageFactory->make();
        $message->setCc($cc);
		$message->setFrom(\Message::useDoNotReply($GLOBALS['project_contact_email']));
        $message->setFromName($project_contact_name);
        $message->setTo($user['user_email']);
		$institution_subject = (trim($GLOBALS['institution']) == '') ? '' : " (".$GLOBALS['institution'].")";
		$message->setSubject("[REDCap] {$user['username']}{$lang['cron_16']} $daysLeft {$lang['scheduling_25']}".$institution_subject);
        $message->setBody($messageBody, true);
        $sent = $message->send();
        ($sent)
            ? $this->addSentWarnings($user)
            : $this->addFailedWarnings($user);
    }

    protected function calculateDaysLeft($expirationDate) {
        $expiry = new DateTime($expirationDate);
        $today = new DateTime();
        $interval = $today->diff($expiry);
        return $interval->days;
    }

    /**
     * generates a list of strings and callables
     * to use as placeholders in the message template
     *
     * @param array $user
     * @return array
     */
    public function makePlaceholders($user) {
        $sponsorUserInfo = User::getUserInfo($user['user_sponsor'] ?? '');
        $undefined = '--replacement not available--';
        $mktime = strtotime($user['user_expiration'] ?? '');
        $x_days_from_now_friendly = date("l, F j, Y", $mktime);
        $x_time_from_now_friendly = date("g:i A", $mktime);
        $expirationDateTime = function($format='Y-m-d H:i:s') use($user) {
            try {
                // Create a DateTime object from the date string
                $date = new DateTime($user['user_expiration'] ?? '');
                
                // Format the date according to the specified format
                return $date->format($format);
            } catch (Exception $e) {
                // Handle any exceptions (like invalid date formats)
                return "Invalid date: " . $e->getMessage();
            }
        };
        $preferredUserDateFormat = function() use($user) {
            return DateTimeRC::format_user_datetime($user['user_expiration'] ?? '', 'Y-M-D_24', $user['datetime_format'] ?? null);
        };

        return [
            // user info
            Placeholders::USERNAME => $user['username'] ?? $undefined,
            Placeholders::USER_EMAIL => $user['user_email'] ?? $undefined,
            Placeholders::USER_EXPIRATION => $user['user_expiration'] ?? $undefined,
            Placeholders::USER_SPONSOR => $user['user_sponsor'] ?? $undefined,
            Placeholders::USER_FIRSTNAME => $user['user_firstname'] ?? $undefined,
            Placeholders::USER_LASTNAME => $user['user_lastname'] ?? $undefined,
            // sponsor info
            Placeholders::SPONSOR_USERNAME => $sponsorUserInfo['username'] ?? $undefined,
            Placeholders::SPONSOR_USER_EMAIL => $sponsorUserInfo['user_email'] ?? $undefined,
            Placeholders::SPONSOR_USER_FIRSTNAME => $sponsorUserInfo['user_firstname'] ?? $undefined,
            Placeholders::SPONSOR_USER_LASTNAME => $sponsorUserInfo['user_lastname'] ?? $undefined,
            // remaining days
            Placeholders::EXPIRATION_DATE_FRIENDLY => $x_days_from_now_friendly,
            Placeholders::EXPIRATION_TIME_FRIENDLY => $x_time_from_now_friendly,
            Placeholders::PREFERRED_USER_DATE_FORMAT => $preferredUserDateFormat,
            Placeholders::EXPIRATION_DATE_TIME => $expirationDateTime,
            // System
            // Placeholders::APP_PATH_DOCROOT => APP_PATH_DOCROOT,
            // Placeholders::APP_PATH_WEBROOT_FULL => APP_PATH_WEBROOT_FULL,
            // Placeholders::APP_PATH_IMAGES => APP_PATH_IMAGES,
            // Placeholders::APP_PATH_WEBROOT => APP_PATH_WEBROOT,
            // Placeholders::APP_PATH_WEBROOT_PARENT => APP_PATH_WEBROOT_PARENT,
            // Placeholders::REDCAP_VERSION => REDCAP_VERSION,
            // REDCap
            // Placeholders::INSTITUTION => $GLOBALS['institution'],
        ];
    }

    /**
     * list of placeholders that can be used with matching label
     * this list is used in the frontend to automatically
     * type a valid placeholder
     * 
     * @return array
     */
    public static function getGroupedPlaceholders() {
        $placeholders = [
            [
                'label' => 'User', 'value' => [
                    ['label' => 'username', 'value' => Placeholders::USERNAME],
                    ['label' => 'User Email', 'value' => Placeholders::USER_EMAIL],
                    ['label' => 'User Expiration', 'value' => Placeholders::USER_EXPIRATION],
                    ['label' => 'User Sponsor', 'value' => Placeholders::USER_SPONSOR],
                    ['label' => 'User Firstname', 'value' => Placeholders::USER_FIRSTNAME],
                    ['label' => 'User Lastname', 'value' => Placeholders::USER_LASTNAME],
                ]
            ],
            [
                'label' => 'Sponsor', 'value' => [
                    ['label' => 'Sponsor Username', 'value' => Placeholders::SPONSOR_USERNAME],
                    ['label' => 'Sponsor User Email', 'value' => Placeholders::SPONSOR_USER_EMAIL],
                    ['label' => 'Sponsor User Firstname', 'value' => Placeholders::SPONSOR_USER_FIRSTNAME],
                    ['label' => 'Sponsor User Lastname', 'value' => Placeholders::SPONSOR_USER_LASTNAME],
                ]
            ],
            [
                'label' => 'Time', 'value' => [
                    ['label' => 'Preferred User Date Format', 'value' => Placeholders::PREFERRED_USER_DATE_FORMAT],
                    // ['label' => 'Expiration Date Friendly', 'value' => Placeholders::EXPIRATION_DATE_FRIENDLY],
                    // ['label' => 'Expiration Time Friendly', 'value' => Placeholders::EXPIRATION_TIME_FRIENDLY],
                    ['label' => 'Custom Expiration Date Time', 'value' => Placeholders::EXPIRATION_DATE_TIME.':l\, F j\, Y'],
                ]
            ],
            // [
            //     'label' => 'REDCap', 'value' => [
            //         ['label' => 'Institution', 'value' => Placeholders::INSTITUTION],
            //     ]
            // ],
            // [
            //     'label' => 'System', 'value' => [
            //         ['label' => 'APP_PATH_DOCROOT', 'value' => Placeholders::APP_PATH_DOCROOT],
            //         ['label' => 'APP_PATH_WEBROOT_FULL', 'value' => Placeholders::APP_PATH_WEBROOT_FULL],
            //         ['label' => 'APP_PATH_IMAGES', 'value' => Placeholders::APP_PATH_IMAGES],
            //         ['label' => 'APP_PATH_WEBROOT', 'value' => Placeholders::APP_PATH_WEBROOT],
            //         ['label' => 'APP_PATH_WEBROOT_PARENT', 'value' => Placeholders::APP_PATH_WEBROOT_PARENT],
            //         ['label' => 'REDCAP_VERSION', 'value' => Placeholders::REDCAP_VERSION],
            //         /* ['label' => 'test', 'value' => [
            //             ['label' => 'test1', 'value' => 'test1'],
            //             ['label' => 'test2', 'value' => 'test2'],
            //             ['label' => 'test3', 'value' => 'test3'],
            //         ]], */
            //     ],
            // ],
        ];
        return $placeholders;
    }
    
}
