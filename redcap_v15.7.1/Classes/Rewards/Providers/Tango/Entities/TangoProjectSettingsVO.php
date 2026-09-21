<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities;

use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardsProvider;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Config\TangoApiConfig;
use Vanderbilt\REDCap\Classes\Rewards\Settings\Project\ProjectSettingsValueObject;

class TangoProjectSettingsVO extends ProjectSettingsValueObject
{
    const KEY_BASE_URL = 'base_url';
    const KEY_TOKEN_URL = 'token_url';
    const KEY_CLIENT_ID = 'client_id';
    const KEY_CLIENT_SECRET = 'client_secret';
    const KEY_GROUP_IDENTIFIER = 'group_identifier';
    const KEY_CAMPAIGN_IDENTIFIER = 'campaign_identifier';
    
    public ?string $base_url = null;
    public ?string $token_url = null;
    public ?string $client_id = null;
    public ?string $client_secret = null;
    public ?string $group_identifier = null;
    public ?string $campaign_identifier = null;
    
    public static function getSettingKeys(): array
    {
        return [
            self::KEY_BASE_URL,
            self::KEY_TOKEN_URL,
            self::KEY_CLIENT_ID,
            self::KEY_CLIENT_SECRET,
            self::KEY_GROUP_IDENTIFIER,
            self::KEY_CAMPAIGN_IDENTIFIER,
            ProjectSettingsValueObject::KEY_EMAIL_TEMPLATE,
            ProjectSettingsValueObject::KEY_EMAIL_SUBJECT,
            ProjectSettingsValueObject::KEY_EMAIL_FROM,
            ProjectSettingsValueObject::KEY_PREVIEW_EXPRESSION,
            ProjectSettingsValueObject::KEY_PARTICIPANT_DETAILS,
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

    public function getGroupIdentifier(): ?string { return $this->group_identifier; }
    public function setGroupIdentifier(?string $value) { $this->group_identifier = $value; }

    public function getCampaignIdentifier(): ?string { return $this->campaign_identifier; }
    public function setCampaignIdentifier(?string $value) { $this->campaign_identifier = $value; }

    public static function getProviderType(): string { return RewardsProvider::TANGO; }
    
    // Business logic methods
    public function environment(): ?string
    {
        return TangoApiConfig::getEnvironmentByBaseUrl($this->base_url ?? '');
    }
    
}