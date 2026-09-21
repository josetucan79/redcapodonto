// Remove __page__ from the address bar, if needed
if (getParameterByName('__page__') != '') {
	modifyURL(removeParameterFromURL(window.location.href, '__page__'));
}

$(function(){

	// Make section headers into toolbar CSS
	$('.header').addClass('toolbar');

	// Add extra border about main survey container for survey acknowledgement, etc.
	if (!$('#questiontable').length) $('#container').css('border','1px solid #ccc');

	// Remove ability to submit form via Enter button on keyboard
	$(':input').keypress(function(e) {
		if ((this.type == 'checkbox' || this.type == 'text' || this.type == 'radio') && e.which == 13) {
			return false;
		}
	});
	// Remove ability to submit form via JS if the submit button is hidden
	if (!$('form#form tr.surveysubmit button:first:visible').length) {
		$('form#form').on('submit', function(e) {
			e.preventDefault();
		});
	}

	// Safari hack for large radios/checkboxes
	var chrome = navigator.userAgent.indexOf('Chrome') > -1;
	var safari = navigator.userAgent.indexOf("Safari") > -1;
	if (chrome && safari) safari = false;
    // If Safari and large text, increase padding of checkbox/radio (should be done with classes - not style)
    if (safari && $('#questiontable input[type="checkbox"]:first, #questiontable input[type="radio"]:first').length) {
        if ($('#questiontable input[type="checkbox"]:first, #questiontable input[type="radio"]:first').css('margin').substring(0,3) == '6px') {
            $('#questiontable input[type="checkbox"], #questiontable input[type="radio"]').css('margin', '8px');
        }
    }

	// Hack to un-truncate long select options: http://stackoverflow.com/a/19734474/1402028
	if (isIOS) $("#questiontable select").append('<optgroup label=""></optgroup>');

	// Add more space below Submit button for mobile devices (so not partially covered at bottom)
	if (isMobileDevice) $('tr.surveysubmit td').css('padding-bottom','40px');

	try{
		// Enable action tags
		enableActionTags();

		// Enable auto-complete for drop-downs (unless Field Embedding is used, and if so, we'll run after doFieldEmbedding())
		if (!$('#questiontable .rc-field-embed').length) enableDropdownAutocomplete();

		// Hide or disable fields if using special annotation
		if (!pageHasEmbeddedFields) triggerActionTags();

		// Set autocomplete for BioPortal ontology search for ALL fields on a page
		initAllWebServiceAutoSuggest();

		// Make sure dropdowns don't get too wide so that they create horizontal scrollbar
		shrinkWideDropDowns();

		// Improve onchance event of text/notes fields to trigger branching logic before leaving the field
		// (so that we don't skip over the next field, which becomes displayed)
		improveBranchingOnchange();
	}catch(e){ }

	// Bubble pop-up for Return Code widget
	if ($('.bubbleInfo').length) {
		$('.bubbleInfo').each(function () {
			var distance = 10;
			var time = 250;
			var hideDelay = 500;
			var hideDelayTimer = null;
			var beingShown = false;
			var shown = false;
			var trigger = $('.trigger', this);
			if (!trigger.length) return;
			var info = $('.popup', this).css('opacity', 0);
			$([trigger.get(0), info.get(0)]).mouseover(function (e) {
				if (hideDelayTimer) clearTimeout(hideDelayTimer);
				if (beingShown || shown) {
					// don't trigger the animation again
					return;
				} else {
					// reset position of info box
					beingShown = true;
					info.css({
						top: 0,
						right: 0,
						width: 300,
						display: 'block'
					}).animate({
						top: '+=' + distance + 'px',
						opacity: 1
					}, time, 'swing', function() {
						beingShown = false;
						shown = true;
					});
				}
				return false;
			}).mouseout(function () {
				if (hideDelayTimer) clearTimeout(hideDelayTimer);
				hideDelayTimer = setTimeout(function () {
					hideDelayTimer = null;
					info.animate({
						top: '-=' + distance + 'px',
						opacity: 0
					}, time, 'swing', function () {
						shown = false;
						info.css('display', 'none');
					});

				}, hideDelay);

				return false;
			});
		});
	}

	// Add ALT text to all images that lack it
	$('img').each(function(){
		if (typeof $(this).attr('alt') == "undefined") $(this).attr('alt', '');
	});

	// Set location/etc of the survey admin controls
	setPositionAdminControls();
});

