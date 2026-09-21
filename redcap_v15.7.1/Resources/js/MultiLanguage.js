/* REDCap Multi-Language Management */

// @ts-check
;(function() {

//#region -- Variables

/** @type REDCap */
// @ts-ignore
var REDCap = window.REDCap
if (typeof REDCap == 'undefined') {
    REDCap = {
        MultiLanguage: {}
    }
    // @ts-ignore
    window.REDCap = REDCap
}
/** @type MultiLanguage */
var ML = REDCap.MultiLanguage
if (typeof ML == 'undefined') {
    ML = {}
    REDCap.MultiLanguage = ML
}

/** @type MultiLanguageConfig */
var config;

/** @type {string} sha1 of the initial state (for dirty check) */
var initialHash;

/** @type {boolean} Tracks whether there are unsaved changes */
var dirty = false;

/** @type {string} The current language for translations */
var currentLanguage = ''

/** @type {string} The currently shown main tab */
var currentTab = '';

/** @type {string} The last panel that was shown on the Forms/Surveys tab */
var formsTabMode = 'table'

/** @type {string} The name of the currently shown instrument on the Forms/Surveys tab */
var currentFormsTabForm = ''

/** @type {string} The name of the currently shown subcategory on the Misc tab */
var currentMiscTabCategory = ''

/** @type {string} The name of the currently shown subcategory on the MyCap tab */
var currentMyCapTabCategory = ''

/** @type {string[]} List of all data dictionary keys for dirty checks cache */
var hashCacheDdKeys = [];

/** @type {Number} The interval in minutes between polling of new metadata hash values */
const hashPollInterval = 2

// Access to global language strings
// @ts-ignore
const $lang = window.lang

/** @type {boolean} Indicates whether RTE is available */
// @ts-ignore
const rteSupported = !isIE || vIE() >= 11;

/** @type {Object<string, Object<string, Object<string, Object<string, ChangedItemInfo>>>>} List of items with hash mismatches (update at init and after save) */
let itemsChangedSinceTranslated = {}

// Modals

/** @type {bootstrap.Modal} Data Changed Report */
let dcr_modal;

/** @type {AddEditLanguageModal} Add/Edit Language Modal */
let addEditLanguageModal;

/** @type {RegExp} Field embedding regex */
const fieldEmbeddingRegex = /\{([a-z](?:[_a-z0-9]*[a-z0-9]+){0,1})\}/gm;

//#endregion

//#region -- Initialization

/**
 * Initializes the Multi-Language Management page
 * @param {MultiLanguageConfig} data 
 */
 ML.init = function(data) {
    config = data

    $(function() {
        log('Multi-Language Management initializing ...')
        const initStart = Date.now()
        $('div[data-mlm-initialized]').hide()
        initTemplateCache();

        // Initialize modals
        setupAddEditLanguageModal();
        gsiModal.setup()
        delModal.init('#mlm-delete-modal')
        exportModal.init('#mlm-export-modal')
        delSnapshotModal.init('#mlm-delete-snapshot-modal')
        // Rich Text Editor
        if (rteSupported) {
            rteModal.init()
            // TinyMCE
            // @ts-ignore
            if (typeof window.tinymce == 'undefined') {
                // @ts-ignore
                window.loadJS(app_path_webroot + 'Resources/webpack/css/tinymce/tinymce.min.js')
            }
            // The following allows TinyMCE's internal dialogs to work (like when adding links).
            // It was copied from here: https://stackoverflow.com/questions/18111582/tinymce-4-links-plugin-modal-in-not-editable
            $(document).on('focusin', function(e) {
                if ($(e.target).closest(".mce-window").length) {
                    e.stopImmediatePropagation()
                }
            })
        }
        // Single Item Translation
        sitModal.init()

        // Set toggles on settings tab and hook up change tracking
        $('div[data-mlm-tab=settings] input[data-mlm-config]').each(function() {
            const $this = $(this)
            const name = $this.attr('data-mlm-config') ?? ''
            $this.prop('checked', config.settings[name] ?? false)
        })
        // Get default language
        currentLanguage = config.settings.refLang
        // Build a hash map from project metadata
        config.projMeta.refMap = buildRefMap();
        // Assemble list of hash cache keys
        hashCacheDdKeys = Object.keys(config.projMeta.refMap).filter(key => key !== 'ui');
        hashCacheDdKeys.push('form-active');
        hashCacheDdKeys.push('survey-active');
        hashCacheDdKeys.push('recaptcha-lang');
        

        // Calculate some statistics
        config.stats = calcStats()
        // Record initial state
        initialHash = calcInitialHash();
        setDirty(false)
        log('Initial hash: ' + initialHash)
        // Perform updates
        renderLanguagesTable()
        updateSwitcher(true)
        $('.mlm-translation-default-prompt').on('click', copyDefaultValue)
        // Handle navigation away from page when there are unsaved changes
        window.addEventListener('beforeunload', function (e) {
            if (dirty) {
                // Cancel the event
                e.preventDefault(); // If you prevent default behavior in Mozilla Firefox prompt will be allways shown
                // Chrome requires returnValue to be set
                e.returnValue = '';
            }
            else {
                delete e['returnValue'];
            }
        })

        // Update translations and go to languages tab
        updateTranslations();
        activateCategory('ui-common');
        if (config.mode == 'Project') {
            // Activate the first Misc category that has data
            if (Object.keys(config.projMeta.mdcs).length > 0) {
                activateCategory('misc-mdc');
            }
            else if(Object.keys(config.projMeta.pdfCustomizations).length > 0) {
                activateCategory('misc-pdf');
            }
            else if(Object.keys(config.projMeta.descriptivePopups).length > 0) {
                activateCategory('misc-descriptive-popups');
            }
            else if(Object.keys(config.projMeta.protectedMail).length > 0) {
                activateCategory('misc-protmail');
            }
        }
        activateTab('languages') 

        // Handle actions
        $('div.mlm-setup-container').on('click', handleActions)
        $('#mlm-dcr-modal').on('click', handleActions)

        // Register change trackers
        $('div[data-mlm-tab="languages"]').on('change', storeConfig)
        $('div[data-mlm-tab="ui"] .mlm-ui-translations').on('change', storeUITranslation)
        $('div[data-mlm-tab="settings"]').on('change', storeConfig)
        if (config.mode == 'Project') {
            $('div[data-mlm-tab="forms"]').on('change', storeTranslations)
            $('div[data-mlm-tab="mycap"]').on('change', storeTranslations);
            $('div[data-mlm-tab="alerts"]').on('change', storeTranslations)
            $('div[data-mlm-tab="misc"]').on('change', storeTranslations)
        }

        // Search functionalities
        const throttledUISearch = throttle(performUISearch, 200, { leading: false })
        $('input[data-mlm-config="ui-search"]').on('input change keyup paste click search', function(e) {
            const val = ($(e.target).val() ?? '').toString().toLowerCase()
            if (val == '') {
                performUISearch()
            }
            else {
                throttledUISearch()
            }
        })
        const throttledAlertsSearch = throttle(performAlertsSearch, 200, { leading: false })
        $('input[data-mlm-config="alerts-search"]').on('input change keyup paste click search', function(e) {
            const val = ($(e.target).val() ?? '').toString().toLowerCase()
            if (val == '') {
                performAlertsSearch()
            }
            else {
                throttledAlertsSearch()
            }
        })
        // Show warning message to user if MyCap is enabled for language which is not supported at app-side
        $(document).on('click', 'input[data-mlm-config="mycap-active"]', function(e) {
            const $source = $(e.target)
            const lang = $source.parents('tr').attr('data-mlm-language') ?? currentLanguage;
            const isChecked = $(e.target).is(":checked")
            if (isChecked) {
                checkMyCapSupportedLanguage(lang);
            }
        })
        const throttledMyCapSearch = throttle(performMyCapSearch, 200, { leading: false });
        $('input[data-mlm-config="mycap-search"]').on('input change keyup paste click search', function(e) {
            const val = ($(e.target).val() ?? '').toString().toLowerCase();
            if (val == '') {
                performMyCapSearch();
            }
            else {
                throttledMyCapSearch();
            }
        });
        const throttledDescriptivePopupsSearch = throttle(performDescriptivePopupsSearch, 200, { leading: false });
        $('input[data-mlm-config="descriptivepopups-search"]').on('input change keyup paste click search', function(e) {
            const val = ($(e.target).val() ?? '').toString().toLowerCase();
            if (val == '') {
                performDescriptivePopupsSearch();
            }
            else {
                throttledDescriptivePopupsSearch();
            }
        });

        // Initialize textarea autosizing
        // @ts-ignore
        $('.textarea-autosize').on('focus', function() { $(this).trigger('input'); }).textareaAutoSize()
        // @ts-ignore
        $('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });


        // Provide CTRL-S (CMD-S) for saving
        document.addEventListener("keydown", function (e) {
            if ((window.navigator.platform.match("Mac") ? e.metaKey : e.ctrlKey)) {
                if (e.key == 's') {
                    e.preventDefault()
                    // Click on 'Save Changes'
                    $(e.target ?? {}).trigger('blur')
                    $('[data-mlm-action="save-changes"]').trigger('click')
                }
                else if (e.key == 'g' && config.mode == 'Project') {
                    e.preventDefault()
                    // Focus on Jump to field
                    const $jumpList = $('select.mlm-jumplist')
                    if ($jumpList.length) {
                        $jumpList.trigger('focus')[0].scrollIntoView(false)
                    }
                }
            }
        }, false);

        // Show off notice (Control Center and Projects)
        updateSystemOfforProjectDisabledIndicator()

        // Setup periodic metadata hash checking (once per minute; Project only)
        if (config.mode == 'Project') {
            setInterval(checkMetadataHash, Math.round(hashPollInterval * 60000))
        }

        // Project/UI Metadata Change Report
        updateSourceDataChangedReport(true)

        // Remove items not intended to be shown on the Control Center page
        if (config.mode == 'System') {
            $('.remove-when-control-center').remove()
        }

        // Production mode?
        if (config.settings.status == 'prod') {
            // Disable save button
            $('button[data-mlm-action="save-changes"]').addClass('text-danger').prop('disabled', true)
        }

        // Update state of the snapshot button
        updateCreateSnapshotButton();

        // Apply select2 to language preference field; max width set to 300
        $('#designated-language-field').select2({
            width: '400px'
        });

        // Done
        // Hide initializing spinner
        $('[data-mlm-loading]').addClass('d-none')
        $('div[data-mlm-initialized]').show()

        const initEnd = Date.now()
        const initDuration = initEnd - initStart
        
        log('Multi-Language Management initialized in ' + initDuration + ' ms.')
        log(config)

        // Debug only - navigate to places (uncomment)
        // if (config.settings.debug) {
        //     if (config.mode == 'Project') {
        //         // Project
        //         setTimeout(function() {
        //             switchLanguage('de')
        //             activateTab('forms')
        //             currentFormsTabForm = 'survey_1'
        //             setFormsTabMode('survey')
        //         }, 200)
        //     }
        //     else {
        //         // Control Center
        //     }
        // }

        // Disable the Control Center page if admin does not have System Config privileges
        // @ts-ignore (defined in base.js)
        if (getParameterByName('pid') == '' && access_system_config == '0') {
            // @ts-ignore (ControlCenter.js)
            disableAllFormElements();
        }
    });
}

//#region Ref Map
/**
 * Builds a data structure compatible with lang.dd containing ref hashes (refHash), reference values (refValue) and an indication whether there is reference content.
 * @returns {Object<string?, Object<string, Object<string, { translation: boolean, refHash: string, refValue: string }>>>}
 */
function buildRefMap() {
    const map = {}
    const pm = config.projMeta
    // Helper function to set items, ensuring that the path exists by creating it
    function setMap(type, name, index, hasTranslation, refHash, refValue, prompt) {
        if (!map.hasOwnProperty(type)) {
            map[type] = {}
        }
        if (!map[type].hasOwnProperty(name)) {
            map[type][name] = {}
        }
        map[type][name][index] = {
            translation: hasTranslation,
            refHash: refHash,
            refValue: refValue,
            prompt: prompt,
        }
    }
    //#region UI items
    for (const uiName of Object.keys(config.uiMeta)) {
        const uiMeta = config.uiMeta[uiName]
        setMap('ui', uiName, '', true, uiMeta.refHash, uiMeta.default, uiMeta.prompt)
    }
    //#endregion
    //#region Form
    if (pm.forms) {
        for (const formName of Object.keys(pm.forms)) {
            const form = pm.forms[formName]
            const isset = !isEmpty(form['form-name'])
            const refHash = isset ? form['form-name'].refHash : config.projMeta.emptyHash
            const refValue = isset ? form['form-name'].reference : ''
            setMap('form-name', formName, '', isset, refHash, refValue, $lang.multilang_107)
        }
    }
    //#endregion
    //#region Fields
    if (pm.fields) {
        for (const fieldName of Object.keys(pm.fields)) {
            const field = pm.fields[fieldName]
            for (const type of [
                'field-header',
                'field-label',
                'field-note',
                'field-video_url'
            ]) {
                if (field[type]) {
                    const isset = !isEmpty(field[type])
                    const refHash = isset ? field[type].refHash : config.projMeta.emptyHash
                    const refValue = isset ? field[type].reference : ''
                    const prompt = pm.fieldTypes[field.type][type.replace('field-','')]
                    setMap(type, fieldName, '', isset, refHash, refValue, prompt)
                }
            }
            for (const type of [
                'field-enum',
                'field-actiontag',
            ]) {
                if (field[type]) {
                    for (const index of Object.keys(field[type])) {
                        const choice = field[type][index]
                        const prompt = type == 'field-actiontag'
                            ? pm.fieldTypes.actiontag.value 
                            : pm.fieldTypes[field.type].enum
                        // must always be true (otherwise the choice wouldn't exist)
                        setMap(type, fieldName, index, true, choice.refHash, choice.reference ?? '', prompt)
                    }
                }
            }
        }
    }
    //#endregion
    //#region Matrix
    if (pm.matrixGroups) {
        for (const mgName of Object.keys(pm.matrixGroups)) {
            const mg = pm.matrixGroups[mgName]
            const isset = !isEmpty(mg["matrix-header"])
            const refHash = isset ? mg["matrix-header"].refHash : config.projMeta.emptyHash
            const refValue = isset ? mg["matrix-header"].reference : ''
            const prompt = pm.fieldTypes.matrix.header
            setMap('matrix-header', mgName, '', isset, refHash, refValue, prompt)
            for (const index of Object.keys(mg["matrix-enum"] ?? {})) {
                const choice = mg["matrix-enum"][index]
                const prompt = pm.fieldTypes.matrix.enum
                // must always be true (otherwise the choice wouldn't exist)
                setMap('matrix-enum', mgName, index, true, choice.refHash, choice.reference ?? '', prompt)
            }
        }
    }
    //#endregion
    //#region Surveys
    if (pm.surveys) {
        for (const formName of Object.keys(pm.surveys)) {
            const survey = pm.surveys[formName]
            for (const type of [
                'survey-acknowledgement',
                'survey-confirmation_email_content',
                'survey-confirmation_email_from_display',
                'survey-confirmation_email_subject',
                'survey-end_survey_redirect_url',
                'survey-instructions',
                'survey-logo_alt_text',
                'survey-offline_instructions',
                'survey-repeat_survey_btn_text',
                'survey-response_limit_custom_text',
                'survey-stop_action_acknowledgement',
                'survey-survey_btn_text_next_page',
                'survey-survey_btn_text_prev_page',
                'survey-survey_btn_text_submit',
                'survey-text_to_speech',
                'survey-text_to_speech_language',
                'survey-title',
            ]) {
                const isset = !isEmpty(survey[type])
                const refHash = isset ? survey[type].refHash : config.projMeta.emptyHash
                const refValue = isset ? survey[type].reference : ''
                setMap(type, formName, '', isset, refHash, refValue, survey[type].prompt)
            }
        }
    }
    //#endregion
    //#region ASIs
    if (pm.asis) {
        for (const asiId of Object.keys(pm.asis)) {
            const asi = pm.asis[asiId]
            for (const type of [
                'asi-email_content',
                'asi-email_sender_display',
                'asi-email_subject',
            ]) {
                const isset = !isEmpty(asi[type])
                const refHash = isset ? asi[type].refHash : config.projMeta.emptyHash
                const refValue = isset ? asi[type].reference : ''
                setMap(type, asi.form, asiId, isset, refHash, refValue, asi[type].prompt)
            }
        }
    }
    //#endregion
    //#region Alerts
    if (pm.alerts) {
        for (const alertId of Object.keys(pm.alerts)) {
            const alerts = pm.alerts[alertId]
            for (const type of [
                'alert-email_from_display',
                'alert-email_subject',
                'alert-alert_message'
            ]) {
                const isset = !isEmpty(alerts[type])
                const refHash = isset ? alerts[type].refHash : config.projMeta.emptyHash
                const refValue = isset ? alerts[type].reference : ''
                setMap(type, alertId, '', isset, refHash, refValue, alerts[type].prompt)
            }
            var templateDataType = 'alert-sendgrid_template_data'
            if (!isEmpty(alerts[templateDataType])) {
                const isset = !isEmpty(alerts[templateDataType])
                const refHash = isset ? alerts[templateDataType].refHash : config.projMeta.emptyHash
                const refValue = isset ? alerts[templateDataType].reference : ''
                var templateData = JSON.parse(refValue)
                for (var key in templateData) {
                    setMap(templateDataType, alertId, key, isset, refHash, templateData[key], alerts[templateDataType].prompt)
                }
            }
        }
    }
    //#endregion
    //#region Survey Queue
    if (pm.surveyQueue) {
        for (const type of [
            'sq-survey_auth_custom_message',
            'sq-survey_queue_custom_text',
        ]) {
            const isset = !isEmpty(pm.surveyQueue[type])
            const refHash = isset ? pm.surveyQueue[type].refHash : config.projMeta.emptyHash
            const refValue = isset ? pm.surveyQueue[type].reference : ''
            setMap(type, '', '', isset, refHash, refValue, pm.surveyQueue[type].prompt)
        }
    }
    //#endregion
    //#region Events
    if (pm.events) {
        for (const eventId of Object.keys(pm.events)) {
            const event = pm.events[eventId]
            for (const type of Object.keys(event)) {
                if (type.startsWith('event-')) {
                    setMap(type, event.id, '', true, event[type].refHash, event[type].reference, event[type].prompt)
                }
            }
        }
    }
    //#endregion
    //#region Missing Data Codes
    if (pm.mdcs) {
        for (const mdc of Object.keys(pm.mdcs)) {
            const mdcInfo = pm.mdcs[mdc]
            // These do not need a prompt - it's pre-rendered in PHP
            setMap('mdc-label', mdc, '', true, mdcInfo.refHash, mdcInfo.reference, null)
        }
    }
    //#endregion
    //#region PDF Customizations
    if (pm.pdfCustomizations) {
        for (const name of Object.keys(pm.pdfCustomizations)) {
            for (const pdfCustType of Object.keys(pm.pdfCustomizations[name])) {
                const pdfInfo = pm.pdfCustomizations[''][pdfCustType]
                // These do not need a prompt - it's pre-rendered in PHP
                setMap(pdfCustType, name, '', true, pdfInfo.refHash, pdfInfo.reference, null)
            }
        }
    }
    //#endregion
    //#region Protected Mail (REDCap Secure Messaging)
    if (pm.protectedMail) {
        for (const name of Object.keys(pm.protectedMail)) {
            for (const protmailType of Object.keys(pm.protectedMail[name])) {
                const protmailInfo = pm.protectedMail[''][protmailType]
                // These do not need a prompt - it's pre-rendered in PHP
                setMap(protmailType, name, '', true, protmailInfo.refHash, protmailInfo.reference, null)
            }
        }
    }
    //#endregion
    //#region MyCap
    if (pm.myCapEnabled) {
        setMap('mycap-app_title', '', '', !isEmpty(pm.myCap["mycap-app_title"]), pm.myCap["mycap-app_title"].refHash, pm.myCap["mycap-app_title"].reference, pm.myCap["mycap-app_title"].prompt);
        //#region Baseline Date Task
        for (const name of Object.keys(pm.myCap['mycap-baseline_task'])) {
            const item = pm.myCap['mycap-baseline_task'][name]
            const isset = !isEmpty(item)
            const refHash = isset ? item.refHash : config.projMeta.emptyHash
            const refValue = isset ? item.reference : ''
            setMap('mycap-baseline_task', name, '', isset, refHash, refValue, item.prompt)
        }
        //#endregion
        //#region About pages
        for (const id of Object.keys(pm.myCap.pages)) {
            const item = pm.myCap.pages[id]
            for (const type of [
                'mycap-about-page_title',
                'mycap-about-page_content'
            ]) {
                const isset = !isEmpty(item[type])
                const refHash = isset ? item[type].refHash : config.projMeta.emptyHash
                const refValue = isset ? item[type].reference : ''
                setMap(type, id, '', isset, refHash, refValue, item[type].prompt)
            }
        }
        //#endregion
        //#region Contacts
        for (const id of Object.keys(pm.myCap.contacts)) {
            const item = pm.myCap.contacts[id]
            for (const type of [
                'mycap-contact-additional_info',
                'mycap-contact-contact_header',
                'mycap-contact-contact_title',
                'mycap-contact-email',
                'mycap-contact-phone_number',
                'mycap-contact-website'
            ]) {
                const isset = !isEmpty(item[type])
                const refHash = isset ? item[type].refHash : config.projMeta.emptyHash
                const refValue = isset ? item[type].reference : ''
                setMap(type, id, '', isset, refHash, refValue, item[type].prompt)
            }
        }
        //#endregion
        //#region Links
        for (const id of Object.keys(pm.myCap.links)) {
            const item = pm.myCap.links[id]
            for (const type of [
                'mycap-link-link_name',
                'mycap-link-link_url'
            ]) {
                const isset = !isEmpty(item[type])
                const refHash = isset ? item[type].refHash : config.projMeta.emptyHash
                const refValue = isset ? item[type].reference : ''
                setMap(type, id, '', isset, refHash, refValue, item[type].prompt)
            }
        }
        //#endregion
        //#region Tasks
        for (const formName of Object.keys(pm.forms)) {
            const form = pm.forms[formName];
            if (!form.myCapTaskId) continue;
            // Pan-event items
            for (const taskItemName of Object.keys(form.myCapTaskItems)) {
                if (!config.projMeta.myCap.orderedListOfTaskItems[taskItemName]) continue;
                const taskItem = form.myCapTaskItems[taskItemName];
                setMap(
                    'task-' + taskItemName, 
                    form.myCapTaskId, 
                    '', 
                    !isEmpty(taskItem), 
                    taskItem.refHash, 
                    taskItem.reference, 
                    taskItem.prompt
                );
            }
            // Event-specific items
            for (const eventId in config.projMeta.events) {
                const eventIdTaskId = eventId+'-'+form.myCapTaskId;
                if (!form.myCapTaskItems[eventIdTaskId]) continue;
                for (const taskItemName in form.myCapTaskItems[eventIdTaskId]) {
                    const taskItem = form.myCapTaskItems[eventIdTaskId][taskItemName];
                    setMap(
                        'task-' + taskItemName, 
                        eventIdTaskId, 
                        '', 
                        !isEmpty(taskItem), 
                        taskItem.refHash, 
                        taskItem.reference, 
                        taskItem.prompt
                    );
                }
            }
        }
        //#endregion
    }
    //#endregion
    //#region Descriptive Popups
    for (const popup_uid in pm.descriptivePopups) {
        const popup = pm.descriptivePopups[popup_uid];
        for (const name of ['inline_text', 'inline_text_popup_description']) {
            setMap('descriptive-popup', name, popup_uid, !isEmpty(popup[name]), popup[name].refHash, popup[name].reference, popup[name].prompt);
        }
    }
    //#endregion
    return map
}
//#endregion

/**
 * Calculates translation statistics
 */
 function calcStats() {
    // User Interface
    const stats = {
        ui: {}
    }
    stats.ui.n =  Object.keys(config.uiMeta).reduce(function(n, current) {
        return (config.uiMeta[current].type == 'string') ? n + 1 : n
    }, 0)
    for (const langKey of Object.keys(config.settings.langs)) {
        const lang = config.settings.langs[langKey]
        const n = Object.keys(lang.ui).reduce(function(n, current) {
            return (current in config.uiMeta && config.uiMeta[current].type == 'string') ? n + 1 : n
        }, 0)
        stats.ui[langKey] = {
            n: n,
            percentage: Math.min(n < stats.ui.n ? 99.9 : 100, n / stats.ui.n * 100).toFixed(1)
        }
    }
    return stats
}

//#endregion

//#region -- Navigation

/**
 * Switches between main tabs.
 * @param {string} tab The name of the tab to navigate to
 */
function activateTab(tab) {
    if (Object.keys(config.settings.langs).length == 0 && !['settings','usage'].includes(tab)) {
        // Always redirect to languages tab when there are no languages set up set
        tab = 'languages'
    }
    currentTab = tab
    updateSwitcher();
    log('Activating tab: ' + tab)
    $('a[data-mlm-target]').parent().removeClass('active')
    $('a[data-mlm-target="' + tab + '"]').parent().addClass('active')
    $('div[data-mlm-tab]').addClass('d-none')
    $('div[data-mlm-tab="' + tab + '"]').removeClass('d-none')

    if (tab == 'ui') {
        updateUserInterface();
    }
    else if (tab == 'forms') {
        // When switching to Forms/Surveys, always show the table and hide the fields
        setFormsTabMode('table')
    }
    else if (tab == 'mycap') {
        renderMyCapTab();
    }
    else if (tab == 'alerts') {
        renderAlertsTab()
    }
    else if (tab == 'misc') {
        updateMiscTab()
    }
    else if (tab == 'usage') {
        renderUsageStatsTab()
    }
    setUIEnabledState();
    setRtlStatus();
    setAITranslator();
}

/**
 * Disables most of the UI when the project is in production mode
 * @param {string} selector Limit to a specific selector
 * @returns 
 */
function setUIEnabledState(selector = '') {
    if (config.settings.status != 'prod') return

    const $tab = $(selector == '' ? 'div[data-mlm-tab]' : selector);
    $tab.find('button, input, select, textarea').each(function() {
        const $el = $(this)
        const action = $el.attr('data-mlm-action') ?? 'no-action'
        $el.prop('disabled', !prod_allowed_actions.includes(action))
    })
}
const prod_allowed_actions = [
    'export-language',
    'export-single-form',
    'explain-actiontags',
    'translate-fields',
    'translate-forms',
    'translate-mycap',
    'translate-alerts',
    'translate-misc',
    'translate-ui',
    'switch-language',
    'translate-survey',
    'translate-asis',
    'toggle-snapshots',
    'toggle-show-deleted-snapshots',
    'download-snapshot',
    'export-general',
    'empty-pdf-all',
    'empty-pdf',
    'toggle-actions',
    'reveal-task-items'
];

/**
 * Sets the direction of input elements based on the current language
 */
function setRtlStatus() {
    const rtl = config.settings.langs[currentLanguage]?.rtl ?? false;
    $('[data-mlm-tab]').find('input[type=text], textarea').attr('dir', rtl ? 'rtl' : 'ltr');
}

/**
 * Toggles visibility of Forms/Surveys elements
 * @param {string} mode Use null to hide all, ommit to show last shown
 */
function setFormsTabMode(mode = '') {
    $('[data-mlm-tab="forms"] [data-mlm-mode]').hide()
    if (config.settings.debug && mode) {
        // Show updates in plain sight in debug mode
        $('[data-mlm-tab="forms"] [data-mlm-mode="' + mode + '"]').show()
    }
    if (mode !== null) {
        if (mode) {
            formsTabMode = mode
        }
        clearFormsTab()
        switch (formsTabMode) {
            case 'table':
                updateForms()
                currentFormsTabForm = ''
                break
            case 'fields':
                renderFields()
                break
            case 'survey':
                renderSurveySettings()
                break
            case 'asi':
                renderASIs()
                break
        }
        $('[data-mlm-tab="forms"] [data-mlm-mode="' + formsTabMode + '"]').show()
        setUIEnabledState();
        setRtlStatus();
    }
}

/**
 * Cleans up fields and survey settings
 */
function clearFormsTab() {
    $('div[data-mlm-render="fields"]').children().remove()
    $('div[data-mlm-render="survey"]').children().remove()
}


/**
 * Renders the Alerts tab
 */
function renderAlertsTab() {
    if (currentTab != 'alerts') return // Do nothing when alerts tab is not shown
    $('[data-mlm-tab="alerts"] [data-mlm-mode]').hide()
    if (currentLanguage == config.settings.refLang) {
        // Alerts Exclusion
        renderAlertsExclusionTable()
        $('[data-mlm-tab="alerts"] [data-mlm-mode="alerts-exclusion"]').show()
    }
    else {
        // Alerts Translation
        renderAlertsTranslation()
        $('[data-mlm-tab="alerts"] [data-mlm-mode="alerts-translation"]').show()
    }
}

/**
 * Switches to the given language
 * @param {string} to 
 */
function switchLanguage(to) {
    if (to != currentLanguage) {
        currentLanguage = to
        // Keep a record of the currently shown forms tab
        const prevFormsTabForm = currentFormsTabForm
        const prevFormsTabMode = formsTabMode
        updateSwitcher()
        updateTranslations()
        if (currentTab == 'forms') {
            currentFormsTabForm = prevFormsTabForm
            setFormsTabMode(prevFormsTabMode)
        }
        setUIEnabledState();
        setRtlStatus();
        setAITranslator();
    }
}

/**
 * Switches between translation sub-tabs.
 * @param {string} cat The name of the category tab to show
 */
function activateCategory(cat) {
    log('Activating category: ' + cat)
    // Common actions
    const type = cat.split('-')[0]
    const $tab = $('[data-mlm-tab="' + type + '"]')
    $tab.find('.mlm-sub-category-nav a').removeClass('active')
    $tab.find('.mlm-sub-category-nav a[data-mlm-sub-category=' + cat + ']').addClass('active')
    // Specific stuff
    if (cat.startsWith('ui-')) {
        $tab.find('div.mlm-sub-category').addClass('d-none')
        $tab.find('div.mlm-sub-category[data-mlm-sub-category=' + cat + ']').removeClass('d-none')
        // Show or hide items depending on category assigment
        $tab.find('div.mlm-ui-translations [data-mlm-ui-translation]').each(function() {
            const $this = $(this)
            if (cat == 'ui-all' || $this.hasClass(cat)) {
                $this.removeClass('mlm-ui-item-cat-exclude').addClass('mlm-ui-visible')
            }
            else {
                $this.addClass('mlm-ui-item-cat-exclude').removeClass('mlm-ui-visible')
            }
        })
        updateUISubheadings()
    }
    else if (cat.startsWith('misc-')) {
        currentMiscTabCategory = cat
        $tab.find('.mlm-misc-category-tab').addClass('hide')
        $tab.find('.mlm-misc-category-tab[data-mlm-sub-category="' + cat + '"]').removeClass('hide')
        updateMiscTab()
    }
    else if (cat.startsWith('mycap-')) {
        currentMyCapTabCategory = cat;
        $tab.find('.mlm-mycap-category-tab').addClass('hide');
        $tab.find('.mlm-mycap-category-tab[data-mlm-sub-category="' + cat + '"]').removeClass('hide');
        renderMyCapTab();
    }
}

function updateUISubheadings() {
    // Remove and recreate all subheadings
    const $ui = $('div.mlm-ui-translations')
    $ui.find('.mlm-sub-category-subheading').remove()
    for (const group of Object.keys(config.uiSubheadings)) {
        const shText = config.uiSubheadings[group]
        const $sh = $('<h5 class="mlm-sub-category-subheading">' + shText + '</h5>')
        $ui.find('[data-mlm-group="' + group + '"].mlm-ui-visible').not('.mlm-ui-item-hidden').first().before($sh)
    }
    performUISearch(true)
}

function updateSystemOfforProjectDisabledIndicator() {
    if (config.settings.disabled || config.settings['admin-disabled']) {
        $('.mlm-off-notice').removeClass('hide')
    }
    else {
        $('.mlm-off-notice').addClass('hide')
    }
}

//#endregion

//#region -- Actions

/**
 * Handles actions (mouse clicks on links, buttons)
 * @param {JQuery.TriggeredEvent} event 
 */
function handleActions(event) {
    var $source = $(event.target)
    var action = $source.attr('data-mlm-action')
    if (!action) {
        $source = $source.parents('[data-mlm-action]')
        action = $source.attr('data-mlm-action')
    }
    if (!action || $source.prop('disabled')) return

    log('Handling action "' + action + '" from:', $source)

    switch (action) {
        case 'explain-actiontags':
            // @ts-ignore
            actionTagExplainPopup(1);
            break;
        case 'main-nav':
            var target = $source.attr('data-mlm-target') ?? ''
            activateTab(target)
            break;
        case 'cat-nav':
            var cat = $source.attr('data-mlm-sub-category') ?? ''
            activateCategory(cat)
            break
        case 'save-changes':
            saveChanges()
            break
        case 'ai-translate':
            aiTranslate()
            break
        case 'switch-language':
            const toLang = $source.attr('data-mlm-lang') ?? currentLanguage
            switchLanguage(toLang)
            break;
        // Languages - Add language and row actions
        case 'add-language':
            addEditLanguageModal.add();
            // aeiModal_show('add')
            break
        case 'edit-language':
        case 'update-language':
        case 'toggle-actions':
        case 'translate-forms':
        case 'translate-mycap':
        case 'translate-alerts':
        case 'translate-misc':
        case 'translate-ui':
        case 'export-language':
        case 'delete-language':
        case 'add-system':
        case 'empty-pdf-all':
            const lang = $source.parents('tr').attr('data-mlm-language') ?? currentLanguage;
            if (config.settings.status != 'prod' || prod_allowed_actions.includes(action)) {
                executeLanguageRowAction(action, lang);
            }
            break;
        // Forms/Surveys/ASIs
        case 'empty-pdf':
            currentFormsTabForm = $source.parents('[data-mlm-form]').attr('data-mlm-form') ?? '';
            const url = new URL(config.settings.pdfLink.url);
            url.searchParams.append(config.settings.pdfLink.pageParam, currentFormsTabForm);
            url.searchParams.append(config.settings.pdfLink.langParam, currentLanguage);
            url.searchParams.append(config.settings.pdfLink.forceParam, '');
            log(`Downloading PDF for instrument '${currentFormsTabForm}' and language '${currentLanguage}'.`);
            window.open(url.toString());
            break;
        case 'translate-fields':
            currentFormsTabForm = $source.parents('[data-mlm-form]').attr('data-mlm-form') ?? '';
            setFormsTabMode('fields');
            break;
        case 'translate-survey':
            currentFormsTabForm = $source.parents('[data-mlm-form]').attr('data-mlm-form') ?? '';
            setFormsTabMode('survey');
            break;
        case 'translate-asis':
            currentFormsTabForm = $source.parents('[data-mlm-form]').attr('data-mlm-form') ?? '';
            setFormsTabMode('asi');
            break;
        case 'copy-reference':
            // Copy a reference value to the clipboard
            var text = $source.parents('.mlm-reference').find('.mlm-reference-value').text();
            copyTextToClipboard(text);
            // Give visual cue of copy
            $source.find('i:first').removeClass('far').removeClass('fa-copy').addClass('fas').addClass('fa-check');
            setTimeout(function(){
                $source.find('i:first').removeClass('fas').removeClass('fa-check').addClass('far').addClass('fa-copy');
                // Place cursor in field
                $source.parent().parent().find('input, textarea').focus();
            }, 600);
            break;
        case 'rich-text-editor':
            // Show the rich text editor for a field
            rteModal.show({
                lang: config.settings.langs[currentLanguage],
                name: $source.attr('data-mlm-name') ?? '',
                type: $source.attr('data-mlm-type') ?? '',
                index: $source.attr('data-mlm-index') ?? '',
                mode: $source.attr('data-mlm-rtemode') ?? 'normal',
                $textarea: $source.parent().find('textarea'),
            });
            break;
        case 'accept-ref-change':
            acceptRefChange($source.parent().parent().find('[data-mlm-translation]'));
            break;
        case 'toggle-hide-ui-translated':
            applyHideTranslatedUIItems();
            break;
        case 'toggle-hide-fielditems-translated':
        case 'refresh-hide-fielditems-translated':
            applyHideTranslatedFieldItems();
            break;
        case 'translate-surveyqueue':
            translateSingleDdItem($lang.multilang_58, 'sq-survey_queue_custom_text', '', 'rte');
            break;
        case 'translate-surveylogin':
            translateSingleDdItem($lang.multilang_59, 'sq-survey_auth_custom_message', '', 'rte');
            break;
        case 'translate-formname':
            const formName = $source.parents('tr[data-mlm-form]').attr('data-mlm-form');
            translateSingleDdItem($lang.multilang_150, 'form-name', formName, 'single');
            break;
        // MyCap
        case 'mycap-toggle-collapse':
            const mycapId = $source.attr('data-mlm-mycap-id') ?? '';
            toggleMyCapItem(mycapId, null, true);
            break;
        case 'mycap-collapse-all':
            $source.parents('.mlm-mycap-category-tab').find('div[data-mlm-mycap-setting]').each(function() {
                const id = $(this).attr('data-mlm-mycap-setting') ?? '';
                toggleMyCapItem(id, 'collapse');
            });
            break;
        case 'mycap-expand-all':
            $source.parents('.mlm-mycap-category-tab').find('div[data-mlm-mycap-setting]').each(function() {
                const id = $(this).attr('data-mlm-mycap-setting') ?? '';
                toggleMyCapItem(id, 'expand');
            });
            break;
        // Alerts
        case 'alert-toggle-collapse':
            const alertId = $source.attr('data-mlm-alert-id') ?? '';
            toggleAlert(alertId, null, true);
            break;
        case 'alerts-collapse-all':
            $('div[data-mlm-alert]').each(function() {
                const alertId = $(this).attr('data-mlm-alert') ?? ''
                toggleAlert(alertId, 'collapse')
            })
            break
        case 'alerts-expand-all':
            $('div[data-mlm-alert]').each(function() {
                const alertId = $(this).attr('data-mlm-alert') ?? ''
                toggleAlert(alertId, 'expand')
            })
            break
        // Snapshots
        case 'toggle-snapshots':
            toggleSnapshotsTable()
            break
        case 'toggle-show-deleted-snapshots':
            toggleShowDeletedSnapshots()
            break
        case 'create-snapshot':
            createSnapshot()
            break
        case 'delete-snapshot': 
            {
                const snapshotId = $source.attr('data-mlm-snapshot')
                if (snapshotId != undefined) {
                    delSnapshotModal.show(snapshotId)
                }
            }
            break
        case 'download-snapshot':
            {
                const snapshotId = $source.attr('data-mlm-snapshot') ?? ''
                if (!Number.isNaN(Number.parseInt(snapshotId))) {
                    downloadSnapshot(snapshotId)
                }
            }
            break
        case 'review-changed-hash-items':
            showChangedHashItems(true)
            break
        case 'accept-all-changed-items':
            acceptAllChangedItems()
            break
        case 'accept-changed-item':
            acceptChangedItem($source.parents('tr.mlm-changed-item'))
            break
        case 'edit-changed-item':
            editChangedItem($source.parents('tr.mlm-changed-item'))
            break
        case 'export-changed-items':
            exportChangedItems($source.parents('tr.mlm-lang-title'))
            break
        case 'export-single-form':
            exportSingleFormItems($source.parents('tr[data-mlm-form]'))
            break
        case 'export-general':
            exportModal.show('', 'general', '')
            break
        case 'import-general':
            gsiModal.show()
            break
        case 'refresh-usage':
            refreshUsageStats()
            break
        case 'export-usage':
            exportUsageStats()
            break
        case 'reveal-field-items':
            revealFieldItems($source);
            break;
        case 'reveal-task-items':
            revealTaskItems($source);
            break;
        case 'toggle-descriptivepopup-items':
            toggleDescriptivePopup($source);
            break;
        case 'descriptivepopups-collapse-all':
            collapseAllDescriptivePopups();
            break;
        case 'descriptivepopups-expand-all':
            uncollapseAllDescriptivePopups();
            break;
        // ???
        default:
            warn('Unknown action: ' + action)
            break
    }
}

/**
 * Copies a string to the clipboard (fallback method for older browsers)
 * @param {string} text 
 */
function fallbackCopyTextToClipboard(text) {
    var textArea = document.createElement("textarea");
    textArea.value = text;
    // Avoid scrolling to bottom
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
    } catch(e) {
        error('Failed to copy text to clipboard.')
    }
    document.body.removeChild(textArea);
}

/**
 * Copies a string to the clipboard (supported in modern browsers)
 * @param {string} text 
 * @returns 
 */
function copyTextToClipboard(text) {
    if (!navigator.clipboard) {
        fallbackCopyTextToClipboard(text);
        return;
    }
    navigator.clipboard.writeText(text).catch(function() {
        error('Failed to copy text to clipboard.')
    })
}

//#endregion

//#region -- Language Switcher

/**
 * Updates the language switcher widgets
 * @param {boolean} recreate 
 */
function updateSwitcher(recreate = false) {
    const $switcher = $('div.mlm-switcher-buttons')
    if (recreate) {
        $switcher.children().remove()
        const $prompt = $('<span class="mlm-switcher-buttons-pretext me-1" data-rc-lang="multilang_734">' + $lang.multilang_734 +'</span>');
        $switcher.append($prompt);
        sortLanguages().forEach(function(langKey) {
            /** @type {Language} */
            const lang = config.settings.langs[langKey]
            const $btn = $('<button data-mlm-action="switch-language" class="btn btn-sm mlm-switcher-button"></button>')
            $btn.attr('data-mlm-lang', lang.key)
            const $span = $('<span></span>')
            $span.text(lang.display)
            // Denote the reference language in the project context only
            if (langKey == config.settings.refLang && config.mode == 'Project') {
                $btn.append('<span class="badge"><i class="fas fa-asterisk"></i></span>')
                $btn.addClass('mlm-default-lang')
            }
            $btn.append($span)
            $switcher.append($btn)
        })
    }
    $switcher.children('button').each(function() {
        const btn = $(this);
        const langKey = (btn.attr('data-mlm-lang') ?? '').toString();
        const lang = config.settings.langs[langKey];
        btn.removeClass('btn-primary btn-light btn-outline-primary text-danger').find('i.mlm-subscribed').remove();
        if (lang.syslang != '' && lang.subscribed && currentTab == 'ui') {
            btn.addClass(langKey == currentLanguage ? 'btn-outline-primary' : 'btn-light text-danger');
            btn.append('<i class="fa-solid fa-bolt-lightning text-warning ml-1 mlm-subscribed"></i>');
        }
        else if (langKey == currentLanguage) {
            btn.addClass('btn-primary')
        }
        else {
            btn.addClass('btn-light');
        }
    })
}

/**
 * Updates the translations on all tabs
 */
function updateTranslations() {
    // Make sure currentLanguage is valid
    if (!config.settings.langs.hasOwnProperty(currentLanguage)) {
        return
    }

    if (config.mode == 'Project') {
        updateForms()
        renderMyCapTab();
        renderAlertsTab()
        updateMiscTab()
    }
    updateUserInterface()

    // Show or hide the reference language notice
    const action = currentLanguage == config.settings.refLang ? 'removeClass' : 'addClass'
    $('.mlm-reflang-notice')[action]('hide')
    log('Updated translations for [' + currentLanguage + ']')
}

//#endregion

//#region -- Language Tab

/**
 * Renders the table on the 'Languages' tab.
 */
function renderLanguagesTable() {
    log('Updating languages:', config.settings.langs)
    $('#mlm-languages').hide()
    const $tbody = $('#mlm-languages-rows')
    // Remove all rows
    $tbody.children().remove()
    // Create rows
    const langKeys = sortLanguages()
    for (const langKey of langKeys) {
        /** @type Language */
        const lang = config.settings.langs[langKey];
        const $row = getTemplate('languages-row')
        $row.attr('data-mlm-language', langKey)
        $row.find('[data-mlm-config]').each(function() {
            const $this = $(this)
            const name = $this.attr('data-mlm-config')
            if (name == undefined) return
            if ($this.is('input[type=checkbox]')) {
                $this.attr('id', lang.key + '-' + name)
            }
            if ($this.is('label')) {
                $this.attr('for', lang.key + '-' + name)
            }
            if (name == 'refLang') {
                $this.prop('checked', lang.key == config.settings.refLang)
            }
            else if (name == 'initialLang') {
                $this.prop('checked', lang.key == config.settings.initialLang)
            }
            else if (name == 'fallbackLang') {
                $this.prop('checked', lang.key == config.settings.fallbackLang)
            }
            if ($this.is('input')) {
                $this.prop('checked', lang[name])
            }
            if (name == 'percent' && config.stats.ui[langKey]) {
                $this.text(config.stats.ui[langKey] .percentage)
            }
            else if($this.is('span')) {
                $this.text(lang[name])
            }
        })
        if (langKey == config.settings.refLang) {
            $row.find('[data-mlm-action="translate-misc"]').prop('disabled', true)
        }
        // Subscription status on delete button - system context only
        if (config.mode == 'System') {
            $row.find('[data-mlm-action="delete-language"]').addClass(lang["subscribed-to-details"] && lang["subscribed-to-details"].total > 0 ? 'mlm-subscribed' : '').addClass(lang["subscribed-to"] ? 'mlm-cannot-delete' : '').prop('disabled', lang["subscribed-to"]);
            if (lang["subscribed-to"]) {
                // Add tooltip
                $row.find('[data-mlm-action="delete-language"]').each(function() {
                    this.setAttribute('title', '');
                    const wrapper = $(this).parent().get(0);
                    log(wrapper);
                    if (wrapper) {
                        new bootstrap.Popover(wrapper, {
                            'trigger': 'hover focus',
                            'html': true,
                            'title': '',
                            // @ts-ignore base.js
                            'content': interpolateString(window['lang'].multilang_689, [
                                lang["subscribed-to-details"]?.total, 
                                lang["subscribed-to-details"]?.dev, 
                                lang["subscribed-to-details"]?.prod
                            ])
                        });
                    }
                });
            }
        }
        $tbody.append($row)
    }
    // Designated field - project context only
    if (config.mode == 'Project') {
        const $select = $('[data-mlm-config="designatedField"]')
        const field = config.settings.designatedField ?? ''
        $select.val(field)
        updateDesignatedFieldWarning(field)
    }
    // Show or hide the table
    if (langKeys.length) {
        $('.mlm-no-languages').hide();
        $('#mlm-languages').find('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
        $('#mlm-languages').show();
        $('.hidden-when-no-langs').show();
    }
    else {
        $('.mlm-no-languages').show();
        $('#mlm-languages').hide();
        $('.hidden-when-no-langs').hide();
    }
}

/**
 * Shows or hides the warning about a designated field not having all langs encoded
 * The values for this are all predefined during PHP rendering
 * @param {string} field 
 */
function updateDesignatedFieldWarning(field) {
    const complete = $('[data-mlm-config="designatedField"]').find('option[value="' + field + '"]').attr('data-mlm-complete')
    const action = (complete == '1' || !field) ? 'addClass' : 'removeClass'
    $('.mlm-designated-field-warning')[action]('hide')
}

/**
 * Perform action after clicking on one of the buttons in the languages table
 * @param {string} action The action to perform
 * @param {string} langKey The language to act upon
 */
function executeLanguageRowAction(action, langKey) {
    log('Executing row action: "' + action + '" for [' + langKey + ']');
    // Dispose any tooltips (inside popovers)
    $('.mlm-language-actions-popover [data-bs-toggle="tooltip"]').each(function() {
        const tooltip = bootstrap.Tooltip.getInstance(this);
        if (tooltip) tooltip.dispose();
    });
    // Dispose any (other) popovers (i.e., in tr that are not the language specified by langKey)
    const popoverDisposeSelector = action == 'toggle-actions' 
        ? 'tr[data-mlm-language!="' + langKey + '"] button[data-mlm-action="toggle-actions"]'
        : 'button[data-mlm-action="toggle-actions"]';
    $(popoverDisposeSelector).each(function() {
        $(this).popover('dispose');
    });
    switch (action) {
        case 'translate-forms':
        case 'translate-mycap':
        case 'translate-alerts':
        case 'translate-misc':
        case 'translate-ui':
            currentLanguage = langKey;
            updateSwitcher();
            updateTranslations();
            activateTab(action.substring(10));
            break;
        case 'edit-language':
            addEditLanguageModal.edit(langKey);
            break;
        case 'update-language':
            addEditLanguageModal.update(langKey);
            break;
        case 'delete-language':
            delModal.show(langKey);
            break;
        case 'export-language':
            exportModal.show(langKey, 'all', '');
            break;
        case 'empty-pdf-all':
            const url = new URL(config.settings.pdfLink.url);
            url.searchParams.append(config.settings.pdfLink.langParam, langKey);
            url.searchParams.append(config.settings.pdfLink.forceParam, '');
            url.searchParams.append('all', '');
            window.open(url.toString());
            break;
        case 'toggle-actions': {
            const $tr = $('tr[data-mlm-language="' + langKey + '"]');
            const $btn = $tr.find('button[data-mlm-action="toggle-actions"]');
            let popover = bootstrap.Popover.getInstance($btn[0]);
            if (popover) {
                popover.dispose();
                return;
            }
            const content = $tr.find('template.language-actions').html();
            popover = new bootstrap.Popover($btn[0], {
                content: content,
                title: '',
                sanitize: false,
                html: true,
                container: $tr.get(0),
                placement: 'left',
                customClass: 'mlm-language-actions-popover',
            });
            popover.show();
            // Update UI icon
            const lang = config.settings.langs[langKey];
            const uiOverridesAllowed = config.settings["allow-ui-overrides"] || !config.settings["disable-ui-overrides"];
            $tr.find('[data-mlm-action="translate-ui"]').addClass((lang.syslang == '' ? '' : 'mlm-syslang ') + (lang.subscribed ? 'mlm-subscribed ' : '') + (uiOverridesAllowed ? 'mlm-can-override' : ''));
            $('.mlm-language-actions-popover [data-bs-toggle="tooltip"]').each(function() {
                new bootstrap.Tooltip(this);
            });
            setUIEnabledState('.mlm-language-actions-popover');
            break;
        }
    }
}

//#endregion

//#region -- Modals

//#region ---- Add/Edit/Update Language Modal

/**
 * Sets up the Add/Edit Language modal
 */
function setupAddEditLanguageModal() {
    addEditLanguageModal = (() => {
        /** @type {AddEditLanguageData} */
        const data = {
            mode: 'add-source',
            merge: 'local',
            overwrite: false,
            source: '',
            uiItems: true,
            psItems: true,
            fileValid: false,
            fileData: null,
            lang: createEmptyLang(),
            prevKey: ''
        }
        //#region Modals
        const $addModal = $('#mlm-add-language-modal');
        const addModal = new bootstrap.Modal($addModal.get(0) ?? '', { backdrop: 'static', keyboard: true });
        const $editModal = $('#mlm-edit-language-modal');
        const editModal = new bootstrap.Modal($editModal.get(0) ?? '', { backdrop: 'static', keyboard: true });
        const $updateModal = $('#mlm-update-language-modal');
        const updateModal = new bootstrap.Modal($updateModal.get(0) ?? '', { backdrop: 'static', keyboard: true });
        const $modal = () => { switch (data.mode) {
            case 'add-source': return $addModal;
            case 'add-info': return $addModal;
            case 'edit': return $editModal;
            case 'update': return $updateModal;
        }};
        const modal = () => { switch (data.mode) {
            case 'add-source': return addModal;
            case 'add-info': return addModal;
            case 'edit': return editModal;
            case 'update': return updateModal;
        }};
        //#endregion
        //#region Click and change handlers
        // Add
        $addModal.find('button[data-mlm-modal-btn="continue"]').on('click', () => addNewContinue());
        $addModal.find('button[data-mlm-modal-btn="add"]').on('click', () => saveLanguage());
        $addModal.find('input').on('change', handleChanges);
        // Edit
        $editModal.find('button[data-mlm-modal-btn="save"]').on('click', () => saveLanguage());
        $editModal.find('input').on('change', handleChanges);
        // Update
        $updateModal.find('button[data-mlm-modal-btn="update"]').on('click', () => updateLanguage());
        $updateModal.find('input').on('change', handleChanges);
        $updateModal.find('select').on('change', function() {
            $updateModal.find('input[name="source"][value="system"]').prop('checked', true);
        });
        //#endregion
        //#region Public interface
        return {
            add: () => {
                data.mode = 'add-source';
                data.lang = createEmptyLang();
                show();
            },
            edit: (prevKey) => {
                data.mode = 'edit';
                data.prevKey = prevKey;
                data.lang = copyLang(data.prevKey);
                show();
            },
            update: (langKey) => {
                data.mode = 'update';
                data.prevKey = langKey;
                data.lang = copyLang(langKey);
                show();
            }
        }
        //#endregion
        //#region Display
        /** Shows the modal in the set mode */
        function show() {
            log('Add/Edit/Update Language:', data.prevKey, data.mode, $modal());
            // Show or hide elements
            $modal().find('[data-mlm-modal-mode]').each(function() {
                const $this = $(this);
                $this[($this.attr('data-mlm-modal-mode') ?? '').split(' ').includes(data.mode) ? 'show' : 'hide']();
            });
            //#region Add
            if (data.mode == 'add-source') {
                // Clear data
                data.fileData = null;
                data.fileValid = false;
                data.lang = createEmptyLang();
                data.prevKey = '';
                $modal().find('input[name="key"]').val(data.lang.key);
                $modal().find('input[name="display"]').val(data.lang.display);
                $modal().find('textarea[name="notes"]').val(data.lang.notes ?? '');
                $modal().find('input[name="sort"]').val(data.lang.sort);
                // Ensure first available option is checked and clear file
                $modal().find('input[type="radio"]').not('[disabled]').first().prop('checked', true);
                $modal().find('input[type="file"]').removeClass('is-valid').removeClass('is-invalid').val('');
                // Trigger updates and show
                handleChanges();
                modal().show();
                return;
            }
            if (data.mode == 'add-info') {
                if (data.source == 'system' || data.source == 'file') {
                    copyImportedValues();
                }
                $modal().find('input[name="key"]').val(data.lang.key);
                $modal().find('input[name="display"]').val(data.lang.display);
                $modal().find('textarea[name="notes"]').val(data.lang.notes ?? '');
                $modal().find('input[name="sort"]').val(data.lang.sort);
                handleChanges();
                $modal().find('input[name="key"]').trigger('focus');
                return;
            }
            //#endregion
            //#region Edit
            if (data.mode == 'edit') {
                $modal().find('[data-item="orig-id"]').text(data.prevKey);
                $modal().find('[data-item="orig-name"]').text(config.settings.langs[data.prevKey].display);
                $modal().find('input[name="key"]').val(data.lang.key);
                $modal().find('input[name="display"]').val(data.lang.display);
                $modal().find('textarea[name="notes"]').val(data.lang.notes ?? '');
                $modal().find('input[name="sort"]').val(data.lang.sort);
                $modal().find('.mlm-show-when-subscribed')[data.lang.subscribed ? 'show' : 'hide']();
                $modal().find('.mlm-show-when-based-on-syslang').hide();
                const sysLang = getSysLangByGuid(data.lang.syslang ?? '');
                if (sysLang) {
                    $modal().find('[data-mlm-item="subscribed-to-lang"]').text(sysLang.display);
                    $modal().find('.mlm-show-when-based-on-syslang').show();
                }
                $modal().find('input[name="subscribed"]')
                    .prop('checked', data.lang.subscribed)
                    .prop('disabled', config.settings['force-subscription'] && !config.settings["optional-subscription"]);
                handleChanges();
                $modal().find('input[name="key"]').trigger('focus');
                modal().show();
                return;
            }
            //#endregion
            //#region Update
            if (data.mode == 'update') {
                $modal().find('[data-item="orig-id"]').text(data.prevKey);
                $modal().find('[data-item="orig-name"]').text(config.settings.langs[data.prevKey].display);
                // System language and subscription status
                const sysLang = getSysLangByGuid(data.lang.syslang ?? '');
                if (sysLang) {
                    $modal().find('select[name="syslang"] option[value="' + sysLang.key + '"]').prop('selected', true);
                    $modal().find('.mlm-show-when-subscribed')[data.lang.subscribed ? 'show' : 'hide']();
                    $modal().find('[data-mlm-item="subscribed-to-lang"]').text(sysLang.display).attr('data-mlm-syslang', sysLang.guid);
                }
                else {
                    // Pre-select first
                    $modal().find('select[name="syslang"] option').first().prop('selected', true);
                }
                $modal().find('.mlm-show-when-based-on-syslang')[sysLang ? 'show' : 'hide']();
                $modal().find('.mlm-hide-when-based-on-syslang')[sysLang ? 'hide' : 'show']();
                $modal().find('input[name="associate-with-syslang"]').prop('checked', false);
                // Restrict UI import based on subscription and override status
                let uiChecked = true;
                let uiDisabled = false; 
                if (data.lang.syslang != '') {
                    uiChecked = !data.lang.subscribed && (!config.settings["disable-ui-overrides"] || config.settings["allow-ui-overrides"]);
                    uiDisabled = config.settings["disable-ui-overrides"] && !config.settings["allow-ui-overrides"];
                }
                $modal().find('input[name="include-ui"]').prop('checked', uiChecked).prop('disabled', uiDisabled);
                // Always start with importing PSI
                $modal().find('input[name="include-psi"]').prop('checked', true);
                // Always start with "Keep existing" on and "Allow blank to overwrite" off
                $modal().find('input[name="import-merge-mode"][value="keep"]').prop('checked', true);
                $modal().find('input[name="import-empty"]').prop('checked', false);
                // Do not preselect a source
                $modal().find('input[name="source"]').prop('checked', false);
                // Reset file
                data.fileData = null;
                data.fileValid = false;
                $modal().find('input[type="file"]').val('')
                    .removeClass('is-invalid').removeClass('is-valid')
                    // @ts-ignore
                    .get(0)?.setCustomValidity('');
                handleChanges();
                modal().show();
                return;
            }
            //#endregion
        }
        //#endregion
        //#region Change handling
        /**
         * Handles change events to update some states
         * @param {JQuery.TriggeredEvent|false} e
         */
        function handleChanges(e = false) {
            let valid;
            if (data.mode == 'add-source' || data.mode == 'update') {
                // @ts-ignore
                data.source = $modal().find('input[name="source"]:checked').val();
            }
            else if (data.mode == 'add-info' || data.mode == 'edit') {
                // Capture lang info
                data.lang.key = ($modal().find('input[name="key"]').val() ?? '').toString();
                data.lang.display = ($modal().find('input[name="display"]').val() ?? '').toString();
                data.lang.notes = ($modal().find('textarea[name="notes"]').val() ?? '').toString();
                data.lang.sort = ($modal().find('input[name="sort"]').val() ?? '').toString();
                // Validate language key
                valid = data.lang.key == '' ? true : validateLanguage('key', data.lang.key, data.prevKey);
                if (data.lang.key != '') {
                    if (valid) {
                        // @ts-ignore
                        $modal().find('input[name="key"]').removeClass('is-invalid').addClass('is-valid').get(0)?.setCustomValidity('');
                    }
                    else {
                        // @ts-ignore
                        $modal().find('input[name="key"]').removeClass('is-valid').addClass('is-invalid').get(0)?.setCustomValidity('Invalid');
                    }
                }
            }
            // Enable/Disable Continue
            if (data.mode == 'add-source') {
                $modal().find('[data-mlm-modal-btn="continue"]').prop('disabled', !(
                    data.source == 'system' ||
                    (data.source == 'file' && data.fileValid) ||
                    data.source == 'new'
                ));
                // File
                if (e && e.target.name == 'file') {
                    $modal().find('input[name="source"][value="file"]').prop('checked', true);
                    const files = $modal().find('input[type="file"]').removeClass('is-valid').prop('files');
                    if (files.length == 1) {
                        // Check from file whennever there is a file
                        $modal().find('input[name="source"][value="file"]').prop('checked', true);
                        data.source = 'file';
                        readFile(files[0]);
                    }
                }
            }
            else if (data.mode == 'add-info') {
                $modal().find('[data-mlm-modal-btn="add"]').prop('disabled',
                    // Key must be valid and non-empty and display name must be non-empty
                    !valid || data.lang.key.length == 0 || data.lang.display.length == 0
                );
            }
            else if (data.mode == 'edit') {
                // Nothing
            }
            else if (data.mode == 'update') {
                // Update state of Update button
                $modal().find('[data-mlm-modal-btn="update"]').prop('disabled', !(
                    data.source == 'system' || (data.source == 'file' && data.fileValid)
                ));
                // File
                if (e && e.target.name == 'file') {
                    const files = $modal().find('input[type="file"]').removeClass('is-valid').prop('files');
                    if (files.length == 1) {
                        // Check from file whennever there is a file
                        $modal().find('input[name="source"][value="file"]').prop('checked', true);
                        data.source = 'file';
                        readFile(files[0]);
                    }
                }
                // Import options
                data.merge = $modal().find('input[name="import-merge-mode"]:checked').val() == 'keep' ? 'local' : 'imported';
                data.overwrite = $modal().find('input[name="import-empty"]:checked').length == 1;
                data.uiItems = $modal().find('input[name="include-ui"]').prop('checked') && (!data.lang.subscribed || config.settings["allow-ui-overrides"]); 
                data.psItems = $modal().find('input[name="include-psi"]').prop('checked');
            }
            log('Modal data updated:', data.mode, data);
        }
        //#endregion
        //#region Transitions
        /** Transitions to page 2 of the Add New Language modal */
        function addNewContinue() {
            $modal().find('button[data-mlm-modal-btn="continue"]').prop('disabled', true);
            // Store page 1 data
            // @ts-ignore
            data.source = $modal().find('input[name="source"]:checked').val();
            data.uiItems = $modal().find('input[name="include-ui"]').prop('checked');
            data.psItems = $modal().find('input[name="include-psi"]').prop('checked');
            if (config.mode == 'System') {
                // Need to get a GUID for the new language
                ajax('get-guid').then(function(response) {
                    data.lang.guid = response.guid;
                    if (data.source == 'system') {
                        addOrUpdateFromSysLang();
                    }
                    else if (data.source == 'file') {
                        mergeLanguageFromFileOrSystem(data);
                        data.mode = 'add-info';
                        show();
                    }
                    else {
                        data.mode = 'add-info';
                        show();
                    }
                }).catch(function(err) {
                    showToastMLM('#mlm-errorToast', $lang.multilang_664) // Failed to obtain a GUID from the server. Please check the browser console (F12) for details.
                    error(err);
                }).finally(function() {
                    $modal().find('[data-mlm-modal-btn="continue"]').prop('disabled', false);
                });
            }
            else {
                if (data.source == 'system') {
                    addOrUpdateFromSysLang();
                }
                else if (data.source == 'file') {
                    mergeLanguageFromFileOrSystem(data);
                    data.mode = 'add-info';
                    show();
                }
                else {
                    data.mode = 'add-info';
                    show();
                }
            }
        }
        /** Gets a system language via AJAX */
        function addOrUpdateFromSysLang() {
            // Perform server request for language
            const sysLangKey = ($modal().find('select[name="syslang"]').val() ?? '').toString();
            const sysLangGuid = config.sysLangs[sysLangKey].guid;
            ajax('get-sys-lang', { guid: sysLangGuid }).then(function(response) {
                data.fileData = response.lang;
                log('Obtained system language: [' + data.fileData.key + ']', data.fileData);
                mergeLanguageFromFileOrSystem(data);
                if (data.mode == 'add-source') {
                    // Go back to the first page of the dialog
                    data.lang.subscribed = $('input[name="subscribed"]').prop('checked');
                    data.mode = 'add-info';
                    show();
                }
                else {
                    // Associate?
                    if (data.lang.syslang == '' && $modal().find('input[name="associate-with-syslang"]').prop('checked')) {
                        data.lang.syslang = data.fileData.guid;
                        data.lang.subscribed = config.settings["force-subscription"] == true;
                    }
                    // Save immediately
                    saveLanguage();
                }
            }).catch(function(err) {
                showToastMLM('#mlm-errorToast', $lang.multilang_665) // Failed to load the system language from the server. Please check the browser console (F12) for details.
                error(err);
                // Re-enabled button
                $modal().find('[data-mlm-modal-btn="update"]').prop('disabled', false);
            });
        }
        /** Copies key, display, notes, sort values from imported language */
        function copyImportedValues() {
            // Assign key if not empty (increment a counter until the key is a valid)
            let key = '' + data.fileData.key;
            let i = 1;
            if (key.length && validateLanguage('key', key, key)) {
                while(!validateLanguage('key', i == 1 ? key : `${key}-${i}`, '')) {
                    i++;
                }
            } 
            data.lang.key = i == 1 ? key : `${key}-${i}`;
            data.lang.display = data.fileData.display ?? '';
            if (data.lang.display != '' && i > 1) data.lang.display = data.lang.display + ' ' + i;
            data.lang.notes = data.fileData.notes ?? '';
            data.lang.sort = data.fileData.sort ?? '';
        }
        /** Performs steps necessary to update a language from system or import */
        function updateLanguage() {
            handleChanges();
            log('Performing update', data);
            $modal().find('[data-mlm-modal-btn="update"]').prop('disabled', true);
            if (data.source == 'system') {
                addOrUpdateFromSysLang();
            }
            else {
                mergeLanguageFromFileOrSystem(data);
                saveLanguage();
            }
        }
        //#endregion
        //#region Save
        /** Adds the new language */
        function saveLanguage() {
            handleChanges();
            if (data.mode == 'add-info') {
                // Add system language GUID and subscription status
                if (data.source == 'system') {
                    if (config.mode == 'Project') {
                        data.lang.syslang = data.fileData.guid;
                    }
                } 
                if (Object.keys(config.settings.langs).length == 0) {
                    // This is the first, so make it the default one
                    config.settings.refLang = data.lang.key
                    config.settings.fallbackLang = data.lang.key
                    // And set it as the current one
                    currentLanguage = data.lang.key
                }
                config.settings.langs[data.lang.key] = data.lang
                log('Added new language:', data);
            }
            else {
                if (data.mode == 'edit') {
                    data.lang.subscribed = config.mode == 'Project' && $modal().find('input[name="subscribed"]').prop('checked');
                }
                var isMyCapActive = config.settings.langs[data.prevKey]["mycap-active"]

                delete config.settings.langs[data.prevKey]
                config.settings.langs[data.lang.key] = data.lang
                // Current or default affected? Fix
                if (config.settings.refLang == data.prevKey) {
                    config.settings.refLang = data.lang.key
                }
                if (config.settings.fallbackLang == data.prevKey) {
                    config.settings.fallbackLang = data.lang.key
                }
                if (currentLanguage == data.prevKey) {
                    currentLanguage = data.lang.key
                }
                log('Updated language:', data);
                if (isMyCapActive) {
                    checkMyCapSupportedLanguage(data.lang.key);
                }
            }
            renderLanguagesTable();
            updateTranslations();
            updateSourceDataChangedReport(false);
            checkDirty(data.lang.key, 'full');
            updateSwitcher(true);
            modal().hide();
        }
        //#endregion
        //#region File Handling
        /**
         * Reads a file
         * @param {File} file 
         */
        function readFile(file) {
            const filename = file.name;
            const reader = new FileReader()
            const ext = filename.includes('.') ? filename.split('.').reverse()[0].toLowerCase() : 'json' // Try JSON
            reader.onload = function(e) {
                if (e.target && e.target.result) {
                    log('Processing file:', filename)
                    data.fileValid = false;
                    processFile(e.target.result.toString(), ext).then(function(response) {
                        data.fileData = response
                        if (!data.fileData || data.fileData.creator !== 'REDCap MLM') {
                            throw 'Not a REDCap Multi-Language Management file'
                        }
                        // @ts-ignore
                        $modal().find('input[type="file"]').removeClass('is-invalid').addClass('is-valid').get(0)?.setCustomValidity('');
                        data.fileValid = true;
                        log('Imported language from file:', data.fileData);
                    }).catch(function(ex) {
                        data.fileData = null
                        // @ts-ignore
                        $modal().find('input[type="file"]').addClass('is-invalid').get(0)?.setCustomValidity('Invalid');
                        if (config.settings.debug) {
                            error('Failed to import from file:', ex)
                        }
                    }).finally(function() {
                        $modal().find('input[type="file"]').prop('disabled', false);
                        handleChanges(false);
                    })
                }
            }
            reader.onerror = function(e) {
                error('Failed to load file:', e)
            }
            reader.readAsText(file)
        }
        /**
         * Processes a file (JSON or CSV) and returns an object
         * @param {string} content 
         * @param {string} type 
         * @returns {Promise<Language>}
         */
        function processFile(content, type) {
            return new Promise(function(resolve, reject) {
                if (type == 'json') {
                    try {
                        const lang = JSON.parse(content)
                        // reformat template data alert translations
                        const reformattedAlertTranslations = []
                        for (const i in lang['alertTranslations']) {
                            const translation = lang['alertTranslations'][i]
                            const reformattedAlertSettings = []
                            const aggregateTemplateDataSetting = {
                                'id': 'alert-sendgrid_template_data'
                            }
                            const templateData = {}
                            for (const j in translation['settings']) {
                                const setting = translation['settings'][j]
                                if (setting['id'].startsWith('alert-sendgrid_template_data')) {
                                    const key = setting['id'].split(':')[1] || null
                                    if (key) {
                                        templateData[key] = setting['translation']
                                    }
                                    aggregateTemplateDataSetting.hash = setting['hash']
                                } else {
                                    reformattedAlertSettings.push(setting)
                                }
                            }
                            if (Object.keys(templateData).length > 0) {
                                aggregateTemplateDataSetting.translation = JSON.stringify(templateData)
                                reformattedAlertSettings.push(aggregateTemplateDataSetting)
                            }
                            translation['settings'] = reformattedAlertSettings
                            reformattedAlertTranslations.push(translation)
                        }
                        lang['alertTranslations'] = reformattedAlertTranslations
                        resolve(lang)
                    }
                    catch (ex) {
                        reject(ex)
                    }
                }
                else if (type == 'csv') {
                    try {
                        const lang = parseCsvIntoLang(content)
                        resolve(lang)
                    }
                    catch (ex) {
                        reject(ex)
                    }
                }
                else if (type == 'ini') {
                    // Let server parse ini file and return a json representation
                    ajax('parse-ini', content).then(function(data) {
                        resolve(data.data)
                    }).catch(function(ex) {
                        reject(ex)
                    })
                }
                else {
                    reject($lang.multilang_613) // Cannot determine file type. Use .csv/.json/.ini files only.
                }
            })
        }
        //#endregion
    })();
}

/**
 * Merges language data
 * @param {AddEditLanguageData} data 
 */
function mergeLanguageFromFileOrSystem(data) {
    log('Merging into language:', data);
    //#region Preliminaries
    if (config.mode == 'Project' && data.mode == 'edit') {
        data.lang.syslang = data.source == 'system' ? data.fileData.guid : '';
    }
    // What to import
    let importUI = config.mode == 'System' || data.uiItems || data.source == 'system';
    const importPSI = config.mode == 'Project' && data.psItems && data.source != 'system';
    // Type of import: 'local' or 'imported'
    const useImported = data.merge == 'imported';
    const useLocal = data.merge == 'local';
    // Import empty values
    const overwrite = data.overwrite == true;
    /**
     * Helper function Determines whether the to import or not, depending on values and settings
     * @param {string|null} incoming 
     * @param {string} local 
     * @returns 
     */
    function shouldImport(incoming, local) {
        if (incoming == null) return false
        return (incoming.length != 0 && !(typeof local == 'string' && local.length && useLocal)) || 
            (incoming.length == 0 && useImported && overwrite)
    }
    // Update modal and copy values for key, display, rtl
    // Skip key, display, and sort in edit mode
    if (data.mode == 'add-info') {
        if (shouldImport(data.fileData.key ?? '', data.lang.key)) {
            data.lang.key = data.fileData.key ?? '';
        }
        if (shouldImport( data.fileData.display ?? '', data.lang.display)) {
            data.lang.display =  data.fileData.display ?? '';
        }
        if (shouldImport(data.fileData.sort ?? '', data.lang.sort)) {
            data.lang.sort = data.fileData.sort ?? '';
        }
    }
    const incomingRtl = data.fileData.hasOwnProperty('rtl') ? (data.fileData.rtl == true ? 'true' : 'false') : null;
    const localRtl = data.lang.rtl ? 'true' : 'false';
    if (shouldImport(incomingRtl, localRtl)) {
        data.lang.rtl = data.fileData.rtl == 'true';
    }
    //#endregion
    //#region Import UI
    // When UI overrides are disabled in a project and subscription is on, prevent UI merging and instead clear
    // all UI translations (including UI options)
    if (config.mode == 'Project' && 
        data.lang.syslang != '' && data.lang.subscribed &&
        config.settings["disable-ui-overrides"] && !config.settings["allow-ui-overrides"]) {
        importUI = false;
    }
    // User interface options
    if (importUI && data.fileData.hasOwnProperty('uiOptions') && Array.isArray(data.fileData.uiOptions)) {
        for (const item of data.fileData.uiOptions) {
            // Check if this is a valid id
            if (config.uiMeta[item.id]) {
                if (item.value !== null || overwrite) {
                    data.lang.ui[item.id] = {
                        translation: item.value === true,
                        refHash: null,
                    };
                }
            }
        }
    }
    // User interface translations
    if (importUI && data.fileData.hasOwnProperty('uiTranslations') && Array.isArray(data.fileData.uiTranslations)) {
        for (const item of data.fileData.uiTranslations) {
            // Check if this is a valid id
            if (config.uiMeta[item.id]) {
                const translation = item.translation ?? '';
                const hash = item.hash ?? null;
                const local = data.lang.ui[item.id] ?? { translation: '', refHash: null };
                if (shouldImport(translation, local.translation)) {
                    data.lang.ui[item.id] = { 
                        translation: translation,
                        refHash: hash,
                    };
                }
            }
        }
    }
    //#endregion
    //#region Import PSI (project-specific items)
    //#region - Forms
    if (importPSI && data.fileData.hasOwnProperty('formTranslations') && Array.isArray(data.fileData.formTranslations)) {
        const itemType = 'form-name';
        const itemName = 'name';
        for (const item of data.fileData.formTranslations) {
            if (item.hasOwnProperty('id') && item.hasOwnProperty(itemName) && item[itemName].hasOwnProperty('translation')) {
                const name = item.id;
                const translation = item[itemName].translation ?? '';
                const hash = item[itemName].hasOwnProperty('hash') ? item[itemName].hash : null;
                const local = get_dd(data.lang, itemType, name).translation ?? '';
                if (shouldImport(translation, local)) {
                    set_dd(data.lang, itemType, name, '', translation, hash, false);
                }
            }
            // Form active?
            if (item.hasOwnProperty('id') && item.hasOwnProperty('active') && item.active === true) {
                set_dd(data.lang, 'form-active', item.id, '', true);
            }
        }
    }
    //#endregion
    //#region - Events
    if (importPSI && data.fileData.hasOwnProperty('eventTranslations') && Array.isArray(data.fileData.eventTranslations)) {
        for (const item of data.fileData.eventTranslations) {
            let itemName = '';
            let itemType = '';
            // Name
            itemName = 'name';
            itemType = 'event-name';
            if (item.hasOwnProperty('id') && item.hasOwnProperty(itemName) && item[itemName].hasOwnProperty('translation')) {
                const name = getEventIdFromUniqueEventName(item.id);
                if (name == null) continue;
                const translation = item[itemName].translation ?? '';
                const hash = item[itemName].hasOwnProperty('hash') ? item[itemName].hash : null;
                const local = get_dd(data.lang, itemType, name).translation ?? '';
                if (shouldImport(translation, local)) {
                    set_dd(data.lang, itemType, name, '', translation, hash, false);
                }
            }
            // Custom Event Label
            itemName = 'custom_event_label';
            itemType = 'event-custom_event_label';
            if (item.hasOwnProperty('id') && item.hasOwnProperty(itemName) && item[itemName].hasOwnProperty('translation')) {
                const name = getEventIdFromUniqueEventName(item.id);
                if (name == null) continue;
                const translation = item[itemName].translation ?? '';
                const hash = item[itemName].hasOwnProperty('hash') ? item[itemName].hash : null;
                const local = get_dd(data.lang, itemType, name).translation ?? '';
                if (shouldImport(translation, local)) {
                    set_dd(data.lang, itemType, name, '', translation, hash, false);
                }
            }
        }
    }
    //#endregion
    //#region - Fields
    if (importPSI && data.fileData.hasOwnProperty('fieldTranslations') && Array.isArray(data.fileData.fieldTranslations)) {
        for (const field of data.fileData.fieldTranslations) {
            if (field.hasOwnProperty('id') && config.projMeta.fields.hasOwnProperty(field.id)) {
                const fieldType = config.projMeta.fields[field.id].type;
                const typeMeta = config.projMeta.fieldTypes[fieldType];
                // Header, Label, Note
                for (const type of Object.keys(typeMeta)) {
                    if (['header','label','note'].includes(type) && field.hasOwnProperty(type)) {
                        const translation = field[type].translation ?? '';
                        const hash = field[type].hash ?? null;
                        const local = get_dd(data.lang, 'field-' + type, field.id).translation ?? '';
                        if (shouldImport(translation, local)) {
                            set_dd(data.lang, 'field-' + type, field.id, '', translation, hash, false);
                        }
                    }
                }
                // Enum
                if (typeMeta.hasOwnProperty('enum') && field.hasOwnProperty('enum') && Array.isArray(field.enum)) {
                    const validCodes = Object.keys(config.projMeta.fields[field.id]['field-enum'] ?? {})
                    for (const item of field.enum) {
                        if (item.hasOwnProperty('id') && validCodes.includes(item.id.toString()) && item.hasOwnProperty('translation')) {
                            const translation = item.translation ?? ''
                            const code = item.id.toString()
                            const hash = item.hash ?? null
                            const local = get_dd(data.lang, 'field-enum', field.id, code).translation ?? ''
                            if (shouldImport(translation, local)) {
                                set_dd(data.lang, 'field-enum', field.id, code, translation, hash, false)
                            }
                        }
                    }
                }
                // Actiontags
                if (field.hasOwnProperty('actiontags') && Array.isArray(field.actiontags)) {
                    for (const at of field.actiontags) {
                        const id = at.id ?? '';
                        if (at.hasOwnProperty('translation') && config.projMeta.fields[field.id]['field-actiontag'].hasOwnProperty(id)) {
                            const translation = at.translation ?? '';
                            const hash = at.hash ?? null;
                            const local = get_dd(data.lang, 'field-actiontag', field.id, id).translation ?? '';
                            if (shouldImport(translation, local)) {
                                set_dd(data.lang, 'field-actiontag', field.id, id, translation, hash, false);
                            }
                        }
                    }
                }
            }
        }
    }
    //#endregion
    //#region - Matrix Groups
    if (importPSI && data.fileData.hasOwnProperty('matrixTranslations') && Array.isArray(data.fileData.matrixTranslations)) {
        for (const matrix of data.fileData.matrixTranslations) {
            if (matrix.hasOwnProperty('id') && config.projMeta.matrixGroups.hasOwnProperty(matrix.id)) {
                const name = matrix.id;
                // Header
                if (matrix.hasOwnProperty('header')) {
                    const translation = matrix.header.translation ?? '';
                    const hash = matrix.header.hash ?? null;
                    const local = get_dd(data.lang, 'matrix-header', name).translation ?? '';
                    if (shouldImport(translation, local)) {
                        set_dd(data.lang, 'matrix-header', name, '', translation, hash, false);
                    }
                }
                // Enum
                if (matrix.hasOwnProperty('enum') && Array.isArray(matrix.enum)) {
                    const validCodes = Object.keys(config.projMeta.matrixGroups[name]['matrix-enum'] ?? {});
                    for (const item of matrix.enum) {
                        if (item.hasOwnProperty('id') && validCodes.includes(item.id.toString()) && item.hasOwnProperty('translation')) {
                            const translation = item.translation ?? '';
                            const code = item.id.toString();
                            const hash = item.hash ?? null;
                            const local = get_dd(data.lang, 'matrix-enum', name, code).translation ?? '';
                            if (shouldImport(translation, local)) {
                                set_dd(data.lang, 'matrix-enum', name, code, translation, hash, false);
                            }
                        }
                    }
                }
            }
        }
    }
    //#endregion
    //#region - Survey settings
    if (importPSI && data.fileData.hasOwnProperty('surveyTranslations') && Array.isArray(data.fileData.surveyTranslations)) {
        for (const item of data.fileData.surveyTranslations) {
            if (item.hasOwnProperty('id') && config.projMeta.surveys.hasOwnProperty(item.id) && item.hasOwnProperty('settings') && Array.isArray(item.settings)) {
                const name = item.id;
                for (const setting of item.settings) {
                    if (typeof setting.id == 'string' && setting.id.startsWith('survey-') && config.projMeta.surveys[name].hasOwnProperty(setting.id) && setting.hasOwnProperty('translation')) {
                        const type = setting.id;
                        const translation = setting.translation ?? '';
                        const hash = setting.hasOwnProperty('hash') ? setting.hash : null;
                        const local = get_dd(data.lang, type, name).translation ?? '';
                        if (shouldImport(translation, local)) {
                            set_dd(data.lang, type, name, '', translation, hash, false);
                        }
                    }
                }
            }
            // Survey active?
            if (item.hasOwnProperty('id') && item.hasOwnProperty('active') && item.active === true) {
                set_dd(data.lang, 'survey-active', item.id, '', '1');
            }
        }
    }
    //#endregion
    //#region - Survey Queue settings
    if (importPSI && data.fileData.hasOwnProperty('sqTranslations') && Array.isArray(data.fileData.sqTranslations)) {
        for (const item of data.fileData.sqTranslations) {
            if (typeof item.id == 'string' && item.id.startsWith('sq-') && config.projMeta.surveyQueue.hasOwnProperty(item.id) && item.hasOwnProperty('translation')) {
                const type = item.id;
                const name = '';
                const translation = item.translation ?? '';
                const hash = item.hasOwnProperty('hash') ? item.hash : null;
                const local = get_dd(data.lang, type, name).translation ?? ''
                if (shouldImport(translation, local)) {
                    set_dd(data.lang, type, name, '', translation, hash, false);
                }
            }
        }
    }
    //#endregion
    //#region - ASIs
    if (importPSI && data.fileData.hasOwnProperty('asiTranslations') && Array.isArray(data.fileData.asiTranslations)) {
        for (const item of data.fileData.asiTranslations) {
            if (typeof item.id == 'string' && item.hasOwnProperty('settings') && Array.isArray(item.settings)) {
                // The id is a composite id: unique_event_name-form_name
                // Extract the parts and convert to the internal format which is event_id-survey_id
                // Perform some checks and ignore this item if they fail
                const id_parts = item.id.split('-');
                if (id_parts.length != 2) continue;
                const event_id = getEventIdFromUniqueEventName(id_parts[0]);
                if (event_id == null) continue;
                const form_name = id_parts[1];
                if (typeof config.projMeta.surveys[form_name] == 'undefined') continue;
                const survey_id = config.projMeta.surveys[form_name].id;
                const name = event_id + '-' + survey_id;
                if (config.projMeta.asis.hasOwnProperty(name)) {
                    for (const setting of item.settings) {
                        if (typeof setting.id == 'string' && setting.id.startsWith('asi-') && config.projMeta.asis[name].hasOwnProperty(setting.id) && setting.hasOwnProperty('translation')) {
                            const type = setting.id;
                            const translation = setting.translation ?? '';
                            const hash = setting.hasOwnProperty('hash') ? setting.hash : null;
                            const local = get_dd(data.lang, type, name).translation ?? '';
                            if (shouldImport(translation, local)) {
                                set_dd(data.lang, type, form_name, name, translation, hash, false);
                            }
                        }
                    }
                }
            }
        }
    }
    //#endregion
    //#region - Alerts
    if (importPSI && data.fileData.hasOwnProperty('alertTranslations') && Array.isArray(data.fileData.alertTranslations)) {
        for (const item of data.fileData.alertTranslations) {
            if (typeof item.id == 'string' && item.hasOwnProperty('settings') && Array.isArray(item.settings)) {
                const name = getAlertIdFromAlertData(item);
                if (name != null && config.projMeta.alerts.hasOwnProperty(name)) {
                    for (const setting of item.settings) {
                        if (typeof setting.id == 'string' && setting.id.startsWith('alert-') && config.projMeta.alerts[name].hasOwnProperty(setting.id) && setting.hasOwnProperty('translation')) {
                            const type = setting.id;
                            const translation = setting.translation ?? '';
                            const hash = setting.hasOwnProperty('hash') ? setting.hash : null;
                            const local = get_dd(data.lang, type, name).translation ?? '';
                            if (shouldImport(translation, local)) {
                                if (type == 'alert-sendgrid_template_data') {
                                    const templateData = JSON.parse(translation);
                                    for (var key in templateData) {
                                        set_dd(data.lang, type, name, key, templateData[key], hash, false);
                                    }
                                } else {
                                    set_dd(data.lang, type, name, '', translation, hash, false);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    //#endregion
    //#region - Missing Data Codes
    if (importPSI && data.fileData.hasOwnProperty('mdcTranslations') && Array.isArray(data.fileData.mdcTranslations)) {
        for (const item of data.fileData.mdcTranslations) {
            if (item.hasOwnProperty('id') && config.projMeta.mdcs.hasOwnProperty(item.id) && item.hasOwnProperty('translation')) {
                const type = 'mdc-label';
                const name = item.id;
                const translation = item.translation ?? '';
                const hash = item.hasOwnProperty('hash') ? item.hash : null;
                const local = get_dd(data.lang, type, name).translation ?? '';
                if (shouldImport(translation, local)) {
                    set_dd(data.lang, type, name, '', translation, hash, false);
                }
            }
        }
    }
    //#endregion
    //#region - PDF Customizations
    if (importPSI && data.fileData.hasOwnProperty('pdfTranslations') && Array.isArray(data.fileData.pdfTranslations)) {
        for (const item of data.fileData.pdfTranslations) {
            if (item.hasOwnProperty('id') && config.projMeta.pdfCustomizations[''].hasOwnProperty(item.id) && item.hasOwnProperty('translation')) {
                const type = item.id;
                const name = '';
                const translation = item.translation ?? '';
                const hash = item.hasOwnProperty('hash') ? item.hash : null;
                const local = get_dd(data.lang, type, name).translation ?? '';
                if (shouldImport(translation, local)) {
                    set_dd(data.lang, type, name, '', translation, hash, false);
                }
            }
        }
    }
    //#endregion
    //#region - Protected Email settings
    if (importPSI && data.fileData.hasOwnProperty('protemailTranslations') && Array.isArray(data.fileData.protemailTranslations)) {
        for (const item of data.fileData.protemailTranslations) {
            if (item.hasOwnProperty('id') && config.projMeta.protectedMail[''].hasOwnProperty(item.id) && item.hasOwnProperty('translation')) {
                const type = item.id;
                const name = '';
                const translation = item.translation ?? '';
                const hash = item.hasOwnProperty('hash') ? item.hash : null;
                const local = get_dd(data.lang, type, name).translation ?? '';
                if (shouldImport(translation, local)) {
                    set_dd(data.lang, type, name, '', translation, hash, false);
                }
            }

            // Event-specific items
            for (const eventId in config.projMeta.events) {
                const event = config.projMeta.events[eventId];
                const eventIdTaskId = eventId+'-'+taskId
                if (!item.hasOwnProperty(event.uniqueEventName)) continue;
                for (const type in config.projMeta.myCap.orderedListOfEventSpecificTaskItems) {
                    if (!item[event.uniqueEventName].hasOwnProperty('task-'+type)) continue;
                    if (!item[event.uniqueEventName]['task-'+type].hasOwnProperty('translation')) continue;
                    const translation = item[event.uniqueEventName]['task-'+type].translation ?? '';
                    const hash = item[event.uniqueEventName]['task-'+type].hasOwnProperty('hash') ? item[event.uniqueEventName]['task-'+type].hash : null;
                    const local = get_dd(data.lang, 'task-'+type, eventIdTaskId).translation ?? '';
                    if (shouldImport(translation, local)) {
                        set_dd(data.lang, 'task-'+type, eventIdTaskId, '', translation, hash, false);
                    }
                }
            }
        }
    }
    //#endregion
    //#region - MyCap
    if (importPSI && config.projMeta.myCapEnabled && data.fileData.hasOwnProperty('myCapTranslations') && Array.isArray(data.fileData.myCapTranslations)) {
        for (const item of data.fileData.myCapTranslations) {
            if (item.type == 'mycap-app_title') {
                for (const type of ['mycap-app_title']) {
                    if (!item.hasOwnProperty(type)) continue;
                    if (!item[type].hasOwnProperty('translation')) continue;
                    const translation = item[type].translation ?? '';
                    const hash = item[type].hasOwnProperty('hash') ? item[type].hash : null;
                    const local = get_dd(data.lang, type, '').translation ?? '';
                    if (shouldImport(translation, local)) {
                        set_dd(data.lang, type, '', '', translation, hash, false);
                    }
                }
            }
            else if (item.type == 'mycap-baseline_task') {
                for (const name in item) {
                    if (name.startsWith('mycap-') && config.projMeta.myCap["mycap-baseline_task"].hasOwnProperty(name)) {
                        const translation = item[name].translation ?? '';
                        const hash = item[name].hasOwnProperty('hash') ? item[name].hash : null;
                        const local = get_dd(data.lang, name, '').translation ?? '';
                        if (shouldImport(translation, local)) {
                            set_dd(data.lang, item.type, name, '', translation, hash, false);
                        }
                    }
                }
            }
            else if (item.type == 'mycap-pages') {
                if (!item.hasOwnProperty('id')) continue;
                const name = item.id;
                if (!config.projMeta.myCap.pages.hasOwnProperty(name)) continue;
                for (const type in item) {
                    if (type.startsWith('mycap-') && config.projMeta.myCap.pages[name].hasOwnProperty(type)) {
                        if (!item[type].hasOwnProperty('translation')) continue;
                        const translation = item[type].translation ?? '';
                        const hash = item[type].hasOwnProperty('hash') ? item[type].hash : null;
                        const local = get_dd(data.lang, type, name).translation ?? '';
                        if (shouldImport(translation, local)) {
                            set_dd(data.lang, type, name, '', translation, hash, false);
                        }
                    }
                }
            }
            else if (item.type == 'mycap-contacts') {
                if (!item.hasOwnProperty('id')) continue;
                const name = item.id;
                if (!config.projMeta.myCap.contacts.hasOwnProperty(name)) continue;
                for (const type in item) {
                    if (type.startsWith('mycap-') && config.projMeta.myCap.contacts[name].hasOwnProperty(type)) {
                        if (!item[type].hasOwnProperty('translation')) continue;
                        const translation = item[type].translation ?? '';
                        const hash = item[type].hasOwnProperty('hash') ? item[type].hash : null;
                        const local = get_dd(data.lang, type, name).translation ?? '';
                        if (shouldImport(translation, local)) {
                            set_dd(data.lang, type, name, '', translation, hash, false);
                        }
                    }
                }
            }
            else if (item.type == 'mycap-links') {
                if (!item.hasOwnProperty('id')) continue;
                const name = item.id;
                if (!config.projMeta.myCap.links.hasOwnProperty(name)) continue;
                for (const type in item) {
                    if (type.startsWith('mycap-') && config.projMeta.myCap.links[name].hasOwnProperty(type)) {
                        if (!item[type].hasOwnProperty('translation')) continue;
                        const translation = item[type].translation ?? '';
                        const hash = item[type].hasOwnProperty('hash') ? item[type].hash : null;
                        const local = get_dd(data.lang, type, name).translation ?? '';
                        if (shouldImport(translation, local)) {
                            set_dd(data.lang, type, name, '', translation, hash, false);
                        }
                    }
                }
            }
            else if (item.type == 'mycap-task') {
                if (!item.hasOwnProperty('form')) continue;
                if (!config.projMeta.forms.hasOwnProperty(item.form)) continue;
                if (!config.projMeta.forms[item.form].hasOwnProperty('myCapTaskId')) continue;
                const taskId = config.projMeta.forms[item.form].myCapTaskId.toString();
                for (const type in config.projMeta.myCap.orderedListOfTaskItems) {
                    if (!item.hasOwnProperty('task-'+type)) continue;
                    if (!item['task-'+type].hasOwnProperty('translation')) continue;
                    const translation = item['task-'+type].translation ?? '';
                    const hash = item['task-'+type].hasOwnProperty('hash') ? item['task-'+type].hash : null;
                    const local = get_dd(data.lang, 'task-'+type, '').translation ?? '';
                    if (shouldImport(translation, local)) {
                        set_dd(data.lang, 'task-'+type, taskId, '', translation, hash, false);
                    }
                }
            }
        }
    }
    //#endregion
    //#endregion
    log("Merged language:", '\nSource:', data.fileData, '\nMerged:', data.lang);
}

/**
 * Gets the event id for the given unique event name
 * @param {string} uniqueEventName 
 * @returns {string|null} Returns `null` in case the event does not exists
 */
function getEventIdFromUniqueEventName(uniqueEventName) {
    for (const event_id of Object.keys(config.projMeta.events)) {
        const event = config.projMeta.events[event_id]
        if (event.uniqueEventName == uniqueEventName) {
            return event_id
        }
    }
    return null
}

/**
 * Gets the alert id for the given unique alert name
 * @param {AlertsImportData} alertData 
 * @returns {string|null} Returns `null` in case the alert does not exists
 */
function getAlertIdFromAlertData(alertData) {
    if (alertData.hasOwnProperty('pid-pk')) {
        const pid_pk = alertData['pid-pk'].split('-')
        if (pid_pk.length == 2) {
            const pid = pid_pk[0]
            const alert_id = pid_pk[1]
            if (pid == config.projMeta.pid && config.projMeta.alerts.hasOwnProperty(alert_id)) {
                return alert_id
            }
        }
    }
    for (const alert_id of Object.keys(config.projMeta.alerts)) {
        const alert = config.projMeta.alerts[alert_id]
        if (alert.alertNum == alertData.id) {
            return alert_id
        }
    }
    return null
}

//#endregion

//#region ---- Delete Modal

/** @type {DeleteModal} The delete confirmation modal */
var delModal = {
    _$: $(),
    _modal: null, // bootstrap.Modal
    _langKey: '',
    init: function(selector) {
        // Modal
        delModal._$ = (typeof selector == 'string') ? $(selector) : selector;
        delModal._modal = new bootstrap.Modal(delModal._$[0], { backdrop: 'static' });
        // Events
        delModal._$.find('[action]').on('click', delModal.act);
        delModal._langKey = '';
    },
    show: function(langKey) {
        delModal._langKey = langKey;
        delModal._$.find('.modal-language-key').text(delModal._langKey + ': ' + config.settings.langs[delModal._langKey]['display']);
        // @ts-ignore
        delModal._modal.show();
    },
    act: function(event) {
        const action = event.currentTarget.getAttribute('action');
        if (action == 'delete') {
            delModal.delete(delModal._langKey);
        }
        // @ts-ignore
        delModal._modal.hide();
        delModal._langKey = '';
    },
    delete: function(lang) {
        log('Deleting language \'' + lang + '\'');
        if (config.mode == 'System') {
            // Store deleted GUIDs
            const guid = config.settings.langs[lang].guid;
            if (guid) config.settings.deleted[guid] = true;
        }
        removeLangFromHashCache(lang);
        delete config.settings.langs[lang];
        // Get currently available languages
        const langs = Object.keys(config.settings.langs);
        // Adjust stuff if necessary
        if (langs.length == 0) {
            currentLanguage = '';
            config.settings.refLang = '';
            config.settings.fallbackLang = '';
        }
        else {
            if (config.settings.refLang == lang) {
                // Set first language in list to be the new default
                config.settings.refLang = langs[0];
            }
            if (config.settings.fallbackLang == lang) {
                // Set first language in list to be the new default
                config.settings.fallbackLang = langs[0];
            }
            if (currentLanguage == lang) {
                // Set the default language as the new current language
                currentLanguage = config.settings.refLang;
            }
        }
        updateSwitcher(true);
        renderLanguagesTable();
        updateTranslations();
        checkDirty();
    }
}

//#endregion

//#region ---- Export Modal

/** @type {ExportModal} The export modal */
var exportModal = {
    _$: $(),
    _modal: null, // bootstrap.Modal
    _langKey: '',
    _mode: 'all',
    _form: '',
    _promise: null,
    _close: null,
    init: function(selector) {
        // Modal
        exportModal._$ = (typeof selector == 'string') ? $(selector) : selector
        exportModal._modal = new bootstrap.Modal(exportModal._$[0], { backdrop: 'static' })
        // Events
        exportModal._$.find('[action]').on('click', exportModal.act)
        exportModal._langKey = ''
    },
    show: function(langKey, mode = 'all', form = '') {
        exportModal._langKey = langKey;
        exportModal._mode = mode;
        exportModal._form = form;
        exportModal._promise = new Promise(function(resolve, reject) {
            exportModal._close = resolve;
        })
        if (mode == 'general') {
            exportModal._$.find('.mlm-hide-when-exporting-general').hide();
            exportModal._$.find('.mlm-show-when-exporting-general').show();
            exportModal._$.find('.mlm-disable-when-exporting-general').prop('disabled', true);
            exportModal._$.find('#export-format-json').prop('checked', true);
            
        }
        else {
            exportModal._$.find('.modal-language-key').text(exportModal._langKey + ': ' + config.settings.langs[exportModal._langKey]['display']);
            exportModal._$.find('.mlm-hide-when-exporting-general').show();
            exportModal._$.find('.mlm-show-when-exporting-general').hide();
            exportModal._$.find('.mlm-disable-when-exporting-general').prop('disabled', false);
        }
        // Show or hide elements depending on export mode
        switch (mode) {
            case 'changes':
                exportModal._$.find('.mlm-hide-when-exporting-changes').hide();
                exportModal._$.find('.mlm-disable-when-exporting-changes').prop('checked', true).prop('disabled', true);
                break;
            case 'single-form':
                exportModal._$.find('input[name="export-include-surveyqueue"]').prop('checked', false);
                exportModal._$.find('.mlm-hide-when-exporting-changes').show();
                exportModal._$.find('.mlm-disable-when-exporting-changes').prop('disabled', false);
                exportModal._$.find('.mlm-export-items div').not('.mlm-single-form-export-item').hide();
                break;
            case 'general':
                break;
            default:
                exportModal._$.find('.mlm-hide-when-exporting-changes').show();
                exportModal._$.find('.mlm-disable-when-exporting-changes').prop('disabled', false);
                exportModal._$.find('.mlm-export-items div').show();
                break;
        }
        exportModal._modal._config = mode == 'changes' ? {} : { backdrop: 'static' };
        // Clear all visible tooltips
        $('[data-bs-toggle="tooltip"]').tooltip('hide');
        exportModal._modal.show();
        return exportModal._promise;
    },
    act: function(event) {
        var action = event.currentTarget.getAttribute('action')
        if (action == 'download') {
            exportModal.download()
        }
        // @ts-ignore
        exportModal._modal.hide()
        exportModal._langKey = ''
        if (exportModal._close != null) {
            exportModal._close(null)
        }
    },
    download: function() {
        log('Requesting export file for [' + exportModal._langKey + '].')
        const expItems = {}
        const isProject = config.mode == 'Project'
        const isSystem = config.mode == 'System'
        const isChangesExport = exportModal._mode == 'changes'
        const isSingleFormExport = exportModal._mode == 'single-form'
        // Set what to export
        // UI is always on for system and changes, but never for single-form export
        expItems.ui          = !isSingleFormExport && 
                            (
                                isSystem || 
                                isChangesExport || 
                                exportModal._$.find('input[name="export-include-ui"]').is(":checked")
                            )
        // Project-only items
        // Instrument-related items are always governed by checkbox status
        expItems.forms       = isProject && exportModal._$.find('input[name="export-include-forms"]').is(":checked")
        expItems.fields      = isProject && exportModal._$.find('input[name="export-include-fields"]').is(":checked")
        expItems.surveys     = isProject && exportModal._$.find('input[name="export-include-surveysettings"]').is(":checked")
        expItems.asis        = isProject && exportModal._$.find('input[name="export-include-asis"]').is(":checked")
        expItems.mycap       = isProject && exportModal._$.find('input[name="export-include-mycap"]').is(":checked")
        // Other items are always off for single form exports
        expItems.surveyqueue = isProject && !isSingleFormExport && exportModal._$.find('input[name="export-include-surveyqueue"]').is(":checked")
        expItems.alerts      = isProject && !isSingleFormExport && exportModal._$.find('input[name="export-include-alerts"]').is(":checked")
        expItems.events      = isProject && !isSingleFormExport && exportModal._$.find('input[name="export-include-events"]').is(":checked")
        expItems.mdc         = isProject && !isSingleFormExport && exportModal._$.find('input[name="export-include-mdc"]').is(":checked")
        expItems.pdf         = isProject && !isSingleFormExport && exportModal._$.find('input[name="export-include-pdf"]').is(":checked")
        expItems.protemail   = isProject && !isSingleFormExport && exportModal._$.find('input[name="export-include-protemail"]').is(":checked")
        // Note: 'changes' exports will consider all types checked (this is done in PHP)

        // Export format
        const expFormat = $('input[name="export-format"]:checked').val() ?? 'json'
        const expDelimiter = $('input[name="export-csv-format"]:checked').val() ?? 'comma'
        // Build export request
        const exportRequest = {
            lang: exportModal._langKey,
            limit: expItems,
            prompts: exportModal._$.find('#export-prompts').is(":checked"),
            defaults: exportModal._$.find('#export-defaults').is(":checked"),
            notes: exportModal._$.find('#export-notes').is(":checked"),
            format: expFormat,
            items: {},
            form: '',
            mode: exportModal._mode,
        }
        if (exportModal._mode == 'changes') {
            exportRequest.items = itemsChangedSinceTranslated[exportModal._langKey]
        }
        else if (exportModal._mode == 'single-form') {
            exportRequest.items = getSingleFormExportItems(exportModal._form);
            exportRequest.form = exportModal._form;
        }
        // Export
        const ajaxAction = exportModal._mode == 'general' ? 'export-general' : 'export-lang'
        const ajaxPayload = exportModal._mode == 'general' ? null : exportRequest
        ajax(ajaxAction, ajaxPayload)
        .then(function(data) {
            const filename = data.name +
                (expFormat == 'json' ? '.json' : '.csv')
            let blob = null
            if (expFormat == 'json') {
                const json = JSON.stringify(data.content, null, 4)
                blob = new Blob([json], { type: "text/plain;charset=utf-8" })
            }
            else {
                const delimiterMap = {
                    comma: ',',
                    semicolon: ';',
                    tab: '\t', 
                }
                const csv = generateCSV(data.content, delimiterMap[expDelimiter] ?? ',')
                blob = new Blob([
                    new Uint8Array([0xEF, 0xBB, 0xBF]), 
                    csv
                ], { type: "text/plain;charset=utf-8" })
            }
            // @ts-ignore
            saveAs(blob, filename)
        })
        .catch(function(err) {
            showToastMLM('#mlm-errorToast', $lang.multilang_185) // Failed to create the export. Please check the browser console (F12) for details.
            console.error(err)
        })
    }
}

function toCsvString(s) {
    if (typeof s == 'string') {
        return '"' + s.replace(new RegExp('"', 'g'), '""') + '"'
    }
    return s
}

/**
 * Generates a CSV from the export datastructure
 * @param {Object} data 
 * @param {string} delimiter 
 * @returns 
 */
function generateCSV(data, delimiter) {
    const linebreak = "\n"
    const csv = []
    // Informational (prefixed with # to indicated comments)
    csv.push('#' + delimiter + '"' + data.creator + '"')
    csv.push('#' + delimiter + '"' + data.version + '"')
    csv.push('#' + delimiter + '"' + data.timestamp + '"')
    csv.push('#' + delimiter + '"' + data.instructions + '"')
    // Separating line
    csv.push('#' + delimiter)
    // Header row
    csv.push(['section','type','name','index','kind','text'].join(delimiter))
    // Language data
    csv.push(['lang', 'info', 'key', '', 'value', toCsvString(data.key)].join(delimiter))
    csv.push(['lang', 'info', 'display', '', 'value', toCsvString(data.display)].join(delimiter))
    csv.push(['lang', 'info', 'rtl', '', 'value', data.rtl ? 1 : 0].join(delimiter))
    csv.push(['lang', 'info', 'sort', '', 'value', toCsvString(data.sort)].join(delimiter))
    // Process data in specific order
    const sections = ['uiTranslations','uiOptions','fieldTranslations','matrixTranslations','formTranslations','eventTranslations','surveyTranslations','sqTranslations','asiTranslations','alertTranslations','mdcTranslations', 'pdfTranslations', 'protemailTranslations', 'myCapTranslations']
    sections.forEach(function(section) {
        if (!data.hasOwnProperty(section)) return
        for (var item_num = 0; item_num < data[section].length; item_num++) {
            const item = data[section][item_num]
            var type = ''
            var name = item.id
            var index = ''
            var text = ''
            switch (section) {
                case 'uiTranslations':
                    ['prompt','default','translation','hash'].forEach(function(kind) {
                        if (item.hasOwnProperty(kind)) {
                            text = toCsvString(item[kind])
                            csv.push([section, type, name, index, kind, text].join(delimiter))
                        }
                    })
                    break
                case 'uiOptions':
                    ['prompt','default','value'].forEach(function(kind) {
                        if (item.hasOwnProperty(kind)) {
                            text = kind == 'value' ? (item[kind] ? 1 : 0) : toCsvString(item[kind])
                            csv.push([section, type, name, index, kind, text].join(delimiter))
                        }
                    })
                    break
                case 'fieldTranslations':
                case 'matrixTranslations':
                    ['header','label','note','video_url'].forEach(function(type) {
                        if (item.hasOwnProperty(type)) {
                            ['prompt','default','hash','translation'].forEach(function(kind) {
                                if (item[type].hasOwnProperty(kind)) {
                                    text = toCsvString(item[type][kind])
                                    csv.push([section, type, name, index, kind, text].join(delimiter))
                                }
                            })
                        }
                    });
                    ['enum','actiontags'].forEach(function(type) {
                        if (item.hasOwnProperty(type)) {
                            for (var i = 0; i < item[type].length; i++) {
                                const indexedItem = item[type][i]
                                index = indexedItem.id;
                                ['prompt','default','hash','translation'].forEach(function(kind) {
                                    if (indexedItem.hasOwnProperty(kind)) {
                                        text = toCsvString(indexedItem[kind])
                                        csv.push([section, type, name, index, kind, text].join(delimiter))
                                    }
                                })
                                }
                        }
                    })
                    break
                case 'formTranslations':
                    // Form active state
                    csv.push([section, 'active', name, index, 'value', item.active ? 1 : 0].join(delimiter));
                    // Form items
                    ['name'].forEach(function(type) {
                        if (item.hasOwnProperty(type)) {
                            ['prompt','default','hash','translation'].forEach(function(kind) {
                                if (item[type].hasOwnProperty(kind)) {
                                    text = toCsvString(item[type][kind])
                                    csv.push([section, type, name, index, kind, text].join(delimiter))
                                }
                            })
                        }
                    })
                    break
                case 'eventTranslations':
                    ['name','custom_event_label'].forEach(function(type) {
                        if (item.hasOwnProperty(type)) {
                            ['prompt','default','hash','translation'].forEach(function(kind) {
                                if (item[type].hasOwnProperty(kind)) {
                                    text = toCsvString(item[type][kind])
                                    csv.push([section, type, name, index, kind, text].join(delimiter))
                                }
                            })
                        }
                    })
                    break
                case 'surveyTranslations':
                    // Survey active state
                    csv.push([section, 'active', name, index, 'value', item.active ? 1 : 0].join(delimiter));
                    // Survey settings
                    for (var i = 0; i < item.settings.length; i++) {
                        const setting = item.settings[i]
                        type = setting.id;
                        ['prompt','default','hash','translation'].forEach(function(kind) {
                            if (setting.hasOwnProperty(kind)) {
                                text = toCsvString(setting[kind])
                                csv.push([section, type, name, index, kind, text].join(delimiter))
                            }
                        })
                    }
                    break
                case 'sqTranslations':
                    ['prompt','default','hash','translation'].forEach(function(kind) {
                        if (item.hasOwnProperty(kind)) {
                            text = toCsvString(item[kind])
                            csv.push([section, type, name, index, kind, text].join(delimiter))
                        }
                    })
                    break
                case 'asiTranslations':
                    // ASI settings
                    for (var i = 0; i < item.settings.length; i++) {
                        const setting = item.settings[i]
                        type = setting.id;
                        ['prompt','default','hash','translation'].forEach(function(kind) {
                            if (setting.hasOwnProperty(kind)) {
                                text = toCsvString(setting[kind])
                                csv.push([section, type, name, index, kind, text].join(delimiter))
                            }
                        })
                    }
                    break
                case 'alertTranslations':
                    // Alert title
                    if (item.hasOwnProperty('title')) {
                        csv.push([section, type, name, index, 'title', toCsvString(item.title)].join(delimiter))
                    }
                    // Alert pid-pk
                    if (item.hasOwnProperty('pid-pk')) {
                        csv.push([section, type, name, index, 'pid-pk', toCsvString(item['pid-pk'])].join(delimiter))
                    }
                    // Alert settings
                    for (var i = 0; i < item.settings.length; i++) {
                        const setting = item.settings[i]
                        type = setting.id;
                        ['prompt','default','hash','translation'].forEach(function(kind) {
                            if (setting.hasOwnProperty(kind)) {
                                text = toCsvString(setting[kind])
                                csv.push([section, type, name, index, kind, text].join(delimiter))
                            }
                        })
                    }
                    break
                case 'mdcTranslations':
                case 'pdfTranslations':
                case 'protemailTranslations':
                    ['prompt','default','hash','translation'].forEach(function(kind) {
                        if (item.hasOwnProperty(kind)) {
                            text = toCsvString(item[kind])
                            csv.push([section, type, name, index, kind, text].join(delimiter))
                        }
                    })
                    break
                case 'myCapTranslations':
                    if (item.type == 'mycap-task') {
                        name = item.form ? item.form : (item.id ? item.id : '');
                        index = '';
                        for (const key in item) {
                            if (key.startsWith('task-')) {
                                type = key;
                                const myCapItem = item[key];
                                ['prompt','default','hash','translation'].forEach(function(kind) {
                                    if (myCapItem.hasOwnProperty(kind)) {
                                        text = toCsvString(myCapItem[kind]);
                                        csv.push([section, type, name, index, kind, text].join(delimiter))
                                    }
                                });
                            }
                        }
                        // Event-specific settings
                        for (const eventId in config.projMeta.events) {
                            const event = config.projMeta.events[eventId];
                            if (item.hasOwnProperty(event.uniqueEventName)) {
                                for (const key in item[event.uniqueEventName]) {
                                    if (key.startsWith('task-')) {
                                        type = key;
                                        index = event.uniqueEventName;
                                        const myCapItem = item[event.uniqueEventName][key];
                                        ['prompt','default','hash','translation'].forEach(function(kind) {
                                            if (myCapItem.hasOwnProperty(kind)) {
                                                text = toCsvString(myCapItem[kind]);
                                                csv.push([section, type, name, index, kind, text].join(delimiter))
                                            }
                                        });
                                    }
                                }
                            }
                        }
                    }
                    else {
                        for (const key in item) {
                            if (key.startsWith('mycap-')) {
                                type = key;
                                name = item.id ? item.id : '';
                                index = '';
                                const myCapItem = item[key];
                                ['prompt','default','hash','translation'].forEach(function(kind) {
                                    if (myCapItem.hasOwnProperty(kind)) {
                                        text = toCsvString(myCapItem[kind]);
                                        csv.push([section, type, name, index, kind, text].join(delimiter))
                                    }
                                });
                            }
                        }
                    }
                    break; 
                default:
                    break
            }
        }
    })
    // Join lines and return, also - add a UTF8 byte order mark
    return '\ufeff' + csv.join(linebreak)
}


/**
 * Parses a CSV string and constructs an object for importing
 * @param {string} csvString
 * @returns {Language}
 */
function parseCsvIntoLang(csvString) {
    const lang = {
        creator: 'REDCap MLM',
        key: '',
        display: '',
        sort: '',
        'recaptcha-lang': '',
        rtl: false,
        /** @type object[] */
        uiTranslations: [],
        /** @type object[] */
        uiOptions: [],
        /** @type object[] */
        formTranslations: [],
        /** @type object[] */
        eventTranslations: [],
        /** @type object[] */
        fieldTranslations: [],
        /** @type object[] */
        matrixTranslations: [],
        /** @type object[] */
        surveyTranslations: [],
        /** @type object[] */
        sqTranslations: [],
        /** @type object[] */
        asiTranslations: [],
        /** @type object[] */
        alertTranslations: [],
        /** @type object[] */
        mdcTranslations: [],
        /** @type object[] */
        pdfTranslations: [],
        /** @type object[] */
        protemailTranslations: [],
        /** @type object[] */
        myCapTranslations: [],
        ui: {},
        dd: {}
    }
    // Sanitize (Excel badly screws things up)
    const lines = csvString.split('\n')
    const sanitizedLines = []
    for (const line of lines) {
        if (line.trim() == '') continue
        if (line.trim().startsWith('#')) continue
        // Ignore 'all delimiter' lines
        if ('\t;,'.includes(line.trim()[0]) && line.replace(new RegExp(line.trim()[0], 'g'), '').trim() == '') continue
        sanitizedLines.push(line)
    }
    const parseOptions = {
        delimiter: '', // auto-detect
        newline: '', // auto-detect
        quoteChar: '"',
        escapeChar: '"',
        header: true,
        comments: '#',
        delimitersToGuess: [',', '\t', ';'],
        skipEmptyLines: true,
    }
    // Parse
    // @ts-ignore (papaparse.min.js)
    const csv = Papa.parse(sanitizedLines.join('\n'), parseOptions)
    // Output errors as warnings to console
    if (csv.errors.length) {
        console.error($lang.multilang_219)
        for (const error of csv.errors) {
            console.warn(error.message + ' - [' + error.row + ']', csv.data[error.row])
        }
    }
    // Verify that the required headers are present
    const actualDelimiter = csv.meta.delimiter ?? ',';
    if (csv.meta.fields.length < 6 || csv.meta.fields.slice(0, 6).join(actualDelimiter) != ['section','type','name','index','kind','text'].join(actualDelimiter)) {
        console.error(interpolateString($lang.multilang_783, [csv.meta.fields.join(actualDelimiter)]));
        throw 'Parsing of CSV failed';
    }
    
    // Process each row (section, type, name, index, kind, text) and assemble data
    const data = {
        uiTranslations: {},
        formTranslations: {},
        eventTranslations: {},
        fieldTranslations: {},
        matrixTranslations: {},
        surveyTranslations: {},
        sqTranslations: {},
        asiTranslations: {},
        alertTranslations: {},
        mdcTranslations: {},
        pdfTranslations: {},
        protemailTranslations: {},
        myCapTranslations: {}
    }
   
    for (const line of csv.data) {
        // Skip incomplete lines
        if (Object.keys(line).length < 6) continue
        switch (line.section) {
            case 'lang':
                if (line.type == 'info' && line.index == '' && line.kind == 'value') {
                    if (lang.hasOwnProperty(line.name)) {
                        lang[line.name] = line.text
                    }
                } 
                break
            case 'uiOptions':
                // uiOptions have no (meaningful) hash and thus can only come from a single line
                if (line.kind == 'value') {
                    lang.uiOptions.push({
                        id: line.name,
                        value: line.text == '1'
                    })
                }
                break
            case 'uiTranslations':
                if (typeof data.uiTranslations[line.name] == 'undefined') {
                    data.uiTranslations[line.name] = {
                        id: line.name,
                        translation: '',
                        hash: '',
                    }
                }
                if (line.kind == 'translation') {
                    data.uiTranslations[line.name].translation = line.text
                }
                if (line.kind == 'hash') {
                    data.uiTranslations[line.name].hash = line.text
                }
                break
            case 'formTranslations':
                if (typeof data.formTranslations[line.name] == 'undefined') {
                    data.formTranslations[line.name] = {
                        id: line.name,
                        active: false,
                        name: {},
                    }
                }
                if (line.type == 'active' && line.kind == 'value') {
                    data.formTranslations[line.name].active = line.text == '1'
                }
                if ([
                    'name'
                ].includes(line.type) && [
                    'hash',
                    'translation'
                ].includes(line.kind)) {
                    data.formTranslations[line.name][line.type][line.kind] = line.text
                }
                break
            case 'eventTranslations':
                if (typeof data.eventTranslations[line.name] == 'undefined') {
                    data.eventTranslations[line.name] = {
                        id: line.name,
                        name: {},
                        custom_event_label: {},
                    }
                }
                if ([
                    'name',
                    'custom_event_label'
                ].includes(line.type) && [
                    'hash',
                    'translation'
                ].includes(line.kind)) {
                    data.eventTranslations[line.name][line.type][line.kind] = line.text
                }
                break
            case 'fieldTranslations':
                if (typeof data.fieldTranslations[line.name] == 'undefined') {
                    data.fieldTranslations[line.name] = {
                        id: line.name,
                        header: {},
                        label: {},
                        note: {},
                        video_url: {},
                        enum: {},
                        actiontags: {},
                    }
                }
                if ([
                    'header',
                    'label',
                    'note',
                    'video_url'
                ].includes(line.type) && [
                    'hash',
                    'translation'
                ].includes(line.kind)) {
                    data.fieldTranslations[line.name][line.type][line.kind] = line.text
                }
                if ([
                    'enum',
                    'actiontags'
                ].includes(line.type) && [
                    'hash',
                    'translation'
                ].includes(line.kind)) {
                    if (typeof data.fieldTranslations[line.name][line.type][line.index] == 'undefined') {
                        data.fieldTranslations[line.name][line.type][line.index] = {}
                    }
                    data.fieldTranslations[line.name][line.type][line.index][line.kind] = line.text
                }
                break
            case 'matrixTranslations':
                if (typeof data.matrixTranslations[line.name] == 'undefined') {
                    data.matrixTranslations[line.name] = {
                        id: line.name,
                        header: {},
                        enum: {},
                    }
                }
                if ([
                    'header'
                ].includes(line.type) && [
                    'hash',
                    'translation'
                ].includes(line.kind)) {
                    data.matrixTranslations[line.name][line.type][line.kind] = line.text
                }
                if ([
                    'enum'
                ].includes(line.type) && [
                    'hash',
                    'translation'
                ].includes(line.kind)) {
                    if (typeof data.matrixTranslations[line.name][line.type][line.index] == 'undefined') {
                        data.matrixTranslations[line.name][line.type][line.index] = {}
                    }
                    data.matrixTranslations[line.name][line.type][line.index][line.kind] = line.text
                }
                break
            case 'surveyTranslations':
                if (typeof data.surveyTranslations[line.name] == 'undefined') {
                    data.surveyTranslations[line.name] = {
                        id: line.name,
                        active: false,
                        settings: {
                            'survey-acknowledgement': {},
                            'survey-confirmation_email_content': {},
                            'survey-confirmation_email_from_display': {},
                            'survey-confirmation_email_subject': {},
                            'survey-end_survey_redirect_url': {},
                            'survey-instructions': {},
                            'survey-logo_alt_text': {},
                            'survey-offline_instructions': {},
                            'survey-repeat_survey_btn_text': {},
                            'survey-response_limit_custom_text': {},
                            'survey-stop_action_acknowledgement': {},
                            'survey-survey_btn_text_next_page': {},
                            'survey-survey_btn_text_prev_page': {},
                            'survey-survey_btn_text_submit': {},
                            'survey-text_to_speech': {},
                            'survey-text_to_speech_language': {},
                            'survey-title': {}
                        },
                    }
                }
                if (line.type == 'active' && line.kind == 'value') {
                    data.surveyTranslations[line.name].active = line.text == '1'
                }
                if ([
                    'survey-acknowledgement',
                    'survey-confirmation_email_content',
                    'survey-confirmation_email_from_display',
                    'survey-confirmation_email_subject',
                    'survey-end_survey_redirect_url',
                    'survey-instructions',
                    'survey-logo_alt_text',
                    'survey-offline_instructions',
                    'survey-repeat_survey_btn_text',
                    'survey-response_limit_custom_text',
                    'survey-stop_action_acknowledgement',
                    'survey-survey_btn_text_next_page',
                    'survey-survey_btn_text_prev_page',
                    'survey-survey_btn_text_submit',
                    'survey-text_to_speech',
                    'survey-text_to_speech_language',
                    'survey-title'
                ].includes(line.type) && [
                    'hash',
                    'translation'
                ].includes(line.kind)) {
                    data.surveyTranslations[line.name]['settings'][line.type][line.kind] = line.text
                }
                break
            case 'sqTranslations':
                if ([
                    'sq-survey_queue_custom_text',
                    'sq-survey_auth_custom_message'
                ].includes(line.name)) {
                    if (typeof data.sqTranslations[line.name] == 'undefined') {
                        data.sqTranslations[line.name] = {
                            id: line.name,
                        }
                    }
                    if (['hash','translation'].includes(line.kind)) {
                        data.sqTranslations[line.name][line.kind] = line.text
                    }
                }
                break
            case 'asiTranslations':
                if (typeof data.asiTranslations[line.name] == 'undefined') {
                    const id_parts = line.name.split('-')
                    if (id_parts.length != 2) break
                    data.asiTranslations[line.name] = {
                        event: id_parts[0], 
                        form: id_parts[1],
                        id: line.name,
                        settings: {},
                    }
                }
                if ([
                    'asi-email_subject',
                    'asi-email_content',
                    'asi-email_sender_display'
                ].includes(line.type) && [
                    'hash',
                    'translation'
                ].includes(line.kind)) {
                    if (typeof data.asiTranslations[line.name].settings[line.type] == 'undefined') {
                        data.asiTranslations[line.name]['settings'][line.type] = {}
                    }
                    data.asiTranslations[line.name]['settings'][line.type][line.kind] = line.text
                }
                break
            case 'alertTranslations':
                var template_data_type = 'alert-sendgrid_template_data'
                if (typeof data.alertTranslations[line.name] == 'undefined') {
                    data.alertTranslations[line.name] = {
                        id: line.name,
                        settings: {
                            'alert-email_from_display': {},
                            'alert-email_subject': {},
                            'alert-alert_message': {},
                            'alert-sendgrid_template_data': {}
                        },
                    }
                }
                if ([
                    'alert-email_from_display',
                    'alert-email_subject',
                    'alert-alert_message'
                ].includes(line.type) && [
                    'hash',
                    'translation'
                ].includes(line.kind)) {
                    if (typeof data.alertTranslations[line.name]['settings'][line.type] == 'undefined') {
                        data.alertTranslations[line.name]['settings'][line.type] = {}
                    }
                    data.alertTranslations[line.name]['settings'][line.type][line.kind] = line.text
                }
                
                if (line.type.startsWith(template_data_type)) {
                    if (line.kind === 'hash') {
                        data.alertTranslations[line.name]['settings'][template_data_type][line.kind] = line.text
                    }
                    if (line.kind === 'translation') {
                        var key = line.type.split(':')[1] || null
                        if (key) {
                            var template_data_translations_string = data.alertTranslations[line.name]['settings'][template_data_type][line.kind]
                            if (template_data_translations_string) {
                                var template_data_translations = JSON.parse(template_data_translations_string)
                                template_data_translations[key] = line.text
                                template_data_translations_string = JSON.stringify(template_data_translations)
                            } else {
                                template_data_translations_string = JSON.stringify({key: line.text})
                            }
                            data.alertTranslations[line.name]['settings'][template_data_type][line.kind] = template_data_translations_string
                        }
                    }
                }
                break
            case 'mdcTranslations':
            case 'pdfTranslations':
            case 'protemailTranslations':
                if ([
                    'hash',
                    'translation'
                ].includes(line.kind)) {
                    if (typeof data[line.section][line.name] == 'undefined') {
                        data[line.section][line.name] = {
                            id: line.name,
                        }
                    }
                    data[line.section][line.name][line.kind] = line.text
                }
                break
            case 'myCapTranslations':
                if (line.type == 'mycap-app_title') {
                    if (typeof data[line.section][line.type] == 'undefined') {
                        data[line.section][line.type] = {
                            type: 'mycap-app_title',
                            'mycap-app_title': {}
                        };
                    }
                    if (line.kind == 'hash' || line.kind == 'translation') {
                        data[line.section][line.type]['mycap-app_title'][line.kind] = line.text;
                    }
                }
                else if (line.type.startsWith('mycap-about-')) {
                    if (line.kind == 'hash' || line.kind == 'translation') {
                        if (typeof data[line.section]['mycap-pages'] == 'undefined') {
                            data[line.section]['mycap-pages'] = {};
                        }
                        if (typeof data[line.section]['mycap-pages'][line.name] == 'undefined') {
                            data[line.section]['mycap-pages'][line.name] = {
                                type: 'mycap-pages',
                                id: line.name
                            };
                        }
                        if (typeof data[line.section]['mycap-pages'][line.name][line.type] == 'undefined') {
                            data[line.section]['mycap-pages'][line.name][line.type] = {};
                        }
                        data[line.section]['mycap-pages'][line.name][line.type][line.kind] = line.text;
                    }
                }
                else if (line.type.startsWith('mycap-contact-')) {
                    if (line.kind == 'hash' || line.kind == 'translation') {
                        if (typeof data[line.section]['mycap-contacts'] == 'undefined') {
                            data[line.section]['mycap-contacts'] = {};
                        }
                        if (typeof data[line.section]['mycap-contacts'][line.name] == 'undefined') {
                            data[line.section]['mycap-contacts'][line.name] = {
                                type: 'mycap-contacts',
                                id: line.name
                            };
                        }
                        if (typeof data[line.section]['mycap-contacts'][line.name][line.type] == 'undefined') {
                            data[line.section]['mycap-contacts'][line.name][line.type] = {};
                        }
                        data[line.section]['mycap-contacts'][line.name][line.type][line.kind] = line.text;
                    }
                }
                else if (line.type.startsWith('mycap-link-')) {
                    if (line.kind == 'hash' || line.kind == 'translation') {
                        if (typeof data[line.section]['mycap-links'] == 'undefined') {
                            data[line.section]['mycap-links'] = {};
                        }
                        if (typeof data[line.section]['mycap-links'][line.name] == 'undefined') {
                            data[line.section]['mycap-links'][line.name] = {
                                type: 'mycap-links',
                                id: line.name
                            };
                        }
                        if (typeof data[line.section]['mycap-links'][line.name][line.type] == 'undefined') {
                            data[line.section]['mycap-links'][line.name][line.type] = {};
                        }
                        data[line.section]['mycap-links'][line.name][line.type][line.kind] = line.text;
                    }
                }
                else if (line.type.startsWith('mycap-baseline_')) {
                    if (line.kind == 'hash' || line.kind == 'translation') {
                        if (typeof data[line.section]['mycap-baseline_task'] == 'undefined') {
                            data[line.section]['mycap-baseline_task'] = {
                                type: 'mycap-baseline_task'
                            };
                        }
                        if (typeof data[line.section]['mycap-baseline_task'][line.type] == 'undefined') {
                            data[line.section]['mycap-baseline_task'][line.type] = {};
                        }
                        data[line.section]['mycap-baseline_task'][line.type][line.kind] = line.text;
                    }
                }
                else if (line.type.startsWith('task-')) {
                    if (line.kind == 'hash' || line.kind == 'translation') {
                        if (typeof data[line.section]['mycap-task'] == 'undefined') {
                            data[line.section]['mycap-task'] = {};
                        }
                        if (typeof data[line.section]['mycap-task'][line.name] == 'undefined') {
                            data[line.section]['mycap-task'][line.name] = {
                                type: 'mycap-task',
                                form: line.name
                            };
                        }
                        if (line.index.indexOf('_') > 0) {
                            if (typeof data[line.section]['mycap-task'][line.name][line.index] == 'undefined') {
                                data[line.section]['mycap-task'][line.name][line.index] = {};
                            }
                            if (typeof data[line.section]['mycap-task'][line.name][line.index][line.type] == 'undefined') {
                                data[line.section]['mycap-task'][line.name][line.index][line.type] = {};
                            }
                            data[line.section]['mycap-task'][line.name][line.index][line.type][line.kind] = line.text;
                        }
                        else if (line.index == '') {
                            if (typeof data[line.section]['mycap-task'][line.name][line.type] == 'undefined') {
                                data[line.section]['mycap-task'][line.name][line.type] = {};
                            }
                            data[line.section]['mycap-task'][line.name][line.type][line.kind] = line.text;
                        }
                    }
                }
                break;
            default:
                break
        }
    }
    // Transform first-level object hierarchy into arrays in lang
    for (const sectionName of Object.keys(data)) {
        const section = data[sectionName]
        for (const key of Object.keys(section)) {
            lang[sectionName].push(section[key])
        }
    }
    // Convert some nested objects into arrays
    // Field translation enums
    if (lang.hasOwnProperty('fieldTranslations')) {
        for (const ft of lang.fieldTranslations) {
            const enumItems = []
            for (const id of Object.keys(ft['enum'] ?? {})) {
                const item = ft.enum[id]
                item.id = id
                enumItems.push(item)
            }
            ft.enum = enumItems
        }
    }
    // Matrix translation enums
    if (lang.hasOwnProperty('matrixTranslations')) {
        for (const mt of lang.matrixTranslations) {
            const enumItems = []
            for (const id of Object.keys(mt['enum'] ?? {})) {
                const item = mt.enum[id]
                item.id = id
                enumItems.push(item)
            }
            mt.enum = enumItems
        }
    }
    // Survey settings translations
    if (lang.hasOwnProperty('surveyTranslations')) {
        for (const ss of lang.surveyTranslations) {
            const settings = []
            for (const id of Object.keys(ss.settings)) {
                const setting = {
                    id: id,
                    hash: ss.settings[id].hasOwnProperty('hash') ? ss.settings[id].hash : '',
                    translation: ss.settings[id].hasOwnProperty('translation') ? ss.settings[id].translation : '',
                }
                settings.push(setting)
            }
            ss.settings = settings
        }
    }
    // ASI translations
    if (lang.hasOwnProperty('asiTranslations')) {
        for (const asi of lang.asiTranslations) {
            const settings = []
            for (const id of Object.keys(asi.settings)) {
                const setting = {
                    id: id,
                    hash: asi.settings[id].hasOwnProperty('hash') ? asi.settings[id].hash : '',
                    translation: asi.settings[id].hasOwnProperty('translation') ? asi.settings[id].translation : '',
                }
                settings.push(setting)
            }
            asi.settings = settings
        }
    }
    // Alert translations
    if (lang.hasOwnProperty('alertTranslations')) {
        for (const alert of lang.alertTranslations) {
            const settings = []
            for (const id of Object.keys(alert.settings)) {
                const setting = {
                    id: id,
                    hash: alert.settings[id].hasOwnProperty('hash') ? alert.settings[id].hash : '',
                    translation: alert.settings[id].hasOwnProperty('translation') ? alert.settings[id].translation : ''
                }
                settings.push(setting)
            }
            alert.settings = settings
        }
    }
    // MyCap translations
    if (lang.hasOwnProperty('myCapTranslations')) {
        // Transform nested objects into arrays
        const myCapTranslations = [];
        for (const item of lang.myCapTranslations) {
            if (item.hasOwnProperty('type')) {
                myCapTranslations.push(item);
            }
            else {
                for (const key in item) {
                    myCapTranslations.push(item[key]);
                }
            }
        }
        lang.myCapTranslations = myCapTranslations;
    }
    return lang
}

/**
 * Strips a potential HTML string of all tags
 * @param {string} html 
 * @returns {string}
 */
function textOnly(html) {
    return $('<div></div>').html(html).text()
}


//#endregion

//#region ---- Import Modal (General Settings)

/** @type {GSImportModal} The add/edit language modal */
const gsiModal = {
    _$: $(), // The modal
    _modal: null, // bootstrap.Modal
    _$file: $(), // The file input
    _$customFile: $(), // The file wrapping div.custom-file
    _$cancel: $(), // The cancel button
    _$import: $(), // The save button
    _settings: {},
    setup: function() {
        // Store element references
        gsiModal._$ = $('#mlm-import-general-modal')
        gsiModal._modal = new bootstrap.Modal(gsiModal._$[0], { backdrop: 'static' })
        gsiModal._$file = gsiModal._$.find('input[name="mlm-import-file"]')
        gsiModal._$customFile = gsiModal._$.find('.custom-file')
        gsiModal._$cancel = gsiModal._$.find('button[data-mlm-action="cancel"]')
        gsiModal._$import = gsiModal._$.find('button[data-mlm-action="import"]')
        // Hook up some events
        gsiModal._$.on('hidden.bs.modal', function(e) {
            // @ts-ignore
            bsCustomFileInput.destroy()
        })
        gsiModal._$cancel.on('click', function(e) {
            // @ts-ignore
            gsiModal._modal.hide()
        })
        gsiModal._$import.on('click', function(e) {
            gsiModal._import()
        })
        gsiModal._$file.on('change', function(e) {
            gsiModal._readFile(e)
        }) 
        gsiModal._$file.on('keypress input click blur', function(e) {
            gsiModal._updateImportButtonState()
            gsiModal._updateFileWidget()
        })
        gsiModal._$customFile
        .on('dragover dragenter', function(e) {
            gsiModal._$customFile.addClass('is-dragover');
        })
        .on('dragleave dragend drop', function(e) {
            gsiModal._$customFile.removeClass('is-dragover');
        })
    },
    show: function() {
        log('Opening general settings import modal')
        // Reset state
        // @ts-ignore
        bsCustomFileInput.init()
        gsiModal._settings = {}
        gsiModal._$file.val('')
        gsiModal._updateImportButtonState()
        gsiModal._updateFileWidget()
        // Show
        // @ts-ignore
        gsiModal._modal.show()
    },
    _updateImportButtonState: function() {
        const enabled = gsiModal._settings != null
        gsiModal._$import.prop('disabled', !enabled)
    },
    _updateFileWidget: function() {
        const files = gsiModal._$file.prop('files')
        if (files.length == 0) {
            gsiModal._$.find('.processing-file').addClass('hide')
            gsiModal._$file.removeClass('is-invalid').removeClass('is-valid')
            setTimeout(function() {
                gsiModal._$.find('[data-rc-lang=multilang_34]').html($lang.multilang_34)
            }, 10)
        }
    
    },
    _readFile: function(event) {
        gsiModal._$file.removeClass('is-valid')
        const files = gsiModal._$file.prop('files')
        if (files.length === 1) {
            const reader = new FileReader()
            const filename = '' + files[0].name
            const ext = filename.includes('.') ? filename.split('.').reverse()[0].toLowerCase() : 'json' // Try JSON
            gsiModal._$.find('.processing-file').removeClass('hide')
            reader.onload = function(e) {
                if (e.target && e.target.result) {
                    gsiModal._processFile(e.target.result.toString(), ext).then(function(data) {
                        gsiModal._settings = data
                        gsiModal._$file.removeClass('is-invalid').addClass('is-valid')
                        // @ts-ignore
                        event.target?.setCustomValidity('')
                    }).catch(function(ex) {
                        gsiModal._settings = {}
                        gsiModal._$file.addClass('is-invalid')
                        // @ts-ignore
                        event.target?.setCustomValidity('Invalid')
                        if (config.settings.debug) {
                            error('Failed to import from file:', ex)
                        }
                    }).finally(function() {
                        gsiModal._updateImportButtonState()
                        gsiModal._$.find('.processing-file').addClass('hide')
                    })
                }
            }
            reader.onerror = function(e) {
                error('Failed to load file:', e)
            }
            reader.readAsText(files[0])
        }
    },
    _processFile: function(content, type) {
        return new Promise(function(resolve, reject) {
            if (type == 'json') {
                try {
                    const settings = JSON.parse(content)
                    // Must have 'creator' with "REDCap MLM"
                    if (settings['creator'] != 'REDCap MLM') {
                        throw 'Invalid file: Missing \'creator: "REDCap MLM"\''
                    }
                    resolve(settings)
                }
                catch (ex) {
                    reject(ex)
                }
            }
            else {
                reject($lang.multilang_614) // Invalid file type. Use .json files only.
            }
        })
    },
    _import: function() {
        // Get import settings
        const importLangsTab = gsiModal._$.find('input[name="gs-import-include-langs-tab"]').is(':checked') ?? false
        const importFormsTab = gsiModal._$.find('input[name="gs-import-include-forms-tab"]').is(':checked') ?? false
        const importAlertsTab = gsiModal._$.find('input[name="gs-import-include-alerts-tab"]').is(':checked') ?? false
        const importSettingsTab = gsiModal._$.find('input[name="gs-import-include-settings-tab"]').is(':checked') ?? false

        // Copy data
        const s = gsiModal._settings
        try {
            if (importLangsTab) {
                // Active languages
                if (typeof s.langActive == 'object') {
                    for (const langId of Object.keys(s.langActive)) {
                        const val = s.langActive[langId] == true
                        if (config.settings.langs.hasOwnProperty(langId)) {
                            config.settings.langs[langId].active = val
                        }
                    }
                }
                // Designated field
                if (typeof s.designatedField == 'string') {
                    const val = s.designatedField
                    if (config.projMeta.fields.hasOwnProperty(val) && 
                        $('select[data-mlm-config="designatedField"] option[value="' + val + '"]').length == 1) {
                        config.settings.designatedField = val
                    }
                }
                // Default language
                if (typeof s.refLang == 'string') {
                    const val = s.refLang
                    if (config.settings.langs.hasOwnProperty(val)) {
                        config.settings.refLang = val
                    }
                }
                // Fallback language
                if (typeof s.fallbackLang == 'string') {
                    const val = s.fallbackLang
                    if (config.settings.langs.hasOwnProperty(val)) {
                        config.settings.fallbackLang = val
                    }
                }
                // MyCap status
                if (typeof s.myCapActive == 'object') {
                    for (const langId of Object.keys(s.myCapActive)) {
                        const val = s.myCapActive[langId] == true;
                        if (config.settings.langs.hasOwnProperty(langId)) {
                            config.settings.langs[langId]["mycap-active"] = val
                        }
                    }
                }
            }
            if (importFormsTab) {
                // Enabled for data entry
                if (typeof s.dataEntryEnabled == 'object') {
                    for (const formName of Object.keys(s.dataEntryEnabled)) {
                        if (typeof s.dataEntryEnabled[formName] == 'object') {
                            for (const langId of Object.keys(s.dataEntryEnabled[formName])) {
                                if (typeof s.dataEntryEnabled[formName][langId] == 'boolean') {
                                    if (config.projMeta.forms.hasOwnProperty(formName) && config.settings.langs.hasOwnProperty(langId)) {
                                        const val = s.dataEntryEnabled[formName][langId]
                                        set_dd(config.settings.langs[langId], 'form-active', formName, '', val)
                                    }
                                }
                            }
                        }
                    }
                }
                // Enabled for surveys
                if (typeof s.surveyEnabled == 'object') {
                    for (const formName of Object.keys(s.surveyEnabled)) {
                        if (typeof s.surveyEnabled[formName] == 'object') {
                            for (const langId of Object.keys(s.surveyEnabled[formName])) {
                                if (typeof s.surveyEnabled[formName][langId] == 'boolean') {
                                    if (config.projMeta.forms.hasOwnProperty(formName) && config.projMeta.forms[formName].isSurvey && config.settings.langs.hasOwnProperty(langId)) {
                                        const val = s.surveyEnabled[formName][langId]
                                        set_dd(config.settings.langs[langId], 'survey-active', formName, '', val)
                                    }
                                }
                            }
                        }
                    }
                }
                // Excluded survey settings
                if (typeof s.excludedSettings == 'object') {
                    for (const formName of Object.keys(s.excludedSettings)) {
                        if (typeof s.excludedSettings[formName] == 'object') {
                            for (const settingName of Object.keys(s.excludedSettings[formName])) {
                                if (typeof s.excludedSettings[formName][settingName] == 'boolean') {
                                    if (settingName.startsWith('survey-') && config.projMeta.refMap.hasOwnProperty(settingName) && config.projMeta.forms.hasOwnProperty(formName)) {
                                        const val = s.excludedSettings[formName][settingName]
                                        if (val) {
                                            excludeSurveySetting(formName, settingName)
                                        }
                                        else {
                                            includeSurveySetting(formName, settingName)
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                // Excluded fields
                if (typeof s.excludedFields == 'object') {
                    for (const fieldName of Object.keys(s.excludedFields)) {
                        if (typeof s.excludedFields[fieldName] == 'boolean') {
                            if (config.projMeta.fields.hasOwnProperty(fieldName)) {
                                const val = s.excludedFields[fieldName]
                                if (val) {
                                    config.settings.excludedFields[fieldName] = true
                                }
                                else {
                                    delete config.settings.excludedFields[fieldName]
                                }
                            }
                        }
                    }
                }
                // ASI sources
                if (typeof s.asiSources == 'object') {
                    for (const formName of Object.keys(s.asiSources)) {
                        if (typeof s.asiSources[formName] == 'string' && typeof formName == 'string' && config.projMeta.forms.hasOwnProperty(formName)) {
                            const val = s.asiSources[formName]
                            if (['field', 'user'].includes(val)) {
                                config.settings.asiSources[formName] = val
                            }
                        }
                    }
                }
            }
            if (importAlertsTab) {
                // Excluded alerts
                if (typeof s.excludedAlerts == 'object') {
                    for (const uniqueAlertId of Object.keys(s.excludedAlerts)) {
                        if (typeof s.excludedAlerts[uniqueAlertId] == 'boolean' && typeof uniqueAlertId == 'string' && uniqueAlertId.startsWith('A-')) {
                            const alertId = uniqueAlertId.substring(2)
                            if (config.projMeta.alerts.hasOwnProperty(alertId)) {
                                const val = s.excludedAlerts[uniqueAlertId]
                                if (val) {
                                    config.settings.excludedAlerts[alertId] = true
                                }
                                else {
                                    delete config.settings.excludedAlerts[alertId]
                                }
                            }
                        }
                    }
                }
                // Alert sources
                if (typeof s.alertSources == 'object') {
                    for (const uniqueAlertId of Object.keys(s.alertSources)) {
                        if (typeof s.alertSources[uniqueAlertId] == 'string' && typeof uniqueAlertId == 'string' && uniqueAlertId.startsWith('A-')) {
                            const alertId = uniqueAlertId.substring(2)
                            if (config.projMeta.alerts.hasOwnProperty(alertId)) {
                                const val = s.alertSources[uniqueAlertId]
                                if (['field', 'user'].includes(val)) {
                                    config.settings.alertSources[alertId] = val
                                }
                            }
                        }
                    }
                }
            }
            if (importSettingsTab) {
                // Debug Mode (Admin only)
                if (typeof s.debug == 'boolean') {
                    const val = s.debug
                    $('input[data-mlm-config="debug"]').prop('checked', val)
                }
                // Disabled
                if (typeof s.disabled == 'boolean') {
                    const val = s.disabled
                    $('input[data-mlm-config="disabled"]').prop('checked', val)
                }
                // Highlights
                if (typeof s.highlightMissingDataentry == 'boolean') {
                    const val = s.highlightMissingDataentry
                    $('input[data-mlm-config="highlightMissingDataentry"]').prop('checked', val)
                }
                if (typeof s.highlightMissingSurvey == 'boolean') {
                    const val = s.highlightMissingSurvey
                    $('input[data-mlm-config="highlightMissingSurvey"]').prop('checked', val)
                }
                if (typeof s.autoDetectBrowserLang == 'boolean') {
                    const val = s.autoDetectBrowserLang
                    $('input[data-mlm-config="autoDetectBrowserLang"]').prop('checked', val)
                }
            }
            showToastMLM('#mlm-successToast', $lang.multilang_621)
        }
        catch (ex) {
            error(ex)
            showToastMLM('#mlm-errorToast', $lang.multilang_620)
        }

        // UI Updates
        renderLanguagesTable();
        checkDirty();

        // Close modal
        // @ts-ignore
        gsiModal._modal.hide();
    }
}

//#endregion

//#region ---- Delete Snapshot Modal

/** @type {DeleteSnapshotModal} The delete confirmation modal */
var delSnapshotModal = {
    _$: $(),
    _modal: null, // bootstrap.Modal
    _snapshotId: '',
    init: function(selector) {
        // Modal
        delSnapshotModal._$ = (typeof selector == 'string') ? $(selector) : selector
        delSnapshotModal._modal = new bootstrap.Modal(delSnapshotModal._$[0], { backdrop: 'static' })
        // Events
        delSnapshotModal._$.find('[action]').on('click', delSnapshotModal.act)
    },
    show: function(snapshotId) {
        delSnapshotModal._snapshotId = snapshotId
        // @ts-ignore
        delSnapshotModal._modal.show()
    },
    act: function(event) {
        const action = event.currentTarget.getAttribute('action')
        if (action == 'delete') {
            deleteSnapshot(delSnapshotModal._snapshotId)
        }
        // @ts-ignore
        delSnapshotModal._modal.hide()
        delSnapshotModal._snapshotId = ''
    }
}

//#endregion

//#endregion

//#region -- Forms/Surveys/ASIs Tab

//#region ---- Forms

/**
 * Updates (recreates) the table on the 'Forms/Surveys' tab.
 * @param {Language} lang
 */
function updateFormsTable(lang) {
    const $formsTab = $('#mlm-forms');
    $formsTab.hide();
    const $tbody = $('#mlm-forms-rows');
    // Remove all rows
    $tbody.children().remove();
    // Create rows
    for (const formName of Object.keys(config.projMeta.forms)) {
        /** @type FormMetadata */
        const form = config.projMeta.forms[formName];
        const $row = getTemplate('forms-row');
        $row.attr('data-mlm-language', currentLanguage);
        $row.attr('data-mlm-form', formName);
        $row.find('[data-mlm-type=form-active]')
            .prop('checked', get_dd(lang, 'form-active', formName).translation == '1')
            .attr('data-mlm-name', formName);
        $row.find('[data-mlm-type=survey-active]').each(function() {
            const $this = $(this);
            $this.prop('checked', get_dd(lang, 'survey-active', formName).translation == '1')
                .prop('disabled', !form.isSurvey) // Disable survey toggle when the form is not enabled as a survey
                .attr('data-mlm-name', formName);
        });
        $row.find('[data-mlm-switch]').each(function() {
            // Link input and label in switches
            const $this = $(this);
            const type = $this.attr('data-mlm-switch');
            $this.find('input').attr('id', type + '-' + formName);
            $this.find('label').attr('for', type + '-' + formName);
            // Remove tooltip in some cases
            if (type == 'survey-active' && !form.isSurvey) {
                $this.find('label').attr('title', null);
            }
        });
        $row.find('img.mlm-mycap-task-indicator')[form.myCapTaskId ? 'removeClass' : 'addClass']('hide');
        injectDisplayValues($row, {
            form: form["form-name"].reference,
            fields: Object.keys(form.fields).length.toString(),
        });
        // Update some display stuff
        $row.find('[data-mlm-type="asi-source"]')
            .attr('data-mlm-name', formName)
            .attr('id', 'asi-source-' + formName);
        if (currentLanguage == config.settings.refLang) {
            // Change button to show exclude action (and highlight)
            if (config.projMeta.forms[formName].isPROMIS) {
                $row.find('[data-mlm-action="translate-fields"]')
                    .html('<i class="fas fa-lock text-danger"></i> ' + $lang.multilang_04);
            }
            else {
                $row.find('[data-mlm-action="translate-fields"]')
                    .html('<i class="far fa-eye-slash"></i> ' + $lang.multilang_04);
            }
            $row.find('[data-mlm-action="translate-survey"]')
                .html('<i class="far fa-eye-slash"></i> ' + $lang.multilang_04);
            // Set ASI language source
            $row.find('[data-mlm-type="asi-source"]').val(config.settings.asiSources[formName]);
        }
        else {
            if (config.projMeta.forms[formName].isPROMIS) {
                // Change field translation icon to indicated that PROMIS instruments cannot be translated
                $row.find('[data-mlm-action="translate-fields"]')
                    .html('<i class="fas fa-lock text-danger"></i> ' + $lang.multilang_83);
            }
        }
        if (form.isSurvey) {
            $row.find('.remove-when-survey').remove();
            if (config.projMeta.surveys[formName].hasASIs) {
                $row.find('.remove-when-asis').remove();
            }
            else {
                $row.find('.remove-when-no-asis').remove();
            }
        }
        else {
            $row.find('.remove-when-not-survey').remove();
            $row.find('.remove-when-no-asis').remove();
        }
        $tbody.append($row);
    }
    if (!config.projMeta.surveysEnabled) {
        $formsTab.find('.remove-when-surveys-off').remove();
    }
    if (lang.key == config.settings.refLang) {
        $formsTab.find('.hide-when-ref-lang').hide();
        $formsTab.find('.show-when-ref-lang').show();
        $formsTab.find('.disable-when-ref-lang').prop('disabled', true);
    }
    else {
        $formsTab.find('.hide-when-ref-lang').show();
        $formsTab.find('.show-when-ref-lang').hide();
        $formsTab.find('.disable-when-ref-lang').prop('disabled', false);
    }
    // reCAPTCHA lang
    $('input[data-mlm-type="recaptcha-lang"]').val(lang["recaptcha-lang"] ?? '');
    $formsTab.find('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
    $formsTab.show();
}

/**
 * Renders the field exclusion table for the given form and appends it to a container element
 * @param {FormMetadata} form 
 * @param {JQuery<HTMLElement>} $container 
 */
function renderFieldExclusionTable(form, $container) {
    const $table = getTemplate('field-exclusion-table')
    const $tbody = $table.find('tbody')
    $tbody.attr('data-mlm-form', currentFormsTabForm)
    for (const strFieldNum of Object.keys(form.fields)) {
        const fieldName = form.fields[strFieldNum]
        const fieldNum = Number.parseInt(strFieldNum) + 1 // Start at 1
        const $row = getTemplate('field-exclusion-row')
        $row.attr('data-mlm-field', fieldName)
        $row.attr('data-mlm-language', currentLanguage)
        injectDisplayValues($row, {
            rowNumber: fieldNum.toString(),
            fieldName: fieldName,
        })
        $row.find('[data-mlm-type="field-excluded"]').prop('checked', config.settings.excludedFields[fieldName] == true).attr('data-mlm-name', fieldName)
        $tbody.append($row)
    }
    $container.append($table)
}

/**
 * Renders the field jumplist for the given form and appends it to a container element
 * @param {FormMetadata} form 
 * @param {JQuery<HTMLElement>} $container 
 * @returns {Number} The number of fields
 */
function renderFieldJumplist(form, $container) {
    const $jumplist = getTemplate('field-jumplist')
    const fieldNames = []
    for (const strFieldNum of Object.keys(form.fields).sort()) {
        const fieldName = form.fields[strFieldNum]
        if (!(config.settings.excludedFields[fieldName] == true)) {
            fieldNames.push(fieldName)
        }
    }
    if (fieldNames.length) {
        for (const fieldName of fieldNames.sort()) {
            $jumplist.find('select').append('<option value="' + fieldName + '">' + fieldName + '</option>')
        }
        $container.append($jumplist)
    }
    return fieldNames.length
}

/**
 * Renders a field item
 * @param {Language} lang 
 * @param {Object} meta 
 * @param {string} template 
 * @param {string} type 
 * @param {string} name 
 * @param {string} prompt 
 * @param {boolean} rte Rich Text Editor
 * @returns 
 */
function renderFieldItem(lang, meta, template, type, name, prompt, rte) {
    const $itemTpl = getTemplate(template);
    $itemTpl.attr('data-mlm-field-item', type);
    $itemTpl.find('.mlm-translation-prompt').after(getTemplate('reference-value'));
    var empty = false;
    if (isEmpty(meta[type])) {
        empty = true;
        injectDisplayValues($itemTpl, {
            html_prompt: prompt,
            html_reference: $lang.multilang_06 // Empty
        });
    }
    else {
        injectDisplayValues($itemTpl, {
            html_prompt: prompt,
            reference: meta[type].reference
        });
        // Add notice for field embedding
        let match;
        while ((match = fieldEmbeddingRegex.exec(meta[type].reference)) !== null) {
            if (config.projMeta.fields[match[1]]?.formName == config.projMeta.fields[name].formName) {
                log('Found field embedding:', match);
                $itemTpl.find('.mlm-ref-has-embeddings').addClass('shown');
            }
            break;
        }
    }
    $itemTpl.find('label').attr('for', name + type);
    const val = get_dd(lang, type, name);
    $itemTpl.find('[data-mlm-translation]')
        .attr('id', name + type)
        .attr('data-mlm-empty-text', empty)
        .val(val.translation);
    setNestedAttribute($itemTpl, 'data-mlm-translation', name);
    setNestedAttribute($itemTpl, 'data-mlm-name', name);
    setNestedAttribute($itemTpl, 'data-mlm-type', type);
    setNestedAttribute($itemTpl, 'data-mlm-refhash', val.refHash);
    if (!rte) $itemTpl.find('button[data-mlm-action="rich-text-editor"]').remove();
    return $itemTpl;
}

/**
 * Renders a field (or matrix) enum
 * @param {Language} lang 
 * @param {string} type 
 * @param {string} name 
 * @param {Object} choices 
 * @param {Object} choiceLabels 
 * @param {string} prompt 
 * @param {string} choiceType 
 * @returns 
 */
function renderFieldEnum(lang, type, name, choices, choiceLabels, prompt, choiceType) {
    if (choices == null) return $('<span></span>');
    const $itemTpl = getTemplate('field-item-table')
    $itemTpl.attr('data-mlm-field-item', type)
    injectDisplayValues($itemTpl, {
        html_prompt: prompt,
        html_choiceType: choiceType,
    })
    const $tbody = $itemTpl.find('tbody')
    // Add choices
    let choiceCodes = Object.keys(choices ?? {});
    if (type == 'field-enum') {
        choiceCodes = config.projMeta.fields[name]["enum-order"] ?? {};
    }
    else if (type == 'matrix-enum') {
        choiceCodes = config.projMeta.matrixGroups[name]["enum-order"] ?? {};
    }
    for (const choiceIdx in choiceCodes) {
        const choice = choiceCodes[choiceIdx];
        if (isEmpty(choices[choice])) continue;
        const $choiceTpl = getTemplate('field-item-table-row').attr('data-mlm-choice', choice)
        $choiceTpl.find('.mlm-choices-table-translation').prepend(getTemplate('reference-value'))
        injectDisplayValues($choiceTpl, {
            code: choiceLabels ? choiceLabels[choice] : choice,
            reference: choices[choice].reference,
        })
        const val = get_dd(lang, type, name, choice)
        $choiceTpl.find('label').attr('for', name + '-choice-' + choice)
        $choiceTpl.find('input').attr('id', name + '-choice-' + choice).val(val.translation)
        setNestedAttribute($choiceTpl, 'data-mlm-translation', name)
        setNestedAttribute($choiceTpl, 'data-mlm-name', name)
        setNestedAttribute($choiceTpl, 'data-mlm-type', type)
        setNestedAttribute($choiceTpl, 'data-mlm-index', choice)
        setNestedAttribute($choiceTpl, 'data-mlm-refhash', val.refHash ?? getRefHash(type, name, choice))
        $tbody.append($choiceTpl)
    }
    if (!rteSupported) $itemTpl.find('button[data-mlm-action="rich-text-editor"]').remove()
    return $itemTpl
}

/**
 * Renders the fields for translation
 */
function renderFields() {
    const form = config.projMeta.forms[currentFormsTabForm]
    const $container = $('[data-mlm-mode="fields"]')
    const $itemsContainer = $('[data-mlm-render="fields"]')
    setNestedAttribute($container, 'data-mlm-form', currentFormsTabForm)
    toggleVisibility($container.find('.mlm-survey-settings-link'), form.isSurvey)
    const hasASIs = form.isSurvey ? config.projMeta.surveys[currentFormsTabForm].hasASIs : false
    toggleVisibility($container.find('.mlm-asis-link'), hasASIs)
    $itemsContainer.children().remove()
    // Heading
    injectDisplayValues($container, {
        'form-display': form["form-name"].reference,
    })
    if (currentLanguage == config.settings.refLang) {
        // Render the field exclusion table for the reference language
        if (!form.isPROMIS) {
            renderFieldExclusionTable(form, $itemsContainer)
            $('[data-mlm-no-fields]').hide()
        }
        // PROMIS or Shared Library instrument?
        $('[data-mlm-promis]')[form.isPROMIS ? 'show' : 'hide']()
        $('[data-mlm-fromsharedlibrary]')[form.isFromSharedLibrary ? 'show' : 'hide']()
    }
    else {
        const lang = config.settings.langs[currentLanguage]
        // Render jumplist
        const numFields = form.isPROMIS ? 0 : renderFieldJumplist(form, $itemsContainer)
        // Are there any items?
        $('[data-mlm-no-fields]')[numFields || form.isPROMIS ? 'hide' : 'show']()
        // PROMIS or Shared Library instrument?
        $('[data-mlm-promis]')[form.isPROMIS ? 'show' : 'hide']()
        $('[data-mlm-fromsharedlibrary]')[form.isFromSharedLibrary ? 'show' : 'hide']()

        /** @type {Array<string>} Keeps track of already rendered matrix groups */
        const matrixRendered = []

        //#region MyCap Task Items

        const taskItems = Object.keys(form.myCapTaskItems ?? {});
        if (taskItems.length > 0) {
            // Setup task template
            const $tpl = getTemplate('task');
            $tpl.attr('data-mlm-field', 'task-'+currentFormsTabForm);
            $tpl.attr('data-mlm-task', form.myCapTaskId);
            $tpl.find('[data-mlm-type="task-complete"]').attr('id', currentFormsTabForm + '-task-complete').attr('data-mlm-translation', currentFormsTabForm).prop('checked', get_dd(lang, 'task-complete', currentFormsTabForm).translation == '1');
            $tpl.find('label[data-mlm-task-label]').attr('for', currentFormsTabForm + '-task-complete');
            injectDisplayValues($tpl, {
                'taskType': form.myCapTaskType
            }, true);
            const $taskItems = $tpl.find('.mlm-task-items');
            for (const taskItem in config.projMeta.myCap.orderedListOfTaskItems) {
                if (!taskItems.includes(taskItem)) continue;
                const task = form.myCapTaskItems[taskItem];
                const type = 'task-' + taskItem;
                const meta = {};
                meta[type] = task;
                const name = config.projMeta.forms[currentFormsTabForm].myCapTaskId.toString();
                $taskItems.append(renderFieldItem(
                    lang, meta, 'field-item-' + task.mode, type, name, task.prompt ?? '', false
                ));
            }
            for (const eventId in config.projMeta.events) {
                const eventIdTaskId = eventId+'-'+form.myCapTaskId;
                if (!taskItems.includes(eventIdTaskId)) continue;

                const eventMeta = config.projMeta.events[eventId]
                const $eventTaskItems = getTemplate('event-task-items').attr('data-mlm-event-tasks', eventIdTaskId);
                const $eventTaskItemsContainer = $eventTaskItems.find('.mlm-event-task-items');
                injectDisplayValues($eventTaskItems, {
                    'event-name': getRefValue('event-name', eventId.toString(), ''),
                    'unique-event-name': eventMeta.uniqueEventName,
                });
                const eventTaskItems = Object.keys(form.myCapTaskItems[eventIdTaskId]);
                for (const taskItem in config.projMeta.myCap.orderedListOfEventSpecificTaskItems) {
                    if (!eventTaskItems.includes(taskItem)) continue;
                    const task = form.myCapTaskItems[eventIdTaskId][taskItem];
                    const type = 'task-' + taskItem;
                    const meta = {};
                    meta[type] = task;
                    $eventTaskItemsContainer.append(renderFieldItem(
                        lang, meta, 'field-item-' + task.mode, type, eventIdTaskId, task.prompt ?? '', false
                    ));
                }
                const eventTaskHtml = $($.parseHTML($eventTaskItemsContainer.html()));
                const divElements = eventTaskHtml.find('div');
                if (divElements.length > 0) { // Show event section ONLY when elements exists (It will hide in case of active tasks)
                    $taskItems.append($eventTaskItems);
                }
            }
            $itemsContainer.append($tpl);
            // Add a separator after the task items
            $itemsContainer.append(getTemplate('task-fields-separator'));
        }

        //#endregion

        // Go through all fields
        for (const strFieldNum of Object.keys(form.fields)) {
            const fieldName = form.fields[strFieldNum]
            if (config.settings.excludedFields[fieldName] == true) continue // Skip excluded

            const field = config.projMeta.fields[fieldName]
            const fieldNum = Number.parseInt(strFieldNum) + 1 // Start at 1
            const fieldType = config.projMeta.fieldTypes[field.type]

            //#region Render matrix group

            // Is this field in a matrix group?
            const matrixName = config.projMeta.fields[fieldName].matrix
            const isMatrix = matrixName != null
            if(isMatrix && !matrixRendered.includes(matrixName)) {
                const matrixData = config.projMeta.matrixGroups[matrixName]
                // Render matrix - header, if defined, and choices
                const $tpl = getTemplate('matrix').attr('data-mlm-matrix', matrixName)
                injectDisplayValues($tpl, {
                    matrixName: matrixName,
                })
                $tpl.find('[data-mlm-type="matrix-complete"]').attr('id', matrixName + '-matrix-complete').attr('data-mlm-translation', matrixName).prop('checked', get_dd(lang, 'matrix-complete', matrixName).translation == '1')
                $tpl.find('label').attr('for', matrixName + '-matrix-complete')

                // Matrix Header
                $tpl.find('.mlm-field-items').append(
                    renderFieldItem(
                        lang, matrixData, 'field-item-textarea', 
                        'matrix-header', matrixName, config.projMeta.fieldTypes['matrix']['header'], rteSupported)
                 )
                // Matrix Enum
                $tpl.find('.mlm-field-items').append(
                    renderFieldEnum(
                        lang,
                        'matrix-enum',
                        matrixName,
                        matrixData['matrix-enum'], 
                        null, // No labels
                        fieldType.enum, 
                        fieldType.colHeader
                    )
                )
                // Mark as done
                matrixRendered.push(matrixName)
                $itemsContainer.append($tpl)
            }
            //#endregion

            //#region Render field

            const $tpl = getTemplate('field')
            $tpl.attr('data-mlm-field', fieldName)
            injectDisplayValues($tpl, {
                fieldNum: fieldNum.toString(),
                fieldName: fieldName,
                matrixName: matrixName,
            })
            if (field.isTaskItem) {
                $tpl.addClass('mlm-field-is-task-item mlm-task-item-hidden');
            }
            $tpl.find('[data-mlm-type="field-complete"]').attr('id', fieldName + '-field-complete').attr('data-mlm-translation', fieldName).prop('checked', get_dd(lang, 'field-complete', fieldName).translation == '1')
            $tpl.find('label[data-mlm-display="fieldName"]').attr('for', fieldName + '-field-complete')
            $tpl.find('.mlm-matrix-name').prop('hidden', !isMatrix)
            const $fieldItems = $tpl.find('.mlm-field-items')
            // Section Header
            if (!isMatrix && (field["field-header"].reference || !isEmpty(get_dd(lang, 'field-header', fieldName).translation))) {
                $fieldItems.append(renderFieldItem(
                    lang, field, 'field-item-textarea', 'field-header', fieldName, 
                    config.projMeta.fieldTypes['header']['header'], rteSupported
                ))
            }
            // Field Label
            if (fieldType.label) {
                $fieldItems.append(renderFieldItem(
                    lang, field, 'field-item-textarea', 'field-label', fieldName, 
                    fieldType.label, rteSupported
                ))
            }
            // Field Note
            if (!isMatrix && fieldType.note && (!isEmpty(field["field-note"]) || !isEmpty(get_dd(lang, 'field-note', fieldName).translation))) {
                $fieldItems.append(renderFieldItem(
                    lang, field, 'field-item-textarea', 'field-note', fieldName,
                    fieldType.note, rteSupported
                ))
            }
            // Video Url
            if (fieldType.video_url && (field["field-video_url"].reference || !isEmpty(get_dd(lang, 'field-video_url', fieldName).translation))) {
                $fieldItems.append(renderFieldItem(
                    lang, field, 'field-item-text', 'field-video_url', fieldName,
                    fieldType.video_url, rteSupported
                ))
            }
            // Field Enum (choices, slider labels)
            if (!isMatrix && fieldType.enum) {
                $fieldItems.append(renderFieldEnum(
                    lang, 
                    'field-enum', 
                    fieldName, 
                    field["field-enum"], 
                    fieldType.enumLabels, 
                    fieldType.enum, 
                    fieldType.colHeader
                ))
            }
            // Field Enum Hints
            if (fieldType.enumHints) {
                $fieldItems.append('<div class="mlm-enum-hints mb-3"><i class="fa-solid fa-info-circle text-dark me-1"></i>' + fieldType.enumHints + '</div>');
            }
            // Action Tags
            if (Object.keys(field["field-actiontag"]).length) {
                const choiceLabels = {}
                for (const key of Object.keys(field["field-actiontag"])) {
                    choiceLabels[key] = field["field-actiontag"][key].tag
                }
                $fieldItems.append(renderFieldEnum(
                    lang,
                    'field-actiontag',
                    fieldName,
                    field["field-actiontag"],
                    choiceLabels,
                    config.projMeta.fieldTypes.actiontag.value,
                    config.projMeta.fieldTypes.actiontag.colHeader
                ))
            }
            //#endregion

            $itemsContainer.append($tpl)
        }
        // Update translation status indicators for tasks, fields and matrix groups
        $('div[data-mlm-render="fields"]')
            .find('[data-mlm-field]').each(function () {
                const fieldName = this.getAttribute('data-mlm-field') ?? ''
                updateDdTranslationStatus(fieldName)
            })
        $('div[data-mlm-render="fields"]')
            .find('[data-mlm-matrix]').each(function () {
                const matrixName = this.getAttribute('data-mlm-matrix') ?? ''
                updateDdTranslationStatus(matrixName)
            })
        $('div[data-mlm-render="fields"]')
            .find('div[data-mlm-event-tasks]').each(function() {
                updateEventTaskTranslationStatus(this);
            });

        // Collapse any fields marked complete
        if (lang.dd['field-complete']) {
            for (const fieldName of Object.keys(lang.dd['field-complete'])) {
                applyHideTranslatedFieldItems(fieldName)
            }
        }
    }
    // Show or hide MyCap items
    $container.find(".hide-when-mycap-task")[form.myCapTaskId ? "addClass" : "removeClass"]("hide");
    $container.find(".hide-when-not-mycap-task")[form.myCapTaskId ? "removeClass" : "addClass"]("hide");
    // @ts-ignore
    $container.find('.textarea-autosize').on('focus', function() { $(this).trigger('input'); }).textareaAutoSize()
    // @ts-ignore
    $container.find('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
    setUIEnabledState();
    setRtlStatus();
    log('Rendered fields for "' + currentFormsTabForm + '"')
}

/** Reveals hidden items in a field block */
function revealFieldItems($revealLink) {
    $revealLink.parents('.mlm-field-block').find('.mlm-field-item-hidden').removeClass('mlm-field-item-hidden');
}

/** Reveals hidden MyCap Task-associated items */
function revealTaskItems($revealLink) {
    $revealLink.parents('[data-mlm-render="fields"]').find('.mlm-task-item-hidden').removeClass('mlm-task-item-hidden');
    $revealLink.parents('.mlm-task-fields-separator').remove();
}

function updateEventTaskTranslationStatus(container) {
    const $container = $(container);
    $container.find('[data-mlm-indicator="event-task-translated"]').removeClass('badge-success badge-danger badge-light').addClass($container.find('[data-mlm-indicator].badge-danger').length == 0 ? 'badge-success' : 'badge-danger');
}

function updateTaskTranslationStatus(type, name) {
    $('[data-mlm-type="' + type + '"][data-mlm-translation="' + name + '"]').parents('.form-group').find('[data-mlm-ref-changed]').each(function() {
        $(this).tooltip('dispose').remove();
    });
}

/**
 * Updates the translation status icons (red/green); additionally highlight any
 * changed reference values
 * @param {string} name Name of the field
 */
function updateDdTranslationStatus(name) {
    // Check if the field is excluded
    if (config.settings.excludedFields[name]) return;

    /** @type {JQuery<HTMLElement>} */
    const $items = $('[data-mlm-field="' + name + '"], [data-mlm-matrix="' + name + '"], [data-mlm-event-tasks="' + name + '"]');
    if ($items.length < 1) {
        error('Unable to find field or matrix named "' + name + '"');
        return;
    }
    $items.each(function() {
        const $item = $(this);
        let allTranslated = true;
        $item.find('[data-mlm-field-item]').each(function() {
            const $this = $(this);
            const $val = $this.find('[data-mlm-translation]');
            const type = $val.attr('data-mlm-type') ?? '';
            if (type == '') return;
            const name = $val.attr('data-mlm-translation') ?? '';
            const index = $val.attr('data-mlm-index') ?? '';
            // Item translation status
            let itemTranslated = isDdTranslated(type, name, index);
            // Single item or group (enums)?
            if (['field-actiontag','field-enum','matrix-enum'].includes(type)) {
                // Evaluate per sub-item
                let allChoicesTranslated = true;
                $this.find('.mlm-choices-table [data-mlm-choice]').each(function() {
                    const $thisChoice = $(this);
                    const $choiceVal = $thisChoice.find('[data-mlm-translation]');
                    const choice = $choiceVal.attr('data-mlm-index');
                    // Choice translation status
                    const choiceTranslated = isDdTranslated(type, name, choice);
                    const translatedClass = choiceTranslated ? 'hide' : 'badge-danger';
                    $thisChoice.find('[data-mlm-indicator="field-choice-translated"]').removeClass('badge-success badge-danger badge-light').addClass(translatedClass);
                    allChoicesTranslated = allChoicesTranslated && choiceTranslated;
                    // Reference hash mismatch indicator
                    const hash = $choiceVal.attr('data-mlm-refhash') ?? null;
                    const refHashMismatch = hasRefHashMismatch(hash, type, name, choice);
                    const refHashFunc = refHashMismatch ? 'show' : 'hide';
                    $thisChoice.find('[data-mlm-ref-changed]')[refHashFunc]();
                })
                itemTranslated = allChoicesTranslated;
            }
            else {
                // Reference hash mismatch indicator
                const hash = $val.attr('data-mlm-refhash') ?? null;
                const refHashMismatch = hasRefHashMismatch(hash, type, name, index);
                const refHashFunc = refHashMismatch ? 'show' : 'hide';
                $this.find('[data-mlm-ref-changed]')[refHashFunc]();
            }
            // Translation status
            const translatedClass = itemTranslated ? 'badge-success' : 'badge-danger';
            $this.find('[data-mlm-indicator="field-item-translated"]').removeClass('badge-success badge-danger badge-light').addClass(translatedClass);
            allTranslated = allTranslated && itemTranslated;
        })
        // Set overall translation status
        $item.find('[data-mlm-indicator="translated"]')
            .removeClass('badge-success badge-danger badge-light')
            .addClass(allTranslated ? 'badge-success' : 'badge-danger');
    });
}

/**
 * Checks whether there is a reference hash mismatch
 * @param {string|null} hash 
 * @param {string} type 
 * @param {string} name 
 * @param {string} index 
 * @returns {boolean}
 */
function hasRefHashMismatch(hash, type, name, index = '') {
    try {
        const ref = config.projMeta.refMap[type][name][index]
        if (isEmpty(hash) || !ref.translation) {
            return false
        }
        return ref.refHash != hash
    }
    catch {
        error('Invalid type/name/index combination: ', type, name, index)
        return false
    }
}

/**
 * Hides or shows translated field items based on the state of the 'Hide translated items' checkbox
 * @param {string|null} field 
 */
 function applyHideTranslatedFieldItems(field = null) {
    const hideTranslated = $('input[data-mlm-action="toggle-hide-fielditems-translated"]').prop('checked');
    // Scope to a single field or all?
    const $top = field ? $('[data-mlm-field="' + field + '"]') : $('[data-mlm-render="fields"]');
    if (hideTranslated || field) {
        $top.find('[data-mlm-field-item]').each(function() {
            const $this = $(this);
            const action = ($this.find('[data-mlm-indicator="field-item-translated"]').not('.badge-success').length || !hideTranslated) ? 'removeClass' : 'addClass';
            $this[action]('mlm-field-item-hidden');
        });
    }
    else {
        $top.find('[data-mlm-field-item]').each(function() {
            const $this = $(this);
            const name = $this.find('[data-mlm-translation]').attr('data-mlm-translation');
            if (field == null && $('[data-mlm-field="' + name + '"] input[data-mlm-type="field-complete"]:checked').length) {
                // Do nothing
            }
            else {
                $this.removeClass('mlm-field-item-hidden');
            }
        });
    }
}

//#endregion

//#region ---- Survey Settings

/**
 * Checks whether a survey setting is set to be excluded from translation for the specified instrument
 * @param {string} formName 
 * @param {string} setting 
 * @returns {boolean}
 */
function isSurveySettingExcluded(formName, setting) {
    if (config.settings.excludedSettings[formName]) {
        return config.settings.excludedSettings[formName][setting] ?? false
    }
    return false
}

function excludeSurveySetting(formName, setting) {
    if (!config.settings.excludedSettings[formName]) {
        config.settings.excludedSettings[formName] = {}
    }
    config.settings.excludedSettings[formName][setting] = true
}

function includeSurveySetting(formName, setting) {
    if (isSurveySettingExcluded(formName, setting)) {
        delete config.settings.excludedSettings[formName][setting]
        if (!Object.keys(config.settings.excludedSettings[formName]).length) {
            // Get rid of empty object - otherwise change tracking will no work
            delete config.settings.excludedSettings[formName]
        }
    }
}

/**
 * Renders the survey settings for translation
 */
function renderSurveySettings() {
    const form = config.projMeta.forms[currentFormsTabForm]
    const survey = config.projMeta.surveys[currentFormsTabForm]
    const $container = $('[data-mlm-mode="survey"]')
    setNestedAttribute($container, 'data-mlm-form', currentFormsTabForm)
    const $itemsContainer = $('[data-mlm-render="survey"]')
    $itemsContainer.children().remove()
    // Heading
    injectDisplayValues($container, {
        'form-display': form["form-name"].reference,
    })
    // ASI Link - hide/show depending on whether ASIs are defined
    toggleVisibility($container.find('.mlm-asis-link'), survey.hasASIs)
    // Render the settings exclusion table for the reference language
    if (currentLanguage == config.settings.refLang) {
        const $table = getTemplate('survey-setting-exclusion-table')
        const $tbody = $table.find('tbody')
        for (const settingName of Object.keys(survey)) {
            if (!settingName.startsWith('survey-')) continue
            /** @type {SurveyMetadataElement} */
            const setting = survey[settingName]
            const $row = getTemplate('survey-setting-exclusion-row')
            $row.attr('data-mlm-form', currentFormsTabForm)
            $row.attr('data-mlm-setting', settingName)
            $row.attr('data-mlm-language', currentLanguage)
            injectDisplayValues($row, {
                html_settingName: setting.name,
            })
            $row.find('[data-mlm-type="setting-excluded"]').prop('checked', isSurveySettingExcluded(currentFormsTabForm, settingName))
            $tbody.append($row)
        }
        $itemsContainer.append($table)
    }
    // Render the settings for the current language
    else {
        const lang = config.settings.langs[currentLanguage]
        const surveyMeta = config.projMeta.surveys[currentFormsTabForm]
        let $title = null
        for (const type of Object.keys(surveyMeta)) {
            if (!type.startsWith('survey-')) continue
            /** @type {SurveyMetadataElement} */
            const settingMeta = surveyMeta[type]
            // Add a title?
            if (settingMeta.title) {
                $title = getTemplate('survey-setting-title')
                injectDisplayValues($title, {
                    title: settingMeta.title,
                }, true)
            }
            // Is the setting excluded?
            if (isSurveySettingExcluded(currentFormsTabForm, type)) continue
            // Is the setting disabled?
            if (settingMeta.disabled) continue
            // Current value
            const value = get_dd(lang, type, currentFormsTabForm)
            const hash = value.refHash ?? settingMeta.refHash
            // Has the setting a reference value?
            const empty = isEmpty(settingMeta.reference)
            // When both reference and current are empty, then skip this item (unless in debug mode)
            if (empty && isEmpty(value) && !config.settings.debug) continue
            // Add the setting
            const $setting = getTemplate('survey-setting-' + settingMeta.mode)
            setNestedAttribute($setting, 'data-mlm-name', currentFormsTabForm)
            setNestedAttribute($setting, 'data-mlm-type', type)
            setNestedAttribute($setting, 'data-mlm-refhash', hash)
            $setting.find('label').attr('for', currentFormsTabForm + '-' + type)
            $setting.find('[data-mlm-translation]').attr('id', currentFormsTabForm + '-' + type)
            // Set data-mlm-empty-text to true/false to use this to decide if this text will need to get translated from AI translator service or not
            $setting.find('[data-mlm-translation]').attr('data-mlm-empty-text', empty)

            let translation = value.translation
            if (settingMeta.mode != 'select') {
                $setting.find('[data-mlm-translation]').val(translation)
                const $reference = getTemplate('reference-value')
                $setting.find('label.mlm-translation-prompt').after($reference)
                injectDisplayValues($setting, {
                    html_prompt: settingMeta.prompt,
                    reference: empty ? $lang.multilang_06 : settingMeta.reference,
                }, empty)
                if (!rteSupported) $setting.find('button[data-mlm-action="rich-text-editor"]').remove()
            }
            else {
                // Add available options
                if (translation == null) translation = settingMeta.reference
                const $select = $setting.find('select[data-mlm-translation]')
                for (const optValue of Object.keys(settingMeta.select)) {
                    const $option = $('<option></option')
                    .text(settingMeta.select[optValue])
                    .attr('value', optValue)
                    if (optValue == translation) {
                        $option.attr('selected', 'selected')
                    }
                    $select.append($option)
                }
                const $reference = getTemplate('reference-value')
                $reference.find('button[data-mlm-action="copy-reference"]').remove()
                $setting.find('label.mlm-translation-prompt').after($reference)
                injectDisplayValues($setting, {
                    html_prompt: settingMeta.prompt,
                    reference: empty ? $lang.multilang_06 : settingMeta.select[settingMeta.reference],
                }, empty)
            }
            if ($title) {
                $itemsContainer.append($title)
                $title = null
            }
            $itemsContainer.append($setting)
        }
        // Update translation status indicators
        $itemsContainer.find('[data-mlm-survey-setting]').each(function() {
            const type = $(this).find('[data-mlm-translation]').attr('data-mlm-type') ?? ''
            updateSurveySettingsTranslationStatus(type)
        })
    }

    // @ts-ignore
    $container.find('.textarea-autosize').on('focus', function() { $(this).trigger('input'); }).textareaAutoSize()
    // @ts-ignores
    $container.find('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
    log('Rendered survey settings for "' + currentFormsTabForm + '"')
}

/**
 * Updates the translation status icons (red/green); additionally highlight any
 * changed reference values
 * @param {string} type The survey setting type
 */
function updateSurveySettingsTranslationStatus(type) {
    // Find the setting row and input element
    const $setting = $('[data-mlm-survey-setting] [data-mlm-type="' + type + '"]').parentsUntil('[data-mlm-render="survey"]').last()
    const $val = $setting.find('[data-mlm-translation]')
    // Get values
    const hash = $val.attr('data-mlm-refhash') ?? null
    const name = $val.attr('data-mlm-name') ?? ''
    // Translated indicator
    const translated = isDdTranslated(type, name)
    $setting.find('[data-mlm-indicator="translated"]')
        .removeClass('badge-success badge-danger badge-secondary badge-light')
        .addClass(translated ? 'badge-success' : 'badge-danger')
    // Reference changed indicator
    const refHashMismatch = hasRefHashMismatch(hash, type, name)
    const refHashFunc = refHashMismatch ? 'show' : 'hide'
    $setting.find('[data-mlm-ref-changed]')[refHashFunc]()
}

//#endregion

//#region ---- Survey Queue

/**
 * Updates the translation status of the survey queue / login items
 */
function updateSurveyQueueStatus() {
    // Do not check for reference language
    if (currentLanguage == config.settings.refLang) return
    // Update
    const lang = config.settings.langs[currentLanguage]
    for (const type of Object.keys(config.projMeta.surveyQueue)) {
        // Check if there is a reference value
        const refVal = getRefValue(type, '', '')
        const $item = $('[data-mlm-sq-item="' + type + '"]')
        if (refVal.length) {
            // Show
            $item.removeClass('hide')
            // Translation
            const indicatorClass = isDdTranslated(type, '', '') ? 'button-translated-indicator' : 'button-untranslated-indicator'
            $item.find('[data-mlm-indicator]').removeClass('button-translated-indicator button-untranslated-indicator').addClass(indicatorClass)
            // Refhash
            const action = hasRefHashMismatch(get_dd(lang, type, '', '').refHash, type, '', '') ? 'removeClass' : 'addClass'
            $item.find('[data-mlm-ref-changed]')[action]('hide')
        }
        else {
            // Hide
            $item.addClass('hide')
        }
    }
}

//#endregion

//#region ---- Form Names

/**
 * Updates the translation status of the survey queue / login items
 */
 function updateFormNameStatus() {
    // Do not check for reference language
    if (currentLanguage == config.settings.refLang) return
    // Update
    const lang = config.settings.langs[currentLanguage]
    for (const name of Object.keys(config.projMeta.forms)) {
        // Check if there is a reference value
        const $item = $('tr[data-mlm-form="' + name + '"]')
        // Translation
        const indicatorClass = isDdTranslated('form-name', name, '') ? 'button-translated-indicator' : 'button-untranslated-indicator'
        $item.find('[data-mlm-indicator="form-name"]').removeClass('button-translated-indicator button-untranslated-indicator').addClass(indicatorClass)
        // Refhash
        const action = hasRefHashMismatch(get_dd(lang, 'form-name', name, '').refHash, 'form-name', name, '') ? 'addClass' : 'removeClass'
        $item.find('[data-mlm-ref-changed="form-name"]')[action]('button-ref-changed-indicator')
    }
}

//#endregion

//#region ---- ASI

/**
 * Renders the survey settings for translation
 */
function renderASIs() {
    const form = config.projMeta.forms[currentFormsTabForm];
    const survey = config.projMeta.surveys[currentFormsTabForm];
    const $container = $('[data-mlm-mode="asi"]');
    setNestedAttribute($container, 'data-mlm-form', currentFormsTabForm);
    const $itemsContainer = $('[data-mlm-render="asi"]');
    $itemsContainer.children().remove();
    // Heading
    injectDisplayValues($container, {
        'form-display': form["form-name"].reference,
    });
    // For the reference language, there are no options
    if (currentLanguage == config.settings.refLang) {
        const $notice = getTemplate('asi-ref-lang-notice');
        $itemsContainer.append($notice);
    }
    // Render the ASI settings for the current language
    else {
        const lang = config.settings.langs[currentLanguage];
        const surveyMeta = config.projMeta.surveys[currentFormsTabForm];
        for (const asiIdx of Object.keys(surveyMeta.asis)) {
            const asiId = surveyMeta.asis[asiIdx];
            const asiMeta = config.projMeta.asis[asiId];
            const eventId = asiMeta.event_id;
            const eventMeta = config.projMeta.events[eventId];
            const $asi = getTemplate('asi-settings').attr('data-mlm-asi', asiId);
            injectDisplayValues($asi, {
                'event-name': getRefValue('event-name', eventId.toString(), ''),
                'unique-event-name': eventMeta.uniqueEventName,
            });
            const $asiItemsContainer = $asi.find('.mlm-asi-items');
            for (const type of ['asi-email_sender_display','asi-email_subject','asi-email_content']) {
                const empty = isEmpty(asiMeta[type].reference);
                const value = get_dd(lang, type, asiMeta.form, asiId);
                const hash = value.refHash ?? asiMeta[type].refHash;
                // When both reference and current are empty, then skip this item (unless in debug mode)
                if (empty && isEmpty(value) && !config.settings.debug) continue;
                const $asiItem = getTemplate('asi-setting-' + asiMeta[type].mode).attr('data-mlm-asi-setting', type);
                $asiItem.find('label.mlm-translation-prompt').after(getTemplate('reference-value'));
                injectDisplayValues($asiItem, {
                    html_prompt: asiMeta[type].prompt,
                    reference: empty ? $lang.multilang_06 : asiMeta[type].reference,
                }, empty);
                setNestedAttribute($asiItem, 'data-mlm-name', asiMeta.form);
                setNestedAttribute($asiItem, 'data-mlm-index', asiId);
                setNestedAttribute($asiItem, 'data-mlm-type', type);
                setNestedAttribute($asiItem, 'data-mlm-refhash', hash);
                setNestedAttribute($asiItem, 'id', type + '-' + asiId);
                $asiItem.find('[data-mlm-translation]').val(value.translation);
                $asiItem.find('[data-mlm-translation]').attr('data-mlm-empty-text', empty)
                if (!rteSupported) $asiItem.find('button[data-mlm-action="rich-text-editor"]').remove();
                $asiItemsContainer.append($asiItem);
            }
            $itemsContainer.append($asi);
        }
        // Update translation status indicators
        $itemsContainer.find('[data-mlm-asi]').each(function() {
            const asiId = $(this).attr('data-mlm-asi') ?? '';
            updateASITranslationStatus(asiId);
        })
    }
    // @ts-ignore
    $container.find('.textarea-autosize').on('focus', function() { $(this).trigger('input'); }).textareaAutoSize();
    // @ts-ignores
    $container.find('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
    log('Rendered ASIs for "' + currentFormsTabForm + '"');
}

/**
 * Updates the translation status icons (red/green); additionally highlight any
 * changed reference values
 * @param {string} asiId Name of the asi setting
 */
function updateASITranslationStatus(asiId) {
    const $item = $('[data-mlm-asi="' + asiId + '"]')
    if ($item.length != 1) {
        error('Unable to find ASI block "' + asiId + '"')
        return
    }
    let allTranslated = true
    $item.find('[data-mlm-asi-setting]').each(function() {
        const $this = $(this)
        const $val = $this.find('[data-mlm-translation]')
        const type = $val.attr('data-mlm-type') ?? ''
        const name = $val.attr('data-mlm-name') ?? ''
        const index = $val.attr('data-mlm-index') ?? ''
        // Reference hash mismatch indicator
        const hash = $val.attr('data-mlm-refhash') ?? null
        const refHashMismatch = hasRefHashMismatch(hash, type, name, index)
        const refHashFunc = refHashMismatch ? 'show' : 'hide'
        $this.find('[data-mlm-ref-changed]')[refHashFunc]()
        // Translation status
        const itemTranslated = isDdTranslated(type, name, index)
        const translatedClass = itemTranslated ? 'badge-success' : 'badge-danger'
        $this.find('[data-mlm-indicator="asi-setting-translated"]').removeClass('badge-success badge-danger badge-light').addClass(translatedClass)
        allTranslated = allTranslated && itemTranslated
    })
    // Set overall translation status
    $item.find('[data-mlm-indicator="asi-translated"]')
        .removeClass('badge-success badge-danger badge-light')
        .addClass(allTranslated ? 'badge-success' : 'badge-danger')
}


//#endregion

//#region ---- Render and Data Access Helpers

//#region ---- Rich Text Editor (RTE) Modal

/** @type {RichTextEditorModal} */
const rteModal = {
    _$: $(),
    _modal: null, // bootstrap.Modal
    _initialized: false,
    _$editor: $(),
    _$title: $(),
    _data: null,
    _editorSelector: '',

    /**
     * Initializes the RTE modal
     */
    init: function() {
        // Already initialized
        if (rteModal._initialized) {
            console.warn('MLM: RichTextEditor already initialized!')
            return
        }
        // Modal
        rteModal._$ = $('#mlm-rte-modal')
        rteModal._modal = new bootstrap.Modal(rteModal._$[0], { backdrop: 'static' })
        // Events
        rteModal._$.find('[action]').on('click', rteModal.act)
        // Editor textarea and title
        rteModal._editorSelector = '#mlm-rte-editor'
        rteModal._$editor = rteModal._$.find(rteModal._editorSelector)
        rteModal._$title = rteModal._$.find('.modal-header h1')
        rteModal._initialized = true
        // TinyMCE
        // @ts-ignore
        if (typeof window.tinymce == 'undefined') {
            // @ts-ignore
            window.loadJS(app_path_webroot + 'Resources/webpack/css/tinymce/tinymce.min.js')
        }
        // The following allows TinyMCE's internal dialogs to work (like when adding links).
        // It was copied from here: https://stackoverflow.com/questions/18111582/tinymce-4-links-plugin-modal-in-not-editable
        $(document).on('focusin', function(e) {
            if ($(e.target).closest(".mce-window").length) {
                e.stopImmediatePropagation()
            }
        })
    },
    /**
     * Shows the RTE modal
     * @param {RichTextEditorData} rteData 
     */
    show: function(rteData) {
        if (rteModal._$ == null) {
            error('RTE modal is not initialized yet!')
            return
        }
        rteModal._data = rteData
        const title = getRefPrompt(rteData.type, rteData.name, rteData.index) ?? ''
        // Convert existing line breaks to <br> tags 
        // @ts-ignore
        const text = rteData.$textarea.val() ?? ''
        rteModal._$title.html(title)
        rteModal._$editor.val(text)
        rteModal._initRTE()
        // @ts-ignore
        rteModal._modal.show()
    },
    /**
     * Handles RTE modal events
     * @param {JQuery.ClickEvent} event 
     */
    act: function(event) {
        const action = event.currentTarget.getAttribute('action')
        if (action == 'paste-ref' && rteModal._data != null) {
            let text = getRefValue(rteModal._data.type, rteModal._data.name, rteModal._data.index)
            log('Pasted references value into RTE: "' + text + '"')
            // Convert depending on mode
            if (rteModal._data.mode == 'inverted') {
                // Convert existing line breaks to <br> tags
                // @ts-ignore
                text = nl2br(text)
            }
            rteModal._pasteIntoRTE(text)
            return
        }
        rteModal._removeRTE()
        if (action == 'apply' && rteModal._data != null) {
            // Get text
            let text = (rteModal._$editor.val() ?? '').toString()
            // Remove newlines so REDCap doesn't replace them with <br> tags.
            text = text.split('\n').join(' ')
            // Wrap in `<div class="rich-text-field-label"></div>` if not already present
            if (rteModal._data.mode == 'inverted' && !text.startsWith('<div class="rich-text-field-label">')) {
                text = '<div class="rich-text-field-label">' + text + '</div>'
            }
            // And set back to original textarea
            rteModal._data.$textarea.val(text)
            setTimeout(function() {
                if (rteModal._data != null) {
                    rteModal._data.$textarea.trigger('change')
                }
                // Close and reset
                // @ts-ignore
                rteModal._modal.hide()
                rteModal._data = null
            }, 0)
        }
        else {
            // Close and reset
            // @ts-ignore
            rteModal._modal.hide()
            rteModal._data = null
        }
    },
    _initRTE: () => {
        const selector = rteModal._editorSelector;
        const directionality = rteModal._data != null && rteModal._data.lang.rtl ? 'rtl' : 'ltr';
        const inverted = (rteModal._data?.mode ?? 'normal') == 'inverted';
        initializeRichTextEditor(rteModal._$, selector, directionality, inverted);
    },
    _removeRTE: function() {
        // @ts-ignore
        tinymce.remove(rteModal._editorSelector)
    },
    _pasteIntoRTE: function(text) {
        // @ts-ignore
        tinymce.activeEditor.selection.setContent(text);
    }
}

//#endregion

//#region ---- Single Item Translation (SIT) Modal

/** @type {SingleItemTranslationModal} */
const sitModal = {
    _$: $(),
    _modal: null, // bootstrap.Modal
    _initialized: false,
    _$editor: $(),
    _$input: $(),
    _$textarea: $(),
    _$ref: $(),
    _$title: $(),
    _data: null,
    /**
     * Initializes the SIT modal
     */
    init: function() {
        // Already initialized
        if (sitModal._initialized) {
            console.warn('MLM: Single Item Translation Modal already initialized!')
            return
        }
        // Modal
        sitModal._$ = $('#mlm-sit-modal')
        sitModal._modal = new bootstrap.Modal(sitModal._$[0], { backdrop: 'static' })
        // Events
        sitModal._$.find('[action]').on('click', sitModal.act)
        // Editor textarea/input and title
        sitModal._$textarea = sitModal._$.find('.mlm-sit-editor-container textarea')
        sitModal._$input = sitModal._$.find('.mlm-sit-editor-container input')
        sitModal._$ref = sitModal._$.find('.mlm-sit-reference')
        sitModal._$title = sitModal._$.find('.modal-header h1')
        sitModal._initialized = true
    },
    /**
     * Shows the SIT modal
     * @param {SingleItemTranslationData} sitData 
     */
    show: function(sitData) {
        if (sitModal._$ == null) {
            error('Single Item Translation modal is not initialized yet!')
            return
        }
        sitModal._data = sitData
        sitModal._$title.html(sitData.title)
        sitModal._$ref.text(sitModal._data.reference)
        if (sitData.mode == 'single') {
            sitModal._$.find('.mlm-sit-editor-container').css('height','auto')
            sitModal._$editor = sitModal._$input
            sitModal._$textarea.hide()
            sitModal._$input.val(sitData.value).show()
            setTimeout(function() {
                sitModal._$input.trigger('focus')
            }, 0)
        }
        else {
            sitModal._$.find('.mlm-sit-editor-container').css('height','400px')
            sitModal._$textarea.val(sitData.value).show()
            if (rteSupported && sitData.mode == 'rte') {
                sitModal._initRTE()
            }
            sitModal._$editor = sitModal._$textarea
            sitModal._$input.hide()
        }
        // @ts-ignore
        sitModal._modal.show()
    },
    /**
     * Handles SIT modal events
     * @param {JQuery.ClickEvent} event 
     */
    act: function(event) {
        if (sitModal._data == null) return
        const action = event.currentTarget.getAttribute('action') ?? ''
        if (action == 'paste-ref') {
            log('Pasted references value into editor: "' + sitModal._data.reference + '"')
            if (sitModal._data.mode == 'rte') {
                // @ts-ignore
                const ref = nl2br(sitModal._data.reference)
                sitModal._pasteIntoRTE(ref)
            }
            else {
                sitModal._$editor.val(sitModal._data.reference)
            }
            return
        }
        if (sitModal._data.mode == 'rte') {
            sitModal._removeRTE()
        }
        if (action == 'apply') {
            // Get text
            let text = (sitModal._$editor.val() ?? '').toString()
            // Remove newlines so REDCap doesn't replace them with <br> tags.
            text = text.split('\n').join(' ')
            // Call 'done' callback
            if (typeof sitModal._data.done == 'function') {
                sitModal._data.done(text)
            }
        }
        // Close and reset
        // @ts-ignore
        sitModal._modal.hide()
        sitModal._data = null
        sitModal._$editor = $()
    },
    _initRTE: () => {
        const selector = '#mlm-sit-rte';
        const directionality = sitModal._data != null && sitModal._data.lang.rtl ? 'rtl' : 'ltr';
        initializeRichTextEditor(sitModal._$, selector, directionality);
    },
    _removeRTE: function() {
        // @ts-ignore
        tinymce.remove('#mlm-sit-rte')
    },
    _pasteIntoRTE: function(text) {
        // @ts-ignore
        tinymce.EditorManager.activeEditor.selection.setContent(text);
    }
}

//#endregion

/**
 * Updates the Forms/Surveys tab
 */
function updateForms() {
    if (currentTab != 'forms') return // Nothing to do when Forms/Surveys tab is not shown
    log('Updating forms for [ ' + currentLanguage + ' ]')
    $('[data-mlm-tab="forms"] [data-mlm-mode]').hide()
    /** @type Language */
    var lang = config.settings.langs[currentLanguage]
    updateFormsTable(lang)
    updateSurveyQueueStatus()
    updateFormNameStatus()
}

/** Holds templates */
const templateCache = new Map();

/**
 * Adds all templates to a cache
 */
function initTemplateCache() {
    $('[data-mlm-template]').each(function() {
        const $this = $(this);
        const name = $this.attr('data-mlm-template');
        templateCache.set(name, $($this.html()));
    });
}
/**
 * Gets a template by name and returns its jQuery representation
 * @param {string} name 
 * @returns {JQuery<HTMLElement>}
 */
function getTemplate(name) {
    return templateCache.get(name).clone(false);
}

/**
 * Calls .show() or .hide() on the passed JQuery 
 * @param {JQuery<HTMLElement>} $el 
 * @param {boolean} show 
 */
function toggleVisibility($el, show) {
    if (show) {
        $el.show()
    } 
    else {
        $el.hide()
    }
}

function setNestedAttribute($el, attr, value) {
    $el.find('[' + attr + ']').attr(attr, value)
}

/**
 * Inject display values into a [data-mlm-display] element
 * @param {JQuery<HTMLElement>} $el 
 * @param {Object<string, string>} values 
 * @param {boolean} html 
 */
function injectDisplayValues($el, values, html = false) {
    const inject = function() {
        const $this = $(this)
        const name = $this.attr('data-mlm-display') ?? ''
        const explicitHtml = values.hasOwnProperty('html_' + name)
        const value = explicitHtml ? values['html_' + name] : (values[name] ?? '')
        if (html || explicitHtml) { 
            $this.html(value) 
        } else { 
            $this.text(value) 
        }
    }
    $el.find('[data-mlm-display]').each(inject)
    if ($el.length && $el[0].hasAttribute('data-mlm-display')) inject.call($el[0])
}

/**
 * Determines whether a project translatable item is translated
 * @param {string} type 
 * @param {string} name 
 * @param {string} index 
 * @returns {boolean}
 */
function isDdTranslated(type, name, index = '') {

    // Excluded fields / settings are considered translated
    if (type.startsWith('field-') && config.settings.excludedFields[name]) {
        return true
    }
    if (type.startsWith('survey-') && config.settings.excludedSettings[type]) {
        return true
    }

    // No references? Considered translated
    if (!config.projMeta.refMap[type] || 
        !config.projMeta.refMap[type][name] ||
        !config.projMeta.refMap[type][name][index] ||
        !config.projMeta.refMap[type][name][index].translation) {
        return true
    }

    // Check if a value is set
    if (currentLanguage) {
        const dd = config.settings.langs[currentLanguage]['dd']
        // Manually marked as complete?
        if (type.startsWith('field-') && dd['field-complete'] && dd['field-complete'][name]) {
            return true
        }
        if (dd.hasOwnProperty(type) && dd[type].hasOwnProperty(name) && !isEmpty(dd[type][name][index])) {
            return true
        }
    }
    return false
}

/**
 * Gets a value from the dd object
 * @param {Language} lang 
 * @param {string} type 
 * @param {string} name 
 * @param {string} name 
 * @returns {TranslationDataElement}
 */
function get_dd(lang, type, name, index = '') {
    const empty = { 
        translation: '',
        refHash: null,
    }
    if (lang.dd[type] && lang.dd[type][name]) {
        return lang.dd[type][name][index] ?? empty
    }
    return empty
}

/**
 * Sets a data dictionary value
 * @param {Language} lang 
 * @param {string} type 
 * @param {string} name 
 * @param {string} index 
 * @param {any} val 
 * @param {string|null} hash 
 * @param {boolean} update Whether UI updates should be performed
 */
function set_dd(lang, type, name, index, val, hash = null, update = true) {
    if (val) {
        // Cascading update/create
        if (!lang.dd.hasOwnProperty(type)) {
            lang.dd[type] = {};
        }
        if (!lang.dd[type].hasOwnProperty(name)) {
            lang.dd[type][name] = {};
        }
        if (hash == null) hash = getRefHash(type, name, index);
        lang.dd[type][name][index] = { 
            translation: val,
            refHash: hash
        };
        log('Set data item lang.dd[' + lang.key + '].' + type + '.' + name + '[' + index + '] to "' + val + '"');
    }
    else {
        try {
            // Cascading delete
            delete lang.dd[type][name][index];
            if (Object.keys(lang.dd[type][name]).length == 0) {
                delete lang.dd[type][name];
            }
            if (Object.keys(lang.dd[type]).length == 0) {
                delete lang.dd[type];
            }
        }
        catch {
            warn('Could not delete non-existing entry lang.dd.' + type + '.' + name + ' in [ ' + lang.key + ' ]');
        }
    }
    // Set (new) ref hash in textarea or input attribute
    let $textarea = $('textarea[data-mlm-translation="' + name + '"], textarea[data-mlm-name="' + name + '"]');
    if (type && type != '') $textarea = $textarea.filter('[data-mlm-type="' + type + '"]');
    if (index && index != '') $textarea = $textarea.filter('[data-mlm-index="' + index + '"]');
    $textarea.attr('data-mlm-refhash', hash);
    if ($textarea.length == 0) {
        let $input = $('input[data-mlm-translation="' + name + '"], input[data-mlm-name="' + name + '"]');
        if (type && type != '') $input = $input.filter('[data-mlm-type="' + type + '"]');
        if (index && index != '') $input = $input.filter('[data-mlm-index="' + index + '"]');
        $input.attr('data-mlm-refhash', hash);
    }
    // Update UI
    if (update && !['survey-active','form-active'].includes(type)) {
        if (type.startsWith('survey-')) {
            updateSurveySettingsTranslationStatus(type);
        }
        else if (type.startsWith('sq-')) {
            updateSurveyQueueStatus();
        }
        else if (type.startsWith('asi-')) {
            updateASITranslationStatus(index);
        }
        else if (type == 'mycap-app_title') {
            updateMyCapTranslationStatus('app_title');
        }
        else if (type.startsWith('task-')) {
            const formName = type == 'task-complete' ? name : config.projMeta.myCap.taskToForm[name];
            if (name && name.includes('-')) {
                updateDdTranslationStatus(name);
                // Update indicator for Event label
                updateEventTaskTranslationStatus('div[data-mlm-event-tasks="'+name+'"]');
            }
            else {
                updateDdTranslationStatus('task-' + formName);
            }
        }
        else if (type.startsWith('mycap-')) {
            updateMyCapTranslationStatus(name);
        }
        else if (type.startsWith('alert-')) {
            updateAlertTranslationStatus(name);
        }
        else if (type.startsWith('mdc-')) {
            updateMissingDataCodesTranslationStatus();
        }
        else if (type.startsWith('pdf-')) {
            updatePDFCustomizationsTranslationStatus();
        }
        else if (type.startsWith('protmail-')) {
            updateProtectedMailTranslationStatus();
        }
        else if (type == 'form-name') {
            updateFormNameStatus();
        }
        else if (type.startsWith('descriptive-popup')) {
            updateDescriptivePopupsTranslationStatus(index, true);
        }
        else {
            updateDdTranslationStatus(name);
        }
    }
}

/**
 * Gets a reference hash for the specified path
 * @param {string} type 
 * @param {string} name 
 * @param {string} index 
 * @returns {string|null} Returns 'NoHash' in case the hash is not defined
 */
function getRefHash(type, name, index) {
    if ([
        'form-active',
        'survey-active',
        'field-complete',
        'matrix-complete',
        'task-complete',
        'descriptive-popup-complete'
    ].includes(type)) {
        return null;
    }
    try {
        return config.projMeta.refMap[type][name][index].refHash;
    }
    catch {
        error('No reference hash for [ ' + [type, name, index].join(':') + ' ]');
    }
    return 'NoHash';
}

/**
 * Gets a reference value for the specified path
 * @param {string} type 
 * @param {string} name 
 * @param {string} index 
 * @returns {string} 
 */
function getRefValue(type, name, index) {
    if (['form-active', 'survey-active', 'field-complete', 'matrix-complete'].includes(type)) {
        return ''
    }
    try {
        return config.projMeta.refMap[type][name][index].refValue
    }
    catch {
        error('No reference value for [ ' + [type, name, index].join('-') + ' ]')
    }
    return ''
}

/**
 * Gets a reference value for the specified path
 * @param {string} type 
 * @param {string} name 
 * @param {string} index 
 * @returns {string|null} 
 */
function getRefPrompt(type, name, index) {
    if ([
        'form-active',
        'survey-active',
        'field-complete',
        'matrix-complete',
        'task-complete',
        'descriptive-popup-complete'
    ].includes(type)) {
        return null
    }
    try {
        return config.projMeta.refMap[type][name][index].prompt
    }
    catch {
        error('No reference prompt for [ ' + [type, name, index].join('-') + ' ]')
    }
    return ''
}


//#endregion

//#region ---- Translate Single DD Item

/**
 * Translates a single DD item
 * - Queue Custom Text
 * - Custom Login Text
 * - Form Name
 * @param {string} title 
 * @param {string} type 
 * @param {string} name 
 * @param {'rte'|'single'|'multiline'} mode `rte`, `single`, or `multiline` 
 */
function translateSingleDdItem(title, type, name = '', mode = 'rte') {
    const lang = config.settings.langs[currentLanguage];
    const index = '';
    const refHash = getRefHash(type, name, index);
    sitModal.show({
        mode: mode,
        lang: lang,
        title: title,
        type: type,
        name: name,
        index: index,
        reference: getRefValue(type, name, index),
        refHash: refHash,
        value: get_dd(lang, type, name, index).translation,
        done: function(val) {
            set_dd(lang, type, name, index, val, refHash);
            checkDirty(currentLanguage, type);
            if (type.startsWith('sq-')) updateSurveyQueueStatus();
            if (type == 'form-name') updateFormNameStatus();
        }
    });
}

//#endregion

//#endregion

//#region -- MyCap Tab

/**
 * Renders the MyCap tab
 */
function renderMyCapTab() {
    if (currentTab != 'mycap') return // Do nothing when not on the Misc tab
    if (!['mycap-app_title', 'mycap-about', 'mycap-contacts', 'mycap-links'].includes(currentMyCapTabCategory)) {
        activateCategory('mycap-app_title');
        return;
    } 
    const lang = config.settings.langs[currentLanguage]
    const $tab = $('div[data-mlm-tab="mycap"]')
    if (lang.key == config.settings.refLang) {
        $tab.find('.hide-when-ref-lang').hide()
        $tab.find('.disable-when-ref-lang').prop('disabled', true)
    }
    else {
        $tab.find('.hide-when-ref-lang').show()
        $tab.find('.disable-when-ref-lang').prop('disabled', false)
    }
    log("Updated 'MyCap' tab.")
    if (currentMyCapTabCategory == 'mycap-app_title') {
        renderMyCapTitle();
    }
    else if (currentMyCapTabCategory == 'mycap-about') {
        renderMyCapAbout();
    }
    else if (currentMyCapTabCategory == 'mycap-contacts') {
        renderMyCapContacts();
    }
    else if (currentMyCapTabCategory == 'mycap-links') {
        renderMyCapLinks();
    }
}

//#region ---- App Title & Baseline Date Task

function renderMyCapTitle() {
    if (currentTab != 'mycap' || currentMyCapTabCategory != 'mycap-app_title') return; // Only work when on correct tab/category
    const lang = config.settings.langs[currentLanguage];
    const $tab = $('.mlm-mycap-category-tab[data-mlm-sub-category="mycap-app_title"]');
    const value = get_dd(lang, 'mycap-app_title', '', '');
    const hash = value.refHash ?? config.projMeta.myCap["mycap-app_title"].refHash;
    $tab.find('input[data-mlm-type="mycap-app_title"]')
        .val(value.translation)
        .attr('data-mlm-ref-hash', hash);
    // Baseline Date Task
    for (const name of Object.keys(config.projMeta.myCap['mycap-baseline_task'])) {
        const value = get_dd(lang, 'mycap-baseline_task', name, '');
        const hash = value.refHash ?? config.projMeta.myCap['mycap-baseline_task'][name].refHash;
        $tab.find(':input[data-mlm-name="' + name + '"]')
            .val(value.translation)
            .attr('data-mlm-ref-hash', hash);
    }
    updateMyCapTranslationStatus('app_title', true);
    log('Rendered MyCap app title & baseline date task tab for [' + lang.key + ']')
}

//#endregion

//#region ---- About Pages

function renderMyCapAbout() {
    if (currentTab != 'mycap' || currentMyCapTabCategory != 'mycap-about') return; // Only work when on correct tab/category
    const lang = config.settings.langs[currentLanguage];
    const $tab = $('.mlm-mycap-category-tab[data-mlm-sub-category="mycap-about"]');
    const doSearch = moveMyCapSearch($tab);
    const $container = $tab.find('div[data-mlm-render="mycap-settings"]');
    $container.children().remove();
    let numSettings = 0;
    const mycapType = 'pages';
    const mycapItemKind = window['lang'].mycap_mobile_app_02; // About
    for (const id of getSortedMyCapSettingIds(mycapType)) {
        numSettings++
        const item = config.projMeta.myCap[mycapType][id];
        const $item = getTemplate('mycap-setting').attr('data-mlm-mycap-setting', id)
        injectDisplayValues($item, {
            'mycap-item-kind': mycapItemKind,
            'mycap-number': item.order,
            'mycap-title': getRefValue('mycap-about-page_title', id, ''),
        });
        setNestedAttribute($item, 'data-mlm-mycap-id', id);
        setNestedAttribute($item, 'data-mlm-mycap-item-type', mycapType);
        const $mycapItemsContainer = $item.find('.mlm-mycap-items');
        for (const type of [
            'mycap-about-page_title',
            'mycap-about-page_content'
        ]) {
            const empty = isEmpty(item[type].reference);
            const value = get_dd(lang, type, id);
            const hash = value.refHash ?? item[type].refHash;
            // When both reference and current are empty, then skip this item (unless in debug mode)
            if (empty && isEmpty(value) && !config.settings.debug) continue;
            const $mycapItem = getTemplate('mycap-setting-' + item[type].mode).attr('data-mlm-mycap-setting', type);
            $mycapItem.find('label.mlm-translation-prompt').after(getTemplate('reference-value'));
            injectDisplayValues($mycapItem, {
                html_prompt: item[type].prompt,
                reference: empty ? $lang.multilang_06 : item[type].reference,
            }, empty);
            setNestedAttribute($mycapItem, 'data-mlm-name', id);
            setNestedAttribute($mycapItem, 'data-mlm-type', type);
            setNestedAttribute($mycapItem, 'data-mlm-refhash', hash);
            $mycapItem.find('[data-mlm-translation]').val(value.translation);
            // No rich text support for MyCap items
            $mycapItem.find('button[data-mlm-action="rich-text-editor"]').remove();
            $mycapItemsContainer.append($mycapItem);
        }
        $container.append($item);
        updateMyCapTranslationStatus(id, true);
    }
    // Toggle 'No MyCap Settings'
    $('.show-when-no-mycap-settings')[numSettings < 1 ? 'removeClass' : 'addClass']('hide')
    $('.hide-when-no-mycap-settings')[numSettings < 1 ? 'addClass' : 'removeClass']('hide')
    // @ts-ignore
    $container.find('.textarea-autosize').on('focus', function() { $(this).trigger('input'); }).textareaAutoSize()
    $container.find('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
    log('Rendered MyCap "About" tab for [' + lang.key + ']')
    doSearch.go();
}

//#endregion

//#region ---- Contacts

function renderMyCapContacts() {
    if (currentTab != 'mycap' || currentMyCapTabCategory != 'mycap-contacts') return; // Only work when on correct tab/category
    const lang = config.settings.langs[currentLanguage];
    const $tab = $('.mlm-mycap-category-tab[data-mlm-sub-category="mycap-contacts"]');
    const doSearch = moveMyCapSearch($tab);
    const $container = $tab.find('div[data-mlm-render="mycap-settings"]');
    $container.children().remove();
    let numSettings = 0;
    const mycapType = 'contacts';
    const mycapItemKind = window['lang'].index_09; // Contact
    for (const id of getSortedMyCapSettingIds(mycapType)) {
        numSettings++
        const item = config.projMeta.myCap[mycapType][id];
        const $item = getTemplate('mycap-setting').attr('data-mlm-mycap-setting', id)
        injectDisplayValues($item, {
            'mycap-item-kind': mycapItemKind,
            'mycap-number': item.order,
            'mycap-title': getRefValue('mycap-contact-contact_header', id, ''),
        });
        setNestedAttribute($item, 'data-mlm-mycap-id', id);
        setNestedAttribute($item, 'data-mlm-mycap-item-type', mycapType);
        const $mycapItemsContainer = $item.find('.mlm-mycap-items');
        for (const type of [
            'mycap-contact-contact_header',
            'mycap-contact-contact_title',
            'mycap-contact-phone_number',
            'mycap-contact-email',
            'mycap-contact-website',
            'mycap-contact-additional_info'
        ]) {
            const empty = isEmpty(item[type].reference);
            const value = get_dd(lang, type, id);
            const hash = value.refHash ?? item[type].refHash;
            // When both reference and current are empty, then skip this item (unless in debug mode)
            if (empty && isEmpty(value) && !config.settings.debug) continue;
            const $mycapItem = getTemplate('mycap-setting-' + item[type].mode).attr('data-mlm-mycap-setting', type);
            $mycapItem.find('label.mlm-translation-prompt').after(getTemplate('reference-value'));
            injectDisplayValues($mycapItem, {
                html_prompt: item[type].prompt,
                reference: empty ? $lang.multilang_06 : item[type].reference,
            }, empty);
            setNestedAttribute($mycapItem, 'data-mlm-name', id);
            setNestedAttribute($mycapItem, 'data-mlm-type', type);
            setNestedAttribute($mycapItem, 'data-mlm-refhash', hash);
            $mycapItem.find('[data-mlm-translation]').val(value.translation);
            $mycapItem.find('[data-mlm-translation]').attr('data-mlm-empty-text', empty)
            // No rich text support for MyCap items
            $mycapItem.find('button[data-mlm-action="rich-text-editor"]').remove();
            $mycapItemsContainer.append($mycapItem);
        }
        $container.append($item);
        updateMyCapTranslationStatus(id, true);
    }
    // Toggle 'No MyCap Settings'
    $('.show-when-no-mycap-settings')[numSettings < 1 ? 'removeClass' : 'addClass']('hide')
    $('.hide-when-no-mycap-settings')[numSettings < 1 ? 'addClass' : 'removeClass']('hide')
    // @ts-ignore
    $container.find('.textarea-autosize').on('focus', function() { $(this).trigger('input'); }).textareaAutoSize()
    $container.find('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
    log('Rendered MyCap "Contacts" tab for [' + lang.key + ']')
    doSearch.go();
}

//#endregion

//#region ---- Links

function renderMyCapLinks() {
    if (currentTab != 'mycap' || currentMyCapTabCategory != 'mycap-links') return; // Only work when on correct tab/category
    const lang = config.settings.langs[currentLanguage];
    const $tab = $('.mlm-mycap-category-tab[data-mlm-sub-category="mycap-links"]');
    const doSearch = moveMyCapSearch($tab);
    const $container = $tab.find('div[data-mlm-render="mycap-settings"]');
    $container.children().remove();
    let numSettings = 0;
    const mycapType = 'links';
    const mycapItemKind = window['lang'].design_196; // Link
    for (const id of getSortedMyCapSettingIds(mycapType)) {
        numSettings++
        const item = config.projMeta.myCap[mycapType][id];
        const $item = getTemplate('mycap-setting').attr('data-mlm-mycap-setting', id)
        injectDisplayValues($item, {
            'mycap-item-kind': mycapItemKind,
            'mycap-number': item.order,
            'mycap-title': getRefValue('mycap-link-link_name', id, ''),
        });
        setNestedAttribute($item, 'data-mlm-mycap-id', id);
        setNestedAttribute($item, 'data-mlm-mycap-item-type', mycapType);
        const $mycapItemsContainer = $item.find('.mlm-mycap-items');
        for (const type of [
            'mycap-link-link_name',
            'mycap-link-link_url',
        ]) {
            const empty = isEmpty(item[type].reference);
            const value = get_dd(lang, type, id);
            const hash = value.refHash ?? item[type].refHash;
            // When both reference and current are empty, then skip this item (unless in debug mode)
            if (empty && isEmpty(value) && !config.settings.debug) continue;
            const $mycapItem = getTemplate('mycap-setting-' + item[type].mode).attr('data-mlm-mycap-setting', type);
            $mycapItem.find('label.mlm-translation-prompt').after(getTemplate('reference-value'));
            injectDisplayValues($mycapItem, {
                html_prompt: item[type].prompt,
                reference: empty ? $lang.multilang_06 : item[type].reference,
            }, empty);
            setNestedAttribute($mycapItem, 'data-mlm-name', id);
            setNestedAttribute($mycapItem, 'data-mlm-type', type);
            setNestedAttribute($mycapItem, 'data-mlm-refhash', hash);
            $mycapItem.find('[data-mlm-translation]').val(value.translation);
            // No rich text support for MyCap items
            $mycapItem.find('button[data-mlm-action="rich-text-editor"]').remove();
            $mycapItemsContainer.append($mycapItem);
        }
        $container.append($item);
        updateMyCapTranslationStatus(id, true);
    }
    // Toggle 'No MyCap Settings'
    $('.show-when-no-mycap-settings')[numSettings < 1 ? 'removeClass' : 'addClass']('hide')
    $('.hide-when-no-mycap-settings')[numSettings < 1 ? 'addClass' : 'removeClass']('hide')
    // @ts-ignore
    $container.find('.textarea-autosize').on('focus', function() { $(this).trigger('input'); }).textareaAutoSize()
    $container.find('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
    log('Rendered MyCap "Links" tab for [' + lang.key + ']')
    doSearch.go();
}

//#endregion

//#region ---- Helpers

/**
 * Moves the search widget to the specific sub-tab
 * @param {JQuery<HTMLElement>} $tab
 * @param {boolean} clear
 * @returns {object}
 */
function moveMyCapSearch($tab, clear = false) {
    const $mycapSearch = $('#mycap-search-tool');
    const itemKind = $tab.attr('data-mlm-sub-category') ?? '';
    const $input = $mycapSearch.find('input[data-mlm-config="mycap-search"]');
    $input.attr('data-mlm-mycap-item-kind', itemKind);
    if (clear) $input.val('');
    $tab.prepend($mycapSearch);
    log($tab, $mycapSearch);
    function search() {
        lastMyCapSearchTerm = '';
        $input.trigger('change');
    }
    return {
        go: search
    }
}

/**
 * Returns an array of MyCap setting ids sorted by order
 * @param {'pages'|'contacts'|'links'} type The resource type
 * @returns {string[]}
 */
function getSortedMyCapSettingIds(type) {
    const ids = Object.keys(config.projMeta.myCap[type])
    ids.sort(function(a, b) {
        const order_a = config.projMeta.myCap[type][a].order;
        const order_b = config.projMeta.myCap[type][b].order;
        return order_a < order_b ? -1 : 1
    })
    return ids
}

/**
 * Updates the translation status icons (red/green); additionally highlight any
 * changed reference values
 * @param {string} id Name of the MyCap setting
 * @param {boolean} collapse When true, will collapse the alert when fully translated
 */
function updateMyCapTranslationStatus(id, collapse = false) {
    let $item;
    if (id == 'app_title' || id.startsWith('mycap-')) {
        $item = $('div[data-mlm-mycap-setting="app_title"]').parent();
    }
    else {
        $item = $('[data-mlm-sub-category="'+currentMyCapTabCategory+'"] div[data-mlm-mycap-setting="' + id + '"]');
    }
    if ($item.length != 1) {
        error('Unable to find MyCap setting block "' + id + '"');
        return;
    }
    if (id == 'app_title') collapse = false; // Never collapse the app title
    let allTranslated = true;
    $item.find('[data-mlm-mycap-setting]').each(function() {
        const $this = $(this);
        const $val = $this.find('[data-mlm-translation]');
        const type = $val.attr('data-mlm-type') ?? '';
        const name = $val.attr('data-mlm-name') ?? '';
        const index = $val.attr('data-mlm-index') ?? '';
        // Reference hash mismatch indicator
        const hash = $val.attr('data-mlm-refhash') ?? null;
        const refHashMismatch = hasRefHashMismatch(hash, type, name, index);
        const refHashFunc = refHashMismatch ? 'show' : 'hide';
        $this.find('[data-mlm-ref-changed]')[refHashFunc]();
        // Translation status
        const itemTranslated = isDdTranslated(type, name, index);
        const translatedClass = itemTranslated ? 'badge-success' : 'badge-danger';
        $this.find('[data-mlm-indicator="mycap-setting-translated"]').removeClass('badge-success badge-danger badge-light').addClass(translatedClass);
        allTranslated = allTranslated && itemTranslated;
    });
    // Set overall translation status
    $item.find('[data-mlm-indicator="mycap-translated"]')
        .removeClass('badge-success badge-danger badge-light')
        .addClass(allTranslated ? 'badge-success' : 'badge-danger');
    // Collapse?
    if (allTranslated && collapse) {
        toggleMyCapItem(id);
    }
}

/**
 * Toggles display (collapse/expand) of an MyCap setting block
 * @param {string} id 
 * @param {null|'expand'|'collapse'} force 
 * @param {boolean} focus
 */
function toggleMyCapItem(id, force = null, focus = false) {
    const $item = $('div[data-mlm-mycap-setting="' + id + '"]');
    const collapsed = ($item.find('.mlm-mycap-items').hasClass('hide') || force == 'expand') && force != 'collapse';
    if (collapsed) {
        // Expand
        $item.find('.show-when-collapsed').addClass('hide');
        $item.find('.hide-when-collapsed').removeClass('hide');
        if (focus) $item.find('input').first().trigger('focus');
        // When expanding, also clear any mlm-mycap-setting-item-search-exclude classes
        $item.find('.mlm-mycap-setting-item-search-exclude').removeClass('mlm-mycap-setting-item-search-exclude');
    }
    else {
        // Collapse
        $item.find('.show-when-collapsed').removeClass('hide');
        $item.find('.hide-when-collapsed').addClass('hide');
    }
}

/**
 * Filters the MyCap tab to only show items that contain the search string
 * @param {boolean} force
 */
function performMyCapSearch(force = false) {
    const $searchInput = $('input[data-mlm-config="mycap-search"]');
    const itemKind = $searchInput.attr('data-mlm-mycap-item-kind');
    const needle = ($searchInput.val() ?? '').toString().toLowerCase();
    if (needle != lastMyCapSearchTerm || force) {
        const $filterContainer = $searchInput.parents('.mlm-mycap-category-tab').find('div[data-mlm-render="mycap-settings"]');
        $filterContainer.find('[data-mlm-mycap-setting]').each(function() {
            const $this = $(this);
            // Does the text content of the entire item contain the search term?
            // Need to add value of input element explicitly
            let text = '';
            $this.find('[data-mlm-searchable]').each(function() {
                text += ' ' + $(this).text();
            });
            $this.find('[data-mlm-translation]').each(function() {
                text += ' ' + $(this).val();
            });
            if (text.toLowerCase().includes(needle)) {
                toggleMyCapItem($this.attr('data-mlm-mycap-setting') ?? '', 'expand');
                $this.removeClass('mlm-mycap-setting-item-search-exclude');
            }
            else {
                $this.addClass('mlm-mycap-setting-item-search-exclude');
            }
        });
        // Store last search term in order to prevent unnecessary work
        lastMyCapSearchTerm = needle;
        log('Executed MyCap search for "' + needle + '"');
    }
    // Toggle display of the no items indicator
    const $container = $('[data-mlm-sub-category="' + itemKind + '"]');
    if ($container.find('[data-mlm-mycap-setting]').not('.mlm-mycap-setting-item-search-exclude').length) {
        $container.find('.mlm-mycap-setting-no-items').addClass('hide');
    }
    else {
        $container.find('.mlm-mycap-setting-no-items').removeClass('hide');
    }
}
var lastMyCapSearchTerm = ''

//#endregion

//#endregion

//#region -- Alerts & Notifications

/**
 * Returns an array of alert ids sorted by alert number
 * @returns {string[]}
 */
function getSortAlertIds() {
    const alertIds = Object.keys(config.projMeta.alerts)
    alertIds.sort(function(a, b) {
        const alert_a = Number.parseInt(config.projMeta.alerts[a].alertNum.replace('#', ''))
        const alert_b = Number.parseInt(config.projMeta.alerts[b].alertNum.replace('#', ''))
        return alert_a < alert_b ? -1 : 1
    })
    return alertIds
}

/**
 * Renders the alerts exclusion table
 */
function renderAlertsExclusionTable() {
    const $tbody = $('#mlm-alets-exclusion-rows');
    $tbody.children().remove();
    for (const alertId of getSortAlertIds()) {
        const alert = config.projMeta.alerts[alertId];
        const $row = getTemplate('alert-exclusion-row');
        $row.attr('data-mlm-alert', alertId);
        injectDisplayValues($row, {
            'alert-id': alert.uniqueId,
            'alert-name': alert.title,
            'alert-number': alert.alertNum,
        });
        $row.find('[data-mlm-type="alert-excluded"]')
            .prop('checked', config.settings.excludedAlerts[alertId] == true)
            .attr('id', 'alert-excluded-' + alertId)
            .attr('data-mlm-name', alertId);
        $row.find('[data-mlm-type="alert-source"]')
            .attr('data-mlm-name', alertId)
            .attr('id', 'alert-source-' + alertId)
            .val(config.settings.alertSources[alertId]);
        $tbody.append($row);
    }
    if (Object.keys(config.projMeta.alerts).length == 0) {
        $tbody.append(getTemplate('no-alerts-exclusion-row'));
    }
    log('Rendered Alerts & Notifications exclusion tab');
}

function renderAlertsTranslation() {
    const $container = $('div[data-mlm-render="alerts"]');
    $container.children().remove();
    const lang = config.settings.langs[currentLanguage];
    let numAlerts = 0;
    for (const alertId of getSortAlertIds()) {
        // Excluded?
        if (config.settings.excludedAlerts[alertId] == true) continue;
        numAlerts++;
        const alert = config.projMeta.alerts[alertId];
        const $alert = getTemplate('alert-settings').attr('data-mlm-alert', alertId);
        injectDisplayValues($alert, {
            'alert-name': alert.title,
            'alert-id': alert.uniqueId,
            'alert-number': alert.alertNum,
        });
        setNestedAttribute($alert, 'data-mlm-alert-id', alertId);
        const $alertItemsContainer = $alert.find('.mlm-alert-items');
        if (alert.type === 'SENDGRID_TEMPLATE') {
            const type = 'alert-sendgrid_template_data';
            let sendgridTemplateData;
            try {
                sendgridTemplateData = JSON.parse(alert[type].reference);
            } catch (e) {
                error('Failed to JSON parse sendgrid_template_data');
                sendgridTemplateData = {};
            }
            for (const key in sendgridTemplateData) {
                const value = get_dd(lang, type, alertId, key);
                const hash = value.refHash ?? alert[type].refHash;
                const templateData = sendgridTemplateData[key];
                const $alertItem = getTemplate('alert-setting-text').attr('data-mlm-alert-setting', type);
                $alertItem.find('label.mlm-translation-prompt').after(getTemplate('reference-value'));
                injectDisplayValues($alertItem, {
                    html_prompt: [alert[type].prompt, key].join(' '),
                    reference: templateData,
                }, false);
                setNestedAttribute($alertItem, 'data-mlm-name', alertId);
                setNestedAttribute($alertItem, 'data-mlm-type', type);
                setNestedAttribute($alertItem, 'data-mlm-index', key);
                setNestedAttribute($alertItem, 'data-mlm-refhash', hash);
                setNestedAttribute($alertItem, 'id', type + '-' + alertId);
                $alertItem.find('[data-mlm-translation]').val(value.translation);
                if (!rteSupported) $alertItem.find('button[data-mlm-action="rich-text-editor"]').remove();
                $alertItemsContainer.append($alertItem);
            }
        } else {
            for (const type of ['alert-email_from_display','alert-email_subject','alert-alert_message']) {
                const empty = isEmpty(alert[type].reference);
                const value = get_dd(lang, type, alertId);
                const hash = value.refHash ?? alert[type].refHash;
                // When both reference and current are empty, then skip this item (unless in debug mode)
                if (empty && isEmpty(value) && !config.settings.debug) continue;
                const $alertItem = getTemplate('alert-setting-' + alert[type].mode).attr('data-mlm-alert-setting', type);
                $alertItem.find('label.mlm-translation-prompt').after(getTemplate('reference-value'));
                injectDisplayValues($alertItem, {
                    html_prompt: alert[type].prompt,
                    reference: empty ? $lang.multilang_06 : alert[type].reference,
                }, empty);
                setNestedAttribute($alertItem, 'data-mlm-name', alertId);
                setNestedAttribute($alertItem, 'data-mlm-type', type);
                setNestedAttribute($alertItem, 'data-mlm-refhash', hash);
                setNestedAttribute($alertItem, 'id', type + '-' + alertId);
                $alertItem.find('[data-mlm-translation]').val(value.translation);
                if (!rteSupported) $alertItem.find('button[data-mlm-action="rich-text-editor"]').remove();
                $alertItemsContainer.append($alertItem);
            }
        }
        $container.append($alert);
        updateAlertTranslationStatus(alertId, true);
    }
    // Toggle 'no alerts'
    $('.show-when-no-alerts')[numAlerts < 1 ? 'removeClass' : 'addClass']('hide');
    $('.hide-when-no-alerts')[numAlerts < 1 ? 'addClass' : 'removeClass']('hide');
    // @ts-ignore
    $container.find('.textarea-autosize').on('focus', function() { $(this).trigger('input'); }).textareaAutoSize();
    // @ts-ignores
    $container.find('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
    log('Rendered Alerts & Notifications translation tab [' + lang.key + ']');
}

/**
 * Updates the translation status icons (red/green); additionally highlight any
 * changed reference values
 * @param {string} alertId Name of the asi setting
 * @param {boolean} collapse When true, will collapse the alert when fully translated
 */
 function updateAlertTranslationStatus(alertId, collapse = false) {
    const $item = $('div[data-mlm-alert="' + alertId + '"]')
    if ($item.length != 1) {
        error('Unable to find Alert block "' + alertId + '"')
        return
    }
    let allTranslated = true
    $item.find('[data-mlm-alert-setting]').each(function() {
        const $this = $(this)
        const $val = $this.find('[data-mlm-translation]')
        const type = $val.attr('data-mlm-type') ?? ''
        const name = $val.attr('data-mlm-name') ?? ''
        const index = $val.attr('data-mlm-index') ?? ''
        // Reference hash mismatch indicator
        const hash = $val.attr('data-mlm-refhash') ?? null
        const refHashMismatch = hasRefHashMismatch(hash, type, name, index)
        const refHashFunc = refHashMismatch ? 'show' : 'hide'
        $this.find('[data-mlm-ref-changed]')[refHashFunc]()
        // Translation status
        const itemTranslated = isDdTranslated(type, name, index)
        const translatedClass = itemTranslated ? 'badge-success' : 'badge-danger'
        $this.find('[data-mlm-indicator="alert-setting-translated"]').removeClass('badge-success badge-danger badge-light').addClass(translatedClass)
        allTranslated = allTranslated && itemTranslated
    })
    // Set overall translation status
    $item.find('[data-mlm-indicator="alert-translated"]')
        .removeClass('badge-success badge-danger badge-light')
        .addClass(allTranslated ? 'badge-success' : 'badge-danger')
    // Collapse?
    if (allTranslated && collapse) {
        toggleAlert(alertId)
    }
}

/**
 * Toggles display (collapse/expand) of an alert block
 * @param {string} alertId 
 * @param {null|'expand'|'collapse'} force 
 * @param {boolean} focus
 */
function toggleAlert(alertId, force = null, focus = false) {
    const $alert = $('div[data-mlm-alert="' + alertId + '"]')
    const collapsed = ($alert.find('.mlm-alert-items').hasClass('hide') || force == 'expand') && force != 'collapse'
    if (collapsed) {
        // Expand
        $alert.find('.show-when-collapsed').addClass('hide')
        $alert.find('.hide-when-collapsed').removeClass('hide')
        if (focus) $alert.find('input').first().trigger('focus');
        // Also, remove an sub-items hidden by search
        $alert.find('.mlm-alert-item-search-exclude').removeClass('mlm-alert-item-search-exclude');
    }
    else {
        // Collapse
        $alert.find('.show-when-collapsed').removeClass('hide')
        $alert.find('.hide-when-collapsed').addClass('hide')
    }
}

/**
 * Filters the alerts tab to only show items that contain the search string
 * @param {boolean} force
 */
function performAlertsSearch(force = false) {
    const needle = ($('input[data-mlm-config="alerts-search"]').val() ?? '').toString().toLowerCase()
    if (needle != lastAlertsSearchTerm || force) {
        $('[data-mlm-alert-setting]').each(function() {
            const $this = $(this)
            const $alert = $this.parents('div[data-mlm-alert]')
            // Does the text content of the entire item contain the search term?
            // Need to add value of input element explicitly
            // Furthermore, add the parents alert name
            const text = ($alert.find('[data-mlm-display="alert-name"]').text() + ' ' + $this.find('[data-mlm-translation]').val() + ' ' + $this.text()).toLowerCase()
            if (text.includes(needle)) {
                toggleAlert($alert.attr('data-mlm-alert') ?? '', 'expand')
                $this.removeClass('mlm-alert-item-search-exclude')
            }
            else {
                $this.addClass('mlm-alert-item-search-exclude')
            }
        })
        // Store last search term in order to prevent unnecessary work
        lastAlertsSearchTerm = needle
        log('Executed alerts search for "' + needle + '"')
    }
    // Toggle display of the no items indicator
    if ($('[data-mlm-alert-setting]').not('.mlm-alert-item-search-exclude').length) {
        $('.mlm-alerts-no-items').addClass('hide')
    }
    else {
        $('.mlm-alerts-no-items').removeClass('hide')
    }
}
var lastAlertsSearchTerm = ''

//#endregion

//#region -- Misc Tab

//#region ---- Common

/**
 * Updates the Misc tab
 */
function updateMiscTab() {
    if (currentTab != 'misc') return; // Do nothing when not on the Misc tab
    const lang = config.settings.langs[currentLanguage];
    const $tab = $('div[data-mlm-tab="misc"]');
    if (lang.key == config.settings.refLang) {
        $tab.find('.hide-when-ref-lang').hide();
        $tab.find('.disable-when-ref-lang').prop('disabled', true);
    }
    else {
        $tab.find('.hide-when-ref-lang').show();
        $tab.find('.disable-when-ref-lang').prop('disabled', false);
    }
    log("Updated 'Misc' tab.");
    if (currentMiscTabCategory == 'misc-mdc') {
        renderMissingDataCodes();
    }
    else if (currentMiscTabCategory == 'misc-pdf') {
        updatePDFCustomizations();
    }
    else if (currentMiscTabCategory == 'misc-descriptive-popups') {
        updateDescriptivePopups();
    }
    else if (currentMiscTabCategory == 'misc-protmail') {
        updateProtectedMailSettings();
    }
}

//#endregion

//#region ---- Missing Data Codes

/**
 * Renders the missing data codes table
 */
function renderMissingDataCodes() {
    if (currentTab != 'misc' || currentMiscTabCategory != 'misc-mdc') return // Only work when on correct tab/category
    const lang = config.settings.langs[currentLanguage]
    const $tbody = $('div[data-mlm-sub-category="misc-mdc"] tbody')
    $tbody.children().remove()
    for (const mdc of Object.keys(config.projMeta.mdcs)) {
        const mdcRef = config.projMeta.mdcs[mdc]
        const $row = getTemplate('field-item-table-row').attr('data-mlm-choice', mdc)
        $row.find('.mlm-choices-table-translation').prepend(getTemplate('reference-value'))
        injectDisplayValues($row, {
            code: mdc,
            reference: mdcRef.reference,
        })
        const val = get_dd(lang, 'mdc-label', mdc)
        $row.find('label').attr('for', 'mdc.' + mdc)
        $row.find('input').attr('id', 'mdc.' + mdc).val(val.translation)
        setNestedAttribute($row, 'data-mlm-name', mdc)
        setNestedAttribute($row, 'data-mlm-type', 'mdc-label')
        setNestedAttribute($row, 'data-mlm-refhash', val.refHash ?? getRefHash('mdc-label', mdc, ''))
        $tbody.append($row)
    }
    updateMissingDataCodesTranslationStatus()
    log('Rendered missing data codes for [' + lang.key + ']')
}

/**
 * Updates the translation status icons (red/green); additionally highlight any
 * changed reference values
 */
function updateMissingDataCodesTranslationStatus() {
    const $item = $('[data-mlm-sub-category="misc-mdc"]')
    let allTranslated = true
    $item.find('tr[data-mlm-choice]').each(function() {
        const $this = $(this)
        const $val = $this.find('[data-mlm-translation]')
        const type = $val.attr('data-mlm-type') ?? ''
        const name = $val.attr('data-mlm-name') ?? ''
        // Reference hash mismatch indicator
        const hash = $val.attr('data-mlm-refhash') ?? null
        const refHashMismatch = hasRefHashMismatch(hash, type, name)
        const refHashFunc = refHashMismatch ? 'show' : 'hide'
        $this.find('[data-mlm-ref-changed]')[refHashFunc]()
        // Translation status
        const itemTranslated = isDdTranslated(type, name)
        const translatedClass = itemTranslated ? 'badge-success' : 'badge-danger'
        $this.find('[data-mlm-indicator="field-choice-translated"]').removeClass('badge-success badge-danger badge-light').addClass(translatedClass)
        allTranslated = allTranslated && itemTranslated
    })
    // Set overall translation status
    $item.find('[data-mlm-indicator="mdcs-translated"]')
        .removeClass('badge-success badge-danger badge-light')
        .addClass(allTranslated ? 'badge-success' : 'badge-danger')
}

//#endregion

//#region ---- PDF Customizations

function updatePDFCustomizations() {
    /** @type {Language} */
    const lang = config.settings.langs[currentLanguage]
    // User Interface
    $('div[data-mlm-sub-category="misc-pdf"] [data-mlm-translation]').each(function() {
        const $el = $(this) 
        const name = ''
        const type = $el.attr('data-mlm-type') ?? ''
        const index = ''
        const val = lang.dd.hasOwnProperty(type) ? lang.dd[type][name][index].translation : ''
        $el.val(val)
        updatePDFCustomizationsTranslationStatus()
    })
}

function updatePDFCustomizationsTranslationStatus() {
    $('div[data-mlm-sub-category="misc-pdf"] [data-mlm-pdf-setting]').each(function() {
        const $this = $(this)
        const $val = $this.find('[data-mlm-translation]')
        const type = $val.attr('data-mlm-type') ?? ''
        const name = $val.attr('data-mlm-name') ?? ''
        const index = $val.attr('data-mlm-index') ?? ''
        // Reference hash mismatch indicator
        const hash = $val.attr('data-mlm-refhash') ?? null
        const refHashMismatch = hasRefHashMismatch(hash, type, name, index)
        const refHashFunc = refHashMismatch ? 'show' : 'hide'
        $this.find('[data-mlm-ref-changed]')[refHashFunc]()
        // Translation status
        const itemTranslated = isDdTranslated(type, name, index)
        const translatedClass = itemTranslated ? 'badge-success' : 'badge-danger'
        $this.find('[data-mlm-indicator="pdf-setting-translated"]').removeClass('badge-success badge-danger badge-light').addClass(translatedClass)
    })
}

//#endregion

//#region ---- Descriptive Popups

function updateDescriptivePopups() {
    if (currentLanguage == config.settings.refLang) return; // Do nothing when on the reference language
    /** @type {Language} */
    const lang = config.settings.langs[currentLanguage];
    const items = {};
    $('div[data-mlm-sub-category="misc-descriptive-popups"] [data-mlm-translation]').each(function() {
        const $el = $(this);
        const name = $el.attr('data-mlm-name') ?? '';
        const type = $el.attr('data-mlm-type') ?? '';
        const index = $el.attr('data-mlm-index') ?? '';
        const val = get_dd(lang, type, name, index).translation;
        if (type == 'descriptive-popup-complete') {
            $el.prop('checked', val == '1');
        }
        else {
            $el.val(val);
        }
        items[index] = true;
    });
    for (const index in items) {
        updateDescriptivePopupsTranslationStatus(index, true);
    }
}

/**
 * Updates the translation status icons (red/green); additionally highlight any
 * changed reference values
 * @param {string} popupUid Name of the popup
 * @param {boolean} collapse When true, will collapse the popup when fully translated
 */
function updateDescriptivePopupsTranslationStatus(popupUid, collapse = false) {
    const $item = $('div[data-mlm-descriptivepopup="' + popupUid + '"]');
    if ($item.length != 1) {
        error(`Unable to find Descriptive Popup block "${popupUid}"`);
        return;
    }
    let allTranslated = true;
    const markedTranslated = get_dd(config.settings.langs[currentLanguage], 'descriptive-popup-complete', '', popupUid).translation == '1';
    $item.find('[data-mlm-descriptivepopup-item]').each(function() {
        const $this = $(this);
        const $val = $this.find('[data-mlm-translation]');
        const type = $val.attr('data-mlm-type') ?? '';
        const name = $val.attr('data-mlm-name') ?? '';
        const index = $val.attr('data-mlm-index') ?? '';
        // Reference hash mismatch indicator
        const hash = $val.attr('data-mlm-refhash') ?? null;
        $this.find('[data-mlm-ref-changed]')[
            hasRefHashMismatch(hash, type, name, index) ? 'show' : 'hide'
        ]();
        // Translation status
        const itemTranslated = markedTranslated || isDdTranslated(type, name, index);
        const translatedClass = itemTranslated ? 'badge-success' : 'badge-danger';
        $this.find('[data-mlm-indicator="descriptivepopup-item-translated"]').removeClass('badge-success badge-danger badge-light').addClass(translatedClass);
        allTranslated = allTranslated && itemTranslated;
    })
    // Set overall translation status
    $item.find('[data-mlm-indicator="descriptivepopup-translated"]')
        .removeClass('badge-success badge-danger badge-light')
        .addClass(allTranslated ? 'badge-success' : 'badge-danger');
}
function toggleDescriptivePopup($nested) {
    $nested.parents('[data-mlm-descriptivepopup]').toggleClass('collapsed');
}
function uncollapseAllDescriptivePopups() {
    $('div[data-mlm-descriptivepopup]').removeClass('collapsed');
}
function collapseAllDescriptivePopups() {
    $('div[data-mlm-descriptivepopup]').addClass('collapsed');
}
/**
 * Filters the Descriptive Popups tab to only show items that contain the search string
 * @param {boolean} force
 */
function performDescriptivePopupsSearch(force = false) {
    const $searchInput = $('input[data-mlm-config="descriptivepopups-search"]');
    const needle = ($searchInput.val() ?? '').toString().toLowerCase();
    if (needle != lastDescriptivePopupsSearchTerm || force) {
        $('[data-mlm-descriptivepopup-item]').each(function() {
            const $this = $(this);
            const $popup = $this.parents('div[data-mlm-descriptivepopup]');
            // Does the text content of the entire item contain the search term?
            // Need to add value of input element explicitly
            // Furthermore, add the popup name
            const text = (($popup.attr('[data-mlm-descriptivepopup]') ?? '') + ' ' + $this.find('[data-mlm-translation]').val() + ' ' + $this.text()).toLowerCase();
            if (text.includes(needle)) {
                $this.removeClass('mlm-descriptivepopup-item-search-exclude')
            }
            else {
                $this.addClass('mlm-descriptivepopup-item-search-exclude')
            }
        })
        // Store last search term in order to prevent unnecessary work
        lastDescriptivePopupsSearchTerm = needle
        log('Executed descriptive popups search for "' + needle + '"');
    }
    // Toggle display of the no items indicator
    if ($('[data-mlm-descriptivepopup-item]').not('.mlm-descriptivepopup-item-search-exclude').length) {
        $('.mlm-descriptivepopups-no-items').addClass('hide')
    }
    else {
        $('.mlm-descriptivepopups-no-items').removeClass('hide')
    }
}
var lastDescriptivePopupsSearchTerm = ''


//#endregion

//#region ---- Protected Mail (REDCap Secure Messaging)

function updateProtectedMailSettings() {
    /** @type {Language} */
    const lang = config.settings.langs[currentLanguage]
    // User Interface
    $('div[data-mlm-sub-category="misc-protmail"] [data-mlm-translation]').each(function() {
        const $el = $(this) 
        const name = ''
        const type = $el.attr('data-mlm-type') ?? ''
        const index = ''
        const val = lang.dd.hasOwnProperty(type) ? lang.dd[type][name][index].translation : ''
        $el.val(val)
        updateProtectedMailTranslationStatus()
    })
}

function updateProtectedMailTranslationStatus() {
    $('div[data-mlm-sub-category="misc-protmail"] [data-mlm-protmail-setting]').each(function() {
        const $this = $(this)
        const $val = $this.find('[data-mlm-translation]')
        const type = $val.attr('data-mlm-type') ?? ''
        const name = $val.attr('data-mlm-name') ?? ''
        const index = $val.attr('data-mlm-index') ?? ''
        // Reference hash mismatch indicator
        const hash = $val.attr('data-mlm-refhash') ?? null
        const refHashMismatch = hasRefHashMismatch(hash, type, name, index)
        const refHashFunc = refHashMismatch ? 'show' : 'hide'
        $this.find('[data-mlm-ref-changed]')[refHashFunc]()
        // Translation status
        const itemTranslated = isDdTranslated(type, name, index)
        const translatedClass = itemTranslated ? 'badge-success' : 'badge-danger'
        $this.find('[data-mlm-indicator="protmail-setting-translated"]').removeClass('badge-success badge-danger badge-light').addClass(translatedClass)
    })
}

//#endregion

//#endregion

//#region -- User Interface Tab

function updateUserInterface() {
    if (currentTab != 'ui') return // Nothing to do when User Interface tab is not shown
    $('div[data-mlm-tab="ui"] .show-when-can-override').hide();
    $('.mlm-ai-translate-tool').show();
    /** @type {Language} */
    const lang = config.settings.langs[currentLanguage];
    const uiOverridesAllowed = config.settings['allow-ui-overrides'] || !config.settings['disable-ui-overrides'];
    if (lang.syslang != '' && lang.subscribed && !uiOverridesAllowed) {
        $('div[data-mlm-tab="ui"] .show-when-subscribed').show();
        $('div[data-mlm-tab="ui"] .hide-when-subscribed').hide();
    }
    else {
        if (lang.syslang != '' && lang.subscribed && uiOverridesAllowed) {
            $('div[data-mlm-tab="ui"] .show-when-can-override').show();
            $('.mlm-ai-translate-tool').hide();
        }
        $('div[data-mlm-tab="ui"] .show-when-subscribed').hide();
        $('div[data-mlm-tab="ui"] .hide-when-subscribed').show();
        // User Interface
        $('div[data-mlm-tab="ui"] [data-mlm-translation]').each(function() {
            const $el = $(this) 
            const name = $el.attr('data-mlm-translation') ?? ''
            const meta = config.uiMeta[name]
            let val = meta.type == 'bool' ? false : ''
            let hash = meta.refHash
            if (lang.ui.hasOwnProperty(name)) {
                val = lang.ui[name].translation
                hash = lang.ui[name].refHash ?? meta.refHash
            }
            if ($el.is('input[type=checkbox]')) {
                $el.prop('checked', val)
            }
            else {
                $el.val(val.toString())
                $el.attr('data-mlm-refhash', hash)
            }
            updateUITranslationStatus(name)
        })
        applyHideTranslatedUIItems()
    }
    setUIEnabledState();
    setRtlStatus();
}

/**
 * Updates the translation status icons (red/green, gray/orange)
 * @param {string} name Name of the UI item
 */
function updateUITranslationStatus(name) {
    if (name && name.length) {
        const $item = $('div[data-mlm-ui-translation] textarea[data-mlm-translation="' + name + '"]')
        const translated = $item.val() != ''
        let badgeSet = 'badge-success'
        let badgeNotSet = 'badge-danger'
        // For the references language in the project context, use different badge classes to highhlight overridden values
        if (currentLanguage == config.settings.refLang && config.mode == 'Project') {
            badgeSet = 'badge-warning'
            badgeNotSet = 'badge-secondary'
        }
        const badgeClass = translated ? badgeSet : badgeNotSet
        $item.parent().removeClass('mlm-ui-item-translated').find('[data-mlm-indicator="translated"]').removeClass('badge-success badge-danger badge-secondary badge-light badge-warning').addClass(badgeClass)
        if (translated) {
            $item.parent().addClass('mlm-ui-item-translated')
        }
        else {
            // Make sure the item is not hidden due to the 'Hide translated items' option
            $item.parent().removeClass('mlm-ui-item-hidden')
        }
        // Reference hash mismatch indicator
        const hash = $item.attr('data-mlm-refhash') ?? null
        const refHashMismatch = hasRefHashMismatch(hash, 'ui', name)
        const refHashFunc = refHashMismatch ? 'show' : 'hide'
        $item.parent('div[data-mlm-ui-translation]').find('[data-mlm-ref-changed]')[refHashFunc]()
    }
}

/**
 * Hides or shows translated items based on the state of the 'Hide translated items' checkbox
 */
function applyHideTranslatedUIItems() {
    const hideTranslated = $('input[data-mlm-action="toggle-hide-ui-translated"]').prop('checked')
    // First, show all
    $('.mlm-ui-translations [data-mlm-ui-translation]').removeClass('mlm-ui-item-hidden')
    if (hideTranslated) {
        // Then, if set to hide, hide all that are translated
        $('.mlm-ui-translations .mlm-ui-item-translated').addClass('mlm-ui-item-hidden')
    }
    updateUISubheadings()
}

/**
 * Limits the UI translations to those matching the search term
 * @param {boolean} force 
 */
function performUISearch(force = false) {
    if (currentTab != 'ui') return // Do not do anything unless on UI tab
    const needle = ($('input[data-mlm-config="ui-search"]').val() ?? '').toString().toLowerCase()
    if (needle != lastUISearchTerm || force) {
        $('[data-mlm-ui-translation]').each(function() {
            const $this = $(this)
            // Does the text content of the entire item contain the search term?
            // Need to add value of input element explicitly
            const text = ($this.find('[data-mlm-translation]').val() + ' ' + $this.text()).toLowerCase()
            if (text.includes(needle)) {
                $this.removeClass('mlm-ui-item-search-exclude')
            }
            else {
                $this.addClass('mlm-ui-item-search-exclude')
            }
        })
        // When limiting by search, hide subheadings
        if (needle == '') {
            $('.mlm-sub-category-subheading').removeClass('mlm-ui-item-search-exclude')
        }
        else {
            $('.mlm-sub-category-subheading').addClass('mlm-ui-item-search-exclude')
        }
        // Store last search term in order to prevent unnecessary work
        lastUISearchTerm = needle
        log('Executed search for "' + needle + '"')
    }
    // Toggle display of the no items indicator
    if ($('.mlm-ui-translations [data-mlm-ui-translation].mlm-ui-visible').not('.mlm-ui-item-hidden').not('.mlm-ui-item-search-exclude').length) {
        $('.mlm-ui-no-items').addClass('hide')
    }
    else {
        $('.mlm-ui-no-items').removeClass('hide')
    }
}
var lastUISearchTerm = ''


/**
 * The throttle implementation from underscore.js
 * See https://stackoverflow.com/a/27078401
 * @param {function} func 
 * @param {Number} wait 
 * @param {Object} options 
 * @returns 
 */
function throttle(func, wait, options) {
    let context, args, result;
    let timeout = null;
    let previous = 0;
    if (!options) options = {};
    const later = function() {
        previous = options.leading === false ? 0 : Date.now();
        timeout = null;
        result = func.apply(context, args);
        if (!timeout) context = args = null;
    };
    return function() {
        const now = Date.now();
        if (!previous && options.leading === false) previous = now;
        const remaining = wait - (now - previous);
        context = this;
        args = arguments;
        if (remaining <= 0 || remaining > wait) {
            if (timeout) {
                clearTimeout(timeout);
                timeout = null;
            }
            previous = now;
            result = func.apply(context, args);
            if (!timeout) context = args = null;
        } else if (!timeout && options.trailing !== false) {
            timeout = setTimeout(later, remaining);
        }
        return result;
    };
};

//#endregion

//#region -- Snapshots

function updateCreateSnapshotButton() {
    const $btn = $('button[data-mlm-action="create-snapshot"]');
    $btn.prop('disabled', Object.keys(config.settings.langs).length == 0);
}

/**
 * Renders the snapshots table (and initiates an ajax request, if necessary)
 */
function renderSnapshotsTable() {
    const showDeleted = $('input[data-mlm-action="toggle-show-deleted-snapshots"]').prop('checked')
    const $tbody = $('.mlm-snapshots-table tbody')
    $tbody.children().remove()
    if (config.snapshots === false) {
        $tbody.append(getTemplate('snapshots-loading'))
        // Need to fetch snapshots
        ajax('load-snapshots').then(function(data) {
            config.snapshots = data.snapshots
            renderSnapshotsTable()
        }).catch(function(err) {
            $tbody.children().remove()
            $tbody.append(getTemplate('snapshots-loading-failed'))
        })
    }
    else {
        for (const snapshotId of Object.keys(config.snapshots).sort(function(a, b) {
            // Sort so that snapshots are displayed newest to oldest
            return parseInt(b) - parseInt(a)
        })) {
            const snapshot = config.snapshots[snapshotId]
            if (snapshot.deleted_ts == null || showDeleted) {
                addSnapshotTableRow(snapshot)
            }
        }
        if ($tbody.children().length == 0) {
            const $row = getTemplate('no-snapshots-row')
            injectDisplayValues($row, {
                message: Object.keys(config.snapshots).length == 0 ? $lang.multilang_164 : $lang.multilang_170
            })
            $tbody.append($row)
        }
        else {
            // @ts-ignore
            $tbody.find('[data-bs-toggle="popover"]').popover({ trigger: 'hover', placement: 'right', animation: true })
        }
    }
    setUIEnabledState();
    setRtlStatus();
}

/**
 * Adds a row to the snapshots table
 * @param {SnapshotData} snapshot 
 */
function addSnapshotTableRow(snapshot) {
    const $tbody = $('.mlm-snapshots-table tbody')
    const $row = getTemplate('snapshot-row').attr('data-mlm-snapshot', snapshot.id)
    injectDisplayValues($row, {
        timestamp: snapshot.created_ts,
        user: snapshot.created_by,
    })
    setNestedAttribute($row, 'data-mlm-snapshot', snapshot.id)
    if (snapshot.deleted_ts == null) {
        $row.find('.remove-when-not-deleted').remove()
    }
    else {
        $row.find('.remove-when-deleted').remove()
        $row.find('.remove-when-not-deleted').attr('title', snapshot.deleted_ts).attr('data-content', snapshot.deleted_by)
    }
    $tbody.append($row)
}

/**
 * Toggles display of the snapshots table
 */
function toggleSnapshotsTable() {
    if (config.snapshots === false) {
        renderSnapshotsTable()
    }
    const $table = $('.mlm-snapshots-table')
    const hidden = $table.hasClass('hide')
    const action = hidden ? 'removeClass' : 'addClass'
    $table[action]('hide')
}

/**
 * "Toggles" (i.e. re-renders, if already shown) the snapshots table
 */
function toggleShowDeletedSnapshots() {
    if (config.snapshots) {
        renderSnapshotsTable()
    }
}

/**
 * Creates a new snapshot
 */
function createSnapshot() {
    const $btn = $('button[data-mlm-action="create-snapshot"]')
    $btn.prop('disabled', true)
    $btn.find('.when-enabled').addClass('hide')
    $btn.find('.when-disabled').removeClass('hide')
    ajax('create-snapshot').then(function(data) {
        showToastMLM('#mlm-successToast', $lang.multilang_165) // The snapshot has been created.
        if (config.snapshots !== false) {
            // Only add/display the table if it has been loaded previously
            config.snapshots[data.snapshot.id] = data.snapshot
            renderSnapshotsTable()
        }
    }).catch(function(err) {
        showToastMLM('#mlm-errorToast', $lang.multilang_166) // Failed to create the snapshot. Please check the browser console (F12) for details.
        error(err)
    }).finally(function() {
        $btn.prop('disabled', false)
        $btn.find('.when-enabled').removeClass('hide')
        $btn.find('.when-disabled').addClass('hide')
    })
}

/**
 * Deletes a snapshot
 * @param {string} snapshotId 
 */
function deleteSnapshot(snapshotId) {
    const $btn = $('button[data-mlm-snapshot="' + snapshotId + '"]')
    $btn.prop('disabled', true)
    ajax('delete-snapshot', snapshotId).then(function(data) {
        showToastMLM('#mlm-successToast', $lang.multilang_167)
        config.snapshots[snapshotId] = data.snapshot
        renderSnapshotsTable()
    }).catch(function(err) {
        showToastMLM('#mlm-errorToast', $lang.multilang_168)
        error(err)
        $btn.prop('disabled', false)
    })
}

/**
 * Helper function to convert base64-encoded data into a blob
 * @param {string} b64Data Base64-encoded data
 * @param {string} contentType Optional: content type
 * @param {Number} sliceSize Optional: 
 * @returns {Blob}
 */
function base64toBlob(b64Data, contentType = 'octet/stream', sliceSize = 512) {
    const byteCharacters = atob(b64Data)
    const byteArrays = []
    for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
        const slice = byteCharacters.slice(offset, offset + sliceSize)
        const byteNumbers = new Array(slice.length)
        for (let i = 0; i < slice.length; i++) {
            byteNumbers[i] = slice.charCodeAt(i)
        }
        const byteArray = new Uint8Array(byteNumbers)
        byteArrays.push(byteArray)
    }
    return new Blob(byteArrays, {type: contentType})
}

/**
 * Requests a snapshot from the server (via AJAX) and initiates a download from the browser
 * @param {string} snapshotId 
 */
function downloadSnapshot(snapshotId) {
    const $btn = $('button[data-mlm-snapshot="' + snapshotId + '"]')
    $btn.prop('disabled', true)
    ajax('download-snapshot', snapshotId)
    .then(function(data) {
        const blob = base64toBlob(data.content)
        // @ts-ignore
        saveAs(blob, data.name)
    })
    .catch(function(err) {
        showToastMLM('#mlm-errorToast', $lang.multilang_176)
        error(err)
    })
    .finally(function() {
        $btn.prop('disabled', false)
    })
}

//#endregion

//#region -- Usage Tab

function renderUsageStatsTab() {
    if (config.settings.usageStats == null) {
        log('Loading MLM usage stats ...')
        ajax('get-usage-stats')
        .then(function(response) {
            log('Usage stats loaded:', response.usageStats)
            try {
                // Store
                config.settings.usageStats = response.usageStats
                // Configure table
                $('[data-mlm-tab="usage"] table')['DataTable']({
                    autoWidth: false,
                    responsive: true,
                    stripeClasses: [],
                    data: processUsageStats(response.usageStats, false),
                    order: [[1, 'asc']],
                    columns: [
                        {
                            data: 'projectStatus',
                            className: 'text-center',
                            orderable: false
                        },
                        {
                            title: $lang.home_30,
                            data: 'projectLink'
                        },
                        {
                            title: $lang.home_65,
                            data: 'projectId',
                            className: 'text-end'
                        },
                        {
                            title: $lang.multilang_640,
                            data: 'projectIni',
                            className: 'text-center'
                        },
                        {
                            title: $lang.multilang_67,
                            data: 'mlmLangs',
                            className: 'text-center',
                            type: 'num-html'
                        },
                        {
                            title: $lang.setup_87,
                            data: 'mlmActive',
                            className: 'text-center',
                            type: 'num-html'
                        },
                        {
                            title: $lang.home_33,
                            data: 'mlmStatus',
                            className: 'mlm-usage-stats-status',
                            orderable: false
                        },
                        {
                            visible: false,
                            data: 'additionalSearch'
                        }
                    ],
                    language: {
                        "emptyTable":     $lang.multilang_103,
                        "info":           $lang.datatables_02,
                        "infoEmpty":      $lang.datatables_03,
                        "infoFiltered":   $lang.datatables_04,
                        "lengthMenu":     $lang.datatables_05,
                        "loadingRecords": $lang.data_entry_64,
                        "processing":     "",
                        "search":         $lang.datatables_06,
                        "zeroRecords":    $lang.datatables_07,
                        "paginate": {
                            "first":      $lang.datatables_08,
                            "last":       $lang.datatables_09,
                            "next":       $lang.datatables_10,
                            "previous":   $lang.datatables_11,
                        },
                        "aria": {
                            "sortAscending":  $lang.datatables_12,
                            "sortDescending": $lang.datatables_13
                        }
                    }
                })
                // Hook events
                $('[data-mlm-switch="show-all-projects"]').on('change', function() {
                    const showAllProjects = $('[data-mlm-switch="show-all-projects"]').prop('checked')
                    const table = $('[data-mlm-tab="usage"] table')['DataTable']()
                    table.clear()
                    table.rows.add(processUsageStats(config.settings.usageStats, showAllProjects))
                    table.draw()
                })
                // Show hide items
                $('[data-mlm-visibility="hide-when-usage-loaded"]').addClass('d-none')
                $('[data-mlm-visibility="show-when-usage-loaded"]').removeClass('d-none')
                // Enable popovers after table has been drawn
                $('[data-mlm-tab="usage"] table').on('draw.dt', function() {
                    $('[data-mlm-tab="usage"]').find('[data-bs-toggle="popover"]')['popover']({
                        html: true
                    })
                }).trigger('draw.dt')
            }
            catch (err) {
                error(err)
            }
        })
        .catch(function(err) {
            showToastMLM('#mlm-errorToast', $lang.multilang_637)
            error(err)
        });
    }
}

function processUsageStats(raw, showAllProjects) {
    const data = [];
    for (const entry of raw) {
        if (entry.hasMlm || showAllProjects) {
            data.push({
                projectLink: renderProjectTitle(entry.projectId, entry.projectTitle),
                projectId: renderProjectId(entry.projectId),
                projectStatus: renderProjectStatusIcon(entry.projectStatus),
                projectIni: renderProjectLanguage(entry.projectLanguage),
                mlmLangs: renderLanguagesInfo(entry.projectId, entry.nAllLangs, entry.allLangs),
                mlmActive: renderLanguagesInfo(entry.projectId, entry.nActiveLangs, entry.activeLangs),
                mlmStatus: renderStatusInfo(entry),
                additionalSearch: getAdditionalSearchText(entry)
            });
        }
    }
    log('Processed usage stats:', data);
    return data;
}

function renderProjectTitle(pid, title) {
    return '<a href="' + window['app_path_webroot'] + 'index.php?pid=' + pid + '" target="_blank">' + title + '</a>';
}

function renderProjectId(pid) {
    return '<a class="me-3" target="_blank" href="' + window['app_path_webroot'] + '/ControlCenter/edit_project.php?project=' + pid + '">' + pid + '</a>';
}

function renderProjectStatusIcon(status) {
    switch(status) {
        case 'deleted': 
            return '<span class="fas fa-times" style="color:#C00000;font-size:14px;" aria-hidden="true" data-bs-toggle="tooltip" title="' + $lang.global_106 + '"></span>';
        case 'completed': 
            return '<span class="fa fa-archive fs11" style="color:#C00000;font-size:14px;" aria-hidden="true" data-bs-toggle="tooltip" title="' + $lang.edit_project_207 + '"></span>';
        case 'analysis':
            return '<span class="fas fa-minus-circle" style="color:#A00000;font-size:14px;" aria-hidden="true" data-bs-toggle="tooltip" title="' + $lang.global_159 + '"></span>';
        case 'production':
            return '<span class="far fa-check-square" style="color:#00A000;font-size:14px;" aria-hidden="true" data-bs-toggle="tooltip" title="' + $lang.global_30 + '"></span>';
        default:
            return '<span class="fas fa-wrench" style="color:#444;font-size:14px;" aria-hidden="true" data-bs-toggle="tooltip" title="' + $lang.global_29 + '"></span>';
    }
}

function renderProjectLanguage(lang) {
    if (lang == '') return '';
    return '<span class="fas fa-language mlm-stats-with-popover" data-bs-toggle="popover" data-bs-content="' + lang + '" data-bs-trigger="hover"></span>';
}

function renderLanguagesInfo(pid, n, langs) {
    if (n < 1) return '-';
    let lines = [];
    for (const key in langs) {
        const name = langs[key];
        lines.push('<i>' + key + '</i> <b>' + name + '</b>');
    }
    const html = lines.join('<br>');
    return '<span onclick="window.open(\'' + window['app_path_webroot'] + 'index.php?pid=' + pid + '&route=MultiLanguageController:projectSetup\', \'_blank\').focus();" class="mlm-stats-with-popover" data-bs-toggle="popover" data-bs-content="' + html + '" data-bs-trigger="hover">' + n + '</span>';
}


function renderStatusInfo(item) {
    const debug = item.mlmDebug ? '<i class="fas fa-bug" data-bs-toggle="tooltip" title="' + $lang.multilang_641 + '"></i>' : ''
    const userDisabled = item.mlmDisabled ? '<i class="fas fa-toggle-off" data-bs-toggle="tooltip" title="' + $lang.multilang_642 + '"></i>' : ''
    const adminEnabled = item.mlmAdminEnabled ? '<i class="fas fa-user-check text-success" data-bs-toggle="tooltip" title="' + $lang.multilang_644 + '"></i>' : ''
    const adminDisabled = item.mlmAdminDisabled ? '<i class="fas fa-user-lock text-danger" data-bs-toggle="tooltip" title="' + $lang.multilang_643 + '"></i>' : ''
    return userDisabled + adminEnabled + adminDisabled + debug
}

function getAdditionalSearchText(item) {
    const text = []
    if (item.mlmDebug) text.push($lang.multilang_641)
    if (item.mlmDisabled) text.push($lang.multilang_642)
    if (item.mlmAdminEnabled) text.push($lang.multilang_644)
    if (item.mlmAdminDisabled) text.push($lang.multilang_643)
    text.push(item.projectLanguage)
    return text.join(' ')
}

/**
 * Refreshes the usage stats table
 */
function refreshUsageStats() {
    log('Refreshing usage stats...')
    $('.mlm-usage-controls button').prop('disabled', true);
    ajax('get-usage-stats')
    .then(function(response) {
        config.settings.usageStats = response.usageStats
        const showAllProjects = $('[data-mlm-switch="show-all-projects"]').prop('checked')
        const table = $('[data-mlm-tab="usage"] table')['DataTable']()
        table.clear()
        table.rows.add(processUsageStats(config.settings.usageStats, showAllProjects))
        table.draw()
    })
    .catch(function(err) {
        showToastMLM('#mlm-errorToast', $lang.multilang_637)
        error(err)
    })
    .finally(function() {
        $('.mlm-usage-controls button').prop('disabled', false);
    })
}

/**
 * Exports the usage stats as CSV file
 */
function exportUsageStats() {
    const showAllProjects = $('[data-mlm-switch="show-all-projects"]').prop('checked')
    const lines = []
    const colTitles = ['pid','project_status','project_title','language_ini','n_languages','n_active','user_disabled','admin_enabled','admin_disabled','debug_mode','lang_id','lang_name','lang_active']
    lines.push(colTitles.join(config.csvDelimiter))
    for (const entry of config.settings.usageStats) {
        if (!(entry.hasMlm || showAllProjects)) continue
        const row = [
            entry['projectId'],
            entry['projectStatus'],
            toCsvString(entry['projectTitle']),
            toCsvString(entry['projectLanguage'] == '' ? 'English' : entry['projectLanguage']),
            entry['nAllLangs'],
            entry['nActiveLangs'],
            entry['mlmDisabled'] ? '1' : '0',
            entry['mlmAdminEnabled'] ? '1' : '0',
            entry['mlmAdminDisabled'] ? '1' : '0',
            entry['mlmDebug'] ? '1' : '0',
            '',
            '',
            ''
        ]
        lines.push(row.join(config.csvDelimiter))
        for (const lang in entry.allLangs) {
            const row = [
                entry['projectId'],
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                toCsvString(lang),
                toCsvString(entry.allLangs[lang]),
                entry.activeLangs.hasOwnProperty(lang) ? '1' : '0'
            ]
            lines.push(row.join(config.csvDelimiter))
        }
    }
    const filename = 'MLM Usage Stats - ' + formatDatetime(new Date()) + '.csv'
    const blob = new Blob([
        new Uint8Array([0xEF, 0xBB, 0xBF]),
        lines.join('\r\n')
    ], { type: "text/plain;charset=utf-8" })
    // @ts-ignore
    saveAs(blob, filename)
    log('Exported usage stats as "' + filename + '"')
}

//#endregion

//#region -- Dirty Management & Saving

/**
 * Event handler for changes in languages and settings
 * @param {JQuery.TriggeredEvent} event
 */
function storeConfig(event) {
    const $el = $(event.target)
    let name = $el.attr('data-mlm-config')
    if (!name) return

    let log_name = name
    let val = $el.val() ?? ''
    if ($el.is('input[type=checkbox]')) {
        val = $el.prop('checked')
    }
    else if (name == 'refLang' || name == 'fallbackLang' || name == 'initialLang') {
        val = $el.parents('tr[data-mlm-language]').attr('data-mlm-language') ?? ''
    }
    // Determine where to store the value, and store it
    if (Object.keys(config.settings).includes(name)) {
        // A general setting
        config.settings[name] = val;
        checkDirty();
    }
    else {
        // A language setting
        const langKey = $el.parents('tr[data-mlm-language]').attr('data-mlm-language')
        if (langKey) {
            config.settings.langs[langKey][name] = val
            log_name = name + ' [' + langKey + ']'
            if (name == 'active') {
                // Enable or disable (and clear) initial
                const $initialRadio = $('tr[data-mlm-language="' + langKey + '"] input[data-mlm-config="initialLang"]')
                const isChecked = $initialRadio.prop('checked')
                $initialRadio.prop('disabled', !val)
                if (isChecked && !val) {
                    $initialRadio.prop('checked', false)
                    // Set a new initial
                    config.settings.initialLang = ''
                    for (const thisLangKey of Object.keys(config.settings.langs)) {
                        const lang = config.settings.langs[thisLangKey]
                        if (lang.active) {
                            config.settings.initialLang = thisLangKey
                            $('tr[data-mlm-language="' + thisLangKey + '"] input[data-mlm-config="initialLang"]').prop('checked', true)
                            break
                        }
                    }
                }
            }
            checkDirty(langKey);
            checkDirty();
        } 
    }
    // Additional actions to be performed
    if (name == 'refLang') {
        updateSwitcher(true)
        updateTranslations()
    }
    else if (name == 'designatedField') {
        updateDesignatedFieldWarning(val.toString())
    }
    log('Updated data for: "' + log_name + '" (' + val + ')')
}

/**
 * Event handler for changes on the Forms/Surveys tab (also handles the field jumplist)
 * @param {JQuery.TriggeredEvent} event 
 */
function storeTranslations(event) {
    const $el = $(event.target)
    // Is this an action? If so, do nothing
    if ($el.attr('data-mlm-action')) return 

    const name = $el.attr('data-mlm-name') ?? $el.attr('data-mlm-translation') ?? ''
    const type = $el.attr('data-mlm-type') ?? ''
    if (type == 'search-tool') return;
    const index = $el.attr('data-mlm-index') ?? ''
    const val = $el.is('input[type=checkbox]') ? ($el.prop('checked') ? '1' : '') : ($el.val() ?? '')

    switch (type) {
        case 'fields-jumplist':
            // Handle jumplist - not storing anything
            if (val) {
                const $target = $('[data-mlm-field="' + val + '"]')
                const scrollY = ($target.get(0)?.offsetTop ?? 0) + 50;
                log('Jumping to field:', val, scrollY);
                window.scrollTo(0, scrollY);

                // $target[0].scrollIntoView()
                // window.scrollBy(0, -15)
                // Fade green to highlight place
                $target.addClass('greenhighlight')
                setTimeout(() => {
                    $target.removeClass('greenhighlight')
                }, 1000);
            }
            break

        case 'field-excluded':
            if (val) {
                config.settings.excludedFields[name] = true
            }
            else {
                delete config.settings.excludedFields[name]
            }
            log('Updated config "' + type + '" (' + name + ') to: "' + val + '"')
            break

        case 'alert-excluded':
            if (val) {
                config.settings.excludedAlerts[name] = true
            }
            else {
                delete config.settings.excludedAlerts[name]
            }
            log('Updated config "' + type + '" (' + name + ') to: "' + val + '"')
            break

        case 'alert-source':
            config.settings.alertSources[name] = val.toString()
            log('Updated config "' + type + '" (' + name + ') to: "' + val + '"')
            break

        case 'asi-source':
            config.settings.asiSources[name] = val.toString()
            log('Updated config "' + type + '" (' + name + ') to: "' + val + '"')
            break

        case 'setting-excluded':
            const setting = $el.parents('[data-mlm-setting]').attr('data-mlm-setting')
            const formName = $el.parents('[data-mlm-form]').attr('data-mlm-form')
            if (val) {
                excludeSurveySetting(formName, setting)
            }
            else {
                includeSurveySetting(formName, setting)
            }
            log('Updated config "' + type + '" (' + formName + ': ' + setting + ') to: "' + val + '"')
            break

        case 'recaptcha-lang':
            config.settings.langs[currentLanguage]["recaptcha-lang"] = '' + val;
            log('Upated config "' + type + '" [' + currentLanguage +'] to: "' + val + '"');
            break;

        default:
            if (type.length) {
                // Store language settings
                set_dd(config.settings.langs[currentLanguage], type, name, index, val)
                log('Updated "' + type + '" (' + name + ') [ ' + currentLanguage + ' ] to: "' + val + '"', config.settings.langs[currentLanguage])
                // In case a field-complete checkbox was checked or unchecked, re-apply hidden status
                if (type == 'field-complete') {
                    applyHideTranslatedFieldItems(name)
                }
            }
            else {
                warn('Undefined trigger:', $el)
            }
            break
    }
    removeChangedItem(currentLanguage, type, name, index, true)
    if (type.length) {
        checkDirty(currentLanguage, type)
    }
}

/**
 * Event handler for changes in UI translations
 * @param {JQuery.TriggeredEvent} event
 */
 function storeUITranslation(event) {
    const $el = $(event.target);
    const name = $el.attr('data-mlm-translation') ?? '';
    // Convert checkbox 'true' to '1' (for dirty checking to work properly)
    const val = $el.is('input[type=checkbox]') ? ($el.prop('checked') ? '1' : false) : ($el.val() ?? '');

    // Store value if it exists
    if (config.uiMeta.hasOwnProperty(name)) {
        if (config.uiMeta[name].type == 'bool' && val == false) {
            // Do not store false bools - delete instead (otherwise dirty tracking will not work with sparse data)
            delete config.settings.langs[currentLanguage]['ui'][name];
        }
        else {
            const hash = config.uiMeta[name].refHash;
            config.settings.langs[currentLanguage]['ui'][name] = {
                translation: val,
                refHash: hash,
            }
            $el.attr('data-mlm-refhash', hash);
        }
        updateUITranslationStatus(name);
        removeChangedItem(currentLanguage, 'ui', name, '', true);
        checkDirty(currentLanguage, 'ui');
        log('Updated translation for: "' + name + '" in language "' + currentLanguage + '"');
    }
    else {
        error('Unknown translation item: "' + name + '"');
    }
}

/**
 * Update the hash of a translation element
 * @param {JQuery<HTMLElement>} $el 
 */
function acceptRefChange($el) {
    const type = $el.attr('data-mlm-type') ?? '';
    const name = $el.attr('data-mlm-name') ?? $el.attr('data-mlm-translation') ?? '';
    const index = $el.attr('data-mlm-index') ?? '';
    const refHash = getRefHash(type, name, index);
    $el.attr('data-mlm-refhash', refHash);
    if (type == 'ui') {
        const ui = config.settings.langs[currentLanguage].ui;
        ui[name].refHash = refHash;
        updateUITranslationStatus(name);
    }
    else {
        const dd = config.settings.langs[currentLanguage].dd;
        dd[type][name][index].refHash = refHash;
        if (type.startsWith('survey-')) {
            updateSurveySettingsTranslationStatus(type);
        }
        else if (type.startsWith('task-')) {
            updateTaskTranslationStatus(type, name);
        }
        else {
            updateDdTranslationStatus(name);
        }
    }
    removeChangedItem(currentLanguage, type, name, index, true);
    checkDirty(currentLanguage, type);
}


/**
 * Updates the dirty state
 * @param {boolean} state 
 */
function setDirty(state) {
    // Act only when there is a change
    if (dirty !== state && config.settings.status != 'prod') {
        dirty = state
        if (state) {
            // Indicate that there are unsaved changes
            $('[data-mlm-action="save-changes"]').removeClass('btn-light disabled').addClass('btn-warning').removeAttr('disabled')
            $('.mlm-setup-container').addClass('dirty')
        }
        else {
            $('[data-mlm-action="save-changes"]').removeClass('btn-warning').addClass('btn-light disabled').attr('disabled', 'disabled')
            $('.mlm-setup-container').removeClass('dirty')
        }
    }
}

/**
 * Checks the state and sets the dirty bit (and also updates the hash cache)
 */
function checkDirty(langId, type) {
    // When type ends with "-excluded", set langId to '' in order to force the non-language-specific hash cache to be updated.
    if (typeof type == 'string' && type.slice(-9) ==='-excluded') {
        langId = '';
    }
    if (langId) {
        if (type == 'full' || !hashCache.hasOwnProperty('lang_' + langId + '__main')) {
            addLangToHashCache(langId);
        }
        else if (type == 'ui') {
            // @ts-ignore
            hashCache['lang_' + langId + '__ui'] = objectHash(config.settings.langs[langId].ui);
        }
        else if (type && hashCacheDdKeys.indexOf(type) == -1) {
            // @ts-ignore
            hashCache['lang_' + langId + '__dd'] = objectHash(config.settings.langs[langId].dd, {
                excludeKeys: function(key) {
                    return hashCacheDdKeys.indexOf(key) == -1;
                }
            });
        }
        else if (type && (hashCacheDdKeys.indexOf(type) != -1 || config.settings.langs[langId].dd.hasOwnProperty(type))) {
            // @ts-ignore
            hashCache['lang_' + langId + '__dd_' + type] = objectHash(config.settings.langs[langId].dd[type] ?? {});
        }
        else {
            // @ts-ignore
            hashCache['lang_' + langId + '__main'] = objectHash(config.settings.langs[langId], { 
                excludeKeys: function(key) {
                    return key == 'ui' || key == 'dd';
                }
            });
        }
    }
    else {
        // @ts-ignore
        hashCache['main'] = objectHash(config.settings, {
            excludeKeys: function(key) {
                return key == 'langs';
            }
        });
    }

    // @ts-ignore
    const hash = objectHash(hashCache);
    if (hash == initialHash) {
        setDirty(false);
    }
    else {
        setDirty(true);
        log('Current hash ("' + hash + '") differs from initial hash ("' + initialHash + '").');
    }
}

/**
 * Deletes a language from the hash cache
 * @param {string} langId 
 */
function removeLangFromHashCache(langId) {
    delete hashCache['lang_' + langId + '__main']
    delete hashCache['lang_' + langId + '__ui']
    if (config.mode == 'Project') {
        delete hashCache['lang_' + langId + '__dd']
    }
}

/** Hashes a language and adds it to the hash cache
 * @param {string} langId 
 */
function addLangToHashCache(langId) {
    // @ts-ignore
    hashCache['lang_' + langId + '__main'] = objectHash(config.settings.langs[langId], { 
        excludeKeys: function(key) {
            return key == 'ui' || key == 'dd';
        }
    });
    // @ts-ignore
    hashCache['lang_' + langId + '__ui'] = objectHash(config.settings.langs[langId].ui);
    if (config.mode == 'Project') {
        // @ts-ignore
        hashCache['lang_' + langId + '__dd'] = objectHash(config.settings.langs[langId].dd, {
            excludeKeys: function(key) {
                return hashCacheDdKeys.indexOf(key) == -1;
            }
        });
        for (const ddType of hashCacheDdKeys) {
            if (config.settings.langs[langId].dd.hasOwnProperty(ddType)) {
                // @ts-ignore
                hashCache['lang_' + langId + '__dd_' + ddType] = objectHash(config.settings.langs[langId].dd[ddType]);
            }
        }
    }
}

/**
 * Calculates the initial hash (cache)
 * @returns {string}
 */
function calcInitialHash() {
    // Check if the hash cache has already been calculated
    if (hashCache.hasOwnProperty('main')) {
        // @ts-ignore
        return objectHash(hashCache);
    }
    // Calculate all hashes
    // @ts-ignore
    hashCache['main'] = objectHash(config.settings, {
        excludeKeys: function(key) {
            return key == 'langs';
        }
    });
    for (const langId in config.settings.langs) {
        addLangToHashCache(langId);
    }
    // @ts-ignore
    return objectHash(hashCache);
}

const hashCache = {};


/** Indicates whether a saving operation is currently ongoing */
var saving = false

/**
 * Saves the changes.
 */
function saveChanges() {
    if (!saving) {
        saving = true;
        log('Saving settings ...', config.settings);
        $('[data-mlm-action="save-changes"]').addClass('disabled').prop('disabled', true)
            .find('i.fas').removeClass('fa-save').addClass('fa-spinner').addClass('fa-spin');
        ajax('save', config.settings)
        .then(function(data) {
            delete hashCache['main'];
            initialHash = calcInitialHash();
            alertSaved(true);
            updateMetadataHashChangedAlert(data.hash);
            config.stats = calcStats();
            renderLanguagesTable();
            updateCreateSnapshotButton();
            updateSourceDataChangedReport(false);
        })
        .catch(function(err) {
            alertSaved(false);
            console.error('Failed to save data!', err);
        })
        .finally(function() {
            $('[data-mlm-action="save-changes"]').removeClass('disabled').prop('disabled', false)
                .find('i.fas').addClass('fa-save').removeClass('fa-spinner').removeClass('fa-spin');
            saving = false;
            checkDirty();
            updateSystemOfforProjectDisabledIndicator();
        });
    }
}

/**
 * Indicates that all changes have been saved
 * @param {boolean} success 
 */
function alertSaved(success) {
    if (success) {
        showToastMLM('#mlm-successToast', $lang.multilang_66)
    }
    else {
        showToastMLM('#mlm-errorToast', $lang.multilang_151)
    }
}

/**
 * Performs a server request to obtain an updated metadata hash
 */
function checkMetadataHash() {
    ajax('get-metadata-hash', null).then(function(data) {
        updateMetadataHashChangedAlert(data.hash)
    })
}

/**
 * Checks the supplied hash against the metadata hash in settings and shows an alert in case of a mismatch
 * @param {string} hash 
 */
function updateMetadataHashChangedAlert(hash) {
    if (config.settings.projMetaHash != hash) {
        $('.mlm-hash-mismatch-warning').removeClass('hide')
        warn('Project metadata hash mismatch detected!', 'Old:', config.settings.projMetaHash, 'New:', hash)
    }
}

//#endregion

//#region -- Changed Source Data Report

/**
 * Adds an item to the list of changed items
 * @param {ChangedItemInfo} item 
 */
function addChangedItem(item) {
    if (!itemsChangedSinceTranslated.hasOwnProperty(item.langKey)) {
        itemsChangedSinceTranslated[item.langKey] = {}
    }
    if (!itemsChangedSinceTranslated[item.langKey].hasOwnProperty(item.type)) {
        itemsChangedSinceTranslated[item.langKey][item.type] = {}
    }
    if (!itemsChangedSinceTranslated[item.langKey][item.type].hasOwnProperty(item.name)) {
        itemsChangedSinceTranslated[item.langKey][item.type][item.name] = {}
    }
    itemsChangedSinceTranslated[item.langKey][item.type][item.name][item.index] = item
}


function removeChangedItem(langKey, type, name, index, realtimeUpdates) {
    // Is the item defined?
    if (typeof itemsChangedSinceTranslated[langKey] == 'undefined') return
    if (typeof itemsChangedSinceTranslated[langKey][type] == 'undefined') return
    if (typeof itemsChangedSinceTranslated[langKey][type][name] == 'undefined') return
    if (typeof itemsChangedSinceTranslated[langKey][type][name][index] == 'undefined') return
    // Delete it
    delete itemsChangedSinceTranslated[langKey][type][name][index]
    if (Object.keys(itemsChangedSinceTranslated[langKey][type][name]).length == 0) {
        delete itemsChangedSinceTranslated[langKey][type][name]
        if (Object.keys(itemsChangedSinceTranslated[langKey][type]).length == 0) {
            delete itemsChangedSinceTranslated[langKey][type]
            if (Object.keys(itemsChangedSinceTranslated[langKey]).length == 0) {
                delete itemsChangedSinceTranslated[langKey]
                if (realtimeUpdates) $('#mlm-dcr-modal tr.mlm-lang-title[data-mlm-lang="' + langKey + '"]').remove()
            }
        }
    }
    if (realtimeUpdates) {
        $('#mlm-dcr-modal tr[data-mlm-lang="' + langKey + '"][data-mlm-type="' + type + '"][data-mlm-name="' + name + '"][data-mlm-index="' + index + '"]').remove()
        // Are there any items left? If not, close the modal
        if (Object.keys(itemsChangedSinceTranslated).length == 0) {
            dcr_modal.hide()
            $('div.mlm-items-hash-changed-warning').addClass('hide')
        }
        else {
            updateSourceDataChangedReport(false)
        }
    }
}

/**
 * Generates a data structure with changed items and displays info if there are any changed items. 
 * @param {Boolean} showDialog 
 */
function updateSourceDataChangedReport(showDialog = true) {
    itemsChangedSinceTranslated = {};
    let n = 0;
    for (const langKey in config.settings.langs) {
        const lang = config.settings.langs[langKey];
        // UI items
        if (lang.hasOwnProperty('ui')) {
            for (const uiId in lang.ui) {
                const uiItem = lang.ui[uiId];
                const uiMeta = config.uiMeta[uiId];
				// Fix for refHash length-change
				if (uiMeta && uiMeta.type == 'string') {
					if (!uiItem.hasOwnProperty('refHash') || uiItem.refHash == null) uiItem.refHash = uiMeta.refHash;
					if (uiItem.refHash.length == 6 && uiItem.refHash == uiMeta.refHash.substring(0, 6)) {
						uiItem.refHash = uiMeta.refHash
					}
				}
                if (uiMeta && uiMeta.type == 'string' && uiItem.refHash != uiMeta.refHash) {
                    addChangedItem({
                        langKey: langKey,
                        name: uiId,
                        type: 'ui',
                        index: '',
                        translation: uiItem.translation,
                        default: uiMeta.default.toString(),
                        prompt: uiMeta.prompt,
                    });
                    n++;
                }
            }
        }
        // Project items
        if (lang.hasOwnProperty('dd')) {
            for (const type in lang.dd) {
                for (const name in lang.dd[type]) {
                    for (const index in lang.dd[type][name]) {
                        const item = lang.dd[type][name][index];
                        const refHash = getRefHash(type, name, index);
						// Fix for refHash length-change
						if (typeof refHash == 'string' && typeof item['refHash'] == 'string' && item.refHash.length == 6 && item.refHash == refHash.substring(0, 6)) {
							item.refHash = refHash;
						}
                        if (item.refHash != refHash) {
                            let prompt = getRefPrompt(type, name, index);
                            if (type == 'mdc-label') {
                                prompt = config.projMeta.fieldTypes.mdc.label;
                            }
                            else if (type == 'pdf-pdf_custom_header_text') {
                                prompt = config.projMeta.pdfCustomizations[''][type].prompt;
                            }
                            else if (type == 'protmail-protected_email_mode_custom_text') {
                                prompt = config.projMeta.protectedMail[''][type].prompt;
                            }
                            addChangedItem({
                                langKey: langKey,
                                name: name,
                                type: type,
                                index: index,
                                translation: item.translation,
                                default: getRefValue(type, name, index),
                                prompt: prompt,
                            });
                            n++;
                        }
                    }
                }
            }
        }
    }
    log('Updated source data changed report. ' + n + ' changed item(s).', itemsChangedSinceTranslated);
    // Show the result in the UI
    const $alert = $('div.mlm-items-hash-changed-warning');
    if (n > 0) {
        //@ts-ignore base.js
        $alert.find('[data-mlm-action="review-changed-hash-items"]').text(interpolateString($lang.multilang_565, [n]));
        $alert.removeClass('hide');
        if (showDialog) {
            showChangedHashItems();
        } 
    }
    else {
        $alert.addClass('hide');
    }
}

/**
 * Helper function to safely populate a nested object of type/name/index triplets for 
 * single-form export operations
 * @param {object} items The object to be modified
 * @param {string} type 
 * @param {string} name 
 * @param {string} index 
 */
function addSingleFormExportItem(items, type, name, index) {
    if (!items.hasOwnProperty(type)) {
        items[type] = {};
    }
    if (!items[type].hasOwnProperty(name)) {
        items[type][name] = {};
    }
    items[type][name][index] = true;
}

/**
 * Assembles a type/name/index data structure containing all fields/survey settings/asi for the given form
 * @param {string} exportForm Instrument name
 */
function getSingleFormExportItems(exportForm) {
    // Instrument-related project items
    const items = {};
    // Fields
    for (const fieldName in config.projMeta.fields) {
        const field = config.projMeta.fields[fieldName];
        if (field.formName == exportForm) {
            for (const type in field) {
                switch (type) {
                    case 'field-header':
                    case 'field-label':
                    case 'field-note':
                    case 'field-video_url':
                        if (field.hasOwnProperty(type) && 
                            field[type] != null) {
                            addSingleFormExportItem(items, type, fieldName, '');
                        }
                        break;
                    case 'field-enum':
                    case 'field-actiontag':
                        if (field.hasOwnProperty(type) && field[type] != null) {
                            for (const index of Object.keys(field[type])) {
                                addSingleFormExportItem(items, type, fieldName, index);
                            }
                        }
                        break;
                }
            }
        }
    }
    // Matrix groups
    for (const matrixName in config.projMeta.matrixGroups) {
        const matrix = config.projMeta.matrixGroups[matrixName];
        if (matrix.form == exportForm) {
            for (const type in matrix) {
                switch (type) {
                    case 'matrix-header':
                        if (matrix.hasOwnProperty(type) && 
                            typeof matrix[type] == 'object') {
                            addSingleFormExportItem(items, type, matrixName, '');
                        }
                        break;
                    case 'matrix-enum':
                        if (matrix.hasOwnProperty(type) && typeof matrix[type] == 'object') {
                            for (const index in matrix[type]) {
                                addSingleFormExportItem(items, type, matrixName, index);
                            }
                        }
                        break;
                }
            }
        }
    }
    // Survey settings
    if (config.projMeta.surveys.hasOwnProperty(exportForm) && typeof config.projMeta.surveys[exportForm] == 'object') {
        const ss = config.projMeta.surveys[exportForm];
        for (const type in ss) {
            if (type.startsWith('survey-') && ss[type] != null) {
                addSingleFormExportItem(items, type, exportForm, '');
            }
        }
    }
    // ASIs
    if (config.projMeta.hasOwnProperty('asis') && config.projMeta.asis != null) {
        for (const asiId in config.projMeta.asis) {
            const asi = config.projMeta.asis[asiId];
            if (asi.form == exportForm) {
                for (const type in asi) {
                    if (type.startsWith('asi-')) {
                        addSingleFormExportItem(items, type, asiId, '');
                    }
                }
            }
        }
    }
    // MyCap Task Items
    if (config.projMeta.myCapEnabled && config.projMeta.forms[exportForm].myCapTaskItems != null) {
        for (const type in config.projMeta.forms[exportForm].myCapTaskItems) {
            addSingleFormExportItem(items, 'task-' + type, config.projMeta.forms[exportForm].myCapTaskId.toString(), '');
        }
    }
    return items;;
}

/**
 * Exports items related to a single instrument for a language
 * @param {JQuery<HTMLElement>} $row 
 */
 function exportSingleFormItems($row) {
    const langKey = $row.attr('data-mlm-language') ?? '';
    const form = $row.attr('data-mlm-form') ?? '';
    log('Exporting items of form [ ' + form + ' ] for [ ' + langKey + ' ]');
    exportModal.show(langKey, 'single-form', form);
}


/**
 * Populates and shows the report of changed items modal.
 */
function showChangedHashItems(forceShow = false) {

    const $dcr = $('#mlm-dcr-modal');
    dcr_modal = dcr_modal || new bootstrap.Modal($dcr[0], { backdrop: 'static' });
    const $tbody = $dcr.find('tbody');
    $tbody.children().remove();
    for (const langKey in itemsChangedSinceTranslated) {
        // Language display name
        const $tr = getTemplate('dcr-row-title');
        $tr.attr('data-mlm-lang', langKey);
        injectDisplayValues($tr, { lang: config.settings.langs[langKey].display});
        $tbody.append($tr);
        // Items
        for (const type in itemsChangedSinceTranslated[langKey]) {
            for (const name in itemsChangedSinceTranslated[langKey][type]) {
                for (const index in itemsChangedSinceTranslated[langKey][type][name]) {
                    const item = itemsChangedSinceTranslated[langKey][type][name][index];
                    const $item = getTemplate('dcr-row-item');
                    const defaultVal = textOnly(item.default);
                    const translation = textOnly(item.translation);
                    const html = (defaultVal != item.default || translation != item.translation) ? '<i class="fas fa-exclamation-circle text-warning" data-bs-toggle="tooltip" title="' + $lang.multilang_572 + '"></i> ' : '';
                    $item.find('[data-mlm-display="default"]').html(html + defaultVal);
                    $item.find('[data-mlm-display="translation"]').html(html + translation);
                    $item.find('[data-mlm-prompt]').html(item.prompt ?? '');
                    $item.attr('data-mlm-lang', langKey);
                    $item.attr('data-mlm-type', type);
                    $item.attr('data-mlm-name', name);
                    $item.attr('data-mlm-index', index);
                    if (type == 'alert-sendgrid_template_data') {
                        $item.find('[data-mlm-prompt]').html([item.prompt, index].join(' '));
                        if (item.default == '') {
                            $item.find('[data-mlm-prompt]').html([$lang.multilang_599, index].join(' '));
                            $item.find('[data-mlm-display="default"]').html('<i class="fas fa-exclamation-circle" data-bs-toggle="tooltip" title="' + $lang.multilang_600 + '"></i> <b>' + $lang.multilang_600 + '</b>');
                            $item.find('[data-mlm-actions]').html('<a href="javascript:;" class="text-primary" data-mlm-action="accept-changed-item">' + $lang.multilang_570 + '</a>');
                        }
                    }
                    $tbody.append($item);
                }
            }
        }
    }
    // When in production, but not in draft mode, remove all action links
    if (config.settings.status == 'prod') {
        $dcr.find('button[data-mlm-action]').remove();
        $dcr.find('td[data-mlm-actions]').html('');
        // Ensure dismiss button is enabled
        $dcr.find('button[data-dismiss]').prop('disabled', false);
    }
    else {
        // Go to Languages tab (so we don't have to worry about in-page updates)
        activateTab('languages');
        forceShow = true;
    }
    if (forceShow) {
        dcr_modal.show();
    }
}

/**
 * Exports changed items for a language
 * @param {JQuery<HTMLElement>} $row 
 */
function exportChangedItems($row) {
    const langKey = $row.attr('data-mlm-lang');
    if (langKey) {
        log('Exporting changed items for [ ' + langKey + ' ]');
        dcr_modal.hide();
        exportModal.show(langKey, 'changes', '').then(function() {
            dcr_modal.show();
        });
    }
}

/**
 * Navigates to an item for editing
 * @param {JQuery<HTMLElement>} $row 
 */
function editChangedItem($row) {
    const langKey = $row.attr('data-mlm-lang');
    if (langKey) {
        const type = $row.attr('data-mlm-type') ?? '';
        const name = $row.attr('data-mlm-name') ?? '';
        const index = $row.attr('data-mlm-index') ?? '';
        log("Edit changed item", langKey, type, name, index);
        // Hide the modal
        dcr_modal.hide();
        // Navigate to the item
        switchLanguage(langKey);
        if (type == 'ui') {
            activateTab('ui');
            $('input[data-mlm-config="ui-search"]').val(textOnly(getRefValue(type, name, index)));
            performUISearch();
            activateCategory('ui-all');
            $('[data-mlm-translation="' + name + '"][data-mlm-type="ui"]').trigger('focus');
        }
        else if (type.startsWith('field-')) {
            activateTab('forms');
            currentFormsTabForm = config.projMeta.fields[name].formName;
            setFormsTabMode('fields');
            $('[data-mlm-translation="' + name + '"][data-mlm-type="' + type + '"]').trigger('focus').parents('.mlm-field-block')[0].scrollIntoView();
        }
        else if (type.startsWith('task-')) {
            activateTab('forms');
            currentFormsTabForm = config.projMeta.myCap.taskToForm[name];
            setFormsTabMode('fields');
            $('[data-mlm-translation="' + name + '"][data-mlm-type="' + type + '"]').trigger('focus').parents('.mlm-field-block')[0].scrollIntoView();
        }
        else if (type.startsWith('survey-')) {
            activateTab('forms');
            currentFormsTabForm = name;
            setFormsTabMode('survey');
            $('[data-mlm-type="' + type + '"]').trigger('focus').parents('[data-mlm-survey-setting]')[0].scrollIntoView();
        }
        else if (type.startsWith('asi-')) {
            activateTab('forms');
            currentFormsTabForm = name;
            setFormsTabMode('asi');
            $('[data-mlm-type="' + type + '"]').trigger('focus').parents('[data-mlm-asi]')[0].scrollIntoView();
        }
        else if (type.startsWith('alert-')) {
            activateTab('alerts');
            $('input[data-mlm-config="alerts-search"]').val(textOnly(getRefValue(type, name, index)));
            performAlertsSearch(true);
            $('[data-mlm-type="' + type + '"][data-mlm-name="' + name + '"]').trigger('focus');
        }
        else if (type.startsWith('sq-')) {
            activateTab('forms');
            setFormsTabMode('table');
            if (type == 'sq-survey_queue_custom_text') {
                $('button[data-mlm-action="translate-surveyqueue"]').trigger('click');
            }
            else if (type == 'sq-survey_auth_custom_message') {
                $('button[data-mlm-action="translate-surveylogin"]').trigger('click');
            }
        }
        else if (type == 'mdc-label') {
            activateTab('misc');
            activateCategory('misc-mdc');
            $('[data-mlm-type="mdc-label"][data-mlm-name="' + name + '"]').trigger('focus').parents("tr")[0].scrollIntoView();
        }
        else if (type.startsWith('pdf-')) {
            activateTab('misc');
            activateCategory('misc-pdf');
            $('[data-mlm-type="' + type + '"]').trigger('focus').parents(".mlm-field-block")[0].scrollIntoView();
        }
        else if (type.startsWith('protmail-')) {
            activateTab('misc');
            activateCategory('misc-protmail');
            $('[data-mlm-type="' + type + '"]').trigger('focus').parents(".mlm-field-block")[0].scrollIntoView();
        }
    }
}

/**
 * Accepts the translation of an item and removes it from the list/table
 * @param {JQuery<HTMLElement>} $row 
 */
function acceptChangedItem($row) {
    const langKey = $row.attr('data-mlm-lang');
    if (langKey) {
        const type = $row.attr('data-mlm-type') ?? '';
        const name = $row.attr('data-mlm-name') ?? '';
        const index = $row.attr('data-mlm-index') ?? '';
        updateRefHash(langKey, type, name, index);
        removeChangedItem(langKey, type, name, index, true);
        checkDirty(langKey, type);
        log("Accepted changed item", langKey, type, name, index);
    }
}

/**
 * Updates an item with the ref hash
 * @param {string} langKey 
 * @param {string} type 
 * @param {string} name 
 * @param {string} index 
 */
function updateRefHash(langKey, type, name, index) {
    const lang = config.settings.langs[langKey]
    const hash = getRefHash(type, name, index)
    if (type == 'ui') {
        lang.ui[name].refHash = hash
    }
    else {
        lang.dd[type][name][index].refHash = hash
    }
}

/**
 * Accepts the translations of all changed items and dismisses the modal
 */
function acceptAllChangedItems() {
    dcr_modal.hide();
    for (const langKey of Object.keys(itemsChangedSinceTranslated)) {
        const langItems = itemsChangedSinceTranslated[langKey];
        for (const type of Object.keys(langItems)) {
            const typeItems = langItems[type];
            for (const name of Object.keys(typeItems)) {
                const nameItems = typeItems[name];
                for (const index of Object.keys(nameItems)) {
                    updateRefHash(langKey, type, name, index);
                    removeChangedItem(langKey, type, name, index, false);
                }
            }
        }
        checkDirty(langKey, 'full');
    }
    checkDirty();
    updateSourceDataChangedReport(false);
    log('Accepted all changed items');
}

//#endregion

//#region -- Misc. Usability Enhancements

/**
 * Shows a message in a toast
 * @param {string} selector 
 * @param {string} msg 
 */
function showToastMLM(selector, msg) {
    const $toast = $(selector)
    $toast.find('[data-content=toast]').html(msg)
    const toast = bootstrap.Toast.getOrCreateInstance($toast[0])
    toast.show()
}

/**
 * Copies translation defaults to the clipboard after clicking on 'Default value:' label
 * @param {JQuery.TriggeredEvent} event
 */
 function copyDefaultValue(event) {
    var $source = $(event.currentTarget).siblings('.mlm-translation-default-value')
    var val = $source.text()
    // Create a temp textarea for the copy process
    var el = document.createElement('textarea')
    el.value = val
    el.setAttribute('readonly', '')
    el.style.position = 'absolute'
    el.style.left = '-9999px'
    // Copy to clipboard
    document.body.appendChild(el)
    el.select()
    if (document.execCommand('copy')) {
        $source.addClass('copied')
        setTimeout(function() { $source.removeClass('copied') }, 200)
    }
    document.body.removeChild(el)
}

//#endregion

//#region -- Ajax

/**
 * Sends an ajax request to the server
 * @param {string} action
 * @param {object} payload
 * @returns {Promise}
 */
function ajax(action, payload = null) {
    return new Promise(function(resolve, reject) {
        $.ajax({
            method: 'POST', 
            url: config.ajax.endpoint,
            data: {
                verification: config.ajax.verification,
                action: action, 
                payload: JSON.stringify(payload),
                redcap_csrf_token: config.ajax.csrfToken
            },
            dataType: "json",
            success: function(response) {
                if (response['success']) {
                    log('Successful server request:', action, '- Payload:', payload, 'Response:', response)
                    config.ajax.verification = response['verification']
                    resolve(response)
                }
                else {
                    warn('Unsuccessful server request:', action, '- Payload:', payload, 'Response:', response)
                    reject(response.error)
                }
            },
            error: function(jqXHR, err) {
                error('Ajax error:', action, '- Payload:', payload, 'Error:', err, 'jqXHR:', jqXHR)
                reject(err)
            }
        })
    })
}

//#endregion

//#region -- Helpers

/**
 * Initializes a TinyMCE rich text editor (RTE)
 * @param {object} modal The Bootstrap modal using the RTE
 * @param {string} selector The selector for the textarea that is converter to a RTE
 * @param {string} directionality "ltr" or "rtl"
 * @param {boolean} inverted Set to true to get REDCap's default bold behavior
 */
function initializeRichTextEditor(modal, selector, directionality, inverted = false) {
    // @ts-ignore rich_text_image_embed_enabled from global scope
    const imageuploadIcon = rich_text_image_embed_enabled ? 'image' : '';
    // @ts-ignore rich_text_attachment_embed_enabled from global scope
    const fileuploadIcon = rich_text_attachment_embed_enabled ? 'fileupload' : '';
    const fileimageicons = (imageuploadIcon + ' ' + fileuploadIcon).trim();
    const config = {
        license_key: 'gpl',
        font_family_formats: 'Open Sans=Open Sans; Andale Mono=andale mono,times; Arial=arial,helvetica,sans-serif; Arial Black=arial black,avant garde; Book Antiqua=book antiqua,palatino; Comic Sans MS=comic sans ms,sans-serif; Courier New=courier new,courier; Georgia=georgia,palatino; Helvetica=helvetica; Impact=impact,chicago; Symbol=symbol; Tahoma=tahoma,arial,helvetica,sans-serif; Terminal=terminal,monaco; Times New Roman=times new roman,times; Trebuchet MS=trebuchet ms,geneva; Verdana=verdana,geneva; Webdings=webdings; Wingdings=wingdings,zapf dingbats',
        promotion: false,
        entity_encoding: "raw",
        default_link_target: '_blank',
        selector: selector,
        directionality: directionality,
        height: '100%',
        branding: false,
        statusbar: false,
        menubar: false,
        elementpath: false, // Hide this, since it oddly renders below the textarea.
        plugins: 'autolink image lists link searchreplace code fullscreen table directionality hr',
        toolbar1: 'fontfamily blocks fontsize bold italic underline strikethrough forecolor backcolor ltr rtl',
        toolbar2: 'align bullist numlist outdent indent table pre hr link '+fileimageicons+' fullscreen searchreplace removeformat undo redo code',
        contextmenu: "copy paste | link image inserttable | cell row column deletetable",
        // @ts-ignore app_path_webroot = global var
        content_css: app_path_webroot + "Resources/webpack/css/bootstrap.min.css," + app_path_webroot + "Resources/webpack/css/fontawesome/css/all.min.css,"+ app_path_webroot + "Resources/css/style.css",
        content_style: 'p { text-align: unset; }',
        relative_urls: false,
        convert_urls : false,
        extended_valid_elements: 'i[class]',
        paste_postprocess: function(plugin, args) {
            // @ts-ignore base.js
            args.node.innerHTML = cleanHTML(args.node.innerHTML);
        },
        remove_linebreaks: true,
        setup: function (editor) {
            // Add file attachment button to toolbar
            editor.ui.registry.addIcon('paper-clip-custom', '<svg height="20" width="20" viewBox="0 0 512 512"><path d="M396.2 83.8c-24.4-24.4-64-24.4-88.4 0l-184 184c-42.1 42.1-42.1 110.3 0 152.4s110.3 42.1 152.4 0l152-152c10.9-10.9 28.7-10.9 39.6 0s10.9 28.7 0 39.6l-152 152c-64 64-167.6 64-231.6 0s-64-167.6 0-231.6l184-184c46.3-46.3 121.3-46.3 167.6 0s46.3 121.3 0 167.6l-176 176c-28.6 28.6-75 28.6-103.6 0s-28.6-75 0-103.6l144-144c10.9-10.9 28.7-10.9 39.6 0s10.9 28.7 0 39.6l-144 144c-6.7 6.7-6.7 17.7 0 24.4s17.7 6.7 24.4 0l176-176c24.4-24.4 24.4-64 0-88.4z"/></svg>');
            editor.ui.registry.addButton('fileupload', { 
                icon: 'paper-clip-custom', 
                tooltip: 'Attach a file', 
                onAction: () => {
                    // @ts-ignore base.js
                    rich_text_attachment_dialog()
                }
            });
            // Add pre/code button to toolbar
            editor.ui.registry.addIcon('preformatted-custom', '<svg height="20" width="20" viewBox="0 0 640 512"><path d="M392.8 1.2c-17-4.9-34.7 5-39.6 22l-128 448c-4.9 17 5 34.7 22 39.6s34.7-5 39.6-22l128-448c4.9-17-5-34.7-22-39.6zm80.6 120.1c-12.5 12.5-12.5 32.8 0 45.3L562.7 256l-89.4 89.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l112-112c12.5-12.5 12.5-32.8 0-45.3l-112-112c-12.5-12.5-32.8-12.5-45.3 0zm-306.7 0c-12.5-12.5-32.8-12.5-45.3 0l-112 112c-12.5 12.5-12.5 32.8 0 45.3l112 112c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256l89.4-89.4c12.5-12.5 12.5-32.8 0-45.3z"/></svg>');
            editor.ui.registry.addButton('pre', {
                icon: 'preformatted-custom', 
                tooltip: 'Preformatted code block', 
                onAction: function() { 
                    // @ts-ignore
                    editor.insertContent('<pre>' + tinymce.activeEditor.selection.getContent() + '</pre>');
                }
            });
        },
        // Embedded image uploading
        file_picker_types: 'image',
        // @ts-ignore rich_text_image_upload_handler() from global scope
        images_upload_handler: rich_text_image_upload_handler,
        browser_spellcheck : true
    }
    if (inverted) {
        // Match REDCap's default bold label style.
        config.content_style = 'body { font-weight: bold; } p { text-align: unset; }';
        config.formats = {
            bold: {
                inline: 'span',
                styles: {
                    'font-weight': 'normal' // Make the 'bold' option function like an 'unbold' instead.
                }
            }
        }
    }
    // @ts-ignore
    tinymce.init(config)
    // Focus fix for TinyMCE modals inside Bootstrap modals
    // See https://stackoverflow.com/questions/34202549
    // The idea is to move the modals into the Bootstrap modal, so that the may receive focus.
    // Image upload (from TinyMCE)
    $(document).off('.tinymodal').on('focusin.tinymodal', function(e) {
        const dialog = $('.tox-tinymce-aux');
        if (dialog.length && modal.find(dialog).length === 0) {
            modal.append(dialog);
        }
    });
    // File attachment (from REDCap, based on jQuery UI)
    $(document).on('dialogopen', function(e) {
        const dialog = $(e.target).parent();
        const backdrop = $('.ui-widget-overlay');
        if (dialog.length && modal.find(dialog).length === 0) {
            modal.append(backdrop).append(dialog);
        }
    })
}


/**
 * Checks if a value is null/empty/undefined
 * @param {any} val 
 * @returns {boolean} True if null/empty/undefined
 */
function isEmpty(val) {
    if (typeof val == 'undefined' || val === null) {
        return true
    }
    if (typeof val == 'string' && val == '') {
        return true
    }
    if (typeof val == 'object') {
        const inner = val.translation ?? val.reference ?? null
        return inner == null || (typeof inner == 'string' && inner == '')
    }
    return false
}

/**
 * Gets a system langauge by GUID
 * @param {string} guid 
 * @returns {SystemLanguage|null}
 */
function getSysLangByGuid(guid) {
    for (const sysLangId in config.sysLangs) {
        const sysLang = config.sysLangs[sysLangId];
        if (sysLang.guid == guid) {
            return sysLang;
        }
    }
    return null;
}

/**
 * Creates an empty language object
 * @returns {Language}
 */
 function createEmptyLang() {
    const lang = {
        key: '',
        display: '',
        notes: '',
        sort: '',
        rtl: false,
        'recaptcha-lang': '',
        active: false,
        dd: {},
        ui: {},
    };
    if (config.mode == 'System') {
        lang.guid = '';
    }
    else {
        lang.subscribed = false;
        lang.syslang = '';
    }
    return lang;
}

/**
 * Copies a language object
 * @param {string} langKey 
 * @returns {Language}
 */
function copyLang(langKey) {
    const json = JSON.stringify(config.settings.langs[langKey])
    return JSON.parse(json)
}

/**
 * Validates fields in the add/edit language modal
 * @param {string} name The name of the field
 * @param {string} val The value to validate
 * @param {string} prevKey The previous language key (in edit mode)
 */
 function validateLanguage(name, val, prevKey) {
    switch(name) {
        case 'display':
            return val != ''
        case 'key':
            // Key must match pattern, be same (edit mode), or unique
            // https://regex101.com/r/DI3adu/1
            const re = /^[a-zA-Z]?(?:[a-zA-Z\-]*(?:[a-zA-Z]{1,}|\d{1,})$)/u;
            const res = re.exec(val)
            const rePass = (res != null) && (res[0] == val)
            const keyPass = (val == prevKey) || !Object.keys(config.settings.langs).map(val => val.toLowerCase()).includes(val.toLowerCase())
            return rePass && keyPass
    }
    return true
}

/** 
 * Sorts the languages
*/
function sortLanguages() {
    const unsorted = {}
    let suffix = 1
    for (const key of Object.keys(config.settings.langs)) {
        /** @type {Language} */
        const lang = config.settings.langs[key]
        // Add suffix in case of equal names
        const sortBy = (lang.sort ? lang.sort : lang.display).toUpperCase() + '-' + suffix++
        unsorted[sortBy] = key
    }
    /** @type {Array<string>} */
    const sorted = []
    for (const sortKey of Object.keys(unsorted).sort()) {
        const key = unsorted[sortKey]
        sorted.push(key)
    }
    return sorted
}

/**
 * Formats a date as YYYYMMDD-HHMMSS
 * @param {Date} d 
 */
function formatDatetime(d) {
    var year = d.getFullYear()
    var month = zeroPad(d.getMonth() + 1, 2)
    var day = zeroPad(d.getDate(), 2)
    var hour = zeroPad(d.getHours(), 2)
    var min = zeroPad(d.getMinutes(), 2)
    var sec = zeroPad(d.getSeconds(), 2)
    return year + month + day + '-' + hour + min + sec
}

/**
 * Formats a date as YYYY-MM-DD HH:MM:SS
 * @param {Date} d 
 */
function formatTimestamp(d) {
    var year = d.getFullYear()
    var month = zeroPad(d.getMonth() + 1, 2)
    var day = zeroPad(d.getDate(), 2)
    var hour = zeroPad(d.getHours(), 2)
    var min = zeroPad(d.getMinutes(), 2)
    var sec = zeroPad(d.getSeconds(), 2)
    return year + '-' + month + '-' + day + ' ' + hour + ':' + min + ':' + sec
}

/**
 * Formats a data as YYYYMMDD
 * @param {Date} d 
 */
function formatDate(d) {
    return formatDatetime(d).substring(0, 8)
}

/**
 * Returns a zero-padded number as a string
 * @param {number} val 
 * @param {number} digits 
 */
function zeroPad(val, digits) {
    if (val >= Math.pow(10, digits)) return val.toString()
    var s = '0'.repeat(digits) + val
    return s.substring(s.length - digits, s.length)
}

//#endregion

//#region -- Debug Logging and Helpers

/**
 * Logs a message to the console when in debug mode
 */
function log() {
    if (!config.settings.debug) return
    let ln = '??'
    try {
        const line = ((new Error).stack ?? '').split('\n')[2]
        const parts = line.split(':')
        ln = parts[parts.length - 2]
    }
    catch { }
    log_print(ln, 'log', arguments)
}
/**
 * Logs a warning to the console when in debug mode
 */
function warn() {
    if (!config.settings.debug) return
    let ln = '??'
    try {
        const line = ((new Error).stack ?? '').split('\n')[2]
        const parts = line.split(':')
        ln = parts[parts.length - 2]
    }
    catch { }
    log_print(ln, 'warn', arguments)
}

/**
 * Logs an error to the console when in debug mode
 */
function error() {
    if (!config.settings.debug) return
    let ln = '??'
    try {
        const line = ((new Error).stack ?? '').split('\n')[2]
        const parts = line.split(':')
        ln = parts[parts.length - 2]
    }
    catch { }
    log_print(ln, 'error', arguments)
}

/**
 * Prints to the console
 * @param {string} ln Line number where log was called from
 * @param {'log'|'warn'|'error'} mode 
 * @param {IArguments} args 
 */
function log_print(ln, mode, args) {
    var prompt = 'MultiLanguage [' + ln + ']'
    switch(args.length) {
        case 1: 
            console[mode](prompt, args[0])
            break
        case 2: 
            console[mode](prompt, args[0], args[1])
            break
        case 3: 
            console[mode](prompt, args[0], args[1], args[2])
            break
        case 4: 
            console[mode](prompt, args[0], args[1], args[2], args[3])
            break
        case 5: 
            console[mode](prompt, args[0], args[1], args[2], args[3], args[4])
            break
        case 6: 
            console[mode](prompt, args[0], args[1], args[2], args[3], args[4], args[5])
            break
        default: 
            console[mode](prompt, args)
            break
    }
}

/**
 * Wraps a function in order to time its execution
 * @param {function} func 
 * @param {Array|null} args
 * @returns 
 */
function timed(func, args = null) {
    const start = Date.now();
    const rv = args ? func(...args) : func();
    const end = Date.now();
    log('Timed [' + func.name + ']: ' + (end - start) + ' ms');
    return rv;
}

/**
 * Sets the AI translation HTML
 */
function setAITranslator() {
    if (currentLanguage == config.settings.refLang) {
        $(".mlm-ai-translate-tool").hide();
    } else {
        $(".mlm-ai-translate-tool").show();
    }
}

/**
 * Translate texts using AI
 */
function aiTranslate() {
    const stringsToTranslate = [];
    const editMap = {};
    // Find what to translate in the current view
    // First, set the view's container
    let $container = $('<div></div>');
    if (currentTab == 'alerts') {
        $container = $('div[data-mlm-render=alerts]')
    }
    else if (currentTab == 'misc') {
        if (currentMiscTabCategory == 'misc-mdc') {
            $container = $('div[data-mlm-sub-category=misc-mdc]');
        } else if (currentMiscTabCategory == 'misc-pdf') {
            $container = $('div[data-mlm-sub-category=misc-pdf]');
        } else if (currentMiscTabCategory == 'misc-descriptive-popups') {
            $container = $('div[data-mlm-sub-category=misc-descriptive-popups]');
        } else if (currentMiscTabCategory == 'misc-protmail') {
            $container = $('div[data-mlm-sub-category=misc-protmail]');
        }
    }
    else if (currentTab == 'forms') {
        if (formsTabMode == 'survey') {
            $container = $('div[data-mlm-render=survey]')
        } else if (formsTabMode == 'asi') {
            $container = $('div[data-mlm-render=asi]')
        } else {
            $container = $('div[data-mlm-render=fields]')
        }
    }
    else if (currentTab == 'ui') {
        let sub_cat = $('div[data-mlm-tab=ui]').find('a.active').attr('data-mlm-sub-category');
        if (sub_cat == 'ui-all') { // change selector if "UI -> All" sub category is active
            sub_cat = "mlm-translation-item";
        }
        $container = $('div.'+sub_cat);
    }
    else if (currentTab == 'mycap') {
        $container = $('div[data-mlm-sub-category='+currentMyCapTabCategory+']');
    }
    // Then, find what to translate
    $container.find('[data-mlm-translation]').each(function() {
        const $editEl = $(this);
        // Skip checkboxes/dropdowns and when already translated
        if ($editEl.is('checkbox') || $editEl.is('select') || ($editEl.val() ?? '') !== '') return; 
        // Extract type/name/index
        const type = $editEl.attr('data-mlm-type') ?? '';
        let name = $editEl.attr('data-mlm-translation') ?? '';
        if (name == '') name = $editEl.attr('data-mlm-name') ?? '';
        const index = $editEl.attr('data-mlm-index') ?? '';
        const refValue = getRefValue(type, name, index);
        if (refValue != '') {
            // Add to array if there is a non-empty reference value
            stringsToTranslate.push(getRefValue(type, name, index));
            editMap[stringsToTranslate.length] = $editEl;
            log('Translating:', type, name, index);
        }
    });
    // Check if there is anything to translate
    if (stringsToTranslate.length < 1) {
        simpleDialog("<div class='fs15'>"+lang.openai_096+"</div>");
        return;
    }
    // Initiate translation
    const message = interpolateString(window['lang'].openai_138, [stringsToTranslate.length]) + 
        "<br><div class='font-weight-normal fs14 my-1'>"+window['lang'].openai_095+"</div>";
    showProgress(1, 0, message);
    const language = config.settings.langs[currentLanguage]
    log('Requesting AI translations:', stringsToTranslate);
    $.post(app_path_webroot+'AI/translator.php'+(pid == '' ? '' : '?pid='+pid), { 
        texts: JSON.stringify(stringsToTranslate),
        action: 'get_translations',
        languageName: language.display + ' (' + language.key + ')'
    }, function(data) {
        if (data == '0') {
            alert(woops);
        } else {
            const json_data = JSON.parse(data);
            log('Received AI translations:', json_data, 'Mapping to: ', editMap);
            // All we need to do now is insert the translations into the previously stored elements
            for (const index in json_data) {
                const value = json_data[index];
                const $editEl = editMap[index];
                if ($editEl) {
                    $editEl.val(value);
                    $editEl.trigger('change');
                }
            }
        }
        showProgress(0, 0);
    });
}

/**
 * Strips the country code from valid ISO 639-1 language codes (i.e., the part after the dash, including the dash) 
 * E.g., passing "en-US" will return "en"
 * @param {string} langId 
 * @returns string
 */
function stripCountryCode(langId) {
    return langId.replace(/[-][A-Za-z].*$/, '');
}

/**
 * Show warning message to user if MyCap is enabled for language which is not supported at app-side
 * @param {string} langKey 
 */
function checkMyCapSupportedLanguage(langKey) {
    const searchLang = stripCountryCode(langKey);
    const supportedLangs = Object.values(config.myCapSupportedLanguages);
    // More flexible check - en-US/en-UK/en will match to en-US, de/de-XX matches to de-DE
    if (supportedLangs.findIndex((myCapLang) => myCapLang.startsWith(searchLang), searchLang) < 0) {
        simpleDialog($lang.multilang_808, $lang.global_48, null, 400)
    }
}


//#endregion

})();