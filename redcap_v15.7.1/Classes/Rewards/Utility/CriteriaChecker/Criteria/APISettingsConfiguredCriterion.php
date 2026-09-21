<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Exception;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Facades\ProjectSettings;
use Vanderbilt\REDCap\Classes\Rewards\Facades\SystemSettings;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectProviderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProviderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardsProvider;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities\TangoProjectSettingsVO;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities\TangoSystemSettingsVO;

class APISettingsConfiguredCriterion extends AbstractCriterion {
    
    private $projectId;
    private $error = null;

    public function __construct(int $projectId) {
        $this->projectId = $projectId;
    }

    public function check() {
        try {
            $this->error = null; // reset error
            // Determine provider for this project
            $entityManager = EntityManager::get();
            $projectReference = $entityManager->getReference(ProjectEntity::class, $this->projectId);
            $projectProvider = $entityManager->getRepository(ProjectProviderEntity::class)->findOneBy(['project' => $projectReference]);
            if (!$projectProvider) return false;
            
            $providerID = $projectProvider->getProviderId();
            $provider = $entityManager->getRepository(ProviderEntity::class)->findOneBy(['provider_id' => $providerID]);
    
            // Load project-specific settings based on provider
            $requiredFields = $this->loadProviderSettings($provider);
    
            // if all fields are defined in the project settings then stop here
            if (!$this->anyEmpty($requiredFields)) return true;
            
            // merge with the system settings and validate again
            $requiredFields = $this->loadSystemSettings($provider, $requiredFields);
            if ($this->anyEmpty($requiredFields)) {
                return false;
            }
    
            return true;
        } catch (\Throwable $th) {
            $this->error = $th->getMessage();
            return false;
        }
    }

    private function loadProviderSettings(ProviderEntity $provider) {
        $providerName = $provider->getProviderName();

        $requiredFields = [];
        $settings = ProjectSettings::get($this->projectId);
        if(!$settings) throw new Exception("Settings not found for $this->projectId", 404);

        switch ($providerName) {
            case RewardsProvider::TANGO:
                /** @var  TangoProjectSettingsVO $settings */
                $requiredFields = [
                    'client_id' => $settings->getClientId(),
                    'client_secret' => $settings->getClientSecret(),
                    'group_identifier' => $settings->getGroupIdentifier(),
                    'campaign_identifier' => $settings->getCampaignIdentifier(),
                    'base_url' => $settings->getBaseUrl(),
                    'token_url' => $settings->getTokenUrl(),
                ];
                break;

            case RewardsProvider::TREMENDOUS:
                // Add specific settings for Tremendous here
                break;

            case RewardsProvider::GIFTBIT:
                // Add specific settings for Giftbit here
                break;

            default:
                break;
        }
        return $requiredFields;
    }

    private function loadSystemSettings(ProviderEntity $provider, $fields) {
        $providerName = $provider->getProviderName();

        $settings = SystemSettings::get($providerName);
        if(!$settings) throw new Exception("Settings not found for $providerName", 404);
        

        switch ($providerName) {
            case RewardsProvider::TANGO:
                /** @var  TangoSystemSettingsVO $settings */
                $fields['client_id'] = $settings->getClientId();
                $fields['client_secret'] = $settings->getClientSecret();
                $fields['base_url'] = $settings->getBaseUrl();
                $fields['token_url'] = $settings->getTokenUrl();
                break;

            case RewardsProvider::TREMENDOUS:
                // Load system settings for Tremendous here
                break;

            case RewardsProvider::GIFTBIT:
                // Load system settings for Giftbit here
                break;

            default:
                break;
        }
        return $fields;
    }

    private function anyEmpty($fields) {
        foreach ($fields as $value) {
            if (empty($value)) {
                return true;
            }
        }
        return false;
    }

    public function getTitle() {
        return $this->lang['rewards_api_settings_criterion_title'] ?? 'API settings are configured';
    }

    public function getDescription() {
        $description = $this->lang['rewards_api_settings_criterion_description'] ?? '';
        if($this->error) $description .= " - $this->error";
        return $description;
    }
}

