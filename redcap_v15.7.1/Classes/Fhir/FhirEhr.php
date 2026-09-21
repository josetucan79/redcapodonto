<?php

namespace Vanderbilt\REDCap\Classes\Fhir;

use DynamicDataPull;
use Project;
use User;
use UserRights;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManagerFactory;

class FhirEhr
{	
	// Other FHIR-related settings
	private static $fhirRedirectPage = 'ehr.php';
	public $ehrUIID = null;
	public $fhirPatient = null; // Current patient
	public $fhirAccessToken = null; // Current FHIR access token
	public $fhirResourceErrors = array();

	/**
	 * Standard Standalone Launch authentication flow
	 */
	const AUTHENTICATION_FLOW_STANDARD = 'standalone_launch';
	/**
	 * OAuth2 client credentials authentication flow (cerner only)
	 */
	const AUTHENTICATION_FLOW_CLIENT_CREDENTIALS = 'client_credentials';

	
	// Construct
	public function __construct()
	{
		// Start the session if not started
		\Session::init();
		// Initialization check to ensure we have all we need
		$this->initCheck();
	}

	public static function getUserID()
	{
		if ($GLOBALS['auth_meth_global'] == 'none') {
			$_SESSION['username'] = 'site_admin';
		}
		\Session::init();
		if (!isset($_SESSION['username'])) return;
		if(!defined("USERID")) define("USERID", strtolower($_SESSION['username']));
		$user_id = \User::getUIIDByUsername(USERID);
		/* $user_info = (object)\User::getUserInfo($id=USERID);
		$user_id = $user_info->ui_id; */
		return $user_id;
	}
	
	
	// Query table to determine if REDCap username has been allowlisted for DDP on FHIR
	public static function isDdpUserAllowlistedForFhir($ehrID, $username)
	{		
		$sql = "SELECT 1 FROM redcap_ehr_user_map m, redcap_user_information i
				WHERE ehr_id = ?
				AND i.ui_id = m.redcap_userid
				AND i.username = ?";
		$params = [$ehrID, $username];
		$q = db_query($sql, $params);
		return (db_num_rows($q) > 0);
	}
	
