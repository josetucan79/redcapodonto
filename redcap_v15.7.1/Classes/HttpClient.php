<?php
/**
 * client for HTTP requests
 * 
 * wrapper fot the HTTP library Requests (http://requests.ryanmccue.info)
 * it can intercept different exceptions allowing a better debugging
 */
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

class HttpClient {

	protected static $default_options = array(
		'headers' => array(),
		'options' => array(),
		'data' => array(),
	);

	protected static function defaultRequestOptions($URI)
	{
		$systemConfig = System::getConfigVals();
		$overrideSystemCaBundle = boolval($systemConfig['override_system_bundle_ca'] ?? false);
		$options = [
			RequestOptions::HTTP_ERRORS => true,
			RequestOptions::TIMEOUT  => 60.0, //seconds
		];
		$proxy = $systemConfig['proxy_hostname'] ?? null;
		if($proxy && !sameHostUrl($URI)) {
			$options[RequestOptions::PROXY] = $proxy;
			// also add password
			$proxy_username_password = $systemConfig['proxy_username_password'] ?? '';
			if (!empty($proxy_username_password)) {
				$options[RequestOptions::PROXY] = preg_replace('/^(https?:\/\/)/', '${1}' . $proxy_username_password . '@', $proxy);
			}
		}
		if($overrideSystemCaBundle) $options[RequestOptions::VERIFY] = APP_PATH_DOCROOT . "Resources/misc/cacert.pem";
		return $options;
	}

	/**
     * merge the params with the default values,
     * but only keep the keys of the default params
     *
     * @param array $defaultParams array with the master keys
     * @param array $overrideParams array with the values to override
     * @return array $params merged array with just the keys of the defaultParams array
     */
    private static function mergeParams($defaultParams, $overrideParams=array())
    {
        $params = array_replace_recursive($defaultParams, $overrideParams);
        return $params;
	}

	public static function request($method, $URI, $options=array())
    {
		$client = new Client(self::defaultRequestOptions($URI));
		try {
			$response = $client->request($method, $URI, $options);
			return $response;
		} catch (RequestException $e) {
			/* $message = $e->getMessage();
			$message = Psr7\str($e->getRequest());
			if ($e->hasResponse()) {
				$message = Psr7\str($e->getResponse());
			} */
			throw($e);
		}
	}
	
	public static function redirect($URL, $replace=true, $responseCode=302)
    {
		header("Location: $URL", $replace, $responseCode);
        exit();
	}
	

	/**
	 * print a JSON response and exit
	 * 
	 * some status codes for reference:
	 * 	100: 'Continue'
	 * 	101: 'Switching Protocols'
	 * 	200: 'OK'
	 * 	201: 'Created'
	 * 	202: 'Accepted'
	 * 	203: 'Non-Authoritative Information'
	 * 	204: 'No Content'
	 * 	205: 'Reset Content'
	 * 	206: 'Partial Content'
	 * 	300: 'Multiple Choices'
	 * 	301: 'Moved Permanently'
	 * 	302: 'Moved Temporarily'
	 * 	303: 'See Other'
	 * 	304: 'Not Modified'
	 * 	305: 'Use Proxy'
	 * 	400: 'Bad Request'
	 * 	401: 'Unauthorized'
	 * 	402: 'Payment Required'
	 * 	403: 'Forbidden'
	 * 	404: 'Not Found'
	 * 	405: 'Method Not Allowed'
	 * 	406: 'Not Acceptable'
	 * 	407: 'Proxy Authentication Required'
	 * 	408: 'Request Time-out'
	 * 	409: 'Conflict'
	 * 	410: 'Gone'
	 * 	411: 'Length Required'
	 * 	412: 'Precondition Failed'
	 * 	413: 'Request Entity Too Large'
	 * 	414: 'Request-URI Too Large'
	 * 	415: 'Unsupported Media Type'
	 * 	500: 'Internal Server Error'
	 * 	501: 'Not Implemented'
	 * 	502: 'Bad Gateway'
	 * 	503: 'Service Unavailable'
	 * 	504: 'Gateway Time-out'
	 * 	505: 'HTTP Version not supported'
	 *
	 * @param array $response
	 * @param integer $status_code
	 * @return void
	 */
	public static function printJSON($response, $status_code=200)
	{
		http_response_code($status_code); // set the status header
		header('Content-Type: application/json');
		print json_encode_rc( $response );
		exit;
	}

 }