<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

/**
 * Enumerates annotations used by MyCap. Use to avoid spelling mistakes.
 * MyCap uses REDCap field annotations extensively to indicate which
 * field is used for what purpose.
 *
 * @package Vanderbilt\REDCap\Classes\MyCap
 */
class Annotation
{
    // MARK: Participant

    /** Record ID, uniquely identifies the participant */
    const PARTICIPANT_ID = "@MC-PARTICIPANT-ID";
    /** First name of participant */
    const PARTICIPANT_FIRSTNAME = "@MC-PARTICIPANT-FIRSTNAME";
    /** Last name of participant */
    const PARTICIPANT_LASTNAME = "@MC-PARTICIPANT-LASTNAME";
    /** Date of important event. See Scheduling */
    const PARTICIPANT_ZERODATE = "@MC-PARTICIPANT-ZERODATE";
    /** Date participant installed the app */
    const PARTICIPANT_JOINDATE = "@MC-PARTICIPANT-JOINDATE";
    /** Date (UTC Format) participant installed the app */
    const PARTICIPANT_JOINDATE_UTC = "@MC-PARTICIPANT-JOINDATE-UTC";
    /** Participant's timezone */
    const PARTICIPANT_TIMEZONE = "@MC-PARTICIPANT-TIMEZONE";
    /** Code that identifies the participant. Used in QR code. */
    const PARTICIPANT_CODE = "@MC-PARTICIPANT-CODE";
    /** Stores push notification identifiers */
    const PARTICIPANT_PUSHNOTIFICATIONIDS = "@MC-PARTICIPANT-PUSHNOTIFICATIONIDS";

    /**
     * The first REDCap instrument must have fields with these annotations
     * @var array
     */
    public static $requiredParticipantAnnotations = [
        Annotation::PARTICIPANT_ID,
        Annotation::PARTICIPANT_ZERODATE,
        Annotation::PARTICIPANT_JOINDATE,
        Annotation::PARTICIPANT_PUSHNOTIFICATIONIDS
    ];

    // MARK: Result

    /** ID of the task result */
    const TASK_UUID = "@MC-TASK-UUID";
    /** Date user started task */
    const TASK_STARTDATE = "@MC-TASK-STARTDATE";
    /** Date user completed task */
    const TASK_ENDDATE = "@MC-TASK-ENDDATE";
    /** Date task was scheduled, if appropriate. .OneTime and .Infinite tasks will not have a schedule date */
    const TASK_SCHEDULEDATE = "@MC-TASK-SCHEDULEDATE";
    /** DEPRECATED!!! Was this task result deleted by user? Use TASK_STATUS now */
    const TASK_ISDELETED = "@MC-TASK-ISDELETED";
    /** 0=deleted, 1=completed, 2=incomplete */
    const TASK_STATUS = "@MC-TASK-STATUS";
    /** Any available supplemental data. JSON format. */
    const TASK_SUPPLEMENTALDATA = "@MC-TASK-SUPPLEMENTALDATA";
    /** Raw data from ResearchKit */
    const TASK_SERIALIZEDRESULT = "@MC-TASK-SERIALIZEDRESULT";

    // MARK: Active task

