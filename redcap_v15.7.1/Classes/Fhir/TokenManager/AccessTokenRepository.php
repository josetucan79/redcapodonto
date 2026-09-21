<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager;

use DateTime;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystemSettingsDTO;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;

class AccessTokenRepository
{

    private function __construct() {}

    public static function instance() {
        return new self();
    }
    /**
     * Fetch all tokens for a specific user and EHR ID.
     *
     * @param int $tokenOwner
     * @param int $ehrId
     * @return FhirTokenDTO[]
     */
    public function getTokensByUserAndEhr(int $tokenOwner, int $ehrId): array
    {
        $query = "SELECT * FROM redcap_ehr_access_tokens 
                  WHERE token_owner = ? AND ehr_id = ? 
                  ORDER BY expiration DESC";
        $result = db_query($query, [$tokenOwner, $ehrId]);

        $tokens = [];
        while ($row = db_fetch_assoc($result)) {
            $tokens[] = new FhirTokenDTO($row);
        }

        return $tokens;
    }

    /**
     * Fetch the most recent valid token for a specific user and EHR ID.
     *
     * @param int $tokenOwner
     * @param int $ehrId
     * @return FhirTokenDTO|null
     */
    public function getValidToken(int $tokenOwner, int $ehrId): ?FhirTokenDTO
    {
        $now = date('Y-m-d H:i:s');
        $query = "SELECT * FROM redcap_ehr_access_tokens 
                  WHERE token_owner = ? AND ehr_id = ? 
                  AND (expiration IS NULL OR expiration > ?)
                  ORDER BY expiration DESC LIMIT 1";
        $result = db_query($query, [$tokenOwner, $ehrId, $now]);

        $row = db_fetch_assoc($result);
        return $row ? new FhirTokenDTO($row) : null;
    }

