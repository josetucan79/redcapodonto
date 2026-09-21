<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\ParticipantHandler;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\ProjectHandler;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\MiscHandler;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\ResultHandler;
use Vanderbilt\REDCap\Classes\MyCap\Api\Error\ApiError;
use Vanderbilt\REDCap\Classes\MyCap\Api\Response;


/**
 * MyCap API Class
 */
class MyCapApi
{
    /** @var string $apiUrl MyCap API URL */
    public $apiUrl;
    /** @var string $metadataPid REDCap project ID for the MyCap !METADATA! project */
    public $metadataPid;
    /** @var string $hmacKey Hash-based Message Access Code key */
    private $hmacKey;
    /** @var int $expirationLimit Requests are valid for (expiration limit) minutes after timestamp */
    public $expirationLimit = 15;

    /**
     * The API constructor requires the following arguments:
     *
     * - apiUrl
     * - hmacKey
     * - messageToken
     * - metadataPid
     * - metadataToken
     * - redcapApiUrl
     *
     * @param array $args API configuration arguments.
     * @throws \Exception
     */
    public function __construct(array $args)
    {
        $this->hmacKey = $args['hmacKey'];

        if (isset($args['expirationLimit']) && is_numeric($args['expirationLimit'])) {
            $this->expirationLimit = $args['expirationLimit'];
        }
    }

    /**
     * Verifies that all required parameters exist
     *
     * @param array $data
     * @param array $parameters Array of parameter names (string) to check existence
     */
    public static function validateParameters($data, $parameters)
    {
        $error = false;
        $message = "";

        foreach ($parameters as $param) {
            if (!isset($data[$param])) {
                $error = true;
                $message .= ", " . $param;
            }
        }

        if ($error) {
            $message = "Missing parameter(s): " . ltrim(
                $message,
                ", "
            );
            Response::sendError(
                400,
                ApiError::MISSING_PARAMETER,
                $message
            );
        }
    }

    /**
     * Process/Execute an API Request
     *
     * @param array $data
     * @return string JSON Response
     */
    public function processRequest($data)
    {

        $apiError = $this->validateRequest($data);
        switch ($apiError) {
            case ApiError::MISSING_SIGNATURE:
                Response::sendError(
                    401,
                    $apiError,
                    "Missing signature"
                );
                break;

            case ApiError::INVALID_SIGNATURE:
                Response::sendError(
                    403,
                    $apiError,
                    "Invalid signature"
                );
                break;

            case ApiError::MISSING_EXPIRATION:
                Response::sendError(
                    400,
                    $apiError,
                    "Missing parameter: expires"
                );
                break;

            case ApiError::EXPIRED:
                Response::sendError(
                    400,
                    $apiError,
                    "Request has expired"
                );
                break;

            case ApiError::MISSING_ACTION:
                Response::sendError(
                    400,
                    $apiError,
                    "Missing parameter: action"
                );
                break;

            case ApiError::INVALID_ACTION:
                Response::sendError(
                    400,
                    $apiError,
                    "Action does not exist"
                );
                break;

            default:
                // Intentionally empty. Occurs when request is valid.
                break;
        }

        switch ($data['action']) {
            case ProjectHandler::$actions['GET_STUDY_CONFIG']:
                $project = new ProjectHandler(["apiDelegate" => $this]);
                $project->getConfig($data);
                break;

            case ProjectHandler::$actions['GET_STUDY_IMAGES']:
                $project = new ProjectHandler(["apiDelegate" => $this]);
                $project->getImages($data);
                break;

            case ProjectHandler::$actions['GET_STUDY_FILE']:
                $project = new ProjectHandler(["apiDelegate" => $this]);
                $project->getFile($data);
                break;

            case ProjectHandler::$actions['GET_STUDY_TRANSLATIONS']:
                $project = new ProjectHandler(["apiDelegate" => $this]);
                $project->getTranslations($data);
                break;

            case ResultHandler::$actions['SAVE_RESULT']:
                $result = new ResultHandler(["apiDelegate" => $this]);
                $result->saveResult($data);
                break;

            case ResultHandler::$actions['GET_RESULTS']:
                $result = new ResultHandler(["apiDelegate" => $this]);
                $result->getResults($data);
                break;

            case ParticipantHandler::$actions['AUTHENTICATE_PARTICIPANT']:
                $user = new ParticipantHandler(["apiDelegate" => $this]);
                $user->authenticate($data);
                break;

            case ParticipantHandler::$actions['SAVE_PARTICIPANT_PUSH_IDENTIFIER']:
                $user = new ParticipantHandler(["apiDelegate" => $this]);
                $user->saveParticipantPushIdentifier($data);
                break;

            case ParticipantHandler::$actions['SAVE_USER_PROPERTIES']:
                $user = new ParticipantHandler(["apiDelegate" => $this]);
                $user->saveUserProperties($data);
                break;

            case ParticipantHandler::$actions['GET_USER_ZERODATE']:
                $user = new ParticipantHandler(["apiDelegate" => $this]);
                $user->getUserZeroDate($data);
                break;

            case ParticipantHandler::$actions['GET_USER_INSTALLDATE']:
                $user = new ParticipantHandler(["apiDelegate" => $this]);
                $user->getUserInstallDate($data);
                break;

            case ParticipantHandler::$actions['GET_PARTICIPANT_MESSAGES']:
                $user = new ParticipantHandler(["apiDelegate" => $this]);
                $user->getParticipantMessages($data);
                break;

            case ParticipantHandler::$actions['SAVE_PARTICIPANT_MESSAGE']:
                $user = new ParticipantHandler(["apiDelegate" => $this]);
                $user->saveParticipantMessage($data);
                break;

            case MiscHandler::$actions['GET_ACCESS_KEY']:
                $utility = new MiscHandler(["apiDelegate" => $this, "hmacKey" => $this->hmacKey]);
                $utility->getAccessKey($data);
                break;

            case MiscHandler::$actions['TEST_ENDPOINT']:
                $utility = new MiscHandler(["apiDelegate" => $this]);
                $utility->testEndpoint();
                break;

            case ResultHandler::$actions['GET_PROMIS_SURVEY_LINK']:
                $result = new ResultHandler(["apiDelegate" => $this]);
                $result->getSurveyLink($data);
                break;
            default:
                // Exists simply for development purposes
                throw new \Exception($data['action'] . " is valid but has not been implemented.");
                break;
        }
    }

