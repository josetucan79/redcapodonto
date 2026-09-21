<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Utility;

use DateTime;

class StringConverter {
    /**
     * Converts a string to a number.
     *
     * @param string $str The string to convert to a number.
     * @return float|null The converted number, or null if the string cannot be converted to a number.
     */
    public static function toNumber($str) {
        return is_numeric($str) ? (float)$str : null;
    }

    /**
     * Converts a string to a boolean.
     *
     * Returns true if the string is "true", "1", or "yes", case insensitive.
     * Returns false if the string is "false", "0", or "no", case insensitive.
     * Returns null otherwise.
     *
     * @param string $str The string to convert to a boolean.
     * @return bool|null The converted boolean, or null if the string cannot be converted to a boolean.
     */
    public static function toBoolean1($str) {
        $lower = strtolower($str);
        if ($lower === 'true' || $lower === '1' || $lower === 'yes') {
        return true;
        } else if ($lower === 'false' || $lower === '0' || $lower === 'no') {
        return false;
        } else {
        return null;
        }
    }

    /**
     * Converts a string to a boolean value.
     *
     * @param string $str The string to convert.
     * @return bool|null The boolean value, or null if the string cannot be converted.
     */
    public static function toBoolean($str) {
        return filter_var($str, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Converts a string to a datetime object.
     *
     * Assumes the string is in ISO 8601 format (e.g. "2023-04-06T10:30:00Z").
     * Returns null if the string cannot be converted to a datetime object.
     *
     * @param string $str The string to convert to a datetime object.
     * @return DateTime|null The converted datetime object, or null if the string cannot be converted to a datetime object.
     */
    public static function toDatetime($str) {
        $dateTime = DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $str);
        return ($dateTime !== false) ? $dateTime : null;
    }

    /**
     * Converts a string to a string.
     *
     * Returns the original string.
     *
     * @param string $str The string to convert to a string.
     * @return string The original string.
     */
    public static function toString($str) {
        return strval($str);
    }

    public static function currencyIdToSymbol($currencyIdentifier) {
        $currencySymbols = [
            'USD' => '$',   // US Dollar
            'EUR' => '€',   // Euro
            'GBP' => '£',   // British Pound
            'JPY' => '¥',   // Japanese Yen
            // Add more currencies as needed
        ];
        return $currencySymbols[$currencyIdentifier] ?? '';
    }

    public static function toCurrency($amount, $currencySymbol = '$', $decimals = 2, $decimalSeparator = '.', $thousandsSeparator = ',') {
        if(!$amount) return 'NAN';
        $symbol = static::currencyIdToSymbol($currencySymbol) ?? $currencySymbol;
        return $symbol . number_format($amount, $decimals, $decimalSeparator, $thousandsSeparator);
    }
}
