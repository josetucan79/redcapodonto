// Set up pre-check when put focus on validation type in pop-up to catch when user's change date validation format (effects min/max validation values)
var oldValType, newValType;

$(function() {

	if ($('#ontology_auto_suggest').length) {
		// Set autocomplete for BioPortal ontology search for ALL fields on a page
		initAllWebServiceAutoSuggest();
	}

	// Enable auto-complete for drop-downs
	enableDropdownAutocomplete();

	// Set trigger to open Matrix Group Help pop-up
	$('.mtxgrpHelp').on('click', function() {
		simpleDialog(lang.design_304, lang.design_303);
	});

	// Set trigger to open Matrix Rank description popup
	$('.mtxrankDesc').on('click', function() {
		simpleDialog(lang.design_499+'<br><br><b>'+lang.design_500+'<br><br>'+lang.design_501+'</b>',lang.design_496,null,550);
	});

	// Quick-edit field(s) Event Handling
	document.addEventListener('change', REDCapQuickEditFields.changeHandler);
	document.addEventListener('click', REDCapQuickEditFields.clickHandler);
	document.addEventListener('keydown', REDCapQuickEditFields.keydownHandler);
	document.addEventListener('pointerdown', REDCapQuickEditFields.pointerdownHandler);
	document.addEventListener('dragstart', REDCapQuickEditFields.dndDragStartHandler);
	document.addEventListener('dragend', REDCapQuickEditFields.dndDragEndHandler);
	document.addEventListener('dragenter', REDCapQuickEditFields.dndDragEnterHandler);
	document.addEventListener('dragleave', REDCapQuickEditFields.dndDragLeaveHandler);
	document.addEventListener('drop', REDCapQuickEditFields.dndDragDropHandler);
	document.addEventListener('dragover', REDCapQuickEditFields.dndDragOverHandler);
	$(document).on('dialogopen dialogclose', REDCapQuickEditFields.dialogHandler);
	$(document).on('shown.bs.popover', REDCapQuickEditFields.popoverHandler);
	$(document).on('hide.bs.popover', REDCapQuickEditFields.popoverHandler);
	$(document).on('hidden.bs.modal shown.bs.modal', REDCapQuickEditFields.modalHandler);
	$(document).on('select2:select', REDCapQuickEditFields.select2Handler);

	// Set up pre-check when put focus on validation type in pop-up
	preCheckValType();

	// Check if non-numeric MC coded values exist and fix
	$('#element_enum, #element_enum_matrix').blur(function(){
		checkEnumRawVal($(this));
	});

	// Field name unique check + enable auto-variable naming (if set)
	field_name_trigger($('#field_name'), $('#field_label'));

	// Set trigger for matrix group name check for unique name
	$('#grid_name').blur(function(){
		// When using auto-variable naming, converts field label into variable
		$(this).val( filterMatrixGroupName($(this).val()) );
		// Perform matrix group name check for unique name
		checkMatrixGroupName(false);
	});

	// Set click trigger to show more matrix field inputs
	$('#addMoreMatrixFields').click(function(){
		addAnotherRowMatrixPopup(1);
		// Get last pair of input fields in popup
		var field_name_ob = $('#addMatrixPopup .field_name_matrix:last');
		var field_label_ob = $('#addMatrixPopup .field_labelmatrix:last');
		// Remove any values from the row just created (problem for IE7-8 when copying a row with value in input)
		field_name_ob.val('');
		field_label_ob.val('');
		// Remove onfocus and readonly
		field_name_ob.removeAttr('readonly');
		field_name_ob.removeAttr('onfocus');
		// Add unique check trigger to field_name field
		field_name_trigger(field_name_ob, field_label_ob);
		// Enable auto-var naming (if set)
		enableAutoVarSuggest(field_name_ob, field_label_ob);
		// Highlight new row
		field_name_ob.effect('highlight',{ },1000);
		field_label_ob.effect('highlight',{ },1000);
		$('#addMatrixPopup .field_quesnum_matrix:last').effect('highlight',{ },1000);
		// Put focus on new label field
		field_label_ob.focus();
	});

	// When leaving field label box, always go directly to variable name box.
	$('#field_label').blur(function(){
		if (!(status > 0 && $('#sq_id').val().length > 1)) {
			setTimeout(function () { $('#field_name').focus() }, 1);
		}
	});

	// When click to enable auto variable naming
	$('#auto_variable_naming, #auto_variable_naming_matrix').click(function(){
		var auto_variable_naming = ($(this).prop('checked') ? 1 : 0);
		var id = $(this).attr('id');
		if (auto_variable_naming) {
			$('#auto_variable_naming-popup').dialog({ bgiframe: true, modal: true, width: 550,
				buttons: [
					{ text: window.lang.global_53, click: function () {
						$('#auto_variable_naming, #auto_variable_naming_matrix').prop('checked',false);
						$(this).dialog("close");
					} },
					{ text: lang.survey_459, click: function () {
						$('#auto_variable_naming, #auto_variable_naming_matrix').prop('checked',true);
						save_auto_variable_naming(auto_variable_naming,id);
						$(this).dialog("close");
					} }
				]
			});
		} else {
			save_auto_variable_naming(auto_variable_naming,id);
			// Make sure that both checkboxes (in both dialogs) get unchecked
			$('#auto_variable_naming, #auto_variable_naming_matrix').prop('checked',false);
		}
	});


	// For survey projects, display message that primary key field will not be displayed on survey page
	showPkNoDisplayMsg();
	// Add note that the record_id field cannot be moved
	addRecordIdFieldNote();

	// Select drag-n-drop logic builder
	initDragDropBuilderDlg();

	// Quick actions
	initFieldActionTooltips();
	addDeactivatedActionTagsWarning();
	REDCapQuickEditFields.setFieldTypes();
	setSurveyQuestionNumbers();
	updateOnlineDesignerSectionNav();
	REDCapQuickEditFields.setPreferredLocation($('input[name="qef-preferred-location"]:checked').val() ?? 'right');

	// Activate a field?
	const gotoField = getParameterByName('goto');
	if (gotoField) {
		modifyURL(removeParameterFromURL(window.location.href, 'goto'));
		REDCapQuickEditFields.toggle(gotoField);
	}
});

/**
 * Creates an element (off screen) that serves as the drag image
 * @param {string} id 
 * @param {'field'|'matrix'} kind 
 */
function createDragImage(id, kind) {
	// Create a container div for the custom content
	const text = interpolateString({field: lang.design_1325, sh: lang.design_1328, matrix: lang.design_1326}[kind], [id]);
	const $container = $('<div class="qef-drag-container">'+onlineDesigner_moveIcon+'<span class="ms-1">'+text+'</span></div>').appendTo('body');
	return $container.get(0);
}

/**
 * Set survey question numbers
 */
function setSurveyQuestionNumbers(field) {
	const selector = (typeof field == 'string' && field != '')
		? '[data-field-action="surveycustomquestionnumber"][data-field-name="'+field+'"]'
		: '[data-field-action="surveycustomquestionnumber"][data-field-name]';
	$(selector).each(function() {
		const field = this.getAttribute('data-field-name');
		const fi = REDCapQuickEditFields.getFieldInfo(field);
		if (fi) {
			if (fi.questionNum == null || fi.questionNum == '') {
				this.innerHTML = '&nbsp;';
			}
			else {
				const div = document.createElement('div');
				div.innerHTML = fi.questionNum;
				this.textContent = div.textContent;
			}
		}
	});
}

function addRecordIdFieldNote() {
	const $table_pk = $('#'+padDesign(table_pk));
	if ($table_pk.length == 0 || $('#table_pk_note').length > 0) return;
	// Place note below record ID field in Online Designer
	$table_pk.after('<div class="frmedit" id="table_pk_note" style="padding:5px 0 10px 5px;margin-bottom:10px;background-color:#DDDDDD;font-size:11px;color:#888;">' + lang.design_1127 + '</div>');
}

$.fn.isInViewport = function() {
    const elementTop = $(this).offset().top;
    const elementBottom = elementTop + $(this).outerHeight();

    const viewportTop = $(window).scrollTop();
    const viewportBottom = viewportTop + $(window).height();

    return elementBottom > viewportTop && elementTop < viewportBottom;
};

