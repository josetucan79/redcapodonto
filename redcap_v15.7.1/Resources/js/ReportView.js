// On pageload
$(function(){
	// If viewing a report, then fetch the report
	if ($('#report_parent_div').length) {
		var pagenum = getParameterByName('pagenum') == '' ? '1' : getParameterByName('pagenum');
		if (page != 'surveys/index.php') {
			fetchReportAjax(getParameterByName('report_id'), pagenum, getLiveFilterUrlFromParams());
		} else {
			fetchReportAjax(report_id, pagenum, "&__report="+getParameterByName('__report')+getLiveFilterUrlFromParams());
		}
		// Rework the page a bit for mobile devices
		if (isMobileDevice) {

			/*
			var reportwidth = $('#report_table').width();
			// Resize all images in report description
			$('#this_report_description img').each(function(){
				var width = $(this).width();
				if ($(this).width() > reportwidth) {
					var newheight = round(reportwidth/width*$(this).height());
					$(this).width(reportwidth);
					$(this).height(newheight);
				}
			});
			*/

			// Resize page container to be same width as table
			var bodywidth = $(document).width();
			if ($('#pagecontainer').width() < (bodywidth+60)) {
				$('#pagecontainer').width(bodywidth+60);
			}
		}
	}
});

// Fetch report via ajax
function fetchReportAjax(report_id,pagenum,append_url) {
	// Initialize divs
	$('#report_load_progress').show();
	$('#report_load_progress2').hide();
	$('#report_parent_div').html('');
	$('.FixedHeader_Cloned , #FixedTableHdrsEnable').remove();
	if (pagenum == null) pagenum = '';
	if (append_url == null) append_url = '';
	// Set base URL
	if (page != 'surveys/index.php') {
		var baseUrl = app_path_webroot+'DataExport/report_ajax.php?pid='+pid+getInstrumentsListFromURL();
	} else {
		var baseUrl = dirname(dirname(app_path_webroot))+'/surveys/index.php?';
	}
	// Ajax call
	exportajax = $.post(baseUrl+'&pagenum='+pagenum+append_url, { report_id: report_id }, function(data) {
		if (data == '0' || data == '') {
			$('#report_load_progress').hide();
			simpleDialog(langReportFailed,langError);
			return;
		}
		// Hide/show progress divs
		$('#report_load_progress').hide();
		$('#report_load_progress2').show();
		// Load report into div on page
		setTimeout(function(){
			// Hide "please wait" div
			$('#report_load_progress2, #report_load_progress_pagenum_text').hide();
			// Add report tabel to page
			document.getElementById('report_parent_div').innerHTML = data;
			// Buttonize the report buttons
			$('.report_btn').button();
			// Eval any Smart Charts loaded via AJAX
			$('script[id^="js-rc-smart-chart"]').each(function(){
				eval($(this).html()); // We can eval this because we trust its source
			});
			// Enable fixed table headers (except on mobile devices)
			enableFixedTableHdrs('report_table',true,true,'.report_pagenum_div:first', 0, "", isMobileDevice);
			$('.dataTables-rc-searchfilter-parent').width(200).addClass('float-end').removeClass('mt-1');
			// Adjust page width (public reports only)
			if (page == 'surveys/index.php' && $('#report_table').width() > $(document).width()) {
				$('#pagecontainer').css("max-width",$('#report_table').width()+"px");
				$('.dataTables_scroll').css("max-width","100%");
			}
			// Change width of search div and pagenum div (if exists on page)
			var searchBoxParent = $('.report_pagenum_div').length ? $('.report_pagenum_div') : $('#report_table_filter');
			var center_width_visible = ($(window).width()-($('#west').length ? $('#west').width()-50 : 0));
			var table_width = $('#report_table').width();
			var min_width = min(center_width_visible, table_width);
			var absolute_min_width = 750;
			var page_num_width = max(min_width, absolute_min_width);
			searchBoxParent.width(page_num_width-((center_width_visible > table_width && page_num_width > absolute_min_width) ? 32 : 0));
			if (!$('.report_pagenum_div').length) {
				searchBoxParent.css({'float': 'left', 'margin-left': '0px' });
			}
			searchBoxParent.addClass('d-print-none');
			if (page_num_width <= table_width) {
				$('.report_pagenum_div:eq(0)').css('border-bottom','0');
				$('.report_pagenum_div:eq(1)').css('border-top','0');
				$('.report_pagenum_div').width(center_width_visible-($('#west').length ? 130 : 80));
			}
			// Place "Download Files" button (if visible) in front of search box
			if ($('.download-files-btn').length) {
				searchBoxParent.append($('.download-files-btn').clone().removeClass('hidden').addClass('ms-3').css({'top':'-2px','height':'24px'}));
				$('.download-files-btn:first').remove();
				// If this is a report than contains File Upload fields but has no files for downloading files, then disable the Download Files buttons
				if (!$('.filedownloadbtn').length && (!$('.report_page_select').length || $('.report_page_select option:selected').val() == 'ALL')) {
					$('.download-files-btn').prop('disabled',true).button();
				}
			}
			// Resize page container to be same width as table
			var bodywidth = $(document).width();
			if ($('#pagecontainer').width() < (bodywidth+60)) {
				$('#pagecontainer').width(bodywidth+60);
			}
			// Display cache info on page, if applicable
			justStoredPageInCache = !$('#redcap-cache-time').length;
			if (!justStoredPageInCache) {
				displayPageCacheInfo($('#redcap-cache-time').val());
			}
		},10);
	})
	.fail(function(xhr, textStatus, errorThrown) {
		$('#report_load_progress').hide();
		if (xhr.statusText == 'Internal Server Error') simpleDialog(langReportFailed,langError);
	});
	// Set progress div to appear if report takes more than 0.5s to load
	setTimeout(function(){
		if (exportajax.readyState == 1) {
			$('#report_load_progress').show();
		}
	},500);
}

