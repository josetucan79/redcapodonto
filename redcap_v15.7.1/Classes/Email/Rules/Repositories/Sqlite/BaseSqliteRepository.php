<?php
namespace Vanderbilt\REDCap\Classes\Email\Rules\Repositories\Sqlite;

use PDO;

// Repository to manage SQLite interactions.
abstract class BaseSqliteRepository
{
    protected $pdo;

    const DB_FILE = 'email-queries.db';

    protected string $table_name;

    public function __construct()
    {
        $dbFile = static::getDbFilePath();
        $this->pdo = new PDO("sqlite:" . $dbFile);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTable();
    }

    public static function getDbFilePath() {
        $destFolder = APP_PATH_TEMP;
        $dbFile = $destFolder.self::DB_FILE;
        return $dbFile;
    }

    /**
     * Creates the keys table if it does not exist.
     */
    abstract protected function createTable(): void;

    /**
     * Retrieves all key records from the database.
     *
     * @return array Array of all key records.
     */
    public function getAll($start=0, $limit=0, &$metadata=[]): array
    {
        // First, retrieve the total number of rows before applying any limit
        $countStmt = $this->pdo->query("SELECT COUNT(*) AS total FROM $this->table_name");
        $totalRows = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $metadata['total_rows'] = $totalRows;

        // Build the SQL query with optional LIMIT clause
        $sql = "SELECT * FROM $this->table_name";
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$start . ", " . (int)$limit;
        }

        // Execute the query and fetch the results
        $stmt = $this->pdo->query($sql);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $stmt = $this->pdo->prepare("DELETE FROM $this->table_name WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM $this->table_name WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * change the modification time so it
     * will not be collected by REDCap temp files removal
     *
     * @param BaseSqliteRepository $repo
     * @return void
     */
    static function extendRepoFileDuration(BaseSqliteRepository $repo) {
        $filePath = $repo->getDbFilePath();
        $isNewFile = !file_exists($filePath);
        $ttl = 60 * 60 * 24 * 365; // 1 year
        $lifespan = time()+$ttl;
        $accessTime = $isNewFile ? time() : fileatime($filePath);
        touch($filePath, $lifespan, $accessTime); // set the modification time of the file to its lifespan
    }

}