// Set location/etc of the survey admin controls (auto-fill button, etc.)
function setPositionAdminControls() {
	$('#pagecontent').css('position', 'relative');
	$('#admin-controls-div a').css({
		'color': $('#footer').css('color'), 
	});
	$('#admin-controls-div').css({
		'left': 'calc(100%)'
	}).show();
}

// Display the Survey Login dialog (login form)
// Note: For MultiLanguage on-the-fly translation, the data-rc-lang attribute needed to be sneaked in here!
function displaySurveyLoginDialog() {
	$('#survey_login_dialog').dialog({ bgiframe: true, modal: true, width: (isMobileDevice ? $(window).width() : 670), open:function(){fitDialog(this);},
		close:function(){ window.location.href=window.location.href; },
		title: '<img src="'+app_path_images+'lock_big.png" style="vertical-align:middle;margin-right:2px;"><span style="color:#A86700;font-size:18px;vertical-align:middle;" data-rc-lang="survey_573">'+window.lang.survey_573+'</span>', buttons: [
		{ text: window.lang.config_functions_45, 'data-rc-lang': 'config_functions_45', click: function () {
			// Make sure enough inputs were entered
			var numValuesEntered = 0;
			$('#survey_auth_form input').each(function(){
				var thisval = trim($(this).val());
				if (thisval != '') numValuesEntered++;
			});
			// If not enough values entered, give error message
			if (numValuesEntered < survey_auth_min_fields) {
				simpleDialog(window.lang.survey_588, window.lang.global_01);
				return;
			}
			// Reset flag so that we don't see the "Leave site?" prompt
			dataEntryFormValuesChanged = false;
			// Submit form
			$('#survey_auth_form').submit();
		} }] });
	// If there are no login fields displayed in the dialog, then remove the "Log In" button
	if ($('#survey_auth_form table.form_border tr').length == 0) {
		$('#survey_login_dialog').parent().find('div.ui-dialog-buttonpane').hide();
	}
	// Add extra style to the "Log In" button
	else {
		$('#survey_login_dialog').parent().find('div.ui-dialog-buttonpane button').css({'font-weight':'bold','color':'#444','font-size':'15px'});
	}
}

// Send confirmation message to respondent after they provide their email address
function sendConfirmationEmail(record, s) {
	showProgress(1,100);
	$.post(dirname(dirname(app_path_webroot))+"/surveys/index.php?s="+s+"&__passthru="+encodeURIComponent("Surveys/email_participant_confirmation.php"),{ record: record, email: $('#confirmation_email_address').val() },function(data){
		showProgress(0,0);
		if (data == '0') {
			alert(woops);
		} else {
			simpleDialog(data,null,null,350);
			$('#confirmation_email_sent').show();
			if (window.REDCap && window.REDCap.MultiLanguage) {
				window.REDCap.MultiLanguage.updateUI()
			}
		}
	});
}

// Because the survey logo can lag in loading after the page loads, it can dislocate the text-to-speech
// icons of the title and instructions, so reload those icons when logo loads
function reloadSpeakIconsForLogo() {
	// If not using text-to-speech, then do nothing
	if (typeof texttospeech_js_loaded == 'undefined') return;
	// First, remove icons already loaded
	$('#surveyinstructions img.spkrplay, #surveytitle img.spkrplay').remove();
	// Now re-add the icons
	addSpeakIconsToSurvey(true);
}

