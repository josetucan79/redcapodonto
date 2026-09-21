<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo;

use CurlHandle;
use Duo\DuoUniversal\DuoException;
use Duo\DuoUniversal\Client as DuoUniversalClient;

/**
 * Modified version of the Client library provided by DUO.
 * Uses the proxy configuration defined in the REDCap
 * Genaral Configuration settings page.
 */
class Client extends DuoUniversalClient {

   /**
     * Make HTTPS calls to Duo.
     *
     * @param string  $endpoint   The endpoint we are trying to hit
     * @param any     $request    Information to send to Duo
     * @param boolean $user_agent (Optional)True if we want to send
     *                            a user-agent string
     *
     * @return array of strings
     * @throws DuoException For failure to connect to Duo
     */
    protected function makeHttpsCall($endpoint, $request, $user_agent = null)
    {

        $url = "https://" . $this->api_host . $endpoint;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_CAINFO, self::DUO_CERTS);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($user_agent !== null) {
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        }
        $this->applyProxyConfiguration($ch);

        $result = curl_exec($ch);

        /* Throw an error if the result doesn't exist or if our request returned a 5XX status */
        if (!$result) {
            throw new DuoException(self::FAILED_CONNECTION);
        }
        if (self::SUCCESS_STATUS_CODE !== curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
            throw new DuoException($this->getExceptionFromResult(json_decode($result, true)));
        }
        return json_decode($result, true);
    }

    /**
     * apply the REDCap proxy configuration
     *
     * @param CurlHandle $ch
     * @return void
     */
    private function applyProxyConfiguration($ch) {
        curl_setopt($ch, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
    }

    /**
     * Retrieves exception message for DuoException from HTTPS result message.
     *
     * @param array $result The result from the HTTPS request
     *
     * @return string The exception message taken from the message or MALFORMED_RESPONSE
     */
    public function getExceptionFromResult($result)
    {
        if (isset($result["message"]) && isset($result["message_detail"])) {
            return $result["message"] . ": " . $result["message_detail"];
        } elseif (isset($result["error"]) && isset($result["error_description"])) {
            return $result["error"] . ": " . $result["error_description"];
        }
        return self::MALFORMED_RESPONSE;
    }

}