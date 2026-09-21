<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules;

class RuleQuery
{
    public function __construct(
        private string $queryString,
        private array $params
    ) {}

    public function getQueryString(): string
    {
        return $this->queryString;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}
