/**
 * AdjudicatedDataApplicator - Applies adjudicated data to form fields
 * 
 * Parses simplified data structure from saveAdjudicatedData response
 * and applies values to matching form fields using appropriate strategies
 * for different input types (text, textarea, checkbox, radio, select).
 */
export class AdjudicatedDataApplicator {
    constructor(options = {}) {
        // Auto-detect development environment
        const isDevelopment = window.location.hostname === 'localhost' || 
                             window.location.hostname.includes('redcap.test');
        
        this.config = {
            debug: options.debug !== undefined ? options.debug : isDevelopment,
            customSelectors: options.customSelectors || {}
        };
    }

    /**
     * Main entry point - apply adjudicated data to form fields
     * @param {Object} responseData - Full response from saveAdjudicatedData
     * @param {Object} contextFilter - Object with instance, event_id filters
     */
    apply(responseData, contextFilter = {}) {
        if (!responseData || !responseData.data || !Array.isArray(responseData.data)) {
            this.log('No data array found in response', responseData);
            return;
        }

        this.log('Applying adjudicated data', { responseData, contextFilter });

        // Get current context if not provided
        const context = contextFilter.instance || contextFilter.event_id ? 
                       contextFilter : this.getCurrentContext();

        // Filter data by context
        const filteredData = this.filterByContext(responseData.data, context);
        this.log(`Filtered ${responseData.data.length} items to ${filteredData.length} matching context`, filteredData);

        // Apply each field
        let appliedCount = 0;
        filteredData.forEach(item => {
            if (this.applyFieldData(item.field, item.data)) {
                appliedCount++;
            }
        });

        this.log(`Successfully applied ${appliedCount} of ${filteredData.length} fields`);
        
        // Trigger REDCap's calculation and branching logic after all fields are updated
        if (appliedCount > 0) {
            this.triggerREDCapUpdates();
        }
    }

    /**
     * Filter data array by current page context
     * @param {Array} dataArray - Array of data objects
     * @param {Object} context - Object with instance, event_id from URL params
     * @returns {Array} Filtered array
     */
    filterByContext(dataArray, context) {
        this.log('Filtering data by context:', { dataArray, context });
        
        const filtered = dataArray.filter(item => {
            // Convert both values to strings for comparison (URL params are always strings)
            const instanceMatch = !context.instance || String(item.instance) === String(context.instance);
            const eventMatch = !context.event_id || String(item.event_id) === String(context.event_id);
            
            this.log(`Item ${JSON.stringify(item)}: instanceMatch=${instanceMatch}, eventMatch=${eventMatch}`);
            
            return instanceMatch && eventMatch;
        });
        
        this.log(`Filtered result:`, filtered);
        return filtered;
    }

    /**
     * Apply data to a specific field using appropriate strategy
     * @param {string} field - Field name
     * @param {string} data - Data value to apply
     * @returns {boolean} Success status
     */
    applyFieldData(field, data) {
        this.log(`Attempting to apply field: ${field} = ${data}`);

        const element = this.getFieldElement(field);
        if (!element) {
            return false; // Warning already logged in getFieldElement
        }

        // Log element details
        this.log(`Found element for field "${field}":`, {
            tagName: element.tagName,
            type: element.type,
            name: element.name,
            id: element.id,
            currentValue: element.value,
            visible: !element.hidden && element.style.display !== 'none'
        });

        const inputType = this.detectREDCapFieldType(element, field);
        this.log(`Detected input type: ${inputType} for field: ${field}`);

        try {
            switch (inputType) {
                case 'redcap-checkbox':
                    return this.applyREDCapCheckbox(field, data);
                case 'redcap-radio':
                    return this.applyREDCapRadio(field, data);
                case 'autocomplete-select':
                    return this.applyAutocompleteSelect(element, data);
                case 'checkbox':
                    return this.applyCheckboxValue(element, data);
                case 'radio':
                    return this.applyRadioValue(field, data);
                case 'select':
                    return this.applySelectValue(element, data);
                case 'textarea':
                    return this.applyTextareaValue(element, data);
                case 'text':
                default:
                    return this.applyTextValue(element, data);
            }
        } catch (error) {
            console.warn(`AdjudicatedDataApplicator: Error applying field "${field}":`, error);
            return false;
        }
    }

