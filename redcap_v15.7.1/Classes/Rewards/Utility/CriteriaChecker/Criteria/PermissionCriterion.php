<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Doctrine\ORM\EntityRepository;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserPermissionEntity;

abstract class PermissionCriterion extends AbstractCriterion {
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
     * @var string
     */
    private $permission_name;

    /**
     *
     * @param integer $project_id
     * @param EntityRepository<UserPermissionEntity> $permissionRepository
     * @param string $permission_name
     */
    public function __construct($project_id, $userPermissionRepository, $permission_name) {
        $this->project_id = $project_id;
        $this->userPermissionRepository = $userPermissionRepository;
        $this->permission_name = $permission_name;
    }

    public function check() {
        /** @var UserPermissionEntity[] $userPermissions */
        $userPermissions = $this->userPermissionRepository->findBy(['project_id' => $this->project_id]);
        foreach ($userPermissions as $userPermission) {
            /** @var PermissionEntity $permission */
            $permission = $userPermission->getPermission();
            if($permission->getName() === $this->permission_name) return true;
        }
        return false;
    }

    
    /**
     * Provides a title of the criterion.
     *
     * @return string A human-readable title of the criterion.
     */
    abstract public function getTitle();

    /**
     * Gets the description of the criterion and steps to take if not met.
     *
     * @return string The detailed description of the criterion.
     */
    abstract public function getDescription();
}

