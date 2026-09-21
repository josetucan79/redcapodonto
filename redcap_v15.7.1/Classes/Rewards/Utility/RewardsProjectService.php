<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Utility;

use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectProviderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProviderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardsProvider;

/**
 * Class RewardsProjectService
 *
 * This service class is responsible for enabling or disabling the Rewards feature for a REDCap project.
 * It is used in the Project Setup page to manage the rewards configuration,
 * including assigning the default provider (TANGO) and updating the redcap_projects table.
 *
 * In the future, this class may support assigning different rewards providers or advanced configurations.
 */
class RewardsProjectService {
    public static function toggleRewards(int $projectId, bool $enable, string $requestedBy = null): void
    {
        $entityManager = EntityManager::get();
        $projectReference = $entityManager->getReference(ProjectEntity::class, $projectId);

        $providerRepo = $entityManager->getRepository(ProviderEntity::class);
        $projectProviderRepo = $entityManager->getRepository(ProjectProviderEntity::class);

        // Currently defaults to TANGO, can be expanded later
        $provider = $providerRepo->findOneBy(['provider_name' => RewardsProvider::TANGO]);

        $projectProvider = $projectProviderRepo->findOneBy(['project' => $projectReference]);

        if ($enable && !$projectProvider) {
            $projectProvider = new ProjectProviderEntity();
            $projectProvider->setProject($projectReference);
            $projectProvider->setProvider($provider);
            $entityManager->persist($projectProvider);
        } elseif (!$enable && $projectProvider) {
            $entityManager->remove($projectProvider);
        }

        $entityManager->flush();

        // Update redcap_projects table
        $sql = "UPDATE redcap_projects SET rewards_enabled = ? WHERE project_id = ?";
        if (!db_query($sql, [$enable ? 1 : 0, $projectId])) {
            throw new \Throwable("Failed to update rewards_enabled setting.");
        }
    }

}
