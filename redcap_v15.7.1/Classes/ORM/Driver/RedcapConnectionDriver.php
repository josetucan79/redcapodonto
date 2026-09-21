<?php
// Driver/RedcapConnectionDriver.php
namespace Vanderbilt\REDCap\Classes\ORM\Driver;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\API\MySQL\ExceptionConverter;

class RedcapConnectionDriver extends AbstractMySQLDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params)
    {
        global $rc_connection;
        
        if (!$rc_connection) {
            db_connect();
            if (!$rc_connection) {
                throw new \Exception("Failed to connect to REDCap database");
            }
        }
        
        return new RedcapConnection($rc_connection);
    }

    /**
     * {@inheritdoc}
     */
    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}