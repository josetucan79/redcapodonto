<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Resources;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Vanderbilt\REDCap\Classes\Rewards\DTOs\REDCapRecordDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\AccessTokenEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Entities\BaseEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProviderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\RewardOptionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Settings\Project\ProjectSettingsValueObject;

/**
 * Class ResourceFactory
 *
 * Factory class to create resource instances based on the provided data.
 *
 * @package Vanderbilt\REDCap\Classes\Rewards\Resources
 *
 */
class ResourceFactory {
    /**
     * Create resource instances based on the provided data.
     * 
     * @template TEntity of BaseEntity
     * @template TResource of BaseResource
     * 
     * @param class-string<TEntity>|class-string<TResource> $class The class name of the entity or resource to create.
     * @param BaseEntity|BaseEntity[] $data An array or a single entity object.
     * @param mixed ...$args Additional arguments to pass to the resource constructor.
     * @return ($class is class-string<TEntity> ? BaseResource<TEntity> : TResource) An instance of the specified repository class.
     * @throws \InvalidArgumentException If an unsupported entity type is provided.
     *
     */
    public static function create($class, $data, ...$args) {
        // Handle null data
        if (is_null($data)) return null;
        
        // Handle Doctrine PersistentCollection
        if ($data instanceof PersistentCollection) {
            // Create a new collection of the appropriate type
            $collection = new ArrayCollection();
            
            // Process each entity in the persistent collection
            foreach ($data as $entity) {
                // If we're transforming to a different class, process each entity
                if ($entity::class !== $class) {
                    $transformedEntity = self::create($class, $entity, ...$args);
                    if ($transformedEntity !== null) {
                        $collection->add($transformedEntity);
                    }
                } else {
                    // If same class, just add to the collection
                    $collection->add($entity);
                }
            }
            
            return self::create($class, $collection->toArray());
        }
        
        // Handle ArrayCollection
        if ($data instanceof ArrayCollection || $data instanceof Collection) {
            if($data->isEmpty()) return [];
            return self::create($class, $data->toArray(), ...$args);
        }

        if (is_array($data)) {
            if (empty($data))  return [];
            // Process each item in the array individually
            return array_map(function ($item) use($class, $args) {
                // Recursively call create for each item
                return self::create($class, $item, ...$args);
            }, $data);
        } else {

            // Determine if the class is a repository or an entity
            if (is_subclass_of($class, BaseResource::class)) {
                // If a repository class is provided
                $resource = new $class($data, ...$args);
            } else {
                // Map entity class to resource class based on naming convention or custom logic
                $resourceClass = self::inferResourceClass($class);

                if ($resourceClass === null) {
                    throw new \InvalidArgumentException("Unsupported entity type: $class");
                }
                // If an entity class is provided, create a generic Resource for the entity
                $resource = new $resourceClass($data, ...$args);
            }

            return $resource;
        }
    }

    /**
     * Infer the resource class based on the entity class name.
     *
     * @param string $entityClass The class name of the entity.
     * @return string|null The corresponding resource class name or null if not supported.
     */
    protected static function inferResourceClass(string $entityClass): ?string {
        // Mapping or naming convention to convert entity class to resource class
        $map = [
            AccessTokenEntity::class => AccessTokenResource::class,
            RewardOptionEntity::class => RewardOptionResource::class,
            ProviderEntity::class => ProviderResource::class,
            OrderEntity::class => OrderResource::class,
            ActionEntity::class => ActionResource::class,
            REDCapRecordDTO::class => REDCapRecordResource::class,
            ProjectSettingsValueObject::class => ProjectSettingsResource::class,
            // Add more mappings if necessary
        ];

        return $map[$entityClass] ?? null;
    }

    
}