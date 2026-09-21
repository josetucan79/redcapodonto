<?php

namespace Vanderbilt\REDCap\Classes\Email\Configuration;

use DateInterval;
use DateTime;
use Exception;

class Condition
{
    private string $textCondition;

    private function __construct(string $textCondition)
    {
        $this->textCondition = $textCondition;
    }

    public function value() {
        return $this->textCondition;
    }

    public function is($comparison) {
        if(is_string($comparison)) return $this->textCondition === $comparison;
        else if($comparison instanceof Condition) return $comparison->value() === $this->value();
        return false;
    }

    public static function fromString(string $textCondition): self
    {
        return new self($textCondition);
    }

    private $negationMap = [
        Conditions::NOT_EQUAL => Conditions::EQUAL,
        Conditions::IS_NOT => Conditions::IS,
        Conditions::HAS_NOT => Conditions::HAS,
        Conditions::DOES_NOT_CONTAIN => Conditions::CONTAINS,
        Conditions::DOES_NOT_BEGIN_WITH => Conditions::BEGINS_WITH,
        Conditions::DOES_NOT_END_WITH => Conditions::ENDS_WITH,
        Conditions::IS_NOT_NULL => Conditions::IS_NULL,
        Conditions::IS_NOT_IN => Conditions::IS_IN,
        Conditions::IS_NOT_BETWEEN => Conditions::IS_BETWEEN,
        Conditions::IS_NOT_WITHIN => Conditions::IS_WITHIN,
    ];

    private function isNegated($condition) {
        return isset($this->negationMap[$condition]);
    }

    /**
     * validate a date
     *
     * @param mixed $date
     * @return DateTime
     */
    function validateDateOrNull($date): ?DateTime {
        if ($date === null) return null;
        if ($date instanceof DateTime) return $date;

        try {
            $dt = new DateTime($date);
            return $dt;
        } catch (Exception $e) {
            return null;
        }
    }
    

    /**
     * Applies the condition to given values to produce:
     * - $conditionExpression (e.g. "=", "LIKE", "IS NULL")
     * - $params (array of bound values, or empty)
     */
    public function applyToValues(array &$params): string
    {
        // default operator
        $conditionExpression = '= ?';
        $isNegated = $this->isNegated($this->textCondition);

        switch (strtolower($this->textCondition)) {
            case Conditions::EQUAL:
            case Conditions::IS:
            case Conditions::HAS:
            case Conditions::NOT_EQUAL:
            case Conditions::IS_NOT:
            case Conditions::HAS_NOT:
                $conditionExpression = $isNegated ? '!= ?' : '= ?';
                break;
            case Conditions::LESS_THAN:
                $conditionExpression = '< ?';
                break;
            case Conditions::LESS_THAN_EQUAL:
                $conditionExpression = '<= ?';
                break;
            case Conditions::GREATER_THAN:
                $conditionExpression = '> ?';
                break;
            case Conditions::GREATER_THAN_EQUAL:
                $conditionExpression = '>= ?';
                break;
            case Conditions::CONTAINS:
            case Conditions::DOES_NOT_CONTAIN:
                $conditionExpression = 'LIKE ?';
                if($isNegated) $conditionExpression = 'NOT '.$conditionExpression;
                $value = $params[0] ?? '';
                $params = ["%$value%"];
                break;
            case Conditions::BEGINS_WITH:
            case Conditions::DOES_NOT_BEGIN_WITH:
                $conditionExpression = 'LIKE ?';
                if($isNegated) $conditionExpression = 'NOT '.$conditionExpression;
                $value = $params[0] ?? '';
                $params = ["$value%"];
                break;
            case Conditions::ENDS_WITH:
            case Conditions::DOES_NOT_END_WITH:
                $conditionExpression = 'LIKE ?';
                if($isNegated) $conditionExpression = 'NOT '.$conditionExpression;
                $value = $params[0] ?? '';
                $params = ["%$value"];
                break;
            case Conditions::IS_NULL:
            case Conditions::IS_NOT_NULL:
                $conditionExpression = $isNegated ? 'IS NOT NULL': 'IS NULL';
                $params = [];
                break;
            case Conditions::IS_IN:
            case Conditions::IS_NOT_IN:
                $sqlPlaceholder = dbQueryGeneratePlaceholdersForArray($params);
                $conditionExpression = "IN ($sqlPlaceholder)";
                if($isNegated) $conditionExpression = 'NOT '.$conditionExpression;
                break;
            case Conditions::IS_BETWEEN:
            case Conditions::IS_NOT_BETWEEN:
                if(count($params) != 2) throw new Exception("Error; exactly 2 values are needed for this condition", 400);
                $conditionExpression = 'BETWEEN ? AND ?';
                if($isNegated) $conditionExpression = 'NOT '.$conditionExpression;
                break;
            case Conditions::IS_WITHIN:
            case Conditions::IS_NOT_WITHIN:
                $intervalString = $params[0] ?? null;
                $interval = DateInterval::createFromDateString($intervalString);
                if(!$interval instanceof DateInterval) throw new Exception("Error. Please provide a valid interval. '$intervalString' was provided.", 400);
                $referenceDateParam = $params[1] ?? null;
                $referenceDate = $this->validateDateOrNull($referenceDateParam);
                if(!$referenceDate instanceof DateTime) throw new Exception("Error. Please provide a valid reference date. '$referenceDateParam' was provided.", 400);
                $pasteDate = clone $referenceDate;

                $pasteDate->sub($interval);

                // Format the dates for MySQL (Y-m-d H:i:s)
                $referenceStr = $referenceDate->format('Y-m-d H:i:s');
                $pastStr = $pasteDate->format('Y-m-d H:i:s');
                $conditionExpression = 'BETWEEN ? AND ?';
                if($isNegated) $conditionExpression = 'NOT '.$conditionExpression;
                $params = [$pastStr, $referenceStr];
                break;
            default:
                // fallback or throw an exception
                throw new \InvalidArgumentException("Unknown condition: {$this->textCondition}");
        }

        return $conditionExpression;
    }
}
