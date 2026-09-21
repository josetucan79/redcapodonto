/*!
* Clamp.js 0.5.1
*
* Copyright 2011-2013, Joseph Schmitt http://joe.sh
* Released under the WTFPL license
* http://sam.zoy.org/wtfpl/
*/
(function(){window.$clamp=function(c,d){function s(a,b){n.getComputedStyle||(n.getComputedStyle=function(a,b){this.el=a;this.getPropertyValue=function(b){var c=/(\-([a-z]){1})/g;"float"==b&&(b="styleFloat");c.test(b)&&(b=b.replace(c,function(a,b,c){return c.toUpperCase()}));return a.currentStyle&&a.currentStyle[b]?a.currentStyle[b]:null};return this});return n.getComputedStyle(a,null).getPropertyValue(b)}function t(a){a=a||c.clientHeight;var b=u(c);return Math.max(Math.floor(a/b),0)}function x(a){return u(c)*
    a}function u(a){var b=s(a,"line-height");"normal"==b&&(b=1.2*parseInt(s(a,"font-size")));return parseInt(b)}function l(a){if(a.lastChild.children&&0<a.lastChild.children.length)return l(Array.prototype.slice.call(a.children).pop());if(a.lastChild&&a.lastChild.nodeValue&&""!=a.lastChild.nodeValue&&a.lastChild.nodeValue!=b.truncationChar)return a.lastChild;a.lastChild.parentNode.removeChild(a.lastChild);return l(c)}function p(a,d){if(d){var e=a.nodeValue.replace(b.truncationChar,"");f||(h=0<k.length?
    k.shift():"",f=e.split(h));1<f.length?(q=f.pop(),r(a,f.join(h))):f=null;m&&(a.nodeValue=a.nodeValue.replace(b.truncationChar,""),c.innerHTML=a.nodeValue+" "+m.innerHTML+b.truncationChar);if(f){if(c.clientHeight<=d)if(0<=k.length&&""!=h)r(a,f.join(h)+h+q),f=null;else return c.innerHTML}else""==h&&(r(a,""),a=l(c),k=b.splitOnChars.slice(0),h=k[0],q=f=null);if(b.animate)setTimeout(function(){p(a,d)},!0===b.animate?10:b.animate);else return p(a,d)}}function r(a,c){a.nodeValue=c+b.truncationChar}d=d||{};
    var n=window,b={clamp:d.clamp||2,useNativeClamp:"undefined"!=typeof d.useNativeClamp?d.useNativeClamp:!0,splitOnChars:d.splitOnChars||[".","-","\u2013","\u2014"," "],animate:d.animate||!1,truncationChar:d.truncationChar||"\u2026",truncationHTML:d.truncationHTML},e=c.style,y=c.innerHTML,z="undefined"!=typeof c.style.webkitLineClamp,g=b.clamp,v=g.indexOf&&(-1<g.indexOf("px")||-1<g.indexOf("em")),m;b.truncationHTML&&(m=document.createElement("span"),m.innerHTML=b.truncationHTML);var k=b.splitOnChars.slice(0),
        h=k[0],f,q;"auto"==g?g=t():v&&(g=t(parseInt(g)));var w;z&&b.useNativeClamp?(e.overflow="hidden",e.textOverflow="ellipsis",e.webkitBoxOrient="vertical",e.display="-webkit-box",e.webkitLineClamp=g,v&&(e.height=b.clamp+"px")):(e=x(g),e<=c.clientHeight&&(w=p(l(c),e)));return{original:y,clamped:w}}})();

