<?php
namespace Vanderbilt\REDCap\Classes\Cache;

use InvalidArgumentException;
use Vanderbilt\REDCap\Classes\Cache\REDCapCache;
use Vanderbilt\REDCap\Classes\Cache\States\CacheState;
use Vanderbilt\REDCap\Classes\Cache\DTOs\CacheRequestDTO;
use Vanderbilt\REDCap\Classes\Cache\States\ReadThroughState;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\StorageItem;
use Vanderbilt\REDCap\Classes\Utility\Mediator\EventDispatcher;

class CacheManager implements CacheState
{
    const NOTIFY_INIT = 'init';
    const NOTIFY_GET_OR_SET = 'getOrSet';
    const NOTIFY_HIT = 'hit';
    const NOTIFY_MISS = 'miss';
    const NOTIFY_DONE = 'done';
    const NOTIFY_ERROR = 'error';

    /**
     *
     * @var REDCapCache
     */
    private $cache;

    /**
     * Event Dispatcher
     *
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * contains the list of requests
     * @var CacheRequestDTO[]
     */
    private $cacheRequests = [];

    /**
     *
     * @var CacheLockManager
     */
    private $cacheLockManager;

    /**
     *
     * @var CacheState
     */
    private $state;
    
    /**
     * contains the list of hash keys requests that were missed
     * @var array
     */
    private $cacheMiss = [];

    public function __construct($cache, $eventDispatcher = null, $cacheLockManager=null)
    {
        $this->setState(new ReadThroughState($this)); // default state
        $this->cache = $cache;
        $this->cacheLockManager = $cacheLockManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventDispatcher()->notify($this, self::NOTIFY_INIT, null);
    }

    public function __destruct()
    {
        $this->eventDispatcher()->notify($this, self::NOTIFY_DONE, $this->getCacheRequests());
    }

    /**
     *
     * @param CacheState $state
     * @return void
     */
    public function setState($state) {
        $this->state = $state;
    }

    public function state() { return $this->state; }
    public function cacheMiss() { return $this->cacheMiss; }

    /**
     * provide access to the underliyng cache system
     *
     * @return REDCapCache
     */
    public function cache() { return $this->cache; }

    /**
     *
     * @return CacheRequestDTO[]
     */
    public function getCacheRequests() { return $this->cacheRequests; }

    /**
     *
     * @return EventDispatcher
     */
    public function eventDispatcher() { return $this->eventDispatcher; }

    /**
     *
     * @return CacheLockManager
     */
    public function cacheLockManager() { return $this->cacheLockManager; }


    /**
     *
     * @param CacheRequestDTO $cacheRequest
     * @return void
     */
    public function addCacheRequest($cacheRequest) {
        if($cacheRequest->cacheMiss) $this->cacheMiss[] = $cacheRequest;
        $this->cacheRequests[] = $cacheRequest;
    }

    /**
     * Retrieves data from cache or sets it if it doesn't exist, behavior depending on the current state.
     * 
     * This method delegates the action to the current state object, which 
     * implements the actual behavior depending on the state of the CacheManager 
     * (e.g., normal read-through caching or disabled caching).
     *
     * @param callable $callable  The function used to retrieve the data if not present in the cache.
     * @param array    $args      Optional arguments to pass to the callable function.
     * @param array    $options   Optional cache options.
     * @param string   $cache_key Optional reference parameter to store the cache key.
     * 
     * @return mixed The data retrieved from cache or directly from the callable, depending on the state.
     */
    public function getOrSet($callable, $args=[], $options=[], &$cache_key=null)
    {
        return $this->state->getOrSet(...func_get_args());
    }

    /**
     * Generate a unique key for caching based on the callable and arguments.
     * 
     * This method serializes the callable and arguments, optionally includes a salt, 
     * and returns an SHA1 hash of this combination, ensuring a unique cache key for
     * distinct callable/argument pairs.
     *
     * @param callable $callable The function used to retrieve the data.
     * @param array    $args     Arguments used in the callable function and cache key construction.
     * @param array    $options  Cache options.
     * 
     * @return string  The SHA1 hash serving as the unique key for the cache entry.
     */
    public function makeCacheKey($callable, $args, $options)
    {
        $options = $this->cache()->applyDefaultOptions($options);
        $salt = $options[REDCapCache::OPTION_SALT];
        $hash = serialize($callable).serialize($args);
        if($salt) $hash .= serialize($salt);
        return sha1($hash);
    }

    /**
     * Handles operations for when the cache hit occurs.
     *
     * @param string      $cache_key   The cache key where the data is stored.
     * @param StorageItem $storageItem The cache storage item.
     * 
     * @return mixed The data retrieved from the cache.
     */
    public function handleCacheHit($cache_key, $storageItem)
    {
        $this->eventDispatcher()->notify($this, self::NOTIFY_HIT, $storageItem);
        return $storageItem->data();
    }

