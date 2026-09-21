<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication;

use DateTime;
use Language;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs\FieldDataDTO;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs\FieldSourceValueDTO;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\ValueObjects\ProcessingWarning;

class UserInterfaceService
{
    const INVALID_SRC_VALUE_LABEL       = 'invalidSrcValueLabel';
    const SAME_VALUE_LABEL              = 'sameSingleSrcValueLabel';
    const LOCKED_SRC_VALUE_LABEL        = 'lockedSrcValueLabel';
    const OUT_OF_RANGE_SRC_VALUE_LABEL  = 'outOfRangeSrcValueLabel';
    const DUPLICATE_MAPPING_WARNING_LABEL = 'duplicateMappingWarningLabel';

    /**
     * store html labels
     * 
     * @var array
     */
    private $labels = [];

    public function generateHtmlLabels()
    {
        // Generate predefined HTML snippets for various conditions
        $labels = [];
        // Invalid Source Value Label
        // Set HTML label for INVALID SOURCE VALUE
        $labels[self::INVALID_SRC_VALUE_LABEL] = $invalidLabel = '<span><i class="fas fa-triangle-exclamation fa-fw text-warning"></i> '.Language::tt('ws_08').'</span>';
        // Same Single Source Value Label
        // Set HTML label for if the REDCap VALUE = SOURCE VALUE when only ONE source value exists
        $labels[self::SAME_VALUE_LABEL] = $existingValueLabel = '<span class="text-success">&#x2714; '.Language::tt('ws_14').'</span>';
        // Locked Source Value Label
        // Set HTML label for if the REDCap field is on a LOCKED form/event
        $labels[self::LOCKED_SRC_VALUE_LABEL] = $lockedLabel = '<span><i class="fas fa-lock fa-fw"></i> '.Language::tt('esignature_29').'</span>';
        // Out of Range Source Value Label
        // Set HTML label for if the REDCap field value is out of range
        $labels[self::OUT_OF_RANGE_SRC_VALUE_LABEL] = $outOfRangeLabel = '<span><i class="fas fa-triangle-exclamation fa-fw text-warning"></i> '.Language::tt('dataqueries_58').'</span>';
        // Duplicate Mapping Warning Label
        // Set HTML label for when a REDCap field is mapped to multiple FHIR fields
        $labels[self::DUPLICATE_MAPPING_WARNING_LABEL] = '<span class="duplicate-mapping-warning"><i class="fas fa-exclamation-triangle fa-fw text-warning"></i> Multiple mappings</span>';

        return $labels;
    }

    public function getHtmlLabels()
    {
        if(empty($this->labels)) {
            $this->labels = $this->generateHtmlLabels();
        }
        return $this->labels;
    }

    private function getColumnHeaders() {
        return [
            'Event',
            'Field',
            'REDCap Date/Time',
            'Source Date/Time',
            'REDCap Current Value',
            'Source Value',
            'Import?',
        ];
    }

    /**
     * Generates a string of space-separated HTML data attributes based on an array of attributes.
     *
     * This function takes an associative array and maps its keys and values to HTML data attributes:
     * - Boolean `true` values include the attribute without a value (e.g., `data-is-equal`).
     * - Boolean `false` values are excluded.
     * - Non-boolean values are included as `data-key="value"`.
     *
     * Each key in the resulting string is prefixed with "data-" and includes the corresponding value.
     * Special characters in the values are escaped to ensure valid HTML.
     *
     * @param array $attributes An associative array of attributes to include as data attributes.
     * @return string A string of space-separated HTML data attributes.
     */
    function generateDataAttributes(array $attributes): string {
        // Generate the HTML attributes string
        return implode(' ', array_filter(array_map(
            function ($key, $value) {
                if (is_bool($value)) {
                    // Add attribute without value if true
                    return $value ? sprintf('data-%s', $key) : null;
                }
                // Add attribute with value for non-boolean values
                return sprintf('data-%s="%s"', $key, htmlspecialchars((string)$value, ENT_QUOTES));
            },
            array_keys($attributes),
            $attributes
        )));
    }

