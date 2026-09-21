<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractPaginatedRepository extends EntityRepository
{
    /**
     * Execute a paginated query and return results with metadata
     * 
     * @param QueryBuilder $qb The query builder with filters already applied
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @param callable|null $resultProcessor Optional callback to process results
     * @return array Array with 'data' and 'metadata' keys
     */
    protected function executePaginatedQuery(
        QueryBuilder $qb, 
        ?int $page = 1, 
        ?int $perPage = 20, 
        ?callable $resultProcessor = null,
        ?array &$metadata = null
    ): array {
        // Get total count for metadata
        $countQb = clone $qb;
        $rootAlias = $countQb->getRootAliases()[0];
        $countQb->select("COUNT(DISTINCT {$rootAlias}.{$this->getClassMetadata()->getSingleIdentifierFieldName()})")
            ->resetDQLPart('orderBy')
            ->resetDQLPart('groupBy');
        
        $total = (int)$countQb->getQuery()->getSingleScalarResult();
        
        // Apply pagination
        if ($perPage > 0) {
            $qb->setFirstResult(($page - 1) * $perPage)
               ->setMaxResults($perPage);
        }
        
        // Execute query
        $results = $qb->getQuery()->getResult();
        
        // Process results if callback provided
        $data = $resultProcessor ? $resultProcessor($results) : $results;
        
        // Create metadata
        $metadata = [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $perPage > 0 ? ceil($total / $perPage) : 0,
            'cached' => null
        ];
        
        return $data;
    }
}