const REDCapQuickEditFields = {
	//#region Variables
	_selected: [],
	_current: '',
	_lastCustomBranchingLogic: '',
	_lastCustomActionTags: '',
	_lastCustomChoices: [],
	_autoExpand: false,
	_fieldTypes: null,
	_fieldOrderMap: new Map(),
	_orderFieldMap: new Map(),
	_actionTagsExplainClosedListener: null,
	_preferredLocation: 'right',
	_gotoFieldActive: false,
	//#endregion
	//#region Global Event Handlers
	/**
	 * Handles change events
	 * @param {Event} e 
	 */
	changeHandler: function(e) {
		if (e.target.tagName == 'INPUT') {
			// Update Quick-edit field(s) location preferrence
			if (e.target.name == 'qef-preferred-location') {
				const val = $(e.target).val();
				if (['right', 'top'].includes(val)) {
					REDCapQuickEditFields.setPreferredLocation(val);
					$.post(app_path_webroot+"Design/quick_update_fields.php?pid="+pid, {
						uiState: 1,
						'qef-preferred-location': val
					});
				}
			}
		}
	},
	/**
	 * Handles click events
	 * @param {Event} e 
	 */
	clickHandler: function(e) {
		if (e.target.tagName == 'INPUT') {
			if (e.target.classList.contains('qef-select-checkbox')) {
				const id = e.target.id.replace('mfsckb-', '');
				REDCapQuickEditFields.toggle(id, e.target.checked);
				return;
			}
		}
		if (e.target.tagName == 'BUTTON' || e.target.closest('BUTTON')) {
			const el = e.target.tagName == 'BUTTON' ? e.target : e.target.closest('BUTTON');
			if (el.dataset.multiFieldAction) {
				REDCapQuickEditFields._performAction(el.dataset.multiFieldAction);
				return;
			}
		}
		if (e.target.tagName == 'A' || e.target.closest('A')) {
			const el = e.target.tagName == 'A' ? e.target : e.target.closest('A');
			if (el.dataset.multiFieldAction) {
				REDCapQuickEditFields._performAction(el.dataset.multiFieldAction);
				return;
			}
		}
	},
	/**
	 * Handles mousedown events such as selecting/unselecting fields
	 * (mousedown is used in favor of click to prevent text highlights when using shift+click)
	 * @param {Event} e 
	 */
	pointerdownHandler: function(e) {
		// Ignore any A, INPUT, TEXTAREA, SELECT, BUTTON elements (or any element nested within them)
		if (e.target.tagName == 'A'
			|| e.target.tagName == 'INPUT'
			|| e.target.tagName == 'TEXTAREA'
			|| e.target.tagName == 'SELECT'
			|| e.target.tagName == 'BUTTON'
			|| e.target.closest('A, INPUT, TEXTAREA, SELECT, BUTTON')
		) return;
		// Do nothing when a modal is open?
		if ($('.modal.show, .ui-widget-overlay').length > 0) return;
		// Check if the target element is nested within a TABLE.frmedit_tbl element
		const id = e.target.closest('table.frmedit_tbl')?.id ?? '';
		if (e.ctrlKey || e.metaKey) {
			e.preventDefault();
			e.stopImmediatePropagation();
			REDCapQuickEditFields.toggle(id, null, true, e.shiftKey);
		}
		else if (!e.altKey && !e.shiftKey && !e.ctrlKey && !e.metaKey && REDCapQuickEditFields.count() > 0) {
			e.preventDefault();
			REDCapQuickEditFields.setActive(id);
		}
	},
	/**
	 * Handles certain keypresses in some situations
	 * @param {KeyboardEvent} e 
	 */
	keydownHandler: function(e) {
		// Exit if a dialog is currently open or something else has already handled the event
		if (e.defaultPrevented || $('.ui-widget-overlay').length > 0) return;
		const QEF = REDCapQuickEditFields;
		// Goto field widget
		if ((e.ctrlKey || e.metaKey) && e.code == 'KeyG') {
			QEF._performAction('goto-show');
			e.preventDefault();
			return;
		}
		// Exit here if the QEF widget is not shown
		if (REDCapQuickEditFields._current == '') return;
		// Keyboard shortcuts when the QEF widget is shown
		if (e.code == 'ArrowDown') {
			if (e.ctrlKey || e.metaKey) {
				QEF._performAction('expand-down');
			}
			else {
				QEF._performAction('navigate-next');
			}
			e.preventDefault();
		}
		else if (e.code == 'ArrowUp') {
			if (e.ctrlKey || e.metaKey) {
				QEF._performAction('expand-up');
			}
			else {
				QEF._performAction('navigate-prev');
			}
			e.preventDefault();
		}
		else if (e.code == 'Escape') {
			QEF._performAction('clear');
			e.preventDefault();
		}
		else if (e.code == 'Backspace') {
			QEF.unselect(QEF._current);
			e.preventDefault();
		}
		else if (e.code == 'Space') {
			QEF._performAction('expand-toggle');
			e.preventDefault();
		}
		else {
			// console.log(e.code, e.shiftKey ? 'shift' : '', e.altKey ? 'alt' : '', e.ctrlKey ? 'ctrl' : '');
		}
	},
	/**
	 * Modify certain dialogs when related to Quick-edit field(s) actions
	 * @param {JQuery.Event} e 
	 */
	dialogHandler: function(e) {
		if (e.type == 'dialogopen') {
			const $dlg = $(e.target).parents('[role="dialog"]');
			if ($dlg.find('.qef-apply').length) {
				// Disable 'Apply' button and set an id for it and the 'Cancel' button
				$dlg.find('.ui-dialog-buttonpane button.ok-button').attr('id', 'qef-dlg-apply').addClass('ui-state-disabled');
				$dlg.find('.ui-dialog-buttonpane button.close-button').attr('id', 'qef-dlg-cancel');
			}
		}
		else if (e.type == 'dialogclose') {
			// Hook into action tags explain popup's closing
			if (e.target.id == 'action_tag_explain_popup') {
				if (typeof REDCapQuickEditFields._actionTagsExplainClosedListener == 'function') {
					REDCapQuickEditFields._actionTagsExplainClosedListener();
				}
			}
		}
	},
	/**
	 * React to some popovers being shown
	 * @param {JQuery.Event} e 
	 */
	popoverHandler: function(e) {
		const popover = bootstrap.Popover.getInstance(e.target);
		if (e.type == 'shown') {
			// Focus the text input when a question number editor is shown
			const $input = $(popover.tip).find('input[data-qeqn-content="question-num"]');
			if ($input.length) {
				$input.get(0).select();
			}
		}
		else if (e.type == 'hide' && popover.tip.classList.contains('qef-actions-popover')) {
			e.preventDefault();
		}
	},
	/**
	 * React to some modals being closed
	 * @param {JQuery.Event} e 
	 */
	modalHandler: function(e) {
		const modal = bootstrap.Modal.getInstance(e.target);
		// Goto field modal
		if (modal._element.id == 'qef-goto-field-modal') {
			if (e.type == 'shown') {
				$('#qef-goto-fields').select2('open');
			}
			else if (e.type == 'hidden') {
				REDCapQuickEditFields._gotoFieldActive = false;
			}
		}
	},
	select2Handler: function(e) {
		if (e.target.id == 'qef-goto-fields') {
			REDCapQuickEditFields._performAction('goto-set', e.params.data.id, e.params.data.element.dataset.form);
		}
	},
	//#region Drag and Drop
	/**
	 * Handles drag and drop operations - START
	 * @param {DragEvent} e 
	 */
	dndDragStartHandler: function(e) {
		const QEF = REDCapQuickEditFields;
		if (e.target.tagName == 'A') {
			const kind = e.target.getAttribute('draggable') ?? '';
			// Start to drag a field (regular or matrix field) or a section header
			if (kind == 'field' || kind == 'sh') { 
				const fieldTable = e.target.closest('table.frmedit_tbl');
				const id = fieldTable.id;
				const fi = QEF.getFieldInfo(id);
				const rect = fieldTable.getBoundingClientRect();
				fieldTable.classList.add('qef-drag-source');
				QEF._dragging = {
					type: kind,
					source: id,
					dragImg: createDragImage(fi.name, id.endsWith('-sh') ? 'sh' : 'field'),
					srcRect: {
						left: rect.left,
						right: rect.right,
						top: rect.top,
						bottom: rect.bottom
					},
				};
				// Set drag image
				e.dataTransfer.setDragImage(QEF._dragging.dragImg, -20, 0);
			}
			// Start to drag an entire matrix group of fields
			else if (kind == 'matrix') {
				const matrixRow = e.target.closest('div.design-matrix-icons');
				$('[data-matrix-group-name="' + matrixRow.dataset.matrixGroupName + '"]').addClass('qef-drag-source');
				const rect = matrixRow.getBoundingClientRect();
				QEF._dragging = {
					type: kind,
					source: matrixRow.dataset.matrixGroupName,
					dragImg: createDragImage(matrixRow.dataset.matrixGroupName, 'matrix'),
					srcRect: {
						left: rect.left,
						right: rect.right,
						top: rect.top,
						bottom: rect.bottom,
						height: rect.height
					}
				};
				// Set drag image
				e.dataTransfer.setDragImage(QEF._dragging.dragImg, -20, 0);
			}
		}
	},
	/**
	 * Handles drag and drop operations - ENTER
	 * @param {DragEvent} e 
	 */
	dndDragEnterHandler: function(e) {
		const D = REDCapQuickEditFields._dragging;
		if (!D.srcRect) return;
		if (D.tRect) return;
		if (e.clientX < D.srcRect.left || e.clientX > D.srcRect.right) return;
		if (e.clientY > D.srcRect.top && e.clientY < D.srcRect.bottom) return;
		const QEF = REDCapQuickEditFields;

		// console.log('ENTER');

		//
		//#region Basics and Setup
		//
		let allowBefore = true;
		let allowAfter = true;
		// Gather target info
		let curEl = e.target.classList.contains('frmedit_tbl') ? e.target : e.target.closest('table.frmedit_tbl');
		let targetId = curEl?.id ?? '';
		if (!curEl) {
			curEl = e.target.classList.contains('design-matrix-icons') ? e.target : e.target.closest('div.design-matrix-icons');
			if (curEl) {
				const mg = curEl.getAttribute('data-matrix-group-name');
				// Set target id to first field in matrix
				targetId = QEF._matrixGroups[mg].fields[0];
			}
		}
		const targetEl = curEl;
		// Outside potentiall droppable area
		if (targetId == '') { // No target
			D.target = '';
			return;
		}
		// Get info about some fields
		const sourceFI = D.type == 'matrix' 
			? QEF.getFieldInfo(QEF._matrixGroups[D.source].fields[0]) // first field in matrix
			: QEF.getFieldInfo(D.source);
		const sourceNextFI = D.type == 'matrix' 
			? QEF.getNextFieldInfo(QEF._matrixGroups[D.source].fields[QEF._matrixGroups[D.source].fields.length - 1]) // last field in matrix
			:  QEF.getNextFieldInfo(D.source);
		const targetFI = QEF.getFieldInfo(targetId);
		const targetNextFI = QEF.getNextFieldInfo(targetId);
		//#endregion
		//
		//#region Dragging a regular FIELD
		//
		if (D.type == 'field' && !sourceFI.isMatrixField) {
			// Check if the element is a potential drop target
			if (targetId == D.source
				|| (targetId.endsWith('-sh') && targetFI.isMatrixField) // Matrix SH is NEVER a drop target
				|| (D.type == 'sh' && targetId + '-sh' == D.source)
				|| targetId == padDesign(table_pk)
			) {
				D.target = '';
				return;
			}
			// Special handling of matrix field targets: 
			// Allow before matrix header or after last field in matrix
			if (targetFI.isMatrixField) {
				allowAfter = false;
				allowBefore = false;
				const matrixLast = trimDesign(QEF.getLastMatrixField(targetFI.matrixGroup));
				const matrixFirst = trimDesign(QEF.getFirstMatrixField(targetFI.matrixGroup));
				const matrixLastNextFI = QEF.getNextFieldInfo(matrixLast);
				// Allow to drop on the LAST matrix field if the source is not its next field
				if (targetFI.name == matrixLast && matrixLastNextFI.name != sourceFI.name) {
					// Make sure it is not the matrix icon div
					allowAfter = !(targetEl?.classList.contains('design-matrix-icons') ?? false);
				}
				// Allow to drop on the FIRST matrix field if the source is not its previous field
				if (targetFI.name == matrixFirst && sourceNextFI.name != matrixFirst) {
					// Make sure it is the matrix icon div (and not the first field's table)
					allowBefore = targetEl?.classList.contains('design-matrix-icons') ?? false;
				}
			}
			// Set insertion point: Before or after the target field (depending on coordinates)
			// Only when target is not a matrix field
			else if (!targetFI.isMatrixField) {
				// Allow before/after?
				if (targetId == padDesignSH(sourceFI.name)) {
					allowAfter = false;
				}
				else if (targetNextFI.name == sourceFI.name && !sourceFI.hasSectionHeader && !targetId.endsWith('-sh')) {
					allowAfter = false;
				}
				else if (sourceNextFI.name == targetFI.name) {
					if (sourceNextFI.hasSectionHeader) {
						if (targetId.endsWith('-sh')) {
							allowBefore = false;
						}
					}
					else {
						allowBefore = false;
					}
				}
			}
			if (!allowBefore && !allowAfter) return;
		}
		//#endregion
		//
		//#region Dragging a matrix FIELD
		//
		if (D.type == 'field' && sourceFI.isMatrixField) {
			// Check if the element is a potential drop target 
			// It must not be itself, but must be a matrix field of the same matrix group
			if (targetId == D.source
				|| targetEl.dataset.matrixGroupName != sourceFI.matrixGroup
				|| targetId.endsWith('-sh') // Matrix SH is NEVER a drop target
				|| !targetEl.classList.contains('frmedit_tbl') // Must be in a table
			) {
				D.target = '';
				return;
			}
			// Set allowed insertion points
			if (targetFI.order < sourceFI.order) {
				allowAfter = false;
			}
			else {
				allowBefore = false;
			}
		}
		//#endregion
		//
		//#region Dragging a MATRIX
		//
		if (D.type == 'matrix') {
			// Check if the element is a potential drop target
			if (targetId == D.source
				|| targetEl.dataset.matrixGroupName == sourceFI.matrixGroup  // Same matrix is NEVER a drop target
				|| targetId == padDesign(table_pk)
			) {
				D.target = '';
				return;
			}
			// Special handling of matrix field targets: 
			if (targetFI.isMatrixField) {
				allowBefore = false;
				allowAfter = false;
				const matrixLast = trimDesign(QEF.getLastMatrixField(targetFI.matrixGroup));
				const matrixFirst = trimDesign(QEF.getFirstMatrixField(targetFI.matrixGroup));
				const matrixLastNextFI = QEF.getNextFieldInfo(matrixLast);
				// Allow to drop on the LAST matrix field if the source is not its next field
				if (targetFI.name == matrixLast && matrixLastNextFI.name != sourceFI.name) {
					// Make sure it is not the matrix icon div
					allowAfter = !(targetEl?.classList.contains('design-matrix-icons') ?? false);
				}
				// Allow to drop on the FIRST matrix field if the source is not its previous field
				if (targetFI.name == matrixFirst && sourceNextFI.name != matrixFirst) {
					// Make sure it is the matrix icon div (and not the first field's table)
					allowBefore = targetEl?.classList.contains('design-matrix-icons') ?? false;
				}
			}
			// Fields and Section Headers
			else if (!targetFI.isMatrixField) {
				if (targetId.endsWith('-sh')) {
					// Target is a section header
					// When it is immediately after the matrix, it cannot be a target at all
					if (targetFI.name == sourceNextFI.name) return;
					// Insertion can only be at the top
					allowBefore = true;
					allowAfter = false;
				}
				else {
					// Target is not a section header
					const matrixLast = QEF.getLastMatrixField(D.source);
					const matrixLastNextFI = QEF.getNextFieldInfo(matrixLast);
					if (matrixLastNextFI.name == targetFI.name) {
						allowBefore = false;
					}
					// When it is immediately before the matrix, it cannot be a target at all
					else if (targetNextFI.name == sourceFI.name) {
						allowAfter = false;
						allowBefore = true;
					}
					// When it has a section header, insertion can only be at the bottom
					if (targetFI.hasSectionHeader) {
						allowBefore = false;
					}
				}
			}
			if (!allowBefore && !allowAfter) return;
		}
		//#endregion
		//
		//#region Dragging a SECTION HEADER
		//
		if (D.type == 'sh') {
			// Exclude some targets
			if (targetId == D.source // Not on self
				|| targetFI.isMatrixField // Never on any element of a matrix
				|| targetId == padDesign(table_pk)
			) {
				D.target = '';
				return;
			}
			// Set allowed insertion points
			if (targetFI.name == sourceFI.name) {
				// Section header's field - cannot be at the top
				allowBefore = false;
			}
			// Never at the bottom of the field immediately before the section header
			if (targetNextFI.name == sourceFI.name) {
				allowAfter = false;
			}
			// Never at the bottom of the form
			if (targetNextFI.isFormStatus) {
				allowAfter = false;
			}
			if (!allowBefore && !allowAfter) return;
		}
		//#endregion
		//
		//#region Allow DROP
		//
		e.dataTransfer.dropEffect = 'move';
		D.target = targetId;
		D.targetEl = targetEl;
		// Set target rect and decide the insertion mode
		const rect = targetEl.getBoundingClientRect();
		D.tRect = {
			left: rect.left,
			right: rect.right,
			top: rect.top,
			bottom: rect.bottom,
			height: rect.height
		}
		D.insert = e.clientY - rect.top < rect.height / 2 ? 'before' : 'after';
		// Fix mode based on allowance and set allowance
		if (D.insert == 'before' && !allowBefore) D.insert = 'after';
		else if (D.insert == 'after' && !allowAfter) D.insert = 'before';
		D.allowBefore = allowBefore;
		D.allowAfter = allowAfter;
		// Finally, add highlight classes to the target
		if (D.insert == 'before') {
			targetEl.classList.add('qef-drag-target-before');
			targetEl.classList.remove('qef-drag-target-after');
		}
		if (D.insert == 'after') {
			targetEl.classList.add('qef-drag-target-after');
			targetEl.classList.remove('qef-drag-target-before');
		}
		e.preventDefault();
		//#endregion
		// console.log('ENTERED', D);
	},
	/**
	 * Handles drag and drop operations - LEAVE
	 * @param {DragEvent} e 
	 */
	dndDragLeaveHandler: function(e) {
		const D = REDCapQuickEditFields._dragging;
		if (!D.srcRect) return;
		if (D.tRect && e.clientX > D.tRect.left && e.clientX < D.tRect.right && e.clientY > D.tRect.top && e.clientY < D.tRect.bottom) return;
		// console.log('LEAVE');
		if (D.targetEl) {
			D.targetEl.classList.remove('qef-drag-target-before', 'qef-drag-target-after');
			D.targetEl = null;
			D.tRect = null;
		}
	},
	/**
	 * Handles drag and drop operations - END
	 * @param {DragEvent} e 
	 */
	dndDragEndHandler: function(e) {
		const D = REDCapQuickEditFields._dragging;
		if (D.type) {
			// Remove drag image
			D.dragImg.remove();
			// Remove drag stylings
			if (D.type == 'field' || D.type == 'sh') {
				$('table.frmedit_tbl#'+D.source).removeClass('qef-drag-source');
			}
			else if (D.type == 'matrix') {
				$('[data-matrix-group-name="' + D.source + '"]').removeClass('qef-drag-source');
			}
			$('.qef-drag-target-before, .qef-drag-target-after')
				.removeClass('qef-drag-target-before qef-drag-target-after');

			REDCapQuickEditFields._dragging = {};
		}
	},
	/**
	 * Handles drag and drop operations - DROP
	 * @param {DragEvent} e 
	 */
	dndDragDropHandler: function(e) {
		const QEF = REDCapQuickEditFields;
		const D = QEF._dragging;
		if (Object.keys(D).length == 0) return; // Do nothing if drag has not been initiated
		showProgress(1);
		const form_name = getParameterByName('page');
		const from = [];
		const from_fi = D.type == 'matrix' 
			? QEF.getFieldInfo(QEF.getFirstMatrixField(D.source)) 
			: QEF.getFieldInfo(D.source);
		if (from_fi.isMatrixField && D.type == 'matrix') {
			if (from_fi.hasSectionHeader) {
				from.push(padSH(from_fi.name));
			}
			from.push(...QEF._matrixGroups[from_fi.matrixGroup].fields.map(trimDesign));
		}
		else {
			if (D.type == 'sh') {
				from.push(padSH(from_fi.name));
			}
			else {
				from.push(from_fi.name);
			}
		}
		let to = trimDesign(D.target);
		if (D.type == 'field' && D.insert == 'before'
			&& QEF.getFieldInfo(to).isMatrixField 
			&& QEF.getFieldInfo(to).hasSectionHeader 
			&& !from_fi.isMatrixField
		) {
			to = padSH(to);
		}
		const section_header = D.type == 'sh' ? from_fi.name : '';
		// Current field order with section headers
		const currentFields = [];
		for (const f in QEF._fieldTypes) {
			const fi = QEF.getFieldInfo(f);
			if (fi.isFormStatus) continue;
			if (fi.hasSectionHeader) currentFields.push(padSH(fi.name));
			currentFields.push(fi.name);
		}
		// Assemble new field order
		const updatedFields = [];
		for (const f of currentFields) {
			if (from.includes(f)) continue;
			if (f == to) {
				if (D.insert == 'before') updatedFields.push(...from, f);
				else updatedFields.push(f, ...from);
			}
			else updatedFields.push(f);
		}
		const field_names = updatedFields.join(',');
		let from_name = from[0];
		let reloadTable = false;
		if (
			// If field moved TO directly beneath section header or if section header was moved, then set flag to reload whole table (to fix SH discrepancies)
			(section_header != '' || field_names.indexOf('-sh,'+from+',') > -1)
			// If field moved FROM directly beneath section header , then set flag to reload whole table (to fix SH discrepancies)
			|| (section_header == '' && field_names.indexOf(from+'-sh,') > -1 && field_names.indexOf(from+'-sh,'+from+',') == -1)
			// If a matrix was dragged or a field was dragged next to a matrix
			|| D.type == 'matrix'
			|| QEF.getFieldInfo(to).isMatrixField
		) {
			reloadTable = true;
		}
		const params = {
			field_name: from_name,
			form_name: form_name, 
			section_header: section_header, 
			field_names: field_names 
		}

		// console.log('DROP:', D, params);

		$.post(app_path_webroot+'Design/update_field_order.php?pid='+pid, params, function(data) {
			if (typeof data != 'object') {
				reloadTable = true;
				showToast(lang.global_01, lang.design_1339, 'error');
			}
			else {
				if (data.message) {
					showToast(data.message.title, data.message.text, data.message.type, 5000);
					reloadTable = true;
				}
				if (!reloadTable) {
					QEF.updateFieldOrder(data.fieldTypes, D);
					showProgress(0,0);
				}
			}
			if (reloadTable) {
				reloadDesignTable(form_name, null, false);
			}
		});
	},
	/**
	 * Handles drag and drop operations - DRAG OVER
	 * @param {DragEvent} e 
	 */
	dndDragOverHandler: function(e) {
		const D = REDCapQuickEditFields._dragging;
		if (!D.srcRect) return;
		if (e.clientX < D.srcRect.left || e.clientX > D.srcRect.right) return;
		// Over the current target?
		if (D.tRect && (e.clientY > D.tRect.top && e.clientY < D.tRect.bottom)) {
			// Check before/after
			const tophalf = e.clientY - D.tRect.top < D.tRect.height / 2;
			if (D.insert == 'before' && !tophalf) {
				if (D.allowAfter) {
					D.targetEl.classList.add('qef-drag-target-after');
					D.targetEl.classList.remove('qef-drag-target-before');
					D.insert = 'after';
				}
			}
			else if (D.insert == 'after' && tophalf) {
				if (D.allowBefore) {
					D.targetEl.classList.add('qef-drag-target-before');
					D.targetEl.classList.remove('qef-drag-target-after');
					D.insert = 'before';
				}
			}
			e.dataTransfer.dropEffect = 'move';
			e.preventDefault();
			// console.log('DRAG OVER TARGET', D, e);
		}
	},
	_dragging: {},
	//#endregion
	/**
	 * Captures mouse position (not currently used)
	 * @param {MouseEvent} e 
	 */
	mousemoveHandler: function(e) {
		this._mousePosition.x = e.pageX;
		this._mousePosition.y = e.pageY;
	},
	_mousePosition: { x: 0, y: 0 },
	//#endregion
	//#region Selection Management and Display
	isSelected: function (id) {
		return this._selected.indexOf(id) > -1;
	},
	isLastSelected: function (id) {
		return this._current == id;
	},
	count: function() {
		return this._selected.length;
	},
	setActive: function (id) {
		if (id == this._current) return;
		if (this._selected.includes(id)) {
			this._current = id;
			this.refresh();
		}
	},
	setSelection: function(ids, refresh = true) {
		this._selected = [];
		for (const id of ids) {
			const fixedId = id.startsWith('design-') ? id : padDesign(id);
			if (this._selected.indexOf(fixedId) == -1) {
				this._selected.push(fixedId);
				this._current = fixedId;
			}
		}
		if (refresh) this.refresh();
	},
	/**
	 * Include or exclude a field from the list of selected fields
	 * @param {string} id 
	 * @param {null|boolean} state
	 * @param {boolean} refresh
	 * @param {boolean} expandFromLast When true, expands the selection from the last selected field
	 */
	toggle: function (id, state = null, refresh = true, expandFromLast = false) {
		if (id == '' || id.endsWith('-sh')) return;
		id = padDesign(id);
		if (id == padDesign(table_pk)) {
			window.scrollTo(0, 0);
			highlightTableRow(id, 3000);
			return;
		}
		if (state == null) {
			if (this._selected.indexOf(id) > -1) {
				this.unselect(id, refresh);
				return;
			}
			else {
				this._selected.push(id);
				this._current = id;
			}
		}
		else {
			if (state && this._selected.indexOf(id) == -1) {
				this._selected.push(id);
				this._current = id;
			}
			if (!state && this._selected.indexOf(id) > -1) {
				this.unselect(id, refresh);
				return;
			}
		}
		if (refresh) this.refresh();
	},
	unselect: function(id, refresh = true) {
		if (!this.isSelected(id)) return;

		this._selected.splice(this._selected.indexOf(id), 1);
		if (this._current == id) {
			this._current = '';
			// Find closest selected field by sorting by the absolute difference in order
			if (this._selected.length > 1) {
				const nearest = this._selected.sort((a, b) => Math.abs(this._fieldOrderMap.get(a) - this._fieldOrderMap.get(id)) - Math.abs(this._fieldOrderMap.get(b) - this._fieldOrderMap.get(id)))[0];
				if (nearest) {
					this._current = nearest;
				}
			}
			else if (this._selected.length == 1) {
				this._current = this._selected[0];
			}
		}
		if (refresh) this.refresh();
	},
	/**
	 * Refreshes the visal selection indicators
	 */
	refresh: function (clear = false, afterFn = null) {
		// Remove all popovers and reset the classes
		if ($('.multi-field-alignment-popover').length) {
			$('.qef-actions-popover [data-multi-field-action="align-show"]').popover('dispose');
		}
		$('.qef-actions-popover [data-multi-field-action="expand-toggle"]').popover('dispose');
		$('.frmedit_tbl').popover('dispose');
		$('.od-tooltip').remove();
		$('.qef-actions-popover').remove();
		$('.qef-field-selected').removeClass('qef-field-selected qef-field-current');
		$('input.qef-select-checkbox').prop('checked', false);
		if (clear) {
			this._selected = [];
			this._current = '';
		}
		// Anything to do?
		if (this._selected.length == 0) {
			this._show('');
			return;
		}
		// Apply selection
		const existing = [];
		this._selected.forEach(function (id) {
			const $el = $('.frmedit_tbl#'+id);
			if ($el.length) {
				$el.addClass('qef-field-selected');
				$('input#mfsckb-'+id).prop('checked', true);
				existing.push(id);
			}
		});
		this._selected = existing;
		// Show popover on the row of the last selected id or the first visible selected row
		if (this._current == '') {
			const $firstVisible = $('.qef-field-selected:visible:first');
			if ($firstVisible.length > 0) this._current = $firstVisible.attr('id');
		}
		if (this._current != '') {
			// Scroll so that the selected row is in view. 
			const el = $('.frmedit_tbl#'+this._current).addClass('qef-field-current').get(0);
			if (el && el.scrollIntoViewIfNeeded) el.scrollIntoViewIfNeeded(); else el?.scrollIntoView();
		}
		this._show(this._current);
		setTimeout(() => {
			addDeactivatedActionTagsWarning();
			if (this._autoExpand) {
				this._autoExpand = false;
				this._action_expand('toggle');
			}
			if (typeof afterFn == 'function') afterFn();
		}, 0);
	},
	/**
	 * Show the menu
	 * @param {string} id 
	 */
	_show: function(id) {
		if (id == '' || this._selected.length == 0) return;
		// Show menu
		const config = this._getPopoverConfig();
		$('#'+id).popover(config).popover('show');
		const $qef = $('.qef-actions-popover');
		$qef.find('[data-qef-content="current-index"]').text(this._getSelectedOrdered().indexOf(this._current) + 1);
		$qef.find('[data-qef-content="selected-count"]').text(this._selected.length);
		this._canNavigate();
		this._setButtonStates();
	},
	_popoverConfig: null,
	_getPopoverConfig: function () {
		if (this._popoverConfig == null) {
			const $tpl = $($('[data-template="qef-template"]').html());
			initTooltips($tpl);

			this._popoverConfig = {
				customClass: 'qef-actions-popover',
				content: $tpl.find('[data-part="content"]'),
				title: $tpl.find('[data-part="title"]').attr('data-part', null),
				html: true,
				placement: this._preferredLocation,
				offset: '0, 5'
			};
		}
		return this._popoverConfig;
	},
	/**
	 * Gets the selected fields (in the order as shown in the designer; with the 'design-' prefix)
	 * @returns {string[]}
	 */
	_getSelectedOrdered : function () {
		const orderedSelected = [];
		$('.qef-field-selected').each(function() {
			orderedSelected.push($(this).attr('id'));
		});
		return orderedSelected;
	},
	/**
	 * Gets the selected fields (in the order as shown in the designer; without the 'design-' prefix)
	 * @returns {string}
	 */
	_getItems: function () {
		return this._getSelectedOrdered().map(trimDesign);
	},
	/**
	 * Gets a comma[space]-separated list of selected fields (in the order as shown in the designer)
	 * @returns {string}
	 */
	_getItemsAsString: function () {
		return this._getItems().join(', ');
	},
	//#endregion
	//#region Actions
	_performAction: function (raw, arg1, arg2) {
		const parts = raw.split('-');
		const action = '_action_'+parts[0];
		const param = parts.length > 1 ? parts[1] : '';
		if (typeof this[action] == 'undefined') {
			console.error('Unknown multi field action: ' + raw);
		}
		else {
			try {
				if (param == '') {
					this[action](arg1, arg2);
				}
				else {
					this[action](param, arg1, arg2);
				}
			}
			catch (e) {
				console.error('Error while executing action: ' + raw, e);
			}
		}
	},
	_action_goto: function (mode, field, form) {
		if (mode == 'show') {
			if (this._gotoFieldActive) return;
			this._gotoFieldActive = true;
			$.get(app_path_webroot+"Design/quick_update_fields.php?pid="+pid, {
					form: getParameterByName('page'),
					'goto-field': 1
				}, 
				function (data) {
					if ((''+data).indexOf('qef-goto-fields') == -1) {
						REDCapQuickEditFields._gotoFieldActive = false;
						// Error
						return;
					}
					// Show goto field modal
					const $modal = $('#qef-goto-field-modal');
					$modal.find('select#qef-goto-fields').html(data).select2({
						placeholder: $('#qef-goto-fields').attr('placeholder'),
						dropdownAutoWidth: true,
						dropdownParent: $modal,
						language: {
							noResults: () => lang.design_1323
						},
						matcher: function (params, data) {
							// If there are no search terms, return all of the data
							if (!params.term) return data;
							// Skip if there is no 'children' property
							if (typeof data.children === 'undefined') return null;
							// `data.children` contains the actual options that we are matching against
							const filteredChildren = [];
							data.children.forEach(function (child) {
								if (typeof child.element.dataset.search != 'undefined') {
									const text = atob(child.element.dataset.search);
									if (text.indexOf(params.term.toLowerCase()) > -1) {
										filteredChildren.push(child);
									}
								}
							});
							if (filteredChildren.length) {
								const modifiedData = $.extend({}, data, true);
								modifiedData.children = filteredChildren;
								return modifiedData;
							}
							// Return `null` if the term should not be displayed
							return null;
						}
					});
					$modal.modal('show');
				}
			);
		}
		else if (mode == 'set') {
			$('#qef-goto-field-modal').modal('hide');
			if (this.getFieldInfo(field) == null) {
				// Field not on current form
				const url = new URL(window.location);
				url.searchParams.set('page', form);
				url.searchParams.set('goto', field);
				window.location = url.toString();
			}
			else {
				if (this.isSelected(padDesign(field))) {
					this.setActive(padDesign(field));
				}
				else {
					this.toggle(padDesign(field));
				}
			}
		}
	},
	_action_clear: function () {
		this.refresh(true);
	},
	_action_copy: function() { 
		copyField(this._getItems());
	},
	_action_move: function() { 
		moveField(this._getItemsAsString(), '');
	},
	_action_delete: function() {
		deleteField(this._getItemsAsString());
	},
	_action_convert: function() {
		convertToMatrix();
	},
	_action_required: function(mode) {
		// Ask for confirmation to remove Required status
		if (mode == 'OFF') {
			simpleDialog(
				interpolateString(lang.design_1149, [this._getItemsAsString()]), 
				lang.design_1148, // REMOVE REQUIRED?
				null, 
				550, 
				null, 
				lang.global_53, // Cancel
				() => this._quickUpdateAjax({ type: 'required', mode: mode }),
				lang.design_1150 // Remove
			);
		}
		else {
			this._quickUpdateAjax({ type: 'required', mode: mode });
		}
	},
	_action_phi: function(mode) {
		// Ask for confirmation to remove PHI status
		if (mode == 'OFF') {
			simpleDialog(
				interpolateString(lang.design_1146, [this._getItemsAsString()]), 
				lang.design_1145, // REMOVE IDENTIFIER?
				null, 
				550, 
				null, 
				lang.global_53, // Cancel
				() => this._quickUpdateAjax({ type: 'phi', mode: mode }),
				lang.design_1147 // Remove
			);
		}
		else {
			this._quickUpdateAjax({ type: 'phi', mode: mode });
		}
	},
	_action_align: function(mode) {
		if (mode == 'show') {
			if ($('.multi-field-alignment-popover').length) {
				$('.qef-actions-popover [data-multi-field-action="align-show"]').popover('dispose');
			}
			else {
				$tpl = $($('[data-template="qef-alignment"]').html());
				initTooltips($tpl, { placement: 'right', subSelector: '[title]' });
				$('.qef-actions-popover [data-multi-field-action="align-show"]')
					.popover({
						customClass: 'multi-field-alignment-popover',
						content: $tpl,
						title: '',
						html: true,
						placement: 'top'
					})
					.popover('show');
			}
		}
		else {
			$('.qef-actions-popover [data-multi-field-action="align-show"]').popover('dispose');
			this._quickUpdateAjax({ type: 'align', mode: mode });
		}
	},
	_action_branchinglogic: function() {
		const current = trimDesign(this._current);
		const fields = this._getItems();
		this._showEditBranchingLogicDialog(current, fields);
	},
	_action_actiontags: function() {
		const current = trimDesign(this._current);
		const fields = this._getItems();
		this._showEditActionTagsDialog(current, fields);
	},
	_action_expand: function(mode) {
		if (mode == 'toggle') {
			if (this._fieldTypes == null) {
				// Reload to get field types info
				this._autoExpand = true;
				reloadDesignTable(getParameterByName('page'), null, false);
			}
			else {
				const current = trimDesign(this._current);
				const fields = this._getItems();
				this._showExpandSelectionDialog(current, fields);
			}
		}
		else if (mode == 'up' || mode == 'down') {
			const idx = this._fieldOrderMap.get(this._current);
			const offset = mode == 'up' ? -1 : 1;
			const next = this._orderFieldMap.get(idx + offset) ?? null;
			if (next == null) return;
			if (this.isSelected(next)) {
				this.setActive(next);
			}
			else {
				this.toggle(next);
			}
		}
	},
	_action_navigate: function(direction) {
		if (this.count() < 2) return;
		// Depending on direction (up, down), set the last selected id 
		// to the (rolling) previous or next selected item
		const ordered = this._getSelectedOrdered();
		const currentIdx = ordered.indexOf(this._current);
		let newIdx = direction == 'prev' ? currentIdx - 1 : currentIdx + 1;
		if (newIdx < 0) newIdx = ordered.length - 1;
		if (newIdx >= ordered.length) newIdx = 0;
		this._current = ordered[newIdx];
		this.refresh(false, () => $('[data-multi-field-action="navigate-'+direction+'"]').get(0).blur());
	},
	_action_choices: function() {
		const current = trimDesign(this._current);
		const fields = this._getItems();
		showProgress(1);
		$.get(app_path_webroot+"Design/existing_choices.php?pid="+pid, { field: current }, response => {
			showProgress(0);
			if (response.error) {
				showToast(lang.global_01, response.error, 'error');
			}
			else {
				this._showEditChoicesDialog(current, fields, response.choices);
			}
		});
	},
	_action_sliders: function() {
		console.warn('Edit Sliders - Not implemented');
	},
	_action_validation: function() {
		console.warn('Set Validation - Not implemented');
	},
	_action_notes: function() {
		console.warn('Edit Field Notes - Not implemented');
	},
	setQuestionNum: function(field) {
		if ($('.multi-field-set-question-num-popover').length) {
			$('[data-field-action="surveycustomquestionnumber"]').popover('dispose');
			return;
		}
		const $field = $('[data-field-action="surveycustomquestionnumber"][data-field-name="'+field+'"]');
		const $tpl = $($('[data-template="qef-set-question-num"]').html());
		const $input = $tpl.find('[data-qeqn-content="question-num"]');
		const orig = this.getFieldInfo(field).questionNum ?? '';
		$input.val(orig);
		$tpl.find('button').on('click', function() {
			const num = $input.val();
			$field.popover('dispose');
			if (num == orig) return;
			$field.addClass('disabled');
			const params = {
				form: getParameterByName('page'),
				fields: field,
				type: 'question-number',
				mode: 'set',
				custom: num
			};
			$.post(app_path_webroot+"Design/quick_update_fields.php?pid="+pid, params, 
				function (data) {
					if (data == "1") { // Success
						REDCapQuickEditFields._fieldTypes[padDesign(field)].questionNum = num;
						$field.text($('<div>' + (num == '' ? '&nbsp;' : num)  + '</div>').text());
					}
					else {
						showToast(lang.global_01, data, 'error');
					}
					$field.removeClass('disabled');
				}
			);
		});
		$field.popover({
			customClass: 'multi-field-set-question-num-popover',
			content: $tpl,
			html: true,
			placement: 'bottom'
		}).popover('show');
	},
	//#endregion
	//#region Ajax
	_quickUpdateAjax: function (params) {
		params.form = getParameterByName('page');
		params.fields = this._getItemsAsString();
		$.post(app_path_webroot+"Design/quick_update_fields.php?pid="+pid, params, 
			function (data) {
				if (data == "1") { // Success
					reloadDesignTable(params.form, '', false);
				}
				else {
					// Failed
					showProgress(0);
					const msg = data == "0" ? lang.design_1144 : data;
					showToast(lang.global_01, msg, 'error');
				}
			}
		);
	},
	//#endregion
	//#region Enable or disable actions based on field selection
	_canNavigate: function() {
		const canNav = this._selected.length > 1;
		$('button[data-multi-field-action^="navigate-"]').prop('disabled', !canNav);
	},
	/**
	 * Fields must be consecutive and of same type (radio or checkbox)
	 */
	_setButtonStates: function() {
		const orderedFields = this._getSelectedOrdered();
		const canConvertTypes = {};
		const matrixGroups = {};
		let canEditEnum = true;
		let canDelete = true;
		let canMove = true;
		let canSetValidation = false;
		let canEditSliders = true;
		let canAlign = false;
		let canEditFieldNote = false;
		// Process all selected in order (top to bottom)
		for (const id of orderedFields) {
			const fi = this.getFieldInfo(id);
			// Edit enum: Only radio/select and checkbox fields
			canEditEnum = canEditEnum && ['radio', 'yesno', 'truefalse', 'select', 'checkbox'].includes(fi.type);
			// Delete/Move: Fields must not include the baseline date field
			if (baseline_date_field != '' && fi.fieldName == baseline_date_field) {
				canDelete = false;
				canMove = false;
			}
			// Sliders: All fields must be sliders
			if (fi.type != 'slider') {
				canEditSliders = false;
			}
			// Align and Field Note: At least one other type than descriptive and matrix fields must be present
			if (fi.type != 'descriptive' && !fi.isMatrixField) {
				canAlign = true;
				canEditFieldNote = true;
			}
			// Set validation: True if at least one text box field is present
			if (fi.type == 'text') {
				canSetValidation = true;
			}
			// Build canConvertTypes
			if (fi.isMatrixField) {
				// Exclude matrix fields by setting type to 'other'
				canConvertTypes.other = true;
			}
			else if (['radio', 'yesno', 'truefalse', 'checkbox'].includes(fi.type)) {
				canConvertTypes.radio = true;
			}
			else if (fi.type == 'checkbox') {
				canConvertTypes.checkbox = true;
			}
			else {
				canConvertTypes.other = true;
			}
			// Move: Build list of matrix groups; baseline date field cannot be moved
			matrixGroups[fi.matrixGroup] = true;
		}
		// Matrix conversion
		const allSequence = '.'+Object.keys(this._fieldTypes).join('.')+'.';
		const selectedSequence = '.'+orderedFields.join('.')+'.';
		const canConvertTypesArray = Object.keys(canConvertTypes);
		const canConvert = canConvertTypesArray.length == 1
			&& ['radio', 'checkbox'].includes(canConvertTypesArray[0])
			&& allSequence.indexOf(selectedSequence) > -1;
		// Move: All fields must be outside of matrix groups or in the same matrix
		canMove = canMove && Object.keys(matrixGroups).length == 1;
		
		// Set button states
		$('button[data-multi-field-action="convert"]').prop('disabled', !canConvert);
		$('button[data-multi-field-action="choices"]').prop('disabled', !canEditEnum);
		$('button[data-multi-field-action="delete"]').prop('disabled', !canDelete);
		$('button[data-multi-field-action="move"]').prop('disabled', !canMove);
		$('button[data-multi-field-action="validation"]').prop('disabled', !canSetValidation);
		$('button[data-multi-field-action="sliders"]').prop('disabled', !canEditSliders);
		$('button[data-multi-field-action="align-show"]').prop('disabled', !canAlign);
		$('button[data-multi-field-action="notes"]').prop('disabled', !canEditFieldNote);
	},
	//#endregion
	//#region Edit branching logic
	_showEditBranchingLogicDialog: function(current, fields) {
		let mode = '';
		let custom = this._lastCustomBranchingLogic;
		const $dlg = $($('[data-template="qef-branchinglogic"]').html());
		// Fill in dynamic content
		$dlg.find('[data-qebl-content="current"]').text(current);
		$dlg.find('[data-qebl-content="fields"]').text(fields.join(', '));
		const $custom = $dlg.find('[data-qebl-content="custom"]').val(custom);
		// Enable copy current option only when there is branching logic on the current field
		// and there are at least 2 fields selected
		const currentHasBL = $('#bl-log_'+ trimDesignSH(current)).text() != '';
		const canCopy = currentHasBL && fields.length > 1;
		$dlg.find('#qebl-mode-copy').prop('disabled', !canCopy);
		// Hook up events to the textarea
		$custom.on('click', function() {
			$dlg.find('input[name="qebl-mode"]').val('new');
			$dlg.find('input#qebl-mode-new').prop('checked', true);
			openLogicBuilder('--QUICK-EDIT--', $custom.val());
		});
		// React to changes
		$dlg.on('change', function(e) {
			const $el = $(e.target);
			mode = $dlg.find('input[name="qebl-mode"]:checked').val();
			custom = $custom.val() ?? '';
			// Enable/Disable 'Apply' button
			const enabled = ['clear', 'copy'].includes(mode) || (mode == 'new' && custom != '');
			$dlg.parents('[role="dialog"]').find('#qef-dlg-apply')[enabled ? 'removeClass' : 'addClass']('ui-state-disabled');
		});
		// Execute when 'Apply' is clicked
		function _doIt() {
			if (mode == 'new') {
				REDCapQuickEditFields._lastCustomBranchingLogic = custom;
			}
			const params = {
				type: 'branchinglogic',
				mode: mode,
				current: current,
				custom: custom
			};
			REDCapQuickEditFields._quickUpdateAjax(params);
		}
		// Show the dialog
		simpleDialog(
			$dlg,
			lang.design_1166,
			null,
			null,
			null,
			lang.global_53,
			_doIt,
			lang.design_1168
		);
		// Make sure the branching logic textarea in the dialog is cleared out each time opened
		$('textarea#qebl-custom').val('');
	},
	//#endregion
	//#region Edit action tags / field annotation
	_showEditActionTagsDialog: function(current, fields) {
		let mode = '';
		let custom = this._lastCustomActionTags;
		const $dlg = $($('[data-template="qef-actiontags"]').html());
		// Fill in dynamic content
		$dlg.find('[data-qeat-content="current"]').text(current);
		$dlg.find('[data-qeat-content="fields"]').text(fields.join(', '));
		const $custom = $dlg.find('[data-qeat-content="custom"]').val(custom);
		// Enable copy current option only when there is misc content on the current field
		$dlg.find('#qeat-mode-copy').prop('disabled', !this.getFieldInfo(current).hasAnnotation);
		// React to changes
		$dlg.on('change', function(e) {
			const $el = $(e.target);
			mode = $dlg.find('input[name="qeat-mode"]:checked').val();
			custom = $custom.val() ?? '';
			// Enable/Disable 'Apply' button
			const enabled = ['clear', 'copy', 'deactivate', 'reactivate'].includes(mode) || (mode == 'append' && custom != '');
			$dlg.parents('[role="dialog"]').find('#qef-dlg-apply')[enabled ? 'removeClass' : 'addClass']('ui-state-disabled');
		});
		// Edit current field's action tags
		$dlg.on('click', '[data-qeat-action]', function() {
			const action = $(this).attr('data-qeat-action') ?? '';
			if (action == 'edit-current') {
				const misc = REDCapQuickEditFields.getFieldInfo(current).misc;
				// Set the field editor's misc field, then open the logic editor
				const $misc = $('#field_annotation').val(misc);
				openLogicEditor($misc, false, () => {
					// Get updated content and clear the field editor's misc field
					const newMisc = $misc.val();
					$misc.val('');
					// Has there been a change?
					if (newMisc != misc) {
						// Close the action tag dialog
						$dlg.parents('.simpleDialog').dialog('close');
						// Perform the update
						mode = 'update';
						custom = newMisc;
						_doIt();
					}
				});
			}
			else if (action == 'clear-actiontags') {
				custom = '';
				$custom.val(custom).trigger('change');
			}
		});
		// Add action tags from action tags popup
		$dlg.on('click', '[data-qeat-action="add-actiontags"]', function(e) {
			e.preventDefault();
			e.stopImmediatePropagation();
			e.stopPropagation();
			const $misc = $('#field_annotation').val(custom);
			REDCapQuickEditFields._actionTagsExplainClosedListener = function() {
				custom = $misc.val();
				$misc.val('');
				$custom.val(custom).trigger('change');
				REDCapQuickEditFields._actionTagsExplainClosedListener = null;
			}
			actionTagExplainPopup(0);
		});
		// Hook up textarea for editing
		$dlg.on('click keypress', '[data-qeat-content="custom"]', function(e) {
			e.preventDefault();
			// Set the field editor's misc field, then open the logic editor
			const $misc = $('#field_annotation').val(custom);
			openLogicEditor($misc, false, () => {
				// Get updated content and clear the field editor's misc field
				custom = $misc.val();
				$misc.val('');
				// Set in the dialog
				$custom.val(custom).trigger('change');
			});
		});
		// Tooltips
		initTooltips($dlg, { placement: 'top' });

		// Execute when 'Apply' is clicked
		function _doIt() {
			if (['append','deactivate','reactivate'].includes(mode)) {
				REDCapQuickEditFields._lastCustomActionTags = custom;
			}
			const params = {
				type: 'actiontags',
				mode: mode,
				current: current,
				custom: custom
			};
			REDCapQuickEditFields._quickUpdateAjax(params);
		}
		// Show the dialog
		simpleDialog(
			$dlg,
			lang.design_1172,
			null,
			null,
			null,
			lang.global_53,
			_doIt,
			lang.design_1168
		);
	},
	//#endregion
	//#region Edit choices
	_showEditChoicesDialog: function(current, fields, currentChoices) {
		let mode = '';
		let convertTo = '';
		let customChoices = this._lastCustomChoices;
		let custom = REDCapChoicesEditor.choicesToText(customChoices);
		const $dlg = $($('[data-template="qef-editchoices"]').html());
		// Fill in dynamic content
		$dlg.find('[data-qeec-content="current"]').text(current);
		$dlg.find('[data-qeec-content="fields"]').text(fields.join(', '));
		const $custom = $dlg.find('[data-qeec-content="custom"]').val(custom);
		// Disable "Copy" when there are no choices or only one selected field, or all are yes/no or true/false
		let copyDisabled = currentChoices.length == 0 || this._selected.length < 2;
		// Or when all targets are yes/no or true/false
		if (!copyDisabled) {
			copyDisabled = copyDisabled || fields.filter(field => {
				const fi = this.getFieldInfo(field);
				return field != current && (fi.type == 'yesno' || fi.type == 'truefalse');
			}).length == fields.length - 1;
		}
		// Or when all fields are in the same matrix
		if (!copyDisabled) {
			const types = {}; // Will hold an entry for each matrix or each field not in a matrix
			fields.forEach(field => {
				const fi = this.getFieldInfo(field);
				types[ fi.isMatrixField ? '_'+fi.matrixGroup : field] = true;
			});
			copyDisabled = copyDisabled || Object.keys(types).length < 2;
		}
		$dlg.find('#qeec-mode-copy').prop('disabled', copyDisabled);
		// Disable "Append" when there are only yes/no or true/false fields
		const allYesNoTrueFalse = fields.every(field => {
			const fi = this.getFieldInfo(field);
			return fi.type == 'yesno' || fi.type == 'truefalse';
		});
		$dlg.find('#qeec-mode-append').prop('disabled', allYesNoTrueFalse);
		$dlg.find('#qeec-custom').prop('disabled', allYesNoTrueFalse);
		// Disable "Convert" when there are only yes/no or true/false fields
		$dlg.find('#qeec-mode-convert, [id^="qeec-convert-"]').prop('disabled', allYesNoTrueFalse);
		// Disable "Drop-down List" when there are only matrix fields (or yes/no, true/false)
		$dlg.find('#qeec-convert-select').prop('disabled', fields.every(field => {
			const fi = this.getFieldInfo(field);
			return fi.isMatrixField || fi.type == 'yesno' || fi.type == 'truefalse';
		}));
		// Disable "Edit" when the current field is a yes/no or true/false field
		const editDisabled = ['yesno', 'truefalse'].includes(this.getFieldInfo(current).type);
		$dlg.find('[data-qeec-action="edit-current"]').prop('disabled', editDisabled)[editDisabled ? 'addClass' : 'removeClass']('disabled');
		// React to changes
		$dlg.on('change', () => {
			mode = $dlg.find('input[name="qeec-mode"]:checked').val() ?? '';
			convertTo = $dlg.find('input[name="qeec-convert"]:checked').val() ?? '';
			if (convertTo == 'select' && $dlg.find('input[name="qeec-autocomplete"]:checked').length > 0) {
				convertTo = 'autocomplete';
			}
			// Enable/Disable 'Apply' button
			const enabled = ['copy'].includes(mode) || (mode == 'append' && customChoices.length) || (mode == 'convert' && convertTo != '');
			_setApplyButtonEnabled(enabled);
		});
		// Check "Convert" when a sub-radio is clicked
		$dlg.on('click', '[name="qeec-convert"]', () => {
			$dlg.find('input#qeec-mode-convert').prop('checked', true).trigger('change');
		});
		// Helper function to set 'Apply' button state (and optionally click it)
		function _setApplyButtonEnabled(enabled, click = false) {
			const $apply = $dlg.parents('[role="dialog"]').find('#qef-dlg-apply');
			$apply[enabled ? 'removeClass' : 'addClass']('ui-state-disabled');
			if (click) {
				$apply.trigger('click');
			}
		}
		// Helper function to close the dialog
		function _closeDialog() {
			const $cancel = $dlg.parents('[role="dialog"]').find('#qef-dlg-cancel');
			$cancel.trigger('click');
		}
		// "Clear" custom button or "Edit choices of the current field" link
		$dlg.on('click', '[data-qeec-action]', (e) => {
			const action = $(e.currentTarget).attr('data-qeec-action') ?? '';
			if (action == 'clear-custom') {
				custom = '';
				customChoices = [];
				$custom.val(custom).trigger('change');
			}
			else if (action == 'edit-current') {
				REDCapChoicesEditor.open(currentChoices, {
					fieldName: current,
					onUpdate: (choices) => {
						// Any actual changes?
						if (JSON.stringify(choices) != JSON.stringify(currentChoices)) {
							// Save changes
							custom = REDCapChoicesEditor.choicesToText(choices);
							mode = 'edit';
							_setApplyButtonEnabled(true, true);
						}
						else {
							// No changes - close dialog
							_closeDialog();
						}
					},
					onCancel: () => $custom.trigger('change')
				});
			}
		});
		// Hook up textarea for editing
		$dlg.on('click keypress', '[data-qeec-content="custom"]', (e) => {
			e.preventDefault();
			$dlg.find('input#qeec-mode-append').prop('checked', true);
			REDCapChoicesEditor.open(customChoices, {
				onUpdate: (choices) => {
					customChoices = choices;
					custom = REDCapChoicesEditor.choicesToText(choices);
					$custom.val(custom).trigger('change');
				},
				onCancel: () => $custom.trigger('change')
			});
		});
		// Tooltips
		$dlg.find('[data-bs-toggle="tooltip"]').each(function() {
			new bootstrap.Tooltip(this);
		});

		// Execute when 'Apply' is clicked
		function _doIt() {
			if (mode == 'append') {
				REDCapQuickEditFields._lastCustomChoices = customChoices;
			}
			const params = {
				type: 'choices',
				mode: mode,
				current: current,
				custom: custom
			};
			if (mode == 'convert') {
				params.custom = convertTo;
			}
			else if (mode == 'append') {
				params.custom = customChoices;
			}
			REDCapQuickEditFields._quickUpdateAjax(params);
		}
		// Show the dialog
		simpleDialog(
			$dlg,
			lang.design_1268,
			null,
			null,
			null,
			lang.global_53,
			_doIt,
			lang.design_1168
		);
	},
	//#endregion
	//#region Expand selection
	_showExpandSelectionDialog: function(current, fields) {
		if ($('.multi-field-expand-popover').length) {
			$('.qef-actions-popover [data-multi-field-action="expand-toggle"]').popover('dispose');
		}
		else {
			$tpl = $($('[data-template="qef-expand"]').html());
			$tpl_content = $tpl.find('[data-part="content"]');
			$tpl_title = $tpl.find('[data-part="title"]');
			$tpl.find('[title]').each(function() {
				new bootstrap.Tooltip(this, {
					trigger: 'hover',
					html: true,
					placement: () => $(this).attr('data-bs-placement') ?? ($(this).parents('.qees-exclude').length ? 'bottom' : 'top'),
					delay: { "show": 300, "hide": 0 },
					customClass: 'od-tooltip'
				});
			});
			$tpl_content.on('click', '[data-qees-action]', function() {
				const action = $(this).attr('data-qees-action') ?? '';
				if (action.startsWith('clear-')) {
					const targetSelector = action.replace('clear-', '.qees-') + ' label input[type="radio"]';
					$tpl_content.find(targetSelector).prop('checked', false);
				}
				else if (action.startsWith('apply-')) {
					_doIt(action.explode('-', 2)[1], {
						direction: $tpl_content.find('.qees-direction input[type="radio"]:checked').val(),
						include: $tpl_content.find('.qees-include input[type="radio"]:checked').map(function() { return $(this).val(); }).get(),
						exclude: $tpl_content.find('.qees-exclude input[type="radio"]:checked').map(function() { return $(this).val(); }).get()
					});
				}
			});
			$tpl_title.on('click', '[data-qees-action]', function() {
				const action = $(this).attr('data-qees-action') ?? '';
				if (action == 'help') {
					// Show some help content
					simpleDialog(lang.design_1210, lang.design_1209, null, null, null, lang.calendar_popup_01, null, null);
				}
			});
			$tpl_content.on('click keydown', 'input[type="radio"].incl-excl', function(e) {
				const $this = $(this);
				const prev = $this.data('prev-state') ?? false;
				if (e.type == 'keydown' && e.code == 'Space') {
					if (prev) {
						e.preventDefault();
						$this.prop('checked', false).data('prev-state', false);
					}
					return;
				}
				if (e.type == 'click') {
					let state = $this.prop('checked');
					if (prev && state) {
						state = false;
						$this.prop('checked', false);
					}
					$this.data('prev-state', state);
				}
			});
			$('.qef-actions-popover [data-multi-field-action="expand-toggle"]')
				.popover({
					customClass: 'multi-field-expand-popover',
					content: $tpl_content,
					title: $tpl_title,
					html: true,
					placement: 'top'
				})
				.popover('show');
			$tpl_content.find('#qees-dir-all').get(0)?.focus({ preventScroll: true });
		}
		// Execute when 'Apply' is clicked
		function _doIt(mode, config) {
			// Assemble list of fields in order
			const all = [];
			const selected = [];
			$('.frmedit_tbl').each(function() {
				const id = this.id.replace('frmedit_', '');
				if (id == padDesign(table_pk)) return;
				if (id.endsWith('-sh')) return;
				all.push(id);
				if (mode == 'expand' && REDCapQuickEditFields.isSelected(id)) {
					selected.push(id);
				}
			});
			// Apply inclusions first
			const included = config.include.length == 0 
				? all 
				: all.filter(id => {
					// If already selected, keep it when expanding
					if (mode == 'expand' && selected.includes(id)) return true;
					// Filter by field type
					const fieldTypes = REDCapQuickEditFields.getFieldType(id);
					return fieldTypes.some(x => config.include.includes(x));
				});
			// Then, apply exclusions
			const filtered = config.exclude.length == 0
				? included 
				: included.filter(id => {
					// If already selected, keep it when expanding
					if (mode == 'expand' && selected.includes(id)) return true;
					// Filter by field type
					const fieldTypes = REDCapQuickEditFields.getFieldType(id);
					return !fieldTypes.some(x => config.exclude.includes(x));
				});
			// Save last selected and window's scroll position
			const scrollTop = $(window).scrollTop();
			const current = REDCapQuickEditFields._current;
			// Clear selection when replacing
			if (mode == 'replace') {
				REDCapQuickEditFields._selected = [];
			}
			if (config.direction == 'all') {
				for (const id of all) {
					if (filtered.includes(id)) {
						REDCapQuickEditFields.toggle(id, true, false);
					}
				}
			}
			else if (config.direction == 'top') {
				const end = all.indexOf(current);
				for (const id of all.slice(0, end)) {
					if (!selected.includes(id) && filtered.includes(id)) {
						REDCapQuickEditFields.toggle(id, true, false);
					}
				}
			}
			else if (config.direction == 'bottom') {
				const start = all.indexOf(current);
				for (const id of all.slice(start+1)) {
					if (!selected.includes(id) && filtered.includes(id)) {
						REDCapQuickEditFields.toggle(id, true, false);
					}
				}
			}
			else if (config.direction == 'first2last') {
				const start = all.indexOf(selected[0]);
				const end = all.indexOf(selected[selected.length-1]);
				for (const id of all.slice(start, end+1)) {
					if (filtered.includes(id)) {
						REDCapQuickEditFields.toggle(id, true, false);
					}
				}
			}
			else if (config.direction == 'up') {
				const start = all.indexOf(current);
				for (let i = start-1; i >= 0; i--) {
					const id = all[i];
					if (selected.includes(id)) break;
					if (filtered.includes(id)) {
						REDCapQuickEditFields.toggle(id, true, false);
					}
				}
			}
			else if (config.direction == 'down') {
				const start = all.indexOf(current);
				for (let i = start+1; i < all.length; i++) {
					const id = all[i];
					if (selected.includes(id)) break;
					if (filtered.includes(id)) {
						REDCapQuickEditFields.toggle(id, true, false);
					}
				}
			}
			// Restore last selected and window's scroll position
			if (REDCapQuickEditFields._selected.includes(current)) {
				REDCapQuickEditFields._current = current;
				$(window).scrollTop(scrollTop);
			}
			// Refresh
			REDCapQuickEditFields.refresh();
			// Show warning when this action has resulted in no fields being selected
			if (REDCapQuickEditFields._selected.length == 0) {
				showToast(lang.global_48, lang.design_1218, 'warning');
			}
		}
	},
	//#endregion
	//#region Field Types
	/**
	 * Gets information about the field
	 * @param {string} id A field id (can be prefixed with 'design-')
	 * @returns {Object}
	 */
	getFieldInfo: function(id) {
		if (!this._fieldTypes) {
			return null;
		}
		return this._fieldTypes[padDesign(trimSH(id))] ?? null;
	},
	getNextFieldInfo: function(id) {
		const thisIdx = this._fieldOrderMap.get(padDesign(trimSH(id)));
		if (!thisIdx) return null;
		nextId = this._orderFieldMap.get(thisIdx+1);
		return this.getFieldInfo(nextId);
	},
	getPrevFieldInfo: function(id) {
		const thisIdx = this._fieldOrderMap.get(trimSH(id));
		if (!thisIdx) return null;
		prevId = this._orderFieldMap.get(thisIdx-1);
		return this.getFieldInfo(prevId);
	},
	getFieldType: function(id) {
		if (!this._fieldTypes) {
			return [];
		}
		const fi = this.getFieldInfo(id);
		const types = [];
		if (fi.isMatrixField) types.push('matrix');
		if (fi.isRequired) types.push('required');
		if (fi.isPHI) types.push('phi');
		switch (fi.type) {
			case 'text':
				types.push('text');
				if (fi.validation) {
					if (fi.validation == 'integer' || fi.validation.startsWith('number')) types.push('number');
					if (fi.validation.startsWith('date') || fi.validation.startsWith('time')) types.push('datetime');
					if (fi.validation == 'email') types.push('email');
					if (fi.validation.startsWith('phone')) types.push('phone');
				}
				break;
			case 'radio':
				types.push('radio');
				break;
			case 'yesno':
				types.push('radio', 'yesno');
				break;
			case 'truefalse':
				types.push('radio', 'truefalse');
				break;
			case 'select':
			case 'checkbox':
			case 'textarea':
			case 'calc':
			case 'slider':
			case 'descriptive':
				types.push(fi.type);
				break;
			case 'file':
				types.push(fi.validation == 'signature' ? 'signature' : 'file');
				break;
		}
		return types;
	},
	setFieldTypes: function(fieldTypesJson = null) {
		if (fieldTypesJson == null) {
			const $fieldTypesJson = $('[data-qef-field-types-json]');
			fieldTypesJson = atob($fieldTypesJson.text()) ?? '{}';
			$fieldTypesJson.remove();
		}
		this._setFieldTypesAndOrderMaps(JSON.parse(fieldTypesJson));
	},
	_setFieldTypesAndOrderMaps: function(fieldTypes) {
		this._fieldTypes = fieldTypes;
		// Update field order map and drag images
		this._fieldOrderMap = new Map();
		this._orderFieldMap = new Map();
		this._matrixGroups = {};
		for (const id in this._fieldTypes) {
			const fi = this._fieldTypes[id];
			this._fieldOrderMap.set(id, fi.order);
			this._orderFieldMap.set(fi.order, id);
			// Matrix?
			if (fi.isMatrixField) {
				if (!this._matrixGroups[fi.matrixGroup]) {
					this._matrixGroups[fi.matrixGroup] = { dragImg: null, fields: [ id ] };
				}
				else {
					this._matrixGroups[fi.matrixGroup].fields.push(id);
				}
			}
		}
	},
	updateFieldOrder: function(fieldTypes, drop) {
		this._setFieldTypesAndOrderMaps(fieldTypes);
		const $source = $('tr[id="'+trimDesignSH(drop.source)+'-tr"]');
		const $target = $('tr[id="'+trimDesignSH(drop.target)+'-tr"]');
		// There should be only one source and one target
		if ($source.length == 1 && $target.length == 1) {
			// Fine to swap
			$target[drop.insert]($source);
		}
		else {
			// If there is not ... then force a reload just to be safe
			reloadDesignTable(getParameterByName('page'), null, false);
		}
	},
	_matrixGroups: {},
	getFirstMatrixField: function(mg_name) {
		return this._matrixGroups[mg_name] ? this._matrixGroups[mg_name].fields[0] : null;
	},
	getLastMatrixField: function(mg_name) {
		return this._matrixGroups[mg_name] ? this._matrixGroups[mg_name].fields[this._matrixGroups[mg_name].fields.length - 1] : null;
	},
	//#endregion
	//#region Misc Helpers
	setPreferredLocation: function(loc) {
		if (this._popoverConfig) {
			this._popoverConfig.placement = loc;
		}
		this._preferredLocation = loc;
	}
	//#endregion
};

