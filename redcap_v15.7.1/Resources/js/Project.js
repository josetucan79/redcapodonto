var survey_pid_move_to_prod_status_record = '';
var survey_pid_move_to_analysis_status_record = '';
var survey_pid_mark_completed_record = '';
var selectedRevId = '';

$(function(){
    // Impersonate user
    $('#impersonate-user-select').change(function(){
        $.post(app_path_webroot+"index.php?route=UserRightsController:impersonateUser&pid="+pid, { user: $(this).val() },function(data){
            if (data == '0') {
                alert(woops);
                return false;
            }
            simpleDialog(data,null,null,500,function(){
                showProgress(1);
                window.location.reload();
            });
        });
    });
    // DAG Switch
    $('#dag-switcher-change-button').click(function(e) {
        simpleDialog(null,null,'dag-switcher-change-dialog',500,null,lang.global_53,function(){
            showProgress(1);
            var dagname = $('#dag-switcher-change-select option:selected').text();
            $.post(app_path_webroot+'index.php?route=DataAccessGroupsController:switchDag&pid='+pid, { dag: $('#dag-switcher-change-select').val() }, function(data){
                showProgress(0,0);
                if (data != '1') alert(data);
                try {
                    Swal.fire(
                        lang.data_access_groups_17+'<br>"'+dagname+'"',
                        '',
                        'success'
                    );
                    setTimeout('window.location.reload();', 1500);
                } catch(e) {
                    alert(lang.data_access_groups_17+'<br>"'+dagname+'"');
                }
            });
        },lang.data_access_groups_15);
    });
    $('#dag-switcher-change-button-span[data-toggle="popover"]').popover();
    // Record-level locking
    $('#record_lock_pdf_confirm_checkbox').click(function(){
        if ($(this).prop('checked')) {
            $('#record_lock_pdf_confirm_checkbox_div').removeClass('yellow').addClass('green');
            $('#recordLockPdfConfirmDialog').parent().find('.ui-dialog-buttonpane button:eq(1)').prop('disabled',false).removeClass('opacity50');
        } else {
            $('#record_lock_pdf_confirm_checkbox_div').removeClass('green').addClass('yellow');
            $('#recordLockPdfConfirmDialog').parent().find('.ui-dialog-buttonpane button:eq(1)').prop('disabled',true).addClass('opacity50');
        }
    });
    // Add ALT text to all images that lack it
    $('img:not([alt])').on('load', function() {
        var alt = '';
        if (typeof $(this).prop('href') != 'undefined' && $(this).prop('href').indexOf('help.png') > -1) {
            alt = 'Help';
        }
        $(this).attr('alt', alt);
    }).each(function() {
        if (this.complete) $(this).trigger('load');
    });
    // Move any hidden field search boxes to end of page for compatibility with modal dialogs
    initLogicSuggestSearchTip();

    if (removeMyCapEMLink == 1) {
        var frm = $('#external-modules-disabled-modal').find('.modal-body form');
        frm.bind('DOMSubtreeModified', function(){
            frm.find("#external-modules-disabled-table tr").each(function() {
                if ($(this).attr('data-module') == 'mycap') {
                    $(this).remove();
                    frm.bind('DOMSubtreeModified');
                }
            });
        });
    }
});

// Opens pop-up for sending Send-It files on forms and in File Repository
function popupSendIt(doc_id,loc) {
    var spid = (typeof pid == 'undefined') ? "" : "&spid="+pid;
    window.open(app_path_webroot+'index.php?route=SendItController:upload&loc='+loc+'&id='+doc_id+spid,'sendit','width=900, height=700, toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1');
}

function showEv(day_num) {
    document.getElementById('hiddenlink'+day_num).style.display = 'none';
    document.getElementById('hidden'+day_num).style.display = 'block';
}

var REDCap = {
    richTextFieldLabelPrefix: '<div class="rich-text-field-label">',
    richTextFieldLabelSuffix: '</div>',

    getFieldLabelSelector: function() {
        return '#field_label';
    },

    removeFieldLabelTinyMCE: function() {

        tinymce.remove(REDCap.getFieldLabelSelector());

        var field = REDCap.getFieldLabel();

        // Remove newlines so REDCap doesn't replace them with <br> tags.
        field.val(field.val().split('\n').join(' '));

        // Set the text color back to normal to undo the hacky way of preventing users from seeing the raw HTML during save.
        field.css('color', 'inherit');
    },

    initTinyMCEFieldLabel: function(isPreInit) {

        if(isPreInit){
            // The following allows TinyMCE's internal dialogs to work (like when adding links).
            // It was copied from here: https://stackoverflow.com/questions/18111582/tinymce-4-links-plugin-modal-in-not-editable
            $(document).on('focusin', function(e) {
                if ($(e.target).closest(".mce-window").length) {
                    e.stopImmediatePropagation();
                }
            });
        } else {
            // Convert existing line breaks to <br> tags
            var field = REDCap.getFieldLabel();
            field.val(nl2br(field.val()));
        }

        if (typeof tinymce == 'undefined') loadJS(app_path_webroot+"Resources/webpack/css/tinymce/tinymce.min.js");
        var imageuploadIcon = rich_text_image_embed_enabled ? 'image' : ' ';
        var fileuploadIcon = rich_text_attachment_embed_enabled ? 'fileupload' : ' ';
        var fileimageicons = trim(imageuploadIcon + ' ' + fileuploadIcon);
        var openaiIcon = openAIImproveTextServiceEnabled ? ' openai' : '';
        tinymce.init({
            license_key: 'gpl',
            font_family_formats: 'Open Sans=Open Sans; Andale Mono=andale mono,times; Arial=arial,helvetica,sans-serif; Arial Black=arial black,avant garde; Book Antiqua=book antiqua,palatino; Comic Sans MS=comic sans ms,sans-serif; Courier New=courier new,courier; Georgia=georgia,palatino; Helvetica=helvetica; Impact=impact,chicago; Symbol=symbol; Tahoma=tahoma,arial,helvetica,sans-serif; Terminal=terminal,monaco; Times New Roman=times new roman,times; Trebuchet MS=trebuchet ms,geneva; Verdana=verdana,geneva; Webdings=webdings; Wingdings=wingdings,zapf dingbats',
            promotion: false,
            entity_encoding : "raw",
            default_link_target: '_blank',
            selector: REDCap.getFieldLabelSelector(),
            height: 350,
            branding: false,
            statusbar: true,
            menubar: false,
            elementpath: false, // Hide this, since it oddly renders below the textarea.
            plugins: 'autolink lists link image searchreplace code fullscreen table directionality hr',
            toolbar1: 'fontfamily blocks fontsize bold italic underline strikethrough forecolor backcolor',
            toolbar2: 'align bullist numlist outdent indent table pre hr link '+fileimageicons+' fullscreen searchreplace removeformat undo redo code'+openaiIcon,
            contextmenu: "copy paste | link image inserttable | cell row column deletetable",
            content_css: app_path_webroot + "Resources/webpack/css/bootstrap.min.css," + app_path_webroot + "Resources/webpack/css/fontawesome/css/all.min.css,"+app_path_webroot+"Resources/css/style.css",
            relative_urls: false,
            convert_urls : false,
            extended_valid_elements: 'i[class]',
            paste_postprocess: function(plugin, args) {
                args.node.innerHTML = cleanHTML(args.node.innerHTML);
            },
            remove_linebreaks: true,
            content_style: 'body { font-weight: bold; }', // Match REDCap's default bold label style.
            formats: {
                bold: {
                    inline: 'span',
                    styles: {
                        'font-weight': 'normal'  // Make the 'bold' option function like an 'unbold' instead.
                    }
                }
            },
            setup: function (editor) {
                // Add file attachment button to toolbar
                editor.ui.registry.addIcon('paper-clip-custom', '<svg height="20" width="20" viewBox="0 0 512 512"><path d="M396.2 83.8c-24.4-24.4-64-24.4-88.4 0l-184 184c-42.1 42.1-42.1 110.3 0 152.4s110.3 42.1 152.4 0l152-152c10.9-10.9 28.7-10.9 39.6 0s10.9 28.7 0 39.6l-152 152c-64 64-167.6 64-231.6 0s-64-167.6 0-231.6l184-184c46.3-46.3 121.3-46.3 167.6 0s46.3 121.3 0 167.6l-176 176c-28.6 28.6-75 28.6-103.6 0s-28.6-75 0-103.6l144-144c10.9-10.9 28.7-10.9 39.6 0s10.9 28.7 0 39.6l-144 144c-6.7 6.7-6.7 17.7 0 24.4s17.7 6.7 24.4 0l176-176c24.4-24.4 24.4-64 0-88.4z"/></svg>');
                editor.ui.registry.addButton('fileupload', { icon: 'paper-clip-custom', tooltip: 'Attach a file', onAction: function(){ rich_text_attachment_dialog(); } });
                // Add pre/code button to toolbar
                editor.ui.registry.addIcon('preformatted-custom', '<svg height="20" width="20" viewBox="0 0 640 512"><path d="M392.8 1.2c-17-4.9-34.7 5-39.6 22l-128 448c-4.9 17 5 34.7 22 39.6s34.7-5 39.6-22l128-448c4.9-17-5-34.7-22-39.6zm80.6 120.1c-12.5 12.5-12.5 32.8 0 45.3L562.7 256l-89.4 89.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l112-112c12.5-12.5 12.5-32.8 0-45.3l-112-112c-12.5-12.5-32.8-12.5-45.3 0zm-306.7 0c-12.5-12.5-32.8-12.5-45.3 0l-112 112c-12.5 12.5-12.5 32.8 0 45.3l112 112c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256l89.4-89.4c12.5-12.5 12.5-32.8 0-45.3z"/></svg>');
                editor.ui.registry.addButton('pre', { icon: 'preformatted-custom', tooltip: 'Preformatted code block', onAction: function(){ editor.insertContent('<pre>'+tinymce.activeEditor.selection.getContent()+'</pre>'); } });
                if (openAIImproveTextServiceEnabled) {
                    editor.ui.registry.addIcon('openai-improve-text', '<svg height="20" width="20" viewBox="0 0 640 512"><path fill="#eb03eb" d="M464 6.1c9.5-8.5 24-8.1 33 .9l8 8c9 9 9.4 23.5 .9 33l-85.8 95.9c-2.6 2.9-4.1 6.7-4.1 10.7l0 21.4c0 8.8-7.2 16-16 16l-15.8 0c-4.6 0-8.9 1.9-11.9 5.3L100.7 500.9C94.3 508 85.3 512 75.8 512c-8.8 0-17.3-3.5-23.5-9.8L9.7 459.7C3.5 453.4 0 445 0 436.2c0-9.5 4-18.5 11.1-24.8l111.6-99.8c3.4-3 5.3-7.4 5.3-11.9l0-27.6c0-8.8 7.2-16 16-16l34.6 0c3.9 0 7.7-1.5 10.7-4.1L464 6.1zM432 288c3.6 0 6.7 2.4 7.7 5.8l14.8 51.7 51.7 14.8c3.4 1 5.8 4.1 5.8 7.7s-2.4 6.7-5.8 7.7l-51.7 14.8-14.8 51.7c-1 3.4-4.1 5.8-7.7 5.8s-6.7-2.4-7.7-5.8l-14.8-51.7-51.7-14.8c-3.4-1-5.8-4.1-5.8-7.7s2.4-6.7 5.8-7.7l51.7-14.8 14.8-51.7c1-3.4 4.1-5.8 7.7-5.8zM87.7 69.8l14.8 51.7 51.7 14.8c3.4 1 5.8 4.1 5.8 7.7s-2.4 6.7-5.8 7.7l-51.7 14.8L87.7 218.2c-1 3.4-4.1 5.8-7.7 5.8s-6.7-2.4-7.7-5.8L57.5 166.5 5.8 151.7c-3.4-1-5.8-4.1-5.8-7.7s2.4-6.7 5.8-7.7l51.7-14.8L72.3 69.8c1-3.4 4.1-5.8 7.7-5.8s6.7 2.4 7.7 5.8zM208 0c3.7 0 6.9 2.5 7.8 6.1l6.8 27.3 27.3 6.8c3.6 .9 6.1 4.1 6.1 7.8s-2.5 6.9-6.1 7.8l-27.3 6.8-6.8 27.3c-.9 3.6-4.1 6.1-7.8 6.1s-6.9-2.5-7.8-6.1l-6.8-27.3-27.3-6.8c-3.6-.9-6.1-4.1-6.1-7.8s2.5-6.9 6.1-7.8l27.3-6.8 6.8-27.3c.9-3.6 4.1-6.1 7.8-6.1z"/></svg>');
                    editor.ui.registry.addButton('openai', { icon: 'openai-improve-text', tooltip: lang.openai_001, onAction: function(){  openImproveTextByAIPopup(editor.id);return false; } });
                }
            },
            // Embedded image uploading
            file_picker_types: 'image',
            images_upload_handler: rich_text_image_upload_handler,
            browser_spellcheck : true
        });
    },

    toggleFieldLabelRichText: function(enabled) {
        if(enabled === undefined){
            enabled = REDCap.isFieldLabelRichTextChecked();
        }
        else{
            REDCap.getFieldLabelRichTextCheckbox().prop('checked', enabled);
        }

        if(enabled){
            REDCap.initTinyMCEFieldLabel(false);
        }
        else{
            REDCap.removeFieldLabelTinyMCE();
        }
    },

    getFieldLabel: function() {
        return $(REDCap.getFieldLabelSelector());
    },

    initFieldLabel: function(value) {
        var prefix = REDCap.richTextFieldLabelPrefix;
        var suffix = REDCap.richTextFieldLabelSuffix;

        value = value.slice(prefix.length, -suffix.length);

        // If TinyMCE was previously initialized, remove it.
        // This has no effect if it wasn't previously initialized.
        // This is required for the the val() call below to correctly set the textarea value.
        REDCap.removeFieldLabelTinyMCE();

        REDCap.getFieldLabel().val(value);
        REDCap.toggleFieldLabelRichText(true);
    },

    isRichTextFieldLabel: function(value) {
        return value.indexOf(REDCap.richTextFieldLabelPrefix) === 0;
    },

    getFieldLabelRichTextCheckbox: function() {
        return $('#field_label_rich_text_checkbox');
    },

    isFieldLabelRichTextChecked: function() {
        return REDCap.getFieldLabelRichTextCheckbox().is(':checked');
    },

    beforeAddFieldFormSubmit: function() {
        if(REDCap.isFieldLabelRichTextChecked()){
            // Remove TinyMCE in order to remove newlines that REDCap would replace with <br> tags.
            // This also allows us to interact with the textarea directly below.
            REDCap.removeFieldLabelTinyMCE();

            var field = REDCap.getFieldLabel();
            field.val(REDCap.richTextFieldLabelPrefix + field.val() + REDCap.richTextFieldLabelSuffix);

            // Hack to prevent the user from seeing the raw html while the field is saving.
            field.css('color', 'white');
        }
    }
}

// Popup to explain Data Collection Strategies for Repeating Surveys
function repeatingSurveyExplainPopup() {
    $.get(app_path_webroot+"Design/repeating_asi_explain.php"+(isProjectPage ? "?pid="+pid : ""), { },function(data){
        var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
        if (json_data.length < 1) {
            alert(woops);
            return false;
        }
        simpleDialog(json_data.content,json_data.title,'repeating_asi_explain_popup',1100);
        fitDialog($('#repeating_asi_explain_popup'));
    });
}

// Popup to explain Smart Variables
function smartVariableExplainPopup() {
    const url = new URL(app_path_webroot_full+'redcap_v'+redcap_version+'/Design/smart_variable_explain.php');
    if (typeof super_user != 'undefined' && super_user == 1) url.searchParams.append('su', '1');
    if (isProjectPage) url.searchParams.append('pid', pid);
    $.get(url.toString(), { },function(data){
        var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
        if (json_data.length < 1) {
            alert(woops);
            return false;
        }
        simpleDialog(json_data.content,json_data.title,'smart_variable_explain_popup',1000);
        fitDialog($('#smart_variable_explain_popup'));
    });
}

// Popup to explain Action Tags
function actionTagExplainPopup(hideBtns) {
    $.post(app_path_webroot+"Design/action_tag_explain.php?pid="+pid, { hideBtns: hideBtns },function(data){
        var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
        if (json_data.length < 1) {
            alert(woops);
            return false;
        }
        simpleDialog(json_data.content,json_data.title,'action_tag_explain_popup',1000);
        fitDialog($('#action_tag_explain_popup'));
    });
}

// Move any hidden field search boxes to end of page for compatibility with modal dialogs
function initLogicSuggestSearchTip()
{
    if ($('.fs-item-parent:not(.fs-item-parent-moved)').length) {
        $('.fs-item-parent').each(function(){
            $(this).addClass('fs-item-parent-moved');
            $(document.body).append($(this).detach());
        });
    }
}

var logicSuggestAjax = null;
var logicFieldSuggestLastKeyDown = null;
function logicSuggestSearchTip(ob, event, longitudinalthis, draft_mode, forceMetadataTable) {
    // Force it to look at the metadata table only (instead of metadata temp if in prod draft mode)?
    if (typeof forceMetadataTable == "undefined") {
        forceMetadataTable = 1;
    }
    if (typeof longitudinalthis != "undefined") {
        var longitudinal = longitudinalthis;
    }
    if (typeof draft_mode == "undefined") {
        draft_mode = false;
    }
    draft_mode = (draft_mode) ? 1 : 0;
    var res_ob_id = $(ob).prop('id') + "_res";
    if ($("#"+res_ob_id))
    {
        $("#"+res_ob_id).html("");
        var sel_id = "logicTesterRecordDropdown";
        if ($("#"+sel_id))
        {
            $("#"+sel_id).val("");
        }
    }

    // Do preliminary validation via JS then full validation via PHP/AJAX
    logicValidate(ob, longitudinal, forceMetadataTable);

    // If these keys are hit, abort, so user can keep working
    // ascii codes http://unixpapa.com/js/key.html
    // backspace keyCode === 8
    // [ keyCode === 219
    var stopKeyCodes = new Array(13, 32, 33, 61, 60, 62, 91, 123);
    if (in_array(logicFieldSuggestLastKeyDown, stopKeyCodes)) {
        logicSuggestHidetip($(ob).prop('id'));  // hide the tips
        return; // since one of these disabled keys was pressed, we abort, so user can keep working
    }

    // Is the element an iframe?
    var isIframe = ($(ob).prop("tagName").toLowerCase() == 'iframe');

    var text = isIframe ? strip_tags($(ob).contents().find('body').html()) : $(ob).val();
    var word = "";
    if (text.indexOf(' ') >= 0) {
        word = text.split(' ').pop();
    } else {
        word = text;  // If there are no spaces, then use the word since it's first
    }
    if (trim(word) == "") return;

    // timeout to let new text value to enter field;
    // just to back of queue to process
    setTimeout(function() {
        var word = "";
        if (text.indexOf(' ') >= 0) {
            word = text.split(' ').pop();
        } else {
            word = text;  // If there are no spaces, then use the word since it's first
        }

        var location;
        if ($(ob).prop('id') == "") {
            re = /^([a-zA-Z0-9_-])+$/; // letters, numbers, and underscores only
            if (!re.test($(ob).prop('name'))) return;
            location = "textarea[name='"+$(ob).prop('name')+"']";  // since we can't get the name of the id, we're gonna get the name of the name= in the textarea
        } else {
            re = /^([a-zA-Z0-9_-])+$/; // letters, numbers, and underscores only
            if (!re.test($(ob).prop('id'))) return;
            location = '#'+$(ob).prop('id');  // get name of id
        }

        var location_plain = location.replace(/^\#/, "");
        var elems = $(".fs-item");
        for (var i=0; i < elems.length; i++)
        {
            if (elems[i].id && (elems[i].id.match(/^LSC_id_/)) && (!elems[i].id.match(location_plain)))
            {
                $("#"+elems[i].id).hide();
            }
        }

        var elem = $("#LSC_id_"+location_plain);

        if (!elem.length) return;

        // If there are spaces then grab the last word and change the value of 'text' to be equal to the last word

        // Now that we have the word we want to autocomplete, let's run some tests
        // If the last word is a space, then abort
        if (trim(word) == '') {
            logicSuggestHidetip($(ob).prop('id'));  // hide the tips
            return;
        }

        // If there's a left bracket in the word, that means we want to autocomplete it
        if ((word.indexOf('[') >= 0) && (!word.match(/\]\[[^\]^\s]+\]\[/)))
        {
            // Kill previous ajax instance (if running from previous keystroke)
            if (logicSuggestAjax !== null) {
                if (logicSuggestAjax.readyState == 1) logicSuggestAjax.abort();
            }
            // Ajax request
            logicSuggestAjax = $.post(app_path_webroot+'Design/logic_field_suggest.php?pid='+pid, { draft_mode: draft_mode, location: location_plain, word: word.substring(1,word.length)  }, function(data){
                // Position the element
                elem.html(data)
                    .show();
                elem.position({
                    my:        "left top",
                    at:        "left bottom",
                    of:        ($('#rc-ace-editor:visible').length ? $('#rc-ace-editor') : $(location)),
                    collision: "fit"
                });
                // If nothing returned, then hide the suggest box
                if (data == '') logicSuggestHidetip($(ob).prop('id'));
            });
        } else {
            logicSuggestHidetip($(ob).prop('id')); // There is not a left bracket in the word, so hide the box
        }
    }, 0);
}

