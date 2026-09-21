<?php
namespace Vanderbilt\REDCap\Classes\JsonParser\Nodes;

use ArrayAccess;
use JsonSerializable;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Vanderbilt\REDCap\Classes\JsonParser\Helpers\Utils;
use Vanderbilt\REDCap\Classes\JsonParser\Filters\FilterFactory;
use Vanderbilt\REDCap\Classes\JsonParser\ArrayWalker\ArrayWalker;
use Vanderbilt\REDCap\Classes\JsonParser\ArrayWalker\ArrayWalkerDto;

/**
 * a base Node, where data is an object
 */
class Node implements ArrayAccess, JsonSerializable {

    const DEFAULT_TEXT_SEPARATOR = ' ';

    /**
     *
     * @var Node
     */
    protected $parent;

    /**
     *
     * @var mixed
     */
    protected $data;
    
    public function __construct($data, $parent=null)
    {
        $this->parent = $parent;
        $this->data = $data;
    }

    public function getParent() {
        $parent = $this->parent;
        if(is_null($parent)) return new EmptyNode($this);
        return $this->parent;
    }

    /**
     * return data
     *
     * @return mixed
     */
    public function getData() {
        return $this->data;
    }

    /**
     * compare to anmother node
     *
     * @param Node $other
     * @return bool
     */
    public function equals($other) {
        $thisValue = $this->getData();
        $otherValue = $other->getData();
        if( is_numeric($thisValue) ) return $thisValue === $otherValue;
        if( is_string($thisValue) ) return strcmp($thisValue, $otherValue) === 0;
        return $thisValue === $otherValue;
    }

    /**
     * alternative for select
     *
     * @param mixed ...$args
     * @return Node
     */
    public function any($condition, ...$args) {
        return $this->traverse($condition, $maxDepth=0, $limit=0, ...$args);
    }

    /**
     * alternative for select
     * return the first item matching the condition
     * 
     * @param mixed ...$args
     * @return Node
     */
    public function first($condition, ...$args) {
        return $this->traverse($condition, $maxDepth=0, $limit=1, ...$args);
    }

    /**
     * select any child matching the specified condition
     *
     * @param string $condition
     * @param integer $maxDepth maximum traversal depth level
     * @param integer $limit maximum results
     *  if 0, then no limit.
     *  if negative, then remove results from the end.
     * @param mixed ...$args
     * @return Node
     */
    public function traverse($condition, $maxDepth=0, $limit=1, ...$args) {
        $keyFilter = FilterFactory::make($condition, ...$args);
        $generator = ArrayWalker::fromArray($this->data)->BF();
        $results = [];
        /** @var ArrayWalkerDto $dto */
        while( $dto = $generator->current() ) {
            $generator->next();
            if($maxDepth>0 && count($dto->path()) > $maxDepth ) break; // limit depth
            if( $limit>0 && count($results) >= $limit ) break; // limit results
            if($keyFilter($dto->key()) === true) $results[] = $dto->data();
        }
        $results = Utils::normalizeArray($results);
        if($limit<0) $results = array_splice($results, 0, $limit);
        return NodeFactory::make($results, $this);
    }

    /**
     * Select immediate children matching the specified condition.
     * Selected data will always return a list of results
     *
     * @param string $condition
     * @param mixed ...$args
     * @return Node
     */
    public function select($condition, ...$args) {
        // return $this->traverse($condition, $maxDepth=1, $limit=1, ...$args);
        $keyFilter = FilterFactory::make($condition, ...$args);
        $generator = ArrayWalker::fromArray($this->data)->BF();
        $results = [];

        /** @var ArrayWalkerDto $dto */
        while( ($dto = $generator->current())) {
            $generator->next();
            $key = $dto->key();
            if(is_numeric($key)) continue; // ignore numeric keys
            $visitedPath = $dto->path();
            $nonNumericPath = array_filter($visitedPath, function($key) { return !is_numeric($key); });
            $nonNumericDepth = count($nonNumericPath);
            if($nonNumericDepth>1) break; // exit after when all direct non-numeric children have been processed
            // if there is a match, then store the data
            if($keyFilter($key) === true) {
                $results[] = $dto->data();
            }
        }
        $results = Utils::normalizeArray($results);
        return NodeFactory::make($results, $this);
    }

    /**
     * return a list of results matching the creiteria
     *
     * @param array ...$criteriaList
     * @return Node
     */
    public function get(...$criteriaList) {
        if(!Utils::is_multidimensional_array($criteriaList)) $criteriaList = [$criteriaList];
        $current = $this;
        foreach ($criteriaList as $criteria) {
            if($current instanceof EmptyNode) return $current;
            $current = $current->select(...$criteria);
        }
        return $current;
    }

