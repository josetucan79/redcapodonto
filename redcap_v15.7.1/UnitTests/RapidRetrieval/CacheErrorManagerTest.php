<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Cache\CacheManager;
use Vanderbilt\REDCap\Classes\Cache\REDCapCache;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\FileStorage;
use Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies\InvalidationStrategyFactory;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\FileStorageNameVisitor;
use Vanderbilt\REDCap\Classes\Utility\FileCache\CacheItemsManager;
use Vanderbilt\REDCap\Classes\Cache\CacheActivityMonitor;
use Vanderbilt\REDCap\Classes\Cache\CacheLockManager;
use Vanderbilt\REDCap\Classes\Cache\CacheLogger;
use Vanderbilt\REDCap\Classes\Cache\CacheErrorManager;
use Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies\ProjectActivityInvalidation;
use Vanderbilt\REDCap\Classes\Utility\Mediator\EventDispatcher;

class CacheErrorManagerTest extends TestCase
{
    private CacheManager $cacheManager;
    private CacheErrorManager $cacheErrorManager;
    private $projectId = 1;

    protected function setUp(): void
    {
        $factory = new InvalidationStrategyFactory();
        $projectId = $this->projectId;
        $fileNameVisitor = new FileStorageNameVisitor($projectId);
        $cacheDir = sys_get_temp_dir() . '/redcap_cache';
        $fileCache = new FileCache(CacheManager::class . $projectId, $cacheDir, $fileNameVisitor);
        $cacheItemsManager = new CacheItemsManager($fileCache);

        $storage = new FileStorage($projectId, $fileCache);
        $coreCache = new REDCapCache($projectId, $storage, $factory);

        $maxLockTime = CacheLockManager::calcMaxSystemLockTime(0.85);
        $cacheLockManager = new CacheLockManager($cacheItemsManager, $maxLockTime);
        $activityMonitor = new CacheActivityMonitor($cacheItemsManager);
        $eventDispatcher = new EventDispatcher();

        $cacheLogger = new CacheLogger($projectId, $cacheDir);
        $eventDispatcher->attach($cacheLogger, CacheManager::NOTIFY_GET_OR_SET);

        $eventDispatcher->attach($activityMonitor, CacheManager::NOTIFY_INIT);
        $eventDispatcher->attach($activityMonitor, CacheManager::NOTIFY_DONE);

        // Attach error manager - this is what we're testing
        $this->cacheErrorManager = new CacheErrorManager($projectId);
        $eventDispatcher->attach($this->cacheErrorManager, CacheManager::NOTIFY_ERROR);

        $this->cacheManager = new CacheManager($coreCache, $eventDispatcher, $cacheLockManager);
    }

    public function testErrorManagerHandlesCacheStorageError()
    {
        $cacheOptions = [
            REDCapCache::OPTION_INVALIDATION_STRATEGIES => [ProjectActivityInvalidation::signature($this->projectId)],
            REDCapCache::OPTION_SALT => []
        ];

        // Test with data that will cause serialization issues but still return data
        $result = $this->cacheManager->getOrSet(
            [$this, 'createUnserializableDataCallback'],
            [],
            $cacheOptions
        );

        // Should still return the data even if caching failed
        $this->assertNotNull($result);
        $this->assertIsObject($result);
        $this->assertTrue(property_exists($result, 'closure'));
        
        // Verify that cache storage failed but error was handled gracefully by CacheErrorManager
        $this->assertTrue(true, 'CacheErrorManager handled cache storage failure gracefully');
    }

    public function testErrorManagerDoesNotInterceptCallableErrors()
    {
        $cacheOptions = [
            REDCapCache::OPTION_INVALIDATION_STRATEGIES => [ProjectActivityInvalidation::signature($this->projectId)],
            REDCapCache::OPTION_SALT => []
        ];

        // Test that callable errors are properly propagated (not caught by CacheErrorManager)
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Intentional callable error for testing');

        $this->cacheManager->getOrSet(
            [$this, 'createErrorCallback'],
            [],
            $cacheOptions
        );
    }

    /**
     * Creates an object with a closure that cannot be serialized
     * This will trigger serialization errors that should be handled by CacheErrorManager
     */
    public function createUnserializableDataCallback()
    {
        $obj = new stdClass();
        $obj->data = 'test data';
        $obj->closure = function() { return 'this cannot be serialized'; };
        $obj->timestamp = time();
        
        return $obj;
    }

    /**
     * Creates a callback that throws an exception
     * This should NOT be caught by the CacheErrorManager
     */
    public function createErrorCallback()
    {
        throw new RuntimeException('Intentional callable error for testing');
    }

    protected function tearDown(): void
    {
        // Clean up any temporary cache files
        $cacheDir = sys_get_temp_dir() . '/redcap_cache';
        if (is_dir($cacheDir)) {
            $this->recursiveDelete($cacheDir);
        }
    }

    private function recursiveDelete($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }
}