var message_letter = getParameterByName('message');
if (message_letter != '' && getParameterByName('log') == '') {
    // Modify the URL
    modifyURL(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:setup');
}
var changedCronSendEmailOn = false;

function initTinyMCE()
{
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
        selector: '.external-modules-rich-text-field',
        height: 325,
        menubar: false,
        branding: false,
        statusbar: true,
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
        setup: function (editor) {
            editor.on('keyup', function (e) {
                logicSuggestSearchTip($('#alert-message_ifr'), e);
            });
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
}

/**
 * Function to preview the message on the alerts table
 * @param index, the alert id
 */
function previewEmailAlert(index,alertnumber, alert_id){
    var data = "&index_modal_preview="+index+"&redcap_csrf_token="+redcap_csrf_token;
    $.ajax({
        type: "POST",
        url: app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:previewAlertMessage',
        data: data,
        error: function (xhr, status, error) {
            alert(xhr.responseText);
        },
        success: function (result) {
            $('#modal_message_preview').html(result);
            $('#uniqueAlertId').remove();
            var alertUniqueIdHTML = '<span id="uniqueAlertId" style="font-size: 14px; padding-left: 10px;">(' + lang.alerts_311 + ' ' + uniqueIdPrefix + alert_id + ')</span>';
            $('#modalPreviewNumber').text("- "+lang.alerts_24+" #"+alertnumber);
            $('#modalPreviewNumber').after(alertUniqueIdHTML);
            $('#myModalLabelA').show();
            $('#myModalLabelB').hide();
            $('#external-modules-configure-modal-preview').modal('show');
        }
    });
}

function previewEmailAlertRecord(index, alertnumber, alert_id){
    $('#index_modal_record_preview').val(index);
    $('#uniqueAlertId').remove();
    var alertUniqueIdHTML = '<span id="uniqueAlertId" style="font-size: 14px; padding-left: 10px;">(' + lang.alerts_311 + ' ' + uniqueIdPrefix + alert_id + ')</span>';
    $('#modalRecordNumber').text("- "+lang.alerts_24+" #"+alertnumber);
    $('#modalRecordNumber').after(alertUniqueIdHTML);

    var data = "&index_modal_alert="+index+"&redcap_csrf_token="+redcap_csrf_token;
    $.ajax({
        type: "POST",
        url: app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:previewAlertMessageByRecordDialog',
        data: data,
        error: function (xhr, status, error) {
            alert(xhr.responseText);
        },
        success: function (result) {
            $('#load_preview_record').html(result);
            $('#external-modules-configure-modal-record').modal('show');
        }
    });

}
function deleteRecurrenceDo(aq_id, alert_id){
    ajaxLoadOptionAndMessage("&aq_id="+aq_id+"&pid="+pid+"&alert_id="+alert_id, app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:deleteQueuedRecord',"");
}

function deleteRecurrence(aq_id, alert_id, alert_number, record, event) {
    var alertUniqueIdHTML = '<span id="uniqueAlertId" style="font-size: 14px; font-weight: normal; padding-left: 5px;">(' + uniqueIdPrefix + alert_id + ')</span>';
    $('#delete-recurrence-modal-body-alert').html(lang.alerts_24+" #"+alert_number+alertUniqueIdHTML);
    $('#delete-recurrence-modal-body-record').html(record);
    $('#delete-recurrence-modal-body-event').html(event);
    $('#delete-recurrence-modal').modal('show');
    $('#delete-recurrence-modal-body-submit').attr('onclick','deleteRecurrenceDo('+aq_id+','+alert_id+');return false;');
}

function loadPreviewEmailAlertRecord(alert_sent_log_id, aq_id, alertnumber, alert_id) {
    var selRecord = $('#selectPreviewRecord').find('select[name="preview_record_id"]').val();
    if (selRecord == '') {
        // Clear previous result html if no record selected
        $('#modal_message_record_preview').html('');
    } else {
        var data = $('#selectPreviewRecord').serialize();
        if (alert_sent_log_id != "") {
            data += "&alert_sent_log_id=" + alert_sent_log_id + "&redcap_csrf_token=" + redcap_csrf_token;
        } else if (aq_id != "") {
            data += "&aq_id=" + aq_id + "&redcap_csrf_token=" + redcap_csrf_token;
        }
        $.ajax({
            type: "POST",
            url: app_path_webroot + 'index.php?pid=' + pid + '&route=AlertsController:previewAlertMessageByRecord',
            data: data,
            error: function (xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function (result) {
                $('#uniqueAlertId').remove();
                var alertUniqueIdHTML = '<span id="uniqueAlertId" style="font-size: 14px; padding-left: 10px;">(' + lang.alerts_311 + ' ' + uniqueIdPrefix + alert_id + ')</span>';
                if (alert_sent_log_id == "" && aq_id == "") {
                    $('#modal_message_record_preview').html(result);
                    $('#modalRecordNumber').after(alertUniqueIdHTML);
                } else {
                    $('#modal_message_preview').html(result);
                    $('#modalPreviewNumber').text("- " + lang.alerts_24 + " #" + alertnumber);
                    $('#modalPreviewNumber').after(alertUniqueIdHTML);
                    $('#myModalLabelA').hide();
                    $('#myModalLabelB').show();
                    $('#external-modules-configure-modal-preview').modal('show');
                }
                // Fix inline images to display correctly
                $('#external-modules-configure-modal-preview img').each(function () {
                    var lsrc = $(this).attr('lsrc') || '';
                    if (lsrc.length > 0) this.src = lsrc;
                });
            }
        });
    }
}

function checkSchedule(repetitive, repetitive_change, cron_send_email_on, cron_send_email_on_date, cron_repeat_for) {
    $('[name=external-modules-configure-modal] input[name="cron-send-email-on-date"]').val('');
    if (repetitive == '1' || repetitive_change == '1') {
        // Send immediately (either once or every time form is saved)
        $('[name=external-modules-configure-modal] input[name="cron-send-email-on"][value="now"]').prop('checked',true);
    } else {
        // Send using schedule
        $('[name=external-modules-configure-modal] input[name="cron-repeat-for"]').val(cron_repeat_for);
        $('[name=external-modules-configure-modal] input[name="cron-send-email-on"][value="'+cron_send_email_on+'"]').prop('checked',true);
        if (cron_send_email_on == "date") {
            $('[name=external-modules-configure-modal] :input[name="cron-send-email-on-date"]').val(cron_send_email_on_date);
        }
    }
}

function displayTriggerSettings() {
    var val = $('[name="alert-trigger"]:checked').val();
    $('[field="form-name"], [field="condition-andor"], [field="alert-condition"], .condition-andor-text2').hide();
    if (val == 'submit') {
        // Form submit only
        $('[field="form-name"]').show();
    } else if (val == 'submit-logic') {
        // Form submit + logic
        $('[field="form-name"], [field="condition-andor"], [field="alert-condition"]').show();
    } else {
        // Logic only
        $('[field="alert-condition"], .condition-andor-text2').show();
    }
}

function checkTriggerSettings() {
    var trigger;
    var form = $('[name=external-modules-configure-modal] select[name="form-name"]').val();
    var logic = $('[name=external-modules-configure-modal] :input[name="alert-condition"]').val().trim();
    if ((form == '-' || form == '') && logic != '') {
        // Logic only
        trigger = 'logic';
    } else if (form != '-' && form != '' && logic != '') {
        // Form submit + logic
        trigger = 'submit-logic';
    } else {
        // Form submit only
        trigger = 'submit';
    }
    $('input[name="alert-trigger"][value="'+trigger+'"]').prop('checked',true).trigger('click');
}

function checkMessageSettings() {
    $('tr#sendgrid-advanced-settings-button-row').hide();
    if (!$('[name=external-modules-configure-modal] input[name="alert-type"]').length ||
        !$('[name=external-modules-configure-modal] input[name="alert-type"]:checked').val() ||
        $('[name=external-modules-configure-modal] input[name="alert-type"]:checked').val() == 'EMAIL') {
        // Email
        $("#alert-type-email").prop("checked", true);
        $('#code_modal_table_update tr[field="phone-number-to"]').hide();
        $('#code_modal_table_update tr[field="email-from"]').show();
        $('#code_modal_table_update tr[field="email-to"]').show();
        $('#code_modal_table_update tr[field="email-subject"]').show();
        $('#code_modal_table_update tr[field="sendgrid-template-id"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-template-data"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-unsubscribe-group"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-mail-settings"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-tracking-settings"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-categories"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-from"]').hide();
        $('#code_modal_table_update tr[field="alert-message"]').show();
        $('#code_modal_table_update #showAttachments').show();
        $('#prevent-piping-identifiers-box').appendTo('#alert-message-label-td');
        if (document.getElementById('showCC').classList.contains('d-none')) {
            $('#code_modal_table_update tr[field="email-failed"]').show();
        }
        $('#advanced-sendgrid-settings-banner').hide();
    } else if ($('[name=external-modules-configure-modal] input[name="alert-type"]:checked').val() == 'SENDGRID_TEMPLATE') {
        // SendGrid
        $('#code_modal_table_update tr[field="phone-number-to"]').hide();
        $('#code_modal_table_update tr[field="email-from"]').hide();
        $('#code_modal_table_update tr[field="email-to"]').show();
        $('#code_modal_table_update tr[field="email-subject"]').hide();
        $('#code_modal_table_update tr[field="email-failed"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-template-id"]').show();
        $('#code_modal_table_update tr[field="sendgrid-template-data"]').show();
        $('#code_modal_table_update tr[field="sendgrid-from"]').show();
        $('#code_modal_table_update tr[field="alert-message"]').hide();
        $('#code_modal_table_update #showAttachments').show();
        $('#prevent-piping-identifiers-box').appendTo('#sendgrid-template-data-label-td');
        $('#advanced-sendgrid-settings-banner').hide();
        $('#code_modal_table_update tr[field="sendgrid-unsubscribe-group"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-mail-settings"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-tracking-settings"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-categories"]').hide();
        $('tr#sendgrid-advanced-settings-button-row').show();
        document.getElementById("sendgrid-advanced-settings-button").textContent = lang.alerts_397;
        getSendgridData(true, false);
    } else {
        // SMS or Voice Call
        $('#code_modal_table_update tr[field="phone-number-to"]').show();
        $('#code_modal_table_update tr[field="email-from"]').hide();
        $('#code_modal_table_update tr[field="email-to"]').hide();
        $('#code_modal_table_update tr[field="email-subject"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-template-id"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-template-data"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-unsubscribe-group"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-tracking-settings"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-mail-settings"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-categories"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-from"]').hide();
        $('#code_modal_table_update tr[field="alert-message"]').show();
        $('#code_modal_table_update #showAttachments').hide();
        $('#advanced-sendgrid-settings-banner').hide();
    }
    showAttachmentFields(false);
}

// Show/hide the warn-cond-logic-every-instance-trigger-limit div
function showHideTriggerLimitInstanceWarning() {
    var showTriggerLimitWarning = ($('#alert-trigger3').prop('checked') && $(':input[name="alert-stop-type"]:visible').length && $(':input[name="alert-stop-type"]').val() == 'RECORD_EVENT_INSTRUMENT_INSTANCE');
    if (showTriggerLimitWarning) {
        $('#warn-cond-logic-every-instance-trigger-limit').show();
    } else {
        $('#warn-cond-logic-every-instance-trigger-limit').hide();
    }
    // Enable/disable the option "Allow pausing of recurrences"
    if ($('#ensure-logic-still-true:visible').prop('checked')) {
        $('#do-not-clear-recurrences-parent').removeClass('text-tertiary').find(":input").prop('disabled',false);
    } else {
        $('#do-not-clear-recurrences-parent').addClass('text-tertiary').find(":input").prop('disabled',true);
    }
}

/**
 * Function that shows the modal with the alert information to modify it
 * @param modal, array with the data from a specific alert
 * @param index, the alert id
 */
function editEmailAlert(modal, index, alertNum)
{
    tinymce.remove();
    initTinyMCE();
    changedCronSendEmailOn = false;
    // Remove nulls
    for (var key in modal) {
       if (modal[key] == null) modal[key] = "";
    }
    // for (var i = 0; i < tinymce.editors.length; i++) {
    //     var editor = tinymce.editors[i];
    //     editor.on('init', function () {
    //         editor.setContent(modal['alert-message'])
    //     });
    // }

    $("#index_modal_update").val(index);
    $('[name=cron-queue]').prop('checked', false);

    if (index == '') {
        $('#add-edit-title-text').html(lang.alerts_36);
    } else {
        var alertUniqueIdHTML = '<span style="font-size: 14px; padding-left: 10px;">(' + lang.alerts_311 + ' ' + uniqueIdPrefix + modal['alert-id'] + ')</span>';
        $('#add-edit-title-text').html(lang.alerts_37+' #' + alertNum + alertUniqueIdHTML);
    }

    // Remove left-over items
    $('select .email-select-temp').remove();
    $('select .email-from-select-temp').remove();

    var twilio_enabled_alerts = !$('[name=external-modules-configure-modal] input#alert-type-sms').prop('disabled');
    if (!twilio_enabled_alerts && modal['alert-type'] !== 'SENDGRID_TEMPLATE') {
            modal['alert-type'] = 'EMAIL';
    }

    $('[name=external-modules-configure-modal] input[name="alert-type"][value="'+modal['alert-type']+'"]').prop('checked',true);

    // Fix for issue of appending db value to previous form values
    $('#phone-number-to').val(null).trigger('change');
    $('input[name="phone-number-to-freeform"]').val('');
    var langPrevSaved = lang.alerts_309;
    // Phone numbers (Twilio only)
    $.each(modal['phone-number-to'].split(";"), function(i,e){
        e = trim(e);
        if (e == '') return;
        var isVariable = (e.charAt(0) == '[');
        if (!isVariable) {
            // Remove all non-numerals
            e = e.replace(/\D/g,'');
        }
        if (!$('[name=external-modules-configure-modal] select[name="phone-number-to"] option[value="' + e + '"]').length) {
            if ($('input[name="phone-number-to-freeform"]').length && isNumeric(e)) {
                var val = $('input[name="phone-number-to-freeform"]').val();
                if (val != '') val += '; ';
                $('input[name="phone-number-to-freeform"]').val(val+e);
            } else {
                if (!$('[name=external-modules-configure-modal] select[name="phone-number-to"] optgroup[label="' + langPrevSaved + '"]').length) {
                    $('[name=external-modules-configure-modal] select[name="phone-number-to"]').append('<optgroup class="email-select-temp" label="' + langPrevSaved + '"></optgroup>');
                }
                $('[name=external-modules-configure-modal] select[name="phone-number-to"] optgroup[label="' + langPrevSaved + '"]').append('<option class="email-select-temp" value="' + e + '">' + e + '</option>');
            }
        }
        $('[name=external-modules-configure-modal] select[name="phone-number-to"] option[value="' + e + '"]').prop("selected", true).addClass('ms-selection');
    });

    //Add values
    $('[name=external-modules-configure-modal] :input[name="email-deleted"]').val(modal['email-deleted']);
    $('[name=external-modules-configure-modal] input[name="alert-title"]').val(modal['alert-title']);
    $('[name=external-modules-configure-modal] input[name="email-from-display"]').val(modal['email-from-display']);
    $('[name=external-modules-configure-modal] select[name="form-name"]').val(modal['form-name']+'-'+modal['form-name-event']);
    $('[name=external-modules-configure-modal] select[name="email-incomplete"]').val(modal['email-incomplete']);
    $('[name=external-modules-configure-modal] select[name="cron-repeat-for-units"]').val(modal['cron-repeat-for-units']);
    $('[name=external-modules-configure-modal] :input[name="alert-condition"]').val(modal['alert-condition']);
    $('[name=external-modules-configure-modal] :input[name="email-repetitive"]').val(modal['email-repetitive']);
    $('[name=external-modules-configure-modal] :input[name="email-repetitive-change"]').val(modal['email-repetitive-change']);
    $('[name=external-modules-configure-modal] :input[name="email-repetitive-change-calcs"]').prop('checked', (modal['email-repetitive-change-calcs'] == '1' && modal['email-repetitive-change'] == '1'));
    $('[name=external-modules-configure-modal] :input[name="cron-repeat-for"]').val(modal['cron-repeat-for']);
    $('[name=external-modules-configure-modal] :input[name="cron-repeat-for-max"]').val(modal['cron-repeat-for-max']);
    if (modal['email-repetitive'] == '1' || modal['email-repetitive-change'] == '1') {
        $('[name="alert-send-how-many"][value="every"]').prop('checked', true);
        if (modal['email-repetitive'] == '1') {
            $('#every-time-type').val('every');
        } else if (modal['email-repetitive-change'] == '1' && modal['email-repetitive-change-calcs'] == '1') {
            $('#every-time-type').val('every-change-calcs');
        } else {
            $('#every-time-type').val('every-change');
        }
    } else {
        if (modal['cron-repeat-for'] == '0' || modal['cron-repeat-for'] == '') {
            $('[name="alert-send-how-many"][value="once"]').prop('checked',true);
        } else {
            $('[name="alert-send-how-many"][value="schedule"]').prop('checked',true);
        }
    }
    $('input[name="email-to-freeform"]').val('');
    $('input[name="email-cc-freeform"]').val('');
    $('input[name="email-bcc-freeform"]').val('');
    $('[name=external-modules-configure-modal] select[name="email-to"] option').prop("selected", false);
    var langPrevSaved = lang.alerts_309;
    $.each(modal['email-to'].split(";"), function(i,e){
        if (e == '') return;
        if (!$('[name=external-modules-configure-modal] select[name="email-to"] option[value="' + e + '"]').length) {
            if ($('input[name="email-to-freeform"]').length && isEmail(e)) {
                var val = $('input[name="email-to-freeform"]').val();
                if (val != '') val += '; ';
                $('input[name="email-to-freeform"]').val(val+e);
            } else {
                if (!$('[name=external-modules-configure-modal] select[name="email-to"] optgroup[label="' + langPrevSaved + '"]').length) {
                    $('[name=external-modules-configure-modal] select[name="email-to"]').append('<optgroup class="email-select-temp" label="' + langPrevSaved + '"></optgroup>');
                }
                $('[name=external-modules-configure-modal] select[name="email-to"] optgroup[label="' + langPrevSaved + '"]').append('<option class="email-select-temp" value="' + e + '">' + e + '</option>');
            }
        }
        $('[name=external-modules-configure-modal] select[name="email-to"] option[value="' + e + '"]').prop("selected", true).addClass('ms-selection');
    });
    $('[name=external-modules-configure-modal] select[name="email-cc"] option').prop("selected", false);
    $.each(modal['email-cc'].split(";"), function(i,e){
        if (e == '') return;
        if (!$('[name=external-modules-configure-modal] select[name="email-cc"] option[value="' + e + '"]').length) {
            if ($('input[name="email-cc-freeform"]').length && isEmail(e)) {
                var val = $('input[name="email-cc-freeform"]').val();
                if (val != '') val += '; ';
                $('input[name="email-cc-freeform"]').val(val+e);
            } else {
                if (!$('[name=external-modules-configure-modal] select[name="email-cc"] optgroup[label="' + langPrevSaved + '"]').length) {
                    $('[name=external-modules-configure-modal] select[name="email-cc"]').append('<optgroup class="email-select-temp" label="' + langPrevSaved + '"></optgroup>');
                }
                $('[name=external-modules-configure-modal] select[name="email-cc"] optgroup[label="' + langPrevSaved + '"]').append('<option class="email-select-temp" value="' + e + '">' + e + '</option>');
            }
        }
        $('[name=external-modules-configure-modal] select[name="email-cc"] option[value="' + e + '"]').prop("selected", true).addClass('ms-selection');
    });
    $('[name=external-modules-configure-modal] select[name="email-bcc"] option').prop("selected", false);
    $.each(modal['email-bcc'].split(";"), function(i,e){
        if (e == '') return;
        if (!$('[name=external-modules-configure-modal] select[name="email-bcc"] option[value="' + e + '"]').length) {
            if ($('input[name="email-bcc-freeform"]').length && isEmail(e)) {
                var val = $('input[name="email-bcc-freeform"]').val();
                if (val != '') val += '; ';
                $('input[name="email-bcc-freeform"]').val(val+e);
            } else {
                if (!$('[name=external-modules-configure-modal] select[name="email-bcc"] optgroup[label="' + langPrevSaved + '"]').length) {
                    $('[name=external-modules-configure-modal] select[name="email-bcc"]').append('<optgroup class="email-select-temp" label="' + langPrevSaved + '"></optgroup>');
                }
                $('[name=external-modules-configure-modal] select[name="email-bcc"] optgroup[label="' + langPrevSaved + '"]').append('<option class="email-select-temp" value="' + e + '">' + e + '</option>');
            }
        }
        $('[name=external-modules-configure-modal] select[name="email-bcc"] option[value="' + e + '"]').prop("selected", true).addClass('ms-selection');
    });
    $('[name=external-modules-configure-modal] select[name="email-attachment-variable"] option').prop("selected", false);
    $.each(modal['email-attachment-variable'].split(";"), function(i,e){
        if (e == '') return;
        $('[name=external-modules-configure-modal] select[name="email-attachment-variable"] option[value="' + e + '"]').prop("selected", true).addClass('ms-selection');
    });

    if (!$('[name=external-modules-configure-modal] select[name="email-from"] option[value="'+modal['email-from']+'"]').length) {
        $('[name=external-modules-configure-modal] select[name="email-from"]').append('<option class="email-from-select-temp" value="'+modal['email-from']+'">'+modal['email-from']+' '+lang.survey_1237+'</option>');
    }
    $('[name=external-modules-configure-modal] select[name="email-from"]').val(modal['email-from']);

    $('[name=external-modules-configure-modal] select[name="email-failed"]').val(modal['email-failed']);
    $('[name=external-modules-configure-modal] input[name="email-subject"]').val(modal['email-subject']);
    $('[name=external-modules-configure-modal] tr[field="sendgrid-from"] input[name=email-from]').val(modal['email-from']);
    var templateSelector = document.getElementById('sendgrid-template-id')
    if (templateSelector.children.length === 0) {
        // if template selector options haven't loaded yet, create one for the
        // current template id selection
        var currentOption = document.createElement('option')
        currentOption.value = modal['sendgrid-template-id']
        currentOption.textContent = lang.data_entry_64
        templateSelector.appendChild(currentOption)
    }
    $('[name=external-modules-configure-modal] select[name="sendgrid-template-id"]').val(modal['sendgrid-template-id']);
    $('[name=external-modules-configure-modal] input[name="sendgrid-template-data"]').val(modal['sendgrid-template-data']);
    $('[name=external-modules-configure-modal] input[name="sendgrid-mail-send-configuration"]').val(modal['sendgrid-mail-send-configuration']);
    $('#new-sendgrid-template-data-key').val('');
    $('#new-sendgrid-template-data-value').val('');

    var sendgridMailSendConfiguration
    try {
        sendgridMailSendConfiguration = JSON.parse(modal['sendgrid-mail-send-configuration'])
    } catch (e) {
        sendgridMailSendConfiguration = {}
    }

    if (sendgridMailSendConfiguration.categories) {
        $('[name=external-modules-configure-modal] input[name="sendgrid-categories"]').val(sendgridMailSendConfiguration.categories.join(';'));
    } else {
        $('[name=external-modules-configure-modal] input[name="sendgrid-categories"]').val('');
    }

    $('[name=external-modules-configure-modal] input[name="sendgrid-bypass-list-management"]').prop('checked', sendgridMailSendConfiguration['bypass-list-management'] || false);
    $('[name=external-modules-configure-modal] input[name="sendgrid-bypass-spam-management"]').prop('checked', sendgridMailSendConfiguration['bypass-spam-management'] || false);
    $('[name=external-modules-configure-modal] input[name="sendgrid-bypass-bounce-management"]').prop('checked', sendgridMailSendConfiguration['bypass-bounce-management'] || false);
    $('[name=external-modules-configure-modal] input[name="sendgrid-bypass-unsubscribe-management"]').prop('checked', sendgridMailSendConfiguration['bypass-unsubscribe-management'] || false);
    $('[name=external-modules-configure-modal] input[name="sendgrid-sandbox-mode"]').prop('checked', sendgridMailSendConfiguration['sandbox-mode'] || false);
    $('[name=external-modules-configure-modal] input[name="sendgrid-click-tracking"]').prop('checked', sendgridMailSendConfiguration['click-tracking'] || false);
    $('[name=external-modules-configure-modal] input[name="sendgrid-open-tracking"]').prop('checked', sendgridMailSendConfiguration['open-tracking'] || false);
    $('[name=external-modules-configure-modal] input[name="sendgrid-subscription-tracking"]').prop('checked', sendgridMailSendConfiguration['subscription-tracking'] || false);
    var unsubscribeGroupSelector = document.getElementById('sendgrid-unsubscribe-group')
    if (unsubscribeGroupSelector.children.length === 0) {
        // if unsubscribe group selector options haven't loaded yet, create one for the
        // current unsubscribe group selection
        var currentOption = document.createElement('option')
        if (sendgridMailSendConfiguration['unsubscribe-group-id']) {
            currentOption.value = sendgridMailSendConfiguration['unsubscribe-group-id']
        }
        currentOption.textContent = lang.data_entry_64
        unsubscribeGroupSelector.appendChild(currentOption)
    }
    $('[name=external-modules-configure-modal] select[name="sendgrid-unsubscribe-group"]').val(sendgridMailSendConfiguration['unsubscribe-group-id'] || null);

    $('[name=external-modules-configure-modal] textarea[name="alert-message"]').val(modal['alert-message']);
    $('[name=external-modules-configure-modal] input[name="alert-expiration"]').val(modal['alert-expiration']);
    $('[name=external-modules-configure-modal] input[name="ensure-logic-still-true"]').prop('checked', modal['ensure-logic-still-true']=='1' );
    $('[name=external-modules-configure-modal] input[name="do-not-clear-recurrences"]').prop('checked', modal['do-not-clear-recurrences']=='1' );
    $('[name=external-modules-configure-modal] input[name="prevent-piping-identifiers"]').prop('checked', modal['prevent-piping-identifiers']=='1' );
    $('[name=external-modules-configure-modal] select[name="cron-send-email-on-next-day-type"]').val(modal['cron-send-email-on-next-day-type']);
    $('[name=external-modules-configure-modal] input[name="cron-send-email-on-next-time"]').val(modal['cron-send-email-on-next-time'].substring(0,5));

    $('[name=external-modules-configure-modal] input[name="cron-send-email-on-time-lag-days"]').val(modal['cron-send-email-on-time-lag-days']);
    $('[name=external-modules-configure-modal] input[name="cron-send-email-on-time-lag-hours"]').val(modal['cron-send-email-on-time-lag-hours']);
    $('[name=external-modules-configure-modal] input[name="cron-send-email-on-time-lag-minutes"]').val(modal['cron-send-email-on-time-lag-minutes']);

    $('[name=external-modules-configure-modal] :input[name="cron-send-email-on-field"]').val(modal['cron-send-email-on-field']);
    if (modal['cron-send-email-on-field'] == '') modal['cron-send-email-on-field-after'] = 'after';
    $('[name=external-modules-configure-modal] :input[name="cron-send-email-on-field-after"]').val(modal['cron-send-email-on-field-after']);

    if (!$('[name=external-modules-configure-modal] :input[name="alert-stop-type"] option[value="'+modal['alert-stop-type']+'"]').length) {
        if ($('[name=external-modules-configure-modal] :input[name="alert-stop-type"] option[value="RECORD_EVENT"]').length) {
            modal['alert-stop-type'] = 'RECORD_EVENT';
        } else {
            modal['alert-stop-type'] = 'RECORD';
        }
    }
    $('[name=external-modules-configure-modal] :input[name="alert-stop-type"]').val(modal['alert-stop-type']);

    checkSchedule(modal['email-repetitive'], modal['email-repetitive-change'], modal['cron-send-email-on'], modal['cron-send-email-on-date'], modal['cron-repeat-for']);
    checkTriggerSettings();
    showHideTriggerLimitInstanceWarning();

    // Add Files
    $('.external-modules-edoc-file, button.external-modules-configure-modal-delete-file').remove();
    var fileDocIds = [];
    for (var i=1; i<6 ; i++){
        $('[name=external-modules-configure-modal] input[name="email-attachment'+i+'"]').val('').prop('type','file');
        if (isNumeric(modal['email-attachment' + i])) {
            fileDocIds.push(modal['email-attachment' + i]+','+i);
        }
    }
    if (fileDocIds.length > 0) {
        getFileFieldElement(fileDocIds);
    }

    //clean up error messages
    $('#errMsgContainerModal').empty();
    $('#errMsgContainerModal').hide();
    $('[name=external-modules-configure-modal] input[name=form-name]').removeClass('alert');
    $('[name=external-modules-configure-modal] :input[name=email-to]').removeClass('alert');
    $('[name=external-modules-configure-modal] input[name=email-subject]').removeClass('alert');
    $('[name=external-modules-configure-modal] [name=alert-message]').removeClass('alert');

    // Hide CC fields and attachments, if necessary
    if (trim(modal['email-cc']+modal['email-bcc']+modal['email-failed']) == '') {
        $('tr[field="email-cc"], tr[field="email-bcc"], tr[field="email-failed"]').hide();
        $('#showCC').addClass('d-block').removeClass('d-none');
    } else {
        $('tr[field="email-cc"], tr[field="email-bcc"], tr[field="email-failed"]').show();
        $('#showCC').removeClass('d-block').addClass('d-none');
    }

    if (modal['alert-type'] == 'SENDGRID_TEMPLATE') {
        // Sendgrid alert type doesn't use email-failed field
        $('tr[field="email-failed"]').hide();
    }

    showAttachmentFields(false);
    multipageSurveyWarningCheckDo();
    checkMessageSettings();
    setEmailRepetitiveFields();
    showStopType();
    renderSendgridTemplateData();

    //Show modal
    $('[name=external-modules-configure-modal]').modal('show');
}

function renderSendgridTemplateData() {
    var dataString = document.getElementById("sendgrid-template-data").value
    var table = document.getElementById("sendgrid-template-data-table");

    while (table.firstChild) {
        table.removeChild(table.lastChild);
    }
    var headerRow = document.createElement("tr");
    var keyHeader = document.createElement("th");
    var valueHeader = document.createElement("th");
    var operationsHeader = document.createElement("th");
    keyHeader.textContent = lang.alerts_333;
    valueHeader.textContent = lang.data_import_tool_99;
    headerRow.appendChild(keyHeader);
    headerRow.appendChild(valueHeader);
    headerRow.appendChild(operationsHeader);
    table.appendChild(headerRow);

    if (dataString) {
        var data = JSON.parse(dataString)
        for (let [key, value] of Object.entries(data)) {
            var tr = document.createElement("tr");
            var keytd = document.createElement("td");
            var valuetd = document.createElement("td");
            var operationstd = document.createElement("td");
            keytd.style.wordWrap = 'break-word';
            valuetd.style.wordWrap = 'break-word';
            var keyp = document.createElement("p");
            var valuep = document.createElement("p");
            keyp.textContent = key;
            valuep.textContent = value;
            keytd.appendChild(keyp);
            valuetd.appendChild(valuep);

            var removeButton = document.createElement("button");
            removeButton.innerText = lang.global_19;
            removeButton.type = "button";
            removeButton.setAttribute('class', "me-4 btn btn-xs btn-rcred btn-rcred-light float-end");
            removeButton.onclick = function() { removeSendgridTemplateData(key); }
            operationstd.appendChild(removeButton)

            var editButton = document.createElement("button");
            editButton.innerText = lang.global_27;
            editButton.type = "button";
            editButton.setAttribute('class', "me-4 btn btn-xs btn-rcpurple btn-rcpurple-light float-end");
            editButton.onclick = function() { editSendgridTemplateData(key); }
            operationstd.appendChild(editButton)

            tr.appendChild(keytd);
            tr.appendChild(valuetd);
            tr.appendChild(operationstd);
            table.appendChild(tr);
        }
    }
}

function addSendgridTemplateData() {
    var templateData = document.getElementById("sendgrid-template-data");
    var data = {}
    if (templateData.value) {
        data = JSON.parse(templateData.value);
    }
    var newKey = document.getElementById("new-sendgrid-template-data-key").value;
    var newValue = document.getElementById("new-sendgrid-template-data-value").value;
    if (newKey !== "") {
        data[newKey] = newValue;
        templateData.value = JSON.stringify(data);
        renderSendgridTemplateData();
        updateSendgridTemplateDataButtonText();
        $("#new-sendgrid-template-data-key").val('');
        $("#new-sendgrid-template-data-value").val('');
    }
}

function removeSendgridTemplateData(key) {
    var templateData = document.getElementById("sendgrid-template-data");
    if (templateData.value) {
        var data = JSON.parse(templateData.value);
        if (data.hasOwnProperty(key)) {
            delete data[key];
        }
        templateData.value = JSON.stringify(data);
        renderSendgridTemplateData();
        updateSendgridTemplateDataButtonText();
    }
}

function editSendgridTemplateData(key) {
    var templateData = document.getElementById("sendgrid-template-data");
    if (templateData.value) {
        var data = JSON.parse(templateData.value);
        if (data.hasOwnProperty(key)) {
            document.getElementById("new-sendgrid-template-data-key").value = key;
            document.getElementById("new-sendgrid-template-data-value").value = data[key];
        }
        templateData.value = JSON.stringify(data);
        updateSendgridTemplateDataButtonText();
    }
}

function updateSendgridTemplateDataButtonText() {
    var templateData = document.getElementById("sendgrid-template-data");
    var data = {}
    if (templateData.value) {
        data = JSON.parse(templateData.value);
    }
    var key = document.getElementById("new-sendgrid-template-data-key").value;
    var button = document.getElementById("sendgrid-template-data-button")
    if (Object.keys(data).includes(key)) {
        button.textContent = lang.alerts_340;
    } else {
        button.textContent = lang.alerts_332;
    }
}

function validateSendGridCategories() {
    var categoriesString = document.getElementById('sendgrid-categories').value
    var categories = categoriesString.split(';')
    var uniqueCategories = categories.filter((category, index, categories) => categories.indexOf(category) === index && category != "");
    var issue = false
    for (var j in uniqueCategories) {
        var category = uniqueCategories[j]
        if (category.length > 255) {
            issue = true
        }
    }
    if (uniqueCategories.length > 10 || issue) {
        document.getElementById('sendgrid-categories-error-message').hidden = false
    } else {
        document.getElementById('sendgrid-categories-error-message').hidden = true
    }
}

function aggregateSendgridMailSendConfiguration() {
    var sendgridMailSendConfiguration = document.getElementById('sendgrid-mail-send-configuration');
    var sendgridMailSendConfigurationData = {}
    var unsubscribeGroupsSelect = document.getElementById('sendgrid-unsubscribe-group');
    var unsubscribeGroup = unsubscribeGroupsSelect.value
    var categoriesInput = document.getElementById('sendgrid-categories');
    var categories = []
    if (categoriesInput.value != "") {
        categories = categoriesInput.value.split(';')
    }
    var uniqueCategories = categories.filter((category, index, categories) => categories.indexOf(category) === index && category != "");
    var bypassListManagement = document.getElementById('sendgrid-bypass-list-management').checked || false
    var bypassSpamManagement = document.getElementById('sendgrid-bypass-spam-management').checked || false
    var bypassBounceManagement = document.getElementById('sendgrid-bypass-bounce-management').checked || false
    var bypassUnsubscribeManagement = document.getElementById('sendgrid-bypass-unsubscribe-management').checked || false
    var sandboxMode = document.getElementById('sendgrid-sandbox-mode').checked || false

    var clickTracking = document.getElementById('sendgrid-click-tracking').checked || false
    var openTracking = document.getElementById('sendgrid-open-tracking').checked || false
    var subscriptionTracking = document.getElementById('sendgrid-subscription-tracking').checked || false

    sendgridMailSendConfigurationData['unsubscribe-group-id'] = parseInt(unsubscribeGroup)
    // sendgrid only accepts 10 categories, so only use the first 10 if more are provided
    // There is an error message in the UI stating that max categories is 10.
    var usableCategories = uniqueCategories.filter(function(a){return a.length <= 255;}).slice(0, 10);
    sendgridMailSendConfigurationData['categories'] = usableCategories;
    sendgridMailSendConfigurationData['bypass-list-management'] = bypassListManagement
    sendgridMailSendConfigurationData['bypass-spam-management'] = bypassSpamManagement
    sendgridMailSendConfigurationData['bypass-bounce-management'] = bypassBounceManagement
    sendgridMailSendConfigurationData['bypass-unsubscribe-management'] = bypassUnsubscribeManagement
    sendgridMailSendConfigurationData['sandbox-mode'] = sandboxMode
    sendgridMailSendConfigurationData['click-tracking'] = clickTracking
    sendgridMailSendConfigurationData['open-tracking'] = openTracking
    sendgridMailSendConfigurationData['subscription-tracking'] = subscriptionTracking

    sendgridMailSendConfiguration.value = JSON.stringify(sendgridMailSendConfigurationData)
}

function validateSendGridBypassOptions(input) {
    var bypassListManagementInput = document.getElementById("sendgrid-bypass-list-management")
    var bypassSpamManagementInput = document.getElementById("sendgrid-bypass-spam-management")
    var bypassBounceManagementInput = document.getElementById("sendgrid-bypass-bounce-management")
    var bypassUnsubscribeManagementInput = document.getElementById("sendgrid-bypass-unsubscribe-management")
    if (input.id === "sendgrid-bypass-list-management" && input.checked) {
        bypassSpamManagementInput.checked = false
        bypassBounceManagementInput.checked = false
        bypassUnsubscribeManagementInput.checked = false
    } else if (['sendgrid-bypass-spam-management','sendgrid-bypass-bounce-management','sendgrid-bypass-unsubscribe-management'].indexOf(input.id) > -1 && input.checked) {
        bypassListManagementInput.checked = false
    }
}

function toggleAdvancedSendgridSettingsDisplay() {
    var settingsButton = document.getElementById("sendgrid-advanced-settings-button")
    if (settingsButton.textContent === lang.alerts_397) {
        settingsButton.textContent = lang.alerts_398
        $('#advanced-sendgrid-settings-banner').show();
        $('#code_modal_table_update tr[field="sendgrid-unsubscribe-group"]').show();
        $('#code_modal_table_update tr[field="sendgrid-mail-settings"]').show();
        $('#code_modal_table_update tr[field="sendgrid-tracking-settings"]').show();
        $('#code_modal_table_update tr[field="sendgrid-categories"]').show();
    } else {
        settingsButton.textContent = lang.alerts_397
        $('#advanced-sendgrid-settings-banner').hide();
        $('#code_modal_table_update tr[field="sendgrid-unsubscribe-group"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-mail-settings"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-tracking-settings"]').hide();
        $('#code_modal_table_update tr[field="sendgrid-categories"]').hide();
    }
}

// Reload the Survey Invitation Log for another "page" when paging the log
function loadNotificationLog(pagenum) {
    showProgress(1);
    window.location.href = app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:setup&log=1&pagenum='+pagenum+
        '&filterBeginTime='+$('#filterBeginTime').val()+'&filterEndTime='+$('#filterEndTime').val()+'&filterRecord='+$('#filterRecord').val()+'&filterAlert='+$('#filterAlert').val();
}

function showAttachmentFields(forceOpen) {
    var hideBtn = false;
    var alert_type = 'EMAIL'
    if ($('[name=external-modules-configure-modal] input[name="alert-type"]').length) {
        alert_type = $('[name=external-modules-configure-modal] input[name="alert-type"]:checked').val();
    }
    $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment2"], tr[field="email-attachment3"], tr[field="email-attachment4"], tr[field="email-attachment5"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').hide();
    if (alert_type == 'EMAIL' || alert_type == 'SENDGRID_TEMPLATE') {
        if (forceOpen || ($(':input[name="email-attachment-variable"]').length && $(':input[name="email-attachment-variable"]').val().length > 0)) {
            $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').show();
            hideBtn = true;
        }
        if ($('input[name="email-attachment4"]').val() != '') {
            $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment2"], tr[field="email-attachment3"], tr[field="email-attachment4"], tr[field="email-attachment5"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').show();
            hideBtn = true;
        }
        if ($('input[name="email-attachment3"]').val() != '') {
            $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment2"], tr[field="email-attachment3"], tr[field="email-attachment4"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').show();
            hideBtn = true;
        }
        if ($('input[name="email-attachment2"]').val() != '') {
            $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment2"], tr[field="email-attachment3"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').show();
            hideBtn = true;
        }
        if ($('input[name="email-attachment1"]').val() != '') {
            $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment2"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').show();
            hideBtn = true;
        }
    } else {
        hideBtn = true;
    }
    if (hideBtn) {
        $('tr[field="email-attachment-btn"]').hide();
    } else {
        $('tr[field="email-attachment-btn"]').show();
    }
    addNewAttachmentBtn();
    enableMultiSelect2();
}

function addNewAttachmentBtn() {
    $('.addNewAttachmentBtn').remove();
    if ($('td.email-attach-label-td:visible:last').length > 0 && $('tr[field="email-attachment5"]:visible').length == 0) {
        var lastVisibleAttachFld = 'email-attachment' + ($('td.email-attach-label-td:visible:last').parent().find('td:eq(1) input:first').prop('name').replace('email-attachment', '') * 1 + 1);
        $('td.email-attach-label-td:visible:last').append('<div class="fs11 mt-2 ms-3 addNewAttachmentBtn"><button onclick=\'$("tr[field='+lastVisibleAttachFld+']").show();addNewAttachmentBtn();return false;\' class="btn btn-outline-success btn-xs" style="border:0;"><i class="fas fa-plus"></i> '+lang.alerts_38+'</button></div>');
    }
}

/***FILES***/
function getAttributeValueHtml(s){
    if(typeof s == 'string'){
        s = s.replace(/"/g, '&quot;');
        s = s.replace(/'/g, '&apos;');
    }

    if (typeof s == "undefined") {
        s = "";
    }

    return s;
}

function getFileFieldElement(nextValues)
{
    if (nextValues.length < 1) {
        showAttachmentFields(false);
        return;
    }
    var valueCSV = nextValues.shift();
    var arr = valueCSV.split(',');
    var value = arr[0];
    var file_number = arr[1];
    var name = "email-attachment"+file_number+"";
    if (typeof value != "undefined" && value !== "" && value != null) {
        var html = '<input type="hidden" name="' + name + '" value="' + getAttributeValueHtml(value) + '" >';
        html += '<span class="external-modules-edoc-file"></span> ';
        html += '<button class="btn btn-xs btn-outline-danger external-modules-configure-modal-delete-file" style="border:0;" onclick="hideFile('+value+','+file_number+')"><i class="fas fa-times"></i> '+lang.docs_72+'</button>';
        $.post(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:getEdocName', { edoc : value }, function(data) {
            $("[name='"+name+"']").closest("tr").find(".external-modules-edoc-file").html("<b>" + data.doc_name + "</b> ");
            // Call recursively until we're done with all of them
            getFileFieldElement(nextValues);
        });
    } else {
        var html = '<input type="file" name="' + name + '" value="' + getAttributeValueHtml(value) + '" class="external-modules-input-element">';
    }
    $('[name=external-modules-configure-modal] input[name="email-attachment'+file_number+'"]').parent().html(html);
}

function hideFile(value,file_number){
    var name = "email-attachment"+file_number;
    var html = '<input type="file" name="' + name + '" value="" class="external-modules-input-element">';
    html += '<input type="hidden" name="'+name+'" value="'+value+'" class="external-modules-input-element deletedFile">';
    $('[name=external-modules-configure-modal] input[name="email-attachment'+file_number+'"]').parent().html(html);
}

function deleteEmailAlert(index,modal,indexmodal){
    $('#'+indexmodal).val(index);
    $('#'+modal).modal('show');
}

function reactivateEmailAlert(index){
    ajaxLoadOptionAndMessage("&index_modal_delete_user="+index+"&enable=1",app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:deleteAlert',"R");
}

function duplicateEmailAlert(index){
    ajaxLoadOptionAndMessage("&index_duplicate="+index,app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:copyAlert',"P");
}

function addQueue(index,form){
    $('#external-modules-configure-modal-addQueue').modal('show');
    $('#index_modal_queue').val(index);
    alert('TODO: add form+event dropdown here!')
    // var data = "index="+index+"&form="+form+"&queue=1";
    // $.post(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:getEventByForm', data, function(returnData){
    //     jsonAjax = jQuery.parseJSON(returnData);
    //     $('#event_queue').html(jsonAjax.event);
    // });
}

/**
 * Function that reloads the page and updates the success message
 * @param letter
 * @returns {string}
 */
function getUrlMessageParam(letter){
    var url = window.location.href;
    if (letter == '') return url;
    if (url.substring(url.length-1) == "#")
    {
        url = url.substring(0, url.length-1);
    }
    if(window.location.href.match(/(&message=)([A-Z]{1})/)){
        url = url.replace( /(&message=)([A-Z]{1})/, "&message="+letter );
    }else{
        url = url + "&message="+letter;
    }
    return url;
}

function checkToFields() {
    var errMsg = [];
    var hasFreeformEmails = $('input[name="email-to-freeform"]').length;
    if ($('[name=external-modules-configure-modal] :input[name=email-to] option:selected').length == 0) {
        if (!hasFreeformEmails || (hasFreeformEmails && $('input[name="email-to-freeform"]').val().trim().length == 0)) {
            $('[name=external-modules-configure-modal] :input[name=email-to]').addClass('alert');
            errMsg.push(lang.alerts_197);
        }
    } else {
        $('[name=external-modules-configure-modal] :input[name=email-to]').removeClass('alert');
    }
    return errMsg;
}

function getSendgridData(async, checkConfigurations = false) {
    if (!sendgrid_enabled) return;
    $.ajax({
        url: app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:getSendgridData',
        type: 'POST',
        data:{ redcap_csrf_token: redcap_csrf_token },
        async: async,
        success:
            function(data){
                fresh_sendgrid_data = JSON.parse(data);
                sendgrid_alerts_email_domain_allowlist = fresh_sendgrid_data['sendgrid_alerts_email_domain_allowlist'] || []
                sendgrid_alerts_email_allowlist = fresh_sendgrid_data['sendgrid_alerts_email_allowlist'] || []
                sendgrid_template_ids = fresh_sendgrid_data['sendgrid_template_ids'] || []
                sendgrid_from_emails = fresh_sendgrid_data['sendgrid_from_emails'] || []
                sendgrid_unsubscribe_groups = fresh_sendgrid_data['sendgrid_unsubscribe_groups'] || []
                var zeroTemplateMessage = document.getElementById('sendgrid-zero-templates-message')
                var zeroSendersMessage = document.getElementById('sendgrid-zero-senders-message')
                var sendgridFromList = document.getElementById('sendgridFromList')
                sendgridFromList.innerHTML = '' // remove old options
                if (sendgrid_from_emails.length === 0) {
                    zeroSendersMessage.hidden = false
                } else {
                    zeroSendersMessage.hidden = true
                    for (var i in sendgrid_from_emails){
                        var newOption = document.createElement('option')
                        newOption.value = sendgrid_from_emails[i]
                        sendgridFromList.appendChild(newOption)
                    }
                }
                var templateSelector = document.getElementById('sendgrid-template-id')
                var previousTemplateIdValue = templateSelector.value
                templateSelector.innerHTML = '' // remove old options
                if (sendgrid_template_ids.length === 0) {
                    zeroTemplateMessage.hidden = false
                } else {
                    zeroTemplateMessage.hidden = true
                    for (var k in sendgrid_template_ids) {
                        var newOption = document.createElement('option')
                        newOption.value = k
                        newOption.textContent = sendgrid_template_ids[k]
                        templateSelector.appendChild(newOption)
                    }
                    templateSelector.value = previousTemplateIdValue
                }
                var unsubscribeGroupsSelector = document.getElementById('sendgrid-unsubscribe-group')
                var zeroUnsubscribeGroupsMessage = document.getElementById('sendgrid-zero-unsubscribe-groups-message')
                var previousUnsubscribeGroupsValue = unsubscribeGroupsSelector.value
                unsubscribeGroupsSelector.innerHTML = '' // remove old options
                if (sendgrid_unsubscribe_groups.length === 0) {
                    zeroUnsubscribeGroupsMessage.hidden = false
                } else {
                    zeroUnsubscribeGroupsMessage.hidden = true
                    for (var k in sendgrid_unsubscribe_groups) {
                        var newOption = document.createElement('option')
                        newOption.value = k
                        newOption.textContent = sendgrid_unsubscribe_groups[k]
                        unsubscribeGroupsSelector.appendChild(newOption)
                    }
                    var noneOption = document.createElement('option')
                    unsubscribeGroupsSelector.appendChild(noneOption)
                    unsubscribeGroupsSelector.value = previousUnsubscribeGroupsValue
                }
                if (checkConfigurations) {
                    var warnings = []
                    for (var k in project_alert_data) {
                        var alert = project_alert_data[k]
                        if (alert.alert_type === 'SENDGRID_TEMPLATE') {
                            if (sendgrid_template_ids[alert.sendgrid_template_id] === undefined) {
                                warnings.push(lang.alerts_24 + ' #' + alert.alert_number + ': ' + alert.alert_title + ' ' + lang.alerts_351)
                            }
                        }
                    }
                    if (warnings.length > 0) {
                        var warningMessage = '<ul>'
                        for (var i in warnings) {
                            warningMessage += '<li>' + warnings[i] + '</li>'
                        }
                        warningMessage += '</ul>'
                        simpleDialog(warningMessage, lang.global_48)
                    }
                }
            }
    });
}

function checkSendgridFromEmail() {
    getSendgridData(false, false);
    var errMsg = [];
    var sendgrid_from = $('[name=external-modules-configure-modal] tr[field="sendgrid-from"] input[name=email-from]').val();
    if (isEmail(sendgrid_from)) {
        var sendgrid_from_domain = sendgrid_from.split('@')[1];
        if (!sendgrid_alerts_email_domain_allowlist.includes(sendgrid_from_domain) && !sendgrid_alerts_email_allowlist.includes(sendgrid_from)) {
            errMsg.push(lang.alerts_343);
            $('[name=external-modules-configure-modal] tr[field="sendgrid-from"] input[name=email-from]').addClass('alert');
        }
    } else {
        errMsg.push(lang.alerts_342);
        $('[name=external-modules-configure-modal] tr[field="sendgrid-from"] input[name=email-from]').addClass('alert');
    }
    if (errMsg.length === 0) {
        $('[name=external-modules-configure-modal] tr[field="sendgrid-from"] input[name=email-from]').removeClass('alert');
    }
    return errMsg;
}


/**
 * Function that checks if all required fields form the alerts are filled @param errorContainer
 * @returns {boolean}
 */
function checkRequiredFieldsAndLoadOption(){
    $('#succMsgContainer').hide();
    $('#errMsgContainerModal').hide();
    if ($('[name=external-modules-configure-modal] input[name="alert-type"]').length) {
        var alert_type = $('[name=external-modules-configure-modal] input[name="alert-type"]:checked').val();
    } else {
        alert_type = 'EMAIL';
    }

    var errMsg = [];
    if (alert_type == "EMAIL") {
        errMsg = errMsg.concat(checkToFields());
        if ($('[name=external-modules-configure-modal] input[name=email-subject]').val() === "") {
            errMsg.push(lang.alerts_214);
            $('[name=external-modules-configure-modal] input[name=email-subject]').addClass('alert');
        } else {
            $('[name=external-modules-configure-modal] input[name=email-subject]').removeClass('alert');
        }
    } else if (alert_type == "SENDGRID_TEMPLATE") {
        if ($('[name=external-modules-configure-modal] select[name=sendgrid-template-id]').val() === null
            || $('[name=external-modules-configure-modal] select[name=sendgrid-template-id]').val() === "") {
            errMsg.push(lang.alerts_327);
            $('[name=external-modules-configure-modal] select[name=sendgrid-template-id]').addClass('alert');
        } else {
            $('[name=external-modules-configure-modal] select[name=sendgrid-template-id]').removeClass('alert');
        }
        if ($('[name=external-modules-configure-modal] tr[field="sendgrid-from"] input[name=email-from]').val() === "") {
            errMsg.push(lang.alerts_325);
            $('[name=external-modules-configure-modal] tr[field="sendgrid-from"] input[name=email-from]').addClass('alert');
        } else {
            $('[name=external-modules-configure-modal] tr[field="sendgrid-from"] input[name=email-from]').removeClass('alert');
            errMsg = errMsg.concat(checkSendgridFromEmail());
        }
        errMsg = errMsg.concat(checkToFields());
    } else {
        var hasFreeformPhones = $('input[name="phone-number-to-freeform"]').length;
        if ($(':input[name=phone-number-to]').val() == ''
            && (!hasFreeformPhones || (hasFreeformPhones && $('input[name="phone-number-to-freeform"]').val().trim().length == 0))) {
            $(':input[name=phone-number-to-freeform]').addClass('alert');
            errMsg.push(lang.alerts_197);
        } else {
            $(':input[name=phone-number-to-freeform]').removeClass('alert');
        }
    }

    if ($('[name=external-modules-configure-modal] select[name=form-name]').val() == "-" && $('[name=external-modules-configure-modal] :input[name=alert-condition]').val().trim() == "") {
        errMsg.push(lang.alerts_198);
        $('[name=external-modules-configure-modal] select[name=form-name]').addClass('alert');
        $('[name=external-modules-configure-modal] :input[name=alert-condition]').addClass('alert');
    }else{
        $('[name=external-modules-configure-modal] select[name=form-name]').removeClass('alert');
    }
    var editor_text = tinymce.activeEditor.getContent();
    if(editor_text == "" && alert_type !== "SENDGRID_TEMPLATE"){
        errMsg.push(lang.alerts_39);
        $('#alert-message_ifr').addClass('alert');
    }else{ $('#alert-message_ifr').removeClass('alert');}

    if (errMsg.length > 0) {
        $('#errMsgContainerModal').empty();
        $.each(errMsg, function (i, e) {
            $('#errMsgContainerModal').append('<div>' + e + '</div>');
        });
        $('#errMsgContainerModal').show();
        $('html,body').scrollTop(0);
        $('[name=external-modules-configure-modal]').scrollTop(0);
        return false;
    }
    else {
        return true;
    }
}

function ajaxLoadOptionAndMessage(data, url, message){
    $.post(url, data, function(returnData){
        jsonAjax = jQuery.parseJSON(returnData);
        if(jsonAjax.status == 'success'){
            //refresh page to show changes
            if(jsonAjax.message != '' && jsonAjax.message != undefined){
                message = jsonAjax.message;
            }
            var newUrl = getUrlMessageParam(message);
            if (newUrl.substring(newUrl.length-1) == "#")
            {
                newUrl = newUrl.substring(0, newUrl.length-1);
            }
            window.location.href = newUrl;
        } else {
	        alert(woops);
        }
    });
}

// Hide Step 1C if choose either of the "every time" options in 2B
function showStopType() {
    if (!$('tr[field="alert-stop-type"]').length) return;
    if ($('[name="alert-send-how-many"]:checked').val() == 'every') {
        if ($('tr[field="alert-stop-type"]').text().trim() != '') {
            // Hide (but only if visible)
            $('tr[field="alert-stop-type"]').hide();
        }
    } else {
        $('tr[field="alert-stop-type"]').show();
    }
}

// Set values for email-repetitive fields
function setEmailRepetitiveFields() {
    // Reset to defaults
    $(':input[name="email-repetitive"], :input[name="email-repetitive-change"], :input[name="email-repetitive-change-calcs"]').val('0');
    // Set based on selections
    if ($('[name="alert-send-how-many"]:checked').val() == 'every') {
        var everyTimeType = $('#every-time-type option:selected').val();
        if (everyTimeType == 'every-change-calcs') {
            $(':input[name="email-repetitive"]').val('0');
            $(':input[name="email-repetitive-change"], :input[name="email-repetitive-change-calcs"]').val('1');
        } else if (everyTimeType == 'every-change') {
            $(':input[name="email-repetitive-change"]').val('1');
            $(':input[name="email-repetitive"], :input[name="email-repetitive-change-calcs"]').val('0');
        } else {
            $(':input[name="email-repetitive"]').val('1');
            $(':input[name="email-repetitive-change"], :input[name="email-repetitive-change-calcs"]').val('0');
        }
    }
}

function uploadRepeatableInstances(data){
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:displayRepeatingFormTextboxQueue', data, function(returnData){
        jsonAjax = jQuery.parseJSON(returnData);
        if (jsonAjax.status == 'success') {
            $('#addQueueInstance').html(jsonAjax.instance);
        }
        else {
            alert(woops);
        }
    });
}

function getInstances(element){
    uploadRepeatableInstances('event='+element.value+'&index_modal_queue='+$('#index_modal_queue').val());
}
function saveFilesIfTheyExist(url, files, alertExists, data) {
    var lengthOfFiles = 0;
    var formData = new FormData();
    for (var name in files) {
        lengthOfFiles++;
        formData.append(name, files[name]);   // filename agnostic
    }
    // Find available file fields to use
    var filesAvail = new Array();
    var x=0, thisFileVal;
    for (var i=1; i<=5; i++){
        thisFileVal = $('input[name="email-attachment'+i+'"]').val();
        if (thisFileVal != '' && !isNumeric(thisFileVal)) {
            filesAvail[x++] = i;
        }
    }
    if (lengthOfFiles > 0) {
        x = 0;
        $.ajax({
            url: url,
            data: formData,
            processData: false,
            contentType: false,
            async: false,
            type: 'POST',
            success: function(returnData) {
                if (returnData.status == 'success') {
                    var attach = returnData.doc_ids.split(',');
                    for (var i=0; i<attach.length; i++){
                        data += '&email-attachment'+filesAvail[i]+'='+attach[i];
                    }
                } else {
                    alert(returnData.status+" "+lang.alerts_40);
                }
            },
            error: function(e) {
                alert(lang.alerts_40+" "+JSON.stringify(e));
            }
        });
    }
    return data;
}

function deleteFile(index, data) {
    $('.deletedFile').each(function() {
        var inputname = $(this).attr('name');
        $.ajax({
            url: app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:deleteAttachment',
            type: 'POST',
            data: { key: inputname, edoc: $(this).val(), index: index, redcap_csrf_token: redcap_csrf_token },
            async: false,
            success:
                function(data2){
                    if (data2.status == "success") {
                        $('input[type="hidden"][name="'+inputname+'"]').val('');
                        data += '&'+inputname+'=';
                    } else {
                        // failure
                        alert(lang.alerts_41+" "+JSON.stringify(data2));
                    }
                }
        });
    });
    return data;
};

function showChangeRecurrenceDialog() {
    return (changedCronSendEmailOn && $('#index_modal_update').val() != '' && $('input[name="alert-send-how-many"][value="schedule"]').prop('checked'));
}

function datepicker_init() {
    if ($('.alert-datetimepicker').length) {
        $('.alert-datetimepicker').datetimepicker({
            buttonText: lang.alerts_42, yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
            hour: currentTime('h'), minute: currentTime('m'),
            showOn: 'both', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'HH:mm', constrainInput: false
        });
    }
    if ($('.alert-datepicker').length) {
        $('.alert-datepicker').datepicker({
            dateFormat: user_date_format_jquery,
            yearRange: '-100:+10', showOn: 'button', buttonImage: app_path_images+'date.png', buttonImageOnly: true,
            showOn: 'both', changeMonth: true, changeYear: true
        });
    }
}

function multipageSurveyWarningCheck() {
    if (multipageSurveys.length == 0 || $('select[name="form-name"]').val() == null) return false;
    var formArr = $('select[name="form-name"]').val().split('-');
    var form = formArr[0];
    // If the selected form is not a multi-page survey instrument, then leave
    if (!in_array(form, multipageSurveys)) return false;
    // If the "every time send" option is not selected, then leave
    if ($('[name="alert-send-how-many"]:checked').val() != 'every') return false;
    // Display message about using multi-page survey with "every time send" option
    return true;
}

function multipageSurveyWarningCheckDo() {
    if (multipageSurveyWarningCheck()) {
        $('#email-repetitive-multipage-warning').show();
    } else {
        $('#email-repetitive-multipage-warning').hide();
    }
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

function datepicker_destroy() {
    $('.alert-datetimepicker').datetimepicker("destroy");
    $('.alert-datepicker').datepicker("destroy");
}

function enableMultiSelect2() {
    $("#email-to, #email-cc, #email-bcc").select2({
        placeholder: lang.alerts_43,
        dropdownParent: $('#external-modules-configure-modal #code_modal_table_update')
    });
    $("#email-attachment-variable").select2({
        placeholder: lang.alerts_44,
        dropdownParent: $('#external-modules-configure-modal #code_modal_table_update')
    });
    $("#phone-number-to").select2({
        placeholder: lang.alerts_43,
        dropdownParent: $('#external-modules-configure-modal #code_modal_table_update')
    });
}

// Load ajax call into dialog to re-evaluate ASIs
function dialogReevalAlerts() {
    showProgress(1);
    $.post(app_path_webroot+'index.php?route=AlertsController:reevalAlerts&action=view&pid='+pid, { }, function(data){
        showProgress(0,0);
        if (data == '0') {
            alert(woops);
            return false;
        }
        simpleDialog(data,lang.alerts_252,'reeval_alert_dlg',700, null, window.lang.global_53, 'saveReevalAlerts();', lang.alerts_251);
    });
}

// Re-evaluate ASIs
function saveReevalAlerts() {
    var se = new Array();
    var i = 0;
    $('#reeval_alert_dlg input[type=checkbox]').each(function(){
        if ($(this).prop('checked') && $(this).attr("id") !== 'alerts-dry-run-toggle-switch') {
            se[i++] = $(this).prop('id').replace('alert_','');
        }
    });
    if (se.length == 0) {
        simpleDialog('ERROR: You must select an alert','ERROR',null,null,'dialogReevalAlerts()',null);
        return false;
    }
    showProgress(1);
    var is_dry_run = $('#alerts-dry-run-toggle-switch').prop('checked') === true ? 1 : 0;
    $.post(app_path_webroot+'index.php?route=AlertsController:reevalAlerts&action=save&pid='+pid, { alert_ids: se.join(','), is_dry_run: is_dry_run }, function(data){
        showProgress(0,0);
        if (data == '0') {
            alert(woops);
            return false;
        }
        let obj = {
            html: data.split('</h1>')[1],
            icon: 'success'
        };
        if (is_dry_run) {
            let header = data.split('</h1>')[0];
            obj.title = '<span style="color:#9c2626b3;">' + header.split('<h1>')[1]+ '</span>';
            obj.confirmButtonColor = '#9c2626b3';
            delete obj.icon;
        }
        Swal.fire(obj);
    });
}

var alertsDataTable;
var dataTableSettings = {
    "autoWidth": false,
    "processing": true,
    "paging": false,
    "info": false,
    "aaSorting": [],
    "fixedHeader": { header: false, footer: false },
    "searching": true,
    "ordering": false,
    "oLanguage": { "sSearch": "" },
}

$(function() {
    $('[data-bs-toggle="popover"]').click(function(e) {
        // Hide any other opened popups first
        if ($('.popover:visible').length > 0) {
            $('.popover').hide();
        }
        bootstrap.Popover.getOrCreateInstance(this).dispose();
        // Show popup
        popover = new bootstrap.Popover(e.target, {
            html: true,
            title: '<span style="padding-top:0px;">'+$(this).data('title')+'<span class="close" style="line-height: 0.5;padding-top:0px;padding-left: 10px">&times;</span></span>',
            content: $(this).data('content')
        });
        popover.show();
        $('.close').css('cursor', 'pointer');
    });

    // Hide popup if clicked anywhere on page (outside popup)
    $('html').on('click', function (e) {
        if(!$(e.target).is('[data-bs-toggle="popover"]') && $(e.target).closest('.popover').length == 0) {
            $('[data-bs-toggle="popover"]').popover('hide');
        }
    });
    $(document).on("click", ".popover .close" , function(){
        $(this).closest(".popover").hide();
    });

    //To prevent the popover from scrolling up on click
    $("a[rel=popover]")
        .popover()
        .click(function(e) {
            e.preventDefault();
        });

    //Messages on reload
    if(message != "") {
        $("#succMsgContainer").html(message);
        // After adding/copying alert display success message just above highlighted alert (not at top) so that will be visible to user
        var msgBox = $("#succMsgContainer");
        var prevId = $('#customizedAlertsPreview tr:last').prevUntil().not('.alert-deleted').attr('id');
        if (prevId != '' && prevId != undefined) {
            $("#"+prevId+" td:first").append($("#succMsgContainer").clone().attr('id','newSuccMsgContainer').css({'margin-top':'10px', 'margin-bottom':'0px'}));
            if (message_letter == 'A' || message_letter == 'P') {
                msgBox = $("#newSuccMsgContainer");
            }
        }
        setTimeout(function(){
            msgBox.slideToggle('normal');
        },300);
        setTimeout(function(){
            msgBox.slideToggle(1200);
        }, 5000);
    }

    $('#ensure-logic-still-true, #alert-send-how-many3').click(function(e){
        showHideTriggerLimitInstanceWarning();
    });

    $('[name="alert-trigger"], [name="cron-send-email-on"]').click(function(e){
        displayTriggerSettings();
        showHideTriggerLimitInstanceWarning();
        // Set bold on label
        var thisname = $(this).prop('name');
        if (thisname == 'alert-trigger') {
            $('[name="alert-trigger"]').parent().find('label[for]').removeClass('boldish');
            var idVal = $(this).attr("id");
            var label = $("label[for='"+idVal+"']");
            label.addClass('boldish');
        }
        // Hide/display email-repetitive field
        if ($('[name="alert-trigger"]:checked').val() == 'logic' || $('[name="cron-send-email-on"]:checked').val() != 'now') {
            $('#alert-send-how-many2').parent().parent().hide();
            // If field is checked, then move selection to other option since we're hiding this one
            if ($('[name="alert-send-how-many"]:checked').val() == 'every') {
                $('[name="alert-send-how-many"][value="once"]').prop('checked',true).trigger('click');
            }
        } else {
            $('#alert-send-how-many2').parent().parent().show();
        }
    });

    /***SCHEDULED EMAIL OPTIONS***/
    $('[name="cron-send-email-on"]').on('click', function(e){
        if($(this).val() == 'date'){
            $('[name="cron-send-email-on-date"]').focus();
        }
    });

    $('#addQueue .close').on('click', function () {
        $('#addQueueInstance').html('');
    });

    $('#btnModalRescheduleForm2').click(function() {
        $('#saveAlert').submit();
    });

    $('[name="alert-send-how-many"]').click(function(){
        setEmailRepetitiveFields();
        showStopType();
    });

    $('#every-time-type').change(function(){
        setEmailRepetitiveFields();
        showStopType();
    });

    $('#external-modules-configure-modal-record').on('hidden.bs.modal', function () {
        //clean up
        $('[name=preview_record_id]').val('');
        $('#modal_message_record_preview').html('');
    });

    $('#btnModalsaveAlert').click(function()
    {
        // Make sure that email text does not contain survey-link or survey-url without an instrument
        var editor_text = tinymce.activeEditor.getContent();
        if (editor_text.indexOf('[survey-url]') > -1 || editor_text.indexOf('[survey-link]') > -1 || editor_text.indexOf('[form-url]') > -1 || editor_text.indexOf('[form-link]') > -1) {
            simpleDialog(lang.alerts_46, lang.alerts_45);
            return;
        }

        if (showChangeRecurrenceDialog()) {
            $('[name=cron-queue]').prop('checked', true);
            $('#external-modules-configure-modal').modal('hide');
            $('#external-modules-configure-modal-schedule-confirmation').modal('show');
        } else {
            $('#saveAlert').submit();
        }
    });

    $('#saveAlert').submit(function ()
    {
        // Clear form-name if using logic only
        if ($('input[name="alert-trigger"]').val() == 'logic') {
            $('[name=external-modules-configure-modal] select[name="form-name"]').val('');
        }

        // make sure email-from inputs are synced on save to avoid misconfiguration
        var alert_type = 'EMAIL'
        if ($('[name=external-modules-configure-modal] input[name="alert-type"]').length) {
            alert_type = $('[name=external-modules-configure-modal] input[name="alert-type"]:checked').val();
        }
        if (alert_type === 'SENDGRID_TEMPLATE') {
            $('[name=external-modules-configure-modal] select[name="email-from"]').val($('[name=external-modules-configure-modal] tr[field="sendgrid-from"] input[name=email-from]').val());
            // aggregate mail send option values into sendgrid-mail-send-configuration json string
            aggregateSendgridMailSendConfiguration()
        } else if (alert_type === 'EMAIL') {
            $('[name=external-modules-configure-modal] tr[field="sendgrid-from"] input[name=email-from]').val($('[name=external-modules-configure-modal] select[name="email-from"]').val());
        }

        var data = $('#saveAlert').serialize();
        var editor_text = tinymce.activeEditor.getContent();
        data += "&alert-message-editor=" + encodeURIComponent(editor_text);
        data += "&email-to="+ encodeURIComponent($('#email-to').val());
        data += "&email-cc="+encodeURIComponent($('#email-cc').val());
        data += "&email-bcc="+encodeURIComponent($('#email-bcc').val());
        if ($('#phone-number-to').length) {
            data += "&phone-number-to=" + encodeURIComponent($('#phone-number-to').val());
        }

        if ($('#email-attachment-variable').length) {
            data += "&email-attachment-variable=" + encodeURIComponent($('#email-attachment-variable').val());
        }

        var files = {};
        $('#saveAlert').find('input, select, textarea').each(function(index, element){
            var element = $(element);
            var name = element.attr('name');
            var type = element[0].type;

            if (type == 'file') {
                name = name.replace("", "");
                // only store one file per variable - the first file
                jQuery.each(element[0].files, function(i, file) {
                    if (typeof files[name] == "undefined") {
                        files[name] = file;
                    }
                });
            }
        });
        if (checkRequiredFieldsAndLoadOption()) {
            //close confirmation modal
            $('#external-modules-configure-modal-schedule-confirmation').modal('hide');
            $('#external-modules-configure-modal').modal('hide');
            var index = $('#index_modal_update').val();
            data = deleteFile(index, data);
            data = saveFilesIfTheyExist(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:saveAttachment', files, 1, data);
            ajaxLoadOptionAndMessage(data,app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:saveAlert',(index === "" ? "A" : "U"));
        }
        return false;
    });

    $('#deleteUserForm').submit(function () {
        var data = $('#deleteUserForm').serialize();
        ajaxLoadOptionAndMessage(data,app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:deleteAlert',"D");
        return false;
    });

    $('#deleteForm').submit(function () {
        var data = $('#deleteForm').serialize();
        ajaxLoadOptionAndMessage(data,app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:deleteAlertPermanent',"B");
        return false;
    });

    //To filter the data
    $.fn.dataTable.ext.search.push(
        function( settings, data, dataIndex ) {
            var deleted = $('#deleted_alerts').is(':checked');
            var column_deleted = data[3];
            if (deleted && column_deleted == 'Y') {
                return true;
            } else if (!deleted && column_deleted == 'N'){
                return true;
            }
            return false;
        }
    );

    // DataTable
    alertsDataTable = $('#customizedAlertsPreview').DataTable(dataTableSettings);
    $('#customizedAlertsPreview_filter input[type="search"]').attr('type','text').prop('placeholder','Search');
    $('#customizedAlertsPreview').show();
    alertsDataTable.draw();

    //When message reactivated reload on the Deleted status
    if(message_letter == 'R' || message_letter === 'N'){
        $('#deleted_alerts').prop('checked',true);
        alertsDataTable.draw();
    }
    // If copied/created an alert, then highlight the alert
    else if (message_letter == 'P' || message_letter == 'A') {
        $("html, body").animate({ scrollTop: $(document).height() }, "normal");
        $('#customizedAlertsPreview tr:last td:visible').effect('highlight',{},4000);
    }

    $('.trigger-descrip').each(function(){
        $clamp(document.getElementById($(this).prop('id')), {clamp: 3});
    });

    $('.expire-descrip').each(function(){
        $clamp(document.getElementById($(this).prop('id')), {clamp: 2});
    });

    // Date/time pickers
    datepicker_init();

    //when any of the filters is called upon change datatable data
    $('#deleted_alerts').change( function() {
        alertsDataTable.draw();
    } );

    $('#showCC').click(function(){
        $(this).removeClass('d-block').addClass('d-none');
        $('tr[field="email-cc"], tr[field="email-bcc"]').show();
        if ($('[name=external-modules-configure-modal] input[name="alert-type"]:checked').val() == 'EMAIL' || !$('[name=external-modules-configure-modal] input[name="alert-type"]:checked').length) {
            $('tr[field="email-failed"]').show();
        }
        enableMultiSelect2();
    });

    $('#showAttachments').click(function(){
        showAttachmentFields(true);
        return false;
    });

    $('input[name="cron-repeat-for"], input[name="cron-repeat-for-max"]').blur(function(){
        $('input[name="alert-send-how-many"][value="schedule"]').prop('checked', true);
    });

    $(':input[name="cron-repeat-for-units"]').on('click change', function(){
        $('input[name="alert-send-how-many"][value="schedule"]').prop('checked', true);
    });

    $('input[name="cron-send-email-on"]').change(function(e){
        changedCronSendEmailOn = true;
    });

    $('input[name="prevent-piping-identifiers"]').click(function(e){
        if (!$(this).prop('checked')) {
            setTimeout(function(){
                $('input[name="prevent-piping-identifiers"]').prop('checked',true);
            },100);
            simpleDialog(null,null,'prevent-piping-dialog',500,null,lang.alerts_48,function(){
                $('input[name="prevent-piping-identifiers"]').prop('checked',false);
            },lang.alerts_47);
        }
    });

    $('input[name="cron-send-email-on-date"]').change(function(e){
        changedCronSendEmailOn = true;
        if ($(this).val() != '') {
            $('input[name="cron-send-email-on"][value="date"]').prop('checked', true);
        }
    });

    $(':input[name="cron-send-email-on-field"], :input[name="cron-send-email-on-time-lag-days"], :input[name="cron-send-email-on-time-lag-hours"], :input[name="cron-send-email-on-time-lag-minutes"]').change(function(e){
        changedCronSendEmailOn = true;
        if ($(this).val() != '') {
            $('input[name="cron-send-email-on"][value="time_lag"]').prop('checked', true);
        }
    });

    // Check to/cc/bcc freeform email fields
    $('input[name="email-to-freeform"], input[name="email-cc-freeform"], input[name="email-bcc-freeform"]').blur(function(){
        var val = $(this).val().toLowerCase().replace(/\s/g,'').replace(/,/g,';');
        $(this).val(val);
        if (val == '') return;
        var emails = val.split(';');
        var invalid = new Array();
        var invalid_domain = new Array();
        var k = 0, j = 0;
        for (var i=0; i < emails.length; i++) {
            var email = emails[i];
            // Ignore [survey-participant-email]
            if (email == '[survey-participant-email]') continue;
            // Check the email address
            if (isEmail(email)) {
                // If we're using email domain allowlist, then validate
                if (alerts_email_freeform_domain_allowlist.length > 0 && !super_user) {
                    var thisEmailParts = email.split('@');
                    var thisEmailDomain = thisEmailParts[1];
                    if (!in_array(thisEmailDomain, alerts_email_freeform_domain_allowlist)) {
                        invalid_domain[j++] = email;
                    }
                }
            } else {
                // Not an email
                invalid[k++] = email;
            }
        }
        var thisInputName = $(this).prop('name');
        $(this).val(val.replace(/;/g,'; '));
        if (invalid.length > 0) {
            simpleDialog(lang.alerts_49+' <b>'+escapeHtml(invalid.join("</b>, <b>"))+'</b>'+lang.period+' '+lang.alerts_50,
                lang.global_01,null,null,'try{$("input[name='+thisInputName+']").focus()}catch(e){}');
        } else if (invalid_domain.length > 0) {
            simpleDialog(lang.alerts_51
                +'<br><br>'+lang.alerts_52+'<br><b>'+alerts_email_freeform_domain_allowlist.join('<br>')+'</b>'
                +'<br><br>'+lang.alerts_53+'<br><b>'+invalid_domain.join('<br>')+'</b>'
                ,lang.alerts_54,null,550,'try{$("input[name='+thisInputName+']").focus()}catch(e){}');
        }
    });

    // Run this after all elements in the dialog are visible when main dialog is opened
    $('[name=external-modules-configure-modal]').on('shown.bs.modal', function (e) {
        showAttachmentFields(false);
    });

    // Set datetime pickers
    $('.filter_datetime_mdy').datetimepicker({
        yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
        hour: currentTime('h'), minute: currentTime('m'), buttonText: lang.alerts_42,
        timeFormat: 'HH:mm', constrainInput: true
    });

    // Add fade mouseover for "delete scheduled invitation" icons
    $(".inviteLogDelIcon").mouseenter(function() {
        $(this).removeClass('opacity50');
    }).mouseleave(function() {
        $(this).addClass('opacity50');
    });

    $('select[name="form-name"], [name="alert-send-how-many"]').change(function(){
        multipageSurveyWarningCheckDo();
    });

    getSendgridData(true, true);
});

// Move Alert
function moveEmailAlert(alert_id) {
    // Get dialog content via ajax
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:moveAlert',{ alert_id: JSON.stringify(alert_id), action: 'view' },function(data){
        var json_data = jQuery.parseJSON(data);
        if (json_data.length < 1) {
            alert(woops);
            return false;
        }
        // Add dialog content and set dialog title
        $('#move_alert_popup').html(json_data.payload);
        // Open the "move alert" dialog
        $('#move_alert_popup').dialog({ title: json_data.title, bgiframe: true, modal: true, width: 700, open: function(){fitDialog(this)},
            buttons: [
                { text: lang.global_53, click: function () { $(this).dialog('close'); } },
                { text: lang.alerts_267, click: function () {
                        // Make sure we have a field first
                        if ($('#move_after_alert').val() == '') {
                            simpleDialog(pleaseSelectAlert);
                            return;
                        }
                        // Save new position via ajax
                        showProgress(1);
                        $.post(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:moveAlert',{ alert_id: JSON.stringify(alert_id), action: 'save', move_after_alert: $('#move_after_alert').val() },function(data){
                            $('#move_alert_popup').dialog("close");
                            var newUrl = window.location.href;
                            if (newUrl.substring(newUrl.length-1) == "#")
                            {
                                newUrl = newUrl.substring(0, newUrl.length-1);
                            }
                            window.location.href = newUrl;
                        });
                    }
                }
            ]
        });
    });
}

// Check file upload extension
function checkFileUploadExt() {
    var fileName = trim($('#alertsFile').val());
    if (fileName.length < 1) {
        alert(lang.design_128);
        return false;
    }
    var file_ext = getfileextension(fileName.toLowerCase());
    if (file_ext != 'csv') {
        $('#filetype_mismatch_div').dialog({ bgiframe: true, modal: true, width: 530, buttons: { Close: function() { $(this).dialog('close'); } }});
        return false;
    }
    return true;
}

function scrollToAlert(alertId, emailDeleted) {
    if (emailDeleted == '1') {
        $('#deleted_alerts').trigger('click');
    }
    $("html, body").animate({ scrollTop: ($('tr#alert_'+alertId).offset().top - 20) }, "normal");
    $('#customizedAlertsPreview tr#alert_'+alertId+' td:visible').effect('highlight',{},4000);
}