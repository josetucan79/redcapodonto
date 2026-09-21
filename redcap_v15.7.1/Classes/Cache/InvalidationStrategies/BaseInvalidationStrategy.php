<?php
namespace Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies;

use DateTime;
use Vanderbilt\REDCap\Classes\Cache\REDCapCache;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\StorageItem;

/**
 * interface for Cache invalidation strategies
 */
abstract class BaseInvalidationStrategy implements InvalidationInterface
{
    protected $redcapCache;
    protected $storageItem;

    /**
     *
     * @param REDCapCache $redcapCache
     * @param StorageItem $storageItem
     */
    public function __construct($redcapCache, $storageItem)
    {
        $this->redcapCache = $redcapCache;
        $this->storageItem = $storageItem;
    }
    
    public static function signature(...$args) { return ""; }

    public function validate() { return true; }
}