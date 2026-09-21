var bgDeletionTable;
$(function() {
var urlParams = new URLSearchParams(window.location.search); //get all parameters
if(urlParams.get('deletion_id') !== undefined && urlParams.get('deletion_id') !== null) {
    bgDeletionTable = $('#background-deletion-table').DataTable({
        processing: true,
        pageLength: 25,
        lengthMenu: [
            [10, 25, 50, 100, 500, -1],
            [10, 25, 50, 100, 500, 'All'],
        ],
        ajax: {url: app_path_webroot+'index.php?route=BulkRecordDeleteController:loadBackgroundDeletionsTable&pid='+pid, type: 'POST'},
        columns: [
            {data: "status", title: '<i class="fa-regular fa-square-check"></i>&nbsp;'+lang.data_import_tool_346, className: 'lh-1 dt-body-center dt-head-center'},
            {data: { _: "request_time.display", sort: "request_time.sort" }, title: '<i class="fa-regular fa-clock"></i>&nbsp;'+lang.data_entry_712, className: 'nowrap'},
            {data: { _: "completed_time.display", sort: "completed_time.sort" }, title: '<i class="fa-regular fa-clock"></i>&nbsp;'+lang.data_import_tool_348, className: 'nowrap'},
            {data: "username", title: '<div class="nowrap"><i class="fa-regular fa-user"></i>&nbsp;'+lang.data_import_tool_350+'</div>', className: 'lh-1'},
            {data: { _: "records_provided.display", sort: "records_provided.sort" }, type: "num", title: lang.data_import_tool_351, className: 'lh-1 dt-body-center dt-head-center'},
            {data: { _: "records_deleted.display", sort: "records_deleted.sort" }, type: "num", title: lang.data_entry_718, className: 'lh-1 dt-body-center dt-head-center'},
            {data: { _: "total_processing_time.display", sort: "total_processing_time.sort" }, type: "num", title: lang.data_entry_719, className: 'dt-body-center dt-head-center lh-1'},
            {data: { _: "total_errors.display", sort: "total_errors.sort" }, type: "num", title: '<div class="nowrap"><i class="fa-solid fa-circle-exclamation"></i>&nbsp;'+lang.data_import_tool_354+'</div>', className: 'dt-body-center dt-head-center'}
        ],
        aaSorting: [],
        fixedHeader: {header: true, footer: false},
        oLanguage: {"sSearch": ""},
        language: {"emptyTable": '<div class="my-3 text-secondary fs14"><i class="fa-regular fa-folder-open"></i> No deletions</div>'}
    });
    $('.dataTables_filter input[type=search]').attr('placeholder',lang.email_users_112).after('<button onclick="reloadBgDeletionTable();" class="btn btn-xs btn-light fs13 ms-3" style="position:relative;top:-2px;"><i class="fa-solid fa-rotate-right"></i> '+lang.data_import_tool_374+'</button>');
    if (getParameterByName('async_success') != '') {
        simpleDialog(null, null, 'async_success_dialog', 600);
        modifyURL(removeParameterFromURL(window.location.href, 'async_success'));
    }
    var newUrl = removeParameterFromURL(window.location.href, 'deletion_id')+'&deletion_id=';
    modifyURL(newUrl);
    $('#view-bg-deletion-tab').parent().attr('href', newUrl);
    $("li.active").removeClass('active');
    $('#view-bg-deletion-tab').parent().parent().addClass("active");
} else {
    'use strict';
    var arm_id;
    var recordExists;
    var prevPageButton;
    var nextPageButton;
    var currentPage;
    var selection;
    var recordListItems;
    var group_id = $('input[name="group_id"]').val();
    var record_list_mode = document.getElementById('radio-record-list').checked;
    var btn_delete_selection = $('#btn-delete-selection');
    var txtarea_custom_list = $('.list-input-step');
    var formEventListWrapperOption1 = document.querySelector('.form-event-list-wrapper-option1');
    var formEventListWrapperOption2 = document.querySelector('.form-event-list-wrapper-option2');
    var longitudinalArmsListOption2 = document.getElementById('longitudinal-arms-list-option2');
    var triangle = document.getElementById('triangle');
    var searchBox = document.getElementById('searchBox');
    var recordList = document.getElementById('record-output');
    var countRecordsScheduledForDeletion = 0;
    $('#radio-record-list, #radio-custom-list').on('click', function() {
        if (this.id === 'radio-record-list') {
            $('#radio-custom-list').prop('checked', false);
            // Get the currently selected arm
            if ($('#toggle-delete-entire-record').is(':checked')) {
                arm_id = document.querySelectorAll('.arm-select')[0]?.value;
            } else {
                arm_id = document.querySelectorAll('.arm-select')[1]?.value;
            }
            // Make sure records displayed in the list correspond to the selected arm
            fetchRecords(0);
        } else if (this.id === 'radio-custom-list') {
            $('#radio-record-list').prop('checked', false);
        }
        showProgress(1);
        let url = $(this).val();
        if (!$('#toggle-delete-entire-record').is(':checked')) {
            url += '&toggle-delete-forms-record=1';
        }
        let formData = $('input[name="form_event[]"]:checked').serializeArray();
        $('.arm-select').each(function () {
            formData.push({
                name: $(this).attr('name'),
                value: $(this).val()
            });
        });
        // add CSRF token to formData
        formData.push({ name: 'redcap_csrf_token', value: redcap_csrf_token });
        // add identifier for this post request
        formData.push({ name: 'form_event_ajax', value: true });

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            success: function(response) {
                window.location.href = url;
            },
            error: function(xhr, status, error) {
                console.error("Error occurred: " + error);
            }
        });
    });
    
    if (recordList) {
        recordListItems = Array.from(recordList.getElementsByTagName('li'));
    }
    var app = {
        registerListenerRecordList: function () {
            document.getElementById('record-output').addEventListener('click', function(event) {
                if (event.target.name === 'records[]') {
                    app.setSelection();
                }
            });
        },
        setSelection: function () {
            var mode = $('input[name="mode"]').val();
            if(mode === 'record-list') {
                selection = $('input[name="records[]"]:checked').map(function(){
                    return $(this).val();
                }).get();
                updateCountRecordsScheduledForDeletion(selection);
            } else {
                selection = $('input[name="records[]"]').map(function(){
                    return $(this).val();
                }).get();
            }
            if( selection.length > 0 ) {
                btn_delete_selection.prop("disabled", false);
            } else {
                btn_delete_selection.prop("disabled", true);
            }

        },
        setView(mode) {
            const spinnerContainer = $('#spinner-container');
            switch (mode) {
                case 'fetching':
                    spinnerContainer.show();
                    $('#prevPage').prop('disabled', true);
                    $('#nextPage').prop('disabled', true);
                    break;
                case 'fetched':
                    spinnerContainer.hide();
                    $('#prevPage').prop('disabled', false);
                    $('#nextPage').prop('disabled', false);
                    break;
                default:
                    alert("Unrecognized mode for setView function");
                    break;
            }
        }

    };

    btn_delete_selection.on('mouseenter click', handleRecordsListInput);
    txtarea_custom_list.on('change keyup paste mouseout', handleRecordsListInput);
    function handleRecordsListInput(event) {
        if (record_list_mode) {
            return;
        }
        var content = txtarea_custom_list.val();
        var list = [];
        $.each(content?.split(/\n|,/), function(index, item) {
            var trimmedItem = $.trim(item);
            if (trimmedItem) {
                list.push(trimmedItem);
            }
        });
        updateCountRecordsScheduledForDeletion(list);
        if (event.type !== 'keyup' || (event.type === 'keyup' && event.which === 13)) {
            if (list.length > 0) {
                // Get correct arm_id to validate records in input list
                if ($('#toggle-delete-entire-record').is(':checked')) {
                    arm_id = document.querySelectorAll('.arm-select')[0]?.value;
                } else {
                    arm_id = document.querySelectorAll('.arm-select')[1]?.value;
                }
                checkRecordsExist(list, group_id, arm_id)
                    .then(result => {
                        recordExists = Object.values(result).length === 0;
                        if (!recordExists) {
                            $('.list-input-step').removeClass('is-valid').addClass('is-invalid');
                            $('#validateHelpBlock').hide();
                            $('#validInputBlock').hide();
                            $('#invalidInputBlock').html(langRMD.message_invalid_records_detected + Object.values(result).join(', ')).show();
                            btn_delete_selection.prop("disabled", true);
                        } else {
                            //  Set to valid
                            $('.list-input-step').removeClass('is-invalid').addClass('is-valid');
                            $('#validateHelpBlock').hide();
                            $('#invalidInputBlock').hide();
                            $('#validInputBlock').show();
                            btn_delete_selection.prop("disabled", false);
                            renderCustomList(list);
                            app.setSelection()
                        }
                    })
                    .catch(error => {
                        alert(error);
                });
            } else {
                //  Back to default
                $('.list-input-step').removeClass('is-valid is-invalid');
                btn_delete_selection.prop("disabled", true);
                $('#validateHelpBlock').show();
                $('#invalidInputBlock').hide();
                $('#validInputBlock').hide();
            }
        }
    }

    if (document.getElementById('radio-record-list').checked) {

        searchBox.addEventListener('focus', function() {
            document.getElementById('infoText').style.visibility = 'visible';
        });
        searchBox.addEventListener('blur', function() {
            document.getElementById('infoText').style.visibility = 'hidden';
        });

        app.registerListenerRecordList();
        prevPageButton = document.getElementById('prevPage');
        nextPageButton = document.getElementById('nextPage');
        currentPage = 0;

        prevPageButton?.addEventListener('click', (event) => {
            event.preventDefault();
            if (currentPage > 0) {
                if (selection && selection.length > 0) {
                    let okBtnJs = function () {
                        currentPage--;
                        resetSelection();
                        fetchRecords(currentPage * fetchLimit);
                    };
                    simpleDialog(langRMD.message_navigation_warning.replace("{0}", langRMD.previous.toLowerCase()), null, null, null, null, lang.global_53, okBtnJs, langRMD.continue)
                } else {
                    currentPage--;
                    fetchRecords(currentPage * fetchLimit);
                }
            }
        });

        nextPageButton?.addEventListener('click', (event) => {
            event.preventDefault();
            if (selection && selection.length > 0) {
                let okBtnJs = function () {
                    currentPage++;
                    resetSelection();
                    fetchRecords(currentPage * fetchLimit);
                    if (prevPageButton != null) prevPageButton.style.display = 'inline-block';
                };
                simpleDialog(langRMD.message_navigation_warning.replace("{0}", langRMD.next.toLowerCase()), null, null, null, null, lang.global_53, okBtnJs, langRMD.continue)
            } else {
                currentPage++;
                fetchRecords(currentPage * fetchLimit);
                if (prevPageButton != null) prevPageButton.style.display = 'inline-block';
            }
        });
    }

    $('.arm-select').change(function(e) {
        arm_id = e.target.value;
        if (record_list_mode) {
            fetchRecords(0);
        }
        let selectedValue = $(this).val();
        let url = app_path_webroot + 'index.php?pid=' + pid + '&route=BulkRecordDeleteController:renderFormEventList';
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            data: {
                arm_number: selectedValue
            },
            success: function(response) {
                if (response && response.form_event_list) {
                    $('#form-event-list-wrapper').html(response.form_event_list);
                    cleanFormEventDisplay();
                } else if (response && response.errors) {
                    simpleDialog(response.errors, lang.alerts_24);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error occurred: " + error);
            }
        });
    });

    function toggleFormEventListDisplay(list, trigger) {
        return function() {
            if (list.style.display === 'none') {
                list.style.display = 'block';
                trigger.innerHTML = '&#9650;';
            } else {
                list.style.display = 'none';
                trigger.innerHTML = '&#9660;';
            }
        };
    }

    if (triangle != null) {
        triangle.onclick = toggleFormEventListDisplay(longitudinalArmsListOption2, triangle);
    }

    $('.sel').click( function() {
        var state = $(this).data('choice') == 'all';
        $('input[name="records[]"]').prop('checked', state);
        app.setSelection();
        return false;
    });

    $('#btn-delete-selection').click( function() {
        if (!selection) {
            simpleDialog('Please select a record to delete');
            return false;
        }
        var num_selected = selection.length;
        var num_selected_forms = $('input[name="form_event[]"]:checked:not(:disabled)').length;
        // var total_forms = $('input[name="form_event[]"]:not(:disabled)').length;
        var partial_delete = $('#toggle-delete-forms-record').prop('checked');

        initDialog("confirmDeletion");
        let container = document.getElementById('confirmDeletion');
        if (partial_delete) {
            let div1 = document.createElement('div');
            div1.className = 'mt-1 mb-3 text-dangerrc fs16';
            div1.innerHTML = '<i class="fa-solid fa-circle-minus"></i> ' +
                langRMD.delete_forms_warning
                    .replace('{0}', num_selected_forms)
                    .replace('{1}', num_selected);
            container.appendChild(div1);
        } else if (!partial_delete) { // display message indicating deletion of entire record(s)
            let div2 = document.createElement('div');
            div2.className = 'text-dangerrc fs16 mb-3';
            div2.innerHTML = '<b class="fs15">' + langRMD.delete_records_warning.replace('{0}', num_selected);
            +'</b>';
            container.appendChild(div2);
        }
        let p1 = document.createElement('p');
        p1.innerHTML = langRMD.delete_message_instructions;
        container.appendChild(p1);
        let p2 = document.createElement('div');
        p2.innerHTML = '<div class="mt-3 font-weight-bold" style="font-family:Verdana;">'+langRMD.confirm_delete_txt + '</div>' +
            '<input type="text" id="delete_records_confirm" class="x-form-text x-form-field" style="border:2px solid red;width:170px;">' +
            '<div class="mt-3 fs14 boldish"><input type="checkbox" id="delete_records_background" style="position:relative;top:1px;"> <label for="delete_records_background">'+langRMD.bg_checkbox+'</label></div>' +
            '<div class="ms-3 text-secondary">'+langRMD.bg_checkbox2+'</div>';
        container.appendChild(p2);

        $('#confirmDeletion')
            .dialog({
                title: langRMD.confirm_deletion,
                bgiframe:true,
                modal:true,
                width:600,
                close: function() { $(this).dialog('destroy');},
                open: function(){ fitDialog(this) },
                buttons: {
                    'Cancel': function() { $(this).dialog('destroy'); },
                    'Delete': function() {
                        // Make sure user enters Reason for Change, if applicable
                        if (partial_delete && $('#change-reason:visible').length && trim($('#change-reason').val()) == '') {
                            simpleDialog(lang.data_entry_70);
                            return;
                        }
                        // if an invalid records list is pasted immediately after a valid one, then `Delete` button may still be active long enough to allow the confirm deletion popup to appear
                        if (!record_list_mode && !recordExists) {
                            simpleDialog(lang.data_entry_652);
                            return;
                        }
                        // confirm deletion by typing the `delete` keyword
                        if (trim($('#delete_records_confirm').val().toLowerCase()) !== "delete") {
                            simpleDialog(lang.data_entry_653);
                            return;
                        }
                        showProgress(1);
                        let input = $("<input>")
                            .attr("type", "hidden")
                            .attr("name", "delete").val("true");
                        if (partial_delete && num_selected_forms === 0 && !$('#toggle-delete-entire-record').checked) {
                            simpleDialog(lang.data_entry_654);
                            showProgress(0);
                            return;
                        }
                        $('form.delete_records').append(input);
                        if (partial_delete && $('#change-reason:visible').length) {
                            $('form.delete_records').append('<input type="hidden" value="'+htmlspecialchars($('#change-reason').val())+'" name="change-reason">');
                        }
                        $('form.delete_records').append('<input type="hidden" value="'+($('#allow_delete_record_from_log').length && $('#allow_delete_record_from_log').prop('checked') ? '1' : '0')+'" name="delete_logging">');
                        $('form.delete_records').append('<input type="hidden" value="'+($('#delete_records_background').length && $('#delete_records_background').prop('checked') ? '1' : '0')+'" name="delete_background">');
                        $(this).dialog('destroy');
                        $('form.delete_records').submit();
                    }
                },
                create:function () {
                    var b = $(this).closest(".ui-dialog")
                        .find(".ui-dialog-buttonset .ui-button:last").addClass("delete_btn");
                }
            });
        // Hide the GDPR logging option (if enabled) if we're doing a partial delete
        $('#allow_delete_record_from_log').prop('checked', false);
        if (partial_delete) {
            $('#allow_delete_record_from_log_parent').hide();
            $('#change_reason_div').show();
        } else {
            $('#allow_delete_record_from_log_parent').show();
            $('#change_reason_div').hide();
        }
        return false;
    });

    function cleanFormEventDisplay() {
        // Take off the buttons and title that we don't want with the checkboxes.
        $("#select_links_forms button").remove();
        $("#select_links_forms a").first().css("margin-left", "5px");
        var title = $("#choose_select_forms_events_div_sub div").first();
        title.css("border-bottom", "0px");
        title.css("padding", "0px");
        title.html("");
    }
    cleanFormEventDisplay();

    let checkbox1 = document.getElementById('toggle-delete-entire-record');
    let checkbox2 = document.getElementById('toggle-delete-forms-record');
    if (checkbox1.checked) {
        formEventListWrapperOption2.style.display = 'none';
        const dropdown = formEventListWrapperOption1.querySelector('#arm-select-option1');
        if (formEventListWrapperOption1.style.display === 'none' && dropdown.options.length > 1) {
            formEventListWrapperOption1.style.display = 'block';
        }
        selectAllFormsEvents(true, true);
        if (record_list_mode) {
            arm_id = document.querySelectorAll('.arm-select')[0]?.value;
            fetchRecords(0);
        }
    }
    if (checkbox2.checked) {
        formEventListWrapperOption1.style.display = 'none';
        if (formEventListWrapperOption2.style.display === 'none') {
            formEventListWrapperOption2.style.display = 'block';
        }
        if (!$('#form-event-list-wrapper #choose_select_forms_events_div').is(':visible')) {
            triangle.click();
        }
        if (record_list_mode) {
            arm_id = document.querySelectorAll('.arm-select')[1]?.value;
            fetchRecords(0);
        }
    }
    checkbox1.addEventListener('change', function() {
        if (record_list_mode) {
            arm_id = document.querySelectorAll('.arm-select')[0]?.value;
            fetchRecords(0);
        }
        if (checkbox1.checked) {
            selectAllFormsEvents(false, true);
            const dropdown = formEventListWrapperOption1.querySelector('#arm-select-option1');
            if (formEventListWrapperOption1.style.display === 'none' && dropdown.options.length > 1) {
                formEventListWrapperOption1.style.display = 'block';
            }
            if (formEventListWrapperOption2.style.display !== 'none') {
                formEventListWrapperOption2.style.display = 'none';
            }
        }
    });
    checkbox2.addEventListener('change', function() {
        if (record_list_mode) {
            arm_id = document.querySelectorAll('.arm-select')[1]?.value;
            fetchRecords(0);
        }
        if (checkbox2.checked) {
            if (formEventListWrapperOption1.style.display !== 'none') {
                formEventListWrapperOption1.style.display = 'none';
            }
            if (formEventListWrapperOption2.style.display === 'none') {
                formEventListWrapperOption2.style.display = 'block';
            }
            selectAllFormsEvents(false, false);
            triangle.onclick = toggleFormEventListDisplay(longitudinalArmsListOption2, triangle);
            if (!$('#form-event-list-wrapper #choose_select_forms_events_div').is(':visible')) {
                triangle.click();
            }
        }
    });

    function renderCustomList(records) {
        $('#custom-output').empty();
        records.forEach(function(record){
            var inputnode = document.createElement("input");
            inputnode.type = "hidden";
            inputnode.value = record;
            inputnode.name = "records[]";
            document.getElementById("custom-output").appendChild(inputnode);
        })
    }
    function checkRecordsExist(records, group_id, arm_id) {
        return new Promise((resolve, reject) => {
            $.ajax({
                method: 'POST',
                url: app_path_webroot+'index.php?pid='+pid+'&route=BulkRecordDeleteController:checkRecordsExist',
                dataType: 'json',
                data: {
                    records: records,
                    group_id: group_id,
                    arm_id: arm_id
                },
                success: function(data) {
                    resolve(data.response);
                },
                error: function(jqXHR) {
                    let errorMessage = jqXHR.responseText || "Unknown Error: Could not validate records.";
                    if (jqXHR.responseText) {
                        try {
                            var responseJson = JSON.parse(jqXHR.responseText);
                            errorMessage = responseJson.message || errorMessage;
                        } catch(e) {
                            console.error("Error parsing JSON response: ", e);
                        }
                    }
                    reject(errorMessage);
                }
            });
        });
    }

    function debounce(func, wait, immediate) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    function extractTextContent(text) {
        const match = text.match(/^(.*?)\s*(\(([^)]*)\))?$/);
        if (match) {
            if (!match[2]) {
                return match[1].toLowerCase();
            } else {
                return [match[1].toLowerCase(), match[2].toLowerCase()];
            }
        }
        return '';
    }

    function handleSearch() {
        const searchText = searchBox.value.toLowerCase();
        recordList.innerHTML = '';
        recordListItems.forEach(item => {
            const itemText = extractTextContent(item.textContent);
            const itemTextString = Array.isArray(itemText) ? itemText.join(' ').toLowerCase() : itemText;
            if (itemTextString.includes(searchText.trim())) {
                recordList.appendChild(item);
            }
        });
    }
    if (searchBox) {
        const debouncedSearch = debounce(handleSearch, 250);
        searchBox.addEventListener('keyup', debouncedSearch);
        searchBox.addEventListener('keydown', function(event) {
            if (event.key === "Enter") {
                event.preventDefault();
                return false;
            }
        });
    }
    function fetchRecords(limitOffset = 0) {
        app.setView('fetching');
        $.ajax({
            method: 'POST',
            url: app_path_webroot+'index.php?pid='+pid+'&route=BulkRecordDeleteController:fetchRecords&limitOffset='+limitOffset,
            dataType: 'json',
            data: {
                arm_id: arm_id,
                group_id: group_id
            },
            success: function(data) {
                if (data.hasOwnProperty('records')) {
                    renderRecordList(data.records);
                }
            },
            error: function(jqXHR) {
                let errorMessage = jqXHR.responseText || "Unknown Error: Could not validate records.";
                if (jqXHR.responseText) {
                    try {
                        var responseJson = JSON.parse(jqXHR.responseText);
                        errorMessage = responseJson.message || errorMessage;
                    } catch(e) {
                        console.error("Error parsing JSON response: ", e);
                    }
                }
                alert(errorMessage);
            }
        });

    }
    function renderRecordList(records) {
        $('.card').removeClass("hidden");
        $('#record-output').empty();
        let total = 0;
        records.forEach(function(chunk, index){
            let keys = Object.keys(chunk);
            let lastKey = keys[keys.length - 1];
            total += keys.length;
            if (total > fetchLimit) {
                delete chunk[lastKey];
                if (nextPageButton != null) nextPageButton.style.display = 'inline-block';
            } else {
                if (nextPageButton != null) nextPageButton.style.display = 'none';
            }
            setTimeout(() => {
                Object.entries(chunk).forEach(([id, name]) => {
                    var node = document.createElement("li");
                    var inputnode = document.createElement("input");
                    inputnode.type = "checkbox";
                    inputnode.value = id;
                    inputnode.name = "records[]";
                    let tag = " " + (name.trim() === "" ? id : (id + " (" + name + ")"));
                    var label= document.createElement("label");
                    label.appendChild(inputnode);
                    label.appendChild(document.createTextNode(tag));
                    node.appendChild(label);
                    $('#record-output').append(node);
                });
            }, 0.001);

        });
        setTimeout(() => {
            app.setView('fetched');
            // recordList variable expected to be null when clicking on option to select records from a list because the element with id `record-output` not yet present in document flow at that time.
            recordListItems = Array.from(recordList?.getElementsByTagName('li') ?? []);
            if (currentPage === 0) {
                if (prevPageButton != null) prevPageButton.style.display = 'none';
            }
        }, 0.01);
    }
    function resetSelection()
    {
        if (selection) {
            selection = null;
        }
    }
    window.BulkRecordDelete = app;
}
});

