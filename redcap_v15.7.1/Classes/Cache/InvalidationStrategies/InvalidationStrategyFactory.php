<?php
namespace Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies;

use Vanderbilt\REDCap\Classes\Cache\REDCapCache;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\StorageItem;

/**
 * create strategies based on the stored signatures
 */
class InvalidationStrategyFactory
{
    /**
     *
     * @param REDCapCache $redcapCache
     * @param StorageItem $storageItem
     * @return InvalidationInterface[]
     */
    public function make(REDCapCache $redcapCache, StorageItem $storageItem) {
        $signatures = $storageItem->invalidationStrategies();
        $strategies = [];
        foreach ($signatures as $signature) {
            $strategy = self::makeOne($redcapCache, $storageItem, $signature);
            if($strategy instanceof InvalidationInterface) $strategies[] = $strategy;
        }
        return $strategies;
    }

    public function makeOne($redcapCache, $storageItem, $signature) {
        self::extract($signature, $identifier, $arguments);
        $strategy = null;
        switch ($identifier) {
            case 'project-activity':
                $projectID = $arguments[0] ??null;
                $strategy = new ProjectActivityInvalidation($redcapCache, $storageItem, $projectID);
                break;
            default:
                break;
        }
        return $strategy;
    }

    /**
     * split the signature and extract identifier and potential arguments
     *
     * @param string $signature
     * @return array
     */
    private function extract($signature, &$identifier='', &$arguments=[]) {
        $parts = explode(':', $signature);
        $identifier = array_splice($parts, 0, 1)[0] ?? null;
        $arguments = $parts;
    }
}