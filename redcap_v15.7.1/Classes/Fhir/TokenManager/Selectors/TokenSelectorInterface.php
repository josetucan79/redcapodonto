<?php
namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors;

use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;

interface TokenSelectorInterface
{
    /**
     * Attempt to select and return a valid token using the provided context.
     *
     * @param TokenSelectionContext $context The context containing FhirTokenManager and users list.
     * @return FhirTokenDTO[] Returns a valid token DTO or null if none found.
     */
    public function selectToken(TokenSelectionContext $context): array;

    public function setNext(TokenSelectorInterface $nextSelector): TokenSelectorInterface;
}