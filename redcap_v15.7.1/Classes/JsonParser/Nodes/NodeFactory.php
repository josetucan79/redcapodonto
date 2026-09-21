<?php
namespace Vanderbilt\REDCap\Classes\JsonParser\Nodes;

/**
 * Nodes factory
 */
class NodeFactory {
    /**
     * factory for node types
     * 
     * @param mixed $data
     * @param Node|null $parent
     * @return Node
     */
    public static function make($data, $parent=null) {
        if(is_null($data)) return new EmptyNode($parent);
        if(is_array($data) && count($data)===0) return new EmptyNode($parent);
        // if(is_string($data)) return $data;
        // if(is_numeric($data)) return $data;
        // if(is_array($data) && !Utils::arrayIsAssoc($data)) return new ArrayNode($data, $parent);
        return new Node($data, $parent);
    }
    
}