    /**
     * Store or update a token.
     *
     * @param FhirTokenDTO $token
     * @return bool
     * @throws \Exception
     */
    public function save(FhirTokenDTO $token): bool
    {
        $query = "INSERT INTO redcap_ehr_access_tokens 
                  (patient, mrn, token_owner, expiration, access_token, refresh_token, ehr_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE 
                  patient = VALUES(patient),
                  mrn = VALUES(mrn),
                  token_owner = VALUES(token_owner),
                  expiration = VALUES(expiration),
                  access_token = VALUES(access_token),
                  refresh_token = VALUES(refresh_token),
                  ehr_id = VALUES(ehr_id)";

        $params = [
            $token->getPatient(),
            $token->getMrn(),
            $token->getTokenOwner(),
            $token->getExpirationString(),
            $token->getAccessToken(),
            $token->getRefreshToken(),
            $token->getEhrId()
        ];

        if (!db_query($query, $params)) {
            throw new \Exception("Failed to save token.");
        }

        return true;
    }

    /**
     * Delete a token by its access token.
     *
     * @param string $accessToken
     * @param string $query
     * @return bool
     */
    public function deleteByAccessToken(string $accessToken, &$query=null): bool
    {
        $query = "DELETE FROM redcap_ehr_access_tokens WHERE access_token = ?";
        return db_query($query, [$accessToken]);
    }

    /**
     * Invalidate a token by setting its access and refresh tokens to NULL.
     *
     * @param FhirTokenDTO $token
     * @return bool
     */
    public function invalidateToken(FhirTokenDTO $token): bool
    {
        $accessToken = $token->getAccessToken();
        $tokenOwner = $token->getTokenOwner();
        $query = "UPDATE redcap_ehr_access_tokens 
                  SET access_token = NULL, refresh_token = NULL, expiration = NULL
                  WHERE access_token = ? AND token_owner = ?";
        return db_query($query, [$accessToken, $tokenOwner]);
    }

    /**
     * Delete all tokens associated with a specific patient.
     *
     * @param string $patientId
     * @param int $ehrId
     * @return bool
     */
    public function deleteTokensByPatient(string $patientId, int $ehrId): bool
    {
        $query = "DELETE FROM redcap_ehr_access_tokens WHERE patient = ? AND ehr_id = ?";
        return db_query($query, [$patientId, $ehrId]);
    }

    /**
     * Get all users with tokens for a specific EHR ID.
     *
     * @param int $ehrId
     * @return array
     */
    public function getUsersByEhrId(int $ehrId): array
    {
        $query = "SELECT DISTINCT token_owner FROM redcap_ehr_access_tokens WHERE ehr_id = ?";
        $result = db_query($query, [$ehrId]);

        $users = [];
        while ($row = db_fetch_assoc($result)) {
            $users[] = $row['token_owner'];
        }

        return $users;
    }

    /**
     * Retrieves valid tokens for the specified EHR system, filtered by token owners and expiration.
     *
     * Filters tokens based on expiration logic and orders the results:
     * 1. By patient-specific tokens
     * 2. By relevance to token owner
     * 3. By expiration date in descending order
     * @param int $ehrId The EHR system ID.
     * @param string $currentDate The current date and time for expiration filtering.
     * @param array|null $tokenOwners Optional list of token owners to restrict the query.
     * @param string|null $patientId Optional patient ID for prioritization.
     * @return array a list of FhirTokenDTO.
     */
    public function getValidTokens(int $ehrId, string $currentDate, ?array $tokenOwners = null, ?string $patientId = null): array
    {
        $params = [
            $ehrId,
            $currentDate,
            $currentDate,
        ];

        $query = "SELECT * FROM redcap_ehr_access_tokens
                WHERE ehr_id = ?
                AND (
                    (access_token IS NOT NULL AND expiration > ?)
                    OR
                    (refresh_token IS NOT NULL AND expiration > DATE_SUB(?, INTERVAL 30 DAY))
                )";

        // Restrict by token owners if specified
        if (!empty($tokenOwners)) {
            $placeholders = dbQueryGeneratePlaceholdersForArray($tokenOwners);
            $query .= "\nAND token_owner IN ($placeholders)";
            $params = array_merge($params, $tokenOwners);
        }

        // Add patient-specific and expiration-based ordering
        if ($patientId) {
            $query .= "\nORDER BY FIELD(patient, ?) DESC, expiration DESC";
            $params[] = $patientId;
        } else {
            $query .= "\nORDER BY expiration DESC";
        }

        $result = db_query($query, $params);

        $tokens = [];
        while ($row = db_fetch_assoc($result)) {
            $tokens[] = new FhirTokenDTO($row); // Add each token to the result array
        }

        return $tokens; // Return all tokens in the order of the query
    }

    public function cachePatientId($ehr_id, $mrn, $fhir_id, &$query_string = null)
    {
        $query_string = 'INSERT INTO `redcap_ehr_access_tokens` (`mrn`, `patient`, `ehr_id`) VALUES (?, ?, ?)';
        return db_query($query_string, [$mrn, $fhir_id, $ehr_id]);
    }

    /**
     * If there is an institution-specific MRN, then store in access token table to pair it with the patient id
     *
     * @param string $patient
     * @param string $mrn
     * @return void
     */
	public function storePatientMrn($ehr_id, $patient, $mrn)
	{
		$query_string = "UPDATE redcap_ehr_access_tokens SET mrn = ?
				        WHERE patient=? AND ehr_id = ?";
        $params = [$mrn, $patient, $ehr_id];
		return db_query($query_string, $params);
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
    public function removeOrphanedMrns($ehr_id, $token_owner, $mrn, $patient_id)
    {
        $query_string = 
            "DELETE FROM redcap_ehr_access_tokens 
            WHERE ehr_id = ? AND
            token_owner = ? AND
            mrn = ? AND
            patient != ?";
        return db_query($query_string, [$ehr_id, $token_owner, $mrn, $patient_id]);
    }

    public function getTotalExpiredTokensPerEhrSystem($currentDate=null) {
        $currentDate = $currentDate ?? new DateTime('now');
        $query = "SELECT s.ehr_id, ehr_name,
                COUNT(t.access_token) AS expired_token_count
        FROM redcap_ehr_settings s
        LEFT JOIN redcap_ehr_access_tokens t
        ON s.ehr_id = t.ehr_id
            AND t.access_token IS NOT NULL
            AND t.expiration IS NOT NULL
            AND t.expiration < DATE_SUB(?, INTERVAL 30 DAY)
        GROUP BY s.ehr_id
        ORDER BY s.order;";
        $result = db_query($query, [$currentDate]);
        $rows = [];
        while ($row = db_fetch_assoc($result)) {
            $rows[] = $row; // Add each token to the result array
        }
        return $rows;
    }

    public function getExpiredTokens($currentDate=null, $ehr_id = null) {
        $currentDate = $currentDate ?? new DateTime('now');
        $params = [$currentDate];
        $query = "SELECT * FROM redcap_ehr_access_tokens
         WHERE access_token IS NOT NULL
           AND expiration IS NOT NULL
           AND expiration < DATE_SUB(?, INTERVAL 30 DAY)
           ORDER BY ehr_id, expiration";
        if($ehr_id) {
            $query .= "\nAND ehr_id = ?";
            $params[] = $ehr_id;
        }
        $result = db_query($query, $params);
        $tokens = [];
        while ($row = db_fetch_assoc($result)) {
            $tokens[] = new FhirTokenDTO($row); // Add each token to the result array
        }
        return $tokens;
    }

    public function deleteExpiredTokens($currentDate=null, $ehr_id = null) {
        $currentDate = $currentDate ?? new DateTime('now');
        $params = [$currentDate];
        $query = "DELETE FROM redcap_ehr_access_tokens
         WHERE access_token IS NOT NULL
           AND expiration IS NOT NULL
           AND expiration < DATE_SUB(?, INTERVAL 30 DAY)";
        if($ehr_id) {
            $query .= "\nAND ehr_id = ?";
            $params[] = $ehr_id;
        }
        $result = db_query($query, $params);
        return db_affected_rows();
    }

    public function invalidateExpiredTokens($currentDate = null, $ehr_id = null) {
        $currentDate = $currentDate ?? new DateTime('now');
        $params = [$currentDate];
        $query = "UPDATE redcap_ehr_access_tokens
            SET access_token = NULL,
                refresh_token = NULL
            WHERE patient IS NOT NULL
                AND mrn IS NOT NULL
                AND access_token IS NOT NULL
                AND expiration IS NOT NULL
                AND expiration < DATE_SUB(?, INTERVAL 30 DAY)
        ";
        if($ehr_id) {
            $query .= "\nAND ehr_id = ?";
            $params[] = $ehr_id;
        }
        $result = db_query($query, $params);
        return db_affected_rows();
    }

}