// event and field are only applicable to calc fields; can be blank for branching
function logicCheck(logic_ob, type, longitudinal, field, rec, mssg, err_mssg, invalid, action, logic_ob_id_opt)
{
    var logic_ob_id = $(logic_ob).prop('id');
    if (!logic_ob_id)
        logic_ob_id = logic_ob_id_opt;
    if (rec !== "")
    {
        setTimeout(function() {
            var res_ob_id = logic_ob_id+"_res";
            if (!checkLogicErrors($(logic_ob).val(), false, longitudinal))
            {
                var page = "";
                var page = getParameterByName("page");
                if (type == "branching")
                    page = "Design/logic_test_record.php";
                else if (type == "calc")
                    page = "Design/logic_calc_test_record.php";
                var logic = $(logic_ob).val();
                var hasrecordevent = ($(logic_ob).attr('hasrecordevent') == '1') ? 1 : 0;
                if ($("#"+res_ob_id))
                {
                    $.post(app_path_webroot+page+"?pid="+pid, { hasrecordevent: hasrecordevent, record: rec, logic: logic }, function(data) {
                        if (data !== "")
                        {
                            if (data.match("ERROR"))
                            {
                                $("#"+res_ob_id).html(data);
                            }
                            else if (typeof mssg != "undefined")
                            {
                                if (data.toString().match(/hide/i))
                                    $("#"+res_ob_id).html(mssg+" "+action[1]);
                                else if (data.toString().match(/show/i))
                                    $("#"+res_ob_id).html(mssg+" "+action[0]);
                                else
                                    $("#"+res_ob_id).html(mssg+" "+data.toString());
                            }
                            else
                            {
                                $("#"+res_ob_id).html(data.toString());
                            }
                        }
                        else
                        {
                            $("#"+res_ob_id).html("["+action[2]+"]");
                        }
                    });
                }
            }
            else
            {
                $("#"+res_ob_id).html(invalid);
            }
        }, 0);
    }
}

function showInstrumentsToggle(ob,collapse) {
    var targetid = 'show-instruments-toggle';
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=DataEntryController:saveShowInstrumentsToggle',{ object: 'sidebar', targetid: targetid, collapse: collapse },function(data){
        if (data == '0') { alert(woops);return; }
        if (collapse == 0) {
            $('.rc-form-menu-item').removeClass('hidden');
        } else {
            $('.rc-form-menu-item').addClass('hidden');
        }
        $('a.show-instruments-toggle').removeClass('hidden');
        $(ob).addClass('hidden');
    });
}


function logicValidate(ob, longitudinal, forceMetadataTable, preCheckResult = null) {
    // Force it to look at the metadata table only (instead of metadata temp if in prod draft mode)?
    if (typeof forceMetadataTable == "undefined") {
        forceMetadataTable = 1;
    }
    const mssg = '<span class="logicValidatorOkay"><img src="'+app_path_images+'tick_small.png">'+window.lang.design_718+'<span>'+window.lang.global_172+'</span></span>';
    const err_mssg = "<span class='logicValidatorOkay'><img style='position:relative;top:-1px;margin-right:4px;' src='"+app_path_images+"cross_small2.png'>"+window.lang.global_171+'<span>'+window.lang.global_172+'</span></span>';

    const ob_id = $(ob).prop('id');
    const confirm_ob_id = ob_id + "_Ok";
    const confirm_ob = "#"+confirm_ob_id;

    // Helper function to update result
    const setResult = function(isValid) {
        if (isValid == '1') {
            $(confirm_ob).css({"color": "green"}).html(mssg);
            $('#rc-ace-editor-status:visible').css({"color": "green"}).html($(confirm_ob).html());
        } else {
            $(confirm_ob).css({"color": "red"}).html(err_mssg);
            $('#rc-ace-editor-status:visible').css({"color": "red"}).html($(confirm_ob).html());
        }
    };

    // Pre-checked?
    if (preCheckResult !== null) {
        setResult(preCheckResult);
        return;
    }

    // timeout to let new text value to enter field;
    // just to back of queue to process
    setTimeout(function() {
        var logic = trim($(ob).val());
        var b = checkLogicErrors(logic, false, longitudinal);
        if ($(confirm_ob))
        {
            if (b || logic == '') {   // obvious errors or nothing
                $(confirm_ob).html("");
                // If ACE Editor is being used, also update it
                $('#rc-ace-editor-status:visible').html($(confirm_ob).html());
            } else {
                // If logic ends with any of these strings, then don't display OK or ERROR (to prevent confusion mid-condition)
                var allowedEndings = new Array(' and', ' or', '=', '>', '<');
                for (var i=0; i<allowedEndings.length; i++) {
                    if (ends_with(logic, allowedEndings[i])) {
                        $(confirm_ob).html("");
                        // If ACE Editor is being used, also update it
                        $('#rc-ace-editor-status:visible').html($(confirm_ob).html());
                        return;
                    }
                }
                // Kill previous ajax instance (if running from previous keystroke)
                if (logicSuggestAjax !== null) {
                    if (logicSuggestAjax.readyState == 1) logicSuggestAjax.abort();
                }
                // Check via AJAX if logic is really true
                logicSuggestAjax = $.post(app_path_webroot+'Design/logic_validate.php?pid='+pid, { logic: logic, forceMetadataTable: forceMetadataTable }, setResult);
            }
        }
    }, 100);
}


function logicSuggestClick(text, location) {
    re = /^([a-zA-Z0-9_-])+$/; // letters, numbers, and underscores only
    if (!re.test(location)) return;
    // Is the element an iframe?
    var isIframe = ($("#"+location).prop("tagName").toLowerCase() == 'iframe');

    if (isIframe) {
        // TinyMCE Editor
        var originalText = $("#"+location).contents().find('body').html();
        var originalTextNoTags = strip_tags(originalText);
        var lastLeftBracket = originalText.lastIndexOf("[");
        var lastLeftBracketNoTags = originalTextNoTags.lastIndexOf("[");
        var originalTextA = originalText.substring(0, lastLeftBracket);
        var originalTextB = originalText.substring(lastLeftBracket);
        var Match = originalTextNoTags.substring(lastLeftBracketNoTags);
        try {
            tinyMCE.activeEditor.setContent(originalTextA + text + originalTextB.substring(Match.length));
        } catch(e) { }
    } else {
        var originalText = $("#"+location).val();
        var lastLeftBracket = originalText.lastIndexOf("[");
        $("#" + location).val(originalText.substring(0, lastLeftBracket) + text);

        // Rerun the validation
        logicValidate($("#"+location), true);

        // must disable any additional checking in onblur before resetting focus
        var onblur_ev = $("#"+location).attr("onblur");
        $("#"+location).removeAttr("onblur");
        setTimeout(function() {
            $("#"+location).attr("onblur", onblur_ev);
            $("#"+location).focus();
        }, 100);
    }
    logicSuggestHidetip(location);  // hide the tips
}

function logicSuggestHidetip(location) {
    re = /^([a-zA-Z0-9_-])+$/; // letters, numbers, and underscores only
    if (!re.test(location)) return;
    $("#LSC_id_"+location).hide();
    var elems = $(".fs-item");
    for (var i=0; i < elems.length; i++)
    {
        if (elems[i].id && ((elems[i].id.match("LSC_fn_"+location+"_")) || (elems[i].id.match("LSC_ev_"+location+"_"))))
        {
            $("#"+CSS.escape(elems[i].id)).hide();
        }
    }
}

function logicSuggestShowtip(location) {
    re = /^([a-zA-Z0-9_-])+$/; // letters, numbers, and underscores only
    if (!re.test(location)) return;
    var elems = $(".fs-item");
    $("#LSC_id_"+location).show();
    $("#LSC_id_"+location).css({ position: "absolute", zIndex: "1000000" });
    for (var i=0; i < elems.length; i++)
    {
        if (elems[i].id && ((elems[i].id.match("LSC_fn_"+location+"_")) || (elems[i].id.match("LSC_ev_"+location+"_"))))
        {
            $("#"+CSS.escape(elems[i].id)).show();
        }
    }
}

function logicHideSearchTip(ob) {
    var location = $(ob).prop('id');  // get name of id
    re = /^([a-zA-Z0-9_-])+$/; // letters, numbers, and underscores only
    if (!re.test(location)) return;
    if (document.getElementById("LSC_id_"+location))
    {
        $("#LSC_id_"+location).hide();
    }
}

// Validate the Automated Survey Invitation logic
function validate_auto_invite_logic(ob,evalOnSuccess) {
    var dfd = $.Deferred();
    // Get logic as value of object passed
    var logic = ob.val();
    // First, make sure that the logic is not blank
    if (trim(logic).length < 1) return dfd.resolve(true);
    // Make ajax request to check the logic via PHP
    $.post(app_path_webroot+'Surveys/automated_invitations_check_logic.php?pid='+pid, { logic: logic }, function(data){
        if (data == '0') {
            alert(woops);
            dfd.reject(data);
        } else if (data == '1') {
            // Success
            dfd.resolve(data);
            if (evalOnSuccess != null) eval(evalOnSuccess);
        } else {
            // Error msg - problems in logic to fix
            simpleDialog(data);
            dfd.reject(data);
        }
    });
    return dfd;
}

// Create a new DD snapshot via AJAX
function createDataDictionarySnapshot() {
    $.post(app_path_webroot+'Design/data_dictionary_snapshot.php?pid='+pid,{},function(data){
        if (data == '0') {
            alert(woops);
        } else {
            $('#dd_snapshot_btn').attr('disabled','disabled').addClass('opacity65');
            $('#last_dd_snapshot_ts').html(data);
            $('#dd_snapshot_btn img:first').prop('src',app_path_images+'tick.png');
            $('#last_dd_snapshot').effect('highlight',{},3000);
        }
    });
}

function cancelRequest(pid,reqName,ui_id){
    areYouSure(function(res){
        if(res === 'yes'){
            $.post(app_path_webroot+'ToDoList/todo_list_ajax.php',
                { action: 'delete-request', pid: pid, ui_id: ui_id, req_type: reqName },
                function(data){
                    if (data == '1'){
                        if (reqName == 'move to prod') {
                            window.location.href = app_path_webroot+page+'?pid='+pid;
                        } else {
                            window.location.reload();
                        }
                    }
                });
        }
    });
}

// Change default behavior of the multi-select boxes so that they are more intuitive to users when selecting/de-selecting options
function modifyMultiSelect(multiselect_jquery_object, option_css_class) {
    if (option_css_class == null) option_css_class = 'ms-selection';
    // Add classes to options in case some are already pre-selected on page load
    multiselect_jquery_object.find('option:selected').addClass(option_css_class);
    // Set click trigger to add class to whichever option is clicked and then manually select it
    multiselect_jquery_object.click(function(event){
        var obparent = $(this);
        var ob = obparent.find('option[value="'+event.target.value+'"]');
        if (!ob.hasClass(option_css_class)) {
            ob.addClass(option_css_class);
        } else {
            ob.removeClass(option_css_class);
        }
        $('option:not(.'+option_css_class+')', obparent).prop('selected', false);
        $('option.'+option_css_class, obparent).prop('selected', true);
    });
}

// Load ajax call into dialog to analyze a survey for use as SMS/Voice Call survey
function dialogTwilioAnalyzeSurveys() {
    $.post(app_path_webroot+'Surveys/twilio_analyze_surveys.php?pid='+pid, { }, function(data){
        var json_data = JSON.parse(data);
        if (json_data.length < 1) {
            alert(woops);
            return false;
        }
        var dlg_id = 'tas_dlg';
        $('#'+dlg_id).remove();
        initDialog(dlg_id);
        $('#'+dlg_id).html(json_data.popupContent);
        simpleDialog(null,json_data.popupTitle,dlg_id,700);
    });
}

// AJAX call to regenerate API token
function regenerateToken() {
    $.post(app_path_webroot + "API/project_api_ajax.php?pid="+pid,{ action: "regenToken" },function (data) {
        simpleDialog(data);
        $.get(app_path_webroot + "API/project_api_ajax.php",{ action: 'getToken', pid: pid },function(data) {
            $("#apiTokenId").val(data).effect('highlight',{},2000);
        });
    });
}

// AJAX call to delete API token
function deleteToken() {
    $.post(app_path_webroot + "API/project_api_ajax.php?pid="+pid,{ action: "deleteToken" },function (data) {
        simpleDialog(data,null,null,400,function(){
            if (page == 'MobileApp/index.php') {
                window.location.reload();
            }
        });
        $.get(app_path_webroot + "API/project_api_ajax.php",{ action: 'getToken', pid: pid },function(data) {
            if (page != 'MobileApp/index.php') {
                if (data.length == 0) {
                    $("#apiReqBoxId").show();
                    $("#apiTokenBoxId").hide();
                    $("#apiTokenId, #apiTokenUsersId").html("");
                } else {
                    $("#apiTokenId").html(data);
                }
            }
        });
    });
}

// AJAX call to request API token from admin
function requestToken(autoApprove, mobileAppOnly) {
    $.post(app_path_webroot +'API/project_api_ajax.php?pid='+pid,{ action: 'requestToken', mobileAppOnly: mobileAppOnly },function (data) {
        if (autoApprove == '1' || super_user || AUTOMATE_ALL == '1') {
            window.location.reload();
        } else {
            $('.chklistbtn .jqbuttonmed, .yellow .jqbuttonmed').prop('disabled', true)
                .addClass('api-req-pending')
                .css('color','grey');
            $('.api-req-pending').parent().append('<p class="api-req-pending-text">Request pending</p>');
            simpleDialog(data);
            if($('.mobile-token-alert-text').length != 0){
                $('.mobile-token-alert-text').remove();
            }else{
                $('.chklistbtn .api-req-pending').text('Request Api token');
                $('api-req-pending span, .mobile-token-alert-text').remove();
            }
        }
    });
}

// Display explanation dialog for survey participant's invitation delivery preference
function deliveryPrefExplain() {
    // Get content via ajax
    $.get(app_path_webroot+'Surveys/delivery_preference_explain.php',{ pid: pid }, function(data){
        if (data == "") {
            alert(woops);
        } else {
            // Decode JSON
            var json_data = JSON.parse(data);
            simpleDialog(json_data.content, json_data.title, null, 600);
        }
    });
}

// Survey Reminder related setup
function initSurveyReminderSettings() {
    // Option up reminder options
    $('#enable_reminders_chk').click(function(){
        if ($(this).prop('checked')) {
            $('#reminders_text1').show();
            $('#reminders_choices_div').show('fade',function(){
                // Try to reposition each dialog (depending on which page we're on)
                if ($('#emailPart').length) {
                    fitDialog($('#emailPart'));
                    $('#emailPart').dialog('option','position','center');
                }
                if ($('#popupSetUpCondInvites').length) {
                    fitDialog($('#popupSetUpCondInvites'));
                    $('#popupSetUpCondInvites').dialog('option','position','center');
                }
                if ($('#inviteFollowupSurvey').length) {
                    fitDialog($('#inviteFollowupSurvey'));
                    $('#inviteFollowupSurvey').dialog('option','position','center');
                }
            });
        } else {
            $('#reminders_text1').hide();
            $('#reminders_choices_div').hide('fade',{ },200);
        }
    });
    // Disable recurrence option if using exact time reminder
    $('#reminders_choices_div input[name="reminder_type"]').change(function(){
        if ($(this).val() == 'EXACT_TIME') {
            $('#reminders_choices_div select[name="reminder_num"]').val('1').prop('disabled', true);
        } else {
            $('#reminders_choices_div select[name="reminder_num"]').prop('disabled', false);
        }
    });
    // Enable exact time reminder's datetime picker
    $('#reminders_choices_div .reminderdt').datetimepicker({
        onClose: function(dateText, inst){ $('#'+$(inst).attr('id')).blur(); },
        buttonText: 'Click to select a date', yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
        hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
        showOn: 'button', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'HH:mm', constrainInput: false
    });
}

// Validate the surveys reminders options
function validateSurveyRemindersOptions() {
    // If not using reminders, return true to skip it
    if (!$('#enable_reminders_chk').prop('checked')) return true;
    // Is reminder option chosen?
    var reminder_type = $('#reminders_choices_div input[name="reminder_type"]:checked').val();
    if ((reminder_type == 'NEXT_OCCURRENCE' && ($('#reminders_choices_div select[name="reminder_nextday_type"]').val() == ''
        || $('#reminders_choices_div input[name="reminder_nexttime"]').val() == ''))
        || (reminder_type == 'TIME_LAG' && $('#reminders_choices_div input[name="reminder_timelag_days"]').val() == ''
            && $('#reminders_choices_div input[name="reminder_timelag_hours"]').val() == ''
            && $('#reminders_choices_div input[name="reminder_timelag_minutes"]').val() == '')
        || (reminder_type == 'EXACT_TIME' && $('#reminders_choices_div input[name="reminder_exact_time"]').val() == '')
        || reminder_type == null)
    {
        // Get fieldset title
        var reminder_title = $('#reminders_choices_div').parents('fieldset:first').find('legend:first').html();
        // Display error msg
        simpleDialog("<div style='color:#C00000;font-size:13px;'><img src='"+app_path_images+"exclamation.png'> ERROR: If you are enabling reminders, please make sure all reminder choices are selected. One or more options are not entered/selected.</div>", reminder_title, null, 400);
        return false;
    }
    return true;
}

