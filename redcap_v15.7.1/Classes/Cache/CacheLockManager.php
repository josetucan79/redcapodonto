<?php
namespace Vanderbilt\REDCap\Classes\Cache;

use DateTime;
use Vanderbilt\REDCap\Classes\Utility\FileCache\CacheItem;
use Vanderbilt\REDCap\Classes\Utility\Sleeper\StoppableSleeper;
use Vanderbilt\REDCap\Classes\Utility\FileCache\CacheItemsManager;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\StorageInterface;

/**
 * WRITE-THROUGH CACHE SYSTEM
 * 
 * - self-cleanup via TTL
 * - different invalidation strategies can be applied to each entry
 */
class CacheLockManager
{
    const LOCK_TIMEOUT = 1800; // seconds
    const LOCK_PREFIX = 'LOCK';

    /**
     * The number of seconds until the lock expires. A new lock cannot be acquired until the current one expires.
     *
     * @var int
     */
    private $lockTime;


    /**
     *
     * @var CacheItemsManager
     */
    private $cacheItemsManager;

    /**
     *
     * @var string[]
     */
    private $locks = [];

    /**
     *
     * @param StorageInterface $storage
     */
    public function __construct(CacheItemsManager $cacheItemsManager, $lockTime = self::LOCK_TIMEOUT) {
        $this->cacheItemsManager = $cacheItemsManager;
        $this->lockTime = $lockTime;
        register_shutdown_function([$this, 'releaseLocks']);
    }

    public function getLockTime() { return $this->lockTime; }

    public function releaseLocks()
    {
        foreach ($this->locks as $lockKey) {
            $this->releaseLock($lockKey);
        }
    }

    /**
     * Tries to acquire a lock for a given cache key.
     *
     * This method checks if there is an existing valid lock for the provided cache key. If the lock exists and has not expired, 
     * the method will return false, indicating the lock's acquisition failure. If the lock does not exist or has expired, 
     * the method will set a new lock and return true, indicating the lock's successful acquisition.
     *
     * @param string $cacheKey The cache key for which the lock is to be acquired.
     * 
     * @return bool Returns true if the lock was successfully acquired, and false if the lock is still in place and valid.
     */
    public function acquireLock($cache_key) {
        $lockKey = self::LOCK_PREFIX . "-$cache_key";
        
        // Try to get the existing lock
        $existingLock =  $this->cacheItemsManager->get($lockKey);
        $now = new DateTime();

        if ($existingLock instanceof CacheItem) {
            $expirationTime = $existingLock->getExpirationTime();

            if ($expirationTime instanceof DateTime) {
                // If expirationTime is a DateTime object, the lock has an expiry time set.
                if ($expirationTime > $now) {
                    // Lock is still valid (not expired)
                    return false;
                }
                // If we reached here, it means the lock has expired. We'll overwrite it.
            } else {
                // If expirationTime is false, the lock doesn't have an expiry time, so we consider it valid.
                return false; // or you could decide to overwrite it depending on your business logic.
            }
        }

        // Set the lock in the cache with the current time as data
        $lockData = ['lockTime' => time()];
        $lock = $this->cacheItemsManager->make($lockKey, $lockData, $this->lockTime);
        $this->cacheItemsManager->save($lock);
        $this->locks[] = $lockKey;

        return true;
    }

    /**
     * Releases an existing lock for a given cache key.
     *
     * This method will remove the lock for the specified cache key, allowing a new lock to be acquired. 
     * If there is no existing lock, the method will not perform any action, ensuring safe usage even when the lock is uncertain.
     *
     * @param string $lockKey The cache key for which the lock is to be released.
     * 
     * @return void
     */
    public function releaseLock($lockKey) {
        $item = $this->cacheItemsManager->get($lockKey);
        if(!$item) return;
        $this->cacheItemsManager->delete($item);
    }

    /**
     * Checks if a lock is free (i.e., non-existent or expired) for a given cache key.
     *
     * @param string $cacheKey
     * @return bool
     */
    public function lockIsFree($cacheKey) {
        $lockKey = self::LOCK_PREFIX. "-$cacheKey";
        $existingLock = $this->cacheItemsManager->get($lockKey);
        return $existingLock === false;
    }

    /**
     * Waits for a lock to be released on a specific cache key.
     * 
     * This method is used during a cache-miss scenario when another process is already 
     * rebuilding the cache. It prevents multiple processes from attempting to rebuild 
     * the cache simultaneously for the same key, reducing redundant operations and 
     * ensuring data consistency.
     * 
     * The method employs a StoppableSleeper along with a LockTimeoutCondition to implement
     * an interruptible waiting mechanism. It waits until either the lock is free or a 
     * predefined timeout period elapses.
     *
     * If the lock is free before the timeout, the method returns true, allowing the calling 
     * process to proceed with the cache rebuild. If the timeout is reached before the lock 
     * becomes free, the method returns false, indicating that it ceased waiting due to the 
     * elapsed timeout.
     *
     * @param string $cache_key The unique cache key string representing the cache entry being locked.
     * @return boolean returns false if the wait is interrupted before the lock is free.
     */
    public function waitForLock($cache_key) {
        $maxWaitInterval = $this->lockTime;

        // Initialize the interrupt condition and sleeper
        $timeoutCondition = new LockTimeoutCondition($this, $cache_key);
        
        if(!$this->lockIsFree($cache_key)) {
            StoppableSleeper::sleep($maxWaitInterval, $timeoutCondition);
            return $this->lockIsFree($cache_key);
        }
        return true;
    }

    /**
     * Calculates the maximum time to hold a lock based on the system's maximum execution time.
     *
     * This method retrieves the system's maximum execution time setting and applies a given 
     * threshold to calculate the maximum time a lock can be held. This approach helps in ensuring 
     * that the lock-holding operation completes within a safe margin under the script's maximum 
     * execution time limit, thus preventing script termination due to exceeding the execution time.
     *
     * The threshold is a fraction (expressed as a value between 0 and 1) of the system's maximum 
     * execution time. It defines the portion of the maximum execution time that can be safely 
     * allocated to holding the lock.
     *
     * @param float $maxExecutionThreshold The threshold fraction of the maximum execution time to be used for the lock.
     * @return int The calculated maximum lock time in seconds. It is determined as a fraction of the 
     *             system's maximum execution time, defined by the provided threshold.
     */
    public static function calcMaxSystemLockTime($maxExecutionThreshold = .8) {
        $maxExecutionTime = intval(ini_get('max_execution_time')); // Retrieve the system's max execution time
        $maxLockTime = floor($maxExecutionTime * $maxExecutionThreshold);
        return $maxLockTime;
    }
}