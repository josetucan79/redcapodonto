<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts;

interface LoggableEntityInterface
{
    /**
     * Returns a lightweight associative array of entity fields
     * to be stored in the log's payload column.
     *
     * This method should avoid returning large relationships or collections.
     *
     * @return array
     */
    public function toLogArray(): array;
}