<?php

use Vanderbilt\REDCap\Classes\ProjectDesigner;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Output minimal HTML5 header and body
print '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
/**
 * Close BODY and HTML
 * @return string 
 */
function close_html5() {
	return '</body></html>';
}
// If project is in production and another user just changed its draft_mode status, don't allow any actions here if not in draft mode
if ($status > 0 && $draft_mode != '1') {
	print "<script type='text/javascript'>window.parent.window.alert(window.parent.window.woops);</script>";
	exit(close_html5());
}	

// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
// Determine if adding to very bottom of table or not
$is_last = ($_POST['this_sq_id'] == "") ? 1 : 0;
// Determine if editing an existing question or not
$_POST['sq_id'] = preg_replace("/[^0-9a-z_]/", "", strtolower($_POST['sq_id']));
$edit_question = ($_POST['sq_id'] == "") ? 0 : 1;
// Determine if a section header rather than a real field
$is_section_header = (isset($_POST['field_type']) && $_POST['field_type'] == "section_header") ? 1 : 0;
// Determine if WAS a section header but was changed to a real field
$was_section_header = (!$is_section_header && isset($_POST['wasSectionHeader']) && $_POST['wasSectionHeader']);
// Default for table row in DOM that should be deleted
$delete_row = "";
// Required Field value
$_POST['field_req'] = ($_POST['field_req'] == "") ? 0 : $_POST['field_req'];
// Clean the variable name
$_POST['field_name'] = preg_replace("/[^0-9a-z_]/", "", strtolower($_POST['field_name']));
// Validate the form name
if (!($status > 0 ? isset($Proj->forms_temp[$_POST['form_name']]) : isset($Proj->forms[$_POST['form_name']]))) {
	// Send back JS error msg
	print  "<script type='text/javascript'>
			window.parent.window.alert('".js_escape($lang['global_01'])."');
			window.parent.window.location.reload();
			</script>";
	exit(close_html5());
}
// Edoc_id value and video url
$_POST['edoc_id']   = (isset($_POST['field_type']) && $_POST['field_type'] == 'descriptive' && is_numeric($_POST['edoc_id'])) ? $_POST['edoc_id'] : "";
$_POST['video_url'] = (isset($_POST['field_type']) && $_POST['field_type'] == 'descriptive' && $_POST['edoc_id'] == '' && $_POST['video_url'] != '') ? trim($_POST['video_url']) : "";
$_POST['video_display_inline'] = (isset($_POST['video_display_inline']) && $_POST['video_display_inline'] == '1') ? '1' : '0';
// Remove any MC choices for descriptive fields and text fields
if ($_POST['field_type'] == 'descriptive' || $_POST['field_type'] == 'text') {
	$_POST['element_enum'] = "";
}

// Edoc image/file attachment display value
$_POST['edoc_display_img'] = ($_POST['field_type'] == 'descriptive' && is_numeric($_POST['edoc_id']) && is_numeric($_POST['edoc_display_img'])) ? $_POST['edoc_display_img'] : 0;
// Check custom alignment
$align_options = array('', 'LV', 'LH', 'RV', 'RH');
$_POST['custom_alignment'] = (isset($_POST['custom_alignment']) && $_POST['field_type'] != 'descriptive' && $_POST['field_type'] != 'section_header' && in_array($_POST['custom_alignment'], $align_options)) ? $_POST['custom_alignment'] : "";
// If field_type is missing, then set as Text field
if (!isset($_POST['field_type']) || (isset($_POST['field_type']) && $_POST['field_type'] == "")) {
	$_POST['field_type'] = 'text';
}
// Ontology auto-suggest: Add ontology name as element_enum attribute
$has_ontology_provider = OntologyManager::hasOntologyProviders();

$enable_ontology_auto_suggest_field = false;
if ($has_ontology_provider && $_POST['field_type'] == "text" && $_POST['ontology_auto_suggest'] != '') {
	$_POST['element_enum'] = $_POST['ontology_auto_suggest'];
	$enable_ontology_auto_suggest_field = true;
}

