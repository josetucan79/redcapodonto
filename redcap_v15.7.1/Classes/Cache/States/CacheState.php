<?php
namespace Vanderbilt\REDCap\Classes\Cache\States;

interface CacheState {
    public function getOrSet($callable, $args=[], $options=[], &$cache_key=null);
    public function hasCacheMiss(): bool;
}
