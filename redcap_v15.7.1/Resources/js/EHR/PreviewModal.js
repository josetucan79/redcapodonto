export class PreviewModal {
    constructor(modal, options = {}) {
        // Store the modal instance passed from outside
        this.modal = modal;

        // Configuration
        this.config = {
            sourceSystemName: options.sourceSystemName || 'External System',
            recordLabel: options.recordLabel || 'MRN',
            previewEndpoint: options.previewEndpoint || 'DynamicDataPull/preview.php',
            hasPreviewFields: options.hasPreviewFields !== false, // Default true
            
            // Button labels
            saveButtonLabel: 'Save record and fetch data',
            cancelButtonLabel: 'Cancel',
            
            // Custom handlers
            onLoadSuccess: options.onLoadSuccess || null,
            onLoadError: options.onLoadError || null,
            onSave: options.onSave || null,
            onCancel: options.onCancel || null,
            
            ...options
        };

        // State management
        this.state = {
            isLoading: false,
            currentIdentifier: null,
            hasPreviewFields: this.config.hasPreviewFields,
            data: null
        };
    }

    /**
     * Check if modal is currently open
     */
    get isOpen() {
        return this.modal?.dialog?.open || false;
    }

    /**
     * Generate the modal body content using Bootstrap 5.3 classes
     */
    generateModalBody() {
        const { hasPreviewFields, isLoading, currentIdentifier } = this.state;

        return `
            <div class="alert alert-success bg-light border-success-subtle p-3 rounded">
                <!-- Preview instructions (shown when preview fields are available) -->
                <div id="preview-instructions-with-fields" class="${hasPreviewFields ? '' : 'd-none'}">
                    <div class="mb-3">
                        <strong>REDCap is currently fetching preview data for this record.</strong> Once it has completed, review the preview data below to ensure that the values match the record you wish to pull from the external source system. If they are correct, click the Save button to save this value and automatically begin fetching data from the source system. If the data displayed below is not correct for the value you entered, click Cancel to change the value.
                    </div>
                </div>

                <!-- Instructions when there are NO preview fields -->
                <div id="preview-instructions-no-fields" class="${!hasPreviewFields ? 'mb-4' : 'd-none'}">
                    Now that you have entered a value for the Source Identifier Field, click the Save button below to save the value and automatically begin fetching data from the source system.
                </div>

                <!-- Div for loading/displaying preview field data -->
                <div id="rtws_idfield_new_record_preview_fields" class="${hasPreviewFields ? 'my-3' : 'd-none'}">
                    ${isLoading ? `
                        <div class="d-flex align-items-center text-secondary fw-bold">
                            <i class="fas fa-spinner fa-spin me-2"></i> 
                            <span>Fetching preview data for "<span id="rtws_idfield_new_record_preview_field_progress_id_value" class="text-primary">${currentIdentifier || ''}</span>"...</span>
                        </div>
                    ` : '<!-- Preview data will be loaded here -->'}
                </div>
            </div>
        `;
    }

    /**
     * Open the modal with specified identifier and options
     * Returns a Promise that resolves to the save result
     */
    async open(identifier, options = {}) {
        if (this.isOpen) {
            console.warn('Preview modal is already open');
            return false;
        }

        // Update state
        this.state.currentIdentifier = identifier;
        this.state.isLoading = this.state.hasPreviewFields; // Only show loading if we have preview fields

        // Generate modal title and content
        const modalTitle = this.state.hasPreviewFields ? 
            `Fetching preview data from ${this.config.sourceSystemName}` :
            'Save record and fetch data';
        
        const modalBody = this.generateModalBody();

        try {
            // Show modal with text-only button labels (HTML would be escaped)
            const modalPromise = this.modal.show({
                title: modalTitle,
                body: modalBody,
                okText: this.config.saveButtonLabel,
                cancelText: this.config.cancelButtonLabel,
                size: 'md'
            });

            // Add icons to buttons after modal is rendered (since textContent escapes HTML)
            setTimeout(() => {
                this.addIconsToButtons();
                
                // Start fetching preview data if needed
                if (this.state.hasPreviewFields) {
                    this.fetchPreviewData(identifier, options).catch(error => {
                        console.error('Error fetching preview data:', error);
                        this.showError('Failed to fetch preview data from ' + this.config.sourceSystemName + ': ' + error.message);
                    });
                }
            }, 100);

            // Wait for user action (true = Save clicked, false = Cancel clicked)
            const userConfirmed = await modalPromise;
            
            if (userConfirmed) {
                // User clicked Save - perform save operation
                return await this.handleSave(identifier);
            } else {
                // User clicked Cancel
                if (typeof this.config.onCancel === 'function') {
                    this.config.onCancel();
                }
                return false;
            }
        } catch (error) {
            console.error('Error in preview modal flow:', error);
            return false;
        }
    }

    /**
     * Handle the Save operation when user clicks Save button
     */
    async handleSave(identifier) {
        try {
            // Show saving progress by updating the modal footer button
            this.showSavingProgress();
            
            // Call custom save handler if provided
            if (typeof this.config.onSave === 'function') {
                await this.config.onSave(identifier);
                return true;
            } else {
                // Default behavior: replicate original form submission logic
                this.performDefaultSave();
                return true;
            }
        } catch (error) {
            console.error('Error during save:', error);
            this.hideSavingProgress();
            
            if (window.toaster) {
                window.toaster.error(`Save failed: ${error.message}`, { title: 'Error' });
            }
            return false;
        }
    }

    /**
     * Add FontAwesome icons to modal buttons (since Modal.setOkText uses textContent which escapes HTML)
     */
    addIconsToButtons() {
        const okButton = this.modal.dialog?.querySelector('[data-btn-ok]');
        if (okButton) {
            // Create icon element
            const icon = document.createElement('i');
            icon.className = 'fas fa-database me-2';
            
            // Clear current content and add icon + text
            okButton.textContent = this.config.saveButtonLabel;
            okButton.prepend(icon);
        }
    }

    /**
     * Show saving progress in the modal footer
     */
    showSavingProgress() {
        const okButton = this.modal.dialog?.querySelector('[data-btn-ok]');
        if (okButton) {
            // Create spinner icon
            const spinner = document.createElement('i');
            spinner.className = 'fas fa-spinner fa-spin me-2';
            
            // Update button content
            okButton.textContent = 'Saving and fetching...';
            okButton.prepend(spinner);
            okButton.disabled = true;
        }
    }

    /**
     * Hide saving progress in the modal footer
     */
    hideSavingProgress() {
        const okButton = this.modal.dialog?.querySelector('[data-btn-ok]');
        if (okButton) {
            // Restore original button content
            this.addIconsToButtons();
            okButton.disabled = false;
        }
    }

    /**
     * Perform default save operation - replicates the original form submission
     */
    performDefaultSave() {
        // Original functionality was:
        // appendHiddenInputToForm('scroll-top', $(window).scrollTop());
        // appendHiddenInputToForm('open-ddp', '1');
        // appendHiddenInputToForm('save-and-continue','1');
        // dataEntrySubmit(this);
        
        // Add hidden form inputs
        if (typeof window.appendHiddenInputToForm === 'function') {
            window.appendHiddenInputToForm('scroll-top', window.scrollY || document.documentElement.scrollTop);
            window.appendHiddenInputToForm('open-ddp', '1');
            window.appendHiddenInputToForm('save-and-continue', '1');
        }
        
        // Submit the form
        if (typeof window.dataEntrySubmit === 'function') {
            // Find a form button context
            const okButton = this.modal.dialog?.querySelector('[data-btn-ok]');
            window.dataEntrySubmit(okButton);
        } else {
            // Fallback: submit the main form
            const form = document.querySelector('form#form');
            if (form) {
                form.submit();
            }
        }
    }

    /**
     * Update the modal body content (refresh the display)
     */
    updateModalBody() {
        const bodyElement = this.modal.dialog?.querySelector('[data-body]');
        if (bodyElement) {
            bodyElement.innerHTML = this.generateModalBody();
        }
    }

    /**
     * Perform default save operation - replicates the original form submission
     */
    performDefaultSave() {
        // Original functionality was:
        // appendHiddenInputToForm('scroll-top', $(window).scrollTop());
        // appendHiddenInputToForm('open-ddp', '1');
        // appendHiddenInputToForm('save-and-continue','1');
        // dataEntrySubmit(this);
        
        // Add hidden form inputs
        if (typeof window.appendHiddenInputToForm === 'function') {
            window.appendHiddenInputToForm('scroll-top', $(window).scrollTop());
            window.appendHiddenInputToForm('open-ddp', '1');
            window.appendHiddenInputToForm('save-and-continue', '1');
        }
        
        // Submit the form
        if (typeof window.dataEntrySubmit === 'function') {
            // Find the form button context (the save button)
            const saveBtn = this.modal.dialog?.querySelector('#rtws_preview_save_btn');
            window.dataEntrySubmit(saveBtn);
        } else {
            // Fallback: submit the main form
            const form = document.querySelector('form#form');
            if (form) {
                form.submit();
            }
        }
    }

    /**
     * Fetch preview data from server using REDCap DynamicDataPull preview API
     */
    async fetchPreviewData(identifier, options = {}) {
        try {
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const pid = urlParams.get('pid');
            
            // Build preview URL
            const previewUrl = `${window.app_path_webroot}${this.config.previewEndpoint}?pid=${pid}`;
            
            // Make AJAX call to preview.php
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Accept': '*/*',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                },
                body: new URLSearchParams({
                    'source_id_value': identifier,
                    'redcap_csrf_token': window.redcap_csrf_token
                }),
                mode: 'cors',
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const htmlData = await response.text();
            
            // Update the preview fields div with the fetched data
            const previewFieldsDiv = this.modal.dialog?.querySelector('#rtws_idfield_new_record_preview_fields');
            
            if (previewFieldsDiv) {
                if (htmlData.trim()) {
                    // Replace the loading content with the actual preview data
                    previewFieldsDiv.innerHTML = htmlData;
                } else {
                    // Show "no data" message using Bootstrap classes
                    previewFieldsDiv.innerHTML = `
                        <div class="alert alert-warning d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <span>No preview data available for "${identifier}".</span>
                        </div>
                    `;
                }

                // Find and execute scripts in the loaded content if any
                this.executeScriptsInContent(previewFieldsDiv);
            }
            
            // Store the fetched data
            this.state.data = htmlData;
            this.state.isLoading = false;
            
            // Call success handler if provided
            if (typeof this.config.onLoadSuccess === 'function') {
                await this.config.onLoadSuccess(htmlData);
            }
            
        } catch (error) {
            console.error('Error fetching preview data:', error);
            this.showError('Failed to fetch preview data from ' + this.config.sourceSystemName + ': ' + error.message);
            
            // Call error handler if provided
            if (typeof this.config.onLoadError === 'function') {
                await this.config.onLoadError(error);
            }
        }
    }

    /**
     * Execute scripts found in loaded content
     */
    executeScriptsInContent(container) {
        const scripts = Array.from(container.querySelectorAll('script'));
        scripts.forEach(script => {
            const newScript = document.createElement('script');
            // Copy attributes
            for (const { name, value } of script.attributes) {
                newScript.setAttribute(name, value);
            }
            // Copy content
            newScript.textContent = script.textContent;
            // Replace the old script tag with the new one to trigger execution
            script.parentNode.replaceChild(newScript, script);
        });
    }

    /**
     * Show error message in the preview fields area using Bootstrap classes
     */
    showError(message) {
        const previewFieldsDiv = this.modal.dialog?.querySelector('#rtws_idfield_new_record_preview_fields');
        if (previewFieldsDiv) {
            previewFieldsDiv.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span>${message}</span>
                </div>
            `;
        }
    }

    /**
     * Get current state (useful for debugging and testing)
     */
    getState() {
        return { ...this.state };
    }

    /**
     * Update configuration
     */
    updateConfig(newConfig) {
        this.config = { ...this.config, ...newConfig };
    }
}