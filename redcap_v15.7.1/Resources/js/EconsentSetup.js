$(function(){
    loadSetupTable();
});

// Load the files table
var eConsentTable;
function loadSetupTable()
{
    var ajaxUrl = app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:loadTable';
    var currentURL = window.location.href;
    currentURL = removeParameterFromURL(currentURL, 'display_inactive');
    var display_inactive = getParameterByName('display_inactive');
    if (display_inactive == '1') {
        currentURL += "&display_inactive="+display_inactive;
        ajaxUrl += "&display_inactive="+display_inactive;
    }
    // Define columns
    var columns = [
        {data: "0", title: lang.econsent_37, className: 'dt-body-center dt-head-center wrap', width: '10%'},
        {data: "1", title: lang.econsent_43, className: 'dt-body-center dt-head-center wrap'},
        {data: "2", title: lang.survey_437, className: "lineheight10", width: '45%'},
        {data: "3", title: lang.econsent_15, className: "lineheight10", width: '25%'},
        {data: "4", title: lang.econsent_10, className: "lineheight10"},
        {data: "5", title: lang.calendar_popup_11, className: "lineheight10"}
    ];
    // DataTable
    if (eConsentTable == null) {
        try {
            eConsentTable.destroy();
            eConsentTable = null;
        } catch (e) {
            eConsentTable = null;
        }
        $('#econsent-table').html('');
        eConsentTable = $('#econsent-table').DataTable({
            processing: true,
            pageLength: 25,
            dom: 'frtip',
            drawCallback: function (settings) {
                $('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
            },
            ajax: {url: ajaxUrl, type: 'POST'},
            columns: columns,
            aaSorting: [],
            fixedHeader: {header: true, footer: false},
            oLanguage: {"sSearch": ""},
            language: {"emptyTable": '<div class="my-3 text-secondary fs14"><i class="fa-solid fa-circle-info"></i> ' + lang.econsent_06 + '</div>'}
        });
        // Customize search box
        $('.dataTables_filter input[type=search]').attr('placeholder',lang.control_center_439).addClass('mr-2');
        $('#econsent-table_filter').after('<div class="float-right mr-5"><button class="nowrap btn btn-rcgreen btn-xs fs13" onclick="preSetupChooseSurvey()"><i class="fa-solid fa-plus"></i> '+lang.econsent_44+'</button></div>');
        var showActiveSwitch = '<div class="float-right form-check form-switch mr-5 mt-1 nowrap">' +
          '                        <input type="checkbox" class="form-check-input" role="switch" id="show-active" onclick="reloadConsentTable();" '+(getParameterByName('display_inactive')=='1' ? '' : 'checked')+'>' +
          '                        <label class="form-check-label" for="show-active">'+lang.econsent_08+'</label>' +
          '                    </div>';
        $('#econsent-table_filter').after(showActiveSwitch);
        $('#econsent-table_filter').after('<div class="float-left fs18 text-dangerrc font-weight-bold ml-2 mb-4">'+lang.econsent_39+'</div>');
    } else {
        eConsentTable.ajax.url(ajaxUrl).load();
    }
}

function hideInactiveVersions()
{
    return ($('#show-active:checked').length > 0);
}

// Refresh the table
function reloadConsentTable()
{
    var currentURL = window.location.href;
    if (!hideInactiveVersions()) currentURL += '&display_inactive=1';
    eConsentTable.ajax.url(currentURL.replace('EconsentController:index','EconsentController:loadTable')).load(null, false);
}

// Display dialog for user to choose a survey that does not have e-Consent enabled yet
function preSetupChooseSurvey()
{
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:surveySelectDialog',{ },function(data){
        simpleDialog(data,lang.econsent_47,'editSetupPre', 600);
    });
}

// View setup dialog
function openSetupDialog(consent_id, survey_id, readonly)
{
    if (typeof readonly == 'undefined') readonly = false;
    if (typeof consent_id == 'undefined') consent_id = '';
    if (typeof survey_id == 'undefined') survey_id = '';
    showProgress(1);
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:editSetup',{ consent_id: consent_id, survey_id: survey_id },function(data){
        showProgress(0,0);
        if (data == '0') { alert(woops); return; }
        var saveBtnFunc = function() {
            // Save it
            showProgress(1);
            $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:saveSetup', $('#editSetupForm').serializeObject(), function (data) {
                showProgress(0,0);
                if (data == '0') { alert(woops); openSetupDialog(consent_id, survey_id, readonly); return; }
                Swal.fire({title: lang.control_center_4879, html: data, icon: 'success', timer: 3000});
                reloadConsentTable();
            });
        };
        simpleDialog(data,(consent_id==null ? lang.econsent_50 : lang.econsent_46),'editSetup',900, null, (readonly ? null : lang.global_53), (readonly ? null : saveBtnFunc), (readonly ? null : lang.econsent_60));
        // Show/hide e-Consent options
        showHideEconsentSigFields();
        $('#select-more-sigs button').click(function(){
            showNewEconsentSigField();
        });
        fitDialog($('#editSetup'));
        // Set all fields as readonly?
        if (readonly) {
            $("#editSetup :input").prop("disabled", true);
            $("#editSetup #select-more-sigs").remove();
            setTimeout('$("#editSetup .tox-editor-header").remove();',100);
            setTimeout('$("#editSetup .tox-editor-header").remove();',500);
        }
    });
}

