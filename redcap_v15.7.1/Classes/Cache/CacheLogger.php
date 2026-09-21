<?php
namespace Vanderbilt\REDCap\Classes\Cache;

use DateTime;
use Vanderbilt\REDCap\Classes\Cache\CacheManager;
use Vanderbilt\REDCap\Classes\Cache\DTOs\CacheRequestDTO;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\Utility\Mediator\ObserverInterface;
use Vanderbilt\REDCap\Classes\Utility\FileCache\NameVisitorInterface;

/**
 * This class logs cache hits in the user's session,
 * storing them in a manner that is specific to the current user.
 */
class CacheLogger implements ObserverInterface {
    
    const TS_KEY = 'ts';
    const CACHE_EXTENSION = 'rr';

    protected $project_id;

    protected $dataDirectory;

    /**
     *
     * @param REDCapCache $cache
     */
    public function __construct($project_id, $dataDirectory)
    {
        $this->project_id = $project_id;
        $this->dataDirectory = $dataDirectory;
    }

    public function fileCache($nameVisitor) {
        return new FileCache(self::class, $this->dataDirectory, $nameVisitor);
    }

    public static function pageTimestampNameVisitor($project_id, $page) {
        return new class($project_id, $page) implements NameVisitorInterface {
            const PREFIX = 'LATEST_PAGE_CACHE_TS';
            private $project_id;
            private $page;

            public function __construct($project_id, $page) {
                $this->project_id = $project_id;
                $this->page = $page;
            }

            function visit($key, $hashedFilename, $extension) {
                $filename = sprintf('PID%s-%s-%s',
                    $this->project_id,
                    self::PREFIX,
                    Utils::sanitizeFileName($this->page)
                );
                $extension = CacheLogger::CACHE_EXTENSION;
                return [$filename, $extension];
            }
        };
    }

    public static function projectTimestampNameVisitor($project_id) {
        return new class($project_id) implements NameVisitorInterface {
            const PREFIX = 'LATEST_PROJECT_CACHE_TS';
            private $project_id;

            public function __construct($project_id) {
                $this->project_id = $project_id;
            }

            function visit($key, $hashedFilename, $extension) {
                $filename = sprintf('PID%s-%s',
                    $this->project_id,
                    self::PREFIX
                );
                $extension = CacheLogger::CACHE_EXTENSION;
                return [$filename, $extension];
            }
        };
    }

    public function getLastCacheTimeForProject($project_id) {
        $fileCache = $this->fileCache(static::projectTimestampNameVisitor($project_id));
        $cache = $fileCache->get(static::TS_KEY);
        return $cache;
    }

    public function getLastCacheTimeForPage($project_id, $page) {
        $fileCache = $this->fileCache(static::pageTimestampNameVisitor($project_id, $page));
        $cache = $fileCache->get(static::TS_KEY);
        return $cache;
    }

    /**
     *
     * @param CacheManager $emitter
     * @param string $event
     * @param mixed $data
     * @return void
     */
    public function update($emitter, $event, $data=null) {
        $page = Utils::currentPage();
        if(!$page) return; // only log if page is available
        switch ($event) {
            case CacheManager::NOTIFY_GET_OR_SET:
                $this->onCacheGetOrSet($emitter, $data, $page);
                break;
            case CacheManager::NOTIFY_DONE:
                $this->onCacheDone($emitter, $data, $page);
                break;
            default:
                break;
        }
    }
    
    /**
     *
     * @param CacheManager $emitter
     * @param CacheRequestDTO $data
     * @param string $page
     * @return void
     */
    function onCacheGetOrSet($emitter, $cacheRequest, $page) {
        if($cacheRequest->cacheMiss !== true) return;

        list($currentLatestPageCacheTime, $currentLatestProjectCacheTime) = $this->fetchLatestCacheTimestamps($page);
        $latestPageCacheTime = $currentLatestPageCacheTime;
        $latestProjectCacheTime = $currentLatestProjectCacheTime;

        $this->processCacheRequest($cacheRequest, $latestPageCacheTime, $latestProjectCacheTime);

        $this->updateCacheTimestamp($this->fileCache(static::pageTimestampNameVisitor($this->project_id, $page)), $currentLatestPageCacheTime, $latestPageCacheTime);
        $this->updateCacheTimestamp($this->fileCache(static::projectTimestampNameVisitor($this->project_id)), $currentLatestProjectCacheTime, $latestProjectCacheTime);
    }