    public function generateTableRows($data, $options)
    {
        // Generate HTML table rows for the adjudication table
        $rows = '';
        $currentHeader = null;
        $currentEventId = null;
        $total_columns = count($this->getColumnHeaders());

        foreach ($data as $compositeKey => $formAttributes) {
            // Extract event ID and form name from composite key
            $keyParts = explode('|', $compositeKey);
            if (count($keyParts) === 2) {
                [$eventId, $formName] = $keyParts;
            } else {
                // Fallback for backward compatibility
                $eventId = null;
                $formName = $compositeKey;
            }
            $formInfo = $formAttributes['info'] ?? null;
            $formData = $formAttributes['data'] ?? null;
            // check if we have valid data or continue loop
            if(!$formInfo || !$formData) continue;

            $isRepeatingForm = $formInfo['is_repeating'] ?? false;
            $event_id = $formInfo['event_id'] ?? null;
            $eventName = $formInfo['event_name'] ?? '';
            $form_label = $formInfo['form_label'] ?? '';

            // Add event header when event changes
            if ($currentEventId !== $event_id && !empty($eventName)) {
                $rows .= '<tr class="event-header">';
                $rows .= '<td colspan="' . $total_columns . '" class="bg-primary text-white fw-bold">';
                $rows .= '<i class="fas fa-calendar-days fa-fw"></i>' . htmlspecialchars($eventName);
                $rows .= '</td>';
                $rows .= '</tr>';
                $currentEventId = $event_id;
                // Reset form header tracking when event changes
                $currentHeader = null;
            }

            foreach ($formData as $instance => $fields) {
                $row_header = $form_label;
                if($isRepeatingForm) {
                    $row_header = "$form_label #$instance";
                }

                /** @var FieldDataDTO $fieldData */
                foreach ($fields as $fieldName => $fieldData) {
                    // $fieldLabel = htmlspecialchars($fieldData->getFieldLabel() ?? $fieldName ?? '');
                    $fieldLabel = strip_tags($fieldData->getFieldLabel() ?? $fieldName ?? '');
                    $rcValue = strip_tags($fieldData->getRcValue() ?? '');
                    $rcDisplay = strip_tags($fieldData->getDisplay() ?? '');
                    
                    // Generate warning display HTML
                    $warningHtml = '';
                    $warnings = $fieldData->getWarnings();
                    if (!empty($warnings)) {
                        $htmlLabels = $this->getHtmlLabels();
                        foreach ($warnings as $warning) {
                            if ($warning->getType() === ProcessingWarning::TYPE_DUPLICATE_MAPPING) {
                                $warningHtml .= '<div class="field-warning small text-warning mt-1" title="' . htmlspecialchars($warning->getMessage()) . '">';
                                $warningHtml .= $htmlLabels[self::DUPLICATE_MAPPING_WARNING_LABEL];
                                $warningHtml .= '</div>';
                            }
                        }
                    }
    
                    // Get the array of source values
                    $srcValues = $fieldData->getSrcValues();
                    $srcValueCount = count($srcValues);
    
                    // Determine the rowspan for the event name, field label, and rc_value cells
                    $rowspan = max($srcValueCount, 1);

                    $isFirstRow = true;
                    // $rows .= '<div style="display: contents;" >';
                    // Generate the first row for the current field (which also includes rowspan values for shared cells)
                    if ($srcValueCount > 0) {
                        // check if printing header is needed
                        if($row_header && $row_header!==$currentHeader) {
                            $rowHeaderDataAttributes = $this->generateDataAttributes([
                                'form-name' => $formName,
                                'instance' => $instance,
                            ]);
                            $currentHeader = $row_header;
                            $rows .= '<tr '.$rowHeaderDataAttributes.' >';
                            $rows .= '<td class="bg-dark-subtle fw-bold" colspan="' . $total_columns . '">' . htmlspecialchars($row_header) . '</td>';
                            $rows .= '</tr>';
                        }
                        // Process all entries
                        foreach ($srcValues as $srcValueData) {
                            $srcValue = strip_tags($srcValueData->getSrcValue() ?? '');
                            $srcDisplay = strip_tags($srcValueData->getDisplay() ?? '');
                            $rcTimestamp = $fieldData->getRcTimestamp() ?? '';
                            $formattedRcTimestamp = $rcTimestamp instanceof DateTime ? $rcTimestamp->format('Y-m-d H:i:s')  : '';
                            
                            $rcTemporalField = $fieldData->getTemporalField();
                            $srcTimestamp = $srcValueData->getSrcTimestamp();
                            $formattedSrcTimestamp = $srcTimestamp instanceof DateTime ? $srcTimestamp->format('Y-m-d H:i:s')  : '';

                            // generate data attributes for each row based on the srcValueData
                            $groupId = "{$event_id}-{$instance}-{$fieldName}";
                            $rowDataAttributes = $this->generateDataAttributes([
                                'is-equal' => $srcValueData->getIsEqual(),
                                'is-invalid' => $srcValueData->getIsInvalid(),
                                'is-locked' => $srcValueData->getIsLocked(),
                                'is-out-of-range' => $srcValueData->getIsOutOfRange(),
                                'first-row' => $isFirstRow,
                                'form-name' => $formName,
                                'instance' => $instance,
                                'multiple-value' => $srcValueCount > 1,
                                'group-id' => $groupId,
                            ]);
                            
                            if($isFirstRow) {
                                // Process the first source value
                                $rows .= '<tr '.$rowDataAttributes.'>';
                                $rows .= '<td rowspan="' . $rowspan . '">' . $eventName . '</td>';
                                $rows .= '<td data-field-label rowspan="' . $rowspan . '">'.
                                            '<span class="small text-muted d-block">'.$fieldName.'</span><span>' . $fieldLabel . '</span>' . $warningHtml .
                                        '</td>';
                                $rows .= '<td data-rc-time rowspan="' . $rowspan . '">';
                                $rows .= ($formattedRcTimestamp ? ('<span class="small text-muted d-block">'.$rcTemporalField.'</span>' . $formattedRcTimestamp) : '-');
                                $rows .= '</td>';
                                $rows .= '<td data-src-time>' . ($formattedSrcTimestamp ?: '-') . '</td>';
                                $rows .= '<td data-rc-value><div data-content>' . $rcDisplay . '</div></td>';
                                $rows .= '<td data-src-value><div data-content>' . $srcDisplay . '</div></td>';
                                $rows .= '<td data-selection>' . $this->generateSelectionColumn($event_id, $instance, $srcValueData, $fieldData) . '</td>';
                                $rows .= '</tr>';
                                $isFirstRow = false;
                                continue;
                            }
    
                            $rows .= '<tr '.$rowDataAttributes.'>';
                            // Only include the columns for source values and adjudication selection
                            $rows .= '<td data-src-time>' . ($formattedSrcTimestamp ?: '-') . '</td>';
                            $rows .= '<td data-rc-value><div data-content>' . $rcValue . '</div></td>';
                            $rows .= '<td data-src-value><div data-content>' . $srcValue . '</div></td>';
                            $rows .= '<td data-selection>' . $this->generateSelectionColumn($event_id, $instance, $srcValueData, $fieldData) . '</td>';
                            $rows .= '</tr>';
                        }
                    } else {
                        // No source values - generate empty row with colspan for source columns
                        $rows .= '<tr data-no-source>';
                        $rows .= '<td data-event>' . $eventName . '</td>';
                        $rows .= '<td data-field-label>' . $fieldLabel . $warningHtml . '</td>';
                        $rows .= '<td data-rc-time></td>';
                        $rows .= '<td data-src-time></td>';
                        $rows .= '<td data-rc-value><div data-content>' . $rcValue . '</div></td>';
                        $rows .= '<td data-src-value><div data-content></div></td>';
                        $rows .= '<td data-selection><span class="fst-italic text-muted">no values</span></td>';
                        $rows .= '</tr>';
                    }
                    // $rows .= '</div>';
                }
            }
        }

        return $rows;
    }

