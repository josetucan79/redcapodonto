$(function(){
    var isSurveyPage = (page == 'surveys/index.php');
    // Maintain original value of dataEntryFormValuesChanged. Since we're just triggering piping here, no data is changed, but the jquery triggers will set dataEntryFormValuesChanged = true.
    var thisDataEntryFormValuesChanged = dataEntryFormValuesChanged;
    // Replace instances of fake object tag
    replaceFakeObjectTag();
    // If any fields are using @SETVALUE/@PREFILL, @DEFAULT, @NOW, or @TODAY action tag, make sure piping of default value is performed on page load
    $('#questiontable tr.\\@SETVALUE, #questiontable tr.\\@PREFILL, #questiontable tr.\\@DEFAULT, #questiontable tr.\\@NOW, #questiontable tr.\\@TODAY, #questiontable tr.\\@NOW-SERVER, #questiontable tr.\\@TODAY-SERVER, #questiontable tr.\\@NOW-UTC, #questiontable tr.\\@TODAY-UTC').each(function(i, row) {
        var fname = $(row).attr('sq_id');
        var field = $(row).find('[name="'+fname+'"]');
        if (typeof field.val() != 'undefined' && field.val() != '') { // Only do anything if the field has a value
            // Escape the value we're about to pipe
             var val = filter_tags(field.val());
            // Trigger piping on page
            if ($(field).is('select')) {
                field.trigger('change');
            } else if ($('input[name="'+fname+'___radio"]').length) {
                if ($('input[name="'+fname+'___radio"][value="' + val + '"]').length) {
                    updatePipingRadiosDo($('input[name="' + fname + '___radio"][value="' + val + '"]'));
                }
            } else {
                // Set @DEFAULT field to show that it changed value (no need to worry about @PREFILL/@SETVALUE here because they are handled server side)
                if ($('#'+fname+'-tr').hasClass('@DEFAULT') && $(this).attr('ignoreDefault') != '1') {
                    $('input[name="'+fname+'"][type="text"]').addClass('calcChanged');
                }
                // For all other fields, simply propagate the value manually to minimize branching/calculation triggering on page load
                $('.piping_receiver.piperec-'+event_id+'-'+fname).html(val);
                $('.piping_receiver.piperec-'+event_id+'-'+fname+'-label').html(val);
                $('.piping_receiver.piperec-'+event_id+'-'+fname+'-value').html(val);
            }
        }
    });
    // Enable @RICHTEXT on notes fields
    $('#questiontable tr.\\@RICHTEXT').each(function(i, row) {
        var fieldname = $(row).attr('sq_id');
        $('.expandLinkParent', $(this)).hide();
        // If field also contains @READONLY, then do not enable rich text
        if (!$(this).hasClass('@READONLY') && !(isSurveyPage && $(this).hasClass('@READONLY-SURVEY')) && !(!isSurveyPage && $(this).hasClass('@READONLY-FORM'))) {
            // Regular rich text field
            $('textarea[name="'+fieldname+'"][rc-align="left"]').height(250).addClass('notesbox-richtext-left');
            $('textarea[name="'+fieldname+'"][rc-align="right"]').height(200).addClass('notesbox-richtext-right');
        } else {
            // Deal with read-only rich text field one field at a time
            var fieldclass = 'disabled-rcfield-'+fieldname;
            $('textarea[name="'+fieldname+'"][rc-align="left"]').height(250).addClass(fieldclass);
            $('textarea[name="'+fieldname+'"][rc-align="right"]').height(200).addClass(fieldclass);
            initTinyMCEglobal(fieldclass, ($('textarea[name="'+fieldname+'"][rc-align="right"]').length > 0), true, false);
            $('textarea[name="'+fieldname+'"]').removeClass(fieldclass);
        }
    });
    setTimeout(function() {
        var editorReadOnly = (page == 'DataEntry/index.php' && $('#submit-btn-saverecord:visible').length==0);
        initTinyMCEglobal('notesbox-richtext-left', false, editorReadOnly, (page == 'DataEntry/index.php'));
        initTinyMCEglobal('notesbox-richtext-right', true, editorReadOnly, (page == 'DataEntry/index.php'));
    }, 10);
    // Now set dataEntryFormValuesChanged back to its original value
    dataEntryFormValuesChanged = thisDataEntryFormValuesChanged;
    if (page != 'Design/online_designer.php') {
        // Field embedding
        doFieldEmbedding();
        // Now run @READONLY functions since they were delayed for Field Embedding
        if (pageHasEmbeddedFields) triggerActionTags();
        // Set the onclick function of all the missing data buttons for only fields that have been embedded
        if (page != 'surveys/index.php') initMissingDataBtns('.rc-field-embed .missingDataButton');
        // Initialize any File Upload fields with @INLINE action tag
        initInlineImages();
        // Now initialize auto-complete drop-downs since they were delayed for Field Embedding
        enableDropdownAutocomplete();
        // Init secondary unique identifier check
        if (typeof secondary_pk != 'undefined' && secondary_pk != '' && $('#form :input[name="'+secondary_pk+'"]').length) {
            // Create onblur event to make an ajax call to check uniqueness
            // Add extra inline onblur attribute for fastest reaction time to prevent submitting form
            var onblur = ($('#form :input[name="'+secondary_pk+'"]').attr('onblur') == null) ? '' : ($('#form :input[name="'+secondary_pk+'"]').attr('onblur')+';');
            $('#form :input[name="'+secondary_pk+'"]').attr('onblur', onblur+'checkSecondaryUniqueField($(this));');
        }
    } else {
        showDescriptiveTextImages();
    }
    // Remove scrollbars on checkbox divs if less than a certain height
    // Array.from(document.querySelectorAll('.check-box-holder')).forEach(function(item) {
    //     if (item.offsetHeight < 600) item.style.overflowY = 'hidden';
    // });
    // if (isSurveyPage) {
    //     Array.from(document.querySelectorAll('.check-box-enhanced-holder')).forEach(function (item) {
    //         if (item.offsetHeight < 1200) item.style.overflowY = 'hidden';
    //     });
    // }
    // Floating matrix headers
    enabledFloatingMatrixHeaders();
    // Set CSS via JS for piped File Upload fields using "inline" - e.g., [field:inline]
    $('#form span.piping_receiver>object>iframe').parent().parent().css({'display':'block','width':'100%'});
    // Hide parts of the page from being printed
    $('#__SUBMITBUTTONS__-tr, #__DELETEBUTTONS__-tr, #__LOCKRECORD__-tr, img.ui-datepicker-trigger').addClass('d-print-none');
    $('#questiontable td.context_msg').parent().addClass('d-print-none');
    // If a text field has a saved value, but then the field's value is manually removed, remove the "hasval" attribute
    // from the field's TR row to prevent issues related to required field detection after submitting the page.
    $('#questiontable tr[sq_id][req=1][hasval=1]').each(function(){
        var fieldOb = $("input[type=text][name='"+$(this).attr('sq_id')+"']", this);
        if (!fieldOb.length) return;
        fieldOb.blur(function(){
           if ($(this).val() == '') removeHasVal($(this).attr('name'));
        });
    });
    // Auto-scroll feature for surveys when selecting a value for a drop-down or radio field while on a mobile device
    if (isSurveyPage && isMobileDevice) autoScroll.init();
    // Warn about back button
    setCookie('redcap_survey_nav_alert', 0);
    if (isSurveyPage && (($('input[name=__page__]').val() == 'undefined' ? 0 : $('input[name=__page__]').val()) * 1) > 1) {
        $(window).on('beforeunload', function(e) {
            if (!window['regularSubmit']) {
                setCookie('redcap_survey_nav_alert', 1);
                console.warn('Please do not use the browser\'s back or forward buttons to navigate surveys!');
            }
        });
    }
    // Remove initial change flag from all inputs
    $('input.calcChangedInitial').one("change", function() {
        $(this).removeClass('calcChangedInitial'); 
    });

    // Fix - On Data Entry page, clicking anywhere in the label of a field that has an embedded checkboxes will toggle the first checkbox option (which is not expected)
    $('label.label-fl.fl').click(function (event) {
        var clickedElement = $(event.target);
        // Below solution is not reliable as there might be more element we need to consider
        /*if (clickedElement[0].tagName != 'LABEL' && clickedElement[0].tagName != 'INPUT' && clickedElement[0].tagName != 'A') {
            event.preventDefault();
        }*/
        // Ignore Outer "label" element click (selecting first input from inner HTML) when element clicked is div or td inside a label
        var htmlElementsArr = ["DIV", "TABLE", "TR", "TD"];
        var valueToCheck = clickedElement[0].tagName;

        var index = $.inArray(valueToCheck, htmlElementsArr);

        if (index >= 0) {
            // Value exists in the array
            event.preventDefault();
        } else {
            // Value does not exist in the array - Do nothing
        }
    });
});
$(window).on('resize', function () {
    enabledFloatingMatrixHeaders();
});

// Is element visible in the browser viewport?
!function(t){var i=t(window);t.fn.visible=function(t,e,o){if(!(this.length<1)){var r=this.length>1?this.eq(0):this,n=r.get(0),f=i.width(),h=i.height(),o=o?o:"both",l=e===!0?n.offsetWidth*n.offsetHeight:!0;if("function"==typeof n.getBoundingClientRect){var g=n.getBoundingClientRect(),u=g.top>=0&&g.top<h,s=g.bottom>0&&g.bottom<=h,c=g.left>=0&&g.left<f,a=g.right>0&&g.right<=f,v=t?u||s:u&&s,b=t?c||a:c&&a;if("both"===o)return l&&v&&b;if("vertical"===o)return l&&v;if("horizontal"===o)return l&&b}else{var d=i.scrollTop(),p=d+h,w=i.scrollLeft(),m=w+f,y=r.offset(),z=y.top,B=z+r.height(),C=y.left,R=C+r.width(),j=t===!0?B:z,q=t===!0?z:B,H=t===!0?R:C,L=t===!0?C:R;if("both"===o)return!!l&&p>=q&&j>=d&&m>=L&&H>=w;if("vertical"===o)return!!l&&p>=q&&j>=d;if("horizontal"===o)return!!l&&m>=L&&H>=w}}}}(jQuery);

// Replace instances of fake object tag
function replaceFakeObjectTag()
{
    if (typeof fakeObjectTag == 'undefined') return;
    $(fakeObjectTag).each(function(){
        var parent = $(this).parent();
        if (typeof parent.html() == 'undefined') return;
        parent.html( parent.html().replace(fakeObjectTag, "object").replace("/"+fakeObjectTag, "/object") );
    });
}

// Embed fields
// All calculations in this method must be done synchronously so that we are able at the end of the method to conclusively establish that field embedding work has been completed
function doFieldEmbedding()
{
    // start field embedding
    if (typeof DescriptivePopups == 'object') DescriptivePopups.doFieldEmbeddingCompleted = false;
    // Is survey page?
    var isSurveyPage = (page == 'surveys/index.php');
    var embeddedFields = new Array();
    var i = 0;
    // Do not perform field embedding on the eConsent certification page (not necessary and actually causes issues with required fields).
    // Prevent this simply by removing the rc-field-embed class, but finish out this function since it is necessary for the whole flow.
    if ($('#econsent_confirm_checkbox_div').length) {
        $('.rc-field-embed').removeClass('rc-field-embed');
    }
    // Deal with survey enhanced choices: Due to labels being duplicated here, remove the embedded field in the original label.
    if (isSurveyPage) {
        $('#questiontable .enhancedchoice .rc-field-embed').each(function(){
            $('#questiontable .rc-field-embed[var="'+$(this).attr('var')+'"]:first').remove();
        });
    }
    // Loop through HTML content and substitute in native page content
    $('#questiontable .rc-field-embed').each(function ()
    {
        var this_field = $(this).attr('var'); // field_name
        // Find the 'real tr' for the field to be relocated
        var source_tr = $("tr[sq_id='" + this_field + "']");
        // Make sure field is not the record ID field. If so, give a warning.
        if (this_field == table_pk) {
            $(this).attr('error','1').html('<span class="text-danger fs11 rc-field-embed-error"><i class="fas fa-exclamation-triangle"></i> '+lang.design_799+' '+lang.design_823+'</span>');
            return;
        }
        // Make sure field is not from another instrument. If so, give a warning.
        if (source_tr.length === 0 || $(this).hasClass('embed-other-form')) {
            $(this).attr('error','1').html('<span class="text-danger fs11 rc-field-embed-error"><i class="fas fa-exclamation-triangle"></i> '+lang.design_799+' "'+this_field+'" '+lang.design_821+'</span>');
            return;
        }
        // Make sure we don't embed a field more than once. Give a warning.
        if (in_array(this_field, embeddedFields)) {
            $(this).attr('error','1').html('<span class="text-danger fs11 rc-field-embed-error"><i class="fas fa-exclamation-triangle"></i> '+lang.design_799+' "'+this_field+'" '+lang.design_800+'</span>');
            return;
        }
        // Make sure the field isn't embedded in itself
        if ($("tr[sq_id='" + this_field + "'] .rc-field-embed[var='" + this_field + "']").length) {
            $(this).attr('error','1').html('<span class="text-danger fs11 rc-field-embed-error"><i class="fas fa-exclamation-triangle"></i> '+lang.design_799+' "'+this_field+'" '+lang.design_833+'</span>');
            return;
        }
        // Do not embed a field in the Context Msg at the top of a form
        if ($("td.context_msg .rc-field-embed[var='" + this_field + "']").length && $(this).parentsUntil('td.context_msg').length) {
            $(this).removeClass('rc-field-embed');
            return;
        }
        // Move the field
        embeddedFields[i++] = this_field;
        var displayIcons = $(this).hasClass('embed-show-icons');
        var displayMissingIcon = (!displayIcons && source_tr.attr('hasmdcval') == '1');
        var align = (source_tr.children('td').length === (isSurveyPage ? 2 : 1)) ? "left": "right";
        var source_data;
        if (source_tr.attr('fieldtype') == 'descriptive') {
            // Descriptive field
            var lastTdDesc = $('tr#' + this_field + '-tr>td:last');
            if (lastTdDesc.length) {
                // Wrap in spans so that children() can pick up text when not using rich text editor
                lastTdDesc.html("<span>"+lastTdDesc.html()+"</span>");
            }
            source_data = lastTdDesc.children();
        } else if (source_tr.attr('mtxgrp')) {
            // Matrix field
            var iconsTd = null;
            var iconsHtml = "";
            // Add Data History & Field Comment Log/Data Resolution Workflow icons
            if (isSurveyPage === false && displayIcons) {
                // Place the icons into a span tag so you can do CSS to control their wrapping
                iconsTd =  $('.rc-field-icons', source_tr);
                // Place the icons into a span tag so you can do CSS to control their wrapping
                var trp = $('table[role="presentation"]:first', source_tr);
                if (trp.length) {
                    // Set icon style
                    var iconStyle = $('.rc-field-icons', this).attr('style');
                    $('.rc-field-icons', this).attr('style', iconStyle+'width:'+iconsTd.outerWidth()+'px !important;');
                    iconsTd =  $('.rc-field-icons', source_tr);
                }
            }
            var header_table = $('#' + source_tr.attr('mtxgrp') + '-mtxhdr-tr').find('table:first').clone();
            header_table.find('td:first').remove();
            var data_table = source_tr.find('table:first').clone();
            data_table.find('td:first').remove();
            source_tr.find('td:last').remove();
            source_tr.find('table:first').remove();
            if (iconsTd !== null) {
                iconsHtml = '<div class="float-left mt-2" style="width:10%;">' + iconsTd.html() + '</div>';
                header_table.css('width', '90%').addClass('float-left');
                data_table.css('width', '90%').addClass('float-left');
            }
            source_data = $("<div class='clearfix'>").append(iconsHtml).append(header_table).append(data_table).append(source_tr.find('.resetLinkParent').clone());
        } else if (align === "right") {
            // Vanilla- take the td.data
            source_data =  $("td.data", source_tr).children();
        } else if ($('a.fileuploadlink, a.filedownloadlink', source_tr).length > 0) {
            // This is a file upload field which needs an exception
            source_data = source_tr.find("td").not('questionnum').find('label').nextAll();
        } else {
            // This rule handles the majority of left-aligned fields
            // Take everything after the div.space separator
            source_data = source_tr.find("td").not('questionnum').find("div.space").nextAll();
        }

        // If we still didn't find anything - then lets log an error and continue
        if (!source_data.length) {
            return true;
        }
        // If has @INLINE action tag, add new class for embedded span
        if (source_tr.hasClass('@INLINE')) $(this).addClass('file-upload-inline-embed');
        // If has @READONLY action tag, add new class for embedded span
        if (source_tr.hasClass('@READONLY')) $(this).addClass('@READONLY');
        // If has @HIDDEN action tag, add new class for embedded span to keep it hidden
        if (source_tr.hasClass('@HIDDEN')
            || (isSurveyPage  && source_tr.hasClass('@HIDDEN-SURVEY'))
            || (!isSurveyPage && source_tr.hasClass('@HIDDEN-FORM'))
        ) {
            $(this).addClass('hide');
        }
        // Adjust width of some non-hidden inputs
        $("input[type!='hidden']", source_data.parent()).each(function (i, e) {
            var type = $(e).prop('type');
            // Left aligned stuff has a rci-left class... going to leave it for now.
            if ($(e).hasClass("rci-left")) $(e).removeClass('rci-left');
            if (type == 'text' && !$(this).hasClass('hasDatepicker') && !$(this).hasClass('hiddenradio')) $(this).css({'max-width': '380px'});
            if (type === 'textarea' || (type == 'text' && !$(this).hasClass('hasDatepicker') && !$(this).hasClass('hiddenradio'))) $(this).css('width', '95%');
        });
        // Move contents of source
        $(this).html(source_data);
        // Add Data History & Field Comment Log/Data Resolution Workflow icons
        if (isSurveyPage === false && (displayIcons || displayMissingIcon)) {
            // Place the icons into a span tag so you can do CSS to control their wrapping
            if (typeof source_tr.attr('mtxgrp') == 'undefined') { // Icons cannot yet be displayed for embedded matrix fields!
                var iconsTd =  $('.rc-field-icons', source_tr);
                // Place the icons into a span tag so you can do CSS to control their wrapping
                var trp = $('table[role="presentation"]:first', source_tr);
                if (trp.length) {
                    // Replace the first TD with the data
                    trp.find('td:first').html(source_data);
                    $(this).html(trp);
                    // Set icon style
                    var iconStyle = $('.rc-field-icons', this).attr('style');
                    $('.rc-field-icons', this).attr('style', iconStyle+'width:'+iconsTd.outerWidth()+'px !important;');
                    // If the Missing icon is the only icon to be displayed, then remove all else in the rc-field-icons box
                    if (displayMissingIcon) {
                        $('.rc-field-icons :not(.missingDataButton)', this).remove();
                        $('.rc-field-icons', this).attr('style', 'padding:0px;margin-left:0px;margin-right:2px;width:14px !important;');
                    }
                }
            }
        }
    });
    // If a field is embedded inside another embedded field, then display an error
    $('#questiontable tr.row-field-embedded .rc-field-embed').each(function(){
        // Re-display the original field
        var tr = $(this).parentsUntil('tr.row-field-embedded').parent();
        var tr_id = tr.attr('sq_id');
        tr.removeClass('hide');
        // If this embedded already has an error, don't go further here
        if ($(this).attr('error') == '1') return;
        // Add warning note
        var this_field = $(this).attr('var');
        $(this).after('<div class="text-danger fs11"><i class="fas fa-exclamation-triangle"></i> '+lang.design_799+' "'+tr_id+'" '+lang.design_801+' {'+this_field+'} '+lang.design_802+' {'+this_field+'} '+lang.design_803+'</div>');
    });
    // Display the form/survey
    displayQuestionTable();
    // Open Save button tooltip	fixed at top-right of data entry forms (re-run this now that the table has been displayed)
    if (i > 0 && page == 'DataEntry/index.php') displayFormSaveBtnTooltip();
    // Granted nothing was done asynchronously within the function
    if (typeof DescriptivePopups == 'object') DescriptivePopups.doFieldEmbeddingCompleted = true;
}

function displayQuestionTable()
{
    if (elementExists(document.getElementById('formtop-div'))) document.getElementById('formtop-div').style.display='none';
    if (elementExists(document.getElementById('questiontable_loading'))) document.getElementById('questiontable_loading').style.display='none';
    if (elementExists(document.getElementById('questiontable'))) document.getElementById('questiontable').style.display='table';
    if (elementExists(document.getElementById('form_response_header'))) document.getElementById('form_response_header').style.display='block';
    if (elementExists(document.getElementById('formtop-div'))) document.getElementById('formtop-div').style.display='block';
    if (elementExists(document.getElementById('inviteFollowupSurveyBtn'))) document.getElementById('inviteFollowupSurveyBtn').style.display='block';
}

// Make Descriptive field image attachments load with delay (for server performance reasons)
// This will be triggered by doBranching().
function showDescriptiveTextImages() {
    $(function() {
        var imgClass = 'rc-dt-img';
        var delayIncrement = 1;
        $('img.' + imgClass + ':visible').each(function () {
            var this_image = this;
            var src = $(this_image).attr('src') || '';
            if (!src.length > 0) {
                var lsrc = $(this_image).attr('lsrc') || '';
                if (lsrc.length > 0) {
                    setTimeout(function () {
                        this_image.src = lsrc;
                    }, 100 * (delayIncrement++));
                }
            }
            $(this_image).removeClass(imgClass);
        });
    });
}

// Floating matrix headers
var matrices = [];
function enabledFloatingMatrixHeaders()
{
    var isSurvey = is_survey();
    if (!$('#questiontable').length) return;

    var form = $('#questiontable');
    var formPosLeft = form.offset().left;
    var formWidth = form.width();
    var offset = isSurvey ? 0 : $('#west').width();
    var mtx_bgcolor = $('.labelmatrix ').css('background-color');

    // Destroy existing scroll-triggered function to reset it
    $(window).off("scroll", scrollHandler);

    // If no visible matrixes, then stop here
    if (!$('.headermatrix:visible').length) return;

    // Destroy all existing (in case this function has already been run)
    $('.floatMtxHdr').remove();

    // create floating headers
    var i = 0;
    $('.headermatrix:visible').each(function () {
        var header = $(this);
        var floatingHeader = $('<div></div>').append(header.clone());
        matrices[i++] = {
            "header": header,
            "floatingHeader": floatingHeader
        };
        floatingHeader
            .addClass('floatMtxHdr')
            .css({
                position: 'fixed',
                display: 'none',
                top: '-5px',
                left: (formPosLeft)+'px',
                width: formWidth,
                'border': '1px solid #dddddd',
                'padding-bottom': '5px',
                'padding-left': formWidth - header.width(),
                'background-color': mtx_bgcolor
            })
            .attr('data-padding-left', (formWidth - header.width())+'px')
            .attr('data-extra', ($('td.labelrc.questionnum.col-1').width() + $('td.matrix_first_col_hdr').width() + 10.7) + 'px');
        // Remove IDs of columns to prevent conflict with originals
        floatingHeader.find('td').each(function(){
            if ($(this).prop('id') != '') $(this).removeAttr('id');
        })
        // Add to body (surveys) or center div (data entry form)
        $(isSurvey ? 'body' : 'div#center').append(floatingHeader);
    });
    // MLM: Refresh floating headers
    if (REDCap && REDCap.MultiLanguage && REDCap.MultiLanguage.updateFloatingMatrixHeaders) {
        REDCap.MultiLanguage.updateFloatingMatrixHeaders();
    }

    // decide when to show each floating header based on scroll
    $(window).scroll(scrollHandler);

    // Trigger scroll to display floating headers if just disappeared
    window.scrollTo(window.scrollX, window.scrollY - 1);
    window.scrollTo(window.scrollX, window.scrollY + 1);
}
var scrollHandler = function()
{
    var isSurvey = (page == 'surveys/index.php');
    var offsetTop = (!isSurvey && $('.rcproject-navbar:visible').length) ? $('.rcproject-navbar').outerHeight() : 0;
    var scrollTop = $(window).scrollTop();
    for (var i = 0; i < matrices.length; i++) {
        try {
            var header = matrices[i].header;
            var matrixGroup = header.attr('hdrmtxgrp');
            var inViewport = false;
            $('#questiontable tr.mtxfld[mtxgrp="'+matrixGroup+'"]').each(function(){
                if ($(this).visible(true)) {
                    inViewport = true;
                    return;
                }
            });
            var floatingHeader = matrices[i].floatingHeader;
            if (inViewport) {
                var headerTop = header.offset().top;
                var lastRow = $('#questiontable tr.mtxfld[mtxgrp=' + matrixGroup + ']:visible:last');
                var lastRowTop = lastRow.offset().top;
                if (scrollTop > headerTop && scrollTop <= lastRowTop) {
                    var top = 0;
                    if (scrollTop > (lastRowTop - floatingHeader.height())) top = -(scrollTop - (lastRowTop - floatingHeader.height()) + 2);		// + 2 to prevent floating header from overlapping last row
                    floatingHeader.css({
                        display: 'block',
                        top: offsetTop + top + 'px'
                    });
                } else {
                    floatingHeader.css({
                        display: 'none'
                    });
                }
            } else {
                floatingHeader.css({
                    display: 'none'
                });
            }
        } catch(e){}
    }
}

