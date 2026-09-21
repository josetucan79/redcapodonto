<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use RCView;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\AmslerGrid;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\ArrangingPicturesSpanish;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\Audio;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\AudioRecording;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\BlockRotation;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\FitnessCheck;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\FNAMELearningSpanish;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\FnameTestSpanish;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\HolePeg;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\LettersAndNumbers;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\NumberMatchSpanish;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\Promis;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\PSAT;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\PuzzleCompletion;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\RangeOfMotion;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\ReactionTime;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\SelfieCapture;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\SequencesSpanish;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\ShapeColorSortingDeprecated;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\ShortWalk;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\SpatialSpanMemory;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\SpeechRecognition;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\Stroop;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\TimedWalk;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\ToneAudiometry;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\TowerOfHanoi;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\TrailMaking;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\TwoFingerTappingInterval;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\Arrows;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\FNAMELearning;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\Spelling;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\Sequences;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\FnameTest;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\NumberMatch;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\VarietyTest;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\WordMeaningForm1;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\WordMeaningForm1Spanish;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\WordMeaningForm2;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\ShapeColorSorting;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\ArrangingPictures;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\WordProblems;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\ShapeColorSortingSpanish;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\ArrowsSpanish;
use Vanderbilt\REDCap\Classes\ProjectDesigner;
use Project;

class ActiveTask
{
    const AMSLERGRID = '.AmslerGrid';
    const AUDIO = '.Audio';
    const DBHLTONEAUDIOMETRY = '.DbhlToneAudiometry';
    const FITNESSCHECK = '.FitnessCheck';
    /** Custom task. Display all fields as a form */
    const FORM = '.Form';
    const HOLEPEG = '.HolePeg';
    /** PROMIS computer adaptive test from REDCap Shared Library */
    const PROMIS = '.PROMIS';
    const PSAT = '.PSAT';
    /** Custom task. Display all fields as individual qustions */
    const QUESTIONNAIRE = '.Questionnaire';
    const RANGEOFMOTION = '.RangeOfMotion';
    const REACTIONTIME = '.ReactionTime';
    const SELFIECAPTURE = '.SelfieCapture';
    const AUDIORECORDING = '.AudioRecording';
    const SHORTWALK = '.ShortWalk';
    const SPATIALSPANMEMORY = '.SpatialSpanMemory';
    const SPEECHINNOISE = '.SpeechInNoise';
    const SPEECHRECOGNITION = '.SpeechRecognition';
    const STROOP = '.Stroop';
    const TIMEDWALK = '.TimedWalk';
    const TONEAUDIOMETRY = '.ToneAudiometry';
    const TOWEROFHANOI = '.TowerOfHanoi';
    const TRAILMAKING = '.TrailMaking';
    const TWOFINGERTAPPINGINTERVAL = '.TwoFingerTappingInterval';
    /** Custom active task for Alex Gelbard */
    const VUMCAUDIORECORDING = '.VumcAudioRecording';
    const VUMCCONTRACTIONTIMER = '.VumcContractionTimer';
    const ARROWS = 'MtbArrows';
    const SPELLING = 'MtbSpelling';
    const SEQUENCES = 'MtbSequences';
    const FNAMETEST = 'MtbFNAMETest';
    const NUMBERMATCH = 'MtbNumberMatch';
    const FNAMELEARNING = 'MtbFNAMELearning';
    const WORDMEANING1 = 'MtbWordMeaning1';
    const WORDMEANING2 = 'MtbWordMeaning2';
    const SHAPECOLORSORTING = 'MtbShapeColorSorting';
    const ARRANGINGPICTURES = 'MtbArrangingPictures';
    const BLOCKROTATION = 'MtbBlockRotation';
    const WORDPROBLEMS = 'MtbWordProblems';
    const PUZZLECOMPLETION = 'MtbPuzzleCompletion';
    const LETTERSANDNUMBERS = 'MtbLettersAndNumbers';
    const VARIETYTEST = 'MtbVarietyTest';
    const SHAPECOLORSORTING_DEPRECATED = 'MtbShapeColorSortingDeprecated';
    const SHAPECOLORSORTING_SPANISH = 'MtbShapeColorSortingSpanish';
    const ARROWS_SPANISH = 'MtbArrowsSpanish';
    const FNAMELEARNING_SPANISH = 'MtbFNAMELearningSpanish';
    const FNAMETEST_SPANISH = 'MtbFNAMETestSpanish';
    const SEQUENCES_SPANISH = 'MtbSequencesSpanish';
    const NUMBERMATCH_SPANISH = 'MtbNumberMatchSpanish';
    const ARRANGINGPICTURES_SPANISH = 'MtbArrangingPicturesSpanish';
    const WORDMEANING1_SPANISH = 'MtbWordMeaning1Spanish';


