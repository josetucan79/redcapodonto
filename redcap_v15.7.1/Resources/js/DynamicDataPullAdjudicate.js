// Set global variables for adjudication dialog and fetch ajax request
var adjud_dialog, rtws_fetch_ajax;
// Exclude a source value for a given record during the Adjudication process
function excludeValue(md_id, exclude, ob) {
	// exclude = 1 means "exclude" else means "remove exclusion"
	if (exclude == null) exclude = 1;
	// Call web service via ajax
	$.post(app_path_webroot+'DynamicDataPull/exclude.php?pid='+pid, { md_id: md_id, exclude: exclude }, function(data){
		if (data == '0') {
			alert(woops);
		} else {
			// Change the text and onclick attribute for the link
			ob.html(data)
			  .attr('onclick',"excludeValue("+md_id+","+(exclude==1?'0':'1')+",$(this));");
			// Get table row object
			var thisRow = ob.parents('tr:first');
			if (exclude) {
				ob.addClass('darkRedClr');
				// Also deselect the radio button for this field and make radio invisible
				thisRow.find('a.reset:first').css('visibility','hidden').click();
				thisRow.find('.rtws_adjud_radio').css('visibility','hidden');
			} else {
				ob.removeClass('darkRedClr');
				// Display the radio button again
				thisRow.find('a.reset:first').css('visibility','visible');
				thisRow.find('.rtws_adjud_radio').css('visibility','visible');
			}
		}
	});
}
// Show all hidden rows in adjudication table
function showHiddenAdjudRows(hidethem) {
	if (hidethem == null) hidethem = true;
	$('#rtws_adjud_form .hidden').addClass('hidden-orig');
	if (!hidethem) {
		// Hide the link just clicked and re-hide all rows and event headers
		$('#btn_existing_items_show').show();
		$('#btn_existing_items_hide').hide();
		$('#rtws_adjud_form tr.adjud_evt_hdr, #rtws_adjud_form tr.adjud_rptinst_hdr').hide();
		// Re-hide the hidden fields that were displayed
		$('#rtws_adjud_form .hidden-orig').addClass('hidden');
	} else {
		// Hide the link just clicked and show all rows and event headers
		$('#btn_existing_items_show').hide();
		$('#btn_existing_items_hide').show();
		$('#rtws_adjud_form tr.adjud_evt_hdr, #rtws_adjud_form tr.adjud_rptinst_hdr').show();
		// Show hidden fields
		if ($('#rtws_adjud_popup_content input[name="rtws-otherformfields-radio"]:checked').val() == 'allforms') {
			$('#rtws_adjud_form .hidden-orig').removeClass('hidden');
		} else {
			$('#rtws_adjud_form .hidden-orig').not('.rtws-otherformHide').removeClass('hidden');
		}
		// Set position of dialog
		$('#rtws_adjud_popup').dialog('option', 'position', { my: 'center', at: 'center', of: window });
		fitDialog(adjud_dialog);
		// Highlight the once-hidden rows
		$('#rtws_adjud_form tr.hidden-orig').each(function(){
			highlightTableRowOb($(this),1000);
		});
		// Now send ajax call to log that the user is viewing the previously hidden values (only do once per dialog-load)
		if ($('#btn_existing_items_show').attr('log_view') == '1') {
			// Log the data viewed
			logSourceValuesInPopup(true);
			// Set flag on button so we won't have duplicate data view logged
			$('#btn_existing_items_show').attr('log_view', '0');
		}
	}
}
// Confirm with user if they should refetch data and lose all selections made
function confirmRefetchSourceData() {
	// Check if any radios have been selected
	var num_radios_selected = $('#rtws_adjud_popup .rtws_adjud_radio_clicked:checked').length;
	// If any are selected, then have them confirm loss
	if (num_radios_selected > 0 && !confirm("ABANDON UNSAVED SELECTIONS?\n\nIf you fetch the source data again, you will lose all selections made below, which have not been saved. Are you sure you wish to abandon your current selections?")) {
		return false;
	}
	return true;
}
// Trigger serial processing of ALL records on Record Status Dashboard (but don't output html into the dialog)
function triggerAllRecordsRTWS() {
	// Get list of records delimited with line break
	var records = new Array();
	var i = 0;
	$('table#record_status_table [id^=rtws_new_items]').each(function(){
		if ($(this).find('button').length) {
			records[i++] = this.id.substring('rtws_new_items-'.length);
		}
	});
	var recordList = records.join("\n");
	$('table#record_status_table [id^=rtws_new_items] button').parent().addClass('darkgreen').removeClass('data').removeClass('statusdashred').html(recordProgressIcon);
	// Call the new compatibility function to fetch data for each record one at a time
	if (window.triggerRTWSmappedField) {
		window.triggerRTWSmappedField(recordList, false, 0);
	}
}
// Legacy function triggerRTWSmappedField has been removed - functionality moved to new Vue-based modal system
// Use window.REDCap.openAdjudicationModal() or window.triggerRTWSmappedField() for compatibility
// Legacy function openAdjudicationDialog has been removed - functionality moved to new Vue-based modal system
// Use window.REDCap.openAdjudicationModal() or window.openAdjudicationDialog() for compatibility
// Log the source values displayed in the adjudication popup when it initially opens
function logSourceValuesInPopup(getHiddenMdIds) {
	// If popup is not visible, then return
	if (!$('#rtws_adjud_popup').dialog('isOpen')) return;
	// Set var if null
	if (getHiddenMdIds == null || getHiddenMdIds !== true) getHiddenMdIds = false;
	// Get all values/md_ids that are visible
	var md_ids_viewed = '';
	$('#rtws_adjud_popup_content #adjud_table tr').each(function(){
		var row = $(this);
		if (row.hasClass('hidden') == getHiddenMdIds && row.attr('md_id') != null) {
			md_ids_viewed += row.attr('md_id')+',';
		}
	});
	if (md_ids_viewed.length > 0) {
		md_ids_viewed = md_ids_viewed.substring(0,md_ids_viewed.length-1);
		// Log the values displayed to the user
		$.post(app_path_webroot+'DynamicDataPull/data_view_logging.php?pid='+pid, { source_id_value: $('#rtws_adjud_popup #hidden_source_id_value').val(), md_ids: md_ids_viewed }, function(data){
			// Do nothing
		});
	}
}
// Unset radio value for group of radios in adjudication table
function radioResetValAdjud(field,form) {
	$('form[name="'+form+'"] input[name="'+field+'"]').prop('checked',false);
	resetAdjudRadioBgLink(field);
}
// Reset bgcolor for all radios in this group and hide "reset" link
function resetAdjudRadioBgLink(field) {
	// Radio input
	var this_radio = $('#rtws_adjud_popup input[name="'+field+'"]');
	// Uncheck radio
	this_radio.each(function(){
		var thisCell2 = $(this).parents('td:first');
		thisCell2.removeClass('radiogreen');
		thisCell2.children('a.reset').css('visibility', 'hidden');
	});	
	// Get row class
	var this_row_classes = this_radio.parents('tr:first').attr('class').split(' ');
	var this_row_class = null;
	for (var i=0; i<this_row_classes.length; i++) {
		if (this_row_classes[i].indexOf('adjud_tr-') > -1) {
			this_row_class = this_row_classes[i];
		}
	}
	// If selected a value for a checkbox field, make sure all get selected
	var trs = $('#adjud_table tr.chkbx.'+this_row_class);
	if (trs.length && this_row_class != null) {
		trs.find('.rtws_adjud_radio').each(function(){
			$(this).prop('checked', false);
			var thisCell2 = $(this).parents('td:first');
			thisCell2.removeClass('radiogreen');
			thisCell2.children('a.reset').css('visibility', 'hidden');
		});
	}
}
// Perform auto-check of new items from source system and display count at top of data entry form
function autoCheckNewItemsFromSource(record, forceDataFetch) {
	// Set var
	if (forceDataFetch == null) forceDataFetch = false;
	// Set html for fetching status message
	var fetchingDataText = '<img src="'+app_path_images+'progress_circle.gif"> Checking data in source system...';
	setRTWSContextMsgPlaceholder(fetchingDataText);
	// Now check for data in the source system
	triggerRTWSmappedField(record, false, 1, false, forceDataFetch);
}

