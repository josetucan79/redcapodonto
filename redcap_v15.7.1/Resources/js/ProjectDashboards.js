$(function(){
    initTinyMCEglobal('mceEditor');
    // Dashboard List: Enable drag n drop on dashboard list table
    if ($('table#table-project_dashboard_list').length) {
        enableDashboardListTable();
    }
    // Change default behavior of the multi-select boxes so that they are more intuitive to users when selecting/de-selecting options
    $("select[multiple]").each(function(){
        modifyMultiSelect($(this), 'ms-selection');
    });
    // When adding/editing dashboard, show/hide the public link div
    if ($('#is_public').length) {
        $('#is_public').change(function(){
            // If users must request dashboards be public
            if (!super_user_not_impersonator && project_dashboard_allow_public == '2' && getParameterByName('dash_id') == '') {
                $(this).prop('checked', false);
                $('#public_link_div').removeClass('hide');
                $('#public_link_div>span').effect('highlight', { }, 2500);
            } else if (super_user_not_impersonator || project_dashboard_allow_public == '1') {
                // Admin
                if ($(this).prop('checked')) {
                    $('#public_link_div').removeClass('hide');
                    $('#public-dash-enable-warning').addClass('hide');
                } else {
                    $('#public_link_div').addClass('hide');
                    $('#public-dash-enable-warning').removeClass('hide');
                }
            } else {
                // Normal user needing admin approval
                if ($(this).prop('checked') && getParameterByName('dash_id') != '') {
                    requestPublicDash(getParameterByName('dash_id'));
                } else {
                    $('#public_link_div').addClass('hide');
                    $('#public-dash-enable-warning').removeClass('hide');
                }
            }
        });
    }
    // Copy-to-clipboard action
    $('.btn-clipboard').click(function(){
        copyUrlToClipboard(this);
    });
    // Put focus in title if creating new dashboard
    if ($(':input[name="title"]').length && $(':input[name="title"]').val() == '') {
        $(':input[name="title"]').focus();
    }
    // If any drop-down in the wizard dialog changes, then run a function
    $('#dash_wizard_dialog select').on('change', function(){
        dashWizardGenerate();
    });
    // If user clicks any stats-table column options, always unselect the "all columns" option (add slight delay to allow the multi-select jQuery functionality to work first)
    $('#dash_wizard_dialog select[multiple]').on("click", "option", function() {
        var val = $(this).val();
        var id = $(this).parent().attr('id');
        setTimeout(function() {
            var optionJustSelected = ($('#'+id+' option[value="'+val+'"]:selected').length > 0);
            var allOptionJustSelected = (val == '' && !optionJustSelected);
            // If nothing selected, then select the "all" option
            $('#'+id+' option[value=""]').prop('selected', allOptionJustSelected);
            // Unselect the "all columns" option if another option was clicked
            if ($('#'+id+' option[value=""]:selected').length && $('#'+id+' option:selected').length > 1) {
                if (allOptionJustSelected) {
                    $('#'+id+' option').prop('selected', false);
                    $('#'+id+' option[value=""]').prop('selected', true);
                } else {
                    $('#'+id+' option[value=""]').prop('selected', false);
                }
            }
            // If nothing is selected, then select the 'all' option
            if ($('#'+id+' option:selected').length == 0) {
                $('#'+id+' option[value=""]').prop('selected', true);
            }
            // Re-run the generate function since this block above was delayed
            dashWizardGenerate(false);
        }, 5);
    });
});

// AJAX call to request public dash be enabled from admin
function requestPublicDash(dash_id) {
    showProgress(1);
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:request_public_enable', { dash_id: dash_id }, function(data) {
        $(':input[name=is_public]').prop('checked',false).prop('disabled',true); // Reset value to unchecked state
        showProgress(0,0);
        simpleDialog(data);
    });
}

// Display a prompt to an admin to enable a dashboard as public
function promptEnablePublicDash(dash_id, dash_title) {
    simpleDialog(lang.dash_76+"<p class='mt-3 fs14'>"+lang.dash_79+" \"<a class='fs14 boldish' style='text-decoration: underline;' href='"+app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:view&dash_id='+dash_id+"' target='_blank'>"+dash_title+"</a>\"</p>",
        lang.dash_77, null, 600, null, lang.global_53, function(){
            $.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:public_enable', { dash_id: dash_id, user: getParameterByName('user') }, function(data) {
                if (data == '' || data == '0') alert(woops);
                else simpleDialog(data);
            });
    },lang.dash_78);
}

