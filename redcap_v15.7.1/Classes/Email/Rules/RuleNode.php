<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules;

use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\HasKeyInterface;

/**
 * this class represents a node in a query
 * it should be transformed into a concrete strategy
 */
abstract class RuleNode implements QueryNodeInterface, HasKeyInterface
{
    private string $type;
    private string $field;
    private string $condition;
    private array  $values;

    public function __construct(string $field, string $condition, array $values)
    {
        $this->type = 'rule';
        $this->field = $field;
        $this->condition = $condition;
        $this->values = $values;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    abstract public static function key(): string;
    
    abstract public function toRuleQuery(): RuleQuery;

    public function toJSON(): array {
        return [
            'type' => $this->getType(),
            'field' => $this->getField(),
            'condition' => $this->getCondition(),
            'values' => $this->getValues(),
        ];
    }
}