    /**
     * Handles operations for when the cache miss occurs.
     *
     * @param string   $cache_key The cache key.
     * @param callable $callable  The function used to retrieve the data.
     * @param array    $args      Arguments to pass to the callable function.
     * @param array    $options   Cache options.
     * 
     * @return mixed The data retrieved using the callable or directly from cache after lock wait.
     */
    public function handleCacheMiss($cache_key, $callable, $args, $options)
    {
        $this->eventDispatcher()->notify($this, self::NOTIFY_MISS, $cache_key);
        $lockAcquired = $this->cacheLockManager->acquireLock($cache_key);
        
        if ($lockAcquired === true) {
            return $this->retrieveAndCacheData($cache_key, $callable, $args, $options);
        } else {
            return $this->handleLockWait($cache_key, $callable, $args);
        }
    }

    /**
     * Retrieves and caches the data using the provided callable.
     *
     * @param string   $cache_key The cache key.
     * @param callable $callable  The function used to retrieve the data.
     * @param array    $args      Arguments to pass to the callable function.
     * @param array    $options   Cache options.
     * 
     * @return mixed The data retrieved from the callable.
     * @throws InvalidArgumentException Throws an exception if the provided argument is not callable.
     */
    private function retrieveAndCacheData($cache_key, $callable, $args, $options)
    {
        if (!is_callable($callable)) {
            $this->cacheLockManager->releaseLock($cache_key);  // Ensure to release the lock
            throw new InvalidArgumentException("Provided argument is not callable.");
        }
        try {
            $data = $callable(...$args);
            $this->cache()->set($cache_key, $data, $options);
            
            return $data;
        } catch (\Throwable $th) {
            if (isset($data)) {
                
                // notify via event dispatcher
                $errorMessage = $th->getMessage();
                $this->eventDispatcher()->notify($this, self::NOTIFY_ERROR, [
                    'cache_key' => $cache_key,
                    'error' => get_class($th),
                    'message' => "Cache storage failed for key '$cache_key' – data was returned without being cached. Error: $errorMessage"
                ]);
                
                return $data;
            }
            
            // If the callable itself failed, re-throw the exception
            throw $th;
        } finally {
            $this->cacheLockManager->releaseLock($cache_key);  // Ensure to release the lock
        }
    }

    /**
     * Handles the condition when waiting for a lock release is necessary.
     *
     * @param string   $cache_key The cache key.
     * @param callable $callable  The function used to retrieve the data.
     * @param array    $args      Arguments to pass to the callable function.
     * 
     * @return mixed The data retrieved from cache or from the callable function after lock release.
     */
    private function handleLockWait($cache_key, $callable, $args)
    {
        $lockAcquired = $this->cacheLockManager->waitForLock($cache_key);
        if(!$lockAcquired) return $callable(...$args); // could not acquire lock; possible timeout reached

        $storageItem = $this->cache()->get($cache_key);

        if ($storageItem instanceof StorageItem) {
            return $storageItem->data();
        } else {
            // This should not happen, as it means the other process did not build the cache correctly.
            return $callable(...$args);
            // throw new RuntimeException("Expected cache data not found after waiting for lock release.");
        }
    }

    /**
     * Records details of the cache request, whether it was a hit or a miss.
     * 
     * This method encapsulates details of a cache request, including the key, 
     * the data retrieved (if a cache hit occurred), and metadata about the 
     * cache request, within a CacheRequestDTO object. This object can be used 
     * for logging, monitoring, or analyzing cache behavior.
     *
     * @param string            $cache_key    The cache key.
     * @param StorageItem|false $storageItem  The cache storage item.
     * @param bool              $cacheMiss    Flag indicating whether the cache miss occurred.
     */
    public function recordCacheRequest($cache_key, $storageItem, $cacheMiss)
    {
        $request = new CacheRequestDTO();
        $request->key = $cache_key;
        $request->cacheMiss = $cacheMiss;
        
        if ($storageItem instanceof StorageItem) {
            $request->value = $storageItem->data();
            $request->ts = $storageItem->timestamp()->format(REDCapCache::TIMESTAMP_FORMAT);
        } else {
            // If no storage item is available, we record limited information.
            $request->value = null;
            $request->ts = date(REDCapCache::TIMESTAMP_FORMAT);  // Or current time, or another indicator of "no cache data".
        }
    
        $this->addCacheRequest($request);
        $this->eventDispatcher()->notify($this, self::NOTIFY_GET_OR_SET, $request);
    }

    /**
     * Create a structured object representing a cache access attempt.
     * 
     * This method encapsulates details of a cache request, including the key, 
     * the data retrieved (if a cache hit occurred), and metadata about the 
     * cache request, within a CacheRequestDTO object. This object can be used 
     * for logging, monitoring, or analyzing cache behavior.
     *
     * @param string $cache_key The unique cache key string representing the cache entry.
     * @param StorageItem $storageItem The object returned from the cache, if any.
     * @param bool $cacheMiss Flag indicating whether the cache request was a miss.
     * @return CacheRequestDTO A data transfer object encapsulating cache request details.
     */
    public function makeCacheRequest($cache_key, $storageItem, $cacheMiss) {
        $request = new CacheRequestDTO();
        $request->key = $cache_key;
        $request->value = $storageItem->data();
        $request->cacheMiss = $cacheMiss;
        $request->ts = $storageItem->timestamp()->format(REDCapCache::TIMESTAMP_FORMAT);
        return $request;
    }

    public function hasRequests() {
        return count($this->cacheRequests) > 0;
    }

    public function hasCacheMiss(): bool {
        return $this->state->hasCacheMiss();
    }
}
