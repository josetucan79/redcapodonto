<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Doctrine\ORM\EntityRepository;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserPermissionEntity;

class RewardsOptionsManagerRoleAssignedCriterion extends PermissionCriterion {

    /**
     *
     * @param integer $project_id
     * @param EntityRepository<UserPermissionEntity> $permissionRepository
     */
    public function __construct($project_id, $userPermissionRepository) {
        parent::__construct($project_id, $userPermissionRepository, PermissionEntity::MANAGE_REWARD_OPTIONS);
    }
    
    /**
     * Provides a title of the criterion.
     *
     * @return string A human-readable title of the criterion.
     */
    public function getTitle() {
        return $this->lang['rewards_rewards_manager_role_criterion_title'] ?? 'Rewards Options Manager Role assigned';
    }

    /**
     * Gets the description of the criterion and steps to take if not met.
     *
     * @return string The detailed description of the criterion.
     */
    public function getDescription() {
        return $this->lang['rewards_rewards_manager_role_criterion_description'] ?? '';
    }
}

