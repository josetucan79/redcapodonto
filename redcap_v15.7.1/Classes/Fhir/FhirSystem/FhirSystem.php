<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirSystem;

use Exception;

class FhirSystem
{
    private $ehr_id;

    /**
     * @var FhirSystemSettingsDTO
     */
    private $settings;

    public function __construct($ehr_id) {
        $this->ehr_id = $ehr_id;
        $this->settings = $settings = $this->fetchSettingsFromDatabase();
        if(!$settings instanceof FhirSystemSettingsDTO) {
            throw new \Exception(
                "No FHIR system entry exists for the provided ID: $ehr_id.
                Please verify the ID is correct and try again", 1
            );
        }
    }

    /**
     *
     * This method attempts to fetch the EHR ID from the `redcap_projects` table for the specified
     * project ID. If no EHR ID is associated with the project or the project ID doesn't exist,
     * the method falls back to retrieving the default EHR ID from the `redcap_ehr_settings` table.
     *
     * @param mixed $project_id The project ID to search for in the `redcap_projects` table. Can be NULL.
     * @return self|null Returns an instance of the class with the EHR ID set if found,
     *                   or null if no EHR ID could be retrieved.
     */
    public static function fromProjectId($project_id) {
        $queryString = "SELECT IF(rp.ehr_id IS NOT NULL, rp.ehr_id, res.ehr_id) AS ehr_id
                        FROM (SELECT ehr_id FROM redcap_ehr_settings ORDER BY `order` LIMIT 1) res
                        LEFT JOIN redcap_projects rp ON rp.project_id <=> ?";

        $result = db_query($queryString, [$project_id]);
        if (!$result) return null;
    
        $row = db_fetch_assoc($result);
        if (!$row || !$row['ehr_id']) return null;
    
        return new self($row['ehr_id']);
    }

    /**
     * Retrieves the EHR settings for a given ISS URL.
     * 
     * This method attempts to find a single EHR setting entry in the database
     * where the 'fhir_base_url' matches the given URL after normalization (trailing slashes removed).
     * It ensures that the query returns exactly one result to avoid ambiguity. If the query does not return
     * exactly one result or if the EHR ID is not found or is empty, the method will return null.
     * 
     * @param string $url The FHIR base URL to search for in the `redcap_ehr_settings` table.
     * @return self|null Returns an instance of the class with the EHR ID set if found and unique, otherwise null.
     */
    public static function fromIss($url) {
        // Normalize the URL by removing trailing slashes
        $normalizedUrl = rtrim($url, '/') . '%';

        $queryString = "SELECT s1.*
        FROM `redcap_ehr_settings` s1
        WHERE s1.`fhir_base_url` LIKE ?
        ORDER BY s1.`order` ASC";
        $result = db_query($queryString, [$normalizedUrl]);
        // Check for errors in query execution or if result set is ambiguous
        if (!$result || db_num_rows($result) !== 1) return null;
    
        $row = db_fetch_assoc($result);
        if (!$row || empty($row['ehr_id'])) return null;
    
        return new self($row['ehr_id']);
    }
    
    /**
     * Retrieves a collection of FhirSystemSettingsDTO objects from the 'redcap_ehr_settings' table.
     *
     * This method performs a database query to fetch all records from the 'redcap_ehr_settings' table and
     * creates an FhirSystemSettingsDTO object for each record. It collects these objects into an array and returns them.
     * The method assumes that the FhirSystemSettingsDTO class has a constructor that accepts an array of EHR settings data.
     *
     * @return FhirSystemSettingsDTO[] An array of FhirSystemSettingsDTO objects representing the EHR settings.
     */
    public static function getEhrSystems() {
        $queryString = "SELECT * FROM redcap_ehr_settings ORDER BY `order` ASC";
        $result = db_query($queryString);
        $systems = [];
        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $systems[] = new FhirSystemSettingsDTO($row);
            }
        }
        return $systems;
    }

    public static function getDefault() {
        $queryString = "SELECT ehr_id FROM redcap_ehr_settings ORDER BY `order` ASC LIMIT 1";
        $result = db_query($queryString);
        if ($result && ($row = db_fetch_assoc($result))) {
            $ehr_id = $row['ehr_id'] ?? null;
            if(!$ehr_id) return;
            return new self($ehr_id);
        }
        return null;
    }
    

    public function getSettings() {
        if ($this->settings === null) {
            // Settings are not set, fetch from the database
            $this->settings = $this->fetchSettingsFromDatabase();
        }
        return $this->settings;
    }

    public function fetchSettingsFromDatabase() {
        $queryString = "SELECT * FROM redcap_ehr_settings WHERE ehr_id = ?";
        $result = db_query($queryString, [$this->ehr_id]);
        if($row = db_fetch_assoc($result)) return new FhirSystemSettingsDTO($row);
    }

    /**
     * Fetches the Conformance Statement from a FHIR server.
     *
     * Retrieves the Conformance (or CapabilityStatement) from the FHIR base URL of this instance.
     * It ensures the base URL does not have trailing slashes before appending '/metadata' to form
     * the URL for the Conformance Statement. The response is expected to be in JSON format.
     *
     * @return array|null The decoded Conformance Statement in associative array format if successful, or null if not.
     * @throws \Exception If no valid data is received.
     */
    public function fetchConformanceStatement() {
		$removeTrailingSlashes = function($string) {
			return preg_replace('/\/*$/', '',$string);
		};
		$fhirBaseUrl = $removeTrailingSlashes($this->getFhirBaseUrl());
		$conformanceStatementURL = "$fhirBaseUrl/metadata";

        try {
            $response = \HttpClient::request('GET', $conformanceStatementURL, ['headers' => ['Accept' => 'application/json']]);
            $data = json_decode($response->getBody(), true);
            if(!$data) throw new \Exception("no valid data received", 400);
            return $data;
        } catch (\Throwable $th) {
            throw new \Exception("Could not fetch the Conformance Statement from $conformanceStatementURL");
        }
	}

    /**
     * get the authorization header to use in requests
     *
     * @return string
     */
    public function getAuthorizationHeader()
	{
		return base64_encode("{$this->getClientId()}:{$this->getClientSecret()}");
	}

    public function getEhrId() {return $this->getSettings()->ehr_id ?? null; }
    public function getOrder() {return $this->getSettings()->order ?? null; }
    public function getEhrName() {return $this->getSettings()->ehr_name ?? null; }
    public function getClientId() {return $this->getSettings()->client_id ?? null; }
    public function getClientSecret() {return $this->getSettings()->client_secret ?? null; }
    public function getFhirBaseUrl() {return $this->getSettings()->fhir_base_url ?? null; }
    public function getFhirTokenUrl() {return $this->getSettings()->fhir_token_url ?? null; }
    public function getFhirAuthorizeUrl() {return $this->getSettings()->fhir_authorize_url ?? null; }
    public function getFhirIdentityProvider() {return $this->getSettings()->fhir_identity_provider ?? null; }
    public function getPatientIdentifierString() {return $this->getSettings()->patient_identifier_string ?? null; }
}