<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager;

use HttpClient;
use Logging;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\AccessTokenRepository;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;

class TokenValidationService 
{
    /**
     *
     * @var FhirSystem
     */
    private $fhirSystem;
    /**
     *
     * @var AccessTokenRepository
     */
    private $accessTokenRepository;

    public function __construct(FhirSystem $fhirSystem, AccessTokenRepository $accessTokenRepository)
    {
        $this->fhirSystem = $fhirSystem;
        $this->accessTokenRepository = $accessTokenRepository;
    }

    /**
     * Validate and refresh tokens, returning the first valid one.
     *
     * @param array $tokens List of tokens to validate and refresh.
     * @return FhirTokenDTO|false The first valid token or false if none are valid.
     */
    public function getFirstValidToken(array $tokens)
    {
        foreach ($tokens as $token) {
            $validToken = $this->refreshAndValidateToken($token);
            if ($validToken) {
                return $validToken;
            }
        }
        return false;
    }

    /**
     * Refresh and validate a token.
     *
     * @param FhirTokenDTO $token
     * @return FhirTokenDTO|false A valid token or false if refresh fails.
     */
    private function refreshAndValidateToken(FhirTokenDTO $token)
    {
        if ($token->isExpired() && ($refreshTokenString = $token->getRefreshToken())) {
            $refreshSuccessful = $this->refreshToken($token);
            // If the refresh failed, return false
            if (!$refreshSuccessful) return false;
        }

        // Return the token if it's valid
        return $token->isValid() ? $token : false;
    }

    /**
     * Update the token using refresh data and save it.
     *
     * @param FhirTokenDTO $token The token to be updated.
     * @return bool True if the token was successfully refreshed and saved, false otherwise.
     */
    private function refreshToken(FhirTokenDTO $token)
    {
        try {
            $refreshTokenString = $token->getRefreshToken();
            $refreshData = $this->refreshTokenFromAPI($refreshTokenString);
            $token->setAccessToken($refreshData['access_token']);
            $token->setExpirationFromSeconds($refreshData['expires_in']);
            $this->accessTokenRepository->save($token);
            return true;
        } catch (\Exception $e) {
            $code = $e->getCode();
            $invalidating_codes = [400,401];
            if(in_array($code, $invalidating_codes));
            {
                // a 400 error code stands for "invalid grant": the token is too old and no longer usable
                // 401 is forbidden
                if($token->getPatient() || $token->getMrn()) return $this->accessTokenRepository->invalidateToken($token, $this->userId);
                else {
                    $deleted = $this->accessTokenRepository->deleteByAccessToken($token, $queryString);
                    if($deleted) Logging::logEvent($queryString, 'redcap_ehr_access_token', 'FHIR', $token, '','Access token has been deleted', 'Permission denied');
                    return $deleted;
                }
            }
            Logging::logEvent(
                '',
                'redcap_ehr_access_tokens',
                'FHIR',
                '',
                '',
                'Failed to refresh the token',
                'Error: ' . $e->getMessage()
            );
            return false; // Failed to update or save the token
        }
    }

    /**
     * Refresh an access token using the refresh token.
     *
     * @param string $refreshToken
     * @return array
     */
    public function refreshTokenFromAPI(string $refreshToken): ?array
    {
        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . $this->fhirSystem->getAuthorizationHeader(),
        ];


        $result = HttpClient::request('POST', $this->fhirSystem->getFhirTokenUrl(), [
            'form_params' => $data,
            'headers' => $headers,
        ]);

        return json_decode($result->getBody(), true);
    }
}