function showHideEconsentSigFields()
{
    var thisField;
    var nameBase = 'signature_field';
    var selectAnotherBtn = $('#select-more-sigs');
    selectAnotherBtn.show();
    for (var i=numEconsentSignatureFields; i>=1; i--) {
        thisField = $('select[name="'+nameBase+i+'"]');
        if (thisField.val() == '' && i > 1) {
            thisField.parent().hide();
        } else {
            if (i == numEconsentSignatureFields) {
                selectAnotherBtn.hide();
            }
            break;
        }
    }
}

function showNewEconsentSigField()
{
    $('.signature_field_div:hidden:first').show().find('select').effect('highlight',{},3000);
    if ($('.signature_field_div:visible').length == numEconsentSignatureFields) {
        $('#select-more-sigs').hide();
    }
}


function initTinyMceEditorsEconsent(readonly) {
    $('.mceEditor').each(function() {
        var editorId = $(this).attr('id');
        if (editorId == null) {
            $(this).attr('id', "editor-"+Math.floor(Math.random()*10000000000000000));
        } else {
            tinymce.EditorManager.execCommand('mceRemoveEditor', true, editorId);
        }
    });
    initTinyMCEglobal('mceEditor', false, readonly);
}

// View and save setup dialog
function openAddConsentFormDialog(consent_id, consent_form_id)
{
    if ($('#add-consent-form-pre').hasClass('ui-dialog-content') && $('#add-consent-form-pre').dialog('isOpen') == true) {
        $('#add-consent-form-pre').dialog('close');
    }
    // Get dialog content
    showProgress(1);
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:addConsentForm',{ consent_id: consent_id, consent_form_id: (consent_form_id == null ? '' : consent_form_id) },function(data){
        showProgress(0,0);
        // Display full dialog
        initDialog('add-consent-form', data)
        $('#add-consent-form').dialog({ bgiframe: true, modal: true, width: 900, title: (consent_form_id == null ? lang.econsent_45 : lang.econsent_86), autoOpen: true,
            buttons: [
                { text: lang.global_53, click: function() {
                    $(this).dialog('destroy'); $(this).remove();
                }},
                { text: lang.econsent_70, click: function() {
                    // Error checking
                    $('#version').val( $('#version').val().trim() );
                    if ($('#version').val() == '') {
                        simpleDialog(lang.econsent_83); return;
                    }
                    if ($('#version').val() != '' && $('#version').attr('valorig') != '' && $('#version').val() == $('#version').attr('valorig')) {
                        simpleDialog(lang.econsent_85); return;
                    }
                    if ($('#consent_form_location_field').val() == '') {
                        simpleDialog(lang.econsent_84); return;
                    }
                    if ($('#consent_form_pdf_doc_id').val().length > 0 && $('#consent_form_richtext').val().length > 0) {
                        simpleDialog(lang.econsent_92); return;
                    }
                    if ($('#consent_form_pdf_doc_id').val().length == 0 && $('#consent_form_pdf_doc_id_num').val().length == 0 && $('#consent_form_richtext').val().length == 0) {
                        simpleDialog(lang.econsent_93); return;
                    }
                    // Clicked save button
                    $('#add-consent-form :input:disabled').prop('disabled', false); // Reenable disabled fields before posting, otherwise will not get picked up
                    // Save consent form info
                    saveConsentForm(consent_id, (consent_form_id == null));
                }}
            ]
        });
        initTinyMceEditorsEconsent();
        setTimeout("fitDialog($('#add-consent-form'));",50);
        setTimeout("fitDialog($('#add-consent-form'));",500);
    });
}

// View and save setup dialog
function openViewConsentFormVersionsDialog(consent_id, survey_id)
{
    showProgress(1);
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:viewConsentFormVersions',{ consent_id: consent_id, survey_id: survey_id },function(data){
        showProgress(0,0);
        simpleDialog(data,lang.econsent_133,'view-consent-form-versions',1200);
        fitDialog($('#view-consent-form-versions'));
    });
}

function saveConsentForm(consent_id, addingNew)
{
    var params = $('#addConsentForm').serializeObject();
    var dagOrLangSelected = (params.consent_form_filter_dag_id != '' || params.consent_form_filter_lang_id != '');
    showProgress(1);
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:saveConsentForm',params,function(data) {
        showProgress(0, 0);
        var consent_form_id = data;
        if (data == '0' || data == '') {
            // Error
            showProgress(0,0);
            alert(woops);
        } else if (data == '-2') {
            // Error: Version number already exists
            Swal.fire({html: (dagOrLangSelected ? lang.econsent_177 : lang.econsent_176), icon: 'error', timer: 7000});
        } else if (data == '-1') {
            // Error
            Swal.fire({html: lang.econsent_78, icon: 'error', timer: 5000});
        } else {
            // Success
            if ($('#consent_form_pdf_doc_id').val().length > 0) {
                // Upload PDF, if provided, and then save consent form info
                uploadConsentFormPdf(consent_id, consent_form_id);
            } else {
                Swal.fire({html: (addingNew ? lang.econsent_79 : lang.econsent_94), icon: 'success', timer: 3000});
                closeAddConsentFormDialog();
                reloadConsentTable();
            }
        }
    });
}

