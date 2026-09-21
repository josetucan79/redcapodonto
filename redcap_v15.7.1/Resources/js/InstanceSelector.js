// Form Instance Selector and Tables

/**
 * Updates the visibility of the "(Uncollapse all tables)" link
 */
function updateUncollapseAll() {
	const $uncollapseAll = $('#recordhome-uncollapse-all');
	const anyCollapsed = $('.rc-rhp-repeat-insturments-container').find('[data-rc-collapsed="1"]').length > 0
		|| $('button[collapsed="1"]').length > 0;
	$uncollapseAll.toggle(anyCollapsed);
}

/**
 * Toggles the collapse state of a form instance table on the RHP
 * @param {HTMLElement} btn 
 * @param {boolean} toState 
 * @returns 
 */
function setFormInstanceTableCollapsed(btn, toState) {
	toState = toState ? '1' : '0';
	const $container = $(btn).closest('.rc-rhp-repeat-instrument-container');
	const fromState = btn.getAttribute('data-rc-collapsed');
	if (fromState == toState) return null;
	btn.setAttribute('data-rc-collapsed', toState);
	if (toState == '1') {
		// Collapse
		btn.classList.add('btn-primaryrc');
		btn.classList.remove('btn-defaultrc');
		$container.find('.rc-rhp-repeat-instrument-container-body').hide();
	}
	else {
		// Uncollapse
		btn.classList.add('btn-defaultrc');
		btn.classList.remove('btn-primaryrc');
		renderFormInstanceTable($container.get(0));
		$container.find('.rc-rhp-repeat-instrument-container-body').show();
	}
	return ($container.attr('id') ?? '').replace('-container', '');
}

/**
 * Sets up the form instance tables on the RHP
 */
function setupFormInstanceTables() {
	$('.rc-rhp-repeat-insturments-container').on('click', '[data-rc-collapse]', function(e) {
		const collapsed = this.getAttribute('data-rc-collapsed') == '1';
		const targetId = setFormInstanceTableCollapsed(this, !collapsed);
		// Save state via AJAX
		$.post(app_path_webroot+'DataEntry/record_home_collapse_table.php?pid='+pid, { 
			collapse: collapsed ? 0 : 1, 
			object: 'record_home', 
			targetid: targetId
		});
		updateUncollapseAll();
	});
	$('.rc-rhp-repeat-instrument-container:has([data-rc-collapsed="0"]').each(function() { 
		renderFormInstanceTable(this); 
	});
}

/**
 * Renders a form instance table on the RHP
 * @param {HTMLElement} container 
 * @returns 
 */
function renderFormInstanceTable(container) {
	if (!container) return;
	const parts = container.id.split('-');
	const eventId = parts[1];
	const formName = parts[2];
	const currentUrl = new URL(window.location.href);
	const recordId = currentUrl.searchParams.get('id');
	const pid = currentUrl.searchParams.get('pid');
	const recordIdLink = (dde_user != '' && recordId.slice(-3) == '--'+dde_user) ? recordId.slice(0, -3) : recordId;
	showFormInstanceSelector(container, pid, recordId, formName, eventId, recordIdLink);
}

/**
 * Displays a form instance selector (suitable for the context in which it is called)
 * @param {HTMLElement} el A referende to the element that triggered calling this function
 * @param {string|int} pid Project ID
 * @param {string} recordId Record ID
 * @param {string} formName The form whose instances should be shown
 * @param {string} eventId The event ID
 * @param {string} recordIdLink Record ID using in a link (might need to remove appended --1 or --2 if using DDE)
 */
