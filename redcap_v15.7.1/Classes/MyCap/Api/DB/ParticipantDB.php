<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\DB;

use Vanderbilt\REDCap\Classes\MyCap\Api\Exceptions\ParticipantNotFoundException;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\Participant;

/**
 * Enumerates error types for the API class
 */
class ParticipantDB
{
    /**
     * Load participant given a participant code
     *
     * @param $code
     * @return array
     */
    public function loadByCode($code)
    {
        if (!strlen(trim($code))) {
            throw new ParticipantNotFoundException("Participant code has no length.");
        }

        $participants = array();
        $sql = "SELECT * FROM redcap_mycap_participants WHERE code = '" . db_escape($code) ."'";
        $q = db_query($sql);
        if (db_num_rows($q) == 0) {
            throw new ParticipantNotFoundException("Could not find participant code $code");
        } elseif (db_num_rows($q) > 1) {
            throw new \Exception(
                "Participant project contains multiple participants with code $code. There should only be 1"
            );
        } else {
            while ($row = db_fetch_assoc($q)) {
                foreach ($row as $key=>$value) {
                    $participants[$key] = $value;
                }
            }
        }
        return $participants;
    }

    /**
     * Get current push ids of participant given a participant code
     *
     * @param $code
     * @return string
     */
    public function getCurrentPushIds($code)
    {
        $sql = "SELECT push_notification_ids FROM redcap_mycap_participants WHERE code = '" . db_escape($code) ."'";
        $result = db_query($sql);
        $pushIds = db_result($result, 0);

        return $pushIds;
    }

    /**
     * Get Baseline date for participant
     *
     * @param string $par_code
     * @param string $project_code
     * @param boolean $convertDateFormat
     * @return string
     */
    public function getBaselineDate($par_code, $project_code, $convertDateFormat = false) {
        $sql = "SELECT record, event_id FROM redcap_mycap_participants WHERE code = '" . db_escape($par_code) ."'";

        $result = db_query($sql);
        $record = db_result($result, 0, "record");
        $eventId = db_result($result, 0, "event_id");

        $projectId = MyCap::getProjectIdByCode($project_code);

        $baseline_date_identifier = Participant::getBaselineDateIdentifier($record, $projectId, $eventId, $convertDateFormat);
        return $baseline_date_identifier;
    }

    /**
     * Save participant push identifier
     * @param string $allIdsJson
     * @param array $data
     *
     * @return boolean
     */
    public function savePushIdentifier($allIdsJson, $data)
    {
        $doNotSave = $data['doNotSave'] ?? false;
        $projectId = MyCap::getProjectIdByCode($data['stu_code']);
        $par_code = $data['par_code'];

        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        $sql = "UPDATE redcap_mycap_participants SET push_notification_ids = '".$allIdsJson."' WHERE project_id = '".$projectId."' AND code = '".db_escape($par_code)."'";
        $q = db_query($sql);
        if ($q == false) {
            // ERROR: Roll back all changes made and return the error message
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
            return false;
        } else {
            if ($doNotSave == true) {
                db_query("ROLLBACK");
                db_query("SET AUTOCOMMIT=1");
            } else {
                db_query("COMMIT");
                db_query("SET AUTOCOMMIT=1");
            }
            return true;
        }
    }

