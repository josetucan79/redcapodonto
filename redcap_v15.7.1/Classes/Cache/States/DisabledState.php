<?php
namespace Vanderbilt\REDCap\Classes\Cache\States;

class DisabledState implements CacheState {
    public function getOrSet($callable, $args=[], $options=[], &$cache_key=null) {
        // Skip caching entirely and just return the data
        return $callable(...$args);
    }

    public function hasCacheMiss(): bool {
        return true;
    }
}