    /**
     * Get a list of translatable items for a given task
     * @param string $task_format 
     * @return array
     */
    public static function getActiveTaskTranslatableItems($task_format) {
        // Default items apply to most tasks
        $items = [
            "task_title" => [
                "prompt" => RCView::tt("mycap_mobile_app_108"),
                "mode" => "text",
            ],
            "extendedConfig_intendedUseDescription" => [
                "prompt" => RCView::tt("mycap_mobile_app_193"),
                "mode" => "textarea",
            ]
        ];
        // Add task-specific items
        switch($task_format) {
            case ActiveTask::QUESTIONNAIRE:
            case ActiveTask::FORM:
                unset($items["extendedConfig_intendedUseDescription"]);
                $items["instruction_step_title"] = [
                    "prompt" => RCView::tt("multilang_728"),
                    "mode" => "text",
                ];
                $items["instruction_step_content"] = [
                    "prompt" => RCView::tt("multilang_729"),
                    "mode" => "textarea",
                ];
                $items["completion_step_title"] = [
                    "prompt" => RCView::tt("multilang_730"),
                    "mode" => "text",
                ];
                $items["completion_step_content"] = [
                    "prompt" => RCView::tt("multilang_731"),
                    "mode" => "textarea",
                ];
                break;
            case ActiveTask::RANGEOFMOTION:
                // None
                break;
            case ActiveTask::SHORTWALK:
                // None
                break;
            case ActiveTask::TWOFINGERTAPPINGINTERVAL:
                // None
                break;
            case ActiveTask::FITNESSCHECK:
                // None
                break;
            case ActiveTask::TIMEDWALK:
                // None
                break;
            case ActiveTask::SPATIALSPANMEMORY:
                // None
                break;
            case ActiveTask::STROOP:
                // None
                break;
            case ActiveTask::TRAILMAKING:
                $items["extendedConfig_trailMakingInstruction"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_241"),
                    "mode" => "textarea",
                ];
                break;
            case ActiveTask::PSAT:
                // None
                break;
            case ActiveTask::TOWEROFHANOI:
                // None
                break;
            case ActiveTask::REACTIONTIME:
                // None
                break;
            case ActiveTask::AUDIO:
                $items["extendedConfig_speechInstruction"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_283"),
                    "mode" => "textarea",
                ];
                $items["extendedConfig_shortSpeechInstruction"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_285"),
                    "mode" => "textarea",
                ];
                break;
            case ActiveTask::AUDIORECORDING:
                unset($items["extendedConfig_intendedUseDescription"]);
                $items["extendedConfig_speechInstruction"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_319"),
                    "mode" => "text",
                ];
                $items["extendedConfig_infoInstructions"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_320"),
                    "mode" => "textarea",
                ];
                $items["extendedConfig_captureTitle"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_321"),
                    "mode" => "text",
                ];
                $items["extendedConfig_captureInstructions"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_323"),
                    "mode" => "textarea",
                ];
                break;
            case ActiveTask::SELFIECAPTURE:
                unset($items["extendedConfig_intendedUseDescription"]);
                $items["extendedConfig_infoTitle"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_319"),
                    "mode" => "text",
                ];
                $items["extendedConfig_infoInstructions"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_320"),
                    "mode" => "textarea",
                ];
                $items["extendedConfig_captureTitle"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_321"),
                    "mode" => "text",
                ];
                $items["extendedConfig_captureInstructions"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_323"),
                    "mode" => "textarea",
                ];
                break;
            case ActiveTask::SPEECHRECOGNITION:
                // This task is not implemented yet?
                $localList = \Vanderbilt\REDCap\Classes\MyCap\Locale::getLocaleList();
                $items["extendedConfig_speechRecognizerLocale"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_290"),
                    "mode" => "dropdown",
                    "enum" => $localList,
                ];
                $items["extendedConfig_speechRecognitionText"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_292"),  
                ];
                break;
            case ActiveTask::TONEAUDIOMETRY:
                $items["extendedConfig_speechInstruction"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_298"),
                    "mode" => "textarea",
                ];
                $items["extendedConfig_shortSpeechInstruction"] = [
                    "prompt" => RCView::tt("mycap_mobile_app_300"),
                    "mode" => "textarea",
                ];
                break;
            case ActiveTask::HOLEPEG:
                // None
                break;
            case ActiveTask::AMSLERGRID:
                // None
                break;
            case ActiveTask::PROMIS:
                // Seems to be not implemented yet?
                break;

            // Mobile Toolbox
            case ActiveTask::ARRANGINGPICTURES:
            case ActiveTask::ARROWS:
            case ActiveTask::FNAMELEARNING:
            case ActiveTask::FNAMETEST:
            case ActiveTask::NUMBERMATCH:
            case ActiveTask::SEQUENCES:
            case ActiveTask::SHAPECOLORSORTING:
            case ActiveTask::SHAPECOLORSORTING_DEPRECATED:
            case ActiveTask::SPELLING:
            case ActiveTask::WORDMEANING1:
            case ActiveTask::WORDMEANING2:
            case ActiveTask::BLOCKROTATION:
            case ActiveTask::WORDPROBLEMS:
            case ActiveTask::PUZZLECOMPLETION:
            case ActiveTask::LETTERSANDNUMBERS:
            case ActiveTask::VARIETYTEST:
            case ActiveTask::SHAPECOLORSORTING_SPANISH:
            case ActiveTask::ARROWS_SPANISH:
            case ActiveTask::FNAMELEARNING_SPANISH:
            case ActiveTask::FNAMETEST_SPANISH:
            case ActiveTask::SEQUENCES_SPANISH:
            case ActiveTask::NUMBERMATCH_SPANISH:
            case ActiveTask::ARRANGINGPICTURES_SPANISH:
            case ActiveTask::WORDMEANING1_SPANISH:
                unset($items["extendedConfig_intendedUseDescription"]);
                break;
        }
        return $items;
    }

    /**
     * Get a human readable name for the given task format
     * @param string $task_format 
     * @return array
     */
    public static function getTaskType($task_format) {
        switch($task_format) {
            case ActiveTask::QUESTIONNAIRE: return RCView::tt("multilang_741"); // Questionnaire
            case ActiveTask::FORM: return RCView::tt("global_54"); // Form
        }
        return static::toString($task_format);
    }


    /**
     * Check if task is active task or not based on task format
     *
     * @param string $task_format
     * @return boolean
     */
    public static function isActiveTask($task_format) {
        return !in_array($task_format, array(self::FORM, self::QUESTIONNAIRE, self::PROMIS));
    }

    /**
     * Check if task is MTB active task or not based on task format
     *
     * @param string $task_format
     * @return boolean
     */
    public static function isMTBActiveTask($task_format) {
        return in_array($task_format, array(self::ARROWS, self::FNAMELEARNING, self::SPELLING, self::SEQUENCES, self::FNAMETEST,
                                            self::NUMBERMATCH, self::WORDMEANING1, self::WORDMEANING2, self::SHAPECOLORSORTING, self::ARRANGINGPICTURES,
                                            self::BLOCKROTATION, self::WORDPROBLEMS, self::PUZZLECOMPLETION, self::LETTERSANDNUMBERS, self::VARIETYTEST, self::SHAPECOLORSORTING_DEPRECATED,
                                            self::SHAPECOLORSORTING_SPANISH, self::ARROWS_SPANISH, self::FNAMELEARNING_SPANISH, self::FNAMETEST_SPANISH, self::SEQUENCES_SPANISH,
                                            self::NUMBERMATCH_SPANISH, self::ARRANGINGPICTURES_SPANISH, self::WORDMEANING1_SPANISH));
    }

