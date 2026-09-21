<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass;

use DateTime;
use Exception;
use Language;
use SplObserver;
use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;
use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\DTOs\REDCapProjectDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\BreakTheGlass\DTOs\AcceptDTO;
use Vanderbilt\REDCap\Classes\BreakTheGlass\DTOs\ResultDTO;
use Vanderbilt\REDCap\Classes\Fhir\Facades\FhirClientFacade;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Patient;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManager;
use Vanderbilt\REDCap\Classes\BreakTheGlass\DTOs\ProtectedPatientDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirClientResponse;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\OperationOutcome;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManagerFactory;

class GlassBreaker implements SplObserver
{
    /**
     * authorization modes (REDCap setting)
     * 
     * disabled: Break the glass is disabled 
     * enabled: Use an OAuth2 access token and the standard FHIR base URL
     */
    const AUTHORIZATION_MODE_DISABLED = 'disabled';
    const AUTHORIZATION_MODE_ENABLED = 'enabled';

    /**
     *
     * @var FhirSystem
     */
    private $fhirSystem;

    private $project_id;

    private $userID;

    private $ehrUSER;

    /**
     *
     * @var REDCapConfigDTO
     */
    private $redcapConfig;


    /**
     * the API class
     *
     * @var API
     */
    public $api;

    private $protectedPatientManager;

    /**
     *
     * @param int $project_id
     * @param integer $userID
     */
    public function __construct($project_id, $userID)
    {
        $this->project_id = $project_id;
        $this->fhirSystem = FhirSystem::fromProjectId($project_id);
        $this->userID = $userID;
        $this->ehrUSER = static::getEhrMappedUsername();
        
        $this->redcapConfig = $config = REDCapConfigDTO::fromDB();
        $fhirBaseUrl = $this->fhirSystem->getFhirBaseUrl();
        $epicClientID = $config->fhir_client_id;
        $this->api = new API($fhirBaseUrl, $epicClientID);
        $this->protectedPatientManager = new ProtectedPatientManager($project_id);
    }

    /**
     *
     * @param int $project_id
     * @param int $user_id
     * @return GlassBreaker
     */
    public static function forProjectAndUser($project_id, $user_id) {
        return new GlassBreaker($project_id, $user_id);
    }


    /**
     * provide settings for the forntend app
     *
     * @return void
     */
    public function getSettings() {
        $settings = [
            'initialize' => $this->initialize(),
            'ehrUser' => $this->ehrUSER,
            'userTypes' => BreakTheGlassTypes::userTypes(),
            'userType' => $this->redcapConfig->fhir_break_the_glass_ehr_usertype,
            'translations' => $this->getTranslations(),
        ];
        return $settings;
    }

    private function getTranslations() {
        $translations = [
            'break_glass_field_patients'                => Language::tt('break_glass_field_patients'),
            'break_glass_field_patients_description'    => Language::tt('break_glass_field_patients_description'),
            'break_glass_field_reason'                  => Language::tt('break_glass_field_reason'),
            'break_glass_field_reason_description'      => Language::tt('break_glass_field_reason_description'),
            'break_glass_field_explanation'             => Language::tt('break_glass_field_explanation'),
            'break_glass_field_explanation_description' => Language::tt('break_glass_field_explanation_description'),
            'break_glass_field_user'                    => Language::tt('break_glass_field_user'),
            'break_glass_field_user_description'        => Language::tt('break_glass_field_user_description'),
            'break_glass_field_user_type'               => Language::tt('break_glass_field_user_type'),
            'break_glass_field_user_type_description'   => Language::tt('break_glass_field_user_type_description'),
            'break_glass_field_password'                => Language::tt('break_glass_field_password'),
            'break_glass_field_password_description'    => Language::tt('break_glass_field_password_description'),
        ];
        return $translations;
    }

