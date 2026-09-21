var message;
var foundInvlid = lang.mycap_mobile_app_175;
var gte = lang.config_functions_89;
var numeric = lang.data_import_tool_85;
var delayMsg = lang.mycap_mobile_app_178;

$(document).ready(function() {
    var defaultVisibility = 1;
    var icons = ['down', 'up'];
    var btnLbl = [lang.design_774, lang.design_776];
    var toggleAction = [ 'show', 'hide' ];
    function btnLblText(visibility) {
        return '<i class="fas fa-chevron-'+icons[visibility]+'"></i>&nbsp;'+btnLbl[visibility];
    }
    var toggleRows = function() {
        const $this = $(this);
        const eventName = $this.attr('data-toggle-event');
        const visible = btnLbl.indexOf($this.text().trim()); // visible when button says "Collapse"
        // Toggle and switch button label
        $this.html(btnLblText(1-visible));
        $('#schedule-setting-table tr[data-event="' + eventName + '"]')[toggleAction[visible]]();
        //$('#'+eventName+'-collapsed')[collapsedToggle[visible]]('d-print-none')
    };
    $('#schedule-setting-table .setup-form-header').each(function() {
        const $this = $(this);
        const event = $this.attr('data-event-name');
        const $btn = $('<button type="button" data-toggle-event="'+event+'" class="btn btn-xs btn-primaryrc toggle-rows d-print-none" style="float:right;" data-toggle="button">'+btnLblText(defaultVisibility)+'</button>');
        $btn.on('click', toggleRows);
        const $collapsed = $('<span id="'+event+'-collapsed" class="visible_in_print_only d-print-none" style="float:right;padding-right:5px;">[collapsed]</span>');
        $this.append($btn)
        $this.append($collapsed);
    });

    jQuery('[data-toggle="popover"]').popover({
        html : true,
        content: function() {
            return $(jQuery(this).data('target-selector')).html();
        },
        title: function(){
            return '<span style="padding-top:0px;">'+jQuery(this).data('title')+'<span class="close" style="line-height: 0.5;padding-top:0px;padding-left: 10px">&times;</span></span>';
        }
    }).on('shown.bs.popover', function(e){
        var popover = jQuery(this);
        jQuery(this).parent().find('div.popover .close').on('click', function(e){
            popover.popover('hide');
        });
        $('div.popover .close').on('click', function(e){
            popover.popover('hide');
        });

    });
    //We add this or the second time we click it won't work. It's a bug in bootstrap
    $('[data-toggle="popover"]').on("hidden.bs.popover", function() {
        //BOOTSTRAP 4
        $(this).data("bs.popover")._activeTrigger.click = false;
    });

    //To prevent the popover from scrolling up on click
    $("a[rel=popover]")
        .popover()
        .click(function(e) {
            e.preventDefault();
        });
    // Load image in preview for selected System image
    $('#system_image').change(function(){
        var selected_img_type = $(this).val();
        var url_img = app_path_images+systemImages[selected_img_type]+'.png';
        $('#image_div').find("img").attr('src',url_img);
        $('#image_div').show();
    });

    // Remove existing image to upload new custom image
    $('.remove-image').click(function() {
        if (confirm(lang.mycap_mobile_app_21)) {
            $("#new_image_div").css({"display":"block"});
            $("#old_image_div").css({"display":"none"});
            $("#old_image").val("");
        }
    });

    // Save Page information form
    $('#savePage').submit(function () {
        if (validatePageInfoForm()) {
            //close confirmation modal
            var index = $('#index_modal_update').val();
            $('#external-modules-configure-modal').modal('hide');
            var data = new FormData(document.getElementById("savePage"));
            submitForm(data,app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=savePage',(index == "" ? "A" : "U"));
        }
        return false;
    });

    var message_letter = getParameterByName('message');
    if (message_letter != '') {
        // Modify the URL
        var url = window.location.href;
        // Remove 'message=?' from current url
        modifyURL(url.split( '&message=' )[0]);
    }
    //Messages on reload
    if(message != "") {
        $("#succMsgContainer").html(message);
        var msgBox = $("#succMsgContainer");
        setTimeout(function(){
            msgBox.slideToggle('normal');
        },300);
        setTimeout(function(){
            msgBox.slideToggle(1200);
        }, 5000);
    }

    $(".color-scheme-letter").each(function(){
        $(this).tooltip2({ tipClass: 'tooltip1', position: 'top center'});
    });
    $(".system-theme").each(function(){
        $(this).tooltip2({ tipClass: 'tooltip3', position: 'top center'});
    });

    $(".link-icon").click(function() {
        $(".link-icon").removeClass('selected');
        $(".link-icon i").hide();
        $(this).addClass('selected');
        $( this ).children( 'i.fa-check' ).show();
        $("#selected_icon").val($(this).attr("data-value"));
    });

    // Save Link information form
    $('#saveLink').submit(function () {
        if (validateLinkInfoForm()) {
            //close confirmation modal
            var index = $('#index_modal_update').val();
            $('#external-modules-configure-modal-link').modal('hide');
            var data = new FormData(document.getElementById("saveLink"));

            submitForm(data,app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=saveLink',(index == "" ? "AL" : "UL"));
        }
        return false;
    });

    // Save Contact information form
    $('#saveContact').submit(function () {
        if (validateContactInfoForm()) {
            //close confirmation modal
            var index = $('#index_modal_update').val();
            $('#external-modules-configure-modal-contact').modal('hide');
            var data = new FormData(document.getElementById("saveContact"));

            submitForm(data,app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=saveContact',(index == "" ? "AC" : "UC"));
        }
        return false;
    });

    // Links List: Enable drag n drop on link list table
    if ($('table#table-links_list').length) {
        enableLinkListTable();
    }

    // Contacts List: Enable drag n drop on contacts list table
    if ($('table#table-contacts_list').length) {
        enableContactListTable();
    }

    var params = new window.URLSearchParams(window.location.search);
    if (params.get('theme') == 1) {
        $(".color-picker").ColorPickerSliders({
            previewformat: 'hex',
            placement: 'top',
            swatches: false,
            sliders: false,
            hsvpanel: true,
            title: $(this).attr('title'),
            invalidcolorsopacity: 0
        });
    }

    // Save Task information form
    $('button#taskSettingsSubmit').click(function() {
        submitTaskSettingsForm();
    });

    $('.date-input').datepicker({
        onSelect: function(){
            $(this).focus();
            if ($(this).val() != '') {
                var eventId = $(this).prop("name").split('-')[1];
                $('#schedule_ends_on_date-'+eventId).prop('checked', true);
                $('#schedule_ends_conditions-'+eventId).prop('checked', true);
            }
        },
        buttonText: window.lang.calendar_widget_choosedatehint, yearRange: '-50:+10', showOn: 'both', buttonImage: app_path_images+'date.png',
        buttonImageOnly: true, changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery, constrainInput: false
    });

    $('.schedule_type_sel').click(function() {
        var selectionVal = '';
        if ($(this).is(':checked')){
            selectionVal = $(this).val();

            var infiniteSchedule = false;
            if (selectionVal == infiniteType || selectionVal == repeatingType) {
                var label = selectionVal.substring(1, selectionVal.length); // Remove "." from ".Infinite" or ".Repeating"
                if (label == "Repeating") {
                    $(this).closest('tr').find("#scheduleRepeatingFields").removeClass("disableInputs");
                    $(this).closest('tr').find("#scheduleFixedFields").addClass("disableInputs");
                } else if (label == "Infinite") {
                    $(this).closest('tr').find("#scheduleRepeatingFields").addClass("disableInputs");
                    $(this).closest('tr').find("#scheduleFixedFields").addClass("disableInputs");
                    infiniteSchedule = true;
                }
                $(this).closest('table').find("#endTaskFields").show();
                var scheduleText = "";
                if (label == "Infinite") {
                    scheduleText = lang.mycap_mobile_app_142;
                }
                if (label == "Repeating") {
                    scheduleText = lang.mycap_mobile_app_143;
                }
                $(this).closest('table').find("#typeSelection").html(scheduleText);
            } else {
                if (selectionVal == fixedType) {
                    $(this).closest('tr').find("#scheduleFixedFields").removeClass("disableInputs");
                    $(this).closest('tr').find("#scheduleRepeatingFields").addClass("disableInputs");
                } else if (selectionVal == oneTimeType) {
                    $(this).closest('tr').find("#scheduleRepeatingFields").addClass("disableInputs");
                    $(this).closest('tr').find("#scheduleFixedFields").addClass("disableInputs");
                }
                $(this).closest('table').find("#endTaskFields").hide();
            }
            var eventId = $(this).prop("id").split('-')[1];

            if (infiniteSchedule == true || selectionVal == oneTimeType) {
                // Disable setting "Allow retroactive completion?" for infinite tasks
                $('#allow_retroactive_completion-'+eventId).prop( "disabled", "disabled");
                $('#allow_retro_completion_row-'+eventId).css({ 'opacity' : 0.6 });
            } else {
                // Disable setting "Allow retroactive completion?" for infinite tasks
                $('#allow_retroactive_completion-'+eventId).removeAttr( "disabled");
                $('#allow_retro_completion_row-'+eventId).css("opacity","");
            }
        }
    });

    $(".schedule_frequency_sel").change(function(){
        var selFreq = $(this).val();
        if (selFreq == dailyFreqVal) {
            $(this).closest('tr').find("#schedulePrefix, #scheduleFreqWeekFields, #scheduleDaysOfWeekFields, #scheduleFreqMonthFields, #scheduleDaysOfMonthFields").hide();
        } else if (selFreq == weeklyFreqVal) {
            $(this).closest('tr').find("#schedulePrefix, #scheduleFreqMonthFields, #scheduleDaysOfMonthFields").hide();
            $(this).closest('tr').find("#schedulePrefix, #scheduleFreqWeekFields, #scheduleDaysOfWeekFields").show();
        } else if (selFreq == monthlyFreqVal) {
            $(this).closest('tr').find("#schedulePrefix, #scheduleFreqWeekFields, #scheduleDaysOfWeekFields").hide();
            $(this).closest('tr').find("#schedulePrefix, #scheduleFreqMonthFields, #scheduleDaysOfMonthFields").show();
        }
    });

    $('.schedule-end-count-input').change(function(){
        if ($(this).val() != '') {
            var eventId = $(this).prop("name").split('-')[1];
            $('#schedule_ends_after_count-'+eventId).prop('checked', true);
            $('#schedule_ends_conditions-'+eventId).prop('checked', true);
        }
    });
    $('.schedule-end-after-days').change(function(){
        if ($(this).val() != '') {
            var eventId = $(this).prop("name").split('-')[1];
            $('#schedule_ends_after_days-'+eventId).prop('checked', true);
            $('#schedule_ends_conditions-'+eventId).prop('checked', true);
        }
    });

    $('.schedule_ends_conditions').change(function(){
        var eventId = $(this).prop("id").split('-')[1];
        if ($(this).prop('checked') == false) {
            if ($('#schedule_ends_after_count-'+eventId).prop('checked') == false && $('#schedule_ends_after_days-'+eventId).prop('checked') == false && $('#schedule_ends_on_date-'+eventId).prop('checked') == false) {
                $('#schedule_ends_never-'+eventId).prop('checked', true);
            }
        } else {
            $('#schedule_ends_conditions-'+eventId).prop('checked', true);
        }
    });
    $('.schedule-ends').change(function(){
        var eventId = $(this).prop("id").split('-')[1];
        if ($(this).val() == endsNever) {
            $('#schedule_ends_after_count-'+eventId+', #schedule_ends_after_days-'+eventId+', #schedule_ends_on_date-'+eventId).prop('checked', false);
            $('input[name="schedule_end_count-'+eventId+'"], input[name="schedule_end_after_days-'+eventId+'"], input[name="schedule_end_date-'+eventId+'"]').val('');
        }
    });

    $("button#pagesPreview").click(function(){
        // clear previous preview
        $('#previewContent').html('');

        bioMp(document.getElementById('previewContent'), {
            url: app_path_webroot+'MyCapMobileApp/preview.php?pid='+pid+'&section=about',
            view: 'front',
            image: app_path_images+'iphone6_front_black.png',
            width: 300
        });
    });

    $("button#contactsPreview").click(function(){
        // clear previous preview
        $('#previewContent').html('');

        bioMp(document.getElementById('previewContent'), {
            url: app_path_webroot+'MyCapMobileApp/preview.php?pid='+pid+'&section=contacts',
            view: 'front',
            image: app_path_images+'iphone6_front_black.png',
            width: 300
        });
    });

    $('body').on('click', '#div_initial_setup_instr_show_link', function() {
        $(this).hide();$('#div_initial_setup_instr').show('fade'); fitDialog($('#migrateMyCapDialog'));
    });
    $('body').on('click', '#div_initial_setup_instr_hide_link', function() {
        $('#div_initial_setup_instr').hide('fade'); fitDialog($('#migrateMyCapDialog'));
        $('#div_initial_setup_instr_show_link').show();
    });
    $('body').on('click', '#participant_id_custom_chk', function() {
        if ($(this).prop('checked')) {
            $('#participant_id_custom_div').fadeTo('slow', 1);
            $('#participant_id_div').fadeTo('fast', 0.3);
            $('#participant_custom_field').attr('disabled', true);
            $('#participant_custom_label').attr('disabled', false);
        } else {
            $('#participant_id_custom_div').fadeTo('fast', 0.3);
            $('#participant_id_div').fadeTo('slow', 1);
            $('#participant_custom_field').attr('disabled', false);
            $('#participant_custom_label').attr('disabled', true);
        }
    });
    // Copy-to-clipboard action
    $('.btn-clipboard').click(function(){
        copyUrlToClipboard(this);
    });

    // Set datetime pickers
    $('.filter_datetime_mdy').datetimepicker({
        yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
        hour: currentTime('h'), minute: currentTime('m'), buttonText: lang.alerts_42,
        timeFormat: 'HH:mm', constrainInput: true
    });

    $('#deleted_participants').click(function() {
        // Start "working..." progress bar
        showProgress(1,0);
        if ($(this).is(":checked") == true) {
            window.location.href = app_path_webroot+"MyCapMobileApp/index.php?participants=1&deleted=1&pid="+pid;
        } else {
            window.location.href = app_path_webroot+"MyCapMobileApp/index.php?participants=1&pid="+pid;
        }
    });

    $('[name="template-type"]').click(function(e){
        $.get(app_path_webroot + 'MyCap/participant_info.php?pid='+pid+'&record='+$('#recordVal').val()+'&event_id='+$('#eventVal').val()+'&action=getHTMLByType',
            { type: $('[name="template-type"]:checked').val() },
            function(data) {
                $('#html-message-generated, #textboxTemplate').html(data);
            }
        );
    });

    // Save Announcement form
    $('#saveAnnouncement').submit(function () {
        if (validateAnnouncementInfoForm()) {
            //close confirmation modal
            var index = $('#index_modal_update').val();
            $('#external-modules-configure-modal-ann').modal('hide');
            var data = new FormData(document.getElementById("saveAnnouncement"));

            submitForm(data,app_path_webroot+'MyCapMobileApp/messaging.php?pid='+pid+'&action=saveAnnouncement',(index == "" ? "AA" : "UA"));
        }
        return false;
    });

    $('#removeButton').click(function() {
        $('#delete-announcement-modal').modal('show');
        $('#delete-announcement-modal-body-submit').attr('onclick','removeAnnouncement("'+$('#index_modal_update').val()+'");return false;');
    });

    $('#set-identifier').on('click', function(e){
        e.stopPropagation();
        displaySetUpParticpantLablesPopup();
    });

    var textbox = $("#textboxTemplate");
    var textarea = $("#html-message-generated");

    $("#change").click(function() {
        var el = this;
        return (el.tog^=1) ? turnOff(el) : turnOn(el);
    });
    function turnOn(el){
        $('#change').html('<i class=\'fas fa-code\'></i> '+ window.lang.mycap_mobile_app_901);
        $('#html-message-generated').hide();
        $('#textboxTemplate').show();
        $('#messageQR_dialog .btn-clipboard').attr('data-clipboard-target', '#textboxTemplate');
    };
    function turnOff(el){
        $('#change').html('<i class=\'fas fa-magnifying-glass\'></i> '+ window.lang.mycap_mobile_app_902);
        $('#textboxTemplate').hide();
        $('#html-message-generated').show();
        $('#messageQR_dialog .btn-clipboard').attr('data-clipboard-target', '#html-message-generated');
    };

    $('[data-bs-toggle="popover"], [data-toggle="popover"]').hover(function(e) {
        // Show popup
        popover = new bootstrap.Popover(e.target, {
            html: true,
            title: $(this).data('title'),
            content: $(this).data('content')
        });
        popover.show();
    }, function() {
        // Hide popup
        bootstrap.Popover.getOrCreateInstance(this).dispose();
    });

    $('body').on('click', '#show_issues_list', function() {
        $(this).hide();
        $('#div_errors_list, #div_warnings_list').show('fade');
        $('#hide_issues_list').show();
    });
    $('body').on('click', '#hide_issues_list', function() {
        $(this).hide();
        $('#div_errors_list, #div_warnings_list').hide('fade');
        $('#show_issues_list').show();
    });

    // Update schedule description on change of schedule relative to value
    $('input[type=radio][name=schedule_relative_to]').change(function() {
        if ($(this).attr("id") == 'install_date') {
            $(".scheduleToText").html("Install Date");
        }
        else if ($(this).attr("id") == 'baseline_date') {
            $(".scheduleToText").html("Baseline Date");
        }
    });
    $(".enableSchedule, .disableSchedule").tooltip({ tipClass: 'tooltip4sm', position: 'top center' });

    $(".active-task-list-tab").click(function() {
        var showId = $(this).attr('id');
        if (showId == 'researchKitTasks') {
            $(this).closest( "li").addClass('active');
            $('#mtbTasks').closest( "li").removeClass('active');
            $('#List_mtbTasks').hide();
            $('#List_researchKitTasks').show();
        } else {
            $('#researchKitTasks').closest( "li").removeClass('active');
            $(this).closest( "li").addClass('active');
            $('#List_researchKitTasks').hide();
            $('#List_mtbTasks').show();
        }
    });

    $("#participant_table").find('th').each (function() {
        // Initiate popover JS upon sorting columns of participant listing table to show install_date_utc and timezone popups
        $(this).click(function() {
            $('[data-bs-toggle="popover"], [data-toggle="popover"]').hover(function(e) {
                // Show popup
                popover = new bootstrap.Popover(e.target, {
                    html: true,
                    title: $(this).data('title'),
                    content: $(this).data('content')
                });
                popover.show();
            }, function() {
                // Hide popup
                bootstrap.Popover.getOrCreateInstance(this).dispose();
            });
        });
    });

    $(document).click(function(event) {
        if(page == 'MyCap/edit_task.php' || page == 'MyCap/create_task.php') {
            if (!$(event.target).hasClass('dropdown-menu') && $(event.target).parents('.dropdown-menu').length == 0) {
                $(".dropdown-menu").hide();
            }
        }
    });

    // Save Contact information form
    $('#saveNotification').submit(function () {
        if (validateNotificationSettingsForm()) {
            var data = new FormData(document.getElementById("saveNotification"));
            submitForm(data,app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=saveNotification', "NU");
        }
        return false;
    });

    $('body').on('change', '#notify_participant', function() {
        if ($(this).is(":checked")) {
            // Notify Participant Checkbox is checked
            $('#par-message-box').css({ 'opacity' : "" });
            $("#par-message-box textarea").prop("disabled", false);
        } else {
            // Notify Participant Checkbox is unchecked
            $('#par-message-box').css({ 'opacity' : 0.6 });
            $("#par-message-box textarea").prop("disabled", true);
        }
    });

    $("textarea[id^='mceEditor_']").each(function() {
        initTinyMCEglobal($(this).attr('id'));
    });


    $(".enable-message-notification").change(function () {
        var tbl = $(this).closest("table");
        if ($(this).is(':checked')) {
            tbl.find("tbody>tr.notification-settings-tr").css({ 'opacity' : "" });
            tbl.find("tbody>tr textarea").prop("disabled", false);
        } else {
            tbl.find("tbody>tr.notification-settings-tr").css({ 'opacity' : "0.5" });
            tbl.find("tbody>tr textarea").prop("disabled", true);
        }
    });

    // Save Contact information form
    $('#saveMsgNotification').submit(function () {
        var errMsg = validateMessageNotificationSettingsForm();
        if (errMsg != '') {
            $('#errMsgNotificationContainer').empty();
            $('#errMsgNotificationContainer').append(errMsg);
            $('#errMsgNotificationContainer').show();
            $('html,body').scrollTop(0);
            return false;
        } else {
            var data = new FormData(document.getElementById("saveMsgNotification"));
            submitForm(data,app_path_webroot+'MyCapMobileApp/msg_notification_settings.php?pid='+pid+'&action=saveMsgNotification', "NMU");
        }
        return false;
    });
});

function validateEmailList (emailList) {
    var emails = emailList.split('\n');
    var invalidEmails = [];
    var regex = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;

    $.each(emails, function(index, email) {
        if (email.trim() !== "") { // Avoid adding empty lines
            if(regex.test(email) == false) {
                invalidEmails.push("<b>"+email+"</b>");
            }
        }
    });

    return invalidEmails;
}
function validateMessageNotificationSettingsForm() {
    var errDagIds = [];
    var errText = [];
    var errMsg = errDagNote = '';
    var invalidEmails = [];
    $('input[name="dag_ids[]"]').each(function() {
        var dagId = $(this).val();

        if ($('input[name="notify_user_'+dagId+'"]').is(':checked')) {
            if ($('textarea[name="user_emails_'+dagId+'"]').val() == '')  {
                errDagIds.push(dagId);
                errText.push(lang.survey_515);
                $('textarea[name="user_emails_'+dagId+'"]').addClass('error-field');

            }
            var output = validateEmailList($('textarea[name="user_emails_'+dagId+'"]').val());
            if (output.length > 0) {
                invalidEmails = invalidEmails.concat(output);
                errDagIds.push(dagId);
                $('textarea[name="user_emails_'+dagId+'"]').addClass('error-field');
            }
            if ($('textarea[name="custom_text_'+dagId+'"]').val() == '') {
                errDagIds.push(dagId);
                errText.push(lang.mycap_mobile_app_429);
            }
        }
    });
    if (invalidEmails.length > 0) {
        errText.push(lang.mycap_mobile_app_981 + invalidEmails.join(", "));
    }

    if (errText.length > 0) {
        errText = errText.filter(function (itm, i, a) {
            return i == a.indexOf(itm);
        });
    }

    if (errDagIds.length > 0) {
        errDagIds = errDagIds.filter(function (itm, i, a) {
            return i == a.indexOf(itm);
        });

        var dagLables = [];

        $.each(errDagIds, function (i2, e2) {
            console.log($('li#sel-dag-' + e2).find('a').find('span').length);
            if ($('li#sel-dag-' + e2).find('a').find('span').length > 0) {
                dagLables.push("<b>"+$('li#sel-dag-' + e2).find('a').find('span:first').text()+"</b>");
            }
        });

        if (dagLables.length > 0) {
            errDagNote += ' <span style="font-weight: normal;"> '+ lang.leftparen + lang.mycap_mobile_app_980+' ';
            errDagNote += dagLables.join(", ");
            errDagNote += lang.rightparen + '</span>';
        }
    }

    if (errText.length > 0) {
        errMsg += '<div><b>'+lang.mycap_mobile_app_979+errDagNote+'</b></div><ul>';
        $.each(errText, function (i3, e3) {
            errMsg += '<li>' + e3 + '</li>';
        });
        errMsg += '</ul>';
    }

    return errMsg;
}
function enableNotificationSettings(obj) {
    var dag_id = $(obj).attr("id");
    // Hide all settings block first
    $('[id^="settings-dag-"]').hide();
    $('[id^="sel-dag-"]').removeClass("active");
    // Show related setting block
    $("#settings-"+dag_id).show();
    $("li#sel-"+dag_id).addClass("active");
}

function validateNotificationSettingsForm () {
    if ($('#notification_time').val() == '') {
        simpleDialog(lang.mycap_mobile_app_871, lang.global_01);
        return false;
    }
    return true;
}

// Place Migrate to REDCap button in front of MyCap link at left panel
function placeMyCapMigrationButton(obj) {
    obj.after('<button type="button" ' +
        'id="migrateMyCap" ' +
        'onclick="showMyCapMigrationDialog();"' +
        'class="btn btn-defaultrc btn-xs fs11"' +
        'style="color:#800000;float:right;padding:1px 5px 0;position:relative;top:-1px;"><i style="color:#A00000;" class="fa-solid fa-circle-arrow-right"></i> Migrate to REDCap</button>');
}

// Open mycap migration dialog (to see info/notes/proceed button)
function showMyCapMigrationDialog(flag = '') {
    showProgress(1, 0);
    // Id of dialog
    var dlgid = 'migrateMyCapDialog';
    // Display "migrate" button only for admins
    if (super_user_not_impersonator) {
        var btns = [{
            text: "Cancel", click: function () {
                $(this).dialog('close');
            }
        },
        {
            text: "Begin Migration", click: function () {
                proceedMyCapMigration();
            }
        }];
    } else {
        var btns = [{
            text: "Close", click: function () {
                $(this).dialog('close');
            }
        }];
    }
    // Get content via ajax
    $.post(app_path_webroot + 'MyCap/migrate_mycap.php?action=showDetails&flag='+flag+'&pid=' + pid, {}, function (data) {
        showProgress(0, 0);
        if (data == "0") {
            alert(woops);
            return;
        }
        // Decode JSON
        var json_data = JSON.parse(data);
        // Add html
        initDialog(dlgid);
        $('#' + dlgid).html(json_data.content);
        // Display dialog
        $('#' + dlgid).dialog({
            title: json_data.title, bgiframe: true, modal: true, width: 800, open: function () {
                fitDialog(this);
            }, close: function () {
                $(this).dialog('destroy');
            },
            buttons: btns
        });
    });
}

// Proceed to MyCap EM Migration
function proceedMyCapMigration() {
    // Display progress bar
    showProgress(1);
    if ($('#migrateMyCapDialog').hasClass('ui-dialog-content')) $('#migrateMyCapDialog').dialog('destroy');

    $.post(app_path_webroot+'MyCap/migrate_mycap.php?action=proceedMigration&pid='+pid, {}, function(data){
        var json_data = jQuery.parseJSON(data);
        showProgress(0,0);
        if (json_data.success == 1) {
            Swal.fire({
                title: json_data.title,
                html: json_data.content,
                icon: 'success'
            }).then(function(){
                showProgress(1);
                window.location.href = app_path_webroot+'ProjectSetup/index.php?pid='+pid
            });
        } else {
            Swal.fire({
                title: json_data.title,
                html: json_data.content,
                icon: 'error'
            });
        }
    });
}

// Copy-to-clipboard action
try {
    var clipboard = new Clipboard('.btn-clipboard');
} catch (e) {}

// Copy the html message to the user's clipboard
function copyUrlToClipboard(ob) {
    // Create progress element that says "Copied!" when clicked
    var rndm = Math.random()+"";
    var copyid = 'clip'+rndm.replace('.','');
    $('.clipboardSaveProgress').remove();
    var clipSaveHtml = '<span class="clipboardSaveProgress" id="'+copyid+'">Copied!</span>';
    $(ob).after(clipSaveHtml);
    $('#'+copyid).toggle('fade','fast');
    setTimeout(function(){
        $('#'+copyid).toggle('fade','fast',function(){
            $('#'+copyid).remove();
        });
    },2000);
}

/**
 * Validate add/edit task settings - Basic info, Optional Settings and Task Schedule sections (EXCLUDING Active task extended config settings)
 */
function validateOtherActiveTaskParams() {
    var errMsgBasic = [];
    var errMsgOptional = [];
    var errMsgSchedule = [];
    // Validate Task Title
    if ($('input[name=task_title]').val() == "") {
        errMsgBasic.push(lang.mycap_mobile_app_151);
        $('input[name=task_title]').addClass('error-field');
    } else {
        $('input[name=task_title]').removeClass('error-field');
    }

    // Validate chart fields if selected option is "Chart"
    if ($('#chart:checked').length > 0) {
        if ($('select[name=x_date_field]').val() == "") {
            errMsgBasic.push(lang.mycap_mobile_app_166);
            $('select[name=x_date_field]').addClass('error-field');
        } else {
            $('select[name=x_date_field]').removeClass('error-field');
        }

        if ($('select[name=x_time_field]').val() == "") {
            errMsgBasic.push(lang.mycap_mobile_app_167);
            $('select[name=x_time_field]').addClass('error-field');
        } else {
            $('select[name=x_time_field]').removeClass('error-field');
        }

        if ($('select[name=y_numeric_field]').val() == "") {
            errMsgBasic.push(lang.mycap_mobile_app_168);
            $('select[name=y_numeric_field]').addClass('error-field');
        } else {
            $('select[name=y_numeric_field]').removeClass('error-field');
        }
    }

    var eventIds = [];

    if (isLongitudinal == 1) {
        var checkedEventsCount = $(".event-enabled:checked").length;
        if (checkedEventsCount == 0) {
            errMsgBasic.push(lang.mycap_mobile_app_779);
        } else {
            $('.event-enabled:checked').each(function() {
                var idText = this.id;
                var idParts= idText.split("-");
                eventIds.push(idParts[1]);
            });
        }
    } else {
        eventIds.push(firstEventId);
    }
    var errEventIdsOptional = [];
    var errEventIdsSchedule = [];
    $.each(eventIds, function( i, eventId ) {
        // Validate Optional Settings
        if ($('#instruction_step-'+eventId).is(':checked')) {
            if ($('input[name=instruction_step_title-'+eventId+']').val() == "") {
                errEventIdsOptional.push(eventId);
                errMsgOptional.push(lang.mycap_mobile_app_169);
                $('input[name=instruction_step_title-'+eventId+']').addClass('error-field');
            } else {
                $('input[name=instruction_step_title-'+eventId+']').removeClass('error-field');
            }

            if ($('textarea[name=instruction_step_content-'+eventId+']').val() == "") {
                errEventIdsOptional.push(eventId);
                errMsgOptional.push(lang.mycap_mobile_app_170);
                $('textarea[name=instruction_step_content-'+eventId+']').addClass('error-field');
            } else {
                $('textarea[name=instruction_step_content-'+eventId+']').removeClass('error-field');
            }
        }

        if ($('#completion_step-'+eventId).is(':checked')) {
            if ($('input[name=completion_step_title-'+eventId+']').val() == "") {
                errEventIdsOptional.push(eventId);
                errMsgOptional.push(lang.mycap_mobile_app_171);
                $('input[name=completion_step_title-'+eventId+']').addClass('error-field');
            } else {
                $('input[name=completion_step_title-'+eventId+']').removeClass('error-field');
            }

            if ($('textarea[name=completion_step_content-'+eventId+']').val() == "") {
                errEventIdsOptional.push(eventId);
                errMsgOptional.push(lang.mycap_mobile_app_172);
                $('textarea[name=completion_step_content-'+eventId+']').addClass('error-field');
            } else {
                $('textarea[name=completion_step_content-'+eventId+']').removeClass('error-field');
            }
        }
        // Validate Schedules
        if ($('#repeating-'+eventId).is(':checked')) {
            $('input[name="schedule_days_fixed-'+eventId+'"]').removeClass('error-field');
            if ($('select[name=schedule_frequency-'+eventId+']').val() == weeklyFreqVal) {
                var checkedDays = $('input[name="schedule_days_of_the_week-'+eventId+'[]"]:checked').length;
                if (checkedDays == 0) {
                    errEventIdsSchedule.push(eventId);
                    errMsgSchedule.push(lang.mycap_mobile_app_173);
                }
            }
            if ($('select[name=schedule_frequency-'+eventId+']').val() == monthlyFreqVal) {
                var checkedDays = $('input[name="schedule_days_of_the_month-'+eventId+'"]').val();
                var errText = lang.mycap_mobile_app_174;
                var errorDesc = "";
                if (checkedDays == "") {
                    errEventIdsSchedule.push(eventId);
                    errMsgSchedule.push(errText);
                    $('input[name="schedule_days_of_the_month-'+eventId+'"]').addClass('error-field');
                } else {
                    var nums = checkedDays.split(",");
                    var isError = false;
                    for (var i in nums) {
                        if (isNaN(nums[i])) {
                            errorDesc = foundInvlid+nums[i];
                            isError = true;
                            break;
                        } else {
                            if (nums[i] < 1 || nums[i] > 31) {
                                errorDesc = foundInvlid+nums[i];
                                isError = true;
                                break;
                            }
                        }

                    }
                    if (isError) {
                        errEventIdsSchedule.push(eventId);
                        errMsgSchedule.push(errText + " " + errorDesc);
                        $('input[name="schedule_days_of_the_month-'+eventId+'"]').addClass('error-field');
                    } else {
                        $('input[name="schedule_days_of_the_month-'+eventId+'"]').removeClass('error-field');
                    }
                }
            }
        }

        if ($('#fixed-'+eventId).is(':checked')) {
            $('input[name="schedule_days_of_the_month-'+eventId+'"]').removeClass('error-field');
            var checkedDays = $('input[name="schedule_days_fixed-'+eventId+'"]').val();
            var errText = lang.mycap_mobile_app_176;
            if (checkedDays == "") {
                errEventIdsSchedule.push(eventId);
                errMsgSchedule.push(errText);
                $('input[name="schedule_days_fixed-'+eventId+'"]').addClass('error-field');
            } else {
                var nums = checkedDays.split(",");

                var isError = false;
                var errorDesc = "";
                for (var i in nums) {
                    if (isNaN(nums[i])) {
                        errorDesc = foundInvlid+nums[i];
                        isError = true;
                        break;
                    }
                }
                if (isError) {
                    errEventIdsSchedule.push(eventId);
                    errMsgSchedule.push(errText+" "+errorDesc);
                    $('input[name="schedule_days_fixed-'+eventId+'"]').addClass('error-field');
                } else {
                    $('input[name="schedule_days_fixed-'+eventId+'"]').removeClass('error-field');
                }
            }
        }

        // Validate "Number of days to delay"
        if($("input[name=schedule_relative_offset-"+eventId+"]").length) {
            if ($('input[name=schedule_relative_offset-'+eventId+']').val() == "") {
                errEventIdsSchedule.push(eventId);
                errMsgSchedule.push(delayMsg+" "+numeric);
                $('input[name="schedule_relative_offset-'+eventId+'"]').addClass('error-field');
            } else {
                $('input[name="schedule_relative_offset-'+eventId+'"]').removeClass('error-field');
                var delay = $('input[name=schedule_relative_offset-'+eventId+']').val();
                if (isNaN(delay)) {
                    errEventIdsSchedule.push(eventId);
                    errMsgSchedule.push(delayMsg+" "+numeric+ foundInvlid +delay);
                    $('input[name="schedule_relative_offset-'+eventId+'"]').addClass('error-field');
                } else if (delay < 0) {
                    errEventIdsSchedule.push(eventId);
                    errMsgSchedule.push(delayMsg+" "+numeric+" "+gte+" 0."+ foundInvlid +delay);
                    $('input[name="schedule_relative_offset-'+eventId+'"]').addClass('error-field');
                }
            }
        }

        // Validate "End this task" if condition checkbox is selected
        if($('input:radio[name=schedule_ends-'+eventId+']:checked').val() != endsNever) {
            if (!$('#schedule_ends_after_count-'+eventId).is(':checked')
                && !$('#schedule_ends_after_days-'+eventId).is(':checked')
                && !$('#schedule_ends_on_date-'+eventId).is(':checked'))
            {
                    errEventIdsSchedule.push(eventId);
                    errMsgSchedule.push(lang.mycap_mobile_app_836);
            }
        }

        if ($('#infinite-'+eventId).is(':checked') || $('#repeating').is(':checked')) {
            if ($('#schedule_ends_after_count-'+eventId).is(':checked')) {
                $('input[name="schedule_end_after_days-'+eventId+'"]').removeClass('error-field');
                $('input[name="schedule_end_date-'+eventId+'"]').removeClass('error-field');
                var element = $('input[name="schedule_end_count-'+eventId+'"]');
                var times = element.val();
                var errText = lang.mycap_mobile_app_179;
                if (times == "") {
                    errEventIdsSchedule.push(eventId);
                    errMsgSchedule.push(errText);
                    element.addClass('error-field');
                } else {
                    if (isNaN(times)) {
                        errorDesc = foundInvlid+times;
                        isError = true;
                    } else if (times <= 0) {
                        errorDesc = gte+" 1."+foundInvlid+times;
                        isError = true;
                    }
                    if (isError) {
                        errEventIdsSchedule.push(eventId);
                        errMsgSchedule.push(errText+" "+errorDesc);
                        element.addClass('error-field');
                    } else {
                        element.removeClass('error-field');
                    }
                }
            }

            if ($('#schedule_ends_after_days-'+eventId).is(':checked')) {
                $('input[name="schedule_end_count-'+eventId+'"]').removeClass('error-field');
                $('input[name="schedule_end_date-'+eventId+'"]').removeClass('error-field');
                var element = $('input[name="schedule_end_after_days-'+eventId+'"]');
                var days = element.val();
                var errText = lang.mycap_mobile_app_180;
                if (days == "") {
                    errEventIdsSchedule.push(eventId);
                    errMsgSchedule.push(errText);
                    element.addClass('error-field');
                } else {
                    if (isNaN(days)) {
                        errorDesc = foundInvlid+days;
                        isError = true;
                    } else if (days <= 0) {
                        errorDesc = gte+" 1."+foundInvlid+days;
                        isError = true;
                    }
                    if (isError) {
                        errEventIdsSchedule.push(eventId);
                        errMsgSchedule.push(errText+" "+errorDesc);
                        element.addClass('error-field');
                    } else {
                        element.removeClass('error-field');
                    }
                }
            }

            if ($('#schedule_ends_on_date-'+eventId).is(':checked')) {
                $('input[name="schedule_end_count-'+eventId+'"]').removeClass('error-field');
                $('input[name="schedule_end_after_days-'+eventId+'"]').removeClass('error-field');
                var element = $('input[name="schedule_end_date-'+eventId+'"]');
                var date= element.val();
                if (date == "") {
                    errEventIdsSchedule.push(eventId);
                    errMsgSchedule.push(lang.mycap_mobile_app_181);
                    element.addClass('error-field');
                } else {
                    element.removeClass('error-field');
                }
            }
        }

    });

    var errMsg = new Object();

    var errMsg1 = '';
    var errMsg2 = '';
    var errMsg3 = '';

    // Generate error text in basic task info section
    if (errMsgBasic.length > 0) {
        errMsg1 += '<div><b>'+lang.mycap_mobile_app_182+' '+lang.mycap_mobile_app_107+'</b></div><ul>';
        $.each(errMsgBasic, function (i1, e1) {
            errMsg1 += '<li>' + e1 + '</li>';
        });
        errMsg1 += '</ul>';
    }
    errMsg['basic'] = errMsg1;

    errOptionalNote = errScheduleNote = '';
    // Generate error text in Optional settings section
    if (errMsgOptional.length > 0) {
        errMsgOptional = errMsgOptional.filter(function(itm, i, a) {
            return i == a.indexOf(itm);
        });
        if (isLongitudinal == 1 && errEventIdsOptional.length > 0) {
            errEventIdsOptional = errEventIdsOptional.filter(function(itm, i, a) {
                return i == a.indexOf(itm);
            });
            var eventLables = [];
            errOptionalNote += ' <span style="font-weight: normal;">(Please note, below errors exist for one or more selected events from list: ';
            $.each(errEventIdsOptional, function (i2, e2) {
                eventLables.push($('tr#tstr-'+e2).find('td:eq(0)').find('span').text());
            });
            errOptionalNote += eventLables.join(", ");
            errOptionalNote += ')</span>';
        }
        errMsg2 +='<div><b>'+lang.mycap_mobile_app_182+' '+lang.design_984+errOptionalNote+'</b></div><ul>';
        $.each(errMsgOptional, function (i2, e2) {
            errMsg2 += '<li>' + e2 + '</li>';
        });
        errMsg2 += '</ul>';
    }
    errMsg['optional'] = errMsg2;

    // Generate error text in Set up task schedule section
    if (errMsgSchedule.length > 0) {
        errMsgSchedule = errMsgSchedule.filter(function(itm, i, a) {
            return i == a.indexOf(itm);
        });

        if (isLongitudinal == 1 && errEventIdsSchedule.length > 0) {
            errEventIdsSchedule = errEventIdsSchedule.filter(function (itm, i, a) {
                return i == a.indexOf(itm);
            });
            var eventLables = [];
            errScheduleNote += ' <span style="font-weight: normal;">(Please note, below errors exist for one or more selected events from list: ';
            $.each(errEventIdsSchedule, function (i2, e2) {
                eventLables.push($('tr#tstr-' + e2).find('td:eq(0)').find('span').text());
            });
            errScheduleNote += eventLables.join(", ");
            errScheduleNote += ')</span>';
        }
        errMsg3 += '<div><b>'+lang.mycap_mobile_app_182+' '+lang.mycap_mobile_app_137+errScheduleNote+'</b></div><ul>';
        $.each(errMsgSchedule, function (i3, e3) {
            errMsg3 += '<li>' + e3 + '</li>';
        });
        errMsg3 += '</ul>';
        // Hide same error displayed on page to avoid duplicate errors on same page
        $('.red').hide();
    }
    errMsg['schedule'] = errMsg3;

    // Return object with basic, optional and schedule sections error texts
    return errMsg;
}

/**
 * Validate add/edit task settings - If active task, validate via ajax call
 */
function submitTaskSettingsForm() {

    $('#errMsgContainerModal').empty();
    $('#errMsgContainerModal').hide();

    if ($("#is_active_task").val() == 1) {
        var errorsActiveTask = '';
        // Send ajax call to validate Active task extended config inputs and then combine with other sections error messages
        var formData = new FormData(document.getElementById("saveTaskSettings"));
        formData.append('action', 'validateActiveTask');
        $.ajax({
            type: "POST",
            url: app_path_webroot+'MyCap/create_activetask.php?pid='+pid,
            data:  formData,
            enctype: 'multipart/form-data',
            processData: false,
            contentType: false,
            dataType: "json",
            success: function(jsonAjax)
            {
                if(jsonAjax.status == 'success'){
                    //refresh page to show changes
                    if(jsonAjax.message != '' && jsonAjax.message != undefined) {
                        errorsActiveTask += '<div><b>'+lang.mycap_mobile_app_182+" "+$("#activeTaskHeading").html()+'</b>'+jsonAjax.note+'</div><ul>';
                        errorsActiveTask += jsonAjax.message;
                        errorsActiveTask += '</ul>';
                    }
                } else {
                    alert(woops);
                }
            },
            complete: function (data) {
                processErrorsDisplay(errorsActiveTask);
            }
        });
    } else {
        processErrorsDisplay("");
    }
}

/**
 * Process error displaying and if no errors, submit task settings form
 */
function processErrorsDisplay(errorsActiveTask) {
    var otherErrors = validateOtherActiveTaskParams();
    // Combine other errors + active task errors in sequence they are in UI of add/edit task settings
    var errMsg = otherErrors['basic'] + otherErrors['optional'] + errorsActiveTask + otherErrors['schedule'];
    if (errMsg != '') {
        $('#errMsgContainerModal').empty();
        $('#errMsgContainerModal').append(errMsg);
        $('#errMsgContainerModal').show();
        $('html,body').scrollTop(0);
        return false;
    } else {
        $("#saveTaskSettings").submit();
        return true;
    }
    return false;
}

/**
 * Validate edit active task settings
 */
function validateActiveTaskSettings() {
    var formData = new FormData(document.getElementById("saveTaskSettings"));//new FormData();
    formData.append('action', 'validateActiveTask');
    $.ajax({
        type: "POST",
        url: app_path_webroot+'MyCap/create_activetask.php?pid='+pid,
        data:  formData,
        enctype: 'multipart/form-data',
        processData: false,
        contentType: false,
        dataType: "json",
        success: function(jsonAjax)
        {
            if(jsonAjax.status == 'success'){
                //refresh page to show changes
                if(jsonAjax.message != '' && jsonAjax.message != undefined){
                    return jsonAjax.message;
                }
                return '';
            } else {
                alert(woops);
            }
        }
    });
}
/**
 * Display confirm message for deleting the task settings for REDCap instrument
 */
function deleteMyCapSettings(task_id, page) {
    simpleDialog(lang.mycap_mobile_app_448,lang.mycap_mobile_app_449,null,600,null,lang.global_53,"deleteMyCapSettingsSave("+task_id+", '"+page+"');",lang.mycap_mobile_app_450);
}

/**
 * Delete the task settings for REDCap instrument
 */
function deleteMyCapSettingsSave(task_id, page) {
    $.post(app_path_webroot+'MyCap/delete_task.php?pid='+pid+'&task_id='+task_id+'&page='+page,{ },function(data){
        if (data != '1') {
            alert(woops);
        } else {
            simpleDialog(lang.mycap_mobile_app_398, lang.mycap_mobile_app_399,null,null,"window.location.href='"+app_path_webroot+"Design/online_designer.php?pid="+pid+"';");
        }
    });
}

/**
 * Enable link list table if links data exists
 */
function enableLinkListTable() {
    // Add dragHandle to first cell in each row
    $("table#table-links_list tr").each(function() {
        var link_id = trim($(this.cells[0]).text());
        $(this).prop("id", "linkrow_"+link_id).attr("linkid", link_id);
        if (isNumeric(link_id)) {
            // User-defined links (draggable)
            $(this.cells[0]).addClass('dragHandle');
        } else {
            $(this).addClass("nodrop").addClass("nodrag");
        }
    });
    // Restripe the link list rows
    restripeLinkListRows();
    if ($('[id^=linkrow_]').length > 2) {
        // Enable drag n drop
        $('table#table-links_list').tableDnD({
            onDrop: function (table, row) {
                // Loop through table
                var ids = "";
                var this_id = $(row).prop('id');
                $("table#table-links_list tr").each(function () {
                    // Gather form_names
                    var row_id = $(this).attr("linkid");
                    if (isNumeric(row_id)) {
                        ids += row_id + ",";
                    }
                });
                // Save new order via ajax
                $.post(app_path_webroot + 'MyCapMobileApp/update.php?pid=' + pid + '&action=reorderLink', {link_ids: ids}, function (returnData) {
                    jsonAjax = jQuery.parseJSON(returnData);
                    redirectToPage(jsonAjax, 'ML');
                });
                // Reset link order numbers in report list table
                resetLinkOrderNumsInTable();
                // Restripe table rows
                restripeLinkListRows();
                // Highlight row
                setTimeout(function () {
                    var i = 1;
                    $('tr#' + this_id + ' td').each(function () {
                        if (i++ != 1) $(this).effect('highlight', {}, 2000);
                    });
                }, 100);
            },
            dragHandle: "dragHandle"
        });
    }
    // Create mouseover image for drag-n-drop action and enable button fading on row hover
    $("table#table-links_list tr:not(.nodrag)").mouseenter(function() {
        $(this.cells[0]).css('background','#ffffff url("'+app_path_images+'updown.gif") no-repeat center');
        $(this.cells[0]).css('cursor','move');
    }).mouseleave(function() {
        $(this.cells[0]).css('background','');
        $(this.cells[0]).css('cursor','');
    });
    // Set up drag-n-drop pop-up tooltip
    var first_hdr = $('#links_list .hDiv .hDivBox th:first');
    first_hdr.prop('title',lang.mycap_mobile_app_66);
    first_hdr.tooltip2({ tipClass: 'tooltip4sm', position: 'top center', offset: [25,0], predelay: 100, delay: 0, effect: 'fade' });
    $('.dragHandle').mouseenter(function() {
        first_hdr.trigger('mouseover');
    }).mouseleave(function() {
        first_hdr.trigger('mouseout');
    });
}

/**
 * Restripe the rows of the link list table
 */
function restripeLinkListRows() {
    var i = 1;
    $("table#table-links_list tr").each(function() {
        // Restripe table
        if (i++ % 2 == 0) {
            $(this).addClass('erow');
        } else {
            $(this).removeClass('erow');
        }
    });
}

/**
 * Reset link order numbers in links list table
 */
function resetLinkOrderNumsInTable() {
    var i = 1;
    $("table#table-links_list tr:not(.nodrag)").each(function(){
        $(this).find('td:eq(1) div').html(i++);
    });
}

/**
 * Function that shows the modal with the page information to modify it
 * @param modal, array with the data from a specific page
 * @param index, the page id
 * @param pageNum, the page number
 */
function editAboutPage(modal, index, pageNum)
{
    $('input, textarea').removeClass('error-field');
    if (pageNum == 0 && index != '') {
        $("#info-page-msg").hide();
        $("#home-page-msg").show();

        // Only custom option is available for image type selection for homepage
        $("#type-system").removeClass("d-inline");
        $("#image-type-custom").hide();
        $("#type-system").hide();
        $("#home-page-note").addClass("d-inline");
        $("#home-page-note").show();
    } else {
        $("#home-page-msg").hide();
        $("#info-page-msg").show();
        // Both options - system, custom is available for image type selection
        $("#type-system").addClass("d-inline");
        $("#image-type-custom").show();
        $("#type-system").show();
        $("#home-page-note").removeClass("d-inline");
        $("#home-page-note").hide();


    }
    // Remove nulls
    for (var key in modal) {
        if (modal[key] == null) modal[key] = "";
    }

    $("#index_modal_update").val(index);

    var imageType, imageName, customImageName, customImageSrc;
    if (index == '') {
        $('#add-edit-title-text').html(lang.mycap_mobile_app_07);
        $('input[name=old_image]').val('');
        imageType = '.System';
        imageName = '';
        customImageName = '';
    } else {
        $('#add-edit-title-text').html(lang.mycap_mobile_app_08+' #' + (pageNum+1));
        imageType = modal['image-type'];
        if (imageType == ".System") {
            imageName = modal['system-image-name'];
            customImageName = '';
            customImageSrc = '';
        } else {
            imageName = '';
            customImageName = modal['custom-logo'];
            customImageSrc = modal['imgSrc'];
        }
    }

    //Add values
    if (modal['dag-id'] != 0) {
        $('select[name="dag_id"]').val(modal['dag-id']);
    } else {
        $('#dag_id option:first').prop('selected','selected');
    }
    $('input[name="page_title"]').val(modal['page-title']);
    $('textarea[name="page_content"]').val(modal['page-content']);
    $('input[name="image_type"][value="'+imageType+'"]').prop('checked',true);
    $('input[name=logo]').val('');

    setImageLayout(imageType, imageName, customImageName, customImageSrc);

    //clean up error messages
    $('#errMsgContainerModal').empty();
    $('#errMsgContainerModal').hide();

    //Show modal
    $('[name=external-modules-configure-modal]').modal('show');
}

/**
 * Function that set either "system" image selection dropdown or "custom" image upload UI
 * @param imageType, either system or custom
 * @param systemImageName, system image name
 * @param customImageName, custom image name
 * @param customImageSrc, custom image path
 */
function setImageLayout(imageType, systemImageName, customImageName, customImageSrc) {
    if (imageType == '.System') {
        // Select from existing system images
        $('select[name="system_image"] option[value="' + systemImageName + '"]').prop("selected", true)
        $('#system_image').change();

        $('#systemImageRow').show();
        $('#customImageRow').hide();
        $('#old_image_div').hide();
        $('#new_image_div').hide();
    } else {
        // Custom Image upload
        $('#systemImageRow').hide();
        $('#customImageRow').show();
        if (customImageName != '') {
            $('#old_image_div').show();
            $("#old_image").val(customImageName);
            $('#old_image_div').find("img").attr('src',customImageSrc);
            $('#new_image_div').hide()
        } else {
            $('#new_image_div').show();
        }
    }
}

/**
 * Function that checks if all required fields form the pages are filled @param errorContainer
 * @returns {boolean}
 */
function validatePageInfoForm()
{
    $('#succMsgContainer').hide();
    $('#errMsgContainerModal').empty();
    $('#errMsgContainerModal').hide();

    var errMsg = [];
    if ($('input[name=page_title]').val() == "") {
        errMsg.push(lang.mycap_mobile_app_22);
        $('input[name=page_title]').addClass('error-field');
    } else {
        $('input[name=page_title]').removeClass('error-field');
    }

    if ($('textarea[name=page_content]').val() == "") {
        errMsg.push(lang.mycap_mobile_app_23);
        $('textarea[name=page_content]').addClass('error-field');
    } else {
        $('textarea[name=page_content]').removeClass('error-field');
    }
    $('input[name=image_type]').each(function() {
        if ($(this).prop('checked') === true) {
            if ($(this).val() == '.Custom') {
                // Validate Custom Image upload
                var fileVal = $('input[name=logo]').val();
                if ($('input[name=old_image]').val() == ""
                    && fileVal == "") {
                    errMsg.push(lang.mycap_mobile_app_42);
                } else if (fileVal != "") {
                    var extension = getfileextension(fileVal);
                    extension = extension.toLowerCase();
                    if (extension != "jpeg" && extension != "jpg" && extension != "gif" && extension != "png" && extension != "bmp") {
                        errMsg.push(lang.mycap_mobile_app_43);
                    }
                }
            }
        }
    });

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

/**
 * Function that submits add/edit form
 * @param data
 * @param url
 * @param message
 */
function submitForm(data, url, message){

    $.ajax({
        type: "POST",
        url: url,
        data:  data,
        enctype: 'multipart/form-data',
        processData: false,
        contentType: false,
        dataType: "json",
        success: function(jsonAjax)
        {
            redirectToPage(jsonAjax, message);
        }
    });
}

/**
 * Function that redirects to the page and appends the message letter
 * @param jsonAjax
 * @param message
 */
function redirectToPage(jsonAjax, message) {
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

/**
 * Function that delete page
 * @param this_page_id, page id of page which is deleted
 * @param this_page_name, the page name
 */
function deleteAboutPage(this_page_id, this_page_name) {
    var delPageAjax = function(){
        var url = app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=deletePage';
        $.post(url, { page: this_page_id }, function(returnData){
            jsonAjax = jQuery.parseJSON(returnData);
            redirectToPage(jsonAjax, 'D');
        });
    };
    simpleDialog(lang.mycap_mobile_app_26+' "<b>'+this_page_name+'</b>"'+lang.questionmark,lang.mycap_mobile_app_25,null,null,null,lang.global_53,delPageAjax,lang.global_19);
}

/**
 * Function that move a page
 * @param page_id, page id of page which is moved
 */
function moveAboutPage(page_id) {
    // Get dialog content via ajax
    $.post(app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=movePage',{ page_id: JSON.stringify(page_id), param: 'view'},function(data){
        var json_data = jQuery.parseJSON(data);
        if (json_data.length < 1) {
            alert(woops);
            return false;
        }
        // Add dialog content and set dialog title
        $('#move_page_popup').html(json_data.payload);
        // Open the "move page" dialog
        $('#move_page_popup').dialog({ title: json_data.title, bgiframe: true, modal: true, width: 700, open: function(){fitDialog(this)},
            buttons: [
                { text: window.lang.global_53, click: function () { $(this).dialog('close'); } },
                { text: lang.mycap_mobile_app_33, click: function () {
                        // Make sure we have a field first
                        if ($('#move_after_page').val() == '') {
                            simpleDialog(pleaseSelectPage);
                            return;
                        }
                        // Save new position via ajax
                        showProgress(1);
                        $.post(app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=movePage',{ page_id: JSON.stringify(page_id), param: 'save', move_after_page: $('#move_after_page').val() },function(data){
                            $('#move_page_popup').dialog("close");
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

/**
 * Function that shows the modal with the page information to modify it
 * @param modal, array with the data from a specific link
 * @param index, the link id
 * @param linkNum, the link number
 */
function editLink(modal, index, linkNum)
{
    // Remove nulls
    for (var key in modal) {
        if (modal[key] == null) modal[key] = "";
    }

    $("#index_modal_update").val(index);

    if (index == '') {
        $('#add-edit-title-text').html(lang.mycap_mobile_app_47);
    } else {
        $('#add-edit-title-text').html(lang.mycap_mobile_app_54+' #' + (linkNum+1));
    }

    //Add values
    if (modal['dag-id'] != 0) {
        $('select[name="dag_id"]').val(modal['dag-id']);
    } else {
        $('#dag_id option:first').prop('selected','selected');
    }
    $('input[name="link_name"]').val(modal['link-name']);
    $('input[name="link_url"]').val(modal['link-url']);

    if (modal['append-project-code'] == '1') {
        $('input[name="append_project_code"]').prop('checked', true);
    } else {
        $('input[name="append_project_code"]').prop('checked', false);
    }

    if (modal['append-participant-code'] == '1') {
        $('input[name="append_participant_code"]').prop('checked', true);
    } else {
        $('input[name="append_participant_code"]').prop('checked', false);
    }
    $('input[name="selected_icon"]').val(modal['link-icon']);
    $(".link-icon").removeClass('selected');
    $(".link-icon").children( 'i.fa-check' ).hide();
    $('li[data-value=' + modal['link-icon'] + ']').addClass( 'selected' );
    $('li[data-value=' + modal['link-icon'] + ']').children( 'i.fa-check' ).show();
    //clean up error messages
    $('#errMsgContainerModal').empty();
    $('#errMsgContainerModal').hide();

    //Show modal
    $('[name=external-modules-configure-modal-link]').modal('show');
}

/**
 * Function that checks if all required fields form the pages are filled @param errorContainer
 * @returns {boolean}
 */
function validateLinkInfoForm()
{
    $('#succMsgContainer').hide();
    $('#errMsgContainerModal').empty();
    $('#errMsgContainerModal').hide();

    var errMsg = [];
    if ($('input[name=link_name]').val() == "") {
        errMsg.push(lang.mycap_mobile_app_56);
        $('input[name=link_name]').addClass('error-field');
    } else {
        $('input[name=link_name]').removeClass('error-field');
    }
    var linkURLElement = $('input[name=link_url]');
    if (linkURLElement.val() == "") {
        errMsg.push(lang.mycap_mobile_app_57);
        $('input[name=link_url]').addClass('error-field');
    } else {
        var pattern = new RegExp('^((https|http|ftp)?:\\/\\/)?'+ // protocol
            '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|'+ // domain name
            '((\\d{1,3}\\.){3}\\d{1,3}))'+ // OR ip (v4) address
            '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*'+ // port and path
            '(\\?[;&a-z\\d%_.~+=-]*)?'+ // query string
            '(\\#[-a-z\\d_]*)?$','i'); // fragment locator
        if(pattern.test(linkURLElement.val()) == false){
            errMsg.push(lang.mycap_mobile_app_65);
            $('input[name=link_url]').addClass('error-field');
        } else {
            $('input[name=link_url]').removeClass('error-field');
        }
    }

    if ($('input[name=selected_icon]').val() == "") {
        errMsg.push(lang.mycap_mobile_app_58);
        $('input[name=selected_icon]').addClass('error-field');
    } else {
        $('input[name=selected_icon]').removeClass('error-field');
    }

    if (errMsg.length > 0) {
        $('#errMsgContainerModal').empty();
        $.each(errMsg, function (i, e) {
            $('#errMsgContainerModal').append('<div>' + e + '</div>');
        });
        $('#errMsgContainerModal').show();
        $('html,body').scrollTop(0);
        $('[name=external-modules-configure-modal-link]').scrollTop(0);
        return false;
    }
    else {
        return true;
    }
}

/**
 * Function that delete link
 * @param this_link_id, link id of link which is deleted
 * @param this_link_name, the link name
 */
function deleteLink(this_link_id, this_link_name) {
    var delLinkAjax = function(){
        var url = app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=deleteLink';
        $.post(url, { link: this_link_id }, function(returnData){
            jsonAjax = jQuery.parseJSON(returnData);
            redirectToPage(jsonAjax, 'DL');
        });
    };
    simpleDialog(lang.mycap_mobile_app_63+' "<b>'+this_link_name+'</b>"'+lang.questionmark,lang.mycap_mobile_app_64,null,null,null,lang.global_53,delLinkAjax,lang.global_19);
}

/**
 * Function that shows the modal with the contact information to modify it
 * @param modal, array with the data from a specific contact
 * @param index, the contact id
 * @param contactNum, the contact number
 */
function editContact(modal, index, contactNum)
{
    $('input, textarea').removeClass('error-field');
    // Remove nulls
    for (var key in modal) {
        if (modal[key] == null) modal[key] = "";
    }

    $("#index_modal_update").val(index);

    if (index == '') {
        $('#add-edit-title-text').html(lang.mycap_mobile_app_72);
    } else {
        $('#add-edit-title-text').html(lang.mycap_mobile_app_73+' #' + (contactNum+1));
    }

    //Add values
    if (modal['dag-id'] != 0) {
        $('select[name="dag_id"]').val(modal['dag-id']);
    } else {
        $('#dag_id option:first').prop('selected','selected');
    }
    $('input[name="header"]').val(modal['contact-header']);
    $('input[name="title"]').val(modal['contact-title']);
    $('input[name="phone"]').val(modal['phone-number']);
    $('input[name="email"]').val(modal['email']);
    $('input[name="weburl"]').val(modal['website']);
    $('textarea[name="info"]').val(modal['additional-info']);

    //clean up error messages
    $('#errMsgContainerModal').empty();
    $('#errMsgContainerModal').hide();

    //Show modal
    $('[name=external-modules-configure-modal-contact]').modal('show');
}

/**
 * Function that checks if all required fields form the contacts are filled
 * @returns {boolean}
 */
function validateContactInfoForm()
{
    $('#succMsgContainer').hide();
    $('#errMsgContainerModal').empty();
    $('#errMsgContainerModal').hide();

    var errMsg = [];
    if ($('input[name=header]').val() == "") {
        errMsg.push(lang.mycap_mobile_app_74);
        $('input[name=header]').addClass('error-field');
    } else {
        $('input[name=header]').removeClass('error-field');
    }

    if (errMsg.length > 0) {
        $('#errMsgContainerModal').empty();
        $.each(errMsg, function (i, e) {
            $('#errMsgContainerModal').append('<div>' + e + '</div>');
        });
        $('#errMsgContainerModal').show();
        $('html,body').scrollTop(0);
        $('[name=external-modules-configure-modal-contact]').scrollTop(0);
        return false;
    }
    else {
        return true;
    }
}

/**
 * Function that delete contact
 * @param this_contact_id, contact id of contact which is deleted
 * @param this_contact_name, the contact name
 */
function deleteContact(this_contact_id, this_contact_name) {
    var delContactAjax = function(){
        var url = app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=deleteContact';
        $.post(url, { contact: this_contact_id }, function(returnData){
            jsonAjax = jQuery.parseJSON(returnData);
            redirectToPage(jsonAjax, 'DC');
        });
    };
    simpleDialog(lang.mycap_mobile_app_75+' "<b>'+this_contact_name+'</b>"'+lang.questionmark,lang.mycap_mobile_app_76,null,null,null, lang.global_53, delContactAjax, lang.global_19);
}

/**
 * Function that enable contact list table
 */
function enableContactListTable() {
    // Add dragHandle to first cell in each row
    $("table#table-contacts_list tr").each(function() {
        var contact_id = trim($(this.cells[0]).text());
        $(this).prop("id", "contactrow_"+contact_id).attr("contactid", contact_id);
        if (isNumeric(contact_id)) {
            // User-defined contacts (draggable)
            $(this.cells[0]).addClass('dragHandle');
        } else {
            $(this).addClass("nodrop").addClass("nodrag");
        }
    });
    // Restripe the contact list rows
    restripeContactListRows();
    if ($('[id^=contactrow_]').length > 2) {
        // Enable drag n drop
        $('table#table-contacts_list').tableDnD({
            onDrop: function(table, row) {
                // Loop through table
                var ids = "";
                var this_id = $(row).prop('id');
                $("table#table-contacts_list tr").each(function() {
                    // Gather form_names
                    var row_id = $(this).attr("contactid");
                    if (isNumeric(row_id)) {
                        ids += row_id + ",";
                    }
                });
                // Save new order via ajax
                $.post(app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=reorderContact', { contact_ids: ids }, function(returnData) {
                    jsonAjax = jQuery.parseJSON(returnData);
                    redirectToPage(jsonAjax, 'MC');
                });
                // Reset contact order numbers in contact list table
                resetContactOrderNumsInTable();
                // Restripe table rows
                restripeContactListRows();
                // Highlight row
                setTimeout(function(){
                    var i = 1;
                    $('tr#'+this_id+' td').each(function(){
                        if (i++ != 1) $(this).effect('highlight',{},2000);
                    });
                },100);
            },
            dragHandle: "dragHandle"
        });
    }

    // Create mouseover image for drag-n-drop action and enable button fading on row hover
    $("table#table-contacts_list tr:not(.nodrag)").mouseenter(function() {
        $(this.cells[0]).css('background','#ffffff url("'+app_path_images+'updown.gif") no-repeat center');
        $(this.cells[0]).css('cursor','move');
    }).mouseleave(function() {
        $(this.cells[0]).css('background','');
        $(this.cells[0]).css('cursor','');
    });
    // Set up drag-n-drop pop-up tooltip
    var first_hdr = $('#contacts_list .hDiv .hDivBox th:first');
    first_hdr.prop('title',lang.mycap_mobile_app_66);
    first_hdr.tooltip2({ tipClass: 'tooltip4sm', position: 'top center', offset: [25,0], predelay: 100, delay: 0, effect: 'fade' });
    $('.dragHandle').mouseenter(function() {
        first_hdr.trigger('mouseover');
    }).mouseleave(function() {
        first_hdr.trigger('mouseout');
    });
}

/**
 * Function that restripe the rows of the contact list table
 */
function restripeContactListRows() {
    var i = 1;
    $("table#table-contacts_list tr").each(function() {
        // Restripe table
        if (i++ % 2 == 0) {
            $(this).addClass('erow');
        } else {
            $(this).removeClass('erow');
        }
    });
}

/**
 * Function that Reset contact order numbers in contact list table
 */
function resetContactOrderNumsInTable() {
    var i = 1;
    $("table#table-contacts_list tr:not(.nodrag)").each(function(){
        $(this).find('td:eq(1) div').html(i++);
    });
}

/**
 * Function that saves a theme
 * @param formId, form id to identify which is theme system/custom
 */
function saveThemeForm(formId) {
    var data = new FormData(document.getElementById("form_theme_"+formId));
    submitForm(data,app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=saveTheme', "UT");
    return false;
}

/**
 * Function that display publish config confirm dialog
 * @param content, content of dialog box
 * @param showDraftModeWarning, check if project is in PROD mode and not entered in draft mode
 */
function publishConfigConfirm(content, showDraftModeWarning) {
    showProgress(1);
    $.get(app_path_webroot + 'MyCap/tasks_issues.php?pid='+pid,
        function(data) {
            if (data != '' && showDraftModeWarning == 1) {
                simpleDialog(lang.mycap_mobile_app_843, lang.global_48 + lang.colon+' '+lang.mycap_mobile_app_844);
            } else {
                content += data;
                showProgress(0);
                simpleDialog(content, lang.mycap_mobile_app_92+lang.questionmark, null, 520, null, lang.global_53, function(){
                    publishMyCapVersion();
                }, lang.mycap_mobile_app_95);
            }
        }
    );
    showProgress(0);
}

/**
 * Function that publish config
 */
function publishMyCapVersion() {
    showProgress(1);

    $.post(app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=publishVersion', {}, function(data){
        var json_data = jQuery.parseJSON(data);
        var msg = $('div.versionPublishMsg').html();
        if (json_data.status == 'success' || json_data.status == 'warning') {
            var newVersion = parseInt($("#versionNum").html()) + 1;
            $("#versionNum").html(newVersion);
            $('.mycap-config-outofdate').addClass('invisible');
        }
        if (json_data.status == 'warning') {
            msg += '<br /><br />' + langWarningTasksWithErrors;
        }
        showProgress(0,0);
        simpleDialog(msg, langNewFormRights2, null, 650, "showProgress(1);window.location.reload();", "Close");
    });
}

/**
 * Function that displays setup participant labels popup
 */
function displaySetUpParticpantLablesPopup() {
    showProgress(1,0);
    // Id of dialog
    var dlgid = 'participantIdentifier_dialog';
    // Get content via ajax
    $.post(app_path_webroot+'MyCap/participant_info.php?action=setIdentifier&pid='+pid,{ }, function(data) {
        showProgress(0,0);
        if (data == "0") {
            alert(woops);
            return;
        }
        // Decode JSON
        var json_data = JSON.parse(data);
        // Add html
        initDialog(dlgid);
        $('#'+dlgid).html(json_data.content);
        // Display dialog
        $('#'+dlgid).dialog({ title: json_data.title, bgiframe: true, modal: true, width: 800, open:function(){ fitDialog(this); }, close:function(){ $(this).dialog('destroy'); },
            buttons: [{ text: window.lang.global_53, click: function(){ $(this).dialog('close'); } },
                      { text: window.lang.pub_085, click: function(){ saveParticipantIdentifier(); }
                     }]
        });
        $('#'+dlgid).parent().find('div.ui-dialog-buttonpane button:eq(1)').css({'font-weight':'bold','color':'#222'});
        // Init buttons
        initButtonWidgets();
    });
}

/**
 * Save the participant identifier for project
 */
function saveParticipantIdentifier() {
    var json_ob = $('form#setuplabelsform').serializeObject();
    json_ob.action = 'setparticipantid';
    // Save via ajax
    $.post(app_path_webroot+'ProjectGeneral/edit_project_settings.php?pid='+pid, json_ob,function(data){
        if (data=='[]') alert(woops);
        else {
            var json_data = jQuery.parseJSON(data);
            if ($('#participantIdentifier_dialog').hasClass('ui-dialog-content')) $('#participantIdentifier_dialog').dialog('destroy');
            simpleDialog(json_data.content, json_data.title, null, 500, "showProgress(1);window.location.reload();");
            setTimeout("showProgress(1);window.location.reload();", 2000);
        }
    });
}

/**
 * Generate a participant QR Code and open dialog window
 * @param record, current participants record
 */
function getQRCode(record, eventId) {
    if (eventId == undefined) {
        eventId = "";
    }
    showProgress(1,0);
    // Id of dialog
    var dlgid = 'genQR_dialog';
    // Get content via ajax
    $.post(app_path_webroot+'MyCap/participant_info.php?pid='+pid+'&record='+record+'&event_id='+eventId,{ }, function(data) {
        showProgress(0,0);
        if (data == "0") {
            alert(woops);
            return;
        }
        // Decode JSON
        var json_data = JSON.parse(data);
        // Add html
        initDialog(dlgid);
        $('#'+dlgid).html(json_data.content);
        // If QR codes are not being displayed, then make the dialog less wide
        var dwidth = ($('#'+dlgid+' #qrcode-info').length) ? 900 : 600;
        // Display dialog
        $('#'+dlgid).dialog({ title: json_data.title, bgiframe: true, modal: true, width: dwidth, open:function(){ fitDialog(this); }, close:function(){ $(this).dialog('destroy'); },
            buttons: [{
                text: window.lang.calendar_popup_01, click: function(){ $(this).dialog('close'); }
            }]
        });
        // Init buttons
        initButtonWidgets();
    });
}

/**
 * Open a popup to copy html message to send via alerts and notification
 * @param record, current participants record
 * @param template_type, possible values are qr, link or both
 */
function openEmailTemplatePopup(record, eventId, template_type) {
    showProgress(1,0);
    // Id of dialog
    var dlgid = 'messageQR_dialog';
    // Get content via ajax
    $.post(app_path_webroot+'MyCap/participant_info.php?action=getHTML&pid='+pid+'&record='+record+'&event_id='+eventId+'&type='+template_type,{ }, function(data){
        showProgress(0,0);
        if (data == "0") {
            alert(woops);
            return;
        }
        // Decode JSON
        var json_data = JSON.parse(data);
        // Add html
        initDialog(dlgid);
        $('#'+dlgid).html(json_data.content);
        // If QR codes are not being displayed, then make the dialog less wide
        var dwidth = 900;
        // Display dialog
        $('#'+dlgid).dialog({ title: json_data.title, bgiframe: true, modal: true, width: dwidth, open:function(){ fitDialog(this); }, close:function(){ $(this).dialog('destroy'); },
            buttons: [{
                text: window.lang.calendar_popup_01, click: function(){ $(this).dialog('close'); }
            }]
        });
        // Init buttons
        initButtonWidgets();
    });
}

/**
 * Open Disable/Enable a participant popup box
 * @param record, current participants record
 * @param part_id, participant ID
 * @param flag, possible values are disable or enable
 * @param joined, is participant joined from app or not
 */
function openMyCapParticipantStatus(record, part_id, flag, joined) {
    showProgress(1,0);
    $.post(app_path_webroot+'MyCap/participant_info.php?action=getChangeStatusHTML&pid='+pid+'&record='+record+'&part_id='+part_id+'&flag='+flag+'&joined='+joined,{ }, function(data){
        showProgress(0,0);
        if (data=='[]') alert(woops);
        else {
            var json_data = jQuery.parseJSON(data);
            // Open dialog
            initDialog('MyCapParticipantStatusDialog');
            $('#MyCapParticipantStatusDialog').html(json_data.content);
            isPopupOpened = true;

            var buttonText = window.lang.control_center_153;
            if (flag == 'enable') {
                buttonText = window.lang.survey_152;
            }
            $('#MyCapParticipantStatusDialog').dialog({ title: json_data.title, bgiframe: true, modal: true, width: 720, open:function(){fitDialog(this);}, buttons: [
                    { text: lang.global_53, click: function () { isPopupOpened = false; $(this).dialog('destroy'); } },
                    { text: buttonText, click: function () {
                            // Validate form
                            if (!validationChangeStatusForm()) return false;
                            $(this).dialog('destroy');
                            updateParticipantStatusDo(record, part_id, flag)
                        }
                    }]
            });
        }
    });
}

/* Validate Message value in enable/disable participant */
function validationChangeStatusForm() {
    if ($('#notify_participant').is(':checked')) {
        if ($('#par-message-box textarea').val() == '') {
            isValid = false;
            simpleDialog(lang.mycap_mobile_app_429, lang.global_01,null,null);
            return false;
        }
    }
    return true;
}

/**
 * Disable/Enable participant from list
 * @param record, current participants record
 * @param part_id, participant ID
 * @param flag, possible values are disable or enable
 */
function updateParticipantStatusDo(record, part_id, flag) {
    $.post(app_path_webroot + 'MyCapMobileApp/update.php?pid=' + pid + '&action=updateParStatus&flag=' + flag, {
        participant_id: part_id,
        record: record,
        notify_participant: ($('#notify_participant').is(':checked') ? 1 : 0),
        message: $('#par-message-box textarea').val()
    }, function (returnData) {
        var json_data = jQuery.parseJSON(returnData);
        var title = window.lang.setup_08;
        var content = '';
        if (json_data.status == 'disabled') {
            content = window.lang.mycap_mobile_app_935;
        } else if (json_data.status == 'enabled') {
            content = window.lang.mycap_mobile_app_936;
        } else if (json_data.status == 'disablednotified') {
            content = window.lang.mycap_mobile_app_935 + ' ' + window.lang.mycap_mobile_app_937;
        } else if (json_data.status == 'enablednotified') {
            content = window.lang.mycap_mobile_app_936 + ' ' + window.lang.mycap_mobile_app_937;
        }
        simpleDialog(content, title, null, 600, "showProgress(1);window.location.reload();");
        setTimeout("showProgress(1);window.location.reload();", 2000);
    });
}

/**
 * Reload the Participants for another "page" when paging
 * @param pagenum, page number
 */
function loadParticipantList(pagenum) {
    showProgress(1);
    var filterBBTime = '';
    if ($('#filterBBeginTime').val() != undefined) {
        filterBBTime = $('#filterBBeginTime').val();
    }
    var filterBETime = '';
    if ($('#filterBEndTime').val() != undefined) {
        filterBETime = $('#filterBEndTime').val();
    }
    window.location.href = app_path_webroot+'MyCapMobileApp/index.php?participants=1&pid='+pid+'&pagenum='+pagenum+
        '&filterIBeginTime='+$('#filterIBeginTime').val()+'&filterIEndTime='+$('#filterIEndTime').val()+'&filterBBeginTime='+filterBBTime+'&filterBEndTime='+filterBETime+'&filterRecord='+$('#filterRecord').val()+'&filterParticipant='+$('#filterParticipant').val();
}

/**
 * Display the pop-up for setting up allow participants logic condition
 */
function displayParticipantsLogicPopup() {
    showProgress(1,0);
    $.post(app_path_webroot+'MyCapMobileApp/participants_allow_logic_setup.php?pid='+pid,{action: 'view'},function(data){
        showProgress(0,0);
        if (data=='[]') alert(woops);
        else {
            var json_data = jQuery.parseJSON(data);
            // Open dialog
            initDialog('LogicSetupDialog');
            $('#LogicSetupDialog').html(json_data.content);

            isPopupOpened = true;

            $('#LogicSetupDialog').dialog({ title: json_data.title, bgiframe: true, modal: true, width: 920, open:function(){fitDialog(this);}, buttons: [
                    { text: lang.global_53, click: function () { isPopupOpened = false; $(this).dialog('destroy'); } },
                    { text: lang.designate_forms_13, click: function () {
                            saveAllowParticipantLogic();
                        }
                    }]
            });
        }
    });
}

/**
 * Save the values in the pop-up when setting up of allow participant Logic
 */
function saveAllowParticipantLogic() {
    var json_ob = $('form#LogicForm').serializeObject();
    json_ob.action = 'save';
    // Save via ajax
    $.post(app_path_webroot+'MyCapMobileApp/participants_allow_logic_setup.php?pid='+pid, json_ob,function(data){
        if (data=='[]') alert(woops);
        else {
            var json_data = jQuery.parseJSON(data);
            if ($('#LogicSetupDialog').hasClass('ui-dialog-content')) $('#LogicSetupDialog').dialog('destroy');
            simpleDialog(json_data.content, json_data.title, null, 600, "showProgress(1);window.location.reload();");
            setTimeout("showProgress(1);window.location.reload();", 2000);
        }
    });
}

/**
 * Open print window when clicked on "Print for participant" button
 */
function printQRCode(record) {
    window.open(app_path_webroot+'ProjectGeneral/print_page.php?pid='+pid+'&action=mycapqrcode&record='+record,'myWin','width=850, height=600, toolbar=0, menubar=1, location=0, status=0, scrollbars=1, resizable=1');
}

/**
 * Function that shows the modal with the announcement to modify it
 * @param modal, array with the data from a specific announcement
 * @param index, the announcement id
 * @param annNum, the announcement number
 */
function editAnnouncement(modal, index, annNum)
{
    $('input, textarea').removeClass('error-field');
    // Remove nulls
    for (var key in modal) {
        if (modal[key] == null) modal[key] = "";
    }

    $("#index_modal_update").val(index);

    if (index == '') { // Add Form
        $('#add-edit-title-text').html(lang.mycap_mobile_app_422);
        $('#removeButton, #warningBox').hide();
        REDCapMessege.toggleBodyRichText(false);
        $('textarea[name="announcement_msg"]').val('');
    } else { // Edit Form
        $('#add-edit-title-text').html(lang.mycap_mobile_app_423);
        $('#removeButton, #warningBox').show();
        if (REDCapMessege.isRichTextBody(modal['body'])) {
            REDCapMessege.initBody(modal['body']);
        } else {
            REDCapMessege.toggleBodyRichText(false);
            $('textarea[name="announcement_msg"]').val(modal['body']);
        }
    }
    //Add values
    $('input[name="title"]').val(modal['title']);

    //clean up error messages
    $('#errMsgContainerModal').empty();
    $('#errMsgContainerModal').hide();

    //Show modal
    $('[name=external-modules-configure-modal-ann]').modal('show');
}

/**
 * Function that checks if all required fields form the announcement are filled
 * @returns {boolean}
 */
function validateAnnouncementInfoForm()
{
    $('#succMsgContainer').hide();
    $('#errMsgContainerModal').empty();
    $('#errMsgContainerModal').hide();

    var errMsg = [];

    var editor_text = "";
    if (REDCapMessege.isBodyRichTextChecked()) {
        var editor_text = tinymce.activeEditor.getContent();
    }

    if (editor_text == "") {
        if ($('textarea[name=announcement_msg]').val() == "") {
            errMsg.push(lang.mycap_mobile_app_429);
        } else {
            $('textarea[name=announcement_msg]').removeClass('error-field');
        }
    } else {
        editor_text = REDCapMessege.updateBodyBeforeFormSubmit();
        $('textarea[name=announcement_msg]').val(editor_text);
    }

    if (errMsg.length > 0) {
        $('#errMsgContainerModal').empty();
        $.each(errMsg, function (i, e) {
            $('#errMsgContainerModal').append('<div>' + e + '</div>');
        });
        $('#errMsgContainerModal').show();
        $('html,body').scrollTop(0);
        $('[name=external-modules-configure-modal-ann]').scrollTop(0);
        return false;
    }
    else {
        return true;
    }
}

/**
 * Reload the Announcements for another "page" when paging
 * @param pagenum, page number
 */
function loadAnnouncementList(pagenum) {
    showProgress(1);
    window.location.href = app_path_webroot+'MyCapMobileApp/index.php?announcements=1&pid='+pid+'&pagenum='+pagenum;
}

/**
 * Reload the Inbox Messages for another "page" when paging
 * @param pagenum, page number
 */
function loadInboxMessagesList(pagenum) {
    showProgress(1);
    window.location.href = app_path_webroot+'MyCapMobileApp/index.php?messages=1&pid='+pid+'&pagenum='+pagenum+
        '&filterBeginTime='+$('#filterBeginTime').val()+'&filterEndTime='+$('#filterEndTime').val()+'&filterParticipant='+$('#filterParticipant').val();
}

/**
 * Reload the Sent Messages for another "page" when paging
 * @param pagenum, page number
 */
function loadOutboxMessagesList(pagenum) {
    showProgress(1);
    window.location.href = app_path_webroot+'MyCapMobileApp/index.php?outbox=1&pid='+pid+'&pagenum='+pagenum+
        '&filterBeginTime='+$('#filterBeginTime').val()+'&filterEndTime='+$('#filterEndTime').val()+'&filterUser='+$('#filterUser').val()+'&filterParticipant='+$('#filterParticipant').val();
}

/**
 * Open popup to display messages history of selected participant
 * @param participantCode, Participant Code
 * @param messageId, Message to hightlight
 */
function openMessagesHistory(participantCode, messageId = "") {
    showProgress(1,0);
    $.post(app_path_webroot+'MyCapMobileApp/messaging.php?pid='+pid,{action: 'view', participantCode: participantCode},function(data){
        showProgress(0,0);
        if (data=='[]') alert(woops);
        else {
            var json_data = jQuery.parseJSON(data);
            // Open dialog
            initDialog('MessagesHistoryDialog');
            $('#MessagesHistoryDialog').html(json_data.content);
            isPopupOpened = true;

            $('#MessagesHistoryDialog').dialog({ title: json_data.title, bgiframe: true, modal: true, width: 920, open:function() {
                    REDCapMessege.toggleBodyRichText(false);
                    fitDialog(this);
                    if (messageId != '') {
                        // Highlight Message block
                        var scrollPos = $(window).scrollTop();
                        $(this).animate({ scrollTop: ($('div#message_'+messageId).offset().top - (scrollPos + 150)) }, "normal");
                        $('#messageTimeline div#message_'+messageId+':visible').effect('highlight',{},4000);
                    }
                }, close:function(){ isPopupOpened = false; $(this).dialog('destroy'); window.location.reload(); }, buttons: [{ text: lang.calendar_popup_01, click: function () { isPopupOpened = false; $(this).dialog('destroy'); window.location.reload(); } }]
            });
        }
    });
}

/**
 * Send New message to participant
 * @param this_participant, Participant Identifier
 */
function sendNewMessage(this_participant) {
    var body = $('#body').val();
    var editor_text = "";
    if (REDCapMessege.isBodyRichTextChecked()) {
        editor_text = tinymce.activeEditor.getContent();
    }

    if (editor_text == "") {
        if ($.trim(body) == "") {
            simpleDialog(lang.mycap_mobile_app_439, lang.global_01);
            return;
        } else {
            $('#body').removeClass('error-field');
        }
    } else {
        $('#body').val(editor_text);
    }

    var sendNewMessageAjax = function(){
        showProgress(1);
        var json_ob = $('form#newMessageForm').serializeObject();
        json_ob.action = 'send';
        // Save via ajax
        $.post(app_path_webroot+'MyCapMobileApp/messaging.php?pid='+pid, json_ob,function(data){
            if (data == '0' || data == '[]') {
                showProgress(0,0);
                alert(woops);
            }
            else {
                var json_data = jQuery.parseJSON(data);
                if (json_data.liHTML != '') {
                    var li = $("ul#messageTimeline li:last-child");
                    li.before(json_data.resultHtml);
                    li.prev().slideDown();
                    REDCapMessege.toggleBodyRichText(false);
                    $('#body').val('');
                }
                showProgress(0);
            }
        });
    };
    simpleDialog(lang.mycap_mobile_app_438+" <b>"+this_participant+"</b>"+lang.questionmark, lang.mycap_mobile_app_440,null,null,null,lang.global_53, sendNewMessageAjax, lang.survey_180);
}

/**
 * Process Action Needed paramter for message
 * @param obj, checkbox element indicating action needed
 * @param messageId, message for which action needed is processing
 */
function processActionNeeded(obj, messageId) {
    if (messageId != '') {
        var isActionNeeded = $(obj).prop('checked');
        showProgress(1);
        $.post(app_path_webroot+'MyCapMobileApp/messaging.php?pid='+pid, { action: 'saveActionNeeded', message_id: messageId, is_action_needed: isActionNeeded }, function(data) {
            var json_data = jQuery.parseJSON(data);
            if (json_data.resultHtml != '') {
                $(obj).next().after(json_data.resultHtml);

                if($('#action_needed_'+messageId).length) { // This element only exists on inbox messages listing row under "Action Needed?" column
                    var isActionNeededText = lang.design_100;
                    if (isActionNeeded == false) {
                        isActionNeededText = lang.design_99;
                    }
                    $('#action_needed_'+messageId).html(isActionNeededText);
                }
                setTimeout(function(){
                    $('#saveStatus').remove();
                },1500);
            }
        });
        showProgress(0);
    }
}

/**
 * Process Action Needed paramter for message
 * @param this_announcement_id, announcement to remove
 */
function removeAnnouncement(this_announcement_id) {
    var url = app_path_webroot+'MyCapMobileApp/messaging.php?pid='+pid+'&action=removeAnnouncement';
    $.post(url, { announcememt_id: this_announcement_id }, function(returnData){
        jsonAjax = jQuery.parseJSON(returnData);
        redirectToPage(jsonAjax, 'DA');
    });
}

/**
 * Display the pop-up for setting up baseline date settings
 */
function displayMyCapAdditionalSettingsPopup() {
    showProgress(1,0);
    $.post(app_path_webroot+'MyCapMobileApp/mycap_additional_setup.php?pid='+pid,{action: 'view'},function(data){
        showProgress(0,0);
        if (data=='[]') alert(woops);
        else {
            var json_data = jQuery.parseJSON(data);
            // Open dialog
            initDialog('AdditionalSetupDialog');
            $('#AdditionalSetupDialog').html(json_data.content);

            isPopupOpened = true;

            // Exclude other elements from additional popup so that it will include only baseline date settings
            var origForm = $("#MyCapAdditionalSetting").find(":not(#id, #label, #none, #task_completion_status, #baseline_date_field, #prevent_lang_switch_mtb)").serialize();
            $('#AdditionalSetupDialog').dialog({ title: json_data.title, bgiframe: true, modal: true, width: 920, open:function(){fitDialog(this); initBaselineSettings();}, buttons: [
                    { text: lang.global_53, click: function () { isPopupOpened = false; $(this).dialog('destroy'); } },
                    { text: lang.folders_11, click: function () {
                            // Validate form
                            if (validationBaselineDateSetupForm()) return false;

                            // If form values are updated, get confirmation from user
                            if ($("#MyCapAdditionalSetting").find(":not(#id, #label, #none, #task_completion_status, #baseline_date_field, #prevent_lang_switch_mtb)").serialize() !== origForm) {
                                simpleDialog(lang.mycap_mobile_app_889, lang.mycap_mobile_app_594,null,null,null, window.lang.global_53,
                                    "saveAdditionalSettings();", lang.folders_11);
                            } else {
                                saveAdditionalSettings();
                            }
                        }
                    }]
            });
        }
    });
}

/**
 * Initialize js for baseline date settings pop-up
 */
function initBaselineSettings() {
    $('#use_baseline_chk').click(function(){
        if ($(this).prop('checked')) {
            $('#baseline_date_id_div').fadeTo('slow', 1);
            $('#baseline_date_field').prop('disabled', false);
            $('#div_baseline_settings, #div_include_instructions, #div_baseline_settings_title, #div_include_instructions_title').show('fade',function() {

                if ($('#include_instructions_chk').prop('checked')) {
                    $('#div_instruction_steps').show();
                }
                // Try to reposition each dialog (depending on which page we're on)
                if ($('#BaselineDateSetupDialog').length) {
                    fitDialog($('#BaselineDateSetupDialog'));
                    $('#BaselineDateSetupDialog').dialog('option','position','center');
                }
            });
        } else {
            $('#baseline_date_id_div').fadeTo('fast', 0.3);
            $('#baseline_date_field').prop('disabled', true);
            $('#div_baseline_settings, #div_include_instructions, #div_baseline_settings_title, #div_include_instructions_title').hide('fade',{ },200);
            if ($('#div_instruction_steps').length) {
                $('#div_instruction_steps').hide('fade',{ },200);
            }
        }
    });

    $('#include_instructions_chk').click(function(){
        if ($(this).prop('checked')) {
            $('#div_instruction_steps').show('fade',function() {
                // Try to reposition each dialog (depending on which page we're on)
                if ($('#BaselineDateSetupDialog').length) {
                    fitDialog($('#BaselineDateSetupDialog'));
                    $('#BaselineDateSetupDialog').dialog('option','position','center');
                }
            });
        } else {
            $('#div_instruction_steps').hide('fade',{ },200);
            $('input[name=instruction_title]').val('');
            $('textarea[name=instruction_content]').val('');
        }
    });
}

/**
 * Validate Baseline Date setup form
 */
function validationBaselineDateSetupForm() {
    // Make sure all visible fields have a value
    var errMsg = [];
    var errFieldLabel = [];
    var found = 0;
    if ($('#use_baseline_chk').prop('checked')) {
        var isMultiArm = false;
        // baseline date field is an array and can be one or more select boxes as baseline dates will be set per arm now
        $('select.baseline-field').each(function() {
            if($(this).attr('name') == 'baseline_date_field[]') {
                isMultiArm = true;
            }
            if ($(this).find('option:selected').val() != "") {
                found = 1;
            }
        });

        if (found == 0) {
            if (isMultiArm == true) {
                errMsg.push(lang.mycap_mobile_app_884);
            } else {
                errMsg.push(lang.mycap_mobile_app_480);
            }
            $('.baseline-field').addClass('error-field');
        } else {
            $('.baseline-field').removeClass('error-field');
        }

        if ($('input[name=title]').val() == "") {
            errFieldLabel.push(lang.mycap_mobile_app_108);
            $('input[name=title]').addClass('error-field');
        } else {
            $('input[name=title]').removeClass('error-field');
        }

        if ($('input[name=yesnoquestion]').val() == "") {
            errFieldLabel.push(lang.mycap_mobile_app_457);
            $('input[name=yesnoquestion]').addClass('error-field');
        } else {
            $('input[name=yesnoquestion]').removeClass('error-field');
        }

        if ($('input[name=datequestion]').val() == "") {
            errFieldLabel.push(lang.mycap_mobile_app_458);
            $('input[name=datequestion]').addClass('error-field');
        } else {
            $('input[name=datequestion]').removeClass('error-field');
        }

        if ($('#include_instructions_chk').prop('checked')) {
            if ($('input[name=instruction_title]').val() == "") {
                errFieldLabel.push(lang.mycap_mobile_app_481);
                $('input[name=instruction_title]').addClass('error-field');
            } else {
                $('input[name=instruction_title]').removeClass('error-field');
            }

            if ($('textarea[name=instruction_content]').val() == "") {
                errFieldLabel.push(lang.mycap_mobile_app_482);
                $('textarea[name=instruction_content]').addClass('error-field');
            } else {
                $('textarea[name=instruction_content]').removeClass('error-field');
            }
        }
    }
    if (errMsg.length > 0 || errFieldLabel.length > 0) {
        $('#errMsgContainerModal').empty();
        $('#errMsgContainerModal').append('<div>');
        if (errMsg.length > 0) {
            $.each(errMsg, function (i, e) {
                $('#errMsgContainerModal').append(e);
            });
        }
        if (errFieldLabel.length > 0) {
            var fieldList = errFieldLabel.join(", ");
            $('#errMsgContainerModal').append(" "+lang.create_project_20+" "+fieldList);
        }
        $('#errMsgContainerModal').append('</div>');
        $('#errMsgContainerModal').show();
        $('html,body').scrollTop(0);
        return true;
    } else {
        return false;
    }
}


/**
 * Function that saves baseline date settings to database via ajax call
 */
function saveAdditionalSettings() {
    var json_ob = $('form#MyCapAdditionalSetting').serializeObject();
    json_ob.action = 'save';
    $.post(app_path_webroot+'MyCapMobileApp/mycap_additional_setup.php?pid='+pid,json_ob,function(data) {
        var json_data = jQuery.parseJSON(data);
        if ($('#AdditionalSetupDialog').hasClass('ui-dialog-content')) $('#AdditionalSetupDialog').dialog('destroy');
        simpleDialog(json_data.content, json_data.title, null, 600, "showProgress(1);window.location.reload();");
        setTimeout("showProgress(1);window.location.reload();", 2000);
    });
}

/**
 * Open popup to display sync issue details
 * @param projectCode, Project Code
 * @param participantCode, Participant Code
 * @param issueId, Issue to hightlight
 */
function openSyncIssueDetails(projectCode, participantCode, issueId) {
    showProgress(1,0);
    $.post(app_path_webroot+'MyCapMobileApp/sync_issues.php?pid='+pid,{action: 'view', projectCode: projectCode, participantCode: participantCode, issueId: issueId},function(data){
        showProgress(0,0);
        if (data=='[]') alert(woops);
        else {
            var json_data = jQuery.parseJSON(data);
            // Open dialog
            initDialog('SyncIssueDialog');
            $('#SyncIssueDialog').html(json_data.content);
            isPopupOpened = true;

            $('#SyncIssueDialog').dialog({ title: json_data.title, bgiframe: true, modal: true, width: 1000, open:function() {
                    fitDialog(this);
                }, buttons: [{ text: lang.global_53, click: function () { isPopupOpened = false; $(this).dialog('destroy'); } },
                    { text: lang.pub_085, click: function(){ saveResolution(); }
                    }]
            });
        }
    });
}

/**
 * Save the resolution status and comment for sync issue
 */
function saveResolution() {
    showProgress(1);
    var json_ob = $('form#SyncIssueSetupForm').serializeObject();
    json_ob.action = 'save';
    // Save via ajax
    $.post(app_path_webroot+'MyCapMobileApp/sync_issues.php?pid='+pid, json_ob,function(data){
        showProgress(0);
        if (data=='[]') alert(woops);
        else {
            var json_data = jQuery.parseJSON(data);
            if ($('#SyncIssueDialog').hasClass('ui-dialog-content')) $('#SyncIssueDialog').dialog('destroy');
            simpleDialog(json_data.content, json_data.title, null, 600, "showProgress(1);window.location.reload();");
            setTimeout("showProgress(1);window.location.reload();", 2000);
        }
    });
}

/**
 * Reload the Sync Issues for another "page" when paging
 * @param pagenum, page number
 */
function loadIssuesList(pagenum) {
    showProgress(1);
    window.location.href = app_path_webroot+'MyCapMobileApp/index.php?syncissues=1&pid='+pid+'&pagenum='+pagenum+
        '&filterBeginTime='+$('#filterBeginTime').val()+'&filterEndTime='+$('#filterEndTime').val()+'&filterStatus='+$('#filterStatus').val()+'&filterParticipant='+$('#filterParticipant').val();
}

/**
 * Function that display all tasks enabled for MyCap with info
 */
function displayTasksListing() {
    showProgress(1);

    $.post(app_path_webroot+'MyCap/tasks_list.php?pid='+pid, {}, function(data){
        var json_data = jQuery.parseJSON(data);
        showProgress(0,0);
        simpleDialog(json_data.content, json_data.title, null, 920);
    });
}

/**
 * Function to display Active Task listing dialog (upon clicking of "Create Active Task" button)
 */
function openActiveTasksListing() {
    // AJAX call to get active tasks values for pre-filling
    simpleDialog(null, langAT01, "activetask_list",850, "");
    fitDialog($('#activetask_list'));
}

/**
 * Function to display add Active Task dialog (upon clicking of "Add" button)
 */
function addNewActiveTask(activeTask, taskName) {
    $('#activetask_instrument_label').html(taskName);
    $('#instrument_new_name').val('');
    // Open popup
    $('#activetask_add').dialog({ bgiframe: true, modal: true, width: 500,
        buttons: [
            { text: window.lang.calendar_popup_01, click: function () { $(this).dialog('close'); } },
            { text: langCreateActiveTask, click: function () {
                    showProgress(1, 0, langImportATProcessText);
                    var newForm = $('#instrument_new_name').val();
                    // Remove unwanted characters
                    newForm = newForm.replace(/^\s+|\s+$/g,'');
                    // Make sure instrument title is given
                    if (newForm == '') {
                        simpleDialog(langActiveTaskInstr);
                        return false;
                    }
                    // Ajax request to copy instrument
                    $.post(app_path_webroot+'MyCap/create_activetask.php?pid='+pid,{ selected_active_task:activeTask, new_form_label: newForm }, function(data) {
                        showProgress(0);
                        if (data == "0") { alert(woops); return; }
                        // Set dialog title/content
                        try {
                            var json_data = jQuery.parseJSON(data);
                            $('#activetask_add').dialog('close');
                            simpleDialogAlt("<div style='color:green;font-size:13px;'><img src='"+app_path_images+"tick.png'> "+langActiveTaskInstr1+"</div>", 300, 400);
                            setTimeout(function(){
                                showProgress(1);
                                window.location.href = app_path_webroot+'MyCap/edit_task.php?pid='+pid+'&view=showform&page='+json_data.instrument_name+'&redirectDesigner=1';
                            },3000);
                            initWidgets();
                        } catch(e) {
                            alert(woops);
                        }
                    });
                }
            }
        ]
    });
}

/**
 * Function to Set the MyCap task title to be the same as the form label value
 */
function setMyCapTaskTitleAsFormLabel(form) {

    $.post(app_path_webroot+'Design/set_task_title_as_form_name.php?pid='+pid,{ form: form },function(data) {
        if (data == '0') {
            alert(woops);
        } else {
            simpleDialog(langSetTaskTitleAsForm5+' "<b>'+data+'</b>"'+langPeriod,langSetTaskTitleAsForm6,null,null,"window.location.reload();");
        }
    });
}

/**
 * Function to display list of all MyCap issues in popup for instrument
 */
function showMyCapIssues(form) {
    showProgress(1,0);
    if (!$('#myCapIssues').length) $('body').append('<div id="myCapIssues" style="display:none;"></div>');
    $.post(app_path_webroot+'Design/mycap_task_issues.php?pid='+pid,{ page: form, action: 'list_issues' },function(data) {
        var json_data = jQuery.parseJSON(data);
        if (json_data.length < 1) {
            alert(woops);
            return false;
        }
        showProgress(0);
        // Add dialog content and set dialog title
        $('#myCapIssues').html(json_data.payload);

        $('#myCapIssues').dialog({
            bgiframe: true, modal: true, width: 600, open: function () {
                fitDialog(this)
            },
            title: json_data.title,
            buttons: [{
                text: window.lang.calendar_popup_01,
                click: function() {
                    $(this).dialog('close');
                }
            }]
        });
        if (json_data.count == 0 ) {
            $('#fixBtn').hide();
        }
    });
    return false;
}

/**
 * Function to fix all listed MyCap issues in popup/instrument design page for instrument
 */
function fixMyCapIssues(form) {
    showProgress(1, 0);
    $.post(app_path_webroot+'Design/mycap_task_issues.php?pid='+pid,{ page: form, action: 'fix_issues' },function(data){
        // Set dialog title/content
        try {
            var json_data = jQuery.parseJSON(data);
            if (json_data.length < 1) {
                alert(woops);
                return false;
            }
            showProgress(0);
            $("#myCapIssues").remove();
            simpleDialogAlt(json_data.payload, 300, 400);
            setTimeout(function(){
                showProgress(1);
                var url = app_path_webroot+page+'?pid='+pid;
                if (getParameterByName('page') != '') {
                    url += '&page='+getParameterByName('page');
                }
                window.location.href = url;
            },3000);
            initWidgets();
        } catch(e) {
            alert(woops);
        }
    });
}

// Dialog to open modify project title in app form
function dialogModifyAppProjectTitle() {
    $.post(app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=renderProjectTitleSetup', { }, function(data){
        if (data == '0') {
            alert(woops);
            return;
        }
        initDialog('projTitleDialog');
        $('#projTitleDialog').html(data);
        $('#projTitleDialog').dialog({ bgiframe: true, modal: true, width: 700, open: function(){ fitDialog(this) }, title: lang.mycap_mobile_app_691,
            buttons: [
                { text: window.lang.calendar_popup_01, click: function(){ $(this).dialog('close'); }},
                { text: window.lang.pub_085, click: function() {
                    // Check values before submission on Project form, stop here if basic form info are not valid
                    if ($('#project_title').val().length < 1) {
                        simpleDialog(window.lang.mycap_mobile_app_905, window.lang.global_01);
                        return false;
                    }
                    // Save via ajax
                    $('#projTitleDialog').dialog('close');
                    $.post(app_path_webroot+'MyCapMobileApp/update.php?pid='+pid+'&action=saveProjectTitle',$('#project_title_setup_form').serializeObject(),function(data){
                        if (data == '0') {
                            alert(woops);
                            return;
                        }
                        $('#projTitleDialog').remove();
                        simpleDialog('<img src="'+app_path_images+'tick.png"> <span style="font-size:14px;color:green;">'+lang.mycap_mobile_app_693+'</span>',lang.survey_605,null,null,function(){ window.location.reload(); }, window.lang.calendar_popup_01);
                        setTimeout(function(){ window.location.reload(); },2500);
                    });
                    }
                }
            ]
        });
    });
}

// MyCap Task setup: Adjust bgcolor of cells and inputs when activating/deactivating a task for event
function taskSetupActivate(activate, event_id) {
    if (activate) {
        // Activate this task setup
        $('#tstr-'+event_id+' td.data').removeClass('opacity35').addClass('darkgreen');
        // Enable all inputs
        $("#tstr-"+event_id).find("input,textarea,select").attr("disabled", false);
        if ($("#infinite-"+event_id).is(':checked') || $("#onetime-"+event_id).is(':checked')) {
            $('#allow_retroactive_completion-'+event_id).prop( "disabled", "disabled");
            $('#allow_retro_completion_row-'+event_id).css({ 'opacity' : 0.6 });
        }
        $('#tsactive-'+event_id).prop('checked', true);
        // Show/hide activation icons/text
        $('#div_ts_icon_enabled-'+event_id).show();
        $('#div_ts_icon_disabled-'+event_id).hide();

        if ($('tr[data-event="event_'+event_id+'"]:visible').length == 0) {
            $('button[data-toggle-event="event_'+event_id+'"]').trigger('click');
        }
    } else {
        // Deactivate this task setup
        // Remove bgcolors
        $('#tstr-'+event_id+' td').removeClass('darkgreen');
        $('#tstr-'+event_id+' td:eq(1), #tstr-'+event_id+' td:eq(2)').addClass('opacity35 disable-inputs');
        // Disable all inputs and remove their values
        $('#tstr-'+event_id+' input[type="checkbox"]').prop('checked', false);
        $("#tstr-"+event_id).find("input,textarea,select").attr("disabled", "disabled");
        $('#tsactive-'+event_id).prop('checked', false);
        // Show/hide activation icons/text
        $('#div_ts_icon_enabled-'+event_id).hide();
        $('#div_ts_icon_disabled-'+event_id).show();

        if ($('tr[data-event="event_'+event_id+'"]:visible').length > 0) {
            $('button[data-toggle-event="event_'+event_id+'"]').trigger('click');
        }
    }
}

// Transition project to Flutter App
function transitionToFlutter(doBtn,cancelBtn,title,content) {
    simpleDialog(content,title,null,500,null,cancelBtn,'transitionToFlutterDo();',doBtn);
}
function transitionToFlutterDo() {
    // Display progress bar
    showProgress(1);
    if ($('#migrateMyCapDialog').hasClass('ui-dialog-content')) $('#migrateMyCapDialog').dialog('destroy');

    $.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, { action: 'convert', setting: 'flutter_conversion' }, function(data){

        showProgress(0,0);
        if (data == '0') {
            alert(woops);
            return;
        } else {
            // Hide Transition to Flutter Notice
            $('#flutterNotice').hide();

            $('.flutterConversionMsg').show();
            $("#flutterNoticeImg").attr("src", app_path_images+'tick_small_circle.png');

            // Show message
            simpleDialog(data, lang.global_79, null, 600);
        }
    });
}

/**
 * Function to Copy Optional, Task Schedules or Active task settings to one or more events
 */
function copyTaskSettings(fromEventId, section) {
    var eventsList = [];
    $('#eventsListingDiv-'+section+'-'+fromEventId+' input:checked').each(function() {
        eventsList.push($(this).val());
    });
    if (eventsList.length == 0) {
        alert('Please select at least one event.');
        return false;
    }

    switch (section) {
        case 'schedules':
            $.each( eventsList, function( i, toEventId ) {
                if (!$('#tsactive-' + toEventId).is(":checked")) {
                    taskSetupActivate(1, toEventId);
                }

                if ($("input[name='schedule_relative_to-" + fromEventId + "']").length > 0) {
                    var schedule_relative_to = $("input[type='radio'][name='schedule_relative_to-" + fromEventId + "']:checked").val();
                    $('input:radio[name="schedule_relative_to-' + toEventId + '"][value="' + schedule_relative_to + '"]').prop("checked", true);
                }


                var schedule_type = $("input[name='schedule_type-" + fromEventId + "']:checked").val();
                $('input[name="schedule_type-' + toEventId + '"][value="' + schedule_type + '"]').trigger('click');
                if (schedule_type == repeatingType) {
                    var schedule_freq = $("select[name='schedule_frequency-" + fromEventId + "'] option:selected").val();
                    $('select[name="schedule_frequency-' + toEventId + '"]').val(schedule_freq);
                    $('select[name="schedule_frequency-' + toEventId + '"]').trigger('change');
                    if (schedule_freq == weeklyFreqVal) {
                        var weeks = $("select[name='schedule_interval_week-" + fromEventId + "'] option:selected").val();
                        $('select[name="schedule_interval_week-' + toEventId + '"]').val(weeks);
                        $('input:checkbox[name="schedule_days_of_the_week-' + fromEventId + '[]"]').each(function () {
                            var checked = $(this).is(":checked");
                            $('input:checkbox[name="schedule_days_of_the_week-' + toEventId + '[]"][value="' + $(this).val() + '"]').prop("checked", checked);
                        });
                    } else if (schedule_freq == monthlyFreqVal) {
                        var months = $("select[name='schedule_interval_month-" + fromEventId + "'] option:selected").val();
                        $('select[name="schedule_interval_month-' + toEventId + '"]').val(months);
                    }
                }

                if (schedule_type == repeatingType || schedule_type == infiniteType) {
                    var schedule_ends = $("input[name='schedule_ends-" + fromEventId + "']:checked").val();
                    $('input[name="schedule_ends-' + toEventId + '"][value="' + schedule_ends + '"]').click();
                    $('input:checkbox[name="schedule_ends_list-' + fromEventId + '[]"]').each(function () {
                        var checked_condition = $(this).is(":checked");
                        $('input:checkbox[name="schedule_ends_list-' + toEventId + '[]"][value="' + $(this).val() + '"]').prop("checked", checked_condition);
                    });
                }

                $('#tr-schedules-' + fromEventId + ' input[type="text"][name$="-' + fromEventId + '"]:visible').each(function () {
                    var name = $(this).attr('name').split("-");
                    var toInputName = name[0] + '-' + toEventId;
                    $('input[name="' + toInputName + '"]').val($(this).val());
                });
            });

            break;
        case 'optional':
        case 'activetasks':
            $.each( eventsList, function( i, toEventId ) {
                if (!$('#tsactive-' + toEventId).is(":checked")) {
                    taskSetupActivate(1, toEventId);
                }

                $('#tr-'+section+'-' + fromEventId + ' input[name$="-' + fromEventId + '"]:visible').each(function () {
                    var fromInputName = $(this).attr('name');
                    var name = $(this).attr('name').split("-");
                    var toInputName = name[0] + '-' + toEventId;

                    if ($(this).is(':checkbox')) {
                        var checked = $(this).is(":checked");
                        $('input[name="' + toInputName + '"]').prop('checked', checked);
                        if (name[0] == 'instruction_step' || name[0] == 'completion_step') {
                            $('input[name="' + toInputName + '"]').trigger('change');
                            $(':text').blur();
                        }
                    }
                    if ($(this).is('input:text')) {
                        $('input[name="' + toInputName + '"]').val($(this).val());
                    }
                    if ($(this).is(':radio')) {
                        var selectedVal = $("input[name='" + fromInputName + "']:checked").val();
                        $('input[name="' + toInputName + '"][value="' + selectedVal + '"]').click();
                    }
                });
                $('#tr-'+section+'-' + fromEventId + ' textarea[name$="-' + fromEventId + '"]:visible').each(function () {
                    var name = $(this).attr('name').split("-");
                    var toInputName = name[0] + '-' + toEventId;
                    $('textarea[name="' + toInputName + '"]').val($(this).val());
                });
            });
            break;
        default:
            alert('Invalid Value!');
    }

    var rndm = Math.random()+"";
    var copyid = 'copy'+rndm.replace('.','');
    $('.clipboardSaveProgress').remove();
    var clipSaveHtml = '<span class="clipboardSaveProgress" style="float: right; font-weight: bold; color: green;" id="'+copyid+'"><i class="fas fa-check"></i> Copied!</span>';
    $('#eventsListingBtn-'+section+'-'+fromEventId).after(clipSaveHtml);
    $('#'+copyid).toggle('fade','fast');
    setTimeout(function(){
        $('#'+copyid).toggle('fade','fast',function(){
            $('#'+copyid).remove();
        });
    },3000);

    $('#eventsListingDiv-'+section+'-'+fromEventId+' input:checkbox').each(function(){
        $(this).prop('checked', false);
    });
    $('#eventsListingDiv-'+section+'-'+fromEventId).hide();
}

/**
 * Function to select/deselect all events in "Copy settings to:" dropdown
 */
function selectAllEvents(select_all, section, eventId) {
    var do_select_all = (select_all == 1);
    $('#eventsListingDiv-'+section+'-'+eventId+' input:checkbox').each(function(){
        $(this).prop('checked',do_select_all);
    });
}

var REDCapMessege = {
    richTextBodyPrefix: '<div class="rich-text-body">',
    richTextBodySuffix: '</div>',

    getBodySelector: function() {
        return '#body';
    },

    removeBodyTinyMCE: function() {

        tinymce.remove(REDCapMessege.getBodySelector());

        var body = REDCapMessege.getBody();

        // Remove newlines so REDCap doesn't replace them with <br> tags.
        body.val(body.val().split('\n').join(' '));

        // Set the text color back to normal to undo the hacky way of preventing users from seeing the raw HTML during save.
        body.css('color', 'inherit');
    },

    initTinyMCEBody: function(isPreInit) {

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
            var body = REDCapMessege.getBody();
            body.val(nl2br(body.val()));
        }

        if (typeof tinymce == 'undefined') loadJS(app_path_webroot+"Resources/webpack/css/tinymce/tinymce.min.js");

        tinymce.init({
            license_key: 'gpl',
            promotion: false,
            entity_encoding : "raw",
            default_link_target: '_blank',
            selector: REDCapMessege.getBodySelector(),
            height: 325,
            menubar: false,
            branding: false,
            statusbar: true,
            elementpath: false, // Hide this, since it oddly renders below the textarea.
            plugins: 'autolink lists link image searchreplace code fullscreen table directionality hr',
            toolbar1: 'bold italic underline link undo redo code',
            contextmenu: "copy paste | link image inserttable | cell row column deletetable",
            content_css: app_path_webroot + "Resources/webpack/css/bootstrap.min.css," + app_path_webroot + "Resources/webpack/css/fontawesome/css/all.min.css,"+app_path_webroot+"Resources/css/style.css",
            relative_urls: false,
            convert_urls : false,
            extended_valid_elements: 'i[class]',
            paste_postprocess: function(plugin, args) {
                args.node.innerHTML = cleanHTML(args.node.innerHTML);
            },
            // Embedded image uploading
            file_picker_types: 'image',
            images_upload_handler: rich_text_image_upload_handler,
        });
    },

    toggleBodyRichText: function(enabled) {
        if(enabled === undefined){
            enabled = REDCapMessege.isBodyRichTextChecked();
        }
        else{
            REDCapMessege.getBodyRichTextCheckbox().prop('checked', enabled);
        }

        if(enabled){
            REDCapMessege.initTinyMCEBody(false);
        }
        else{
            REDCapMessege.removeBodyTinyMCE();
        }
    },

    getBody: function() {
        return $(REDCapMessege.getBodySelector());
    },

    initBody: function(value) {
        var prefix = REDCapMessege.richTextBodyPrefix;
        var suffix = REDCapMessege.richTextBodySuffix;

        value = value.slice(prefix.length, -suffix.length);

        // If TinyMCE was previously initialized, remove it.
        // This has no effect if it wasn't previously initialized.
        // This is required for the the val() call below to correctly set the textarea value.
        REDCapMessege.removeBodyTinyMCE();

        REDCapMessege.getBody().val(value);
        REDCapMessege.toggleBodyRichText(true);
    },

    isRichTextBody: function(value) {
        return value.indexOf(REDCapMessege.richTextBodyPrefix) === 0;
    },

    getBodyRichTextCheckbox: function() {
        return $('#body_rich_text_checkbox');
    },

    isBodyRichTextChecked: function() {
        return REDCapMessege.getBodyRichTextCheckbox().is(':checked');
    },

    updateBodyBeforeFormSubmit: function() {
            //alert("Is checked="+REDCapMessege.isBodyRichTextChecked());
        if(REDCapMessege.isBodyRichTextChecked()){
            // Remove TinyMCE in order to remove newlines that REDCap would replace with <br> tags.
            // This also allows us to interact with the textarea directly below.
            REDCapMessege.removeBodyTinyMCE();

            var body = REDCapMessege.getBody();
            //alert("Body is="+body.val());
            var html =REDCapMessege.richTextBodyPrefix + body.val() + REDCapMessege.richTextBodySuffix;
            //alert("After Body is="+body.val());
            // Hack to prevent the user from seeing the raw html while the field is saving.
            body.css('color', 'white');
            return html;
        }
    }
}
/* Hide message box on MyCap participant page and decrement notification count by "1" in left menu when user clicks on "Acknowledge and close" button  */
function acknowledgeNewAppLink() {
    // Display progress bar
    showProgress(1);

    $.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, { action: 'acknowledge', setting: 'new_app_link' }, function(data){
        showProgress(0,0);
        if (data == '0') {
            alert(woops);
            return;
        } else {
            // Hide Transition to Flutter Notice
            $('#appLinkNotice').hide();
            // Hide Notification block on Participant tab
            $('#partCountBlock').hide();
            var countLeftMenu = $('#allCountBlock').html();
            // Hide/Update Notification block on Left Menu MPT link
            if (countLeftMenu > 1) {
                $('#allCountBlock').html((countLeftMenu-1));
            } else {
                $('#allCountBlock').hide();
            }
        }
    });
}

/**
 * Function to Copy notification settings to one or more DAGs
 */
function copyDagsSettings(fromDagId) {
    var dagsList = [];
    $('#dagsListingDiv-'+fromDagId+' input:checked').each(function() {
        dagsList.push($(this).val());
    });
    if (dagsList.length == 0) {
        alert(lang.mycap_mobile_app_982);
        return false;
    }

    $.each( dagsList, function( i, toDagId ) {
        var tbl = $('input[name="notify_user_' + toDagId + '"]').closest("table");
        if ($('input[name="notify_user_'+fromDagId+'"]').is(':checked')) {
            $('input[name="notify_user_' + toDagId + '"]').attr("checked", true);
            // Enable all inputs on a page
            tbl.find("tbody>tr.notification-settings-tr").css({ 'opacity' : "" });
            tbl.find("tbody>tr textarea").prop("disabled", false);
        } else {
            $('input[name="notify_user_' + toDagId + '"]').attr("checked", false);
            // Disable all inputs on a page
            tbl.find("tbody>tr.notification-settings-tr").css({ 'opacity' : "0.5" });
            tbl.find("tbody>tr textarea").prop("disabled", true);
        }
        // Copy User email list
        var user_emails = $('textarea[name="user_emails_'+fromDagId+'"]').val();
        $('textarea[name="user_emails_'+toDagId+'"]').val(user_emails);

        // Copy Message body
        var text = tinymce.activeEditor.getContent();
        tinymce.get('mceEditor_'+toDagId).setContent(text);
    });

    var rndm = Math.random()+"";
    var copyid = 'copy'+rndm.replace('.','');
    $('.clipboardSaveProgress').remove();
    var clipSaveHtml = '<span class="clipboardSaveProgress" style="float: right; font-weight: bold; color: green;" id="'+copyid+'"><i class="fas fa-check"></i> Copied!</span>';
    $('#dagsListingBtn-'+fromDagId).after(clipSaveHtml);
    $('#'+copyid).toggle('fade','fast');
    setTimeout(function(){
        $('#'+copyid).toggle('fade','fast',function(){
            $('#'+copyid).remove();
        });
    },3000);

    $('#dagsListingDiv-'+fromDagId+' input:checkbox').each(function(){
        $(this).prop('checked', false);
    });
    $('#dagsListingDiv-'+fromDagId).hide();
}

/**
 * Function to select/deselect all DAGs in "Copy settings to:" dropdown
 */
function selectAllGroups(select_all, dagId) {
    var do_select_all = (select_all == 1);
    $('#dagsListingDiv-'+dagId+' input:checkbox').each(function(){
        $(this).prop('checked',do_select_all);
    });
}