var keyword = '';
var org = '';
var serviceName;
var sqId = '';
var frequentlyUsedOrg = '', frequentlyUsedService = '';
var items_per_page;
var total_items;
var currentPage = 1;

$(function(){

	$('#keyword-search-input').on('keypress', function(e) {
		if ( e.keyCode == 13 ) {  // detect the enter key
			e.preventDefault();
			doFieldBankSearch()
		}
	});

	$('#classification-list').on('change', function(e) {
		if ($(this).val() == 'expand-nih-options') {
			// clicked on expand option
			var $select = $(this);
			var $option = $('#classification-list option[value='+$(this).val()+']');
			var replaceText = "";
			if ($option.data('content').includes(lang.design_928) == true) {
				replaceText = $option.data('content').replace(lang.design_928, lang.design_929);
				replaceText = replaceText.replace("plus", "minus");
				$option.data('content',replaceText);
			} else {
				replaceText = $option.data('content').replace(lang.design_929, lang.design_928);
				replaceText = replaceText.replace("minus", "plus");
				$option.data('content',replaceText);
			}
			$select.find('option.optionChild').toggle();
			$select.find('li.optionChild').toggle();
			$select.val(org); // Keep the current value in the button since we're not actually selecting a value
			initFieldBankSelectPicker(true);
			// After reseting the drop-down, re-select things and re-open it
			setTimeout(function(){
				$('#add_fieldbank button.dropdown-toggle').trigger('click');
			},1);
		} else {
			// Click outside of select box so that it will get close
			$("#fieldbank-result-container").trigger("click");
			if (keyword != '') {
				loadQuestionBankResult('classification');
			} else {
				$("#keyword-search-input").focus();
			}
		}
		//$(this).data("id");
	});
});

function loadQuestionBankResult(source, page_num = 1)
{
	if (typeof sqId == 'undefined') sqId = '';
	if($('#classification-list :selected').hasClass('optionGroup')) {
		// web service is selected
		serviceName = $('#classification-list :selected').attr('search-type');
	} else {
		// Classification/category of web service is selected
		serviceName = $('#classification-list :selected').prevAll('.optionGroup').attr('search-type');
	}
	if (serviceName == '' || serviceName == null) serviceName = 'nih';
	keyword = $('#keyword-search-input').val().trim();
	org = $('#classification-list').val();
	if (org == null || org == '') org = 'nih_all';

	if (source != 'pagination') {
		// Remove existing pagination before new search
		$('.pagination').pagination('destroy');
		currentPage = 1;
	}

	var firstTimeCalled = (org == 'nih_all' && serviceName == 'nih' && keyword == '');

	if (source == '') {
		// when called from onload or clear all, set keyword and org to blank
		keyword = '';
	} else if (source == 'clear_all') {
		// when called from onload or clear all, set keyword and org to blank
		keyword = '';
		//org = '';
	} else if (source == 'clear_org') {
		// When clicked clear org icon set org to blank
		org = 'nih_all';
	} else if (source == 'clear_keyword') {
		// When clicked clear keyword icon set keyword to blank
		keyword = '';
	} else if(source == 'classification') {
		if (org == undefined) org = '';
	}

	// Show loading text before displaying response
	if (keyword == '') {
		$('#cde_search_result').html(lang.design_908);
	} else {
		$('#cde_search_result').html('<img src="'+app_path_images+'progress_circle.gif">&nbsp; '+lang.design_920);
	}

	// Call ajax to get API classification and cde list
	$.ajax({
		method: 'POST',
		url: app_path_webroot + 'Design/field_bank_search.php?pid='+pid,
		data: {
				action: 'search',
				keyword: keyword,
				org: org,
				sqId: sqId,
				serviceName: serviceName,
				form_name: getParameterByName('page'),
				page_num: page_num,
				nih_endorsed: (nihEndorsedChecked ? 1 : 0)
		},
		dataType: 'json'
	})
	.done(function (json_data) {
		if (json_data.length < 1) {
			alert(woops);
		} else {
			total_items = json_data.overview.totalNumber;
			items_per_page = json_data.overview.itemsPerPage;
			frequentlyUsedService = json_data.frequentlyUsedService;
			frequentlyUsedOrg = json_data.frequentlyUsedOrg;

			if (total_items > 10000) total_items = 10000;
			if ((total_items > items_per_page) && (source != 'pagination')) {
				// Set pagination variables and function definition
				$('.pagination').pagination({
					items: total_items,
					itemsOnPage: items_per_page,
					cssStyle: 'light-theme',
					currentPage: currentPage,
					onPageClick: function (pageNumber) {
						$('#cde_search_result').html('<img src="'+app_path_images+'progress_circle.gif">&nbsp; '+lang.design_920);
						currentPage = pageNumber;
						loadQuestionBankResult('pagination', pageNumber);
						return false;
					},
					onInit: function () {
						currentPage = 1;
					}
				});
			}

			$('#cde_search_result').html(json_data.result);

			fitDialog($('#add_fieldbank'));

			if (firstTimeCalled && frequentlyUsedService != '') {
				org = frequentlyUsedOrg;
				serviceName = frequentlyUsedService;
			}
			$('#classification-list')
				.html(json_data.classification)
				.val(org);

			initFieldBankSelectPicker();

			if (source == 'pagination') {
				$("html, body").scrollTop($("#add_fieldbank").offset().top);
			}
			// Force user to enter keyword
			if (keyword == '') {
				$("#keyword-search-input").focus();
			}
		}
	})
	.fail(function (json_data) {
	});
}

var nihEndorsedChecked = false;
function initFieldBankSelectPicker(bypassSubCatToggle) {
	if (typeof bypassSubCatToggle == 'undefined') bypassSubCatToggle = false;
	var $select = $('#classification-list');
	$select.selectpicker("destroy");
	// If selected a sub-category, make sure the subcats are viewable
	if (!bypassSubCatToggle) {
		var $select = $('#classification-list');
		if ($select.val().substr(-4) != "_all") {
			$select.find('option.optionChild').show();
			$select.find('li.optionChild').show();
		} else {
			$select.find('option.optionChild').hide();
			$select.find('li.optionChild').hide();
		}
	}
	// Enable boostrap select
	$select.selectpicker({
		liveSearch: false,
		showIcon: true,
		showSubtext: true,
		style: 'btn-defaultrc',
		noneSelectedText : lang.design_921,
		hideDisabled: true
	});
	$('.popover-header button').css('display','none');
	// For NIH CDE Repository, add extra NIH-Endorsed checkbox
	$select.on('show.bs.select', function (e, clickedIndex, isSelected, previousValue) {
		// if ($(this).val() != 'nih_all' || $('.nih_all_class_header_after').length) return;
		if ($('.nih_all_class_header_after').length) return;
		setTimeout(function(){
			$('#nih_all_class_header').after('<span class="nih_all_class_header_after ml-5 pl-5 fs12 boldish text-dangerrc"><input type="checkbox" id="nih_endorsed" style="position:relative;top:2px;" '+(nihEndorsedChecked?'checked':'')+'> Search NIH-Endorsed CDEs <i class="fs14 fa-solid fa-medal"></i></span>');
			$('#nih_endorsed').click(function(e){
				nihEndorsedChecked = $(this).prop('checked');
				e.stopPropagation();
			});
		},10);
	});
}

function doFieldBankSearch()
{
	keyword = $('#keyword-search-input').val();
	loadQuestionBankResult('keyword');
}