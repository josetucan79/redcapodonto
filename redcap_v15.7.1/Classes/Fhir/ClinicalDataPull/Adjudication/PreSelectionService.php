<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication;

use DateTime;
use Project;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs\FieldDataDTO;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs\FieldSourceValueDTO;

class PreSelectionService
{

    const CRITERION_UNSUPPORTED = 'UNSUPPORTED';
    const CRITERION_MIN = 'MIN';
    const CRITERION_MAX = 'MAX';
    const CRITERION_NEAR = 'NEAR';
    const CRITERION_FIRST = 'FIRST';
    const CRITERION_LAST = 'LAST';


    private $project;
    // Constructor
    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    private function getEqualSrcValue(array $srcValues)
    {
        foreach ($srcValues as $srcValue) {
            if ($srcValue->getIsEqual() === true) {
                return $srcValue; // Return the first matching srcValue
            }
        }
        return null; // No matching srcValue found
    }

    
    
    public function applyPreSelection($data, $fieldMappings)
    {
        // Build a mapping configuration from $fieldMappings
        $mappingConfigs = [];
        foreach ($fieldMappings as $srcField => $eventMappings) {
            foreach ($eventMappings as $eventId => $fieldMapping) {
                foreach ($fieldMapping as $rcField => $mapping) {
                    $mappingConfigs[$eventId][$rcField] = $mapping;
                }
            }
        }

        // Apply pre-selection logic using the mapping configurations
        foreach ($data as $eventId => &$fields) {
            foreach ($fields as $rcField => &$instances) {
                foreach ($instances as $instance => &$fieldData) {
                    /** @var FieldDataDTO $fieldData */
                    $srcValues = $fieldData->getSrcValues();

                    // Skip if no source values or no mapping configuration specified
                    if (empty($srcValues) || !isset($mappingConfigs[$eventId][$rcField])) {
                        continue;
                    }

                    $equalSrcValue = $this->getEqualSrcValue($srcValues);

                    // If any srcValue is equal, set the selection to this $srcValue and skip criterion
                    if ($equalSrcValue !== null) {
                        $fieldData->setSelection($equalSrcValue);
                        continue; // Move to the next $fieldData
                    }

                    $criterion = strtoupper($fieldData->getPreselect());

                    // Determine the reference timestamp for 'NEAREST', 'FIRST', 'LAST'
                    $rcTimestamp = $fieldData->getRcTimestamp();
                    $referenceTimestamp = $rcTimestamp ?? null;

                    switch ($criterion) {
                        case self::CRITERION_MIN:
                            $selectedValue = $this->selectMinValue($srcValues);
                            break;
                        case self::CRITERION_MAX:
                            $selectedValue = $this->selectMaxValue($srcValues);
                            break;
                        case self::CRITERION_NEAR:
                            $selectedValue = $this->selectNearestTimestamp($srcValues, $referenceTimestamp);
                            break;
                        case self::CRITERION_LAST:
                            $selectedValue = $this->selectLastValue($srcValues);
                            break;
                        case self::CRITERION_FIRST:
                            $selectedValue = $this->selectFirstValue($srcValues);
                            break;
                        default:
                            $selectedValue = null;
                    }

                    // Mark the selected source value
                    if ($selectedValue !== null) {
                        $fieldData->setSelection($selectedValue);
                    }else {
                        // select the first if no selection was made
                        if($srcValues[0] ?? false) $fieldData->setSelection($srcValues[0]);
                    }
                }
            }
        }

        return $data;
    }

    /**
     *
     * @param FieldSourceValueDTO[] $srcValues
     * @return FieldSourceValueDTO|null
     */
    private function selectMinValue($srcValues)
    {
        if (empty($srcValues)) return null;

        $minItem = null;
        $minValue = PHP_INT_MAX; // Start with the maximum possible value

        foreach ($srcValues as $item) {
            if ($item->getIsInvalid()) continue;
            $value = $item->getSrcValue();

            // Skip items with null values
            if ($value === null) continue;

            // Check if the current value is smaller than the current minimum
            if ($value < $minValue) {
                $minValue = $value;
                $minItem = $item;
            }
        }
        return $minItem;
    }


