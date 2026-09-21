<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\Validators;

use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Utilities\TextNormalizer;

class EqualityValidator extends AbstractValidator
{

    public function validate($context)
    {
        $srcValues = $this->validatedData->getSrcValues();
        $rcValue = $this->validatedData->getRcValue();
        foreach ($srcValues as $srcValue) {
            $normalizedSrc = TextNormalizer::normalizeText($srcValue->getSrcValue());
            $normalizedRc  = TextNormalizer::normalizeText($rcValue);
            $srcValue->setIsEqual($normalizedSrc === $normalizedRc);
        }

        return $this;
    }
}