function selectAllRecords(select_all) {
    $('#record-output input[type="checkbox"]').prop('checked',select_all);
    BulkRecordDelete.setSelection();
}

function selectAllInEvent(event_name,ob) {
    $('#choose_select_forms_events_div_sub input[id^="ef-'+event_name+'-"]').prop('checked',$(ob).prop('checked'));
}

function selectAllFormsEvents(selectAll, disable = false) {
    let all = $('#choose_select_forms_events_div_sub input[type="checkbox"]')
    all.prop('checked', selectAll);
    all.prop('disabled',disable);
}

function updateCountRecordsScheduledForDeletion(recordsList) {
    countRecordsScheduledForDeletion = recordsList.length;
    document.getElementById('count-scheduled-for-deletion').innerText = countRecordsScheduledForDeletion;
}

function reloadBgDeletionTable()
{
    bgDeletionTable.ajax.reload();
}

function viewBgDeleteDetails(delete_id)
{
    $.get(app_path_webroot+'index.php?route=BulkRecordDeleteController:viewBackgroundDeleteDetails&pid='+pid+'&delete_id='+delete_id, {}, function(data){
        simpleDialog(data, lang.data_entry_724, 'view-details-dialog', 600);
    });
}

function cancelBgDelete(delete_id)
{
    $.post(app_path_webroot+'index.php?route=BulkRecordDeleteController:cancelBackgroundDelete&pid='+pid, { delete_id: delete_id, action: 'view'}, function(data){
        // If user is not the uploader, prevent them from doing anything
        if (data == '2') {
            simpleDialog('<div class="text-dangerrc fs14"><i class="fa-solid fa-circle-exclamation"></i> '+lang.data_entry_713+'</div>');
            return;
        }
        if (data == '1') {
            simpleDialog(lang.data_entry_715, lang.data_entry_714, 'cancel-delete-dialog', 600, null, lang.data_entry_716, function () {
                // Cancel it
                $.post(app_path_webroot + 'index.php?route=BulkRecordDeleteController:cancelBackgroundDelete&pid=' + pid, {
                    delete_id: delete_id,
                    action: 'save'
                }, function (data) {
                    if (data == '1') {
                        reloadBgDeletionTable();
                        simpleDialog('<div class="text-successrc fs14"><i class="fa-solid fa-check"></i> ' + lang.data_entry_717 + '</div>', lang.global_79);
                    } else {
                        alert(woops);
                    }
                });
            }, lang.data_import_tool_393);
        } else {
            alert(woops);
        }
    });
}