<?php
namespace Vanderbilt\REDCap\Classes\Cache;

use System;
use Vanderbilt\REDCap\Classes\Utility\Mediator\ObserverInterface;

/**
 * 
 * this class provides logic to manage errors
 * notified by the cache system
 */
class CacheErrorManager implements ObserverInterface
{

    public function __construct(private int $project_id) {}

    /**
     *
     * @param CacheManager $emitter
     * @param string $event
     * @param mixed $data
     * @return void
     */
    public function update($emitter, $event, $data=null) {
        switch ($event) {
            case CacheManager::NOTIFY_ERROR:
                $this->onCacheError($emitter, $data);
                break;
            default:
                break;
        }
    }



    /**
     * change the state of the cache manager if
     * too many misses in recent activity
     *
     * @param CacheManager $emitter
     * @param string $data
     * @return void
     */
    private function onCacheError($emitter, $data) {
        $project_id = $this->project_id;
        $cacheKey = $data['cache_key'] ?? null;
        $error = $data['error'] ?? 'Error';
        $message = $data['message'] ?? 'Unknown error';

        $composedError = "Project ID {$project_id} | Cache storage failed - Key: {$cacheKey} | Error: {$error} | Message: {$message}";
        System::addErrorToRCErrorLogTable($composedError);
    }

    
}