// Copy-to-clipboard action
try {
    var clipboard = new Clipboard('.btn-clipboard');
} catch (e) {}

// Copy the public survey URL to the user's clipboard
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

// Display or hide user access custom view options
function displayUserAccessOptions() {
    if ($('#create_report_table input[name="user_access_radio"]:checked').val() == 'SELECTED') {
        // Open custom user access options
        $('#selected_users_div').show('blind','fast');
    } else {
        // Hide options
        $('#selected_users_div').hide('blind','fast');
    }
    $('#selected_users_note1, #selected_users_note2').toggle();
}

// For multi-selects, if the last selected option is clicked, then de-select it
function clearMultiSelect(ob) {
    var selections = $(ob).find('option:selected').map(function(){ return this.value }).get().length;
}

// Obtain list of usernames who would have access to a report based on the User Access selections on the page
function getUserAccessList() {
    // Save the report via ajax
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:access', $('form#create_report_form').serializeObject(), function(data) {
        if (data == '0') {
            alert(woops);
            return;
        }
        // Parse JSON
        var json_data = jQuery.parseJSON(data);
        simpleDialog(json_data.content, json_data.title, null, 600);
    });
}

// Save the new/existing dashboard
function saveDash(dash_id) {
    // Validate the report fields
    if (!validateCreateDash()) return false;
    // Start clock so we can display progress for set amount of time
    var start_time = new Date().getTime();
    var min_wait_time = 500;
    // Save the report via ajax
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:save&dash_id='+dash_id, $('form#create_report_form').serializeObject(), function(data) {
        if (data == '0') {
            alert(woops);
            return;
        }
        // Update left-hand menu panel of Reports
        updateDashboardPanel();
        // Parse JSON
        var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
        // Build buttons for dialog
        var btns =	[{ text: langBtn1, click: function() {
                if (json_data.newdash) {
                    // Reload page with new dash_id
                    showProgress(1);
                    window.location.href = app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:index&dash_id='+json_data.dash_id+'&addedit=1';
                } else {
                    $(this).dialog('close').dialog('destroy');
                }
            }},
            {text: langBtn2, click: function() {
                    window.location.href = app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:index';
                }},
            {text: langBtn3, click: function() {
                    window.location.href = app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:view&dash_id='+json_data.dash_id;
                }}];
        // End clock
        var total_time = new Date().getTime() - start_time;
        // If total_time is less than min_wait_time, then wait till it gets to min_wait_time
        var wait_time = (total_time < min_wait_time) ? (min_wait_time-total_time) : 0;
        // Set wait time, if any
        setTimeout(function(){
            showProgress(0,0);
            // Display success dialog
            initDialog('dashboard_saved_success_dialog');
            $('#dashboard_saved_success_dialog').html(json_data.content).dialog({ bgiframe: true, modal: true, width: 640,
                title: json_data.title, buttons: btns, close: function(){
                    if (json_data.newdash) {
                        // Reload page with new dash_id
                        showProgress(1);
                        window.location.href = app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:index&dash_id='+json_data.dash_id+'&addedit=1';
                    } else {
                        $(this).dialog('destroy');
                    }
                } });
            $('#dashboard_saved_success_dialog').dialog("widget").find(".ui-dialog-buttonpane button").eq(2).css({'font-weight':'bold', 'color':'#333'});
        }, wait_time);
    });
    // Set progress bar if still running after a moment
    setTimeout(function(){
        showProgress(1,300);
    },100);
}

// Validate report attributes when adding/editing report
function validateCreateDash() {
    // Make sure there is a title
    var title_ob = $('#create_report_table input[name="title"]');
    title_ob.val( trim(title_ob.val()) );
    if (title_ob.val() == '') {
        simpleDialog(langNoTitle,null,null,null,"$('#create_report_table input[name=title]').focus();");
        return false;
    }
    // Make sure there is a body
    var body_ob = $('#create_report_table :input[name="body"]');
    body_ob.val( trim(body_ob.val()) );
    if (body_ob.val() == '') {
        simpleDialog(langNoBody);
        return false;
    }
    // If doing custom user access, make sure something is selected
    if ($('#create_report_table input[name="user_access_radio"]:checked').val() != 'ALL'
        && ($('#create_report_table select[name="user_access_users"] option:selected').length
            + $('#create_report_table select[name="user_access_dags"] option:selected').length
            + $('#create_report_table select[name="user_access_roles"] option:selected').length) == 0) {
        simpleDialog(langNoUserAccessSelected);
        return false;
    }
    // If we made it this far, then all is well
    return true;
}

