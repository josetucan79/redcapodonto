<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;
use Vanderbilt\REDCap\Classes\MyCap\Locale;

/**
 * Class SpeechRecognition
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/speechRecognitionTaskWithIdentifier:intendedUseDescription:speechRecognizerLocale:speechRecognitionImage:speechRecognitionText:shouldHideTranscript:allowsEdittingTranscript:options:
 */
class SpeechRecognition
{
    /** @var string (optional) */
    public $intendedUseDescription = '';

    /** @var string */
    public $speechRecognizerLocale = Locale::ENGLISH_US;

    /** @var string "Foo.png" */
    public $speechRecognitionImage = '';

    /** @var string A quick brown fox jumps over the lazy dog. */
    public $speechRecognitionText = '';

    /** @var bool */
    public $shouldHideTranscript = false;

    /** @var bool */
    public $allowsEdittingTranscript = true;

    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'audio',
                            'field_label' => 'Recorded audio',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_SPR_AUDIO);
        $fieldArr[] = array('field_name' => 'transcription',
                            'field_label' => 'Transcription',
                            'field_type' => 'textarea',
                            'field_annotation' => Annotation::TASK_ACTIVE_SPR_TRANSCRIPTION);
        $fieldArr[] = array('field_name' => 'edited',
                            'field_label' => 'Edited Transcription',
                            'field_type' => 'textarea',
                            'field_annotation' => Annotation::TASK_ACTIVE_SPR_EDITED_TRANSCRIPTION);
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
        if (isset($data['speechRecognizerLocale'])) {
            $this->speechRecognizerLocale = $data['speechRecognizerLocale'];
        }
        if (isset($data['speechRecognitionText'])) {
            $this->speechRecognitionText = $data['speechRecognitionText'];
        }
        if (isset($data['shouldHideTranscript'])) {
            $this->shouldHideTranscript = boolval($data['shouldHideTranscript']);
        }
        if (isset($data['allowsEdittingTranscript'])) {
            $this->allowsEdittingTranscript = boolval($data['allowsEdittingTranscript']);
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

        if (!in_array($data['speechRecognizerLocale'], array_keys(Locale::getLocaleList()))) {
            $errors[] = $lang['mycap_mobile_app_343'];
        }

        if (!in_array(intval($data['shouldHideTranscript']), [0,1])) {
            $errors[] = $lang['mycap_mobile_app_344'];
        }

        if (!in_array(intval($data['allowsEdittingTranscript']), [0,1])) {
            $errors[] = $lang['mycap_mobile_app_345'];
        }
        return $errors;
    }
}
