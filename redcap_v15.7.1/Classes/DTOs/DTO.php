<?php
namespace Vanderbilt\REDCap\Classes\DTOs;

use JsonSerializable;
use ReflectionObject;
use ReflectionProperty;
use Vanderbilt\REDCap\Classes\Traits\CanMakeDateTime;

/**
 * base for a Data Transfer Object:
 * an object that carries data between processes
 */
abstract class DTO implements JsonSerializable {

    use CanMakeDateTime;

    /**
     * list of keys that will not be exposed in 
     * the JSON serialized payload
     *
     * @var array
     */
    protected $hidden = [];
    
    /**
     *
     * @param array $data associative array
     */
    public function __construct($data=[]) {
        $this->loadData($data);
    }

    /**
     * load data from a compatible DTO or an associative array
     *
     * @param array $data
     * @return void
     */
    public function loadData($data=[]) {
        if($data instanceof DTO)
            $data = $data->getData();
        
        if(!is_array($data)) return;

        foreach ($data as $key => $value) {
            $this->setProperty($key, $value);
        }
        $this->onDataLoaded();
    }

    /**
     * set a property if exists
     * also trigger the onPropertySet method
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setProperty($key, $value) {
        $property = $this->normalizeKey($key);
        $setterMethod = $this->toCamelCase(self::SET_PREFIX . '_' . $property);

        if (method_exists($this, $setterMethod)) {
            $this->$setterMethod($value);
        } elseif (property_exists($this, $property)) {
            $visited = $this->visitProperty($key, $value);
            $this->$property = $visited;
        }
    }

    /**
     * Retrieve the value of a property if it exists and is public.
     * Uses a getter method if available.
     *
     * @param string $key
     * @param bool &$accessible Will be set to true if the property is accessible, false otherwise.
     * @return mixed|null Returns the property value or null if explicitly set.
     */
    public function getProperty($key, &$accessible = false) {
        $property = $this->normalizeKey($key);
        $getterMethod = $this->toCamelCase('get_' . $property);

        if (method_exists($this, $getterMethod)) {
            $accessible = true;
            return $this->$getterMethod();
        }

        $reflect = new ReflectionObject($this);
        if ($reflect->hasProperty($property)) {
            $prop = $reflect->getProperty($property);
            if ($prop->isPublic()) {
                $accessible = true;
                return $this->$property;
            }
        }

        $accessible = false;
        return null;
    }


    /**
     * called every time a property is set
     * override this method to adjust values
     *
     * @param string $key
     * @param mixed $value
     * @return mixed return the visited valud
     */
    public function visitProperty($key, $value) { return $value; }

    /**
     * called everytime data is loaded
     *
     * @return void
     */
    public function onDataLoaded() {}

    /**
     * normalize keys.
     *
     * @param string $key
     * @return string
     */
    private function normalizeKey($key) {
        return preg_replace('/[\.-]/', '_', $key);
    }

    private function toCamelCase($string) {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
    }

    /**
     * get an associative array with all the public key with an assigned value
     *
     * @return array
     */
    public function getData() {
        $reflect = new ReflectionObject($this);
        $properties = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
        $data = [];
    
        foreach ($properties as $property) {
            $key = $property->getName();
            $accessible = false;
            $value = $this->getProperty($key, $accessible); 
    
            // Only include properties that are accessible
            if ($accessible) {
                $data[$key] = $value;
            }
        }
    
        return $data;
    }
    

    public function __serialize(): array {
        return $this->getData();
    }

    public function __unserialize(array $data): void {
		$this->loadData($data);
    }

    const SET_PREFIX = 'set';

    /**
     * allow to use dynamic set{PropertyName}
     * setters
     *
     * @param string $name
     * @param mixed $arguments
     * @return static
     */
    public function __call($name, $arguments)
    {
        if (substr($name, 0, strlen(self::SET_PREFIX)) !== self::SET_PREFIX) {
            return $this;
        }

        $propertyName = lcfirst(substr($name, strlen(self::SET_PREFIX)));
        $propertyName = $this->normalizeKey($propertyName);

        if (!property_exists($this, $propertyName)) {
            return $this;
        }

        $this->$propertyName = $arguments[0] ?? null;
        return $this;
    }


    /**
     * create an instance using an associative array
     *
     * @param array $array
     * @return static
     */
    public static function fromArray($array) {
        return new static($array);
    }

    /**
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize() {
        $data = array_filter($this->getData(), function($value, $key) {
            return !in_array($key, $this->hidden);
        },ARRAY_FILTER_USE_BOTH);
        return $data;
    }
}