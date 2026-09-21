<?php
namespace Vanderbilt\REDCap\Classes\Utility\FileCache;

use Serializable;

class CacheItem implements Serializable {
    private $cache;
    private $key;
    private $data;
    private $ttl;
    private $expiration;
    private $creation;

    const OPTION_CACHE_DIR = 'cache_dir';
    const OPTION_FILENAME_VISITOR = 'filename_visitor';

    public function __construct($key, $data, $ttl = FileCache::DEFAULT_TTL) {
        $this->key = $key;
        $this->data = $data;
        $this->ttl = $ttl;
        $this->expiration = time() + $ttl;
        $this->creation = time();
    }

    /**
     *
     * @param string $ttl
     * @return CacheItem
     */
    public function refresh($ttl=null) {
        $this->expiration = time() + ($ttl ?? $this->ttl);
        return $this;
    }

    public function getKey() {
        return $this->key;
    }

    public function getData() {
        return $this->data;
    }

    public function getCreationTime() {
        return $this->creation;
    }

    public function getTtl() {
        return $this->ttl;
    }

    public function getExpirationTime() {
        return $this->expiration;
    }

    public function setData($data) {
        $this->data = $data;
    }

    public function isExpired() {
        return time() >= $this->getExpirationTime();
    }

    public function __serialize(): array {
        // Return the data that should be serialized
        return [
            'key' => $this->key,
            'data' => $this->data,
            'ttl' => $this->ttl,
            'expiration' => $this->expiration,
            'creation' => $this->creation,
        ];
    }

    public function __unserialize(array $data): void {
        // Initialize object properties from serialized data
        $key = $data['key'] ?? null;
        $this->key = $key;
        $this->data = $data['data'] ?? null;
        $this->ttl = $data['ttl'] ?? null;
        $this->expiration = $data['expiration'] ?? null;
        $this->creation = $data['creation'] ?? null;
    }

    public function serialize(): ?string {
        $data = $this->__serialize();
        return serialize($data);
    }
    public function unserialize($serialized) {
        $data = unserialize($serialized, ['allowed_classes' => [self::class]]);
        $this->__unserialize($data);
    }
}
