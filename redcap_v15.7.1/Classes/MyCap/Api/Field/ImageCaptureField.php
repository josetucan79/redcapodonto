<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Field;

/**
 * An image capture field compatible with ResearchKit image capture step
 *
 * @link http://researchkit.org/docs/Classes/ORKImageCaptureStep.html
 * @package Vanderbilt\REDCap\Classes\MyCap\Api\Field
 */
class ImageCaptureField
{
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
            "instructions" => $this->instructions,
            "hint" => $this->hint
        ];
        return $ret_val;
    }

    /**
     * Validate an image capture field
     *
     * @return array
     */
    public function validate()
    {
        $errors = [];

        if (!is_string(($this->instructions))) {
            $errors[] = "Invalid instructions: (" . $this->instructions . "). Must be a string value.";
        }
        if (!is_string(($this->hint))) {
            $errors[] = "Invalid hint: (" . $this->hint . "). Must be a string value.";
        }

        return $errors;
    }
}
