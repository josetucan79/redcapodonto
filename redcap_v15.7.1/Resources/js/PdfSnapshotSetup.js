$(function(){
    loadSetupTable();
});

// Load the files table
var pdfSnapshotTable;
function loadSetupTable()
{
    var ajaxUrl = app_path_webroot + 'index.php?pid=' + pid + '&route=PdfSnapshotController:loadTable';
    var currentURL = window.location.href;
    currentURL = removeParameterFromURL(currentURL, 'display_inactive');
    var display_inactive = getParameterByName('display_inactive');
    if (display_inactive == '1') {
        currentURL += "&display_inactive="+display_inactive;
        ajaxUrl += "&display_inactive="+display_inactive;
    }

    // Define columns
    var columns = [
        {data: "0", title: lang.econsent_02, className: 'dt-body-center dt-head-center wrap'},
        {data: "1", title: lang.econsent_43, className: 'dt-body-center dt-head-center wrap'},
        {data: "2", title: lang.docs_77},
        {data: "3", title: lang.econsent_42, className: 'dt-body-center dt-head-center'},
        {data: "4", title: lang.econsent_09, className: "lineheight10"},
        {data: "5", title: lang.econsent_142, className: "lineheight10"},
        {data: "6", title: lang.econsent_16, className: "lineheight10"},
        {data: "7", title: lang.econsent_150, className: "dt-body-center dt-head-center font-weight-normal fs12"}
    ];
    // DataTable
    if (pdfSnapshotTable == null) {
        try {
            pdfSnapshotTable.destroy();
            pdfSnapshotTable = null;
        } catch (e) {
            pdfSnapshotTable = null;
        }
        $('#record-snapshot-table').html('');
        pdfSnapshotTable = $('#record-snapshot-table').DataTable({
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
            language: {"emptyTable": '<div class="my-3 text-secondary fs14"><i class="fa-solid fa-circle-info"></i> ' + lang.econsent_71 + '</div>'}
        });
        // Customize search box
        $('.dataTables_filter input[type=search]').attr('placeholder',lang.control_center_439).addClass('mr-2');
        $('#record-snapshot-table_filter').after('<div class="float-right mr-5"><button class="nowrap btn btn-rcgreen btn-xs fs13" onclick="openSetupDialog();"><i class="fa-solid fa-plus"></i> '+lang.econsent_32+'</button></div>');
        var showActiveSwitch = '<div class="float-right form-check form-switch mr-5 mt-1 nowrap">' +
            '                        <input type="checkbox" class="form-check-input" role="switch" id="show-active" onclick="hideInactiveVersionsAction();" '+(getParameterByName('display_inactive')=='1' ? '' : 'checked')+'>' +
            '                        <label class="form-check-label" for="show-active">'+lang.econsent_08+'</label>' +
            '                    </div>';
        $('#record-snapshot-table_filter').after(showActiveSwitch);
         $('#record-snapshot-table_filter').after('<div class="float-left fs18 text-dangerrc font-weight-bold ml-2 mb-4">'+lang.econsent_38+'</div>');
    } else {
        pdfSnapshotTable.ajax.url(ajaxUrl).load();
    }
}

// When clicking the "hide inactive versions" switch
function hideInactiveVersionsAction()
{
    // currentURL = removeParameterFromURL(window.location.href, 'display_inactive');
    // if (!hideInactiveVersions()) currentURL += '&display_inactive=1';
    // modifyURL(currentURL);
    reloadConsentTable();
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
    pdfSnapshotTable.ajax.url(currentURL.replace('PdfSnapshotController:index','PdfSnapshotController:loadTable')).load(null, false);
}

// View setup dialog
function openSetupDialog(snapshot_id)
{
    showProgress(1);
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=PdfSnapshotController:editSetup',{ snapshot_id: snapshot_id },function(data){
        showProgress(0,0);
        initDialog('editSetup', data);
        $('#editSetup').dialog({ bgiframe: true, modal: true, width: 800, title: lang.econsent_99, autoOpen: true, open: function(){fitDialog(this)},
            buttons: [
                { text: lang.global_53, click: function() {
                        $(this).dialog('destroy'); $(this).remove();
                    }},
                { text: lang.control_center_4878, click: function() {
                    // Checks
                    if ($('#trigger_surveycomplete_survey_id').val() == '' && $('#pdfsnapshotlogic').val() == '') {
                        simpleDialog(lang.econsent_109);
                        return;
                    }
                    if (!$('#pdf_save_to_file_repository').prop('checked') && $('select[name=pdf_save_to_field]').val() == '') {
                        simpleDialog(lang.econsent_108);
                        return;
                    }
                    $('#pdfsnapshotlogic').val( trim($('#pdfsnapshotlogic').val()) );
                    if ($('#pdfsnapshotlogic').val() != '' && $('#trigger_surveycomplete_survey_id').val() != '') {
                        simpleDialog(lang.econsent_178);
                        return;
                    }
                    // Submit
                    $('#editSetup :input:disabled').prop('disabled', false);
                    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=PdfSnapshotController:saveSetup&snapshot_id='+snapshot_id,$('#addEditSnapshot').serializeObject(),function(data){
                        if (data == '0' || data == '') {
                            alert(woops);
                        } else {
                            $('#editSetup').dialog('destroy'); $('#editSetup').remove();
                            Swal.fire({title: lang.control_center_4879, html: data, icon: 'success', timer: 3000});
                            reloadConsentTable();
                        }
                    });
                }}
            ]
        });
        $('.snapshot-survey-list-dropdown').select2();
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