// Generate a survey Quick code and QR code and open dialog window
function getAccessCode(hash,shortCode) {
    // Id of dialog
    var dlgid = 'genQSC_dialog';
    // Get short code?
    if (shortCode != '1') shortCode = 0;
    // Show progres icon for short code generation
    if (shortCode) $('#gen_short_access_code_img').show();
    // Get content via ajax
    $.post(app_path_webroot+'Surveys/get_access_code.php?pid='+pid+'&hash='+hash+'&shortCode='+shortCode,{ }, function(data){
        if (data == "0") {
            alert(woops);
            return;
        }
        // Decode JSON
        var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
        // Put short code in input box
        if (shortCode) {
            $('#short_access_code_expire').html(json_data.expiration);
            $('#short_access_code').val(json_data.code);
            $('#short_access_code_div').show().effect('highlight',{},2000);
            $('#gen_short_access_code_div').hide();
        } else {
            // Add html
            initDialog(dlgid);
            $('#'+dlgid).html(json_data.content);
            // If QR codes are not being displayed, then make the dialog less wide
            var dwidth = ($('#'+dlgid+' #qrcode-info').length) ? 800 : 600;
            // Display dialog
            $('#'+dlgid).dialog({ title: json_data.title, bgiframe: true, modal: true, width: dwidth, open:function(){ fitDialog(this); }, close:function(){ $(this).dialog('destroy'); },
                buttons: [{
                    text: lang.calendar_popup_01, click: function(){ $(this).dialog('close'); }
                }, {
                    text: lang.survey_1610, click: function(){
                        window.open(app_path_webroot+'ProjectGeneral/print_page.php?pid='+pid+'&action=accesscode&hash='+hash,'myWin','width=850, height=600, toolbar=0, menubar=1, location=0, status=0, scrollbars=1, resizable=1');
                    }
                }]
            });
            $('#'+dlgid).parent().find('div.ui-dialog-buttonpane button:eq(1)').css({'font-weight':'bold','color':'#222'});
            // Init buttons
            initButtonWidgets();
        }
    });
}

// On data export page, display/hide the Send-It option for each export type
function displaySendItExportFile(doc_id) {
    $('#sendit_'+doc_id+' div').each(function(){
        if ($(this).css('visibility') == 'hidden') $(this).hide();
    });
    $('#sendit_'+doc_id).toggle('blind',{},'fast');
}

// Initialize a "fake" drop-down list (like a button to reveal a "drop-down" list)
function showBtnDropdownList(ob,event,list_div_id) {
    // Prevent $(window).click() from hiding this
    try {
        event.stopPropagation();
    } catch(err) {
        window.event.cancelBubble=true;
    }
    // Set drop-down div object
    var ddDiv = $('#'+list_div_id);
    // If drop-down is already visible, then hide it and stop here
    if (ddDiv.css('display') != 'none') {
        ddDiv.hide();
        return;
    }
    // Set width
    if (ddDiv.css('display') != 'none') {
        var ebtnw = $(ob).width();
        var eddw  = ddDiv.width();
        if (eddw < ebtnw) ddDiv.width( ebtnw );
    }
    // Set position
    var btnPos = $(ob).offset();
    ddDiv.show().offset({ left: btnPos.left, top: (btnPos.top+$(ob).outerHeight()) });
}

// Display DDP explanation dialog
function ddpExplainDialog(fhir, pid=null) {
    initDialog('ddpExplainDialog');
    var dialogHtml = $('#ddpExplainDialog').html();
    if (dialogHtml.length > 0) {
        $('#ddpExplainDialog').dialog('open');
    } else {
        const params = []
        if(fhir == '1') params.push('type=fhir');
        if(pid) params.push('pid='+pid);
        const queryParams = '?'+params.join('&')
        $.get(app_path_webroot+'DynamicDataPull/info.php'+queryParams,{ },function(data) {
            var json_data = JSON.parse(data);
            $('#ddpExplainDialog').html(json_data.content).dialog({ bgiframe: true, modal: true, width: 750, title: json_data.title,
                open: function(){ fitDialog(this); },
                buttons: {
                    Close: function() { $(this).dialog('close'); }
                }
            });
        });
    }
}

// Display Piping explanation dialog pop-up
function pipingExplanation() {
    // Get content via ajax
    $.get(app_path_webroot+'DataEntry/piping_explanation.php'+(isProjectPage ? "?pid="+pid : ""),{},function(data){
        var json_data = JSON.parse(data);
        simpleDialog(json_data.content,json_data.title,'piping_explain_popup',900);
        fitDialog($('#piping_explain_popup'));
    });
}

// Display explanation dialog pop-up for Field Embedding
function fieldEmbeddingExplanation() {
    // Get content via ajax
    $.get(app_path_webroot+'DataEntry/field_embedding_explanation.php'+(isProjectPage ? "?pid="+pid : ""),{},function(data){
        var json_data = JSON.parse(data);
        simpleDialog(json_data.content,json_data.title,'field_embed_explain_popup',900);
        fitDialog($('#field_embed_explain_popup'));
    });
}

// Display explanation dialog pop-up for Special Functions
function specialFunctionsExplanation() {
    // Get content via ajax
    $.get(app_path_webroot+'DataEntry/special_functions_explanation.php'+(isProjectPage ? "?pid="+pid : ""),{},function(data){
        var json_data = JSON.parse(data);
        simpleDialog(json_data.content,json_data.title,'special_functions_explain_popup',900);
        fitDialog($('#special_functions_explain_popup'));
    });
}

// Removes comments from logic expressions.
// Comments must start with // and lines with comments must only contain comments or whitespace and comments.
function removeComments(str) {
    var line, strClean="";
    var lines = str.split("\n");
    for (var i=0; i<lines.length; i++) {
        line = trim(lines[i]);
        if (!(starts_with(line, "//") || starts_with(line, "#"))) {
            if (i > 0) strClean += "\n";
            strClean += line;
        }
    }
    return strClean;
}

// Do quick check if logic errors exist in string (not very extensive)
// - used for both Data Quality and Automated Survey Invitations
function checkLogicErrors(brStr,display_alert,forceEventNotationForLongitudinal) {
    var brErr = false;
    if (display_alert == null) display_alert = false;
    // If forceEventNotationForLongitudinal=true, then make sure that field_names are preceded with [event_name] for longitudinal projects
    if (forceEventNotationForLongitudinal == null) forceEventNotationForLongitudinal = false;
    var msg = "<b>ERROR! Syntax errors exist in the logic:</b><br>"
    if (typeof brStr != "undefined") {
        // Remove any comments
        brStr = removeComments(brStr);
    }
    if (typeof brStr != "undefined" && brStr.length > 0) {
        // Must have at least one [ or ]
        // if (brStr.split("[").length == 1 || brStr.split("]").length == 1) {
        // msg += "&bull; Square brackets are missing. You have either not included any variable names in the logic or you have forgotten to put square brackets around the variable names.<br>";
        // brErr = true;
        // }
        // If longitudinal and forcing event notation for fields, then must be referencing events for variable names
        // if (longitudinal && forceEventNotationForLongitudinal && (brStr.split("][").length <= 1
        // || (brStr.split("][").length-1)*2 != (brStr.split("[").length-1)
        // || (brStr.split("][").length-1)*2 != (brStr.split("]").length-1))) {
        // msg += "&bull; One or more fields are not referenced by event. Since this is a longitudinal project, you must specify the unique event name "
        // + "when referencing a field in the logic. For example, instead of using [age], you must use [enrollment_arm1][age], "
        // + "assuming that enrollment_arm1 is a valid unique event name in your project. You can find a list of all your project's "
        // + "unique event names on the Define My Events page.<br>";
        // brErr = true;
        // }
        // Check symmetry of "
        if ((brStr.split('"').length - 1)%2 > 0) {
            msg += "&bull; Odd number of double quotes exist<br>";
            brErr = true;
        }
        // Check symmetry of '
        if ((brStr.split("'").length - 1)%2 > 0) {
            msg += "&bull; Odd number of single quotes exist<br>";
            brErr = true;
        }
        // Check symmetry of [ with ]
        if (brStr.split("[").length != brStr.split("]").length) {
            msg += "&bull; Square bracket is missing<br>";
            brErr = true;
        }
        // Check symmetry of ( with )
        if (brStr.split("(").length != brStr.split(")").length) {
            msg += "&bull; Parenthesis is missing<br>";
            brErr = true;
        }
        // Make sure does not contain $ dollar signs
        if (brStr.indexOf('$') > -1) {
            msg += "&bull; Illegal use of dollar sign ($). Please remove.<br>";
            brErr = true;
        }
        // Make sure does not contain ` backtick character
        if (brStr.indexOf('`') > -1) {
            msg += "&bull; Illegal use of backtick character (`). Please remove.<br>";
            brErr = true;
        }
    }
    // If errors exist, stop and show message
    if (brErr && display_alert) {
        simpleDialog(msg+"<br>You must fix all errors listed before you can save this logic.");
        return true;
    }
    return brErr;
}

// Open dialog to randomize a record
function randomizeDialog(record, rid) {
    // Open dialog pop-up populated by ajax call content
    if (!$('#randomizeDialog').length) $('body').append('<div id="randomizeDialog'+rid+'" class="randomizeDialog" style="display:none;"></div>');
    // Get the dialog content via ajax first
    $.post(app_path_webroot+'Randomization/randomize_record.php?pid='+pid, { action: 'view', record: record, rid: rid }, function(data){
        if (data == '0') {
            alert(woops);
            return;
        }
        // Load dialog content
        $('#randomizeDialog'+rid).html(data);
        // Check if returned without error
        if (!$('#randomizeDialog'+rid+' #randomCriteriaFields'+rid).length) {
            // Open dialog
            $('#randomizeDialog'+rid).dialog({ bgiframe: true, modal: true, width: 750, open: function(){fitDialog(this)},
                title: '<i class="fas fa-random"></i> ' + interpolateString(window.lang.data_entry_538, [table_pk_label+' "'+record+'"']), // Cannot yet randomize {0}
                buttons: {
                    Close: function() {
                        $(this).dialog('close');
                    }
                }
            });
            return;
        }
        // Check if we're on a data entry page
        var isDataEntryPage = (page == 'DataEntry/index.php');
        // Get arrays of criteria fields/events
        var critFldsCsv = $('#randomizeDialog'+rid+' #randomCriteriaFields'+rid).val();
        var critFlds = (critFldsCsv.length > 0) ? critFldsCsv.split(',') : new Array();
        var critEvtsCsv = $('#randomizeDialog'+rid+' #randomCriteriaEvents'+rid).val();
        var critEvts = (critEvtsCsv.length > 0) ? critEvtsCsv.split(',') : new Array();
        // Check if we're on a form right now AND if our criteria fields are present.
        // If so, copy in their current values (because they may not have been saved yet).
        if (isDataEntryPage) {
            for (var i=0; i<critFlds.length; i++) {
                var field = critFlds[i];
                var event = critEvts[i];
                // Only do for correct event
                if (event == event_id) {
                    if ($('#form select[name="'+field+'"]').length) {
                        // Drop-down
                        var fldVal = $('#form select[name="'+field+'"]').val();
                        $('#random_form select[name="'+field+'"]').val(fldVal);
                    } else if ($('#form :input[name="'+field+'"]').length) {
                        // Radio/YN/TF
                        var fldVal = $('#form :input[name="'+field+'"]').val();
                        // First unselect all, then loop to find the one to select
                        if ($('#random_form input[type="radio"][name="'+field+'"]').length) {
                            radioResetVal(field,'random_form');
                        }
                        $('#random_form input[name="'+field+'"]').val(fldVal);
                        if (fldVal != '' && $('#random_form input[type="radio"][name="'+field+'___radio"]').length) {
                            $('#random_form input[name="'+field+'___radio"]').each(function(){
                                if ($(this).val() == fldVal) {
                                    $(this).prop('checked',true);
                                }
                            });
                        }
                    }
                }
            }
            // If we're grouping by DAG and user is NOT in a DAG, then transfer DAG value from form to pop-up
            if ($('#form select[name="__GROUPID__"]').length && $('#random_form select[name="redcap_data_access_group"]').length) {
                $('#random_form select[name="redcap_data_access_group"]').val( $('#form select[name="__GROUPID__"]').val() );
            }
        }
        // Open dialog
        $('#randomizeDialog'+rid).dialog({ bgiframe: true, modal: true, width: 750, open: function(){fitDialog(this);if (isMobileDevice) fitDialog(this);},
            title: '<i class="fas fa-random"></i> ' + interpolateString(window.lang.data_entry_539, [table_pk_label+' "'+record+'"']), // Randomize {0}
            buttons: {
                Cancel: function() { // ttfy
                    // Lastly, clear out dialog content
                    $('#randomizeDialog'+rid).html('');
                    $(this).dialog('close');
                },
                'Randomize': function() { // Randomize - ttfy
                    // Disable buttons so they can't be clicked multiple times
                    $('#randomizeDialog'+rid).parent().find('div.ui-dialog-buttonpane button').button('disable');
                    // Make sure all fields have a value
                    var critFldVals = new Array();
                    if ($('#randomizeDialog'+rid+' #random_form table.form_border tr').length) {
                        var fldsNoValCnt = 0;
                        // Loop through all strata fields
                        for (var i=0; i<critFlds.length; i++) {
                            var isDropDownField = $('#randomizeDialog'+rid+' #random_form select[name="'+critFlds[i]+'"]').length;
                            if (!isDropDownField && $('#randomizeDialog'+rid+' #random_form input[name="'+critFlds[i]+'"]').val().length < 1) {
                                // Radio/TF/YN w/o value
                                fldsNoValCnt++;
                            } else if (isDropDownField && $('#randomizeDialog'+rid+' #random_form select[name="'+critFlds[i]+'"]').val().length < 1) {
                                // Dropdown w/o value
                                fldsNoValCnt++;
                            } else {
                                critFldVals[i] = (isDropDownField ? $('#randomizeDialog'+rid+' #random_form select[name="'+critFlds[i]+'"]').val() : $('#randomizeDialog'+rid+' #random_form input[name="'+critFlds[i]+'"]').val());
                            }
                        }
                        // Also check DAG field, if exists
                        if ($('#random_form select[name="redcap_data_access_group"]').length && $('#random_form select[name="redcap_data_access_group"]').val().length < 1) {
                            fldsNoValCnt++;
                        }
                        // If any missing fields are missing a value, stop here and prompt user
                        if (fldsNoValCnt > 0) {
                            simpleDialog(fldsNoValCnt+" strata/criteria field(s) do not yet have a value. "
                                + "You must first provide them with a value before randomization can be performed.","VALUES MISSING FOR STRATA/CRITERIA FIELDS!"); // ttfy
                            // Re-eable buttons
                            $('#randomizeDialog'+rid).parent().find('div.ui-dialog-buttonpane button').button('enable');
                            return;
                        }
                    }
                    // AJAX call to save data and randomize record
                    $.post(app_path_webroot+'Randomization/randomize_record.php?pid='+pid+'&instance='+getParameterByName('instance'), { rid: rid, event_id: event_id, redcap_data_access_group: $('#random_form select[name="redcap_data_access_group"]').val(), existing_record: document.form.hidden_edit_flag.value, action: 'randomize', record: record, fields: critFlds.join(','), field_values: critFldVals.join(',') }, function(data){
                        if (data == '0' || data == '2') {
                            if (data == '0') {
                                alert(woops);
                                // Re-enable buttons
                                $('#randomizeDialog'+rid).parent().find('div.ui-dialog-buttonpane button').button('enable');
                            } else {
                                $('#randomizeDialog'+rid).dialog('close');
                                simpleDialog(lang.random_216);
                            }
                            return;
                        }
                        // Replace dialog content with response data
                        $('#randomizeDialog'+rid).html(data);
                        // Replace dialog buttons with a Close button
                        $('#randomizeDialog'+rid).dialog("option", "buttons", []);
                        fitDialog($('#randomizeDialog'+rid));
                        // Initialize widgets
                        initWidgets();
                        // Replace Randomize button on left-hand menu
                        var success = $('#randomizeDialog'+rid+' #alreadyRandomizedTextWidget'+rid).length;
                        if (success) {
                            // Replace Randomize button on form with "Already Randomized" text and redisplay the field
                            $('#alreadyRandomizedText'+rid).html( $('#randomizeDialog'+rid+' #alreadyRandomizedTextWidget'+rid).html() );
                            $('#randomizationFieldHtml'+rid).show();
                            // If on data entry form and criteria fields are on this form, disable them and set their values
                            if (isDataEntryPage) {
                                // Set hidden_edit_flag to 1 (in case this is a new record)
                                $('#form :input[name="hidden_edit_flag"]').val('1');
                                // Remove &auto=1 from location
                                modifyURL(window.location.href.replace('&auto=1',''));
                                // Loop through criteria fields
                                for (var i=0; i<critFlds.length; i++) {
                                    var field = critFlds[i];
                                    var fldVal = critFldVals[i];
                                    var event = critEvts[i];
                                    // Only do for correct event
                                    if (event == event_id) {
                                        if ($('#form select[name="'+field+'"]').length) {
                                            // Drop-down
                                            $('#form select[name="'+field+'"]').val(fldVal).prop('disabled',true);
                                            // Also set autocomplete input for drop-down (if using auto-complete)
                                            if ($('#form #rc-ac-input_'+field).length)
                                                $('#form #rc-ac-input_'+field).val( $('#form select[name="'+field+'"] option:selected').text() ).prop('disabled',true).parent().find('button.rc-autocomplete').prop('disabled',true);
                                        } else if ($('#form :input[name="'+field+'"]').length) {
                                            // Radio/YN/TF
                                            $('#form :input[name="'+field+'"]').val(fldVal);
                                            if (fldVal != '' && $('#form input[type="radio"][name="'+field+'___radio"]').length) {
                                                $('#form :input[name="'+field+'___radio"]').prop('disabled',true);
                                                $('#form :input[name="'+field+'___radio"][value="'+fldVal+'"]').prop('checked',true);
                                            }
                                            // Now hide the "reset value" link for this field
                                            $('#form tr#'+field+'-tr .resetLinkParent a.smalllink').hide(); // regular non-embedded location
                                            $('#form .rc-field-embed[var="'+field+'"] .resetLinkParent a.smalllink').hide(); // embedded location
                                        }
                                    }
                                }
                                // Now set value for randomization field, if on this form
                                var fldVal = $('#randomizeDialog'+rid+' #randomizationFieldRawVal'+rid).val();
                                var field = $('#randomizeDialog'+rid+' #randomizationFieldName'+rid).val();
                                var event = $('#randomizeDialog'+rid+' #randomizationFieldEvent'+rid).val();
                                // Only do for correct event
                                if (event == event_id) {
                                    if ($('#form select[name="'+field+'"]').length) {
                                        // Drop-down
                                        $('#form select[name="'+field+'"]').val(fldVal).prop('disabled',true);
                                        // Also set autocomplete input for drop-down (if using auto-complete)
                                        if ($('#form #rc-ac-input_'+field).length)
                                            $('#form #rc-ac-input_'+field).val( $('#form select[name="'+field+'"] option:selected').text() ).prop('disabled',true).parent().find('button.rc-autocomplete').prop('disabled',true);
                                    } else if ($('#form :input[name="'+field+'"]').length) {
                                        // Radio/YN/TF
                                        // First unselect all, then loop to find the one to select
                                        $('#form :input[name="'+field+'"]').val(fldVal);
                                        $('#form :input[name="'+field+'___radio"]').prop('disabled',true);
                                        $('#form :input[name="'+field+'___radio"][value="'+fldVal+'"]').prop('checked',true);
                                    }
                                }
                                // If we're grouping by DAG and user is NOT in a DAG, then transfer DAG value from pop-up back to form
                                // after randomizing AND also disabled the DAG drop-down to prevent someone changing it.
                                if ($('#form select[name="__GROUPID__"]').length && $('#randomizeDialog'+rid+' #redcap_data_access_group').length) {
                                    $('#form select[name="__GROUPID__"]').val( $('#randomizeDialog'+rid+' #redcap_data_access_group').val() );
                                    $('#form select[name="__GROUPID__"]').prop('disabled',true);
                                }
                            }
                            // Just in case we're using auto-numbering and current ID does not reflect saved ID (due to simultaneous users),
                            // change the record value on the page in all places.
                            var recordName = $('#randomizeDialog'+rid+' #record').val();
                            $('#form :input[name="'+table_pk+'"], #form :input[name="__old_id__"]').val(recordName);
                            // Set new record name in Record Home page menu link (and remove "auto", if applicable)
                            var home_page_menu_link = removeParameterFromURL(removeParameterFromURL($('#record-home-link').attr('href'), 'auto'), 'id');
                            home_page_menu_link += "&id="+recordName;
                            $('#record-home-link').attr('href', home_page_menu_link);
                            // Hide the duplicate randomization field label (if Left-Aligned)
                            $('.randomizationDuplLabel').hide();
                            // Now that record is randomized, run branching and calculations on form in case any logic is built off of fields used in randomization
                            calculate();
                            doBranching();
                        }
                    });
                }
            }
        });
        // Init any autocomplete dropdowns inside the randomization dialog
        if (isDataEntryPage) enableDropdownAutocomplete();
    });
}