    /**
     * Return object for active task to process functions based on task format
     *
     * @param string $format
     * @return object
     */
    public static function getActiveTaskObj($format)
    {
        switch ($format) {
            case self::AMSLERGRID:
                $retObj = new AmslerGrid();
                break;

            case self::AUDIO:
                $retObj = new Audio();
                break;

            case self::AUDIORECORDING:
                $retObj = new AudioRecording();
                break;

            case self::FITNESSCHECK:
                $retObj = new FitnessCheck();
                break;

            case self::HOLEPEG:
                $retObj = new HolePeg();
                break;

            case self::PSAT:
                $retObj = new PSAT();
                break;

            case self::RANGEOFMOTION:
                $retObj = new RangeOfMotion();
                break;

            case self::REACTIONTIME:
                $retObj = new ReactionTime();
                break;

            case self::SELFIECAPTURE:
                $retObj = new SelfieCapture();
                break;

            case self::SHORTWALK:
                $retObj = new ShortWalk();
                break;

            case self::SPATIALSPANMEMORY:
                $retObj = new SpatialSpanMemory();
                break;

            case self::SPEECHRECOGNITION:
                $retObj = new SpeechRecognition();
                break;

            case self::STROOP:
                $retObj = new Stroop();
                break;

            case self::TIMEDWALK:
                $retObj = new TimedWalk();
                break;

            case self::TONEAUDIOMETRY:
                $retObj = new ToneAudiometry();
                break;

            case self::TOWEROFHANOI:
                $retObj = new TowerOfHanoi();
                break;

            case self::TRAILMAKING:
                $retObj = new TrailMaking();
                break;

            case self::TWOFINGERTAPPINGINTERVAL:
                $retObj = new TwoFingerTappingInterval();
                break;

            case self::PROMIS:
                $retObj = new Promis();
                break;

            case self::ARROWS:
                $retObj = new Arrows();
                break;

            case self::SPELLING:
                $retObj = new Spelling();
                break;

            case self::SEQUENCES:
                $retObj = new Sequences();
                break;

            case self::FNAMETEST:
                $retObj = new FnameTest();
                break;

            case self::NUMBERMATCH:
                $retObj = new NumberMatch();
                break;

            case self::FNAMELEARNING:
                $retObj = new FNAMELearning();
                break;

            case self::WORDMEANING1:
                $retObj = new WordMeaningForm1();
                break;

            case self::WORDMEANING2:
                $retObj = new WordMeaningForm2();
                break;

            case self::SHAPECOLORSORTING:
                $retObj = new ShapeColorSorting();
                break;

            case self::ARRANGINGPICTURES:
                $retObj = new ArrangingPictures();
                break;

            case self::SHAPECOLORSORTING_DEPRECATED:
                $retObj = new ShapeColorSortingDeprecated();
                break;

            case self::BLOCKROTATION:
                $retObj = new BlockRotation();
                break;

            case self::WORDPROBLEMS:
                $retObj = new WordProblems();
                break;

            case self::PUZZLECOMPLETION:
                $retObj = new PuzzleCompletion();
                break;

            case self::LETTERSANDNUMBERS:
                $retObj = new LettersAndNumbers();
                break;

            case self::VARIETYTEST:
                $retObj = new VarietyTest();
                break;

            case self::SHAPECOLORSORTING_SPANISH:
                $retObj = new ShapeColorSortingSpanish();
                break;

            case ActiveTask::ARROWS_SPANISH:
                $retObj = new ArrowsSpanish();
                break;

            case ActiveTask::FNAMELEARNING_SPANISH:
                $retObj = new FNAMELearningSpanish();
                break;

            case ActiveTask::FNAMETEST_SPANISH:
                $retObj = new FnameTestSpanish();
                break;

            case ActiveTask::SEQUENCES_SPANISH:
                $retObj = new SequencesSpanish();
                break;

            case ActiveTask::NUMBERMATCH_SPANISH:
                $retObj = new NumberMatchSpanish();
                break;

            case ActiveTask::ARRANGINGPICTURES_SPANISH:
                $retObj = new ArrangingPicturesSpanish();
                break;

            case ActiveTask::WORDMEANING1_SPANISH:
                $retObj = new WordMeaningForm1Spanish();
                break;

            default:
                throw new \Exception("Invalid Active Task Format: $format");
        }
        return $retObj;
    }

    /**
     * Create instrument in REDCap application
     *
     * @param string $form_name
     * @return array
     */
    public static function createREDCapForm($form_name) {
        global $Proj;
        $projectDesigner = new ProjectDesigner($Proj);
        $created = $projectDesigner->createForm($form_name);
        return array($created, $projectDesigner->form);
    }

    /**
     * Insert initial default task settings when task is created from active tasks and make it enabled for mycap
     *
     * @param string $form_name
     * @param string $form_label
     * @param string $task_format
     * @param string $extendedConfigAsString
     * @return boolean
     */
    public static function insertDefaultTaskSetting($form_name, $form_label, $task_format, $extendedConfigAsString) {
        $return = false;
        $task_title = $form_label;
        $card_display =  Task::TYPE_PERCENTCOMPLETE;

        // Save task info
        $sql = "REPLACE INTO redcap_mycap_tasks (project_id, form_name, task_title, question_format, card_display, extended_config_json)
			    VALUES (".PROJECT_ID.", '" . db_escape($form_name) . "', '" . db_escape($task_title) . "', '" . db_escape($task_format) . "', 
			            '" . db_escape($card_display) . "', '" . db_escape($extendedConfigAsString) . "')";

        if (db_query($sql)) {
            $return = true;
            $task_id = db_insert_id();
            global $Proj;
            if (!$Proj->longitudinal) {
                $sql_task_schedule = "REPLACE INTO redcap_mycap_tasks_schedules (task_id, event_id, schedule_relative_to, schedule_type, schedule_relative_offset, schedule_ends)
			            VALUES (".$task_id.", ".$Proj->firstEventId.", '".Task::RELATIVETO_JOINDATE."', '".Task::TYPE_INFINITE."', '0', '".Task::ENDS_NEVER."')";
                db_query($sql_task_schedule);
            }
            // Log the event
            //Logging::logEvent($sql, "redcap_mycap_tasks", "MANAGE", $task_id, "task_id = $task_id", "Set up MyCap Active Task");
        }
        return $return;
    }

