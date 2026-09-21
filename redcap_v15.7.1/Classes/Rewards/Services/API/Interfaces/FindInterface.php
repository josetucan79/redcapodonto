<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces;

/**
 * Interface for finding records.
 */
interface FindInterface {

    /**
     * Find one or more records from the database table.
     *
     * @param array $criteria An associative array of column names and values to filter the results
     * @param string|null $order The column to sort the results by
     * @param string|null $direction The direction to sort the results in
     * @param int|null $page The desired page of results
     * @param int|null $perPage The maximum number of results to return
     * @param mixed $metadata Metadata for pagination or other information
     * @param bool $includeDeleted Whether to include deleted records in the results
     * @return array An array of associative arrays representing the records
     */
    public function find(array $criteria = [], ?array $orderBy = null, ?int $page = 1, ?int $perPage = 500, &$metadata = null, bool $includeDeleted = false): array;
}