    /** JSON for the amsler grid active task */
    const TASK_ACTIVE_AMS_LEFT_IMAGE = "@MC-TASK-ACTIVE-AMS-LEFT-IMAGE";
    const TASK_ACTIVE_AMS_LEFT_JSON = "@MC-TASK-ACTIVE-AMS-LEFT-JSON";
    const TASK_ACTIVE_AMS_RIGHT_IMAGE = "@MC-TASK-ACTIVE-AMS-RIGHT-IMAGE";
    const TASK_ACTIVE_AMS_RIGHT_JSON = "@MC-TASK-ACTIVE-AMS-RIGHT-JSON";
    /** Audio recorded during the countdown phase of the audio active task */
    const TASK_ACTIVE_AUD_COUNTDOWN = "@MC-TASK-ACTIVE-AUD-COUNTDOWN";
    /** Audio recorded during the main phase of the audio active task */
    const TASK_ACTIVE_AUD_MAIN = "@MC-TASK-ACTIVE-AUD-MAIN";
    /** JSON data for the dBHL tone audiometry active task */
    const TASK_ACTIVE_DBH = "@MC-TASK-ACTIVE-DBH";
    /** Pedometer JSON recorded during the walking phase of the fitness active task */
    const TASK_ACTIVE_FIT_WALK_PEDOMETER = "@MC-TASK-ACTIVE-FIT-WALK-PEDOMETER";
    /** Accelerometer JSON recorded during the walking phase of the fitness active task */
    const TASK_ACTIVE_FIT_WALK_ACCELEROMETER = "@MC-TASK-ACTIVE-FIT-WALK-ACCELEROMETER";
    /** Device motion JSON recorded during the walking phase of the fitness active task */
    const TASK_ACTIVE_FIT_WALK_DEVICEMOTION = "@MC-TASK-ACTIVE-FIT-WALK-DEVICEMOTION";
    /** Location JSON recorded during the walking phase of the fitness active task */
    const TASK_ACTIVE_FIT_WALK_LOCATION = "@MC-TASK-ACTIVE-FIT-WALK-LOCATION";
    /** Accelerometer JSON recorded during the resting phase of the fitness active task */
    const TASK_ACTIVE_FIT_REST_ACCELEROMETER = "@MC-TASK-ACTIVE-FIT-REST-ACCELEROMETER";
    /** Device motion JSON recorded during the resting phase of the fitness active task */
    const TASK_ACTIVE_FIT_REST_DEVICEMOTION = "@MC-TASK-ACTIVE-FIT-REST-DEVICEMOTION";
    /** JSON for the hole peg active task */
    const TASK_ACTIVE_HOL_DOMINANT_PLACE = "@MC-TASK-ACTIVE-HOL-DOMINANT-PLACE";
    /** JSON for the hole peg active task */
    const TASK_ACTIVE_HOL_DOMINANT_REMOVE = "@MC-TASK-ACTIVE-HOL-DOMINANT-REMOVE";
    /** JSON for the hole peg active task */
    const TASK_ACTIVE_HOL_NONDOMINANT_PLACE = "@MC-TASK-ACTIVE-HOL-NONDOMINANT-PLACE";
    /** JSON for the hole peg active task */
    const TASK_ACTIVE_HOL_NONDOMINANT_REMOVE = "@MC-TASK-ACTIVE-HOL-NONDOMINANT-REMOVE";
    /** JSON data recorded during the range of motion active task */
    const TASK_ACTIVE_RMO_FLEXION = "@MC-TASK-ACTIVE-RMO-FLEXION";
    /** JSON data recorded during the range of motion active task */
    const TASK_ACTIVE_RMO_EXTENSION = "@MC-TASK-ACTIVE-RMO-EXTENSION";
    /** JSON data recorded during the range of motion active task */
    const TASK_ACTIVE_RMO_DEVICEMOTION = "@MC-TASK-ACTIVE-RMO-DEVICEMOTION";
    /** JSON data for the PSAT active task */
    const TASK_ACTIVE_PSA = "@MC-TASK-ACTIVE-PSA";
    /** JSON data recorded during the reaction time active task */
    const TASK_ACTIVE_REA = "@MC-TASK-ACTIVE-REA";
    /** Audio recording file */
    const TASK_ACTIVE_REC_AUD = "@MC-TASK-ACTIVE-REC-AUD";
    /** Photo file for the selfie capture active task */
    const TASK_ACTIVE_SEL = "@MC-TASK-ACTIVE-SEL";
    /** Acceleromoter JSON recorded during the outbound walking phase of the short walk active task */
    const TASK_ACTIVE_SHO_OUTBOUND_ACCELEROMETER = "@MC-TASK-ACTIVE-SHO-OUTBOUND-ACCELEROMETER";
    /** Device motion JSON recorded during the outbound walking phase of the short walk active task */
    const TASK_ACTIVE_SHO_OUTBOUND_DEVICEMOTION = "@MC-TASK-ACTIVE-SHO-OUTBOUND-DEVICEMOTION";
    /** Acceleromoter JSON recorded during the return walking phase of the short walk active task */
    const TASK_ACTIVE_SHO_RETURN_ACCELEROMETER = "@MC-TASK-ACTIVE-SHO-RETURN-ACCELEROMETER";
    /** Device motion JSON recorded during the return walking phase of the short walk active task */
    const TASK_ACTIVE_SHO_RETURN_DEVICEMOTION = "@MC-TASK-ACTIVE-SHO-RETURN-DEVICEMOTION";
    /** Acceleromoter JSON recorded during the resting phase of the short walk active task */
    const TASK_ACTIVE_SHO_REST_ACCELEROMETER = "@MC-TASK-ACTIVE-SHO-REST-ACCELEROMETER";
    /** Device motion JSON recorded during the resting phase of the short walk active task */
    const TASK_ACTIVE_SHO_REST_DEVICEMOTION = "@MC-TASK-ACTIVE-SHO-REST-DEVICEMOTION";
    /** JSON data for the speech in noise active task */
    const TASK_ACTIVE_SIN = "@MC-TASK-ACTIVE-SIN";
    /** JSON data for the spatial memory active task */
    const TASK_ACTIVE_SPA = "@MC-TASK-ACTIVE-SPA";
    /** Recorded audio file for the speech recognition active task */
    const TASK_ACTIVE_SPR_AUDIO = "@MC-TASK-ACTIVE-SPR-AUDIO";
    /** Transcription text for for speech recognition */
    const TASK_ACTIVE_SPR_TRANSCRIPTION = "@MC-TASK-ACTIVE-SPR-TRANSCRIPTION";
    /** Edited transcription text for speech recognition */
    const TASK_ACTIVE_SPR_EDITED_TRANSCRIPTION = "@MC-TASK-ACTIVE-SPR-EDITED-TRANSCRIPTION";
    /** JSON for the stroop active task */
    const TASK_ACTIVE_STR = "@MC-TASK-ACTIVE-STR";
    /** Timed walk active task */
    const TASK_ACTIVE_TIM_TRIAL1 = "@MC-TASK-ACTIVE-TIM_TRIAL1";
    const TASK_ACTIVE_TIM_TRIAL2 = "@MC-TASK-ACTIVE-TIM_TRIAL2";
    const TASK_ACTIVE_TIM_TURNAROUND = "@MC-TASK-ACTIVE-TIM_TURNAROUND";
    /** JSON data for the tone audiometry active task */
    const TASK_ACTIVE_TON = "@MC-TASK-ACTIVE-TON";
    /** JSON data for the tower of hanoi active task */
    const TASK_ACTIVE_TOW = "@MC-TASK-ACTIVE-TOW";
    /** JSON data for the trail making active task */
    const TASK_ACTIVE_TRA = "@MC-TASK-ACTIVE-TRA";
    /** JSON data for the two finger tapping active task - left hand */
    const TASK_ACTIVE_TWO_LEFT = "@MC-TASK-ACTIVE-TWO-LEFT";
    /** Accelerometer file data for the two finger tapping active task - left hand */
    const TASK_ACTIVE_TWO_LEFT_ACCELEROMETER = "@MC-TASK-ACTIVE-TWO-LEFT-ACCELEROMETER";
    /** JSON data for the two finger tapping active task - right hand */
    const TASK_ACTIVE_TWO_RIGHT = "@MC-TASK-ACTIVE-TWO-RIGHT";
    /** Accelerometer file data for the two finger tapping active task - right hand */
    const TASK_ACTIVE_TWO_RIGHT_ACCELEROMETER = "@MC-TASK-ACTIVE-TWO-RIGHT-ACCELEROMETER";
    /** Audio recorded during main phase of the VUMC audio recording task */
    const TASK_ACTIVE_VAU_MAIN = "@MC-TASK-ACTIVE-VAU-MAIN";
    /** Vumc Contraction Timer duration */
    const TASK_ACTIVE_VCT_DURATION = "@MC-TASK-ACTIVE-VCT-DURATION";
    /** Vumc Contraction Timer intensity */
    const TASK_ACTIVE_VCT_INTENSITY = "@MC-TASK-ACTIVE-VCT-INTENSITY";