// Open a dashboard
function openDashboard(dash_id) {
    window.location.href = app_path_webroot+'index.php?route=ProjectDashController:view&dash_id='+dash_id+'&pid='+pid;
}

// Open a public dashboard
function openDashboardPublic(hash) {
    window.open(app_path_webroot_full+'Surveys/index.php?dashboard='+hash,'_blank');
}

// Edit a dashboard
function editDashboard(dash_id) {
    window.location.href = app_path_webroot+'index.php?route=ProjectDashController:index&dash_id='+dash_id+'&addedit=1&pid='+pid;
}

// Copy a dashboard
function copyDashboard(dash_id, confirmCopy) {
    if (confirmCopy == null) confirmCopy = true;
    // Get dashboard title from table
    var row_id = $('#repcopyid_'+dash_id).parents('tr:first').attr('id');
    var dash_title = trim($('#repcopyid_'+dash_id).parents('tr:first').find('td:eq(2)').find('.dash-title').text());
    if (confirmCopy) {
        // Prompt user to confirm copy
        simpleDialog(langCopyDashboardConfirm
            + ' "<span style="color:#C00000;font-size:14px;">'+dash_title+'</span>"'+langQuestionMark,
            langCopyReport,null,350,null,closeBtnTxt,"copyDashboard("+dash_id+",false);",langCopy);
    } else {
        // Copy via ajax
        $.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:copy', { dash_id: dash_id }, function(data) {
            if (data == '0') {
                alert(woops);
                return;
            }
            // Parse JSON
            var json_data = jQuery.parseJSON(data);
            // Replace current report list on page
            $('#dashboard_list_parent_div').html(json_data.html);
            // Re-enable table
            enableDashboardListTable();
            initWidgets();
            // Highlight new row then remove row from table
            var i = 1;
            $('tr#reprow_'+json_data.new_dash_id+' td').each(function(){
                if (i++ != 1) $(this).effect('highlight',{},2000);
            });
            // Update left-hand menu panel of Reports
            updateDashboardPanel();
        });
    }
}

// Delete a dashboard
function deleteDashboard(dash_id, confirmDelete) {
    if (confirmDelete == null) confirmDelete = true;
    // Get report title from table
    var row_id = $('#repdelid_'+dash_id).parents('tr:first').attr('id');
    var dash_title = trim($('#repdelid_'+dash_id).parents('tr:first').find('td:eq(2)').find('.dash-title').text());
    if (confirmDelete) {
        // Prompt user to confirm deletion
        simpleDialog(langDeleteDashboardConfirm
            + ' "<span style="color:#C00000;font-size:14px;">'+dash_title+'</span>"'+langQuestionMark,
            langDeleteReport,null,350,null,closeBtnTxt,"deleteDashboard("+dash_id+",false);",langDelete);
    } else {
        // Delete via ajax
        $.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:delete', { dash_id: dash_id }, function(data) {
            if (data == '0') {
                alert(woops);
                return;
            }
            // Highlight deleted row then remove row from table
            var i = 1;
            $('tr#'+row_id+' td').each(function(){
                if (i++ != 1) $(this).effect('highlight',{},700);
            });
            setTimeout(function(){
                $('tr#'+row_id).hide('fade',function(){
                    $('tr#'+row_id).remove();
                    resetDashboardOrderNumsInTable();
                    restripeDashboardListRows();
                });
            },300);
            // Update left-hand menu panel of Reports
            updateDashboardPanel();
        });
    }
}