// Using button click, add "speak" icon to all viable elements on the survey page
function addSpeakIconsToSurveyViaBtnClick(enable) {
	if (enable == '1') {
		if (typeof texttospeech_js_loaded == 'undefined') {
			$.loadScript(app_path_webroot+'Resources/js/TextToSpeech.js');
		} else {
			addSpeakIconsToSurvey();
		}
		$('#enable_text-to-speech').hide();
		$('#disable_text-to-speech').show();
		setCookie('texttospeech','1',365);
	} else {
		$('#enable_text-to-speech').show();
		$('#disable_text-to-speech').hide();
		setCookie('texttospeech','0',365);
		$('.spkrplay').remove();
	}
}

// Checks survey page's URL for any reserved parameters (prevents conflict when using survey pre-filling)
function checkReservedSurveyParams(haystack) {
	var hu = window.location.search.substring(1);
	var gy = hu.split("&");
	var param, paramVal;
	var listRes = new Array();
	var listcount = 0;
	for (i=0;i<gy.length;i++) {
		ft = gy[i].split("=");
		param = ft[0];
		paramVal = ft[1];
		if (param != "s" && param != "hash" && !(param == "preview" && paramVal == "1")) {
			if (in_array(param, haystack) && trim(param).length !== 0) {
				listRes[listcount] = param;
				listcount++;
			}
		}
	}
	if (listcount>0) {
		msg = "NOTICE: You are attempting to pass parameters in the URL that are reserved. "
			+ "Below are the parameters that you will need to remove from the URL's query string, as they will not be able to pre-fill "
			+ "survey questions because they are reserved. If you do not know what this means, please contact "
			+ "your survey administrator.\n\nReserved parameters:\n - " + listRes.join("\n - ");
		alert(msg);
	};
}

// For emailing survey link for participants that wish to return later
function emailReturning(survey_id,event_id,participant_id,hash,email,page,success_body,success_title) {
	if (email == '') {
		$('#autoEmail').show();
	} else {
		$('#autoEmail').hide();
	}
	$.get(page, { s: hash, survey_id: survey_id, event_id: event_id, participant_id: participant_id, email: email }, function(data) {
		if (data == '0') {
			alert(woops);
		} else if (data == '2') {
			$('#autoEmail').hide();
			$('#provideEmail').show();
		} else if (email != '') {
			simpleDialog(success_body+' '+data,success_title);
		}
	});
}

// Auto-scroll feature for surveys when selecting a value for a drop-down or radio field while on a mobile device
var autoScroll = {
	scrollToNextTr: function(){
		// Skip Matrix Radios
		if ($(this).closest('td').hasClass('choicematrix')) return;
		// Get the current tr
		currentTr = $(this).parentsUntil('tr').parent();
		// Add a slight delay for branching logic to fire and new TRs to be displayed...
		var timeoutId = window.setTimeout(function() {
			if ($(currentTr).find(".rc-field-embed:visible").length == 0) {
				if (nextTr = $(currentTr).nextAll('tr:visible').first()) {
					$("html, body").animate({
						scrollTop: $(nextTr).offset().top - 10
					}, 500);
				}
			}
		},100,currentTr);
	},
	init: function () {
		//Enable radios, but not when HIDDEN
		$('#questiontable tr:not(.\\@HIDDEN, .\\@HIDDEN-SURVEY) input[type="radio"]').on('click',autoScroll.scrollToNextTr);
		// Enable Selects, but not when HIDDEN
		$('#questiontable tr:not(.\\@HIDDEN, .\\@HIDDEN-SURVEY) select').on('change',autoScroll.scrollToNextTr);
	}
};

// If user tries to close the page after modifying any values on the data entry form, then stop and prompt user if they really want to leave page
var survey_btn_hide_submit = 0;
window.onbeforeunload = function() {
	// If form values have changed (and we're not hiding the survey Submit buttons) ...
	if (dataEntryFormValuesChanged && survey_btn_hide_submit == 0) {
		var separator = "#########################################\n";
		// Prompt user with confirmation
		return separator + lang.data_entry_199 + "\n\n" + lang.data_entry_265 + "\n" + separator;
	}
}

