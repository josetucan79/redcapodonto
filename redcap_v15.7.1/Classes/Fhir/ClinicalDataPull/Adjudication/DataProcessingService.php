<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication;

use Project;
use DateTime;
use Exception;
use DynamicDataPull;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs\FieldDataDTO;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\ValueObjects\ProcessingWarning;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\Validators\EqualityValidator;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\Validators\LockedFormValidator;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\Validators\NumberValidator;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\Validators\RangeValidator;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\Validators\RegexValidator;

class DataProcessingService
{
    private $project;
    private $fhirMetadata;
    /**
     *
     * @var FieldDataDTO
     */
    private $recordIdentifierField;

    /**
     * track all created FieldDataDTOs
     *
     * @var FieldDataDTO[]
     */
    private $fieldDataDtoList = [];

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /* private function extractRepeatingForms($recordData, &$repeat_instances=[]) {
        $normalized = [];
        $repeat_instances = $recordData['repeat_instances'] ?? [];
        unset($recordData['repeat_instances']);
        foreach ($recordData as $event => $fields) {
            $normalized[$event][''][] = $fields;
            $repeatingForms = $repeat_instances[$event] ?? null;
            if($repeatingForms) $normalized[$event] = $normalized[$event] + $repeatingForms;
        }
        return $normalized;
    } */

    private function getFhirMetadata() {
        if(!$this->fhirMetadata) {
            $fhirMetadata = DynamicDataPull::getFhirMetadataSource($this->project->project_id);
            $this->fhirMetadata = $fhirMetadata->getList();
        }
        return $this->fhirMetadata;
    }

    private function getFieldMetadataInfo($field) {
        $fhirMetadata = $this->getFhirMetadata();
        return $fhirMetadata[$field] ?? [];
    }
    
    public function mergeData($redcapData, $sourceData, $mappings, $dayOffset, $dayOffsetPlusMinus)
    {
        $this->recordIdentifierField = null;
        $mergedData = [];
        $this->fieldDataDtoList = [];

        foreach ($mappings as $srcField => $eventAttr) {
            foreach ($eventAttr as $eventId => $fieldAttr) {
                foreach ($fieldAttr as $rcField => $attr) {

                    // Get REDCap value
                    $rcValues = $this->getRedcapValues($redcapData, $rcField, $eventId, $isRepeating);
                    // Get REDCap timestamp if temporal field
                    $rcTimestamps = [];
                    if ($attr['temporal_field']) {
                        $rcTimestamps = $this->getRedcapValues($redcapData, $attr['temporal_field'], $eventId);
                    }
                    foreach ($rcValues as $index => $rcValue) {
                        $instance = $index + 1; // adjusted because instance number should start from 1
                        $existingDto = null;
                        $existingFhirKey = null;
                        if(isset($mergedData[$eventId][$rcField][$instance]) && ($mergedData[$eventId][$rcField][$instance] instanceof FieldDataDTO)) {
                            $existingDto = $mergedData[$eventId][$rcField][$instance];
                            $existingFhirKey = $existingDto->getFhirKey();
                        }
                        
                        // Initialize merged data structure using FieldDataDTO
                        $fieldDataDto = new FieldDataDTO([
                            'fhir_key'          => $srcField,
                            'temporal_field'    => $attr['temporal_field'] ?? null,
                            'preselect'         => $attr['preselect'] ?? null,
                        ]);
                        
                        // Add warning if this is a duplicate mapping
                        if($existingDto && $existingFhirKey) {
                            $warning = ProcessingWarning::duplicateMapping(
                                $eventId,
                                $rcField,
                                $instance,
                                $existingFhirKey,
                                $srcField
                            );
                            $fieldDataDto->addWarning($warning);
                        }
                        
                        $mergedData[$eventId][$rcField][$instance] = $fieldDataDto;

                        // Get form name and label
                        $form_label = $this->getFormLabelForField($eventId, $rcField, $form_name);
                        $fieldDataDto->setFieldName($rcField);
                        $fieldDataDto->setFormName($form_name);
                        $fieldDataDto->setFormLabel($form_label);

                        // check if multiple choice
                        if ($this->project->isMultipleChoice($rcField)) {
                            $choices = parseEnum($this->project->metadata[$rcField]['element_enum']);
                            $fieldDataDto->setIsMultipleChoice(true);
                            $fieldDataDto->setMultipleChoices($choices);
                            // $fieldDataDto->setDisplay(($choices[$rcValue] ?? '') . "($rcValue)");
                        } else {
                            $fieldDataDto->setIsMultipleChoice(false);
                            $fieldDataDto->setMultipleChoices([]);
                        }
                        $fieldDataDto->setFieldLabel($this->project->metadata[$rcField]['element_label'] ?? '');
                    
                    
                        $fieldDataDto->setRcValue($rcValue);
                        // rc timestamp
                        $rcTimestamp = $rcTimestamps[$index] ?? null;
                        // collect metadata info from the metadata source (mapping configuration file)
                        $metadataInfo = $this->getFieldMetadataInfo($srcField);
                        $fieldDataDto->setFhirMetadata($metadataInfo);
                        $fieldDataDto->setIsTemporal($isTemporal = $metadataInfo['temporal'] ?? false);

                        $fieldDataDto->setRcTimestamp($rcTimestamp);
                        // Get source values
                        if (isset($sourceData[$srcField])) {
                            $srcValues = $sourceData[$srcField];
    
                            // Handle temporal fields
                            if ($isTemporal) {
                                $srcValues = $this->filterDataByDateRange(
                                    [$srcField => $srcValues],
                                    $rcTimestamp,
                                    $dayOffset,
                                    $dayOffsetPlusMinus
                                )[$srcField] ?? [];
                            }
                            $fieldDataDto->setSrcValues($srcValues);
                        }
                        $this->fieldDataDtoList[] = $fieldDataDto;

                        // Detect and store record identifier if not already set
                        $this->detectAndStoreRecordIdentifier($attr, $fieldDataDto);
                    }
                }
            }
        }

        return $mergedData;
    }

