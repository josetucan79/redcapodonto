<?php
namespace Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies;

use DateTime;

/**
 * interface for Cache invalidation strategies
 */
class ProjectActivityInvalidation extends BaseInvalidationStrategy
{
    private $project_id;

    public function __construct($redcapCache, $storageItem, $project_id)
    {
        parent::__construct($redcapCache, $storageItem);
        $this->project_id = $project_id;
    }

    public static function signature(...$args) {
        $project_id = $args[0] ?? null;
        return "project-activity:$project_id";
    }

    public function validate() {
        $last_logged_event = \Project::getLastLoggedEvent($this->project_id, true);
        if ($last_logged_event === null) return true;
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $last_logged_event);
        return $this->storageItem->wasCreatedAfter($date);
    }
}