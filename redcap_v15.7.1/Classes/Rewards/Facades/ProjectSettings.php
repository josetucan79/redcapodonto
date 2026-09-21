<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Facades;

use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Settings\Project\ProjectSettingsService;
use Vanderbilt\REDCap\Classes\Rewards\Settings\Project\ProjectSettingsValueObject;

class ProjectSettings
{
    /**
     * Get settings for a project
     */
    public static function get(int $projectId): ?ProjectSettingsValueObject
    {
        $entityManager = EntityManager::get();
        $service = new ProjectSettingsService($entityManager);
        
        return $service->getSettings($projectId);
    }
    
    /**
     * Save settings for a project
     */
    public static function save(int $projectId, ProjectSettingsValueObject $settings): bool
    {
        $entityManager = EntityManager::get();
        $service = new ProjectSettingsService($entityManager);
        
        return $service->saveSettings($projectId, $settings);
    }
}