//#region Trimming and Padding Helpers
/**
 * Trims the '-sh' suffix from an ID if it exists
 * @param {string} id 
 * @returns {string}
 */
function trimSH(id) {
	return id && id.substring(id.length - 3) == '-sh' ? id.substring(0, id.length - 3) : id;
}
/**
 * Trims the 'design-' prefix from an ID if it exists
 * @param {string} id 
 * @returns {string}
 */
function trimDesign(id) {
	return id && id.startsWith('design-') ? id.substring(7) : id;
}
/**
 * Trims the 'design-' prefix and the '-sh' suffix from an ID if they exist
 * @param {string} id 
 * @returns {string}
 */
function trimDesignSH(id) {
	return trimSH(trimDesign(id));
}
/**
 * Pads the 'design-' prefix to an ID if it doesn't already exist
 * @param {string} id 
 * @returns {string}
 */
function padDesign(id) {
	return id && id.startsWith('design-') ? id : ('design-' + id);
}
/**
 * Pads the '-sh' suffix to an ID if they don't already exist
 * @param {string} id 
 * @returns {string}
 */
function padSH(id) {
	return id && id.endsWith('-sh') ? id : (id + '-sh');
}
/**
 * Pads the 'design-' prefix and the '-sh' suffix to an ID if they don't already exist 
 * @param {string} id 
 * @returns {string}
 */
function padDesignSH(id) {
	return padSH(padDesign(id));
}
//#endregion

