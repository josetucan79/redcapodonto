<?php
// Driver/RedcapConnection.php
namespace Vanderbilt\REDCap\Classes\ORM\Driver;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\ParameterType;

class RedcapConnection implements Connection
{
    private $connection;
    
    public function __construct($connection)
    {
        $this->connection = $connection;
    }
    
    public function prepare(string $sql): Statement
    {
        // We don't actually make a prepared statement,
        // but create an object that will execute the query when needed
        return new RedcapStatement($sql, $this->connection);
    }
    
    public function query(string $sql): Result
    {
        $result = db_query($sql, [], $this->connection);
        if ($result === false) {
            throw new \Exception("Query failed: " . db_error());
        }
        
        return new RedcapResult($result);
    }
    
    public function quote($value, $type = ParameterType::STRING)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        
        return "'" . db_escape($value) . "'";
    }
    
    public function exec(string $sql): int
    {
        $result = db_query($sql, [], $this->connection);
        if ($result === false) {
            throw new \Exception("Query execution failed: " . db_error());
        }
        
        return db_affected_rows();
    }
    
    public function lastInsertId($name = null)
    {
        return db_insert_id();
    }
    
    public function beginTransaction(): bool
    {
        return (bool)db_query("START TRANSACTION", [], $this->connection);
    }
    
    public function commit(): bool
    {
        return (bool)db_query("COMMIT", [], $this->connection);
    }
    
    public function rollBack(): bool
    {
        return (bool)db_query("ROLLBACK", [], $this->connection);
    }
}