// Show/hide options for various delivery methods when sending survye invitations
function setInviteDeliveryMethod(ob) {
    var val = $(ob).val();
    $('#compose_email_subject_tr, #compose_email_from_tr, #compose_email_form_fieldset, #compose_email_to_tr').show();
    $('.show_for_sms, .show_for_voice, .show_for_part_pref, #compose_phone_to_tr, #surveyLinkWarningDeliveryType').hide();
    if (val == 'VOICE_INITIATE') {
        $('#compose_email_subject_tr, #compose_email_from_tr, #compose_email_form_fieldset, #compose_email_to_tr').hide();
        $('.show_for_voice, #compose_phone_to_tr').show();
    } else if (val == 'SMS_INVITE_MAKE_CALL' || val == 'SMS_INVITE_RECEIVE_CALL' || val == 'SMS_INITIATE' || val == 'SMS_INVITE_WEB') {
        $('#compose_email_subject_tr, #compose_email_from_tr, #compose_email_to_tr').hide();
        $('.show_for_sms, #compose_phone_to_tr').show();
    } else if (val == 'PARTICIPANT_PREF') {
        $('.show_for_part_pref').show();
    }
    if ($('#inviteFollowupSurvey').length) {
        $('#inviteFollowupSurvey').dialog('option', 'position', 'center');
    }
    if (val != 'EMAIL' && val != 'SMS_INVITE_WEB' && val != 'PARTICIPANT_PREF' && val != 'VOICE_INITIATE') {
        $('#surveyLinkWarningDeliveryType').show();
    }
}

// Dynamics when setting email address in pop-up for inviting participant to finish a follow-up survey
function inviteFollowupSurveyPopupSelectEmail(ob) {
    var isDD = ($(ob).attr('id') == 'followupSurvEmailToDD');
    if (isDD) {
        $('#followupSurvEmailTo').val('');
    } else {
        $('#followupSurvEmailToDD').val('');
    }
}

// Dynamics when setting phone number in pop-up for inviting participant to finish a follow-up survey
function inviteFollowupSurveyPopupSelectPhone(ob) {
    var isDD = ($(ob).attr('id') == 'followupSurvPhoneToDD');
    if (isDD) {
        $('#followupSurvPhoneTo').val('');
    } else {
        $('#followupSurvPhoneToDD').val('');
    }
}

// Open pop-up for the Help & FAQ page (can specify section using # anchor)
function helpPopup(anchor, qid) {
    window.open(app_path_webroot_full+'index.php?action=help&newwin=1'+(qid == null ? '' : '&qid='+qid)+(anchor == null ? '' : '#'+anchor),'myWin','width=1000, height=600, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');
}

// Open pop-up for the Codebook
function codebookPopup() {
    window.open(app_path_webroot+'Design/data_dictionary_codebook.php?popup=1&pid='+pid,'myWin','width=1000, height=600, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');
}

// When clicking through the External Links, do logging via ajax before sending to destination
function ExtLinkClickThru(ext_id,openNewWin,url,form) {
    $.post(app_path_webroot+'ExternalLinks/clickthru_logging_ajax.php?pid='+pid, { url: url, ext_id: ext_id }, function(data){
        if (data != '1') {
            alert(woops);
            return false;
        }
        if (!openNewWin) {
            if (form != '') {
                // Adv Link: Submit the form
                $('#'+form).submit();
            } else {
                // Simple Link: If not opening a new window, then redirect the current page
                window.location.href = url;
            }
        }
    });
}

// Graphical page: Show/hide plots and stats tables
function showPlotsStats(option,obj) {
    // Enable all buttons
    $('#showPlotsStatsOptions button').each(function(){
        $(this).prop('disabled',false);
        $(this).button('enable');
    });
    // Disable this button
    $(obj).button('disable');
    // Options
    if (option == 1) {
        // Plots only
        $('.descrip_stats_table, .gct_plot img').hide();
        $('.gct_plot, .plot-download-div').show();
        $('.plot-download-div button').css('display','inline-block');
    } else if (option == 2) {
        // Stats only
        $('.descrip_stats_table, .gct_plot img').show();
        $('.gct_plot, .plot-download-div').hide();
        $('.plot-download-div button').css('display','none');
    } else {
        // Plots+Stats
        $('.descrip_stats_table, .gct_plot, .plot-download-div').show();
        $('.plot-download-div button').css('display','inline-block');
        $('.gct_plot img').hide();
    }
    $('.hideforever').hide();
}

// Function to download data dictionary (give warning if project has any forms downloaded from Shared Library)
function downloadDD(draft,showLegal,delimiter) {
    if (typeof delimiter == 'undefined') delimiter = csv_delimiter;
    var url = app_path_webroot+'Design/data_dictionary_download.php?pid='+pid+'&delimiter='+delimiter;
    if (draft) url += '&draft';
    if (showLegal) {
        displaySharedLibraryTermsOfUse(function(){
            downloadDD(draft, 0, delimiter);
        });
    } else {
        window.location.href = url;
    }
}

function downloadCurrentUsersList() {
    location.search += (location.search=="") ? "?route=UserController:downloadCurrentUsersList" : "&route=UserController:downloadCurrentUsersList";
}

// Give warning and Terms of Use if project has any forms downloaded from Shared Library
function displaySharedLibraryTermsOfUse(callback)
{
    $.get(app_path_webroot+'SharedLibrary/terms_of_use.php', { }, function(data){
        simpleDialog(data, 'Agree to Terms to Use', 'sharedLibLegal', 800, null, 'Cancel', function(){ callback(); }, 'I Agree with Terms of Use');
    });
}

// Give message if PK field was changed on Design page
function update_pk_msg(reload_page,moved_source) {
    $.get(app_path_webroot+'Design/update_pk_popup.php', { pid: pid, moved_source: moved_source }, function(data) {
        if (data != '') { // Don't show dialog if no callback html (i.e. no records exist)
            initDialog("update_pk_popup",data);
            $('#update_pk_popup').dialog({title: langRecIdFldChanged, bgiframe: true, modal: true, width: 600, buttons: [
                    { text: window.lang.design_401, click: function () {
                            $(this).dialog('close');
                            if (reload_page != null) {
                                if (reload_page) window.location.reload();
                            }
                        }}
                ]});
        } else if (moved_source == 'form') {
            simpleDialog(form_moved_msg,null,'','','window.location.reload();');
        }
    });
}

// Open window for viewing survey
function surveyOpen(path,preview) {
    // Determine if showing a survey preview rather than official survey (default preview=false or 0)
    if (preview == null) preview = 0;
    if (preview != 1 && preview != 0) preview = 0;
    // Open window
    window.open(path+(preview ? '&preview=1' : ''),'_blank');
}

// Selecting logo for survey and check if an image
function checkLogo(file) {
    extension = getfileextension(file);
    extension = extension.toLowerCase();
    if (extension != "jpeg" && extension != "jpg" && extension != "gif" && extension != "png" && extension != "bmp") {
        $("#old_logo").val("");
        alert("ERROR: The file you selected is not an image file (e.g., GIF, JPG, JPEG, BMP, PNG). Please try again.");
    }
}

// Display explanation dialog pop-up to explain create/rename/delete record settings on User Rights
function userRightsRecordsExplain() {
    $.get(app_path_webroot+'UserRights/record_rights_popup.php', { pid: pid }, function(data) {
        if (!$('#recordsExplain').length) $('body').append('<div id="recordsExplain"></div>');
        $('#recordsExplain').html(data);
        $('#recordsExplain').dialog({ bgiframe: true, modal: true, title: 'User privileges pertaining to project records', width: 650, buttons: { Close: function() { $(this).dialog('close'); } } });
    });
}

// Toggle project left-hand menu sections
function projectMenuToggle(selector) {
    $(selector).click(function(){
        var divBox = $(this).parent().parent().find('.x-panel-bwrap:first');
        // Toggle the box
        divBox.toggle('blind','fast');
        // Toggle the image
        var toggleImg = $(this).find('img:first');
        if (toggleImg.prop('src').indexOf('toggle-collapse.png') > 0) {
            toggleImg.prop('src', app_path_images+'toggle-expand.png');
            var collapse = 1;
        } else {
            toggleImg.prop('src', app_path_images+'toggle-collapse.png');
            var collapse = 0;
        }
        // Send ajax request to save cookie
        $.post(app_path_webroot+'ProjectGeneral/project_menu_collapse.php?pid='+pid, { menu_id: $(this).prop('id'), collapse: collapse });
    });
}

// Initialization functions for normal project-level pages
function initPage() {
    // Exclude survey theme view page
    if (page == 'Surveys/theme_view.php') return;
    // Get window height
    var winHeight = $(window).height();
    if (isMobileDevice) {
        // Make sure the bootstrap navbar stays at top-right (wide pages can push it to the right)
        var winWidth = $(window).width();
        try {
            $('button.navbar-toggler:visible').each(function(){
                var btnRight = $(this).offset().left+80;
                if (btnRight > winWidth) {
                    $(this).css({'margin-right':(btnRight-winWidth)+'px'});
                }
            });
        } catch(err) {}
    } else if ($('#center').length) {
        // Set project footer position
        setProjectFooterPosition();
    }
    // Perform actions upon page resize
    window.onresize = function() {
        if (isMobileDevice) $('#south').hide();
        try{ displayFormSaveBtnTooltip(); }catch(e){}
        if (!$('#west').hasClass('d-md-block') && !isMobileDeviceFunc()) {
            toggleProjectMenuMobile($('#west'));
        }
        // Reset project footer position
        setProjectFooterPosition();
        // User Messaging msg window
        try{ calculateMessageWindowPosition(); }catch(e){}
    }
    // Add fade mouseover for "Edit instruments", "Edit reports", etc. links on project menu
    $("#menuLnkEditInstr, #menuLnkEditBkmrk, #menuLnkEditReports, .projMenuToggle, #menuLnkProjectFolders, #menuLnkSearchReports, #menuLnkEditDashboards, #external_modules_panel .opacity65").mouseenter(function() {
        $(this).removeClass('opacity65');
        if (isIE) $(this).find("img").removeClass('opacity65');
    }).mouseleave(function() {
        $(this).addClass('opacity65');
        if (isIE) $(this).find("img").addClass('opacity65');
    });
    // Toggle project left-hand menu sections
    projectMenuToggle('.projMenuToggle');
    // Add fade mouseover for "Choose other record" link on project menu
    $("#menuLnkChooseOtherRec").mouseenter(function() {
        $(this).removeClass('opacity65');
    }).mouseleave(function() {
        $(this).addClass('opacity65');
    });
    // Reset project footer position when the page's height changes
    onElementHeightChange(document.body, function(){
        setProjectFooterPosition();
    });
    // Put focus on main window for initial scrolling (only works in IE)
    if ($('#center').length) document.getElementById('center').focus();
}

// Set project footer position
function setProjectFooterPosition() {
    var centerHeight = $('#center').height();
    var westHeight = $('#west').height();
    var winHeight = $(window).height();
    var hasScrollBar = ($(document).height() > winHeight);
    if ((hasScrollBar && (centerHeight > winHeight || westHeight > centerHeight))
        || (!hasScrollBar && centerHeight+$('#south').height() > winHeight))
    {
        if (westHeight > centerHeight) {
            $('#south').css({'position':'absolute','margin':'50px 0px 0px 1px','bottom':'-'+(westHeight - centerHeight)+'px'});
            $('#center').css('padding-bottom','60px');
        } else {
            $('#south').css({'position':'relative','margin':'50px 0px 0px 1px','bottom':'0px'});
            $('#center').css('padding-bottom','0px');
        }
    } else {
        var westWidth = $('#west').width();
        var leftMargin = ($('#west').css('display') == 'none') ? 0 : westWidth+3;
        $('#south').css({'position':'fixed','margin':'0 0 0 '+leftMargin+'px','bottom':'0px'});
        $('#south').width( $(window).width()-(leftMargin == 0 ? 0 : (westWidth+44)));
        $('#center').css('padding-bottom','60px');
    }
    $('#south').css('visibility','visible');
}

// Set form as unlocked (enabled fields, etc.)
function setUnlocked(esign_action) {
    var form_name = getParameterByName('page');
    // Bring back Save buttons
    $('#__SUBMITBUTTONS__-div').css('display','block');
    $('#__DELETEBUTTONS__-div').css('display','block');
    // Remove locking informational text
    $('#__LOCKRECORD__').prop('checked', false);
    $('#__ESIGNATURE__').prop('checked', false);
    $('#lockingts').html('').css('display','none');
    $('#unlockbtn').css('display','none');
    $('#lock_record_msg').css('display','none');
    // Remove lock icon from menu (if visible)
    $('img#formlock-'+form_name).hide();
    $('img#formesign-'+form_name).hide();
    // Hide e-signature checkbox if e-signed but user does not have e-sign rights
    if (lock_record < 2 && $('#esignchk').length) {
        $('#esignchk').hide().html('');
    }
    // Determine if user has read-only rights for this form
    var readonly_form_rights = !($('#__SUBMITBUTTONS__-div').length && $('#__SUBMITBUTTONS__-div').css('display') != 'none');
    if (readonly_form_rights) {
        $('#__LOCKRECORD__').prop('disabled', false);
        $('#__ESIGNATURE__').prop('disabled', false);
    } else {
        // Remove the onclick attribute from the lock record checkbox so that the next locking is done via form post
        $('#__LOCKRECORD__').removeAttr('onclick').attr('onclick','');
        $('#__ESIGNATURE__').removeAttr('onclick').attr('onclick','');
        // Unlock and reset all fields on form
        $(':input').each(function() {
            // Re-enable field UNLESS field is involved in randomization (i.e. has randomizationField class)
            if (!$(this).hasClass('randomizationField')
                && !$(this).parentsUntil('tr[sq_id]').parent().hasClass('@READONLY')
                && !$(this).parentsUntil('tr[sq_id]').parent().hasClass('@READONLY-FORM')
            ) {
                // Enable field
                $(this).prop('disabled', false);
            }
        });
        // Make radio "reset" link visible again
        $('.cclink').each(function() {
            // Re-enable link UNLESS field is involved in randomization (i.e. has randomizationField class)
            if (!$(this).hasClass('randomizationField')) {
                // Enable field
                $(this).css('display','block');
            }
        });
        // Enable "Randomize" button, if using randomization
        $('#redcapRandomizeBtn').removeAttr('aria-disabled').removeClass('ui-state-disabled').prop('disabled', false);
        // Add all options back to Form Status drop-down, and set value back afterward
        var form_status_field = $(':input[name='+form_name+'_complete]');
        var form_val = form_status_field.val();
        var sel = ' selected ';
        form_status_field
            .find('option')
            .remove()
            .end()
            .append('<option value="0"'+(form_val==0?sel:'')+'>Incomplete</option><option value="1"'+(form_val==1?sel:'')+'>Unverified</option><option value="2"'+(form_val==2?sel:'')+'>Complete</option>');
        // If editing a survey response, do NOT re-enable the Form Status field
        if (getParameterByName('editresp') == "1") form_status_field.prop("disabled",true);
        // Enable green row highlight for data entry form table
        enableDataEntryRowHighlight();
        // Re-display the save form buttons tooltip
        displayFormSaveBtnTooltip();
        //re-display missing data buttons
        $('.missingDataButton').show();
        // Enable sliders
        $('.slider').each(function(index,item){
            $(item).attr('locked','0');
            var field = $(item).prop('id').substring(7);
            $("#slider-"+field).attr('onmousedown',"enableSldr('"+field+"');$(this).attr('modified','1');");
            if ($(item).attr('modified') == '1') {
                enableSldr(field);
            }
        });
    }
    // Check for e-sign negation
    var esign_msg = "";
    if (esign_action == "negate") {
        $('#esignts').hide();
        $('#esign_msg').hide();
        $('#__ESIGNATURE__').prop('checked', false);
        esign_msg = " "+lang.data_entry_692;
    }
    // Give confirmation
    simpleDialog(lang.data_entry_691+esign_msg+" "+lang.data_entry_693,lang.data_entry_694);
}

