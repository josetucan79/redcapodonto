<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class Audio (Sustained Phonation)
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/audioTaskWithIdentifier:intendedUseDescription:speechInstruction:shortSpeechInstruction:duration:recordingSettings:checkAudioLevel:options:
 */
class Audio
{
    // See ORKAudioStep->validateParemeters()
    const ORKAUDIOTASKMINIMUMDURATION = 5.0;

    /** @var string (optional) */
    public $intendedUseDescription = '';

    /** @var string (optional) */
    public $speechInstruction = '';

    /** @var string (optional) */
    public $shortSpeechInstruction = '';

    /** @var double */
    public $duration = 20;

    /** @var bool */
    public $checkAudioLevel = false;

    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'countdown',
                            'field_label' => 'Audio Countdown',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_AUD_COUNTDOWN);
        $fieldArr[] = array('field_name' => 'main',
                            'field_label' => 'Audio Main',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_AUD_MAIN);
        return $fieldArr;
    }

    /**
     * Assign array values to class variables to save extended config variables
     *
     * @return void
     */
    public function buildExtendedConfig($data = array())
    {
        if (isset($data['intendedUseDescription'])) {
            $this->intendedUseDescription = $data['intendedUseDescription'];
        }
        if (isset($data['speechInstruction'])) {
            $this->speechInstruction = $data['speechInstruction'];
        }
        if (isset($data['shortSpeechInstruction'])) {
            $this->shortSpeechInstruction = $data['shortSpeechInstruction'];
        }
        if (isset($data['duration'])) {
            $this->duration = doubleval($data['duration']);
        }
        if (isset($data['checkAudioLevel'])) {
            $this->checkAudioLevel = boolval($data['checkAudioLevel']);
        }
    }

    /**
     * Validate extended config variables and returns list of errors
     *
     * @return array
     */
    public static function validateExtendedConfigParams($data = array())
    {
        global $lang;
        $errors = array();

        if (!is_numeric($data['duration'])) {
            $errors[] = $lang['mycap_mobile_app_207']." ".$lang['data_import_tool_85'];
        }
        if ($data['checkAudioLevel'] !== 0 &&
            $data['checkAudioLevel'] !== 1 &&
            $data['checkAudioLevel'] !== '0' &&
            $data['checkAudioLevel'] !== '1' &&
            $data['checkAudioLevel'] !== false &&
            $data['checkAudioLevel'] !== true
        ) {
            $errors[] = $lang['mycap_mobile_app_342'];
        }

        $duration = doubleval($data['duration']);

        if ($duration < self::ORKAUDIOTASKMINIMUMDURATION) {
            $errors[] = $lang['mycap_mobile_app_207']." ".$lang['mycap_mobile_app_208']." ". self::ORKAUDIOTASKMINIMUMDURATION . " ".$lang['control_center_4469'];
        }

        return $errors;
    }
}
