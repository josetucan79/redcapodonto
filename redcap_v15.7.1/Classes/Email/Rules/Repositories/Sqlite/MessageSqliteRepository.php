<?php
namespace Vanderbilt\REDCap\Classes\Email\Rules\Repositories\Sqlite;

// Repository to manage SQLite interactions.
class MessageSqliteRepository extends BaseSqliteRepository
{

    protected string $table_name = 'messages';

    /**
     * Creates the keys table if it does not exist.
     */
    protected function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            sent_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }

    /**
     * Stores a new key record in the database.
     */
    public function store(int $sent_by, string $subject, string $body): bool
    {
        $sql = "INSERT INTO $this->table_name (`subject`, `body`, `sent_by`)
                VALUES (:subject, :body, :sent_by)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':subject' => $subject,
            ':body' => $body,
            ':sent_by' => $sent_by,
        ]);
    }

    /**
     * Stores a new key record in the database.
     */
    public function update(int|string $id, int $sent_by, string $subject, string $body): bool
    {
        $sql = "UPDATE $this->table_name 
                SET `subject` = :subject, 
                    `body` = :body,
                    `sent_by` = :sent_by
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':subject' => $subject,
            ':body'    => $body,
            ':sent_by' => $sent_by,
            ':id'      => $id,
        ]);
    }
}
