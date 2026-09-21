<?php
namespace Vanderbilt\REDCap\Classes\Cache;

use Vanderbilt\REDCap\Classes\Cache\StorageSystems\StorageItem;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\StorageInterface;
use Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies\InvalidationStrategyFactory;

/**
 * WRITE-THROUGH CACHE SYSTEM
 * 
 * - self-cleanup via TTL
 * - different invalidation strategies can be applied to each entry
 */
class REDCapCache
{
    /**
     *
     * @var integer
     */
    private $project_id;

    /**
     *
     * @var StorageInterface
     */
    private $storage;

    /**
     *
     * @var InvalidationStrategyFactory
     */
    private $invalidationFactory;

    /**
     *
     * @var array
     */
    private $recentList = []; // contains data that was just cached

    const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';
    const DEFAULT_TTL = 432000; // seconds

    const OPTION_TTL = 'ttl'; // time to live of the cache
    const OPTION_INVALIDATION_STRATEGIES = 'invalidationStrategies'; // strategies for cache invalidation
    const OPTION_SALT = 'salt'; // salt to add to the hashed key of the cache

    /**
     *
     * @param int $project_id
     * @param StorageInterface $storage
     * @param InvalidationStrategyFactory $invalidationFactory
     */
    public function __construct($project_id, $storage, $invalidationFactory) {
        $this->project_id = $project_id;
        $this->storage = $storage;
        $this->invalidationFactory = $invalidationFactory;
        $this->purgeExpired();
    }

    public function projectID() {
        return $this->project_id;
    }

    /**
     * set a key/value pair
     *
     * @param string $cache_key
     * @param mixed $data
     * @return StorageItem|false
     */
    public function get($cache_key) {
        $storageItem = $this->storage->get($cache_key);
        $valid = $this->validate($storageItem);
        if(!$valid) return;
        return $storageItem;
    }

    /**
     * set a key/value pair
     *
     * @param string $cache_key
     * @param mixed $data
     * @return StorageItem
     */
    public function set($cache_key, $data, $options=[]) {
        $options = $this->applyDefaultOptions($options); 
        $ttl = $options[self::OPTION_TTL];
        $invalidationStrategies = $options[self::OPTION_INVALIDATION_STRATEGIES];

        $storageItem = $this->storage->add($cache_key, $data, $ttl, $invalidationStrategies);
        $this->recentList[] = $cache_key;

        return $storageItem;
    }

    /**
     * delete an entry
     *
     * @param string $cache_key
     * @return void
     */
	public function delete($cache_key) {
        $this->storage->delete($cache_key);
    }

    /**
     * delete cache entries:
     * - if there where recent changes to the project
     * - if the cache expired
     *
     * @return void
     */
    public function purgeExpired() {
        $list = $this->storage->getList();
        foreach ($list as $cache_key => $item) {
            if($item->isExpired()) {
                $this->delete($cache_key);
                continue;
            }
        }
    }

    public function validate($storageItem) {
        if(!($storageItem instanceof StorageItem)) return false;
        // check invalidation strategies
        $strategies = $this->invalidationFactory->make($this, $storageItem);
        foreach ($strategies as $strategy) {
            $valid = $strategy->validate();
            if(!$valid) return false;
        }
        return true;
    }

    public function applyDefaultOptions($options=[]) {
        $defaultOptions = [
            self::OPTION_TTL => self::DEFAULT_TTL,
            self::OPTION_INVALIDATION_STRATEGIES => [],
            self::OPTION_SALT => null,
        ];
        return array_merge($defaultOptions, $options);
    }

    /**
     * delete all data
     *
     * @return void
     */
    public function reset() {
        $list = $this->storage->getList();
        foreach ($list as $cache_key => $value) {
            $this->storage->delete($cache_key);
        }
    }

    /**
     * return elements that were cached in previous sessions
     *
     * @return array
     */
    public function previouslyCachedData() {
        $list = $this->storage->getList();
        $diff = array_diff(array_keys($list), $this->recentList);
        return $diff;
    }

}