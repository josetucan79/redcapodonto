<?php
namespace Vanderbilt\REDCap\Classes\Cache;

use Vanderbilt\REDCap\Classes\Utility\Sleeper\StopSleepConditionInterface;

class LockTimeoutCondition implements StopSleepConditionInterface {
    private $manager;
    private $cacheKey;

    /**
     *
     * @param CacheLockManager $manager
     * @param string $cacheKey
     */
    public function __construct($manager, $cacheKey) {
        $this->manager = $manager;
        $this->cacheKey = $cacheKey;
    }

    /**
     * interrupt sleep if the lock is free or timeout is reached
     *
     * @param int $elapsedTime maximum sleep seconds
     * @return boolean
     */
    public function shouldStop($elapsedTime) {
        return $this->manager->lockIsFree($this->cacheKey);
    }
}