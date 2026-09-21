/* REDCap Multi-Language Forms and Surveys */

// @ts-check
;(function() {

//#region -- Constants & Variables

const TRANSLATION_MISSING_CLASS = 'mlm-translation-missing'
const DATAENTRY_RC_SELECTORS = ['#center', '#data-collection-menu', '#mlm-embed-repository', '#valtext_divs']
const DATAENTRY_MLM_SELECTOR = '#center [data-mlm], #data-collection-menu [data-mlm], #mlm-embed-repository [data-mlm]'
const DATAENTRY_FIELD_SELECTOR = '#center [data-mlm-field], #data-collection-menu [data-mlm-field], #mlm-embed-repository [data-mlm-field]'
const SURVEY_RC_SELECTORS = ['body']
const SURVEY_MLM_SELECTOR = '[data-mlm]'
const SURVEY_FIELD_SELECTOR = '[data-mlm-field]'

/** @type {Boolean} Indicates whethere MLM has completed initialization */
var initialized = false

/** @type {REDCap} */
// @ts-ignore
var REDCap = window.REDCap
if (typeof REDCap == 'undefined') {
    // @ts-ignore
    REDCap = {}
    // @ts-ignore
    window.REDCap = REDCap
}
if (typeof REDCap.MultiLanguage == 'undefined') {
    REDCap.MultiLanguage = {
        isInitialized: function() {
            return initialized
        },
        init: initialize,
        updateUI: public_updateUI,
        getCurrentLanguage: function() {
            return currentLanguage
        },
        isRTL: function() {
            return (config && config.langs && config.langs[currentLanguage]) ? config.langs[currentLanguage].rtl : false
        },
        translateFieldLables: public_translateFieldLables,
        updateSurveyQueue: public_updateSurveyQueue,
        translateRcLang: public_translateRcLang,
        onLangChanged: public_registerOnLangChanged,
        unsubscribe_onLangChanged: public_unregisterOnLangChanged,
        getDescriptivePopupTranslation: public_getDescriptivePopupTranslation,
        updateFloatingMatrixHeaders: function() {
            setFloatingMatrixHeaderDirectionality(this.isRTL());
        }
    }
}
/** @type {MultiLanguage} */
const ML = REDCap.MultiLanguage; 

/** @type MultiLanguageData */
var config

/** @type {string} The current language for translations */
var currentLanguage = ''

/** @type {boolean} TRUE when on a data entry page */
var isDataEntry = false

/** @type {boolean} Keeps track whether text alignments have been recorded */
var textAlignmentRecorded = false

/** @type {Number} The duration the fade effect is applied during language switching */
const FADE_DURATION = 200

//#endregion

//#region -- Initialization

function waitForJQuery() {
    return new Promise(function(resolve) {
        const checker = setInterval(function() {
            if (typeof $ != 'undefined') {
                clearInterval(checker)
                resolve(null)
            }
        }, 100);
    })
}
/**
 * Initializes the Multi-Language Management page
 * @param {MultiLanguageData} data 
 */
 function initialize(data) {
    if (initialized) {
        error('MLM initialization has already been performed.')
        return
    }
    config = data
    // Are there any RTL languages active?
    // @ts-ignore
    config.hasRTL = Object.keys(config.langs).reduce(function(prev, current) {
        return prev || config.langs[current].rtl
    }, false)
    isDataEntry = config.mode.startsWith('dataentry-')
    waitForJQuery().then(function() {
        $(function() {
            /** Keeps track of whether language rendering has occurred during initialization */
            let langChangedDuringInit = false
            try {
                log('Multi-Language controls initializing ...', config)
                let showLangMenu = false
                let userLang;
                if (isDataEntry) {
                    userLang = config.userPreferredLang;
                }
                else {
                    userLang = getLangKeyFromCookie()
                    if (userLang == '' && config.autoDetectBrowserLang) {
                        userLang = getLangKeyFromBrowserPreferences()
                        if (userLang != '') {
                            showLangMenu = true
                        }
                    }
                }
                let preferredLang = currentLanguage
                if (typeof config.setLang != 'undefined' && config.setLang != '') {
                    preferredLang = config.setLang
                }
                else if (userLang != '') {
                    preferredLang = userLang
                }
                else if (typeof config.initialLang != 'undefined') {
                    preferredLang = config.initialLang
                    showLangMenu = true
                }
                const targetLanguageOverride = setupLanguageSetActionTag()
                const targetLanguage = targetLanguageOverride != '' 
                    ? targetLanguageOverride 
                    : (preferredLang == '' ? config.fallbackLang : preferredLang)
                if (isDataEntry) {
                    currentLanguage = config.refLang
                }
                if (currentLanguage == targetLanguage) {
                    // Need to "fix" currentLanguage to be different, as otherwise no translation occurs
                    currentLanguage = config.refLang
                }
                log('Creating switcher, actual = [' + targetLanguage + '], preferred = [' + preferredLang + ']')
                // Create the switcher 
                renderLanguageSwitcher()
                log('Initialization complete.')
                langChangedDuringInit = switchLanguage(targetLanguage, true, true, false)
                updateLanguageSwitcher()
                // Force the language switcher to show when there is no (valid) preferred language and there are at least 2 languages (applies to survey pages only)
                // But definitely do NOT show it when there was a targetLanguageOverride
                showHideLangMenu(
                    (preferredLang == '' || userLang == '' || showLangMenu) && config.numLangs > 1, 
                    preferredLang != '' || targetLanguageOverride != '',
                    false
                );
            }
            catch(err) {
                error("Failed to initialize MultiLanguage:", err)
                // TODO - Probably show an error message to the user, indicating that translation of the survey might be incomplete?
            }
            finally {
                // @ts-ignore
                $('[data-toggle="tooltip"]').tooltip()
                // Visuals disabled for now -- need to find better effect
                // setTimeout(function() {
                //     $('body').removeClass('mlm-faded')
                // }, FADE_DURATION)

                // In case no language rendering occurred during initialization, we still need to notify subscribers
                if (!langChangedDuringInit) {
                    const langKey = config.langs.hasOwnProperty(currentLanguage) ? currentLanguage : ''
                    const rtl = langKey == '' ? false : config.langs[currentLanguage].rtl
                    notifyOnLangChangedSubscribers(langKey, rtl)
                }
                initialized = true
            }
        })
    });
    // Prevent translation through browser
    const mainContent = document.getElementById(config.mode == 'dataentry-form' ? 'center' : 'pagecontent');
    if (mainContent) {
        mainContent.translate = false;
    }
    const noTranslateMeta = document.createElement('meta');
    noTranslateMeta.name = 'google';
    noTranslateMeta.content = 'notranslate';
    document.getElementsByTagName('head')[0].appendChild(noTranslateMeta);
}

//#endregion

//#region -- Public Methods

/**
 * Refreshes UI translations
 */
function public_updateUI() {
    // Wait for initialization
    if (!initialized) {
        setTimeout(ML.updateUI, 50)
        return
    }
    if (config && config.langs && config.langs.hasOwnProperty(currentLanguage)) {
        const lang = config.langs[currentLanguage]
        translateGlobalLang(lang)
        translateDataRcLang(lang)
    }
}
/**
 * Translates field labels
 */
function public_translateFieldLables() {
    // Wait for initialization
    if (!initialized) {
        setTimeout(ML.translateFieldLables, 50)
        return
    }
    let langKey = currentLanguage
    if (langKey == '') {
        langKey = getLangKeyFromCookie()
    }
    if (langKey == '') {
        langKey = getLangKeyFromBrowserPreferences()
    }
    if (config && config.langs && config.langs.hasOwnProperty(langKey)) {
        const lang = config.langs[langKey]
        $('[data-mlm-field-label]').each(function() {
            const $this = $(this)
            const name = getAttrVal($this, 'data-mlm-field-label');
            const label = getFieldTranslation(lang, name, 'label', '').value
            if (label) {
                $this.text(sanitizeTextOnly(label))
            }
        })
    }
}

/**
 * Translates Survey Queue elements
 * @param {Object} sqData 
 * @param {boolean} apply 
 */
function public_updateSurveyQueue(sqData, apply = false) {
    var arr = Object.keys(sqData.langs);
    for (var i = 0; i < arr.length; i++) {
        var langId = arr[i];
        if (config.langs.hasOwnProperty(langId)) {
            config.langs[langId]['sq-translations'] = sqData.langs[langId]['sq-translations'];
        }
    }
    config.ref['sq-translations'] = sqData.ref['sq-translations'];
    ML.onLangChanged(function() {
        updateSurveyQueue(config.langs[currentLanguage]);
    })
    if (apply) {
        updateSurveyQueue(config.langs[currentLanguage]);
    }
    log('Updating SQ data:', sqData);
}

/**
 * Translates data-rc-lang elements in a specfied scope
 * @param {string} selector 
 */
function public_translateRcLang(selector) {
    translateDataRcLangScoped(config.langs[currentLanguage], selector)
}

/**
 * Registers a callback for the onLangChanged event 
 * Parameters are: 
 *  - string  = The id of the language that was just rendered
 *  - boolean = Whether the language is an right-to-left language
 * @param {function(string,boolean):void} callback A callback that takes a string and bool as parameters
 */
function public_registerOnLangChanged(callback) {
    onLangChangedLastHandle++
    onLangChangedCallbacks[onLangChangedLastHandle] = callback
    return onLangChangedLastHandle
}
var onLangChangedCallbacks = {}
var onLangChangedLastHandle = 0

/**
 * Unregisters the specified callback
 * @param {Number} handle 
 */
function public_unregisterOnLangChanged(handle) {
    if (onLangChangedCallbacks[handle]) {
        delete onLangChangedCallbacks[handle]
    }
}

/**
 * Gets a descriptive popup's data for the currently active language
 * @param {Number} popupId
 * @returns {Object} // Contains the translation (value) and an indicator (missing) whether this is a fallback or reference value
 */
function public_getDescriptivePopupTranslation(popupId) {
    const lang = config.langs[currentLanguage];
    return {
        'inline_text': 
            getDDTranslation(lang, 'descriptive-popup-translations', ''+popupId, 'inline_text'),
        'inline_text_popup_description': 
            getDDTranslation(lang, 'descriptive-popup-translations', ''+popupId, 'inline_text_popup_description')
    };
}

//#endregion

//#region -- Cookie Handling 

/**
 * Gets the IDs of active languages
 * @returns {string[]}
 */
function getActiveLangs() {
    const langs = []
    for (let langKey in config.langs) {
        if (config.langs[langKey].active) {
            langs.push(langKey)
        }
    }
    return langs
}

/**
 * Determine (from cookie) the language to use.
 * @returns {string} A language key or '' if there is no valid language key.
 */
function getLangKeyFromCookie() {
    let lang = ''
    const activeLangs = getActiveLangs()
    // First, try to get the language from the language cookie
    if (typeof config.cookieName != 'undefined') {
        // First, try to get language from cookie.
        var arr = document.cookie.split('; ');
        for (var i = 0; i < arr.length; i++) {
            var cookie = arr[i];
            if (cookie.substring(0, config.cookieName.length) == config.cookieName) {
                lang = cookie.substring(config.cookieName.length + 1)
            }
        }
        // Does the language exist?
        lang = activeLangs.includes(lang) ? lang : ''
    }
    return lang
}

/**
 * Attempts to match a preferred language in the browser with one of the active languages
 * @returns {string}
 */
function getLangKeyFromBrowserPreferences() {
    let lang = ''
    const langMap = {}
    const activeLangs = getActiveLangs().map(function (val) {
        const lc = val.toLowerCase()
        langMap[lc] = val
        return lc
    })
    // If not set yet, try to match against browser languages
    if (navigator && (navigator.language || navigator.languages)) {
        const browserLangs = [...navigator.languages].map(function (val){
            return val.toLowerCase()
        })
        if (navigator.language && !browserLangs.includes(navigator.language.toLowerCase())) {
            browserLangs.push(navigator.language.toLowerCase())
        }
        log('Attempting to get lang from browser', browserLangs, activeLangs)
        // Exact match?
        for (let browserLang of browserLangs) {
            if (activeLangs.includes(browserLang)) {
                lang = langMap[browserLang]
                break
            }
        }
        // Matching start AND before a hyphen?
        if (lang == '') {
            for (let browserLang of browserLangs) {
                for (let activeLang of activeLangs) {
                    if (browserLang.indexOf(activeLang.split('-')[0]) === 0) {
                        lang = langMap[activeLang]
                        break
                    }
                }
                if (lang != '') break
            }
        }
        if (lang == '') {
            log('Failed to determine language from browser preference.')
        }
        else {
            // Fix case
            log('Determined language from browser preference:', lang)
        }
    }
    return lang
}

/**
 * Determine (from cookie only) whether text-to-speech is on.
 * @returns {boolean}
 */
function isText2SpeechEnabled() {
    const cookieName = 'texttospeech'
    let on = false
    var arr = document.cookie.split('; ');
    for (var i = 0; i < arr.length; i++) {
        var cookie = arr[i];
        if (cookie.startsWith(cookieName)) {
            on = cookie.substring(cookieName.length + 1) == '1'
        }
    }
    return on
}

/**
 * Sets the language cookie
 * @param {string} val
 */
function setLangCookie(val) {
    if (isDataEntry && val != config.userPreferredLang) {
        // For logged in users, do not use the cookie but store in UIState
        ajax('set-user-preferred-lang', val).then(function() {
            log('User language preference updated to: [' + val + ']')
            config.userPreferredLang = val
        }).catch(function(err) {
            console.error('Failed to update language preference to [' + val + ']', err)
        })
    }
    else {
        // @ts-ignore (base.js)
        setCookie(config.cookieName, val, 60)
        log('Cookie set to: [' + val + ']')
    }
}

//#endregion

//#region -- Language Switcher Widget

/**
 * Sorts the languages
*/
function sortLanguages(returnActiveOnly) {
    if (typeof returnActiveOnly == 'undefined') returnActiveOnly = false;
    const unsorted = {};
    let arr;
    let suffix = 1;
    arr = Object.keys(config.langs);
    for (let i = 0; i < arr.length; i++) {
        const key = arr[i];
        /** @type {Language} */
        const lang = config.langs[key];
        // Add suffix in case of equal names
        if (!returnActiveOnly || (returnActiveOnly && lang.active)) {
            const sortBy = (lang.sort ? lang.sort : lang.display).toUpperCase() + '-' + suffix++;
            unsorted[sortBy] = key;
        }
    }
    /** @type {Array<string>} */
    const sorted = [];
    arr = Object.keys(unsorted).sort();
    for (let i = 0; i < arr.length; i++) {
        const sortKey = arr[i];
        const key = unsorted[sortKey];
        sorted.push(key);
    }
    return sorted;
}

/**
 * Creates the language switcher widget
 */
function renderLanguageSwitcher() {
    let $switcher = $();
    let $link = $();
    var activeLangs = sortLanguages(true);
    if (activeLangs.length < 2) return;
    if (isDataEntry) {
        $switcher = $('<div class="mlm-language-menu"></div>');
        // Button
        // @ts-ignore -- REDCap global var app_path_images
        $switcher.append('<button id="LanguageDropDown" onclick="showBtnDropdownList(this,event,\'LanguageDropDownDiv\');return false;" class="jqbuttonmed ui-button ui-corner-all ui-widget"><i class="fas fa-globe"></i> <span data-mlm-language>???</span> <img src="' + app_path_images + 'arrow_state_grey_expanded.png" style="margin-left:2px;vertical-align:middle;position:relative;top:-1px;" alt=""></button>');
        // Dropdown
        const $dropdownDiv = $('<div id="LanguageDropDownDiv" class="mlm-language-menu dropdown-menu-div" style="display:none;"></div>');
        const $ul = $('<ul role="menu" tabindex="0" class="ui-menu ui-widget ui-widget-content"></ul>');
        $dropdownDiv.append($ul);
        for (let i = 0; i < activeLangs.length; i++) {
            const key = activeLangs[i];
            const lang = config.langs[key];
            const $li = $('<li class="ui-menu-item"></li>');
            const $a = $('<a href="javascript:;" tabindex="-1" role="menuitem" class="ui-menu-item-wrapper"></a>');
            $a.html(lang.display + '&nbsp;&nbsp;<span class="lang-id">[' + key + ']</span>');
            $a.on('click', function() {
                $dropdownDiv.hide();
                switchLanguage(lang.key, false, false, false);
            });
            $ul.append($li.append($a));
        }
        $switcher.append($dropdownDiv);
    }
    else {
        $link = createSwitchLanguageLink();
        $switcher = $('<div data-mlm-widget="switcher" class="mlm-switcher" style="display:none;"></div>');
        for (let i = 0; i < activeLangs.length; i++) {
            const key = activeLangs[i];
            /** @type {Language} */
            const lang = config.langs[key];
            const $btn = $('<button data-mlm-language="' + lang.key + '" class="btn btn-outline-secondary btn-sm">' + lang.display + '</button>');
            $btn.on('click', null, lang.key, switchLanguageByEvent);
            $switcher.append($btn);
        }
    }
    // Insert menu or switcher and language icon depending on mode
    switch (config.mode) {

        case 'dataentry-form':
            // The order matters!!
            if ($('#formtop-div-2 div.clear').length) {
                $('#formtop-div-2 div.clear').before($switcher);
            }
            else if ($('#inviteFollowupSurveyBtn').length) {
                $('#inviteFollowupSurveyBtn').prepend($switcher);
            }
            else if ($('#formtop-div div.clear').length) {
                $('#formtop-div div.clear').before($switcher);
            }
            break;

        case 'survey':
            // Icon already there
            $('#surveytitlelogo').after($switcher);
            break;

        case 'survey-return':
            $('#return_code_form_instructions').before($switcher);
            $('#return_instructions').before($switcher);
            $('#pagecontent').prepend($link);
            break;

        case 'exit-survey':
            let $exitPlace = $('#surveyacknowledgment');
            if ($exitPlace.length) {
                $exitPlace.before($switcher);
                $('#pagecontent').prepend($link);
            }
            else {
                $exitPlace = $('#pagecontent');
            }
            if ($exitPlace.length) {
                $exitPlace.before($switcher);
                $exitPlace.prepend($link);
            }
            else {
                $exitPlace = $('#container');
                $exitPlace.prepend($switcher);
                $exitPlace.prepend($link);
            }
            break;

        case 'survey-access':
            $switcher.append('<div class="mlm-languages-note" data-rc-lang="multilang_647"></div>');
            $link.css('float','left').css('display','inline-block');
            $('#survey_code_form').before($switcher);
            $('#project-redcap-link').prepend($link).css('margin-bottom','15px');
            break;

        case 'survey-queue':
            $('[data-mlm-survey-queue]').prepend($switcher);
            $('#pagecontent').prepend($link);
            break;

        case 'survey-queue-disabled':
            $('#container').prepend($link);
            $('div > span[data-rc-lang=survey_508]').before($switcher);
            break;

        case 'survey-login':
            $('#survey-login-instructions').prepend($switcher).prepend($link);
            $link.css('position','absolute').css('right','10px').css('top','10px');
            $link.find('button').css('padding','0');
            break;

        case 'survey-captcha':
            $('#pagecontent').prepend($switcher.css('margin-top','2em'));
            $('#pagecontent').prepend($link);
            break;
    }
    if (!isDataEntry) {
        // Show icon in control area only if there are multiple language available
        if (config.numLangs > 1) {
            $('#mlm-change-lang').on('click', showHideLangMenu).show();
        }
    }
}

/**
 * Creates the language switcher link (survey pages)
 * @returns {JQuery<HTMLElement>}
 */
function createSwitchLanguageLink() {
    const $div = $('<div class="mlm-change-lang-container"></div>');
    const $btn = $('<button id="mlm-change-lang" class="btn btn-link" data-rc-lang-attrs="aria-label=multilang_02 data-bs-original-title=multilang_02" data-toggle="tooltip"><i class="fas fa-globe"></i> <span class="btn-link-lang-name"></span></button>');
    // @ts-ignore
    $btn.attr('title', window.lang.multilang_02).attr('aria-labal', window.lang.multilang_02);
    $btn.css('display', 'none');
    $div.append($btn);
    return $div;
}

/**
 * Updates the language switcher widget to show the currently active language
 */
function updateLanguageSwitcher() {
    if (isDataEntry) {
        $('.mlm-language-menu [data-mlm-language]').text(config.langs[currentLanguage].display);
    }
    else {
        $('.btn-link-lang-name').html( $('button[data-mlm-language="' + currentLanguage + '"]').text() );
        $('button[data-mlm-language]').removeClass('btn-primary').addClass('btn-outline-secondary').find('i.fas').remove();
        $('button[data-mlm-language="' + currentLanguage + '"]').removeClass('btn-outline-secondary').addClass('btn-primary').prepend('<i class="fas fa-check me-1"></i>');
    }
    // Replace instances of fake object tag
    // @ts-ignore DataEntrySurveyCommon.js
    if (typeof fakeObjectTag == 'function') replaceFakeObjectTag();
}

/**
 * Toggles visibility of the language switcher (survey pages only)
 * @param {boolean|JQuery.Event} forceShow
 * @param {boolean} forceHide
 * @param {boolean} participantSelected
 */
function showHideLangMenu(forceShow, forceHide, participantSelected) {
    if (typeof forceShow == 'undefined') forceShow = false;
    if (typeof forceHide == 'undefined') forceHide = false;
    if (typeof participantSelected == 'undefined') participantSelected = false;
    // Do nothing on data entry pages
    if (isDataEntry) return;
    // Show or hide the menu on survey pages
    log('Showing/Hiding language menu', forceShow, forceHide, participantSelected);
    const $switcher = $('[data-mlm-widget=switcher]');
    if (config.staticMenu || forceShow === true || (!forceHide && $switcher.css('display') == 'none')) {
        $switcher.show();
        if (!config.staticMenu) $switcher.find('button').first().trigger('focus');
    }
    else {
        if (participantSelected) {
            setTimeout(function() {
                $switcher.hide(FADE_DURATION);
            }, 1000);
        } else {
            $switcher.hide(FADE_DURATION);
        }
    }
}

//#endregion

//#region -- Switch Language

/**
 * Forwards data from an event with a language payload
 * @param {{ data:string }} event
 */
function switchLanguageByEvent(event) {
    switchLanguage(event.data, false, false, true)
}

/**
 * Updates the translations
 * @param {string} langKey ID of the language to switch to
 * @param {boolean} suppressLangMenu When true, does not show the language menu
 * @param {boolean} forceRender When true, forces language updates
 * @param {boolean} participantSelected 
 * @returns {boolean} True if an actual update has happened
 */
function switchLanguage(langKey, suppressLangMenu, forceRender, participantSelected) {
    let langSwitched = false
    if (typeof suppressLangMenu == 'undefined') suppressLangMenu = false;
    if (typeof forceRender == 'undefined') forceRender = false;
    if (typeof participantSelected == 'undefined') participantSelected = false;
    if (!Object.keys(config.langs).includes(langKey)) {
        return langSwitched
    }
    const prevLanguage = typeof currentLanguage != 'undefined' ? currentLanguage : config.refLang
    currentLanguage = langKey
    if (prevLanguage != currentLanguage || forceRender) {
        setLangCookie(currentLanguage)
        updateLanguageSwitcher()
        const lang = config.langs[currentLanguage]
        // Dim page while working (not in debug mode nor if current language is the reference language)
        // Visuals disabled for now -- need to find better effect
        // if (!config.debug && currentLanguage != config.refLang) {
        //     $('body').addClass('mlm-faded')
        // }
        try {
            switch (config.mode) {
                case 'survey':
                case 'dataentry-form':
                    updateSurveyPage(lang, prevLanguage)
                    break
                case 'survey-access':
                case 'survey-return':
                case 'exit-survey':
                case 'survey-queue-disabled':
                case 'survey-captcha':
                    updateGenericSurveyPage(lang)
                    break
                case 'survey-queue':
                    updateSurveyQueue(lang)
                    break
                case 'survey-login':
                    updateSurveyLogin(lang)
                    break
                default:
                    // Other page types?
                    break
            }
            log('Switched to language [' + currentLanguage + ']')
            // Notify subscribers
        }
        catch (err) {
            error('Failed to switch the language!', err)
        }
        finally {
            notifyOnLangChangedSubscribers(lang.key, lang.rtl)
            langSwitched = true
            // Reveal page -- visuals disabled for now - need to find better
            // setTimeout(function() {
            //     $('body').removeClass('mlm-faded')
            // }, FADE_DURATION)
        }
    }
    showHideLangMenu(false, suppressLangMenu, participantSelected)
    return langSwitched
}


/**
 * Notifies onLangLanged subscribers
 * @param {string} langKey 
 * @param {boolean} rtl 
 */
function notifyOnLangChangedSubscribers(langKey, rtl) {
    if (langKey in config.langs) {
        var arr = Object.keys(onLangChangedCallbacks);
        for (var i = 0; i < arr.length; i++) {
            var handle = arr[i];
            try {
                onLangChangedCallbacks[handle](langKey, rtl)
            }
            catch (ex) {
                error('Exception during onLangChanged for handle [' + handle + ']', ex)
            }
        }
    }
}

/**
 * Translates survey queue elements
 * @param {Language} lang
 */
function updateSurveyQueue(lang) {
    // Custom survey text
    const cstVal = getSQValue('sq-survey_queue_custom_text', '', '')
    if (cstVal && cstVal.value.length) {
        const $cst = $('[data-mlm="survey-queue-text"]')
        $cst.html(cstVal.value)
        toggleMissingClass($cst, cstVal.missing)
    }
    // Survey Titles / Take survey again button
    $('[data-mlm-sq="survey-title"]').each(function() {
        const $this = $(this)
        const surveyId = getAttrVal($this, 'data-mlm-id');
        const val = getSQValue('survey-title', surveyId, '')
        $this.text(val.value)
        toggleMissingClass($this, val.missing)
    })
    $('[data-mlm-sq="survey-repeat_survey_btn_text"]').each(function() {
        const $this = $(this)
        const surveyId = getAttrVal($this, 'data-mlm-id');
        const val = getSQValue('survey-repeat_survey_btn_text', surveyId, '')
        $this.text(val.value)
        toggleMissingClass($this, val.missing)
    })

    // Event name and instance text
    // TODO - need to provide MLM backend implementation first

    // User Interface
    translateGlobalLang(lang)
    translateDataRcLang(lang)
}

/**
 * Gets a Survey Queue translation value
 * @param {string} type
 * @param {string} id
 * @param {string} instance
 * @returns
 */
function getSQValue(type, id, instance) {
    let val = ''
    let missing = false
    const lang = config.langs[currentLanguage]
    let sq = lang['sq-translations']
    if (lang.key != config.refLang) {
        if (sq.hasOwnProperty(type)) {
            val = ensureString(sq[type][id][instance], '');
        }
        // Check fallback
        if (!val.length && config.langs.hasOwnProperty(config.fallbackLang)) {
            sq = config.langs[config.fallbackLang]['sq-translations']
            if (sq.hasOwnProperty(type)) {
                val = ensureString(sq[type][id][instance], '');
                missing = true
            }
        }
    }
    if (!val.length) {
        // Use reference
        sq = config.ref['sq-translations']
        val = ensureString(sq[type][id][instance], '');
        missing = lang.key != config.refLang
    }
    return {
        value: val,
        missing: missing
    }
}

/**
 * Translates a survey return page
 * @param {Language} lang
 */
function updateGenericSurveyPage(lang) {
    // General (Survey Settings)
    translateDataMlm(lang)
    // User Interface
    translateGlobalLang(lang)
    translateDataRcLang(lang)
    // reCAPTCHA
    renderCAPTCHA(lang["recaptcha-lang"]);
}

/**
 * Re-renders the reCAPTCHA
 * @param {string} hl 
 */
function renderCAPTCHA(hl) {
    if (typeof window['grecaptcha']?.render == 'undefined') {
        setTimeout(() => {
            renderCAPTCHA(hl); 
        }, 100);
        return;
    }
    const $old = $('.g-recaptcha');
    const prev = $old.attr('data-mlm-recaptcha-hl');
    if ($old.length == 1 && prev != hl) {
        const $new = $('<div class="g-recaptcha"></div>');
        $new.attr('data-mlm-recaptcha-hl', hl);
        $old.before($new);
        $old.remove();
        window['grecaptcha'].render($new[0], { 
            'sitekey' : config.recaptchaSiteKey, 
            'hl': hl 
        });
    }
}

/**
 * Translates the survey login page
 * @param {Language} lang
 */
function updateSurveyLogin(lang) {
    // Questions
    $(SURVEY_FIELD_SELECTOR).each(function() {
        const $this = $(this)
        const name = getAttrVal($this, 'data-mlm-field');
        const type = getAttrVal($this, 'data-mlm-type');
        const val = getFieldTranslation(lang, name, type, '')
        $this.text(sanitizeTextOnly(val.value))
        toggleMissingClass($this, val.missing)
    })
    // General (Survey Settings)
    translateDataMlm(lang)
    // User Interface
    translateGlobalLang(lang)
    translateDataRcLang(lang)
}

/**
 * Translates the window.lang object
 * @param {Language} lang
 */
function translateGlobalLang(lang) {
    var arr = Object.keys(window['lang']);
    for (var i = 0; i < arr.length; i++) {
        var id = arr[i];
        const translation = getTranslation(lang, 'ui-translations', id)
        if (translation.value) {
            // @ts-ignore
            window['lang'][id] = translation.value
        }
        else {
            // warn('Missing (window.lang) UI string:', id, window['lang'][id])
        }
    }
}

/**
 * Translates elements with attribute data-mlm (such as survey title, instructions, etc)
 * @param {Language} lang
 */
function translateDataMlm(lang) {
    const selector = isDataEntry ? DATAENTRY_MLM_SELECTOR : SURVEY_MLM_SELECTOR;
    // Go through all elements
    $(selector).each(function() {
        const $this = $(this);
        const type = getAttrVal($this, 'data-mlm');
        /** @type {{ value: string, missing: boolean }} */
        let translation = { value: '???', missing: true };

        // Survey settings
        if (type.startsWith('survey-')) {
            translation = getTranslation(lang, 'survey-translations', type);
            $this.html(translation.value);
            if (type == 'survey-title') {
                document.title = $this.text(); // Set title to text-only (i.e strip tags etc.)
            }
        }
        // Survey Queue settings
        else if (type.startsWith('sq-')) {
            translation = getTranslation(lang, 'sq-translations', type);
            $this.html(translation.value);
        }
        // Table PK
        else if (type == 'table-pk-label') {
            const fieldName = getAttrVal($this, 'data-mlm-field');
            translation = getFieldTranslation(lang, fieldName, 'label', '');
            $this.html(translation.value);
        }
        // Missing? Add or remove the highlighter class
        toggleMissingClass($this, translation.missing);
    })
}

/**
 * Translates validation labels
 * @param {Language} lang
 */
function translateValidationLabels(lang) {
    for (const ui_id of Object.keys(lang["ui-translations"])) {
        if (ui_id.startsWith('_valtype_')) {
            const valtype = ui_id.substring(9)
            const label = textOnly(getTranslation(lang, 'ui-translations', ui_id).value)
            $('[id="valregex-' + valtype + '"]').attr('label', label)
        }
    }
}

/**
 * Translate form and event names
 * @param {Language} lang
 */
function translateFormEventNames(lang) {
    const scopes = ['#center', '#data-collection-menu']
    for (var i = 0; i < scopes.length; i++) {
        var scope = scopes[i];
        $(scope).find('[data-mlm]').each(function() {
            const $this = $(this)
            const type = getAttrVal($this, 'data-mlm');
            const name = getAttrVal($this, 'data-mlm-name');
            if (['form-name','event-name','event-custom_event_label'].includes(type)) {
                const translation = getTranslation(lang, type, name)
                $this.html(translation.value)
                toggleMissingClass($this, translation.missing)
            }
        })
    }
}

/**
 * Translates missing data codes
 * @param {Language} lang
 */
function translateMissingDataCodes(lang) {
    if (!config.ref['mdc-label']) return // Nothing to do
    const $menu = $('#MDMenu')
    var arr = Object.keys(config.ref['mdc-label']);
    for (var i = 0; i < arr.length; i++) {
        var mdc = arr[i];
        const label = getTranslation(lang, 'mdc-label', mdc)
        // Menu label
        const $label = $menu.find('[code="' + mdc + '"]')
        $label.attr('label', label.value)
        $label.text(label.value)
        toggleMissingClass($label, label.missing)
    }
    // Fields with MDC displayed (blue boxes)
    $('div.MDLabel[code]').each(function() {
        const $this = $(this)
        const mdc = getAttrVal($this, 'code');
        const label = getTranslation(lang, 'mdc-label', mdc)
        $this.text(label.value + ' (' + mdc + ')')
        toggleMissingClass($this, label.missing)
    })
}

function translateDataRcLangScoped(lang, selector) {
    $(selector).find('[data-rc-lang]').each(function() {
        const $this = $(this)
        const id = getAttrVal($this, 'data-rc-lang');
        const rawValues = getAttrVal($this, 'data-rc-lang-values');
        const values = rawValues ? JSON.parse(atob(rawValues)) : false
        const translation = getTranslation(lang, 'ui-translations', id)
        if (translation.value) {
            // @ts-ignore
            $this.html(values ? interpolateString(translation.value, values) : translation.value)
        }
        else if (config.debug) {
            warn('Missing (data-rc-lang) UI string: ' + id)
        }
        if (values) {
            // One level of nesting, in case a value brings in another data-rc-lang
            // This might be better solved by recursion, which would require to refactor this whole part
            $this.find('[data-rc-lang]').each(function() {
                const $this = $(this)
                const id = getAttrVal($this, 'data-rc-lang');
                const translation = getTranslation(lang, 'ui-translations', id)
                if (translation.value) {
                    // @ts-ignore
                    $this.html(translation.value)
                }
                else if (config.debug) {
                    warn('Missing (data-rc-lang) UI string: ' + id)
                }
            })
        }
        toggleMissingClass($this, translation.missing)
    })
    $(selector).find('[data-rc-lang-attrs]').each(function() {
        const $this = $(this)
        const attrs = getAttrVal($this, 'data-rc-lang-attrs').split(' ');
        let missing = false
        for (var i = 0; i < attrs.length; i++) {
            var attr = attrs[i];
            const kvPair = attr.split('=')
            const name = kvPair[0]
            const id = kvPair[1]
            const translation = getTranslation(lang, 'ui-translations', id)
            // TODO - interpolation for attributes? Need to rework how data-rc-lang-values are set - use "key1=aaencoded key2=aaencoded" as in data-rc-lang-attrs
            if (translation.value) {
                $this.attr(name, translation.value)
                missing = missing || translation.missing
            }
            else if (config.debug) {
                console.warn('Missing (data-rc-lang) UI string: ' + id)
            }
        }
        toggleMissingClass($this, missing)
    })
}

/**
 * Translates language strings (from Language.ini files)
 * @param {Language} lang
 */
function translateDataRcLang(lang) {
    // Translate in different parts of the page
    const scopes = isDataEntry ? DATAENTRY_RC_SELECTORS : SURVEY_RC_SELECTORS
    for (var i = 0; i < scopes.length; i++) {
        var scope = scopes[i];
        translateDataRcLangScoped(lang, scope)
    }
}

/**
 * Goes through all elements and records their text alignment setting in a data-mlm-text-align attribute
 */
function recordTextAlignments() {
    // Already run?
    if (textAlignmentRecorded) return;
    // Go through all elements on the page and record their text-alignment
    $('body, body *').each(function() {
        const $this = $(this);
        const tagName = this.tagName.toLowerCase();
        if (!['script','style','svg','polygon','text','i','em','img','input','textarea','select','option'].includes(tagName)) {
            const ta = $this.css('text-align');
            $this.attr('data-mlm-text-align', ta);
        }
    });
    // Set flag that this has already been done
    textAlignmentRecorded = true;
}

/**
 * Sets the directionality (dir attribute) for the container and overrides "text-align" CSS for nested
 * elements according to directionality and what was set before (either inherited or explicitly).
 * Note: This could/maybe should take into consideration if a field (subelement) actually has a translation
 * set in an RTL language and maybe preserve the directionality of the fallback/reference language.
 * @param {boolean} isRtl TRUE for a right-to-left language
 */
function setDirectionality(isRtl) {
    const selector = isDataEntry ? '#questiontable' : '#container';
    // Remove alignment on all elements inside the selector 
    // (except when explicitly set with text-align in the style attribute)
    $(selector + ' *').each(function() {
        const style = $(this).attr('style') ?? '';
        if (!style.includes('text-align')) $(this).css('text-align', 'unset');
    });
    // Set dir on container
    $(selector).attr('dir', isRtl ? 'rtl' : 'ltr');
    // Set piping receivers to 'auto' mode (we do not know, whether the received content is rtl or ltr)
    $(selector + ' .' + config.pipingReceiverClass).attr('dir', 'auto');
    // Set text alignment
    $(selector + ' [data-mlm-text-align]').each(function() {
        const $this = $(this);
        const ta = $this.attr('data-mlm-text-align');
        if (ta == 'left' || ta == 'start') {
            $this.css('text-align', isRtl ? 'right' : 'left');
        }
        else if (ta == 'right' || ta == 'end') {
            $this.css('text-align', isRtl ? 'left' : 'right');
        }
        else {
            // Restore center
            $this.css('text-align', 'center');
        }
    })
    // Fix sliders
    // Horizontal sliders - the slider labels must remain ltr
    const slidersH = selector + ' table.sliderlabels.left-horizontal, ' + selector + ' table.sliderlabels.right-horizontal';
    const slidersV = selector + ' table.sliderlabels.left-vertical, '   + selector + ' table.sliderlabels.right-vertical';
    $(slidersH).attr('dir', 'ltr').find('td').each(function() {
        const $this = $(this);
        const alignment = $this.attr('data-mlm-text-align');
        if (alignment) {
            $this.css('text-align', alignment);
        }
    })
    // Vertical sliders - the complete slider must remain ltr, as otherwise things break down
    $(slidersV).each(function() {
        const $this = $(this).parents('table.sldrparent');
        $this.attr('dir', 'ltr').find('[data-mlm-text-align]').each(function() {
            const $this = $(this);
            const alignment = $this.attr('data-mlm-text-align');
            if (alignment) {
                $this.css('text-align', alignment);
            }
        })
        $this.parents('tr[sq_id]').find('[data-kind=reset-link]').css('text-align', 'right');
    });
    // Fix floating matrix headers
    setFloatingMatrixHeaderDirectionality(isRtl);
}

function setFloatingMatrixHeaderDirectionality(isRtl) {
    $('.floatMtxHdr').each(function() {
        const $this = $(this);
        $this.attr('dir', isRtl ? 'rtl' : 'ltr');
        $this.css('padding-left', isRtl ? '0px' : $this.attr('data-padding-left') ?? '0px');
        if (isRtl) {
            $this.find('td.matrix_first_col_hdr').css('width', $this.attr('data-extra') ?? 'auto');
        }
        else {
            $this.find('td.matrix_first_col_hdr').css('width', '');
        }
    });
}

/**
 * Translates a survey page by identifying elements and replacing their contents
 * @param {Language} lang
 * @param {string} prevLang
 */
function updateSurveyPage(lang, prevLang) {
    // Record all text alignments
    recordTextAlignments();
    // Move all embedded fields out of the way
    saveEmbedded()
    // User interface (HTML)
    translateDataRcLang(lang)
    // User Interface (window.lang)
    translateGlobalLang(lang)
    // General (Survey Settings and other project-specific strings)
    translateDataMlm(lang)
    // Validation labels
    translateValidationLabels(lang)
    if (isDataEntry) { // Data Entry pages only
        // Form and Event names
        translateFormEventNames(lang)
        translateMissingDataCodes(lang)
    }
    // Fields
    const fieldsSelector = isDataEntry ? DATAENTRY_FIELD_SELECTOR : SURVEY_FIELD_SELECTOR
    $(fieldsSelector).each(function() {
        const $this = $(this);
        const fieldName = getAttrVal($this, 'data-mlm-field');
        // Ensure that a field name is present and that this field exists (in the referene data)
        if (fieldName == '' || !config.ref['field-translations'].hasOwnProperty(fieldName)) return;
        // Is the field excluded?
        if (config.excludedFields.hasOwnProperty(fieldName)) {
            return;
        }
        // Translate the field
        const type = getAttrVal($this, 'data-mlm-type');
        const index = getAttrVal($this, 'data-mlm-value');
        let translation = { value: '???', missing: true };
        let val = '';
        switch (type) {
            case 'placeholder':
                translation = getFieldTranslation(lang, fieldName, 'actiontag', '@PLACEHOLDER');
                $this.attr('placeholder', textOnly(translation.value));
                break;
            case 'enum':
                translation = getFieldTranslation(lang, fieldName, type, index);
                // Sanitize for <option>
                val = $this.is('option') ? sanitizeTextOnly(translation.value) : translation.value;
                $this.html(val);
                break;
            case 'video_url':
                translation = getFieldTranslation(lang, fieldName, type, index);
                val = translation.value;
                const unknownVideoService = $this.attr('data-mlm-unknown-video-service');
                // @ts-ignore - base.js
                const videoCustomHtml = html_entity_decode($this.attr('data-mlm-video-custom-html'));
                const height = Number.parseInt(ensureString($this.attr('data-mlm-video-height'), '0'));
                if (height) {
                    if (videoCustomHtml != '') {
                        $this.html(videoCustomHtml);
                    } else {
                        if (unknownVideoService == '1') {
                            if (isIOS && hasVideoExtension(val)) {
                                var $embed = $('<video width="100%" height="'+height+'" playsinline controls><source src="'+htmlspecialchars(val)+'" type="video/'+getfileextension(val.toLowerCase())+'"></source></video>');
                            } else {
                                var $embed = $('<embed width="100%" height="' + height + '" scale="aspect" controller="true" autostart="false" autostart="0"></embed>');
                                $embed.attr('src', val);
                            }
                        } else {
                            var $embed = $('<iframe type="text/html" frameborder="0" allowfullscreen width="100%" height="' + height + '"></iframe>');
                            $embed.attr('src', val);
                        }
                        $this.children().remove();
                        $this.append($embed);
                    }
                } else {
                    // @ts-ignore escapeHtml in base.js
                    $this.find('button').attr('onclick', 'openEmbedVideoDlg("' + val + '", ' + unknownVideoService + ', "' + fieldName + '", "' + escapeHtml(videoCustomHtml) + '");return false;');;
                }
                // @ts-ignore initVidYardJS in DataEntrySurveyCommon.js
                if (videoCustomHtml != '') initVidYardJS();
                break;
            default:
                translation = getFieldTranslation(lang, fieldName, type, index);
                // In case of field lablel, convert line breaks (\n) to <br>
                // @ts-ignore nl2br in base.js
                val = (type == 'label' || type == 'header') ? nl2br(translation.value) : translation.value;
                $this.html(val);
                break;
        }
        // Render smart charts
        $this.find('.rc-smart-chart canvas').each(function() {
            const id = $(this).attr('id') ?? '';
            if (id.startsWith('rc-smart-chart-')) {
                // Re-excute the script tag with id 'js-'+id
                eval($('#js-' + id).text());
            }
        });
        toggleMissingClass($this, translation.missing);
    })
    // Special case missing handling of dropdowns (regular and autocomplete)
    // As soon as there is at least one not-translated item, 
    // highlight the whole thing, otherwise make sure it's not highlighted
    $('select').each(function() {
        const $select = $(this)
        if ($select.find('option.'+ TRANSLATION_MISSING_CLASS).length) {
            $select.addClass(TRANSLATION_MISSING_CLASS)
            .filter('.rc-autocomplete')
            .parents('[data-kind=field-value]')
            .first()
            .find('input[role=combobox]')
            .addClass(TRANSLATION_MISSING_CLASS)
        }
        else {
            $select.removeClass(TRANSLATION_MISSING_CLASS)
            .filter('.rc-autocomplete')
            .parents('[data-kind=field-value]')
            .first()
            .find('input[role=combobox]')
            .removeClass(TRANSLATION_MISSING_CLASS)
        }
    })
    // Re-initialize date pickers
    const datePickerClasses = [
        '.date_ymd',
        '.date_dmy',
        '.date_mdy'
    ];
    const dateTimePickerClasses = [
        '.datetime_ymd',
        '.datetime_dmy',
        '.datetime_mdy',
        '.datetime_seconds_ymd',
        '.datetime_seconds_dmy',
        '.datetime_seconds_mdy'
    ];
    const timePickerClasses = [
        '.time2',
        '.time3'
    ];
    $(datePickerClasses.join(','))['datepicker']('destroy');
    $(dateTimePickerClasses.join(','))['datetimepicker']('destroy');
    $(timePickerClasses.join(','))['timepicker']('destroy');
    // @ts-ignore init_functions.php
    setDatePickerDefaults();
    // @ts-ignore base.js
    initDatePickers();
    setDatePickerTooltips();
    setTimeout(() => {
        // Weired fix for date pickers where the icon ends up BEFORE the input
        $('input.hasDatepicker').each(function() {
            $(this).prependTo($(this).parent());
        });
    }, 0);
    // Finalizations ...
    restoreEmbedded()
    applyLanguageCurrentActionTag()
    // applyDefaultActionTag(lang, config.langs[prevLang])
    // Reset auto complete dropdowns
    $('.rc-autocomplete-enabled').removeClass('rc-autocomplete-enabled')
    // @ts-ignore -- REDCap global function
    enableDropdownAutocomplete(false)
    // Trigger piping
    $('.' + config.pipingReceiverClass).each(function() {
        const $this = $(this)
        triggerPiping($this)
    })
    // For piping to be complete, trigger the change event of all transmitter fields
    // @ts-ignore --- global var
    const transmitters = typeof piping_transmitter_fields == 'undefined' ? [] : piping_transmitter_fields
    for (var i = 0; i < transmitters.length; i++) {
        var tf = transmitters[i];
        if (!tf.startsWith('[')) {
            triggerChange(tf)
        }
    }
    if (isDataEntry) { // Data Entry pages only
        // Need to do this again
        translateMissingDataCodes(lang)
    }
    if (!isDataEntry) {
        // Text-2-Speech
        const t2sMode = getSurveySetting(lang, 'survey-text_to_speech')
        const t2sEnabled = ['1','2'].includes(t2sMode)
        if (t2sEnabled) {
            $('#disable_text-to-speech, #enable_text-to-speech').removeClass('hide')
            if (isText2SpeechEnabled()) {
                // @ts-ignore --- Resources/js/Surveys.js
                addSpeakIconsToSurveyViaBtnClick(0);
                setTimeout(function(){
                    // @ts-ignore --- Resources/js/Surveys.js
                    addSpeakIconsToSurveyViaBtnClick(1);
                }, 0);
                $('#disable_text-to-speech').show()
                $('#enable_text-to-speech').hide()
            }
            else {
                $('#disable_text-to-speech').hide()
                $('#enable_text-to-speech').show()
            }
        }
        else {
            // @ts-ignore --- Resources/js/Surveys.js
            addSpeakIconsToSurveyViaBtnClick(0);
            $('#disable_text-to-speech, #enable_text-to-speech').addClass('hide')
        }
    }
    // Apply directionality (only if there are any RTL languages defined)
    if (config.hasRTL) {
        setDirectionality(lang.rtl)
    }
    // Replace consent form for e-Consent rich text or inline PDF (these are not defined via MLM Setup but on the e-Consent page)
    replaceConsentForm();
    // Init inline PDF divs
    initInlinePdfs();
    // Fix for iOS - data entry only - refresh form status select
    if (isDataEntry && window['isMobileDevice']) {
        const page = ensureString((new URL(window.location.href)).searchParams.get('page'), '');
        $('select[name="' + page + '_complete"]').hide().show(0);
    }
}

/**
 * Replace consent form for e-Consent rich text or inline PDF (these are not defined via MLM Setup but on the e-Consent page)
 */
function replaceConsentForm() {
    try {
        // If we are viewing a data entry form of a record that has already gone through e-consent, then stop here
        // (because we don't want to change the consent form - it needs to always display the consent form that the participant saw when consenting)
        if (!is_survey() && $('#consent-form-version-display').length) return;
        // If there is no consent form on the page or if no consent forms are defined in the project, then stop here
        if (!$('.consent-form-container').length || !config.langs[currentLanguage]['econsent-consent-form'].hasOwnProperty('inline-pdf-url')) return;
        // Get values
        const pdf_src = config.langs[currentLanguage]['econsent-consent-form']['inline-pdf-url'];
        const richtext = config.langs[currentLanguage]['econsent-consent-form']['rich-text'];
        // Replace content
        $('.consent-form-pdf .inline-pdf-resizer').remove();
        if (pdf_src != '') {
            $('.consent-form-container').html('<div class="consent-form-pdf"><div class="inline-pdf-viewer" src="' + pdf_src + '"></div></div>');
            $('.inline-pdf-viewer').each(function() { try { window['.REDCapFileEnhancements'].addResizer(this); } catch (ex) {} });
        } else {
            $('.consent-form-container').html('<div class="consent-form-richtext"><div class="consent-form-richtext">' + richtext + '</div></div>');
        }
    } catch(e) {}
    // Re-init the PDF viewer, if needed
    initInlinePdfs();
}

/**
 * Updates the tooltip of date/time pickers
 */
function setDatePickerTooltips() {
    $('input.hasDatepicker').each(function() {
        const field = $(this).attr('name') ?? '';
        const type = config.ref['field-translations'][field].validation;
        const $trigger = $(this).parent().find('.ui-datepicker-trigger');
        let key = 'calendar_widget_choosedatehint';
        if (type.startsWith('datetime_')) {
            key = 'calendar_widget_choosedatetimehint';
        }
        else if (type.startsWith('time_')) {
            key = 'calendar_widget_choosetimehint';
        }
        const title = getTranslation(config.langs[currentLanguage], 'ui-translations', key).value;
        $trigger.attr('alt', title).attr('title', title);
    });
}

/**
 * Gets a survey setting
 * @param {Language} lang
 * @param {string} setting
 */
function getSurveySetting(lang, setting) {
    let val = ''
    if (lang["survey-translations"].hasOwnProperty(setting)) {
        val = lang["survey-translations"][setting]
    }
    if (isEmpty(val)) {
        if (config.langs.hasOwnProperty(config.fallbackLang) &&
            config.langs[config.fallbackLang].hasOwnProperty('survey-translations') &&
            config.langs[config.fallbackLang]['survey-translations'].hasOwnProperty(setting)
           ) {
            val = config.langs[config.fallbackLang]['survey-translations'][setting]
        }
    }
    if (isEmpty(val)) {
        val = config.ref['survey-translations'][setting]
    }
    return val
}

/**
 * Checks whether a value is empty (null, empty string)
 * @param {any} val
 * @returns {boolean}
 */
function isEmpty(val) {
    return !((val != null) && (('' + val) != ''))
}

/**
 * Strips a potential HTML string of all tags
 * @param {string} html 
 * @returns {string}
 */
function textOnly(html) {
    return $('<div></div>').html(html).text()
}


//#region Translation Helpers

/**
 * Triggers change or blur on the given field if it is of a certain type (for piping)
 * @param {string} fieldName
 */
function triggerChange(fieldName) {
    try {
        const type = config.ref['field-translations'][fieldName].type
        switch (type) {
            case 'textarea':
                $('textarea[name="' + fieldName + '"]').trigger('blur')
                break
            case 'select':
            case 'sql':
                const selectVal = getFieldValue(fieldName)
                const selectLabel = getFieldTranslation(config.langs[currentLanguage], fieldName, 'enum', selectVal).value
                const $el = $('select[name="' + fieldName + '"]')
                // @ts-ignore -- DataEntrySurveyCommon.js
                updatePipingDropdownsDo($el, fieldName, selectVal, selectLabel)
                break
            case 'checkbox':
                $('input[name="__chkn__' + fieldName + '"]').first().trigger('change')
                break
            default:
                $('input[name="' + fieldName + '"]').trigger('change').trigger('blur')
                break
        }
        log('Triggered change/blur for "' + fieldName + '"')
    }
    catch(err) {
        console.warn('triggerChange(): Unknown field "' + fieldName + '"')
    }
}

/**
 * Gather info on the piping receiver field and perform piping operations
 * @param {JQuery<HTMLElement>} $el
 */
function triggerPiping($el) {
    var arr = $el[0].classList;
    for (var i = 0; i < arr.length; i++) {
        var className = arr[i];
        if (className.startsWith(config.pipingReceiverClassField)) {
            const parts = className.split('-')
            const eventId = Number.parseInt(parts[1])
            const fieldName = parts[2]
            const mod = parts[parts.length - 1]
            const instance = ensureString($el.attr('data-piperec-instance'), '1');
            // It might be that the field is not available or on a different event.
            // In such a case, skip the update (the value, if there is one, came
            // from PHP)
            if (eventId == config.eventId && config.ref['field-translations'][fieldName]) {
                performPiping(fieldName, parts, mod, instance)
            }
        }
    }
}

/**
 * Performs the piping operation by calling into various functions defined elsewhere or by
 * triggering the blur event on the respective input controls.
 * @param {string} fieldName
 * @param {Array} parts
 * @param {string} mod The modifier - 'value', 'label', 'link', 'inline', 'ampm'
 * @param {string} instance
 * @returns
 */
function performPiping(fieldName, parts, mod, instance) {
    const fieldInfo = config.ref['field-translations'][fieldName]
    if (fieldInfo.isPROMIS) return // Do not attempt to alter/update fields from PROMIS forms
    if (fieldInfo.piped && !fieldInfo.onPage) {
        // This is a piped field that has no input element on this page
        // For certain field types, we need to update the piped value directly
        const $span = $('.' + parts.join('-') + '[data-piperec-instance="' + instance + '"]')
        let val
        try {
            const attrVal = getAttrVal($span, 'data-piperec-value');
            const jsonVal = attrVal == '' ? '""' : atob(attrVal)
            val = JSON.parse(jsonVal)
        }
        catch (err) {
            warn('Piping error for field:', fieldName, err)
            val = null
        }
        const lang = config.langs[currentLanguage]
        let translation = config.pipingPlaceholder
        switch (fieldInfo.type) {
            case 'slider':
                // Simply use the value
                if (val != '') {
                    translation = val
                }
                $span.html(translation)
                break
            case 'radio':
            case 'yesno':
            case 'truefalse':
            case 'select':
                if (val != '') {
                    translation = mod == 'value' ? val : getFieldTranslation(lang, fieldName, 'enum', val).value
                }
                $span.html(translation)
                break
            case 'checkbox':
                // Only need to set when a label is shown
                if (mod == 'label') {
                    if (parts[3] == 'choice') {
                        // Individual - Show "Checked" (global_143) or "Unchecked" (global_144)
                        const index = parts[4]
                        const key = val[index] == '1' ? 'global_143' : 'global_144'
                        translation = getTranslation(lang, 'ui-translations', key).value
                    }
                    else {
                        const target = parts[4] == 'checked' ? '1' : '0'
                        const items = []
                        for (const code of Object.keys(val)) {
                            if (val[code] == target) {
                                items.push(getFieldTranslation(lang, fieldName, 'enum', code).value)
                            }
                        }
                        translation = items.length ? items.join(', ') : config.pipingPlaceholder
                    }
                    $span.html(translation)
                }
                break;
            case 'text':
            case 'textarea':
            case 'calc':
            case 'file':
            case 'sql':
                // No action required
                break
            default:
                console.warn('Unhandled field type:', fieldName, fieldInfo.type)
                break
        }
    }
    else {
        const val = getFieldValue(fieldName)
        switch (fieldInfo.type) {
            case 'text':
                const $text = $('input[type=text][name="' + fieldName + '"]')
                if ($text.hasClass('rci-calc2')) {
                    // @CALCTEXT or @CALCDATE
                    // @ts-ignore -- Classes/DataEntry.php
                    try { updateCalcPipingReceivers(); } catch (e) {}
                }
                else {
                    // Blur triggers REDCap's piping mechanism
                    $text.trigger('blur')
                }
                break
            case 'textarea':
                // Blur triggers REDCap's piping mechanism
                $('textarea[name="' + fieldName + '"]').trigger('blur')
                break
            case 'calc':
                // @ts-ignore -- Classes/DataEntry.php
                try { updateCalcPipingReceivers(); } catch (e) {}
                break
            case 'slider':
                // @ts-ignore -- base.js
                updatePipeReceivers(fieldName, parts[1], val)
                break
            case 'radio':
            case 'yesno':
            case 'truefalse':
                $('input[name="' + fieldName + '___radio"]:checked').trigger('click')
                break
            case 'select':
                const selectLabel = getFieldTranslation(config.langs[currentLanguage], fieldName, 'enum', val).value
                const $el = $('select[name="' + fieldName + '"]')
                // @ts-ignore -- DataEntrySurveyCommon.js
                updatePipingDropdownsDo($el, fieldName, val, selectLabel)
                break
            case 'sql':
                $('select[name="' + fieldName + '"]').trigger('change')
                break
            case 'checkbox':
                const $cb = $('input[name="__chkn__' + fieldName + '"]').first()
                // @ts-ignore -- DataEntrySurveyCommon.js
                updatePipingCheckboxes($cb)
                break
            case 'file':
                $('input[name="' + fieldName + '"]').trigger('change')
                break
            default:
                console.warn('Unhandled field type:', fieldName, fieldInfo.type)
                break
        }
    }
}

/**
 * Gets the value of a field (in case of checkboxes, always return '')
 * @param {string} fieldName
 * @returns {string}
 */
 function getFieldValue(fieldName) {
    const fieldInfo = config.ref['field-translations'][fieldName]
    const type = fieldInfo.piped ? 'piped' : fieldInfo.type
    let val = ''
    switch (type) {
        case 'piped':
            val = ensureString($('input[type=hidden][name="' + fieldName + '"]').val(), '');
            break
        case 'text':
        case 'slider':
        case 'calc':
            val = ensureString($('input[type=text][name="' + fieldName + '"]').val(), '');
            break
        case 'textarea':
            val = ensureString($('textarea[name="' + fieldName + '"]').val(), '');
            break
        case 'radio':
        case 'yesno':
        case 'truefalse':
            val = ensureString($('input.hiddenradio[name="'+ fieldName + '"]').val(), '');
            break
        case 'select':
        case 'sql':
            val = ensureString($('select[name="' + fieldName + '"]').find(":selected").attr('value'), '');
            break
        case 'checkbox':
            // Not needed
            break
        case 'file':
            val = ensureString($('input[name="' + fieldName + '"]').val(), '');
            break
        default:
            console.warn('getFieldValue(): Unhandled field type:', fieldName, type)
            break
    }
    return val
}

/**
 * Removes all HTML tags and gets the textual content
 * @param {string} html
 * @returns
 */
function sanitizeTextOnly(html) {
    const $div = $('<div></div>')
    $div.html(html)
    return $div.text()
}

/**
 * Sets default values
 * @param {Language} currLang
 * @param {Language} prevLang
 */
function applyDefaultActionTag(currLang, prevLang) {
    $('.\\@DEFAULT').each(function() {
        const $this = $(this)
        const fieldName = getAttrVal($this, 'sq_id');
        // @DEFAULT is only supported on text and textarea
        if (!['text', 'textarea'].includes(config.ref['field-translations'][fieldName].type)) return

        // Get translations for current and previous lang
        const currVal = getFieldTranslation(currLang, fieldName, 'actiontag', '@DEFAULT')
        const prevVal = getFieldTranslation(prevLang, fieldName, 'actiontag', '@DEFAULT')
        // If they are different, swap, but only if the field is set to the previous value
        if (currVal.missing || (currVal.value && currVal.value.length && currVal.value != prevVal.value)) {
            const $el = $this.find('input[name="' + fieldName + '"]')
            if ($el.val() == prevVal.value) {
                $el.val(currVal.value)
            }
            toggleMissingClass($el, currVal.missing)
            // @ts-ignore
            doBranching(fieldName)
        }
    })
}

/**
 * Sets fields marked with @LANGUAGE-CURRENT-FORM/SURVEY to the current language
 */
function applyLanguageCurrentActionTag() {
    // Disable on data entry pages of survey responses
    if ($('#form_response_header').length == 1) return;
    const atName = '\\@LANGUAGE-CURRENT-' + (isDataEntry ? 'FORM' : 'SURVEY')
    $('.' + atName).each(function() {
        const $this = $(this)
        const fieldName = $this.attr('sq_id')
        log('Setting ' + atName + ' for field \'' + fieldName + '\' to [ ' + currentLanguage + ' ]')
        // Set depending on type
        // Is it a text field?
        let $el = $this.find('input[type=text][name="' + fieldName + '"]')
        if ($el.length) {
            $el.val(currentLanguage).trigger('input');
            // @ts-ignore - Need to manually call doBranching
            doBranching();
            return
        }
        // Is it a radio field with enhanced buttons?
        if ($this.find('.enhancedchoice').length) {
            // Set enhanced cosmetics
            $this.find('div.enhancedchoice label').removeClass('selectedradio')
            $this.find('label[comps="' + fieldName + ',value,' + currentLanguage + '"]').addClass('selectedradio')
            // But do not return - drop down to radio, which will actually set the value
        }
        // Is it a radio field?
        $el = $this.find('input.hiddenradio[name="' + fieldName + '"]')
        if ($el.length) {
            // Check if the correct choice is available
            const $choice = $this.find('input#opt-' + fieldName + '_' + currentLanguage)
            if ($choice.length == 1) {
                $el.val(currentLanguage)
                $choice.prop('checked', true)
            }
            else {
                // Cannot set to actual, at least clear
                $el.val('')
                $this.find('input[type="radio"][name="' + fieldName + '___radio"]').prop('checked', false)
            }
            // @ts-ignore -- REDCap global function
            doBranching(fieldName)
            return
        }
        // Is it a dropdown field?
        $el = $this.find('select[name="' + fieldName + '"]')
        if ($el.length) {
            $el.val('')
            $el.val(currentLanguage)
            $el.trigger('change')
            return
        }
    })
}

/**
 * Add event handlers to fields marked with @LANGUAGE-SET/-FORM/-SURVEY and, if a value is set,
 * sets the cookie value.
 * @returns string
 */
function setupLanguageSetActionTag() {
    let targetLanguageOverride = '';
    const setup = function() {
        let $this = $(this);
        const fieldName = $this.attr('sq_id');
        // If embedded, switch selector to the embedded location
        if ($('.rc-field-embed[var="' + fieldName + '"]').length) {
            $this = $('.rc-field-embed[var="' + fieldName + '"]');
        }
        // Get the element
        let $el = $this.find('input.hiddenradio[name="' + fieldName + '"]');
        if (!$el.length) $el = $this.find('select[name="' + fieldName + '"]');
        if ($el.length) {
            // Setup handler (note: jQuery's change does not work on hidden fields)
            $this.on('change', function() {
                handleLanguageSetActionTag($el)
            })
            // Is there a (valid) value? 
            // If this is an active language, use it as the target language
            // (the active check is needed because it may be an inactive fallback language)
            const val = ensureString($el.val(), '');
            if (config.langs.hasOwnProperty(val) && config.langs[val].active) {
                targetLanguageOverride = val
            }
        }
        log('Setup @LANGUAGE-SET for field \'' + fieldName + '\'.')
    };
    $('.\\@LANGUAGE-SET').each(setup);
    if (isDataEntry) {
        $('.\\@LANGUAGE-SET-FORM').each(setup);
    }
    else {
        $('.\\@LANGUAGE-SET-SURVEY').each(setup);
    }
    return targetLanguageOverride
}

/**
 * Performs language switching after the value of a field with @LANGUAGE-SET changes
 * @param {JQuery<HTMLElement>} $el
 */
function handleLanguageSetActionTag($el) {
    const val = ensureString($el.val(), '');
    const valid = config.langs.hasOwnProperty(val)
    log('Handling @LANGUAGE-SET - target = [ ' + (valid ? val : '-invalid-') + ' ].')
    if (valid && currentLanguage != val) {
        switchLanguage(val, true, false, false)
    }
}

/**
 * Moves embedded fields to a (hidden) div for temporary storage while their parent field is translated.
 * There, they are seamlessly translated, before they are then restored to using restoreEmbedded().
 */
function saveEmbedded() {
    if (!$('#mlm-embed-repository').length) {
        // Create a hidden div that will hold the embedded fields
        $('body').append('<div id="mlm-embed-repository" style="display:none;"></div>')
    }
    const $repo = $('#mlm-embed-repository')
    $('.rc-field-embed').each(function() {
        const $this = $(this);
        // Find the parent field and check whether it is excluded from translation
        const embeddingFieldName = $this.parents('tr[sq_id]').attr('sq_id') ?? '';
        if (config.excludedFields[embeddingFieldName] || $this.attr('error') == '1') {
            // Skip
        }
        else {
            $this.removeClass('rc-field-embed').addClass('rc-field-embed-saved').appendTo($repo);
        }
    })
}

/**
 * Restores embedded fields to their proper positions in the DOM.
 */
function restoreEmbedded() {
    const $repo = $('#mlm-embed-repository');
    const $lang = window['lang'];
    $repo.find('.rc-field-embed-saved').each(function() {
        const $this = $(this);
        const fieldName = $this.attr('var') ?? '';
        const $target = $('.rc-field-embed[var="'+fieldName+'"]');
        const targetFieldName = $target.closest('[data-mlm-field]').attr('data-mlm-field') ?? '';
        const isMatrixField = (config.ref["field-translations"][targetFieldName]?.matrix ?? null) != null;
        // Only restore if there actually is a target
        let alreadyEmbedded = false;
        $target.each(function() {
            const $thisTarget = $(this);
            if (fieldName == config.ref.recordIdField) { // Cannot embed record id field
                $this.attr('error', '1').html('<span class="text-danger fs11 rc-field-embed-error"><i class="fas fa-exclamation-triangle"></i> '+$lang.design_799+' '+$lang.design_823+'</span>')
            }
            else if (alreadyEmbedded) {
                // Add an error message
                $thisTarget.attr('error','1').html('<span class="text-danger fs11 rc-field-embed-error"><i class="fas fa-exclamation-triangle"></i> '+$lang.design_799+' "'+fieldName+'" '+$lang.design_800+'</span>');
            }
            else {
                // Check for enhanced choice
                if ($thisTarget.parentsUntil('[data-kind="field-value"]').siblings('.enhancedchoice_wrapper').length == 0 || isMatrixField) {
                    // Restore original class
                    $this.removeClass('rc-field-embed-saved').addClass('rc-field-embed');
                    // Insert before the target
                    $thisTarget.before($this);
                    alreadyEmbedded = true;
                }
                // Remove the target
                $thisTarget.remove();
            }
        });
    })
}

/**
 * Adds or removes the highlighter class
 * @param {JQuery<HTMLElement>} $el
 * @param {boolean} missing
 */
function toggleMissingClass($el, missing) {
    if (missing && config.highlightMissing) {
        $el.addClass(TRANSLATION_MISSING_CLASS)
    }
    else {
        $el.removeClass(TRANSLATION_MISSING_CLASS)
    }
}

/**
 * Gets a field translation
 * @param {Language} lang
 * @param {string} fieldName
 * @param {string} type
 * @param {string} index
 * @returns {{ value: string, missing: boolean }}
 */
function getFieldTranslation(lang, fieldName, type, index) {
    type = 'field-' + type
    let markedComplete = false // keeps track whether the field was set to be complete - no highlight
    let ft = lang['field-translations']
    markedComplete = ft[fieldName].hasOwnProperty('field-complete')
    if (ft.hasOwnProperty(fieldName) && ft[fieldName] && ft[fieldName][type]) {
        let val = index == '' ? ft[fieldName][type] : ft[fieldName][type][index]
        if (val) {
            val = fixValue(val)
            return {
                value: val,
                missing: false,
            }
        }
    }
    // Check fallback (unless it's the reference language)
    if (lang.key != config.refLang) {
        ft = config.langs.hasOwnProperty(config.fallbackLang) ? config.langs[config.fallbackLang]['field-translations'] : {}
        if (ft.hasOwnProperty(fieldName) && ft[fieldName] && ft[fieldName][type]) {
            let val = index == '' ? ft[fieldName][type] : ft[fieldName][type][index]
            if (val) {
                val = fixValue(val)
                return {
                    value: val,
                    missing: !markedComplete
                }
            }
        }
    }
    // Use reference
    let val = index == ''
        ? config.ref['field-translations'][fieldName][type]
        : config.ref['field-translations'][fieldName][type][index]
    val = fixValue(val)
    const missing = lang.key != config.refLang
        && !(config.excludedFields[fieldName] == true)
        && !markedComplete
    return {
        value: val,
        missing: missing
    }
}

/**
 * Replaces line breaks with <br>, but not line breaks between HTML tags
 * TODO - this needs to be improved (best: eliminated - How?) --- THIS MAY BE OBSOLETE already!
 * @param {string} val
 * @returns
 */
function fixValue(val) {
    return val

    return val
        .replace(/>\s*\r\n\s*</g, "><")
        .replace(/>\s*\n\s*</g, '><')
        .replace(/\r\n/g, '\n')
        .replace(/\n/g, '<br>')
}


/**
 * Gets a UI translation
 * @param {Language} lang
 * @param {string} kind
 * @param {string} key
 * @returns {{ value: string, missing: boolean }}
 */
function getTranslation(lang, kind, key) {
    const origLangKey = lang.key
    const isRefLang = lang.key == config.refLang
    // Always let ui translations enter the if (to allow overriding of reference values)
    if (kind == 'ui-translations' || !isRefLang) {
        if (lang[kind].hasOwnProperty(key) && lang[kind][key]) {
            return {
                value: fixValue(lang[kind][key]),
                missing: false
            }
        }
        // Check fallback (but skip, if the language is the reference/default lang)
        lang = config.langs[config.fallbackLang]
        if (!isRefLang && lang && lang[kind].hasOwnProperty(key) && lang[kind][key]) {
            return {
                value: fixValue(lang[kind][key]),
                missing: true
            }
        }
    }
    // Use reference
    return {
        value: fixValue(config.ref[kind][key]),
        // Not missing when reference is empty
        missing: origLangKey != config.refLang && fixValue(config.ref[kind][key]) != ''
    }
}

/**
 * Gets a generic DD translation
 * @param {Language} lang
 * @param {string} kind
 * @param {string} name
 * @param {string} index
 * @returns {{ value: string, missing: boolean }}
 */
function getDDTranslation(lang, kind, name, index = '') {
    const origLangKey = lang.key
    const isRefLang = lang.key == config.refLang
    // Always let ui translations enter the if (to allow overriding of reference values)
    if (!isRefLang) {
        if (lang[kind].hasOwnProperty(name) && lang[kind][name].hasOwnProperty(index) && lang[kind][name][index]) {
            return {
                value: fixValue(lang[kind][name][index]),
                missing: false
            }
        }
        // Check fallback (but skip, if the language is the reference/default lang)
        lang = config.langs[config.fallbackLang]
        if (!isRefLang && lang && lang[kind].hasOwnProperty(name) && lang[kind][name].hasOwnProperty(index) && lang[kind][name][index]) {
            return {
                value: fixValue(lang[kind][name][index]),
                missing: true
            }
        }
    }
    // Use reference
    return {
        value: fixValue(config.ref[kind][name][index]),
        // Not missing when reference is empty
        missing: origLangKey != config.refLang && fixValue(config.ref[kind][name][index]) != ''
    }
}

//#endregion

//#endregion

//#region -- Ajax

/**
 * Sends an ajax request to the server
 * @param {string} action
 * @param {object} payload
 * @returns {Promise}
 */
 function ajax(action, payload) {
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

function getAttrVal($el, attrName) {
    const val = $el.attr(attrName);
    return ensureString(val, '');
}
/**
 * Ensures that the passed value is a string
 * @param {any} val 
 * @param {string} defaultVal The string returned when val is undefined or null
 * @returns {string}
 */
function ensureString(val, defaultVal) {
    if (typeof defaultVal == 'undefined' || typeof defaultVal != 'string') {
        defaultVal = '';
    }
    if (typeof val == 'undefined' || val === null) {
        return defaultVal;
    }
    return '' + val;
}

//#endregion

//#region -- Debug Logging

/**
 * Logs a message to the console when in debug mode
 */
 function log() {
    if (!config.debug) return
    var ln = '??'
    try {
        var line = ensureString((new Error).stack, '').split('\n')[2]
        var parts = line.split(':')
        ln = parts[parts.length - 2]
    }
    catch(err) { }
    log_print(ln, 'log', arguments)
}
/**
 * Logs a warning to the console when in debug mode
 */
function warn() {
    if (!config.debug) return
    var ln = '??'
    try {
        var line = ensureString((new Error).stack, '').split('\n')[2]
        var parts = line.split(':')
        ln = parts[parts.length - 2]
    }
    catch(err) { }
    log_print(ln, 'warn', arguments)
}

/**
 * Logs an error to the console when in debug mode
 */
function error() {
    var ln = '??'
    try {
        var line = ensureString((new Error).stack, '').split('\n')[2]
        var parts = line.split(':')
        ln = parts[parts.length - 2]
    }
    catch(err) { }
    log_print(ln, 'error', arguments)
}

/**
 * Prints to the console
 * @param {string} ln Line number where log was called from
 * @param {'log'|'warn'|'error'} mode
 * @param {IArguments} args
 */
function log_print(ln, mode, args) {
    var prompt = 'MultiLanguage v' + config.version + ' [' + ln + ']'
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

//#endregion

})();