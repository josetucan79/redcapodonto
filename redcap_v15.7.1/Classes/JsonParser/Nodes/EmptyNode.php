<?php
namespace Vanderbilt\REDCap\Classes\JsonParser\Nodes;



/**
 * a Node with no data
 */
class EmptyNode extends Node {

    public function __construct($parent=null)
    {
        parent::__construct(null, $parent);
    }

    public function __get($name)
    {
        return new EmptyNode($this);
    }
}