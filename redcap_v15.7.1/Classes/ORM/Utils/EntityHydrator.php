<?php
namespace Vanderbilt\REDCap\Classes\ORM\Utils;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionClass;

class EntityHydrator
{
    /**
     * Hydrates an entity from an associative array using reflection
     *
     * @param array $data The data to hydrate from
     * @param object $entity The entity to hydrate
     * @param object|null $entityManager Optional EntityManager for resolving relations
     * @return object The hydrated entity
     */
    public static function hydrate(array $data, object $entity, ?object $entityManager = null): object
    {
        $reflectionClass = new \ReflectionClass(get_class($entity));
        
        foreach ($data as $property => $value) {
            // Try setter method first
            $camelProperty = self::snakeToCamel($property);
            $setterMethod = 'set' . ucfirst($camelProperty);
            if (method_exists($entity, $setterMethod)) {
                // Handle relations if EntityManager is provided
                if ($entityManager !== null && is_array($value) && isset($value['id'])) {
                    $value = self::resolveRelation($entityManager, $entity, $property, $value['id']);
                }
                
                $entity->$setterMethod($value);
                continue;
            }
            
            // Fall back to direct property setting via reflection
            try {
                $reflectionProperty = $reflectionClass->getProperty($property);
                $reflectionProperty->setAccessible(true);
                
                // Handle relations if EntityManager is provided
                if ($entityManager !== null && is_array($value) && isset($value['id'])) {
                    $value = self::resolveRelation($entityManager, $entity, $property, $value['id']);
                }
                
                $reflectionProperty->setValue($entity, $value);
            } catch (\ReflectionException $e) {
                // Property doesn't exist, skip it
                continue;
            }
        }
        
        return $entity;
    }

    /**
     * Converts a snake_case string to camelCase
     * 
     * @param string $string The snake_case string
     * @return string The camelCase result
     */
    private static function snakeToCamel($string)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
    }

    /**
     * Resolves an entity relation
     */
    private static function resolveRelation(object $entityManager, object $entity, string $property, $id)
    {
        // Make sure we're dealing with Doctrine's EntityManager
        if (method_exists($entityManager, 'getClassMetadata') && method_exists($entityManager, 'getReference')) {
            $metadata = $entityManager->getClassMetadata(get_class($entity));
            
            if ($metadata->hasAssociation($property)) {
                $targetClass = $metadata->getAssociationTargetClass($property);
                return $entityManager->getReference($targetClass, $id);
            }
        }
        
        return null;
    }

    /**
     * Converts a Doctrine entity into an associative array.
     *
     * - If an EntityManager is provided, the method uses Doctrine metadata to extract
     *   all scalar fields and association identifiers (for relations).
     * - If no EntityManager is provided, it uses reflection to extract all properties.
     *
     * Associations (e.g., ManyToOne) are represented by their identifier values if possible.
     *
     * @param object $entity The entity instance to convert to an array.
     * @param EntityManagerInterface|null $entityManager Optional Doctrine EntityManager
     *        for metadata-aware conversion (fields + associations).
     * @return array An associative array representing the entity's field names and values.
     */
    public static function entityToArray(object $entity, ?EntityManagerInterface $entityManager = null): array
    {
        $data = [];
        $reflectionClass = new \ReflectionClass(get_class($entity));

        if ($entityManager !== null) {
            /** @var ClassMetadata $meta */
            $meta = $entityManager->getClassMetadata(get_class($entity));

            foreach ($meta->getFieldNames() as $field) {
                try {
                    $reflProp = $meta->getReflectionProperty($field);
                    $reflProp->setAccessible(true);
                    $data[$field] = $reflProp->isInitialized($entity)
                        ? $reflProp->getValue($entity)
                        : null;
                } catch (\ReflectionException $e) {
                    $data[$field] = null;
                }
            }


            // Optional: handle associations (as IDs only)
            foreach ($meta->getAssociationNames() as $assoc) {
                $mapping = $meta->getAssociationMapping($assoc);
            
                // Skip collections (OneToMany, ManyToMany)
                if ($mapping['type'] & ClassMetadata::TO_MANY) {
                    // Optional: log the count or leave null
                    $data[$assoc] = null; // or count($meta->getFieldValue($entity, $assoc));
                    continue;
                }
            
                // Handle single-valued associations (ManyToOne, OneToOne)
                $assocValue = $meta->getFieldValue($entity, $assoc);
                if (is_object($assocValue)) {
                    $assocMeta = $entityManager->getClassMetadata(get_class($assocValue));
                    $id = $assocMeta->getIdentifierValues($assocValue);
                    $data[$assoc] = count($id) === 1 ? array_values($id)[0] : $id;
                } else {
                    $data[$assoc] = $assocValue;
                }
            }
            
        } else {
            // No EM: use reflection fallback
            foreach ($reflectionClass->getProperties() as $prop) {
                $prop->setAccessible(true);
                $data[$prop->getName()] = $prop->isInitialized($entity)
                    ? $prop->getValue($entity)
                    : null;
            }
        }

        return $data;
    }

}