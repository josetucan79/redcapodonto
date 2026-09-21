<?php
namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors;

use DateTime;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;

class ExpirationSelector extends AbstractTokenSelector
{
    public function __construct() {}

    protected function handle(TokenSelectionContext $context): void
    {
        $currentDate = new DateTime('now');
        $tokens = $context->getTokens(); // Assume tokens are already set in context

        foreach ($tokens as $tokenDTO) {
            $expiration = $tokenDTO->getExpiration();
            if($expiration && $expiration < $currentDate) {
                $tokenDTO->setStatus(FhirTokenDTO::STATUS_EXPIRED);
            }
        }

        $orderedTokens = $this->sortByExpiration($tokens, $currentDate);
        $context->setTokens($orderedTokens);
    }

    /**
     *
     * @param FhirTokenDTO[] $tokens
     * @param DateTime $currentDate
     * @return FhirTokenDTO[]
     */
    private function sortByExpiration($tokens, $currentDate) {
        usort($tokens, function (FhirTokenDTO $a, FhirTokenDTO $b) {
            // Convert expiration to DateTime or use a MAX date dynamically
            $expirationA = $a->getExpiration() ? $a->getExpiration() : new \DateTimeImmutable('@' . PHP_INT_MAX);
            $expirationB = $b->getExpiration() ? $b->getExpiration() : new \DateTimeImmutable('@' . PHP_INT_MAX);
    
            // Sort by expiration date in descending order (latest expiration first)
            return $expirationB <=> $expirationA;
        });
    
        return $tokens;
    }
}
