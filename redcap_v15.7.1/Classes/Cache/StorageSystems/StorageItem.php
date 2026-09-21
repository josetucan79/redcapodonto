<?php
namespace Vanderbilt\REDCap\Classes\Cache\StorageSystems;

use DateTime;
use Vanderbilt\REDCap\Classes\Cache\REDCapCache;

/**
 * defines the structure of a storage list item
 */
class StorageItem
{
    public $cache_key;
    public $ts;
    public $expiration;
    public $invalidationStrategies;
    public $data;

    public function __construct($cache_key, $ts, $expiration, $invalidationStrategies, $data='')
    {
        $this->cache_key = $cache_key;
        $this->ts = $ts;
        $this->expiration = $expiration;
        $this->invalidationStrategies = $invalidationStrategies;
        $this->data = $data;
    }

    /**
     *
     * @return string
     */
    public function cacheKey() { return $this->cache_key; }

    /**
     *
     * @return DateTime|false
     */
    public function timestamp() { return $this->makeDate($this->ts); }

    /**
     *
     * @return DateTime|false
     */
    public function expiration() { return $this->makeDate($this->expiration); }

    /**
     *
     * @return array
     */
    public function invalidationStrategies() { return $this->invalidationStrategies; }

    /**
     *
     * @return string
     */
    public function data() { return $this->data; }

    public function setData($value) { $this->data = $value; }

    /**
     * check if the itrem is expired
     *
     * @return boolean
     */
    function isExpired() {
        $expiration = $this->expiration();
        if(!($expiration instanceof DateTime)) return false;
        $now = new DateTime();
        return $expiration < $now;
    }

    /**
     * check if the item was created before a specific date
     *
     * @param DateTime $comparisonDateTime
     * @return boolean
     */
    function wasCreatedBefore(DateTime $comparisonDateTime) {
        if(!($comparisonDateTime instanceof DateTime)) return true;
        $timestamp = $this->timestamp();
        if(!($timestamp instanceof DateTime)) return false;
        return $timestamp < $comparisonDateTime;
    }

    /**
     *
     * @param DateTime $comparisonDateTime
     * @return boolean
     */
    function wasCreatedAfter(DateTime $comparisonDateTime) {
        if(!($comparisonDateTime instanceof DateTime)) return true;
        $timestamp = $this->timestamp();
        if(!($timestamp instanceof DateTime)) return false;
        return $timestamp > $comparisonDateTime;
    }

    private function makeDate($datetimeString) {
        if(!is_string($datetimeString)) return false;
        return DateTime::createFromFormat(REDCapCache::TIMESTAMP_FORMAT, $datetimeString);
    }

}