    /**
     * update page and project latest update timestamp
     * 
     * @param CacheManager $emitter
     * @param CacheRequestDTO[] $cacheRequests
     * @param CacheRequestDTOstring $page
     * @return void
     */
    function onCacheDone($emitter, $cacheRequests, $page) {
        list($currentLatestPageCacheTime, $currentLatestProjectCacheTime) = $this->fetchLatestCacheTimestamps($page);
        $latestPageCacheTime = $currentLatestPageCacheTime;
        $latestProjectCacheTime = $currentLatestProjectCacheTime;

        foreach ($cacheRequests as $cacheRequest) {
            $this->processCacheRequest($cacheRequest, $latestPageCacheTime, $latestProjectCacheTime);
        }

        $this->updateCacheTimestamp($this->fileCache(static::pageTimestampNameVisitor($this->project_id, $page)), $currentLatestPageCacheTime, $latestPageCacheTime);
        $this->updateCacheTimestamp($this->fileCache(static::projectTimestampNameVisitor($this->project_id)), $currentLatestProjectCacheTime, $latestProjectCacheTime);
    }

    /**
     * Fetch the current latest cache timestamps for a page and the project.
     * 
     * @param string $page The page identifier.
     * @return array An array containing DateTime objects or null for the page and project timestamps.
     */
    private function fetchLatestCacheTimestamps($page) {
        $fileCachePage = $this->fileCache(static::pageTimestampNameVisitor($this->project_id, $page));
        $fileCacheProject = $this->fileCache(static::projectTimestampNameVisitor($this->project_id));

        $latestPageCacheTimeData = $fileCachePage->get(static::TS_KEY);
        $latestProjectCacheTimeData = $fileCacheProject->get(static::TS_KEY);

        return [
            Utils::createDateTime($latestPageCacheTimeData),
            Utils::createDateTime($latestProjectCacheTimeData)
        ];
    }

    /**
     * Update the cache timestamp if it has changed.
     * 
     * @param $fileCache The file cache instance to update.
     * @param DateTime|null $latestTimestamp The current latest timestamp.
     * @param DateTime|null $newTimestamp The new timestamp to update if different from current.
     * @return void
     */
    private function updateCacheTimestamp($fileCache, $latestTimestamp, $newTimestamp) {
        if ($latestTimestamp != $newTimestamp && $newTimestamp instanceof DateTime) {
            $fileCache->set(static::TS_KEY, $newTimestamp->format(REDCapCache::TIMESTAMP_FORMAT), REDCapCache::DEFAULT_TTL);
        }
    }


    /**
     * Process a single cache request to update the latest cache times.
     * 
     * @param CacheRequestDTO $cacheRequest The cache request to process.
     * @param DateTime|null &$latestPageCacheTime Reference to the latest page cache time.
     * @param DateTime|null &$latestProjectCacheTime Reference to the latest project cache time.
     * @return void
     */
    private function processCacheRequest($cacheRequest, &$latestPageCacheTime, &$latestProjectCacheTime) {
        if ($cacheRequest->cacheMiss === false) {
            return;
        }

        $cacheTime = Utils::createDateTime($cacheRequest->ts);

        if (!$latestPageCacheTime || $cacheTime > $latestPageCacheTime) {
            $latestPageCacheTime = $cacheTime;
        }
        if (!$latestProjectCacheTime || $cacheTime > $latestProjectCacheTime) {
            $latestProjectCacheTime = $cacheTime;
        }
    }

}