<?php
namespace Vanderbilt\REDCap\Classes\Cache\StorageSystems;

use Vanderbilt\REDCap\Classes\Cache\CacheActivityMonitor;
use Vanderbilt\REDCap\Classes\Cache\CacheLockManager;
use Vanderbilt\REDCap\Classes\Utility\FileCache\NameVisitorInterface;

class FileStorageNameVisitor implements NameVisitorInterface {
    const EXTENSION = "rr";
    private $project_id;

    public function __construct($project_id) {
        $this->project_id = $project_id;
    }

    public function visit($key, $hashedFilename, $extension) {
        $modifiedName = "PID$this->project_id";
        $extension = self::EXTENSION;
        if($key === FileStorage::METADATA_KEY) $modifiedName .= "-$key"; // add METADATA_KEY if this is a metadata file
        else if(preg_match("/^".CacheActivityMonitor::KEY_PREFIX."/", $key)) $modifiedName .= "-$key";
        else if(preg_match("/^".CacheLockManager::LOCK_PREFIX."/", $key)) $modifiedName .= "-".CacheLockManager::LOCK_PREFIX;
        $modifiedName .= "-$hashedFilename";
        return [$modifiedName, $extension];
    }
}