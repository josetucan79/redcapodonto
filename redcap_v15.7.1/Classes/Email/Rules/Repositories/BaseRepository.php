<?php
namespace Vanderbilt\REDCap\Classes\Email\Rules\Repositories;

use Exception;
use Vanderbilt\REDCap\Classes\Email\DTOs\RepositoryMetadata;

// Repository to manage SQLite interactions.
abstract class BaseRepository
{
    protected string $table_name;

    public function __construct(){}

    /**
     * Retrieves all key records from the database.
     *
     * @return array Array of all key records.
     */
    public function getAll($start=0, $limit=0, &$metadata=[]): array
    {
        // First, retrieve the total number of rows before applying any limit
        $result = db_query("SELECT COUNT(*) AS total FROM $this->table_name");
        $row = db_fetch_assoc($result);
        $totalRows = (int)$row['total'] ?? 0;
        $metadata['total_rows'] = $totalRows;

        // Build the SQL query with optional LIMIT clause
        $sql = "SELECT * FROM $this->table_name";
        $params = [];
        if ($limit > 0) {
            $sql .= " LIMIT ?, ?";
            $params = [$start, $limit];
        }

        // Execute the query and fetch the results
        $result = db_query($sql, $params);
        $list = [];
        while($row = db_fetch_assoc($result)) {
            $list[] = $row;
        }

        // Store the number of rows returned after applying the limit
        $metadata['returned_rows'] = count($list);

        return $list;
    }

    /**
     * Deletes a key record from the database.
     *
     * @param string $kid The key identifier to delete.
     *
     * @return bool True if deletion was successful, false otherwise.
     */
    public function delete(string $id): bool
    {
        $queryString = "DELETE FROM $this->table_name WHERE id = ?";
        return db_query($queryString, [$id]);
    }

    public function getById(string $id): ?array
    {
        $queryString = "SELECT * FROM $this->table_name WHERE id = ?";
        $result = db_query($queryString, [$id]);
        $row = db_fetch_assoc($result);
        return $row ? $row : null;
    }

    public function getPage(int $page, int $perPage, ?RepositoryMetadata &$metadata = null) {
        $params = [];
        $queryString = "SELECT * FROM $this->table_name";
        // get metadata
        $metadata = new RepositoryMetadata(['page' => $page, 'per_page' => $perPage]);
        $result = db_query($queryString, $params);
        $metadata->setTotal(db_num_rows($result));
        $metadata->setOverallTotal($this->getTotal());
        
        // get results
        $start = ($page-1) * $perPage;
        $paginationQuery = $queryString . " LIMIT ?, ?";
        $paginationParams = array_merge($params, [$start, $perPage]);
        $paginatedResult = db_query($paginationQuery, $paginationParams);
        $metadata->setPartialTotal(db_num_rows($paginatedResult));
        $list = [];
        while($row = db_fetch_assoc($paginatedResult)) {
            $list[] = $row;
        }
        return $list;
    }

    protected function getTotal() {
        // first get the unfiltered total
        $totalResult = db_query("SELECT COUNT(1) AS total FROM $this->table_name");
        if(!$totalResult) throw new Exception("Cannot determine total number", 400);
        $row = db_fetch_array($totalResult);
        return intval($row['total']);
    }

}