    // Helper function to generate selection column with buttons/checks
    private function generateSelectionColumn(int $event_id, int $instance, FieldSourceValueDTO $srcValue, FieldDataDTO $fieldData)
    {
        $mdId = $srcValue->getMdId();
        $value = $srcValue->getSrcValue();
        $fieldName  = htmlspecialchars($fieldData->getFieldName());
        $htmlLabels = $this->getHtmlLabels();

        $isLocked = $srcValue->getIsLocked();
        $isInvalid = $srcValue->getIsInvalid();
        $isExisting = $srcValue->getIsEqual();
        $isOutOfRange = $srcValue->getIsOutOfRange();
        
        $checkboxID = "$event_id-$fieldName-$instance";

        $selectionColumn = '';
        $hasStatusLabels = false;

        // Add all applicable status labels
        if ($isInvalid) {
            $selectionColumn .= '<div>' . $htmlLabels[self::INVALID_SRC_VALUE_LABEL] . '</div>';
            $hasStatusLabels = true;
        }
        if ($isOutOfRange) {
            $selectionColumn .= '<div>' . $htmlLabels[self::OUT_OF_RANGE_SRC_VALUE_LABEL] . '</div>';
            $hasStatusLabels = true;
        }
        if ($isLocked) {
            $selectionColumn .= '<div>' . $htmlLabels[self::LOCKED_SRC_VALUE_LABEL] . '</div>';
            $hasStatusLabels = true;
        }
        if ($isExisting) {
            $selectionColumn .= '<div>' . $htmlLabels[self::SAME_VALUE_LABEL] . '</div>';
            $hasStatusLabels = true;
        }

        // If no status labels were added, show the radio button for selection
        if (!$hasStatusLabels) {
            $selection = $fieldData->getSelection();
            $checked = ($selection && $selection->getMdId() === $mdId) ? 'checked' : '';
            // Checkbox for adjudication selection
            $converted = htmlspecialchars($value);
            $selectionColumn = <<<EOL
            <div class="form-check form-switch">
                <input data-adjudication-radio data-md-id="$mdId"
                    class="form-check-input" type="radio" name="$checkboxID" value="$converted" $checked >
            </div>
            EOL;
        }

        return $selectionColumn;
    }