// Update checkboxes for piping
function updatePipingCheckboxes(ob) {
    var name = $(ob).attr('name').substring(8, $(ob).attr('name').length);
    var labelsChecked = new Array(), valsChecked = new Array(), i=0;
    var labelsUnchecked = new Array(), valsUnchecked = new Array(), j=0;
    var isMatrix = $(ob).parent().hasClass('choicematrix');
    var matrixName = isMatrix ? $(ob).parentsUntil('table').parent().parentsUntil('tr').parent().attr('mtxgrp') : "";
    // Get labels of all choices checked
    $('form#form input[name="__chkn__'+name+'"]').each(function(){
        var thisCode = $(this).attr('code');
        var thisLabel = isMatrix ? $('#matrixheader-'+matrixName+'-'+thisCode).text().trim() : $(this).parent().text().trim();
        var thisChecked = $(this).prop('checked');
        var thisCheckedText = thisChecked ? window.lang.global_143 : window.lang.global_144;
        var thisCheckedVal = thisChecked ? '1' : '0';
        if (thisChecked) {
            labelsChecked[i] = thisLabel;
            valsChecked[i] = thisCode;
            i++;
        } else {
            labelsUnchecked[j] = thisLabel;
            valsUnchecked[j] = thisCode;
            j++;
        }
        // Set "checked"/"unchecked" for any using [checkbox(code)]
        $(piping_receiver_class_field_js+event_id+'-'+name+'-choice-'+thisCode+'-label').html(thisCheckedText);
        $(piping_receiver_class_field_js+event_id+'-'+name+'-choice-'+thisCode+'-value').html(thisCheckedVal);
    });
    // If value is a Missing Data Code
    if (missing_data_codes_check && $('#'+name+'_MDLabel:visible').length) {
        labelsChecked[0] = $('#'+name+'_MDLabel').attr('label');
        valsChecked[0] = $('#'+name+'_MDLabel').attr('code');
    }
    // Set value for all piping receivers on page
    if (labelsChecked.length == 0) labelsChecked[0] = missing_data_replacement_js;
    if (labelsUnchecked.length == 0) labelsUnchecked[0] = missing_data_replacement_js;
    if (valsChecked.length == 0) valsChecked[0] = missing_data_replacement_js;
    if (valsUnchecked.length == 0) valsUnchecked[0] = missing_data_replacement_js;
    $(piping_receiver_class_field_js+event_id+'-'+name+'-checked-checked-label').html(labelsChecked.join(', '));
    $(piping_receiver_class_field_js+event_id+'-'+name+'-checked-unchecked-label').html(labelsUnchecked.join(', '));
    $(piping_receiver_class_field_js+event_id+'-'+name+'-checked-checked-value').html(valsChecked.join(', '));
    $(piping_receiver_class_field_js+event_id+'-'+name+'-checked-unchecked-value').html(valsUnchecked.join(', '));
}

// Radio fields
function updatePipingRadiosDo(ob) {
    // Remove "___radio" from end of name
    var name = ob.attr('name').substring(0, ob.attr('name').length-8);
    var label = ob.parent().html();
    // Remove radio input from label
    if (ob.attr('label') != null) {
        label = ob.attr('label');
    } else {
        label = label.substring(label.indexOf('>')+2);
    }
    // Remove any embedded fields from the label, if applicable
    try {
        if ($(label).find("span.rc-field-embed").length) {
            var element = $("<div>" + label + "</div>");
            element.find("span.rc-field-embed").remove();
            label = element.html();
        }
    } catch (e) { }
    if (label.substring(0, 7) == '<label ') {
        // In case the label is still inside a <label> tag, get the contents of the tag
        label = $(label).first().html();
    }
    var val = (ob.val() != '') ? ob.val() : missing_data_replacement_js;
    updatePipingRadiosDoValLabel(name,val,label);
}
function updatePipingRadiosDoValLabel(name,val,label) {
    // Set value for all piping receivers on page
    $(piping_receiver_class_field_js+event_id+'-'+name).html(label);
    $(piping_receiver_class_field_js+event_id+'-'+name+'-label').html(label);
    $(piping_receiver_class_field_js+event_id+'-'+name+'-value').html(val);
    // Update drop-down options separately via ajax
    try{ updatePipingDropdowns(name,val); } catch(e) { }

}
function updatePipingRadios(selector) {
    $(selector).click(function(){
        updatePipingRadiosDo($(this));
    });
}

// Drop-down fields
function updatePipingDropdownsDo($el, name, val, label) {
        if (missing_data_codes_check && in_array($el.val(), missing_data_codes)) {
            // Remove parentheses from label
            var posLastParen = label.lastIndexOf('(');
            if (posLastParen > 0) {
                label = label.substr(0, posLastParen).trim();
            }
        }
        var isblank = (val == missing_data_replacement_js);
        // Set value for all piping receivers on page
        $(piping_receiver_class_field_js+event_id+'-'+name).html(label);
        $(piping_receiver_class_field_js+event_id+'-'+name+'-label').html(label);
        $(piping_receiver_class_field_js+event_id+'-'+name+'-value').html(val);
        const $fieldLabel = $(piping_receiver_class_field_js+event_id+'-'+name+'-field-label');
        if ($fieldLabel.length) {
            const labelHtml = filter_tags($('#label-'+name).html());
            $fieldLabel.html(labelHtml);
        }
        if (isblank) {
            $(piping_receiver_class_field_js+event_id+'-'+name+'.pipingrec-hideunderscore').html('');
            $(piping_receiver_class_field_js+event_id+'-'+name+'-label.pipingrec-hideunderscore').html('');
            $(piping_receiver_class_field_js+event_id+'-'+name+'-value.pipingrec-hideunderscore').html('');
        }
        // Update drop-down options separately via ajax
        updatePipingDropdowns(name,val);

}
function updatePipingDropdownsPre(selector) {
    $(selector).on('change', function(){
        var $this = $(this)
        var name = $this.attr('name');
        // Find selected option to get its label
        var isblank = ($this.val() == '');
        var label = !isblank ? $("form#form select[name='" + name + "'] option:selected").text() : missing_data_replacement_js;
        var val = !isblank ? $this.val() : missing_data_replacement_js;
        updatePipingDropdownsDo($this, name, val, label)
    });
}

// Text fields
function updatePipingTextFields(selector) {
    $(selector).blur(function(){
        if ($(this).hasClass('autosug-search')) {
            var idname = $(this).prop('id').split('-');
            var name = idname[0];
            var val = $('#'+$(this).prop('id')+'-span').val();
        } else {
            var name = $(this).attr('name');
            var val = $(this).val();
        }
        // If no value, set as 6 underscores
        var isblank = (val == '');
        val = isblank ? missing_data_replacement_js : filter_tags(val);
        // Does this field have the @RICHTEXT action tag? If so, don't do nl2br()
        if (!$('#'+name+'-tr').hasClass('@RICHTEXT')) {
            val = nl2br(val);
        }
        // Start piping on page
        $(piping_receiver_class_field_js+event_id+'-'+name).html(val);
        $(piping_receiver_class_field_js+event_id+'-'+name+'-label').html(val);
        const $fieldLabel = $(piping_receiver_class_field_js+event_id+'-'+name+'-field-label');
        if ($fieldLabel.length) {
            const labelHtml = filter_tags($('#label-'+name).html());
            $fieldLabel.html(labelHtml);
        }
        if (isblank) {
            $(piping_receiver_class_field_js+event_id+'-'+name+'.pipingrec-hideunderscore').html('');
            $(piping_receiver_class_field_js+event_id+'-'+name+'-label.pipingrec-hideunderscore').html('');
        }
        // Convert time/datetime to am/pm format
        if ($(piping_receiver_class_field_js+event_id+'-'+name+'-ampm').length) {
            // Split into date and time components
            var valDate = "";
            var valTime = val;
            if (val != missing_data_replacement_js) {
                if (val.indexOf(" ") > -1) {
                    valComp = val.split(" ");
                    valDate = valComp[0];
                    valTime = valComp[1];
                }
                valTime = format_time(valTime);
            }
            $(piping_receiver_class_field_js+event_id+'-'+name+'-ampm').html( trim(valDate+" "+valTime) );
            if (isblank) {
                $(piping_receiver_class_field_js+event_id+'-'+name+'-ampm.pipingrec-hideunderscore').html('');
            }
        }
        // Convert date/datetime to year, month, or day component
        if ($(piping_receiver_class_field_js+event_id+'-'+name+'-year').length) {
            $(piping_receiver_class_field_js+event_id+'-'+name+'-year').html( val == missing_data_replacement_js ? val : year(convertDateToYMD(this)) );
        }
        if ($(piping_receiver_class_field_js+event_id+'-'+name+'-month').length) {
            $(piping_receiver_class_field_js+event_id+'-'+name+'-month').html( val == missing_data_replacement_js ? val : month(convertDateToYMD(this)) );
        }
        if ($(piping_receiver_class_field_js+event_id+'-'+name+'-day').length) {
            $(piping_receiver_class_field_js+event_id+'-'+name+'-day').html( val == missing_data_replacement_js ? val : day(convertDateToYMD(this)) );
        }
        if (isblank) {
            $(piping_receiver_class_field_js+event_id+'-'+name+'-year.pipingrec-hideunderscore').html('');
            $(piping_receiver_class_field_js+event_id+'-'+name+'-month.pipingrec-hideunderscore').html('');
            $(piping_receiver_class_field_js+event_id+'-'+name+'-day.pipingrec-hideunderscore').html('');
        }
        // Update drop-down options separately via ajax
        updatePipingDropdowns(name,val);
    });
}

// File Upload and Signature fields
function updatePipingFileFields(selector) {
    $(selector).change(function(){
        var name = $(this).attr('name');
        var val = $(this).val();
        var isblank = (val == '');
        var label = !isblank ? nl2br(filter_tags($('#form a.filedownloadlink[name="'+name+'"] .fu-fn').attr('vf'))) : missing_data_replacement_js;
        val = !isblank ? nl2br(filter_tags(val)) : missing_data_replacement_js;
        // Set value for all piping receivers on page
        $(piping_receiver_class_field_js+event_id+'-'+name).html(val);
        $(piping_receiver_class_field_js+event_id+'-'+name+'-value').html(val);
        $(piping_receiver_class_field_js+event_id+'-'+name+'-label').html(label);
        const $fieldLabel = $(piping_receiver_class_field_js+event_id+'-'+name+'-field-label');
        if ($fieldLabel.length) {
            const labelHtml = filter_tags($('#label-'+name).html());
            $fieldLabel.html(labelHtml);
        }
        // Pipe the inline image, if applicable (set with delay to give the source field time to update first)
        setTimeout(function(){
            if ($('a#'+name+'-link.filedownloadlink').length) {
                var filename = $('#form a.filedownloadlink[name="'+name+'"] .fu-fn').attr('vf');
                var fileext = getfileextension(filename.toLowerCase());
                var srcDownload = $('a#'+name+'-link.filedownloadlink').attr('href');
                var srcView = srcDownload.replace('DataEntry/file_download.php','DataEntry/image_view.php')
                                         .replace('DataEntry%2Ffile_download.php','DataEntry%2Fimage_view.php'); // Change to image_view.php
                let html = '<i class="fa-solid fa-eye-slash text-danger" title="' + window.lang.docs_1101 + '"></i><span class="visually-hidden" data-rc-lang="docs_1101">' + window.lang.docs_1101 + '</span>';
                if (in_array(fileext, valid_image_suffixes)) {
                    // image
                    html = "<img src='"+srcView+"' style='max-width:100%;' alt='"+filename+"'>";
                } else if (fileext == 'pdf') {
                    // pdf
                    // html = "<object data='"+srcView+"' type='application/pdf' style='width:100%;max-width:100%;height:300px;'><iframe src='"+srcView+"' style='width:100%;max-width:100%;height:300px;border:none;'></iframe></object>";
                    html = renderInlinePdfContainer(srcView);
                }
                let link = "<a target='_blank' href='"+srcDownload+"' style='text-decoration:underline;'>"+filename+"</a>";
                let missing = false;
                if (val == missing_data_replacement_js) {
                    html = link = missing_data_replacement_js;
                    missing = true;
                }
                $(piping_receiver_class_field_js+event_id+'-'+name+'-inline').each(function() {
                    // Need to add unique ids for PDF resizer support
                    if (missing) {
                        $(this).html(html);
                    }
                    else {
                        const $html = $(html);
                        $html.attr('data-file-id', getFileId(name));
                        $(this).html('');
                        $(this).append($html);
                    }
                });
                $(piping_receiver_class_field_js+event_id+'-'+name+'-link').html(link);
                if (isblank) {
                    $(piping_receiver_class_field_js+event_id+'-'+name+'-link.pipingrec-hideunderscore').html('');
                }
                if (fileext == 'pdf') addFileEnhancements();
            }
        },100);
        // Update drop-down options separately via ajax
        updatePipingDropdowns(name,val);
    });
}

// Check if any checkboxes in a group are checked
function anyChecked(formname, field) {
    var numChecked = 0;
    var domfld = document.forms[formname].elements[field];
    // If field doesn't exist, it must be a "descriptive" field
    try {
        var fldexists = (domfld != null);
    } catch(e) {
        try {
            var fldexists = (domfld.value != null);
        } catch(e) {
            var fldexists = false;
        }
    }
    if (!fldexists) return 0;
    var chkLen2 = domfld.length;
    if (chkLen2) {
        for (var x = 0; x < chkLen2; x++) {
            if (document.forms[formname].elements[field][x].checked) numChecked++;
        }
    } else {
        if (document.forms[formname].elements[field].checked) numChecked++;
    }
    return numChecked;
}

//Functions used in Branching Logic for hiding/showing fields
function checkAll(flag, formname, field) {
    var this_code;
    eval("var chkLen=document."+formname+"."+field+".length;");
    if (chkLen) {
        for (var x = 0; x < chkLen; x++) {
            if (flag == 1) {
                eval("document."+formname+"."+field+"[x].checked = true;");
            } else {
                eval("document."+formname+"."+field+"[x].checked = false;"
                    +"this_code = document."+formname+"."+field+"[x].getAttribute('code');");
                try {
                    eval("document." + formname + ".__chk__" + field.substring(8) + "_RC_" + replaceDotInCheckboxCoding(this_code) + ".value='';");
                } catch(e) {
                    document.getElementById("id-__chk__" + field.substring(8) + "_RC_" + replaceDotInCheckboxCoding(this_code)).value = '';
                }
            }
        }
    } else {
        if (flag == 1) {
            eval("document."+formname+"."+field+".checked = true;");
        } else {
            eval("document."+formname+"."+field+".checked = false;"
                +"this_code = document."+formname+"."+field+".getAttribute('code');");
            try {
                eval("document." + formname + ".__chk__" + field.substring(8) + "_RC_" + replaceDotInCheckboxCoding(this_code) + ".value='';");
            } catch(e) {
                document.getElementById("id-__chk__" + field.substring(8) + "_RC_" + replaceDotInCheckboxCoding(this_code)).value = '';
            }
        }
    }
}

// Return boolean for whether DOM element exists
function elementExists(domfld) {
    try {
        return (domfld != null);
    } catch(e) {
        return false;
    }
}

// Function called when a calculated field's value gets changed via JavaScript
function calcChangeCheck(newval, oldval, this_field, isOnPageLoad) {
    if (typeof isOnPageLoad == 'undefined') isOnPageLoad = false;
    // Set data change flag
    if (!isOnPageLoad && newval != oldval) setDataEntryFormValuesChanged(this_field);
    // Add visual marker after field
    $(function(){
        var domfld = $('form#form input[name="'+this_field+'"]');
        domfld.removeClass('calcChangedInitial');
        var savedval = domfld.attr('sv');
        if (savedval+"" !== newval+"") {
            var nspwa = isOnPageLoad && $('tr[sq_id="' + this_field + '"]').hasClass('\@SAVE-PROMPT-EXEMPT-WHEN-AUTOSET');
            domfld.addClass(nspwa ? 'calcChangedInitial' : 'calcChanged');
            // domfld.change();
        } else {
            domfld.removeClass('calcChanged');
        }
    });
}

function indexEmbeddedFields() {
    var index = {};
    var spans = document.querySelectorAll('.rc-field-embed[var]');
    for (var m = 0; m < spans.length; m++) {
        if (typeof spans[m].attributes.var == 'undefined') continue;
        var val = spans[m].attributes.var.value;
        if (typeof index[val] == 'undefined') index[val] = [];
        index[val].push(spans[m]);
    }
    return index;
}

// Create a lookup table that connects field names to the elements that embed those fields 
var embeddedFieldsByVar = indexEmbeddedFields();

// Apply branching logic and show/hide table row based upon its evaluation
var pageHasEmbeddedFields = (Object.keys(embeddedFieldsByVar).length > 0);
function applyBranchingLogicResult(thisField, bypassEraseFieldPrompt, blResult, parentField) {
	if (typeof parentField == 'undefined') parentField = false;
	let showHideAction = null;
	if (blResult == false) {
		//#region HIDE ROW
		let fieldLen = 0;
		let hasMissingDataCode = false;
		const domField = document.forms['form'][thisField] ?? null;
		const fieldExists = (domField != null);
		if (fieldExists && domField.value != null) fieldLen = domField.value.length;
		if (!fieldExists) {
			// Checkbox fields (might also be a "descriptive" field)
			fieldLen = anyChecked('form', '__chkn__'+thisField);
		}
		// Randomization fields CANNOT be hidden after randomization has happened, so stop here.
		if (in_array(thisField, randomizationCriteriaFieldList ?? [])) {
			return false;
		}
		// Hide icon for this field if displayed because it has a value but is not hidden by branching logic
		if (BranchingLogic.overrideEraseValuePrompt && !parentField && document.getElementById('icon-showfield-'+thisField) != null) {
			document.getElementById('icon-showfield-'+thisField).remove();
		}
		// HIDE ROW
		if (fieldLen > 0) do {
			// Determine if we should erase the value or prompt to erase the value
			if (showEraseValuePrompt && !BranchingLogic.overrideEraseValuePrompt) {
				// Survey - always erase
				if (is_survey()) {
					eraseValueInFieldToBeHidden(thisField);
					showHideAction = 'hide';
				}
				else {
					// Determine if we should prompt the user before erasing the value
					hasMissingDataCode = (missing_data_codes_check && typeof domField != 'undefined' && domField.value != '' && in_array(domField.value, missing_data_codes));
					if (hasMissingDataCode) {
						// Erase without asking
						eraseValueInFieldToBeHidden(thisField);
						showHideAction = 'hide';
					}
					else {
						// Prompt to erase
						BranchingLogic.fieldsToErase.push(thisField);
						break; // out of do/while
					}
				}
			} 
			else {
				// If a field would normally be hidden by branching logic but isn't because it has a value and because the project-level feature
				// "Prevent branching logic from hiding fields that have values" is enabled,
				// then add a visual indicator that the field would normally be hidden.
				if (BranchingLogic.overrideEraseValuePrompt && !parentField && !is_survey() && document.getElementById('icon-showfield-'+thisField) == null) {
					branchingLogicAddShownDespiteIndicator(thisField);
				}
			}
			if (parentField != false) {
				applyBranchingLogicResult(parentField, bypassEraseFieldPrompt, true);
			}
		}
		while (false) else {
			showHideAction = 'hide';
			if (elementExists(document.getElementById(thisField+'-tr'))) {
				document.getElementById(thisField+'-tr').style.display='none';
				// On data entry forms, notify when a field that was supposed to be hidden is now hidden
				const iconShowFieldIndicator = document.getElementById('icon-showfield-'+thisField);
				if (!is_survey() && iconShowFieldIndicator != null) {
					iconShowFieldIndicator.remove();
					simpleDialog(RCView.$tt('data_entry_736'), '<i class="fa-solid fa-eye-slash text-secondary me-1"></i>' + RCView.tt('data_entry_735'));
				}
			}
		}
		// If any fields are embedded inside this field that is being hidden, then also check if their value needs to be cleared out too
		if (pageHasEmbeddedFields && elementExists(document.getElementById(thisField+'-tr'))) {
			var a = document.querySelector('#' + thisField + '-tr').querySelectorAll('.rc-field-embed');
			for (var i = 0; i < a.length; i++) {
				if ($(a[i]).parents('.piping_receiver').length > 0) continue; // skip when inside a piping receiver
				applyBranchingLogicResult(a[i].getAttribute('var'), bypassEraseFieldPrompt, false, thisField);
			}
		}
		//#endregion
	} 
	else {
		//#region SHOW ROW
		var showit = true;
		showHideAction = 'show';
		if (is_survey()) {
			// Survey page: Treat differently since it contains fields on the form that might need to remain hidden (because of multi-paging)
			if (document.getElementById(thisField+'-tr').getAttribute('class') != null) {
				if (document.getElementById(thisField+'-tr').getAttribute('class').indexOf('hidden') > -1) {
					// If row has class 'hidden', then keep hidden
					showit = false;
				}
			}
		}
		else {
			branchingLogicRemoveShownDespiteIndicator(thisField);
		}
		// Do not show it if it has any @HIDDEN action tag
		if (showit && document.getElementById(thisField+'-tr').getAttribute('class') != null) {
			var rowClasses = document.getElementById(thisField+'-tr').getAttribute('class').split(" ");
			if (in_array('@HIDDEN', rowClasses)
				|| (!is_survey() && in_array('@HIDDEN-FORM', rowClasses))
				|| (is_survey() && in_array('@HIDDEN-SURVEY', rowClasses))
			) {
				showit = false;
			}
		}
		if (showit) {
			// Now show the row, if applicable
			document.getElementById(thisField+'-tr').style.display = 'table-row';
			// If any fields are embedded inside this field that is being shown, then by default make all embedded fields visible (their individual branching will be checked below, if applicable)
			if (pageHasEmbeddedFields) {
				const a = document.querySelector('#' + thisField + '-tr').querySelectorAll('.rc-field-embed');
				if (a.length > 0) {
					for (let i = 0; i < a.length; i++) {
						a[i].style.display = 'inline';
					}
					// Re-run doBranching() for ALL after processing all fields here because there might be cases where some embedded fields with branching are not getting evaluated
					BranchingLogic.runAllAgain = true;
				}
			}
		}
		//#endregion
	}
	// If field is embedded inside another field, then show/hide the embedded field based on their branching logic
	if (pageHasEmbeddedFields) {
		const a = embeddedFieldsByVar[thisField];
		if (a) {
			for (let i = 0; i < a.length; i++) {
				a[i].style.display = (showHideAction == 'hide') ? 'none' : 'inline';
			}
		}
	}
}

function branchingLogicAddShownDespiteIndicator(field) {
	if (document.getElementById('icon-showfield-'+field) != null) return;
	// Create icon
	const icon = document.createElement('i');
	icon.setAttribute('id', 'icon-showfield-'+field);
	icon.setAttribute('class', 'fa-solid fa-eye position-absolute opacity35');
	icon.setAttribute('style', 'cursor:pointer;top:5px;right:5px;');
	icon.setAttribute('title', lang.data_entry_601);
	icon.setAttribute('data-rc-lang-attrs', 'title=data_entry_601');
	// Add icon to table row (or embedded field)
	const embedded = document.querySelector('.rc-field-embed[var="'+field+'"]');
	if (embedded) {
		embedded.style.position = 'relative';
		embedded.appendChild(icon);
	}
	else {
		const tr = document.getElementById(field+'-tr');
		tr.style.position = 'relative';
		tr.appendChild(icon);
	}
}

