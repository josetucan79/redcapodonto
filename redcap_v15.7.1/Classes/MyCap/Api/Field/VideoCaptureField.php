<?php
namespace Vanderbilt\REDCap\Classes\MyCap\Api\Field;

/**
 * A video capture field compatible with ResearchKit video capture step
 *
 * @link http://researchkit.org/docs/Classes/ORKVideoCaptureStep.html
 * @package Vanderbilt\REDCap\Classes\MyCap\Api\Field
 */
class VideoCaptureField
{
    const FLASHMODE_AUTO = '.Auto';
    const FLASHMODE_OFF = '.Off';
    const FLASHMODE_ON = '.On';

    const POSITION_BACK = '.Back';
    const POSITION_FRONT = '.Front';
    const POSITION_UNSPECIFIED = '.Unspecified';

    /** @var float */
    public $duration = 20.0;

    /** @var bool */
    public $audioMute = false;

    /** @var string */
    public $flashMode = self::FLASHMODE_OFF;

    /** @var string */
    public $devicePosition = self::POSITION_BACK;

    /** @var string */
    public $instructions = '';

    /** @var string */
    public $hint = '';

    /**
     * Returns object properties as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $ret_val = [
            "duration" => (float)$this->duration,
            "audioMute" => (bool)$this->audioMute,
            "flashMode" => $this->flashMode,
            "devicePosition" => $this->devicePosition,
            "instructions" => $this->instructions,
            "hint" => $this->hint
        ];
        return $ret_val;
    }

    /**
     * Validate basic and continuous scale fields. You must call validate() prior to calling toArray(). ResearchKit will
     * crash the mobile app if it gets an invalid scale field.
     *
     * @return array
     */
    public function validate()
    {
        $errors = [];

        if (!is_numeric($this->duration)) {
            $errors[] = "Invalid duration: (" . $this->duration . "). Must be a numeric value.";
        }
        if (!is_bool($this->audioMute)) {
            $errors[] = "Invalid audioMute: (" . $this->audioMute . "). Must be a boolean value.";
        }
        if (!in_array(
            $this->flashMode,
            [self::FLASHMODE_AUTO, self::FLASHMODE_OFF, self::FLASHMODE_ON]
        )) {
            $errors[] = "Invalid flashMode: (" . $this->flashMode . ")";
        }
        if (!in_array(
            $this->devicePosition,
            [self::POSITION_BACK, self::POSITION_FRONT, self::POSITION_UNSPECIFIED]
        )) {
            $errors[] = "Invalid devicePosition: (" . $this->devicePosition . ")";
        }
        if (!is_string(($this->instructions))) {
            $errors[] = "Invalid instructions: (" . $this->instructions . "). Must be a string value.";
        }
        if (!is_string(($this->hint))) {
            $errors[] = "Invalid hint: (" . $this->hint . "). Must be a string value.";
        }

        return $errors;
    }
}
