$(function() {
    $('body').on('mouseover', '[data-bs-toggle="popover"]', function(e) {
        // Hide any other opened popups first
        if ($('.popover:visible').length > 0) {
            $('.popover').hide();
        }
        if (!this.dataset.content) return;
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

    $('body').on('focus', '#final-text', function(e) {
        var $this = $(this);
        $this.data('before', $this.html());
        return $this;
    });

    $('body').on('focusout', '#final-text', function(e) {
        var divEle = $(this);
        if (divEle.html().trim()) {
            if (divEle.html() === divEle.data('before')) {
                // No Changes to text
            } else {
                // Changes done so perform grammar check and evaluate reading level
                $("i#grammar-status").addClass('fa-spinner fa-spin-pulse').removeClass('fa-check fa-spell-check fa-exclamation-circle');
                $("i#grammar-status").css({'color': ''});
                sendToChatGPT('check_grammer', '', divEle.html());
                $("#open-info-box").remove();
                $("#reading-level-info").html('<i id="spinReadingLevel" class="fa-solid fa-spinner fa-spin-pulse"></i>');
                sendToChatGPT('get_reading_level', divEle.html());
            }
        }
    });
});

// Open popup for functionality - Improve text using Azure OpenAI feature
function openImproveTextByAIPopup(openerId, fullscreen, onUpdateFn)
{
    let tinyMceInstance = tinymce.get(openerId);
    if( tinyMceInstance === null ){
        // Your code if not it is not a tinyMCE
    } else {
        var orig_content = tinymce.get(openerId).getContent();
    }

    // Init settings
    $('#rc-chatgpt-editor').popover('hide');

    if (typeof fullscreen == 'undefined') fullscreen = false;
    // Set regular size settings
    var containerHeight = 210;
    var editorStyle = 'height:450px;';
    var dialogWidth = 875;
    var buttonText1 = window.lang.global_168;

    var custom_prompt_html = '<div style="background-color: #f5f5f5; border:none;">\n' +
        '                                 <div><hr style="border-top: 1px solid #AAA;"></div>' +
        '                                 <div style="margin:0 0 5px;font-weight:bold;">'+lang.openai_051+'<a href="javascript:;" style="font-weight:normal;margin-left:10px;font-size:11px;text-decoration:underline;" onclick="$(\'#custom-prompt-input\').hide();$(\'#custom-prompt\').val(\'\');">'+lang.openai_052+'</a></div>\n' +
        '                                 <div class="openai-container">' +
        '                                     <div><textarea id="custom-prompt" class="external-modules-input-element" style="width: 98%;" placeholder="'+lang.openai_053+'"></textarea></div>\n' +
        '                                     <div class="cc_info" style="float: left; color: #777;">'+lang.openai_054+'</div>' +
        '                                    <div style="float:right;margin:0 10px 0 20px; padding-top:5px;">\n' +
        '                                         <a href="javascript:;" style="font-size:11px;color:#3E72A8;text-decoration:underline;margin-right:20px;" onclick="simpleDialog(null,null,\'useCustomPromopDialog\');">'+lang.openai_055+'</a> \n' +
        '                                         <button type="button" class="btn btn-xs btn-defaultrc" style="font-size:11px;" onclick="sendToChatGPT(\'custom_prompt\');"><i class="fa-solid fa-wand-sparkles"></i> '+lang.openai_056+'</button></div>\n' +
        '                                         <div class="clear"></div>\n' +
        '                                     </div>\n' +
        '                                 </div>';

    var action_html = '<div class="btns-panel panel panel-default" style="margin-bottom:0">\n' +
        '\t<div class="panel-heading">\n' +
        '    \t<div class="row">\n' +
        '        \t<div class="col-lg-12 col-md-12 col-sm-12">\n' +
        '                  <div style="display: inline-block; margin-right:5px; font-size: 12px; font-weight: bold; color: #800000;">'+lang.survey_281+'</div>' +
        '                \t<div class="btn-ai-group">\n' +
        '                    \t<button type="button" onclick="sendToChatGPT(\'fix_grammar\', \'\');return false;" class="btn-ai btn-ai-default" id="checkGrammerBtn"><i id="grammar-status" class="fa-solid fa-spinner fa-spin-pulse fa-sm"></i> <span class="action-text">'+lang.openai_004+'</span></button>\n' +
        '                  </div>\n' +
        '                   <div class="btn-ai-group">' +
        '                           <button id="btnSetLengthDrop" type="button" class="btn-ai btn-ai-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false" data-bs-toggle=\'dropdown\' aria-haspopup=\'true\' aria-expanded=\'false\'><i class="fas fa-file-lines fa-sm"></i> <span class="action-text">'+lang.openai_015+'</span></button>' +
        '                           <div class="dropdown-menu" aria-labelledby="btnSetLengthDrop">'+
        '                                   <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_length\', \'25\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_016+'</a>'+
        '                                   <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_length\', \'50\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_017+'</a>'+
        '                                   <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_length\', \'75\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_018+'</a>'+
        '                                   <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_length\', \'one_paragraph\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_019+'</a>'+
        '                                   <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_length\', \'25+\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_020+'</a>'+
        '                                   <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_length\', \'50+\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_021+'</a>'+
        '                                   <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_length\', \'75+\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_022+'</a>'+
        '                               </div>' +
        '                   </div>' +
        '                   <div class="btn-ai-group">' +
        '                           <button id="btnChangeToneDrop" type="button" class="btn-ai btn-ai-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false" data-bs-toggle=\'dropdown\' aria-haspopup=\'true\' aria-expanded=\'false\'><i class="fas fa-microphone fa-sm"></i> <span class="action-text">'+lang.openai_023+'</span></button>' +
        '                           <div class="dropdown-menu" aria-labelledby="btnChangeToneDrop">'+
        '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'change_tone\', \'formal\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_024+'</a>'+
        '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'change_tone\', \'friendly\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_025+'</a>'+
        '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'change_tone\', \'encourage\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_026+'</a>'+
        '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'change_tone\', \'professional\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_027+'</a>'+
        '                           </div>' +
        '                   </div>' +
          '                  <div class="btn-ai-group">\n' +
          '                       <button id="btnSetReadingLevelDrop" type="button" class="btn-ai btn-ai-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false" data-bs-toggle=\'dropdown\' aria-haspopup=\'true\' aria-expanded=\'false\'><i class="fa fa-book-open-reader fa-sm"></i> <span class="action-text">'+lang.openai_006+'</span></button>' +
          '                       <div id="reading-level-info" style="display: inline-block; margin: 4px 3px 0 3px;"><i id="spinReadingLevel" class="fa-solid fa-spinner fa-spin-pulse"></i></div>' +
          '                       <div class="clearfix"></div>' +
          '                           <div class="dropdown-menu" aria-labelledby="btnSetReadingLevelDrop">'+
          '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_reading_level\', \'5th\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_007+'</a>'+
          '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_reading_level\', \'6th\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_008+'</a>'+
          '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_reading_level\', \'7th\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_009+'</a>'+
          '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_reading_level\', \'8_9\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_010+'</a>'+
          '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_reading_level\', \'10_12\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_011+'</a>'+
          '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_reading_level\', \'college\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_012+'</a>'+
          '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_reading_level\', \'college_grad\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_013+'</a>'+
          '                               <a class="dropdown-item fs12 " href="javascript:;" onclick="sendToChatGPT(\'set_reading_level\', \'professional\');return false;" style="padding-bottom:2px;color:#8A5502;padding-left: 10px;">'+lang.openai_014+'</a>'+
          '                           </div>' +
          '                   </div>\n' +
        '                   <div style="display: inline-block;"><span style="color:#777;margin: 0 4px 0 14px;"><span style="font-weight: bold;">'+lang.global_46+'</span> '+lang.openai_028+'</span></div>' +
        '                   <div class="btn-ai-group">' +
        '                   <button type="button" onclick="openCustomPromptInput(); return false;" class="btn-ai btn-ai-default"><i class="fa fa-pen-to-square fa-sm"></i> <span class="action-text">'+lang.openai_029+'</span></button></div>' +
        '               </div>' +
        '           </div>' +
        '       <div class="clearfix"></div>' +
        '     <div id="custom-prompt-input" style="display: none;">'+custom_prompt_html+'</div></div><div class="clearfix"></div></div></div>';

    var suggestion_action_html = '<div class="row" style="text-align: right;">\n' +
        '        \t<div class="col-sm-12">\n' +
        '                  <div class="cc_info">\n' +
        '                       <div class="float-start">'+lang.openai_030+'</div>\n' +
        '                   </div>' +
        '                \t<div class="btn-ai-group">\n' +
        '                    \t<button type="button" disabled onclick="commitSuggestion();" class="btn-ai btn-ai-default" id="commit-btn"><i class="fas fa-check fa-sm"></i> <span class="action-text">'+lang.openai_031+'</span></button>\n' +
        '                  </div>\n' +
        '                  <div class="btn-ai-group">\n' +
        '                       <button type="button" disabled onclick="copySuggestionToClipboard();" data-clipboard-target="#suggested-text" class="btn-ai btn-ai-default btn-clipboard" id="copy-btn"><i class="fas fa-copy fa-sm"></i> <span class="action-text">'+lang.asi_017+'</span></button>' +
        '                   </div>\n' +
        '                   <div class="btn-ai-group">\n' +
        '                       <button type="button" disabled onclick="clearSuggestionText();" class="btn-ai btn-ai-default" id="clear-btn"><i class="fas fa-trash"></i> <span class="action-text">'+lang.design_1259+'</span></button>' +
        '                   </div>' +
        '               </div>' +
        '           </div>' +
        '       </div><div class="clearfix"></div>';

    var data_html = '<div id="chatgpt-container" style="'+editorStyle+'"><form id="chatgpt-suggestions"><table width="100%">\n' +
        '                         <tr><td width="48%" class="align-top" style="padding: 5px;">\n' +
        '                                <div class="projhdr" style="font-size:14px;margin-top:0;" id="working-text-label"><i class="fas fa-envelope"></i> '+lang.openai_003+'</div>\n' +
        '                                <div class="x-form-text" contenteditable="true" id="final-text" style="width:100%; border-color: grey;overflow-y:scroll;height:'+containerHeight+'px;">'+orig_content+'</div>\n' +
        '                         </td></tr>' +
        '                         <tr><td>'+ action_html +'</td></tr>' +
        '                         <tr><td><hr style="margin-bottom: 10px; border-top: 2px solid #AAA;"></td></tr>' +
        '                         <tr><td width="48%" class="align-top">' +
        '                             <div class="projhdr" style="font-size: 14px; font-weight: normal;" id="suggested-text-label"><i class="fas fa-lightbulb"></i> '+lang.openai_032+' </div>\n' +
        '                             <div>' + suggestion_action_html +
        '                                   <div class="clear"></div> ' +
        '                                   <div class="x-form-text" id="suggested-text" style="overflow-y:scroll;height:'+containerHeight+'px;"><span style="color: grey;"><i>'+lang.openai_033+'</i></span></div>' +
        '                             </div>\n' +
        '                             </td></tr></table></form>' +
        '                       </div>';
    // Open dialog
    var html =  '<div id="rc-chatgpt-editor-parent">' +
        '                   <div class="mb-1">'+
        '                       <div>'+lang.openai_002+'</div>'+
        '                       <div class="text-right"><a href="javascript:;" class="text-successrc" onclick="simpleDialog(window.lang.openai_069,window.lang.openai_068);"><i class="fa-solid fa-shield-halved mr-1"></i>'+lang.openai_068+'</a></div>'+
        '                       <div id="rc-chatgpt-editor">'+data_html+'</div>'+
        '                   </div>' +
        '               </div>';
    html += '<div id="useCustomPromopDialog" class="simpleDialog" title="How to use Custom Prompts">' +
                '<div class="mb-3 fs13" style="line-height: 1.2;">'+lang.openai_034+'</div>' +
                '<div style="font-size:12px;color:#777;margin:10px 0 0;line-height:1.2;">'+lang.openai_035+'</div>' +
            '</div>';

    initDialog('rc-chatgpt-editor-dialog', html);
    var dlgDiv = $('#rc-chatgpt-editor-dialog');
    dlgDiv.dialog({ bgiframe: true, modal: true, width: dialogWidth, title: '<i class="fa-solid fa-wand-sparkles"></i> '+lang.openai_001, buttons:
        // Cancel
            [{text: window.lang.global_53, click: function() {
                    try{ $(".popover").hide(); dlgDiv.dialog('close'); }catch(e){ }
                }},
                // Finalize Text
                { text: lang.openai_036, click: function() {
                        previewText(lang.mycap_mobile_app_764, lang.global_53, lang.openai_037, $('#final-text').html(), openerId, onUpdateFn);
                    }},
                // Fullscreen/Regular View
                {text: buttonText1, 'id': 'change_screen_mode', click: function() {
                        if (!fullscreen) { // Set dialog to fullscreen
                            var height = round($(window).height()*0.75);
                            var containerHeight = (height - 40);
                            var dialogWidth = round($(window).width()*0.9);
                            var buttonText = window.lang.global_167;
                            fullscreen = true;
                        } else {
                            var height = 450;
                            var containerHeight = 210;
                            var dialogWidth = 850;
                            var buttonText = window.lang.global_168;
                            fullscreen = false;
                        }

                        $("#final-text").height(containerHeight);
                        $("#chatgpt-container").height(height);
                        dlgDiv.dialog({ width: dialogWidth });
                        $("#change_screen_mode").text(buttonText);
                        dlgDiv.parent().find('.ui-dialog-buttonset .ui-button:eq(2)').css({ 'font-size': '13px', 'margin-right': '50px', 'color': '#666'}).prepend(fullscreen ? '<i class="fas fa-compress-arrows-alt"></i> ' : '<i class="fas fa-expand-arrows-alt"></i> ');
                        fitDialog($('#rc-chatgpt-editor-dialog'));
                    }}]
    });

    dlgDiv.parent().find('.ui-dialog-buttonset .ui-button:eq(0)').css({ 'color': '#A00000'}).prepend('<i class="fas fa-times"></i> ');
    dlgDiv.parent().find('.ui-dialog-buttonset .ui-button:eq(1)').addClass('font-weight-bold').css({ 'color': '#008000'}).prepend('<i class="fas fa-check"></i> ');
    dlgDiv.parent().find('.ui-dialog-buttonset .ui-button:eq(2)').css({ 'font-size': '13px', 'margin-right': '50px', 'color': '#666'}).prepend(fullscreen ? '<i class="fas fa-compress-arrows-alt"></i> ' : '<i class="fas fa-expand-arrows-alt"></i> ');
    dlgDiv.bind('dialogclose', function(){
        // Hide any opened reading level info popups upon clicking "Close" button or clicking "x" in title bar
        if ($('.popover').length > 0) {
            $('.popover').hide();
        }
        // Destroy the dialog
        try{ dlgDiv.dialog('destroy'); }catch(e){ }
        dlgDiv.remove();
    });
    fitDialog(dlgDiv);

    initializeEditorText(orig_content);
}

// Open Preview Text popup
function previewText(doBtn,cancelBtn,title,content,openerId, onUpdateFn) {
    simpleDialog(content,title,null,500,null,cancelBtn,'finalizeText("'+openerId+'", '+onUpdateFn+'); ',doBtn);

}

// Finalize text upon clicking "Yes, proceed"
function finalizeText (openerId, onUpdateFn) {
    // Update val attribute
    tinymce.get(openerId).setContent($('#final-text').html());
    try{ $('#rc-chatgpt-editor-dialog').dialog('destroy'); }catch(e){ }
    $('#rc-chatgpt-editor-dialog').remove();
    $('.popover').popover('hide');
    if (typeof onUpdateFn == 'function') {
        onUpdateFn();
    }
}

// Initialize editor text - check if grammar mistakes exist, and fetch reading level of text
function initializeEditorText(orig_content) {
    showProgress(1, 0, lang.openai_038);
    sendToChatGPT('check_grammer', '', orig_content);
    sendToChatGPT('get_reading_level', orig_content);
    showProgress(0,0);
}

// Commit Suggestion once cliked on "Use It!" button
function commitSuggestion() {
    setTimeout(function(){
        $('#rc-chatgpt-editor-dialog').scrollTop(0);
    }, 500);
    var final_content = $("#suggested-text").html();
    $("#final-text").html(final_content);
    // UI effect
    setTimeout(function(){
        if ($("#final-text").css('background-color') == 'rgb(255, 255, 255)') {
            $("#final-text").effect('highlight', { }, 2000);
        }
    },300);
    initializeEditorText(final_content);
    clearSuggestionText();
    showProgress(0,0);
}

// Send request to ChatGPT to get response
function sendToChatGPT(action, param, string) {
    if (action != 'check_grammer' && action != 'get_reading_level') {
        $("#suggested-text").html('<span style="color: grey;"><i class="fa-solid fa-spinner fa-spin-pulse"></i> '+lang.openai_067+'</span>');
    }

    if (typeof param === 'undefined') var param = '';
    if (typeof string === 'undefined') var string = '';

    var desc = "";
    switch (action) {
        case 'fix_grammar':
            desc = lang.openai_039;
            break;
        case 'set_length':
            if (param == 'one_paragraph') {
                desc = lang.openai_040;
            } else if ($.inArray(param, ['25', '50', '75']) !== -1) {
                desc = lang.openai_041 + ' ';
                desc += param + "%...";
            } else if ($.inArray(param, ['25+', '50+', '75+']) !== -1) {
                desc = lang.openai_042 + ' ';
                desc += param.substr(0, 2) + "%...";
            }
            break;
        case 'change_tone':
            desc = lang.openai_043 + ' ';
            if (param == 'formal') {
                desc += lang.openai_024;
            } else if (param == 'friendly') {
                desc += lang.openai_025;
            } else if (param == 'encourage') {
                desc += lang.openai_026;
            } else if (param == 'professional') {
                desc += lang.openai_027;
            }
            desc += ' ' + lang.openai_044;
            break;
        case "set_reading_level":
            desc = lang.openai_045 + ' ';
            if ($.inArray(param, ['5th', '6th', '7th']) !== -1) {
                desc += param + " " + lang.openai_046;
            } else {
                if (param == '8_9') {
                    desc += lang.openai_047;
                } else if (param == '10_12') {
                    desc += lang.openai_048;
                } else if (param == 'college') {
                    desc += lang.openai_049;
                } else if (param == 'college_grad') {
                    desc += lang.openai_050;
                } else if (param == 'professional') {
                    desc += lang.openai_005;
                }
            }
            desc += '...';
            break;
        default:
            break;
    }
    if (desc != '') {
        showProgress(1, 0, desc);
    }

    var formData = new FormData();
    if (string != undefined && $.trim(string) != '') {
        formData.append('content_str', string);
    } else {
        if (action == 'custom_prompt') {
            var orig_content = $('#final-text').html();
            var custom_instr = $("#custom-prompt").val();
            custom_instr = custom_instr.replace("[TEXT]", orig_content);
            if ($.trim(custom_instr) == '')  return false;
            showProgress(1,0,lang.openai_067);
            formData.append('custom_prompt_str', custom_instr);
        } else {
            var orig_text = $("#final-text").html();
            formData.append('content_str', orig_text);
        }
    }

    formData.append('redcap_csrf_token', redcap_csrf_token);
    formData.append('action', action);
    formData.append('param', param);

    $.ajax({
        type: "POST",
        url: app_path_webroot+'AI/text_enhancer.php'+(pid == '' ? '' : '?pid='+pid),
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function(jsonAjax)
        {
            var actionArr = ['fix_grammar', 'set_reading_level', 'set_length', 'change_tone', 'custom_prompt'];

            if(jsonAjax.status == 1) {
                //refresh page to show changes
                if (jsonAjax.errors != undefined) {
                    simpleDialog(jsonAjax.errors, 'AI Service Error');
                } else if (jsonAjax.message != '' && jsonAjax.message != undefined) {
                    if (action == 'check_grammer') {
                        var text = jsonAjax.message;
                        if (text.startsWith("No")) {
                            $("#checkGrammerBtn").attr("disabled", "disabled");
                            $("i#grammar-status").addClass('fa-spell-check').removeClass('fa-exclamation-circle fa-spinner fa-spin-pulse');
                            $("i#grammar-status").css({'color': 'green'});
                        } else {
                            $("#checkGrammerBtn").removeAttr("disabled");
                            $("i#grammar-status").addClass('fa-exclamation-circle').removeClass('fa-check fa-spinner fa-spin-pulse');
                            $("i#grammar-status").css({'color': 'red'});
                        }
                    } else if ($.inArray(action, actionArr) !== -1) {
                        $("#rc-chatgpt-editor-dialog").animate({ scrollTop: $('#rc-chatgpt-editor-dialog').prop("scrollHeight")}, 1000);
                        $("#suggested-text").html(jsonAjax.message);
                        // UI effect
                        setTimeout(function(){
                            if ($("#suggested-text").css('background-color') == 'rgb(255, 255, 255)') {
                                $("#suggested-text").effect('highlight', { }, 2000);
                            }
                        },300);

                        $("#commit-btn, #copy-btn, #clear-btn").removeAttr("disabled");
                        $("#commit-btn, #copy-btn, #clear-btn").css({'color': 'green'});
                        //sendToChatGPT('get_suggestion_reading_level', jsonAjax.message);
                    } else if (action == 'get_reading_level') {
                        $("#open-info-box").remove();
                        $("#spinReadingLevel").remove();
                        var info_html = '<i id="open-info-box" class="fas fa-info-circle text-secondary" data-bs-toggle="popover" data-trigger="hover" data-content="' + escapeHtml(jsonAjax.message) + '" data-title="'+lang.openai_006+'"></i>';
                        $("#reading-level-info").html(info_html);
                    }
                } else {
                    alert(woops);
                }
            }
            showProgress(0,0);
        },
        complete: function (data) {
        }
    });
}

// Open a Custom prompt input area
function openCustomPromptInput() {
    $('#custom-prompt-input').show('fade');
    fitDialog($('#rc-chatgpt-editor-dialog'));
}
// Copy-to-clipboard action
try {
    var clipboard = new Clipboard('.btn-clipboard');
} catch (e) {}

// Copy the Suggestion to the user's clipboard
function copySuggestionToClipboard()
{
    copyTextToClipboard($('#suggested-text').html());
    // Create progress element that says "Copied!" when clicked
    var rndm = Math.random()+"";
    var copyid = 'clip'+rndm.replace('.','');
    var clipSaveHtml = '<div class="clipboardSaveProgress" style="float: right;" id="'+copyid+'">'+lang.docs_1102+'</div>';
    $('#suggested-text-label').append(clipSaveHtml);
    $('#'+copyid).toggle('fade','fast');
    setTimeout(function(){
        $('#'+copyid).toggle('fade','fast',function(){
            $('#'+copyid).remove();
        });
    },2000);
}

// Clear Suggestion area
function clearSuggestionText() {
    $("#suggested-text").html('<span style="color: grey;"><i>'+lang.openai_033+'</i></span>');
    $("#commit-btn, #copy-btn, #clear-btn").attr("disabled", "disabled");
    $("#commit-btn, #copy-btn, #clear-btn").css({'color': ''});
}