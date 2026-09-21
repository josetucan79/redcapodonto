<?php
// Driver/RedcapResult.php
namespace Vanderbilt\REDCap\Classes\ORM\Driver;

use Doctrine\DBAL\Driver\Result;

class RedcapResult implements Result
{
    private $result;
    
    public function __construct($result)
    {
        $this->result = $result;
    }
    
    public function fetchNumeric()
    {
        $row = db_fetch_array($this->result, MYSQLI_NUM);
        return $row ?: false;
    }
    
    public function fetchAssociative()
    {
        $row = db_fetch_assoc($this->result);
        return $row ?: false;
    }
    
    public function fetchOne()
    {
        $row = $this->fetchNumeric();
        return $row ? $row[0] : false;
    }
    
    public function fetchAllNumeric(): array
    {
        $rows = [];
        while ($row = $this->fetchNumeric()) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    public function fetchAllAssociative(): array
    {
        return db_fetch_assoc_all($this->result);
    }
    
    public function fetchFirstColumn(): array
    {
        $column = [];
        while ($row = $this->fetchNumeric()) {
            $column[] = $row[0];
        }
        return $column;
    }
    
    public function rowCount(): int
    {
        return db_num_rows($this->result) ?: db_affected_rows();
    }
    
    public function columnCount(): int
    {
        return db_num_fields($this->result);
    }
    
    public function free(): void
    {
        if ($this->result) {
            db_free_result($this->result);
        }
    }
}