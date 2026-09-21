var searchFields = ["#surveyinstructions","#form"];
var DescriptivePopups = {};
DescriptivePopups.doFieldEmbeddingCompleted = false;

DescriptivePopups.handleDescriptivePopups = function() {
    const params = new URLSearchParams(window.location.search);
    let _exit = false;
    let setup = function (mlmIsActive) {
        // check if we are on the Data Entry Form or on a Survey Page
        if ((page === 'DataEntry/index.php' && params.has('id')) || (page === 'surveys/index.php' && params.has('s'))) {
            // inline descriptive popups
            if (searchFields.some(field => $(field).length > 0)) {
                let data = dataAllPopups;
                data.forEach(function (popup) {
                    if (mlmIsActive) {
                        let translatedData = REDCap.MultiLanguage.getDescriptivePopupTranslation(popup.popup_id);
                        popup.inline_text = translatedData.inline_text.value;
                        popup.inline_text_popup_description = translatedData.inline_text_popup_description.value;
                    }
                    popup.first_occurrence_only = popup.first_occurrence_only == true;
                    popup.active_on_surveys = popup.active_on_surveys == true;
                    popup.active_on_data_entry_forms = popup.active_on_data_entry_forms == true;
                    processPopup(popup);
                });
            }
            _exit = true; // return because code below not meant for data entry form or survey
        } else if (page === 'Design/descriptive_popups.php' && params.has('pid') && !params.has('popid') && !params.has('add_new')) {
            $('.tbl_list_btn_view_popup_summary').on('click', function() {
                var popupId = $(this).data('popup-id');
                fetchPopupSummary(popupId);
            });
            _exit = true;
        }
    };
    if (typeof window.REDCap != 'undefined' &&
        typeof window.REDCap.MultiLanguage != 'undefined' &&
        typeof window.REDCap.MultiLanguage.isInitialized != 'undefined') {
        REDCap.MultiLanguage.onLangChanged(function () {
            setup(true);
        });
    }
    setup(false);
    if (_exit) {
        return;
    }

    let formsSelect = document.getElementById('formsSelect');
    initTinyMCEglobal('descriptive_popup_text', false, false);
    setTimeout(function() {
        var tinymceContainers = document.querySelectorAll('.tox-tinymce');
        tinymceContainers.forEach(function(container) {
            container.style.height = '300px'; // for all tinyMCE containers on the page, we reduce the container's height down to 300px
        });
        $(document).click(function (e) {
            if (!$(e.target).closest('.descriptive_popup_text').length) {
                let editArea = document.querySelector('.tox-edit-area');
                if (editArea) {
                    editArea.classList.remove('rich_text_border_style');
                }
            }
        });
    }, 500);

    $('[data-action="save-popup"]').click(function() {
        let formData = {};
        let formIsValid = true;
        const popid = (new URLSearchParams(window.location.search)).get('popid');
        formData['popup_id'] = popid ? popid : '0';
        $('#descriptivePopupForm').find('input, select, textarea').each(function() {
            let settingName = $(this).attr('name');
            if (settingName) {
                if ($(this).attr('name') === 'list_instruments') {
                    formData[settingName] = JSON.stringify(Array.from(formsSelect.options)
                        .filter(option => option.selected)
                        .map(option => option.value));
                } else if ($(this).attr('name') === 'list_survey_pages') {
                    let pageNumbersText = document.getElementById('pageNumbers').value;
                    let lines = pageNumbersText.split('\n');
                    let listSurveyPages = {};
                    lines.forEach(function(line, index) {
                        if (line.trim().length > 0) {
                            let parts = line.split(':');
                            if (parts.length !== 2) {
                                simpleDialog("Formatting error on line " + (index + 1) + ". Please ensure each line contains exactly one colon.", lang.alerts_24);
                                formIsValid = false;
                                return;
                            }
                            let formName = parts[0].trim();
                            let pages = parts[1].trim().split(',').map(function(page) {
                                return page.trim();
                            });
                            if (pages[0] !== '' && !pages.every(page => page !== '' && !isNaN(page))) {
                                simpleDialog("Page number error on line " + (index + 1) + ". Please ensure all pages are numeric.", lang.alerts_24);
                                formIsValid = false;
                                return;
                            }
                            listSurveyPages[formName] = pages;
                        }
                    });
                    formData['list_survey_pages'] = JSON.stringify(listSurveyPages);
                } else {
                    let value;
                    if ($(this).attr('type') === 'checkbox') {
                        value = $(this).prop('checked')
                    } else {
                        value = $(this).val();
                    }
                    formData[settingName] = (typeof value === 'string') ? value.trim() : value;
                }
            }
        });
        if (!formIsValid) {
            return;
        }
        let hexLinkColor = formData.hex_link_color;
        let inlineText = filter_tags(formData.inline_text);
        let popupDescription = filter_tags(formData.inline_text_popup_description.trim());
        let activeOnSurveys = formData.active_on_surveys;
        let activeOnDataEntryForms = formData.active_on_data_entry_forms;
        let firstOccurrenceOnly = formData.first_occurrence_only;
        if (inlineText.trim() === '') {
            document.getElementById('linkTextRequired').style.display = 'block';
            return;
        } else if (popupDescription.replace(/<\/?p>/g, '').trim() === '') {
            document.getElementById('popupDescriptionRequired').style.display = 'block';
            return;
        }
        let truncatedDescription = '<div style="border:1px solid #ddd;padding:2px 4px;margin:2px 0;">'+popupDescription+'</div>';
        const popupSummary = `
            <div class="fs14 mb-2"><strong>Link Text:</strong> <span style="color: ${hexLinkColor};">${inlineText}</span></div>
            <strong>Popup Description:</strong> ${truncatedDescription}
            <strong>Active on Surveys:</strong> ${activeOnSurveys ? 'Yes' : 'No'}<br>
            <strong>Active on Data Entry Forms:</strong> ${activeOnDataEntryForms ? 'Yes' : 'No'}<br>
            <strong>First Occurrence Only:</strong> ${firstOccurrenceOnly ? 'Yes' : 'No'}
        `;

        simpleDialog(popupSummary, lang.descriptive_popups_37, null, 600, null, lang.global_53, triggerAjax, lang.report_builder_28);

        function triggerAjax() {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response && response.popup_id) {
                        var url = new URL(window.location.href);
                        url.searchParams.delete('add_new');
                        window.location.href = url.toString();
                    } else {
                        simpleDialog(response.errors.join('<br>'), lang.alerts_24);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error:", error);
                }
            });
        }
    });
    updatePageNumbersTextArea();
};

