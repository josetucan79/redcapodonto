<?php
namespace Vanderbilt\REDCap\Classes\Rewards\DTOs;

use JsonSerializable;
use ReflectionObject;
use ReflectionProperty;

/**
 * Represents an abstract Data Transfer Object (DTO) base class for creating objects
 * that can be serialized into JSON format. This class is designed to be inherited by
 * other classes that implement specific API response structures.
 *
 * @abstract
 */
abstract class BaseDTO implements JsonSerializable {

    /**
     * Constructs a new instance of a DTO, optionally initializing with values.
     *
     * @param array $params Associative array of properties to initialize on the object.
     */
    public function __construct($params = []) {
        $this->loadData($params);
    }

    public function loadData($params = []) {
        foreach ($params as $key => $value) {
            $this->setProperty($key, $value);
        }
    }

    /**
     * Sets a property on the object if it exists. If a setter method exists for the property,
     * the setter is called to assign the value.
     *
     * @param string $key The property name to set.
     * @param mixed $value The value to set to the property.
     */
    public function setProperty(string $key, $value) {
        if (!property_exists($this, $key)) return;

        // If a method exists with the same name as the key, use it to transform the value before setting the property.
        if (method_exists($this, $key)) {
            $value = $this->$key($value);
        }
        
        $this->$key = $value;
    }

    /**
     * Creates a collection of DTO objects from an array of data sets.
     *
     * @param array $list An array of associative arrays, each representing properties to initialize a DTO object.
     * @return array An array of initialized DTO objects.
     */
    public static function collection($list=[]) {
        return array_map(function($data) { return new static($data); }, $list);
    }

     /**
     * Creates a DTO object from an associative array.
     *
     * @param string $data asssociative array representing the properties to initialize the DTO object.
     * @return self Returns an instance of the DTO class initialized with data.
     */
    public static function from($data) {
        return new static($data);
    }

    /**
     * Creates a DTO object from a JSON string.
     *
     * @param string $json JSON string representing the properties to initialize the DTO object.
     * @return self Returns an instance of the DTO class initialized with data decoded from JSON.
     */
    public static function fromJson($json) {
        $data = json_decode($json, true);
        return new static($data);
    }

    /**
     * Converts the DTO object into an associative array using its public properties.
     *
     * @return array Associative array containing the public properties and their values of the DTO object.
     */
    public function toArray() {
        $reflect = new ReflectionObject($this);
        $properties = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
        $data = [];
        foreach ($properties as $property) {
            $data[$property->getName()] = $property->getValue($this);
        }
        return $data;
    }

    public function __serialize(): array {
        return $this->toArray();
    }

    public function __unserialize(array $data): void {
		$this->loadData($data);
    }

    /**
     * Serializes the DTO object into a format that can be converted to JSON. Implements the JsonSerializable interface.
     *
     * @return array The array representation of the DTO object.
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array {
        return $this->toArray();
    }
}