<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
$ProjFields = ($status > 0) ? $Proj->metadata_temp : $Proj->metadata;
$ProjForms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;

if (isset($_GET["field"])) {
	$field = htmlspecialchars(strip_tags($_GET["field"]));
	if (array_key_exists($field, $ProjFields) 
		&& !empty($ProjFields[$field]['element_enum']) 
		&&  in_array($ProjFields[$field]["element_type"], ["radio", "yesno", "truefalse", "select", "checkbox"], true)) 
	{
		$code_label_pairs = explode("\n", str_replace("\\n", "\n", $ProjFields[$field]['element_enum']));
		$choices = array();
		foreach ($code_label_pairs as $pair) {
			$parts = explode(",", $pair, 2);
			$choices[] = [
				"code" => trim($parts[0]),
				"label" => trim($parts[1] ?? "")
			];
		}
		$json = json_encode_rc([
			"choices" => $choices
		]);
	}
	else {
		$json = json_encode_rc([
			"error" => $lang["design_1271"]
		]);
	}
}
else {
	$is_matrix = (isset($_GET['is_matrix']) && $_GET['is_matrix'] == '1') ? '1' : '0';
	$is_editor = (isset($_GET['is_editor']) && $_GET['is_editor'] == '1') ? '1' : '0';

	// Build rows
	$tr = [];
	foreach ($ProjFields as $field_name => $attr) {
		// Skip stuff
		if (empty($attr["element_enum"])) continue;
		if (ends_with($field_name, '_complete') && array_key_exists(substr($field_name, 0, -9), $ProjForms)) continue;
		if (!in_array($attr["element_type"], ["radio", "select", "checkbox"], true)) continue;
		// Build button
		$button_attrs = [
			"type" => "button",
			"class" => "btn btn-xs btn-primaryrc",
			"data-ec-source-field" => $field_name,
			"title" => js_escape2(RCView::tt_i("design_1310", [$field_name, strip_tags($ProjForms[$ProjFields[$field_name]["form_name"]]["menu"])], true, null)),
			"data-bs-toggle" => "tooltip",
		];
		if ($is_editor == "0") {
			$button_attrs["onclick"] = "existingChoicesClick('$field_name', $is_matrix);";
		}
		$button = RCView::button($button_attrs, RCView::tt("design_520"));
		// Build choices
		$choices = RCView::div([
				"id" => "ec_$field_name",
				"style" => "max-height:100px;overflow-y:auto;"
			], str_replace("\n", "<br>", RCView::escape(str_replace("\\n", "\n", $attr['element_enum']), false))
		);
		// Build row
		$tr[] = RCView::tr([], 
			RCView::td([
				"valign" => "top",
				"style" => "background:#f3f3f3;padding:12px 8px 4px;".
					"width:60px;text-align:center;border:1px solid #ccc;" .
					"border-right:0;border-bottom:0;"
			], $button) .
			RCView::td([
				"valign" => "top",
				"style" => "padding:6px 0;line-height:13px;background:#f3f3f3;" .
					"border:1px solid #ccc;border-left:0;border-bottom:0;"
			], $choices)
		);
	}
	// Add message in case there are no choices
	if (count($tr) == 0) {
		$tr[] = RCView::tr([], 
			RCView::td([
				"valign" => "top",
				"colspan" => "2",
				"style" => "background:#f3f3f3;padding:15px;color:#666;" .
					"text-align:center;border:1px solid #ccc;border-bottom:0;"
			], RCView::tt("design_521"))
		);
	}
	// Build content
	$content = 
		RCView::div([
				"class" => "mb-2"
			], 
			$is_editor == "1" ? RCView::tt("design_1311") : RCView::tt("design_519")
		) . 
		RCView::table([
				"cellpadding" => "0",
				"cellspacing" => "0",
				"style" => "width:100%;border-bottom:1px solid #ccc;"
			],
			join("\n", $tr)
		);

	// Return title and content as JSON
	$json = json_encode_rc(array(
		'title'		=> $lang['design_522'],
		'content'	=> $content
	));
}

header("Content-Type: application/json");
print $json;