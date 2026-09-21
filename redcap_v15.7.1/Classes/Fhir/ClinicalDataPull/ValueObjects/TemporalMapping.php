<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\ValueObjects;

class TemporalMapping
{
    private $fields;
    private $eventIds;

    public function __construct(array $fields = [], array $eventIds = [])
    {
        $this->fields = $fields;
        $this->eventIds = $eventIds;
    }

    public static function fromMappedFields(array $mappings): self
    {
        $temporalFields = [];
        $temporalEventIds = [];

        foreach ($mappings as $eventArray) {
            foreach ($eventArray as $eventId => $rcFields) {
                foreach ($rcFields as $rcFieldAttr) {
                    $temporalField = $rcFieldAttr['temporal_field'] ?? '';

                    if ($temporalField !== '') {
                        $temporalEventIds[] = $eventId;
                        $temporalFields[] = $temporalField;
                    }
                }
            }
        }

        // Remove duplicates
        $temporalFields = array_unique($temporalFields);
        $temporalEventIds = array_unique($temporalEventIds);

        return new self($temporalFields, $temporalEventIds);
    }


    public function getFields(): array
    {
        return $this->fields;
    }

    public function getEventIds(): array
    {
        return $this->eventIds;
    }
}