// Lock/Unlock records for entire record
function lockUnlockFormsDo(fetched, fetched2, lock, arm) {
    if (lock == 'lock') {
        var alertmsg = lang.global_49+' "'+fetched2+'" '+lang.data_entry_478;
    } else if (lock == 'unlock') {
        var alertmsg = lang.global_49+' "'+fetched2+'" '+lang.data_entry_479;
    } else {
        return;
    }
    showProgress(1);
    $.get(app_path_webroot+'Locking/all_forms_action.php', { pid: pid, id: fetched, action: lock, arm: arm },
        function(data) {
            showProgress(0, 0);
            if (data == "1") {
                Swal.fire({
                    title: alertmsg, html: lang.create_project_97, icon: 'success', timer: 2500
            });
            setTimeout('showProgress(1);', 2500);
            setTimeout('window.location.reload();', 3000);
            } else {
                alert(woops);
            }
        }
    );
}
function lockUnlockForms(fetched, fetched2, event_id, arm, grid, lock) {
    var showLockConfirmationDialog = ($('#recordLockPdfConfirmDialog').length && lock == 'lock');
    var lockConfirmationPdfUrl = app_path_webroot+"index.php?route=PdfController:index&pid="+pid+"&id="+fetched+"&__noLogPDFSave=1&compact=1&display=inline";
    if (showLockConfirmationDialog) {
        simpleDialog(null,null,'recordLockPdfConfirmDialog',800,'',lang.global_53,"lockUnlockFormsDo('"+fetched+"','"+fetched2+"','"+lock+"','"+arm+"');",lang.data_entry_482);
        $('#record_lock_pdf_confirm_iframe').attr('src', lockConfirmationPdfUrl);
        $('#record_lock_pdf_confirm_iframe').parent().attr('data', lockConfirmationPdfUrl);
        fitDialog($('#recordLockPdfConfirmDialog'));
        $('#record_lock_pdf_confirm_checkbox_label').removeClass('opacity50');
        $('#record_lock_pdf_confirm_checkbox_div').removeClass('green').addClass('yellow');
        $('#record_lock_pdf_confirm_checkbox').prop('checked',false);
        showProgress(1);
        setTimeout(function(){
            showProgress(0,0);
            $('#record_lock_pdf_confirm_checkbox').prop('disabled',false);
        },1000);
        $('#recordLockPdfConfirmDialog').parent().find('.ui-dialog-buttonpane button:eq(1)').prop('disabled',true).addClass('opacity50');
        initInlinePdfs();
        return;
    } else if (lock == 'lock') {
        var prompt = lang.data_entry_480+' "<b>'+fetched2+'</b>"'+lang.questionmark+' '+lang.data_entry_484;
        var btn = lang.data_entry_482;
    } else if (lock == 'unlock') {
        var prompt = lang.data_entry_481+' "<b>'+fetched2+'</b>"?';
        var btn = lang.data_entry_483;
    } else {
        return;
    }
    simpleDialog('<div class="fs14">'+prompt+'</div>',btn,null,600,null,lang.global_53,"lockUnlockFormsDo('"+fetched+"','"+fetched2+"','"+lock+"','"+arm+"');",btn);
    initInlinePdfs();
}

// Run any time an esign fails to verify username/password
function esignFail(numLogins) {
    if (numLogins == 3) {
        alert(lang.data_entry_689+"\n\n"+lang.data_entry_690);
        window.location.href += "&logout=1";
    } else {
        $('#esign_popup_error').toggle('blind',{},'normal');
    }
}

// Save the locking value from the form, then submit form
function saveLocking(lock_action,esign_action)
{
    // Determine action
    if (lock_action == 2) 		var action = "";
    else if (lock_action == 1)  var action = "lock";
    else if (lock_action == 0)  var action = "unlock";
    // Error msg
    var error_msg = lang.data_entry_703;
    // E-signature required (i.e. lock_record==2), but not if simply unlocking/negating esign
    if (lock_record == 2 && $('#__ESIGNATURE__').prop('checked') && esign_action == "save")
    {
        // Count login attempts
        var numLogins = 0;
        // Username/password popup
        $('#esign_popup').dialog({ bgiframe: true, modal: true, width: 530, zIndex: 3999, buttons: {
                'Save': function() {
                    // Check username/password entered is correct
                    $('#esign_popup_error').css('display','none'); //Default state
                    $.post(app_path_webroot+"Locking/single_form_action.php?pid="+pid, {auto: getParameterByName('auto'), instance: getParameterByName('instance'), esign_action: esign_action, event_id: event_id, action: action, username: $('#esign_username').val(), password: $('#esign_password').val(), record: getParameterByName('id'), form_name: getParameterByName('page')}, function(data){
                        if ($('#esign_password').attr('readonly') != 'readonly') $('#esign_password').val('');
                        if (starts_with(data, "ERROR:")) {
                            $('#esign_popup').dialog('close');
                            simpleDialog("<div class='fs14 text-dangerrc'><i class=\"fa-solid fa-circle-exclamation\"></i> "+data.replace("ERROR:","")+"</div>", lang.global_01);
                        } else if (data != "") {
                            // If response=1, then correct username/password was entered and e-signature was saved
                            $('#esign_popup').dialog('close');
                            numLogins = 0;
                            // Submit the form if saving e-signature
                            if (action == 'lock' || action == '') {
                                // Just in case we're using auto-numbering and current ID does not reflect saved ID (due to simultaneous users),
                                // change the record value on the page in all places.
                                if (auto_inc_set && getParameterByName('auto') == '1' && isinteger(data.replace('-',''))) {
                                    $('#form :input[name="'+table_pk+'"], #form :input[name="__old_id__"]').val(data);
                                }
                                // Submit the form (unless it's readonly, in which case just reload the page)
                                if (!$('#form :input[name="'+getParameterByName('page')+'_complete"]:visible').length || $('#form :input[name="'+getParameterByName('page')+'_complete"] option').length == 1) {
                                    // If the form status drop-down has only one choice, then that means the page is not editable, so just reload the page here
                                    window.location.reload();
                                } else {
                                    formSubmitDataEntry();
                                }
                            } else {
                                setUnlocked(esign_action);
                            }
                        } else {
                            // Login failed
                            numLogins++;
                            esignFail(numLogins);
                        }
                    });
                }
            } });
    }
    // No e-signature, so just save locking value
    else
    {
        $.post(app_path_webroot+"Locking/single_form_action.php?pid="+pid, {auto: getParameterByName('auto'), instance: getParameterByName('instance'), esign_action: esign_action, no_auth_key: 'q4deAr8s', event_id: event_id, action: action, record: getParameterByName('id'), form_name: getParameterByName('page')}, function(data){
            if (starts_with(data, "ERROR:")) {
                simpleDialog("<div class='fs14 text-dangerrc'><i class=\"fa-solid fa-circle-exclamation\"></i> "+data.replace("ERROR:","")+"</div>", lang.global_01);
            } else if (data != "") {
                // Submit the form if saving e-signature
                if (action == 'lock' || action == '') {
                    // Just in case we're using auto-numbering and current ID does not reflect saved ID (due to simultaneous users),
                    // change the record value on the page in all places.
                    if (auto_inc_set && getParameterByName('auto') == '1' && isinteger(data.replace('-',''))) {
                        if (!$('#form :input[name="__old_id__"]').length) {
                            appendHiddenInputToForm('__old_id__', data);
                        }
                        $('#form :input[name="'+table_pk+'"], #form :input[name="__old_id__"]').val(data);
                        $('#form :input[name="hidden_edit_flag"]').val('1');
                    }
                    // Submit the form
                    formSubmitDataEntry();
                } else {
                    setUnlocked(esign_action);
                }
            } else {
                // error occurred
                alert(error_msg);
            }
        });
    }
}

// Unlock a record on a form
function unlockForm(unlockBtnJs) {
    var esign_notice = "";
    var esign_action = "";
    if (unlockBtnJs == null) unlockBtnJs = '';
    // Show extra notice if record has been e-signed (because unlocking will negate it)
    if ($('#__ESIGNATURE__').length && $('#__ESIGNATURE__').prop('checked') && $('#__ESIGNATURE__').prop('disabled')) {
        esign_notice = " "+lang.data_entry_698;
        esign_action = "negate";
    }
    simpleDialog(lang.data_entry_695+esign_notice,lang.data_entry_696,null,null,
        null,lang.global_53,"saveLocking(0,'"+esign_action+"');"+unlockBtnJs,lang.data_entry_697);
}

// Function used when whole form is disabled *except* the lock record checkbox (this avoids a form post to prevent issues of saving for disabled fields)
function lockDisabledForm(ob) {
    // Dialog for confirmation
    if (confirm(lang.data_entry_699+"\n\n"+lang.data_entry_700)) {
        $.post(app_path_webroot+"Locking/single_form_action.php?pid="+pid, {instance: getParameterByName('instance'), esign_action: '', no_auth_key: 'q4deAr8s', event_id: event_id, action: "lock", record: getParameterByName('id'), form_name: getParameterByName('page')}, function(data){
            if (data != "") {
                $(ob).prop('disabled',true);
                simpleDialog(lang.data_entry_701,lang.data_entry_702,null,null,"window.location.reload();");
            } else {
                alert(woops);
            }
        });
    } else {
        // Make sure we uncheck the checkbox if they decline after checking it.
        $(ob).prop('checked',false);
    }
}

// Data Quality: Reload an individual record-event[-field] table of rules violated on data entry page
function reloadDQResultSingleRecord(show_excluded) {
    // Do ajax call to set exclude value
    $.post(app_path_webroot+'DataQuality/data_entry_single_record_ajax.php?pid='+pid+'&instance='+getParameterByName('instance'), { dq_error_ruleids: getParameterByName('dq_error_ruleids'),
        show_excluded: show_excluded, record: getParameterByName('id'), event_id: getParameterByName('event_id'),
        page: getParameterByName('page')}, function(data){
        $('#dq_rules_violated').html(data);
        initWidgets();
    });
}

// Data Quality: When user clicks data value on form for real-time execution, close dialog and highlight field with pop-up to save
function dqRteGoToField(field) {
    // Close dialog
    $('#dq_rules_violated').dialog('close');
    // Go to the field
    $('html, body').animate({
        scrollTop: $('tr#'+field+'-tr').offset().top - 150
    }, 700);
    // Put focus on field
    $('form#form :input[name="'+field+'"]').focus();
    // Open tooltip right above field
    $('tr#'+field+'-tr')
        .tooltip2({ tip: '#dqRteFieldFocusTip', relative: true, effect: 'fade', offset: [10,0], position: 'top center', events: { tooltip: "mouseenter" } })
        .trigger('mouseenter')
        .unbind();
}

// Data Quality: Exclude an individual record-event[-field] from displaying in the results table
function excludeDQResult(ob,rule_id,exclude,record,event_id,field_name,instance,repeat_instrument) {
    if (typeof instance == "undefined") instance = 1;
    if (typeof repeat_instrument == "undefined") repeat_instrument = '';
    // Do ajax call to set exclude value
    $.post(app_path_webroot+'DataQuality/exclude_result_ajax.php?pid='+pid+'&instance='+instance+'&repeat_instrument='+repeat_instrument, { exclude: exclude, field_name: field_name, rule_id: rule_id, record: record, event_id: event_id }, function(data){
        if (data == '1') {
            // Change style of row to show exclusion value change
            var this_row = $(ob).parent().parent().parent();
            this_row.removeClass('erow');
            if (exclude) {
                this_row.css({'background-color':'#FFE1E1','color':'red'});
                $(ob).parent().html("<a href='javascript:;' style='font-size:10px;text-decoration:underline;color:#800000;' onclick=\"excludeDQResult(this,'"+rule_id+"',0,'"+record+"',"+event_id+",'"+field_name+"','"+instance+"','"+repeat_instrument+"');\"><span data-rc-lang=\"dataqueries_88\">"+window.lang.dataqueries_88+"</span></a>");
            } else {
                this_row.css({'background-color':'#EFF6E8','color':'green'});
                $(ob).parent().html("<a href='javascript:;' style='font-size:10px;text-decoration:underline;' onclick=\"excludeDQResult(this,'"+rule_id+"',1,'"+record+"',"+event_id+",'"+field_name+"','"+instance+"','"+repeat_instrument+"');\"><span data-rc-lang=\"dataqueries_87\">"+window.lang.dataqueries_87+"</span></a>");
                // Remove the "(excluded)" label under record name
                this_row.children('td:first').find('.dq_excludelabel').html('')
            }
        } else {
            alert(woops);
        }
    });
}

// Data Quality: Display the explainExclude dialog
function explainDQExclude() {
    $('#explain_exclude').dialog({ bgiframe: true, modal: true, width: 500,
        buttons: {'Close':function(){$(this).dialog("close");}}
    });
}

// Data Quality: Display the explainResolve dialog
function explainDQResolve() {
    $('#explain_resolve').dialog({ bgiframe: true, modal: true, width: 500,
        buttons: {'Close':function(){$(this).dialog("close");}}
    });
}

// Data Resolution Workflow: Open dialog for uploading files (for query response)
function openDataResolutionFileUpload(record, event_id, field, rule_id) {
    // Reset all hidden/non-hidden divs
    $('#drw_upload_success').hide();
    $('#drw_upload_failed').hide();
    $('#drw_upload_progress').hide();
    $('#drw_upload_form').show();
    // Reset file input field (must replace it because val='' won't work)
    var fileInput = $('#dc-upload_doc_id-container').html();
    $('#dc-upload_doc_id-container').html('').html(fileInput);
    // Add values to the hidden inputs inside the dialog
    $("#drw_file_upload_popup input[name='record']").val(record);
    $("#drw_file_upload_popup input[name='event_id']").val(event_id);
    $("#drw_file_upload_popup input[name='field']").val(field);
    $("#drw_file_upload_popup input[name='rule_id']").val(rule_id);
    // Open dialog
    $("#drw_file_upload_popup").dialog({ bgiframe: true, modal: true, width: 450, buttons: {
            "Cancel": function() { $(this).dialog("close"); },
            "Upload document": function() { $('form#drw_upload_form').submit(); }
        }});
}
// Data Resolution Workflow: Delete uploaded file (for query response)
function dataResolutionDeleteUpload() {
    // If any hidden input doc_id's already exist, they must be deleted, so keep them but mark them for deletion
    $('#drw_upload_file_container input.drw_upload_doc_id').attr('delete','yes');
    // Show "add new document" link
    $('#drw_upload_new_container').show();
    // Hide "remove document" link
    $('#drw_upload_remove_doc').hide();
    // Hide doc_name link
    $('#dc-upload_doc_id-label').html('').hide();
}
// Data Resolution Workflow: Start uploading file (for query response)
function dataResolutionStartUpload() {
    if (!fileTypeAllowed(basename($('#drw_upload_form input[name="myfile"]').val()))) {
        Swal.fire(window.lang.docs_1136, '', 'error');
        return false;
    }
    $('#drw_upload_form').hide();
    $('#drw_upload_progress').show()
    return true;
}
// Data Resolution Workflow: Stop uploading file (for query response)
function dataResolutionStopUpload(doc_id,doc_name) {
    $('#drw_file_upload_popup #drw_upload_form').hide();
    $('#drw_file_upload_popup #drw_upload_progress').hide();
    if (doc_id > 0) {
        // Success
        $('#drw_file_upload_popup #drw_upload_success').show();
        // Add doc_id as hidden input in hidden div container inside dialog
        $('#drw_upload_file_container').append('<input type="hidden" class="drw_upload_doc_id" value="'+doc_id+'">');
        // Hide "add new document" link
        $('#drw_upload_new_container').hide();
        // Show "remove document" link
        $('#drw_upload_remove_doc').show();
        // Add doc_name to hidden link
        $('#dc-upload_doc_id-label').html(doc_name).show();
    } else {
        // Failed
        $('#drw_file_upload_popup #drw_upload_failed').show();
    }
    // Add close button
    $('#drw_file_upload_popup').dialog('option', 'buttons', { "Close": function() { $(this).dialog("close"); } });
}

// Save new values from data cleaner pop-up dialog for individual field
function dataResolutionSave(field,event_id,record,rule_id,instance) {
    if (typeof instance == "undefined") instance = 1;
    // Set vars
    if (record == null) record = getParameterByName('id');
    if (rule_id == null) rule_id = '';
    // Check input values
    var comment = trim($('#dc-comment').val());
    //alert( $('#data_resolution input[name="dc-status"]:checked').val() );return;
    if (comment.length == 0 && ($('#data_resolution input[name="dc-status"]').length == 0
        || ($('#data_resolution input[name="dc-status"]').length && $('#data_resolution input[name="dc-status"]:checked').val() != 'VERIFIED'))) {
        simpleDialog(lang.dataqueries_360, lang.dataqueries_361);
        return;
    }
    var query_status = ($('#data_resolution input[name="dc-status"]:checked').length ? $('#data_resolution input[name="dc-status"]:checked').val() : '');
    if ($('#dc-response').length && query_status != 'CLOSED' && $('#dc-response').val().length == 0) {
        simpleDialog(lang.dataqueries_362,lang.dataqueries_363);
        return;
    }
    var response = (($('#dc-response').length && query_status != 'CLOSED') ? $('#dc-response').val() : '');
    // Note if user is sending query back for further attention (rather than closing it)
    var send_back = (query_status != 'CLOSED' && $('#dc-response_requested-closed').length) ? 1 : 0;
    // Determine if we're re-opening the query (i.e. if #dc-response_requested is a checkbox and assign user drop-down is not there)
    var reopen_query = ($('#dc-response_requested').length && $('#dc-response_requested').attr('type') == 'checkbox' && $('#dc-assigned_user_id').length == 0) ? 1 : 0;
    // If user is responding to query, check for file uploaded
    var upload_doc_id = '';
    var delete_doc_id = '';
    delete_doc_id_count = 0;
    if ($('#drw_upload_file_container input.drw_upload_doc_id').length > 0) {
        // Loop through all doc_id's available
        delete_doc_id = new Array();
        $('#drw_upload_file_container input.drw_upload_doc_id').each(function(){
            if ($(this).attr('delete') == 'yes') {
                delete_doc_id[delete_doc_id_count++] = $(this).val();
            } else {
                upload_doc_id = $(this).val();
            }
        });
        delete_doc_id = delete_doc_id.join(",");
    }
    // Disable all input fields in pop-up while saving
    $('#newDCHistory :input').prop('disabled',true);
    $('#data_resolution .jqbutton').button('disable');
    // Display saving icon
    $('#drw_saving').removeClass('hidden');
    // Get start time before ajax call is made
    var starttime = new Date().getTime();
    // Make ajax call
    $.post(app_path_webroot+"DataQuality/data_resolution_popup.php?pid="+pid+'&instance='+instance, {
        action: 'save', field_name: field, event_id: event_id, record: record,
        form_name: (page == 'DataEntry/index.php' ? getParameterByName('page') : ''),
        comment: comment,
        response_requested: (($('#dc-response_requested').length && $('#dc-response_requested').prop('checked')) ? 1 : 0),
        upload_doc_id: upload_doc_id, delete_doc_id: delete_doc_id,
        assigned_user_id: ($('#dc-assigned_user_id').length ? $('#dc-assigned_user_id').val() : ''),
        assigned_user_id_notify_email: ($('#assigned_user_id_notify_email').prop('checked') ? '1' : '0'),
        assigned_user_id_notify_messenger: ($('#assigned_user_id_notify_messenger').prop('checked') ? '1' : '0'),
        status: query_status, send_back: send_back,
        response: response, reopen_query: reopen_query,
        rule_id: rule_id
    }, function(data){
        if (data=='0') {
            alert(woops);
        } else {
            // Parse JSON
            var json_data = JSON.parse(data);
            // Update new timestamp for saved row (in case different)
            $('#newDCnow').html(json_data.tsNow);
            // Display saved icon
            $('#drw_saving').addClass('hidden');
            $('#drw_saved').removeClass('hidden');
            // Set bg color of last row to green
            $('table#newDCHistory tr td.data').css({'background-color':'#C1FFC1'});
            // Page-dependent actions
            if (page == 'DataQuality/field_comment_log.php') {
                // Field Comment Log page: reload table
                reloadFieldCommentLog();
            } else if (page == 'DataQuality/resolve.php') {
                // Data Quality Resolve Issues page: reload table
                dataResLogReload();
            } else if (page == 'DataQuality/index.php') {
                // Update count in tab badge
                $('#dq_tab_issue_count').html(json_data.num_issues);
            }
            // Update icons/counts
            if (page == 'DataEntry/index.php' || page == 'DataQuality/index.php') {
                // Data Quality Find Issues page: Change balloon icon for this field/rule result
                $('#dc-icon-'+rule_id+'_'+field+'__'+event_id+'__'+record).attr('src', json_data.icon);
                $('#table-dq_rules_table_single_record #dc-icon-'+rule_id+'___'+record).attr('src', json_data.icon);
                // Update number of comments for this field/rule result
                $('#dc-numcom-'+rule_id+'_'+field+'__'+event_id+'__'+record).html(json_data.num_comments);
                $('#table-dq_rules_table_single_record #dc-numcom-'+rule_id+'___'+record).html(json_data.num_comments);
                // Data Entry page: Change balloon icon for field
                $('#dc-icon-'+field).attr('src', json_data.icon).attr('onmouseover', '').attr('onmouseout', '');
            }
            // CLOSE DIALOG: Get response time of ajax call (to ensure closing time is always the same even with longer requests)
            var endtime = new Date().getTime() - starttime;
            var delaytime = 1500;
            var timeouttime = (endtime >= delaytime) ? 1000 : (delaytime - endtime);
            setTimeout(function(){
                // Close dialog with fade effect
                $('#data_resolution').dialog('option', 'hide', {effect:'fade', duration: 500}).dialog('close');
                // Highlight table row in form (to emphasize where user was) - Data Entry page only
                if (page == 'DataEntry/index.php') {
                    setTimeout(function(){
                        highlightTableRow(field+'-tr',3000);
                    },200);
                }
                // Destroy the dialog so that fade effect doesn't persist if reopened
                setTimeout(function(){
                    if ($('#data_resolution').hasClass('ui-dialog-content')) $('#data_resolution').dialog('destroy');
                },500);
            }, timeouttime);
        }
    });
}