    public function assembleHtmlInterface($tableRows, $additionalInfo)
    {
        ob_start();
        $currentForm = $additionalInfo['page'];
        $currentInstance = $additionalInfo['instance'];
        $itemCount = $additionalInfo['itemCount'];
        $lastFetchTime = $additionalInfo['lastFetchTime'] ?? 'never';
        $recordIdentifier = $additionalInfo['recordIdentifier'];
        $overallMetadata = $additionalInfo['overallMetadata'] ?? [];
        $totalExisting = $overallMetadata[FieldDataDTO::META_TOTAL_EQUAL] ?? 0;

        $mrn = ($recordIdentifier instanceof FieldDataDTO) ? $recordIdentifier->getRcValue() : null;
        $showText = Language::tt('global_84')." $totalExisting ".Language::tt('ws_145');
        $hideText = Language::tt('ws_144')." $totalExisting ".Language::tt('ws_146');
        // Assemble the full HTML interface
        ?>
        <form method="post" id="rtws_adjud_form">
            <div class="toolbar border rounded p-2 mb-2 bg-light">
                <div class="d-flex flex-column gap-2">
                    <div>
                        <i class="fas fa-user fa-fw"></i>
                        <span class="fw-bold">MRN:</span>
                        <span><?= $mrn ?></span>
                    </div>
                    <div>
                        <i class="fas fa-list fa-fw"></i>
                        <span class="fw-bold">New items:</span>
                        <span><?= htmlspecialchars($itemCount) ?></span>
                    </div>
                    <div>
                        <i class="fas fa-clock fa-fw"></i>
                        <span class="fw-bold">Last fetch time:</span>
                        <span><?= htmlspecialchars($lastFetchTime) ?></span>
                    </div>
                </div>

                <?php if($totalExisting > 0 || $currentForm): ?>
                <div class="d-flex flex-column gap-1">
                    <span class="fw-bold">Display options:</span>
                    <?php if($currentForm) : ?>
                    <div class="form-check form-switch mb-0">
                        <label for="display-forms-checkbox">Display only this form's items</label>
                        <input type="checkbox" class="form-check-input" id="display-forms-checkbox"
                        data-current-form="<?= $currentForm ?>"
                        data-current-instance="<?= $currentInstance ?>"
                        />
                    </div>
                    <?php endif; ?>
                    <?php if($totalExisting > 0) : ?>
                    <div class="form-check form-switch mb-0">
                        <label for="display-existing-checkbox"><?= $showText ?></label>
                        <input type="checkbox" class="form-check-input" id="display-existing-checkbox" />
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <table data-adjudication-table class="table table-bordered table-striped table-sm" style="width:100%;">
                    <thead class="sticky-top bg-white"><tr class="border">
                    <?php foreach ($this->getColumnHeaders() as $column) : ?>
                        <th><?= $column ?></th>
                    <?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                        <?= $tableRows ?>
                    </tbody>
                </table>
            </div>
        </form>
        <?php
        $output = ob_get_contents(); // stores buffer contents to the output variable
        ob_end_clean();  // clears buffer and closes buffering
        $output .= $this->getStyle();
        $output .= $this->getScript();
        return $output;
    }

