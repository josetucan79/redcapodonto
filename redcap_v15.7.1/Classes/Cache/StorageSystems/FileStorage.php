<?php
namespace Vanderbilt\REDCap\Classes\Cache\StorageSystems;

use DateTime;
use Throwable;
use Vanderbilt\REDCap\Classes\Cache\Exceptions\SerializationMemoryException;
use Vanderbilt\REDCap\Classes\Cache\Helpers\MemorySafeSerializer;
use Vanderbilt\REDCap\Classes\Cache\REDCapCache;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\Utility\FileCache\NameVisitorInterface;

/**
 * use a file-based cache
 */
class FileStorage implements StorageInterface
{
    private $list;

    const NAMESPACE_PREFIX = 'redcap-cache-';
    const METADATA_KEY = 'METADATA';
    /**
     * set the cache modification time to NOW
     * these fiels will not be collected by the remove_temp_deleted_files
     * functuion of the FILES class
     */
    const CACHE_TTL = 0;

    /**
     *
     * @param int $project_id
     * @param FileCache $fileCache
     */
    public function __construct(
        private int $project_id,
        private FileCache $fileCache,
        private ?MemorySafeSerializer $serializer = null
    ) {
        $cacheDir = empty($cacheDir) ? APP_PATH_TEMP : $cacheDir;
        $this->serializer ??= new MemorySafeSerializer();
    }

    private function createFolderIfNeeded($folderPath) {
        // Check if the folder already exists
        if (file_exists($folderPath)) {
            return true;
        }
    
        // Try to create the folder
        return mkdir($folderPath, 0755, true);
    }
    

    public function __destruct()
    {
        $this->saveList();
    }

    /**
     * Process storage item for saving with memory safety
     *
     * @param StorageItem $storageItem
     * @return string
     * @throws Exception
     */
    private function processDataForSaving($storageItem) {
        // Create a copy of the storage item for processing
        $itemForStorage = clone $storageItem;
        
        // Try to serialize the data safely - throw exception if it fails
        $serializedData = $this->serializer->serialize($itemForStorage->data());
        $itemForStorage->setData($serializedData);
        
        // Now serialize the entire storage item
        $processed = serialize($itemForStorage);
        
        // Encrypt the serialized data
        $processed = encrypt($processed);
        
        return $processed;
    }
    

    /**
     * Process data for retrieval
     *
     * @param string $data
     * @return StorageItem|false
     */
    private function processDataForRetrieval($data) {
        try {
            // Decrypt the data
            $processed = decrypt($data);
            
            // Unserialize the storage item
            $storageItem = unserialize($processed, ['allowed_classes' => [StorageItem::class]]);
            
            if (!$storageItem instanceof StorageItem) {
                return false;
            }
            
            // Unserialize the data
            if (is_string($storageItem->data())) {
                try {
                    $unserializedData = $this->serializer->unserialize($storageItem->data());
                    $storageItem->setData($unserializedData);
                } catch (SerializationMemoryException $e) {
                    // If unserialization fails, treat as cache miss
                    error_log("Failed to unserialize cache data for key: " . $storageItem->cacheKey() . 
                             " - " . $e->getMessage());
                    return false;
                }
            }
            
            return $storageItem;
            
        } catch (Throwable $e) {
            return false;
        }
    }

    public function get($cache_key) {
        $cached = $this->fileCache->get($cache_key);
        if($cached===false) return false;
        $storageItem = $this->processDataForRetrieval($cached);
        return $storageItem;
    }

    public function add($cache_key, $data, $ttl=null, $invalidationStrategies=[]) {
        if(!is_int($ttl)) $ttl = REDCapCache::DEFAULT_TTL;
        $ts = date(REDCapCache::TIMESTAMP_FORMAT);
        $expiration = date(REDCapCache::TIMESTAMP_FORMAT, time() + $ttl);
        $storageItem = new StorageItem($cache_key, $ts, $expiration, $invalidationStrategies, $data);
        $cacheData = $this->processDataForSaving($storageItem);
        // $ttl = self::CACHE_TTL;
        $this->fileCache->set($cache_key, $cacheData, $ttl);
        $this->updateList($cache_key, $storageItem);
        return $storageItem;
    }

    public function delete($cache_key) {
        $this->fileCache->delete($cache_key);
        $this->deleteFromList($cache_key);
    }

    /**
     *
     * @return StorageItem[]
     */
    public function getList() {
        if(!is_array($this->list)) {
            $serializedList = $this->fileCache->get(self::METADATA_KEY);
            if (!is_string($serializedList)) {
                // Nothing cached or corrupted, initialize empty list
                $this->list = [];
                return $this->list;
            }

            $list = unserialize($serializedList, ['allowed_classes' => [StorageItem::class]]);
            $this->list = is_array($list) ? $list : [];
        }
        return $this->list;
    }

    /**
     * Save the list of cached items.
     * Ensures that the expiration time of the list aligns with the expiration 
     * time of the item that expires last
     *
     * @return void
     */
    public function saveList() {
        $getLatestExpiration = function () {
            $latestExpiration = REDCapCache::DEFAULT_TTL;
            $list = $this->getList();
            $now = new DateTime();
            foreach ($list as $storageItem) {
                $expiration = $storageItem->expiration();
                if(!$expiration instanceof DateTime) continue;
                $difference = $now->getTimestamp() - $expiration->getTimestamp();
                if($difference > $latestExpiration) $latestExpiration = $difference;
            }
            return $latestExpiration;
        };
        $serializedList = serialize($this->getList());
        $ttl = $getLatestExpiration();
        // $ttl = self::CACHE_TTL;
        $this->fileCache->set(self::METADATA_KEY, $serializedList, $ttl);
    }

    /**
     *
     * @param string $key
     * @param StorageItem $storageItem
     * @return void
     */
    public function updateList($key, $storageItem) {
        $list = $this->getList();
        $storageItem->setData(null); // do not store the data in the list
        $list[$key] = $storageItem;
        $this->list = $list;
    }

    public function deleteFromList($key) {
        $list = $this->getList();
        unset($list[$key]);
    }
}