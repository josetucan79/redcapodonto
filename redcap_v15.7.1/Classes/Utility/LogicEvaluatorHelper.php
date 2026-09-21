<?php
namespace Vanderbilt\REDCap\Classes\Utility;

use LogicTester;
use Project;
use Records;
use Piping;
use Exception;

class LogicEvaluatorHelper
{
    // Caches to improve performance
    protected static $projectCache = [];
    protected static $logicValidationCache = [];
    protected static $recordExistsCache = [];

    /**
     * Evaluate logic for a given project, record, and context.
     * Caches metadata and logic validations to reduce overhead.
     * 
     * @param string $logic
     * @param int $project_id
     * @param string $record
     * @param string|null $event_name
     * @param int $repeat_instance
     * @param string $repeat_instrument
     * @param string $current_context_instrument
     * @param array|null $record_data
     * @param bool $returnValue
     * @param bool $checkRecordExists
     * @return mixed Returns boolean or null if invalid
     * @throws Exception
     */
    public static function evaluateLogic(
        $logic, 
        $project_id, 
        $record, 
        $event_name = null, 
        $repeat_instance = 1, 
        $repeat_instrument = "",
        $current_context_instrument = "",
        $record_data = null, 
        $returnValue = false, 
        $checkRecordExists = true
    ) {
        // Basic validation
        if ($logic == "" || !is_numeric($project_id) || $record == "" || !is_numeric($repeat_instance)) {
            return null;
        }

        // Validate logic syntax via cache
        if (!isset(self::$logicValidationCache[$logic])) {
            if (!LogicTester::isValid($logic)) {
                // If logic isn't valid, don't cache as true
                return null;
            }
            self::$logicValidationCache[$logic] = true;
        }

        // Load project from cache
        if (!isset(self::$projectCache[$project_id])) {
            self::$projectCache[$project_id] = new Project($project_id);
        }
        $Proj = self::$projectCache[$project_id];

        // Determine event_id and append event logic if needed
        $event_id = $Proj->longitudinal ? null : $Proj->firstEventId;
        if (!empty($event_name)) {
            if ($Proj->longitudinal) {
                $event_names = $Proj->getUniqueEventNames();
                if (is_numeric($event_name) && isset($event_names[$event_name])) {
                    $event_id = $event_name;
                    $event_name = $event_names[$event_id];
                } elseif (in_array($event_name, $event_names)) {
                    $event_id = array_search($event_name, $event_names);
                } else {
                    return null;
                }
                $logic = LogicTester::logicPrependEventName($logic, $event_name, $Proj);
            }
        }

        // Validate repeating instrument if needed
        if ($repeat_instrument != "" && 
           ((is_numeric($event_id) && !$Proj->isRepeatingForm($event_id, $repeat_instrument))
            || (!is_numeric($event_id) && !$Proj->isRepeatingFormAnyEvent($repeat_instrument)))
        ) {
            $repeat_instrument = "";
        }

        // Check record existence once per unique (project_id, record)
        if ($checkRecordExists) {
            $cacheKey = $project_id . ':' . $record;
            if (!isset(self::$recordExistsCache[$cacheKey])) {
                self::$recordExistsCache[$cacheKey] = Records::recordExists($project_id, $record);
            }
            if (!self::$recordExistsCache[$cacheKey]) {
                return null;
            }
        }

        // If project is not repeating but we have repeat parameters, return null
        if (($repeat_instance > 1 || $repeat_instrument != "") && !$Proj->hasRepeatingFormsEvents()) {
            return null;
        }

        // Append instance if repeating
        if ($Proj->hasRepeatingFormsEvents() && ($Proj->isRepeatingEvent($event_id)
            || ($current_context_instrument != "" && $Proj->isRepeatingForm($event_id, $current_context_instrument)))
        ) {
            $logic = LogicTester::logicAppendInstance($logic, $Proj, $event_id, $current_context_instrument, $repeat_instance);
        }

        // Append current instance if logic fields are on repeating forms/events
        if (is_numeric($event_id) && ($Proj->isRepeatingEvent($event_id)
            || ($repeat_instrument != "" && $Proj->isRepeatingForm($event_id, $repeat_instrument)))
        ) {
            $logic = LogicTester::logicAppendCurrentInstance($logic, $Proj, $event_id);
        }

        // Pipe special tags
        $logic = Piping::pipeSpecialTags($logic, $project_id, $record, $event_id, $repeat_instance, null, true, null,
                                         $current_context_instrument, false, false, false, true, false, false, true);

        // Evaluate logic
        return LogicTester::evaluateLogicSingleRecord(
            $logic, 
            $record, 
            $record_data, 
            $project_id, 
            $repeat_instance,
            $repeat_instrument, 
            $returnValue, 
            true, 
            true
        );
    }
}
