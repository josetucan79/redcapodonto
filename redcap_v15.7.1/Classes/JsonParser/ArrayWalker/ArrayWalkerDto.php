<?php
namespace Vanderbilt\REDCap\Classes\JsonParser\ArrayWalker;

/**
 * Data Transfer Object for Array Walker.
 * Defines a structure for the ArrayWalker functions
 */
class ArrayWalkerDto {

    private $key;
    private $path = [];
    private $data = null;

    public function __construct($data, $basePath, $key) {
        array_push($basePath, $key); // add the new key to the base path
        $this->key = $key;
        $this->data = $data;
        $this->path = $basePath;
    }

    public function data() { return $this->data; }
    public function path() { return $this->path; }
    public function key() { return $this->key; }
    public function depth() { return count($this->path); }
    public function isLeaf() { return !( is_array($this->data) || is_object($this->data) ); }
    public function parent() {
        end($this->path);
        $parent = prev($this->path);
        return $parent;
    }

}