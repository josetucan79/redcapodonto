<?php
// Driver/RedcapStatement.php
namespace Vanderbilt\REDCap\Classes\ORM\Driver;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\ParameterType;

class RedcapStatement implements Statement
{
    private $sql;
    private $connection;
    private $params = [];
    
    public function __construct($sql, $connection)
    {
        $this->sql = $sql;
        $this->connection = $connection;
    }
    
    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        $this->params[$param] = $value;
        return true;
    }
    
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        $this->params[$param] = &$variable;
        return true;
    }
    
    public function execute($params = null): Result
    {
        if ($params !== null) {
            $this->params = $params;
        }
        
        $result = db_query($this->sql, $this->params, $this->connection);
        
        if ($result === false) {
            throw new \Exception("Statement execution failed: " . db_error());
        }
        
        return new RedcapResult($result);
    }
}