function branchingLogicRemoveShownDespiteIndicator(field) {
	const el = document.getElementById('icon-showfield-'+field);
	if (el) el.remove();
}

// Remove "hasval" attribute from table row
function removeHasVal(this_field)
{
    if (document.getElementById(this_field+'-tr').getAttribute("hasval") != null) {
        document.getElementById(this_field+'-tr').removeAttribute("hasval");
    }
}

// Action Tags: Function that is run on forms and surveys to perform actions based on tags in the Field Annotation text
function triggerActionTags() {
    // Is this a survey page?
    var isSurvey = (page == 'surveys/index.php');

    // Note: @HIDDEN tags are handled via CSS and also inside doBranching()
    // on forms/surveys, so we don't need to force them to be hidden here.

    // DISABLES ANY FIELD THAT CONTAINS @READONLY
    // Disable survey and form
    $("#questiontable tr.\\@READONLY").disableRowActionTag();
    $("#questiontable tr.\\@READONLY.row-field-embedded").each(function(){
        $('.rc-field-embed[var="'+$(this).attr('sq_id')+'"]').disableRowActionTag();
    });
    // Disable survey only
    if (isSurvey) {
        $("#questiontable tr[sq_id].\\@READONLY-SURVEY").disableRowActionTag();
        $("#questiontable tr.\\@READONLY-SURVEY.row-field-embedded").each(function(){
            $('.rc-field-embed[var="'+$(this).attr('sq_id')+'"]').disableRowActionTag();
        });
    }
    // Disable form only
    else {
        $("#questiontable tr[sq_id].\\@READONLY-FORM").disableRowActionTag();
        $("#questiontable tr.\\@READONLY-FORM.row-field-embedded").each(function(){
            $('.rc-field-embed[var="'+$(this).attr('sq_id')+'"]').disableRowActionTag();
        });
    }
}

// Disable row via @READONLY action tag
(function ( $ ) {
    $.fn.disableRowActionTag = function() {
        var tr = this;
        if (tr.length < 1) return;
        // Disable buttons and all text links (ignore images surrounded by links, we just want text links)
        $('a:not(a:has(img))', tr).each(function(){
            $(this).attr('onfocus', '');
            if ($(this).hasClass('fileuploadlink')) {
                $(this).attr('href', 'javascript:;').attr('onclick', 'return false;');
            }
        });
        // Disable sliders
        $("[id^=sldrmsg-]", tr).css('visibility','hidden');
        $("[id^=slider-]", tr).attr('onmousedown', '').slider("disable");
        // Perform the rest multiple times using a delay in case of race conditions of other JS on page
        var loops = [100, 1000, 2000];
        for (var i = 0; i < loops.length; i++) {
            setTimeout(function () {
                // Disable or hide certain buttons/icons
                $('button, .ui-datepicker-trigger', tr).hide();
                $("[id^=slider-]", tr).slider("disable");
                $('.tox-toolbar button', tr).prop("disabled", true);
                // Disable all inputs row, trigger blur (to update any piping), and gray out whole row.
                // This needs to be delayed slightly in case a URL-prefilled survey is prefilling a Secondary Unique Field, which will become editable again.
                $('input, select, textarea, .tox-toolbar button', tr).prop("disabled", true);
            }, loops[i]);
        }
    };
}( jQuery ));

// Improve onchance event of text/notes fields to trigger branching logic before leaving the field
// (so that we don't skip over the next field, which becomes displayed)
var currentFocusTextField = null;
var bypassBranchingErrors = false;
function improveBranchingInitIntervalCheck() {
    // If field not set, turn on checker
    if (currentFocusTextField === null) {
        improveBranchingDisableIntervalCheck();
        return;
    }
    $.doTimeout('improveBranchingCheck', 2000, function(){
        // If this is unset by the end of the delay, then stop it
        if (currentFocusTextField === null) {
            improveBranchingDisableIntervalCheck();
            return;
        }
        // Obtain the initial value of field during onfocus
        var initValue = currentFocusTextField.data('val');
        if (initValue != currentFocusTextField.val()) {
            // Set new initial value
            currentFocusTextField.data('val', currentFocusTextField.val());
            // Do branching
            bypassBranchingErrors = true;
            doBranching(currentFocusTextField.attr('name'),true);
            bypassBranchingErrors = false;
        }
        // Wait another interval and re-run
        improveBranchingInitIntervalCheck();
    });
}
function improveBranchingDisableIntervalCheck() {
    currentFocusTextField = null;
    bypassBranchingErrors = false;
    try {
        $.doTimeout('improveBranchingCheck', false);
    } catch(e) { }
}
function improveBranchingOnchange() {
    $('form#form input[type="text"], form#form textarea').focus(function(){
        // Only set this variable for text/notes fields that will trigger branching
        currentFocusTextField = null;
        var attr = $(this).attr('onchange');
        if (typeof attr !== typeof undefined && attr !== false && attr.indexOf('doBranching(') > -1) {
            currentFocusTextField = $(this);
            // Set original value at the time of focus
            $(this).data('val', currentFocusTextField.val());
        }
        // Initiate checking value at an interval
        improveBranchingInitIntervalCheck();
    });
    $('form#form input[type="text"], form#form textarea').blur(function(){
        improveBranchingDisableIntervalCheck();
    });
}

// Run when clicking a checkbox on a survey/form
function checkboxClick(field, value, that, e, maxchecked) {
    var elementClicked = '';
    var isCheckboxClicked = false;
    var targetName = $(e.target).attr('name');
    try {
        elementClicked = e.target.nodeName.toLowerCase();
        isCheckboxClicked = (elementClicked == 'input' && $(e.target).attr('type') == 'checkbox');
    } catch(error) { }
    // Deal with other fields embedded inside the checkbox labels
    if (typeof targetName != 'undefined' && field != '__chkn__'+targetName && targetName.indexOf('___radio') < 0) {
        // If the field being clicked is not the checkbox field, then we have an embedded field being clicked in the parent checkbox's label
        e.stopPropagation();
        // if ($('#'+field+'-tr .enhancedchoice label.selectedchkbox[comps="'+field+',code,'+value+'"] .rc-field-embed').length) {
        //     console.log('field embedded inside an enhanced choice');
        // }
    } else if (typeof targetName == 'undefined' && $('.rc-field-embed[var="'+field+'"]').length && elementClicked == 'label' && $(that).hasClass('mc')) {
        // Use the label ID to determine this embedded checkbox's ID, and then check/uncheck the associated checkbox (since it will not automatically get checked/unchecked simply by clicking its label here)
        var labelIdParts = $(that).prop('id').split('-');
        var embeddedCheckboxId = '#id-__chk__'+labelIdParts[1]+'_RC_'+labelIdParts[2];
        var isEmbeddedCheckboxClicked = $(embeddedCheckboxId).prop('checked');
        // If checkbox is disabled, then do not check it
        if ($(embeddedCheckboxId).prop('disabled')) {
            e.stopPropagation();
            return;
        }
        // Make sure the checkbox stays checked
        $(embeddedCheckboxId).prop('checked', !isEmbeddedCheckboxClicked);
        // If this is a @NONEOFTHEABOVE checkbox option, then skip stopPropagation() because it prevents the checkbox from being checked
        var fieldHasNoneOfTheAboveOption = in_array(labelIdParts[1], noneOfTheAboveFields);
        var clickedChoiceIsNoneOfTheAboveOption = in_array(labelIdParts[1]+':'+labelIdParts[2], noneOfTheAboveFieldsChoices);
        if (!clickedChoiceIsNoneOfTheAboveOption || !fieldHasNoneOfTheAboveOption) {
            e.stopPropagation();
        }
        if (fieldHasNoneOfTheAboveOption && !clickedChoiceIsNoneOfTheAboveOption) {
            // If this choice is not the @NONEOFTHEABOVE choice, click the checkbox option to manually trigger the @NONEOFTHEABOVE prompt (do it twice because of weird doubling issue)
            $(embeddedCheckboxId).trigger('click').trigger('click');
        } else if (clickedChoiceIsNoneOfTheAboveOption && !($(embeddedCheckboxId).prop('checked') && $('input[type="checkbox"][name="__chkn__'+field+'"]:checked').length > 1)) {
            // If this choice IS the @NONEOFTHEABOVE choice AND it is being unchecked, click the checkbox option to manually but ONLY if we need to trigger the @NONEOFTHEABOVE prompt (can cause double dialog if triggered generally)
            $(embeddedCheckboxId).trigger('click');
        }
    } else if (elementClicked == 'button' && $(elementClicked).hasClass('today-now-btn')) {
        // If clicking the Today/Now button for a date/time field embedded in a checkbox
        e.stopPropagation();
    } else if (elementClicked == 'a' || (elementClicked == 'span' && typeof targetName == 'undefined')) {
        // If clicking on a link inside the checkbox label
        e.stopPropagation();
    }
    try {
        if (isCheckboxClicked) {
            var checkbox = $(that);
        } else if (elementClicked == 'label') {
            var checkbox = $(that).parent().find('input[type="checkbox"]:first');
        } else {
            return;
        }
        var wasJustChecked = checkbox.prop('checked');
        if (wasJustChecked && maxchecked > 0 && $('input[type="checkbox"][name="__chkn__'+field+'"]:checked').length > maxchecked) {
            // Uncheck it
            checkbox.prop('checked',false);
            // Set value of hidden field to blank
            $('#form input[name="__chk__'+field+'_RC_'+replaceDotInCheckboxCoding(value)+'"]').val('');
            // Show temporary note about it being unchecked
            setPositionMaxcheckedActionTagAlert(checkbox);
            return;
        }
        // Set value of hidden field
        $('#form input[name="__chk__'+field+'_RC_'+replaceDotInCheckboxCoding(value)+'"]').val( wasJustChecked ? value : '' );
        calculate(field);
        doBranching(field);
    } catch(e) { }
}

function setPositionMaxcheckedActionTagAlert(that) {
    var enhanced_choice_fudge = that.hasClass('enhancedchoice') ? '250' : '100';
    var maxchoice_label = $('#maxchecked_tag_label');
    maxchoice_label.show().position({
        my:        "left top",
        at:        "left+"+enhanced_choice_fudge+" top",
        of:        that
    });
    setTimeout(function(){
        maxchoice_label.hide();
    },4000);
}

// Implement @NONEOFTHEABOVE action tag for checkboxes
var noneOfTheAboveFields = new Array();
var noneOfTheAboveFieldsChoices = new Array();
function noneOfTheAboveAlert(field, choicesCsv, regchoicesCsv, langOkay, langCancel) {
    // Add field to array
    noneOfTheAboveFields[noneOfTheAboveFields.length] = field;
    // Initialize values
    var noneOfTheAboveCurrentField = '';
    var noneOfTheAboveNotCurrentField = '';
    var choices = choicesCsv.split(",");
    var regchoices = regchoicesCsv.split(",");
    for (var i=0; i<choices.length; i++) {
        if (noneOfTheAboveCurrentField != '') noneOfTheAboveCurrentField += ', ';
        noneOfTheAboveCurrentField += 'input[name="__chkn__'+field+'"][code="'+choices[i]+'"]';
        // Add field to array
        noneOfTheAboveFieldsChoices[noneOfTheAboveFieldsChoices.length] = field+':'+choices[i];
    }
    for (var i=0; i<regchoices.length; i++) {
        if (noneOfTheAboveNotCurrentField != '') noneOfTheAboveNotCurrentField += ', ';
        noneOfTheAboveNotCurrentField += 'input[name="__chkn__'+field+'"][code="'+regchoices[i]+'"]';
    }
    // Click trigger for ALL options except the NONEOFTHEABOVE option
    $(noneOfTheAboveNotCurrentField).click(function(){
        // Deselect the NONEOFTHEABOVE option(s)
        $(noneOfTheAboveCurrentField).each(function(){
            if ($(this).prop('checked')) {
                var thisCode = $(this).attr('code');
                $(this).prop('checked', false);
                $(this).parent().find('input[type="hidden"]').val('');
                if ($("#questiontable .rc-field-embed[var='"+field+"']").length) {
                    $('#questiontable .rc-field-embed[var="'+field+'"] .enhancedchoice label.selectedchkbox[comps="'+field+',code,'+thisCode+'"]').removeClass('selectedchkbox').addClass('unselectedchkbox');
                } else {
                    $('#'+field+'-tr .enhancedchoice label.selectedchkbox[comps="'+field+',code,'+thisCode+'"]').removeClass('selectedchkbox').addClass('unselectedchkbox');
                }
                setDataEntryFormValuesChanged(field);
                try{ updatePipingCheckboxes(this); }catch(e){ }
            }
        });
    });
    // Click trigger for NONEOFTHEABOVE option
    $(noneOfTheAboveCurrentField).click(function(){
        var regChoicesChecked = 0;
        var thisCode = $(this).attr('code');
        let getInnerHtmlTextExcludeScreenReaderOnlyText = function (element) {
            return $(element)
                .contents()
                .map(function () {
                    if (this.nodeType === Node.TEXT_NODE) {
                        return this.nodeValue;
                    } else if (this.nodeType === Node.ELEMENT_NODE && !$(this).hasClass('visually-hidden')) {
                        return getInnerHtmlTextExcludeScreenReaderOnlyText(this);
                    }
                    return '';
                }).get().join('');
        };

        // If no other choices are selected, then do nothing
        if ($('input[name="__chkn__'+field+'"]:not([code="'+thisCode+'"]):checked').length == 0) {
            return;
        }
        // Place the choice text inside the dialog
        if ($('#label-'+field+'-'+thisCode).length) {
            $('#noneOfTheAboveLabelDialog').html(getInnerHtmlTextExcludeScreenReaderOnlyText($('#label-' + field + '-' + thisCode)));
        } else {
            $('#noneOfTheAboveLabelDialog').html(getInnerHtmlTextExcludeScreenReaderOnlyText($('#matrixheader-'+$('#'+field+'-tr').attr('mtxgrp')+'-'+thisCode)));
        }
        // Make sure it was checked (in case using @MAXCHECKED action tag)
        $('input[name="__chkn__'+field+'"][code="'+thisCode+'"]').prop('checked',true);
        $('#maxchecked_tag_label').hide();
        // Dialog
        $('#noneOfTheAboveDialog').dialog({ bgiframe: true, modal: true, width: 450,
            title: window.lang.data_entry_412,
            close: function(){
                // If close dialog, uncheck the checkbox and set the hidden input as blank
                var thisOb = $('input[name="__chkn__'+field+'"][code="'+thisCode+'"]');
                thisOb.prop('checked', false);
                thisOb.parent().find('input[type="hidden"]').val('');
                if ($("#questiontable .rc-field-embed[var='"+field+"']").length) {
                    $('#questiontable .rc-field-embed[var="'+field+'"] .enhancedchoice label.selectedchkbox[comps="'+field+',code,'+thisCode+'"]').removeClass('selectedchkbox').addClass('unselectedchkbox');
                } else {
                    $('#'+field+'-tr .enhancedchoice label.selectedchkbox[comps="'+field+',code,'+thisCode+'"]').removeClass('selectedchkbox').addClass('unselectedchkbox');
                }
                try{ updatePipingCheckboxes(thisOb); }catch(e){ }
            },
            buttons: [
                { text: window.lang.global_53, click: function() {
                        $(this).dialog('close');
                    }},
                {text: window.lang.data_entry_417, click: function() {
                        // Okay button: Uncheck all other checkbox options and set their hidden input as blank
                        var thisOb = $('input[name="__chkn__'+field+'"]:not([code="'+thisCode+'"]):checked');
                        thisOb.each(function(){
                            var thisCode2 = $(this).attr('code');
                            $(this).prop('checked', false);
                            $(this).parent().find('input[type="hidden"]').val('');
                            if ($("#questiontable .rc-field-embed[var='"+field+"']").length) {
                                $('#questiontable .rc-field-embed[var="'+field+'"] .enhancedchoice label.selectedchkbox[comps="'+field+',code,'+thisCode2+'"]').removeClass('selectedchkbox').addClass('unselectedchkbox');
                            } else {
                                $('#'+field+'-tr .enhancedchoice label.selectedchkbox[comps="'+field+',code,'+thisCode2+'"]').removeClass('selectedchkbox').addClass('unselectedchkbox');
                            }
                        });
                        // Make sure the checked checkbox has its value set
                        $('input[name="__chkn__'+field+'"][code="'+thisCode+'"]').prop('checked', true);
                        $('input[name="__chk__'+field+'_RC_'+replaceDotInCheckboxCoding(thisCode)+'"]').val(thisCode);
                        // Do other bookkeeping triggered by data change
                        try{ updatePipingCheckboxes(thisOb); }catch(e){ }
                        setDataEntryFormValuesChanged(field);
                        calculate(field);
                        doBranching(field);
                        $(this).dialog('destroy');
                    }}
            ] });
    });
}

// Implement the @WORDLIMIT and @CHARLIMIT action tags
function wordcharlimit(field, type, goal, msg) {
    var ob = $('input[name="'+field+'"], textarea[name="'+field+'"]');
    ob.after('<div id="wordcharcounter-'+type+'-'+field+'" class="wordcharcounter"></div>');
    ob.counter({ type: type, count: 'down', msg: msg, goal: goal, target: '#wordcharcounter-'+type+'-'+field });
    // For unknown reasons, sometime multiple divs with id=undefined_counter will appear for the first instance of the action tag on the page. Remove all but the last to compensate.
    $('div#undefined_counter:not(:last-child)').remove();
}

// In case data entry forms don't fully load, which would prevent the save button group drop-downs
// from working, use this replacement method to make sure the drop-down opens regardless.
function openSaveBtnDropDown(ob,e) {
    e.stopPropagation();
    var btngroup = $(ob).parent();
    if (btngroup.hasClass('show')) {
        // Close it
        btngroup.removeClass('show');
        btngroup.find('.dropdown-menu').removeClass('show');
    } else {
        // Open it
        btngroup.addClass('show');
        btngroup.find('.dropdown-menu').addClass('show');
    }
}

// If any enhanced choice fields are hidden due to branching logic, then make sure their UI shows them as unselected
function updateHiddenEnhancedChoices() {
    $('label.selectedradio:not(:visible)').removeClass('selectedradio');
    $('label.selectedchkbox:not(:visible)').removeClass('selectedchkbox').addClass('unselectedchkbox');
}

// Action when selecting an Enhanced Choice radio or checkbox
var justClickedEnhancedChoice = false;
function enhanceChoiceSelect(ob, e, maxchecked) {
    // Ignore if clicked on a text field or notes field that is embedded inside the enhanced choice
    nodeName = '';
    try {
        var nodeName = e.target.nodeName;
    } catch(error) {
        return;
    }
    var elementClicked = nodeName.toLowerCase();
    if (elementClicked == 'input' || elementClicked == 'textarea') return;
    // Set flag to deal with choices embedded in other choices
    if (justClickedEnhancedChoice) return;
    justClickedEnhancedChoice = true;
    setTimeout("justClickedEnhancedChoice = false;", 50);
    // Get attributes
    var label = $(ob);
    var attr = label.attr('comps').split(',');
    var type = attr[1] == 'code' ? 'checkbox' : 'radio';
    // Set the element class
    if (type == 'checkbox') {
        var input = $('input[name="__chkn__'+attr[0]+'"]['+attr[1]+'="'+attr[2]+'"]');
        if (!input.length) {
            // PROMIS inputs
            input = $('input[name="'+attr[0]+'"]');
        }
        if (input.prop('checked')) {
            label.removeClass('selectedchkbox').addClass('unselectedchkbox');
        } else {
            // Deal with maxchecked action tag
            if (isNumeric(maxchecked) && maxchecked > 0 && $('input[name="__chkn__'+attr[0]+'"]:checked').length >= maxchecked) {
                // Since we hit the max, stop here to prevent it from being checked
                setPositionMaxcheckedActionTagAlert(label.parent());
                return;
            }
            label.removeClass('unselectedchkbox').addClass('selectedchkbox');
        }
    } else {
        var input = $('input[name="'+attr[0]+'___radio"]['+attr[1]+'="'+attr[2]+'"]');
        if (!input.length) {
            // PROMIS inputs
            input = $('input[name="'+attr[0]+'"]['+attr[1]+'="'+attr[2]+'"]');
        }
        // First, set all unchecked
        label.parentsUntil('div.enhancedchoice_wrapper').parent().find('div.enhancedchoice label').removeClass('selectedradio');
        // Now set the one selected one
        label.addClass('selectedradio');
    }
    // Trigger the original input
    //input.trigger('click');
    setDataEntryFormValuesChanged(attr[0]);
}

// On forms/surveys, make sure dropdowns don't get too wide so that they create horizontal scrollbar
function shrinkWideDropDowns() {
    // Get width of viewport
    var winWidth = $(window).width();
    // If we don't have a horizontal scrollbar, then do nothing
    if ($(document).width() <= winWidth) return;
    // Loop through each drop-down
    $('form#form select.x-form-text').each(function(){
        var dd = $(this);
        var posDdLeft = dd.offset().left
        var posDdRight = posDdLeft + dd.width();
        // If drop-down spills off page, then resize it
        if (posDdRight > winWidth) dd.css('width','95%');
    });
}

// Enable all action tags
function enableActionTags() {
    // If we're viewing a response on a form but not in edit mode, then stop here
    if ($('#edit-response-btn').length && $('#SurveyActionDropDown').length && getParameterByName('editresp') != '1') return;
    // If they can't save the page, do not run auto-fill the fields with values
    if (!$('button[name="submit-btn-saverecord"]').length) return;
    // Track any changes made
    var changes = {};
    // Enable NOW/TODAY action tags
    $("#questiontable tr.\\@NOW, #questiontable tr.\\@TODAY, #questiontable tr.\\@NOW-SERVER, #questiontable tr.\\@TODAY-SERVER, #questiontable tr.\\@NOW-UTC, #questiontable tr.\\@TODAY-UTC").each(function(){
        var name = $(this).attr('sq_id');
        var input = $('#questiontable input[name="'+name+'"]');
        var fv = (input.attr('fv') == null) ? '' : input.attr('fv');
        var dateFVs = new Array('date_mdy', 'date_dmy', 'date_ymd');
        var nspwa = $(this).hasClass("\@SAVE-PROMPT-EXEMPT-WHEN-AUTOSET");
        // Add value if doesn't already have a value
        if (input.val() == '') {
            if (fv == 'time') {
                // NOW for time fields
                if ($(this).hasClass("\@NOW-SERVER")) {
                    document.forms['form'].elements[name].value = mid(now,12,5);
                } else {
                    document.forms['form'].elements[name].value = currentTime('both', false, $(this).hasClass("\@NOW-UTC"));
                }
            } else if (fv == 'time_hh_mm_ss') {
                // NOW for time fields
                if ($(this).hasClass("\@NOW-SERVER")) {
                    document.forms['form'].elements[name].value = right(now,8);
                } else {
                    document.forms['form'].elements[name].value = currentTime('both', true, $(this).hasClass("\@NOW-UTC"));
                }
            } else if (fv == 'time_mm_ss') {
                // NOW for time fields
                if ($(this).hasClass("\@NOW-SERVER")) {
                    document.forms['form'].elements[name].value = right(now,5);
                } else {
                    document.forms['form'].elements[name].value = right(currentTime('both', true, $(this).hasClass("\@NOW-UTC")), 5);
                }
            } else if ($(this).hasClass("\@NOW-SERVER")) {
                // NOW-SERVER for datetime fields (if detect a date field, then fall back to inserting date instead of datetime)
                var showSeconds = (fv == '' || fv.indexOf('datetime_seconds') === 0);
                if (fv.indexOf('_dmy') > -1) {
                    var thisNow = now_dmy;
                } else if (fv.indexOf('_mdy') > -1) {
                    var thisNow = now_mdy;
                } else {
                    var thisNow = now;
                }
                if (in_array(fv, dateFVs)) thisNow = thisNow.substring(0,10);
                if (!showSeconds)  thisNow = thisNow.substring(0,16);
                document.forms['form'].elements[name].value = thisNow;
            } else if ($(this).hasClass("\@NOW-UTC")) {
                // NOW for datetime fields (if detect a date field, then fall back to inserting date instead of datetime)
                var thisNow = getCurrentDate(fv, true)+' '+currentTime('both',(fv == '' || fv.indexOf('datetime_seconds') === 0), true);
                if (in_array(fv, dateFVs)) thisNow = thisNow.substring(0,10);
                document.forms['form'].elements[name].value = thisNow;
            } else if ($(this).hasClass("\@NOW")) {
                // NOW for datetime fields (if detect a date field, then fall back to inserting date instead of datetime)
                var thisNow = getCurrentDate(fv)+' '+currentTime('both',(fv == '' || fv.indexOf('datetime_seconds') === 0));
                if (in_array(fv, dateFVs)) thisNow = thisNow.substring(0,10);
                document.forms['form'].elements[name].value = thisNow;
            } else if ($(this).hasClass("\@TODAY-SERVER")) {
                // TODAY-SERVER for date fields
                if (fv.indexOf('_dmy') > -1) {
                    var thisToday = today_dmy;
                } else if (fv.indexOf('_mdy') > -1) {
                    var thisToday = today_mdy;
                } else {
                    var thisToday = today;
                }
                document.forms['form'].elements[name].value = thisToday;
            } else if ($(this).hasClass("\@TODAY-UTC")) {
                // TODAY-UTC for date fields
                document.forms['form'].elements[name].value = getCurrentDate(fv, true);
            } else if ($(this).hasClass("\@TODAY")) {
                // TODAY for date fields
                document.forms['form'].elements[name].value = getCurrentDate(fv);
            }
            input.addClass(nspwa ? 'calcChangedInitial' : 'calcChanged');
            // Increment changes count
            changes[name] = true;
        }
    });
    // Enable LATITUTE/LONGITUDE action tags
    var changesGPS = 0; // Not used!?
    $("#questiontable tr.\\@LATITUDE, #questiontable tr.\\@LONGITUDE").each(function(){
        var name = $(this).attr('sq_id');
        // Disable field
        $('#questiontable input[name="'+name+'"]').prop('readonly',true);
        // Add GPS value
        if (document.forms['form'].elements[name].value == '') {
            if (getGeolocation(($(this).hasClass("\@LATITUDE") ? 'latitude' : 'longitude'), name, 'form') > 0) {
                changes[name] = true;
            }
        }
    });
    // Trigger branching and calculations if changes were made
    Object.keys(changes).forEach(function(name) { 
        if (!$('#questiontable tr[sq_id="'+name+'"]').hasClass('\@SAVE-PROMPT-EXEMPT-WHEN-AUTOSET')) {
            setDataEntryFormValuesChanged(name);
        }
        // Note: This may have never worked as intended before, because name was not in scope
        setTimeout(function(){try{calculate(name, true);doBranching(name);}catch(e){}},50);
    });
}