// If a text field with any kind of date validation with min/max range set, reformat min/max data value to YMD format when saving
if ($_POST['field_type'] == "text" && (substr($_POST['val_type'], -4) == "_mdy" || substr($_POST['val_type'], -4) == "_dmy"))
{
	// Check validation min
	if ($_POST['val_min'] != "" && $_POST['val_min'] != "now" && $_POST['val_min'] != "today" && !(substr($_POST['val_min'], 0, 1) == "[" && substr($_POST['val_min'], -1) == "]")) {
		// If has time component, remove it temporarily to convert the date separately
		$this_date = $_POST['val_min'];
		$this_time = "";
		if (substr($_POST['val_type'], 0, 8) == "datetime") {
			list ($this_date, $this_time) = explode(" ", $_POST['val_min']);
		}
		if (substr($_POST['val_type'], -4) == "_mdy") {
			$_POST['val_min'] = trim(DateTimeRC::date_mdy2ymd($this_date) . " " . $this_time);
		} else {
			$_POST['val_min'] = trim(DateTimeRC::date_dmy2ymd($this_date) . " " . $this_time);
		}
	}
	// Check validation max
	if ($_POST['val_max'] != "" && $_POST['val_max'] != "now" && $_POST['val_max'] != "today" && !(substr($_POST['val_max'], 0, 1) == "[" && substr($_POST['val_max'], -1) == "]")) {
		// If has time component, remove it temporarily to convert the date separately
		$this_date = $_POST['val_max'];
		$this_time = "";
		if (substr($_POST['val_type'], 0, 8) == "datetime") {
			list ($this_date, $this_time) = explode(" ", $_POST['val_max']);
		}
		if (substr($_POST['val_type'], -4) == "_mdy") {
			$_POST['val_max'] = trim(DateTimeRC::date_mdy2ymd($this_date) . " " . $this_time);
		} else {
			$_POST['val_max'] = trim(DateTimeRC::date_dmy2ymd($this_date) . " " . $this_time);
		}
	}
}


// SQL Field: Do extra server-side check to ensure that only super users can add/edit "sql" field types
if ($_POST['field_type'] == 'sql' && !$super_user)
{
	// Send back JS error msg
	print  "<script type='text/javascript'>
			window.parent.window.alert('".js_escape($lang['design_272'])."');
			window.parent.window.location.reload();
			</script>";
	exit(close_html5());
}


// Set slider labels as val type, min, and max
if ($_POST['field_type'] == "slider") {
	$_POST['slider_label_left']   = trim($_POST['slider_label_left']);
	$_POST['slider_label_middle'] = trim($_POST['slider_label_middle']);
	$_POST['slider_label_right']  = trim($_POST['slider_label_right']);
	// Determine how to delimit the enum string
	$_POST['element_enum'] = trim("{$_POST['slider_label_left']} | {$_POST['slider_label_middle']} | {$_POST['slider_label_right']}");
	if ($_POST['element_enum'] == "|  |") $_POST['element_enum'] = "";
	// Set slider display value
	$_POST['val_type'] = ($_POST['slider_display_value'] == 'on') ? 'number' : '';
	// Set defaults for min/max as blank
	$_POST['val_min'] = (isset($_POST['slider_min']) && isinteger($_POST['slider_min']) && $_POST['slider_min'] != "0") ? $_POST['slider_min'] : "";
	$_POST['val_max'] = (isset($_POST['slider_max']) && isinteger($_POST['slider_max']) && $_POST['slider_max'] != "100") ? $_POST['slider_max'] : "";
}
// If a file upload 'signature' field, then add 'signature' as the validation type
elseif ($_POST['field_type'] == "file" && $_POST['isSignatureField'] == '1')
{
	$_POST['val_type'] = 'signature';
}
// Enable auto-complete for drop-downs?
elseif ($_POST['field_type'] == "select" || $_POST['field_type'] == "sql")
{
	$_POST['val_type'] = ($_POST['dropdown_autocomplete'] == 'on') ? "autocomplete" : "";
	// Set defaults for min/max as blank
	$_POST['val_min']  = "";
	$_POST['val_max']  = "";
}
// Make sure only text fields and sliders have any validation/slider labels (could get left over when changing field type)
elseif ($_POST['field_type'] != "text" && $_POST['field_type'] != "slider")
{
	$_POST['val_type'] = "";
	$_POST['val_min']  = "";
	$_POST['val_max']  = "";
}
// Make sure we restore legacy validation values when saving
elseif ($_POST['field_type'] == "text")
{
	if ($_POST['val_type'] == "number") {
		$_POST['val_type'] = "float";
	} elseif ($_POST['val_type'] == "integer") {
		$_POST['val_type'] = "int";
	}
}


