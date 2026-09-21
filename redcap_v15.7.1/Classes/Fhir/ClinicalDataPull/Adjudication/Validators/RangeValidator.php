<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\Validators;

class RangeValidator extends AbstractValidator
{
    protected $projectMetadata;

    public function __construct($projectMetadata)
    {
        $this->projectMetadata = $projectMetadata;
    }

    /**
     * Converts a numeric value to a float. If the value is not numeric, it returns the original value.
     *
     * @param mixed $value The value to be normalized.
     * @return float|mixed The converted float if numeric, or the original value if not numeric.
     */
    private function toNumericOrOriginal($value)
    {
        return is_numeric($value) ? (float) $value : $value;
    }

    public function validate($context)
    {
        $rcField = $context['rcField'] ?? null;
        $validationMin = isset($this->projectMetadata[$rcField]['element_validation_min']) 
                     ? $this->toNumericOrOriginal($this->projectMetadata[$rcField]['element_validation_min']) 
                     : null;

        $validationMax = isset($this->projectMetadata[$rcField]['element_validation_max']) 
                        ? $this->toNumericOrOriginal($this->projectMetadata[$rcField]['element_validation_max']) 
                        : null;

        $srcValues = $this->validatedData->getSrcValues();
        foreach ($srcValues as $srcValueData) {
            $srcValue = $this->toNumericOrOriginal($srcValueData->getSrcValue());

            $outOfRange =   ($validationMin !== null && is_numeric($srcValue) && $srcValue < $validationMin) ||
                            ($validationMax !== null && is_numeric($srcValue) && $srcValue > $validationMax);

            $srcValueData->setIsOutOfRange($outOfRange);
        }

        return $this;
    }
}
