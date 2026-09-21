<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

/**
 * The REDCap shared library provides access to a number of computer adaptive tests (CATs) utilizing the PROMIS
 * item response theory (IRT) framework. REDCap downloads a PROMIS shared library instrument in the
 * .../redcap_vX.X.X/SharedLibrary/receiver.php file and fields are created as normal in the redcp_metadata table. A row
 * is also inserted into the redcap_library_map table which contains two helpful columns: promis_key, scoring_type.
 *
 * The redcap_config table has keys: promis_api_base_url, promis_enabled, promis_registration_id, promis_token
 *
 * This PromisApi class is useful when you need to download a Form or Calibration file.
 *
 * @see \PROMIS (.../redcap_vX.X.X/Classes/PROMIS)
 * @see https://www.redcap-cats.org/promis_api/
 * @package REDCapExt
 */
class PromisApi {

    /** @var string Each question is scored. Computer adaptive test. Non-linear questions. */
    const ENGINE_GRADED = '.Graded';
    /** @var string Promis form is scored at the very end. All questions asked linear. */
    const ENGINE_SCOREDSEQUENCE = '.ScoredSequence';
    /** @var string Promis form is not scored. All questions asked linear. */
    const ENGINE_SEQUENCE = '.Sequence';

    /** @var string Scoring calculated after each question. Computer adaptive test. */
    const SCORING_TYPE_EACH_ITEM = 'EACH_ITEM';

    /** @var string Scoring calculated at the end of the survey. Linear, but scored. */
    const SCORING_TYPE_END_ONLY = 'END_ONLY';

    /** @var string No scoring. Linear, not scored. */
    const SCORING_TYPE_NONE = '';

    const FINAL_TSCORE_FIELD_IDENTIFIER = 'finalTScoreFieldIdentifier';
    const FINAL_STDERROR_FIELD_IDENTIFIER = 'finalStdErrorFieldIdentifier';
    const FIELD_PREFIX = 'prefix';

    /**
     * Determine which field in the instrument is the final tscore and standard error. Also determines the prefix used
     * on all other fields.
     *
     * Follows logic in: \PROMIS::getParticipantResponses(). It is expected that the first field will be the final
     * tscore and the second field will be the stderror field, unless the PROMIS instrument contains the "Record ID"
     * field.
     *
     * @param $instrument
     * @param $pid
     * @return array
     * @throws \Exception
     */
    public static function fieldInfoForInstrument($instrument, $pid)
    {
        $fields = $dictionary = \REDCap::getDataDictionary(
            $pid,
            'array',
            false,
            null,
            $instrument
        );

        $recordIdFieldIdentifier = \REDCap::getRecordIdField();

        $info = [
            self::FINAL_TSCORE_FIELD_IDENTIFIER => '',
            self::FINAL_STDERROR_FIELD_IDENTIFIER => '',
            self::FIELD_PREFIX => ''
        ];

        $fieldIdentifiers = array_keys($fields);
        $firstFieldIdentifier = array_shift($fieldIdentifiers);
        if ($firstFieldIdentifier == $recordIdFieldIdentifier) {
            $info[self::FINAL_TSCORE_FIELD_IDENTIFIER] = array_shift($fieldIdentifiers);
            $info[self::FINAL_STDERROR_FIELD_IDENTIFIER] = array_shift($fieldIdentifiers);
        } else {
            $info[self::FINAL_TSCORE_FIELD_IDENTIFIER] = $firstFieldIdentifier;
            $info[self::FINAL_STDERROR_FIELD_IDENTIFIER] = array_shift($fieldIdentifiers);
        }

        $nextFieldIdentifier = array_shift($fieldIdentifiers);
        $fieldParts = explode('_', $nextFieldIdentifier);
        $info[self::FIELD_PREFIX] = $fieldParts[0];

        return $info;
    }

    /**
     * @param $instrument
     * @param $pid
     * @return string
     * @throws \Exception
     */
    public static function scoringTypeForInstrument($instrument, $pid)
    {
        // Get array of PROMIS instrument names (if any forms were downloaded from the Shared Library)
        $sql = "SELECT scoring_type
                FROM redcap_library_map
                WHERE project_id = '".db_escape($pid)."'
                    AND form_name = '".db_escape($instrument)."'
                    AND promis_key IS NOT NULL
                    AND promis_key != ''
                LIMIT 1";
        try {
            $q = db_query($sql);
            while ($result = db_fetch_assoc($q)) {
                $rows = $result;
            }
        } catch (\Exception $e) {
            throw new \Exception("Could not determine PROMIS scoring type for instrument $instrument in project $pid");
        }

        if (count($rows) == 1) {
            $scoringType = $rows[0]['scoring_type'];
            if (is_null($scoringType)) {
                $scoringType = self::SCORING_TYPE_NONE;
            }
            if (!in_array($scoringType, array(self::SCORING_TYPE_EACH_ITEM, self::SCORING_TYPE_END_ONLY, self::SCORING_TYPE_NONE))) {
                throw new \Exception("REDCap contains a PROMIS scoring type value ($scoringType) that is unexpected");
            }
            return $scoringType;
        }

        throw new \Exception("Could not find PROMIS scoring type for instrument $instrument in project $pid");
    }

    /**
     * Upload file with json string
     * @param string $json
     * @return integer $edoc_id
     */
    public function uploadFileWithJSONString($json) {
        $filename_tmp = APP_PATH_TEMP . substr(sha1(rand()), 0, 8) . 'results.json';
        file_put_contents($filename_tmp, $json);

        // Set file attributes as if just uploaded
        $file = array('name'=> basename($filename_tmp),
                      'type'=> 'application/json',
                      'size'=>filesize($filename_tmp),
                      'tmp_name'=>$filename_tmp);
        $edoc_id = \Files::uploadFile($file);

        return $edoc_id;
    }

    /**
     * Return array *only* of PROMIS instruments in this project downloaded from the Shared Library.
     * @return array
     */
    public static function batteryInstrumentsSeries()
    {
        global $Proj;
        $result = [];
        // Assign position and prev form name for each form
        $retVal = [];
        $sql = "SELECT *
                FROM redcap_library_map
                WHERE project_id = '" . PROJECT_ID . "'
                    AND battery = 1
                    AND promis_battery_key IS NOT NULL
                    AND promis_battery_key != ''";
        $q = db_query($sql);
        if (db_num_rows($q) > 0) {
            while ($row = db_fetch_assoc($q)) {
                $result[$row['form_name']][$row['acknowledgement_cache']][] = $row['form_name'];
                $tasks_order[$row['form_name']] = $Proj->forms[$row['form_name']]['form_number'];
            }
            // Reorder as per instrument order
            asort($tasks_order);
            $row2 = array();
            foreach ($tasks_order as $this_form=>$order) {
                $row2 = $result[$this_form];
                $rows[] = $row2;
            }
            // Group by acknowledgement cache time so that they belongs to same battery series
            $series = [];
            foreach ($rows as $identifier => $data) {
                foreach ($data as $time => $arr) {
                    $series[$time][] = $arr[0];
                }
            }


            foreach ($series as $time => $form_arr) {
                $position = 1;
                $total = count($form_arr);
                foreach ($form_arr as $form) {
                    $retVal[$form]['batteryPosition'] = $position;
                    $retVal[$form]['firstInstrument'] = $firstInstrument;
                    if ($position == 1)  $firstInstrument = $form;
                    $position++;
                }

            }
        }
        return $retVal;
    }
}
