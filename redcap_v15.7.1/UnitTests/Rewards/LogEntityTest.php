<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Tests;

use DateTime;
use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\ORM\EntityManagerFactory;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\LogEntity;

class LogEntityTest extends TestCase
{
    private $entityManager;
    private static $project_id = 1;
    
    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::create();
    }
    
    protected function tearDown(): void
    {
        // Clean up the entity manager to avoid memory leaks
        if ($this->entityManager) {
            $this->entityManager->clear();
            $this->entityManager->close();
        }
    }

    /**
     * Test database connection
     */
    public function testDatabaseConnection()
    {
        // Get the connection
        $connection = EntityManagerFactory::getConnection();

        // Explicitly try to connect
        try {
            $connection->connect();
            $this->assertTrue($connection->isConnected(), 'Database connection is not active after explicit connect');
        } catch (\Exception $e) {
            $this->fail('Database connection failed with message: ' . $e->getMessage());
        }
        
        // Test if connection is active
        $this->assertTrue($connection->isConnected(), 'Database connection is not active');
        
        // Try a simple query to verify further
        try {
            $result = $connection->executeQuery('SELECT 1')->fetchOne();
            $this->assertEquals(1, $result, 'Simple database query failed');
        } catch (\Exception $e) {
            $this->fail('Database query failed with message: ' . $e->getMessage());
        }
    }

    /**
     * Test if required tables exist
     */
    public function testTablesExist()
    {
        $connection = EntityManagerFactory::getConnection();
        
        // Check if the table exists
        $schemaManager = $connection->createSchemaManager();
        $tableExists = $schemaManager->tablesExist(['redcap_rewards_logs']);
        
        $this->assertTrue($tableExists, 'Required table redcap_rewards_logs does not exist');
    }
    
    /**
     * Test creating a new log entry
     */
    public function testCreateLogEntry()
    {
        // Create a new log entity
        $log = new LogEntity();
        $log->setTableName('redcap_user_information');
        $log->setAction('CREATE');
        $log->setPayload(json_encode(['username' => 'testuser', 'email' => 'test@example.com']));
        $log->setUsername('site_admin');
        $log->setProjectId(static::$project_id);
        $log->setCreatedAt(new DateTime());
        
        // Persist and flush
        $this->entityManager->persist($log);
        $this->entityManager->flush();
        
        // Assert that log_id was generated
        $this->assertNotNull($log->getLogId());
        $this->assertGreaterThan(0, $log->getLogId());
        
        return $log->getLogId();
    }
    
    /**
     * Test reading a log entry
     * 
     * @depends testCreateLogEntry
     */
    public function testReadLogEntry($logId)
    {
        // Find the log entry by ID
        $log = $this->entityManager->find(LogEntity::class, $logId);
        
        // Assert that the log was found
        $this->assertNotNull($log);
        $this->assertInstanceOf(LogEntity::class, $log);
        
        // Assert field values are correct
        $this->assertEquals('redcap_user_information', $log->getTableName());
        $this->assertEquals('CREATE', $log->getAction());
        $this->assertEquals('site_admin', $log->getUsername());
        $this->assertEquals(static::$project_id, $log->getProjectId());
        $this->assertInstanceOf(DateTime::class, $log->getCreatedAt());
        
        return $logId;
    }
    
    /**
     * Test finding logs by criteria
     * 
     * @depends testCreateLogEntry
     */
    public function testFindLogsByCriteria($logId)
    {
        $repository = $this->entityManager->getRepository(LogEntity::class);
        
        // Find logs for a specific project
        $logs = $repository->findBy(['project_id' => static::$project_id]);
        
        // Assert that logs were found
        $this->assertNotEmpty($logs);
        $this->assertContainsOnlyInstancesOf(LogEntity::class, $logs);
        
        // Find logs by table name and action
        $logs = $repository->findBy([
            'table_name' => 'redcap_user_information',
            'action' => 'CREATE'
        ]);
        
        // Assert that logs were found
        $this->assertNotEmpty($logs);
        
        return $logId;
    }
    
    /**
     * Test updating a log entry
     * 
     * @depends testReadLogEntry
     */
    public function testUpdateLogEntry($logId)
    {
        // Find the log to update
        $log = $this->entityManager->find(LogEntity::class, $logId);
        $this->assertNotNull($log);
        
        // Update the log
        $log->setAction('UPDATE');
        $log->setPayload(json_encode(['username' => 'testuser', 'email' => 'updated@example.com']));
        
        // Flush changes
        $this->entityManager->flush();
        
        // Clear entity manager to ensure we get fresh data
        $this->entityManager->clear();
        
        // Re-fetch the log and verify changes
        $updatedLog = $this->entityManager->find(LogEntity::class, $logId);
        $this->assertEquals('UPDATE', $updatedLog->getAction());
        $this->assertStringContainsString('updated@example.com', $updatedLog->getPayload());
        
        return $logId;
    }
    
    /**
     * Test complex queries using DQL
     * 
     * @depends testCreateLogEntry
     */
    public function testQueryBuilderQueries($logId)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        
        // Query logs created after a specific date
        $query = $queryBuilder
            ->select('l')
            ->from(LogEntity::class, 'l')
            ->where('l.created_at > :date')
            ->andWhere('l.project_id = :projectId')
            ->setParameter('date', new DateTime('-1 day'))
            ->setParameter('projectId', static::$project_id)
            ->getQuery();
            
        $logs = $query->getResult();
        
        // Assert logs were found
        $this->assertNotEmpty($logs);
        $this->assertContainsOnlyInstancesOf(LogEntity::class, $logs);
        
        return $logId;
    }
    
    /**
     * Test native SQL queries
     * 
     * @depends testCreateLogEntry
     */
    public function testNativeSQLQueries($logId)
    {
        $sql = "SELECT * FROM redcap_rewards_logs WHERE log_id = ?";
        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $result = $stmt->execute([$logId]);
        
        $logs = $result->fetchAllAssociative();
        
        // Assert log was found
        $this->assertNotEmpty($logs);
        $this->assertEquals($logId, $logs[0]['log_id']);
        
        return $logId;
    }

    /**
     * Test native SQL queries
     * 
     * @depends testCreateLogEntry
     */
    public function testNativeSQLQueriesWithConnection($logId)
    {
        $sql = "SELECT * FROM redcap_rewards_logs WHERE log_id = ?";
        $stmt = EntityManagerFactory::getConnection()->prepare($sql);
        $result = $stmt->executeQuery([$logId]);
        
        $logs = $result->fetchAllAssociative();
        
        // Assert log was found
        $this->assertNotEmpty($logs);
        $this->assertEquals($logId, $logs[0]['log_id']);
        
        return $logId;
    }
    
    /**
     * Test deleting a log entry
     * 
     * @depends testUpdateLogEntry
     */
    public function testDeleteLogEntry($logId)
    {
        // Find the log to delete
        $log = $this->entityManager->find(LogEntity::class, $logId);
        $this->assertNotNull($log);
        
        // Remove the log
        $this->entityManager->remove($log);
        $this->entityManager->flush();
        
        // Verify it was deleted
        $deletedLog = $this->entityManager->find(LogEntity::class, $logId);
        $this->assertNull($deletedLog);
    }
    
    /**
     * Test querying logs by username
     */
    public function testGetLogsByUsername()
    {
        // Create test log entries
        for ($i = 0; $i < 3; $i++) {
            $log = new LogEntity();
            $log->setTableName('test_table');
            $log->setAction('TEST_ACTION');
            $log->setUsername('testuser');
            $log->setCreatedAt(new DateTime());
            
            $this->entityManager->persist($log);
        }
        $this->entityManager->flush();
        
        // Query by username
        $repository = $this->entityManager->getRepository(LogEntity::class);
        $logs = $repository->findBy(['username' => 'testuser', 'action' => 'TEST_ACTION']);
        
        // Assert we found the correct number of logs
        $this->assertGreaterThanOrEqual(3, count($logs));
        
        // Clean up test data
        foreach ($logs as $log) {
            if ($log->getAction() === 'TEST_ACTION') {
                $this->entityManager->remove($log);
            }
        }
        $this->entityManager->flush();
    }
    
    /**
     * Test querying logs from a specific date range
     */
    public function testGetLogsByDateRange()
    {
        // Create a log entry for yesterday
        $log1 = new LogEntity();
        $log1->setTableName('date_test');
        $log1->setAction('DATE_TEST');
        $log1->setCreatedAt(new DateTime('-1 day'));
        $this->entityManager->persist($log1);
        
        // Create a log entry for today
        $log2 = new LogEntity();
        $log2->setTableName('date_test');
        $log2->setAction('DATE_TEST');
        $log2->setCreatedAt(new DateTime());
        $this->entityManager->persist($log2);
        
        $this->entityManager->flush();
        
        // Query logs in date range
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $query = $queryBuilder
            ->select('l')
            ->from(LogEntity::class, 'l')
            ->where('l.created_at BETWEEN :start AND :end')
            ->andWhere('l.action = :action')
            ->setParameter('start', new DateTime('-2 days'))
            ->setParameter('end', new DateTime('+1 day'))
            ->setParameter('action', 'DATE_TEST')
            ->getQuery();
            
        $logs = $query->getResult();
        
        // Assert we found both logs
        $this->assertCount(2, $logs);
        
        // Clean up
        foreach ($logs as $log) {
            $this->entityManager->remove($log);
        }
        $this->entityManager->flush();
    }
    
    /**
     * Test batch insert of logs
     */
    public function testBatchInsertLogs()
    {
        $batchSize = 5;
        $totalLogs = 10;
        
        // Create multiple logs
        for ($i = 0; $i < $totalLogs; $i++) {
            $log = new LogEntity();
            $log->setTableName('batch_test');
            $log->setAction('BATCH_INSERT');
            $log->setPayload("Log entry #{$i}");
            $log->setCreatedAt(new DateTime());
            
            $this->entityManager->persist($log);
            
            // Flush every $batchSize entities and clear the EntityManager
            if (($i % $batchSize) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }
        
        // Flush remaining entities
        $this->entityManager->flush();
        
        // Count logs with this action
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $query = $queryBuilder
            ->select('COUNT(l)')
            ->from(LogEntity::class, 'l')
            ->where('l.action = :action')
            ->setParameter('action', 'BATCH_INSERT')
            ->getQuery();
            
        $count = $query->getSingleScalarResult();
        
        // Assert we inserted the correct number of logs
        $this->assertGreaterThanOrEqual($totalLogs, $count);
        
        // Clean up - use a direct delete query for efficiency
        $this->entityManager->createQueryBuilder()
            ->delete(LogEntity::class, 'l')
            ->where('l.action = :action')
            ->setParameter('action', 'BATCH_INSERT')
            ->getQuery()
            ->execute();
    }
}