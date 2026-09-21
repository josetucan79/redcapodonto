<?php
namespace Vanderbilt\REDCap\Classes\Fhir\MappingHelper;

use User;
use Exception;
use UserRights;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\Facades\FhirClientFacade;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMartRevision;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\FhirClientResponse;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\MappingHelper\EndpointOptionsVisitor;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataAbstractDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataMappingHelperDecorator;

class FhirMappingHelper
{
    const PROJECT_TYPE_DATAMART = 'datamart';
    const PROJECT_TYPE_CDP = 'cdp';

    private $project_id;
    private $user_id;
    private $metadataSource; // cached metadata source
    /**
     *
     * @param integer $project_id
     * @param integer $user_id
     */
    public function __construct($project_id, $user_id)
    {
        $this->project_id = $project_id;
        $this->user_id = $user_id;
    }

    /**
     * @return integer
     */
    public function getProjectId()
    {
        return $this->project_id;
    }

    /**
     * @return integer
     */
    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * print the link button pointing to the Mapping Helper page
     *
     * @param integer $project_id
     * @return void
     */
    public static function printLink($project_id)
    {
        $link = self::getLink($project_id);
        $html = sprintf('<a class="btn btn-primaryrc btn-xs" style="color:#fff !important;" href="%s">Mapping Helper</a>', $link);

        print $html;
    }

    /**
     * print the link button pointing to the Mapping Helper page
     *
     * @param integer $project_id
     * @return string
     */
    public static function getLink($project_id)
    {
        $parseUrl = function($URL) {
            $parts = parse_url($URL);
            $scheme = $parts['scheme'] ?? '';
            $host = $parts['host'] ?? '';
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            $base = sprintf("%s://%s%s", $scheme, $host, $port);
            return $base;
        };
        $root = $parseUrl(APP_PATH_WEBROOT_FULL);
        $version_dir = APP_PATH_WEBROOT;
        $url = $root.$version_dir."index.php?pid={$project_id}&route=FhirMappingHelperController:index";
        $double_slashes_regexp = "#(?<!https:)(?<!http:)\/\/#";
        $link = preg_replace($double_slashes_regexp, '/', $url);
        return $link;
    }

    /**
     *
     * @param Project $project
     * @return DataMartRevision|false
     */
    public function getDatamartRevision($project)
    {
        $datamart_enabled = boolval($project->project['datamart_enabled']);
        if(!$datamart_enabled) return false;
        $active_revision = DataMartRevision::getActive($project->project_id);
        return $active_revision;
    }

    public function getClinicalDataPullMapping()
    {
        $query_string = sprintf(
            'SELECT * FROM redcap_ddp_mapping
            WHERE project_id = %u', $this->project_id
        );
        $result = db_query($query_string);
        $mapping = [];
        while($row = db_fetch_assoc($result))
        {
            $mapping[] = $row;
        }
        return $mapping;
    }

    public function getNextResource($fhir_category = null) {
        $categories = [
            'Patient',
            'Immunization',
            'Allergy Intolerance',
            'Encounter',
            'Condition',
            'Core Characteristics',
            'Medications',
            'Laboratory',
            'Vital Signs',
            'Social History',
            'Adverse Event',
        ];
        $index = array_search($fhir_category, $categories) ?? -1;
        $nextIndex = intval($index) + 1;
        $nextCategory = $categories[$nextIndex] ?? null;
        return $nextCategory;
    }

