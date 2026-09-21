<?php
namespace Vanderbilt\REDCap\Classes\Cache\StorageSystems;

/**
 * defines an interface for storage strategies
 */
interface StorageInterface
{
    /**
     *
     * @param string $cache_key
     * @return StorageItem
     */
    public function get($cache_key);

    /**
     *
     * @param string $cache_key
     * @param mixed $data
     * @param integer $ttl
     * @param array $invalidationStrategies
     * @return StorageItem
     */
    public function add($cache_key, $data, $ttl=null, $invalidationStrategies=[]);

    /**
     *
     * @param string $cache_key
     * @return void
     */
    public function delete($cache_key);

    /**
     *
     * @return StorageItem[]
     */
    public function getList();
}