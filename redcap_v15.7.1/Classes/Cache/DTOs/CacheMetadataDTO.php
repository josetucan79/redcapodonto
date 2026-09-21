<?php
namespace Vanderbilt\REDCap\Classes\Cache\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

/**
 * stores metadata related to the
 * cache of a page
 */
class CacheMetadataDTO extends DTO {
    
    /**
     *
     * @var boolean
     */
    public $loading = false;

    /**
     *
     * @var CacheRequestDTO[]
     */
    public $cacheHits = [];

    /**
     *
     * @var CacheRequestDTO[]
     */
    public $cacheMisses = [];

    /**
     * the class that originally created the object
     *
     * @var string|null
     */
    private $lock = null;

    /**
     *
     * @var string|null
     */
    public $page;

    public function __construct($data = []) {
        parent::__construct($data);
    }

    /**
     *
     * @param CacheRequestDTO $hit
     * @return void
     */
    public function addCacheRequest($hit) {
        if($hit->cacheMiss===true) {
            $this->cacheMisses[] = $hit;
        } else {
            $this->cacheHits[] = $hit;
        }
    }
    
    public function resetRequests() {
        $this->cacheMisses = [];
        $this->cacheHits = [];
    }

    /**
     * return the percentage of cacheHits
     *
     * @return double
     */
    public function missPercentage() {
        $totalHits = count($this->cacheHits);
        $totalMisses = count($this->cacheMisses);
        $totalRequests = $totalHits + $totalMisses;
        if($totalRequests===0) return 0;
        return $totalHits / $totalRequests;
    }

    public function hasAccess($identifier) {
        return ($this->lock === $identifier);
    }

    /**
     * lock the access to prevent multiple calls to modify
     *
     * @param string $identifier
     * @return void
     */
    public function lock($identifier) {
        $this->lock = $identifier;
    }

    public function unlock($identifier) {
        if($this->lock !== $identifier) return;
        $this->lock = null;
    }

    public function forceUnlock() {
        $this->lock = null;
    }

    public function isLocked($identifier) {
        if($this->lock === null) return false;
        if($this->lock === $identifier) return false;
        return true;
    }

    public function getData() {
        $data = parent::getData();
        $data['missPercentage'] = $this->missPercentage();
        $data['lastUpdate'] = $this->latestUpdate();
        return $data;
    }

    public function latestUpdate() {
        $totalRequests = count($this->cacheHits);
        if($totalRequests === 0) return;
        $latestCacheHit = max($this->cacheHits[0], ...$this->cacheHits);
        if($latestCacheHit instanceof CacheRequestDTO) return $latestCacheHit->ts;
    }

}