    /**
     * Returns human readable string for the given format
     *
     * @param string $format
     * @return string
     */
    public static function toString($format)
    {
        switch ($format) {
            case self::AMSLERGRID:
                $retVal = 'Amsler Grid Active Task';
                break;
            case self::AUDIO:
                $retVal = 'Sustained Phonation Active Task';
                break;
            case self::AUDIORECORDING:
                $retVal = 'Audio Recording';
                break;
            case self::DBHLTONEAUDIOMETRY:
                $retVal = 'dBHL Tone Audiometry Active Task';
                break;
            case self::FITNESSCHECK:
                $retVal = 'Fitness Check Active Task';
                break;
            case self::HOLEPEG:
                $retVal = 'Hole Peg';
                break;
            case self::PROMIS:
                $retVal = 'Health Measure';
                break;
            case self::PSAT:
                $retVal = 'PSAT Active Task';
                break;
            case self::RANGEOFMOTION:
                $retVal = 'Range of Motion Active Task';
                break;
            case self::REACTIONTIME:
                $retVal = 'Reaction Time Active Task';
                break;
            case self::SELFIECAPTURE:
                $retVal = 'Selfie Capture Active Task';
                break;
            case self::SHORTWALK:
                $retVal = 'Short Walk Active Task';
                break;
            case self::SPATIALSPANMEMORY:
                $retVal = 'Spatial Span Memory Test Active Task';
                break;
            case self::SPEECHINNOISE:
                $retVal = 'Speech in Noise Active Task';
                break;
            case self::SPEECHRECOGNITION:
                $retVal = 'Speech Recognition Active Task';
                break;
            case self::STROOP:
                $retVal = 'Stroop Active Task';
                break;
            case self::TIMEDWALK:
                $retVal = 'Timed Walk Active Task';
                break;
            case self::TONEAUDIOMETRY:
                $retVal = 'Tone Audiometry Active Task';
                break;
            case self::TOWEROFHANOI:
                $retVal = 'Tower of Hanoi Active Task';
                break;
            case self::TRAILMAKING:
                $retVal = 'Trail Making Active Task';
                break;
            case self::TWOFINGERTAPPINGINTERVAL:
                $retVal = 'Two Finger Tapping Interval Active Task';
                break;
            case self::VUMCAUDIORECORDING:
                $retVal = 'Audio Recording';
                break;
            case self::VUMCCONTRACTIONTIMER:
                $retVal = 'Contraction Timer';
                break;
            case self::ARROWS:
                $retVal = 'MTB Arrows Active Task';
                break;
            case self::SPELLING:
                $retVal = 'MTB Spelling Active Task';
                break;
            case self::SEQUENCES:
                $retVal = 'MTB Sequences Active Task';
                break;
            case self::FNAMELEARNING:
                $retVal = 'MTB Faces and Names 1A Active Task';
                break;
            case self::FNAMETEST:
                $retVal = 'MTB Faces and Names 1B Active Task';
                break;
            case self::NUMBERMATCH:
                $retVal = 'MTB Number Match Active Task';
                break;
            case self::WORDMEANING1:
                $retVal = 'MTB Word Meaning Form 1 Active Task';
                break;
            case self::WORDMEANING2:
                $retVal = 'MTB Word Meaning Form 2 Active Task';
                break;
            case self::SHAPECOLORSORTING:
                $retVal = 'MTB Shape Color Sorting Active Task';
                break;
            case self::ARRANGINGPICTURES:
                $retVal = 'MTB Arranging Pictures Active Task';
                break;
            case self::SHAPECOLORSORTING_DEPRECATED:
                $retVal = 'MTB Shape Color Sorting (Deprecated) Active Task';
                break;
            case self::BLOCKROTATION:
                $retVal = 'MTB Block Rotation Active Task';
                break;
            case self::WORDPROBLEMS:
                $retVal = 'MTB Word Problems Active Task';
                break;
            case self::PUZZLECOMPLETION:
                $retVal = 'MTB Puzzle Completion Active Task';
                break;
            case self::LETTERSANDNUMBERS:
                $retVal = 'MTB Letters And Numbers Active Task';
                break;
            case self::VARIETYTEST:
                $retVal = 'MTB Variety Test Active Task';
                break;
            case self::SHAPECOLORSORTING_SPANISH:
                $retVal = 'MTB Shape Color Sorting Spanish Active Task';
                break;
            case ActiveTask::ARROWS_SPANISH:
                $retVal = 'MTB Arrows Spanish Active Task';
                break;
            case ActiveTask::FNAMELEARNING_SPANISH:
                $retVal = 'MTB Faces and Names 1A Spanish Active Task';
                break;
            case ActiveTask::FNAMETEST_SPANISH:
                $retVal = 'MTB Faces and Names 1B Spanish Active Task';
                break;
            case ActiveTask::SEQUENCES_SPANISH:
                $retVal = 'MTB Sequences Spanish Active Task';
                break;
            case ActiveTask::NUMBERMATCH_SPANISH:
                $retVal = 'MTB Number Match Spanish Active Task';
                break;
            case ActiveTask::ARRANGINGPICTURES_SPANISH:
                $retVal = 'MTB Arranging Pictures Spanish Active Task';
                break;
            case ActiveTask::WORDMEANING1_SPANISH:
                $retVal = 'MTB Word Meaning Form 1 Spanish Active Task';
                break;
            default:
                $retVal = 'Invalid Format';
                break;
        }
        return $retVal;
    }

    public static function getHelpURLForTaskFormat($task_format) {
        $allTasks = self::getAllActiveTasks();
        $taskArr = [];
        foreach ($allTasks as $category => $tasks) {
            foreach ($tasks as $task) {
                $taskArr[$task['addHref']] = $task['helpLink'] ?? "";
            }
        }
        return $taskArr[$task_format] ?? "";
    }

