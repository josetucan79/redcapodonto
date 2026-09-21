<?php
namespace Vanderbilt\REDCap\Classes\Fhir\MappingHelper;

use System;
use Vanderbilt\REDCap\Classes\Fhir\FhirUser;
use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManager;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManagerFactory;

class FhirMappingHelperSettings
{
    private $mapping_helper;
    private $project_id;
    private $user_id;
    /**
     *
     * @param FhirMappingHelper $mapping_helper
     */
    public function __construct($mapping_helper)
    {
        $this->mapping_helper = $mapping_helper;
        $this->project_id = $mapping_helper->getProjectId();
        $this->user_id = $mapping_helper->getUserId();
    }

    /**
     * get system settings, language and other parameters useful for the app
     *
     * @return array
     */
    public function getAppSettings()
    {
        $getLanguage = function($project_id) {
            $project = new \Project($project_id);
            $project_language = $project->project['project_language'];
            return \Language::getLanguage($project_language);
        };
        $system_configs = System::getConfigVals();
        $fhir_source_system_custom_name = $system_configs['fhir_source_system_custom_name'] ?? '';
        $fhir_standalone_authentication_flow = $system_configs['fhir_standalone_authentication_flow'] ?? '';
        $base_url = preg_replace('/(.+?)[\/]*$/', '$1', APP_PATH_WEBROOT_FULL); // remove trailing slash

        $fhir_base_url = $system_configs['fhir_endpoint_base_url'] ?? '';
        $fhirSystem = FhirSystem::fromProjectId($this->project_id);
        $fhirVersionManager = FhirVersionManager::getInstance($fhirSystem);

        $app_settings = [
            'project_id' => $this->project_id,
            'lang' => $getLanguage($this->project_id),
            'standalone_authentication_flow' => $fhir_standalone_authentication_flow,
            'standalone_launch_enabled' => true,
            'standalone_launch_url' => FhirLauncher::getStandaloneLaunchUrl(),
            'project_mapping_url' => $base_url.'/DynamicDataPull/setup.php?pid='.$this->project_id,
            'fhir_base_url' => $fhir_base_url,
            'ehr_system_name' => strip_tags($fhir_source_system_custom_name),
            'blocklisted_codes' => $this->mapping_helper->getBlocklistedCodes(),
            'fhir_code' => $fhirVersionManager->getFhirCode(),
            'fhir_version' => $fhirVersionManager->getFhirVersion(),
        ];
        return $app_settings;
    }

    /**
     * get a list of token for a user_id
     * 
     * @return array
     */
    public function getUserInfo()
    {
        $fhirSystem = FhirSystem::fromProjectId($this->project_id);
        $user = new FhirUser($this->user_id);
        $tokenManager = FhirTokenManagerFactory::create($fhirSystem, $this->user_id);

        $token = $tokenManager->getToken();
        $data = [
            'info' => $user,
            'token' => $token,
        ];
        return $data;
    }

    /**
     * get info about a project
     * 
     * @return void
     */
    public function getProjectInfo()
    {
        $filterProjectData = function($project, $skippedKeys=[]) {
            $projectVars = get_object_vars($project); // Only public properties
            $filtered = array_filter($projectVars, function($key) use($skippedKeys) {
                return !in_array($key, $skippedKeys);
            }, ARRAY_FILTER_USE_KEY);
            return $filtered;
        };

        $project_id = $this->project_id;
        $project = new \Project($this->project_id);
        $datamart_active_revision = $this->mapping_helper->getDatamartRevision($project);
        $cdp_mapping = $this->mapping_helper->getClinicalDataPullMapping($project_id);
        
        $skippedKeys = ['metadata', 'metadata_temp', 'forms'];
        $projectData = $filterProjectData($project, $skippedKeys);
        $data = array(
            'info' =>  $projectData,
            'datamart_revision' => $datamart_active_revision,
            'cdp_mapping' => $cdp_mapping,
        );
        return $data;
    }
}