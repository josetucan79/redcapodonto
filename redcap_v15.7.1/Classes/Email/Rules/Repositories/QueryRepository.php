<?php
namespace Vanderbilt\REDCap\Classes\Email\Rules\Repositories;



// Repository to manage SQLite interactions.
class QueryRepository extends BaseRepository
{

    protected string $table_name = 'redcap_email_users_queries';

    /**
     * Stores a new key record in the database.
     */
    public function store(string|array $query, string $name='', string $description=''): bool
    {
        if(is_array($query)) $query = json_encode($query);
        $sql = "INSERT INTO $this->table_name (name, description, query, created_at) VALUES (?, ?, ?, ?)";
        return db_query($sql, [$name, $description, $query, NOW]);
    }

    /**
     * Stores a new key record in the database.
     */
    public function update(int|string $id, string|array $query, string $name='', string $description=''): bool
    {
        if(is_array($query)) $query = json_encode($query);
        $sql = "UPDATE $this->table_name SET name = ?, description = ?, query = ? WHERE id = ?";
        return db_query($sql, [$name, $description, $query, $id]);
    }
}