    /**
     * Get all active tasks listing
     *
     * @return array
     */
    public static function getAllActiveTasks($third_party_platform = '')
    {
        $tasks['Motor Activities'][] = array(
            'name' => 'Range of Motion',
            'helpLink' => '#range',
            'addHref' => self::RANGEOFMOTION
        );
        $tasks['Motor Activities'][] = array(
            'name' => 'Gait and Balance (Short Walk)',
            'helpLink' => '#gait',
            'addHref' => self::SHORTWALK
        );
        $tasks['Motor Activities'][] = array(
            'name' => 'Tapping Speed',
            'helpLink' => '#tapping',
            'platform' => array('android','apple'),
            'addHref' => self::TWOFINGERTAPPINGINTERVAL
        );

        $tasks['Fitness'][] = array(
            'name' => 'Fitness',
            'helpLink' => '#fitness',
            'addHref' => self::FITNESSCHECK
        );
        $tasks['Fitness'][] = array(
            'name' => 'Timed Walk',
            'helpLink' => '#timed',
            'addHref' => self::TIMEDWALK
        );

        $tasks['Cognition'][] = array(
            'name' => 'Spatial Memory',
            'helpLink' => '#spatial',
            'addHref' => self::SPATIALSPANMEMORY
        );
        $tasks['Cognition'][] = array(
            'name' => 'Stroop',
            'helpLink' => '#stroop',
            'addHref' => self::STROOP
        );
        $tasks['Cognition'][] = array(
            'name' => 'Trail Making Test',
            'helpLink' => '#trail',
            'addHref' => self::TRAILMAKING
        );
        $tasks['Cognition'][] = array(
            'name' => 'Paced Serial Addition Test (PSAT)',
            'helpLink' => '#paced',
            'addHref' => self::PSAT
        );
        $tasks['Cognition'][] = array(
            'name' => 'Tower of Hanoi',
            'helpLink' => '#tower',
            'addHref' => self::TOWEROFHANOI
        );
        $tasks['Cognition'][] = array(
            'name' => 'Reaction Time',
            'warning' => 'Partially implemented. Results are not sent to REDCap.',
            'helpLink' => '#reaction',
            'platform' => array(),
            'addHref' => self::REACTIONTIME,
            'action' => ''
        );

        $tasks['Speech'][] = array(
            'name' => 'Audio Recording',
            'note' => 'Record spoken phrases using the microphone.',
            'platform' => array('android'),
            'addHref' => self::AUDIORECORDING
        );
        $tasks['Speech'][] = array(
            'name' => 'Sustained Phonation',
            'helpLink' => '#sustained',
            'addHref' => self::AUDIO
        );
        $tasks['Speech'][] = array(
            'name' => 'Speech Recognition',
            'warning' => 'Partially implemented. Speech recognition image is not supported.',
            'helpLink' => '#speech_recognition',
            'addHref' => self::SPEECHRECOGNITION,
            'platform' => array(),
            'action' => ''
        );
        $tasks['Speech'][] = array(
            'name' => 'Speech in Noise',
            'warning' => 'Not implemented',
            'helpLink' => '#speech_in_noise',
            'addHref' => self::SPEECHINNOISE,
            'platform' => array(),
            'action' => ''
        );

        $tasks['Hearing'][] = array(
            'name' => 'Environment SPL',
            'warning' => 'Not implemented',
            'helpLink' => 'spl',
            'addHref' => '',
            'platform' => array(),
            'action' => ''
        );
        $tasks['Hearing'][] = array(
            'name' => 'Tone Audiometry',
            'helpLink' => '#tone',
            'addHref' => self::TONEAUDIOMETRY
        );
        $tasks['Hearing'][] = array(
            'name' => 'dBHL Tone Audiometry',
            'warning' => 'Not implemented',
            'helpLink' => '#dBHL',
            'addHref' => self::DBHLTONEAUDIOMETRY,
            'platform' => array(),
            'action' => ''
        );

        $tasks['Hand Dexterity'][] = array(
            'name' => '9-Hole Peg Test',
            'helpLink' => '#nine',
            'addHref' => self::HOLEPEG
        );

        $tasks['Vision'][] = array(
            'name' => 'Amsler Grid',
            'helpLink' => '#amsler',
            'addHref' => self::AMSLERGRID
        );
        $tasks['Vision'][] = array(
            'name' => 'Selfie Capture',
            'note' => 'Capture a selfie using the front facing camera with facial detection',
            'addHref' => self::SELFIECAPTURE,
            'platform' => array('android'),
        );

        // MTB Active tasks listing
        $mtbEnglishVersionTitle = 'Mobile Toolbox Tasks (English Versions)';
        $mtbTasks[$mtbEnglishVersionTitle][] = array(
            'name' => 'Arranging Pictures',
            'note' => '',
            'addHref' => self::ARRANGINGPICTURES
        );
        $mtbTasks[$mtbEnglishVersionTitle][] = array(
            'name' => 'Arrows',
            'note' => '',
            'addHref' => self::ARROWS
        );
        $mtbTasks[$mtbEnglishVersionTitle][] = array(
            'name' => 'Faces and Names 1A',
            'note' => '',
            'addHref' => self::FNAMELEARNING
        );
        $mtbTasks[$mtbEnglishVersionTitle][] = array(
            'name' => 'Faces and Names 1B',
            'note' => '',
            'addHref' => self::FNAMETEST
        );
        $mtbTasks[$mtbEnglishVersionTitle][] = array(
            'name' => 'Number Match',
            'note' => '',
            'addHref' => self::NUMBERMATCH
        );
        $mtbTasks[$mtbEnglishVersionTitle][] = array(
            'name' => 'Sequences',
            'note' => '',
            'addHref' => self::SEQUENCES
        );
        $mtbTasks[$mtbEnglishVersionTitle][] = array(
            'name' => 'Shape-Color Sorting',
            'note' => '',
            'addHref' => self::SHAPECOLORSORTING
        );
        $mtbTasks[$mtbEnglishVersionTitle][] = array(
            'name' => 'Shape Color Sorting (Deprecated)',
            'note' => '',
            'addHref' => self::SHAPECOLORSORTING_DEPRECATED
        );
        $mtbTasks[$mtbEnglishVersionTitle][] = array(
            'name' => 'Spelling',
            'note' => '',
            'addHref' => self::SPELLING
        );
        $mtbTasks[$mtbEnglishVersionTitle][] = array(
            'name' => 'Word Meaning Form 1',
            'note' => '',
            'addHref' => self::WORDMEANING1
        );
        $mtbTasks[$mtbEnglishVersionTitle][] = array(
            'name' => 'Word Meaning Form 2',
            'note' => '',
            'addHref' => self::WORDMEANING2
        );

        $mtbSpanishVersionTitle = 'Mobile Toolbox Tasks (Spanish)';
        $mtbTasks[$mtbSpanishVersionTitle][] = array(
            'name' => 'Arranging Pictures Spanish',
            'note' => '',
            'addHref' => self::ARRANGINGPICTURES_SPANISH
        );
        $mtbTasks[$mtbSpanishVersionTitle][] = array(
            'name' => 'Arrows Spanish',
            'note' => '',
            'addHref' => self::ARROWS_SPANISH
        );
        $mtbTasks[$mtbSpanishVersionTitle][] = array(
            'name' => 'FNAME Learning Spanish',
            'note' => '',
            'addHref' => self::FNAMELEARNING_SPANISH
        );
        $mtbTasks[$mtbSpanishVersionTitle][] = array(
            'name' => 'FNAME Test Spanish',
            'note' => '',
            'addHref' => self::FNAMETEST_SPANISH
        );
        $mtbTasks[$mtbSpanishVersionTitle][] = array(
            'name' => 'Number Match Spanish',
            'note' => '',
            'addHref' => self::NUMBERMATCH_SPANISH
        );
        $mtbTasks[$mtbSpanishVersionTitle][] = array(
            'name' => 'Sequences Spanish',
            'note' => '',
            'addHref' => self::SEQUENCES_SPANISH
        );
        $mtbTasks[$mtbSpanishVersionTitle][] = array(
            'name' => 'Shape-Color Sorting Spanish',
            'note' => '',
            'addHref' => self::SHAPECOLORSORTING_SPANISH
        );
        $mtbTasks[$mtbSpanishVersionTitle][] = array(
            'name' => 'Word Meaning Form 1 Spanish',
            'note' => '',
            'addHref' => self::WORDMEANING1_SPANISH
        );

        global $mtb_experimental_enabled;
        if ($mtb_experimental_enabled) {
            $mtbTasks['Mobile Toolbox Tasks (Experimental)'][] = array(
                'name' => 'Block Rotation',
                'note' => '',
                'addHref' => self::BLOCKROTATION
            );
            $mtbTasks['Mobile Toolbox Tasks (Experimental)'][] = array(
                'name' => 'Letters And Numbers',
                'note' => '',
                'addHref' => self::LETTERSANDNUMBERS
            );
            $mtbTasks['Mobile Toolbox Tasks (Experimental)'][] = array(
                'name' => 'Puzzle Completion',
                'note' => '',
                'addHref' => self::PUZZLECOMPLETION
            );
            $mtbTasks['Mobile Toolbox Tasks (Experimental)'][] = array(
                'name' => 'Variety Test',
                'note' => '',
                'addHref' => self::VARIETYTEST
            );
            $mtbTasks['Mobile Toolbox Tasks (Experimental)'][] = array(
                'name' => 'Word Problems',
                'note' => '',
                'addHref' => self::WORDPROBLEMS
            );
        }
        return ($third_party_platform == 'mtb' ? $mtbTasks : $tasks);
    }

