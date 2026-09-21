<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules;

/**
 * this class represents a group in a query
 */
class GroupNode implements QueryNodeInterface
{
    public const OPERATOR_AND = 'AND';
    public const OPERATOR_OR = 'OR';
    public const OPERATOR_AND_NOT = 'AND_NOT';
    public const OPERATOR_OR_NOT = 'OR_NOT';

    private string $type;
    /**
     * @var array<int, array{operator: string, node: QueryNodeInterface}>
     */
    private array $children = [];

    public function __construct(array $children = [])
    {
        $this->type = 'group';
        // Process any initial children with validation
        foreach ($children as $child) {
            if (isset($child['operator']) && isset($child['node'])) {
                $this->addChild($child['operator'], $child['node']);
            }
        }
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<int, array{operator: string, node: QueryNodeInterface}>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    private function validateOperator(string $operator): string {
        // Validate the operator
        $operator = strtoupper($operator);
        if (!in_array($operator, [
            self::OPERATOR_AND, 
            self::OPERATOR_OR,
            self::OPERATOR_AND_NOT,
            self::OPERATOR_OR_NOT
        ])) {
            throw new \InvalidArgumentException(
                "Invalid operator: '$operator'. Must be one of: " . 
                self::OPERATOR_AND . ", " . 
                self::OPERATOR_OR . ", " .
                self::OPERATOR_AND_NOT . ", " .
                self::OPERATOR_OR_NOT
            );
        }
        return $operator;
    }

    public function addChild(string $operator, QueryNodeInterface $node): void
    {
        // Validate the operator
        $operator = $this->validateOperator($operator);

        $this->children[] = [
            'operator' => $operator,
            'node' => $node
        ];
    }

    public function toJSON(): array {
        $childrenJSON = [];
        foreach ($this->children as $child) {
            $childrenJSON[] = [
                'operator' => $child['operator'],
                'node' => $child['node']->toJSON()
            ];
        }
        return [
            'type' => $this->getType(),
            'children' => $childrenJSON,
        ];
    }
}
