<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\API;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use ReflectionClass;
use Vanderbilt\REDCap\Classes\ORM\Utils\EntityHydrator;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Attributes\SoftDeleteField;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\SoftDeletableInterface;
use Vanderbilt\REDCap\Classes\Rewards\Resources\ResourceFactory;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces\FindInterface;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces\ReadInterface;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces\CreateInterface;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces\DeleteInterface;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces\UpdateInterface;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces\RestoreInterface;

abstract class RepositoryService implements CreateInterface,
                                           ReadInterface,
                                           UpdateInterface,
                                           DeleteInterface,
                                           RestoreInterface,
                                           FindInterface {

    /** @var EntityRepository $repository */
    protected $repository;


    /**
     * @param EntityManagerInterface $entityManager
     * @param string $entityClass
     */
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected string $entityClass
    ) {
        $this->entityManager = $entityManager;
        $this->entityClass = $entityClass;
        $this->repository = $entityManager->getRepository($entityClass);
    }

    /**
     * @return EntityRepository
     */
    public function repository() { 
        return $this->repository; 
    }

    /**
     * @param array $data
     * @return integer|string|null
     */
    public function create(array $data) {
        $entity = new $this->entityClass();

        $data = $this->transformInput($data);

        EntityHydrator::hydrate($data, $entity, $this->entityManager);
        
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        
        return $entity->getId(); // Assuming your entity has an getId method
    }

    /**
     * @param integer $id
     * @param array $data
     * @return mixed
     */
    public function update(int $id, array $data) {
        $entity = $this->repository->find($id);
        
        if (!$entity) {
            return false;
        }

        $data = $this->transformInput($data);

        EntityHydrator::hydrate($data, $entity, $this->entityManager);
        
        $this->entityManager->flush();
        
        return true;
    }

    /**
     * @param integer $id
     * @return Object|false
     */
    public function read(int $id) {
        $entity = $this->repository->find($id);
        
        if (!$entity) {
            return false;
        }
        
        return ResourceFactory::create(get_class($entity), $entity);
    }

    /**
     * @param integer $id
     * @return boolean
     */
    public function delete(int $id) {
        $entity = $this->repository->find($id);
        
        if (!$entity) {
            return false;
        }
        
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
        
        return true;
    }

    /**
     * @param integer $id
     * @return boolean
     */
    public function softDelete($id): bool {
        return $this->performSoftDelete($id);
    }

    /**
     * @param integer $id
     * @return boolean
     */
    protected function performSoftDelete($id): bool {
        $entity = $this->repository->find($id);
        
        if (!$entity instanceof SoftDeletableInterface) {
            return false;
        }
        
        $entity->softDelete();

        $this->entityManager->flush();
        
        return true;
    }

    /**
     * @param integer $id
     * @return boolean
     */
    public function restore(int $id) {
        $entity = $this->findWithDeleted($id);
        
        if (!$entity instanceof SoftDeletableInterface) {
            return false;
        }

        $entity->restore();

        $this->entityManager->flush();
        
        return true;
    }

    protected function getSoftDeleteField(): ?string
    {
        $reflection = new ReflectionClass($this->entityClass);
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes() as $attribute) {
                if ($attribute->getName() === SoftDeleteField::class) {
                    return $property->getName();
                }
            }
    
        }
        return null;
    }

    
    protected function findWithDeleted(int|string|array $id) {
        $meta = $this->entityManager->getClassMetadata($this->entityClass);
        $identifierFields = $meta->getIdentifierFieldNames();
    
        if (!is_array($id)) {
            // Support shorthand for single PK
            $id = [$identifierFields[0] => $id];
        }
    
        $criteria = $this->transformInput($id); // ✅ apply transformation

        $qb = $this->repository->createQueryBuilder('e');

        foreach ($criteria as $field => $value) {
            $paramName = str_replace('.', '_', $field);
            $qb->andWhere("e.{$field} = :{$paramName}")
            ->setParameter($paramName, $value);
        }
    
        return $qb->getQuery()->getOneOrNullResult();
    }
    
    
    /**
     * Find one or more records from the database table
     *
     * @param array $criteria An associative array of column names and values to filter the results
     * @param array|null $orderBy The columns to sort the results by with direction
     * @param int|null $page The desired page of results
     * @param int|null $perPage The maximum number of results to return
     * @param array|null &$metadata Metadata about the query results
     * @param bool $includeDeleted Whether to include soft-deleted records
     * @return array An array of entities
     */
    public function find(array $criteria = [], ?array $orderBy = null, ?int $page = 1, ?int $perPage = 500, &$metadata = null, bool $includeDeleted = false): array {
        $queryBuilder = $this->repository->createQueryBuilder('e');
        
        $criteria = $this->transformInput($criteria);

        // Apply criteria
        $paramCount = 0;
        foreach ($criteria as $field => $value) {
            $paramName = 'param' . $paramCount++;
            $queryBuilder->andWhere("e.$field = :$paramName")
                ->setParameter($paramName, $value);
        }
        
        // Handle soft deleted entities
        $softDeleteField = $this->getSoftDeleteField();
        if (!$includeDeleted && $softDeleteField) {
            $queryBuilder->andWhere("e.{$softDeleteField} IS NULL OR e.{$softDeleteField} = false");
        }
        
        // Apply ordering
        if ($orderBy) {
            foreach ($orderBy as $field => $direction) {
                $queryBuilder->addOrderBy("e.$field", $direction);
            }
        }
        
        // Count total results for metadata
        if ($metadata !== null) {
            $countQueryBuilder = clone $queryBuilder;
            $rootAlias = $countQueryBuilder->getRootAliases()[0];
            $identifier = $this->entityManager->getClassMetadata($this->entityClass)->getSingleIdentifierFieldName();
            $countQueryBuilder->select("COUNT(DISTINCT {$rootAlias}.{$identifier})");
            $totalCount = $countQueryBuilder->getQuery()->getSingleScalarResult();
            
            $metadata = [
                'total' => (int)$totalCount,
                'page' => $page,
                'perPage' => $perPage,
                'pageCount' => ceil($totalCount / $perPage)
            ];
        }
        
        // Apply pagination
        if ($page && $perPage) {
            $queryBuilder->setFirstResult(($page - 1) * $perPage)
                ->setMaxResults($perPage);
        }
        
        $entities = $queryBuilder->getQuery()->getResult();
        
        // Transform to resources
        $collection = [];
        foreach ($entities as $entity) {
            $collection[] = ResourceFactory::create(get_class($entity), $entity);
        }
        
        return $collection;
    }

    protected function transformInput(array $data): array {
        return $data; // no-op by default
    }
    
}