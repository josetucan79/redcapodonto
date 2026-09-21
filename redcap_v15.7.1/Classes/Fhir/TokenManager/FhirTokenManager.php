<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager;

use Exception;
use HttpClient;
use Logging;
use SplObserver;
use User;
use UserRights;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\FhirRequest;
use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirClientResponse;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\AccessTokenRepository;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\TokenSelectionContext;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\TokenSelectorInterface;
use Vanderbilt\REDCap\Classes\Utility\TransactionHelper;

class FhirTokenManager implements SplObserver
{
    /**
     *
     * @var string
     */
    private $patientId;
    private $accessTokenRepository;

    /**
     *
     * @param FhirSystem $fhirSystem
     * @param TokenSelectorInterface $tokenSelector
     * @param integer |null $userId
     * @param integer|null $project_id
     */
    public function __construct(
        private FhirSystem $fhirSystem,
        private TokenSelectorInterface $tokenSelector,
        private ?int $userId = null,
        private ?int $project_id = null
    ) {
        $this->accessTokenRepository = AccessTokenRepository::instance();
    }
    
    public function getFhirSystem(): ?FhirSystem { return $this->fhirSystem; }
    public function getUserId(): ?int { return $this->userId; }
    public function getPatientId(): ?string { return $this->patientId; }
    public function getProjectId(): ?int { return $this->project_id; }
    public function setPatientId(string $patientId): void { $this->patientId = $patientId; }

    /**
     * Retrieve and filter tokens for the specified users and for a specific FHIR system
     *
     * @param int $ehrID
     * @param array $users
     * @return FhirTokenDTO[]
     */
    public function getUsersTokens($users) {
        if(!$this->fhirSystem instanceof FhirSystem) return [];
        $ehrId = $this->fhirSystem->getEhrId();
        $currentDate = date('Y-m-d H:i:s');
        $projectUsers = $this->getProjectUsers();
        // always make sure the users are in the project
        $filteredUsers = array_intersect($users, $projectUsers);

        $tokens = $this->accessTokenRepository->getValidTokens($ehrId, $currentDate, $filteredUsers, $this->patientId);
        array_walk($tokens, function(FhirTokenDTO $token) {
            $token->isValid() ? 
                $token->setStatus(FhirTokenDTO::STATUS_VALID) :
                $token->setStatus(FhirTokenDTO::STATUS_EXPIRED);
        });
        $context = new TokenSelectionContext(
            $this->getProjectId(),
            $filteredUsers,
            $tokens,
            $this->getPatientId()
        );
        $selectedTokens = $this->tokenSelector->selectToken($context);
        return $selectedTokens;
    }
    /**
     * Retrieves a valid access token, applying rules and logic based on whether
     *
     * @return FhirTokenDTO|false A valid access token or false if none is available.
     */
    public function getToken()
    {
        if ($this->userId) {
            $users = [$this->userId];
        }else {
            $users = $this->getProjectUsers();
        }
        $selectedTokens = $this->getUsersTokens($users);
        return $this->getFirstValidToken($selectedTokens);
    }

    public function getFirstToken()
    {
        if ($this->userId) {
            $users = [$this->userId];
        }else {
            $users = $this->getProjectUsers();
        }
        $selectedTokens = $this->getUsersTokens($users);
        $firstValidToken = $this->getFirstValidToken($selectedTokens);
        if($firstValidToken instanceof FhirTokenDTO) return $firstValidToken;
        return $selectedTokens[0] ?? null;
    }


    /**
     * Validate and refresh tokens, returning the first valid one.
     *
     * @param FhirTokenDTO[] $tokens List of tokens to validate and refresh.
     * @return FhirTokenDTO|false The first valid token or false if none are valid.
     */
    public function getFirstValidToken(array $tokens)
    {
        $validStatuses = [FhirTokenDTO::STATUS_VALID, FhirTokenDTO::STATUS_EXPIRED];
        foreach ($tokens as $token) {
            // Only process tokens that are currently valid or expired
            if (!in_array($token->getStatus(), $validStatuses)) continue;
            
            $this->refreshTokenIfExpired($token);

            // If the token remains valid after refresh, return it
            if ($token->isValid()) {
                $token->setStatus(FhirTokenDTO::STATUS_VALID);
                return $token;
            }

            // Mark token as invalid if it doesn't pass validation
            $token->setStatus(FhirTokenDTO::STATUS_INVALID);
        }

        // No valid token found
        return false;
    }

    /**
     * Refresh a token if it is expired and has a refresh token.
     *
     * @param FhirTokenDTO $token
     * @return void
     */
    private function refreshTokenIfExpired(FhirTokenDTO $token): void
    {
        if ($token->isExpired() && $token->getRefreshToken()) {
            $this->refreshToken($token);
        }
    }

