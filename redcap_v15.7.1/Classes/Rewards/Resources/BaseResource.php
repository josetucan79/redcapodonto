<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Resources;

use JsonSerializable;
use Entity;

/**
 * Base class for resource objects that transform them into arrays suitable for API output.
 * This abstract class implements JsonSerializable to ensure all derived resource classes can be 
 * easily serialized to JSON.
 * 
 * @template T of BaseEntity
 */
abstract class BaseResource implements JsonSerializable {


    /**
     * @var T The entity associated with the resource.
     */
    protected $entity;

    /**
     * Constructor that accepts any subclass of BaseEntity.
     * 
     * @param T $entity The entity instance.
     */
    public function __construct($entity) {
        $this->entity = $entity;
    }


    /**
     * Transforms the entity into an associative array.
     *
     * @return array The array representation of the entity.
     */
    abstract public function toArray();

    /**
     * Serialize the resource to JSON.
     * 
     * @return mixed The array representation of the entity.
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize() {
        return $this->toArray();
    }
}