    public function getStyle() {
        ob_start();
        ?>
        <style>
            /* [data-adjudication-table] td {
                max-height: 200px;
            } */
            [data-first-row] > td{
                /* Adds some shadow effect */
                /* box-shadow: 0 -1px 0 rgba(120, 120, 120, 1); */
                border-top: solid 1px rgb(0, 0, 0, .5);
            }
            [data-no-source] {
                display: none;
            }
            [data-src-value] {
                font-style: italic;
            }
            [data-rc-value] [data-content],
            [data-src-value] [data-content] {
                max-height: 200px;
                overflow-y: auto;
                position: relative;
            }
            /* Hide REDCap value content in non-first rows of multiple-value groups */
            [data-multiple-value]:not([data-first-row]) [data-rc-value] [data-content] {
                opacity: .5;
            }
            .field-warning {
                display: flex;
                align-items: center;
                font-size: 0.875rem;
            }
            .field-warning i {
                margin-right: 4px;
            }
            .duplicate-mapping-warning {
                color: #f59e0b !important;
                cursor: help;
            }
            #rtws_adjud_form .toolbar {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 10px;
            }
            .event-header td {
                font-size: 1.0rem;
                border-top: 2px solid #0d6efd !important;
            }
            .event-header + tr td {
                border-top: none !important;
            }
        </style>
        <?php
		$output = ob_get_contents(); // stores buffer contents to the output variable
		ob_end_clean();  // clears buffer and closes buffering
        return $output;
    }

    public function getScript() {
        ob_start();
        ?>
        <script type="module">
            // Function to get or create a checkbox
            const getOrCreateCheckbox = (selector, defaultChecked = false, defaultAttributes = {}) => {
                let element = document.querySelector(selector);
                if (!element) {
                element = document.createElement('input');
                element.type = 'checkbox';
                element.checked = defaultChecked;
                // Set any default attributes
                for (let attr in defaultAttributes) {
                    element.setAttribute(attr, defaultAttributes[attr]);
                }
                }
                return element;
            };
            const init = () => {
                const displayFormsCheckbox = getOrCreateCheckbox('#display-forms-checkbox', false, {
                    'data-current-form': null,
                    'data-current-instance': null
                });
                const displayExistingCheckbox = getOrCreateCheckbox('#display-existing-checkbox', false);

                // Get all groups that have at least one existing value
                const allRowsWithExistingData = document.querySelectorAll('[data-adjudication-table] tbody tr[data-is-equal]');
                const existingGroupIds = new Set();
                allRowsWithExistingData.forEach(row => {
                    const groupId = row.getAttribute('data-group-id');
                    if (groupId) existingGroupIds.add(groupId);
                });
                // Convert to array for length property
                const existingDataRows = Array.from(existingGroupIds).map(groupId => 
                    document.querySelector(`[data-adjudication-table] tbody tr[data-group-id="${groupId}"]`)
                ).filter(row => row !== null);
                const allRows = document.querySelectorAll('[data-adjudication-table] tbody tr:not([data-no-source])');

                // State variables to track the checkbox states
                let displayFormsChecked = displayFormsCheckbox.checked;
                let displayExistingChecked = displayExistingCheckbox.checked;

                const updateHiddenItemsCount = () => {
                    const currentForm = displayFormsCheckbox.getAttribute('data-current-form');
                    const currentInstance = displayFormsCheckbox.getAttribute('data-current-instance');
                    const label = document.querySelector('label[for="display-existing-checkbox"]');
                    
                    if (!label) return;

                    let existingItemsCount = 0;
                    
                    // Count existing data rows based on form filter state
                    if (!displayFormsChecked) {
                        // Form filter is OFF - count ALL existing groups across all forms
                        existingItemsCount = existingGroupIds.size;
                    } else {
                        // Form filter is ON - count only existing groups from current form
                        existingGroupIds.forEach(groupId => {
                            const row = document.querySelector(`[data-adjudication-table] tbody tr[data-group-id="${groupId}"]`);
                            if (row) {
                                const formName = row.getAttribute('data-form-name');
                                const instance = row.getAttribute('data-instance');
                                const isCurrentFormRow = (formName === currentForm) && (currentInstance == 0 || (instance === currentInstance));
                                
                                if (isCurrentFormRow) {
                                    existingItemsCount++;
                                }
                            }
                        });
                    }

                    // Update the label text with current count - always use "Show" text
                    const showText = `<?= Language::tt('global_84') ?> ${existingItemsCount} <?= Language::tt('ws_145') ?>`;
                    
                    label.textContent = showText;
                };

                const updateRowVisibility = () => {
                    const currentForm = displayFormsCheckbox.getAttribute('data-current-form');
                    const currentInstance = displayFormsCheckbox.getAttribute('data-current-instance');

                    allRows.forEach(row => {
                        const formName = row.getAttribute('data-form-name');
                        const instance = row.getAttribute('data-instance');
                        const groupId = row.getAttribute('data-group-id');
                        const isExistingDataGroup = existingGroupIds.has(groupId);
                        // here we check if we are in the current form and instance. in non-repeating forms, instance is 0
                        const isCurrentFormRow = (formName === currentForm) && (currentInstance == 0 || (instance === currentInstance));

                        let show = true;

                        // Apply the displayExistingCheckbox filter - hide entire group if it has existing data
                        if (!displayExistingChecked && isExistingDataGroup) {
                            show = false;
                        }

                        // Apply the displayFormsCheckbox filter
                        if (displayFormsChecked && !isCurrentFormRow) {
                            show = false;
                        }

                        // Set the display property based on the combined state
                        row.style.display = show ? '' : 'none';
                    });
                    
                    // Update the hidden items count after visibility changes
                    updateHiddenItemsCount();
                };

                if(displayFormsCheckbox) {
                    displayFormsCheckbox.addEventListener('change', () => {
                        displayFormsChecked = displayFormsCheckbox.checked;
                        updateRowVisibility();
                    });
                }

                if(displayExistingCheckbox) {
                    displayExistingCheckbox.addEventListener('change', () => {
                        displayExistingChecked = displayExistingCheckbox.checked;
                        updateRowVisibility();
                    });
                }

                // Initial call to set up the row visibility and hidden count
                updateRowVisibility();
            };

            init();
        </script>

        <script type="module">
            function toggleRadio(radio) {
                if(radio.checked) radio.checked = false
                else radio.checked = true
            }

            const radios = document.querySelectorAll('[data-adjudication-radio]');
            radios.forEach(radio => {
                radio.addEventListener('click', (e) => {
                    e.preventDefault()
                    requestAnimationFrame(() => {
                        // Delays the state change until the current event loop cycle finishes and the next repaint starts.
                        toggleRadio(radio)
                    })
                })
            })
        </script>
        <?php
		$output = ob_get_contents(); // stores buffer contents to the output variable
		ob_end_clean();  // clears buffer and closes buffering
        return $output;
    }

    public function createButton($label, $attributes)
    {
        // Generate HTML for a button
        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= htmlspecialchars($key) . '="' . htmlspecialchars($value) . '" ';
        }
        return '<button ' . $attrString . '>' . htmlspecialchars($label) . '</button>';
    }
}