// Obtain the latitute or longitude of the user (direction = 'latitude' or 'longitude')
// and place the value inside an input field with specified 'input_name'.
// Note: It will *only* add the lat/long if the input is blank/empty.
function getGeolocation(direction,input_name,form_name,overwrite) {
    if (direction == null || input_name == null || direction == '' || input_name == '') return 0;
    if (overwrite == null) overwrite = false;
    // Get the position
    if (geoPosition.init()){
        geoPosition.getCurrentPosition(function(p){
            // Set form and input
            if (form_name == null) form_name = 'form';
            var myinput = document.forms[form_name].elements[input_name];
            // Make sure this is a textarea or input
            if (myinput.type != 'text') return;
            // Add lat or long to input
            if (overwrite == true || myinput.value == '') {
                if (direction == 'latitude') {
                    myinput.value = p.coords.latitude;
                } else if (direction == 'longitude') {
                    myinput.value = p.coords.longitude;
                }
                // Call calculations/branching
                try{calculate(input_name);doBranching(input_name);}catch(e){}
                $('#questiontable input[name="'+input_name+'"]').addClass('calcChanged');
            }
        },function(){ },{enableHighAccuracy:true});
        return 1;
    }
    return 0;
}

// AUTO-COMPLETE FOR DROP-DOWNS: Loop through drop-down fields on the form/survey and enable auto-complete for them
function enableDropdownAutocomplete(addEvents) {
    if (typeof addEvents == 'undefined') addEvents = true;
    // Class to add to select box once auto-complete has been enabled
    var selectClass = "rc-autocomplete-enabled";
    // Loop through all SELECT fields
    $('select.rc-autocomplete:not(.'+selectClass+')').each(function(){
        // If missing name attribute, then ignore
        if ($(this).attr('name') == null) return;
        // Elements
        if ($('.rc-field-embed[var="'+$(this).attr('name')+'"]').length) {
            // Field is embedded, so make the field embedding span the container here (instead of the main table row)
            var $tr = $('.rc-field-embed[var="'+$(this).attr('name')+'"]');
        } else {
            // Not embedded
            var $tr = $(this).parents('tr:first');
        }
        var $dropdown = $('select:first', $tr);
        var $input = $('input.rc-autocomplete:first', $tr);
        var $button = $('button.rc-autocomplete:first', $tr);
        // Add class to denote that drop-down already has auto-complete enabled
        $dropdown.addClass(selectClass);
        var ddwidth = $dropdown.width();
        // Make input width same as original drop-down
        if ($tr.css('display') != 'none' && ddwidth > 10) {
            $input.width( ddwidth );
        } else {
            // Drop-down is hidden by branching logic, so clone it to get its width
            var ddclone = $dropdown.clone();
            ddclone.css("visibility","hidden").appendTo('body');
            $input.width( ddclone.width() );
            ddclone.remove();
        }
        // Add event bindings
        if (addEvents) {
            // If put focus/click on blank input, open the full list
            $input.bind('focus click', function () {
                if ($(this).val() == '') {
                    $input.autocomplete('search', '');
                }
            });
            // Prevent form submission via Enter key in input
            $input.keydown(function (e) {
                if (e.which == 13) return false;
            });
            // When user changes autocomplete input to blank value
            $input.blur(function () {
                var object_clicked_local = object_clicked;
                var thisval = $(this).val();
                var ddval = $dropdown.val();
                if (thisval == '') {
                    if (ddval != '') {
                        $dropdown.val('').trigger('change');
                    }
                } else {
                    var isValid = false;
                    var valueToSelect = '';
                    $('option', $dropdown).each(function () {
                        if ($(this).text() == thisval) {
                            isValid = true;
                            valueToSelect = $(this).val();
                            return false;
                        }
                    });
                    // Check if the new value is valid
                    if (!isValid &&
                        // object_clicked_local will be null if we just blurred out of input (as opposed to clicking)
                        (object_clicked_local == null
                            // If we just clicked on the autocomplete list (to choose an option), then don't throw an error.
                            || (object_clicked_local != null && object_clicked_local.parents('ul.ui-autocomplete').length == 0))) {
                        // Not a valid value
                        simpleDialog(lang.survey_681, lang.global_287, null, null, "$('#" + $(this).attr('id') + "').focus().autocomplete('search',$('#" + $(this).attr('id') + "').val());");
                    } else {
                        // Set drop-down to same value and trigger change
                        $dropdown.val(valueToSelect);
                        if (ddval != valueToSelect) {
                            $dropdown.trigger('change');
                        }
                    }
                }
            });
            // Open full list when click button/arrow icon
            $button.mousedown(function (event) {
                $(this).attr('listopen', ($('.ui-autocomplete:visible').length ? '1' : '0'));
            });
            $button.click(function (event) {
                var targetTag = event.target.nodeName.toLowerCase();
                // Get list_open attribute from button
                var list_open = $(this).attr('listopen');
                if (list_open == '1') {
                    // Hide the autocomplete list
                    $('.ui-autocomplete').hide();
                    // Change value of listopen attribute
                    $(this).attr('listopen', '0');
                } else {
                    // If the drop-down contains more than 1000 choices, prevent it from trying to display the full list, which may be really slow.
                    if ($('option', $dropdown).length >= 1000) {
                        // Only show popover if clicked down-arrow img (not clicked item in full list)
                        if (targetTag == 'img') {
                            $(this).popover({ container: $tr, content: '<div class="fs11"><i class="fas fa-info-circle"></i> <b>'+lang.global_248+'</b> '+lang.global_249+' <b class="text-primary">'+lang.global_250+'</b>'+lang.period+'</div>', html: true, placement: 'right' }).popover('show');
                            setTimeout(function(){
                                $('#'+$tr.attr('id')+' .popover.show').addClass('rc-autocomplete-popover').removeClass('show');
                            }, 10000);
                        }
                        $input.focus();
                        return;
                    }
                    // If click the down arrow icon, put cursor inside text box and open the full list (force minLength=0 temporarily in order to do this)
                    $input.autocomplete("option", "minLength", 0);
                    $input.focus();
                    $input.autocomplete("search", "");
                    // Now that the drop-down has been fully opened, we need to reset minLength to re-enable performance limitations when searching
                    if ($input.val() == '') {
                        var numChoices = $('option', $dropdown).length;
                        var minLength;
                        if (numChoices <= 200) {
                            minLength = 0;
                        } else if (numChoices <= 500) {
                            minLength = 1;
                        } else if (numChoices <= 2000) {
                            minLength = 2;
                        } else {
                            minLength = Math.min(4, Math.ceil(numChoices/1000));
                        }
                        $input.autocomplete("option", "minLength", minLength);
                    }
                    // Change value of list_open attribute
                    $(this).attr('listopen', '1');
                }
                // Hide any progress popovers
                $('.rc-autocomplete-popover').remove();
                setTimeout(function(){ $('.rc-autocomplete-popover').remove(); }, 10);
            });
        }
        // When page loads, add existing value's label to input field
        if ($dropdown.val() != "") {
            var saved_val_text = $("option:selected", $dropdown).text();
            $input.val(saved_val_text).attr('value',saved_val_text); // Also set attr() in case using Randomization, which replaces text inside TD cell.
        }
        // Extract options from dropdown for jQueryUI autocomplete
        var list = $dropdown.children();
        var x = [];
        for (var i = 0; i<list.length; i++){
            var this_opt_val = list[i].value;
            if (this_opt_val != '') {
                x.push({ value: html_entity_decode(list[i].innerHTML), code: this_opt_val });
            }
        }
        // As the size of the option list increases, increase the minLength up to a max.
        // This is required to prevent fields with hundreds/thousands of options from being unreasonably slow.
        var minLength;
        var numChoices = x.length;
        if (numChoices <= 200) {
            minLength = 0;
        } else if (numChoices <= 500) {
            minLength = 1;
        } else if (numChoices <= 2000) {
            minLength = 2;
        } else {
            minLength = Math.min(4, Math.ceil(numChoices/1000));
        }
        // Initialize jQueryUI autocomplete
        $input.autocomplete({
            source: x,
            minLength: minLength,
            select: function (event, ui) {
                $dropdown.val(ui.item.code);
                $button.click();
                // $dropdown.change();
                $dropdown[0].dispatchEvent(new Event('change')); // Use vanilla JS to trigger onchange
                // Get field name (starts with "rc-ac-input_")
                var field_name = $input.attr('id').substring(12);
                setDataEntryFormValuesChanged(field_name);
            }
        })
        // Add escape character as HTML character code before the label because a single dash will turn into a divider
        .data('ui-autocomplete')._renderItem = function( ul, item ) {
        if (item.label == '-' || item.label == "\u2014" || item.label == "\u2013") {
            item.label = "&#27; " + item.label;
        }
        return $("<li></li>")
            .data("item", item)
            .append(item.label)
            .appendTo(ul);
        };
    });
}

// Select the radio button or checkbox inside "this" div/object (doesn't work on IE8 and below)
function sr(ob,e) {
    ob = $(ob);
    // Ignore if the radio button itself was clicked
    try {
        var nodeName = e.target.nodeName;
    } catch(error) {
        return;
    }
    var elementClicked = nodeName.toLowerCase();
    if (elementClicked == 'input') return;
    // Auto-click the radio/checkbox
    var isRadio = $('input[type="radio"]', ob).length;
    // if (isRadio && $('.rc-field-embed[var="'+$('input[type="radio"]:first', ob).prop('name').replace('___radio','')+'"]').length) {
        // Embedded radio
        // console.log($('input[type="radio"]:first', ob).prop('name')+' = '+$('input[type="radio"]:first', ob).prop('value'));
        //console.log('input[name="'+$('input[type="radio"]:first', ob).prop('name')+'"][value="'+$('input[type="radio"]:first', ob).prop('value')+'"]');
    //} else
    if (isRadio) {
        // Normal radio
        $('input[type="radio"]:first', ob).trigger('click');
    } else {
        // Checkbox
        var chkbox = $('input[type="checkbox"]:first', ob);
        var hidden = $('input[type="hidden"]:first', ob);
        var chkbox_checked = !chkbox.prop('checked');
        var chkbox_code = chkbox.attr('code');
        // Manually set the value of the hidden input field (because for some reason, having jQuery trigger click doesn't set this)
        hidden.val( (chkbox_checked ? chkbox_code : '') );
        // Click the checkbox
        chkbox.trigger('click');
    }
}

// Set autocomplete for BioPortal ontology search for ALL fields on a page
function initAllWebServiceAutoSuggest() {
    $('input.autosug-ont-field').each(function(){
        initWebServiceAutoSuggest($(this).attr('name'));
    });
}

// Set autocomplete for BioPortal ontology search for a field
function initWebServiceAutoSuggest(field_name,retriggerClick) {
    if ($('input#'+field_name+'-autosuggest').length < 1) return;
    // Check if autocomplete has been enabled already for this field
    if ($('input#'+field_name+'-autosuggest').hasClass('ui-autocomplete-input')) return;
    // If the data entry page is locked or is a non-editable response, then don't enable this feature
    if (   (!$('#__SUBMITBUTTONS__-tr').length && page != 'surveys/index.php')
        || ($('#__SUBMITBUTTONS__-tr').length && $('#__SUBMITBUTTONS__-tr').css('display') == 'none')
        || ($('#__LOCKRECORD__').length && $('#__LOCKRECORD__').prop('checked'))
    ) {
        return;
    }
    // If we need to retrigger the click (due to Online Designer not initiating this function on page load), then trigger click
    if (retriggerClick != null && retriggerClick == '1') {
        $('input[name="'+field_name+'"]').removeAttr('onclick');
        initWebServiceAutoSuggest(field_name);
        $('input[name="'+field_name+'"]').trigger('click');
        return;
    }
    // Set URLs for ajax
    if (page == 'surveys/index.php') {
        var url = dirname(app_path_webroot.substring(0,app_path_webroot.length-1))+'/surveys/index.php?s='+getParameterByName('s')+'&__passthru='+encodeURIComponent('DataEntry/web_service_auto_suggest.php')+'&field='+field_name;
        var url_cache = dirname(app_path_webroot.substring(0,app_path_webroot.length-1))+'/surveys/index.php?s='+getParameterByName('s')+'&__passthru='+encodeURIComponent('DataEntry/web_service_cache_item.php');
    } else {
        var url = app_path_webroot+'DataEntry/web_service_auto_suggest.php?pid='+pid+'&field='+field_name;
        var url_cache = app_path_webroot+'DataEntry/web_service_cache_item.php?pid='+pid;
    }
    // Init auto-complete
    $('input#'+field_name+'-autosuggest').autocomplete({
        source: url,
        minLength: 2,
        delay: 0,
        search: function( event, ui ) {
            // Show progress icon
            $('#'+field_name+'-autosuggest-progress').show();
        },
        open: function( event, ui ) {
            // Hide progress icon
            $('#'+field_name+'-autosuggest-progress').hide('fade',{ },200);
            // If user backspaces to remove all search characters, then make sure the auto-suggest list stays hidden (buggy)
            if ($('input#'+field_name+'-autosuggest').val().length == 0) {
                $('.ui-autocomplete, .ui-menu-item').hide();
            }
        },
        focus: function( event, ui ) {
            // Prevent it from putting the value in the search input (default)
            return false;
        },
        select: function( event, ui ) {
            // Prevent user from clicking "No results were returned" option
            if (strip_tags(ui.item.value) === "["+lang.report_builder_87+"]") {
                return false;
            }
            // Add raw value to original input field
            $('input[name="'+field_name+'"]').val(ui.item.value);
            // Put the label into the span
            $('#'+field_name+'-autosuggest-span').val(ui.item.preflabel);
            // Trigger blur on search input to force it to hide
            $('input#'+field_name+'-autosuggest').trigger('blur');
            // Make ajax call to store the label
            if (page != 'Design/online_designer.php') {
                $.post(url_cache, { service: ui.item.service, category: ui.item.cat, value: ui.item.value, label: ui.item.preflabel });
            }
            // Execute branching and calculations, just in case
            try{ calculate(field_name);doBranching(field_name); }catch(e){ }
            return false;
        }
    })
        .data('ui-autocomplete')._renderItem = function( ul, item ) {
        return $("<li></li>")
            .data("item", item)
            .append("<a>"+item.label+"</a>")
            .appendTo(ul);
    };
    // When user clicks or focuses on original input, put cursor in the search box
    $('#'+field_name+'-autosuggest-span, input[name="'+field_name+'"]').bind('click focus', function(){
        var current_val = $('#'+field_name+'-autosuggest-span').val();
        // Temporarily hide original input and display search input
        $('input[name="'+field_name+'"]').hide();
        $('#'+field_name+'-autosuggest-span').hide();
        $('input#'+field_name+'-autosuggest').val(current_val).show().focus();
        $('#'+field_name+'-autosuggest-instr').show();
    });
    // Re-display original input after choosing selection or leaving search field
    $('input#'+field_name+'-autosuggest').bind('blur', function(){
        $(this).hide();
        $('#'+field_name+'-autosuggest-instr, #'+field_name+'-autosuggest-progress').hide();
        $('input[name="'+field_name+'"], #'+field_name+'-autosuggest-span').show();
        // If auto-suggest value was removed or is empty, make sure the other inputs are empty as well so that it gets erased if already saved.
        if ($(this).val().length == 0) {
            $('#'+field_name+'-autosuggest-span').val('');
            $('input[name="'+field_name+'"]').val('');
            // Execute branching and calculations, just in case
            try{ calculate(field_name);doBranching(field_name); }catch(e){ }
        }
    });
}

// Open dialog with embedded video
function openEmbedVideoDlg(video_url,unknown_video_service,field_name,video_custom_html) {
    if (typeof video_custom_html == 'undefined') video_custom_html = '';
    var dlgid = 'rc-embed-video-dlg_'+field_name;
    var vidid = 'rc-embed-video_'+field_name;
    var vidwidth = 750;
    var vidheight = 500;
    if (unknown_video_service) {
        if (isIOS) {
            var rc_embed_html = '<video id="'+vidid+'" width="'+vidwidth+'" height="'+vidheight+'" playsinline controls><source src="'+video_url+'" type="video/'+getfileextension(video_url.toLowerCase())+'"></source></video>';
        } else {
            var rc_embed_html = '<embed id="'+vidid+'" src="'+video_url+'" width="'+vidwidth+'" height="'+vidheight+'" scale="aspect" controller="true" autostart="0" autostart="false"></embed>';
        }
    } else if (video_custom_html != '') {
        var rc_embed_html = html_entity_decode(video_custom_html);
    } else {
        var rc_embed_html = '<iframe id="'+vidid+'" src="'+video_url+'" type="text/html" frameborder="0" allowfullscreen width="'+vidwidth+'" height="'+vidheight+'"></iframe>';
    }
    // Add content to dialog and open it
    initDialog(dlgid);
    // Strings are from global lang
    // Close = calendar_popup_01
    // Video = data_entry_610
    $('#'+dlgid)
        .show().html(rc_embed_html)
        .dialog({ height: (vidheight+130), width: (isMobileDevice ? $(window).width() : (vidwidth+60)), open:function(){ fitDialog(this); }, close:function(){ $(this).dialog('destroy'); $('#'+dlgid).remove(); },
            buttons: [{ text: window.lang.calendar_popup_01, click: function(){ $(this).dialog('close'); } }], title: window.lang.data_entry_610, bgiframe: true, modal: true
        });
    // Mobile only: Resize video
    if (isMobileDevice) {
        $('#'+vidid).width( $('body').width()-40 );
        $('#'+vidid).height( $('#'+dlgid).height()-10 );
    }
    // Reload VidYard JS (needed to initialize any videos added after page load)
    if (video_custom_html != '') initVidYardJS();
}

// Reload VidYard JS (needed to initialize any videos added after page load)
function initVidYardJS() {
    if ($('.vidyard-player-embed:visible').length) {
        loadJS(app_path_webroot + 'Resources/js/Libraries/vidyard_v4.js');
    }
}


// Add or remove a password mask from a text input field
// Object "ob" should be passed to the function as the jQuery object of the input field.
// Boolean "add", in which false=remove password mask.
function passwordMask(ob, add) {
    // Remove any date/time picker widgets from input
    try { ob.datepicker('destroy'); }catch(e){ }
    try { ob.datetimepicker('destroy'); }catch(e){ }
    try { ob.timepicker('destroy'); }catch(e){ }
    ob.removeClass('hasDatepicker').unbind();
    // Clone input field and replace it
    ob.clone().attr('type', (add ? 'text' : 'password')).insertAfter(ob);
    ob.remove();
    // Reactivate any widgets whose connection to object gets lost with cloning
    initWidgets();
}

// Matrix field ranking validation function
function matrix_rank(crnt_val,crnt_var,grid_vars) {
    // Reset validation flag on page
    $('#field_validation_error_state').val('0');
    // array of all field_names within matrix group
    // gv[0]=>'w1',gv[1]=>'w2',gv[2]=>'w3',...
    var grid_vars = grid_vars.split(',');
    var id, i;
    var rank_remove_label = $('#matrix_rank_remove_label');
    var remove_label_time = 2500;
    // loop through other variables within this matrix group
    for (i = 0; i < grid_vars.length; i++) {
        if (crnt_var !== grid_vars[i]) {
            id = "mtxopt-"+grid_vars[i]+"_"+crnt_val;
            id = id.replace(/\./g,'\\.');
            if ($("#"+id).is(":checked")) {
                // Uncheck the input
                radioResetVal(grid_vars[i],'form');
                // Add temporary "value removed" label
                rank_remove_label.show().position({
                    my:        "center top",
                    at:        "center top+10",
                    of:        $("#"+id)
                });
                setTimeout(function(){
                    rank_remove_label.hide();
                },remove_label_time);
            }
        }
    }
}

// When stop action is triggered by clicking a survey question option, give notice before ending survey
function triggerStopAction(ob) {
    var obname = ob.prop('name');
    // Get varname of field
    var varname = '';
    if (obname.substring(0,8) == '__chkn__'){
        // Checkbox
        varname = obname.substring(8,obname.length);
    } else if (obname.substring(obname.length-8,obname.length) == '___radio'){
        // Radio
        varname = obname.substring(0,obname.length-8);
    } else {
        // Drop-down (including any auto-complete input component)
        varname = obname;
    }
    $('#stopActionPrompt').dialog({ bgiframe: true, modal: true, title: lang.survey_01, width: (isMobileDevice ? $(window).width() : 600),
        close: function(){
            // Undo last response if closing and returning to survey
            if (obname.substring(0,8) == '__chkn__'){
                // Checkbox
                $('#form :input[name="'+obname+'"]').each(function(){
                    if ($(this).attr('code') == ob.attr('code')) {
                        $(this).prop('checked',false);
                        // If using Enhanced Choices for radios, then deselect it
                        $('#'+varname+'-tr div.enhancedchoice label.selectedchkbox[comps="'+varname+',code,'+ob.attr('code')+'"]').removeClass('selectedchkbox').addClass('unselectedchkbox');
                    }
                });
                $('#form :input[name="'+obname.replace('__chkn__','__chk__')+'_RC_'+replaceDotInCheckboxCoding(ob.attr('code'))+'"]').val('');
            } else if (obname.substring(obname.length-8,obname.length) == '___radio'){
                // Radio
                radioResetVal(varname,'form');
            } else {
                // Drop-down (including any auto-complete input component)
                $('#form select[name="'+obname+'"], #rc-ac-input_'+obname).val('');
            }
        },
        buttons: [{ text: lang.survey_1312, click: function() {
                // Trigger calculations and branching logic
                $('#'+varname+'-tr>td').removeClass('greenhighlight');
                highlightTableRow(varname+'-tr',2000);
                setTimeout(function(){$('#'+varname+'-tr>td').addClass('greenhighlight');},1500);
                setTimeout(function(){calculate(varname);doBranching(varname);},50);
                setDataEntryFormValuesChanged(varname);
                $(this).dialog('close');
            } },
            { text: lang.survey_1311, click: function() {
                    // Make sure that auto-complete drop-downs get their value set prior to ending survey
                    if ($('#form select[name="'+obname+'"]').hasClass('rc-autocomplete') && $('#rc-ac-input_'+obname).length) {
                        $('#rc-ac-input_'+obname).trigger('blur');
                    }
                    // Change form action URL to force it to end the survey
                    $('#form').prop('action', $('#form').prop('action')+'&__stopaction=1&__endsurvey=1' );
                    // Submit the survey
                    dataEntrySubmit(document.getElementById('submit-action'));
                } } ]
    });
}