// Reassign data query to other user
function reassignDataQuery(status_id)
{
    if ($('#dc-assigned_user_id').val() == '') {
        simpleDialog('Please select a user to reassign.');
        return;
    }
    // Get start time before ajax call is made
    var starttime = new Date().getTime();
    showProgress(1);
    $.post(app_path_webroot+"DataQuality/data_resolution_popup.php?pid="+pid, { action: 'reassign', status_id: status_id, assigned_user_id: $('#dc-assigned_user_id').val(),
        assigned_user_id_notify_email: ($('#assigned_user_id_notify_email').prop('checked') ? '1' : '0'),
        assigned_user_id_notify_messenger: ($('#assigned_user_id_notify_messenger').prop('checked') ? '1' : '0')
    }, function(data){
        showProgress(0,0);
        if (data != '1') {
            alert(woops);
            return;
        }
        // CLOSE DIALOG: Get response time of ajax call (to ensure closing time is always the same even with longer requests)
        var endtime = new Date().getTime() - starttime;
        var delaytime = 1000;
        var timeouttime = (endtime >= delaytime) ? 1000 : (delaytime - endtime);
        setTimeout(function(){
            // Close dialog with fade effect
            $('#data_resolution').dialog('option', 'hide', {effect:'fade', duration: 500}).dialog('close');
            // Destroy the dialog so that fade effect doesn't persist if reopened
            setTimeout(function(){
                if ($('#data_resolution').hasClass('ui-dialog-content')) $('#data_resolution').dialog('destroy');
            },500);
        }, timeouttime);
    });
}

// Delete file upload field for data query
function deleteDataQueryFile(res_id, upload_doc_id, error_msg, confirm_msg, cancel_btn, delete_btn, title_msg)
{
    if (!super_user) {
        simpleDialog(error_msg);
        return;
    }
    simpleDialog(confirm_msg,title_msg,"","",null,cancel_btn,"deleteDataQueryFileDo('"+res_id+"','"+upload_doc_id+"','"+error_msg+"');",delete_btn);
}

// Delete file upload field for data query
function deleteDataQueryFileDo(res_id, upload_doc_id, error_msg)
{
    if (!super_user) {
        simpleDialog(error_msg);
        return;
    }
    // Get start time before ajax call is made
    var starttime = new Date().getTime();
    showProgress(1);
    $.post(app_path_webroot+"DataQuality/data_resolution_file_delete.php?pid="+pid, { res_id: res_id, id: upload_doc_id }, function(data){
        showProgress(0,0);
        simpleDialog(data);
        // CLOSE DIALOG: Get response time of ajax call (to ensure closing time is always the same even with longer requests)
        var endtime = new Date().getTime() - starttime;
        var delaytime = 1000;
        var timeouttime = (endtime >= delaytime) ? 1000 : (delaytime - endtime);
        setTimeout(function(){
            // Close dialog with fade effect
            $('#data_resolution').dialog('option', 'hide', {effect:'fade', duration: 500}).dialog('close');
            // Destroy the dialog so that fade effect doesn't persist if reopened
            setTimeout(function(){
                if ($('#data_resolution').hasClass('ui-dialog-content')) $('#data_resolution').dialog('destroy');
            },500);
        }, timeouttime);
    });
}

// Open pop-up dialog for viewing data resolution for a field
function dataResPopup(field,event_id,record,existing_record,rule_id,instance) {
    if (typeof instance == "undefined") instance = 1;
    if (record == null) record = getParameterByName('id');
    if (existing_record == null) existing_record = $('form#form :input[name="hidden_edit_flag"]').val();
    if (rule_id == null) rule_id = '';
    // Hide floating field tooltip on form (if visible)
    $('#tooltipDRWsave').hide();
    showProgress(1,0);
    // Get dialog content via ajax
    $.post(app_path_webroot+"DataQuality/data_resolution_popup.php?pid="+pid+'&instance='+instance, { rule_id: rule_id, action: 'view', field_name: field, event_id: event_id, record: record, existing_record: existing_record }, function(data){
        showProgress(0,0);
        // Parse JSON
        var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
        if (existing_record == 1) {
            // Get window scroll position before we load dialog content
            var windowScrollTop = $(window).scrollTop();
            // Load the dialog content
            initDialog('data_resolution');
            $('#data_resolution').html(json_data.content);
            initWidgets();
            // Set dialog width
            var dialog_width = (data_resolution_enabled == '1') ? 700 : 750;
            // Open dialog
            $('#data_resolution').dialog({ bgiframe: true, title: json_data.title, modal: true, width: dialog_width, zIndex: 3999, destroy: 'fade' });
            // Adjust table height within the dialog to fit
            var existingRowsHeightMax = 300;
            if ($('#existingDCHistoryDiv').height() > existingRowsHeightMax) {
                $('#existingDCHistoryDiv').height(existingRowsHeightMax);
                $('#existingDCHistoryDiv').scrollTop( $('#existingDCHistoryDiv')[0].scrollHeight );
                // Reset window scroll position, if got moved when dialog content was loaded
                $(window).scrollTop(windowScrollTop);
                // Re-center dialog
                $('#data_resolution').dialog('option', 'position', { my: "center", at: "center", of: window });
            }
            // Put cursor inside text box
            $('#dc-comment').focus();
        } else {
            // If record does not exist yet, then give warning that will not work
            initDialog('data_resolution');
            $('#data_resolution').css('background-color','#FFF7D2').html(json_data.content);
            initWidgets();
            $('#data_resolution').dialog({ bgiframe: true, title: json_data.title, modal: true, width: 500, zIndex: 3999 });
        }
        // Set user assign query drop-down list as a Select2 drop-down
        $('#dc-assigned_user_id').select2();
    });
}

// Edit a Field Comment
function editFieldComment(res_id, form, openForEditing, cancelEdit) {
    var td_div = $('table#existingDCHistory tr#res_id-'+res_id+' td:eq(3) div:first');
    if (openForEditing) {
        // Make the text an editable textarea
        var comment = br2nl(td_div.html().replace(/\t/g,'').replace(/\r/g,'').replace(/\n/g,''));
        var textarea = '<div id="dc-comment-edit-div-'+res_id+'"><textarea id="dc-comment-edit-'+res_id+'" class="x-form-field notesbox" style="height:45px;width:97%;display:block;margin-bottom:2px;">'+comment+'</textarea>'
            + '<button id="dc-comment-savebtn-'+res_id+'" class="jqbuttonmed" style="font-size:11px;font-weight:bold;" onclick="editFieldComment('+res_id+',\''+form+'\',0,0);">Save</button>'
            + '<button id="dc-comment-cancelbtn-'+res_id+'" class="jqbuttonmed" style="font-size:11px;" onclick="editFieldComment('+res_id+',\''+form+'\',0,1);">Cancel</button></div>';
        td_div.hide().after(textarea);
        $('#dc-comment-savebtn-'+res_id+', #dc-comment-cancelbtn-'+res_id).button();
        $('table#existingDCHistory tr#res_id-'+res_id+' td:eq(0) img').css('visibility','hidden');
    } else if (cancelEdit) {
        // Cancel the edit (return as it was)
        $('table#existingDCHistory tr#res_id-'+res_id+' td:eq(0) img').css('visibility','visible');
        td_div.show();
        $('#dc-comment-edit-div-'+res_id).remove();
    } else {
        var comment = $('#dc-comment-edit-'+res_id).val();
        // Make ajax call
        $.post(app_path_webroot+"DataQuality/field_comment_log_edit_delete_ajax.php?pid="+pid, { action: 'edit', comment: comment, form_name: form, res_id: res_id}, function(data){
            if (data=='0') {
                alert(woops);
            } else {
                // Parse JSON
                var json_data = JSON.parse(data);
                $('table#existingDCHistory tr#res_id-'+res_id+' td:eq(0) img').css('visibility','visible');
                highlightTableRowOb( $('table#existingDCHistory tr#res_id-'+res_id), 3000);
                td_div.show().html(nl2br(comment));
                $('#dc-comment-edit-div-'+res_id).remove();
                // Display the "edit" text
                $('table#existingDCHistory tr#res_id-'+res_id+' .fc-comment-edit').show();
            }
        });
    }
}

// Delete a Field Comment
function deleteFieldComment(res_id, form, confirmDelete) {
    var url = app_path_webroot+"DataQuality/field_comment_log_edit_delete_ajax.php?pid="+pid;
    // Make ajax call
    $.post(url, { action: 'delete', form_name: form, res_id: res_id, confirmDelete: confirmDelete}, function(data){
        if (data=='0') {
            alert(woops);
        } else {
            // Parse JSON
            var json_data = JSON.parse(data);
            if (confirmDelete) {
                simpleDialog(json_data.html,json_data.title,null,null,null,json_data.closeButton,'deleteFieldComment('+res_id+', "'+form+'",0);',json_data.actionButton);
            } else {
                $('table#existingDCHistory tr#res_id-'+res_id+' td:eq(0) img').css('visibility','hidden');
                $('table#existingDCHistory tr#res_id-'+res_id+' td').each(function(){
                    $(this).removeClass('data').addClass('red').css('color','gray');
                });
                setTimeout(function(){
                    $('table#existingDCHistory tr#res_id-'+res_id).hide('fade');
                },3000);
            }
        }
    });
}

// Data Cleaner icon onmouseover/out actions
function dc1(ob) {
    ob.src = app_path_images+'balloon_left.png';
}
function dc2(ob) {
    ob.src = app_path_images+'balloon_left_bw2.gif';
}

// Missing Data icon onmouseover/out actions
function md1(ob) {
    ob.src = app_path_images+'missing_active.png';
}
function md2(ob) {
    if (ob.missing!=true){
        ob.src = app_path_images+'missing.png';
    }
}

// Data history icon onmouseover/out actions
function dh1(ob) {
    ob.src = app_path_images+'history_active.png';
}
function dh2(ob) {
    ob.src = app_path_images+'history.png';
}

// Open pop-up dialog for viewing data history of a field
function dataHist(field,event_id,record) {
    // Get window scroll position before we load dialog content
    var windowScrollTop = $(window).scrollTop();
    if (record == null) record = decodeURIComponent(getParameterByName('id'));
    if ($('#data_history').hasClass('ui-dialog-content')) $('#data_history').dialog('destroy');
    $('#dh_var').html(field);
    $('#data_history2').html('<p><img src="'+app_path_images+'progress_circle.gif"> Loading...</p>');
    $('#data_history').dialog({ bgiframe: true, title: 'Data History for variable "'+field+'" for record "'+record+'"', modal: true, width: 650, zIndex: 3999, buttons: {
            Close: function() { $(this).dialog('destroy'); } }
    });
    $.post(app_path_webroot+"DataEntry/data_history_popup.php?pid="+pid, {field_name: field, event_id: event_id, record: record, instance: getParameterByName('instance') }, function(data){
        $('#data_history2').html(data);
        // Adjust table height within the dialog to fit
        var tableHeightMax = 300;
        if ($('#data_history3').height() > tableHeightMax) {
            $('#data_history3').height(tableHeightMax);
            $('#data_history3').scrollTop( $('#data_history3')[0].scrollHeight );
            // Reset window scroll position, if got moved when dialog content was loaded
            $(window).scrollTop(windowScrollTop);
            // Re-center dialog
            $('#data_history').dialog('option', 'position', { my: "center", at: "center", of: window });
        }
        // Highlight the last row in DH table
        if ($('table#dh_table tr').length > 1) {
            setTimeout(function(){
                highlightTableRowOb($('table#dh_table tr:last'), 3500);
            },300);
        }
    });
}

// Chack Two-byte character (for Japanese)
function checkIsTwoByte(value) {
    for (var i = 0; i < value.length; ++i) {
        var c = value.charCodeAt(i);
        if (c >= 256 || (c >= 0xff61 && c <= 0xff9f)) {
            return true;
        }
    }
    return false;
}

// Change status of project
function doChangeStatus(archive,super_user_action,user_email,randomization,randProdAllocTableExists) {
    randomization = (randomization == null) ? 0 : (randomization == 1 ? 1 : 0);
    randProdAllocTableExists = (randProdAllocTableExists == null) ? 0 : (randProdAllocTableExists == 1 ? 1 : 0);
    var delete_data = 0;
    if (randomization == 1 && randProdAllocTableExists == 0) {
        alert('ERROR: This project is utilizing the randomization module and cannot be moved to production status yet because a randomization allocation table has not been uploaded for use in production status. Someone with appropriate rights must first go to the Randomization page and upload an allocation table.');
        return false;
    }
    var alertMessage =  '<div class="select-radio-button-msg" style="color: #C00000; font-size: 16px; margin-top: 10px;">Please select one of the options above before moving to production.</div>';
    if (archive == 0 && $('#delete_data').length) {
        if ($('input[name="data"]:checked').prop('id') !== undefined ) {
            if ($('input[name="data"]:checked').prop('id') == "delete_data") {
                delete_data = 1;
                $('.select-radio-button-msg').remove();
                // Make user confirm that they want to delete data
                if (archive == 0 && super_user_action != 'move_to_prod') { // Don't show prompt when super users are processing users' requests to push to prod
                    if (!confirm("DELETE ALL DATA?\n\nAre you sure you really want to delete all existing data when the project is moved to production? If not, click Cancel and change the setting inside the yellow box.")) {
                        return false;
                    }
                }
            } else if (randomization) {
                // If not deleting all data BUT using randomization module, remind that the randomization field's values will be erased
                if (!confirm("WARNING: RANDOMIZATION FIELD'S DATA WILL BE DELETED\n\nSince you have enabled the randomization module, please be advised that if any records contain a value for your randomization field (i.e. have been randomized), those values will be PERMANENTLY DELETED once the project is moved to production. (Only data for that field will be deleted. Other fields will not be touched.) Is this okay?")) {
                    return false;
                }
            }
        }else if($('input[name="data"]:checked').prop('id') !== undefined){
            if ($('input[name="data"]:checked').prop('id') == "keep_data") {
                delete_data = 0;
                $('.select-radio-button-msg').remove();
            }
        }else{//if both undefined display message
            $('.select-radio-button-msg').remove();
            $('#status_dialog .yellow').append(alertMessage);
            return false;
        }
    }
    $(".ui-dialog-buttonpane :button:eq(1)").html('Please wait...').button("disable");
    $(".ui-dialog-buttonpane :button:eq(0)").css("display","none");
    $.post(app_path_webroot+'ProjectGeneral/change_project_status.php?pid='+pid, { current_status: status, do_action_status: 1, archive: archive, delete_data: delete_data,
            survey_pid_move_to_prod_status: survey_pid_move_to_prod_status_record, survey_pid_move_to_analysis_status: survey_pid_move_to_analysis_status_record,
            survey_pid_mark_completed: survey_pid_mark_completed_record},
        function(data) {
            if (archive == 1) $('#completed_time_dialog').dialog('destroy'); else $('#status_dialog').dialog('destroy');
            if (data != '0') {
                if (archive == 1) {
                    alert("The project has now been marked as COMPLETED. The project and its data will remain in the system and cannot be modified. "
                        + "Only a REDCap administrator may return the project back to its previous status.\n\n(You will now be redirected back to the Home page.)");
                    window.location.href = app_path_webroot_full+'index.php?action=myprojects';
                } else {
                    if (data == '1') {
                        if (super_user_action == 'move_to_prod') {
                            $.get(app_path_webroot+'ProjectGeneral/notifications.php', { pid: pid, type: 'move_to_prod_user', this_user_email: user_email, survey_pid_move_to_prod_status: getParameterByName('survey_pid_move_to_prod_status') },
                                function(data2) {
                                    if(self!=top){//decect if in iframe
                                        simpleDialog('The user request for their REDCap project to be moved to production status has been approved.',
                                            'Request Approved / User Notified',null,null,function(){
                                                closeToDoListFrame();
                                            });
                                    }else{
                                        window.location.href = app_path_webroot_full+'index.php?action=approved_movetoprod&user_email='+user_email;
                                    }
                                }
                            );
                        } else if (status == '2') {
                            alert("The project has now been moved back to PRODUCTION status.\n\n(The page will now be reloaded to reflect the change.)");
                            window.location.href = app_path_webroot+'ProjectSetup/other_functionality.php?pid='+pid;
                        } else {
                            window.location.href = app_path_webroot+'ProjectSetup/index.php?pid='+pid+'&msg=movetoprod';
                        }
                    } else {
                        alert("The project has now been set to ANALYSIS/CLEANUP status.\n\n(The page will now be reloaded to reflect the change.)");
                        window.location.reload();
                    }
                }
            } else {
                alert('ERROR: The action could not be performed.');
            }
        }
    );
}