function closeAddConsentFormDialog()
{
    $('#add-consent-form').dialog('destroy');
    $('#add-consent-form').remove();
}

// Upload single PDF via AJAX
function uploadConsentFormPdf(consent_id, consent_form_id, addingNew)
{
    var file = $('#consent_form_pdf_doc_id')[0].files[0];
    if (file.name.toLowerCase().indexOf('.pdf') < 0) {
        Swal.fire({html: lang.econsent_82, icon: 'error', timer: 5000});
        // Delete the consent form placeholder since the consent form could not be uploaded
        $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:deleteConsentForm', { consent_id: consent_id, consent_form_id: consent_form_id });
        return;
    }
    // Add file to formdata
    var fd = new FormData();
    fd.append('file', file);
    fd.append('redcap_csrf_token', redcap_csrf_token);
    // Upload the file via AJAX
    $.ajax({
        url: app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:uploadConsentForm&consent_id='+consent_id+'&consent_form_id='+consent_form_id,
        type: 'post',
        data: fd,
        contentType: false,
        processData: false,
        async: false,
        success: function (response) {
            if (response == '0') {
                // Error
                alert(woops);
            } else if (isinteger(response)) {
                // Success: Set value
                $('#consent_form_pdf_doc_id_num').val(response);
                $('#consent_form_richtext').val('');
                // Close all dialogs and refresh table
                Swal.fire({html: (addingNew ? lang.econsent_79 : lang.econsent_94), icon: 'success', timer: 3000});
                closeAddConsentFormDialog();
                reloadConsentTable();
            } else {
                // Specific error returned
                simpleDialog(response);
            }
        },
        error: function (response) {
            // Error
            alert(woops);
        }
    });
}

// Disable/re-enable e-Consent for a survey
function toggleEnableEconsent(ob, consent_id, survey_id)
{
    var enableIt = $(ob).prop('checked');
    if (enableIt) {
        reenableEconsent(consent_id, survey_id)
    } else {
        disableEconsentConfirm(consent_id, survey_id);
    }
}

// Disable e-Consent for a survey
function disableEconsent(consent_id, survey_id)
{
    showProgress(1);
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:disable',{ consent_id: consent_id, survey_id: survey_id },function(data) {
        showProgress(0, 0);
        if (data != '0' && data != '') {
            Swal.fire({html: data, icon: 'success', timer: 3000});
            reloadConsentTable();
        } else {
            alert(woops);
        }
    });
}
function disableEconsentConfirm(consent_id, survey_id)
{
    simpleDialog(lang.econsent_100, lang.econsent_96, 'disable-econsent-confirm', 500, function(){
        reloadConsentTable();
    }, lang.global_53, function(){
        disableEconsent(consent_id, survey_id);
    }, lang.econsent_96);
}

// Re-enable e-Consent for a survey
function reenableEconsent(consent_id, survey_id)
{
    showProgress(1);
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:reenable',{ consent_id: consent_id, survey_id: survey_id },function(data) {
        showProgress(0, 0);
        if (data != '0' && data != '') {
            Swal.fire({html: data, icon: 'success', timer: 3000});
            reloadConsentTable();
        } else {
            alert(woops);
        }
    });
}

// Remove a consent form
function removeConsentForm(consent_id, consent_form_id, survey_id)
{
    showProgress(1);
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:removeConsentForm',{ consent_id: consent_id, consent_form_id: consent_form_id },function(data) {
        showProgress(0, 0);
        if (data != '0' && data != '') {
            reloadConsentTable();
            Swal.fire({html: data, icon: 'success', timer: 3000});
            $('#add-consent-form').dialog('close');
            setTimeout(function(){
                openViewConsentFormVersionsDialog(consent_id, survey_id);
            },100);
        } else {
            alert(woops);
        }
    });
}

// Display the consent form inline in a dialog
function viewConsentForm(consent_id, consent_form_id)
{
    var html = renderInlinePdfContainer(app_path_webroot + 'index.php?pid=' + pid + '&route=EconsentController:viewConsentForm&consent_id='+consent_id+'&consent_form_id='+consent_form_id);
    var consentFormViewDialogId = 'consent-form-id'+consent_form_id;
    simpleDialog(html,lang.econsent_136,consentFormViewDialogId,800);
    var height = min($(window).height()-200, 800);
    $('#'+consentFormViewDialogId+' .inline-pdf-viewer').height(height);
    initInlinePdfs();
    setTimeout(function(){ fitDialog($('#'+consentFormViewDialogId)); },500);
    setTimeout(function(){ fitDialog($('#'+consentFormViewDialogId)); },1500);
    setTimeout(function(){ fitDialog($('#'+consentFormViewDialogId)); },2500);
}