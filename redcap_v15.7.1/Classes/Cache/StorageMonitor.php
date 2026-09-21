<?php
namespace Vanderbilt\REDCap\Classes\Cache;

use Vanderbilt\REDCap\Classes\Cache\States\DisabledState;
use Vanderbilt\REDCap\Classes\Utility\Mediator\ObserverInterface;
use Vanderbilt\REDCap\Classes\Utility\StorageInfo;

/**
 * Class StorageMonitor
 * 
 * this class provides logic to track the status of the storage
 * where the cache files are saved
 */
class StorageMonitor implements ObserverInterface
{
    const USED_SPACE_THRESHOLD = 0.90;

    private $cacheDir;

    public function __construct($cacheDir) {
        $this->cacheDir = $cacheDir;
    }

    /**
     *
     * @param CacheManager $emitter
     * @param string $event
     * @param mixed $data
     * @return void
     */
    public function update($emitter, $event, $data=null) {
        switch ($event) {
            case CacheManager::NOTIFY_INIT:
                $this->onCacheInit($emitter, $data);
                break;
        }
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
    private function onCacheInit($emitter, $data) {
        $usedSpacePercentage = StorageInfo::getUsagePercent($this->cacheDir);
        if($usedSpacePercentage < self::USED_SPACE_THRESHOLD) return;
        // too many miss! change the state of the CacheManager ($emitter) to DISABLED
        $emitter->setState(new DisabledState());
    }
}