    /**
     *
     * @param array $attr
     * @param FieldDataDTO $fieldDataDto
     * @return void
     */
    private function detectAndStoreRecordIdentifier($attr, FieldDataDTO $fieldDataDto)
    {
        // Detect and store record identifier if not already set
        if (!$this->recordIdentifierField) {
            $isRecordIdentifier = $attr['is_record_identifier'] ?? false;
            if (!$isRecordIdentifier) {
                return; // Exit the function if it's not a record identifier
            }
            $this->recordIdentifierField = $fieldDataDto;
        }
    }

    public function calculateOverallMetadata($mergedData)
    {
        $metadataKeys = [
            FieldDataDTO::META_TOTAL_OUT_OF_RANGE,
            FieldDataDTO::META_TOTAL_EQUAL,
            FieldDataDTO::META_TOTAL_INVALID,
            FieldDataDTO::META_TOTAL_LOCKED,
        ];
        $overallMetadata = array_fill_keys($metadataKeys, 0);

        // Iterate over each form in the merged data
        foreach ($mergedData as $formName => $formInstances) {
            // Iterate over each instance in the form
            foreach ($formInstances as $instance => $fields) {
                // Iterate over each field in the instance
                /** @var FieldDataDTO $fieldDataDTO */
                foreach ($fields as $fieldName => $fieldDataDTO) {
                    // Calculate metadata for the current FieldDataDTO
                    $fieldMetadata = $fieldDataDTO->getMetadata();
                    // Accumulate the totals
                    foreach ($metadataKeys as $key) {
                        $overallMetadata[$key] += $fieldMetadata[$key];
                    }
                }
            }
        }

        return $overallMetadata;

    }


    /**
     * get the list of FieldDataDTO elements created during the mergeData process
     *
     * @return FieldDataDTO[]
     */
    public function getFieldDataDtoList(): array {
        return $this->fieldDataDtoList;
    }


    /**
     *
     * @return FieldDataDTO
     */
    public function getRecordIdentifierField() {
        return $this->recordIdentifierField ?? null;
    }

    private function getFormLabelForField($event_id, $rc_field, &$form_name=null) {
        $field_metadata = $this->project->metadata[$rc_field] ?? [];
        $form_name = $field_metadata['form_name'] ?? '';
        $form_label = $this->project->forms[$form_name]['menu'] ?? '';
        return $form_label;
    }

    private function getRedcapValues($redcapData, $rcField, $eventId, &$isRepeating=null)
    {
        $values = [];
        $form_name = $this->project->metadata[$rcField]['form_name'] ?? null;
        $isRepeating = $this->project->isRepeatingForm($eventId, $form_name);
        // Retrieve REDCap value for the field and event
        foreach ($redcapData as $recordId => $events) {
            if(!$isRepeating) {
                $value = isset($events[$eventId][$rcField]) ? $events[$eventId][$rcField] : null;
                $values = [$value];
            } else {
                // Handle repeating instances
                $instances = $events['repeat_instances'][$eventId][$form_name] ?? [];
                foreach ($instances as $instance => $fields) {
                    $values[] = $fields[$rcField] ?? null;
                }
            }
            
        }
        return $values;
    }

    public function filterDataByDateRange($sourceData, $rcTimestamp, $dayOffset, $dayOffsetPlusMinus)
    {
        $filteredData = [];

        foreach ($sourceData as $srcField => $values) {
            foreach ($values as $value) {
                if (isset($value['src_timestamp']) && $rcTimestamp) {
                    if ($this->dateInRange($value['src_timestamp'], $rcTimestamp, $dayOffset, $dayOffsetPlusMinus)) {
                        $filteredData[$srcField][] = $value;
                    }
                }
            }
        }

        return $filteredData;
    }