    /**
     * return if the Glass Breaker is enabled in the system
     *
     * @return boolean
     */
    public static function isSystemEnabled()
    {
        $config = REDCapConfigDTO::fromDB();
        $enabled = $config->fhir_break_the_glass_enabled ?? '';
        return strcasecmp($enabled, self::AUTHORIZATION_MODE_ENABLED)===0;
    }

    /**
     * check if break the glass can be enabled
     * in a project
     *
     * @return boolean
     */
    public static function isAvailable($project_id)
    {
        if(!isinteger($project_id)) return false;
        if(!self::isSystemEnabled()) return false;
        // continue only if the project is FHIR enabled
        if(!FhirEhr::isFhirEnabledInProject($project_id)) return false;
        return true;
    }
    
    public static function isEnabled($project_id)
    {
        if(!self::isSystemEnabled()) return false;
        $projectConfig = REDCapProjectDTO::fromProjectID($project_id);
        return $projectConfig->break_the_glass_enabled==1;
    }

    /**
     * getter for the authorization mode
     *
     * @return string
     */
    private function getAccessToken()
    {
        $token_manager = FhirTokenManagerFactory::create($this->fhirSystem, $this->userID, $this->project_id);
        $access_token = $token_manager->getAccessToken();
        return $access_token;
    }

    /**
     * get the initialize data from the EHR system
     * data is cached to minimize calls
     *
     * @return array
     */
    public function initialize() {
        $fhirBaseUrl = $this->fhirSystem->getFhirBaseUrl();
        $fileCache = new FileCache(__CLASS__);
        $key = "initialize_$fhirBaseUrl";
        $cached = $fileCache->get($key);
        if($cached) {
            $decoded = json_decode($cached, true);
            if($decoded) return $decoded;
            else $fileCache->delete($key); // delete data since cannot be decoded
        }
        $accessToken = $this->getAccessToken();
        if($accessToken===false) return;
        $response = $this->api->initialize($accessToken);
        $fileCache->set($key, json_encode($response, true), 60*60); // save to cache
        return $response;
    }

    public function accept($mrns, $params) {
        $results = [];
        
        $list = $this->getProtectedMrnList();
        foreach($mrns as $mrn) {
            try {
                $patientDTO = $list[$mrn] ?? false;
                if(!$patientDTO) {
                    // an MRN must be registered in the cache with an associated FHIR btg token
                    $results[] = $result = new ResultDTO([
                        'mrn' => $mrn,
                        'status' => ResultDTO::STATUS_SKIPPED,
                        'details' => 'the provided MRN was not found in the cache',
                    ]);
                    $this->logResult($result);
                    continue;
                }
                if($patientDTO->isExpired()) {
                    // try to get a fresh token
                    $fhirBtgToken = $this->refreshBTGToken($patientDTO);
                    $patientDTO->fhirBtgToken = $fhirBtgToken;
                }
                $acceptDTO = AcceptDTO::fromArray($params);
                $acceptDTO->FhirBTGToken = $patientDTO->fhirBtgToken;
                $acceptDTO->UserID = $this->ehrUSER; // use the one associated to the current user
                
                $this->validateAcceptParameters($acceptDTO);

                $response = $this->acceptSingle($acceptDTO);
                $result = new ResultDTO([
                    'mrn' => $mrn,
                    'status' => ResultDTO::STATUS_ACCEPTED,
                    'details' => $response,
                ]);
                $results[] = $result;
                $this->logResult($result);
                // remove only on success
                $this->removeProtectedPatient($mrn);
            } catch (\Throwable $th) {
                
                $results[] = $result = new ResultDTO([
                    'mrn' => $mrn,
                    'status' => ResultDTO::STATUS_NOT_ACCEPTED,
                    'details' => $th->getMessage(),
                ]);
                $this->logResult($result);
            }
        }
        return $results;
    }

