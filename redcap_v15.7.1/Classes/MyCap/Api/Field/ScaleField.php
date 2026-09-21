<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Field;

use Vanderbilt\REDCap\Classes\MyCap\ValidationType;

/**
 * A scale field compatible with ResearchKit Scale and Continuous Scale fields. Reference ResearchKit
 * ORKScaleAnswerFormat and ORKContinuousScaleAnswerFormat
 *
 * @link http://researchkit.org/docs/Classes/ORKScaleAnswerFormat.html
 * @link http://researchkit.org/docs/Classes/ORKContinuousScaleAnswerFormat.html
 *
 * @package Vanderbilt\REDCap\Classes\MyCap\Api\Field
 */
class ScaleField
{
    const DEFAULT_MIN = 0;
    const DEFAULT_MAX = 100;

    const TYPE_BASIC = '.Basic';
    const TYPE_CONTINUOUS = '.Continuous';

    /** Minimum number of steps in a task should not be less than 1 */
    const MINIMUM_STEP_SIZE = 1;

    /** Minimum number of sections on a scale (step count) should not be less than 1 */
    const MINIMUM_STEP_COUNT = 1;

    /** Maximum number of sections on a scale (step count) should not be more than 13 */
    const MAXIMUM_STEP_COUNT = 13;

    /** The lower bound value in scale answer format cannot be lower than -10000 */
    const VALUE_LOWER_BOUND = -10000;

    /** The upper bound value in scale answer format cannot be more than 10000 */
    const VALUE_UPPER_BOUND = 10000;

    /** @var string */
    public $type = self::TYPE_CONTINUOUS;

    /** @var int|float */
    public $max = 100;

    /** @var int|float */
    public $min = 0;

    /** @var int|float */
    public $redcapMax = 100;

    /** @var int|float */
    public $redcapMin = 0;

    /**
     * If default exceeds upper bound then there will be no default. VALUE_UPPER_BOUND + 1
     * @var int|float Default scale value.
     */
    public $default = 10001;

    /** @var int */
    public $stepBy = 0;

    /** @var boolean */
    public $vertical = false;

    /** @var string */
    public $maximumDescription = '';

    /** @var string */
    public $minimumDescription = '';

    /** @var string */
    public $middleDescription = '';

    // Slider Constants
    const RANGE_MIN = 0;
    const RANGE_MAX = 100;

    // TODO: This has not been tested
    public $text_validation_min = self::RANGE_MIN;
    public $text_validation_max = self::RANGE_MAX;
    public $text_validation_type_or_show_slider_number = ValidationType::INTEGER;
    /**
     * Returns object properties as an array. You must call validate() prior to calling toArray(). ResearchKit will
     * crash the mobile app if it gets an invalid scale field.
     *
     * @return array
     */
    public function toArray()
    {
        $ret_val = [
            "type" => $this->type,
            "maximumValue" => ($this->type == self::TYPE_BASIC) ? (int)$this->max : (float)$this->max,
            "minimumValue" => ($this->type == self::TYPE_BASIC) ? (int)$this->min : (float)$this->min,
            "defaultValue" => ($this->type == self::TYPE_BASIC) ? (int)$this->default : (float)$this->default,
            "step" => (int)$this->stepBy,
            "redcapMaximumValue" => ($this->type == self::TYPE_BASIC) ? (int)$this->redcapMax : (float)$this->redcapMax,
            "redcapMinimumValue" => ($this->type == self::TYPE_BASIC) ? (int)$this->redcapMin : (float)$this->redcapMin,
            "vertical" => $this->vertical,
            "maximumDescription" => $this->maximumDescription,
            "middleDescription" => $this->middleDescription,
            "minimumDescription" => $this->minimumDescription
        ];
        return $ret_val;
    }

    /**
     * Validate basic and continuous scale fields
     *
     * @return array
     */
    public function validate()
    {
        $errors = [];

        if (!in_array(
            $this->type,
            [self::TYPE_BASIC, self::TYPE_CONTINUOUS]
        )) {
            $errors[] = "Invalid type: (" . $this->type . ")";
        }
        if (!is_numeric($this->max)) {
            $errors[] = "Invalid max: (" . $this->max . "). Must be a numeric value.";
        }
        if (!is_numeric($this->min)) {
            $errors[] = "Invalid min: (" . $this->max . "). Must be a numeric value.";
        }
        if (!is_numeric($this->default)) {
            $errors[] = "Invalid default: (" . $this->max . "). Must be a numeric value.";
        }
        if (!is_numeric($this->stepBy)) {
            $errors[] = "Invalid stepBy: (" . $this->stepBy . "). Must be a numeric value.";
        }
        if (!is_bool($this->vertical)) {
            $errors[] = "Invalid vertical: (" . $this->stepBy . "). Must be a bool value.";
        }
        if (!is_string(($this->maximumDescription))) {
            $errors[] = "Invalid minimumDescription: (" . $this->maximumDescription . "). Must be a string value.";
        }
        if (!is_string(($this->minimumDescription))) {
            $errors[] = "Invalid minimumDescription: (" . $this->minimumDescription . "). Must be a string value.";
        }

        if ($this->type == self::TYPE_BASIC) {
            $errors = array_merge(
                $errors,
                $this->validateBasic()
            );
        } elseif ($this->type == self::TYPE_CONTINUOUS) {
            $errors = array_merge(
                $errors,
                $this->validateContinuous()
            );
        }

        return $errors;
    }