    public static function getResearchKitActiveTasksFormats($third_party_platform = '') {
        $all_tasks = self::getAllActiveTasks($third_party_platform);

        foreach ($all_tasks as $category => $tasks) {
            foreach ($tasks as $task) {
                $formats[] = $task['addHref'];
            }
        }
        return $formats;
    }

    /**
     * Return HTML that displays list of active tasks with link and "Add" button
     *
     * @return string
     */
    public static function getActiveTasksListLayout($third_party_platform) {
        global $lang;
        $active_tasks = self::getAllActiveTasks($third_party_platform);

        $html = '';
        foreach ($active_tasks as $this_task_category => $tasks) {
            $warning = '';
            if ($this_task_category == 'Mobile Toolbox Tasks (Experimental)') {
                $warning = '<br><span style="color:red;font-size:11px;font-weight: normal">[Please note, these are experimental MTB measures that are not yet validated.]</span>';
            }
            $row = '';
            foreach ($tasks as $key => $task) {
                if (!isset($task['action'])) {
                    $button = '<button onclick="addNewActiveTask(\''.$task['addHref'].'\', \''.$task['name'].'\')" class="btn btn-xs btn-rcgreen addInstrBtn" style="margin-left:0px;">
                                <i class="fas fa-plus"></i> Add</button>';
                } else {
                    $button = $lang['control_center_149'];
                }

                if (isset($task['platform'])) {
                    $platforms = $task['platform'];
                } else {
                    $platforms = array('apple');
                }
                $supported_platform = array_map(function ($value)
                                        {
                                            return ($value == 'android') ?
                                                '<img width="16" src="'.APP_PATH_IMAGES.'android.png" title="Platform: Android">'
                                                : '<img width="16" src="'.APP_PATH_IMAGES.'apple.png" title="Platform: Apple">';
                                        }, $platforms);
                $supported_platform_icons = implode("",$supported_platform);

                $flutter_supported_platform_icons = '';
                if (!empty($supported_platform)) {
                    $flutter_supported_platform_icons = '<img width="16" src="'.APP_PATH_IMAGES.'android.png" title="Platform: Android">'
                                                        . '<img width="16" src="'.APP_PATH_IMAGES.'apple.png" title="Platform: Apple">';
                }

                // First, build rows for each active task
                $row .= RCView::tr('',
                    RCView::td(array('class' => 'data', 'style' => 'padding:1px 5px;'),
                            RCView::table(array('cellspacing'=>'0','align'=>'center','style'=>'table-layout:fixed;width:100%;'),
                                // Table header
                                RCView::tr('',
                                    RCView::td(array('width' => '90%'),
                                        // Display task name
                                        '<span style="padding-left: 8px; font-weight: bold;">'.$task['name'].'</span>'.
                                        RCView::br().
                                        // Display Warning
                                        ((isset($task['warning']) && $task['warning'] != '') ?
                                            RCView::span(array('style' => 'color:red;margin-left:8px;font-size:11px;'), "[" . $task['warning'] . "]") : ""
                                        ).
                                        // Display Note
                                        ((isset($task['note']) && $task['note'] != '') ?
                                            RCView::span(array('class' => 'newdbsub', 'style'=>'margin-left:8px;font-size:11px;'), "[" . $task['note'] . "]")
                                         : "")
                                    )
                                )
                            )
                        )  .
                        (($third_party_platform != 'mtb') ? RCView::td(array('class'=>'data', 'style'=>'text-align:center;'),
                            $supported_platform_icons
                        ) : '') .
                        RCView::td(array('class'=>'data', 'style'=>'text-align:center;'),
                            $flutter_supported_platform_icons
                        ) .
                        RCView::td(array('class'=>'data', 'style'=>'text-align:center;font-size:11px;font-weight:bold;'),
                            // Add Button
                            $button
                        )
                    );
            }

            // Build table for this survey
            $html .= 	RCView::table(array('cellspacing'=>'0','class'=>'form_border', 'align'=>'center','style'=>'table-layout:fixed;margin:0 20px 20px 20px;width:97%;'),
                // Table header
                RCView::tr('',
                    RCView::td(array('class'=>'header', 'width'=>'65%', 'style'=>'color:#800000;'),
                        strip_tags($this_task_category).$warning
                    ) .
                    (($third_party_platform != 'mtb') ? RCView::td(array('class'=>'header', 'width'=>'9%', 'style'=>'text-align: center;'),
                        RCView::img(array('src'=>'mycap_classic.jpg', 'width'=>'30'))."<br><span style='color:#800000;font-size:9px; font-weight: normal;'>".RCView::tt('mycap_mobile_app_819')."</span>"
                    ) : '') .
                    RCView::td(array('class'=>'header', 'width'=>'7%', 'style'=>'color:#800000;  text-align: center;'),
                        RCView::img(array('src'=>'mycap_new.png'))."<br><span style='color:#800000;font-size:9px; font-weight: normal;'>".RCView::tt('grid_58')."</span>"
                    ) .
                    RCView::td(array('class'=>'header', 'width'=>'9%', 'style'=>'text-align:center;font-weight:bold;'),
                        $lang['reporting_21']
                    )
                ) .
                // All rows of active tasks
                $row
            );

        }
        return $html;
    }

