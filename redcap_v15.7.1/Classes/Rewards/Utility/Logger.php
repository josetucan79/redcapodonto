<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Utility;

use Exception;
use Doctrine\DBAL\Connection;
use Vanderbilt\REDCap\Classes\Rewards\Entities\BaseEntity;

class Logger {
    
    /**
     *
     * @var Connection
     */
    private $db;

    private $tableName = 'redcap_rewards_logs';

    public function __construct() {
    }

    private function normalizeArgument($argument) {
        $normalized = $argument;
        if($normalized instanceof BaseEntity) $normalized = $normalized->toArray();
        if(is_array($normalized)) {
            foreach ($normalized as $key => $value) {
                $normalized[$key] = $this->normalizeArgument($value);
            }
            $normalized = json_encode($normalized);
        }
        return $normalized;
    }

    function logAction($table_name, $action, $payload=null, $username=null, $project_id=null, $record_id=null) {
        try {
            $builder = $this->db->createQueryBuilder();
            $builder->insert($this->tableName);
            $queryString = "INSERT INTO $this->tableName
                (`table_name`, `action`, `payload`, `username`, `project_id`, `record_id`)
                VALUES (?, ?, ?, ?, ?, ?)";
            $normalizedPaylod = $this->normalizeArgument($payload);
            $params = [
                $table_name,
                $action,
                $normalizedPaylod,
                $username,
                $project_id,
                $record_id,
            ];
            
            return $result = db_query($queryString, $params);
        } catch (Exception $e) {
            // Handle any errors that occurred during the logging process
            echo "Error: " . $e->getMessage();
        }
    }

}