    /**
     * Validate a continuous scale field. This logic needs to be the same as ResearchKit's logic found in the
     * "ORKAnswerFormat.m" file.
     *
     * @return array
     */
    private function validateBasic()
    {
        $errors = [];

        $max = (int)$this->max;
        $min = (int)$this->min;
        $stepBy = (int)$this->stepBy;

        if ($max < $min) {
            $errors[] = "Expect max value ($max) larger than min value ($min)";
        }
        if ($stepBy < self::MINIMUM_STEP_SIZE) {
            $errors[] = "Expect step value ($stepBy) not less than than (" . self::MINIMUM_STEP_SIZE . ")";
        }
        $mod = ($max - $min) % $stepBy;
        if ($mod != 0) {
            $errors[] = "Expect the difference between max value ($max) and min value ($min) is divisible by "
                . "step value ($stepBy).";
        }
        $steps = ($max - $min) / $stepBy;
        if ($steps < self::MINIMUM_STEP_COUNT || $steps > self::MAXIMUM_STEP_COUNT) {
            $errors[] = "Expect the total number of steps between min value and max value more than "
                . self::MINIMUM_STEP_COUNT . " and no more than " . self::MAXIMUM_STEP_COUNT . ".";
        }
        if ($min < self::VALUE_LOWER_BOUND) {
            $errors[] = "Min value should not be less than " . self::VALUE_LOWER_BOUND;
        }
        if ($max > self::VALUE_UPPER_BOUND) {
            $errors[] = "Max value should not be more than " . self::VALUE_UPPER_BOUND;
        }

        return $errors;
    }

    /**
     * Validate a continuous scale field. This logic needs to be the same as ResearchKit's logic found in the
     * "ORKAnswerFormat.m" file.
     *
     * @return array
     */
    private function validateContinuous()
    {
        $errors = [];

        $max = (float)$this->max;
        $min = (float)$this->min;

        // Note that "stepBy" is being used as "maximumFractionDigits" in ResearchKit terms.
        // Just clamp maximumFractionDigits to be 0-4. This is all aimed at keeping the maximum
        // number of digits down to 6 or less. I.e. these ranges are valid:
        //   -1...1         with 4 fractional digits (0.9999)
        //   -10...10       with 3 fractional digits (9.999)
        //   -100...100     with 2 fractional digits (99.99)
        //   -1000...1000   with 1 fractional digit  (999.9)
        //   -10000...10000 with 0 fractional digits (9999)
        $this->stepBy = MAX(
            $this->stepBy,
            0
        );
        $this->stepBy = MIN(
            $this->stepBy,
            4
        );

        $effectiveUpperBound = self::VALUE_UPPER_BOUND * pow(
            0.1,
            $this->stepBy
        );
        $effectiveLowerBound = self::VALUE_LOWER_BOUND * pow(
            0.1,
            $this->stepBy
        );

        if ($max < $min) {
            $errors[] = "Expect max value ($max) larger than min value ($min)";
        }
        if ($min < $effectiveLowerBound) {
            $errors[] = "Min value ($min) should not less than ($effectiveLowerBound) with ("
                . $this->stepBy . ") fractional digits";
        }
        if ($max > $effectiveUpperBound) {
            $errors[] = "Max value ($max) should not be more than ($effectiveUpperBound) whit ("
                . $this->stepBy . ") fractional digits";
        }

        return $errors;
    }

    /**
     * Parse Labels
     *
     * @param string
     * @return array
     */
    public static function parseLabels($select_choices_or_calculations)
    {
        $ret_val = [
            'LEFT' => '',
            'MIDDLE' => '',
            'RIGHT' => ''
        ];

        $parts = explode(
            "|",
            $select_choices_or_calculations
        );

        $parts = array_map('trim', $parts);
        switch (count($parts)) {
            case 2:
                $ret_val['LEFT'] = $parts[0];
                $ret_val['RIGHT'] = $parts[1];
                break;

            case 3:
                $ret_val['LEFT'] = $parts[0];
                $ret_val['MIDDLE'] = $parts[1];
                $ret_val['RIGHT'] = $parts[2];
                break;

            default:
                // Slider did not specify labels
        }
        return $ret_val;
    }
}