function processPopup(popup) {
    let activeInCurrentContext = false;
    if (!is_survey()) {
        activeInCurrentContext = popup.list_instruments.length === 0 || (popup.list_instruments.length !== 0 && popup.list_instruments.includes(currentInstrument));
        activeInCurrentContext = activeInCurrentContext && popup.active_on_data_entry_forms;
    } else {
        activeInCurrentContext = popup.list_instruments.length === 0 || ((popup.list_instruments.length !== 0 && popup.list_instruments.includes(currentSurveyPage)) && (popup.list_survey_pages.length !== 0 && popup.list_survey_pages[currentSurveyPage] && (popup.list_survey_pages[currentSurveyPage][0] === "" || popup.list_survey_pages[currentSurveyPage].includes(currentSurveyPageNumber))));
        activeInCurrentContext = activeInCurrentContext && popup.active_on_surveys;
    }
    // check if current page is relevant for the popup
    if (activeInCurrentContext) {
        const checkFieldEmbedding = setInterval(() => {
            if (DescriptivePopups.doFieldEmbeddingCompleted === true) {
                clearInterval(checkFieldEmbedding);
                findAndReplaceText(popup);
            }
        }, 100);
    }
}

function findAndReplaceText(popup) {

    popup.inline_text = strip_tags(popup.inline_text);
    var flags = "i";
    if (!popup.first_occurrence_only) {
        flags += 'g';
    }
    var findString = new RegExp("([^a-zA-Z]|^)(" + escapeRegExp(popup.inline_text) + ")([^a-zA-Z]|$)", flags);

    searchFields.forEach(function(field) {
        var currentItem = document.querySelector(field);
        if (currentItem) {
            var nodeIterator = document.createNodeIterator(
                currentItem,
                NodeFilter.SHOW_TEXT,
                function(node) {
                    var isVisible = $(node.parentNode).is(':visible');
                    var isEmpty = node.textContent.trim() === '';
                    var isScript = node.parentNode.nodeName === 'SCRIPT';
                    var isDropDown = node.parentNode.nodeName === 'OPTION';
                    var firstMatchOnlyAndInvisible = popup.first_occurrence_only && !isVisible;

                    if (isEmpty || isScript || firstMatchOnlyAndInvisible || isDropDown) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                },
                false
            );

            var nodes = [];
            var buttonNodes = new Map();
            var labelNodes = new Map();
            var node;

            while (node = nodeIterator.nextNode()) {
                nodes.push(node);
                // check for any button nodes
                let currentNode = node;
                while (currentNode && currentNode.nodeName !== 'BUTTON' && currentNode.nodeName !== 'LABEL') {
                    currentNode = currentNode.parentNode;
                }
                if (currentNode) {
                    if (currentNode.nodeName === 'BUTTON') {
                        buttonNodes.set(node, currentNode);
                    } else if (currentNode.nodeName === 'LABEL') {
                        labelNodes.set(node, currentNode);
                    }
                }
            }

            for (const node of nodes) {
                if (!shouldSkip(node)) {
                    var fontSize = $(node.parentNode).css('font-size');
                    var style = `font-size: ${fontSize}; color: ${popup.hex_link_color}; cursor: help;`;

                    var buttonNode = buttonNodes.get(node);
                    // var labelNode = labelNodes.get(node);

                    let stripHtml = function (htmlContent) {
                        let tempElement = document.createElement("div");
                        tempElement.innerHTML = htmlContent;
                        return tempElement.innerText || tempElement.textContent;
                    };
                    if (node.textContent.match(findString)) {
                        // Regular replacement
                        var styleAttribute = buttonNode ? `style='color:inherit;'` : `style='${style}'`; // We are not changing color of text inside button because it could lead to really poor contrast
                        var replaceString = `$1<a popup='${popup.popup_id}' href='javascript:void(0)' data-link-text='${escapeHtml(popup.inline_text_popup_description)}' ${styleAttribute}>$2</a>$3`;
                        var newContent = node.textContent.replace(findString, replaceString);
                        $(node).before($('<span>').html(newContent));
                        $(node).remove();

                        let popupAnchor = $(`a[popup='${popup.popup_id}']`);
                        let popupWrapperSpan = popupAnchor.closest("span");
                        // First remove all
                        popupAnchor.nextAll('.visually-hidden').remove();
                        // Then make sure there is only one left
                        popupWrapperSpan.append(
                            `<span class='visually-hidden'>${stripHtml(popup.inline_text)}: ${stripHtml(popup.inline_text_popup_description)}</span>`
                        );
                        // break out of the loop if only the first match should be replaced
                        if (popup.first_occurrence_only) {
                            break;
                        }
                    }
                }
            }
        }
    });

    $('a[popup]').each(function() {
        var link = this;
        var linkText = $(link).data('link-text');

        $(link).click((e) => {
            const label = $(link).parentsUntil('label.mc').parent();
            if (label.length) {
                label.click();
                return false;
            }
            const enhancedChoice = e.target.closest('.enhancedchoice');
            if (enhancedChoice) {
                enhancedChoice.querySelector('label').click();
                return false;
            }
        });

        tippy(link, {
            content: linkText,
            allowHTML: true,
            trigger: 'mouseenter',
            hideOnClick: false,
            theme: 'inline_descriptive_popup_theme',
            arrow: true,
            interactive: true,
            placement: 'bottom',
            onShow(instance) {
                const computedStyle = window.getComputedStyle(instance.reference);
            },
            onHide() {
                // console.log('popup closed', linkText, popupIndex);
            }
        });
    });
}

