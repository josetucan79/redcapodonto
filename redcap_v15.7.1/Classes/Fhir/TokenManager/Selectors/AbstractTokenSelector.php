<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors;

use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;

abstract class AbstractTokenSelector implements TokenSelectorInterface
{
    /**
     * @var TokenSelectorInterface|null
     */
    private $nextHandler;

    /**
     * Set the next TokenSelector in the chain.
     *
     * @param TokenSelectorInterface $nextSelector
     * @return TokenSelectorInterface
     */
    public function setNext(TokenSelectorInterface $nextHandler): TokenSelectorInterface
    {
        $this->nextHandler = $nextHandler;
        return $nextHandler;
    }

    /**
     * Attempt to select and return a valid token using the provided context.
     *
     * @param TokenSelectionContext $context The context containing FhirTokenManager and users list.
     * @return FhirTokenDTO[] Returns a valid token DTO or null if none found.
     */
    public function selectToken(TokenSelectionContext $context): array
    {
        $this->handle($context);

        if ($this->nextHandler) {
            $this->nextHandler->selectToken($context);
        }

        return $context->getTokens();
    }

    /**
     * Handle the token selection logic.
     *
     * @param TokenSelectionContext $context
     * @return void
     */
    abstract protected function handle(TokenSelectionContext $context): void;

}