    /**
     * Logs the result of the MRN processing.
     *
     * @param ResultDTO $result
     */
    private function logResult(ResultDTO $result) {
        \Logging::logEvent(
            $sql = "",
            $object_type = "redcap_glass_breaker",
            $event = "MANAGE",
            $pk = $result->mrn,
            $data_values = json_encode([
                'action' => 'accept',
                'result' => $result,
            ], JSON_PRETTY_PRINT),
            $change_reason = "Break the glass"
        );
    }

    /**
     *
     * @param AcceptDTO $acceptDTO
     * @return mixed
     */
    private function acceptSingle(AcceptDTO $acceptDTO) {
        $accessToken = $this->getAccessToken();
        return $this->api->accept($accessToken, $acceptDTO);
    }


    /**
     * Validate parameters for acceptance.
     *
     * @param AcceptDTO $acceptDTO
     * @throws Exception
     */
    private function validateAcceptParameters(AcceptDTO $acceptDTO)
    {
        $errors = [];
        if (empty($acceptDTO->FhirBTGToken)) $errors[] = "A FHIR BTG token is required.";
        if (empty($acceptDTO->UserID)) $errors[] = "An EHR user is required.";
        if (empty($acceptDTO->UserIDType)) $errors[] = "An EHR user type is required.";
        
        if (!empty($errors)) {
            throw new Exception("Errors: " . implode("\n ", $errors), 400);
        }
    }

    /**
     *
     * @param ProtectedPatientDTO $patientDTO
     * @return string the refreshed BTG token
     */
    public function refreshBTGToken($patientDTO)
    {
        $fhirClient = FhirClientFacade::getInstance($this->fhirSystem, $this->project_id, $this->userID);
        $mrn = $patientDTO->mrn;
        $patient_id = $fhirClient->getPatientID($mrn);
        if($patient_id===false) throw new Exception("Cannot retrieve the patient ID form MRN '$mrn'", 404);
        
        $request = $fhirClient->makeRequest(FhirCategory::DEMOGRAPHICS, $patient_id, AbstractEndpoint::INTERACTION_READ);
        
        $response = new FhirClientResponse([
            'mrn' => $mrn,
            'project_id' => $this->project_id,
            'user_id' => $this->userID,
            'request' => $request,
            'patient_id' => $patient_id,
        ]);

        $response = $fhirClient->sendRequest($request, $response);
        $resource = $response->getResource();
        if(is_null($resource)) throw new Exception("No resurce was returned by the EHR system '$mrn'", 404);
        if($resource instanceof Patient) throw new Exception("Break the glass is not necessary: accesss to patient is granted.", 400);
        // nothing to refresh, access is granted
        if(!($resource instanceof OperationOutcome)) throw new Exception("Cannot process the resource provided by the EHR system: expected OperationOutcome, but received ".get_class($resource), 400);
        
        $fhirBtgToken = $resource->getFhirBgtToken();
        if(!$fhirBtgToken) throw new Exception("No BTG token was found in the response; cannot proceed", 400);
        // save the updated data
        $this->storeProtectedPatient($mrn, $fhirBtgToken);
        // retrieve the updated data
        return $fhirBtgToken;
    }

    /**
     * react to notifications (from the FHIR client)
     *
     * @param SplSubject $subject
     * @param string $event
     * @param mixed $data
     * @return void
     */
    public function update($fhirClient, $event = null, $data = null): void
    {
        if(!($fhirClient instanceof FhirClient)) return;

        switch ($event) {
            case FhirClient::NOTIFICATION_ENTRY_RECEIVED:
                /** @var AbstractResource $resource */
                $resource = $data;
                if( !($resource instanceof OperationOutcome) ) break;

                $mrn = $fhirClient->getMrn();
                $fhirBtgToken = $resource->getFhirBgtToken();
                if(!$fhirBtgToken) break;
                $this->storeProtectedPatient($mrn, $fhirBtgToken);
                break;
            default:
                # code...
                break;
        }
    }