// Enable report list table
function enableDashboardListTable() {
    // Add dragHandle to first cell in each row
    $("table#table-project_dashboard_list tr").each(function() {
        var dash_id = trim($(this.cells[0]).text());
        $(this).prop("id", "reprow_"+dash_id).attr("dashid", dash_id);
        if (isNumeric(dash_id)) {
            // User-defined reports (draggable)
            $(this.cells[0]).addClass('dragHandle');
            // $(this.cells[3]).addClass('opacity50');
            // $(this.cells[4]).addClass('opacity50');
        } else {
            // Pre-defined reports
            $(this).addClass("nodrop").addClass("nodrag");
        }
    });
    // Restripe the report list rows
    restripeDashboardListRows();
    // Enable drag n drop (but only if user has "reports" user rights)
    $('table#table-project_dashboard_list').tableDnD({
        onDrop: function(table, row) {
            // Loop through table
            var ids = "";
            var this_id = $(row).prop('id');
            $("table#table-project_dashboard_list tr").each(function() {
                // Gather form_names
                var row_id = $(this).attr("dashid");
                if (isNumeric(row_id)) {
                    ids += row_id + ",";
                }
            });
            // Save new order via ajax
            $.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:reorder', { dash_ids: ids }, function(data) {
                if (data == '0') {
                    alert(woops);
                    window.location.reload();
                } else if (data == '2') {
                    window.location.reload();
                }
                // Update left-hand menu panel of Reports
                updateDashboardPanel();
            });
            // Reset report order numbers in report list table
            resetDashboardOrderNumsInTable();
            // Restripe table rows
            restripeDashboardListRows();
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
    // Create mouseover image for drag-n-drop action and enable button fading on row hover
    $("table#table-project_dashboard_list tr:not(.nodrag)").mouseenter(function() {
        $(this.cells[0]).css('background','#ffffff url("'+app_path_images+'updown.gif") no-repeat center');
        $(this.cells[0]).css('cursor','move');
        // $(this.cells[3]).removeClass('opacity50');
        // $(this.cells[4]).removeClass('opacity50');
    }).mouseleave(function() {
        $(this.cells[0]).css('background','');
        $(this.cells[0]).css('cursor','');
        // $(this.cells[3]).addClass('opacity50');
        // $(this.cells[4]).addClass('opacity50');
    });
    // Set up drag-n-drop pop-up tooltip
    var first_hdr = $('#report_list .hDiv .hDivBox th:first');
    first_hdr.prop('title',langDragReport);
    first_hdr.tooltip2({ tipClass: 'tooltip4sm', position: 'top center', offset: [25,0], predelay: 100, delay: 0, effect: 'fade' });
    $('.dragHandle').mouseenter(function() {
        first_hdr.trigger('mouseover');
    }).mouseleave(function() {
        first_hdr.trigger('mouseout');
    });
}

// Restripe the rows of the report list table
function restripeDashboardListRows() {
    var i = 1;
    $("table#table-project_dashboard_list tr").each(function() {
        // Restripe table
        if (i++ % 2 == 0) {
            $(this).addClass('erow');
        } else {
            $(this).removeClass('erow');
        }
    });
}

// Reset report order numbers in report list table
function resetDashboardOrderNumsInTable() {
    var i = 1;
    $("table#table-project_dashboard_list tr:not(.nodrag)").each(function(){
        $(this).find('td:eq(1) div').html(i++);
    });
}

function confirmCustomUrl(hash,dash_id,custom_url){
    custom_url = trim(custom_url);
    if(custom_url != ''){
        showProgress(1);
        $.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:shorturl', { hash: hash, dash_id: dash_id, custom_url: custom_url }, function(data) {
            showProgress(0,0);
            if (data == '0' || data == '') {
                simpleDialog(woops,null,null,350,"customizeShortUrl('"+hash+"','"+dash_id+"')",'Close');
            } else if (data == '1') {
                simpleDialog('The text you entered does not make a valid URL. Please try again using only letters, numbers, and underscores.',null,null,350,"customizeShortUrl('"+hash+"','"+dash_id+"')",'Close');
            } else if (data == '2') {
                simpleDialog('Unfortunately, the URL you entered has already been taken. Please try again.',null,null,350,"customizeShortUrl('"+hash+"','"+dash_id+"')",'Close');
            } else {
                if (data.indexOf('ERROR:') > -1) {
                    var title = "ERROR!";
                    simpleDialog("<div class='fs14'></div>"+data+"</div>", title,null,600);
                } else {
                    var title = "SUCCESS!";
                    $('#create-custom-link-btn').hide();
                    $('#short-link-display').show();
                    $('#dashurl-custom').val(data);
                    simpleDialog("<div class='fs14'></div>"+langCreateCustomLink+"</div><a href='"+data+"' class='d-block mt-3 fs15' target='_blank' style='text-decoration:underline;'>"+data+"</a>", title,null,600);
                }
            }
        });
    }else{
        simpleDialog('Please enter a valid url.',null,null,350,"customizeShortUrl('"+hash+"','"+dash_id+"')",'Close');
    }
}