    /**
     * Apply value to REDCap checkbox fields (dual input system)
     */
    applyREDCapCheckbox(fieldName, data) {
        this.log(`Applying REDCap checkbox value to ${fieldName}:`);
        this.log(`  - Checkbox value: ${data}`);
        
        // REDCap checkboxes have both hidden input and visible checkbox
        const checkboxInputName = `__chk__${fieldName}_RC_${data}`;
        
        // Update hidden input value
        const hiddenInput = document.querySelector(`[name="${checkboxInputName}"]`);
        if (hiddenInput) {
            hiddenInput.value = data;
            this.log(`  ✓ Updated hidden checkbox input: ${checkboxInputName} = ${data}`);
        } else {
            this.log(`  ✗ Hidden checkbox input not found: ${checkboxInputName}`);
        }
        
        // Update visible checkbox
        const visibleCheckbox = document.querySelector(`#id-${checkboxInputName}`);
        if (visibleCheckbox) {
            visibleCheckbox.checked = true;
            visibleCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
            this.log(`  ✓ Checked visible checkbox: #id-${checkboxInputName}`);
        } else {
            this.log(`  ✗ Visible checkbox not found: #id-${checkboxInputName}`);
        }
        
        return hiddenInput && visibleCheckbox;
    }

    /**
     * Apply value to REDCap radio fields (dual input system)
     */
    applyREDCapRadio(fieldName, data) {
        this.log(`Applying REDCap radio value to ${fieldName}:`);
        this.log(`  - Radio value: ${data}`);
        
        // Update hidden input
        const hiddenInput = document.querySelector(`[name="${fieldName}"]`);
        if (hiddenInput) {
            hiddenInput.value = data;
            this.log(`  ✓ Updated hidden radio input: ${fieldName} = ${data}`);
        } else {
            this.log(`  ✗ Hidden radio input not found: ${fieldName}`);
        }
        
        // Update visible radio button
        const radioButton = document.querySelector(`[name="${fieldName}___radio"][value="${data}"]`);
        if (radioButton) {
            radioButton.checked = true;
            radioButton.dispatchEvent(new Event('change', { bubbles: true }));
            this.log(`  ✓ Selected radio button: ${fieldName}___radio = ${data}`);
        } else {
            this.log(`  ✗ Radio button not found: ${fieldName}___radio with value ${data}`);
        }
        
        return hiddenInput && radioButton;
    }

    /**
     * Apply value to autocomplete select fields (select + autocomplete input)
     */
    applyAutocompleteSelect(element, data) {
        this.log(`Applying autocomplete select value to ${element.name}:`);
        this.log(`  - Select value: ${data}`);
        
        // Set the select value
        element.value = data;
        element.dispatchEvent(new Event('change', { bubbles: true }));
        
        // Find and update the corresponding autocomplete input
        const autocompleteInput = document.querySelector(`#rc-ac-input_${element.name}`);
        if (autocompleteInput) {
            const selectedOption = element.querySelector(`option[value="${data}"]`);
            if (selectedOption) {
                const optionText = selectedOption.textContent;
                autocompleteInput.value = optionText;
                autocompleteInput.dispatchEvent(new Event('input', { bubbles: true }));
                this.log(`  ✓ Updated autocomplete input: ${optionText}`);
            } else {
                this.log(`  ✗ No option found with value: ${data}`);
            }
        } else {
            this.log(`  - No autocomplete input found (this might be a regular select)`);
        }
        
        this.log(`Applied autocomplete select value to ${element.name}`);
        return true;
    }

    /**
     * Apply value to text input fields
     */
    applyTextValue(element, data) {
        element.value = data;
        element.dispatchEvent(new Event('change', { bubbles: true }));
        element.dispatchEvent(new Event('input', { bubbles: true }));
        this.log(`Applied text value to ${element.name || element.id}`);
        return true;
    }

