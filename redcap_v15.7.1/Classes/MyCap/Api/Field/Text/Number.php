<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Field;

//use REDCapExt\Api\Field\Text;

/**
 * TODO: Implement. Have only implemented min/max validation
 *
 * @package REDCapExt\Api\Field\Text
 */
class Number extends Text
{
    public $text_validation_type_or_show_slider_number = ValidationType::NUMBER;

    /**
     * Gets the maximum validation range. Returns null if maximum is not set
     *
     * @param self $field
     * @return float|null
     */
    public static function maximum($field)
    {
        if (strlen($field->text_validation_max) && is_numeric($field->text_validation_max)) {
            return (float)$field->text_validation_max;
        }
        return null;
    }

    /**
     * Gets the minimum validation range. Returns null if minimum is not set
     *
     * @param self $field
     * @return float|null
     */
    public static function minimum($field)
    {
        if (strlen($field->text_validation_min) && is_numeric($field->text_validation_min)) {
            return (float)$field->text_validation_min;
        }
        return null;
    }
}
