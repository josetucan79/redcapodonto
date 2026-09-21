<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services;

use Vanderbilt\REDCap\Classes\Utility\Context;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Facades\ProjectSettings;
use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardsProvider;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\OrderService;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\OrderEmailService;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalService;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\REDCapDataService as API_REDCapDataService;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\RewardOptionService as API_RewardOptionService;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\ProjectSettingsService as API_ProjectSettingsService;

class ServiceFactory {

    /**
     * @template T
     * @param class-string<T> $serviceName The class name of the service to create.
     * @param mixed ...$args Arguments to pass to the service constructor.
     * @return T|null An instance of the specified service class, or null if not recognized.
     */
    public static function make($serviceName, ...$args) {
        $user_id = Context::getUIID();
        $project_id = Context::getProjectId();
        $entityManager = EntityManager::get();
        
        $service = null;
        switch ($serviceName) {
            case API_RewardOptionService::class:
                $service = new API_RewardOptionService($entityManager);
                break;
            case API_REDCapDataService::class:
                $settings = ProjectSettings::get($project_id);
                $service = new API_REDCapDataService($entityManager, $settings, $project_id);
                break;
            case API_ProjectSettingsService::class:
                $settings = ProjectSettings::get($project_id);
                $service = new API_ProjectSettingsService($project_id, $user_id, $settings);
                break;
            case OrderEmailService::class:
                $settings = ProjectSettings::get($project_id);
                $service = new OrderEmailService($settings, $project_id, $user_id);
                break;
            case RewardApprovalService::class:
                $service = new RewardApprovalService($entityManager, $project_id, $user_id);
                break;
            case OrderService::class:
                $provider = RewardsProvider::make($project_id);
                $service = new OrderService($entityManager, $provider, $project_id, $user_id);
                break;
            default:
                # code...
                break;
        }
        return $service;
    }


}