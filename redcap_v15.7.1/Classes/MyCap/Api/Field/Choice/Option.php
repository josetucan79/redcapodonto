<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Field;

/**
 * A radio or dropdown choice field may have multiple choices/options
 *
 * @package REDCapExt\Api\Field\Choice
 */
class Option
{
    /** @var string Display-friendly string */
    public $text = '';

    /** @var string Stored-as string */
    public $value = '';

    /**
     * Utility constructor
     *
     * @param $text
     * @param $value
     */
    public function __construct($text, $value)
    {
        $this->text = $text;
        $this->value = $value;
    }
}
