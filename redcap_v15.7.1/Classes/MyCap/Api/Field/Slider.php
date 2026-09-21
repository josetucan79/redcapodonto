<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Field;

/**
 * A REDCap API Slider field. It is very important to note that REDCap forces INTEGER, 0...100 for all sliders. API
 * will throw an error if you provide a float.
 *
 * @see /redcap_vX.X.X/Classes/MetaData.php, getFields2, element_validation_[min|max]
 * @package REDCapExt\Api\Field
 */
class Slider extends Field
{
    const RANGE_MIN = 0;
    const RANGE_MAX = 100;

    // TODO: This has not been tested
    public $text_validation_min = self::RANGE_MIN;
    public $text_validation_max = self::RANGE_MAX;
    public $text_validation_type_or_show_slider_number = ValidationType::INTEGER;

    /**
     * Parse REDCap slider labels
     *
     * @param string $select_choices_or_calculations
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
            " | ",
            $select_choices_or_calculations
        );
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

    public function validate()
    {
    }
}
