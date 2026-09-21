<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication;

use Project;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs\FieldDataDTO;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Utilities\TextNormalizer;

class DataOrganizationService
{
    private $project;
    private $rcFieldsSameValue;
    private $rcAllFieldsEvents;
    private $mergedAdjudicatedSame;
    private $emptySrcValues;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function sortEventsAndFields($data)
    {
        $sortedData = [];

        // Get the ordered list of event IDs
        $eventOrder = array_keys($this->project->eventInfo);

        // Sort events based on the project event order
        foreach ($eventOrder as $eventId) {
            if (isset($data[$eventId])) {
                $fields = $data[$eventId];

                // Sort fields within the event based on field order
                uksort($fields, function ($a, $b) {
                    $orderA = $this->project->metadata[$a]['field_order'] ?? 0;
                    $orderB = $this->project->metadata[$b]['field_order'] ?? 0;
                    return $orderA - $orderB;
                });

                $sortedData[$eventId] = $fields;
            }
        }

        return $sortedData;
    }

    public function initializeFieldTracking($mergedData, $adjudicatedValues)
    {
        $this->rcFieldsSameValue = [];
        $this->rcAllFieldsEvents = [];
        $this->mergedAdjudicatedSame = [];
        $this->emptySrcValues = [];

        foreach ($mergedData as $eventId => $fields) {
            foreach ($fields as $rcField => $instances) {
                foreach ($instances as $instance => $fieldData) {
                    /** @var FieldDataDTO $fieldData */
                    $fieldKey = "$eventId-$rcField";
                    $this->rcAllFieldsEvents[] = $fieldKey;

                    // Check if the field value is the same
                    $rcValue = $fieldData->getRcValue();
                    $normalizedRcValue = TextNormalizer::normalizeText($rcValue);
                    $srcValues = $fieldData->getSrcValues();
                    if(empty($srcValues)) {
                        $this->emptySrcValues[] = $fieldKey;
                    };

                    $valueIsSame = false;
                    foreach ($srcValues as $srcValueData) {
                        $srcValue = $srcValueData->getSrcValue();
                        $normalizedSrcValue = TextNormalizer::normalizeText($srcValue);
                        if (
                            $normalizedSrcValue == $normalizedRcValue &&
                            !$srcValueData->getIsInvalid()
                        ) {
                            $valueIsSame = true;
                            break;
                        }
                    }

                    if ($valueIsSame) {
                        $this->rcFieldsSameValue[] = $fieldKey;
                    }

                    // Check if field has been adjudicated
                    if (in_array($fieldKey, $adjudicatedValues)) {
                        $this->mergedAdjudicatedSame[] = $fieldKey;
                    }
                }
            }
        }
    }

    public function getEmpty() { return $this->emptySrcValues; }
    public function getFieldsSameValue() { return $this->rcFieldsSameValue; }
    public function getAdjudicated() { return $this->mergedAdjudicatedSame; }

    /**
     * Helper method to subtract elements in sameOrAdjudicatedOrEmpty from rcAllFieldsEvents.
     *
     * @return array The difference between all fields and the same/adjudicated/empty fields.
     */
    public function getNewValues()
    {
        // Merge and get unique elements from rcFieldsSameValue, mergedAdjudicatedSame, and emptySrcValues
        $sameOrAdjudicatedOrEmpty = array_unique(array_merge(
            $this->rcFieldsSameValue,
            $this->mergedAdjudicatedSame,
            $this->emptySrcValues
        ));

        // Return the difference
        return array_diff($this->rcAllFieldsEvents, $sameOrAdjudicatedOrEmpty);
    }

    public function getNewValuesCount()
    {
        $newValues = $this->getNewValues();
        $itemCount = count($newValues);
        return $itemCount;
    }

    public function getFormName($event_id, $form_name)
    {
        $this->project->forms[$form_name]['menu'];
        $this->project->forms[$form_name]['menu'];
        // Return the display name of the form
        return $this->project->forms[$form_name]['menu'] ?? '';
    }

}
