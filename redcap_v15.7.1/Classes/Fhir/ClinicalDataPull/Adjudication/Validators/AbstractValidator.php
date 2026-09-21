<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\Validators;

use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs\FieldDataDTO;

abstract class AbstractValidator
{
    /**
     *
     * @var AbstractValidator|null
     */
    protected $nextValidator = null;

    /**
     *
     * @var FieldDataDTO
     */
    protected $validatedData = null;

    public function setNext(AbstractValidator $nextValidator): AbstractValidator
    {
        $this->nextValidator = $nextValidator;
        return $nextValidator;
    }

    public function handle($fieldData, $context)
    {
        // Set the initial data to be validated
        $this->validatedData = $fieldData;

        // Perform validation
        $this->validate($context);

        // Pass the data to the next validator in the chain if it exists
        if ($this->nextValidator) {
            return $this->nextValidator->handle($this->validatedData, $context);
        }

        return $this;
    }

    abstract public function validate($context);

    public function getValidatedData()
    {
        return $this->validatedData;
    }
}
