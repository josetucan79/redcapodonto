<?php
namespace Vanderbilt\REDCap\Classes\Utility\FileCache;


class CacheItemsManager {
    private $fileCache;


    const OPTION_CACHE_DIR = 'cache_dir';
    const OPTION_FILENAME_VISITOR = 'filename_visitor';

    public function __construct(FileCache $fileCache) {
        $this->fileCache = $fileCache;
    }

    /**
     *
     * @param string $key
     * @param mixed $data
     * @param int $ttl
     * @return CacheItem
     */
    public function make($key, $data, $ttl = FileCache::DEFAULT_TTL) {
        return new CacheItem($key, $data, $ttl);
    }

    /**
     *
     * @param string $key
     * @return CacheItem
     */
    public function get($key) {
        $serialized = $this->fileCache->get($key);
        if($serialized === false) return false;
        return unserialize($serialized, ['allowed_classes' => [CacheItem::class]]);
    }

    /**
     * @param FileCache $fileCache
     * @param mixed $data
     * @return CacheItem
     */
    public function save(CacheItem $item) {
        $ttl = $item->getExpirationTime() - $item->getCreationTime(); // keep the original ttl if saved multiple times
        $serialized = serialize($item);
        $this->fileCache->set($item->getKey(), $serialized, $ttl);
        return $item;
    }

    /**
     *
     * @param CacheItem $item
     * @return void
     */
    public function delete(CacheItem $item) {
        $this->fileCache->delete($item->getKey());
    }
}
