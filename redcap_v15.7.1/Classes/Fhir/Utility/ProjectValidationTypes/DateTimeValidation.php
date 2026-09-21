<?php namespace Vanderbilt\REDCap\Classes\Fhir\Utility\ProjectValidationTypes;

class DateTimeValidation
{
    const TIME = "H:i";
    const DATE = "Y-m-d";
    const DATE_YMD = self::DATE;
    const DATE_MDY = "m-d-Y";
    const DATE_DMY = "d-m-Y";
    const DATETIME = "Y-m-d H:i";
    const DATETIME_YMD = self::DATETIME;
    const DATETIME_MDY = "m-d-Y H:i";
    const DATETIME_DMY = "d-m-Y H:i";
    const DATETIME_SECONDS = "Y-m-d H:i:s";
    const DATETIME_SECONDS_YMD = self::DATETIME_SECONDS;
    const DATETIME_SECONDS_MDY = "m-d-Y H:i:s";
    const DATETIME_SECONDS_DMY = "d-m-Y H:i:s";
    const DEFAULT = '';

    const REDCAP_TIME = 'time';
    const REDCAP_DATE = 'date';
    const REDCAP_DATE_YMD = 'date_ymd';
    const REDCAP_DATE_MDY = 'date_mdy';
    const REDCAP_DATE_DMY = 'date_dmy';
    const REDCAP_DATETIME = 'datetime';
    const REDCAP_DATETIME_YMD = 'datetime_ymd';
    const REDCAP_DATETIME_MDY = 'datetime_mdy';
    const REDCAP_DATETIME_DMY = 'datetime_dmy';
    const REDCAP_DATETIME_SECONDS = 'datetime_seconds';
    const REDCAP_DATETIME_SECONDS_YMD = 'datetime_seconds_ymd';
    const REDCAP_DATETIME_SECONDS_MDY = 'datetime_seconds_mdy';
    const REDCAP_DATETIME_SECONDS_DMY = 'datetime_seconds_dmy';

    const ALL_FORMATS = [
        self::TIME,
        self::DATE,
        self::DATE_YMD,
        self::DATE_MDY,
        self::DATE_DMY,
        self::DATETIME,
        self::DATETIME_YMD,
        self::DATETIME_MDY,
        self::DATETIME_DMY,
        self::DATETIME_SECONDS,
        self::DATETIME_SECONDS_YMD,
        self::DATETIME_SECONDS_MDY,
        self::DATETIME_SECONDS_DMY,
    ];

    const REDCAP_FORMATS = [
        self::REDCAP_TIME => self::TIME,
        self::REDCAP_DATE => self::DATE,
        self::REDCAP_DATE_YMD => self::DATE_YMD,
        self::REDCAP_DATE_MDY => self::DATE_MDY,
        self::REDCAP_DATE_DMY => self::DATE_DMY,
        self::REDCAP_DATETIME => self::DATETIME,
        self::REDCAP_DATETIME_YMD => self::DATETIME_YMD,
        self::REDCAP_DATETIME_MDY => self::DATETIME_MDY,
        self::REDCAP_DATETIME_DMY => self::DATETIME_DMY,
        self::REDCAP_DATETIME_SECONDS => self::DATETIME_SECONDS,
        self::REDCAP_DATETIME_SECONDS_YMD => self::DATETIME_SECONDS_YMD,
        self::REDCAP_DATETIME_SECONDS_MDY => self::DATETIME_SECONDS_MDY,
        self::REDCAP_DATETIME_SECONDS_DMY => self::DATETIME_SECONDS_DMY,
    ];

    /**
     * returna a date format based on the provided REDCap validation type
     *
     * @param string $validation_type
     * @return string
     */
    public static function getDateFormatFromRedcapValidation($validation_type)
    {
        if(!array_key_exists($validation_type, static::REDCAP_FORMATS)) return false;
        return static::REDCAP_FORMATS[$validation_type];
    }
}