function showFormInstanceSelector(el, pid, recordId, formName, eventId, recordIdLink) {
	const $trigger = 
		el.classList.contains('rc-form-menu-repeating') 
		? $(el) 
		: (
			el.classList.contains('rc-rsd-status-link') 
			|| el.classList.contains('rc-rhp-status-link')
			|| el.classList.contains('rc-form-status-link')
			|| el.classList.contains('rc-rhp-repeat-instrument-container')
			? $(el)
			: $(el).parents('.rc-form-menu-repeating').find('.rc-form-menu-link').last()
		);
	const isRhpTable = el.classList.contains('rc-rhp-repeat-instrument-container');
	const popover = isRhpTable ? false : bootstrap.Popover.getInstance($trigger.get(0));
	const popoverId = 'rc-instance-selector-'+ eventId + '-' + formName + '-' +fnv1aHash(recordId);
	if (popover) {
		const $popover = $('.popover.'+popoverId);
		if ($popover.hasClass('show')) {
			popover.hide();
		}
		else {
			closeAllPopups();
			popover.show();
		}
	}
	else {
		// Close all popovers
		if (!isRhpTable) closeAllPopups();
		// The next line is a hack to limit the number of pages shown in the pagination bar.
		// This will affect all DataTables on the page.
		// The alternative would be to implement a custom pagination drawback.
		$.fn.DataTable.ext.pager.numbers_length = 5;
		// Setup the DataTable in the popover once it exists in the DOM
		$trigger.on('inserted.bs.popover', function(e) {
			if ($(e.target).hasClass('rc-instance-selector-setpagesize-link')) return;
			const response = $trigger.data('response');
			const $popover = isRhpTable ? $trigger : $('.popover.'+popoverId);
			const $locked = $(response.locked);
			const lockedTpl = $popover.find('[data-rc-lang="bottom_117"]').text();
			const $esigned = $(response.esigned);
			const esignedTpl = $popover.find('[data-rc-lang="bottom_118"]').text();
			const columns = [
				{
					title: '#',
					data: 'instance',
					className: 'rc-min-content'
				},
				{
					title: '',
					data: 'status',
					render: function(data, type, row, meta) {
						if (type == 'display') {
							const disabled = row.disabled ? ' rc-form-menu-fdl-disabled' : '';
							const locked = row.locked 
								? $locked.attr('title', interpolateString(lockedTpl, [ row.locked ])).prop('outerHTML')
								: '';
							const esigned = row.esigned 
								? $esigned.attr('title', interpolateString(esignedTpl, [ row.esigned ])).prop('outerHTML')
								: '';
							const linkAction = makeLinkAction(row.instance);
							return '<a class="rc-form-menu-link'+disabled+'" tabindex="-1" '+linkAction+'><div class="rc-instance-selector-status-icon" data-rc-status="'+data+'"><i class="fa-solid fa-check"></i><div class="rc-instance-selector-status-dot"></div></div></a>' + locked + esigned;
						}
						else {
							const locked = row.locked ? interpolateString(lockedTpl, [ row.locked ]) : '';
							const esigned = row.esigned ? interpolateString(esignedTpl, [ row.esigned ]) : '';
							return  (locked + ' ' + esigned).trim();
						}
					},
					className: 'rc-min-content rc-no-padding rc-padding-right-5'
				},
				{ 
					title: getText(response.language.label), 
					data: 'label',
					className: 'rc-full-width',
				}
			];
			const dataTable = $('#'+response.id).DataTable({
				data: response.data.filter(row => response.filters.includes(row.status)),
				columns: columns,
				pageLength: response.pageLength == 'all' ? response.data.length : Number.parseInt(response.pageLength),
				pagingType: 'simple_numbers',
				dom: 'ftpi',
				order: [[0, 'asc']],
				columnDefs: [
					{ type: 'num', targets: 0 },
					{ type: 'string', targets: '_all' },
					{ orderable: false, targets: 1 },
				],
				language: {
					search: '<i class="fa-solid fa-filter text-muted"></i>',
					searchPlaceholder: getText(response.language.searchPlaceholder),
					info: "_START_ - _END_ / _TOTAL_",
					infoFiltered: '',
					infoEmpty: '&ndash; &ndash;',
					zeroRecords: getText(response.language.zeroRecords),
					paginate: {
						first: '<i class="fa-solid fa-angle-double-left"></i>',
						last: '<i class="fa-solid fa-angle-double-right"></i>',
						previous: '<i class="fa-solid fa-angle-left"></i>',
						next: '<i class="fa-solid fa-angle-right"></i>',
					}
				},
				stripeClasses: [],
			});
			// Set up status filters
			const filterCheckedState = { };
			let nFiltersActive = 0;
			'0,1,2,S0,S2'.split(',').forEach(function(status) {
				const active = response.filters.includes(status);
				filterCheckedState[status] = active ? 'checked' : '';
				nFiltersActive += (active ? 1 : 0);
			});
			filterCheckedState['toggle'] = nFiltersActive == 5 ? 'checked' : '';
			const $statusFilters = $('<div class="rc-instance-selector-status-filter"></div>')
				.append('<div class="rc-instance-selector-status-wrapper"><label tabindex="0" class="rc-instance-selector-status-checkbox" data-rc-status="0"><i class="fa-solid fa-check"></i><div class="rc-instance-selector-status-dot"></div><input type="checkbox" '+filterCheckedState['0']+'></label></div>')
				.append('<div class="rc-instance-selector-status-wrapper"><label tabindex="0" class="rc-instance-selector-status-checkbox" data-rc-status="1"><i class="fa-solid fa-check"></i><div class="rc-instance-selector-status-dot"></div><input type="checkbox" '+filterCheckedState['1']+'></label></div>')
				.append('<div class="rc-instance-selector-status-wrapper"><label tabindex="0" class="rc-instance-selector-status-checkbox" data-rc-status="2"><i class="fa-solid fa-check"></i><div class="rc-instance-selector-status-dot"></div><input type="checkbox" '+filterCheckedState['2']+'></label></div>')
				.append('<div class="rc-instance-selector-status-wrapper"><label tabindex="0" class="rc-instance-selector-status-checkbox" data-rc-status="S0"><i class="fa-solid fa-check"></i><div class="rc-instance-selector-status-dot"></div><input type="checkbox" '+filterCheckedState['S0']+'></label></div>')
				.append('<div class="rc-instance-selector-status-wrapper"><label tabindex="0" class="rc-instance-selector-status-checkbox" data-rc-status="S2"><i class="fa-solid fa-check"></i><div class="rc-instance-selector-status-dot"></div><input type="checkbox" '+filterCheckedState['S2']+'></label></div>')
				.append('<div class="rc-instance-selector-status-wrapper"><label tabindex="0" class="rc-instance-selector-status-toggle" data-rc-status="toggle"><i class="fa-solid fa-check"></i><input type="checkbox" '+filterCheckedState['toggle']+'></label></div>');
			const $setPaging = $('<div class="rc-instance-selector-setpaging"></div>');
			$setPaging.append($('<a type="button" class="rc-instance-selector-setpagesize-link"><i class="fa-regular fa-file-lines"></i></a>'));
			$setPaging.find('a').on('click', function(e) {
				showPageSizePopover(this, getText(response.language.setPageSizeTitle), response.pageLengthStateKey);
			});
			$popover.find('.dataTables_filter').prepend($setPaging).append($statusFilters);
			$statusFilters.on('change', function(e) {
				const status = $(e.target).parents('[data-rc-status]').attr('data-rc-status');
				const checked = $(e.target).prop('checked');
				if (status == 'toggle') {
					$($statusFilters).find('.rc-instance-selector-status-checkbox input').prop('checked', checked);
				}
				// Get status values from all checked
				const statuses = [];
				$statusFilters.find('.rc-instance-selector-status-checkbox:has(input:checked)').each(function() {
					statuses.push($(this).attr('data-rc-status'));
				});
				// Store filter (on RHP only)
				if (response.filtersStateKey) {
					$.post(app_path_webroot+'index.php?route=DataEntryController:storeRepeatInstancesFilters&pid='+pid, {
						key: response.filtersStateKey,
						value: statuses.join(',')
					});
				};
				const filtered = response.data.filter(row => statuses.includes(row.status));
				dataTable.clear();
				dataTable.rows.add(filtered);
				dataTable.draw();
			});
			$popover.find('tbody').on('dblclick', function(e) {
				// Double click on a row triggers link
			 	const $a = $(e.target).parentsUntil('tbody').find('a').not('.rc-form-menu-fdl-disabled');
				if ($a.length > 0) {
					const href = $a.attr('href');
					window.location.href = href;
				}
			});
			// Only show info, filters, and paging when there are more than a certain number of instances
			let keypaging = true;
			if (response.data.length <= response.pageLengthMin) {
				$popover.find('.dataTables_paginate, .dataTables_info').hide();
				$popover.find('.dataTables_filter').hide();
				if (!isRhpTable) {
					$popover.find('a.rc-form-menu-link:not([tabindex="-1"], .rc-form-menu-fdl-disabled)').first().get(0)?.focus({ preventScroll: true });
				}
				keypaging = false;
			}
			else if (!isRhpTable) {
				$popover.find('input[type="search"]')[0]?.focus({ preventScroll: true });
			}
			const focusSearch = function() { $popover.find('input[type="search"]').trigger('focus'); };
			// Keyboard handling
			function handleKey(key, target) {
				switch (key) {
					// Dismiss popup
					case 'Escape': $trigger.popover('hide'); break;
					// Set focus to search box
					case 'f': focusSearch(); break;
					// Paging
					case 'PageUp':
					case 'PageDown':
						const op = (key == 'PageDown') ? 'next' : 'previous';
						if (keypaging) {
							dataTable.page(op).draw('page');
							focusSearch();
						}
						break;
					case 'Home':
						dataTable.page('first').draw('page');
						break;
					case 'End':
						dataTable.page('last').draw('page');
						break;
					// Navigate between links
					case 'ArrowDown':
					case 'ArrowUp':
						if(target.tagName == "A") {
							const op = key == 'ArrowDown' ? 'next' : 'prev';
							const $a = $(target).parentsUntil('tbody')
							.last()[op]('tr')
							.find('a:not([tabindex="-1"], .rc-form-menu-fdl-disabled)');
							if ($a.length) {
								$a.trigger('focus');
							}
							else {
								focusSearch();
							}
						}
						else {
							const op = key == 'ArrowDown' ? 'first' : 'last';
							$popover.find('a.rc-form-menu-link:not([tabindex="-1"], .rc-form-menu-fdl-disabled)')[op]().trigger('focus');
						}
						break;
					// Toggle checkboxes
					case ' ':
					case 'Enter':
						if (target.tagName == 'LABEL') {
							$(target).find('input').prop('checked', ! $(target).find('input').prop('checked')).trigger('change');
						}
						break;
				}
			}
			const keyStates = {};
			$popover.on('keyup', function(e) {
				keyStates[e.key] = false;
				handleKey(e.key, e.target);
			});
			$popover.on('keydown', function(e) {
				if ((e.code == 'Space' || e.key == ' ') && e.target.tagName == 'LABEL') {
					e.preventDefault();
				}
				else if (e.key == 'ArrowUp' || e.key == 'ArrowDown' || e.key == 'PageUp' || e.key == 'PageDown') {
					if (keyStates[e.key]) {
						handleKey(e.key, e.target);
					}
					e.preventDefault();
				}
				keyStates[e.key] = true;
			});
			// Close button
			$popover.on('click', '.rc-close-button', function() {
				$trigger.popover('hide');
			});
			// Helper to retrieve translatable text
			function getText(key) {
				return $popover.find('[data-rc-lang="'+key+'"]').text();
			}
			// Helper to construct a link
			function makeLinkAction(instance) {
				if (window.location.href.indexOf('Calendar/calendar_popup.php') > -1) {
					return 'onclick="window.opener.location.href=\''+app_path_webroot+'DataEntry/index.php?pid='+pid+'&page='+formName+'&id='+urlencode(recordIdLink)+'&event_id='+eventId+'&instance='+instance+'\';self.close();" href="javascript:;"';
				}
				else {
					return 'href="'+app_path_webroot+'DataEntry/index.php?pid='+pid+'&page='+formName+'&id='+urlencode(recordIdLink)+'&event_id='+eventId+'&instance='+instance+'"';
				}
			}
		});
		const storedResponse = $trigger.data('response');
		if (!storedResponse || storedResponse.data.length == 0) {
			$.post(app_path_webroot+'index.php?route=DataEntryController:getRepeatInstances&pid='+pid, {
				record: recordId,
				form: formName,
				event_id: eventId,
				isRhpTable: isRhpTable
			}, 
			function(response) {
				$trigger.data('response', response);
				if (isRhpTable) {
					$trigger.find('.rc-d-instance-count').text('(' + response.data.length + ')');
					$trigger.trigger('inserted.bs.popover');
					$trigger.find('.rc-rhp-repeat-instrument-container-body').show();
				}
				else {
					createPopover(response);
				}
			});
		}
		else {
			if (isRhpTable && $trigger.find('.dataTables_wrapper').length > 0) {
				$trigger.find('.rc-rhp-repeat-instrument-container-body').show();
			}
			else {
				$trigger.trigger('inserted.bs.popover');
			}
		}
	}
	function createPopover(response) {
		const config = {
			container: 'body',
			trigger: 'manual',
			html: true,
			sanitize: false,
			customClass: 'rc-instance-selector ' + popoverId,
			content: response.body,
			title: response.title,
			placement: $trigger.hasClass('rc-form-menu-link') ? 'right' : 'bottom',
			rcForm: formName,
			offset: [-14, 10]
		}
		$trigger.popover(config).popover('show');
	}
	function closeAllPopups() {
		$('.popover.rc-instance-selector.show').each(function() {
			const trigger = document.querySelector('[aria-describedby="'+ this.id + '"]');
			if (trigger) {
				const instance = bootstrap.Popover.getInstance(trigger);
				instance.hide();
			}
		});
	}
	function showPageSizePopover(el, title, storeKey) {
		// First, remove any existing popovers and destroy them
		$('.rc-instance-selector-pagesize-popover').each(function() { 
			const instance = bootstrap.Popover.getInstance(this);
			if (instance) {
				// Destroy them
				instance.dispose();
			}
			this.remove();
		});
		// Create a new popover
		const $content = $('<div class="rc-instance-selector-pagesize-popover-content"></div>');
		const response = $trigger.data('response');
		// Add a radio for each page lenght; the currently selected value is in response.pageLength
		for (const key in response.pageLengths) {
			const value = response.pageLengths[key];
			const checked = key == response.pageLength ? 'checked' : '';
			const $radio = $('<input type="radio" name="rc-instance-selector-pagesize-radio" id="rc-instance-selector-pagesize-radio-'+key+'" value="'+key+'" '+checked+' class="me-1"><label class="me-2" for="rc-instance-selector-pagesize-radio-'+key+'">'+value+'<label>');
			$content.append($radio);
		}
		$content.on('click', 'input[name="rc-instance-selector-pagesize-radio"]', function(e) {
			const value = $(e.target).val();
			// Destroy the current popover
			const instance = bootstrap.Popover.getInstance(el);
			if (instance) {
				instance.dispose();
			}
			// Update the data table
			const table = $('#'+response.id).DataTable();
			table.page.len(value == 'all' ? -1 : value).draw();
			// Store the new value, both locally and via ajax
			response.pageLength = value;
			$.post(app_path_webroot+'index.php?route=DataEntryController:storeRepeatInstancesPageLength&pid='+pid, {
				key: storeKey,
				value: value
			});
		});
		const config = {
			container: $(el).parent(),
			trigger: 'manual',
			html: true,
			sanitize: false,
			customClass: 'rc-instance-selector-pagesize-popover',
			content: $content,
			title: title,
			placement: 'right'
		}
		const popover = new bootstrap.Popover(el, config);
		popover.show();
	}
}
