<?php
namespace Vanderbilt\REDCap\Classes\Email\Rules\Repositories;

use DateTime;
use Vanderbilt\REDCap\Classes\Email\DTOs\RepositoryMetadata;

// Repository to manage SQLite interactions.
class MessageRepository extends BaseRepository
{

    protected string $table_name = 'redcap_email_users_messages';

    /**
     * Stores a new key record in the database.
     */
    public function store(int $sent_by, string $subject, string $body): bool
    {
        $now = new DateTime();
        $sql = "INSERT INTO $this->table_name (`subject`, `body`, `sent_by`, `created_at`) VALUES (?, ?, ?, ?)";
        return db_query($sql, [$subject, $body, $sent_by, $now]);
    }

    /**
     * Stores a new key record in the database.
     */
    public function update(int|string $id, int $sent_by, string $subject, string $body): bool
    {
        $sql = "UPDATE $this->table_name SET `subject` = ?, `body` = ?, `sent_by` = ? WHERE id = ?";
        return db_query($sql, []);
        return $stmt->execute([$subject, $body, $sent_by, $id]);
    }

    public function getPage(int $page, int $perPage, ?RepositoryMetadata &$metadata = null) {
        $params = [];
        $queryString = "SELECT * FROM $this->table_name";
        // get metadata
        $metadata = new RepositoryMetadata(['page' => $page, 'per_page' => $perPage]);
        $result = db_query($queryString, $params);
        $metadata->setTotal(db_num_rows($result));
        $metadata->setOverallTotal($this->getTotal());
        
        // set order
        $queryString .= " ORDER BY created_at DESC";
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
}