const REDCapChoicesEditor = {
	//#region Variables and default config
	_sheet: null,
	_$dlg: null,
	_config: {},
	_originalChoices: [],
	_minRows: 10,
	_mergeConfig: function(customConfig) {
		const config = {
			title: lang.design_1277,
			fieldName: null,
			colLabels: [
				' ',
				lang.design_1275,
				lang.design_1276
			],
			colTooltips: [
				lang.design_1278,
				lang.design_1292,
				lang.design_1293 + '<br><small>' + lang.design_1300 + '</small>'
			],
			cancelLabel: lang.global_53,
			updateLabel: lang.global_169,
			// Callbacks
			onUpdate: null,
			onCancel: null
		}
		for (const option in customConfig) {
			config[option] = customConfig[option];
		}
		return config;
	},
	//#endregion
	//#region Setup and Configuration
	/**
	 * Opens the choices editor
	 * @param {Object} choices 
	 * @param {Object} customConfig 
	 */
	open: function(origChoices, customConfig = {}) {
		// Merge in custom config
		this._config = this._mergeConfig(customConfig);
		// Copy choices
		this._originalChoices = this.copy(origChoices);
		// Initialize the template
		this._$dlg = $($('[data-template="qef-kvpair-editor"]').html());
		//#region -- Set up the spreadsheet
		const $spreadsheet = this._$dlg.find('[data-qef-kvpair-content="spreadsheet"]');
		const spreadheetConfig = {
			about: false,
			allowExport: false,
			csvDelimiter: '\t',
			copyCompatibility: true,
			allowRenameColumn: false,
			allowDeleteColumn: false,
			confirmDeleteRow: false,
			allowComments: false,
			parseFormulas: false,
			columnResize: false,
			columnDrag: false,
			allowInsertColumn: false,
			wordWrap: true,
			columnSorting: false,
			tableOverflow: true,
			tableHeight: '500px',
			search: true,
			pagination: 10,
			minDimensions: [3, 10],
			minSpareRows: 1,
			columns: [
				{
					type: 'checkbox',
					title: this._config.colLabels[0],
					width: 20,
					searchable: false
				},
				{
					type: 'text',
					title: this._config.colLabels[1],
					width: 120,
					align: 'left',
				},
				{
					type: 'text',
					title: this._config.colLabels[2],
					width: 425,
					align: 'left'
				}
			],
			onchangepage: (el, page, oldPage) => { },
			onchange: (el, rec, x, y, value, oldValue) => {
				if (x == '1') {
					// Sanitize code and warn if changes were made
					const sanitized = this._sanitizeCode(value);
					if (sanitized != value) {
						this._sheet.setValueFromCoords(x, y, sanitized);
						const msg = interpolateString(lang.design_1299, [sanitized]) + '<br>' + lang.design_1300;
						showToast(lang.design_1298, msg, 'warning', 5000);
						this._highlightCell([x, y]);
					}
					else {
						this._unhighlightCell([x, y]);
					}
				}
				else if (x == '2') {
					// New lines are not allowed - replace with <br>
					if (value.indexOf('\n') > -1) {
						value = value.replace(/\n/g, '<br>');
						this._sheet.setValueFromCoords(x, y, value);
						showToast(lang.global_48, lang.design_1309, 'warning', 5000);
						this._highlightCell([x, y]);
					}
					else {
						this._unhighlightCell([x, y]);
					}
				}
			},
			oneditionstart: (el, cell, x, y) => { },
			onbeforepaste: (el, data, x, y) => {
				// Apply some restrictions when copying to code column
				if (x == 1) {
					const result = this._prepPasteData(data, Number.parseInt(y));
					if (result.updatedCodes.length > 0) {
						showToast(lang.design_1298, lang.design_1302, 'warning', 5000);
						// console.warn(lang.design_1302);
						// if (typeof console.table != 'undefined') {
						// 	console.table(result.updatedCodes)
						// }
						// else {
						// 	console.log(result.updatedCodes);
						// }
						// console.log(lang.design_1300);
					} 
					else if (result.warnings.length > 0 || result.duplicateLabels.length > 0 || result.duplicatesDropped) {
						showToast(lang.global_48, lang.design_1303, 'warning', 5000);
					}
					setTimeout(() => {
						result.warnings.forEach(cell => this._highlightCell(cell));
					}, 100);
					return result.data == '' ? false : result.data;
				}
				else {
					return data;
				}
			},
			ondeleterow: (el, rowNumber, numOfRows, rowRecords) => {
				// Keep min row count
				const numRows = this._sheet.rows.length;
				if (numRows < this._minRows) {
					this._sheet.insertRow(this._minRows - numRows, numRows);
				} 
			},
			text: {
				search: '',
				insertANewRowBefore: lang.design_1280,
				insertANewRowAfter: lang.design_1281,
				deleteSelectedRows: lang.design_1282,
				showingPage: lang.design_1283,
				noRecordsFound: '<i>' + lang.design_1305 + '</i>'
			}
		};
		spreadheetConfig.data = this.choicesToSpreadsheet(this._originalChoices);
		this._sheet = jspreadsheet($spreadsheet[0], spreadheetConfig);
		//#endregion
		//#region -- Set up the main dialog
		// Set the dialog title (add field name when set in config)
		let title = onlineDesigner_choicesIcon+'<span class="ms-1">'+this._config.title+'</span>';
		if (this._config.fieldName) {
			title += ' &ndash; [<span class="qef-kvpair-fieldname">' + this._config.fieldName + '</span>]';
		}
		// Show the dialog
		this._$dlg.dialog({ 
			bgiframe: true,
			closeOnEscape: false,
			modal: true,
			width: 670,
			resizable: false,
			title: title,
			buttons: [
				{
					html: '<i class="fas fa-times text-rc-danger me-1"></i><span>'+this._config.cancelLabel+'</span>',
					click: () => this._performAction('cancel')
				},
				{
					html: '<i class="fas fa-check me-1"></i><b>'+this._config.updateLabel+'</b>',
					style: 'color: var(--color-rc-green);',
					click: () => this._performAction('update')
				}
			],
			open: () => {
				// Configure the dialog
				this._$dlg.find('input.jexcel_search')
					.addClass("ms-1 form-control form-control-sm")
					.attr('placeholder', lang.design_1304)
					.on('search', (e) => {
						// Needed to make jSpreadsheet work nicely with search inputs
						if (e.target.value == '') this._sheet.resetSearch();
					})
					.parent().css('display', 'flex');
				// Wrap column labels in span and add tooltips
				this._$dlg.find('table.jexcel thead td').each(function() {
					const $td = $(this).addClass('choices-editor-header-cell');
					const colIdx = Number.parseInt($td.attr('data-x') ?? -1);
					if (colIdx > -1) {
						const title = REDCapChoicesEditor._config.colLabels[colIdx].trim();
						const tooltip = REDCapChoicesEditor._config.colTooltips[colIdx];
						if (title.length > 0) {
							$td.html(`<span data-bs-toggle="tooltip" data-bs-offset="0,11" title="${REDCapChoicesEditor._config.colTooltips[colIdx]}">${REDCapChoicesEditor._config.colLabels[colIdx]}</span>`);
						}
						else {
							$td.attr('data-bs-toggle', 'tooltip');
							$td.attr('title', tooltip);
						}
					}
				});
				// Move the toolbar
				this._$dlg.find('.qef-kvpair-toolbar').prependTo(this._$dlg.find('.jexcel_filter'));
				// Add tooltips
				this._$dlg.find('[data-bs-toggle="tooltip"]').each(function() {
					new bootstrap.Tooltip(this, { 
						html: true,
						delay: {
							show: $(this).parents('table.jexcel').length ? 50 : 500,
							hide: 0
						},
						trigger: 'hover',
						customClass: 'od-tooltip'
					});
				});
				// Add event listeners
				this._$dlg.on('click', '[data-qef-kvpair-action]', function() {
					REDCapChoicesEditor._performAction($(this));
				});
				// Select cell B1 (1, 0)
				this._sheet.updateSelectionFromCoords(1, 0, 1, 0);
			}	
		});
		//#endregion
	},
	//#endregion
	//#region Actions
	//#region -- Dispatcher
	/**
	 * Actions dispatcher
	 * @param {JQuery<HTMLElement>|string} $btn 
	 */
	_performAction: function($btn, arg1, arg2) {
		this._enableSpreadsheet(false);
		const raw = typeof $btn == 'string' ? $btn : $btn.attr('data-qef-kvpair-action');
		const parts = raw.explode('-', 2);
		const action = parts[0];
		const mode = '' + (parts[1] ?? '');
		if (typeof this['_action_' + action] == 'function') {
			const result = mode.length 
				? this['_action_'+action](mode, arg1, arg2) 
				: this['_action_'+action](arg1, arg2);
			if (result && typeof $btn != 'string') {
				// Indicate success by flashing the button
				$btn.css({
					'outline': '2px solid var(--color-rc-green)',
					'outline-offset': '-2px'
				});
				setTimeout(() => $btn.css('outline', 'none'), 200);
			}
		}
		else {
			console.warn('Unrecognized action:', raw);
		}
		this._enableSpreadsheet(true);
	},
	//#endregion
	//#region -- Update and Cancel
	/**
	 * Verifies the choices and closes the dialog when valid
	 */
	_action_update: function() {
		// Check if the choices are valid ('paste' check)
		const text = this.choicesToText(this._choicesFromSpreadsheet(true), '\t');
		const result = this._prepPasteData(text, 0, true);
		const valid = result.updatedCodes.length == 0 && result.warnings.length == 0 && result.duplicateLabels.length == 0 && !result.duplicatesDropped;

		if (valid) {
			const choices = this._choicesFromSpreadsheet();
			// Close dialog
			this._$dlg.dialog('destroy');
			// Callback registered? Call it with the choices!
			if (typeof this._config.onUpdate == 'function') {
				this._config.onUpdate(choices);
			}
			return;
		}

		// Highlight issues and add set markers
		const marked = this._sheet.getColumnData(0).map(() => false);
		result.warnings.forEach(cell => {
			this._highlightCell(cell);
			marked[cell[1]] = true;
		});
		this._sheet.setColumnData(0, marked);

		// Add error messages
		const errors = [];
		if (result.missingCodes.length > 0) {
			errors.push(lang.design_1314);
		}
		if (result.missingLabels.length > 0) {
			errors.push(lang.design_1315);
		}
		if (result.duplicateLabels.length > 0) {
			errors.push(lang.design_1316);
		}
		if (result.duplicateCodes.length > 0) {
			errors.push(lang.design_1317);
		}
		if (result.duplicatesDropped) {
			errors.push(lang.design_1318);
		}

		// Prepare and show error dialog
		const $content = $('<div></div>')
			.append('<p data-rc-lang="design_1296">'+lang.design_1296+'</p>')
			.append('<ul></ul>')
			.append('<p data-rc-lang="design_1313">'+interpolateString(lang.design_1313, [
				'<i class="fa-solid fa-broom text-rc-purple"></i>'
			])+'</p>');
		for (const error of errors) {
			$content.find('ul').append('<li>'+error+'</li>');
		}
		simpleDialog($content, lang.design_1295, null, null, null, lang.calendar_popup_01);
	},
	/**
	 * Closes the dialog without making any changes
	 */
	_action_cancel: function() {
		this._$dlg.dialog('destroy');
		if (typeof this._config.onCancel == 'function') {
			this._config.onCancel();
		}
	},
	//#endregion
	//#region -- Copy, Paste, Undo, and Cleanup
	/**
	 * Copies the current choices (unverified) to the clipboard (tab-separated values)
	 */
	_action_copy: function() {
		const choices = this._choicesFromSpreadsheet();
		const text = this.choicesToText(choices, '\t');
		copyTextToClipboard(text);
		return true;
	},
	_action_from: async function(mode, text = null) {
		let signalSuccess = false;
		switch (mode) {
			case 'paste': {
				try {
					if (text == null) {
						// Get text from clipboard
						text = await navigator.clipboard.readText();
					}
					// Get offset
					const codes = this._sheet.getColumnData(1);
					const labels = this._sheet.getColumnData(2);
					let lastIdx = -1;
					for (let i = codes.length - 1; i >= 0; i--) {
						if (codes[i].length > 0 || labels[i].length > 0) {
							lastIdx = i;
							break;
						}
					}
					if (lastIdx == codes.length - 1) {
						// Add a new row
						this._sheet.insertRow(lastIdx );
					}
					this._sheet.paste(1, lastIdx + 1, text);
					signalSuccess = true;
				}
				catch (ex) {
					console.error('Failed to read text from clipboard:', ex);
				}
			} break;

			case 'existing': {
				$.get(app_path_webroot+"Design/existing_choices.php?pid="+pid, { 
					is_editor: 1 
				},function(data) {
					if (typeof data != 'object' || !data.content) {
						showToast(lang.global_01, lang.global_64, 'error');
					}
					$dlg = $(data.content);
					initTooltips($dlt, { delay: 500 });
					$dlg.on('click', 'button[data-ec-source-field]', (e) => {
						// Get choices
						const ec_field = $(e.currentTarget).attr('data-ec-source-field');
						let ec_value = $('#ec_'+ec_field).html()
							.replace(/<br>/gi, "\n") // Replace <br> with \n
							.replace(/&gt;/gi, ">")  // Replace >
							.replace(/&lt;/gi, "<"); // Replace <
						$('#existing_choices_popup').dialog('destroy');
						REDCapChoicesEditor._performAction('from-paste', ec_value);
					});
					simpleDialog($dlg, data.title, 'existing_choices_popup');
					fitDialog($('#existing_choices_popup'));
				});
			} break;
		
			default: {
				console.warn('Action: from, Mode: '+mode+' - Not implemented yet.');
			} break;
		}
		return signalSuccess;
	},
	_action_undo: function() {
		this._sheet.undo();
		while (this._sheet.rows.length < this._minRows) {
			this._sheet.undo();
		}
		return true;
	},
	_action_cleanup: function() {
		const codes = this._sheet.getColumnData(1);
		const labels = this._sheet.getColumnData(2);
		const nOrigRows = codes.length;
		let highestIntCode = codes.reduce((prev, cur) => { 
			const num = Number.parseInt(cur);
			return Math.max(prev, isNaN(num) ? 0 : num); 
		}, 1);
		const revisedCodes = [];
		const revisedLabels = [];
		let revisedIdx = 0;
		const highlightCells = []
		for (let i = 0; i < codes.length; i++) {
			// Make sure there are no newlines in a label
			const label = labels[i].replace(/\n/g, '<br>');
			if (codes[i] == '' && labels[i] == '') continue;
			if (codes[i] == '') {
				highlightCells.push([1, revisedIdx]);
				revisedCodes.push(''+(++highestIntCode));
				revisedLabels.push(label);
			}
			else if (labels[i] == '') {
				highlightCells.push([2, revisedIdx]);
				revisedCodes.push(codes[i]);
				revisedLabels.push(codes[i]);
			}
			else {
				revisedCodes.push(codes[i]);
				revisedLabels.push(label);
				if (labels[i] != label) {
					// Label has been changed
					highlightCells.push([2, revisedIdx]);
				}
			}
			revisedIdx++;
		}
		// Delete all rows
		this._sheet.insertRow(1, 0, true);
		this._sheet.deleteRow(1, nOrigRows);
		// Set new data
		this._sheet.setColumnData(1, revisedCodes);
		this._sheet.setColumnData(2, revisedLabels);
		// Perform the 'paste' check
		const text = this.choicesToText(this._choicesFromSpreadsheet(), '\t');
		const result = this._prepPasteData(text, 0, true);
		if (result.updatedCodes.length > 0 || result.warnings.length > 0 || 
			result.duplicateLabels.length > 0 || result.duplicatesDropped) {
			showToast(lang.global_48, lang.design_1312, 'warning', 5000);
		}
		// Highlight (first, make sure there are no duplicate cells)
		const marked = this._sheet.getColumnData(0);
		let combined = highlightCells.concat(result.warnings);
		combined = combined.filter((w, idx) => combined.findIndex(w2 => w[0] == w2[0] && w[1] == w2[1]) == idx);
		combined.forEach(cell => {
			this._highlightCell(cell);
			marked[cell[1]] = true;
		});
		this._sheet.setColumnData(0, marked);
		return true;
	},
	//#endregion
	//#region -- Marked: Move, Include/Exclude, Delete
	_action_marked: function(mode) {
		let signalSuccess = false;
		switch (mode) {
			case 'unmark': {
				let updated = false;
				const selected = this._sheet.getSelectedRows(true);
				if (selected.length > 0) {
					const marked = this._sheet.getColumnData(0);
					for (const i of selected) {
						updated = updated || marked[i];
						marked[i] = false;
					}
					this._sheet.setColumnData(0, marked);
					signalSuccess = updated;
				}
			} break;

			case 'mark': {
				let updated = false;
				const selected = this._sheet.getSelectedRows(true);
				if (selected.length > 0) {
					const marked = this._sheet.getColumnData(0);
					for (const i of selected) {
						updated = updated || !marked[i];
						marked[i] = true;
					}
					this._sheet.setColumnData(0, marked);
					signalSuccess = updated;
				}
			} break;

			case 'delete': {
				const marked = this._sheet.getColumnData(0);
				const pageNum = this._sheet.pageNumber;
				let numRows = marked.length;
				let numDeleted = 0;
				for (let i = numRows - 1; i >= 0; i--) {
					if (marked[i]) {
						this._sheet.deleteRow(i);
						numRows--;
						numDeleted++;
					}
				}
				if (pageNum > (marked.length - numDeleted)/this._minRows) {
					this._sheet.page(Math.floor((marked.length - numDeleted)/this._minRows));
				}
				signalSuccess = numDeleted > 0;
			} break;

			case 'up': {
				 const /** @type {array} */ marked =this._sheet.getColumnData(0);
				// If the first row is marked, nothing to do
				if (marked[0]) break;
				// Each marked row will be swapped with the previous one
				for (let i = 1; i < marked.length; i++) {
					if (marked[i]) {
						const thisRow = this._sheet.getRowData(i);
						// Need to copy the previous row; otherwise it will be overwritten
						const prevRow = [...this._sheet.getRowData(i - 1)];
						this._sheet.setRowData(i - 1, thisRow);
						this._sheet.setRowData(i, prevRow);
						signalSuccess = true;
					}
				}
			} break;

			case 'down': {
				const /** @type {array} */ marked = this._sheet.getColumnData(0);
				const lastRowIdx = marked.length - 1;
				// When the last row is marked, add a new row first
				if (marked[lastRowIdx]) {
					this._sheet.insertRow(lastRowIdx);
				}
				// Simply swap out rows, starting from the back
				for (let i = lastRowIdx; i >= 0; i--) {
					if (marked[i]) {
						const thisRow = this._sheet.getRowData(i);
						// Need to copy the next row; otherwise it will be overwritten
						const nextRow = [...this._sheet.getRowData(i + 1)];
						this._sheet.setRowData(i + 1, thisRow);
						this._sheet.setRowData(i, nextRow);
						signalSuccess = true;
					}
				}
			} break;

			default: {
				console.warn('Action: marked, Mode: '+mode+' - Not implemented yet.');
			} break;
		}
		return signalSuccess;
	},
	//#endregion
	//#region -- Export
	_action_exportCsv: function(mode) {
		exportChoiceEditorToCSV(mode);
	},
	//#endregion
	//#endregion
	//#region Helpers
	choicesToText: function(choices, delimiter = ', ') {
		return choices.reduce(function(s, choice) {
			return s + `${choice.code}${delimiter}${choice.label}\n`;
		}, '');
	},
	choicesToCsv: function(choices, delimiter) {
		return choices.reduce(function(s, choice) {
			return s + `${choice.code}${delimiter}${choice.label}\n`;
		}, '');
	},
	/**
	 * Converts a text string into an array of choices
	 * @param {string} text 
	 * @returns []
	 */
	choicesFromText: function(text) {
		const lines = text.split('\n').filter(l => l.trim().length > 0);
		const choices = [];
		for (const line of lines) {
			const parts = line.explode(',', 2);
			choices.push({ code: parts[0].trim(), label: parts[1].trim() });
		}
		return choices;
	},
	choicesToSpreadsheet: function(choices) {
		const data = [];
		for (const choice of choices) {
			data.push([
				choice.selected && choice.selected == true,
				choice.code,
				choice.label
			]);
		}
		return data;
	},
	_choicesFromSpreadsheet: function(includeBlankRows = false) {
		const data = this._sheet.getData(false);
		const choices = [];
		for (const row of data) {
			if (row[1] != '' || row[2] != '' || includeBlankRows) {
				choices.push({
					code: row[1].trim(),
					label: html_entity_decode(row[2]).trim(),
				});
			}
		}
		return choices;
	},
	/**
	 * Validate the input
	 * @param {string} data The data to be pasted
	 * @param {int} yOffset The offset from the top of the sheet
	 * @param {boolean} simulate In simulation mode, an empty sheet will be assumed and no duplicates rows will actually be dropped
	 * @returns 
	 */
	_prepPasteData: function(data, yOffset, simulate = false) {
		const lines = simulate
			? data.replace(/\r\n/g, '\n').split('\n')
			: data.replace(/\r\n/g, '\n').split('\n').filter(l => (l.trim()).length > 0);
		// Determine delimiter by looking for the position of each of comma, semicolon, or tab
		// The one with the lowest position must be the delimiter; if none, try the next line
		const delimiters = [',', ';', '\t'];
		let delimiter = '';
		for (const line of lines) {
			const pos = delimiters.map(d => line.indexOf(d));
			let minPos = -1;
			let minPosIdx = -1;
			for (const i in pos) {
				if (pos[i] > -1 && (pos[i] < minPos || minPos == -1)) {
					minPos = pos[i];
					minPosIdx = i;
				}
			}
			if (minPosIdx > -1) {
				delimiter = delimiters[minPosIdx];
				break;
			}
		}
		// When there is no delimiter, let all pass
		if (delimiter == '') {
			return { 
				data: data,
				updatedCodes: [],
				warnings: [],
				duplicatesDropped: false,
				duplicateLabels: []
			};
		}
		// Get some data necessary for duplicate code checks
		const codes = simulate ? [] : this._sheet.getColumnData(1);
		const labels = simulate ? [] : this._sheet.getColumnData(2);
		let highestIntCode = codes.reduce((prev, cur) => { 
			const num = Number.parseInt(cur);
			return Math.max(prev, isNaN(num) ? 0 : num); 
		}, 0);

		// Process all lines and add to new array
		const processedCodes = [];
		const processedLabels = [];
		const updatedCodes = [];
		const missingCodes = [];
		const missingLabels = [];
		const duplicateLabels = [];
		const duplicateCodes = [];
		let duplicatesDropped = false;
		let warnings = [];
		let rowIdx = yOffset;
		for (const line of lines) {
			let thisDuplicateDropped = false;
			const parts = line.explode(delimiter, 2);
			const origCode = parts[0].trim();
			let code = this._sanitizeCode(origCode);
			let origLabel = (parts[1] ?? '').trim();
			let warning = [];
			if (origCode == '' && origLabel == '') {
				// Nothing to do (simulation mode only)
				rowIdx++;
				continue;
			}
			if (parts.length == 1) {
				// Only one part. 
				// If the original code is matching the expectations, treat it as code.
				// Otherwise, treat it as label and set code the next highest integer.
				if (code != origCode) {
					origLabel = origCode;
					code = ''+(++highestIntCode);
					warning.push([1, rowIdx]);
				}
				else {
					// Is this code a number?
					const num = Number.parseInt(code);
					if (!isNaN(num)) {
						// It's a number. Check if it is lower than or equal to the highest number.
						// If so, then it must be a duplicate and must be replaced.
						// Otherwise, set it as the new highest number.
						if (num <= highestIntCode) {
							code = ''+(++highestIntCode);
							updatedCodes.push({ orig: origCode, added: code });
						}
						else {
							highestIntCode = num;
						}
					}
					else {
						// Check for duplicate code
						if (code != '' && (codes.includes(code) || processedCodes.includes(code))) {
							duplicateCodes.push(code);
							code = ''+(++highestIntCode);
							updatedCodes.push({ orig: origCode, added: code });
						}
					}
					warning.push([2, rowIdx]);
				}
			}
			else {
				// Check for duplicate code; when label is identical, do not add (continue)
				const codesIdx = codes.indexOf(code);
				if (codesIdx > -1) {
					if (labels[codesIdx] == origLabel) {
						duplicatesDropped = true;
						thisDuplicateDropped = true;
						warning.push([1, rowIdx], [2, rowIdx]);
						if (!simulate) continue;
					}
				}
				const processedCodesIdx = processedCodes.indexOf(code);
				if (processedCodesIdx > -1) {
					if (processedLabels[processedCodesIdx] == origLabel) {
						duplicatesDropped = true;
						thisDuplicateDropped = true;
						warning.push([1, rowIdx], [2, rowIdx]);
						if (!simulate) continue;
					}
				}
				if (!thisDuplicateDropped) {
					if (codesIdx > -1 || processedCodesIdx > -1) {
						// Duplicate code, but different label
						if (code != '') duplicateCodes.push(code);
						code = ''+(++highestIntCode);
					}
					if (code != origCode) {
						updatedCodes.push({ orig: origCode, added: code });
						warning.push([1, rowIdx]);
					}
				}
				// Update highestIntCode
				const num = Number.parseInt(code);
				if (!isNaN(num) && num > highestIntCode) {
					highestIntCode = num;
				}
			}
			if (code == '') {
				warning.push([1, rowIdx]);
				missingCodes.push(rowIdx + 1);
			}
			if (origLabel == '') {
				warning.push([2, rowIdx]);
				missingLabels.push(rowIdx + 1);
			}
			else {
				// Duplicate label?
				if (origLabel != '' && (labels.includes(origLabel) || processedLabels.includes(origLabel))) {
					duplicateLabels.push(origLabel);
					warning.push([2, rowIdx]);
				}
			}
			processedCodes.push(code);
			processedLabels.push(origLabel);
			if (warning.length > 0) warnings.push(...warning);
			rowIdx++;
		}
		// Combine processed codes and labels
		const processed = processedCodes.map((code, idx) => 
			`${code}\t${this._sanitizeLabelForCSV(processedLabels[idx])}`);
		return { 
			data: processed.join('\n'),
			updatedCodes: updatedCodes,
			warnings: warnings.filter((w, idx) => warnings.findIndex(w2 => w[0] == w2[0] && w[1] == w2[1]) == idx),
			duplicatesDropped: duplicatesDropped,
			duplicateLabels: duplicateLabels,
			duplicateCodes: duplicateCodes,
			missingCodes: missingCodes.sort((a, b) => a - b),
			missingLabels: missingLabels.sort((a, b) => a - b)
		};
	},
	_sanitizeCode: function(code) {
		return (''+code).split('').map(char => char.match(/[a-zA-Z0-9_]/) ? char : '_').join('');
	},
	_sanitizeLabelForCSV: function(label) {
		return '"'+label.replace(/"/g, '""')+'"';
	},
	copy: function(choices) {
		return JSON.parse(JSON.stringify(choices));
	},
	_enableSpreadsheet: function(state) {
		this._$dlg.find('.jexcel_content')[state ? 'removeClass' : 'addClass']('disabled');
		// Ensure the spreadsheet has (keyboard) focus
		jspreadsheet.current = this._sheet;
	},
	_highlightCell: function(cell) {
		const id = this._getCellNameFromCoords(cell);
		const resetId = {};
		resetId[id] = '';
		this._sheet.resetStyle(resetId, true);
		this._sheet.setStyle(id, 'background-color', 'var(--color-rc-lightorange)', null, true);
	},
	_unhighlightCell: function(cell) {
		const id = this._getCellNameFromCoords(cell);
		const resetId = {};
		resetId[id] = '';
		this._sheet.resetStyle(resetId, true);
	},
	_getCellNameFromCoords:  function(xOrCell, maybeY) {
		let x, y;
		if (Array.isArray(xOrCell)) {
			x = Number.parseInt(xOrCell[0]);
			y = Number.parseInt(xOrCell[1]);
		}
		else {
			x = Number.parseInt(xOrCell);
			y = Number.parseInt(maybeY);
		}
		// Convert x to Excel-style column name (A, B, ... AA, AB, ...)
		let colName = '';
		while (x > 0 || colName == '') {
			colName = String.fromCharCode(x % 26 + 65) + colName;
			x = Math.floor(x / 26);
		}
		return colName + (y + 1);
	}
	//#endregion
};

/**
 * Adds a warning icon and a tooltip to fields with deactivated action tags
 */
function addDeactivatedActionTagsWarning() {
	$('.frmedit.actiontags').each(function() {
		const $this = $(this).find('div').first();
		const text = $this.html();
		if (text.includes('@DEACTIVATED-ACTION-TAGS') && $this.find('.deactivated-at-warning').length == 0) {
			$this.prepend('<i class="deactivated-at-warning fa-solid fa-triangle-exclamation text-rc-danger me-1" data-bs-toggle="tooltip" title="Some action tags have been deactivated."></i>');
			initTooltips($this);
		}
	});
}


//#region Copy Section Header
/**
 * Show copy section header dialog and ask for target field
 * @param {string} field 
 */
function copySectionHeader(field) {
	// Make a list of all qualifying fields
	const selection = [
		'<select id="section-header-target">',
		'<option value="" selected="selected">-- '+lang.random_02 +' --</option>'
	];

	$('table.frmedit_tbl[data-matrix-group-name=""]').each(function () {
		const field_name = trimDesign(this.id);
		// Skip record id field
		if (field_name == table_pk) return;
		// Skip section headers
		if (field_name.endsWith('-sh')) return;
		// Skip if field already has a section header
		if ($('table#'+padDesign(padSH(field_name))).length > 0) return;
		// Get (abbreviated) field label
		let field_label = $(this).find('[data-mlm-field="'+field_name+'"][data-mlm-type="label"]').text() ?? '';
		if (field_label.length > 20) field_label = field_label.substring(0, 17) + '&hellip;';
		// Add to list
		selection.push('<option value="' + field_name + '"><b>' + field_name + '</b> - ' + field_label + '</option>');
	});
	selection.push('</select>');
	// Create dialog (and apply select2)
	const $container = $('#volatile-dialogs-container').length == 0 
	? $('<div id="volatile-dialogs-container"></div>').appendTo('body')
	: $('#volatile-dialogs-container');
	$container.find('#copy-section-header-dialolg').remove();
	const $content = $('<div id="copy-section-header-dialog"></div>').html(interpolateString(lang.design_1158, [field]) + selection.join('')).appendTo($container);
	$content.find('#section-header-target').select2();
	$content.find('#section-header-target').next().addClass('mt-1');
	// Show the dialog
	simpleDialog(
		$content,
		lang.design_1157,
		null,
		650,
		null,
		lang.global_53,
		() => copySectionHeaderDo(field, $('#section-header-target').val()),
		lang.design_1159
	);
}
/**
 * Copy a section header to another field on the form
 * @param {string} srcField 
 * @param {string} targetField 
 */
function copySectionHeaderDo(srcField, targetField) {
	const form = getParameterByName('page');
	showProgress(1);
	$.post(app_path_webroot+"Design/copy_section_header.php?pid="+pid, {
		form: form,
		srcField: srcField,
		targetField: targetField
	}, function (data) {
		if (data == "1") { // Success
			reloadDesignTable(form, '', false);
		}
		else {
			// Failed
			showProgress(0);
			const msg = data == "0" ? lang.design_1144 : data;
			showToast(lang.global_01, msg, 'error');
		}
	});
}

//#endregion


//#region Convert/Merge fields into a matrix group

function convertToMatrix() {
	const selectedArray =  [];
	$('.qef-field-selected').each(function() {
		const name = trimDesign($(this).attr('id') ?? '');
		selectedArray.push(name);
	});
	const fields = selectedArray.join(', ');
	simpleDialog(interpolateString(lang.design_1130, [fields]), lang.design_1129, null, 550, null, lang.global_53, () => convertToMatrixDo(fields), lang.design_1131);
}

function convertToMatrixDo(fields) {
	showProgress(1);
	$.post(app_path_webroot+"Design/convert_to_matrix.php?pid="+pid, {
		fields: fields,
		form: getParameterByName('page')
	},
	function (data) {
		if (data == "1") { // Successfully converted
			$('.qef-field-selected').popover('dispose');
			$('.qef-field-selected').removeClass('qef-field-selected');
			reloadDesignTable(getParameterByName('page'), () => {
				for (const field of fields.split(', ')) {
					highlightTable(padDesign(field), 2000);
				}
			});
		}
		else {
			// Failed
			showProgress(0);
			showToast(lang.global_01, lang.design_1133, 'error');
		}
	});
}

//#endregion



// For survey projects, display message that primary key field will not be displayed on survey page
function showPkNoDisplayMsg() {
	// If not a survey or if table_pk not in this form, then stop here
	if (surveys_enabled == 0 || !$('table#draggable #'+table_pk+'-tr .pkNoDispMsg').length) return;
	// Hide all first
	$('.pkNoDispMsg').hide();
	// Now display the message for the first row in the table (might not be the primary key field if reordering has been done)
	$('table#draggable tr:first .pkNoDispMsg').html(lang.design_392 + '<br>' + lang.design_792).show();
}

// When using auto-variable naming, converts field label into variable
function filterMatrixGroupName(name) {
	var name = filterFieldName(trim(name));
	// Force 60 chars or less (and then remove any underscores on end)
	if (name.length > 60) {
		name = filterFieldName(name.substr(0,60));
	}
	return name;
}

// Display the dialog of matrix example pictures
function showMatrixExamplePopup() {
	$.get(app_path_webroot+'Design/matrix_examples.php?pid='+pid, {  }, function(data){
		$('#matrixExamplePopup').html(data);
		$('#matrixExamplePopup').dialog({ bgiframe: true, modal: true, width: 790, height: 700,
			buttons: {
				Close: function() { $(this).dialog('close'); }
			}
		});
	});
}

// Perform matrix group name check for unique name (save the matrix group via Ajax, if specified)
function checkMatrixGroupName(saveMatrix,current_field,next_field) {
	if (saveMatrix == null) saveMatrix = false;
	var ob = $('#grid_name');
	// Trim it
	ob.val(trim(ob.val()));
	// ALSO do unique check on all variable names (if saving matrix group here)
	var checkVarNames = new Array();
	var i = 0;
	if (saveMatrix) {
		var old_matrix_field_names = $('#old_matrix_field_names').val().split(',');
		// Loop through all matrix variable names
		$('#addMatrixPopup .field_name_matrix').each(function(){
			if (!in_array($(this).val(), old_matrix_field_names)) {
				checkVarNames[i] = $(this).val();
				i++;
			}
		});
	}
	// Set vars to know what to check (matrix group name, field names, or both)
	var checkGridName = (ob.val() != $('#old_grid_name').val()) ? 1 : 0;
	var checkFieldNames = (checkVarNames.length > 0) ? 1 : 0;
	// Do AJAX request to do unique check on grid_name OR on variables names (if any are new) OR both
	if (checkGridName || checkFieldNames) {
		// Check if grid_name does not exist already. If so, then return error
		$.get(app_path_webroot+'Design/check_matrix_group_name.php',{ checkFieldNames: checkFieldNames, fieldNames: checkVarNames.join(','), checkGridName: checkGridName, pid: pid, grid_name: ob.val(), old_grid_name: $('#old_grid_name').val() },function(data){
			var json_data = jQuery.parseJSON(data);
			if (json_data.length < 1) {
				alert(woops);
				return false;
			}
			// Determine action
			if (json_data.matrixGroup == '1') {
				// Matrix group already exists
				simpleDialog('"'+ob.val()+'" '+lang.survey_460);
				ob.css({'background-color':'#FFB7BE','font-weight':'bold'});
				saveMatrix = false;
			} else if (json_data.fieldNames != '0' && json_data.fieldNames != '') {
				// Field name(s) already exist
				simpleDialog(lang.survey_461+"<br><br>"+lang.survey_462+"<br> - <b>"+json_data.fieldNames.replace(/,/ig,"</b><br> - <b>")+"</b>");
				saveMatrix = false;
			}
			if (saveMatrix) {
				// Save matrix via Ajax
				saveMatrixAjax(current_field,next_field);
			}
		});
	}
	else {
		if (saveMatrix) {
			// Save matrix via Ajax
			saveMatrixAjax(current_field,next_field);
		}
		else {
			// Reset split
			$('input#split_matrix').val(0);
		}
	}
}

// Field name unique check + enable auto-variable naming (if set)
function field_name_trigger(field_name_ob, field_label_ob) {
	// Do some variable name checking (for uniqueness and format) when leaving the field
	field_name_ob.blur(function(){
		// Prevent auto variable from overwriting variable name given
		field_label_ob.unbind();
		// Re-enable auto var suggest again if no variable is given
		if ($(this).val().length < 1) {
			enableAutoVarSuggest(field_name_ob, field_label_ob);
		}
		// Check non-Latin characters
		if (checkIsTwoByte($(this).val())) {
			simpleDialog(lang.design_79);
			$(this).val('');
			return;
		}
		// Check if valid and unique variable name
		if (field_name_ob.attr('id') == 'field_name') {
			checkFieldName($(this).val(),false);
		} else {
			checkMatrixFieldName(field_name_ob);
		}
	});
}

function addAnotherRowMatrixPopup(numRowsOrJsonData = null) {
	let existing = false;
	let new_rows;
	if (numRowsOrJsonData && numRowsOrJsonData.field_names && Array.isArray(numRowsOrJsonData.field_names)) {
		existing = true;
		new_rows = numRowsOrJsonData.field_names.length;
	} else {
		if (numRowsOrJsonData == null) {
			new_rows = 1;
		} else {
			new_rows = numRowsOrJsonData;
		}
	}
	let originalRow = $('.addFieldMatrixRow:first').html();
	let html = "";
	if (existing) {
		$('.addFieldMatrixRowParent').empty();
		numRowsOrJsonData.field_names.forEach(function (fieldName) {
			let rowHtml = originalRow;
			rowHtml = rowHtml.replace(/name=['"]addFieldMatrixRow-varname_[^'"]*['"]/g, `name="addFieldMatrixRow-varname_${fieldName}"`);
			html += "<tr class='addFieldMatrixRow'>" + rowHtml + "</tr>";
		});
	} else {
		originalRow = originalRow.replace(/name=['"]addFieldMatrixRow-varname_[^'"]*['"]/g, `name="addFieldMatrixRow-varname_"`);
		for (var k = 1; k <= new_rows; k++) {
			html += "<tr class='addFieldMatrixRow'>" + originalRow + "</tr>";
		}
	}
	// Add rows to dialog
	$('.addFieldMatrixRowParent').append(html);
	// Reset dialog height (if needed)
	fitDialog($('#addMatrixPopup'));
}

// Save auto_variable_naming value via AJAX
function save_auto_variable_naming(val,id) {
	$.post(app_path_webroot+'Design/set_auto_var_naming_ajax.php?pid='+pid, { auto_variable_naming: val }, function(data){
		if (data != '1') {
			alert(woops);
			window.location.reload();
		}
		// Enable auto-var naming again (if set) on appropriate fields
		if (id == 'auto_variable_naming_matrix') {
			// Unbind anything on all the label/variable input
			$('#addMatrixPopup .field_name_matrix').unbind();
			$('#addMatrixPopup .field_labelmatrix').unbind();
			// Put focus back on EACH name so it will auto name each, if no var is defined yet
			matrix_field_name_trigger();
			// Loop through each matrix row and put focus on label for any without a var name defined
			for (var k = 0; k < $('#addMatrixPopup .field_labelmatrix').length; k++) {
				field_name_ob  = $('#addMatrixPopup .field_name_matrix').eq(k);
				field_label_ob = $('#addMatrixPopup .field_labelmatrix').eq(k);
				if (field_name_ob.val().length < 1) {
					field_label_ob.focus();
				}
				field_name_ob.focus();
			}
			// Set object for showing saved status
			var savedStatusOb = $('#auto_variable_naming_matrix_saved');
		} else {
			enableAutoVarSuggest($('#field_name'), $('#field_label'));
			// Put focus back on label so it will auto name, if no var is defined yet
			if (val && $('#field_name').val() == '') {
				$('#field_label').trigger('click').focus();
			}
			// Set object for showing saved status
			var savedStatusOb = $('#auto_variable_naming_saved');
		}
		// Show saved status
		savedStatusOb.css('visibility','visible');
		setTimeout(function(){
			savedStatusOb.css('visibility','hidden');
		},2500);
	});
}

// Remove any illegal characters from REDCap variable names
function filterFieldName(temp) {
	temp = trim(temp);
	temp = temp.toLowerCase();
	temp = temp.replace(/[^a-z0-9]/ig,"_");
	temp = temp.replace(/[_]+/g,"_");
	while (temp.length > 0 && (temp.charAt(0) == "_" || temp.charAt(0)*1 == temp.charAt(0))) {
		temp = temp.substr(1,temp.length);
	}
	while (temp.length > 0 && temp.charAt(temp.length-1) == "_") {
		temp = temp.substr(0,temp.length-1);
	}
	return temp;
}

// For matrix popup fields, check if field name exists and make any corrections
function checkMatrixFieldName(ob)
{
	// Reset bg color
	ob.css('background-color','#ffffff');
	// Trim and make sure it's not blank
	ob.val( trim(ob.val()) );
	if (ob.val().length == 0) return false;
	// Remove any illegal characters
	ob.val( filterFieldName(ob.val()) );
	// Make sure it's not a reserved variable
	var is_reserved = in_array(ob.val(), reserved_field_names);
	if (is_reserved) {
		setTimeout(function(){
			simpleDialog('"'+ob.val()+'" '+lang.survey_463,null,'illegalFieldErrorDialog');
		},50);
		ob.css('background-color','#FFB7BE');
		return false;
	}
	// Check non-Latin characters
	if (checkIsTwoByte(ob.val())) {
		simpleDialog(lang.design_79);
		return false;
	}
	// Make sure the field name doesn't conflict with others in the same matrix dialog popup
	if (!checkFieldUniqueInSameMatrix(ob)) {
		simpleDialog(interpolateString(lang.design_1255, [ob.val()]));
		ob.css('background-color','#FFB7BE');
		return false;
	}
	// Loop through all matrix variable names and see which ones are newly added (since we opened the popup)
	if (!in_array(ob.val(), $('#old_matrix_field_names').val().split(','))) {
		// Check if grid_name does not exist already. If so, then return error
		$.get(app_path_webroot+'Design/check_matrix_group_name.php',{ checkFieldNames: 1, fieldNames: ob.val(), pid: pid },function(data){
			var json_data = jQuery.parseJSON(data);
			if (json_data.length < 1) {
				alert(woops);
				return false;
			}
			// Determine action
			if (json_data.fieldNames != '0' && json_data.fieldNames != '') {
				// Field name(s) already exist
				simpleDialog(
					interpolateString(lang.design_1252, [json_data.fieldNames]),
					null,
					'fieldExistsErrorDialog'
				);
				ob.css('background-color','#FFB7BE');
				return false;
			}
		});
	}
	// Detect if longer than 26 characters. If so, give warning but allow.
	if (ob.val().length > 26) {
		simpleDialog(lang.design_1253, null, 'variable-name-too-long-warning');
	}
	// If variable name changed, then make sure the matrix variable name input field's "name" attribute also changes accordingly
	let varNamePrefix = 'addFieldMatrixRow-varname_';
	let previousVar = ob.attr('name').replace(varNamePrefix,'');
	let currentVar = ob.val();
	if (previousVar != currentVar) {
		ob.attr('name', varNamePrefix + currentVar);
	}
}

//Check if field name exists (when editing field via AJAX) and make any corrections
function checkFieldName(temp,submitForm)
{
	// Set save button to show progress spinner
	if (submitForm && $('#div_add_field').hasClass('ui-dialog-content')) {
		var saveBtn = $('#div_add_field').dialog("widget").find(".ui-dialog-buttonpane button").eq(1);
		var saveBtnHtml = saveBtn.html();
		var cancelBtn = $('#div_add_field').dialog("widget").find(".ui-dialog-buttonpane button").eq(0);
		saveBtn.html('<img src="'+app_path_images+'progress_circle.gif"> '+lang.designate_forms_21);
		cancelBtn.prop('disabled',true);
		saveBtn.prop('disabled',true);
	}

	var performSubmit = function(){
		REDCap.beforeAddFieldFormSubmit();
		document.addFieldForm.submit();
	}

	// If a section header, then ignore field name and just submit, if saving field
	if ($('#field_type').val() == 'section_header' && submitForm) {
		performSubmit();
	}
	// Initialize and get current field name before changed
	old_field_name = $('#sq_id').val();
	document.getElementById('field_name').style.backgroundColor='#FFFFFF';
	// Make sure value is not empty
	temp = trim(temp);
	if (temp.length == 0) return false;
	document.getElementById('field_name').value = temp;
	// Detect if an illegal field name ('redcap_event_name' et al)
	if (!(status > 0 && $('#field_name').attr('readonly'))) { // If already in production with a reserved name, do not give alert (it's too late).
		// Remove any illegal characters
		document.getElementById('field_name').value = temp = filterFieldName(temp);
		// Check if has a reserved name
		var is_reserved = in_array(temp, reserved_field_names);
		if (is_reserved) {
			setTimeout(function(){
				simpleDialog('"'+temp+'" '+lang.survey_463,null,'illegalFieldErrorDialog',null,"document.getElementById('field_name').focus();");
			},50);
			document.getElementById('field_name').style.backgroundColor='#FFB7BE';
			// Set save button back to normal if clicked Save button
			if (submitForm) {
				saveBtn.html(saveBtnHtml);
				cancelBtn.prop('disabled',false);
				cancelBtn.removeAttr('disabled');
				saveBtn.prop('disabled',false);
				saveBtn.removeAttr('disabled');
			}
			return false;
		}
	}

	// Make sure this variable name doesn't already exist
	if (temp != old_field_name) {
		// Make ajax call
		$.get(app_path_webroot+'Design/check_field_name.php', { pid: pid, field_name: temp, old_field_name: old_field_name },
			function(data) {
				if (data != '0') {
					// Set save button back to normal if clicked Save button
					if (submitForm) {
						saveBtn.html(saveBtnHtml);
						cancelBtn.prop('disabled',false);
						cancelBtn.removeAttr('disabled');
						saveBtn.prop('disabled',false);
						saveBtn.removeAttr('disabled');
					}
					simpleDialog(
						interpolateString(lang.design_1252, [temp]),
						null,
						'fieldExistsErrorDialog',
						null,
						() => document.getElementById('field_name').focus()
					);
					document.getElementById('field_name').style.backgroundColor='#FFB7BE';
				} else {
					//Detect if longer than 26 characters. If so, give warning but allow.
					if (!submitForm && temp.length > 26 && !$('#field_name').prop('readonly')) {
						simpleDialog(lang.design_1253, null, 'variable-name-too-long-warning');
					}
					// Submit form if specified.
					if (submitForm) performSubmit();
				}
			}
		 );
	} else {
		// Submit form if specified.
		if (submitForm) performSubmit();
	}
}

// Determine if file is image based upon filename (using file extension)
function is_image(filename) {
	if (typeof filename == 'undefined' || filename === false || filename == '') return false;
	var dot = filename.lastIndexOf(".");
	if( dot == -1 ) return false;
	var extension = filename.substr(dot+1,filename.length).toLowerCase();
	var all_img_extensions = new Array("jpeg", "jpg", "gif", "png", "bmp");
	return (in_array(extension, all_img_extensions));
}

// Enable/disable the radio button to specify field attachment as an image or link
function enableAttachImgOption(filename,set_display_option,force_enable) {
	if (force_enable == null) force_enable = false;
	var is_img_or_pdf = is_image(filename) || getfileextension(filename.toLowerCase()) == 'pdf';
	var enable = (force_enable || (filename != '' && is_img_or_pdf) || set_display_option == '2');
	if (enable) {
		// Image - give choice to display as image or link
		$('#div_img_display_options').fadeTo(0, 1);
		$('#edoc_img_display_link').prop('disabled',false);
		$('#edoc_img_display_image').prop('disabled',false);
		$('#edoc_img_display_audio').prop('disabled',false);
		set_display_option = (set_display_option != null) ? set_display_option : '1';
		if (set_display_option == '2') {
			$('#edoc_img_display_link').prop('checked',false);
			$('#edoc_img_display_image').prop('checked',false);
			$('#edoc_img_display_audio').prop('checked',true);
			$('#edoc_img_display_image').prop('disabled',true);
		} else if (set_display_option == '1') {
			$('#edoc_img_display_link').prop('checked',false);
			$('#edoc_img_display_image').prop('checked',true);
			$('#edoc_img_display_audio').prop('checked',false);
			$('#edoc_img_display_audio').prop('disabled',true);
		} else {
			$('#edoc_img_display_image').prop('checked',false);
			$('#edoc_img_display_link').prop('checked',true);
			$('#edoc_img_display_audio').prop('checked',false);
			$('#edoc_img_display_audio').prop('disabled',is_img_or_pdf);
			$('#edoc_img_display_image').prop('disabled',!is_img_or_pdf);
		}
	} else {
		// File - force it as link only
		$('#div_img_display_options').fadeTo(0, 0.3);
		$('#edoc_img_display_link').prop('checked',true);
		$('#edoc_img_display_image').prop('checked',false);
		$('#edoc_img_display_audio').prop('checked',false);
		$('#edoc_img_display_link').prop('disabled',true);
		$('#edoc_img_display_image').prop('disabled',true);
		$('#edoc_img_display_audio').prop('disabled',true);
	}
}

// Select/deselect all stop action checkboxes in popup dialog
function selectAllStopActions(select_all) {
	select_chkbox = (select_all ? true : false);
	$('#stop_actions_checkboxes input[type="checkbox"]').each(function(){
		$(this).prop('checked', select_chkbox);
	});
}

// Set stop actions
function setStopActions(field_name) {
	$.post(app_path_webroot+'Design/stop_actions.php?pid='+pid, { field_name: field_name, action: 'view' }, function(data){
		if (data != '0') {
			$('#stop_action_popup').html(data);
			$('#stop_action_popup').dialog({ bgiframe: true, modal: true, width: 670, open: function(){fitDialog(this)},
				buttons: {
					Close: function() { $(this).dialog('close'); },
					Save: function() {
						var codes = new Array();
						var i = 0;
						$('#stop_actions_checkboxes input[type="checkbox"]').each(function(){
							if ($(this).prop('checked')) {
								codes[i] = $(this).val();
								i++;
							}
						});
						$.post(app_path_webroot+'Design/stop_actions.php?pid='+pid, { field_name: field_name, action: 'save', codes: codes.join(',') }, function(data){
							if (data != '0') {
								$('#stop_action_popup').dialog('close');
								// Reload whole table with new values (easy way)
								reloadDesignTable(getParameterByName('page'));
							} else {
								alert(woops);
							}
						});
					}
				}
			});
		} else {
			alert(woops);
		}
	});
}

// Update the Advanced Branching Syntax with drag-n-drop fields
function updateAdvBranchingBox() {
	var advBranching = '';
	var operator = $('input:radio[name=brOper]:checked').val();
	var thisVal = '';
	var selectVal = '';
	$('#dropZone1 li').each(function(){
		var thisString = $(this).attr('val');
		// For text fields, add the user-defined operator and value
		if ($(this).children('input').length) {
			thisString = thisString.substring(0,thisString.indexOf("= "+lang.design_411));
			selectVal = $(this).children('select').val();
			// If using > or <, then don't add quotes around it
			if (selectVal.indexOf(">") > -1 || selectVal.indexOf("<") > -1) {
				thisVal = $(this).children('input').val();
				if (!isNumeric(thisVal)) thisVal = "'"+thisVal+"'";
			} else {
				thisVal = "'"+$(this).children('input').val()+"'";
			}
			// Add to string
			thisString += selectVal+' '+thisVal;
		}
		// Add and/or first
		if (advBranching != '') {
			advBranching += ' '+operator+' ';
		}
		// Now add equal and value
		advBranching += thisString;
	});
	$('#advBranchingBox').val(advBranching);
	setTimeout(function(){
		$('#advBranchingBox').effect('highlight', { }, 2000);
	},100);
}

// Select drag-n-drop logic builder
function initDragDropBuilderDlg() {
	$('input[name="optionBranchType"][value="drag"]').click(function(){
		showProgress(1);
		$.post(app_path_webroot+'Design/branching_logic_builder.php?pid='+pid, { form_name: getParameterByName('page'), field_name: $('#logic_builder').attr('field'), action: 'view' }, function(data){
			if (data != '0') {
				showProgress(0,1);
				// Add dialog content
				document.getElementById('logic_builder_drag').innerHTML = data;
				fitDialog($('#logic_builder'));
				// Enable drag-n-drop
				$('.dragrow').draggable({ helper: 'clone', cursor: 'move', stack: '.dragrow' });
				$('#dropZone1').droppable({
					drop: function(event, ui) {
						var innerDisp = $(ui.draggable).html();
						//If for a "text/slider" field, give drop-down and text box to allow user to define value
						if (innerDisp.indexOf("= "+lang.design_411) > 0) {
							innerDisp = innerDisp.substring(0,innerDisp.indexOf("= "+lang.design_411));
							innerDisp += ' <select onchange="updateAdvBranchingBox();"><option value=">"> \> </option><option value=">="> \>= </option><option value="=" selected> = </option>'
									  +  '<option value="<="> \<= </option><option value="<"> \< </option><option value="<>"> \<\> </option></select>'
									  +  ' <input onchange="updateAdvBranchingBox();" type="text" size="5">';
						}
						$(this).append('<li class="brDrag" style="display:block;" val="'+$(ui.draggable).attr('val')+'">'+innerDisp
									 + ' <a href="javascript:;"><img onclick=\'$(this.parentNode.parentNode).remove();setTimeout(function(){updateAdvBranchingBox()},10);\' src="'+app_path_images+'cross.png"></a></li>');
						// Loop through all choices that have been dragged and build advanced logic syntax
						updateAdvBranchingBox();
					}
				});
				// Now that dialog is loaded, hide/show the choice set as chosen
				chooseBranchType('drag',true);
			} else {
				showProgress(0,1);
				alert(woops);
			}
		});
	});
}

// Open the dialog for Logic Builder
function openLogicBuilder(field_name, quick_logic = '') {
	$('#logic_builder').attr('field', field_name);
	$.post(app_path_webroot+'Design/branching_logic_builder.php?pid='+pid, { field_name: $('#logic_builder').attr('field'), action: 'get-logic', quick_logic: quick_logic }, function(data){
		if (data != '0') {
			var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
			if (json_data.length < 1) {
				showProgress(0,1);
				alert(woops);
				return;
			}
			$('#advBranchingBox').val(json_data.logic);
			$('#logic_builder_label').html(json_data.label);			
			$('#logicTesterRecordDropdown2').val(''); 
			$('#advBranchingBox_res').html('');
			$('#logic_builder_drag').fadeTo(0, 0.3);
			$('#logic_builder_field').html(field_name);
			$('input[name="optionBranchType"][value="advanced"]').click();
			$('#logic_builder').dialog({ bgiframe: true, modal: true, width: 810, open: function(){fitDialog(this)},
				buttons: [{
					text: window.lang.global_53,
					click: function() { $(this).dialog("close"); }
				},{
					html: '<b>'+lang.designate_forms_13+'</b>',
					click: function() {
						// Do quick format checking of branching logic for any possible errors
						$('#advBranchingBox').val(trim($('#advBranchingBox').val()));
						var brStr = $('#advBranchingBox').val();
						var branchingErrorsExist = checkBranchingCalcErrors(brStr,true,true);
						if (branchingErrorsExist) {
							return false;
						}
						// Validate fields and save it
						var branching_logic = $('#advBranchingBox').val();
						var any_same_logic_fields_val = false;
						var saveBranchingLogic = function () {
							var branching_logic = $('#advBranchingBox').val();
							var field_name = $('#logic_builder').attr('field');
							$.post(app_path_webroot + 'Design/branching_logic_builder.php?pid=' + pid, {
								branching_logic: branching_logic,
								field_name: field_name,
								any_same_logic_fields: any_same_logic_fields_val,
								action: 'save'
							}, function (data) {
								if (data.length > 1) {
									simpleDialog(data, lang.survey_471);
								} else if (data == '1' || data == '2' || data == '3' || data == '4') {
									if ($('#logic_builder').hasClass('ui-dialog-content')) $('#logic_builder').dialog('close');
									// Add new logic onto field for display
									if (field_name !== '--QUICK-EDIT--') {
										var branching_logic_trun = (branching_logic.length > 65) ? branching_logic.substring(0, 63) + "..." : branching_logic;
										$('#bl-log_' + field_name).html(branching_logic_trun);
										$('#bl-label_' + field_name).css('visibility', ($('#advBranchingBox').val().length > 0 ? 'visible' : 'hidden')); // Show/hide 'branching logic exits' label
										highlightTable('design-' + field_name, 2000); // Highlight row
									}
									else {
										$('textarea#qebl-custom').val(branching_logic);
										$('.quick-edit-options-dialog').trigger('change');
									}
									// If returned 2 or 4, then OK but display msg about disabling auto-question numbering
									if (data == '2' || data == '4') simpleDialog(lang.design_1257, lang.global_03);
									// If returned 3, then OK but display msg to super user that errors existed
									if ((data == '3' || data == '4') && super_user_not_impersonator) simpleDialog(lang.design_441, lang.survey_471);

									if (any_same_logic_fields_val) reloadDesignTable(getParameterByName('page'));
								} else {
									alert(woops);
								}
							});
						};

						var checkBranchingLogicStatus = function () {
							//If checkbox has been clicked update status to do not show alert anymore
							if($('#branching_update_chk').is(':checked')) {
								$.post(app_path_webroot+'Design/branching_logic_builder.php?pid='+pid, { field_name: field_name, action: 'logic-alert-status' }, function(data){
									saveBranchingLogic();
								});
							}else{
								saveBranchingLogic();
							}
							$('#branching_update').dialog('close');
						};

						var any_same_logic_fields = {
							simpleDialogYes: function() {
								any_same_logic_fields_val = true;
								checkBranchingLogicStatus();
							},
							simpleDialogNo: function() {
								any_same_logic_fields_val = false;
								checkBranchingLogicStatus();
							}
						};

						if(json_data.logic != branching_logic && json_data.same_logic_field == true && json_data.branching_status == "0") {
							$('#branching_update').dialog({ bgiframe: true, modal: true, width: 550, open: function(){fitDialog(this)},
								buttons: [{
									text: interpolateString(lang.design_1381, [json_data.num_same_logic_field - 1]),
									click: function() { any_same_logic_fields.simpleDialogYes();}
								},{
									text: design_99,
									click: function() { any_same_logic_fields.simpleDialogNo();}
								}]
							});
							$('#branching_update').dialog("widget").find(".ui-dialog-buttonpane button:eq(1)").css({'font-weight':'bold', 'color':'#333'});
							$('#branching_update').dialog("widget").find(".ui-dialog-buttonpane button:eq(1)").focus();
						} else {
							saveBranchingLogic();
						}
					}
				}]
			});
			// Now that dialog is loaded, hide/show the choice set as chosen
			chooseBranchType('advanced',false);
		} else {
			showProgress(0,1);
			alert(woops);
		}
	});
}

// User chooses type of branching building option to use, so fade/disable the other option
function chooseBranchType(val,do_highlight) {
	if (val == 'advanced') {
		// Chose advanced syntax
		$('#logic_builder_advanced').fadeTo(0, 1);
		if (do_highlight) $('#logic_builder_advanced').effect('highlight', {}, 1000);
		$('#logic_builder_drag').fadeTo(0, 0.3);
		$('#advBranchingBox').prop('readonly',false);
		$('#advBranchingBox').removeAttr('readonly');
		$('input:radio[name=brOper]').prop('disabled',true);
		$('.dragrow').draggable("option", "disabled", true);
		$('#brFormSelect').prop('disabled',true);
		$('#dropZone1').html('');
		$('#linkClearAdv').css('visibility','visible');
		$('#linkClearDrag').css('visibility','hidden');
	} else {
		// Chose drag-n-drop
		if (convertAdvBranching()) {
			$('#logic_builder_drag').fadeTo(0, 1);
			if (do_highlight) $('#logic_builder_drag').effect('highlight', {}, 1000);
			$('#logic_builder_advanced').fadeTo(0, 0.3);
			$('#advBranchingBox').prop('readonly',true);
			$('input:radio[name=brOper]').prop('disabled',false);
			$('input:radio[name=brOper]').removeAttr('disabled');
			$('.dragrow').draggable("option", "disabled", false);
			$('#brFormSelect').prop('disabled',false);
			$('#brFormSelect').removeAttr('disabled');
			$('#linkClearAdv').css('visibility','hidden');
			$('#linkClearDrag').css('visibility','visible');
		} else {
			// Could not convert from advanced. Give error msg and reselect advanced.
			$('input:radio[name=optionBranchType]').each(function(){
				if ($(this).val() == 'advanced') {
					$(this).prop('checked',true);
				}
			});
			chooseBranchType('advanced',false);
			simpleDialog(lang.survey_474, lang.survey_473);
		}
	}
}

// Convert the advanced branching syntax logic to the drag-n-drop version (might not be compatible)
function convertAdvBranching() {
	$('#advBranchingBox').val( trim($('#advBranchingBox').val()) );
	var logic = $('#advBranchingBox').val();
	// If no logic is defined
	if (logic.length < 1) {
		$('#dropZone1').html('');
		return true;
	}
	// Do basic syntax check first
	if (checkBranchingCalcErrors(logic,false,true)) {
		return false;
	}
	// Check for instances of AND and OR
	var orCount  = logic.split(' or ').length-1;
	var andCount = logic.split(' and ').length-1;
	if (orCount > 0 && andCount > 0) {
		return false;
	}
	// Remove any parentheses inside square brackets so we can check logic for any parentheses (can't have them)
	var logicNoParen = logic, haveLeftSquare = false, haveRightParen = false, leftSquare = 0, bracketField;
	for (var i=0; i<logic.length; i++) {
		if (!haveLeftSquare) {
			if (logic.substr(i,1) == '[') {
				haveLeftSquare = true;
				leftSquare = i;
			}
		} else if (logic.substr(i,1) == ']') {
			// Check if parentheses are inside square brackets
			bracketField = logic.substring(leftSquare,i+1);
			if ((bracketField.split('(').length-1) == 1 && (bracketField.split(')').length-1) == 1) {
				logicNoParen = logicNoParen.replace(/\(/g,'').replace(/\)/g,'');
			}
			// Reset
			haveLeftSquare = false;
		}
	}
	if ((logicNoParen.split('(').length-1) > 0 && (logicNoParen.split(')').length-1) > 0) {
		return false;
	}
	// Go through list of possible choices and make sure all options match
	var allLogicChoices = new Array();
	var allLogicLabelChoices = new Array();
	var i=0;
	$('#nameList li').each(function(){ // Put all <li> elements into an array first for speed
		allLogicChoices[i] = $(this).attr('val');
		allLogicLabelChoices[i] = $(this).text();
		i++;
	});
	// Set operator and check correct operator radio button
	if (orCount > 0) {
		var operator = ' or ';
		$('#brOperOr').prop('checked',true);
	} else if (andCount > 0) {
		var operator = ' and ';
		$('#brOperAnd').prop('checked',true);
	} else {
		var operator = ' and '; //default
	}
	var logicArray = logic.split(operator);
	var thisLogic, foundLogicLabel, foundMatch, thisLogicDC;
	for (var i=0; i<logicArray.length; i++) {
		// Trim syntax values and trade double quote for single quote and put spaces before and after = sign
		thisLogic = trim(logicArray[i].replace(/"/g,"'").replace("]='","] = '").replace("]=","] =").replace("] ='","] = '"));
		// Loop through all available logic choices to choose from
		var foundMatch = false;
		for (var k=0; k<allLogicChoices.length; k++) {
			if (!foundMatch) {
				if (allLogicChoices[k] == thisLogic) {
					// Find literal match first
					foundMatch = true;
					foundLogicLabel = allLogicLabelChoices[k];
				} else {
					// Try replacing strings so we can append " = (define criteria)" and match it with that string appended
					thisLogicDC = thisLogic.replace(" <= "," = ").replace(" >= "," = ").replace(" < "," = ").replace(" > "," = ");
					thisLogicDC = thisLogicDC.substring(0,thisLogicDC.indexOf(" = ")) + " = "+lang.design_411;
					if (allLogicChoices[k] == thisLogicDC) {
						foundMatch = true;
						foundLogicLabel = thisLogic.replace(/\[/g,"").replace(/\]/g,"");
					}
				}
			}
		}
		// Did we find it? If not, exit with error.
		if (foundMatch) {
			$('#dropZone1').append('<li class="brDrag" style="display:block;" val="'+thisLogic+'">'+foundLogicLabel
								 + ' <a href="javascript:;"><img onclick=\'$(this.parentNode.parentNode).remove();setTimeout(function(){updateAdvBranchingBox()},10);\' src="'+app_path_images+'cross.png"></a></li>');
		} else {
			return false;
		}
	}
	// No issues with conversion
	return true;
}

// If form drop-down is displayed in branching logic builder, show only fields for selected form
function displayBranchingFormFields(ob) {
	var form = ob.value;
	$('#nameList li').hide();
	$('#nameList li.br-frm-'+form).show().effect('highlight',{},2500);
}

/**
 * Do quick check if branching logic errors exist in string (not very extensive)
 * @param {string} calcOrLogic 
 * @param {boolean} display_alert 
 * @param {boolean} isBranching 
 * @returns 
 */
function checkBranchingCalcErrors(calcOrLogic, display_alert, isBranching) {
	// Remove comments before checking
	const logicNoComments = calcOrLogic.split('\n').filter(line => !(line.trim().startsWith('//') || line.trim().startsWith('#')) ? line : null).join('\n');
	const msg = [ isBranching 
		? window.lang.design_1056 // Branching logic syntax error(s):
		: window.lang.design_1057 // Syntax error(s) exist in the calculation equation:
	];
	if (logicNoComments.length > 0) {
		// Check symmetry of "
		if ((logicNoComments.split('"').length - 1)%2 > 0) {
			msg.push(window.lang.design_1059); // - Odd number of double quotes exist
		}
		// Check symmetry of '
		if ((logicNoComments.split("'").length - 1)%2 > 0) {
			msg.push(window.lang.design_1060); // - Odd number of single quotes exist
		}
		// Check symmetry of [ with ]
		if (logicNoComments.split("[").length != logicNoComments.split("]").length) {
			msg.push(window.lang.design_1061); // - Square bracket is missing
		}
		// Check symmetry of ( with )
		if (logicNoComments.split("(").length != logicNoComments.split(")").length) {
			msg.push(window.lang.design_1062); // - Parenthesis is missing
		}
	}
	if (msg.length > 1) {
		// Errors exist
		if (display_alert) {
			msg.push('');
			msg.push(window.lang.design_1058); // You must fix all errors listed before you can continue.
			return !alert(msg.join('\n'));
		}
		return true;
	}
	return false;
}

function checkMinMaxVal() {
	if (oldValType != newValType && newValType.substring(0,4) == 'date' && oldValType.substring(0,4) == 'date') {
		// Convert date[time][seconds] to other date format for min/max
		$('#val_min').val( trim($('#val_min').val()) );
		$('#val_max').val( trim($('#val_max').val()) );
		var min = $('#val_min').val();
		var max = $('#val_max').val();
		var minOkay = true;
		var maxOkay = true;
		if (min.length > 0) {
			minOkay = convertDateFormat(min,oldValType,newValType,'val_min');
			if (!minOkay) $('#val_min').blur();
		}
		if (minOkay && max.length > 0) {
			maxOkay = convertDateFormat(max,oldValType,newValType,'val_max');
			if (!maxOkay) $('#val_max').blur();
		}
	}
	oldValType = newValType;
	preCheckValType();
}

function convertDateFormat(val,convertFrom,convertTo,id) {
	// Split into time and date (regardless of which date format used)
	var thisdatetime = val.split(' ');
	var thisdate = thisdatetime[0];
	var thistime = (thisdatetime[1] == null) ? '' : thisdatetime[1];
	// If value has no dashes, then it's not in the right format, so force onblur to alert user.
	if (thisdate.split('-').length != 3) {
		return false;
	}
	// Split the date into components
	var dateparts = thisdate.split('-');
	if (/_ymd/.test(convertFrom)) {
		var mm = dateparts[1];
		var dd = dateparts[2];
		var yyyy = dateparts[0];
	} else if (/_mdy/.test(convertFrom)) {
		var mm = dateparts[0];
		var dd = dateparts[1];
		var yyyy = dateparts[2];
	} else if (/_dmy/.test(convertFrom)) {
		var mm = dateparts[1];
		var dd = dateparts[0];
		var yyyy = dateparts[2];
	}
	// Put the date back together in the new format
	if (/_ymd/.test(convertTo)) {
		thisdate = yyyy+"-"+mm+"-"+dd;
	} else if (/_mdy/.test(convertTo)) {
		thisdate = mm+"-"+dd+"-"+yyyy;
	} else if (/_dmy/.test(convertTo)) {
		thisdate = dd+"-"+mm+"-"+yyyy;
	}
	// Extend the time component into full hh:mm:ss format (even if a date field -> 00:00:00)
	if (thistime.split(':').length == 2) {
		thistime += ':00';
	} else if (thistime == '') {
		thistime = '00:00:00';
	}
	// Now trim down the time component based on which format we're converting to
	if (/datetime_/.test(convertTo) && !/datetime_seconds/.test(convertTo)) {
		thistime = thistime.substring(0,5);
	} else if (/date_/.test(convertTo)) {
		thistime = '';
	}
	// Append time, if exists
	val = trim(thisdate+" "+thistime);
	// Set new value
	$('#'+id).val(val);
	return true;
}

function preCheckValType() {
	$('#val_type').bind('click select', function(){
		oldValType = $(this).val();
		$('#val_type').unbind();
		$('#val_type').bind('change', function(){
			newValType = $(this).val();
			checkMinMaxVal();
		});
	})
}
//Insert row into Draggable table
function insertRow(tblId, current_field, edit_question, is_last, moveToRowAfter, section_header, delete_row) {

	// Remove easter egg field types from drop-down, if field was an easter egg field type
	var selectbox = document.getElementById('field_type');
	for (var i=selectbox.options.length-1; i>=0; i--) {
		if (selectbox.options[i].value == 'sql' || selectbox.options[i].value == 'advcheckbox') {
			setTimeout("document.getElementById('field_type').remove("+i+");",10);
		}
	}

	var txtIndex = document.getElementById('this_sq_id').value;
	if (section_header) {
		if (edit_question) {
			current_field = document.getElementById('sq_id').value;
			txtIndex = current_field + '-sh';
		} else {
			current_field = document.getElementById('this_sq_id').value;
			txtIndex = current_field;
		}
	}
	var tbl = document.getElementById(tblId);
	var rows = tbl.tBodies[0].rows; //getElementsByTagName("tr")

	//Remove a table row, if needed
	// if (deleteRowIndex != null) {
		// for (var i=0; i<rows.length; i++) {
			// if (rows[i].getAttribute("id") == delete_row+"-tr") {
				// document.getElementById('draggable').deleteRow(i);
			// }
		// }
	// }

	//Determine node index for inserting into table
	if (is_last) {
		//Add as last at very bottom
		var rowIndex = rows.length-1;
	} else {
		//Get index somewhere in middle of table
		for (var i=0; i<rows.length; i++) {
			if (rows[i].getAttribute("id") == txtIndex+"-tr") {
				var rowIndex = i;
			}
		}
		//If flag is set, place the new row after the original (rather than before it)
		if (moveToRowAfter) rowIndex++;
	}
	if (document.getElementById('add_form_name') != null && $('#add_form_name').val() != '') {
		window.location.href = app_path_webroot+"Design/online_designer.php?pid="+pid+"&page="+form_name;
	} else {
		$('#add_form_name').val('');
	}
	//Add cell in row. Obtain html to insert into table for the current field being added/edited.
	$.get(app_path_webroot+"Design/online_designer_render_fields.php", { pid: pid, page: form_name, field_name: current_field, edit_question: edit_question, section_header: section_header },
		function(data) {
			//If editing existing question, replace it with itself in table
			if (edit_question) {
				document.getElementById('draggable').deleteRow(rowIndex);
			}
			//Reset and close popup
			resetAddQuesForm();
			//Add new row
			if (section_header) current_field += '-sh';
			const $rowPlaceholder = $(tbl.insertRow(rowIndex));
			const $newRowWrapped = $('<div></div>').html(data);
			$rowPlaceholder.before($newRowWrapped.find('tr[sq_id="'+current_field+'"]'));
			$rowPlaceholder.remove();
			// Extract JSON
			const $fieldTypesJson = $newRowWrapped.find('[data-qef-field-types-json]');
			REDCapQuickEditFields.setFieldTypes(atob($fieldTypesJson.text()) ?? '{}');
			$newRowWrapped.remove();
			// Fix tooltips
			$('tr[sq_id="'+current_field+'"] [data-bs-toggle=tooltip]').each(function() {
				const delay = $(this).attr('data-field-action') == "copy-name" ? 800 : 500;
				new bootstrap.Tooltip(this, {
					html: true,
					trigger: 'hover',
					delay: { "show": delay, "hide": 0 },
					customClass: 'od-tooltip'
				});
			});
			// Initialize all jQuery widgets, etc.
			initWidgets();
			//Highlight table row for emphasis of changes
			if (section_header == 0) highlightTable(padDesign(current_field), 2000);
			initFieldActionTooltips();
			addDeactivatedActionTagsWarning();
			setSurveyQuestionNumbers(current_field);
		}
	);
}

// Show/hide validation min/max when editing field via AJAX
function hide_val_minmax() {
	var this_val = $('#val_type').val();
	if (this_val != '' && this_val != null) {
		// If record auto-numbering is enabled, then don't allow user to choose anything other than "integer" and blank
		if (auto_inc_set && table_pk == $('#field_name').val() && $('#val_type:visible').length && this_val != 'integer') {
			$('#val_type').val('integer');
			document.getElementById("div_val_minmax").style.display = "";
			simpleDialog(lang.design_525);
			return;
		}
		// Get data type of field
		var data_type = $('#val_type option:selected').attr('datatype');
		var minMaxValTypes = new Array('integer', 'number', 'number_comma_decimal', 'time', 'date', 'datetime', 'datetime_seconds');
		if (in_array(data_type, minMaxValTypes)) {
			document.getElementById("div_val_minmax").style.display = "";
			// Change onblur depending on if date, time, or number. Allow "today" and "now" for dates and datetime fields.
			var onblur_min = "if (this.value != 'today' && this.value != 'now' && !(this.value.indexOf('[') === 0 && this.value.indexOf(']') > -1)) { setTimeout(function(){ redcap_validate(document.getElementById('val_min'),'','','hard','"+this_val+"',1); },1); } else { this.style.fontWeight = 'normal'; this.style.backgroundColor='#FFFFFF'; }";
			var onblur_max = "if (this.value != 'today' && this.value != 'now' && !(this.value.indexOf('[') === 0 && this.value.indexOf(']') > -1)) { setTimeout(function(){ redcap_validate(document.getElementById('val_max'),'','','hard','"+this_val+"',1); },1); } else { this.style.fontWeight = 'normal'; this.style.backgroundColor='#FFFFFF'; }";
			document.getElementById('val_min').setAttribute("onblur",onblur_min);
			document.getElementById('val_max').setAttribute("onblur",onblur_max);
		} else {
			document.getElementById("div_val_minmax").style.display = "none";
			// Erase any values inside min/max input
			$('#val_min').val('');
			$('#val_max').val('');
		}
	} else {
		document.getElementById("div_val_minmax").style.display = "none";
		// Erase any values inside min/max input
		$('#val_min').val('');
		$('#val_max').val('');
	}
}

// Open Qu&estion Bank dialog
function openAddQuestionBank(sq_id) {
	sqId = sq_id;
	// AJAX call to get question values for pre-filling
	simpleDialog(null,lang.design_906,"add_fieldbank",1000, "$('#keyword-search-input').val('');$('#classification-list').val('');");
	initFieldBankSelectPicker(true);
	loadQuestionBankResult('', 1);
}

// Open Add Question box
function openAddQuesForm(sq_id,question_type,section_header,signature) {
	//Only super users can add/edit "sql" fields
	if (!super_user_not_impersonator && question_type == 'sql') {
		simpleDialog(lang.survey_476, lang.survey_475);
		return;
	}

	//Reset the form before we open it
	resetAddQuesForm();

	// In case someone changes a Section Header to a normal field, note this in a hidden field
	$('#wasSectionHeader').val(section_header);

	//Open Add Question form
	if (question_type == "" && section_header == 0) {
		document.getElementById('sq_id').value = "";
		document.getElementById("field_name").removeAttribute("readonly");
		// Add SQL field type for super users
		if (super_user_not_impersonator) {
			if ($('#field_type option[value="sql"]').length < 1) $('#field_type').append('<option value="sql">'+lang.survey_477+'</option>');
		}
		// Make popup visible
		openAddQuesFormVisible(sq_id);
		// If we're adding a field before or after a SH, then remove SH as an option in the drop-down Field Type list
		if (sq_id.endsWith('-sh') || $('#'+sq_id+'-sh-tr').length) {
			if ($('#field_type').val() == 'section_header') $('#field_type').val('');
			$('#field_type option[value="section_header"]').wrap('<span>').hide(); // Wrap in span in order to hide for IE/Safari
		}
		// For non-obvious reasons, Firefox 4 will sometimes leave old text inside the textarea boxes in the dialog pop-up when opened,
		// so clear out after it's visible just to be safe.
		document.getElementById('field_label').value = '';
		if (document.getElementById('element_enum') != null) document.getElementById('element_enum').value = '';
		// Customize pop-up window
		$('#div_add_field').dialog('option', 'title', '<span style="color:#800000;font-size:15px;">'+lang.design_57+'</span>');
		if ($('#field_type').val().length < 1) {
			$('#div_add_field').dialog("widget").find(".ui-dialog-buttonpane").hide();
		} else {
			$('#div_add_field').dialog("widget").find(".ui-dialog-buttonpane").show();
		}
		// Call function do display/hide items in pop-up based on field type
		selectQuesType();

	//Pre-fill form if editing question
	} else {
		// If field types "sql", "advcheckbox" (i.e. Easter eggs) are used for this field,
		// add it specially to the drop-down, but do not show otherwise.
		if (question_type == 'sql' || super_user_not_impersonator) {
			if ($('#field_type option[value="sql"]').length < 1) $('#field_type').append('<option value="sql">'+lang.survey_477+'</option>');
		} else if (question_type == 'advcheckbox') {
			if ($('#field_type option[value="advcheckbox"]').length < 1) $('#field_type').append('<option value="advcheckbox">'+lang.survey_478+'</option>');
		}
		// Show the progress bar
		showProgress(1);
		// Set sq_id
		document.getElementById('sq_id').value = sq_id;
		// Set field type ('file' and 'signature' fields are both 'file' types)
		$('#isSignatureField').val(signature);
		if (question_type == 'file') {
			$('#field_type option[value="file"]').each(function(){
				if ($(this).attr('sign') == signature) {
					$(this).prop('selected', true);
				}
			});
		} else {
			document.getElementById('field_type').value = question_type;
		}
		// Call function do display/hide items in pop-up based on field type
		selectQuesType();
		// AJAX call to get question values for pre-filling
		$.get(app_path_webroot+"Design/edit_field_prefill.php", { pid: pid, field_name: sq_id },
			function(data) {
				ques = (typeof(data) == 'object') ? data : JSON.parse(data);

				// Display warning for MYCAP if current field is selected as baseline date field
				if (ques['field_name'] == baseline_date_field) {
					$('#baseline_date_warning').show();
				} else {
					$('#baseline_date_warning').hide();
				}
				//If error occurs, stop here
				if (typeof ques == 'undefined') {
					// Close progress
					$("#fade").removeClass('black_overlay').hide();
					document.getElementById('working').style.display = 'none';
					return;
				}
				// Set field type drop-down vlaue
				if ((question_type == "radio" || question_type == "checkbox") && ques['grid_name'] != '') {
					// If is a Matrix formatted checkbox or radio, set field type drop-down as "in a Matrix/Grid"
					$('#dd_'+question_type+'grid').prop('selected',true);
					// Call this function again to now catch any Matrix specific setup
					selectQuesType();
				}
				// Remove all options in matrix group drop-down (except the first blank option)
				$('#grid_name_dd option').each(function(){
					if ($(this).val().length > 0) {
						$(this).remove();
					}
				});
				// Set matrix group name drop-down options (from adjacent fields)
				var grid_names = ques['adjacent_grid_names'].split(',');
				for (var i=0; i<grid_names.length; i++) {
					$('#grid_name_dd').append(new Option(grid_names[i],grid_names[i]));
				}
				// Set value for either matrix group drop-down or input field (if field's group name exists in drop-down, then select drop-down)
				// if (ques['grid_name'] != '') {
					// $('#grid_name_text').val(ques['grid_name']);
				// }
				// matrixGroupNameTextBlur();
				// Value DOM elements from AJAX response values
				document.getElementById('field_name').value = ques['field_name'];
				document.getElementById('field_note').value = ques['element_note'];
				document.getElementById('field_req').value = ques['field_req'];
				document.getElementById('field_phi').value = ques['field_phi'];
				document.getElementById('custom_alignment').value = (question_type == "slider" && ques['custom_alignment'] == 'RV') ? '' : ques['custom_alignment'];
				if (document.getElementById('video_url') != null) document.getElementById('video_url').value = ques['video_url'];
				if (document.getElementById('video_display_inline1') != null) {
					$('#video_display_inline0').prop('checked', false);
					$('#video_display_inline1').prop('checked', false);
					if (ques['video_display_inline'] == '1') {
						$('#video_display_inline1').prop('checked', true);
					} else {
						$('#video_display_inline0').prop('checked', true);
					}
				}
				$('#field_annotation').val(ques['misc']);
				// Clean-up: Loop through all hidden validation types (listed as not 'visible' from db table) and hide each UNLESS field has this validation (Easter Egg)
				for (i=0;i<valTypesHidden.length;i++) {
					var thisHiddenValType = $('#val_type option[value="'+valTypesHidden[i]+'"]');
					if (valTypesHidden[i].length > 0 && thisHiddenValType.length > 0) {
						thisHiddenValType.remove();
					}
				}
				// Add hidden validation type to drop-down, if applicable
				if (question_type == 'text' && ques['element_validation_type'] != '') {
					if (in_array(ques['element_validation_type'],valTypesHidden)) {
						$('#val_type').append('<option value="'+ques['element_validation_type']+'">'+ques['element_validation_type']+'</option>');
						$('#val_type').val(ques['element_validation_type']);
					}
				}
				//
				if (document.getElementById('question_num') != null) {
					document.getElementById('question_num').value = ques['question_num'];
					REDCapQuickEditFields._fieldTypes[padDesign(ques['field_name'])].questionNum = ques['question_num'];
				}
				if (question_type == "slider") {
					document.getElementById('slider_label_left').value = ques['slider_label_left'];
					document.getElementById('slider_label_middle').value = ques['slider_label_middle'];
					document.getElementById('slider_label_right').value = ques['slider_label_right'];
					document.getElementById('slider_min').value = (isNumeric(ques['element_validation_min']) && isinteger(ques['element_validation_min']*1)) ? ques['element_validation_min'] : 0;
					document.getElementById('slider_max').value = (isNumeric(ques['element_validation_max']) && isinteger(ques['element_validation_max']*1)) ? ques['element_validation_max'] : 100;
					document.getElementById('slider_display_value').checked = (ques['element_validation_type'] == 'number');
				} else {
					if (ques['element_validation_type'] == 'float') {
						ques['element_validation_type'] = 'number';
					} else if (ques['element_validation_type'] == 'int') {
						ques['element_validation_type'] = 'integer';
					}
					document.getElementById('val_type').value = ques['element_validation_type'];
					document.getElementById('val_min').value = ques['element_validation_min'];
					document.getElementById('val_max').value = ques['element_validation_max'];
				}
				if (question_type == "text") {
					if (ques['text_rand_target']==1) {
						// text fields used as rand target may not have validation
						document.getElementById('val_type').value = '';
						document.getElementById('val_min').value = '';
						document.getElementById('val_max').value = '';
						$('#field_type, #val_type, #val_min, #val_max, #ontology_service_select').prop('disabled', true);
						$('#text_rand_target').show();
					} else {
						$('#field_type, #val_type, #val_min, #val_max, #ontology_service_select').prop('disabled', false);
						$('#text_rand_target').hide();
					}
					showHideValidationForMCFields();
				}
				// If has file attachment
				if (question_type == "descriptive" && ques['edoc_id'].length > 0) {
					$('#edoc_id').val(ques['edoc_id']);
					$('#div_attach_upload_link').hide();
					$('#div_attach_download_link').show();
					if (ques['attach_download_link'] != null) {
						$('#attach_download_link').html(ques['attach_download_link']);
						enableAttachImgOption(ques['attach_download_link'],ques['edoc_display_img'],1);
						$('#edoc_id_hash').val(ques['edoc_id_hash']);
					}
					// Disable video url text box
					$('#video_url, #video_display_inline0, #video_display_inline1').prop('disabled', true);
				}
				// If a section header, close out all other fields except Label
				if (section_header) {
					if (document.getElementById('question_num') != null) document.getElementById('question_num').value = '';
					document.getElementById('field_type').value = "section_header";
					selectQuesType();
					document.getElementById('field_name').value = '';
				}
				// Determine if need to show Min/Max validation text boxes
				hide_val_minmax();
				// If field is the Primary Key field, then disable certain attributes in Edit Field pop-up
				if (document.getElementById('field_name').value == table_pk) {
					// Disable certain attributes
					$('#field_type').val('text').prop('disabled', true);
					document.getElementById('div_field_req').style.display='none';
					//document.getElementById('div_field_note').style.display='none';
					document.getElementById('div_custom_alignment').style.display='none';
					document.getElementById('div_pk_field_info').style.display='block';
					if (document.getElementById('div_ontology_autosuggest') != null) {
            document.getElementById('div_ontology_autosuggest').style.display='none';
          }
					// If record auto-numbering is enabled, then disable validation drop-down
					//if (auto_inc_set) $('#val_type').val('').prop('disabled', true);
				}
				// Close progress
				$("#fade").removeClass('black_overlay').hide();
				document.getElementById('working').style.display = 'none';
				//Open Edit Question form
				if (status != 0) {
					document.getElementById("field_name").setAttribute("readonly", "true");
				} else {
					document.getElementById("field_name").removeAttribute("readonly");
				}
				// Open dialog
				openAddQuesFormVisible(sq_id);
				$('#div_add_field').dialog('option', 'title', '<span style="color:#800000;font-size:15px;">'+lang.design_320+'</span>');
				// For non-obvious reasons, Firefox 4 will sometimes clear out the textarea boxes in the dialog pop-up when opened,
				// so pre-fill them after it's visible just to be safe.

				var labelValue
				if (section_header) {
					labelValue = ques['element_preceding_header'];
				} else {
					// Standard label
					labelValue = ques['element_label'];
				}

				if (REDCap.isRichTextFieldLabel(labelValue)) {
					REDCap.initFieldLabel(labelValue);
				} else {
					document.getElementById('field_label').value = labelValue.replace(/<br>/gi,"\n");
				}

			  if (question_type == "text" && ques['element_enum'] != '' && $('#ontology_auto_suggest').length) {
					$('#ontology_auto_suggest').val(ques['element_enum']);
					var firstColon = ques['element_enum'].indexOf(':');
					var service = ques['element_enum'].substring(0, firstColon);
					var category = ques['element_enum'].substring(firstColon + 1)
					showSelectedOntologyProvider(service);
					$('#ontology_service_select').val(service);
					notifyOntologyProviders(service, category);
				} else if (question_type == "slider") {
					document.getElementById('element_enum').value = '';
				} else {
					document.getElementById('element_enum').value = ques['element_enum'];
					// Add raw coded enums to hidden field
					document.getElementById('existing_enum').value = ques['existing_enum'];
				}
				// Run the code to select radios after dialog is set because IE will not set radios correctly when done beforehand
				if (ques['field_req'] == '1') {
					document.getElementById('field_req1').checked = true;
					document.getElementById('field_req0').checked = false;
				} else {
					document.getElementById('field_req0').checked = true;
					document.getElementById('field_req1').checked = false;
				}
				if (ques['field_phi'] == '1') {
					document.getElementById('field_phi1').checked = true;
					document.getElementById('field_phi0').checked = false;
				} else {
					document.getElementById('field_phi0').checked = true;
					document.getElementById('field_phi1').checked = false;
				}
				// Autocomplete for dropdowns
				if (question_type == "select" || question_type == "sql") {
					$('#dropdown_autocomplete').prop('checked', (ques['element_validation_type'] == 'autocomplete'));
				}
				//Disable field_name field if appropriate
				if (status != 0) {
					$.get(app_path_webroot+"Design/check_field_disable.php", { pid: pid, field_name: $('#field_name').val() },
						function(data) {
							if (data == '0') {
								document.getElementById("field_name").removeAttribute("readonly");
							}
						}
					);
				}
			}
		);
	}
}

// Field Embedding: Make an AJAX call to retrieve a list of all embedded fields on this instrument to toggle the button/div for each field
function toggleEmbeddedFieldsButtonDesigner(selector)
{
	// First, reset all
	$('.rc-field-embed-designer').addClass('hide');
	$(selector).each(function(){
		$('.rc-field-embed-designer', this).removeClass('hide');
	});
}

//Make Add/Edit Question form visible
function openAddQuesFormVisible(sq_id) {
	document.getElementById("this_sq_id").value = sq_id;
	$('#div_add_field').dialog({ bgiframe: true, modal: true, width: 1200, open: function(){fitDialog(this)},
		buttons: [
			{ text: window.lang.global_53, click: function () { $(this).dialog('close'); } },
			{ text: lang.designate_forms_13, click: function () {
				// Do quick logic check for calc fields
				if ($('#field_type').val() == 'calc') {
					var eq = $('#element_enum').val();
					var branchingCalcErrorsExist = checkBranchingCalcErrors(eq,true,false);
					if (branchingCalcErrorsExist) {
						return false;
					}
					var fldname = $('#field_name').val();
					// Now validate fields in the equation
					$.post(app_path_webroot+'Design/calculation_equation_validate.php?pid='+pid, { field: fldname, eq: eq }, function(data){
						if (data == '0') {
							alert(woops);
							setTimeout(function(){
								openAddQuesForm(fldname,'calc',0,0);
							},500);
						} else if (data == '2' && super_user_not_impersonator) {
							// Saved the calc, although syntax errors exist, so give super user a message
							simpleDialog(lang.design_453,lang.design_412);
						} else if (data.length > 1) {
							// Give error message and reload the Edit Field dialog so they can fix it.
							// Since an error exists, remove calc equation via ajax to prevent users from injecting invalid syntax.
							simpleDialog(data,lang.design_412,null,null,"openAddQuesForm('"+fldname+"','calc',0,0);eraseCalcEqn('"+fldname+"');");
						}
					});
				}
				// Validate and fix any issues with MC fields' raw values for their choices
				if (checkEnumRawVal($('#element_enum'))) {
					// Save the field
					addEditFieldSave();
				}
			} }
		]
	})
	.dialog("widget").find(".ui-dialog-buttonpane button").eq(1).css({'font-weight':'bold', 'color':'#333'}).end();
}

// Remove calc equation via ajax to prevent users from injecting invalid syntax
function eraseCalcEqn(field) {
	setTimeout(function(){
		$.post(app_path_webroot+'Design/calculation_equation_validate.php?pid='+pid, { field: field, action: 'erase' }, function(data){ });
	},500);
}

// Do checking of values before adding/editing field on Online Form Editor
function addEditFieldSave() {
	if ($('#field_type').val() != '' && $('#field_type').val() != 'section_header' && $('#field_name').val().length < 1) {
		simpleDialog(lang.design_414);
		return false;
	}
	// Prevent section headers from having null value (will cause errors)
	if ($('#field_type').val() == 'section_header' && $('#field_label').val().length < 1) {
		$('#field_label').val(' ');
	}
	// Check if valid and unique variable name. Will submit form if no duplicates exist.
	checkFieldName($('#field_name').val(),true);
}

// Reset values for Add New Field form
function resetAddQuesForm() {
	REDCap.toggleFieldLabelRichText(false);

	document.getElementById('field_label').value = '';
	document.getElementById('field_name').value = '';
	document.getElementById('field_note').value = '';
	document.getElementById('field_annotation').value = '';
	if (document.getElementById('question_num') != null) document.getElementById('question_num').value = '';
	if (document.getElementById('element_enum') != null) document.getElementById('element_enum').value = '';
	if (document.getElementById('val_type') != null) document.getElementById('val_type').value = '';
	if (document.getElementById('val_min') != null) document.getElementById('val_min').value = '';
	if (document.getElementById('val_max') != null) document.getElementById('val_max').value = '';
	document.getElementById('field_phi0').checked = true;
	document.getElementById('field_phi1').checked = false;
	document.getElementById('field_phi').value = '';
	document.getElementById('field_req0').checked = true;
	document.getElementById('field_req1').checked = false;
	document.getElementById('field_req').value = '';
	document.getElementById('edoc_id').value = '';
	document.getElementById('slider_label_left').value = '';
	document.getElementById('slider_label_middle').value = '';
	document.getElementById('slider_label_right').value = '';
	if (document.getElementById('video_url') != null) document.getElementById('video_url').value = '';
	$('#video_url').prop('disabled', false);
	$('#video_display_inline0').prop('disabled', false).prop('checked', true);
	$('#video_display_inline1').prop('disabled', false).prop('checked', false);
	document.getElementById('slider_display_value').checked = false;
	if (document.getElementById('grid_name_text') != null) document.getElementById('grid_name_text').value = '';
	if (document.getElementById('grid_name_dd') != null) {
		document.getElementById('grid_name_dd').value = '';
	}
	document.getElementById('existing_enum').value = '';
	//Other operations
	document.getElementById('div_val_minmax').style.display = 'none';
	if ($('#ontology_auto_suggest').length){
    $('#ontology_auto_suggest').val('');
    try { update_ontology_selection(); }catch(e){ }
  }
	//document.getElementById('addSubmitBtn').disabled = false;
	$('#div_attach_upload_link').show();
	$('#div_attach_download_link').hide();
	$('#attach_download_link').html('');
	enableAttachImgOption('');
	enableAutoVarSuggest($('#field_name'), $('#field_label'));
	if ($('#div_add_field').hasClass('ui-dialog-content')) {
		$('#div_add_field').dialog("widget").find(".ui-dialog-buttonpane").show();
		$('#div_add_field').dialog('destroy');
	}
	// In case SH option was removed from Field Type drop-down, add it back
	$('#field_type option[value="section_header"]').show();
	$('#field_type span').each(function(){
		var opt = $(this).find('option').show();
		$(this).replaceWith(opt);
	});
	// Show/hide divs and enable field type and validation type drop-downs if were disabled when editing PK field
	$('#field_type').prop('disabled', false);
	$('#val_type').prop('disabled', false);
	document.getElementById('div_pk_field_info').style.display='none';
	document.getElementById('div_field_req').style.display='block';
	document.getElementById('div_field_phi').style.display='block';
	document.getElementById('div_field_note').style.display='block';
	document.getElementById('div_custom_alignment').style.display='block';
	document.getElementById('div_autocomplete').style.display='none';
	$('#dropdown_autocomplete').prop('checked', false);
	if (document.getElementById('div_ontology_autosuggest') != null) document.getElementById('div_ontology_autosuggest').style.display='block';
	hideOntologyServiceList();
}

// If only one ontology service exists, auto-select and hide the ontology service drop-down
function hideOntologyServiceList()
{
	if ($('#ontology_service_select').length && $('#ontology_service_select option').length <= 2 && $('#ontology_service_select option[value="BIOPORTAL"]').length) {
		$('#ontology_service_select').val('BIOPORTAL').trigger('change').hide();
	} else {
		$('#ontology_service_select').show();
	}
}

// Auto fill variable name, if empty, based upon label text.
function enableAutoVarSuggest(field_name_ob, field_label_ob) {
	// Only enable if checkbox is set to allow it (based on project-level value)
	if ($('#auto_variable_naming').prop('checked')) {
		// Enable auto var naming (but not if field already exists)
		field_label_ob.bind('click focus keyup',function(){
			if (field_name_ob.attr('id') == 'field_name' && $('#sq_id').val().length < 1) { // i.e. if we are adding a new field
				// Field name input
				field_name_ob.val(convertLabelToVariable($(this).val()));
			} else if (field_name_ob.attr('id') != 'field_name' && !field_name_ob.prop('readonly')) {
				// Matrix field name input
				field_name_ob.val(convertLabelToVariable($(this).val()));
			}
		});
	} else {
		// Prevent auto variable from overwriting variable name given
		field_label_ob.unbind();
	}
}

// When using auto-variable naming, converts field label into variable
function convertLabelToVariable(field_label) {
	var field_name = filterFieldName(trim(field_label));
	// Force 26 chars or less (and then remove any underscores on end)
	if (field_name.length > 26) {
		field_name = filterFieldName(field_name.substr(0,26));
	}
	return field_name;
}

//Select question type from Add/Edit Field box
function selectQuesType() {
	if (document.getElementById('field_type').value.length < 1) {
		// If no field type is selected
		document.getElementById('quesTextDiv').style.visibility='hidden';
		if ($('#div_add_field').hasClass('ui-dialog-content')) $('#div_add_field').dialog("widget").find(".ui-dialog-buttonpane").hide();
		document.getElementById('slider_labels').style.display='none';
		document.getElementById('righthand_fields').style.visibility='hidden';
	} else {
		// If field type has been selected
		if (document.getElementById('div_question_num') != null) document.getElementById('div_question_num').style.display = 'block';
		document.getElementById('quesTextDiv').style.visibility='visible';
		if ($('#div_add_field').hasClass('ui-dialog-content')) $('#div_add_field').dialog("widget").find(".ui-dialog-buttonpane").show();
		document.getElementById('righthand_fields').style.visibility='visible';
		document.getElementById('div_field_req').style.display='block';
		document.getElementById('div_field_phi').style.display='block';
		document.getElementById('div_field_note').style.display='block';
		document.getElementById('div_custom_alignment').style.display='block';
		document.getElementById('div_val_type').style.display='none';
		document.getElementById('slider_labels').style.display='none';
		document.getElementById('div_element_enum').style.display='none';
		document.getElementById('div_element_yesno_enum').style.display='none';
		document.getElementById('div_element_truefalse_enum').style.display='none';
		document.getElementById('div_field_annotation').style.display = 'block';
		document.getElementById('div_custom_alignment_slider_tip').style.display='none';
		document.getElementById('div_mc_slider_help').style.display='none';
		$('#div_img_display_options').fadeTo(0,0.3);
		document.getElementById('div_attachment').style.display='none';
		document.getElementById('field_req0').disabled = false;
		document.getElementById('field_req1').disabled = false;
		$('#test-calc-parent').css('display','none');
		$('#element_enum_Ok').html('');
		$('#req_disable_text').css('visibility','hidden');
		if (document.getElementById('field_type').value == 'yesno') {
			document.getElementById('div_element_yesno_enum').style.display='block';
		} else if (document.getElementById('field_type').value == 'truefalse') {
			document.getElementById('div_element_truefalse_enum').style.display='block';
		} else if (document.getElementById('field_type').value == 'text') {
			document.getElementById('div_val_type').style.display='';
		} else if (document.getElementById('field_type').value == 'advcheckbox' || document.getElementById('field_type').value == 'sql' || document.getElementById('field_type').value == 'radio' || document.getElementById('field_type').value == 'select' || document.getElementById('field_type').value == 'calc' || document.getElementById('field_type').value == 'checkbox') {
			document.getElementById('div_val_type').style.display='none';
			document.getElementById('div_element_enum').style.display='';
			// Advcheckboxes cannot be required (since "unchecked" is technically a real value)
			if (document.getElementById('field_type').value == 'advcheckbox') {
				document.getElementById('field_req0').checked = false;
				document.getElementById('field_req1').checked = false;
				document.getElementById('field_req0').checked = true;
				document.getElementById('field_req0').disabled = true;
				document.getElementById('field_req1').disabled = true;
				$('#req_disable_text').css('visibility','visible');
			}
			// If a calc, change label above choices box
			if (document.getElementById('field_type').value == 'calc') {
				$('#test-calc-parent').css('display','block');
				$('#choicebox-label-mc').css('display','none');
				$('#choicebox-label-sql').css('display','none');
				$('#choicebox-label-calc').css('display','block');
				$('.manualcode-label').css('display','none');
				$('#div_manual_code').css('display','none');
			} else if (document.getElementById('field_type').value == 'sql') {
				$('#choicebox-label-mc').css('display','none');
				$('#choicebox-label-sql').css('display','block');
				$('#choicebox-label-calc').css('display','none');
				$('.manualcode-label').css('display','none');
				$('#div_manual_code').css('display','none');
			} else {
				$('#choicebox-label-mc').css('display','block');
				$('#choicebox-label-sql').css('display','none');
				$('#choicebox-label-calc').css('display','none');
				$('.manualcode-label').css('display','block');
			}
		} else if (document.getElementById('field_type').value == 'section_header') {
			document.getElementById('righthand_fields').style.visibility='hidden';
			if (document.getElementById('div_question_num') != null) document.getElementById('div_question_num').style.display = 'none';
			document.getElementById('div_field_annotation').style.display = 'none';
		} else if (document.getElementById('field_type').value == 'slider') {
			document.getElementById('slider_labels').style.display='block';
			document.getElementById('slider_min').value = '0';
			document.getElementById('slider_max').value = '100';
			// Set default custom alignment to RH
			document.getElementById('custom_alignment').value = 'RH';
			document.getElementById('div_custom_alignment_slider_tip').style.display='block';
			if (isMyCapEnabled == 1 && $('#is_form_enabled_for_mycap').length != 0) {
				document.getElementById('div_mc_slider_help').style.display='block';
			}
		} else if (document.getElementById('field_type').value == 'descriptive') {
			document.getElementById('div_field_req').style.display='none';
			document.getElementById('div_field_phi').style.display='none';
			document.getElementById('div_field_note').style.display='none';
			document.getElementById('div_attachment').style.display='block';
			document.getElementById('div_custom_alignment').style.display='none';
		}
		// Do special things for Matrix radios/checkboxes
		if (($('#field_type').val() == 'radio' || $('#field_type').val() == 'checkbox') && $('#field_type :selected').attr('grid') == '1') {
			// Matrix: Disable custom alignment drop-down
			$('#customalign_disable_text').css('visibility','visible');
			$('#custom_alignment').val('').prop('disabled',true);
		} else {
			// Non-matrix: Re-enable custom alignment drop-down and reset matrix group name
			$('#customalign_disable_text').css('visibility','hidden');
			$('#custom_alignment').prop('disabled',false);
			$('#grid_name_text').val('');
			$('#grid_name_dd').val('');
		}
		// Set value used for differentiating Signature fields from regular File Upload fields
		$('#isSignatureField').val( ($('#field_type').val() == 'file' && $('#field_type :selected').attr('sign') == '1') ? '1' : '0' );
		// Enable auto-complete checkbox for select with autocomplete validate
		$('#dropdown_autocomplete').prop('checked', false);
		if ($('#field_type').val() == 'select' || $('#field_type').val() == 'sql') {
			document.getElementById('div_autocomplete').style.display='block';
		} else {
			document.getElementById('div_autocomplete').style.display='none';
		}
		// Make sure the "test calculation" option gets reset
		$('#logicTesterRecordDropdown').val(''); 
		$('#element_enum_res').html('');
	}
	hideOntologyServiceList();
}

//Delete field from a form and remove row in Draggable table
function deleteField(this_field,section_header,contains_par_info = 0) {
	// Don't allow user to delete if baseline date field - IF MYCAP ENABLED
	if (this_field == baseline_date_field) {
		simpleDialog(lang.mycap_mobile_app_469, lang.mycap_mobile_app_470);
		return;
	}
	if (section_header) {
		simpleDialog(lang.design_330, lang.design_415, null, null, null, lang.global_53,
			() => deleteFieldDo(this_field, section_header), lang.global_19);
	} else {
		let content = interpolateString(lang.design_1249, [this_field]);
		if (contains_par_info == 1) {
			content += "<br><br>"+lang.design_1081;
		}
		simpleDialog(content, lang.design_1248, null, null, null, lang.global_53,
			() => deleteFieldDo(this_field, section_header), lang.global_19);
	}
}

//Delete field from a form and remove row in Draggable table
function deleteFieldDo(this_field,section_header) {
	var fields = this_field.split(", ");
	$.post(app_path_webroot + "Design/delete_field.php?pid="+pid, {
			field_names: fields,
			section_header: section_header,
			form_name: getParameterByName('page')
		},
		function (data) {
			$('.qef-field-selected').popover('dispose');
			const responseCodes = JSON.parse(data);
			if (responseCodes.includes(4)) { // Table_pk was deleted, so inform user of this
				update_pk_msg(false, 'field');
			} else if (responseCodes.includes(2)) { // All fields were deleted so redirect back to previous page
				simpleDialog(lang.design_420, null, null, null, 'window.location.href = app_path_webroot + "Design/online_designer.php?pid=" + pid;');
				return // return to skip the reloadDesignTable() call below 
			} else if (responseCodes.includes(5)) { // Field is last on page and has section header, which was then removed. Alert user of SH deletion and reload table.
				simpleDialog(lang.design_421);
			} else if (responseCodes.includes(6)) { // Field is being used by randomization. So prevent deletion.
				simpleDialog(lang.design_422);
				return // return to skip the reloadDesignTable() call below 
			} else {
				// 1 or 3, the reloadDesignTable() call at the bottom covers these cases
			}
			// Send AJAX request to check if the deleted field was in any calc equations or branching logic
			if (!section_header) {
				$.post(app_path_webroot + "Design/delete_field_check_calcbranch.php?pid=" + pid, {field_names: fields}, function (data) {
					if (data != "") {
						if (!$('#delFldChkCalcBranchPopup').length) $('body').append('<div id="delFldChkCalcBranchPopup" style="display:none;"></div>');
						$('#delFldChkCalcBranchPopup').html(data);
						$('#delFldChkCalcBranchPopup').dialog({
							bgiframe: true, modal: true, width: 650, open: function () {
								fitDialog(this)
							},
							title: lang.design_423,
							buttons: {
								Close: function () {
									$(this).dialog('close');
								}
							}
						});
					}
				});
			}
			reloadDesignTable(getParameterByName('page'));
		}
	);
}

//#region Copy fields / matrix
/**
 * Copy field(s)
 * @param {string[]} fields 
 */
function copyField(fields) {
	// When only one field is to be copied, execute immediately
	if (fields.length == 1) {
		copyFieldDo(fields[0], 'after-each', false, lang.design_1343);
		return;
	}
	const text = interpolateString(lang.design_1225, [fields.join(', ')]);
	const $content = $('<div></div>');
	$content.append('<div>'+text+'</div>');
	// Check if matrix fields (or fields from different matrix groups) are mixed in with other fields
	const mixedCollector = {};
	for (const field of fields) {
		const fi = REDCapQuickEditFields.getFieldInfo(field);
		mixedCollector[fi.matrixGroup] = true;
	}
	const mixed = Object.keys(mixedCollector).length > 1;
	if (fields.length > 1) {
		// Additional options for multiple fields
		$content.append('<div class="form-check mt-2"> \
			<input class="form-check-input" type="radio" name="copy-field-mode" id="copy-field-mode-1" value="after-each"'+ (mixed ? ' checked' : '') +'> \
			<label class="form-check-label" for="copy-field-mode-1">' + lang.design_1226 + '</label> \
		</div>');
		$content.append('<div class="form-check"> \
			<input class="form-check-input" type="radio" name="copy-field-mode" id="copy-field-mode-2" value="after-last"'+ (mixed ? ' disabled' : ' checked') +'> \
			<label class="form-check-label" for="copy-field-mode-2">' + interpolateString(lang.design_1227, [fields[fields.length - 1]]) + '</label> \
		</div>');
		$content.append('<div class="form-check mt-3"> \
			<input class="form-check-input" type="checkbox" name="copy-field-select-new" id="copy-field-mode-3" value="select-new"> \
			<label class="form-check-label" for="copy-field-mode-3">' + lang.design_1228 + '</label> \
		</div>');
	}
	simpleDialog($content,lang.design_1222,null,null,null,lang.global_53,() => {
		const mode = fields.length > 1 ? $('input[name="copy-field-mode"]:checked').val() ?? 'after-each' : 'after-each';
		const selectNew = $('input[name="copy-field-select-new"]:checked').length == 1;
		copyFieldDo(fields.join(', '), mode, selectNew, lang.design_1344); 
	}, lang.design_1223);
}
/**
 * Copies an entire matrix group of fields
 * @param {string} mg_name Name of the matrix group
 */
function copyMatrix(mg_name) {
	const content = interpolateString(lang.design_1230, [mg_name]);
	simpleDialog(content,lang.design_1229,null,null,null,lang.global_53,() => {
		copyFieldDo(mg_name, 'copy-matrix', false, lang.design_1345);
	}, lang.design_1231);
}
/**
 * Executes the copy field/matrix action
 * @param {string} fieldNamesOrMatrixName [, ]-separated list of fields (or the matrix name)
 * @param {'after-each'|'after-last'|'copy-matrix'} mode 
 * @param {boolean} selectNew 
 */
function copyFieldDo(fieldNamesOrMatrixName, mode, selectNew, successMsg) {
	showProgress(1);
	$.post(app_path_webroot + "Design/copy_field.php?pid=" + pid, {
			form: getParameterByName('page'),
			fields: fieldNamesOrMatrixName,
			mode: mode
	}, function (data) {
		reloadDesignTable(getParameterByName('page'), () => {
				showToast(lang.design_1346, successMsg, 'success', 3000);
				data.split(',').map(id => id.trim()).forEach(id => {
					highlightTable(padDesign(id), 2000);
				});
				// Replace selection?
				if (data != '0' && selectNew) {
					REDCapQuickEditFields.setSelection(data.split(',').map(id => id.trim()), false);
				}
			}, false);
	});
}
//#endregion

// Reloads table via AJAX on Online Form Editor page
function reloadDesignTable(form_name, js, clearQuickActions = true) {
	$("div.popover").popover('dispose');
	resetAddQuesForm();
	showProgress(1);
	$.get(app_path_webroot+'Design/online_designer_render_fields.php', { pid: pid, page: form_name, ordering: 1 },
		function(data) {
			document.querySelector('#draggablecontainer_parent').innerHTML = data
			// Initialize all jQuery widgets, etc.
			initWidgets();
			showDescriptiveTextImages();
			showPkNoDisplayMsg();
			showProgress(0);
			// Set autocomplete for BioPortal ontology search for ALL fields on a page
			initAllWebServiceAutoSuggest();
			// Enable drop-down autocomplete
			enableDropdownAutocomplete();
			// Initialize field action tooltips
			initFieldActionTooltips();
			addRecordIdFieldNote();
			REDCapQuickEditFields.setFieldTypes();
			setSurveyQuestionNumbers();
			updateOnlineDesignerSectionNav();
			// Eval some Javascript if provided
			if (typeof js == 'string') {
				eval(js);
			}
			else if (typeof js == 'function') {
				js();
			}
			// Refresh multiple fields selection (give new fieldinfo a chance to be loaded
			// before refreshing the Quick modify field(s) widget)
			setTimeout(() => {
				REDCapQuickEditFields.refresh(clearQuickActions);
			}, 0);
		}
	);
}

function initFieldActionTooltips() {
	$('.od-tooltip').remove();
	$('[data-bs-toggle="tooltip"].field-action-link, [data-bs-toggle="tooltip"].field-action-item, [data-bs-toggle="tooltip"].info-item').each(function() {
		const delay = $(this).attr('data-field-action') == "copy-name" ? 800 : 500;
		new bootstrap.Tooltip(this, {
			html: true,
			trigger: 'hover',
			delay: { "show": delay, "hide": 0 },
			customClass: 'od-tooltip'
		});
	});
}


// Delete an entire matrix group of fields
function deleteMatrix(current_field,grid_name) {
	// Remove "-sh" from field name (if has a section header)
	if (current_field.indexOf("-sh") > -1) {
		current_field = current_field.substr(0, current_field.length-3);
	}
	const delMatrixMsg = interpolateString(lang.design_1251, [grid_name]);
	simpleDialog(interpolateString(lang.design_1251, [grid_name]), lang.design_324,null,null,null,lang.global_53,() => deleteMatrixDo(current_field, grid_name), lang.global_19);
}

// Delete an entire matrix group of fields
function deleteMatrixDo(current_field,grid_name) {
	// Do ajax call to delete the whole matrix group
	$.post(app_path_webroot+'Design/delete_matrix.php?pid='+pid,{ field_name: current_field, grid_name: grid_name}, function(data){
		var json_data = jQuery.parseJSON(data);
		if (json_data.length < 1) {
			alert(woops);
			reloadDesignTable(getParameterByName('page'));
		} else {
			// Highlight rows
			for (var i = 0; i < json_data.fields.length; i++) {
				highlightTable(padDesign(json_data.fields[i]),2000);
			}
			setTimeout(function(){
				reloadDesignTable(getParameterByName('page'));
			},1000);
			if (json_data.pk_changed == '1') update_pk_msg(false,'field');
		}
	});
}

// Open dialog for adding matrix of fields (Online Designer)
var next_item_is_sh = false;
function openAddMatrix(current_field,next_field) {
	// Set default value
	var sectionHeaderPreFill = '';
	next_item_is_sh = false;

	if (next_field.indexOf("-sh") > -1) {
		// Remove "-sh" from field name (if adding before a section header)
		next_field = next_field.substr(0, next_field.length-3);
		next_item_is_sh = true;
	} else if (next_field.indexOf("{") > -1) {
		// If adding field at end of form, set field name as blank
		next_field = '';
	} else if (current_field == '' && $('#'+next_field+'-sh-tr').length) {
		// Check if field
		sectionHeaderPreFill = trim($('#'+next_field+'-sh-tr').text());
	}
	if (current_field.indexOf("-sh") > -1) {
		// Remove "-sh" from field name (if adding before a section header)
		current_field = current_field.substr(0, current_field.length-3);
	} else if (current_field.indexOf("{") > -1) {
		// If adding field at end of form, set field name as blank
		current_field = '';
	}
	// Erase any values in the popup currently
	resetAddMatrixPopup(sectionHeaderPreFill);
	$('#sq_id').val(current_field);
	$('#this_sq_id').val(next_field);
	// EDIT EXISTING FIELD: If editing a field, then pre-load pop-up with existing values
	if (current_field != '') {
		// Ajax call to get existing matrix fields' values to pre-fill popup
		$.post(app_path_webroot+'Design/edit_matrix_prefill.php?pid='+pid,{ field_name: current_field}, function(data){
			var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
			if (json_data.length < 1) {
				alert(woops);
				return;
			}
			// Add set number of rows to popup
			addAnotherRowMatrixPopup(json_data);
			// Preload dialog with values
			$('#element_enum_matrix').val(json_data.choices.replace(/\|/g,"\n"));
			$('#grid_name').val(json_data.grid_name);
			$('#old_grid_name').val(json_data.grid_name);
			$('#old_matrix_field_names').val(json_data.field_names.join(','));
			$('#field_type_matrix').val(json_data.field_type);
			$('#section_header_matrix').val(br2nl(json_data.section_header));
			$('#field_rank_matrix').prop('checked',(json_data.field_ranks == 1));
			// Loop through labels
			var counter = 0;
			$('.addFieldMatrixRowParent input.field_labelmatrix').each(function(){
				$(this).val(json_data.field_labels[counter]);
				counter++;
			});
			// Loop through variable names
			var counter = 0;
			$('.addFieldMatrixRowParent input.field_name_matrix').each(function(){
				$(this).val(json_data.field_names[counter]);
				// If we're in production and fields already exist in prod
				if (status > 0 && json_data.fields_in_production.length > 0 && in_array(json_data.field_names[counter], json_data.fields_in_production)) {
					$(this).prop('readonly',true);
					$(this).attr('onfocus','chkVarFldDisabled(this)');
				}
				counter++;
			});
			// Loop through field reqs
			var counter = 0;
			$('.addFieldMatrixRowParent input.field_req_matrix').each(function(){
				$(this).prop('checked',(json_data.field_reqs[counter] == 1));
				counter++;
			});
			// Loop through ques nums
			var counter = 0;
			$('.addFieldMatrixRowParent input.field_quesnum_matrix').each(function(){
				$(this).val(json_data.question_nums[counter]);
				counter++;
			});
			// Loop through field annotations (and make sure all field annotation textareas revert to original size)
			var counter = 0;
			$('.addFieldMatrixRowParent textarea.field_annotation_matrix').css('height','22px').each(function(){
				$(this).val(br2nl(json_data.field_annotations[counter]));
				counter++;
			});
			// Now open dialog
			openAddMatrixPopup(current_field,next_field);
			$('#addMatrixPopup').dialog('option', 'title', '<img src="'+app_path_images+'table__pencil.png"> <span style="color:#800000;font-size:15px;">'+lang.design_321+'</span>');
			const $splitButton = $('<button id="splitMatrix" class="ui-button ui-corner-all ui-widget" type="button" style="font-weight:normal;float:left;">'+lang.design_1124+'</button>');
			$splitButton.on('click', function() {
				simpleDialog(lang.design_1126, lang.design_1125, null, null, null, lang.global_53, () => matrixGroupSave(current_field, next_field, true), lang.design_1132);
			});
			if ($('#splitMatrix').length > 0) { 
				$('#splitMatrix').replaceWith($splitButton);
			}
			else {
				$('#addMatrixPopup').dialog("widget").find('.ui-dialog-buttonpane').prepend($splitButton);
			}
		});
	}
	// ADDING NEW FIELD
	else {
		openAddMatrixPopup(current_field,next_field);
		$('#addMatrixPopup').dialog('option', 'title', '<img src="'+app_path_images+'table.png"> <span style="color:#800000;font-size:15px;">'+lang.design_321+'</span>');
		// Make sure all field annotation textareas revert to original size
		$('.addFieldMatrixRowParent textarea.field_annotation_matrix').css('height','22px');
	}
}

// Open popup to move field to another location on form (or on another form)
function moveField(field, grid_name) {
	let move_sh = 0;
	// Remove "-sh" from field name (if has a section header)
	if (field.indexOf("-sh") > -1) {
		field = field.substr(0, field.length-3);
		move_sh = 1;
	}

	// Get dialog content via ajax
	$.post(app_path_webroot+'Design/move_field.php?pid='+pid, { 
		field: field, 
		move_sh: move_sh, 
		grid_name: grid_name, 
		action: 'view' 
	}, function(data) {
		const json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
		if (json_data.length < 1) {
			alert(woops);
			return false;
		}
		// Add dialog content and set dialog title
		$('#move_field_popup').html(json_data.payload);
		// Open the "move field" dialog
		$('#move_field_popup').dialog({ 
			title: json_data.title, 
			bgiframe: true, 
			modal: true, 
			width: 700, 
			open: function() {
				fitDialog(this)
			},
			buttons: [
				{ 
					text: lang.global_53, 
					click: function () { $(this).dialog('close'); } 
				},
				{ 
					text: move_sh == 1 ? lang.design_1333 : lang.design_1334, 
					click: function () {
						// Make sure we have a field first
						if ($('#move_after_field').val() == '') {
							simpleDialog(lang.design_338);
							return;
						}
						$('.qef-field-selected').popover('dispose');
						moveFieldDo(field, grid_name, $('#move_after_field').val(), move_sh, '1', true, function() { 
							$('#move_field_popup').dialog('close'); 
						});
					} 
				}
			]
		});
	});
}
function moveFieldDo(field, grid_name, move_after_field, move_sh, sh_merge_append, notify, onDone) {
	showProgress(1);
	$.post(app_path_webroot+'Design/move_field.php?pid='+pid, { 
		field: field, 
		grid_name: grid_name, 
		move_sh: move_sh,
		action: 'save', 
		move_after_field: move_after_field,
		sh_merge_append: sh_merge_append
	}, function(data) {
		if (data == '0') {
			alert(woops);
		} else if (notify) {
			simpleDialog(data, lang.design_1258, null, 600);
		}
		reloadDesignTable(getParameterByName('page'));
		showProgress(0,0);
		if (typeof onDone == 'function') onDone();
	});

}

// Save the matrix group via ajax
function saveMatrixAjax(current_field,next_field) {

	// Trim and check all values on form
	$('#element_enum_matrix').val(trim($('#element_enum_matrix').val()));
	// Check if choices should be auto-coded
	if (!checkEnumRawVal($('#element_enum_matrix'))) return false;

	// Get values needed and send ajax request
	var form = getParameterByName('page');
	var sh = $('#section_header_matrix').val();
	var field_type = $('#field_type_matrix').val();
	var choices = $('#element_enum_matrix').val();
	var grid_name = $('#grid_name').val();
	var old_grid_name = $('#old_grid_name').val();
	var split_matrix = $('#split_matrix').val();
	var labels = '';
	var fields = {};
	var field_reqs = '';
	var field_ranks = '';
	var field_annotations = '';
    var field_rank = $('#field_rank_matrix').prop('checked') ? '1' : '0';
	var ques_nums = '';
	var missing_labelvar = 0;
	let varNamePrefix = 'addFieldMatrixRow-varname_';
	$('.addFieldMatrixRowParent .addFieldMatrixRow').each(function(){
		var thislabel = trim($(this).find('.field_labelmatrix:first').val());
		var thisvar = trim($(this).find('.field_name_matrix:first').val());
		var thisreq = $(this).find('.field_req_matrix:first').prop('checked') ? '1' : '0';
		var thisquesnum = $(this).find('.field_quesnum_matrix:first').length ? $(this).find('.field_quesnum_matrix:first').val() : '';
		var field_annotation = trim($(this).find('.field_annotation_matrix:first').val());
		var nameAttr = $(this).find('.field_name_matrix:first').attr('name');
		// ensures keys in the `fields` array are unique in order to prevent error
		if (nameAttr === varNamePrefix) {
			nameAttr += thisvar;
		}
		if (thisvar.length < 1) {
			missing_labelvar++;
		} else {
			labels += thislabel+"\n";
			fields[nameAttr] = thisvar;
			field_reqs += thisreq+"\n";
			field_ranks += field_rank+"\n";
			field_annotations += field_annotation+"|-RCANNOT-|";
			ques_nums += thisquesnum+"\n";
		}
	});
	// If any errors, then stop here
	if (field_type.length < 1) {
		simpleDialog(lang.design_425);
		return;
	}
	if (choices.length < 1) {
		simpleDialog(lang.design_426);
		return;
	}
	if (grid_name.length < 1) {
		simpleDialog(lang.design_427);
		return;
	}
	if (missing_labelvar > 0) {
		simpleDialog(lang.design_656);
		return;
	}
	// Check if new matrix is adopting an existing SH
	sectionHeaderAdopt = (current_field == '' && $('#'+next_field+'-sh-tr').length && !next_item_is_sh) ? next_field : '';

	// Save matrix via ajax
	$.post(app_path_webroot+'Design/edit_matrix.php?pid='+pid,{ sectionHeaderAdopt: sectionHeaderAdopt, ques_nums: ques_nums,
		field_reqs: field_reqs, section_header: sh, field_type: field_type, current_field: current_field, next_field: next_field,
		form: form, choices: choices, grid_name: grid_name, old_grid_name: old_grid_name, labels: labels, fields: fields,
		form_name: form_name, field_ranks: field_ranks, field_annotations: field_annotations, split_matrix: split_matrix }, function(data){
		if (data == '1' || data == '2' || data == '3') {
			$('#addMatrixPopup').dialog('close');
			if ($('#add_form_name').length) {
				// Reload page if just created this form
				window.location.href = app_path_webroot+"Design/online_designer.php?pid="+pid+"&page="+form;
			} else {
				// Reload table and highlight newly added rows
				var allfields = Object.values(fields);
				var js= '';
				for (var i = 0; i < allfields.length; i++) {
					js += "highlightTable('design-"+allfields[i]+"',2000);";
				}
				reloadDesignTable(form,js);
				// If changed PK field or disabled auto-question-numbering, give prompts
				if (data == '2') {
					update_pk_msg(false,'field');
				} 
				else if (data == '3') {
					simpleDialog(lang.design_1257, lang.global_03)
				}
			}
		} else {
			alert(woops);
			reloadDesignTable(form);
		}
	});
}

// Make sure the field name doesn't conflict with others in the same matrix dialog popup (return false if duplication occurs)
function checkFieldUniqueInSameMatrix(ob) {
	var countDupl = 0;
	$('#addMatrixPopup input.field_name_matrix').each(function(){
		if (ob.val() == $(this).val()) countDupl++;
	});
	return (countDupl <= 1);
}

// Make sure ALL field names in a matrix group don't conflict with others (return false if duplication occurs).
// Return alert pop-up on first duplication.
function checkFieldUniqueInSameMatrixAll() {
	var duplErrorField = null;
	$('#addMatrixPopup .field_name_matrix').each(function(){
		if (!checkFieldUniqueInSameMatrix($(this))) {
			duplErrorField = $(this);
			return false;
		}
	});
	if (duplErrorField != null) {
		const content = lang.design_1256 + '<br><br>' + interpolateString(lang.design_1255, [duplErrorField.val()]);
		simpleDialog(content, lang.global_01);
		return false;
	}
	return true;
}


// Hide all rows in matrix field popup that have both label and variable inputs empty
function hideBlankMatrixFields() {
	var k;
	var numMatrixRows = $('#addMatrixPopup .addFieldMatrixRow').length;
	if (numMatrixRows == 1) return;
	for (k = numMatrixRows-1; k >= 0; k--) {
		if (trim($('#addMatrixPopup .field_name_matrix').eq(k).val()) == '' && trim($('#addMatrixPopup .field_labelmatrix').eq(k).val()) == '') {
			// Remove this row
			$('#addMatrixPopup .addFieldMatrixRow').eq(k).remove();
			// If there's only one blank row left, then stop
			if ($('#addMatrixPopup .addFieldMatrixRow').length == 1) return;
		}
	}
}

// Enables or disables Ranking checkbox on Add Matrix Field popup
function matrix_rank_disable() {
    // uncheck and disable Ranking checkbox if Multiple Answers (checkbox) is selected
    if ($('#field_type_matrix').val() == 'checkbox') {
		$('#field_rank_matrix').prop('checked', false).prop('disabled', true);
		$('#ranking_option_div').addClass('opacity35');
    }
    // enable Ranking checkbox only if Single Answer (radio) is selected
    else {
		$('#field_rank_matrix').prop('disabled', false);
		$('#ranking_option_div').removeClass('opacity35');
    }
}

function matrixGroupSave(current_field, next_field, split) {
	$('input#split_matrix').val(split ? 1 : 0);
	// Hide all rows that have both label and variable inputs empty
	hideBlankMatrixFields();
	// Make sure any field name doesn't conflict with others in the same matrix dialog popup
	const pass = checkFieldUniqueInSameMatrixAll();
	if (!pass) return false;
	// Check the uniqueness of the matrix group name AND save the matrix (will be done inside that function)
	checkMatrixGroupName(true,current_field,next_field);
}

// Open the Add Matrix dialog pop-up
function openAddMatrixPopup(current_field,next_field) {
	// Open the "add matrix" dialog
	$('#addMatrixPopup').dialog({ bgiframe: true, modal: true, width: 800, open: function(){fitDialog(this)},
		buttons: [
			{ text: lang.global_53, click: function () { $(this).dialog('close'); } },
			{ html: '<b>'+lang.designate_forms_13+'</b>', click: function () {
				matrixGroupSave(current_field, next_field, false);
			} }
		]
	})
	// Enable field name unique check + auto var naming (if set) for EACH matrix row
	matrix_field_name_trigger();
	// Put temporarily focus on each variable name input to turn off auto-var naming if label has already been set
	if (status < 1) {
		$('#addMatrixPopup .field_name_matrix').each(function () {
			$(this).focus();
		});
	}
	// If using checkboxes in matrix, disable ranking option
	matrix_rank_disable();
	// Put cursor in first field label
	$('#addMatrixPopup .field_labelmatrix:first').focus();
}

// Enable field name unique check + auto var naming (if set) for EACH matrix row
function matrix_field_name_trigger() {
	var field_name_ob, field_label_ob;
	// Count matrix rows
	var num_rows = $('#addMatrixPopup .field_labelmatrix').length;
	// Loop through each matrix row and enable each
	for (var k = 0; k < num_rows; k++) {
		field_name_ob  = $('#addMatrixPopup .field_name_matrix').eq(k);
		field_label_ob = $('#addMatrixPopup .field_labelmatrix').eq(k);
		// Add unique check trigger to field_name field
		field_name_trigger(field_name_ob, field_label_ob);
		// Enable auto-var naming (if set)
		enableAutoVarSuggest(field_name_ob, field_label_ob);
	}
}

// Reset all input values in Add Matrix dialog pop-up
function resetAddMatrixPopup(sectionHeaderPreFill) {
	$('#element_enum_matrix').val('');
	$('#grid_name').val('');
	$('#old_grid_name').val('');
	$('#old_matrix_field_names').val('');
	$('#field_type_matrix').val('radio');
	$('#field_rank_matrix').prop('checked', false);
	$('#section_header_matrix').val(sectionHeaderPreFill);
	$('.field_labelmatrix').val('');
	$('.field_name_matrix').val('');
	var labelvar_row_html = $('.addFieldMatrixRowParent .addFieldMatrixRow:first').html();
	$('.addFieldMatrixRowParent').html("<tr class='addFieldMatrixRow'>"+labelvar_row_html+"</tr>");
	$('#addMatrixPopup .field_name_matrix').removeAttr('readonly');
	$('#addMatrixPopup .field_name_matrix').removeAttr('onfocus');
	fitDialog($('#addMatrixPopup'));
}

// Check if non-numeric MC coded values exist and fix
function checkEnumRawVal(ob) {
	var isMatrixEnum = (ob.attr('id') == 'element_enum_matrix');
	var isCheckbox = ((!isMatrixEnum && $('#field_type').val() == 'checkbox') || (isMatrixEnum && $('#field_type_matrix').val() == 'checkbox'));
	var thisval = trim(ob.val());
	// Catch any auto fixes in array
	var choices_fixed = new Array();
	var choices_fixed_labels = new Array();
	var choices_fixed_key = 0;
	var maxExistingEnum = 0;
	// Parse choices and detect if a raw value is missing
	var choices = thisval.split("\n");
	// Check things only for Editing Field popup
	if (!isMatrixEnum) {
		// Do not process if an invalid field type for this or if enum is blank
		var mcfields = new Array('radio','select','checkbox');
		if (thisval.length < 1 || !in_array($('#field_type').val(), mcfields)) {
			return true;
		}
		// Get array of existing enums already saved (returned from ajax request)
		var choices_existing = $('#existing_enum').val().split("|");
		// Get the highest numbered value for the existing choices
		for (var i=0; i<choices_existing.length; i++) {
			var thisexistchoice = trim(choices_existing[i]);
			if (isNumeric(thisexistchoice) && thisexistchoice == round(thisexistchoice) && thisexistchoice*1 > maxExistingEnum*1) {
				maxExistingEnum = thisexistchoice;
			}
		}
	}
	// Now get the highest numbered value for choices that were just entered (see if higher than existing saved choices)
	if (status > 0) {
		// Prod only
		for (var i=0; i<choices.length; i++) {
			var wholeChoiceNumeric = isNumeric(trim(choices[i]));
			if (choices[i].split(",").length > 1 || wholeChoiceNumeric) {
				if (wholeChoiceNumeric) {
					var val = trim(choices[i]);
				} else {
					var valAndLabel = choices[i].split(",", 2);
					var val = trim(valAndLabel[0]);
				}
				var isCheckboxWithDot = (isCheckbox && val.indexOf('.') > -1);
				if (isNumeric(val) && val == round(val) && !isCheckboxWithDot && val*1 > maxExistingEnum*1) {
					maxExistingEnum = val;
				}
			}
		}
	} else {
		var choices_to_be_fixed = 0; // Let's see how many choices are missing a proper key
		for (var i=0; i<choices.length; i++) {
			var wholeChoiceNumeric = isNumeric(trim(choices[i]));
			if (choices[i].split(",").length > 1 || wholeChoiceNumeric) {
				if (wholeChoiceNumeric) {
					var val = trim(choices[i]);
					choices_to_be_fixed++;
				} else {
					var valAndLabel = choices[i].split(",", 2);
					var val = trim(valAndLabel[0]);
				}
				var isCheckboxWithDot = (isCheckbox && val.indexOf('.') > -1);
				if (isNumeric(val) && val == round(val) && !isCheckboxWithDot && val*1 > maxExistingEnum*1) {
					maxExistingEnum = val;
				}
			} else {
				choices_to_be_fixed++;
			}
		}
		// If all choices have been replaced with options that lack keys, then start numbering from 1 again (ABM)
		if (choices.length == choices_to_be_fixed) maxExistingEnum = 0;
	}
	// Loop through each choice in the textbox
	for (var i=0; i<choices.length; i++) {
		if (trim(choices[i]) != "") {
			if (choices[i].split(",").length > 1) {
				// If has one or more commas, then parse it
				var commaPos = choices[i].indexOf(",");
				var val = trim(choices[i].substring(0,commaPos));
				var label = trim(choices[i].substring(commaPos+1));
				// Check if value is numeric, and if not, check if raw value is acceptable as non-numeric, otherwise, prepend new raw value
				if (!isNumeric(val) && !val.match(/^[0-9A-Za-z._\-]*$/)) {
					// If user added a label w/o a raw value, prepend with raw value
					maxExistingEnum++;
					val = maxExistingEnum;
					label = trim(choices[i]);
					// Add to fixed array
					choices_fixed[choices_fixed_key] = val;
					choices_fixed_labels[choices_fixed_key] = label;
					choices_fixed_key++;
				}
			} else {
				// No comma, so it MUST not have a raw value. Give it one.
				// Check if value is numeric
				if (isNumeric(choices[i])) {
					var val = choices[i];
					if (val > maxExistingEnum) maxExistingEnum = val;
				} else {
					maxExistingEnum++;
					var val = maxExistingEnum;
				}
				
				var label = choices[i];
				// Add to fixed array
				choices_fixed[choices_fixed_key] = val;
				choices_fixed_labels[choices_fixed_key] = label;
				choices_fixed_key++;
			}
			// Re-add cleaned line to array
			choices[i] = val+", "+label;
		}
	}
	// Replace the element_enum choices with our newly formatted version
	ob.val(choices.join("\n"));
	// Give extra warning if has duplicate codings
	var dupCodeWarning = '';
	var choices_codes = new Array();
	var choices_labels = new Array();
	var ii = 0, ii2;
	for (var i=0; i<choices.length; i++) {
		var commaPos = choices[i].indexOf(",");
		var val = trim(choices[i].substring(0,commaPos));
		var label = trim(choices[i].substring(commaPos+1));
		if (in_array(val, choices_codes)) {
			ii2 = array_search(val, choices_codes);
			dupCodeWarning += val+', '+choices_labels[ii2]+"<br>"+choices[i]+"<br>";
		} else {
			choices_codes[ii] = val;
			choices_labels[ii] = label;
			ii++;
		}
	}
	if (dupCodeWarning != '') {
		dupCodeWarning = "<div class='red'>"+lang.design_729+"<br><b>"+dupCodeWarning+"</b></div>";
	}
	// If any choice's raw values were fixed or auto-added, then open pop-up
	if (choices_fixed.length > 0) {
		// Set HTML to display the fixed choices
		var choices_fixed_html = "";
		for (var i=0; i<choices_fixed.length; i++) {
			const rawValueSet = interpolateString(lang.design_1254, [
				'<span class="rawVal">'+choices_fixed[i]+'</span>',
				choices_fixed_labels[i].replace(/</g,"&lt;").replace(/>/g,"&gt;")
			]);
			choices_fixed_html += '<div class="mc_raw_val_fix">' + rawValueSet + '</div>';
		}
		// Add formatted choices to box
		$('#element_enum_clone').html(choices_fixed_html);
		$('#element_enum_dup_warning').html(dupCodeWarning);
		// If a checkbox, display extra note about not using decimals in choice values
		if (isCheckbox) {
			$('#checkbox-nodecimal-notice').show();
		} else {
			$('#checkbox-nodecimal-notice').hide();
		}
		// Open pop-up
		$('#mc_code_change').dialog({ bgiframe: true, modal: true, width: 500, open: function(){ },
			buttons: { Close: function() { $(this).dialog('close'); } }
		});
		return false;
	} else if (dupCodeWarning != '') {
		simpleDialog(dupCodeWarning,lang.global_03);
	}
	return true;
}

// If user clicks or focuses on Variable field in Add New Question dialog on Design page, give user warning is field has been disabled and why
function chkVarFldDisabled(ob) {
	const $ob = $(ob);
	if (status > 0 && $ob.attr('readonly')) {
		const varname = $ob.val();
		const copyToClipboardWidget = '<a style="text-decoration:none; background-color:lightyellow;" href="javascript:copyTextToClipboard(\''+varname+'\')">'+varname+'</a>';
		simpleDialog(lang.design_429 + '<p>' + lang.design_1116 + ' '  + copyToClipboardWidget + '</p>',lang.alerts_24,'varnameprod-nochange');
	}
}


// Remove row of label/var input from Add Matrix dialog
function delMatrixRow(ob) {
	if ($('.addFieldMatrixRow').length > 1) {
		var row = $(ob).parent().parent();
		var removeRow = false;
		// Set delay time (ms)
		var delay = 1000;
		// If label and var name are blank, then remove row without prompt
		if (trim(row.find('.field_name_matrix').val()) == '' && trim(row.find('.field_labelmatrix').val()) == '') {
			removeRow = true;
			delay = 600;
		} else if (confirm(lang.design_1248+"\n\n"+lang.design_1250)) {
			removeRow = true;
		}
		if (removeRow) {
			// Highlight row for a split second
			row.find('.field_name_matrix').effect('highlight',{ },delay);
			row.find('.field_labelmatrix').effect('highlight',{ },delay);
			// Remove row
			setTimeout(function(){
				row.remove();
			},delay-500);
		}
	} else {
		simpleDialog(lang.design_315,lang.global_03);
	}
}

// Delete the attachment for image/file attachment fields
function deleteAttachment() {
	simpleDialog(lang.design_203, lang.design_202, null, null, null, lang.global_53,
		() => {
			$('#div_attach_upload_link').show();
			$('#div_attach_download_link').hide();
			$('#edoc_id').val('');
			enableAttachImgOption('');
			$('#video_url, #video_display_inline0, #video_display_inline1').prop('disabled', false).trigger('focus');
		}, lang.global_19);
}

// Open pop-up for uploading documents as image/file attachments
function openAttachPopup() {
	$('#div_attach_doc_in_progress').hide();
	$('#div_attach_doc_success').hide();
	$('#div_attach_doc_fail').hide();
	$("#attachFieldUploadForm").show();
	$('#myfile').val('');
	$('#attachment-popup').dialog({ bgiframe: true, modal: true, width: 400,
		buttons: [
			{ text: window.lang.calendar_popup_01, click: function () { $(this).dialog('close'); } },
			{ text: lang.form_renderer_23, click: function () {
				if ($('#myfile').val().length < 1) {
					alert(lang.design_128);
					return false;
				}
				$(":button:contains('"+lang.form_renderer_23+"')").css('display','none');
				$('#div_attach_doc_in_progress').show();
				$('#attachFieldUploadForm').hide();
				$("#attachFieldUploadForm").submit();
			} }
		]
	});
}

// Loads dialog of existing MC choices to choose from
function existingChoices(is_matrix) {
	if (typeof is_matrix == 'undefined') is_matrix = 0;
	$.get(app_path_webroot+"Design/existing_choices.php?pid="+pid, { is_matrix: is_matrix },function(data){
		var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
		if (json_data.length < 1) {
			alert(woops);
			return false;
		}
		simpleDialog(json_data.content,json_data.title,'existing_choices_popup');
		fitDialog($('#existing_choices_popup'));
	});
}

// Takes the chose MC choice list and moves it to the Choices textarea in the Edit Field popup
function existingChoicesClick(field_name,is_matrix) {
	if (typeof is_matrix == 'undefined') is_matrix = 0;
	var ob = is_matrix ? $('#element_enum_matrix') : $('#element_enum');
    var data = $('#ec_'+field_name).html();
    data = data.replace(/<br>/gi, "\n"); // Replace <br> with \n
    data = data.replace(/&gt;/gi, ">"); // Replace >
    data = data.replace(/&lt;/gi, "<"); // Replace <
    if ($('#existing_choices_popup').hasClass('ui-dialog-content')) $('#existing_choices_popup').dialog('destroy').remove();  // wipes out the dialog box so we lose the data that was there
    ob.val(data).trigger('blur').effect('highlight',{},2500);
}

// Display dialog of SQL field explanation
function dialogSqlFieldExplain() {
	$.get(app_path_webroot+"Design/sql_field_explanation.php?pid="+pid, { },function(data){
		var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
		if (json_data.length < 1) {
			alert(woops);
			return false;
		}
		simpleDialog(json_data.content,json_data.title,'sql_field_popup',850);
		fitDialog($('#sql_field_popup'));
	});
}

// Display dialog of explanation of BioPortal functionality
function displayBioPortalExplainDlg() {
	$.post(app_path_webroot+"Design/get_bioportal_explain_popup.php?pid="+pid, { },function(data){
		var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
		if (json_data.length < 1) {
			alert(woops);
			return false;
		}
		simpleDialog(json_data.content,json_data.title,'get_bioportal_explain_popup',800);
		fitDialog($('#get_bioportal_explain_popup'));
	});
}

// Display dialog for user to obtain a BioPortal token in order to use the functionality
function alertGetBioPortalToken() {
	$.post(app_path_webroot+"Design/get_bioportal_token_popup.php?pid="+pid, { },function(data){
		var json_data = (typeof(data) == 'object') ? data : JSON.parse(data);
		if (json_data.length < 1) {
			alert(woops);
			return false;
		}
		simpleDialog(json_data.content,json_data.title,'get_bioportal_token_popup',600);
		fitDialog($('#get_bioportal_token_popup'));
		$('#bioportal_api_token_btn').button();
	});
}

// Display dialog for user to obtain a BioPortal token in order to use the functionality
function saveBioPortalToken() {
	var bioportal_api_token = trim($('#bioportal_api_token').val());
	if (bioportal_api_token == '') {
		$('#bioportal_api_token').focus();
		return;
	}
	showProgress(1);
	$.post(app_path_webroot+"Design/get_bioportal_token_popup.php?pid="+pid, { bioportal_api_token: bioportal_api_token },function(data){
		var json_data = jQuery.parseJSON(data);
		if (json_data.length < 1) {
			alert(woops);
			return false;
		}
		showProgress(0,0);
		simpleDialog(json_data.content,json_data.title,'get_bioportal_token_popup',600,"if("+json_data.success+"=='1') window.location.reload();");
		$('#bioportal_api_token_btn').button();
	});
}

//Insert row into Draggable table added from Question Bank
function insertRowFromQuestion(tblId, current_field, field_type, this_sq_id, is_last, delete_row) {

	var tbl = document.getElementById(tblId);
	var rows = tbl.tBodies[0].rows;

	//Determine node index for inserting into table
	if (is_last) {
		//Add as last at very bottom
		var rowIndex = rows.length-1;
	} else {
		//Get index somewhere in middle of table
		for (var i=0; i<rows.length; i++) {
			if (rows[i].getAttribute("id") == this_sq_id+"-tr") {
				var rowIndex = i;
			}
		}
	}

	//Add cell in row. Obtain html to insert into table for the current field being added/edited.
	$.get(app_path_webroot+"Design/online_designer_render_fields.php", { pid: pid, page: form_name, field_name: current_field, edit_question: 0, section_header: 0 },
		function(data) {
			//Add new row
			var newRow = tbl.insertRow(rowIndex);
			newRow.setAttribute("id",current_field+"-tr");
			newRow.setAttribute("sq_id",current_field);
			var cell = document.createElement("td");
			cell.innerHTML = "<td>" + data + "</td>";
			cell.style.backgroundColor = "#ddd";
			newRow.appendChild(cell);
			
			// Highlight newly added row for highlighting purpose
			highlightTableRow(padDesign(current_field), 3000);
			//Reset and close popup
			resetAddQuesForm();
			// Initialize all jQuery widgets, etc.
			initWidgets();
			// Quick-edit field(s)
			REDCapQuickEditFields.setFieldTypes();
			initFieldActionTooltips();
			addDeactivatedActionTagsWarning();
			setSurveyQuestionNumbers(current_field);
		}
	);
}

function gotoEmbed(field) {
	const $target = $('div[var="'+field+'"].rc-field-embed-parent-designer');
	const $highlight = $target.parents('table.frmedit_tbl');
	window.scrollTo({
		top: $highlight.offset().top - 50
	});
	for (let i = 0; i < 7; i++) {
		setTimeout(function() {
			$highlight.toggleClass('jump-to-container-highlight');
		}, 100 * i);
	}
	setTimeout(function() {
		$highlight.addClass('jump-to-container-highlight');
	}, 800);
	setTimeout(function() {
		$highlight.removeClass('jump-to-container-highlight');
	}, 1200);
	REDCapQuickEditFields.refresh();
}

function sqlFieldToDQT() {
	const sql = '' + $('#element_enum').val();
	const current = new URL(window.location);
	const url = new URL('ControlCenter/database_query_tool.php', app_path_webroot_full + 'redcap_v' + redcap_version + '/');
	url.searchParams.set('sql-field', sql);
	url.searchParams.set('project-id', pid);
	url.searchParams.set('instrument-name', current.searchParams.get('page') ?? '');
	window.open(url.toString(), '_blank');
}


/**
 * The throttle implementation from underscore.js
 * See https://stackoverflow.com/a/27078401
 * @param {function} func 
 * @param {Number} wait 
 * @param {Object} options 
 * @returns 
 */
function throttle(func, wait, options) {
    var context, args, result;
    var timeout = null;
    var previous = 0;
    if (!options) options = {};
    var later = function() {
    previous = options.leading === false ? 0 : Date.now();
    timeout = null;
    result = func.apply(context, args);
    if (!timeout) context = args = null;
    };
    return function() {
    var now = Date.now();
    if (!previous && options.leading === false) previous = now;
    var remaining = wait - (now - previous);
    context = this;
    args = arguments;
    if (remaining <= 0 || remaining > wait) {
        if (timeout) {
        clearTimeout(timeout);
        timeout = null;
        }
        previous = now;
        result = func.apply(context, args);
        if (!timeout) context = args = null;
    } else if (!timeout && options.trailing !== false) {
        timeout = setTimeout(later, remaining);
    }
    return result;
    };
};

function dismissNewDragAndDropInfo() {
	$.post(app_path_webroot+"Design/quick_update_fields.php?pid="+pid, {
		uiState: 1,
		'dismissed_new_drag_and_drop_info': 1
	});
}

function openChoiceEditor()
{
	var choices = REDCapChoicesEditor.choicesFromText($('#element_enum').val());
	REDCapChoicesEditor.open(choices, {
		onUpdate: function(newChoices) {
			$('#element_enum').val(REDCapChoicesEditor.choicesToText(newChoices));
		}
	});
}

function exportChoiceEditorToCSV(mode)
{
	// Set delimiter
	var delimiter = ','; // default
	if (mode == 'tab') delimiter = '\t';
	else if (mode == 'semicolon') delimiter = ';';
	// Get data from table
	var tbl = $('table.jexcel:visible tr:has(td)').map(function(i, v) {
		var $td =  $('td', this);
		if (i == 0 || $td.eq(2).text().length < 1) return;
		return {
			Code: $td.eq(2).text(),
			Label: $td.eq(3).text()
		}
	}).get();
	var nowChoiceEditor = getCurrentDate('datetime')+'_'+currentTime('both',true).replace(/:/g,"");
	// Download data as CSV file
	downloadCSV(convertToCSV(tbl, delimiter), "choices_"+nowChoiceEditor+".csv");
}

function convertToCSV(data, delimiter) {
	const csvRows = []
	// Extract the keys from the first object as the CSV headers
	const headers = Object.keys(data[0])
	csvRows.push(headers.join(delimiter))
	// Convert each object into a CSV row
	for (const obj of data) {
		const values = headers.map((header) => {
			const escapedValue =
				obj[header] === null || obj[header] === undefined
					? ''
					: String(obj[header]).replace(/"/g, '""')
			return `"${escapedValue}"`
		})
		csvRows.push(values.join(delimiter))
	}
	// Combine all CSV rows into a single string
	return csvRows.join('\n')
}

function downloadCSV(csvContent, fileName, headers = null) {
	const fileType = 'text/csv;charset=utf-8;'
	download(csvContent, { fileName, fileType })
}

const download = (
	text,
	{ fileName = 'download.txt', fileType = 'text/plain' }
) => {
	// Create a Blob with the text content
	const blob = new Blob([text], { type: fileType })
	// Create a temporary URL for the Blob
	const url = URL.createObjectURL(blob)
	// Create a virtual anchor element
	const anchor = document.createElement('a')
	anchor.href = url
	anchor.download = fileName
	// Programmatically trigger a click event on the anchor
	anchor.click()
	// Clean up the temporary URL
	URL.revokeObjectURL(url)
}

function getSectionHeaderTextContentCleaned(element) {
	function traverse(node) {
		let text = '';
		node.childNodes.forEach(child => {
			if (child.nodeType === Node.TEXT_NODE) {
				text += child.nodeValue;
			} else if (child.nodeType === Node.ELEMENT_NODE) {
				text += ' ' + traverse(child);
			}
		});
		return text;
	}
	return traverse(element).replace(/\s+/g, ' ').trim();
}

function updateOnlineDesignerSectionNav() {
	const maxLength = 35;
	const headers = {};
	for (const fieldName in REDCapQuickEditFields._fieldTypes) {
		if (!REDCapQuickEditFields._fieldTypes[fieldName].hasSectionHeader) continue;
		if (fieldName == 'design-'+form_name+'_complete') continue;
		let text = getSectionHeaderTextContentCleaned(document.querySelector('#'+fieldName+'-sh div.sh-content'));
		if (text.length < 1) text = '<i>'+interpolateString(lang.design_1359, [trimDesignSH(fieldName)])+'</i>';
		// Trim to maxLength and add elipsis if longer
		if (text.length > maxLength) {
			text = text.substring(0, maxLength - 3) + '&hellip;';
		}
		headers[fieldName+'-sh'] = text;
	}
	const ul = document.getElementById('online-designer-fn-card-sh-list');
	ul.innerHTML = '';
	const sh = document.getElementById('online-designer-fn-card-sh');
	if (Object.keys(headers).length < 1) {
		sh.classList.add('hidden');
	}
	else {
		sh.classList.remove('hidden');
		for (const fieldName in headers) {
			const li = document.createElement('li');
			li.innerHTML = '<a href="javascript:void(0);" onclick="scrollSectionHeaderIntoView(\''+fieldName+'\');">' + headers[fieldName] + '</a>';
			ul.appendChild(li);
		}
	}
}

function scrollSectionHeaderIntoView(shId) {
	const sh = document.getElementById(shId);
	sh.scrollIntoView();
}

// Show/Hide Validation section for add/edit field. Exa., Hide Validation for text fields if action tag have any MyCap annotations
function showHideValidationForMCFields() {
	if ($("#field_type").val() == 'text') {
		var fieldAnnotation = $("#field_annotation").val();
		// Defined mcFieldAnnotationsList and value will be ["@MC-TASK-UUID", "@MC-TASK-STARTDATE", "@MC-TASK-ENDDATE", "@MC-TASK-SCHEDULEDATE"];
		var mcAnnotationsList = JSON.parse(mcFieldAnnotationsList);

		var result = stringContainsAnyFromList(fieldAnnotation, mcAnnotationsList);

		if (result == true) {
			$("#div_val_type").hide();
		} else {
			$("#div_val_type").show();
		}
	}
}

// Return true if string contain any string from a list
function stringContainsAnyFromList(str, list) {
	return list.some(item => str.includes(item));
}