// Set the div in place in the form's blue/green context msg div, in which we'll put the RTWS fetching data status msg
function setRTWSContextMsgPlaceholder(text) {
	// Record Home Page
	var isEHRpage = (window.location.href.indexOf('/ehr.php') > -1);
	if (isEHRpage || page == 'DataEntry/record_home.php') {
		$('#record_display_name>div:last').append('<div id="RTWS_sourceDataCheck" style="margin-top:2px;color:#666;width:320px;text-align:right;">'+text+'</div>');
	} else {
		// Get class of context msg div
		var contextMsgClass = (record_exists ? 'blue' : 'darkgreen');
		// Add the "checking source system..." text at top of form
		if (!$('div#contextMsg #RTWS_sourceDataCheck').length) {
			// Get contents of context msg div
			var contextMsgDiv = $('div#contextMsg .'+contextMsgClass);
			var contextMsgContents = contextMsgDiv.html();
			// Check if #RTWS_sourceDataCheck is already on page. If not, then add.
			contextMsgDiv.html('<table cellspacing="0" style="width:100%;table-layout:fixed;"><tr><td id="RTWS_contextMsg_original">'+contextMsgContents+'</td>'
							 + '<td id="RTWS_sourceDataCheck" style="color:#666;width:320px;text-align:right;">'+text+'</td></tr></table>');
		} else if (!$('div#contextMsg #RTWS_sourceDataCheck #RTWS_sourceDataCheck_msgBox').length) {
			$('div#contextMsg #RTWS_sourceDataCheck').html(text);
		}
	}
}

// Add db icon as td cell background
function addDDPicon(field) {
	$('#questiontable #'+field+'-tr').children('td:last').css('background','#F3F3F3 url("'+app_path_images+'databases_arrow.png") no-repeat 97% 8px');
}

// In adjudication popup, hide/show fields that exist on a different form/event that the current one
function hideOtherFormFields(hide) {
	if (hide) {
		// Hide them
		$('#adjud_table .rtws-otherform').addClass('rtws-otherformHide');
	} else {
		// Redisplay them
		$('#adjud_table .rtws-otherformHide').removeClass('rtws-otherformHide').children('td').effect('highlight',{},2000);
	}
}