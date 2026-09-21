<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories;

trait PaginationTrait
{
    /**
     * Calculate pagination metadata for a query
     * 
     * @param \Doctrine\ORM\QueryBuilder $qb The query builder instance
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return array Metadata array with pagination details
     */
    protected function getPaginationMetadata(\Doctrine\ORM\QueryBuilder $qb, int $page, int $perPage): array
    {
        // Clone the query builder to create a count query
        $countQb = clone $qb;
        $countQb->select('COUNT(DISTINCT ' . $countQb->getRootAliases()[0] . '.id)')
                ->resetDQLPart('orderBy')
                ->resetDQLPart('groupBy');
        
        // Get total count
        $total = (int)$countQb->getQuery()->getSingleScalarResult();
        
        // Calculate total pages
        $totalPages = $perPage > 0 ? ceil($total / $perPage) : 0;
        
        return [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'cached' => null
        ];
    }
    
    /**
     * Apply pagination to a query builder
     * 
     * @param \Doctrine\ORM\QueryBuilder $qb The query builder instance
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return \Doctrine\ORM\QueryBuilder The modified query builder
     */
    protected function applyPagination(\Doctrine\ORM\QueryBuilder $qb, int $page, int $perPage): \Doctrine\ORM\QueryBuilder
    {
        if ($perPage > 0) {
            $qb->setFirstResult(($page - 1) * $perPage)
               ->setMaxResults($perPage);
        }
        
        return $qb;
    }
}