    /**
     * Undocumented function
     *
     * @param string $fhir_category
     * @param string $mrn
     * @param array $options
     * @return FhirResource
     */
    public function getResourceByMrn($fhir_category, $mrn, $options=[])
    {
        /** 
         * create an error with a reference to all previous errors
         * @param Exception[] $errors
         */
        $combineErrors = function($errors) {
            $last = current($errors);
            while($next = next($errors)) {
                $last = new Exception($next->getMessage(), $next->getCode(), $last);
            }
            return $last;
        };
        $fhirSystem = FhirSystem::fromProjectId($this->project_id);
        $fhirClient = FhirClientFacade::getInstance($fhirSystem, $this->project_id, $this->user_id);
        
        $patient_id = $fhirClient->getPatientID($mrn);
        if(!$patient_id) throw new \Exception("Patient ID not found", 404);
        $fhirClient->setMrn($mrn);

        
        $endpoint_factory = $fhirClient->getEndpointFactory();
        $endpoint = $endpoint_factory->makeEndpoint($fhir_category);
        if(!($endpoint instanceof AbstractEndpoint)) {
            throw new \Exception(sprintf('No endpoint available for the category %s', $fhir_category), 1);
        };
        $options_visitor = new EndpointOptionsVisitor($patient_id, $options, $fhirClient);
        $params = $endpoint->accept($options_visitor);
        $request = $endpoint->getSearchRequest($params);
        
        $result = [];
        if($request) {
            $response = new FhirClientResponse([
                '$mrn' => $mrn,
                'patient_id' => $patient_id,
                'project_id' => $this->project_id,
                'user_id' => $this->user_id,
            ]);
            $response = $fhirClient->sendRequest($request, $response);
            if($fhirClient->hasErrors()) {
                $combinedErrors = $combineErrors($fhirClient->getErrors());
                throw $combinedErrors;
            }
            $resource = $response->getResource();
            // $fhir_code = $fhirClient->getFhirVersionManager()->getFhirCode();
            $resource_visitor = new ResourceVisitor($this, $fhir_category);
            $data = $resource->accept($resource_visitor);
            $result['data'] = $data;
            $result['metadata'] = $resource->getMetadata();
        }
        return $result;
    }

    /**
     *
     * @param string $relative_url
     * @param string $method
     * @param array $options
     * @return AbstractResource
     */
    public function getCustomFhirResource($relative_url, $method='GET', $options=[] )
    {   
        $fhirSystem = FhirSystem::fromProjectId($this->project_id);
        $fhir_client = FhirClientFacade::getInstance($fhirSystem, $this->project_id, $this->user_id);
        $queryOptions = ['query'=>$options];
        $fhir_request = $fhir_client->getFhirRequest($relative_url, $method, $queryOptions);
        $response = new FhirClientResponse([
            'project_id' => $this->project_id,
            'user_id' => $this->user_id,
        ]);
        $response = $fhir_client->sendRequest($fhir_request, $response);
        $resource = $response->getResource();
        if(is_null($resource) && $response->hasError()) throw $response->getError();
        $resource = $response->getResource();
        return $resource;
    }

    /**
     * check if a user can use the mapping helper in a specific project
     *
     * @param int $user_id
     * @param int $project_id
     * @return boolean
     */
    public static function availableToUser($user_id, $project_id) {
        $canCreateDataMartRevisions = function($project_id) {
            $project = new \Project($project_id);
            $projectInfo = $project->project;
            $datamart_allow_create_revision = $projectInfo['datamart_allow_create_revision'] ?? 0;
            return boolval($datamart_allow_create_revision);
        };
        $hasMappingPrivileges = function($username, $project_id) {
            $user_rights = UserRights::getPrivileges($project_id, $username)[$project_id][$username] ?? [];
            $realtime_webservice_mapping = $user_rights['realtime_webservice_mapping'] ?? 0;
            return boolval($realtime_webservice_mapping);
        };
        $hasCreateDatamartProjectsPrivileges = function($userInfo) {
            $canCreateDataMartProject = $userInfo['fhir_data_mart_create_project'] ?? 0;
            return boolval($canCreateDataMartProject);
        };

        $userInfo = User::getUserInfoByUiid($user_id);
        
        $superUser = boolval($userInfo['super_user'] ?? 0);
        $hasMappingPrivileges = $hasMappingPrivileges(($userInfo['username'] ?? ''), $project_id);
        $canCreateDataMartRevisions = $canCreateDataMartRevisions($project_id);
        $canCreateDatamartProjects = $hasCreateDatamartProjectsPrivileges($userInfo);
        return $superUser || $hasMappingPrivileges || $canCreateDataMartRevisions || $canCreateDatamartProjects;
    }

