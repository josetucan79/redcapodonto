<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\PromisApi;
use Webmozart\Assert\Assert;

/**
 * A PROMIS task
 *
 * @package MyCap\Study\Config\Task\ActiveTask
 */
class Promis
{
    const ACTION_AWAKEN = '.Awaken';
    const ACTION_AWAKEN_AND_NOTIFY = '.AwakenAndNotify';
    const ACTION_AUTO_CONTINUE = '.AutoContinue';

    const CONDITION_COMPLETED = '.Completed';
    /** @var string UUID */
    public $oid = '00000000-0000-0000-0000-000000000000';

    /** @var string Promis\Engine */
    public $engineType = PromisApi::ENGINE_GRADED;

    /** @var string */
    public $finalTscoreFieldIdentifier = '';

    /** @var string */
    public $finalStdErrorFieldIdentifier = '';

    /** @var string */
    public $fieldPrefix = '';

    public function getFormFields()
    {
        return [];
    }

    /**
     * Assign array values to class variables to save extended config variables
     *
     * @return void
     */
    public function buildExtendedConfig($data = array())
    {
    }
    /**
     * @param $data
     * @throws \Exception
     */
    public function validateExtendedConfigParams($data = array())
    {
        //parent::build($data);

        Assert::string($data['oid'], "PROMIS oid is not a string: {$data['oid']}");
        Assert::length(
            $data['oid'],
            \REDCapExt\Promis\Api::OID_LENGTH,
            'A PROMIS oid must be '.\REDCapExt\Promis\Api::OID_LENGTH.' characters long'
        );
        Assert::string($data['engineType'], "PROMIS engineType is not a string: {$data['engineType']}");
        if (Promis\Engine::isInvalid($data['engineType'])) {
            throw new \Exception('PROMIS engine type ('.$data['engineType'].') is invalid');
        }
        Assert::string(
            $data['finalTscoreFieldIdentifier'],
            "PROMIS finalTscoreFieldIdentifier is not a string: {$data['finalTscoreFieldIdentifier']}"
        );
        Assert::minLength($data['finalTscoreFieldIdentifier'], 1);
        Assert::string(
            $data['finalStdErrorFieldIdentifier'],
            "PROMIS finalStdErrorFieldIdentifier is not a string: {$data['finalStdErrorFieldIdentifier']}"
        );
        Assert::minLength($data['finalStdErrorFieldIdentifier'], 1);
        Assert::string(
            $data['fieldPrefix'],
            "PROMIS fieldPrefix is not a string: {$data['fieldPrefix']}"
        );
        Assert::minLength($data['fieldPrefix'], 1);
    }