//Set value and enable specific slider
function setSlider(fld,val) {
    $("#slider-"+fld).slider("option", "value", val);
    $("#slider-"+fld).slider("enable");
    $("#sldrmsg-"+fld).css('visibility','hidden');
}

// If a field's value is a missing data code, remove it and set value to blank/null
function removeFieldMissingDataCode(fld) {
    if ($('#' + fld +'_MDLabel:visible').length) {
        $('tr#'+fld+'-tr img.missingDataButton').trigger('click');
        $('#MDMenu .set_btn[code=""]').trigger('click');
    }
}

//Reset slider value
function resetSlider(fld,bypassBranchingCalcs) {
    if (typeof bypassBranchingCalcs == 'undefined') bypassBranchingCalcs = false;
    var min = $('#slider-'+fld).attr('data-min')*1;
    var max = $('#slider-'+fld).attr('data-max')*1;
    var startValue = Math.floor((min+max)/2);
    $("#slider-"+fld).slider("option", "value", startValue);
    $("#slider-"+fld).slider("disable");
    $("#sldrmsg-"+fld).css('visibility','visible');
    $('form[name="form"] input[name="'+fld+'"]').val('');
    setDataEntryFormValuesChanged(fld);
    // If field has missing data code, then reset it
    removeFieldMissingDataCode(fld);
    $('#slider-'+fld).removeAttr('modified');
    if (!bypassBranchingCalcs) {
        calculate(fld);
        doBranching(fld);
    }
    try {
        $('.piping_receiver.piperec-'+event_id+'-'+fld).html(missing_data_replacement_js );
        $('.piping_receiver.piperec-'+event_id+'-'+fld+'-value').html(missing_data_replacement_js );
        $('.piping_receiver.piperec-'+event_id+'-'+fld+'-label').html(missing_data_replacement_js );
    } catch(e) { }
}

//Date field functions
function dateKeyDown(event2,fldname) {
    // eval("var fld = document.form."+fldname+";");
    if (event2.keyCode==13) {
        $('document.form.'+fldname).blur();
        return true;
    }
}

// Button to set date field to today's date
function setToday(name,valType) {
    eval("document.form."+name+".value='"+getCurrentDate(valType)+"';");
    // If user modifies any values on the data entry form, set flag to TRUE
    setDataEntryFormValuesChanged(name);
    // Trigger branching/calc fields, in case fields affected
    $('[name='+name+']').focus();
    setTimeout(function(){try{calculate(name);doBranching(name);}catch(e){}},50);
}

// Button to set time field to current time as hh:ss
function setNowTime(name,seconds) {
    if (typeof seconds == 'undefined') seconds = false;
    eval("document.form."+name+".value='"+currentTime('both',seconds)+"';");
    // If user modifies any values on the data entry form, set flag to TRUE
    setDataEntryFormValuesChanged(name);
    // Trigger branching/calc fields, in case fields affected
    $('[name='+name+']').focus();
    setTimeout(function(){try{calculate(name);doBranching(name);}catch(e){}},50);
}

// Button to set datetime field to current time as yyyy-mm-dd hh:ss
function setNowDateTime(name,showSeconds,valType) {
    eval("document.form."+name+".value='"+getCurrentDate(valType)+' '+currentTime('both',showSeconds)+"';");
    // If user modifies any values on the data entry form, set flag to TRUE
    setDataEntryFormValuesChanged(name);
    // Trigger branching/calc fields, in case fields affected
    $('[name='+name+']').focus();
    setTimeout(function(){try{calculate(name);doBranching(name);}catch(e){}},50);
}

// Open popup window for viewing a calc field's equation
function viewEq(field, isDataCalc, isCalcText, randTarget) {
    var dialogTitle = (randTarget) ? lang.form_renderer_68 : lang.form_renderer_67;
    var metadata_table = (status > 0 && page == 'Design/online_designer.php') ? 'metadata_temp' : 'metadata';
    $.get(app_path_webroot+'DataEntry/view_equation_popup.php', { pid: pid, field: field, metadata_table: metadata_table, calcdate: isDataCalc, calctext: isCalcText, randtarget: randTarget }, function(data) {
        if (!$('#viewEq').length) $('body').append('<div id="viewEq"></div>');
        $('#viewEq').html(data);
        $('#viewEq').dialog({ bgiframe: true, modal: true, title: dialogTitle+' "'+field+'"', width: 700,
            buttons: { Close: function() { $(this).dialog('close'); } }, open:function(){ fitDialog(this); } });
    });
}

// Branching Logic & Calculated Fields
function brErase(fld) {
    return lang.global_218+' "'+fld+'" '+lang.questionmark+'\n\n'+lang.global_223+' "'+fld+'" '+lang.global_224+'\n\n'+ lang.global_225;
}

//#region Calculations

function ctf(t, c) {
	if (t == '') return true;
	if (typeof Calculations.triggerFields[t] == 'undefined') return true;
	for (const field of Calculations.triggerFields[t]) {
		if (Calculations.triggerFields.hasOwnProperty(field)) return true;
	}
	return Calculations.triggerFields[t].includes(c);
}

function clearCalculationErrors() {
	for (const field of Object.keys(Calculations.errorTracker)) {
		// Only clear non-syntactical errors
		if (Calculations.errorTracker[field] != 2) Calculations.errorTracker[field] = 0;
	}
}

function setupCalculations() {
	Calculations.funcs = {};
	for (const field of Object.keys(Calculations.jsCode)) {
		try {
			try {
				const expressionBody = '"use strict"; return(' + Calculations.jsCode[field] + ');';
				const func = new Function(expressionBody);
				func(); // Test the function
				Calculations.jsCode[field] = expressionBody;
				Calculations.funcs[field] = func;
			}
			catch (innerErrorIgnored) {
				const statementBody = Calculations.jsCode[field];
				const func = new Function(statementBody);
				func(); // Test the function
				Calculations.jsCode[field] = statementBody;
				Calculations.funcs[field] = func;
			}
		}
		catch (e) {
			Calculations.errorTracker[field] = 2;
			console.error('Calculation JS syntax error in field: \'' + field + '\': ' + e.message);
		}
	}
}

function calculate(t, isOnPageLoad) {
	clearCalculationErrors();
	if (typeof t == 'undefined') t = '';
	if (typeof isOnPageLoad == 'undefined') isOnPageLoad = false;
	// Loop through all calculations
	for (const field of Object.keys(Calculations.funcs)) {
		if (!ctf(t, field)) continue;
		let result;
		if (Calculations.displayErrors) {
			result = Calculations.funcs[field]();
			if (result == Infinity || result == -Infinity) {
				// Only ouput if not output in the last 300ms
				if (Calculations.errorLastReported[field] == null || (Date.now() - Calculations.errorLastReport[field]) > 300) {
					Calculations.errorLastReported[field] = Date.now();
					console.warn(lang.global_318, field);
				}
			}
			const oldVal = document.form[field].value;
			const newVal = document.form[field].value = 
				Calculations.resultChecks[field]
					? (isNumeric(result) ? result : '')
					: ((String(result) != '' && result != 'NaN') ? result : '');
			calcChangeCheck(newVal, oldVal, field, isOnPageLoad);
		}
		else {
			try {
				result = Calculations.funcs[field]();
				if (result == Infinity || result == -Infinity) {
					// Only ouput if not output in the last 300ms to avoid spam
					if (Calculations.errorLastReported[field] == null || (Date.now() - Calculations.errorLastReported[field]) > 300) {
						Calculations.errorLastReported[field] = Date.now();
						console.warn(lang.global_318, field);
					}
				}
				const oldVal = document.form[field].value;
				const newVal = document.form[field].value = 
					Calculations.resultChecks[field]
						? (isNumeric(result) ? result : '')
						: ((String(result) != '' && result != 'NaN') ? result : '');
				calcChangeCheck(newVal, oldVal, field, isOnPageLoad);
			}
			catch (e) {
				Calculations.errorTracker[field] = 1;
			}
		}
	}
	// Update receivers
	try { updateCalcPipingReceivers(); } catch (e) { }
	if (!isOnPageLoad) reportBranchingAndCalculationErrors(false);
}

//#endregion

//#region Branching Logic

function dbtf(t, c) {
	if (t == '') return true;
	if (typeof BranchingLogic.triggerFields[t] == 'undefined') return true;
	for (const field of BranchingLogic.triggerFields[t]) {
		if (BranchingLogic.triggerFields.hasOwnProperty(field)) return true;
	}
	return BranchingLogic.triggerFields[t].includes(c);
}

function clearBranchingErrors() {
	for (const field of Object.keys(BranchingLogic.errorTracker)) {
		// Only clear non-syntactical errors
		if (BranchingLogic.errorTracker[field] != 2) BranchingLogic.errorTracker[field] = 0;
	}
}

function setupBranchingLogic() {
	BranchingLogic.fieldsToErase = [];
	BranchingLogic.funcs = [];
	for (const field of Object.keys(BranchingLogic.jsCode)) {
		try {
			const body = BranchingLogic.jsCode[field];
			const func = new Function(body);
			BranchingLogic.funcs[field] = func;
		}
		catch (e) {
			BranchingLogic.errorTracker[field] = 2;
			console.error('Branching Logic JS syntax error in field: \'' + field + '\': ' + e.message);
		}
	}
}

function doBranching(t, bypassEraseFieldPrompt, isOnPageLoad) {
	if ($('#questiontable').length == 0) return;
	clearBranchingErrors();
	BranchingLogic.fieldsToErase = [];
	BranchingLogic.runAllAgain = false;
	if (typeof t == 'undefined') t = '';
	if (typeof bypassEraseFieldPrompt == 'undefined') bypassEraseFieldPrompt = false;
	if (typeof isOnPageLoad == 'undefined') isOnPageLoad = false;
	// Loop through all branching logic fields
	for (const field of Object.keys(BranchingLogic.funcs)) {
		if (!dbtf(t, field)) continue;
		if (BranchingLogic.displayErrors) {
			const result = BranchingLogic.funcs[field]();
			applyBranchingLogicResult(field, bypassEraseFieldPrompt, result);
		}
		else {
			try {
				const result = BranchingLogic.funcs[field]();
				applyBranchingLogicResult(field, bypassEraseFieldPrompt, result);
			}
			catch (e) {
				BranchingLogic.errorTracker[field] = 1;
			}
		}
	}
	// Run various functions
	$(function() {
		updateHiddenEnhancedChoices();
		showDescriptiveTextImages();
		initInlinePdfs();
		initVidYardJS();
		hideSectionHeaders();
	});
	// Re-run floating matrix headers
	if (!isOnPageLoad) enabledFloatingMatrixHeaders();
	// Re-run all branching again if we have weird embedding situations
	if (t != '' && BranchingLogic.runAllAgain) doBranching();
	// Prompt to erase values in fields that are to be hidden
	// (at page load, this needs to be wrapped in document.ready to detect if MLM is active)
	if (isOnPageLoad) {
		$(function() {
			if (typeof REDCap?.MultiLanguage?.onLangChanged == 'function') {
				REDCap.MultiLanguage.onLangChanged(function() {
					promptToEraseValuesInFieldsToBeHidden(t);
				});
			}
			else {
				promptToEraseValuesInFieldsToBeHidden(t);
			}
		});
	}
	else {
		promptToEraseValuesInFieldsToBeHidden(t);
	}
	// Update any calc fields after executing branching for a single field
	if (t != '') calculate();
}

function promptToEraseValuesInFieldsToBeHidden(t) {
	if (BranchingLogic.fieldsToErase.length == 0) return;
	// Only prompt when the triggering field is actually a trigger field
	if (t != '' && !BranchingLogic.triggerFields.hasOwnProperty(t)) return;


	// Build dialog content
	const title = '<i class="fa-solid fa-eraser me-1"></i>' + RCView.tt('data_entry_731');
	const $list = $('<div class="bl-erase-list"></div>');
	const $content = $('<div></div>')
		.append(RCView.$tt('data_entry_728', 'p'))
		.append(RCView.$tt('data_entry_729', 'p'))
		.append($list)
		.append(RCView.$tt('data_entry_730', 'p'));
	for (const field of BranchingLogic.fieldsToErase) {
		// Field label
		let fieldLabel = trim($('[data-mlm-field="'+field+'"][data-mlm-type="label"]').text() ?? '');
		if (fieldLabel == '') {
			fieldLabel = '[&#8202;' + field + '&#8202;]'; // Hair-spaces
		}
		else {
			fieldLabel = RCView.trimForDropdownDisplay(fieldLabel, 60);
		}
		const $entry = $('<div class="form-check"><input data-field="' + field + '" id="bl-erase-'+field+'" type="checkbox" checked class="form-check-input" /><label for="bl-erase-'+field+'" class="form-check-label">' + fieldLabel + '</label></div>');
		// Special note for file upload - file will be permanently deleted if user chooses to erase the field
		const $fuContainer = $('#fileupload-container-'+field);
		if ($fuContainer.length > 0) {
			const fileType = $fuContainer.find('[data-file-type]').attr('data-file-type') ?? ''; // 'file' or 'signature'
			const filenameShort = $fuContainer.find('span[vf]').text() ?? ''; // Abbreviated filename
			const filenameFull = $fuContainer.find('span[vf]').attr('vf') ?? ''; // Full filename
			const $fileNote = $('<div class="bl-erase-file-note"></div>');
			const filename = $('<span></span>').text(filenameShort).attr('title', filenameFull).html();
			$fileNote.append(RCView.$tt_i('data_entry_734', [filename], false));
			$entry.append($fileNote);
		}
		$list.append($entry);
	}
	
	const applyLabel = RCView.getLangStringByKey('data_entry_732');
	const applyAction = function(keepAll = false) {
		const fieldsToActuallyErase = [];
		if (!keepAll) {
			$list.find('input[type="checkbox"]').each(function() {
				if ($(this).prop('checked')) {
					fieldsToActuallyErase.push($(this).attr('data-field'));
				}
			});
		}
		// Erase values in fields that are to be hidden (and eye-mark others)
		for (const field of BranchingLogic.fieldsToErase) {
			if (fieldsToActuallyErase.indexOf(field) > -1) {
				eraseValueInFieldToBeHidden(field);
			}
			else if (!is_survey()) {
				branchingLogicAddShownDespiteIndicator(field);
			}
		}
	};
	const keepAllLabel = RCView.getLangStringByKey('data_entry_733');
	const keepAllAction = function() {
		applyAction(true);
	}
	// Show dialog
	simpleDialog(
		$content, title,
		null, 500,
		keepAllAction, keepAllLabel,
		applyAction, applyLabel,
		undefined, 'rc-dialog-prompt-bl-erase'
	);
	// Remove X (close) button (escape key will still work and execute 'Keep All')
	$('div[role=dialog].rc-dialog-prompt-bl-erase').find('.ui-dialog-titlebar-close').remove();
}

function eraseValueInFieldToBeHidden(thisField) {
	const domField = document.forms['form'][thisField] ?? null;
	let isCheckbox = false;
	if (domField != null) {
		if (domField.value != null) fieldLen = domField.value.length;
	}
	else {
		// Checkbox fields (might also be a "descriptive" field)
		fieldLen = anyChecked('form', '__chkn__'+thisField);
		isCheckbox = true;
	}
	// if (!hasMissingDataCode) {
	if (isCheckbox) {
		// Checkbox fields
		checkAll(0, "form", "__chkn__" + thisField);
	} 
	else {
		// If a radio field, additionally make sure the radio buttons are all unchecked
		if (document.forms['form'].elements[thisField + '___radio'] != null) {
			domField.value = '';
			uncheckRadioGroup(document.forms['form'].elements[thisField + '___radio']);
		}
		// If a select field with auto-complete enabled, then reset the text field value too
		if (document.getElementById('rc-ac-input_' + thisField) != null) {
			domField.value = '';
			document.getElementById('rc-ac-input_' + thisField).value = '';
		}
		// If a slider field, then reset the slider
		else if (document.forms['form'].elements[thisField] != null && document.forms['form'].elements[thisField].getAttribute('class') != null
			&& document.forms['form'].elements[thisField].getAttribute('class').indexOf('sldrnum') > -1) {
			resetSlider(thisField, true);
		}
		// If a file upload field, then reset the field
		else if (elementExists(document.getElementById('fileupload-container-'+thisField))) {
			domField.value = '';
			$(function(){
				const $fu = $('#fileupload-container-'+thisField);
				$fu.removeClass('hidden').find('.filedownloadlink').remove();
				if ($fu.attr('data-file-type') == 'signature') {
					$('#'+thisField+'-sigimg').remove();
					$('#'+thisField+'-linknew').html('<a href="javascript:;" class="fileuploadlink" onclick="filePopUp(\''+thisField+'\',1,0);return false;"><i class="fa-solid fa-signature me-1"></i>'+RCView.tt('form_renderer_31')+'</a>');
				}
				else {
					$('#'+thisField+'-linknew').html('<a href="javascript:;" class="fileuploadlink" onclick="filePopUp(\''+thisField+'\',0,0);return false;"><i class="fa-solid fa-upload me-1 fs12"></i>'+RCView.tt('form_renderer_23')+'</a>');
				}
				if (window.REDCap && window.REDCap.MultiLanguage) {
					window.REDCap.MultiLanguage.updateUI();
				}
			});
		} else {
			// Reset field value
			domField.value = '';
		}
	}
	// Trigger piping now that the value has been erased
	if (isCheckbox) {
		updatePipingCheckboxes(':input[name="__chkn__' + thisField + '"]');
	} else if (document.forms['form'].elements[thisField + '___radio'] != null) {
		radioResetVal(thisField,'form');
	} else if (typeof document.forms['form'].elements[thisField] != 'undefined' && document.forms['form'].elements[thisField].tagName.toLowerCase() == 'input'
		&& document.forms['form'].elements[thisField].getAttributeNode("type").value.toLowerCase() == 'text') {
		$(function(){ $(':input[name="'+thisField + '"]').trigger('blur'); });
	} else {
		$(function(){ $(':input[name="'+thisField + '"]').trigger('change'); });
	}
	// }
	document.getElementById(thisField+'-tr').style.display='none';
	// Remove "hasval" attribute from row
	removeHasVal(thisField);
}

//#endregion
 
function reportBranchingAndCalculationErrors(onPageLoad = false) {
	// Check if there are any branching/calculation errors
	const blErrFields = [];
	const calcErrFields = [];
	let fatalError = false;
	for (const field in BranchingLogic.errorTracker) {
		if (BranchingLogic?.errorTracker[field] ?? 0 > 0) blErrFields.push(field);
		if (BranchingLogic?.errorTracker[field] ?? 0 > 1) fatalError = true;
	}
	for (const field in Calculations.errorTracker) {
		if (Calculations?.errorTracker[field] ?? 0 > 0) calcErrFields.push(field);
		if (Calculations?.errorTracker[field] ?? 0 > 1) fatalError = true;
	}
	const nErrors = blErrFields.length + calcErrFields.length;
	if (nErrors == 0 ) return;
	// Surveys
	if (is_survey()) {
		if (fatalError) {
			$('button[name="submit-btn-saverecord"]').prop('disabled', true).addClass('ui-state-disabled');
			window.dataEntrySubmit = function() { return false; };
		}
		const title = RCView.tt('global_215');
		let content = RCView.tt('global_316') + '<ul>';
		if (blErrFields.length) {
			content += '<li>' + RCView.tt('global_313') + ' <i>' + blErrFields.join(', ') + '</i></li>';
		}
		if (calcErrFields.length) {
			content += '<li>' + RCView.tt('global_315') + ' <i>' + calcErrFields.join(', ') + '</i></li>';
		}
		content += '</ul>' + RCView.tt('global_217');
		simpleDialog(content, title, null, undefined, undefined, undefined, undefined, undefined, undefined, 'rc-dialog-error-title');
	}
	// Data Entry
	else {
		const title = RCView.tt('global_319');
		let content = RCView.tt('global_314') + '<ul>';
		if (blErrFields.length) {
			content += '<li>' + RCView.tt('global_313') + ' <i>' + blErrFields.join(', ') + '</i></li>';
		}
		if (calcErrFields.length) {
			content += '<li>' + RCView.tt('global_315') + ' <i>' + calcErrFields.join(', ') + '</i></li>';
		}
		content += '</ul>' + RCView.tt('global_317') + (onPageLoad ? ('<br>' + RCView.tt('global_320', 'span', { 'class': 'fs11 text-muted'})) : '') + '<br><br>' + RCView.tt('global_213');
		simpleDialog(content, title, null, 600, undefined, undefined, undefined, undefined, undefined, 'rc-dialog-error-title');
	}
}

// Remove all unselected options from Form Status drop-down (used when page is locked but not e-signed)
function removeUnselectedFormStatusOptions() {
    $(':input[name='+getParameterByName('page')+'_complete] option').each(function(){
        if ( $(this).prop('selected') == false ) {
            $(this).remove();
        } else {
            $(this).css('color','gray');
        }
    });
}

