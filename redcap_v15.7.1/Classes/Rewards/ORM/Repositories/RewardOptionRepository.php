<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories;

use Doctrine\ORM\EntityRepository;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;

class RewardOptionRepository extends EntityRepository
{
    public function findActiveByProjectId(int $projectId): array
    {
        $projectReference = $this->getEntityManager()->getReference(ProjectEntity::class, $projectId);
    
        return $this->findBy([
            'project' => $projectReference,
            'deleted_at' => null
        ]);
    }
    
}