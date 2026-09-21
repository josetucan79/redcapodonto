<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities;

use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardsProvider;
use Vanderbilt\REDCap\Classes\Rewards\Settings\System\SystemSettingsValueObject;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Config\TangoApiConfig;

class TangoSystemSettingsVO extends SystemSettingsValueObject
{
    const KEY_BASE_URL = 'base_url';
    const KEY_TOKEN_URL = 'token_url';
    const KEY_CLIENT_ID = 'client_id';
    const KEY_CLIENT_SECRET = 'client_secret';
    
    public ?string $base_url = null;
    public ?string $token_url = null;
    public ?string $client_id = null;
    public ?string $client_secret = null;
    
    public static function getSettingKeys(): array
    {
        return [
            SystemSettingsValueObject::KEY_PROVIDER_ID,
            self::KEY_BASE_URL,
            self::KEY_TOKEN_URL,
            self::KEY_CLIENT_ID,
            self::KEY_CLIENT_SECRET,
        ];
    }

    public function getBaseUrl(): ?string { return $this->base_url; }
    public function setBaseUrl(?string $value) { $this->base_url = $value; }

    public function getTokenUrl(): ?string { return $this->token_url; }
    public function setTokenUrl(?string $value) { $this->token_url = $value; }

    public function getClientId(): ?string { return $this->client_id; }
    public function setClientId(?string $value) { $this->client_id = $value; }

    public function getClientSecret(): ?string { return $this->client_secret; }
    public function setClientSecret(?string $value) { $this->client_secret = $value; }

    public static function getProviderType(): string { return RewardsProvider::TANGO; }
    
    // Business logic methods
    public function environment(): ?string
    {
        return TangoApiConfig::getEnvironmentByBaseUrl($this->base_url ?? '');
    }
}