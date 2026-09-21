<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Settings\System;

use Vanderbilt\REDCap\Classes\Rewards\Settings\BaseSettingsValueObject;

abstract class SystemSettingsValueObject extends BaseSettingsValueObject
{

    const KEY_PROVIDER_ID = 'provider_id';

    public ?string $provider_id = null;

    public function getProviderId(): ?string { return $this->provider_id; }
    public function setProviderId(?string $value) { $this->provider_id = $value; }
}