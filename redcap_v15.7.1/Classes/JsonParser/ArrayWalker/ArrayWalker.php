<?php
namespace Vanderbilt\REDCap\Classes\JsonParser\ArrayWalker;

use SplQueue;
use Generator;

/**
 * provides functions for traversiong a graph
 */
class ArrayWalker {

    protected $data;

    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * create an instance from an array
     *
     * @param array $array
     * @return ArrayWalker
     */
    public static function fromArray($array){

            return new self($array);
    }

    /**
     * helper to match for equality
     *
     * @param array $array
     * @param mixed $expectedValue
     * @return ArrayWalkerDto
     */
    public function bfsEquals($expectedValue)
    {
        /** @param ArrayWalkerDto $dto */
        $callback = function($dto) use($expectedValue) {
            $value = $dto->data();
            return $value===$expectedValue;
        };
        return $this->BFS($callback);
    }

    /**
     *
     * @param callable $callback
     * @param Generator $traverseMethod
     * @return ArrayWalkerDto
     */
    public function search($callback, $traverseMethod) {
        // make a default callback that compares a node to a value
        if(!is_callable($callback)) return;

        $generator = $traverseMethod();
        while($dto = $generator->current()) {   
            if($callback($dto)===true) return $dto; // return a DTO if the callback function matches
            $generator->next();
        }
    }

    /**
     *
     * @param callable $callback
     * @return ArrayWalkerDto
     */
    public function BFS($callback) {
        // make a default callback that compares a node to a value
        if(!is_callable($callback)) return;

        $generator = $this->BF();
        while($dto = $generator->current()) {   
            if($callback($dto)===true) return $dto; // return a DTO if the callback function matches
            $generator->next();
        }
    }

    /**
     *
     * @param callable $callback
     * @return ArrayWalkerDto
     */
    public function DFS($callback) {
        // make a default callback that compares a node to a value
        if(!is_callable($callback)) return;

        $generator = $this->DF();
        while($dto = $generator->current()) {   
            if($callback($dto)===true) return $dto; // return a DTO if the callback function matches
            $generator->next();
        }
    }

    /**
     * traverse an array using BFS (breadth first search).
     * apply a callback to match the desired node.
     * this is a genarator function, so all matches will be returned
     *
     * @param array $array
     * @param callable $callback
     * @param array $path
     * @param SplQueue $queue keeps a reference to nested data that must be processed
     * @return Generator
     */
    public function BF($path=[], $queue=null)
    {
        if(!($queue instanceof SplQueue)) $queue = new SplQueue();
        foreach ($this->data as $key => $current) {
            // traverse all the sibilings
            $dto = new ArrayWalkerDto($current, $path, $key);
            yield $dto; // return a DTO
            // add elements to a queue if is an array with children for further processing
            if(is_array($current) && count($current)>0) $queue->enqueue($dto);
        }

        while(!($queue->isEmpty())) {
            // process nested arrays
            /** @var ArrayWalkerDto $subDto */
            $subDto = $queue->dequeue();
            $instance = ArrayWalker::fromArray($subDto->data());
            $generator = $instance->BF($subDto->path(), $queue);
            // yield from the nested generator
            yield from $generator;
        }
    }

    /**
     * traverse an array using DFS (depth first search).
     * apply a callback to match the desired node.
     * this is a genarator function, so all matches will be returned
     *
     * @param callable $callback
     * @param array $path
     * @return Generator
     */
    public function DF($path=[])
    {
        foreach ($this->data as $key => $current) {
            // traverse all the sibilings
            $dto = new ArrayWalkerDto($current, $path, $key);
            yield $dto; // return a DTO
            // process nested arrays recursively
            if(is_array($current) && count($current)>0) {
                $subDto = new ArrayWalkerDto($current, $path, $key);
                $instance = ArrayWalker::fromArray($subDto->data());
                $generator = $instance->DF($subDto->path());
                // yield from the nested generator
                yield from $generator;
            }
        }

    }
    
    


}