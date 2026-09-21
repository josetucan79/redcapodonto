<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Doctrine\ORM\EntityRepository;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserPermissionEntity;

class ReviewerRoleAssignedCriterion extends AbstractCriterion {
    /**
     *
     * @var integer
     */
    private $project_id;

    /**
     *
     * @var EntityRepository<UserPermissionEntity>
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
        foreach ($userPermissions as $userPermission) {
            /** @var PermissionEntity $permission */
            $permission = $userPermission->getPermission();
            if($permission->getName() === PermissionEntity::REVIEW_ELIGIBILITY) return true;
        }
        return false;
    }

    
    /**
     * Provides a title of the criterion.
     *
     * @return string A human-readable title of the criterion.
     */
    public function getTitle() {
        return $this->lang['rewards_reviewer_role_criterion_title'] ?? 'Reviewer Role assigned';
    }

    /**
     * Gets the description of the criterion and steps to take if not met.
     *
     * @return string The detailed description of the criterion.
     */
    public function getDescription() {
        return $this->lang['rewards_reviewer_role_criterion_description'] ?? '';
    }
}