    /**
     * Return New Field name - check for unique
     *
     * @param string $fieldName
     * @return string
     */
    public static function getNewFieldName($fieldName) {
        global $status;
        $metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
        $sql = "SELECT COUNT(*) AS total FROM $metadata_table  WHERE project_id = ".PROJECT_ID." AND field_name = '{$fieldName}' LIMIT 1";
        $q = db_query($sql);
        $total_rows = db_result($q, 0, 'total');
        if ($total_rows > 0) {
            list($root, $num, $padding) = determine_repeat_parts($fieldName);
            do {
                $num++;
                $suffix_padded = str_pad($num, $padding, '0', STR_PAD_LEFT);
                $new_field_name = $root . $suffix_padded;

                $sql = "SELECT COUNT(1) FROM $metadata_table WHERE project_id = ".PROJECT_ID." AND field_name = '$new_field_name'";
                $varExists = db_result(db_query($sql), 0);
            } while ($varExists);

            $fieldName = $new_field_name;
        }
        return $fieldName;
    }

    /**
     * An active task may have one or more configurable properties. This method returns DEFAULT property
     * values for the active task as a JSON string. E.g. {"foo":"bar","zip":"zap"}
     *
     * @return string JSON string
     */
    public static function extendedConfigAsString($obj)
    {
        return is_object($obj) ? json_encode(get_object_vars($obj), JSON_FORCE_OBJECT) : null;
    }

    /**
     * New *_item_count fields are added to MTB measures, So add those fields in existing MTB measures by executing script
     *
     * @return void
     */
    public static function fixMissingFieldsMTBMeasures() {
        global $draft_mode, $status;
        if ($draft_mode != '1' && $status > 0)  return;

        $mtbFormats = array(self::FNAMELEARNING, self::FNAMETEST, self::WORDMEANING1, self::WORDMEANING2,
                            self::SHAPECOLORSORTING, self::ARROWS, self::SPELLING, self::SEQUENCES,
                            self::NUMBERMATCH, self::ARRANGINGPICTURES);

        $formatList = prep_implode($mtbFormats);
        // Fetch all instruments added as MTB measures
        $sql = "SELECT task_id, project_id, form_name, question_format FROM redcap_mycap_tasks WHERE enabled_for_mycap = 1 AND question_format IN ($formatList) AND project_id = ".PROJECT_ID;
        $q = db_query($sql);

        if (db_num_rows($q) > 0) {
            while ($row = db_fetch_assoc($q)) {
                $pid = $row['project_id'];
                $form = $row['form_name'];
                $Proj = new Project($pid);
                $projectDesigner = new ProjectDesigner($Proj);

                $task_format = $row['question_format'];

                // Perform:: Create specific *_item_count fields as per Task format
                switch ($task_format) {
                    case self::FNAMELEARNING:
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'fnl_item_count');
                        if ($fieldExists == false) {
                            // Add new field "fnl_item_count" to this instrument
                            $fieldArr = array('field_name' => 'fnl_item_count',
                                            'field_label' => 'itemCount',
                                            'field_type' => 'text',
                                            'field_req' => 1,
                                            'val_type' => 'integer',
                                            'val_min' => 0,
                                            'val_max' => 12);
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "fnl_userinteractions"
                            $projectDesigner->moveFieldAfterField('fnl_userinteractions', 'fnl_item_count');
                        }
                        break;

                    case self::FNAMETEST:
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'fnt_item_count');
                        if ($fieldExists == false) {
                            // Add new field "fnt_item_count" to this instrument
                            $fieldArr = array('field_name' => 'fnt_item_count',
                                            'field_label' => 'itemCount',
                                            'field_type' => 'text',
                                            'field_req' => 1,
                                            'val_type' => 'integer',
                                            'val_min' => 0,
                                            'val_max' => 36);
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "fnt_rawscore"
                            $projectDesigner->moveFieldAfterField('fnt_rawscore', 'fnt_item_count');
                        }
                        break;

                    case self::WORDMEANING1:
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'wm1_item_count');
                        if ($fieldExists == false) {
                            // Add new field "wm1_item_count" to this instrument
                            $fieldArr = array('field_name' => 'wm1_item_count',
                                            'field_label' => 'itemCount',
                                            'field_type' => 'text',
                                            'field_req' => 1,
                                            'val_type' => 'integer',
                                            'val_min' => 0,
                                            'val_max' => 25);
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "wm1_finalse"
                            $projectDesigner->moveFieldAfterField('wm1_finalse', 'wm1_item_count');
                        }
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'wm1_skip_count');
                        if ($fieldExists == false) {
                            // Add new field "wm1_skip_count" to this instrument
                            $fieldArr = array('field_name' => 'wm1_skip_count',
                                            'field_label' => 'skipCount',
                                            'field_type' => 'text',
                                            'val_type' => 'integer');
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "wm1_item_count"
                            $projectDesigner->moveFieldAfterField('wm1_item_count', 'wm1_skip_count');
                        }
                        break;

