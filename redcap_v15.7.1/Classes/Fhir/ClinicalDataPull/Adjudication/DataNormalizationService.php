<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication;

use Project;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs\FieldDataDTO;

class DataNormalizationService
{

    private $project;

    public function __construct(Project $project) {
        $this->project = $project;
    }

    public function normalize(array $data): array
    {
        $normalizedData = [];

        // Iterate over the event IDs
        foreach ($data as $eventId => $fields) {
            foreach ($fields as $fieldName => $instances) {
                /** @var FieldDataDTO $metadata */
                foreach ($instances as $instanceNumber => $metadata) {

                    // Extract form name and form label from the metadata
                    $formName = $metadata->getFormName() ?? 'unknown_form';
                    $formLabel = $metadata->getFormLabel() ?? 'Unknown Label';
                    
                    // Create composite key to preserve data from multiple events
                    $compositeKey = "$eventId|$formName";
                    
                    // Create or update the form entry in the normalized data
                    if (!isset($normalizedData[$compositeKey])) {
                        $isRepeating = $this->project->isRepeatingForm($eventId, $formName);
                        $normalizedData[$compositeKey] = [
                            'info' => [
                                'event_id' => $eventId,
                                'event_name' => $this->getEventName($eventId),
                                'is_repeating' => $isRepeating,
                                'form_label' => $formLabel,
                            ],
                            'data' => []
                        ];
                    }

                    // Add metadata to the 'data' section under the field name
                    $normalizedData[$compositeKey]['data'][$instanceNumber][$fieldName] = $metadata;
                }
            }
        }

        return $normalizedData;
    }

    private function getEventName($event_id)
    {
        // Return the name of the event
        return $this->project->eventInfo[$event_id]['name_ext'] ?? '';
    }
}