    /**
     * A REDCap PROMIS instrument needs to be processed to be compatible with MyCap. Form and Calibration files need to
     * be downloaded
     *
     * @param string $instrument
     * @param integer $projectId
     * @return string|null
     * @throws \Exception
     */
    public function setupIfNeeded($instrument, $projectId)
    {
        // The first time a PROMIS-driven task is saved we need to download the form and calibration file
        // Subsequent downloads are unnecessary because form and calibration files never change
        list ($isPromisInstrument, $isAutoScoringInstrument) = \PROMIS::isPromisInstrument($instrument);
        if ($isPromisInstrument) {
            $promisApi = new PromisApi();

            $promisKey = \PROMIS::getPromisKey($instrument);
            $name = "$promisKey.json";

            $categories = [MyCap::FILE_CATEGORY_PROMIS_FORM, MyCap::FILE_CATEGORY_PROMIS_CALIBRATION];

            $firstSave = false;
            foreach ($categories as $category) {
                $values = MyCap::loadFileByName($name, $category);
                // Generate JSON Files for .Promis and .PromisCalibration for this promis instrument ONLY once
                if (empty($values)) {
                    $firstSave = true;
                    global $promis_api_base_url;

                    if ($category === MyCap::FILE_CATEGORY_PROMIS_FORM) {
                        $category_num = 1; // "1" stands for PROMIS
                        // Call FORMS API to get name of this form
                        $url = $promis_api_base_url . \PROMIS::api_version . "/Forms/$promisKey.json";
                        $json = \PROMIS::promisApiRequest($url);
                    } else {
                        $category_num = 2; // "2" stands for PROMIS Calibration
                        $url = $promis_api_base_url . \PROMIS::api_version . "/Calibrations/$promisKey.json";
                        $json = \PROMIS::promisApiRequest($url);
                    }

                    if (!empty($json)) {
                        $edoc_id = $promisApi->uploadFileWithJSONString($json);

                        // Save uploaded file info to "redcap_mycap_projectfiles" table
                        global $myCapProj;
                        $sql = "INSERT INTO redcap_mycap_projectfiles (project_code, doc_id, `name`, category) VALUES
                                            ('".$myCapProj->project['code']."', '".$edoc_id."', '".$name."', '".$category_num."')";

                        db_query($sql);
                    }
                }
            }

            $extendedConfigJson = '';
            if (isset($myCapProj->tasks[$instrument]['task_id'])) {
                $taskId = $myCapProj->tasks[$instrument]['task_id'];
                $sql = "SELECT extended_config_json FROM redcap_mycap_tasks WHERE task_id = $taskId AND form_name = '".db_escape($instrument)."' LIMIT 1";
                $q = db_query($sql);
                if (db_num_rows($q)) {
                    $extendedConfigJson = strip_tags(label_decode(db_result($q, 0)));
                }
            }
            if (!isset($myCapProj->tasks[$instrument]['task_id']) || strlen($extendedConfigJson) == 0) {
                // force generation if extended config does not exist or is empty
                $firstSave = true;
            }

            if ($firstSave) {
                $fieldInfo = PromisApi::fieldInfoForInstrument($instrument, $projectId);
                $scoringType = PromisApi::scoringTypeForInstrument($instrument, $projectId);
                $engineType = PromisApi::ENGINE_GRADED;
                if ($scoringType == PromisApi::SCORING_TYPE_END_ONLY) {
                    $engineType = PromisApi::ENGINE_SCOREDSEQUENCE;
                } elseif ($scoringType == PromisApi::SCORING_TYPE_NONE) {
                    $engineType = PromisApi::ENGINE_SEQUENCE;
                }

                $this->oid = $promisKey;
                $this->engineType = $engineType;
                $this->finalTscoreFieldIdentifier = $fieldInfo[PromisApi::FINAL_TSCORE_FIELD_IDENTIFIER];
                $this->finalStdErrorFieldIdentifier = $fieldInfo[PromisApi::FINAL_STDERROR_FIELD_IDENTIFIER];
                $this->fieldPrefix = $fieldInfo[PromisApi::FIELD_PREFIX];
            }
        }
    }

    /**
     * @param $instrument
     * @param array $batteryInstruments
     * @return object|null
     */
    public static function triggerForBattery($instrument, $batteryInstruments)
    {
        $currentInstrument = $batteryInstruments[$instrument];
        $batteryPosition = $currentInstrument['batteryPosition'];
        $instrumentPosition = $currentInstrument['instrumentPosition'];
        foreach ($batteryInstruments as $name => $batteryInstrument) {
            if ($batteryInstrument['batteryPosition'] != $batteryPosition) {
                continue;
            }
            if ($batteryInstrument['instrumentPosition'] == $instrumentPosition + 1) {
                $trigger = new \stdClass();
                $trigger->uuid = MyCap::guid();
                $trigger->immutable = true;
                $trigger->action = self::ACTION_AUTO_CONTINUE;
                $trigger->condition = self::CONDITION_COMPLETED;
                $trigger->target = $name;
                return $trigger;
            }
        }
        return null;
    }

