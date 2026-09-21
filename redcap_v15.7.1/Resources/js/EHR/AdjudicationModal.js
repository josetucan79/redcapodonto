export class AdjudicationModal {
    constructor(modal, toaster, options = {}) {
        // Store the modal instance passed from outside
        this.modal = modal;
        this.toaster = toaster;
        
        // Store data applicator instance if provided
        this.dataApplicator = options.dataApplicator || null;

        // Configuration
        this.config = {
            sourceSystemName: options.sourceSystemName || 'External System',
            recordLabel: options.recordLabel || 'Record ID',
            dayOffsetMin: options.dayOffsetMin || 0.01,
            dayOffsetMax: options.dayOffsetMax || 365,
            dayOffset: options.dayOffset !== undefined ? options.dayOffset : (options.dayOffsetMin || 0.01),
            dayOffsetPlusMinus: options.dayOffsetPlusMinus || '+-',
            saveEndpoint: options.saveEndpoint || 'DynamicDataPull/save.php',
            instructionsText: options.instructionsText || 'Review the data retrieved from the source system and select the values you want to import into your REDCap project.',
            rangeText: options.rngeText || '0.01 (15 minutes) - 365 days)',

            // Save control options
            autoSave: options.autoSave !== false, // Default true
            reloadOnSuccess: options.reloadOnSuccess !== false, // Default true
            
            // Toast messages
            savingMessage: options.savingMessage || 'Saving adjudicated data...',
            successMessage: options.successMessage || 'Data saved successfully!',
            errorMessage: options.errorMessage || 'Save failed: {error}',
            
            // Timing
            successDelay: options.successDelay || 2000,
            
            // Custom handlers
            onSaveSuccess: options.onSaveSuccess || null,
            onSaveError: options.onSaveError || null,
            
            // UI control
            showSaveProgress: options.showSaveProgress !== false, // Default true
            
            ...options
        };

        // State management
        this.state = {
            isLoading: false,
            currentRecord: null,
            hasTemporalFields: false,
            dayOffset: this.config.dayOffset,
            dayOffsetPlusMinus: this.config.dayOffsetPlusMinus,
            data: null
        };

        // Store default footer structure for reuse
        this.defaultFooterHtml = null;

        // Event handlers binding
        this.handleRefreshClick = this.handleRefreshClick.bind(this);
        this.handleDayOffsetChange = this.handleDayOffsetChange.bind(this);
        this.handlePlusMinusChange = this.handlePlusMinusChange.bind(this);
    }

    /**
     * Check if modal is currently open
     */
    get isOpen() {
        return this.modal?.dialog?.open || false;
    }

    /**
     * Generate the toolbar HTML using template literals with Bootstrap classes
     * This makes it easy to modify and maintain the UI structure
     */
    generateToolbarTemplate() {
        const { sourceSystemName, recordLabel } = this.config;
        const { currentRecord, hasTemporalFields, dayOffset, dayOffsetPlusMinus } = this.state;

        return `
            <div class="card">
                <div class="card-body">
                    <!-- Record Context Section -->
                    <div class="d-flex align-items-end gap-2 mb-2">
                        <div class="d-flex flex-column gap-2 me-auto">
                            <!-- record label -->
                            <div class="d-flex align-items-start">
                                <span class="me-2">${recordLabel}:</span>
                                <span class="badge bg-danger fw-bold" id="current-record-display">${currentRecord || ''}</span>
                            </div>
                            
                            <!-- Temporal Controls Section -->
                            <div
                                id="temporal-controls" 
                                style="display: ${hasTemporalFields ? 'block' : 'none'}">
                                
                                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                    <label class="form-label mb-0 fw-medium">Pulling dates:</label>
                                    
                                    <select 
                                        class="form-select form-select-sm" 
                                        id="day-offset-plusminus"
                                        style="width: auto; min-width: 60px;"
                                        title="Select date offset direction">
                                        <option value="+-" ${dayOffsetPlusMinus === '+-' ? 'selected' : ''}>±</option>
                                        <option value="+" ${dayOffsetPlusMinus === '+' ? 'selected' : ''}>+</option>
                                        <option value="-" ${dayOffsetPlusMinus === '-' ? 'selected' : ''}>-</option>
                                    </select>
                                    
                                    <input 
                                        type="number" 
                                        class="form-control form-control-sm text-center" 
                                        id="day-offset-input"
                                        style="width: 80px;"
                                        min="${this.config.dayOffsetMin}" 
                                        max="${this.config.dayOffsetMax}" 
                                        step="0.01" 
                                        value="${dayOffset}"
                                        title="Enter number of days (${this.config.dayOffsetMin} - ${this.config.dayOffsetMax})">
                                    
                                    <span class="fw-medium">days</span>
                                </div>
                                
                                <div class="small fst-italic">
                                    Range: ${this.config.rangeText}
                                </div>
                            </div>
                        </div>

                        <div>
                            <button 
                                class="btn btn-primary btn-sm" 
                                id="refresh-data-btn"
                                type="button"
                                title="Refresh data from ${sourceSystemName}">
                                <i class="fas fa-database fa-fw me-2"></i>
                                <span>Refresh data from ${sourceSystemName}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Generate the complete modal content template (footer managed by modal system)
     */
    generateModalTemplate() {
        const { instructionsText } = this.config;
        const { isLoading } = this.state;

        return `
            <!-- Instructions Section -->
            <div class="mb-2">
                <details class="small">
                    <summary class="text-primary text-decoration-underline" style="cursor: pointer;">Show Instructions</summary>
                    <div class="alert alert-info mt-2 mb-0">
                        ${instructionsText}
                    </div>
                </details>
            </div>

            <!-- Toolbar -->
            ${this.generateToolbarTemplate()}

            <!-- Loading Indicator -->
            <div id="loading-container" class="${isLoading ? 'd-flex' : 'd-none'} flex-column align-items-center justify-content-center py-5" style="min-height: 200px;">
                <div class="mb-3">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                </div>
                <div class="text-muted">Fetching data from ${this.config.sourceSystemName}...</div>
            </div>

            <!-- Content Container -->
            <div id="content-container" class="${isLoading ? 'd-none' : 'd-block'}" style="max-height: 60vh; overflow-y: auto;">
                <!-- Dynamic content will be loaded here -->
            </div>
        `;
    }

    /**
     * Open the modal with specified record and options
     * Returns a Promise that resolves to result object: { confirmed: boolean, saved: boolean, error: Error|null }
     */
    async open(recordId, options = {}) {
        if (this.isOpen) {
            console.warn('Modal is already open');
            return { confirmed: false, saved: false, error: new Error('Modal already open') };
        }

        // Merge options with config for this session
        const sessionOptions = { ...this.config, ...options };

        // Update state
        this.state.currentRecord = recordId;
        this.state.hasTemporalFields = options.hasTemporalFields || false;
        this.state.dayOffset = options.dayOffset !== undefined ? options.dayOffset : this.config.dayOffset;
        this.state.dayOffsetPlusMinus = options.dayOffsetPlusMinus || this.config.dayOffsetPlusMinus;

        // Set loading state if auto-fetch is enabled
        if (options.autoFetch !== false) {
            this.state.isLoading = true;
        }

        // Generate modal content
        const modalContent = this.generateModalTemplate();
        const modalTitle = `Adjudicate data from ${this.config.sourceSystemName}`;

        // Show modal using the provided modal instance
        const modalPromise = this.modal.show({
            title: modalTitle,
            body: modalContent,
            okText: 'Save',
            cancelText: 'Cancel',
            size: 'xl'
        });

        // Wait a moment for modal to be rendered before attaching listeners
        setTimeout(() => {
            // Capture default footer HTML if not already stored
            if (!this.defaultFooterHtml) {
                const footer = this.modal.dialog?.querySelector('[data-footer]');
                if (footer) {
                    this.defaultFooterHtml = footer.innerHTML;
                }
            }
            this.attachEventListeners();
        }, 100);

        // Auto-fetch data if requested
        if (options.autoFetch !== false) {
            this.fetchData(recordId, options).catch(error => {
                console.error('Error fetching data:', error);
                this.showError('Failed to fetch data from ' + this.config.sourceSystemName);
            });
        }

        try {
            // Wait for user action (Save or Cancel)
            const confirmed = await modalPromise;
            
            if (confirmed && sessionOptions.autoSave !== false) {
                // User clicked Save and autoSave is enabled - handle save flow
                const saveResult = await this.handleSaveFlow(sessionOptions);
                return { 
                    confirmed: true, 
                    saved: saveResult.success, 
                    error: saveResult.error 
                };
            } else {
                // User cancelled or autoSave is disabled
                return { 
                    confirmed: confirmed, 
                    saved: false, 
                    error: null 
                };
            }
        } catch (error) {
            console.error('Error in modal flow:', error);
            return { 
                confirmed: false, 
                saved: false, 
                error: error 
            };
        }
    }

    /**
     * Close the modal
     */
    close(result = false) {
        if (!this.isOpen) {
            return;
        }

        // Reset state
        this.state.isLoading = false;
        this.state.currentRecord = null;
        this.state.data = null;

        // Close using the modal's built-in method
        this.modal.closeDialog(result);
    }

    /**
     * Show loading state
     */
    showLoading() {
        this.state.isLoading = true;
        
        const loadingContainer = this.modal.dialog?.querySelector('#loading-container');
        const contentContainer = this.modal.dialog?.querySelector('#content-container');
        
        if (loadingContainer) {
            loadingContainer.classList.remove('d-none');
            loadingContainer.classList.add('d-flex');
        }
        
        if (contentContainer) {
            contentContainer.classList.remove('d-block');
            contentContainer.classList.add('d-none');
        }
    }

    /**
     * Hide loading state
     */
    hideLoading() {
        this.state.isLoading = false;
        
        const loadingContainer = this.modal.dialog?.querySelector('#loading-container');
        const contentContainer = this.modal.dialog?.querySelector('#content-container');
        
        if (loadingContainer) {
            loadingContainer.classList.remove('d-flex');
            loadingContainer.classList.add('d-none');
        }
        
        if (contentContainer) {
            contentContainer.classList.remove('d-none');
            contentContainer.classList.add('d-block');
        }
    }

    /**
     * Show/hide temporal controls
     */
    toggleTemporalControls(show = true) {
        this.state.hasTemporalFields = show;
        const temporalControls = this.modal.dialog?.querySelector('#temporal-controls');
        if (temporalControls) {
            temporalControls.style.display = show ? 'block' : 'none';
        }
    }

    /**
     * Update toolbar with new state
     */
    updateToolbar() {
        // Find the toolbar container in the modal and update it
        const bodyElement = this.modal.dialog?.querySelector('[data-body]');
        if (bodyElement) {
            // Re-generate the entire modal content to update the toolbar
            bodyElement.innerHTML = this.generateModalTemplate();
            this.attachEventListeners();
        }
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Refresh button
        const refreshBtn = this.modal.dialog?.querySelector('#refresh-data-btn');
        if (refreshBtn) refreshBtn.addEventListener('click', this.handleRefreshClick);

        // Day offset controls
        const dayOffsetInput = this.modal.dialog?.querySelector('#day-offset-input');
        const plusMinusSelect = this.modal.dialog?.querySelector('#day-offset-plusminus');

        if (dayOffsetInput) dayOffsetInput.addEventListener('change', this.handleDayOffsetChange);
        if (plusMinusSelect) plusMinusSelect.addEventListener('change', this.handlePlusMinusChange);
    }

    /**
     * Event Handlers
     */

    async handleRefreshClick(event) {
        event.preventDefault();
        
        // Show confirmation if data exists
        if (this.state.data && !confirm('This will refresh the data and lose any current selections. Continue?')) {
            return;
        }

        await this.fetchData(this.state.currentRecord, { forceRefresh: true });
    }

    handleDayOffsetChange(event) {
        const value = parseFloat(event.target.value);
        
        if (value < this.config.dayOffsetMin || value > this.config.dayOffsetMax) {
            alert(`Day offset must be between ${this.config.dayOffsetMin} and ${this.config.dayOffsetMax}`);
            event.target.value = this.state.dayOffset;
            return;
        }

        this.state.dayOffset = value;
        this.onTemporalControlsChange();
    }

    handlePlusMinusChange(event) {
        this.state.dayOffsetPlusMinus = event.target.value;
        this.onTemporalControlsChange();
    }

    /**
     * Override points for customization
     */
    onTemporalControlsChange() {
        // Override in subclass or set as callback
    }

    /**
     * Fetch data from server using the actual REDCap DynamicDataPull API
     */
    async fetchData(recordId, options = {}) {
        this.showLoading();
        
        try {
            
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const pid = urlParams.get('pid');
            const instance = urlParams.get('instance') || '1';
            const page = urlParams.get('page') || '';
            const eventId = urlParams.get('event_id') || '';
            
            // Use the temporal controls values from state
            const dayOffset = this.state.dayOffset;
            const dayOffsetPlusMinus = encodeURIComponent(this.state.dayOffsetPlusMinus);
            
            // Build fetch URL with required parameters
            const fetchUrl = `${window.app_path_webroot}DynamicDataPull/fetch.php?pid=${pid}&event_id=${eventId}&instance=${instance}&page=${page}&day_offset=${dayOffset}&day_offset_plusminus=${dayOffsetPlusMinus}&output_html=1&record_exists=1&show_excluded=0&forceDataFetch=${options.forceRefresh ? '1' : '0'}`;
            
            // Make AJAX call to fetch.php
            const response = await fetch(fetchUrl, {
                method: 'POST',
                headers: {
                    'Accept': '*/*',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                },
                body: new URLSearchParams({
                    'record': recordId,
                    'redcap_csrf_token': window.redcap_csrf_token
                }),
                mode: 'cors',
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Update content container with fetched data
            const contentContainer = this.modal.dialog?.querySelector('#content-container');
            
            if (contentContainer) {
                contentContainer.innerHTML = `
                    <div >
                        ${data.html || '<div class="alert alert-warning">No data returned</div>'}
                    </div>
                    ${data.errors ? `
                        <div class="alert alert-warning mt-3">
                            <strong>Errors:</strong><br>
                            ${Array.isArray(data.errors) ? data.errors.join('<br>') : data.errors}
                        </div>
                    ` : ''}
                `;

                // Find and execute scripts in the loaded content
                this.executeScriptsInContent(contentContainer);
            }
            
            // Store the fetched data
            this.state.data = data;
            
            // Handle footer based on data availability
            if (data.item_count > 0) {
                // Show default footer for successful data fetch
                this.setDefaultFooter();
                /* const footer = this.modal.dialog?.querySelector('[data-footer]');
                if (footer) {
                    footer.style.display = 'flex';
                } */
            } else {
                // Show error footer for no data
                this.setErrorFooter();
            }
            
        } catch (error) {
            console.error('Error fetching data:', error);
            this.showError('Failed to fetch data from ' + this.config.sourceSystemName + ': ' + error.message);
            // Show error footer for fetch errors
            this.setErrorFooter();
        } finally {
            this.hideLoading();
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
     * Show error message
     */
    showError(message) {
        const contentContainer = this.modal.dialog?.querySelector('#content-container');
        if (contentContainer) {
            contentContainer.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span>${message}</span>
                </div>
            `;
        }
    }

    /**
     * Set footer to error state (single Ok button)
     */
    setErrorFooter() {
        const footer = this.modal.dialog?.querySelector('[data-footer]');
        if (footer) {
            footer.innerHTML = `<button type="button" data-btn-cancel>Ok</button>`;
            // Re-attach event listener for the Ok button
            const okButton = footer.querySelector('[data-btn-cancel]');
            if (okButton) {
                okButton.addEventListener('click', () => this.modal.closeDialog(false));
            }
        }
    }

    /**
     * Set footer to default state using stored HTML
     */
    setDefaultFooter() {
        const footer = this.modal.dialog?.querySelector('[data-footer]');
        if (footer && this.defaultFooterHtml) {
            footer.innerHTML = this.defaultFooterHtml;
            // Re-attach event listeners for both buttons
            const cancelButton = footer.querySelector('[data-btn-cancel]');
            const okButton = footer.querySelector('[data-btn-ok]');
            if (cancelButton) {
                cancelButton.addEventListener('click', () => this.modal.closeDialog(false));
            }
            if (okButton) {
                okButton.addEventListener('click', () => this.modal.closeDialog(true));
            }
        }
    }

    /**
     * Get current state (useful for debugging and testing)
     */
    getState() {
        return { ...this.state };
    }

    /**
     * Save adjudicated data by collecting checked inputs and sending POST request
     */
    async saveData() {
        try {
            // Collect all checked input elements from the modal
            const modalElement = this.modal.dialog;
            if (!modalElement) {
                throw new Error('Modal element not found');
            }

            const checkedInputs = modalElement.querySelectorAll('input:checked');
            if (checkedInputs.length === 0) {
                throw new Error('No selections made to save');
            }

            // Build form data structure matching save.php expectations
            const formData = new URLSearchParams();
            
            // Add record and CSRF token
            formData.append('redcap_csrf_token', window.redcap_csrf_token);
            
            // Process each checked input
            checkedInputs.forEach(input => {
                const mdId = input.getAttribute('data-md-id');
                const name = input.name;
                const value = input.value;
                
                if (mdId && name && value !== null) {
                    const key = `${mdId}-${name}`
                    // Build key in format expected by saveAdjudicatedData: md_id-event-field-instance
                    formData.append(key, value);
                }
            });

            // Get URL parameters for the save request
            const urlParams = new URLSearchParams(window.location.search);
            const pid = urlParams.get('pid');
            const eventId = urlParams.get('event_id') || '';
            const record = this.state.currentRecord;

            if (!record) {
                throw new Error('No record ID available for saving');
            }

            // Build save URL
            const saveUrl = `${window.app_path_webroot}${this.config.saveEndpoint || 'DynamicDataPull/save.php'}?pid=${pid}&record=${encodeURIComponent(record)}&event_id=${eventId}`;

            // Show loading feedback
            this.showLoading();

            // Send POST request to save endpoint
            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Accept': '*/*',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                },
                body: formData,
                mode: 'cors',
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const responseText = await response.text();
            
            // Hide loading state
            this.hideLoading();

            // Check if response indicates success (JSON format with itemsSaved)
            const responseData = JSON.parse(responseText);
            if (responseData && typeof responseData.itemsSaved === 'number' && responseData.itemsSaved >= 0) {
                // Apply adjudicated data to form fields only if we're in a form (page param exists)
                const urlParams = new URLSearchParams(window.location.search);
                const isInForm = urlParams.has('page');
                
                if (this.dataApplicator && responseData.data && isInForm) {
                    try {
                        const context = this.dataApplicator.getCurrentContext();
                        this.dataApplicator.apply(responseData, context);
                    } catch (applicatorError) {
                        console.warn('Error applying adjudicated data to form:', applicatorError);
                        // Don't fail the save operation due to applicator errors
                    }
                }
                
                return { success: true, data: responseData }; // Success with data
            } else {
                throw new Error('Save operation may have failed - unexpected response format');
            }

        } catch (error) {
            console.error('Error saving adjudicated data:', error);
            this.hideLoading();
            this.showError('Failed to save data: ' + error.message);
            return { success: false, error: error }; // Failure
        }
    }

    /**
     * Toast helper methods for user feedback
     */
    showSavingToast() {
        if (this.toaster && this.config.showSaveProgress) {
            this.toaster.info(this.config.savingMessage, {title: 'Saving'});
        }
    }

    showSuccessToast(message) {
        if (this.toaster) {
            this.toaster.success(message || this.config.successMessage, { title: 'Success' });
        }
    }

    showErrorToast(message) {
        if (this.toaster) {
            this.toaster.danger(message || this.config.errorMessage, { title: 'Save Error' });
        }
    }

    /**
     * Handle save success with configurable behavior
     */
    async handleSaveSuccess(options = {}) {
        const urlParams = new URLSearchParams(window.location.search);
        const isInForm = urlParams.has('page');
        
        if (typeof this.config.onSaveSuccess === 'function') {
            // Semi-auto: use custom handler
            await this.config.onSaveSuccess();
        } else if (this.config.reloadOnSuccess !== false && isInForm) {
            // Calculate reload timing text
            const successDelay = this.config.successDelay;
            const successDelaySeconds = Math.floor(successDelay / 1000);
            const reloadSecondsText = successDelaySeconds === 1 ? '1 second' : `${successDelaySeconds} seconds`;
            
            // Build success message with reload text
            const successMessageWithReload = `${this.config.successMessage} Page will reload in ${reloadSecondsText}...`;
            
            // Fully auto: default behavior with toast and reload (only when in forms)
            this.showSuccessToast(successMessageWithReload);
            
            setTimeout(() => {
                window.location.reload();
            }, successDelay);
        } else {
            // Just show success toast, no reload (not in a form or reload disabled)
            this.showSuccessToast();
        }
    }

    /**
     * Handle save error with configurable behavior
     */
    async handleSaveError(error, options = {}) {
        if (typeof this.config.onSaveError === 'function') {
            // Semi-auto: use custom handler
            await this.config.onSaveError(error);
        } else {
            // Fully auto: default error toast
            const message = this.config.errorMessage.replace('{error}', error.message);
            this.showErrorToast(message);
        }
    }

    /**
     * Main save flow handler - manages entire save process
     */
    async handleSaveFlow(options = {}) {
        try {
            // Show saving toast
            this.showSavingToast();
            
            // Save data
            const result = await this.saveData();
            
            if (result.success) {
                await this.handleSaveSuccess(options);
                return { success: true, error: null };
            } else {
                await this.handleSaveError(result.error || new Error('Save operation failed'), options);
                return { success: false, error: result.error };
            }
        } catch (error) {
            await this.handleSaveError(error, options);
            return { success: false, error };
        }
    }

    /**
     * Update configuration
     */
    updateConfig(newConfig) {
        this.config = { ...this.config, ...newConfig };
    }
}