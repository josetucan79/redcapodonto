<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Resources;


/**
 * Base class for resource objects that transform entities into arrays suitable for API output.
 * This abstract class implements JsonSerializable to ensure all derived resource classes can be 
 * easily serialized to JSON.
 */
abstract class BaseEntityResource extends BaseResource {

    /**
     * @var Object The entity to transform into an API consumable array.
     */
    protected $entity;

    /**
     * Constructor for BaseResource.
     *
     * @param Object $entity The entity instance to be transformed.
     */
    public function __construct($entity) {
        $this->entity = $entity;
    }

    /**
     *
     * @return Object
     */
    public function entity() { return $this->entity; }

}
