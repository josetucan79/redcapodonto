<?php
namespace Vanderbilt\REDCap\Classes\Utility;

class TypeConverter
{
    /**
     * Convert a value to a number (float).
     *
     * @param mixed $value
     * @return float|null
     */
    public static function toNumber($value)
    {
        return is_numeric($value) ? (float)$value : null;
    }

    /**
     * Convert a value to an integer.
     *
     * @param mixed $value
     * @return int|null
     */
    public static function toInteger($value)
    {
        return is_numeric($value) && (int)$value == $value ? (int)$value : null;
    }

    /**
     * Convert a value to an integer number (int).
     *
     * @param mixed $value
     * @return int|null
     */
    public static function toInt($value)
    {
        if (is_numeric($value)) {
            return intval($value);
        }
        return null;
    }

    /**
     * Convert a value to a string.
     *
     * @param mixed $value
     * @return string|null
     */
    public static function toString($value, $default='')
    {
        return is_scalar($value) || (is_object($value) && method_exists($value, '__toString')) ? (string)$value : $default;
    }

    /**
     * Convert a value to a DateTime object.
     *
     * @param mixed $value
     * @param string|null $format
     * @return \DateTime|null
     */
    public static function toDateTime($value, $format = null)
    {
        if (is_null($value)) return null;
        if(is_string($value) && trim($value)==='') return null;
        if ($value instanceof \DateTime) return $value;

        try {
            if ($format) {
                return \DateTime::createFromFormat($format, $value) ?: null;
            }
            return new \DateTime($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Convert a value to a boolean.
     *
     * @param mixed $value
     * @return bool
     */
    public static function toBoolean($value)
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null
            ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
            : false;
    }

    /**
     * Convert a value to an array.
     *
     * @param mixed $value
     * @return array|null
     */
    public static function toArray($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * Convert a value to JSON.
     *
     * @param mixed $value
     * @return string|null
     */
    public static function toJson($value)
    {
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value);
            return $json !== false ? $json : null;
        }

        return null;
    }

    /**
     * Convert a value to a specific type.
     *
     * @param string $type The type to convert to ('int', 'float', 'string', 'datetime', 'bool', 'array', 'json').
     * @param mixed $value The value to be converted.
     * @param string|null $format Optional format for DateTime conversion.
     * @return mixed|null The converted value or null if conversion failed.
     */
    public static function convertTo($type, $value, $format = null)
    {
        switch (strtolower($type)) {
            case 'int':
            case 'integer':
                return self::toInteger($value);
            case 'float':
            case 'double':
            case 'number':
                return self::toNumber($value);
            case 'string':
                return self::toString($value);
            case 'datetime':
                return self::toDateTime($value, $format);
            case 'bool':
            case 'boolean':
                return self::toBoolean($value);
            case 'array':
                return self::toArray($value);
            case 'json':
                return self::toJson($value);
            default:
                throw new \InvalidArgumentException("Unsupported type for conversion: $type");
        }
    }
}