    function getAvailableCategoriesFromMetadata() {
        $metadataSource = $this->getMetadataSource();
        $uniqueCategories = array_unique(array_column($metadataSource, 'category'));
        $nonEmpty = array_filter($uniqueCategories, function($category) {
            if(!is_string($category)) return false;
            return trim($category) !== '';
        });
        return array_values($nonEmpty); // reset indexes
    }

    /**
     * Retrieves the metadata source for the current project.
     *
     * This function handles projects that are CDP, CDM, or both.
     * It collects metadata sources from the Clinical Data Pull and/or Data Mart
     * based on the project type, while eliminating code repetition.
     *
     * @return array The combined metadata source list.
     */
    function getMetadataSource() {
        if (is_null($this->metadataSource)) {
            // Initialize metadataSource as an empty array
            $this->metadataSource = [];

            // Define an array of sources based on project types
            $sources = [];

            if ($this->isProjectType(self::PROJECT_TYPE_CDP)) {
                $sources[] = [
                    'class' => \DynamicDataPull::class,
                    'constructor_args' => [$this->project_id, 'FHIR'],
                    'method' => 'getFhirMetadataSource',
                ];
            }

            if ($this->isProjectType(self::PROJECT_TYPE_DATAMART)) {
                $sources[] = [
                    'class' => DataMart::class,
                    'constructor_args' => [$this->user_id],
                    'method' => 'getFhirMetadataSource',
                ];
            }

            // Loop through each source and retrieve metadata
            foreach ($sources as $source) {
                $class = $source['class'];
                $args = $source['constructor_args'];
                $method = $source['method'];

                // Instantiate the class with the provided arguments
                $instance = new $class(...$args);

                // Call the specified method to get the metadata source
                $metadataSource = $instance->$method($this->project_id);

                // Check if the metadata source is valid
                if ($metadataSource instanceof FhirMetadataAbstractDecorator) {
                    // Decorate the metadata source
                    $decoratedSource = new FhirMetadataMappingHelperDecorator($metadataSource, $this->project_id);

                    // Get the list of metadata and merge it
                    $metadataList = $decoratedSource->getList();
                    $this->metadataSource = array_merge($this->metadataSource, $metadataList);
                }
            }
        }

        return $this->metadataSource;
    }

    /**
     * Checks if the project is of a certain type.
     *
     * Uses the logic provided to determine if the project is CDP, CDM, or both.
     *
     * @param string $type The project type to check against.
     * @return bool True if the project is of the specified type, false otherwise.
     */
    private function isProjectType($type) {
        $project = new \Project($this->project_id);

        if ($type === self::PROJECT_TYPE_DATAMART) {
            $datamartEnabled = $project->project['datamart_enabled'] ?? null;
            return $datamartEnabled === '1';
        }

        if ($type === self::PROJECT_TYPE_CDP) {
            $fhirEnabled = $project->project['realtime_webservice_type'] ?? null;
            return $fhirEnabled === 'FHIR';
        }

        return false;
    }

    /**
     * Retrieves the mapping data for the current project.
     *
     * This function handles projects that are CDP, CDM, or both.
     * It collects mapped fields from the Data Mart's active revision
     * and/or the Clinical Data Pull mapping based on the project type.
     *
     * @return array The list of mapped fields.
     */
    function getProjectMappingData() {
        $fields = [];

        if ($this->isProjectType(self::PROJECT_TYPE_DATAMART)) {
            $datamart_active_revision = DataMartRevision::getActive($this->project_id);
            if ($datamart_active_revision instanceof DataMartRevision) {
                $cdm_fields = $datamart_active_revision->getFields() ?? [];
                $fields = array_merge($fields, $cdm_fields);
            }
        }

        if ($this->isProjectType(self::PROJECT_TYPE_CDP)) {
            $cdp_mapping = $this->getClinicalDataPullMapping($this->project_id);
            if (!empty($cdp_mapping)) {
                $cdp_fields = array_column($cdp_mapping, 'external_source_field_name');
                $fields = array_merge($fields, $cdp_fields);
            }
        }

        return $fields;
    }


    /**
     * get a list of codes that are available in REDCap, but not used
     *
     * @return void
     */
    public function getBlocklistedCodes()
    {
        $list = [];
        // Vital signs
        $list[] = new BlocklistCode('8716-3','too generic');
        return $list;
    }
}