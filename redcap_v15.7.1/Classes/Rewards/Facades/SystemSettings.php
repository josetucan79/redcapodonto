<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Facades;

use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Settings\System\SystemSettingsService;
use Vanderbilt\REDCap\Classes\Rewards\Settings\BaseSettingsValueObject;

class SystemSettings
{
    /**
     * Get system settings for a provider type
     */
    public static function get(string $providerType): ?BaseSettingsValueObject
    {
        $entityManager = EntityManager::get();
        $service = new SystemSettingsService($entityManager);
        
        return $service->getSettings($providerType);
    }
    
    /**
     * Save system settings
     */
    public static function save(BaseSettingsValueObject $settings): bool
    {
        $entityManager = EntityManager::get();
        $service = new SystemSettingsService($entityManager);
        
        return $service->saveSettings($settings);
    }
}