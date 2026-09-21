<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\DB;

use Vanderbilt\REDCap\Classes\MyCap\Api\Exceptions\StudyNotFoundException;

/**
 * Enumerates error types for the API class
 */
class Project
{
    /**
     * Load metadata given a study code
     *
     * @param $code
     * @throws \Exception
     * @throws StudyNotFoundException
     */
    public function loadByCode($code)
    {
        $project = array();
        $sql = "SELECT * FROM redcap_mycap_projects WHERE code = '" . db_escape($code) ."'";
        $q = db_query($sql);
        if (db_num_rows($q) == 0) {
            throw new StudyNotFoundException("Could not find study code $code");
        } elseif (db_num_rows($q) > 1) {
            throw new \Exception(
                "Metadata project contains multiple data projects with code $code. There should only be 1"
            );
        } else {
            while ($row = db_fetch_assoc($q)) {
                foreach ($row as $key=>$value) {
                    $project[$key] = $value;
                }
            }
            $Proj = new \Project($project['project_id']);
            // Return error if project was enabled but now it is disabled for MyCap OR project is deleted temporarily
            if ($Proj->project['mycap_enabled'] == 0 || $Proj->project['date_deleted'] != '') {
                throw new StudyNotFoundException("Could not find study code $code");
            }
            if ($Proj->project['completed_time'] != '') { // Project marked as "Complete"
                throw new StudyNotFoundException("Could not find study code $code");
            }
        }
        return $project;
    }
}