    /**
     * Validates a request. Returns an ApiError type if the request is not
     * valid. Return null otherwise. Note that a few API requests are public
     * and do not require validation.
     *
     * @param array $data POST/GET/etc...
     * @return mixed ApiError if error or null if valid
     */
    private function validateRequest($data)
    {
        $doNotValidate = array_values(MiscHandler::$actions);
        if (isset($data['action']) && in_array(
            $data['action'],
            $doNotValidate
        )) {
            return null;
        }

        if (!isset($data['signature'])) {
            return ApiError::MISSING_SIGNATURE;
        }

        if (!$isValidSignature = $this->validateSignature($data)) {
            return ApiError::INVALID_SIGNATURE;
        }

        if (!isset($data['expires'])) {
            return ApiError::MISSING_EXPIRATION;
        }

        if (!$isValidExpiration = $this->validateExpiration($data['expires'])) {
            return ApiError::EXPIRED;
        }

        if (!isset($data['action']) || $data['action'] == '') {
            return ApiError::MISSING_ACTION;
        }

        $actions = array_merge(
            ParticipantHandler::$actions,
            ResultHandler::$actions,
            ProjectHandler::$actions,
            MiscHandler::$actions
        );
        if (!in_array(
            $data['action'],
            $actions
        )) {
            return ApiError::INVALID_ACTION;
        }

        return null;
    }

    /**
     * Each request is signed using a Hash-based message authentication code (HMAC).
     * Make sure the signature is valid. Signatures are similar to Amazon Web Services
     * HMAC-SHA256 Signatures for REST Requests.
     *
     * @param array $data
     * @return bool
     */
    private function validateSignature($data)
    {
        $valid = true;

        // Save a copy of the provided signature
        // Trimming because we received a newline character once
        $signature = trim($data['signature']);
        // Delete the provided signature from our data
        unset($data['signature'], $data['content'], $data['format']);

        // Sort data by key
        ksort($data);

        // Prepend the HTTP-Verb, which is always POST in our case
        $str = "POST";

        // Convert array into a single long string
        foreach ($data as $key => $val) {
            // Do not include files when validating signature
            /*if (is_a(
                $val,
                'GuzzleHttp\Psr7\UploadedFile'
            )) {
                continue;
            }*/
            $str .= $key . $val;
        }

        //
        // Example POST (signature has been removed)
        //
        //   Array
        //   (
        //      [action] => getUserMessages
        //      [expires] => 1458241065
        //      [stu_id] => render-usable-policy-conga
        //      [par_code] => JONSWA
        //   )
        //
        // Into ($str):
        //
        //   POSTactiongetUserMessagesexpires1458241065stu_idrender-usable-policy-congapar_codeJONSWA
        //
        // Hashed ($computedSignature):
        //
        //   e6ad44e89a7a7a37d697a2c9472aa4c673c52839ccf1e33ed1f99652d193c88b
        //
        // error_log("----str for creating signature in validateSignature ----".$str."----hmacKey---".$this->hmacKey);
        $computedSignature = trim(
            hash_hmac(
                'sha256',
                $str,
                $this->hmacKey ?? ""
            )
        );
        // error_log("----Compare signature in validateSignature ----posted:".$signature."==generated:".$computedSignature."----");
        if ($signature != $computedSignature) {
            $valid = false;
            // One of our tests passes an empty array. No need to log when nothing was given. Intent of logging this
            // is to see if anyone is trying to reverse engineer security
            if (count($data)) {
                /*Log::message(
                    "Could not validate signature. " . print_r(
                        $data,
                        true
                    ),
                    __FILE__,
                    __LINE__
                );*/
            }
        }

        return $valid;
    }

    /**
     * Each request is valid for N minutes after being sent
     *
     * @param int $expires Unix Timestamp (number of seconds since the Unix Epoch: January 1 1970 00:00:00 GMT)
     * @return bool
     */
    private function validateExpiration($expires)
    {

        if (!is_numeric($expires)) {
            return false;
        }

        $expires += (60 * $this->expirationLimit);

        if (time() > $expires) {
            return false;
        }

        return true;
    }
}
