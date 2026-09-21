<?php
namespace Vanderbilt\REDCap\Classes\Cache\StorageSystems;

use Vanderbilt\REDCap\Classes\Cache\REDCapCache;

/**
 * use the database to store cache
 */
class DatabaseStorage implements StorageInterface
{
    private $project_id;

    const CACHE_TABLE = 'redcap_cache';

    public function __construct($project_id)
    {
        $this->project_id = $project_id;
    }

    public function get($cache_key) {
        $tableName = self::CACHE_TABLE;
        $query = sprintf(
            "SELECT * FROM $tableName WHERE project_id = %u AND `cache_key` = %s LIMIT 1",
            $this->project_id, checkNull($cache_key)
        );
        $result = db_query($query);
        if($row = db_fetch_assoc($result)) {
            $ts = $row['ts'] ?? '';
            $expiration = $row['expiration'] ?? '';
            $invalidation_strategies = unserialize($row['invalidation_strategies'] ?? '', ['allowed_classes'=> false]);
            $data = unserialize($row['data'] ?? '', ['allowed_classes'=> false]);
            return new StorageItem($cache_key, $ts, $expiration, $invalidation_strategies, $data);
        }
        return false;
    }

    public function add($cache_key, $data, $ttl=null, $invalidationStrategies=[]) {
        $tableName = self::CACHE_TABLE;
        $serialized = serialize($data);
        $serializedInvalidationStrategies = serialize($invalidationStrategies);
        // TODO: adjust this based on the final decided size of the data field in the database
        if($this->isSizeExceeded($serialized, self::SIZE_LONG_BLOB)) return; // do not store if potentially truncated when saved

        $ts = date(REDCapCache::TIMESTAMP_FORMAT);
        $expiration = null;
        if(is_int($ttl)) $expiration = date(REDCapCache::TIMESTAMP_FORMAT, time() + $ttl);

        $query = sprintf(
            "INSERT INTO $tableName (`project_id`, `cache_key`, `data`, `ts`, `expiration`, `invalidation_strategies`)
                VALUES (%u, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                `data` = %s, `ts` = %s, `expiration` = %s, `invalidation_strategies` = %s;",
            $this->project_id,
            $db_cache_key = checkNull($cache_key),
            $db_data = checkNull($serialized),
            $db_ts = checkNull($ts),
            $db_expiration = checkNull($expiration),
            $db_InvalidationStrategies = checkNull($serializedInvalidationStrategies),
            $db_data,
            $db_ts,
            $db_expiration,
            $db_InvalidationStrategies
        );
        $result = db_query($query);
        if($result === false) throw new \Exception("Error saving cache in the database", 1);
        return new StorageItem($cache_key, $ts, $expiration, $invalidationStrategies, $data);
    }

    public function delete($cache_key) {
        $tableName = self::CACHE_TABLE;
        $query = sprintf(
            "DELETE FROM `$tableName` WHERE project_id = %u AND `cache_key` = %s",
            $this->project_id, checkNull($cache_key)
        );
        $result = db_query($query);
        return $result;
    }

    public function getList() {
        $tableName = self::CACHE_TABLE;
        $query = sprintf(
            "SELECT `cache_key`, `ts`, `expiration`, `invalidation_strategies`
            FROM $tableName WHERE project_id = %u",
            $this->project_id
        );
        $result = db_query($query);
        $list = [];
        while($row = db_fetch_assoc($result)) {
            $cache_key = $row['cache_key'] ?? null;
            if(!$cache_key) continue;
            $ts = $row['ts'] ?? '';
            $expiration = $row['expiration'] ?? '';
            $invalidation_strategies = unserialize($row['invalidation_strategies'] ?? '', ['allowed_classes' => false]);
            $listItem = new StorageItem($cache_key, $ts, $expiration, $invalidation_strategies);
            $list[$cache_key] = $listItem;
        }
        return $list;
    }

    /**
     * max bytes allowed in a TEXT field of a MySQL database
     * (as used by redcap_data)
     */
    const SIZE_BLOB = 65536;
    const SIZE_MEDIUM_BLOB = 16777216;
    const SIZE_LONG_BLOB = 4294967296;

  /**
   *
   * @param string $string
   * @param int $byteCount
   * @return void
   */
  function isSizeExceeded($string, $maxTextSize) {
    // Get the size of the string in bytes
    $stringSize = strlen($string);
    
    // Check if the size of the string exceeds the maximum size of TEXT data type
    if ($stringSize > $maxTextSize) {
      return true; // Size exceeded
    } else {
      return false; // Size within limit
    }
  }

}