function customizeShortUrl(hash,dash_id){
    simpleDialog(null,null,'custom_url_dialog',550,null, lang.global_53,function(){
        confirmCustomUrl(hash,dash_id,$('input.customurl-input').val())
    }, lang.survey_200);
}

function showQRCode(el, dashId, short = false) {
    // Disabled the button by preventing all interactions
    el.style.pointerEvents = 'none';
    // Build the content
    const content = $('<div style="display:flex;flex-direction:column;align-items:center;"></div>');
    // Add the images
    content.append($('<img id="qr-code-img" width="180" height="180" src="' + app_path_webroot + 'index.php?route=ProjectDashController:get_qr_code_png&dash_id='+dashId+(short ? '&short=1' : '')+'&pid='+pid+'">'));
    // Add a copy to clipboard link
    const copyPngLink = $('<a href="javascript:void(0);"><i class="fa-solid fa-paste"></i> ' + lang.global_137 + '</a>');
    copyPngLink.on('click', function() {
        copyImageToClipboardAsPng('qr-code-img');
    });
    content.append(copyPngLink);
    // Add a SVG download link
    const downloadSvgLink = $('<a href="' + app_path_webroot + 'index.php?route=ProjectDashController:get_qr_code_svg&dash_id='+dashId+(short ? '&short=1' : '')+'&pid='+pid+'" target="_blank"><i class="fa-solid fa-download"></i> ' + lang.survey_1560 + '</a>');
    content.append(downloadSvgLink);
    // Set a title
    const title = '<i class="fa fa-qrcode"></i> ' + lang.dash_140;
    // Display the dialog
    simpleDialog(
        content, 
        title, 
        'show-qr-code-dialog', 
        null, 
        // Re-enable the button
        () => el.style.pointerEvents = 'auto'
    );
}

async function copyImageToClipboardAsPng(imgId) {
    try {
        // Get the image element by its id
        const imgElement = document.getElementById(imgId);
        if (!imgElement) {
            throw new Error("Image element not found.");
        }

        // Create a canvas element to draw the image onto
        const canvas = document.createElement("canvas");
        canvas.width = imgElement.width;
        canvas.height = imgElement.height;

        // Draw the image on the canvas
        const context = canvas.getContext("2d");
        context.drawImage(imgElement, 0, 0, canvas.width, canvas.height);

        // Convert the canvas content to a Blob (PNG format)
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/png"));

        if (!blob) {
            throw new Error("Failed to create image blob.");
        }

        // Create an ClipboardItem and copy to clipboard
        const clipboardItem = new ClipboardItem({ "image/png": blob });
        await navigator.clipboard.write([clipboardItem]);
    } catch (error) {
        console.error("Failed to copy image to clipboard:", error);
    }
}

function removeCustomUrl(dash_id){
    showProgress(1);
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:remove_shorturl', { dash_id: dash_id }, function(data) {
        showProgress(0,0);
        if (data == '0' || data == '') {
            simpleDialog(woops);
        } else {
            $('#create-custom-link-btn').show();
            $('#short-link-display').hide();
            $('input.customurl-input').val('');
            simpleDialog(data);
        }
    });
}

function openDashWizard()
{
    simpleDialog(null,null,'dash_wizard_dialog',900);
    dashWizardGenerate();
}

