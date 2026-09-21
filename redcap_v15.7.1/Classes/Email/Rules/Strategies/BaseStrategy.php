<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules\Strategies;

use Vanderbilt\REDCap\Classes\Email\Rules\RuleNode;

abstract class BaseStrategy extends RuleNode
{
    const FIELD = '';

    public function __construct(string $condition, array $values) {
        if (static::FIELD === '') {
            throw new \Exception('Subclasses must define their own FIELD constant.');
        }
        parent::__construct(self::FIELD, $condition, $values);
    }

    public static function key(): string { return static::FIELD; }

}
