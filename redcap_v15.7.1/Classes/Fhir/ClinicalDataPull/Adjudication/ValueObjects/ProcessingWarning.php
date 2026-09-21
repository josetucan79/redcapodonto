<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\ValueObjects;

class ProcessingWarning
{
    const TYPE_DUPLICATE_MAPPING = 'duplicate_mapping';
    
    private $type;
    private $eventId;
    private $fieldName;
    private $instance;
    private $context;
    
    private function __construct($type, $eventId, $fieldName, $instance, array $context)
    {
        $this->type = $type;
        $this->eventId = $eventId;
        $this->fieldName = $fieldName;
        $this->instance = $instance;
        $this->context = $context;
    }
    
    public static function duplicateMapping($eventId, $fieldName, $instance, $previousFhirField, $currentFhirField)
    {
        return new self(
            self::TYPE_DUPLICATE_MAPPING,
            $eventId,
            $fieldName,
            $instance,
            [
                'previous_fhir_field' => $previousFhirField,
                'current_fhir_field' => $currentFhirField
            ]
        );
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function getEventId()
    {
        return $this->eventId;
    }
    
    public function getFieldName(): string
    {
        return $this->fieldName;
    }
    
    public function getInstance()
    {
        return $this->instance;
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
    
    public function getMessage(): string
    {
        if ($this->type === self::TYPE_DUPLICATE_MAPPING) {
            return "Field {$this->fieldName} mapped to multiple FHIR fields: {$this->context['previous_fhir_field']} and {$this->context['current_fhir_field']}. Using {$this->context['current_fhir_field']}.";
        }
        return '';
    }
    
    public function equals(ProcessingWarning $other): bool
    {
        return $this->type === $other->type &&
               $this->eventId === $other->eventId &&
               $this->fieldName === $other->fieldName &&
               $this->instance === $other->instance &&
               $this->context === $other->context;
    }
    
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'event_id' => $this->eventId,
            'field_name' => $this->fieldName,
            'instance' => $this->instance,
            'message' => $this->getMessage(),
            'context' => $this->context
        ];
    }
}