    /**
     * filter children matching the where clause
     *
     * @param array|string $path
     * @param string $condition
     * @param mixed $expected
     * @param mixed ...$args
     * @return Node
     */
    public function where($path, $condition, $expected=null, ...$args) {
        if(!is_array($this->data)) return new EmptyNode($this);
        if(is_string($path)) $path = explode('.', $path);
        // select the item specified in the path

        $valueFilter = FilterFactory::make($condition, $expected, ...$args);
        $generator = ArrayWalker::fromArray($this->data)->DF();
        $results = [];
        $maxNonNumericLength = count($path);
        /** @var ArrayWalkerDto $dto */
        while( ($dto = $generator->current())) {
            $generator->next();
            if(is_numeric($dto->key())) continue; // ignore numeric keys
            $visitedPath = $dto->path();
            $nonNumericPath = array_filter($visitedPath, function($key) { return !is_numeric($key); });
            $nonNumericDepth = count($nonNumericPath);
            if($nonNumericDepth != $maxNonNumericLength) continue; // do not process unless same non numeric depth
            if(empty(array_diff($path, $nonNumericPath))) {
                // if there is a match, then register the index of the branch as matching
                if($valueFilter($dto->data()) === true) {
                    $matchingIndex = reset($visitedPath);
                    $results[] = @$this->data[$matchingIndex];
                }
            }
        }
        
        if(count($results)>0) return NodeFactory::make($results, $this); // new node with filtered results
        return new EmptyNode($this);
    }

    /**
     * further filter children using OR
     *
     * @param string $key
     * @param string $condition
     * @param mixed $expected
     * @param mixed ...$args
     * @return Node
     */
    public function orWhere($key, $condition, $expected=null, ...$args) {
        $value = $this->getData(); // check if a value was already found
        if( !is_null($value)) return $this;
        $parent = $this->getParent();
        $found = $parent->where($key, $condition, $expected, ...$args);
        return $found;
    }

    public function flatten() {
        $flat = Utils::normalizeArray($this->data);
        return NodeFactory::make($flat, $this);
    }

    /**
     * access properties
     *
     * @param string $name
     * @return Node
     */
    public function __get($name)
    {
        return $this->get('=', $name);
    }

    
    /** provide value when class is invoked */
    public function __invoke() { return $this->getData(); }

    /** regular expression for valid function names */
    private static $functionNameRegExp = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';

    /**
     * provide shortcuts for methods
     */
    public function __call($name, $arguments)
    {
        $funcRegExp = self::$functionNameRegExp;
        $whereFuncRegExp = "^\_where_({$funcRegExp}$)";
        if(preg_match("/$whereFuncRegExp/", $name, $matches)) {
            return $this->where($matches[1], ...$arguments);
        }
        $orWhereFuncRegExp = "^\_orWhere_({$funcRegExp}$)";
        if(preg_match("/$orWhereFuncRegExp/", $name, $matches)) {
            return $this->orWhere($matches[1], ...$arguments);
        }
        return null;
    }

    #[\ReturnTypeWillChange] 
    public function jsonSerialize(): array {
        $data = $this->getData();
        if(!is_array($data)) $data = [$data];
        return $data;
    }

    public function printJSON() {
        $string = json_encode($this->data, JSON_PRETTY_PRINT);
        return $string;
    }

    public function __toString() { return $this->join(); }

    /**
     * transform the underlying data to array
     *
     * @return array
     */
    public function toArray() {
        $data = $this->getData();
        if(is_array($data) || is_object($data)) return json_decode(json_encode($data), true);
        else return [$data];
    }

    /**
     * Retrieve the leaves of an array using a recursive iterator.
     *
     * This function leverages PHP's RecursiveArrayIterator and RecursiveIteratorIterator
     * to perform a depth-first traversal of the input array and extract all leaves.
     * It returns an array containing all the leaves found in the array.
     *
     * @return array An array containing the extracted leaves.
     */
    public function getLeaves() {
        $arrayIterator = new RecursiveArrayIterator($this->toArray());
        $recursiveIterator = new RecursiveIteratorIterator( $arrayIterator, $mode = RecursiveIteratorIterator::LEAVES_ONLY );
        $leaves =[];
        foreach( $recursiveIterator as $key => $value ){
           $leaves[] = $value;
        }
        return $leaves;
    }

    /**
     * return a version of the node that contains
     * only the leaves
     *
     * @return Node
     */
    public function leaves() {
        $leaves = $this->getLeaves();
        return NodeFactory::make($leaves, $this);
    }

    /**
     * join all leaf values using the specified separator
     *
     * @param string $separator
     * @return string
     */
    public function join($separator=self::DEFAULT_TEXT_SEPARATOR) {
        $leaves = $this->getLeaves();
        return implode($separator, $leaves);
    }

    /**
     * alias for the join function
     *
     * @param string $separator
     * @return string
     */
    public function text($separator=self::DEFAULT_TEXT_SEPARATOR) {
        return $this->join($separator);
    }

    /**
     * ArrayAccess Interface function
     *
     * @param mixed $offset
     * @return boolean
     */
    public function offsetExists($offset): bool {
        return isset($this->data[$offset]);
    }

    /**
     * ArrayAccess Interface function
     *
     * @param mixed $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        $data = isset($this->data[$offset]) ? $this->data[$offset] : null;
        return NodeFactory::make($data, $this);
    }
    
    /**
     * ArrayAccess Interface function
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }
    
    /**
     * ArrayAccess Interface function
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void {
        unset($this->data[$offset]);
    }

}