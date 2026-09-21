<?php
namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors;

use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;

class PatientSelector extends AbstractTokenSelector
{
    public function __construct() {}

    protected function handle(TokenSelectionContext $context): void
    {
        $patientId = $context->getPatientId();
        $tokens = $context->getTokens(); // Assume tokens are already set in context

        $orderedTokens = $this->sortByPatient($tokens, $patientId);
        $context->setTokens($orderedTokens);
    }

    /**
     *
     * @param FhirTokenDTO[] $tokens
     * @param string $patient
     * @return FhirTokenDTO[]
     */
    private function sortByPatient($tokens, $patient) {
        usort($tokens, function(FhirTokenDTO $a, FhirTokenDTO $b)  use ($patient) {
            $priorityA = ($a->getPatient() === $patient) ? 0 : 1;
            $priorityB = ($b->getPatient() === $patient) ? 0 : 1;
    
            // Preserve original order for non-specific patients
            return $priorityA <=> $priorityB;
        });
        return $tokens;
    }
}
