<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Utility;

use Project;

/**
 * ProjectArmFetcher
 *
 * This class provides methods to get a information about a project's amrms.
 */
class ProjectArmFetcher  {

    /**
     * Get the arms of a project.
     *
     * This method initializes a Project object with the given project ID and retrieves
     * the arms associated with that project. Each arm is formatted into an array with
     * its number and name.
     *
     * @param int $project_id The ID of the project.
     * @return array An array of arms, where each arm is represented as an associative array
     *               with 'arm_num' and 'name' keys.
     */
    public static function getProjectArms($project_id) {
        $Proj = new Project($project_id);
        $arms = [];
		foreach ($Proj->events as $num => $data)
		{
			// Add to array
			$arms[$num] = array(
				'arm_num' => $num,
				'name'    => label_decode($data['name']),
				'events'  => $data['events'] ?? [],
			);
		}
        return $arms;
    }

    public static function getArmNumForEventId($project_id, $event_id) {
        $Proj = new Project($project_id);
        
        // Iterate over each arm and its events
        foreach ($Proj->events as $arm_num => $data) {
            // Check if the event_id exists in the current arm's events
            if (array_key_exists($event_id, $data['events'] ?? [])) {
                return $arm_num; // Return the arm number if found
            }
        }
        
        // Return null if the event_id was not found in any arm
        return null;
    }
    

    public static function getProjectArmUniqueName($project_id, $arm_number) {
        $Proj = new Project($project_id);
        $event_id = $Proj->getFirstEventIdArm($arm_number);
        return $Proj->getUniqueEventNames($event_id);
    }

    public static function getProjectArmFirstEvent($project_id, $arm_number) {
        $Proj = new Project($project_id);
        $event_id = $Proj->getFirstEventIdArm($arm_number);
        return $event_id;
    }

    public static function getArmEventsIds($project_id, $arm_number=1) {
        $Proj = new Project($project_id);
        return array_keys($Proj->events[$arm_number]['events'] ?? []);
    }

}