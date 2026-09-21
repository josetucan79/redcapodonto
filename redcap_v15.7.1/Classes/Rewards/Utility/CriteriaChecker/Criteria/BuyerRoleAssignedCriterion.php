<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Doctrine\ORM\EntityRepository;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserPermissionEntity;

class BuyerRoleAssignedCriterion extends AbstractCriterion {
    /**
     *
     * @var integer
     */
    private $project_id;

    /**
     *
     * @var EntityRepository<EntitiesUserPermissionEntity>
     */
    private $userPermissionRepository;

    /**
     *
     * @param integer $project_id
     * @param EntityRepository<UserPermissionEntity> $permissionRepository
     */
    
    public function __construct($project_id, $userPermissionRepository) {
        $this->project_id = $project_id;
        $this->userPermissionRepository = $userPermissionRepository;
    }

    public function check() {
        /** @var UserPermissionEntity[] $userPermissions */
        $userPermissions = $this->userPermissionRepository->findBy(['project_id' => $this->project_id]);
        $names = [];
        $users = [];
        foreach ($userPermissions as $userPermission) {
            /** @var PermissionEntity $permission */
            $permission = $userPermission->getPermission();
            $user = $userPermission->getUser();
            $name = $permission->getName();
            $names[] = $name;
            $users[] = $user;
            if($permission->getName() === PermissionEntity::PLACE_ORDERS) return true;
        }
        return false;
    }

    
    /**
     * Provides a title of the criterion.
     *
     * @return string A human-readable title of the criterion.
     */
    public function getTitle() {
        return $this->lang['rewards_buyer_role_criterion_title'] ?? 'Buyer Role assigned';
    }

    /**
     * Gets the description of the criterion and steps to take if not met.
     *
     * @return string The detailed description of the criterion.
     */
    public function getDescription() {
        return $this->lang['rewards_buyer_role_criterion_description'] ?? '';
    }
}