    private function dateInRange($srcTimestamp, $rcTimestamp, $dayOffset, $dayOffsetPlusMinus)
    {
        // Check if either timestamp is an empty string
        if (empty($srcTimestamp) || empty($rcTimestamp)) {
            return false;
        }
        // Create DateTime objects from the provided timestamps
        try {
            $srcDateTime = new DateTime($srcTimestamp);
            $rcDateTime = new DateTime($rcTimestamp);
        } catch (Exception $e) {
            // Handle invalid date format
            return false;
        }

        // Calculate the difference in seconds
        $diffSeconds = $srcDateTime->getTimestamp() - $rcDateTime->getTimestamp();

        // Convert the difference to days (including fractions)
        $diffDays = $diffSeconds / (60 * 60 * 24);

        // Adjust for plus/minus offset
        if ($dayOffsetPlusMinus === '+') {
            return $diffDays >= 0 && $diffDays <= $dayOffset;
        } elseif ($dayOffsetPlusMinus === '-') {
            return $diffDays <= 0 && abs($diffDays) <= $dayOffset;
        } elseif ($dayOffsetPlusMinus === '+-') {
            return abs($diffDays) <= $dayOffset;
        }

        // If an invalid option is provided for $dayOffsetPlusMinus, return false
        return false;
    }

    public function setupFieldValidations($rcFields)
    {
        $valTypes = getValTypes();
        $valFields = array();

        foreach ($rcFields as $rcField) {
            $valType = $this->project->metadata[$rcField]['element_validation_type'] ?? '';
            if ($valType != '') {
                // Convert validation type to YMD format if needed
                $valType = $this->convertLegacyValidationType($valType);
                $regex = $valTypes[$valType]['regex_php'] ?? false;
                if ($regex) {
                    $valFields[$rcField] = $regex;
                }
            }
        }

        return $valFields;
    }

    private function convertLegacyValidationType($valType)
    {
        // Implement any necessary conversion for legacy validation types
        if (strpos($valType, '_mdy') !== false || strpos($valType, '_dmy') !== false) {
            $valType = str_replace(array('_mdy', '_dmy'), '', $valType) . '_ymd';
        }
        return $valType;
    }

    public function getLockedFormsAndEvents($record)
    {
        // Retrieve information about locked forms/events for the given record
        $lockedFormsEvents = array();
        $sql = "SELECT event_id, form_name, instance FROM redcap_locking_data 
                WHERE project_id = ? AND record = ?";
        $q = db_query($sql, [$this->project->project_id, $record]);
        while ($row = db_fetch_assoc($q)) {
            // Set instance to 1 for non-repeating forms
            if (!$this->project->isRepeatingForm($row['event_id'], $row['form_name']) && !$this->project->isRepeatingEvent($row['event_id'])) {
                $row['instance'] = 1;
            }
            $lockedFormsEvents[$row['event_id']][$row['form_name']][$row['instance']] = true;
        }
        return $lockedFormsEvents;
    }

    public function filterEmptyValues($mergedData)
    {
        $isEmpty = fn($value) => empty($value) && $value !== '0' && $value !== 0;
        $filteredData = [];
        
        foreach ($mergedData as $eventId => $fields) {
            foreach ($fields as $rcField => $instances) {
                foreach ($instances as $instance => $fieldData) {
                    /** @var FieldDataDTO $fieldData */
                    $rcValue = $fieldData->getRcValue();
                    $srcValues = $fieldData->getSrcValues();
                    
                    // Check if REDCap value is empty
                    $rcEmpty = $isEmpty($rcValue);
                    
                    // Check if all source values are empty
                    $srcEmpty = true;
                    if (!empty($srcValues) && is_array($srcValues)) {
                        foreach ($srcValues as $srcValueDto) {
                            /** @var \Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs\FieldSourceValueDTO $srcValueDto */
                            $srcValue = $srcValueDto->getSrcValue();
                            if (!$isEmpty($srcValue)) {
                                $srcEmpty = false;
                                break;
                            }
                        }
                    }
                    
                    // Keep the entry if at least one has a value
                    if (!($rcEmpty && $srcEmpty)) {
                        $filteredData[$eventId][$rcField][$instance] = $fieldData;
                    }
                }
            }
        }
        
        return $filteredData;
    }

    public function validateAllChecks(&$mergedData, $validationPatterns, $lockedForms)
    {
        // Set up the chain of validators
        $validator = $lockedFormValidator = new LockedFormValidator($lockedForms);
        $regexValidator = new RegexValidator($validationPatterns);
        $numberValidator = new NumberValidator($this->project->metadata);
        $rangeValidator = new RangeValidator($this->project->metadata);
        $equalityValidator = new EqualityValidator();

        // Chain the validators
        $validator
            ->setNext($equalityValidator)
            ->setNext($regexValidator)
            ->setNext($numberValidator)
            ->setNext($rangeValidator);

        foreach ($mergedData as $eventId => &$fields) {
            foreach ($fields as $rcField => &$instances) {
                // Validate each instance
                foreach ($instances as $instance => &$fieldData) {
                    // Set up the validation context
                    $context = [
                        'event_id' => $eventId,
                        'rcField' => $rcField,
                        'instance' => $instance,
                    ];
                    $validator->handle($fieldData, $context);
                    $validatedData = $validator->getValidatedData();
                    $mergedData[$eventId][$rcField][$instance] = $validatedData;
                }
            }
        }

        return $mergedData;
    }


}