// Determine if a section header rather than a real field
$is_section_header = ($_POST['field_type'] == "section_header") ? 1 : 0;

// If using matrix formatting for a radio or checkbox, capture the grid_name
$grid_name = "";
if (($_POST['field_type'] == "checkbox" || $_POST['field_type'] == "radio") &&
	((isset($_POST['grid_name_dd']) && $_POST['grid_name_dd'] != '') || (isset($_POST['grid_name_text']) && $_POST['grid_name_text'] != '')))
{
	$grid_name = ($_POST['grid_name_dd'] != '') ? $_POST['grid_name_dd'] : $_POST['grid_name_text'];
	// Ensure that only specified charcters are allowed
	if ($grid_name == "" || !preg_match("/^[a-z0-9_]+$/", $grid_name)) {
		$grid_name = "";
	}
}



/**
 * anonymous class that observes the designer and reacts (usually with javascript code)
 */
$observer = new class implements SplObserver {
	function update($subject, $event='', $data=[]): void {
		global $lang;
		switch ($event) {
			case ProjectDesigner::NOTIFICATION_FIELD_INSERTED_BELOW_SECTION_HEADER:
				$formName = @$data['form_name'];
				
				exit(sprintf("<script type='text/javascript'>
					window.parent.window.reloadDesignTable('{$formName}');
					</script>").close_html5());
				break;
			case ProjectDesigner::NOTIFICATION_UNDO_PREVIOUS_FIELD_REORDER:
				$formName = @$data['form_name'];
				// If field failed to save, then give error msg and reload form completely
				exit  ("<script type='text/javascript'>
					window.parent.window.alert(window.parent.window.woops);
					window.parent.window.reloadDesignTable('{$formName}');
					</script>".close_html5());
				break;
			case ProjectDesigner::NOTIFICATION_ERROR_SECTION_HEADER_NOT_ATTACHED_TO_FIELD:
				// Prevent user from adding section header as last field
				exit("<script type='text/javascript'>
					window.parent.window.resetAddQuesForm();
					window.parent.window.alert('".js_escape($lang['design_201'])."');
					</script>".close_html5());
				break;
			case ProjectDesigner::NOTIFICATION_FIELD_CHANGED_INTO_SECTION_HEADER:
				$formName = @$data['form_name'];
				// Prevent user from adding section header as last field
				exit("<script type='text/javascript'>
					window.parent.window.reloadDesignTable('{$formName}');
					</script>".close_html5());
				break;
			case ProjectDesigner::NOTIFICATION_SECTION_HEADER_ADDED:
				// update the field_name in POST when a section header is added
				$_POST['field_name'] = @$data['next_field_name'];
			default:
				# code...
				break;
		}
	}
};


// Check if calculation is valid for calc/CALCTEXT/CALCDATE fields to prevent XSS via JS injection by non-super users
$logic = null;
if (!$super_user) {
    // Traditional calc field
    if ($_POST['field_type'] == "calc" && trim($_POST['element_enum']) != '') {
        $logic = LogicParser::removeCommentsAndSanitize($_POST['element_enum']);
    } // CALCTEXT
    elseif ($_POST['field_type'] == "text" && Calculate::isCalcTextField($_POST['field_annotation'])) {
        $_POST['element_type'] = "text";
        $_POST['misc'] = $_POST['field_annotation'];
        $_POST['element_validation_type'] = $_POST['val_type'];
        $logic = Calculate::buildCalcTextEquation($_POST, LogicParser::removeCommentsAndSanitize($_POST['misc'])); // Pass last param as override in case piping has resulted in a static value
    } // CALCDATE
    elseif ($_POST['field_type'] == "text" && Calculate::isCalcDateField($_POST['field_annotation'])) {
        $_POST['element_type'] = "text";
        $_POST['misc'] = $_POST['field_annotation'];
        $_POST['element_validation_type'] = $_POST['val_type'];
        $logic = Calculate::buildCalcDateEquation($_POST, false, LogicParser::removeCommentsAndSanitize($_POST['misc'])); // Pass last param as override in case piping has resulted in a static value
    }
}
if ($logic !== null && !LogicTester::isValid(Piping::pipeSpecialTags($logic, PROJECT_ID, null, null, null, USERID, true, null, null, false, false, false, true))) {
    exit("<script type='text/javascript'>
        window.parent.window.alert('".js_escape($lang['global_01'])."');
        </script>".close_html5());
}


$projectDesigner = new ProjectDesigner($Proj);
$projectDesigner->attach($observer);



/**
 * EDITING EXISTING QUESTION
 * If was a Section Header but it being converted to a real field, then simply ADD as a new field
 */
if ($edit_question && !$was_section_header)
{

	$form_name = $_POST['form_name'] ?? null;
	$field_id = $_POST['sq_id'];
	$fieldParams = $_POST;

	$projectDesigner->updateField($form_name, $field_id, $fieldParams, $grid_name, $enable_ontology_auto_suggest_field);
/**
 * ADDING NEW QUESTION
 */
} else {
	$formName = $_POST['form_name'] ?? null;
	$fieldParams = $_POST;
	$next_field_name = $_POST['this_sq_id'] ?? null;
	$add_form_name = $_POST['add_form_name'] ?? null;
	$add_before_after = $_POST['add_before_after'] ?? null;
	$add_form_place = $_POST['add_form_place'] ?? null;

	$projectDesigner->createField($formName, $fieldParams, $next_field_name, $was_section_header, $grid_name, $add_form_name, $add_before_after, $add_form_place);
}

/*
print  "<script type='text/javascript'>window.parent.window.alert('";
foreach ($_POST as $key=>$value) { print "$key=>$value, "; }
print  "');</script>";
exit(close_html5());
 */




// RELOAD DESIGN TABLE if fields are not in proper order
// OR if matrix field was add/edited.
// OR if edited the Primary Key field.
// If not, reload table on page, else do insertRow into table.
if (($_POST['field_name'] == $table_pk) || $grid_name != "" || $Proj->checkReorderFields($metadata_table))
{
	// Reload form completely in order to associate section header with newly added field below it
	print  "<script type='text/javascript'>
			window.parent.window.reloadDesignTable('{$_POST['form_name']}');
			</script>";
}
// Reload whole page if Primary Key field's variable name was modified
elseif ($_POST['sq_id'] == $table_pk && $_POST['sq_id'] != $_POST['field_name'])
{
	print  "<script type='text/javascript'>
			window.parent.window.showProgress(1);
			window.parent.window.location.href = window.parent.window.app_path_webroot+window.parent.window.page+'?pid='+window.parent.window.pid+'&page='+window.parent.window.getParameterByName('page');
			</script>";
}
// Insert new row into table and close "Add/Edit Field" dialog pop-up
else
{
	// Insert row into table
	print  "<script type='text/javascript'>
			window.parent.window.insertRow('draggable', '{$_POST['field_name']}', $edit_question, $is_last, 0, $is_section_header, '$delete_row');
			</script>";
	// If an auto-suggest ontology field, then enable for this field
	if ($enable_ontology_auto_suggest_field) {
		print  "<script type='text/javascript'>
				window.parent.window.setTimeout(function(){
					window.parent.window.document.forms['form'].{$_POST['field_name']}.setAttribute('onclick', \"initWebServiceAutoSuggest('{$_POST['field_name']}',1);\");
				},500);
				</script>";
	}
	## If field_name was renamed AND other fields have branching logic dependent upon it, then give notice
	if (!$is_section_header && $edit_question && $_POST['sq_id'] != $_POST['field_name'])
	{
		// Check if the table_pk is being deleted. If so, give back different response so as to inform the user of change.
		$sql = "select field_name, element_label from $metadata_table where project_id = $project_id and (
				(element_type = 'calc' and
					(element_enum like '%[{$_POST['sq_id']}]%' or element_enum like '%[{$_POST['sq_id']}(%)]%')
				) or
				(branching_logic like '%[{$_POST['sq_id']}]%') or
				(branching_logic like '%[{$_POST['sq_id']}(%)]%')
				) order by field_order";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			$response = "";
			while ($row = db_fetch_assoc($q))
			{
				$response .= RCView::SP . RCView::SP . "-" . RCView::SP . RCView::b($row['field_name']) . RCView::SP
						   . "-" . RCView::SP . RCView::escape($row['element_label']) . RCView::br();
			}
			// Set message with list of fields
			$response = $lang['design_478'] . RCView::br() . RCView::br(). $lang['design_479']
					  . " (<b>{$_POST['sq_id']}</b> => <b>{$_POST['field_name']}</b>) " . $lang['design_480'] . RCView::br() . $response;
			// Display message in a popup
			print  "<script type='text/javascript'>
					window.parent.window.simpleDialog('".js_escape($response)."','".js_escape($lang['design_477'])."',null,650);
					</script>";
		}
	}
}

// Check if the table_pk has changed during this script. If so, give user a prompt alert.
if (Design::recordIdFieldChanged())
{
	print  "<script type='text/javascript'>
			window.parent.window.update_pk_msg(false,'field');
			</script>";
}


// SURVEY QUESTION NUMBERING (DEV ONLY): Detect if form is a survey, and if so, if has any branching logic. If so, disable question auto numbering.
if (Design::checkDisableSurveyQuesAutoNum($_POST['form_name']))
{
	// Give user a prompt as notice of this change
	print  "<script type='text/javascript'>
			setTimeout(function(){
				window.parent.window.alert(window.parent.window.lang.design_1257);
			},300);
			</script>";
}

// If user changes the equation of a calc field (in development only), give them a warning if SOME data is saved for that field
if ($status == '0' && $_POST['field_type'] == "calc" && trim(isset($Proj->metadata[$_POST['field_name']]) ? $Proj->metadata[$_POST['field_name']]['element_enum'] : '') != trim($_POST['element_enum']))
{
	// Check for any data for this calc field
	$q = db_query("select 1 from ".\Records::getDataTable($project_id)." where project_id = $project_id and field_name = '".db_escape($_POST['field_name'])."' limit 1");
	$hasData = (db_num_rows($q) > 0);
	// Equation changed AND it has data
	if ($hasData) {
		print  "<script type='text/javascript'>
				window.parent.window.simpleDialog('".js_escape($lang['design_515'])."','".js_escape($lang['design_514'])."');
				</script>";
	}
}

// Field Embedding: Get a list of all embedded fields on this instrument to toggle the button/div for each field
$Proj->loadMetadata();
$embeddedVarsThisInstrument = Piping::getEmbeddedVariables(PROJECT_ID, $_POST['form_name'], null, true);
$embeddedVarsThisField = Piping::getEmbeddedVariablesForField(PROJECT_ID, $_POST['field_name'], true);
foreach ($embeddedVarsThisInstrument as &$this_field) $this_field = "tr#".$this_field."-tr";
print  "<script type='text/javascript'>
		window.parent.window.toggleEmbeddedFieldsButtonDesigner('".implode(",", $embeddedVarsThisInstrument)."');
        setTimeout(function(){
		    window.parent.window.showDescriptiveTextImages();
            window.parent.window.enableDropdownAutocomplete();
            window.parent.window.initInlinePdfs();
            window.parent.window.initVidYardJS();
		},300);
		</script>";
// If a field is somehow embedded inside itself, display a warning to user
if (in_array($_POST['field_name'], $embeddedVarsThisField)) {
	print  "<script type='text/javascript'>
			window.parent.window.simpleDialog('".js_escape('<div class="yellow fs14">'.$lang['design_796']." <b>{".$_POST['field_name']."}</b> ".$lang['design_797'].'</div>')."');
			</script>";
}
close_html5();