// Run processes when submitting form on data entry page
function formSubmitDataEntry() {
    // Disable the onbeforeunload so that we don't get an alert before we leave
    window.onbeforeunload = function() { }
    // Close the datepicker widget, if opened, for hardtyped textbox fields
    if ($('#ui-datepicker-div:visible').length && pickerOpenerField != '' && $('input[name='+pickerOpenerField+'][hardtyped]').length) {
        $('#ui-datepicker-div').hide();
        $('input[name='+pickerOpenerField+'][hardtyped]').blur();
        setTimeout("formSubmitDataEntry();",100);
        return;
    }
    // If field validation popup is visible, do not allow submission. It must be closed first.
    if ($('#redcapValidationErrorPopup:visible').length) return;
    // If a drop-down field was somehow given an invalid value, which removes it from the POST request because it has a NULL value,
    // set its value to "" so that it gets picked up by the Required Field check in POST (in case the field is required).
    $('#questiontable select').each(function(){
        var field = $(this).attr('name');
        if ($('#'+field+'-tr[req]').length && $('#'+field+'-tr[req]').attr('req') == '1' && $(this).val() === null) {
            $(this).val('');
        }
    });
    // Disable all buttons on page when submitting to prevent double submission
    $('#form :button, #formSaveTip :button').prop('disabled',true);
    // Before finally submitting the form, execute all calculated fields again just in case someone clicked Enter in a text field
    calculate();
    // Is survey page?
    var isSurveyPage = (page == 'surveys/index.php');
    // REQUIRED FIELDS: Loop through table and remove form elements from html that are hidden due to branching logic
    // (so user is not prompted to enter values for invisible fields).
    $("#questiontable .rc-field-embed[req='1']").each(function() { // Run this before the next REQUIRED FIELD block below because some embedded fields might be erased via $(this).html(''); below for a container field.
        // Is the req field hidden (i.e. on another survey page)? But not hidden via @HIDDEN.
        var parent_tr = $(this).parentsUntil('tr[sq_id], tr[mtxgrp]').parent();
        var field_tr = $('#'+$(this).attr("var")+'-tr');
        var parentHiddenNotByActionTag = (parent_tr.css("display") == "none" && !parent_tr.hasClass("\@HIDDEN") && !(isSurveyPage && parent_tr.hasClass("\@HIDDEN-SURVEY")) && !(!isSurveyPage && parent_tr.hasClass("\@HIDDEN-FORM")));
        var fieldHasHiddenActionTag = (field_tr.hasClass("\@HIDDEN") || (isSurveyPage && field_tr.hasClass("\@HIDDEN-SURVEY")) || (!isSurveyPage && field_tr.hasClass("\@HIDDEN-FORM")));
        var fieldVisible = ($(this).css("display") != "none");
        if (!$(this).find('.rc-field-embed-error').length && (
            // If parent/container is hidden (but not via @HIDDEN action tag) while the embedded field is visible on its own right (but not via @HIDDEN action tag), then add as empty-required-field
            (parentHiddenNotByActionTag && !(fieldVisible && !fieldHasHiddenActionTag))
            // If field itself or its parent/container is hidden (but not via @HIDDEN action tag), then add as empty-required-field
            || ((parentHiddenNotByActionTag || !fieldVisible) && !fieldHasHiddenActionTag)
        )) {
            // If field does not exist on this survey page, then skip it (it might be hidden on the page due to branching/calc necessity)
            if (isSurveyPage && typeof pageFields != 'undefined' && !in_array($(this).attr("var"), pageFields)) return;
            // Only remove field from form if does not already have a saved value (i.e. has 'hasval=1' as row attribute)
            $(this).html('');
            // Add to empty required field list that gets submitted
            appendHiddenInputToForm('empty-required-field[]', $(this).attr("var"));
        }
    });
    $("#questiontable tr").each(function() {
        // Is it a required field (and is not embedded)?
        if ($(this).attr("req") != null && !$(this).hasClass('row-field-embedded')) {
            // Is the req field hidden (i.e. on another survey page)?
            if ($(this).css("display") == "none") {
                // Only remove field from form if does not already have a saved value (i.e. has 'hasval=1' as row attribute)
                if ($(this).attr("hasval") != "1" && !($(this).hasClass("\@HIDDEN")
                    || ($(this).hasClass("\@HIDDEN-SURVEY") && isSurveyPage) || ($(this).hasClass("\@HIDDEN-FORM") && !isSurveyPage)))
                {
                    // If field does not exist on this survey page, then skip it (it might be hidden on the page due to branching/calc necessity)
                    if (isSurveyPage && typeof pageFields != 'undefined' && !in_array($(this).attr("sq_id"), pageFields)) return;
                    // Remove value
                    $(this).html('');
                    // Add to empty required field list that gets submitted
                    appendHiddenInputToForm('empty-required-field[]', $(this).attr("sq_id"));
                }
            }
        }
    });
    // For surveys only
    if (isSurveyPage) {
        // If using "save and return later", append to form action to point to new place
        if ($('#submit-action').val() == "submit-btn-savereturnlater") {
            $('#form').attr('action', $('#form').attr('action')+'&__return=1' );
        }
        // If using "previous page" button, append to form action to point to new place
        if ($('#submit-action').val() == "submit-btn-saveprevpage") {
            $('#form').attr('action', $('#form').attr('action')+'&__prevpage=1' );
        }
    }
    // Re-enable any disabled fields (due to field action tags and such) - make sure we leave any randomization fields disabled though
    $('#questiontable input:disabled, #questiontable select:disabled, #questiontable textarea:disabled').each(function(){
        var fld = $(this);
        if (randomizationCriteriaFieldList == null ||
            (typeof fld.parents('tr:first').attr('id') != 'undefined' && !in_array(fld.parents('tr:first').attr('id').slice(0,-3), randomizationCriteriaFieldList)) ||
            (typeof fld.parentsUntil('.rc-field-embed').parent().attr('var') != 'undefined' && !in_array(fld.parentsUntil('.rc-field-embed').parent().attr('var'), randomizationCriteriaFieldList))
        ) {
            fld.prop('disabled', false);
        }
    });
    // If Secondary Unique Field is disabled (because it's currently being checked for uniqueness via AJAX), then don't submit form
    if (secondary_pk != '' && $('#form :input[name="'+secondary_pk+'"]').length && $('#form :input[name="'+secondary_pk+'"]').prop('disabled')) {
        // Re-enable all submit buttons
        $('#form :button, #formSaveTip :button').prop('disabled',false);
        // Do not submit form
        return;
    }
    // Hide all images on page and show progress div
    $('#form img').css('visibility', 'hidden');
    showProgress(1);
    // Submit form (finally!)
    document.form.submit();
}

