<?php
namespace Vanderbilt\REDCap\Classes\MyCap\Api\Field;

/**
 * A barcode capture field. Uses the device's camerea to scan a barcode of varying symbologies.
 * The text value is displayed and returned. The symbologies array is intended to to be used to
 * specify which symbology to watch for, such as "look for QR codes, Data Matrix, or Code 39".
 * If no symbologies are specified (default) then watch for all types supported by the device.
 *
 * @package namespace Vanderbilt\REDCap\Classes\MyCap\Api\Field
 */
class BarcodeCaptureField
{
    const ENGINE_DEFAULT = '.Default';
    const ENGINE_SCANDIT = '.Scandit';

    public $engine = self::ENGINE_DEFAULT;    

    /**
     * Returns object properties as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $ret_val = [
            "engine" => $this->engine
        ];
        return $ret_val;
    }

    /**
     * Validate a barcode capture field
     *
     * @return array
     */
    public function validate()
    {
        $errors = [];

        if ($this->engine != self::ENGINE_DEFAULT && $this->engine != self::ENGINE_SCANDIT) {
            $errors[] = "Invalid engine. Given (" . $this->engine . "). Expected (".self::ENGINE_DEFAULT.") or (".self::ENGINE_SCANDIT.").";
        }

        return $errors;
    }
}