    /**
     * Apply value to textarea fields (with TinyMCE support)
     */
    applyTextareaValue(element, data) {
        const oldValue = element.value;
        this.log(`Applying textarea value to ${element.name || element.id}:`);
        this.log(`  - Current value: "${oldValue}"`);
        this.log(`  - New value: "${data}"`);
        this.log(`  - Data length: ${data.length}`);
        
        // Check for TinyMCE editor
        if (window.tinymce && element.id) {
            const editor = tinymce.get(element.id);
            if (editor) {
                this.log(`  - TinyMCE editor found for ${element.id}`);
                const currentEditorContent = editor.getContent();
                this.log(`  - Current editor content: "${currentEditorContent}"`);
                
                // Update TinyMCE editor content
                editor.setContent(data);
                this.log(`  - TinyMCE setContent() called`);
                
                // Sync editor content back to textarea
                tinymce.triggerSave();
                this.log(`  - tinymce.triggerSave() called`);
                
                // Fire TinyMCE change event
                editor.fire('change');
                this.log(`  - TinyMCE change event fired`);
                
                // Verify the update
                const newEditorContent = editor.getContent();
                const newTextareaValue = element.value;
                this.log(`  - New editor content: "${newEditorContent}"`);
                this.log(`  - New textarea value: "${newTextareaValue}"`);
                this.log(`  - TinyMCE update successful: ${newEditorContent.includes(data) || newTextareaValue.includes(data)}`);
                
                this.log(`Applied textarea value via TinyMCE to ${element.name || element.id}`);
                return true;
            }
        }
        
        // Fallback for regular textareas (no TinyMCE editor found)
        this.log(`  - No TinyMCE editor found, using DOM approach`);
        element.value = data;
        
        const newValue = element.value;
        this.log(`  - Value after DOM update: "${newValue}"`);
        this.log(`  - DOM update successful: ${newValue === data}`);
        
        // Dispatch events to trigger any change handlers
        element.dispatchEvent(new Event('change', { bubbles: true }));
        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('blur', { bubbles: true }));
        
