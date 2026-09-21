<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Settings\Project;

use Doctrine\ORM\EntityManagerInterface;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectProviderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectSettingEntity;

class ProjectSettingsService
{
    private EntityManagerInterface $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    
    /**
     * Get settings for a project by provider type
     */
    public function getProjectSettings(int $projectId, string $providerType): ?ProjectSettingsValueObject
    {
        $projectReference = $this->entityManager->getReference(ProjectEntity::class, $projectId);
        // Get project provider to verify provider type
        $projectProvider = $this->entityManager->getRepository(ProjectProviderEntity::class)
            ->findOneBy(['project' => $projectReference]);
            
        if (!$projectProvider) {
            return null;
        }
        
        // Verify this project has the requested provider type
        $provider = $projectProvider->getProvider();
        if ($provider->getProviderName() !== $providerType) {
            return null;
        }
        
        // Create the appropriate value object
        $valueObject = ProjectSettingsRegistry::createValueObject($providerType);
        if (!$valueObject) {
            return null;
        }
        
        // Load all settings for this project
        $repository = $this->entityManager->getRepository(ProjectSettingEntity::class);
        $settings = $repository->findBy(['project' => $projectReference]);
        
        // example to manipualte special properties
        $data = [];
        foreach ($settings as $setting) {
            $key = $setting->getSettingKey();
            $value = $setting->getSettingValue();
            
            match ($key) {
                 '__some_specific_key_that_needs_special_handling' => $data[$key] = json_decode($value, true),
                 default => $data[$key] = $value,
            };
        }
        
        // Populate the value object
        $valueObject->populate($data);
        
        return $valueObject;
    }
    
    /**
     * Save settings for a project
     */
    public function saveSettings(int $projectId, ProjectSettingsValueObject $settings): bool
    {
        // Convert value object to key-value pairs
        $data = $settings->toKeyValuePairs();
        
        // Begin transaction
        $this->entityManager->beginTransaction();

        $projectReference = $this->entityManager->getReference(ProjectEntity::class, $projectId);
        
        try {
            // Process each setting
            foreach ($data as $key => $value) {
                // Skip null values
                if ($value === null) {
                    continue;
                }
                
                // Handle special types
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                
                // Find existing setting or create new one
                $setting = $this->entityManager->getRepository(ProjectSettingEntity::class)
                    ->findOneBy([
                        'project' => $projectReference,
                        'setting_key' => $key
                    ]);
                
                if (!$setting) {
                    $setting = new ProjectSettingEntity();
                    $setting->setProject($projectReference);
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
    
    /**
     * Get the provider type for a project
     */
    public function getProviderType(int $projectId): ?string
    {
        $projectReference = $this->entityManager->getReference(ProjectEntity::class, $projectId);

        $projectProvider = $this->entityManager->getRepository(ProjectProviderEntity::class)
            ->findOneBy(['project' => $projectReference]);
            
        if (!$projectProvider) {
            return null;
        }
        
        $provider = $projectProvider->getProvider();
        return $provider->getProviderName();
    }
    
    /**
     * Dynamically get the correct settings for a project based on its provider
     */
    public function getSettings(int $projectId): ?ProjectSettingsValueObject
    {
        $providerType = $this->getProviderType($projectId);
        if (!$providerType) {
            return null;
        }
        
        return $this->getProjectSettings($projectId, $providerType);
    }
}