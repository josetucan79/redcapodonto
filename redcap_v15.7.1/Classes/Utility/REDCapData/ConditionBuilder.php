<?php

namespace Vanderbilt\REDCap\Classes\Utility\REDCapData;

class ConditionBuilder {
    private $conditions = [];

    public static function make() { return new self(); }

    /**
     * Add a condition to the builder.
     */
    public function addCondition($field_name, $value = null, $operator = '=', $logicalOperator = 'AND') {
        // Normalize operators
        $operator = strtoupper($operator);
        $logicalOperator = strtoupper($logicalOperator);

        switch ($operator) {
            // Operators without a value
            case 'IS NULL':
            case 'IS NOT NULL':
                $this->conditions[] = [
                    'type'     => 'condition',
                    'clause'   => "$field_name $operator",
                    'params'   => [],
                    'operator' => $logicalOperator
                ];
                break;

            // Operators with a single value
            case '=':
            case '!=':
            case '<>':
            case '>':
            case '<':
            case '>=':
            case '<=':
            case 'LIKE':
            case 'NOT LIKE':
            case 'REGEXP':
            case 'NOT REGEXP':
            case 'SOUNDS LIKE':
                $this->conditions[] = [
                    'type'     => 'condition',
                    'clause'   => "$field_name $operator ?",
                    'params'   => [$value],
                    'operator' => $logicalOperator
                ];
                break;

            // Operators with a list of values
            case 'IN':
            case 'NOT IN':
                if (is_array($value) && !empty($value)) {
                    $placeholders = implode(', ', array_fill(0, count($value), '?'));
                    $this->conditions[] = [
                        'type'     => 'condition',
                        'clause'   => "$field_name $operator ($placeholders)",
                        'params'   => $value,
                        'operator' => $logicalOperator
                    ];
                } else {
                    throw new \InvalidArgumentException("Value for '$operator' must be a non-empty array.");
                }
                break;

            // Operators with two values
            case 'BETWEEN':
            case 'NOT BETWEEN':
                if (is_array($value) && count($value) === 2) {
                    $this->conditions[] = [
                        'type'     => 'condition',
                        'clause'   => "$field_name $operator ? AND ?",
                        'params'   => [$value[0], $value[1]],
                        'operator' => $logicalOperator
                    ];
                } else {
                    throw new \InvalidArgumentException("Value for '$operator' must be an array with exactly two elements.");
                }
                break;

            // Operators with subqueries
            case 'EXISTS':
            case 'NOT EXISTS':
                if (!empty($value)) {
                    $this->conditions[] = [
                        'type'     => 'condition',
                        'clause'   => "$operator ($value)",
                        'params'   => [],
                        'operator' => $logicalOperator
                    ];
                } else {
                    throw new \InvalidArgumentException("Value for '$operator' must be a valid subquery.");
                }
                break;

            default:
                throw new \InvalidArgumentException("Invalid operator: $operator");
        }

        return $this;
    }

    /**
     * Add a group of conditions to the builder.
     *
     * This method allows you to group multiple conditions together with their own
     * logic. The provided callback will receive a new instance of ConditionBuilder,
     * allowing you to add conditions inside the group. The group will then be
     * combined with the other conditions in the main builder using the specified
     * logical operator (AND/OR).
     *
     * Example usage:
     * 
     * $builder->addGroup(function ($group) {
     *     $group->addCondition('status', 'active')
     *           ->addCondition('subscription', 'premium', '=', 'OR');
     * }, 'AND');
     *
     * The above example will group conditions `status = 'active'` and 
     * `subscription = 'premium'` together, applying an 'OR' logic within the 
     * group, and combine it with other conditions using an 'AND' logic.
     *
     * @param callable $callback A callback function that receives a new ConditionBuilder
     *                           instance to define the group of conditions.
     * @param string $logicalOperator The logical operator to apply when combining this group
     *                                with other conditions in the builder (AND/OR).
     * @return self Returns the current instance of ConditionBuilder for method chaining.
     */
    public function addGroup(callable $callback, $logicalOperator = 'AND') {
        $group = new self();

        // Build the group using the provided callback
        $callback($group);

        $this->conditions[] = [
            'type'     => 'group',
            'group'    => $group,
            'operator' => strtoupper($logicalOperator)
        ];

        return $this;
    }

    /**
     * Build the condition clauses and parameters.
     */
    public function build() {
        $clauses = [];
        $params  = [];

        foreach ($this->conditions as $index => $condition) {
            $operator = $condition['operator'];

            // Skip the operator for the first condition
            if ($index === 0) {
                $operator = '';
            }

            if ($condition['type'] === 'condition') {
                $clauses[] = trim("$operator " . $condition['clause']);
                $params   = array_merge($params, $condition['params']);
            } elseif ($condition['type'] === 'group') {
                $groupResult = $condition['group']->build();
                $clauses[] = trim("$operator (" . $groupResult['clause'] . ")");
                $params   = array_merge($params, $groupResult['params']);
            }
        }

        $clause = implode(' ', $clauses);

        return [
            'clause' => $clause,
            'params' => $params
        ];
    }
}
