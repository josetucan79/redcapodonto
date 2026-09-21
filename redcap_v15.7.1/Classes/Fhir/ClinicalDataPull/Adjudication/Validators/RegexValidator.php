<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\Validators;

class RegexValidator extends AbstractValidator
{
    private $validationPatterns;

    public function __construct($validationPatterns)
    {
        $this->validationPatterns = $validationPatterns;
    }

    public function validate($context)
    {
        $rcField = $context['rcField'] ?? null;
        $regex = $this->validationPatterns[$rcField] ?? null;

        if ($regex) {
            foreach ($this->validatedData->getSrcValues() as &$srcValueData) {
                $srcValue = $srcValueData->getSrcValue();
                if(empty($srcValue)) continue; // do not evaluate if empty string
                $invalid = !preg_match($regex, $srcValue);
                $srcValueData->setIsInvalid($invalid);
            }
        }

        return $this;
    }
}

