<?php
namespace Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies;

use Vanderbilt\REDCap\Classes\Cache\REDCapCache;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\StorageItem;

/**
 * interface for Cache invalidation strategies
 */
interface InvalidationInterface
{

    /**
     * make a signature that will be stored
     * in cache.
     *
     * @param mixed ...$args
     * @return string
     */
    public static function signature(...$args);

    /**
     * validate
     * 
     * @return boolean
     */
    public function validate();
}