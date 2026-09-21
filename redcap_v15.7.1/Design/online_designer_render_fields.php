<?php


//Get any variables passed by Post
if (isset($_POST['pnid']))  $_GET['pnid'] = $_POST['pnid'];
if (isset($_POST['pid']))   $_GET['pid']  = $_POST['pid'];
if (isset($_POST['page'])) 	$_GET['page'] = $_POST['page'];

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$ProjForms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;
$ProjFields = ($status > 0) ? $Proj->metadata_temp : $Proj->metadata;
if (!is_array($ProjForms)) $ProjForms = [];
if (!is_array($ProjFields)) $ProjFields = [];

// Validate PAGE
if (!isset($_GET['page']) || !array_key_exists($_GET['page'], $ProjForms)) {
	exit("ERROR!");
}
// Validate field_name (if set)
if (isset($_GET['field_name']) && !array_key_exists($_GET['field_name'], $ProjFields)) {
	exit("ERROR!");
}
// Get all fields on this form and render them here as editable table
$metadata = [];
foreach ($ProjForms[$_GET['page']]['fields'] as $this_field => $_) {
	$metadata[] = $ProjFields[$this_field];
}

//Render form as editable table
if (count($metadata) > 0) {

	//Replace any single or double quotes since they cause rendering problems
	$orig_quote = array("'", "\"");
	$repl_quote = array("&#039;", "&quot;");

	//Collect any "sql" field types
	$sql_fields = array();

	// Set default
	$prev_grid_name = "";
    $grid_rank  = '0';
	
	// ACTION TAGS: Create regex string to detect all action tags being used in the Field Annotation
	$action_tags_regex_od = Form::getActionTagMatchRegexOnlineDesigner();
	// Display all action tags, including those not bundled in REDCap (i.e., from External Modules)
	$action_tags_regex_all = Form::getActionTagMatchRegexOnlineDesignerInformational($Proj->project_id);

	// Find all variables that are embedded/embedding on THIS instrument
	list($embeddedFields, $embeddingFields) = Piping::getEmbeddedVariablesMap(PROJECT_ID, $_GET['page'], true);

	/**
	 * Field metadata needed by the Online Designer's Quick-modify fields(s) tool
	 */
	$qef_meta = [];
    $elements1 = [];
    $key = -1;
	//Render each table row
	foreach ($metadata as $row)
	{
        $key++;
		$field_name = $row['field_name'];
		$element_preceding_header = $row['element_preceding_header'];
		$element_type = $row['element_type'];
		$element_label = str_replace($orig_quote, $repl_quote, $row['element_label'] ?? "");
		$element_enum = str_replace($orig_quote, $repl_quote, $row['element_enum'] ?? "");
		$element_note = str_replace($orig_quote, $repl_quote, $row['element_note'] ?? "");
		$element_validation_type = $row['element_validation_type'];
		$element_validation_min = $row['element_validation_min'];
		$element_validation_max = $row['element_validation_max'];
		$element_validation_checktype = $row['element_validation_checktype'];
		$branching_logic = trim($row['branching_logic'] ?? "");
		$field_req = $row['field_req'];
		$field_phi = $row['field_phi'];
		$edoc_id = $row['edoc_id'];
		$edoc_display_img = $row['edoc_display_img'];
		$stop_actions = (isset($Proj->forms[$_GET['page']]['survey_id'])) ? DataEntry::parseStopActions($row['stop_actions'] ?? "") : "";
		$custom_alignment = $row['custom_alignment'];
		$grid_name = trim($row['grid_name'] ?? "");
		$grid_rank = ($row['grid_rank'] === '1') ? '1' : '0';
		$video_url = trim($row['video_url'] ?? "");
		$video_display_inline = trim($row['video_display_inline'] ?? "");

		$qef_meta["design-".$field_name] = array(
			"name" => $field_name,
			"isFormStatus" => $field_name == $_GET['page']."_complete",
			"hasSectionHeader" => !empty($element_preceding_header),
			"type" => $element_type,
			"validation" => $element_validation_type,
			"hasBranchingLogic" => !empty($branching_logic),
			"hasAttachment" => $edoc_id && $edoc_id > 0,
			"hasVideo" => !empty($video_url),
			"hasStopActions" => !empty($stop_actions),
			"customAlignment" => $custom_alignment ?? "RV",
			"isRequired" => $field_req == 1,
			"isPHI" => $field_phi == 1,
			"hasAnnotation" => !empty($row["misc"]),
			"hasActionTags" => preg_match($action_tags_regex_all, $row["misc"]??""),
			"misc" => $row["misc"] ?? "",
			"isMatrixField" => !empty($grid_name),
			"matrixGroup" => $grid_name ?? "",
			"questionNum" => $row["question_num"],
			"order" => intval($row["field_order"]),
		);
		// Do not process the Form Status field
		if ($_GET['page'] . "_complete" == $field_name) {
			continue;
		}
		// If rendering only a single row, skip rest
		if (isset($_GET['field_name']) && $_GET['field_name'] != $field_name) {
			continue;
		}

		$fieldIsEmbeddedOnInstrument = array_key_exists($field_name, $embeddedFields);

		## MATRIX QUESTION GROUPS
		$isMatrixField = false; //default
		// Beginning a new grid
        $matrixGroupPosition = "";
		if ($grid_name != "" && $prev_grid_name != $grid_name)
		{
			// Set flag that this is a matrix field
			$isMatrixField = true;
			// Set that field is the first field in matrix group
			$matrixGroupPosition = '1';
		}
		// Continuing an existing grid
		elseif ($grid_name != "" && $prev_grid_name == $grid_name)
		{
			// Set flag that this is a matrix field
			$isMatrixField = true;
			// Set that field is *not* the first field in matrix group
			$matrixGroupPosition = 'X';
		}
		// Set value for next loop
		$prev_grid_name = $grid_name;


		//if this data field specifies a 'header' separator - process this first
		$hasSHtag = false;
		if ($element_preceding_header && ($field_name != $table_pk) && ((!isset($_GET['edit_question']) || !$_GET['edit_question']) || (isset($_GET['section_header']) && $_GET['section_header'])))
		{
            $elements1[$key] = ['rr_type'=>'header', 'css_element_class'=>'header', 'field'=>"{$field_name}-sh"];
			// Tag if this field has a section header attached to it
			$hasSHtag = true;
			// IF a matrix field, then set flag in this element
			if ($isMatrixField) {
                $elements1[$key]['matrix_field'] = $matrixGroupPosition;
                $elements1[$key]['grid_name'] = $grid_name;
				$shIcons = "";
			} else {
				$shIcons = 
					"<div class='frmedit' style='padding-bottom:4px;'>
						<a href='javascript:;' onclick='openAddQuesForm(\"$field_name\",\"$element_type\",1,0);' data-rc-lang-attrs='title=design_1154' title='".RCView::tt_js("design_1154")."' data-bs-toggle='tooltip' class='field-action-link' data-field-action='edit-field'>".RCIcon::OnlineDesignerEdit()."</a>
						<a href='javascript:;' onclick='copySectionHeader(\"$field_name\");' data-rc-lang-attrs='title=design_1155' title='".RCView::tt_js("design_1155")."' data-bs-toggle='tooltip' class='field-action-link' data-field-action='copy-field'>".RCIcon::OnlineDesignerCopy()."</a>
						<a href='javascript:;' onclick='moveField(\"$field_name-sh\", \"\");' data-rc-lang-attrs='title=design_1327' title='".RCView::tt_js("design_1327")."' data-bs-toggle='tooltip' class='field-action-link' data-field-action='move-sh' draggable='sh'>".RCIcon::OnlineDesignerMove()."</a>
						<a href='javascript:;' onclick='deleteField(\"$field_name\",1);' data-rc-lang-attrs='title=design_1156' title='".RCView::tt_js("design_1156")."' data-bs-toggle='tooltip' class='field-action-link' data-field-action='delete-field'>".RCIcon::OnlineDesignerDelete()."</a>
					</div>";
			}
			$element_preceding_header = filter_tags(nl2br($element_preceding_header));
            $elements1[$key]['value'] = "$shIcons<div class=\"sh-content\">$element_preceding_header</div>";
			// If only editing/adding a single section header, stop this loop here
			if (isset($_GET['section_header']) && $_GET['section_header']) continue;
            // Increment key to begin new row
            $key++;
		}

		//process the true data element
		if ($element_type == 'sql') {
            $elements1[$key] = ['rr_type'=>'select', 'field'=>$field_name, 'name'=>$field_name];
			//Add to array of sql field type fields
			$sql_fields[] = $field_name;
		} else {
            $elements1[$key] = ['rr_type'=>$element_type, 'field'=>$field_name, 'name'=>$field_name];
		}

		// IF a matrix field, then set flag in this element
		if ($isMatrixField) {
            $elements1[$key]['matrix_field'] = $matrixGroupPosition;
            $elements1[$key]['grid_name'] = $grid_name;
            $elements1[$key]['grid_rank'] = $grid_rank;
		}

		// Tag if this field has a section header attached to it
		if ($hasSHtag) {
            $elements1[$key]['hasSH'] = '1';
		}

		//Process required field status (add note underneath field label)
		if ($field_req == '1' && $element_type != 'descriptive') {
			$fieldReqClass = ($isMatrixField) ? 'requiredlabelmatrix' : 'requiredlabel'; // make matrix fields more compact
			$element_label .= "<div class='$fieldReqClass'>* ".RCView::tt("data_entry_39")."</div>";
		}

		//FIELD LABEL
        $elements1[$key]['label'] = nl2br($element_label);

		// Custom alignment
        $elements1[$key]['custom_alignment'] = $custom_alignment;

		// Identifier?
        $elements1[$key]['field_phi'] = $field_phi;
		
		// If field_annotation has @, then assume it might be an action tag
		if ($row['misc'] != null && strpos($row['misc'], '@') !== false) {
			// Match triggers via regex (live preview)
			preg_match_all($action_tags_regex_od, $row['misc'], $this_misc_match);
			if (isset($this_misc_match[1]) && !empty($this_misc_match[1])) {
                $elements1[$key]['action_tag_class'] = implode(" ", $this_misc_match[1]);
			}
			$tagNames = [];
			// Match triggers via regex
			preg_match_all($action_tags_regex_all, $row['misc'], $this_misc_match);
			if (isset($this_misc_match[1]) && !empty($this_misc_match[1])) {
				foreach ($this_misc_match[1] as $tag) {
					$tagNames[] = $tag;
				}
			}
			$tagNames = array_unique($tagNames);
			natcasesort($tagNames);
			$elements1[$key]['action_tag_class_design'] = implode(" ", $tagNames);
		}

		//For elements of type 'text', we'll handle data validation if details are provided in metadata
		if ($element_type == 'text' || $element_type == 'calc') {
			if($element_validation_type){
                $elements1[$key]['validation'] = $element_validation_type;
                $valCheckType = $element_validation_checktype ? js_escape($element_validation_checktype) : "soft_typed";
                $elements1[$key]['onblur'] = "redcap_validate(this,'$element_validation_min','$element_validation_max','$valCheckType','$element_validation_type')";
			}
			// ONTOLOGY AUTO-SUGGEST
			elseif ($element_type == 'text' && $element_enum != '' && strpos($element_enum, ":") !== false) {
                $elements1[$key]['element_enum'] = $element_enum;
			}
		}

		// Add $element_validation_type for FILE fields (for signatures only) and SELECT fields (for auto-complete)
		if (($element_type == 'file' || $element_type == 'select' || $element_type == 'sql') && $element_validation_type != '') {
            $elements1[$key]['validation'] = $element_validation_type;
		}

		// Add edoc_id, if a Descriptive field has an attachement or video url
		if ($element_type == 'descriptive') {
			if (is_numeric($edoc_id)) {
                $elements1[$key]['edoc_id'] = $edoc_id;
                $elements1[$key]['edoc_display_img'] = $edoc_display_img;
			} elseif ($video_url != '') {
                $elements1[$key]['video_url'] = strip_tags(label_decode($video_url));
                $elements1[$key]['video_display_inline'] = $video_display_inline;
			}
		}

		// Add slider labels & and display value option
		if ($element_type == 'slider') {
			$slider_labels = Form::parseSliderLabels($element_enum);
            $elements1[$key]["slider_labels"] = array(
                decode_filter_tags($slider_labels['left']),
                decode_filter_tags($slider_labels['middle']),
                decode_filter_tags($slider_labels['right']),
                $element_validation_type
            );
            $slider_min = (is_numeric($Proj->metadata[$field_name]['element_validation_min']) ? $Proj->metadata[$field_name]['element_validation_min'] : 0);
            $slider_max = (is_numeric($Proj->metadata[$field_name]['element_validation_max']) ? $Proj->metadata[$field_name]['element_validation_max'] : 100);
            $elements1[$key]['slider_min'] = $slider_min;
            $elements1[$key]['slider_max'] = $slider_max;
		}

		//For elements of type 'select', we need to include the $element_enum information
		if ($element_type == 'truefalse' || $element_type == 'yesno' || $element_type == 'select' || $element_type == 'radio' || $element_type == 'checkbox' || $element_type == 'sql')
		{
			//Add any checkbox fields to array to use during data pull later to fill form with existing data
			if ($element_type == 'checkbox') $chkbox_flds[$field_name] = "";

			// Stop Actions
			if ($element_type == 'sql') {
				$element_enum = getSqlFieldEnum($element_enum);
			}
			$element_enum = DataEntry::cleanLabel($element_enum);
			// If stop actions exist, add labels to element_enum and set back to original formatting (exclude matrix fields due to matrix header complexity)
			if (!empty($stop_actions) && !$isMatrixField)
			{
				$element_enum_temp = array();
				foreach (parseEnum($element_enum) as $this_key=>$this_choice)
				{
					// Append "end survey" string to choice if a stop action exists
					if (in_array($this_key, $stop_actions)) {
						$this_choice .= " ".RCView::tt("design_211", "span", array("class"=>"stopnote"));
					}
					$element_enum_temp[] = "$this_key, $this_choice";
				}
				// Now set element_enum back again
				$element_enum = implode("\\n", $element_enum_temp);
			}
			// Add to string data
			$element_enum = str_replace(array('"',"'"), array('&quot;',"&#039;"), $element_enum);
            $elements1[$key]['enum'] = $element_enum;
		}

		//If an element_note is specified, we'll utilize here:
		if ($element_note) {
            $elements1[$key]['note'] = decode_filter_tags($element_note);
		}

		//For elements of type 'textarea', we need to specify the number of rows to include
		//Note that we used to use $element_other for this, but probably not necessary
		if ($element_type == 'textarea'){
            $elements1[$key]['rows'] = '2';
            $elements1[$key]['style'] = 'width:97%;';
		}

		// If branching logic exists, add to element in order to display that it exists on Online Form Editor
		$branching_logic_trunc = str_replace(array("\t", "\r", "\n"), array(" ", " ", " "), br2nl(label_decode($branching_logic, false)));
		if (mb_strlen($branching_logic_trunc) > 65) $branching_logic_trunc = mb_substr($branching_logic_trunc, 0, 63)."...";
		$branching_logic_trunc = htmlspecialchars($branching_logic_trunc, ENT_QUOTES);
        $branchingVisibility = ($branching_logic == "" ? "hidden" : "visible");
        $elements1[$key]['branching_logic'] = "<span id=\"bl-label_{$field_name}\" class=\"bledit\" style=\"visibility:{$branchingVisibility};\"><i>"
                                            . RCView::tt("design_731")."</i> <span data-kind=\"branching-logic\" id=\"bl-log_{$field_name}\">$branching_logic_trunc</span></span>";

		// If this field is embedded somewhere on this instrument, then give a note about it
		$fieldIsEmbeddedOnInstrument1 = $fieldIsEmbeddedOnInstrument ? '' : 'hide';
		$fieldContainsEmbeddedFields = isset($embeddingFields[$field_name]);
		$fieldContainsEmbeddedFields1 = $fieldContainsEmbeddedFields ? '' : 'hide';
		$gotoEmbed = $embeddedFields[$field_name] ?? '';
		if ($field_name != $Proj->table_pk) {
            $elements1[$key]['field_embed'] = "<button type='button' onclick=\"gotoEmbed('{$gotoEmbed}')\" var=\"$field_name\" class=\"$fieldIsEmbeddedOnInstrument1 rc-field-embed-designer float-end me-1 bg-success text-white\" style=\"padding:1px 3px;\"> <i class=\"fas fa-info-circle\"></i> ".RCView::tt("design_793")."</button>";
			if ($fieldContainsEmbeddedFields) {
                $elements1[$key]['field_embed_container'] = "<div var=\"$field_name\" class=\"rc-field-embed-parent-designer float-end me-1 btn-primaryrc text-white\" style=\"padding:1px 3px;\"> <i class=\"fas fa-info-circle\"></i> ".RCView::tt("design_996")."</div>";
			}
		}

	}
}

// Render table or row
$field_types = base64_encode(json_encode($qef_meta, JSON_UNESCAPED_UNICODE));
print (PAGE == "Design/online_designer.php" || (isset($_GET['ordering']) && $_GET['ordering'])) ? "<div id='draggablecontainer'>" : "<div>";
DataEntry::renderForm($elements1);
print "<div data-qef-field-types-json style='display:none;'>$field_types</div>";
print "</div>";