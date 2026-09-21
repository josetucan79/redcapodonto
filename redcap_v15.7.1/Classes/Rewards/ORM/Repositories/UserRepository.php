<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories;

use Doctrine\ORM\EntityRepository;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserPermissionEntity;

class UserRepository extends EntityRepository
{
    public function findByProjectId(int $projectId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.project_id = :projectId')
            ->setParameter('projectId', $projectId)
            ->getQuery()
            ->getResult();
    }


    /**
     * get permission for a specific user or all users in a project
     *
     * @param int $project_id
     * @param int $user_id
     * @return PermissionEntity[]|null
     */
    function getPermissions($project_id, $user_id)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('p')
            ->from(PermissionEntity::class, 'p')
            ->join(UserPermissionEntity::class, 'up', 'WITH', 'up.permission = p')
            ->join('up.user', 'u')
            ->where('up.project_id = :project_id')
            ->andWhere('u.ui_id = :user_id')
            ->setParameters([
                'project_id' => $project_id,
                'user_id' => $user_id
            ]);

        return $qb->getQuery()->getResult();
    }

    function useHasPermission($project_id, $user_id) {
        /** @var PermissionEntity $permissions */
        $userPermissions = $this->getPermissions($project_id, $user_id);
        return function($permission) use($userPermissions) {
            foreach ($userPermissions as $userPermission) {
                if($userPermission->getId()==$permission->getId()) return true;
            }
            return false;
        };
    }

    /**
     * Get permissions using entity relationships only (LESS EFFICIENT)
     *
     * @param int $project_id
     * @param int $user_id
     * @return PermissionEntity[]|null
     */
    public function getPermissionsAlt(int $project_id, int $user_id): ?array
    {
        /** @var UserEntity|null $user */
        $user = $this->find($user_id);
        if (!$user) return null;

        return array_map(
            fn(UserPermissionEntity $up) => $up->getPermission(),
            array_filter(
                $user->getUserPermissions()->toArray(),
                fn(UserPermissionEntity $up) => $up->getProjectId() === $project_id
            )
        );
    }

}