    /**
     * Mike Bass occassionaly introduces new engine types to the IRTFramework. These engines may be implemented in the
     * https://redcap-cats.org/promis_api/ that REDCap uses but not yet available in the binary the MyCap is
     * provided. Ensure unsupported PROMIS measures do not get sent to MyCap. Current unsupported engines:
     *   AlcoholScreeningCATEngine
     *   ScreeningCATEngine
     *   ScreeningCATEngine2
     *   SequenceBranchEngine
     *
     * @return array Of PROMIS measure identifers that are not supported by MyCap
     */
    public static function unsupportedPromisInstruments()
    {
        return [
            'A3A571A6-1E62-4768-B8C1-AB99CDBF867F', // PROMIS Bank v1.0 Alcohol: Negative Consequences
            'F6C7CFF1-76B1-4CB6-8D11-8CAEF848B11A', // PROMIS Bank v1.0 Alcohol: Positive Consequences
            'B996AB09-5B25-445E-BA81-96815A64D3BA', // PROMIS Bank v1.0-Phys Func Samples w Mobility Aid
            '88BC918C-CC0A-4D5A-A68D-5DD181DA8CEC', // PROMIS Bank v1.0-Appeal of Sub Use (Past 3 mo)
            '3E1BA4CC-B75B-4EC5-BF44-6D8B7C0A8BA5', // PROMIS Bank v1.0-Appeal of Sub Use (Past 30 days)
            'ECB289ED-6E48-449B-8853-4FF564ECB2B0', // PROMIS Bank v1.0-Prescription Pain Med Misuse
            'ADE20290-415F-4DEC-9E3B-F24F62303FF9', // PROMIS Bank v1.0-Sev of Sub Use (Past 30 days)
            '72CDDE0E-DADD-416B-A71F-D79E80242071', // PROMIS Bank v1.0-Severity of Sub Use (Past 3 mo)
            '4D1E246A-28F6-4101-BFBA-DEDB6D1130EE', // PROMIS Scale v1.0 - GI Belly Pain 5a
            'DE3279E9-ED2C-45FA-9EF0-1BC5305A2245', // PROMIS Scale v1.0 - GI Constipation 9a
            '097EF029-9A5D-4A60-9E6B-06691EC01BE5', // PROMIS Scale v1.0 - GI Diarrhea 6a
            '33CF9F89-F250-401A-9B95-03F87F7163F8', // PROMIS Scale v1.0 - GI Gas and Bloating 13a
            '4D8E5124-1AE3-4036-A9B3-C08A06E65AA0', // PROMIS Scale v1.0 - GI Nausea and Vomiting 4a
            '7083E333-30F7-4EDF-B1F0-A984405E0029', // PROMIS Scale v1.0 - GI Reflux 13a
            '93882B21-8336-42E3-A1D8-4EC6FD3ABE69', // PROMIS SF v1.0-Appeal of Sub Use (Past 3 mo) 7a
            '69C03146-B96D-4B1A-B2A8-170EF8AA231F', // PROMIS SF v1.0-Appeal of Sub Use (Past 30 days) 7a
            'B4B25A11-994D-4709-A3B5-A76CBD3B0902', // PROMIS SF v1.0-Prescription Pain Med Misuse 7a
            'AAA49CF5-1E63-472A-B65D-5C00D4226723', // PROMIS SF v1.0-Sev of Sub Use (Past 30 days) 7a
            '04D2FBCC-884C-4C03-BBD1-CD3F4B38B43F', // PROMIS SF v1.0-Severity of Sub Use (Past 3 mo) 7a
        ];
    }

    /**
     * @param $instrument
     * @param array $batteryInstruments
     * @return object|null
     */
    public static function triggerForBatterySeries($instrument, $batteryInstruments)
    {
        $currentInstrument = $batteryInstruments[$instrument];
        $batteryPosition = $currentInstrument['batteryPosition'];
        $instrumentPosition = $currentInstrument['instrumentPosition'];

        foreach ($batteryInstruments as $name => $batteryInstrument) {
            if ($batteryInstrument['instrumentPosition'] > $instrumentPosition) {
                $trigger = new \stdClass();
                $trigger->uuid = MyCap::guid();
                $trigger->immutable = true;
                $trigger->action = self::ACTION_AUTO_CONTINUE;
                $trigger->condition = self::CONDITION_COMPLETED;
                $trigger->target = $name;
                return $trigger;
            }
        }
        return null;
    }
}
