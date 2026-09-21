<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules;

class QueryParser
{
    
    private $fieldsConfig;
    
    public function __construct(array $config) {
        $this->fieldsConfig = $config;
    }
    /**
    * Build a QueryNodeInterface (GroupNode or RuleNode) from JSON data.
    *
    * @param array $data The decoded JSON array
    * @return QueryNodeInterface
    */
    public function parse(?array $data): QueryNodeInterface
    {
        if (!isset($data['type'])) {
            throw new \InvalidArgumentException("Missing 'type' in query node.");
        }
        
        if ($data['type'] === 'group') {
            return $this->parseGroup($data);
        } elseif ($data['type'] === 'rule') {
            return $this->parseRule($data);
        } else {
            throw new \InvalidArgumentException("Invalid node type: " . $data['type']);
        }
    }
    
    protected function parseRule(array $data): RuleNode
    {
        // Basic structure check
        if (!isset($data['field'], $data['condition'])) {
            throw new \InvalidArgumentException("Rule node must have 'field' and 'condition'.");
        }
        
        // 'value' can be null or absent in certain conditions (e.g. 'is null')
        $values = $data['values'] ?? null;
        
        // (Optional) Validate with your FieldConfig/ConditionConfig
        $this->validateRule($data['field'], $data['condition'], $values);

        $rule = RuleFactory::create($data['field'], $data['condition'], $values);
        
        return $rule;
        // return new RuleNode($data['field'], $data['condition'], $values);
    }
    
    protected function parseGroup(array $data): GroupNode
    {
        if (!isset($data['children']) || !is_array($data['children'])) {
            throw new \InvalidArgumentException("Group node must have an array of 'children'.");
        }
        
        $group = new GroupNode();
        
        foreach ($data['children'] as $child) {
            if (!isset($child['operator'], $child['node'])) {
                throw new \InvalidArgumentException("Each child must have 'operator' and 'node'.");
            }
            
            // Recursively parse the child node
            $node = $this->parse($child['node']);
            $group->addChild($child['operator'], $node);
        }
        
        return $group;
    }
    
    /**
    * Example of how to validate a rule against your config
    */
    protected function validateRule(string $field, string $condition, array $values): void
    {
        // $fieldsConfig is an array of FieldConfig
        
        // Find the matching FieldConfig
        $fieldConfig = null;
        foreach ($this->fieldsConfig as $fc) {
            if ($fc->name === $field) {
                $fieldConfig = $fc;
                break;
            }
        }
        
        if (!$fieldConfig) {
            throw new \InvalidArgumentException("Unknown field '$field'.", 400);
        }
        
        // Check if condition is valid for that field
        if (!isset($fieldConfig->conditions[$condition])) {
            throw new \InvalidArgumentException("Invalid condition '$condition' for field '$field'.", 400);
        }
        
        /** @var ConditionConfig $conditionConfig */
        $conditionConfig = $fieldConfig->conditions[$condition];
        
        // If condition requires a value, ensure it's present
        if ($conditionConfig->requiresValue && count($values) === 0) {
            throw new \InvalidArgumentException("Condition '$condition' requires a value, but none was provided.", 400);
        }
        
        // If condition has a set of valid options (like 'select'), check each value.
        if ($conditionConfig->inputType === 'select') {
            foreach ($values as $value) {
                if (!array_key_exists($value, $conditionConfig->options)) {
                    throw new \InvalidArgumentException("Value '$value' is not valid for field '$field' with condition '$condition'.", 400);
                }
            }
        }
    }
}
