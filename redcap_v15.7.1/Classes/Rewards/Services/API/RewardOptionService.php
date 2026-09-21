<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\API;

use Doctrine\ORM\EntityManagerInterface;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\RewardOptionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Facades\PermissionsGateFacade as Gate;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;

class RewardOptionService extends RepositoryService {



    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, RewardOptionEntity::class);
    }

    public function find(array $criteria = [], ?array $orderBy = null, ?int $page = 1, ?int $perPage = 500, &$metadata = null, bool $includeDeleted = false): array {
        // show deleted if super user
        if(Gate::allows('SUPER_USER')) $includeDeleted = true;

        return parent::find($criteria, $orderBy, $page, $perPage, $metadata, $includeDeleted);
    }

    public function delete(int $id, $force_delete = false)
    {
        if(Gate::denies('SUPER_USER')) return;
        if($force_delete) return parent::delete($id);
        else return parent::softDelete($id);
    }

    protected function transformInput(array $data): array {
        if(isset($data['project_id'])) {
            $project_id = $data['project_id'];
            $projectReference = $this->entityManager->getReference(ProjectEntity::class, $project_id);
            unset($data['project_id']);
            $data['project'] = $projectReference;
        }
        return $data;
    }

}