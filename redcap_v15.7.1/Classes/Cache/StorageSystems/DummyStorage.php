<?php
namespace Vanderbilt\REDCap\Classes\Cache\StorageSystems;

/**
 * storage strategy that does not cache any data.
 * used as a fallback if no other strategy is applicable.
 */
class DummyStorage implements StorageInterface
{
    public function get($cache_key) { return false; }

    public function add($cache_key, $data, $ttl=null, $invalidationStrategies=[]) {}

    public function delete($cache_key) {}

    public function getList() { return []; }
}