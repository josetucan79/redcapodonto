<?php
namespace Vanderbilt\REDCap\Classes\Cache\States;

use Vanderbilt\REDCap\Classes\Cache\CacheManager;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\StorageItem;

class ReadThroughState implements CacheState {

    /**
     *
     * @var CacheManager
     */
    private $cacheManager;

    public function __construct($cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }


    public function getOrSet($callable, $args=[], $options=[], &$cache_key=null)
    {
        $cacheManager = $this->cacheManager;
        $cacheMiss = false;
        $cache_key = $cacheManager->makeCacheKey($callable, $args, $options);
        $storageItem = $cacheManager->cache()->get($cache_key);

        if ($storageItem instanceof StorageItem) {
            $data = $cacheManager->handleCacheHit($cache_key, $storageItem);
        } else {
            $cacheMiss = true;
            $data = $cacheManager->handleCacheMiss($cache_key, $callable, $args, $options);
        }

        $cacheManager->recordCacheRequest($cache_key, $storageItem, $cacheMiss);

        return $data;
    }

    public function hasCacheMiss(): bool {
        $totalRequests = count($this->cacheManager->getCacheRequests());
        if($totalRequests===0) return false;
        $totalMisses = count($this->cacheManager->cacheMiss());
        return $totalMisses > 0;
    }
}