                    case self::WORDMEANING2:
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'wm2_item_count');
                        if ($fieldExists == false) {
                            // Add new field "wm2_item_count" to this instrument
                            $fieldArr = array('field_name' => 'wm2_item_count',
                                            'field_label' => 'itemCount',
                                            'field_type' => 'text',
                                            'field_req' => 1,
                                            'val_type' => 'integer',
                                            'val_min' => 0,
                                            'val_max' => 25);
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "wm2_finalse"
                            $projectDesigner->moveFieldAfterField('wm2_finalse', 'wm2_item_count');
                        }
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'wm2_skip_count');
                        if ($fieldExists == false) {
                            // Add new field "wm2_skip_count" to this instrument
                            $fieldArr = array('field_name' => 'wm2_skip_count',
                                            'field_label' => 'skipCount',
                                            'field_type' => 'text',
                                            'val_type' => 'integer');
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "wm2_item_count"
                            $projectDesigner->moveFieldAfterField('wm2_item_count', 'wm2_skip_count');
                        }
                        break;

                    case self::SHAPECOLORSORTING:
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'scs_item_count');
                        if ($fieldExists == false) {
                            // Add new field "scs_item_count" to this instrument
                            $fieldArr = array('field_name' => 'scs_item_count',
                                            'field_label' => 'itemCount',
                                            'field_type' => 'text',
                                            'field_req' => 1,
                                            'val_type' => 'integer',
                                            'val_min' => 0,
                                            'val_max' => 60);
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "scs_rawscore"
                            $projectDesigner->moveFieldAfterField('scs_rawscore', 'scs_item_count');
                        }
                        break;

                    case self::ARROWS:
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'arw_item_count');
                        if ($fieldExists == false) {
                            // Add new field "arw_item_count" to this instrument
                            $fieldArr = array('field_name' => 'arw_item_count',
                                            'field_label' => 'itemCount',
                                            'field_type' => 'text',
                                            'field_req' => 1,
                                            'val_type' => 'integer',
                                            'val_min' => 0,
                                            'val_max' => 100);
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "arw_rawscore"
                            $projectDesigner->moveFieldAfterField('arw_rawscore', 'arw_item_count');
                        }
                        break;

                    case self::SPELLING:
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'spl_item_count');
                        if ($fieldExists == false) {
                            // Add new field "spl_item_count" to this instrument
                            $fieldArr = array('field_name' => 'spl_item_count',
                                            'field_label' => 'itemCount',
                                            'field_type' => 'text',
                                            'field_req' => 1,
                                            'val_type' => 'integer',
                                            'val_min' => 0,
                                            'val_max' => 30);
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "spl_consideredsteps"
                            $projectDesigner->moveFieldAfterField('spl_consideredsteps', 'spl_item_count');
                        }

                        $fieldExists = \Form::checkFieldExists($pid, $form, 'spl_skip_count');
                        if ($fieldExists == false) {
                            // Add new field "spl_skip_count" to this instrument
                            $fieldArr = array('field_name' => 'spl_skip_count',
                                                'field_label' => 'skipCount',
                                                'field_type' => 'text',
                                                'val_type' => 'integer');
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "spl_item_count"
                            $projectDesigner->moveFieldAfterField('spl_item_count', 'spl_skip_count');
                        }
                        break;

                    case self::SEQUENCES:
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'mfs_item_count');
                        if ($fieldExists == false) {
                            // Add new field "mfs_item_count" to this instrument
                            $fieldArr = array('field_name' => 'mfs_item_count',
                                                'field_label' => 'itemCount',
                                                'field_type' => 'text',
                                                'field_req' => 1,
                                                'val_type' => 'integer',
                                                'val_min' => 0,
                                                'val_max' => 30);
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "mfs_rawscore"
                            $projectDesigner->moveFieldAfterField('mfs_rawscore', 'mfs_item_count');
                        }
                        break;

                    case self::NUMBERMATCH:
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'nsm_item_count');
                        if ($fieldExists == false) {
                            // Add new field "nsm_item_count" to this instrument
                            $fieldArr = array('field_name' => 'nsm_item_count',
                                            'field_label' => 'itemCount',
                                            'field_type' => 'text',
                                            'field_req' => 1,
                                            'val_type' => 'integer',
                                            'val_min' => 0,
                                            'val_max' => 144);
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "nsm_rawscore"
                            $projectDesigner->moveFieldAfterField('nsm_rawscore', 'nsm_item_count');
                        }
                        break;

                    case self::ARRANGINGPICTURES:
                        $fieldExists = \Form::checkFieldExists($pid, $form, 'arp_item_count');
                        if ($fieldExists == false) {
                            // Add new field "arp_item_count" to this instrument
                            $fieldArr = array('field_name' => 'arp_item_count',
                                            'field_label' => 'itemCount',
                                            'field_type' => 'text',
                                            'field_req' => 1,
                                            'val_type' => 'integer',
                                            'val_min' => 0,
                                            'val_max' => 2);
                            $projectDesigner->createField($form, $fieldArr);
                            // Move this field after "arp_adjacentpairsscore"
                            $projectDesigner->moveFieldAfterField('arp_adjacentpairsscore', 'arp_item_count');
                        }
                        break;
                }
            }
        }
    }

    /**
     * Check if at least one Spanish/English MTB measure added for a project
     * @param string $language
     * @param integer $project_id
     *
     * @return boolean
     */
    public static function isLangMTBActiveTaskExists($language = 'English', $project_id = null) {
        if (is_null($project_id)) {
            $project_id = PROJECT_ID;
        }
        $found = false;
        // Populate tasks array those enabled for MyCap
        $sql = "SELECT * FROM redcap_mycap_tasks WHERE project_id = " . $project_id . " AND enabled_for_mycap = 1";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Check if English MTB tasks exists
            if ($language == 'English') {
                if (self::isMTBActiveTask($row['question_format']) && !str_ends_with($row['question_format'], 'Spanish')) {
                    $found = true;
                    break;
                }
            } else {
                // Check if Spanish MTB tasks exists
                if (str_ends_with($row['question_format'], 'Spanish')) {
                    $found = true;
                    break;
                }
            }
        }
        return $found;
    }
}
