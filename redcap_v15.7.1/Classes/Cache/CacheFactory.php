<?php
namespace Vanderbilt\REDCap\Classes\Cache;

use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\FileStorage;
use Vanderbilt\REDCap\Classes\Utility\Mediator\EventDispatcher;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\DummyStorage;
use Vanderbilt\REDCap\Classes\Utility\FileCache\CacheItemsManager;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\DatabaseStorage;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\FileStorageNameVisitor;
use Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies\InvalidationStrategyFactory;

class CacheFactory
{
    /**
     * @var CacheManager[]
     */
    static $instances = [];

    static $loggerInstances = [];

    /**
     *
     * @return CacheManager
     */
    public static function manager($local_project_id=null, $storageType=null)
    {
        $project_id = isinteger($local_project_id) ? $local_project_id : $GLOBALS['project_id'];

        $redecapConfig = REDCapConfigDTO::fromDB();
        $storageType = $storageType ?? $redecapConfig->cache_storage_system;
        $factory = new InvalidationStrategyFactory();
        $instance = self::$instances[$project_id] ?? null;

        $fileNameVisitor = new FileStorageNameVisitor($project_id);
        $cacheDir = static::getCacheDir();
        $fileCache = new FileCache(CacheManager::class.$project_id, $cacheDir, $fileNameVisitor);
        $cacheItemsManager = new CacheItemsManager($fileCache); // use to provide advanced cache items object

        if(is_null($instance)) {
            if(!$project_id) {
                // set dummy storage if no project_id is provided
                $storageType = 'dummy';
            }
            switch ($storageType) {
                case 'file':
                    $storage = new FileStorage($project_id, $fileCache);
                    break;
                case 'db':
                    $storage = new DatabaseStorage($project_id);
                    break;    
                case 'dummy':
                    $storage = new DummyStorage($project_id);
                    break;    
                default:
                    $storage = new DummyStorage($project_id);
                    break;
            }
            
            $cache = new REDCapCache($project_id, $storage, $factory);
            // add a cache lock manager
            // set max stop to .85 of the max execution time of the system
            $maxLockTime = CacheLockManager::calcMaxSystemLockTime(.85);
            $cacheLockManager = new CacheLockManager($cacheItemsManager, $maxLockTime);
            // make an event dispatcher
            $eventDispatcher = new EventDispatcher();
            
            // attach error manager
            $cacheErrorManager =  new CacheErrorManager($project_id);
            $eventDispatcher->attach($cacheErrorManager, CacheManager::NOTIFY_ERROR);
            
            if(!($storage instanceof DummyStorage)) {
                // do not attach logger and activity monitor for dummy storage
                $activityMonitor = new CacheActivityMonitor($cacheItemsManager);
                $cacheLogger = static::logger($project_id);
                
                // add a storage monitor to disable cache if not enough space in the system
                // (don't enable this [yet] since this might not be accurate if the cache dir is an external mapped drive)
                // $storageMonitor = new StorageMonitor($cacheDir);
                // $eventDispatcher->attach($storageMonitor, CacheManager::NOTIFY_INIT);
                
                self::setupEventDispatcher(
                    eventDispatcher: $eventDispatcher,
                    cacheLogger: $cacheLogger,
                    activityMonitor: $activityMonitor
                );
            }

            $instance = new CacheManager($cache, $eventDispatcher, $cacheLockManager);
            self::$instances[$project_id] = $instance;
        }
        return $instance;
    }

    /**
     *
     * @param intger $local_project_id
     * @return CacheLogger
     */
    public static function logger($local_project_id=null) {
        $project_id = isinteger($local_project_id) ? $local_project_id : $GLOBALS['project_id'];
        $cacheDir = static::getCacheDir();
        $instance = static::$loggerInstances[$project_id] ?? null;
        if(!$instance) {
            $cacheLogger = new CacheLogger($project_id, $cacheDir);
            static::$loggerInstances[$project_id] = $instance = $cacheLogger;
        }
        return $instance;
    }

    public static function getCacheDir() {
        $redecapConfig = REDCapConfigDTO::fromDB();
        $cacheDir = $redecapConfig->cache_files_filesystem_path;
        $cacheDir = empty($cacheDir) ? APP_PATH_TEMP : $cacheDir;
        return $cacheDir;
    }

    /**
     * Configures the event dispatcher with cache logging and monitoring.
     * 
     * Attaches CacheLogger to cache miss and completion events, and CacheActivityMonitor to initialization
     * and completion events within the cache system.
     *
     * @param EventDispatcher $eventDispatcher The event dispatcher for cache events.
     * @param CacheLogger $cacheLogger Logger for cache events.
     * @param CacheActivityMonitor $activityMonitor Monitor for cache activities.
     *
     * @return void
     */

    private static function setupEventDispatcher(EventDispatcher $eventDispatcher, CacheLogger $cacheLogger, CacheActivityMonitor $activityMonitor)
    {
        $eventDispatcher->attach($cacheLogger, CacheManager::NOTIFY_GET_OR_SET);
        // $eventDispatcher->attach($cacheLogger, CacheManager::NOTIFY_MISS);
        // $eventDispatcher->attach($cacheLogger, CacheManager::NOTIFY_DONE);

        $eventDispatcher->attach($activityMonitor, CacheManager::NOTIFY_INIT);
        $eventDispatcher->attach($activityMonitor, CacheManager::NOTIFY_DONE);
    }
}