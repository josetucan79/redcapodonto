<?php


define("NOAUTH", true);
if (isset($_GET['pid'])) {
	include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
} else {
	include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
}

$replaceSeparator = "|-RC-COLON-|";
$smartVarsInfo = Piping::getSpecialTagsInfo();

// Build list of all action tags
$smart_var_descriptions = 
	RCView::tr(["class" => "smart-var-sticky-margin"],
		RCView::td(["colspan" => "4"],"")
	) .
	RCView::tr([
			"class" => "smart-var-header"
		],
		RCView::th([
				"rowspan" => "2",
				"class" => "smart-var-name wrap"
			], RCView::tt("piping_25") // Name of Smart Variable
		) .
		RCView::th([
				"rowspan" => "2",
				"class" => "smart-var-desc"
			], RCView::tt("global_20") // Description
		) .
		RCView::th([
				"colspan" => "2",
				"class" => "smart-var-usage",
			], RCView::tt("piping_26") // Example of Usage
		)
	) .
	RCView::tr(["class" => "smart-var-subheader"],
		RCView::th([], 
			RCView::tt("piping_27") // Example input
		).
		RCView::th([], 
			RCView::tt("piping_28") // Example output
		)
	);
	
foreach ($smartVarsInfo as $catname=>$attr0) 
{
	// Add more video and text for Smart Charts/Functions/Tables
	if ($catname == $lang['global_181']) {
		$catname =  RCView::div(array('class'=>'float-end'), "<a onclick=\"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=smart_charts01.mp4&referer=".SERVER_NAME."&title={$lang['training_res_104']}','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');\" href=\"javascript:;\" style=\"font-size:12px;text-decoration:underline;font-weight:normal;\"><i class=\"fas fa-film me-1\"></i>{$lang['training_res_107']} (14 {$lang['calendar_12']})</a>") .
			        RCView::div(array('class'=>'float-start'), $lang['global_181'] . RCView::br() . RCView::span(array('class'=>'font-weight-normal fs12'), $lang['global_230']));
	}
	// Category header
	$smart_var_descriptions .=
			RCView::tr([
					"data-tag" => "",
					"class" => "smart-var-category"
				], 
				RCView::td(["colspan" => "4"], $catname)
			);
    // Loop through all items in this category
	foreach ($attr0 as $tag_name=>$attr) 
	{
		$description = array_shift($attr);
		$examplesCount = count($attr);
		$example = array_shift($attr);
		// Make the parameters that follow the colon a lighter color
		$tag = str_replace(":", $replaceSeparator, $tag_name);
		$tagParts = explode($replaceSeparator, $tag);
		$tag = array_shift($tagParts);
		$tag = "<span class='wrap'>$tag</span>";
		if (count($tagParts) > 0) {
			$tag .= "<span style='color:#ca8a00;'>$replaceSeparator" . implode($replaceSeparator, $tagParts) . "</span>";
			// Make "Custom Text" a different color text
			if ((isset($tagParts[0]) && strpos($tagParts[0], "Custom Text") !== false) || (isset($tagParts[1]) && strpos($tagParts[1], "Custom Text") !== false)) {
				$tag = str_replace($replaceSeparator . "Custom Text", "<span style='color:rgba(128, 0, 0, 0.70);'>" . $replaceSeparator . "Custom Text</span>", $tag);
			}
		}
		if (count($tagParts) > 1) {
			$tag = str_replace($replaceSeparator . $tagParts[1], "<span style='color:rgba(128, 0, 0, 0.70);'>" . $replaceSeparator . $tagParts[1] . "</span>", $tag);
			if ((isset($tagParts[1]) && strpos($tagParts[1], "parameters") !== false) || (isset($tagParts[2]) && strpos($tagParts[2], "parameters") !== false)) {
				$tag = str_replace($replaceSeparator . "parameters", "<span style='color:rgba(1, 84, 187, 0.70);'>" . $replaceSeparator . "parameters</span>", $tag);
			}
			if (strpos($tagParts[0], "_____") !== false) {
				$tag = str_replace($replaceSeparator . $tagParts[1], "<span style='color:rgba(1, 84, 187, 0.70);'>" . $replaceSeparator . $tagParts[1] . "</span>", $tag);
			}
		}
        // Add spaces after any commas
		$tag = str_replace(",", ", ", $tag);
		// Put some spacing around colons for easier reading
		$tag = str_replace($replaceSeparator, "<span style='margin:0 2px;'>:</span>", $tag);
		// Output row
		$smart_var_descriptions .=
			RCView::tr(["data-tag" => $tag_name],
				RCView::td([
						"data-cell" => "name",
						"rowspan" => $examplesCount
					], RCView::span(["class" => "tag-name"], $tag)
				) .
				RCView::td([
						"data-cell" => "desc",
						"rowspan" => $examplesCount
					], $description
				) .
				RCView::td(["data-cell" => "input"], $example[0]) .
				RCView::td(["data-cell" => "output"], $example[1])
			);
		// Add extra examples
		foreach ($attr as $example) {
			$smart_var_descriptions .=
				RCView::tr(["data-tag" => $tag_name],
					RCView::td(["data-cell" => "input"], $example[0]) .
					RCView::td(["data-cell" => "output"], $example[1])
				);
		}
	}
}

