<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Field;

/**
 * Enumerates text validation types for Text Fields
 *
 * @package REDCapExt\Field\Text
 */
class ValidationType
{
    const DATE_DMY = 'date_dmy';
    const DATE_MDY = 'date_mdy';
    const DATE_YMD = 'date_ymd';
    const DATETIME_DMY = 'datetime_dmy';
    const DATETIME_MDY = 'datetime_mdy';
    const DATETIME_YMD = 'datetime_ymd';
    const DATETIME_SECONDS_DMY = 'datetime_seconds_dmy';
    const DATETIME_SECONDS_MDY = 'datetime_seconds_mdy';
    const DATETIME_SECONDS_YMD = 'datetime_seconds_ymd';
    const EMAIL = 'email';
    const INTEGER = 'integer';
    const NUMBER = 'number';
    const PHONE = 'phone';
    const TIME = 'time';
    // I think this is an alias for DATETIME_SECONDS_YMD. Find where it lives in REDCap
    const YMD = 'datetime_seconds_ymd';
    const ZIPCODE = 'zipcode';

    /**
     * There are 9 types of dates in different formats.
     *
     * @param $type
     * @return bool
     */
    public static function isDateType($type)
    {
        return in_array(
            $type,
            [
                self::DATE_DMY,
                self::DATE_MDY,
                self::DATE_YMD,
                self::DATETIME_DMY,
                self::DATETIME_MDY,
                self::DATETIME_YMD,
                self::DATETIME_SECONDS_DMY,
                self::DATETIME_SECONDS_MDY,
                self::DATETIME_SECONDS_YMD,
                self::YMD
            ]
        );
    }
}