//Function to begin editing an event/visit
function beginEdit(arm,event_id,stopText) {
    if (typeof stopText == 'undefined') stopText = '';
    if (stopText != '') {
        simpleDialog(stopText);
        return;
    }
    document.getElementById("progress").style.visibility = "visible";
    $.get(app_path_webroot+"Design/define_events_ajax.php", { pid: pid, arm: arm, edit: '', event_id: event_id },
        function(data) {
            document.getElementById("table").innerHTML = data;
            initDefineEvents();
            setCaretToEnd(document.getElementById("day_offset_edit"));
        }
    );
}
//Function for editing an event/visit
function editVisit(arm,event_id) {
    if (trim($("#descrip_edit").val()) == "" || ($("#offset_min_edit").length && ($("#offset_min_edit").val() == "" || $("#offset_max_edit").val() == "" || $("#day_offset_edit").val() == ""))) {
        simpleDialog("Please enter a value for Days Offset and Event Name");
        return;
    } else if ($("#offset_min_edit").length) {
        var offset_min = $("#offset_min_edit").val();
        var offset_max = $("#offset_max_edit").val();
        var day_offset = $("#day_offset_edit").val();
    } else {
        var offset_min = '';
        var offset_max = '';
        var day_offset = '';
    }
    if ($("#offset_min_edit").length) {
        document.getElementById("day_offset_edit").disabled = true;
        document.getElementById("offset_min_edit").disabled = true;
        document.getElementById("offset_max_edit").disabled = true;
    }
    document.getElementById("editbutton").disabled = true;
    document.getElementById("descrip_edit").disabled = true;
    document.getElementById("progress").style.visibility = "visible";
    $.post(app_path_webroot+"Design/define_events_ajax.php", { pid: pid, arm: arm, action: 'edit', event_id: event_id, offset_min: offset_min, offset_max: offset_max, day_offset: day_offset, descrip: document.getElementById("descrip_edit").value, custom_event_label: document.getElementById("custom_event_label_edit").value },
        function(data) {
            document.getElementById("table").innerHTML = data;
            initDefineEvents();
            highlightTableRow('design_'+event_id,2000);
        }
    );
}
//Function for adding an event/visit
function addEvents(arm,num_events_total) {
    if (trim($("#descrip").val()) == "") {
        simpleDialog("Please enter a name for the event you wish to add");
        $("#descrip").val(jQuery.trim($("#descrip").val()));
        return;
    } else if ($("#offset_min").length && ($("#offset_min").val() == "" || $("#offset_max").val() == "" || $("#day_offset").val() == "" || trim($("#descrip").val()) == "")) {
        simpleDialog("Please enter a value for Days Offset and Event Name");
        $("#descrip").val(jQuery.trim($("#descrip").val()));
        return;
    } else if ($("#offset_min").length) {
        var offset_min = $("#offset_min").val();
        var offset_max = $("#offset_max").val();
        var day_offset = $("#day_offset").val();
    } else {
        var offset_min = 0;
        var offset_max = 0;
        var day_offset = 9999;
    }
    // Check if event name is duplicated
    var event_names = "|";
    $("#event_table .evt_name").each(function(){
        event_names += jQuery.trim($(this).html()) + "|";
    });
    if (event_names.indexOf("|"+jQuery.trim($("#descrip").val())+"|") > -1) {
        simpleDialog("You have duplicated an existing event name. All events must have unique names. Please enter a different value.",null,null,null,'$("#descrip").focus()');
        return;
    }
    document.getElementById("progress").style.visibility = "visible";
    document.getElementById("addbutton").disabled = true;
    document.getElementById("descrip").disabled = true;
    if ($("#offset_min").length) {
        document.getElementById("day_offset").disabled = true;
        document.getElementById("offset_min").disabled = true;
        document.getElementById("offset_max").disabled = true;
    }
    $.get(app_path_webroot+"Design/define_events_ajax.php", { pid: pid, arm: arm, action: 'add', offset_min: offset_min, offset_max: offset_max, day_offset: day_offset, descrip: document.getElementById("descrip").value, custom_event_label: document.getElementById("custom_event_label").value },
        function(data) {
            $("#table").html(data);
            initDefineEvents();
            highlightTableRow('design_'+$("#new_event_id").val(), 2000);
            $('#descrip').focus();
            //Reload page if just added second event (so that all Longitudinal functions show)
            if (num_events_total == 1) {
                showProgress(1);
                setTimeout("window.location.reload();",300);
            } else {
                // If add event for first time on page, show tooltip reminder about designating forms
                if (hasShownDesignatePopup == 0) {
                    $("#popupTrigger").trigger('mouseover');
                    hasShownDesignatePopup++;
                }
            }
        }
    );
}

// Init Designate Instruments page
function initDesigInstruments() {
    initButtonWidgets();
    $('#downloadUploadEventsInstrDropdown').menu();
    $('#downloadUploadEventsInstrDropdownDiv ul li a').click(function(){
        $('#downloadUploadEventsInstrDropdownDiv').hide();
    });
    // Enable fixed table headers for event grid
    enableFixedTableHdrs('event_grid_table');
}

function getUrlParameter(param) {
    var pageURL = decodeURIComponent(window.location.search.substring(1)),
        URLVariables = pageURL.split('&'),
        parameterName,
        i;

    for (i = 0; i < URLVariables.length; i++) {
        parameterName = URLVariables[i].split('=');

        if (parameterName[0] === param) {
            return parameterName[1] === undefined ? 'request_time' : parameterName[1];
        }
    }
}

// Init Define Events page
function initDefineEvents() {
    initButtonWidgets();
    $('#downloadUploadEventsArmsDropdown').menu();
    $('#downloadUploadEventsArmsDropdownDiv ul li a').click(function(){
        $('#downloadUploadEventsArmsDropdownDiv').hide();
    });
    // If not using scheduling, then enable drag-n-drop for events in table
    if (!scheduling && $('.dragHandle').length > 1) {
        // Modify event order: Enable drag-n-drop on table
        $('#event_table').tableDnD({
            onDrop: function(table, row) {
                // Loop through table
                var event_ids = new Array(); var i=0;
                $("#event_table tr").each(function() {
                    if ($(this).attr('id') != null) {
                        event_ids[i++] = $(this).attr('id').substr(7);
                    }
                });
                // Save event order
                $.post(app_path_webroot+'Design/define_events_ajax.php?pid='+pid, { action: 'reorder_events', arm: $('#arm').val(), event_ids: event_ids.join(',') }, function(data){
                    $('#table').html(data);
                    initDefineEvents();
                    highlightTableRow($(row).attr('id'),2000);
                });
            },
            dragHandle: "dragHandle"
        });
        // Create mouseover image for drag-n-drop action and enable button fading on row hover
        $("#event_table tr:not(.nodrop)").mouseenter(function() {
            $(this.cells[0]).css('background','#fafafa url("'+app_path_images+'updown.gif") no-repeat center');
        }).mouseleave(function() {
            $(this.cells[0]).css('background','');
        });
        // Set up drag-n-drop pop-up tooltip
        $('.dragHandle').mouseenter(function() {
            $("#reorderTrigger").trigger('mouseover');
        }).mouseleave(function() {
            $("#reorderTrigger").trigger('mouseout');
        });
        // Miscellaneous things to init
        $('#reorderTip').hide('fade');
        $("#reorderTrigger").tooltip2({ tip: '#reorderTip', relative: true, effect: 'fade', position: 'top center', offset: [35,0] });
    }
}

//Open pop-up for month/year/week conversion to days
function openConvertPopup() {
    if ($('#convert').hasClass('ui-dialog-content')) $('#convert').dialog('destroy');
    var this_day = $('#day_offset').val();
    if (this_day != '') {
        $("#calc_year").val(this_day/365);
        $("#calc_month").val(this_day/30);
        $("#calc_week").val(this_day/7);
        $("#calc_day").val(this_day);
    } else {
        $("#calc_year").val('');
        $("#calc_month").val('');
        $("#calc_week").val('');
        $("#calc_day").val('');
    }
    var pos = $('#day_offset').offset();
    $('#convTimeBtn').addClass('ui-state-default ui-corner-all');
    $('#convert').addClass('simpleDialog').dialog({ bgiframe: true, modal: true, width: 350, height: 250});
}
//Provide month/year/week conversion to days
function calcDay(el) {
    var isNumeric=function(symbol){var objRegExp=/(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;return objRegExp.test(symbol);};
    if (!isNumeric(el.value)) {
        var oldval = el.value;
        $("#calc_year").val('');
        $("#calc_month").val('');
        $("#calc_week").val('');
        $("#calc_day").val('');
        $("#"+el.id).val(oldval);
    } else if (el.id == "calc_year") {
        $("#calc_month").val(el.value*12);
        $("#calc_week").val(el.value*52);
        $("#calc_day").val(Math.round(el.value*365));
    } else if (el.id == "calc_month") {
        $("#calc_year").val(el.value/12);
        $("#calc_week").val(el.value*4);
        $("#calc_day").val(Math.round(el.value*30));
    } else if (el.id == "calc_week") {
        $("#calc_year").val(el.value/52);
        $("#calc_month").val(el.value/4);
        $("#calc_day").val(Math.round(el.value*7));
    } else if (el.id == "calc_day") {
        $("#calc_year").val(el.value/365);
        $("#calc_month").val(el.value/30);
        $("#calc_week").val(el.value/7);
    }
    //Value of 9999 days is max
    if ($("#calc_day").val() != '' && isNumeric($("#calc_day").val())) {
        if ($("#calc_day").val() > 9999) $("#calc_day").val(9999);
    }
}


//Function for deleting an event/visit
function delVisit(arm,event_id,num_events_total,num_mycaptasks_total) {
    var msg = "";
    if (num_mycaptasks_total > 0) {
        msg = "\n\nOne or more MyCap tasks are designated to this event. Please note, upon deletion of this event, MyCap task settings related to this event will be disabled.";
    }
    if (confirm('DELETE EVENT?\n\nAre you sure you wish to delete this event?'+msg)) {
        if (status > 0) {
            if (!confirm('ARE YOU SURE?\n\nDeleting this event will DELETE ALL DATA collected for this event. Are you sure you wish to delete this event?')) {
                return;
            }
        }
        document.getElementById("progress").style.visibility = "visible";
        $.get(app_path_webroot+"Design/define_events_ajax.php", { pid: pid, arm: arm, action: 'delete', event_id: event_id },
            function(data) {
                document.getElementById("table").innerHTML = data;
                initDefineEvents();
                //Reload page if just added second event (so that all Longitudinal functions show)
                if (num_events_total == 2) {
                    showProgress(1);
                    setTimeout("window.location.reload();",300);
                }
            }
        );
    }
}
function delVisit2(arm,event_id,num_events_total) {
    if (confirm('DELETE EVENT?\n\nAre you sure you wish to delete this event?')) {
        document.getElementById("progress").style.visibility = "visible";
        $.get(app_path_webroot+"Design/define_events_ajax.php", { pid: pid, arm: arm, action: 'delete', event_id: event_id },
            function(data) {
                document.getElementById("table").innerHTML = data;
                initDefineEvents();
                //Reload page if just added second event (so that all Longitudinal functions show)
                if (num_events_total == 2) {
                    showProgress(1);
                    window.location.reload();
                }
            }
        );
    }
}

//Place focus at end of text in an input Text field
function setCaretToEnd(el) {
    try {
        if (isIE) {
            if (el.createTextRange) {
                var v = el.value;
                var r = el.createTextRange();
                r.moveStart('character', v.length);
                r.select();
                return;
            }
            el.focus();
            return;
        }
        el.focus();
    } catch(e) { }
}

function delete_doc(docs_id) {
    if(confirm(delete_doc_msg)) {
        showProgress(1);
        $.post(app_path_webroot+page+"?pid="+pid,{ 'delete': docs_id },function(data){
            window.location.href = app_path_webroot+page+"?pid="+pid;
        });
    }
    return false;
}

var LogicEditorOpenTime = performance.now();
var LogicEditorClosing = false;
var LogicEditorOpening = false;
function openLogicEditor(opener, fullscreen, onUpdateFn)
{
    // Already opening?
    if (LogicEditorOpening) return;
    if(opener instanceof HTMLElement) {
        // convert HTMLElement to jQuery object
        opener = $(opener);
    }
    // Load the ACE Editor JS file
    if (typeof window['ace'] == 'undefined') {
        LogicEditorOpening = true;
        $.getScript(app_path_webroot+"Resources/js/Libraries/ace.js").then(function() {
            // Reopen after script has loaded
            setTimeout(() => {
                LogicEditorOpening = false;
                openLogicEditor(opener, fullscreen, onUpdateFn);
            }, 0);
        });
        return;
    }
    // Prevent multiple retriggering of function in small time period
    if ((performance.now()-LogicEditorOpenTime) <= 200) return;
    LogicEditorOpenTime = performance.now();
    // Init settings
    $('#rc-ace-editor').popover('hide');
    initLogicSuggestSearchTip();
    if (typeof fullscreen == 'undefined') fullscreen = false;
    if (typeof origValueOverride == 'undefined') origValueOverride = null;
    // If opener lacks an ID, then add one
    var openerId = opener.attr('id');
    if (openerId == null) {
        openerId = "rc-ace-editor-opener-"+Math.floor(Math.random()*10000000000000000);
        opener.attr('id', openerId);
    }
    // Set the original value so we know how to revert the opener value if user cancels
    if (opener.attr('val') == null) opener.attr('val', opener.val());
    // Set fullscreen or regular size settings
    if (fullscreen) {
        var editorStyle = 'height:'+round($(window).height()*0.75)+'px;';
        var dialogWidth = round($(window).width()*0.9);
        var buttonText1 = window.lang.global_167;
    } else {
        var editorStyle = 'height:400px;';
        var dialogWidth = 750;
        var buttonText1 = window.lang.global_168;
    }
    // Open dialog
    var html =  '<div id="rc-ace-editor-parent"><div class="mb-3">'+window.lang.global_208+
                '<span class="nowrap ms-2"><span style="color:#777;margin-right:5px;">'+window.lang.edit_project_186+'</span>'+
                    '<button class="btn btn-xs btn-rcgreen btn-rcgreen-light" style="font-size:11px;padding:0px 3px 1px;line-height:14px;margin-right:3px;" onclick="smartVariableExplainPopup();return false;">[<i class="fas fa-bolt fa-xs" style="margin:0 1px;"></i>] '+window.lang.global_146+'</button> ' +
                    '<button class="btn btn-xs btn-primaryrc btn-primaryrc-light" style="font-size:11px;padding:1px 3px;line-height:14px;margin-right:3px;" onclick="specialFunctionsExplanation();return false;"><i class="fas fa-square-root-alt" style="margin:0 2px 0 1px;"></i> '+window.lang.design_839+'</button> '+
                    '<button class="btn btn-xs btn-rcred btn-rcred-light" style="font-size:11px;padding:1px 3px;line-height:14px;margin-right:3px;" onclick="actionTagExplainPopup(1);return false;">@ '+window.lang.global_132+'</button> '+
                '<span style="color:#777;margin-right:3px;">'+window.lang.design_962+'</span> <button class="btn btn-xs btn-defaultrc" style="font-size:11px;padding:1px 3px;line-height:14px;" onclick="codebookPopup();return false;"><i class="fas fa-book" style="margin:0 2px 0 1px;"></i> '+window.lang.design_482+'</button>'+
                '</span>'+
                '</div><div id="rc-ace-editor" style="'+editorStyle+'"></div><div id="rc-ace-editor-status"></div>'+
                '<div style="font-size:12px;color:#777;margin:10px 0 0;line-height:1.2;"><i class="fa-regular fa-lightbulb"></i> '+window.lang.global_266+
                ' <a href="javascript:;" style="text-decoration:underline;font-size:12px;" onclick="simpleDialog(null,\''+window.lang.global_267+'\',\'rc-ace-editor-example\',700);">'+window.lang.global_267+'</a></div></div>';
    initDialog('rc-ace-editor-dialog', html);
    var dlgDiv = $('#rc-ace-editor-dialog');
    dlgDiv.dialog({ bgiframe: true, modal: true, width: dialogWidth, title: window.lang.global_170, buttons:
        // Cancel
        [{text: window.lang.global_53, click: function() {
            try { 
                $('#'+opener.prop('id')+'_Ok').not('.logicEditorDoNotHide').hide(); 
            } catch(e) { }
            try{ dlgDiv.dialog('close'); }catch(e){ }
            // Get rid of any helpers
            $('.LSC-element').hide();
        }},
        // Update and Close Editor
        { text: window.lang.global_169, click: function() {
            // Update val attribute
            opener.attr('val', opener.val());
            setTimeout(function(){
                opener.removeAttr('val');
            },500);
            try {
                $('#'+opener.prop('id')+'_Ok').not('.logicEditorDoNotHide').hide();
            } catch(e) { }
            try{ dlgDiv.dialog('destroy'); }catch(e){ }
            dlgDiv.remove();
            $('.popover').popover('hide');
            // UI effect
            if (opener.css('background-color') == 'rgb(255, 255, 255)') {
                opener.effect('highlight', { }, 2000);
            }
            if (typeof onUpdateFn == 'function') {
                onUpdateFn();
            }
            // trigger change event
            var inputEvent = new Event('input');
            const element = opener.get(0);
            if(element instanceof HTMLElement) {
                element.dispatchEvent(inputEvent);
            }
        }},
        // Fullscreen/Regular View
        {text: buttonText1, click: function() {
            try{ dlgDiv.dialog('destroy'); }catch(e){ }
            dlgDiv.remove();
            $('.popover').popover('hide');
            openLogicEditor(opener, !fullscreen, onUpdateFn);
        }}]
    });
    dlgDiv.parent().find('.ui-dialog-buttonset .ui-button:eq(0)').css({ 'color': '#A00000'}).prepend('<i class="fas fa-times"></i> ');
    dlgDiv.parent().find('.ui-dialog-buttonset .ui-button:eq(1)').addClass('font-weight-bold').css({ 'color': '#008000'}).prepend('<i class="fas fa-check"></i> ');
    dlgDiv.parent().find('.ui-dialog-buttonset .ui-button:eq(2)').css({ 'font-size': '13px', 'margin-right': '50px', 'color': '#666'}).prepend(fullscreen ? '<i class="fas fa-compress-arrows-alt"></i> ' : '<i class="fas fa-expand-arrows-alt"></i> ');
    dlgDiv.bind('dialogclose', function(){
        // Prevent multiple retriggering of function in small time period
        if (LogicEditorClosing) return;
        LogicEditorClosing = true;
        // Reset the opener's value
        opener.val( opener.attr('val') );
        // Also remove the "val" attribute value to reset it for next time (set with a timeout because this function may be triggered multiple times when closing)
        setTimeout(function(){
            opener.removeAttr('val');
        },500);
        // Destroy the dialog
        try{ dlgDiv.dialog('destroy'); }catch(e){ }
        dlgDiv.remove();
        $('.popover').popover('hide');
    });
    // Init ACE Editor and set it to auto-update its corresponding textarea
    var editor = ace.edit("rc-ace-editor");
    editor.setShowPrintMargin(false);
    editor.renderer.setShowGutter(false);
    editor.setOptions({ wrap: true, fontSize: "11pt" });
    editor.getSession().setValue( opener.val() );
    editor.getSession().on('change', function(){
        opener.val(editor.getSession().getValue()).trigger('keydown');
    });
    editor.keyBinding.addKeyboardHandler(function(data, hashId, keyString, keyCode, e) {
        // Set this for logic field suggest function to use
        if (isinteger(keyCode)) logicFieldSuggestLastKeyDown = keyCode;
    })
    // Popover
    if (!fullscreen && opener.val().trim() == '') {
        var el = $('#rc-ace-editor').popover({ title: window.lang.global_173, content: '<span class="text-secondary fs12">Example: <code>[age] > 30 and [dob] <> ""</code></span>', html: true, placement: 'right', delay: 0, trigger: 'manual', customClass: 'enter-logic-here-hint'}).popover('show');
        setTimeout(function(){
            $('.enter-logic-here-hint.popover:visible').css({'top':'-187px', 'z-index':'999'});
        },1);
        setTimeout(function(){
            el.popover('hide');
        },5000);
    }
    // Put focus in the editor
    setTimeout(function(){
        editor.focus();
        editor.navigateFileEnd();
        LogicEditorClosing = false;
    },100);
    // Remove the popover when the editor loses focus
    $(editor).on('blur', function(){
        $('#rc-ace-editor').popover('hide');
    });
}

// Reset the cache for a project dashboard
function resetDashbardCache(dash_id){
    showProgress(1);
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:reset_cache', { dash_id: dash_id }, function(data) {
        if (data == '0' || data == '') {
            showProgress(0,0);
            simpleDialog(woops);
        } else {
            window.location.reload();
        }
    });
}

