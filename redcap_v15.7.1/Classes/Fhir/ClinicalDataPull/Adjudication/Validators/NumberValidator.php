<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\Validators;

class NumberValidator extends AbstractValidator
{
    const VALIDATION_TYPE_INTEGER = 'int';
    const VALIDATION_TYPE_NUMBER = 'number';

    public function __construct(private $projectMetadata) {}

    public function validate($context)
    {
        $rcField = $context['rcField'] ?? null;
        $validationType = isset($this->projectMetadata[$rcField]['element_validation_type'])
            ? $this->projectMetadata[$rcField]['element_validation_type']
            : null;

        // This validator only handles 'integer' and 'number' types.
        if ($validationType !== self::VALIDATION_TYPE_INTEGER && $validationType !== self::VALIDATION_TYPE_NUMBER) {
            return $this;
        }

        $srcValues = $this->validatedData->getSrcValues();
        foreach ($srcValues as $srcValue) {
            $value = $srcValue->getSrcValue();
            $isValid = false;

            if ($validationType === self::VALIDATION_TYPE_INTEGER) {
                // Stricter validation for integers.
                // Allows for integer values and string representations of integers.
                if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
                    $isValid = true;
                }
            } elseif ($validationType === self::VALIDATION_TYPE_NUMBER) {
                // Allows for floats and integers.
                if (is_numeric($value)) {
                    $isValid = true;
                }
            }

            if (!$isValid) {
                $srcValue->setIsInvalid(true);
            }
        }

        return $this;
    }
}