// Execute when buttons are clicked on data entry forms
function dataEntrySubmit(ob)
{
    window['regularSubmit'] = true;
    // Set value of hidden field used in post-processing after form is submitted
    if (typeof ob === 'string' || ob instanceof String) {
        $('#submit-action').val( ob );
    } else {
        $('#submit-action').val( $(ob).attr('name') );
    }
    if ($('#submit-action').val() == '' || $('#submit-action').val() == null) {
        $('#submit-action').val('submit-btn-saverecord');
    }

    // Clicked Save or Delete
    if ($('#submit-action').val() != "submit-btn-cancel")
    {
        // Determine esign_action
        var esign_action = "";
        if ($('#__ESIGNATURE__').length && $('#__ESIGNATURE__').prop('checked') && $('#__ESIGNATURE__').prop('disabled') == false) {
            esign_action = "save";
            // If form is not locked already or checked to be locked, then stop (because is necessary)
            if ($('#__LOCKRECORD__').prop('checked') == false) {
                simpleDialog('WARNING:\n\nThe "Lock Record" option must be checked before the e-signature can be saved. Please check the "Lock Record" check box and try again.');
                return false;
            }
        }

        // Set the lock action
        var lock_action = ($('#__LOCKRECORD__').prop("disabled") && (esign_action == "save" || esign_action == "")) ? 2 : 1;

        // "change reason" popup for existing records (and lock record, if user has rights)
        if (require_change_reason && record_exists && (dataEntryFormValuesChanged || $('#submit-action').val() == 'submit-btn-delete'))
        {
            $('#change_reason_popup').dialog({
                title: lang.data_entry_603,
                bgiframe: true, 
                modal: true, 
                width: 500, 
                zIndex: 4999, 
                buttons: [{
                    text: lang.report_builder_28,
                    click: function() {
                        if ($("#change_reason").val().length < 1) {
                            $('#change_reason_popup_error').show();
                            return false;
                        }
                        // Before submitting the form, add change reason values from dialog as form elements for submission
                        $('#form').append('<input type="hidden" name="change-reason" value="'+$("#change_reason").val().replace(/"/gi, '&quot;')+'">');
                        // Save locked value
                        if ($('#__LOCKRECORD__').prop('checked')) {
                            $('#change_reason_popup').dialog('destroy');
                            saveLocking(lock_action,esign_action);
                            // Not locked, so just submit form
                        } else {
                            formSubmitDataEntry();
                        }
                    }
                }],
                open: () => {
                    // Restore default state (error hidden)
                    $('#change_reason_popup_error').css('display','none');
                    // Update strings (if MLM is present)
                    if (typeof REDCap.MultiLanguage != 'undefined') {
                        REDCap.MultiLanguage.translateRcLang('#change_reason_popup');
                    }
                }
            });

        }
        // Do locking and/or save e-signature, then submit form
        else if ($('#__LOCKRECORD__').prop('checked') && (!$('#__LOCKRECORD__').prop("disabled") || esign_action == "save"))
        {
            saveLocking(lock_action,esign_action);
        }
        // Just submit form if neither using change_reason nor locking
        else
        {
            formSubmitDataEntry();
        }
    }
    // Clicked Cancel (requires form submission)
    else {
        formSubmitDataEntry();
    }
}

// After running branching logic, hide any section headers in which all fields in the section have been hidden
function hideSectionHeaders() {
    var this_id;
    var lastSH = "";
    var numFields = 0;
    var numFieldsHidden = 0;
    var tbl = document.getElementById("questiontable");
    var rows = tbl.tBodies[0].rows;
    var matrixGroup = "";
    var matrixGroups = new Array();
    var fieldIsHidden;
    var getClassTerm = 'class';
    var thisClass;
    var isSurveyPage = (page == 'surveys/index.php');
    //Get index somewhere in middle of table
    for (var i=0; i<rows.length; i++) {
        // Get id for this row
        this_id = rows[i].getAttribute("id");

        // If this row has an id, then check if SH, matrix header, matrix field, or regular field
        if (this_id != null && this_id.indexOf("-tr") > 0) {

            // If a Section Header, then check if previous section's fields were all hidden. If so, then hide the SH too.
            if (this_id.indexOf("-sh-tr") > 0) {
                if (lastSH != "") {
                    if (numFieldsHidden == numFields && numFields > 0) {
                        // Hide SH
                        document.getElementById(lastSH).style.display = 'none';
                    } else {
                        // Possibly show SH OR do nothing
                        var showit = true;
                        if (isSurveyPage) {
                            // Survey page: Treat differently since it contains fields on the form that might need to remain hidden (because of multi-paging)
                            if (document.getElementById(lastSH).getAttribute(getClassTerm) != null) {
                                if (document.getElementById(lastSH).getAttribute(getClassTerm).indexOf('hidden') > -1) {
                                    // If row has class 'hidden', then keep hidden
                                    showit = false;
                                }
                            }
                        }
                        // Make SH visible (in case it was hidden)
                        if (showit) document.getElementById(lastSH).style.display = 'table-row';
                    }
                }
                // Reset values for next section
                lastSH = this_id;
                numFields = 0;
                numFieldsHidden = 0;
                matrixGroup = "";
            }

            // If a Matrix Header, then hide the Matrix Header too.
            else if (this_id.indexOf("-mtxhdr-tr") > 0) {
                matrixGroup = document.getElementById(this_id).getAttribute('mtxgrp');
                matrixGroups[matrixGroup] = 0;
            }

            // If a normal field, then check its display value AND if it's in a matrix group
            else {
                // Check if hidden
                fieldIsHidden = (document.getElementById(this_id).style.display == "none"
                                || (document.getElementById(this_id).getAttribute(getClassTerm) != null && document.getElementById(this_id).getAttribute(getClassTerm).indexOf('hide') > -1));
                if (!fieldIsHidden) {
                    // Also check if has @HIDDEN action tag
                    if (document.getElementById(this_id).getAttribute(getClassTerm) != null) {
                        thisClass = document.getElementById(this_id).getAttribute(getClassTerm);
                        if (thisClass.indexOf('@HIDDEN ') > -1 || thisClass.substr(thisClass.length-7) == '@HIDDEN'
                            || (isSurveyPage && thisClass.indexOf('@HIDDEN-SURVEY') > -1)
                            || (!isSurveyPage && thisClass.indexOf('@HIDDEN-FORM') > -1))
                        {
                            // Set as hidden
                            fieldIsHidden = true;
                            document.getElementById(this_id).style.display == "none";
                        }
                    }
                }
                if (fieldIsHidden) numFieldsHidden++;
                // Count field for this section
                numFields++;
                // If field is in a matrix group, get group name
                if (document.getElementById(this_id).getAttribute('mtxgrp') != null) {
                    matrixGroup = document.getElementById(this_id).getAttribute('mtxgrp');
                    if (!fieldIsHidden) matrixGroups[matrixGroup]++;
                }
            }

        }
    }

    // For survey pages only: Check if we need to hide the last SH on the page (will not hide by itself with current logic)
    if (isSurveyPage && lastSH != "") {
        if (numFieldsHidden == numFields && numFields > 0) {
            // Hide SH
            document.getElementById(lastSH).style.display = 'none';
        } else {
            // Possibly show SH OR do nothing
            var showit = true;
            if (isSurveyPage) {
                // Survey page: Treat differently since it contains fields on the form that might need to remain hidden (because of multi-paging)
                if (document.getElementById(lastSH).getAttribute(getClassTerm) != null) {
                    if (document.getElementById(lastSH).getAttribute(getClassTerm).indexOf('hidden') > -1) {
                        // If row has class 'hidden', then keep hidden
                        showit = false;
                    }
                }
            }
            // Make SH visible (in case it was hidden)
            if (showit) document.getElementById(lastSH).style.display = 'table-row';
        }
    }

    // If any matrix groups have all their fields hidden (i.e. value=0), then hide the matrix header
    for (var grpname in matrixGroups) {
        var mtxhdr_id = grpname+'-mtxhdr-tr';
        if (matrixGroups[grpname] == 0) {
            // Hide matrix header
            document.getElementById(mtxhdr_id).style.display = 'none';
        } else {
            // Possibly show matrix header OR do nothing
            var showit = true;
            if (isSurveyPage) {
                // Survey page: Treat differently since it contains fields on the form that might need to remain hidden (because of multi-paging)
                if (document.getElementById(mtxhdr_id).getAttribute(getClassTerm) != null) {
                    if (document.getElementById(mtxhdr_id).getAttribute(getClassTerm).indexOf('hidden') > -1) {
                        // If row has class 'hidden', then keep hidden
                        showit = false;
                    }
                }
            }
            // Make matrix header visible (in case it was hidden)
            if (showit) document.getElementById(mtxhdr_id).style.display = 'table-row';
        }
    }
}

function uploadFilePreProcess() {
    $('#file_upload_vault_popup_error').hide();
    var isSignature = ($('#f1_upload_form input[name="myfile_base64"]').val().length > 0);
    var missingFile = (!isSignature && $('#f1_upload_form input[name="myfile"]').val().length + $('#f1_upload_form input[name="myfile_base64"]').val().length == 0);
    var isSurvey = (page == 'surveys/index.php');
    if (isSignature || missingFile || !file_upload_vault_enabled) {
        // Normal: Submit the form
        $('#form_file_upload').trigger('submit');
        return;
    }
    $('#file_upload_vault_popup_text1').html(basename($('#f1_upload_form input[name="myfile"]').val()));
    // Prompt for user password for Vault Storage+Password feature (excluding surveys)
    if (isSurvey) {
        // Survey
        simpleDialog(null, null, 'file_upload_vault_popup', 550, null, window.lang.global_53, "$('#form_file_upload').submit();", window.lang.design_654);
    } else {
        // Form: Password prompt
        simpleDialog(null, null, 'file_upload_vault_popup', 600, null, window.lang.global_53, function(){
            // Verify username/password
            $('#file_upload_vault_password').val( $('#file_upload_vault_password').val().trim() );
            $.post(app_path_webroot+'index.php?pid='+pid+'&route=DataEntryController:passwordVerify',{username: $('#file_upload_vault_username').val(), password: $('#file_upload_vault_password').val()},function(data){
                if (data == '1') {
                    if ($('#file_upload_vault_password').attr('readonly') != 'readonly') $('#file_upload_vault_password').val('');
                    $('#form_file_upload').submit();
                } else if (data == '0') {
                    simpleDialog(window.lang.form_renderer_48, window.lang.global_01, null, 400, 'uploadFilePreProcess();');
                } else {
                    alert(woops);
                    uploadFilePreProcess();
                }
            });
        }, window.lang.design_654);
    }
    $('#file_upload_vault_popup').parent().find('div.ui-dialog-buttonpane button:eq(1)').css('color','#006000').addClass('font-weight-bold').prepend('<i class="fas fa-check"></i> ');
    $('#file_upload_vault_password').trigger('focus');
}

var file_upload_delete_reason = '';
function deleteDocumentConfirm(doc_id,this_field,id,event_id,instance,delete_page,version,version_hash) {
    if (typeof version == 'undefined') version = '';
    if (typeof version_hash == 'undefined') version_hash = '';
    var extraText = '';
    if (file_upload_vault_enabled) {
        file_upload_delete_reason = '';
        extraText = '<div class="mt-3 mb-2">' + window.lang.form_renderer_50 + '<textarea id="file_upload_delete_reason" class="x-form-field notesbox" style="height:60px;" onchange="file_upload_delete_reason=this.value;"></textarea></div>';
    }
    simpleDialog("<div class='boldish fs14' data-doc-id='"+doc_id+"'>" + window.lang.form_renderer_51 + "</div>" + extraText, window.lang.form_renderer_52, 'remove-file', 480, "$('#MDMenu').hide();", window.lang.global_53, function(){
        if ($('#file_upload_delete_reason').length) {
            $('#file_upload_delete_reason').val($('#file_upload_delete_reason').val().trim());
            if ($('#file_upload_delete_reason').val() == '') {
                simpleDialog(window.lang.form_renderer_49, window.lang.global_01, null, null,
                    "deleteDocumentConfirm('" + doc_id + "','" + this_field + "','" + id + "','" + event_id + "','" + instance + "','" + delete_page + "','" + version + "','" + version_hash + "');"
                );
                return;
            }
        }
        deleteDocument(doc_id,this_field,id,event_id,instance,delete_page,version,version_hash);
        addFileEnhancements();
    }, window.lang.design_397);
}
var codeUpdateAfterDeleteFile = "";
function deleteDocument(doc_id,this_field,id,event_id,instance,delete_page,version,version_hash) {
    // Set value to blank on form
    if (version == '') {
        eval("document.form." + this_field + ".value = '';");
        $('#form a.filedownloadlink[name="'+this_field+'"] .fu-fn').attr('vf', '');
        $('#fileupload-container-'+this_field+' .inline-pdf-viewer-loaded').remove();
    }
    // Force onchange to trigger any piping
    $('#form :input[name="'+this_field+'"]').trigger("change");
    var data = { s: getParameterByName('s'), id: doc_id, field_name: this_field, record: id, event_id: event_id, instance: instance,
        doc_version: version, doc_version_hash: version_hash };
    // Delete the value via AJAX
    $.post(delete_page+'&'+$.param(data),{ file_upload_delete_reason: file_upload_delete_reason },function(data) {
        // Set value to blank on form
        if (version == '') {
            $("#" + this_field + "-linknew").html(data);
            $("#" + this_field + "-link").hide();
            $('#' + this_field + '-sigimg').hide();
            setDataEntryFormValuesChanged(this_field);
            if (typeof REDCapFileEnhancements != 'undefined') REDCapFileEnhancements.removeFileUploadPreview(this_field);
        }
        // Display confirmation dialog
        var file_delete_dialog_id = 'file_delete_dialog';
        initDialog(file_delete_dialog_id);
        var message = version == '' ? 
            interpolateString(window.lang.form_renderer_57, [$("#" + this_field + "-link").text()]) :
            interpolateString(window.lang.form_renderer_58, [version]);
        $('#' + file_delete_dialog_id).html(message);
        simpleDialog(null, window.lang.form_renderer_59, file_delete_dialog_id);
        // Close dialog automatically with fade effect
        setTimeout(function(){
            if ($('#'+file_delete_dialog_id).hasClass('ui-dialog-content')) $('#'+file_delete_dialog_id).dialog('option', 'hide', {effect:'fade', duration: 500}).dialog('close');
            if ($('#data_history').hasClass('ui-dialog-content')) $('#data_history').dialog('destroy');
            // Destroy the dialog so that fade effect doesn't persist if reopened
            setTimeout(function(){
                if ($('#'+file_delete_dialog_id).hasClass('ui-dialog-content')) $('#'+file_delete_dialog_id).dialog('destroy');
                if (version != '') dataHist(this_field,event_id,lastDataHistWidth);
            },500);
        },2200);
        // If clicked a Missing Data Code, which deleted the file, then
        if (codeUpdateAfterDeleteFile != '' && $('#MDMenu div[code="'+codeUpdateAfterDeleteFile+'"]').length) {
            $('#MDMenu div[code="'+codeUpdateAfterDeleteFile+'"]').trigger('click');
        } else {
            $('#MDMenu').hide();
        }
        codeUpdateAfterDeleteFile = '';
        // Initialize any File Upload fields with @INLINE action tag
        initInlineImages(this_field);
    });
    // Trigger branching logic in case a "file" field is involved in branching
    doBranching(this_field);
    return true;
}

function stopUpload(success,this_field,doc_id,doc_name,doc_size,event_id,download_page,delete_page,doc_id_hash,instance,isSigField){
    var result = '';
    var study_id = getParameterByName('id');
    if (success == 1){
        if (typeof REDCapFileEnhancements != 'undefined') REDCapFileEnhancements.removeFileUploadPreview(this_field);
        $('#fileupload-container-'+this_field+' .inline-pdf-viewer-loaded').remove();
        try {
            if (typeof window.parent.lang.form_renderer_24 != 'undefined') {
                window.lang.form_renderer_24 = window.parent.lang.form_renderer_24;
                window.lang.form_renderer_43 = window.parent.lang.form_renderer_43;
                window.lang.form_renderer_25 = window.parent.lang.form_renderer_25;
                window.lang.data_entry_459 = window.parent.lang.data_entry_459;
                window.lang.form_renderer_60 = window.parent.lang.form_renderer_60;
                window.lang.dataqueries_160 = window.parent.lang.dataqueries_160;
                window.lang.global_03 = window.parent.lang.global_03;
                window.lang.form_renderer_61 = window.parent.lang.form_renderer_61;
            }
        } catch (e) { }
        if (typeof window.lang.form_renderer_24 == 'undefined') {
            window.lang.form_renderer_24 = 'Remove file';
            window.lang.form_renderer_43 = 'Remove signature';
            window.lang.form_renderer_25 = 'Send-It';
            window.lang.data_entry_459 = 'Upload new version';
            window.lang.form_renderer_60 = 'File was successfully uploaded!';
            window.lang.dataqueries_160 = 'File was successfully uploaded!';
            window.lang.global_03 = 'NOTICE';
            window.lang.form_renderer_61 = 'In order to use Send-It with this file, the current web page must first be saved by clicking the button at the bottom of the page.';
        }
        var sigimg = $('#'+this_field+'-sigimg');
        result = '<div style="font-weight:bold;font-size:14px;text-align:center;color:green;"><br><i class="fas fa-check"></i> <span data-rc-lang="form_renderer_60">' + window.lang.form_renderer_60 + '</span></div>';
        document.getElementById(this_field+"-link").style.display = 'block';
        var doc_name_full = doc_name;
        document.getElementById(this_field+"-link").title = htmlspecialchars(doc_name_full);
        doc_name = truncate_filename(doc_name, 34);
        document.getElementById(this_field+"-link").innerHTML = '<span class="fu-fn" vf="'+htmlspecialchars(doc_name_full)+'">'+doc_name+'</span>'+doc_size;
        document.getElementById(this_field+"-link").href = download_page+"&doc_id_hash="+doc_id_hash+"&id="+doc_id+"&s="+getParameterByName('s')+"&record="+htmlspecialchars(study_id)+"&page="+getParameterByName('page')+"&event_id="+event_id+"&field_name="+this_field+"&instance="+instance;
        $('#'+this_field+"-link").attr('onclick', "return appendRespHash('"+this_field+"');");
        var newlinktext = '<a href="javascript:;" class="deletedoc-lnk" style="font-size:10px;color:#C00000;" onclick=\'deleteDocumentConfirm('+doc_id+',"'+this_field+'","'+htmlspecialchars(study_id)+'",'+event_id+','+instance+',"'+delete_page+'&__response_hash__="+$("#form :input[name=__response_hash__]").val());return false;\'><i class="far fa-trash-alt me-1"></i><span data-rc-lang="' + (isSigField ? 'form_renderer_43' : 'form_renderer_24') + '">'+ (isSigField ? window.lang.form_renderer_43 : window.lang.form_renderer_24) +'</span></a>';
        if (sendit_enabled) {
            newlinktext += "<span class=\"sendit-lnk\"><span style=\"font-size:10px;padding:0 10px;\">or</span><a onclick=\"simpleDialog(window.lang.form_renderer_61,window.lang.global_03);return false;\" href=\"javascript:;\" style=\"font-size:10px;\"><i class=\"far fa-envelope me-1\"></i><span data-rc-lang=\"form_renderer_25\">"+window.lang.form_renderer_25+"</span></a>&nbsp;</span>";
        }
        if (file_upload_versioning_enabled && !sigimg.length) {
            newlinktext = '<a href="javascript:;" style="font-size:10px !important;color:green;" class="fileuploadlink" '
                + 'onclick="filePopUp(\''+this_field+'\',0,1);return false;"><i class="fas fa-upload me-1"></i><span data-rc-lang="data_entry_459">'+window.lang.data_entry_459+'</span></a>'
                + '<span style="font-size:10px;padding:0 10px;">or</span>' + newlinktext;
        }
        document.getElementById(this_field+"-linknew").innerHTML = newlinktext;
        eval("document.form."+this_field+".value = '"+doc_id+"';");
        // If a signature field, then add inline image
        if (sigimg.length) {
            sigimg.show().html('<img src="'+download_page.replace('file_download.php','image_view.php')+"&doc_id_hash="+doc_id_hash+"&id="+doc_id+"&s="+getParameterByName('s')+"&record="+htmlspecialchars(study_id)+"&page="+getParameterByName('page')+"&event_id="+event_id+"&instance="+instance+"&field_name="+this_field+'&signature=1" alt="Signature">');
        }
        addFileEnhancements();
    } else {
        result = '<div style="font-weight:bold;color:#C00000;margin-top:15px;font-size:14px;text-align:center;"><span data-rc-lang="dataqueries_160">' + window.lang.dataqueries_160 + '</div>';
    }
    document.getElementById('f1_upload_form').style.display = 'block';
    document.getElementById('f1_upload_form').innerHTML = result;
    document.getElementById('f1_upload_process').style.display = 'none';
    // Close dialog automatically with fade effect
    if ($("#file_upload").hasClass('ui-dialog-content')) {
        if (success == 1) {
            // If this is a signature field, then close dialog immediately
            if ($('#'+this_field+'-sigimg').length) {
                $('#file_upload').dialog('destroy');
                if (inIframe()) {
                    var urlparts = window.location.href.split('#');
                    window.location.href = urlparts[0]+'#'+this_field+'-tr';
                }
            } else {
                $('#file_upload').dialog('option', 'buttons', { "Close": function() { $(this).dialog("destroy"); } });
                setTimeout(function(){
                    if ($("#file_upload").hasClass('ui-dialog-content')) $('#file_upload').dialog('option', 'hide', {effect:'fade', duration: 200}).dialog('close');
                    // Destroy the dialog so that fade effect doesn't persist if reopened
                    setTimeout(function(){
                        if ($("#file_upload").hasClass('ui-dialog-content')) $('#file_upload').dialog('destroy');
                    },200);
                    if (inIframe()) {
                        var urlparts = window.location.href.split('#');
                        window.location.href = urlparts[0]+'#'+this_field+'-tr';
                    }
                },1500);
            }
        } else {
            $('#file_upload').dialog('option', 'buttons', { "Close": function() { $(this).dialog("destroy"); },
                "Try again": function() { $('#file_upload').dialog('destroy'); $('#'+this_field+'-linknew a.fileuploadlink').trigger('click'); } });
        }
    }
    // Force onchange to trigger any piping
    $('#form :input[name="'+this_field+'"]').trigger("change");
    // Trigger branching logic in case a "file" field is involved in branching
    calculate(this_field);
    doBranching(this_field);
    return true;
}

// Obtain the base64 data from a signature File Upload field
function saveSignature() {
    // Make sure we have a signature first (bypass this for IE8 and lower or iOS 6 and lower because of some strange issue)
    if ($('#f1_upload_form input[name="myfile_base64_edited"]').val() == '0' && !((isIOS && iOSv <= 6))) {
        simpleDialog(window.lang.form_renderer_46, window.lang.global_01, null, 300);
        return false;
    }
    $('#signature-div, #signature-div-actions').hide();
    $('#f1_upload_form').show();
    var data = $('#signature-div').jSignature('getData', 'default');
    $('#f1_upload_form input[name="myfile_base64"]').val( data.substring(data.indexOf(',')+1) );
    $('form#form_file_upload').trigger('submit');
}

function startUpload(){
    // If didn't select a file, give an error msg
    var isSignature = ($('#f1_upload_form input[name="myfile_base64"]').val().length > 0);
    var missingFile = (!isSignature && $('#f1_upload_form input[name="myfile"]').val().length + $('#f1_upload_form input[name="myfile_base64"]').val().length == 0);
    if (!isSignature && missingFile) {
        simpleDialog(window.lang.form_renderer_47, window.lang.global_01, null, 300);
        return false;
    } else {
        if (!isSignature && !fileTypeAllowed(basename($('#f1_upload_form input[name="myfile"]').val()))) {
            Swal.fire(window.lang.docs_1136, '', 'error');
            return false;
        }
        document.getElementById('f1_upload_process').style.display = 'block';
        document.getElementById('f1_upload_form').style.display = 'none';
        return true;
    }
}

// Truncate a file name to X characters while still maintaining the file extension
function truncate_filename(filename, charLimit, truncateMarkFromEnd)
{
    if (typeof truncateMarkFromEnd == 'undefined') truncateMarkFromEnd = 9;
    var origLength = filename.length;
    if (origLength > charLimit) {
        filename = trim(filename.substr(0, charLimit - truncateMarkFromEnd))+"..."+trim(filename.substr(origLength - truncateMarkFromEnd));
    }
    return filename;
}

//For individual field File uploads
function filePopUp(field_name, signature, replace_version) {
    // Return if field validation errors exists
    if ($('#field_validation_error_state').val() == '1')  return false;

    // Reset value of hidden field used to determine if signature was signed
    $('#f1_upload_form input[name="myfile_base64_edited"]').val('0');
    $('#f1_upload_form input[name="myfile_replace"]').val(replace_version);
    // Set dialog content, etc.
    document.getElementById('file_upload').innerHTML = getFileUploadWinHTML();
    document.getElementById('field_name').value = field_name+'-linknew';
    // Dialog
    // var label = $('#label-'+field_name).clone();
    // label.find('.requiredlabel').remove();
    // label.find('#MDMenu').remove();
    // $("#field_name_popup").html(trim(label.text()));
    var labelText = $('#label-'+field_name+' div[data-kind=field-label]').html()
    $("#field_name_popup").html(trim(labelText));
    var dlgtitle = (signature == 1 ? window.lang.form_renderer_31 : (replace_version == 1 ? (window.lang.data_entry_459 + ' ' + window.lang.data_entry_468) : window.lang.form_renderer_23));
    $('#file_upload').dialog({ title: dlgtitle, bgiframe: true, modal: true, width: (isMobileDevice ? $('#questiontable').width() : 500) });
    // Signature?
    if (signature == 1) {
        $('#signature-div, #signature-div-actions').show();
        $('#f1_upload_form').hide();
        $('#signature-div').jSignature();
    } else {
        $('#signature-div, #signature-div-actions').hide();
        // Since iOS (v5.1 and below) devices do not support file uploading on webpages in Mobile Safari, give note to user about this.
        if (isIOS && iOSv <= 5) {
            $('#this_upload_field').hide();
            $('#f1_upload_form').html("<p style='color:red;'><b>" + window.lang.form_renderer_44 + "</b><br>" + window.lang.form_renderer_45 + "</p>");
        } else {
            $('#f1_upload_form').show();
        }
    }
    // In case any unsaved data from the form needs to be piped into the label in the dialog, manually trigger onblur for the field(s)
    $('#file_upload .piping_receiver').each(function(){
        // Get class that begins with "piperec"
        var classList = $(this).attr('class').split(/\s+/);
        for (var i = 0; i < classList.length; i++) {
            classList[i] = trim(classList[i]);
            if (classList[i].indexOf('piperec') === 0) {
                var evtRec = classList[i].split('-');
                // If the event_id is the current event_id of this form
                if (evtRec[1] == event_id) {
                    // Trigger onblur/change/click of the field (cover all the bases, even radio elements)
                    if ($('form#form [name="'+evtRec[2]+'___radio"]').length) {
                        $('form#form [name="'+evtRec[2]+'___radio"]:checked').trigger('click');
                    } else if ($('form#form [name="'+evtRec[2]+'"]').prop("tagName").toLowerCase() == 'select') {
                        $('form#form [name="'+evtRec[2]+'"] option:selected').trigger('change');
                    } else {
                        $('form#form [name="'+evtRec[2]+'"]').trigger('blur');
                    }
                }
            }
        }
    });
    // Set any embedded fields from the field label as read-only to prevent confusion
    $('#file_upload .rc-field-embed :input:visible').prop('disabled', true).css('background-color','#f5f5f5');
}

//For unchecking radio buttons
function uncheckRadioGroup (radioButtonOrGroup) {
    if (radioButtonOrGroup.length) { // we have a group
        for (var b = 0; b < radioButtonOrGroup.length; b++)
            if (radioButtonOrGroup[b].checked) {
                radioButtonOrGroup[b].checked = false;
                break;
            }
    }
    else
        try{radioButtonOrGroup.checked = false}catch(err){};
}

// Append hidden input to Data Entry Form (i.e. form#form)
function appendHiddenInputToForm(name,val) {
    $('form#form').append('<input type="hidden" value="'+val+'" name="'+name+'">');
}

// Enable green row highlight for data entry form table
function enableDataEntryRowHighlight() {
    $('form#form #questiontable :input, form#form #questiontable a')
        .bind('click focus select', function(event){
            // If save buttons are not displayed (e.g., form is locked), then don't highlight row
            if ($('#__SUBMITBUTTONS__-div').css('display') == 'none') return;
            // Exclude if clicked the Data History and balloon icons for this field
            if ($(this).has('img').length) return;
            // Obtain type of html tag source that triggered this event
            var targetTag = event.target.nodeName.toLowerCase();
            // Exclude "reset" links for radios (unless directly clicked)
            if ($(this).hasClass('cclink') && event.type != 'click') return;
            // Exclude text input, textarea, and drop-down click because it would have already been triggered by focus
            if (event.type == 'click' && (targetTag == 'textarea' || targetTag == 'select'
                || (targetTag == 'input' && $(event.target).attr('type') == 'text'))) return;
            // Skip over calc fields
            if (targetTag == 'input' && $(event.target).attr('type') == 'text' && $(event.target).attr('readonly') == 'readonly') return;
            // Find row element
            var tr = $(this).closest('tr');
            // Go up one or two levels if table nested within table
            if (tr.attr('sq_id') == null) tr = tr.parent().closest('tr');
            if (tr.attr('sq_id') == null) tr = tr.parent().closest('tr');
            // If could not find the row element, then stop
            if (tr.attr('sq_id') == null || tr.attr('id') == null || tr.attr('id').indexOf('-sh-tr') > -1) return;
            // Do green highlight on row
            var containsEmbeddedFields = $(this).parentsUntil('tr[sq_id]').parent().find('.rc-field-embed').length;
            var isEmbeddedResetLink = (targetTag == 'a' && $(this).hasClass('smalllink') && $(this).parents('span.rc-field-embed').length);
            var isEmbeddedField = (typeof $(this).attr('name') != 'undefined' && ($('.rc-field-embed[var="'+$(this).attr('name')+'"]').length
                                    || $('.rc-field-embed[var="'+$(this).attr('name').replace('___radio','')+'"]').length
                                    || $('.rc-field-embed[var="'+$(this).attr('name').replace('__chkn__','')+'"]').length));
            var triggerIsTodayNowBtn = (targetTag == 'button' && $(this).hasClass('today-now-btn'));
            var thisDoGreenHighlight = (!isEmbeddedResetLink && !isEmbeddedField && !triggerIsTodayNowBtn && !containsEmbeddedFields);
            if (thisDoGreenHighlight) doGreenHighlight(tr);
            // Check user's ability to save the form
            var readonly_form_rights = !($('#__SUBMITBUTTONS__-div').length && $('#__SUBMITBUTTONS__-div').css('display') != 'none');
            // Add custom "Save and Open Query Popup" button
            if (data_resolution_enabled == '2' && !data_locked && !readonly_form_rights) {
                // Get field name
                var fieldname = $(this).attr('name');
                // Has icons?
                if ($('#dc-icon-'+fieldname).length) {
                    var iconHtml = $('#dc-icon-' + fieldname).parent().html();
                    var hasExclRedIcon = (iconHtml.indexOf('balloon_exclamation.gif') > -1);
                    var hasExclBlueIcon = (iconHtml.indexOf('balloon_exclamation_blue.gif') > -1);
                    if (hasExclRedIcon || hasExclBlueIcon) {
                        // Add content to tooltip
                        $('#tooltipDRWsave').html('<div style="padding:12px 0 0 8px;overflow:hidden;">' +
                            '<button name="submit-btn-saverecord" class="jqbuttonmed" onclick="appendHiddenInputToForm(\'scroll-top\',\'' + ($(window).scrollTop()) + '\');appendHiddenInputToForm(\'dqres-fld\',\'' + fieldname + '\');dataEntrySubmit(this);return false;">' +
                            '<img src="' + app_path_images + 'balloon_exclamation' + (hasExclBlueIcon ? '_blue' : '') + '.gif"> '+lang.dataqueries_369+'</button>' +
                            '</div>');
                        // Buttonize the Save&Open Popup button
                        $('#tooltipDRWsave button').button();
                        // Open tooltip	to right of field
                        $('#tooltipDRWsave').show().position({
                            my: "left center",
                            at: "right+20 center",
                            of: this
                        });
                    } else {
                        $('#tooltipDRWsave').hide();
                    }
                }
            }
        });
}

// Highlight a form/survey table row with green background color
function doGreenHighlight(rowob) {
    // Reset bgcolor for all rows in case others are highlighted
    $('form#form #questiontable tr td.greenhighlight').removeClass('greenhighlight');
    // If found the row element, highlight all cells
    rowob.children("td").each(function() {
        $(this).addClass('greenhighlight');
        if ($(this).hasClass('labelmatrix')) {
            $(this).find('table tr td.data_matrix, table.mtxchoicetablechk tr td.data, table.mtxchoicetable tr td.data')
                .addClass('greenhighlight');
        }
    });
}

// Run when click the "reset value" for radio button fields
function radioResetVal(field,form) {
    // Set flag to deal with choices embedded in other choices
    if (justClickedEnhancedChoice) return;
    justClickedEnhancedChoice = true;
    setTimeout("justClickedEnhancedChoice = false;", 50);
    // Put everything inside a timeout due to issues with embedded radio fields
    setTimeout(function(){
        var currentVal = $('form[name="'+form+'"] input[name="'+field+'"]').val() ?? '';
        if (currentVal == '') return;
        $('form[name="'+form+'"] input[name="'+field+'___radio"]').prop('checked',false);
        $('form[name="'+form+'"] input[name="'+field+'"]').val('');
        if (form == 'form') {
            // If using Enhanced Choices for radios, then deselect it
            if ($('.rc-field-embed[var="'+field+'"]').length) {
                $('.rc-field-embed[var="'+field+'"] div.enhancedchoice label.selectedradio').removeClass('selectedradio');
            } else {
                $('#'+field+'-tr div.enhancedchoice label.selectedradio').removeClass('selectedradio');
            }
            // Piping: Transmit blank value to all piping receiver spans
            if (event_id != null) {
                $('.piping_receiver.piperec-'+event_id+'-'+field+', .piping_receiver.piperec-'+event_id+'-'+field+'-label, .piping_receiver.piperec-'+event_id+'-'+field+'-value').html('______');
                $('.piping_receiver.piperec-'+event_id+'-'+field+'.pipingrec-hideunderscore, .piping_receiver.piperec-'+event_id+'-'+field+'-label.pipingrec-hideunderscore, .piping_receiver.piperec-'+event_id+'-'+field+'-value.pipingrec-hideunderscore').html('');
                // Update drop-down options separately via ajax
                try{ updatePipingDropdowns(field,''); } catch(e) { }
            }
            setDataEntryFormValuesChanged(field);
            // If field has missing data code, then reset it
            removeFieldMissingDataCode(field);
            removeHasVal(field);
            // Branching logic and calculations
            try { calculate(field);doBranching(field); } catch(e){ }
        }
    },10);
    // Set vars used in Missing Data Codes
    if (typeof fieldToUpdate != 'undefined') {
        fieldName = field;
        fieldToUpdate = $('[name=' + fieldName + ']');
        qtype = 'radio';
    }
    return false;
}

// Check if value is unique
function checkSecondaryUniqueField(ob)
{
    // MLM active? Wait for it to be initialized before proceeding with the check
    // (otherwise, the displayed message will not be translated because MLM may 
    // not have had a chance to run yet).
    if (typeof REDCap['MultiLanguage'] != 'undefined' && REDCap['MultiLanguage'].isInitialized() == false) {
        setTimeout(function(){ checkSecondaryUniqueField(ob); }, 100);
        return;
    }
    var instance = getParameterByName('instance');
    if (instance == '') instance = '1';
    // Init values
    var url_base = 'DataEntry/check_unique_ajax.php';
    if (page == 'surveys/index.php') {
        // Survey page
        var record = ((document.form.__response_hash__.value == '') ? '' : $('#form :input[name="'+table_pk+'"]').val());
        var url = app_path_webroot_full+page+'?s='+getParameterByName('s')+'&__passthru='+encodeURIComponent(url_base);
    } else {
        // Data entry page
        var record = ((document.form.hidden_edit_flag.value == '0') ? '' : getParameterByName('id'));
        var url = app_path_webroot+url_base+'?page='+getParameterByName('page');
    }
    // If SUF is not visible, then do not do anything
    if (!$('#form :input[name="'+secondary_pk+'"]:visible').length) return;
    // Disable all form buttons temporarily
    $('#formSaveTip input[type="button"], #form input[type="button"], #form :input[name="'+secondary_pk+'"]').prop('disabled', true);
    // Check the SUF's value
    ob.val( trim(ob.val()) );
    if (ob.val().length > 0) {
        // Make ajax request
        $.ajax({
            url: url,
            type: 'GET',
            data: { pid: pid, field_name: secondary_pk, event_id: event_id, record: record, instance: instance, value: ob.val() },
            async: false,
            success:
                function(data){
                    if (data.length == 0) {
                        alert(woops);
                        setTimeout(function () { ob.focus() }, 1);
                    } else if (data != '0') {
                        if (page == 'surveys/index.php') {
                            simpleDialog(interpolateString(lang.data_entry_575, [ob.val()]), lang.data_entry_105, 'suf_warning_dialog',500,"$('#form :input[name="+secondary_pk+"]').focus();", lang.calendar_popup_01);
                        } else {
                            simpleDialog(interpolateString(lang.data_entry_576, [secondary_pk, ob.val()]), lang.data_entry_105, 'suf_warning_dialog',500,"$('#form :input[name="+secondary_pk+"]').focus();", lang.calendar_popup_01);
                        }
                        ob.css('font-weight','bold');
                        ob.css('background-color','#FFB7BE');
                        // If this is a DDP project and the DDP "preview data" dialog is displayed, close it
                        if ($('#rtws_idfield_new_record_warning').length && $('#rtws_idfield_new_record_warning').hasClass('ui-dialog-content')) {
                            $('#rtws_idfield_new_record_warning').dialog('close');
                        }
                    } else {
                        ob.css('font-weight','normal');
                        ob.css('background-color','#FFFFFF');
                    }
                    // Enable all form buttons again
                    $('#formSaveTip input[type="button"], #form input[type="button"], #form :input[name="'+secondary_pk+'"]').prop('disabled', false);
                }
        });
    } else {
        // Enable all form buttons again
        $('#formSaveTip input[type="button"], #form input[type="button"], #form :input[name="'+secondary_pk+'"]').prop('disabled', false);
    }
}

// Initialize any File Upload fields with @INLINE action tag
var valid_image_suffixes = new Array('jpeg','jpg','jpe','gif','png','tif','tiff','bmp','webp','svg');
function initInlineImages(currentfield)
{
    if (typeof currentfield == 'undefined') currentfield = '';
    var fieldEmbedded = false;
    if (currentfield == '') {
        // Multiple fields
        var selector = "#questiontable tr.\\@INLINE a.filedownloadlink, #questiontable .rc-field-embed.file-upload-inline-embed a.filedownloadlink";
    } else if ($("#questiontable .rc-field-embed[var='"+currentfield+"']").length) {
        // Single field embedded
        var selector = "#questiontable .rc-field-embed[var='"+currentfield+"'] a.filedownloadlink";
    } else {
        // Single field non-embedded
        var selector = "#questiontable tr#"+currentfield+"-tr a.filedownloadlink";
    }
    var usleep = 0;
    // Loop through one or more images to embed
    $(selector).each(function(){
        // Attributes
        var src = $(this).attr('href').replace('DataEntry/file_download.php','DataEntry/image_view.php')
                                      .replace('DataEntry%2Ffile_download.php','DataEntry%2Fimage_view.php'); // Change to image_view.php
        src += "&usleep="+usleep;
        var field = $(this).attr('name');
        var filename = $(this).find('.fu-fn').attr('vf');
        var fileext = getfileextension(filename.toLowerCase());
        var td = $("#questiontable .rc-field-embed[var='"+field+"']").length ? $("#questiontable .rc-field-embed[var='"+field+"']") : $("#questiontable tr#"+field+"-tr>td:last");
        var maxwidth = td.width();
        var isImage = in_array(fileext, valid_image_suffixes);
        var isPdf = (fileext == 'pdf');
        var dim = $('input[type="hidden"][name="'+field+'"]').attr('inlinedim');
        if (typeof dim == 'undefined') {
            dim = new Array();
        } else {
            dim = (dim.indexOf(',') > -1) ? dim.split(',') : new Array(dim);
        }
        var width = (dim.length > 0) ? "width:"+dim[0]+(isNumeric(dim[0]) ? "px" : "")+";" : "";
        var height = (dim.length > 1) ? "height:"+dim[1]+(isNumeric(dim[1]) ? "px" : "")+";" : "";
        // Decide action to take
        var action = true;
        if ($(this).css('display') == 'none' || (!isImage && !isPdf)) {
            // If file was removed, then remove embedded image too
            $(this).parent().find('.file-upload-inline').remove();
            action = false;
        } else if ((isPdf && td.find('iframe.file-upload-inline').length) || (isImage && td.find('img.file-upload-inline').length)) {
            // Update src attribute if embedded PDF/image already exists on page
            td.find('object.file-upload-inline').attr('data', src);
            td.find('iframe.file-upload-inline, img.file-upload-inline').attr('src', src);
            td.find('img.file-upload-inline').attr('alt', filename);
        } else if (isPdf) {
            // Remove in case already existed as other tag type
            $(this).parent().find('.file-upload-inline').remove();
            // Add iframe for embedded PDF
            if (height == "") height = "height:300px;";
            // const $pdf = $("<object data='"+src+"' data-file-id='" + getFileId(field) + "' class='file-upload-inline' type='application/pdf' style='width:100%;"+width+height+"max-width:"+maxwidth+"px;'><iframe class='file-upload-inline' src='"+src+"' style='width:100%;border:none;max-width:"+maxwidth+"px;"+height+"'></iframe></object>");
            const $pdf = $(renderInlinePdfContainer(src));
            $(this).before($pdf);
            addFileEnhancements();
            initInlinePdfs();
        } else if (isImage) {
            // Remove in case already existed as other tag type
            $(this).parent().find('.file-upload-inline').remove();
            // Add img tag for embedded image
            $(this).before('<img src="'+src+'" class="file-upload-inline" style="'+width+height+'max-width:'+maxwidth+'px;" alt="'+htmlspecialchars(filename)+'">');
        } else {
            action = false;
        }
        if (action) {
            usleep += 100000;
        }
    });
}

// Auto-fill the form/survey
function autoFill()
{
    $("tr[sq_id], .rc-field-embed[var]").each(function(){
        if ($(this).is(':visible')) {
            var field_name = $(this).attr($(this).hasClass('rc-field-embed') ? 'var' : 'sq_id');
            if (typeof randomizationFieldsThisForm != 'undefined' && in_array(field_name, randomizationFieldsThisForm)) {
                // Skip the randomization field(s) if on the page
                return;
            }
            $('html, body').animate({scrollTop: $(this).offset().top},1);
            autoFillRow(this);
            if (field_name != '{}' && field_name != '') {
                doBranching(field_name);
            }
            setDataEntryFormValuesChanged(field_name);
        }
    });
}
function autoFillRow(tr)
{
    var date_types = new Array('date_ymd', 'date_mdy', 'date_dmy', 'datetime_ymd', 'datetime_mdy', 'datetime_dmy',
                                'datetime_seconds_ymd', 'datetime_seconds_mdy', 'datetime_seconds_dmy', 'time', 'time_mm_ss', 'time_hh_mm_ss');

    // Select a dropdown value
    var options = $(tr).find('option:not([value=""])');
    if (options.length > 0) {
        var randomnumber = Math.floor(Math.random() * options.length);
        $(options[randomnumber]).prop('selected', true);
        if (options.parent().hasClass('rc-autocomplete')) {
            $(':input#rc-ac-input_'+ options.parent().prop('name')).val( $(options[randomnumber]).html() ).trigger('blur');
        }
        return;
    }

    // Check checkboxes
    var checkboxes = $(tr).find("input[type=checkbox]").filter(":visible").filter(":not([id='__LOCKRECORD__'])");
    var enhancedchoice = $(tr).find("div.enhancedchoice label.selectedchkbox, div.enhancedchoice label.unselectedchkbox").filter(":visible");
    var checkboxes_checked = $('input:checked',tr);
    if (checkboxes.length > 0 && checkboxes_checked.length == 0 && enhancedchoice.length == 0) {
        var randomnumber = Math.floor(Math.random() * checkboxes.length);
        $(checkboxes[randomnumber]).trigger('click');
        return;
    }
    // Check for enhanced checkboxes
    var enhancedCheckSelected = $(tr).find("div.enhancedchoice label.selectedchkbox").filter(":visible").length;
    if (enhancedchoice.length) {
        if (enhancedCheckSelected >= 1) return;
        var randomnumber = Math.floor(Math.random() * enhancedchoice.length);
        $(tr).find("div.enhancedchoice label:eq("+randomnumber+")").trigger('click').removeClass('unselectedchkbox').addClass('selectedchkbox');
        return;
    }

    // Check a random radio button (skip checked radios)
    var radios = $(tr).find("input[type=radio]").filter(":visible");
    var radios_checked = $(tr).find("input[type=radio]:checked");
    if ((radios.length > 0) && radios_checked.length == 0) {
        var randomnumber = Math.floor(Math.random() * radios.length);
        radios[randomnumber].checked = true;
        $(radios[randomnumber]).trigger('click').trigger('blur');
        return;
    }
    // Check for enhanced radios
    var enhancedchoice = $(tr).find("div.enhancedchoice").filter(":visible");
    if (enhancedchoice.length > 0 && radios_checked.length == 0) {
        var randomnumber = Math.floor(Math.random() * enhancedchoice.length);
        $(tr).find("div.enhancedchoice:eq("+randomnumber+") label:first").trigger('click').addClass('selectedradio');
        return;
    }

    // Set sliders
    var sliders = $(tr).find("div.slider:first");
    if (sliders.length) {
        sliders.trigger('mousedown');
        return;
    }

    // Handle text inputs
    var inputs = $(tr).find("input[type=text]").each(function(i,e){
        // Skip ones with existing values
        if ($(e).val() !== "") return;

        // Check for field-validation attribute
        var fv = (typeof $(e).attr('fv') == 'undefined') ? '' : $(e).attr('fv');
        var min='', max='';

        if (fv == 'email') {
            $(e).val('test@noreply.com');
        } else if (fv == 'integer') {
            b = $(e).attr('onblur');
            parts = b.replace(/'/g,'').split(',');
            if (typeof parts[1] != 'undefined' && parts[1] != '') {
                min = parts[1];
            }
            if (typeof parts[2] != 'undefined' && parts[2] != '') {
                max = parts[2];
            }
            val = autoFillRandomIntFromInterval(min, max);
            $(e).val(val);
        } else if (in_array(fv, date_types)) {
            b = $(e).attr('onblur');
            parts = b.replace(/'/g,'').split(',');
            if (typeof parts[1] != 'undefined' && parts[1] != '') {
                // If has min range check
                if (parts[1] === 'now') {
                    parts[1] = getCurrentDate(fv.replace(/_dmy/,'_ymd').replace(/_mdy/,'_ymd'))+' '+currentTime('both',(fv.indexOf('_seconds') > -1));
                } else if (parts[1] === 'today') {
                    parts[1] = getCurrentDate(fv.replace(/_dmy/,'_ymd').replace(/_mdy/,'_ymd'));
                }
                // Convert from ymd to mdy/dmy, if needed
                if (fv.indexOf('_mdy') > -1) {
                    parts[1] = date_ymd2mdy(parts[1]);
                } else if (fv.indexOf('_dmy') > -1) {
                    parts[1] = date_ymd2dmy(parts[1]);
                }
                $(e).val(parts[1]);
            } else if (typeof parts[2] != 'undefined' && parts[2] != '') {
                // If has max range check
                if (parts[2] === 'now') {
                    parts[2] = getCurrentDate(fv.replace(/_dmy/,'_ymd').replace(/_mdy/,'_ymd'))+' '+currentTime('both',(fv.indexOf('_seconds') > -1));
                } else if (parts[2] === 'today') {
                    parts[2] = getCurrentDate(fv.replace(/_dmy/,'_ymd').replace(/_mdy/,'_ymd'));
                }
                // Convert from ymd to mdy/dmy, if needed
                if (fv.indexOf('_mdy') > -1) {
                    parts[2] = date_ymd2mdy(parts[2]);
                } else if (fv.indexOf('_dmy') > -1) {
                    parts[2] = date_ymd2dmy(parts[2]);
                }
                $(e).val(parts[2]);
            } else {
                if (($(e).parent().find("button[onclick^='set']").length > 0)) {
                    $(e).parent().find("button").trigger('click');
                } else {
                    switch(fv) {
                        case "date_ymd":
                            $(e).val(today);
                            break;
                        case "date_mdy":
                            $(e).val(today_mdy);
                            break;
                        case "date_dmy":
                            $(e).val(today_dmy);
                            break;
                        case "datetime_ymd":
                            $(e).val(today + " 00:00");
                            break;
                        case "datetime_mdy":
                            $(e).val(today_mdy + " 00:00");
                            break;
                        case "datetime_dmy":
                            $(e).val(today_dmy + " 00:00");
                            break;
                        case "datetime_seconds_ymd":
                            $(e).val(today + " 00:00:00");
                            break;
                        case "datetime_seconds_mdy":
                            $(e).val(today_mdy + " 00:00:00");
                            break;
                        case "datetime_seconds_dmy":
                            $(e).val(today_dmy + " 00:00:00");
                            break;
                        case "time":
                            $(e).val('12:34');
                            break;
                        case "time_mm_ss":
                            $(e).val('34:57');
                            break;
                        case "time_hh_mm_ss":
                            $(e).val('12:34:57');
                            break;
                    }
                }
            }
        } else if (fv.indexOf('number') === 0) {
            b = $(e).attr('onblur');
            parts = b.replace(/'/g,'').split(',');
            // Apply min/max if defined, else generate a value
            if (typeof parts[1] != 'undefined' && parts[1] != '') {
                if (fv == 'number_comma_decimal' || ends_with(fv,'dp_comma_decimal')) {
                    // If a comma decimal number, compensate for the fact that we're parsing the validation function by comma
                    parts[1] = parts[1]+','+parts[2];
                }
                $(e).val(parts[1]);
            } else if (typeof parts[2] != 'undefined' && parts[2] != '') {
                if (fv == 'number_comma_decimal' || ends_with(fv,'dp_comma_decimal')) {
                    // If a comma decimal number, compensate for the fact that we're parsing the validation function by comma
                    parts[2] = parts[2]+','+parts[3];
                }
                $(e).val(parts[2]);
            } else {
                if (fv == 'number') {
                    $(e).val('3.14');
                } else if (fv == 'number_comma_decimal') {
                    $(e).val('3,14');
                } else if (fv.indexOf('number_') === 0 && (ends_with(fv,'dp') || ends_with(fv,'dp_comma_decimal'))) {
                    var comma = ends_with(fv,'dp_comma_decimal') ? "," : ".";
                    var numDecimals = fv.substring(7).replace('dp','').replace('_comma_decimal','');
                    var thisVal = '3'+comma;
                    for (var i=0;i<numDecimals;i++) thisVal += '4';
                    $(e).val(thisVal);
                } else {
                    $(e).val('3');
                }
            }
        } else if (fv == 'zip' ) {
            $(e).val('55112');
        } else if (fv == 'zipcode' ) {
            $(e).val('55112');
        } else if (fv == 'phone') {
            $(e).val('(555) 867-5309');
        } else if (fv == "time_shorthand") {
            $(e).val('1122');
        } else if (fv == "lab_value") {
            $(e).val('123');
        } else {
            // Get a random word
            $(e).val(autoFillGetRandomWord());
        }
        $(e).trigger('blur');
        return;
    });

    // Set textarea
    var textarea = $(tr).find('textarea');
    if (textarea.length && textarea.val() == '') {
        textarea.val(autoFillGetRandomWord());
        return;
    }
}
function autoFillGetRandomWord() {
    var random_words = ['Rock', 'Paper', 'Scissors'];
    return random_words[Math.floor(Math.random()*random_words.length)];
}
function autoFillRandomIntFromInterval(min, max) { // min and max included
    if (max == '') max = 9999;
    if (min == '') min = -9999;
    min = min*1;
    max = max*1;
    return Math.floor(Math.random() * (max - min + 1) + min);
}

//#region File Enhancements (PDF and image preview)

/** Adds enhancements to inline PDFs, such as resizing, min/max, etc. */
function addFileEnhancements() {
    initFileEnhancements();
    setTimeout(() => {
        // Add previews
        $('[data-file-preview]').each(function() {
            try {
                window.REDCapFileEnhancements.addPreview(this);
            }
            catch (ex) {} // Ignore
        })
        $('.fileupload-container[data-file-type="file"][data-can-preview="1"]').each(function() {
            try {
                window.REDCapFileEnhancements.addFileUploadPreview(this);
            }
            catch (ex) { console.error(ex);} // Ignore
        })
        // Add resizers
        $('object[type="application/pdf"], .inline-pdf-viewer').each(function() {
            try {
                window.REDCapFileEnhancements.addResizer(this);
            }
            catch (ex) {} // Ignore
        });
        // Cleanup
        $('.inline-pdf-resizer').each(function() {
            window.REDCapFileEnhancements.cleanup(this);
        })
    }, 0);
}

/** Initializes PDF enhancements */
function initFileEnhancements(debug = false) {
    if (typeof window.REDCapFileEnhancements == 'undefined') window.REDCapFileEnhancements = (() => {
        const body = document.getElementsByTagName('body')[0];
        const minH = 50;
        const pdfs = {};
        let current = false;
        const config = {
            debug: debug
        };
        //#region Preview
        /** @param {HTMLElement} fileUpload */
        function addFileUploadPreview(fileUpload) {
            const $a = $(fileUpload).find('a.filedownloadlink');
            const fileSrc = $a.attr('href').replace('file_download.php', 'image_view.php');
            const fieldName = $a.attr('name');
            if (!fileSrc || $a.attr('data-file-initialized') == '1') return; // No file or already initialized
            const fileId = getFileId(fieldName);
            const fileName = $a.find('span[vf]').attr('vf');
            const fileExt = getfileextension(fileName.toLowerCase());
            if (!(valid_image_suffixes.includes(fileExt) || fileExt == 'pdf')) return; // Not supported
            $a.attr('data-file-initialized', '1');
            $a.attr('data-file-id', fileId);
            $a.attr('data-file-type', fileExt == 'pdf' ? 'pdf' : 'img')
            const $btn = $('<div class="file-preview-button-container">\
                <button class="btn btn-secondary btn-xs rc-preview-file" type="button">\
                    <i class="fa-solid fa-magnifying-glass show-preview"></i>\
                    <i class="fa-solid fa-xmark hide-preview"></i>\
                    <span class="visually-hidden" data-rc-lang="data_entry_605">' +
                        window.lang.data_entry_605 + '\
                    </span>\
                </button></div>');
            $btn.attr('data-file-id', fileId);
            $('#'+fieldName+'-linknew').after($btn);
            if (config.debug) console.log('File Upload Preview:', fileUpload);
            $btn.on('click', function() {
                // Viewer already there?
                let viewerContainer = document.querySelector('.preview-container[data-file-id="' + fileId + '"]');
                if (viewerContainer == null) {
                    // Create preview
                    viewerContainer = document.createElement('div');
                    viewerContainer.dataset.fileId = fileId;
                    viewerContainer.classList.add('preview-container');
                    let viewer;
                    if (fileExt == 'pdf') {
                        viewer = document.createElement('div');
                        viewer.setAttribute('id', fileId);
                        viewer.setAttribute('src', fileSrc);
                        viewer.setAttribute('class', 'inline-pdf-viewer');
                        viewerContainer.appendChild(viewer);
                    }
                    else {
                        viewer = document.createElement('img');
                        viewer.dataset.fileId = fileId;
                        viewer.setAttribute('src', fileSrc);
                        viewer.style.maxWidth = '100%';
                        viewerContainer.appendChild(viewer);
                    }
                    if (fileExt == 'svg') {
                        viewerContainer.style.backgroundColor = '#FFFFFF'; // White background for SVG
                    }
                    $btn.addClass('previewing');
                    $btn.after(viewerContainer);
                    if (fileExt == 'pdf') addResizer(viewer);
                    return;
                }
                if ($btn.hasClass('previewing')) {
                    $btn.removeClass('previewing');
                    viewerContainer.style.display = 'none';
                    $('tr[data-viewer-for-pdf="' + fileId + '"]').hide();
                }
                else {
                    $btn.addClass('previewing');
                    viewerContainer.style.display = 'block';
                    $('tr[data-viewer-for-pdf="' + fileId + '"]').show();
                }
            });
        }
        function removeFileUploadPreview(fieldName) {
            const $a = $('a.filedownloadlink[name="' + fieldName + '"]');
            const fileId = $a.parents('.fileupload-container').find('[data-file-id]').attr('data-file-id');
            $a.attr('data-file-initialized', null);
            $a.attr('data-file-id', null);
            $a.attr('data-file-type', null)
            $('[data-file-id="' + fileId + '"]').remove();
            $('.inline-pdf-resizer[data-target-pdf="' + fileId + '"]').remove();
            if (config.debug) console.log('Removed file upload preview:', fieldName, fileId);
        }
        /** @param {HTMLElement} fileAttachment */
        function addPreview(fileAttachment) {
            const $fileAttachment = $(fileAttachment);
            let fileId = fileAttachment.dataset.filePreview;
            if (typeof fileId !== 'string') fileId = false;
            const fileSrc = $fileAttachment.find('a.file-link').attr('href').replace('file_download.php', 'image_view.php');
            if (!fileId || !fileSrc) return; // Missing essentials
            const $btn = $fileAttachment.find('button.rc-preview-file');
            if ($btn.attr('data-file-initialized') == '1') return; // Already initialized
            if (config.debug) console.log('Preview:', fileAttachment);
            let type = $btn.attr('data-file-type');
            if (typeof type == 'undefined') type = 'application/pdf';
            $btn.on('click', function() {
                // Viewer already there?
                let viewerContainer = document.querySelector('.preview-container[data-file-id="' + fileId + '"]');
                if (viewerContainer == null) {
                    // Create preview
                    viewerContainer = document.createElement('div');
                    viewerContainer.dataset.fileId = fileId;
                    viewerContainer.classList.add('preview-container');
                    let viewer;
                    if (type == 'application/pdf') {
                        viewer = document.createElement('div');
                        viewer.setAttribute('id', fileId);
                        viewer.setAttribute('src', fileSrc);
                        viewer.setAttribute('class', 'inline-pdf-viewer');
                        viewerContainer.appendChild(viewer);
                    }
                    else {
                        viewer = document.createElement('img');
                        viewer.dataset.fileId = fileId;
                        viewer.setAttribute('src', fileSrc);
                        viewer.style.maxWidth = '100%';
                        viewerContainer.appendChild(viewer);
                    }
                    if (type == 'image/svg+xml') {
                        viewerContainer.style.backgroundColor = '#FFFFFF'; // White background for SVG
                    }
                    $fileAttachment.after(viewerContainer);
                    $fileAttachment.addClass('previewing');
                    if (type == 'application/pdf') addResizer(viewer);
                    return;
                }
                if ($fileAttachment.hasClass('previewing')) {
                    $fileAttachment.removeClass('previewing');
                    viewerContainer.style.display = 'none';
                }
                else {
                    $fileAttachment.addClass('previewing');
                    viewerContainer.style.display = 'block';
                }
            });
            $btn.attr('data-file-initialized', '1');
        }
        function removePreview(fileAttachment) {
            if (config.debug) console.log('Remove preview:', fileAttachment);
            const fileId = fileAttachment.dataset.filePreview;
            fileAttachment.classList.remove('previewing');
            $(fileAttachment).find('button.rc-preview-file').attr('data-file-initilized', null);
            $('.preview-container[data-file-id="' + fileId + '"]').remove();
        }
        //#endregion
        //#region PDF Resizer
        document.addEventListener("pointermove", function(e) {
            if (!current) return;
            const h = Math.max(minH, current.resize.initialH + e.clientY - current.resize.initialY);
            current.pdf.style.height = h + 'px';
            if (!current.maximize.moved) {
                current.resizer.classList.remove('maximized');
            }
            if(e.stopPropagation) e.stopPropagation();
            if(e.preventDefault) e.preventDefault();
            e.cancelBubble = true; // Legacy (deprecated)
            e.returnValue = false; // Legacy (deprecated)
            return false;
        });
        document.addEventListener("pointerup", function(e) {
            const pdf = current;
            current = false;
            if (pdf) {
                setTimeout(() => {
                    pdf.resizing = false;
                }, 10);
                pdf.resizer.classList.remove('resizing');
                body.classList.remove('resizing-v');
                pdf.pdf.classList.remove('resizing');
                if (!pdf.maximize.moved) {
                    pdf.resizer.classList.remove('maximized');
                }
                if(e.stopPropagation) e.stopPropagation();
                if(e.preventDefault) e.preventDefault();
                e.cancelBubble = true; // Legacy (deprecated)
                e.returnValue = false; // Legacy (deprecated)
                if (config.debug) console.log('Done resizing:', pdf);
            }
        });
        /** @param {HTMLElement} pdf */
        function addResizer(pdf) {
            if (pdf.dataset.pdfEnhanced) return;
            pdf.dataset.pdfEnhanced = true;
            const pdfId = pdf.id;
            const $resizer = $(' \
                <div class="inline-pdf-resizer"> \
                    <i class="fa-solid fa-grip-lines resize"></i> \
                    <button class="btn btn-xs btn-light" data-action="enter-fs" type="button"> \
                        <i class="fa-solid fa-expand"></i> \
                        <span data-rc-lang="form_renderer_66" class="visually-hidden">' + lang.form_renderer_66 + '</span> \
                    </button> \
                    <button class="btn btn-xs btn-light" data-action="exit-fs" type="button"> \
                        <i class="fa-solid fa-compress"></i> \
                        <span data-rc-lang="form_renderer_66" class="visually-hidden">' + lang.form_renderer_66 + '</span> \
                    </button> \
                </div>');
            const resizer = $resizer.get(0);
            resizer.dataset.targetPdf = pdfId;
            let field = $(pdf).siblings(".filedownloadlink").attr('name');
            if (typeof field !== 'string') field = false;
            if (field) {
                // Remove and pre-existing resizers for this field
                Object.keys(pdfs).filter((id) => pdfs[id].field === field).forEach((id) => remove(id));
            }
            pdfs[pdfId] = {
                resize: {
                    initialH: 500,
                    initialY: 0
                },
                maximize: {
                    initialH: 500,
                    initialY: 0,
                    w: pdf.clientWidth,
                    maxW: pdf.style.maxWidth,
                    moved: false,
                },
                resizer: resizer,
                pdf: pdf,
                isNarrow: $(pdf).parents('tr').find('td.data').length > 0,
                maximized: false,
                resizing: false,
                field: field
            };
            $resizer.on('pointerdown', function(e) {
                if (e.originalEvent.target.classList.contains('inline-pdf-resizer')) {
                    current = pdfs[pdfId];
                    current.resizing = true;
                    if (config.debug) console.log('Resizing: ', current, e);
                    resizer.classList.add('resizing');
                    body.classList.add('resizing-v');
                    pdf.classList.add('resizing');
                    pdfs[pdfId].resize.initialH = pdf.clientHeight;
                    pdfs[pdfId].resize.initialY = e.clientY;
                }
            });
            $resizer.find('[data-action=enter-fs]').on('click', function(e) {
                if (pdfs[pdfId].resizing) return; 
                pdfs[pdfId].maximized = true;
                resizer.classList.add('maximized');
                pdfs[pdfId].maximize.initialH = pdf.clientHeight;
                pdfs[pdfId].maximize.initialY = window.scrollY;
                if (pdfs[pdfId].isNarrow) {
                    // Move to full row
                    pdfs[pdfId].maximize.w = pdf.clientWidth;
                    pdfs[pdfId].maximize.maxW = pdf.style.maxWidth;
                    pdfs[pdfId].maximize.moved = true;
                    const placeholder = document.createElement('div');
                    placeholder.style.display = 'none';
                    placeholder.dataset.placeholderForPdf = pdfId;
                    $resizer.after(placeholder);
                    const $parentTR = $resizer.parents('tr[sq_id]');
                    const colspan = $parentTR.children('td').length;
                    const $container = $(' \
                        <tr data-viewer-for-pdf="' + pdfId + '"> \
                            <td class="labelrc" colspan="' + colspan + '" class="col-12" style="padding: 2px 5px;"></td> \
                        </tr>');
                    $parentTR.after($container);
                    $container.find('td').append(pdf).append($resizer);
                    pdf.style.width = '100%';
                    pdf.style.maxWidth = '100%';
                }
                const scrollbarH = window.innerHeight - document.documentElement.clientHeight;
                const viewportH = window.innerHeight - scrollbarH;
                const extra = resizer.offsetTop - pdf.offsetTop - pdf.offsetHeight;
                const finalH = viewportH - resizer.clientHeight - extra - 2;
                pdf.style.height = finalH + 'px';
                setTimeout(() => {
                    pdf.scrollIntoView();
                }, 0); 
                $resizer.find('[data-action=exit-fs]').trigger('focus');
                if (config.debug) console.log('Maximized:', pdfs[pdfId], e);
            });
            $resizer.find('[data-action=exit-fs]').on('click', function(e) {
                pdfs[pdfId].maximized = false;
                if (pdfs[pdfId].isNarrow) {
                    // Move back to cell
                    pdf.style.width = pdfs[pdfId].maximize.w + 'px';
                    pdf.style.maxWidth = pdfs[pdfId].maximize.maxW;
                    $('[data-placeholder-for-pdf="' + pdfId + '"]').after(resizer).after(pdf).remove();
                    $('tr[data-viewer-for-pdf="' + pdfId + '"]').remove();
                }
                resizer.classList.remove('maximized');
                pdf.style.height = pdfs[pdfId].maximize.initialH + 'px';
                setTimeout(() => {
                    pdfs[pdfId].maximize.moved = false;
                    window.scroll({ top: pdfs[pdfId].maximize.initialY });
                }, 0);
                $resizer.find('[data-action=enter-fs]').trigger('focus');
                if (config.debug) console.log('Restored:', pdfs[pdfId], e);
            });
            // Add resize widget
            $(pdf).after($resizer);
            // Prevent <label> interference
            $resizer.parents('label').each(function() {
                if (!this.getAttributeNames().includes('for')) this.setAttribute('for', 'this-is-some-dummy-id-that-probably-does-not-exist');
            })
            if (config.debug) console.log('Resizer:', pdfs[pdfId]);
            initInlinePdfs();
        }
        function cleanup(el) {
            let id = el.dataset.targetPdf;
            if (typeof id !== 'string') id = false;
            if (id) {
                if (pdfs[id].field && $('input[type=hidden][name="' + pdfs[id].field + '"]').val() == '') {
                    remove(id);
                }
            }
        }
        function remove(id) {
            pdfs[id].resizer.remove();
            $('[data-placeholder-for-pdf="' + id + '"]').remove();
            $('[data-viewer-for-pdf="' + id + '"]').remove();
            delete pdfs[id];
        }
        //#endregion

        function setDebugMode(mode) {
            config.debug = mode == true
        };

        // Public interface
        return {
            addPreview: (fileAttachment) => {
                try { addPreview(fileAttachment); } 
                catch (ex) { if(config.debug) console.error(ex); }
            },
            removePreview: (fileAttachment) => {
                try { removePreview(fileAttachment); } 
                catch (ex) { if(config.debug) console.error(ex); }
            },
            addFileUploadPreview: (fileUpload) => {
                try { addFileUploadPreview(fileUpload); } 
                catch (ex) { if(config.debug) console.error(ex); }
            },
            removeFileUploadPreview: (fileUpload) => {
                try { removeFileUploadPreview(fileUpload); } 
                catch (ex) { if(config.debug) console.error(ex); }
            },
            addResizer: (pdf) => {
                try { addResizer(pdf); } 
                catch (ex) { if(config.debug) console.error(ex); }
            },
            cleanup: (el) => {
                try { cleanup(el); } 
                catch (ex) { if(config.debug) console.error(ex); }
            },
            debug: (mode) => setDebugMode(mode)
        };
    })();
}

//#endregion
