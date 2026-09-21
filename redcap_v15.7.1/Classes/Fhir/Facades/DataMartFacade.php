<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Facades;

use ReflectionMethod;
use Http\Client\HttpClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\Logs\FhirLogger;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMartRevision;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroup;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManager;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMartRecordAdapter;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\GroupDecorators\DataMartGroupDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManagerFactory;

/**
 * @method static array getRecord(integer $project_id, string $mrn, integer $user_id)
 */
class DataMartFacade {

    /**
     * get a record using the latest revision in a project
     *
     * @param integer $project_id
     * @param string $mrn
     * @return array
     */
    public static function getRecord($project_id, $mrn, $user_id=null) {
        $fhirSystem = FhirSystem::fromProjectId($project_id);
        $tokenManager = FhirTokenManagerFactory::create($fhirSystem, $user_id, $project_id);
        $dataMart = new DataMart($user_id);
        $fhirLogsMapper = new FhirLogger();

        $fhirClient = new FhirClient($project_id, $tokenManager);

        $revision = DataMartRevision::getActive($project_id);

        $adapter = new DataMartRecordAdapter($mrn, $revision);
        $fhirClient->attach($fhirLogsMapper, FhirClient::NOTIFICATION_RESOURCE_RECEIVED);
        $fhirClient->attach($adapter, FhirClient::NOTIFICATION_ENTRIES_RECEIVED);
        $fhirClient->attach($tokenManager, FhirClient::NOTIFICATION_PATIENT_IDENTIFIED);
		$fhirClient->attach($tokenManager, FhirClient::NOTIFICATION_RESOURCE_ERROR);

        $fhirMetadataSource = $dataMart->getFhirMetadataSource($project_id);

        $mapping_list = $revision->getNormalizedMapping($mrn);
        // start the fetching process
        $mappingGroups = FhirMappingGroup::makeGroups($fhirMetadataSource, $mapping_list);
        foreach ($mappingGroups as $mappingGroup) {
            $mappingGroup = new DataMartGroupDecorator($mappingGroup, $revision);
            $fhirClient->fetchData($mrn, $mappingGroup);
        }
        
        return [
            'data' => $adapter->getRecord(),
            'errors' => $fhirClient->getErrors(),
        ];
    }

    /* public static function __callStatic($name, $arguments)
    {
        if(!method_exists(__CLASS__, $name)) return;
        $method = new ReflectionMethod(__CLASS__, $name);
        $instance = null; // instance can be null for static methods
        if(!$method->isStatic()) {
            $instance = new static();
        }
        return $method->invokeArgs($instance, $arguments);
    } */
}