    /**
     * Save baseline date for participant
     *
     * @param array $data
     * @return boolean
     */
    public function saveBaselineDate($data)
    {
        $doNotSave = $data['doNotSave'] ?? false;
        $projectId = MyCap::getProjectIdByCode($data['stu_code']);
        $par_code = $data['par_code'];

        // Get record to update
        $sql = "SELECT record FROM redcap_mycap_participants WHERE code = '" . db_escape($par_code) ."'";
        $result = db_query($sql);
        $record = db_result($result, 0);

        // Get field to which value need to update
        $myCapProj = new MyCap($projectId);
        $baseline_field = $myCapProj->project['baseline_date_field'];

        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        $Proj = new \Project($projectId);
        $eventId = $Proj->firstEventId;

        if ($Proj->longitudinal) {
            if ($Proj->multiple_arms) {
                $recordArms = \Records::getArmsForAllRecords($projectId, [$record]);
                $allArms = $recordArms[$record] ?? [];
                foreach ($allArms as $recordArm) {
                    $arm = $recordArm;
                }
                $eventsInArm = $Proj->getEventsByArmNum($arm);
                $fields = explode("|", $baseline_field);
                foreach ($fields as $field1) {
                    list ($event_id, $field_name) = explode("-", $field1);
                    if (in_array($event_id, $eventsInArm)) {
                        $eventId = $event_id;
                        $baseline_field = $field_name;
                        break;
                    }
                }
            } else {
                $date_arr = explode("-", $myCapProj->project['baseline_date_field']);
                if ($Proj->longitudinal && count($date_arr) > 1) {
                    list ($eventId, $baseline_field) = $date_arr;
                }
            }
        }

        $sql = "UPDATE ".\Records::getDataTable($projectId)." SET value = '".db_escape($data['zerodate'])."' 
                WHERE project_id = $projectId AND record = '".db_escape($record)."' and event_id = $eventId
                AND field_name = '".db_escape($baseline_field)."' and instance is null";
        $q = db_query($sql);
        if (!$q || db_affected_rows() < 1) {
            $sql = "INSERT INTO ".\Records::getDataTable($projectId)." (project_id, event_id, record, field_name, value) VALUES
                    ($projectId, $eventId, '".db_escape($record)."', '".db_escape($baseline_field)."', '".db_escape($data['zerodate'])."')";
            $q = db_query($sql);
        }

        if ($q == false) {
            // ERROR: Roll back all changes made and return the error message
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
            return false;
        } else {
            if ($doNotSave == true) {
                db_query("ROLLBACK");
                db_query("SET AUTOCOMMIT=1");
            } else {
                db_query("COMMIT");
                db_query("SET AUTOCOMMIT=1");
            }
            return true;
        }
    }

    /**
     * Get Install/join date for participant
     *
     * @param string $par_code
     * @param string $project_code
     * @return string
     */
    public function getInstallDate($par_code, $project_code) {
        $projectId = MyCap::getProjectIdByCode($project_code);

        $sql = "SELECT join_date FROM redcap_mycap_participants WHERE code = '" . db_escape($par_code) ."' AND project_id='".$projectId."'";
        $result = db_query($sql);
        $join_date = db_result($result, 0);

        return $join_date;
    }

    /**
     * Save Install/join date for participant
     *
     * @param array $data
     *
     * @return boolean
     */
    public function saveInstallDate($data)
    {
        $doNotSave = $data['doNotSave'] ?? false;
        $projectId = MyCap::getProjectIdByCode($data['stu_code']);
        $par_code = $data['par_code'];

        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        $sql = "UPDATE redcap_mycap_participants SET join_date = '".$data['joindate']."', join_date_utc = ".checkNull($data['utcTime']).", timezone = '".$data['timezone']."'  WHERE project_id = $projectId AND code = '".db_escape($par_code)."'";

        $q = db_query($sql);
        if ($q == false) {
            // ERROR: Roll back all changes made and return the error message
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
            return false;
        } else {
            if ($doNotSave == true) {
                db_query("ROLLBACK");
                db_query("SET AUTOCOMMIT=1");
            } else {
                db_query("COMMIT");
                db_query("SET AUTOCOMMIT=1");
            }
            return true;
        }
    }

    /**
     * Delete participant push identifier from list
     * @param string $removePushId
     * @param string $par_code
     * @param integer $projectId
     *
     * @return boolean
     */
    public function removePushIdentifier($removePushId, $par_code, $projectId)
    {
        $currentPushIds = $this->getCurrentPushIds($par_code);
        $allIds = json_decode($currentPushIds);
        if ($currentPushIds !== null && strlen($currentPushIds)) {
            if (($key = array_search($removePushId, $allIds)) !== false) {
                unset($allIds[$key]);
            }
        }
        $allIdsJson = (!empty($allIds)) ? json_encode(array_values($allIds)) : '';

        $sql = "UPDATE redcap_mycap_participants SET push_notification_ids = ".checkNull($allIdsJson)." WHERE project_id = '".$projectId."' AND code = '".db_escape($par_code)."'";
        db_query($sql);
    }
}