    public function getAccessToken(): ?string
    {
        $token = $this->getToken();
        return $token ? $token->getAccessToken() : null;
    }

    /**
     * Update the token using refresh data and save it.
     *
     * @param FhirTokenDTO $token The token to be updated.
     * @return bool True if the token was successfully refreshed and saved, false otherwise.
     */
    public function refreshToken(FhirTokenDTO $token)
    {
        try {
            $refreshTokenString = $token->getRefreshToken();
            $refreshData = $this->refreshTokenFromAPI($refreshTokenString);
            $accessToken = $refreshData['access_token'] ?? null;
            $expiresIn = $refreshData['expires_in'] ?? null;
            if(!$accessToken || !$expiresIn) return false;
            $token->setAccessToken($accessToken);
            $token->setExpirationFromSeconds($expiresIn);
            $this->accessTokenRepository->save($token);
            return true;
        } catch (\Exception $e) {
            $code = $e->getCode();
            $invalidating_codes = [400,401];
            if(in_array($code, $invalidating_codes));
            {
                // a 400 error code stands for "invalid grant": the token is too old and no longer usable
                // 401 is forbidden
                if($token->getPatient() || $token->getMrn()) $this->invalidateToken($token);
                else $this->deleteAccessToken($token);
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

    /**
     * TODO: make sure the user is a SUPER user or can adjudicate
     *
     * @return array
     */
    public function getProjectUsers(): array
    {
        if(!$this->project_id) return [];
        $projectsPrivileges = UserRights::getPrivileges($this->project_id);
        $projectPrivileges = $projectsPrivileges[$this->project_id] ?? [];
        foreach ($projectPrivileges as $username => $privileges) {
            $userInfo = User::getUserInfo($username);
            $userID = User::getUIIDByUsername($username);
            $canMap = boolval($privileges['realtime_webservice_mapping'] ?? 0);
            $canAdjudicate = boolval($privileges['realtime_webservice_adjudicate'] ?? 0);
            # code...
            $user_ids[] = $userID;
        }
        return $user_ids;
    }

    public function deleteAccessToken(string $accessToken): bool
    {
        $deleted = $this->accessTokenRepository->deleteByAccessToken($accessToken, $queryString);
        if($deleted) Logging::logEvent($queryString, 'redcap_ehr_access_token', 'FHIR', $accessToken, '','Access token has been deleted', 'Permission denied');
        return $deleted;
    }

    public function invalidateToken(FhirTokenDTO $token): bool
    {
        return $this->accessTokenRepository->invalidateToken($token, $this->userId);
    }

     // If there is an institution-specific MRN, then store in access token table to pair it with the patient id
    /**
     * Undocumented function
     *
     * @param string $patient
     * @param string $mrn
     * @return void
     */
	public function storePatientMrn($patient, $mrn)
	{
	    if (empty($mrn)) return false;
        $ehr_id = $this->fhirSystem->getEhrId();
		return $this->accessTokenRepository->storePatientMrn($ehr_id, $patient, $mrn);
    }
    
    /**
     * cleanup MRN entries for a user
     * 
     * the table could contain orphaned MRNs 
     * if the FHIR ID changes for any reason (i.e. EHR updates)
     *
     * @param integer $user_id token owner
     * @param string $mrn
     * @param string $patient_id
     * @return boolean
     */
    public function removeOrphanedMrns($mrn, $patient_id)
    {
        if(!$user_id = $this->getUserId()) return;
        $ehr_id = $this->fhirSystem->getEhrId();
        return $this->accessTokenRepository->removeOrphanedMrns($ehr_id, $user_id, $mrn, $patient_id);
    }

    public function removeCachedPatient(string $patientId): bool
    {
        return $this->accessTokenRepository->deleteTokensByPatient($patientId, $this->fhirSystem->getEhrId());
    }

    /**
	 * react to notifications (from the FHIR client)
	 *
	 * @param SplSubject $subject
	 * @param string $event
	 * @param mixed $data
	 * @return void
	 */
	public function update($subject, ?string $event = null, $data = null): void
	{
        $updateFhirClient = function() use($subject, $event, $data) {
            switch ($event) {
                case FhirClient::NOTIFICATION_PATIENT_IDENTIFIED:
                    $this->handlePatientIdentified($data);
                    break;
                case FhirClient::NOTIFICATION_RESOURCE_ERROR:
                    if(!($subject instanceof FhirClient)) break;
                    /** @var FhirClientResponse $data  */
                    $this->handleResourceError($data);
                    break;
                default:
                    break;
            }
        };
        
        if($subject instanceof FhirClient) $updateFhirClient();
	}


    /**
     * cache the FHIR ID when a patient is identified
     *
     * @param array $data
     * @return void
     */
    private function handlePatientIdentified($data)
    {
        $mrn = $data['mrn'] ?? '';
        $fhir_id = $data['fhir_id'] ?? '';
        if(empty($mrn) || empty($fhir_id)) return;

        $ehr_id = $this->fhirSystem->getEhrId();
        $result = $this->accessTokenRepository->cachePatientId($ehr_id, $mrn, $fhir_id, $query_string);
        if($result) Logging::logEvent(
            $sql = $query_string,
            $table = 'redcap_ehr_access_token',
            $event = 'FHIR',
            $record = '',
            $display = json_encode(compact('fhir_id', 'mrn'), JSON_PRETTY_PRINT),
            $descrip = 'Patient FHIR ID has been cached',
            $change_reason = 'Patient identified',
            $userid_override = "",
            $project_id_override = 0,
            $useNOW = true,
            $event_id_override = null,
            $instance = null,
            $bulkProcessing = false
        );
    }

    /**
     * perform actions when errors are detected
     * (e.g. delete access token if access is forbidden)
     * @param FhirClientResponse $fhirClientResponse
     * @return void
     */
    private function handleResourceError($fhirClientResponse)
    {
        $error = $fhirClientResponse->getError();
        if(!$error instanceof Exception) return;
        $code = $error->getCode();
        switch ($code) {
            // delete identifier if 'Wrong format' or 'not found'
            case '400':
            case '404':
                $request = $fhirClientResponse->getRequest();
                if(!($request instanceof FhirRequest)) break;
                $identifier = $request->extractIdentifier();
                if($identifier) $this->removeCachedPatient($identifier);
                break;
            // delete access token if access is forbidden
            case '401':
                $accessToken = $fhirClientResponse->getAccessToken();
                if($accessToken) $deleted = $this->deleteAccessToken($accessToken);
                break;
            default:
                # code...
                break;
        }
    }

    /**
     * persist a token to the database
     *
     * @param array $token_data
     * @param integer $user_id
     * @return FhirToken
     */
    public static function storeToken($token_data)
    {
        $token = new FhirTokenDTO();
        $token->setAccessToken($token_data['access_token'] ?? null);
        $token->setRefreshToken($token_data['refresh_token'] ?? null);
        $token->setExpirationFromSeconds($token_data['expires_in'] ?? 0);
        $token->setPatient($token_data['patient'] ?? null);
        $token->setTokenOwner($token_data['token_owner'] ?? null);
        $token->setEhrId($token_data['ehr_id'] ?? null);
        $accessTokenRepository = AccessTokenRepository::instance();
        $accessTokenRepository->save($token);
        return $token;
    }

        /**
     * Collects FHIR access tokens for specified users in a project. It retrieves the FHIR system based on the project ID and 
     * iterates through the usernames to fetch their tokens.
     * If the FHIR system is unavailable, the token for that user is set to false.
     *
     * @param int $project_id The ID of the project.
     * @param array $usernames Usernames for token collection.
     * @return array Associative array with usernames as keys and FHIR access tokens (or false) as values.
     */
    public static function getAccessTokensForUsersinProject($project_id) {
        $privileges = UserRights::getPrivileges($project_id);
        $usernames = array_keys($privileges[$project_id] ?? []);
        $fhirSystem = FhirSystem::fromProjectId($project_id);
        $fhirTokens = [];
        foreach ($usernames as $username) {
            if(!$fhirSystem) {
                $fhirTokens[$username] = false;
                continue;
            }
            $user_id = User::getUIIDByUsername($username);
            $tokenManager = FhirTokenManagerFactory::create($fhirSystem, $user_id, $project_id);
            $token = $tokenManager->getFirstToken();
            $fhirTokens[$username] = $token;
        }
        return $fhirTokens;
    }

    public static function clearExpiredTokens($ehr_id = null) {
        try {
            TransactionHelper::beginTransaction();
            $accessTokenRepository = AccessTokenRepository::instance();
            $totalInvalidated = $accessTokenRepository->invalidateExpiredTokens(null, $ehr_id);
            $totalDeleted = $accessTokenRepository->deleteExpiredTokens(null, $ehr_id);
            TransactionHelper::commitTransaction();
            return [$totalInvalidated, $totalDeleted];
        } catch (\Exception $e) {
            TransactionHelper::rollbackTransaction();
            throw $e;
        }
    }
}
