<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Settings\System;

use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardsProvider;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities\TangoSystemSettingsVO;
use Vanderbilt\REDCap\Classes\Rewards\Settings\SettingsRegistryTrait;

class SystemSettingsRegistry
{
    use SettingsRegistryTrait;
    
    protected static function init(): void
    {
        // Register all system settings value object classes
        self::registerValueObject(RewardsProvider::TANGO, TangoSystemSettingsVO::class);
    }
}