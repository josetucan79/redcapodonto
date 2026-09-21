<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Utility;

use Project;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;

class RewardsFeatureChecker
{
    /**
     * Check if the Rewards feature is enabled at the system level
     */
    public static function isSystemEnabled(): bool
    {
        $config = REDCapConfigDTO::fromDB();
        return filter_var($config->rewards_enabled_global, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Check if the Rewards feature is enabled for a given project
     *
     * @param Project|int $project Either a Project object or project_id
     */
    public static function isProjectEnabled(Project|int|string $project): bool
    {
        if($project instanceof Project) {
            $enabled = $project->project['rewards_enabled'] ?? false;
            return filter_var($enabled, FILTER_VALIDATE_BOOL);
        }
        // Fallback: return false if invalid ID
        if (!$project) return false;

        $sql = "SELECT rewards_enabled FROM redcap_projects WHERE project_id = ?";
        $result = db_query($sql, [$project]);
        if($row = db_fetch_assoc($result)) {
            $enabled = $row['rewards_enabled'] ?? false;
            return filter_var($enabled, FILTER_VALIDATE_BOOL);
        }
        return false;
    }
}