function dashWizardGenerate(doHighlight)
{
    if (typeof doHighlight == 'undefined') doHighlight = true;
    dashWizardPreGenerateCleanup();
    var val = $('#smart-thing-var').val();
    // Fields
    var fields = new Array();
    var f=0;
    $('.smart-thing-fields').each(function(){
        fields[f++] = $(this).val();
    });
    val += ':'+fields.join(',');
    // Stats table cols
    var cols = $('#smart-thing-table-cols').val();
    var colNum = cols.length;
    var newcols = new Array(); var n=0;
    for (var c=0; c<colNum; c++) {
        if (cols[c] != '') newcols[n++] = cols[c];
    }
    if (newcols.length > 0) val += ':'+newcols.join(',');
    // Optional params
    var params = new Array(); var p=0;
    // Reports
    var report = $('#smart-thing-reports').val();
    if (report != '') params[p++] = report;
    // DAGs
    if ($('#smart-thing-dags').length) {
        if (report != '') {
            // Disable DAG dropdown because reports can't be used with them
            $('#smart-thing-dags').prop('disabled',true);
            $('#smart-thing-dags').val('');
        } else {
            $('#smart-thing-dags').prop('disabled',false);
            var cols = $('#smart-thing-dags').val();
            var colNum = cols.length;
            var newcols = new Array();
            var n = 0;
            for (var c = 0; c < colNum; c++) {
                if (cols[c] != '') newcols[n++] = cols[c];
            }
            if (newcols.length > 0) params[p++] = newcols.join(',');
        }
    }
    // Events
    if ($('#smart-thing-events').length) {
        if (report != '') {
            // Disable DAG dropdown because reports can't be used with them
            $('#smart-thing-events').prop('disabled',true);
            $('#smart-thing-events').val('');
        } else {
            $('#smart-thing-events').prop('disabled',false);
            var cols = $('#smart-thing-events').val();
            var colNum = cols.length;
            var newcols = new Array();
            var n = 0;
            for (var c = 0; c < colNum; c++) {
                if (cols[c] != '') newcols[n++] = cols[c];
            }
            if (newcols.length > 0) params[p++] = newcols.join(',');
        }
    }
    // Add the params
    if (params.length > 0) val += ':'+params.join(',');
    // Generate the value
    $('#smart-thing-generated').val('['+val+']');
    if (doHighlight) $('#smart-thing-generated').val('['+val+']').effect('highlight',{},1000);
    fitDialog($('#dash_wizard_dialog'));
}

function dashWizardPreGenerateCleanup()
{
    var smartVar = $('#smart-thing-var').val();
    var addFieldBtn = $('#smart-thing-fields-add');
    var statsTblColDD = $('#smart-thing-table-cols');
    // Defaults
    // Enable "add field" button
    addFieldBtn.prop('disabled', false);
    // Hide the stats table column drop-down
    statsTblColDD.parent().hide();
    // Pies and Donuts (1 field only)
    if (smartVar == 'pie-chart' || smartVar == 'donut-chart') {
        // Remove all but one field
        var f=0;
        $('.smart-thing-fields').each(function(){
            if (f++ > 0) $(this).parent().remove();
        });
        // Disable "add field" button
        addFieldBtn.prop('disabled', true);
    }
    // Bar charts (2 fields max)
    else if (smartVar == 'bar-chart') {
        // Remove all but first 2 fields
        var f=0;
        $('.smart-thing-fields').each(function(){
            if (f++ > 1) $(this).parent().remove();
        });
        // Disable "add field" button if has 2 fields visible
        if ($('.smart-thing-fields').length >= 2) {
            addFieldBtn.prop('disabled', true);
        }
    }
    // Scatter and Line charts (3 fields max)
    else if (smartVar == 'scatter-plot' || smartVar == 'line-chart') {
        // Remove all but one field
        var f=0;
        $('.smart-thing-fields').each(function(){
            if (f++ > 2) $(this).parent().remove();
        });
        // Disable "add field" button
        if ($('.smart-thing-fields').length >= 3) {
            addFieldBtn.prop('disabled', true);
        }
    }
    // Stats table
    else if (smartVar == 'stats-table') {
        // Show column drop-down
        statsTblColDD.parent().show();
    }
}

function addFieldWizard()
{
    var lastFieldDD = $('.smart-thing-fields:last').parent();
    lastFieldDD.clone().insertAfter(lastFieldDD);
    var lastFieldDD = $('.smart-thing-fields:last').parent();
    if (!lastFieldDD.find('.fas').length) {
        $('.smart-thing-fields:last').after('<a href="javascript:;" class="ms-2 mt-1 text-danger" title="'+lang.dash_106+'" onclick="removeFieldWizard(this);"><i class="fas fa-times fs16"></i></a>')
    }
    $('.smart-thing-fields:last').effect('highlight',{},1000);
    dashWizardGenerate();
    // Register this new field
    $('.smart-thing-fields:last').on('change', function(){
        dashWizardGenerate();
    });
}

function removeFieldWizard(ob)
{
    $(ob).parent().remove();
    dashWizardGenerate();
}