    private function selectMaxValue($srcValues)
    {
        if (empty($srcValues)) return null;

        $maxItem = null;
        $maxValue = PHP_INT_MIN; // Start with the minimum possible value

        foreach ($srcValues as $item) {
            if ($item->getIsInvalid()) continue;
            $value = $item->getSrcValue();

            // Skip items with null values
            if ($value === null) continue;

            // Check if the current value is larger than the current maximum
            if ($value > $maxValue) {
                $maxValue = $value;
                $maxItem = $item;
            }
        }
        return $maxItem;
    }


    /**
     *
     * @param FieldSourceValueDTO[] $srcValues
     * @return FieldSourceValueDTO|null
     */
    private function selectFirstValue($srcValues)
    {
        if (empty($srcValues)) return null;
        
        $firstItem = null;
        $firstTimestamp = null;
        
        foreach ($srcValues as $item) {
            if ($item->getIsInvalid()) continue;
            $srcTimestamp = $item->getSrcTimestamp();
    
            // Skip items with null timestamps
            if ($srcTimestamp === null) continue;
    
            // Compare timestamps to find the earliest one
            if ($firstTimestamp === null || $srcTimestamp < $firstTimestamp) {
                $firstTimestamp = $srcTimestamp;
                $firstItem = $item;
            }
        }
        return $firstItem;
    }

    /**
     *
     * @param FieldSourceValueDTO[] $srcValues
     * @return FieldSourceValueDTO|null
     */
    private function selectLastValue($srcValues)
    {
        if (empty($srcValues)) return null;
    
        $lastItem = null;
        $lastTimestamp = null;
    
        foreach ($srcValues as $item) {
            if ($item->getIsInvalid()) continue;
            $srcTimestamp = $item->getSrcTimestamp();
    
            // Skip items with null timestamps
            if ($srcTimestamp === null) continue;
    
            // Compare timestamps to find the latest one
            if ($lastTimestamp === null || $srcTimestamp > $lastTimestamp) {
                $lastTimestamp = $srcTimestamp;
                $lastItem = $item;
            }
        }
        return $lastItem;
    }
    
    /**
     *
     * @param FieldSourceValueDTO[] $srcValues
     * @param DateTime|null $referenceTimestamp
     * @return FieldSourceValueDTO|null
     */
    private function selectNearestTimestamp($srcValues, $referenceTimestamp)
    {
        // Return null if the array is empty or referenceTimestamp is null
        if (empty($srcValues) || $referenceTimestamp === null) {
            return null;
        }

        // Initialize variables to track the nearest item and its difference
        $nearestItem = null;
        $smallestDifference = PHP_INT_MAX;

        foreach ($srcValues as $item) {
            if($item->getIsInvalid()) continue;
            // Get the source timestamp from the item
            $srcTimestamp = $item->getSrcTimestamp();

            // Skip items with null timestamps
            if ($srcTimestamp === null) continue;

            // Calculate the absolute difference between timestamps
            $difference = abs($referenceTimestamp->getTimestamp() - $srcTimestamp->getTimestamp());

            // Update the nearest item if the difference is smaller
            if ($difference < $smallestDifference) {
                $smallestDifference = $difference;
                $nearestItem = $item;
            }
        }

        return $nearestItem;
    }
    

    public function processCheckboxFields($data)
    {
        foreach ($data as $eventId => &$fields) {
            foreach ($fields as $rcField => &$instances) {
                foreach ($instances as $instance => &$fieldData) {
                    if ($this->isCheckboxField($rcField)) {
                        $srcValues = &$fieldData['src_values'];
                        $selectedOptions = [];

                        foreach ($srcValues as &$srcValueData) {
                            if (!isset($srcValueData['invalid']) || !$srcValueData['invalid']) {
                                $options = explode(',', $srcValueData['src_value']);
                                foreach ($options as $option) {
                                    $option = trim($option);
                                    if ($option !== '') {
                                        $selectedOptions[$option] = true;
                                    }
                                }
                            }
                        }

                        $fieldData['selected_options'] = array_keys($selectedOptions);
                    }
                }
            }
        }

        return $data;
    }

    private function isCheckboxField($rcField)
    {
        return isset($this->project->metadata[$rcField]) && $this->project->metadata[$rcField]['element_type'] === 'checkbox';
    }

}