$font_size = $isAjax ? "13px" : "14px";
$su = isset($_GET["su"]) && $_GET["su"] == "1" ? "?su=1" : "";
$scroll_selector = $isAjax ? "#smart_variable_explain_popup" : "body";
// Content
$content = 
	($isAjax ? 
		RCView::div(["class" => "smart-var-title"],
			RCView::h1([],
				RCView::span(["class" => "smart-var-icon mr-2"], RCView::fa("fas fa-bolt fa-xs")) .
				RCView::tt("global_146") // Smart Variables
			) .
			RCView::tt("survey_977", "a", [ // View text on separate page
				"class" => "ml-auto",
				"href" => PAGE_FULL . $su, 
				"target" => "_blank"
			])
		) : ""
	) . 
	// Instructions
	RCView::div([
		"id" => "smart-var-filter"
	], 
		RCView::div([
			"class" => "input-group input-group-sm sm-search"
		], 
			RCView::input([
				"type" => "text",
				"class" => "form-control filter-text fs12 initial-focus",
				"placeholder" => RCView::tt_attr("design_1069") // Filter smart variables
			]) . 
			RCView::span([
				"class" => "input-group-text fs12"
			], RCView::fa("fa-solid fa-filter")) .
			RCView::button([
				"class" => "btn btn-secondary btn-clear-search fs12"
			], RCView::fa("fa-solid fa-filter-circle-xmark"))
		) .
		RCView::input([
			"type" => "checkbox",
			"class" => "ml-2",
			"name" => "include-desc",
			"id" => "sm-search-include-desc"
		]) .
		RCView::label([
			"class" => "ml-1",
			"for" => "sm-search-include-desc"
		], 
			RCView::tt("design_1068") // Also search in descriptions
		)
	) .
	RCView::div(["class" => "smart-var-explanation"], 
		RCView::tt("design_737", "h2") . // A review of REDCap Field Variables and Field Notation
		RCView::tt("design_738", "p") .  // In REDCap, all fields ...
		RCView::tt("design_739", "h2") . // An introduction to Smart Variables
		RCView::tt("design_740", "p") .  // In REDCap Field Notation, variable names ...
		RCView::tt("design_746", "p") .  // Smart Variables can be used...
		RCView::ul([],
			RCView::tt("design_743", "li") . // On their own ...
			RCView::tt("design_744", "li") . // In conjunction with field variables ...
			RCView::tt("design_745", "li")   // In conjunction with other Smart Variables ...
		) .
		RCView::tt("design_741", "h2") . // How and where to use Field Notation & Smart Variables
		RCView::tt("design_742", "p") .  // Field notation (whether referencing ...
		RCView::tt("design_752", "p") .  // Field notation and Smart Variables can be used for...
		RCView::ul([],
		RCView::tt("design_749", "li") . // Calculated fields ...
		RCView::tt("design_767", "li") . // Conditional logic ...
		RCView::tt("design_751", "li")   // Piping ...
		) .
		RCView::tt("design_766", "p") .  // NOTE: While Smart Variables ...
		RCView::tt("global_302", "p") .  // Blank values: ...
		(isset($_GET["su"]) && $_GET["su"] == "1" ?
			RCView::tt("design_765", "p") // ADMINISTRATOR NOTE ...
			: "" 
		) .
		RCView::tt("piping_39", "h2") .  // Smart Variable List
		RCView::tt("piping_40", "p")     // Listed below ...
	) .
	RCView::style(<<<END
		#smart-var-filter {
			display: flex;
			align-items: center;
		}
		#smart-var-filter .input-group {
			max-width: 300px;
		}
		#smart-var-filter label {
			margin: 0;
		}
		#smart-var-filter .btn-clear-search {
			background-color: var(--bs-gray-200);
			border: 1px solid #ced4da;
			color: var(--bs-danger);
		}
		#smart-var-filter .sm-search > input ~ span,
		#smart-var-filter .sm-search > input:placeholder-shown ~ button.btn-clear-search {
			display: none;
			border-top-right-radius: .25rem;
			border-bottom-right-radius: .25rem;
		}
		#smart-var-filter .sm-search > input:placeholder-shown ~ span, 
		#smart-var-filter .sm-search > input ~ button.btn-clear-search {
			display: block;
		}
		.smart-var-explanation {
			display: none;
		}
		#smart-var-filter:has(input:placeholder-shown) ~ .smart-var-explanation {
			display: block;
		}
		$scroll_selector {
			overflow-y: scroll;
		}
	END) .
	// Table
	RCView::div([],
		RCView::table([
			"class" => "smart-var-table"
		], $smart_var_descriptions)
	) . 
	RCView::iife(<<<END
		const search = $('#smart-var-filter');
		const rows = [];
		$('table.smart-var-table tr').each(function() {
			const tr = $(this);
			const tag = tr.attr('data-tag') ?? '';
			const name = tr.find('[data-cell="name"]').text().toLowerCase();
			const desc = tr.find('[data-cell="desc"]').text().toLowerCase();
			if (tag != '') {
				rows.push({ tag: tag, name: name, all: name + ' ' + desc });
			}
		});
		search.find('.btn-clear-search').on('click', () => search.find('input.filter-text').val('').trigger('keyup'));
		search.find('input[type=checkbox][name=include-desc]')
		search.find('input.filter-text').on('keyup', function(e) {
			const searchText = e.target.value.toLowerCase();
			const scope = search.find('input[type=checkbox][name=include-desc]').prop('checked') ? 'all' : 'name';
			const tagsToShow = rows.filter((i) => searchText == '' || i[scope].includes(searchText)).map((i) => i.tag);
			$('table.smart-var-table tr[data-tag]').each(function() {
				const action = searchText == '' || tagsToShow.includes(this.dataset.tag) ? 'remove' : 'add';
				this.classList[action]('hide');
			});
		});
		$('table.smart-var-table span.tag-name').on('click', function() {
			copyTextToClipboard('[' + this.textContent + ']');
			this.classList.add('text-copied');
			setTimeout(() => this.classList.remove('text-copied'), 300);
		});
		$(() => search.find('input.filter-text').trigger('focus'));
	END) .
	RCView::style(<<<END
		body {
			--header-top: 0;
			--subheader-top: 2.4em;
		}
		#smart_variable_explain_popup {
			--header-top: .2em;
			--subheader-top: 2.7em;
		}
		.smart-var-table {
			font-size: $font_size;
			table-layout: fixed;
			width: 100%;
			margin-top: 1.5em;
		}
		.smart-var-table .smart-var-sticky-margin td {
			border: none;
		}
		#smart_variable_explain_popup .smart-var-table .smart-var-sticky-margin td {
			position: sticky;
			top: 0;
			height: .3em;
			background-color: white;
		}
		.smart-var-table th,
		.smart-var-table td {
			border: 1px solid #cccccc;
		}
		.smart-var-header th,
		.smart-var-subheader th {
			padding: .5em;
			font-weight: 700;
			background-color: #e5e5e5;
			position: sticky;
			top: var(--header-top);
		}
		.smart-var-subheader th {
			font-weight: 400;
			top: var(--subheader-top);
			text-align: center;
		}
		#smart_variable_explain_popup {
			padding-top: 1px !important;
		}
		#smart_variable_explain_popup .smart-var-title {
			margin-top: 1em;
		}
		th.smart-var-name {
			width: 350px;
		}
		th.smart-var-usage {
			width: 300px;
			text-align: center;
		}
		.smart-var-category td {
			font-size: 14px;
			font-weight: 700;
			color: #800000;
			background-color: #ffffe0;
			padding: 10px;
		}
		td[data-cell] {
			padding: .5em;
		}
		td[data-cell="name"] {
			color: green;
			font-weight: 700;
		}
		td[data-cell="desc"] {
			font-size: 90%;
		}
		td[data-cell="input"],
		td[data-cell="output"] {
			font-size: 85%;
			color: #666;
		}
		td[data-cell="output"] {
			word-break: break-word;
		}
		.tag-name {
			cursor: pointer;
		}
		.smart-var-icon::before,
		.tag-name::before {
			content: '[';
			margin-right: 1px;
		}
		.smart-var-icon::after,
		.tag-name::after {
			content: ']';
			margin-left: 1px;
		}
		.text-copied {
			background-color: lightgoldenrodyellow;
		}
		.smart-var-title {
			display: flex;
			align-items: start;
		}
		h1 {
			font-size: 18px;
			font-weight: 700;
			color: green;
			margin: 0 0 1em 0;
		}
		h2 {
			font-weight: 700;
			font-size: 14px;
			margin: 1em 0 .5em 0;
		}
		p {
			margin: 0 0 .5em 0;
	END);

if ($isAjax) {	
	// Return JSON
	header("Content-Type: application/json");
	print json_encode_rc([
		"content" => $content, 
		"title" => $lang['global_146'] // Smart Variables
	]);
} else {
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
	print 
		RCView::div(["class" => "smart-var-title"],
			RCView::h1([],
				RCView::span(["class" => "smart-var-icon mr-2"], RCView::fa("fas fa-bolt fa-xs")) .
				RCView::tt("global_146") // Smart Variables
			) .
			RCView::img([
				"class" => "ml-auto",
				"src" => "redcap-logo.png"
			])
		) .
		$content .
		// Style overrides (must be at end)
		RCView::style(<<<END
			#pagecontainer { max-width:1100px; }
			h1 { margin: .5em 0 .75em 0; font-size: 24px; }
		END);
	$objHtmlPage->PrintFooterExt();
}