function loadReportNewPage(pagenum, preventLoadAll) {
	if (typeof preventLoadAll == 'undefined') preventLoadAll = false;
	// Get report_id
	if (typeof report_id == 'undefined') report_id = getParameterByName('report_id');
	// Get live filter URL
	var dynamicFiltersUrl = getLiveFilterUrl();
	// Stats&Charts page or table report page?
	if (getParameterByName('stats_charts') == '1') {
		// STATS (load new page)
		var instrumentRecordSelectUrl = "";
		if ($('#stats-charts-record-event').length) {
			var recevt = $('#stats-charts-record-event').val().split('[__EVTID__]');
			instrumentRecordSelectUrl += '&record='+recevt[1]+'&event_id='+recevt[0];
		}
		if ($('#stats-charts-instrument').length) {
			instrumentRecordSelectUrl += '&page='+$('#stats-charts-instrument').val();
		}
		window.location.href = app_path_webroot+'DataExport/index.php?pid='+pid+'&stats_charts=1&report_id='+report_id+(report_id=='SELECTED' ? '&instruments='+getParameterByName('instruments')+'&events='+getParameterByName('events') : '')+dynamicFiltersUrl+instrumentRecordSelectUrl;
	} else {
		// TABLE REPORT	(reload via AJAX)
		if (preventLoadAll && pagenum == 'ALL') {
			$('.report_page_select').val(1);
			simpleDialog("We're sorry, but it appears that you will not be able to view ALL pages of this report at the same time. The report is simply too large to view all at once. You may only view each page individually. Our apologies for this inconvenience.");
			return;
		}
		// Show page number in progress text
		if (pagenum == '0') {
			// Maintain the ALL pages option if currently showing all pages, else revert to page 1
			pagenum = (getParameterByName('pagenum') == 'ALL') ? 'ALL' : 1;
		} else if (isNumeric(pagenum)) {
			$('#report_load_progress_pagenum_text').show();
			$('#report_load_progress_pagenum').html(pagenum);
		}
		$('#report_parent_div').html('');
		// Change URL for last tab and for browser address bar
		if (page != 'surveys/index.php') {
			var baseUrl = app_path_webroot+'DataExport/index.php?pid='+pid+'&report_id='+report_id+(report_id=='SELECTED' ? '&instruments='+getParameterByName('instruments')+'&events='+getParameterByName('events') : '');
			var newUrl = baseUrl+'&pagenum='+pagenum+dynamicFiltersUrl;
			$('#sub-nav li:last a').attr('href', newUrl);
		} else {
			var baseUrl = dirname(dirname(app_path_webroot))+'/surveys/index.php?__report='+getParameterByName('__report');
			var newUrl = baseUrl+'&pagenum='+pagenum+dynamicFiltersUrl;
			dynamicFiltersUrl += "&__report="+getParameterByName('__report');
		}
		modifyURL(newUrl);
		// Run report
		setTimeout(function(){
			fetchReportAjax(report_id, pagenum, dynamicFiltersUrl);
		}, 50);
	}
}

// Get URL for appending live filters to report AJAX URL (obtain from main page URL params)
function getLiveFilterUrlFromParams() {
	var dynamicFiltersUrl = '';
	var this_dyn_filter;
	if (max_live_filters == null) max_live_filters = 3;
	for (var i=1; i<=max_live_filters; i++) {
		if (getParameterByName('lf'+i) != '') {
			dynamicFiltersUrl += '&lf'+i+'='+getParameterByName('lf'+i);
		}
	}
	return dynamicFiltersUrl;
}

// Reset the live filters on a report and reload the report
function resetLiveFilters() {
	$('select[id^="lf"]').val('');
	loadReportNewPage(0);
}

// Determine if at least one live filter in a report is selected (return boolean)
function liveFiltersSelected() {
	var this_dyn_filter;
	if (max_live_filters == null) max_live_filters = 3;
	for (var i=1; i<=max_live_filters; i++) {
		this_dyn_filter = $('#lf'+i);
		if (this_dyn_filter.length && this_dyn_filter.val() != '') {
			return true;
		}
	}
	return false;
}