function shouldSkip(node) {
    var parent = node.parentNode;
    if (!parent) {
        return false;
    } else if (['A', 'TEXTAREA'].includes(parent.tagName)) {
        return true;
    } else {
        return shouldSkip(parent);
    }
}

function escapeRegExp(text) {
    return text.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&');
}

let formsPagesCache = null;

function updatePageNumbersTextArea() {
    let formsSelect = document.getElementById('formsSelect');
    let surveyPagesTextarea = document.getElementById('pageNumbers');
    let currentLines = surveyPagesTextarea.value.split('\n');

    let existingFormsPages = currentLines.reduce((result, line) => {
        let parts = line.split(':');
        if (parts.length === 2) {
            let formName = parts[0].trim();
            result[formName] = parts[1].trim();
        }
        return result;
    }, {});
    // cache only original values
    if (formsPagesCache === null) {
        formsPagesCache = existingFormsPages;
    }

    let newLines = [];
    for (var i = 0; i < formsSelect.options.length; i++) {
        let option = formsSelect.options[i];
        if (option.selected && option.value) {
            let formName = option.value.trim();
            let pages = formsPagesCache[formName] ? formsPagesCache[formName] : '';
            newLines.push(formName + ':' + pages);
        }
    }
    surveyPagesTextarea.value = newLines.join('\n');
}

