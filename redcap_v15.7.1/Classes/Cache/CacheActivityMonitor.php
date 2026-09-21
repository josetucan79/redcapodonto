<?php
namespace Vanderbilt\REDCap\Classes\Cache;

use DateTime;
use DateInterval;
use Vanderbilt\REDCap\Classes\Cache\DTOs\CacheRequestDTO;
use Vanderbilt\REDCap\Classes\Cache\States\DisabledState;
use Vanderbilt\REDCap\Classes\Utility\FileCache\CacheItem;
use Vanderbilt\REDCap\Classes\Utility\FileCache\CacheItemsManager;
use Vanderbilt\REDCap\Classes\Utility\Mediator\ObserverInterface;

/**
 * Class CacheActivityMonitor
 * 
 * this class provides logic to track recent cache activity
 * in a page and can alter the state of the CacheManager
 */
class CacheActivityMonitor implements ObserverInterface
{

    const DEFAULT_ACTIVITY_TIME_THRESHOLD = 6000; // seconds
    const STATS_TTL = 3000; // seconds
    const MAX_RECENT_MISS = 10;
    const KEY_PREFIX = "PAGE-STATS";

    private $activity_threshold;
    private $cacheItemsManager;

    /**
     *
     * @param int $project_id
     * @param int $activity_threshold
     * @param CacheItemsManager $factory
     */
    public function __construct(CacheItemsManager $cacheItemsManager, $activity_threshold=null)
    {
        $this->cacheItemsManager = $cacheItemsManager;
        $this->activity_threshold = $activity_threshold ?? self::DEFAULT_ACTIVITY_TIME_THRESHOLD;
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

        switch ($event) {
            case CacheManager::NOTIFY_INIT:
                $this->onCacheInit($emitter, $data, $page);
                break;
            case CacheManager::NOTIFY_DONE:
                $this->onCacheDone($emitter, $data, $page);
                break;

            default:
                break;
        }
    }

    /**
     * get or create an item with page stats
     * 
     * @param string $page
     * @return CacheItem
     */
    private function getPageStats($page) {
        $key = sprintf(self::KEY_PREFIX."-%s", Utils::sanitizeFileName($page));
        $pageStats = $this->cacheItemsManager->get($key);
        if(!$pageStats) $pageStats = $this->cacheItemsManager->make($key, ['miss'=>[], 'hit'=>[]], self::STATS_TTL);
        return $pageStats;
    }

    /**
     * change the state of the cache manager if
     * too many misses in recent activity
     *
     * @param CacheManager $emitter
     * @param string $data
     * @param string $page
     * @return void
     */
    private function onCacheInit($emitter, $data, $page) {
        $pageStats = $this->getPageStats($page);
        $stats = $pageStats->getData();
        $missList = $stats['miss'] ?? [];
        $TotalRecentMiss = $this->countRecentTimestamps($missList, $this->activity_threshold);
        if($TotalRecentMiss === 0) {
            $this->cacheItemsManager->delete($pageStats);
            return;
        }
        if($TotalRecentMiss < self::MAX_RECENT_MISS) return;
        // too many miss! change the state of the CacheManager ($emitter) to DISABLED
        $emitter->setState(new DisabledState());
    }

    /**
     * delete the page stats if there no miss are logged
     *
     * @param CacheManager $emitter
     * @param CacheRequestDTO[] $data
     * @param string $page
     * @return void
     */
    private function onCacheDone($emitter, $data, $page) {
        $pageStats = $this->getPageStats($page);
        $stats = $pageStats->getData();

        $found = false;
        // check for any cache-miss
        foreach ($data as $cacheRequest) {
            if ($cacheRequest->cacheMiss === true) {
                $found = true;
                break; // No need to continue the loop if we've found a match
            }
        }
        if($found) {
            $stats['miss'][] = date(REDCapCache::TIMESTAMP_FORMAT);
            $pageStats->refresh()->setData($stats);
            $this->cacheItemsManager->save($pageStats); // refresh also
        }

        $totalMiss = count($stats['miss'] ?? []);
        if($totalMiss===0) $this->cacheItemsManager->delete($pageStats);
    }

    /**
     * Counts how many date-times from the provided array are within the specified threshold.
     *
     * @param array $dateTimes An array of date-times in 'Y-m-d H:i:s' format.
     * @param string $threshold A relative date/time string compatible with DateInterval::createFromDateString.
     *                          For example, '10 minutes', '1 hour', etc., are used to calculate the interval 
     *                          from the current time.
     * @return int The count of timestamps falling within the recent interval.
     */
    public function countRecentTimestamps(array $dateTimes, $seconds) {
        // Get the current time
        $currentTime = time();
        
        // Calculate the oldest acceptable timestamp that should be considered "recent"
        $threshold = $currentTime - $seconds;

        $count = 0;

        // Check each timestamp to see if it's greater than the threshold
        foreach ($dateTimes as $dateTime) {
            $timestamp = strtotime($dateTime);

            if ($timestamp > $threshold) {
                $count++;
            }
        }

        return $count;
    }
}