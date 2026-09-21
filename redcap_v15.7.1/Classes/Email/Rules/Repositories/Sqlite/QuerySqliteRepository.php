<?php
namespace Vanderbilt\REDCap\Classes\Email\Rules\Repositories\Sqlite;

// Repository to manage SQLite interactions.
class QuerySqliteRepository extends BaseSqliteRepository
{

    protected string $table_name = 'queries';

    /**
     * Creates the keys table if it does not exist.
     */
    protected function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT NOT NULL,
            query TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }

    /**
     * Stores a new key record in the database.
     */
    public function store(string|array $query, string $name='', string $description=''): bool
    {
        if(is_array($query)) $query = json_encode($query);
        $sql = "INSERT INTO $this->table_name (name, description, query)
                VALUES (:name, :description, :query)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':name'         => $name,
            ':description' => $description,
            ':query' => $query,
        ]);
    }

    /**
     * Stores a new key record in the database.
     */
    public function update(int|string $id, string|array $query, string $name='', string $description=''): bool
    {
        if(is_array($query)) $query = json_encode($query);
        $sql = "UPDATE $this->table_name 
                SET name = :name, 
                    description = :description, 
                    query = :query 
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':name'         => $name,
            ':description'  => $description,
            ':query'        => $query,
            ':id'           => $id,
        ]);
    }
}
