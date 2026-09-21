<?php
namespace Vanderbilt\REDCap\Classes\ORM\Utils;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\Persistence\Proxy;

/**
 * @template TRelation of object
 * @template TResult
 */
class SafeEntityAccessor
{
    /**
     * Safely access a method on a possibly proxy-related entity.
     *
     * @param TRelation|null $relation The related entity (can be a Proxy or null)
     * @param callable(TRelation): TResult $getter A callable that gets a value from the relation
     * @return TResult|null The result of the getter, or null if the relation is missing or uninitialized
     */
    public static function get(?object $relation, callable $getter)
    {
        if ($relation === null) {
            return null;
        }

        try {
            return $getter($relation);
        } catch (EntityNotFoundException $e) {
            // The proxy pointed to a missing entity — treat as null
            return null;
        }
    }
}
