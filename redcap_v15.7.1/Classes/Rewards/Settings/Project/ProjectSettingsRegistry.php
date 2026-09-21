<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Settings\Project;

use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardsProvider;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities\TangoProjectSettingsVO;
use Vanderbilt\REDCap\Classes\Rewards\Settings\SettingsRegistryTrait;

class ProjectSettingsRegistry
{
    use SettingsRegistryTrait;
    
    /**
     * Ensure the registry is initialized with project settings classes
     */
    protected static function init(): void
    {
        // Register all project settings value object classes
        self::registerValueObject(RewardsProvider::TANGO, TangoProjectSettingsVO::class);
        // Register other project settings classes here
        
        self::$initialized = true;
    }
}