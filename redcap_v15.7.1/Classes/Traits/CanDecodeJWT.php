<?php
namespace Vanderbilt\REDCap\Classes\Traits;

trait CanDecodeJWT {

    /**
     * transform a JWT token into an associative array
	 *
     * @param string $token
     * @return array
	 */
    public function decodeJWT($token) {
        $tokenArray = explode('.', $token ?? '');
        $base64Url = str_replace('-', '+', $tokenArray[1] ?? '');
		$base64 = str_replace('_', '/', $base64Url);
		$jsonPayload = base64_decode($base64);
		$jsonDecoded = json_decode($jsonPayload, true);
        return $jsonDecoded ?? [];
	}
}
