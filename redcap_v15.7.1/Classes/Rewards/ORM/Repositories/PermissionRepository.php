<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories;

use Doctrine\ORM\EntityRepository;

class PermissionRepository extends EntityRepository
{
    public function findAllExcept(array $excludedNames)
    {
        return $this->createQueryBuilder('p')
            ->where('p.name NOT IN (:excludedNames)')
            ->setParameter('excludedNames', $excludedNames)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}