function copyReasonToAll(ob) {
    var reason = $(ob).parent().find('textarea').val();
    $('textarea.change_reason').each(function(){
        $(this).val(reason);
    });
}

// Validate the fields in the user-defined logic as real fields
function validate_logic(thisLogic) {
    // First, make sure that the logic is not blank
    if (trim(thisLogic).length < 1) return;
    // Make ajax request to check the logic via PHP
    $.post(app_path_webroot+'Design/logic_validate.php?pid='+pid, { logic: thisLogic, forceMetadataTable: 1 }, function(data){
        if (data == '1') {
            // All good!
        } else if (data == '0') {
            alert(woops);
            return false;
        } else {
            alert(data);
            return false;
        }
    });
}

var CSSEditorOpenTime = performance.now();
var CSSEditorClosing = false;
var CSSEditorOpening = false;
function openCSSEditor(opener, fullscreen, onUpdateFn)
{
    // Already opening?
    if (CSSEditorOpening) return;
    // Load the ACE Editor JS file
    if (typeof window['ace'] == 'undefined') {
        CSSEditorOpening = true;
        $.getScript(app_path_webroot+"Resources/js/Libraries/ace.js").then(function() {
            // Reopen after script has loaded
            setTimeout(() => {
                CSSEditorOpening = false;
                openCSSEditor(opener, fullscreen, onUpdateFn);
            }, 0);
        });
        return;
    }
    // Prevent multiple retriggering of function in small time period
    if ((performance.now()-CSSEditorOpenTime) <= 200) return;
    CSSEditorOpenTime = performance.now();
    // Init settings
    $('#rc-css-editor').popover('hide');
    initLogicSuggestSearchTip();
    if (typeof fullscreen == 'undefined') fullscreen = false;
    if (typeof origValueOverride == 'undefined') origValueOverride = null;
    // If opener lacks an ID, then add one
    var openerId = opener.attr('id');
    if (openerId == null) {
        openerId = "rc-css-editor-opener-"+Math.floor(Math.random()*10000000000000000);
        opener.attr('id', openerId);
    }
    // Set the original value so we know how to revert the opener value if user cancels
    if (opener.attr('val') == null) opener.attr('val', opener.val());
    // Set fullscreen or regular size settings
    if (fullscreen) {
        var editorStyle = 'height:'+round($(window).height()*0.75)+'px;';
        var dialogWidth = round($(window).width()*0.9);
        var buttonText1 = window.lang.global_167;
    } else {
        var editorStyle = 'height:400px;';
        var dialogWidth = 750;
        var buttonText1 = window.lang.global_168;
    }
    // Open dialog
    var html =  '<div id="rc-css-editor-parent"><div class="mb-3">'+window.lang.global_309+
                '<div class="mt-2"><i class="fa-solid fa-triangle-exclamation text-warning"></i> '+window.lang.global_312+'</div>'+
                '</div><div id="rc-css-editor" style="'+editorStyle+'"></div><div id="rc-css-editor-status"></div>'+
                '<div style="font-size:12px;color:#777;margin:10px 0 0;line-height:1.2;"><i class="fa-regular fa-lightbulb"></i> '+window.lang.global_311+'</div></div>';
    initDialog('rc-css-editor-dialog', html);
    var dlgDiv = $('#rc-css-editor-dialog');
    dlgDiv.dialog({ bgiframe: true, modal: true, width: dialogWidth, title: window.lang.global_310, buttons:
        // Cancel
        [{text: window.lang.global_53, click: function() {
            try { 
                $('#'+opener.prop('id')+'_Ok').not('.CSSEditorDoNotHide').hide(); 
            } catch(e) { }
            try{ dlgDiv.dialog('close'); }catch(e){ }
            // Get rid of any helpers
            $('.LSC-element').hide();
        }},
        // Update and Close Editor
        { text: window.lang.global_169, click: function() {
            // Update val attribute
            opener.attr('val', opener.val());
            setTimeout(function(){
                opener.removeAttr('val');
            },500);
            try {
                $('#'+opener.prop('id')+'_Ok').not('.CSSEditorDoNotHide').hide();
            } catch(e) { }
            try{ dlgDiv.dialog('destroy'); }catch(e){ }
            dlgDiv.remove();
            $('.popover').popover('hide');
            // UI effect
            if (opener.css('background-color') == 'rgb(255, 255, 255)') {
                opener.effect('highlight', { }, 2000);
            }
            if (typeof onUpdateFn == 'function') {
                onUpdateFn();
            }
        }},
        // Fullscreen/Regular View
        {text: buttonText1, click: function() {
            try{ dlgDiv.dialog('destroy'); }catch(e){ }
            dlgDiv.remove();
            $('.popover').popover('hide');
            openCSSEditor(opener, !fullscreen, onUpdateFn);
        }}]
    });
    dlgDiv.parent().find('.ui-dialog-buttonset .ui-button:eq(0)').css({ 'color': '#A00000'}).prepend('<i class="fas fa-times"></i> ');
    dlgDiv.parent().find('.ui-dialog-buttonset .ui-button:eq(1)').addClass('font-weight-bold').css({ 'color': '#008000'}).prepend('<i class="fas fa-check"></i> ');
    dlgDiv.parent().find('.ui-dialog-buttonset .ui-button:eq(2)').css({ 'font-size': '13px', 'margin-right': '50px', 'color': '#666'}).prepend(fullscreen ? '<i class="fas fa-compress-arrows-alt"></i> ' : '<i class="fas fa-expand-arrows-alt"></i> ');
    dlgDiv.bind('dialogclose', function(){
        // Prevent multiple retriggering of function in small time period
        if (CSSEditorClosing) return;
        CSSEditorClosing = true;
        // Reset the opener's value
        opener.val( opener.attr('val') );
        // Also remove the "val" attribute value to reset it for next time (set with a timeout because this function may be triggered multiple times when closing)
        setTimeout(function(){
            opener.removeAttr('val');
        },500);
        // Destroy the dialog
        try{ dlgDiv.dialog('destroy'); }catch(e){ }
        dlgDiv.remove();
        $('.popover').popover('hide');
    });
    // Init ACE Editor and set it to auto-update its corresponding textarea
    ace.config.set('basePath', app_path_webroot + 'Resources/js/Libraries/ace/')
    var editor = ace.edit("rc-css-editor");
    editor.setShowPrintMargin(false);
    editor.renderer.setShowGutter(true);
    editor.setOptions({ wrap: true, fontSize: "11pt" });
    editor.getSession().setValue( opener.val() );
    editor.getSession().setMode("ace/mode/css");
    editor.getSession().setUseWorker(true);
    editor.getSession().on('change', function(){
        opener.val(editor.getSession().getValue()).trigger('keydown');
    });
    // Put focus in the editor
    setTimeout(function(){
        editor.focus();
        editor.navigateFileEnd();
        CSSEditorClosing = false;
    },100);
}

/**
 * Save form custom CSS and show a toast message in case of error/success 
 * @param {string} form_name 
 */
function saveFormCustomCSS(form_name) {
    let custom_css = ('' + $('#custom_css').val());
    if (custom_css.trim() == '') {
        custom_css = '';
    }
    // Update button label
    $('#edit-custom-css-label').text(custom_css == '' ? lang.design_1393 : lang.design_1394);

    // Save
    $.post(app_path_webroot+'index.php?route=DesignController:saveFormCustomCSS&pid='+pid, { form_name: form_name, custom_css: custom_css }, function(data) {
        if (data.error) {
            showToast(lang.global_01, data.error, 'error');
        }
        else if (data.changed) {
            // Only show the success toast when an actual change was made
            showToast(lang.design_1346, lang.design_1397, 'success', 1250);
        }
    });
}


// Make an AJAX request to determine if a given field is designed on multiple events or exists on a repeating instrument or event
function fieldUsedInMultiplePlaces(ob)
{
    var id = 'field-multi-location-warning';
    var field = ob.val();
    if (field == '') {
        $('#'+id).remove();
        return;
    }
    var warningHtml = '<div id="'+id+'" class="yellow mt-2"><i class="fa-solid fa-triangle-exclamation"></i> '+lang.design_1051+' <code>'+field+'</code> '+lang.design_1052+'</div>';
    $.post(app_path_webroot+'index.php?route=DesignController:fieldUsedInMultiplePlaces&pid='+pid, { field: field }, function(data){
        $('#'+id).remove();
        if (data == '1') {
            // Display a warning div below the ob element
            ob.after(warningHtml);
        }
    });
}

// Populate Language ID and Language display name when user clicks MyCap language code from allowed lang list
function populateLanguageID(langCode, langDisplay) {
    $('input[name="key"]').val(langCode);
    $('input[name="display"]').val(langDisplay);
    // Call change event to validate value
    $('input[name="key"]').change();
    // UI effect
    setTimeout(function(){
        if ($('input[name="key"]').css('background-color') == 'rgb(255, 255, 255)') {
            $('input[name="key"]').effect('highlight', { }, 2000);
        }
        if ($('input[name="display"]').css('background-color') == 'rgb(255, 255, 255)') {
            $('input[name="display"]').effect('highlight', { }, 2000);
        }
    },300);
}


//#region Draft Preview

/**
 * Handles clicks on the "Draft Preview" toggle
 * @param {HTMLElement} btn 
 * @param {Event} event 
 * @param {boolean} supported
 */
function setDraftPreview(btn, event, supported) {
	event.preventDefault();
	const $btn = $(btn);
	const toState = $btn.prop('checked');

	if (toState == true) {
        if (supported) {
            // Turn ON
            simpleDialog(lang.draft_preview_02 + '<br><br>' + lang.draft_preview_20 + '<p><b>' + lang.draft_preview_18 + '</b></p>', lang.draft_preview_01, 'draft-preview-confirm-dialog', 700, null, lang.global_53, function() { setDraftPreviewDo($btn, 'ON'); }, lang.draft_preview_03, true);
        }
        else {
            simpleDialog('<div class="red mb-2">'+lang.draft_preview_17+'</div>' + lang.draft_preview_02 + '<br><br>' + lang.draft_preview_20, lang.draft_preview_01, 'draft-preview-confirm-dialog', 700, null, lang.global_53, null, null, true);
        }
	}
	else {
		// Turn OFF
		setDraftPreviewDo($btn, 'OFF');
	}
}

/**
 * Displays the draft preview info dialog
 */
function showDraftPreviewInfo() {
    simpleDialog(lang.draft_preview_02 + '<br><br>' + lang.draft_preview_20 + '<p><b>' + lang.draft_preview_18 + '</b></p>', lang.draft_preview_01, 'draft-preview-confirm-dialog', 700, null, lang.calendar_popup_01, null, null, true);
}

/**
 * Turns on or off the draft preview mode
 * @param {JQuery<HTMLElement>|null} $btn 
 * @param {'ON'|'OFF'} toState 
 */
function setDraftPreviewDo($btn, toState) {
	$.post(
		app_path_webroot+'Design/set_draft_preview_enabled.php?pid='+pid, {
			toState: toState
		},
		function(data) {
			if (!['ON', 'OFF'].includes(data)) {
				woops();
			}
			else {
				if ($btn) {
					$btn.prop('checked', data === 'ON');
				}
				else {
					dataEntryFormValuesChanged = false;
            // Remove &msg=draft-preview from URL, if exists
            var url = window.location.href.replace("&msg=draft-preview","");
            window.location.href = url;
				}
				$('#draft-preview-status')[data === 'ON' ? 'show' : 'hide']();
			}
            if (data === 'ON') {
                displayPopover('rsd-men-link', lang.draft_preview_21, '', 'right', 8);
            }
		}
	);
}

//#endregion

// Set a binary UI State value via AJAX
function setUiState(object, name, state) {
    if (state !== 1 && state !== '1') state = '0'; // binary only
    var pattern = /^[a-zA-Z0-9_-]+$/;
    if (pattern.test(name)) {
        $.post(app_path_webroot+"ProjectGeneral/set_ui_state.php?pid="+pid, { object: object, name: name, state: state });
    }
}

// Display a popover
function displayPopover(id, content, btn_text, placement, timeAutoClose) {
    if (!$("#" + id).length) return;
    if (typeof timeAutoClose == 'undefined' || !isinteger(timeAutoClose)) timeAutoClose = 0;
    const Icon = $("#" + id)[0];
    const Class = (btn_text == '' ? "my-0" : "mt-0 mb-2")
    const Content = $("<div><p class='"+Class+"'>" + content + "</p></div>");
    const button = $("<button>" + btn_text + "</button>")
      .addClass("btn btn-primary btn-xs");
    if (btn_text != '') button.appendTo(Content);
    const popover = new bootstrap.Popover(Icon, {
        html: true,
        content: Content,
        placement: placement
    });
    popover.show();
    if (btn_text != '') {
        button.on("click", function () {
            popover.dispose();
        });
    }
    if (timeAutoClose > 0) {
        setTimeout(function(){
            popover.dispose();
        }, timeAutoClose*1000);
    }
}

// Display a popover with specific content that is tied to a specific UIState binary value.
// When the popover is dismissed, the UIState will be set to 0 via AJAX.
// See UIState::checkDisplayPopover() in PHP for full usage.
function displayUiStatePopover(id, content, btn_text, uistate_object, uistate_name) {
    if (!$("#"+id).length) return;
    const Icon = $("#"+id)[0];
    const Content = $("<div><p class='mt-0 mb-2'>" + content + "</p></div>");
    const button = $("<button>" + btn_text + "</button>")
      .addClass("btn btn-primary btn-xs")
      .appendTo(Content);
    const popover = new bootstrap.Popover(Icon, {
        html: true,
        content: Content,
        placement: "bottom"
    });
    popover.show();
    button.on("click", function() {
        popover.dispose();
        setUiState(uistate_object, uistate_name, '1');
    });
}

/**
 * Initializes Bootstrap tooltips ([data-bs-tooltip="toggle"]).
 * @param {string|jQuery<HTMLElement>} selector An optional CSS selector or jQuery object; when provided, tooltips will only be initialized for elements that match the selector
 * @param {object} options An object specifying additional options. Supported keys are: customClass - CSS class name(s), delay - delay in milliseconds (default: 500), placement - 'top', 'bottom', 'left', 'right' (default: 'bottom'), subSelector - CSS selector (default: '[data-bs-toggle="tooltip"], [data-toggle="tooltip"]')
 */
function initTooltips(selector = '', options = {}) {
    // Set default options
    if (typeof options != 'object') options = {};
    options.delay = options.delay ?? 500;
    options.customClass = options.customClass ?? 'od-tooltip';
    options.placement = options.placement ?? null;
    options.subSelector = options.subSelector ?? '[data-bs-toggle="tooltip"], [data-toggle="tooltip"]';
    try {
        // Get jQuery
        const elements = typeof selector == 'string' 
            ? $(selector + ' ' + options.subSelector)
            : selector.find(options.subSelector);
        if (!elements.length) return;
        // Initialize tooltips
        elements.each(function() {
            new bootstrap.Tooltip(this, {
                html: true,
                trigger: 'hover',
                delay: { "show": options.delay, "hide": 0 },
                customClass: options.customClass,
                placement: () => options.placement ?? $(this).attr('data-bs-placement') ?? $(this).attr('data-placement') ?? 'bottom'
            });
        });
    } 
    catch (e) {
        // Ignore
    }
}


//#region Edit form name
/**
 * Show the edit form name pop-up
 * @param {Object} data 
 */
function editFormName(data) {
	if (status != '0' && draft_mode == 0) return;
	if ($('.popover.edit-form-name-popover').length == 0) {
		// Get and init template
		const $tpl = $($('[data-template="qef-editformname"]').html());
		const title = $tpl.find('header').html();
		$tpl.find('header').remove();
		initTooltips($tpl, { placement: 'right', subSelector: '[title]' });
		$tpl.find('[data-action]').on('click', function() {
			editFormNameAct($tpl, $(this).data('action'), data);
		});
		if (data.surveyTitle !== false) {
			$tpl.find('#efn-surveytitle label').text(interpolateString(lang.design_1366, [data.surveyTitle]))
		}
		else {
			$tpl.find('#efn-surveytitle').remove();
		}
		if (data.taskTitle !== false) {
			$tpl.find('#efn-tasktitle label').text(interpolateString(lang.design_1367, [data.taskTitle]))
		}
		else {
			$tpl.find('#efn-tasktitle').remove();
		}
		$(data.selector).popover({
			customClass: 'edit-form-name-popover',
			content: $tpl,
			title: title,
			html: true,
			offset: data.offset ?? '100, 8',
			placement: data.placement ?? 'bottom',
		}).on('inserted.bs.popover, show.bs.popover', function() {
			// Set value and removal of invalid class (after a delay)
			$tpl.find('#efn-displayname').val(data.displayName).removeClass('is-invalid');
			$tpl.find('#efn-formname').val(data.formName).removeClass('is-invalid').prop('disabled', !data.canEditFormName);
			$tpl.find('input[type="checkbox"]').prop('checked', false);
		});
	}
	$(data.selector).popover('toggle');
}

/**
 * Execute actions on the edit form name pop-up
 * @param {JQuery<HTMLElement} $tpl 
 * @param {string} action 
 * @param {Object} info Form details passed on from editFormName()
 */
function editFormNameAct($tpl, action, info) {
	if (action == 'cancel') {
		$(info.selector).popover('hide');
	}
	else if (action == 'apply') {
		// Clear errors
		$tpl.find('.is-invalid').removeClass('is-invalid');
		const data = { 
			action: 'edit-form-name', 
			displayName: $tpl.find('#efn-displayname').val(),
			newFormName: $tpl.find('#efn-formname').val(),
			prevFormName: info.formName,
			changeSurvey: $tpl.find('#efn-change-surveytitle').prop('checked') ? 1 : 0,
			changeTask: $tpl.find('#efn-change-tasktitle').prop('checked') ? 1 : 0
		};
		// Send to server
		showProgress(1);
		$.post(app_path_webroot+'Design/rename_form.php?pid='+pid, data, function(response) {
			if (response == '1') {
				showToast(lang.design_1346, lang.design_1371);
				// Applied successfully: modify url reload page
				if (info.gotoPage ?? false) {
                    modifyURL(updateParameterInURL(location.href, 'page', data.newFormName));
                }
				setTimeout(() => {
					location.reload();
				}, 500);
			}
			else if (response == '0') {
				// An unspecified error occurred
				$(info.selector).popover('hide');
				showProgress(0);
				showToast(lang.global_01, woops, 'error');
			}
			else {
				// Display error
				const errors = JSON.parse(response);
				for (const fieldName in errors) {
					$tpl.find('#'+fieldName+'-error').text(errors[fieldName]);
					$tpl.find('#'+fieldName).addClass('is-invalid');
				}
				showProgress(0);
			}
		});
	}
}

// Designate all remaining instruments in series if first instrument is checked
function checkAllPromisInstruments (obj) {
    if ($(obj).is(':checked')) {
        // Check all remaining instruments in this series for this event
        $('input[type="checkbox"][first-instrument="'+obj.attr('id')+'"]').prop("checked", true);
    } else {
        // Uncheck all remaining instruments in this series for this event
        $('input[type="checkbox"][first-instrument="'+obj.attr('id')+'"]').prop("checked", false);
    }
}
//#endregion

