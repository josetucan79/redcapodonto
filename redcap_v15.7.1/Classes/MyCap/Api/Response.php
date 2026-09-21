<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api;

/**
 * Sends a standard MyCap API Response
 *
 * @package MyCap\Api
 */
class Response
{
    /**
     * Make a standard success response
     *
     * @param array|object|null $data
     * @param string|null $message
     */
    public static function sendSuccess($data = null, $message = null)
    {
        $body = [
            'success' => true
        ];
        if (isset($data)) {
            $body['data'] = $data;
        }
        if (isset($message)) {
            $body['message'] = $message;
        }
        // error_log("--- Response:sendSuccess ----");
        // error_log(print_r($body, true));
        self::send(
            200,
            json_encode($body)
        );
    }

    /**
     * Send a response and stop execution of the script. You should typically be using sendSuccess or sendError. Only
     * call send directly if you need to send a non-json response. E.g. binary data
     *
     * @param int $httpCode HTTP Code
     * @param string $body
     */
    public static function send($httpCode, $body)
    {
        \RestUtility::sendResponse($httpCode, $body, 'json');

    }

    /**
     * Make a standard error response
     *
     * @param int $httpCode HTTP Code
     * @param string $errorType Type of error
     * @param string $message Developer-friendly error message
     */
    public static function sendError($httpCode, $errorType, $message)
    {
        //error_log("--- Response:sendError httpCode, errorType, message  ----");
        //error_log($httpCode."--".$errorType."--".$message);
        $body = json_encode(
            [
                'success' => false,
                'error' => $errorType,
                'message' => $message
            ]
        );
        self::send(
            $httpCode,
            $body
        );
    }
}