    /**
     * Get a list of MRNs from the basket.
     * The list is stored in the session
     * and is seeded with recently blocked MRNs
     * coming from the FHIR logs table.
     * For each MRN we check if "break the glass" has been already used (check for empty status).
     *
     * @return ProtectedPatientDTO[]
     */
    public function getProtectedMrnList()
    {
        $fileCache = $this->getCache();
        $encryptedList = $fileCache->get('list');
        if($encryptedList) {
            /** @var ProtectedPatientDTO[] $list */
            $list = unserialize(decrypt($encryptedList), ['allowed_classes'=>[ProtectedPatientDTO::class, DateTime::class]]);
        } else $list = [];
        return $list;
    }

    /**
     * Retrieve a list of unique MRNs (Medical Record Numbers) for patients.
     *
     * This function fetches the list of ProtectedPatientDTOs,
     * and extracts the unique MRNs into a clean, indexed array.
     * 
     * @return string[] An array of unique MRNs for valid (non-expired) patients.
     */
    public function getUniqueMrnList()
    {
        $protectedPatients = $this->getProtectedMrnList();
        
        $mrnList = [];
        foreach ($protectedPatients as $protectedPatient) {
            if ($protectedPatient instanceof ProtectedPatientDTO) {
                $mrnList[] = $protectedPatient->mrn;
            }
        }
        
        return array_values(array_unique($mrnList));
    }


    /**
     * return a protected patient matching the specified MRN
     *
     * @param string $mrn
     * @return ProtectedPatientDTO|null
     */
    public function getStoredPatient($mrn) {
        $list = $this->getProtectedMrnList();
        foreach ($list as $entry) {
            if($entry->mrn===$mrn) return $entry;
        }
        return;
    }

    /**
     * remove a protected patient from the cached list
     *
     * @param string $mrn
     * @return void
     */
    public function removeProtectedPatient($mrn) {
        $list = $this->getProtectedMrnList();
        $existing = $list[$mrn] ?? false;
        if(!$existing) return; // nothing to remove
        unset($list[$mrn]);
        $this->saveProtectedList($list);
    }

    public function storeProtectedPatient($mrn, $fhirBtgToken=null) {
        $protectedPatient = new ProtectedPatientDTO([
            'mrn' => $mrn,
            'timestamp' => new DateTime(),
            'fhirBtgToken' => $fhirBtgToken,
        ]);
        
        $list = $this->getProtectedMrnList();
        $existing = $list[$mrn] ?? false;
        if($existing && $existing->fhirBtgToken) {
            // check if already cached with the same fhirBtgToken
            if($existing->fhirBtgToken == $protectedPatient->fhirBtgToken) return; 
        };
        // add if not existing or rewrite if existing and without a fhirBtgToken
        $list[$mrn] = $protectedPatient;
        // save the updated list
        $this->saveProtectedList($list);
    }

    /**
     * persist the list to cache
     *
     * @param array $list
     * @return void
     */
    private function saveProtectedList($list=[]) {
        $ttl = 60*60*24; // store the list for 1 day
        $fileCache = $this->getCache();
        $fileCache->set('list', encrypt(serialize($list)), $ttl);
    }

    private function getCache() {
        return new FileCache(__CLASS__."-".$this->project_id);
    }

    /**
     * Query table to get REDCap username from passed EHR username
     *
     * @param string $userid
     * @return string
     */
    private function getEhrMappedUsername()
    {
        $ehrID = $this->fhirSystem->getEhrId();
        if(!$ehrID) return false;

        $sql = 
            "SELECT ehr_username
            FROM redcap_ehr_user_map
            WHERE ehr_id = ?
            AND redcap_userid = ?
            LIMIT 1";
        $params = [$ehrID, $this->userID];
        $result = db_query($sql, $params);
        if(!$result) return false;
        if($result && $row = db_fetch_assoc($result)) return $row['ehr_username'];
    }

    public static function getFormURL($project_id) {
        return APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . "/DynamicDataPull/break-the-glass?pid=$project_id";
    }
}