function openExcludeFormsEvents() {
    if ($('#choose_select_forms_events_div').css('display') == 'none') {
        // Parse the existing value to check all the correct checkboxes
        var selected_forms_events = trim($('#selected_forms_events').val());
        if (selected_forms_events == '') {
            // Select all
            selectAllFormsEvents(true);
        } else {
            // Deselect all first
            selectAllFormsEvents(false);
            // Select those from the input val
            var event_forms = selected_forms_events.split(',');
            for (var i = 0; i < event_forms.length; i++) {
                // Trim the excess whitespace.
                var event_form = event_forms[i].trim().split('][');
                // Add additional code here, such as:
                if (typeof event_form[1] == "undefined") {
                    var event_name = '';
                    var form = event_form[0].replace(']','').replace('[','');
                } else {
                    var event_name = event_form[0].replace(']','').replace('[','');
                    var form = event_form[1].replace(']','').replace('[','');
                }
                $('#choose_select_forms_events_div_sub input#ef-'+event_name+'-'+form).prop('checked',true);
            }

        }
    }
    $('#choose_select_forms_events_div').toggle('fade','fast',function(){
        var d = $('#editSetup');
        d.scrollTop(d.prop("scrollHeight"));
    });
}

function excludeEventsUpdate(update) {
    if (update == '1') {
        var selected_forms_events = new Array();
        var i=0;
        $('#choose_select_forms_events_div_sub input[type="checkbox"].efchk:checked').each(function(){
            var ef = $(this).prop('id').split('-');
            var event_name = (longitudinal && ef[1] != '') ? '['+ef[1]+']' : '';
            var form = '['+ef[2]+']';
            selected_forms_events[i++] = event_name+form;
        });
        $('#selected_forms_events').val(selected_forms_events.join(','));
    }
    $('#choose_select_forms_events_div').hide();
}

function selectAllFormsEvents(select_all) {
    $('#choose_select_forms_events_div_sub input[type="checkbox"]').prop('checked',select_all);
}

function selectAllInEvent(event_name,ob) {
    $('#choose_select_forms_events_div_sub input[id^="ef-'+event_name+'-"]').prop('checked',$(ob).prop('checked'));
}

// Disable/re-enable Snapshot Trigger
function toggleEnableSnapshot(ob, snapshot_id)
{
    var enableIt = $(ob).prop('checked');
    if (enableIt) {
        reenableSnapshot(snapshot_id)
    } else {
        disableSnapshotConfirm(snapshot_id);
    }
}

// Disable Snapshot Trigger
function disableSnapshot(snapshot_id)
{
    showProgress(1);
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=PdfSnapshotController:disable',{ snapshot_id: snapshot_id },function(data) {
        showProgress(0, 0);
        if (data != '0' && data != '') {
            Swal.fire({html: data, icon: 'success', timer: 3000});
            reloadConsentTable();
        } else {
            alert(woops);
        }
    });
}
function disableSnapshotConfirm(snapshot_id)
{
    simpleDialog(lang.econsent_129, lang.econsent_96, 'disable-snapshot-confirm', 500, function(){
        reloadConsentTable();
    }, lang.global_53, function(){
        disableSnapshot(snapshot_id);
    }, lang.econsent_96);
}

// Re-enable Snapshot Trigger
function reenableSnapshot(snapshot_id)
{
    showProgress(1);
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=PdfSnapshotController:reenable',{ snapshot_id: snapshot_id },function(data) {
        showProgress(0, 0);
        if (data != '0' && data != '') {
            Swal.fire({html: data, icon: 'success', timer: 3000});
            reloadConsentTable();
        } else {
            alert(woops);
        }
    });
}

// Copy Snapshot Trigger
function copySnapshot(snapshot_id)
{
    showProgress(1);
    $.post(app_path_webroot + 'index.php?pid=' + pid + '&route=PdfSnapshotController:copy',{ snapshot_id: snapshot_id },function(data) {
        showProgress(0, 0);
        if (data != '0' && data != '') {
            Swal.fire({html: data, icon: 'success', timer: 3000});
            reloadConsentTable();
        } else {
            alert(woops);
        }
    });
}
function copySnapshotConfirm(snapshot_id)
{
    simpleDialog(lang.econsent_170, lang.econsent_169, 'disable-snapshot-confirm', 500, function(){
        reloadConsentTable();
    }, lang.global_53, function(){
        copySnapshot(snapshot_id);
    }, lang.econsent_169);
}