function dismissErrorMssg(element) {
    element.parentNode.style.display = 'none';
}

function deletePopup(element) {
    let popupId = element.id.replace('tbl_list_btn_del_popup_', '');
    function triggerAjax() {
        $.ajax({
            url: app_path_webroot + 'index.php?pid=' + pid + '&route=DescriptivePopupsController:deletePopup',
            type: 'GET',
            data: { popup_id: popupId },
            success: function(response) {
                response = JSON.parse(response);
                if (response && response.success) {
                    window.location.reload(true);
                } else {
                    simpleDialog(response.errors.join('<br>'), lang.alerts_24);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error:", error);
            }
        });
    }
    simpleDialog(lang.descriptive_popups_36, lang.design_654, null, null, null, lang.global_53, triggerAjax, lang.design_397);
}

function fetchPopupSummary(popupId) {
    $('#popupSummaryContent').html('');
    $('#popupSummaryLoadingIndicator').show();
    $.ajax({
        url: app_path_webroot + 'index.php?pid=' + pid + '&route=DescriptivePopupsController:getPopupSummary',
        type: 'GET',
        data: { popup_id: popupId },
        dataType: 'json',
        success: function(response) {
            if(response && response.html) {
                $('#popupSummaryLoadingIndicator').hide();
                $('#popupSummaryContent').html(response.html);
                $('#popupSummaryModal').modal('show');
            } else {
                simpleDialog(response.errors.join('<br>'), lang.alerts_24);
            }
        },
        error: function(xhr, status, error) {
            console.error("Error:", error);
        }
    });
}

// Change default behavior of the multi-select boxes so that they are more intuitive to users when selecting/de-selecting options
$(function() {
    if (page == 'Design/descriptive_popups.php') {
        $("#formsSelect").each(function(){
            modifyMultiSelect($(this), 'ms-selection');
        });
        $("#formsSelect option").click(function(){
            setTimeout("updatePageNumbersTextArea()", 50);
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    DescriptivePopups.handleDescriptivePopups();
});