    // MARK: FIELD OPTIONS

    /** ResearchKit Image Capture */
    const FIELD_FILE_IMAGECAPTURE = "@MC-FIELD-FILE-IMAGECAPTURE";
    /** Describes how the ResearchKit Video Capture field should behave */
    const FIELD_FILE_VIDEOCAPTURE = "@MC-FIELD-FILE-VIDEOCAPTURE=[DURATION]:[AUDIO_MUTE(YES/NO)]:[FLASH_MODE(ON/OFF)]:[DEVICE_POSITION(BACK/FRONT)]";
    /** Same as REDCap's @HIDDEN annotation. Used when a field should be visible in REDCap but not MyCap */
    const FIELD_HIDDEN = "@MC-FIELD-HIDDEN";
    /**
     * Slider/VAS. Example: @MC-FIELD-SLIDER-BASIC=0:10:1 is scale from 0 to 10 step by increments of 1.
     * Note that REDCap slider does not accept values < 0 and > 100.
     */
    const FIELD_SLIDER_BASIC = "@MC-FIELD-SLIDER-BASIC=[MIN]:[MAX]:[STEP]";
    /**
     * Slider/VAS. Example @MC-FIELD-SLIDER-CONTINUOUS=0:100:0 is scale from 0 to 100 with no fractions.
     * A REDCap slider will default to this if no annotation is specified. Note that REDCap slider does not
     * accept values < 0 and > 100.
     */
    const FIELD_SLIDER_CONTINUOUS = "@MC-FIELD-SLIDER-CONTINUOUS=[MIN]:[MAX]:[MAX_FRACTIONAL_DIGITS]";
    /** Uses the device's camera to decode a barcode of varying formats. */
    const FIELD_TEXT_BARCODE = "@MC-FIELD-TEXT-BARCODE";
    const FIELD_TEXT_BARCODE_SCANDIT = "@MC-FIELD-TEXT-BARCODE-SCANDIT";

