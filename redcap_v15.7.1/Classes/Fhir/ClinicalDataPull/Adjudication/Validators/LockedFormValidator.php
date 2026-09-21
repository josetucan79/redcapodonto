<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\Validators;

class LockedFormValidator extends AbstractValidator
{
    private $lockedForms;

    public function __construct($lockedForms)
    {
        $this->lockedForms = $lockedForms;
    }

    public function validate($context)
    {
        $eventId = $context['event_id'] ?? null;
        $instance = $context['instance'] ?? 0;
        $form_name = $this->validatedData->getFormName();
        $isLocked = $this->lockedForms[$eventId][$form_name][$instance] ?? false;

        $srcValues = $this->validatedData->getSrcValues();
        foreach ($srcValues as $srcValue) {
            $srcValue->setIsLocked($isLocked);
        }

        return $this;
    }
}

