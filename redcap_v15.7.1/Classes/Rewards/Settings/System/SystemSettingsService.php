<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Settings\System;

use Doctrine\ORM\EntityManagerInterface;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProviderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProviderSettingEntity;

class SystemSettingsService
{
    private EntityManagerInterface $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    
    /**
     * Get system settings for a provider type
     */
    public function getSettings(string $providerType): ?SystemSettingsValueObject
    {
        // Get provider to verify it exists
        /** @var ProviderEntity $provider */
        $provider = $this->entityManager->getRepository(ProviderEntity::class)
            ->findOneBy(['provider_name' => $providerType]);
            
        if (!$provider) {
            return null;
        }
        
        // Create the appropriate value object
        $valueObject = SystemSettingsRegistry::createValueObject($providerType);
        if (!$valueObject) {
            return null;
        }
        
        // Load all settings for this provider
        $repository = $this->entityManager->getRepository(ProviderSettingEntity::class);
        $settings = $repository->findBy(['provider' => $provider]);
        
        // Convert to key-value pairs
        $data = [];
        foreach ($settings as $setting) {
            $key = $setting->getSettingKey();
            $value = $setting->getSettingValue();
            
            match ($key) {
                 // Handle special cases if needed
                 default => $data[$key] = $value,
            };
        }
        
        // Add provider_id to data
        $data['provider_id'] = $provider->getProviderId();
        
        // Populate the value object
        $valueObject->populate($data);
        
        return $valueObject;
    }
    
    /**
     * Save system settings for a provider
     */
    public function saveSettings(SystemSettingsValueObject $settings): bool
    {
        // Convert value object to key-value pairs
        $data = $settings->toKeyValuePairs();
        
        // Get provider ID
        $providerId = $data['provider_id'] ?? null;
        if (!$providerId) {
            return false;
        }
        
        // Get provider entity
        $providerEntity = $this->entityManager->getReference(ProviderEntity::class, $providerId);
        if (!$providerEntity) {
            return false;
        }
        
        // Begin transaction
        $this->entityManager->beginTransaction();
        
        try {
            // Process each setting
            foreach ($data as $key => $value) {
                // Skip null values and provider_id
                if ($value === null || $key === 'provider_id') {
                    continue;
                }
                
                // Handle special types
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                
                // Find existing setting or create new one
                $setting = $this->entityManager->getRepository(ProviderSettingEntity::class)
                    ->findOneBy([
                        'provider_id' => $providerId,
                        'setting_key' => $key
                    ]);
                
                if (!$setting) {
                    $setting = new ProviderSettingEntity();
                    $setting->setProvider($providerEntity);
                    $setting->setSettingKey($key);
                }
                
                $setting->setSettingValue($value);
                $this->entityManager->persist($setting);
            }
            
            $this->entityManager->flush();
            $this->entityManager->commit();
            
            return true;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return false;
        }
    }
}