    /**
     * Does the annotation exist within the string? You cannot call parent class' Enumeration::exists($t) because we
     * are dealing with regular expressions.
     *
     * @param string $type Needle
     * @param string $str Haystack
     * @return bool
     * @throws \Exception
     */
    public static function matchExists($type, $str)
    {
        $matches = self::matches(
            $type,
            $str
        );
        return (count($matches) > 0) ? true : false;
    }

    /**
     * Return matches for
     *
     * @param string $type Needle. E.g. Annotation.TASK_UUID
     * @param string $str Haystack
     * @return mixed
     * @throws \Exception
     */
    public static function matches($type, $str)
    {
        if (self::isInvalid($type)) {
            throw new \Exception("$type is not a valid Annotation. See MyCap\\Annotation");
        }
        preg_match(
            self::pattern($type),
            $str,
            $matches
        );
        return $matches;
    }

    /**
     * Is the given string an invalid enumerated item? I.e., does not exist
     *
     * @param $t
     * @return bool
     */
    final public static function isInvalid($t, $a = '')
    {
        return !self::isValid($t, $a);
    }

    /**
     * Is the given string a valid enumerated item? I.e., does exist
     *
     * @param string $t
     * @return bool
     */
    final public static function isValid($t, $a = '')
    {
        if (is_null($t)) {
            return false;
        }
        if ($a == 'here') {
            echo $t;
            print_array(self::values());
        }

        return (in_array(
            $t,
            self::values()
        ));
    }

    /**
     * Returns all enumerated values. E.g.
     *   [
     *     "January",
     *     "February",
     *     etc...
     *   ]
     *
     * @return array
     */
    final public static function values()
    {
        return array_values(self::all());
    }

    /**
     * Returns all enumerated constants as a key=>value array. E.g.
     *   [
     *     [JAN] => "January",
     *     [FEB] => "February",
     *     etc...
     *   ]
     *
     * @return array
     */
    final public static function all()
    {
        $constants = [];
        try {
            // static::class is the same as get_called_class()
            $class = new \ReflectionClass(static::class);
            $constants = $class->getConstants();
        } catch (\ReflectionException $e) {
            error_log($e->getMessage());
        }

        return $constants;
    }

    /**
     * Get the regex pattern for an annotation
     *
     * @param $type
     * @return string
     */
    public static function pattern($type)
    {

        switch ($type) {
            case 'ANY':
                // Any annotation beginning with @MC-, colon(:) and equal(=) OK
                $pattern = '~@MC-[\w-:=]+~';
                break;
            case self::FIELD_SLIDER_BASIC:
            case self::FIELD_SLIDER_CONTINUOUS:
                $pattern = '/@MC-FIELD-SLIDER-(BASIC|CONTINUOUS)=(-?\d+):(-?\d+):(\d+)/';
                break;
            case self::FIELD_FILE_VIDEOCAPTURE:
                $pattern = '/@MC-FIELD-FILE-VIDEOCAPTURE[=]?(\d*[.]?\d*)?:?(\w*):?(\w*):?(\w*)/';
                break;
            default:
                // https://teamtreehouse.com/community/get-exact-match-of-a-word-using-regex
                $pattern = '/(^|\s)' . $type . '($|\s)/';
        }

        return $pattern;
    }
}