	/**
	 * Query table to determine if REDCap username has been allowlisted for Data Mart project creation rights.
	 * Super users are allowed by default.
	 * 
	 */
	public static function isDdpUserAllowlistedForDataMart($username)
	{		
		$sql = "SELECT 1 FROM redcap_user_information WHERE username = '".db_escape($username)."'
				AND (super_user = 1 OR fhir_data_mart_create_project = 1)";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}
	
	// Obtain the FHIR redirect URL for this external module (assumes that page=index is the main launching page)
	public static function getFhirRedirectUrl()
	{
		return APP_PATH_WEBROOT_FULL . self::$fhirRedirectPage;
	}
	
	
	// Initialization check to ensure we have all we need
	private function initCheck()
	{
		$errors = array();
		if (empty($GLOBALS['fhir_client_id'])) {
			$errors[] = "Missing the FHIR client_id! Please add value to module configuration.";
		}
		if (empty($GLOBALS['fhir_endpoint_base_url'])) {
			$errors[] = "Missing the FHIR endpoint base URL! Please add value to module configuration.";
		}
		if (!empty($errors)) {
			throw new \Exception("<br>- ".implode("<br>- ", $errors));
		}	
	}

	public static function isDdpCustomEnabledInSystem() {
		$config = REDCapConfigDTO::fromDB();
		return boolval($config->realtime_webservice_global_enabled ?? false);

	}

	public static function isCdpEnabledInSystem() {
		$config = REDCapConfigDTO::fromDB();
		return boolval($config->fhir_ddp_enabled ?? false);

	}

	public static function isCdmEnabledInSystem() {
		$config = REDCapConfigDTO::fromDB();
		return boolval($config->fhir_data_mart_create_project ?? false);
	}

	public static function isDdpCustomEnabledInProject($project_id) {
		return DynamicDataPull::isEnabled($project_id);
	}

	public static function isCdpEnabledInProject($project_id) {
		return DynamicDataPull::isEnabledFhir($project_id);

	}

	public static function isCdmEnabledInProject($project_id) {
		return DataMart::isEnabled($project_id);
	}

	public static function isFhirEnabledInSystem() {
		return (
			self::isCdpEnabledInSystem() ||
			self::isCdmEnabledInSystem()
		);
	}

	/**
	 * check if a project has EHR servvices enabled
	 *
	 * @param integer $project_id
	 * @return boolean
	 */
	public static function isFhirEnabledInProject($project_id)
	{
		return (
			self::isCdpEnabledInProject($project_id) ||
			self::isCdmEnabledInProject($project_id)
		);
	}

	/**
	 * render the menu for the FHIR tools
	 *
	 * @param string $menu_id
	 * @param boolean $collapsed 
	 * @return string
	 */
	public static function renderFhirLaunchModal()
	{
		global $project_id, $lang;
		
		// get token 
		$user_id = FhirEhr::getUserID();
		$fhirSystem = FhirSystem::fromProjectId($project_id);
		$tokenManager = FhirTokenManagerFactory::create($fhirSystem, $user_id, $project_id);
		$token = $tokenManager->getToken();
		$token_found = $token instanceof FhirTokenDTO;
		$token_valid =  $token_found and $token->isValid();

		// exit if the token is valid
		if($token_valid) return;

		$data = array(
			'lang' => $lang,
			'ehr_system_name' => strip_tags($fhirSystem->getEhrName()),
			'ehr_id' => $fhirSystem->getEhrId(),
			'app_path_webroot' => APP_PATH_WEBROOT,
		);
		$modal = \Renderer::run('ehr.launch_modal', $data);
		return $modal;
	}
	

	/**
	 * check if clinical data interoperability services are enabled
	 * at the system-level in REDCap
	 *
	 * @return boolean
	 */
	public static function isCdisEnabledInSystem()
	{
		return self::isCdmEnabledInSystem() ||
			self::isCdpEnabledInSystem() ||
			self::isDdpCustomEnabledInSystem();
	}

	public static function canAccessCdpDashboard($project_or_id, $user_id) {
        if($project_or_id instanceof Project) {
            $Proj = $project_or_id;
        }elseif (isinteger($project_or_id)) {
            $Proj = new Project($project_or_id);
        }else {
            return false;
        }
        $project_id = $Proj->project_id;
        $currentUserId = intval($user_id);
        $userInfo = User::getUserInfoByUiid($currentUserId);
		$username = $userInfo['username'] ?? false;
        $isSuperUser = boolval($userInfo['super_user'] ?? false);
        $isImpersonator = isset($_SESSION['impersonate_user'][$project_id]);
		$uerRightsForProject = UserRights::getPrivileges($project_id, $username);
		$userRights = $uerRightsForProject[$project_id][$username] ?? [];
		$hasAdjudicationRights = boolval($userRights['realtime_webservice_adjudicate'] ?? false);
		$canView = (
			(DynamicDataPull::isEnabledInSystem() && DynamicDataPull::isEnabled($project_id)) ||
			(DynamicDataPull::isEnabledInSystemFhir() && DynamicDataPull::isEnabledFhir($project_id))
		) && (
			($isSuperUser && !$isImpersonator) || $hasAdjudicationRights
		);
        return $canView;
    }
	
	public static function canAccessTokenPriorityRules($project_or_id, $user_id) {
        if($project_or_id instanceof Project) {
            $Proj = $project_or_id;
        }elseif (isinteger($project_or_id)) {
            $Proj = new Project($project_or_id);
        }else {
            return false;
        }
        $project_id = $Proj->project_id;
        $projectOwner = intval($Proj->project['created_by'] ?? null);
        $currentUserId = intval($user_id);
        $userInfo = User::getUserInfoByUiid($currentUserId);
        $isSuperUser = boolval($userInfo['super_user'] ?? false);
        $isImpersonator = isset($_SESSION['impersonate_user'][$project_id]);

        $canView = (
            (FhirEhr::isFhirEnabledInSystem() && FhirEhr::isFhirEnabledInProject($project_id))
            && (
                ($isSuperUser && !$isImpersonator) ||
                ($projectOwner && $projectOwner == $currentUserId)
            )
        );
        return $canView;
    }
}