        this.log(`Applied textarea value via DOM to ${element.name || element.id} - Events dispatched`);
        return true;
    }

    /**
     * Apply value to checkbox fields (REDCap naming: __chk__field_name_RC_X)
     */
    applyCheckboxValue(element, data) {
        // REDCap checkboxes: data should be '1' for checked, '' or '0' for unchecked
        const shouldCheck = (data === '1' || data === 'checked' || data === true);
        element.checked = shouldCheck;
        element.dispatchEvent(new Event('change', { bubbles: true }));
        this.log(`Applied checkbox value to ${element.name || element.id}: ${shouldCheck}`);
        return true;
    }

    /**
     * Apply value to radio button groups (REDCap naming: field_name___radio)
     */
    applyRadioValue(fieldName, data) {
        const radioGroup = document.querySelectorAll(`input[name="${fieldName}___radio"][type="radio"]`);
        if (radioGroup.length === 0) {
            console.warn(`AdjudicatedDataApplicator: No radio buttons found for field "${fieldName}" (expected name: ${fieldName}___radio)`);
            return false;
        }

        let found = false;
        radioGroup.forEach(radio => {
            const shouldCheck = (radio.value === data);
            radio.checked = shouldCheck;
            if (shouldCheck) {
                radio.dispatchEvent(new Event('change', { bubbles: true }));
                found = true;
            }
        });

        if (found) {
            this.log(`Applied radio value to ${fieldName}___radio: ${data}`);
        } else {
            console.warn(`AdjudicatedDataApplicator: No radio option found with value "${data}" for field "${fieldName}___radio"`);
        }
        return found;
    }

    /**
     * Apply value to select fields
     */
    applySelectValue(element, data) {
        // Check if the option exists
        const option = element.querySelector(`option[value="${data}"]`);
        if (!option) {
            console.warn(`AdjudicatedDataApplicator: No option found with value "${data}" for select field "${element.name || element.id}"`);
            return false;
        }

        element.value = data;
        element.dispatchEvent(new Event('change', { bubbles: true }));
        this.log(`Applied select value to ${element.name || element.id}: ${data}`);
        return true;
    }

    /**
     * Find DOM element for field using REDCap naming conventions
     * @param {string} fieldName - Field name to search for
     * @returns {Element|null} Found element or null
     */
    getFieldElement(fieldName) {
        this.log(`Searching for field: "${fieldName}"`);
        
        // Check for custom selector first
        if (this.config.customSelectors[fieldName]) {
            const customSelector = this.config.customSelectors[fieldName];
            this.log(`  - Trying custom selector: ${customSelector}`);
            const element = document.querySelector(customSelector);
            if (element) {
                this.log(`  ✓ Found field "${fieldName}" using custom selector`);
                return element;
            }
            this.log(`  ✗ Custom selector failed`);
        }

        // Try REDCap naming patterns
        const selectors = [
            `[name="${fieldName}"]`,                    // Standard name (text, textarea, select)
            `[name="${fieldName}___radio"]`,            // Radio buttons
            `[name^="__chk__${fieldName}_RC_"]`,        // Checkboxes
        ];

        for (const selector of selectors) {
            try {
                this.log(`  - Trying selector: ${selector}`);
                const element = document.querySelector(selector);
                if (element) {
                    this.log(`  ✓ Found field "${fieldName}" using selector: ${selector}`);
                    return element;
                }
                this.log(`  ✗ No element found with selector: ${selector}`);
            } catch (error) {
                // Skip invalid selectors
                this.log(`  ✗ Invalid selector for field "${fieldName}": ${selector} - ${error.message}`);
            }
        }

        // Show console warning if field not found
        console.warn(`AdjudicatedDataApplicator: Field "${fieldName}" not found in DOM`);
        this.log(`  ✗ No element found for field: "${fieldName}"`);
        return null;
    }

    /**
     * Determine REDCap-specific field type for strategy selection
     * @param {Element} element - DOM element
     * @param {string} fieldName - Field name
     * @returns {string} Input type
     */
    detectREDCapFieldType(element, fieldName) {
        if (!element) return 'unknown';

        // Check for autocomplete select (has corresponding autocomplete input)
        if (element.tagName.toLowerCase() === 'select') {
            const autocompleteInput = document.querySelector(`#rc-ac-input_${fieldName}`);
            if (autocompleteInput) {
                this.log(`Detected autocomplete select for field: ${fieldName}`);
                return 'autocomplete-select';
            }
            return 'select';
        }

        // Check for REDCap checkbox pattern (name starts with __chk__)
        if (element.name && element.name.startsWith('__chk__')) {
            this.log(`Detected REDCap checkbox for field: ${fieldName}`);
            return 'redcap-checkbox';
        }

        // Check for REDCap radio (has both hidden input and ___radio input)
        const hiddenRadioInput = document.querySelector(`[name="${fieldName}"]:not([name*="___radio"])`);
        const radioInput = document.querySelector(`[name="${fieldName}___radio"]`);
        if (hiddenRadioInput && radioInput && element === hiddenRadioInput) {
            this.log(`Detected REDCap radio for field: ${fieldName}`);
            return 'redcap-radio';
        }

        // Fallback to standard detection
        return this.detectInputType(element);
    }

    /**
     * Standard input type detection (fallback)
     * @param {Element} element - DOM element
     * @returns {string} Input type
     */
    detectInputType(element) {
        if (!element) return 'unknown';

        if (element.type === 'checkbox') return 'checkbox';
        if (element.type === 'radio') return 'radio';
        if (element.tagName.toLowerCase() === 'select') return 'select';
        if (element.tagName.toLowerCase() === 'textarea') return 'textarea';
        
        return 'text'; // Default for input[type=text], input[type=email], etc.
    }

    /**
     * Extract current page context from URL parameters
     * @returns {Object} Context object with instance and event_id
     */
    getCurrentContext() {
        const urlParams = new URLSearchParams(window.location.search);
        const context = {
            instance: urlParams.get('instance') || '1',
            event_id: urlParams.get('event_id') || null
        };
        
        this.log('Current context:', context);
        return context;
    }

    /**
     * Debug logging helper
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[AdjudicatedDataApplicator]', ...args);
        }
    }

    /**
     * Update configuration
     * @param {Object} newConfig - Configuration updates
     */
    updateConfig(newConfig) {
        this.config = { ...this.config, ...newConfig };
    }

    /**
     * Get current configuration (useful for debugging)
     * @returns {Object} Current configuration
     */
    getConfig() {
        return { ...this.config };
    }

    /**
     * Trigger REDCap's built-in calculation and branching logic
     */
    triggerREDCapUpdates() {
        this.log('Triggering REDCap updates...');
        
        try {
            // Trigger calculations for any calculated fields
            if (window.calculate && typeof window.calculate === 'function') {
                this.log('  - Triggering REDCap calculate() function');
                window.calculate();
            } else {
                this.log('  - REDCap calculate() function not available');
            }
            
            // Trigger branching logic to show/hide fields
            if (window.doBranching && typeof window.doBranching === 'function') {
                this.log('  - Triggering REDCap doBranching() function');
                window.doBranching();
            } else {
                this.log('  - REDCap doBranching() function not available');
            }
            
            // Trigger form validation if available
            if (window.validate && typeof window.validate === 'function') {
                this.log('  - Triggering REDCap validate() function');
                window.validate();
            } else {
                this.log('  - REDCap validate() function not available');
            }
            
            this.log('REDCap updates completed');
            
        } catch (error) {
            console.warn('AdjudicatedDataApplicator: Error triggering REDCap functions:', error);
        }
    }
}